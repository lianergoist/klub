<template>
    <div v-if="!p" class="spinner"></div>
    <div v-else>
        <!-- Mobil/app: undersider med tilbage-link i stedet for en fanebjælke -->
        <template v-if="isMobile && cur">
            <RouterLink to="/profil" class="small back">‹ Min profil</RouterLink>
            <h1 style="margin-bottom:14px">{{ curTab.title }}</h1>
        </template>
        <template v-else>
        <h1>{{ p.profile.name }}</h1>
        <p class="muted" style="margin-bottom:14px">
            <span :class="['tag', p.profile.status === 'active' ? 'ok' : 'warn']">{{ p.profile.status_label }}</span>
            <span v-if="p.membership" class="tag ok">{{ p.membership.name }}<template v-if="p.membership.valid_to"> · til {{ dato(p.membership.valid_to) }}</template></span>
            <span v-else-if="p.upcoming" class="tag info">{{ p.upcoming.name }} · fra {{ dato(p.upcoming.valid_from) }}</span>
            <span v-else-if="p.profile.status !== 'pending'" class="tag bad">Intet gyldigt medlemskab</span>
        </p>
        </template>
        <div v-if="!isMobile" class="tabs">
            <RouterLink v-for="t in tabs" :key="t.id" :to="`/profil/${t.id}`" :class="{ on: cur === t.id }">{{ t.label }}</RouterLink>
        </div>

        <template v-if="isMobile && !cur">
            <div v-for="(g, gi) in menuGroups" :key="gi" class="card menu">
                <ul class="list">
                    <li v-for="t in g" :key="t.id"><RouterLink :to="`/profil/${t.id}`" class="mrow">
                        <Icon :name="t.icon" class="mi" />
                        <span class="mtext"><strong>{{ t.title }}</strong><span class="small muted">{{ t.hint }}</span></span>
                        <span v-if="t.badge" :class="['tag', t.badgeCls]">{{ t.badge }}</span>
                        <span class="chev" aria-hidden="true">›</span>
                    </RouterLink></li>
                </ul>
            </div>
            <button class="btn danger block" @click="logout">Log ud</button>
        </template>

        <form v-if="cur === 'oplysninger'" class="card stack" @submit.prevent="saveProfile">
            <label class="field"><span>Navn</span><input v-model="f.name" required></label>
            <div class="grid c2">
                <label class="field"><span>E-mail</span><input v-model="f.email" type="email" required></label>
                <label class="field"><span>Telefon</span><input v-model="f.phone"></label>
            </div>
            <label class="field"><span>Adresse</span><input v-model="f.address"></label>
            <div class="grid c3">
                <label class="field"><span>Postnr.</span><input v-model="f.postal_code"></label>
                <label class="field"><span>By</span><input v-model="f.city"></label>
                <label class="field"><span>Fødselsdato</span><input v-model="f.birthdate" type="date"></label>
            </div>
            <h3 style="margin-top:8px">Nødkontakt</h3>
            <div class="grid c2">
                <label class="field"><span>Navn</span><input v-model="f.emergency_name"></label>
                <label class="field"><span>Telefon</span><input v-model="f.emergency_phone"></label>
            </div>
            <button class="btn primary">Gem</button>
        </form>

        <div v-if="cur === 'familie'">
            <div v-if="!shop" class="spinner"></div>
            <template v-else>
                <div class="card" style="padding:4px 16px">
                    <ul class="list">
                        <li v-for="m in shop.household" :key="m.id" class="row between" style="flex-wrap:nowrap">
                            <span><strong>{{ m.name }}</strong> <span class="muted small">{{ m.age }} år</span>
                                <span class="small muted" style="display:block">{{ m.membership ? m.membership.name + (m.membership.valid_to ? ' · til ' + dato(m.membership.valid_to) : '') : m.upcoming ? m.upcoming.name + ' · fra ' + dato(m.upcoming.valid_from) : 'Intet gyldigt medlemskab' }}</span></span>
                            <span :class="['tag', m.membership ? 'ok' : m.upcoming ? 'info' : m.status === 'pending' ? 'warn' : 'bad']">{{ m.membership ? 'Medlem' : m.upcoming ? 'Medlem fra ' + dato(m.upcoming.valid_from) : m.status === 'pending' ? 'Afventer betaling' : 'Ikke medlem' }}</span>
                        </li>
                    </ul>
                </div>
                <p class="small muted">Som betaler for husstanden kan du tilmelde alle i familien til aktiviteter og betale deres regninger.</p>

                <div v-if="openPurchases('membership').length" class="card" style="border-color:var(--warn)">
                    <h2>Afventer betaling</h2>
                    <ul class="list">
                        <li v-for="x in openPurchases('membership')" :key="x.id">
                            <div><strong>{{ x.name }}</strong> · {{ x.amount_fmt }}</div>
                            <div class="small muted">{{ x.people.join(', ') }}. Gælder fra betalingen<template v-if="x.valid_to"> til {{ dato(x.valid_to) }}</template>. Nye personer tilføjes, når der er betalt.</div>
                            <div class="row" style="margin-top:8px">
                                <RouterLink :to="`/betal/${x.invoice_id}`" class="btn small primary">Betal nu</RouterLink>
                                <button v-if="x.can_cancel && store.user.status !== 'pending'" class="btn small" @click="cancelPurchase(x)">Fortryd</button>
                            </div>
                        </li>
                    </ul>
                </div>

                <form v-if="store.user.status !== 'pending' && (shop.can_add_people || withoutMembership.length)" class="card stack" @submit.prevent="buyMembership">
                    <h2>Køb medlemskab</h2>
                    <p class="small muted" style="margin:0">Vælg hvem medlemskabet skal dække. Skal der tilføjes et barn eller en partner til husstanden, gør du det her. Har resten af familien allerede medlemskab, købes et medlemskab til den nye person.</p>
                    <div v-if="withoutMembership.length">
                        <span class="lbl">Personer i husstanden uden medlemskab</span>
                        <label v-for="m in withoutMembership" :key="m.id" class="check"><input type="checkbox" :value="m.id" v-model="ms.member_ids"> {{ m.name }} <span class="muted small">({{ m.age }} år)</span></label>
                    </div>
                    <div v-if="shop.can_add_people">
                        <span class="lbl">Nye personer</span>
                        <div v-for="(n, i) in ms.new_members" :key="i" class="newp">
                            <div class="grid c2">
                                <label class="field"><span>Navn</span><input v-model="n.name" required></label>
                                <label class="field"><span>Fødselsdato</span><input v-model="n.birthdate" type="date" required></label>
                            </div>
                            <label class="field"><span>E-mail (kun hvis vedkommende skal have eget login)</span><input v-model="n.email" type="email"></label>
                            <div class="row between"><label class="check small"><input type="checkbox" v-model="n.consent" required> Jeg giver som forælder/værge samtykke til, at klubben behandler oplysningerne.</label>
                                <button type="button" class="btn small ghost danger" @click="ms.new_members.splice(i, 1)">Fjern</button></div>
                        </div>
                        <button type="button" class="btn small" @click="ms.new_members.push({ name: '', birthdate: '', email: '', consent: false })">+ Tilføj ny person</button>
                    </div>
                    <div v-if="msPeople.length">
                        <span class="lbl">Vælg medlemskab</span>
                        <PackageList v-model="ms.package_id" :packages="shop.memberships" :fits="(x) => compositionError(x, msPeople, shop.categories)" :price-of="(x) => priceFor(x, msPeople, shop.categories, shop.singles)" />
                    </div>
                    <p v-if="msError" class="banner warn small" style="margin:0">{{ msError }}</p>
                    <button class="btn primary" :disabled="!msPkg || !!msError || busy">{{ msPkg ? `Køb og betal ${kr(priceFor(msPkg, msPeople, shop.categories, shop.singles).price)}` : 'Vælg personer og medlemskab' }}</button>
                </form>
                <div v-if="store.user.status === 'pending'" class="banner warn">Betal først dit eget medlemskab. Derefter kan du købe medlemskab til andre i husstanden.</div>
            </template>
        </div>

        <div v-if="cur === 'beskeder'" class="card">
            <h2>Hvordan vil du have besked?</h2>
            <div class="stack">
                <label class="check"><input type="checkbox" v-model="f.notify_email" @change="saveProfile"> E-mail</label>
                <label class="check"><input type="checkbox" v-model="f.notify_push" @change="saveProfile"> Push-besked på telefonen <span class="small muted">(kræver appen)</span></label>
            </div>
            <h2 style="margin-top:20px">Beskeder fra andre medlemmer</h2>
            <label class="check"><input type="checkbox" v-model="f.allow_messages" @change="saveProfile"> Andre medlemmer må skrive til mig</label>
            <p class="small muted" style="margin-top:4px">Slår du det fra, kan andre medlemmer ikke skrive til dig, og du kan selv kun skrive med trænere og bestyrelse. Trænere og bestyrelse kan altid skrive til dig om klubbens aktiviteter.</p>
            <h2 style="margin-top:20px">Hvilke aktiviteter vil du høre om?</h2>
            <p class="small muted">Fravælg kategorier, du ikke er interesseret i. Du får stadig besked om aktiviteter, du er tilmeldt.</p>
            <ul class="list">
                <li v-for="c in cats" :key="c.id"><label class="check"><input type="checkbox" v-model="c.notify" @change="saveCats"><span class="tag"><span class="sw" :style="{ background: c.color }"></span>{{ c.name }}</span></label></li>
            </ul>
        </div>

        <Receipt v-if="cur === 'kvitteringer'" embedded />

        <div v-if="cur === 'regninger'">
            <div v-if="!invoices" class="spinner"></div>
            <div v-else class="card" style="padding:4px 16px">
                <ul class="list">
                    <li v-for="i in invoices" :key="i.id">
                        <div class="row between" style="flex-wrap:nowrap;align-items:flex-start">
                            <div><div><strong>{{ i.description }}</strong></div>
                                <div class="small muted">{{ i.status === 'paid' ? `Betalt ${dato(i.paid_at)} · ${i.method}` : `Forfald ${dato(i.due_on)}` }}<span v-if="i.refunded_fmt"> · refunderet {{ i.refunded_fmt }}</span></div></div>
                            <div style="text-align:right"><div class="num"><strong>{{ i.amount_fmt }}</strong></div><span :class="['tag', stCls(i.status)]">{{ i.status_label }}</span></div>
                        </div>
                        <RouterLink v-if="['open', 'overdue'].includes(i.status)" :to="`/betal/${i.id}`" class="btn small primary" style="margin-top:8px">Betal</RouterLink>
                    </li>
                    <li v-if="!invoices.length" class="muted">Ingen regninger.</li>
                </ul>
            </div>
        </div>

        <div v-if="cur === 'tilkob' && shop">
            <div v-if="shop.purchases.filter(x => x.kind === 'product').length" class="card">
                <h2>Mine tilkøb</h2>
                <ul class="list"><li v-for="o in shop.purchases.filter(x => x.kind === 'product')" :key="o.id" class="row between" style="flex-wrap:nowrap">
                    <span><strong>{{ o.name }}</strong><span v-if="o.resource"> · {{ o.resource }}</span> <span class="muted small" style="display:block">{{ o.people.join(', ') }}<span v-if="o.valid_to">, til {{ dato(o.valid_to) }}</span></span></span>
                    <span class="row" style="gap:6px;flex-wrap:nowrap">
                        <RouterLink v-if="['open', 'overdue'].includes(o.status)" :to="`/betal/${o.invoice_id}`" class="btn small primary">Betal</RouterLink>
                        <button v-if="o.can_cancel" class="btn small" @click="cancelPurchase(o)">Fortryd</button>
                        <span v-if="o.status === 'paid'" class="tag ok">Betalt</span>
                    </span>
                </li></ul>
            </div>
            <h3>Klubbens tilbud</h3>
            <p v-if="!shop.products.length" class="muted">Der er ingen tilkøb lige nu.</p>
            <div class="grid c2">
                <div v-for="x in shop.products" :key="x.id" class="card flat" style="margin:0">
                    <div class="row between" style="flex-wrap:nowrap;align-items:flex-start"><strong>{{ x.name }}</strong><span class="num" style="white-space:nowrap">{{ x.price_fmt }}</span></div>
                    <p class="small muted" style="margin:4px 0 8px">{{ x.description }} <span style="display:block">{{ x.composition_label }} · {{ x.valid_to ? `til ${dato(x.valid_to)}` : x.period_label }}<template v-if="x.requires_membership"> · kræver medlemskab</template></span></p>
                    <p v-if="x.needs_resource" class="small">{{ x.free_resources.length ? `${x.free_resources.length} ledige pladser` : 'Ingen ledige pladser' }}</p>
                    <button class="btn small" :disabled="x.needs_resource && !x.free_resources.length" @click="openBuy(x)">Vælg</button>
                </div>
            </div>
        </div>

        <div v-if="cur === 'data'" class="stack">
            <div class="card">
                <h2>Dine data</h2>
                <p>Du kan altid hente alle de oplysninger, klubben har om dig og din husstand: profil, roller, tilmeldinger og betalinger.</p>
                <button class="btn" @click="download('profile/export', 'mine-data.json')"><Icon name="download" style="width:18px" /> Hent mine data</button>
            </div>
            <div class="card">
                <h2>Roller i klubben</h2>
                <ul class="list"><li v-for="(r, i) in p.roles" :key="i" class="row between"><span>{{ r.label }}</span><span class="small muted">{{ dato(r.starts_on) }} – {{ r.ends_on ? dato(r.ends_on) : 'nu' }}</span></li>
                    <li v-if="!p.roles.length" class="muted">Medlem</li></ul>
            </div>
            <div class="card"><h2>Udseende</h2><ThemeSwitch /></div>
        </div>

        <Modal v-if="buy" :title="buy.name" @close="buy = null">
            <div class="stack">
                <div>
                    <span class="lbl">Hvem skal have det? <span class="muted">({{ buy.composition_label }})</span></span>
                    <label v-for="m in shop.household" :key="m.id" class="check" style="margin-top:6px"><input type="checkbox" :value="m.id" v-model="buyMembers"> {{ m.name }}<span v-if="buy.requires_membership && !m.membership" class="muted small"> (intet medlemskab)</span></label>
                </div>
                <label v-if="buy.needs_resource" class="field"><span>Plads</span><select v-model="buyRes"><option v-for="r in buy.free_resources" :key="r.id" :value="r.id">{{ r.label }}</option></select></label>
                <p v-if="buyError" class="banner warn small" style="margin:0">{{ buyError }}</p>
                <p style="margin:0"><strong>{{ buy.price_fmt }}</strong> <span class="muted">({{ buy.valid_to ? `${dato(buy.valid_from)} – ${dato(buy.valid_to)}` : buy.period_label }})</span></p>
                <button class="btn primary block" :disabled="!!buyError || busy" @click="order">Bestil og betal</button>
            </div>
        </Modal>
    </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { isMobile } from '../mobile';
