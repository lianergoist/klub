<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\ExpenseClaim;
use App\Models\Invoice;
use App\Models\User;
use App\Support\Audit;
use App\Support\Memberships;
use App\Support\Money;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public const FIELDS = ['name', 'email', 'phone', 'address', 'postal_code', 'city', 'birthdate', 'emergency_name', 'emergency_phone'];

    public function show(Request $r)
    {
        $u = $r->user()->load('household', 'roleAssignments');

        return [
            'profile' => self::profile($u),
            'household' => $u->managedMembers()->map(fn ($m) => self::profile($m))->values(),
            'membership_valid' => $u->hasValidMembership(),
            // Til statuslinjerne i profilmenuen på mobil
            'counts' => [
                'open_invoices' => Invoice::where('payer_id', $u->id)->whereIn('status', ['open', 'overdue'])->count(),
                'pending_claims' => ExpenseClaim::where('user_id', $u->id)->where('status', 'submitted')->count(),
            ],
            'membership' => ($c = $u->currentMembership()) ? ['name' => $c->package->name, 'valid_to' => $c->valid_to?->toDateString()] : null,
            'upcoming' => ($n = $u->upcomingMembership()) ? ['name' => $n->package->name, 'valid_from' => $n->valid_from->toDateString()] : null,
            'signup_invoice_id' => $u->status === 'pending' ? Invoice::where('member_id', $u->id)->where('kind', 'membership')->where('status', 'open')->value('id') : null,
            'roles' => $u->roleAssignments->sortByDesc('starts_on')->map(fn ($ra) => [
                'role' => $ra->role, 'label' => User::ROLE_LABELS[$ra->role] ?? $ra->role,
                'starts_on' => $ra->starts_on->toDateString(), 'ends_on' => $ra->ends_on?->toDateString(),
            ])->values(),
        ];
    }

    public static function profile(User $u): array
    {
        return [
            'birthdate' => $u->birthdate?->toDateString(),
            'age' => $u->age(),
            'joined_on' => $u->joined_on?->toDateString(),
            'membership_type' => $u->currentMembership()?->package->name,
            'status_label' => User::STATUS_LABELS[$u->status] ?? $u->status,
        ] + $u->only(['id', ...self::FIELDS, 'status', 'notify_email', 'notify_push', 'notify_posts', 'allow_messages']);
    }

    public function update(Request $r)
    {
        $u = $r->user();
        $data = $r->validate([
            'name' => 'required|string|max:120',
            // Betaleren skal have e-mail (regninger, glemt adgangskode); andre i husstanden logger ind med e-mail eller telefon
            'email' => [$u->payer()->id === $u->id ? 'required' : 'required_without:phone', 'nullable', 'email', 'unique:users,email,'.$u->id],
            'phone' => ['nullable', 'string', 'max:30', \App\Support\Phone::rule($u->id)],
            'address' => 'nullable|string|max:200',
            'postal_code' => 'nullable|string|max:10',
            'city' => 'nullable|string|max:100',
            'birthdate' => 'nullable|date',
            'emergency_name' => 'nullable|string|max:120',
            'emergency_phone' => 'nullable|string|max:30',
            'notify_email' => 'boolean',
            'notify_push' => 'boolean',
            'notify_posts' => 'boolean',
            'allow_messages' => 'boolean',
        ], ['email.required' => 'Som betaler for husstanden skal du have en e-mail (regninger og glemt adgangskode).',
            'email.required_without' => 'Skriv din e-mail eller dit telefonnummer – du logger ind med det.']);
        $before = $u->only(array_keys($data));
        $u->update($data);
        Audit::log('member.updated', $u, ['before' => $before, 'after' => $u->only(array_keys($data))]);

        return self::profile($u->fresh());
    }

    /* ---------- Eget login til personer i husstanden (betaleren) ---------- */

    private static function householdMember(Request $r, User $member): User
    {
        $u = $r->user();
        abort_unless($u->household_id && $u->household?->payer_id === $u->id && $member->household_id === $u->household_id && $member->id !== $u->id,
            403, 'Kun husstandens betaler kan give personer i husstanden eget login.');
        abort_if(in_array($member->status, ['resigned', 'guest']), 422, $member->name.' er ikke aktivt medlem.');

        return $member;
    }

    /** E-mail og telefonnummer, som personen selv logger ind med (skal være personens egne – ikke forælderens). */
    public function householdLogin(Request $r, User $member)
    {
        $m = self::householdMember($r, $member);
        $d = $r->validate([
            'email' => ['nullable', 'email', 'unique:users,email,'.$m->id],
            'phone' => ['nullable', 'string', 'max:30', \App\Support\Phone::rule($m->id)],
        ], ['email.unique' => 'E-mailen bruges allerede af et andet medlem. Barnet skal have sin egen.']);
        $before = $m->only('email', 'phone');
        $m->update($d + ['notify_email' => (bool) ($d['email'] ?? null)]);
        Audit::log('member.updated', $m, ['before' => $before, 'after' => $m->only('email', 'phone'), 'by' => $r->user()->name]);

        return ['ok' => true, 'can_login' => (bool) \App\Support\TempPassword::loginOf($m->fresh())];
    }

    /** Midlertidig adgangskode til en person i husstanden; vises én gang, personen vælger sin egen ved første login. */
    public function householdPassword(Request $r, User $member)
    {
        $m = self::householdMember($r, $member);
        $login = \App\Support\TempPassword::loginOf($m);
        abort_unless($login, 422, 'Giv først '.$m->name.' sin egen e-mail eller sit eget telefonnummer.');
        abort_if(AuthController::isDemoAccount($m), 403, 'Demokontoens adgangskode kan ikke ændres.');
        $code = \App\Support\TempPassword::make($m);
        Audit::log('member.temp_password', $m, ['by' => $r->user()->name]);

        return ['password' => $code, 'login' => $login];
    }

    public function categories(Request $r)
    {
        $settings = $r->user()->categorySettings->keyBy('id');

        return Category::whereNull('archived_at')->orderBy('name')->get()->map(fn ($c) => [
            'id' => $c->id, 'name' => $c->name, 'color' => $c->color,
            'show' => (bool) ($settings[$c->id]->pivot->show ?? true),
            'mandatory' => (bool) $c->mandatory,
            'notify' => $c->mandatory || (bool) ($settings[$c->id]->pivot->notify ?? true),
        ]);
    }

    public function saveCategories(Request $r)
    {
        $data = $r->validate(['items' => 'array', 'items.*.id' => 'exists:categories,id', 'items.*.show' => 'boolean', 'items.*.notify' => 'boolean']);
        $mandatory = Category::where('mandatory', true)->pluck('id')->all();
        $sync = collect($data['items'])->mapWithKeys(fn ($i) => [$i['id'] => ['show' => $i['show'], 'notify' => $i['notify'] || in_array($i['id'], $mandatory)]]);
        $r->user()->categorySettings()->sync($sync);

        return $this->categories($r->merge([]));
    }

    public function invoices(Request $r)
    {
        $u = $r->user();

        return Invoice::with('member', 'payments')->where('payer_id', $u->id)->orderByDesc('created_at')->get()
            ->map(fn ($i) => FinanceController::invoiceRow($i));
    }

    public function notifications(Request $r)
    {
        $list = $r->user()->notifications()->limit(50)->get()->map(fn ($n) => $n->data + [
            'id' => $n->id, 'read' => (bool) $n->read_at, 'created_at' => $n->created_at->toIso8601String(),
        ]);
        // Notifikationer om aflyste aktiviteter: skjules hvis aktiviteten er arkiveret (aflyst uden tilmeldte), ellers markeres de som aflyst
        $actId = fn ($n) => preg_match('#^/aktiviteter/(\d+)$#', $n['link'] ?? '', $m) ? (int) $m[1] : null;
        $acts = \App\Models\Activity::whereIn('id', $list->map($actId)->filter()->unique())->whereNotNull('cancelled_at')
            ->get(['id', 'archived_at'])->keyBy('id');
        $list = $list->reject(fn ($n) => ($a = $acts[$actId($n)] ?? null) && $a->archived_at)
            ->map(fn ($n) => $n + ['cancelled' => isset($acts[$actId($n)]) && ! str_starts_with($n['title'] ?? '', 'Aflyst')])->values();
        $r->user()->unreadNotifications->markAsRead();

        return $list;
    }

    /** GDPR: medlemmet kan hente sine egne data. */
    public function export(Request $r)
    {
        $u = $r->user();
        Audit::log('member.data_exported', $u);

        return response()->json([
            'eksporteret' => now()->toIso8601String(),
            'profil' => self::profile($u),
            'husstand' => $u->managedMembers()->map(fn ($m) => self::profile($m)),
            'roller' => $u->roleAssignments,
            'tilmeldinger' => $u->registrations()->with('activity:id,title,starts_at', 'events')->get(),
            'regninger' => Invoice::where('payer_id', $u->id)->with('payments')->get(),
            'køb' => \App\Models\Purchase::with('package:id,name')->where('payer_id', $u->id)->get(),
            'samtykke_givet' => $u->consent_at,
        ], 200, ['Content-Disposition' => 'attachment; filename="mine-data.json"'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /** Skift adgangskode (kræver den nuværende). Andre enheder logges ud; denne forbliver logget ind. */
    public function password(Request $r)
    {
        $u = $r->user();
        abort_if(\App\Http\Controllers\Api\AuthController::isDemoAccount($u), 403, 'Demokontoens adgangskode kan ikke ændres, så andre stadig kan prøve systemet.');
        $data = $r->validate([
            // Med en midlertidig kode fra administrator er man lige logget ind med den, så den skal ikke gentages
            'current_password' => $u->must_change_password ? 'nullable|string' : 'required|string',
            'password' => 'required|string|min:8|max:200|confirmed'.($u->must_change_password ? '' : '|different:current_password'),
        ], [
            'current_password.required' => 'Skriv din nuværende adgangskode.',
            'password.min' => 'Den nye adgangskode skal være på mindst 8 tegn.',
            'password.confirmed' => 'De to nye adgangskoder er ikke ens.',
            'password.different' => 'Den nye adgangskode skal være en anden end den nuværende.',
        ]);
        if (! $u->must_change_password && (! $u->password || ! \Illuminate\Support\Facades\Hash::check($data['current_password'], $u->password))) {
            throw \Illuminate\Validation\ValidationException::withMessages(['current_password' => 'Den nuværende adgangskode er forkert.']);
        }

        abort_if($u->must_change_password && \Illuminate\Support\Facades\Hash::check($data['password'], $u->password), 422, 'Vælg en anden adgangskode end den midlertidige.');
        $u->forceFill(['password' => \Illuminate\Support\Facades\Hash::make($data['password']), 'must_change_password' => false])->save();
        $current = $u->currentAccessToken();
        $u->tokens()->when($current && isset($current->id), fn ($q) => $q->where('id', '!=', $current->id))->delete();
        Audit::log('member.password_changed', $u);

        return ['message' => 'Din adgangskode er skiftet. Andre enheder, du var logget ind på, er logget ud.'];
    }
}
