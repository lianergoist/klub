import { store } from './api';

// Nøglen 'standard' bevares, så tidligere valg huskes (vises som Blå).
// 'auto' = SurfHorsens, lys eller mørk efter telefonens/computerens indstilling.
export const THEMES = { auto: 'Automatisk', klub: 'SurfHorsens', standard: 'Blå', gron: 'Grøn', orange: 'Orange', mork: 'Mørk' };
const COLORS = { standard: '#0c4a5e', gron: '#1d4d35', orange: '#6b2f12', klub: '#7e5a8c', 'klub-mork': '#1d1520', mork: '#0d1716' };
const dark = window.matchMedia('(prefers-color-scheme: dark)');

function read() {
    try {
        const t = localStorage.getItem('tema');
        // Før 13/11-2026 blev standardtemaet også gemt. Et gemt 'klub' uden markering var altså ikke et aktivt valg.
        if (t === 'klub' && !localStorage.getItem('tema-valgt')) return null;
        return t;
    } catch { return null; }
}

/** Sætter html[data-tema] (og data-mork for mørk SurfHorsens) ud fra det valgte tema. */
function apply() {
    const html = document.documentElement;
    const t = store.tema === 'auto' ? 'klub' : store.tema;
    const klubMork = store.tema === 'auto' && dark.matches;
    html.dataset.tema = t;
    if (klubMork) html.dataset.mork = ''; else delete html.dataset.mork;
    document.querySelector('meta[name=theme-color]')?.setAttribute('content', COLORS[klubMork ? 'klub-mork' : t]);
}

/** remember=true, når medlemmet selv vælger – kun aktive valg gemmes. */
export function setTheme(t, remember = false) {
    if (!THEMES[t]) t = 'auto';
    store.tema = t;
    apply();
    if (remember) try { localStorage.setItem('tema', t); localStorage.setItem('tema-valgt', '1'); } catch {}
}

/** Fx ?tema=klub, ?tema=mork eller ?tema=auto i adressen vælger tema (praktisk til at dele et link). */
export function initTheme() {
    const fromUrl = new URLSearchParams(location.search).get('tema');
    if (THEMES[fromUrl]) setTheme(fromUrl, true);
    else setTheme(read() || 'auto');
    // Følg med, når telefonen skifter mellem lys og mørk (fx om aftenen)
    dark.addEventListener('change', () => { if (store.tema === 'auto') apply(); });
}
