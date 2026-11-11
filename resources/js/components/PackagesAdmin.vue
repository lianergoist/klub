<template>
    <div class="card">
        <h2>Medlemskaber og tilkøb</h2>
        <p class="small muted">Alt, der kan købes, er en pakke: <strong>hvem</strong> den dækker (antal personer pr. kategori), <strong>hvor længe</strong> den gælder og <strong>hvad</strong> den koster. Ændringer gælder kun nye køb. Pakker slettes ikke, men arkiveres, så tidligere køb og regninger bevares.<span v-if="d && !d.can_edit"> Kun bestyrelse og admin kan oprette og ændre pakker.</span></p>
        <div class="chips" style="margin-bottom:12px">
            <button v-for="t in tabs" :key="t.id" :class="['chip', { on: tab === t.id }]" @click="tab = t.id">{{ t.label }}</button>
        </div>
        <div v-if="!d" class="spinner"></div>

        <template v-else-if="tab !== 'categories'">
            <ul class="list pk">
                <li v-for="p in list(false)" :key="p.id">
                    <div class="row between" style="flex-wrap:nowrap;align-items:flex-start;gap:10px">
                        <div style="min-width:0">
                            <strong>{{ p.name }}</strong> <span v-if="!p.on_sale" class="tag">Ikke til salg nu</span>
                            <div class="small muted">{{ p.composition_label }} · {{ p.period_label }}<template v-if="p.sale_label"> · {{ p.sale_label }}</template><template v-if="!p.gives_access"> · uden adgang til aktiviteter</template><template v-if="p.requires_membership"> · kræver medlemskab</template><template v-if="!p.self_service"> · tildeles af bestyrelsen</template><template v-if="p.renews_to_id"> · fornyes som {{ nameOf(p.renews_to_id) }}</template></div>
                            <div class="small muted">{{ p.active_purchases }} gældende køb<template v-if="p.current"> · pris fra {{ dato(p.current.valid_from) }}</template>
                                <a v-if="p.previous.length" href="#" @click.prevent="p.showHist = !p.showHist"> · {{ p.showHist ? 'skjul' : 'vis' }} tidligere priser ({{ p.previous.length }})</a></div>
                            <div v-for="u in p.upcoming" :key="u.id" class="upcoming small">
                                <span class="tag info">Ny pris</span> <strong>{{ u.amount_fmt }}</strong> fra {{ dato(u.valid_from) }}<span v-if="u.by" class="muted"> · sat af {{ u.by }}</span>
                                <button v-if="d.can_price" class="btn ghost small danger" @click="deletePrice(p, u)">Fortryd</button>
                            </div>
                            <ul v-if="p.showHist" class="timeline small" style="margin-top:6px"><li v-for="h in p.previous" :key="h.id">{{ h.amount_fmt }} fra {{ dato(h.valid_from) }}<span v-if="h.by"> (sat af {{ h.by }})</span></li></ul>
                        </div>
                        <strong class="num" style="white-space:nowrap">{{ p.current?.amount_fmt || '–' }}</strong>
                    </div>
                    <div class="row" style="gap:6px;margin-top:8px">
                        <button v-if="d.can_edit" class="btn small" @click="openEdit(p)">Rediger</button>
                        <button v-if="d.can_price" class="btn small" @click="openPrice(p)">Ny pris</button>
                        <button v-if="d.can_edit" class="btn small ghost" @click="archive(p, true)">Arkivér</button>
                    </div>
                </li>
                <li v-if="!list(false).length" class="muted">Ingen {{ tab === 'membership' ? 'medlemskaber' : 'tilkøb' }}.</li>
            </ul>
            <details v-if="list(true).length" style="margin-top:10px">
                <summary class="small muted" style="cursor:pointer">Arkiverede ({{ list(true).length }})</summary>
                <p class="small muted" style="margin:6px 0">Kan ikke købes, og fornyes ikke ved ny sæson. Tidligere køb og regninger bevares.</p>
                <ul class="list">
                    <li v-for="p in list(true)" :key="p.id" class="row between">
                        <span>{{ p.name }} <span class="small muted">· {{ p.purchases }} køb i alt</span></span>
                        <button v-if="d.can_edit" class="btn small" @click="archive(p, false)">Gendan</button>
                    </li>
                </ul>
            </details>
            <button v-if="d.can_edit" class="btn primary small" style="margin-top:12px" @click="openNew">+ {{ tab === 'membership' ? 'Nyt medlemskab' : 'Nyt tilkøb' }}</button>
        </template>

        <template v-else>
            <p class="small muted" style="margin-top:0">Kategorierne bruges til at beskrive, hvem en pakke dækker, fx "2 voksne og 1–3 børn". Alderen regnes på den dag, pakken begynder at gælde.</p>
            <ul class="list">
                <li v-for="c in d.categories" :key="c.id" :class="{ archived: c.archived }">
                    <div class="grid cat">
                        <label class="field"><span>Navn</span><input v-model="c.name" :disabled="!d.can_edit"></label>
                        <label class="field"><span>Flertal</span><input v-model="c.plural" :disabled="!d.can_edit"></label>
                        <label class="field"><span>Fra alder</span><input v-model.number="c.min_age" type="number" min="0" :disabled="!d.can_edit"></label>
                        <label class="field"><span>Til alder</span><input v-model.number="c.max_age" type="number" min="0" placeholder="ingen" :disabled="!d.can_edit"></label>
                    </div>
                    <div v-if="d.can_edit" class="row" style="gap:6px;margin-top:6px">
                        <button class="btn small" @click="saveCat(c)">Gem</button>
                        <button class="btn small ghost" @click="saveCat(c, !c.archived)">{{ c.archived ? 'Gendan' : 'Arkivér' }}</button>
                        <span v-if="c.archived" class="tag">Arkiveret</span>
                    </div>
                </li>
            </ul>
            <form v-if="d.can_edit" class="grid cat" style="margin-top:12px" @submit.prevent="saveCat(nc)">
                <label class="field"><span>Ny kategori</span><input v-model="nc.name" placeholder="Fx Ungdom" required></label>
                <label class="field"><span>Flertal</span><input v-model="nc.plural" placeholder="Fx Unge" required></label>
                <label class="field"><span>Fra alder</span><input v-model.number="nc.min_age" type="number" min="0" required></label>
                <label class="field"><span>Til alder</span><input v-model.number="nc.max_age" type="number" min="0" placeholder="ingen"></label>
                <button class="btn primary small" style="align-self:end">Tilføj</button>
            </form>
        </template>

        <!-- Opret/rediger pakke -->
        <Modal v-if="ed" :title="ed.id ? `Rediger: ${ed.name}` : (ed.kind === 'membership' ? 'Nyt medlemskab' : 'Nyt tilkøb')" @close="ed = null">
            <form class="stack" @submit.prevent="savePkg">
                <p v-if="ed.id && ed.purchases" class="banner info small" style="margin:0">{{ ed.purchases }} køb bruger pakken. Ændringer gælder kun nye køb – de eksisterende beholder deres personer og periode.</p>
                <label class="field"><span>Navn</span><input v-model="ed.name" required maxlength="120" :placeholder="ed.kind === 'membership' ? 'Fx Familie, 2 voksne og 1–3 børn' : 'Fx Adgang til motionsrum'"></label>
                <label class="field"><span>Beskrivelse (vises for medlemmerne)</span><textarea v-model="ed.description" maxlength="1000" rows="2"></textarea></label>

                <fieldset>
                    <legend>Hvor længe gælder den?</legend>
                    <div class="chips">
                        <button v-for="t in periodTypes" :key="t.id" type="button" :class="['chip', { on: ed.period_type === t.id }]" @click="ed.period_type = t.id">{{ t.label }}</button>
                    </div>
                    <label v-if="ed.period_type === 'days'" class="field" style="margin-top:8px"><span>Antal dage fra køb (1–365)</span><input v-model.number="ed.period_days" type="number" min="1" max="365" required></label>
                    <div v-if="ed.period_type === 'dates'" class="grid c2" style="margin-top:8px">
                        <div class="field"><span>Fra</span><div class="row dm"><select v-model="ed.fromD"><option v-for="n in 31" :key="n" :value="n">{{ n }}.</option></select><select v-model="ed.fromM"><option v-for="(m, i) in months" :key="i" :value="i + 1">{{ m }}</option></select></div></div>
                        <div class="field"><span>Til og med</span><div class="row dm"><select v-model="ed.toD"><option v-for="n in 31" :key="n" :value="n">{{ n }}.</option></select><select v-model="ed.toM"><option v-for="(m, i) in months" :key="i" :value="i + 1">{{ m }}</option></select></div></div>
                    </div>
                    <p class="small muted" style="margin:6px 0 0">{{ periodHelp }}</p>
                    <template v-if="ed.period_type !== 'once'">
                        <label class="check" style="margin-top:4px"><input type="checkbox" v-model="ed.hasSale"> Kun til salg i en del af året</label>
                        <div v-if="ed.hasSale" class="grid c2">
                            <div class="field"><span>Til salg fra</span><div class="row dm"><select v-model="ed.sFromD"><option v-for="n in 31" :key="n" :value="n">{{ n }}.</option></select><select v-model="ed.sFromM"><option v-for="(m, i) in months" :key="i" :value="i + 1">{{ m }}</option></select></div></div>
                            <div class="field"><span>Til og med</span><div class="row dm"><select v-model="ed.sToD"><option v-for="n in 31" :key="n" :value="n">{{ n }}.</option></select><select v-model="ed.sToM"><option v-for="(m, i) in months" :key="i" :value="i + 1">{{ m }}</option></select></div></div>
                        </div>
                        <p v-if="ed.hasSale" class="small muted" style="margin:0">Fx kan "hel sæson" sælges 1. maj – 31. jul. og "halv sæson" fra 1. aug., så nye medlemmer i efteråret kun får tilbudt halv sæson. Fornyelse ved ny sæson sker uanset salgsperioden.</p>
                    </template>
                </fieldset>

                <fieldset>
                    <legend>Hvem dækker den?</legend>
                    <div class="grid c2">
                        <label class="field"><span>Personer i alt, mindst</span><input v-model.number="ed.total_min" type="number" min="1" required></label>
                        <label class="field"><span>Personer i alt, højst</span><input v-model.number="ed.total_max" type="number" min="1" placeholder="ingen grænse"></label>
                    </div>
                    <table class="slots">
                        <thead><tr><th>Kategori</th><th>Med</th><th>Mindst</th><th>Højst</th></tr></thead>
                        <tbody>
                            <tr v-for="s in ed.slotRows" :key="s.person_category_id">
                                <td>{{ s.label }}</td>
                                <td><input type="checkbox" v-model="s.on"></td>
                                <td><input v-model.number="s.min" type="number" min="0" :disabled="!s.on"></td>
                                <td><input v-model.number="s.max" type="number" min="0" :disabled="!s.on"></td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="small" style="margin:6px 0 0"><span class="muted">Eksempel:</span> {{ preview }}</p>
                </fieldset>

                <fieldset>
                    <legend>Indstillinger</legend>
                    <label v-if="ed.kind === 'membership'" class="check"><input type="checkbox" v-model="ed.gives_access"> Giver adgang til aktiviteter <span class="small muted">(fra for fx støttemedlem)</span></label>
                    <label v-else class="check"><input type="checkbox" v-model="ed.requires_membership"> Kræver gyldigt medlemskab</label>
                    <label class="check"><input type="checkbox" v-model="ed.self_service"> Medlemmerne kan selv vælge den <span class="small muted">(ellers kun bestyrelsen)</span></label>
                    <label v-if="ed.kind === 'membership' && ed.period_type === 'dates'" class="field"><span>Ved ny sæson fornyes den som</span>
                        <select v-model="ed.renews_to_id"><option :value="null">Samme medlemskab</option><option v-for="x in d.packages.filter(x => x.kind === 'membership' && x.id !== ed.id && !x.archived && x.period_type === 'dates')" :key="x.id" :value="x.id">{{ x.name }}</option></select>
                    </label>
                    <label class="field"><span>Indtægten bogføres på</span>
                        <select v-model="ed.account_id" required><option v-for="a in d.accounts" :key="a.id" :value="a.id">{{ a.number }} {{ a.name }}</option></select>
                    </label>
                    <label v-if="!ed.id" class="field"><span>Pris (kr.)</span><input v-model="ed.price" type="number" min="0" step="0.01" required></label>
                    <p v-else class="small muted" style="margin:0">Prisen ændres med "Ny pris", så den gamle pris bevares.</p>
                </fieldset>
                <p v-if="error" class="banner bad" style="margin:0">{{ error }}</p>
                <button class="btn primary block" :disabled="busy">{{ ed.id ? 'Gem ændringer' : 'Opret' }}</button>
            </form>
        </Modal>

        <!-- Ny pris -->
        <Modal v-if="pr" :title="`Ny pris: ${pr.name}`" @close="pr = null">
            <form class="stack" @submit.prevent="savePrice">
                <p class="small muted" style="margin:0">Nuværende pris: <strong>{{ pr.current?.amount_fmt || '–' }}</strong></p>
                <div class="grid c2">
                    <label class="field"><span>Ny pris (kr.)</span><input v-model="nf.amount" type="number" min="0" step="0.01" required></label>
                    <label class="field"><span>Gælder fra</span><input v-model="nf.valid_from" type="date" :min="d.today" required></label>
                </div>
                <div class="row" style="gap:6px">
                    <button type="button" class="chip" @click="nf.valid_from = d.today">I dag</button>
                    <button type="button" class="chip" @click="nf.valid_from = nextSeason">Næste sæson ({{ dato(nextSeason) }})</button>
                </div>
                <p class="small muted" style="margin:0">Regninger oprettet før denne dato beholder den gamle pris. Ændringen skrives i ændringsloggen.</p>
                <p v-if="error" class="banner bad" style="margin:0">{{ error }}</p>
                <button class="btn primary block" :disabled="busy">Gem ny pris</button>
            </form>
        </Modal>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import Modal from './Modal.vue';
