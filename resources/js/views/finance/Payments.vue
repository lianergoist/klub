<template>
    <div>
        <div v-if="d" class="grid c3">
            <div class="card kpi"><div class="l">Opkrævet, ikke forfaldent</div><div class="v">{{ d.totals.open }}</div></div>
            <div class="card kpi"><div class="l">Forfaldent</div><div class="v neg">{{ d.totals.overdue }}</div><div class="s">{{ d.totals.overdue_count }} regninger</div></div>
            <div class="card kpi"><div class="l">Betalt i alt</div><div class="v">{{ d.totals.paid }}</div></div>
        </div>
        <div class="row between" style="margin-bottom:12px">
            <div class="row" style="flex:1">
                <input v-model="q" placeholder="Søg medlem eller tekst" style="max-width:240px" @input="debounced">
                <select v-model="status" style="max-width:180px"><option value="">Alle statusser</option><option value="open">Opkrævet</option><option value="overdue">Forfalden</option><option value="paid">Betalt</option><option value="refunded">Refunderet</option><option value="cancelled">Annulleret</option></select>
                <select v-model="kind" style="max-width:180px"><option value="">Alle typer</option><option value="membership">Kontingent</option><option value="activity">Aktiviteter</option><option value="product">Tilkøb</option></select>
            </div>
            <button v-if="canWrite" class="btn primary small" @click="showGen = true">Opkræv kontingent</button>
        </div>
        <div class="card" style="padding:0 16px">
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
                <label class="field"><span>År</span><input type="number" v-model.number="gYear"></label>
                <label class="check"><input type="checkbox" v-model="gProrate"> Forholdsmæssigt kontingent for medlemmer, der er indmeldt i løbet af året</label>
                <p class="small muted">Der oprettes én regning pr. medlem, og én pr. husstand ved familiekontingent. Medlemmer, der allerede har en regning for året, springes over. Alle får besked.</p>
                <button class="btn primary block" @click="generate">Opret opkrævninger</button>
            </div>
        </Modal>
    </div>
</template>
<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import Modal from '../../components/Modal.vue';
import { api, store, dato, toast } from '../../api';
const canWrite = computed(() => store.user.can.finance_write);
const d = ref(null); const q = ref(''); const status = ref(''); const kind = ref('');
const manual = ref(null); const mMethod = ref('bank'); const mRef = ref('');
const refund = ref(null); const rAmount = ref(0); const rReason = ref('');
const showGen = ref(false); const gYear = ref(new Date().getFullYear() + 1); const gProrate = ref(true);
async function load() { d.value = await api(`finance/invoices?q=${encodeURIComponent(q.value)}&status=${status.value}&kind=${kind.value}`); }
let t; const debounced = () => { clearTimeout(t); t = setTimeout(load, 250); };
watch([status, kind], load); onMounted(load);
async function doManual() { try { await api(`finance/invoices/${manual.value.id}/manual`, { method: 'POST', body: { method: mMethod.value, reference: mRef.value } }); manual.value = null; mRef.value = ''; toast('Betaling registreret og bogført'); load(); } catch (e) { toast(e.message, 'error'); } }
async function doRefund() { try { await api(`finance/invoices/${refund.value.id}/refund`, { method: 'POST', body: { amount: Math.round(rAmount.value * 100), reason: rReason.value } }); refund.value = null; rReason.value = ''; toast('Refusion godkendt'); load(); } catch (e) { toast(e.message, 'error'); } }
async function remind(i) { await api(`finance/invoices/${i.id}/remind`, { method: 'POST' }); toast(`Påmindelse sendt til ${i.payer}`); }
async function generate() { try { const r = await api('finance/membership/generate', { method: 'POST', body: { year: gYear.value, prorate: gProrate.value } }); showGen.value = false; toast(`${r.created} opkrævninger oprettet (${r.total})`); load(); } catch (e) { toast(e.message, 'error'); } }
</script>
