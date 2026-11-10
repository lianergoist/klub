<template>
    <div class="theme-switch" role="radiogroup" aria-label="Farvetema">
        <span class="ts-label">Farvetema</span>
        <button v-for="(label, key) in THEMES" :key="key" type="button" role="radio" :aria-checked="store.tema === key" :class="{ on: store.tema === key }" @click="setTheme(key)">
            <span class="ts-sw" :data-for="key"></span>{{ label }}
        </button>
    </div>
</template>
<script setup>
import { store } from '../api';
import { THEMES, setTheme } from '../theme';
</script>
<style scoped>
.theme-switch { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.ts-label { font-size: .78rem; font-weight: 600; opacity: .75; margin-right: 2px; }
button { display: inline-flex; align-items: center; gap: 6px; border: 1px solid currentColor; background: transparent; color: inherit; border-radius: 999px; padding: 4px 10px 4px 6px; font: inherit; font-size: .8rem; cursor: pointer; opacity: .7; }
button.on { opacity: 1; font-weight: 600; box-shadow: inset 0 0 0 1px currentColor; }
.ts-sw { width: 14px; height: 14px; border-radius: 50%; }
.ts-sw[data-for=standard] { background: linear-gradient(135deg, #0c4a5e 50%, #0f7c8c 50%); }
.ts-sw[data-for=klub] { background: conic-gradient(#7e5a8c 0 25%, #e665a1 0 50%, #ff9933 0 75%, #65a4a3 0); }
</style>
