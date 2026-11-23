<template>
    <div>
        <h1 style="margin-bottom:14px">System</h1>
        <div class="card links">
            <ul class="list">
                <li v-if="can.roles_manage"><RouterLink to="/system/rettigheder" class="mrow">
                    <Icon name="users" class="mi" /><span class="mtext"><strong>Rettigheder</strong><span class="small muted">Hvilke roller der må hvad, og personlige rettigheder</span></span><span class="chev">›</span>
                </RouterLink></li>
                <li v-if="can.design_manage"><RouterLink to="/system/design" class="mrow">
                    <Icon name="image" class="mi" /><span class="mtext"><strong>Design</strong><span class="small muted">Egne farvetemaer med farver, skrift og hjørner</span></span><span class="chev">›</span>
                </RouterLink></li>
                <li v-if="can.audit_view"><RouterLink to="/system/aendringslog" class="mrow">
                    <Icon name="clock" class="mi" /><span class="mtext"><strong>Ændringslog</strong><span class="small muted">Alle handlinger i systemet med hvem og hvornår</span></span><span class="chev">›</span>
                </RouterLink></li>
                <li v-if="can.categories_manage"><details class="fold">
                    <summary class="mrow">
                        <Icon name="list" class="mi" /><span class="mtext"><strong>Kategorier</strong><span class="small muted">Aktivitetskategorier, som medlemmer filtrerer og får besked efter</span></span><span class="chev">›</span>
                    </summary>
                    <div class="foldbody">
                    <p class="small muted">Medlemmer filtrerer og vælger notifikationer efter kategorierne. Tryk på en kategori for at rette navn og farve eller arkivere den. Om kategorier mærket <em>Kan ikke fravælges</em> får alle medlemmer besked.</p>
                    <ul class="list cats">
                        <li v-for="c in active" :key="c.id"><button type="button" class="catrow" @click="openCat(c)">
                            <span class="sw" :style="{ background: c.color }"></span>
                            <span class="mtext"><strong>{{ c.name }}<span v-if="c.mandatory" class="tag" style="margin-left:6px">Kan ikke fravælges</span></strong><span class="small muted">{{ c.followers }} følger · brugt på {{ c.activities }} aktiviteter</span></span>
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
                    <button class="btn small" style="margin-top:12px" @click="newCat"><Icon name="plus" style="width:16px" /> Ny kategori</button>
                    </div>
                </details></li>
            </ul>
        </div>
        <Modal v-if="cat" :title="cat.id ? cat.orig : 'Ny kategori'" @close="cat = null">
            <form class="stack" @submit.prevent="save(cat)">
                <label class="field"><span>Navn</span><input v-model="cat.name" placeholder="Fx Surfski" required maxlength="50"></label>
                <div><div class="group-label" style="margin-bottom:6px">Farve</div><PalettePick v-model="cat.color" label="Kategoriens farve" /></div>
                <div>
                    <label class="check"><input type="checkbox" v-model="cat.mandatory"> Kan ikke fravælges</label>
                    <p class="small muted" style="margin:4px 0 0 30px">Alle aktive medlemmer får besked om nye aktiviteter i kategorien. Medlemmerne ser den nedtonet og kan ikke slå den fra. Brug det til fx generalforsamling og arbejdsdage.</p>
                </div>
                <p v-if="cat.id" class="small muted" style="margin:0">Ret kun navnet ved små rettelser: det nye navn gælder også for de {{ cat.followers }}, der følger kategorien, og for {{ cat.activities }} tidligere aktiviteter. Er det en ny slags aktivitet, så opret en ny kategori og arkivér evt. den gamle.</p>
                <button class="btn primary block">{{ cat.id ? 'Gem' : 'Opret kategori' }}</button>
                <button v-if="cat.id" type="button" class="btn ghost block" @click="archive(cat, true)">Arkivér kategorien</button>
            </form>
        </Modal>
    </div>
</template>
<script setup>
import { ref, computed, onMounted } from 'vue';
import Modal from '../components/Modal.vue';
import Icon from '../components/Icon.vue';
import PalettePick from '../components/PalettePick.vue';
import { api, toast, store } from '../api';
const can = computed(() => store.user.can);
const cats = ref([]);
const active = computed(() => cats.value.filter(c => !c.archived));
// Rettes på en kopi, så listen først ændres, når der gemmes
const cat = ref(null); const openCat = (c) => { cat.value = { ...c }; };
const newCat = () => { cat.value = { name: '', color: '#2599be', mandatory: false }; };
const archived = computed(() => cats.value.filter(c => c.archived));
async function loadCats() { cats.value = (await api('categories')).map(c => ({ ...c, orig: c.name })); }
onMounted(() => { if (can.value.categories_manage) loadCats(); });
async function save(c) {
    if (!c.id) return add(c);
    if (c.name !== c.orig && !confirm(`Omdøb "${c.orig}" til "${c.name}"?\n\nNavnet ændres for alle ${c.followers}, der følger kategorien, og på ${c.activities} tidligere aktiviteter. Er det en ny slags aktivitet, så opret en ny kategori i stedet.`)) return;
    try { await api(`categories/${c.id}`, { method: 'PUT', body: { name: c.name, color: c.color, mandatory: c.mandatory } }); cat.value = null; await loadCats(); toast('Gemt'); } catch (e) { toast(e.message, 'error'); }
}
async function archive(c, on) {
    if (on && !confirm(`Arkivér "${c.name}"?\n\nDen kan ikke længere vælges til nye aktiviteter eller af medlemmer. Tidligere aktiviteter beholder den, og den kan gendannes.`)) return;
    try { await api(`categories/${c.id}`, { method: 'PUT', body: { name: c.orig, color: c.color, archived: on } }); cat.value = null; await loadCats(); toast(on ? 'Arkiveret' : 'Gendannet'); } catch (e) { toast(e.message, 'error'); }
}
async function add(c) { try { await api('categories', { method: 'POST', body: { name: c.name, color: c.color, mandatory: c.mandatory } }); cat.value = null; await loadCats(); toast('Oprettet'); } catch (e) { toast(e.message, 'error'); } }
</script>
<style scoped>
.links { padding: 2px 16px; }
.links .list > li, .cats > li { padding: 0; }
.mrow, .catrow { display: flex; align-items: center; gap: 12px; padding: 12px 0; color: var(--ink); min-height: 56px; width: 100%; background: none; border: 0; font: inherit; text-align: left; cursor: pointer; }
.mi { width: 22px; height: 22px; color: var(--accent); flex-shrink: 0; }
.catrow .sw { width: 22px; height: 22px; border-radius: 6px; flex-shrink: 0; }
.mtext { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.chev { color: var(--ink-3); font-size: 1.4rem; line-height: 1; transition: transform .15s; }
/* Kategorier foldes ud på stedet: samme række som de andre punkter, pilen drejer */
.fold > summary { list-style: none; }
.fold > summary::-webkit-details-marker { display: none; }
.fold[open] > summary .chev { transform: rotate(90deg); }
.foldbody { padding: 0 0 14px 34px; }
.foldbody > p:first-child { margin-top: 0; }
@media (max-width: 599.98px) { .foldbody { padding-left: 0; } }
</style>