import { api, toast, dato } from '../api';

const tabs = [{ id: 'membership', label: 'Medlemskaber' }, { id: 'product', label: 'Tilkøb' }, { id: 'categories', label: 'Personkategorier' }];
const periodTypes = [{ id: 'dates', label: 'Faste datoer' }, { id: 'days', label: 'Antal dage' }, { id: 'once', label: 'Engangsbetaling' }];
const months = ['jan.', 'feb.', 'mar.', 'apr.', 'maj', 'jun.', 'jul.', 'aug.', 'sep.', 'okt.', 'nov.', 'dec.'];
const tab = ref('membership'); const d = ref(null); const ed = ref(null); const pr = ref(null); const error = ref(''); const busy = ref(false);
const nf = reactive({ amount: '', valid_from: '' });
const nc = reactive({ name: '', plural: '', min_age: 0, max_age: null });
const nextSeason = (() => { const t = new Date(); return `${t.getMonth() >= 4 ? t.getFullYear() + 1 : t.getFullYear()}-05-01`; })();

async function load() { d.value = await api('settings/packages'); }
onMounted(load);
const list = (archived) => d.value.packages.filter(p => p.kind === tab.value && p.archived === archived);
const nameOf = (id) => d.value.packages.find(p => p.id === id)?.name || '';
const pad = (n) => String(n).padStart(2, '0');
const md = (s) => s ? { d: Number(s.slice(3)), m: Number(s.slice(0, 2)) } : null;

