<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fælles adgangskode foran hele sitet (proof of concept).
 * Uden gyldig cookie: web -> /adgang, API -> 401.
 * Statiske filer (build, ikoner) serveres direkte af Apache og indeholder ingen data.
 */
class SiteGate
{
    public const COOKIE = 'klub_access';

    public function handle(Request $request, Closure $next): Response
    {
        if (! self::enabled() || $request->isMethod('OPTIONS') || $request->is('adgang', 'up', 'api/site-access') || self::valid($request)) {
            return $next($request);
        }

        if ($request->is('api/*')) {
            return response()->json(['message' => 'Adgangskoden til siden mangler.', 'gate' => true], 401);
        }

        return redirect('/adgang?til='.urlencode($request->getRequestUri()));
    }

    public static function enabled(): bool
    {
        return (bool) config('app.access_hash');
    }

    /** Token afhænger af den nuværende kode: skiftes koden, bliver alle gamle cookies ugyldige. */
    public static function token(): string
    {
        return hash_hmac('sha256', 'site-access|'.config('app.access_hash'), config('app.key'));
    }

    /** Gyldig via cookie (web) eller headeren X-Site-Access (mobilappen, som ikke kan bruge sitets cookie). */
    public static function valid(Request $request): bool
    {
        foreach ([$request->cookie(self::COOKIE), $request->header('X-Site-Access')] as $c) {
            if (is_string($c) && hash_equals(self::token(), $c)) return true;
        }

        return false;
    }
}
