<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Budget extends Model
{
    protected $guarded = ["id"];

    public $timestamps = false;
    public function account() { return $this->belongsTo(Account::class); }
}
