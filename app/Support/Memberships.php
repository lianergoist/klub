<?php

namespace App\Support;

use App\Http\Controllers\Api\FinanceController;
use App\Models\Invoice;
use App\Models\MembershipType;
use App\Models\User;
use App\Notifications\ClubMessage;

/**
 * Kontingent pr. træningssæson (1. maj – 30. april), beregnet pr. husstand:
 *  - 1–2 personer: enkeltkontingent pr. person (halv pris ved indmeldelse fra 1. august)
 *  - 3 personer: familiekontingent (3); 4+: familiekontingent (4+)
 *  - støttemedlemmer betaler deres egen pris og tæller ikke med i husstanden
 * Medlemskab hænger på betaling: ny indmeldelse og tilføjelse af personer træder først i kraft, når der er betalt.
 */
class Memberships
{
    public static function type(string $code): MembershipType
    {
        return MembershipType::where('code', $code)->firstOrFail();
    }

    public static function typeFor(?string $birthdate, bool $passive = false): MembershipType
    {
        return self::type($passive ? 'passive' : 'single');
    }

    /** Aktive (ikke-støtte) medlemmer i husstanden, der tæller med i kontingentet. */
    public static function householdCount(User $payer): int
    {
        if (! $payer->household_id) return 1;

        return User::where('household_id', $payer->household_id)->whereIn('status', ['active', 'pending'])
            ->whereHas('membershipType', fn ($q) => $q->where('code', '!=', 'passive'))->count();
    }

    /** Betalt kontingent (minus refusioner) for husstanden i sæsonen. */
    public static function paidThisSeason(User $payer, ?string $season = null): int
    {
        $season ??= Season::label();
        $ids = $payer->household_id ? User::where('household_id', $payer->household_id)->pluck('id') : collect([$payer->id]);

        return (int) Invoice::where('kind', 'membership')->where('period', $season)->where('status', 'paid')
            ->where(fn ($q) => $q->whereIn('payer_id', $ids)->orWhereIn('member_id', $ids))
            // støttemedlemmers egne regninger tæller ikke med (NB: regninger for hele husstanden har member_id = NULL)
            ->where(fn ($q) => $q->whereNull('member_id')->orWhereNotIn('member_id', User::whereIn('id', $ids)->whereHas('membershipType', fn ($q) => $q->where('code', 'passive'))->pluck('id')))
            ->get()->sum(fn ($i) => $i->amount + $i->payments()->where('amount', '<', 0)->sum('amount'));
    }

    public static function signupInvoice(User $u): Invoice
    {
        $type = $u->membershipType;
        $season = Season::label();
        $half = $type->code !== 'passive' && Season::isHalf();
        $amount = $type->code === 'passive' ? Prices::get('membership.passive') : Prices::membership(1);

        return Invoice::create([
            'payer_id' => $u->id, 'member_id' => $u->id, 'kind' => 'membership', 'period' => $season,
            'description' => "Kontingent $season – {$type->name}".($half ? ' (halv sæson)' : '')." – {$u->name}",
            'amount' => $amount, 'due_on' => now(), 'account_id' => FinanceController::accountId('1000'),
        ]);
    }

    /** Hvad koster det at tilføje $extra personer til husstanden resten af sæsonen? */
    public static function addQuote(User $payer, int $extra): int
    {
        return max(0, Prices::membership(self::householdCount($payer) + $extra) - self::paidThisSeason($payer));
    }

    public static function openUpgrade(User $payer): ?Invoice
    {
        return Invoice::where('payer_id', $payer->id)->where('status', 'open')->where('kind', 'membership')
            ->whereNotNull('payload')->latest('id')->get()->first(fn ($i) => ($i->payload['action'] ?? null) === 'family_upgrade');
    }

    /** Opret/udvid regning for tilføjelse af personer. Intet oprettes før betaling. */
    public static function requestFamilyUpgrade(User $payer, array $person): Invoice
    {
        $inv = self::openUpgrade($payer);
        $season = Season::label();
        $people = array_merge($inv?->payload['members'] ?? [], [$person]);
        $n = self::householdCount($payer) + count($people);
        $names = implode(', ', array_column($people, 'name'));
        $data = [
            'payload' => ['action' => 'family_upgrade', 'members' => $people],
            'amount' => self::addQuote($payer, count($people)),
            'description' => "Kontingent $season – ".self::householdLabel($n)." (tilføjer $names)",
        ];
        if ($inv) {
            $inv->update($data);

            return $inv;
        }

        return Invoice::create($data + [
            'payer_id' => $payer->id, 'member_id' => null, 'kind' => 'membership', 'period' => $season,
            'due_on' => now(), 'account_id' => FinanceController::accountId('1000'),
        ]);
    }

    public static function householdLabel(int $n): string
    {
        return $n >= 3 ? "Familie ($n personer)" : ($n === 2 ? 'Husstand (2 × enkelt)' : 'Enkelt medlem');
    }

    /** Tilføj personer til husstanden (efter betaling, eller med det samme hvis det ikke koster noget). */
    public static function addToHousehold(User $payer, array $people): array
    {
        $added = [];
        foreach ($people as $p) {
            $added[] = User::create([
                'name' => $p['name'], 'birthdate' => $p['birthdate'], 'email' => $p['email'] ?? null,
                'emergency_name' => $p['emergency_name'] ?? $payer->name, 'emergency_phone' => $p['emergency_phone'] ?? $payer->phone,
                'household_id' => $payer->household_id, 'membership_type_id' => self::type('single')->id, 'status' => 'active',
                'joined_on' => now(), 'consent_at' => $p['consent_at'] ?? now(), 'notify_email' => false,
                'address' => $payer->address, 'postal_code' => $payer->postal_code, 'city' => $payer->city,
            ]);
        }
        self::syncHouseholdTypes($payer);
        Audit::log('household.members_added', $payer, ['added' => array_map(fn ($u) => $u->name, $added)]);

        return $added;
    }

