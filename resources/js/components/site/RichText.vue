<template>
    <div class="rt" :class="{ focus }">
        <div class="rt-bar" role="toolbar" :aria-label="'Formatering' + (label ? ' af ' + label : '')">
            <select class="rt-fmt" :value="fmt" aria-label="Afsnit eller overskrift" @mousedown="save" @change="format($event.target.value)">
                <option value="p">Brødtekst</option>
                <option value="h2">Stor overskrift</option>
                <option value="h3">Overskrift</option>
                <option value="h4">Lille overskrift</option>
            </select>
            <button type="button" title="Fed" @mousedown.prevent="cmd('bold')"><b>F</b></button>
            <button type="button" title="Kursiv" @mousedown.prevent="cmd('italic')"><i>K</i></button>
            <button type="button" title="Punktliste" @mousedown.prevent="cmd('insertUnorderedList')">• Liste</button>
            <button type="button" title="Link" @mousedown.prevent="openLink">Link</button>
            <button v-if="prices?.length" type="button" title="Indsæt en pris fra Pakker" @mousedown.prevent="openPrice">Pris</button>
        </div>
        <div v-if="pop" class="rt-pop">
            <template v-if="pop === 'link'">
                <select v-model="link.kind" aria-label="Hvor fører linket hen?">
                    <option v-for="o in pages" :key="o.value" :value="o.value">{{ o.label }}</option>
                    <option value="url">Anden hjemmeside …</option>
                    <option value="mail">E-mail …</option>
                    <option value="none">Fjern linket</option>
                </select>
                <input v-if="link.kind === 'url'" v-model.trim="link.url" type="url" placeholder="https://…" aria-label="Adresse">
                <input v-if="link.kind === 'mail'" v-model.trim="link.mail" type="email" placeholder="navn@eksempel.dk" aria-label="E-mail">
            </template>
            <select v-else v-model="price" aria-label="Pris">
                <option v-for="p in prices" :key="p.id" :value="p.id">{{ p.label }} – {{ p.price }}</option>
            </select>
            <div class="row" style="gap:6px">
                <button type="button" class="btn small primary" @click="pop === 'link' ? applyLink() : applyPrice()">OK</button>
                <button type="button" class="btn small ghost" @click="pop = null">Annuller</button>
            </div>
            <p v-if="pop === 'pris'" class="small muted" style="margin:0">Prisen følger pakken, så den altid er den aktuelle.</p>
        </div>
        <div ref="ed" class="rt-ed" contenteditable="true" role="textbox" aria-multiline="true" :aria-label="label || 'Tekst'"
            @input="changed" @blur="focus = false; changed()" @focus="focus = true" @keyup="track" @mouseup="track" @paste="paste"></div>
    </div>
</template>
<script setup>
import { ref, reactive, onMounted, watch, computed } from 'vue';

/** Enkel tekstredigering: overskrifter, fed, kursiv, lister, links og priser. Serveren renser altid teksten (App\Support\Site::html). */
const props = defineProps({ modelValue: { type: String, default: '' }, options: Object, prices: Array, label: String });
const emit = defineEmits(['update:modelValue']);
const ed = ref(null);
const focus = ref(false);
const fmt = ref('p');
const pop = ref(null);
let last = null;
let range = null;

onMounted(() => { ed.value.innerHTML = props.modelValue || '<p><br></p>'; last = props.modelValue; });
// Udefra (fortryd, hentet udgave): sæt teksten, hvis den er en anden
watch(() => props.modelValue, (v) => { if (v !== last && ed.value) { ed.value.innerHTML = v || '<p><br></p>'; last = v; } });

function changed() {
    let html = ed.value.innerHTML;
    if (html === '<p><br></p>' || html === '<br>') html = '';
    if (html !== last) { last = html; emit('update:modelValue', html); }
}
function track() {
    const s = window.getSelection();
    let n = s?.anchorNode;
    while (n && n !== ed.value) { if (n.nodeType === 1 && /^(H2|H3|H4|P)$/.test(n.tagName)) { fmt.value = n.tagName.toLowerCase(); return; } n = n.parentNode; }
    fmt.value = 'p';
}
function save() {
    const s = window.getSelection();
    if (s?.rangeCount && ed.value.contains(s.anchorNode)) range = s.getRangeAt(0).cloneRange();
}
function restore() {
    ed.value.focus();
    if (range) { const s = window.getSelection(); s.removeAllRanges(); s.addRange(range); }
}
function cmd(c, v = null) {
    if (!ed.value.contains(window.getSelection()?.anchorNode)) ed.value.focus();
    document.execCommand('styleWithCSS', false, false);
    document.execCommand(c, false, v);
    changed();
}
function format(tag) { restore(); cmd('formatBlock', `<${tag}>`); fmt.value = tag; }
function paste(e) {
    // Kun ren tekst (formatering fra Word og hjemmesider gør siden rodet)
    e.preventDefault();
    const t = (e.clipboardData || window.clipboardData).getData('text/plain');
    document.execCommand('insertText', false, t);
}

