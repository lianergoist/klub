<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $guarded = ["id"];

    protected $casts = ["active" => "boolean", "needs_resource" => "boolean"];
    public function resources() { return $this->hasMany(Resource::class); }
}
