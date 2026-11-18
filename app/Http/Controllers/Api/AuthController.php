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
        // "login" er e-mail eller telefonnummer (ældre app-versioner sender "email")
        $data = $r->validate(['login' => 'required_without:email|nullable|string|max:190', 'email' => 'nullable|string|max:190', 'password' => 'required'],
            ['login.required_without' => 'Skriv din e-mail eller dit telefonnummer.']);
        $id = trim($data['login'] ?? $data['email']);
        $user = str_contains($id, '@') ? User::where('email', $id)->first()
            : (($n = \App\Support\Phone::normalize($id)) ? User::where('login_phone', $n)->first() : null);
        if (! $user || ! $user->password || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['login' => 'Forkert e-mail/telefonnummer eller adgangskode.']);
        }
        if ($user->status === 'resigned') {
            throw ValidationException::withMessages(['login' => 'Medlemskabet er afsluttet.']);
        }

        return ['token' => $user->createToken('app')->plainTextToken, 'user' => self::me($user)];
    }

    /**
     * Kun i demo-tilstand og kun i appen: hurtig-login til bestyrelsens afprøvning.
     * Webudgaven er offentlig (hjemmesiden ligger på samme adresse), så her er hurtig-login slået fra.
     */
    private static function demoAllowed(Request $r): bool
    {
        return config('app.demo') && in_array($r->header('Origin'), ['capacitor://localhost', 'https://localhost', 'http://localhost'], true);
    }

    /** Demokontoernes adgangskode (demo1234) må ikke ændres, ellers kan andre ikke prøve systemet. */
    public static function isDemoAccount(?User $u): bool
    {
        return $u && config('app.demo') && array_key_exists($u->email, config('app.demo_accounts') ?? []);
    }

    /**
     * Glemt adgangskode: sender et link på mail. Svaret er det samme, uanset om e-mailen findes,
     * så man ikke kan bruge siden til at finde ud af, hvem der er medlem.
     */
    public function forgot(Request $r)
    {
        $data = $r->validate(['email' => 'required|email|max:255']);
        $user = User::where('email', $data['email'])->first();
        if ($user && $user->status !== 'resigned' && ! self::isDemoAccount($user)) {
            \Illuminate\Support\Facades\Password::broker()->sendResetLink(['email' => $user->email]);
        }

        return ['message' => 'Hvis der findes en konto med den e-mail, har vi sendt et link til at vælge en ny adgangskode. Linket virker i '
            .config('auth.passwords.users.expire', 60).' minutter. Kig også i spam-mappen.'];
    }

    /** Ny adgangskode via linket fra mailen. Logger ind bagefter og logger alle andre enheder ud. */
    public function reset(Request $r)
    {
        $data = $r->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:8|max:200|confirmed',
        ], ['password.min' => 'Adgangskoden skal være på mindst 8 tegn.', 'password.confirmed' => 'De to adgangskoder er ikke ens.']);

        $done = null;
        $status = \Illuminate\Support\Facades\Password::broker()->reset($data, function (User $user, string $password) use (&$done) {
            abort_if(self::isDemoAccount($user), 403, 'Demokontoens adgangskode kan ikke ændres.');
            $user->forceFill(['password' => Hash::make($password), 'must_change_password' => false])->save();
            $user->tokens()->delete();
            Audit::log('member.password_reset', $user);
            $done = $user;
        });

        if ($status !== \Illuminate\Support\Facades\Password::PASSWORD_RESET || ! $done) {
            throw ValidationException::withMessages(['token' => 'Linket er ugyldigt eller udløbet. Bed om et nyt link på login-siden.']);
        }

        return ['token' => $done->createToken('web')->plainTextToken, 'user' => self::me($done->fresh())];
    }

    public function demoAccounts(Request $r)
    {
        abort_unless(self::demoAllowed($r), 404);

        return collect(config('app.demo_accounts'))->map(function ($label, $email) {
            $u = User::where('email', $email)->first();

            return $u ? ['email' => $email, 'name' => $u->name, 'label' => $label] : null;
        })->filter()->values();
    }

    public function demoLogin(Request $r)
    {
        abort_unless(self::demoAllowed($r), 404);
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
            'phone' => ['nullable', 'string', 'max:30', \App\Support\Phone::rule(User::where('email', $r->input('email'))->where('status', 'guest')->value('id'))],
            'address' => 'nullable|string|max:200',
            'postal_code' => 'nullable|string|max:10',
            'city' => 'nullable|string|max:100',
            'birthdate' => 'required|date|before:today',
            'consent' => 'accepted',
            'package_id' => ['required', \Illuminate\Validation\Rule::exists('packages', 'id')->where('kind', 'membership')->whereNull('archived_at')->where('self_service', true)],
            'new_members' => 'array|max:10',
            'new_members.*.name' => 'required|string|max:120',
            'new_members.*.birthdate' => 'required|date|before:today',
            'new_members.*.email' => 'nullable|email|distinct|different:email|unique:users,email',
            'new_members.*.phone' => ['nullable', 'string', 'max:30', 'distinct', \App\Support\Phone::rule()],
        ], ['consent.accepted' => 'Du skal give samtykke for at kunne melde dig ind.', 'package_id.required' => 'Vælg et medlemskab.'],
           ['new_members.*.name' => 'navn', 'new_members.*.birthdate' => 'fødselsdato', 'new_members.*.email' => 'e-mail', 'new_members.*.phone' => 'telefonnummer']);

        $package = \App\Models\Package::with('slots.category')->where('kind', 'membership')->whereNull('archived_at')->find($data['package_id']);
        abort_unless($package, 422, 'Vælg et medlemskab.');
        $new = collect($data['new_members'] ?? [])->map(fn ($p) => ['name' => $p['name'], 'birthdate' => \Illuminate\Support\Carbon::parse($p['birthdate'])->toDateString(),
            'email' => $p['email'] ?? null, 'phone' => $p['phone'] ?? null, 'consent_at' => now()->toIso8601String()])->all();
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
            'unread_posts' => $u->unreadPosts(),
            'is_payer' => $u->household?->payer_id === $u->id,
            'must_change_password' => (bool) $u->must_change_password,
            'season' => \App\Support\Season::label(),
            'can' => ($can = \App\Support\Permissions::flags($u)) + [
                // Menupunkterne System og Medlemskaber og tilkøb
                'system' => $can['categories_manage'] || $can['roles_manage'] || $can['audit_view'] || $can['design_manage'],
                'memberships' => $can['packages_manage'] || $can['prices_edit'],
                // Den gamle samlede Indstillinger-side (bruges af ældre app-versioner)
                'settings' => $can['categories_manage'] || $can['packages_manage'] || $can['prices_edit'] || $can['roles_manage'] || $can['audit_view'] || $can['design_manage'] || $can['site_edit'],
                // Trænerportalen: egen utilgængelighed (trænere) og/eller oversigten over alle trænere
                'portal' => $can['trainers_portal'] || $can['activities_take'],
                // Udstyrssiden: vedligehold, underkategorier eller booking (også almindelige medlemmer, hvis admin tillader det)
                'equipment' => $can['equipment_manage'] || $can['equipment_types'] || $can['equipment_book'] || \App\Support\Equipment::memberCanBook($u)
                    || \App\Models\EquipmentBooking::where('user_id', $u->id)->whereIn('status', ['confirmed', 'requested'])->where('ends_at', '>', now())->exists() // egne bookinger
                    || \App\Support\Equipment::unpaidRentalInvoiceIds($u), // købt leje, ikke betalt endnu
                'equipment_book_any' => $can['equipment_book'] || \App\Support\Equipment::memberCanBook($u),
            ],
        ];
    }
}
