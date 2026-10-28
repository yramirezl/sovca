/* =====================================================================
   SERVICE WORKER DEL PORTAL (no del modo offline)
   ---------------------------------------------------------------------
   Su única función es que el navegador considere el sitio como
   "instalable" (Chrome/Edge exigen un service worker con manejador de
   fetch para mostrar el aviso de instalación).

   NO CACHEA NADA: todas las peticiones van a la red tal cual. El modo
   offline real vive en /app_offline/ con su propio service worker y su
   propio scope, así que aquí no lo interceptamos.

   Ubicación: <carpeta de la app>/sw.js  →  scope automático de esa carpeta.
   ===================================================================== */

'use strict';

/* Instalación y activación inmediatas: no hay caché que poblar. */
self.addEventListener('install', function () {
    self.skipWaiting();
});

self.addEventListener('activate', function (evento) {
    evento.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', function (evento) {
    var peticion = evento.request;

    /* Solo lecturas del mismo origen. Los POST (formularios, API)
       pasan directo al servidor sin pasar por aquí. */
    if (peticion.method !== 'GET') {
        return;
    }

    var url;
    try {
        url = new URL(peticion.url);
    } catch (e) {
        return;
    }
    if (url.origin !== self.location.origin) {
        return;
    }

    /* El portal offline gestiona su propio ciclo de vida. */
    if (url.pathname.indexOf('/app_offline/') !== -1) {
        return;
    }

    /* Paso directo a la red: comportamiento idéntico al de no tener SW. */
    evento.respondWith(fetch(peticion));
});
