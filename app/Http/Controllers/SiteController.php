<?php

namespace App\Http\Controllers;

/**
 * Klubbens offentlige hjemmeside (kopi af Squarespace-siden).
 * Siderne ligger i resources/site og bygges af tools/hjemmeside/import.py; billeder, CSS og
 * public/hjemmeside/klub-widget.js (kalender, priser, log ind) ligger i public/hjemmeside.
 * Hjemmesiden er ikke en del af appen – appen bygges kun af Vue-delen.
 */
class SiteController extends Controller
{
    public const PAGES = [
        '' => 'forside',
        'priser-og-medlemskab' => 'priser-og-medlemskab',
        'kalender' => 'kalender',
        'kontakt' => 'kontakt',
        'read-me' => 'read-me',
    ];

    public function show(string $page = '')
    {
        $file = resource_path('site/'.self::PAGES[$page].'.html');
        abort_unless(is_file($file), 404);

        return response()->file($file, ['Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'public, max-age=300']);
    }
}
