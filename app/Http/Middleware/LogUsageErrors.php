<?php

namespace App\Http\Middleware;

use App\Support\Usage;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logger API-svar, hvor brugeren løb ind i noget: valideringsfejl (422), ingen adgang (403),
 * ikke fundet (404), for mange forsøg (429) og serverfejl (5xx).
 * Kun feltnavne og systemets fejltekst gemmes – aldrig de indtastede værdier.
 */
class LogUsageErrors
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $status = $response->getStatusCode();
        if ($status < 403 || $request->is('api/usage')) return;

        $data = json_decode((string) $response->getContent(), true) ?: [];
        $meta = [];
        if ($status === 422 && ! empty($data['errors']) && is_array($data['errors'])) {
            $meta['fields'] = array_slice(array_keys($data['errors']), 0, 20);
        }
        if (! empty($data['message']) && is_string($data['message'])) {
            $meta['message'] = mb_substr($data['message'], 0, 200);
        }

        Usage::log(Usage::context($request) + [
            'type' => 'error',
            'path' => mb_substr($request->route()?->uri() ?? $request->path(), 0, 191),
            'target' => $request->method().' '.$status,
            'meta' => $meta ?: null,
        ]);
    }
}
