@php $href = \App\Support\Site::href($btn['link'] ?? null, $next ?? null); @endphp
@if ($href && trim($btn['label'] ?? '') !== '')
    <p class="sh-btnrow"><a class="sh-btn {{ $class ?? '' }}" href="{{ $href }}"
        @if (\App\Support\Site::isExternal($btn['link'])) target="_blank" rel="noopener" @endif
        @if (($btn['link'] ?? '') === 'next') data-naeste @endif>{{ $btn['label'] }}</a></p>
@endif
