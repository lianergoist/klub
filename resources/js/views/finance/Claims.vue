<template>
    <div>
        <div class="chips" style="margin-bottom:12px">
            <button v-for="s in tabs" :key="s.id" :class="['chip', { on: status === s.id }]" @click="status = s.id">{{ s.label }}</button>
        </div>
        <p v-if="status === 'submitted' && can.finance_write" class="small muted" style="margin:0 0 12px">
            Overfør pengene via MobilePay eller netbank, og bogfør derefter udlægget. Kvitteringen bliver bilag på posteringen, og medlemmet får besked.
        </p>

        <div v-if="list === null" class="spinner"></div>
        <div v-else-if="!list.length" class="card empty">{{ status === 'submitted' ? 'Ingen udlæg venter. 👍' : 'Ingen udlæg.' }}</div>
        <div v-else class="claims">
            <div v-for="c in list" :key="c.id" class="card claim">
                <a :href="c.file_url" target="_blank" rel="noopener" class="thumb" title="Åbn kvittering">
                    <img v-if="!c.is_pdf" :src="c.file_url" alt="Kvittering" loading="lazy">
                    <span v-else class="pdf"><Icon name="file" style="width:28px" />PDF</span>
                </a>
                <div class="body">
                    <div class="row between" style="align-items:flex-start">
                        <div><strong style="font-size:1.1rem">{{ c.amount_fmt }}</strong> <span class="muted small">· {{ c.member }} · {{ dato(c.created_at) }}</span></div>
                        <span :class="['tag', statusCls[c.status]]">{{ statusText[c.status] }}</span>
                    </div>
                    <p style="margin:6px 0;white-space:pre-line">{{ c.description }}</p>
                    <div class="payto">
                        <template v-if="c.payout_method === 'mobilepay'"><span class="muted small">MobilePay til</span> <strong>{{ c.mobilepay_phone }}</strong></template>
                        <template v-else><span class="muted small">Bank</span> reg. <strong>{{ c.bank_reg }}</strong> konto <strong>{{ c.bank_account }}</strong></template>
                    </div>
                    <p v-if="c.status === 'rejected'" class="small" style="margin:6px 0 0;color:var(--bad)">Afvist af {{ c.handled_by }} {{ dato(c.handled_at) }}: {{ c.reject_reason }}</p>
                    <p v-if="c.status === 'paid'" class="small muted" style="margin:6px 0 0">Bogført af {{ c.handled_by }} {{ dato(c.handled_at) }} som postering #{{ c.entry_id }}.</p>
                    <div v-if="c.status === 'submitted' && can.finance_write" class="row" style="gap:8px;margin-top:10px">
                        <button class="btn primary small" @click="openPay(c)">Bogfør som udbetalt</button>
                        <button class="btn small danger" @click="openReject(c)">Afvis</button>
                    </div>
                </div>
            </div>
        </div>

        <Modal v-if="paying" title="Bogfør udlæg" @close="paying = null">
            <p class="small" style="margin-top:0">{{ paying.member }} · {{ paying.payout_label }}</p>
            <div class="stack">
                <label class="field"><span>Udgiftskonto</span>
                    <select v-model="pay.account_id" required><option :value="null" disabled>Vælg konto</option><option v-for="a in expenseAccounts" :key="a.id" :value="a.id">{{ a.number }} {{ a.name }}</option></select>
                </label>
                <div class="grid c2">
                    <label class="field"><span>Dato</span><input v-model="pay.entry_date" type="date" required></label>
                    <label class="field"><span>Beløb (kr.)</span><input v-model.number="pay.kr" type="number" min="0.01" step="0.01" required></label>
                </div>
                <label class="field"><span>Posteringstekst</span><input v-model="pay.text" maxlength="200"></label>
            </div>
            <p v-if="pay.error" class="banner bad small">{{ pay.error }}</p>
            <button class="btn primary block" style="margin-top:12px" :disabled="!pay.account_id || busy" @click="savePay">Bogfør og giv besked</button>
        </Modal>

        <Modal v-if="rejecting" title="Afvis udlæg" @close="rejecting = null">
            <p class="small" style="margin-top:0">{{ rejecting.member }} · {{ rejecting.amount_fmt }}. Medlemmet får begrundelsen.</p>
            <label class="field"><span>Begrundelse</span><textarea v-model="rejectReason" placeholder="Fx kvitteringen kan ikke læses – send et nyt billede"></textarea></label>
            <button class="btn danger block" style="margin-top:12px" :disabled="!rejectReason || busy" @click="saveReject">Afvis og giv besked</button>
        </Modal>
    </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue';
import Icon from '../../components/Icon.vue';
import Modal from '../../components/Modal.vue';
import { api, store, toast, dato } from '../../api';

const can = computed(() => store.user.can);
const tabs = [{ id: 'submitted', label: 'Afventer' }, { id: 'paid', label: 'Udbetalt' }, { id: 'rejected', label: 'Afvist' }];
const statusText = { submitted: 'Afventer', paid: 'Udbetalt', rejected: 'Afvist' };
const statusCls = { submitted: 'warn', paid: 'ok', rejected: 'bad' };
const status = ref('submitted'); const list = ref(null); const accounts = ref([]); const busy = ref(false);
const paying = ref(null); const pay = reactive({ account_id: null, entry_date: '', kr: 0, text: '', error: '' });
const rejecting = ref(null); const rejectReason = ref('');
const expenseAccounts = computed(() => accounts.value.filter(a => a.type === 'expense'));

async function load() { list.value = null; list.value = await api(`finance/expense-claims?status=${status.value}`); }
watch(status, load);
onMounted(async () => { load(); accounts.value = await api('finance/accounts'); });

function openPay(c) {
    Object.assign(pay, { account_id: null, entry_date: new Date().toISOString().slice(0, 10), kr: c.amount / 100, text: `Udlæg ${c.member}: ${c.description.slice(0, 120)}`, error: '' });
    paying.value = c;
}
async function savePay() {
    busy.value = true; pay.error = '';
    try {
        await api(`finance/expense-claims/${paying.value.id}/pay`, { method: 'POST', body: { account_id: pay.account_id, entry_date: pay.entry_date, amount: Math.round(pay.kr * 100), text: pay.text } });
        paying.value = null; toast('Udlægget er bogført, og medlemmet har fået besked'); load();
    } catch (e) { pay.error = e.message; } finally { busy.value = false; }
}
function openReject(c) { rejectReason.value = ''; rejecting.value = c; }
async function saveReject() {
    busy.value = true;
    try {
        await api(`finance/expense-claims/${rejecting.value.id}/reject`, { method: 'POST', body: { reason: rejectReason.value } });
        rejecting.value = null; toast('Udlægget er afvist, og medlemmet har fået besked'); load();
    } catch (e) { toast(e.message, 'error'); } finally { busy.value = false; }
}
</script>

<style scoped>
.claims { display: grid; gap: 12px; }
.claim { display: flex; gap: 14px; margin: 0; }
.thumb { flex-shrink: 0; width: 96px; height: 120px; border-radius: 10px; overflow: hidden; background: var(--surface-2); display: grid; place-items: center; border: 1px solid var(--line); }
.thumb img { width: 100%; height: 100%; object-fit: cover; }
.pdf { display: flex; flex-direction: column; align-items: center; gap: 4px; font-weight: 700; font-size: .8rem; color: var(--ink-2); }
.body { flex: 1; min-width: 0; }
.payto { background: var(--surface-2); border-radius: 10px; padding: 8px 10px; font-size: .95rem; }
</style>
