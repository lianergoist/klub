import { reactive } from 'vue';
import { store } from './api';
import { PREVIEW, design, findTheme, themeColor } from './design';

// Temaet Marie blev fjernet 16/11-2026; har man det valgt, giver setTheme Automatisk.
// 23/11-2026: alle temaer følger SurfHorsens' designguide. Nøglerne standard/gron/orange var Blå/Grøn/Orange og er nu Hav/Mint/Solnedgang,
// så tidligere valg huskes.
// 'auto' = SurfHorsens, lys eller mørk efter telefonens/computerens indstilling.
// Egne temaer fra System → Design tilføjes som 'egen-<id>' og følger også telefonens lyse/mørke tilstand.
const BUILT_IN = {
    auto: 'Automatisk', klub: 'SurfHorsens', mork: 'SurfHorsens (mørk)', 'klub-arial': 'SurfHorsens (Arial)',
    standard: 'Hav', 'standard-mork': 'Hav (mørk)', gron: 'Mint', 'gron-mork': 'Mint (mørk)', orange: 'Solnedgang', 'orange-mork': 'Solnedgang (mørk)',
};
// Altid mørke temaer: får html[data-mork] ligesom den mørke udgave af SurfHorsens
const DARK = ['mork', 'standard-mork', 'gron-mork', 'orange-mork'];
export const THEMES = reactive({ ...BUILT_IN });
const COLORS = { klub: '#7d598b', 'klub-arial': '#7d598b', standard: '#575756', gron: '#e2efe6', orange: '#f8c760', 'klub-mork': '#575756', mork: '#575756', 'standard-mork': '#575756', 'gron-mork': '#575756', 'orange-mork': '#575756' };
const dark = window.matchMedia('(prefers-color-scheme: dark)');
// Android-appen: WebView'en melder ikke telefonens mørke tilstand videre, så vi spørger appen selv (MainActivity.KlubNative)
const systemDark = () => (typeof window.KlubNative?.isDark === 'function' ? window.KlubNative.isDark() : dark.matches);
const isCustom = (t) => typeof t === 'string' && t.startsWith('egen-');

function read() {
    try {
        // Før 13/11-2026 blev standardtemaet (Blå på web, SurfHorsens i appen) gemt, som om man selv havde valgt det.
        // Kun valg med markeringen 'tema-valgt' er aktive valg – alt andet giver Automatisk.
        if (!localStorage.getItem('tema-valgt')) return null;
        return localStorage.getItem('tema');
    } catch { return null; }
}

/** Egne temaer ind i listen (dem under udvikling ses kun af dem, der må rette design) */
function syncCustom() {
    for (const k of Object.keys(THEMES)) if (isCustom(k)) delete THEMES[k];
    for (const t of design.themes) THEMES[t.key] = t.name + (t.status === 'development' ? ' (under udvikling)' : '');
}

/** Sætter html[data-tema] (og data-mork for mørk udgave af SurfHorsens og egne temaer) ud fra det valgte tema. */
function apply() {
    const html = document.documentElement;
    let t = store.tema === 'auto' ? 'klub' : store.tema;
    // Et eget tema, der er slettet eller ikke længere tilgængeligt: vis Automatisk (valget i localStorage bevares)
    if (isCustom(t) && !findTheme(t)) t = 'klub';
    const followsDevice = store.tema === 'auto' || isCustom(t);
    const mork = PREVIEW ? PREVIEW === 'mork' : followsDevice && systemDark();
    html.dataset.tema = t;
    if (DARK.includes(t) || (mork && (t === 'klub' || isCustom(t)))) html.dataset.mork = ''; else delete html.dataset.mork;
    const color = isCustom(t) ? themeColor(t, mork) : COLORS[mork && t === 'klub' ? 'klub-mork' : t];
    document.querySelector('meta[name=theme-color]')?.setAttribute('content', color || COLORS.klub);
    // Appen: statuslinjens klokkeslæt og ikoner skal være mørke på lyse temaer og lyse på mørke
    // eslint-disable-next-line no-undef
    if (__APP__) import('./native').then((m) => m.setStatusBarDark(mork || DARK.includes(t))).catch(() => {});
}

/** remember=true, når medlemmet selv vælger – kun aktive valg gemmes. */
export function setTheme(t, remember = false) {
    // Egne temaer er måske ikke hentet endnu ved opstart – apply() falder selv tilbage, hvis temaet ikke findes
    if (!THEMES[t] && !isCustom(t)) t = 'auto';
    store.tema = t;
    apply();
    if (remember) try { localStorage.setItem('tema', t); localStorage.setItem('tema-valgt', '1'); } catch {}
}

/** Fx ?tema=klub, ?tema=mork eller ?tema=auto i adressen vælger tema (praktisk til at dele et link). */
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
    const fromUrl = new URLSearchParams(location.search).get('tema');
    if (THEMES[fromUrl] || isCustom(fromUrl)) setTheme(fromUrl, true);
    else setTheme(read() || 'auto');
    // Følg med, når telefonen skifter mellem lys og mørk (fx om aftenen)
    const follow = () => { if (store.tema === 'auto' || isCustom(store.tema)) apply(); };
    dark.addEventListener('change', follow);
    window.addEventListener('nativecolorscheme', follow); // fra Android-appen
    document.addEventListener('visibilitychange', () => { if (!document.hidden) follow(); }); // tilbage i appen efter skift
}
