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
    ];

    public function categories() { return $this->belongsToMany(Category::class); }
    public function trainers() { return $this->belongsToMany(User::class, 'activity_trainer'); }
    public function trainerChanges() { return $this->hasMany(TrainerChange::class)->orderBy('created_at')->orderBy('id'); }
    public function registrations() { return $this->hasMany(Registration::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }

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
