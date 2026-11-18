<?php

namespace App\Support;

use App\Models\Invoice;
use App\Models\Package;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Skift af medlemskab, når husstanden får flere med (fx Familie med 3 personer → Familie med 4 eller flere).
 *
 * Udgangspunktet er husstandens eksisterende medlemskab (betalt af betaleren). Det nye medlemskab dækker de
 * nuværende personer + de nye og træder i kraft
 *  - med det samme: resten af den nuværende periode; man betaler differencen mellem det nye medlemskabs pris og det,
 *    der allerede er betalt for det gamle. Er næste sæsons kontingent allerede oprettet, rettes det også, når der er betalt.
 *  - fra næste sæson: det nuværende medlemskab er uændret; næste sæsons kontingent bliver det nye medlemskab
 *    (et allerede oprettet, ubetalt kontingent annulleres, et betalt modregnes).
 * Det gamle køb afsluttes først, når det nye er betalt. (Fuld difference også sent i sæsonen – bevidst, foreløbig.)
 */
class MembershipChange
{
    /** Husstandens medlemskab i dag og evt. næste sæsons, begge betalt af betaleren. @return array{0:?Purchase,1:?Purchase} */
    public static function bases(User $payer): array
    {
        $today = now()->toDateString();
        $q = fn () => Purchase::with('package.slots.category', 'users', 'invoice')->where('payer_id', $payer->id)->whereNull('cancelled_at')
            ->whereHas('package', fn ($q) => $q->where('kind', 'membership')->where('period_type', 'dates'))->whereNotNull('valid_to');

        $cur = $q()->where('valid_from', '<=', $today)->where('valid_to', '>=', $today)->get()
            ->filter(fn ($p) => $p->isPaid())
            ->sortByDesc(fn ($p) => ($p->users->contains('id', $payer->id) ? 100 : 0) + $p->users->count())->first();
        $next = $q()->where('valid_from', '>', $today)->get()
            ->filter(fn ($p) => in_array($p->invoice?->status ?? 'paid', ['open', 'paid']))
            ->sortByDesc(fn ($p) => $cur ? $p->users->pluck('id')->intersect($cur->users->pluck('id'))->count() : $p->users->count())->first();
        if ($cur && $next && ! $next->users->pluck('id')->intersect($cur->users->pluck('id'))->count()) $next = null; // et andet medlemskab i husstanden

        return [$cur, $next];
    }

    /** Personerne på et køb, der stadig er med (ikke udmeldt). $forNext: kun dem, der fortsætter ved fornyelse. */
    public static function people(Purchase $p, bool $forNext = false): Collection
    {
        return $p->users->whereNotIn('status', ['resigned', 'guest'])
            ->filter(fn ($u) => ! $forNext || ($u->pivot->renew ?? true))->values();
    }

    /** Det, der er betalt for købet (fuld pris før modregning). Ubetalt = 0. */
    public static function paidValue(?Purchase $p): int
    {
        if (! $p || ! $p->isPaid() || ! $p->invoice) return 0;
        $pl = $p->invoice->payload ?? [];

        return (int) ($pl['value'] ?? $pl['price'] ?? $p->invoice->amount);
    }

    /** Medlemskaber, husstanden kan skifte til. $staff: også dem, medlemmerne ikke selv kan vælge. */
    private static function targets(bool $staff = false): Collection
    {
        return Package::with('slots.category')->where('kind', 'membership')->where('period_type', 'dates')
            ->when(! $staff, fn ($q) => $q->where('self_service', true))->whereNull('archived_at')
            ->orderBy('position')->orderBy('id')->get()->reject(fn ($p) => $p->isOffer())->values();
    }

    /** Næste sæsons periode for pakken ud fra husstandens medlemskaber. */
    private static function nextWindow(Package $target, ?Purchase $cur, ?Purchase $next): array
    {
        $year = $next ? $next->valid_from->year : $cur->valid_to->copy()->addDay()->year;

        return $target->dateWindowStartingIn($year);
    }

