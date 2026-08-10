const WCB_CACHE = 'redjili-pwa-v6';
const WCB_ASSETS = [
  '/',
  '/index.php',
  '/manifest.php',
  '/pwa-install.js',
  '/assets/css/mobile-scroll-fix.css',
  '/assets/icons/icon-16.png',
  '/assets/icons/icon-32.png',
  '/assets/icons/icon-192.png',
  '/assets/icons/icon-512.png',
  '/assets/icons/maskable-192.png',
  '/assets/icons/maskable-512.png'
];

self.addEventListener('install', event => {
  event.waitUntil(caches.open(WCB_CACHE).then(cache => cache.addAll(WCB_ASSETS).catch(() => null)));
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => Promise.all(keys.filter(k => k !== WCB_CACHE).map(k => caches.delete(k))))
  );
  self.clients.claim();
});

self.addEventListener('fetch', event => {
  const req = event.request;
  if (req.method !== 'GET') return;
  event.respondWith(
    fetch(req).then(res => {
      const copy = res.clone();
      caches.open(WCB_CACHE).then(cache => cache.put(req, copy)).catch(() => null);
      return res;
    }).catch(() => caches.match(req).then(cached => cached || caches.match('/index.php')))
  );
});
