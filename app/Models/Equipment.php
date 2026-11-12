<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Equipment extends Model
{
    protected $table = 'equipment';
    protected $guarded = ['id'];
    protected $casts = ['archived_at' => 'datetime'];

    public const STATUS = ['ok' => 'OK', 'service' => 'Skal serviceres', 'defect' => 'Defekt'];

    public function type() { return $this->belongsTo(EquipmentType::class, 'equipment_type_id'); }
    public function events() { return $this->hasMany(EquipmentEvent::class)->latest('id'); }

    public function scopeInUse($q) { return $q->whereNull('equipment.archived_at'); }
}