    /**
     * Hvad kan husstandens medlemskab skiftes til, når $members (uden medlemskab) og $newPeople kommer med?
     * @param  Collection<User>  $members
     */
    public static function options(User $payer, Collection $members, array $newPeople, bool $staff = false): ?array
    {
        [$cur, $next] = self::bases($payer);
        if (! $cur && ! $next) return null;
        $added = $members->map(fn ($m) => ['name' => $m->name, 'birthdate' => $m->birthdate?->toDateString()])->merge($newPeople)->values()->all();
        $out = [
            'base' => self::baseRow($cur ?? $next),
            'next_base' => $cur && $next ? self::baseRow($next) : null,
            'now' => [], 'next' => [], 'next_from' => null,
        ];

        foreach (self::targets($staff) as $t) {
            if ($cur) {
                $people = array_merge(Packages::peopleRows(self::people($cur)), $added);
                $from = now()->startOfDay();
                if (! $t->compositionError($people, $from)) {
                    $price = Packages::priceFor($t, $people, $from);
                    $credit = self::paidValue($cur);
                    $out['now'][] = self::row($t, $price, $credit, $from, $cur->valid_to, count($people)) + ['also_next' => (bool) $next];
                }
            }
            $base = $next ?? $cur;
            [$from, $to] = self::nextWindow($t, $cur, $next);
            $people = array_merge(Packages::peopleRows(self::people($base, ! $next)), $added);
            if (! $t->compositionError($people, $from)) {
                $out['next'][] = self::row($t, Packages::priceFor($t, $people, $from), self::paidValue($next), $from, $to, count($people));
                $out['next_from'] = $from->toDateString();
            }
        }

        return $out;
    }

    private static function baseRow(Purchase $p): array
    {
        return ['id' => $p->id, 'name' => $p->package->name, 'people' => self::people($p)->pluck('name')->values(),
            'valid_from' => $p->valid_from->toDateString(), 'valid_to' => $p->valid_to?->toDateString(),
            'paid' => $p->isPaid(), 'paid_fmt' => Money::fmt(self::paidValue($p)), 'status' => $p->invoice?->status ?? 'paid'];
    }

    private static function row(Package $t, array $price, int $credit, Carbon $from, Carbon $to, int $n): array
    {
        $amount = max(0, $price['price'] - $credit);

        return ['package_id' => $t->id, 'name' => $t->name, 'gives_access' => (bool) $t->gives_access, 'persons' => $n, 'self_service' => (bool) $t->self_service,
            'valid_from' => $from->toDateString(), 'valid_to' => $to->toDateString(),
            'price' => $price['price'], 'price_fmt' => Money::fmt($price['price']), 'capped' => $price['capped'],
            'credit' => $credit, 'credit_fmt' => Money::fmt($credit), 'amount' => $amount, 'amount_fmt' => Money::fmt($amount)];
    }

    /** Skift husstandens medlemskab. Returnerer det nye køb (med regning). */
    public static function change(User $payer, Package $target, string $when, Collection $members, array $newPeople, bool $staff = false): Purchase
    {
        abort_unless($target->isMembership() && ($target->self_service || $staff) && ! $target->archived_at && ! $target->isOffer() && $target->period_type === 'dates', 422, 'Medlemskabet kan ikke vælges her.');
        [$cur, $next] = self::bases($payer);
        abort_unless($cur || $next, 422, 'Husstanden har ikke et medlemskab, der kan udvides.');
        foreach ($members as $m) {
            abort_if(Packages::overlapping($m, now()->startOfDay(), null), 422, $m->name.' har allerede et medlemskab.');
        }

        if ($when === 'now') {
            abort_unless($cur, 422, 'Husstanden har ikke et medlemskab i den nuværende sæson.');
            $from = now()->startOfDay(); $to = $cur->valid_to->copy();
            $base = $cur;
        } else {
            $base = $next ?? $cur;
            [$from, $to] = self::nextWindow($target, $cur, $next);
        }
        $people = self::people($base, $when === 'next' && ! $next)->merge($members)->unique('id')->values();
        $rows = array_merge(Packages::peopleRows($people), $newPeople);
        if ($err = $target->compositionError($rows, $from)) abort(422, $err);

        return DB::transaction(function () use ($payer, $target, $when, $base, $cur, $next, $people, $newPeople, $from, $to) {
            // Næste sæsons ubetalte kontingent erstattes nu (genåbnes, hvis skiftet fortrydes)
            $reopen = false;
            if ($when === 'next' && $next && $next->invoice?->status === 'open') {
                $next->invoice->update(['status' => 'cancelled']); $next->update(['cancelled_at' => now()]); $reopen = true;
            }
            // Fra næste sæson uden et oprettet kontingent: et nyt køb for næste sæson (fornyelsen af det nuværende), intet modregnes
            $replaces = $when === 'now' || $next ? $base : null;
            $purchase = self::create($payer, $target, $base, $replaces, $people, $newPeople, $from, $to, [
                'when' => $when, 'reopen_replaced' => $reopen, 'also_next' => $when === 'now' ? $next?->id : null,
            ], $replaces ? null : $cur->id);
            Packages::settleIfFree($purchase->invoice);

            return $purchase;
        });
    }

