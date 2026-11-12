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

    /** Regninger for købt udstyrsleje/dagsleje, der ikke er betalt endnu (så man kan se, hvorfor man ikke kan booke). */
    public static function unpaidRentalInvoiceIds(User $u): array
    {
        $today = now()->toDateString();

        return $u->purchases()->whereNull('cancelled_at')
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $today))
            ->whereHas('package', fn ($q) => $q->where('equipment_booking', true))
            ->whereHas('invoice', fn ($q) => $q->where('status', 'open'))
            ->pluck('invoice_id')->unique()->values()->all();
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
        $ids = self::itemIdsFor($a->equipment_mode ?? 'all', $a->categories->pluck('id')->all(), $a->equipment->pluck('id')->all());

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

    /* ---------- Anmodning om at flytte en booking ---------- */

    /** Udstyr, aktiviteten skal bruge (også når den afventer). */
    public static function neededIds(Activity $a): array
    {
        return $a->isPending()
            ? self::itemIdsFor($a->equipment_mode ?? 'all', $a->categories->pluck('id')->all(), $a->equipment->pluck('id')->all())
            : self::activityItemIds($a);
    }

    /** Bekræftede bookinger, der optager udstyr, aktiviteten skal bruge. */
    public static function conflictingBookingIds(Activity $a): array
    {
        return collect(self::conflicts(self::neededIds($a), $a->starts_at, $a->ends_at, $a->id))
            ->flatMap(fn ($c) => $c['holders'])->where('kind', 'booking')->pluck('booking_id')->unique()->values()->all();
    }

    /**
     * Svarfrist: 24 timer – men højst halvdelen af tiden, til aktiviteten starter, så der er tid til at handle bagefter.
     */
    public static function moveDeadline(Activity $a): Carbon
    {
        $minutes = max(0, (int) now()->diffInMinutes($a->starts_at, false));

        return now()->addMinutes(min(24 * 60, intdiv($minutes, 2)));
    }

    /**
     * Bed dem, der har booket aktivitetens udstyr, om at flytte bookingen. Springer bookinger over, der allerede
     * har en åben anmodning fra aktiviteten. Returnerer antal sendte anmodninger.
     */
    public static function requestMoves(Activity $a, User $by, ?string $message = null, ?array $onlyBookings = null): int
    {
        $a->loadMissing('categories', 'equipment');
        $ids = self::conflictingBookingIds($a);
        if ($onlyBookings !== null) $ids = array_values(array_intersect($ids, $onlyBookings));
        $needed = self::neededIds($a);
        $sent = 0;
        foreach (EquipmentBooking::with('user', 'items')->whereIn('id', $ids)->get() as $b) {
            if ($b->moveRequests()->where('activity_id', $a->id)->where('status', 'pending')->exists()) continue;
            if ($b->user_id === $by->id) continue; // egen booking: flyt den selv
            $req = $b->moveRequests()->create(['activity_id' => $a->id, 'requested_by' => $by->id, 'message' => $message,
                'deadline_at' => self::moveDeadline($a)]);
            $items = $b->items->whereIn('id', $needed)->pluck('name')->implode(', ');
            $b->user->notify(new \App\Notifications\ClubMessage('Kan du flytte din booking af udstyr?',
                $by->name.' beder dig flytte din booking '.self::when($b).', fordi '.$items.' skal bruges til aktiviteten "'.$a->title.'" '
                .self::when($a).'.'.($message ? ' "'.$message.'"' : '')
                .' Flyt eller annullér bookingen – eller svar nej – senest '.self::fmt($req->deadline_at).'. Ellers kan bookingen blive annulleret.',
                '/udstyr?vis=bookinger'));
            \App\Support\Audit::log('equipment.move_requested', $b, ['activity_id' => $a->id]);
            $sent++;
        }

        return $sent;
    }

    /** Må brugeren annullere bookingen? Egen booking, rettigheden, eller en anmodning om at flytte, der ikke er svaret på inden fristen. */
    public static function mayCancel(User $u, EquipmentBooking $b): bool
    {
        if (! in_array($b->status, ['confirmed', 'requested'])) return false;
        if ($b->user_id === $u->id || $u->may('equipment.cancel')) return true;

        return (bool) self::overdueRequestFor($u, $b);
    }

    /** En anmodning om at flytte bookingen, der er overskredet, og som brugeren står bag (eller er træner/tovholder på). */
    public static function overdueRequestFor(User $u, EquipmentBooking $b): ?\App\Models\EquipmentMoveRequest
    {
        if (! $u->may('equipment.cancel_overdue')) return null;
        return $b->moveRequests()->with('activity.trainers')->where('status', 'pending')->where('deadline_at', '<=', now())->get()
            ->first(fn ($m) => $m->requested_by === $u->id || \App\Http\Controllers\Api\EquipmentController::mayDecide($u, $m->activity));
    }

    /**
     * Bookingen er annulleret eller flyttet: luk åbne anmodninger og fortæl dem, der bad om det, at udstyret er frit.
     * $status: done (den, der havde booket, flyttede/annullerede) eller forced (annulleret af en anden).
     */
    public static function closeMoves(EquipmentBooking $b, string $status, User $by): void
    {
        foreach ($b->moveRequests()->with('activity', 'requester')->whereIn('status', ['pending', 'declined'])->get() as $m) {
            $m->update(['status' => $status, 'answered_at' => now(), 'answered_by' => $by->id]);
            if ($status === 'done' && $m->requester && $m->requester->id !== $by->id) {
                $m->requester->notify(new \App\Notifications\ClubMessage('Udstyret er frigivet: '.$m->activity->title,
                    $b->user->name.' har flyttet eller annulleret sin booking, så udstyret er frit til "'.$m->activity->title.'".'
                    .($m->activity->isPending() ? ' Du kan nu oprette aktiviteten.' : ''), '/aktiviteter/'.$m->activity_id));
            }
        }
    }

    public static function when($x): string
    {
        return self::fmt($x->starts_at).'–'.($x->starts_at->isSameDay($x->ends_at) ? $x->ends_at->format('H:i') : self::fmt($x->ends_at));
    }

    public static function fmt($d): string { return $d->locale('da')->isoFormat('D. MMM [kl.] HH:mm'); }

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
