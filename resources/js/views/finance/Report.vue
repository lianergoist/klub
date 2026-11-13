<template>
    <div v-if="d">
        <div class="row between noprint" style="margin-bottom:12px">
            <p class="small muted" style="margin:0">Til generalforsamlingen. Brug <em>Udskriv</em> og vælg "Gem som PDF".</p>
            <button v-if="IS_APP" class="btn small" @click="openInBrowser">Udskriv / PDF (åbner i browseren)</button>
            <button v-else class="btn primary small" @click="print">Udskriv / gem som PDF</button>
        </div>
        <div class="card report">
            <h1>Årsregnskab {{ d.year }}</h1>
            <p class="muted">Windsurfingklubben · udskrevet {{ dato(new Date().toISOString()) }}<span v-if="d.year === new Date().getFullYear()"> · <strong>foreløbigt, året er ikke afsluttet</strong></span></p>
            <h2>Resultatopgørelse</h2>
            <table>
                <thead><tr><th></th><th class="num">Regnskab {{ d.year }}</th><th class="num">Budget {{ d.year }}</th></tr></thead>
                <tbody>
                    <tr><td colspan="3"><strong>Indtægter</strong></td></tr>
                    <tr v-for="r in d.rows.filter(r => r.type === 'income')" :key="r.id"><td>{{ r.name }}</td><td class="num">{{ kr(r.actual) }}</td><td class="num muted">{{ kr(r.budget) }}</td></tr>
                    <tr><td><strong>Indtægter i alt</strong></td><td class="num"><strong>{{ kr(d.income) }}</strong></td><td class="num muted">{{ kr(sum('income')) }}</td></tr>
                    <tr class="gap"><td colspan="3"><strong>Udgifter</strong></td></tr>
                    <tr v-for="r in d.rows.filter(r => r.type === 'expense')" :key="r.id"><td>{{ r.name }}</td><td class="num">{{ kr(r.actual) }}</td><td class="num muted">{{ kr(r.budget) }}</td></tr>
                    <tr><td><strong>Udgifter i alt</strong></td><td class="num"><strong>{{ kr(d.expense) }}</strong></td><td class="num muted">{{ kr(sum('expense')) }}</td></tr>
                </tbody>
                <tfoot><tr><td>Årets resultat</td><td class="num">{{ kr(d.result) }}</td><td class="num">{{ kr(d.budget_result) }}</td></tr></tfoot>
            </table>
            <h2 class="sec">Balance pr. 31.12.{{ d.year }}</h2>
            <table>
                <tbody>
                    <tr><td>Bank og kasse</td><td class="num">{{ kr(d.balance) }}</td></tr>
                    <tr><td>Tilgodehavender (ubetalte regninger)</td><td class="num">{{ kr(d.receivables) }}</td></tr>
                </tbody>
                <tfoot><tr><td>Aktiver i alt</td><td class="num">{{ kr(d.balance + d.receivables) }}</td></tr></tfoot>
            </table>
            <h2 class="sec">Noter</h2>
            <p class="small">Regnskabet er udarbejdet på grundlag af klubsystemets posteringer. Alle posteringer har bilag eller stammer fra registrerede betalinger. Rettelser fremgår som modposteringer. Ændringsloggen er tilgængelig for revisor.</p>
            <div class="grid c2 sign">
                <div>Kasserer</div><div>Revisor</div>
            </div>
        </div>
    </div>
</template>
<script setup>
import { ref, inject, onMounted } from 'vue';
import { api, kr, dato, IS_APP } from '../../api';
const fy = inject('fy'); const d = ref(null);
const sum = (t) => d.value.rows.filter(r => r.type === t).reduce((s, r) => s + r.budget, 0);
onMounted(async () => { d.value = await api(`finance/overview?year=${fy.year}`); });
function print() { window.print(); }
// Appens WebView kan ikke udskrive – åbn siden i telefonens browser
async function openInBrowser() { (await import('../../native')).openWeb('/okonomi/aarsregnskab'); }
</script>
<style scoped>
.report { max-width: 760px; }
.report h2 { margin-top: 18px; }
.report .sec { margin-top: 24px; }
.report tr.gap td { padding-top: 14px; }
.sign { margin-top: 56px; } .sign > div { border-top: 1px solid var(--ink-3); padding-top: 6px; font-size: .85rem; color: var(--ink-2); }

/* Udskrift: hele årsregnskabet på én A4-side */
@media print {
    @page { size: A4; margin: 15mm 16mm; }
    .report { max-width: none; font-size: 10pt; line-height: 1.3; color: #000; }
    .report h1 { font-size: 17pt; margin: 0 0 2pt; }
    .report > p.muted { margin: 0 0 8pt; font-size: 9pt; }
    .report h2 { font-size: 12pt; margin: 12pt 0 4pt; }
    .report .sec { margin-top: 14pt; }
    .report table { font-size: 10pt; }
    .report th, .report td { padding: 2.5pt 6pt; }
    .report th { font-size: 8pt; }
    .report tr.gap td { padding-top: 7pt; }
    .report tfoot td { padding-top: 4pt; }
    .report p.small { font-size: 9pt; margin: 0; }
    .sign { margin-top: 36pt; gap: 40pt; }
    .sign > div { font-size: 9pt; color: #000; }
    .report table, .sign { break-inside: avoid; }
}
</style>
