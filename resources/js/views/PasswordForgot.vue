<template>
    <div class="pw-wrap">
        <RouterLink to="/login" class="small">‹ Tilbage til log ind</RouterLink>
        <h1 style="margin:10px 0 6px">Glemt adgangskode</h1>
        <p class="muted" style="margin-top:0">Skriv den e-mail, du bruger til klubben, så sender vi et link, hvor du kan vælge en ny adgangskode.</p>
        <div class="card">
            <p v-if="sent" class="banner ok" role="status" style="margin:0">{{ sent }}</p>
            <form v-else class="stack" @submit.prevent="submit">
                <label class="field"><span>E-mail</span><input v-model="email" type="email" autocomplete="username" autocapitalize="off" spellcheck="false" required></label>
                <p v-if="error" class="banner bad">{{ error }}</p>
                <button class="btn primary block" :disabled="busy">Send link</button>
            </form>
        </div>
        <p class="small muted">Har du ikke adgang til din e-mail længere, så kontakt bestyrelsen.</p>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRoute } from 'vue-router';
import { api } from '../api';
import { track } from '../usage';

const route = useRoute();
const email = ref(route.query.email || '');
const sent = ref(''); const error = ref(''); const busy = ref(false);
track('adgangskode', 'glemt_start');

async function submit() {
    busy.value = true; error.value = '';
    try {
        sent.value = (await api('forgot-password', { method: 'POST', body: { email: email.value } })).message;
        track('adgangskode', 'glemt_sendt');
    } catch (e) { error.value = e.message; track('adgangskode', 'glemt_fejl', { status: e.status }); } finally { busy.value = false; }
}
</script>

<style scoped>
.pw-wrap { max-width: 460px; margin: 0 auto; padding: calc(env(safe-area-inset-top, 0px) + 32px) 16px 40px; }
</style>
