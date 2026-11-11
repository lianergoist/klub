<?php

namespace App\Support;

use App\Http\Controllers\Api\FinanceController;
use App\Models\Account;
use App\Models\Household;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\PersonCategory;
use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\Purchase;
use App\Models\User;
use App\Notifications\ClubMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Pakker og køb.
 *
 * En pakke (medlemskab eller tilkøb) dækker et antal personer pr. kategori (min–max) i en periode
 * (antal dage fra køb, faste datoer som 1/5–30/4, eller engang) til en pris med historik.
 * Et køb knytter pakken til bestemte personer og en regning. Gyldigt medlemskab = personen er med i et
 * betalt medlemskab, der dækker dags dato. Kommer der en person til midt i perioden, købes en ekstra pakke.
 */
class Packages
{
    /* ---------- Pris ---------- */

    /**
     * Prisen for at købe pakken til bestemte personer.
     * Regel: et medlemskab til flere personer koster aldrig mere end summen af det billigste enkeltmedlemskab
     * (til én person, til salg samme dag, samme adgang til aktiviteter), som hver person kunne købe.
     * Fx familie 3 personer 2.500 kr. i november, hvor halv sæson er 400 kr.: højst 3 × 400 = 1.200 kr.
     * @return array{price:int, full:int, capped:bool}
     */
    public static function priceFor(Package $package, array $people, $date = null): array
    {
        $date = Carbon::parse($date ?? now());
        $full = $package->price($date);
        $out = ['price' => $full, 'full' => $full, 'capped' => false];
        if (! $package->isMembership() || $package->total_max === 1 || count($people) < 2) return $out;

        $singles = self::singleMemberships($package->gives_access, $date);
        $cap = 0;
        foreach ($people as $p) {
            $best = $singles->filter(fn ($s) => $s->compositionError([$p], $date) === null)->map(fn ($s) => $s->price($date))->min();
            if ($best === null) return $out; // en person kan ikke købe enkeltmedlemskab – ingen sammenligning
            $cap += $best;
        }

        return $cap < $full ? ['price' => $cap, 'full' => $full, 'capped' => true] : $out;
    }

    /** Enkeltmedlemskaber (til én person), der kan købes på datoen, med samme adgang til aktiviteter. */
    public static function singleMemberships(bool $access, $date)
    {
        return Package::with('slots.category')->where('kind', 'membership')->whereNull('archived_at')->where('self_service', true)
            ->where('total_max', 1)->where('gives_access', $access)->get()->filter(fn ($s) => $s->buyableOn($date))->values();
    }

    /* ---------- Køb ---------- */

