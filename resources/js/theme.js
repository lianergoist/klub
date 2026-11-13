import { store } from './api';

// Nøglen 'standard' bevares, så tidligere valg huskes (vises som Blå)
export const THEMES = { standard: 'Blå', gron: 'Grøn', orange: 'Orange', klub: 'SurfHorsens', mork: 'Mørk' };
const COLORS = { standard: '#0c4a5e', gron: '#1d4d35', orange: '#6b2f12', klub: '#7e5a8c', mork: '#0d1716' };

function read() { try { return localStorage.getItem('tema'); } catch { return null; } }

export function setTheme(t) {
    if (!THEMES[t]) t = 'standard';
    store.tema = t;
    document.documentElement.dataset.tema = t;
    document.querySelector('meta[name=theme-color]')?.setAttribute('content', COLORS[t]);
    try { localStorage.setItem('tema', t); } catch {}
}

/** Fx ?tema=klub, ?tema=mork eller ?tema=standard i adressen vælger tema (praktisk til at dele et link). */
export function initTheme() {
    const fromUrl = new URLSearchParams(location.search).get('tema');
    setTheme(THEMES[fromUrl] ? fromUrl : read() || 'klub'); // SurfHorsens er standard, indtil man selv vælger
}
