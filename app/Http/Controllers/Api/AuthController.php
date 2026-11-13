<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Household;
use App\Models\User;
use App\Notifications\ClubMessage;
use App\Support\Audit;
use App\Support\Memberships;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $r)
    {
        $data = $r->validate(['email' => 'required|email', 'password' => 'required']);
        $user = User::where('email', $data['email'])->first();
        if (! $user || ! $user->password || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'Forkert e-mail eller adgangskode.']);
        }
        if ($user->status === 'resigned') {
            throw ValidationException::withMessages(['email' => 'Medlemskabet er afsluttet.']);
        }

        return ['token' => $user->createToken('app')->plainTextToken, 'user' => self::me($user)];
    }

    /** Kun i demo-tilstand: hurtig-login til bestyrelsens afprøvning. */
    public function demoAccounts()
    {
        abort_unless(config('app.demo'), 404);

        return collect(config('app.demo_accounts'))->map(function ($label, $email) {
            $u = User::where('email', $email)->first();

            return $u ? ['email' => $email, 'name' => $u->name, 'label' => $label] : null;
        })->filter()->values();
    }

    public function demoLogin(Request $r)
    {
        abort_unless(config('app.demo'), 404);
        abort_unless(array_key_exists($r->input('email'), config('app.demo_accounts')), 403);
        $user = User::where('email', $r->input('email'))->firstOrFail();

        return ['token' => $user->createToken('demo')->plainTextToken, 'user' => self::me($user)];
    }

    public function register(Request $r)
    {
        $data = $r->validate([
            'name' => 'required|string|max:120',
            'email' => ['required', 'email', \Illuminate\Validation\Rule::unique('users', 'email')->where(fn ($q) => $q->where('status', '!=', 'guest'))],
            'password' => 'required|min:8',
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:200',
            'postal_code' => 'nullable|string|max:10',
            'city' => 'nullable|string|max:100',
            'birthdate' => 'required|date|before:today',
            'consent' => 'accepted',
            'package_id' => ['required', \Illuminate\Validation\Rule::exists('packages', 'id')->where('kind', 'membership')->whereNull('archived_at')->where('self_service', true)],
            'new_members' => 'array|max:10',
            'new_members.*.name' => 'required|string|max:120',
            'new_members.*.birthdate' => 'required|date|before:today',
        ], ['consent.accepted' => 'Du skal give samtykke for at kunne melde dig ind.', 'package_id.required' => 'Vælg et medlemskab.'],
           ['new_members.*.name' => 'navn', 'new_members.*.birthdate' => 'fødselsdato']);

        $package = \App\Models\Package::with('slots.category')->where('kind', 'membership')->whereNull('archived_at')->find($data['package_id']);
        abort_unless($package, 422, 'Vælg et medlemskab.');
        $new = collect($data['new_members'] ?? [])->map(fn ($p) => ['name' => $p['name'], 'birthdate' => \Illuminate\Support\Carbon::parse($p['birthdate'])->toDateString(), 'consent_at' => now()->toIso8601String()])->all();
        // Tjek sammensætningen, før der oprettes noget
        if ($err = $package->compositionError(array_merge([['name' => $data['name'], 'birthdate' => $data['birthdate']]], $new))) abort(422, $err);
        unset($data['consent'], $data['package_id'], $data['new_members']);
        // Medlemmet er "afventer betaling", indtil kontingentet er betalt (se Memberships::onPaid)
        // Har personen tidligere købt prøvetræning/SUP-kursus som gæst, bliver gæsteprofilen til medlemsprofilen (og beløbet modregnes)
        $user = User::where('email', $data['email'])->where('status', 'guest')->first();
        if ($user) {
            $user->update($data + ['status' => 'pending', 'consent_at' => now()]);
            Audit::log('guest.converted', $user);
        } else {
            $user = User::create($data + ['status' => 'pending', 'consent_at' => now()]);
        }
        if (! $user->household_id) {
            $user->update(['household_id' => Household::create(['name' => 'Husstand '.$user->name, 'payer_id' => $user->id])->id]);
        }
        Audit::log('member.registered', $user);
        $purchase = \App\Support\Packages::buy($user->fresh(), $package, [$user->id], $new);
        $invoice = \App\Support\Packages::applyGuestCredit($purchase->invoice, $user);
        \App\Support\Packages::settleIfFree($invoice);
        $invoice->refresh();

        $user->tokens()->where('name', 'guest')->delete();

        return ['token' => $user->createToken('app')->plainTextToken, 'user' => self::me($user->fresh()), 'invoice_id' => $invoice->id,
            'paid' => $invoice->status === 'paid', 'credit' => $invoice->payload['guest_credit'] ?? 0];
    }

    public function logout(Request $r)
    {
        $r->user()->currentAccessToken()?->delete();

        return ['ok' => true];
    }

    public function show(Request $r)
    {
        return self::me($r->user());
    }

    public static function me(User $u): array
    {
        $u->loadMissing('roleAssignments', 'userPermissions', 'household', 'membershipType');

        return [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'status' => $u->status,
            'roles' => $u->roles(),
            'unread' => $u->unreadNotifications()->count(),
            'unread_messages' => $u->canUseMessages() ? $u->unreadMessages() : 0,
            'can_message' => (bool) $u->canUseMessages(),
            'is_payer' => $u->household?->payer_id === $u->id,
            'season' => \App\Support\Season::label(),
            'can' => ($can = \App\Support\Permissions::flags($u)) + [
                // Indstillinger vises, hvis man må mindst én af tingene dér
                'settings' => $can['categories_manage'] || $can['packages_manage'] || $can['prices_edit'] || $can['roles_manage'] || $can['audit_view'],
                // Trænerportalen: egen utilgængelighed (trænere) og/eller oversigten over alle trænere
                'portal' => $can['trainers_portal'] || $can['activities_take'],
                // Udstyrssiden: vedligehold, underkategorier eller booking (også medlemmer med udstyrsleje)
                'equipment' => $can['equipment_manage'] || $can['equipment_types'] || $can['equipment_book'] || \App\Support\Equipment::hasRental($u)
                    || \App\Models\EquipmentBooking::where('user_id', $u->id)->whereIn('status', ['confirmed', 'requested'])->where('ends_at', '>', now())->exists() // egne bookinger
                    || \App\Support\Equipment::unpaidRentalInvoiceIds($u), // købt leje, ikke betalt endnu
                'equipment_book_any' => $can['equipment_book'] || \App\Support\Equipment::hasRental($u),
            ],
        ];
    }
}
