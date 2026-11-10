<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Appen registrerer telefonens push-adresse efter login og fjerner den ved logud. */
class DeviceController extends Controller
{
    public function store(Request $r)
    {
        $d = $r->validate(['token' => 'required|string|max:512', 'platform' => 'required|in:android,ios']);
        DB::table('device_tokens')->updateOrInsert(['token' => $d['token']], [
            'user_id' => $r->user()->id, 'platform' => $d['platform'], 'last_seen_at' => now(), 'updated_at' => now(), 'created_at' => now(),
        ]);

        return ['ok' => true];
    }

    public function destroy(Request $r)
    {
        DB::table('device_tokens')->where('user_id', $r->user()->id)->where('token', (string) $r->input('token'))->delete();

        return ['ok' => true];
    }
}
