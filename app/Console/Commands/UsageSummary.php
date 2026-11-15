<?php

namespace App\Console\Commands;

use App\Models\UsageEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Sammenfatter brugsloggen (usage_events) til en kort Markdown-fil, som et menneske eller en AI kan læse
 * for at finde steder, hvor medlemmerne går i stå. Gemmes i storage/app/private/usage/.
 * Indeholder ingen personoplysninger: kun ruteskabeloner, roller, platform og rensede fejltekster.
 */
class UsageSummary extends Command
{
    protected $signature = 'usage:summary {--days=7 : Antal dage bagud} {--stdout : Skriv til skærmen i stedet for en fil}';

    protected $description = 'Ugentlig sammenfatning af brugsloggen (forløb, fejl og mulige dead-ends)';

    private const STORIES = 25;

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $to = now();
        $from = $to->copy()->subDays($days);

        $events = UsageEvent::where('created_at', '>=', $from)->orderBy('created_at')->orderBy('id')->get();
        $sessions = $events->whereNotNull('session')->groupBy('session');

        $md = [];
        $md[] = '# Brugslog: '.$from->format('d.m.Y').' – '.$to->format('d.m.Y');
        $md[] = '';
        $md[] = '_Sådan læses filen:_ Data er pseudonyme. Stier er ruteskabeloner fra appen (fx `/aktiviteter/:id`) eller API\'et (fx `api/activities/{activity}/signup`). '
            .'Et forløb er en browserfane eller en app-start. Forløb (`tilmelding.start`, `tilmelding.ok` …) registreres i frontenden. Fejl er API-svar med status 403 eller derover. '
            .'Navne og indtastede værdier er fjernet og står som `[navn]` eller `[indtastet]`. Rollerne er member (medlem), trainer, board, treasurer, auditor, admin og guest (ikke-medlem).';
        $md[] = '';

        if ($events->isEmpty()) {
            $md[] = 'Ingen hændelser i perioden.';
            return $this->output($md, $to);
        }

        $this->overview($md, $events, $sessions);
        $this->flows($md, $events, $sessions);
        $this->deadEnds($md, $sessions);
        $this->errors($md, $events);
        $this->pages($md, $events);
        $this->help($md, $events);
        $this->stories($md, $sessions);

