<template>
    <div class="feed">
        <h1 style="margin-bottom:14px">Opslag</h1>

        <form class="card composer" @submit.prevent="post">
            <div class="row" style="flex-wrap:nowrap;align-items:flex-start">
                <span class="avatar">{{ initials(store.user.name) }}</span>
                <textarea v-model="body" rows="2" placeholder="Del en oplevelse, et billede fra vandet eller et spørgsmål …" maxlength="3000" @focus="open = true"></textarea>
            </div>
            <div v-if="previews.length" class="thumbs">
                <div v-for="(pv, i) in previews" :key="pv" class="thumb"><img :src="pv" alt=""><button type="button" aria-label="Fjern billede" @click="removeImage(i)"><Icon name="x" /></button></div>
            </div>
            <div v-if="video" class="thumbs"><div class="thumb vid"><video :src="videoPreview + '#t=0.1'" muted playsinline preload="metadata"></video><span class="vsize">{{ mb(video.size) }}</span><button type="button" aria-label="Fjern video" @click="removeVideo"><Icon name="x" /></button></div></div>
            <label v-if="files.length || video" class="check small" style="margin-top:10px"><input type="checkbox" v-model="consent"> Personerne på {{ video && files.length ? 'billederne og videoen' : video ? 'videoen' : 'billederne' }} er okay med, at de bliver vist for klubbens medlemmer.</label>
            <div v-if="busy && progress !== null" class="prog" :aria-label="`Uploader ${progress} %`"><i :style="{ width: progress + '%' }"></i></div>
            <div v-if="open || files.length || video" class="row between" style="margin-top:10px">
                <div class="row" style="gap:4px">
                    <label class="btn ghost small" :class="{ disabled: files.length >= MAX_IMAGES }"><Icon name="image" style="width:18px" /> Billeder<input type="file" accept="image/jpeg,image/png,image/webp" multiple hidden :disabled="files.length >= MAX_IMAGES" @change="addImages"></label>
                    <label class="btn ghost small" :class="{ disabled: video }"><Icon name="play" style="width:18px" /> Video<input type="file" accept="video/mp4,video/quicktime,video/webm,video/*" hidden :disabled="!!video" @change="addVideo"></label>
                </div>
                <button class="btn primary small" :disabled="busy || (!body.trim() && !files.length && !video)">{{ busy ? (progress !== null && progress < 100 ? `Uploader ${progress} %` : 'Deler …') : 'Del' }}</button>
            </div>
        </form>

        <div v-if="isMod" class="chips" style="margin-bottom:12px">
            <button :class="['chip', { on: filter === 'all' }]" @click="setFilter('all')">Alle opslag</button>
            <button :class="['chip', { on: filter === 'reported' }]" @click="setFilter('reported')"><Icon name="flag" style="width:14px" /> Anmeldte<span v-if="openReports" class="tag bad" style="margin-left:2px">{{ openReports }}</span></button>
            <button :class="['chip', { on: filter === 'hidden' }]" @click="setFilter('hidden')"><Icon name="eyeoff" style="width:14px" /> Skjulte</button>
        </div>

        <div v-if="posts === null" class="spinner"></div>
        <div v-else-if="!posts.length" class="card empty">{{ filter === 'all' ? 'Ingen opslag endnu. Del det første!' : 'Intet at moderere. 🎉' }}</div>

        <article v-for="p in posts || []" :key="p.id" :class="['card post', { hidden: p.hidden }]">
            <div v-if="p.hidden" class="banner warn small" style="margin:-4px 0 12px">
                <Icon name="eyeoff" style="width:14px;vertical-align:-2px" /> Skjult for andre medlemmer<span v-if="p.hidden_reason">: {{ p.hidden_reason }}</span>
            </div>
            <div v-if="isMod && p.open_reports" class="banner bad small" style="margin:-4px 0 12px">
                <strong>Anmeldt ({{ p.open_reports }})</strong>
                <ul style="margin:4px 0 8px;padding-left:18px"><li v-for="(r, i) in p.report_reasons" :key="i">{{ r }}</li></ul>
                <div class="row"><button class="btn small" @click="askHide(p)">Skjul opslag</button><button class="btn small ghost" @click="dismiss(p)">Afvis anmeldelse</button></div>
            </div>

            <header class="row" style="flex-wrap:nowrap">
                <span class="avatar">{{ initials(p.author) }}</span>
                <div style="flex:1;min-width:0"><strong>{{ p.author }}</strong><div class="small muted">{{ ago(p.created_at) }}</div></div>
                <button v-if="p.can_message" class="btn ghost small" title="Send besked" :aria-label="`Send besked til ${p.author}`" @click="messageTo(p.author_id)"><Icon name="chat" style="width:16px" /> <span class="small">Skriv</span></button>
                <div class="row" style="gap:2px">
                    <button v-if="p.mine" class="btn ghost small" title="Slet" aria-label="Slet opslag" @click="remove(p)"><Icon name="trash" style="width:16px" /></button>
                    <button v-if="isMod && !p.hidden" class="btn ghost small" title="Skjul" aria-label="Skjul opslag" @click="askHide(p)"><Icon name="eyeoff" style="width:16px" /></button>
                    <button v-if="isMod && p.hidden" class="btn ghost small" title="Vis igen" aria-label="Vis opslag igen" @click="unhide(p)"><Icon name="eye" style="width:16px" /></button>
                    <button v-if="!p.mine && !isMod" class="btn ghost small" :title="p.reported_by_me ? 'Anmeldt' : 'Anmeld'" aria-label="Anmeld opslag" :disabled="p.reported_by_me" @click="reportPost = p"><Icon name="flag" style="width:16px" /></button>
                </div>
            </header>

            <p v-if="p.body" class="body">{{ p.body }}</p>
            <div v-for="(v, i) in p.images.filter(x => x.kind === 'video')" :key="'v' + i" class="gallery">
                <video v-if="v.status === 'ready'" class="vplay" :src="v.url" :poster="v.poster" :width="v.w" :height="v.h" controls playsinline preload="none"></video>
                <div v-else class="vwait small">{{ v.status === 'failed' ? 'Videoen kunne ikke behandles.' : 'Videoen behandles … Det tager typisk et minut eller to.' }}</div>
            </div>
            <div v-if="pics(p).length" :class="['gallery', 'n' + Math.min(pics(p).length, 5)]">
                <button v-for="(img, i) in pics(p)" :key="i" type="button" @click="lightbox = img.url"><img :src="img.url" :width="img.w" :height="img.h" loading="lazy" alt="Billede fra opslag"></button>
            </div>

            <footer class="row" style="gap:4px;margin-top:10px">
                <button :class="['btn ghost small like', { on: p.liked }]" :aria-pressed="p.liked" @click="like(p)"><Icon name="heart" style="width:18px" /> {{ p.likes || '' }}</button>
                <button class="btn ghost small" @click="p.showComments = !p.showComments"><Icon name="chat" style="width:18px" /> <span class="small">{{ !p.comments.length ? 'Kommentér' : p.comments.length === 1 ? '1 kommentar' : p.comments.length + ' kommentarer' }}</span></button>
            </footer>

            <div v-if="p.showComments || p.comments.length && p.comments.length <= 2" class="comments">
                <div v-for="c in p.comments" :key="c.id" :class="['comment', { hidden: c.hidden }]">
                    <span class="avatar sm">{{ initials(c.author) }}</span>
                    <div class="bubble">
                        <strong class="small">{{ c.author }}</strong> <span class="small muted">{{ ago(c.created_at) }}</span>
                        <div>{{ c.body }}</div>
                        <div v-if="c.hidden" class="small" style="color:var(--warn)">Skjult af moderator</div>
                    </div>
                    <button v-if="c.mine" class="btn ghost small" aria-label="Slet kommentar" @click="removeComment(c)"><Icon name="trash" style="width:14px" /></button>
                    <button v-else-if="isMod" class="btn ghost small" :aria-label="c.hidden ? 'Vis kommentar' : 'Skjul kommentar'" @click="modComment(c)"><Icon :name="c.hidden ? 'eye' : 'eyeoff'" style="width:14px" /></button>
                </div>
                <form v-if="!p.hidden" class="row" style="flex-wrap:nowrap;margin-top:8px" @submit.prevent="comment(p)">
                    <input v-model="p.draft" placeholder="Skriv en kommentar …" maxlength="1000">
                    <button class="btn small" :disabled="!p.draft?.trim()">Send</button>
                </form>
            </div>
        </article>

        <button v-if="more" class="btn block" @click="load(true)">Vis ældre opslag</button>

        <Modal v-if="hidePost" title="Skjul opslag" @close="hidePost = null">
            <p>Opslaget bliver skjult for andre medlemmer. Forfatteren får besked med din begrundelse.</p>
            <label class="field"><span>Begrundelse</span><input v-model="hideReason" placeholder="Fx billede uden samtykke, usagligt indhold"></label>
            <button class="btn primary block" style="margin-top:12px" :disabled="!hideReason.trim()" @click="hide">Skjul</button>
        </Modal>
        <Modal v-if="reportPost" title="Anmeld opslag" @close="reportPost = null">
            <p>Trænere og bestyrelse får besked og ser på det. Den, der har skrevet opslaget, kan ikke se, hvem der har anmeldt det.</p>
            <label class="field"><span>Hvad er problemet?</span><input v-model="reportReason" placeholder="Fx jeg er på billedet og vil ikke vises"></label>
            <button class="btn primary block" style="margin-top:12px" :disabled="!reportReason.trim()" @click="report">Anmeld</button>
        </Modal>
        <div v-if="lightbox" class="lightbox" @click="lightbox = null"><img :src="lightbox" alt=""></div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Icon from '../components/Icon.vue';
