<template>
    <div class="pp" role="radiogroup" :aria-label="label">
        <button v-for="(name, hex) in colors" :key="hex" type="button" role="radio" :aria-checked="hex === value" :title="name" :aria-label="name"
            :class="['pp-c', { on: hex === value }]" :style="{ background: hex }" @click="$emit('update:modelValue', hex)"></button>
    </div>
</template>
<script setup>
import { computed } from 'vue';
import { PALETTE } from '../design';

// Farver kan kun vælges blandt designguidens (PALETTE) eller en anden tilladt liste (fx med lyse toninger)
const props = defineProps({ modelValue: String, colors: { type: Object, default: () => PALETTE }, label: { type: String, default: 'Farve' } });
defineEmits(['update:modelValue']);
const value = computed(() => props.modelValue?.toLowerCase());
</script>
<style scoped>
.pp { display: flex; flex-wrap: wrap; gap: 6px; }
.pp-c { width: 28px; height: 28px; border-radius: 50%; border: 1px solid var(--line); padding: 0; cursor: pointer; position: relative; }
.pp-c.on { box-shadow: 0 0 0 2px var(--surface), 0 0 0 4px var(--ink); }
.pp-c:focus-visible { outline: 2px solid var(--accent); outline-offset: 3px; }
</style>
