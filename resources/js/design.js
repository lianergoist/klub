import { reactive, watch } from 'vue';
import { api, store } from './api';

/**
 * Egne farvetemaer (System → Design): farver i en lys og en mørk udgave og hjørner (skriften er altid klubbens; letlæselig vælges af medlemmet). Logoet er altid klubbens.
 * Kun CSS-variabler ændres – aldrig layout – så siderne virker på mobil og pc uanset valg.
 * De indbyggede temaer ligger i app.css og kan ikke rettes; BUILT_IN er deres grundfarver, så man kan åbne en kopi.
 * Et eget tema har nøglen 'egen-<id>' (html[data-tema=egen-5]) og følger enhedens lyse/mørke tilstand.
 */
// ---------- farver ----------
const rgb = (h) => [1, 3, 5].map((i) => parseInt(h.slice(i, i + 2), 16));
const hex = (c) => '#' + c.map((v) => Math.round(Math.max(0, Math.min(255, v))).toString(16).padStart(2, '0')).join('');
/** a blandet med t af b (t=0 → a) */
export const mix = (a, b, t) => { const x = rgb(a), y = rgb(b); return hex(x.map((v, i) => v + (y[i] - v) * t)); };
const lum = (h) => { const [r, g, b] = rgb(h).map((v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; }); return 0.2126 * r + 0.7152 * g + 0.0722 * b; };
export const contrast = (a, b) => { const [x, y] = [lum(a), lum(b)].sort((p, q) => q - p); return (x + 0.05) / (y + 0.05); };
/** Hvid eller mørk tekst – den med bedst kontrast */
export const inkOn = (bg) => (contrast('#fffef0', bg) >= contrast('#575756', bg) ? '#fffef0' : '#575756'); // creme eller grå (paletten har ikke hvid/sort)

// SurfHorsens' designguide: de eneste tilladte farver (digital, 100 %). Samme liste i app/Support/Design.php.
export const PALETTE = {
    '#f8c760': 'Gul', '#f18975': 'Laks', '#f5b8d9': 'Lys pink', '#e668a3': 'Pink', '#2599be': 'Blå', '#78cadb': 'Lyseblå',
    '#ff9832': 'Orange', '#7d598b': 'Lilla', '#fffef0': 'Creme', '#acd3d2': 'Mint', '#64a3a2': 'Petrol', '#575756': 'Grå',
};
const CREME = '#fffef0', GRAA = '#575756';
/** Lys toning: 35 % paletfarve på creme – til baggrunde. Skal give samme værdier som Design::tints() i PHP. */
export const TINTS = Object.fromEntries(Object.entries(PALETTE).filter(([h]) => h !== CREME).map(([h, n]) => [mix(CREME, h, 0.35), `${n}, lys`]));
/** Farver, man kan vælge i egne temaer */
/** Lyseblå og mint en anelse mod creme, så de kan bruges til knapper og links på grå (mørk udgave). Samme i Design::DARK_TONES (PHP). */
export const DARK_TONES = { '#9ed9e1': 'Lyseblå, til mørk', '#b1d6d4': 'Mint, til mørk' };
export const ALLOWED = { ...PALETTE, ...TINTS, ...DARK_TONES };

const MORK = { bg: GRAA, surface: GRAA, ink: CREME, heading: '#f8c760', accent: '#f8c760', brand: GRAA, orange: '#e668a3' };
const KLUB = { bg: mix(CREME, '#f5b8d9', 0.35), surface: CREME, ink: GRAA, heading: '#7d598b', accent: '#7d598b', brand: '#7d598b', orange: '#ff9832' };
// Indbyggede temaer (kan åbnes som kopi i System → Design). Mørk udgave af Hav og Mint: lyseblå/mint en anelse mod creme til knapper (DARK_TONES).
export const BUILT_IN = {
    klub: { name: 'SurfHorsens', data: { light: KLUB, dark: MORK, radius: 14, font: 'futura', logo: null } },
    standard: { name: 'Hav', data: { light: { bg: mix(CREME, '#78cadb', 0.35), surface: CREME, ink: GRAA, heading: GRAA, accent: GRAA, brand: GRAA, orange: '#2599be' }, dark: { ...MORK, heading: '#78cadb', accent: '#9ed9e1', orange: '#2599be' }, radius: 14, font: 'futura', logo: null } },
    gron: { name: 'Mint', data: { light: { bg: mix(CREME, '#acd3d2', 0.35), surface: CREME, ink: GRAA, heading: GRAA, accent: '#7d598b', brand: CREME, orange: '#64a3a2' }, dark: { ...MORK, heading: '#acd3d2', accent: '#b1d6d4', orange: '#64a3a2' }, radius: 14, font: 'futura', logo: null } },
    orange: { name: 'Solnedgang', data: { light: { bg: mix(CREME, '#f8c760', 0.35), surface: CREME, ink: GRAA, heading: '#7d598b', accent: '#7d598b', brand: '#f8c760', orange: '#ff9832' }, dark: { ...MORK, heading: '#ff9832', orange: '#ff9832' }, radius: 14, font: 'futura', logo: null } },
};
export const DEFAULT_LOGO = '/brand/surfhorsens-logo.webp';

