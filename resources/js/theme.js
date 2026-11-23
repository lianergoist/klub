import { reactive } from 'vue';
import { store } from './api';
import { PREVIEW, design, findTheme, themeColor } from './design';

// Udseende = tre uafhængige valg (24/11-2026):
//   farvetema  (store.tema):        klub = SurfHorsens (lyserød, standard), sand = Sand (baggrunden fra surfhorsens.dk), standard = Hav, gron = Mint, orange = Solnedgang, eller et eget tema 'egen-<id>'
//   tilstand   (store.tilstand):    'lys', 'mork' eller 'auto' (efter telefonens/computerens indstilling)
//   letlæselig (store.letlaeselig): Arial i brødtekst, Futura/Jost i overskrifter og knapper
// Nøglerne standard/gron/orange var engang Blå/Grøn/Orange; de gamle valg (fx 'auto', 'mork', 'standard-mork', 'klub-kombi')
// oversættes af fromOld(), så medlemmerne beholder deres valg.
const BUILT_IN = { klub: 'SurfHorsens', sand: 'Sand', standard: 'Hav', gron: 'Mint', orange: 'Solnedgang' };
export const THEMES = reactive({ ...BUILT_IN });
export const MODES = { auto: 'Automatisk', lys: 'Lys', mork: 'Mørk' }; // Automatisk først: det er standard
// Statuslinjens farve (menufarven i lys udgave; mørk udgave er altid grå)
const COLORS = { klub: '#7d598b', 'klub-dark': '#6e5876', sand: '#7d598b', 'sand-dark': '#6e5876', standard: '#575756', gron: '#e2efe6', orange: '#f8c760', dark: '#575756' };
const dark = window.matchMedia('(prefers-color-scheme: dark)');
// Android-appen: WebView'en melder ikke telefonens mørke tilstand videre, så vi spørger appen selv (MainActivity.KlubNative)
const systemDark = () => (typeof window.KlubNative?.isDark === 'function' ? window.KlubNative.isDark() : dark.matches);
const isCustom = (t) => typeof t === 'string' && t.startsWith('egen-');

/** Et gammelt temavalg (før 24/11-2026: ét valg for farve, lys/mørk og skrift) → { tema, tilstand, letlaeselig } */
function fromOld(old) {
    if (isCustom(old)) return { tema: old, tilstand: 'auto', letlaeselig: false }; // egne temaer fulgte enheden
    const m = /^(klub|sand|standard|gron|orange)(-mork)?$/.exec(old || '');
    if (m) return { tema: m[1], tilstand: m[2] ? 'mork' : 'lys', letlaeselig: false };
    return {
        mork: { tema: 'klub', tilstand: 'mork', letlaeselig: false },
        'klub-arial': { tema: 'klub', tilstand: 'lys', letlaeselig: true },
        'klub-kombi': { tema: 'klub', tilstand: 'lys', letlaeselig: true },
    }[old] || { tema: 'klub', tilstand: 'auto', letlaeselig: false };
}

function read() {
    try {
        const tema = localStorage.getItem('farvetema');
        if (tema) return { tema, tilstand: localStorage.getItem('tilstand') || 'auto', letlaeselig: localStorage.getItem('letlaeselig') === '1' };
        // Før 13/11-2026 blev standardtemaet gemt, som om man selv havde valgt det: kun valg med 'tema-valgt' er aktive valg
        if (localStorage.getItem('tema-valgt')) return fromOld(localStorage.getItem('tema'));
    } catch {}
    return null;
}

function save() {
    try {
        localStorage.setItem('farvetema', store.tema);
        localStorage.setItem('tilstand', store.tilstand);
        localStorage.setItem('letlaeselig', store.letlaeselig ? '1' : '0');
    } catch {}
}

/** Egne temaer ind i listen (dem under udvikling ses kun af dem, der må rette design) */
function syncCustom() {
    for (const k of Object.keys(THEMES)) if (isCustom(k)) delete THEMES[k];
    for (const t of design.themes) THEMES[t.key] = t.name + (t.status === 'development' ? ' (under udvikling)' : '');
}

/** Sætter html[data-tema], html[data-mork] og html[data-letlaeselig] ud fra valgene. */
function apply() {
    const html = document.documentElement;
    let t = store.tema;
    // Et eget tema, der er slettet eller ikke længere tilgængeligt: vis SurfHorsens (valget i localStorage bevares)
    if (!BUILT_IN[t] && !(isCustom(t) && findTheme(t))) t = 'klub';
    const mork = PREVIEW ? PREVIEW === 'mork' : store.tilstand === 'mork' || (store.tilstand === 'auto' && systemDark());
    html.dataset.tema = t;
    if (mork) html.dataset.mork = ''; else delete html.dataset.mork;
    if (store.letlaeselig && !PREVIEW) html.dataset.letlaeselig = ''; else delete html.dataset.letlaeselig;
    const color = isCustom(t) ? themeColor(t, mork) : (mork ? COLORS[t + '-dark'] || COLORS.dark : COLORS[t]);
    document.querySelector('meta[name=theme-color]')?.setAttribute('content', color || COLORS.klub);
    // Appen: statuslinjens klokkeslæt og ikoner skal være mørke på lyse temaer og lyse på mørke
    // eslint-disable-next-line no-undef
    if (__APP__) import('./native').then((m) => m.setStatusBarDark(mork)).catch(() => {});
}

/** Medlemmets valg. Kun aktive valg gemmes (remember=true). */
export function setTheme(t, remember = false) {
    // Egne temaer er måske ikke hentet endnu ved opstart – apply() falder selv tilbage, hvis temaet ikke findes
    store.tema = BUILT_IN[t] || isCustom(t) ? t : 'klub';
    apply();
    if (remember) save();
}
export function setMode(m) { store.tilstand = MODES[m] ? m : 'auto'; apply(); save(); }
export function setReadable(on) { store.letlaeselig = !!on; apply(); save(); }

/** Fx ?tema=hav... i adressen: ?tema=<nøgle> (også de gamle som 'standard-mork'), ?tilstand=lys|mork|auto, ?letlaeselig=1 */
export function initTheme() {
    // Forhåndsvisningen i System → Design viser temaet, der redigeres, og gemmer intet (localStorage deles med siden udenom)
    if (PREVIEW) {
        THEMES['egen-preview'] = 'Forhåndsvisning';
        store.tema = 'egen-preview';
        window.addEventListener('klubdesign', apply);
        apply();
        return;
    }
    syncCustom();
    window.addEventListener('klubdesign', () => { syncCustom(); apply(); });
    const q = new URLSearchParams(location.search);
    const fromUrl = q.get('tema') ? fromOld(q.get('tema')) : null;
    const v = { ...(read() || { tema: 'klub', tilstand: 'auto', letlaeselig: false }), ...(fromUrl || {}) };
    if (MODES[q.get('tilstand')]) v.tilstand = q.get('tilstand');
    if (q.has('letlaeselig')) v.letlaeselig = q.get('letlaeselig') === '1';
    store.tilstand = MODES[v.tilstand] ? v.tilstand : 'auto';
    store.letlaeselig = !!v.letlaeselig;
    setTheme(v.tema, !!(fromUrl || q.has('tilstand') || q.has('letlaeselig')));
    // Følg med, når telefonen skifter mellem lys og mørk (fx om aftenen)
    const follow = () => { if (store.tilstand === 'auto') apply(); };
    dark.addEventListener('change', follow);
    window.addEventListener('nativecolorscheme', follow); // fra Android-appen
    document.addEventListener('visibilitychange', () => { if (!document.hidden) follow(); }); // tilbage i appen efter skift
}
