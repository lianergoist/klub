<?php

namespace App\Support;

use App\Models\Activity;
use App\Models\Equipment as Item;
use App\Models\EquipmentBooking;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Hvem har hvilket udstyr hvornår?
 *  - En aktivitet booker som udgangspunkt alt udstyr i underkategorierne til sine kategorier (equipment_mode = all),
 *    eller kun det valgte udstyr (selected). Udstyr, som træneren har sagt ja til at låne ud, er undtaget.
 *  - En booking er bestemte stykker udstyr i et tidsrum. Kun bekræftede bookinger optager udstyret.
 *  - Aktiviteter, der afventer, er ikke oprettet endnu og optager ikke udstyr.
 *  - Defekt udstyr kan ikke bookes eller bruges.
 */
class Equipment
{
    /** Må brugeren booke udstyr på datoen? (rettighed eller betalt udstyrsleje/dagsleje, der gælder på datoen) */
    public static function canBook(User $u, $date = null): bool
    {
        return $u->may('equipment.book') || self::hasRental($u, $date);
    }

    public static function hasRental(User $u, $date = null): bool
    {
        return $u->purchases()->activeOn($date)->whereHas('package', fn ($q) => $q->where('equipment_booking', true))->exists();
    }

    /** Udstyr en (evt. kommende) aktivitet booker. */
    public static function itemIdsFor(string $mode, array $categoryIds, array $selectedIds = []): array
    {
        $q = Item::inUse()->where('status', '!=', 'defect');
        if ($mode === 'selected') {
            return $q->whereIn('id', $selectedIds ?: [0])->pluck('id')->all();
        }

        return $q->whereHas('type', fn ($q) => $q->whereIn('category_id', $categoryIds ?: [0])->whereNull('archived_at'))->pluck('id')->all();
    }

    public static function activityItemIds(Activity $a): array
    {
        if ($a->cancelled_at || $a->isPending()) return [];
        $ids = self::itemIdsFor($a->equipment_mode, $a->categories->pluck('id')->all(), $a->equipment->pluck('id')->all());

        return array_values(array_diff($ids, self::releasedIds($a)));
    }

    /** Udstyr, træneren har sagt ja til at låne ud fra aktiviteten. */
    public static function releasedIds(Activity $a): array
    {
        return EquipmentBooking::where('status', 'confirmed')
            ->whereHas('approvals', fn ($q) => $q->where('activity_id', $a->id)->where('status', 'accepted'))
            ->with('items:id')->get()->flatMap(fn ($b) => $b->items->pluck('id'))->unique()->values()->all();
    }

    /**
     * equipment_id => liste af det, der optager udstyret i tidsrummet.
     */
    public static function holders($start, $end, ?int $exceptActivity = null, ?int $exceptBooking = null): array
    {
        $start = Carbon::parse($start); $end = Carbon::parse($end);
        $out = [];

        $acts = Activity::with('categories', 'equipment', 'trainers', 'creator')->whereNull('cancelled_at')->where('status', 'active')
            ->where('starts_at', '<', $end)->where('ends_at', '>', $start)
            ->when($exceptActivity, fn ($q) => $q->whereKeyNot($exceptActivity))->get();
        foreach ($acts as $a) {
            $h = ['kind' => 'activity', 'activity_id' => $a->id, 'title' => $a->title, 'starts_at' => $a->starts_at->toIso8601String(), 'ends_at' => $a->ends_at->toIso8601String(),
                'contacts' => $a->contacts()->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values()->all()];
            foreach (self::activityItemIds($a) as $id) $out[$id][] = $h;
        }

        $bookings = EquipmentBooking::with('user', 'items:id')->where('status', 'confirmed')
            ->where('starts_at', '<', $end)->where('ends_at', '>', $start)
            ->when($exceptBooking, fn ($q) => $q->whereKeyNot($exceptBooking))->get();
        foreach ($bookings as $b) {
            $h = ['kind' => 'booking', 'booking_id' => $b->id, 'user_id' => $b->user_id, 'name' => $b->user->name,
                'starts_at' => $b->starts_at->toIso8601String(), 'ends_at' => $b->ends_at->toIso8601String()];
            foreach ($b->items as $i) $out[$i->id][] = $h;
        }

        return $out;
    }

