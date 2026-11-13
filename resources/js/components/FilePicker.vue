<template>
    <div>
        <!-- To knapper: kameraet direkte (capture) og galleri/filer. Uden capture åbner Android ofte kun galleri og filer. -->
        <input ref="camera" type="file" accept="image/*" capture="environment" class="hidden-input" @change="pick">
        <input ref="picker" type="file" accept="image/*,application/pdf" class="hidden-input" @change="pick">
        <div v-if="modelValue" class="drop has">
            <img v-if="preview" :src="preview" alt="Valgt billede">
            <span v-else class="row" style="gap:8px"><Icon name="file" style="width:22px" /> {{ modelValue.name }}</span>
        </div>
        <div v-else class="drop">
            <span class="drop-empty"><Icon name="receipt" style="width:34px;height:34px" /><span class="small muted">{{ hint }}</span></span>
            <div class="pick-btns">
                <button type="button" class="btn primary" @click="camera.click()">Tag billede</button>
                <button type="button" class="btn" @click="picker.click()">Vælg billede eller PDF</button>
            </div>
        </div>
        <button v-if="modelValue" type="button" class="btn small ghost" style="margin-top:6px" @click="clear">Vælg et andet billede</button>
    </div>
</template>
<script setup>
import { ref, watch, onUnmounted } from 'vue';
import Icon from './Icon.vue';

const props = defineProps({ modelValue: Object, hint: { type: String, default: 'Et billede eller en PDF' } });
const emit = defineEmits(['update:modelValue']);
const camera = ref(null); const picker = ref(null); const preview = ref('');

/** Store telefonbilleder skaleres ned (max 2400 px), så upload går hurtigt, men teksten stadig kan læses. */
async function shrink(img) {
    if (!img.type.startsWith('image/') || img.size < 1.5e6) return img;
    try {
        const bmp = await createImageBitmap(img);
        const scale = Math.min(1, 2400 / Math.max(bmp.width, bmp.height));
        const c = document.createElement('canvas');
        c.width = Math.round(bmp.width * scale); c.height = Math.round(bmp.height * scale);
        c.getContext('2d').drawImage(bmp, 0, 0, c.width, c.height);
        const blob = await new Promise(res => c.toBlob(res, 'image/jpeg', 0.85));
        return blob ? new File([blob], (img.name || 'bilag').replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' }) : img;
    } catch { return img; } // fx HEIC, som browseren ikke kan læse – send originalen
}
function setPreview(f) {
    if (preview.value) URL.revokeObjectURL(preview.value);
    preview.value = f && f.type.startsWith('image/') && f.type !== 'image/heic' ? URL.createObjectURL(f) : '';
}
async function pick(e) {
    const chosen = e.target.files?.[0]; if (!chosen) return;
    const f = await shrink(chosen);
    setPreview(f); emit('update:modelValue', f);
}
function clear() { setPreview(null); camera.value.value = ''; picker.value.value = ''; emit('update:modelValue', null); }
// Forælderen nulstiller (fx efter afsendelse)
watch(() => props.modelValue, (v) => { if (!v) { setPreview(null); if (camera.value) camera.value.value = ''; if (picker.value) picker.value.value = ''; } });
onUnmounted(() => setPreview(null));
</script>
<style scoped>
.drop { display: grid; place-items: center; gap: 12px; min-height: 150px; border: 2px dashed var(--line); border-radius: 14px; background: var(--surface-2); overflow: hidden; text-align: center; padding: 10px; }
.drop.has { border-style: solid; padding: 6px; }
.hidden-input { position: absolute; width: 1px; height: 1px; opacity: 0; pointer-events: none; }
.pick-btns { display: flex; gap: 8px; flex-wrap: wrap; justify-content: center; }
.drop img { max-width: 100%; max-height: 320px; border-radius: 10px; display: block; }
.drop-empty { display: flex; flex-direction: column; align-items: center; gap: 4px; color: var(--accent); }
</style>
