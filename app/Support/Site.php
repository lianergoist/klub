<?php

namespace App\Support;

use App\Http\Controllers\Api\PublicController;
use App\Models\Activity;
use App\Models\Package;
use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Hjemmesidens blokke. Layoutet ligger fast i bloktyperne (resources/views/site), så indholdet kan rettes
 * uden at ødelægge visningen på mobil og pc.
 *
 * Bloktyper (fælles felter: id, type, hidden):
 *   hero      Billede i fuld bredde med overskrift. height: screen (hele skærmen) | medium; image, kicker, heading,
 *             heading_size (h1|h2), heading_color (sand|orange), logo (farve|hvid|null), button {label, link}
 *   columns   Én eller to kolonner. layout: one|two, widths [a, b] (kun admin), bg: sand|orange, tone: lilla|pink,
 *             align: left|center, cols: [[elementer], [elementer]]
 *             Elementer: image {src, alt, link?, plain?} · text {html} · button {label, link} · box {html} · rule · map {address}
 *   card      Billede til venstre + orange boks til højre (Priser). image, alt, label, heading, html,
 *             prices (prislisten fra Indstillinger → Pakker), button {label, link}, widths [billede, boks]
 *   faq       Spørgsmål og svar (fold ud). label, items [{q, a}]
 *   calendar  Kalenderen fra klub-systemet (aktiviteter med "Vis i kalenderen på hjemmesiden"). images [...]
 *
 * Links (knapper og billeder) gemmes som henvisninger, så de ikke kan gå i stykker:
 *   next · block:<id> · page:/sti · signup · offer:<pakke-id> · activity:<id> · url:https://…
 * Et tilbud eller en aktivitet, der ikke længere findes, er udsolgt eller overstået, giver ingen knap.
 */
class Site
{
    public const PAGES_INTERNAL = ['/bliv-medlem', '/klippekort', '/prov', '/login'];

    /** href for et link – eller null, hvis det, der linkes til, ikke (længere) findes */
    public static function href(?string $link, ?string $nextId = null): ?string
    {
        if (! $link) return null;
        [$kind, $value] = array_pad(explode(':', $link, 2), 2, '');

        return match ($kind) {
            'next' => $nextId ? '#blok-'.$nextId : null,
            'block' => preg_match('/^[\w-]+$/', $value) ? '#blok-'.$value : null,
            'signup' => '/bliv-medlem',
            'page' => preg_match('#^/[a-z0-9/-]*(\#[\w-]+)?$#', $value) ? $value : null,
            'offer' => self::offerHref((int) $value),
            'activity' => self::activityHref((int) $value),
            'url' => preg_match('#^https://[^\s"<>]+$#', $value) ? $value : null,
            default => null,
        };
    }

    public static function isExternal(?string $link): bool
    {
        return str_starts_with((string) $link, 'url:');
    }

    private static function offerHref(int $id): ?string
    {
        $p = Package::whereKey($id)->whereNull('archived_at')->where('self_service', true)->first();

        return $p ? '/prov?tilbud='.$p->id : null;
    }

    private static function activityHref(int $id): ?string
    {
        $a = Activity::whereKey($id)->where('on_website', true)->where('status', 'active')
            ->whereNull('cancelled_at')->whereNull('archived_at')->where('ends_at', '>', now())->first();

        return $a ? '/kalender#aktivitet-'.$a->id : null;
    }

    /** "kr. 1.000,-" som på den gamle side (øre: "kr. 499,50") */
    public static function kr(int $ore): string
    {
        $whole = intdiv($ore, 100);
        $rest = $ore % 100;

        return 'kr. '.number_format($whole, 0, ',', '.').','.($rest ? str_pad((string) $rest, 2, '0', STR_PAD_LEFT) : '-');
    }

    /** Prislisten (medlemskaber, tilkøb, tilbud) grupperet som på den gamle side */
    public static function priceGroups(): array
    {
        $data = app(PublicController::class)->prices();
        $groups = [];
        foreach ($data['memberships']->groupBy('period_label') as $period => $items) {
            $groups[] = ['title' => 'Kontingent ('.$period.')', 'items' => $items->sortBy('price')->values()->all()];
        }
        $members = $data['products']->where('guest_only', false)->values()->all();
        if ($members) $groups[] = ['title' => 'Udstyrsleje og tilkøb', 'items' => $members];
        $guests = $data['offers']->concat($data['products']->where('guest_only', true))->values()->all();
        if ($guests) $groups[] = ['title' => 'Prøv det af – også for ikke-medlemmer', 'items' => $guests];

        return $groups;
    }

    /** Tekst fra blokkene: kun simple tags (overskrifter, afsnit, fed, kursiv, lister, links), priser sættes ind fra pakkerne */
    public static function html(?string $html): string
    {
        if (! $html || trim($html) === '') return '';
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><div id="rod">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $root = $doc->getElementById('rod');
        self::clean($root);
        $out = '';
        foreach ($root->childNodes as $c) $out .= $doc->saveHTML($c);

        return $out;
    }

    private const TAGS = ['p', 'br', 'strong', 'b', 'em', 'i', 'a', 'ul', 'ol', 'li', 'h2', 'h3', 'h4', 'span'];

    private static function clean(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $c) {
            if ($c->nodeType === XML_TEXT_NODE) continue;
            if (! $c instanceof DOMElement) { $node->removeChild($c); continue; }
            $tag = strtolower($c->tagName);
            if (! in_array($tag, self::TAGS, true)) {
                // Ukendte tags: behold teksten, fjern tagget (script/style fjernes helt)
                if (! in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'template'], true)) {
                    self::clean($c);
                    while ($c->firstChild) $node->insertBefore($c->firstChild, $c);
                }
                $node->removeChild($c);
                continue;
            }
            $keep = [];
            if ($tag === 'a') {
                $href = trim($c->getAttribute('href'));
                if (preg_match('#^(https://|mailto:|tel:|/(?!/)|\#)#i', $href)) $keep['href'] = $href;
                if (str_starts_with($href, 'https://')) $keep += ['target' => '_blank', 'rel' => 'noopener'];
            }
            if ($tag === 'span' && preg_match('/^\d+$/', $c->getAttribute('data-pris'))) $keep['data-pris'] = $c->getAttribute('data-pris');
            foreach (iterator_to_array($c->attributes) as $a) $c->removeAttribute($a->name);
            foreach ($keep as $k => $v) $c->setAttribute($k, $v);
            if ($tag === 'span' && isset($keep['data-pris'])) {
                $p = Package::whereKey((int) $keep['data-pris'])->whereNull('archived_at')->first();
                if ($p) $c->textContent = self::kr($p->price());
                continue;
            }
            self::clean($c);
        }
    }

    private static array $sizes = [];

    /** src + srcset (billedet findes i en mindre udgave "<navn>-900.webp" / "-600.webp") */
    public static function img(?string $src): array
    {
        if (! $src || ! preg_match('#^/hjemmeside/[\w/.-]+\.webp$#', $src)) return ['src' => $src, 'srcset' => null];
        $base = substr($src, 0, -5);
        $set = [];
        foreach ([600, 900] as $w) {
            if (is_file(public_path(ltrim("$base-$w.webp", '/')))) $set[] = "$base-$w.webp {$w}w";
        }
        $file = public_path(ltrim($src, '/'));
        $full = self::$sizes[$src] ??= (is_file($file) ? (getimagesize($file)[0] ?? 0) : 0);
        if ($set && $full) $set[] = "$src {$full}w";

        return ['src' => $src, 'srcset' => $set ? implode(', ', $set) : null];
    }
}
