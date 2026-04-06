/**
 * BetVibe Service Worker
 * Cache-first for static assets, network-first for API calls
 * Push notification support
 */

const CACHE_VERSION = 'betvibe-v1';
const STATIC_CACHE = CACHE_VERSION + '-static';
const DYNAMIC_CACHE = CACHE_VERSION + '-dynamic';

// Assets to pre-cache
const PRECACHE_ASSETS = [
    '/',
    '/assets/css/app.css',
    '/assets/css/style.css',
    '/assets/js/app.js',
    '/assets/js/socket.js',
    '/manifest.json',
];

// Install: pre-cache static assets
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then(cache => cache.addAll(PRECACHE_ASSETS))
            .then(() => self.skipWaiting())
    );
});

// Activate: clean old caches
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(
                keys
                    .filter(key => key !== STATIC_CACHE && key !== DYNAMIC_CACHE)
                    .map(key => caches.delete(key))
            )
        ).then(() => self.clients.claim())
    );
});

// Fetch: strategy-based caching
self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);

    // Skip non-GET and external requests
    if (request.method !== 'GET' || url.origin !== location.origin) {
        return;
    }

    // API calls: network-first
    if (url.pathname.startsWith('/api/')) {
        event.respondWith(
            fetch(request)
                .then(response => {
                    const clone = response.clone();
                    caches.open(DYNAMIC_CACHE).then(cache => cache.put(request, clone));
                    return response;
                })
                .catch(() => caches.match(request))
        );
        return;
    }

    // Static assets: cache-first
    if (url.pathname.match(/\.(css|js|png|jpg|webp|woff2?|svg)$/)) {
        event.respondWith(
            caches.match(request).then(cached => {
                if (cached) return cached;
                return fetch(request).then(response => {
                    const clone = response.clone();
                    caches.open(STATIC_CACHE).then(cache => cache.put(request, clone));
                    return response;
                });
            })
        );
        return;
    }

    // HTML pages: network-first
    event.respondWith(
        fetch(request)
            .then(response => {
                const clone = response.clone();
                caches.open(DYNAMIC_CACHE).then(cache => cache.put(request, clone));
                return response;
            })
            .catch(() => caches.match(request).then(cached => cached || caches.match('/')))
    );
});

// Push notification handler
self.addEventListener('push', event => {
    let data = {};
    try {
        data = event.data.json();
    } catch (e) {
        data = { title: 'BetVibe', body: event.data.text() };
    }

    const options = {
        body: data.body || '',
        icon: data.icon || '/assets/images/icon-192.png',
        badge: data.badge || '/assets/images/icon-72.png',
        vibrate: [200, 100, 200],
        data: {
            url: data.url || '/',
            timestamp: data.timestamp || Date.now(),
        },
        actions: [
            { action: 'open', title: '🎮 Open BetVibe' },
            { action: 'dismiss', title: 'Dismiss' },
        ],
        tag: data.tag || 'betvibe-notification',
        renotify: true,
    };

    event.waitUntil(
        self.registration.showNotification(data.title || 'BetVibe', options)
    );
});

// Notification click handler
self.addEventListener('notificationclick', event => {
    event.notification.close();

    if (event.action === 'dismiss') return;

    const url = event.notification.data?.url || '/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then(clientList => {
                // Focus existing window if open
                for (const client of clientList) {
                    if (client.url.includes(self.location.origin) && 'focus' in client) {
                        client.navigate(url);
                        return client.focus();
                    }
                }
                // Open new window
                return clients.openWindow(url);
            })
    );
});
