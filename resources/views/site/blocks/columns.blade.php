@php
    // En tom kolonne (fx når billedet er fjernet): resten breder sig over hele bredden
    $raw = array_slice($b['cols'] ?? [], 0, 2);
    $filled = array_values(array_filter($raw, fn ($c) => !empty($c)));
    // I editorens forhåndsvisning vises en tom kolonne som en stiplet ramme, så man kan se, at den findes
    $showEmpty = !empty($preview) && ($b['layout'] ?? 'one') === 'two' && count($raw) === 2 && $filled;
    $two = ($b['layout'] ?? 'one') === 'two' && (count($filled) === 2 || $showEmpty);
    $cols = $showEmpty ? $raw : ($two ? $filled : [$filled ? array_merge(...$filled) : []]);
    $w = $b['widths'] ?? [1, 1];
    $isImg = fn ($col) => ($col[0]['t'] ?? '') === 'image';
    // På mobil står billedet øverst
    $flip = $two && $isImg($cols[1]) && ! $isImg($cols[0]);
@endphp
@if ($filled || !empty($preview))
<section id="blok-{{ $b['id'] }}" class="sh-block sh-cols sh-bg-{{ $b['bg'] ?? 'sand' }} sh-tone-{{ $b['tone'] ?? 'lilla' }} sh-align-{{ $b['align'] ?? 'left' }}">
    <div class="sh-wrap {{ $two ? 'sh-grid' : 'sh-single' }} {{ $flip ? 'sh-flip' : '' }}" @if ($two) style="--a:{{ (int) $w[0] ?: 1 }}fr;--b:{{ (int) ($w[1] ?? 1) ?: 1 }}fr" @endif>
        @if (!$filled)<p class="sh-pv-empty">Tom blok – tilføj tekst, billede eller en knap.</p>@endif
        @foreach ($cols as $col)
            <div class="sh-col">
                @if (!$col)<p class="sh-pv-empty">Tom kolonne. Den vises ikke på hjemmesiden – her fylder resten af blokken hele bredden. Tilføj indhold for at vise den.</p>@endif
                @foreach ($col as $el)
                    @switch($el['t'] ?? '')
                        @case('image')
                            @php $href = \App\Support\Site::href($el['link'] ?? null, $next); @endphp
                            <figure class="sh-figure {{ !empty($el['plain']) ? 'is-plain' : '' }}">
                                @if ($href)<a href="{{ $href }}">@endif
                                @include('site.blocks._image', ['src' => $el['src'] ?? null, 'alt' => $el['alt'] ?? '', 'sizes' => $two ? '(max-width: 767px) 100vw, 50vw' : '100vw'])
                                @if ($href)</a>@endif
                            </figure>
                            @break
                        @case('text')
                            <div class="sh-text">{!! \App\Support\Site::html($el['html'] ?? '') !!}</div>
                            @break
                        @case('box')
                            <div class="sh-box">{!! \App\Support\Site::html($el['html'] ?? '') !!}</div>
                            @break
                        @case('button')
                            @include('site.blocks._button', ['btn' => $el])
                            @break
                        @case('rule')
                            <hr class="sh-rule">
                            @break
                        @case('map')
                            @php $q = urlencode($el['address'] ?? 'Husoddevej 85, 8700 Horsens'); @endphp
                            <div class="sh-map" data-kort="https://www.google.com/maps?q={{ $q }}&amp;output=embed">
                                <p><strong>{{ $el['address'] ?? '' }}</strong></p>
                                <button type="button" class="sh-btn sh-btn--small" data-vis-kort>Vis kort</button>
                                <p class="sh-map-note">Kortet hentes fra Google Maps, når du klikker. <a href="https://www.google.com/maps/search/?api=1&amp;query={{ $q }}" target="_blank" rel="noopener">Åbn i Google Maps</a></p>
                            </div>
                            @break
                    @endswitch
                @endforeach
            </div>
        @endforeach
    </div>
</section>
@endif
