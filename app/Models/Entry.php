<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Entry extends Model
{
    protected $guarded = ["id"];

    protected $casts = ["entry_date" => "date"];
    public function account() { return $this->belongsTo(Account::class); }
    public function activity() { return $this->belongsTo(Activity::class); }
    public function payment() { return $this->belongsTo(Payment::class); }
    public function creator() { return $this->belongsTo(User::class, "created_by"); }
    public function reverses() { return $this->belongsTo(Entry::class, "reverses_id"); }
    public function reversedBy() { return $this->hasOne(Entry::class, "reverses_id"); }
}