function openNew() {
    const income = d.value.accounts;
    ed.value = blank({ kind: tab.value, account_id: (income.find(a => a.number === (tab.value === 'membership' ? '1000' : '1200')) || income[0])?.id });
}
function blank(over = {}) {
    const p = { id: null, name: '', description: '', period_type: 'dates', period_days: 30, date_from: '05-01', date_to: '04-30', total_min: 1, total_max: 1,
        gives_access: true, requires_membership: false, self_service: true, renews_to_id: null, slots: [], price: '', purchases: 0, ...over };
    const f = md(p.date_from) || { d: 1, m: 5 }; const t = md(p.date_to) || { d: 30, m: 4 };
    const sf = md(p.sale_from) || { d: 1, m: 5 }; const st = md(p.sale_to) || { d: 31, m: 7 };
    return { ...p, fromD: f.d, fromM: f.m, toD: t.d, toM: t.m, hasSale: !!p.sale_from, sFromD: sf.d, sFromM: sf.m, sToD: st.d, sToM: st.m,
        slotRows: d.value.categories.filter(c => !c.archived || p.slots.some(s => s.person_category_id === c.id)).map(c => {
            const s = p.slots.find(s => s.person_category_id === c.id);
            return { person_category_id: c.id, label: `${c.name} (${c.min_age}${c.max_age === null ? '+' : '–' + c.max_age} år)`, on: !!s || !p.id, min: s?.min ?? 0, max: s?.max ?? (p.total_max || 1) };
        }) };
}
function openEdit(p) { error.value = ''; ed.value = blank(JSON.parse(JSON.stringify(p))); }