    /** Opret køb + regning for skiftet. Prisen modregnes det, der er betalt for det køb, der erstattes ($replaces). */
    private static function create(User $payer, Package $target, Purchase $base, ?Purchase $replaces, Collection $people, array $newPeople, Carbon $from, Carbon $to, array $change, ?int $renewedFrom = null): Purchase
    {
        $rows = array_merge(Packages::peopleRows($people), $newPeople);
        $pr = Packages::priceFor($target, $rows, $from);
        $credit = ! $replaces || $replaces->cancelled_at ? 0 : self::paidValue($replaces);
        $amount = max(0, $pr['price'] - $credit);
        $names = $people->pluck('name')->merge(array_column($newPeople, 'name'));
        $invoice = Invoice::create([
            'payer_id' => $payer->id, 'member_id' => null, 'kind' => 'membership',
            'description' => $target->name.Packages::periodText($from, $to).' – '.self::who($names)
                .($pr['capped'] ? ' (højst '.count($rows).' × enkeltkontingent)' : '')
                .($credit ? ' (fratrukket '.Money::fmt($credit).' betalt for '.$replaces->package->name.')' : ''),
            'amount' => $amount, 'due_on' => now(), 'period' => Season::label($from), 'account_id' => $target->account_id,
            'payload' => ['action' => 'purchase', 'package_id' => $target->id, 'new_members' => $newPeople,
                'value' => $pr['price'], 'credit' => $credit, 'change' => $change + ['replaces' => $replaces?->id, 'base' => $base->id]],
        ]);
        $purchase = Purchase::create([
            'package_id' => $target->id, 'payer_id' => $payer->id, 'invoice_id' => $invoice->id,
            'valid_from' => $from, 'valid_to' => $to, 'replaces_id' => $replaces?->id, 'renewed_from_id' => $renewedFrom,
        ]);
        // Den, der er fjernet fra næste sæson, fortsætter heller ikke, når det nye medlemskab fornyes
        $stops = $base->users->filter(fn ($u) => ! ($u->pivot->renew ?? true))->pluck('id');
        $purchase->users()->attach($people->pluck('id')->mapWithKeys(fn ($id) => [$id => ['renew' => ! $stops->contains($id)]])->all());
        $invoice->update(['payload' => $invoice->payload + ['purchase_id' => $purchase->id]]);
        Audit::log('membership.change_ordered', $purchase, ['from' => $base->package->name, 'to' => $target->name,
            'when' => $change['when'] ?? null, 'amount' => $amount, 'credit' => $credit, 'persons' => $names->all()]);

        return $purchase;
    }

