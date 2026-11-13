<template>
    <div>
        <h1 v-if="!embedded" style="margin-bottom:4px">Indsend kvittering</h1>
        <h2 v-else style="margin:0 4px 4px">Indsend kvittering</h2>
        <p class="muted small" style="margin:0 4px 14px">Har du lagt ud for klubben? Send kvitteringen til kassereren, så får du pengene tilbage.</p>

        <form class="card stack" @submit.prevent="submit">
            <div>
                <span class="lbl">Kvittering</span>
                <FilePicker v-model="file" hint="Et billede af kvitteringen eller en PDF" />
            </div>

            <label class="field"><span>Hvad drejer det sig om?</span>
                <textarea v-model="f.description" required maxlength="1000" placeholder="Fx kaffe og kage til arbejdsdagen, eller ny tamp til racket"></textarea>
            </label>

            <label class="field"><span>Beløb (kr.)</span>
                <input v-model="amountText" inputmode="decimal" required placeholder="Fx 249,50" autocomplete="off">
            </label>

            <div>
                <span class="lbl">Hvordan vil du have pengene?</span>
                <div class="pay-choice">
                    <label :class="['opt', { on: f.payout_method === 'mobilepay' }]"><input type="radio" v-model="f.payout_method" value="mobilepay"> MobilePay</label>
                    <label :class="['opt', { on: f.payout_method === 'bank' }]"><input type="radio" v-model="f.payout_method" value="bank"> Bankkonto</label>
                </div>
                <label v-if="f.payout_method === 'mobilepay'" class="field" style="margin-top:10px"><span>MobilePay-nummer</span>
                    <input v-model="f.mobilepay_phone" type="tel" inputmode="tel" required placeholder="8 cifre" autocomplete="tel">
                    <small v-if="suggested && f.mobilepay_phone === suggested" class="muted">Dit telefonnummer fra din profil – ret det, hvis pengene skal til et andet nummer.</small>
                </label>
                <div v-else class="grid bank" style="margin-top:10px">
                    <label class="field"><span>Reg. nr.</span><input v-model="f.bank_reg" inputmode="numeric" maxlength="4" required placeholder="4 cifre" pattern="\d{4}"></label>
                    <label class="field"><span>Kontonummer</span><input v-model="f.bank_account" inputmode="numeric" maxlength="10" required placeholder="Op til 10 cifre" pattern="\d{1,10}"></label>
                </div>
            </div>

            <p v-if="error" class="banner bad" style="margin-bottom:0">{{ error }}</p>
            <button class="btn primary block" :disabled="busy">{{ busy ? 'Sender …' : 'Send til kassereren' }}</button>
        </form>

        <h2 style="margin:22px 4px 10px">Mine udlæg</h2>
        <div v-if="claims === null" class="spinner"></div>
        <div v-else-if="!claims.length" class="card empty">Du har ikke indsendt nogen kvitteringer endnu.</div>
        <div v-else class="card" style="padding:4px 16px">
            <ul class="list">
                <li v-for="c in claims" :key="c.id">
                    <div class="row between" style="align-items:flex-start;flex-wrap:nowrap">
                        <div style="min-width:0">
                            <strong>{{ c.amount_fmt }}</strong> <span class="small muted">· {{ dato(c.created_at) }}</span>
                            <div class="small clamp">{{ c.description }}</div>
                            <div class="small muted">{{ c.payout_label }}</div>
                            <div v-if="c.status === 'rejected'" class="small" style="color:var(--bad)">Afvist: {{ c.reject_reason }}</div>
                        </div>
                        <div style="text-align:right;flex-shrink:0">
                            <span v-if="c.awaiting_approval" class="tag warn">Afventer revisor</span>
                            <span v-else :class="['tag', statusCls[c.status]]">{{ statusText[c.status] }}</span>
                            <div><a :href="c.file_url" target="_blank" rel="noopener" class="small">Se kvittering</a></div>
                        </div>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import FilePicker from '../components/FilePicker.vue';

defineProps({ embedded: Boolean });
import { api, toast, dato, parseKr } from '../api';

const f = reactive({ description: '', payout_method: 'mobilepay', mobilepay_phone: '', bank_reg: '', bank_account: '' });
const amountText = ref(''); const file = ref(null);
const claims = ref(null); const suggested = ref(''); const busy = ref(false); const error = ref('');
const statusText = { submitted: 'Afventer kasserer', paid: 'Udbetalt', rejected: 'Afvist' };
const statusCls = { submitted: 'warn', paid: 'ok', rejected: 'bad' };

const amountOre = computed(() => parseKr(amountText.value));

async function load() {
    const r = await api('expense-claims');
    claims.value = r.claims; suggested.value = r.suggested_phone || '';
    if (!f.mobilepay_phone) f.mobilepay_phone = suggested.value;
    // Genbrug sidst brugte bankkonto
    const lastBank = r.claims.find(c => c.payout_method === 'bank');
    if (lastBank && !f.bank_reg) { f.bank_reg = lastBank.bank_reg; f.bank_account = lastBank.bank_account; }
}
onMounted(load);

function clearFile() { file.value = null; }

async function submit() {
    error.value = '';
    if (!file.value) return (error.value = 'Vedhæft et billede af kvitteringen.');
    if (!amountOre.value) return (error.value = 'Skriv beløbet, fx 249,50.');
    const fd = new FormData();
    fd.append('file', file.value);
    fd.append('description', f.description);
    fd.append('amount', amountOre.value);
    fd.append('payout_method', f.payout_method);
    if (f.payout_method === 'mobilepay') fd.append('mobilepay_phone', f.mobilepay_phone);
    else { fd.append('bank_reg', f.bank_reg.trim()); fd.append('bank_account', f.bank_account.trim()); }
    busy.value = true;
    try {
        await api('expense-claims', { method: 'POST', form: fd });
        toast('Kvitteringen er sendt til kassereren');
        f.description = ''; amountText.value = ''; clearFile();
        await load();
    } catch (e) { error.value = e.message; } finally { busy.value = false; }
}
</script>

<style scoped>
.lbl { display: block; font-size: .85rem; font-weight: 600; color: var(--ink-2); margin-bottom: 6px; }
.pay-choice { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.opt { display: flex; align-items: center; justify-content: center; gap: 8px; min-height: 48px; border: 1px solid var(--line); border-radius: 12px; font-weight: 600; cursor: pointer; background: var(--surface); }
.opt.on { border-color: var(--accent); background: var(--accent-soft); color: var(--accent); }
.opt input { accent-color: var(--accent); width: 20px; height: 20px; min-height: 0; margin: 0; padding: 0; flex: 0 0 auto; }
.bank { grid-template-columns: 1fr 2fr; gap: 10px; }
.clamp { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
</style>
