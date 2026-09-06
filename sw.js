/**
 * sw.js — Notezy service worker
 * Caches static assets (CSS, logo) via the Cache API and serves offline.html
 * as a fallback for navigation requests when the network is unavailable
 * and the page isn't already cached.
 *
 * NOTE: Dynamic PHP pages that depend on session/DB state (index_notezy.php,
 * account.php, themghichu.php, edit_note.php, ...) are intentionally NOT
 * precached — caching them would risk showing stale notes/session data.
 * They're fetched network-first with a safe offline.html fallback only.
 */

const CACHE_VERSION = 'notezy-v4';
const STATIC_CACHE = `${CACHE_VERSION}-static`;
const DYNAMIC_CACHE = `${CACHE_VERSION}-dynamic`;

// Static, mostly-immutable assets safe to precache.
const PRECACHE_URLS = [
    'offline.html',
    'manifest.json',
    'logo.png',
    'js/offline-store.js',
    'CSS/main.css',
    'CSS/index.css',
    'CSS/style.css',
    'CSS/login.css',
    'CSS/register.css',
    'CSS/reset.css'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then((cache) => Promise.all(
                PRECACHE_URLS.map((url) =>
                    cache.add(url).catch((err) => {
                        // Don't let a single missing/renamed asset break install
                        console.warn('[sw.js] Skip precache for', url, err);
                    })
                )
            ))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys
                    .filter((key) => key.startsWith('notezy-') && !key.includes(CACHE_VERSION))
                    .map((key) => caches.delete(key))
            )
        ).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    // Only handle same-origin GET requests.
    if (request.method !== 'GET' || new URL(request.url).origin !== self.location.origin) {
        return;
    }

    const url = new URL(request.url);

    // Navigation requests (loading a page/tab): network-first, fall back to
    // cache, then to offline.html if nothing is available.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    // Cache successful navigations of pages
                    if (response && response.ok) {
                        const clone = response.clone();
                        caches.open(DYNAMIC_CACHE).then((cache) => cache.put(request, clone));
                    }
                    return response;
                })
                .catch(() =>
                    caches.match(request).then((cached) => cached || caches.match('offline.html'))
                )
        );
        return;
    }

    // API calls: network-first, fall back to cache
    if (url.pathname.includes('/api/')) {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    if (response && response.ok) {
                        const clone = response.clone();
                        caches.open(DYNAMIC_CACHE).then((cache) => cache.put(request, clone));
                    }
                    return response;
                })
                .catch(() => caches.match(request))
        );
        return;
    }

    // Static assets (CSS/JS/images): cache-first, then network, then cache the result.
    const isStaticAsset = /\.(css|js|png|jpg|jpeg|gif|webp|svg|ico)$/i.test(url.pathname);
    if (isStaticAsset) {
        event.respondWith(
            caches.match(request).then((cached) => {
                if (cached) return cached;
                return fetch(request)
                    .then((response) => {
                        if (response && response.ok) {
                            const clone = response.clone();
                            caches.open(STATIC_CACHE).then((cache) => cache.put(request, clone));
                        }
                        return response;
                    })
                    .catch(() => cached); // cached is undefined here, request simply fails
            })
        );
        return;
    }

    // Everything else: Network-first, cache fallback
    event.respondWith(
        fetch(request)
            .then((response) => {
                if (response && response.ok) {
                    const clone = response.clone();
                    caches.open(DYNAMIC_CACHE).then((cache) => cache.put(request, clone));
                }
                return response;
            })
            .catch(() => caches.match(request))
    );
});
