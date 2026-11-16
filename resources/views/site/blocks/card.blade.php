@php
    $w = $b['widths'] ?? [3, 5];
    $hasImg = !empty($b['image']);
@endphp
<section id="blok-{{ $b['id'] }}" class="sh-block sh-card-block">
    <div class="sh-wrap {{ $hasImg ? 'sh-card-grid' : '' }}" style="--a:{{ (int) $w[0] ?: 3 }}fr;--b:{{ (int) ($w[1] ?? 5) ?: 5 }}fr">
        @if ($hasImg)
            <figure class="sh-card-img">@include('site.blocks._image', ['src' => $b['image'], 'alt' => $b['alt'] ?? ''])</figure>
        @endif
        <div class="sh-card {{ !empty($b['prices']) ? 'is-stacked' : '' }}">
            <div class="sh-card-head">
                @if (!empty($b['label']))<p class="sh-label">{{ $b['label'] }}</p>@endif
                @if (!empty($b['heading']))<h3>{{ $b['heading'] }}</h3>@endif
            </div>
            <div class="sh-card-body sh-text">
                {!! \App\Support\Site::html($b['html'] ?? '') !!}
                @if (!empty($b['prices']))
                    @foreach (\App\Support\Site::priceGroups() as $g)
                        <p><strong>{{ $g['title'] }}</strong></p>
                        @foreach ($g['items'] as $p)
                            <p>{{ \App\Support\Site::kr($p['price']) }} {{ $p['name'] }}</p>
                        @endforeach
                    @endforeach
                @endif
                @if (!empty($b['button']))
                    @include('site.blocks._button', ['btn' => $b['button']])
                @endif
            </div>
        </div>
    </div>
</section>
