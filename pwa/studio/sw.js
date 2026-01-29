/**
 * KUHUL GLYPH Studio - Service Worker
 *
 * Offline-first PWA support with caching strategies
 *
 * @law ASX = XCFE = XJSON = KUHUL = AST = CM-1
 * @version 2.1.0
 */

const CACHE_NAME = 'kuhul-studio-v2.1.0';
const CACHE_VERSION = '2.1.0';

// Assets to cache on install
const STATIC_ASSETS = [
    './',
    './index.php',
    './glyph-runtime.js',
    './manifest.json',
];

// API endpoints to cache with network-first strategy
const API_ENDPOINTS = [
    '/api/brain-mesh/terminal.php',
    '/api/brain-mesh/health.php',
    '/api/brain-mesh/leaderboard.php',
];

// ═══════════════════════════════════════════════════════════════════════════
// INSTALL EVENT
// ═══════════════════════════════════════════════════════════════════════════

self.addEventListener('install', (event) => {
    console.log('[SW] Installing KUHUL Studio Service Worker v' + CACHE_VERSION);

    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                console.log('[SW] Caching static assets');
                return cache.addAll(STATIC_ASSETS);
            })
            .then(() => {
                console.log('[SW] Install complete, skipping waiting');
                return self.skipWaiting();
            })
            .catch((error) => {
                console.error('[SW] Install failed:', error);
            })
    );
});

// ═══════════════════════════════════════════════════════════════════════════
// ACTIVATE EVENT
// ═══════════════════════════════════════════════════════════════════════════

self.addEventListener('activate', (event) => {
    console.log('[SW] Activating Service Worker v' + CACHE_VERSION);

    event.waitUntil(
        caches.keys()
            .then((cacheNames) => {
                return Promise.all(
                    cacheNames
                        .filter((name) => name !== CACHE_NAME)
                        .map((name) => {
                            console.log('[SW] Deleting old cache:', name);
                            return caches.delete(name);
                        })
                );
            })
            .then(() => {
                console.log('[SW] Claiming clients');
                return self.clients.claim();
            })
    );
});

// ═══════════════════════════════════════════════════════════════════════════
// FETCH EVENT
// ═══════════════════════════════════════════════════════════════════════════

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Skip non-GET requests for caching
    if (request.method !== 'GET') {
        // For POST requests (terminal commands), use network-only
        if (request.method === 'POST' && url.pathname.includes('/api/')) {
            event.respondWith(
                fetch(request)
                    .catch((error) => {
                        console.error('[SW] Network error for POST:', error);
                        return new Response(JSON.stringify({
                            success: false,
                            error: 'Network unavailable. Please check your connection.',
                            offline: true
                        }), {
                            status: 503,
                            headers: { 'Content-Type': 'application/json' }
                        });
                    })
            );
        }
        return;
    }

    // API endpoints: Network first, then cache
    if (url.pathname.includes('/api/')) {
        event.respondWith(networkFirst(request));
        return;
    }

    // Static assets: Cache first, then network
    event.respondWith(cacheFirst(request));
});

// ═══════════════════════════════════════════════════════════════════════════
// CACHING STRATEGIES
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Cache First Strategy
 * Best for static assets that rarely change
 */
async function cacheFirst(request) {
    const cached = await caches.match(request);
    if (cached) {
        // Return cached, but update in background
        updateCache(request);
        return cached;
    }

    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, response.clone());
        }
        return response;
    } catch (error) {
        console.error('[SW] Cache first failed:', error);
        return offlineResponse(request);
    }
}

/**
 * Network First Strategy
 * Best for API endpoints that need fresh data
 */
async function networkFirst(request) {
    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, response.clone());
        }
        return response;
    } catch (error) {
        console.log('[SW] Network failed, trying cache:', request.url);
        const cached = await caches.match(request);
        if (cached) {
            return cached;
        }
        return offlineResponse(request);
    }
}

/**
 * Update cache in background (stale-while-revalidate)
 */
async function updateCache(request) {
    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, response);
        }
    } catch (error) {
        // Silently fail background updates
    }
}

/**
 * Generate offline response
 */
