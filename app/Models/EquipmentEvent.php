<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipmentEvent extends Model
{
    public const UPDATED_AT = null;
    protected $guarded = ['id'];

    public function user() { return $this->belongsTo(User::class); }
}