    /** Kaldes fra Packages::onPaid, når et skift er betalt (nye personer er da oprettet og knyttet til købet). */
    public static function onPaid(Purchase $purchase, Invoice $invoice): void
    {
        $change = $invoice->payload['change'] ?? null;
        if (! $change) return;
        $old = $purchase->replaces_id ? Purchase::with('invoice')->find($purchase->replaces_id) : null;
        if ($old && ! $old->cancelled_at) {
            if ($old->valid_from->lt($purchase->valid_from)) $old->update(['valid_to' => $purchase->valid_from->copy()->subDay()]);
            else {
                if ($old->invoice?->status === 'open') $old->invoice->update(['status' => 'cancelled']);
                $old->update(['cancelled_at' => now()]);
            }
            Audit::log('membership.replaced', $old, ['by_purchase' => $purchase->id]);
        }

        // Med det samme, og næste sæsons kontingent var allerede oprettet: ret det også
        if ($nextId = $change['also_next'] ?? null) {
            $next = Purchase::with('package', 'users', 'invoice')->find($nextId);
            if (! $next || $next->cancelled_at) return;
            $target = $purchase->package;
            [$from, $to] = $target->dateWindowStartingIn($next->valid_from->year);
            $people = self::people($next)->merge($purchase->users()->get())->unique('id')->values();
            if ($err = $target->compositionError(Packages::peopleRows($people), $from)) {
                Audit::log('membership.change_next_skipped', $next, ['reason' => $err]);

                return;
            }
            if ($next->invoice?->status === 'open') { $next->invoice->update(['status' => 'cancelled']); $next->update(['cancelled_at' => now()]); }
            $n = self::create($purchase->payer, $target, $next, $next->cancelled_at ? null : $next, $people, [], $from, $to, ['when' => 'next']);
            if ($n->invoice->amount > 0) {
                $purchase->payer->notify(new \App\Notifications\ClubMessage('Kontingent '.Season::label($from),
                    'Næste sæsons kontingent er rettet til '.$target->name.'. Der skal betales '.Money::fmt($n->invoice->amount).'.', '/profil/regninger'));
            }
            Packages::settleIfFree($n->invoice);
        }
    }

    /** Fortrudt skift: et ubetalt kontingent, som skiftet erstattede, genåbnes. */
    public static function onCancelled(Purchase $purchase): void
    {
        $change = $purchase->invoice?->payload['change'] ?? null;
        if (! ($change['reopen_replaced'] ?? false) || ! $purchase->replaces_id) return;
        $old = Purchase::with('invoice')->find($purchase->replaces_id);
        if ($old?->invoice?->status === 'cancelled') {
            $old->invoice->update(['status' => 'open']); $old->update(['cancelled_at' => null]);
        }
    }

    /* ---------- Hvem er med næste sæson (fjern personer, skift type, del op) ---------- */

