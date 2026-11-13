<template>
    <div>
        <h1 style="margin-bottom:14px">Indstillinger</h1>
        <div v-if="can.roles_manage || can.audit_view" class="card links">
            <ul class="list">
                <li v-if="can.roles_manage"><RouterLink to="/indstillinger/rettigheder" class="mrow">
                    <Icon name="users" class="mi" /><span class="mtext"><strong>Rettigheder</strong><span class="small muted">Hvilke roller der må hvad, og personlige rettigheder</span></span><span class="chev">›</span>
                </RouterLink></li>
                <li v-if="can.audit_view"><RouterLink to="/indstillinger/aendringslog" class="mrow">
                    <Icon name="clock" class="mi" /><span class="mtext"><strong>Ændringslog</strong><span class="small muted">Alle handlinger i systemet med hvem og hvornår</span></span><span class="chev">›</span>
                </RouterLink></li>
            </ul>
        </div>
        <div v-if="can.categories_manage" class="card">
            <h2>Kategorier</h2>
            <p class="small muted">Medlemmer filtrerer og vælger notifikationer efter kategorierne. Tryk på en kategori for at rette navn og farve eller arkivere den.</p>
            <ul class="list cats">
                <li v-for="c in active" :key="c.id"><button type="button" class="catrow" @click="openCat(c)">
                    <span class="sw" :style="{ background: c.color }"></span>
                    <span class="mtext"><strong>{{ c.name }}</strong><span class="small muted">{{ c.followers }} følger · brugt på {{ c.activities }} aktiviteter</span></span>
                    <span class="chev">›</span>
                </button></li>
            </ul>
            <details v-if="archived.length" style="margin-top:10px">
                <summary class="small muted" style="cursor:pointer">Arkiverede ({{ archived.length }})</summary>
                <p class="small muted" style="margin:6px 0">Kan ikke vælges til nye aktiviteter eller af medlemmer. Tidligere aktiviteter beholder dem.</p>
                <ul class="list">
                    <li v-for="c in archived" :key="c.id" class="row between">
                        <span><span class="sw" :style="{ background: c.color, display: 'inline-block', width: '10px', height: '10px', borderRadius: '50%', marginRight: '6px' }"></span>{{ c.name }} <span class="small muted">· {{ c.activities }} aktiviteter</span></span>
                        <button class="btn small" @click="archive(c, false)">Gendan</button>
                    </li>
                </ul>
            </details>
            <form class="row" style="margin-top:12px;flex-wrap:nowrap" @submit.prevent="add">
                <input type="color" v-model="nc.color" style="width:44px;padding:2px">
                <input v-model="nc.name" placeholder="Ny kategori, fx Surfski" required>
                <button class="btn primary small">Tilføj</button>
            </form>
        </div>
        <Modal v-if="cat" :title="cat.orig" @close="cat = null">
            <div class="stack">
                <div class="row" style="flex-wrap:nowrap">
                    <label class="field" style="flex:0 0 auto"><span>Farve</span><input type="color" v-model="cat.color" style="width:56px;padding:2px"></label>
                    <label class="field" style="flex:1"><span>Navn</span><input v-model="cat.name"></label>
                </div>
                <p class="small muted" style="margin:0">Ret kun navnet ved små rettelser: det nye navn gælder også for de {{ cat.followers }}, der følger kategorien, og for {{ cat.activities }} tidligere aktiviteter. Er det en ny slags aktivitet, så opret en ny kategori og arkivér evt. den gamle.</p>
                <button class="btn primary block" @click="save(cat)">Gem</button>
                <button class="btn ghost block" @click="archive(cat, true)">Arkivér kategorien</button>
            </div>
        </Modal>
        <PackagesAdmin v-if="priceAccess" />
        <div v-if="priceAccess && pr?.groups?.length" class="card">
            <h2>Øvrige priser</h2>
            <p class="small muted">Priser for medlemskaber og tilkøb ligger på pakkerne ovenfor. En pris rettes ikke – man lægger en ny ind med den dato, den skal gælde fra. Den gamle bevares, og regninger, der allerede er sendt, ændres ikke.<span v-if="pr && !pr.can_edit"> Du kan se priserne, men ikke ændre dem.</span></p>
            <div v-if="!pr" class="spinner"></div>
            <template v-else v-for="g in pr.groups" :key="g.group">
                <h3 style="margin-top:18px">{{ g.group }}</h3>
                <ul class="list prices">
                    <li v-for="p in g.items" :key="p.code">
                        <div class="row between" style="flex-wrap:nowrap;align-items:flex-start">
                            <div style="min-width:0">
                                <div>{{ p.label }}</div>
                                <div class="small muted">Gælder fra {{ p.current ? dato(p.current.valid_from) : '–' }}<span v-if="p.current?.by"> · sat af {{ p.current.by }}</span>
                                    <a v-if="p.previous.length" href="#" @click.prevent="p.showHist = !p.showHist"> · {{ p.showHist ? 'skjul' : 'vis' }} tidligere ({{ p.previous.length }})</a></div>
                            </div>
                            <div class="row" style="gap:6px;flex-wrap:nowrap">
                                <strong class="num">{{ p.current?.amount_fmt || '–' }}</strong>
                                <button v-if="pr.can_edit" class="btn small" @click="openEdit(p)">Ny pris</button>
                            </div>
                        </div>
                        <div v-for="u in p.upcoming" :key="u.id" class="upcoming">
                            <span class="tag info">Ny pris</span> <strong>{{ u.amount_fmt }}</strong> fra {{ dato(u.valid_from) }}<span v-if="u.by" class="muted"> · sat af {{ u.by }}</span>
                            <button v-if="pr.can_edit" class="btn ghost small danger" @click="remove(u)">Fortryd</button>
                        </div>
                        <ul v-if="p.showHist" class="timeline small" style="margin-top:6px">
                            <li v-for="h in p.previous" :key="h.id">{{ h.amount_fmt }} fra {{ dato(h.valid_from) }}<span v-if="h.by"> (sat af {{ h.by }})</span></li>
                        </ul>
                    </li>
                </ul>
            </template>
        </div>

        <Modal v-if="edit" :title="edit.label" @close="edit = null">
            <form class="stack" @submit.prevent="savePrice">
                <p class="small muted" style="margin:0">Nuværende pris: <strong>{{ edit.current?.amount_fmt || '–' }}</strong></p>
                <div class="grid c2">
                    <label class="field"><span>Ny pris (kr.)</span><input v-model="nf.amount" type="number" min="0" step="0.01" required autofocus></label>
                    <label class="field"><span>Gælder fra</span><input v-model="nf.valid_from" type="date" :min="pr.today" required></label>
                </div>
                <div class="row" style="gap:6px">
                    <button type="button" class="chip" @click="nf.valid_from = pr.today">I dag</button>
                    <button type="button" class="chip" @click="nf.valid_from = nextSeason">Næste sæson ({{ dato(nextSeason) }})</button>
                    <button type="button" class="chip" @click="nf.valid_from = nextYear">1. januar</button>
                </div>
                <p class="small muted" style="margin:0">Regninger oprettet før denne dato beholder den gamle pris. Ændringen bliver skrevet i ændringsloggen.</p>
                <p v-if="error" class="banner bad" style="margin-bottom:0">{{ error }}</p>
                <button class="btn primary block">Gem ny pris</button>
            </form>
        </Modal>
    </div>
