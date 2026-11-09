<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\User;
use App\Support\Audit;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    public const FIELDS = ['name', 'email', 'phone', 'address', 'postal_code', 'city', 'birthdate', 'emergency_name', 'emergency_phone'];

    public function show(Request $r)
    {
        $u = $r->user()->load('household', 'membershipType', 'roleAssignments');

        return [
            'profile' => self::profile($u),
            'household' => $u->managedMembers()->map(fn ($m) => self::profile($m))->values(),
            'membership_valid' => $u->hasValidMembership(),
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
            'membership_type' => $u->membershipType?->name,
            'status_label' => User::STATUS_LABELS[$u->status] ?? $u->status,
        ] + $u->only(['id', ...self::FIELDS, 'status', 'notify_email', 'notify_push']);
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
        ]);
        $before = $u->only(array_keys($data));
        $u->update($data);
        Audit::log('member.updated', $u, ['before' => $before, 'after' => $u->only(array_keys($data))]);

        return self::profile($u->fresh());
    }

    /** Forælder tilføjer barn/partner til husstanden. */
    public function addMember(Request $r)
    {
        $u = $r->user();
        abort_unless($u->household?->payer_id === $u->id, 403, 'Kun husstandens betaler kan tilføje medlemmer.');
        $data = $r->validate([
            'name' => 'required|string|max:120',
            'birthdate' => 'required|date|before:today',
            'email' => 'nullable|email|unique:users,email',
            'emergency_name' => 'nullable|string|max:120',
            'emergency_phone' => 'nullable|string|max:30',
        ]);
        $m = User::create($data + [
            'household_id' => $u->household_id, 'status' => 'pending', 'consent_at' => now(),
            'address' => $u->address, 'postal_code' => $u->postal_code, 'city' => $u->city,
            'notify_email' => false,
        ]);
        Audit::log('member.added_to_household', $m, ['by_parent' => $u->id]);

        return self::profile($m);
    }

    public function categories(Request $r)
    {
        $settings = $r->user()->categorySettings->keyBy('id');

        return Category::orderBy('name')->get()->map(fn ($c) => [
            'id' => $c->id, 'name' => $c->name, 'color' => $c->color,
            'show' => (bool) ($settings[$c->id]->pivot->show ?? true),
            'notify' => (bool) ($settings[$c->id]->pivot->notify ?? true),
        ]);
    }

    public function saveCategories(Request $r)
    {
        $data = $r->validate(['items' => 'array', 'items.*.id' => 'exists:categories,id', 'items.*.show' => 'boolean', 'items.*.notify' => 'boolean']);
        $sync = collect($data['items'])->mapWithKeys(fn ($i) => [$i['id'] => ['show' => $i['show'], 'notify' => $i['notify']]]);
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
            'samtykke_givet' => $u->consent_at,
        ], 200, ['Content-Disposition' => 'attachment; filename="mine-data.json"'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function products(Request $r)
    {
        $u = $r->user();
        $mine = ProductOrder::with('product', 'resource', 'invoice')->whereIn('user_id', $u->managedMembers()->pluck('id'))->get();

        return [
            'catalog' => Product::where('active', true)->with('resources')->get()->map(function ($p) {
                $taken = ProductOrder::where('product_id', $p->id)->whereNotNull('resource_id')
                    ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', now()))->pluck('resource_id');

                return [
                    'id' => $p->id, 'name' => $p->name, 'description' => $p->description, 'price' => $p->price,
                    'price_fmt' => Money::fmt($p->price), 'billing' => $p->billing, 'needs_resource' => $p->needs_resource,
                    'free_resources' => $p->resources->whereNotIn('id', $taken)->map->only('id', 'label')->values(),
                ];
            }),
            'mine' => $mine->map(fn ($o) => [
                'id' => $o->id, 'product' => $o->product->name, 'resource' => $o->resource?->label,
                'member' => $o->user->name, 'valid_to' => $o->valid_to?->toDateString(),
                'invoice_status' => $o->invoice?->displayStatus(), 'invoice_id' => $o->invoice_id,
            ]),
        ];
    }

    public function order(Request $r, Product $product)
    {
        $u = $r->user();
        $data = $r->validate(['member_id' => 'required|integer', 'resource_id' => 'nullable|integer']);
        abort_unless($u->managedMembers()->pluck('id')->contains($data['member_id']), 403);

        return DB::transaction(function () use ($u, $product, $data) {
            $resourceId = null;
            if ($product->needs_resource) {
                abort_unless($data['resource_id'] ?? null, 422, 'Vælg en plads.');
                $res = $product->resources()->lockForUpdate()->findOrFail($data['resource_id']);
                $busy = ProductOrder::where('resource_id', $res->id)->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', now()))->exists();
                abort_if($busy, 422, 'Pladsen er desværre lige blevet optaget.');
                $resourceId = $res->id;
            }
            $member = User::find($data['member_id']);
            $invoice = Invoice::create([
                'payer_id' => $u->id, 'member_id' => $member->id, 'kind' => 'product',
                'description' => $product->name.($resourceId ? ' ('.$product->resources->find($resourceId)->label.')' : '').' – '.$member->name,
                'amount' => $product->price, 'due_on' => now()->addDays(14), 'period' => (string) now()->year,
                'product_id' => $product->id, 'account_id' => FinanceController::accountId($product->needs_resource ? '1300' : '1200'),
            ]);
            $order = ProductOrder::create([
                'user_id' => $member->id, 'product_id' => $product->id, 'resource_id' => $resourceId, 'invoice_id' => $invoice->id,
                'valid_from' => now(), 'valid_to' => $product->billing === 'once' ? null : now()->endOfYear(),
            ]);
            Audit::log('product.ordered', $order);

            return ['invoice_id' => $invoice->id];
        });
    }
}
