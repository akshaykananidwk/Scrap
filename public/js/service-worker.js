/**
 * ScrapX service worker.
 *
 * Deliberately conservative: an offline shell plus cached static assets, but
 * NEVER cached responses for money-critical pages. Serving a stale auction page
 * or order total from cache would be worse than showing an offline notice.
 */

const VERSION = 'scrapx-v1';
const SHELL_CACHE = VERSION + '-shell';
const ASSET_CACHE = VERSION + '-assets';

const SHELL_URLS = [
    '/offline',
    '/public/css/app.css',
    '/public/js/app.js',
];

// Anything under these paths is always fetched fresh.
const NEVER_CACHE = [
    '/auctions/', '/api/', '/dashboard/', '/admin/', '/login', '/register',
    '/cron/', '/install', '/logout',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(SHELL_CACHE)
            .then((cache) => cache.addAll(SHELL_URLS).catch(() => undefined))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys.filter((key) => !key.startsWith(VERSION)).map((key) => caches.delete(key))
            ))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    // Only GET requests are ever served from cache.
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;
    if (NEVER_CACHE.some((path) => url.pathname.startsWith(path))) return;

    // Static assets: cache-first, they are versioned by deployment.
    if (url.pathname.startsWith('/public/') || url.pathname.startsWith('/uploads/')) {
        event.respondWith(
            caches.match(request).then((cached) => {
                if (cached) return cached;
                return fetch(request).then((response) => {
                    if (response.ok && response.type === 'basic') {
                        const clone = response.clone();
                        caches.open(ASSET_CACHE).then((cache) => cache.put(request, clone));
                    }
                    return response;
                }).catch(() => cached);
            })
        );
        return;
    }

    // Pages: network-first, falling back to the offline shell.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    if (response.ok) {
                        const clone = response.clone();
                        caches.open(SHELL_CACHE).then((cache) => cache.put(request, clone));
                    }
                    return response;
                })
                .catch(() => caches.match(request).then((cached) => cached || caches.match('/offline')))
        );
    }
});

// Push is architected but not enabled — see PushProvider in the PHP layer.
self.addEventListener('push', (event) => {
    if (!event.data) return;

    let payload = {};
    try {
        payload = event.data.json();
    } catch (e) {
        payload = { title: 'ScrapX', body: event.data.text() };
    }

    event.waitUntil(
        self.registration.showNotification(payload.title || 'ScrapX', {
            body: payload.body || '',
            icon: '/public/img/icon-192.png',
            badge: '/public/img/icon-192.png',
            data: { url: payload.url || '/dashboard' },
            tag: payload.tag || 'scrapx',
        })
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = event.notification.data?.url || '/dashboard';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
            for (const client of clients) {
                if (client.url.includes(target) && 'focus' in client) return client.focus();
            }
            return self.clients.openWindow(target);
        })
    );
});
