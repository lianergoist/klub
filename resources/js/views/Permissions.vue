<template>
    <div>
        <div class="topbar">
            <h1>Rettigheder</h1>
            <RouterLink to="/indstillinger" class="btn small ghost">← Indstillinger</RouterLink>
        </div>
        <p class="muted" style="margin-top:0">Her vælger du, hvilke roller der giver adgang til hver handling. Tryk på en rolle for at slå den til eller fra – det gælder med det samme for alle med rollen.
            Skal ét medlem kunne noget uden at få hele rollen, så giv en <strong>personlig rettighed</strong> på medlemmets side (Medlemmer → medlem → Personlige rettigheder).
            Almindelige medlemmers adgang (tilmelding, betaling, profil) følger af medlemskabet og styres ikke her.</p>
        <div v-if="!data" class="spinner"></div>
        <template v-else>
            <div v-for="(list, g) in groups" :key="g" class="card">
                <h2>{{ g }}</h2>
                <ul class="list">
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
                        <p v-if="!p.roles.length && !p.users.length" class="small" style="color:var(--warn);margin:0">Ingen har denne rettighed lige nu.</p>
                        <p v-if="p.users.length" class="small" style="margin:0">Også personligt:
                            <template v-for="(u, i) in p.users" :key="u.id"><span v-if="i">, </span><RouterLink :to="`/medlemmer/${u.user_id}`">{{ u.name }}</RouterLink><span class="muted" v-if="u.ends_on"> (til {{ dato(u.ends_on) }})</span><span class="muted" v-else-if="u.starts_on > today"> (fra {{ dato(u.starts_on) }})</span></template>
                        </p>
                    </li>
                </ul>
            </div>
            <p class="small muted">Alle ændringer skrives i ændringsloggen.</p>
        </template>
    </div>
</template>
<script setup>
import { ref, computed, onMounted } from 'vue';
import { api, dato, toast, refreshMe } from '../api';

const data = ref(null); const busy = ref(null);
const today = new Date().toISOString().slice(0, 10);
const groups = computed(() => data.value.permissions.reduce((g, p) => ((g[p.group] ||= []).push(p), g), {}));
const same = (a, b) => a.length === b.length && a.every(r => b.includes(r));
const changed = (p) => !same(p.roles, p.default);

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
    if (!roles.length && !p.users.length && !confirm(`Fjerner du ${data.value.roles[r]}, har ingen længere rettigheden "${p.label}". Fortsæt?`)) return;
    save(p, roles);
}
const reset = (p) => save(p, p.default);

onMounted(async () => { data.value = await api('settings/permissions'); });
</script>
