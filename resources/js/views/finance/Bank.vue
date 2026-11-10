<template>
    <div>
        <div class="card">
            <h2>Bankafstemning</h2>
            <p>Hent et kontoudtog som CSV fra netbanken, og upload det her. Systemet finder hver banklinje i regnskabet og viser, hvad der mangler eller afviger. MobilePay-udbetalinger genkendes på udbetalingens id eller beløb, så alle betalingerne i en udbetaling afstemmes på én gang.</p>
            <div class="row">
                <input type="file" accept=".csv,text/csv" style="max-width:320px" @change="upload">
                <button class="btn ghost small" @click="sample">Prøv med et eksempel-udtog</button>
            </div>
            <p class="small muted" style="margin-top:8px">Format: dato;tekst;beløb (som de fleste danske banker eksporterer).</p>
        </div>
        <template v-if="r">
            <div class="grid c3">
                <div class="card kpi"><div class="l">Banklinjer</div><div class="v">{{ r.lines.length }}</div></div>
                <div class="card kpi"><div class="l">Fundet i regnskabet</div><div class="v pos">{{ r.lines.filter(l => l.match).length }}</div></div>
                <div class="card kpi"><div class="l">Mangler i regnskabet</div><div class="v neg">{{ r.lines.filter(l => !l.match).length }}</div></div>
            </div>
            <div class="card" style="padding:0 16px">
                <div class="table-wrap"><table>
                    <thead><tr><th>Bankdato</th><th>Banktekst</th><th class="num">Beløb</th><th>Postering i regnskabet</th></tr></thead>
                    <tbody><tr v-for="(l, i) in r.lines" :key="i">
                        <td>{{ dato(l.date) }}</td><td>{{ l.text }}</td><td :class="['num', l.amount < 0 && 'neg']">{{ kr(l.amount) }}</td>
                        <td><span v-if="l.match?.type === 'payout'" class="tag info">{{ l.match.text }}</span><span v-else-if="l.match" class="tag ok">#{{ l.match.id }} {{ l.match.text }}</span><span v-else class="tag bad">Ikke fundet – skal bogføres</span></td>
                    </tr></tbody>
                </table></div>
            </div>
            <div v-if="r.unmatched_payouts?.length" class="card">
                <h2>MobilePay-udbetalinger uden banklinje ({{ r.unmatched_payouts.length }})</h2>
                <p class="small muted">Udbetalingen er ikke fundet i kontoudtoget. Den er måske ikke landet endnu, eller beløbet afviger.</p>
                <ul class="list"><li v-for="p in r.unmatched_payouts" :key="p.id" class="row between"><span>{{ dato(p.date) }} · {{ p.ref }} ({{ p.count }} betalinger)</span><span class="num">{{ kr(p.net) }}</span></li></ul>
            </div>
            <p v-if="r.saved && r.lines.some(l => l.match?.type === 'payout')" class="small muted">Fundne MobilePay-udbetalinger er markeret som afstemt.</p>
            <div v-if="r.unmatched_entries.length" class="card">
                <h2>Posteringer i perioden uden banklinje ({{ r.unmatched_entries.length }})</h2>
                <p class="small muted">Det kan være kontante betalinger, der endnu ikke er sat i banken, eller fejl. (MobilePay-betalinger står ikke her – de afstemmes via udbetalingerne ovenfor.)</p>
                <ul class="list"><li v-for="e in r.unmatched_entries.slice(0, 30)" :key="e.id" class="row between"><span>#{{ e.id }} {{ dato(e.date) }} · {{ e.text }}</span><span :class="['num', e.amount < 0 && 'neg']">{{ kr(e.amount) }}</span></li></ul>
            </div>
        </template>
    </div>
</template>
<script setup>
import { ref } from 'vue';
import { api, kr, dato, toast } from '../../api';
const r = ref(null);
async function send(file) { const fd = new FormData(); fd.append('file', file); try { r.value = await api('finance/reconcile', { method: 'POST', form: fd }); } catch (e) { toast(e.message, 'error'); } }
const upload = (e) => e.target.files[0] && send(e.target.files[0]);
async function sample() {
    // Eksempel på en uges kontoudtog: de seneste ikke-afstemte MobilePay-udbetalinger (én uden id i teksten,
    // så den findes på beløbet) plus to linjer, der ikke står i regnskabet.
    const p = (await api(`finance/payouts?year=${new Date().getFullYear()}`)).payouts.filter(x => !x.matched).reverse();
    if (!p.length) return toast('Der er ingen ikke-afstemte MobilePay-udbetalinger at lave et eksempel ud fra.', 'error');
    const fmt = (ore) => (ore / 100).toLocaleString('da-DK', { minimumFractionDigits: 2 });
    const dk = (iso) => iso.split('-').reverse().join('-');
    const lines = p.map((x, i) => `${dk(x.date)};${i === 1 ? 'Overførsel MobilePay' : 'MobilePay ' + x.ref};${fmt(x.net)}`);
    const last = p.at(-1).date;
    lines.push(`${dk(p[0].date)};Gebyr kontoførelse;-125,00`, `${dk(last)};Indbetaling uden tekst;350,00`);
    send(new File(['Dato;Tekst;Beløb\n' + lines.join('\n')], 'eksempel.csv', { type: 'text/csv' }));
}
</script>
