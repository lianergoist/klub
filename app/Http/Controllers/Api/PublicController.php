<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Package;
use App\Support\Money;
use Illuminate\Support\Str;

/**
 * Offentlige data til klubbens hjemmeside (kalender og priser).
 * Kræver ikke login og må hentes fra hjemmesidens domæne (se config/cors.php).
 * Bruges af public/hjemmeside/klub-widget.js.
 */
class PublicController extends Controller
{
    /** Kommende aktiviteter, som er markeret "Vis i kalenderen på hjemmesiden". */
    public function calendar()
    {
        return Activity::with('categories', 'guestPackage')
            ->where('on_website', true)->where('status', 'active')->whereNull('cancelled_at')->whereNull('archived_at')
            ->where('ends_at', '>', now())->orderBy('starts_at')->limit(60)->get()
            ->map(function (Activity $a) {
                $open = ! $a->deadlinePassed() && ! $a->isFull() && $a->starts_at->isFuture();
                // Ikke-medlemmer booker via tilbuddet, klippekort via /klippekort, ellers logger man ind
                [$signup, $url] = match (true) {
                    $a->guest_package_id && $open => ['guest', '/prov?tilbud='.$a->guest_package_id.'&aktivitet='.$a->id],
                    $a->punchcard && $open => ['punchcard', '/klippekort'],
                    $open => ['member', '/aktiviteter/'.$a->id],
                    default => [null, null],
                };
                $price = $a->guest_package_id ? $a->guestPackage?->price() : $a->price;

                return [
                    'id' => $a->id,
                    'title' => $a->title,
                    'description' => $a->description ? Str::limit($a->description, 600) : null,
                    'starts_at' => $a->starts_at->toIso8601String(),
                    'ends_at' => $a->ends_at->toIso8601String(),
                    'location' => $a->location,
                    'categories' => $a->categories->pluck('name')->values(),
                    'price' => $price ?: null,
                    'price_fmt' => $price ? Money::fmt($price) : null,
                    'members_only' => $a->members_only && ! $a->guest_package_id,
                    'full' => $a->isFull(),
                    'free' => $a->max_participants ? max(0, $a->max_participants - $a->takenSeats()) : null,
                    'signup' => $signup,
                    'signup_url' => $url,
                ];
            });
    }

    /** Medlemskaber, tilkøb og tilbud til ikke-medlemmer, som de står under Indstillinger → Pakker. */
    public function prices()
    {
        $rows = Package::whereNull('archived_at')->where('self_service', true)->whereIn('kind', ['membership', 'product', 'offer'])
            ->orderBy('position')->orderBy('id')->get()
            ->map(fn (Package $p) => [
                'id' => $p->id,
                'kind' => $p->kind,
                'name' => $p->name,
                'description' => $p->description,
                'period_label' => $p->periodLabel(),
                'composition_label' => $p->compositionLabel(),
                'price' => $p->price(),
                'price_fmt' => Money::fmt($p->price()),
                'requires_membership' => (bool) $p->requires_membership,
                'guest_only' => (bool) $p->guest_only,
            ]);

        return [
            'memberships' => $rows->where('kind', 'membership')->values(),
            'products' => $rows->where('kind', 'product')->values(),
            'offers' => $rows->where('kind', 'offer')->values(),
            'signup_url' => '/bliv-medlem',
        ];
    }
}
