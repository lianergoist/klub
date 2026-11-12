<template>
    <div class="picker">
        <input v-model="filter" type="search" placeholder="Filtrér listen …" aria-label="Filtrér medlemmer">
        <div v-if="picked.length" class="chips" style="margin-top:8px">
            <button v-for="p in pickedPeople" :key="p.id" type="button" class="chip on" @click="toggle(p.id)">{{ p.name }} <Icon name="x" style="width:12px" /></button>
        </div>
        <div v-if="people === null" class="spinner"></div>
        <div v-else class="plist" role="listbox" aria-multiselectable="true">
            <template v-for="g in groups" :key="g.letter">
                <div class="letter">{{ g.letter }}</div>
                <label v-for="p in g.items" :key="p.id" class="prow">
                    <input type="checkbox" :value="p.id" v-model="picked">
                    <span class="avatar sm">{{ initials(p.name) }}</span>
                    <span style="flex:1">{{ p.name }}</span>
                    <span v-if="p.trainer" class="tag">Træner/bestyrelse</span>
                    <span v-else-if="p.opted_out" class="tag warn" title="Har fravalgt beskeder fra andre medlemmer – du kan skrive, fordi du er træner/bestyrelse">Kun fra trænere</span>
                </label>
            </template>
            <p v-if="!groups.length" class="muted small" style="padding:12px 4px">Ingen medlemmer matcher.</p>
        </div>
        <p class="small muted" style="margin:8px 0 0">{{ store.user.can.messages_staff ? 'Som træner/bestyrelse kan du skrive til alle voksne medlemmer – også dem, der har fravalgt beskeder fra andre medlemmer.' : 'Listen viser voksne medlemmer, der modtager beskeder, samt trænere og bestyrelse.' }}</p>
    </div>
</template>
<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import Icon from './Icon.vue';
import { api, store } from '../api';
const props = defineProps({ exclude: { type: Array, default: () => [] } });
const emit = defineEmits(['change']);
const people = ref(null); const filter = ref(''); const picked = ref([]);
const initials = (n) => n.split(' ').map(x => x[0]).slice(0, 2).join('').toUpperCase();
const pickedPeople = computed(() => (people.value || []).filter(p => picked.value.includes(p.id)));
const groups = computed(() => {
    const f = filter.value.trim().toLowerCase();
    const out = [];
    for (const p of (people.value || []).filter(p => !props.exclude.includes(p.id) && (!f || p.name.toLowerCase().includes(f)))) {
        const l = p.name[0].toUpperCase();
        let g = out.find(x => x.letter === l); if (!g) out.push(g = { letter: l, items: [] });
        g.items.push(p);
    }
    return out;
});
const toggle = (id) => { const i = picked.value.indexOf(id); i >= 0 ? picked.value.splice(i, 1) : picked.value.push(id); };
watch(picked, (v) => emit('change', [...v]), { deep: true });
onMounted(async () => { people.value = await api('messages/people'); });
</script>
<style scoped>
.plist { max-height: 46vh; overflow-y: auto; margin-top: 8px; border: 1px solid var(--line); border-radius: 12px; }
.letter { position: sticky; top: 0; background: var(--surface-2); font-size: .72rem; font-weight: 700; color: var(--ink-3); padding: 3px 12px; z-index: 1; }
.prow { display: flex; align-items: center; gap: 10px; padding: 8px 12px; cursor: pointer; border-top: 1px solid var(--line); }
.letter + .prow { border-top: 0; }
.prow:hover { background: var(--surface-2); }
.avatar.sm { flex-shrink: 0; width: 30px; height: 30px; border-radius: 50%; display: grid; place-items: center; background: var(--accent-soft); color: var(--accent); font-weight: 700; font-size: .7rem; }
.chip svg { margin-left: 2px; }
</style>
