<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'signup_deadline' => 'datetime',
        'cancelled_at' => 'datetime',
        'members_only' => 'boolean',
        'punchcard' => 'boolean',
    ];

    public function categories() { return $this->belongsToMany(Category::class); }
    public function trainers() { return $this->belongsToMany(User::class, 'activity_trainer'); }
    public function trainerChanges() { return $this->hasMany(TrainerChange::class)->orderBy('created_at')->orderBy('id'); }
    public function registrations() { return $this->hasMany(Registration::class); }
    public function series() { return $this->belongsTo(ActivitySeries::class, 'series_id'); }
    /** Tilbud til ikke-medlemmer, aktiviteten er åben for (fx prøvetræning). */
    public function guestPackage() { return $this->belongsTo(Package::class, 'guest_package_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function equipment() { return $this->belongsToMany(Equipment::class, 'activity_equipment'); }
    public function equipmentApprovals() { return $this->hasMany(EquipmentBookingApproval::class); }

    public function isPending(): bool { return $this->status === 'pending'; }

    /** Træner(e) på aktiviteten – eller den, der oprettede den, hvis der ingen træner er. */
    public function contacts()
    {
        return $this->trainers->isNotEmpty() ? $this->trainers : collect([$this->creator])->filter();
    }

    /** Tilmeldte + igangværende betalinger (plads reserveres i 15 min). */
    public function takenSeats(): int
    {
        return $this->registrations()
            ->where(fn ($q) => $q->where('status', 'signed_up')
                ->orWhere(fn ($q) => $q->where('status', 'pending_payment')->where('updated_at', '>', now()->subMinutes(15))))
            ->count();
    }

    public function isFull(): bool
    {
        return $this->max_participants && $this->takenSeats() >= $this->max_participants;
    }

    public function deadlinePassed(): bool
    {
        return $this->signup_deadline && $this->signup_deadline->isPast();
    }

    public function cancelLocked(): bool
    {
        return now()->greaterThan($this->starts_at->copy()->subHours($this->cancel_hours));
    }
}