const periodHelp = computed(() => {
    const e = ed.value; if (!e) return '';
    if (e.period_type === 'once') return 'Betales én gang og udløber ikke, fx depositum.';
    if (e.period_type === 'days') return `Gælder ${e.period_days || '?'} dage fra købsdatoen.`;
    return `Gælder fra ${e.fromD}. ${months[e.fromM - 1]} til ${e.toD}. ${months[e.toM - 1]} hvert år. Kan købes, mens perioden løber, og gælder til periodens slutning.`;
});
const preview = computed(() => {
    const e = ed.value; if (!e) return '';
    const age = (s) => d.value.categories.find(c => c.id === s.person_category_id)?.min_age || 0;
    const rows = e.slotRows.filter(s => s.on && s.max > 0).sort((a, b) => age(b) - age(a));
    const r = (a, b) => a === b ? `${a}` : `${a}–${b}`;
    const total = e.total_max ? r(e.total_min, e.total_max) : `${e.total_min}+`;
    const cats = rows.map(s => { const c = d.value.categories.find(c => c.id === s.person_category_id); return `${r(s.min, s.max)} ${(s.max === 1 ? c.name : c.plural).toLowerCase()}`; });
    return `${total === '1' ? '1 person' : total + ' personer'}${cats.length ? ' – ' + cats.join(' og ') : ''}`;
});

