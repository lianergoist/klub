<template>
    <button type="button" class="help-btn noprint" :aria-label="`Hjælp: ${title}`" :title="`Hjælp: ${title}`" @click="openHelp(topic)">?</button>
</template>
<script setup>
import { computed } from 'vue';
import { openHelp, sections } from '../help';
const props = defineProps({ topic: { type: String, required: true } });
const title = computed(() => sections.find((s) => s.id === props.topic)?.title || 'Hjælp');
</script>