        return $this->output($md, $to);
    }

    private function overview(array &$md, Collection $events, Collection $sessions): void
    {
        $md[] = '## Overblik';
        $md[] = '- Hændelser: '.$events->count().', forløb: '.$sessions->count().', forskellige brugere (pseudonymer): '.$events->whereNotNull('who')->pluck('who')->unique()->count();
        $md[] = '- Platform (forløb): '.$this->counts($sessions->map(fn ($s) => $s->first()->platform ?? '?'));
        $md[] = '- Roller (forløb med login): '.$this->counts($sessions->map(fn ($s) => $s->whereNotNull('roles')->first()?->roles)->filter());
        $md[] = '- Typer: '.$this->counts($events->pluck('type'));
        $md[] = '';
    }

    /** Tragte: hvor mange forløb nåede hvert trin, og hvor stor en andel gennemførte. */
    private function flows(array &$md, Collection $events, Collection $sessions): void
    {
        $tracks = $events->where('type', 'track')->filter(fn ($e) => str_contains((string) $e->target, '.'));
        if ($tracks->isEmpty()) return;

        $md[] = '## Forløb (tragte)';
        $md[] = 'Antal forløb, der nåede hvert trin. "gennemført" = andel af forløb med `start`, der også nåede `ok`.';
        $md[] = '';
        $md[] = '| Forløb | Trin (antal forløb) | Gennemført |';
        $md[] = '| - | - | - |';
        foreach ($tracks->groupBy(fn ($e) => explode('.', $e->target)[0]) as $flow => $rows) {
            $bySession = $rows->groupBy(fn ($e) => $e->session ?? 'x'.$e->id);
            $steps = $rows->groupBy(fn ($e) => explode('.', $e->target, 2)[1])->map(fn ($r) => $r->pluck('session')->unique()->count())->sortDesc();
            $started = $bySession->filter(fn ($r) => $r->contains(fn ($e) => str_ends_with($e->target, '.start')));
            $rate = $started->count()
                ? round(100 * $started->filter(fn ($r) => $r->contains(fn ($e) => str_ends_with($e->target, '.ok')))->count() / $started->count()).' %'
                : '–';
            $md[] = "| $flow | ".$steps->map(fn ($n, $k) => "$k $n")->implode(', ')." | $rate |";
        }
        $md[] = '';
    }

    private function deadEnds(array &$md, Collection $sessions): void
    {
        $md[] = '## Mulige dead-ends';

        // Forløb, der sluttede kort efter en fejl
        $endedOnError = collect();
        foreach ($sessions as $rows) {
            $tail = $rows->slice(-3);
            $err = $tail->last(fn ($e) => $this->isProblem($e));
            if ($err) $endedOnError->push($this->label($err));
        }
        $md[] = '### Forløb, der sluttede lige efter en fejl';
        $md[] = $endedOnError->isEmpty() ? 'Ingen.' : $this->list($endedOnError->countBy()->sortDesc()->take(15));
        $md[] = '';

        // Samme fejl flere gange i samme forløb
        $repeated = collect();
        foreach ($sessions as $rows) {
            foreach ($rows->filter(fn ($e) => $e->type === 'error')->groupBy(fn ($e) => $this->label($e)) as $label => $r) {
                if ($r->count() >= 3) $repeated->push($label);
            }
        }
        $md[] = '### Samme fejl 3+ gange i samme forløb';
        $md[] = $repeated->isEmpty() ? 'Ingen.' : $this->list($repeated->countBy()->sortDesc()->take(15));
        $md[] = '';

        // Ping-pong: A → B → A, hvor B blev forladt inden for 10 sekunder
        $pingpong = collect();
        foreach ($sessions as $rows) {
            $views = $rows->where('type', 'view')->values();
            for ($i = 2; $i < $views->count(); $i++) {
                $a = $views[$i - 2]->path; $b = $views[$i - 1]->path; $c = $views[$i]->path;
                if ($a === $c && $a !== $b && ($views[$i]->meta['ms'] ?? PHP_INT_MAX) < 10000) $pingpong->push("$a → $b → tilbage");
            }
        }
        $md[] = '### Sider, man straks går tilbage fra (A → B → A inden for 10 sek.)';
        $md[] = $pingpong->isEmpty() ? 'Ingen.' : $this->list($pingpong->countBy()->sortDesc()->take(15));
        $md[] = '';

        // Forløb, der blev startet, men aldrig nåede ok eller fejl
        $abandoned = collect();
        foreach ($sessions as $rows) {
            foreach ($rows->where('type', 'track')->filter(fn ($e) => str_ends_with((string) $e->target, '.start')) as $start) {
                $flow = explode('.', $start->target)[0];
                $later = $rows->filter(fn ($e) => $e->id > $start->id && $e->type === 'track' && str_starts_with((string) $e->target, $flow.'.') && ! str_ends_with($e->target, '.start'));
                if ($later->isEmpty()) $abandoned->push($flow.' (på '.$start->path.')');
            }
        }
        $md[] = '### Påbegyndt, men hverken gennemført eller fejlet (opgivet?)';
        $md[] = $abandoned->isEmpty() ? 'Ingen.' : $this->list($abandoned->countBy()->sortDesc()->take(15));
        $md[] = '';
    }

    private function errors(array &$md, Collection $events): void
    {
        $errors = $events->whereIn('type', ['error', 'js_error']);
        $md[] = '## Fejl (top 20)';
        if ($errors->isEmpty()) { $md[] = 'Ingen.'; $md[] = ''; return; }
        $md[] = '| Antal | Forløb | Fejl |';
        $md[] = '| - | - | - |';
        foreach ($errors->groupBy(fn ($e) => $this->label($e))->sortByDesc(fn ($r) => $r->count())->take(20) as $label => $r) {
            $md[] = '| '.$r->count().' | '.$r->pluck('session')->filter()->unique()->count().' | '.str_replace('|', '/', $label).' |';
        }
        $md[] = '';
    }

    private function pages(array &$md, Collection $events): void
    {
        $views = $events->where('type', 'view');
        if ($views->isEmpty()) return;
        // meta.ms på et sideskift er tiden på den forrige side (meta.from)
        $time = $views->filter(fn ($e) => isset($e->meta['from'], $e->meta['ms']) && $e->meta['ms'] < 3600000)
            ->groupBy(fn ($e) => $e->meta['from'])->map(fn ($r) => $r->pluck('meta.ms')->median());
        $md[] = '## Mest besøgte sider (top 20)';
        $md[] = '| Side | Visninger | Typisk tid på siden |';
        $md[] = '| - | - | - |';
        foreach ($views->countBy('path')->sortDesc()->take(20) as $path => $n) {
            $md[] = "| $path | $n | ".(isset($time[$path]) ? $this->dur($time[$path]) : '–').' |';
        }
        $md[] = '';
    }

    private function help(array &$md, Collection $events): void
    {
        $help = $events->where('type', 'track')->filter(fn ($e) => str_starts_with((string) $e->target, 'hjaelp.'));
        if ($help->isEmpty()) return;
        $md[] = '## Hjælp';
        $md[] = '- Hjælpen åbnet: '.$help->where('target', 'hjaelp.aabnet')->count().' gange. Emner: '.$this->counts($help->where('target', 'hjaelp.aabnet')->map(fn ($e) => $e->meta['topic'] ?? 'oversigt'));
        $md[] = '- Hjælpen åbnet fra siderne: '.$this->counts($help->where('target', 'hjaelp.aabnet')->pluck('path'));
        $md[] = '- Emner læst (foldet ud): '.$this->counts($help->where('target', 'hjaelp.emne')->map(fn ($e) => $e->meta['topic'] ?? '?'));
        $md[] = '- Søgninger: '.$help->where('target', 'hjaelp.soegt')->count().' med resultat, '.$help->where('target', 'hjaelp.soegt_uden_resultat')->count()
            .' uden resultat (mulig mangel i hjælpeteksten). Uden resultat fra siderne: '.$this->counts($help->where('target', 'hjaelp.soegt_uden_resultat')->map(fn ($e) => $e->meta['page'] ?? 'ukendt side'));
        $md[] = '';
    }

    /** Udvalgte forløb fortalt som en kæde af trin. Forløb med fejl eller opgivne trin vælges først. */
    private function stories(array &$md, Collection $sessions): void
    {
        $picked = $sessions->filter(fn ($r) => $r->count() >= 2)
            ->sortByDesc(fn ($r) => [$r->contains(fn ($e) => $this->isProblem($e)) ? 1 : 0, $r->count()])
            ->take(self::STORIES);
        if ($picked->isEmpty()) return;

        $md[] = '## Udvalgte forløb ('.$picked->count().' af '.$sessions->count().', dem med fejl først)';
        foreach ($picked as $sid => $rows) {
            $first = $rows->first(); $last = $rows->last();
            $who = $rows->whereNotNull('roles')->first()?->roles ?? 'ikke logget ind';
            $steps = [];
            $prev = null;
            foreach ($rows as $e) {
                $step = match ($e->type) {
                    'view' => $e->path,
                    'track' => '['.$e->target.']',
                    default => '✗ '.$this->label($e),
                };
                if ($step !== $prev) $steps[] = $step;
                $prev = $step;
            }
            if (count($steps) > 40) $steps = [...array_slice($steps, 0, 20), '…', ...array_slice($steps, -18)];
            $md[] = '- **'.substr($sid, 0, 6).'** ('.$who.', '.$first->platform.', '.$this->dur($first->created_at->diffInMilliseconds($last->created_at)).'): '.implode(' → ', $steps);
        }
        $md[] = '';
    }

    private function isProblem($e): bool
    {
        return in_array($e->type, ['error', 'js_error']) || ($e->type === 'track' && preg_match('/\.(fejl|konflikt|mangler)$/', (string) $e->target));
    }

    private function label($e): string
    {
        if ($e->type === 'track') return $e->target.' på '.$e->path;
        $meta = $e->meta ?? [];
        $parts = [$e->type === 'js_error' ? 'JS-fejl' : $e->target, $e->path];
        if (! empty($meta['fields'])) $parts[] = 'felter: '.implode(',', $meta['fields']);
        if (! empty($meta['message'])) $parts[] = '"'.$meta['message'].'"';

        return implode(' ', $parts);
    }

    private function counts(Collection $values): string
    {
        return $values->countBy()->sortDesc()->map(fn ($n, $k) => "$k $n")->implode(', ') ?: '–';
    }

    private function list(Collection $counts): string
    {
        return $counts->map(fn ($n, $k) => "- $n × $k")->implode("\n");
    }

    private function dur($ms): string
    {
        $s = (int) round($ms / 1000);
        return $s < 60 ? "$s sek." : ($s < 3600 ? round($s / 60).' min.' : round($s / 3600, 1).' t.');
    }

    private function output(array $md, $to): int
    {
        $text = implode("\n", $md)."\n";
        if ($this->option('stdout')) {
            $this->line($text);
            return self::SUCCESS;
        }
        $path = 'usage/brugslog-'.$to->format('Y-m-d').'.md';
        Storage::disk('local')->put($path, $text);
        // Sammenfatninger gemmes ligesom loggen kun i 90 dage
        foreach (Storage::disk('local')->files('usage') as $f) {
            if (Storage::disk('local')->lastModified($f) < now()->subDays(90)->getTimestamp()) Storage::disk('local')->delete($f);
        }
        $this->info('Gemt: '.Storage::disk('local')->path($path));

        return self::SUCCESS;
    }
}
