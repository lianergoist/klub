<template>
    <div style="max-width:520px;margin:0 auto;padding:calc(env(safe-area-inset-top, 0px) + 24px) 16px 40px">
        <RouterLink to="/login" class="small">‹ Tilbage</RouterLink>
        <h1 style="margin-top:8px">Meld dig ind</h1>
        <p class="muted">Når du har meldt dig ind, godkender bestyrelsen dig. Herefter får du en opkrævning på kontingentet. Børn tilføjer du bagefter under <em>Familie</em> i din profil.</p>
        <form class="card stack" @submit.prevent="submit">
            <label class="field"><span>Fulde navn</span><input v-model="f.name" required></label>
            <label class="field"><span>E-mail</span><input v-model="f.email" type="email" required></label>
            <label class="field"><span>Adgangskode (mindst 8 tegn)</span><input v-model="f.password" type="password" minlength="8" required></label>
            <div class="grid c2">
                <label class="field"><span>Telefon</span><input v-model="f.phone" inputmode="tel"></label>
                <label class="field"><span>Fødselsdato</span><input v-model="f.birthdate" type="date" required></label>
            </div>
            <label class="field"><span>Adresse</span><input v-model="f.address"></label>
            <div class="grid c2">
                <label class="field"><span>Postnr.</span><input v-model="f.postal_code" inputmode="numeric"></label>
                <label class="field"><span>By</span><input v-model="f.city"></label>
            </div>
            <div class="banner info small">
                <strong>Sådan bruger vi dine oplysninger:</strong> Klubben gemmer navn, kontaktoplysninger og fødselsdato for at kunne administrere dit medlemskab, beregne kontingent, sende besked om aktiviteter og søge kommunalt tilskud. Du kan altid se, rette og hente dine data. Når du melder dig ud, bliver oplysningerne slettet efter den frist, bestyrelsen har fastsat. Regnskabsbilag gemmes dog så længe, loven kræver.
            </div>
            <label class="check"><input type="checkbox" v-model="f.consent"> Jeg giver samtykke til ovenstående (og på vegne af de børn, jeg tilføjer).</label>
            <p v-if="error" class="banner bad">{{ error }}</p>
            <button class="btn primary block" :disabled="busy">Send indmeldelse</button>
        </form>
    </div>
</template>
<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { api, setSession, toast } from '../api';
const router = useRouter();
const f = reactive({ name: '', email: '', password: '', phone: '', birthdate: '', address: '', postal_code: '', city: '', consent: false });
const error = ref(''); const busy = ref(false);
async function submit() {
    busy.value = true; error.value = '';
    try { const r = await api('register', { method: 'POST', body: f }); setSession(r.token, r.user); toast('Tak! Din indmeldelse afventer godkendelse.'); router.push('/profil'); }
    catch (e) { error.value = e.message; } finally { busy.value = false; }
}
</script>
