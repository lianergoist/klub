<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Invoice;
use App\Models\Registration;
use App\Models\User;
use App\Notifications\ClubMessage;
use App\Support\Audit;
use App\Support\Money;
use App\Support\Payments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class ActivityController extends Controller
{
    /** tab: upcoming | mine | attended | trainer */
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
            default => $q->where('ends_at', '>=', now())->orderBy('starts_at'),
        };

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
            'categories' => $a->categories->map->only('id', 'name', 'color'),
            'trainers' => $a->trainers->map->brief(),
        ];
    }

    public function show(Request $r, Activity $activity)
    {
        $u = $r->user();
        $activity->load('categories', 'trainers', 'trainerChanges.trainer', 'trainerChanges.changedBy', 'creator');
        $isStaff = $u->isTrainer();
        $regs = $activity->registrations()->with('user', 'events.by')->get();
        $managed = $u->managedMembers();

        return self::row($activity) + [
            'description' => $activity->description,
            'min_participants' => $activity->min_participants,
            'cancel_hours' => $activity->cancel_hours,
            'cancel_locked' => $activity->cancelLocked(),
            'deadline_passed' => $activity->deadlinePassed(),
            'is_full' => $activity->isFull(),
            'members_only' => $activity->members_only,
            'guest_offer' => $activity->guest_offer,
            'is_past' => $activity->ends_at->isPast(),
            'created_by' => $activity->creator?->name,
            // Medlemmer ser kun navne på deltagerlisten
            'participants' => $regs->where('status', 'signed_up')->map(fn ($reg) => [
                'id' => $reg->user_id, 'name' => $reg->user->name,
            ] + ($isStaff ? [
                'guest' => $reg->user->status === 'guest',
                'registration_id' => $reg->id, 'attended' => $reg->attended, 'age' => $reg->user->age(),
                'phone' => $reg->user->phone, 'emergency' => trim($reg->user->emergency_name.' '.$reg->user->emergency_phone) ?: null,
            ] : []))->sortBy('name')->values(),
            'household' => $managed->map(function ($m) use ($regs) {
                $reg = $regs->firstWhere('user_id', $m->id);

                return [
                    'id' => $m->id, 'name' => $m->name, 'age' => $m->age(),
                    'membership_valid' => $m->hasValidMembership(),
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
            'guest_offer' => 'nullable|in:trial,sup_course',
            'category_ids' => 'required|array|min:1',
            'category_ids.*' => 'exists:categories,id',
            'trainer_ids' => 'array',
            'trainer_ids.*' => 'exists:users,id',
            'notify' => 'boolean',
        ];
    }

    public function store(Request $r)
    {
        abort_unless($r->user()->isTrainer(), 403);
        $data = $r->validate($this->rules(), [], ['category_ids' => 'kategori']);

        $activity = DB::transaction(function () use ($r, $data) {
            $a = Activity::create(collect($data)->except('category_ids', 'trainer_ids', 'notify')->all() + ['created_by' => $r->user()->id]);
            $a->categories()->sync($data['category_ids']);
            $this->syncTrainers($a, $data['trainer_ids'] ?? [], $r->user());
            Audit::log('activity.created', $a);

            return $a;
        });

        if ($data['notify'] ?? true) {
            $this->notifyNew($activity);
        }

        return ['id' => $activity->id];
    }

    public function update(Request $r, Activity $activity)
    {
        abort_unless($r->user()->isTrainer(), 403);
        $data = $r->validate($this->rules());
        $before = $activity->only('starts_at', 'ends_at', 'location');

        DB::transaction(function () use ($r, $data, $activity) {
            $activity->update(collect($data)->except('category_ids', 'trainer_ids', 'notify')->all());
            $activity->categories()->sync($data['category_ids']);
            $this->syncTrainers($activity, $data['trainer_ids'] ?? [], $r->user());
            Audit::log('activity.updated', $activity);
        });

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
        abort_unless($r->user()->isTrainer(), 403);
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
        abort_unless($r->user()->isTrainer(), 403);
        $reason = $r->validate(['reason' => 'required|string|max:300'])['reason'];
        $activity->update(['cancelled_at' => now()]);
        Audit::log('activity.cancelled', $activity, ['reason' => $reason]);
        $this->notifyParticipants($activity, 'Aflyst: '.$activity->title, 'Aktiviteten er aflyst. Årsag: '.$reason.($activity->price ? ' Betalingen refunderes af kassereren.' : ''));

        return ['ok' => true];
    }

    public function attendance(Request $r, Activity $activity)
    {
        abort_unless($r->user()->isTrainer(), 403);
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
        abort_if($activity->deadlinePassed() && ! $u->isTrainer(), 422, 'Svarfristen er overskredet. Kontakt træneren.');

        $invoiceIds = [];
        DB::transaction(function () use ($activity, $data, $allowed, $u, &$invoiceIds) {
            Activity::whereKey($activity->id)->lockForUpdate()->first();
            foreach ($data['member_ids'] as $mid) {
                $m = $allowed[$mid] ?? abort(403, 'Du kan kun tilmelde dig selv og din husstand.');
                if ($activity->members_only && ! $m->hasValidMembership()) {
                    abort(422, $m->name.' har ikke betalt kontingent for sæson '.\App\Support\Season::label().'.');
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
        abort_if($activity->cancelLocked() && ! $u->isTrainer(), 422, 'Afmelding er lukket '.$activity->cancel_hours.' timer før start.');

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
        abort_unless($r->user()->isTrainer(), 403);
        $date = $r->query('date', now()->toDateString());

        return User::whereHas('roleAssignments', fn ($q) => $q->whereIn('role', ['trainer'])->where('starts_on', '<=', now())->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', now())))
            ->with(['unavailabilities' => fn ($q) => $q->where('from_date', '<=', $date)->where('to_date', '>=', $date)])
            ->orderBy('name')->get()->map(fn ($t) => [
                'id' => $t->id, 'name' => $t->name,
                'unavailable' => $t->unavailabilities->isNotEmpty(),
                'reason' => $t->unavailabilities->first()?->reason,
            ]);
    }

    private function notifyNew(Activity $a): void
    {
        $catIds = $a->categories->pluck('id');
        $users = User::whereIn('status', ['active'])->whereNotNull('email')
            ->whereDoesntHave('categorySettings', fn ($q) => $q->whereIn('categories.id', $catIds)->where('category_user.notify', false))->get();
        $deadline = $a->signup_deadline ? ' Svarfrist: '.$a->signup_deadline->locale('da')->isoFormat('D. MMM [kl.] HH:mm').'.' : '';
        Notification::send($users, new ClubMessage(
            'Ny aktivitet: '.$a->title,
            $a->starts_at->locale('da')->isoFormat('dddd D. MMMM [kl.] HH:mm').($a->location ? ', '.$a->location : '').'.'.$deadline,
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