</template>
<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import Modal from '../components/Modal.vue';
import Icon from '../components/Icon.vue';
import PackagesAdmin from '../components/PackagesAdmin.vue';
import { api, toast, dato, store } from '../api';
const can = computed(() => store.user.can);
const priceAccess = computed(() => can.value.packages_manage || can.value.prices_edit);
const cats = ref([]); const pr = ref(null); const edit = ref(null); const error = ref('');
const nf = reactive({ amount: '', valid_from: '' });
const nextSeason = (() => { const d = new Date(); const y = d.getMonth() >= 4 ? d.getFullYear() + 1 : d.getFullYear(); return `${y}-05-01`; })();
const nextYear = `${new Date().getFullYear() + 1}-01-01`; const nc = reactive({ name: '', color: '#0f7c8c' });
const active = computed(() => cats.value.filter(c => !c.archived));
// Rettes på en kopi, så listen først ændres, når der gemmes
const cat = ref(null); const openCat = (c) => { cat.value = { ...c }; };
const archived = computed(() => cats.value.filter(c => c.archived));
async function loadCats() { cats.value = (await api('categories')).map(c => ({ ...c, orig: c.name })); }
onMounted(async () => { await loadCats(); if (priceAccess.value) pr.value = await api('finance/prices'); });
async function save(c) {
    if (c.name !== c.orig && !confirm(`Omdøb "${c.orig}" til "${c.name}"?\n\nNavnet ændres for alle ${c.followers}, der følger kategorien, og på ${c.activities} tidligere aktiviteter. Er det en ny slags aktivitet, så opret en ny kategori i stedet.`)) return;
    try { await api(`categories/${c.id}`, { method: 'PUT', body: { name: c.name, color: c.color } }); cat.value = null; await loadCats(); toast('Gemt'); } catch (e) { toast(e.message, 'error'); }
}
async function archive(c, on) {
    if (on && !confirm(`Arkivér "${c.name}"?\n\nDen kan ikke længere vælges til nye aktiviteter eller af medlemmer. Tidligere aktiviteter beholder den, og den kan gendannes.`)) return;
    try { await api(`categories/${c.id}`, { method: 'PUT', body: { name: c.orig, color: c.color, archived: on } }); cat.value = null; await loadCats(); toast(on ? 'Arkiveret' : 'Gendannet'); } catch (e) { toast(e.message, 'error'); }
}
function openEdit(p) { edit.value = p; error.value = ''; nf.amount = p.current ? p.current.amount / 100 : ''; nf.valid_from = nextSeason; }
async function savePrice() {
    error.value = '';
    try { pr.value = await api('finance/prices', { method: 'POST', body: { code: edit.value.code, ...nf } }); toast(`Ny pris gemt – gælder fra ${dato(nf.valid_from)}`); edit.value = null; }
    catch (e) { error.value = e.message; }
}
async function remove(u) {
    try { pr.value = await api(`finance/prices/${u.id}`, { method: 'DELETE' }); toast('Den kommende pris er fjernet'); }
    catch (e) { toast(e.message, 'error'); }
}
async function add() { try { cats.value.push({ ...(await api('categories', { method: 'POST', body: nc })), orig: nc.name }); nc.name = ''; toast('Tilføjet'); } catch (e) { toast(e.message, 'error'); } }
</script>
<style scoped>
.prices > li { padding: 12px 0; }
.links { padding: 2px 16px; }
.links .list > li, .cats > li { padding: 0; }
.mrow, .catrow { display: flex; align-items: center; gap: 12px; padding: 12px 0; color: var(--ink); min-height: 56px; width: 100%; background: none; border: 0; font: inherit; text-align: left; cursor: pointer; }
.mi { width: 22px; height: 22px; color: var(--accent); flex-shrink: 0; }
.catrow .sw { width: 22px; height: 22px; border-radius: 6px; flex-shrink: 0; }
.mtext { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.chev { color: var(--ink-3); font-size: 1.4rem; line-height: 1; }
.upcoming { margin-top: 6px; font-size: .88rem; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
</style>
