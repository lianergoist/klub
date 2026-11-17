<template>
    <div class="pw-wrap">
        <h1 style="margin:10px 0 6px">Vælg din egen adgangskode</h1>
        <p class="muted">Du er logget ind med en midlertidig adgangskode fra klubben. Vælg din egen, før du fortsætter.</p>
        <div class="card">
            <form class="stack" @submit.prevent="submit">
                <input type="email" :value="store.user?.email" autocomplete="username" hidden readonly>
                <p class="small muted" style="margin:0">Konto: <strong>{{ store.user?.email }}</strong></p>
                <label class="field"><span>Ny adgangskode</span><input v-model="password" type="password" autocomplete="new-password" minlength="8" required></label>
                <label class="field"><span>Gentag ny adgangskode</span><input v-model="password2" type="password" autocomplete="new-password" minlength="8" required></label>
                <p class="small muted" style="margin:0">Mindst 8 tegn.</p>
                <p v-if="error" class="banner bad">{{ error }}</p>
                <button class="btn primary block" :disabled="busy">Gem og fortsæt</button>
            </form>
        </div>
        <p class="small" style="text-align:center"><a href="#" @click.prevent="out">Log ud</a></p>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { api, store, refreshMe, logout, toast } from '../api';
import { track } from '../usage';

const router = useRouter();
const password = ref(''); const password2 = ref(''); const error = ref(''); const busy = ref(false);
async function submit() {
    error.value = '';
    if (password.value !== password2.value) { error.value = 'De to adgangskoder er ikke ens.'; return; }
    busy.value = true;
    try {
        await api('profile/password', { method: 'PUT', body: { password: password.value, password_confirmation: password2.value } });
        track('adgangskode', 'midlertidig_skiftet');
        await refreshMe();
        toast('Din adgangskode er gemt');
        router.replace('/aktiviteter');
    } catch (e) { error.value = e.message; } finally { busy.value = false; }
}
async function out() { await logout(); router.replace('/login'); }
</script>

<style scoped>
.pw-wrap { max-width: 420px; margin: 0 auto; padding: calc(env(safe-area-inset-top, 0px) + 24px) 16px 40px; }
</style>
