<template>
    <div>
        <div class="topbar">
            <h1>Design <HelpButton topic="design" /></h1>
            <RouterLink to="/indstillinger" class="btn small ghost">← Indstillinger</RouterLink>
        </div>
        <p class="muted" style="margin:0 0 14px">Lav klubbens egne farvetemaer. Et tema bestemmer farver, logo, skrift og runde hjørner, men aldrig sidernes opbygning, så alt virker på både mobil og pc. De indbyggede temaer kan ikke rettes. Åbn en kopi og gem den under et nyt navn.</p>
        <div v-if="!themes" class="spinner"></div>
        <div v-else :class="['dz', { open: !!ed }]">
            <div class="ctl">
                <div class="card">
                    <h2>Temaer</h2>
                    <ul class="list tl">
                        <li v-for="t in themes" :key="t.key" :class="{ cur: ed?.id === t.id }">
                            <span class="sw" :style="{ background: sw(t.data) }"></span>
                            <span class="mtext"><strong>{{ t.name }}</strong>
                                <span class="small muted"><span :class="['tag', t.status === 'available' ? 'ok' : 'warn']">{{ t.status === 'available' ? 'Tilgængeligt' : 'Under udvikling' }}</span>
                                    <template v-if="t.by"> · {{ t.by }}, {{ datoTid(t.updated_at) }}</template></span></span>
                            <button class="btn small" :disabled="ed?.id === t.id" @click="openTheme(t)">{{ ed?.id === t.id ? 'Åben' : 'Rediger' }}</button>
                        </li>
                        <li v-if="!themes.length" class="small muted">Ingen egne temaer endnu. Åbn en kopi af et indbygget tema nedenfor.</li>
                    </ul>
                    <h3 style="margin-top:16px">Indbyggede (låst)</h3>
                    <ul class="list tl">
                        <li v-for="(b, k) in BUILT_IN" :key="k">
                            <span class="sw" :style="{ background: sw(b.data) }"></span>
                            <span class="mtext"><strong>{{ b.name }}</strong><span v-if="k === 'klub'" class="small muted">Bruges af Automatisk</span></span>
                            <button class="btn small ghost" @click="openCopy(k)">Åbn kopi</button>
                        </li>
                    </ul>
                </div>

                <template v-if="ed">
                    <div class="card edhead">
                        <div class="row between" style="gap:8px;flex-wrap:nowrap;align-items:flex-start">
                            <div style="min-width:0">
                                <div class="small muted">{{ ed.id ? 'Redigerer' : 'Ny kopi – ikke gemt endnu' }}</div>
                                <h2 style="margin:0;overflow-wrap:anywhere">{{ ed.name }}</h2>
                            </div>
                            <span v-if="dirty" class="tag info" style="flex:none">Ikke gemt</span>
                        </div>
                        <label class="field" style="margin-top:12px"><span>Navn</span><input v-model.trim="ed.name" maxlength="40" required></label>
                        <div style="margin-top:10px">
                            <div class="small" style="font-weight:600;margin-bottom:6px">Hvem kan vælge temaet?</div>
                            <div class="chips">
                                <button :class="['chip', { on: ed.status === 'development' }]" @click="ed.status = 'development'">{{ ed.status === 'development' ? '✓ ' : '' }}Under udvikling</button>
                                <button :class="['chip', { on: ed.status === 'available' }]" @click="ed.status = 'available'">{{ ed.status === 'available' ? '✓ ' : '' }}Tilgængeligt for medlemmer</button>
                            </div>
                            <p class="small muted" style="margin:6px 0 0">{{ ed.status === 'available' ? 'Alle kan vælge temaet under Farvetema.' : 'Kun dig og andre, der må rette design, kan vælge temaet – så I kan prøve det af i praksis.' }}</p>
                        </div>
                        <div class="row" style="gap:8px;margin-top:14px">
                            <button class="btn primary" :disabled="busy || !ed.name || (ed.id && !dirty)" @click="save">Gem</button>
                            <button class="btn" :disabled="busy" @click="saveAs.open = true; saveAs.name = suggestName(); saveAs.status = 'development'">Gem som…</button>
                            <button v-if="ed.id" class="btn ghost danger" :disabled="busy" @click="remove">Slet</button>
                            <button class="btn ghost" @click="close">Luk</button>
                        </div>
                    </div>

                    <div class="card">
                        <div class="tabs" style="margin-bottom:10px">
                            <button :class="{ on: mode === 'light' }" @click="mode = 'light'">Lys udgave</button>
                            <button :class="{ on: mode === 'dark' }" @click="mode = 'dark'">Mørk udgave</button>
                        </div>
                        <p class="small muted" style="margin:0 0 6px">{{ mode === 'light' ? 'Bruges, når telefonen eller computeren står på lys tilstand.' : 'Bruges, når telefonen eller computeren står på mørk tilstand (fx om aftenen).' }} Tryk på en farve for at ændre den.</p>
                        <ul class="list colors">
                            <li v-for="[k, label, hint] in COLOR_FIELDS" :key="k">
                                <label class="crow">
                                    <input type="color" v-model="ed.data[mode][k]" class="cpick">
                                    <span class="mtext"><strong>{{ label }}</strong><span v-if="hint" class="small muted">{{ hint }}</span></span>
                                </label>
                                <button v-if="ed.data[mode][k] !== orig.data[mode][k]" class="btn ghost small" title="Fortryd ændringen af farven" @click="ed.data[mode][k] = orig.data[mode][k]">↺</button>
                            </li>
                        </ul>
                    </div>

                    <div class="card">
                        <h2>Kan man læse det?</h2>
                        <p v-if="!bad.length" class="small" style="margin:0;color:var(--ok)">✓ Alle tekster står tydeligt i både lys og mørk udgave.</p>
                        <template v-else>
                            <p class="small muted" style="margin:0 0 8px">Disse farver giver for lidt forskel mellem tekst og baggrund. Det rammer især svagtseende og folk, der læser i sollys på stranden.</p>
                            <ul class="list small">
                                <li v-for="c in bad" :key="c.mode + c.label" class="row between"><span><span class="tag warn">{{ c.mode }}</span> {{ c.label }}</span><span class="num muted">{{ c.ratio.toFixed(1) }} (skal være {{ c.min }})</span></li>
                            </ul>
                        </template>
                    </div>

                    <div class="card">
                        <h2>Logo</h2>
                        <div class="logos">
                            <div class="lg" :style="{ background: ed.data[mode].surface }"><img :src="ed.logo_url || DEFAULT_LOGO" alt="Logo"></div>
                            <div class="lg" :style="{ background: ed.data[mode].brand }"><img :src="ed.logo_url || DEFAULT_LOGO" alt="Logo i menuen"></div>
                        </div>
                        <p class="small muted" style="margin:8px 0">PNG med gennemsigtig baggrund er bedst. Logoet vises altid i samme bredde, så det ikke kan skubbe siden.</p>
                        <div class="row" style="gap:8px">
                            <label class="btn small"><input type="file" accept="image/png,image/jpeg,image/webp" hidden @change="uploadLogo"> {{ uploading ? 'Sender…' : 'Vælg nyt logo' }}</label>
                            <button v-if="ed.data.logo" class="btn ghost small" @click="ed.data.logo = null; ed.logo_url = null">Brug klubbens standardlogo</button>
                        </div>
                    </div>

                    <div class="card">
                        <h2>Skrift og hjørner</h2>
                        <div class="chips" style="margin-bottom:14px">
                            <button v-for="(label, k) in FONTS" :key="k" :class="['chip', { on: ed.data.font === k }]" @click="ed.data.font = k">{{ ed.data.font === k ? '✓ ' : '' }}{{ label }}</button>
                        </div>
                        <label class="field"><span>Runde hjørner: {{ ed.data.radius }} px</span>
                            <input type="range" min="0" max="20" step="1" v-model.number="ed.data.radius"></label>
                        <div class="row" style="gap:8px;margin-top:6px">
                            <span class="rdemo" :style="{ borderRadius: ed.data.radius + 'px' }">Kort</span>
                            <span class="rdemo" :style="{ borderRadius: Math.min(ed.data.radius, 12) + 'px', background: ed.data[mode].accent, borderColor: ed.data[mode].accent, color: inkOn(ed.data[mode].accent) }">Knap</span>
                        </div>
                    </div>
                </template>
            </div>

            <div v-if="ed" class="pv">
                <div class="card">
                    <div class="row between" style="gap:8px;margin-bottom:10px">
                        <div class="chips">
                            <button v-for="(label, path) in PAGES" :key="path" :class="['chip', { on: page === path }]" @click="page = path">{{ label }}</button>
                        </div>
                        <div v-if="!isMobile" class="tabs" style="margin:0;flex:none">
                            <button :class="{ on: device === 'phone' }" @click="device = 'phone'">Mobil</button>
                            <button :class="{ on: device === 'pc' }" @click="device = 'pc'">Computer</button>
                        </div>
                    </div>
                    <p class="small muted" style="margin:0 0 10px">{{ mode === 'light' ? 'Lys' : 'Mørk' }} udgave. Forhåndsvisningen er de rigtige sider med temaet, som det ser ud lige nu. Du kan trykke rundt i dem, men intet bliver gemt derinde.</p>
                    <div class="frames">
                        <figure v-show="isMobile || device === 'phone'" class="fr phone" ref="phoneBox">
                            <div class="clip" :style="{ height: 780 * sPhone + 'px' }"><iframe ref="phoneF" :src="src" :style="{ width: '390px', height: '780px', transform: `scale(${sPhone})` }" title="Forhåndsvisning på mobil" @load="send"></iframe></div>
                        </figure>
                        <figure v-if="!isMobile" v-show="device === 'pc'" class="fr pc" ref="pcBox">
                            <div class="clip" :style="{ height: 800 * sPc + 'px' }"><iframe ref="pcF" :src="src" :style="{ width: '1280px', height: '800px', transform: `scale(${sPc})` }" title="Forhåndsvisning på computer" @load="send"></iframe></div>
                        </figure>
                    </div>
                </div>
            </div>
        </div>

        <Modal v-if="saveAs.open" title="Gem som nyt tema" @close="saveAs.open = false">
            <form class="stack" @submit.prevent="doSaveAs">
                <label class="field"><span>Navn</span><input v-model.trim="saveAs.name" maxlength="40" required></label>
                <div class="chips">
                    <button type="button" :class="['chip', { on: saveAs.status === 'development' }]" @click="saveAs.status = 'development'">{{ saveAs.status === 'development' ? '✓ ' : '' }}Under udvikling</button>
                    <button type="button" :class="['chip', { on: saveAs.status === 'available' }]" @click="saveAs.status = 'available'">{{ saveAs.status === 'available' ? '✓ ' : '' }}Tilgængeligt for medlemmer</button>
                </div>
                <p class="small muted" style="margin:0">{{ ed?.id ? `"${orig.name}" forbliver, som det var sidst gemt.` : '' }} Du fortsætter med at redigere det nye tema.</p>
                <button class="btn primary block" :disabled="busy || !saveAs.name">Gem som nyt tema</button>
            </form>
        </Modal>
    </div>
