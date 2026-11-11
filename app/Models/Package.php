<?php

namespace App\Models;

use App\Support\Prices;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Package extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'gives_access' => 'boolean', 'requires_membership' => 'boolean', 'self_service' => 'boolean',
        'archived_at' => 'datetime',
    ];

    public const KINDS = ['membership' => 'Medlemskab', 'product' => 'Tilkøb'];

    public function slots() { return $this->hasMany(PackageSlot::class)->with('category'); }
    public function purchases() { return $this->hasMany(Purchase::class); }
    public function product() { return $this->belongsTo(Product::class); }
    public function renewsTo() { return $this->belongsTo(Package::class, 'renews_to_id'); }
    public function account() { return $this->belongsTo(Account::class); }

    public function priceCode(): string { return 'package.'.$this->id; }

    public function price($date = null): int { return Prices::get($this->priceCode(), $date); }

    public function isMembership(): bool { return $this->kind === 'membership'; }

    /**
     * Gyldighed, hvis pakken købes på $date: [fra, til] (til = null for engang).
     * Faste datoer: den periode (fx 1/5–30/4), der dækker datoen – eller null, hvis datoen ligger uden for perioden.
     */
    public function windowFor($date = null): ?array
    {
        $d = Carbon::parse($date ?? now())->startOfDay();

        return match ($this->period_type) {
            'once' => [$d, null],
            'days' => [$d, $d->copy()->addDays(max(1, (int) $this->period_days) - 1)],
            'dates' => $this->dateWindowContaining($d),
        };
    }

    /** Den faste periode, der starter i en given sæson/år (bruges ved fornyelse). */
    public function dateWindowStartingIn(int $year): array
    {
        $from = Carbon::parse($year.'-'.$this->date_from);
        $to = Carbon::parse($year.'-'.$this->date_to);
        if ($to->lt($from)) $to->addYear();

        return [$from, $to];
    }

    private function dateWindowContaining(Carbon $d): ?array
    {
        foreach ([$d->year - 1, $d->year] as $y) {
            [$from, $to] = $this->dateWindowStartingIn($y);
            if ($d->betweenIncluded($from, $to)) return [$d, $to];
        }

        return null;
    }

    /** Fx "Hver sæson 1. maj – 30. apr.", "30 dage fra køb" eller "Engangsbetaling" */
    public function periodLabel(): string
    {
        $md = fn ($s) => Carbon::parse('2000-'.$s)->locale('da')->isoFormat('D. MMM');

        return match ($this->period_type) {
            'once' => 'Engangsbetaling',
            'days' => $this->period_days == 1 ? '1 dag' : $this->period_days.' dage fra køb',
            'dates' => $md($this->date_from).' – '.$md($this->date_to),
        };
    }

    /** Fx "2 voksne og 1–3 børn", "1 person" eller "2–5 personer (højst 2 voksne)" */
    public function compositionLabel(): string
    {
        $range = fn ($a, $b) => $a == $b ? (string) $a : $a.'–'.$b;
        $total = $this->total_max === null ? $this->total_min.'+' : $range($this->total_min, $this->total_max);
        $persons = ($total === '1' ? '1 person' : $total.' personer');
        $limits = $this->slots->filter(fn ($s) => $s->min > 0 || ($this->total_max === null || $s->max < $this->total_max));
        if ($limits->isEmpty()) return $persons;
        $name = fn ($s, $n) => mb_strtolower($n == 1 ? $s->category->name : $s->category->plural);
        $parts = $this->slots->sortByDesc(fn ($s) => $s->category->min_age)->filter(fn ($s) => $s->max > 0)->map(fn ($s) => $range($s->min, $s->max).' '.$name($s, $s->max))->values();
        $sum = $this->slots->sum('min').'-'.$this->slots->sum('max');
        $exact = $sum === $this->total_min.'-'.$this->total_max || ($this->slots->sum('min') == $this->total_min && $this->slots->sum('max') == $this->total_max);

        return $exact ? $parts->join(', ', ' og ') : $persons.' ('.$parts->join(', ', ' og ').')';
    }

    /**
     * Passer personerne til pakken? Alder regnes på startdatoen.
     * $people: liste af ['name' => ..., 'birthdate' => 'Y-m-d'].
     * Returnerer null hvis ok, ellers en forklaring.
     */
    public function compositionError(array $people, $startDate = null): ?string
    {
        $start = Carbon::parse($startDate ?? now());
        $n = count($people);
        if ($n < $this->total_min || ($this->total_max !== null && $n > $this->total_max)) {
            return $this->name.' dækker '.$this->compositionLabel().', men du har valgt '.$n.'.';
        }
        $counts = [];
        foreach ($people as $p) {
            $age = isset($p['birthdate']) && $p['birthdate'] ? Carbon::parse($p['birthdate'])->diffInYears($start) : null;
            $age = $age === null ? null : (int) floor($age);
            $slot = $this->slots->first(fn ($s) => $s->category->fits($age));
            if (! $slot) return ($p['name'] ?? 'En person').($age === null ? ' mangler fødselsdato.' : " ($age år) passer ikke til ".$this->name.'.');
            $counts[$slot->person_category_id] = ($counts[$slot->person_category_id] ?? 0) + 1;
        }
        foreach ($this->slots as $s) {
            $c = $counts[$s->person_category_id] ?? 0;
            if ($c < $s->min || $c > $s->max) {
                $n = $s->min == $s->max ? $s->min : $s->min.'–'.$s->max;
                $what = fn ($k) => mb_strtolower($k == 1 ? $s->category->name : $s->category->plural);

                return $this->name.' kræver '.$n.' '.$what($s->max).", men der er $c.";
            }
        }

        return null;
    }
}
