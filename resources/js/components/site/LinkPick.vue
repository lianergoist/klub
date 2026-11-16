<template>
    <div class="lp">
        <select :value="selectValue" :aria-label="label || 'Hvor fører den hen?'" @change="pick($event.target.value)">
            <option v-if="allowEmpty" value="">{{ emptyLabel }}</option>
            <option v-if="unknown" :value="modelValue">{{ unknown }}</option>
            <option v-if="allowNext" value="next">Næste blok på siden (ruller ned)</option>
            <optgroup label="Sider">
                <option v-for="o in options.pages" :key="o.value" :value="o.value">{{ o.label }}</option>
            </optgroup>
            <optgroup v-if="options.offers?.length" label="Tilbud til ikke-medlemmer (booking)">
                <option v-for="o in options.offers" :key="o.value" :value="o.value">{{ o.label }}</option>
            </optgroup>
            <optgroup v-if="options.activities?.length" label="Aktiviteter i kalenderen">
                <option v-for="o in options.activities" :key="o.value" :value="o.value">{{ o.label }}</option>
            </optgroup>
            <optgroup v-if="blockOptions.length" label="En blok længere nede på siden">
                <option v-for="o in blockOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
            </optgroup>
            <option value="url:">Anden hjemmeside …</option>
        </select>
        <input v-if="isUrl" :value="url" type="url" inputmode="url" placeholder="https://…" aria-label="Adressen på den anden hjemmeside" @input="setUrl($event.target.value)">
        <p v-if="isUrl && url && !/^https:\/\/\S+\.\S+/.test(url)" class="small" style="color:var(--bad);margin:4px 0 0">Adressen skal starte med https://</p>
        <p v-if="hint" class="small muted" style="margin:4px 0 0">{{ hint }}</p>
    </div>
</template>
<script setup>
import { computed } from 'vue';

/** Vælger, hvor en knap eller et billede fører hen – fra en liste, så links ikke kan gå i stykker (se App\Support\Site::href) */
const props = defineProps({
    modelValue: { type: String, default: null },
    options: { type: Object, required: true },
    blocks: { type: Array, default: () => [] },
    label: String,
    allowEmpty: Boolean,
    emptyLabel: { type: String, default: 'Intet link' },
    allowNext: { type: Boolean, default: true },
});
const emit = defineEmits(['update:modelValue']);

const all = computed(() => [...(props.options.pages || []), ...(props.options.offers || []), ...(props.options.activities || [])]);
const blockOptions = computed(() => props.blocks.map((b) => ({ value: 'block:' + b.id, label: b.label })));
const isUrl = computed(() => (props.modelValue || '').startsWith('url:'));
const url = computed(() => (isUrl.value ? props.modelValue.slice(4) : ''));
const selectValue = computed(() => (isUrl.value ? 'url:' : props.modelValue || ''));
// Fx et tilbud, der er arkiveret, eller en aktivitet, der er overstået: knappen vises ikke på siden
const unknown = computed(() => {
    const v = props.modelValue;
    if (!v || v === 'next' || isUrl.value || all.value.some((o) => o.value === v) || blockOptions.value.some((o) => o.value === v)) return null;
    if (v.startsWith('offer:')) return 'Tilbud, der ikke længere findes';
    if (v.startsWith('activity:')) return 'Aktivitet, der er overstået eller fjernet fra kalenderen';
    if (v.startsWith('block:')) return 'En blok, der ikke længere findes';
    return v;
});
const hint = computed(() => (unknown.value && !props.modelValue.startsWith('page:') ? 'Knappen vises ikke på siden, før du vælger noget andet.' : ''));
function pick(v) { emit('update:modelValue', v === '' ? null : v === 'url:' ? 'url:https://' : v); }
function setUrl(v) { emit('update:modelValue', 'url:' + v.trim()); }
</script>
<style scoped>
.lp { display: flex; flex-direction: column; gap: 6px; }
</style>
