<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $guarded = ["id"];

    public $timestamps = false;
    protected $casts = ["changes" => "array", "created_at" => "datetime"];
    public function user() { return $this->belongsTo(User::class); }
}
