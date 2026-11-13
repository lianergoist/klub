<template>
    <div v-if="!d" class="spinner"></div>
    <div v-else class="viz-root">
        <!-- Det, der venter på kassereren -->
        <div v-if="tasks.length" class="card tasks">
            <h2 style="margin:0 0 4px">Venter</h2>
            <ul class="mlist">
                <li v-for="t in tasks" :key="t.to"><RouterLink :to="t.to" class="task">
                    <Icon :name="t.icon" class="ti" /><span style="flex:1">{{ t.text }}</span><span :class="['tag', t.cls]">{{ t.badge }}</span><span class="chev">›</span>
                </RouterLink></li>
            </ul>
        </div>
        <div class="grid kpis c4">
            <div class="card kpi"><div class="l">Saldo</div><div class="v">{{ krRound(d.balance) }}</div><div class="s">bank og kasse</div></div>
            <div class="card kpi"><div class="l">Resultat {{ d.year }}</div><div class="v">{{ krRound(d.result) }}</div><div class="s">budget {{ krRound(d.budget_result) }}</div></div>
            <div class="card kpi"><div class="l">Indtægter</div><div class="v">{{ krRound(d.income) }}</div><div class="s">{{ pct(d.income, sum(d.rows, 'income', 'budget')) }} af budget</div></div>
            <div class="card kpi"><div class="l">Udestående</div><div class="v">{{ krRound(d.receivables) }}</div><div class="s"><RouterLink to="/okonomi/betalinger">åbne regninger</RouterLink></div></div>
        </div>

        <div class="card">
            <div class="row between"><h2 style="margin:0">Indtægter og udgifter pr. måned</h2>
                <div class="row small" style="gap:14px"><span class="row" style="gap:6px"><i class="lg" style="background:var(--series-1)"></i>Indtægter</span><span class="row" style="gap:6px"><i class="lg" style="background:var(--series-2)"></i>Udgifter</span></div>
            </div>
            <div style="position:relative;margin-top:10px" @mouseleave="hover = null">
                <svg :viewBox="`0 0 ${W} ${H}`" style="width:100%;height:auto;display:block" role="img" aria-label="Søjlediagram over indtægter og udgifter pr. måned">
                    <g v-for="t in ticks" :key="t">
                        <line :x1="padL" :x2="W" :y1="y(t)" :y2="y(t)" stroke="var(--line)" stroke-width="1" />
                        <text :x="padL - 6" :y="y(t) + 4" text-anchor="end" font-size="11" fill="var(--ink-3)">{{ (t / 100000).toLocaleString('da-DK') }}k</text>
                    </g>
                    <g v-for="(m, i) in d.monthly" :key="m.m">
                        <rect :x="bx(i) - 4" :y="0" :width="bw * 2 + 10" :height="H - padB" fill="transparent" @mouseenter="hover = i" @click="hover = i" />
                        <rect v-if="hover === i" :x="bx(i) - 4" y="0" :width="bw * 2 + 10" :height="H - padB" fill="var(--surface-2)" style="pointer-events:none" />
                        <path :d="bar(bx(i), m.income)" fill="var(--series-1)" style="pointer-events:none" />
                        <path :d="bar(bx(i) + bw + 2, m.expense)" fill="var(--series-2)" style="pointer-events:none" />
                        <text :x="bx(i) + bw + 1" :y="H - padB + 16" text-anchor="middle" font-size="11" fill="var(--ink-3)">{{ months[i] }}</text>
                    </g>
                    <line :x1="padL" :x2="W" :y1="y(0)" :y2="y(0)" stroke="var(--ink-3)" stroke-width="1" />
                </svg>
                <div v-if="hover !== null" class="tip" :style="{ left: Math.min(78, Math.max(4, (bx(hover) / W) * 100)) + '%' }">
                    <strong>{{ monthsLong[hover] }}</strong>
                    <div class="row between" style="gap:12px"><span><i class="lg" style="background:var(--series-1)"></i> Indtægter</span><span class="num">{{ krRound(d.monthly[hover].income) }}</span></div>
                    <div class="row between" style="gap:12px"><span><i class="lg" style="background:var(--series-2)"></i> Udgifter</span><span class="num">{{ krRound(d.monthly[hover].expense) }}</span></div>
                </div>
            </div>
        </div>

        <div class="card">
            <h2>Budget og faktisk resultat</h2>
            <div class="mobile-only">
                <template v-for="type in ['income', 'expense']" :key="type">
                    <div class="mgroup" style="margin-left:0">{{ type === 'income' ? 'Indtægter' : 'Udgifter' }}</div>
                    <ul class="mlist">
                        <li v-for="r in d.rows.filter(r => r.type === type)" :key="r.id">
                            <div class="l1"><span>{{ r.name }}</span><strong class="num">{{ krRound(r.actual) }}</strong></div>
                            <div class="bud"><i :style="{ width: Math.min(100, r.budget ? r.actual / r.budget * 100 : 0) + '%', background: type === 'income' ? 'var(--series-1)' : 'var(--series-2)' }"></i></div>
                            <div class="l2"><span>Budget {{ krRound(r.budget) }}</span><span :class="type === 'expense' && r.actual > r.budget ? 'neg' : ''">{{ r.budget ? Math.round(r.actual / r.budget * 100) + ' %' : '–' }}<span v-if="type === 'expense' && r.actual > r.budget"> · over budget</span></span></div>
                        </li>
                    </ul>
                </template>
                <div class="l1 row between" style="border-top:2px solid var(--line);padding-top:10px;margin-top:6px"><strong>Resultat</strong><strong class="num">{{ krRound(d.result) }}</strong></div>
                <div class="small muted" style="text-align:right">budget {{ krRound(d.budget_result) }}</div>
            </div>
            <div class="table-wrap desktop-only">
                <table>
                    <thead><tr><th>Konto</th><th class="num">Budget</th><th class="num">Faktisk</th><th style="width:34%">Forbrug af budget</th></tr></thead>
                    <tbody>
                        <template v-for="type in ['income', 'expense']" :key="type">
                            <tr><td colspan="4" style="padding-top:16px"><h3 style="margin:0">{{ type === 'income' ? 'Indtægter' : 'Udgifter' }}</h3></td></tr>
                            <tr v-for="r in d.rows.filter(r => r.type === type)" :key="r.id">
                                <td>{{ r.number }} {{ r.name }}</td>
                                <td class="num muted">{{ krRound(r.budget) }}</td>
                                <td class="num">{{ krRound(r.actual) }}</td>
                                <td>
                                    <div class="bud"><i :style="{ width: Math.min(100, r.budget ? r.actual / r.budget * 100 : 0) + '%', background: type === 'income' ? 'var(--series-1)' : 'var(--series-2)' }"></i></div>
                                    <span class="small" :class="type === 'expense' && r.actual > r.budget ? 'neg' : 'muted'">{{ r.budget ? Math.round(r.actual / r.budget * 100) + ' %' : '–' }}<span v-if="type === 'expense' && r.actual > r.budget"> · over budget</span></span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                    <tfoot><tr><td>Resultat</td><td class="num">{{ krRound(d.budget_result) }}</td><td class="num">{{ krRound(d.result) }}</td><td></td></tr></tfoot>
                </table>
            </div>
        </div>

        <div class="card" v-if="d.activities.length">
            <h2>Økonomi pr. aktivitet</h2>
            <ul class="mlist mobile-only">
                <li v-for="a in topActs" :key="a.id">
                    <div class="l1"><RouterLink :to="`/aktiviteter/${a.id}`">{{ a.title }}</RouterLink><strong class="num">{{ krRound(a.income - a.expense) }}</strong></div>
                    <div class="l2"><span>Indtægter {{ krRound(a.income) }} · udgifter {{ krRound(a.expense) }}</span></div>
                </li>
            </ul>
            <div class="table-wrap desktop-only"><table>
                <thead><tr><th>Aktivitet</th><th class="num">Indtægter</th><th class="num">Udgifter</th><th class="num">Netto</th></tr></thead>
                <tbody><tr v-for="a in topActs" :key="a.id"><td><RouterLink :to="`/aktiviteter/${a.id}`">{{ a.title }}</RouterLink></td><td class="num">{{ krRound(a.income) }}</td><td class="num">{{ krRound(a.expense) }}</td><td class="num">{{ krRound(a.income - a.expense) }}</td></tr></tbody>
            </table></div>
        </div>
    </div>
