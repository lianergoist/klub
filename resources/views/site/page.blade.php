@php
    $v = fn ($f) => '/hjemmeside/'.$f.'?v='.(@filemtime(public_path('hjemmeside/'.$f)) ?: 1);
@endphp
<!doctype html>
<html lang="da">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $page->seo_title ?: $page->title.' · SurfHorsens' }}</title>
    @if ($page->description)
        <meta name="description" content="{{ $page->description }}">
        <meta property="og:description" content="{{ $page->description }}">
    @endif
    <meta property="og:title" content="{{ $page->seo_title ?: $page->title }}">
    <meta property="og:type" content="website">
    <meta name="theme-color" content="#e3d8d3">
    <link rel="icon" href="{{ $v('billeder/favicon.webp') }}" type="image/webp">
    <link rel="preload" href="/hjemmeside/fonts/poppins-latin-300-normal.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/hjemmeside/fonts/poppins-latin-500-normal.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ $v('site.css') }}">
</head>
<body>
<a class="sh-skip" href="#indhold">Spring til indholdet</a>
<header class="sh-header">
    <a class="sh-logo" href="/" aria-label="SurfHorsens – forsiden"><img src="/hjemmeside/billeder/logo-hvid-600.webp" alt="SurfHorsens" width="90" height="64"></a>
    <nav class="sh-nav" aria-label="Hovedmenu">
        @foreach ($menu as $m)
            <a href="/{{ $m->slug }}" @if ($m->slug === $page->slug) aria-current="page" @endif>{{ $m->title }}</a>
        @endforeach
        <a href="/login" data-klub="login">Log ind</a>
        <a class="sh-fb" href="https://www.facebook.com/groups/131691726958818/" target="_blank" rel="noopener" aria-label="SurfHorsens på Facebook">
            <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M13.5 21v-7.5H16l.4-3h-2.9V8.6c0-.9.3-1.5 1.5-1.5h1.5V4.4c-.3 0-1.2-.1-2.2-.1-2.2 0-3.7 1.3-3.7 3.8v2.4H8.1v3h2.5V21h2.9z"/></svg>
        </a>
        <a class="sh-btn sh-btn--small" href="/bliv-medlem">Meld mig ind!</a>
    </nav>
    <button class="sh-burger" type="button" aria-expanded="false" aria-controls="sh-mobilmenu" aria-label="Menu"><span></span><span></span></button>
</header>
<nav id="sh-mobilmenu" class="sh-mobilmenu" aria-label="Menu" hidden>
    @foreach ($menu as $m)
        <a href="/{{ $m->slug }}" @if ($m->slug === $page->slug) aria-current="page" @endif>{{ $m->title }}</a>
    @endforeach
    <a href="/login" data-klub="login">Log ind</a>
    <a class="sh-btn" href="/bliv-medlem">Meld mig ind!</a>
</nav>

<main id="indhold">
    @foreach ($blocks as $i => $b)
        @php
            // "Næste blok" = den næste, der vises
            $next = null;
            for ($j = $i + 1; $j < count($blocks); $j++) if (empty($blocks[$j]['hidden'])) { $next = $blocks[$j]['id']; break; }
        @endphp
        @if ($preview)<div class="sh-pv {{ !empty($b['hidden']) ? 'is-hidden' : '' }}" data-blok="{{ $b['id'] }}">@endif
        @includeIf('site.blocks.'.$b['type'], ['b' => $b, 'next' => $next, 'first' => $i === 0])
        @if ($preview)</div>@endif
    @endforeach
</main>

<footer class="sh-footer">
    <p>Husoddevej 85, 8700 Horsens Danmark</p>
    <p>Design 2026© <a href="https://mpdesignstudio.dk/" target="_blank" rel="noopener">MP Design Studio</a></p>
</footer>
<script src="{{ $v('site.js') }}" defer></script>
<script src="{{ $v('klub-widget.js') }}" defer></script>
@if ($preview)
    <link rel="stylesheet" href="{{ $v('editor-preview.css') }}">
    <script src="{{ $v('editor-preview.js') }}"></script>
@endif
</body>
</html>
