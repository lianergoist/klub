<template>
    <div>
        <div class="topbar"><h1>Udstyr</h1></div>
        <div v-if="d?.unpaid_rental?.length" class="banner warn row between" style="margin-bottom:12px">
            <span>Din udstyrsleje er ikke betalt endnu. Når regningen er betalt, kan du booke udstyr.</span>
            <RouterLink :to="`/betal/${d.unpaid_rental.join(',')}`" class="btn small primary">Betal nu</RouterLink>
        </div>
        <div class="tabs">
            <button :class="{ on: tab === 'list' }" @click="tab = 'list'">Udstyr</button>
            <button v-if="store.user.can.equipment_book_any" :class="{ on: tab === 'book' }" @click="tab = 'book'">Book udstyr</button>
            <button :class="{ on: tab === 'bookings' }" @click="tab = 'bookings'">Bookinger<span v-if="todo" class="count">{{ todo }}</span></button>
        </div>

        <!-- ============ Udstyrslisten ============ -->
        <template v-if="tab === 'list'">
            <div v-if="!d" class="spinner"></div>
            <template v-else>
                <div class="row between" style="margin-bottom:12px;gap:10px">
                    <div class="chips">
                        <button :class="['chip', { on: !cat }]" @click="cat = null">Alle</button>
                        <button v-for="c in withTypes" :key="c.id" :class="['chip', { on: cat === c.id }]" @click="cat = c.id"><span class="sw" :style="{ background: c.color }"></span>{{ c.name }}</button>
                    </div>
                    <label class="check small"><input type="checkbox" v-model="showArchived"> Vis arkiveret</label>
                </div>
                <p v-if="!withTypes.length" class="banner info">Der er ikke oprettet underkategorier af udstyr endnu.
                    <span v-if="d.can.types">Opret dem under kategorierne nedenfor, fx Windsurf → Sejl, Master, Boards.</span>
                    <span v-else>Administrator opretter dem.</span></p>

                <div v-for="c in shown" :key="c.id" class="card">
                    <div class="row between">
                        <h2 style="margin:0"><span class="sw" :style="{ background: c.color }"></span>{{ c.name }}</h2>
                        <button v-if="d.can.types" class="btn small" @click="typeEd = { category_id: c.id, name: '', cat: c.name }">+ Underkategori</button>
                    </div>
                    <p v-if="!c.types.length" class="small muted" style="margin:6px 0 0">Ingen underkategorier.</p>
                    <div v-for="t in c.types" :key="t.id" class="type">
                        <div class="row between" style="flex-wrap:nowrap">
                            <h3 :style="t.archived ? 'opacity:.55' : ''">{{ t.name }} <span class="small muted">({{ t.items.filter(i => !i.archived).length }})</span>
                                <span v-if="t.archived" class="tag">Arkiveret</span></h3>
                            <div class="row" style="gap:6px;flex-wrap:nowrap">
                                <button v-if="d.can.types" class="btn small ghost" @click="typeEd = { id: t.id, name: t.name, archived: t.archived, cat: c.name }">Ret</button>
                                <button v-if="d.can.manage && !t.archived" class="btn small" @click="itemEd = { equipment_type_id: t.id, name: t.name.replace(/e?r$/, ''), count: 1, note: '', typeName: t.name }">+ Tilføj</button>
                            </div>
                        </div>
                        <div class="grid-items">
                            <button v-for="i in t.items" :key="i.id" type="button" :class="['it', i.archived ? 'arch' : i.status]" @click="openItem(i)">
                                <strong>{{ i.name }}</strong>
                                <span v-if="i.archived" class="tag">Arkiveret</span>
                                <span v-else-if="i.status !== 'ok'" :class="['tag', i.status === 'defect' ? 'bad' : 'warn']">{{ i.status_label }}</span>
                                <span v-if="i.status_note" class="small muted">{{ i.status_note }}</span>
                                <span v-if="i.note" class="small muted">{{ i.note }}</span>
                                <span v-if="i.now" class="small">I brug nu: {{ i.now.kind === 'activity' ? i.now.title : i.now.name }}</span>
                            </button>
                            <p v-if="!t.items.length" class="small muted" style="margin:0">Intet udstyr endnu.</p>
                        </div>
                    </div>
                </div>
            </template>
        </template>

        <!-- ============ Book udstyr ============ -->
        <template v-if="tab === 'book'">
            <div class="card stack">
                <div v-if="bf.replaces" class="banner info small row between" style="margin:0">
                    <span>Du flytter din booking af {{ bf.replacesLabel }}. Vælg nyt tidspunkt eller andet udstyr – den gamle booking annulleres, når du gemmer.</span>
                    <button class="btn small ghost" @click="bf.replaces = null">Fortryd</button>
                </div>
                <div class="grid c2">
                    <label class="field"><span>Fra</span><input type="datetime-local" v-model="bf.starts_at" @change="autoEnd"></label>
                    <label class="field"><span>Til</span><input type="datetime-local" v-model="bf.ends_at"></label>
                </div>
                <div>
                    <span class="small" style="font-weight:600;color:var(--ink-2)">Hvad skal du bruge?</span>
                    <div class="chips" style="margin-top:6px">
                        <button v-for="c in availCats" :key="c.id" type="button" :class="['chip', { on: bookCats.includes(c.id) }]" @click="toggle(bookCats, c.id)">{{ c.name }}</button>
                    </div>
                </div>
                <div v-if="avail === null && validTime" class="spinner"></div>
                <p v-else-if="!validTime" class="small muted" style="margin:0">Vælg tidsrum for at se, hvad der er ledigt.</p>
                <!-- Kun den valgte kategori vises, så listen ikke bliver uoverskuelig -->
                <p v-else-if="!bookCats.length" class="small muted" style="margin:0">Vælg en eller flere kategorier ovenfor for at se, hvad der er ledigt.</p>
                <EquipmentPicker v-else v-model="bf.equipment_ids" :items="avail" :category-ids="bookCats" mode="book" collapsible />
                <label class="field"><span>Bemærkning (valgfri)</span><input v-model="bf.note" placeholder="Fx hvad du skal bruge det til"></label>
                <button class="btn primary block book-btn" :disabled="busy || !bf.equipment_ids.length || !validTime" @click="book(pickedHeld.length > 0)">
                    {{ pickedHeld.length ? 'Send ønske til træneren' : bf.replaces ? 'Flyt booking' : `Book ${bf.equipment_ids.length || ''} stk.` }}</button>
            </div>
        </template>

        <!-- ============ Bookinger ============ -->
        <template v-if="tab === 'bookings'">
            <div v-if="!bk" class="spinner"></div>
            <template v-else>
                <div v-if="bk.to_decide.length" class="card" style="border-color:var(--warn)">
                    <h2>Ønsker til dig</h2>
                    <p class="small muted" style="margin-top:0">Udstyr, som dine aktiviteter har booket, og som andre gerne vil låne.</p>
                    <ul class="list"><li v-for="x in bk.to_decide" :key="x.id"><Request :x="x" @done="loadBookings" /></li></ul>
                </div>
                <div class="row between" style="margin-bottom:10px">
                    <div class="chips">
                        <button :class="['chip', { on: scope === 'mine' }]" @click="scope = 'mine'">Mine</button>
                        <button v-if="bk.can_all" :class="['chip', { on: scope === 'all' }]" @click="scope = 'all'">Alle kommende</button>
                    </div>
                </div>
                <div class="card">
                    <ul class="list">
                        <li v-for="b in bk.bookings" :key="b.id">
                            <div class="row between" style="align-items:flex-start;flex-wrap:nowrap">
                                <div>
                                    <strong>{{ tidspunkt(b.starts_at) }}–{{ sameDay(b) ? klok(b.ends_at) : tidspunkt(b.ends_at) }}</strong>
                                    <span :class="['tag', { confirmed: 'ok', requested: 'warn', rejected: 'bad', cancelled: '' }[b.status]]">{{ b.status_label }}</span>
                                    <div class="small"><span v-if="!b.mine">{{ b.user }}: </span>{{ b.items.map(i => i.name).join(', ') }}</div>
                                    <div v-if="b.note" class="small muted">"{{ b.note }}"</div>
                                    <!-- Anmodning om at flytte bookingen -->
                                    <div v-for="m in b.moves" :key="m.id" :class="['move', m.status]">
                                        <template v-if="b.mine">
                                            <div v-if="m.status === 'pending'"><strong>{{ m.by }}</strong> beder dig flytte bookingen, fordi udstyret skal bruges til
                                                <RouterLink :to="`/aktiviteter/${m.activity_id}`">{{ m.activity }}</RouterLink> {{ tidspunkt(m.activity_starts_at) }}–{{ klok(m.activity_ends_at) }}.</div>
                                            <div v-else>Du har sagt nej til at flytte bookingen for <RouterLink :to="`/aktiviteter/${m.activity_id}`">{{ m.activity }}</RouterLink>: "{{ m.reply }}"</div>
                                            <div v-if="m.message" class="muted">"{{ m.message }}"</div>
                                            <div v-if="m.status === 'pending'" class="muted">Svar senest {{ tidspunkt(m.deadline_at) }} – ellers kan bookingen blive annulleret.</div>
                                            <div v-if="m.status === 'pending'" class="row" style="gap:6px;margin-top:6px">
                                                <button class="btn small primary" @click="moveBooking(b)">Flyt booking</button>
                                                <button class="btn small" @click="cancelBooking(b)">Annullér</button>
                                                <button class="btn small ghost" @click="decline = { m, reply: '' }">Nej, jeg har brug for det</button>
                                                <button class="btn small ghost" @click="write(m.by_id)">Skriv til {{ m.by.split(' ')[0] }}</button>
                                            </div>
                                        </template>
                                        <div v-else>{{ m.by }} har bedt om at få bookingen flyttet for <RouterLink :to="`/aktiviteter/${m.activity_id}`">{{ m.activity }}</RouterLink>
                                            – {{ m.status === 'declined' ? `sagt nej: "${m.reply}"` : m.overdue ? 'intet svar inden fristen' : `svarfrist ${tidspunkt(m.deadline_at)}` }}.</div>
                                    </div>
                                    <div v-for="a in b.approvals" :key="a.activity_id" class="small" style="margin-top:4px">
                                        <RouterLink :to="`/aktiviteter/${a.activity_id}`">{{ a.title }}</RouterLink>:
                                        <span v-if="a.status === 'pending'" class="muted">venter på svar fra {{ a.contacts.map(c => c.name).join(' eller ') || 'træneren' }}</span>
                                        <span v-else>{{ a.status === 'accepted' ? 'ja' : 'nej' }} fra {{ a.decided_by }}<span v-if="a.reply" class="muted"> – "{{ a.reply }}"</span></span>
                                    </div>
                                </div>
                                <button v-if="b.can_cancel" class="btn small ghost danger" style="flex:none" @click="cancelBooking(b)">Annullér</button>
                            </div>
                        </li>
                        <li v-if="!bk.bookings.length" class="muted">Ingen kommende bookinger.</li>
                    </ul>
                </div>
            </template>
        </template>

        <!-- ============ Modaler ============ -->
        <Modal v-if="typeEd" :title="typeEd.id ? 'Ret underkategori' : `Ny underkategori under ${typeEd.cat}`" @close="typeEd = null">
            <div class="stack">
                <label class="field"><span>Navn</span><input v-model="typeEd.name" placeholder="Fx Sejl, Master, Boards"></label>
                <button class="btn primary block" :disabled="!typeEd.name" @click="saveType">Gem</button>
                <button v-if="typeEd.id" class="btn ghost block" @click="archiveType">{{ typeEd.archived ? 'Tag i brug igen' : 'Arkivér underkategori' }}</button>
            </div>
        </Modal>

        <Modal v-if="itemEd" :title="itemEd.id ? 'Ret udstyr' : `Tilføj ${itemEd.typeName.toLowerCase()}`" @close="itemEd = null">
            <div class="stack">
                <label class="field"><span>Navn / nummer</span><input v-model="itemEd.name" placeholder="Fx SUP eller Sejl 5,8 m² gul"></label>
                <label v-if="!itemEd.id" class="field"><span>Antal</span><input v-model.number="itemEd.count" type="number" min="1" max="50"></label>
                <p v-if="!itemEd.id && itemEd.count > 1" class="small muted" style="margin:0">Oprettes som {{ itemEd.name }} 1, {{ itemEd.name }} 2 … (fortsætter efter højeste nummer).</p>
                <label v-if="itemEd.id" class="field"><span>Underkategori</span>
                    <select v-model="itemEd.equipment_type_id"><optgroup v-for="c in d.categories.filter(c => c.types.length)" :key="c.id" :label="c.name"><option v-for="t in c.types" :key="t.id" :value="t.id">{{ t.name }}</option></optgroup></select></label>
                <label class="field"><span>Bemærkning (valgfri)</span><input v-model="itemEd.note" placeholder="Fx størrelse, farve, mærke"></label>
                <button class="btn primary block" :disabled="!itemEd.name" @click="saveItem">Gem</button>
            </div>
        </Modal>

        <Modal v-if="statusEd" :title="`Stand: ${statusEd.item.name}`" @close="statusEd = null">
            <div class="stack">
                <div class="chips">
                    <button v-for="(l, k) in d.statuses" :key="k" type="button" :class="['chip', { on: statusEd.status === k }]" @click="statusEd.status = k">{{ l }}</button>
                </div>
                <p class="small muted" style="margin:0">{{ { ok: 'Kan bruges og bookes.', service: 'Kan stadig bruges og bookes, men trænger til service.', defect: 'Kan ikke bookes eller bruges. Dem, der har booket det, får besked.' }[statusEd.status] }}</p>
                <label class="field"><span>{{ statusEd.status === 'ok' ? 'Bemærkning (valgfri)' : 'Hvad er der galt?' }}</span><input v-model="statusEd.note" :placeholder="statusEd.status === 'ok' ? 'Fx repareret' : 'Fx flænge i sejlet ved masten'"></label>
                <button class="btn primary block" :disabled="statusEd.status !== 'ok' && !statusEd.note" @click="saveStatus">Gem</button>
            </div>
        </Modal>

        <Modal v-if="item" :title="item.name" @close="item = null">
            <div class="stack">
                <div class="small muted">{{ item.category }} · {{ item.type }}<span v-if="item.note"> · {{ item.note }}</span></div>
                <div><span :class="['tag', { ok: 'ok', service: 'warn', defect: 'bad' }[item.status]]">{{ item.status_label }}</span> <span v-if="item.status_note" class="small">{{ item.status_note }}</span></div>
                <div v-if="item.upcoming.length"><strong class="small">Kommende bookinger</strong>
                    <ul class="small" style="margin:4px 0 0 18px;padding:0"><li v-for="(u, k) in item.upcoming" :key="k">{{ tidspunkt(u.starts_at) }}–{{ klok(u.ends_at) }}: {{ u.name }}</li></ul></div>
                <div><strong class="small">Historik</strong>
                    <ul class="timeline small"><li v-for="(e, k) in item.events" :key="k">{{ e.label }}<template v-if="e.status">: {{ e.status }}</template><span v-if="e.note"> – {{ e.note }}</span> · {{ tidspunkt(e.at) }} af {{ e.by || 'system' }}</li></ul></div>
                <div v-if="d.can.manage" class="row" style="gap:8px">
                    <button v-if="!item.archived" class="btn small primary" @click="statusEd = { item, status: item.status, note: '' }; item = null">Ret stand</button>
                    <button v-if="!item.archived" class="btn small" @click="itemEd = { id: item.id, name: item.name, note: item.note || '', equipment_type_id: item.equipment_type_id }; item = null">Ret</button>
                    <button class="btn small ghost danger" @click="archiveItem(item)">{{ item.archived ? 'Tag i brug igen' : 'Arkivér (kasseret)' }}</button>
                </div>
            </div>
        </Modal>

        <Modal v-if="decline" title="Kan ikke flytte bookingen" @close="decline = null">
            <div class="stack">
                <p class="small" style="margin:0">{{ decline.m.by }} får dit svar. Bestyrelsen kan stadig annullere bookingen, hvis udstyret er nødvendigt for aktiviteten.</p>
                <label class="field"><span>Hvorfor har du brug for udstyret?</span><input v-model="decline.reply" placeholder="Fx jeg har fået fri og skal til stævne"></label>
                <button class="btn primary block" :disabled="!decline.reply || busy" @click="sendDecline">Send svar</button>
            </div>
        </Modal>

        <Modal v-if="conflict" title="Udstyret er booket til en aktivitet" @close="conflict = null">
            <p class="small" style="margin-top:0">Det følgende er booket i tidsrummet. Send et ønske – træneren kan så sige ja eller nej med det samme – eller skriv først og hør, om det kan lade sig gøre.</p>
            <EquipmentConflicts :holders="conflict" />
            <button class="btn primary block" style="margin-top:12px" :disabled="busy" @click="book(true)">Send ønske til træneren</button>
        </Modal>
    </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Modal from '../components/Modal.vue';
