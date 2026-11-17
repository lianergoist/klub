<template>
    <div>
        <div class="card">
            <h2>Bankafstemning</h2>
            <p>Hent et kontoudtog som CSV fra netbanken, og upload det her. Systemet finder hver banklinje i regnskabet og viser, hvad der mangler eller afviger. MobilePay-udbetalinger genkendes på udbetalingens id eller beløb, så alle betalingerne i en udbetaling afstemmes på én gang.</p>
            <div class="row">
                <input type="file" accept=".csv,text/csv" style="max-width:320px" @change="upload">
                <button class="btn ghost small" @click="sample">Prøv med et eksempel-udtog</button>
            </div>
            <p class="small muted" style="margin-top:8px">Alle CSV-formater fra netbanken kan bruges. Efter upload vælger du, hvilke kolonner der er dato, tekst og beløb.</p>
        </div>
        <div v-if="pv" class="card">
            <h2>Vælg kolonner</h2>
            <p class="small muted">{{ pv.file.name }} · {{ pv.total }} {{ pv.total === 1 ? 'linje' : 'linjer' }} · {{ pv.encoding }}, adskilt med {{ pv.delimiter }}. Systemet har gættet kolonnerne ud fra overskrifterne – ret dem, hvis de er forkerte.</p>
            <div class="grid c3" style="gap:8px">
                <label class="field"><span>Dato</span><select v-model="pv.map.date"><option :value="null" disabled>Vælg …</option><option v-for="(c, i) in pv.columns" :key="i" :value="i">{{ c }}</option></select></label>
                <label class="field"><span>Tekst</span><select v-model="pv.map.text"><option :value="null">(ingen)</option><option v-for="(c, i) in pv.columns" :key="i" :value="i">{{ c }}</option></select></label>
                <label class="field"><span>Beløb</span><select v-model="pv.map.amount"><option :value="null" disabled>Vælg …</option><option v-for="(c, i) in pv.columns" :key="i" :value="i">{{ c }}</option></select></label>
            </div>
            <div class="table-wrap" style="margin-top:10px"><table class="small">
                <thead><tr><th v-for="(c, i) in pv.columns" :key="i" :class="{ picked: Object.values(pv.map).includes(i) }">{{ c }}<div v-if="colRole(i) && colRole(i) !== c.toLowerCase()" class="small muted">= {{ colRole(i) }}</div></th></tr></thead>
                <tbody><tr v-for="(row, j) in pv.rows" :key="j"><td v-for="(v, i) in row" :key="i" :class="{ picked: Object.values(pv.map).includes(i) }">{{ v }}</td></tr></tbody>
            </table></div>
            <div class="row" style="gap:8px;margin-top:10px">
                <button class="btn primary" :disabled="busy || pv.map.date === null || pv.map.amount === null || pv.map.date === pv.map.amount" @click="reconcile">Afstem</button>
                <button class="btn ghost" @click="pv = null">Annullér</button>
            </div>
        </div>
        <template v-if="r">
            <p v-if="r.skipped" class="small muted">{{ r.skipped }} {{ r.skipped === 1 ? 'linje' : 'linjer' }} uden gyldig dato eller beløb er sprunget over (fx saldo- eller sumlinjer).</p>
            <div class="grid kpis c3">
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
const r = ref(null); const pv = ref(null); const busy = ref(false);
// Trin 1: læs filen og vis de første linjer med serverens gæt på kolonnerne
async function send(file) {
    const fd = new FormData(); fd.append('file', file); r.value = null;
    try { const p = await api('finance/reconcile/preview', { method: 'POST', form: fd }); pv.value = { ...p, file, map: { ...p.guess } }; }
    catch (e) { toast(e.message, 'error'); }
}
const upload = (e) => { const f = e.target.files[0]; e.target.value = ''; if (f) send(f); };
const colRole = (i) => ({ date: 'dato', text: 'tekst', amount: 'beløb' })[Object.keys(pv.value.map).find(k => pv.value.map[k] === i)] || '';
// Trin 2: afstem med de valgte kolonner
async function reconcile() {
    busy.value = true;
    const fd = new FormData(); fd.append('file', pv.value.file);
    for (const k of ['date', 'text', 'amount']) if (pv.value.map[k] !== null) fd.append(`${k}_col`, pv.value.map[k]);
    try { r.value = await api('finance/reconcile', { method: 'POST', form: fd }); pv.value = null; }
    catch (e) { toast(e.message, 'error'); } finally { busy.value = false; }
}
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
<style scoped>
th.picked, td.picked { background: var(--accent-soft, rgba(126, 90, 140, .1)); }
</style>
