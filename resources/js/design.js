import { reactive } from 'vue';
import { api, API_BASE } from './api';

/**
 * Klubbens design (Indstillinger → Design): farver, logo, skrift og hjørner i SurfHorsens-temaet.
 * Kun CSS-variabler ændres – aldrig layout – så siderne virker på mobil og pc uanset valg.
 * Standardværdierne svarer til html[data-tema=klub] i app.css. Er en tilstands farver uændrede, rører vi dem ikke,
 * så det håndtunede standarddesign bevares.
 */
export const DEFAULTS = {
    light: { bg: '#e3d8d3', surface: '#fbf7f5', ink: '#33243a', heading: '#7e5a8c', accent: '#7e5a8c', brand: '#7e5a8c', orange: '#ff9933' },
    dark: { bg: '#1d1520', surface: '#291f2e', ink: '#f3ebef', heading: '#d9b8e6', accent: '#c49ad6', brand: '#4f3559', orange: '#ff9933' },
    radius: 14,
    font: 'poppins',
    logo: null,
};
export const DEFAULT_LOGO = '/brand/surfhorsens-logo.webp';

export const COLOR_FIELDS = [
    ['bg', 'Baggrund', 'Bag kortene og på login-siden'],
    ['surface', 'Kort og felter', 'Kort, lister og indtastningsfelter'],
    ['ink', 'Tekst', 'Almindelig tekst. Dæmpet tekst og kanter beregnes ud fra den'],
    ['heading', 'Overskrifter', ''],
    ['accent', 'Knapper og links', 'Teksten på knapperne bliver automatisk hvid eller mørk'],
    ['brand', 'Menu', 'Menuen i venstre side på pc. Teksten bliver automatisk hvid eller mørk'],
    ['orange', 'Detaljer', 'Markering af den valgte side i menuen'],
];
export const FONTS = { poppins: 'Poppins (klubbens skrift)', system: 'Enhedens egen skrift' };
const FONT_CSS = { system: 'ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif' };

export const design = reactive({ logo: DEFAULT_LOGO, version: null });

// ---------- farver ----------
const rgb = (h) => [1, 3, 5].map((i) => parseInt(h.slice(i, i + 2), 16));
const hex = (c) => '#' + c.map((v) => Math.round(Math.max(0, Math.min(255, v))).toString(16).padStart(2, '0')).join('');
/** a blandet med t af b (t=0 → a) */
export const mix = (a, b, t) => { const x = rgb(a), y = rgb(b); return hex(x.map((v, i) => v + (y[i] - v) * t)); };
const lum = (h) => { const [r, g, b] = rgb(h).map((v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; }); return 0.2126 * r + 0.7152 * g + 0.0722 * b; };
export const contrast = (a, b) => { const [x, y] = [lum(a), lum(b)].sort((p, q) => q - p); return (x + 0.05) / (y + 0.05); };
/** Hvid eller mørk tekst – den med bedst kontrast */
export const inkOn = (bg, darkInk = '#1f1724') => (contrast('#ffffff', bg) >= contrast(darkInk, bg) ? '#ffffff' : darkInk);

/** Alle variabler for én tilstand ud fra de 7 valgte farver */
export function derive(c, dark) {
    return {
        ...c,
        'surface-2': mix(c.surface, c.bg, 0.5),
        line: mix(c.bg, c.ink, dark ? 0.18 : 0.1),
        'ink-2': mix(c.ink, c.surface, 0.22),
        'ink-3': mix(c.ink, c.surface, dark ? 0.38 : 0.32),
        'accent-soft': mix(c.surface, c.accent, dark ? 0.22 : 0.14),
        'btn-ink': inkOn(c.accent),
        'brand-ink': inkOn(c.brand),
    };
}