import EquipmentPicker from '../components/EquipmentPicker.vue';
import EquipmentConflicts from '../components/EquipmentConflicts.vue';
import Request from '../components/EquipmentRequest.vue';
import { api, store, toast, tidspunkt, klok } from '../api';

const route = useRoute(); const router = useRouter();
const tab = ref(route.query.vis === 'bookinger' ? 'bookings' : route.query.vis === 'book' ? 'book' : 'list');
const d = ref(null); const cat = ref(null); const showArchived = ref(false);
const typeEd = ref(null); const itemEd = ref(null); const statusEd = ref(null); const item = ref(null);
const busy = ref(false);
const toggle = (arr, id) => { const i = arr.indexOf(id); i >= 0 ? arr.splice(i, 1) : arr.push(id); };

/* ---- liste ---- */
async function load() { d.value = await api('equipment' + (showArchived.value ? '?archived=1' : '')); }
watch(showArchived, load);
const withTypes = computed(() => d.value.categories.filter(c => c.types.length || d.value.can.types));
const shown = computed(() => withTypes.value.filter(c => !cat.value || c.id === cat.value));
const run = async (fn, msg) => { try { await fn(); if (msg) toast(msg); await load(); } catch (e) { toast(e.message, 'error'); } };
const saveType = () => run(async () => {
    const t = typeEd.value;
    await (t.id ? api(`equipment-types/${t.id}`, { method: 'PUT', body: { name: t.name } }) : api('equipment-types', { method: 'POST', body: t }));
    typeEd.value = null;
}, 'Gemt');
const archiveType = () => run(async () => { const t = typeEd.value; await api(`equipment-types/${t.id}`, { method: 'PUT', body: { archived: !t.archived } }); typeEd.value = null; }, 'Gemt');
const saveItem = () => run(async () => {
    const e = itemEd.value;
    if (e.id) await api(`equipment/${e.id}`, { method: 'PUT', body: e });
    else { const r = await api('equipment', { method: 'POST', body: e }); toast(r.ids.length > 1 ? `${r.ids.length} stk. tilføjet` : 'Tilføjet'); }
    itemEd.value = null;
}, itemEd.value?.id ? 'Gemt' : null);
const saveStatus = () => run(async () => { const s = statusEd.value; await api(`equipment/${s.item.id}/status`, { method: 'POST', body: { status: s.status, note: s.note } }); statusEd.value = null; }, 'Standen er gemt');
async function openItem(i) { item.value = await api(`equipment/${i.id}`); }
async function archiveItem(i) {
    if (!i.archived && !confirm(`Arkivér ${i.name}? Det forsvinder fra listen og kan ikke længere bookes. Historikken bevares.`)) return;
    await run(() => api(`equipment/${i.id}/archive`, { method: 'POST', body: { archived: !i.archived } }), i.archived ? 'Taget i brug igen' : 'Arkiveret');
    item.value = null;
}

