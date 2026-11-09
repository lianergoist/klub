<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainerChange extends Model
{
    protected $guarded = ["id"];

    public $timestamps = false;
    protected $casts = ["created_at" => "datetime"];
    public function trainer() { return $this->belongsTo(User::class, "trainer_id"); }
    public function changedBy() { return $this->belongsTo(User::class, "changed_by"); }
}
