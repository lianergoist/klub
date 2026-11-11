<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonCategory extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['archived_at' => 'datetime'];

    public function fits(?int $age): bool
    {
        return $age !== null && $age >= $this->min_age && ($this->max_age === null || $age <= $this->max_age);
    }

    /** Fx "Barn (0–17 år)" eller "Voksen (18+ år)" */
    public function label(): string
    {
        return $this->name.' ('.$this->min_age.($this->max_age === null ? '+' : '–'.$this->max_age).' år)';
    }
}
