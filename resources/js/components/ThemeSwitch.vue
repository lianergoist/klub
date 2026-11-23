<template>
    <div class="theme-switch">
        <label class="ts-row">
            <span class="ts-label">Farvetema</span>
            <span class="ts-box">
                <span class="ts-sw" :style="{ background: swatch(store.tema) }" aria-hidden="true"></span>
                <select :value="store.tema" @change="setTheme($event.target.value, true)">
                    <option v-for="(label, key) in THEMES" :key="key" :value="key">{{ label }}</option>
                </select>
                <span class="ts-chev" aria-hidden="true">▾</span>
            </span>
        </label>
        <div class="ts-modes" role="radiogroup" aria-label="Lys eller mørk">
            <label v-for="(label, key) in MODES" :key="key" :class="{ on: store.tilstand === key }">
                <input type="radio" name="ts-tilstand" :value="key" :checked="store.tilstand === key" @change="setMode(key)">{{ label }}
            </label>
        </div>
        <span v-if="store.tilstand === 'auto'" class="ts-hint">Automatisk: lyst eller mørkt som din telefon/computer</span>
        <label class="ts-check">
            <input type="checkbox" :checked="store.letlaeselig" @change="setReadable($event.target.checked)">
            <span>Brug letlæselig skrifttype</span>
        </label>
    </div>
</template>
<script setup>
import { store } from '../api';
import { MODES, THEMES, setMode, setReadable, setTheme } from '../theme';
import { findTheme } from '../design';

// Farveprikken ved siden af vælgeren viser det valgte tema (en <select> kan ikke vise farver i listen)
const SWATCH = {
    klub: 'conic-gradient(#7d598b 0 25%, #e668a3 0 50%, #ff9832 0 75%, #64a3a2 0)',
    standard: 'linear-gradient(135deg, #575756 50%, #2599be 50%)',
    gron: 'linear-gradient(135deg, #acd3d2 50%, #64a3a2 50%)',
    orange: 'linear-gradient(135deg, #f8c760 50%, #ff9832 50%)',
};
// Egne temaer: menu, knapper og detaljer
const swatch = (k) => {
    const c = findTheme(k)?.data?.light;
    return c ? `conic-gradient(${c.brand} 0 40%, ${c.accent} 0 70%, ${c.orange} 0 85%, ${c.bg} 0)` : SWATCH[k] || SWATCH.klub;
};
</script>
<style scoped>
.theme-switch { display: flex; flex-direction: column; gap: 10px; align-items: flex-start; }
.ts-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.ts-label { font-size: .78rem; font-weight: 600; opacity: .75; }
.ts-box { position: relative; display: inline-flex; align-items: center; flex: 0 1 190px; min-width: 150px; }
.ts-sw { position: absolute; left: 11px; width: 16px; height: 16px; border-radius: 50%; box-shadow: 0 0 0 1px rgba(128,128,128,.45); pointer-events: none; }
.ts-chev { position: absolute; right: 11px; font-size: .8rem; opacity: .7; pointer-events: none; }
select { appearance: none; -webkit-appearance: none; padding: 7px 30px 7px 36px; min-height: 40px; font-weight: 500; cursor: pointer; }
option { color: var(--ink); background: var(--surface); }
/* Lys / Mørk / Automatisk som tre knapper i én række (rigtige radioknapper, så tastatur og skærmlæser virker) */
.ts-modes { display: inline-flex; border: 1px solid var(--line); border-radius: 8px; overflow: hidden; max-width: 100%; }
.ts-modes label { position: relative; padding: 7px 12px; font-size: .85rem; font-weight: 500; cursor: pointer; white-space: nowrap; font-family: var(--font-heading); }
.ts-modes label + label { border-left: 1px solid var(--line); }
.ts-modes label.on { background: var(--accent); color: var(--btn-ink); }
.ts-modes input { position: absolute; opacity: 0; pointer-events: none; }
.ts-modes label:has(input:focus-visible) { outline: 2px solid var(--accent); outline-offset: -3px; }
.ts-hint { font-size: .75rem; opacity: .7; margin-top: -4px; }
.ts-check { display: flex; align-items: center; gap: 8px; font-size: .9rem; cursor: pointer; }
.ts-check input { width: 18px; height: 18px; min-height: 0; margin: 0; flex: none; accent-color: var(--accent); }
</style>
<style>
/* I sidemenuen: gennemsigtig vælger og knapper, der arver menuens tekstfarve */
.sidebar .theme-switch { gap: 8px; }
.sidebar .theme-switch .ts-row { width: 100%; gap: 6px; }
.sidebar .theme-switch .ts-label { width: 100%; }
.sidebar .theme-switch .ts-box { flex-basis: 100%; }
.sidebar .theme-switch select { background: var(--nav-hover); border-color: var(--nav-line); color: inherit; }
.sidebar .theme-switch option { color: #575756; background: #fffef0; }
.sidebar .theme-switch .ts-modes { width: 100%; border-color: var(--nav-line); }
.sidebar .theme-switch .ts-modes label { flex: 1; text-align: center; padding: 6px 4px; font-size: .78rem; }
.sidebar .theme-switch .ts-modes label + label { border-left-color: var(--nav-line); }
.sidebar .theme-switch .ts-modes label.on { background: var(--nav-on); color: inherit; font-weight: 600; }
.sidebar .theme-switch .ts-check { font-size: .8rem; }
.sidebar .theme-switch .ts-check input { accent-color: var(--orange); }
/* Sidemenuen er smal og lav: forklaringen til Automatisk vises under Profil i stedet */
.sidebar .theme-switch .ts-hint { display: none; }
</style>
