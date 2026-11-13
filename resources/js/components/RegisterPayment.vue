<template>
    <Modal title="Registrér betaling" @close="$emit('close')">
        <p style="margin-top:0">{{ invoice.description }}<span v-if="invoice.payer"> · {{ invoice.payer }}</span><br><strong style="font-size:1.2rem">{{ invoice.amount_fmt }}</strong></p>
        <div class="stack">
            <div>
                <span class="lbl">Betalt med</span>
                <div class="pay-choice">
                    <label :class="['opt', { on: method === 'cash' }]"><input type="radio" v-model="method" value="cash"> Kontant</label>
                    <label :class="['opt', { on: method === 'bank' }]"><input type="radio" v-model="method" value="bank"> Bankoverførsel</label>
                </div>
            </div>
            <label class="field"><span>Reference (valgfri)</span><input v-model="reference" :placeholder="method === 'cash' ? 'Fx betalt i klubhuset' : 'Fx tekst fra kontoudtog'"></label>
            <p v-if="error" class="banner bad small" style="margin-bottom:0">{{ error }}</p>
            <button class="btn primary block" :disabled="busy" @click="save">{{ busy ? 'Registrerer …' : 'Registrér og bogfør' }}</button>
        </div>
    </Modal>
</template>
<script setup>
import { ref } from 'vue';
import Modal from './Modal.vue';
import { api, toast } from '../api';
import { isMobile } from '../mobile';

const props = defineProps({ invoice: Object });
const emit = defineEmits(['close', 'done']);
// På telefonen er det typisk kontanter i klubhuset; ved computeren en bankoverførsel
const method = ref(isMobile.value ? 'cash' : 'bank'); const reference = ref(''); const busy = ref(false); const error = ref('');
async function save() {
    busy.value = true; error.value = '';
    try {
        await api(`finance/invoices/${props.invoice.id}/manual`, { method: 'POST', body: { method: method.value, reference: reference.value } });
        toast('Betaling registreret og bogført'); emit('done');
    } catch (e) { error.value = e.message; } finally { busy.value = false; }
}
</script>
<style scoped>
.lbl { display: block; font-size: .85rem; font-weight: 600; color: var(--ink-2); margin-bottom: 6px; }
.pay-choice { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.opt { display: flex; align-items: center; justify-content: center; gap: 8px; min-height: 48px; border: 1px solid var(--line); border-radius: 12px; font-weight: 600; cursor: pointer; background: var(--surface); }
.opt.on { border-color: var(--accent); background: var(--accent-soft); color: var(--accent); }
.opt input { accent-color: var(--accent); width: 20px; height: 20px; min-height: 0; margin: 0; padding: 0; flex: 0 0 auto; }
</style>
