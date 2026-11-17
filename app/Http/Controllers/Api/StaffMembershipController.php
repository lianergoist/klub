<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Household;
use App\Models\Package;
use App\Models\Purchase;
use App\Models\User;
use App\Support\Audit;
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
            'email' => ['nullable', 'email', Rule::unique('users', 'email')->where(fn ($q) => $q->where('status', '!=', 'guest'))],
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:200', 'postal_code' => 'nullable|string|max:10', 'city' => 'nullable|string|max:100',
            'emergency_name' => 'nullable|string|max:120', 'emergency_phone' => 'nullable|string|max:30',
            'package_id' => ['required', Rule::exists('packages', 'id')->where('kind', 'membership')->whereNull('archived_at')],
            'consent' => 'accepted',
        ], ['consent.accepted' => 'Bekræft, at medlemmet har givet samtykke.', 'package_id.required' => 'Vælg et medlemskab.', 'email.unique' => 'Der findes allerede et medlem med den e-mail.']);

        $package = Package::with('slots.category')->findOrFail($d['package_id']);
        abort_unless($package->windowFor(now()), 422, $package->name.' kan kun købes i perioden '.$package->periodLabel().'.');
        if ($err = $package->compositionError([['name' => $d['name'], 'birthdate' => Carbon::parse($d['birthdate'])->toDateString()]])) abort(422, $err);
        unset($d['package_id'], $d['consent']);

        [$member, $invoice] = DB::transaction(function () use ($d, $package, $r) {
            // En gæsteprofil (prøvetræning/klippekort) med samme e-mail bliver til medlemsprofilen
            $member = ! empty($d['email']) ? User::where('email', $d['email'])->where('status', 'guest')->first() : null;
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

    public function membershipOptions(Request $r, User $member)
    {
        abort_unless($r->user()->may('purchases.create'), 403);
        [$current, $upcoming] = self::memberships_of($member);
        $row = fn (?Purchase $p) => $p ? [
            'id' => $p->id, 'name' => $p->package->name, 'valid_from' => $p->valid_from->toDateString(), 'valid_to' => $p->valid_to?->toDateString(),
            'paid' => $p->isPaid(), 'people' => $p->users->pluck('name')->values(),
            'renews_as' => ['id' => Packages::renewalTarget($p)->id, 'name' => Packages::renewalTarget($p)->name, 'changed' => (bool) $p->renew_package_id],
            'renewable' => $p->package->period_type === 'dates' && (bool) $p->valid_to,
        ] : null;
        $mode = $upcoming ? ($upcoming->isPaid() ? 'locked' : 'replace_upcoming') : ($current ? ($row($current)['renewable'] ? 'next_renewal' : 'not_renewable') : 'now');

        return [
            'mode' => $mode, 'current' => $row($current), 'upcoming' => $row($upcoming),
            'payer' => self::payerOf($member)->name,
            'memberships' => self::memberships()->filter(fn ($p) => $mode === 'now' ? $p->windowFor(now()) : $p->period_type === 'dates')
                ->map(fn ($p) => self::membershipRow($p))->values(),
        ];
    }

    public function changeMembership(Request $r, User $member)
    {
        abort_unless($r->user()->may('purchases.create'), 403, 'Du har ikke adgang til at ændre medlemskaber.');
        $d = $r->validate(['package_id' => ['required', Rule::exists('packages', 'id')->where('kind', 'membership')->whereNull('archived_at')]]);
        $target = Package::with('slots.category')->findOrFail($d['package_id']);
        [$current, $upcoming] = self::memberships_of($member);
        $by = $r->user();

        // Næste sæson er allerede opkrævet: ubetalt → regningen skiftes ud; betalt → kan ikke ændres her
        if ($upcoming) {
            abort_if($upcoming->isPaid(), 422, 'Medlemskabet for næste periode ('.$upcoming->package->name.') er allerede betalt og kan ikke ændres her.');
            abort_unless($target->period_type === 'dates', 422, $target->name.' har ikke faste datoer og kan ikke bruges til næste sæson.');
            if ($target->id === $upcoming->package_id) return ['message' => 'Næste periode er allerede '.$target->name.'.'];
            $people = $upcoming->users->whereNotIn('status', ['resigned'])->values();
            [$from, $to] = $target->dateWindowStartingIn($upcoming->valid_from->year);
            if ($err = $target->compositionError(Packages::peopleRows($people), $from)) abort(422, $err);
            $inv = DB::transaction(function () use ($upcoming, $target, $people, $from, $to, $by) {
                Packages::cancel($upcoming, $by);
                return Packages::createRenewal($upcoming, $target, $people, $from, $to, ['renewed_from_id' => $upcoming->renewed_from_id, 'access_from' => $upcoming->access_from]);
            });
            Audit::log('membership.changed_by_staff', $member, ['from' => $upcoming->package->name, 'to' => $target->name, 'when' => 'næste periode (regning skiftet ud)', 'invoice' => $inv->id]);

            return ['message' => 'Regningen for '.Season::label($from).' er skiftet til '.$target->name.' ('.Money::fmt($inv->amount).').'];
        }

        // Medlemskab i dag: skiftet gemmes og bruges, når kassereren opkræver kontingent for næste sæson
        if ($current) {
            abort_unless($current->package->period_type === 'dates' && $current->valid_to, 422, $current->package->name.' fornyes ikke automatisk. Køb et nyt medlemskab, når det udløber.');
            abort_unless($target->period_type === 'dates', 422, $target->name.' har ikke faste datoer og kan ikke bruges ved fornyelse.');
            $people = $current->users->whereNotIn('status', ['resigned', 'guest'])->values();
            $year = $current->valid_to->copy()->addDay()->year;
            [$from] = $target->dateWindowStartingIn($year);
            if ($err = $target->compositionError(Packages::peopleRows($people), $from)) abort(422, $err);
            $default = $current->package->renewsTo ?? $current->package;
            $before = Packages::renewalTarget($current)->name;
            $current->update(['renew_package_id' => $target->id === $default->id ? null : $target->id]);
            Audit::log('membership.changed_by_staff', $member, ['from' => $before, 'to' => $target->name, 'when' => 'næste opkrævning ('.Season::label($from).')']);

            return ['message' => 'Fra næste opkrævning ('.Season::label($from).') fornyes medlemskabet som '.$target->name.'.'];
        }

        // Intet medlemskab: oprettes med det samme
        $purchase = Packages::buy(self::payerOf($member), $target, [$member->id], [], null, staff: true);
        Packages::settleIfFree($purchase->invoice);
        $inv = $purchase->invoice->fresh();
        Audit::log('membership.changed_by_staff', $member, ['to' => $target->name, 'when' => 'nu', 'invoice' => $inv->id]);

        return ['message' => $target->name.' er oprettet'.($inv->status === 'paid' ? '.' : ' – regning på '.Money::fmt($inv->amount).' sendt til '.self::payerOf($member)->name.'.')];
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
