/* sw.js — Service Worker for offline-first PWA */

const CACHE_VERSION = 'mpango-v1';
const RUNTIME_CACHE = 'mpango-runtime-v1';
const PRECACHE_URLS = [
  './dashboard.php',
  './login.php',
  './views/sales/add.php',
  './views/sales/index.php',
  './views/stock/stock_in.php',
  './views/stock/stock_out.php',
  './views/debts/index.php',
  './assets/css/style.css',
  './assets/js/app.js',
  './assets/js/offline-queue.js',
  './manifest.json'
];

// Install — precache critical pages
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_VERSION).then((cache) => {
      return Promise.all(
        PRECACHE_URLS.map((url) =>
          cache.add(url).catch((err) => console.warn('[SW] precache failed:', url, err))
        )
      );
    }).then(() => self.skipWaiting())
  );
});

// Activate — clear old caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys.filter((k) => k !== CACHE_VERSION && k !== RUNTIME_CACHE).map((k) => caches.delete(k))
      )
    ).then(() => self.clients.claim())
  );
});

// Fetch strategy:
//  - For same-origin GET requests → network first, fall back to cache, then offline page.
//  - For POST requests to save endpoints → try network; if offline, let the page handle queueing (client side).
//  - For CDN assets → cache first.
self.addEventListener('fetch', (event) => {
  const req = event.request;

  if (req.method !== 'GET') return;    // POST handled by client-side queue

  const url = new URL(req.url);
  const isCDN = url.hostname.includes('jsdelivr') || url.hostname.includes('googleapis') || url.hostname.includes('qrserver');

  if (isCDN) {
    event.respondWith(
      caches.match(req).then((cached) => cached || fetch(req).then((res) => {
        const copy = res.clone();
        caches.open(RUNTIME_CACHE).then((c) => c.put(req, copy));
        return res;
      }))
    );
    return;
  }

  if (url.origin === self.location.origin) {
    event.respondWith(
      fetch(req)
        .then((res) => {
          const copy = res.clone();
          caches.open(RUNTIME_CACHE).then((c) => c.put(req, copy));
          return res;
        })
        .catch(() => caches.match(req).then((r) => r || caches.match('./dashboard.php')))
    );
  }
});

// Listen for "sync" messages from client
self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'SKIP_WAITING') self.skipWaiting();
});