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

    /**
     * Fjerner personoplysninger fra en fejltekst, før den gemmes: e-mails, telefonnumre, alt hvad brugeren
     * har indtastet i forespørgslen, og medlemmers navne (fx "Sofie Jensen har allerede Enkelt medlem").
     */
    public static function redact(string $text, ?Request $request = null): string
    {
        $text = preg_replace('/[^\s@]+@[^\s@]+\.[a-z]{2,}/iu', '[e-mail]', $text);
        $text = preg_replace('/\+?\d[\d \-]{6,}\d/u', '[nummer]', $text);

        if ($request) {
            $typed = collect(\Illuminate\Support\Arr::flatten($request->except(['password', 'password_confirmation'])))
                ->filter(fn ($v) => is_string($v) && mb_strlen(trim($v)) >= 2)
                ->map(fn ($v) => trim($v))->sortByDesc(fn ($v) => mb_strlen($v));
            foreach ($typed as $v) $text = str_ireplace($v, '[indtastet]', $text);
        }

        foreach (array_chunk(self::names(), 200) as $chunk) {
            $re = implode('|', array_map(fn ($n) => preg_quote($n, '/'), $chunk));
            $text = preg_replace('/(?<![\p{L}])('.$re.')(?![\p{L}])/u', '[navn]', $text);
        }

        return $text;
    }

    /** Fulde navne og fornavne (mindst 3 bogstaver) på alle brugere, længste først. Caches en time. */
    private static function names(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('usage-redact-names', 3600, function () {
            $names = [];
            foreach (\App\Models\User::query()->whereNotNull('name')->pluck('name') as $full) {
                $full = trim($full);
                if (mb_strlen($full) >= 3) $names[] = $full;
                $first = explode(' ', $full)[0];
                if (mb_strlen($first) >= 3) $names[] = $first;
            }
            $names = array_values(array_unique($names));
            usort($names, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

            return $names;
        });
    }

    public static function log(array $row): void
    {
        try {
            // Appens tidszone, ikke databasens CURRENT_TIMESTAMP (UTC), så server- og browserhændelser passer sammen
            UsageEvent::create($row + ['created_at' => now()]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
