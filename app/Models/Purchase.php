<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['valid_from' => 'date', 'valid_to' => 'date', 'cancelled_at' => 'datetime'];

    public function package() { return $this->belongsTo(Package::class); }
    public function payer() { return $this->belongsTo(User::class, 'payer_id'); }
    public function invoice() { return $this->belongsTo(Invoice::class); }
    public function resource() { return $this->belongsTo(Resource::class); }
    public function users() { return $this->belongsToMany(User::class); }

    /** Betalt (eller uden regning, fx tildelt gratis) og ikke annulleret. */
    public function isPaid(): bool
    {
        return ! $this->cancelled_at && (! $this->invoice_id || $this->invoice?->status === 'paid');
    }

    /** Køb, der gælder på en dato: betalt, ikke annulleret og inden for perioden. */
    public function scopeActiveOn($q, $date = null)
    {
        $d = \Illuminate\Support\Carbon::parse($date ?? now())->toDateString();

        return $q->whereNull('cancelled_at')
            ->where(fn ($q) => $q->whereNull('invoice_id')->orWhereHas('invoice', fn ($q) => $q->where('status', 'paid')))
            ->where('valid_from', '<=', $d)->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $d));
    }

    /** Køb, der ikke er annulleret eller udløbet (inkl. ubetalte) – til overlap-tjek. */
    public function scopeCurrentOrFuture($q)
    {
        return $q->whereNull('cancelled_at')
            ->where(fn ($q) => $q->whereNull('invoice_id')->orWhereHas('invoice', fn ($q) => $q->whereIn('status', ['open', 'paid'])))
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', now()->toDateString()));
    }
}
