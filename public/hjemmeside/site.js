/* SurfHorsens' hjemmeside: mobilmenu, "næste blok"-knapper og kort, der først hentes ved klik. */
(function () {
    'use strict';
    var burger = document.querySelector('.sh-burger'), menu = document.getElementById('sh-mobilmenu');
    function setMenu(open) {
        burger.setAttribute('aria-expanded', open ? 'true' : 'false');
        menu.hidden = !open;
        document.body.classList.toggle('menu-open', open);
    }
    if (burger && menu) {
        burger.addEventListener('click', function () { setMenu(menu.hidden); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !menu.hidden) { setMenu(false); burger.focus(); } });
        window.addEventListener('resize', function () { if (window.innerWidth >= 1024 && !menu.hidden) setMenu(false); });
    }
    // Kort fra Google Maps hentes først, når man beder om det (ingen cookies fra Google før)
    document.addEventListener('click', function (e) {
        var b = e.target.closest && e.target.closest('[data-vis-kort]');
        if (!b) return;
        var box = b.closest('.sh-map');
        var f = document.createElement('iframe');
        f.src = box.getAttribute('data-kort');
        f.title = 'Kort over ' + (box.querySelector('strong') ? box.querySelector('strong').textContent : 'adressen');
        f.loading = 'lazy';
        f.referrerPolicy = 'no-referrer-when-downgrade';
        box.innerHTML = '';
        box.appendChild(f);
        box.classList.add('is-loaded');
    });
})();
