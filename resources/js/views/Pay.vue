<template>
    <div :style="`max-width:440px;margin:0 auto;${store.user?.status === 'guest' ? 'padding:24px 16px' : ''}`">
        <div v-if="!invs" class="spinner"></div>
        <template v-else-if="done">
            <div class="card" style="text-align:center;padding:32px 20px">
                <div style="width:64px;height:64px;border-radius:50%;background:var(--ok-soft);color:var(--ok);display:grid;place-items:center;margin:0 auto 14px"><Icon name="check" style="width:34px" /></div>
                <h1>{{ isSignup ? 'Velkommen i klubben!' : isUpgrade ? 'I er nu en familie i klubben' : 'Betaling gennemført' }}</h1>
                <p class="muted">{{ kr(total) }} er betalt. Du har fået en kvittering i din indbakke<span v-if="store.user.email"> og på mail</span>.</p>
                <p v-if="store.user.status !== 'guest'" class="small muted">Regningen er automatisk markeret som betalt og bogført i klubbens regnskab.</p>
                <p v-if="isSignup" class="small">Du er nu aktivt medlem og kan tilmelde dig aktiviteter med det samme.</p>
                <RouterLink :to="store.user.status === 'guest' ? '/prov/tak' : activityId ? `/aktiviteter/${activityId}` : isSignup ? '/aktiviteter' : isUpgrade ? '/profil/familie' : '/profil/regninger'" class="btn primary block">Fortsæt</RouterLink>
            </div>
        </template>
        <template v-else>
            <a href="#" class="small" @click.prevent="$router.back()">‹ Tilbage</a>
            <h1 style="margin:8px 0 14px">Betaling</h1>
            <div class="card">
                <div class="small muted">Du betaler</div>
                <div style="font-size:2rem;font-weight:700;letter-spacing:-.02em">{{ kr(total) }}</div>
                <ul class="list" style="margin:6px 0 12px">
                    <li v-for="i in invs" :key="i.id" class="row between small" style="flex-wrap:nowrap"><span>{{ i.description }}</span><span class="num">{{ i.amount_fmt }}</span></li>
                </ul>
                <div v-if="!payable.length" class="banner info">Regningen er allerede {{ invs[0].status_label.toLowerCase() }}.</div>
                <template v-else>
                    <button class="btn mobilepay block" :disabled="busy" @click="pay('mobilepay')">{{ busy ? 'Venter på MobilePay …' : 'Betal med MobilePay' }}</button>
                    <button class="btn block" style="margin-top:8px" :disabled="busy" @click="pay('card')">Betal med kort</button>
                    <p class="small muted" style="margin-top:12px">Pladsen er reserveret til dig i 15 minutter, mens du betaler.</p>
                </template>
            </div>
            <div class="banner warn small"><strong>Proof of concept:</strong> Betalingen er simuleret, og der trækkes ingen penge. I den rigtige løsning åbner MobilePay-appen, og klubsystemet får besked fra Vipps MobilePay, når betalingen er gennemført.</div>
        </template>
    </div>
</template>
<script setup>
import { ref, computed, onMounted } from 'vue';
import Icon from '../components/Icon.vue';
import { api, store, kr, toast, refreshMe } from '../api';
const props = defineProps({ id: String });
const invs = ref(null); const busy = ref(false); const done = ref(false); const activityId = ref(null);
const isSignup = computed(() => invs.value?.some(i => i.kind === 'membership' && i.member_id_pending));
const isUpgrade = computed(() => invs.value?.some(i => i.is_upgrade));
const payable = computed(() => invs.value.filter(i => ['open', 'overdue'].includes(i.status)));
const frozen = ref(null);
const total = computed(() => frozen.value ?? (payable.value.length ? payable.value : invs.value).reduce((s, i) => s + i.amount, 0));
onMounted(async () => { invs.value = await Promise.all(props.id.split(',').map(id => api(`invoices/${id}`))); });
async function pay(method) {
    busy.value = true; frozen.value = total.value;
    await new Promise(r => setTimeout(r, method === 'mobilepay' ? 1600 : 900));
    try {
        for (const i of payable.value) { const r = await api(`invoices/${i.id}/pay`, { method: 'POST', body: { method } }); activityId.value = r.activity_id; }
        done.value = true; refreshMe();
    } catch (e) { toast(e.message, 'error'); } finally { busy.value = false; }
}
</script>
