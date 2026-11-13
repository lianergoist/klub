<template>
    <div>
        <div v-if="d" class="grid kpis c3">
            <div class="card kpi"><div class="l">Opkrævet, ikke forfaldent</div><div class="v">{{ d.totals.open }}</div></div>
            <div class="card kpi"><div class="l">Forfaldent</div><div class="v neg">{{ d.totals.overdue }}</div><div class="s">{{ d.totals.overdue_count }} regninger</div></div>
            <div class="card kpi"><div class="l">Betalt i alt</div><div class="v">{{ d.totals.paid }}</div></div>
        </div>
        <div class="row between" style="margin-bottom:12px">
            <div class="row filters" style="flex:1">
                <div class="row" style="flex:1;flex-wrap:nowrap;gap:8px">
                    <input v-model="q" placeholder="Søg medlem eller tekst" style="max-width:240px" @input="debounced">
                    <button type="button" class="btn small mobile-only" :class="{ on: showFilters }" @click="showFilters = !showFilters">Filtre<template v-if="activeFilters"> ({{ activeFilters }})</template></button>
                </div>
                <template v-if="!isMobile || showFilters">
                <select v-model="status" style="max-width:180px"><option value="">Alle statusser</option><option value="open">Opkrævet</option><option value="overdue">Forfalden</option><option value="paid">Betalt</option><option value="refunded">Refunderet</option><option value="cancelled">Annulleret</option></select>
                <select v-model="kind" style="max-width:180px"><option value="">Alle typer</option><option value="membership">Kontingent</option><option value="activity">Aktiviteter</option><option value="product">Tilkøb</option></select>
                </template>
            </div>
            <button v-if="canWrite" class="btn primary small" @click="showGen = true">Opkræv kontingent</button>
        </div>
        <!-- Mobil: kort med beløb, status og handlinger -->
        <div class="mobile-only">
            <p v-if="d && !d.rows.length" class="card empty">Ingen regninger.</p>
            <div v-else-if="d" class="card" style="padding:0 14px">
                <ul class="mlist">
                    <li v-for="i in d.rows.slice(0, shown)" :key="i.id" class="tap" @click="open = open === i.id ? null : i.id">
                        <div class="l1"><span>{{ i.description }}</span><strong class="num">{{ i.amount_fmt }}</strong></div>
                        <div class="l2">
                            <span>{{ i.payer }} · {{ i.paid_at ? 'betalt ' + dato(i.paid_at) + ' · ' + i.method : 'forfald ' + dato(i.due_on) }}<span v-if="i.refunded_fmt" class="neg"> · −{{ i.refunded_fmt }}</span></span>
                            <span :class="['tag', { paid: 'ok', overdue: 'bad', open: 'info' }[i.status]]">{{ i.status_label }}</span>
                        </div>
                        <!-- Ubetalte: handlinger vises altid. Betalte: tryk for at refundere -->
                        <div v-if="canWrite && (['open', 'overdue'].includes(i.status) || (i.status === 'paid' && open === i.id))" class="acts" @click.stop>
                            <button v-if="['open', 'overdue'].includes(i.status)" class="btn small" @click="manual = i; mMethod = 'bank'">Registrér betaling</button>
                            <button v-if="i.status === 'overdue'" class="btn ghost small" @click="remind(i)">Påmind</button>
                            <button v-if="i.status === 'paid'" class="btn ghost small" @click="refund = i; rAmount = i.refundable / 100">Refundér</button>
                        </div>
                    </li>
                </ul>
            </div>
            <button v-if="d && d.rows.length > shown" class="btn block" style="margin-top:8px" @click="shown += 40">Vis flere ({{ d.rows.length - shown }} tilbage)</button>
        </div>
        <div class="card desktop-only" style="padding:0 16px">
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Regning</th><th>Betaler</th><th class="num">Beløb</th><th>Status</th><th>Forfald / betalt</th><th></th></tr></thead>
                    <tbody>
                        <tr v-for="i in d?.rows || []" :key="i.id">
                            <td>{{ i.description }}</td>
                            <td class="small">{{ i.payer }}</td>
                            <td class="num">{{ i.amount_fmt }}<div v-if="i.refunded_fmt" class="small neg">−{{ i.refunded_fmt }}</div></td>
                            <td><span :class="['tag', { paid: 'ok', overdue: 'bad', open: 'info' }[i.status]]">{{ i.status_label }}</span></td>
                            <td class="small">{{ i.paid_at ? dato(i.paid_at) + ' · ' + i.method : dato(i.due_on) }}</td>
                            <td style="white-space:nowrap">
                                <template v-if="canWrite">
                                    <button v-if="['open', 'overdue'].includes(i.status)" class="btn small" @click="manual = i; mMethod = 'bank'">Registrér betaling</button>
                                    <button v-if="i.status === 'overdue'" class="btn ghost small" @click="remind(i)">Påmind</button>
                                    <button v-if="i.status === 'paid'" class="btn ghost small" @click="refund = i; rAmount = i.refundable / 100">Refundér</button>
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Modal v-if="manual" title="Registrér betaling" @close="manual = null">
            <p>{{ manual.description }} · <strong>{{ manual.amount_fmt }}</strong></p>
            <div class="stack">
                <label class="field"><span>Betalt med</span><select v-model="mMethod"><option value="bank">Bankoverførsel</option><option value="cash">Kontant</option></select></label>
                <label class="field"><span>Reference (valgfri)</span><input v-model="mRef" placeholder="Fx tekst fra kontoudtog"></label>
                <button class="btn primary block" @click="doManual">Registrér og bogfør</button>
            </div>
        </Modal>
        <Modal v-if="refund" title="Refusion" @close="refund = null">
            <p>{{ refund.description }}</p>
            <div class="stack">
                <label class="field"><span>Beløb (kr.)</span><input type="number" step="0.01" v-model="rAmount"></label>
                <label class="field"><span>Årsag</span><input v-model="rReason" placeholder="Fx Aktivitet aflyst"></label>
                <p class="small muted">Refusionen bliver godkendt i dit navn og logget. Den bogføres som en negativ postering på samme konto.</p>
                <button class="btn primary block" :disabled="!rReason" @click="doRefund">Godkend refusion</button>
            </div>
        </Modal>
        <Modal v-if="showGen" title="Opkræv kontingent" @close="showGen = false">
            <div class="stack">
                <label class="field"><span>Sæson (1. maj – 30. april)</span><select v-model.number="gYear"><option v-for="y in seasons" :key="y" :value="y">{{ y }}/{{ String(y + 1).slice(2) }}</option></select></label>
                <p class="small muted">Medlemskaber med faste datoer, der udløb i sæsonen før, fornyes: samme personer og samme medlemskab (eller det medlemskab, det er sat til at fornyes som, fx halv sæson → hel sæson). Prisen er den, der gælder ved periodens start. Passer personerne ikke længere til medlemskabet (fx et barn, der er blevet voksen), eller er medlemskabet arkiveret, springes husstanden over og vises her bagefter. Udmeldte medlemmer fornyes ikke. Alle får besked.</p>
                <ul v-if="genSkipped.length" class="small" style="margin:0;padding-left:18px;color:var(--warn)"><li v-for="(x, i) in genSkipped" :key="i">{{ x }}</li></ul>
                <button class="btn primary block" @click="generate">Opret opkrævninger</button>
            </div>
        </Modal>
    </div>
