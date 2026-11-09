<template>
    <div class="modal-bg" @click.self="$emit('close')">
        <div class="modal" role="dialog" aria-modal="true">
            <div class="row between" style="margin-bottom:12px">
                <h2 style="margin:0">{{ title }}</h2>
                <button class="btn ghost" @click="$emit('close')" aria-label="Luk"><Icon name="x" style="width:20px" /></button>
            </div>
            <slot />
        </div>
    </div>
</template>
<script setup>
import Icon from './Icon.vue';
defineProps({ title: String });
defineEmits(['close']);
</script>
