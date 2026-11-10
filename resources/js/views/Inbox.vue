<template>
    <div>
        <div class="topbar">
            <h1>Indbakke</h1>
            <button v-if="tab === 'beskeder' && store.user.can_message" class="btn primary small" @click="showNew = true"><Icon name="plus" style="width:16px" /> Ny besked</button>
        </div>
        <div class="tabs">
            <RouterLink to="/beskeder" :class="{ on: tab === 'beskeder' }">Beskeder<span v-if="store.user.unread_messages" class="tag bad" style="margin-left:6px">{{ store.user.unread_messages }}</span></RouterLink>
            <RouterLink to="/indbakke" :class="{ on: tab === 'notifikationer' }">Notifikationer<span v-if="store.user.unread && tab !== 'notifikationer'" class="tag bad" style="margin-left:6px">{{ store.user.unread }}</span></RouterLink>
        </div>

        <template v-if="tab === 'beskeder'">
            <div v-if="!store.user.can_message" class="card empty">Beskeder er for voksne medlemmer med eget login.</div>
            <div v-else-if="convs === null" class="spinner"></div>
            <div v-else-if="!convs.length" class="card empty">Ingen beskeder endnu.<br><button class="btn small" style="margin-top:10px" @click="showNew = true">Skriv til et medlem</button></div>
            <div v-else class="card" style="padding:0">
                <RouterLink v-for="c in convs" :key="c.id" :to="`/beskeder/${c.id}`" class="conv">
                    <span class="avatar">{{ initials(c.with) }}</span>
                    <div style="flex:1;min-width:0">
                        <div class="row between" style="flex-wrap:nowrap"><strong :style="c.unread ? '' : 'font-weight:600'">{{ c.with }}</strong><span class="small muted" style="white-space:nowrap">{{ when(c.at) }}</span></div>
                        <div class="row between" style="flex-wrap:nowrap">
                            <span :class="['small', 'snip', { unread: c.unread }]">{{ c.last_mine ? 'Dig: ' : '' }}{{ c.last }}</span>
                            <span v-if="c.unread" class="badge">{{ c.unread }}</span>
                            <span v-else-if="c.blocked" class="tag">Blokeret</span>
                        </div>
                    </div>
                </RouterLink>
            </div>
            <p class="small muted">Beskeder er private mellem jer to. Du kan slå beskeder fra under Profil → Beskeder og blokere enkelte medlemmer.</p>
        </template>

        <template v-else>
            <div v-if="list === null" class="spinner"></div>
            <div v-else-if="!list.length" class="card empty">Ingen notifikationer.</div>
            <div v-else class="card" style="padding:4px 16px">
                <ul class="list">
                    <li v-for="n in list" :key="n.id">
                        <component :is="n.link ? 'RouterLink' : 'div'" :to="n.link" style="color:inherit;display:block">
                            <div class="row between"><strong>{{ n.title }}</strong><span v-if="!n.read" class="tag info">Ny</span></div>
                            <div class="small" style="color:var(--ink-2)">{{ n.body }}</div>
                            <div class="small muted">{{ tidspunkt(n.created_at) }}</div>
                        </component>
                    </li>
                </ul>
            </div>
        </template>

        <Modal v-if="showNew" title="Ny besked" @close="showNew = false">
            <input v-model="q" placeholder="Søg efter et medlem …" autofocus @input="search">
            <ul class="list" style="margin-top:8px">
                <li v-for="p in people" :key="p.id"><button class="btn ghost block" style="justify-content:flex-start" @click="start(p.id)"><span class="avatar sm">{{ initials(p.name) }}</span> {{ p.name }}</button></li>
                <li v-if="q.length >= 2 && !people.length" class="muted small">Ingen medlemmer fundet, eller de modtager ikke beskeder.</li>
            </ul>
            <p class="small muted">Kun voksne medlemmer, der har beskeder slået til, kan findes.</p>
        </Modal>
    </div>
</template>
<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Icon from '../components/Icon.vue';
import Modal from '../components/Modal.vue';
import { api, store, tidspunkt, toast } from '../api';
const route = useRoute(); const router = useRouter();
const tab = computed(() => route.path.startsWith('/beskeder') ? 'beskeder' : 'notifikationer');
const list = ref(null); const convs = ref(null);
const showNew = ref(false); const q = ref(''); const people = ref([]);
const initials = (n) => n.split(' ').map(x => x[0]).slice(0, 2).join('').toUpperCase();
const when = (iso) => { const d = new Date(iso); return d.toDateString() === new Date().toDateString() ? d.toLocaleTimeString('da-DK', { hour: '2-digit', minute: '2-digit' }) : d.toLocaleDateString('da-DK', { day: 'numeric', month: 'short' }); };
let t; function search() { clearTimeout(t); t = setTimeout(async () => { people.value = q.value.length >= 2 ? await api(`messages/people?q=${encodeURIComponent(q.value)}`) : []; }, 200); }
async function start(id) { try { const r = await api('messages/start', { method: 'POST', body: { user_id: id } }); router.push(`/beskeder/${r.id}`); } catch (e) { toast(e.message, 'error'); } }
async function load() {
    if (tab.value === 'beskeder') { if (store.user.can_message) convs.value = await api('messages'); }
    else { list.value = await api('notifications'); if (store.user) store.user.unread = 0; }
}
watch(tab, load); onMounted(load);
</script>
<style scoped>
.conv { display: flex; gap: 12px; align-items: center; padding: 12px 14px; color: inherit; }
.conv + .conv { border-top: 1px solid var(--line); }
.avatar { flex-shrink: 0; width: 42px; height: 42px; border-radius: 50%; display: grid; place-items: center; background: var(--accent-soft); color: var(--accent); font-weight: 700; font-size: .85rem; }
.avatar.sm { width: 28px; height: 28px; font-size: .7rem; margin-right: 8px; }
.snip { color: var(--ink-3); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.snip.unread { color: var(--ink); font-weight: 600; }
.badge { background: var(--accent); color: #fff; border-radius: 999px; font-size: .72rem; font-weight: 700; min-width: 20px; height: 20px; display: grid; place-items: center; padding: 0 6px; }
</style>
