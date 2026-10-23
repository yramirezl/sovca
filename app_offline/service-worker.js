// Service worker de SOVCA Offline.
//
// Regla simple y explícita: el "shell" de la app (HTML/CSS/JS/íconos)
// se cachea para que la app abra sin conexión. Las llamadas al API
// (../api/...) NUNCA se cachean aquí — esas las gestiona sync.js contra
// IndexedDB, que es donde vive la verdad de los datos offline.

const VERSION_CACHE = 'sovca-shell-v1'; // v1: primera versión SOVCA (antes bovs-shell-v88)

const ARCHIVOS_SHELL = [
  './',
  './index.html',
  './manifest.json',
  './version.php',
  './css/estilos.css',
  './css/modal.css',
  './css/calendario.css',
  './js/config.js',
  './js/dispositivo.js',
  './js/credenciales.js',
  './js/db.js',
  './js/api.js',
  './js/sync.js',
  './js/animales.js',
  './js/controlsanitario.js',
  './js/produccionleche.js',
  './js/pesos.js',
  './js/servicios.js',
  './js/nacimientos.js',
  './js/pdf-simple.js',
  './js/fotos.js',
  './js/reportes.js',
  './js/buscador-animal.js',
  './js/calendario.js',
  './js/controlsanitariolotes.js',
  './js/desincorporacion.js',
  './js/movimientorebano.js',
  './js/ficha-animal.js',
  './js/app.js',
  './icons/icon-192.png',
  './icons/icon-512.png',
];

self.addEventListener('install', (evento) => {
  evento.waitUntil(
    caches.open(VERSION_CACHE).then((cache) => Promise.all(
      ARCHIVOS_SHELL.map((url) =>
        // Se pide cada archivo SALTEANDO el caché HTTP: Apache sirve js/ y
        // css/ con max-age de 30 días, así que un cache.addAll() normal
        // guardaría la versión vieja dentro del shell nuevo.
        fetch(new Request(url, { cache: 'reload' })).then((respuesta) => {
          if (!respuesta || !respuesta.ok) {
            throw new Error('No se pudo guardar en caché: ' + url);
          }
          return cache.put(url, respuesta);
        })
      )
    ))
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

  // Nunca interceptar el API.
  if (url.pathname.includes('/api/')) return;
  // version.php tampoco: queremos siempre la versión del servidor,
  // incluso si todo el shell se sirve desde caché.
  if (url.pathname.endsWith('/version.php')) return;
  if (evento.request.method !== 'GET') return;

  const esShellCritico =
    evento.request.destination === 'document'
    || evento.request.destination === 'style'
    || evento.request.destination === 'script';

  if (esShellCritico) {
    evento.respondWith(
      // "no-cache" obliga a revalidar con el servidor (ETag): si el archivo
      // no cambió es un 304 barato y, si cambió, llega la versión nueva en
      // vez de la copia de 30 días del caché HTTP.
      fetch(new Request(evento.request, { cache: 'no-cache' }))
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