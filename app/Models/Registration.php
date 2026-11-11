<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Registration extends Model
{
    protected $guarded = ["id"];

    protected $casts = ["attended" => "boolean"];
    public function purchase() { return $this->belongsTo(Purchase::class); }
    public function activity() { return $this->belongsTo(Activity::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function invoice() { return $this->belongsTo(Invoice::class); }
    public function events() { return $this->hasMany(RegistrationEvent::class)->orderBy("created_at")->orderBy("id"); }
    public function log(string $action, ?User $by): void { $this->events()->create(["action" => $action, "by_user_id" => $by?->id, "created_at" => now()]); }
}
