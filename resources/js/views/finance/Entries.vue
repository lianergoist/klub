<template>
    <div>
        <div class="row between" style="margin-bottom:12px">
            <div class="row filters" style="flex:1;min-width:260px">
                <input v-model="q" placeholder="Søg i tekst" style="max-width:260px" @input="debounced">
                <select v-model="account" style="max-width:240px"><option value="">Alle konti</option><option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.number }} {{ a.name }}</option></select>
            </div>
            <div class="row">
                <button class="btn small" @click="download(`finance/entries/export?year=${fy.year}`, `posteringer-${fy.year}.csv`)"><Icon name="download" style="width:16px" /> CSV</button>
                <button v-if="canWrite" class="btn primary small" @click="showNew = true"><Icon name="plus" style="width:16px" /> Ny postering</button>
            </div>
        </div>
        <p class="small muted">Betalinger fra MobilePay og kassererens manuelle registreringer bogføres automatisk (mærket <span class="tag info">auto</span>). Rettelser laves som modpostering. Intet bliver slettet.</p>
        <!-- Mobil: kort grupperet pr. måned, 40 ad gangen -->
        <div class="mobile-only">
            <p v-if="!rows.length" class="card empty">Ingen posteringer.</p>
            <template v-for="g in monthGroups" :key="g.key">
                <div class="mgroup">{{ g.label }}</div>
                <div class="card" style="padding:0 14px;margin-bottom:8px">
                    <ul class="mlist">
                        <li v-for="e in g.rows" :key="e.id" class="tap" :style="e.reversed || e.reverses_id ? 'opacity:.6' : ''" @click="open = open === e.id ? null : e.id">
                            <div class="l1"><span>{{ e.text }}</span><strong :class="['num', e.amount < 0 ? 'neg' : '']">{{ kr(e.amount) }}</strong></div>
                            <div class="l2">
                                <span>{{ dato(e.date) }} · {{ e.account }}<template v-if="e.activity"> · {{ e.activity }}</template></span>
                                <span class="row" style="gap:4px;flex-wrap:nowrap"><Icon v-if="e.attachment" name="file" style="width:15px" title="Har bilag" /><span v-if="e.auto" class="tag info">auto</span><span v-if="e.reversed" class="tag">udlignet</span></span>
                            </div>
                            <!-- Tryk på posteringen for at se bilag eller rette -->
                            <div v-if="open === e.id && (e.attachment || (canWrite && !e.reversed && !e.reverses_id && !e.auto))" class="acts" @click.stop>
                                <button v-if="e.attachment" class="btn small" @click="download(`finance/entries/${e.id}/attachment`, e.attachment_name)"><Icon name="file" style="width:16px" /> Bilag</button>
                                <button v-if="canWrite && !e.reversed && !e.reverses_id && !e.auto" class="btn ghost small" @click="rev = e">Ret</button>
                            </div>
                        </li>
                    </ul>
                </div>
            </template>
            <button v-if="rows.length > shown" class="btn block" @click="shown += 40">Vis flere ({{ rows.length - shown }} tilbage)</button>
        </div>
        <div class="card desktop-only" style="padding:0 16px">
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Nr.</th><th>Dato</th><th>Tekst</th><th>Konto</th><th class="num">Beløb</th><th>Bilag</th><th>Indtastet af</th><th></th></tr></thead>
                    <tbody>
                        <tr v-for="e in rows" :key="e.id" :style="e.reversed || e.reverses_id ? 'opacity:.6' : ''">
                            <td class="muted small">{{ e.id }}</td>
                            <td style="white-space:nowrap">{{ dato(e.date) }}</td>
                            <td>{{ e.text }} <span v-if="e.auto" class="tag info">auto</span><span v-if="e.reversed" class="tag">udlignet</span><div v-if="e.activity" class="small muted">{{ e.activity }}</div></td>
                            <td class="small">{{ e.account }}</td>
                            <td :class="['num', e.amount < 0 ? 'neg' : '']">{{ kr(e.amount) }}</td>
                            <td><button v-if="e.attachment" class="btn ghost small" @click="download(`finance/entries/${e.id}/attachment`, e.attachment_name)"><Icon name="file" style="width:16px" /></button></td>
                            <td class="small">{{ e.by }}<div class="muted">{{ dato(e.created_at) }}</div></td>
                            <td><button v-if="canWrite && !e.reversed && !e.reverses_id && !e.auto" class="btn ghost small" @click="rev = e">Ret</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-if="!rows.length" class="empty">Ingen posteringer.</p>
        </div>

        <Modal v-if="showNew" title="Ny postering" @close="showNew = false">
            <form class="stack" @submit.prevent="save">
                <div class="tabs" style="margin:0"><button type="button" :class="{ on: kind === 'expense' }" @click="kind = 'expense'">Udgift</button><button type="button" :class="{ on: kind === 'income' }" @click="kind = 'income'">Indtægt</button></div>
                <div class="grid c2">
                    <label class="field"><span>Dato</span><input type="date" v-model="n.entry_date" required></label>
                    <label class="field"><span>Beløb (kr.)</span><input type="number" step="0.01" min="0.01" v-model="n.amount" required></label>
                </div>
                <label class="field"><span>Tekst</span><input v-model="n.text" required placeholder="Fx Nye trapez-liner"></label>
                <label class="field"><span>Konto</span><select v-model="n.account_id" required><option v-for="a in accounts.filter(a => a.type === kind)" :key="a.id" :value="a.id">{{ a.number }} {{ a.name }}</option></select></label>
                <label class="field"><span>Knyt til aktivitet (valgfrit)</span><select v-model="n.activity_id"><option value="">–</option><option v-for="a in acts" :key="a.id" :value="a.id">{{ a.title }} ({{ dato(a.starts_at) }})</option></select></label>
                <label class="field"><span>Bilag (foto eller PDF)</span><input type="file" accept="image/*,application/pdf" capture="environment" @change="file = $event.target.files[0]"></label>
                <button class="btn primary block">Bogfør</button>
            </form>
        </Modal>

        <Modal v-if="rev" title="Ret postering" @close="rev = null">
            <p>Posteringen <strong>#{{ rev.id }} {{ rev.text }}</strong> ({{ kr(rev.amount) }}) bliver udlignet med en modpostering. Lav derefter en ny, korrekt postering.</p>
            <label class="field"><span>Begrundelse</span><input v-model="revReason" placeholder="Fx Forkert beløb"></label>
            <button class="btn primary block" style="margin-top:12px" :disabled="!revReason" @click="reverse">Lav modpostering</button>
        </Modal>
    </div>
