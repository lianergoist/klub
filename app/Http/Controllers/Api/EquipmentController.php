<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Category;
use App\Models\Equipment as Item;
use App\Models\EquipmentBooking;
use App\Models\EquipmentBookingApproval;
use App\Models\EquipmentMoveRequest;
use App\Models\EquipmentType;
use App\Models\User;
use App\Notifications\ClubMessage;
use App\Support\Audit;
use App\Support\Equipment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

/**
 * Udstyr: liste og stand (trænere/bestyrelse), underkategorier (admin), booking af bestemte stykker udstyr
 * og ønsker om at låne udstyr, som en aktivitet har booket (træneren svarer ja eller nej).
 */
class EquipmentController extends Controller
{
    private function canSee(User $u): bool
    {
        return $u->may('equipment.manage') || $u->may('equipment.types') || $u->may('activities.manage') || Equipment::canBook($u)
            || EquipmentBooking::where('user_id', $u->id)->whereIn('status', ['confirmed', 'requested'])->where('ends_at', '>', now())->exists()
            || Equipment::unpaidRentalInvoiceIds($u);
    }

    /* ---------- Udstyrslisten ---------- */

    public function index(Request $r)
    {
        $u = $r->user();
        abort_unless($this->canSee($u), 403);
        $archived = $r->boolean('archived');
        $now = Equipment::holders(now(), now()->addMinute());

        $types = EquipmentType::with(['equipment' => fn ($q) => $q->when(! $archived, fn ($q) => $q->whereNull('archived_at'))->orderByRaw('LENGTH(name), name')])
            ->orderBy('name')->get()->groupBy('category_id');

        return [
            'can' => ['manage' => $u->may('equipment.manage'), 'types' => $u->may('equipment.types'), 'book' => Equipment::canBook($u)],
            'unpaid_rental' => Equipment::canBook($u) ? [] : Equipment::unpaidRentalInvoiceIds($u),
            'statuses' => Item::STATUS,
            'categories' => Category::orderBy('name')->get()->filter(fn ($c) => ! $c->archived_at || isset($types[$c->id]))->map(fn ($c) => [
                'id' => $c->id, 'name' => $c->name, 'color' => $c->color,
                'types' => ($types[$c->id] ?? collect())->filter(fn ($t) => ! $t->archived_at || $archived)->map(fn ($t) => [
                    'id' => $t->id, 'name' => $t->name, 'archived' => (bool) $t->archived_at,
                    'items' => $t->equipment->map(fn ($i) => [
                        'id' => $i->id, 'name' => $i->name, 'note' => $i->note, 'status' => $i->status, 'status_label' => Item::STATUS[$i->status],
                        'status_note' => $i->status_note, 'archived' => (bool) $i->archived_at, 'now' => $now[$i->id][0] ?? null,
                    ])->values(),
                ])->values(),
            ])->values(),
        ];
    }

    public function show(Request $r, Item $equipment)
    {
        abort_unless($this->canSee($r->user()), 403);
        $equipment->load('type.category', 'events.user');
        $labels = ['created' => 'Oprettet', 'edited' => 'Rettet', 'status' => 'Stand', 'archived' => 'Arkiveret', 'restored' => 'Taget i brug igen'];

        return Equipment::brief($equipment) + [
            'note' => $equipment->note, 'status_note' => $equipment->status_note, 'archived' => (bool) $equipment->archived_at,
            'equipment_type_id' => $equipment->equipment_type_id,
            'events' => $equipment->events->map(fn ($e) => [
                'label' => $labels[$e->action] ?? $e->action, 'status' => $e->status ? Item::STATUS[$e->status] : null,
                'note' => $e->note, 'by' => $e->user?->name, 'at' => $e->created_at->toIso8601String(),
            ]),
            'upcoming' => EquipmentBooking::with('user')->where('status', 'confirmed')->where('ends_at', '>', now())
                ->whereHas('items', fn ($q) => $q->whereKey($equipment->id))->orderBy('starts_at')->limit(10)->get()
                ->map(fn ($b) => ['name' => $b->user->name, 'starts_at' => $b->starts_at->toIso8601String(), 'ends_at' => $b->ends_at->toIso8601String()]),
        ];
    }

