<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivitySeries extends Model
{
    protected $table = 'activity_series';

    protected $guarded = ['id'];

    protected $casts = ['from_date' => 'date', 'to_date' => 'date'];

    public function activities() { return $this->hasMany(Activity::class, 'series_id')->orderBy('starts_at'); }

    /** Fx "Hver onsdag kl. 18:00–20:00 · 6. maj – 30. sep. 2026" */
    public function label(): string
    {
        $day = ['', 'mandag', 'tirsdag', 'onsdag', 'torsdag', 'fredag', 'lørdag', 'søndag'][$this->weekday];

        $t = fn ($time) => str_replace(':', '.', substr($time, 0, 5));

        return 'Hver '.$day.' kl. '.$t($this->start_time).'–'.$t($this->end_time)
            .' · '.$this->from_date->locale('da')->isoFormat('D. MMM').' – '.$this->to_date->locale('da')->isoFormat('D. MMM YYYY');
    }
}