/* ---- booking ---- */
const local = (dt) => { const x = new Date(dt); x.setMinutes(x.getMinutes() - x.getTimezoneOffset()); return x.toISOString().slice(0, 16); };
const next = new Date(); next.setDate(next.getDate() + 1); next.setHours(10, 0, 0, 0);
const bf = reactive({ starts_at: local(next), ends_at: local(new Date(next.getTime() + 3 * 3600e3)), equipment_ids: [], note: '', replaces: null, replacesLabel: '' });
const avail = ref(null); const conflict = ref(null);
// Husk sidst valgte kategorier til booking (på denne enhed)
const bookCats = ref((() => { try { return JSON.parse(localStorage.getItem('book-kategorier') || '[]'); } catch { return []; } })());
watch(bookCats, (v) => { try { localStorage.setItem('book-kategorier', JSON.stringify(v)); } catch {} }, { deep: true });
const validTime = computed(() => bf.starts_at && bf.ends_at && bf.ends_at > bf.starts_at);
const availCats = computed(() => { const m = {}; for (const i of avail.value || []) m[i.category_id] = { id: i.category_id, name: i.category }; return Object.values(m); });
const pickedHeld = computed(() => (avail.value || []).filter(i => bf.equipment_ids.includes(i.id) && i.holders.some(h => h.kind === 'activity')).map(i => i.name));
function autoEnd() { if (bf.starts_at && (!bf.ends_at || bf.ends_at <= bf.starts_at)) bf.ends_at = local(new Date(new Date(bf.starts_at).getTime() + 3 * 3600e3)); }
async function loadAvail() {
    if (!validTime.value) return;
    avail.value = null;
    avail.value = await api(`equipment/availability?starts_at=${encodeURIComponent(bf.starts_at)}&ends_at=${encodeURIComponent(bf.ends_at)}${bf.replaces ? '&except_booking=' + bf.replaces : ''}`);
    // fravælg det, der ikke længere kan vælges
    const ok = new Set(avail.value.filter(i => i.status !== 'defect' && !i.holders.some(h => h.kind === 'booking')).map(i => i.id));
    bf.equipment_ids = bf.equipment_ids.filter(id => ok.has(id));
    // Flyt booking: vis kun kategorierne med det bookede udstyr
    if (bf.replaces && !bookCats.value.length) bookCats.value = [...new Set(avail.value.filter(i => bf.equipment_ids.includes(i.id)).map(i => i.category_id))];
}
watch(() => [bf.starts_at, bf.ends_at, bf.replaces, tab.value], () => { if (tab.value === 'book') loadAvail(); });
async function book(request = false) {
    busy.value = true;
    try {
        const r = await api('equipment-bookings', { method: 'POST', body: { ...bf, request } });
        conflict.value = null;
        toast(bf.replaces ? (r.status === 'confirmed' ? 'Bookingen er flyttet' : 'Ønsket er sendt til træneren – den gamle booking er annulleret')
            : r.status === 'confirmed' ? 'Udstyret er booket' : 'Ønsket er sendt til træneren. Du får besked, når der er svaret.');
        Object.assign(bf, { equipment_ids: [], note: '', replaces: null }); tab.value = 'bookings'; loadBookings();
    } catch (e) {
        if (e.status === 409) conflict.value = e.data.conflicts; else toast(e.message, 'error');
    } finally { busy.value = false; }
}