    public function storeType(Request $r)
    {
        abort_unless($r->user()->may('equipment.types'), 403, 'Du har ikke adgang til at oprette underkategorier.');
        $d = $r->validate(['category_id' => 'required|exists:categories,id', 'name' => 'required|string|max:80']);
        $t = EquipmentType::create($d);
        Audit::log('equipment_type.created', $t, $d);

        return ['id' => $t->id];
    }

    public function updateType(Request $r, EquipmentType $type)
    {
        abort_unless($r->user()->may('equipment.types'), 403, 'Du har ikke adgang til at rette underkategorier.');
        $d = $r->validate(['name' => 'sometimes|required|string|max:80', 'archived' => 'sometimes|boolean']);
        if (array_key_exists('archived', $d)) {
            abort_if($d['archived'] && $type->equipment()->whereNull('archived_at')->exists(), 422, 'Arkivér eller flyt først udstyret i underkategorien.');
            $type->archived_at = $d['archived'] ? now() : null;
        }
        if (isset($d['name'])) $type->name = $d['name'];
        $type->save();
        Audit::log('equipment_type.updated', $type, $d);

        return ['ok' => true];
    }

    public function store(Request $r)
    {
        abort_unless($r->user()->may('equipment.manage'), 403, 'Du har ikke adgang til at tilføje udstyr.');
        $d = $r->validate([
            'equipment_type_id' => ['required', Rule::exists('equipment_types', 'id')->whereNull('archived_at')],
            'name' => 'required|string|max:70', 'note' => 'nullable|string|max:500', 'count' => 'nullable|integer|min:1|max:50',
        ]);
        // Flere ens: "SUP" × 5 → SUP 1 … SUP 5 (fortsætter efter højeste eksisterende nummer)
        $count = $d['count'] ?? 1;
        $names = [$d['name']];
        if ($count > 1) {
            $max = Item::where('equipment_type_id', $d['equipment_type_id'])->where('name', 'like', $d['name'].' %')->pluck('name')
                ->map(fn ($n) => (int) substr($n, strlen($d['name']) + 1))->max() ?? 0;
            $names = array_map(fn ($i) => $d['name'].' '.($max + $i), range(1, $count));
        }
        $ids = DB::transaction(function () use ($names, $d, $r) {
            return array_map(function ($name) use ($d, $r) {
                $i = Item::create(['equipment_type_id' => $d['equipment_type_id'], 'name' => $name, 'note' => $d['note'] ?? null]);
                $i->events()->create(['user_id' => $r->user()->id, 'action' => 'created', 'status' => 'ok']);

                return $i->id;
            }, $names);
        });
        Audit::log('equipment.created', null, ['names' => $names]);

        return ['ids' => $ids];
    }

    public function update(Request $r, Item $equipment)
    {
        abort_unless($r->user()->may('equipment.manage'), 403);
        $d = $r->validate([
            'name' => 'required|string|max:80', 'note' => 'nullable|string|max:500',
            'equipment_type_id' => ['required', Rule::exists('equipment_types', 'id')],
        ]);
        $equipment->update($d);
        $equipment->events()->create(['user_id' => $r->user()->id, 'action' => 'edited']);

        return ['ok' => true];
    }

