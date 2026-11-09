# Klubsystem – proof of concept

Klubsystem til windsurfingklubben: medlemmer, aktiviteter, trænere, betaling og regnskab.
Funktionerne er beskrevet i `/root/Funktionsoversigt.md`.

## Arkitektur

| Del | Teknologi |
|---|---|
| Server/API | Laravel 13 (PHP 8.5) på Apache, MySQL 8.4, login med Sanctum-tokens |
| App + web | Vue 3-webapp (PWA), bygget med Vite 6, serveret af Laravel |
| Native apps (senere) | Samme webapp pakket med Capacitor og bygget i Codemagic, mod samme API |

Al forretningslogik ligger på serveren (`app/Http/Controllers/Api`, `app/Support`). Webappen (`resources/js`) er kun brugerflade.
Derfor kan en native app eller en anden frontend genbruge API'et uden ændringer.

## Demo

Kører i demo-tilstand (`APP_DEMO=true` i `.env`): login-siden viser hurtig-login for seks roller. Alle demo-brugere har adgangskoden `demo1234`.

Nulstil demodata:

    cd /var/www/klub && php artisan migrate:fresh --seed --force && chown -R www-data:www-data storage

## Adgangskode til sitet

Hele sitet (webapp og API) ligger bag en fælles adgangskode (`app/Http/Middleware/SiteGate.php`). Koden huskes i en cookie i 90 dage pr. enhed.
Kun en bcrypt-hash ligger i `.env` (`SITE_ACCESS_HASH`).

    php artisan site:code              # generér og sæt ny kode (alle skal indtaste den igen)
    php artisan site:code min-kode     # sæt en bestemt kode
    php artisan site:code --off        # fjern porten

Kør kommandoen som root (den skriver i `.env`), og kør derefter `chown -R www-data:www-data bootstrap/cache`.

## Efter ændringer i koden

    npm run build                     # frontend
    php artisan optimize              # cache config/ruter (production)

Bemærk: Serverens CPU mangler AVX2, så projektet bruger Vite 6 (Rollup) i stedet for Vite 8 (Rolldown).
PHP under Apache kører med `pcre.jit=0` (`/etc/php/8.5/apache2/conf.d/99-klub.ini`).

## Bevidst forenklet i PoC'en

- MobilePay er simuleret (`app/Support/Payments.php::markPaid` er det sted, Vipps MobilePay-webhooket skal kalde).
- E-mails skrives til `storage/logs/laravel.log` (`MAIL_MAILER=log`) i stedet for at blive sendt.
- Push-beskeder, venteliste, gentagne aktiviteter, udlæg og to-trins-login er ikke med endnu.

## Backup af databasen

`/usr/local/sbin/klub-db-backup` tager et konsistent `mysqldump` hver 6. time (cron: `/etc/cron.d/klub-db-backup`, kl. 00:15, 06:15, 12:15, 18:15 UTC).
Dumps gemmes i `/var/backups/klub` (kun root) og kommer med i udbyderens daglige serverbackup. Dumps ældre end 14 dage slettes automatisk.
Bilag ligger i `storage/app/private` og er dækket af serverbackuppen.

Se seneste kørsler: `journalctl -t klub-db-backup`

Genskab (overskriver den nuværende database!):

    zcat /var/backups/klub/klub-ÅÅÅÅMMDD-TTMM.sql.gz | mysql klub
