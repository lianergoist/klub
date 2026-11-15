<template>
    <div class="pw-wrap">
        <h1 style="margin:10px 0 6px">Vælg ny adgangskode</h1>
        <p v-if="!token" class="banner bad">Linket mangler oplysninger. Åbn linket fra mailen igen, eller <RouterLink to="/glemt-adgangskode">bed om et nyt link</RouterLink>.</p>
        <div v-else class="card">
            <form class="stack" @submit.prevent="submit">
                <input type="email" :value="email" autocomplete="username" hidden readonly>
                <p class="small muted" style="margin:0">Konto: <strong>{{ email }}</strong></p>
                <label class="field"><span>Ny adgangskode</span><input v-model="password" type="password" autocomplete="new-password" minlength="8" required></label>
                <label class="field"><span>Gentag ny adgangskode</span><input v-model="password2" type="password" autocomplete="new-password" minlength="8" required></label>
                <p class="small muted" style="margin:0">Mindst 8 tegn. Når du har valgt en ny adgangskode, bliver du logget ud på andre enheder.</p>
                <p v-if="error" class="banner bad">{{ error }} <RouterLink v-if="expired" :to="`/glemt-adgangskode?email=${encodeURIComponent(email)}`">Bed om et nyt link</RouterLink></p>
                <button class="btn primary block" :disabled="busy">Gem og log ind</button>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api, setSession, toast } from '../api';
import { track } from '../usage';

const route = useRoute(); const router = useRouter();
const token = String(route.query.token || ''); const email = String(route.query.email || '');
const password = ref(''); const password2 = ref('');
const error = ref(''); const expired = ref(false); const busy = ref(false);
track('adgangskode', 'nulstil_start');

async function submit() {
    if (password.value !== password2.value) { error.value = 'De to adgangskoder er ikke ens.'; track('adgangskode', 'nulstil_mangler', { felt: 'confirmed' }); return; }
    busy.value = true; error.value = ''; expired.value = false;
    try {
        const r = await api('reset-password', { method: 'POST', body: { token, email, password: password.value, password_confirmation: password2.value } });
        track('adgangskode', 'nulstil_ok');
        setSession(r.token, r.user); toast('Din nye adgangskode er gemt'); router.replace('/aktiviteter');
    } catch (e) {
        error.value = e.message; expired.value = !!e.errors?.token;
        track('adgangskode', 'nulstil_fejl', { status: e.status, udloebet: expired.value });
    } finally { busy.value = false; }
}
</script>

<style scoped>
.pw-wrap { max-width: 460px; margin: 0 auto; padding: calc(env(safe-area-inset-top, 0px) + 32px) 16px 40px; }
</style>
