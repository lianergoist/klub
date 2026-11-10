<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payout extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['payout_date' => 'date', 'bank_matched_at' => 'datetime'];

    public function payments() { return $this->hasMany(Payment::class); }
    public function feeEntry() { return $this->belongsTo(Entry::class, 'fee_entry_id'); }
}
