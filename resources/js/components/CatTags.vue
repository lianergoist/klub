<template>
    <span class="row" style="gap:4px"><span v-for="c in cats" :key="c.id" class="tag"><span class="sw" :style="{ background: c.color }"></span>{{ c.name }}</span></span>
</template>
<script setup>
defineProps({ cats: Array });
</script>
