<template>
    <div class="guest">
        <div class="row between" style="margin-bottom:12px">
            <h1 style="margin:0">Hej {{ d ? d.name.split(' ')[0] : '' }}</h1>
            <button class="btn small" @click="logout">Log ud</button>
        </div>
        <div v-if="!d" class="spinner"></div>
        <template v-else>
            <div v-for="i in d.open_invoices" :key="i.id" class="banner warn row between">
                <span>{{ i.description }} · <strong>{{ i.amount_fmt }}</strong></span>
                <RouterLink :to="`/betal/${i.id}`" class="btn small primary">Betal</RouterLink>
            </div>

            <h2>Mit klippekort</h2>
            <div v-if="!activeCards.length" class="card">
                <p style="margin:0 0 10px">Du har ikke et klippekort med klip tilbage.</p>
                <p v-if="!d.offers.length" class="small muted" style="margin:0">Der er ingen klippekort til salg lige nu.</p>
                <button v-for="o in d.offers" :key="o.id" class="btn primary" style="margin:0 8px 8px 0" :disabled="busy" @click="buy(o)">Køb {{ o.name }} – {{ o.price_fmt }}</button>
            </div>
            <div v-for="c in activeCards" :key="c.id" class="card klip">
                <div class="row between" style="flex-wrap:nowrap;align-items:flex-start">
                    <div><strong>{{ c.name }}</strong>
                        <div class="small muted">Gælder til {{ dato(c.valid_to) }}<template v-if="c.note"> · {{ c.note }}</template></div></div>
                    <div class="left"><span class="big">{{ c.uses_left }}</span><span class="small muted"> af {{ c.uses }} klip</span></div>
                </div>
                <div class="dots"><i v-for="n in c.uses" :key="n" :class="{ used: n > c.uses_left }"></i></div>
                <p v-if="!c.paid" class="small" style="color:var(--warn);margin:8px 0 0">Kortet kan bruges, når det er betalt.</p>
            </div>
            <p v-if="activeCards.length && d.offers.length && runningOut" class="small"><a href="#" @click.prevent="buy(d.offers[0])">Køb et nyt {{ d.offers[0].name }} ({{ d.offers[0].price_fmt }})</a></p>

            <h2 style="margin-top:22px">Træninger</h2>
            <p class="small muted" style="margin-top:-6px">Tilmelding bruger ét klip. Melder du fra senest {{ d.activities[0]?.cancel_hours ?? 24 }} timer før start, får du klippet tilbage.</p>
            <div v-if="!d.activities.length" class="card empty">Der er ingen træninger åbne for klippekort lige nu.</div>
            <div v-else class="card" style="padding:4px 16px">
                <ul class="list">
                    <li v-for="a in d.activities" :key="a.id" class="row between" style="flex-wrap:nowrap;align-items:flex-start;gap:10px">
                        <div style="min-width:0">
                            <strong>{{ datoLang(a.starts_at) }}</strong> kl. {{ klok(a.starts_at) }}–{{ klok(a.ends_at) }}
                            <div class="small muted">{{ a.title }}<span v-if="a.location"> · {{ a.location }}</span><span v-if="a.max"> · {{ a.signed_up }}/{{ a.max }} tilmeldt</span></div>
                        </div>
                        <div style="flex-shrink:0;text-align:right">
                            <template v-if="a.mine">
                                <span class="tag ok">Tilmeldt</span>
                                <button v-if="!a.cancel_locked" class="btn small" style="margin-top:6px;display:block" :disabled="busy" @click="cancel(a)">Afmeld</button>
                            </template>
                            <span v-else-if="a.is_full" class="tag">Fuldt</span>
                            <span v-else-if="a.deadline_passed" class="tag">Lukket</span>
                            <button v-else class="btn small primary" :disabled="busy || !a.has_card" :title="a.has_card ? '' : 'Kræver et klippekort med klip tilbage'" @click="signup(a)">Tilmeld (1 klip)</button>
                        </div>
                    </li>
                </ul>
            </div>
            <p class="small muted" style="margin-top:18px">Vil du være medlem af klubben? <RouterLink to="/bliv-medlem">Meld dig ind</RouterLink>.</p>
        </template>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { api, toast, dato, datoLang, klok, logout as doLogout } from '../api';
const router = useRouter();
const d = ref(null); const busy = ref(false);
const activeCards = computed(() => d.value ? d.value.cards.filter(c => !c.expired && (c.uses_left > 0 || !c.paid)) : []);
// "Køb et nyt kort" først, når kortet er ved at være brugt op eller udløbe
const runningOut = computed(() => activeCards.value.reduce((n, c) => n + c.uses_left, 0) <= 2
    || activeCards.value.every(c => c.valid_to && (new Date(c.valid_to) - new Date()) / 864e5 <= 30));
async function load() { d.value = await api('klip'); }
onMounted(load);
async function run(fn, msg) {
    busy.value = true;
    try { d.value = await fn(); if (msg) toast(msg); } catch (e) { toast(e.message, 'error'); } finally { busy.value = false; }
}
const signup = (a) => run(() => api(`klip/activities/${a.id}/signup`, { method: 'POST' }), 'Du er tilmeldt – der er brugt et klip');
const cancel = (a) => run(() => api(`klip/activities/${a.id}/cancel`, { method: 'POST' }), 'Du er afmeldt, og klippet er givet tilbage');
async function buy(o) {
    busy.value = true;
    try { const r = await api('klip/buy', { method: 'POST', body: { package_id: o.id } }); if (!r.paid) return router.push(`/betal/${r.invoice_id}`); await load(); }
    catch (e) { toast(e.message, 'error'); } finally { busy.value = false; }
}
async function logout() { await doLogout(); router.push('/login'); }
</script>

<style scoped>
.guest { max-width: 640px; margin: 0 auto; padding: calc(env(safe-area-inset-top, 0px) + 20px) 16px 40px; }
.klip .left { text-align: right; white-space: nowrap; }
.klip .big { font-size: 2rem; font-weight: 800; color: var(--accent); }
.dots { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 10px; }
.dots i { width: 18px; height: 18px; border-radius: 50%; background: var(--accent); }
.dots i.used { background: var(--surface-2); border: 1px solid var(--line); }
</style>
