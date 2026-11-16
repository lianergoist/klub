@php $im = \App\Support\Site::img($src); @endphp
@if ($im['src'])
    <img src="{{ $im['src'] }}" @if ($im['srcset']) srcset="{{ $im['srcset'] }}" sizes="{{ $sizes ?? '(max-width: 767px) 100vw, 50vw' }}" @endif
        alt="{{ $alt ?? '' }}" loading="{{ !empty($eager) ? 'eager' : 'lazy' }}" decoding="async" @if (!empty($eager)) fetchpriority="high" @endif class="{{ $class ?? '' }}">
@endif