    /**
     * Opret et køb + regning. Nye personer ($newPeople) oprettes først, når regningen er betalt.
     * @param  int[]  $memberIds  eksisterende personer i betalerens husstand
     * @param  array  $newPeople  [['name','birthdate','email'?, 'emergency_name'?, 'emergency_phone'?], …]
     */
    public static function buy(User $payer, Package $package, array $memberIds, array $newPeople = [], ?int $resourceId = null, bool $staff = false): Purchase
    {
        abort_if($package->archived_at, 422, $package->name.' kan ikke længere købes.');
        abort_unless($package->self_service || $staff, 403, $package->name.' tildeles af bestyrelsen.');
        abort_if(! $package->isMembership() && $newPeople, 422, 'Tilkøb kan kun købes til personer, der allerede er medlemmer.');

        $window = $package->windowFor(now());
        abort_unless($window, 422, $package->name.' kan kun købes i perioden '.$package->periodLabel().'.');
        abort_unless($package->onSale() || $staff, 422, $package->name.' er kun '.$package->saleLabel().'.');
        [$from, $to] = $window;

        $household = $payer->managedMembers()->keyBy('id');
        $members = collect($memberIds)->unique()->map(fn ($id) => $household[$id] ?? abort(403, 'Du kan kun købe til personer i din husstand.'));

        $people = $members->map(fn ($m) => ['name' => $m->name, 'birthdate' => $m->birthdate?->toDateString()])->all();
        foreach ($newPeople as $p) $people[] = $p;
        abort_if(! $people, 422, 'Vælg mindst én person.');
        if ($err = $package->compositionError($people, $from)) abort(422, $err);

        foreach ($members as $m) {
            // Klippekort er kun for ikke-medlemmer (gæster)
            abort_if($package->guest_only && $m->status !== 'guest', 422, $package->name.' er kun for ikke-medlemmer.');
            if ($package->isMembership() && ($other = self::overlapping($m, $from, $to))) {
                abort(422, $m->name.' har allerede '.$other->package->name.($other->valid_to ? ' til '.$other->valid_to->format('d.m.Y') : '').'.');
            }
            if (! $package->isMembership() && $package->requires_membership && ! $m->hasValidMembership(true)) {
                abort(422, $package->name.' kræver gyldigt medlemskab, og det har '.$m->name.' ikke.');
            }
            if (! $package->isMembership() && $package->period_type !== 'once' && ! $package->isPunchcard()
                && Purchase::where('package_id', $package->id)->currentOrFuture()->whereHas('users', fn ($q) => $q->whereKey($m->id))->exists()) {
                abort(422, $m->name.' har allerede '.mb_strtolower($package->name).'.');
            }
        }

        return DB::transaction(function () use ($payer, $package, $members, $newPeople, $resourceId, $from, $to) {
            $resource = null;
            if ($package->product_id && $package->product?->needs_resource) {
                abort_unless($resourceId, 422, 'Vælg en plads.');
                $resource = $package->product->resources()->lockForUpdate()->findOrFail($resourceId);
                abort_if(self::resourceTaken($resource->id), 422, 'Pladsen er desværre lige blevet optaget.');
            }
            $names = $members->pluck('name')->merge(array_column($newPeople, 'name'));
            $people = $members->map(fn ($m) => ['name' => $m->name, 'birthdate' => $m->birthdate?->toDateString()])->merge($newPeople)->all();
            $pr = self::priceFor($package, $people, $from);
            $amount = $pr['price'];
            $invoice = Invoice::create([
                'payer_id' => $payer->id, 'member_id' => $names->count() === 1 && $members->count() === 1 ? $members->first()->id : null,
                'kind' => $package->isMembership() ? 'membership' : 'product',
                'description' => $package->name.($resource ? ' ('.$resource->label.')' : '').self::periodText($from, $to).' – '.self::who($names)
                    .($pr['capped'] ? ' (højst '.count($people).' × enkeltkontingent)' : ''),
                'amount' => $amount, 'due_on' => now()->addDays($package->isMembership() ? 0 : 14),
                'period' => Season::label($from), 'product_id' => $package->product_id,
                'account_id' => $package->account_id,
                'payload' => ['action' => 'purchase', 'package_id' => $package->id, 'new_members' => $newPeople],
            ]);
            $purchase = Purchase::create([
                'package_id' => $package->id, 'payer_id' => $payer->id, 'invoice_id' => $invoice->id, 'resource_id' => $resource?->id,
                'valid_from' => $from, 'valid_to' => $to,
                'uses_left' => $package->isPunchcard() ? $package->uses : null,
                // Medlemskab til næste periode: adgang til aktiviteter fra købsdatoen, hvis pakken giver det (klubbens praksis)
                'access_from' => $package->isMembership() && $package->early_access && $from->isAfter(now()->endOfDay()) ? now()->toDateString() : null,
            ]);
            $purchase->users()->attach($members->pluck('id'));
            $invoice->update(['payload' => $invoice->payload + ['purchase_id' => $purchase->id]]);
            Audit::log('package.purchased', $purchase, ['package' => $package->name, 'amount' => $amount, 'persons' => $names->all()]);

            return $purchase;
        });
    }

    /** Betalte prøvetræninger/SUP-kurser (som gæst inden for 12 måneder) trækkes fra det første kontingent. */
    public static function applyGuestCredit(Invoice $invoice, User $u): Invoice
    {
        $credit = Memberships::guestCredit($u);
        $used = min($invoice->amount, $credit['amount']);
        if (! $used) return $invoice;
        $invoice->update([
            'amount' => $invoice->amount - $used,
            'description' => $invoice->description.' (fratrukket '.Money::fmt($used).' for '.$credit['invoices']->map(fn ($i) => ['trial' => 'prøvetræning', 'sup_course' => 'SUP-kursus'][$i->payload['offer'] ?? ''] ?? 'gæstekøb')->unique()->join(' og ').')',
            'payload' => $invoice->payload + ['guest_credit' => $used, 'price' => $invoice->amount, 'credited_invoices' => $credit['invoices']->pluck('id')->all()],
        ]);
        foreach ($credit['invoices'] as $g) $g->update(['payload' => ($g->payload ?? []) + ['credited_to' => $invoice->id]]);
        Audit::log('membership.guest_credit', $u, ['credit' => $used, 'invoice' => $invoice->id]);

        return $invoice;
    }

