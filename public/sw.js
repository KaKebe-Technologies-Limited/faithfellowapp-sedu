/* FaithFellows service worker
 * - Network-first for pages, falling back to /offline when there's no signal.
 * - Cache-first for built assets (fingerprinted) and saved sermons ("ff-sermons" cache).
 * - Web push display + click handling.
 */
const VERSION = 'ff-v1';
const OFFLINE_URL = '/offline';

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(VERSION).then((c) => c.addAll([OFFLINE_URL, '/favicon.svg', '/manifest.webmanifest'])).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== VERSION && k !== 'ff-sermons').map((k) => caches.delete(k))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);

    // Saved sermons (any origin) — serve from cache when present.
    event.respondWith((async () => {
        const sermon = await caches.open('ff-sermons').then((c) => c.match(req));
        if (sermon) return sermon;

        if (url.origin === location.origin && url.pathname.startsWith('/build/')) {
            const cache = await caches.open(VERSION);
            const hit = await cache.match(req);
            if (hit) return hit;
            const res = await fetch(req);
            if (res.ok) cache.put(req, res.clone());
            return res;
        }

        if (req.mode === 'navigate') {
            try {
                return await fetch(req);
            } catch (e) {
                return (await caches.match(OFFLINE_URL)) || Response.error();
            }
        }

        return fetch(req);
    })());
});

self.addEventListener('push', (event) => {
    let data = {};
    try { data = event.data ? event.data.json() : {}; } catch (e) { data = { title: 'FaithFellows', body: event.data?.text() }; }
    event.waitUntil(self.registration.showNotification(data.title || 'FaithFellows', {
        body: data.body || '',
        icon: data.icon || '/images/icon-192.png',
        badge: '/images/icon-192.png',
        data: { url: data.url || data.action_url || '/notifications' },
        tag: data.tag,
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data?.url || '/';
    event.waitUntil(self.clients.matchAll({ type: 'window' }).then((wins) => {
        const win = wins.find((w) => w.url.includes(url));
        return win ? win.focus() : self.clients.openWindow(url);
    }));
});
