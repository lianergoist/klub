<section id="blok-{{ $b['id'] }}" class="sh-block sh-calendar">
    <div class="sh-wrap">
        <div class="eventlist eventlist--upcoming" data-klub="kalender" data-klub-billeder="{{ implode('|', $b['images'] ?? []) }}">
            <p class="klub-loading">Henter kalenderen …</p>
        </div>
    </div>
</section>
