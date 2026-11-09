<template>
    <div>
        <div class="card">
            <h2>Bankafstemning</h2>
            <p>Hent et kontoudtog som CSV fra netbanken, og upload det her. Systemet finder hver banklinje i regnskabet (samme beløb, ±3 dage) og viser, hvad der mangler eller afviger.</p>
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
                        <td><span v-if="l.match" class="tag ok">#{{ l.match.id }} {{ l.match.text }}</span><span v-else class="tag bad">Ikke fundet – skal bogføres</span></td>
                    </tr></tbody>
                </table></div>
            </div>
            <div v-if="r.unmatched_entries.length" class="card">
                <h2>Posteringer i perioden uden banklinje ({{ r.unmatched_entries.length }})</h2>
                <p class="small muted">Det kan være kontante betalinger, MobilePay-udbetalinger, der samles i én overførsel, eller fejl.</p>
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
    const y = new Date().getFullYear();
    const csv = `Dato;Tekst;Beløb\n15-01-${y};Forsikring Tryg;-8.420,00\n20-01-${y};One.com;-1.499,00\n10-02-${y};Aarhus Kommune tilskud;15.000,00\n01-03-${y};Havnens Bådservice;10.000,00\n12-03-${y};Sejlmageren ApS;-18.900,00\n14-03-${y};Bankgebyr;-125,00\n28-03-${y};Foilshop;-24.500,00\n05-04-${y};Bådværftet;-3.850,00\n22-04-${y};DS Kursus;-6.800,00\n02-05-${y};Ukendt indbetaling;350,00`;
    send(new File([csv], 'eksempel.csv', { type: 'text/csv' }));
}
</script>
