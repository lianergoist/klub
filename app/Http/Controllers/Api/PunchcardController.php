<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Purchase;
use App\Models\User;
use App\Support\Audit;
use App\Support\Money;
use App\Support\Packages;
use App\Support\Punchcards;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Klippekort for ikke-medlemmer (fx SUP 10-turskort).
 * Gæster køber kortet og opretter et login på /klippekort, ser deres kort og de aktiviteter, kortet gælder til,
 * og tilmelder sig med et klip. Kassereren kan overføre papkort (klip uden betaling).
 */
class PunchcardController extends Controller
{
    /** Klippekort, der kan købes nu (offentlig). */
    public function offer()
    {
        return self::offers();
    }

    private static function offers()
    {
        return Package::with('categories')->where('uses', '>', 0)->where('guest_only', true)->where('self_service', true)
            ->whereNull('archived_at')->orderBy('position')->get()->filter(fn ($p) => $p->buyableOn(now()))
            ->map(fn ($p) => [
                'id' => $p->id, 'name' => $p->name, 'description' => $p->description, 'uses' => $p->uses,
                'price' => $p->price(), 'price_fmt' => Money::fmt($p->price()), 'period_label' => $p->periodLabel(),
                'categories' => $p->categories->pluck('name')->values(),
            ])->values();
    }

    /** Opret login som gæst – og køb evt. et klippekort med det samme. Medlemmer kan ikke købe klippekort. */
    public function signup(Request $r)
    {
        $d = $r->validate([
            'package_id' => 'nullable|integer',
            'name' => 'required|string|max:120',
            'email' => 'required|email',
            'phone' => 'required|string|max:30',
            'birthdate' => 'required|date|before:today',
            'password' => 'required|min:8',
            'consent' => 'accepted',
        ], ['consent.accepted' => 'Du skal give samtykke for at kunne oprette et login.']);
        $existing = User::where('email', $d['email'])->first();
        abort_if($existing && $existing->status !== 'guest', 422, 'Du er medlem af klubben. Klippekort er kun for ikke-medlemmer – log ind og tilmeld dig som medlem.');
        abort_if($existing && $existing->password, 422, 'Du har allerede et login. Log ind for at købe et nyt klippekort.');
        $package = null;
        if (! empty($d['package_id'])) {
            $package = Package::where('uses', '>', 0)->where('guest_only', true)->whereNull('archived_at')->findOrFail($d['package_id']);
        }

        $fields = ['name' => $d['name'], 'phone' => $d['phone'], 'birthdate' => $d['birthdate'], 'password' => Hash::make($d['password']), 'consent_at' => now()];
        $guest = $existing ?? User::create($fields + ['email' => $d['email'], 'status' => 'guest', 'notify_email' => true]);
        if ($existing) $existing->update($fields);
        Audit::log($existing ? 'guest.login_created' : 'guest.created', $guest, ['punchcard' => (bool) $package]);

        $invoiceId = null;
        if ($package) {
            $purchase = Packages::buy($guest->fresh(), $package, [$guest->id]);
            Packages::settleIfFree($purchase->invoice);
            $invoiceId = $purchase->invoice->fresh()->status === 'open' ? $purchase->invoice_id : null;
        }

        return ['token' => $guest->createToken('app')->plainTextToken, 'user' => AuthController::me($guest->fresh()), 'invoice_id' => $invoiceId];
    }

