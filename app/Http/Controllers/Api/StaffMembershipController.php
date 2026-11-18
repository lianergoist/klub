<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Household;
use App\Models\Package;
use App\Models\Purchase;
use App\Models\User;
use App\Support\Audit;
use App\Support\MembershipChange;
use App\Support\Money;
use App\Support\Packages;
use App\Support\Season;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Bestyrelse og kasserer: opret nyt medlem, ret et medlems medlemskab (fra næste opkrævning) og giv tilkøb,
 * medlemmerne ikke selv kan købe. Altid til pakkens normale pris; regningen går til husstandens betaler.
 */
class StaffMembershipController extends Controller
{
    private static function payerOf(User $member): User
    {
        return $member->household?->payer ?? $member;
    }

    private static function membershipRow(Package $p): array
    {
        $now = $p->windowFor(now());

        return [
            'id' => $p->id, 'name' => $p->name, 'description' => $p->description,
            'price' => $p->price(), 'price_fmt' => Money::fmt($p->price()), 'self_service' => $p->self_service,
            'composition_label' => $p->compositionLabel(), 'period_label' => $p->periodLabel(),
            'renewable' => $p->period_type === 'dates', 'buyable_now' => (bool) $now,
            'valid_from' => $now ? $now[0]->toDateString() : null, 'valid_to' => $now && $now[1] ? $now[1]->toDateString() : null,
        ];
    }

    private static function memberships()
    {
        return Package::with('slots.category')->where('kind', 'membership')->whereNull('archived_at')
            ->orderBy('position')->orderBy('id')->get();
    }

    /* ---------- Opret nyt medlem ---------- */

    public function newMemberOptions(Request $r)
    {
        abort_unless($r->user()->may('members.create'), 403);

        return ['memberships' => self::memberships()->filter(fn ($p) => $p->windowFor(now()))->map(fn ($p) => self::membershipRow($p))->values()];
    }

    public function createMember(Request $r)
    {
        abort_unless($r->user()->may('members.create'), 403, 'Du har ikke adgang til at oprette medlemmer.');
        $d = $r->validate([
            'name' => 'required|string|max:120',
            'birthdate' => 'required|date|before:today',
            // Medlemmet bliver betaler i sin egen husstand og skal kunne logge ind og betale
            'email' => ['required', 'email', Rule::unique('users', 'email')->where(fn ($q) => $q->where('status', '!=', 'guest'))],
            'phone' => ['nullable', 'string', 'max:30', \App\Support\Phone::rule(User::where('email', $r->input('email'))->where('status', 'guest')->value('id'))],
            'address' => 'nullable|string|max:200', 'postal_code' => 'nullable|string|max:10', 'city' => 'nullable|string|max:100',
            'emergency_name' => 'nullable|string|max:120', 'emergency_phone' => 'nullable|string|max:30',
            'package_id' => ['required', Rule::exists('packages', 'id')->where('kind', 'membership')->whereNull('archived_at')],
            'consent' => 'accepted',
        ], ['consent.accepted' => 'Bekræft, at medlemmet har givet samtykke.', 'package_id.required' => 'Vælg et medlemskab.', 'email.unique' => 'Der findes allerede et medlem med den e-mail.', 'email.required' => 'Skriv medlemmets e-mail – den bruges til at logge ind.', 'email.email' => 'E-mailen ser forkert ud.']);

        $package = Package::with('slots.category')->findOrFail($d['package_id']);
        abort_unless($package->windowFor(now()), 422, $package->name.' kan kun købes i perioden '.$package->periodLabel().'.');
        if ($err = $package->compositionError([['name' => $d['name'], 'birthdate' => Carbon::parse($d['birthdate'])->toDateString()]])) abort(422, $err);
        unset($d['package_id'], $d['consent']);

        [$member, $invoice] = DB::transaction(function () use ($d, $package, $r) {
            // En gæsteprofil (prøvetræning/klippekort) med samme e-mail bliver til medlemsprofilen
            $member = User::where('email', $d['email'])->where('status', 'guest')->first();
            $member ? $member->update($d + ['status' => 'pending', 'consent_at' => now()]) : $member = User::create($d + ['status' => 'pending', 'consent_at' => now()]);
            if (! $member->household_id) {
                $member->update(['household_id' => Household::create(['name' => 'Husstand '.$member->name, 'payer_id' => $member->id])->id]);
            }
            Audit::log('member.created_by_staff', $member, ['by' => $r->user()->name, 'package' => $package->name]);
            $purchase = Packages::buy($member->fresh(), $package, [$member->id], [], null, staff: true);
            $invoice = Packages::applyGuestCredit($purchase->invoice, $member);
            Packages::settleIfFree($invoice);

            return [$member, $invoice->fresh()];
        });

        return ['id' => $member->id, 'invoice_id' => $invoice->id, 'amount_fmt' => Money::fmt($invoice->amount), 'paid' => $invoice->status === 'paid'];
    }

