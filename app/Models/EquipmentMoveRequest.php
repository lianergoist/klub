<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipmentMoveRequest extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['deadline_at' => 'datetime', 'answered_at' => 'datetime'];

    public const STATUS = ['pending' => 'Venter på svar', 'declined' => 'Sagt nej', 'done' => 'Flyttet', 'forced' => 'Annulleret efter fristen', 'withdrawn' => 'Trukket tilbage'];

    public function booking() { return $this->belongsTo(EquipmentBooking::class, 'equipment_booking_id'); }
    public function activity() { return $this->belongsTo(Activity::class); }
    public function requester() { return $this->belongsTo(User::class, 'requested_by'); }

    public function overdue(): bool { return $this->status === 'pending' && $this->deadline_at->isPast(); }
}
