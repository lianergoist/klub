<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $guarded = ["id"];

    protected $casts = ["hidden_at" => "datetime"];
    public function user() { return $this->belongsTo(User::class); }
    public function images() { return $this->hasMany(PostImage::class)->orderBy("position"); }
    public function comments() { return $this->hasMany(PostComment::class)->orderBy("created_at"); }
    public function likes() { return $this->hasMany(PostLike::class); }
    public function reports() { return $this->hasMany(PostReport::class); }
}
