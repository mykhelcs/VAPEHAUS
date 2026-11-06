/**
 * VAPEHAUS SERVICE WORKER
 * Offline Caching for Database Pictures (Supabase Storage CDN, WebP, SVGs)
 * and Catalog Datasets (Products, Categories, Shell)
 */

const CACHE_MEDIA = 'vapehaus-media-v1';
const CACHE_API = 'vapehaus-api-v1';
const CACHE_SHELL = 'vapehaus-shell-v1';

const SHELL_ASSETS = [
  './',
  'index.html',
  'css/theme.css',
  'css/storefront.css',
  'js/icons.js',
  'js/config.js',
  'js/domain/money.js',
  'js/domain/order-status.js',
  'js/store/state.js',
  'js/api/client.js',
  'js/offline-queue.js',
  'js/components/notifications.js',
  'js/components/auth-modal.js',
  'js/components/age-gate.js',
  'js/components/quick-view.js',
  'js/components/cart-drawer.js',
  'js/components/checkout.js',
  'js/components/order-tracker.js',
  'js/storefront.js',
  'images/logo.svg',
  'images/logo-icon.svg',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_SHELL).then((cache) => {
      return cache.addAll(SHELL_ASSETS).catch((err) => {
        console.warn('VAPEHAUS SW: Non-fatal shell precache warning', err);
      });
    })
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.map((key) => {
          if (![CACHE_MEDIA, CACHE_API, CACHE_SHELL].includes(key)) {
            return caches.delete(key);
          }
        })
      );
    })
  );
  self.clients.claim();
});

function isImageRequest(request, url) {
  return (
    request.destination === 'image' ||
    url.pathname.match(/\.(webp|png|jpg|jpeg|svg|gif|ico)$/i) ||
    url.hostname.includes('supabase.co') && url.pathname.includes('/storage/') ||
    url.pathname.includes('/uploads/')
  );
}

function isApiGetRequest(request, url) {
  return (
    request.method === 'GET' &&
    (url.pathname.includes('/api/v1/products') ||
     url.pathname.includes('/api/v1/categories') ||
     url.pathname.includes('/api/v1/health'))
  );
}

self.addEventListener('fetch', (event) => {
  const request = event.request;
  const url = new URL(request.url);

  // 1. Pictures & Database Media Cache Strategy: Cache-First with Network Fallback
  if (isImageRequest(request, url)) {
    event.respondWith(
      caches.open(CACHE_MEDIA).then(async (cache) => {
        const cached = await cache.match(request);
        if (cached) {
          // Serve from cache immediately; update cache in background
          fetch(request).then((networkRes) => {
            if (networkRes && networkRes.status === 200) {
              cache.put(request, networkRes.clone());
            }
          }).catch(() => {});
          return cached;
        }

        try {
          const networkRes = await fetch(request);
          if (networkRes && networkRes.status === 200) {
            cache.put(request, networkRes.clone());
          }
          return networkRes;
        } catch (err) {
          // If offline and image not cached, fallback to logo icon if available
          const fallback = await cache.match('images/logo-icon.svg');
          return fallback || new Response('', { status: 408, statusText: 'Offline' });
        }
      })
    );
    return;
  }

  // 2. API Catalog Data Strategy: Network-First with Cache Fallback
  if (isApiGetRequest(request, url)) {
    event.respondWith(
      caches.open(CACHE_API).then(async (cache) => {
        try {
          const networkRes = await fetch(request);
          if (networkRes && networkRes.status === 200) {
            cache.put(request, networkRes.clone());
          }
          return networkRes;
        } catch (err) {
          const cached = await cache.match(request);
          if (cached) {
            return cached;
          }
          return new Response(JSON.stringify({
            success: false,
            error: {
              code: 'OFFLINE',
              message: 'You are currently offline. Please check your connection.',
              details: []
            }
          }), {
            status: 503,
            headers: { 'Content-Type': 'application/json' }
          });
        }
      })
    );
    return;
  }

  // 3. App Shell Strategy: Stale-While-Revalidate
  if (request.method === 'GET' && request.mode === 'navigate') {
    event.respondWith(
      fetch(request).catch(() => caches.match('index.html'))
    );
    return;
  }
});