    public function setStatus(Request $r, Item $equipment)
    {
        abort_unless($r->user()->may('equipment.manage'), 403, 'Du har ikke adgang til at rette udstyrets stand.');
        $d = $r->validate(['status' => ['required', Rule::in(array_keys(Item::STATUS))], 'note' => 'nullable|string|max:500']);
        $d['note'] = $d['note'] ?? null;
        abort_if($d['status'] !== 'ok' && ! $d['note'], 422, 'Skriv kort, hvad der er galt.');
        $equipment->update(['status' => $d['status'], 'status_note' => $d['status'] === 'ok' ? null : $d['note']]);
        $equipment->events()->create(['user_id' => $r->user()->id, 'action' => 'status', 'status' => $d['status'], 'note' => $d['note']]);

        // Defekt: fortæl dem, der har booket det i fremtiden
        if ($d['status'] === 'defect') {
            $users = EquipmentBooking::with('user')->where('status', 'confirmed')->where('ends_at', '>', now())
                ->whereHas('items', fn ($q) => $q->whereKey($equipment->id))->get()->pluck('user')->unique('id');
            Notification::send($users, new ClubMessage('Udstyr meldt defekt: '.$equipment->name,
                'Udstyr, du har booket, er meldt defekt: '.$d['note'], '/udstyr?vis=bookinger'));
        }

        return ['ok' => true];
    }

    public function archive(Request $r, Item $equipment)
    {
        abort_unless($r->user()->may('equipment.manage'), 403);
        $d = $r->validate(['archived' => 'required|boolean', 'note' => 'nullable|string|max:500']);
        $equipment->update(['archived_at' => $d['archived'] ? now() : null]);
        $equipment->events()->create(['user_id' => $r->user()->id, 'action' => $d['archived'] ? 'archived' : 'restored', 'note' => $d['note'] ?? null]);
        Audit::log($d['archived'] ? 'equipment.archived' : 'equipment.restored', $equipment);

        return ['ok' => true];
    }

    /* ---------- Ledigt udstyr i et tidsrum ---------- */

    public function availability(Request $r)
    {
        abort_unless($this->canSee($r->user()), 403);
        $d = $r->validate(['starts_at' => 'required|date', 'ends_at' => 'required|date|after:starts_at',
            'except_activity' => 'nullable|integer', 'except_booking' => 'nullable|integer']);
        $holders = Equipment::holders($d['starts_at'], $d['ends_at'], $d['except_activity'] ?? null, $d['except_booking'] ?? null);

        return Item::inUse()->with('type.category')->whereHas('type', fn ($q) => $q->whereNull('archived_at'))->orderByRaw('LENGTH(name), name')->get()
            ->map(fn ($i) => Equipment::brief($i) + ['category_id' => $i->type->category_id, 'type_id' => $i->equipment_type_id, 'holders' => $holders[$i->id] ?? []])
            ->values();
    }

    /* ---------- Bookinger ---------- */

    public function bookings(Request $r)
    {
        $u = $r->user(); // egne bookinger kan altid ses – også når udstyrslejen er udløbet
        $all = $r->query('scope') === 'all' && ($u->may('equipment.manage') || $u->may('activities.manage'));
        $q = EquipmentBooking::with('user', 'items.type.category', 'approvals.activity.trainers', 'approvals.activity.creator', 'approvals.decider',
                'moveRequests.activity', 'moveRequests.requester')
            ->when(! $all, fn ($q) => $q->where('user_id', $u->id))
            ->when($all, fn ($q) => $q->whereIn('status', ['confirmed', 'requested']))
            ->where('ends_at', '>', $r->query('past') ? now()->subMonths(3) : now())->orderBy('starts_at')->limit(200);

        // Ønsker, jeg skal svare på
        $toDecide = EquipmentBookingApproval::with('booking.user', 'booking.items', 'activity.trainers', 'activity.creator')->where('status', 'pending')
            ->whereHas('booking', fn ($q) => $q->where('status', 'requested')->where('ends_at', '>', now()))->get()
            ->filter(fn ($a) => $this->mayDecide($u, $a->activity))->values();

        return [
            'bookings' => $q->get()->map(fn ($b) => $this->bookingRow($b, $u)),
            'to_decide' => $toDecide->map(fn ($a) => $this->approvalRow($a)),
            'can_all' => $u->may('equipment.manage') || $u->may('activities.manage'),
        ];
    }

