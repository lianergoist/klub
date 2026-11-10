<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $guarded = ["id"];

    protected $casts = ["due_on" => "date", "paid_at" => "datetime", "payload" => "array"];
    public function payer() { return $this->belongsTo(User::class, "payer_id"); }
    public function member() { return $this->belongsTo(User::class, "member_id"); }
    public function activity() { return $this->belongsTo(Activity::class); }
    public function product() { return $this->belongsTo(Product::class); }
    public function account() { return $this->belongsTo(Account::class); }
    public function payments() { return $this->hasMany(Payment::class); }
    public function displayStatus(): string { return $this->status === "open" && $this->due_on->isPast() && ! $this->due_on->isToday() ? "overdue" : $this->status; }
}
