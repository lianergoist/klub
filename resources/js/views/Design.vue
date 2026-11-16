<template>
    <div>
        <div class="topbar">
            <h1>Design <HelpButton topic="design" /></h1>
            <RouterLink to="/indstillinger" class="btn small ghost">← Indstillinger</RouterLink>
        </div>
        <p class="muted" style="margin:0 0 14px">Ret farver, logo, skrift og hjørner i klubbens tema (SurfHorsens). Siderne beholder deres opbygning, så de virker på både mobil og pc. Ændringerne gemmes som kladde, og ingen andre ser dem, før du trykker <strong>Udgiv</strong>.</p>
        <div v-if="!d" class="spinner"></div>
        <div v-else class="dz">
            <div class="ctl">
                <div class="card status">
                    <div v-if="dirty"><span class="tag info">Kladde</span> <span class="small muted">Ikke udgivet{{ saving ? ' · gemmer…' : savedAt ? ' · gemt' : '' }}</span></div>
                    <div v-else class="small muted">Kladden er det samme som det udgivne design{{ pub ? '' : ' (standarddesignet)' }}.</div>
                    <div class="row" style="gap:8px;margin-top:10px">
                        <button class="btn primary" :disabled="!dirty || busy" @click="publish">Udgiv</button>
                        <button class="btn ghost" :disabled="!dirty || busy" @click="discard">Fortryd ændringerne</button>
                    </div>
                </div>

                <div class="card">
                    <div class="tabs" style="margin-bottom:10px">
                        <button :class="{ on: mode === 'light' }" @click="mode = 'light'">Lys udgave</button>
                        <button :class="{ on: mode === 'dark' }" @click="mode = 'dark'">Mørk udgave</button>
                    </div>
                    <p class="small muted" style="margin:0 0 10px">{{ mode === 'light' ? 'Bruges, når telefonen eller computeren står på lys tilstand.' : 'Bruges, når telefonen eller computeren står på mørk tilstand (fx om aftenen).' }}</p>
                    <ul class="list colors">
                        <li v-for="[k, label, hint] in COLOR_FIELDS" :key="k">
                            <input type="color" v-model="d[mode][k]" :aria-label="label">
                            <span class="mtext"><strong>{{ label }}</strong><span v-if="hint" class="small muted">{{ hint }}</span></span>
                            <input class="hex" :value="d[mode][k]" maxlength="7" spellcheck="false" @change="setHex(k, $event)" :aria-label="label + ' som kode'">
                            <button v-if="d[mode][k] !== DEFAULTS[mode][k]" class="btn ghost small" title="Tilbage til standardfarven" @click="d[mode][k] = DEFAULTS[mode][k]">↺</button>
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
                        <div class="lg" style="background:var(--surface)"><img :src="logoUrl || DEFAULT_LOGO" alt="Logo"></div>
                        <div class="lg" :style="{ background: d[mode].brand }"><img :src="logoUrl || DEFAULT_LOGO" alt="Logo i menuen"></div>
                    </div>
                    <p class="small muted" style="margin:8px 0">PNG med gennemsigtig baggrund er bedst. Logoet vises altid i samme bredde, så det ikke kan skubbe siden.</p>
                    <div class="row" style="gap:8px">
                        <label class="btn small"><input type="file" accept="image/png,image/jpeg,image/webp" hidden @change="uploadLogo"> {{ uploading ? 'Sender…' : 'Vælg nyt logo' }}</label>
                        <button v-if="d.logo" class="btn ghost small" @click="d.logo = null; logoUrl = null">Brug klubbens standardlogo</button>
                    </div>
                </div>

                <div class="card">
                    <h2>Skrift og hjørner</h2>
                    <div class="chips" style="margin-bottom:14px">
                        <button v-for="(label, k) in FONTS" :key="k" :class="['chip', { on: d.font === k }]" @click="d.font = k">{{ d.font === k ? '✓ ' : '' }}{{ label }}</button>
                    </div>
                    <label class="field"><span>Runde hjørner: {{ d.radius }} px{{ d.radius === DEFAULTS.radius ? ' (standard)' : '' }}</span>
                        <input type="range" min="0" max="20" step="1" v-model.number="d.radius"></label>
                    <div class="row" style="gap:8px;margin-top:6px"><span class="rdemo" :style="{ borderRadius: d.radius + 'px' }">Kort</span><span class="rdemo" :style="{ borderRadius: Math.min(d.radius, 12) + 'px', background: d[mode].accent, borderColor: d[mode].accent, color: inkOn(d[mode].accent) }">Knap</span></div>
                </div>

                <div class="card">
                    <h2>Tidligere versioner</h2>
                    <p class="small muted" style="margin:0 0 8px">Hent en udgivet version ind i kladden for at se den igen. Den bliver først aktiv, når du udgiver.</p>
                    <ul class="list small">
                        <li class="row between"><span>Standarddesignet</span><button class="btn small ghost" @click="load(clone(DEFAULTS), null)">Hent</button></li>
                        <li v-for="(h, i) in history" :key="h.id" class="row between">
                            <span>{{ datoTid(h.at) }}<span v-if="h.by" class="muted"> · {{ h.by }}</span><span v-if="i === 0" class="tag ok" style="margin-left:6px">Udgivet nu</span></span>
                            <button class="btn small ghost" @click="load(h.data, h.logo_url)">Hent</button>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="pv">
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
                    <p class="small muted" style="margin:0 0 10px">{{ mode === 'light' ? 'Lys' : 'Mørk' }} udgave. Forhåndsvisningen er de rigtige sider med kladden. Du kan trykke rundt i dem, men intet bliver gemt derinde.</p>
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
    </div>
