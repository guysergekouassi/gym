// Espace membre hors connexion : la dernière page « Mon accès » et le QR code restent consultables.
const CACHE = 'gymflow-membre-v1';

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (e) => e.waitUntil(
    caches.keys().then((cles) => Promise.all(cles.filter((c) => c !== CACHE).map((c) => caches.delete(c)))).then(() => self.clients.claim())
));

self.addEventListener('fetch', (e) => {
    const url = new URL(e.request.url);
    if (e.request.method !== 'GET' || url.origin !== location.origin || !url.pathname.startsWith('/membre')) return;

    // Réseau d'abord, copie en cache pour pouvoir ouvrir la page sans connexion
    e.respondWith(
        fetch(e.request)
            .then((r) => {
                if (r.ok) { const copie = r.clone(); caches.open(CACHE).then((c) => c.put(e.request, copie)); }
                return r;
            })
            .catch(() => caches.match(e.request).then((r) => r || caches.match('/membre')))
    );
});
