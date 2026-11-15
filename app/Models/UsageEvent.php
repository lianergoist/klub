<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsageEvent extends Model
{
    protected $guarded = ['id'];

    public $timestamps = false;
    protected $casts = ['meta' => 'array', 'created_at' => 'datetime'];
}
