<template>
    <div v-if="!c" class="spinner"></div>
    <div v-else class="convo">
        <header class="convo-head">
            <RouterLink to="/beskeder" class="btn ghost small" aria-label="Tilbage"><Icon name="back" style="width:20px" /></RouterLink>
            <span class="avatar">{{ initials(c.with.name) }}</span>
            <strong style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ c.with.name }}</strong>
            <button class="btn ghost small" @click="menu = !menu" aria-label="Flere valg"><Icon name="more" style="width:20px" /></button>
            <div v-if="menu" class="menu card">
                <button class="btn ghost small block" @click="toggleBlock">{{ c.i_blocked ? 'Fjern blokering' : `Bloker ${c.with.name.split(' ')[0]}` }}</button>
            </div>
        </header>

        <div ref="scroller" class="msgs">
            <button v-if="c.more" class="btn small" style="align-self:center" @click="older">Vis ældre beskeder</button>
            <p v-if="!c.messages.length" class="muted small" style="text-align:center;margin-top:30px">Skriv den første besked til {{ c.with.name.split(' ')[0] }}.<br>Beskeder er private mellem jer to.</p>
            <template v-for="(m, i) in c.messages" :key="m.id">
                <div v-if="dayChange(i)" class="day">{{ dag(m.at) }}</div>
                <div :class="['msg', m.mine ? 'mine' : 'theirs']">
                    <div class="b">{{ m.body }}</div>
                    <div class="meta">{{ klok(m.at) }}<span v-if="m.mine && isLastMine(i)"> · {{ m.read ? 'Læst' : 'Sendt' }}</span>
                        <button v-if="!m.mine" class="rep" @click="reportMsg = m" aria-label="Anmeld besked"><Icon name="flag" style="width:12px" /></button>
                    </div>
                </div>
            </template>
        </div>

        <form v-if="c.can_send" class="composer" @submit.prevent="send">
            <textarea v-model="draft" rows="1" placeholder="Skriv en besked …" maxlength="4000" @keydown.enter.exact.prevent="send" @input="grow"></textarea>
            <button class="btn primary" :disabled="!draft.trim() || busy" aria-label="Send">Send</button>
        </form>
        <div v-else class="banner warn small" style="margin:8px 0 0">{{ c.cannot_reason }}</div>

        <Modal v-if="reportMsg" title="Anmeld besked" @close="reportMsg = null">
            <p class="small">Beskeden sendes til bestyrelsen sammen med din begrundelse. Ellers kan ingen andre end jer to se jeres beskeder.</p>
            <blockquote class="bubble-q">{{ reportMsg.body }}</blockquote>
            <label class="field"><span>Hvorfor anmelder du beskeden?</span><input v-model="reportReason" placeholder="Fx chikane, upassende indhold"></label>
            <label class="check small" style="margin-top:8px"><input type="checkbox" v-model="alsoBlock"> Bloker også {{ c.with.name.split(' ')[0] }}</label>
            <button class="btn primary block" style="margin-top:12px" :disabled="!reportReason.trim()" @click="report">Anmeld</button>
        </Modal>
    </div>
</template>

<script setup>
import { ref, nextTick, onMounted, onUnmounted, watch } from 'vue';
import Icon from '../components/Icon.vue';
import Modal from '../components/Modal.vue';
import { api, store, klok, toast } from '../api';

const props = defineProps({ id: String });
const c = ref(null); const draft = ref(''); const busy = ref(false); const scroller = ref(null); const menu = ref(false);
const reportMsg = ref(null); const reportReason = ref(''); const alsoBlock = ref(false);
let timer;

const initials = (n) => n.split(' ').map(x => x[0]).slice(0, 2).join('').toUpperCase();
const dag = (iso) => { const d = new Date(iso), t = new Date(); const y = new Date(); y.setDate(t.getDate() - 1);
    if (d.toDateString() === t.toDateString()) return 'I dag'; if (d.toDateString() === y.toDateString()) return 'I går';
    return d.toLocaleDateString('da-DK', { weekday: 'long', day: 'numeric', month: 'long' }); };
const dayChange = (i) => i === 0 || new Date(c.value.messages[i].at).toDateString() !== new Date(c.value.messages[i - 1].at).toDateString();
const isLastMine = (i) => !c.value.messages.slice(i + 1).some(m => m.mine);
const bottom = async () => { await nextTick(); if (scroller.value) scroller.value.scrollTop = scroller.value.scrollHeight; };
function grow(e) { e.target.style.height = 'auto'; e.target.style.height = Math.min(140, e.target.scrollHeight) + 'px'; }

