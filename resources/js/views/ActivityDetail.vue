<template>
    <div v-if="!a" class="spinner"></div>
    <div v-else>
        <a href="#" class="small" @click.prevent="$router.back()">‹ Tilbage</a>
        <div class="row between" style="margin:8px 0 6px;align-items:flex-start">
            <h1 :style="a.cancelled ? 'text-decoration:line-through' : ''">{{ a.title }}</h1>
            <div v-if="a.can_edit && !a.cancelled" class="row noprint">
                <RouterLink :to="`/aktiviteter/${a.id}/rediger`" class="btn small">Rediger</RouterLink>
                <button v-if="!a.is_past" class="btn small danger" @click="showCancel = true">Aflys</button>
            </div>
        </div>
        <CatTags :cats="a.categories" />

        <div class="card" style="margin-top:14px">
            <div class="stack">
                <div class="inf"><Icon name="cal" /><span><span>{{ datoLang(a.starts_at) }}</span>, kl. {{ klok(a.starts_at) }}–{{ klok(a.ends_at) }}</span></div>
                <div v-if="a.series" class="inf"><Icon name="clock" /><span>Fast aktivitet<br><span class="small muted">{{ a.series.label }}</span></span></div>
                <div v-if="a.location" class="inf"><Icon name="pin" /><span>{{ a.location }}</span></div>
                <div class="inf"><Icon name="whistle" /><span>{{ a.trainers.length ? a.trainers.map(t => t.name).join(', ') : 'Træner ikke fundet endnu' }}</span></div>
                <div v-if="a.signup_deadline" class="inf"><Icon name="clock" /><span>Svarfrist {{ tidspunkt(a.signup_deadline) }}<br><span class="small muted">Afmelding senest {{ a.cancel_hours }} timer før start</span></span></div>
                <div v-if="a.price_fmt" class="inf"><Icon name="money" /><span>{{ a.price_fmt }} pr. deltager<span v-if="a.members_only" class="muted small"> · kræver betalt kontingent</span></span></div>
            </div>
            <p v-if="a.description" style="white-space:pre-line;margin:14px 0 0">{{ a.description }}</p>
            <p v-if="a.punchcard && a.can_edit" class="small" style="margin:10px 0 0"><span class="tag info">Åben for klippekort</span> Ikke-medlemmer med klippekort kan tilmelde sig.</p>
            <p v-if="a.guest_offer && a.can_edit" class="small" style="margin:10px 0 0"><span class="tag info">Åben for ikke-medlemmer</span> som {{ a.guest_offer === 'trial' ? 'prøvetræning' : 'SUP-kursus' }} via siden "Prøv SurfHorsens".</p>
        </div>

        <!-- Mangler træner: alle trænere kan tage den -->
        <div v-if="!a.cancelled && !a.is_past && !a.trainers.length && a.can_edit" class="card need noprint">
            <div class="row" style="gap:10px;align-items:flex-start;flex-wrap:nowrap"><Icon name="whistle" style="width:22px;height:22px;color:var(--warn);flex-shrink:0;margin-top:2px" />
                <div><strong>Mangler træner</strong><p class="small muted" style="margin:2px 0 0">Aktiviteten har ingen træner endnu.</p></div>
            </div>
            <div class="row" style="margin-top:12px;gap:8px">
                <button v-if="a.can_take" class="btn primary" :disabled="busy" @click="take">Jeg tager den</button>
                <button class="btn" @click="openTrainers">Sæt anden træner på</button>
            </div>
        </div>

        <div v-if="a.cancelled" class="banner bad">Aktiviteten er aflyst.</div>

        <!-- Tilmelding -->
        <div v-else-if="!a.is_past && a.is_leader" class="banner info row between"><span><Icon name="whistle" style="width:16px" /> Du er træner på denne aktivitet.</span><button class="btn small noprint" @click="showLeave = true">Meld fra</button></div>
        <!-- Trænere tilmelder sig som almindelige deltagere (ikke på aktiviteter, hvor de selv er træner) -->
        <div v-if="!a.cancelled && !a.is_past && a.household.length" class="card">
            <div class="row between"><h2 style="margin:0">{{ a.is_leader ? 'Tilmeld familiemedlemmer' : 'Tilmelding' }}</h2>
                <span class="small muted" v-if="a.max_participants">{{ a.signed_up_count }} / {{ a.max_participants }} pladser</span>
            </div>
            <div v-if="a.deadline_passed" class="banner warn" style="margin-top:12px">Svarfristen er overskredet. Kontakt træneren, hvis du vil med.</div>
            <div v-else-if="a.is_full" class="banner warn" style="margin-top:12px">Holdet er fuldt.</div>
            <ul class="list" style="margin-top:6px">
                <li v-for="m in a.household" :key="m.id">
                    <div class="row between">
                        <label class="check" :style="{ opacity: canPick(m) ? 1 : .9 }">
                            <input v-if="canPick(m) && !solo" type="checkbox" v-model="picked" :value="m.id">
                            <span><strong>{{ solo && m.id === store.user.id ? 'Dig' : m.name }}</strong> <span v-if="m.age !== null && a.household.length > 1" class="muted small">({{ m.age }} år)</span></span>
                        </label>
                        <div class="row" style="gap:6px">
                            <span v-if="m.status === 'signed_up'" class="tag ok"><Icon name="check" style="width:12px" /> Tilmeldt</span>
                            <template v-else-if="m.status === 'pending_payment'">
                                <span class="tag warn">Afventer betaling</span>
                                <RouterLink :to="`/betal/${m.invoice_id}`" class="btn small primary">Betal</RouterLink>
                            </template>
                            <span v-else-if="a.members_only && !m.membership_valid" class="tag bad">Kontingent ikke betalt</span>
                            <button v-if="m.status === 'signed_up' || m.status === 'pending_payment'" class="btn small" :disabled="a.cancel_locked && !store.user.can.activities_manage" @click="unsign(m)">{{ solo && m.id === store.user.id ? 'Afmeld mig' : 'Afmeld' }}</button>
                        </div>
                    </div>
                    <details v-if="m.history.length" class="small" style="margin-top:6px">
                        <summary class="muted" style="cursor:pointer">Historik ({{ m.history.length }})</summary>
                        <ul class="timeline" style="margin-top:4px">
                            <li v-for="(h, i) in m.history" :key="i">{{ h.label }} · {{ tidspunkt(h.at) }}<span v-if="h.by && h.by !== m.name"> af {{ h.by }}</span></li>
                        </ul>
                    </details>
                </li>
            </ul>
            <p v-if="a.cancel_locked" class="small muted" style="margin-top:8px">Afmelding er lukket, fordi der er under {{ a.cancel_hours }} timer til start.</p>
            <button v-if="pickable.length" class="btn primary block" style="margin-top:10px" :disabled="!picked.length || busy" @click="signup">
                {{ btnLabel }}
            </button>
        </div>

        <!-- Deltagere -->
        <div class="card">
            <div class="row between">
                <h2 style="margin:0">Deltagere ({{ a.participants.length }})</h2>
                <button v-if="a.can_edit && a.participants.length" class="btn small noprint" @click="print">Udskriv liste</button>
            </div>
            <p v-if="!a.participants.length" class="muted" style="margin-top:10px">Ingen tilmeldte endnu.</p>
            <div v-else-if="!a.can_edit" class="small" style="margin-top:10px;columns:2">
                <div v-for="p in a.participants" :key="p.id">{{ p.name }}</div>
            </div>
            <div v-else class="table-wrap" style="margin-top:8px">
                <table>
                    <thead><tr><th>Navn</th><th>Alder</th><th>Telefon</th><th>Nødkontakt</th><th v-if="started" class="noprint">Fremmøde</th></tr></thead>
                    <tbody>
                        <tr v-for="p in a.participants" :key="p.id">
                            <td>{{ p.name }} <span v-if="p.guest" class="tag warn">Gæst</span></td><td>{{ p.age }}</td><td>{{ p.phone || '–' }}</td><td class="small">{{ p.emergency || '–' }}</td>
                            <td v-if="started" class="noprint">
                                <div class="row" style="gap:4px;flex-wrap:nowrap">
                                    <button :class="['btn small', p.attended === true && 'primary']" @click="p.attended = true; att = true">Mødt</button>
                                    <button :class="['btn small', p.attended === false && 'danger']" @click="p.attended = false; att = true">Udeblev</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <button v-if="att" class="btn primary" style="margin-top:12px" @click="saveAttendance">Gem fremmøde</button>
        </div>

        <!-- Træner -->
        <div v-if="a.can_edit" class="card noprint">
            <div class="row between"><h2 style="margin:0">Trænere</h2><button class="btn small" @click="openTrainers">Skift/tilføj træner</button></div>
            <p style="margin:10px 0">{{ a.trainers.map(t => t.name).join(', ') || 'Ingen træner sat på' }}</p>
            <h3 style="margin-top:14px">Ændringslog</h3>
            <ul class="timeline">
                <li v-for="(c, i) in a.trainer_log" :key="i">{{ c.trainer }} {{ c.action === 'added' ? 'sat på' : 'fjernet' }} · {{ tidspunkt(c.at) }}<span v-if="c.by"> af {{ c.by }}</span></li>
                <li v-if="!a.trainer_log.length" class="muted">Ingen ændringer.</li>
            </ul>
            <details style="margin-top:12px">
                <summary class="muted small" style="cursor:pointer">Tilmeldingshistorik for alle ({{ a.registration_log.length }})</summary>
                <ul class="timeline" style="margin-top:6px">
                    <li v-for="(h, i) in a.registration_log" :key="i"><strong>{{ h.member }}</strong>: {{ h.label }} · {{ tidspunkt(h.at) }}<span v-if="h.by && h.by !== h.member"> af {{ h.by }}</span></li>
                </ul>
            </details>
            <p class="small muted" style="margin-top:12px">Oprettet af {{ a.created_by }}</p>
        </div>

        <Modal v-if="showTrainers" title="Vælg trænere" @close="showTrainers = false">
            <label class="check small" style="margin-bottom:10px"><input type="checkbox" v-model="showUnavailable"> Vis også trænere, der er utilgængelige</label>
            <ul class="list">
                <li v-for="t in trainerOpts.filter(t => showUnavailable || !t.unavailable || selTrainers.includes(t.id))" :key="t.id">
                    <label class="check"><input type="checkbox" v-model="selTrainers" :value="t.id">
                        <span>{{ t.name }} <span v-if="t.unavailable" class="tag warn">Utilgængelig: {{ t.reason || 'ikke angivet' }}</span></span>
                    </label>
                </li>
            </ul>
            <div v-if="warnUnavailable" class="banner warn small" style="margin-top:10px">{{ warnUnavailable }} har markeret sig som utilgængelig på dagen.</div>
            <button class="btn primary block" style="margin-top:12px" @click="saveTrainers">Gem</button>
        </Modal>

        <Modal v-if="showLeave" title="Meld fra som træner" @close="showLeave = false">
            <p>Hvis aktiviteten derefter står uden træner, får de andre trænere besked, så en af dem kan tage den.</p>
            <div v-if="a.series && a.series.remaining > 1" class="stack" style="gap:6px">
                <label class="check"><input type="radio" v-model="leaveSeries" :value="false"> Kun denne gang</label>
                <label class="check"><input type="radio" v-model="leaveSeries" :value="true"> Denne og alle senere gange, hvor jeg er træner</label>
            </div>
            <button class="btn danger block" style="margin-top:12px" :disabled="busy" @click="leave">Meld fra</button>
        </Modal>

        <Modal v-if="showCancel" title="Aflys aktivitet" @close="showCancel = false">
            <p>Alle tilmeldte får besked.<span v-if="a.price"> Betalinger skal refunderes af kassereren.</span></p>
            <div v-if="a.series && a.series.remaining > 1" class="stack" style="gap:6px;margin-bottom:10px">
                <label class="check"><input type="radio" v-model="cancelSeries" :value="false"> Kun denne gang</label>
                <label class="check"><input type="radio" v-model="cancelSeries" :value="true"> Denne og alle senere gange ({{ a.series.remaining }})</label>
            </div>
            <label class="field"><span>Begrundelse</span><textarea v-model="cancelReason" placeholder="Fx for lidt vind eller for få tilmeldte"></textarea></label>
            <button class="btn danger block" style="margin-top:12px" :disabled="!cancelReason" @click="cancelActivity">{{ cancelSeries ? `Aflys ${a.series.remaining} gange og giv besked` : 'Aflys og giv besked' }}</button>
        </Modal>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import Icon from '../components/Icon.vue';
