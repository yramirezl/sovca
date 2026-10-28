/* ============================================================
   SOVCA | Pantalla de inicio
   ------------------------------------------------------------
   Compatible con el marcado de BOVS y con el de SOVCA:

     · Saludo y fecha   → [data-bovs-saludo] / [data-sovca-saludo]
                          [data-bovs-fecha]  / [data-sovca-fecha]
     · Carrusel del hero→ .bovs-inicio-hero / .sovca-inicio-hero
                          con sus __slide y sus puntos

   Si la pantalla no tiene ese marcado (por ejemplo el carrusel de
   radios que usa hoy views/layouts/carrusel_bovinos.php), el script
   no hace nada y no interfiere con el carrusel existente.

   - Carrusel del hero: fundido, autoplay pausable, swipe táctil
     y navegación por los indicadores.
   - Saludo dinámico según la hora del dispositivo y fecha local.
   - Precarga diferida de los banners restantes para no frenar la
     primera pintura en móvil.
   Sin dependencias (no usa jQuery).
   ============================================================ */
(function () {
  'use strict';

  function porId(sel, ctx) {
    return (ctx || document).querySelector(sel);
  }

  function todos(sel, ctx) {
    return Array.prototype.slice.call((ctx || document).querySelectorAll(sel));
  }

  /* Primer elemento que exista de una lista de selectores equivalentes. */
  function primero(selectores, ctx) {
    for (var i = 0; i < selectores.length; i++) {
      var el = porId(selectores[i], ctx);
      if (el) { return el; }
    }
    return null;
  }

  /* Todos los que existan, sin duplicados (por si comparten clase). */
  function todosPrimero(selectores, ctx) {
    var vistos = [];
    selectores.forEach(function (sel) {
      todos(sel, ctx).forEach(function (el) {
        if (vistos.indexOf(el) === -1) { vistos.push(el); }
      });
    });
    return vistos;
  }

  var HERO = ['.bovs-inicio-hero', '.sovca-inicio-hero'];
  var SLIDE = ['.bovs-inicio-hero__slide', '.sovca-inicio-hero__slide'];
  var DOT = ['.bovs-inicio-dot', '.sovca-inicio-dot'];

  /* ---------------------------------------------------------
     Saludo y fecha
     --------------------------------------------------------- */
  function initSaludo() {
    var saludo = primero(['[data-bovs-saludo]', '[data-sovca-saludo]']);
    if (saludo) {
      var nombre = (saludo.getAttribute('data-nombre') || '').trim();
      var hora = new Date().getHours();
      var franja = hora < 12 ? 'Buenos días' : (hora < 19 ? 'Buenas tardes' : 'Buenas noches');
      saludo.textContent = nombre ? franja + ', ' + nombre : franja;
    }

    var fecha = primero(['[data-bovs-fecha]', '[data-sovca-fecha]']);
    if (fecha) {
      try {
        var texto = new Date().toLocaleDateString('es-VE', {
          weekday: 'long',
          day: 'numeric',
          month: 'long'
        });
        if (texto) {
          fecha.textContent = texto.charAt(0).toUpperCase() + texto.slice(1);
        }
      } catch (e) {
        /* Si el navegador no soporta el locale, se queda la fecha del PHP */
      }
    }
  }

  /* ---------------------------------------------------------
     Carrusel del hero
     --------------------------------------------------------- */
  function initHero() {
    var hero = primero(HERO);
    if (!hero) return;

    var slides = todosPrimero(SLIDE, hero);
    if (slides.length < 2) return;

    var dots = todosPrimero(DOT, hero);
    var actual = 0;
    var timer = null;
    var DELAY = 7000;
    var reducido = window.matchMedia &&
                   window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function pintar(n, avisar) {
      actual = (n + slides.length) % slides.length;

      slides.forEach(function (slide, i) {
        var activo = i === actual;
        slide.classList.toggle('is-active', activo);
        slide.setAttribute('aria-hidden', activo ? 'false' : 'true');
      });

      dots.forEach(function (dot, i) {
        var activo = i === actual;
        dot.classList.toggle('is-active', activo);
        if (activo) {
          dot.setAttribute('aria-current', 'true');
        } else {
          dot.removeAttribute('aria-current');
        }
      });

      if (avisar) {
        /* Anuncia solo el cambio manual, para no saturar lectores */
        hero.setAttribute('data-slide', String(actual + 1));
      }
    }

    function parar() {
      if (timer) {
        window.clearTimeout(timer);
        timer = null;
      }
    }

    function arrancar() {
      parar();
      if (reducido || document.hidden) return;
      timer = window.setTimeout(function () {
        pintar(actual + 1, false);
        arrancar();
      }, DELAY);
    }

    function ir(n) {
      pintar(n, true);
      arrancar();
    }

    dots.forEach(function (dot, i) {
      dot.addEventListener('click', function () {
        ir(i);
      });
    });

    /* Swipe horizontal */
    var x0 = 0, y0 = 0, siguiendo = false;
    hero.addEventListener('touchstart', function (e) {
      var t = e.changedTouches[0];
      x0 = t.clientX;
      y0 = t.clientY;
      siguiendo = true;
      parar();
    }, { passive: true });

    hero.addEventListener('touchend', function (e) {
      if (!siguiendo) return;
      siguiendo = false;
      var t = e.changedTouches[0];
      var dx = t.clientX - x0;
      var dy = t.clientY - y0;

      if (Math.abs(dx) > 45 && Math.abs(dx) > Math.abs(dy)) {
        ir(actual + (dx < 0 ? 1 : -1));
      } else {
        arrancar();
      }
    }, { passive: true });

    hero.addEventListener('touchcancel', function () {
      siguiendo = false;
      arrancar();
    }, { passive: true });

    /* En escritorio, pausa al pasar el ratón */
    hero.addEventListener('mouseenter', parar);
    hero.addEventListener('mouseleave', arrancar);

    document.addEventListener('visibilitychange', function () {
      if (document.hidden) {
        parar();
      } else {
        arrancar();
      }
    });

    pintar(0, false);
    arrancar();

    /* Precarga diferida: el resto de banners se descargan después de
       la primera pintura para no competir con el contenido principal. */
    var diferidas = todosPrimero([
      '.bovs-inicio-hero__slide img[data-src]',
      '.sovca-inicio-hero__slide img[data-src]'
    ], hero);
    if (diferidas.length) {
      var cargar = function () {
        diferidas.forEach(function (img) {
          if (img.getAttribute('src')) return;
          img.setAttribute('src', img.getAttribute('data-src'));
          img.removeAttribute('data-src');
        });
      };
      if ('requestIdleCallback' in window) {
        window.requestIdleCallback(cargar, { timeout: 2500 });
      } else {
        window.setTimeout(cargar, 1200);
      }
    }
  }

  function init() {
    initSaludo();
    initHero();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
