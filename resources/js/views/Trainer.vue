<template>
    <div>
        <div class="topbar">
            <h1>Trænerportal</h1>
            <RouterLink to="/aktiviteter/ny" class="btn primary"><Icon name="plus" style="width:18px" /> Ny aktivitet</RouterLink>
        </div>
        <div class="tabs">
            <button :class="{ on: tab === 'mine' }" @click="tab = 'mine'">Mine aktiviteter</button>
            <button v-if="isTrainer" :class="{ on: tab === 'off' }" @click="tab = 'off'">Fravær</button>
            <button :class="{ on: tab === 'cal' }" @click="tab = 'cal'">Tilgængelighed</button>
        </div>

        <template v-if="tab === 'mine'">
            <div class="chips" style="margin-bottom:12px">
                <button :class="['chip', { on: when === 'future' }]" @click="when = 'future'">Kommende</button>
                <button :class="['chip', { on: when === 'past' }]" @click="when = 'past'">Afholdte</button>
            </div>
            <div v-if="acts === null" class="spinner"></div>
            <div v-else-if="!acts.length" class="card empty">Ingen aktiviteter.</div>
            <div v-else class="card" style="padding:0">
                <RouterLink v-for="a in acts" :key="a.id" :to="`/aktiviteter/${a.id}`" class="act">
                    <div class="when"><div class="d">{{ dagNr(a.starts_at) }}</div><div class="m">{{ maanedKort(a.starts_at) }}</div></div>
                    <div style="flex:1">
                        <div class="t">{{ a.title }}</div>
                        <div class="meta">kl. {{ klok(a.starts_at) }} · {{ a.signed_up_count }}<span v-if="a.max_participants">/{{ a.max_participants }}</span> tilmeldt<span v-if="a.trainers.length > 1"> · med {{ a.trainers.filter(t => t.id !== store.user.id).map(t => t.name.split(' ')[0]).join(', ') }}</span></div>
                        <CatTags :cats="a.categories" />
                    </div>
                </RouterLink>
            </div>
        </template>

        <template v-if="tab === 'off'">
            <form class="card" @submit.prevent="addOff">
                <h2>Meld fravær</h2>
                <div class="grid c3">
                    <label class="field"><span>Fra</span><input type="date" v-model="off.from" required></label>
                    <label class="field"><span>Til</span><input type="date" v-model="off.to" required></label>
                    <label class="field"><span>Årsag (valgfri)</span><input v-model="off.reason" placeholder="Ferie, arbejde …"></label>
                </div>
                <button class="btn primary" style="margin-top:12px">Tilføj</button>
                <div v-if="clashes.length" class="banner warn small" style="margin-top:12px">
                    Du står allerede som træner på: <strong>{{ clashes.map(c => c.title + ' (' + dato(c.starts_at) + ')').join(', ') }}</strong>. Husk at finde en afløser.
                </div>
            </form>
            <div class="card" style="padding:4px 16px">
                <ul class="list">
                    <li v-for="u in offList" :key="u.id" class="row between">
                        <span><strong>{{ dato(u.from) }}</strong> – <strong>{{ dato(u.to) }}</strong> <span class="muted">{{ u.reason }}</span></span>
                        <button class="btn small ghost danger" @click="delOff(u)">Slet</button>
                    </li>
                    <li v-if="!offList.length" class="muted">Intet fravær registreret.</li>
                </ul>
            </div>
        </template>

        <template v-if="tab === 'cal'">
            <div class="cal-nav">
                <button class="btn mnav" :disabled="!cal" @click="loadCal(cal.prev)" aria-label="Forrige måned">
                    <svg viewBox="0 0 24 24"><path d="M15 5l-7 7 7 7" /></svg><span>{{ cal ? kortMaaned(cal.prev) : '' }}</span>
                </button>
                <div class="cal-month">
                    <strong>{{ cal ? maaned(cal.month + '-01') : '' }}</strong>
                    <button v-if="cal && cal.month !== todayIso.slice(0, 7)" class="today-link" @click="loadCal()">Gå til i dag</button>
                </div>
                <button class="btn mnav" :disabled="!cal" @click="loadCal(cal.next)" aria-label="Næste måned">
                    <span>{{ cal ? kortMaaned(cal.next) : '' }}</span><svg viewBox="0 0 24 24"><path d="M9 5l7 7-7 7" /></svg>
                </button>
            </div>
            <p class="small muted cal-help">
                <template v-if="detail">{{ detail }}</template>
                <template v-else><span class="cell off">✕</span> utilgængelig · <span class="cell busy">●</span> aktivitet · tryk for detaljer</template>
            </p>
            <div v-if="!cal" class="spinner"></div>
            <div v-else ref="scroller" class="cal-scroll" :style="{ height: calHeight }">
                <table class="avail">
                    <thead>
                        <tr><th class="dcol"></th><th v-for="t in cal.trainers" :key="t.id" :title="t.name">{{ shortName(t) }}</th></tr>
                    </thead>
                    <tbody>
                        <template v-for="(d, i) in cal.days" :key="d">
                            <tr :data-day="d" :class="{ we: isWeekend(d), today: d === todayIso, mon: isMonday(d) }">
                                <th class="dcol">{{ ugedag(d) }} {{ Number(d.slice(8)) }}<span v-if="isMonday(d)" class="wk">u{{ uge(d) }}</span></th>
                                <td v-for="t in cal.trainers" :key="t.id" @click="detail = tip(t, t.days[i], i)">
                                    <span v-if="t.days[i].off" class="cell off">✕</span>
                                    <span v-else-if="t.days[i].busy.length" class="cell busy">●</span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </template>
    </div>
