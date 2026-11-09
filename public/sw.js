// Enkel service worker: gør appen installerbar og cacher de statiske filer.
// API-kald går altid til serveren (ingen offline-data i PoC'en). Push-beskeder tilføjes her i næste fase.
const CACHE = 'klub-v1';
self.addEventListener('install', (e) => { self.skipWaiting(); });
self.addEventListener('activate', (e) => {
  e.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))).then(() => self.clients.claim()));
});
self.addEventListener('fetch', (e) => {
  const url = new URL(e.request.url);
  if (e.request.method !== 'GET' || url.origin !== location.origin || url.pathname.startsWith('/api/')) return;
  if (url.pathname.startsWith('/build/') || /\.(png|svg|webmanifest)$/.test(url.pathname)) {
    e.respondWith(caches.open(CACHE).then(async (c) => (await c.match(e.request)) || fetch(e.request).then((r) => { if (r.ok) c.put(e.request, r.clone()); return r; })));
    return;
  }
  if (e.request.mode === 'navigate') {
    e.respondWith(fetch(e.request).catch(() => new Response('<h1 style="font-family:sans-serif;padding:2rem">Ingen forbindelse</h1><p style="font-family:sans-serif;padding:0 2rem">Klubappen kræver internet. Prøv igen om lidt.</p>', { headers: { 'Content-Type': 'text/html; charset=utf-8' } })));
  }
});
