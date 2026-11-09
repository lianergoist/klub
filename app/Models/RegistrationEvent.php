<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegistrationEvent extends Model
{
    protected $guarded = ["id"];

    public $timestamps = false;
    protected $casts = ["created_at" => "datetime"];
    public function by() { return $this->belongsTo(User::class, "by_user_id"); }
}