import { useRouter } from 'vue-router';
import Icon from '../components/Icon.vue';
import Modal from '../components/Modal.vue';
import PackageList from '../components/PackageList.vue';
import Receipt from './Receipt.vue';
import { compositionError, priceFor } from '../packages';
import ThemeSwitch from '../components/ThemeSwitch.vue';
import { api, store, dato, kr, toast, download, logout as doLogout } from '../api';

const props = defineProps({ tab: String });
const router = useRouter();
const tabs = [
    { id: 'oplysninger', label: 'Oplysninger', title: 'Oplysninger', icon: 'user', hint: 'Navn, kontakt og nødkontakt' },
    { id: 'familie', label: 'Familie', title: 'Familie og medlemskab', icon: 'users', hint: 'Husstand og køb af medlemskab' },
    { id: 'beskeder', label: 'Beskeder', title: 'Beskeder og notifikationer', icon: 'bell', hint: 'E-mail, push og hvilke aktiviteter', group: 2 },
    { id: 'regninger', label: 'Regninger', title: 'Regninger', icon: 'money', hint: 'Betalte og ubetalte regninger' },
    { id: 'kvitteringer', label: 'Kvitteringer', title: 'Kvitteringer', icon: 'receipt', hint: 'Få penge tilbage for udlæg' },
    { id: 'tilkob', label: 'Tilkøb', title: 'Tilkøb', icon: 'bag', hint: 'Pladser, leje og andre tilkøb' },
    { id: 'data', label: 'Mine data', title: 'Mine data', icon: 'file', hint: 'Roller, udseende og hent dine data', group: 2 },
];
// På mobil er /profil selve menuen; på computer åbner den første fane
const cur = computed(() => props.tab || (isMobile.value ? null : 'oplysninger'));
const curTab = computed(() => tabs.find(t => t.id === cur.value) || tabs[0]);
const menuGroups = computed(() => {
    const c = p.value?.counts || {};
    const m = p.value?.membership, up = p.value?.upcoming;
    const extra = {
        familie: { hint: m ? m.name + (m.valid_to ? ' · til ' + dato(m.valid_to) : '') : up ? up.name + ' · fra ' + dato(up.valid_from) : 'Intet gyldigt medlemskab', ...(!m && !up ? { badge: 'Ikke medlem', badgeCls: 'bad' } : {}) },
        regninger: c.open_invoices ? { badge: c.open_invoices === 1 ? '1 åben' : `${c.open_invoices} åbne`, badgeCls: 'warn' } : {},
        kvitteringer: c.pending_claims ? { badge: `${c.pending_claims} afventer`, badgeCls: 'info' } : {},
    };
    const rows = tabs.map(t => ({ ...t, ...(extra[t.id] || {}) }));
    return [rows.filter(t => !t.group), rows.filter(t => t.group)];
});
const stCls = (s) => ({ paid: 'ok', open: 'info', overdue: 'bad', refunded: '', cancelled: '' })[s];

