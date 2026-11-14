<template>
    <label class="theme-switch">
        <span class="ts-label">Farvetema</span>
        <span class="ts-box">
            <span class="ts-sw" :style="{ background: SWATCH[store.tema] }" aria-hidden="true"></span>
            <select :value="store.tema" @change="setTheme($event.target.value, true)">
                <option v-for="(label, key) in THEMES" :key="key" :value="key">{{ label }}</option>
            </select>
            <span class="ts-chev" aria-hidden="true">▾</span>
        </span>
        <span v-if="store.tema === 'auto'" class="ts-hint">SurfHorsens – lyst eller mørkt som din telefon/computer</span>
    </label>
</template>
<script setup>
import { store } from '../api';
import { THEMES, setTheme } from '../theme';

// Farveprikken ved siden af vælgeren viser det valgte tema (en <select> kan ikke vise farver i listen)
const SWATCH = {
    auto: 'linear-gradient(135deg, #e665a1 0 25%, #7e5a8c 25% 50%, #4f3559 50% 75%, #1d1520 75%)',
    standard: 'linear-gradient(135deg, #0c4a5e 50%, #0f7c8c 50%)',
    gron: 'linear-gradient(135deg, #1d4d35 50%, #2a7a4f 50%)',
    orange: 'linear-gradient(135deg, #6b2f12 50%, #b84a12 50%)',
    klub: 'conic-gradient(#7e5a8c 0 25%, #e665a1 0 50%, #ff9933 0 75%, #65a4a3 0)',
    marie: 'conic-gradient(#e3d8d3 0 40%, #83598c 0 70%, #ff9933 0 85%, #e665a1 0)',
    mork: 'linear-gradient(135deg, #0d1716 50%, #4cc2cf 50%)',
};
</script>
<style scoped>
.theme-switch { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.ts-label { font-size: .78rem; font-weight: 600; opacity: .75; }
.ts-box { position: relative; display: inline-flex; align-items: center; flex: 0 1 190px; min-width: 150px; }
.ts-sw { position: absolute; left: 11px; width: 16px; height: 16px; border-radius: 50%; box-shadow: 0 0 0 1px rgba(128,128,128,.45); pointer-events: none; }
.ts-hint { flex-basis: 100%; font-size: .75rem; opacity: .7; margin-top: -4px; }
.ts-chev { position: absolute; right: 11px; font-size: .8rem; opacity: .7; pointer-events: none; }
select { appearance: none; -webkit-appearance: none; padding: 7px 30px 7px 36px; min-height: 40px; font-weight: 500; cursor: pointer; }
option { color: var(--ink); background: var(--surface); }
</style>
<style>
/* I den mørke sidemenu: gennemsigtig vælger, der arver menuens tekstfarve */
.sidebar .theme-switch .ts-label { width: 100%; }
.sidebar .theme-switch .ts-box { flex-basis: 100%; }
.sidebar .theme-switch select { background: rgba(255,255,255,.1); border-color: rgba(255,255,255,.25); color: inherit; }
.sidebar .theme-switch option { color: #10201f; background: #fff; }
/* Sidemenuen er smal og lav: forklaringen til Automatisk vises under Profil i stedet */
.sidebar .theme-switch .ts-hint { display: none; }
</style>
