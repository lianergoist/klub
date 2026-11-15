<template>
    <Modal title="Hjælp" @close="help.open = false">
        <form class="ask" @submit.prevent="ask">
            <label class="field"><span>Spørg om noget i systemet</span>
                <div class="askrow">
                    <input v-model="question" placeholder="Fx: Hvordan tilmelder jeg mit barn?" maxlength="300" enterkeyhint="send" :disabled="asking">
                    <button class="btn primary" :disabled="!question.trim() || asking">{{ asking ? 'Tænker …' : 'Spørg' }}</button>
                </div>
            </label>
            <p class="small muted" style="margin:4px 0 0">Svaret laves af AI (Claude) ud fra hjælpeteksten og kan tage fejl. Dit navn sendes ikke med, så skriv ikke personoplysninger i spørgsmålet.</p>
        </form>
        <div v-if="asking" class="answer muted small" role="status"><span class="spinner-inline"></span> Claude læser hjælpeteksten …</div>
        <div v-else-if="reply" :class="['banner', reply.error ? 'warn' : 'info', 'answer']" role="status" aria-live="polite">
            <div v-if="reply.error">{{ reply.error }}</div>
            <template v-else>
                <div class="help-text ai" v-html="answerHtml(reply.answer)"></div>
                <div v-if="replyTopics.length" class="small" style="margin-top:6px">Læs mere:
                    <template v-for="(t, i) in replyTopics" :key="t.id"><a href="#" @click.prevent="openTopic(t.id)">{{ t.title }}</a><span v-if="i < replyTopics.length - 1"> · </span></template>
                </div>
                <div v-if="!reply.answered" class="small" style="margin-top:6px">Kan du ikke finde svaret, så kontakt bestyrelsen.</div>
            </template>
        </div>

        <template v-if="topicSection">
            <article class="help-text">
                <h3>{{ topicSection.title }}</h3>
                <div v-html="topicSection.html"></div>
            </article>
            <button class="btn ghost" style="padding-inline:0" @click="help.topic = null">‹ Alle emner</button>
        </template>
        <template v-else>
            <input v-model="search" type="search" placeholder="Søg i hjælpen" aria-label="Søg i hjælpen" style="margin:6px 0 12px">
            <template v-if="!search && onPage.length">
                <div class="sect">Hjælp til denne side</div>
                <details v-for="s in onPage" :key="'p' + s.id" class="help-item" open>
                    <summary>{{ s.title }}</summary><div class="help-text" v-html="s.html"></div>
                </details>
            </template>
            <template v-for="g in groups" :key="g.name">
                <div class="sect">{{ g.name }}</div>
                <details v-for="s in g.items" :key="s.id" class="help-item" :open="!!search">
                    <summary>{{ s.title }}</summary><div class="help-text" v-html="s.html"></div>
                </details>
            </template>
            <p v-if="search && !groups.length" class="muted">Ingen emner matcher "{{ search }}". Prøv at stille spørgsmålet i feltet ovenfor.</p>
        </template>
    </Modal>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useRoute } from 'vue-router';
import Modal from './Modal.vue';
import { help, visibleSections, answerHtml } from '../help';
import { api } from '../api';
import { track } from '../usage';

const route = useRoute();
const question = ref('');
const asking = ref(false);
const reply = ref(null);
// Emner fra svaret, som brugeren har adgang til (Læs mere-links)
const replyTopics = computed(() => (reply.value?.topics || []).map((id) => all.value.find((s) => s.id === id)).filter(Boolean));
function openTopic(id) { help.topic = id; track('spoerg', 'laes_mere', { topic: id }); }
const search = ref('');

const all = computed(() => visibleSections());
const topicSection = computed(() => help.topic && all.value.find((s) => s.id === help.topic));
const pagePath = computed(() => route.matched.at(-1)?.path || route.path);
const onPage = computed(() => all.value.filter((s) => s.pages.includes(pagePath.value)));
const groups = computed(() => {
    const q = search.value.trim().toLowerCase();
    // Uden søgning står sidens egne afsnit allerede øverst og gentages ikke
    const skip = q ? [] : onPage.value.map((s) => s.id);
    const hits = all.value.filter((s) => !skip.includes(s.id) && (!q || s.title.toLowerCase().includes(q) || s.text.toLowerCase().includes(q)));
    const by = [];
    for (const s of hits) {
        let g = by.find((x) => x.name === s.group);
        if (!g) by.push(g = { name: s.group, items: [] });
        g.items.push(s);
    }
    return by;
});

async function ask() {
    const q = question.value.trim();
    if (!q || asking.value) return;
    asking.value = true; reply.value = null;
    // Selve spørgsmålet gemmes ikke, før bestyrelsen har taget stilling (se Funktionsoversigt.md). Svaret logges på serveren.
    track('spoerg', 'sendt', { len: q.length });
    try { reply.value = await api('help/ask', { method: 'POST', body: { question: q, page: pagePath.value } }); }
    catch (e) { reply.value = { error: e.message }; }
    finally { asking.value = false; }
}
</script>

<style scoped>
.sect { font-size: .72rem; text-transform: uppercase; letter-spacing: .08em; color: var(--ink-3); margin: 14px 0 4px; }
.help-item { border-bottom: 1px solid var(--line); padding: 8px 0; }
.help-item summary { cursor: pointer; font-weight: 600; }
.help-text :deep(p) { margin: 8px 0; }
.help-text :deep(ul) { margin: 8px 0; padding-left: 20px; }
.help-text h3 { margin: 10px 0 4px; }
.askrow { display: flex; gap: 8px; }
.askrow input { flex: 1; min-width: 0; }
.askrow .btn { flex-shrink: 0; }
.answer { margin: 10px 0 4px; display: block; }
.help-text.ai :deep(p:first-child) { margin-top: 0; }
.help-text.ai :deep(p:last-child), .help-text.ai :deep(ol:last-child), .help-text.ai :deep(ul:last-child) { margin-bottom: 0; }
.help-text :deep(ol) { margin: 8px 0; padding-left: 22px; }
.spinner-inline { display: inline-block; width: 12px; height: 12px; border: 2px solid currentColor; border-right-color: transparent; border-radius: 50%; animation: spin .8s linear infinite; vertical-align: -1px; margin-right: 6px; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
