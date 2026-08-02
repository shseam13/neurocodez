/**
 * Neuro Codez service worker.
 *
 * The important decision here is what NOT to cache.
 *
 * Authenticated HTML is never stored. Two reasons, both serious:
 *
 *   1. This app shows money. A cached dashboard would display yesterday's
 *      balance as though it were current, and someone chasing a payment cannot
 *      tell a stale figure from a real one.
 *   2. On a shared phone, a cached authenticated page can be served to whoever
 *      opens the app next — after a logout, or to a different user entirely.
 *
 * So HTML is network-only with an offline fallback that says plainly that it is
 * offline. Hashed assets, fonts and icons are cached hard, because their
 * filenames change whenever their contents do.
 */

const VERSION = 'v1';
const SHELL_CACHE = `neuro-shell-${VERSION}`;
const ASSET_CACHE = `neuro-assets-${VERSION}`;
const OFFLINE_URL = '/offline';

const SHELL_FILES = [
    OFFLINE_URL,
    '/brand/icon-192.png',
    '/brand/logo-mark.svg',
    '/favicon.ico',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(SHELL_CACHE)
            // Individually, so one missing file cannot fail the whole install.
            .then((cache) => Promise.allSettled(SHELL_FILES.map((url) => cache.add(url))))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter((key) => key.startsWith('neuro-') && !key.endsWith(VERSION))
                        .map((key) => caches.delete(key)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

/** Immutable build output and self-hosted fonts. */
function isHashedAsset(url) {
    return url.pathname.startsWith('/build/') || /\.(woff2?|ttf|otf)$/.test(url.pathname);
}

function isImage(url) {
    return /\.(png|jpe?g|svg|webp|ico|gif)$/.test(url.pathname);
}

self.addEventListener('fetch', (event) => {
    const { request } = event;

    // Only GET is ever cacheable, and only our own origin.
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    // Never touch the file-download or PDF endpoints: both are authorised per
    // request, and a cached copy would survive a permission change.
    if (url.pathname.startsWith('/files/') || url.pathname.includes('/pdf')) return;

    if (isHashedAsset(url)) {
        event.respondWith(cacheFirst(request, ASSET_CACHE));
        return;
    }

    if (isImage(url)) {
        event.respondWith(cacheFirst(request, ASSET_CACHE));
        return;
    }

    // Everything else is a document or an API call: straight to the network,
    // with a clear offline page rather than stale content.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match(OFFLINE_URL)),
        );
    }
});

async function cacheFirst(request, cacheName) {
    const cached = await caches.match(request);
    if (cached) return cached;

    try {
        const response = await fetch(request);

        // Only store complete, successful responses. A 206 or an opaque error
        // page in the cache is worse than no cache at all.
        if (response.ok && response.status === 200) {
            const cache = await caches.open(cacheName);
            cache.put(request, response.clone());
        }

        return response;
    } catch (error) {
        return cached ?? Response.error();
    }
}
