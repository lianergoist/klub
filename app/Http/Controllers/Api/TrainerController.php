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

    /**
     * Samlet oversigt: hvem er til rådighed hvornår (trænere).
     * Viser én måned ad gangen. ?month=2026-11 vælger måneden (standard: aktuel måned).
     */
    public function availability(Request $r)
    {
        abort_unless($r->user()->isTrainer(), 403);
        $month = $r->query('month');
        $start = (is_string($month) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) ? Carbon::parse($month.'-01') : now())->startOfMonth()->startOfDay();
        $end = $start->copy()->endOfMonth()->startOfDay();
        $days = collect();
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $days->push($d->copy());
        }

        $trainers = User::whereHas('roleAssignments', fn ($q) => $q->where('role', 'trainer')->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', now())))
            ->with(['unavailabilities' => fn ($q) => $q->where('to_date', '>=', $start)->where('from_date', '<=', $end)])
            ->orderBy('name')->get();

        $acts = Activity::with('trainers:id')->whereNull('cancelled_at')->whereBetween('starts_at', [$start, $end->copy()->endOfDay()])->get()
            ->groupBy(fn ($a) => $a->starts_at->toDateString());

        return [
            'month' => $start->format('Y-m'),
            'prev' => $start->copy()->subMonth()->format('Y-m'),
            'next' => $start->copy()->addMonth()->format('Y-m'),
            'days' => $days->map(fn ($d) => $d->toDateString()),
            // Aktiviteter uden træner pr. dag (vises med rødt i datokolonnen)
            'open' => $days->map(fn ($d) => ($acts[$d->toDateString()] ?? collect())->filter(fn ($a) => $a->trainers->isEmpty())
                ->map(fn ($a) => $a->starts_at->format('H.i').' '.$a->title)->values()),
            'trainers' => $trainers->map(fn ($t) => [
                'id' => $t->id, 'name' => $t->name,
                'days' => $days->map(function ($d) use ($t, $acts) {
                    $un = $t->unavailabilities->first(fn ($u) => $u->from_date->lte($d) && $u->to_date->gte($d));
                    $busy = ($acts[$d->toDateString()] ?? collect())->filter(fn ($a) => $a->trainers->contains('id', $t->id))->pluck('title')->values();

                    return ['off' => (bool) $un, 'reason' => $un?->reason, 'busy' => $busy];
                }),
            ]),
        ];
    }
}
