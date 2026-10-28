<?php
header('Content-Type: application/javascript; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$archivos = [
    'index.html',
    'manifest.json',
    'version.php',
    'css/estilos.css',
    'js/config.js',
    'js/credenciales.js',
    'js/db.js',
    'js/api.js',
    'js/sync.js',
    'js/animales.js',
    'js/controlsanitario.js',
    'js/produccionleche.js',
    'js/pesos.js',
    'js/servicios.js',
    'js/reportes.js',
    'js/app.js',
    'icons/icon-192.png',
    'icons/icon-512.png',
    'icons/icon-maskable-192.png',
    'icons/icon-maskable-512.png',
    'icons/apple-touch-icon.png',
];

$partes = [];
foreach ($archivos as $archivoRel) {
    $ruta = __DIR__ . '/' . $archivoRel;
    if (!is_file($ruta)) continue;
    $partes[] = $archivoRel . ':' . filemtime($ruta);
}

$version = $partes ? substr(sha1(implode('|', $partes)), 0, 12) : 'sin-archivos';
?>
// Service worker dinámico para SOVCA Offline.
// La versión del caché se calcula desde mtimes de archivos del shell,
// así cada despliegue de frontend genera un caché nuevo automáticamente.

const VERSION_CACHE = 'sovca-shell-v1-<?php echo $version; ?>';

const ARCHIVOS_SHELL = [
  './',
  './index.html',
  './manifest.json',
  './version.php',
  './css/estilos.css',
  './js/config.js',
  './js/credenciales.js',
  './js/db.js',
  './js/api.js',
  './js/sync.js',
  './js/animales.js',
  './js/controlsanitario.js',
  './js/produccionleche.js',
  './js/pesos.js',
  './js/servicios.js',
  './js/reportes.js',
  './js/app.js',
  './icons/icon-192.png',
  './icons/icon-512.png',
  './icons/icon-maskable-192.png',
  './icons/icon-maskable-512.png',
  './icons/apple-touch-icon.png',
];

self.addEventListener('install', (evento) => {
  evento.waitUntil(
    caches.open(VERSION_CACHE).then((cache) => cache.addAll(ARCHIVOS_SHELL))
  );
  self.skipWaiting();
});

self.addEventListener('message', (evento) => {
  if (!evento.data) return;
  if (evento.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});

self.addEventListener('activate', (evento) => {
  evento.waitUntil(
    caches.keys().then((nombres) =>
      Promise.all(
        nombres
          .filter((nombre) => nombre !== VERSION_CACHE)
          .map((nombre) => caches.delete(nombre))
      )
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', (evento) => {
  const url = new URL(evento.request.url);

  if (url.pathname.includes('/api/')) return;
  if (evento.request.method !== 'GET') return;

  const esShellCritico =
    evento.request.destination === 'document'
    || evento.request.destination === 'style'
    || evento.request.destination === 'script';

  if (esShellCritico) {
    evento.respondWith(
      fetch(evento.request)
        .then((respuestaRed) => {
          const copia = respuestaRed.clone();
          caches.open(VERSION_CACHE).then((cache) => cache.put(evento.request, copia));
          return respuestaRed;
        })
        .catch(() => caches.match(evento.request))
    );
    return;
  }

  evento.respondWith(
    caches.match(evento.request).then((enCache) => {
      if (enCache) return enCache;

      return fetch(evento.request)
        .then((respuestaRed) => {
          const copia = respuestaRed.clone();
          caches.open(VERSION_CACHE).then((cache) => cache.put(evento.request, copia));
          return respuestaRed;
        })
        .catch(() => enCache);
    })
  );
});
