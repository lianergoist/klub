<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Household extends Model
{
    protected $guarded = ["id"];

    public function payer() { return $this->belongsTo(User::class, "payer_id"); }
    public function members() { return $this->hasMany(User::class); }
}
