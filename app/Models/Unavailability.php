<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Unavailability extends Model
{
    protected $guarded = ["id"];

    protected $casts = ["from_date" => "date", "to_date" => "date"];
    public function user() { return $this->belongsTo(User::class); }
}