import Modal from '../components/Modal.vue';
import { api, upload, store, toast } from '../api';

const route = useRoute(); const router = useRouter();
async function messageTo(id) { try { const r = await api('messages/start', { method: 'POST', body: { user_ids: [id] } }); router.push(`/beskeder/${r.id}`); } catch (e) { toast(e.message, 'error'); } }
const posts = ref(null); const more = ref(false); const isMod = ref(false); const openReports = ref(0);
const filter = ref(route.query.filter || 'all');
const body = ref(''); const files = ref([]); const previews = ref([]); const consent = ref(false); const busy = ref(false); const open = ref(false);
const video = ref(null); const videoPreview = ref(null); const progress = ref(null);
const MAX_VIDEO = 100 * 1024 * 1024; const MAX_IMAGES = 10;
const pics = (p) => p.images.filter(x => x.kind !== 'video');
const mb = (b) => (b / 1024 / 1024).toLocaleString('da-DK', { maximumFractionDigits: 1 }) + ' MB';
function addVideo(e) {
    const f = e.target.files[0]; e.target.value = '';
    if (!f) return;
    if (f.size > MAX_VIDEO) return toast(`Videoen fylder ${mb(f.size)}. Den må højst fylde 100 MB – vælg en kortere video.`, 'error');
    video.value = f; videoPreview.value = URL.createObjectURL(f);
}
function removeVideo() { if (videoPreview.value) URL.revokeObjectURL(videoPreview.value); video.value = null; videoPreview.value = null; }
// Opslag med en video, der stadig behandles: hent listen igen med jævne mellemrum
let poll = null;
function schedulePoll() {
    clearTimeout(poll);
    if (filter.value === 'all' && posts.value?.some(p => p.images.some(i => i.kind === 'video' && i.status === 'processing'))) poll = setTimeout(() => load().then(schedulePoll), 15000);
}
const hidePost = ref(null); const hideReason = ref(''); const reportPost = ref(null); const reportReason = ref(''); const lightbox = ref(null);

