// Service worker mínimo: permite instalar Mi CV como app. No guarda datos
// personales en caché; solo una página sencilla para cuando no hay internet.
const CACHE = 'micv-v2';
const OFFLINE = '/offline.html';
self.addEventListener('install', e => { e.waitUntil(caches.open(CACHE).then(c => c.addAll([OFFLINE, '/css/tema.css', '/icons/icon-192.png']))); self.skipWaiting(); });
self.addEventListener('activate', e => { e.waitUntil(caches.keys().then(k => Promise.all(k.filter(x => x !== CACHE).map(x => caches.delete(x))))); self.clients.claim(); });
self.addEventListener('fetch', e => {
  if (e.request.method === 'GET' && e.request.mode === 'navigate') {
    e.respondWith(fetch(e.request).catch(() => caches.match(OFFLINE)));
  }
});
