<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\User;
use App\Support\Audit;
use App\Support\Memberships;
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
            'pending_upgrade' => ($inv = Memberships::openUpgrade($u)) ? [
                'invoice_id' => $inv->id, 'amount_fmt' => Money::fmt($inv->amount),
                'members' => array_column($inv->payload['members'] ?? [], 'name'),
            ] : null,
            'season' => \App\Support\Season::label(),
            'household_count' => Memberships::householdCount($u),
            'add_quote_fmt' => $u->household?->payer_id === $u->id && $u->status !== 'pending' ? Money::fmt(Memberships::addQuote($u, 1 + count((Memberships::openUpgrade($u)?->payload['members'] ?? [])))) : null,
            'add_quote' => $u->household?->payer_id === $u->id && $u->status !== 'pending' ? Memberships::addQuote($u, 1 + count((Memberships::openUpgrade($u)?->payload['members'] ?? []))) : null,
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
            'membership_type' => $u->membershipType?->name,
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

    /**
     * Betaleren tilføjer barn/partner til husstanden.
     * Er husstanden allerede på familiekontingent, tilføjes personen med det samme.
     * Ellers oprettes en opgraderingsregning; personen oprettes først, når den er betalt.
     */
    public function addMember(Request $r)
    {
        $u = $r->user();
        abort_unless($u->household?->payer_id === $u->id, 403, 'Kun husstandens betaler kan tilføje medlemmer.');
        abort_if($u->status === 'pending', 422, 'Betal først dit eget kontingent – derefter kan du tilføje familiemedlemmer.');
        $data = $r->validate([
            'name' => 'required|string|max:120',
            'birthdate' => 'required|date|before:today',
            'email' => 'nullable|email|unique:users,email',
            'emergency_name' => 'nullable|string|max:120',
            'emergency_phone' => 'nullable|string|max:30',
        ]);
        abort_if(Invoice::where('payer_id', $u->id)->where('kind', 'membership')->where('status', 'open')->whereNull('payload')->exists(), 422,
            'Du har en ubetalt kontingentregning. Betal den først under Regninger.');

        $data['consent_at'] = now()->toIso8601String();

        // Koster det ikke ekstra (fx 5. person på familiekontingent), tilføjes personen med det samme
        if (! Memberships::openUpgrade($u) && $u->hasValidMembership() && Memberships::addQuote($u, 1) === 0) {
            $m = Memberships::addToHousehold($u, [$data])[0];

            return ['added' => true, 'member' => self::profile($m)];
        }

        $inv = Memberships::requestFamilyUpgrade($u, $data);
        Audit::log('household.upgrade_requested', $u, ['invoice_id' => $inv->id, 'name' => $data['name']]);

        return ['added' => false, 'invoice_id' => $inv->id];
    }

    /** Fortryd en ikke-betalt familieopgradering. Ingen personer er oprettet endnu. */
    public function cancelUpgrade(Request $r)
    {
        $inv = Memberships::openUpgrade($r->user());
        abort_unless($inv, 404);
        $inv->update(['status' => 'cancelled']);
        Audit::log('household.upgrade_cancelled', $r->user(), ['invoice_id' => $inv->id]);

        return ['ok' => true];
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

    /** Aktuel pris for et produkt (fra pristabellen, hvis produktet har en priskode). */
    public static function productPrice(Product $p, int $persons = 1): int
    {
        if ($p->pricing === 'household') return \App\Support\Prices::rental($persons);

        return $p->price_code ? \App\Support\Prices::get($p->price_code) : $p->price;
    }

    public function products(Request $r)
    {
        $u = $r->user();
        $managed = $u->managedMembers();
        $mine = ProductOrder::with('product', 'resource', 'invoice', 'user')->whereIn('user_id', $managed->pluck('id'))
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', now()))->get();

        return [
            'season' => \App\Support\Season::label(),
            'season_end' => \App\Support\Season::end()->toDateString(),
            'catalog' => Product::where('active', true)->with('resources')->get()->map(function ($p) use ($managed) {
                $taken = ProductOrder::where('product_id', $p->id)->whereNotNull('resource_id')
                    ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', now()))->pluck('resource_id');
                $price = self::productPrice($p);

                return [
                    'id' => $p->id, 'name' => $p->name, 'description' => $p->description, 'price' => $price,
                    'price_fmt' => ($p->pricing === 'household' ? 'fra ' : '').Money::fmt($price), 'billing' => $p->billing,
                    'needs_resource' => $p->needs_resource, 'household' => $p->pricing === 'household',
                    // pris for 1, 2, 3 … personer i husstanden (udstyrsleje)
                    'tiers' => $p->pricing === 'household' ? collect(range(1, max(1, $managed->count())))->mapWithKeys(fn ($n) => [$n => \App\Support\Prices::rental($n)]) : null,
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

    /** Bestil et tilkøb for én eller flere i husstanden (udstyrsleje prissættes samlet for husstanden). */
    public function order(Request $r, Product $product)
    {
        $u = $r->user();
        $data = $r->validate(['member_ids' => 'required_without:member_id|array|min:1', 'member_ids.*' => 'integer', 'member_id' => 'nullable|integer', 'resource_id' => 'nullable|integer']);
        $ids = array_values(array_unique($data['member_ids'] ?? [$data['member_id']]));
        $allowed = $u->managedMembers()->pluck('id');
        abort_if(collect($ids)->diff($allowed)->isNotEmpty(), 403);
        abort_if(($product->needs_resource || $product->pricing !== 'household') && count($ids) > 1, 422, 'Vælg én person.');
        if ($product->billing === 'season') {
            $has = ProductOrder::where('product_id', $product->id)->whereIn('user_id', $ids)->where('valid_to', '>=', now())->with('user')->get();
            if ($has->isNotEmpty()) abort(422, $has->first()->user->name.' har allerede '.mb_strtolower($product->name).' for sæson '.\App\Support\Season::label().'.');
        }

        return DB::transaction(function () use ($u, $product, $data, $ids) {
            $resourceId = null;
            if ($product->needs_resource) {
                abort_unless($data['resource_id'] ?? null, 422, 'Vælg en plads.');
                $res = $product->resources()->lockForUpdate()->findOrFail($data['resource_id']);
                $busy = ProductOrder::where('resource_id', $res->id)->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', now()))->exists();
                abort_if($busy, 422, 'Pladsen er desværre lige blevet optaget.');
                $resourceId = $res->id;
            }
            $members = User::whereIn('id', $ids)->get();
            $amount = self::productPrice($product, count($ids));
            $validTo = match ($product->billing) { 'once' => null, 'season' => \App\Support\Season::end(), default => now()->endOfYear() };
            $who = $members->count() === 1 ? $members->first()->name : $members->count().' personer ('.$members->map(fn ($m) => explode(' ', $m->name)[0])->join(', ', ' og ').')';
            $invoice = Invoice::create([
                'payer_id' => $u->id, 'member_id' => $members->count() === 1 ? $members->first()->id : null, 'kind' => 'product',
                'description' => $product->name.($resourceId ? ' ('.$product->resources->find($resourceId)->label.')' : '').($product->billing === 'season' ? ' '.\App\Support\Season::label() : '').' – '.$who,
                'amount' => $amount, 'due_on' => now()->addDays(14), 'period' => $product->billing === 'season' ? \App\Support\Season::label() : (string) now()->year,
                'product_id' => $product->id, 'account_id' => FinanceController::accountId($product->needs_resource ? '1300' : '1200'),
            ]);
            foreach ($members as $m) {
                $order = ProductOrder::create([
                    'user_id' => $m->id, 'product_id' => $product->id, 'resource_id' => $resourceId, 'invoice_id' => $invoice->id,
                    'valid_from' => now(), 'valid_to' => $validTo,
                ]);
            }
            Audit::log('product.ordered', $order, ['persons' => count($ids), 'amount' => $amount]);

            return ['invoice_id' => $invoice->id];
        });
    }
}