    private function bookingRow(EquipmentBooking $b, User $u): array
    {
        return [
            'id' => $b->id, 'user' => $b->user->name, 'user_id' => $b->user_id, 'mine' => $b->user_id === $u->id,
            'starts_at' => $b->starts_at->toIso8601String(), 'ends_at' => $b->ends_at->toIso8601String(),
            'status' => $b->status, 'status_label' => EquipmentBooking::STATUS[$b->status], 'note' => $b->note,
            'items' => $b->items->map(fn ($i) => Equipment::brief($i))->values(),
            'approvals' => $b->approvals->map(fn ($a) => [
                'activity_id' => $a->activity_id, 'title' => $a->activity->title, 'status' => $a->status, 'reply' => $a->reply,
                'contacts' => $a->activity->contacts()->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values(),
                'decided_by' => $a->decider?->name,
            ])->values(),
            // Anmodninger om at flytte bookingen (åbne og dem, der er sagt nej til)
            'moves' => $b->status === 'confirmed' ? $b->moveRequests->whereIn('status', ['pending', 'declined'])->map(fn ($m) => [
                'id' => $m->id, 'activity_id' => $m->activity_id, 'activity' => $m->activity->title,
                'activity_starts_at' => $m->activity->starts_at->toIso8601String(), 'activity_ends_at' => $m->activity->ends_at->toIso8601String(),
                'by' => $m->requester?->name, 'by_id' => $m->requested_by, 'message' => $m->message, 'status' => $m->status, 'reply' => $m->reply,
                'deadline_at' => $m->deadline_at->toIso8601String(), 'overdue' => $m->overdue(),
            ])->values() : [],
            'can_cancel' => Equipment::mayCancel($u, $b),
        ];
    }

    public function approvalRow(EquipmentBookingApproval $a): array
    {
        $b = $a->booking;

        return [
            'id' => $a->id, 'activity_id' => $a->activity_id, 'activity' => $a->activity->title, 'status' => $a->status, 'reply' => $a->reply,
            'user' => $b->user->name, 'user_id' => $b->user_id, 'note' => $b->note,
            'starts_at' => $b->starts_at->toIso8601String(), 'ends_at' => $b->ends_at->toIso8601String(),
            'items' => $b->items->map(fn ($i) => $i->name)->values(),
        ];
    }

