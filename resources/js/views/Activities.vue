<template>
    <div>
        <div class="topbar">
            <div><h1>Aktiviteter</h1><p class="muted small" style="margin:0">Hej {{ store.user.name.split(' ')[0] }} 👋</p></div>
            <RouterLink v-if="store.user.can.trainer" to="/aktiviteter/ny" class="btn primary"><Icon name="plus" style="width:18px" /> Ny</RouterLink>
        </div>

        <div v-if="store.user.status === 'pending'" class="banner warn row between">
            <span>Din indmeldelse er ikke betalt endnu. Du bliver medlem, så snart kontingentet er betalt.</span>
            <RouterLink v-if="signupInvoice" :to="`/betal/${signupInvoice}`" class="btn small primary">Betal nu</RouterLink>
        </div>

        <div class="tabs" role="tablist">
            <button v-for="t in tabs" :key="t.id" :class="{ on: tab === t.id }" @click="tab = t.id">{{ t.label }}</button>
        </div>

        <div class="row between" style="margin-bottom:12px;align-items:flex-start">
            <div class="chips">
                <button v-for="c in cats" :key="c.id" :class="['chip', { on: c.show }]" @click="c.show = !c.show; dirty = true">
                    <span class="sw" :style="{ background: c.color }"></span>{{ c.name }}
                </button>
            </div>
            <button v-if="dirty" class="btn ghost small" @click="saveFilter">Gem som standard</button>
        </div>

        <div v-if="loading" class="spinner"></div>
        <div v-else-if="!list.length" class="card empty">{{ emptyText }}</div>
        <template v-else>
            <template v-for="g in grouped" :key="g.month">
                <h3 style="margin:18px 4px 8px">{{ g.month }}</h3>
                <div class="card" style="padding:0">
                    <RouterLink v-for="a in g.items" :key="a.id" :to="`/aktiviteter/${a.id}`" :class="['act', { cancelled: a.cancelled }]">
                        <div class="when"><div class="d">{{ dagNr(a.starts_at) }}</div><div class="m">{{ ugedagKort(a.starts_at) }}</div></div>
                        <div style="flex:1;min-width:0">
                            <div class="t">{{ a.title }}</div>
                            <div class="meta">kl. {{ klok(a.starts_at) }}–{{ klok(a.ends_at) }}<span v-if="a.location"> · {{ a.location }}</span><span v-if="a.trainers.length"> · {{ a.trainers.map(t => t.name.split(' ')[0]).join(', ') }}</span></div>
                            <div class="row" style="gap:6px">
                                <CatTags :cats="a.categories" />
                                <span v-if="a.price_fmt" class="tag">{{ a.price_fmt }}</span>
                                <span v-if="a.cancelled" class="tag bad">Aflyst</span>
                                <span v-else-if="store.user.can.trainer && !a.trainers.length" class="tag warn">Mangler træner</span>
                                <span v-for="s in mine(a)" :key="s.member_id" :class="['tag', s.cls]">{{ s.text }}</span>
                            </div>
                            <div v-if="a.max_participants && tab === 'upcoming'" class="small muted" style="margin-top:6px">
                                {{ a.signed_up_count }} af {{ a.max_participants }} pladser
                                <div class="capacity"><i :style="{ width: Math.min(100, a.signed_up_count / a.max_participants * 100) + '%' }"></i></div>
                            </div>
                        </div>
                    </RouterLink>
                </div>
            </template>
        </template>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import Icon from '../components/Icon.vue';
import CatTags from '../components/CatTags.vue';
import { api, store, klok, toast, dagNr, ugedagKort, maanedAar } from '../api';

const tabs = [{ id: 'upcoming', label: 'Kommende' }, { id: 'mine', label: 'Tilmeldt' }, { id: 'attended', label: 'Deltaget' }];
const tab = ref(sessionStorage.getItem('actTab') || 'upcoming');
const cats = ref([]); const list = ref([]); const loading = ref(true); const dirty = ref(false);
const household = ref({}); const signupInvoice = ref(null);

const emptyText = computed(() => ({ upcoming: 'Ingen kommende aktiviteter i de valgte kategorier.', mine: 'Du er ikke tilmeldt nogen kommende aktiviteter.', attended: 'Ingen registreret deltagelse endnu.' })[tab.value]);

const grouped = computed(() => {
    const out = [];
    for (const a of list.value) {
        const m = maanedAar(a.starts_at);
        let g = out.find(x => x.month === m);
        if (!g) out.push(g = { month: m, items: [] });
        g.items.push(a);
    }
    return out;
});

function mine(a) {
    return (a.my_status || []).filter(s => s.status !== 'cancelled').map(s => {
        const who = household.value[s.member_id];
        const name = Object.keys(household.value).length > 1 ? (who || '') + ' ' : '';
        if (tab.value === 'attended') return s.attended ? { member_id: s.member_id, text: name + 'deltog', cls: 'ok' } : null;
        if (s.status === 'pending_payment') return { member_id: s.member_id, text: name + 'mangler betaling', cls: 'warn' };
        return { member_id: s.member_id, text: (name || '') + 'tilmeldt', cls: 'ok' };
    }).filter(Boolean);
}

async function load() {
    loading.value = true;
    sessionStorage.setItem('actTab', tab.value);
    const shown = cats.value.filter(c => c.show).map(c => c.id);
    const q = shown.length && shown.length < cats.value.length ? `&categories=${shown.join(',')}` : '';
    list.value = await api(`activities?tab=${tab.value}${q}`);
    loading.value = false;
}

async function saveFilter() {
    await api('profile/categories', { method: 'PUT', body: { items: cats.value } });
    dirty.value = false; toast('Filteret er gemt som standard');
}

onMounted(async () => {
    const [c, p] = await Promise.all([api('profile/categories'), api('profile')]);
    cats.value = c;
    signupInvoice.value = p.signup_invoice_id;
    household.value = Object.fromEntries(p.household.map(m => [m.id, m.name.split(' ')[0]]));
    await load();
});
watch(tab, load);
watch(() => cats.value.map(c => c.show).join(), (n, o) => { if (o !== undefined && o !== '') load(); });
</script>
