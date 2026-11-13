<template>
    <div class="guest">
        <header class="ghead">
            <img class="brand-default" src="/icon.svg" alt="" width="52" height="52">
            <img class="brand-logo" src="/brand/surfhorsens-logo.webp" alt="SurfHorsens" style="width:170px;height:auto;margin:0 auto">
            <h1>Klippekort</h1>
            <p class="muted">Ikke medlem, men vil gerne være med til fx SUP-træning? Køb et klippekort, opret et login og tilmeld dig de træninger, du vil med til. Hver gang bruger ét klip.</p>
        </header>

        <div v-if="!offers" class="spinner"></div>
        <template v-else>
            <div v-for="o in offers" :key="o.id" :class="['card', 'offer', { on: f.package_id === o.id }]" @click="f.package_id = o.id">
                <div class="row between" style="align-items:flex-start;flex-wrap:nowrap">
                    <div><h2 style="margin:0">{{ o.name }}</h2>
                        <p class="small muted" style="margin:4px 0 0">{{ o.uses }} klip · gælder {{ o.period_label }} · {{ o.categories.join(', ') }}</p>
                        <p v-if="o.description" class="small" style="margin:6px 0 0">{{ o.description }}</p></div>
                    <div class="price">{{ o.price_fmt }}</div>
                </div>
            </div>
            <p v-if="!offers.length" class="card muted">Der er ingen klippekort til salg lige nu.</p>

            <form class="card stack" autocomplete="on" @submit.prevent="submit">
                <h2 style="margin:0">Opret login</h2>
                <label class="field"><span>Fulde navn</span><input v-model="f.name" name="name" id="name" required autocomplete="name"></label>
                <div class="grid c2">
                    <label class="field"><span>E-mail</span><input v-model="f.email" name="email" id="email" type="email" required autocomplete="email" autocapitalize="off" spellcheck="false"></label>
                    <label class="field"><span>Telefon</span><input v-model="f.phone" name="tel" id="tel" type="tel" required autocomplete="tel"></label>
                </div>
                <div class="grid c2">
                    <label class="field"><span>Fødselsdato</span><input v-model="f.birthdate" name="bday" id="bday" type="date" required autocomplete="bday"></label>
                    <label class="field"><span>Adgangskode (mindst 8 tegn)</span><input v-model="f.password" name="new-password" id="new-password" type="password" minlength="8" required autocomplete="new-password"></label>
                </div>
                <div class="banner info small" style="margin:0">Klubben gemmer dine oplysninger for at kunne holde styr på dit klippekort og dine tilmeldinger. Du kan altid se, rette og hente dine data.</div>
                <label class="check"><input type="checkbox" v-model="f.consent"> Jeg giver samtykke til ovenstående.</label>
                <p v-if="error" class="banner bad" style="margin:0">{{ error }}</p>
                <button class="btn primary block" :disabled="busy || !chosen">{{ chosen ? `Opret login og betal ${chosen.price_fmt}` : 'Vælg et klippekort' }}</button>
            </form>
            <p class="small muted" style="text-align:center">Har du allerede et login? <RouterLink to="/login">Log ind</RouterLink> · Medlem? Så tilmelder du dig under Aktiviteter.</p>
        </template>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { api, setSession, toast } from '../api';
const router = useRouter();
const offers = ref(null); const busy = ref(false); const error = ref('');
const f = reactive({ package_id: null, name: '', email: '', phone: '', birthdate: '', password: '', consent: false });
const chosen = computed(() => offers.value?.find(o => o.id === f.package_id) || null);
onMounted(async () => { offers.value = await api('punchcards'); if (offers.value.length === 1) f.package_id = offers.value[0].id; });
async function submit() {
    busy.value = true; error.value = '';
    try {
        const r = await api('punchcards/signup', { method: 'POST', body: f });
        setSession(r.token, r.user);
        if (r.invoice_id) return router.push(`/betal/${r.invoice_id}`);
        toast('Klippekortet er klar');
        router.push('/klip');
    } catch (e) { error.value = e.message; } finally { busy.value = false; }
}
</script>

<style scoped>
.guest { max-width: 560px; margin: 0 auto; padding: calc(env(safe-area-inset-top, 0px) + 24px) 16px 40px; }
.ghead { text-align: center; margin-bottom: 18px; }
.ghead img { border-radius: 14px; }
.price { font-size: 1.25rem; font-weight: 700; white-space: nowrap; margin-left: 12px; }
.offer { cursor: pointer; border: 2px solid transparent; }
.offer.on { border-color: var(--accent); }
</style>