export const COLOR_FIELDS = [
    ['bg', 'Baggrund', 'Bag kortene og på login-siden'],
    ['surface', 'Kort og felter', 'Kort, lister og indtastningsfelter'],
    ['ink', 'Tekst', 'Dæmpet tekst og kanter beregnes ud fra den'],
    ['heading', 'Overskrifter', ''],
    ['accent', 'Knapper og links', 'Teksten på knapperne bliver selv creme eller grå'],
    ['brand', 'Menu', 'Menuen i venstre side på pc'],
    ['orange', 'Detaljer', 'Markering af den valgte side i menuen'],
];
// Designguiden: Futura, ellers Arial. Jost er en fri skrift tegnet efter Futura (bruges, hvor Futura ikke findes).
// Egne temaer bruger altid Futura; medlemmet kan vælge letlæselig skrift (html[data-letlaeselig] i app.css).
const FUTURA = 'Futura, "Futura PT", "Jost", Arial, sans-serif';

/** themes: egne temaer, man kan vælge (dem under udvikling kun for dem, der må rette design). logo: klubbens logo. */
export const design = reactive({ themes: [], logo: DEFAULT_LOGO });

/** Alle variabler for én tilstand ud fra de 7 valgte farver */
export function derive(c, dark) {
    const v = {
        ...c,
        'surface-2': mix(c.surface, c.bg, 0.5),
        line: mix(c.bg, c.ink, dark ? 0.22 : 0.12),
        'accent-soft': mix(c.surface, c.accent, 0.14),
        sand: mix(c.surface, c.orange, dark ? 0.25 : 0.22),
        'btn-ink': inkOn(c.accent),
        'brand-ink': inkOn(c.brand),
    };
    // Dæmpet tekst: så lys som muligt, men stadig læselig (4,5) på kort og baggrund
    const muted = (t) => {
        for (let s = t; s > 0; s -= 0.01) { const m = mix(c.ink, c.surface, s); if ([v.surface, v['surface-2'], v.bg].every((b) => contrast(m, b) >= 4.6)) return m; }
        return c.ink;
    };
    v['ink-2'] = muted(0.2);
    v['ink-3'] = muted(0.32);
    return v;
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
// (statusfarverne er toninger af paletten – lyse udgaver kommer fra :root i app.css)
const DARK_EXTRA = { ok: '#cae2dd', 'ok-soft': '#596261', warn: '#fbe0a2', 'warn-soft': '#706151', bad: '#f8d0e1', 'bad-soft': '#6c5a62', 'status-ink': '#575756', shadow: 'none' };

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
        out += `${sel} .sidebar{color:${ink}}${sel} .sidebar nav a,${sel} .sidebar nav button.navbtn{color:${rgba(ink, 0.9)}}${sel} .sidebar .sect{color:${rgba(ink, 0.9)}}`
            + `${sel} .sidebar nav a:hover,${sel} .sidebar nav button.navbtn:hover{background:${rgba(ink, 0.08)}}${sel} .sidebar nav a.router-link-active{color:${ink};background:${rgba(ink, 0.16)};box-shadow:inset 3px 0 0 var(--orange)}\n`;
        if (isDark) out += `${sel} .sidebar{border-right:1px solid var(--line)}\n`;
    }
    out += `:root[data-tema=${key}]{--radius:${Number(d.radius) || 0}px;--font:${FUTURA}}\n`;
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
        // Forhåndsvisning i System → Design: temaet, der redigeres, kommer fra siden udenom (samme adresse)
        const take = (p) => { if (p) { preview = { key: 'egen-preview', data: p.data }; inject(); } };
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
