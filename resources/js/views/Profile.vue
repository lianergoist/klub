<template>
    <div v-if="!p" class="spinner"></div>
    <div v-else>
        <h1>{{ p.profile.name }}</h1>
        <p class="muted" style="margin-bottom:14px">
            {{ p.profile.membership_type || 'Medlemstype ikke fastsat' }} · <span :class="['tag', p.profile.status === 'active' ? 'ok' : 'warn']">{{ p.profile.status_label }}</span>
            <span v-if="p.membership_valid" class="tag ok">Kontingent {{ year }} betalt</span>
            <span v-else-if="p.profile.status !== 'pending'" class="tag bad">Kontingent {{ year }} mangler</span>
        </p>
        <div class="tabs">
            <RouterLink v-for="t in tabs" :key="t.id" :to="`/profil/${t.id}`" :class="{ on: cur === t.id }">{{ t.label }}</RouterLink>
        </div>

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
            <div class="card" style="padding:4px 16px">
                <ul class="list">
                    <li v-for="m in p.household" :key="m.id" class="row between">
                        <span><strong>{{ m.name }}</strong> <span class="muted small">{{ m.age }} år · {{ m.membership_type || '–' }}</span></span>
                        <span :class="['tag', m.status === 'active' ? 'ok' : 'warn']">{{ m.status_label }}</span>
                    </li>
                </ul>
            </div>
            <p class="small muted">Som betaler for husstanden kan du tilmelde alle i familien til aktiviteter og betale deres regninger.</p>
            <div v-if="p.pending_upgrade" class="card" style="border-color:var(--warn)">
                <h2>Afventer betaling</h2>
                <p>Skiftet til familiekontingent og tilføjelsen af <strong>{{ p.pending_upgrade.members.join(', ') }}</strong> træder i kraft, når betalingen på <strong>{{ p.pending_upgrade.amount_fmt }}</strong> er gennemført.</p>
                <div class="row">
                    <RouterLink :to="`/betal/${p.pending_upgrade.invoice_id}`" class="btn primary">Betal nu</RouterLink>
                    <button class="btn" @click="cancelUpgrade">Fortryd</button>
                </div>
            </div>
            <div v-if="store.user.status === 'pending'" class="banner warn">Betal først dit eget kontingent. Derefter kan du tilføje familiemedlemmer.</div>
            <form v-else-if="store.user.is_payer" class="card stack" @submit.prevent="addChild">
                <h2>Tilføj barn eller partner</h2>
                <p v-if="p.upgrade_price_fmt" class="banner info small" style="margin:0">
                    Du har i dag <strong>{{ p.profile.membership_type }}</strong>. Når du tilføjer et familiemedlem, skifter husstanden til <strong>familiekontingent</strong>, og du betaler forskellen for resten af året: <strong>{{ p.upgrade_price_fmt }}</strong>.
                    Personen bliver først tilføjet, når betalingen er gennemført. Flere personer kan tilføjes på samme betaling.
                </p>
                <p v-else class="small muted" style="margin:0">I har familiekontingent, så det koster ikke ekstra at tilføje flere i husstanden.</p>
                <div class="grid c2">
                    <label class="field"><span>Navn</span><input v-model="child.name" required></label>
                    <label class="field"><span>Fødselsdato</span><input v-model="child.birthdate" type="date" required></label>
                </div>
                <label class="field"><span>E-mail (kun hvis vedkommende skal have eget login)</span><input v-model="child.email" type="email"></label>
                <div class="grid c2">
                    <label class="field"><span>Nødkontakt</span><input v-model="child.emergency_name" :placeholder="p.profile.name"></label>
                    <label class="field"><span>Nødkontakt, telefon</span><input v-model="child.emergency_phone" :placeholder="p.profile.phone"></label>
                </div>
                <label class="check small"><input type="checkbox" v-model="child.consent" required> Jeg giver som forælder/værge samtykke til, at klubben behandler oplysningerne.</label>
                <button class="btn primary">{{ p.upgrade_price_fmt ? (p.pending_upgrade ? 'Tilføj til samme betaling' : 'Tilføj og gå til betaling') : 'Tilføj' }}</button>
            </form>
        </div>

        <div v-if="cur === 'beskeder'" class="card">
            <h2>Hvordan vil du have besked?</h2>
            <div class="stack">
                <label class="check"><input type="checkbox" v-model="f.notify_email" @change="saveProfile"> E-mail</label>
                <label class="check"><input type="checkbox" v-model="f.notify_push" @change="saveProfile"> Push-besked på telefonen <span class="tag">næste fase</span></label>
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

        <div v-if="cur === 'tilkob' && prod">
            <div v-if="prod.mine.length" class="card">
                <h2>Mine tilkøb</h2>
                <ul class="list"><li v-for="o in prod.mine" :key="o.id" class="row between">
                    <span><strong>{{ o.product }}</strong><span v-if="o.resource"> · {{ o.resource }}</span> <span class="muted small">{{ o.member }}<span v-if="o.valid_to">, til {{ dato(o.valid_to) }}</span></span></span>
                    <RouterLink v-if="['open', 'overdue'].includes(o.invoice_status)" :to="`/betal/${o.invoice_id}`" class="btn small primary">Betal</RouterLink>
                    <span v-else class="tag ok">Betalt</span>
                </li></ul>
            </div>
            <h3>Klubbens tilbud</h3>
            <div class="grid c2">
                <div v-for="x in prod.catalog" :key="x.id" class="card flat" style="margin:0">
                    <div class="row between"><strong>{{ x.name }}</strong><span class="num">{{ x.price_fmt }}</span></div>
                    <p class="small muted" style="margin:4px 0 8px">{{ x.description }} <span>({{ billing[x.billing] }})</span></p>
                    <p v-if="x.needs_resource" class="small">{{ x.free_resources.length ? `${x.free_resources.length} ledige pladser` : 'Ingen ledige pladser' }}</p>
                    <button class="btn small" :disabled="x.needs_resource && !x.free_resources.length" @click="buy = x; buyMember = p.profile.id; buyRes = x.free_resources[0]?.id">Vælg</button>
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
            <button class="btn danger mobile-only" @click="logout">Log ud</button>
        </div>

        <Modal v-if="buy" :title="buy.name" @close="buy = null">
            <div class="stack">
                <label class="field"><span>Til hvem?</span><select v-model="buyMember"><option v-for="m in p.household" :key="m.id" :value="m.id">{{ m.name }}</option></select></label>
                <label v-if="buy.needs_resource" class="field"><span>Plads</span><select v-model="buyRes"><option v-for="r in buy.free_resources" :key="r.id" :value="r.id">{{ r.label }}</option></select></label>
                <p><strong>{{ buy.price_fmt }}</strong> <span class="muted">({{ billing[buy.billing] }})</span></p>
                <button class="btn primary block" @click="order">Bestil og betal</button>
            </div>
        </Modal>
    </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import Icon from '../components/Icon.vue';
