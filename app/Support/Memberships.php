<?php

namespace App\Support;

use App\Http\Controllers\Api\FinanceController;
use App\Models\Invoice;
use App\Models\MembershipType;
use App\Models\User;
use App\Notifications\ClubMessage;

/**
 * Medlemskab hænger på betaling:
 *  - Indmeldelse: medlemmet oprettes som "afventer betaling" med en kontingentregning. Betalt => aktivt medlem.
 *  - Familie: tilføjer et medlem på individuelt kontingent et barn/en partner, opgraderes til familiekontingent.
 *    De nye personer gemmes på regningen (payload) og oprettes først, når regningen er betalt.
 */
class Memberships
{
    /** Andel af året der er tilbage, inkl. indeværende måned (forholdsmæssigt kontingent). */
    public static function monthsLeft(): int
    {
        return 12 - now()->month + 1;
    }

    public static function prorate(int $amount): int
    {
        return (int) round($amount * self::monthsLeft() / 12);
    }

    public static function typeFor(?string $birthdate, bool $passive = false): MembershipType
    {
        if ($passive) {
            return MembershipType::where('code', 'passive')->first();
        }
        $youth = MembershipType::where('code', 'youth')->first();
        $age = $birthdate ? \Illuminate\Support\Carbon::parse($birthdate)->age : 99;

        return $youth && $age <= $youth->max_age ? $youth : MembershipType::where('code', 'adult')->first();
    }

    public static function signupInvoice(User $u): Invoice
    {
        $type = $u->membershipType;
        $year = now()->year;
        $n = self::monthsLeft();

        return Invoice::create([
            'payer_id' => $u->id, 'member_id' => $u->id, 'kind' => 'membership', 'period' => (string) $year,
            'description' => "Kontingent $year – {$type->name} – {$u->name}".($n < 12 ? " ($n/12)" : ''),
            'amount' => self::prorate($type->annual_fee), 'due_on' => now(), 'account_id' => FinanceController::accountId('1000'),
        ]);
    }

    /** Pris for at gå fra nuværende (betalte) kontingent til familie resten af året. */
    public static function upgradePrice(User $payer): int
    {
        $family = MembershipType::where('code', 'family')->first();
        $current = $payer->membershipType?->annual_fee ?? 0;

        return max(0, self::prorate($family->annual_fee - $current));
    }

    public static function openUpgrade(User $payer): ?Invoice
    {
        return Invoice::where('payer_id', $payer->id)->where('status', 'open')->where('kind', 'membership')
            ->whereNotNull('payload')->latest('id')->get()->first(fn ($i) => ($i->payload['action'] ?? null) === 'family_upgrade');
    }

    /** Opret/udvid opgraderingsregning med en ny person. Intet oprettes før betaling. */
    public static function requestFamilyUpgrade(User $payer, array $person): Invoice
    {
        $inv = self::openUpgrade($payer);
        $year = now()->year;
        $n = self::monthsLeft();
        $people = array_merge($inv?->payload['members'] ?? [], [$person]);
        $names = implode(', ', array_column($people, 'name'));
        $data = [
            'payload' => ['action' => 'family_upgrade', 'members' => $people],
            'description' => "Familiekontingent $year (opgradering".($n < 12 ? ", $n/12" : '').") – tilføjer $names",
        ];
        if ($inv) {
            $inv->update($data);

            return $inv;
        }

        return Invoice::create($data + [
            'payer_id' => $payer->id, 'member_id' => null, 'kind' => 'membership', 'period' => (string) $year,
            'amount' => self::upgradePrice($payer), 'due_on' => now(), 'account_id' => FinanceController::accountId('1000'),
        ]);
    }

    /** Kaldes af Payments::markPaid, når en kontingentregning er betalt. */
    public static function onPaid(Invoice $invoice): void
    {
        if ($invoice->kind !== 'membership') {
            return;
        }

        if (($invoice->payload['action'] ?? null) === 'family_upgrade') {
            self::applyFamilyUpgrade($invoice);

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

    private static function applyFamilyUpgrade(Invoice $invoice): void
    {
        $payer = $invoice->payer;
        $family = MembershipType::where('code', 'family')->first();
        $added = [];
        foreach ($invoice->payload['members'] ?? [] as $p) {
            $added[] = User::create([
                'name' => $p['name'], 'birthdate' => $p['birthdate'], 'email' => $p['email'] ?? null,
                'emergency_name' => $p['emergency_name'] ?? $payer->name, 'emergency_phone' => $p['emergency_phone'] ?? $payer->phone,
                'household_id' => $payer->household_id, 'membership_type_id' => $family->id, 'status' => 'active',
                'joined_on' => now(), 'consent_at' => $p['consent_at'] ?? now(), 'notify_email' => false,
                'address' => $payer->address, 'postal_code' => $payer->postal_code, 'city' => $payer->city,
            ]);
        }
        // Hele husstanden går over på familiekontingent
        User::where('household_id', $payer->household_id)->update(['membership_type_id' => $family->id]);
        $invoice->update(['payload' => $invoice->payload + ['applied_at' => now()->toIso8601String(), 'user_ids' => array_map(fn ($u) => $u->id, $added)]]);
        Audit::log('household.upgraded_to_family', $payer, ['added' => array_map(fn ($u) => $u->name, $added)]);
        $payer->notify(new ClubMessage('I er nu på familiekontingent', implode(', ', array_map(fn ($u) => $u->name, $added)).' er tilføjet til husstanden og kan tilmeldes aktiviteter.', '/profil/familie'));
    }

    private static function notifyBoard(string $title, string $body, string $link): void
    {
        User::whereHas('roleAssignments', fn ($q) => $q->whereIn('role', ['board', 'treasurer'])->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', now())))
            ->get()->each->notify(new ClubMessage($title, $body, $link));
    }
}