async function savePkg() {
    const e = ed.value; error.value = ''; busy.value = true;
    const body = {
        name: e.name, description: e.description || null, period_type: e.period_type,
        period_days: e.period_type === 'days' ? e.period_days : null,
        date_from: e.period_type === 'dates' ? `${pad(e.fromM)}-${pad(e.fromD)}` : null,
        date_to: e.period_type === 'dates' ? `${pad(e.toM)}-${pad(e.toD)}` : null,
        sale_from: e.hasSale && e.period_type !== 'once' ? `${pad(e.sFromM)}-${pad(e.sFromD)}` : null,
        sale_to: e.hasSale && e.period_type !== 'once' ? `${pad(e.sToM)}-${pad(e.sToD)}` : null,
        total_min: e.total_min, total_max: e.total_max || null,
        gives_access: e.gives_access, requires_membership: e.requires_membership, self_service: e.self_service,
        renews_to_id: e.period_type === 'dates' ? e.renews_to_id : null, account_id: e.account_id,
        slots: e.slotRows.filter(s => s.on).map(s => ({ person_category_id: s.person_category_id, min: s.min || 0, max: s.max || 0 })),
        ...(e.id ? {} : { kind: e.kind, price: e.price }),
    };
    try {
        d.value = await api(e.id ? `settings/packages/${e.id}` : 'settings/packages', { method: e.id ? 'PUT' : 'POST', body });
        toast(e.id ? 'Ændringerne er gemt' : 'Pakken er oprettet'); ed.value = null;
    } catch (x) { error.value = x.message; } finally { busy.value = false; }
}
async function archive(p, on) {
    if (on && !confirm(`Arkivér "${p.name}"?\n\nDen kan ikke længere købes og fornyes ikke ved ny sæson. ${p.active_purchases} gældende køb beholder deres periode. Den kan gendannes.`)) return;
    try { d.value = await api(`settings/packages/${p.id}/archive`, { method: 'PUT', body: { archived: on } }); toast(on ? 'Arkiveret' : 'Gendannet'); } catch (x) { toast(x.message, 'error'); }
}
function openPrice(p) { error.value = ''; pr.value = p; nf.amount = p.current ? p.current.amount / 100 : ''; nf.valid_from = nextSeason; }
async function savePrice() {
    busy.value = true; error.value = '';
    try { d.value = await api(`settings/packages/${pr.value.id}/price`, { method: 'POST', body: nf }); toast(`Ny pris gemt – gælder fra ${dato(nf.valid_from)}`); pr.value = null; }
    catch (x) { error.value = x.message; } finally { busy.value = false; }
}
async function deletePrice(p, u) {
    try { d.value = await api(`settings/packages/${p.id}/price/${u.id}`, { method: 'DELETE' }); toast('Den kommende pris er fjernet'); } catch (x) { toast(x.message, 'error'); }
}
async function saveCat(c, archived) {
    try {
        d.value = await api(c.id ? `settings/person-categories/${c.id}` : 'settings/person-categories', { method: c.id ? 'PUT' : 'POST',
            body: { name: c.name, plural: c.plural, min_age: c.min_age, max_age: c.max_age === '' ? null : c.max_age, ...(archived !== undefined ? { archived } : {}) } });
        if (!c.id) Object.assign(nc, { name: '', plural: '', min_age: 0, max_age: null });
        toast('Gemt');
    } catch (x) { toast(x.message, 'error'); }
}
</script>

<style scoped>
.pk > li { padding: 12px 0; }
.upcoming { margin-top: 6px; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
fieldset { border: 1px solid var(--line); border-radius: 12px; padding: 10px 12px 12px; margin: 0; display: grid; gap: 8px; }
legend { font-weight: 700; font-size: .9rem; padding: 0 4px; }
.dm { gap: 6px; flex-wrap: nowrap; } .dm select:first-child { width: 80px; }
.slots { width: 100%; font-size: .9rem; } .slots th, .slots td { padding: 4px 6px; } .slots input[type=number] { width: 70px; min-height: 36px; padding: 4px 8px; }
.slots input[type=checkbox] { width: 20px; height: 20px; min-height: 0; }
.cat { grid-template-columns: 1.2fr 1.2fr .8fr .8fr; gap: 8px; }
@media (max-width: 560px) { .cat { grid-template-columns: 1fr 1fr; } }
li.archived { opacity: .6; }
</style>
