<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $guarded = ["id"];

    public function entries() { return $this->hasMany(Entry::class); }
}
