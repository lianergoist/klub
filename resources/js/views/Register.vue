<template>
    <div style="max-width:520px;margin:0 auto;padding:calc(env(safe-area-inset-top, 0px) + 24px) 16px 40px">
        <RouterLink to="/login" class="small">‹ Tilbage</RouterLink>
        <h1 style="margin-top:8px">Meld dig ind</h1>
        <p class="muted">Udfyld dine oplysninger og betal kontingentet, så er du medlem med det samme. Børn og partner tilføjer du bagefter under <em>Profil → Familie</em>.</p>
        <div v-if="guest?.credit" class="banner ok">Du har betalt <strong>{{ guest.credit_fmt }}</strong> for {{ guest.bookings.length ? 'prøvetræning/kursus' : 'et tilbud' }} som gæst. Beløbet trækkes fra kontingentet.</div>
        <form class="card stack" @submit.prevent="submit">
            <label class="field"><span>Fulde navn</span><input v-model="f.name" required autocomplete="name"></label>
            <label class="field"><span>E-mail</span><input v-model="f.email" type="email" required autocomplete="email"></label>
            <label class="field"><span>Adgangskode (mindst 8 tegn)</span><input v-model="f.password" type="password" minlength="8" required autocomplete="new-password"></label>
            <div class="grid c2">
                <label class="field"><span>Telefon</span><input v-model="f.phone" inputmode="tel" autocomplete="tel"></label>
                <label class="field"><span>Fødselsdato</span><input v-model="f.birthdate" type="date" required></label>
            </div>
            <label class="field"><span>Adresse</span><input v-model="f.address" autocomplete="street-address"></label>
            <div class="grid c2">
                <label class="field"><span>Postnr.</span><input v-model="f.postal_code" inputmode="numeric" autocomplete="postal-code"></label>
                <label class="field"><span>By</span><input v-model="f.city" autocomplete="address-level2"></label>
            </div>

            <div class="card flat" style="margin:4px 0 0;background:var(--surface-2)">
                <h3>Medlemskab · sæson {{ t?.season }}</h3>
                <p class="small muted" style="margin:0 0 8px">Træningssæsonen løber fra 1. maj til 30. april.</p>
                <label class="check"><input type="checkbox" v-model="f.passive"> Støttemedlem (jeg deltager ikke i aktiviteter)</label>
                <div v-if="t" class="row between" style="margin-top:10px;flex-wrap:nowrap;align-items:flex-end">
                    <div><strong>{{ f.passive ? 'Støttemedlem' : 'Enkelt medlem' }}</strong>
                        <div class="small muted">{{ f.passive ? 'Gælder sæsonen ud' : t.half ? 'Halv sæson – indmeldelse fra 1. august (fuld sæson: ' + kr(t.single.full) + ')' : 'Fuld sæson' }}</div></div>
                    <div style="font-size:1.3rem;font-weight:700" class="num"><s v-if="credit" class="muted" style="font-weight:400;font-size:.9rem">{{ kr(price) }}</s> {{ kr(toPay) }}</div>
                </div>
                <p class="small muted" style="margin:10px 0 0">Er I flere i husstanden, tilføjer du dem bagefter under Profil → Familie. 3 personer betaler {{ t ? kr(t.family3) : '' }} samlet, 4 eller flere {{ t ? kr(t.family4) : '' }}.</p>
            </div>

            <div class="banner info small">
                <strong>Sådan bruger vi dine oplysninger:</strong> Klubben gemmer navn, kontaktoplysninger og fødselsdato for at kunne administrere dit medlemskab, beregne kontingent, sende besked om aktiviteter og søge kommunalt tilskud. Du kan altid se, rette og hente dine data. Når du melder dig ud, bliver oplysningerne slettet efter den frist, bestyrelsen har fastsat. Regnskabsbilag gemmes dog så længe, loven kræver.
            </div>
            <label class="check"><input type="checkbox" v-model="f.consent"> Jeg giver samtykke til ovenstående (og på vegne af de børn, jeg tilføjer).</label>
            <p v-if="error" class="banner bad">{{ error }}</p>
            <button class="btn primary block" :disabled="busy">{{ !t ? 'Fortsæt til betaling' : toPay === 0 ? 'Meld dig ind' : `Fortsæt til betaling (${kr(toPay)})` }}</button>
        </form>
    </div>
</template>
<script setup>
import { reactive, ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { api, setSession, kr, store, toast } from '../api';
const router = useRouter();
const f = reactive({ name: '', email: '', password: '', phone: '', birthdate: '', address: '', postal_code: '', city: '', passive: false, consent: false });
const error = ref(''); const busy = ref(false); const t = ref(null);
const guest = ref(null);
onMounted(async () => {
    t.value = await api('membership-types');
    if (store.user?.status === 'guest') {
        guest.value = await api('guest/status');
        Object.assign(f, { name: guest.value.name, email: guest.value.email, phone: guest.value.phone || '', birthdate: guest.value.birthdate || '' });
    }
});
const credit = computed(() => Math.min(guest.value?.credit || 0, price.value));
const toPay = computed(() => price.value - credit.value);
const price = computed(() => !t.value ? 0 : f.passive ? t.value.passive.price : t.value.single.price);
async function submit() {
    busy.value = true; error.value = '';
    try {
        const r = await api('register', { method: 'POST', body: f }); setSession(r.token, r.user);
        if (r.paid) { toast('Velkommen i klubben! Dit betalte beløb dækkede kontingentet.'); router.push('/aktiviteter'); }
        else router.push(`/betal/${r.invoice_id}`);
    }
    catch (e) { error.value = e.message; } finally { busy.value = false; }
}
</script>