function offlineResponse(request) {
    const url = new URL(request.url);

    // API requests get JSON error
    if (url.pathname.includes('/api/')) {
        return new Response(JSON.stringify({
            success: false,
            error: 'You are offline. This feature requires a network connection.',
            offline: true,
            cached_at: null
        }), {
            status: 503,
            headers: { 'Content-Type': 'application/json' }
        });
    }

    // HTML requests get offline page
    if (request.headers.get('Accept')?.includes('text/html')) {
        return new Response(`
<!DOCTYPE html>
<html lang="en" data-theme="emerald">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KUHUL Studio - Offline</title>
    <style>
        body {
            font-family: ui-monospace, monospace;
            background: #0a0f0a;
            color: #c8e6c8;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
        }
        .offline-container {
            text-align: center;
            padding: 40px;
        }
        .glyph {
            font-size: 4rem;
            margin-bottom: 20px;
        }
        h1 {
            color: #22c55e;
            margin-bottom: 10px;
        }
        p {
            color: #7cb37c;
            margin-bottom: 20px;
        }
        button {
            padding: 12px 24px;
            background: #22c55e;
            color: #0a0f0a;
            border: none;
            border-radius: 8px;
            font-family: inherit;
            font-size: 1rem;
            cursor: pointer;
        }
        button:hover {
            filter: brightness(1.1);
        }
    </style>
</head>
<body>
    <div class="offline-container">
        <div class="glyph">⟁</div>
        <h1>You're Offline</h1>
        <p>KUHUL GLYPH Studio requires a network connection for terminal commands.</p>
        <p>Cached content may still be available.</p>
        <button onclick="location.reload()">Try Again</button>
    </div>
</body>
</html>
        `, {
            status: 503,
            headers: { 'Content-Type': 'text/html' }
        });
    }

    // Default 503 response
    return new Response('Offline', { status: 503 });
}

// ═══════════════════════════════════════════════════════════════════════════
// MESSAGE CHANNEL
// ═══════════════════════════════════════════════════════════════════════════

self.addEventListener('message', (event) => {
    const { type, payload } = event.data || {};

    switch (type) {
        case 'SKIP_WAITING':
            self.skipWaiting();
            break;

        case 'GET_VERSION':
            event.ports[0]?.postMessage({ version: CACHE_VERSION });
            break;

        case 'CLEAR_CACHE':
            caches.delete(CACHE_NAME).then(() => {
                event.ports[0]?.postMessage({ cleared: true });
            });
            break;

        case 'CACHE_URLS':
            if (payload?.urls) {
                caches.open(CACHE_NAME).then((cache) => {
                    cache.addAll(payload.urls);
                    event.ports[0]?.postMessage({ cached: payload.urls.length });
                });
            }
            break;

        default:
            console.log('[SW] Unknown message type:', type);
    }
});

// ═══════════════════════════════════════════════════════════════════════════
// BACKGROUND SYNC (if supported)
// ═══════════════════════════════════════════════════════════════════════════

self.addEventListener('sync', (event) => {
    if (event.tag === 'sync-commands') {
        event.waitUntil(syncPendingCommands());
    }
});

async function syncPendingCommands() {
    // Get pending commands from IndexedDB and replay them
    console.log('[SW] Syncing pending commands...');
    // Implementation would connect to IndexedDB for queued commands
}

// ═══════════════════════════════════════════════════════════════════════════
// PUSH NOTIFICATIONS (if supported)
// ═══════════════════════════════════════════════════════════════════════════

self.addEventListener('push', (event) => {
    const data = event.data?.json() || {};

    const options = {
        body: data.body || 'New notification from KUHUL Studio',
        icon: 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text y=".9em" font-size="90">⟁</text></svg>',
        badge: 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text y=".9em" font-size="90">⟁</text></svg>',
        tag: data.tag || 'kuhul-notification',
        data: data.payload
    };

    event.waitUntil(
        self.registration.showNotification(data.title || 'KUHUL Studio', options)
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    event.waitUntil(
        clients.matchAll({ type: 'window' }).then((clientList) => {
            // Focus existing window or open new one
            for (const client of clientList) {
                if (client.url.includes('/studio/') && 'focus' in client) {
                    return client.focus();
                }
            }
            return clients.openWindow('/pwa/studio/');
        })
    );
});

console.log('[SW] KUHUL Studio Service Worker loaded v' + CACHE_VERSION);
