<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostLike extends Model
{
    protected $guarded = ["id"];

    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = null;
}
