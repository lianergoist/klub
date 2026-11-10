<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Gæster (prøvetræning/SUP-kursus) må kun se og betale deres egen regning – ikke medlemsdelen. */
class EnsureMember
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if($request->user()?->status === 'guest', 403, 'Denne del er kun for medlemmer. Meld dig ind for at få adgang.');

        return $next($request);
    }
}