</template>
<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import Modal from '../../components/Modal.vue';
import { api, store, dato, toast } from '../../api';
import { isMobile } from '../../mobile';
const canWrite = computed(() => store.user.can.finance_write);
const d = ref(null); const q = ref(''); const status = ref(''); const kind = ref('');
const manual = ref(null); const mMethod = ref('bank'); const mRef = ref('');
const refund = ref(null); const rAmount = ref(0); const rReason = ref('');
const showGen = ref(false);
const showFilters = ref(false); const shown = ref(40); const open = ref(null);
const activeFilters = computed(() => [status.value, kind.value].filter(Boolean).length);
const seasonStartYear = new Date().getMonth() >= 4 ? new Date().getFullYear() : new Date().getFullYear() - 1;
const seasons = [seasonStartYear, seasonStartYear + 1]; const gYear = ref(seasonStartYear + 1);
async function load() { shown.value = 40; d.value = await api(`finance/invoices?q=${encodeURIComponent(q.value)}&status=${status.value}&kind=${kind.value}`); }
let t; const debounced = () => { clearTimeout(t); t = setTimeout(load, 250); };
watch([status, kind], load); onMounted(load);
async function doManual() { try { await api(`finance/invoices/${manual.value.id}/manual`, { method: 'POST', body: { method: mMethod.value, reference: mRef.value } }); manual.value = null; mRef.value = ''; toast('Betaling registreret og bogført'); load(); } catch (e) { toast(e.message, 'error'); } }
async function doRefund() { try { await api(`finance/invoices/${refund.value.id}/refund`, { method: 'POST', body: { amount: Math.round(rAmount.value * 100), reason: rReason.value } }); refund.value = null; rReason.value = ''; toast('Refusion godkendt'); load(); } catch (e) { toast(e.message, 'error'); } }
async function remind(i) { await api(`finance/invoices/${i.id}/remind`, { method: 'POST' }); toast(`Påmindelse sendt til ${i.payer}`); }
const genSkipped = ref([]);
async function generate() {
    try {
        const r = await api('finance/membership/generate', { method: 'POST', body: { year: gYear.value } });
        genSkipped.value = r.skipped || [];
        if (!genSkipped.value.length) showGen.value = false;
        toast(`Sæson ${r.season}: ${r.created} opkrævninger oprettet (${r.total})` + (genSkipped.value.length ? ` – ${genSkipped.value.length} sprunget over` : '')); load();
    } catch (e) { toast(e.message, 'error'); }
}
</script>
<style scoped>
@media (max-width: 899.98px) { .filters { flex: 1 1 100% !important; } .filters input, .filters select { max-width: none !important; } }
.mlist .tap { cursor: pointer; }
.btn.on { border-color: var(--accent); color: var(--accent); }
</style>
