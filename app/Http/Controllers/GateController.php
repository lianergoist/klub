<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SiteGate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class GateController extends Controller
{
    public function show(Request $r)
    {
        if (! SiteGate::enabled() || SiteGate::valid($r)) {
            return redirect($this->target($r));
        }

        return view('gate', ['error' => null, 'to' => $this->target($r)]);
    }

    public function check(Request $r)
    {
        if (! password_verify((string) $r->input('code'), (string) config('app.access_hash'))) {
            return response()->view('gate', ['error' => 'Forkert adgangskode.', 'to' => $this->target($r)], 422);
        }
        $r->session()->regenerate();

        return redirect($this->target($r))->withCookie(
            Cookie::make(SiteGate::COOKIE, SiteGate::token(), 60 * 24 * 90, '/', null, $r->isSecure(), true, false, 'lax')
        );
    }

    /** Mobilappen: byt adgangskoden til et token, der sendes i headeren X-Site-Access. */
    public function appToken(Request $r)
    {
        if (! password_verify((string) $r->input('code'), (string) config('app.access_hash'))) {
            return response()->json(['message' => 'Forkert adgangskode.'], 422);
        }

        return ['token' => SiteGate::token()];
    }

    private function target(Request $r): string
    {
        $to = (string) $r->input('til', '/');

        return str_starts_with($to, '/') && ! str_starts_with($to, '//') && ! str_starts_with($to, '/adgang') ? $to : '/';
    }
}
