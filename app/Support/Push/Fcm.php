<?php

namespace App\Support\Push;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Afsendelse af push-beskeder via Firebase Cloud Messaging (HTTP v1).
 * Kræver en servicekonto-nøgle (JSON) fra Firebase – stien står i FCM_CREDENTIALS i .env. Nøglen må ikke i git.
 */
class Fcm
{
    public static function enabled(): bool
    {
        $f = config('services.fcm.credentials');

        return $f && is_readable($f);
    }

    private static function account(): array
    {
        return json_decode(file_get_contents(config('services.fcm.credentials')), true);
    }

    /** OAuth-token til Firebase: JWT signeret med servicekontoens nøgle, byttes hos Google. Genbruges i 50 min. */
    private static function accessToken(): string
    {
        return Cache::remember('fcm_access_token', 50 * 60, function () {
            $a = self::account();
            $b64 = fn ($s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
            $now = time();
            $jwt = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'.$b64(json_encode([
                'iss' => $a['client_email'], 'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => $a['token_uri'], 'iat' => $now, 'exp' => $now + 3600,
            ]));
            openssl_sign($jwt, $sig, $a['private_key'], OPENSSL_ALGO_SHA256);
            $r = Http::asForm()->timeout(10)->post($a['token_uri'], [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt.'.'.$b64($sig),
            ]);
            if (! $r->ok()) throw new \RuntimeException('Firebase-login fejlede: '.$r->body());

            return $r->json('access_token');
        });
    }

    /** Send til alle brugerens telefoner. Ugyldige adresser (afinstalleret app) slettes. */
    public static function sendToUser(int $userId, string $title, string $body, ?string $link = null): int
    {
        if (! self::enabled()) return 0;
        $tokens = DB::table('device_tokens')->where('user_id', $userId)->pluck('token');
        if ($tokens->isEmpty()) return 0;

        $project = self::account()['project_id'];
        $sent = 0;
        foreach ($tokens as $token) {
            $r = Http::withToken(self::accessToken())->timeout(10)
                ->post("https://fcm.googleapis.com/v1/projects/$project/messages:send", ['message' => [
                    'token' => $token,
                    'notification' => ['title' => $title, 'body' => mb_strimwidth($body, 0, 240, '…')],
                    'data' => ['link' => (string) $link],
                    'android' => ['priority' => 'high', 'notification' => ['channel_id' => 'klub', 'icon' => 'ic_stat_notify', 'color' => '#7e5a8c']],
                    'apns' => ['payload' => ['aps' => ['sound' => 'default']]],
                ]]);
            if ($r->ok()) {
                $sent++;
            } elseif (in_array($r->status(), [400, 404]) && preg_match('/UNREGISTERED|registration-token-not-registered|INVALID_ARGUMENT/', $r->body())) {
                DB::table('device_tokens')->where('token', $token)->delete();
            } else {
                Log::warning('Push fejlede', ['status' => $r->status(), 'body' => $r->body()]);
            }
        }

        return $sent;
    }
}