    /** Konflikter for et sæt udstyr: [{item, holders}] */
    public static function conflicts(array $itemIds, $start, $end, ?int $exceptActivity = null, ?int $exceptBooking = null): array
    {
        $holders = self::holders($start, $end, $exceptActivity, $exceptBooking);
        $ids = array_values(array_intersect($itemIds, array_keys($holders)));
        if (! $ids) return [];

        return Item::with('type.category')->whereIn('id', $ids)->orderBy('name')->get()
            ->map(fn ($i) => ['item' => self::brief($i), 'holders' => $holders[$i->id]])->values()->all();
    }

    /**
     * Konflikter samlet pr. det, der optager udstyret (til advarslen ved oprettelse af en aktivitet):
     * [{kind, title/name, starts_at, contacts, items: [navne]}]
     */
    public static function groupByHolder(array $conflicts): array
    {
        $g = [];
        foreach ($conflicts as $c) {
            foreach ($c['holders'] as $h) {
                $key = $h['kind'].($h['activity_id'] ?? $h['booking_id']);
                $g[$key] ??= $h + ['items' => []];
                $g[$key]['items'][] = $c['item']['name'];
            }
        }

        return array_values($g);
    }

    public static function brief(Item $i): array
    {
        return ['id' => $i->id, 'name' => $i->name, 'status' => $i->status, 'status_label' => Item::STATUS[$i->status],
            'type' => $i->type?->name, 'category' => $i->type?->category?->name];
    }

    /** Eksempel på udstyrsliste til demo (bruges af seederen). Gør intet, hvis der allerede er udstyr. */
    public static function createDemo(): void
    {
        if (\App\Models\EquipmentType::exists()) return;
        $cat = fn ($n) => \App\Models\Category::where('name', $n)->value('id');
        $list = [
            'Windsurf' => ['Boards' => ['Windsurfboard', 6], 'Sejl' => [['Sejl 3,5 m²', 'Sejl 4,5 m²', 'Sejl 5,0 m²', 'Sejl 5,8 m²', 'Sejl 6,5 m²']], 'Master' => ['Mast', 5], 'Bomme' => ['Bom', 5]],
            'SUP' => ['Boards' => ['SUP', 10], 'Padler' => ['Padle', 10]],
            'Wingfoil' => ['Wings' => [['Wing 3 m²', 'Wing 4 m²', 'Wing 5 m²']], 'Foilboards' => ['Foilboard', 3]],
            'Kite' => ['Kites' => [['Kite 7 m²', 'Kite 9 m²', 'Kite 12 m²']], 'Boards' => ['Kiteboard', 2]],
        ];
        foreach ($list as $c => $types) {
            if (! $id = $cat($c)) continue;
            foreach ($types as $t => $spec) {
                $type = \App\Models\EquipmentType::create(['category_id' => $id, 'name' => $t]);
                $names = is_array($spec[0]) ? $spec[0] : array_map(fn ($i) => $spec[0].' '.$i, range(1, $spec[1]));
                foreach ($names as $n) {
                    $i = Item::create(['equipment_type_id' => $type->id, 'name' => $n]);
                    $i->events()->create(['action' => 'created', 'status' => 'ok']);
                }
            }
        }
        foreach (['SUP 4' => ['service', 'Ridse i bunden – skal slibes og lakeres'], 'Sejl 4,5 m²' => ['defect', 'Flænge ved masten'], 'Bom 3' => ['service', 'Trimline slidt']] as $n => [$st, $note]) {
            if ($i = Item::where('name', $n)->first()) {
                $i->update(['status' => $st, 'status_note' => $note]);
                $i->events()->create(['action' => 'status', 'status' => $st, 'note' => $note]);
            }
        }
    }
}
