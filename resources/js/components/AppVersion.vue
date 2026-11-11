<template>
    <p class="small muted app-version">{{ text }}</p>
</template>

<script setup>
/* global __APP_VERSION__, __BUILD_DATE__ */
// Viser versionen, så man kan se, om man har den seneste:
// i appen version + buildnummer fra telefonen (fx "Version 0.1 (build 7) · Android"), på web version + byggedato.
import { ref, onMounted } from 'vue';
import { IS_APP, dato } from '../api';

const text = ref(`Version ${__APP_VERSION__} · web, bygget ${dato(__BUILD_DATE__)}`);
onMounted(async () => {
    if (!IS_APP) return;
    try {
        const [{ App }, { Capacitor }] = await Promise.all([import('@capacitor/app'), import('@capacitor/core')]);
        const info = await App.getInfo();
        const platform = { android: 'Android', ios: 'iOS' }[Capacitor.getPlatform()] || 'app';
        text.value = `Version ${info.version} (build ${info.build}) · ${platform}`;
    } catch {
        text.value = `Version ${__APP_VERSION__} · app`;
    }
});
</script>

<style scoped>
.app-version { margin: 6px 0 0; }
</style>
