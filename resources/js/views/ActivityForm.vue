<template>
    <div>
        <a href="#" class="small" @click.prevent="$router.back()">‹ Tilbage</a>
        <h1 style="margin:8px 0 14px">{{ id ? 'Rediger aktivitet' : 'Ny aktivitet' }}</h1>
        <form class="card stack" @submit.prevent="save">
            <label class="field"><span>Titel</span><input v-model="f.title" required placeholder="Fx Onsdagstræning windsurf"></label>
            <div>
                <span class="small" style="font-weight:600;color:var(--ink-2)">Kategorier</span>
                <div class="chips" style="margin-top:6px">
                    <button v-for="c in cats" :key="c.id" type="button" :class="['chip', { on: f.category_ids.includes(c.id) }]" @click="toggle(f.category_ids, c.id)"><span class="sw" :style="{ background: c.color }"></span>{{ c.name }}</button>
                </div>
            </div>
            <div class="grid c2">
                <label class="field"><span>Start</span><input v-model="f.starts_at" type="datetime-local" required @change="autoFill"></label>
                <label class="field"><span>Slut</span><input v-model="f.ends_at" type="datetime-local" required></label>
            </div>
            <label class="field"><span>Sted</span><input v-model="f.location" placeholder="Fx Strandpladsen"></label>
            <label class="field"><span>Beskrivelse</span><textarea v-model="f.description" placeholder="Hvad skal deltagerne vide? Udstyr, niveau, vejrforbehold …"></textarea></label>
            <div class="grid c2">
                <label class="field"><span>Max deltagere</span><input v-model.number="f.max_participants" type="number" min="1" placeholder="Ubegrænset"></label>
                <label class="field"><span>Min deltagere</span><input v-model.number="f.min_participants" type="number" min="1"></label>
            </div>
            <div class="grid c2">
                <label class="field"><span>Svarfrist</span><input v-model="f.signup_deadline" type="datetime-local"></label>
                <label class="field"><span>Afmelding lukker (timer før start)</span><input v-model.number="f.cancel_hours" type="number" min="0"></label>
            </div>
            <div class="grid c2">
                <label class="field"><span>Pris pr. deltager (kr.)</span><input v-model.number="priceKr" type="number" min="0" step="1" placeholder="Gratis"></label>
                <label class="check" style="margin-top:22px"><input type="checkbox" v-model="f.members_only"> Kræver betalt kontingent</label>
            </div>
            <label class="field"><span>Åben for ikke-medlemmer</span>
                <select v-model="f.guest_offer"><option :value="null">Nej</option><option value="trial">Ja, som prøvetræning (400 kr.)</option><option value="sup_course">Ja, som SUP-kursus (500 kr.)</option></select>
            </label>
            <p v-if="f.guest_offer" class="small muted" style="margin:-4px 0 0">Aktiviteten vises på siden "Prøv SurfHorsens". Gæster betaler prisen fra pristabellen, og beløbet trækkes fra kontingentet, hvis de melder sig ind.</p>
            <div>
                <span class="small" style="font-weight:600;color:var(--ink-2)">Trænere / tovholdere</span>
                <p class="small muted" style="margin:2px 0 6px">Trænere, der har meldt sig utilgængelige på dagen, er skjult. Du kan også oprette aktiviteten uden træner.</p>
                <div class="chips">
                    <button v-for="t in trainers.filter(t => !t.unavailable || f.trainer_ids.includes(t.id))" :key="t.id" type="button" :class="['chip', { on: f.trainer_ids.includes(t.id) }]" @click="toggle(f.trainer_ids, t.id)">
                        {{ t.name }}<span v-if="t.unavailable" class="tag warn">utilgængelig</span>
                    </button>
                </div>
                <p v-if="hidden.length" class="small muted" style="margin-top:6px">Utilgængelige: {{ hidden.map(t => `${t.name} (${t.reason || 'ikke angivet'})`).join(', ') }}</p>
            </div>
            <label v-if="!id" class="check"><input type="checkbox" v-model="f.notify"> Send besked til medlemmer, der følger kategorien</label>
            <p v-if="error" class="banner bad">{{ error }}</p>
            <button class="btn primary block" :disabled="busy">{{ id ? 'Gem ændringer' : 'Opret aktivitet' }}</button>
        </form>
    </div>
</template>

<script setup>
import { reactive, ref, computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { api, toast } from '../api';

const props = defineProps({ id: String });
const router = useRouter();
const cats = ref([]); const trainers = ref([]); const error = ref(''); const busy = ref(false);
const f = reactive({ title: '', description: '', starts_at: '', ends_at: '', location: '', max_participants: null, min_participants: null, signup_deadline: '', cancel_hours: 24, price: 0, members_only: true, guest_offer: null, category_ids: [], trainer_ids: [], notify: true });
const priceKr = computed({ get: () => f.price ? f.price / 100 : '', set: (v) => (f.price = Math.round((Number(v) || 0) * 100)) });
const hidden = computed(() => trainers.value.filter(t => t.unavailable && !f.trainer_ids.includes(t.id)));

const local = (iso) => { if (!iso) return ''; const d = new Date(iso); d.setMinutes(d.getMinutes() - d.getTimezoneOffset()); return d.toISOString().slice(0, 16); };
const toggle = (arr, id) => { const i = arr.indexOf(id); i >= 0 ? arr.splice(i, 1) : arr.push(id); };

function autoFill() {
    if (!f.starts_at) return;
    const s = new Date(f.starts_at);
    if (!f.ends_at || new Date(f.ends_at) <= s) f.ends_at = local(new Date(s.getTime() + 2 * 3600e3));
    if (!f.signup_deadline) { const d = new Date(s); d.setDate(d.getDate() - 1); d.setHours(20, 0); f.signup_deadline = local(d); }
}

async function loadTrainers() { trainers.value = await api(`trainer-options?date=${(f.starts_at || new Date().toISOString()).slice(0, 10)}`); }
watch(() => f.starts_at?.slice(0, 10), loadTrainers);

onMounted(async () => {
    cats.value = await api('categories');
    if (props.id) {
        const a = await api(`activities/${props.id}`);
        Object.assign(f, { title: a.title, description: a.description || '', starts_at: local(a.starts_at), ends_at: local(a.ends_at), location: a.location || '', max_participants: a.max_participants, min_participants: a.min_participants, signup_deadline: local(a.signup_deadline), cancel_hours: a.cancel_hours, price: a.price, members_only: a.members_only, guest_offer: a.guest_offer, category_ids: a.categories.map(c => c.id), trainer_ids: a.trainers.map(t => t.id) });
    }
    await loadTrainers();
});

async function save() {
    busy.value = true; error.value = '';
    const body = { ...f, max_participants: f.max_participants || null, min_participants: f.min_participants || null, signup_deadline: f.signup_deadline || null };
    try {
        const r = await api(props.id ? `activities/${props.id}` : 'activities', { method: props.id ? 'PUT' : 'POST', body });
        toast(props.id ? 'Ændringerne er gemt' : 'Aktiviteten er oprettet');
        router.replace(`/aktiviteter/${r.id}`);
    } catch (e) { error.value = e.message; } finally { busy.value = false; }
}
</script>