    public function book(Request $r)
    {
        $u = $r->user();
        $d = $r->validate([
            'starts_at' => 'required|date|after:'.now()->subHour(), 'ends_at' => 'required|date|after:starts_at',
            'equipment_ids' => 'required|array|min:1|max:50', 'equipment_ids.*' => 'integer',
            'note' => 'nullable|string|max:500', 'request' => 'boolean',
            'replaces' => 'nullable|integer', // flyt en booking: den gamle annulleres, når den nye er gemt
        ], [], ['starts_at' => 'start', 'ends_at' => 'slut', 'equipment_ids' => 'udstyr']);
        $old = null;
        if (! empty($d['replaces'])) {
            $old = EquipmentBooking::where('user_id', $u->id)->where('status', 'confirmed')->find($d['replaces']);
            abort_unless($old, 422, 'Bookingen, du vil flytte, findes ikke længere.');
        }
        abort_unless(Equipment::canBook($u, Carbon::parse($d['starts_at'])), 403,
            'Du kan ikke booke udstyr. Det kræver udstyrsleje eller dagsleje, der gælder på dagen.');
        abort_if(Carbon::parse($d['starts_at'])->diffInDays(Carbon::parse($d['ends_at'])) > 14, 422, 'En booking kan højst vare 14 dage.');

        return DB::transaction(function () use ($d, $u, $r, $old) {
            DB::table('equipment_bookings')->lockForUpdate()->count(); // én booking ad gangen
            $items = Item::inUse()->whereIn('id', $d['equipment_ids'])->get();
            abort_if($items->count() !== count(array_unique($d['equipment_ids'])), 422, 'Noget af udstyret findes ikke længere.');
            if ($bad = $items->firstWhere('status', 'defect')) abort(422, $bad->name.' er defekt og kan ikke bookes.');

            $conflicts = Equipment::conflicts($items->pluck('id')->all(), $d['starts_at'], $d['ends_at'], null, $old?->id);
            foreach ($conflicts as $c) {
                if ($h = collect($c['holders'])->firstWhere('kind', 'booking')) {
                    abort(422, $c['item']['name'].' er allerede booket af '.$h['name'].' i tidsrummet.');
                }
            }
            $activities = collect($conflicts)->flatMap(fn ($c) => $c['holders'])->pluck('activity_id')->unique()->values();
            if ($activities->isNotEmpty() && ! ($d['request'] ?? false)) {
                return response()->json([
                    'message' => 'Noget af udstyret er booket til en aktivitet i tidsrummet.',
                    'conflicts' => Equipment::groupByHolder($conflicts),
                ], 409);
            }

            $b = EquipmentBooking::create(['user_id' => $u->id, 'starts_at' => $d['starts_at'], 'ends_at' => $d['ends_at'],
                'status' => $activities->isEmpty() ? 'confirmed' : 'requested', 'note' => $d['note'] ?? null]);
            $b->items()->sync($items->pluck('id'));
            foreach (Activity::with('trainers', 'creator')->whereIn('id', $activities)->get() as $a) {
                $b->approvals()->create(['activity_id' => $a->id]);
                Notification::send($a->contacts(), new ClubMessage('Ønske om udstyr: '.$a->title,
                    $u->name.' vil gerne låne '.$this->itemsFor($b, $a)->implode(', ').' under aktiviteten '
                    .$a->starts_at->locale('da')->isoFormat('D. MMM [kl.] HH:mm').'.'.($b->note ? ' "'.$b->note.'"' : '').' Svar ja eller nej på aktivitetens side.',
                    '/aktiviteter/'.$a->id));
            }
            Audit::log('equipment.booked', $b, ['status' => $b->status, 'items' => $items->pluck('name')->all(), 'replaces' => $old?->id]);
            if ($old) {
                $old->update(['status' => 'cancelled']);
                Audit::log('equipment.booking_moved', $old, ['to' => $b->id]);
                Equipment::closeMoves($old, 'done', $u);
            }

            return ['id' => $b->id, 'status' => $b->status];
        });
    }

    /** Det af bookingens udstyr, som aktiviteten har booket. */
    private function itemsFor(EquipmentBooking $b, Activity $a)
    {
        $ids = Equipment::activityItemIds($a);

        return $b->items()->get()->filter(fn ($i) => in_array($i->id, $ids))->pluck('name');
    }

    public function cancel(Request $r, EquipmentBooking $booking)
    {
        $u = $r->user();
        abort_unless(in_array($booking->status, ['confirmed', 'requested']), 422, 'Bookingen er allerede afsluttet.');
        abort_unless(Equipment::mayCancel($u, $booking), 403,
            'Du kan kun annullere andres bookinger, når de ikke har svaret på din anmodning om at flytte inden fristen.');
        $d = $r->validate(['reason' => 'nullable|string|max:500']);
        $own = $booking->user_id === $u->id;
        $overdue = $own ? null : Equipment::overdueRequestFor($u, $booking);

        DB::transaction(function () use ($booking, $u, $own) {
            $booking->update(['status' => 'cancelled']);
            Equipment::closeMoves($booking, $own ? 'done' : 'forced', $u);
        });
        Audit::log('equipment.booking_cancelled', $booking, ['by_other' => ! $own, 'reason' => $d['reason'] ?? null]);
        if (! $own) {
            $why = $overdue ? ' Udstyret skal bruges til "'.$overdue->activity->title.'", og du svarede ikke på anmodningen om at flytte bookingen inden fristen.' : '';
            $booking->user->notify(new ClubMessage('Din booking af udstyr er annulleret',
                $u->name.' har annulleret din booking '.Equipment::when($booking).'.'.$why.(! empty($d['reason']) ? ' "'.$d['reason'].'"' : ''), '/udstyr?vis=bookinger'));
        }

        return ['ok' => true];
    }

