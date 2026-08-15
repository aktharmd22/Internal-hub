/* Renewal Guard service worker. */

const CACHE = 'rg-v1';
const OFFLINE_URL = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE).then((cache) => cache.addAll([OFFLINE_URL])).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim())
    );
});

/*
 * Network first for pages. This app is about live expiry dates — a cached
 * dashboard showing last week's numbers would be worse than an honest offline
 * page.
 */
self.addEventListener('fetch', (event) => {
    if (event.request.mode !== 'navigate') return;

    event.respondWith(
        fetch(event.request).catch(() => caches.match(OFFLINE_URL))
    );
});

self.addEventListener('push', (event) => {
    if (!event.data) return;

    let payload = {};

    try {
        payload = event.data.json();
    } catch {
        payload = { title: 'Renewal Guard', body: event.data.text() };
    }

    event.waitUntil(
        self.registration.showNotification(payload.title ?? 'Renewal Guard', {
            body: payload.body ?? '',
            icon: payload.icon ?? '/icons/icon-192.png',
            badge: '/icons/badge-72.png',
            tag: payload.tag,
            data: payload.data ?? {},
            renotify: Boolean(payload.tag),
        })
    );
});

/* Deep-link straight to the thing the notification is about. */
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const target = event.notification.data?.url ?? '/dashboard';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
            for (const client of clients) {
                if (client.url.includes(target) && 'focus' in client) return client.focus();
            }

            return self.clients.openWindow(target);
        })
    );
});
