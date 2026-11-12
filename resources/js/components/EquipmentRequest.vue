<template>
    <!-- Ét ønske om at låne udstyr, som en aktivitet har booket: træneren svarer ja eller nej -->
    <div class="stack" style="gap:6px">
        <div><strong>{{ x.user }}</strong> vil låne {{ x.items.join(', ') }}</div>
        <div class="small muted">{{ tidspunkt(x.starts_at) }}–{{ klok(x.ends_at) }}<template v-if="showActivity"> · under <RouterLink :to="`/aktiviteter/${x.activity_id}`">{{ x.activity }}</RouterLink></template></div>
        <div v-if="x.note" class="small">"{{ x.note }}"</div>
        <template v-if="x.status === 'pending' && (x.booking_status ?? 'requested') === 'requested'">
            <input v-model="reply" placeholder="Kort svar (valgfrit)">
            <div class="row" style="gap:8px">
                <button class="btn primary small" :disabled="busy" @click="decide(true)">Ja, det er fint</button>
                <button class="btn small danger" :disabled="busy" @click="decide(false)">Nej</button>
                <button class="btn small ghost" @click="write">Skriv til {{ x.user.split(' ')[0] }}</button>
            </div>
        </template>
        <div v-else class="small"><span :class="['tag', x.status === 'accepted' ? 'ok' : x.status === 'rejected' ? 'bad' : '']">{{ { accepted: 'Sagt ja', rejected: 'Sagt nej', pending: 'Trukket tilbage' }[x.status] }}</span>
            <span v-if="x.reply" class="muted"> "{{ x.reply }}"</span></div>
    </div>
</template>
<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { api, toast, tidspunkt, klok } from '../api';
const props = defineProps({ x: Object, showActivity: { type: Boolean, default: true } });
const emit = defineEmits(['done']);
const router = useRouter();
const reply = ref(''); const busy = ref(false);
async function decide(accept) {
    busy.value = true;
    try {
        await api(`equipment-approvals/${props.x.id}/decide`, { method: 'POST', body: { accept, reply: reply.value } });
        toast(accept ? 'Du har sagt ja – udstyret er lånt ud' : 'Du har sagt nej'); emit('done');
    } catch (e) { toast(e.message, 'error'); } finally { busy.value = false; }
}
async function write() {
    try { const r = await api('messages/start', { method: 'POST', body: { user_ids: [props.x.user_id] } }); router.push(`/beskeder/${r.id}`); }
    catch (e) { toast(e.message, 'error'); }
}
</script>