import Modal from '../components/Modal.vue';
import CatTags from '../components/CatTags.vue';
import { api, store, kr, klok, datoLang, tidspunkt, toast, refreshMe } from '../api';

const props = defineProps({ id: String });
const router = useRouter();
const a = ref(null); const picked = ref([]); const busy = ref(false); const att = ref(false);
const showTrainers = ref(false); const trainerOpts = ref([]); const selTrainers = ref([]); const showUnavailable = ref(false);
const showCancel = ref(false); const cancelReason = ref(''); const cancelSeries = ref(false);
const showLeave = ref(false); const leaveSeries = ref(false);

const started = computed(() => a.value && new Date(a.value.starts_at) < new Date());
const canPick = (m) => !['signed_up', 'pending_payment'].includes(m.status) && !(a.value.members_only && !m.membership_valid) && (!a.value.deadline_passed || store.user.can.activities_manage) && !a.value.is_full;
const pickable = computed(() => a.value ? a.value.household.filter(canPick) : []);
// Kun én person at vælge (typisk én selv): ingen tjekboks, bare "Tilmeld mig"
const solo = computed(() => a.value && a.value.household.length === 1);
watch([solo, pickable], () => { if (solo.value) picked.value = pickable.value.map(m => m.id); });
const btnLabel = computed(() => {
    const n = picked.value.length;
    if (!n) return 'Vælg hvem der skal med';
    const self = solo.value && picked.value[0] === store.user.id;
    const who = self ? (store.user.can.activities_take ? 'Tilmeld mig som deltager' : 'Tilmeld mig') : n > 1 ? `Tilmeld ${n} personer` : 'Tilmeld';
    return a.value.price ? `${who} og betal ${kr(a.value.price * n)}` : who;
});
const warnUnavailable = computed(() => trainerOpts.value.filter(t => t.unavailable && selTrainers.value.includes(t.id)).map(t => t.name).join(', '));

