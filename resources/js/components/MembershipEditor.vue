<template>
    <!-- Bestyrelse/kasserer: husstandens medlemskaber hos betaleren – næste sæson (hvem og hvilken type) og tilføj personer -->
    <div v-if="!o" class="spinner"></div>
    <div v-else class="stack">
        <template v-if="!o.groups.length && !adding">
            <p class="small muted" style="margin:0">{{ first }} har intet medlemskab nu. Det oprettes med det samme, og {{ o.payer }} får en regning på den normale pris.</p>
            <label class="field"><span>Medlemskab</span>
                <select v-model="nowPkg"><option :value="null" disabled>Vælg …</option>
                    <option v-for="p in o.memberships" :key="p.id" :value="p.id">{{ p.name }} – {{ p.price_fmt }}{{ p.self_service ? '' : ' (kun bestyrelsen)' }}</option></select>
            </label>
            <button class="btn primary block" :disabled="busy || !nowPkg" @click="createNow">Opret medlemskab</button>
        </template>

        <template v-if="!adding">
            <section v-for="(g, i) in o.groups" :key="`${g.purchase_id}-${g.upcoming_id}`" class="grp">
                <div v-if="g.current && (i === 0 || o.groups[i - 1].purchase_id !== g.purchase_id)" class="small">
                    <strong>Nu:</strong> {{ g.current.name }} · {{ dato(g.current.valid_from) }}<template v-if="g.current.valid_to"> – {{ dato(g.current.valid_to) }}</template> · {{ g.current.paid ? 'betalt' : 'ikke betalt' }}
                    <div class="muted">{{ peopleNow(g) }}</div>
                </div>
                <div class="small" style="margin-top:4px">
                    <strong>Næste sæson:</strong>
                    <template v-if="g.upcoming">{{ g.upcoming.name }} · {{ dato(g.upcoming.valid_from) }} · {{ g.upcoming.paid ? 'betalt' : 'ikke betalt (' + g.upcoming.amount_fmt + ')' }} · {{ g.people.map(p => p.name).join(', ') }}</template>
                    <template v-else-if="g.mode === 'not_renewable'">fornyes ikke automatisk</template>
                    <template v-else>{{ nextText(g) }}</template>
                </div>
                <p v-if="g.mode === 'locked'" class="small muted" style="margin:4px 0 0">Næste periode er betalt og kan ikke ændres her.</p>
                <template v-else-if="g.mode !== 'not_renewable'">
                    <button v-if="editing !== i" class="btn small" style="margin-top:8px" @click="editing = i">Ret næste sæson</button>
                    <div v-else class="edit">
                        <NextSeasonEditor :plan="g" :url="`members/${memberId}/membership-next-options`" :save-url="`members/${memberId}/membership`"
                            :extra="{ purchase_id: g.purchase_id, upcoming_id: g.upcoming_id }" @saved="reload" />
                        <button class="btn small ghost" style="margin-top:6px" @click="editing = null">Annullér</button>
                    </div>
                </template>
            </section>
            <button class="btn block" @click="startAdd">+ Tilføj person</button>
        </template>

        <!-- Tilføj personer: udvid husstandens medlemskab eller giv et eget medlemskab -->
        <template v-else>
            <div v-if="o.without.length" class="field"><span>Personer i husstanden uden medlemskab</span>
                <label v-for="m in o.without" :key="m.id" class="check"><input type="checkbox" :value="m.id" v-model="add.member_ids"> {{ m.name }} <span class="small muted">{{ m.age }} år</span></label>
            </div>
            <div class="field"><span>Nye personer</span>
                <div v-for="(n, i) in add.new_members" :key="i" class="newp">
                    <div class="grid c2">
                        <label class="field"><span>Navn</span><input v-model="n.name"></label>
                        <label class="field"><span>Fødselsdato</span><input v-model="n.birthdate" type="date"></label>
                    </div>
                    <div class="grid c2">
                        <label class="field"><span>Egen e-mail (valgfri)</span><input v-model="n.email" type="email" autocapitalize="off"></label>
                        <label class="field"><span>Eget telefonnummer (valgfrit)</span><input v-model="n.phone" type="tel"></label>
                    </div>
                    <div class="row between"><label class="check small"><input type="checkbox" v-model="n.consent"> Forælder/værge har givet samtykke</label>
                        <button type="button" class="btn small ghost danger" @click="add.new_members.splice(i, 1)">Fjern</button></div>
                </div>
                <p class="small muted" style="margin:0 0 8px">Egen e-mail eller eget telefonnummer (ikke forælderens) skal kun gives, hvis personen selv skal kunne logge ind i appen og få notifikationer.</p>
                <button type="button" class="btn small" @click="add.new_members.push({ name: '', birthdate: '', email: '', phone: '', consent: false })">+ Ny person</button>
            </div>
            <div v-if="addBusy" class="spinner"></div>
            <div v-else-if="addOpts" class="field"><span>Medlemskab</span>
                <template v-if="addOpts.change">
                    <label v-for="x in addOpts.change.now" :key="'now' + x.package_id" class="check"><input type="radio" :value="`now:${x.package_id}`" v-model="add.choice">
                        <span><strong>Med det samme:</strong> {{ x.name }} – {{ x.amount_fmt }}<span class="small muted"> ({{ x.price_fmt }} minus {{ x.credit_fmt }} betalt)</span></span></label>
                    <label v-for="x in addOpts.change.next" :key="'next' + x.package_id" class="check"><input type="radio" :value="`next:${x.package_id}`" v-model="add.choice">
                        <span><strong>Fra {{ dato(x.valid_from) }}:</strong> {{ x.name }} – {{ x.amount_fmt }}</span></label>
                </template>
                <label v-for="x in addOpts.own" :key="'own' + x.id" class="check"><input type="radio" :value="`own:${x.id}`" v-model="add.choice">
                    <span><strong>Eget medlemskab:</strong> {{ x.name }} – {{ x.price_for }}{{ x.self_service ? '' : ' (kun bestyrelsen)' }}</span></label>
                <p v-if="!addOpts.own.length && !addOpts.change?.now.length && !addOpts.change?.next.length" class="small muted" style="margin:0">Intet medlemskab passer til de valgte personer.</p>
            </div>
            <p class="small muted" style="margin:0">Regningen går til {{ o.payer }}. Nye personer oprettes og kommer med, når den er betalt (kontant betaling registreres med "Registrér betaling").</p>
            <button class="btn primary block" :disabled="busy || !add.choice" @click="saveAdd">Tilføj og send regning</button>
            <button class="btn ghost block" @click="adding = false">Tilbage</button>
        </template>
    </div>
