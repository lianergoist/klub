import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import App from './App.vue';
import { store, refreshMe, IS_APP, siteAccess } from './api';
import { initTheme } from './theme';

initTheme();

// Appen bygges uden økonomi- og gæstesider (de bruges på weben)
/* global __APP__ */
const views = __APP__
    ? import.meta.glob(['./views/**/*.vue', '!./views/finance/**', '!./views/Guest*.vue'])
    : import.meta.glob('./views/**/*.vue');
const v = (name) => views[`./views/${name}.vue`];

const routes = [
    { path: '/', redirect: '/aktiviteter' },
    { path: '/login', component: v('Login'), meta: { public: true } },
    { path: '/bliv-medlem', component: v('Register'), meta: { public: true } },
    { path: '/klippekort', component: v('Punchcard'), meta: { public: true } },
    { path: '/klip', component: v('GuestKlip'), meta: { guest: true } },
    ...(__APP__ ? [{ path: '/adgang', component: v('AppGate'), meta: { public: true } }] : [
        { path: '/prov', component: v('Guest'), meta: { public: true } },
        { path: '/prov/tak', component: v('GuestThanks'), meta: { guest: true } },
    ]),
    { path: '/aktiviteter', component: v('Activities') },
    { path: '/aktiviteter/ny', component: v('ActivityForm'), meta: { can: 'activities_manage' } },
    { path: '/aktiviteter/:id', component: v('ActivityDetail'), props: true },
    { path: '/aktiviteter/:id/rediger', component: v('ActivityForm'), props: true, meta: { can: 'activities_manage' } },
    { path: '/betal/:id', component: v('Pay'), props: true },
    { path: '/traener', component: v('Trainer'), meta: { can: 'portal' } },
    { path: '/udstyr', component: v('Equipment'), meta: { can: 'equipment', wide: true } },
    { path: '/indbakke', component: v('Inbox') },
    { path: '/beskeder', component: v('Inbox') },
    { path: '/beskeder/:id', component: v('Conversation'), props: true },
    { path: '/opslag', component: v('Feed') },
    { path: '/profil/:tab?', component: v('Profile'), props: true },
    { path: '/mere', component: v('More') },
    { path: '/kvittering', redirect: '/profil/kvitteringer' }, // gamle links og beskeder
    { path: '/medlemmer', component: v('Members'), meta: { can: 'members_view', wide: true } },
    { path: '/medlemmer/:id', component: v('MemberDetail'), props: true, meta: { can: 'members_view' } },
    { path: '/indstillinger', component: v('Settings'), meta: { can: 'settings' } },
    { path: '/indstillinger/aendringslog', component: v('AuditLog'), meta: { can: 'audit_view', wide: true } },
    { path: '/indstillinger/rettigheder', component: v('Permissions'), meta: { can: 'roles_manage', wide: true } },
    ...(__APP__ ? [] : [
    {
        path: '/okonomi', component: v('finance/FinanceLayout'), meta: { can: 'finance_read', wide: true },
        children: [
            { path: '', component: v('finance/Overview') },
            { path: 'posteringer', component: v('finance/Entries') },
            { path: 'betalinger', component: v('finance/Payments') },
            { path: 'udlaeg', component: v('finance/Claims') },
            { path: 'budget', component: v('finance/Budget') },
            { path: 'bank', component: v('finance/Bank') },
            { path: 'mobilepay', component: v('finance/Payouts') },
            { path: 'log', component: v('finance/Audit') },
            { path: 'aarsregnskab', component: v('finance/Report') },
        ],
    },
    ]),
    { path: '/:pathMatch(.*)*', redirect: '/aktiviteter' },
];

const router = createRouter({ history: createWebHistory(), routes, scrollBehavior: (to, from, saved) => saved || { top: 0 } });

// Sider, der kun findes på web (Økonomi er ikke med i appen): i appen åbnes de i browseren
const WEB_ONLY = ['/okonomi'];

router.beforeEach(async (to, from) => {
    if (IS_APP && !siteAccess() && to.path !== '/adgang') return '/adgang';
    // I appen findes Økonomi ikke, så routeren omdirigerer til /aktiviteter, før denne vagt kører – se på den oprindelige adresse
    const asked = to.redirectedFrom || to;
    if (IS_APP && WEB_ONLY.some(p => asked.path === p || asked.path.startsWith(p + '/'))) {
        import('./native').then((m) => m.openWeb(asked.fullPath));
        // Bliv hvor man er – eller gå til forsiden, hvis linket åbnede appen (fx et tryk på en push-besked)
        return from.matched.length ? false : '/aktiviteter';
    }
    if (to.meta.public) return true;
    if (!store.token) return '/login';
    if (!store.user) { try { await refreshMe(); } catch { return '/login'; } }
    // Gæster (prøvetræning/SUP-kursus) har kun adgang til betaling og deres kvittering
    // Gæster (ikke-medlemmer) har kun adgang til deres klippekort-side, betaling og kvittering
    if (store.user?.status === 'guest' && !to.meta.guest && !to.path.startsWith('/betal')) return '/klip';
    if (to.meta.can && !store.user?.can?.[to.meta.can]) return '/aktiviteter';
    return true;
});

createApp(App).use(router).mount('#app');

if (__APP__) {
    import('./native').then((m) => m.initNative(router));
} else if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
}
