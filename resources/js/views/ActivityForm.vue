<template>
    <div>
        <a href="#" class="small" @click.prevent="$router.back()">‹ Tilbage</a>
        <h1 style="margin:8px 0 14px">{{ id ? 'Rediger aktivitet' : 'Ny aktivitet' }}</h1>
        <form class="card stack" @submit.prevent="save()">
            <label class="field"><span>Titel</span><input v-model="f.title" required placeholder="Fx Onsdagstræning windsurf"></label>
            <div>
                <span class="small" style="font-weight:600;color:var(--ink-2)">Kategorier</span>
                <div class="chips" style="margin-top:6px">
                    <button v-for="c in cats.filter(c => !c.archived || f.category_ids.includes(c.id))" :key="c.id" type="button" :class="['chip', { on: f.category_ids.includes(c.id) }]" @click="toggle(f.category_ids, c.id)"><span class="sw" :style="{ background: c.color }"></span>{{ c.name }}</button>
                </div>
            </div>
            <div class="grid c2">
                <label class="field"><span>Start</span><input v-model="f.starts_at" type="datetime-local" required @change="autoFill"></label>
                <label class="field"><span>Slut</span><input v-model="f.ends_at" type="datetime-local" required></label>
            </div>
            <div v-if="!id" class="repeat">
                <label class="check"><input type="checkbox" v-model="repeat"> Fast aktivitet – gentag hver uge</label>
                <template v-if="repeat">
                    <label class="field" style="margin-top:8px"><span>Til og med</span><input v-model="f.repeat_until" type="date" :min="f.starts_at.slice(0, 10)" required></label>
                    <p class="small" style="margin:6px 0 0" v-if="occurrences.length">
                        <strong>{{ weekdayLabel }}</strong> · {{ occurrences.length }} gange ({{ kortDato(occurrences[0]) }} – {{ kortDato(occurrences[occurrences.length - 1]) }}).
                        <span class="muted">Hver gang bliver sin egen aktivitet, som trænerne kan tage, bytte eller aflyse.</span>
                    </p>
                    <p v-else-if="!f.starts_at" class="small muted" style="margin:6px 0 0">Vælg først start – ugedag og tidspunkt tages derfra.</p>
                </template>
            </div>
            <p v-else-if="seriesLabel" class="small muted" style="margin:0">Del af en fast aktivitet ({{ seriesLabel }}). Ændringer her gælder kun denne gang.</p>
            <div class="equip">
                <span class="small" style="font-weight:600;color:var(--ink-2)">Udstyr</span>
                <label class="check" style="margin-top:6px"><input type="radio" value="all" v-model="f.equipment_mode"> Book alt udstyr i kategorien <span class="small muted" v-if="avail">({{ catItems.length }} stk.)</span></label>
                <label class="check"><input type="radio" value="selected" v-model="f.equipment_mode"> Book kun bestemt udstyr – resten kan bookes af andre</label>
                <div v-if="f.equipment_mode === 'selected'" style="margin-top:8px">
                    <p v-if="!f.starts_at || !f.ends_at" class="small muted" style="margin:0">Vælg først tidspunkt.</p>
                    <EquipmentPicker v-else-if="avail" v-model="f.equipment_ids" :items="avail" :category-ids="f.category_ids" mode="activity" />
                </div>
                <p v-if="busyNames.length" class="small" style="margin:8px 0 0;color:var(--warn)"><strong>Allerede booket i tidsrummet:</strong> {{ busyNames.join(', ') }}<span v-if="repeat"> (første gang)</span></p>
            </div>
            <label class="field"><span>Sted</span><input v-model="f.location" placeholder="Fx Strandpladsen"></label>
            <label class="field"><span>Beskrivelse</span><textarea v-model="f.description" placeholder="Hvad skal deltagerne vide? Udstyr, niveau, vejrforbehold …"></textarea></label>
            <div class="grid c2">
                <label class="field"><span>Max deltagere</span><input v-model.number="f.max_participants" type="number" min="1" placeholder="Ubegrænset"></label>
                <label class="field"><span>Min deltagere</span><input v-model.number="f.min_participants" type="number" min="1"></label>
            </div>
            <div class="grid c2">
                <label class="field"><span>Svarfrist</span><input v-model="f.signup_deadline" type="datetime-local" :disabled="repeat"><small v-if="repeat" class="muted">Ingen svarfrist ved faste aktiviteter</small></label>
                <label class="field"><span>Afmelding lukker (timer før start)</span><input v-model.number="f.cancel_hours" type="number" min="0"></label>
            </div>
            <div class="grid c2">
                <label class="field"><span>Pris pr. deltager (kr.)</span><input v-model.number="priceKr" type="number" min="0" step="1" placeholder="Gratis"></label>
                <label class="check" style="margin-top:22px"><input type="checkbox" v-model="f.members_only"> Kræver betalt kontingent</label>
            </div>
            <label v-if="guestOffers.length || f.guest_package_id" class="field"><span>Åben for ikke-medlemmer</span>
                <select v-model="f.guest_package_id"><option :value="null">Nej</option><option v-for="o in guestOffers" :key="o.id" :value="o.id">Ja, som {{ lcFirst(o.label) }} ({{ o.price_fmt }})</option></select>
            </label>
            <label v-if="punchOk" class="check"><input type="checkbox" v-model="f.punchcard"> Åben for klippekort <span class="small muted">(ikke-medlemmer kan tilmelde sig med et klip)</span></label>
            <p v-if="f.guest_package_id" class="small muted" style="margin:-4px 0 0">Aktiviteten vises på siden "Prøv SurfHorsens". Gæster betaler tilbuddets pris<template v-if="guestOffers.find(o => o.id === f.guest_package_id)?.creditable">, og beløbet trækkes fra kontingentet, hvis de melder sig ind</template>. Tilbuddene oprettes under Indstillinger → Medlemskaber og tilkøb → Ikke-medlemmer.</p>
            <p v-if="repeat" class="small muted" style="margin:0"><strong>Trænere:</strong> en fast aktivitet oprettes uden træner. Trænerne melder sig selv på de enkelte gange bagefter (Trænerportalen → Mangler træner).</p>
            <div v-else>
                <span class="small" style="font-weight:600;color:var(--ink-2)">Trænere / tovholdere</span>
                <p class="small muted" style="margin:2px 0 6px">Trænere, der har meldt sig utilgængelige på dagen, er skjult. Du kan også oprette aktiviteten uden træner – så kan trænerne selv tage den bagefter.</p>
                <div class="chips">
                    <button v-for="t in trainers.filter(t => !t.unavailable || f.trainer_ids.includes(t.id))" :key="t.id" type="button" :class="['chip', { on: f.trainer_ids.includes(t.id) }]" @click="toggle(f.trainer_ids, t.id)">
                        {{ t.name }}<span v-if="t.unavailable" class="tag warn">utilgængelig</span>
                    </button>
                </div>
                <p v-if="hidden.length" class="small muted" style="margin-top:6px">Utilgængelige: {{ hidden.map(t => `${t.name} (${t.reason || 'ikke angivet'})`).join(', ') }}</p>
            </div>
            <label v-if="!id" class="check"><input type="checkbox" v-model="f.notify"> Send besked til medlemmer, der følger kategorien</label>
            <p v-if="error" class="banner bad">{{ error }}</p>
            <button class="btn primary block" :disabled="busy || (repeat && occurrences.length < 2)">{{ id ? 'Gem ændringer' : repeat && occurrences.length > 1 ? `Opret ${occurrences.length} gange` : 'Opret aktivitet' }}</button>
        </form>

        <Modal v-if="conflicts" title="Udstyret er booket" @close="conflicts = null">
            <p class="small" style="margin-top:0">Noget af det udstyr, aktiviteten skal bruge, er allerede booket{{ conflicts.equipment_conflicts.length > 1 ? ` på ${conflicts.equipment_conflicts.length} af gangene` : '' }}:</p>
            <div v-for="c in conflicts.equipment_conflicts" :key="c.date">
                <strong v-if="conflicts.equipment_conflicts.length > 1" class="small">{{ dato(c.date) }}</strong>
                <EquipmentConflicts :holders="c.holders" />
            </div>
            <div v-if="hasBookings" class="moves stack" style="gap:6px;margin-top:10px">
                <label class="check"><input type="checkbox" v-model="mv.on"> Bed dem, der har booket, om at flytte deres booking</label>
                <template v-if="mv.on">
                    <input v-model="mv.message" placeholder="Besked (valgfri), fx hvorfor udstyret skal bruges">
                    <p class="small muted" style="margin:0">De får besked og kan flytte, annullere eller sige nej. Svarer de ikke inden fristen (højst 24 timer), kan bookingen annulleres på aktivitetens side.</p>
                </template>
            </div>
            <div class="stack" style="margin-top:12px">
                <button class="btn primary block" :disabled="busy" @click="save({ force: true })">{{ id ? 'Gem alligevel' : 'Opret alligevel' }}</button>
                <button v-if="!id" class="btn block" :disabled="busy" @click="save({ as_pending: true })">Opret som "Afventer"{{ repeat ? ' (kun de gange, hvor udstyret er booket)' : '' }}</button>
                <button class="btn ghost block" @click="conflicts = null">Ret tidspunkt eller udstyr</button>
                <p v-if="!id" class="small muted" style="margin:0">"Afventer" betyder, at aktiviteten ikke er åben for tilmelding, og at medlemmerne ikke får besked endnu. Når udstyret er frigivet, opretter du den på aktivitetens side.</p>
            </div>
        </Modal>
    </div>
