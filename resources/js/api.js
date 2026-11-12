import { reactive } from 'vue';

/* global __APP__, __API_BASE__ */
export const IS_APP = __APP__;            // true i mobilappen (Capacitor), false på weben
export const API_BASE = __API_BASE__;     // '' på weben (samme adresse), fx https://klub.rpix.dk i appen
export const WEB_URL = IS_APP ? API_BASE : '';

function readToken() { try { return localStorage.getItem('token'); } catch { return null; } }
// Appen kan ikke bruge sitets adgangskode-cookie, så den sender et token i en header i stedet (kun i proof of concept)
export function siteAccess(v) { try { if (v === undefined) return localStorage.getItem('site_access'); v ? localStorage.setItem('site_access', v) : localStorage.removeItem('site_access'); } catch { return null; } }

export const store = reactive({ user: null, token: readToken(), toast: null, tema: 'standard' });

export function setSession(token, user) {
    store.token = token; store.user = user;
    try { token ? localStorage.setItem('token', token) : localStorage.removeItem('token'); } catch {}
}

export class ApiError extends Error {
    constructor(message, status, errors, data) { super(message); this.status = status; this.errors = errors || {}; this.data = data || {}; }
}

export async function api(path, { method = 'GET', body, form } = {}) {
    const headers = { Accept: 'application/json' };
    if (store.token) headers.Authorization = `Bearer ${store.token}`;
    if (IS_APP && siteAccess()) headers['X-Site-Access'] = siteAccess();
    let payload;
    if (form) payload = form;
    else if (body !== undefined) { headers['Content-Type'] = 'application/json'; payload = JSON.stringify(body); }
    const res = await fetch(`${API_BASE}/api/${path}`, { method, headers, body: payload });
    const data = res.headers.get('content-type')?.includes('json') ? await res.json() : null;
    if (res.status === 401) {
        if (data?.gate) {
            if (IS_APP) { siteAccess(null); location.href = '/adgang'; } else location.href = '/adgang?til=' + encodeURIComponent(location.pathname);
            throw new ApiError(data.message, 401);
        }
        setSession(null, null); location.href = '/login'; throw new ApiError('Log ind igen', 401);
    }
    if (!res.ok) {
        const first = data?.errors ? Object.values(data.errors)[0]?.[0] : null;
        const msg = first || data?.message || (res.status === 403 ? 'Det har du ikke adgang til.' : 'Noget gik galt. Prøv igen.');
        throw new ApiError(msg, res.status, data?.errors, data);
    }
    return data;
}

/** Hent fil med login (CSV, bilag, dataudtræk) og åbn/gem den. */
export async function download(path, filename) {
    const res = await fetch(`${API_BASE}/api/${path}`, { headers: { Authorization: `Bearer ${store.token}`, ...(IS_APP && siteAccess() ? { 'X-Site-Access': siteAccess() } : {}) } });
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

/** Log ud overalt (i appen afmeldes telefonen også push). */
export async function logout() {
    if (IS_APP) { try { await (await import('./native')).unregisterPush(); } catch {} }
    try { await api('logout', { method: 'POST' }); } catch {}
    setSession(null, null);
}

export async function refreshMe() {
    if (!store.token) return null;
    store.user = await api('me');
    return store.user;
}

/* ---- formattering ---- */
export const kr = (ore) => (ore / 100).toLocaleString('da-DK', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' kr.';
export const krRound = (ore) => Math.round(ore / 100).toLocaleString('da-DK') + ' kr.';
// Alle tider vises i klubbens tid (dansk tid), uanset hvor brugeren befinder sig
const TZ = 'Europe/Copenhagen';
const fmt = (iso, o) => new Date(iso).toLocaleString('da-DK', { timeZone: TZ, ...o });
const stort = (s) => s.charAt(0).toUpperCase() + s.slice(1); // kun første bogstav (dansk: "Lørdag 1. maj")
export const dato = (iso) => iso ? fmt(iso, { day: 'numeric', month: 'short', year: 'numeric' }) : '';
export const datoLang = (iso) => stort(fmt(iso, { weekday: 'long', day: 'numeric', month: 'long' }));
export const klok = (iso) => fmt(iso, { hour: '2-digit', minute: '2-digit' });
export const dagNr = (iso) => fmt(iso, { day: 'numeric' }).replace('.', '');
export const ugedagKort = (iso) => fmt(iso, { weekday: 'short' }).replace('.', '');
export const maanedKort = (iso) => fmt(iso, { month: 'short' });
export const maanedAar = (iso) => fmt(iso, { month: 'long', year: 'numeric' });
export const tidspunkt = (iso) => iso ? `${dato(iso)} kl. ${klok(iso)}` : '';
export const roleLabel = { member: 'Medlem', trainer: 'Træner', board: 'Bestyrelse', treasurer: 'Kasserer', auditor: 'Revisor', admin: 'Administrator' };
