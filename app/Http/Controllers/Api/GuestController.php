<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Household;
use App\Models\Invoice;
use App\Models\Registration;
use App\Models\User;
use App\Notifications\ClubMessage;
use App\Support\Audit;
use App\Support\Money;
use App\Support\Prices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ikke-medlemmer kan købe et tilbud (pakker af typen "offer", fx prøvetræning eller SUP-kursus) til en aktivitet, der er åben for det.
 * Tidligere: en prøvetræning eller et SUP-kursus. De oprettes som "gæst" (uden adgang til medlemsdelen),
 * tilmeldes aktiviteten efter betaling, og beløbet modregnes, hvis de senere melder sig ind.
 */
class GuestController extends Controller
{
    public function offers()
    {
        $acts = Activity::whereNotNull('guest_package_id')->where('status', 'active')->whereNull('cancelled_at')->where('starts_at', '>', now())
            ->where(fn ($q) => $q->whereNull('signup_deadline')->orWhere('signup_deadline', '>', now()))
            ->orderBy('starts_at')->get();

        return \App\Models\Package::where('kind', 'offer')->whereNull('archived_at')->orderBy('position')->orderBy('id')->get()->map(fn ($o) => [
            'code' => $o->id, 'label' => $o->name, 'text' => $o->description, 'creditable' => $o->creditable,
            'price' => $o->price(), 'price_fmt' => Money::fmt($o->price()),
            'activities' => $acts->where('guest_package_id', $o->id)->map(fn ($a) => [
                'id' => $a->id, 'title' => $a->title, 'starts_at' => $a->starts_at->toIso8601String(), 'ends_at' => $a->ends_at->toIso8601String(),
                'location' => $a->location, 'full' => $a->isFull(),
                'free' => $a->max_participants ? max(0, $a->max_participants - $a->takenSeats()) : null,
            ])->values(),
        ])->values();
    }

    public function book(Request $r)
    {
        $d = $r->validate([
            'activity_id' => 'required|integer', 'name' => 'required|string|max:120', 'email' => 'required|email|max:190',
            'phone' => 'required|string|max:30', 'birthdate' => 'required|date|before:today',
            'guardian_name' => 'nullable|string|max:120', 'guardian_phone' => 'nullable|string|max:30',
            'consent' => 'accepted',
        ], ['consent.accepted' => 'Du skal give samtykke, før vi kan tilmelde dig.']);

        $a = Activity::with('guestPackage')->whereNotNull('guest_package_id')->findOrFail($d['activity_id']);
        abort_if(! $a->guestPackage || $a->guestPackage->archived_at, 422, 'Tilbuddet findes ikke længere.');
        abort_if($a->cancelled_at || $a->isPending() || $a->starts_at->isPast(), 422, 'Aktiviteten er ikke længere åben.');
        abort_if($a->isFull(), 422, 'Der er desværre ikke flere pladser. Vælg en anden dato.');
        $minor = \Illuminate\Support\Carbon::parse($d['birthdate'])->age < 18;
        abort_if($minor && (! ($d['guardian_name'] ?? null) || ! ($d['guardian_phone'] ?? null)), 422, 'Under 18 år: angiv navn og telefon på en forælder.');

        $existing = User::where('email', $d['email'])->first();
        abort_if($existing && $existing->status !== 'guest', 422, 'Du er allerede medlem. Log ind for at tilmelde dig aktiviteter.');

        return DB::transaction(function () use ($d, $a, $existing, $minor) {
            $fields = [
                'name' => $d['name'], 'email' => $d['email'], 'phone' => $d['phone'], 'birthdate' => $d['birthdate'],
                'emergency_name' => $minor ? $d['guardian_name'] : null, 'emergency_phone' => $minor ? $d['guardian_phone'] : null,
                'status' => 'guest', 'consent_at' => now(),
            ];
            $guest = $existing ?? User::create($fields);
            if ($existing) $existing->update($fields);
            if (! $guest->household_id) {
                $guest->update(['household_id' => Household::create(['name' => 'Husstand '.$guest->name, 'payer_id' => $guest->id])->id]);
            }

            $reg = Registration::firstOrNew(['activity_id' => $a->id, 'user_id' => $guest->id]);
            abort_if($reg->exists && $reg->status === 'signed_up', 422, 'Du er allerede tilmeldt.');
            $offer = $a->guestPackage;
            $inv = Invoice::create([
                'payer_id' => $guest->id, 'member_id' => $guest->id, 'kind' => 'guest',
                'description' => $offer->name.': '.$a->title.' '.$a->starts_at->format('d.m.Y').' – '.$guest->name,
                'amount' => $offer->price(), 'due_on' => now(), 'activity_id' => $a->id,
                'account_id' => $offer->account_id ?? FinanceController::accountId('1100'),
                'payload' => ['offer_package_id' => $offer->id, 'offer_label' => $offer->name, 'creditable' => $offer->creditable],
            ]);
            $reg->fill(['status' => 'pending_payment', 'invoice_id' => $inv->id]);
            $reg->updated_at = now();
            $reg->save();
            $reg->log('reserved', $guest);
            Audit::log('guest.booked', $guest, ['activity' => $a->id, 'offer' => $offer->name]);

            return [
                'token' => $guest->createToken('guest', ['guest'])->plainTextToken,
                'user' => AuthController::me($guest->fresh()),
                'invoice_id' => $inv->id,
            ];
        });
    }

    /** Til gæstens kvitteringsside: hvad er købt, og hvad modregnes ved indmeldelse. */
    public function status(Request $r)
    {
        $u = $r->user();
        abort_unless($u->status === 'guest', 404);
        $paid = Invoice::where('payer_id', $u->id)->where('kind', 'guest')->where('status', 'paid')->with('activity')->get();

        return [
            'name' => $u->name, 'email' => $u->email, 'phone' => $u->phone, 'birthdate' => $u->birthdate?->toDateString(),
            'bookings' => Registration::where('user_id', $u->id)->where('status', 'signed_up')->with('activity')->get()->map(fn ($reg) => [
                'title' => $reg->activity->title, 'starts_at' => $reg->activity->starts_at->toIso8601String(), 'location' => $reg->activity->location,
            ]),
            'credit' => \App\Support\Memberships::guestCredit($u)['amount'],
            'credit_fmt' => Money::fmt(\App\Support\Memberships::guestCredit($u)['amount']),
            'membership_price_fmt' => Money::fmt(Prices::membership(1)),
        ];
    }
}