    /** Gratis køb (pris 0) er betalt med det samme. Kaldes efter buy() og evt. modregning. */
    public static function settleIfFree(Invoice $invoice): void
    {
        if ($invoice->status === 'open' && $invoice->amount === 0) {
            $invoice->update(['status' => 'paid', 'paid_at' => now()]);
            Memberships::onPaid($invoice->fresh());
        }
    }

    /** Kaldes, når en regning er betalt. Returnerer true, hvis regningen hørte til et køb. */
    public static function onPaid(Invoice $invoice): bool
    {
        $purchase = Purchase::with('package', 'users')->where('invoice_id', $invoice->id)->first();
        if (! $purchase) return false;
        $package = $purchase->package;

        // Nye personer oprettes først nu
        $added = [];
        foreach ($invoice->payload['new_members'] ?? [] as $p) {
            $payer = $purchase->payer;
            if (! $payer->household_id) {
                $payer->update(['household_id' => Household::create(['name' => 'Husstand '.$payer->name, 'payer_id' => $payer->id])->id]);
            }
            $added[] = $u = User::create([
                'name' => $p['name'], 'birthdate' => $p['birthdate'], 'email' => $p['email'] ?? null,
                'emergency_name' => $p['emergency_name'] ?? $payer->name, 'emergency_phone' => $p['emergency_phone'] ?? $payer->phone,
                'household_id' => $payer->household_id, 'status' => $package->gives_access ? 'active' : 'passive',
                'joined_on' => now(), 'consent_at' => $p['consent_at'] ?? now(), 'notify_email' => false,
                'address' => $payer->address, 'postal_code' => $payer->postal_code, 'city' => $payer->city,
            ]);
            $purchase->users()->attach($u->id);
        }
        if ($added) {
            $invoice->update(['payload' => $invoice->payload + ['user_ids' => array_map(fn ($u) => $u->id, $added)]]);
            Audit::log('household.members_added', $purchase->payer, ['added' => array_map(fn ($u) => $u->name, $added)]);
        }

        // Personer, der venter på betaling (ny indmeldelse), bliver aktive medlemmer
        if ($package->isMembership()) {
            foreach ($purchase->users()->get() as $m) {
                if ($m->status === 'pending') {
                    $m->update(['status' => $package->gives_access ? 'active' : 'passive', 'joined_on' => now()]);
                    Audit::log('member.activated_by_payment', $m);
                    $m->notify(new ClubMessage('Velkommen i SurfHorsens!', 'Din betaling er modtaget, og du er nu medlem ('.$package->name.').'.($package->gives_access ? ' Du kan tilmelde dig aktiviteter med det samme.' : ''), '/aktiviteter'));
                    self::notifyBoard('Nyt medlem: '.$m->name, $m->name.' har meldt sig ind og betalt ('.$package->name.').', '/medlemmer/'.$m->id);
                }
            }
            if ($added) {
                $purchase->payer->notify(new ClubMessage('Husstanden er udvidet',
                    implode(', ', array_map(fn ($u) => $u->name, $added)).' er tilføjet med '.$package->name.'.', '/profil/familie'));
            }
        }

        return true;
    }

    /** Annullér et ubetalt køb (regningen annulleres; ingen personer er oprettet). */
    public static function cancel(Purchase $purchase, User $by): void
    {
        abort_unless($purchase->invoice?->status === 'open', 422, 'Kun ubetalte køb kan fortrydes.');
        DB::transaction(function () use ($purchase) {
            $purchase->invoice->update(['status' => 'cancelled']);
            $purchase->update(['cancelled_at' => now()]);
        });
        Audit::log('package.purchase_cancelled', $purchase, ['by' => $by->name]);
    }