    /** Gæstens side: klippekort, aktiviteter og ubetalte regninger. */
    public function home(Request $r)
    {
        $u = $r->user();
        $cards = Purchase::with('package', 'invoice')->whereHas('users', fn ($q) => $q->whereKey($u->id))
            ->whereHas('package', fn ($q) => $q->where('uses', '>', 0))->whereNull('cancelled_at')->latest('id')->get()
            ->filter(fn ($p) => ! $p->invoice || in_array($p->invoice->status, ['open', 'paid']));
        $mine = \App\Models\Registration::where('user_id', $u->id)->get()->keyBy('activity_id');

        return [
            'name' => $u->name,
            'is_member' => $u->status !== 'guest',
            'cards' => $cards->map(fn ($p) => [
                'id' => $p->id, 'name' => $p->package->name, 'uses' => $p->package->uses, 'uses_left' => $p->uses_left,
                'valid_to' => $p->valid_to?->toDateString(), 'note' => $p->note,
                'paid' => $p->isPaid(), 'invoice_id' => $p->invoice?->status === 'open' ? $p->invoice_id : null,
                'expired' => $p->valid_to && $p->valid_to->lt(now()->startOfDay()),
            ])->values(),
            'offers' => $u->status === 'guest' ? self::offers() : [],
            'activities' => Punchcards::activitiesFor($u)->map(fn ($a) => [
                'id' => $a->id, 'title' => $a->title, 'starts_at' => $a->starts_at->toIso8601String(), 'ends_at' => $a->ends_at->toIso8601String(),
                'location' => $a->location, 'description' => $a->description,
                'max' => $a->max_participants, 'signed_up' => $a->signed_up_count,
                'is_full' => $a->max_participants && $a->signed_up_count >= $a->max_participants,
                'deadline_passed' => $a->deadlinePassed(), 'cancel_locked' => $a->cancelLocked(), 'cancel_hours' => $a->cancel_hours,
                'mine' => ($m = $mine[$a->id] ?? null) && $m->status === 'signed_up',
                'has_card' => (bool) Punchcards::cardFor($u, $a),
            ])->values(),
            'open_invoices' => Invoice::where('payer_id', $u->id)->where('status', 'open')->get()->map(fn ($i) => FinanceController::invoiceRow($i)),
        ];
    }

    public function buy(Request $r)
    {
        $u = $r->user();
        abort_unless($u->status === 'guest', 422, 'Klippekort er kun for ikke-medlemmer.');
        $package = Package::where('uses', '>', 0)->where('guest_only', true)->whereNull('archived_at')->findOrFail($r->validate(['package_id' => 'required|integer'])['package_id']);
        $purchase = Packages::buy($u, $package, [$u->id]);
        Packages::settleIfFree($purchase->invoice);

        return ['invoice_id' => $purchase->invoice_id, 'paid' => $purchase->invoice->fresh()->status === 'paid'];
    }

    public function punch(Request $r, Activity $activity)
    {
        abort_unless($r->user()->status === 'guest', 422, 'Som medlem tilmelder du dig under Aktiviteter.');
        Punchcards::punch($r->user(), $activity->load('categories'));

        return $this->home($r);
    }

    public function unpunch(Request $r, Activity $activity)
    {
        Punchcards::unpunch($r->user(), $activity);

        return $this->home($r);
    }

    /** Kassereren/bestyrelsen overfører et papkort: et klippekort med et antal klip, uden betaling. */
    public function transfer(Request $r, User $member)
    {
        abort_unless($r->user()->hasRole('board', 'treasurer', 'admin'), 403);
        abort_unless($member->status === 'guest', 422, 'Klippekort er kun for ikke-medlemmer.');
        $d = $r->validate(['package_id' => 'required|integer', 'uses_left' => 'required|integer|min:1|max:100', 'note' => 'nullable|string|max:200']);
        $package = Package::where('uses', '>', 0)->findOrFail($d['package_id']);
        [$from, $to] = $package->windowFor(now()) ?? [now(), now()->addYear()];
        $p = Purchase::create(['package_id' => $package->id, 'payer_id' => $member->id, 'valid_from' => $from, 'valid_to' => $to,
            'uses_left' => $d['uses_left'], 'note' => $d['note'] ?: 'Overført fra papkort']);
        $p->users()->attach($member->id);
        Audit::log('punchcard.transferred', $p, ['member' => $member->name, 'uses_left' => $d['uses_left'], 'by' => $r->user()->name]);

        return ['ok' => true];
    }
}
