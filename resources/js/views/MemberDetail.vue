<template>
    <div v-if="!m" class="spinner"></div>
    <div v-else>
        <RouterLink to="/medlemmer" class="small">‹ Medlemmer</RouterLink>
        <div class="row between" style="margin:8px 0 14px">
            <div><h1>{{ m.profile.name }}</h1>
                <span :class="['tag', m.profile.status === 'active' ? 'ok' : 'warn']">{{ m.profile.status_label }}</span>
                <span v-if="m.profile.membership_type" class="tag ok">{{ m.profile.membership_type }}</span>
                <span v-else-if="['active', 'passive'].includes(m.profile.status)" class="tag bad">Intet gyldigt medlemskab</span>
            </div>
            <button v-if="can.members_edit" class="btn small" @click="edit = { ...m.profile }">Ret oplysninger</button>
        </div>

        <div v-if="m.profile.status === 'pending' && can.members_approve" class="card" style="border-color:var(--warn)">
            <h2>Afventer betaling</h2>
            <p class="small muted">Medlemmet bliver aktivt automatisk, når medlemskabet er betalt. Ved kontant betaling: brug <em>Økonomi → Betalinger → Registrér betaling</em>, så pengene bliver bogført og medlemskabet gælder. "Aktivér" gør kun profilen aktiv – uden et betalt medlemskab kan medlemmet ikke tilmelde sig aktiviteter, der kræver medlemskab.</p>
            <button class="btn" @click="approve">Aktivér profilen</button>
        </div>

        <div v-if="m.purchases?.length" class="card">
            <h3>Medlemskaber og tilkøb</h3>
            <ul class="list">
                <li v-for="x in m.purchases" :key="x.id" class="row between" style="flex-wrap:nowrap">
                    <span>{{ x.name }} <span class="small muted">· {{ dato(x.valid_from) }}<template v-if="x.valid_to"> – {{ dato(x.valid_to) }}</template><template v-if="x.uses"> · {{ x.uses_left }} af {{ x.uses }} klip tilbage</template><template v-if="x.note"> · {{ x.note }}</template></span></span>
                    <span :class="['tag', { paid: 'ok', open: 'info', overdue: 'bad' }[x.status] || '']">{{ { paid: 'Betalt', open: 'Ubetalt', overdue: 'Forfalden', cancelled: 'Annulleret' }[x.status] || x.status }}</span>
                </li>
            </ul>
        </div>

        <div class="grid c2">
            <div class="card">
                <h3>Kontakt</h3>
                <p><a v-if="m.profile.email" :href="`mailto:${m.profile.email}`">{{ m.profile.email }}</a><template v-else>–</template><br>
                    <a v-if="m.profile.phone" :href="`tel:${m.profile.phone.replace(/\s/g, '')}`">{{ m.profile.phone }}</a><template v-else>–</template><br>{{ m.profile.address }}, {{ m.profile.postal_code }} {{ m.profile.city }}</p>
                <p class="small">Født {{ dato(m.profile.birthdate) }} ({{ m.profile.age }} år)<br>Indmeldt {{ m.profile.joined_on ? dato(m.profile.joined_on) : '–' }}<br>Samtykke givet {{ m.profile.consent_at ? dato(m.profile.consent_at) : '–' }}</p>
                <p class="small" v-if="m.profile.emergency_name">Nødkontakt: {{ m.profile.emergency_name }}, <a v-if="m.profile.emergency_phone" :href="`tel:${m.profile.emergency_phone.replace(/\s/g, '')}`">{{ m.profile.emergency_phone }}</a></p>
            </div>
            <div class="card" v-if="m.household">
                <h3>{{ m.household.name }}</h3>
                <p class="small muted">Betaler: {{ m.household.payer }}</p>
                <ul class="list"><li v-for="h in m.household.members" :key="h.id"><RouterLink :to="`/medlemmer/${h.id}`">{{ h.name }}</RouterLink> <span class="muted small">{{ h.age }} år</span></li></ul>
            </div>
        </div>

        <div class="card">
            <div class="row between"><h2 style="margin:0">Roller</h2><button v-if="can.roles_manage" class="btn small" @click="showRole = true">Tildel rolle</button></div>
            <p v-if="!can.roles_manage" class="small muted" style="margin-top:6px">Kun administrator kan tildele og fjerne roller.</p>
            <table style="margin-top:8px"><tbody>
                <tr v-for="r in m.roles" :key="r.id" :style="r.ends_on && r.ends_on < today ? 'opacity:.55' : ''">
                    <td><strong>{{ r.label }}</strong></td><td class="small">{{ dato(r.starts_on) }} – {{ r.ends_on ? dato(r.ends_on) : 'nu' }}</td><td class="small muted">tildelt af {{ r.granted_by || '–' }}</td>
                    <td class="num"><button v-if="can.roles_manage && !r.ends_on" class="btn small ghost danger" @click="endRole(r)">Afslut</button></td>
                </tr>
                <tr v-if="!m.roles.length"><td class="muted">Ingen roller ud over medlem.</td></tr>
            </tbody></table>
        </div>

        <div class="card" v-if="can.roles_manage || m.permissions.length">
            <div class="row between"><h2 style="margin:0">Personlige rettigheder</h2><button v-if="can.roles_manage" class="btn small" @click="openGrant">Giv rettighed</button></div>
            <p class="small muted" style="margin-top:6px">En enkelt rettighed uden hele rollen, fx at et medlem må godkende nye medlemmer uden at sidde i bestyrelsen.</p>
            <table style="margin-top:8px"><tbody>
                <tr v-for="p in m.permissions" :key="p.id" :style="p.ends_on && p.ends_on < today ? 'opacity:.55' : ''">
                    <td><strong>{{ p.label }}</strong><div v-if="p.note" class="small muted">{{ p.note }}</div></td>
                    <td class="small">{{ dato(p.starts_on) }} – {{ p.ends_on ? dato(p.ends_on) : 'nu' }}</td><td class="small muted">givet af {{ p.granted_by || '–' }}</td>
                    <td class="num"><button v-if="can.roles_manage && (!p.ends_on || p.ends_on >= today)" class="btn small ghost danger" @click="endGrant(p)">Afslut</button></td>
                </tr>
                <tr v-if="!m.permissions.length"><td class="muted">Ingen.</td></tr>
            </tbody></table>
            <details v-if="m.can.length" style="margin-top:10px"><summary class="small">Hvad må {{ m.profile.name.split(' ')[0] }} i dag? ({{ m.can.length }})</summary>
                <ul class="small" style="margin:6px 0 0 18px;padding:0"><li v-for="c in m.can" :key="c">{{ c }}</li></ul>
            </details>
        </div>

        <div class="card" v-if="m.invoices.length">
            <h2>Betalinger</h2>
            <ul class="mlist">
                <li v-for="i in m.invoices" :key="i.id">
                    <div class="l1"><span>{{ i.description }}</span><strong class="num">{{ i.amount_fmt }}</strong></div>
                    <div class="l2">
                        <span>{{ i.paid_at ? 'betalt ' + dato(i.paid_at) + ' · ' + i.method : 'forfald ' + dato(i.due_on) }}<template v-if="i.payer && i.payer !== m.profile.name"> · betales af {{ i.payer }}</template></span>
                        <span :class="['tag', { paid: 'ok', overdue: 'bad', open: 'info' }[i.status]]">{{ i.status_label }}</span>
                    </div>
                    <div v-if="can.finance_write && ['open', 'overdue'].includes(i.status)" class="acts"><button class="btn small" @click="paying = i">Registrér betaling</button></div>
                </li>
            </ul>
        </div>

        <div class="card">
            <h2>Seneste aktiviteter</h2>
            <ul class="list"><li v-for="r in m.registrations" :key="r.activity_id" class="row between">
                <RouterLink :to="`/aktiviteter/${r.activity_id}`">{{ r.title }}</RouterLink>
                <span class="small muted">{{ dato(r.starts_at) }} <span v-if="r.attended === true" class="tag ok">Mødt</span><span v-else-if="r.attended === false" class="tag bad">Udeblev</span><span v-else-if="r.status === 'cancelled'" class="tag">Afmeldt</span></span>
            </li><li v-if="!m.registrations.length" class="muted">Ingen.</li></ul>
        </div>

        <div class="card">
            <h2>Ændringslog</h2>
            <ul class="timeline">
                <li v-for="(l, i) in m.log" :key="i"><strong>{{ auditLabels[l.action] || l.action }}</strong> · {{ tidspunkt(l.at) }} af {{ l.by || 'systemet' }}</li>
                <li v-if="!m.log.length" class="muted">Ingen ændringer.</li>
            </ul>
            <p v-if="m.views" class="small" style="margin:10px 0 0">Set i kartoteket {{ m.views.count }} {{ m.views.count === 1 ? 'gang' : 'gange' }} af {{ m.views.people.join(', ') }} · seneste {{ tidspunkt(m.views.last) }}</p>
            <p class="small muted">Også opslag i kartoteket bliver logget (persondata).</p>
        </div>

        <RegisterPayment v-if="paying" :invoice="paying" @close="paying = null" @done="paying = null; load()" />

        <Modal v-if="showRole" title="Tildel rolle" @close="showRole = false">
            <div class="stack">
                <label class="field"><span>Rolle</span><select v-model="nr.role"><option v-for="(l, k) in roles" :key="k" :value="k">{{ l }}</option></select></label>
                <label class="field"><span>Fra dato</span><input type="date" v-model="nr.starts_on"></label>
                <button class="btn primary block" @click="addRole">Tildel</button>
            </div>
        </Modal>

        <Modal v-if="grant" title="Giv rettighed" @close="grant = null">
            <div class="stack">
                <label class="field"><span>Rettighed</span><select v-model="grant.permission">
                    <optgroup v-for="(list, g) in grantGroups" :key="g" :label="g"><option v-for="p in list" :key="p.key" :value="p.key">{{ p.label }}</option></optgroup>
                </select></label>
                <p v-if="grantInfo" class="small muted" style="margin:0">{{ grantInfo.description }} Følger i dag rollerne: {{ grantInfo.roles.map(r => roles[r]).join(', ') || 'ingen' }}.</p>
                <label class="field"><span>Fra dato</span><input type="date" v-model="grant.starts_on"></label>
                <label class="field"><span>Til og med (valgfri)</span><input type="date" v-model="grant.ends_on"></label>
                <label class="field"><span>Bemærkning (valgfri)</span><input v-model="grant.note" placeholder="fx hjælper kassereren med indmeldelser"></label>
                <button class="btn primary block" :disabled="!grant.permission" @click="saveGrant">Giv rettighed</button>
            </div>
        </Modal>

        <Modal v-if="edit" title="Ret medlemsdata" @close="edit = null">
            <div class="stack">
                <label class="field"><span>Navn</span><input v-model="edit.name"></label>
                <label class="field"><span>E-mail</span><input v-model="edit.email"></label>
                <label class="field"><span>Telefon</span><input v-model="edit.phone"></label>
                <label class="field"><span>Status</span><select v-model="edit.status"><option value="pending">Afventer</option><option value="active">Aktiv</option><option value="passive">Passiv</option><option value="resigned">Udmeldt</option></select></label>

                <button class="btn primary block" @click="saveEdit">Gem</button>
            </div>
        </Modal>
    </div>
