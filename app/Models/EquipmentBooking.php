<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipmentBooking extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime'];

    public const STATUS = ['confirmed' => 'Booket', 'requested' => 'Afventer svar', 'rejected' => 'Afvist', 'cancelled' => 'Annulleret'];

    public function user() { return $this->belongsTo(User::class); }
    public function items() { return $this->belongsToMany(Equipment::class, 'equipment_booking_items'); }
    public function approvals() { return $this->hasMany(EquipmentBookingApproval::class); }
}