    /* ---------- Rediger medlemskab ---------- */

    /** Medlemmets medlemskab i dag og det kommende (næste sæson), hvis det findes. */
    private static function memberships_of(User $member): array
    {
        $q = fn () => Purchase::with('package.renewsTo', 'renewPackage', 'invoice', 'users')->whereHas('package', fn ($q) => $q->where('kind', 'membership'))
            ->whereHas('users', fn ($q) => $q->whereKey($member->id))->currentOrFuture();
        $today = now()->toDateString();

        return [
            $q()->where('valid_from', '<=', $today)->latest('valid_from')->first(),
            $q()->where('valid_from', '>', $today)->oldest('valid_from')->first(),
        ];
    }

    /**
     * Husstandens medlemskaber som (nuværende, næste sæsons) par. Hvert af næste sæsons kontingenter knyttes til det
     * medlemskab, det fornyer; et nuværende medlemskab, der endnu ikke er fornyet, står alene (næste sæson rettes på det).
     * @return \Illuminate\Support\Collection<array{0:?Purchase,1:?Purchase}>
     */
    private static function groups(User $payer)
    {
        $pairs = $payer->managedMembers()->map(fn ($m) => self::memberships_of($m));
        $curs = $pairs->pluck(0)->filter()->unique('id')->values();
        $ups = $pairs->pluck(1)->filter()->unique('id')->values();
        $link = fn (Purchase $up) => $curs->firstWhere('id', $up->renewed_from_id)
            ?? $curs->first(fn ($c) => $c->users->pluck('id')->intersect($up->users->pluck('id'))->isNotEmpty());
        $out = $ups->map(fn ($up) => [$link($up), $up]);
        foreach ($curs as $c) {
            $renewed = $out->contains(fn ($g) => $g[0]?->id === $c->id)
                || Purchase::where(fn ($q) => $q->where('renewed_from_id', $c->id)->orWhere('replaces_id', $c->id))->currentOrFuture()->exists();
            if (! $renewed) $out->push([$c, null]);
        }

        return $out->sortBy(fn ($g) => ($g[0]?->id ?? 0) * 1e6 + ($g[1]?->id ?? 0))->values();
    }

    private static function group(Request $r, User $payer): array
    {
        $cur = $r->input('purchase_id'); $up = $r->input('upcoming_id');

        return self::groups($payer)->first(fn ($g) => $g[0]?->id == $cur && $g[1]?->id == $up)
            ?? abort(422, 'Medlemskabet er ændret i mellemtiden. Luk og åbn siden igen.');
    }

    /** Ikke husstandens betaler: medlemskabet rettes hos betaleren. */
    private static function mustBePayer(User $member): User
    {
        $payer = self::payerOf($member);
        abort_unless($payer->id === $member->id, 422, $member->name.' betaler ikke selv. Medlemskabet rettes hos betaleren, '.$payer->name.'.');

        return $payer;
    }

    public function membershipOptions(Request $r, User $member)
    {
        abort_unless($r->user()->may('purchases.create'), 403);
        $payer = self::payerOf($member);
        if ($payer->id !== $member->id) {
            return ['redirect' => ['id' => $payer->id, 'name' => $payer->name], 'membership' => $member->currentMembership()?->package->name];
        }
        $groups = self::groups($payer);
        $covered = $groups->flatMap(fn ($g) => collect([$g[0], $g[1]])->filter()->flatMap(fn ($p) => $p->users->pluck('id')));

        return [
            'payer' => $payer->name,
            'groups' => $groups->map(fn ($g) => ['purchase_id' => $g[0]?->id, 'upcoming_id' => $g[1]?->id] + MembershipChange::nextPlan($g[0], $g[1]))->values(),
            // Personer i husstanden uden medlemskab (kan tilføjes)
            'without' => $payer->managedMembers()->whereNotIn('id', $covered)->whereNotIn('status', ['resigned', 'guest'])
                ->map(fn ($m) => ['id' => $m->id, 'name' => $m->name, 'age' => $m->age()])->values(),
            // Til et nyt medlemskab med det samme (betaleren har intet, eller et eget medlemskab til en ny person)
            'memberships' => self::memberships()->filter(fn ($p) => $p->windowFor(now()))->map(fn ($p) => self::membershipRow($p))->values(),
        ];
    }

