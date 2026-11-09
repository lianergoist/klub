<template>
    <div v-if="d">
        <p class="small muted">Budget for {{ fy.year }}. Bestyrelsen vedtager budgettet. Kassereren taster det ind, og derefter vises det på Overblik.</p>
        <div class="card" style="padding:0 16px">
            <table>
                <thead><tr><th>Konto</th><th class="num">Budget (kr.)</th><th class="num">Faktisk</th></tr></thead>
                <tbody>
                    <tr v-for="r in d.rows" :key="r.id">
                        <td>{{ r.number }} {{ r.name }} <span class="small muted">{{ r.type === 'income' ? 'indtægt' : 'udgift' }}</span></td>
                        <td class="num"><input v-if="canWrite" type="number" v-model.number="r.kr" style="max-width:140px;text-align:right"><span v-else>{{ krRound(r.budget) }}</span></td>
                        <td class="num muted">{{ krRound(r.actual) }}</td>
                    </tr>
                </tbody>
                <tfoot><tr><td>Budgetteret resultat</td><td class="num">{{ krRound(result) }}</td><td></td></tr></tfoot>
            </table>
        </div>
        <button v-if="canWrite" class="btn primary" @click="save">Gem budget</button>
    </div>
</template>
<script setup>
import { ref, computed, inject, onMounted } from 'vue';
import { api, store, krRound, toast } from '../../api';
const fy = inject('fy');
const canWrite = computed(() => store.user.can.finance_write);
const d = ref(null);
const result = computed(() => d.value.rows.reduce((s, r) => s + (r.kr || 0) * 100 * (r.type === 'income' ? 1 : -1), 0));
onMounted(async () => { d.value = await api(`finance/overview?year=${fy.year}`); d.value.rows.forEach(r => (r.kr = r.budget / 100)); });
async function save() { await api('finance/budget', { method: 'PUT', body: { year: fy.year, items: d.value.rows.map(r => ({ account_id: r.id, budget: Math.round((r.kr || 0) * 100) })) } }); toast('Budgettet er gemt'); }
</script>
