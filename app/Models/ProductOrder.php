<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductOrder extends Model
{
    protected $guarded = ["id"];

    protected $casts = ["valid_from" => "date", "valid_to" => "date"];
    public function user() { return $this->belongsTo(User::class); }
    public function product() { return $this->belongsTo(Product::class); }
    public function resource() { return $this->belongsTo(Resource::class); }
    public function invoice() { return $this->belongsTo(Invoice::class); }
}