</template>

<script setup>
import { reactive, ref, computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { api, toast, dato } from '../api';
import Modal from '../components/Modal.vue';
import EquipmentPicker from '../components/EquipmentPicker.vue';
import EquipmentConflicts from '../components/EquipmentConflicts.vue';

const props = defineProps({ id: String });
const router = useRouter();
// Lille begyndelsesbogstav, men forkortelser bevares (SUP-kursus)
const lcFirst = (s) => /^\p{Lu}\p{Lu}/u.test(s) ? s : s.charAt(0).toLowerCase() + s.slice(1);
const cats = ref([]); const guestOffers = ref([]); const trainers = ref([]); const error = ref(''); const busy = ref(false);
const f = reactive({ title: '', description: '', starts_at: '', ends_at: '', location: '', max_participants: null, min_participants: null, signup_deadline: '', cancel_hours: 24, price: 0, members_only: true, guest_package_id: null, punchcard: false, category_ids: [], trainer_ids: [], notify: true, repeat_until: '', equipment_mode: 'all', equipment_ids: [] });
const priceKr = computed({ get: () => f.price ? f.price / 100 : '', set: (v) => (f.price = Math.round((Number(v) || 0) * 100)) });
const repeat = ref(false); const seriesLabel = ref('');
const kortDato = (d) => d.toLocaleDateString('da-DK', { day: 'numeric', month: 'short' });
const occurrences = computed(() => {
    if (!repeat.value || !f.starts_at || !f.repeat_until) return [];
    const out = []; const end = new Date(f.repeat_until + 'T23:59');
    for (let d = new Date(f.starts_at); d <= end && out.length < 61; d.setDate(d.getDate() + 7)) out.push(new Date(d));
    return out;
});
const weekdayLabel = computed(() => {
    if (!f.starts_at) return '';
    const s = new Date(f.starts_at); const e = f.ends_at ? new Date(f.ends_at) : null;
    const t = (d) => d.toLocaleTimeString('da-DK', { hour: '2-digit', minute: '2-digit' });
    return 'Hver ' + s.toLocaleDateString('da-DK', { weekday: 'long' }) + ' kl. ' + t(s) + (e ? '–' + t(e) : '');
});
watch(repeat, (on) => {
    // Faste aktiviteter: ingen svarfrist og ingen trænere ved oprettelsen – trænerne melder sig selv på bagefter
    if (on) { f.signup_deadline = ''; f.trainer_ids = []; }
    if (on && !f.repeat_until && f.starts_at) { const d = new Date(f.starts_at); d.setDate(d.getDate() + 7 * 11); f.repeat_until = local(d).slice(0, 10); }
});
const hidden = computed(() => trainers.value.filter(t => t.unavailable && !f.trainer_ids.includes(t.id)));

const local = (iso) => { if (!iso) return ''; const d = new Date(iso); d.setMinutes(d.getMinutes() - d.getTimezoneOffset()); return d.toISOString().slice(0, 16); };
const toggle = (arr, id) => { const i = arr.indexOf(id); i >= 0 ? arr.splice(i, 1) : arr.push(id); };

function autoFill() {
    if (!f.starts_at) return;
    const s = new Date(f.starts_at);
    if (!f.ends_at || new Date(f.ends_at) <= s) f.ends_at = local(new Date(s.getTime() + 2 * 3600e3));
    if (!f.signup_deadline && !repeat.value) { const d = new Date(s); d.setDate(d.getDate() - 1); d.setHours(20, 0); f.signup_deadline = local(d); }
}

async function loadTrainers() { trainers.value = await api(`trainer-options?date=${(f.starts_at || new Date().toISOString()).slice(0, 10)}`); }
watch(() => f.starts_at?.slice(0, 10), loadTrainers);

onMounted(async () => {
    cats.value = await api('categories');
    api('guest/offers').then(o => { guestOffers.value = o.map(x => ({ id: x.code, label: x.label, price_fmt: x.price_fmt, creditable: x.creditable })); }).catch(() => {});
    if (props.id) {
        const a = await api(`activities/${props.id}`);
        Object.assign(f, { title: a.title, description: a.description || '', starts_at: local(a.starts_at), ends_at: local(a.ends_at), location: a.location || '', max_participants: a.max_participants, min_participants: a.min_participants, signup_deadline: local(a.signup_deadline), cancel_hours: a.cancel_hours, price: a.price, members_only: a.members_only, guest_package_id: a.guest_package_id, punchcard: a.punchcard, category_ids: a.categories.map(c => c.id), trainer_ids: a.trainers.map(t => t.id) });
        seriesLabel.value = a.series?.label || '';
        if (a.equipment) { f.equipment_mode = a.equipment.mode; f.equipment_ids = [...a.equipment.ids]; }
    }
    await loadTrainers();
});

/* ---- udstyr ---- */
// Klippekort: kun når der findes et klippekort til en af de valgte kategorier
const punchOk = computed(() => cats.value.some(c => c.punchcard && f.category_ids.includes(c.id)));
watch(punchOk, (ok) => { if (!ok && cats.value.length) f.punchcard = false; });
const avail = ref(null); const conflicts = ref(null);
const mv = reactive({ on: true, message: '' });
const hasBookings = computed(() => (conflicts.value?.equipment_conflicts || []).some(c => c.holders.some(h => h.kind === 'booking')));
const catItems = computed(() => (avail.value || []).filter(i => f.category_ids.includes(i.category_id) && i.status !== 'defect'));
const chosen = computed(() => f.equipment_mode === 'all' ? catItems.value : (avail.value || []).filter(i => f.equipment_ids.includes(i.id)));
const busyNames = computed(() => chosen.value.filter(i => i.holders.length).map(i => i.name));
let t = null;
watch(() => [f.starts_at, f.ends_at], () => { clearTimeout(t); t = setTimeout(loadAvail, 300); });
async function loadAvail() {
    if (!f.starts_at || !f.ends_at || f.ends_at <= f.starts_at) return;
    avail.value = await api(`equipment/availability?starts_at=${encodeURIComponent(f.starts_at)}&ends_at=${encodeURIComponent(f.ends_at)}${props.id ? '&except_activity=' + props.id : ''}`);
}

async function save(extra = {}) {
    busy.value = true; error.value = '';
    if (extra.force || extra.as_pending) Object.assign(extra, { request_moves: hasBookings.value && mv.on, move_message: mv.message || null });
    const body = { ...extra, ...f, equipment_ids: f.equipment_mode === 'selected' ? f.equipment_ids : [], max_participants: f.max_participants || null, min_participants: f.min_participants || null, signup_deadline: repeat.value ? null : f.signup_deadline || null, repeat_until: repeat.value && !props.id ? f.repeat_until : null };
    if (repeat.value) body.trainer_ids = [];
    try {
        const r = await api(props.id ? `activities/${props.id}` : 'activities', { method: props.id ? 'PUT' : 'POST', body });
        conflicts.value = null;
        toast(props.id ? 'Ændringerne er gemt'
            : r.count ? `Den faste aktivitet er oprettet (${r.count} gange${r.pending ? `, heraf ${r.pending} afventer` : ''})`
            : r.status === 'pending' ? 'Aktiviteten afventer – den er ikke åben for tilmelding endnu' : 'Aktiviteten er oprettet');
        router.replace(r.count ? '/traener?vis=mangler' : `/aktiviteter/${r.id}`);
    } catch (e) {
        if (e.status === 409) conflicts.value = e.data; else error.value = e.message;
    } finally { busy.value = false; }
}
</script>

<style scoped>
.moves { background: var(--surface-2); border-radius: 12px; padding: 10px 12px; }
.equip { background: var(--surface-2); border-radius: 12px; padding: 12px; }
.repeat { background: var(--surface-2); border-radius: 12px; padding: 12px; }
</style>
