<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $guarded = ["id"];

    public function invoice() { return $this->belongsTo(Invoice::class); }
    public function registeredBy() { return $this->belongsTo(User::class, "registered_by"); }
    public function approvedBy() { return $this->belongsTo(User::class, "approved_by"); }
}
