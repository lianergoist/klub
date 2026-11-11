<template>
    <div style="max-width:520px;margin:0 auto;padding:calc(env(safe-area-inset-top, 0px) + 24px) 16px 40px">
        <RouterLink to="/login" class="small">‹ Tilbage</RouterLink>
        <h1 style="margin-top:8px">Meld dig ind</h1>
        <p class="muted">Udfyld dine oplysninger, vælg medlemskab og betal, så er du medlem med det samme.</p>
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
                <h3>Vælg medlemskab</h3>
                <p class="small muted" style="margin:0 0 10px">Skal flere i husstanden være med, kan du vælge et medlemskab til flere og tilføje dem her. Du kan også tilføje dem senere under Profil → Familie.</p>
                <div v-if="!offer" class="spinner"></div>
                <PackageList v-else v-model="f.package_id" :packages="offer.packages" :fits="mismatch" />
                <template v-if="pkg && (pkg.total_max === null || pkg.total_max > 1)">
                    <h3 style="margin-top:14px">Andre personer i medlemskabet</h3>
                    <div v-for="(m, i) in others" :key="i" class="grid other">
                        <label class="field"><span>Navn</span><input v-model="m.name" required></label>
                        <label class="field"><span>Fødselsdato</span><input v-model="m.birthdate" type="date" required></label>
                        <button type="button" class="btn small ghost danger" style="align-self:end" @click="others.splice(i, 1)">Fjern</button>
                    </div>
                    <button v-if="allowsMore(pkg, others.length + 1)" type="button" class="btn small" @click="others.push({ name: '', birthdate: '' })">+ Tilføj person</button>
                </template>
                <p v-if="f.package_id && error2" class="banner warn small" style="margin:10px 0 0">{{ error2 }}</p>
                <div v-if="pkg" class="row between" style="margin-top:12px;flex-wrap:nowrap;align-items:flex-end">
                    <div class="small muted">{{ pkg.name }}<br>{{ dato(pkg.valid_from) }}<template v-if="pkg.valid_to"> – {{ dato(pkg.valid_to) }}</template></div>
                    <div style="font-size:1.3rem;font-weight:700" class="num"><s v-if="credit" class="muted" style="font-weight:400;font-size:.9rem">{{ kr(price) }}</s> {{ kr(toPay) }}</div>
                </div>
            </div>

            <div class="banner info small">
                <strong>Sådan bruger vi dine oplysninger:</strong> Klubben gemmer navn, kontaktoplysninger og fødselsdato for at kunne administrere dit medlemskab, beregne kontingent, sende besked om aktiviteter og søge kommunalt tilskud. Du kan altid se, rette og hente dine data. Når du melder dig ud, bliver oplysningerne slettet efter den frist, bestyrelsen har fastsat. Regnskabsbilag gemmes dog så længe, loven kræver.
            </div>
            <label class="check"><input type="checkbox" v-model="f.consent"> Jeg giver samtykke til ovenstående (og på vegne af de personer, jeg tilføjer).</label>
            <p v-if="error" class="banner bad">{{ error }}</p>
            <button class="btn primary block" :disabled="busy || !pkg || !!error2">{{ !pkg ? 'Vælg et medlemskab' : toPay === 0 ? 'Meld dig ind' : `Fortsæt til betaling (${kr(toPay)})` }}</button>
        </form>
    </div>
</template>
<script setup>
import { reactive, ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import PackageList from '../components/PackageList.vue';
import { api, setSession, kr, dato, store, toast } from '../api';
import { compositionError, allowsMore } from '../packages';
const router = useRouter();
const f = reactive({ name: '', email: '', password: '', phone: '', birthdate: '', address: '', postal_code: '', city: '', package_id: null, consent: false });
const error = ref(''); const busy = ref(false); const offer = ref(null); const others = ref([]);
const guest = ref(null);
onMounted(async () => {
    offer.value = await api('packages/offer?kind=membership');
    if (store.user?.status === 'guest') {
        guest.value = await api('guest/status');
        Object.assign(f, { name: guest.value.name, email: guest.value.email, phone: guest.value.phone || '', birthdate: guest.value.birthdate || '' });
    }
});
const pkg = computed(() => offer.value?.packages.find(p => p.id === f.package_id) || null);
const people = computed(() => [{ name: f.name || 'Du', birthdate: f.birthdate }, ...others.value]);
// Vises under hver pakke, hvis de valgte personer ikke passer (fx et barn, der vælger et voksenmedlemskab)
const mismatch = (p) => f.birthdate ? compositionError(p, people.value.filter(x => x.birthdate), offer.value.categories) : null;
const error2 = computed(() => pkg.value ? (f.birthdate ? compositionError(pkg.value, people.value, offer.value.categories) : 'Udfyld din fødselsdato.') : null);
const price = computed(() => pkg.value?.price || 0);
const credit = computed(() => Math.min(guest.value?.credit || 0, price.value));
const toPay = computed(() => price.value - credit.value);
async function submit() {
    busy.value = true; error.value = '';
    try {
        const r = await api('register', { method: 'POST', body: { ...f, new_members: others.value } }); setSession(r.token, r.user);
        if (r.paid) { toast('Velkommen i klubben! Dit betalte beløb dækkede kontingentet.'); router.push('/aktiviteter'); }
        else router.push(`/betal/${r.invoice_id}`);
    }
    catch (e) { error.value = e.message; } finally { busy.value = false; }
}
</script>
<style scoped>
.other { grid-template-columns: 1fr 150px auto; gap: 8px; margin-bottom: 6px; }
@media (max-width: 480px) { .other { grid-template-columns: 1fr 1fr; } .other button { grid-column: 1 / -1; justify-self: start; } }
</style>
