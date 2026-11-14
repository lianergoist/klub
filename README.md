# Klubsystem – proof of concept

Klubsystem til windsurfingklubben: medlemmer, aktiviteter, trænere, betaling og regnskab.
Funktionerne og de åbne spørgsmål til bestyrelsen er beskrevet i [`docs/Funktionsoversigt.md`](docs/Funktionsoversigt.md). Økonomidelens muligheder og begrænsninger – og hvornår et eksternt regnskabsprogram er nødvendigt – står i [`docs/Oekonomi.md`](docs/Oekonomi.md).

## Arkitektur

| Del | Teknologi |
|---|---|
| Server/API | Laravel 13 (PHP 8.5) på Apache, MySQL 8.4, login med Sanctum-tokens |
| App + web | Vue 3-webapp (PWA), bygget med Vite 6, serveret af Laravel |
| Native apps (senere) | Samme webapp pakket med Capacitor og bygget i Codemagic, mod samme API |

Al forretningslogik ligger på serveren (`app/Http/Controllers/Api`, `app/Support`). Webappen (`resources/js`) er kun brugerflade.
Derfor kan en native app eller en anden frontend genbruge API'et uden ændringer.

## Demo

Kører i demo-tilstand (`APP_DEMO=true` i `.env`): login-siden **i appen** viser hurtig-login for roller (webudgaven er offentlig, så der er hurtig-login slået fra – API'et svarer kun, når Origin er appens). Alle demo-brugere har adgangskoden `demo1234`.

Nulstil demodata:

    cd /var/www/klub && php artisan migrate:fresh --seed --force && chown -R www-data:www-data storage

## Medlemskab og betaling

- **Sæson**: træningssæsonen løber 1. maj – 30. april (`App\Support\Season`); regnskabsåret er kalenderåret. Kontingent og sæsonprodukter har perioden fx `2026/27`.
- **Priser** ligger i tabellen `prices` med `valid_from` (`App\Support\Prices`). Bestyrelse/kasserer redigerer dem under Indstillinger: en ny pris = en ny række med dato i dag eller senere; kommende priser kan slettes. Produkter peger på en priskode (`products.price_code`).
- **Kontingent pr. husstand** (`Prices::membership`): 1–2 personer enkeltkontingent hver (halv pris fra 1. august), 3 personer familie, 4+ familie (stor). Støttemedlemmer betaler for sig.
- **Indmeldelse** (`/bliv-medlem`): medlemmet oprettes som *afventer betaling* og sendes til betaling. Betalt => aktivt medlem (`App\Support\Memberships::onPaid`).
- **Tilføjelse af barn/partner**: der oprettes en regning på forskellen mellem den nye husstandspris og det betalte. Personerne gemmes på regningen (`invoices.payload`) og oprettes først ved betaling. Koster det intet ekstra, tilføjes de med det samme.
- **Gæster** (`/prov`): ikke-medlemmer køber prøvetræning eller SUP-kursus på aktiviteter med `guest_offer`. De oprettes med status `guest` (kun adgang til egen betaling, se `EnsureMember`). Ved indmeldelse med samme e-mail modregnes betalte gæstekøb fra de sidste 12 måneder (`Memberships::guestCredit`).
- **Udstyrsleje** prissættes samlet for de valgte i husstanden (`Prices::rental`) og gælder sæsonen ud.

## Opslagstavle

`/opslag`: aktive medlemmer deler tekst og op til 4 billeder, liker og kommenterer. Trænere og bestyrelse modererer (skjul/vis, afvis anmeldelser).
Billeder skaleres på telefonen og igen på serveren (max 1600 px, EXIF/GPS fjernes) og ligger i `storage/app/private/feed`. De vises via tidsbegrænsede signerede links.

## Farvetemaer

Appen har to temaer, som kan skiftes på login-siden, i sidemenuen (computer), under Profil → Mine data og under Mere (mobil):

- **Standard**: neutral blågrøn.
- **SurfHorsens**: farver, logo og skrifttype (Poppins) fra surfhorsens.dk. Sand `#e3d8d3`, lilla `#7e5a8c`, pink `#e665a1`, orange `#ff9933`, petrol `#65a4a3`.

Valget huskes pr. enhed. Et link med `?tema=klub` eller `?tema=standard` vælger tema direkte.
Temaet er defineret som CSS-variabler i `resources/css/app.css` (`html[data-tema=klub]`). Poppins er installeret lokalt (`@fontsource/poppins`), så der ikke hentes noget fra Google.
Diagramfarverne i klubtemaet er justeret en smule (mættere lilla, mørkere orange), så de kan skelnes af farveblinde.

## Hjemmesiden

Klubbens hjemmeside (kopi af Squarespace-siden surfhorsens.dk) ligger på forsiden og `/priser-og-medlemskab`, `/kalender`, `/kontakt`, `/read-me` (`SiteController`). Den er **ikke** en del af appen.

- Siderne (`resources/site/`) og filerne (`public/hjemmeside/`, kun det siderne bruger, billeder som WebP) bygges af `python3 -I tools/hjemmeside/import.py /var/www/surfhorsens /var/www/klub` ud fra HTTrack-kopien i `/var/www/surfhorsens`. Scriptet gør alle adresser lokale, sætter "Log ind" i menuen, lader "Meld mig ind!" gå til `/bliv-medlem` og erstatter kalenderen og prislisten med pladser til widgetten. Filer, som Squarespaces scripts henter undervejs, står i `tools/hjemmeside/extra-files.txt`.
- `public/hjemmeside/klub-widget.js` henter `GET /api/public/calendar` (kommende aktiviteter med "Vis i kalenderen på hjemmesiden") og `GET /api/public/prices` (pakker). Den kan bruges uændret på Squarespace-siden: `<script src="https://klub.rpix.dk/hjemmeside/klub-widget.js" defer></script>` + `<div data-klub="kalender"></div>` / `<div data-klub="priser"></div>`. CORS tillader surfhorsens.dk.
- Kendte rester fra Squarespace: nyhedsbrevsformularen på forsiden (virker ikke, henter Google reCAPTCHA), kortet på kontaktsiden (Google Maps) og kald til `/api/census/…` (Squarespace-statistik, giver 404).

## Adgangskode til sitet

**Slået fra 2026-11-14**, så hjemmesiden er offentlig (den gamle hash står som kommentar i `.env`). Den kan slås til igen med `php artisan site:code`.

Hele sitet (webapp og API) kan ligge bag en fælles adgangskode (`app/Http/Middleware/SiteGate.php`). Koden huskes i en cookie i 90 dage pr. enhed.
Kun en bcrypt-hash ligger i `.env` (`SITE_ACCESS_HASH`).

    php artisan site:code              # generér og sæt ny kode (alle skal indtaste den igen)
    php artisan site:code min-kode     # sæt en bestemt kode
    php artisan site:code --off        # fjern porten

Kør kommandoen som root (den skriver i `.env`), og kør derefter `chown -R www-data:www-data bootstrap/cache`.

## Mobilapp (Capacitor)

Appen er den samme Vue-app pakket med Capacitor (`android/`, `ios/`, `capacitor.config.json`). Den bygges med `npm run build:app` (indgang `resources/app/`, output `dist-app/`) og er **uden økonomi og gæstesider** – kassereren får et link, der åbner økonomien i browseren.

- API-adresse: `KLUB_API=https://klub.rpix.dk npm run build:app` (standard: klub.rpix.dk).
- Version: vises under "Log ud" (appen: version + buildnummer fra telefonen, web: version + byggedato). Ny version (fx 0.2) skal skrives tre steder: `version` i `package.json` (0.2.0), `versionName` i `android/app/build.gradle` og `MARKETING_VERSION` i `ios/App/App.xcodeproj/project.pbxproj`. Buildnummeret tæller Codemagic op af sig selv (`BUILD_NUMBER`; Android bruger mindst 2).
- Afprøvning på serveren: JDK 21 og Android SDK ligger i `/opt/android-sdk` med en emulator (`klub`). Byg en debug-APK i en kopi af repoet (`/root/klub-app`), fx `KLUB_API=https://klub.rpix.dk npm run build:app && npx cap sync android && cd android && ./gradlew assembleDebug`. Den seneste APK lægges i `public/app/` (ikke i git), så den kan hentes på https://klub.rpix.dk/app/.
- Sitets adgangskode (proof of concept) sendes som headeren `X-Site-Access` (hentes via `POST /api/site-access`), da appen ikke kan bruge sitets cookie. CORS tillader appens adresser og hjemmesidens domæne (`config/cors.php`).
- `npm run app:sync` = byg + kopiér til de indbyggede projekter. Ikoner og opstartsskærm er lavet ud fra `assets/` (klubbens logo).
- Codemagic: `codemagic.yaml` har en Android-testudgave (debug-APK) og en iOS-testudgave (TestFlight).
- **Push-beskeder** (Firebase Cloud Messaging, `@capacitor-firebase/messaging`): appen registrerer telefonen efter login (`POST /api/devices`), og alle notifikationer (`ClubMessage`, nye beskeder) sendes også som push via `PushChannel` → `App\Support\Push\Fcm`, hvis medlemmet har push slået til. Tryk på en besked åbner det rigtige sted. Kræver:
  - `android/app/google-services.json` og `ios/App/App/GoogleService-Info.plist` (fra Firebase, må gerne i git)
  - APNs-nøgle uploadet i Firebase, og Push Notifications slået til på app-id'et hos Apple
  - servicekonto-nøgle på serveren (**ikke i git**), sti i `.env`: `FCM_CREDENTIALS=/var/www/klub/storage/app/private/firebase.json`
- Mangler før udgivelse: universal/app links, sikker opbevaring af login, MobilePay-retur til appen og sletning af konto i appen (Apple-krav).

## Udvikling og fortrydelse

Hele opsætningen er et proof of concept, og der udvikles direkte på klub.rpix.dk (`main`). Testsitet test.klub.rpix.dk er nedlagt (november 2026).

- Kode fortrydes med git (`git revert`), derefter `npm run build` og `php artisan optimize`.
- Git ruller ikke databasen tilbage: før hver migration tages en sikkerhedskopi med `mysqldump`, og serveren tager selv en kopi hver 6. time (`/var/backups/klub`, 14 dage).
- Demodata kan altid nulstilles med kommandoen under Demo.

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
Bilag og billeder fra opslagstavlen ligger i `storage/app/private` og er dækket af serverbackuppen.

Se seneste kørsler: `journalctl -t klub-db-backup`

Genskab (overskriver den nuværende database!):

    zcat /var/backups/klub/klub-ÅÅÅÅMMDD-TTMM.sql.gz | mysql klub