/** Kontrolpunkter. min 4.5 for brødtekst, 3 for store overskrifter (WCAG AA). */
export function checks(d) {
    const out = [];
    for (const [m, name] of [['light', 'Lys'], ['dark', 'Mørk']]) {
        const v = derive(d[m], m === 'dark');
        const t = (label, fg, bg, min) => out.push({ mode: name, label, ratio: contrast(fg, bg), min, ok: contrast(fg, bg) >= min });
        t('Tekst på kort', v.ink, v.surface, 4.5);
        t('Tekst på baggrund', v.ink, v.bg, 4.5);
        t('Dæmpet tekst på kort', v['ink-3'], v.surface, 4.5);
        t('Overskrifter på baggrund', v.heading, v.bg, 3);
        t('Links på kort', v.accent, v.surface, 4.5);
        t('Tekst på knapper', v['btn-ink'], v.accent, 4.5);
        t('Tekst i menuen', v['brand-ink'], v.brand, 4.5);
    }
    return out;
}

const same = (a, b) => Object.keys(b).every((k) => a?.[k]?.toLowerCase() === b[k]);
const rgba = (h, a) => `rgba(${rgb(h).join(',')},${a})`;

export function css(d) {
    if (!d) return '';
    let out = '';
    for (const m of ['light', 'dark']) {
        if (!d[m] || same(d[m], DEFAULTS[m])) continue;
        const sel = m === 'dark' ? ':root[data-tema=klub][data-mork]' : ':root[data-tema=klub]:not([data-mork])';
        const v = derive(d[m], m === 'dark');
        out += `${sel}{${Object.entries(v).map(([k, x]) => `--${k}:${x}`).join(';')}}\n`;
        out += `${sel} .btn.primary{color:var(--btn-ink)}${sel} .btn.brand{color:var(--brand-ink)}\n`;
        out += `${sel} .sidebar{color:${v['brand-ink']}}${sel} .sidebar nav a{color:${rgba(v['brand-ink'], 0.86)}}${sel} .sidebar .sect{color:${rgba(v['brand-ink'], 0.7)}}`
            + `${sel} .sidebar nav a:hover{background:${rgba(v['brand-ink'], 0.08)}}${sel} .sidebar nav a.router-link-active{color:${v['brand-ink']};background:${rgba(v['brand-ink'], 0.16)}}\n`;
    }
    const root = [];
    if (d.radius !== undefined && d.radius !== DEFAULTS.radius) root.push(`--radius:${Number(d.radius)}px`);
    if (FONT_CSS[d.font]) root.push(`--font:${FONT_CSS[d.font]}`);
    if (root.length) out += `:root[data-tema=klub]{${root.join(';')}}\n`;
    return out;
}

/** Statuslinjens farve i SurfHorsens-temaet */
export function themeColor(darkMode) {
    return current?.[darkMode ? 'dark' : 'light']?.brand || null;
}

let current = null;
function apply(d, logoUrl) {
    current = d;
    let el = document.getElementById('klub-design');
    if (!el) { el = document.createElement('style'); el.id = 'klub-design'; document.head.appendChild(el); }
    el.textContent = css(d);
    design.logo = logoUrl || DEFAULT_LOGO;
    window.dispatchEvent(new Event('klubdesign')); // theme.js opdaterer statuslinjen
}

const logoFrom = (r) => (r?.logo_url ? r.logo_url : null);
export const PREVIEW = new URLSearchParams(location.search).get('designvisning'); // 'lys' | 'mork' i forhåndsvisningen

/** Ved opstart: brug det gemte design med det samme (ingen blink), hent derefter det nyeste. */
export function initDesign() {
    if (PREVIEW) {
        // Forhåndsvisning i Indstillinger → Design: kladden kommer fra siden udenom (samme adresse)
        const take = (p) => p && apply(p.data, p.logo_url);
        try { take(window.parent.__klubDesignDraft); } catch {}
        window.addEventListener('message', (e) => { if (e.origin === location.origin && e.data?.type === 'klub-design') take(e.data); });
        return;
    }
    try { const c = JSON.parse(localStorage.getItem('klub-design') || 'null'); if (c) apply(c.data, logoFrom(c)); } catch {}
    refreshDesign();
}

/** fresh=true efter en udgivelse: spring browserens cache (60 s) over */
export async function refreshDesign(fresh = false) {
    try {
        const r = await api(fresh ? `design?t=${Date.now()}` : 'design');
        apply(r.data, logoFrom(r));
        try { localStorage.setItem('klub-design', JSON.stringify(r)); } catch {}
    } catch {}
}

export const logoSrc = (url) => url || DEFAULT_LOGO;
export { API_BASE };
