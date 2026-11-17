<template>
    <div class="dpage">
        <div class="topbar">
            <h1>Hjemmeside <HelpButton topic="hjemmeside" /></h1>
        </div>

        <div class="toolbar card">
            <div class="tools" role="tablist">
                <select v-if="pages" :value="pageId" class="pagesel" aria-label="Side" @change="onPageChange">
                    <option v-for="p in pages" :key="p.id" :value="p.id">{{ p.slug === '' ? 'Forside' : p.title }}{{ p.locked ? ' (kun admin)' : '' }}</option>
                </select>
                <button v-for="t in TOOLS" :key="t.id" role="tab" :aria-selected="panel === t.id" :class="['tool', { on: panel === t.id }]"
                    :disabled="!blocks || (t.id === 'blok' && !sel)" @click="toggle(t.id)">
                    {{ t.label }}<span v-if="t.id === 'udgiv' && dirty" class="udot" title="Ikke udgivet"></span>
                </button>
                <button class="tool" :disabled="!undoStack.length" title="Fortryd seneste ændring (Ctrl+Z)" @click="undo">↶ Fortryd</button>
            </div>
            <div class="view">
                <span v-if="blocks" class="small muted">{{ dirty ? 'Ikke udgivet' : 'Alt er udgivet' }}</span>
                <div v-if="!isMobile" class="tabs seg">
                    <button :class="{ on: device === 'phone' }" @click="device = 'phone'">Mobil</button>
                    <button :class="{ on: device === 'pc' }" @click="device = 'pc'">Computer</button>
                </div>
                <a v-if="cur" :href="cur.path" target="_blank" rel="noopener" class="btn small ghost">Se siden ↗</a>
            </div>
        </div>

        <div class="stage" ref="stage">
            <div v-if="locked" class="empty card">
                <h2>Kun administratoren kan rette denne side</h2>
                <p class="muted" style="margin:0">Kalenderen udfyldes automatisk med aktiviteter, der er markeret <strong>Vis i kalenderen på hjemmesiden</strong>. Vælg en anden side ovenfor.</p>
            </div>
            <template v-else-if="blocks">
                <div :class="['clip', device === 'phone' || isMobile ? 'phone' : 'pc']"
                    :style="phoneView ? { width: 390 * sPhone + 'px', height: 844 * sPhone + 'px' } : { height: pcH * sPc + 'px' }">
                    <iframe :key="pageId + device" ref="frame" :style="phoneView ? { width: '390px', height: '844px', transform: `scale(${sPhone})` } : { width: '1280px', height: pcH + 'px', transform: `scale(${sPc})` }"
                        title="Forhåndsvisning af siden" @load="frameLoaded"></iframe>
                </div>
                <p class="small muted" style="margin:8px 0 0">Klik på en blok for at rette den. Forhåndsvisningen viser dine ændringer med det samme; de kommer først på hjemmesiden, når du trykker <strong>Udgiv</strong>.</p>
            </template>
        </div>

        <section v-if="panel && blocks" ref="float" :class="['float', { sheet: isMobile }]" :style="isMobile ? null : { left: pos.x + 'px', top: pos.y + 'px', maxHeight: Math.max(220, vh - pos.y - 12) + 'px' }" :aria-label="title">
            <header class="fhead" @pointerdown="dragStart">
                <span class="grip" aria-hidden="true">⠿</span>
                <strong>{{ title }}</strong>
                <button class="fx" aria-label="Luk værktøjsboksen" @pointerdown.stop @click="panel = null">×</button>
            </header>
            <div class="fbody">
                <!-- Den valgte blok -->
                <template v-if="panel === 'blok' && sel">
                    <div class="bhead">
                        <span class="small muted">Blok {{ selIndex + 1 }} af {{ blocks.length }}</span>
                        <div class="row" style="gap:4px;flex-wrap:wrap">
                            <button class="btn small ghost" :disabled="selIndex === 0" title="Flyt blokken op" @click="moveBlock(-1)">↑ Op</button>
                            <button class="btn small ghost" :disabled="selIndex === blocks.length - 1" title="Flyt blokken ned" @click="moveBlock(1)">↓ Ned</button>
                            <button class="btn small ghost" @click="toggleHidden">{{ sel.hidden ? 'Vis blokken' : 'Skjul blokken' }}</button>
                            <button v-if="admin" class="btn small ghost danger" @click="removeBlock">Slet</button>
                        </div>
                    </div>
                    <p v-if="sel.hidden" class="small" style="margin:0 0 10px;color:var(--warn)">Blokken er skjult og vises ikke på hjemmesiden.</p>
                    <BlockFields :key="sel.id" :block="sel" :admin="admin" :options="options" :link-blocks="linkBlocks" />
                    <div class="row" style="margin-top:14px;gap:8px">
                        <button class="btn small" @click="addAt = selIndex + 1; panel = 'tilfoej'">+ Ny blok herunder</button>
                    </div>
                </template>

                <!-- Tilføj blok -->
                <template v-else-if="panel === 'tilfoej'">
                    <p class="small muted" style="margin:0 0 8px">{{ addAt != null && addAt < blocks.length ? `Den nye blok sættes ind efter blok ${addAt}.` : 'Den nye blok sættes ind nederst på siden.' }}</p>
                    <ul class="list tl">
                        <li v-for="t in templates" :key="t.id">
                            <span class="tpl" aria-hidden="true">{{ t.icon }}</span>
                            <span class="mtext"><strong>{{ t.name }}</strong><span class="small muted">{{ t.hint }}</span></span>
                            <button class="btn small" @click="addBlock(t)">Tilføj</button>
                        </li>
                    </ul>
                </template>

                <!-- Historik -->
                <template v-else-if="panel === 'historik'">
                    <p class="small muted" style="margin:0 0 8px">Hver gang siden udgives, gemmes en udgave. Hent en ældre udgave ind i editoren, se den, og tryk Udgiv, hvis den skal bruges.</p>
                    <p v-if="!versions" class="muted">Henter …</p>
                    <p v-else-if="!versions.length" class="small muted">Siden er ikke udgivet fra editoren endnu.</p>
                    <ul v-else class="list tl">
                        <li v-for="(v, i) in versions" :key="v.id">
                            <span class="mtext"><strong>{{ datoTid(v.created_at) }}</strong><span class="small muted">{{ v.by || 'Ukendt' }}{{ i === 0 ? ' · den udgivne' : '' }}</span></span>
                            <button class="btn small ghost" @click="loadVersion(v)">Hent</button>
                        </li>
                    </ul>
                </template>

                <!-- Udgiv -->
                <template v-else-if="panel === 'udgiv'">
                    <p v-if="cur?.updated_at" class="small muted" style="margin:0 0 10px">Sidst udgivet {{ datoTid(cur.updated_at) }}{{ cur.by ? ' af ' + cur.by : '' }}.</p>
                    <template v-if="dirty">
                        <p style="margin:0 0 10px">Du har ændringer, der ikke er udgivet endnu.</p>
                        <div class="row" style="gap:8px">
                            <button class="btn primary" :disabled="busy" @click="publish">Udgiv</button>
                            <button class="btn ghost" :disabled="busy" @click="revert">Fortryd alle ændringer</button>
                        </div>
                    </template>
                    <p v-else style="margin:0">Alt er udgivet. Det, du ser, er det, der står på hjemmesiden.</p>
                </template>
            </div>
        </section>
    </div>
