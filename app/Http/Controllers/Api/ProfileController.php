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
        ] + $u->only(['id', ...self::FIELDS, 'status', 'notify_email', 'notify_push', 'allow_messages']);
    }

    public function update(Request $r)
    {
        $u = $r->user();
        $data = $r->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|unique:users,email,'.$u->id,
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:200',
            'postal_code' => 'nullable|string|max:10',
            'city' => 'nullable|string|max:100',
            'birthdate' => 'nullable|date',
            'emergency_name' => 'nullable|string|max:120',
            'emergency_phone' => 'nullable|string|max:30',
            'notify_email' => 'boolean',
            'notify_push' => 'boolean',
            'allow_messages' => 'boolean',
        ]);
        $before = $u->only(array_keys($data));
        $u->update($data);
        Audit::log('member.updated', $u, ['before' => $before, 'after' => $u->only(array_keys($data))]);

        return self::profile($u->fresh());
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
}