/* ---- bookinger ---- */
const bk = ref(null); const scope = ref('mine');
async function loadBookings() { bk.value = await api('equipment-bookings?scope=' + scope.value); }
watch(scope, loadBookings);
watch(tab, (t) => { if (t === 'bookings') loadBookings(); });
const sameDay = (b) => b.starts_at.slice(0, 10) === b.ends_at.slice(0, 10);
const todo = computed(() => bk.value ? bk.value.to_decide.length + bk.value.bookings.filter(b => b.mine && b.moves.some(m => m.status === 'pending')).length : 0);
// Flyt: åbn "Book udstyr" med samme udstyr og tidsrum; den gamle booking annulleres, når den nye er gemt
function moveBooking(b) {
    Object.assign(bf, { starts_at: local(b.starts_at), ends_at: local(b.ends_at), equipment_ids: b.items.map(i => i.id), note: b.note || '',
        replaces: b.id, replacesLabel: b.items.map(i => i.name).join(', ') });
    tab.value = 'book';
}
const decline = ref(null);
async function sendDecline() {
    busy.value = true;
    try { await api(`equipment-moves/${decline.value.m.id}/decline`, { method: 'POST', body: { reply: decline.value.reply } }); toast('Dit svar er sendt'); decline.value = null; loadBookings(); }
    catch (e) { toast(e.message, 'error'); } finally { busy.value = false; }
}
async function write(id) {
    try { const r = await api('messages/start', { method: 'POST', body: { user_ids: [id] } }); router.push(`/beskeder/${r.id}`); }
    catch (e) { toast(e.message, 'error'); }
}
async function cancelBooking(b) {
    if (!confirm('Annullér bookingen?')) return;
    try { await api(`equipment-bookings/${b.id}/cancel`, { method: 'POST' }); toast('Bookingen er annulleret'); loadBookings(); } catch (e) { toast(e.message, 'error'); }
}

