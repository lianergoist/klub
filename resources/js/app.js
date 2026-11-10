import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import App from './App.vue';
import { store, refreshMe } from './api';
import { initTheme } from './theme';

initTheme();

const views = import.meta.glob('./views/**/*.vue');
const v = (name) => views[`./views/${name}.vue`];

const routes = [
    { path: '/', redirect: '/aktiviteter' },
    { path: '/login', component: v('Login'), meta: { public: true } },
    { path: '/bliv-medlem', component: v('Register'), meta: { public: true } },
    { path: '/aktiviteter', component: v('Activities') },
    { path: '/aktiviteter/ny', component: v('ActivityForm'), meta: { can: 'trainer' } },
    { path: '/aktiviteter/:id', component: v('ActivityDetail'), props: true },
    { path: '/aktiviteter/:id/rediger', component: v('ActivityForm'), props: true, meta: { can: 'trainer' } },
    { path: '/betal/:id', component: v('Pay'), props: true },
    { path: '/traener', component: v('Trainer'), meta: { can: 'trainer' } },
    { path: '/indbakke', component: v('Inbox') },
    { path: '/beskeder', component: v('Inbox') },
    { path: '/beskeder/:id', component: v('Conversation'), props: true },
    { path: '/opslag', component: v('Feed') },
    { path: '/profil/:tab?', component: v('Profile'), props: true },
    { path: '/mere', component: v('More') },
    { path: '/medlemmer', component: v('Members'), meta: { can: 'members', wide: true } },
    { path: '/medlemmer/:id', component: v('MemberDetail'), props: true, meta: { can: 'members' } },
    { path: '/indstillinger', component: v('Settings'), meta: { can: 'board' } },
    {
        path: '/okonomi', component: v('finance/FinanceLayout'), meta: { can: 'finance_read', wide: true },
        children: [
            { path: '', component: v('finance/Overview') },
            { path: 'posteringer', component: v('finance/Entries') },
            { path: 'betalinger', component: v('finance/Payments') },
            { path: 'budget', component: v('finance/Budget') },
            { path: 'bank', component: v('finance/Bank') },
            { path: 'log', component: v('finance/Audit') },
            { path: 'aarsregnskab', component: v('finance/Report') },
        ],
    },
    { path: '/:pathMatch(.*)*', redirect: '/aktiviteter' },
];

const router = createRouter({ history: createWebHistory(), routes, scrollBehavior: (to, from, saved) => saved || { top: 0 } });

router.beforeEach(async (to) => {
    if (to.meta.public) return true;
    if (!store.token) return '/login';
    if (!store.user) { try { await refreshMe(); } catch { return '/login'; } }
    if (to.meta.can && !store.user?.can?.[to.meta.can]) return '/aktiviteter';
    return true;
});

createApp(App).use(router).mount('#app');

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
}
