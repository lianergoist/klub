<template>
    <Modal title="Opret nyt medlem" @close="$emit('close')">
        <div v-if="!options" class="spinner"></div>
        <form v-else class="stack" @submit.prevent="save">
            <label class="field"><span>Navn</span><input v-model="f.name" required autocomplete="off"></label>
            <div class="grid c2" style="gap:8px">
                <label class="field"><span>Fødselsdato</span><input v-model="f.birthdate" type="date" required></label>
                <label class="field"><span>Telefon</span><input v-model="f.phone" type="tel" autocomplete="off"></label>
            </div>
            <label class="field"><span>E-mail (valgfri)</span><input v-model="f.email" type="email" autocomplete="off">
                <span class="small muted">Med e-mail kan medlemmet selv logge ind via "Glemt adgangskode".</span></label>
            <label class="field"><span>Adresse</span><input v-model="f.address" autocomplete="off"></label>
            <div class="grid c2" style="gap:8px">
                <label class="field"><span>Postnr.</span><input v-model="f.postal_code" autocomplete="off"></label>
                <label class="field"><span>By</span><input v-model="f.city" autocomplete="off"></label>
            </div>
            <div class="grid c2" style="gap:8px">
                <label class="field"><span>Nødkontakt</span><input v-model="f.emergency_name" autocomplete="off"></label>
                <label class="field"><span>Nødkontaktens telefon</span><input v-model="f.emergency_phone" type="tel" autocomplete="off"></label>
            </div>
            <label class="field"><span>Medlemskab</span>
                <select v-model="f.package_id" required>
                    <option :value="null" disabled>Vælg …</option>
                    <option v-for="p in options.memberships" :key="p.id" :value="p.id">{{ p.name }} – {{ p.price_fmt }}{{ p.self_service ? '' : ' (kun bestyrelsen)' }}</option>
                </select>
            </label>
            <p v-if="pkg" class="small muted" style="margin:0">{{ pkg.composition_label }} · gælder {{ dato(pkg.valid_from) }}<template v-if="pkg.valid_to"> – {{ dato(pkg.valid_to) }}</template></p>
            <label class="check"><input type="checkbox" v-model="f.consent"> Medlemmet (eller forælder) har givet samtykke til, at klubben gemmer oplysningerne</label>
            <p class="small muted" style="margin:0">Medlemmet får en regning på medlemskabets normale pris og bliver aktivt, når den er betalt. Kontant betaling registreres på medlemmets side med "Registrér betaling".</p>
            <button class="btn primary block" :disabled="busy || !f.consent">Opret medlem</button>
        </form>
    </Modal>
</template>
<script setup>
import { ref, computed, onMounted } from 'vue';
import Modal from './Modal.vue';
import { api, dato, toast } from '../api';
const emit = defineEmits(['close', 'done']);
const options = ref(null); const busy = ref(false);
const f = ref({ name: '', birthdate: '', email: '', phone: '', address: '', postal_code: '', city: '', emergency_name: '', emergency_phone: '', package_id: null, consent: false });
const pkg = computed(() => options.value?.memberships.find(p => p.id === f.value.package_id));
onMounted(async () => {
    try { options.value = await api('members/new-options'); } catch (e) { toast(e.message, 'error'); emit('close'); }
});
async function save() {
    busy.value = true;
    try {
        const body = Object.fromEntries(Object.entries(f.value).map(([k, v]) => [k, v === '' ? null : v]));
        const r = await api('members', { method: 'POST', body });
        toast(r.paid ? 'Medlemmet er oprettet' : `Medlemmet er oprettet – regning på ${r.amount_fmt}`);
        emit('done', r.id);
    } catch (e) { toast(e.message, 'error'); } finally { busy.value = false; }
}
</script>
