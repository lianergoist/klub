// Pseudonym brugslog: sideskift, JavaScript-fejl og forløb (track). Fejl fra API'et logges på serveren.
// Hændelser samles og sendes i bundter; logningen må aldrig forstyrre brugeren.
import { API_BASE, IS_APP, store, siteAccess, usageHeaders, ApiError } from './api';

const queue = [];
let routerRef = null;
// Ruteskabelonen (fx /aktiviteter/:id) i stedet for den konkrete adresse, så mønstre kan tælles sammen
const currentPath = () => routerRef?.currentRoute.value.matched.at(-1)?.path || location.pathname.replace(/\/[\d,]+(?=\/|$)/g, '/:id');
let timer = null;

function push(type, path, target = null, meta = null) {
    queue.push({ type, path: String(path).slice(0, 191), target: target ? String(target).slice(0, 191) : null, meta, at: Date.now() });
    if (queue.length >= 20) flush();
    else if (!timer) timer = setTimeout(flush, 10000);
}

export function flush() {
    clearTimeout(timer); timer = null;
    if (!queue.length) return;
    const events = queue.splice(0, 50);
    const headers = { 'Content-Type': 'application/json', Accept: 'application/json', ...usageHeaders() };
    if (store.token) headers.Authorization = `Bearer ${store.token}`;
    if (IS_APP && siteAccess()) headers['X-Site-Access'] = siteAccess();
    // keepalive: bundtet sendes også, når fanen lukkes
    fetch(`${API_BASE}/api/usage`, { method: 'POST', headers, body: JSON.stringify({ events }), keepalive: true }).catch(() => {});
}

/** Forløb, fx track('tilmelding', 'start') og track('tilmelding', 'ok'). meta må aldrig indeholde personoplysninger. */
export function track(flow, step, meta = null) {
    push('track', currentPath(), `${flow}.${step}`, meta);
}

export function initUsage(router, app) {
    routerRef = router;
    let last = null;
    router.afterEach((to, from, failure) => {
        if (failure) return;
        const path = to.matched.at(-1)?.path || to.path;
        const now = Date.now();
        const meta = last ? { from: last.path, ms: now - last.at } : null;
        last = { path, at: now };
        push('view', path, null, meta);
    });

    const jsError = (msg) => push('js_error', currentPath(), null, { message: String(msg).slice(0, 200) });
    app.config.errorHandler = (err, vm, info) => { jsError(`${err?.message || err} (${info})`); console.error(err); };
    window.addEventListener('error', (e) => jsError(e.message));
    window.addEventListener('unhandledrejection', (e) => {
        if (e.reason instanceof ApiError) return; // allerede logget på serveren
        jsError(e.reason?.message || e.reason);
    });
    document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'hidden') flush(); });
}
