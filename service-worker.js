const CACHE_NAME = 'webnote-cache-v1';
const ASSETS = [
  '/',                // root → index.php
  '/index.php',
  '/style.css',
  '/script.js',
  '/login.php',
  '/profile.php',
  '/save_note.php',
  '/offline.html',    // opțional
  // + orice imagini, fonturi etc.
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(ASSETS))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(
        keys.filter(key => key !== CACHE_NAME)
            .map(key => caches.delete(key))
      )
    )
  );
});

// network-first pentru navigații (SPA fallback)
self.addEventListener('fetch', event => {
  if (event.request.mode === 'navigate') {
    event.respondWith(
      fetch(event.request)
        .catch(() => caches.match('/offline.html') || caches.match('/index.php'))
    );
    return;
  }
  // restul cererilor: încearcă rețeaua, altfel cache
  event.respondWith(
    fetch(event.request)
      .then(res => {
        const copy = res.clone();
        caches.open(CACHE_NAME).then(cache => cache.put(event.request, copy));
        return res;
      })
      .catch(() => caches.match(event.request))
  );
});
self.addEventListener('install', event => {
    event.waitUntil(
      caches.open(CACHE_NAME)
        .then(cache => cache.addAll(ASSETS))
        .catch(err => {
          console.error('SW install failed:', err);
          throw err;
        })
        .then(() => self.skipWaiting())
    );
  });
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/service-worker.js')
      .then(() => console.log('SW registered'))
      .catch(console.error);
  }
  