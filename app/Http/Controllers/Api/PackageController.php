<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Package;
use App\Models\PersonCategory;
use App\Models\Purchase;
use App\Models\User;
use App\Support\Audit;
use App\Support\Money;
use App\Support\Packages;
use App\Support\Prices;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Pakker (medlemskaber og tilkøb): visning og køb for medlemmer, opsætning for bestyrelse og admin.
 * Intet slettes – pakker og personkategorier arkiveres; priser har historik med "gælder fra".
 */
class PackageController extends Controller
{
    /* ---------- Medlemmer ---------- */

    /** Pakker, der kan købes i dag. Offentlig for medlemskaber (bruges ved indmeldelse). */
    public function offer(Request $r)
    {
        $kind = $r->query('kind', 'membership');
        abort_unless(in_array($kind, ['membership', 'product']), 422);
        abort_if($kind === 'product' && ! auth('sanctum')->check(), 401);

        return [
            'categories' => self::categories(),
            'packages' => self::offers($kind),
            'singles' => self::singles(),
        ];
    }

    public static function offers(string $kind)
    {
        // Klippekort kun for ikke-medlemmer vises ikke for medlemmer (de købes på /klippekort)
        return Package::with('slots.category', 'product.resources')->where('kind', $kind)->whereNull('archived_at')->where('self_service', true)->where('guest_only', false)
            ->orderBy('position')->orderBy('id')->get()
            ->filter(fn ($p) => $p->buyableOn(now()))
            ->map(fn ($p) => self::offerRow($p))->values();
    }

    private static function offerRow(Package $p): array
    {
        [$from, $to] = $p->windowFor(now());
        $resources = $p->product?->needs_resource ? $p->product->resources->reject(fn ($res) => Packages::resourceTaken($res->id))->map->only('id', 'label')->values() : null;

        return [
            'id' => $p->id, 'kind' => $p->kind, 'name' => $p->name, 'description' => $p->description,
            'price' => $p->price(), 'price_fmt' => Money::fmt($p->price()),
            'period_label' => $p->periodLabel(), 'composition_label' => $p->compositionLabel(),
            'valid_from' => $from->toDateString(), 'valid_to' => $to?->toDateString(), 'starts_later' => $from->isAfter(now()->endOfDay()), 'early_access' => $p->early_access,
            'total_min' => $p->total_min, 'total_max' => $p->total_max,
            'slots' => $p->slots->map(fn ($s) => ['category_id' => $s->person_category_id, 'min' => $s->min, 'max' => $s->max])->values(),
            'gives_access' => $p->gives_access, 'requires_membership' => $p->requires_membership,
            'needs_resource' => $resources !== null, 'free_resources' => $resources,
        ];
    }

    /** Enkeltmedlemskaber til salg i dag – bruges til at vise loftet "aldrig dyrere end enkeltkontingent". */
    public static function singles()
    {
        return Packages::singleMemberships(true, now())->merge(Packages::singleMemberships(false, now()))
            ->map(fn ($p) => self::offerRow($p))->values();
    }

