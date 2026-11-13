<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ActivitySeries;
use App\Models\Invoice;
use App\Models\Registration;
use App\Models\User;
use App\Notifications\ClubMessage;
use App\Support\Audit;
use App\Support\Equipment;
use App\Support\Money;
use App\Support\Payments;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class ActivityController extends Controller
{
    /** tab: upcoming | mine | attended | trainer | open (mangler træner) */
    public function index(Request $r)
    {
        $u = $r->user();
        $ids = $u->managedMembers()->pluck('id');
        $tab = $r->query('tab', 'upcoming');
        $q = Activity::with('categories', 'trainers')->withCount(['registrations as signed_up_count' => fn ($q) => $q->where('status', 'signed_up')]);

        match ($tab) {
            'mine' => $q->where('ends_at', '>=', now())->whereHas('registrations', fn ($q) => $q->whereIn('user_id', $ids)->whereIn('status', ['signed_up', 'pending_payment']))->orderBy('starts_at'),
            'attended' => $q->where('ends_at', '<', now())->whereHas('registrations', fn ($q) => $q->whereIn('user_id', $ids)->where('attended', true))->orderByDesc('starts_at'),
            'trainer' => $q->whereHas('trainers', fn ($q) => $q->where('users.id', $u->id))
                ->when($r->query('when') === 'past', fn ($q) => $q->where('ends_at', '<', now())->orderByDesc('starts_at'), fn ($q) => $q->where('ends_at', '>=', now())->orderBy('starts_at')),
            // Kommende aktiviteter uden træner – alle trænere kan tage dem
            'open' => $q->where('ends_at', '>=', now())->whereNull('cancelled_at')->whereDoesntHave('trainers')->orderBy('starts_at'),
            default => $q->where('ends_at', '>=', now())->orderBy('starts_at'),
        };

        $q->whereNull('archived_at'); // aflyst uden tilmeldte: vises ikke på listerne
        if (! $u->may('activities.manage')) {
            $q->where('status', 'active');
        }
        if ($cats = $r->query('categories')) {
            $q->whereHas('categories', fn ($q) => $q->whereIn('categories.id', explode(',', $cats)));
        }

        $mine = Registration::whereIn('user_id', $ids)->get()->groupBy('activity_id');

        return $q->limit(100)->get()->map(fn ($a) => self::row($a) + [
            'my_status' => optional($mine[$a->id] ?? null)->map(fn ($reg) => ['member_id' => $reg->user_id, 'status' => $reg->status, 'attended' => $reg->attended])?->values(),
        ]);
    }

    public static function row(Activity $a): array
    {
        return [
            'id' => $a->id,
            'title' => $a->title,
            'starts_at' => $a->starts_at->toIso8601String(),
            'ends_at' => $a->ends_at->toIso8601String(),
            'location' => $a->location,
            'max_participants' => $a->max_participants,
            'signed_up_count' => $a->signed_up_count ?? $a->registrations()->where('status', 'signed_up')->count(),
            'price' => $a->price,
            'price_fmt' => $a->price ? Money::fmt($a->price) : null,
            'signup_deadline' => $a->signup_deadline?->toIso8601String(),
            'cancelled' => (bool) $a->cancelled_at,
            'archived' => (bool) $a->archived_at,
            'categories' => $a->categories->map->only('id', 'name', 'color'),
            'trainers' => $a->trainers->map->brief(),
            'series_id' => $a->series_id,
            'pending' => $a->status === 'pending',
        ];
    }

    public function show(Request $r, Activity $activity)
    {
        $u = $r->user();
        $activity->load('categories', 'trainers', 'trainerChanges.trainer', 'trainerChanges.changedBy', 'creator', 'series');
        $isStaff = $u->may('activities.manage');
        abort_if($activity->isPending() && ! $isStaff, 404);
        $regs = $activity->registrations()->with('user', 'events.by')->get();
        // Trænere på aktiviteten tilmeldes ikke som deltagere. På andre aktiviteter tilmelder trænere sig som almindelige medlemmer.
        $managed = $u->managedMembers()->reject(fn ($m) => $activity->trainers->contains('id', $m->id))->values();

        return self::row($activity) + [
            'description' => $activity->description,
            'min_participants' => $activity->min_participants,
            'cancel_hours' => $activity->cancel_hours,
            'cancel_locked' => $activity->cancelLocked(),
            'deadline_passed' => $activity->deadlinePassed(),
            'is_full' => $activity->isFull(),
            'members_only' => $activity->members_only,
            'guest_package_id' => $activity->guest_package_id,
            'guest_offer_label' => $activity->guestPackage?->name,
            'punchcard' => $activity->punchcard,
            'is_past' => $activity->ends_at->isPast(),
            'status' => $activity->status,
            'created_by' => $activity->creator?->name,
            // Medlemmer ser kun navne på deltagerlisten
            'participants' => $regs->where('status', 'signed_up')->map(fn ($reg) => [
                'id' => $reg->user_id, 'name' => $reg->user->name,
            ] + ($isStaff ? [
                'guest' => $reg->user->status === 'guest',
                'registration_id' => $reg->id, 'attended' => $reg->attended, 'age' => $reg->user->age(),
                'phone' => $reg->user->phone, 'emergency' => trim($reg->user->emergency_name.' '.$reg->user->emergency_phone) ?: null,
                'emergency_name' => $reg->user->emergency_name, 'emergency_phone' => $reg->user->emergency_phone,
                // Børn uden eget nummer: husstandens betaler (typisk en forælder), hvis der ikke er en nødkontakt
                'parent' => ! $reg->user->phone && ! $reg->user->emergency_phone && ($pay = $reg->user->household?->payer) && $pay->id !== $reg->user_id && $pay->phone
                    ? ['name' => $pay->name, 'phone' => $pay->phone] : null,
            ] : []))->sortBy('name')->values(),
            'household' => $managed->map(function ($m) use ($regs) {
                $reg = $regs->firstWhere('user_id', $m->id);

                return [
                    'id' => $m->id, 'name' => $m->name, 'age' => $m->age(),
                    'membership_valid' => $m->hasValidMembership(true),
                    'status' => $reg?->status, 'invoice_id' => $reg?->invoice_id,
                    'history' => $reg ? self::history($reg) : [],
                ];
            })->values(),
            'trainer_log' => $isStaff ? $activity->trainerChanges->map(fn ($c) => [
                'action' => $c->action, 'trainer' => $c->trainer->name, 'by' => $c->changedBy?->name,
                'at' => $c->created_at->toIso8601String(),
            ]) : [],
            'registration_log' => $isStaff ? $regs->flatMap(fn ($reg) => collect(self::history($reg))->map(fn ($h) => $h + ['member' => $reg->user->name]))
                ->sortBy('at')->values() : [],
            'can_edit' => $isStaff,
            'is_leader' => $activity->trainers->contains('id', $u->id),
            'can_take' => $u->may('activities.take'),
            'equipment' => $isStaff || EquipmentController::mayDecide($u, $activity) ? $this->equipmentInfo($activity, $u) : null,
            'series' => $activity->series ? [
                'id' => $activity->series->id,
                'label' => $activity->series->label(),
                // Denne og senere gange, der ikke er aflyst
                'remaining' => $activity->series->activities()->whereNull('cancelled_at')->where('starts_at', '>=', $activity->starts_at)->count(),
            ] : null,
        ];
    }

    private static function history(Registration $reg): array
    {
        $labels = ['signup' => 'Tilmeldt', 'cancel' => 'Afmeldt', 'resignup' => 'Tilmeldt igen', 'paid' => 'Betalt', 'attended' => 'Mødt op', 'absent' => 'Ikke mødt', 'reserved' => 'Plads reserveret – afventer betaling'];

        return $reg->events->map(fn ($e) => [
            'action' => $e->action, 'label' => $labels[$e->action] ?? $e->action,
            'by' => $e->by?->name, 'at' => $e->created_at->toIso8601String(),
        ])->all();
    }

    private function rules(): array
    {
        return [
            'title' => 'required|string|max:150',
            'description' => 'nullable|string|max:5000',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'location' => 'nullable|string|max:150',
            'max_participants' => 'nullable|integer|min:1',
            'min_participants' => 'nullable|integer|min:1',
            'signup_deadline' => 'nullable|date|before_or_equal:starts_at',
            'cancel_hours' => 'integer|min:0|max:720',
            'price' => 'integer|min:0',
            'members_only' => 'boolean',
            'guest_package_id' => ['nullable', \Illuminate\Validation\Rule::exists('packages', 'id')->where('kind', 'offer')],
            'punchcard' => 'boolean',
            'category_ids' => 'required|array|min:1',
            'category_ids.*' => 'exists:categories,id',
            'trainer_ids' => 'array',
            'trainer_ids.*' => 'exists:users,id',
            'notify' => 'boolean',
            'equipment_mode' => 'in:all,selected',
            'equipment_ids' => 'array',
            'equipment_ids.*' => 'integer|exists:equipment,id',
            'force' => 'boolean',       // opret/gem selv om udstyret er booket
            'as_pending' => 'boolean',  // opret som "Afventer" (ikke åben for tilmelding)
            'request_moves' => 'boolean', // bed dem, der har booket udstyret, om at flytte deres booking
            'move_message' => 'nullable|string|max:500',
            'repeat_until' => 'nullable|date|after:starts_at|before_or_equal:'.now()->addYear()->toDateString(),
        ];
    }

    /** Arkiverede kategorier kan ikke vælges til nye aktiviteter, men en aktivitet må beholde dem, den har. */
    private function rejectArchived(array $ids, ?Activity $keep = null): void
    {
        $archived = \App\Models\Category::whereIn('id', $ids)->whereNotNull('archived_at')->pluck('id');
        if ($keep) {
            $archived = $archived->diff($keep->categories()->pluck('categories.id'));
        }
        abort_if($archived->isNotEmpty(), 422, 'En af kategorierne er arkiveret og kan ikke vælges.');
    }

    public function store(Request $r)
    {
        abort_unless($r->user()->may('activities.manage'), 403);
        $data = $r->validate($this->rules(), [], ['category_ids' => 'kategori']);
        // Kun åben for klippekort, hvis der findes et klippekort til en af kategorierne
        if (! empty($data['punchcard']) && ! array_intersect($data['category_ids'], \App\Support\Punchcards::categoryIds())) $data['punchcard'] = false;
        $this->rejectArchived($data['category_ids']);

        if (! empty($data['repeat_until'])) {
            return $this->storeSeries($r, $data);
        }

        $conflicts = $this->equipmentConflicts($data, $data['starts_at'], $data['ends_at']);
        if ($conflicts && empty($data['force']) && empty($data['as_pending'])) {
            return $this->conflictResponse([['date' => $data['starts_at'], 'holders' => $conflicts]]);
        }
        $data['status'] = ! empty($data['as_pending']) ? 'pending' : 'active';

        $activity = DB::transaction(function () use ($r, $data) {
            $a = Activity::create(collect($data)->except(self::NOT_COLUMNS)->all() + ['created_by' => $r->user()->id]);
            $a->categories()->sync($data['category_ids']);
            $a->equipment()->sync(($data['equipment_mode'] ?? 'all') === 'selected' ? ($data['equipment_ids'] ?? []) : []);
            $this->syncTrainers($a, $data['trainer_ids'] ?? [], $r->user());
            Audit::log('activity.created', $a);

            return $a;
        });

        if (($data['notify'] ?? true) && $activity->status === 'active') {
            $this->notifyNew($activity);
        }
        if (! empty($data['request_moves'])) {
            Equipment::requestMoves($activity, $r->user(), $data['move_message'] ?? null);
        }

        return ['id' => $activity->id, 'status' => $activity->status];
    }

    /**
     * Fast aktivitet: samme ugedag og tidspunkt hver uge fra start til og med repeat_until.
     * Hver gang oprettes som sin egen aktivitet. Svarfristen flyttes med, så den ligger lige så længe før start hver gang.
     */
    private function storeSeries(Request $r, array $data)
    {
        $start = Carbon::parse($data['starts_at']);
        $length = (int) $start->diffInMinutes(Carbon::parse($data['ends_at']));
        $deadlineOffset = ! empty($data['signup_deadline']) ? (int) Carbon::parse($data['signup_deadline'])->diffInMinutes($start) : null;
        $until = Carbon::parse($data['repeat_until'])->endOfDay();

        $dates = [];
        for ($d = $start->copy(); $d->lte($until); $d->addWeek()) {
            $dates[] = $d->copy();
        }
        abort_if(count($dates) > 60, 422, 'En fast aktivitet kan højst have 60 gange. Vælg en tidligere slutdato.');

        // Udstyr: tjek hver gang for sig. "Afventer" gælder kun de gange, hvor udstyret er booket.
        $pendingDates = []; $all = [];
        foreach ($dates as $d) {
            if ($c = $this->equipmentConflicts($data, $d, $d->copy()->addMinutes($length))) {
                $all[] = ['date' => $d->toIso8601String(), 'holders' => $c];
                $pendingDates[] = $d->toIso8601String();
            }
        }
        if ($all && empty($data['force']) && empty($data['as_pending'])) {
            return $this->conflictResponse($all);
        }
        if (empty($data['as_pending'])) $pendingDates = [];

        $series = DB::transaction(function () use ($r, $data, $dates, $length, $deadlineOffset, $start, $until, $pendingDates) {
            $series = ActivitySeries::create([
                'title' => $data['title'], 'weekday' => $start->isoWeekday(),
                'start_time' => $start->format('H:i'), 'end_time' => $start->copy()->addMinutes($length)->format('H:i'),
                'from_date' => $start->toDateString(), 'to_date' => end($dates)->toDateString(), 'created_by' => $r->user()->id,
            ]);
            $base = collect($data)->except(array_merge(self::NOT_COLUMNS, ['starts_at', 'ends_at', 'signup_deadline']))->all();
            foreach ($dates as $d) {
                $a = Activity::create($base + [
                    'series_id' => $series->id, 'starts_at' => $d, 'ends_at' => $d->copy()->addMinutes($length),
                    'signup_deadline' => $deadlineOffset !== null ? $d->copy()->subMinutes($deadlineOffset) : null,
                    'created_by' => $r->user()->id,
                    'status' => in_array($d->toIso8601String(), $pendingDates) ? 'pending' : 'active',
                ]);
                $a->categories()->sync($data['category_ids']);
                $a->equipment()->sync(($data['equipment_mode'] ?? 'all') === 'selected' ? ($data['equipment_ids'] ?? []) : []);
                $this->syncTrainers($a, $data['trainer_ids'] ?? [], $r->user());
            }
            Audit::log('activity_series.created', $series, ['count' => count($dates)]);

            return $series;
        });

        if (! empty($data['request_moves'])) {
            foreach ($series->activities()->get() as $a) Equipment::requestMoves($a, $r->user(), $data['move_message'] ?? null);
        }
        $first = $series->activities()->where('status', 'active')->first();
        if (($data['notify'] ?? true) && $first) {
            $this->notifyNew($first, $series);
        }

        return ['id' => $series->activities()->first()->id, 'series_id' => $series->id, 'count' => count($dates), 'pending' => count($pendingDates)];
    }

    /** Aktiviteterne en handling gælder: kun denne, eller denne og alle senere gange i serien, der ikke er aflyst. */
    private function scope(Activity $a, bool $series)
    {
        if (! $series || ! $a->series_id) {
            return collect([$a]);
        }

        return Activity::where('series_id', $a->series_id)->where('starts_at', '>=', $a->starts_at)->whereNull('cancelled_at')->orderBy('starts_at')->get();
    }

    /** En træner melder sig selv som træner på aktiviteten. */
    public function take(Request $r, Activity $activity)
    {
        $u = $r->user();
        abort_unless($u->may('activities.take'), 403, 'Du har ikke adgang til at melde dig som træner.');
        abort_if($activity->cancelled_at, 422, 'Aktiviteten er aflyst.');
        abort_if($activity->ends_at->isPast(), 422, 'Aktiviteten er afholdt.');
        if (! $activity->trainers()->whereKey($u->id)->exists()) {
            // Var træneren tilmeldt som deltager, afmeldes deltagelsen (åben betaling annulleres)
            if ($reg = $activity->registrations()->where('user_id', $u->id)->whereIn('status', ['signed_up', 'pending_payment'])->first()) {
                $reg->update(['status' => 'cancelled']);
                $reg->log('cancel', $u);
                if ($reg->invoice && $reg->invoice->status === 'open') {
                    $reg->invoice->update(['status' => 'cancelled']);
                }
            }
            $this->syncTrainers($activity, array_merge($activity->trainers()->pluck('users.id')->all(), [$u->id]), $u);
        }

        return $this->show($r, $activity->fresh());
    }

    /** En træner melder fra. Står aktiviteten derefter uden træner, får de andre trænere besked. */
    public function leave(Request $r, Activity $activity)
    {
        $u = $r->user();
        $series = $r->boolean('series');
        $orphaned = collect();
        foreach ($this->scope($activity, $series) as $a) {
            if (! $a->trainers()->whereKey($u->id)->exists()) continue;
            $this->syncTrainers($a, array_values(array_diff($a->trainers()->pluck('users.id')->all(), [$u->id])), $u);
            if (! $a->trainers()->exists() && $a->starts_at->isFuture()) {
                $orphaned->push($a);
            }
        }

        if ($orphaned->isNotEmpty()) {
            $first = $orphaned->first();
            $when = $orphaned->count() === 1
                ? $first->starts_at->locale('da')->isoFormat('dddd D. MMMM [kl.] HH:mm')
                : $orphaned->count().' gange fra '.$first->starts_at->locale('da')->isoFormat('D. MMMM');
            $others = User::whereIn('id', \App\Models\RoleAssignment::where('role', 'trainer')->where('starts_on', '<=', now())
                ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', now()))->select('user_id'))
                ->where('id', '!=', $u->id)->get();
            Notification::send($others, new ClubMessage('Mangler træner: '.$first->title, $u->name.' har meldt fra ('.$when.'). Kan du tage den?', '/aktiviteter/'.$first->id));
        }

        return $this->show($r, $activity->fresh());
    }

    public function update(Request $r, Activity $activity)
    {
        abort_unless($r->user()->may('activities.manage'), 403);
        $data = $r->validate($this->rules());
        // Kun åben for klippekort, hvis der findes et klippekort til en af kategorierne
        if (! empty($data['punchcard']) && ! array_intersect($data['category_ids'], \App\Support\Punchcards::categoryIds())) $data['punchcard'] = false;
        $this->rejectArchived($data['category_ids'], $activity);
        $before = $activity->only('starts_at', 'ends_at', 'location');
        // Udstyret tjekkes kun, når tid, kategorier eller udstyr ændres
        $activity->load('categories', 'equipment');
        $changed = Carbon::parse($data['starts_at'])->ne($activity->starts_at) || Carbon::parse($data['ends_at'])->ne($activity->ends_at)
            || collect($data['category_ids'])->sort()->values()->all() !== $activity->categories->pluck('id')->sort()->values()->all()
            || (isset($data['equipment_mode']) && ($data['equipment_mode'] !== $activity->equipment_mode
                || collect($data['equipment_ids'] ?? [])->sort()->values()->all() !== $activity->equipment->pluck('id')->sort()->values()->all()));
        if ($changed && ! $activity->isPending() && empty($data['force'])
            && ($c = $this->equipmentConflicts($data, $data['starts_at'], $data['ends_at'], $activity->id))) {
            return $this->conflictResponse([['date' => $data['starts_at'], 'holders' => $c]], true);
        }

        if (Carbon::parse($data['starts_at'])->ne($activity->starts_at)) {
            $data += ['pending_reminded_at' => null, 'pending_board_notified_at' => null]; // nyt tidspunkt: påmind igen
        }
        DB::transaction(function () use ($r, $data, $activity) {
            $activity->update(collect($data)->except(self::NOT_COLUMNS)->all());
            $activity->categories()->sync($data['category_ids']);
            if (isset($data['equipment_mode'])) {
                $activity->equipment()->sync($data['equipment_mode'] === 'selected' ? ($data['equipment_ids'] ?? []) : []);
            }
            $this->syncTrainers($activity, $data['trainer_ids'] ?? [], $r->user());
            Audit::log('activity.updated', $activity);
        });
        if (! empty($data['request_moves'])) {
            Equipment::requestMoves($activity->fresh(), $r->user(), $data['move_message'] ?? null);
        }

        $activity->refresh();
        if ($before['starts_at'] != $activity->starts_at || $before['location'] !== $activity->location) {
            $this->notifyParticipants($activity, 'Ændret: '.$activity->title,
                'Aktiviteten er flyttet til '.$activity->starts_at->locale('da')->isoFormat('dddd D. MMMM [kl.] HH:mm').($activity->location ? ', '.$activity->location : '').'.');
        }

        return ['id' => $activity->id];
    }

    /** Alle trænere kan skifte træner. Hver ændring logges. */
    public function setTrainers(Request $r, Activity $activity)
    {
        abort_unless($r->user()->may('activities.manage'), 403);
        $data = $r->validate(['trainer_ids' => 'array', 'trainer_ids.*' => 'exists:users,id']);
        $this->syncTrainers($activity, $data['trainer_ids'], $r->user());

        return $this->show($r, $activity->fresh());
    }

    private function syncTrainers(Activity $a, array $ids, User $by): void
    {
        $current = $a->trainers()->pluck('users.id')->all();
        $add = array_diff($ids, $current);
        $remove = array_diff($current, $ids);
        foreach ($remove as $id) {
            $a->trainerChanges()->create(['action' => 'removed', 'trainer_id' => $id, 'changed_by' => $by->id, 'created_at' => now()]);
        }
        foreach ($add as $id) {
            $a->trainerChanges()->create(['action' => 'added', 'trainer_id' => $id, 'changed_by' => $by->id, 'created_at' => now()]);
        }
        $a->trainers()->sync($ids);
    }

    public function cancel(Request $r, Activity $activity)
    {
        abort_unless($r->user()->may('activities.manage'), 403);
        $reason = $r->validate(['reason' => 'required|string|max:300', 'series' => 'boolean'])['reason'];
        $list = $this->scope($activity, $r->boolean('series'))->filter(fn ($a) => ! $a->cancelled_at);
        $archived = 0;
        foreach ($list as $a) {
            // Ingen tilmeldte: aktiviteten arkiveres og vises ikke som aflyst på listerne
            $empty = ! $a->registrations()->whereIn('status', ['signed_up', 'pending_payment'])->exists();
            $a->update(['cancelled_at' => now(), 'archived_at' => $empty ? now() : null]);
            $archived += $empty ? 1 : 0;
            Audit::log('activity.cancelled', $a, ['reason' => $reason, 'archived' => $empty]);
            $this->notifyParticipants($a, 'Aflyst: '.$a->title.' '.$a->starts_at->locale('da')->isoFormat('D. MMM'),
                'Aktiviteten er aflyst. Årsag: '.$reason.($a->price ? ' Betalingen refunderes af kassereren.' : ''));
        }

        return ['ok' => true, 'count' => $list->count(), 'archived' => $archived];
    }

    public function attendance(Request $r, Activity $activity)
    {
        abort_unless($r->user()->may('activities.manage'), 403);
        $data = $r->validate(['items' => 'array', 'items.*.registration_id' => 'integer', 'items.*.attended' => 'nullable|boolean']);
        foreach ($data['items'] as $i) {
            $reg = $activity->registrations()->find($i['registration_id']);
            if ($reg && $reg->attended !== $i['attended'] && $i['attended'] !== null) {
                $reg->update(['attended' => $i['attended']]);
                $reg->log($i['attended'] ? 'attended' : 'absent', $r->user());
            }
        }

        return $this->show($r, $activity);
    }

    public function signup(Request $r, Activity $activity)
    {
        $u = $r->user();
        $data = $r->validate(['member_ids' => 'required|array|min:1', 'member_ids.*' => 'integer']);
        $allowed = $u->managedMembers()->keyBy('id');

        abort_if($activity->cancelled_at, 422, 'Aktiviteten er aflyst.');
        abort_if($activity->isPending(), 422, 'Aktiviteten afventer og er ikke åben for tilmelding endnu.');
        abort_if($activity->deadlinePassed() && ! $u->may('activities.manage'), 422, 'Svarfristen er overskredet. Kontakt træneren.');

        $invoiceIds = [];
        DB::transaction(function () use ($activity, $data, $allowed, $u, &$invoiceIds) {
            Activity::whereKey($activity->id)->lockForUpdate()->first();
            foreach ($data['member_ids'] as $mid) {
                $m = $allowed[$mid] ?? abort(403, 'Du kan kun tilmelde dig selv og din husstand.');
                abort_if($activity->trainers()->whereKey($m->id)->exists(), 422, $m->name.' er træner på aktiviteten og skal ikke tilmeldes som deltager.');
                if ($activity->members_only && ! $m->hasValidMembership(true)) {
                    abort(422, $m->name.' har ikke et gyldigt medlemskab, der giver adgang til aktiviteter.');
                }
                $reg = Registration::firstOrNew(['activity_id' => $activity->id, 'user_id' => $m->id]);
                if ($reg->exists && $reg->status === 'signed_up') {
                    continue;
                }
                abort_if($activity->isFull(), 422, 'Der er ikke flere pladser.');

                $wasCancelled = $reg->exists && $reg->status === 'cancelled';
                if ($activity->price > 0) {
                    $inv = $reg->invoice_id ? Invoice::find($reg->invoice_id) : null;
                    if (! $inv || $inv->status !== 'open') {
                        $inv = Invoice::create([
                            'payer_id' => $u->id, 'member_id' => $m->id, 'kind' => 'activity',
                            'description' => $activity->title.' – '.$m->name, 'amount' => $activity->price,
                            'due_on' => now(), 'activity_id' => $activity->id, 'account_id' => FinanceController::accountId('1100'),
                        ]);
                    }
                    $reg->fill(['status' => 'pending_payment', 'invoice_id' => $inv->id]);
                    $reg->updated_at = now();
                    $reg->save();
                    $reg->log('reserved', $u);
                    $invoiceIds[] = $inv->id;
                } else {
                    $reg->fill(['status' => 'signed_up'])->save();
                    $reg->log($wasCancelled ? 'resignup' : 'signup', $u);
                    $m->payer()->notify(new ClubMessage('Tilmeldt: '.$activity->title, $m->name.' er tilmeldt '.$activity->starts_at->locale('da')->isoFormat('dddd D. MMMM [kl.] HH:mm').'.', '/aktiviteter/'.$activity->id));
                }
            }
        });

        // Flere familiemedlemmer = én samlet betaling er en senere forbedring; i PoC'en betales regningerne én ad gangen.
        return ['invoice_ids' => $invoiceIds];
    }

    public function cancelSignup(Request $r, Activity $activity)
    {
        $u = $r->user();
        $mid = $r->validate(['member_id' => 'required|integer'])['member_id'];
        abort_unless($u->managedMembers()->pluck('id')->contains($mid), 403);
        abort_if($activity->cancelLocked() && ! $u->may('activities.manage'), 422, 'Afmelding er lukket '.$activity->cancel_hours.' timer før start.');

        $reg = Registration::where('activity_id', $activity->id)->where('user_id', $mid)->firstOrFail();
        $reg->update(['status' => 'cancelled']);
        $reg->log('cancel', $u);
        if ($reg->invoice && $reg->invoice->status === 'open') {
            $reg->invoice->update(['status' => 'cancelled']);
        }
        $paid = $reg->invoice?->status === 'paid';
        $u->notify(new ClubMessage('Afmeldt: '.$activity->title, $reg->user->name.' er afmeldt.'.($paid ? ' Spørgsmålet om refusion afklares af bestyrelsen.' : ''), '/aktiviteter/'.$activity->id));

        return ['ok' => true];
    }

    /** Trænere der kan sættes på – utilgængelige markeres (og kan skjules i UI). */
    public function trainerOptions(Request $r)
    {
        abort_unless($r->user()->may('activities.manage'), 403);
        $date = $r->query('date', now()->toDateString());

        return User::whereIn('id', \App\Support\Permissions::userIds('activities.take'))
            ->with(['unavailabilities' => fn ($q) => $q->where('from_date', '<=', $date)->where('to_date', '>=', $date)])
            ->orderBy('name')->get()->map(fn ($t) => [
                'id' => $t->id, 'name' => $t->name,
                'unavailable' => $t->unavailabilities->isNotEmpty(),
                'reason' => $t->unavailabilities->first()?->reason,
            ]);
    }

    private const NOT_COLUMNS = ['category_ids', 'trainer_ids', 'notify', 'repeat_until', 'equipment_ids', 'force', 'as_pending', 'request_moves', 'move_message'];

    /** Hvem har det udstyr, aktiviteten vil booke, i tidsrummet? (samlet pr. aktivitet/booking) */
    private function equipmentConflicts(array $data, $start, $end, ?int $except = null): array
    {
        $ids = Equipment::itemIdsFor($data['equipment_mode'] ?? 'all', $data['category_ids'], $data['equipment_ids'] ?? []);

        return Equipment::groupByHolder(Equipment::conflicts($ids, $start, $end, $except));
    }

    private function conflictResponse(array $dates, bool $update = false)
    {
        return response()->json([
            'message' => 'Noget af udstyret er booket i tidsrummet.',
            'equipment_conflicts' => $dates, 'update' => $update,
        ], 409);
    }

    /** Udstyr på aktivitetens side: hvad den booker, konflikter (hvis den afventer) og ønsker om at låne. */
    private function equipmentInfo(Activity $a, User $u): array
    {
        $a->loadMissing('equipment.type.category');
        $mode = $a->equipment_mode;
        $ids = $a->isPending() || $a->cancelled_at
            ? Equipment::itemIdsFor($mode, $a->categories->pluck('id')->all(), $a->equipment->pluck('id')->all())
            : Equipment::activityItemIds($a);
        $items = \App\Models\Equipment::with('type')->whereIn('id', $ids)->orderByRaw('LENGTH(name), name')->get();
        $requests = $a->equipmentApprovals()->with('booking.user', 'booking.items')->latest()->get()
            ->filter(fn ($x) => $x->booking->ends_at->isFuture() || $x->status === 'pending');

        return [
            'mode' => $mode,
            'ids' => $a->equipment->pluck('id'),
            'summary' => $items->groupBy(fn ($i) => $i->type->name)->map(fn ($g, $t) => ['type' => $t, 'count' => $g->count(), 'names' => $g->pluck('name')])->values(),
            'released' => Equipment::releasedIds($a) ? \App\Models\Equipment::whereIn('id', Equipment::releasedIds($a))->pluck('name') : [],
            'conflicts' => $a->ends_at->isFuture() ? $this->withMoves(Equipment::groupByHolder(Equipment::conflicts($ids, $a->starts_at, $a->ends_at, $a->id)), $a, $u) : [],
            'can_request_moves' => $u->may('activities.manage') || EquipmentController::mayDecide($u, $a),
            'requests' => $requests->map(fn ($x) => (new EquipmentController)->approvalRow($x) + ['booking_status' => $x->booking->status])->values(),
            'can_decide' => EquipmentController::mayDecide($u, $a),
        ];
    }

    /** Bookinger i konflikten får status på anmodningen om at flytte og hvad brugeren må gøre. */
    private function withMoves(array $holders, Activity $a, User $u): array
    {
        $ids = collect($holders)->where('kind', 'booking')->pluck('booking_id');
        $bookings = \App\Models\EquipmentBooking::with('moveRequests')->whereIn('id', $ids)->get()->keyBy('id');

        return array_map(function ($h) use ($bookings, $a, $u) {
            if ($h['kind'] !== 'booking' || ! ($b = $bookings[$h['booking_id']] ?? null)) return $h;
            $m = $b->moveRequests->where('activity_id', $a->id)->sortByDesc('id')->first();

            return $h + [
                'move' => $m ? ['id' => $m->id, 'status' => $m->status, 'reply' => $m->reply, 'deadline_at' => $m->deadline_at->toIso8601String(),
                    'overdue' => $m->overdue(), 'at' => $m->created_at->toIso8601String()] : null,
                'can_cancel' => Equipment::mayCancel($u, $b),
            ];
        }, $holders);
    }

    /** Bed dem, der har booket aktivitetens udstyr, om at flytte deres booking (alle eller udvalgte). */
    public function requestMoves(Request $r, Activity $activity)
    {
        $u = $r->user();
        abort_unless($u->may('activities.manage') || EquipmentController::mayDecide($u, $activity), 403);
        abort_if($activity->cancelled_at || $activity->ends_at->isPast(), 422, 'Aktiviteten er aflyst eller overstået.');
        $d = $r->validate(['booking_ids' => 'nullable|array', 'booking_ids.*' => 'integer', 'message' => 'nullable|string|max:500']);
        $n = Equipment::requestMoves($activity, $u, $d['message'] ?? null, $d['booking_ids'] ?? null);
        abort_unless($n, 422, 'Der er ingen bookinger at sende en anmodning til – måske har de allerede fået en.');

        return ['sent' => $n];
    }

    /** "Afventer" → oprettet: åbner for tilmelding og sender evt. besked til medlemmerne. */
    public function activate(Request $r, Activity $activity)
    {
        abort_unless($r->user()->may('activities.manage'), 403);
        abort_unless($activity->isPending(), 422, 'Aktiviteten er allerede oprettet.');
        $d = $r->validate(['force' => 'boolean', 'notify' => 'boolean']);
        $activity->load('categories', 'equipment');
        $data = ['equipment_mode' => $activity->equipment_mode, 'category_ids' => $activity->categories->pluck('id')->all(), 'equipment_ids' => $activity->equipment->pluck('id')->all()];
        if (empty($d['force']) && ($c = $this->equipmentConflicts($data, $activity->starts_at, $activity->ends_at, $activity->id))) {
            return $this->conflictResponse([['date' => $activity->starts_at->toIso8601String(), 'holders' => $c]]);
        }
        $activity->update(['status' => 'active']);
        Audit::log('activity.activated', $activity);
        if ($d['notify'] ?? true) $this->notifyNew($activity);

        return ['ok' => true];
    }

    private function notifyNew(Activity $a, ?ActivitySeries $series = null): void
    {
        $catIds = $a->categories->pluck('id');
        // Har aktiviteten en kategori, der ikke kan fravælges, får alle aktive medlemmer besked
        $users = User::whereIn('status', ['active'])->whereNotNull('email')
            ->when(! $a->categories->contains('mandatory', true), fn ($q) => $q
                ->whereDoesntHave('categorySettings', fn ($q) => $q->whereIn('categories.id', $catIds)->where('category_user.notify', false)))->get();
        $deadline = $a->signup_deadline ? ' Svarfrist: '.$a->signup_deadline->locale('da')->isoFormat('D. MMM [kl.] HH:mm').'.' : '';
        Notification::send($users, new ClubMessage(
            ($series ? 'Ny fast aktivitet: ' : 'Ny aktivitet: ').$a->title,
            ($series ? $series->label() : $a->starts_at->locale('da')->isoFormat('dddd D. MMMM [kl.] HH:mm')).($a->location ? ', '.$a->location : '').'.'.($series ? '' : $deadline),
            '/aktiviteter/'.$a->id
        ));
    }

    private function notifyParticipants(Activity $a, string $title, string $body): void
    {
        $a->registrations()->where('status', 'signed_up')->with('user.household.payer')->get()
            ->map(fn ($reg) => $reg->user->payer())->unique('id')
            ->each->notify(new ClubMessage($title, $body, '/aktiviteter/'.$a->id));
    }
}
