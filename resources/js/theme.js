import { store } from './api';

export const THEMES = { standard: 'Standard', klub: 'SurfHorsens' };
const COLORS = { standard: '#0c4a5e', klub: '#7e5a8c' };

function read() { try { return localStorage.getItem('tema'); } catch { return null; } }

export function setTheme(t) {
    if (!THEMES[t]) t = 'standard';
    store.tema = t;
    document.documentElement.dataset.tema = t;
    document.querySelector('meta[name=theme-color]')?.setAttribute('content', COLORS[t]);
    try { localStorage.setItem('tema', t); } catch {}
}

/** ?tema=klub eller ?tema=standard i adressen vælger tema (praktisk til at dele et link). */
export function initTheme() {
    const fromUrl = new URLSearchParams(location.search).get('tema');
    setTheme(THEMES[fromUrl] ? fromUrl : read() || (__APP__ ? 'klub' : 'standard')); // eslint-disable-line no-undef
}