    /** Hvilke medlemskaber passer til de personer, der fortsætter næste sæson? */
    public function nextOptions(Request $r, User $member)
    {
        abort_unless($r->user()->may('purchases.create'), 403);
        $d = $r->validate(['member_ids' => 'array', 'member_ids.*' => 'integer']);
        [$cur, $up] = self::group($r, self::mustBePayer($member));

        return MembershipChange::nextOptions($cur, $up, $d['member_ids'] ?? [], staff: true);
    }

    /** Næste sæson: hvem fortsætter, og som hvilket medlemskab. Uden medlemskab: et nyt oprettes med det samme. */
    public function changeMembership(Request $r, User $member)
    {
        abort_unless($r->user()->may('purchases.create'), 403, 'Du har ikke adgang til at ændre medlemskaber.');
        $d = $r->validate(['package_id' => ['nullable', 'required_without:split', Rule::exists('packages', 'id')->where('kind', 'membership')->whereNull('archived_at')],
            'split' => 'nullable|array', 'member_ids' => 'array', 'member_ids.*' => 'integer']);
        $payer = self::mustBePayer($member);

        if ($r->has('purchase_id') || $r->has('upcoming_id')) {
            [$cur, $up] = self::group($r, $payer);

            return ['message' => MembershipChange::setNext($cur, $up, $d['member_ids'] ?? [], $d['package_id'] ?? null, $d['split'] ?? null, $r->user(), staff: true)];
        }
        $target = Package::with('slots.category')->findOrFail($d['package_id']);

        // Intet medlemskab: oprettes med det samme
        abort_if(self::groups($payer)->isNotEmpty(), 422, 'Vælg det medlemskab, der skal rettes.');
        $purchase = Packages::buy($payer, $target, [$member->id], [], null, staff: true);
        Packages::settleIfFree($purchase->invoice);
        $inv = $purchase->invoice->fresh();
        Audit::log('membership.changed_by_staff', $member, ['to' => $target->name, 'when' => 'nu', 'invoice' => $inv->id]);

        return ['message' => $target->name.' er oprettet'.($inv->status === 'paid' ? '.' : ' – regning på '.Money::fmt($inv->amount).' sendt til '.$payer->name.'.')];
    }

    /* ---------- Tilføj personer til husstandens medlemskab ---------- */

    private static function addPeople(Request $r, User $payer, bool $full): array
    {
        $d = $r->validate([
            'member_ids' => 'array', 'member_ids.*' => 'integer',
            'new_members' => 'array|max:10',
            'new_members.*.name' => ($full ? 'required' : 'nullable').'|string|max:120',
            'new_members.*.birthdate' => 'required|date|before:today',
        ] + ($full ? ['new_members.*.email' => 'nullable|email|distinct|unique:users,email', 'new_members.*.phone' => ['nullable', 'string', 'max:30', 'distinct', \App\Support\Phone::rule()],
            'new_members.*.consent' => 'accepted'] : []),
            ['new_members.*.consent.accepted' => 'Bekræft, at der er givet samtykke for de nye personer.'], ['new_members.*.name' => 'navn', 'new_members.*.birthdate' => 'fødselsdato', 'new_members.*.email' => 'e-mail', 'new_members.*.phone' => 'telefonnummer']);
        $household = $payer->managedMembers()->keyBy('id');
        $members = collect($d['member_ids'] ?? [])->unique()->map(fn ($id) => $household[$id] ?? abort(422, 'Personen hører ikke til husstanden.'))->values();
        $new = collect($d['new_members'] ?? [])->map(fn ($p) => ['name' => $p['name'] ?? 'Ny person', 'birthdate' => Carbon::parse($p['birthdate'])->toDateString(),
            'email' => $p['email'] ?? null, 'phone' => $p['phone'] ?? null, 'consent_at' => now()->toIso8601String()])->all();
        abort_if($full && $members->isEmpty() && ! $new, 422, 'Vælg mindst én person, der skal med.');

        return [$members, $new];
    }