</template>

<script setup>
import { ref, reactive, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import CatTags from '../components/CatTags.vue';
import Icon from '../components/Icon.vue';
import { api, store, klok, dato, toast, dagNr, maanedKort } from '../api';

const isTrainer = store.user.roles.includes('trainer');
const tab = ref('mine'); const when = ref('future'); const acts = ref(null);
const offList = ref([]); const off = reactive({ from: '', to: '', reason: '' }); const clashes = ref([]);
const cal = ref(null); const detail = ref('');
const scroller = ref(null); const calHeight = ref('60vh');

const pad = (n) => String(n).padStart(2, '0');
const isoOf = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
const todayIso = isoOf(new Date());
const parse = (iso) => new Date(iso + 'T12:00:00');
const isMonday = (iso) => parse(iso).getDay() === 1;
const ugedag = (iso) => ['sø', 'ma', 'ti', 'on', 'to', 'fr', 'lø'][parse(iso).getDay()];
const kortMaaned = (ym) => parse(ym + '-01').toLocaleDateString('da-DK', { month: 'short' }).replace('.', '');
const maaned = (iso) => { const s = parse(iso).toLocaleDateString('da-DK', { month: 'long', year: 'numeric' }); return s[0].toUpperCase() + s.slice(1); };
function uge(iso) { // ISO-ugenummer
    const d = parse(iso); d.setDate(d.getDate() + 3 - ((d.getDay() + 6) % 7));
    const w1 = new Date(d.getFullYear(), 0, 4);
    return 1 + Math.round(((d - w1) / 864e5 - 3 + ((w1.getDay() + 6) % 7)) / 7);
}
// Fornavn; tilføj forbogstav i efternavn hvis to trænere har samme fornavn
function shortName(t) {
    const [first, ...rest] = t.name.split(' ');
    const dup = cal.value.trainers.filter(x => x.name.split(' ')[0] === first).length > 1;
    return dup && rest.length ? `${first} ${rest[rest.length - 1][0]}.` : first;
}
function fitHeight() {
    if (!scroller.value) return;
    const top = scroller.value.getBoundingClientRect().top + window.scrollY;
    const bottomBar = window.matchMedia('(max-width: 899.98px)').matches ? 84 : 24;
    calHeight.value = Math.max(260, window.innerHeight - top - bottomBar) + 'px';
}
function toToday() {
    const el = scroller.value; if (!el) return;
    const row = el.querySelector(`tr[data-day="${todayIso}"]`);
    el.scrollTop = row ? row.offsetTop - el.querySelector('thead').offsetHeight : 0;
}
async function loadCal(month) {
    detail.value = '';
    cal.value = await api('availability' + (month ? `?month=${month}` : ''));
    await nextTick(); fitHeight(); await nextTick(); toToday();
}
window.addEventListener('resize', fitHeight);
onBeforeUnmount(() => window.removeEventListener('resize', fitHeight));

const isWeekend = (d) => [0, 6].includes(new Date(d).getDay());
const tip = (t, x, i) => `${t.name}, ${dato(cal.value.days[i])}: ` + (x.off ? `utilgængelig (${x.reason || 'ikke angivet'})` : x.busy.length ? x.busy.join(', ') : 'ledig');

async function loadActs() { acts.value = null; acts.value = await api(`activities?tab=trainer&when=${when.value === 'past' ? 'past' : 'future'}`); }
async function addOff() {
    const r = await api('unavailability', { method: 'POST', body: off });
    offList.value = r.list; clashes.value = r.clashes; Object.assign(off, { from: '', to: '', reason: '' }); toast('Fravær registreret');
}
async function delOff(u) { offList.value = await api(`unavailability/${u.id}`, { method: 'DELETE' }); }

watch(when, loadActs);
watch(tab, async (t) => {
    if (t === 'off') offList.value = await api('unavailability');
    if (t === 'cal' && !cal.value) await loadCal();
});
onMounted(loadActs);
</script>

<style scoped>
.cal-nav { display: grid; grid-template-columns: auto 1fr auto; align-items: center; gap: 8px; margin-bottom: 10px; }
.mnav { min-height: 48px; min-width: 76px; padding: 8px 12px; background: var(--accent-soft); border-color: transparent; color: var(--accent); font-size: 1rem; text-transform: capitalize; }
.mnav svg { width: 22px; height: 22px; fill: none; stroke: currentColor; stroke-width: 2.8; stroke-linecap: round; stroke-linejoin: round; }
.mnav:active { transform: scale(.96); }
.cal-month { text-align: center; font-size: 1.15rem; line-height: 1.2; }
.cal-month strong { display: block; }
.today-link { border: 0; background: none; font: inherit; font-size: .8rem; font-weight: 600; color: var(--accent); text-decoration: underline; cursor: pointer; padding: 4px; }
.cal-help { min-height: 1.4em; margin: 0 0 8px; }
.cal-help .cell { width: 16px; height: 16px; font-size: .6rem; vertical-align: middle; }
.cal-scroll { overflow: auto; overscroll-behavior: contain; -webkit-overflow-scrolling: touch; background: var(--surface); border: 1px solid var(--line); border-radius: 14px; }
.avail { border-collapse: separate; border-spacing: 0; width: 100%; }
.avail th, .avail td { padding: 5px 3px; text-align: center; border-bottom: 1px solid var(--line); font-size: .75rem; }
.avail thead th { position: sticky; top: 0; z-index: 2; background: var(--surface); font-size: .72rem; color: var(--ink); text-transform: none; letter-spacing: 0; min-width: 40px; white-space: nowrap; border-bottom: 2px solid var(--line); padding: 8px 3px; }
.avail .dcol { position: sticky; left: 0; z-index: 1; background: var(--surface); text-align: left; white-space: nowrap; padding-left: 12px; padding-right: 6px; font-weight: 600; color: var(--ink-2); width: 1%; text-transform: none; letter-spacing: 0; }
.avail thead .dcol { z-index: 3; }
.avail .wk { margin-left: 4px; font-size: .62rem; font-weight: 600; color: var(--ink-3); }
.avail td { cursor: pointer; height: 30px; }
.avail tr.we td, .avail tr.we .dcol { background: var(--surface-2); }
.avail tr.mon > * { border-top: 2px solid var(--line); }
.avail tr.today > * { background: var(--accent-soft) !important; }
.avail tr.today .dcol { color: var(--accent); }
.avail tr.today .cell.busy { background: var(--surface); }
.cal-month { min-width: 8.5em; text-align: center; }
.cell { display: inline-grid; place-items: center; width: 20px; height: 20px; border-radius: 6px; font-size: .7rem; font-weight: 700; }
.cell.off { background: var(--bad-soft); color: var(--bad); }
.cell.busy { background: var(--accent-soft); color: var(--accent); }
@media (max-width: 899.98px) { .cal-scroll { margin: 0 -16px; border-radius: 0; border-left: 0; border-right: 0; } }
</style>
