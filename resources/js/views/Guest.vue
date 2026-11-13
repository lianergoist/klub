<template>
    <div class="guest">
        <header class="ghead">
            <img class="brand-default" src="/icon.svg" alt="" width="52" height="52">
            <img class="brand-logo" src="/brand/surfhorsens-logo.webp" alt="SurfHorsens" style="width:170px;height:auto;margin:0 auto">
            <h1>Prøv SurfHorsens</h1>
            <p class="muted">Ikke medlem endnu? {{ intro }}</p>
        </header>

        <div v-if="!offers" class="spinner"></div>
        <template v-else-if="!picked">
            <p v-if="only" class="small" style="margin:0 4px 10px"><RouterLink :to="{ query: {} }">← Se alle tilbud</RouterLink></p>
            <div v-for="o in shown" :key="o.code" class="card">
                <div class="row between" style="align-items:baseline;flex-wrap:nowrap">
                    <h2 style="margin:0">{{ o.label }}</h2>
                    <div class="price">{{ o.price_fmt }}</div>
                </div>
                <p v-if="o.text" class="small muted" style="margin:4px 0 0">{{ o.text }}</p>
                <ul class="list" style="margin-top:10px">
                    <li v-for="a in o.activities" :key="a.id" class="row between" style="flex-wrap:nowrap">
                        <div><strong>{{ datoLang(a.starts_at) }}</strong> kl. {{ klok(a.starts_at) }}<div class="small muted">{{ a.title }}<span v-if="a.location"> · {{ a.location }}</span></div></div>
                        <button class="btn small primary" :disabled="a.full" @click="pick(o, a)">{{ a.full ? 'Fuldt' : 'Vælg' }}</button>
                    </li>
                    <li v-if="!o.activities.length" class="muted small">Ingen datoer lige nu – kig forbi igen senere.</li>
                </ul>
            </div>
            <p class="small muted" style="text-align:center">Allerede medlem? <RouterLink to="/login">Log ind</RouterLink></p>
        </template>

        <form v-else class="card stack" autocomplete="on" @submit.prevent="book">
            <a href="#" class="small" @click.prevent="picked = null">‹ Vælg en anden dato</a>
            <div class="banner info" style="margin-bottom:0">
                <strong>{{ picked.offer.label }}</strong>: {{ picked.activity.title }}<br>
                <span>{{ datoLang(picked.activity.starts_at) }}</span> kl. {{ klok(picked.activity.starts_at) }}–{{ klok(picked.activity.ends_at) }} · <strong>{{ picked.offer.price_fmt }}</strong>
            </div>
            <label class="field"><span>Fulde navn</span><input v-model="f.name" name="name" id="name" required autocomplete="name"></label>
            <div class="grid c2">
                <label class="field"><span>E-mail</span><input v-model="f.email" name="email" id="email" type="email" required autocomplete="email" autocapitalize="off" spellcheck="false"></label>
                <label class="field"><span>Telefon</span><input v-model="f.phone" name="tel" id="tel" type="tel" required autocomplete="tel"></label>
            </div>
            <label class="field"><span>Fødselsdato</span><input v-model="f.birthdate" name="bday" id="bday" type="date" required autocomplete="bday"></label>
            <div v-if="minor" class="grid c2">
                <label class="field"><span>Forælders navn</span><input v-model="f.guardian_name" name="guardian-name" autocomplete="off" required></label>
                <label class="field"><span>Forælders telefon</span><input v-model="f.guardian_phone" name="guardian-tel" type="tel" autocomplete="off" required></label>
            </div>
            <p class="small muted" style="margin:0">Vi bruger dine oplysninger til tilmeldingen og til at kontakte dig om {{ /^\p{Lu}\p{Lu}/u.test(picked.offer.label) ? picked.offer.label : picked.offer.label.charAt(0).toLowerCase() + picked.offer.label.slice(1) }}. Melder du dig ikke ind, slettes de efter den frist, klubben har fastsat.</p>
            <label class="check small"><input type="checkbox" v-model="f.consent"> Jeg giver samtykke til ovenstående<span v-if="minor"> (som forælder)</span>.</label>
            <p v-if="error" class="banner bad">{{ error }}</p>
            <button class="btn primary block" :disabled="busy">Fortsæt til betaling ({{ picked.offer.price_fmt }})</button>
        </form>
    </div>
</template>
<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { api, setSession, datoLang, klok } from '../api';
const router = useRouter();
const offers = ref(null); const picked = ref(null);
// ?tilbud=ID (fx fra linket på login-siden) viser kun det ene tilbud
const route = useRoute();
const only = computed(() => Number(route.query.tilbud) || null);
const shown = computed(() => (offers.value || []).filter(o => !only.value || o.code === only.value)); const error = ref(''); const busy = ref(false);
const f = reactive({ name: '', email: '', phone: '', birthdate: '', guardian_name: '', guardian_phone: '', consent: false });
const minor = computed(() => { if (!f.birthdate) return false; const b = new Date(f.birthdate), n = new Date(); let a = n.getFullYear() - b.getFullYear(); if (n < new Date(n.getFullYear(), b.getMonth(), b.getDate())) a--; return a < 18; });
onMounted(async () => { offers.value = await api('guest/offers'); });
// Fx "Book en prøvetræning eller et SUP-kursus." – ud fra klubbens tilbud
const lc = (x) => /^\p{Lu}\p{Lu}/u.test(x) ? x : x.charAt(0).toLowerCase() + x.slice(1);
const intro = computed(() => {
    const o = offers.value || [];
    if (!o.length) return 'Her kan du booke klubbens tilbud til ikke-medlemmer.';
    const names = o.map(x => lc(x.label)); const list = names.length > 1 ? names.slice(0, -1).join(', ') + ' eller ' + names.at(-1) : names[0];
    const credit = o.every(x => x.creditable) ? ' Melder du dig ind bagefter, trækkes beløbet fra kontingentet.' : o.some(x => x.creditable) ? ' Melder du dig ind bagefter, trækkes beløbet for nogle af tilbuddene fra kontingentet.' : '';
    return `Book ${list}.${credit}`;
});
function pick(offer, activity) { picked.value = { offer, activity }; error.value = ''; window.scrollTo(0, 0); }
async function book() {
    busy.value = true; error.value = '';
    try {
        const r = await api('guest/book', { method: 'POST', body: { ...f, activity_id: picked.value.activity.id } });
        setSession(r.token, r.user); router.push(`/betal/${r.invoice_id}`);
    } catch (e) { error.value = e.message; } finally { busy.value = false; }
}
</script>
<style scoped>
.guest { max-width: 560px; margin: 0 auto; padding: calc(env(safe-area-inset-top, 0px) + 24px) 16px 40px; }
.ghead { text-align: center; margin-bottom: 18px; }
.ghead img { border-radius: 14px; }
.price { font-size: 1.25rem; font-weight: 700; white-space: nowrap; margin-left: 12px; }
</style>
