<template>
    <div class="ip">
        <div class="ip-row">
            <button type="button" class="ip-thumb" :class="{ empty: !modelValue }" :title="modelValue ? 'Skift billede' : 'Vælg billede'" @click="open = true">
                <img v-if="modelValue" :src="thumb(modelValue)" alt="">
                <span v-else>Vælg billede</span>
            </button>
            <div class="ip-actions">
                <button type="button" class="btn small" @click="open = true">{{ modelValue ? 'Skift billede' : 'Vælg billede' }}</button>
                <button v-if="removable && modelValue" type="button" class="btn small ghost danger" @click="$emit('remove')">{{ removeLabel }}</button>
            </div>
        </div>
        <label v-if="withAlt" class="field ip-alt"><span>Beskrivelse af billedet <span class="muted">(læses op for svagtseende)</span></span>
            <input :value="alt" maxlength="300" placeholder="Fx: To børn i våddragt på stranden ved Husodde" @input="$emit('update:alt', $event.target.value)">
        </label>
        <p v-if="withAlt && modelValue && !alt?.trim()" class="small" style="color:var(--warn);margin:4px 0 0">Skriv en kort beskrivelse – den skal med, før siden kan udgives.</p>

        <Modal v-if="open" title="Vælg billede" @close="open = false">
            <div class="ip-up">
                <label class="btn primary">
                    {{ busy ? `Uploader … ${progress} %` : 'Upload et nyt billede' }}
                    <input type="file" accept="image/jpeg,image/png,image/webp" hidden :disabled="busy" @change="uploadFile">
                </label>
                <span class="small muted">JPG, PNG eller WebP. Billedet gøres mindre og renses for oplysninger som GPS-position.</span>
            </div>
            <p v-if="!lib" class="muted">Henter billeder …</p>
            <div v-else class="ip-grid">
                <button v-for="im in lib" :key="im.src" type="button" :class="['ip-cell', { on: im.src === modelValue }]" @click="choose(im.src)">
                    <img :src="im.thumb" alt="" loading="lazy">
                    <span v-if="im.uploaded" class="ip-tag">Uploadet</span>
                </button>
            </div>
        </Modal>
    </div>
</template>
<script>
// Billedbiblioteket hentes én gang og deles af alle billedvælgere
let cache = null;
</script>
<script setup>
import { ref, watch } from 'vue';
import { api, upload, toast } from '../../api';
import Modal from '../Modal.vue';

const props = defineProps({
    modelValue: { type: String, default: null },
    alt: { type: String, default: '' },
    withAlt: Boolean,
    removable: Boolean,
    removeLabel: { type: String, default: 'Fjern billedet' },
});
const emit = defineEmits(['update:modelValue', 'update:alt', 'remove']);
const open = ref(false);
const busy = ref(false);
const progress = ref(0);
const lib = ref(cache);

const thumb = (src) => (src && /\/(billeder|uploads)\//.test(src) && !/-\d+\.webp$/.test(src) ? src.replace(/\.webp$/, src.includes('/logo-') ? '-600.webp' : '-900.webp') : src);

watch(open, async (o) => {
    if (!o || cache) { lib.value = cache; return; }
    try { cache = (await api('settings/site/images')).images; lib.value = cache; } catch (e) { toast(e.message, 'error'); }
});
function choose(src) { emit('update:modelValue', src); open.value = false; }
async function uploadFile(e) {
    const f = e.target.files?.[0];
    e.target.value = '';
    if (!f) return;
    if (f.size > 15 * 1024 * 1024) return toast('Billedet må højst fylde 15 MB.', 'error');
    const form = new FormData();
    form.append('image', f);
    busy.value = true; progress.value = 0;
    try {
        const r = await upload('settings/site/images', form, (p) => { progress.value = p; });
        cache = [{ src: r.src, thumb: r.thumb, uploaded: true }, ...(cache || [])];
        lib.value = cache;
        choose(r.src);
        if (props.withAlt) emit('update:alt', '');
        toast('Billedet er uploadet');
    } catch (err) { toast(err.message, 'error'); }
    busy.value = false;
}
</script>
<style scoped>
.ip-row { display: flex; gap: 12px; align-items: center; }
.ip-thumb { width: 104px; height: 72px; flex: none; padding: 0; border: 1px solid var(--line); border-radius: 8px; overflow: hidden; background: var(--surface-2); cursor: pointer; }
.ip-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
.ip-thumb.empty { border-style: dashed; color: var(--ink-3); font-size: .78rem; }
.ip-actions { display: flex; flex-direction: column; gap: 6px; align-items: flex-start; }
.ip-alt { margin-top: 8px; }
.ip-up { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-bottom: 14px; }
.ip-up .btn { cursor: pointer; }
.ip-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 8px; max-height: 60vh; overflow-y: auto; }
.ip-cell { position: relative; padding: 0; border: 2px solid transparent; border-radius: 8px; overflow: hidden; aspect-ratio: 4 / 3; background: var(--surface-2); cursor: pointer; }
.ip-cell img { width: 100%; height: 100%; object-fit: cover; display: block; }
.ip-cell.on { border-color: var(--accent); }
.ip-cell:hover { border-color: var(--accent-soft); }
.ip-tag { position: absolute; left: 4px; bottom: 4px; background: rgba(0,0,0,.6); color: #fff; font-size: .68rem; padding: 2px 6px; border-radius: 4px; }
</style>
