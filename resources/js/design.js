import { reactive, watch } from 'vue';
import { api, store } from './api';

/**
 * Egne farvetemaer (Indstillinger → Design): farver i en lys og en mørk udgave, logo, skrift og hjørner.
 * Kun CSS-variabler ændres – aldrig layout – så siderne virker på mobil og pc uanset valg.
 * De indbyggede temaer ligger i app.css og kan ikke rettes; BUILT_IN er deres grundfarver, så man kan åbne en kopi.
 * Et eget tema har nøglen 'egen-<id>' (html[data-tema=egen-5]) og følger enhedens lyse/mørke tilstand.
 */
const KLUB_DARK = { bg: '#1d1520', surface: '#291f2e', ink: '#f3ebef', heading: '#d9b8e6', accent: '#c49ad6', brand: '#4f3559', orange: '#ff9933' };
const MORK = { bg: '#0d1716', surface: '#15211f', ink: '#e9efee', heading: '#e9efee', accent: '#4cc2cf', brand: '#0f2e36', orange: '#4cc2cf' };
const BLAA = { bg: '#f4f6f5', surface: '#ffffff', ink: '#10201f', heading: '#10201f', accent: '#0f7c8c', brand: '#0c4a5e', orange: '#0f7c8c' };
export const BUILT_IN = {
    klub: { name: 'SurfHorsens', data: { light: { bg: '#e3d8d3', surface: '#fbf7f5', ink: '#33243a', heading: '#7e5a8c', accent: '#7e5a8c', brand: '#7e5a8c', orange: '#ff9933' }, dark: KLUB_DARK, radius: 14, font: 'poppins', logo: null } },
    marie: { name: 'Marie', data: { light: { bg: '#e3d8d3', surface: '#efe8e4', ink: '#4a2f52', heading: '#83598c', accent: '#83598c', brand: '#83598c', orange: '#ff9933' }, dark: KLUB_DARK, radius: 6, font: 'poppins', logo: null } },
    standard: { name: 'Blå', data: { light: BLAA, dark: MORK, radius: 14, font: 'system', logo: null } },
    gron: { name: 'Grøn', data: { light: { bg: '#f2f5f1', surface: '#ffffff', ink: '#142018', heading: '#142018', accent: '#2a7a4f', brand: '#1d4d35', orange: '#2a7a4f' }, dark: MORK, radius: 14, font: 'system', logo: null } },
    orange: { name: 'Orange', data: { light: { bg: '#f8f4f0', surface: '#ffffff', ink: '#2a1a10', heading: '#2a1a10', accent: '#b84a12', brand: '#6b2f12', orange: '#b84a12' }, dark: MORK, radius: 14, font: 'system', logo: null } },
    mork: { name: 'Mørk', data: { light: BLAA, dark: MORK, radius: 14, font: 'system', logo: null } },
};
export const DEFAULT_LOGO = '/brand/surfhorsens-logo.webp';

export const COLOR_FIELDS = [
    ['bg', 'Baggrund', 'Bag kortene og på login-siden'],
    ['surface', 'Kort og felter', 'Kort, lister og indtastningsfelter'],
    ['ink', 'Tekst', 'Dæmpet tekst og kanter beregnes ud fra den'],
    ['heading', 'Overskrifter', ''],
    ['accent', 'Knapper og links', 'Teksten på knapperne bliver selv hvid eller mørk'],
    ['brand', 'Menu', 'Menuen i venstre side på pc'],
    ['orange', 'Detaljer', 'Markering af den valgte side i menuen'],
];
export const FONTS = { poppins: 'Poppins (klubbens skrift)', system: 'Enhedens egen skrift' };
const FONT_CSS = {
    poppins: '"Poppins", ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif',
    system: 'ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
};

/** themes: egne temaer, man kan vælge (dem under udvikling kun for dem, der må rette design). logo: det aktive temas logo. */
export const design = reactive({ themes: [], logo: DEFAULT_LOGO });

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
        sand: mix(c.surface, c.orange, dark ? 0.2 : 0.18),
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

