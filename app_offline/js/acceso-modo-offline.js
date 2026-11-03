// ============================================================================
// ENLACES A LA PORTADA DEL MODO OFFLINE (dentro de la app)
//
// `app_offline/inicio.html` es la portada del módulo: ahí están las opciones
// (entrar a la app, instalar el ícono, crear el acceso directo y, si el equipo
// no está preparado, activarlo).
//
// El login del sistema tiene UN SOLO acceso al módulo ("Modo Offline"), así que
// este script ya no decide nada ahí. Lo que hace es ocultar esos enlaces
// internos en teléfonos y tablets: en esos equipos la app se abre desde el
// ícono instalado y no hace falta la portada.
//
// Los elementos a ocultar se marcan con `data-enlace-inicio-offline`.
// ============================================================================

(function () {
  'use strict';

  var ATRIBUTO = 'data-enlace-inicio-offline';

  /** Teléfonos y tablets: ahí el modo offline vive como app instalada. */
  function esMovil() {
    var ua = navigator.userAgent || '';
    if (/Android|iPhone|iPad|iPod|Mobile|Windows Phone/i.test(ua)) { return true; }
    try {
      return window.matchMedia('(pointer: coarse)').matches && window.innerWidth <= 900;
    } catch (e) { return false; }
  }

  function aplicar() {
    var enlaces = document.querySelectorAll('[' + ATRIBUTO + ']');
    if (!enlaces.length) { return; }

    var ocultar = esMovil();

    for (var i = 0; i < enlaces.length; i++) {
      // Se oculta con estilo en línea: algunas hojas declaran display para
      // estos elementos y el atributo [hidden] quedaría sin efecto.
      enlaces[i].style.display = ocultar ? 'none' : '';
      enlaces[i].setAttribute('aria-hidden', ocultar ? 'true' : 'false');
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', aplicar);
  } else {
    aplicar();
  }
})();
