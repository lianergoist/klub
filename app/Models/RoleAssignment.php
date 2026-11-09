<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoleAssignment extends Model
{
    protected $guarded = ["id"];

    protected $casts = ["starts_on" => "date", "ends_on" => "date"];
    public function user() { return $this->belongsTo(User::class); }
    public function grantedBy() { return $this->belongsTo(User::class, "granted_by"); }
}
