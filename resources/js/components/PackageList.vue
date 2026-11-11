<template>
    <div class="pkgs">
        <label v-for="p in shown" :key="p.id" :class="['pkg', { on: modelValue === p.id, off: fits && fits(p) }]">
            <input type="radio" :value="p.id" :checked="modelValue === p.id" @change="$emit('update:modelValue', p.id)">
            <span class="body">
                <span class="row between" style="flex-wrap:nowrap;align-items:flex-start;gap:10px">
                    <strong>{{ p.name }}</strong>
                    <span class="num price"><s v-if="pr(p).capped" class="muted small">{{ p.price_fmt }}</s><strong>{{ kr(pr(p).price) }}</strong></span>
                </span>
                <span class="small muted meta">{{ p.composition_label }} · {{ p.valid_to ? `${dato(p.valid_from)} – ${dato(p.valid_to)}` : p.period_label }}<template v-if="!p.gives_access"> · uden adgang til aktiviteter</template></span>
                <span v-if="p.description" class="small desc">{{ p.description }}</span>
                <span v-if="pr(p).capped" class="small ok-txt">Prisen er sat ned, så det ikke koster mere end {{ kr(pr(p).price) }}, som I ville betale for hver jeres enkeltmedlemskab.</span>
                <span v-if="fits && fits(p)" class="small warn-txt">{{ fits(p) }}</span>
            </span>
        </label>
        <button v-if="others.length && !showAll" type="button" class="btn small ghost" style="justify-self:start" @click="showAll = true">Vis også {{ others.length }} {{ others.length === 1 ? 'pakke' : 'pakker' }}, der ikke passer til de valgte personer</button>
        <p v-if="!packages.length" class="muted small">{{ empty }}</p>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { dato, kr } from '../api';
// fits(p): returnerer en tekst, hvis de valgte personer ikke passer, ellers null.
// Pakker, der passer, vises først; resten skjules bag "Vis også …" (den valgte vises altid).
// priceOf(p): { price, full, capped } for de valgte personer (reglen om aldrig dyrere end enkeltkontingent)
const props = defineProps({ packages: Array, modelValue: Number, fits: Function, priceOf: Function, empty: { type: String, default: 'Der er ingen pakker at vælge mellem lige nu.' } });
defineEmits(['update:modelValue']);
const showAll = ref(false);
const pr = (p) => props.priceOf && !(props.fits && props.fits(p)) ? props.priceOf(p) : { price: p.price, full: p.price, capped: false };
// Pakker, der passer, vises med den billigste først
const ok = computed(() => (props.fits ? props.packages.filter(p => !props.fits(p)) : props.packages).slice().sort((a, b) => pr(a).price - pr(b).price));
const others = computed(() => props.fits ? props.packages.filter(p => props.fits(p)) : []);
const shown = computed(() => showAll.value || !ok.value.length ? [...ok.value, ...others.value] : [...ok.value, ...others.value.filter(p => p.id === props.modelValue)]);
</script>

<style scoped>
.pkgs { display: grid; gap: 8px; }
.pkg { display: flex; gap: 10px; align-items: flex-start; padding: 12px; border: 1px solid var(--line); border-radius: 12px; background: var(--surface); cursor: pointer; }
.pkg.on { border-color: var(--accent); background: var(--accent-soft); }
.pkg input { width: 20px; height: 20px; min-height: 0; margin: 2px 0 0; padding: 0; flex: 0 0 auto; accent-color: var(--accent); }
.body { display: flex; flex-direction: column; gap: 2px; flex: 1; min-width: 0; }
.price { white-space: nowrap; display: flex; flex-direction: column; align-items: flex-end; line-height: 1.25; }
.desc { color: var(--ink-2); }
.warn-txt { color: var(--warn); }
.ok-txt { color: var(--ok); }
.pkg.off:not(.on) { opacity: .75; }
</style>
