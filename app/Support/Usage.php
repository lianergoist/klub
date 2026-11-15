<?php

namespace App\Support;

use App\Models\UsageEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Pseudonym brugslog (se usage_events). Gemmer aldrig navne, id'er eller det, brugeren har skrevet.
 * Fejl i selve logningen må aldrig vælte en forespørgsel.
 */
class Usage
{
    public const SESSION_HEADER = 'X-Usage-Session';

    /** Fælles felter for en forespørgsel: session, pseudonym, roller og platform. */
    public static function context(Request $request): array
    {
        $user = $request->user('sanctum');
        $session = (string) $request->header(self::SESSION_HEADER, '');

        return [
            'session' => preg_match('/^[A-Za-z0-9]{8,32}$/', $session) ? $session : null,
            // Pseudonymet skifter hver måned, så adfærd ikke kan følges over lang tid
            'who' => $user ? substr(hash_hmac('sha256', $user->id.'|'.now()->format('Y-m'), config('app.key')), 0, 16) : null,
            'roles' => $user ? ($user->status === 'guest' ? 'guest' : Str::limit(implode(',', $user->roles()), 100, '')) : null,
            'platform' => $request->header('X-Platform') === 'app' ? 'app' : 'web',
        ];
    }

    public static function log(array $row): void
    {
        try {
            UsageEvent::create($row);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
