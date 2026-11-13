<template>
    <div v-if="d">
        <div class="grid kpis c4">
            <div class="card kpi"><div class="l">Udbetalt {{ fy.year }}</div><div class="v">{{ krRound(d.totals.net) }}</div><div class="s">{{ d.payouts.length }} udbetalinger</div></div>
            <div class="card kpi"><div class="l">Gebyrer {{ fy.year }}</div><div class="v">{{ krRound(d.totals.fees) }}</div><div class="s">{{ d.totals.gross ? (d.totals.fees / d.totals.gross * 100).toFixed(2).replace('.', ',') : 0 }} % af {{ krRound(d.totals.gross) }}</div></div>
            <div class="card kpi"><div class="l">Ikke afstemt mod bank</div><div :class="['v', d.totals.unmatched ? 'neg' : 'pos']">{{ d.totals.unmatched }}</div><div class="s"><RouterLink to="/okonomi/bank">Gå til bankafstemning</RouterLink></div></div>
            <div class="card kpi"><div class="l">Afventer udbetaling</div><div class="v">{{ krRound(d.awaiting.amount) }}</div><div class="s">{{ d.awaiting.count }} betalinger</div></div>
        </div>

        <div class="card">
            <div class="row between">
                <h2 style="margin:0">Sådan virker det</h2>
                <button v-if="canWrite" class="btn primary small" :disabled="busy" @click="fetchNow">{{ busy ? 'Henter …' : 'Hent afregningsrapport nu' }}</button>
            </div>
            <ol class="small" style="margin:10px 0 0;padding-left:20px;color:var(--ink-2)">
                <li>Når et medlem betaler, bogføres beløbet med det samme som indtægt, og regningen markeres betalt.</li>
                <li>MobilePay udbetaler samlet til banken, fratrukket gebyrer. Hver morgen kl. 6.30 henter systemet afregningsrapporten, kobler betalingerne til udbetalingen og bogfører gebyrerne på kontoen <em>2600 Gebyrer</em>.</li>
                <li>Når kontoudtoget uploades under <RouterLink to="/okonomi/bank">Bankafstemning</RouterLink>, findes udbetalingen på sit id eller beløb. Så er alle betalingerne i den afstemt på én gang.</li>
            </ol>
            <p class="banner warn small" style="margin:12px 0 0"><strong>Proof of concept:</strong> Afregningsrapporten er simuleret: én udbetaling pr. bankdag og {{ String(d.fee_percent).replace('.', ',') }} % i gebyr. I drift hentes den fra Vipps MobilePays rapport-API med klubbens rigtige satser.</p>
        </div>

        <div class="card" style="padding:0 16px">
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Udbetaling</th><th>Dato</th><th class="num">Betalinger</th><th class="num">Brutto</th><th class="num">Gebyr</th><th class="num">Til banken</th><th>Bank</th></tr></thead>
                    <tbody>
                        <template v-for="p in d.payouts" :key="p.id">
                            <tr class="click" @click="toggle(p)">
                                <td><span class="chev">{{ open[p.id] ? '▾' : '▸' }}</span> <code class="small">{{ p.ref }}</code></td>
                                <td style="white-space:nowrap">{{ dato(p.date) }}</td>
                                <td class="num">{{ p.count }}</td>
                                <td class="num">{{ kr(p.gross) }}</td>
                                <td class="num neg">−{{ kr(p.fees) }}</td>
                                <td class="num"><strong>{{ kr(p.net) }}</strong></td>
                                <td><span :class="['tag', p.matched ? 'ok' : 'warn']">{{ p.matched ? 'Afstemt' : 'Ikke afstemt' }}</span></td>
                            </tr>
                            <tr v-if="open[p.id]" class="sub">
                                <td colspan="7">
                                    <div v-if="open[p.id] === 'loading'" class="small muted">Henter …</div>
                                    <table v-else class="inner">
                                        <tbody>
                                            <tr v-for="x in open[p.id]" :key="x.id">
                                                <td class="small" style="white-space:nowrap">{{ tidspunkt(x.at) }}</td>
                                                <td class="small">{{ x.description }}<span v-if="x.payer" class="muted"> · {{ x.payer }}</span></td>
                                                <td class="small muted">{{ x.method }}</td>
                                                <td class="num small">{{ kr(x.amount) }}</td>
                                                <td class="num small neg">{{ x.fee ? '−' + kr(x.fee) : '' }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <p v-if="p.bank_text" class="small muted" style="margin:6px 0 0">Banklinje: {{ p.bank_text }}</p>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <p v-if="!d.payouts.length" class="empty">Ingen udbetalinger i {{ fy.year }}.</p>
        </div>
    </div>
    <div v-else class="spinner"></div>
</template>
<script setup>
import { ref, reactive, computed, inject, onMounted } from 'vue';
import { api, store, kr, krRound, dato, tidspunkt, toast } from '../../api';
const fy = inject('fy');
const canWrite = computed(() => store.user.can.finance_write);
const d = ref(null); const busy = ref(false); const open = reactive({});
async function load() { d.value = await api(`finance/payouts?year=${fy.year}`); }
async function toggle(p) {
    if (open[p.id]) { delete open[p.id]; return; }
    open[p.id] = 'loading'; open[p.id] = await api(`finance/payouts/${p.id}`);
}
async function fetchNow() {
    busy.value = true;
    try {
        const r = await api('finance/payouts/fetch', { method: 'POST' });
        toast(r.created ? `${r.created} nye udbetalinger indlæst (${r.payments} betalinger, gebyrer ${kr(r.fees)})` : 'Ingen nye udbetalinger');
        await load();
    } catch (e) { toast(e.message, 'error'); } finally { busy.value = false; }
}
onMounted(load);
</script>
<style scoped>
.chev { display: inline-block; width: 12px; color: var(--ink-3); }
tr.sub > td { background: var(--surface-2); padding: 8px 12px 12px 32px; }
.inner td { border-bottom: 1px solid var(--line); padding: 5px 8px; }
</style>
