/**
 * Service Worker — activos versionados; red primero para /assets/ (actualizaciones CSS/JS).
 * La versión llega en la URL de registro: sw.js?v={APP_ASSET_VERSION}
 */
'use strict';

const BUILD = new URL(self.location.href).searchParams.get('v') || '1';
const STATIC_CACHE = `contraste-static-${BUILD}`;
const RUNTIME_CACHE = `contraste-runtime-${BUILD}`;

function precacheUrls() {
  const q = `?v=${encodeURIComponent(BUILD)}`;
  return [
    `./assets/css/app.css${q}`,
    `./assets/css/lebytek-ui.css${q}`,
    `./assets/js/app.js${q}`,
    './manifest.webmanifest',
  ];
}

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches
      .open(STATIC_CACHE)
      .then((cache) => cache.addAll(precacheUrls()))
      .then(() => self.skipWaiting())
      .catch(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) =>
        Promise.all(
          keys.map((key) => {
            if (key === STATIC_CACHE || key === RUNTIME_CACHE) {
              return Promise.resolve();
            }
            if (key.startsWith('contraste-static-') || key.startsWith('contraste-runtime-')) {
              return caches.delete(key);
            }
            return Promise.resolve();
          })
        )
      )
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;

  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;

  // Activos estáticos: red primero; caché solo como fallback offline
  if (url.pathname.includes('/assets/')) {
    event.respondWith(
      fetch(req)
        .then((res) => {
          if (res.ok) {
            caches.open(RUNTIME_CACHE).then((cache) => cache.put(req, res.clone()));
          }
          return res;
        })
        .catch(() => caches.match(req))
    );
    return;
  }

  // Manifest: red primero para reflejar cambios de icono en ajustes
  if (url.pathname.endsWith('manifest.webmanifest')) {
    event.respondWith(
      fetch(req)
        .then((res) => {
          if (res.ok) {
            const copy = res.clone();
            caches.open(STATIC_CACHE).then((c) => c.put(req, copy));
          }
          return res;
        })
        .catch(() => caches.match(req))
    );
  }
});
