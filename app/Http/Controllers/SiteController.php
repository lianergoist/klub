<?php

namespace App\Http\Controllers;

use App\Models\SitePage;

/**
 * Klubbens offentlige hjemmeside: sider bygget af blokke (tabellen site_pages, se App\Support\Site).
 * Skabelonerne ligger i resources/views/site, CSS og script i public/hjemmeside (site.css, site.js).
 * Kalenderen og "Log ind" i menuen fyldes af public/hjemmeside/klub-widget.js, som også kan bruges på andre sider.
 * Hjemmesiden er ikke en del af appen – appen bygges kun af Vue-delen.
 */
class SiteController extends Controller
{
    public function show(string $page = '')
    {
        $p = SitePage::where('slug', $page)->firstOrFail();
        $menu = SitePage::where('slug', '!=', '')->orderBy('position')->get(['slug', 'title']);

        return response()
            ->view('site.page', ['page' => $p, 'blocks' => array_values(array_filter($p->blocks, fn ($b) => empty($b['hidden']))), 'menu' => $menu])
            ->header('Cache-Control', 'public, max-age=60');
    }

    /** Den gamle kopi af Squarespace-siden – til sammenligning, indtil den nye er godkendt */
    public function old(string $page = '')
    {
        $names = ['' => 'forside', 'priser-og-medlemskab' => 'priser-og-medlemskab', 'kalender' => 'kalender', 'kontakt' => 'kontakt', 'read-me' => 'read-me'];
        $file = resource_path('site/'.($names[$page] ?? 'x').'.html');
        abort_unless(is_file($file), 404);

        return response()->file($file, ['Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'no-cache']);
    }
}