import Modal from '../components/Modal.vue';
import ThemeSwitch from '../components/ThemeSwitch.vue';
import { api, store, dato, toast, download, setSession } from '../api';

const props = defineProps({ tab: String });
const router = useRouter();
const tabs = [{ id: 'oplysninger', label: 'Oplysninger' }, { id: 'familie', label: 'Familie' }, { id: 'beskeder', label: 'Beskeder' }, { id: 'regninger', label: 'Regninger' }, { id: 'tilkob', label: 'Tilkøb' }, { id: 'data', label: 'Mine data' }];
const cur = computed(() => props.tab || 'oplysninger');
const year = new Date().getFullYear();
const billing = { once: 'engangsbeløb', season: 'pr. sæson', year: 'pr. år' };
const stCls = (s) => ({ paid: 'ok', open: 'info', overdue: 'bad', refunded: '', cancelled: '' })[s];

const p = ref(null); const f = reactive({}); const cats = ref([]); const invoices = ref(null); const prod = ref(null);
const child = reactive({ name: '', birthdate: '', email: '', emergency_name: '', emergency_phone: '', consent: false });
const buy = ref(null); const buyMember = ref(null); const buyRes = ref(null);

async function load() { p.value = await api('profile'); Object.assign(f, p.value.profile); }
async function saveProfile() {
    try { const r = await api('profile', { method: 'PUT', body: f }); Object.assign(f, r); p.value.profile = r; toast('Gemt'); }
    catch (e) { toast(e.message, 'error'); }
}
async function saveCats() { await api('profile/categories', { method: 'PUT', body: { items: cats.value } }); toast('Gemt'); }
async function addChild() {
    try {
        const r = await api('profile/household', { method: 'POST', body: { ...child, email: child.email || null, emergency_name: child.emergency_name || p.value.profile.name, emergency_phone: child.emergency_phone || p.value.profile.phone } });
        Object.assign(child, { name: '', birthdate: '', email: '', emergency_name: '', emergency_phone: '', consent: false });
        if (!r.added) return router.push(`/betal/${r.invoice_id}`);
        toast(`${r.member.name} er tilføjet til husstanden`); load();
    } catch (e) { toast(e.message, 'error'); }
}
async function cancelUpgrade() { await api('profile/household/upgrade', { method: 'DELETE' }); toast('Tilføjelsen er annulleret'); load(); }
async function order() {
    try { const r = await api(`products/${buy.value.id}/order`, { method: 'POST', body: { member_id: buyMember.value, resource_id: buyRes.value } }); router.push(`/betal/${r.invoice_id}`); }
    catch (e) { toast(e.message, 'error'); }
}
async function logout() { try { await api('logout', { method: 'POST' }); } catch {} setSession(null, null); router.push('/login'); }

async function onTab(t) {
    if (t === 'beskeder' && !cats.value.length) cats.value = await api('profile/categories');
    if (t === 'regninger') invoices.value = await api('profile/invoices');
    if (t === 'tilkob') prod.value = await api('profile/products');
}
watch(cur, onTab);
onMounted(async () => { await load(); onTab(cur.value); });
</script>
