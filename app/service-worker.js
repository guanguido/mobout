const CACHE_NAME = 'mobout-app-shell-v1';
const APP_SHELL = [
    '/app/',
    '/app/index.html',
    '/app/manifest.json',
    '/app/icons/icon-192.png',
    '/app/icons/icon-512.png',
];

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(CACHE_NAME).then(function (cache) {
            return cache.addAll(APP_SHELL);
        })
    );
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(
                keys.filter(function (key) { return key !== CACHE_NAME; })
                    .map(function (key) { return caches.delete(key); })
            );
        })
    );
    self.clients.claim();
});

self.addEventListener('fetch', function (event) {
    const request = event.request;

    // Never cache non-GET requests (e.g. POST to /contact.php)
    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    // Only handle same-origin requests for the app shell itself; let everything
    // else (contact.php, external links) go straight to the network.
    if (url.origin !== self.location.origin || !url.pathname.startsWith('/app/')) {
        return;
    }

    event.respondWith(
        caches.match(request).then(function (cached) {
            const network = fetch(request).then(function (response) {
                if (response && response.ok) {
                    caches.open(CACHE_NAME).then(function (cache) {
                        cache.put(request, response.clone());
                    });
                }
                return response;
            }).catch(function () {
                return cached;
            });
            return cached || network;
        })
    );
});