async function load() { c.value = await api(`messages/${props.id}`); bottom(); refreshBadge(); }
async function poll() {
    if (!c.value || document.hidden) return;
    const msgs = c.value.messages;
    const last = msgs.at(-1)?.id || 0;
    // Hent de seneste beskeder igen: giver både nye beskeder og opdateret "Læst"-status
    const r = await api(`messages/${props.id}?after=${msgs.at(-20)?.id - 1 || 0}`);
    for (const m of r.messages) { const x = msgs.find(y => y.id === m.id); if (x) x.read = m.read; }
    const fresh = r.messages.filter(m => m.id > last);
    if (fresh.length) { msgs.push(...fresh); bottom(); refreshBadge(); }
    Object.assign(c.value, { can_send: r.can_send, cannot_reason: r.cannot_reason, i_blocked: r.i_blocked });
}
async function older() {
    const r = await api(`messages/${props.id}?before=${c.value.messages[0].id}`);
    const h = scroller.value.scrollHeight;
    c.value.messages.unshift(...r.messages); c.value.more = r.messages.length === 50;
    await nextTick(); scroller.value.scrollTop = scroller.value.scrollHeight - h;
}
async function send() {
    const body = draft.value.trim(); if (!body || busy.value) return;
    busy.value = true;
    try { const r = await api(`messages/${props.id}`, { method: 'POST', body: { body } }); c.value.messages.push({ id: r.id, body, mine: true, at: r.at, read: false }); draft.value = ''; bottom(); }
    catch (e) { toast(e.message, 'error'); } finally { busy.value = false; }
}
async function toggleBlock() {
    await api(`users/${c.value.with.id}/block`, { method: 'POST', body: { block: !c.value.i_blocked } });
    menu.value = false; toast(c.value.i_blocked ? 'Blokeringen er fjernet' : `${c.value.with.name} er blokeret og kan ikke skrive til dig`); load();
}
async function report() {
    await api(`message-items/${reportMsg.value.id}/report`, { method: 'POST', body: { reason: reportReason.value } });
    if (alsoBlock.value && !c.value.i_blocked) await api(`users/${c.value.with.id}/block`, { method: 'POST', body: { block: true } });
    reportMsg.value = null; reportReason.value = ''; alsoBlock.value = false; toast('Tak. Bestyrelsen har fået besked.'); load();
}
async function refreshBadge() { try { const u = await api('messages/unread'); if (store.user) { store.user.unread_messages = u.messages; store.user.unread = u.notifications; } } catch {} }

onMounted(() => { load(); timer = setInterval(poll, 6000); });
onUnmounted(() => clearInterval(timer));
watch(() => props.id, load);
</script>

<style scoped>
.convo { display: flex; flex-direction: column; height: calc(100dvh - 120px - env(safe-area-inset-bottom, 0px)); max-width: 680px; margin: 0 auto; }
@media (min-width: 900px) { .convo { height: calc(100dvh - 60px); } }
.convo-head { display: flex; align-items: center; gap: 8px; padding-bottom: 10px; border-bottom: 1px solid var(--line); position: relative; }
.menu { position: absolute; right: 0; top: 48px; z-index: 5; padding: 6px; margin: 0; min-width: 200px; }
.avatar { flex-shrink: 0; width: 36px; height: 36px; border-radius: 50%; display: grid; place-items: center; background: var(--accent-soft); color: var(--accent); font-weight: 700; font-size: .8rem; }
.msgs { flex: 1; overflow-y: auto; display: flex; flex-direction: column; gap: 4px; padding: 12px 2px; }
.day { align-self: center; font-size: .75rem; color: var(--ink-3); margin: 10px 0 4px; }
.day::first-letter { text-transform: uppercase; }
.msg { max-width: 80%; display: flex; flex-direction: column; }
.msg.mine { align-self: flex-end; align-items: flex-end; }
.msg.theirs { align-self: flex-start; }
.msg .b { padding: 8px 12px; border-radius: 16px; white-space: pre-line; overflow-wrap: anywhere; }
.msg.mine .b { background: var(--accent); color: #fff; border-bottom-right-radius: 5px; }
.msg.theirs .b { background: var(--surface); border: 1px solid var(--line); border-bottom-left-radius: 5px; }
.msg .meta { font-size: .7rem; color: var(--ink-3); margin: 2px 6px 6px; display: flex; gap: 6px; align-items: center; }
.rep { border: 0; background: none; color: var(--ink-3); cursor: pointer; padding: 0; opacity: .6; }
.rep:hover { opacity: 1; color: var(--bad); }
.composer { display: flex; gap: 8px; align-items: flex-end; padding-top: 8px; border-top: 1px solid var(--line); }
.composer textarea { min-height: 42px; max-height: 140px; resize: none; border-radius: 20px; padding: 10px 14px; }
.bubble-q { margin: 8px 0 12px; padding: 8px 12px; border-left: 3px solid var(--line); color: var(--ink-2); white-space: pre-line; }
</style>