const initials = (n) => n.split(' ').map(x => x[0]).slice(0, 2).join('').toUpperCase();
function ago(iso) {
    const s = (Date.now() - new Date(iso)) / 1000;
    if (s < 60) return 'lige nu';
    if (s < 3600) return `${Math.floor(s / 60)} min. siden`;
    if (s < 86400) return `${Math.floor(s / 3600)} t. siden`;
    if (s < 7 * 86400) return `${Math.floor(s / 86400)} d. siden`;
    return new Date(iso).toLocaleDateString('da-DK', { day: 'numeric', month: 'short', year: new Date(iso).getFullYear() !== new Date().getFullYear() ? 'numeric' : undefined });
}

async function load(older = false) {
    const before = older && posts.value?.length ? `&before=${posts.value[posts.value.length - 1].id}` : '';
    const r = await api(`feed?filter=${filter.value}${before}`);
    const list = r.posts.map(p => ({ ...p, showComments: false, draft: '' }));
    posts.value = older ? [...posts.value, ...list] : list;
    more.value = r.more; isMod.value = r.is_mod; openReports.value = r.open_reports;
}
function setFilter(f) { filter.value = f; posts.value = null; load(); }

/** Læs billedet ind med det samme og skaler det ned på enheden (mobilbilleder er ofte 5-10 MB). */
async function shrink(file, max = 2000) {
    try {
        const bmp = await createImageBitmap(file);
        const scale = Math.min(1, max / Math.max(bmp.width, bmp.height));
        const c = document.createElement('canvas');
        c.width = Math.round(bmp.width * scale); c.height = Math.round(bmp.height * scale);
        c.getContext('2d').drawImage(bmp, 0, 0, c.width, c.height);
        const blob = await new Promise(r => c.toBlob(r, 'image/jpeg', 0.86));
        return new File([blob], file.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' });
    } catch {
        return new File([await file.arrayBuffer()], file.name, { type: file.type });
    }
}
async function addImages(e) {
    const input = e.target;
    if (input.files.length > MAX_IMAGES - files.value.length) toast(`Du kan højst vedhæfte ${MAX_IMAGES} billeder pr. opslag.`, 'error');
    const picked = [...input.files].slice(0, MAX_IMAGES - files.value.length);
    for (const f of picked) { const s = await shrink(f); files.value.push(s); previews.value.push(URL.createObjectURL(s)); }
    input.value = '';
}
function removeImage(i) { URL.revokeObjectURL(previews.value[i]); files.value.splice(i, 1); previews.value.splice(i, 1); }

async function post() {
    if ((files.value.length || video.value) && !consent.value) return toast(`Bekræft, at personerne på ${video.value && files.value.length ? 'billederne og videoen' : video.value ? 'videoen' : 'billederne'} er okay med at blive vist.`, 'error');
    busy.value = true; progress.value = video.value ? 0 : null;
    const fd = new FormData();
    fd.append('body', body.value);
    files.value.forEach(f => fd.append('images[]', f));
    if (video.value) fd.append('video', video.value);
    if (consent.value) fd.append('consent', '1');
    const hadVideo = !!video.value;
    try {
        if (hadVideo) await upload('feed', fd, (p) => (progress.value = p));
        else await api('feed', { method: 'POST', form: fd });
        body.value = ''; previews.value.forEach(URL.revokeObjectURL); files.value = []; previews.value = []; removeVideo(); consent.value = false; open.value = false;
        if (filter.value !== 'all') filter.value = 'all';
        await load(); schedulePoll();
        toast(hadVideo ? 'Dit opslag er delt. Videoen vises, når den er behandlet (typisk et minut eller to).' : 'Dit opslag er delt');
    } catch (e) { toast(e.message, 'error'); } finally { busy.value = false; progress.value = null; }
}
async function like(p) { const r = await api(`feed/${p.id}/like`, { method: 'POST' }); p.likes = r.likes; p.liked = r.liked; }
async function comment(p) {
    try { await api(`feed/${p.id}/comments`, { method: 'POST', body: { body: p.draft } }); p.draft = ''; const open = p.id; await load(); const np = posts.value.find(x => x.id === open); if (np) np.showComments = true; }
    catch (e) { toast(e.message, 'error'); }
}
async function remove(p) { if (p.confirmDelete) { await api(`feed/${p.id}`, { method: 'DELETE' }); posts.value = posts.value.filter(x => x.id !== p.id); toast('Opslaget er slettet'); } else { p.confirmDelete = true; toast('Tryk på skraldespanden igen for at slette'); setTimeout(() => (p.confirmDelete = false), 4000); } }
async function removeComment(c) { await api(`feed-comments/${c.id}`, { method: 'DELETE' }); await load(); }
async function modComment(c) { await api(`feed-comments/${c.id}/moderate`, { method: 'PUT', body: { hide: !c.hidden } }); c.hidden = !c.hidden; }
function askHide(p) { hidePost.value = p; hideReason.value = ''; }
async function hide() { await api(`feed/${hidePost.value.id}/moderate`, { method: 'PUT', body: { hide: true, reason: hideReason.value } }); hidePost.value = null; toast('Opslaget er skjult, og forfatteren har fået besked'); load(); }
async function unhide(p) { await api(`feed/${p.id}/moderate`, { method: 'PUT', body: { hide: false } }); toast('Opslaget er synligt igen'); load(); }
async function dismiss(p) { await api(`feed/${p.id}/dismiss`, { method: 'POST' }); toast('Anmeldelsen er afvist'); load(); }
async function report() { await api(`feed/${reportPost.value.id}/report`, { method: 'POST', body: { reason: reportReason.value } }); reportPost.value.reported_by_me = true; reportPost.value = null; reportReason.value = ''; toast('Tak. Trænere og bestyrelse kigger på det.'); }

onMounted(() => load().then(schedulePoll));
onUnmounted(() => clearTimeout(poll));
</script>

<style scoped>
.feed { max-width: 620px; margin: 0 auto; }
.avatar { flex-shrink: 0; width: 38px; height: 38px; border-radius: 50%; display: grid; place-items: center; background: var(--accent-soft); color: var(--accent); font-weight: 700; font-size: .85rem; }
.avatar.sm { width: 28px; height: 28px; font-size: .7rem; }
.composer textarea { min-height: 44px; resize: vertical; border-radius: 12px; }
.thumbs { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px; padding-left: 48px; }
.thumb { position: relative; width: 84px; height: 84px; }
.thumb img { width: 100%; height: 100%; object-fit: cover; border-radius: 10px; }
.thumb button { position: absolute; top: -6px; right: -6px; width: 24px; height: 24px; border-radius: 50%; border: 0; background: var(--ink); color: var(--bg); display: grid; place-items: center; cursor: pointer; padding: 0; }
.thumb button svg { width: 14px; height: 14px; }
label.btn.disabled { opacity: .45; pointer-events: none; }
.post.hidden { opacity: .8; border-style: dashed; }
.post .body { white-space: pre-line; margin: 12px 0 0; overflow-wrap: anywhere; }
.gallery { display: grid; gap: 4px; margin: 12px -16px 0; }
.gallery button { padding: 0; border: 0; background: var(--surface-2); cursor: zoom-in; display: block; overflow: hidden; }
.gallery img { width: 100%; height: 100%; object-fit: cover; display: block; }
.gallery.n1 img { height: auto; max-height: 520px; object-fit: contain; background: var(--surface-2); }
.gallery.n2 { grid-template-columns: 1fr 1fr; } .gallery.n2 button { aspect-ratio: 1; }
.gallery.n3 { grid-template-columns: 1fr 1fr; } .gallery.n3 button:first-child { grid-row: span 2; } .gallery.n3 button { aspect-ratio: 1; } .gallery.n3 button:first-child { aspect-ratio: auto; }
.gallery.n4 { grid-template-columns: 1fr 1fr; } .gallery.n4 button { aspect-ratio: 1; }
.gallery.n5 { grid-template-columns: 1fr 1fr 1fr; } .gallery.n5 button { aspect-ratio: 1; } /* 5–10 billeder */
.like.on { color: #d6336c; }
.like.on svg { fill: currentColor; }
.comments { border-top: 1px solid var(--line); margin-top: 8px; padding-top: 10px; }
.comment { display: flex; gap: 8px; align-items: flex-start; margin-bottom: 8px; }
.comment.hidden { opacity: .6; }
.bubble { background: var(--surface-2); border-radius: 12px; padding: 6px 10px; flex: 1; min-width: 0; overflow-wrap: anywhere; }
.gallery .vplay { width: 100%; height: auto; max-height: 560px; background: #000; display: block; grid-column: 1 / -1; }
.gallery .vwait { grid-column: 1 / -1; padding: 28px 16px; text-align: center; color: var(--ink-2); background: var(--surface-2); }
.thumb.vid video { width: 100%; height: 100%; object-fit: cover; display: block; background: #000; }
.thumb .vsize { position: absolute; left: 4px; bottom: 4px; background: rgba(0,0,0,.6); color: #fff; font-size: .7rem; padding: 1px 5px; border-radius: 6px; }
.prog { height: 6px; border-radius: 3px; background: var(--surface-2); margin-top: 10px; overflow: hidden; }
.prog > i { display: block; height: 100%; background: var(--accent); transition: width .2s; }
.lightbox { position: fixed; inset: 0; z-index: 60; background: rgba(0,0,0,.88); display: grid; place-items: center; padding: 16px; cursor: zoom-out; }
.lightbox img { max-width: 100%; max-height: 100%; border-radius: 6px; }
</style>
