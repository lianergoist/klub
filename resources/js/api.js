import { reactive } from 'vue';

function readToken() { try { return localStorage.getItem('token'); } catch { return null; } }

export const store = reactive({ user: null, token: readToken(), toast: null });

export function setSession(token, user) {
    store.token = token; store.user = user;
    try { token ? localStorage.setItem('token', token) : localStorage.removeItem('token'); } catch {}
}

export class ApiError extends Error {
    constructor(message, status, errors) { super(message); this.status = status; this.errors = errors || {}; }
}

export async function api(path, { method = 'GET', body, form } = {}) {
    const headers = { Accept: 'application/json' };
    if (store.token) headers.Authorization = `Bearer ${store.token}`;
    let payload;
    if (form) payload = form;
    else if (body !== undefined) { headers['Content-Type'] = 'application/json'; payload = JSON.stringify(body); }
    const res = await fetch(`/api/${path}`, { method, headers, body: payload });
    if (res.status === 401) { setSession(null, null); location.href = '/login'; throw new ApiError('Log ind igen', 401); }
    const data = res.headers.get('content-type')?.includes('json') ? await res.json() : null;
    if (!res.ok) {
        const first = data?.errors ? Object.values(data.errors)[0]?.[0] : null;
        const msg = first || data?.message || (res.status === 403 ? 'Det har du ikke adgang til.' : 'Noget gik galt. Prøv igen.');
        throw new ApiError(msg, res.status, data?.errors);
    }
    return data;
}

/** Hent fil med login (CSV, bilag, dataudtræk) og åbn/gem den. */
export async function download(path, filename) {
    const res = await fetch(`/api/${path}`, { headers: { Authorization: `Bearer ${store.token}` } });
    if (!res.ok) { toast('Filen kunne ikke hentes', 'error'); return; }
    const blob = await res.blob();
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = filename; document.body.appendChild(a); a.click(); a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 5000);
}

let t;
export function toast(text, kind = 'ok') {
    store.toast = { text, kind };
    clearTimeout(t); t = setTimeout(() => (store.toast = null), 3500);
}

export async function refreshMe() {
    if (!store.token) return null;
    store.user = await api('me');
    return store.user;
}

/* ---- formattering ---- */
export const kr = (ore) => (ore / 100).toLocaleString('da-DK', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' kr.';
export const krRound = (ore) => Math.round(ore / 100).toLocaleString('da-DK') + ' kr.';
export const dato = (iso) => iso ? new Date(iso).toLocaleDateString('da-DK', { day: 'numeric', month: 'short', year: 'numeric' }) : '';
export const datoLang = (iso) => new Date(iso).toLocaleDateString('da-DK', { weekday: 'long', day: 'numeric', month: 'long' });
export const klok = (iso) => new Date(iso).toLocaleTimeString('da-DK', { hour: '2-digit', minute: '2-digit' });
export const tidspunkt = (iso) => iso ? `${dato(iso)} kl. ${klok(iso)}` : '';
export const roleLabel = { member: 'Medlem', trainer: 'Træner', board: 'Bestyrelse', treasurer: 'Kasserer', auditor: 'Revisor', admin: 'Administrator' };
