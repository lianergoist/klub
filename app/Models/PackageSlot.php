<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackageSlot extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    public function category() { return $this->belongsTo(PersonCategory::class, 'person_category_id'); }
}
