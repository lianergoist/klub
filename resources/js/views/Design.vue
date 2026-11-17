<template>
    <div class="dpage">
        <div class="topbar">
            <h1>Design <HelpButton topic="design" /></h1>
            <RouterLink to="/system" class="btn small ghost">← System</RouterLink>
        </div>

        <!-- Værktøjslinjen: et tryk åbner den flydende værktøjsboks, et tryk mere lukker den -->
        <div class="toolbar card">
            <div class="tools" role="tablist">
                <button v-for="t in TOOLS" :key="t.id" role="tab" :aria-selected="panel === t.id" :class="['tool', { on: panel === t.id }]"
                    :disabled="t.id !== 'tema' && !ed" @click="toggle(t.id)">
                    {{ t.label }}<span v-if="t.id === 'gem' && dirty" class="udot" title="Ikke gemt"></span><span v-if="t.id === 'farver' && ed && bad.length" class="badge" title="Tekster, der er svære at læse">{{ bad.length }}</span>
                </button>
            </div>
            <div class="view">
                <span v-if="ed" class="cur small"><strong>{{ ed.name || 'Uden navn' }}</strong><span class="muted">{{ dirty ? ' · ikke gemt' : '' }}</span></span>
                <div class="tabs seg">
                    <button :class="{ on: mode === 'light' }" @click="mode = 'light'">Lys</button>
                    <button :class="{ on: mode === 'dark' }" @click="mode = 'dark'">Mørk</button>
                </div>
                <select v-model="page" class="pagesel" aria-label="Side i forhåndsvisningen">
                    <option v-for="(label, path) in PAGES" :key="path" :value="path">{{ label }}</option>
                </select>
                <div v-if="!isMobile" class="tabs seg">
                    <button :class="{ on: device === 'phone' }" @click="device = 'phone'">Mobil</button>
                    <button :class="{ on: device === 'pc' }" @click="device = 'pc'">Computer</button>
                </div>
            </div>
        </div>

        <!-- Forhåndsvisningen fylder hele bredden -->
        <div class="stage" ref="stage">
            <div v-if="!ed" class="empty card">
                <h2>Vælg et tema</h2>
                <p class="muted" style="margin:0">Åbn et af klubbens egne temaer, eller åbn en kopi af et indbygget tema under <strong>Tema</strong>. Så vises det her, som det ser ud på mobil og computer.</p>
            </div>
            <template v-else>
                <div v-show="isMobile || device === 'phone'" class="clip phone" :style="{ width: 390 * sPhone + 'px', height: 844 * sPhone + 'px' }">
                    <iframe ref="phoneF" :src="src" :style="{ width: '390px', height: '844px', transform: `scale(${sPhone})` }" title="Forhåndsvisning på mobil" @load="send"></iframe>
                </div>
                <div v-if="!isMobile" v-show="device === 'pc'" class="clip pc" :style="{ height: pcH * sPc + 'px' }">
                    <iframe ref="pcF" :src="src" :style="{ width: '1280px', height: pcH + 'px', transform: `scale(${sPc})` }" title="Forhåndsvisning på computer" @load="send"></iframe>
                </div>
                <p class="small muted" style="margin:8px 0 0">Forhåndsvisningen er de rigtige sider med temaet, som det ser ud lige nu ({{ mode === 'light' ? 'lys' : 'mørk' }} udgave). Du kan trykke rundt i dem, men intet bliver gemt derinde.</p>
            </template>
        </div>

        <!-- Den flydende værktøjsboks. Kan flyttes i overskriften (på mobil ligger den i bunden) -->
        <section v-if="panel && themes" ref="float" :class="['float', { sheet: isMobile }]" :style="isMobile ? null : { left: pos.x + 'px', top: pos.y + 'px', maxHeight: Math.max(220, vh - pos.y - 12) + 'px' }" :aria-label="title">
            <header class="fhead" @pointerdown="dragStart">
                <span class="grip" aria-hidden="true">⠿</span>
                <strong>{{ title }}</strong>
                <button class="fx" aria-label="Luk værktøjsboksen" @pointerdown.stop @click="panel = null">×</button>
            </header>
            <div class="fbody">
                <!-- Tema -->
                <template v-if="panel === 'tema'">
                    <h3>Klubbens temaer</h3>
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
                    <h3 style="margin-top:14px">Indbyggede (låst)</h3>
                    <ul class="list tl">
                        <li v-for="(b, k) in BUILT_IN" :key="k">
                            <span class="sw" :style="{ background: sw(b.data) }"></span>
                            <span class="mtext"><strong>{{ b.name }}</strong><span v-if="k === 'klub'" class="small muted">Bruges af Automatisk</span></span>
                            <button class="btn small ghost" @click="openCopy(k)">Åbn kopi</button>
                        </li>
                    </ul>
                </template>

                <!-- Gem -->
                <template v-else-if="panel === 'gem' && ed">
                    <p class="small muted" style="margin:0 0 8px">{{ ed.id ? 'Du redigerer et af klubbens temaer.' : 'Ny kopi – ikke gemt endnu.' }}</p>
                    <label class="field"><span>Navn</span><input v-model.trim="ed.name" maxlength="40" required></label>
                    <div style="margin-top:12px">
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
                    </div>
                    <div class="row" style="gap:8px;margin-top:8px">
                        <button v-if="ed.id" class="btn ghost small danger" :disabled="busy" @click="remove">Slet temaet</button>
                        <button class="btn ghost small" @click="close">Luk temaet</button>
                    </div>
                </template>

                <!-- Farver -->
                <template v-else-if="panel === 'farver' && ed">
                    <div class="tabs" style="margin-bottom:8px">
                        <button :class="{ on: mode === 'light' }" @click="mode = 'light'">Lys udgave</button>
                        <button :class="{ on: mode === 'dark' }" @click="mode = 'dark'">Mørk udgave</button>
                    </div>
                    <p class="small muted" style="margin:0 0 4px">Tryk på en farve for at ændre den. {{ mode === 'light' ? 'Den lyse udgave bruges, når enheden står på lys tilstand.' : 'Den mørke udgave bruges, når enheden står på mørk tilstand.' }}</p>
                    <ul class="list colors">
                        <li v-for="[k, label, hint] in COLOR_FIELDS" :key="k">
                            <label class="crow">
                                <input type="color" v-model="ed.data[mode][k]" class="cpick">
                                <span class="mtext"><strong>{{ label }}</strong><span v-if="hint" class="small muted">{{ hint }}</span></span>
                            </label>
                            <button v-if="ed.data[mode][k] !== orig.data[mode][k]" class="btn ghost small" title="Fortryd ændringen af farven" @click="ed.data[mode][k] = orig.data[mode][k]">↺</button>
                        </li>
                    </ul>
                    <h3 style="margin-top:14px">Kan man læse det?</h3>
                    <p v-if="!bad.length" class="small" style="margin:0;color:var(--ok)">✓ Alle tekster står tydeligt i både lys og mørk udgave.</p>
                    <template v-else>
                        <p class="small muted" style="margin:0 0 6px">For lidt forskel mellem tekst og baggrund. Det rammer især svagtseende og folk, der læser i sollys på stranden.</p>
                        <ul class="list small">
                            <li v-for="c in bad" :key="c.mode + c.label" class="row between"><span><span class="tag warn">{{ c.mode }}</span> {{ c.label }}</span><span class="num muted">{{ c.ratio.toFixed(1) }} / {{ c.min }}</span></li>
                        </ul>
                    </template>
                </template>

                <!-- Skrift og hjørner -->
                <template v-else-if="panel === 'skrift' && ed">
                    <div class="small" style="font-weight:600;margin-bottom:6px">Skrift</div>
                    <div class="chips" style="margin-bottom:14px">
                        <button v-for="(label, k) in FONTS" :key="k" :class="['chip', { on: ed.data.font === k }]" @click="ed.data.font = k">{{ ed.data.font === k ? '✓ ' : '' }}{{ label }}</button>
                    </div>
                    <label class="field"><span>Runde hjørner: {{ ed.data.radius }} px</span>
                        <input type="range" min="0" max="20" step="1" v-model.number="ed.data.radius"></label>
                    <div class="row" style="gap:8px;margin-top:8px">
                        <span class="rdemo" :style="{ borderRadius: ed.data.radius + 'px' }">Kort</span>
                        <span class="rdemo" :style="{ borderRadius: Math.min(ed.data.radius, 12) + 'px', background: ed.data[mode].accent, borderColor: ed.data[mode].accent, color: inkOn(ed.data[mode].accent) }">Knap</span>
                    </div>
                </template>
            </div>
        </section>

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
import { BUILT_IN, COLOR_FIELDS, FONTS, checks, refreshDesign, inkOn } from '../design';
import { isMobile } from '../mobile';
import HelpButton from '../components/HelpButton.vue';
import Modal from '../components/Modal.vue';

