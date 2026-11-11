<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseClaim extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['handled_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }
    public function handler() { return $this->belongsTo(User::class, 'handled_by'); }
    public function entry() { return $this->belongsTo(Entry::class); }

    /** Fx "MobilePay 12 34 56 78" eller "Bank 1234 0012345678" */
    public function payoutLabel(): string
    {
        return $this->payout_method === 'mobilepay'
            ? 'MobilePay '.trim(chunk_split($this->mobilepay_phone, 2, ' '))
            : 'Bank reg. '.$this->bank_reg.' konto '.$this->bank_account;
    }
}