    /** Den, der har booket, siger nej til at flytte sin booking (bestyrelsen kan stadig annullere den). */
    public function replyMove(Request $r, EquipmentMoveRequest $move)
    {
        $u = $r->user();
        $move->load('booking.user', 'activity', 'requester');
        abort_unless($move->booking->user_id === $u->id, 403);
        abort_unless($move->status === 'pending', 422, 'Der er allerede svaret på anmodningen.');
        $d = $r->validate(['reply' => 'required|string|max:500'], [], ['reply' => 'begrundelse']);
        $move->update(['status' => 'declined', 'reply' => $d['reply'], 'answered_at' => now(), 'answered_by' => $u->id]);
        Audit::log('equipment.move_declined', $move->booking, ['activity_id' => $move->activity_id]);
        $move->requester?->notify(new ClubMessage('Nej til at flytte booking: '.$move->activity->title,
            $u->name.' kan ikke flytte sin booking '.Equipment::when($move->booking).': "'.$d['reply'].'" Skriv til '.$u->name
            .' eller bed bestyrelsen om hjælp.', '/aktiviteter/'.$move->activity_id));

        return ['ok' => true];
    }

    /* ---------- Ønsker om udstyr, som en aktivitet har booket ---------- */

    public static function mayDecide(User $u, Activity $a): bool
    {
        return $a->trainers->contains('id', $u->id) || $a->created_by === $u->id || $u->may('equipment.decide');
    }

    public function decide(Request $r, EquipmentBookingApproval $approval)
    {
        $u = $r->user();
        $approval->load('activity.trainers', 'booking.user', 'booking.items');
        abort_unless($this->mayDecide($u, $approval->activity), 403, 'Kun træneren eller tovholderen på aktiviteten kan svare.');
        $d = $r->validate(['accept' => 'required|boolean', 'reply' => 'nullable|string|max:500']);
        $b = $approval->booking;
        abort_unless($approval->status === 'pending' && $b->status === 'requested', 422, 'Der er allerede svaret på ønsket.');

        DB::transaction(function () use ($approval, $b, $d, $u) {
            $approval->update(['status' => $d['accept'] ? 'accepted' : 'rejected', 'reply' => $d['reply'] ?? null, 'decided_by' => $u->id, 'decided_at' => now()]);
            if (! $d['accept']) {
                $b->update(['status' => 'rejected']);
            } elseif (! $b->approvals()->where('status', '!=', 'accepted')->exists()) {
                // Alle aktiviteter har sagt ja – udstyret må ikke imens være booket af en anden
                $taken = collect(Equipment::conflicts($b->items->pluck('id')->all(), $b->starts_at, $b->ends_at, null, $b->id))
                    ->first(fn ($c) => collect($c['holders'])->contains('kind', 'booking'));
                abort_if($taken, 422, ($taken['item']['name'] ?? 'Udstyret').' er i mellemtiden booket af en anden.');
                $b->update(['status' => 'confirmed']);
            }
        });
        Audit::log('equipment.request_'.($d['accept'] ? 'accepted' : 'rejected'), $b, ['activity_id' => $approval->activity_id]);

        $when = $b->starts_at->locale('da')->isoFormat('D. MMM [kl.] HH:mm');
        $b->user->notify(new ClubMessage(
            $d['accept'] ? ($b->fresh()->status === 'confirmed' ? 'Ja til dit ønske om udstyr' : 'Delvist ja til dit ønske om udstyr') : 'Nej til dit ønske om udstyr',
            $u->name.' har sagt '.($d['accept'] ? 'ja' : 'nej').' til, at du låner udstyr '.$when.' ('.$approval->activity->title.').'
                .($d['reply'] ? ' "'.$d['reply'].'"' : '').($d['accept'] && $b->fresh()->status !== 'confirmed' ? ' Der mangler stadig svar fra en anden aktivitet.' : ''),
            '/udstyr?vis=bookinger'));

        return ['ok' => true, 'booking_status' => $b->fresh()->status];
    }
}
