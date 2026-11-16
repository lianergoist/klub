<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

/**
 * Renser og kontrollerer blokke fra hjemmeside-editoren (Indstillinger → Hjemmeside), før de gemmes.
 * Kun kendte felter med kendte værdier kommer igennem, så en side altid kan vises på mobil og pc.
 *
 * Redaktører (rettigheden "Redigere hjemmesiden") må rette indholdet: billeder, tekster, knapper, skjule og tilføje blokke.
 * Kun administratoren må ændre layoutet (felterne i LOCKED), slette blokke og tilføje andre bloktyper end NEW_FOR_EDITORS.
 */
class SiteBlocks
{
    public const TYPES = ['hero', 'columns', 'card', 'faq', 'calendar'];

    /** Layout-felter, som kun admin kan ændre */
    public const LOCKED = [
        'hero' => ['height', 'heading_size', 'heading_color', 'logo'],
        'columns' => ['layout', 'widths', 'bg', 'tone', 'align'],
        'card' => ['widths'],
        'faq' => [],
        'calendar' => ['images'],
    ];

    /** Bloktyper, redaktører må tilføje (med standardlayout) */
    public const NEW_FOR_EDITORS = ['columns', 'card'];

    public const ELEMENTS = ['image', 'text', 'button', 'box', 'rule', 'map'];

    public const MAX_BLOCKS = 60;

    /**
     * @param  array  $blocks  blokkene fra editoren
     * @param  array  $old  de udgivne blokke (til kontrol af rettigheder)
     * @param  bool  $admin  må ændre layout og slette
     * @param  bool  $strict  false i forhåndsvisningen: rens, men afvis ikke
     */
    public static function clean(array $blocks, array $old, bool $admin, bool $strict = true): array
    {
        if (count($blocks) > self::MAX_BLOCKS) self::fail('Siden har for mange blokke (højst '.self::MAX_BLOCKS.').');
        $oldById = [];
        foreach ($old as $b) if (isset($b['id'])) $oldById[$b['id']] = self::block($b);

        $out = [];
        $seen = [];
        foreach (array_values($blocks) as $i => $b) {
            if (! is_array($b) || ! in_array($b['type'] ?? null, self::TYPES, true)) {
                if ($strict) self::fail('Blok nr. '.($i + 1).' er ukendt.');
                continue;
            }
            $id = (string) ($b['id'] ?? '');
            if (! preg_match('/^[a-z0-9]{1,20}$/i', $id) || isset($seen[$id])) {
                if ($strict) self::fail('Blok nr. '.($i + 1).' mangler et gyldigt id.');
                $id = 'x'.substr(md5(uniqid('', true)), 0, 8);
            }
            $seen[$id] = true;
            $b['id'] = $id;
            $c = self::block($b);

            if (! $admin) {
                if (isset($oldById[$id])) {
                    $prev = $oldById[$id];
                    if ($prev['type'] !== $c['type']) self::fail('Kun administratoren kan ændre en bloks type.');
                    foreach (self::LOCKED[$c['type']] as $k) $c[$k] = $prev[$k] ?? null; // layoutet bevares
                } else {
                    if (! in_array($c['type'], self::NEW_FOR_EDITORS, true)) self::fail('Kun administratoren kan tilføje den slags blok.');
                    if ($c['type'] === 'columns') $c = array_merge($c, ['widths' => [1, 1], 'bg' => 'sand', 'tone' => 'lilla']);
                    if ($c['type'] === 'card') $c['widths'] = [3, 5];
                }
            }
            if ($strict) self::check($c, $i);
            $out[] = $c;
        }

        if ($strict && ! $admin) {
            $missing = array_diff(array_keys($oldById), array_keys($seen));
            if ($missing) self::fail('Kun administratoren kan slette blokke. Skjul blokken i stedet.');
        }

        return $out;
    }

