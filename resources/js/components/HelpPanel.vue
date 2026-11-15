<template>
    <Modal title="Hjælp" @close="help.open = false">
        <template v-if="topicSection">
            <article class="help-text">
                <h3>{{ topicSection.title }}</h3>
                <div v-html="topicSection.html"></div>
            </article>
            <button class="btn ghost" style="padding-inline:0" @click="help.topic = null">‹ Alle emner</button>
        </template>
        <template v-else>
            <input v-model="search" type="search" placeholder="Søg i hjælpen" aria-label="Søg i hjælpen" style="margin:0 0 12px">
            <template v-if="!search && onPage.length">
                <div class="sect">Hjælp til denne side</div>
                <details v-for="s in onPage" :key="'p' + s.id" class="help-item" open>
                    <summary>{{ s.title }}</summary><div class="help-text" v-html="s.html"></div>
                </details>
            </template>
            <template v-for="g in groups" :key="g.name">
                <div class="sect">{{ g.name }}</div>
                <details v-for="s in g.items" :key="s.id" class="help-item" :open="!!search">
                    <summary @click="!$event.currentTarget.parentElement.open && read(s.id)">{{ s.title }}</summary><div class="help-text" v-html="s.html"></div>
                </details>
            </template>
            <p v-if="search && !groups.length" class="muted">Ingen emner matcher "{{ search }}". Kommer du ikke videre, så kontakt bestyrelsen.</p>
        </template>
    </Modal>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import Modal from './Modal.vue';
import { help, visibleSections } from '../help';
import { track } from '../usage';

const route = useRoute();
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

// Brugslog til forbedring af hjælpen: hvilke emner der læses, og hvor søgninger ikke finder noget.
// Søgeordet gemmes ikke (indtastet tekst logges ikke) – kun siden og antal tegn.
const read = (id) => track('hjaelp', 'emne', { topic: id });
let searchTimer = null;
watch(search, (q) => {
    clearTimeout(searchTimer);
    if (!q.trim()) return;
    searchTimer = setTimeout(() => track('hjaelp', groups.value.length ? 'soegt' : 'soegt_uden_resultat', { page: pagePath.value, len: q.trim().length }), 1500);
});
</script>

<style scoped>
.sect { font-size: .72rem; text-transform: uppercase; letter-spacing: .08em; color: var(--ink-3); margin: 14px 0 4px; }
.help-item { border-bottom: 1px solid var(--line); padding: 8px 0; }
.help-item summary { cursor: pointer; font-weight: 600; }
.help-text :deep(p) { margin: 8px 0; }
.help-text :deep(ul) { margin: 8px 0; padding-left: 20px; }
.help-text h3 { margin: 10px 0 4px; }
.help-text :deep(ol) { margin: 8px 0; padding-left: 22px; }
</style>