const p = ref(null); const f = reactive({}); const cats = ref([]); const invoices = ref(null); const shop = ref(null); const busy = ref(false);
const buy = ref(null); const buyMembers = ref([]); const buyRes = ref(null);
const ms = reactive({ member_ids: [], new_members: [], package_id: null });

const withoutMembership = computed(() => shop.value ? shop.value.household.filter(m => !m.membership && !m.upcoming && !openPurchases('membership').some(x => x.people.includes(m.name))) : []);
const openPurchases = (kind) => shop.value ? shop.value.purchases.filter(x => x.kind === kind && ['open', 'overdue'].includes(x.status)) : [];
const msPeople = computed(() => [...shop.value.household.filter(m => ms.member_ids.includes(m.id)), ...ms.new_members]);
const msPkg = computed(() => shop.value?.memberships.find(x => x.id === ms.package_id) || null);
const msError = computed(() => msPkg.value && msPeople.value.length ? compositionError(msPkg.value, msPeople.value, shop.value.categories) : null);
const buyError = computed(() => {
    if (!buy.value) return null;
    const people = shop.value.household.filter(m => buyMembers.value.includes(m.id));
    const noMember = buy.value.requires_membership ? people.find(m => !m.membership) : null;
    return noMember ? `${buy.value.name} kræver medlemskab, og det har ${noMember.name} ikke.` : compositionError(buy.value, people, shop.value.categories);
});

