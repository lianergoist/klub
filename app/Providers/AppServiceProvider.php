<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Navngivne grænser tælles for sig. throttle:N,M uden navn deler tæller pr. IP på tværs af alle ruter,
        // så fx brugsloggen på login-siden ellers ville tælle med i login-grænsen.
        \Illuminate\Support\Facades\RateLimiter::for('usage', fn ($r) => \Illuminate\Cache\RateLimiting\Limit::perMinute(60)->by('usage|'.($r->user('sanctum')?->id ?: $r->ip())));
        \Illuminate\Support\Facades\RateLimiter::for('password-forgot', fn ($r) => [
            \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by('pwf|'.$r->ip()),
            \Illuminate\Cache\RateLimiting\Limit::perHour(3)->by('pwf-mail|'.mb_strtolower((string) $r->input('email'))),
        ]);
        \Illuminate\Support\Facades\RateLimiter::for('password-reset', fn ($r) => \Illuminate\Cache\RateLimiting\Limit::perMinute(10)->by('pwr|'.$r->ip()));
        \Illuminate\Support\Facades\RateLimiter::for('password-change', fn ($r) => \Illuminate\Cache\RateLimiting\Limit::perMinute(6)->by('pwc|'.($r->user()?->id ?: $r->ip())));

        // Glemt adgangskode: linket åbner frontendens side, og mailen er på dansk (se AuthController::forgot)
        \Illuminate\Auth\Notifications\ResetPassword::createUrlUsing(fn ($user, string $token) => rtrim(config('app.url'), '/')
            .'/nulstil-adgangskode?token='.$token.'&email='.urlencode($user->email));
        \Illuminate\Auth\Notifications\ResetPassword::toMailUsing(function ($user, string $token) {
            $minutes = config('auth.passwords.users.expire', 60);

            return (new \Illuminate\Notifications\Messages\MailMessage)
                ->subject('Nulstil din adgangskode')
                ->greeting('Hej '.explode(' ', (string) $user->name)[0])
                ->line('Vi har fået en anmodning om at nulstille adgangskoden til din konto i klubsystemet.')
                ->action('Vælg ny adgangskode', \Illuminate\Auth\Notifications\ResetPassword::$createUrlCallback
                    ? call_user_func(\Illuminate\Auth\Notifications\ResetPassword::$createUrlCallback, $user, $token) : url('/'))
                ->line("Linket virker i $minutes minutter og kan kun bruges én gang.")
                ->line('Har du ikke bedt om en ny adgangskode, kan du se bort fra denne mail. Din adgangskode er ikke ændret.');
        });
    }
}
