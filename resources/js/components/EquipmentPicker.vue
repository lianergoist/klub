<template>
    <!-- Vælg bestemte stykker udstyr, grupperet efter kategori og underkategori, med hvad der er optaget i tidsrummet -->
    <div>
        <p v-if="anyAct" class="small legend"><span class="sw"></span>Booket til en aktivitet – {{ mode === 'book' ? 'du kan sende et ønske til træneren om at låne det' : 'aktiviteten skal afklares med træneren' }}</p>
        <p v-if="!groups.length" class="small muted" style="margin:0">Der er ikke registreret udstyr{{ categoryIds?.length ? ' i de valgte kategorier' : '' }}.</p>
        <div v-for="g in groups" :key="g.key" class="grp">
            <div class="row between" style="flex-wrap:nowrap">
                <div><strong class="small">{{ g.category }} · {{ g.type }}</strong>
                    <div v-for="(h, k) in g.common" :key="k" class="small d warnc">{{ label(h) }}</div></div>
                <button type="button" class="btn small ghost" @click="all(g)">{{ g.items.every(i => !pickable(i) || model.includes(i.id)) ? 'Fravælg alle' : 'Vælg alle' }}</button>
            </div>
            <div class="items">
                <label v-for="i in g.items" :key="i.id" :class="['it', { off: !pickable(i), warn: busyBy(i), act: byActivity(i) && pickable(i) }]">
                    <input type="checkbox" :disabled="!pickable(i)" :checked="model.includes(i.id)" @change="toggle(i.id)">
                    <span>{{ i.name }}
                        <span v-if="i.status === 'defect'" class="tag bad">Defekt</span>
                        <span v-else-if="i.status === 'service'" class="tag warn">Skal serviceres</span>
                        <span v-for="(h, k) in own(i, g)" :key="k" class="small muted d">{{ label(h) }}</span>
                    </span>
                </label>
            </div>
        </div>
    </div>
</template>
<script setup>
import { computed } from 'vue';
const props = defineProps({
    items: Array,          // fra equipment/availability
    categoryIds: Array,    // vis kun disse kategorier (valgfri)
    mode: { type: String, default: 'book' }, // book: bookinger kan ikke vælges · activity: alt (undtagen defekt) kan vælges
});
const model = defineModel({ type: Array, default: () => [] });
const visible = computed(() => props.items.filter(i => !props.categoryIds?.length || props.categoryIds.includes(i.category_id)));
const groups = computed(() => {
    const g = {};
    for (const i of visible.value) (g[i.type_id] ||= { key: i.type_id, category: i.category, type: i.type, items: [] }).items.push(i);
    // Det, alle stykker i gruppen er booket af, vises én gang ved gruppen
    for (const x of Object.values(g)) {
        const k = (h) => h.kind + (h.activity_id || h.booking_id);
        const first = x.items[0].holders.map(k);
        x.commonKeys = first.filter(key => x.items.every(i => i.holders.some(h => k(h) === key)));
        x.common = x.items[0].holders.filter(h => x.commonKeys.includes(k(h)));
        x.key2 = k;
    }
    return Object.values(g).sort((a, b) => (a.category + a.type).localeCompare(b.category + b.type, 'da'));
});
const busyBy = (i) => i.holders.length > 0;
const byActivity = (i) => i.status !== 'defect' && i.holders.some(h => h.kind === 'activity');
const anyAct = computed(() => visible.value.some(i => byActivity(i) && pickable(i)));
const label = (h) => h.kind === 'activity' ? 'Booket til aktiviteten ' + h.title : 'Booket af ' + h.name;
const own = (i, g) => i.holders.filter(h => !g.commonKeys.includes(g.key2(h)));
const pickable = (i) => i.status !== 'defect' && (props.mode === 'activity' || !i.holders.some(h => h.kind === 'booking'));
function toggle(id) { model.value = model.value.includes(id) ? model.value.filter(x => x !== id) : [...model.value, id]; }
function all(g) {
    const ids = g.items.filter(pickable).map(i => i.id);
    const on = ids.every(id => model.value.includes(id));
    model.value = on ? model.value.filter(id => !ids.includes(id)) : [...new Set([...model.value, ...ids])];
}
</script>
<style scoped>
.grp { border-top: 1px solid var(--line); padding: 10px 0; }
.grp:first-child { border-top: 0; padding-top: 0; }
.items { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 4px 8px; margin-top: 6px; }
.it { display: flex; gap: 8px; align-items: flex-start; cursor: pointer; font-size: .92rem; padding: 5px 7px; border-radius: 8px; border: 1px solid transparent; }
/* Booket til en aktivitet: kan stadig vælges (ønske til træneren), men skal skille sig ud */
.it.act { background: var(--warn-soft); border-color: color-mix(in srgb, var(--warn) 35%, transparent); }
.legend { display: flex; align-items: center; gap: 6px; margin: 0 0 8px; color: var(--ink-2); }
.legend .sw { width: 14px; height: 14px; border-radius: 4px; background: var(--warn-soft); border: 1px solid color-mix(in srgb, var(--warn) 35%, transparent); flex: none; }
.it input { margin-top: 2px; flex: none; }
.it.off { opacity: .55; cursor: not-allowed; }
.it.warn > span > .d { color: var(--warn); }
.d { display: block; }
.warnc { color: var(--warn); }
</style>
