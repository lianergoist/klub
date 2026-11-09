<template>
    <div>
        <h1 style="margin-bottom:14px">Trænerportal</h1>
        <div class="tabs">
            <button :class="{ on: tab === 'mine' }" @click="tab = 'mine'">Mine aktiviteter</button>
            <button :class="{ on: tab === 'off' }" @click="tab = 'off'">Fravær</button>
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
                    <div class="when"><div class="d">{{ new Date(a.starts_at).getDate() }}</div><div class="m">{{ new Date(a.starts_at).toLocaleDateString('da-DK', { month: 'short' }) }}</div></div>
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

        <template v-if="tab === 'cal' && cal">
            <p class="small muted">De næste fire uger. <span class="tag bad">✕</span> utilgængelig · <span class="tag info">●</span> står på en aktivitet. Hold musen over eller tryk for detaljer.</p>
            <div class="card" style="padding:12px">
                <div class="table-wrap">
                    <table class="avail">
                        <thead>
                            <tr><th></th><th v-for="d in cal.days" :key="d" :class="{ we: isWeekend(d) }">{{ new Date(d).toLocaleDateString('da-DK', { weekday: 'narrow' }) }}<br>{{ new Date(d).getDate() }}</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="t in cal.trainers" :key="t.id">
                                <th style="text-transform:none;font-size:.85rem;color:var(--ink)">{{ t.name }}</th>
                                <td v-for="(x, i) in t.days" :key="i" :class="{ we: isWeekend(cal.days[i]) }" :title="tip(t, x, i)" @click="detail = tip(t, x, i)">
                                    <span v-if="x.off" class="cell off">✕</span>
                                    <span v-else-if="x.busy.length" class="cell busy">●</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-if="detail" class="small" style="margin-top:8px">{{ detail }}</p>
            </div>
        </template>
    </div>
</template>

<script setup>
import { ref, reactive, watch, onMounted } from 'vue';
import CatTags from '../components/CatTags.vue';
import { api, store, klok, dato, toast } from '../api';

const tab = ref('mine'); const when = ref('future'); const acts = ref(null);
const offList = ref([]); const off = reactive({ from: '', to: '', reason: '' }); const clashes = ref([]);
const cal = ref(null); const detail = ref('');

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
    if (t === 'cal' && !cal.value) cal.value = await api('availability');
});
onMounted(loadActs);
</script>

<style scoped>
.avail th, .avail td { padding: 6px 4px; text-align: center; border-bottom: 1px solid var(--line); font-size: .75rem; }
.avail th:first-child { text-align: left; position: sticky; left: 0; background: var(--surface); padding-right: 10px; white-space: nowrap; }
.avail td { cursor: pointer; min-width: 26px; }
.avail .we { background: var(--surface-2); }
.cell { display: inline-grid; place-items: center; width: 20px; height: 20px; border-radius: 6px; font-size: .7rem; font-weight: 700; }
.cell.off { background: var(--bad-soft); color: var(--bad); }
.cell.busy { background: var(--accent-soft); color: var(--accent); }
</style>