    /** Udvid husstandens medlemskab (nu eller fra næste sæson) eller køb et eget medlemskab til de nye. */
    public function addOptions(Request $r, User $member)
    {
        abort_unless($r->user()->may('purchases.create'), 403);
        $payer = self::mustBePayer($member);
        [$members, $new] = self::addPeople($r, $payer, false);
        $rows = array_merge(Packages::peopleRows($members), $new);

        return [
            'change' => MembershipChange::options($payer, $members, $new, staff: true),
            'own' => self::memberships()->filter(fn ($p) => ($w = $p->windowFor(now())) && $rows && ! $p->compositionError($rows, $w[0]))
                ->map(fn ($p) => self::membershipRow($p) + ['price_for' => Money::fmt(Packages::priceFor($p, $rows, $p->windowFor(now())[0])['price'])])->values(),
        ];
    }

    public function addPerson(Request $r, User $member)
    {
        abort_unless($r->user()->may('purchases.create'), 403, 'Du har ikke adgang til at ændre medlemskaber.');
        $d = $r->validate(['package_id' => 'required|exists:packages,id', 'when' => 'required|in:now,next,own']);
        $payer = self::mustBePayer($member);
        [$members, $new] = self::addPeople($r, $payer, true);
        $package = Package::with('slots.category')->findOrFail($d['package_id']);
        $purchase = $d['when'] === 'own'
            ? Packages::buy($payer, $package, $members->pluck('id')->all(), $new, null, staff: true)
            : MembershipChange::change($payer, $package, $d['when'], $members, $new, staff: true);
        Packages::settleIfFree($purchase->invoice);
        $inv = $purchase->invoice->fresh();
        $names = $members->pluck('name')->merge(array_column($new, 'name'))->join(', ', ' og ');
        Audit::log('membership.person_added_by_staff', $payer, ['by' => $r->user()->name, 'persons' => $names, 'package' => $package->name, 'when' => $d['when'], 'invoice' => $inv->id]);

        return ['message' => $inv->status === 'paid'
            ? "$names er tilføjet ({$package->name})."
            : "Regning på ".Money::fmt($inv->amount)." sendt til {$payer->name}. $names kommer med, når den er betalt."];
    }

    /* ---------- Tilkøb, medlemmerne ikke selv kan købe ---------- */

    private static function addons(User $member)
    {
        return Package::with('slots.category', 'product.resources')->where('kind', 'product')->where('self_service', false)->whereNull('archived_at')
            ->orderBy('position')->orderBy('id')->get()
            ->filter(fn ($p) => $p->windowFor(now()) && (! $p->guest_only || $member->status === 'guest'));
    }

    public function addonOptions(Request $r, User $member)
    {
        abort_unless($r->user()->may('purchases.create'), 403);
        $payer = self::payerOf($member);

        return [
            'payer' => ['id' => $payer->id, 'name' => $payer->name],
            'household' => $payer->managedMembers()->map(fn ($m) => ['id' => $m->id, 'name' => $m->name, 'age' => $m->age(), 'status' => $m->status])->values(),
            'packages' => self::addons($member)->map(fn ($p) => PackageController::offerRow($p) + ['on_sale' => $p->onSale()])->values(),
        ];
    }

    public function addonBuy(Request $r, User $member)
    {
        abort_unless($r->user()->may('purchases.create'), 403, 'Du har ikke adgang til at give tilkøb.');
        $d = $r->validate([
            'package_id' => 'required|integer',
            'member_ids' => 'required|array|min:1', 'member_ids.*' => 'integer',
            'resource_id' => 'nullable|integer',
        ]);
        $package = self::addons($member)->firstWhere('id', $d['package_id']);
        abort_unless($package, 422, 'Tilkøbet kan ikke gives her.');
        $payer = self::payerOf($member);
        $purchase = Packages::buy($payer, $package, $d['member_ids'], [], $d['resource_id'] ?? null, staff: true);
        Packages::settleIfFree($purchase->invoice);
        $inv = $purchase->invoice->fresh();
        Audit::log('package.created_by_staff', $member, ['package' => $package->name, 'payer' => $payer->name, 'amount' => $inv->amount, 'invoice' => $inv->id]);

        return ['invoice_id' => $inv->id, 'amount_fmt' => Money::fmt($inv->amount), 'paid' => $inv->status === 'paid', 'payer' => $payer->name];
    }
}
