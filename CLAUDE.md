# Klubsystem

Se README.md for arkitektur, demo og drift. Funktionsbeskrivelsen ligger i `docs/Funktionsoversigt.md`. Nye spørgsmål, bestyrelsen skal tage stilling til, tilføjes dér som **Afklares:**-punkter i det relevante afsnit.

- Forretningslogik hører til i API'et (`app/Http/Controllers/Api`, `app/Support`), ikke i Vue-frontenden – appen skal senere pakkes med Capacitor og genbruge samme API.
- UI-tekster er på dansk. Beløb gemmes i øre (heltal).
- Regnskab: intet slettes; rettelser sker ved modpostering. Ændringer i medlemsdata, roller og økonomi logges via `App\Support\Audit`.
- Frontend bygges med `npm run build` (Vite 6 – serverens CPU understøtter ikke Vite 8/Rolldown).
