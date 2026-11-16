@php $tag = ($b['heading_size'] ?? 'h1') === 'h2' ? 'h2' : 'h1'; @endphp
<section id="blok-{{ $b['id'] }}" class="sh-block sh-hero sh-hero--{{ ($b['height'] ?? 'medium') === 'screen' ? 'screen' : 'medium' }}">
    @include('site.blocks._image', ['src' => $b['image'] ?? null, 'alt' => '', 'class' => 'sh-hero-bg', 'sizes' => '100vw', 'eager' => $first])
    <div class="sh-hero-in">
        @if (!empty($b['kicker']))<p class="sh-kicker">{!! nl2br(e($b['kicker'])) !!}</p>@endif
        @if (!empty($b['heading']))<{{ $tag }} class="sh-hero-title {{ ($b['heading_color'] ?? '') === 'orange' ? 'is-orange' : '' }}">{{ $b['heading'] }}</{{ $tag }}>@endif
        @if (!empty($b['logo']))
            <img class="sh-hero-logo" src="/hjemmeside/billeder/logo-{{ $b['logo'] === 'farve' ? 'farve' : 'hvid' }}-600.webp" alt="SurfHorsens – Alåha siden 1987" width="600" height="430">
        @endif
        @if (!empty($b['button']))
            @include('site.blocks._button', ['btn' => $b['button'], 'class' => 'sh-btn--light'])
        @endif
    </div>
</section>