</template>
<script setup>
import { ref, reactive, computed, inject, watch, onMounted } from 'vue';
import Icon from '../../components/Icon.vue';
import Modal from '../../components/Modal.vue';
import { api, store, kr, dato, toast, download } from '../../api';
const fy = inject('fy');
const canWrite = computed(() => store.user.can.finance_write);
const rows = ref([]); const accounts = ref([]); const acts = ref([]); const q = ref(''); const account = ref('');
const showNew = ref(false); const kind = ref('expense'); const file = ref(null);
const n = reactive({ entry_date: new Date().toISOString().slice(0, 10), amount: '', text: '', account_id: '', activity_id: '' });
const rev = ref(null); const revReason = ref('');
const shown = ref(40); const open = ref(null);
const monthName = (k) => new Date(k + '-01').toLocaleDateString('da-DK', { month: 'long', year: 'numeric' });
const monthGroups = computed(() => {
    const out = [];
    for (const e of rows.value.slice(0, shown.value)) {
        const key = String(e.date).slice(0, 7);
        if (!out.length || out[out.length - 1].key !== key) out.push({ key, label: monthName(key), rows: [] });
        out[out.length - 1].rows.push(e);
    }
    return out;
});
async function load() { shown.value = 40; rows.value = await api(`finance/entries?year=${fy.year}&q=${encodeURIComponent(q.value)}&account_id=${account.value}`); }
let t; const debounced = () => { clearTimeout(t); t = setTimeout(load, 250); };
watch(account, load);
watch(showNew, async (v) => { if (v && !acts.value.length) acts.value = [...await api('activities?tab=upcoming'), ...await api('activities?tab=trainer&when=past')]; });
onMounted(async () => { accounts.value = await api('finance/accounts'); load(); });
async function save() {
    const fd = new FormData();
    fd.append('entry_date', n.entry_date); fd.append('text', n.text); fd.append('account_id', n.account_id);
    fd.append('amount', Math.round(parseFloat(String(n.amount).replace(',', '.')) * 100));
    if (n.activity_id) fd.append('activity_id', n.activity_id);
    if (file.value) fd.append('attachment', file.value);
    try { await api('finance/entries', { method: 'POST', form: fd }); showNew.value = false; Object.assign(n, { amount: '', text: '', activity_id: '' }); file.value = null; toast('Bogført'); load(); }
    catch (e) { toast(e.message, 'error'); }
}
async function reverse() {
    try { await api(`finance/entries/${rev.value.id}/reverse`, { method: 'POST', body: { reason: revReason.value } }); rev.value = null; revReason.value = ''; toast('Modpostering oprettet'); load(); }
    catch (e) { toast(e.message, 'error'); }
}
</script>
<style scoped>
@media (max-width: 899.98px) { .filters { min-width: 0 !important; flex: 1 1 100% !important; flex-wrap: nowrap; } .filters input, .filters select { max-width: none !important; flex: 1; min-width: 0; } }
.mlist .tap { cursor: pointer; }
</style>
