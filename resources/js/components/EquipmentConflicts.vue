<template>
    <!-- Det, der optager udstyret: aktiviteter (med træner/tovholder) og bookinger (med den, der har booket) -->
    <ul class="list conflicts">
        <li v-for="(h, i) in holders" :key="i">
            <div><strong v-if="h.kind === 'activity'"><RouterLink :to="`/aktiviteter/${h.activity_id}`">{{ h.title }}</RouterLink></strong>
                <strong v-else>Booket af {{ h.name }}</strong>
                <span class="small muted"> · {{ tidspunkt(h.starts_at) }}–{{ klok(h.ends_at) }}</span></div>
            <div class="small">{{ h.items.join(', ') }}</div>
            <div class="row" style="gap:6px;margin-top:6px">
                <button v-for="p in people(h)" :key="p.id" type="button" class="btn small" @click="write(p.id)">Skriv til {{ p.name.split(' ')[0] }}</button>
                <span v-if="h.kind === 'activity' && !people(h).length" class="small muted">Aktiviteten har ingen træner.</span>
            </div>
        </li>
    </ul>
</template>
<script setup>
import { useRouter } from 'vue-router';
import { api, store, toast, tidspunkt, klok } from '../api';
defineProps({ holders: Array });
const router = useRouter();
const people = (h) => (h.kind === 'activity' ? h.contacts : [{ id: h.user_id, name: h.name }]).filter(p => p.id !== store.user.id);
async function write(id) {
    try { const r = await api('messages/start', { method: 'POST', body: { user_ids: [id] } }); router.push(`/beskeder/${r.id}`); }
    catch (e) { toast(e.message, 'error'); }
}
</script>
<style scoped>
.conflicts li { padding: 10px 0; }
</style>
