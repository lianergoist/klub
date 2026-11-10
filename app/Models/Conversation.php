<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['last_message_at' => 'datetime', 'is_group' => 'boolean'];

    /** Aktive deltagere (har ikke forladt samtalen). */
    public function members()
    {
        return $this->belongsToMany(User::class)->withPivot('last_read_message_id', 'left_at')->wherePivotNull('left_at')->withTimestamps();
    }

    public function messages() { return $this->hasMany(Message::class); }
    public function latestMessage() { return $this->hasOne(Message::class)->latestOfMany(); }

    public function scopeFor($q, User $u)
    {
        return $q->whereHas('members', fn ($q) => $q->where('users.id', $u->id));
    }

    public function involves(User $u): bool
    {
        return $this->members()->where('users.id', $u->id)->exists();
    }

    /** Find eller opret 1:1-samtale mellem to medlemmer. */
    public static function between(User $a, User $b): self
    {
        $key = min($a->id, $b->id).'-'.max($a->id, $b->id);
        $c = self::firstOrCreate(['pair_key' => $key], ['is_group' => false, 'created_by' => $a->id]);
        $c->members()->syncWithoutDetaching([$a->id, $b->id]);

        return $c;
    }

    /** Navn til visning: gruppens titel, ellers de andre deltageres fornavne. */
    public function displayName(User $viewer): string
    {
        $others = $this->members->where('id', '!=', $viewer->id);
        if (! $this->is_group) return $others->first()?->name ?? 'Tidligere medlem';
        if ($this->title) return $this->title;
        $names = $others->map(fn ($u) => explode(' ', $u->name)[0])->values();

        return $names->count() > 3 ? $names->take(3)->join(', ').' +'.($names->count() - 3) : $names->join(', ', ' og ');
    }
}
