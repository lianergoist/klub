<template>
    <div>
        <!-- Mobil og app: /okonomi er en menu med status; undersiderne har et tilbage-link (som Min profil) -->
        <template v-if="isMobile && isMenu">
            <h1>Økonomi</h1>
            <p class="small muted" style="margin:0 0 14px">{{ store.user.can.finance_write ? 'Du kan indtaste og rette (kasserer).' : 'Du har læseadgang.' }}</p>
            <div v-for="(g, gi) in menuGroups" :key="gi" class="card fmenu">
                <ul class="list">
                    <li v-for="p in g" :key="p.id"><RouterLink :to="`/okonomi/${p.id}`" class="mrow">
                        <Icon :name="p.icon" class="mi" />
                        <span class="mtext"><strong>{{ p.title }}</strong><span class="small muted">{{ p.hint }}</span></span>
                        <span v-if="p.badge" :class="['tag', p.badgeCls]">{{ p.badge }}</span>
                        <span class="chev" aria-hidden="true">›</span>
                    </RouterLink></li>
                </ul>
            </div>
        </template>
        <template v-else-if="isMobile">
            <div class="noprint">
                <RouterLink to="/okonomi" class="small">‹ Økonomi</RouterLink>
                <div class="topbar" style="margin-top:4px">
                    <h1 style="margin:0">{{ cur?.title }}</h1>
                    <select v-if="cur?.year" v-model="fy.year" style="width:auto;min-height:36px;padding:5px 10px" aria-label="Regnskabsår"><option v-for="y in years" :key="y" :value="y">{{ y }}</option></select>
                </div>
                <p v-if="cur?.desktop" class="small muted" style="margin:-6px 0 12px">Bedst på en computer, men kan bruges her.</p>
            </div>
        </template>
        <template v-else>
            <div class="topbar noprint">
                <div><h1>Økonomi</h1>
                    <p class="small muted" style="margin:0">{{ store.user.can.finance_write ? 'Du kan indtaste og rette (kasserer).' : 'Du har læseadgang.' }}</p></div>
                <select v-model="fy.year" style="width:auto"><option v-for="y in years" :key="y" :value="y">Regnskabsår {{ y }}</option></select>
            </div>
            <nav class="tabs noprint">
                <RouterLink v-for="p in tabs" :key="p.id" :to="p.id === 'overblik' ? '/okonomi' : `/okonomi/${p.id}`">{{ p.tab }}</RouterLink>
            </nav>
        </template>
        <RouterView v-if="!(isMobile && isMenu)" :key="fy.year" />
    </div>
</template>
<script setup>
import { provide, reactive, ref, computed, watch, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Icon from '../../components/Icon.vue';
import { api, store, krRound } from '../../api';
import { isMobile } from '../../mobile';

const route = useRoute(); const router = useRouter();
const now = new Date().getFullYear();
const years = [now, now - 1];
const fy = reactive({ year: now });
provide('fy', fy);

// year: siden bruger regnskabsåret · desktop: bedst på computer · group 2 = sjældnere brugt
const tabs = [
    { id: 'overblik', tab: 'Overblik', title: 'Overblik', icon: 'chart', hint: 'Saldo, resultat og budget', year: true },
    { id: 'posteringer', tab: 'Posteringer', title: 'Posteringer', icon: 'list', hint: 'Bogføring og bilag', year: true },
    { id: 'betalinger', tab: 'Betalinger', title: 'Betalinger', icon: 'money', hint: 'Regninger, påmindelser og refusioner' },
    { id: 'udlaeg', tab: 'Udlæg', title: 'Udlæg', icon: 'receipt', hint: 'Kvitteringer fra medlemmer' },
    { id: 'budget', tab: 'Budget', title: 'Budget', icon: 'flag', hint: 'Budget pr. konto', year: true, group: 2, desktop: true },
    { id: 'mobilepay', tab: 'MobilePay', title: 'MobilePay', icon: 'phone', hint: 'Afregninger og gebyrer', year: true },
    { id: 'bank', tab: 'Bankafstemning', title: 'Bankafstemning', icon: 'bank', hint: 'Afstem mod kontoudtog fra banken', desktop: true },
    { id: 'aarsregnskab', tab: 'Årsregnskab', title: 'Årsregnskab', icon: 'file', hint: 'Resultat og balance til generalforsamlingen', year: true, group: 2 },
    { id: 'log', tab: 'Ændringslog', title: 'Ændringslog', icon: 'clock', hint: 'Hvem har bogført og rettet hvad', group: 2 },
];
const sub = computed(() => route.path.replace(/^\/okonomi\/?/, '').split('/')[0]);
const isMenu = computed(() => sub.value === '');
const cur = computed(() => tabs.find(t => t.id === sub.value));

// Status i menuen (kun mobil)
const st = ref(null);
async function loadStatus() { try { st.value = await api('finance/menu'); } catch {} }
const menuGroups = computed(() => {
    const s = st.value || {};
    const extra = {
        overblik: st.value ? { hint: `Saldo ${krRound(s.balance)}` } : {},
        udlaeg: s.claims_pending ? { badge: `${s.claims_pending} venter`, badgeCls: 'warn' } : {},
        betalinger: s.overdue_count ? { badge: `${s.overdue_count} forfaldne`, badgeCls: 'bad', hint: `${krRound(s.overdue_amount)} forfaldent` } : {},
        bank: s.unmatched_payouts ? { badge: `${s.unmatched_payouts} ikke afstemt`, badgeCls: 'info' } : {},
    };
    const rows = tabs.map(t => ({ ...t, ...(extra[t.id] || {}) }));
    return [rows.filter(t => !t.group), rows.filter(t => t.group)];
});

// På computer er /okonomi selve overblikket (mobilens /okonomi/overblik sendes dertil)
watch([isMobile, sub], () => { if (!isMobile.value && sub.value === 'overblik') router.replace('/okonomi'); }, { immediate: true });
watch(isMenu, (m) => { if (m && isMobile.value) loadStatus(); });
onMounted(() => { if (isMobile.value && isMenu.value) loadStatus(); });
</script>
<style scoped>
.fmenu { padding: 2px 16px; }
.fmenu .list > li { padding: 0; }
.mrow { display: flex; align-items: center; gap: 12px; padding: 12px 0; color: var(--ink); min-height: 56px; }
.mi { width: 22px; height: 22px; color: var(--accent); flex-shrink: 0; }
.mtext { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.mtext .small { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.chev { color: var(--ink-3); font-size: 1.4rem; line-height: 1; }
</style>
