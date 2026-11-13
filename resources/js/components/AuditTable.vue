<template>
    <div>
        <p class="small muted">{{ intro }}</p>
        <div v-if="!rows" class="spinner"></div>
        <div v-else-if="!rows.length" class="card empty">Ingen ændringer endnu.</div>
        <div v-else class="card" style="padding:0 16px">
            <div class="table-wrap"><table>
                <thead><tr><th>Tidspunkt</th><th>Hvem</th><th>Handling</th><th>Objekt</th><th>Detaljer</th></tr></thead>
                <tbody><tr v-for="l in rows" :key="l.id">
                    <td class="small" style="white-space:nowrap">{{ tidspunkt(l.at) }}</td><td>{{ l.by }}</td><td><span class="tag">{{ labels[l.action] || l.action }}</span></td><td class="small muted">{{ l.subject }}</td>
                    <td class="small"><code v-if="l.changes" style="white-space:pre-wrap;word-break:break-word">{{ short(l.changes) }}</code></td>
                </tr></tbody>
            </table></div>
        </div>
    </div>
</template>
<script setup>
import { ref, onMounted } from 'vue';
import { api, tidspunkt } from '../api';
import { auditLabels as labels } from '../auditLabels';
// endpoint: 'finance/audit' (kun regnskab) eller 'settings/audit' (alt)
const props = defineProps({ endpoint: String, intro: String });
const rows = ref(null);

const short = (c) => { const s = JSON.stringify(c); return s.length > 160 ? s.slice(0, 160) + '…' : s; };
onMounted(async () => { rows.value = await api(props.endpoint); });
</script>