async function load() { a.value = await api(`activities/${props.id}`); picked.value = []; att.value = false; }
onMounted(load);
watch(() => props.id, load);

async function signup() {
    busy.value = true;
    try {
        const r = await api(`activities/${a.value.id}/signup`, { method: 'POST', body: { member_ids: picked.value } });
        if (r.invoice_ids.length) return router.push(`/betal/${r.invoice_ids.join(',')}`);
        toast('Du er tilmeldt'); await load(); refreshMe();
    } catch (e) { toast(e.message, 'error'); } finally { busy.value = false; }
}
async function unsign(m) {
    try { await api(`activities/${a.value.id}/unsignup`, { method: 'POST', body: { member_id: m.id } }); toast(`${m.name} er afmeldt`); await load(); refreshMe(); }
    catch (e) { toast(e.message, 'error'); }
}
async function saveAttendance() {
    a.value = await api(`activities/${a.value.id}/attendance`, { method: 'PUT', body: { items: a.value.participants.map(p => ({ registration_id: p.registration_id, attended: p.attended })) } });
    att.value = false; toast('Fremmøde gemt');
}
async function openTrainers() {
    trainerOpts.value = await api(`trainer-options?date=${a.value.starts_at.slice(0, 10)}`);
    selTrainers.value = a.value.trainers.map(t => t.id);
    showTrainers.value = true;
}
async function saveTrainers() {
    a.value = await api(`activities/${a.value.id}/trainers`, { method: 'PUT', body: { trainer_ids: selTrainers.value } });
    showTrainers.value = false; toast('Trænere opdateret');
}
async function cancelActivity() {
    const r = await api(`activities/${a.value.id}/cancel`, { method: 'POST', body: { reason: cancelReason.value, series: cancelSeries.value } });
    showCancel.value = false; toast(r.count > 1 ? `${r.count} gange er aflyst, og deltagerne har fået besked` : 'Aktiviteten er aflyst, og deltagerne har fået besked'); load();
}
async function take() {
    busy.value = true;
    try {
        a.value = await api(`activities/${a.value.id}/take`, { method: 'POST' });
        toast('Du er træner på aktiviteten');
    } catch (e) { toast(e.message, 'error'); } finally { busy.value = false; }
}
async function leave() {
    busy.value = true;
    try {
        a.value = await api(`activities/${a.value.id}/leave`, { method: 'POST', body: { series: leaveSeries.value } });
        showLeave.value = false; toast('Du har meldt fra som træner');
    } catch (e) { toast(e.message, 'error'); } finally { busy.value = false; }
}
function print() { window.print(); }
</script>

<style scoped>
.inf { display: flex; gap: 10px; align-items: flex-start; }
.need { border: 1px solid var(--warn); }
.inf svg { width: 18px; height: 18px; flex-shrink: 0; margin-top: 2px; color: var(--ink-3); }
</style>