    private static function categories()
    {
        return PersonCategory::whereNull('archived_at')->orderBy('min_age')->get()
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'plural' => $c->plural, 'min_age' => $c->min_age, 'max_age' => $c->max_age]);
    }

    /** Husstandens køb + hvad der kan købes. */
    public function mine(Request $r)
    {
        $u = $r->user();
        $household = $u->managedMembers();
        $ids = $household->pluck('id');
        $purchases = Purchase::with('package', 'users', 'invoice', 'resource')
            ->where(fn ($q) => $q->whereHas('users', fn ($q) => $q->whereIn('users.id', $ids))->orWhere('payer_id', $u->id))
            ->whereNull('cancelled_at')->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', now()->subMonths(2)))
            ->latest('valid_from')->get()
            ->filter(fn ($p) => ! $p->invoice || in_array($p->invoice->status, ['open', 'paid']));

        return [
            'categories' => self::categories(),
            'memberships' => self::offers('membership'),
            'singles' => self::singles(),
            'products' => $u->status === 'pending' ? [] : self::offers('product'),
            'can_add_people' => ! $u->household_id || $u->household?->payer_id === $u->id,
            'household' => $household->map(fn ($m) => [
                'id' => $m->id, 'name' => $m->name, 'birthdate' => $m->birthdate?->toDateString(), 'age' => $m->age(), 'status' => $m->status,
                'membership' => ($c = $m->currentMembership()) ? ['name' => $c->package->name, 'valid_to' => $c->valid_to?->toDateString()] : null,
                'upcoming' => ($n = $m->upcomingMembership()) ? ['name' => $n->package->name, 'valid_from' => $n->valid_from->toDateString()] : null,
            ])->values(),
            'purchases' => $purchases->map(fn ($p) => [
                'id' => $p->id, 'kind' => $p->package->kind, 'name' => $p->package->name, 'resource' => $p->resource?->label,
                'people' => $p->users->pluck('name')->merge(array_column($p->invoice?->payload['new_members'] ?? [], 'name'))->unique()->values(),
                'valid_from' => $p->valid_from->toDateString(), 'valid_to' => $p->valid_to?->toDateString(),
                'status' => $p->invoice ? $p->invoice->displayStatus() : 'paid', 'invoice_id' => $p->invoice_id,
                'amount_fmt' => $p->invoice ? Money::fmt($p->invoice->amount) : null,
                'active' => $p->isPaid() && $p->valid_from->lte(now()) && (! $p->valid_to || $p->valid_to->gte(now()->startOfDay())),
                'can_cancel' => $p->invoice?->status === 'open' && $p->payer_id === $u->id,
            ])->values(),
        ];
    }

    public function buy(Request $r)
    {
        $u = $r->user();
        $d = $r->validate([
            'package_id' => 'required|exists:packages,id',
            'member_ids' => 'array', 'member_ids.*' => 'integer',
            'new_members' => 'array|max:10',
            'new_members.*.name' => 'required|string|max:120',
            'new_members.*.birthdate' => 'required|date|before:today',
            'new_members.*.email' => 'nullable|email|unique:users,email',
            'new_members.*.consent' => 'accepted',
            'resource_id' => 'nullable|integer',
        ], ['new_members.*.consent.accepted' => 'Giv samtykke for de personer, du tilføjer.'], ['new_members.*.name' => 'navn', 'new_members.*.birthdate' => 'fødselsdato']);
        $package = Package::with('slots.category', 'product')->findOrFail($d['package_id']);
        $allowed = $u->managedMembers()->pluck('id');
        abort_if(collect($d['member_ids'] ?? [])->diff($allowed)->isNotEmpty(), 403, 'Du kan kun købe til personer i din husstand.');
        $new = collect($d['new_members'] ?? [])->map(fn ($p) => ['name' => $p['name'], 'birthdate' => Carbon::parse($p['birthdate'])->toDateString(),
            'email' => $p['email'] ?? null, 'consent_at' => now()->toIso8601String()])->all();
        abort_if($new && $u->household_id && $u->household?->payer_id !== $u->id, 403, 'Kun husstandens betaler kan tilføje personer.');
        abort_if($u->status === 'pending' && $package->kind === 'product', 422, 'Betal først dit kontingent.');

        $purchase = Packages::buy($u, $package, $d['member_ids'] ?? [], $new, $d['resource_id'] ?? null);
        Packages::settleIfFree($purchase->invoice);

        return ['invoice_id' => $purchase->invoice_id, 'paid' => $purchase->invoice->fresh()->status === 'paid'];
    }

    public function cancel(Request $r, Purchase $purchase)
    {
        abort_unless($purchase->payer_id === $r->user()->id, 403);
        Packages::cancel($purchase, $r->user());

        return ['ok' => true];
    }

    /* ---------- Bestyrelse og admin ---------- */

    private function board(Request $r): void { abort_unless($r->user()->may('packages.manage'), 403, 'Du har ikke adgang til at ændre medlemskaber og tilkøb.'); }

    public function admin(Request $r)
    {
        abort_unless($r->user()->may('packages.manage') || $r->user()->may('prices.edit'), 403);
        $today = now()->toDateString();
        $prices = DB::table('prices')->leftJoin('users', 'users.id', '=', 'prices.created_by')->where('code', 'like', 'package.%')
            ->select('prices.*', 'users.name as by')->orderByDesc('valid_from')->get()->groupBy('code');
        $counts = Purchase::whereNull('cancelled_at')->selectRaw('package_id, count(*) n')->groupBy('package_id')->pluck('n', 'package_id');
        $active = Purchase::activeOn()->selectRaw('package_id, count(*) n')->groupBy('package_id')->pluck('n', 'package_id');
        $row = fn ($p) => ['id' => $p->id, 'amount' => (int) $p->amount, 'amount_fmt' => Money::fmt($p->amount), 'valid_from' => $p->valid_from, 'by' => $p->by];

        return [
            'can_edit' => $r->user()->may('packages.manage'),
            'can_price' => $r->user()->may('prices.edit'),
            'today' => $today,
            'categories' => PersonCategory::orderBy('min_age')->get()->map(fn ($c) => $c->only('id', 'name', 'plural', 'min_age', 'max_age') + ['archived' => (bool) $c->archived_at]),
            'accounts' => Account::where('type', 'income')->orderBy('number')->get(['id', 'number', 'name']),
            'activity_categories' => \App\Models\Category::whereNull('archived_at')->orderBy('name')->get(['id', 'name', 'color']),
            'packages' => Package::with('slots', 'categories')->orderBy('kind')->orderBy('position')->orderBy('id')->get()->map(function ($p) use ($prices, $today, $row, $counts, $active) {
                $hist = $prices[$p->priceCode()] ?? collect();
                $current = $hist->first(fn ($x) => $x->valid_from <= $today);

                return $p->only('id', 'kind', 'name', 'description', 'period_type', 'period_days', 'date_from', 'date_to', 'sale_from', 'sale_to', 'next_from', 'early_access', 'total_min', 'total_max',
                    'gives_access', 'requires_membership', 'self_service', 'renews_to_id', 'account_id', 'uses', 'guest_only') + [
                    'category_ids' => $p->categories->pluck('id')->values(),
                    'archived' => (bool) $p->archived_at,
                    'needs_resource' => (bool) $p->product?->needs_resource,
                    'slots' => $p->slots->map(fn ($s) => ['person_category_id' => $s->person_category_id, 'min' => $s->min, 'max' => $s->max])->values(),
                    'period_label' => $p->periodLabel(), 'composition_label' => $p->compositionLabel(),
                    'sale_label' => $p->saleLabel(), 'next_label' => $p->nextLabel(), 'on_sale' => $p->buyableOn(now()),
                    'current' => $current ? $row($current) : null,
                    'upcoming' => $hist->filter(fn ($x) => $x->valid_from > $today)->sortBy('valid_from')->map($row)->values(),
                    'previous' => $hist->filter(fn ($x) => $x->valid_from <= $today && $x->id !== $current?->id)->map($row)->values(),
                    'purchases' => (int) ($counts[$p->id] ?? 0), 'active_purchases' => (int) ($active[$p->id] ?? 0),
                ];
            }),
        ];
    }

    private function rules(?Package $p = null): array
    {
        return [
            'kind' => [$p ? 'prohibited' : 'required', 'in:membership,product'],
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:1000',
            'period_type' => 'required|in:days,dates,once',
            'period_days' => 'required_if:period_type,days|nullable|integer|min:1|max:365',
            'date_from' => ['required_if:period_type,dates', 'nullable', 'regex:/^(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/'],
            'date_to' => ['required_if:period_type,dates', 'nullable', 'regex:/^(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/'],
            'sale_from' => ['nullable', 'required_with:sale_to', 'regex:/^(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/'],
            'sale_to' => ['nullable', 'required_with:sale_from', 'regex:/^(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/'],
            'next_from' => ['nullable', 'regex:/^(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/'],
            'total_min' => 'required|integer|min:1|max:50',
            'total_max' => 'nullable|integer|min:1|max:50|gte:total_min',
            'gives_access' => 'boolean',
            'early_access' => 'boolean',
            'requires_membership' => 'boolean',
            'self_service' => 'boolean',
            'uses' => 'nullable|integer|min:1|max:100',
            'guest_only' => 'boolean',
            'category_ids' => 'array',
            'category_ids.*' => 'exists:categories,id',
            'renews_to_id' => ['nullable', Rule::exists('packages', 'id')->where('kind', 'membership')],
            'account_id' => ['required', Rule::exists('accounts', 'id')->where('type', 'income')],
            'slots' => 'required|array|min:1',
            'slots.*.person_category_id' => 'required|distinct|exists:person_categories,id',
            'slots.*.min' => 'required|integer|min:0|max:50',
            'slots.*.max' => 'required|integer|min:0|max:50|gte:slots.*.min',
            'price' => [$p ? 'prohibited' : 'required', 'numeric', 'min:0', 'max:100000'],
        ];
    }

    private static function messages(): array
    {
        return [
            'date_from.regex' => 'Datoen skal skrives som MM-DD, fx 05-01.', 'date_to.regex' => 'Datoen skal skrives som MM-DD, fx 04-30.',
            'total_max.gte' => 'Højst skal være mindst lige så mange som mindst.', 'slots.*.max.gte' => 'Højst skal være mindst lige så mange som mindst.',
            'slots.required' => 'Vælg mindst én personkategori.', 'period_days.max' => 'En periode kan højst være 365 dage.',
        ];
    }

    private function validated(Request $r, ?Package $p = null): array
    {
        $d = $r->validate($this->rules($p), self::messages(), ['total_min' => 'antal personer (mindst)', 'total_max' => 'antal personer (højst)']);
        $mins = array_sum(array_column($d['slots'], 'min'));
        $maxs = array_sum(array_column($d['slots'], 'max'));
        abort_if($d['total_max'] !== null && $mins > $d['total_max'], 422, 'Kategorierne kræver flere personer, end pakken højst dækker.');
        abort_if($maxs < $d['total_min'], 422, 'Kategorierne tillader færre personer, end pakken mindst skal dække.');
        if ($d['period_type'] === 'dates') {
            foreach (['date_from', 'date_to'] as $f) abort_unless(checkdate((int) substr($d[$f], 0, 2), (int) substr($d[$f], 3, 2), 2024), 422, 'Ugyldig dato: '.$d[$f]);
        }
        foreach (['sale_from', 'sale_to', 'next_from'] as $f) {
            if (! empty($d[$f])) abort_unless(checkdate((int) substr($d[$f], 0, 2), (int) substr($d[$f], 3, 2), 2024), 422, 'Ugyldig dato: '.$d[$f]);
        }
        $d['sale_from'] ??= null; $d['sale_to'] ??= null;
        $d['next_from'] = $d['period_type'] === 'dates' ? ($d['next_from'] ?? null) : null;
        abort_if(($d['renews_to_id'] ?? null) && ($d['period_type'] !== 'dates' || ($p && $d['renews_to_id'] == $p->id)), 422, 'Kun pakker med faste datoer kan fornyes som en anden pakke.');
        $d['period_days'] = $d['period_type'] === 'days' ? $d['period_days'] : null;
        [$d['date_from'], $d['date_to']] = $d['period_type'] === 'dates' ? [$d['date_from'], $d['date_to']] : [null, null];
        $kind = $p?->kind ?? $d['kind'];
        if ($kind === 'membership') { $d['requires_membership'] = false; $d['uses'] = null; $d['guest_only'] = false; $d['category_ids'] = []; }
        else { $d['gives_access'] = true; $d['renews_to_id'] = null; }
        abort_if(! empty($d['uses']) && empty($d['category_ids']), 422, 'Vælg hvilke aktiviteter (kategorier) klippekortet gælder til.');
        if (empty($d['uses'])) { $d['uses'] = null; $d['category_ids'] = []; }
        abort_if(! empty($d['uses']) && ! empty($d['requires_membership']) && ! empty($d['guest_only']), 422, 'Et klippekort kan ikke både kræve medlemskab og være kun for ikke-medlemmer.');

        return $d;
    }

    public function store(Request $r)
    {
        $this->board($r);
        $d = $this->validated($r);
        $p = DB::transaction(function () use ($r, $d) {
            $p = Package::create(collect($d)->except('slots', 'price', 'category_ids')->all() + ['created_by' => $r->user()->id,
                'position' => (Package::where('kind', $d['kind'])->max('position') ?? 0) + 1]);
            foreach ($d['slots'] as $s) $p->slots()->create($s);
            $p->categories()->sync($d['category_ids'] ?? []);
            Prices::set($p->priceCode(), (int) round($d['price'] * 100), now()->toDateString(), $r->user()->id);
            Audit::log('package.created', $p, ['name' => $p->name, 'price' => Money::fmt((int) round($d['price'] * 100))]);

            return $p;
        });

        return ['id' => $p->id] + $this->admin($r);
    }

    /** Ændringer gælder kun nye køb. Køb, der allerede er lavet, beholder deres personer og periode. */
    public function update(Request $r, Package $package)
    {
        $this->board($r);
        $d = $this->validated($r, $package);
        $before = $package->only(array_keys(collect($d)->except('slots', 'category_ids')->all())) + ['slots' => $package->slots->map->only('person_category_id', 'min', 'max')->all()];
        DB::transaction(function () use ($package, $d) {
            $package->update(collect($d)->except('slots', 'category_ids')->all());
            $package->categories()->sync($d['category_ids'] ?? []);
            $package->slots()->delete();
            foreach ($d['slots'] as $s) $package->slots()->create($s);
        });
        Audit::log('package.updated', $package, ['before' => $before, 'after' => $d]);

        return $this->admin($r);
    }

    public function archive(Request $r, Package $package)
    {
        $this->board($r);
        $on = $r->validate(['archived' => 'required|boolean'])['archived'];
        $package->update(['archived_at' => $on ? now() : null]);
        Audit::log($on ? 'package.archived' : 'package.restored', $package, ['name' => $package->name]);

        return $this->admin($r);
    }

    /** Ny pris med virkning fra en dato (i dag eller senere). Allerede oprettede regninger ændres aldrig. */
    public function setPrice(Request $r, Package $package)
    {
        abort_unless($r->user()->may('prices.edit'), 403, 'Du har ikke adgang til at ændre priser.');
        $d = $r->validate(['amount' => 'required|numeric|min:0|max:100000', 'valid_from' => 'required|date|after_or_equal:today'],
            ['valid_from.after_or_equal' => 'En ny pris kan tidligst gælde fra i dag.']);
        $ore = (int) round($d['amount'] * 100);
        $date = Carbon::parse($d['valid_from'])->toDateString();
        $old = DB::table('prices')->where('code', $package->priceCode())->where('valid_from', '<=', $date)->orderByDesc('valid_from')->value('amount');
        Prices::set($package->priceCode(), $ore, $date, $r->user()->id);
        Audit::log('price.set', $package, ['label' => $package->name, 'from' => $old !== null ? Money::fmt($old) : '–', 'to' => Money::fmt($ore), 'valid_from' => $date]);

        return $this->admin($r);
    }

    public function deletePrice(Request $r, Package $package, int $price)
    {
        abort_unless($r->user()->may('prices.edit'), 403);
        $row = DB::table('prices')->where('id', $price)->where('code', $package->priceCode())->first();
        abort_unless($row, 404);
        abort_if($row->valid_from <= now()->toDateString(), 422, 'Prisen er allerede trådt i kraft og kan ikke slettes. Læg i stedet en ny pris ind.');
        DB::table('prices')->delete($price);
        Prices::flush();
        Audit::log('price.deleted', $package, ['label' => $package->name, 'amount' => Money::fmt($row->amount), 'valid_from' => $row->valid_from]);

        return $this->admin($r);
    }

    public function saveCategory(Request $r, ?PersonCategory $category = null)
    {
        $this->board($r);
        $d = $r->validate([
            'name' => 'required|string|max:40', 'plural' => 'required|string|max:40',
            'min_age' => 'required|integer|min:0|max:120', 'max_age' => 'nullable|integer|min:0|max:120|gte:min_age',
            'archived' => 'boolean',
        ], ['max_age.gte' => 'Højeste alder skal være mindst lige så høj som laveste.']);
        $archived = $d['archived'] ?? null;
        unset($d['archived']);
        $category ??= new PersonCategory;
        $before = $category->exists ? $category->only('name', 'plural', 'min_age', 'max_age') : null;
        $category->fill($d);
        if ($archived !== null) $category->archived_at = $archived ? now() : null;
        $category->save();
        Audit::log($before ? 'person_category.updated' : 'person_category.created', $category, ['before' => $before, 'after' => $d]);

        return $this->admin($r);
    }
}
