<section id="blok-{{ $b['id'] }}" class="sh-block sh-faq">
    <div class="sh-wrap sh-grid" style="--a:1fr;--b:1fr">
        <div class="sh-col"><p class="sh-label">{{ $b['label'] ?? '' }}</p></div>
        <div class="sh-col">
            @foreach ($b['items'] ?? [] as $it)
                <details class="sh-faq-item">
                    <summary>{{ $it['q'] ?? '' }}</summary>
                    <div class="sh-text">{!! \App\Support\Site::html($it['a'] ?? '') !!}</div>
                </details>
            @endforeach
        </div>
    </div>
</section>
