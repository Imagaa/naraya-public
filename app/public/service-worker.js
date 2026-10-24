// Naraya Sales service worker. Bump CACHE_VERSION on every change to this file.
const CACHE_VERSION = 'v29';
const SHELL = `naraya-sales-shell-${CACHE_VERSION}`;
const PAGES = `naraya-sales-pages-${CACHE_VERSION}`;
const ASSETS = `naraya-sales-assets-${CACHE_VERSION}`;
const PRECACHE = ['/offline', '/icons/icon-192.png', '/icons/badge-96.png', '/brand/naraya-horizontal.svg'];

// No skipWaiting() here: the page shows "Versi baru tersedia" and posts SKIP_WAITING when tapped.
self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(SHELL).then((cache) => cache.addAll(PRECACHE)));
});

self.addEventListener('activate', (event) => {
    const keep = [SHELL, PAGES, ASSETS];
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => !keep.includes(key)).map((key) => caches.delete(key))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin || url.pathname.startsWith('/livewire')) {
        return;
    }

    // Vite output is content-hashed, so a cached copy never goes stale.
    if (url.pathname.startsWith('/build/')) {
        event.respondWith(
            caches.match(request).then((cached) => cached || fetch(request).then((response) => {
                const copy = response.clone();
                caches.open(ASSETS).then((cache) => cache.put(request, copy));
                return response;
            }))
        );
        return;
    }

    // Pages: network first, so data stays fresh; visited pages (e.g. /absensi) still open with no signal.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    if (response.ok && !response.redirected) {
                        const copy = response.clone();
                        caches.open(PAGES).then((cache) => cache.put(request, copy));
                    }
                    return response;
                })
                .catch(() => caches.match(request).then((cached) => cached || caches.match('/offline')))
        );
    }
});

self.addEventListener('push', (event) => {
    const payload = event.data ? event.data.json() : {};
    const data = payload.data || {};
    const jobs = [
        self.registration.showNotification(payload.title || 'Naraya Sales', {
            body: payload.body,
            icon: payload.icon || '/icons/icon-192.png',
            badge: payload.badge || '/icons/badge-96.png',
            tag: payload.tag,
            data,
        }),
    ];

    if ('setAppBadge' in self.navigator && typeof data.unread === 'number') {
        jobs.push((data.unread > 0 ? self.navigator.setAppBadge(data.unread) : self.navigator.clearAppBadge()).catch(() => {}));
    }

    event.waitUntil(Promise.all(jobs));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = new URL((event.notification.data && event.notification.data.url) || '/', self.location.origin).href;

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
            const open = windows.find((w) => new URL(w.url).origin === self.location.origin);
            if (open) {
                return open.focus().then((w) => (w && 'navigate' in w ? w.navigate(target) : null));
            }
            return self.clients.openWindow(target);
        })
    );
});

