<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Unavailability;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TrainerController extends Controller
{
    public function unavailability(Request $r)
    {
        abort_unless($r->user()->isTrainer(), 403);

        return $r->user()->unavailabilities()->where('to_date', '>=', now()->subMonths(1))->orderBy('from_date')->get()
            ->map(fn ($u) => ['id' => $u->id, 'from' => $u->from_date->toDateString(), 'to' => $u->to_date->toDateString(), 'reason' => $u->reason]);
    }

    public function addUnavailability(Request $r)
    {
        abort_unless($r->user()->isTrainer(), 403);
        $d = $r->validate(['from' => 'required|date', 'to' => 'required|date|after_or_equal:from', 'reason' => 'nullable|string|max:100']);
        $r->user()->unavailabilities()->create(['from_date' => $d['from'], 'to_date' => $d['to'], 'reason' => $d['reason'] ?? null]);

        // Advar om aktiviteter i perioden, hvor træneren allerede står på
        $clash = Activity::whereHas('trainers', fn ($q) => $q->where('users.id', $r->user()->id))
            ->whereDate('starts_at', '>=', $d['from'])->whereDate('starts_at', '<=', $d['to'])->whereNull('cancelled_at')->get(['id', 'title', 'starts_at']);

        return ['list' => $this->unavailability($r), 'clashes' => $clash];
    }

    public function deleteUnavailability(Request $r, Unavailability $unavailability)
    {
        abort_unless($unavailability->user_id === $r->user()->id, 403);
        $unavailability->delete();

        return $this->unavailability($r);
    }

    /** Samlet oversigt: hvem er til rådighed hvornår (trænere og bestyrelse). */
    public function availability(Request $r)
    {
        abort_unless($r->user()->isTrainer(), 403);
        $start = Carbon::parse($r->query('from', now()->startOfWeek()->toDateString()))->startOfDay();
        $days = collect(range(0, 27))->map(fn ($i) => $start->copy()->addDays($i));
        $end = $days->last();

        $trainers = User::whereHas('roleAssignments', fn ($q) => $q->where('role', 'trainer')->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', now())))
            ->with(['unavailabilities' => fn ($q) => $q->where('to_date', '>=', $start)->where('from_date', '<=', $end)])
            ->orderBy('name')->get();

        $acts = Activity::with('trainers:id')->whereNull('cancelled_at')->whereBetween('starts_at', [$start, $end->copy()->endOfDay()])->get();

        return [
            'days' => $days->map(fn ($d) => $d->toDateString()),
            'trainers' => $trainers->map(fn ($t) => [
                'id' => $t->id, 'name' => $t->name,
                'days' => $days->map(function ($d) use ($t, $acts) {
                    $un = $t->unavailabilities->first(fn ($u) => $u->from_date->lte($d) && $u->to_date->gte($d));
                    $busy = $acts->filter(fn ($a) => $a->starts_at->isSameDay($d) && $a->trainers->contains('id', $t->id))->pluck('title')->values();

                    return ['off' => (bool) $un, 'reason' => $un?->reason, 'busy' => $busy];
                }),
            ]),
        ];
    }
}
