<template>
    <div v-if="store.user && !route.meta.public && !route.meta.guest && store.user.status !== 'guest'" class="shell">
        <aside class="sidebar">
            <div class="brandmark brand-default"><img src="/icon.svg" alt=""> Klubben</div>
            <div class="brand-logo" style="padding:0 10px 18px"><img :src="design.logo" alt="SurfHorsens" style="width:150px;height:auto;display:block"></div>
            <nav>
                <RouterLink to="/aktiviteter"><Icon name="cal" /> Aktiviteter</RouterLink>
                <RouterLink to="/opslag"><Icon name="chat" /> Opslag</RouterLink>
                <RouterLink v-if="can.portal" to="/traener"><Icon name="whistle" /> Trænerportal</RouterLink>
                <RouterLink v-if="can.equipment" to="/udstyr"><Icon name="board" /> Udstyr</RouterLink>
                <RouterLink :to="inboxLink" :class="{ 'router-link-active': route.path.startsWith('/indbakke') || route.path.startsWith('/beskeder') }"><Icon name="inbox" /> Indbakke <span v-if="badge" class="dot">{{ badge }}</span></RouterLink>
                <RouterLink to="/profil"><Icon name="user" /> Min profil</RouterLink>
                <template v-if="can.members_view || can.finance_read || can.memberships || siteEdit || can.system">
                    <div class="sect">Administration</div>
                    <RouterLink v-if="can.members_view" to="/medlemmer"><Icon name="users" /> Medlemmer</RouterLink>
                    <RouterLink v-if="can.finance_read" to="/okonomi"><Icon name="money" /> Økonomi</RouterLink>
                    <RouterLink v-if="can.memberships" to="/medlemskaber"><Icon name="bag" /> Medlemskaber og tilkøb</RouterLink>
                    <RouterLink v-if="siteEdit" to="/rediger-hjemmeside"><Icon name="globe" /> Hjemmeside</RouterLink>
                    <RouterLink v-if="can.system" to="/system"><Icon name="gear" /> System</RouterLink>
                </template>
                <button class="navbtn" @click="openHelp()"><Icon name="help" /> Hjælp</button>
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
            <RouterLink :to="inboxLink" :class="{ 'router-link-active': route.path.startsWith('/indbakke') || route.path.startsWith('/beskeder') }"><Icon name="inbox" />Indbakke<span v-if="badge" class="dot">{{ badge }}</span></RouterLink>
            <RouterLink to="/profil"><Icon name="user" />Profil</RouterLink>
            <RouterLink v-if="hasMore" to="/mere"><Icon name="more" />Mere</RouterLink>
        </nav>
    </div>
    <!-- Sider, der kræver login, vises kun med en bruger (ellers går de ned, fx lige efter log ud eller udløbet session) -->
    <RouterView v-else-if="store.user || route.meta.public" />
    <div v-else class="spinner"></div>
    <HelpPanel v-if="help.open" />
    <div v-if="store.toast" :class="['toast', store.toast.kind]" role="status">{{ store.toast.text }}</div>
</template>

<script setup>
import { design } from './design';
import { computed, onMounted, onUnmounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Icon from './components/Icon.vue';
import ThemeSwitch from './components/ThemeSwitch.vue';
import AppVersion from './components/AppVersion.vue';
import HelpPanel from './components/HelpPanel.vue';
import { help, openHelp } from './help';
import { store, api, roleLabel, logout as doLogout, IS_APP } from './api';

const route = useRoute();
const router = useRouter();
const can = computed(() => store.user?.can || {});
// "Mere" i bundmenuen kun for dem, der har et værktøj dér (almindelige medlemmer har farvetema og Log ud under Profil)
// Hjemmesiden redigeres kun på weben (editoren virker ikke i appen)
const siteEdit = computed(() => !IS_APP && !!can.value.site_edit);
const hasMore = computed(() => !!(can.value.portal || can.value.equipment || can.value.members_view || can.value.finance_read || can.value.memberships || siteEdit.value || can.value.system));
// Indbakken åbner på den fane, der har ulæste (ellers Beskeder; dem uden beskeder får notifikationerne)
const inboxLink = computed(() => {
    const u = store.user || {};
    if (!u.can_message) return '/indbakke';
    return !u.unread_messages && u.unread ? '/indbakke' : '/beskeder';
});
const badge = computed(() => (store.user?.unread || 0) + (store.user?.unread_messages || 0));
// Hold ulæst-tælleren opdateret (beskeder fra andre medlemmer)
let poll;
onMounted(() => { poll = setInterval(async () => {
    if (!store.user || document.hidden) return;
    try { const u = await api('messages/unread'); store.user.unread = u.notifications; store.user.unread_messages = u.messages; } catch {}
}, 30000); });
onUnmounted(() => clearInterval(poll));

// Tastaturet er oppe, når et tekstfelt har fokus: markér det, så bundmenuen kan skjules på mobil (se app.css)
const isTextField = (el) => el && (el.tagName === 'TEXTAREA' || (el.tagName === 'INPUT' && !['checkbox', 'radio', 'button', 'submit', 'file', 'color', 'range', 'date', 'datetime-local', 'time', 'month'].includes(el.type)));
let typingT;
const onFocusIn = (e) => { if (isTextField(e.target)) { clearTimeout(typingT); document.documentElement.classList.add('typing'); } };
const onFocusOut = () => { typingT = setTimeout(() => { if (!isTextField(document.activeElement)) document.documentElement.classList.remove('typing'); }, 100); };
onMounted(() => { document.addEventListener('focusin', onFocusIn); document.addEventListener('focusout', onFocusOut); });
onUnmounted(() => { document.removeEventListener('focusin', onFocusIn); document.removeEventListener('focusout', onFocusOut); });

// Forsvinder brugeren (log ud, udløbet session), mens en side, der kræver login, er åben: gå til login
watch(() => store.user, (u, before) => {
    if (!u && before && !route.meta.public) router.replace({ path: '/login', query: route.path === '/aktiviteter' ? {} : { til: route.fullPath } });
});
async function logout() {
    await doLogout();
    router.push('/login');
}
defineExpose({ logout });
</script>
