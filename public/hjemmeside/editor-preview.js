/* Forhåndsvisningen i Indstillinger → Hjemmeside. Taler med editoren (siden udenom) via postMessage:
   ud:  sh-ready, sh-select {id}, sh-scroll {y}
   ind: sh-main {html, selected}, sh-state {selected, scroll}, sh-selected {id} */
(function () {
    'use strict';
    var parent = window.parent, origin = location.origin === 'null' ? '*' : location.origin;
    var selected = null;
    function post(m) { parent.postMessage(m, origin); }
    function mark(scroll) {
        [].forEach.call(document.querySelectorAll('.sh-pv'), function (el) { el.classList.toggle('is-selected', el.getAttribute('data-blok') === selected); });
        var el = selected && document.querySelector('.sh-pv[data-blok="' + selected + '"]');
        if (el && scroll) {
            var r = el.getBoundingClientRect();
            if (r.top < 60 || r.top > window.innerHeight - 80) window.scrollTo(0, Math.max(0, el.offsetTop - 90));
        }
    }
    // Et klik vælger blokken – links, knapper og formularer gør intet her
    document.addEventListener('click', function (e) {
        if (e.target.closest('.sh-burger')) return; // mobilmenuen må gerne åbnes
        var pv = e.target.closest('.sh-pv');
        e.preventDefault();
        e.stopPropagation();
        if (!pv) return;
        selected = pv.getAttribute('data-blok');
        mark(false);
        post({ type: 'sh-select', id: selected });
    }, true);
    document.addEventListener('submit', function (e) { e.preventDefault(); }, true);
    var t = null;
    window.addEventListener('scroll', function () {
        clearTimeout(t);
        t = setTimeout(function () { post({ type: 'sh-scroll', y: window.scrollY }); }, 120);
    }, { passive: true });
    window.addEventListener('message', function (e) {
        if (e.source !== parent || !e.data) return;
        var d = e.data;
        if (d.type === 'sh-main') {
            var y = window.scrollY;
            document.getElementById('indhold').innerHTML = d.html;
            window.scrollTo(0, y);
            selected = d.selected || null;
            mark(false);
        } else if (d.type === 'sh-state') {
            selected = d.selected || null;
            if (d.scroll) window.scrollTo(0, d.scroll);
            mark(!d.scroll); // ny visning (fx mobil): vis den valgte blok
        } else if (d.type === 'sh-selected') {
            selected = d.id || null;
            mark(true);
        }
    });
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { post({ type: 'sh-ready' }); });
    else post({ type: 'sh-ready' });
})();
