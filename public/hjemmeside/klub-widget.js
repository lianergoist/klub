/*
 * Klub-widget til klubbens hjemmeside: kalender, priser og log ind – hentet fra klub-systemet.
 *
 * Bruges på klub-systemets egen kopi af hjemmesiden og kan sættes ind på Squarespace-siden
 * (Kode-blok eller Code Injection) uden ændringer:
 *
 *   <script src="https://klub.rpix.dk/hjemmeside/klub-widget.js" defer></script>
 *   <div data-klub="kalender"></div>        kommende aktiviteter med "Vis i kalenderen på hjemmesiden"
 *   <div data-klub="priser"></div>          medlemskaber, tilkøb og tilbud
 *   <span data-klub-pris="SUP-kursus"></span> prisen på én pakke (pakkens navn)
 *   <a data-klub="login" href="…/login">     bliver til "Klubsystem", hvis man er logget ind (samme domæne)
 *
 * Klub-systemets adresse tages fra scriptets egen adresse (eller data-klub-api på script-tagget).
 * Siden bruger Squarespaces egne klasser (eventlist-…, sqs-button-element--primary), så designet følger med.
 */
(function () {
    'use strict';
    var me = document.currentScript || document.querySelector('script[src*="klub-widget.js"]');
    var BASE = ((me && me.getAttribute('data-klub-api')) || (me ? new URL(me.src, location.href).origin : '')).replace(/\/$/, '');
    var link = function (path) { return BASE + path; };

    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function get(path) {
        return fetch(link(path), { headers: { Accept: 'application/json' } }).then(function (r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        });
    }
    // "kr. 1.000,-" som på den gamle side (øre: "kr. 499,50")
    function kr(ore) {
        var v = ore / 100, whole = Math.floor(v), rest = Math.round((v - whole) * 100);
        return 'kr. ' + whole.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ',' + (rest ? String(rest).padStart(2, '0') : '-');
    }
    var fmt = function (o) { return new Intl.DateTimeFormat('da-DK', Object.assign({ timeZone: 'Europe/Copenhagen' }, o)); };
    var fMonth = fmt({ month: 'short' }), fDay = fmt({ day: 'numeric' }), fTime = fmt({ hour: '2-digit', minute: '2-digit' });
    var fWeekday = fmt({ weekday: 'long' }), fLong = fmt({ day: 'numeric', month: 'long', year: 'numeric' }), fIso = fmt({ year: 'numeric', month: '2-digit', day: '2-digit' });
    var isoDate = function (d) { return fIso.format(d).split('.').reverse().join('-'); };
    var sameDay = function (a, b) { return isoDate(a) === isoDate(b); };
    var longDate = function (d) { return fWeekday.format(d) + ' den ' + fLong.format(d); };

    function style() {
        if (document.getElementById('klub-widget-css')) return;
        var css = document.createElement('style');
        css.id = 'klub-widget-css';
        css.textContent = [
            // Squarespaces scripts nulstiller listens klasser, så billedet skjules – vis det som på den gamle kalender
            '[data-klub="kalender"] .eventlist-column-thumbnail{display:block!important}',
            '[data-klub="kalender"] .eventlist-column-thumbnail img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}',
            '@media (max-width:767px){[data-klub="kalender"] .eventlist-column-thumbnail{width:100%}}',
            '[data-klub="kalender"] .klub-tags{display:flex;flex-wrap:wrap;gap:6px;margin:0 0 .6em}',
            '[data-klub="kalender"] .klub-tag{font-size:.8em;padding:.15em .6em;border-radius:999px;border:1px solid currentColor;opacity:.75}',
            '[data-klub="kalender"] .klub-status{display:block;margin-top:.5em;font-size:.9em;opacity:.8}',
            '[data-klub="kalender"] .eventlist-description p{white-space:pre-line}',
            '.klub-empty,.klub-loading,.klub-error{padding:2em 0;text-align:center}',
            '[data-klub="priser"] .klub-pris-sub{opacity:.8;font-size:.9em}',
            '[data-klub="priser"] .klub-cta{margin-top:1.5em}',
        ].join('\n');
        document.head.appendChild(css);
    }

    // ---------- Kalender ----------
    function signupButton(a) {
        if (!a.signup_url) return a.full ? '<span class="klub-status">Holdet er fuldt</span>' : '';
        var text = a.signup === 'guest' ? 'Book plads' : a.signup === 'punchcard' ? 'Tilmeld med klippekort' : 'Log ind og tilmeld dig';
        return '<a href="' + esc(link(a.signup_url)) + '" class="eventlist-button sqs-editable-button sqs-button-element--primary">' + esc(text) + ' &#8594;</a>';
    }
    function eventHtml(a, images, i) {
        var s = new Date(a.starts_at), e = new Date(a.ends_at), multi = !sameDay(s, e);
        var img = images.length ? images[a.id % images.length] : null;
        var status = [];
        if (a.price_fmt) status.push(kr(a.price) + (a.members_only ? ' · kun for medlemmer' : ''));
        else if (a.members_only) status.push('For medlemmer');
        if (a.free !== null && a.free !== undefined && !a.full && a.free <= 5) status.push(a.free === 1 ? '1 ledig plads' : a.free + ' ledige pladser');
        return '<article class="eventlist-event eventlist-event--upcoming' + (img ? ' eventlist-event--hasimg eventlist-hasimg' : '') + (multi ? ' eventlist-event--multiday' : '') + '">' +
            (img ? '<div class="eventlist-column-thumbnail content-fill"><img src="' + esc(img) + '" alt="" loading="lazy"></div>' : '') +
            '<div class="eventlist-column-date"><div class="eventlist-datetag"><div class="eventlist-datetag-inner">' +
            '<div class="eventlist-datetag-startdate eventlist-datetag-startdate--month">' + esc(fMonth.format(s)) + '</div>' +
            '<div class="eventlist-datetag-startdate eventlist-datetag-startdate--day">' + esc(fDay.format(s)) + '</div>' +
            (multi ? '<div class="eventlist-datetag-enddate">til ' + esc(fDay.format(e) + ' ' + fMonth.format(e)) + '</div>' : '') +
            '<div class="eventlist-datetag-status"></div></div></div></div>' +
            '<div class="eventlist-column-info">' +
            '<h1 class="eventlist-title"><span class="eventlist-title-link">' + esc(a.title) + '</span></h1>' +
            '<ul class="eventlist-meta event-meta">' +
            (multi
                ? '<li class="eventlist-meta-item eventlist-meta-date event-meta-item"><time class="event-date">' + esc(longDate(s)) + '</time> <span class="event-time-localized-start">' + esc(fTime.format(s)) + '</span></li>' +
                  '<li class="eventlist-meta-item eventlist-meta-date event-meta-item"><time class="event-date">' + esc(longDate(e)) + '</time> <span class="event-time-localized-end">' + esc(fTime.format(e)) + '</span></li>'
                : '<li class="eventlist-meta-item eventlist-meta-date event-meta-item"><time class="event-date" datetime="' + isoDate(s) + '">' + esc(longDate(s)) + '</time></li>' +
                  '<li class="eventlist-meta-item eventlist-meta-time event-meta-item"><span class="event-time-localized"><time class="event-time-localized-start">' + esc(fTime.format(s)) + '</time><span class="event-datetime-divider"></span><time class="event-time-localized-end">' + esc(fTime.format(e)) + '</time></span></li>') +
            (a.location ? '<li class="eventlist-meta-item eventlist-meta-address event-meta-item">' + esc(a.location) + '</li>' : '') +
            '</ul>' +
            (a.categories && a.categories.length ? '<div class="klub-tags">' + a.categories.map(function (c) { return '<span class="klub-tag">' + esc(c) + '</span>'; }).join('') + '</div>' : '') +
            (a.description ? '<div class="eventlist-description"><p>' + esc(a.description) + '</p></div>' : '') +
            (status.length ? '<span class="klub-status">' + esc(status.join(' · ')) + '</span>' : '') +
            signupButton(a) +
            '</div><div class="clear"></div></article>';
    }
    function calendar(el) {
        var images = (el.getAttribute('data-klub-billeder') || '').split('|').filter(Boolean);
        var limit = +el.getAttribute('data-klub-antal') || 0;
        get('/api/public/calendar').then(function (list) {
            if (limit) list = list.slice(0, limit);
            el.innerHTML = list.length
                ? '<hr class="eventlist-past-upcoming-divider">' + list.map(function (a, i) { return eventHtml(a, images, i); }).join('')
                : '<p class="klub-empty">Der er ingen kommende aktiviteter i kalenderen lige nu.</p>';
        }).catch(function () { el.innerHTML = '<p class="klub-error">Kalenderen kunne ikke hentes. Prøv igen senere.</p>'; });
    }

    // ---------- Priser ----------
    function priceLines(items, withPeriod) {
        return items.map(function (p) {
            var sub = withPeriod && p.period_label ? ' <span class="klub-pris-sub">(' + esc(p.period_label) + ')</span>' : '';
            return '<p style="white-space:pre-wrap;">' + esc(kr(p.price)) + ' ' + esc(p.name) + sub + '</p>';
        }).join('');
    }
    function prices(el, data) {
        var html = '';
        var groups = {};
        data.memberships.forEach(function (p) { (groups[p.period_label] = groups[p.period_label] || []).push(p); });
        Object.keys(groups).forEach(function (period) {
            var items = groups[period].slice().sort(function (a, b) { return a.price - b.price; });
            html += '<p style="white-space:pre-wrap;"><strong>Kontingent (' + esc(period) + ')</strong></p>' + priceLines(items, false);
        });
        var members = data.products.filter(function (p) { return !p.guest_only; });
        if (members.length) html += '<p style="white-space:pre-wrap;"><strong>Udstyrsleje og tilkøb</strong></p>' + priceLines(members, false);
        var guests = data.offers.concat(data.products.filter(function (p) { return p.guest_only; }));
        if (guests.length) html += '<p style="white-space:pre-wrap;"><strong>Prøv det af – også for ikke-medlemmer</strong></p>' + priceLines(guests, false);
        html += '<p class="klub-cta"><a class="sqs-block-button-element--medium sqs-button-element--primary sqs-block-button-element" href="' + esc(link(data.signup_url)) + '">Meld dig ind</a></p>';
        el.innerHTML = html;
    }
    function priceSpots(spots, data) {
        var all = data.memberships.concat(data.products, data.offers);
        spots.forEach(function (el) {
            var name = el.getAttribute('data-klub-pris');
            var p = all.filter(function (x) { return x.name === name || String(x.id) === name; })[0];
            if (p) el.textContent = kr(p.price);
        });
    }

    // ---------- Log ind ----------
    function login(els) {
        var token = null;
        try { token = localStorage.getItem('token'); } catch (e) {}
        // Kun på klub-systemets eget domæne kan vi se, om man er logget ind
        if (!token || BASE !== location.origin) return;
        els.forEach(function (a) {
            a.setAttribute('href', link('/aktiviteter'));
            var t = a.querySelector('.header-menu-nav-item-content') || a;
            t.textContent = 'Klubsystem';
        });
    }

    function init() {
        style();
        login([].slice.call(document.querySelectorAll('[data-klub="login"]')));
        [].forEach.call(document.querySelectorAll('[data-klub="kalender"]'), calendar);
        var priceEls = [].slice.call(document.querySelectorAll('[data-klub="priser"]'));
        var spots = [].slice.call(document.querySelectorAll('[data-klub-pris]'));
        if (priceEls.length || spots.length) {
            get('/api/public/prices').then(function (data) {
                priceEls.forEach(function (el) { prices(el, data); });
                priceSpots(spots, data);
            }).catch(function () {
                priceEls.forEach(function (el) { el.innerHTML = '<p class="klub-error">Priserne kunne ikke hentes. Prøv igen senere.</p>'; });
            });
        }
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
