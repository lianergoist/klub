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

        return response($this->render($p))->header('Content-Type', 'text/html; charset=utf-8')->header('Cache-Control', 'no-cache'); // ændringer fra editoren skal ses med det samme
    }

    /**
     * Siden som HTML. $preview: til editoren (Indstillinger → Hjemmeside) – skjulte blokke vises nedtonet,
     * blokkene kan vælges med et klik, og links fører ingen steder hen.
     */
    public function render(SitePage $p, bool $preview = false): string
    {
        $menu = SitePage::where('slug', '!=', '')->orderBy('position')->get(['slug', 'title']);
        $blocks = array_values($preview ? $p->blocks : array_filter($p->blocks, fn ($b) => empty($b['hidden'])));

        return view('site.page', ['page' => $p, 'blocks' => $blocks, 'menu' => $menu, 'preview' => $preview])->render();
    }
}
