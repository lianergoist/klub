<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Hent MobilePays afregningsrapport hver morgen og afstem udbetalinger (kræver cron: * * * * * php artisan schedule:run)
\Illuminate\Support\Facades\Schedule::call(fn () => \App\Support\MobilePay\Settlements::fetchAndImport())
    ->dailyAt('06:30')->name('mobilepay-afregning')->withoutOverlapping();

// Påmindelser om anmodninger om at flytte bookinger og aktiviteter, der afventer (se App\Support\Reminders)
\Illuminate\Support\Facades\Schedule::call(fn () => \App\Support\Reminders::run())
    ->everyFifteenMinutes()->name('paamindelser')->withoutOverlapping();

// Videoer i opslag komprimeres i baggrunden (App\Jobs\ProcessVideo) af systemd-tjenesten klub-video-worker
// (testsiden: klub-test-video-worker), som kører `queue:work --queue=video`. Efter kodeændringer: php artisan queue:restart
