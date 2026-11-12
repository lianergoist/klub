<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipmentBookingApproval extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['decided_at' => 'datetime'];

    public function booking() { return $this->belongsTo(EquipmentBooking::class, 'equipment_booking_id'); }
    public function activity() { return $this->belongsTo(Activity::class); }
    public function decider() { return $this->belongsTo(User::class, 'decided_by'); }
}