async function load() { p.value = await api('profile'); Object.assign(f, p.value.profile); }
async function saveProfile() {
    try { const r = await api('profile', { method: 'PUT', body: f }); Object.assign(f, r); p.value.profile = r; toast('Gemt'); }
    catch (e) { toast(e.message, 'error'); }
}
async function saveCats() { await api('profile/categories', { method: 'PUT', body: { items: cats.value } }); toast('Gemt'); }
async function loadShop() { shop.value = await api('profile/packages'); }
async function buyMembership() {
    busy.value = true;
    try {
        const r = await api('profile/purchases', { method: 'POST', body: { package_id: ms.package_id, member_ids: ms.member_ids, new_members: ms.new_members.map(n => ({ ...n, email: n.email || null })) } });
        Object.assign(ms, { member_ids: [], new_members: [], package_id: null });
        if (!r.paid) return router.push(`/betal/${r.invoice_id}`);
        toast('Medlemskabet er oprettet'); loadShop(); load();
    } catch (e) { toast(e.message, 'error'); } finally { busy.value = false; }
}
async function cancelPurchase(x) {
    if (!confirm(`Fortryd ${x.name}? Regningen annulleres.`)) return;
    try { await api(`profile/purchases/${x.id}/cancel`, { method: 'POST' }); toast('Købet er fortrudt'); loadShop(); } catch (e) { toast(e.message, 'error'); }
}
function openBuy(x) { buy.value = x; buyMembers.value = x.total_max === 1 ? [p.value.profile.id] : []; buyRes.value = x.free_resources?.[0]?.id; }
async function order() {
    busy.value = true;
    try { const r = await api('profile/purchases', { method: 'POST', body: { package_id: buy.value.id, member_ids: buyMembers.value, resource_id: buyRes.value } }); router.push(`/betal/${r.invoice_id}`); }
    catch (e) { toast(e.message, 'error'); } finally { busy.value = false; }
}
async function logout() { await doLogout(); router.push('/login'); }

async function onTab(t) {
    if (t === 'beskeder' && !cats.value.length) cats.value = await api('profile/categories');
    if (t === 'regninger') invoices.value = await api('profile/invoices');
    if (t === 'familie' || t === 'tilkob') await loadShop();
}
watch(cur, (t) => { onTab(t); if (!t) load(); }); // tilbage til menuen: opdatér tællerne
onMounted(async () => { await load(); onTab(cur.value); });
</script>

<style scoped>
.lbl { display: block; font-size: .85rem; font-weight: 600; color: var(--ink-2); margin-bottom: 6px; }
.back { display: inline-block; margin-bottom: 6px; }
.menu { padding: 2px 16px; }
.menu .list > li { padding: 0; }
.mrow { display: flex; align-items: center; gap: 12px; padding: 12px 0; color: var(--ink); min-height: 56px; }
.mi { width: 22px; height: 22px; color: var(--accent); flex-shrink: 0; }
.mtext { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.mtext .small { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.chev { color: var(--ink-3); font-size: 1.4rem; line-height: 1; }
.newp { border: 1px solid var(--line); border-radius: 12px; padding: 10px; margin-bottom: 8px; display: grid; gap: 8px; }
</style>
