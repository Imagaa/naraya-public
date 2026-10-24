// Naraya Admin service worker: push notifications only. The sales worker
// (service-worker.js) precaches the sales offline page, which only exists on the sales host.
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

self.addEventListener('push', (event) => {
    const payload = event.data ? event.data.json() : {};
    const data = payload.data || {};
    const jobs = [
        self.registration.showNotification(payload.title || 'Naraya Admin', {
            body: payload.body,
            icon: payload.icon || '/icons/icon-192.png',
            badge: payload.badge || '/icons/badge-96.png',
            tag: payload.tag,
            requireInteraction: payload.requireInteraction === true,
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