</template>
<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue';
import NextSeasonEditor from './NextSeasonEditor.vue';
import { api, dato, toast } from '../api';
const props = defineProps({ memberId: [String, Number], name: String });
const emit = defineEmits(['changed']);
const o = ref(null); const editing = ref(null); const busy = ref(false); const nowPkg = ref(null);
const first = computed(() => props.name.split(' ')[0]);
async function load() {
    try { o.value = await api(`members/${props.memberId}/membership-options`); editing.value = o.value.groups.length === 1 ? 0 : null; }
    catch (e) { toast(e.message, 'error'); }
}
onMounted(load);
function reload() { emit('changed'); load(); }
const peopleNow = (g) => g.upcoming ? g.current.people.join(', ') : g.people.map(p => p.name + (p.renews ? '' : ' (stopper)')).join(', ');
function nextText(g) {
    const on = g.people.filter(p => p.renews);
    const stop = g.people.filter(p => !p.renews).map(p => p.name.split(' ')[0]);
    const what = g.split ? on.map(p => `${p.own?.name ?? g.next_as.name} (${p.name.split(' ')[0]})`).join(', ') : `${g.next_as.name}${g.next_as.changed ? ' (ændret)' : ''}`;
    return `${what}${stop.length ? ' · uden ' + stop.join(' og ') : ''}`;
}
async function createNow() {
    busy.value = true;
    try { const r = await api(`members/${props.memberId}/membership`, { method: 'PUT', body: { package_id: nowPkg.value } }); toast(r.message); reload(); }
    catch (e) { toast(e.message, 'error'); } finally { busy.value = false; }
}

// Tilføj personer
const adding = ref(false); const addOpts = ref(null); const addBusy = ref(false);
const add = reactive({ member_ids: [], new_members: [], choice: null });
function startAdd() { Object.assign(add, { member_ids: [], new_members: o.value.without.length ? [] : [{ name: '', birthdate: '', email: '', phone: '', consent: false }], choice: null }); addOpts.value = null; adding.value = true; }
const addKey = computed(() => JSON.stringify([add.member_ids, add.new_members.map(n => n.birthdate)]));
let t = null;
watch(addKey, () => {
    clearTimeout(t); add.choice = null;
    const nm = add.new_members.filter(n => n.birthdate);
    if (!add.member_ids.length && !nm.length) { addOpts.value = null; return; }
    t = setTimeout(async () => {
        addBusy.value = true;
        try { addOpts.value = await api(`members/${props.memberId}/membership-add-options`, { method: 'POST', body: { member_ids: add.member_ids, new_members: nm.map(n => ({ name: n.name || null, birthdate: n.birthdate })) } }); }
        catch (e) { toast(e.message, 'error'); addOpts.value = null; } finally { addBusy.value = false; }
    }, 300);
});
async function saveAdd() {
    const [when, id] = add.choice.split(':');
    busy.value = true;
    try {
        const r = await api(`members/${props.memberId}/membership-add`, { method: 'POST', body: { when, package_id: Number(id), member_ids: add.member_ids, new_members: add.new_members } });
        toast(r.message); adding.value = false; reload();
    } catch (e) { toast(e.message, 'error'); } finally { busy.value = false; }
}
</script>
<style scoped>
.grp { border: 1px solid var(--line); border-radius: 10px; padding: 10px 12px; }
.edit { margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--line); }
.newp { border: 1px dashed var(--line); border-radius: 10px; padding: 10px; margin-bottom: 8px; }
</style>
