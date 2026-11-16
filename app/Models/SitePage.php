<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** En side på hjemmesiden, bygget af blokke (se App\Support\Site). */
class SitePage extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['blocks' => 'array', 'admin_only' => 'boolean'];

    /** Adressen på siden ('' = forsiden) */
    public function path(): string
    {
        return '/'.$this->slug;
    }
}