// ---------- links ----------
const HREF = { signup: '/bliv-medlem' };
const esc = (t) => String(t).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
const pages = computed(() => (props.options?.pages || []).map((o) => ({ value: HREF[o.value] || o.value.replace(/^page:/, ''), label: o.label })));
const link = reactive({ kind: '', url: 'https://', mail: '' });
function openLink() {
    save();
    const a = window.getSelection()?.anchorNode?.parentElement?.closest('a');
    const h = a?.getAttribute('href') || '';
    link.kind = h.startsWith('https://') ? 'url' : h.startsWith('mailto:') ? 'mail' : pages.value.some((p) => p.value === h) ? h : pages.value[0]?.value || 'url';
    link.url = h.startsWith('https://') ? h : 'https://';
    link.mail = h.startsWith('mailto:') ? h.slice(7) : '';
    pop.value = 'link';
}
function applyLink() {
    restore();
    if (link.kind === 'none') cmd('unlink');
    else {
        const href = link.kind === 'url' ? link.url : link.kind === 'mail' ? 'mailto:' + link.mail : link.kind;
        if (link.kind === 'url' && !/^https:\/\/\S+\.\S+/.test(href)) return alert('Adressen skal starte med https://');
        if (window.getSelection()?.isCollapsed) {
            // Ingen tekst markeret: indsæt linket med sidens navn/adressen som tekst
            const text = link.kind === 'mail' ? link.mail : link.kind === 'url' ? href.replace(/^https:\/\//, '') : pages.value.find((p) => p.value === href)?.label || href;
            cmd('insertHTML', `<a href="${esc(href)}">${esc(text)}</a>&nbsp;`);
        } else cmd('createLink', href);
    }
    pop.value = null;
}

// ---------- priser ----------
const price = ref(null);
function openPrice() { save(); price.value = props.prices[0]?.id; pop.value = 'pris'; }
function applyPrice() {
    const p = props.prices.find((x) => x.id === price.value);
    if (p) { restore(); cmd('insertHTML', `<span data-pris="${p.id}">${p.price}</span>&nbsp;`); }
    pop.value = null;
}
</script>
<style scoped>
.rt { border: 1px solid var(--line); border-radius: 10px; background: var(--surface); overflow: hidden; }
.rt.focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-soft); }
.rt-bar { display: flex; flex-wrap: wrap; gap: 4px; padding: 6px; border-bottom: 1px solid var(--line); background: var(--surface-2); }
.rt-bar button { border: 1px solid transparent; background: none; font: inherit; font-size: .82rem; padding: 4px 8px; border-radius: 6px; cursor: pointer; color: var(--ink); }
.rt-bar button:hover { background: var(--surface); border-color: var(--line); }
.rt-fmt { width: auto; min-height: 30px; padding: 2px 8px; font-size: .82rem; }
.rt-pop { display: flex; flex-direction: column; gap: 6px; padding: 8px; border-bottom: 1px solid var(--line); background: var(--accent-soft); }
.rt-ed { padding: 8px 10px; min-height: 90px; max-height: 340px; overflow-y: auto; outline: none; font-size: .9rem; line-height: 1.55; }
.rt-ed :deep(h2), .rt-ed :deep(h3), .rt-ed :deep(h4) { text-transform: none; letter-spacing: normal; color: inherit; }
.rt-ed :deep(h2) { font-size: 1.3rem; margin: .3em 0; }
.rt-ed :deep(h3) { font-size: 1.12rem; margin: .3em 0; }
.rt-ed :deep(h4) { font-size: .98rem; margin: .3em 0; }
.rt-ed :deep(p) { margin: 0 0 .5em; }
.rt-ed :deep(ul) { margin: 0 0 .5em; padding-left: 1.2em; }
.rt-ed :deep(a) { color: var(--accent); text-decoration: underline; }
.rt-ed :deep(span[data-pris]) { background: var(--sand, #f4e6d4); border-radius: 4px; padding: 0 3px; }
</style>