onMounted(() => { load(); loadBookings(); if (tab.value === 'book') loadAvail(); });
</script>

<style scoped>
/* Book-knappen bliver stående over bundmenuen på mobil, så man ser antallet, mens man vælger */
@media (max-width: 899.98px) { .book-btn { position: sticky; bottom: calc(env(safe-area-inset-bottom, 0px) + 72px); z-index: 5; box-shadow: 0 4px 16px rgba(0,0,0,.18); } }
.count { margin-left: 6px; background: var(--warn); color: #fff; border-radius: 999px; padding: 0 7px; font-size: .72rem; font-weight: 700; }
h2 .sw, .sw { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 6px; vertical-align: middle; }
.type { border-top: 1px solid var(--line); margin-top: 12px; padding-top: 10px; }
.type h3 { margin: 0; font-size: 1rem; }
.grid-items { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 8px; margin-top: 8px; }
.it { display: flex; flex-direction: column; align-items: flex-start; gap: 3px; text-align: left; border: 1px solid var(--line); background: var(--surface); border-radius: 10px; padding: 8px 10px; cursor: pointer; font: inherit; color: inherit; }
.it:hover { border-color: var(--accent); }
.it.service { border-color: var(--warn); }
.it.defect { border-color: var(--bad); background: color-mix(in srgb, var(--bad) 6%, var(--surface)); }
.it.arch { opacity: .55; }
.it .tag { margin: 0; }
.move { margin-top: 6px; padding: 8px 10px; border-radius: 10px; background: var(--surface-2); font-size: .85rem; }
.move.pending { border-left: 3px solid var(--warn); }
</style>
