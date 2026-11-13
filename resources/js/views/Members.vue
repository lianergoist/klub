<template>
    <div>
        <div class="topbar">
            <h1>Medlemmer</h1>
            <button class="btn small" @click="download('members/export?' + qs, 'medlemsliste.csv')"><Icon name="download" style="width:16px" /> Eksportér</button>
        </div>
        <div v-if="pending.length" class="banner warn">
            <strong>{{ pending.length }} har meldt sig ind, men ikke betalt:</strong>
            <RouterLink v-for="m in pending" :key="m.id" :to="`/medlemmer/${m.id}`" style="margin-left:8px">{{ m.name }}</RouterLink>
        </div>
        <div class="card flat" style="padding:12px">
            <div class="row mobile-only" style="flex-wrap:nowrap;gap:8px">
                <input v-model="f.q" placeholder="Søg navn, e-mail, telefon" @input="debounced">
                <button type="button" class="btn small" :class="{ on: showFilters }" @click="showFilters = !showFilters">Filtre<template v-if="activeFilters"> ({{ activeFilters }})</template></button>
            </div>
            <template v-if="!isMobile || showFilters">
            <div class="grid c4" style="gap:8px" :style="isMobile ? 'margin-top:8px' : ''">
                <input v-if="!isMobile" v-model="f.q" placeholder="Søg navn, e-mail, telefon" @input="debounced">
                <select v-model="f.status"><option value="">Alle statusser</option><option value="active">Aktive</option><option value="passive">Passive</option><option value="pending">Afventer</option><option value="resigned">Udmeldte</option><option value="guest">Gæster (ikke medlemmer)</option></select>
                <select v-model="f.age"><option value="">Alle aldre</option><option value="adult">Voksne (18+)</option><option value="youth">Børn og unge</option></select>
                <select v-model="f.role"><option value="">Alle roller</option><option value="trainer">Trænere</option><option value="board">Bestyrelse</option></select>
            </div>
            <label class="check small" style="margin-top:8px"><input type="checkbox" v-model="f.unpaid"> Kun med ubetalt kontingent {{ store.user.season }}</label>
            </template>
        </div>
        <p class="small muted">{{ list.length }} medlemmer</p>
        <!-- Mobil: kort med navn, status og kontingent -->
        <div class="card mobile-only" style="padding:0 14px">
            <ul class="mlist">
                <li v-for="m in list.slice(0, shown)" :key="m.id" class="click" @click="$router.push(`/medlemmer/${m.id}`)">
                    <div class="l1"><strong>{{ m.name }}</strong><span class="small muted">{{ m.age }} år</span></div>
                    <div class="l2">
                        <span class="ell">{{ m.type || (m.is_payer ? '' : m.household) || '–' }}<template v-if="m.roles.length"> · {{ m.roles.map(r => roleLabel[r]).join(', ') }}</template></span>
                        <span class="row" style="gap:4px;flex-wrap:nowrap">
                            <span v-if="m.status !== 'active'" :class="['tag', { pending: 'warn', resigned: 'bad' }[m.status]]">{{ m.status_label }}</span>
                            <span v-if="['active', 'passive'].includes(m.status)" :class="['tag', m.paid ? 'ok' : 'bad']">{{ m.paid ? 'Betalt' : 'Mangler' }}</span>
                        </span>
                    </div>
                </li>
            </ul>
            <button v-if="list.length > shown" class="btn block" style="margin:6px 0 12px" @click.stop="shown += 50">Vis flere ({{ list.length - shown }} tilbage)</button>
        </div>
        <div class="card desktop-only" style="padding:0 16px">
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Navn</th><th>Alder</th><th>Type</th><th>Status</th><th>Kontingent</th><th>Roller</th></tr></thead>
                    <tbody>
                        <tr v-for="m in list" :key="m.id" class="click" @click="$router.push(`/medlemmer/${m.id}`)">
                            <td><strong>{{ m.name }}</strong><div class="small muted">{{ m.email || (m.is_payer ? '' : m.household) }}</div></td>
                            <td>{{ m.age }}</td>
                            <td class="small">{{ m.type || '–' }}</td>
                            <td><span :class="['tag', { active: 'ok', pending: 'warn', resigned: 'bad' }[m.status]]">{{ m.status_label }}</span></td>
                            <td><span v-if="['active', 'passive'].includes(m.status)" :class="['tag', m.paid ? 'ok' : 'bad']">{{ m.paid ? 'Betalt' : 'Mangler' }}</span></td>
                            <td class="small">{{ m.roles.map(r => roleLabel[r]).join(', ') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue';
import Icon from '../components/Icon.vue';
import { api, download, roleLabel } from '../api';
import { store } from '../api';
import { isMobile } from '../mobile';
const showFilters = ref(false); const shown = ref(50);
const f = reactive({ q: '', status: '', age: '', role: '', unpaid: false });
const list = ref([]); const pending = ref([]);
const activeFilters = computed(() => [f.status, f.age, f.role, f.unpaid].filter(Boolean).length);
const qs = computed(() => new URLSearchParams(Object.fromEntries(Object.entries(f).filter(([, v]) => v).map(([k, v]) => [k, v === true ? 1 : v]))).toString());
async function load() { shown.value = 50; list.value = await api('members?' + qs.value); }
let t; const debounced = () => { clearTimeout(t); t = setTimeout(load, 250); };
watch(() => [f.status, f.age, f.role, f.unpaid], load);
onMounted(async () => { await load(); pending.value = await api('members?status=pending'); });
</script>
<style scoped>
.mlist .click { cursor: pointer; }
.ell { min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.btn.on { border-color: var(--accent); color: var(--accent); }
</style>
