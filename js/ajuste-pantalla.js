/* ============================================================
   SOVCA — AJUSTE DE PANTALLA
   ------------------------------------------------------------
   Objetivo: que la pantalla de inicio (cabecera + carrusel + pie)
   quepa completa en la ventana, sin desplazador y con el pie
   visible desde la primera carga.

   Calcula el alto REAL disponible para el carrusel:
     alto útil = (borde superior del pie) - (borde superior del slider)

   y lo publica en la variable CSS --sovca-alto-util, que
   css/carrusel.css usa dentro de min(). Si el navegador no
   soporta min(), la variable se ignora y queda el 40% original:
   el carrusel sigue funcionando, solo que puede necesitar scroll.

   Se recalcula al cargar, al redimensionar, al girar el móvil y
   cuando el menú lateral termina de animarse.
   ============================================================ */
(function () {
    'use strict';

    var HERMANOS = '.slider';          // carrusel de fotos del inicio
    var PIE      = '.pie-footer';      // pie de página del layout
    var MINIMO   = 180;                // px: nunca por debajo de esto

    function ajustar() {
        var slider = document.querySelector(HERMANOS);
        if (!slider) { return; }

        var pie = document.querySelector(PIE);
        var arriba = slider.getBoundingClientRect().top;

        /* Si el pie no existe (o no se ha pintado todavía) se usa el
           alto de la ventana menos un margen conservador. */
        var limite = pie
            ? pie.getBoundingClientRect().top
            : (window.innerHeight || document.documentElement.clientHeight) - 70;

        var alto = Math.floor(limite - arriba);

        if (!isFinite(alto) || alto < MINIMO) {
            alto = MINIMO;
        }

        document.documentElement.style.setProperty('--sovca-alto-util', alto + 'px');
    }

    function programar() {
        /* requestAnimationFrame: se mide después de que el navegador
           haya aplicado los estilos y pintado el layout final. */
        if (window.requestAnimationFrame) {
            window.requestAnimationFrame(ajustar);
        } else {
            setTimeout(ajustar, 16);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', programar);
    } else {
        programar();
    }

    window.addEventListener('load', programar);
    window.addEventListener('resize', programar);
    window.addEventListener('orientationchange', programar);

    /* El menú lateral (AdminLTE) anima margin-left durante 300 ms;
       se recalcula al terminar para no medir a mitad de la animación. */
    document.addEventListener('transitionend', function (e) {
        if (e.target && /content-wrapper/i.test(e.target.id || e.target.className || '')) {
            programar();
        }
    }, true);

    /* Al volver a la pestaña el estado puede haber cambiado (zoom,
       barra de direcciones del móvil, teclado en pantalla...). */
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) { programar(); }
    });
})();