    /** 3+ i husstanden => alle (ikke-støtte) på familiekontingent, ellers enkelt medlem. */
    public static function syncHouseholdTypes(User $payer): void
    {
        if (! $payer->household_id) return;
        $code = self::householdCount($payer) >= 3 ? 'family' : 'single';
        User::where('household_id', $payer->household_id)
            ->whereHas('membershipType', fn ($q) => $q->where('code', '!=', 'passive'))
            ->update(['membership_type_id' => self::type($code)->id]);
    }

    /** Kaldes af Payments::markPaid, når en kontingentregning er betalt. */
    public static function onPaid(Invoice $invoice): void
    {
        if ($invoice->kind !== 'membership') return;

        if (($invoice->payload['action'] ?? null) === 'family_upgrade') {
            $added = self::addToHousehold($invoice->payer, $invoice->payload['members'] ?? []);
            $invoice->update(['payload' => $invoice->payload + ['applied_at' => now()->toIso8601String(), 'user_ids' => array_map(fn ($u) => $u->id, $added)]]);
            $n = self::householdCount($invoice->payer);
            $invoice->payer->notify(new ClubMessage(
                $n >= 3 ? 'I er nu på familiekontingent' : 'Husstanden er udvidet',
                implode(', ', array_map(fn ($u) => $u->name, $added)).' er tilføjet til husstanden og kan tilmeldes aktiviteter.', '/profil/familie'));

            return;
        }

        $m = $invoice->member;
        if ($m && $m->status === 'pending') {
            $m->update(['status' => 'active', 'joined_on' => now()]);
            Audit::log('member.activated_by_payment', $m);
            $m->notify(new ClubMessage('Velkommen i SurfHorsens!', 'Din betaling er modtaget, og du er nu medlem. Du kan tilmelde dig aktiviteter med det samme.', '/aktiviteter'));
            self::notifyBoard('Nyt medlem: '.$m->name, $m->name.' har meldt sig ind og betalt kontingent ('.$m->membershipType?->name.').', '/medlemmer/'.$m->id);
        }
    }

    /**
     * Opkræv kontingent for en sæson: én regning pr. husstand (aktive, ikke-støtte medlemmer) + én pr. støttemedlem.
     * Husstande/medlemmer, der allerede har en regning for sæsonen, springes over.
     */
    public static function generateSeason(int $startYear): array
    {
        $season = Season::fromStartYear($startYear);
        $label = Season::label($season);
        $account = FinanceController::accountId('1000');
        $created = 0; $sum = 0;
        $due = now()->max($season)->addDays(14);

        $payers = User::with('household')->whereIn('status', ['active', 'passive'])->get()->map(fn ($u) => $u->payer())->unique('id');
        foreach ($payers as $payer) {
            $members = User::where('household_id', $payer->household_id)->whereIn('status', ['active', 'passive'])->with('membershipType')->get();
            $regular = $members->filter(fn ($m) => $m->membershipType?->code !== 'passive');
            $passive = $members->filter(fn ($m) => $m->membershipType?->code === 'passive');

            if ($regular->count() && ! Invoice::where('kind', 'membership')->where('period', $label)->where('payer_id', $payer->id)
                    ->where(fn ($q) => $q->whereNull('member_id')->orWhereIn('member_id', $regular->pluck('id')))->exists()) {
                $n = $regular->count();
                $amount = Prices::membership($n, $season); // pris ved sæsonstart (fuld sæson)
                Invoice::create([
                    'payer_id' => $payer->id, 'member_id' => $n === 1 ? $regular->first()->id : null, 'kind' => 'membership', 'period' => $label,
                    'description' => "Kontingent $label – ".self::householdLabel($n).' – '.($n === 1 ? $regular->first()->name : ($payer->household?->name ?? $payer->name)),
                    'amount' => $amount, 'due_on' => $due, 'account_id' => $account,
                ]);
                $payer->notify(new ClubMessage("Kontingent $label", 'Der er oprettet en opkrævning på '.Money::fmt($amount).' med betaling senest '.$due->format('d.m.Y').'.', '/profil/regninger'));
                $created++; $sum += $amount;
            }
            foreach ($passive as $p) {
                if (Invoice::where('kind', 'membership')->where('period', $label)->where('member_id', $p->id)->exists()) continue;
                $amount = Prices::get('membership.passive', $season);
                Invoice::create([
                    'payer_id' => $payer->id, 'member_id' => $p->id, 'kind' => 'membership', 'period' => $label,
                    'description' => "Kontingent $label – Støttemedlem – {$p->name}", 'amount' => $amount, 'due_on' => $due, 'account_id' => $account,
                ]);
                $created++; $sum += $amount;
            }
        }
        Audit::log('membership.generated', null, ['season' => $label, 'count' => $created]);

        return ['created' => $created, 'total' => Money::fmt($sum), 'season' => $label];
    }

    private static function notifyBoard(string $title, string $body, string $link): void
    {
        User::whereHas('roleAssignments', fn ($q) => $q->whereIn('role', ['board', 'treasurer'])->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', now())))
            ->get()->each->notify(new ClubMessage($title, $body, $link));
    }
}