const TOOLS = [
    { id: 'tema', label: 'Tema' },
    { id: 'gem', label: 'Gem' },
    { id: 'farver', label: 'Farver' },
    { id: 'skrift', label: 'Skrift og hjørner' },
];
const TITLES = { tema: 'Vælg tema', gem: 'Gem', farver: 'Farver', skrift: 'Skrift og hjørner' };
const PAGES = { '/aktiviteter': 'Aktiviteter', '/opslag': 'Opslag', '/profil': 'Profil', '/mere': 'Mere' };
const clone = (x) => JSON.parse(JSON.stringify(x));
const datoTid = (s) => new Date(s).toLocaleString('da-DK', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
const sw = (d) => `conic-gradient(${d.light.brand} 0 40%, ${d.light.accent} 0 70%, ${d.light.orange} 0 85%, ${d.light.bg} 0)`;

const themes = ref(null);
const ed = ref(null);   // temaet, der redigeres: { id|null, name, status, data }
const orig = ref(null); // som det var, da det blev åbnet/gemt
const panel = ref('tema');
const title = computed(() => TITLES[panel.value]);
const mode = ref(window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
const page = ref('/aktiviteter');
const device = ref('pc');
const busy = ref(false);
const saveAs = reactive({ open: false, name: '', status: 'development' });
const src = computed(() => `${page.value}?designvisning=${mode.value === 'dark' ? 'mork' : 'lys'}`);

function toggle(id) { panel.value = panel.value === id ? null : id; if (panel.value) nextTick(clamp); }

// Sammenlign uafhængigt af nøglernes rækkefølge (databasen sorterer dem)
const pick = (c) => COLOR_FIELDS.map(([k]) => c?.[k]?.toLowerCase());
const norm = (x) => JSON.stringify([x.name, x.status, pick(x.data.light), pick(x.data.dark), Number(x.data.radius), x.data.font]);
const dirty = computed(() => ed.value && orig.value && norm(ed.value) !== norm(orig.value));
const bad = computed(() => (ed.value ? checks(ed.value.data).filter((c) => !c.ok) : []));

async function load() {
    themes.value = (await api('settings/design')).themes;
}

const discardOk = () => !dirty.value || confirm('Du har ændringer, der ikke er gemt. Smid dem væk?');
function start(t) {
    ed.value = clone(t); orig.value = clone(t);
    panel.value = 'farver'; // næste skridt er typisk farverne
    nextTick(() => { send(); fit(); clamp(); });
}
function openTheme(t) {
    if (!discardOk()) return;
    start({ id: t.id, name: t.name, status: t.status, data: t.data });
}
function openCopy(k) {
    if (!discardOk()) return;
    const b = BUILT_IN[k];
    start({ id: null, name: suggestName('Kopi af ' + b.name), status: 'development', data: b.data });
    orig.value.name = null; // en ny kopi er altid "ikke gemt"
}
function close() { if (discardOk()) { ed.value = null; orig.value = null; panel.value = 'tema'; } }

function suggestName(base) {
    base = base || (ed.value?.id ? ed.value.name + ' (kopi)' : ed.value?.name) || 'Nyt tema';
    const taken = new Set((themes.value || []).map((t) => t.name.toLowerCase()));
    let n = base; let i = 2;
    while (taken.has(n.toLowerCase())) n = `${base} ${i++}`;
    return n.slice(0, 40);
}

const body = (x) => ({ name: x.name, status: x.status, data: x.data });
const warnOk = (status) => status !== 'available' || !bad.value.length
    || confirm(`${bad.value.length} ${bad.value.length === 1 ? 'tekst er' : 'tekster er'} svære at læse (se Farver → "Kan man læse det?"). Gøre temaet tilgængeligt alligevel?`);
async function save() {
    if (!warnOk(ed.value.status)) return;
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
    if (!warnOk(saveAs.status)) return;
    busy.value = true;
    try {
        const r = await api('settings/design/themes', { method: 'POST', body: { ...body(ed.value), name: saveAs.name, status: saveAs.status } });
        saveAs.open = false;
        await after(r, `Gemt som "${r.name}"`);
    } catch (e) { toast(e.message, 'error'); }
    busy.value = false;
}
async function after(r, msg) {
    ed.value = { id: r.id, name: r.name, status: r.status, data: r.data };
    orig.value = clone(ed.value);
    await Promise.all([load(), refreshDesign()]); // temavælgeren skal kende det nye/ændrede tema
    toast(msg);
}
async function remove() {
    if (!confirm(`Slet temaet "${orig.value.name}"? Medlemmer, der har valgt det, får Automatisk (SurfHorsens) i stedet.`)) return;
    busy.value = true;
    try {
        await api(`settings/design/themes/${ed.value.id}`, { method: 'DELETE' });
        ed.value = null; orig.value = null; panel.value = 'tema';
        await Promise.all([load(), refreshDesign()]);
        toast('Temaet er slettet');
    } catch (e) { toast(e.message, 'error'); }
    busy.value = false;
}

// ---------- forhåndsvisning ----------
const phoneF = ref(null); const pcF = ref(null);
function send() {
    if (!ed.value) return;
    const p = { type: 'klub-design', data: clone(ed.value.data) };
    window.__klubDesignDraft = p; // læses af iframen, når den indlæses
    for (const f of [phoneF.value, pcF.value]) f?.contentWindow?.postMessage(p, location.origin);
}
watch(() => ed.value?.data, send, { deep: true });
watch(device, () => nextTick(fit));

// Computer: hele bredden, højden fylder skærmen. Mobil: hele telefonen, så høj som skærmen tillader.
const stage = ref(null); const sPhone = ref(0.8); const sPc = ref(0.8); const pcH = ref(800);
function fit() {
    const el = stage.value; if (!el) return;
    const top = el.getBoundingClientRect().top + window.scrollY;
    const availH = Math.max(420, window.innerHeight - Math.min(top, 220) - 60);
    const w = el.clientWidth;
    sPc.value = Math.min(1, w / 1280);
    pcH.value = Math.round(Math.max(600, availH / sPc.value));
    sPhone.value = Math.min(1, w / 390, availH / 844);
}
const ro = new ResizeObserver(() => fit());

// ---------- flydende værktøjsboks ----------
const float = ref(null);
const POS_KEY = 'design-vaerktoej-pos';
const vh = ref(window.innerHeight); // boksen må ikke gå ud over skærmens bund
const pos = reactive((() => { try { const p = JSON.parse(localStorage.getItem(POS_KEY)); if (p?.x >= 0) return p; } catch {} return { x: window.innerWidth - 400, y: 200 }; })());
function clamp() {
    const el = float.value; if (!el || isMobile.value) return;
    const w = el.offsetWidth, h = el.querySelector('.fhead')?.offsetHeight || 44;
    pos.x = Math.round(Math.min(Math.max(8, pos.x), window.innerWidth - w - 8));
    pos.y = Math.round(Math.min(Math.max(8, pos.y), window.innerHeight - h - 8)); // overskriften skal altid kunne nås
}
let drag = null;
function dragStart(e) {
    if (isMobile.value || e.button !== 0) return;
    drag = { dx: e.clientX - pos.x, dy: e.clientY - pos.y };
    e.currentTarget.setPointerCapture(e.pointerId);
    e.currentTarget.addEventListener('pointermove', dragMove);
    e.currentTarget.addEventListener('pointerup', dragEnd, { once: true });
    document.body.classList.add('dragging');
}
function dragMove(e) { if (!drag) return; pos.x = e.clientX - drag.dx; pos.y = e.clientY - drag.dy; clamp(); }
function dragEnd(e) {
    drag = null;
    e.currentTarget.removeEventListener('pointermove', dragMove);
    document.body.classList.remove('dragging');
    try { localStorage.setItem(POS_KEY, JSON.stringify(pos)); } catch {}
}
const onResize = () => { vh.value = window.innerHeight; fit(); clamp(); };

const beforeUnload = (e) => { if (dirty.value) { e.preventDefault(); e.returnValue = ''; } };
onBeforeRouteLeave(() => discardOk());
onMounted(async () => {
    window.addEventListener('beforeunload', beforeUnload);
    window.addEventListener('resize', onResize);
    try { await load(); } catch (e) { toast(e.message, 'error'); }
    await nextTick();
    if (stage.value) ro.observe(stage.value);
    fit(); clamp();
});
onUnmounted(() => {
    ro.disconnect(); delete window.__klubDesignDraft;
    window.removeEventListener('beforeunload', beforeUnload); window.removeEventListener('resize', onResize);
    document.body.classList.remove('dragging');
});
</script>
<style scoped>
.toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; padding: 8px; margin-bottom: 12px; position: sticky; top: calc(env(safe-area-inset-top, 0px) + 8px); z-index: 30; }
.tools { display: flex; gap: 4px; overflow-x: auto; scrollbar-width: none; max-width: 100%; }
.tool { position: relative; border: 0; background: none; font: inherit; font-weight: 600; font-size: .92rem; color: var(--ink-2); padding: 9px 14px; border-radius: 10px; cursor: pointer; white-space: nowrap; display: inline-flex; align-items: center; gap: 6px; }
.tool:hover:not(:disabled) { background: var(--surface-2); color: var(--ink); }
.tool.on { background: var(--accent-soft); color: var(--ink); box-shadow: inset 0 -2px 0 var(--accent); }
.tool:disabled { opacity: .4; cursor: default; }
.tool .udot { flex: none; width: 8px; height: 8px; border-radius: 50%; background: var(--warn); }
.tool .badge { min-width: 18px; height: 18px; padding: 0 5px; border-radius: 9px; background: var(--warn); color: #fff; font-size: .7rem; display: inline-flex; align-items: center; justify-content: center; }
.view { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.view .cur { max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.seg { margin: 0; padding: 3px; flex: none; }
.seg button { padding: 6px 10px; font-size: .85rem; }
.pagesel { width: auto; min-height: 36px; padding: 5px 10px; font-size: .88rem; }
.stage { min-width: 0; }
.empty { max-width: 560px; }
.clip { overflow: hidden; border: 1px solid var(--line); border-radius: 12px; background: var(--bg); box-shadow: var(--shadow); }
.clip.phone { margin: 0 auto; }
.clip iframe { border: 0; transform-origin: 0 0; display: block; }

.float { position: fixed; z-index: 35; width: 360px; max-width: calc(100vw - 16px); max-height: calc(100vh - 24px); display: flex; flex-direction: column;
    background: var(--surface); color: var(--ink); border: 1px solid var(--line); border-radius: 14px; box-shadow: 0 12px 40px rgba(0,0,0,.25), 0 2px 6px rgba(0,0,0,.12); }
.fhead { display: flex; align-items: center; gap: 8px; padding: 10px 10px 10px 12px; border-bottom: 1px solid var(--line); cursor: grab; user-select: none; touch-action: none; }
.fhead strong { flex: 1; }
.grip { color: var(--ink-3); font-size: 1.1rem; line-height: 1; }
.fx { border: 0; background: none; font-size: 1.4rem; line-height: 1; color: var(--ink-2); cursor: pointer; padding: 2px 8px; border-radius: 8px; }
.fx:hover { background: var(--surface-2); }
.fbody { padding: 12px 14px 16px; overflow-y: auto; overscroll-behavior: contain; }
.fbody h3:first-child { margin-top: 0; }
/* Mobil: værktøjsboksen ligger fast i bunden over menuen */
.float.sheet { left: 8px; right: 8px; width: auto; bottom: calc(env(safe-area-inset-bottom, 0px) + 70px); max-height: 55vh; }
.float.sheet .fhead { cursor: default; }
.float.sheet .grip { display: none; }

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
.rdemo { border: 1px solid var(--line); background: var(--surface-2); padding: 10px 18px; font-size: .85rem; }
</style>
<style>
/* Mens værktøjsboksen trækkes: ingen tekstmarkering, og iframes må ikke "sluge" musen */
body.dragging { user-select: none; cursor: grabbing; }
body.dragging iframe { pointer-events: none; }
</style>
