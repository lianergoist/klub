<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Usage;
use Illuminate\Http\Request;

/** Modtager brugshændelser fra frontenden (sideskift, JavaScript-fejl og forløb) i små bundter. */
class UsageController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'events' => 'required|array|max:50',
            'events.*.type' => 'required|in:view,js_error,track',
            'events.*.path' => 'required|string|max:191',
            'events.*.target' => 'nullable|string|max:191',
            'events.*.meta' => 'nullable|array',
            'events.*.at' => 'nullable|integer',
        ]);

        $ctx = Usage::context($request);
        $now = now();
        foreach ($data['events'] as $e) {
            $meta = isset($e['meta']) ? array_slice($e['meta'], 0, 10) : null;
            $json = $meta ? json_encode($meta) : null;
            // Klientens tidspunkt bruges, hvis det er plausibelt (bundter sendes med forsinkelse)
            $at = isset($e['at']) ? \Illuminate\Support\Carbon::createFromTimestampMs($e['at']) : $now;
            if ($at->gt($now) || $at->lt($now->copy()->subHour())) $at = $now;

            Usage::log($ctx + [
                'type' => $e['type'],
                'path' => $e['path'],
                'target' => $e['target'] ?? null,
                'meta' => $json && strlen($json) <= 2000 ? $meta : null,
                'created_at' => $at,
            ]);
        }

        return response()->noContent();
    }
}
