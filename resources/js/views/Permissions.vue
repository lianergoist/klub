<template>
    <div>
        <div class="topbar">
            <h1>Rettigheder</h1>
            <RouterLink to="/indstillinger" class="btn small ghost">← Indstillinger</RouterLink>
        </div>
        <div class="muted" style="margin:0 0 14px">Vælg, hvilke roller der må hvad. Tryk på en rolle for at slå den til eller fra – det gælder med det samme.
            <details class="more-info"><summary class="small">Mere om rettigheder</summary>
                <p class="small" style="margin:6px 0 0">Skal ét medlem kunne noget uden at få hele rollen, så giv en <strong>personlig rettighed</strong> på medlemmets side (Medlemmer → medlem → Personlige rettigheder). Almindelige medlemmers adgang (tilmelding, betaling, profil) følger af medlemskabet og styres ikke her – undtagen hvor der står "Almindelige medlemmer" (fx booke udstyr).</p>
            </details></div>
        <div v-if="!data" class="spinner"></div>
        <template v-else>
            <!-- Grupperne kan foldes sammen; på mobil starter de lukkede -->
            <details v-for="(list, g) in groups" :key="g" class="card grp" :open="!isMobile">
                <summary><h2 style="margin:0;flex:1">{{ g }}</h2><span class="small muted">{{ list.length }} {{ list.length === 1 ? 'rettighed' : 'rettigheder' }}<template v-if="list.some(changed)"> · ændret</template></span></summary>
                <ul class="list" style="margin-top:10px">
                    <li v-for="p in list" :key="p.key" class="stack" style="gap:6px">
                        <div class="row between" style="align-items:flex-start;flex-wrap:nowrap">
                            <div>
                                <strong>{{ p.label }}</strong>
                                <span v-if="p.locked" class="tag" style="margin-left:6px">Låst</span>
                                <span v-else-if="changed(p)" class="tag info" style="margin-left:6px">Ændret</span>
                                <div class="small muted">{{ p.description }}</div>
                            </div>
                            <button v-if="!p.locked && changed(p)" class="btn small ghost" style="flex:none" @click="reset(p)">Standard</button>
                        </div>
                        <div class="chips">
                            <button v-for="(label, r) in data.roles" :key="r" :class="['chip', { on: p.roles.includes(r) }]"
                                :disabled="p.locked || busy === p.key" @click="toggle(p, r)">{{ p.roles.includes(r) ? '✓ ' : '' }}{{ label }}</button>
                        </div>
                        <div v-if="p.members" class="stack" style="gap:4px">
                            <span class="small"><strong>Almindelige medlemmer</strong></span>
                            <div class="chips">
                                <button v-for="(label, v) in p.members.options" :key="v" :class="['chip', { on: p.members.value === v }]"
                                    :disabled="busy === p.key" @click="setMembers(p, v)">{{ p.members.value === v ? '✓ ' : '' }}{{ label }}</button>
                            </div>
                            <p v-if="p.members.value === 'none'" class="small muted" style="margin:0">Medlemmer ser ikke Udstyr i menuen – heller ikke dem, der har købt udstyrsleje.</p>
                            <p v-else class="small muted" style="margin:0">Medlemmer, der må booke, ser Udstyr i menuen.</p>
                        </div>
                        <p v-if="!p.roles.length && !p.users.length && (!p.members || p.members.value === 'none')" class="small" style="color:var(--warn);margin:0">Ingen har denne rettighed lige nu.</p>
                        <p v-if="p.users.length" class="small" style="margin:0">Også personligt:
                            <template v-for="(u, i) in p.users" :key="u.id"><span v-if="i">, </span><RouterLink :to="`/medlemmer/${u.user_id}`">{{ u.name }}</RouterLink><span class="muted" v-if="u.ends_on"> (til {{ dato(u.ends_on) }})</span><span class="muted" v-else-if="u.starts_on > today"> (fra {{ dato(u.starts_on) }})</span></template>
                        </p>
                    </li>
                </ul>
            </details>
            <p class="small muted">Alle ændringer skrives i ændringsloggen.</p>
        </template>
    </div>
</template>
<script setup>
import { ref, computed, onMounted } from 'vue';
import { api, dato, toast, refreshMe } from '../api';
import { isMobile } from '../mobile';

const data = ref(null); const busy = ref(null);
const today = new Date().toISOString().slice(0, 10);
const groups = computed(() => data.value.permissions.reduce((g, p) => ((g[p.group] ||= []).push(p), g), {}));
const same = (a, b) => a.length === b.length && a.every(r => b.includes(r));
const changed = (p) => !same(p.roles, p.default) || (p.members && p.members.value !== p.members.default);

async function save(p, roles) {
    busy.value = p.key;
    try {
        p.roles = (await api(`settings/permissions/${p.key}`, { method: 'PUT', body: { roles } })).roles;
        toast('Gemt');
        refreshMe(); // egne rettigheder kan være ændret
    } catch (e) { toast(e.message, 'error'); }
    busy.value = null;
}
function toggle(p, r) {
    const roles = p.roles.includes(r) ? p.roles.filter(x => x !== r) : [...p.roles, r];
    if (!roles.length && !p.users.length && (!p.members || p.members.value === 'none') && !confirm(`Fjerner du ${data.value.roles[r]}, har ingen længere rettigheden "${p.label}". Fortsæt?`)) return;
    save(p, roles);
}
async function setMembers(p, v) {
    if (p.members.value === v) return;
    busy.value = p.key;
    try {
        p.members.value = (await api(`settings/permissions/${p.key}/members`, { method: 'PUT', body: { members: v } })).members;
        toast('Gemt');
        refreshMe();
    } catch (e) { toast(e.message, 'error'); }
    busy.value = null;
}
async function reset(p) {
    if (!same(p.roles, p.default)) await save(p, p.default);
    if (p.members && p.members.value !== p.members.default) await setMembers(p, p.members.default);
}

onMounted(async () => { data.value = await api('settings/permissions'); });
</script>
<style scoped>
.grp > summary { list-style: none; cursor: pointer; display: flex; justify-content: space-between; align-items: center; gap: 10px; }
.grp > summary::-webkit-details-marker { display: none; }
.grp > summary::after { content: '▾'; color: var(--ink-3); }
.grp[open] > summary::after { content: '▴'; }
.more-info summary { cursor: pointer; color: var(--accent); }
</style>