    /** Én blok med kun de kendte felter */
    public static function block(array $b): array
    {
        $base = ['id' => (string) $b['id'], 'type' => $b['type']] + (! empty($b['hidden']) ? ['hidden' => true] : []);

        return $base + match ($b['type']) {
            'hero' => [
                'height' => self::in($b['height'] ?? null, ['screen', 'medium'], 'medium'),
                'image' => self::image($b['image'] ?? null),
                'kicker' => self::str($b['kicker'] ?? '', 300),
                'heading' => self::str($b['heading'] ?? '', 160),
                'heading_size' => self::in($b['heading_size'] ?? null, ['h1', 'h2'], 'h1'),
                'heading_color' => self::in($b['heading_color'] ?? null, ['sand', 'orange'], 'sand'),
                'logo' => self::in($b['logo'] ?? null, ['farve', 'hvid'], null),
                'button' => self::button($b['button'] ?? null),
            ],
            'columns' => [
                'layout' => self::in($b['layout'] ?? null, ['one', 'two'], 'one'),
                'widths' => self::widths($b['widths'] ?? null, [1, 1]),
                'bg' => self::in($b['bg'] ?? null, ['sand', 'orange'], 'sand'),
                'tone' => self::in($b['tone'] ?? null, ['lilla', 'pink'], 'lilla'),
                'align' => self::in($b['align'] ?? null, ['left', 'center'], 'left'),
                'cols' => array_map(fn ($col) => self::elements($col), array_slice(array_values(array_filter((array) ($b['cols'] ?? []), 'is_array')), 0, 2)) ?: [[]],
            ],
            'card' => [
                'image' => self::image($b['image'] ?? null),
                'alt' => self::str($b['alt'] ?? '', 300),
                'label' => self::str($b['label'] ?? '', 80),
                'heading' => self::str($b['heading'] ?? '', 160),
                'html' => self::html($b['html'] ?? ''),
                'prices' => ! empty($b['prices']),
                'button' => self::button($b['button'] ?? null),
                'widths' => self::widths($b['widths'] ?? null, [3, 5]),
            ],
            'faq' => [
                'label' => self::str($b['label'] ?? '', 80),
                'items' => array_values(array_filter(array_map(fn ($it) => is_array($it) ? ['q' => self::str($it['q'] ?? '', 200), 'a' => self::html($it['a'] ?? '')] : null,
                    array_slice((array) ($b['items'] ?? []), 0, 40)), fn ($it) => $it && ($it['q'] !== '' || $it['a'] !== ''))),
            ],
            'calendar' => [
                'images' => array_values(array_filter(array_map(fn ($s) => self::image($s), array_slice((array) ($b['images'] ?? []), 0, 20)))),
            ],
        };
    }

    private static function elements(array $col): array
    {
        $out = [];
        foreach (array_slice(array_values($col), 0, 12) as $el) {
            if (! is_array($el) || ! in_array($el['t'] ?? null, self::ELEMENTS, true)) continue;
            $out[] = match ($el['t']) {
                'image' => ['t' => 'image', 'src' => self::image($el['src'] ?? null), 'alt' => self::str($el['alt'] ?? '', 300)]
                    + (($l = self::link($el['link'] ?? null)) ? ['link' => $l] : []) + (! empty($el['plain']) ? ['plain' => true] : []),
                'text' => ['t' => 'text', 'html' => self::html($el['html'] ?? '')],
                'box' => ['t' => 'box', 'html' => self::html($el['html'] ?? '')],
                'button' => ['t' => 'button'] + (self::button($el) ?? ['label' => '', 'link' => null]),
                'rule' => ['t' => 'rule'],
                'map' => ['t' => 'map', 'address' => self::str($el['address'] ?? '', 120)],
            };
        }

        return $out;
    }

    /** Fejl, der skal rettes før udgivelse */
    private static function check(array $c, int $i): void
    {
        $n = 'Blok nr. '.($i + 1);
        $imgs = [];
        if ($c['type'] === 'columns') foreach ($c['cols'] as $col) foreach ($col as $el) if ($el['t'] === 'image') $imgs[] = $el;
        if ($c['type'] === 'card' && $c['image']) $imgs[] = ['src' => $c['image'], 'alt' => $c['alt']];
        foreach ($imgs as $im) {
            if (! $im['src']) self::fail("$n: et billede mangler. Vælg et billede, eller fjern det.");
            if ($im['alt'] === '') self::fail("$n: skriv en kort beskrivelse af billedet (bruges af svagtseende og søgemaskiner).");
        }
    }

    // ---------- felter ----------
    public static function str($v, int $max): string
    {
        return mb_substr(trim(str_replace("\r", '', is_scalar($v) ? (string) $v : '')), 0, $max);
    }

    private static function in($v, array $ok, $default)
    {
        return in_array($v, $ok, true) ? $v : $default;
    }

    private static function widths($v, array $default): array
    {
        if (! is_array($v) || count($v) !== 2) return $default;
        $w = array_map(fn ($x) => max(1, min(5, (int) $x)), array_values($v));

        return $w;
    }

    public static function link($v): ?string
    {
        $v = trim(is_string($v) ? $v : '');

        return preg_match('#^(next|signup|block:[a-z0-9]{1,20}|page:/[a-z0-9/-]{0,80}(\#[\w-]+)?|offer:\d{1,9}|activity:\d{1,9}|url:https://[^\s"<>]{3,500})$#i', $v) ? $v : null;
    }

    public static function image($v): ?string
    {
        $v = is_string($v) ? $v : '';

        return preg_match('#^/hjemmeside/(billeder|uploads)/[\w/-]+\.webp$#', $v) && ! str_contains($v, '..') && is_file(public_path(ltrim($v, '/'))) ? $v : null;
    }

    private static function button($b): ?array
    {
        if (! is_array($b)) return null;
        $label = self::str($b['label'] ?? '', 80);
        $link = self::link($b['link'] ?? null);

        return $label !== '' || $link ? ['label' => $label, 'link' => $link] : null;
    }

    private static function html($h): string
    {
        return Site::html(mb_substr(is_string($h) ? $h : '', 0, 30000), false);
    }

    private static function fail(string $msg): never
    {
        throw ValidationException::withMessages(['blocks' => $msg]);
    }
}
