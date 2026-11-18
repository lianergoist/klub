<template>
    <!-- Hvem er med næste sæson, og som hvilket medlemskab (samlet eller hver sit). Bruges af bestyrelsen og af husstandens betaler. -->
    <div class="stack">
        <div class="field"><span>Med næste sæson<template v-if="plan.next_from"> (fra {{ dato(plan.next_from) }})</template></span>
            <label v-for="p in plan.people" :key="p.id" class="check"><input type="checkbox" :value="p.id" v-model="ids"> {{ p.name }} <span class="small muted">{{ p.age }} år</span></label>
        </div>
        <p v-if="stopping.length" class="small muted" style="margin:0">{{ names(stopping) }} er med resten af {{ plan.mode === 'replace_upcoming' ? 'den nuværende periode' : 'sæsonen' }}, men ikke fra {{ dato(plan.next_from) }}. Der betales ikke penge tilbage.</p>

        <div v-if="!opts" class="spinner"></div>
        <template v-else-if="ids.length">
            <div v-if="ids.length > 1" class="chips">
                <button type="button" :class="['chip', { on: !split }]" @click="split = false">Samlet medlemskab</button>
                <button type="button" :class="['chip', { on: split }]" @click="split = true">Hver sit medlemskab</button>
            </div>
            <template v-if="!split || ids.length === 1">
                <p v-if="!opts.together.length" class="banner warn" style="margin:0">Intet medlemskab passer til {{ ids.length }} personer. Vælg "Hver sit medlemskab".</p>
                <label v-else class="field"><span>Medlemskab</span>
                    <select v-model="pkg">
                        <option :value="null" disabled>Vælg …</option>
                        <option v-for="o in opts.together" :key="o.package_id" :value="o.package_id">{{ label(o) }}</option>
                    </select>
                </label>
            </template>
            <template v-else>
                <label v-for="p in chosen" :key="p.id" class="field"><span>{{ p.name }}</span>
                    <select v-model="own[p.id]">
                        <option :value="undefined" disabled>Vælg …</option>
                        <option v-for="o in opts.single[p.id] || []" :key="o.package_id" :value="o.package_id">{{ label(o) }}</option>
                    </select>
                </label>
            </template>
        </template>

        <p class="small muted" style="margin:0">
            <template v-if="plan.mode === 'replace_upcoming'">Regningen for næste periode er sendt, men ikke betalt. Den annulleres, og {{ plan.upcoming.payer }} får en ny.</template>
            <template v-else>Ændringen bruges, når kontingentet for næste sæson opkræves. Det nuværende medlemskab er uændret.</template>
        </p>
        <button class="btn primary block" :disabled="busy || !ready" @click="save">Gem næste sæson</button>
    </div>
</template>
<script setup>
import { ref, reactive, computed, watch } from 'vue';
import { api, dato, toast } from '../api';
const props = defineProps({
    plan: Object,            // fra MembershipChange::nextPlan
    url: String,             // fx members/44/membership-next-options
    saveUrl: String, saveMethod: { type: String, default: 'PUT' },
    extra: { type: Object, default: () => ({}) }, // fx { purchase_id, upcoming_id }
});
const emit = defineEmits(['saved']);
const ids = ref(props.plan.people.filter(p => p.renews).map(p => p.id));
const split = ref(props.plan.split);
const own = reactive(Object.fromEntries(props.plan.people.filter(p => p.own).map(p => [p.id, p.own.id])));
const pkg = ref(null); const opts = ref(null); const busy = ref(false);
const chosen = computed(() => props.plan.people.filter(p => ids.value.includes(p.id)));
const stopping = computed(() => props.plan.people.filter(p => !ids.value.includes(p.id)));
const names = (l) => l.map(p => p.name.split(' ')[0]).join(', ').replace(/, ([^,]*)$/, ' og $1');
const label = (o) => `${o.name} – ${o.price_fmt}${o.current ? ' (nuværende)' : ''}${o.self_service ? '' : ' (kun bestyrelsen)'}`;
const ready = computed(() => ids.value.length && (split.value && ids.value.length > 1 ? chosen.value.every(p => own[p.id]) : pkg.value));

let seq = 0;
async function load() {
    if (!ids.value.length) { opts.value = { together: [], single: {} }; return; }
    const n = ++seq; opts.value = null;
    try {
        const o = await api(props.url, { method: 'POST', body: { ...props.extra, member_ids: ids.value } });
        if (n !== seq) return;
        opts.value = o;
        // Forvalg: det nuværende medlemskab, hvis det stadig passer; ellers hver for sig, når intet samlet passer
        if (!o.together.some(x => x.package_id === pkg.value)) pkg.value = o.together.find(x => x.current)?.package_id ?? null;
        if (!o.together.length && ids.value.length > 1) split.value = true;
        for (const p of chosen.value) if (!(o.single[p.id] || []).some(x => x.package_id === own[p.id])) own[p.id] = (o.single[p.id] || [])[0]?.package_id;
    } catch (e) { if (n === seq) { toast(e.message, 'error'); opts.value = { together: [], single: {} }; } }
}
watch(ids, load, { immediate: true });

async function save() {
    busy.value = true;
    const body = { ...props.extra, member_ids: ids.value };
    if (split.value && ids.value.length > 1) body.split = Object.fromEntries(ids.value.map(id => [id, own[id]])); else body.package_id = pkg.value;
    try { const r = await api(props.saveUrl, { method: props.saveMethod, body }); toast(r.message); emit('saved', r); }
    catch (e) { toast(e.message, 'error'); } finally { busy.value = false; }
}
</script>
