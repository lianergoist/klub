<template>
    <!-- Det, der optager udstyret: aktiviteter (med træner/tovholder) og bookinger (med den, der har booket).
         Med activityId (aktivitetens side): bed den, der har booket, om at flytte – og annullér bookingen efter fristen. -->
    <ul class="list conflicts">
        <li v-for="(h, i) in holders" :key="i">
            <div><strong v-if="h.kind === 'activity'"><RouterLink :to="`/aktiviteter/${h.activity_id}`">{{ h.title }}</RouterLink></strong>
                <strong v-else>Booket af {{ h.name }}</strong>
                <span class="small muted"> · {{ tidspunkt(h.starts_at) }}–{{ klok(h.ends_at) }}</span></div>
            <div class="small">{{ h.items.join(', ') }}</div>

            <!-- Status på anmodningen om at flytte -->
            <div v-if="h.move" class="small" style="margin-top:4px">
                <template v-if="h.move.status === 'pending' && !h.move.overdue">
                    <span class="tag warn">Anmodet om at flytte</span> Svarfrist {{ tidspunkt(h.move.deadline_at) }}
                </template>
                <template v-else-if="h.move.status === 'pending'">
                    <span class="tag bad">Intet svar</span> Fristen ({{ tidspunkt(h.move.deadline_at) }}) er overskredet<template v-if="h.can_cancel"> – du kan annullere bookingen</template><template v-else> – bestyrelsen kan annullere bookingen</template>.
                </template>
                <template v-else-if="h.move.status === 'declined'">
                    <span class="tag bad">Sagt nej</span> "{{ h.move.reply }}"<span class="muted"> – bestyrelsen kan annullere bookingen, hvis det er nødvendigt.</span>
                </template>
            </div>

            <div class="row" style="gap:6px;margin-top:6px">
                <template v-if="activityId && canRequest && h.kind === 'booking' && h.user_id !== store.user.id">
                    <button v-if="!h.move || h.move.status !== 'pending'" type="button" class="btn small primary" @click="ask = ask === i ? null : i">Bed om at flytte</button>
                    <button v-if="h.can_cancel" type="button" class="btn small danger" :disabled="busy" @click="cancel(h)">Annullér booking</button>
                </template>
                <button v-for="p in people(h)" :key="p.id" type="button" class="btn small" @click="write(p.id)">Skriv til {{ p.name.split(' ')[0] }}</button>
                <span v-if="h.kind === 'activity' && !people(h).length" class="small muted">Aktiviteten har ingen træner.</span>
            </div>
            <div v-if="ask === i" class="stack" style="gap:6px;margin-top:8px">
                <input v-model="msg" placeholder="Besked (valgfri), fx hvorfor udstyret skal bruges">
                <p class="small muted" style="margin:0">{{ first(h.name) }} får besked og kan flytte, annullere eller sige nej. Svarer {{ first(h.name) }} ikke inden fristen, kan bookingen annulleres.</p>
                <div><button type="button" class="btn small primary" :disabled="busy" @click="request(h)">Send anmodning</button></div>
            </div>
        </li>
    </ul>
</template>
<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { api, store, toast, tidspunkt, klok } from '../api';
const props = defineProps({ holders: Array, activityId: [Number, String], canRequest: Boolean });
const emit = defineEmits(['changed']);
const router = useRouter();
const ask = ref(null); const msg = ref(''); const busy = ref(false);
const first = (n) => n.split(' ')[0];
const people = (h) => (h.kind === 'activity' ? h.contacts : [{ id: h.user_id, name: h.name }]).filter(p => p.id !== store.user.id);
async function write(id) {
    try { const r = await api('messages/start', { method: 'POST', body: { user_ids: [id] } }); router.push(`/beskeder/${r.id}`); }
    catch (e) { toast(e.message, 'error'); }
}
async function request(h) {
    busy.value = true;
    try {
        await api(`activities/${props.activityId}/move-requests`, { method: 'POST', body: { booking_ids: [h.booking_id], message: msg.value } });
        toast(`Anmodningen er sendt til ${h.name}`); ask.value = null; msg.value = ''; emit('changed');
    } catch (e) { toast(e.message, 'error'); } finally { busy.value = false; }
}
async function cancel(h) {
    if (!confirm(`Annullér bookingen, som ${h.name} har lavet? ${first(h.name)} får besked.`)) return;
    busy.value = true;
    try { await api(`equipment-bookings/${h.booking_id}/cancel`, { method: 'POST' }); toast('Bookingen er annulleret'); emit('changed'); }
    catch (e) { toast(e.message, 'error'); } finally { busy.value = false; }
}
</script>
<style scoped>
.conflicts li { padding: 10px 0; }
</style>