</template>
<script setup>
import { ref, reactive, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { onBeforeRouteLeave } from 'vue-router';
import { api, toast } from '../api';
import { BUILT_IN, DEFAULT_LOGO, COLOR_FIELDS, FONTS, checks, refreshDesign, inkOn } from '../design';
import { isMobile } from '../mobile';
import HelpButton from '../components/HelpButton.vue';
import Modal from '../components/Modal.vue';

const PAGES = { '/aktiviteter': 'Aktiviteter', '/opslag': 'Opslag', '/profil': 'Profil', '/mere': 'Mere' };
const clone = (x) => JSON.parse(JSON.stringify(x));
const datoTid = (s) => new Date(s).toLocaleString('da-DK', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
const sw = (d) => `conic-gradient(${d.light.brand} 0 40%, ${d.light.accent} 0 70%, ${d.light.orange} 0 85%, ${d.light.bg} 0)`;

const themes = ref(null);
const ed = ref(null);   // temaet, der redigeres: { id|null, name, status, data, logo_url }
const orig = ref(null); // som det var, da det blev åbnet/gemt
const mode = ref(window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
const page = ref('/aktiviteter');
const device = ref('pc');
const busy = ref(false); const uploading = ref(false);
const saveAs = reactive({ open: false, name: '', status: 'development' });
const src = computed(() => `${page.value}?designvisning=${mode.value === 'dark' ? 'mork' : 'lys'}`);

// Sammenlign uafhængigt af nøglernes rækkefølge (databasen sorterer dem)
const pick = (c) => COLOR_FIELDS.map(([k]) => c?.[k]?.toLowerCase());
const norm = (x) => JSON.stringify([x.name, x.status, pick(x.data.light), pick(x.data.dark), Number(x.data.radius), x.data.font, x.data.logo || null]);
const dirty = computed(() => ed.value && orig.value && norm(ed.value) !== norm(orig.value));
const bad = computed(() => (ed.value ? checks(ed.value.data).filter((c) => !c.ok) : []));

async function load() {
    themes.value = (await api('settings/design')).themes;
}

const discardOk = () => !dirty.value || confirm('Du har ændringer, der ikke er gemt. Smid dem væk?');
function start(t) {
    ed.value = clone(t); orig.value = clone(t);
    nextTick(() => { send(); fit(); observe(); });
}
function openTheme(t) {
    if (!discardOk()) return;
    start({ id: t.id, name: t.name, status: t.status, data: t.data, logo_url: t.logo_url });
}
function openCopy(k) {
    if (!discardOk()) return;
    const b = BUILT_IN[k];
    start({ id: null, name: suggestName('Kopi af ' + b.name), status: 'development', data: b.data, logo_url: null });
    orig.value.name = null; // en ny kopi er altid "ikke gemt"
}
function close() { if (discardOk()) { ed.value = null; orig.value = null; } }

function suggestName(base) {
    base = base || (ed.value?.id ? ed.value.name + ' (kopi)' : ed.value?.name) || 'Nyt tema';
    const taken = new Set([...(themes.value || []).map((t) => t.name.toLowerCase())]);
    let n = base; let i = 2;
    while (taken.has(n.toLowerCase())) n = `${base} ${i++}`;
    return n.slice(0, 40);
}

const body = (x) => ({ name: x.name, status: x.status, data: x.data });
async function save() {
    if (ed.value.status === 'available' && bad.value.length && !confirm(`${bad.value.length} ${bad.value.length === 1 ? 'tekst er' : 'tekster er'} svære at læse (se "Kan man læse det?"). Gøre temaet tilgængeligt alligevel?`)) return;
    busy.value = true;
    try {
        const r = ed.value.id
            ? await api(`settings/design/themes/${ed.value.id}`, { method: 'PUT', body: body(ed.value) })
            : await api('settings/design/themes', { method: 'POST', body: body(ed.value) });
        await after(r, 'Temaet er gemt');
    } catch (e) { toast(e.message, 'error'); }
    busy.value = false;
}
async function doSaveAs() {
    if (saveAs.status === 'available' && bad.value.length && !confirm(`${bad.value.length} ${bad.value.length === 1 ? 'tekst er' : 'tekster er'} svære at læse. Gøre temaet tilgængeligt alligevel?`)) return;
    busy.value = true;
    try {
        const r = await api('settings/design/themes', { method: 'POST', body: { ...body(ed.value), name: saveAs.name, status: saveAs.status } });
        saveAs.open = false;
        await after(r, `Gemt som "${r.name}"`);
    } catch (e) { toast(e.message, 'error'); }
    busy.value = false;
}
async function after(r, msg) {
    ed.value = { id: r.id, name: r.name, status: r.status, data: r.data, logo_url: r.logo_url };
    orig.value = clone(ed.value);
    await Promise.all([load(), refreshDesign()]); // temavælgeren skal kende det nye/ændrede tema
    toast(msg);
}
async function remove() {
    if (!confirm(`Slet temaet "${orig.value.name}"? Medlemmer, der har valgt det, får Automatisk (SurfHorsens) i stedet.`)) return;
    busy.value = true;
    try {
        await api(`settings/design/themes/${ed.value.id}`, { method: 'DELETE' });
        ed.value = null; orig.value = null;
        await Promise.all([load(), refreshDesign()]);
        toast('Temaet er slettet');
    } catch (e) { toast(e.message, 'error'); }
    busy.value = false;
}

async function uploadLogo(e) {
    const file = e.target.files[0]; e.target.value = '';
    if (!file) return;
    uploading.value = true;
    try {
        const form = new FormData(); form.append('logo', file);
        const r = await api('settings/design/logo', { method: 'POST', form });
        ed.value.logo_url = r.logo_url; ed.value.data.logo = r.logo;
    } catch (err) { toast(err.message, 'error'); }
    uploading.value = false;
}

// Forhåndsvisningen opdateres med det samme ved hver ændring
const phoneF = ref(null); const pcF = ref(null);
function send() {
    if (!ed.value) return;
    const p = { type: 'klub-design', data: clone(ed.value.data), logo_url: ed.value.logo_url };
    window.__klubDesignDraft = p; // læses af iframen, når den indlæses
    for (const f of [phoneF.value, pcF.value]) f?.contentWindow?.postMessage(p, location.origin);
}
watch(() => ed.value && [ed.value.data, ed.value.logo_url], send, { deep: true });
watch(device, () => nextTick(fit));

// Skalér iframes, så de passer i bredden
const phoneBox = ref(null); const pcBox = ref(null); const sPhone = ref(0.8); const sPc = ref(0.5);
const ro = new ResizeObserver(() => fit());
function fit() {
    if (phoneBox.value?.clientWidth) sPhone.value = Math.min(1, phoneBox.value.clientWidth / 390);
    if (pcBox.value?.clientWidth) sPc.value = Math.min(1, pcBox.value.clientWidth / 1280);
}
function observe() { ro.disconnect(); for (const el of [phoneBox.value, pcBox.value]) el && ro.observe(el); }

const beforeUnload = (e) => { if (dirty.value) { e.preventDefault(); e.returnValue = ''; } };
onBeforeRouteLeave(() => discardOk());
onMounted(async () => {
    window.addEventListener('beforeunload', beforeUnload);
    try { await load(); } catch (e) { toast(e.message, 'error'); }
});
onUnmounted(() => { ro.disconnect(); delete window.__klubDesignDraft; window.removeEventListener('beforeunload', beforeUnload); });
</script>
<style scoped>
.dz { display: grid; gap: 14px; grid-template-columns: minmax(0, 1fr); }
.dz:not(.open) { max-width: 560px; }
@media (min-width: 1000px) { .dz.open { grid-template-columns: 380px minmax(0, 1fr); align-items: start; } .pv { position: sticky; top: 14px; } }
.ctl > .card + .card { margin-top: 14px; }
.tl li { display: flex; align-items: center; gap: 10px; }
.tl li.cur { background: var(--accent-soft); border-radius: 10px; padding-left: 8px; padding-right: 8px; }
.tl .mtext { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.tl .sw { width: 26px; height: 26px; border-radius: 50%; flex: none; box-shadow: 0 0 0 1px var(--line); }
.tl .btn { flex: none; }
.colors li { display: flex; align-items: center; gap: 6px; }
.crow { display: flex; align-items: center; gap: 12px; flex: 1; min-width: 0; cursor: pointer; padding: 2px 0; }
.cpick { width: 46px; height: 38px; padding: 0; flex: none; border: 1px solid var(--line); border-radius: 10px; cursor: pointer; background: none; }
.cpick::-webkit-color-swatch-wrapper { padding: 3px; }
.cpick::-webkit-color-swatch { border: 0; border-radius: 7px; }
.cpick::-moz-color-swatch { border: 0; border-radius: 7px; }
.crow .mtext { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.logos { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.lg { border: 1px solid var(--line); border-radius: 10px; padding: 14px; display: flex; align-items: center; justify-content: center; min-height: 80px; }
.lg img { width: 100%; max-width: 150px; max-height: 70px; object-fit: contain; }
.rdemo { border: 1px solid var(--line); background: var(--surface-2); padding: 10px 18px; font-size: .85rem; }
.frames { display: grid; gap: 14px; grid-template-columns: minmax(0, 1fr); }
.fr { margin: 0; min-width: 0; }
.fr.phone { max-width: 390px; width: 100%; margin: 0 auto; }
.clip { overflow: hidden; border: 1px solid var(--line); border-radius: 12px; background: var(--bg); }
.clip iframe { border: 0; transform-origin: 0 0; display: block; }
</style>
