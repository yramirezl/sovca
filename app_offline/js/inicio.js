// Pagina de inicio del modo offline (portada).
//
// Responsabilidades:
//   1. Registrar el service worker para que la propia portada y la app
//      queden disponibles sin conexion (y para que el navegador permita
//      instalar la PWA).
//   2. Averiguar el estado REAL de este equipo (app instalada, datos
//      descargados, sesion abierta) y decirlo en una linea.
//   3. Ofrecer la instalacion como app, que es lo que crea el icono en
//      la pantalla de inicio / escritorio del dispositivo.
//   4. Si el equipo ya esta preparado y la app se abrio desde el icono,
//      pasar directo a la app (la portada no debe estorbar en el uso
//      diario); se puede forzar la portada con ?inicio=1.
//
// No usa ninguna libreria: la portada tiene que abrir incluso cuando el
// equipo nunca cargó nada desde internet.

(function () {
  'use strict';

  const CLAVE_AVISO_INSTALADO = 'sovca_portada_instalada_ok';

  let promptInstalacion = null;
  let yaInstalada = false;
  let equipoPreparado = false;

  // ---------------------------------------------------------------
  // Utilidades
  // ---------------------------------------------------------------

  function el(id) {
    return document.getElementById(id);
  }

  function esIos() {
    const ua = navigator.userAgent || '';
    const touchMac = navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1;
    return /iPhone|iPad|iPod/i.test(ua) || touchMac;
  }

  function esAndroid() {
    return /Android/i.test(navigator.userAgent || '');
  }

  function plataforma() {
    if (esIos()) return 'ios';
    if (esAndroid()) return 'android';
    return 'escritorio';
  }

  function enModoApp() {
    try {
      return window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true;
    } catch (e) {
      return window.navigator.standalone === true;
    }
  }

  function leerRecordatorioInstalado() {
    try {
      return localStorage.getItem(CLAVE_AVISO_INSTALADO) === '1';
    } catch (e) {
      return false;
    }
  }

  function guardarRecordatorioInstalado() {
    try {
      localStorage.setItem(CLAVE_AVISO_INSTALADO, '1');
    } catch (e) { /* sin localStorage: no es crítico */ }
  }

  // ---------------------------------------------------------------
  // Estado del equipo
  // ---------------------------------------------------------------

  /**
   * Mira la base local del equipo (IndexedDB) para saber si este
   * dispositivo ya puede trabajar sin conexión.
   *
   * Se usan las mismas funciones que la app (js/db.js) y no consultas
   * propias: si mañana cambia el nombre de los almacenes, la portada no
   * se queda con datos viejos. Cualquier falla se trata como "equipo sin
   * preparar": es mejor mostrar de más el aviso de preparación que dejar
   * al productor creyendo que tiene datos cuando no los tiene.
   */
  async function revisarEquipo() {
    const estado = { sesion: null, credencial: null, fundos: 0, error: null };

    if (typeof Db === 'undefined') {
      return estado;
    }

    try {
      estado.sesion = await Db.obtenerSesion();
    } catch (e) { /* sin sesión */ }

    try {
      estado.credencial = await Db.algunaCredencialOffline();
    } catch (e) { /* sin credencial */ }

    try {
      const fundos = await Db.listarFundosMeta();
      estado.fundos = Array.isArray(fundos) ? fundos.length : 0;
    } catch (e) { /* sin fundos descargados */ }

    return estado;
  }

  function pintarEstado(tipo, icono, titulo, texto) {
    const caja = el('estado');
    if (!caja) return;

    caja.className = 'portada__estado portada__estado--' + tipo;
    el('estado-icono').textContent = icono;
    el('estado-titulo').textContent = titulo;
    el('estado-texto').textContent = texto || '';
  }

  function pintarConexion() {
    const etiqueta = el('etiqueta-conexion');
    if (!etiqueta) return;

    if (navigator.onLine) {
      etiqueta.textContent = 'Con conexión';
      etiqueta.className = 'portada__pie-estado portada__pie-estado--online';
    } else {
      etiqueta.textContent = 'Sin conexión';
      etiqueta.className = 'portada__pie-estado portada__pie-estado--offline';
    }
  }

  // ---------------------------------------------------------------
  // Instalación como app (crea el icono en el dispositivo)
  // ---------------------------------------------------------------

  function mostrarBotonInstalar(mostrar) {
    const boton = el('boton-instalar');
    if (!boton) return;
    boton.classList.toggle('oculto', !mostrar);
  }

  function configurarInstalacion() {
    yaInstalada = enModoApp();

    if (yaInstalada) {
      mostrarBotonInstalar(false);
      return;
    }

    // Chrome / Edge / Samsung avisan acá cuando la app cumple los
    // requisitos. Al aceptar, el navegador instala y AGREGA EL ICONO en
    // la pantalla de inicio o en el escritorio: es justo el acceso
    // directo que el productor necesita para no volver a escribir la
    // dirección.
    window.addEventListener('beforeinstallprompt', (evento) => {
      evento.preventDefault();
      promptInstalacion = evento;
      mostrarBotonInstalar(true);

      if (!equipoPreparado) {
        pintarEstado(
          'aviso', '!',
          'Instala la app en este dispositivo',
          'Después de instalarla, el ícono SOVCA queda en tu pantalla de inicio.'
        );
      }
    });

    window.addEventListener('appinstalled', () => {
      promptInstalacion = null;
      yaInstalada = true;
      guardarRecordatorioInstalado();
      mostrarBotonInstalar(false);

      pintarEstado(
        'listo', '✓',
        'App instalada en este dispositivo',
        'Busca el ícono SOVCA en tu pantalla de inicio o en el escritorio: desde ahí se abre sin escribir ninguna dirección.'
      );
    });

    // Navegadores sin instalación automática (iPhone/iPad y algunos
    // Android): el acceso se crea a mano. El botón de instrucciones
    // queda como acción principal.
    if (esIos()) {
      mostrarBotonInstalar(false);
    }
  }

  async function manejarInstalar() {
    const boton = el('boton-instalar');
    if (!boton) return;

    if (!promptInstalacion) {
      // Se quedó sin prompt (otra pestaña lo consumió o el navegador ya
      // no lo ofrece): mostramos las instrucciones en vez de un botón
      // que no hace nada.
      abrirComoInstalar();
      return;
    }

    boton.disabled = true;
    try {
      promptInstalacion.prompt();
      const eleccion = await promptInstalacion.userChoice;
      promptInstalacion = null;

      if (eleccion && eleccion.outcome === 'accepted') {
        guardarRecordatorioInstalado();
        pintarEstado(
          'listo', '✓',
          'Instalando…',
          'Cuando termine, el ícono SOVCA aparecerá en tu pantalla de inicio o en el escritorio.'
        );
      } else {
        pintarEstado(
          'aviso', '!',
          'No se instaló todavía',
          'Puedes intentarlo de nuevo o crear el acceso a mano con el botón de abajo.'
        );
      }
    } catch (e) {
      abrirComoInstalar();
    } finally {
      boton.disabled = false;
    }
  }

  function abrirComoInstalar() {
    const panel = el('seccion-como-instalar');
    if (!panel) return;
    panel.classList.remove('oculto');

    // Resaltar el bloque de la plataforma que se está usando.
    const actual = plataforma();
    panel.querySelectorAll('.portada__pasos').forEach((bloque) => {
      if (bloque.getAttribute('data-plataforma') === actual) {
        bloque.setAttribute('data-detectada', 'si');
      } else {
        bloque.removeAttribute('data-detectada');
      }
    });

    panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  // ---------------------------------------------------------------
  // Arranque
  // ---------------------------------------------------------------

  function registrarServiceWorker() {
    if (!('serviceWorker' in navigator)) return;

    // Falla en silencio: si el navegador no deja registrar el SW (HTTP
    // sin TLS, modo privado), la portada y la app siguen funcionando con
    // internet; solo se pierde el uso sin conexión.
    navigator.serviceWorker.register('service-worker.js', { scope: './' }).catch(() => { });
  }

  async function iniciar() {
    registrarServiceWorker();
    pintarConexion();
    window.addEventListener('online', pintarConexion);
    window.addEventListener('offline', pintarConexion);

    configurarInstalacion();

    el('boton-entrar').addEventListener('click', () => {
      window.location.href = 'index.html';
    });
    el('boton-instalar').addEventListener('click', manejarInstalar);
    el('boton-como-instalar').addEventListener('click', abrirComoInstalar);

    const equipo = await revisarEquipo();
    const haySesion = !!(equipo.sesion && equipo.sesion.token);
    equipoPreparado = haySesion || !!equipo.credencial;

    const avisoPreparar = el('seccion-preparar');
    if (avisoPreparar) avisoPreparar.classList.toggle('oculto', equipoPreparado);

    // Uso diario: si la app está instalada y el equipo tiene datos, no
    // hacemos que el productor pase por una portada. Se puede evitar con
    // ?inicio=1 (por ejemplo, un enlace "Página de inicio" dentro de la
    // app o un acceso guardado a propósito).
    const params = new URLSearchParams(window.location.search);
    if (yaInstalada && equipoPreparado && params.get('inicio') !== '1') {
      window.location.replace('index.html');
      return;
    }

    if (equipoPreparado) {
      const usuario = (equipo.sesion && (equipo.sesion.usuario || equipo.sesion.nombre_usuario))
        || (equipo.credencial && equipo.credencial.usuario)
        || '';
      const fundos = equipo.fundos === 1 ? '1 fundo descargado' : (equipo.fundos + ' fundos descargados');

      pintarEstado(
        'listo', '✓',
        'Este equipo está listo para trabajar sin conexión',
        (usuario ? 'Usuario: ' + usuario + '. ' : '') + (equipo.fundos > 0 ? fundos + '.' : '')
      );
    } else if (!yaInstalada && !promptInstalacion) {
      pintarEstado(
        'aviso', '!',
        'Este equipo todavía no está preparado',
        'Actívalo con internet desde el botón «Activar este equipo» y después instala la app.'
      );
    } else if (!yaInstalada) {
      pintarEstado(
        'aviso', '!',
        'Instala la app y prepara el equipo',
        'Primero activa este equipo con el botón de abajo; después instala la app para tener el ícono a mano.'
      );
    } else {
      pintarEstado(
        'aviso', '!',
        'Falta descargar los datos del fundo',
        'Abre la app e inicia sesión una vez con internet para descargar los animales.'
      );
    }

    if (leerRecordatorioInstalado() && !yaInstalada && promptInstalacion) {
      // Ya la instaló alguna vez en este navegador pero los datos del
      // sitio se borraron: se le vuelve a ofrecer instalarla.
      mostrarBotonInstalar(true);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', iniciar);
  } else {
    iniciar();
  }
})();
