<template>
    <div class="gate">
        <img class="brand-logo" src="/brand/surfhorsens-logo.webp" alt="SurfHorsens" style="width:190px;height:auto;margin:0 auto 8px">
        <img class="brand-default" src="/icon.svg" alt="" width="64" height="64" style="margin:0 auto 8px;border-radius:16px">
        <h1>Klubappen</h1>
        <p class="muted">Testudgave – kun for bestyrelsen.</p>
        <form class="card stack" @submit.prevent="submit">
            <label class="field"><span>Adgangskode</span><input v-model="code" type="password" autocomplete="current-password" required autofocus></label>
            <p v-if="error" class="banner bad" style="margin:0">{{ error }}</p>
            <button class="btn primary block" :disabled="busy">Fortsæt</button>
        </form>
        <p class="small muted">Koden huskes i appen.</p>
    </div>
</template>
<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { api, siteAccess } from '../api';
const router = useRouter();
const code = ref(''); const error = ref(''); const busy = ref(false);
async function submit() {
    busy.value = true; error.value = '';
    try { const r = await api('site-access', { method: 'POST', body: { code: code.value } }); siteAccess(r.token); router.replace('/login'); }
    catch (e) { error.value = e.message; } finally { busy.value = false; }
}
</script>
<style scoped>
.gate { max-width: 420px; margin: 0 auto; padding: calc(env(safe-area-inset-top, 0px) + 40px) 16px 40px; text-align: center; display: flex; flex-direction: column; }
.gate form { text-align: left; margin-top: 12px; }
</style>
