/* ============================================================
   Calendario propio de BOVS Offline.
   Se usa en lugar del selector nativo del navegador, que no es
   amigable: al elegir año/mes a veces no vuelve a los días.
   El <input type="date"> se mantiene como almacén del valor
   (AAAA-MM-DD), así el resto de la app sigue leyendo .value igual.
   ============================================================ */

(function () {
  'use strict';

  var MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
    'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
  var DIAS_SEMANA = ['lu', 'ma', 'mi', 'ju', 'vi', 'sá', 'do'];
  var ANIO_MINIMO = 1960;

  var popup = null;
  var inputActivo = null;
  var vista = new Date();
  var aniosCacheados = '';

  function dosDigitos(n) {
    return String(n).padStart(2, '0');
  }

  function isoDe(anio, mesCero, dia) {
    return anio + '-' + dosDigitos(mesCero + 1) + '-' + dosDigitos(dia);
  }

  function hoyISO() {
    var hoy = new Date();
    return isoDe(hoy.getFullYear(), hoy.getMonth(), hoy.getDate());
  }

  function mesISO(fechaISO) {
    return fechaISO.slice(0, 7) + '-01';
  }

  /* Mantiene la vista dentro de los meses permitidos cuando el campo
     tiene min/max (por ejemplo "solo pasado" o "desde hoy"). */
  function limitarVista() {
    if (!inputActivo) return;
    var minimo = inputActivo.min || '';
    var maximo = inputActivo.max || '';
    var inicio = isoDe(vista.getFullYear(), vista.getMonth(), 1);

    if (minimo && inicio < mesISO(minimo)) {
      vista = new Date(Number(minimo.slice(0, 4)), Number(minimo.slice(5, 7)) - 1, 1);
    } else if (maximo && inicio > mesISO(maximo)) {
      vista = new Date(Number(maximo.slice(0, 4)), Number(maximo.slice(5, 7)) - 1, 1);
    }
  }

  function leerISO(input) {
    if (!input) return '';
    var m = /^(\d{4})-(\d{2})-(\d{2})/.exec((input.value || '').trim());
    return m ? m[1] + '-' + m[2] + '-' + m[3] : '';
  }

  function crearPopup() {
    if (popup) return popup;

    popup = document.createElement('div');
    popup.className = 'cal-bovs';
    popup.hidden = true;
    popup.setAttribute('role', 'dialog');
    popup.setAttribute('aria-label', 'Seleccionar fecha');

    var opcionesMes = MESES.map(function (mes, indice) {
      return '<option value="' + indice + '">' + mes + '</option>';
    }).join('');

    popup.innerHTML =
      '<div class="cal-bovs__cabecera">' +
        '<button type="button" class="cal-bovs__nav" data-cal-prev aria-label="Mes anterior">&#8249;</button>' +
        '<button type="button" class="cal-bovs__titulo" data-cal-mes-anio aria-expanded="false"></button>' +
        '<button type="button" class="cal-bovs__nav" data-cal-next aria-label="Mes siguiente">&#8250;</button>' +
      '</div>' +
      '<div class="cal-bovs__selector" hidden>' +
        '<label class="cal-bovs__campo">Mes' +
          '<select data-cal-mes>' + opcionesMes + '</select>' +
        '</label>' +
        '<label class="cal-bovs__campo">Año' +
          '<select data-cal-anio></select>' +
        '</label>' +
      '</div>' +
      '<div class="cal-bovs__semana">' +
        DIAS_SEMANA.map(function (dia) { return '<span>' + dia + '</span>'; }).join('') +
      '</div>' +
      '<div class="cal-bovs__dias" data-cal-dias></div>' +
      '<div class="cal-bovs__pie">' +
        '<button type="button" class="cal-bovs__accion" data-cal-hoy>Hoy</button>' +
        '<button type="button" class="cal-bovs__accion cal-bovs__accion--tenue" data-cal-limpiar>Limpiar</button>' +
        '<button type="button" class="cal-bovs__accion cal-bovs__accion--cerrar" data-cal-cerrar>Cerrar</button>' +
      '</div>';

    document.body.appendChild(popup);

    popup.addEventListener('click', manejarClickPopup);
    popup.addEventListener('change', manejarCambioPopup);

    document.addEventListener('keydown', function (evento) {
      if (evento.key === 'Escape' && popup && !popup.hidden) {
        cerrar();
      }
    });

    document.addEventListener('click', function (evento) {
      if (!popup || popup.hidden) return;
      if (popup.contains(evento.target)) return;
      if (evento.target === inputActivo) return;
      cerrar();
    });

    window.addEventListener('scroll', function () {
      if (popup && !popup.hidden) posicionar();
    }, true);
    window.addEventListener('resize', function () {
      if (popup && !popup.hidden) posicionar();
    });

    return popup;
  }

  function manejarClickPopup(evento) {
    var objetivo = evento.target.closest ? evento.target.closest('[data-cal-prev],[data-cal-next],[data-cal-mes-anio],[data-cal-dia],[data-cal-hoy],[data-cal-limpiar],[data-cal-cerrar]') : null;
    if (!objetivo) return;
    evento.preventDefault();

    if (objetivo.hasAttribute('data-cal-prev')) {
      vista.setMonth(vista.getMonth() - 1);
      limitarVista();
      renderizar();
      posicionar();
      return;
    }
    if (objetivo.hasAttribute('data-cal-next')) {
      vista.setMonth(vista.getMonth() + 1);
      limitarVista();
      renderizar();
      posicionar();
      return;
    }
    if (objetivo.hasAttribute('data-cal-mes-anio')) {
      var selector = popup.querySelector('.cal-bovs__selector');
      var abrir = selector.hidden;
      selector.hidden = !abrir;
      objetivo.setAttribute('aria-expanded', abrir ? 'true' : 'false');
      posicionar();
      return;
    }
    if (objetivo.hasAttribute('data-cal-dia')) {
      var dia = Number(objetivo.getAttribute('data-cal-dia'));
      if (!dia) return;
      aplicarFecha(isoDe(vista.getFullYear(), vista.getMonth(), dia));
      return;
    }
    if (objetivo.hasAttribute('data-cal-hoy')) {
      var hoy = new Date();
      vista = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
      renderizar();
      aplicarFecha(hoyISO());
      return;
    }
    if (objetivo.hasAttribute('data-cal-limpiar')) {
      aplicarFecha('');
      return;
    }
    if (objetivo.hasAttribute('data-cal-cerrar')) {
      cerrar();
    }
  }

  function manejarCambioPopup(evento) {
    if (evento.target.matches('[data-cal-mes]')) {
      vista.setMonth(Number(evento.target.value));
    } else if (evento.target.matches('[data-cal-anio]')) {
      vista.setFullYear(Number(evento.target.value));
    } else {
      return;
    }
    // Al elegir mes o año se vuelve de inmediato a la grilla de días.
    var selector = popup.querySelector('.cal-bovs__selector');
    selector.hidden = true;
    popup.querySelector('[data-cal-mes-anio]').setAttribute('aria-expanded', 'false');
    limitarVista();
    renderizar();
    posicionar();
  }

  function llenarAnios() {
    var select = popup.querySelector('[data-cal-anio]');
    var anioActual = new Date().getFullYear();
    var desde = Math.min(ANIO_MINIMO, vista.getFullYear());
    var hasta = Math.max(anioActual + 2, vista.getFullYear() + 1);
    var firma = desde + '-' + hasta;
    if (firma === aniosCacheados) return;
    aniosCacheados = firma;

    var html = '';
    for (var anio = desde; anio <= hasta; anio++) {
      html += '<option value="' + anio + '">' + anio + '</option>';
    }
    select.innerHTML = html;
  }

  function renderizar() {
    if (!popup) return;

    popup.querySelector('[data-cal-mes-anio]').textContent =
      MESES[vista.getMonth()] + ' de ' + vista.getFullYear();

    llenarAnios();
    popup.querySelector('[data-cal-mes]').value = String(vista.getMonth());
    popup.querySelector('[data-cal-anio]').value = String(vista.getFullYear());

    var contenedor = popup.querySelector('[data-cal-dias]');
    contenedor.innerHTML = '';

    var primerDia = new Date(vista.getFullYear(), vista.getMonth(), 1);
    var desplazamiento = (primerDia.getDay() + 6) % 7; // lunes = 0
    var totalDias = new Date(vista.getFullYear(), vista.getMonth() + 1, 0).getDate();

    var i;
    for (i = 0; i < desplazamiento; i++) {
      var vacio = document.createElement('span');
      vacio.className = 'cal-bovs__vacio';
      contenedor.appendChild(vacio);
    }

    var seleccionado = leerISO(inputActivo);
    var hoy = hoyISO();
    var minimo = inputActivo && inputActivo.min ? inputActivo.min : '';
    var maximo = inputActivo && inputActivo.max ? inputActivo.max : '';

    for (var dia = 1; dia <= totalDias; dia++) {
      var iso = isoDe(vista.getFullYear(), vista.getMonth(), dia);
      var boton = document.createElement('button');
      boton.type = 'button';
      boton.className = 'cal-bovs__dia';
      boton.setAttribute('data-cal-dia', String(dia));
      boton.textContent = String(dia);
      if (iso === hoy) boton.classList.add('cal-bovs__dia--hoy');
      if (iso === seleccionado) boton.classList.add('cal-bovs__dia--seleccionado');
      if ((minimo && iso < minimo) || (maximo && iso > maximo)) {
        boton.disabled = true;
        boton.classList.add('cal-bovs__dia--deshabilitado');
      }
      contenedor.appendChild(boton);
    }

    var inicioMesActual = isoDe(vista.getFullYear(), vista.getMonth(), 1);
    var botonPrev = popup.querySelector('[data-cal-prev]');
    var botonNext = popup.querySelector('[data-cal-next]');
    botonPrev.disabled = !!(minimo && inicioMesActual <= mesISO(minimo));
    botonNext.disabled = !!(maximo && inicioMesActual >= mesISO(maximo));
  }

  function posicionar() {
    if (!popup || popup.hidden || !inputActivo) return;
    if (!inputActivo.isConnected) {
      cerrar();
      return;
    }

    var rect = inputActivo.getBoundingClientRect();
    var ancho = popup.offsetWidth;
    var alto = popup.offsetHeight;
    var margen = 8;

    var izquierda = Math.min(Math.max(margen, rect.left), window.innerWidth - ancho - margen);
    var arriba = rect.bottom + 6;
    if (arriba + alto > window.innerHeight - margen && rect.top - alto - 6 > margen) {
      arriba = rect.top - alto - 6;
    }

    popup.style.left = izquierda + 'px';
    popup.style.top = arriba + 'px';
  }

  function aplicarFecha(iso) {
    if (!inputActivo) return;
    inputActivo.value = iso;
    inputActivo.dispatchEvent(new Event('input', { bubbles: true }));
    inputActivo.dispatchEvent(new Event('change', { bubbles: true }));
    cerrar();
  }

  function abrir(input) {
    if (!input || input.disabled) return;

    crearPopup();

    if (!popup.hidden && inputActivo === input) {
      cerrar();
      return;
    }

    inputActivo = input;

    var partes = /^(\d{4})-(\d{2})-(\d{2})/.exec((input.value || '').trim());
    if (partes) {
      vista = new Date(Number(partes[1]), Number(partes[2]) - 1, 1);
    } else {
      var hoy = new Date();
      vista = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
    }

    popup.querySelector('.cal-bovs__selector').hidden = true;
    popup.querySelector('[data-cal-mes-anio]').setAttribute('aria-expanded', 'false');

    renderizar();
    popup.hidden = false;
    posicionar();
  }

  function cerrar() {
    if (!popup) return;
    popup.hidden = true;
    if (inputActivo && inputActivo.isConnected) inputActivo.blur();
    inputActivo = null;
  }

  function mejorarEntradas(raiz) {
    var contenedor = raiz || document;
    Array.prototype.forEach.call(contenedor.querySelectorAll('input[type="date"]'), function (input) {
      if (input.dataset.calendarioBovs === '1') return;
      // Campos ya de solo lectura (valores calculados) no se editan.
      if (input.hasAttribute('readonly')) return;

      input.dataset.calendarioBovs = '1';
      input.readOnly = true;
      input.classList.add('cal-bovs-input');
      input.setAttribute('autocomplete', 'off');

      /* Límites de selección según el tipo de fecha:
         - data-solo-pasado: hoy o antes (registros ya ocurridos)
         - data-solo-futuro: hoy o después (planificación) */
      if (input.hasAttribute('data-solo-pasado')) {
        input.max = hoyISO();
      } else if (input.hasAttribute('data-solo-futuro')) {
        input.min = hoyISO();
      }

      input.addEventListener('click', function (evento) {
        evento.preventDefault();
        evento.stopPropagation();
        abrir(input);
      });

      input.addEventListener('keydown', function (evento) {
        if (evento.key === 'Enter' || evento.key === ' ' || evento.key === 'ArrowDown') {
          evento.preventDefault();
          abrir(input);
        }
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { mejorarEntradas(); });
  } else {
    mejorarEntradas();
  }

  // Expuesto por si en el futuro se agregan inputs de fecha dinámicos.
  window.CalendarioBovs = { mejorar: mejorarEntradas, abrir: abrir, cerrar: cerrar };
})();