</template>
<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { api, toast, ApiError } from '../api';
import { DEFAULTS, DEFAULT_LOGO, COLOR_FIELDS, FONTS, checks, refreshDesign, inkOn } from '../design';
import { isMobile } from '../mobile';
import HelpButton from '../components/HelpButton.vue';

const PAGES = { '/aktiviteter': 'Aktiviteter', '/opslag': 'Opslag', '/profil': 'Profil', '/mere': 'Mere' };
const clone = (x) => JSON.parse(JSON.stringify(x));
const datoTid = (s) => new Date(s).toLocaleString('da-DK', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });

const d = ref(null); const logoUrl = ref(null);
const pub = ref(null); const history = ref([]);
const mode = ref(window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
const page = ref('/aktiviteter');
const device = ref('pc');
const busy = ref(false); const saving = ref(false); const savedAt = ref(null); const uploading = ref(false);
const src = computed(() => `${page.value}?designvisning=${mode.value === 'dark' ? 'mork' : 'lys'}`);

const base = computed(() => pub.value?.data || DEFAULTS);
const dirty = computed(() => d.value && JSON.stringify(norm(d.value)) !== JSON.stringify(norm(base.value)));
// Sammenlign uafhængigt af nøglernes rækkefølge (databasen sorterer dem)
const pick = (c) => COLOR_FIELDS.map(([k]) => c?.[k]?.toLowerCase());
const norm = (x) => [pick(x.light), pick(x.dark), Number(x.radius), x.font, x.logo || null];
const bad = computed(() => (d.value ? checks(d.value).filter((c) => !c.ok) : []));

function load(data, url) {
    d.value = clone({ ...DEFAULTS, ...data, light: { ...DEFAULTS.light, ...data.light }, dark: { ...DEFAULTS.dark, ...data.dark } });
    logoUrl.value = url || null;
}

async function fetchAll() {
    const r = await api('settings/design');
    pub.value = r.published; history.value = r.history;
    const start = r.draft || r.published;
    loaded = false;
    load(start?.data || DEFAULTS, start?.logo_url);
    await nextTick(); loaded = true;
}

function setHex(k, e) {
    let v = e.target.value.trim().toLowerCase();
    if (/^[0-9a-f]{6}$/.test(v)) v = '#' + v;
    if (/^#[0-9a-f]{6}$/.test(v)) d.value[mode.value][k] = v; else { e.target.value = d.value[mode.value][k]; toast('Skriv farven som #rrggbb, fx #7e5a8c', 'error'); }
}

// Forhåndsvisningen opdateres med det samme; kladden gemmes kort efter sidste ændring
let loaded = false; let t = null;
function payload() { return { type: 'klub-design', data: clone(d.value), logo_url: logoUrl.value }; }
const phoneF = ref(null); const pcF = ref(null);
function send() {
    const p = payload();
    window.__klubDesignDraft = p; // læses af iframen, når den indlæses
    for (const f of [phoneF.value, pcF.value]) f?.contentWindow?.postMessage(p, location.origin);
}
watch(d, () => {
    if (!d.value) return;
    send();
    if (!loaded) return;
    clearTimeout(t); savedAt.value = null;
    t = setTimeout(saveDraft, 700);
}, { deep: true });
watch(logoUrl, send);
watch(device, () => nextTick(fit));

async function saveDraft() {
    saving.value = true;
    try {
        if (dirty.value) await api('settings/design/draft', { method: 'PUT', body: { data: d.value } });
        else await api('settings/design/draft', { method: 'DELETE' });
        savedAt.value = Date.now();
    } catch (e) { toast(e.message, 'error'); }
    saving.value = false;
}

async function uploadLogo(e) {
    const file = e.target.files[0]; e.target.value = '';
    if (!file) return;
    uploading.value = true;
    try {
        const form = new FormData(); form.append('logo', file);
        const r = await api('settings/design/logo', { method: 'POST', form });
        logoUrl.value = r.logo_url; d.value.logo = r.logo;
    } catch (err) { toast(err.message, 'error'); }
    uploading.value = false;
}

async function publish() {
    if (bad.value.length && !confirm(`${bad.value.length} ${bad.value.length === 1 ? 'tekst er' : 'tekster er'} svære at læse (se "Kan man læse det?"). Udgive alligevel?`)) return;
    if (!bad.value.length && !confirm('Udgiv designet? Alle medlemmer ser det næste gang, de åbner appen eller siden.')) return;
    busy.value = true; clearTimeout(t);
    try {
        await api('settings/design/publish', { method: 'POST', body: { data: d.value } });
        await refreshDesign(true); // dit eget skærmbillede skifter med det samme
        await fetchAll();
        toast('Designet er udgivet');
    } catch (e) { if (!(e instanceof ApiError && e.status === 0)) toast(e.message, 'error'); }
    busy.value = false;
}

async function discard() {
    if (!confirm('Smid kladden væk og gå tilbage til det udgivne design?')) return;
    clearTimeout(t);
    try { await api('settings/design/draft', { method: 'DELETE' }); await fetchAll(); toast('Kladden er smidt væk'); } catch (e) { toast(e.message, 'error'); }
}

// Skalér iframes, så de passer i bredden
const phoneBox = ref(null); const pcBox = ref(null); const sPhone = ref(0.8); const sPc = ref(0.5);
let ro = null;
function fit() {
    if (phoneBox.value) sPhone.value = Math.min(1, phoneBox.value.clientWidth / 390);
    if (pcBox.value) sPc.value = Math.min(1, pcBox.value.clientWidth / 1280);
}
onMounted(async () => {
    try { await fetchAll(); } catch (e) { toast(e.message, 'error'); }
    await nextTick();
    ro = new ResizeObserver(fit);
    for (const el of [phoneBox.value, pcBox.value]) el && ro.observe(el);
    fit();
});
onUnmounted(() => { ro?.disconnect(); delete window.__klubDesignDraft; if (t) { clearTimeout(t); saveDraft(); } });
</script>
<style scoped>
.dz { display: grid; gap: 14px; grid-template-columns: minmax(0, 1fr); }
@media (min-width: 1000px) { .dz { grid-template-columns: 380px minmax(0, 1fr); align-items: start; } .pv { position: sticky; top: 14px; } }
.ctl > .card + .card { margin-top: 14px; }
.colors li { display: flex; align-items: center; gap: 10px; }
.colors input[type=color] { width: 44px; height: 36px; padding: 2px; flex: none; border-radius: 8px; cursor: pointer; }
.colors .mtext { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.colors .hex { width: 84px; flex: none; font-family: ui-monospace, monospace; font-size: .85rem; padding: 6px 8px; }
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
