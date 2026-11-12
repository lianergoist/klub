<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipmentType extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['archived_at' => 'datetime'];

    public function category() { return $this->belongsTo(Category::class); }
    public function equipment() { return $this->hasMany(Equipment::class); }
}
