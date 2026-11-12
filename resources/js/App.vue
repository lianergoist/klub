<template>
    <div v-if="store.user && !route.meta.public && !route.meta.guest && store.user.status !== 'guest'" class="shell">
        <aside class="sidebar">
            <div class="brandmark brand-default"><img src="/icon.svg" alt=""> Klubben</div>
            <div class="brand-logo" style="padding:0 10px 18px"><img src="/brand/surfhorsens-logo.webp" alt="SurfHorsens" style="width:150px;height:auto;display:block"></div>
            <nav>
                <RouterLink to="/aktiviteter"><Icon name="cal" /> Aktiviteter</RouterLink>
                <RouterLink to="/opslag"><Icon name="chat" /> Opslag</RouterLink>
                <RouterLink v-if="can.portal" to="/traener"><Icon name="whistle" /> Trænerportal</RouterLink>
                <RouterLink v-if="can.equipment" to="/udstyr"><Icon name="board" /> Udstyr</RouterLink>
                <RouterLink to="/beskeder" :class="{ 'router-link-active': route.path.startsWith('/indbakke') }"><Icon name="inbox" /> Indbakke <span v-if="badge" class="dot">{{ badge }}</span></RouterLink>
                <RouterLink to="/profil"><Icon name="user" /> Min profil</RouterLink>
                <RouterLink to="/kvittering"><Icon name="receipt" /> Indsend kvittering</RouterLink>
                <template v-if="can.members_view || can.finance_read || can.settings">
                    <div class="sect">Administration</div>
                    <RouterLink v-if="can.members_view" to="/medlemmer"><Icon name="users" /> Medlemmer</RouterLink>
                    <a v-if="can.finance_read && IS_APP" href="#" @click.prevent="openFinance"><Icon name="money" /> Økonomi ↗</a>
                    <RouterLink v-else-if="can.finance_read" to="/okonomi"><Icon name="money" /> Økonomi</RouterLink>
                    <RouterLink v-if="can.settings" to="/indstillinger"><Icon name="gear" /> Indstillinger</RouterLink>
                </template>
            </nav>
            <div class="who">
                <div><strong>{{ store.user.name }}</strong></div>
                <div class="small" style="opacity:.8">{{ store.user.roles.map(r => roleLabel[r]).join(' · ') }}</div>
                <ThemeSwitch style="margin:10px 0 2px" />
                <button class="btn small" @click="logout"><Icon name="logout" style="width:16px" /> Log ud</button>
                <AppVersion style="opacity:.75" />
            </div>
        </aside>
        <main :class="['main', { wide: route.matched.some(r => r.meta.wide) }]">
            <RouterView />
        </main>
        <nav class="tabbar">
            <RouterLink to="/aktiviteter"><Icon name="cal" />Aktiviteter</RouterLink>
            <RouterLink to="/opslag"><Icon name="chat" />Opslag</RouterLink>
            <RouterLink to="/beskeder" :class="{ 'router-link-active': route.path.startsWith('/indbakke') }"><Icon name="inbox" />Indbakke<span v-if="badge" class="dot">{{ badge }}</span></RouterLink>
            <RouterLink to="/profil"><Icon name="user" />Profil</RouterLink>
            <RouterLink to="/mere"><Icon name="more" />Mere</RouterLink>
        </nav>
    </div>
    <RouterView v-else />
    <div v-if="store.toast" :class="['toast', store.toast.kind]" role="status">{{ store.toast.text }}</div>
</template>

<script setup>
import { computed, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Icon from './components/Icon.vue';
import ThemeSwitch from './components/ThemeSwitch.vue';
import AppVersion from './components/AppVersion.vue';
import { store, api, roleLabel, IS_APP, logout as doLogout } from './api';

const route = useRoute();
const router = useRouter();
const can = computed(() => store.user?.can || {});
async function openFinance() { (await import('./native')).openWeb('/okonomi'); }
const badge = computed(() => (store.user?.unread || 0) + (store.user?.unread_messages || 0));
// Hold ulæst-tælleren opdateret (beskeder fra andre medlemmer)
let poll;
onMounted(() => { poll = setInterval(async () => {
    if (!store.user || document.hidden) return;
    try { const u = await api('messages/unread'); store.user.unread = u.notifications; store.user.unread_messages = u.messages; } catch {}
}, 30000); });
onUnmounted(() => clearInterval(poll));

async function logout() {
    await doLogout();
    router.push('/login');
}
defineExpose({ logout });
</script>