</template>
<script setup>
import { ref, computed, inject, onMounted } from 'vue';
import { api, krRound } from '../../api';
import Icon from '../../components/Icon.vue';
const fy = inject('fy');
const d = ref(null); const hover = ref(null);
const months = ['J', 'F', 'M', 'A', 'M', 'J', 'J', 'A', 'S', 'O', 'N', 'D'];
const monthsLong = ['Januar', 'Februar', 'Marts', 'April', 'Maj', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'December'];
const W = 720, H = 240, padL = 40, padB = 24, bw = 16;
const step = computed(() => {
    const m = Math.max(100000, ...d.value.monthly.flatMap(x => [x.income, x.expense])) / 5;
    const p = 10 ** Math.floor(Math.log10(m));
    return [1, 2, 2.5, 5, 10].map(f => f * p).find(s => s >= m);
});
const max = computed(() => Math.ceil(Math.max(1, ...d.value.monthly.flatMap(x => [x.income, x.expense])) / step.value) * step.value);
const ticks = computed(() => Array.from({ length: Math.round(max.value / step.value) + 1 }, (_, i) => i * step.value));
const y = (v) => 8 + (H - padB - 8) * (1 - v / max.value);
const bx = (i) => padL + 10 + i * ((W - padL - 10) / 12);
function bar(x, v) {
    if (!v) return '';
    const top = y(v), base = y(0), r = Math.min(4, (base - top));
    return `M${x},${base} V${top + r} Q${x},${top} ${x + r},${top} H${x + bw - r} Q${x + bw},${top} ${x + bw},${top + r} V${base} Z`;
}
const sum = (rows, type, k) => rows.filter(r => r.type === type).reduce((s, r) => s + r[k], 0);
const pct = (a, b) => b ? Math.round(a / b * 100) + ' %' : '–';
const topActs = computed(() => [...d.value.activities].sort((a, b) => (b.income - b.expense) - (a.income - a.expense)).slice(0, 10));
const st = ref(null);
const tasks = computed(() => {
    const s = st.value; if (!s) return [];
    return [
        s.claims_pending && { to: '/okonomi/udlaeg', icon: 'receipt', text: s.claims_pending === 1 ? 'Et udlæg venter på at blive udbetalt' : `${s.claims_pending} udlæg venter på at blive udbetalt`, badge: s.claims_pending, cls: 'warn' },
        s.overdue_count && { to: '/okonomi/betalinger', icon: 'money', text: `Forfaldne regninger for ${krRound(s.overdue_amount)}`, badge: s.overdue_count, cls: 'bad' },
        s.unmatched_payouts && { to: '/okonomi/bank', icon: 'bank', text: 'MobilePay-udbetalinger er ikke afstemt mod banken', badge: s.unmatched_payouts, cls: 'info' },
    ].filter(Boolean);
});
onMounted(async () => { api('finance/menu').then(r => (st.value = r)).catch(() => {}); d.value = await api(`finance/overview?year=${fy.year}`); });
</script>
<style scoped>
.tasks { padding-bottom: 6px; }
.task { display: flex; align-items: center; gap: 10px; color: var(--ink); }
.ti { width: 20px; height: 20px; color: var(--accent); flex-shrink: 0; }
.chev { color: var(--ink-3); font-size: 1.3rem; line-height: 1; }
.lg { display: inline-block; width: 10px; height: 10px; border-radius: 3px; }
.tip { position: absolute; top: 4px; background: var(--surface); border: 1px solid var(--line); border-radius: 10px; padding: 8px 10px; box-shadow: var(--shadow); font-size: .82rem; pointer-events: none; min-width: 170px; }
.bud { position: relative; height: 8px; background: var(--surface-2); border-radius: 4px; overflow: visible; margin: 6px 0 3px; }
.bud i { display: block; height: 100%; border-radius: 4px; }
</style>
