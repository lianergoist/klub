<template>
    <label class="theme-switch">
        <span class="ts-label">Farvetema</span>
        <span class="ts-box">
            <span class="ts-sw" :style="{ background: SWATCH[store.tema] }" aria-hidden="true"></span>
            <select :value="store.tema" @change="setTheme($event.target.value)">
                <option v-for="(label, key) in THEMES" :key="key" :value="key">{{ label }}</option>
            </select>
            <span class="ts-chev" aria-hidden="true">▾</span>
        </span>
    </label>
</template>
<script setup>
import { store } from '../api';
import { THEMES, setTheme } from '../theme';

// Farveprikken ved siden af vælgeren viser det valgte tema (en <select> kan ikke vise farver i listen)
const SWATCH = {
    standard: 'linear-gradient(135deg, #0c4a5e 50%, #0f7c8c 50%)',
    gron: 'linear-gradient(135deg, #1d4d35 50%, #2a7a4f 50%)',
    orange: 'linear-gradient(135deg, #6b2f12 50%, #b84a12 50%)',
    klub: 'conic-gradient(#7e5a8c 0 25%, #e665a1 0 50%, #ff9933 0 75%, #65a4a3 0)',
    mork: 'linear-gradient(135deg, #0d1716 50%, #4cc2cf 50%)',
};
</script>
<style scoped>
.theme-switch { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.ts-label { font-size: .78rem; font-weight: 600; opacity: .75; }
.ts-box { position: relative; display: inline-flex; align-items: center; flex: 0 1 190px; min-width: 150px; }
.ts-sw { position: absolute; left: 11px; width: 16px; height: 16px; border-radius: 50%; box-shadow: 0 0 0 1px rgba(128,128,128,.45); pointer-events: none; }
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
</style>
