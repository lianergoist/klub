<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['last_message_at' => 'datetime'];

    public function userA() { return $this->belongsTo(User::class, 'user_a_id'); }
    public function userB() { return $this->belongsTo(User::class, 'user_b_id'); }
    public function messages() { return $this->hasMany(Message::class); }
    public function latestMessage() { return $this->hasOne(Message::class)->latestOfMany(); }

    public function involves(User $u): bool { return in_array($u->id, [$this->user_a_id, $this->user_b_id]); }
    public function otherId(User $u): int { return $this->user_a_id === $u->id ? $this->user_b_id : $this->user_a_id; }
    public function other(User $u): User { return $this->user_a_id === $u->id ? $this->userB : $this->userA; }

    public static function between(User $a, User $b): self
    {
        [$x, $y] = $a->id < $b->id ? [$a->id, $b->id] : [$b->id, $a->id];

        return self::firstOrCreate(['user_a_id' => $x, 'user_b_id' => $y]);
    }

    public function scopeFor($q, User $u) { return $q->where(fn ($q) => $q->where('user_a_id', $u->id)->orWhere('user_b_id', $u->id)); }
}
