<template>
    <div class="login-wrap">
        <div class="login-hero">
            <img class="brand-default" src="/icon.svg" alt="" width="64" height="64">
            <img class="brand-logo" src="/brand/surfhorsens-logo.webp" alt="SurfHorsens" style="width:200px;height:auto;margin:0 auto 6px">
            <h1>{{ store.tema === 'klub' ? 'Medlemsportal' : 'Klubben' }}</h1>
            <p>Aktiviteter, medlemskab og betaling samlet ét sted.</p>
        </div>
        <div class="card login-card">
            <form @submit.prevent="login" class="stack">
                <label class="field"><span>E-mail</span><input v-model="email" name="email" id="email" type="email" autocomplete="username" autocapitalize="off" spellcheck="false" required></label>
                <label class="field"><span>Adgangskode</span><input v-model="password" name="password" id="password" type="password" autocomplete="current-password" required></label>
                <p v-if="error" class="banner bad">{{ error }}</p>
                <button class="btn primary block" :disabled="busy">Log ind</button>
                <p class="small muted" style="text-align:center">Ikke medlem endnu? <RouterLink to="/bliv-medlem">Meld dig ind</RouterLink> <template v-if="!IS_APP">eller <RouterLink to="/prov">prøv en træning eller et SUP-kursus</RouterLink></template></p>
                <p class="small muted" style="text-align:center;margin-top:4px">Ikke medlem, men vil med til SUP? <RouterLink to="/klippekort">Køb et klippekort</RouterLink></p>
            </form>
        </div>
        <div v-if="demo.length" class="card login-card">
            <h3>Prøv som …</h3>
            <p class="small muted">Proof of concept med demodata. Vælg en rolle for at se systemet fra den vinkel. Adgangskoden er <code>demo1234</code>.</p>
            <div class="grid c2" style="gap:8px">
                <button v-for="d in demo" :key="d.email" class="btn demo-btn" @click="demoLogin(d.email)">
                    <strong>{{ d.label }}</strong><span class="small muted">{{ d.name }}</span>
                </button>
            </div>
        </div>
        <div class="row" style="justify-content:center;margin-top:8px;color:var(--ink-2)"><ThemeSwitch /></div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { api, setSession, store, IS_APP } from '../api';
import ThemeSwitch from '../components/ThemeSwitch.vue';

const router = useRouter();
const email = ref(''); const password = ref(''); const error = ref(''); const busy = ref(false);
const demo = ref([]);

onMounted(async () => { try { demo.value = await api('demo-accounts'); } catch {} });

async function done(res) { setSession(res.token, res.user); router.push('/aktiviteter'); }
async function login() {
    busy.value = true; error.value = '';
    try { await done(await api('login', { method: 'POST', body: { email: email.value, password: password.value } })); }
    catch (e) { error.value = e.message; } finally { busy.value = false; }
}
async function demoLogin(e) { await done(await api('demo-login', { method: 'POST', body: { email: e } })); }
</script>

<style scoped>
.login-wrap { max-width: 460px; margin: 0 auto; padding: calc(env(safe-area-inset-top, 0px) + 32px) 16px 40px; }
.login-hero { text-align: center; margin-bottom: 20px; }
.login-hero img { border-radius: 16px; }
.login-hero p { color: var(--ink-2); }
.demo-btn { flex-direction: column; align-items: flex-start; text-align: left; gap: 0; padding: 10px 12px; }
</style>