</template>
<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import Modal from '../components/Modal.vue';
import RegisterPayment from '../components/RegisterPayment.vue';
import { auditLabels } from '../auditLabels';
import { api, store, dato, tidspunkt, toast } from '../api';
const props = defineProps({ id: String });
const year = new Date().getFullYear(); const today = new Date().toISOString().slice(0, 10);
const can = computed(() => store.user.can);
const paying = ref(null);
const roles = { trainer: 'Træner', board: 'Bestyrelse', treasurer: 'Kasserer', auditor: 'Revisor', admin: 'Administrator' };
const m = ref(null); const showRole = ref(false); const edit = ref(null);
const nr = reactive({ role: 'trainer', starts_on: today });
async function load() { m.value = await api(`members/${props.id}`); }
onMounted(load); watch(() => props.id, load);
async function approve() { await api(`members/${props.id}/approve`, { method: 'POST' }); toast('Medlemmet er aktiveret og har fået besked'); load(); }
async function addRole() { try { await api(`members/${props.id}/roles`, { method: 'POST', body: nr }); showRole.value = false; toast('Rolle tildelt'); load(); } catch (e) { toast(e.message, 'error'); } }
async function endRole(r) { await api(`roles/${r.id}/end`, { method: 'PUT', body: { ends_on: today } }); toast('Rollen er afsluttet'); load(); }
const grant = ref(null); const perms = ref([]);
const grantGroups = computed(() => perms.value.filter(p => !p.locked).reduce((g, p) => ((g[p.group] ||= []).push(p), g), {}));
const grantInfo = computed(() => perms.value.find(p => p.key === grant.value?.permission));
async function openGrant() {
    if (!perms.value.length) perms.value = (await api('settings/permissions')).permissions;
    grant.value = { permission: '', starts_on: today, ends_on: '', note: '' };
}
async function saveGrant() {
    try { await api(`members/${props.id}/permissions`, { method: 'POST', body: { ...grant.value, ends_on: grant.value.ends_on || null } }); grant.value = null; toast('Rettigheden er givet'); load(); }
    catch (e) { toast(e.message, 'error'); }
}
async function endGrant(p) { await api(`user-permissions/${p.id}/end`, { method: 'PUT', body: { ends_on: today } }); toast('Rettigheden er afsluttet'); load(); }
async function saveEdit() { try { await api(`members/${props.id}`, { method: 'PUT', body: edit.value }); edit.value = null; toast('Gemt'); load(); } catch (e) { toast(e.message, 'error'); } }
</script>