const rgba = (h, a) => `rgba(${rgb(h).join(',')},${a})`;
// Mørk udgave: statusfarver, der kan læses på mørk baggrund (som temaet Mørk)
const DARK_EXTRA = { ok: '#5fd08a', 'ok-soft': '#12301f', warn: '#f0b252', 'warn-soft': '#33270f', bad: '#ff8a80', 'bad-soft': '#3a1714', shadow: '0 1px 2px rgba(0,0,0,.3)' };

/** CSS for ét eget tema (nøgle fx 'egen-5') */
export function themeCss(key, d) {
    if (!d?.light || !d?.dark || !/^egen-[\w-]+$/.test(key)) return '';
    let out = '';
    for (const m of ['light', 'dark']) {
        const isDark = m === 'dark';
        const sel = `:root[data-tema=${key}]${isDark ? '[data-mork]' : ':not([data-mork])'}`;
        const v = { ...derive(d[m], isDark), ...(isDark ? DARK_EXTRA : {}) };
        const ink = v['brand-ink'];
        out += `${sel}{color-scheme:${isDark ? 'dark' : 'light'};${Object.entries(v).map(([k, x]) => `--${k}:${x}`).join(';')}}\n`;
        out += `${sel} .btn.primary{background:var(--accent);border-color:var(--accent);color:var(--btn-ink)}${sel} .btn.brand{color:var(--brand-ink)}\n`;
        out += `${sel} .sidebar{color:${ink}}${sel} .sidebar nav a{color:${rgba(ink, 0.86)}}${sel} .sidebar .sect{color:${rgba(ink, 0.7)}}`
            + `${sel} .sidebar nav a:hover{background:${rgba(ink, 0.08)}}${sel} .sidebar nav a.router-link-active{color:${ink};background:${rgba(ink, 0.16)};box-shadow:inset 3px 0 0 var(--orange)}\n`;
        if (isDark) out += `${sel} .sidebar{border-right:1px solid var(--line)}\n`;
    }
    out += `:root[data-tema=${key}]{--radius:${Number(d.radius) || 0}px;--font:${FONT_CSS[d.font] || FONT_CSS.system}}\n`;
    return out;
}

let preview = null;
export const findTheme = (key) => (key === 'egen-preview' ? preview : design.themes.find((t) => t.key === key));

/** Menufarve til statuslinjen */
export function themeColor(key, dark) {
    return findTheme(key)?.data?.[dark ? 'dark' : 'light']?.brand || null;
}

function inject() {
    let el = document.getElementById('klub-design');
    if (!el) { el = document.createElement('style'); el.id = 'klub-design'; document.head.appendChild(el); }
    el.textContent = design.themes.map((t) => themeCss(t.key, t.data)).join('') + (preview ? themeCss('egen-preview', preview.data) : '');
    window.dispatchEvent(new Event('klubdesign')); // theme.js opdaterer temalisten, logo og statuslinje
}

export const PREVIEW = new URLSearchParams(location.search).get('designvisning'); // 'lys' | 'mork' i forhåndsvisningen

/** Ved opstart: brug de gemte temaer med det samme (ingen blink), hent derefter de nyeste. */
export function initDesign() {
    if (PREVIEW) {
        // Forhåndsvisning i Indstillinger → Design: temaet, der redigeres, kommer fra siden udenom (samme adresse)
        const take = (p) => { if (p) { preview = { key: 'egen-preview', data: p.data, logo_url: p.logo_url }; inject(); } };
        try { take(window.parent.__klubDesignDraft); } catch {}
        window.addEventListener('message', (e) => { if (e.origin === location.origin && e.data?.type === 'klub-design') take(e.data); });
        return;
    }
    try { const c = JSON.parse(localStorage.getItem('klub-temaer') || 'null'); if (Array.isArray(c)) { design.themes = c; inject(); } } catch {}
    refreshDesign();
    // Efter log ind/ud: dem, der må rette design, ser også temaer under udvikling
    watch(() => store.token, () => refreshDesign());
}

export async function refreshDesign() {
    try {
        const r = await api('design');
        design.themes = r.themes || [];
        inject();
        try { localStorage.setItem('klub-temaer', JSON.stringify(design.themes)); } catch {}
    } catch {}
}
