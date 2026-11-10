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
 * Ikke-medlemmer kan købe en prøvetræning eller et SUP-kursus. De oprettes som "gæst" (uden adgang til medlemsdelen),
 * tilmeldes aktiviteten efter betaling, og beløbet modregnes, hvis de senere melder sig ind.
 */
class GuestController extends Controller
{
    public const OFFERS = [
        'trial' => ['label' => 'Prøvetræning', 'price' => 'trial', 'text' => 'Prøv en almindelig træning med instruktør. Udstyr og våddragt er med.'],
        'sup_course' => ['label' => 'SUP-kursus', 'price' => 'sup_course', 'text' => 'Begynderkursus i stand up paddle – teknik, sikkerhed og en tur på fjorden.'],
    ];

    public function offers()
    {
        $acts = Activity::whereNotNull('guest_offer')->whereNull('cancelled_at')->where('starts_at', '>', now())
            ->where(fn ($q) => $q->whereNull('signup_deadline')->orWhere('signup_deadline', '>', now()))
            ->orderBy('starts_at')->get();

        return collect(self::OFFERS)->map(fn ($o, $code) => [
            'code' => $code, 'label' => $o['label'], 'text' => $o['text'],
            'price' => Prices::get($o['price']), 'price_fmt' => Money::fmt(Prices::get($o['price'])),
            'activities' => $acts->where('guest_offer', $code)->map(fn ($a) => [
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

        $a = Activity::whereNotNull('guest_offer')->findOrFail($d['activity_id']);
        abort_if($a->cancelled_at || $a->starts_at->isPast(), 422, 'Aktiviteten er ikke længere åben.');
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
            $offer = self::OFFERS[$a->guest_offer];
            $inv = Invoice::create([
                'payer_id' => $guest->id, 'member_id' => $guest->id, 'kind' => 'guest',
                'description' => $offer['label'].': '.$a->title.' '.$a->starts_at->format('d.m.Y').' – '.$guest->name,
                'amount' => Prices::get($offer['price']), 'due_on' => now(), 'activity_id' => $a->id,
                'account_id' => FinanceController::accountId('1100'), 'payload' => ['offer' => $a->guest_offer, 'creditable' => true],
            ]);
            $reg->fill(['status' => 'pending_payment', 'invoice_id' => $inv->id]);
            $reg->updated_at = now();
            $reg->save();
            $reg->log('reserved', $guest);
            Audit::log('guest.booked', $guest, ['activity' => $a->id, 'offer' => $a->guest_offer]);

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
