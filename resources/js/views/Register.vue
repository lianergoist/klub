<template>
    <div style="max-width:520px;margin:0 auto;padding:calc(env(safe-area-inset-top, 0px) + 24px) 16px 40px">
        <RouterLink to="/login" class="small">‹ Tilbage</RouterLink>
        <h1 style="margin-top:8px">Meld dig ind</h1>
        <p class="muted">Udfyld dine oplysninger og betal kontingentet, så er du medlem med det samme. Børn og partner tilføjer du bagefter under <em>Profil → Familie</em>, og så skifter I til familiekontingent.</p>
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
                <h3>Medlemskab</h3>
                <label class="check"><input type="checkbox" v-model="f.passive"> Passivt medlem / støttemedlem (jeg deltager ikke i aktiviteter)</label>
                <div v-if="type" class="row between" style="margin-top:10px;flex-wrap:nowrap;align-items:flex-end">
                    <div><strong>{{ type.name }}</strong><div class="small muted">{{ type.fee_fmt }} pr. år<span v-if="type.months_left < 12"> · du betaler for {{ type.months_left }} måneder</span></div></div>
                    <div style="font-size:1.3rem;font-weight:700" class="num">{{ type.prorated_fmt }}</div>
                </div>
                <p v-else class="small muted" style="margin:8px 0 0">Angiv fødselsdato for at se prisen.</p>
            </div>

            <div class="banner info small">
                <strong>Sådan bruger vi dine oplysninger:</strong> Klubben gemmer navn, kontaktoplysninger og fødselsdato for at kunne administrere dit medlemskab, beregne kontingent, sende besked om aktiviteter og søge kommunalt tilskud. Du kan altid se, rette og hente dine data. Når du melder dig ud, bliver oplysningerne slettet efter den frist, bestyrelsen har fastsat. Regnskabsbilag gemmes dog så længe, loven kræver.
            </div>
            <label class="check"><input type="checkbox" v-model="f.consent"> Jeg giver samtykke til ovenstående (og på vegne af de børn, jeg tilføjer).</label>
            <p v-if="error" class="banner bad">{{ error }}</p>
            <button class="btn primary block" :disabled="busy">{{ type ? `Fortsæt til betaling (${type.prorated_fmt})` : 'Fortsæt til betaling' }}</button>
        </form>
    </div>
</template>
<script setup>
import { reactive, ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { api, setSession } from '../api';
const router = useRouter();
const f = reactive({ name: '', email: '', password: '', phone: '', birthdate: '', address: '', postal_code: '', city: '', passive: false, consent: false });
const error = ref(''); const busy = ref(false); const types = ref([]);
onMounted(async () => { types.value = await api('membership-types'); });
const age = computed(() => { if (!f.birthdate) return null; const b = new Date(f.birthdate), n = new Date(); let a = n.getFullYear() - b.getFullYear(); if (n < new Date(n.getFullYear(), b.getMonth(), b.getDate())) a--; return a; });
const type = computed(() => {
    if (f.passive) return types.value.find(t => t.code === 'passive');
    if (age.value === null) return null;
    const youth = types.value.find(t => t.code === 'youth');
    return youth && age.value <= youth.max_age ? youth : types.value.find(t => t.code === 'adult');
});
async function submit() {
    busy.value = true; error.value = '';
    try { const r = await api('register', { method: 'POST', body: f }); setSession(r.token, r.user); router.push(`/betal/${r.invoice_id}`); }
    catch (e) { error.value = e.message; } finally { busy.value = false; }
}
</script>