    /**
     * Næste sæson bygger på næste sæsons kontingent, hvis det er oprettet, ellers på det nuværende medlemskab.
     * Mode: next_renewal (gemmes på det nuværende og bruges ved fornyelse), replace_upcoming (ubetalt regning skiftes ud),
     * locked (næste sæson er betalt), not_renewable (fornyes ikke automatisk).
     */
    public static function nextPlan(?Purchase $cur, ?Purchase $up): array
    {
        $base = $up ?? $cur;
        $mode = $up ? ($up->isPaid() ? 'locked' : 'replace_upcoming') : ($cur->package->period_type === 'dates' && $cur->valid_to ? 'next_renewal' : 'not_renewable');
        $target = $up ? $up->package : Packages::renewalTarget($cur);
        $own = $up ? collect() : self::people($cur, true)->filter(fn ($u) => $u->pivot->renew_package_id)
            ->mapWithKeys(fn ($u) => [$u->id => Package::find($u->pivot->renew_package_id)]);
        $row = fn (Purchase $p) => ['id' => $p->id, 'name' => $p->package->name, 'valid_from' => $p->valid_from->toDateString(),
            'valid_to' => $p->valid_to?->toDateString(), 'paid' => $p->isPaid(), 'payer' => $p->payer?->name,
            'amount_fmt' => $p->invoice ? Money::fmt($p->invoice->amount) : null, 'people' => self::people($p)->pluck('name')->values()];

        return [
            'mode' => $mode, 'current' => $cur ? $row($cur) : null, 'upcoming' => $up ? $row($up) : null,
            'split' => $own->isNotEmpty(),
            'next_as' => ['id' => $target->id, 'name' => $target->name, 'changed' => ! $up && (bool) $cur->renew_package_id],
            'next_from' => in_array($mode, ['next_renewal', 'replace_upcoming']) ? self::nextFrom($target, $cur, $up)->toDateString() : null,
            'people' => self::people($base)->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'age' => $u->age(),
                'renews' => $up ? true : (bool) ($u->pivot->renew ?? true),
                'own' => isset($own[$u->id]) ? ['id' => $own[$u->id]->id, 'name' => $own[$u->id]->name] : null])->values(),
        ];
    }

    private static function nextFrom(Package $t, ?Purchase $cur, ?Purchase $up): Carbon
    {
        return $t->dateWindowStartingIn($up ? $up->valid_from->year : $cur->valid_to->copy()->addDay()->year)[0];
    }

    /** De valgte personer (skal være med på medlemskabet nu). */
    private static function chosen(?Purchase $cur, ?Purchase $up, array $ids): Collection
    {
        $all = self::people($up ?? $cur);
        abort_if(array_diff($ids, $all->pluck('id')->all()), 422, 'Der kan kun vælges personer, som er med på medlemskabet. Nye personer tilføjes med "Tilføj person".');
        $people = $all->whereIn('id', $ids)->values();
        abort_if($people->isEmpty(), 422, 'Mindst én person skal fortsætte. Skal hele medlemskabet stoppe, så kontakt bestyrelsen.');

        return $people;
    }

    /** Medlemskaber, der passer til $people næste sæson (med pris). */
    private static function fitting(?Purchase $cur, ?Purchase $up, Collection $people, bool $staff): array
    {
        $current = $up ? $up->package : Packages::renewalTarget($cur);
        // Pakker, der selv fornyes som en anden (fx halv sæson → hel sæson), giver ikke mening for en hel næste sæson
        $targets = self::targets($staff)->reject(fn ($t) => $t->renews_to_id && $t->id !== $current->id);
        if (! $targets->contains('id', $current->id) && ! $current->archived_at) $targets->push($current->load('slots.category'));
        $rows = Packages::peopleRows($people);
        $out = [];
        foreach ($targets as $t) {
            $from = self::nextFrom($t, $cur, $up);
            if ($t->compositionError($rows, $from)) continue;
            $pr = Packages::priceFor($t, $rows, $from);
            $out[] = ['package_id' => $t->id, 'name' => $t->name, 'current' => $t->id === $current->id, 'self_service' => (bool) $t->self_service,
                'composition_label' => $t->compositionLabel(), 'price' => $pr['price'], 'price_fmt' => Money::fmt($pr['price']), 'capped' => $pr['capped']];
        }

        return $out;
    }

    /**
     * Medlemskaber for dem, der fortsætter: samlet (ét medlemskab til alle) og hver for sig (et eget til hver person).
     * @return array{together: array, single: array<int, array>}
     */
    public static function nextOptions(?Purchase $cur, ?Purchase $up, array $ids, bool $staff): array
    {
        $people = self::chosen($cur, $up, $ids);

        return [
            'together' => self::fitting($cur, $up, $people, $staff),
            'single' => $people->count() > 1 ? $people->mapWithKeys(fn ($u) => [$u->id => self::fitting($cur, $up, collect([$u]), $staff)])->all() : (object) [],
        ];
    }

    /**
     * Gem, hvem der er med næste sæson, og som hvilket medlemskab: samlet ($packageId) eller hver for sig ($split: person => pakke).
     * Returnerer en besked til brugeren.
     */
    public static function setNext(?Purchase $cur, ?Purchase $up, array $ids, ?int $packageId, ?array $split, User $by, bool $staff): string
    {
        $plan = self::nextPlan($cur, $up);
        if ($plan['mode'] === 'locked') abort(422, 'Medlemskabet for næste sæson ('.$up->package->name.') er allerede betalt og kan ikke ændres her.');
        if ($plan['mode'] === 'not_renewable') abort(422, $cur->package->name.' fornyes ikke automatisk.');
        $people = self::chosen($cur, $up, $ids);
        $opts = self::nextOptions($cur, $up, $ids, $staff);

        // Grupper: [pakke, personer]
        if ($split && $people->count() > 1) {
            $groups = $people->map(function ($u) use ($split, $opts) {
                $pid = (int) ($split[$u->id] ?? 0);
                abort_unless(collect($opts['single'][$u->id] ?? [])->contains('package_id', $pid), 422, 'Vælg et medlemskab, der passer til '.$u->name.'.');

                return [Package::with('slots.category')->find($pid), collect([$u])];
            })->all();
        } else {
            abort_unless($packageId && collect($opts['together'])->contains('package_id', $packageId), 422,
                $opts['together'] ? 'Vælg et medlemskab, der passer til de valgte personer.' : 'Intet medlemskab passer til alle de valgte personer. Vælg "Hver sit medlemskab".');
            $groups = [[Package::with('slots.category')->find($packageId), $people]];
        }
        $stops = collect($plan['people'])->whereNotIn('id', $ids)->pluck('name');
        $payer = ($up ?? $cur)->payer;
        $label = collect($groups)->map(fn ($g) => $g[0]->name.(count($groups) > 1 ? ' ('.$g[1]->first()->name.')' : ''))->join(', ', ' og ');

        if ($up) {
            $before = $up->package->name;
            if (count($groups) === 1 && $groups[0][0]->id === $up->package_id && $stops->isEmpty()) return 'Ingen ændringer.';
            $sum = DB::transaction(function () use ($up, $groups, $by) {
                Packages::cancel($up, $by);
                $sum = 0;
                foreach ($groups as [$target, $ppl]) {
                    [$from, $to] = $target->dateWindowStartingIn($up->valid_from->year);
                    $sum += Packages::createRenewal($up, $target, $ppl, $from, $to, ['renewed_from_id' => $up->renewed_from_id, 'access_from' => $up->access_from])->amount;
                }

                return $sum;
            });
            $from = self::nextFrom($groups[0][0], $cur, $up);
            $msg = 'Regningen for '.Season::label($from).' er skiftet ud: '.$label.', i alt '.Money::fmt($sum).'.';
        } else {
            $before = Packages::renewalTarget($cur)->name;
            $default = $cur->package->renewsTo ?? $cur->package;
            $together = count($groups) === 1 && $groups[0][1]->count() === $people->count() && ! $split ? $groups[0][0] : null;
            $own = [];
            if (! $together) foreach ($groups as [$target, $ppl]) foreach ($ppl as $u) $own[$u->id] = $target->id;
            DB::transaction(function () use ($cur, $ids, $together, $default, $own) {
                foreach (self::people($cur) as $u) {
                    $cur->users()->updateExistingPivot($u->id, ['renew' => in_array($u->id, $ids), 'renew_package_id' => $own[$u->id] ?? null]);
                }
                $cur->update(['renew_package_id' => $together && $together->id !== $default->id ? $together->id : null]);
            });
            $from = self::nextFrom($groups[0][0], $cur, null);
            $msg = 'Fra '.Season::label($from).' fornyes medlemskabet som '.$label.'.';
        }
        if ($stops->isNotEmpty()) $msg .= ' '.self::who($stops).' er ikke med fra '.$from->locale('da')->isoFormat('D. MMMM YYYY').'.';
        Audit::log('membership.next_changed', $payer, ['by' => $by->name, 'from' => $before, 'to' => $label,
            'season' => Season::label($from), 'stops' => $stops->values()->all(), 'continues' => $people->pluck('name')->all()]);

        return $msg;
    }

    private static function who($names): string
    {
        $n = collect($names)->values();

        return $n->count() > 1 ? $n->slice(0, -1)->implode(', ').' og '.$n->last() : (string) $n->first();
    }
}