</template>
<script setup>
import { ref, reactive, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { onBeforeRouteLeave } from 'vue-router';
import { api, toast } from '../api';
import { isMobile } from '../mobile';
import HelpButton from '../components/HelpButton.vue';
import BlockFields from '../components/site/BlockFields.vue';

const TOOLS = [
    { id: 'blok', label: 'Ret blok' },
    { id: 'tilfoej', label: 'Tilføj blok' },
    { id: 'historik', label: 'Historik' },
    { id: 'udgiv', label: 'Udgiv' },
];
const TITLES = { blok: 'Ret blok', tilfoej: 'Tilføj blok', historik: 'Tidligere udgaver', udgiv: 'Udgiv' };
const TYPE_NAMES = { hero: 'Topbillede', card: 'Priskort', faq: 'Spørgsmål og svar', calendar: 'Kalender' };
const clone = (x) => JSON.parse(JSON.stringify(x));
const datoTid = (s) => new Date(s).toLocaleString('da-DK', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
const newId = () => 'b' + Math.random().toString(36).slice(2, 9);

const pages = ref(null);
const admin = ref(false);
const pageId = ref(null);
const cur = computed(() => pages.value?.find((p) => p.id === pageId.value));
const locked = computed(() => cur.value?.locked);
const blocks = ref(null);
const orig = ref('');
const updatedAt = ref(null);
const options = ref({});
const selectedId = ref(null);
const panel = ref(null);
const title = computed(() => (panel.value === 'blok' && sel.value ? blockName(sel.value) : TITLES[panel.value]));
const device = ref('pc');
const phoneView = computed(() => isMobile.value || device.value === 'phone');
const busy = ref(false);
const versions = ref(null);
const addAt = ref(null);

const sel = computed(() => blocks.value?.find((b) => b.id === selectedId.value) || null);
const selIndex = computed(() => blocks.value?.indexOf(sel.value) ?? -1);
const dirty = computed(() => blocks.value && JSON.stringify(blocks.value) !== orig.value);
function blockName(b) {
    if (b.type === 'columns') {
        const first = (b.cols || []).flat().find((e) => e.t === 'text')?.html?.replace(/<[^>]+>/g, ' ').trim().slice(0, 30);
        return (b.layout === 'two' ? 'To kolonner' : 'Én kolonne') + (first ? ` – ${first}…` : '');
    }
    return TYPE_NAMES[b.type] + (b.heading ? ` – ${b.heading.slice(0, 30)}` : '');
}
// Blokke, en knap kan hoppe ned til
const linkBlocks = computed(() => (blocks.value || []).filter((b) => b.id !== selectedId.value).map((b, i) => ({ id: b.id, label: `${i + 1}. ${blockName(b)}` })));

function toggle(id) {
    panel.value = panel.value === id ? null : id;
    if (id === 'tilfoej') addAt.value = sel.value ? selIndex.value + 1 : null;
    if (panel.value === 'historik') loadVersions();
    if (panel.value) nextTick(clamp);
}

// ---------- indlæsning ----------
async function loadPages() {
    const r = await api('settings/site');
    pages.value = r.pages; admin.value = r.admin;
}
async function openPage(id) {
    pageId.value = id;
    blocks.value = null; selectedId.value = null; versions.value = null; undoStack.value = []; lastScroll = 0; frameReady = false; lastHtml = null;
    if (locked.value) { panel.value = null; return; }
    try {
        const r = await api(`settings/site/${id}`);
        options.value = r.options; admin.value = r.admin;
        start(r.page);
    } catch (e) { toast(e.message, 'error'); }
}
function start(page) {
    blocks.value = clone(page.blocks);
    orig.value = JSON.stringify(blocks.value);
    updatedAt.value = page.updated_at;
    snapshot = orig.value;
    nextTick(() => { fit(); refresh(true); });
}
const discardOk = () => !dirty.value || confirm('Du har ændringer, der ikke er udgivet. Smid dem væk?');
function onPageChange(e) { if (!switchPage(Number(e.target.value))) e.target.value = pageId.value; }
function switchPage(id) {
    if (id === pageId.value) return true;
    if (!discardOk()) return false;
    openPage(id);
    return true;
}

// ---------- ændringer ----------
function moveBlock(d) {
    const i = selIndex.value; const [b] = blocks.value.splice(i, 1); blocks.value.splice(i + d, 0, b);
    nextTick(() => post({ type: 'sh-selected', id: selectedId.value }));
}
function toggleHidden() { if (sel.value.hidden) delete sel.value.hidden; else sel.value.hidden = true; }
function removeBlock() {
    if (!confirm('Slet blokken? (Den kan hentes igen under Historik, indtil siden er udgivet mange gange.)')) return;
    blocks.value.splice(selIndex.value, 1); selectedId.value = null; panel.value = null;
}
const txt = '<h3>Overskrift</h3><p>Skriv teksten her.</p>';
const TEMPLATES = [
    { id: 'tekst', icon: '▭', name: 'Tekst', hint: 'Overskrift og tekst i hele bredden', make: () => ({ type: 'columns', layout: 'one', align: 'left', cols: [[{ t: 'text', html: txt }]] }) },
    { id: 'tekst-midt', icon: '▭', name: 'Tekst og knap i midten', hint: 'Fx en opfordring til at melde sig ind', make: () => ({ type: 'columns', layout: 'one', align: 'center', cols: [[{ t: 'text', html: txt }, { t: 'button', label: 'Meld dig ind', link: 'signup' }]] }) },
    { id: 'billede-tekst', icon: '▣▭', name: 'Billede og tekst', hint: 'Billedet til venstre, tekst og knap til højre', make: () => ({ type: 'columns', layout: 'two', align: 'center', cols: [[{ t: 'image', src: null, alt: '' }], [{ t: 'text', html: txt }, { t: 'button', label: 'Læs mere', link: 'page:/priser-og-medlemskab' }]] }) },
    { id: 'tekst-billede', icon: '▭▣', name: 'Tekst og billede', hint: 'Tekst og knap til venstre, billedet til højre', make: () => ({ type: 'columns', layout: 'two', align: 'center', cols: [[{ t: 'text', html: txt }, { t: 'button', label: 'Læs mere', link: 'page:/priser-og-medlemskab' }], [{ t: 'image', src: null, alt: '' }]] }) },
    { id: 'to-tekster', icon: '▭▭', name: 'To tekstkolonner', hint: 'Overskrift til venstre, tekst til højre', make: () => ({ type: 'columns', layout: 'two', align: 'left', cols: [[{ t: 'text', html: '<h2>Overskrift</h2>' }], [{ t: 'text', html: '<p>Skriv teksten her.</p>' }]] }) },
    { id: 'priskort', icon: '▣■', name: 'Priskort', hint: 'Billede og orange boks med tekst (som på Priser)', make: () => ({ type: 'card', image: null, alt: '', label: '', heading: 'Overskrift', html: '<p>Skriv teksten her.</p>', prices: false, button: null, widths: [3, 5] }), only: 'priser-og-medlemskab' },
    // Kun admin
    { id: 'orange', icon: '■', name: 'Orange blok', hint: 'Tekst og knap på orange baggrund', make: () => ({ type: 'columns', layout: 'one', align: 'center', bg: 'orange', cols: [[{ t: 'text', html: '<h2>Overskrift</h2><p>Skriv teksten her.</p>' }, { t: 'button', label: 'Meld dig ind', link: 'signup' }]] }), admin: true },
    { id: 'hero', icon: '▀', name: 'Topbillede', hint: 'Billede i fuld bredde med overskrift', make: () => ({ type: 'hero', height: 'medium', image: null, kicker: '', heading: 'Overskrift', heading_size: 'h1', heading_color: 'sand', logo: null, button: null }), admin: true },
    { id: 'faq', icon: '☰', name: 'Spørgsmål og svar', hint: 'Spørgsmål, der kan foldes ud', make: () => ({ type: 'faq', label: 'Svar på et par spørgsmål', items: [{ q: 'Spørgsmål', a: '<p>Svar</p>' }] }), admin: true },
];
const templates = computed(() => TEMPLATES.filter((t) => (admin.value || !t.admin) && (admin.value || !t.only || t.only === cur.value?.slug)));
function addBlock(t) {
    const b = { id: newId(), widths: [1, 1], bg: 'sand', tone: 'lilla', ...t.make() };
    const at = addAt.value != null ? Math.min(addAt.value, blocks.value.length) : blocks.value.length;
    blocks.value.splice(at, 0, b);
    selectedId.value = b.id; panel.value = 'blok';
    nextTick(() => setTimeout(() => post({ type: 'sh-selected', id: b.id }), 500));
}

// Fortryd: øjebliksbilleder af siden (højst 60)
const undoStack = ref([]);
let snapshot = '';
let snapTimer = null;
watch(blocks, () => {
    clearTimeout(snapTimer);
    snapTimer = setTimeout(() => {
        const now = JSON.stringify(blocks.value);
        if (blocks.value && now !== snapshot) { undoStack.value.push(snapshot); if (undoStack.value.length > 60) undoStack.value.shift(); snapshot = now; }
    }, 600);
}, { deep: true });
function undo() {
    clearTimeout(snapTimer);
    const prev = undoStack.value.pop();
    if (prev == null) return;
    blocks.value = JSON.parse(prev); snapshot = prev;
    if (!blocks.value.some((b) => b.id === selectedId.value)) { selectedId.value = null; if (panel.value === 'blok') panel.value = null; }
}
const onKey = (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'z' && !e.shiftKey && !e.target.closest?.('input, textarea, [contenteditable]')) { e.preventDefault(); undo(); }
};

async function publish() {
    busy.value = true;
    try {
        const r = await api(`settings/site/${pageId.value}`, { method: 'PUT', body: { blocks: blocks.value, updated_at: updatedAt.value } });
        start(r.page);
        versions.value = null;
        await loadPages();
        toast('Siden er udgivet');
    } catch (e) {
        if (e.status === 409 && confirm(e.message + '\n\nHent den nyeste udgave nu?')) { orig.value = JSON.stringify(blocks.value); openPage(pageId.value); }
        else if (e.status !== 409) toast(e.message, 'error');
    }
    busy.value = false;
}
function revert() {
    if (!confirm('Fortryd alle ændringer, der ikke er udgivet?')) return;
    blocks.value = JSON.parse(orig.value);
    if (!blocks.value.some((b) => b.id === selectedId.value)) { selectedId.value = null; panel.value = 'udgiv'; }
}
async function loadVersions() {
    versions.value = null;
    try { versions.value = (await api(`settings/site/${pageId.value}/versions`)).versions; } catch (e) { toast(e.message, 'error'); }
}
async function loadVersion(v) {
    if (dirty.value && !confirm('Dine ændringer, der ikke er udgivet, erstattes af den gamle udgave. Fortsæt?')) return;
    try {
        blocks.value = (await api(`settings/site/${pageId.value}/versions/${v.id}`)).blocks;
        selectedId.value = null;
        toast('Udgaven er hentet. Tryk Udgiv, hvis den skal bruges.');
    } catch (e) { toast(e.message, 'error'); }
}

// ---------- forhåndsvisning ----------
// Siden dannes på serveren (samme skabeloner som hjemmesiden). Første gang hele siden, derefter kun indholdet.
const frame = ref(null);
let frameReady = false; let lastHtml = null; let lastScroll = 0; let reqNo = 0; let refreshTimer = null;
const post = (m) => frame.value?.contentWindow?.postMessage(m, '*');
async function refresh(full = false) {
    if (!blocks.value || locked.value) return;
    const no = ++reqNo;
    try {
        const { html } = await api(`settings/site/${pageId.value}/preview`, { method: 'POST', body: { blocks: blocks.value } });
        if (no !== reqNo) return; // en nyere forespørgsel er på vej
        show(html, full);
    } catch (e) { if (no === reqNo) toast(e.message, 'error'); }
}
function show(html, full) {
    lastHtml = html;
    // Kalenderen fyldes af et script, der kun kører, når hele siden indlæses
    if (full || !frameReady || html.includes('data-klub="kalender"')) { frameReady = false; if (frame.value) frame.value.srcdoc = html; return; }
    const main = new DOMParser().parseFromString(html, 'text/html').getElementById('indhold')?.innerHTML ?? '';
    post({ type: 'sh-main', html: main, selected: selectedId.value });
}
watch(blocks, () => { clearTimeout(refreshTimer); refreshTimer = setTimeout(() => refresh(), 350); }, { deep: true });
watch(device, () => { frameReady = false; lastScroll = 0; nextTick(() => { fit(); if (lastHtml && frame.value) frame.value.srcdoc = lastHtml; }); });
function frameLoaded() { /* sh-ready kommer fra iframen */ }
function onMessage(e) {
    if (!frame.value || e.source !== frame.value.contentWindow || !e.data) return;
    const d = e.data;
    if (d.type === 'sh-ready') { frameReady = true; post({ type: 'sh-state', selected: selectedId.value, scroll: lastScroll }); }
    else if (d.type === 'sh-scroll') lastScroll = d.y;
    else if (d.type === 'sh-select') { selectedId.value = d.id; panel.value = 'blok'; nextTick(clamp); }
}
watch(selectedId, (id) => post({ type: 'sh-selected', id }));

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

// ---------- flydende værktøjsboks (som System → Design) ----------
const float = ref(null);
const POS_KEY = 'hjemmeside-vaerktoej-pos';
const vh = ref(window.innerHeight);
const pos = reactive((() => { try { const p = JSON.parse(localStorage.getItem(POS_KEY)); if (p?.x >= 0) return p; } catch {} return { x: window.innerWidth - 420, y: 180 }; })());
function clamp() {
    const el = float.value; if (!el || isMobile.value) return;
    const w = el.offsetWidth, h = el.querySelector('.fhead')?.offsetHeight || 44;
    pos.x = Math.round(Math.min(Math.max(8, pos.x), window.innerWidth - w - 8));
    pos.y = Math.round(Math.min(Math.max(8, pos.y), window.innerHeight - h - 8));
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
    window.addEventListener('message', onMessage);
    window.addEventListener('keydown', onKey);
    try {
        await loadPages();
        const first = pages.value.find((p) => !p.locked);
        if (first) await openPage(first.id);
    } catch (e) { toast(e.message, 'error'); }
    await nextTick();
    if (stage.value) ro.observe(stage.value);
    fit();
});
onUnmounted(() => {
    ro.disconnect();
    window.removeEventListener('beforeunload', beforeUnload); window.removeEventListener('resize', onResize);
    window.removeEventListener('message', onMessage); window.removeEventListener('keydown', onKey);
    document.body.classList.remove('dragging');
});
</script>
<style scoped>
.toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; padding: 8px; margin-bottom: 12px; position: sticky; top: calc(env(safe-area-inset-top, 0px) + 8px); z-index: 30; }
.tools { display: flex; gap: 4px; overflow-x: auto; scrollbar-width: none; max-width: 100%; align-items: center; }
.tool { position: relative; border: 0; background: none; font: inherit; font-weight: 600; font-size: .92rem; color: var(--ink-2); padding: 9px 14px; border-radius: 10px; cursor: pointer; white-space: nowrap; display: inline-flex; align-items: center; gap: 6px; }
.tool:hover:not(:disabled) { background: var(--surface-2); color: var(--ink); }
.tool.on { background: var(--accent-soft); color: var(--ink); box-shadow: inset 0 -2px 0 var(--accent); }
.tool:disabled { opacity: .4; cursor: default; }
.tool .udot { flex: none; width: 8px; height: 8px; border-radius: 50%; background: var(--warn); }
.view { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.seg { margin: 0; padding: 3px; flex: none; }
.seg button { padding: 6px 10px; font-size: .85rem; }
.pagesel { width: auto; min-height: 36px; padding: 5px 10px; font-size: .88rem; font-weight: 600; margin-right: 4px; }
.stage { min-width: 0; }
.empty { max-width: 560px; }
.clip { overflow: hidden; border: 1px solid var(--line); border-radius: 12px; background: var(--bg); box-shadow: var(--shadow); }
.clip.phone { margin: 0 auto; }
.clip iframe { border: 0; transform-origin: 0 0; display: block; }

.float { position: fixed; z-index: 35; width: 400px; max-width: calc(100vw - 16px); max-height: calc(100vh - 24px); display: flex; flex-direction: column;
    background: var(--surface); color: var(--ink); border: 1px solid var(--line); border-radius: 14px; box-shadow: 0 12px 40px rgba(0,0,0,.25), 0 2px 6px rgba(0,0,0,.12); }
.fhead { display: flex; align-items: center; gap: 8px; padding: 10px 10px 10px 12px; border-bottom: 1px solid var(--line); cursor: grab; user-select: none; touch-action: none; }
.fhead strong { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.grip { color: var(--ink-3); font-size: 1.1rem; line-height: 1; }
.fx { border: 0; background: none; font-size: 1.4rem; line-height: 1; color: var(--ink-2); cursor: pointer; padding: 2px 8px; border-radius: 8px; }
.fx:hover { background: var(--surface-2); }
.fbody { padding: 12px 14px 16px; overflow-y: auto; overscroll-behavior: contain; }
.float.sheet { left: 8px; right: 8px; width: auto; bottom: calc(env(safe-area-inset-bottom, 0px) + 70px); max-height: 60vh; }
.float.sheet .fhead { cursor: default; }
.float.sheet .grip { display: none; }
.bhead { display: flex; flex-direction: column; gap: 6px; margin-bottom: 12px; padding-bottom: 10px; border-bottom: 1px solid var(--line); }
.tl li { display: flex; align-items: center; gap: 10px; }
.tl .mtext { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.tl .btn { flex: none; }
.tpl { width: 40px; text-align: center; font-size: 1.1rem; color: var(--ink-3); letter-spacing: -2px; flex: none; }
</style>