    /** Et andet medlemskab for personen, der overlapper perioden (betalt eller afventer betaling). */
    public static function overlapping(User $u, Carbon $from, ?Carbon $to): ?Purchase
    {
        return Purchase::with('package')->whereHas('package', fn ($q) => $q->where('kind', 'membership'))
            ->whereHas('users', fn ($q) => $q->whereKey($u->id))->currentOrFuture()
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $from->toDateString()))
            ->when($to, fn ($q) => $q->where('valid_from', '<=', $to->toDateString()))
            ->first();
    }

    public static function resourceTaken(int $resourceId): bool
    {
        return Purchase::where('resource_id', $resourceId)->currentOrFuture()->exists();
    }

    /* ---------- Fornyelse (ny sæson) ---------- */

    /**
     * Forny medlemskaber med faste datoer, der udløb i perioden før $year (fx 30/4 $year): samme personer, samme pakke
     * (eller den pakke, den "fornyes som", fx halv sæson → hel sæson). Passer personerne ikke længere (fx et barn er
     * blevet voksen), eller er pakken arkiveret, springes husstanden over og vises i listen, så kassereren kan tage sig af den.
     */
    public static function renew(int $year): array
    {
        $created = 0; $sum = 0; $skipped = [];
        $old = Purchase::with('package.renewsTo', 'users', 'payer')
            ->whereHas('package', fn ($q) => $q->where('kind', 'membership')->where('period_type', 'dates'))
            ->whereNull('cancelled_at')->whereHas('invoice', fn ($q) => $q->where('status', 'paid'))
            ->where('valid_to', '>=', ($year - 1).'-05-01')->where('valid_to', '<', $year.'-05-01')
            ->whereNotExists(fn ($q) => $q->from('purchases as n')->whereColumn('n.renewed_from_id', 'purchases.id'))
            ->get();

        foreach ($old as $p) {
            $target = $p->package->renewsTo ?? $p->package;
            $people = $p->users->whereNotIn('status', ['resigned', 'guest'])->values();
            $who = $p->payer->name;
            if (! $people->count()) continue;
            if ($target->archived_at) { $skipped[] = "$who: {$target->name} er arkiveret"; continue; }
            [$from, $to] = $target->dateWindowStartingIn($year);
            if ($err = $target->compositionError($people->map(fn ($m) => ['name' => $m->name, 'birthdate' => $m->birthdate?->toDateString()])->all(), $from)) {
                $skipped[] = "$who: $err"; continue;
            }
            if ($people->contains(fn ($m) => self::overlapping($m, $from, $to))) continue; // har allerede købt
            DB::transaction(function () use ($p, $target, $people, $from, $to, &$created, &$sum) {
                $pr = self::priceFor($target, $people->map(fn ($m) => ['name' => $m->name, 'birthdate' => $m->birthdate?->toDateString()])->all(), $from);
                $amount = $pr['price'];
                $inv = Invoice::create([
                    'payer_id' => $p->payer_id, 'member_id' => $people->count() === 1 ? $people->first()->id : null, 'kind' => 'membership',
                    'description' => $target->name.self::periodText($from, $to).' – '.self::who($people->pluck('name')).($pr['capped'] ? ' (højst '.$people->count().' × enkeltkontingent)' : ''),
                    'amount' => $amount, 'due_on' => now()->max($from)->addDays(14), 'period' => Season::label($from),
                    'account_id' => $target->account_id, 'payload' => ['action' => 'purchase', 'package_id' => $target->id, 'renewal' => true],
                ]);
                $n = Purchase::create(['package_id' => $target->id, 'payer_id' => $p->payer_id, 'invoice_id' => $inv->id,
                    'valid_from' => $from, 'valid_to' => $to, 'renewed_from_id' => $p->id]);
                $n->users()->attach($people->pluck('id'));
                $inv->update(['payload' => $inv->payload + ['purchase_id' => $n->id]]);
                $p->payer->notify(new ClubMessage('Kontingent '.Season::label($from), 'Der er oprettet en opkrævning på '.Money::fmt($amount).' ('.$target->name.') med betaling senest '.$inv->due_on->format('d.m.Y').'.', '/profil/regninger'));
                $created++; $sum += $amount;
            });
        }
        Audit::log('membership.generated', null, ['year' => $year, 'count' => $created, 'skipped' => count($skipped)]);

        return ['created' => $created, 'total' => Money::fmt($sum), 'season' => Season::label(Carbon::parse($year.'-05-01')), 'skipped' => $skipped];
    }

    /* ---------- Visning ---------- */

    public static function periodText(Carbon $from, ?Carbon $to): string
    {
        if (! $to) return '';

        return ' '.$from->locale('da')->isoFormat('D. MMM YYYY').' – '.$to->locale('da')->isoFormat('D. MMM YYYY');
    }

    private static function who($names): string
    {
        $names = collect($names)->values();

        return $names->count() <= 2 ? $names->join(' og ') : $names->count().' personer ('.$names->map(fn ($n) => explode(' ', $n)[0])->join(', ', ' og ').')';
    }

    private static function notifyBoard(string $title, string $body, string $link): void
    {
        User::whereHas('roleAssignments', fn ($q) => $q->whereIn('role', ['board', 'treasurer'])->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', now())))
            ->get()->each->notify(new ClubMessage($title, $body, $link));
    }

    /* ---------- Overførsel fra de hidtidige medlemstyper og produkter ---------- */

    /** Kan køres flere gange: opretter kun det, der mangler. Kræver kontoplanen (findes ikke ved migrate:fresh før seederen). */
    public static function backfill(): void
    {
        if (! Account::exists()) return;
        DB::transaction(function () {
            if (! PersonCategory::exists()) {
                PersonCategory::create(['name' => 'Barn', 'plural' => 'Børn', 'min_age' => 0, 'max_age' => 17]);
                PersonCategory::create(['name' => 'Voksen', 'plural' => 'Voksne', 'min_age' => 18, 'max_age' => null]);
            }
            self::legacyPackages();
            self::legacyPurchases();
        });
        Prices::flush();
    }

    private static function legacyPackages(): void
    {
        $rows = fn (string $code) => DB::table('prices')->where('code', $code)->orderBy('valid_from')->get(['amount', 'valid_from', 'created_by']);
        // Prisrækker for en kombination af priskoder, fx 2 × enkelt eller familie + 2 × ekstra
        $combo = function (array $parts) use ($rows) {
            $dates = collect($parts)->flatMap(fn ($n, $code) => $rows($code)->pluck('valid_from'))->unique()->sort()->values();

            return $dates->map(function ($d) use ($parts) {
                $sum = 0;
                foreach ($parts as $code => $n) {
                    $v = DB::table('prices')->where('code', $code)->where('valid_from', '<=', $d)->orderByDesc('valid_from')->value('amount');
                    if ($v === null) return null;
                    $sum += $n * $v;
                }

                return (object) ['amount' => $sum, 'valid_from' => $d, 'created_by' => null];
            })->filter()->values();
        };
        $acc = fn ($n) => FinanceController::accountId($n);
        $season = ['period_type' => 'dates', 'date_from' => '05-01', 'date_to' => '04-30'];
        $half = ['period_type' => 'dates', 'date_from' => '08-01', 'date_to' => '04-30'];
        $product = fn ($code) => Product::where('price_code', $code)->first();

        $specs = [
            ['m.single', 'membership', 'Enkelt medlem – hel sæson', 'Ét medlem (barn eller voksen) for hele sæsonen.', $season, 1, 1, ['sale' => ['11-01', '07-31'], 'next' => '11-01'], fn () => $rows('membership.single')],
            ['m.single_half', 'membership', 'Enkelt medlem – halv sæson', 'Ét medlem fra 1. august og sæsonen ud.', $half, 1, 1, ['renews' => 'm.single', 'sale' => ['08-01', '10-31']], fn () => $rows('membership.single_half')],
            ['m.two', 'membership', 'Husstand med 2 personer – hel sæson', 'To medlemmer i samme husstand.', $season, 2, 2, ['sale' => ['11-01', '07-31'], 'next' => '11-01'], fn () => $combo(['membership.single' => 2])],
            ['m.two_half', 'membership', 'Husstand med 2 personer – halv sæson', 'To medlemmer i samme husstand fra 1. august.', $half, 2, 2, ['renews' => 'm.two', 'sale' => ['08-01', '10-31']], fn () => $combo(['membership.single_half' => 2])],
            ['m.family3', 'membership', 'Familie med 3 personer', 'Tre medlemmer i samme husstand.', $season, 3, 3, ['next' => '11-01'], fn () => $rows('membership.family3')],
            ['m.family4', 'membership', 'Familie med 4 eller flere', 'Fire eller flere medlemmer i samme husstand.', $season, 4, 10, ['next' => '11-01'], fn () => $rows('membership.family4')],
            ['m.passive', 'membership', 'Støttemedlem', 'Støtter klubben uden at deltage i aktiviteter.', $season, 1, 1, ['access' => false, 'next' => '11-01'], fn () => $rows('membership.passive')],
        ];
        if ($p = $product('storage.rack')) $specs[] = ['p.rack', 'product', $p->name, $p->description, $season, 1, 1, ['product' => $p, 'acc' => '1300', 'member' => true], fn () => $rows('storage.rack')];
        if ($p = $product('storage.locker')) $specs[] = ['p.locker', 'product', $p->name, $p->description, $season, 1, 1, ['product' => $p, 'acc' => '1300', 'member' => true], fn () => $rows('storage.locker')];
        if ($p = $product('rental.single')) {
            $tiers = [1 => ['rental.single' => 1], 2 => ['rental.single' => 2], 3 => ['rental.family' => 1], 4 => ['rental.family' => 1, 'rental.extra' => 1], 5 => ['rental.family' => 1, 'rental.extra' => 2]];
            foreach ($tiers as $n => $parts) {
                $specs[] = ["p.rental$n", 'product', 'Udstyrsleje i sæsonen, '.($n === 1 ? '1 person' : "$n personer"), 'Fri brug af klubbens boards, sejl og våddragter hele sæsonen.', $season, $n, $n, ['product' => $p, 'acc' => '1200'], fn () => $combo($parts)];
            }
        }
        if ($p = $product('rental.day')) $specs[] = ['p.day', 'product', $p->name, $p->description, ['period_type' => 'days', 'period_days' => 1], 1, 1, ['product' => $p, 'acc' => '1200'], fn () => $rows('rental.day')];
        if ($p = $product('key_deposit')) $specs[] = ['p.key', 'product', $p->name, $p->description, ['period_type' => 'once'], 1, 1, ['product' => $p, 'acc' => '1200'], fn () => $rows('key_deposit')];

        $cats = PersonCategory::orderBy('min_age')->get();
        foreach ($specs as $i => [$key, $kind, $name, $desc, $period, $min, $max, $opt, $prices]) {
            if (Package::where('legacy_key', $key)->exists()) continue;
            $pkg = Package::create($period + [
                'legacy_key' => $key, 'kind' => $kind, 'name' => $name, 'description' => $desc,
                'total_min' => $min, 'total_max' => $max, 'gives_access' => $opt['access'] ?? true,
                'requires_membership' => $opt['member'] ?? false, 'product_id' => ($opt['product'] ?? null)?->id,
                'account_id' => $acc($opt['acc'] ?? '1000'), 'position' => $i,
                'archived_at' => isset($opt['product']) && ! $opt['product']->active ? now() : null,
                'sale_from' => $opt['sale'][0] ?? null, 'sale_to' => $opt['sale'][1] ?? null, 'next_from' => $opt['next'] ?? null,
            ]);
            foreach ($cats as $c) $pkg->slots()->create(['person_category_id' => $c->id, 'min' => 0, 'max' => $max]);
            foreach ($prices() as $r) {
                DB::table('prices')->insert(['code' => $pkg->priceCode(), 'amount' => $r->amount, 'valid_from' => $r->valid_from,
                    'created_by' => $r->created_by, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        foreach ($specs as [$key, , , , , , , $opt]) {
            if (isset($opt['renews'])) Package::where('legacy_key', $key)->whereNull('renews_to_id')->update(['renews_to_id' => Package::where('legacy_key', $opt['renews'])->value('id')]);
        }
    }

    private static function legacyPurchases(): void
    {
        $pkg = fn ($key) => Package::where('legacy_key', $key)->first();
        $linked = Purchase::whereNotNull('invoice_id')->pluck('invoice_id')->flip();

        // Ubetalte familieopgraderinger (gammel ordning) kan ikke overføres: personerne findes endnu ikke
        Invoice::where('kind', 'membership')->where('status', 'open')->get()
            ->filter(fn ($i) => ($i->payload['action'] ?? null) === 'family_upgrade')
            ->each(function ($i) { $i->update(['status' => 'cancelled']); Audit::log('membership.legacy_upgrade_cancelled', $i); });

        // Kontingent: ét køb pr. husstand og sæson for de almindelige medlemmer, ét pr. støttemedlem
        $invoices = Invoice::with('member.membershipType')->where('kind', 'membership')->whereIn('status', ['open', 'paid'])->orderBy('id')->get()
            ->reject(fn ($i) => isset($linked[$i->id]));
        foreach ($invoices->groupBy(fn ($i) => $i->payer_id.'|'.$i->period) as $group) {
            $first = $group->first();
            $payer = User::find($first->payer_id);
            if (! $payer || ! $first->period) continue;
            [$from, $to] = [Season::fromStartYear((int) substr($first->period, 0, 4)), Season::fromStartYear((int) substr($first->period, 0, 4))->addYear()->subDay()];
            $isPassive = fn ($i) => $i->member && $i->member->membershipType?->code === 'passive';

            foreach ($group->filter($isPassive) as $i) {
                self::legacyPurchase($pkg('m.passive'), $payer, $i, [$i->member_id], $from, $to);
            }
            $regular = $group->reject($isPassive);
            if ($regular->isEmpty()) continue;
            $ids = $payer->household_id
                ? User::where('household_id', $payer->household_id)->whereIn('status', ['active', 'pending'])
                    ->where(fn ($q) => $q->whereNull('membership_type_id')->orWhereHas('membershipType', fn ($q) => $q->where('code', '!=', 'passive')))->pluck('id')->all()
                : [$payer->id];
            if ($regular->count() === 1 && $regular->first()->member_id) $ids = [$regular->first()->member_id];
            $n = count($ids);
            $half = $regular->contains(fn ($i) => str_contains($i->description, 'halv sæson')) || $regular->contains(fn ($i) => str_contains($i->description, '2 × enkelt') && Season::isHalf($i->created_at));
            $key = match (true) { $n <= 1 => $half ? 'm.single_half' : 'm.single', $n === 2 => $half ? 'm.two_half' : 'm.two', $n === 3 => 'm.family3', default => 'm.family4' };
            $inv = $regular->firstWhere('status', 'open') ?? $regular->sortByDesc('id')->first();
            self::legacyPurchase($pkg($key), $payer, $inv, $ids, $half ? Carbon::parse($from->year.'-08-01') : $from, $to);
        }

        // Tilkøb: én pr. regning (udstyrsleje for flere personer = én pakke for det antal)
        $orders = ProductOrder::with('product', 'invoice', 'user')->get()->reject(fn ($o) => $o->invoice_id && isset($linked[$o->invoice_id]));
        foreach ($orders->groupBy(fn ($o) => $o->invoice_id ?: 'o'.$o->id) as $group) {
            $o = $group->first();
            $key = match ($o->product?->price_code) {
                'storage.rack' => 'p.rack', 'storage.locker' => 'p.locker', 'rental.day' => 'p.day', 'key_deposit' => 'p.key',
                'rental.single' => 'p.rental'.min(5, $group->count()), default => null,
            };
            if (! $key || ! ($package = $pkg($key))) continue;
            $payer = $o->invoice?->payer ?? $o->user->payer();
            $p = Purchase::create(['package_id' => $package->id, 'payer_id' => $payer->id, 'invoice_id' => $o->invoice_id, 'resource_id' => $o->resource_id,
                'valid_from' => $o->valid_from, 'valid_to' => $o->valid_to, 'created_at' => $o->created_at, 'updated_at' => $o->updated_at]);
            $p->users()->attach($group->pluck('user_id')->unique());
        }
        // Gældende regninger uden ordre (fx dagsleje) får ikke et køb – de er afregnet og har ingen periode
    }

    private static function legacyPurchase(?Package $package, User $payer, Invoice $inv, array $ids, Carbon $from, Carbon $to): void
    {
        if (! $package || ! $ids) return;
        $p = Purchase::create(['package_id' => $package->id, 'payer_id' => $payer->id, 'invoice_id' => $inv->id,
            'valid_from' => $from->toDateString(), 'valid_to' => $to->toDateString(), 'created_at' => $inv->created_at, 'updated_at' => $inv->updated_at]);
        $p->users()->attach(array_unique($ids));
    }
}
