// Arranque de la app y manejo de la interfaz.

const el = (id) => document.getElementById(id);
let edicionAnimalUuid = null;
let edicionControlSanitarioUuid = null;
let productosControlSanitario = [];
let edicionControlLoteUuid = null;
let productosControlLote = [];
let edicionProduccionLecheUuid = null;
let edicionPesosUuid = null;
let pesajePorLote = null;
let pesajeGuardando = false;
let edicionServiciosUuid = null;
let edicionNacimientosUuid = null;
let servicioOrigenNacimiento = null;
let focoAnteriorModalNacimiento = null;
let nacimientoParaFicharUuid = null;
let deferredInstallPrompt = null;

function mostrarModalAviso(titulo, contenidoHtml, opciones = {}) {
  const overlay = el('modal-aviso-overlay');
  const tituloEl = el('modal-aviso-titulo');
  const contenidoEl = el('modal-aviso-contenido');
  const boton = el('modal-aviso-aceptar');

  if (!overlay || !tituloEl || !contenidoEl || !boton) {
    alert(titulo + '\n\n' + String(contenidoHtml).replace(/<[^>]+>/g, ''));
    return;
  }

  tituloEl.textContent = titulo;
  contenidoEl.innerHTML = contenidoHtml;
  boton.textContent = opciones.textoBoton || 'Entendido';

  overlay.classList.remove('oculto');
  document.body.classList.add('modal-abierto');

  const cerrar = () => {
    overlay.classList.add('oculto');
    document.body.classList.remove('modal-abierto');
    boton.removeEventListener('click', cerrar);
  };
  boton.addEventListener('click', cerrar);

  try { boton.focus(); } catch (e) { /* noop */ }
}

/* ============================================================
   Avisos breves y protección contra el doble toque
   ============================================================ */

/**
 * Aviso breve (toast) abajo de la pantalla. A diferencia de
 * mostrarModalAviso() no bloquea: sirve para confirmar "guardado" sin
 * obligar al operador a tocar "Aceptar" después de cada registro.
 */
function mostrarToast(mensaje, tipo = 'ok') {
  const contenedor = el('toast-contenedor');
  if (!contenedor || !mensaje) return;

  const toast = document.createElement('div');
  toast.className = 'toast toast--' + tipo;
  toast.textContent = mensaje;
  contenedor.appendChild(toast);

  requestAnimationFrame(() => toast.classList.add('toast--visible'));
  setTimeout(() => {
    toast.classList.remove('toast--visible');
    setTimeout(() => toast.remove(), 240);
  }, 2800);
}

/**
 * Envuelve el manejador de "Guardar" de un formulario para que un doble
 * toque (o un doble Enter) no cree dos registros: mientras el guardado
 * está en curso el formulario queda marcado y sus botones de envío
 * deshabilitados.
 */
function conGuardiaDeEnvio(idFormulario, manejador) {
  return async function (evento) {
    const formulario = (evento && evento.currentTarget) || el(idFormulario);
    if (!formulario) return manejador(evento);

    if (formulario.dataset.guardando === '1') {
      if (evento) evento.preventDefault();
      return;
    }

    formulario.dataset.guardando = '1';
    formulario.classList.add('formulario--guardando');
    const botones = Array.from(formulario.querySelectorAll('button[type="submit"]'));
    botones.forEach((boton) => { boton.disabled = true; });

    try {
      await manejador(evento);
    } catch (error) {
      console.error('No se pudo guardar (' + idFormulario + '):', error);
      const salida = el(idFormulario + '-mensaje');
      if (salida) {
        salida.textContent = (error && error.message)
          ? error.message
          : 'No se pudo guardar el registro. Intenta de nuevo.';
      }
    } finally {
      formulario.dataset.guardando = '';
      formulario.classList.remove('formulario--guardando');
      botones.forEach((boton) => { boton.disabled = false; });
    }
  };
}

/* ============================================================
   Última sincronización y aviso al salir con registros pendientes
   ============================================================ */

let hayPendientesLocales = false;
let revisandoPendientesSalida = false;
let ultimoConteoPendientes = 0;

/** "hace 4 min", "ayer"... para la tarjeta de sincronización. */
function tiempoRelativo(fecha) {
  if (!(fecha instanceof Date) || Number.isNaN(fecha.getTime())) return '';
  const ms = Date.now() - fecha.getTime();
  if (ms < 0) return 'ahora mismo';

  const minutos = Math.floor(ms / 60000);
  if (minutos < 1) return 'hace instantes';
  if (minutos === 1) return 'hace 1 min';
  if (minutos < 60) return 'hace ' + minutos + ' min';

  const horas = Math.floor(minutos / 60);
  if (horas === 1) return 'hace 1 hora';
  if (horas < 24) return 'hace ' + horas + ' h';

  const dias = Math.floor(horas / 24);
  if (dias === 1) return 'ayer';
  if (dias < 8) return 'hace ' + dias + ' días';

  return formatearFecha(fecha.toISOString());
}

/**
 * Datos del estado de sincronización del fundo activo: cuántos
 * registros faltan por subir, cuándo fue el último envío y qué tan
 * viejo es ese dato. Lo usan el chip del encabezado y su detalle.
 */
async function calcularEstadoSync(pendientes) {
  // El intervalo de 1 minuto llama sin argumento: se reutiliza el
  // último conteo conocido para no "resetear" los pendientes a cero.
  const total = typeof pendientes === 'number' ? pendientes : ultimoConteoPendientes;
  ultimoConteoPendientes = total;

  let fundos = [];
  try {
    fundos = await Db.listarFundosMeta();
  } catch (e) {
    fundos = [];
  }

  const activo = Number(Db.fundoActivo()) || 0;
  const fundo = fundos.find((f) => Number(f.id) === activo) || fundos[0];
  const ultima = fundo && fundo.ultima_sync ? new Date(fundo.ultima_sync) : null;
  const valida = ultima && !Number.isNaN(ultima.getTime()) ? ultima : null;
  const minutos = valida ? (Date.now() - valida.getTime()) / 60000 : Infinity;

  // Con registros por subir el equipo NO está al día aunque la última
  // sincronización haya sido hace un minuto: eso lo deja en ámbar.
  const estado = !valida ? 'error'
    : total > 0 ? 'aviso'
      : minutos < 60 ? 'ok'
        : minutos < 24 * 60 ? 'aviso' : 'error';

  return { total, valida, estado, fundo: (fundo && fundo.nombre) || '' };
}

/**
 * Chip de sincronización del encabezado, al lado del nombre del
 * usuario. Antes esta información ocupaba una tarjeta entera debajo
 * del encabezado; ahora vive acá usando solo el ancho que necesita el
 * texto, y el detalle completo se abre al tocarlo (ver
 * mostrarDetalleSincronizacion).
 */
async function actualizarIndicadorSincronizacion(pendientes) {
  const chip = el('boton-estado-sync');
  if (!chip) return;

  const datos = await calcularEstadoSync(pendientes);
  const total = datos.total;
  const valida = datos.valida;

  const claseEstado = 'chip-sync--' + datos.estado;
  if (!chip.classList.contains(claseEstado)) {
    chip.classList.remove('chip-sync--ok', 'chip-sync--aviso', 'chip-sync--error');
    chip.classList.add(claseEstado);
  }

  const icono = el('chip-sync-icono');
  const texto = el('chip-sync-texto');

  if (icono) {
    const iconoNuevo = !valida ? '?' : (datos.estado === 'ok' ? '✓' : '!');
    if (icono.textContent !== iconoNuevo) icono.textContent = iconoNuevo;
  }

  // Al día el chip queda en "Todo sincronizado" (el tiempo exacto vive
  // en la burbuja de detalle); si hay algo que hacer, se dice qué.
  const textoNuevo = total > 0
    ? (total === 1 ? '1 por subir' : total + ' por subir')
    : (!valida ? 'Sin sincronizar'
      : (datos.estado === 'ok' ? 'Todo sincronizado' : tiempoRelativo(valida)));

  if (texto && texto.textContent !== textoNuevo) {
    // Cambio de texto con un desteñido corto en vez de un salto seco.
    clearTimeout(temporizadorTextoChipSync);
    texto.classList.add('chip-sync__texto--cambio');
    temporizadorTextoChipSync = setTimeout(() => {
      texto.textContent = textoNuevo;
      texto.classList.remove('chip-sync__texto--cambio');
    }, 150);
  }

  const tituloNuevo = total > 0
    ? total + ' registro(s) guardado(s) en el equipo sin subir al servidor. Toca para ver el detalle.'
    : (valida
      ? 'Última sincronización: ' + valida.toLocaleString() + '. Toca para ver el detalle.'
      : 'Este equipo todavía no sincronizó este fundo con el servidor.');
  if (chip.title !== tituloNuevo) chip.title = tituloNuevo;
}

/* ----------------------------------------------------------------
   Burbuja de detalle del chip (anclada al chip, no un modal
   centrado: esto es información, no una alerta grave).
   ---------------------------------------------------------------- */

let popoverSyncAbierto = false;
let temporizadorPopoverSync = null;
let temporizadorTextoChipSync = null;

const NOMBRES_MODULO_SINGULAR = {
  animales: 'Animal',
  control_sanitario: 'Control sanitario',
  control_sanitario_lotes: 'Control por lote',
  desincorporacion: 'Baja',
  movimiento_rebano: 'Movimiento de rebaño',
  produccion_leche: 'Producción de leche',
  pesos: 'Pesaje',
  servicios: 'Servicio',
  nacimientos: 'Nacimiento',
};

const NOMBRES_MODULO_PLURAL = {
  animales: 'Animales',
  control_sanitario: 'Controles sanitarios',
  control_sanitario_lotes: 'Controles por lote',
  desincorporacion: 'Bajas',
  movimiento_rebano: 'Movimientos de rebaño',
  produccion_leche: 'Producciones de leche',
  pesos: 'Pesajes',
  servicios: 'Servicios',
  nacimientos: 'Nacimientos',
};

/** Lista "3 Pesajes / 1 Animal" a partir de los registros del outbox. */
function construirListaPendientes(outbox) {
  const porModulo = {};
  for (const item of outbox) {
    const modulo = item.modulo || 'animales';
    porModulo[modulo] = (porModulo[modulo] || 0) + 1;
  }

  return Object.entries(porModulo)
    .sort((a, b) => b[1] - a[1])
    .map(([modulo, cantidad]) => {
      const etiqueta = cantidad === 1
        ? (NOMBRES_MODULO_SINGULAR[modulo] || modulo)
        : (NOMBRES_MODULO_PLURAL[modulo] || modulo);
      return '<div class="popover-sync__fila">'
        + '<span class="popover-sync__cantidad">' + cantidad + '</span>'
        + '<span>' + escaparHtml(etiqueta) + '</span>'
        + '</div>';
    }).join('');
}

/** Coloca la burbuja debajo del chip (o encima si no cabe). */
function posicionarPopoverSync() {
  const popover = el('popover-sync');
  const chip = el('boton-estado-sync');
  if (!popover || !chip) return;

  const margen = 10;
  const ancho = Math.min(300, window.innerWidth - margen * 2);
  popover.style.width = ancho + 'px';

  const caja = chip.getBoundingClientRect();
  const alto = popover.offsetHeight;

  let izquierda = caja.left + caja.width / 2 - ancho / 2;
  izquierda = Math.max(margen, Math.min(izquierda, window.innerWidth - ancho - margen));

  let arriba = caja.bottom + 10;
  const vaArriba = arriba + alto > window.innerHeight - margen;
  if (vaArriba) arriba = Math.max(margen, caja.top - alto - 10);

  popover.classList.toggle('popover-sync--arriba', vaArriba);
  popover.style.left = Math.round(izquierda) + 'px';
  popover.style.top = Math.round(arriba) + 'px';

  const flecha = el('popover-sync-flecha');
  if (flecha) {
    const centro = caja.left + caja.width / 2 - izquierda;
    flecha.style.left = (Math.round(Math.max(16, Math.min(centro, ancho - 16))) - 6) + 'px';
  }
}

function abrirPopoverSync() {
  const popover = el('popover-sync');
  const chip = el('boton-estado-sync');
  if (!popover || !chip) return;

  clearTimeout(temporizadorPopoverSync);
  popover.classList.remove('oculto', 'popover-sync--visible');
  posicionarPopoverSync();
  void popover.offsetWidth; // fuerza el reflow para que la transición arranque
  popover.classList.add('popover-sync--visible');

  chip.setAttribute('aria-expanded', 'true');
  popoverSyncAbierto = true;

  document.addEventListener('pointerdown', manejarClicFueraPopoverSync, true);
  document.addEventListener('keydown', manejarTeclaPopoverSync);
  window.addEventListener('scroll', cerrarPopoverSync, { passive: true, once: true });
  window.addEventListener('resize', cerrarPopoverSync, { once: true });
}

/**
 * Cierra la burbuja y le da al chip un destello corto: después de
 * leer el detalle, la etiqueta se "desteñe" en vez de quedarse
 * quieta como si nada hubiera pasado.
 */
function cerrarPopoverSync() {
  if (!popoverSyncAbierto) return;
  popoverSyncAbierto = false;

  const popover = el('popover-sync');
  const chip = el('boton-estado-sync');

  if (popover) popover.classList.remove('popover-sync--visible');
  if (chip) {
    chip.setAttribute('aria-expanded', 'false');
    chip.classList.remove('chip-sync--destello');
    void chip.offsetWidth;
    chip.classList.add('chip-sync--destello');
    chip.addEventListener('animationend', () => chip.classList.remove('chip-sync--destello'), { once: true });
  }

  clearTimeout(temporizadorPopoverSync);
  temporizadorPopoverSync = setTimeout(() => {
    if (popover) popover.classList.add('oculto');
  }, 200);

  document.removeEventListener('pointerdown', manejarClicFueraPopoverSync, true);
  document.removeEventListener('keydown', manejarTeclaPopoverSync);
}

function manejarClicFueraPopoverSync(evento) {
  const popover = el('popover-sync');
  const chip = el('boton-estado-sync');
  if (popover && popover.contains(evento.target)) return;
  if (chip && chip.contains(evento.target)) return; // el chip maneja su propio cerrar
  cerrarPopoverSync();
}

function manejarTeclaPopoverSync(evento) {
  if (evento.key === 'Escape') cerrarPopoverSync();
}

/**
 * Arma el contenido de la burbuja según el estado y la abre.
 */
async function mostrarDetalleSincronizacion() {
  const popover = el('popover-sync');
  if (!popover) return;

  const datos = await calcularEstadoSync();
  let outbox = [];
  let conflictos = [];
  try {
    outbox = await Db.obtenerTodos('outbox');
    conflictos = await Db.obtenerTodos('conflictos');
  } catch (e) {
    outbox = [];
    conflictos = [];
  }

  const titulo = el('popover-sync-titulo');
  const cuerpo = el('popover-sync-cuerpo');
  const html = [];

  if (!datos.valida) {
    if (titulo) titulo.textContent = 'Sin sincronizar';
    html.push('<p class="popover-sync__texto">Este equipo todavía no sincronizó este fundo con el servidor.</p>');
    html.push('<p class="popover-sync__texto">Conectate y toca <strong>Sincronizar</strong> para bajar los datos y dejarlo listo para trabajar sin señal.</p>');
  } else {
    if (titulo) {
      titulo.textContent = datos.total > 0
        ? 'Registros pendientes de subir'
        : (datos.estado === 'ok' ? 'Todo sincronizado' : 'Sincronización atrasada');
    }
    html.push('<p class="popover-sync__texto">Última sincronización <strong>'
      + escaparHtml(tiempoRelativo(datos.valida)) + '</strong> · '
      + escaparHtml(formatearFechaHora(datos.valida)) + '.</p>');
  }

  if (datos.total > 0) {
    html.push('<p class="popover-sync__texto">Guardados en este equipo, todavía no están en el servidor:</p>');
    html.push('<div class="popover-sync__lista">' + construirListaPendientes(outbox) + '</div>');
    if (conflictos.length) {
      html.push('<p class="popover-sync__aviso"><strong>' + conflictos.length + ' conflicto'
        + (conflictos.length === 1 ? '' : 's') + '</strong> necesitan tu decisión. Resolvelos en la pestaña <strong>Animales</strong>.</p>');
    }
    html.push('<p class="popover-sync__nota">' + (navigator.onLine
      ? 'Se envían solos en la próxima sincronización (modo <strong>gestión</strong>).'
      : 'Se enviarán solos cuando vuelva la señal.') + '</p>');
  } else if (datos.valida) {
    html.push('<p class="popover-sync__nota">' + (datos.estado === 'ok'
      ? 'No hay registros esperando.'
      : 'No hay registros esperando, pero conviene sincronizar cuando haya señal.') + '</p>');
  }

  if (datos.fundo) {
    html.push('<p class="popover-sync__nota">Fundo: <strong>' + escaparHtml(datos.fundo) + '</strong></p>');
  }

  html.push(
    '<div class="popover-sync__respaldo">'
    + '<p class="popover-sync__subtitulo">Respaldo del equipo</p>'
    + '<p class="popover-sync__nota">Descargá una copia de los datos guardados en este equipo o recuperala en otro.</p>'
    + '<div class="popover-sync__acciones">'
    + '<button id="boton-respaldo-descargar" type="button" class="popover-sync__boton popover-sync__boton--secundario">Descargar respaldo</button>'
    + '<button id="boton-respaldo-restaurar" type="button" class="popover-sync__boton popover-sync__boton--secundario">Restaurar respaldo</button>'
    + '</div>'
    + '<input id="input-respaldo-archivo" type="file" accept="application/json,.json" class="oculto">'
    + '</div>'
  );

  if (cuerpo) cuerpo.innerHTML = html.join('');

  adjuntarAccionesRespaldo();

  abrirPopoverSync();
}

/* ---------------------------------------------------------------- */
/* Respaldo y restauración local                                     */
/* ---------------------------------------------------------------- */

// Almacenes que viajan en el respaldo: los datos de campo, lo pendiente
// de subir, los conflictos abiertos, los rangos de códigos reservados y
// los cursores de sincronización (para que el equipo restaurado sepa
// desde dónde seguir bajando cambios).
const ALMACENES_RESPALDO = [
  'animales', 'control_sanitario', 'control_sanitario_lotes', 'desincorporacion',
  'movimiento_rebano', 'produccion_leche', 'pesos', 'servicios', 'nacimientos',
  'outbox', 'conflictos', 'rangos',
];

function nombreArchivoRespaldo(fundoId) {
  const hoy = new Date();
  const sello = hoy.getFullYear()
    + String(hoy.getMonth() + 1).padStart(2, '0')
    + String(hoy.getDate()).padStart(2, '0')
    + '-' + String(hoy.getHours()).padStart(2, '0') + String(hoy.getMinutes()).padStart(2, '0');
  return 'sovca-respaldo-f' + fundoId + '-' + sello + '.json';
}

async function descargarRespaldoLocal() {
  const fundoId = Db.fundoActivo();
  if (!fundoId) {
    alert('No hay un fundo activo para respaldar.');
    return;
  }

  const almacenes = {};
  let total = 0;
  for (const almacen of ALMACENES_RESPALDO) {
    const filas = await Db.obtenerTodos(almacen).catch(() => []);
    almacenes[almacen] = filas;
    total += filas.length;
  }

  const sesion = await Db.obtenerSesion().catch(() => null);
  const etiquetaFundo = el('fundo-activo');
  const respaldo = {
    app: 'BOVS Offline',
    formato: 1,
    generado: new Date().toISOString(),
    fundo_id: Number(fundoId),
    fundo_nombre: etiquetaFundo ? etiquetaFundo.textContent.trim() : '',
    usuario: sesion && sesion.usuario ? sesion.usuario : '',
    total_registros: total,
    almacenes,
  };

  try {
    const blob = new Blob([JSON.stringify(respaldo)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const enlace = document.createElement('a');
    enlace.href = url;
    enlace.download = nombreArchivoRespaldo(fundoId);
    document.body.appendChild(enlace);
    enlace.click();
    document.body.removeChild(enlace);
    setTimeout(() => URL.revokeObjectURL(url), 4000);
  } catch (e) {
    alert('No se pudo generar el archivo de respaldo: ' + (e && e.message ? e.message : 'error'));
    return;
  }

  alert(
    'Respaldo descargado con ' + total + ' registro(s).\n\n'
    + 'Guardalo en un lugar seguro (correo, nube o computadora).'
  );
}

async function restaurarRespaldoLocal(archivo) {
  if (!archivo) return;

  let contenido = null;
  try {
    contenido = JSON.parse(await archivo.text());
  } catch (e) {
    alert('Ese archivo no es un respaldo válido de BOVS.');
    return;
  }

  if (!contenido || contenido.app !== 'BOVS Offline' || !contenido.almacenes) {
    alert('Ese archivo no es un respaldo válido de BOVS.');
    return;
  }

  const fundoId = Db.fundoActivo();
  if (Number(contenido.fundo_id) !== Number(fundoId)) {
    alert(
      'Este respaldo es del fundo #' + contenido.fundo_id
      + (contenido.fundo_nombre ? ' (' + contenido.fundo_nombre + ')' : '')
      + ', pero el fundo activo es #' + fundoId + '.\n\n'
      + 'Cambiá al fundo correcto antes de restaurar.'
    );
    return;
  }

  const total = Number(contenido.total_registros) || 0;
  const confirmado = confirm(
    '¿Restaurar el respaldo del ' + formatearFechaHora(contenido.generado) + '?\n\n'
    + 'Tiene ' + total + ' registro(s) y REEMPLAZA los datos de este fundo en el equipo.'
  );
  if (!confirmado) return;

  try {
    for (const almacen of ALMACENES_RESPALDO) {
      if (!Array.isArray(contenido.almacenes[almacen])) continue;
      await Db.vaciar(almacen);
      const filas = contenido.almacenes[almacen];
      if (filas.length) await Db.guardarVarios(almacen, filas);
    }
  } catch (e) {
    alert('La restauración se cortó a medias: ' + (e && e.message ? e.message : 'error'));
    return;
  }

  alert('Respaldo restaurado. Se vuelven a leer los datos del equipo.');

  await renderizarLista();
  await renderizarListaControlSanitario();
  await renderizarListaControlLote();
  await renderizarListaDesincorporacion();
  await renderizarListaMovimientoRebano();
  await renderizarListaProduccionLeche();
  await renderizarListaPesos();
  await renderizarListaServicios();
  await renderizarListaNacimientos();
  await renderizarConflictos();
  await actualizarContadorPendientes();

  await mostrarDetalleSincronizacion();
}

/** Engancha los botones de respaldo del popover (se rehace en cada apertura). */
function adjuntarAccionesRespaldo() {
  const descargar = el('boton-respaldo-descargar');
  const restaurar = el('boton-respaldo-restaurar');
  const archivo = el('input-respaldo-archivo');

  if (descargar) {
    descargar.addEventListener('click', () => { descargarRespaldoLocal(); });
  }
  if (restaurar && archivo) {
    restaurar.addEventListener('click', () => archivo.click());
    archivo.addEventListener('change', async () => {
      const seleccionado = archivo.files && archivo.files[0] ? archivo.files[0] : null;
      archivo.value = '';
      await restaurarRespaldoLocal(seleccionado);
    });
  }
}

/**
 * Recalcula si queda algo sin subir en CUALQUIER fundo del equipo. Se
 * guarda en una variable simple porque el navegador no deja hacer
 * consultas asíncronas dentro de "beforeunload".
 */
async function refrescarPendientesParaSalida() {
  if (revisandoPendientesSalida) return;
  revisandoPendientesSalida = true;
  try {
    hayPendientesLocales = await Db.hayPendientesEnAlgunFundo();
  } catch (e) {
    // Si no se puede comprobar, no se bloquea la salida.
  } finally {
    revisandoPendientesSalida = false;
  }
}

/**
 * Evita cerrar la app (o recargar la pestaña) con registros de campo
 * todavía sin subir. El texto lo pone el navegador; solo podemos
 * impedir el cierre silencioso.
 */
function configurarAvisoSalidaConPendientes() {
  window.addEventListener('beforeunload', (evento) => {
    if (!hayPendientesLocales) return;
    evento.preventDefault();
    evento.returnValue = '';
    return '';
  });

  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden') refrescarPendientesParaSalida();
  });
  window.addEventListener('pagehide', refrescarPendientesParaSalida);
}

function preguntarModoOffline(usuarioInfo) {
  return new Promise((resolve) => {
    const overlay = el('modal-eleccion-modo-overlay');
    const usuarioEl = el('modal-eleccion-usuario');

    if (!overlay) {
      resolve('solo_online');
      return;
    }

    if (usuarioEl) {
      usuarioEl.textContent = (usuarioInfo && (usuarioInfo.nombre || usuarioInfo.username)) || 'este usuario';
    }

    const botones = overlay.querySelectorAll('[data-modo]');
    botones.forEach((boton) => {
      const nuevo = boton.cloneNode(true);
      boton.parentNode.replaceChild(nuevo, boton);
    });

    overlay.querySelectorAll('[data-modo]').forEach((boton) => {
      boton.addEventListener('click', () => {
        const modo = boton.dataset.modo;
        overlay.classList.add('oculto');
        document.body.classList.remove('modal-abierto');
        resolve(modo);
      });
    });

    overlay.classList.remove('oculto');
    document.body.classList.add('modal-abierto');
  });
}

function aplicarModoConsultaSiAplica(sesion) {
  const modo = sesion && sesion.modo_offline;
  const esConsulta = modo === 'consulta';
  document.body.classList.toggle('modo-consulta', esConsulta);

  const aviso = el('aviso-modo-consulta');
  if (aviso) {
    aviso.classList.toggle('oculto', !esConsulta);
  }

  document.querySelectorAll(
    '.boton-registrar, .chapeta__boton-editar, button[id^="boton-mostrar-form"]'
  ).forEach((boton) => {
    boton.style.display = esConsulta ? 'none' : '';
  });

  if (aviso && esConsulta) {
    aviso.innerHTML =
      '<strong>Modo CONSULTA activo.</strong> Estás viendo los datos en modo solo lectura. '
      + 'Para registrar, editar o sincronizar cambios, cambiá este dispositivo a modo <strong>gestión</strong> '
      + 'desde el sistema web (MAESTROS → Dispositivos Offline) o desde la pantalla de reconexión.';
  }
}

async function iniciar() {
  registrarServiceWorker();
  configurarInstalacionComoApp();
  cargarVersionFrontend();

  const sesion = await Db.obtenerSesion();
  if (sesion && sesion.sesion_local && await sesionLocalVencida(sesion)) {
    await Db.cerrarSesion();
    await mostrarPantallaInicialSegunEquipo();
    const destino = el('reconexion-mensaje') || el('login-mensaje');
    if (destino) {
      destino.textContent = 'Tu acceso sin conexión venció por inactividad. Conéctate a internet e inicia sesión para seguir usando la app.';
    }
  } else if (sesion) {
    mostrarPantallaPrincipal(sesion);
  } else {
    const params = new URLSearchParams(window.location.search);
    const preloadToken = params.get('preload_token');
    const preloadUser = params.get('preload_user');

    if (preloadToken && preloadUser) {
      window.history.replaceState({}, '', window.location.pathname);
      await manejarPrecargaDesdeLogin(preloadToken, preloadUser);
    } else {
      await mostrarPantallaInicialSegunEquipo();
    }
  }

  window.addEventListener('online', actualizarIndicadorConexion);
  window.addEventListener('offline', actualizarIndicadorConexion);
  window.addEventListener('bovs:sync', (e) => manejarEventoSync(e.detail));
  window.addEventListener('bovs:sesion_invalida', (e) => manejarSesionInvalida(e.detail && e.detail.mensaje));
  window.addEventListener('bovs:dispositivo_desvinculado', (e) => manejarDispositivoDesvinculado(e.detail && e.detail.mensaje));
  window.addEventListener('bovs:sin_acceso_offline', (e) => manejarSinAccesoOffline(e.detail && e.detail.mensaje));
  window.addEventListener('bovs:catalogos', async () => {
    await poblarSelectsCatalogos();
    await poblarSelectProductosControlSanitario();
    FORMULARIOS_MODULOS.forEach((f) => {
      const contenedor = el(f.idContenedor);
      if (contenedor && !contenedor.classList.contains('oculto')) {
        snapshotsFormulario[f.idForm] = capturarSnapshotFormulario(f.idForm);
      }
    });
  });

  actualizarIndicadorConexion();
  if (sesion && sesion.token && navigator.onLine) Sync.sincronizar();
}

function esMovilOTablet() {
  const ua = navigator.userAgent || '';
  const esMovilUA = /Android|iPhone|iPad|iPod|Mobile|Tablet/i.test(ua);
  const touchMac = navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1;
  return esMovilUA || touchMac;
}

function esWindowsDesktop() {
  const ua = navigator.userAgent || '';
  const plataforma = navigator.platform || '';
  return /Windows/i.test(ua) || /Win/i.test(plataforma);
}

function yaInstaladaComoApp() {
  return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
}

function esIosSafari() {
  const ua = navigator.userAgent || '';
  const esIos = /iPhone|iPad|iPod/i.test(ua);
  const esSafari = /Safari/i.test(ua) && !/CriOS|FxiOS|EdgiOS/i.test(ua);
  return esIos && esSafari;
}

function origenPwaSeguro() {
  return window.isSecureContext || location.hostname === 'localhost' || location.hostname === '127.0.0.1';
}

function mostrarCajaInstalacion(texto, mostrarBotonInstalar) {
  const box = el('instalacion-app-box');
  const textoEl = el('instalacion-app-texto');
  const botonInstalar = el('boton-instalar-app');
  if (!box || !textoEl || !botonInstalar) return;

  textoEl.textContent = texto;
  botonInstalar.classList.toggle('oculto', !mostrarBotonInstalar);
  box.classList.remove('oculto');
}

function ocultarCajaInstalacion() {
  const box = el('instalacion-app-box');
  if (box) box.classList.add('oculto');
}

function configurarInstalacionComoApp() {
  const dispositivoObjetivo = esMovilOTablet() || esWindowsDesktop();
  if (!dispositivoObjetivo || yaInstaladaComoApp()) {
    ocultarCajaInstalacion();
    return;
  }

  if (!origenPwaSeguro()) {
    mostrarCajaInstalacion('Instalación como app no disponible en HTTP no seguro. Puedes seguir en navegador y cargar datos con el botón "Cargar datos web".', false);
    return;
  }

  window.addEventListener('beforeinstallprompt', (evento) => {
    evento.preventDefault();
    deferredInstallPrompt = evento;
    mostrarCajaInstalacion('Instala BOVS Offline como app en este dispositivo para mejor experiencia sin conexión.', true);
  });

  window.addEventListener('appinstalled', () => {
    deferredInstallPrompt = null;
    ocultarCajaInstalacion();
  });

  if (esIosSafari()) {
    mostrarCajaInstalacion('En iPhone/iPad: toca Compartir y luego Agregar a pantalla de inicio. Si no instalas, puedes operar en navegador.', false);
  } else if (esWindowsDesktop()) {
    mostrarCajaInstalacion('En Windows (Edge/Chrome) puedes instalar como app. Si no instalas, sigue en navegador con "Cargar datos web".', true);
  } else {
    mostrarCajaInstalacion('Si tu navegador lo permite, instala esta app para usarla como aplicación nativa. Si no, sigue en navegador.', true);
  }
}

async function manejarInstalarApp() {
  if (!deferredInstallPrompt) {
    if (esIosSafari()) {
      mostrarCajaInstalacion('En iPhone/iPad: toca Compartir y luego Agregar a pantalla de inicio.', false);
    }
    return;
  }

  deferredInstallPrompt.prompt();
  try {
    await deferredInstallPrompt.userChoice;
  } finally {
    deferredInstallPrompt = null;
  }
}

function actualizarVisibilidadBotonDatosWeb() {
  const boton = el('boton-cargar-datos-web');
  if (!boton) return;
  boton.classList.toggle('oculto', yaInstaladaComoApp());
}

async function manejarCargarDatosNavegador() {
  const estadoEl = el('estado-sync');
  const sesion = await Db.obtenerSesion();

  if (!sesion) {
    if (estadoEl) estadoEl.textContent = 'Primero inicia sesión para cargar datos.';
    return;
  }
  if (!navigator.onLine) {
    if (estadoEl) estadoEl.textContent = 'Sin conexión. Conéctate para cargar datos en el navegador.';
    return;
  }
  if (!sesion.token) {
    if (estadoEl) estadoEl.textContent = 'Tu sesión es local. Inicia sesión en línea para cargar datos del servidor.';
    return;
  }

  if (estadoEl) estadoEl.textContent = 'Cargando datos en navegador...';

  let traidos = 0;
  const fallas = [];

  try {
    await Sync.actualizarCatalogos(sesion);
  } catch (e) {
    fallas.push('catalogos');
  }

  for (const modulo of CONFIG.MODULOS) {
    try {
      const r = await Sync.traerCambios(sesion, modulo);
      traidos += r.traidos;
    } catch (e) {
      fallas.push(modulo);
    }
  }

  await poblarSelectsCatalogos();
  await renderizarLista();
  await renderizarListaControlSanitario();
  await renderizarListaControlLote();
  await renderizarListaDesincorporacion();
  await renderizarListaMovimientoRebano();
  await renderizarListaProduccionLeche();
  await renderizarListaPesos();
  await renderizarListaServicios();
  await renderizarListaNacimientos();
  await renderizarListaReportes();
  await renderizarConflictos();
  await actualizarContadorPendientes();

  if (!estadoEl) return;
  if (fallas.length) {
    estadoEl.textContent = 'Carga parcial en navegador: ' + traidos + ' registro(s). Falló: ' + fallas.join(', ') + '.';
  } else {
    estadoEl.textContent = 'Datos cargados en navegador: ' + traidos + ' registro(s).';
  }
}

/** Hay algo a medio hacer en pantalla (formulario abierto o modal). */
function hayTrabajoEnPantalla() {
  const formularioVisible = Object.keys(ANCLAS_FORMULARIOS).some((idForm) => {
    const form = el(idForm);
    return !!form && !form.classList.contains('oculto');
  });
  if (formularioVisible) return true;

  return !!document.querySelector('.modal-aviso-overlay:not(.oculto), .modal-nacimiento-overlay:not(.oculto)');
}

function registrarServiceWorker() {
  if (!('serviceWorker' in navigator)) return;

  navigator.serviceWorker.register('service-worker.js', { scope: './' })
    .then((registro) => {
      if (registro.waiting) {
        registro.waiting.postMessage({ type: 'SKIP_WAITING' });
      }

      registro.addEventListener('updatefound', () => {
        const trabajador = registro.installing;
        if (!trabajador) return;

        trabajador.addEventListener('statechange', () => {
          if (trabajador.state !== 'activated') return;

          // Solo se recarga cuando hay una versión nueva instalada, y
          // nunca a mitad de un formulario o de un modal: se perdería lo
          // capturado. En ese caso la versión nueva entra al reabrir.
          if (hayTrabajoEnPantalla()) return;

          window.location.reload();
        });
      });

      if (navigator.serviceWorker.controller) {
        registro.update().catch(() => {});
      }
    })
    .catch((e) => {
      console.warn('No se pudo registrar el service worker:', e);
    });
}

async function sesionLocalVencida(sesion) {
  if (!sesion || !sesion.sesion_local || !sesion.usuario_login) return false;
  const vigencia = await CredencialLocal.vigenciaDe(sesion.usuario_login);
  return vigencia.vencida;
}

async function mostrarPantallaInicialSegunEquipo() {
  const credencial = await Db.algunaCredencialOffline();
  if (credencial && CredencialLocal.disponible()) {
    mostrarPantallaReconexionLocal(credencial);
  } else {
    mostrarPantallaLogin();
    if (credencial && !CredencialLocal.disponible()) {
      el('login-mensaje').textContent = 'Este navegador no soporta reconexión offline segura (WebCrypto). Inicia sesión en línea.';
    }
  }
}

function mostrarPantallaLogin() {
  el('pantalla-login').classList.remove('oculto');
  el('pantalla-reconexion-local').classList.add('oculto');
  el('pantalla-principal').classList.add('oculto');
  document.body.classList.remove('modo-consulta');
}

function mostrarPantallaReconexionLocal(credencial) {
  el('pantalla-login').classList.add('oculto');
  el('pantalla-reconexion-local').classList.remove('oculto');
  el('pantalla-principal').classList.add('oculto');

  const nombreEl = el('reconexion-usuario-nombre');
  if (nombreEl) {
    nombreEl.textContent = (credencial.usuarioInfo && credencial.usuarioInfo.nombre) || credencial.usuario;
  }

  const usuarioEl = el('reconexion-usuario');
  if (usuarioEl) {
    usuarioEl.value = credencial.usuario;
  }

  const passwordEl = el('reconexion-password');
  if (passwordEl) {
    passwordEl.value = '';
  }

  const mensajeEl = el('reconexion-mensaje');
  if (mensajeEl) {
    mensajeEl.textContent = '';
  }
}

function nombreDispositivo() {
  return Dispositivo.obtenerNombre();
}

async function manejarLogin(evento) {
  evento.preventDefault();
  const usuario = el('login-usuario').value.trim();
  const password = el('login-password').value;
  const mensajeEl = el('login-mensaje');
  mensajeEl.textContent = '';

  if (!navigator.onLine) {
    const credencial = await Db.obtenerCredencialOffline(usuario);
    if (credencial) {
      mostrarPantallaReconexionLocal(credencial);
      return;
    }
    mensajeEl.textContent = 'Necesitas conexión a internet para iniciar sesión la primera vez en este equipo.';
    return;
  }

  try {
    el('login-boton').disabled = true;
    const resp = await Api.login(usuario, password, nombreDispositivo(), Dispositivo.obtenerId());
    await establecerSesionYActivarOffline(resp.token, resp.usuario, usuario, password);
  } catch (e) {
    mensajeEl.textContent = e.message === 'sin_conexion'
      ? 'No hay conexión con el servidor. Intenta de nuevo.'
      : e.message;
  } finally {
    el('login-boton').disabled = false;
  }
}

async function establecerSesionYActivarOffline(token, usuarioInfo, usuario, password) {
  const dispositivo = nombreDispositivo();

  await Db.guardarSesion({
    token,
    usuario: usuarioInfo,
    usuario_login: usuario,
    fundo_id: usuarioInfo.fundo_id,
    fundo_activo: usuarioInfo.fundo_id,
    fundos: fundosDeUsuario(usuarioInfo),
    dispositivo,
    sesion_local: false,
    modo_offline: null,
  });

  const credencialExistente = await Db.obtenerCredencialOffline(usuario);

  if (credencialExistente) {
    const modoGuardado = credencialExistente.modoOffline || 'gestion';
    try {
      await CredencialLocal.activar(
        usuario, password, usuarioInfo, dispositivo,
        token, Dispositivo.obtenerId(), modoGuardado,
        fundosDeUsuario(usuarioInfo).map((f) => f.id)
      );
      // Se dejan descargados los fundos que ya estaban habilitados.
      for (const fundo of fundosDeUsuario(usuarioInfo)) {
        await Db.descargarFundo(fundo);
      }
      const sesion = await Db.obtenerSesion();
      sesion.modo_offline = modoGuardado;
      await Db.guardarSesion(sesion);
    } catch (e) {
      if (e && e.code === 'equipo_ocupado') {
        mostrarAvisoEquipoOcupado(e.usuarioExistente);
      } else if (e && e.code === 'dispositivo_gestion_ya_vinculado') {
        mostrarAvisoDispositivoYaVinculado(e.otroDispositivo);
      } else if (e && e.code === 'crypto_no_disponible') {
        console.warn('No se pudo activar reconexion offline:', e.message);
      } else {
        console.warn('Error activando credencial existente:', e);
      }
    }
    const sesion = await Db.obtenerSesion();
    mostrarPantallaPrincipal(sesion);
    Sync.sincronizar();
    return;
  }

  let modoElegido = 'solo_online';
  try {
    modoElegido = await preguntarModoOffline(usuarioInfo);
  } catch (e) {
    console.warn('Error en modal de elección:', e);
  }

  if (modoElegido === 'solo_online') {
    const sesion = await Db.obtenerSesion();
    sesion.modo_offline = null;
    await Db.guardarSesion(sesion);
    mostrarPantallaPrincipal(sesion);
    Sync.sincronizar();
    return;
  }

  // Multifundo: se elige con qué fundos de este usuario se va a trabajar
  // sin conexión (por defecto, su fundo principal).
  const fundosElegidos = await elegirFundosParaOffline(usuarioInfo);
  if (!fundosElegidos.length) {
    const sesion = await Db.obtenerSesion();
    sesion.modo_offline = null;
    await Db.guardarSesion(sesion);
    mostrarPantallaPrincipal(sesion);
    Sync.sincronizar();
    return;
  }

  const infoConFundos = Object.assign({}, usuarioInfo, {
    fundos: fundosDeUsuario(usuarioInfo).filter((f) => fundosElegidos.indexOf(f.id) !== -1),
  });

  try {
    await CredencialLocal.activar(
      usuario, password, infoConFundos, dispositivo,
      token, Dispositivo.obtenerId(), modoElegido, fundosElegidos
    );
    for (const id of fundosElegidos) {
      const info = infoConFundos.fundos.find((f) => f.id === id) || { id };
      await Db.descargarFundo(info);
    }
    const sesion = await Db.obtenerSesion();
    sesion.modo_offline = modoElegido;
    await Db.guardarSesion(sesion);
  } catch (e) {
    if (e && e.code === 'equipo_ocupado') {
      mostrarAvisoEquipoOcupado(e.usuarioExistente);
    } else if (e && e.code === 'dispositivo_gestion_ya_vinculado') {
      mostrarAvisoDispositivoYaVinculado(e.otroDispositivo);
      const sesion = await Db.obtenerSesion();
      sesion.modo_offline = null;
      await Db.guardarSesion(sesion);
    } else if (e && e.code === 'crypto_no_disponible') {
      console.warn('No se pudo activar reconexion offline:', e.message);
    } else {
      console.warn('Error activando credencial:', e);
    }
  }

  const sesion = await Db.obtenerSesion();
  mostrarPantallaPrincipal(sesion);
  Sync.sincronizar();
}

async function manejarReconexionLocal(evento) {
  evento.preventDefault();

  const usuarioEl = el('reconexion-usuario');
  const passwordEl = el('reconexion-password');
  const mensajeEl = el('reconexion-mensaje');
  const botonEl = el('reconexion-boton');

  if (!usuarioEl || !passwordEl) {
    if (mensajeEl) {
      mensajeEl.textContent = 'Este equipo tiene una versión desactualizada de la app. Recargá la página (Ctrl+Shift+R) para actualizarla.';
    } else {
      alert('Este equipo tiene una versión desactualizada de la app. Recargá la página (Ctrl+Shift+R) para actualizarla.');
    }
    return;
  }

  const usuario = usuarioEl.value.trim();
  const password = passwordEl.value;
  mensajeEl.textContent = '';

  if (botonEl) botonEl.disabled = true;

  try {
    if (navigator.onLine) {
      try {
        const resp = await Api.login(usuario, password, nombreDispositivo(), Dispositivo.obtenerId());
        await establecerSesionYActivarOffline(resp.token, resp.usuario, usuario, password);
        return;
      } catch (e) {
        if (e.message !== 'sin_conexion') {
          mensajeEl.textContent = e.message;
          return;
        }
      }
    }

    let credencial = null;
    try {
      credencial = await CredencialLocal.verificar(usuario, password);
    } catch (e) {
      if (e && e.code === 'crypto_no_disponible') {
        mensajeEl.textContent = 'Este navegador no soporta reconexión offline segura. Inicia sesión en línea.';
        return;
      }
      if (e && e.code === 'credencial_vencida') {
        mensajeEl.textContent = e.message;
        return;
      }
      throw e;
    }
    if (!credencial) {
      mensajeEl.textContent = 'Usuario o contraseña incorrectos.';
      return;
    }

    await Db.guardarSesion({
      token: null,
      usuario: credencial.usuarioInfo,
      usuario_login: usuario,
      fundo_id: credencial.usuarioInfo.fundo_id,
      fundo_activo: credencial.fundo_activo || (credencial.usuarioInfo && credencial.usuarioInfo.fundo_id),
      fundos: fundosDeUsuario(credencial.usuarioInfo),
      dispositivo: credencial.dispositivo,
      sesion_local: true,
      modo_offline: credencial.modoOffline || 'gestion',
    });

    const sesion = await Db.obtenerSesion();
    mostrarPantallaPrincipal(sesion);
  } catch (e) {
    mensajeEl.textContent = e && e.message
      ? e.message
      : 'No se pudo iniciar en modo offline en este dispositivo.';
  } finally {
    if (botonEl) botonEl.disabled = false;
  }
}

function manejarUsarOtroUsuario() {
  mostrarPantallaLogin();
}

async function manejarOlvidarEquipo() {
  const usuario = el('reconexion-usuario').value.trim();
  if (!usuario) return;

  const hayPendientes = await Db.hayPendientesSinSincronizar();
  const advertencia = hayPendientes
    ? '\n\n⚠️ ATENCIÓN: hay registros hechos en este equipo que todavía NO se subieron al servidor. Si continúas, se pierden para siempre.'
    : '';

  if (!window.confirm(
    'Esto borra la reconexión local guardada en este equipo para "' + usuario + '" y todos los datos guardados localmente. '
    + 'La próxima vez hará falta conexión para iniciar sesión.' + advertencia + '\n\n¿Continuar?'
  )) {
    return;
  }
  await Db.olvidarEquipoYDatos(usuario);
  mostrarPantallaLogin();
}

async function manejarPrecargaDesdeLogin(token, usuario) {
  try {
    const resp = await Api.validarToken(token);
    await Db.guardarSesion({
      token,
      usuario: resp.usuario,
      usuario_login: usuario,
      fundo_id: resp.usuario.fundo_id,
      fundo_activo: resp.usuario.fundo_id,
      fundos: fundosDeUsuario(resp.usuario),
      dispositivo: nombreDispositivo(),
      sesion_local: false,
      modo_offline: null,
    });

    const sesion = await Db.obtenerSesion();
    mostrarPantallaPrincipal(sesion);
    Sync.sincronizar();

    const hayCredencial = await CredencialLocal.hay(usuario);
    if (!hayCredencial) mostrarConfirmarOffline(usuario);

  } catch (e) {
    mostrarPantallaLogin();
    // Si el servidor dio un motivo concreto (por ejemplo que la cuenta no
    // tiene acceso al modo offline), se muestra tal cual.
    const detalle = e && e.message && e.message !== 'sin_conexion' ? e.message : '';
    el('login-mensaje').textContent = detalle
      || 'No se pudo validar el acceso desde el login principal. Inicia sesión de nuevo.';
  }
}

function mostrarConfirmarOffline(usuario) {
  el('confirmar-offline-usuario').value = usuario;
  el('confirmar-offline-password').value = '';
  el('confirmar-offline-mensaje').textContent = '';
  el('confirmar-offline-contenedor').classList.remove('oculto');
}

async function manejarConfirmarOffline(evento) {
  evento.preventDefault();
  const usuario = el('confirmar-offline-usuario').value.trim();
  const password = el('confirmar-offline-password').value;
  const mensajeEl = el('confirmar-offline-mensaje');
  mensajeEl.textContent = '';

  if (!navigator.onLine) {
    mensajeEl.textContent = 'Necesitas conexión para confirmar esto.';
    return;
  }

  try {
    el('confirmar-offline-boton').disabled = true;
    const resp = await Api.login(usuario, password, nombreDispositivo());
    try {
      await CredencialLocal.activar(usuario, password, resp.usuario, nombreDispositivo());
      el('confirmar-offline-contenedor').classList.add('oculto');
    } catch (err) {
      if (err && err.code === 'crypto_no_disponible') {
        mensajeEl.textContent = 'Este navegador no soporta reconexión offline segura en este equipo.';
      } else {
        throw err;
      }
    }
  } catch (e) {
    mensajeEl.textContent = e.message === 'sin_conexion' ? 'No hay conexión con el servidor.' : e.message;
  } finally {
    el('confirmar-offline-boton').disabled = false;
  }
}

function manejarOmitirConfirmarOffline() {
  el('confirmar-offline-contenedor').classList.add('oculto');
}

async function manejarLogout() {
  // Cerrar sesión nunca borra los datos locales, pero el trabajo de
  // campo sin subir no se sincronizará hasta volver a entrar con
  // conexión: conviene avisarlo antes.
  try {
    const hayPendientes = await Db.hayPendientesEnAlgunFundo();
    if (hayPendientes && !window.confirm(
      'Hay registros guardados en este equipo que todavía no se sincronizaron.\n\n'
      + 'Si cierras sesión no se borran, pero no se subirán al servidor hasta que vuelvas a entrar con conexión.\n\n'
      + '¿Cerrar sesión igual?'
    )) {
      return;
    }
  } catch (e) {
    // Si no se puede comprobar, no se bloquea el cierre de sesión.
  }

  const sesion = await Db.obtenerSesion();
  if (sesion && sesion.token && navigator.onLine) {
    try { await Api.logout(sesion.token); } catch (e) { /* no pasa nada si falla */ }
  }
  await Db.cerrarSesion();
  await mostrarPantallaInicialSegunEquipo();
}

let manejandoSesionInvalida = false;
async function manejarSesionInvalida(mensajeServidor) {
  if (manejandoSesionInvalida) return;
  const sesionActual = await Db.obtenerSesion();
  if (!sesionActual || !sesionActual.token) return;

  manejandoSesionInvalida = true;
  try {
    await Db.cerrarSesion();
    await mostrarPantallaInicialSegunEquipo();

    const destino = el('reconexion-mensaje') || el('login-mensaje');
    if (destino) {
      destino.textContent = 'Tu sesión ya no es válida'
        + (mensajeServidor ? ' (' + mensajeServidor + ')' : '')
        + '. Vuelve a iniciar sesión.';
    }
  } finally {
    manejandoSesionInvalida = false;
  }
}

let manejandoDispositivoDesvinculado = false;
async function manejarDispositivoDesvinculado(mensajeServidor) {
  if (manejandoDispositivoDesvinculado) return;
  const sesionActual = await Db.obtenerSesion();
  if (!sesionActual) return;

  manejandoDispositivoDesvinculado = true;
  try {
    await Db.cerrarSesion();
    await mostrarPantallaInicialSegunEquipo();

    const destino = el('reconexion-mensaje') || el('login-mensaje');
    if (destino) {
      destino.textContent = 'Este dispositivo fue desvinculado'
        + (mensajeServidor ? ' (' + mensajeServidor + ')' : '')
        + '. Tus datos sin sincronizar siguen guardados en este equipo. '
        + 'Inicia sesión en línea para volver a vincularlo.';
    }
  } finally {
    manejandoDispositivoDesvinculado = false;
  }
}

/**
 * Al administrador le quitó a esta cuenta el permiso de usar la app sin
 * conexión (musuarios.acceso_offline = false).
 *
 * Se cierra la sesión local —sin borrar nada del equipo: los registros
 * pendientes siguen en el outbox— y se le indica que debe contactar al
 * administrador del sistema para que le habilite el acceso.
 */
let manejandoSinAccesoOffline = false;
async function manejarSinAccesoOffline(mensajeServidor) {
  if (manejandoSinAccesoOffline) return;
  manejandoSinAccesoOffline = true;

  const texto = mensajeServidor || 'Tu cuenta no tiene acceso al modo sin conexión.';

  try {
    const sesionActual = await Db.obtenerSesion();
    if (sesionActual) {
      await Db.cerrarSesion();
      await mostrarPantallaInicialSegunEquipo();
    }

    const destino = el('reconexion-mensaje') || el('login-mensaje');
    if (destino) {
      destino.textContent = texto + ' Contacta al administrador del sistema.';
    }

    mostrarModalAviso(
      'Sin acceso al modo sin conexión',
      '<p>' + String(texto).replace(/</g, '&lt;') + '</p>'
      + '<p>Contacta al administrador del sistema para que habilite el acceso '
      + 'sin conexión en tu cuenta. Mientras tanto podés seguir usando la app con internet.</p>'
    );
  } finally {
    manejandoSinAccesoOffline = false;
  }
}

// ---------------------------------------------------------------------
// Pantalla principal
// ---------------------------------------------------------------------

/* ============================================================
   MULTIFUNDO
   ------------------------------------------------------------
   Un mismo usuario puede tener acceso a varios fundos. Cada fundo
   tiene SU PROPIA base de datos local (ver db.js), así que el
   aislamiento de datos es por construcción: al cambiar el "fundo
   activo" cambia la base que se está leyendo/escribiendo.
   ============================================================ */

/** Fundos que vienen en el usuario (login, validación o credencial). */
function fundosDeUsuario(usuarioInfo) {
  const fundos = usuarioInfo && Array.isArray(usuarioInfo.fundos) ? usuarioInfo.fundos : [];
  if (fundos.length) return fundos;

  // Compatibilidad con sesiones guardadas por la versión de un solo fundo.
  if (usuarioInfo && usuarioInfo.fundo_id) {
    return [{
      id: parseInt(usuarioInfo.fundo_id, 10),
      nombre: usuarioInfo.fundo_nombre || usuarioInfo.fundo || ('Fundo ' + usuarioInfo.fundo_id),
      tipo_ganaderia_id: parseInt(usuarioInfo.tipo_ganaderia_id, 10) || 0,
    }];
  }
  return [];
}

/**
 * Deja listo el fundo activo antes de pintar cualquier cosa: descarga
 * los fundos habilitados (la primera vez en este equipo) y elige el
 * que se va a usar.
 */
async function prepararFundosDeLaSesion(sesion) {
  let descargados = await Db.listarFundosMeta();

  if (!descargados.length) {
    const habilitados = fundosDeUsuario(sesion && sesion.usuario);
    for (const fundo of habilitados) {
      await Db.descargarFundo(fundo);
    }
    descargados = await Db.listarFundosMeta();
  }

  if (!descargados.length) {
    Db.usarFundo(0);
    return 0;
  }

  const guardado = parseInt(sesion && (sesion.fundo_activo || sesion.fundo_id), 10) || 0;
  const elegido = descargados.some((f) => f.id === guardado) ? guardado : descargados[0].id;

  Db.usarFundo(elegido);
  return elegido;
}

/** Nombre del fundo con el que se está trabajando. */
async function nombreFundoActivo() {
  const activo = Db.fundoActivo();
  if (!activo) return '';
  const lista = await Db.listarFundosMeta();
  const fila = lista.find((f) => f.id === activo);
  return fila ? fila.nombre : ('Fundo ' + activo);
}

/** Selector de fundo en la cabecera (oculto si solo hay uno). */
async function pintarSelectorFundo() {
  const contenedor = el('fundo-activo');
  if (!contenedor) return;

  const lista = await Db.listarFundosMeta();
  const activo = Db.fundoActivo();
  contenedor.innerHTML = '';

  if (!lista.length) {
    contenedor.classList.add('oculto');
    return;
  }
  contenedor.classList.remove('oculto');

  const nombre = document.createElement('span');
  nombre.className = 'fundo-activo__nombre';
  nombre.id = 'fundo-activo-nombre';
  nombre.textContent = normalizarMayusculas((lista.find((f) => f.id === activo) || {}).nombre || ('Fundo ' + activo));
  contenedor.appendChild(nombre);

  if (lista.length > 1) {
    const select = document.createElement('select');
    select.id = 'selector-fundo';
    select.className = 'fundo-activo__select';
    select.title = 'Elegir el fundo con el que trabajar';
    for (const fundo of lista) {
      const opcion = document.createElement('option');
      opcion.value = String(fundo.id);
      opcion.textContent = normalizarMayusculas(fundo.nombre);
      if (fundo.id === activo) opcion.selected = true;
      select.appendChild(opcion);
    }
    select.addEventListener('change', () => { cambiarFundoActivo(parseInt(select.value, 10)); });
    contenedor.appendChild(select);
  }

  const boton = document.createElement('button');
  boton.type = 'button';
  boton.id = 'btn-fundos';
  boton.className = 'fundo-activo__config';
  boton.title = 'Fundos en este dispositivo';
  boton.textContent = '⚙';
  boton.addEventListener('click', () => abrirModalFundos());
  contenedor.appendChild(boton);
}

/** Cambia el fundo con el que se trabaja (cada uno tiene su base local). */
async function cambiarFundoActivo(fundoId) {
  const id = parseInt(fundoId, 10) || 0;
  const sesion = await Db.obtenerSesion();
  if (!id || !sesion) return;

  Db.usarFundo(id);
  sesion.fundo_activo = id;
  sesion.fundo_id = id; // compatibilidad con el resto de la app
  await Db.guardarSesion(sesion);

  await mostrarPantallaPrincipal(sesion);
  Sync.sincronizar();
}

/**
 * Pregunta en qué fundos de este usuario se va a trabajar sin conexión.
 * Devuelve la lista de ids elegidos (vacía = ninguno).
 */
function elegirFundosParaOffline(usuarioInfo) {
  const disponibles = fundosDeUsuario(usuarioInfo);
  const principal = parseInt(usuarioInfo && usuarioInfo.fundo_id, 10) || 0;

  return new Promise((resolve) => {
    const overlay = el('modal-fundos-overlay');
    const listaEl = el('modal-fundos-lista');

    if (!overlay || !listaEl || disponibles.length <= 1) {
      resolve(disponibles.map((f) => f.id));
      return;
    }

    el('modal-fundos-mensaje').textContent = '';
    listaEl.innerHTML = '';

    disponibles.forEach((fundo) => {
      const fila = document.createElement('label');
      fila.className = 'modal-fundos__fila';

      const check = document.createElement('input');
      check.type = 'checkbox';
      check.value = String(fundo.id);
      check.checked = fundo.id === principal;
      check.dataset.nombre = fundo.nombre || '';

      const texto = document.createElement('span');
      texto.innerHTML = '<b>' + (fundo.nombre || ('Fundo ' + fundo.id)) + '</b>'
        + '<small>' + ((fundo.tipo_ganaderia_id === 1) ? 'Ovinos' : 'Caprinos') + '</small>';

      fila.appendChild(check);
      fila.appendChild(texto);
      listaEl.appendChild(fila);
    });

    const cerrar = () => {
      overlay.classList.add('oculto');
      botonGuardar.removeEventListener('click', guardar);
      botonCancelar.removeEventListener('click', cancelar);
    };

    const seleccionados = () => Array.from(listaEl.querySelectorAll('input:checked'))
      .map((c) => parseInt(c.value, 10));

    const guardar = () => {
      const ids = seleccionados();
      if (!ids.length) {
        el('modal-fundos-mensaje').textContent = 'Elegí al menos un fundo para trabajar sin conexión.';
        return;
      }
      cerrar();
      resolve(ids);
    };

    const cancelar = () => {
      cerrar();
      resolve([]);
    };

    const botonGuardar = el('modal-fundos-guardar');
    const botonCancelar = el('modal-fundos-cancelar');
    botonGuardar.addEventListener('click', guardar);
    botonCancelar.addEventListener('click', cancelar);

    overlay.classList.remove('oculto');
  });
}

/** Administra los fundos descargados en el equipo (agregar / quitar). */
async function abrirModalFundos() {
  const overlay = el('modal-fundos-overlay');
  if (!overlay) return;

  const sesion = await Db.obtenerSesion();
  const disponibles = fundosDeUsuario(sesion && sesion.usuario);
  const descargados = await Db.listarFundosMeta();

  const listaEl = el('modal-fundos-lista');
  el('modal-fundos-mensaje').textContent = '';
  listaEl.innerHTML = '';

  const lista = disponibles.length ? disponibles : descargados;

  lista.forEach((fundo, indice) => {
    const fila = document.createElement('label');
    fila.className = 'modal-fundos__fila';

    const check = document.createElement('input');
    check.type = 'checkbox';
    check.value = String(fundo.id);
    check.dataset.nombre = fundo.nombre || '';

    const descargado = descargados.find((f) => f.id === fundo.id);
    check.checked = !!descargado;

    const texto = document.createElement('span');
    const ultima = descargado && descargado.ultima_sync
      ? new Date(descargado.ultima_sync).toLocaleString()
      : 'nunca';
    texto.innerHTML = '<b>' + (fundo.nombre || ('Fundo ' + fundo.id)) + '</b>'
      + '<small>' + (descargado ? ('descargado · última sincronización: ' + ultima) : 'no descargado en este equipo') + '</small>';

    fila.appendChild(check);
    fila.appendChild(texto);
    listaEl.appendChild(fila);

    if (indice === 0) { /* nada especial */ }
  });

  const botonGuardar = el('modal-fundos-guardar');
  const botonCancelar = el('modal-fundos-cancelar');

  const guardar = async () => {
    const elegidos = Array.from(listaEl.querySelectorAll('input:checked')).map((c) => ({
      id: parseInt(c.value, 10),
      nombre: c.dataset.nombre,
    }));

    if (!elegidos.length) {
      el('modal-fundos-mensaje').textContent = 'Debe quedar al menos un fundo en el dispositivo.';
      return;
    }

    // Se quitan los fundos desmarcados (borra su base local) y se suman
    // los nuevos. Antes de quitar uno, se avisa si tiene cambios sin subir.
    const aQuitar = descargados.filter((f) => !elegidos.some((e) => e.id === f.id));
    if (aQuitar.length) {
      const pendientes = await Db.fundosConPendientes();
      const conPendientes = aQuitar.filter((f) => pendientes.some((p) => p.fundo.id === f.id));
      const aviso = conPendientes.length
        ? '\n\n⚠️ Estos fundos tienen registros que todavía NO se subieron al servidor y se van a perder: '
          + conPendientes.map((f) => f.nombre).join(', ')
        : '';
      if (!window.confirm('Se quitarán del equipo: ' + aQuitar.map((f) => f.nombre).join(', ') + '.' + aviso + '\n\n¿Continuar?')) {
        return;
      }
      for (const fundo of aQuitar) {
        await Db.borrarFundo(fundo.id);
      }
    }

    for (const elegido of elegidos) {
      const info = (disponibles.find((f) => f.id === elegido.id) || elegido);
      await Db.descargarFundo(info);
    }

    const quedan = await Db.listarFundosMeta();
    if (!quedan.some((f) => f.id === Db.fundoActivo())) {
      Db.usarFundo(quedan[0].id);
    }
    sesion.fundo_activo = Db.fundoActivo();
    sesion.fundo_id = Db.fundoActivo();
    await Db.guardarSesion(sesion);

    cerrarModal();
    await mostrarPantallaPrincipal(sesion);
    Sync.sincronizar();
  };

  const cerrarModal = () => {
    overlay.classList.add('oculto');
    botonGuardar.removeEventListener('click', guardar);
    botonCancelar.removeEventListener('click', cerrarModal);
  };

  botonGuardar.addEventListener('click', guardar);
  botonCancelar.addEventListener('click', cerrarModal);

  overlay.classList.remove('oculto');
}

async function mostrarPantallaPrincipal(sesion) {
  el('pantalla-login').classList.add('oculto');
  el('pantalla-reconexion-local').classList.add('oculto');
  el('pantalla-principal').classList.remove('oculto');
  el('aviso-equipo-ocupado').classList.add('oculto');

  // Cada fundo tiene su propia base de datos: se deja listo el fundo
  // activo ANTES de leer nada.
  await prepararFundosDeLaSesion(sesion);

  const nombreUsuario = (sesion && sesion.usuario && sesion.usuario.nombre)
    || (sesion && sesion.usuario_nombre)
    || (sesion && sesion.usuario_login)
    || 'Usuario';

  el('etiqueta-usuario').textContent = normalizarMayusculas(nombreUsuario);

  await pintarSelectorFundo();
  aplicarModoConsultaSiAplica(sesion);

  await actualizarAvisoSesionLocal(sesion);
  actualizarVisibilidadBotonDatosWeb();

  await poblarSelectsCatalogos();
  await renderizarLista();
  await renderizarListaControlSanitario();
  await renderizarListaControlLote();
  await renderizarListaDesincorporacion();
  await renderizarListaMovimientoRebano();
  await renderizarListaProduccionLeche();
  await renderizarListaPesos();
  await renderizarListaServicios();
  await renderizarListaNacimientos();
  await renderizarListaReportes();
  await renderizarConflictos();
  await actualizarContadorPendientes();
}

function mostrarAvisoEquipoOcupado(usuarioExistente) {
  mostrarModalAviso(
    'Modo offline no activado en este equipo',
    '<p style="margin:0 0 12px;">Este equipo ya tiene el modo sin conexión activado para:</p>'
    + '<div style="background:#f5f5f5; padding:10px 12px; border-radius:8px; margin-bottom:12px;">'
    +   '<div style="font-weight:700; color:#1f2937;">' + escaparHtml(usuarioExistente) + '</div>'
    + '</div>'
    + '<div style="background:#fff3cd; border:1px solid #e6d89a; padding:10px 12px; border-radius:8px; font-size:12px; color:#6b5a1e;">'
    +   '<strong>Estás trabajando en línea con normalidad.</strong><br>'
    +   'Si querés trabajar sin conexión con tu usuario en este equipo, alguien que administre el '
    +   'dispositivo debe tocar "Olvidar este equipo" desde la pantalla de reconexión.'
    + '</div>'
  );
}

function mostrarAvisoDispositivoYaVinculado(otroDispositivo) {
  const nombre = otroDispositivo && otroDispositivo.nombre
    ? otroDispositivo.nombre
    : 'otro dispositivo';

  const ultimaRaw = otroDispositivo && otroDispositivo.ultima_sincronizacion;
  const ultimaTexto = ultimaRaw
    ? formatearFecha(typeof ultimaRaw === 'object' ? ultimaRaw.date : ultimaRaw)
    : '';
  const lineaUltima = ultimaTexto
    ? '<div style="margin-top:6px; color:#5d676d; font-size:12px;">Última sincronización: <strong>' + escaparHtml(ultimaTexto) + '</strong></div>'
    : '';

  mostrarModalAviso(
    'Este usuario ya tiene un dispositivo en modo GESTIÓN',
    '<p style="margin:0 0 12px;">Ya existe otro dispositivo con modo GESTIÓN activo para este usuario:</p>'
    + '<div style="background:#f5f5f5; padding:10px 12px; border-radius:8px; margin-bottom:12px;">'
    +   '<div style="font-weight:700; color:#1f2937;">' + escaparHtml(nombre) + '</div>'
    +   lineaUltima
    + '</div>'
    + '<div style="background:#fff3cd; border:1px solid #e6d89a; padding:10px 12px; border-radius:8px; font-size:12px; color:#6b5a1e;">'
    +   '<strong>Estás trabajando en línea con normalidad.</strong><br>'
    +   'Para gestionar sin conexión acá, primero desvinculá el otro dispositivo desde el '
    +   'sistema web → MAESTROS → Dispositivos Offline. Podés seguir usando la app en modo online.'
    + '</div>'
  );
}

async function actualizarAvisoSesionLocal(sesion) {
  const aviso = el('aviso-sesion-local');
  if (!aviso) return;

  const esLocal = !!(sesion && sesion.sesion_local);
  aviso.classList.toggle('oculto', !esLocal);
  if (!esLocal) return;

  const plazoEl = el('aviso-sesion-local-plazo');
  if (!plazoEl || !sesion.usuario_login) return;

  const vigencia = await CredencialLocal.vigenciaDe(sesion.usuario_login);
  if (vigencia.avisar && vigencia.fechaLimite) {
    plazoEl.textContent = 'Debes sincronizar antes del ' + formatearFecha(vigencia.fechaLimite.toISOString())
      + ' o dejarás de poder trabajar sin conexión en este equipo.';
    plazoEl.classList.remove('oculto');
  } else {
    plazoEl.classList.add('oculto');
  }
}

async function manejarRenovarSesion(evento) {
  evento.preventDefault();
  const sesionActual = await Db.obtenerSesion();
  if (!sesionActual) return;

  const password = el('renovar-password').value;
  const mensajeEl = el('renovar-mensaje');
  mensajeEl.textContent = '';

  if (!navigator.onLine) {
    mensajeEl.textContent = 'Todavía no hay conexión.';
    return;
  }

  try {
    el('renovar-boton').disabled = true;
    const resp = await Api.login(
      sesionActual.usuario_login, password, sesionActual.dispositivo,
      Dispositivo.obtenerId()
    );
    await establecerSesionYActivarOffline(resp.token, resp.usuario, sesionActual.usuario_login, password);
    el('renovar-password').value = '';
  } catch (e) {
    mensajeEl.textContent = e.message === 'sin_conexion' ? 'No hay conexión con el servidor.' : e.message;
  } finally {
    el('renovar-boton').disabled = false;
  }
}

const REPORTES_ACCIONES_EN_CURSO = new Set();

const REPORT_FILTERS_CONFIG = {
  animales_activos: ['sexo', 'lote_id'],
  resumen_servicios: ['fecha_desde', 'fecha_hasta', 'hembra', 'tipo_servicio'],
  resumen_produccion_lechera: ['fecha_desde', 'fecha_hasta', 'hembra'],
  resumen_control_sanitario: ['fecha_desde', 'fecha_hasta', 'animal'],
  produccion_acumulada: ['fecha_desde', 'fecha_hasta', 'hembra'],
  servicios_por_tipo: ['fecha_desde', 'fecha_hasta', 'hembra', 'tipo_servicio'],
  gestantes_activas: ['fecha_desde', 'fecha_hasta', 'hembra'],
  partos_confirmados: ['fecha_desde', 'fecha_hasta', 'hembra'],
  inventario_por_estado: ['sexo', 'estado_animal'],
  bajas_desincorporados: ['sexo', 'lote_id', 'animal', 'estado_animal'],
  resumen_por_aptitud: ['sexo', 'aptitud'],
  resumen_nacimientos: ['fecha_desde', 'fecha_hasta', 'madre_con_partos'],
  consecutivo_peso: ['fecha_desde', 'fecha_hasta', 'animal'],
  movimiento_rebano: ['fecha_desde', 'fecha_hasta', 'lote_id'],
  grupos_etarios: ['sexo'],
  hembras_servidas: ['fecha_desde', 'fecha_hasta', 'hembra', 'tipo_servicio'],
  productoras_leche: ['fecha_desde', 'fecha_hasta', 'hembra'],
  animales_por_lote: ['sexo', 'lote_id'],
};

const REPORT_FILTER_LABELS = {
  fecha_desde: 'Desde',
  fecha_hasta: 'Hasta',
  sexo: 'Sexo',
  lote_id: 'Lote',
  animal: 'Animal',
  hembra: 'Hembra',
  macho: 'Macho',
  tipo_servicio: 'Tipo servicio',
  estado_animal: 'Estado',
  aptitud: 'Aptitud',
  madre_con_partos: 'Codigo / Arete de la madre',
};

function obtenerEtiquetaFiltroPorContexto(clave, contexto) {
  if (clave === 'estado_animal' && contexto === 'bajas_desincorporados') return 'Tipo de baja';
  if (clave !== 'hembra') return REPORT_FILTER_LABELS[clave] || clave;

  switch (contexto) {
    case 'gestantes_activas':
    case 'partos_confirmados':
      return 'Hembra gestante';
    case 'resumen_produccion_lechera':
    case 'produccion_acumulada':
      return 'Hembra en leche';
    default:
      return 'Hembra';
  }
}

function obtenerDescripcionFiltroPorContexto(clave, contexto) {
  if (clave !== 'hembra') return '';

  switch (contexto) {
    case 'gestantes_activas':
    case 'partos_confirmados':
      return 'Solo hembras gestantes';
    case 'resumen_produccion_lechera':
    case 'produccion_acumulada':
      return 'Solo hembras en leche';
    default:
      return 'Todas las hembras';
  }
}

function obtenerParametrosReporte(code) {
  return (REPORT_FILTERS_CONFIG[code] || []).map((clave) => ({
    clave,
    etiqueta: REPORT_FILTER_LABELS[clave] || clave,
  }));
}

function construirControlFiltro(clave, contexto = 'general') {
  const valorBase = `data-reporte-input="${clave}"`;
  const etiqueta = obtenerEtiquetaFiltroPorContexto(clave, contexto);

  switch (clave) {
    case 'animal':
    case 'hembra':
    case 'madre_con_partos': {
      const inputId = `animal-search-${Math.random().toString(36).slice(2, 10)}`;
      let tipo = clave === 'madre_con_partos' ? 'madre_con_partos' : clave === 'animal' ? 'animal' : 'hembra';
      if (clave === 'animal') {
        if (contexto === 'resumen_control_sanitario') tipo = 'animal_control_sanitario';
        else if (contexto === 'bajas_desincorporados') tipo = 'animal_baja';
      } else if (clave === 'hembra') {
        if (contexto === 'gestantes_activas') tipo = 'gestante';
        else if (contexto === 'partos_confirmados') tipo = 'parto_confirmado';
        else if (contexto === 'resumen_produccion_lechera') tipo = 'leche';
        else if (contexto === 'resumen_servicios' || contexto === 'servicios_por_tipo') tipo = 'hembra_servicio';
      }
      const labelPlaceholder = tipo === 'animal' ? 'Buscar animal...'
        : tipo === 'animal_control_sanitario' ? 'Buscar animal con control sanitario...'
        : tipo === 'animal_baja' ? 'Buscar animal dado de baja...'
        : tipo === 'gestante' ? 'Buscar hembra gestante...'
        : tipo === 'parto_confirmado' ? 'Buscar hembra con parto confirmado...'
        : tipo === 'leche' ? 'Buscar hembra en produccion de leche...'
        : tipo === 'hembra_servicio' ? 'Buscar hembra con servicio...'
        : tipo === 'madre_con_partos' ? 'Buscar codigo o arete de la madre...'
        : 'Buscar hembra...';
      const descripcion = obtenerDescripcionFiltroPorContexto(clave, contexto);
      return `
        <label style="display:flex; flex-direction:column; gap:4px; font-size:11px; color:#475467; position:relative;">
          <span>${escaparHtml(etiqueta)}</span>
          ${descripcion ? `<small style="color:#667085; font-size:10px; line-height:1.2;">${escaparHtml(descripcion)}</small>` : ''}
          <div style="position:relative;">
            <span style="position:absolute; right:10px; top:50%; transform:translateY(-50%); color:#6b7280; pointer-events:none; font-size:12px;">⌕</span>
            <input type="text" ${valorBase} data-animal-buscador="${inputId}" data-animal-tipo="${tipo}" autocomplete="off" placeholder="${escaparHtml(labelPlaceholder)}" style="min-width:150px; padding:6px 28px 6px 8px; border:1px solid #d0d5dd; border-radius:8px; width:100%; background:#fff; appearance:none; box-shadow:inset 0 1px 2px rgba(15,23,42,0.03);">
            <div id="${inputId}" class="animal-search-suggestions oculto" style="position:absolute; top:calc(100% + 4px); left:0; right:0; background:#fff; border:1px solid #d0d5dd; border-radius:8px; box-shadow:0 8px 20px rgba(15,23,42,0.12); max-height:180px; overflow:auto; z-index:20; display:none;"></div>
          </div>
        </label>
      `;
    }
    case 'fecha_desde':
    case 'fecha_hasta':
      return `
        <label style="display:flex; flex-direction:column; gap:4px; font-size:11px; color:#475467;">
          <span>${escaparHtml(etiqueta)}</span>
          <input type="date" ${valorBase} style="min-width:150px; padding:6px 8px; border:1px solid #d0d5dd; border-radius:8px;">
        </label>
      `;
    case 'sexo':
      return `
        <label style="display:flex; flex-direction:column; gap:4px; font-size:11px; color:#475467;">
          <span>${escaparHtml(etiqueta)}</span>
          <select ${valorBase} style="min-width:150px; padding:6px 8px; border:1px solid #d0d5dd; border-radius:8px;">
            <option value="">Todos</option>
            <option value="1">Hembra</option>
            <option value="2">Macho</option>
          </select>
        </label>
      `;
    case 'lote_id':
      return `
        <label style="display:flex; flex-direction:column; gap:4px; font-size:11px; color:#475467;">
          <span>${escaparHtml(etiqueta)}</span>
          <select ${valorBase} style="min-width:150px; padding:6px 8px; border:1px solid #d0d5dd; border-radius:8px;">
            <option value="">Todos</option>
          </select>
        </label>
      `;
    case 'tipo_servicio':
      return `
        <label style="display:flex; flex-direction:column; gap:4px; font-size:11px; color:#475467;">
          <span>${escaparHtml(etiqueta)}</span>
          <select ${valorBase} style="min-width:150px; padding:6px 8px; border:1px solid #d0d5dd; border-radius:8px;">
            <option value="">Todos</option>
            <option value="1">Monta</option>
            <option value="2">Inseminación artificial</option>
            <option value="3">Transferencia de embriones</option>
          </select>
        </label>
      `;
    case 'estado_animal':
      // En el reporte de bajas este filtro es el TIPO DE BAJA (ids de
      // mestatusganados), no el estatus/donador del animal.
      if (contexto === 'bajas_desincorporados') {
        return `
          <label style="display:flex; flex-direction:column; gap:4px; font-size:11px; color:#475467;">
            <span>${escaparHtml(etiqueta)}</span>
            <select ${valorBase} style="min-width:150px; padding:6px 8px; border:1px solid #d0d5dd; border-radius:8px;">
              <option value="">Todos</option>
              <option value="2">Descarte</option>
              <option value="3">Muerte</option>
              <option value="4">Venta</option>
              <option value="5">Extravío</option>
              <option value="7">Migrado a otro fundo</option>
            </select>
          </label>
        `;
      }
      return `
        <label style="display:flex; flex-direction:column; gap:4px; font-size:11px; color:#475467;">
          <span>${escaparHtml(etiqueta)}</span>
          <select ${valorBase} style="min-width:150px; padding:6px 8px; border:1px solid #d0d5dd; border-radius:8px;">
            <option value="">Todos</option>
            <option value="1">Activo</option>
            <option value="2">Donador Activo</option>
            <option value="3">Donador</option>
            <option value="4">Donadora Activo</option>
            <option value="5">Donadora</option>
          </select>
        </label>
      `;
    case 'aptitud':
      return `
        <label style="display:flex; flex-direction:column; gap:4px; font-size:11px; color:#475467;">
          <span>${escaparHtml(etiqueta)}</span>
          <select ${valorBase} style="min-width:150px; padding:6px 8px; border:1px solid #d0d5dd; border-radius:8px;">
            <option value="">Todas</option>
            <option value="1">Carne</option>
            <option value="2">Leche</option>
            <option value="3">Doble propósito</option>
            <option value="4">Reproductor</option>
          </select>
        </label>
      `;
    default:
      return `
        <label style="display:flex; flex-direction:column; gap:4px; font-size:11px; color:#475467;">
          <span>${escaparHtml(etiqueta)}</span>
          <input type="text" ${valorBase} placeholder="${escaparHtml(etiqueta)}" style="min-width:150px; padding:6px 8px; border:1px solid #d0d5dd; border-radius:8px;">
        </label>
      `;
  }
}

async function poblarSelectsLoteEnReportes(card) {
  const selects = card.querySelectorAll('select[data-reporte-input="lote_id"]');
  if (!selects.length) return;

  const lotes = await Db.obtenerCatalogo('lotes');
  selects.forEach((select) => {
    const valorPrevio = select.value;
    while (select.options.length > 1) select.remove(1);
    for (const lote of lotes) {
      const opcion = document.createElement('option');
      opcion.value = String(lote.id);
      opcion.textContent = lote.nombre || `Lote ${lote.id}`;
      select.appendChild(opcion);
    }
    if (valorPrevio && [...select.options].some((o) => o.value === valorPrevio)) {
      select.value = valorPrevio;
    }
  });
}

async function inicializarBuscadorAnimales(card) {
  const animales = await Animales.listar();
  const servicios = await Db.obtenerTodos('servicios');
  const producciones = await Db.obtenerTodos('produccion_leche');
  const nacimientos = await Db.obtenerTodos('nacimientos');
  const controlesSanitarios = await Db.obtenerTodos('control_sanitario');
  const mapaAnimalesPorCodigo = new Map(
    animales.map((animal) => [String(animal.codigo_rgb_id_animal || '').trim(), animal])
  );

  const construirCandidatosDesdeCodigos = (codigos) => [...codigos].map((codigo) => {
    const animal = mapaAnimalesPorCodigo.get(codigo);
    if (animal) {
      const activo = Number(animal.status ?? 1) > 0 && Number(animal.mestatus_id ?? 1) === 1;
      return activo ? animal : null;
    }
    return { codigo_rgb_id_animal: codigo, nombre: '', nro_arete: '', sexo: null };
  }).filter(Boolean);

  const codigosDesde = (lista, campo) => new Set(
    lista.map((item) => String(item[campo] || '').trim()).filter(Boolean)
  );

  const inputs = card.querySelectorAll('[data-animal-buscador]');

  inputs.forEach((input) => {
    const sugerencias = card.querySelector(`#${CSS.escape(input.dataset.animalBuscador)}`);
    if (!sugerencias) return;

    const tipoBusqueda = input.dataset.animalTipo || 'animal';
    let animalesDisponibles;

    if (tipoBusqueda === 'madre_con_partos') {
      const codigosConPartos = codigosDesde(
        nacimientos.filter((n) => Number(n.status ?? 1) === 1),
        'codigo_rgb_id_animal_hembra'
      );
      animalesDisponibles = construirCandidatosDesdeCodigos(codigosConPartos);
    } else if (tipoBusqueda === 'animal_control_sanitario') {
      const codigosConControl = codigosDesde(controlesSanitarios, 'codigo_rgb_id_animal');
      animalesDisponibles = construirCandidatosDesdeCodigos(codigosConControl);
    } else if (tipoBusqueda === 'leche') {
      const codigosConLeche = codigosDesde(producciones, 'codigo_rgb_id_animal_hembra');
      animalesDisponibles = construirCandidatosDesdeCodigos(codigosConLeche);
    } else if (tipoBusqueda === 'hembra_servicio') {
      const codigosConServicio = codigosDesde(servicios, 'codigo_rgb_id_animal_hembra');
      animalesDisponibles = construirCandidatosDesdeCodigos(codigosConServicio);
    } else if (tipoBusqueda === 'gestante') {
      const codigosGestantes = codigosDesde(
        servicios.filter((s) => Number(s.status ?? 1) === 1 && Number(s.medofisiologicos_id) === 1 && Number(s.parto || 0) === 0),
        'codigo_rgb_id_animal_hembra'
      );
      animalesDisponibles = construirCandidatosDesdeCodigos(codigosGestantes);
    } else if (tipoBusqueda === 'parto_confirmado') {
      const codigosPartoConfirmado = codigosDesde(
        servicios.filter((s) => Number(s.status ?? 1) === 1 && Number(s.medofisiologicos_id) === 1 && Number(s.parto || 0) === 1),
        'codigo_rgb_id_animal_hembra'
      );
      animalesDisponibles = construirCandidatosDesdeCodigos(codigosPartoConfirmado);
    } else if (tipoBusqueda === 'animal_baja') {
      animalesDisponibles = animales.filter((animal) => {
        const estado = Number(animal.estatus_animal || 0);
        return [4, 5].includes(estado)
          || /desincorpor|baja|mortal|muerto|retiro|sacrificio/i.test(String(animal.observaciones || ''));
      });
    } else {
      animalesDisponibles = animales.filter((animal) => {
        if (tipoBusqueda === 'animal') return true;
        if (tipoBusqueda === 'hembra') return Number(animal.sexo) === 2;
        return true;
      });
    }

    const aplicarEstiloInput = () => {
      input.style.borderColor = '#a7b7d6';
      input.style.boxShadow = '0 0 0 3px rgba(120, 138, 90, 0.12)';
    };

    const limpiarEstiloInput = () => {
      input.style.borderColor = '#d0d5dd';
      input.style.boxShadow = 'none';
    };

    const cerrarSugerencias = () => {
      sugerencias.style.display = 'none';
      sugerencias.classList.add('oculto');
      limpiarEstiloInput();
    };

    const poblarSugerencias = () => {
      const texto = (input.value || '').trim().toLowerCase();
      const resultados = !texto
        ? animalesDisponibles.slice(0, 8)
        : animalesDisponibles.filter((animal) => {
            const codigo = (animal.codigo_rgb_id_animal || '').toLowerCase();
            const nombre = (animal.nombre || '').toLowerCase();
            const arete = (animal.nro_arete || '').toLowerCase();
            const textoBuscado = [codigo, nombre, arete].join(' ');
            return textoBuscado.includes(texto);
          }).slice(0, 8);

      if (!resultados.length) {
        sugerencias.innerHTML = '<div style="padding:8px 10px; color:#667085; font-size:11px;">Sin coincidencias</div>';
        sugerencias.style.display = 'block';
        sugerencias.classList.remove('oculto');
        aplicarEstiloInput();
        return;
      }

      sugerencias.innerHTML = resultados.map((animal, index) => {
        const codigo = animal.codigo_rgb_id_animal || 'Sin código';
        const nombre = animal.nombre ? ` - ${animal.nombre}` : '';
        const arete = animal.nro_arete ? ` · Arete ${animal.nro_arete}` : '';
        return `
          <button type="button" data-animal-seleccionado="${escaparHtml(codigo)}" data-animal-index="${index}" style="display:block; width:100%; text-align:left; padding:8px 10px; border:0; background:transparent; cursor:pointer; font-size:11px; color:#1f2937; border-bottom:1px solid #f2f4f7; transition:background 0.15s ease;">
            <span style="display:block; font-weight:700; color:#1f2937;">${escaparHtml(codigo)}</span>
            <span style="display:block; color:#667085;">${escaparHtml(nombre.replace(/^\s*-\s*/, ''))}${escaparHtml(arete)}</span>
          </button>
        `;
      }).join('');

      sugerencias.querySelectorAll('[data-animal-seleccionado]').forEach((boton) => {
        boton.addEventListener('mouseenter', () => { boton.style.background = '#f8f5ef'; });
        boton.addEventListener('mouseleave', () => { boton.style.background = 'transparent'; });
        boton.addEventListener('click', () => {
          input.value = boton.dataset.animalSeleccionado;
          cerrarSugerencias();
        });
      });

      sugerencias.style.display = 'block';
      sugerencias.classList.remove('oculto');
      aplicarEstiloInput();
    };

    input.addEventListener('input', poblarSugerencias);
    input.addEventListener('focus', poblarSugerencias);
    input.addEventListener('keydown', (evento) => {
      const botones = [...sugerencias.querySelectorAll('[data-animal-seleccionado]')];
      if (!botones.length) return;

      if (evento.key === 'ArrowDown') {
        evento.preventDefault();
        const actual = botones.findIndex((boton) => boton.style.background === 'rgb(248, 245, 239)');
        const siguiente = actual >= 0 ? (actual + 1) % botones.length : 0;
        botones.forEach((boton, index) => {
          boton.style.background = index === siguiente ? '#f8f5ef' : 'transparent';
        });
      }

      if (evento.key === 'ArrowUp') {
        evento.preventDefault();
        const actual = botones.findIndex((boton) => boton.style.background === 'rgb(248, 245, 239)');
        const anterior = actual > 0 ? actual - 1 : botones.length - 1;
        botones.forEach((boton, index) => {
          boton.style.background = index === anterior ? '#f8f5ef' : 'transparent';
        });
      }

      if (evento.key === 'Enter') {
        const seleccionado = botones.find((boton) => boton.style.background === 'rgb(248, 245, 239)') || botones[0];
        if (seleccionado) {
          evento.preventDefault();
          input.value = seleccionado.dataset.animalSeleccionado;
          cerrarSugerencias();
        }
      }

      if (evento.key === 'Escape') cerrarSugerencias();
    });

    input.addEventListener('blur', () => {
      setTimeout(() => {
        if (!sugerencias.contains(document.activeElement)) cerrarSugerencias();
      }, 120);
    });
  });
}

function mostrarFiltrosReporte(code) {
  const filtrosGlobales = document.getElementById('filtros-reporte');
  if (filtrosGlobales) {
    filtrosGlobales.classList.add('oculto');
    filtrosGlobales.style.display = 'none';
  }

  document.querySelectorAll('.reporte-card').forEach((card) => {
    const activo = card.dataset.reporteCodigo === code;
    card.dataset.activo = activo ? '1' : '0';
    const panel = card.querySelector('.reporte-parametros');
    if (panel) panel.style.display = activo ? 'block' : 'none';

    const botonMostrar = card.querySelector('[data-mostrar-reporte]');
    if (botonMostrar) {
      botonMostrar.style.background = activo ? '#234e35' : '#8a5a2d';
      botonMostrar.style.borderColor = activo ? '#234e35' : '#8a5a2d';
      botonMostrar.textContent = activo ? 'Abierto' : 'Mostrar';
      botonMostrar.style.padding = '6px 10px';
      botonMostrar.style.fontSize = '11px';
      botonMostrar.style.minWidth = '70px';
      botonMostrar.style.fontWeight = '700';
    }

    const vistaPrevia = card.querySelector('[data-vista-reporte]');
    const exportar = card.querySelector('[data-generar-reporte]');
    const compartir = card.querySelector('[data-compartir-reporte]');
    if (vistaPrevia) vistaPrevia.style.display = activo ? 'inline-flex' : 'none';
    if (exportar) exportar.style.display = activo ? 'inline-flex' : 'none';
    if (compartir) compartir.style.display = activo ? 'inline-flex' : 'none';
  });
}

function obtenerFiltrosReporte(code = null) {
  const fuente = code ? document.querySelector(`.reporte-card[data-reporte-codigo="${CSS.escape(code)}"]`) : null;
  const map = {};

  if (fuente) {
    fuente.querySelectorAll('[data-reporte-input]').forEach((input) => {
      const clave = input.dataset.reporteInput;
      const valor = input.value || '';
      if (valor) map[clave] = valor;
    });
  } else {
    const filtros = {
      fecha_desde: el('reporte-fecha-desde')?.value || '',
      fecha_hasta: el('reporte-fecha-hasta')?.value || '',
      sexo: el('reporte-sexo')?.value || '',
      lote_id: el('reporte-lote')?.value || '',
      animal: el('reporte-animal')?.value || '',
      hembra: el('reporte-hembra')?.value || '',
      macho: el('reporte-macho')?.value || '',
      tipo_servicio: el('reporte-tipo-servicio')?.value || '',
      estado_animal: el('reporte-estado-animal')?.value || '',
      aptitud: el('reporte-aptitud')?.value || '',
    };
    Object.entries(filtros).forEach(([clave, valor]) => {
      if (valor) map[clave] = valor;
    });
  }

  if (!code) return map;
  const permitidos = REPORT_FILTERS_CONFIG[code] || [];
  const salida = {};
  Object.entries(map).forEach(([clave, valor]) => {
    if (permitidos.includes(clave)) salida[clave] = valor;
  });
  return salida;
}

let _renderizandoListaReportes = false;
let _renderizarListaReportesPendiente = false;

async function renderizarListaReportes() {
  if (_renderizandoListaReportes) {
    _renderizarListaReportesPendiente = true;
    return;
  }

  _renderizandoListaReportes = true;
  try {
    await renderizarListaReportesInterna();
  } finally {
    _renderizandoListaReportes = false;
    if (_renderizarListaReportesPendiente) {
      _renderizarListaReportesPendiente = false;
      await renderizarListaReportes();
    }
  }
}

async function renderizarListaReportesInterna() {
  const contenedor = el('lista-reportes');
  if (!contenedor) return;

  const reportes = await Reportes.listar();
  contenedor.innerHTML = '';

  for (const reporte of reportes) {
    const parametros = obtenerParametrosReporte(reporte.code);
    // Compartir el PDF con WhatsApp/correo solo es posible si el equipo
    // expone la Web Share API con archivos (requiere HTTPS). Si no, se
    // queda el botón de siempre: exportar = descargar.
    const puedeCompartir = typeof PdfSimple !== 'undefined' && PdfSimple.puedeCompartir();
    const card = document.createElement('div');
    card.className = 'reporte-card';
    card.style.border = '1px solid #d9e5dc';
    card.style.borderRadius = '10px';
    card.style.padding = '14px';
    card.style.background = '#f7faf7';
    card.dataset.reporteCodigo = reporte.code;
    card.dataset.activo = '0';
    card.innerHTML = `
      <div class="reporte-parametros" style="display:none; margin:0 0 10px; padding:8px 10px; border:1px dashed #e8c397; background:#fffaf4; border-radius:10px;">
        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:8px;">
          ${parametros.map((parametro) => construirControlFiltro(parametro.clave, reporte.code)).join('') || '<span style="color:#6b7280; font-size:12px;">Sin filtros disponibles para este reporte.</span>'}
        </div>
      </div>
      <div style="display:flex; flex-direction:column; gap:8px;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px;">
          <div style="flex:1; min-width:0;">
            <strong style="display:block; font-size:15px; color:#234e35; line-height:1.3;">${escaparHtml(reporte.name)}</strong>
            <small style="display:block; color:#5d676d; margin-top:4px; line-height:1.4;">${escaparHtml(reporte.description || '')}</small>
          </div>
        </div>
        <div style="display:flex; gap:6px; flex-wrap:wrap; justify-content:flex-end; align-items:center; margin-top:2px;">
          <button type="button" class="boton-primario" data-vista-reporte="${escaparHtml(reporte.code)}" style="display:none; padding:6px 10px; font-size:11px; min-width:90px;">
            Vista previa
          </button>
          ${puedeCompartir ? `<button type="button" class="boton-primario" data-compartir-reporte="${escaparHtml(reporte.code)}" style="display:none; padding:6px 10px; font-size:11px; min-width:95px;">
            Compartir PDF
          </button>` : ''}
          <button type="button" class="boton-primario" data-generar-reporte="${escaparHtml(reporte.code)}" style="display:none; padding:6px 10px; font-size:11px; min-width:90px;${puedeCompartir ? ' background:#3f6b47; border-color:#3f6b47;' : ''}">
            ${puedeCompartir ? 'Descargar' : 'Exportar PDF'}
          </button>
          <button type="button" class="boton-primario" data-mostrar-reporte="${escaparHtml(reporte.code)}" style="padding:6px 10px; font-size:11px; min-width:70px; background:#8a5a2d; border-color:#8a5a2d;">
            Mostrar
          </button>
        </div>
      </div>
    `;

    await inicializarBuscadorAnimales(card);
    await poblarSelectsLoteEnReportes(card);

    card.addEventListener('click', () => {
      mostrarFiltrosReporte(reporte.code);
    });
    contenedor.appendChild(card);
  }

  contenedor.querySelectorAll('[data-mostrar-reporte]').forEach((boton) => {
    boton.addEventListener('click', (evento) => {
      evento.stopPropagation();
      const code = boton.dataset.mostrarReporte;
      mostrarFiltrosReporte(code);
    });
  });

  contenedor.querySelectorAll('[data-generar-reporte]').forEach((boton) => {
    boton.addEventListener('click', async (evento) => {
      evento.preventDefault();
      evento.stopPropagation();
      const code = boton.dataset.generarReporte;
      const clave = `generar:${code}`;
      if (REPORTES_ACCIONES_EN_CURSO.has(clave)) return;
      REPORTES_ACCIONES_EN_CURSO.add(clave);
      mostrarFiltrosReporte(code);
      try {
        const estado = el('estado-sync');
        if (estado) estado.textContent = 'Generando PDF del reporte...';
        const resultado = await Reportes.descargarPdf(code, obtenerFiltrosReporte(code));
        if (estado) estado.textContent = `PDF descargado: ${resultado.titulo} (${resultado.filas} fila(s), ${Math.round(resultado.bytes / 1024)} KB).`;
      } catch (error) {
        const estado = el('estado-sync');
        if (estado) estado.textContent = error.message || 'No se pudo generar el PDF.';
      } finally {
        REPORTES_ACCIONES_EN_CURSO.delete(clave);
      }
    });
  });

  // Compartir el PDF con las apps del teléfono (WhatsApp, correo, Drive…).
  contenedor.querySelectorAll('[data-compartir-reporte]').forEach((boton) => {
    boton.addEventListener('click', async (evento) => {
      evento.preventDefault();
      evento.stopPropagation();
      const code = boton.dataset.compartirReporte;
      const clave = `compartir:${code}`;
      if (REPORTES_ACCIONES_EN_CURSO.has(clave)) return;
      REPORTES_ACCIONES_EN_CURSO.add(clave);
      mostrarFiltrosReporte(code);
      try {
        const estado = el('estado-sync');
        if (estado) estado.textContent = 'Preparando el PDF para compartir...';
        const resultado = await Reportes.compartirPdf(code, obtenerFiltrosReporte(code));
        if (!estado) return;
        if (resultado.accion === 'compartido') {
          estado.textContent = `PDF compartido: ${resultado.titulo} (${resultado.filas} fila(s)).`;
        } else if (resultado.accion === 'cancelado') {
          estado.textContent = 'No se compartió el PDF. Podés descargarlo con el botón Descargar.';
        } else if (resultado.motivo === 'sin_soporte') {
          estado.textContent = `PDF descargado: ${resultado.titulo} (este equipo no permite compartir archivos).`;
        } else {
          estado.textContent = `PDF descargado: ${resultado.titulo} (no se pudo abrir el menú de compartir).`;
        }
      } catch (error) {
        const estado = el('estado-sync');
        if (estado) estado.textContent = error.message || 'No se pudo compartir el PDF.';
      } finally {
        REPORTES_ACCIONES_EN_CURSO.delete(clave);
      }
    });
  });

  contenedor.querySelectorAll('[data-vista-reporte]').forEach((boton) => {
    boton.addEventListener('click', async (evento) => {
      evento.preventDefault();
      evento.stopPropagation();
      const code = boton.dataset.vistaReporte;
      const clave = `vista:${code}`;
      if (REPORTES_ACCIONES_EN_CURSO.has(clave)) return;
      REPORTES_ACCIONES_EN_CURSO.add(clave);
      mostrarFiltrosReporte(code);
      try {
        const estado = el('estado-sync');
        if (estado) estado.textContent = 'Abriendo vista previa del reporte...';
        const resultado = await Reportes.abrirVistaPrevia(code, obtenerFiltrosReporte(code));
        if (estado) estado.textContent = `Vista previa: ${resultado.titulo} (${resultado.filas} fila(s)).`;
      } catch (error) {
        const estado = el('estado-sync');
        if (estado) estado.textContent = error.message || 'No se pudo abrir la vista previa.';
      } finally {
        REPORTES_ACCIONES_EN_CURSO.delete(clave);
      }
    });
  });
}

async function renderizarLista() {
  // Solo el hato activo (mestatus_id = 1). Los animales que salieron
  // del rebaño — descarte, muerte, venta, extravío o migración — se
  // consultan en la pestaña Bajas, así este módulo queda limpio con lo
  // que hoy está en producción.
  const todos = await Animales.listar();
  const animales = todos.filter((a) => Number(a.mestatus_id ?? 1) === 1);
  const sesion = await Db.obtenerSesion();
  const modoConsulta = sesion && sesion.modo_offline === 'consulta';
  const contenedor = el('lista-animales');
  if (!contenedor) return;
  if (formularioAbiertoEnLista(contenedor)) return;
  rescatarFormulariosMovidos(contenedor);
  contenedor.innerHTML = '';

  if (!animales.length) {
    contenedor.innerHTML = '<p class="texto-vacio">'
      + (todos.length
        ? 'No hay animales activos en este equipo. Los que están de baja se consultan en la pestaña <strong>Desincorporaciones</strong>.'
        : 'Todavía no hay animales registrados en este dispositivo. Toca "Registrar animal" para empezar.')
      + '</p>';
    if (el('buscador-animales-contador')) el('buscador-animales-contador').classList.add('oculto');
    return;
  }

  const sexoTexto = { 1: 'Hembra', 2: 'Macho' };
  const estadoTexto = {
    pendiente: 'Pendiente de sincronizar',
    sincronizado: 'Sincronizado',
    conflicto: 'En conflicto',
    error: 'Error al sincronizar',
  };

  // Fotos del hato: se resuelven de una sola pasada (copia local o URL
  // del servidor) antes de pintar, para no abrir la base por tarjeta.
  let fotosDelHato = new Map();
  if (typeof Fotos !== 'undefined') {
    try {
      fotosDelHato = await Fotos.mapaDeUrls(animales);
    } catch (e) {
      console.warn('[Animales] no se pudieron resolver las fotos:', e);
    }
  }

  for (const a of animales) {
    const tarjeta = document.createElement('article');
    tarjeta.className = 'chapeta chapeta--' + (a.estado_sync || 'pendiente');
    tarjeta.dataset.busqueda = [a.codigo_rgb_id_animal, a.nombre, a.nro_arete]
      .filter(Boolean).join(' ').toLowerCase();

    // Foto del animal: se muestra si ya está guardada en el equipo o si
    // hay señal para pedirla al servidor. Si no, la tarjeta se pinta
    // igual que siempre (sin hueco ni marcador roto).
    const foto = fotosDelHato.get(a.uuid_local);
    if (foto && foto.url) {
      tarjeta.classList.add('chapeta--con-foto');
    }

    tarjeta.innerHTML = `
      <div class="chapeta__ojal"></div>
      ${foto && foto.url
        ? `<img class="chapeta__foto" src="${escaparHtml(foto.url)}" alt="Foto de ${escaparHtml(a.nombre || a.codigo_rgb_id_animal || 'el animal')}" loading="lazy">`
        : ''}
      <div class="chapeta__codigo">${escaparHtml(a.codigo_rgb_id_animal || '')}${a.codigo_provisional ? ' <span class="chapeta__provisional">provisional</span>' : ''}</div>
      <div class="chapeta__nombre">${escaparHtml(a.nombre || '(sin nombre)')}</div>
      <div class="chapeta__detalle">${sexoTexto[a.sexo] || '—'} · Arete ${escaparHtml(a.nro_arete || '—')}</div>
      <div class="chapeta__pie">
        <div class="chapeta__estado">${estadoSyncTexto(a, estadoTexto)}</div>
        <button class="chapeta__boton-ficha" type="button" data-ficha-animal="${escaparHtml(a.uuid_local)}" aria-label="Ver ficha e historial de ${escaparHtml(a.nombre || a.codigo_rgb_id_animal || 'este animal')}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="M9 13h6"/><path d="M9 17h6"/></svg>Ficha</button>
        ${modoConsulta
          ? `<button class="chapeta__boton-ver" type="button" data-ver-registro="${escaparHtml(a.uuid_local)}" data-modulo="animales" aria-label="Ver detalle de ${escaparHtml(a.nombre || a.codigo_rgb_id_animal || 'animal')}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>Ver detalle</button>`
          : `<button class="chapeta__boton-editar" type="button" data-editar-animal="${escaparHtml(a.uuid_local)}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>Editar</button>`}
      </div>
    `;
    contenedor.appendChild(tarjeta);
  }

  contenedor.querySelectorAll('button[data-ver-registro][data-modulo="animales"]').forEach((boton) => {
    boton.addEventListener('click', () => abrirDetalleRegistro(boton.dataset.modulo, boton.dataset.verRegistro, boton));
  });

  // Si la foto apunta a un archivo que no está en el servidor (pasa con
  // miles de imágenes heredadas de la web), se quita la miniatura y la
  // tarjeta vuelve a su tamaño normal. Nunca se ve un icono roto.
  contenedor.querySelectorAll('img.chapeta__foto').forEach((imagen) => {
    imagen.addEventListener('error', () => {
      const tarjeta = imagen.closest('.chapeta');
      imagen.remove();
      if (tarjeta) tarjeta.classList.remove('chapeta--con-foto');
    });
  });

  contenedor.querySelectorAll('button[data-ficha-animal]').forEach((boton) => {
    boton.addEventListener('click', () => FichaAnimal.abrir(boton.dataset.fichaAnimal, boton));
  });

  contenedor.querySelectorAll('button[data-editar-animal]').forEach((boton) => {
    boton.addEventListener('click', () => abrirEdicionAnimal(boton.dataset.editarAnimal, boton.closest('.chapeta')));
  });

  filtrarListaAnimales();
}

const CAMPOS_DETALLE_REGISTRO = {
  animales: [
    {
      titulo: 'Identificación y características',
      campos: [
        ['codigo_rgb_id_animal', 'Código'],
        ['nro_arete', 'Número de arete'],
        ['nombre', 'Nombre'],
        ['sexo', 'Sexo'],
        ['f_nacimiento', 'Fecha de nacimiento'],
        ['f_ingreso', 'Fecha de ingreso'],
        ['peso_nacimiento', 'Peso al nacer'],
        ['servicio', 'Servicio'],
        ['nac_fundo', 'Nacido en el fundo'],
      ],
    },
    {
      titulo: 'Clasificación',
      campos: [
        ['aptitud', 'Aptitud', 'aptitudes'],
        ['mrazas_id', 'Raza', 'razas'],
        ['mlotes_id', 'Lote', 'lotes'],
        ['categoria_ingreso_id', 'Categoría de ingreso', 'categorias'],
        ['categoria_futura_id', 'Categoría futura', 'categorias'],
        ['estatus_animal', 'Estado del animal'],
        ['mestatus_id', 'Estatus'],
        ['mtipoganaderias_id', 'Tipo de ganadería'],
      ],
    },
  ],
  control_sanitario: [
    {
      titulo: 'Control sanitario',
      campos: [
        ['codigo_rgb_id_animal', 'Código del animal'],
        ['fecha_control', 'Fecha del control'],
        ['fecha_prox_control', 'Próximo control'],
        ['evento', 'Evento'],
        ['responsable', 'Responsable'],
        ['observaciones', 'Observaciones'],
        ['productos_aplicados', 'Productos aplicados'],
      ],
    },
  ],
  control_sanitario_lotes: [
    {
      titulo: 'Control por lote',
      campos: [
        ['codigo_mcs_lote', 'Código del control'],
        ['lote_nombre', 'Lote'],
        ['fecha_control', 'Fecha del control'],
        ['fecha_prox_control', 'Próximo control'],
        ['evento', 'Evento'],
        ['responsable', 'Responsable'],
        ['total_animales', 'Animales incluidos'],
        ['observaciones', 'Observaciones'],
      ],
    },
    {
      titulo: 'Productos aplicados',
      campos: [['productos_aplicados', 'Productos']],
    },
    {
      titulo: 'Animales incluidos',
      condicional: 'animalesLote',
      campos: [['_animales', 'Códigos']],
    },
  ],
  desincorporacion: [
    {
      titulo: 'Baja del animal',
      campos: [
        ['codigo_rgb_id_animal', 'Animal (código)'],
        ['tipo_desincorporacion', 'Tipo de baja'],
        ['f_desincorporacion', 'Fecha de la baja'],
        ['causa_descarte', 'Causa del descarte'],
        ['causa_mortalidad', 'Causa de la muerte'],
        ['observaciones', 'Observaciones'],
      ],
    },
  ],
  movimiento_rebano: [
    {
      titulo: 'Movimiento',
      campos: [
        ['tipo_movimiento', 'Tipo de movimiento'],
        ['fecha_movimiento', 'Fecha'],
        ['lote_origen_nombre', 'Lote de origen'],
        ['lote_destino_nombre', 'Lote de destino'],
        ['potrero_origen_nombre', 'Potrero de origen'],
        ['potrero_destino_nombre', 'Potrero de destino'],
        ['total_animales', 'Animales movidos'],
        ['motivo', 'Motivo'],
        ['observaciones', 'Observaciones'],
      ],
    },
    {
      titulo: 'Animales movidos',
      condicional: 'animalesMovimiento',
      campos: [['_animales', 'Códigos']],
    },
  ],
  produccion_leche: [
    {
      titulo: 'Producción diaria',
      campos: [
        ['codigo_rgb_id_animal_hembra', 'Hembra (código)'],
        ['fecha_produccion', 'Fecha'],
        ['cantidad_leche_m', 'Litros en la mañana'],
        ['cantidad_leche_t', 'Litros en la tarde'],
      ],
    },
  ],
  pesos: [
    {
      titulo: 'Pesaje',
      campos: [
        ['codigo_rgb_id_animal', 'Animal (código)'],
        ['fecha_pesaje', 'Fecha del pesaje'],
        ['peso', 'Peso'],
      ],
    },
  ],
  servicios: [
    {
      titulo: 'Datos del servicio',
      campos: [
        ['codigo_servicio', 'Código del servicio'],
        ['codigo_rgb_id_animal_hembra', 'Hembra'],
        ['codigo_rgb_id_animal_macho', 'Macho'],
        ['codigo_rgb_id_animal_dona', 'Donadora'],
        ['f_servicio', 'Fecha del servicio'],
        ['f_probable_palpacion', 'Fecha probable de palpación'],
        ['nro_servicio', 'Número de servicio'],
        ['mservicios_id', 'Tipo de servicio', 'tipos_servicio'],
        ['aptitud', 'Aptitud', 'aptitudes'],
        ['mrazas_id', 'Raza', 'razas'],
        ['observaciones', 'Observaciones'],
      ],
    },
    {
      titulo: 'Seguimiento reproductivo',
      campos: [
        ['medofisiologicos_id', 'Estado fisiológico', 'estados_fisiologicos'],
        ['f_palpacion', 'Fecha de palpación'],
        ['parto', 'Parto confirmado'],
        ['fecha_parto', 'Fecha del parto'],
        ['nro_terneros', 'Número de terneros'],
        ['dnp', 'DNP'],
        ['f_probable_parto', 'Fecha probable de parto'],
        ['f_ultimo_parto', 'Fecha del último parto'],
        ['f_secado', 'Fecha de secado'],
      ],
    },
  ],
  nacimientos: [
    {
      titulo: 'Ternero',
      campos: [
        ['codigo_ternero', 'Código del ternero'],
        ['id_ternero', 'Identificación'],
        ['sexo', 'Sexo'],
        ['f_parto', 'Fecha de nacimiento'],
        ['estatus_nacido', 'Resultado del nacimiento'],
        ['peso_nacimiento', 'Peso al nacer'],
      ],
    },
    {
      titulo: 'Terneros del parto',
      condicional: 'terneros',
      campos: [['_terneros', 'Detalle']],
    },
    {
      titulo: 'Progenitores',
      campos: [
        ['codigo_rgb_id_animal_hembra', 'Madre'],
        ['codigo_rgb_id_animal_macho', 'Padre'],
        ['codigo_rgb_id_animal_dona', 'Donadora'],
      ],
    },
    {
      titulo: 'Datos complementarios',
      campos: [
        ['servicio_codigo_origen', 'Servicio relacionado'],
        ['mtiposervicios_id', 'Tipo de servicio', 'tipos_servicio'],
        ['aptitud', 'Aptitud', 'aptitudes'],
        ['f_servicio', 'Fecha de servicio'],
        ['nro_parto', 'Número de parto'],
        ['produccion_leche', 'Producción de leche'],
        ['proposito', 'Propósito'],
        ['f_secado', 'Fecha de secado'],
        ['observaciones', 'Observaciones'],
      ],
    },
    {
      titulo: 'Ficha del animal',
      condicional: 'fichaAnimal',
      campos: [
        ['_ficha_animal_codigo', 'Código del animal'],
        ['_ficha_animal_nombre', 'Nombre'],
        ['_ficha_animal_arete', 'Número de arete'],
        ['_ficha_animal_sexo', 'Sexo'],
        ['_ficha_animal_aptitud', 'Aptitud'],
        ['_ficha_animal_raza', 'Raza'],
        ['_ficha_animal_estatus', 'Estado del animal'],
      ],
    },
  ],
};

let focoAnteriorDetalleRegistro = null;

function valorDetalleRegistro(registro, modulo, clave, catalogos) {
  const valor = registro[clave];
  if (clave === 'productos_aplicados') {
    if (!Array.isArray(valor) || !valor.length) return 'Sin productos aplicados';
    return valor.map((producto) => {
      const nombre = producto.producto_nombre || producto.nombre_producto || ('Producto ' + producto.mproductos_id);
      const detalles = producto.detalles ? ' (' + producto.detalles + ')' : '';
      return nombre + ' — ' + (producto.dosis || 'Sin dosis') + detalles;
    }).join('\n');
  }
  if (valor === null || valor === undefined || valor === '') return 'Sin información';

  if (clave === 'sexo') {
    return ({ 1: 'Hembra', 2: 'Macho' })[valor] || String(valor);
  }
  if (clave === 'estatus_nacido') {
    return ({ 1: 'Vivo', 2: 'Muerto' })[valor] || String(valor);
  }
  if (clave === 'produccion_leche') {
    return ({ 1: 'Sí, entra a producción de leche', 2: 'No' })[valor] || 'Sin información';
  }
  if (clave === 'proposito') {
    return ({ 1: 'Lactancia', 2: 'Producción de leche' })[valor] || 'Sin información';
  }
  if (clave === 'nac_fundo') {
    return [true, 1, '1', 't', 'true'].includes(valor) ? 'Sí' : 'No';
  }
  if (clave === 'parto') return Number(valor) === 1 ? 'Sí' : 'No';
  if (clave === 'estatus_animal') {
    return ({ 1: 'Activo', 2: 'Donador activo', 3: 'Donador', 4: 'Donadora activo', 5: 'Donadora' })[valor]
      || 'ID ' + String(valor);
  }
  if (clave === 'cantidad_leche_m' || clave === 'cantidad_leche_t') return String(valor) + ' L';
  if (clave === 'tipo_desincorporacion') {
    return Desincorporacion.nombreTipo(valor) || ('ID ' + String(valor));
  }
  if (clave === 'tipo_movimiento') {
    return MovimientoRebano.nombreTipo(valor) || String(valor);
  }
  // Las causas de baja se guardan como ID del catálogo cuando la baja se
  // registró en la web, y como texto cuando se registró en la app.
  if (clave === 'causa_descarte' || clave === 'causa_mortalidad') {
    const items = catalogos[clave === 'causa_descarte' ? 'causas_descarte' : 'causas_mortalidad'];
    return nombreCausaBaja(valor, items) || 'Sin información';
  }
  if (clave === 'peso' || clave === 'peso_nacimiento') return String(valor) + ' kg';
  if (clave.startsWith('f_') || clave.startsWith('fecha_')) return formatearFecha(valor) || 'Sin información';

  const grupoCatalogo = (CAMPOS_DETALLE_REGISTRO[modulo] || []).flatMap((grupo) => grupo.campos)
    .find((campo) => campo[0] === clave)?.[2];
  if (grupoCatalogo) {
    const item = catalogos[grupoCatalogo].find((opcion) => String(opcion.id) === String(valor));
    return item ? item.nombre : 'ID ' + String(valor);
  }

  return String(valor);
}

async function nombreCatalogo(tipoCatalogo, idValor) {
  if (!idValor) return '—';
  const items = await Db.obtenerCatalogo(tipoCatalogo);
  const item = items.find((i) => String(i.id) === String(idValor));
  return item ? item.nombre : 'ID ' + String(idValor);
}

async function abrirDetalleRegistro(modulo, uuidLocal, botonOrigen) {
  const registro = await Db.obtener(modulo, uuidLocal);
  if (!registro) return;

  const valoresExtra = {};

  if (modulo === 'nacimientos') {
    const servicios = await Db.obtenerTodos('servicios');
    const relacionado = registro.servicio_uuid_local
      ? servicios.find((servicio) => servicio.uuid_local === registro.servicio_uuid_local)
      : servicios.find((servicio) => Number(servicio.id) === Number(registro.mregservicios_id));
    registro.servicio_codigo_origen = relacionado
      ? (relacionado.codigo_servicio || 'Servicio pendiente')
      : (registro.mregservicios_id ? 'Servicio ID ' + registro.mregservicios_id : 'Sin servicio previo registrado');

    if (esBanderaActiva(registro.fichado) || registro.ficha_animal_uuid_local) {
      const animales = await Animales.listar();
      const fichaAnimal = registro.ficha_animal_uuid_local
        ? animales.find((a) => a.uuid_local === registro.ficha_animal_uuid_local)
        : animales.find((a) =>
            Number(a.status ?? 1) === 1
            && ((a.codigo_ternero && a.codigo_ternero === registro.codigo_ternero)
              || a.nacimiento_uuid_local === registro.uuid_local)
          );
      if (fichaAnimal) {
        valoresExtra.fichaAnimal = {
          _ficha_animal_codigo: fichaAnimal.codigo_rgb_id_animal || '—',
          _ficha_animal_nombre: fichaAnimal.nombre || '(sin nombre)',
          _ficha_animal_arete: fichaAnimal.nro_arete || '—',
          _ficha_animal_sexo: ({ 1: 'Hembra', 2: 'Macho' })[fichaAnimal.sexo] || '—',
          _ficha_animal_aptitud: await nombreCatalogo('aptitudes', fichaAnimal.aptitud),
          _ficha_animal_raza: await nombreCatalogo('razas', fichaAnimal.mrazas_id),
          _ficha_animal_estatus: ({ 1: 'Activo', 2: 'Donador activo', 3: 'Donador', 4: 'Donadora activo', 5: 'Donadora' })[fichaAnimal.estatus_animal] || '—',
          _ficha_animal_uuid_local: fichaAnimal.uuid_local,
        };
      }
    }

    // Detalle de las crías del parto (una o varias). Los registros
    // antiguos no tienen lista: se muestra la cría de los campos planos.
    const ternerosDetalle = Array.isArray(registro.terneros) && registro.terneros.length
      ? registro.terneros
      : [{
          sexo: registro.sexo,
          estatus_nacido: registro.estatus_nacido,
          peso_nacimiento: registro.peso_nacimiento,
          id_ternero: registro.id_ternero,
          aptitud: registro.aptitud,
        }];
    const aptitudesDetalle = await Db.obtenerCatalogo('aptitudes');
    const nombreAptitud = (id) => {
      const item = aptitudesDetalle.find((aptitud) => String(aptitud.id) === String(id));
      return item ? item.nombre : '';
    };
    valoresExtra.terneros = {
      _terneros: ternerosDetalle.map((ternero, indice) => {
        const partes = [
          Number(ternero.sexo) === 2 ? 'Macho' : 'Hembra',
          Number(ternero.estatus_nacido) === 2 ? 'Muerto' : 'Vivo',
          ternero.peso_nacimiento ? ternero.peso_nacimiento + ' kg' : 'sin peso',
          ternero.id_ternero ? 'ID ' + ternero.id_ternero : '',
          nombreAptitud(ternero.aptitud),
        ].filter(Boolean);
        return (indice + 1) + '. ' + partes.join(' · ');
      }).join('\n'),
    };
  }

  if (modulo === 'control_sanitario_lotes') {
    // El nombre del lote viaja en el catálogo local.
    const lotes = await Db.obtenerCatalogo('lotes');
    const lote = lotes.find((item) => String(item.id) === String(registro.mlotes_id));
    registro.lote_nombre = registro.lote_nombre || (lote ? lote.nombre : 'Lote ' + (registro.mlotes_id || '—'));

    const animales = Array.isArray(registro.animales) ? registro.animales : [];
    if (animales.length) {
      valoresExtra.animalesLote = {
        _animales: animales.length <= 40
          ? animales.join(', ')
          : animales.slice(0, 40).join(', ') + ' … (+' + (animales.length - 40) + ' más)',
      };
    }
  }

  if (modulo === 'movimiento_rebano') {
    // Los animales pueden venir como códigos (creado en el dispositivo)
    // o como objetos con el detalle (traído del servidor).
    const animales = Array.isArray(registro.animales) ? registro.animales : [];
    const codigos = animales.map((animal) => (typeof animal === 'string'
      ? animal
      : (animal && animal.codigo_rgb_id_animal) || '')).filter(Boolean);

    registro.total_animales = registro.total_animales || codigos.length;
    registro.lote_origen_nombre = registro.lote_origen_nombre || '';
    registro.lote_destino_nombre = registro.lote_destino_nombre || '';
    registro.potrero_origen_nombre = registro.potrero_origen_nombre || '';
    registro.potrero_destino_nombre = registro.potrero_destino_nombre || '';

    if (codigos.length) {
      valoresExtra.animalesMovimiento = {
        _animales: codigos.length <= 40
          ? codigos.join(', ')
          : codigos.slice(0, 40).join(', ') + ' … (+' + (codigos.length - 40) + ' más)',
      };
    }
  }

  const catalogosPorModulo = {
    animales: ['aptitudes', 'razas', 'lotes', 'categorias'],
    servicios: ['tipos_servicio', 'aptitudes', 'razas', 'estados_fisiologicos'],
    nacimientos: ['tipos_servicio', 'aptitudes'],
    desincorporacion: ['causas_descarte', 'causas_mortalidad'],
  };
  const catalogos = Object.fromEntries(await Promise.all(
    (catalogosPorModulo[modulo] || []).map(async (tipo) => [tipo, await Db.obtenerCatalogo(tipo)])
  ));
  const grupos = el('modal-detalle-registro-grupos');
  const camposCodigo = {
    control_sanitario: 'codigo_rgb_id_animal',
    control_sanitario_lotes: 'codigo_mcs_lote',
    desincorporacion: 'codigo_rgb_id_animal',
    movimiento_rebano: 'motivo',
    produccion_leche: 'codigo_rgb_id_animal_hembra',
    pesos: 'codigo_rgb_id_animal',
    servicios: 'codigo_servicio',
    nacimientos: 'codigo_ternero',
  };
  const titulosModulo = {
    control_sanitario: 'Control sanitario',
    control_sanitario_lotes: 'Control por lote',
    desincorporacion: 'Baja',
    movimiento_rebano: 'Movimiento de rebaño',
    produccion_leche: 'Producción de leche',
    pesos: 'Pesaje',
    servicios: 'Servicio reproductivo',
    nacimientos: 'Nacimiento',
  };
  const encabezado = modulo === 'animales'
    ? (registro.nombre || registro.codigo_rgb_id_animal || 'Animal')
      + (registro.nombre && registro.codigo_rgb_id_animal ? ' · ' + registro.codigo_rgb_id_animal : '')
    : titulosModulo[modulo] + ' · ' + (registro[camposCodigo[modulo]] || 'Registro');

  el('modal-detalle-registro-titulo').textContent = encabezado;
  el('modal-detalle-registro-contenido').textContent = 'Información disponible en este dispositivo';

  const gruposVisibles = (CAMPOS_DETALLE_REGISTRO[modulo] || []).filter((grupo) => {
    if (!grupo.condicional) return true;
    return !!valoresExtra[grupo.condicional];
  });

  grupos.innerHTML = gruposVisibles.map((grupo) => {
    const extra = grupo.condicional ? valoresExtra[grupo.condicional] : null;
    return `
      <section class="modal-detalle-registro-grupo">
        <h4>${escaparHtml(grupo.titulo)}</h4>
        <dl>
          ${grupo.campos.map(([clave, etiqueta]) => {
            const valor = extra && clave in extra
              ? extra[clave]
              : valorDetalleRegistro(registro, modulo, clave, catalogos);
            return `
              <div class="modal-detalle-registro-campo">
                <dt>${escaparHtml(etiqueta)}</dt>
                <dd>${escaparHtml(valor)}</dd>
              </div>
            `;
          }).join('')}
        </dl>
        ${grupo.titulo === 'Ficha del animal' && extra && extra._ficha_animal_uuid_local
          ? `<div style="margin-top:12px; text-align:right;">
               <button type="button" class="boton-secundario" id="boton-ver-ficha-animal" style="font-size:12px;">Ver ficha completa del animal</button>
             </div>`
          : ''}
      </section>
    `;
  }).join('');

  const botonVerFicha = document.getElementById('boton-ver-ficha-animal');
  if (botonVerFicha && valoresExtra.fichaAnimal && valoresExtra.fichaAnimal._ficha_animal_uuid_local) {
    botonVerFicha.addEventListener('click', () => {
      const uuidAnimal = valoresExtra.fichaAnimal._ficha_animal_uuid_local;
      cerrarDetalleRegistro();
      setTimeout(() => abrirDetalleRegistro('animales', uuidAnimal), 50);
    });
  }

  focoAnteriorDetalleRegistro = botonOrigen || document.activeElement;
  el('modal-detalle-registro-overlay').classList.remove('oculto');
  document.body.classList.add('modal-abierto');
  document.addEventListener('keydown', manejarTeclaDetalleRegistro);
  el('modal-detalle-registro-cerrar').focus();
}

function manejarTeclaDetalleRegistro(evento) {
  if (evento.key === 'Escape') cerrarDetalleRegistro();
}

function cerrarDetalleRegistro() {
  el('modal-detalle-registro-overlay').classList.add('oculto');
  document.body.classList.remove('modal-abierto');
  document.removeEventListener('keydown', manejarTeclaDetalleRegistro);
  if (focoAnteriorDetalleRegistro && focoAnteriorDetalleRegistro.isConnected) {
    focoAnteriorDetalleRegistro.focus();
  }
  focoAnteriorDetalleRegistro = null;
}

/**
 * Texto del estado de sincronización de una tarjeta. Si el último envío
 * falló, agrega el motivo real que devolvió el servidor (se guarda en
 * "ultimo_error" al sincronizar) — antes el mensaje quedaba guardado
 * pero no se mostraba en ninguna pantalla.
 */
function estadoSyncTexto(registro, estadoTexto) {
  const base = (estadoTexto && estadoTexto[registro.estado_sync]) || registro.estado_sync || '';
  if (registro.estado_sync !== 'error') return escaparHtml(base);

  const detalle = String(registro.ultimo_error || '').trim();
  if (!detalle) return escaparHtml(base);

  return escaparHtml(base) + '<span class="chapeta__error">' + escaparHtml(detalle) + '</span>';
}

/** Nombre del estado del animal (solo las bajas se muestran distintas). */
function estadoBajaTexto(animal) {
  const estado = Number(animal.mestatus_id ?? 1);
  if (estado === 1) return '';
  return Desincorporacion.nombreTipo(estado) || 'De baja';
}

/**
 * Filtra la lista de animales por la búsqueda. La lista ya viene solo
 * con los activos (ver renderizarLista), así que acá no hay nada más
 * que ocultar.
 */
function filtrarListaAnimales() {
  const buscador = el('buscador-animales');
  const contenedor = el('lista-animales');
  const contador = el('buscador-animales-contador');
  if (!buscador || !contenedor) return;

  const termino = buscador.value.trim().toLowerCase();
  const tarjetas = contenedor.querySelectorAll('.chapeta');
  let visibles = 0;

  tarjetas.forEach((tarjeta) => {
    const coincide = !termino || (tarjeta.dataset.busqueda || '').includes(termino);
    tarjeta.classList.toggle('oculto', !coincide);
    if (coincide) visibles++;
  });

  if (!contador) return;
  if (termino && tarjetas.length) {
    contador.textContent = visibles + ' de ' + tarjetas.length
      + (tarjetas.length === 1 ? ' animal' : ' animales');
    contador.classList.remove('oculto');
  } else {
    contador.classList.add('oculto');
  }
}

function etiquetaConflicto(modulo, registro) {
  if (!registro) return '(sin datos)';
  switch (modulo) {
    case 'animales':
      return escaparHtml(registro.nombre || '(sin nombre)')
        + (registro.codigo_rgb_id_animal ? ' — ' + escaparHtml(registro.codigo_rgb_id_animal) : '');
    case 'control_sanitario':
      return escaparHtml(registro.evento || '(evento)')
        + ' — ' + escaparHtml(registro.codigo_rgb_id_animal || '')
        + (registro.fecha_control ? ' (' + escaparHtml(formatearFecha(registro.fecha_control)) + ')' : '');
    case 'control_sanitario_lotes':
      return escaparHtml(registro.evento || '(evento)')
        + ' — ' + escaparHtml(registro.codigo_mcs_lote || 'RCS pendiente')
        + (registro.fecha_control ? ' (' + escaparHtml(formatearFecha(registro.fecha_control)) + ')' : '');
    case 'produccion_leche':
      return escaparHtml(registro.codigo_rgb_id_animal_hembra || '')
        + (registro.fecha_produccion ? ' — ' + escaparHtml(formatearFecha(registro.fecha_produccion)) : '')
        + (registro.cantidad_leche_m != null ? ' · ' + registro.cantidad_leche_m + 'L mañana' : '');
    case 'pesos':
      return escaparHtml(registro.codigo_rgb_id_animal || '')
        + (registro.fecha_pesaje ? ' — ' + escaparHtml(formatearFecha(registro.fecha_pesaje)) : '')
        + (registro.peso != null ? ' · ' + registro.peso + ' kg' : '');
    case 'servicios':
      return escaparHtml(registro.codigo_rgb_id_animal_hembra || '—') + ' × ' + escaparHtml(registro.codigo_rgb_id_animal_macho || '—')
        + (registro.f_servicio ? ' (' + escaparHtml(formatearFecha(registro.f_servicio)) + ')' : '');
    case 'nacimientos':
      return escaparHtml(registro.codigo_ternero || registro.id_ternero || '(cria)')
        + ' — Madre: ' + escaparHtml(registro.codigo_rgb_id_animal_hembra || '—')
        + (registro.f_parto ? ' (' + escaparHtml(formatearFecha(registro.f_parto)) + ')' : '');
    case 'desincorporacion':
      return escaparHtml(registro.codigo_rgb_id_animal || '')
        + ' — ' + escaparHtml(Desincorporacion.nombreTipo(registro.tipo_desincorporacion) || 'baja')
        + (registro.f_desincorporacion ? ' (' + escaparHtml(formatearFecha(registro.f_desincorporacion)) + ')' : '');
    case 'movimiento_rebano':
      return escaparHtml(MovimientoRebano.nombreTipo(registro.tipo_movimiento) || 'Movimiento')
        + ' — ' + escaparHtml(registro.motivo || '')
        + (registro.total_animales ? ' · ' + registro.total_animales + ' animal(es)' : '');
    default:
      return '(registro)';
  }
}

const NOMBRE_MODULO = {
  animales: 'un animal',
  control_sanitario: 'un control sanitario',
  control_sanitario_lotes: 'un control sanitario por lote',
  desincorporacion: 'una baja de animal',
  movimiento_rebano: 'un movimiento de rebaño',
  produccion_leche: 'un registro de producción de leche',
  pesos: 'un pesaje',
  servicios: 'un servicio',
  nacimientos: 'un nacimiento',
};

/* ---------------------------------------------------------------- */
/* Conflictos: comparación campo por campo                           */
/* ---------------------------------------------------------------- */

// Campos que no aportan nada a la comparación (técnicos o de control).
const CAMPOS_TECNICOS_CONFLICTO = new Set([
  'uuid_local', 'id', 'version', 'estado_sync', 'created', 'updated', 'mfundos_id',
  'user_create', 'user_modify', 'origen_dispositivo', 'ultimo_error',
  'codigo_provisional', 'nacimiento_uuid_local', 'ficha_animal_uuid_local',
]);

const ETIQUETAS_CAMPO_CONFLICTO = {
  nro_arete: 'Nro de arete',
  nombre: 'Nombre',
  sexo: 'Sexo',
  f_nacimiento: 'Fecha de nacimiento',
  fecha_produccion: 'Fecha',
  cantidad_leche_m: 'Litros (mañana)',
  cantidad_leche_t: 'Litros (tarde)',
  fecha_pesaje: 'Fecha del pesaje',
  peso: 'Peso (kg)',
  fecha_control: 'Fecha del control',
  evento: 'Evento',
  responsable: 'Responsable',
  producto: 'Producto',
  dosis: 'Dosis',
  f_servicio: 'Fecha del servicio',
  mservicios_id: 'Tipo de servicio',
  codigo_rgb_id_animal_macho: 'Macho',
  codigo_rgb_id_animal_dona: 'Donadora',
  codigo_rgb_id_animal_hembra: 'Hembra',
  codigo_rgb_id_animal: 'Animal',
  f_parto: 'Fecha del parto',
  tipo_desincorporacion: 'Tipo de baja',
  f_desincorporacion: 'Fecha de la baja',
  causa_descarte: 'Causa (descarte)',
  causa_mortalidad: 'Causa (muerte)',
  observaciones: 'Observaciones',
  motivo: 'Motivo',
  tipo_movimiento: 'Tipo de movimiento',
  mlotes_id: 'Lote',
  mpotreros_id: 'Potrero',
  categoria_ingreso_id: 'Categoría',
};

/** Parte una fecha ISO/SQL en día y hora (sin la hora si es medianoche). */
function parteFechaHoraConflicto(texto) {
  const partes = /^(\d{4}-\d{2}-\d{2})(?:[T ](\d{2}:\d{2})(?::(\d{2}))?)?/.exec(String(texto));
  if (!partes) return null;

  let hora = '';
  if (partes[2]) {
    const esMedianoche = partes[2] === '00:00' && (!partes[3] || partes[3] === '00');
    if (!esMedianoche) hora = partes[2];
  }

  return { dia: partes[1], hora };
}

/** Texto del valor, sin datos crudos cuando son fechas u objetos. */
function valorLegibleConflicto(valor) {
  if (valor === null || valor === undefined || valor === '') return '—';
  if (typeof valor === 'boolean') return valor ? 'Sí' : 'No';
  if (Array.isArray(valor)) return String(valor.length) + ' elemento(s)';
  if (typeof valor === 'object') return '(detalle)';

  const texto = String(valor);
  const fecha = parteFechaHoraConflicto(texto);
  if (fecha) return fecha.hora ? formatearFechaHora(texto) : formatearFecha(fecha.dia);
  return texto;
}

/** Normaliza un valor para decidir si realmente cambió. */
function valorComparableConflicto(valor) {
  if (valor === null || valor === undefined) return '';
  if (typeof valor === 'boolean') return valor ? '1' : '0';
  if (typeof valor === 'object') return JSON.stringify(valor);

  const texto = String(valor).trim();
  const fecha = parteFechaHoraConflicto(texto);
  if (fecha) {
    // La misma fecha con o sin "00:00:00" es el mismo dato; los
    // microsegundos que agrega el servidor tampoco son un cambio real.
    return fecha.hora ? fecha.dia + ' ' + fecha.hora : fecha.dia;
  }
  return texto;
}

/**
 * Campos que la persona editó y que en el servidor quedaron con otro
 * valor. Solo se comparan las claves que viajaron en "tu versión", así
 * no aparecen como cambiadas todas las columnas que el equipo no envía.
 */
function diferenciasConflicto(tuyo, servidor) {
  const filas = [];
  for (const campo of Object.keys(tuyo || {})) {
    if (CAMPOS_TECNICOS_CONFLICTO.has(campo)) continue;
    if (valorComparableConflicto(tuyo[campo]) === valorComparableConflicto(servidor ? servidor[campo] : undefined)) {
      continue;
    }
    filas.push({
      etiqueta: ETIQUETAS_CAMPO_CONFLICTO[campo] || campo.replace(/_/g, ' '),
      tuyo: valorLegibleConflicto(tuyo[campo]),
      servidor: valorLegibleConflicto(servidor ? servidor[campo] : undefined),
    });
  }
  return filas;
}

function htmlDiferenciasConflicto(tuyo, servidor) {
  const filas = diferenciasConflicto(tuyo, servidor);
  if (!filas.length) {
    return '<p class="conflicto__nota">El servidor cambió otros datos del registro, no los que vos editaste.</p>';
  }

  return '<div class="conflicto__campos">'
    + '<div class="conflicto__campo conflicto__campo--cabecera">'
    + '<span>Campo</span><span>Tu versión</span><span>Servidor</span>'
    + '</div>'
    + filas.map((fila) => '<div class="conflicto__campo">'
      + '<span class="conflicto__campo-nombre">' + escaparHtml(fila.etiqueta) + '</span>'
      + '<span class="conflicto__campo-valor">' + escaparHtml(fila.tuyo) + '</span>'
      + '<span class="conflicto__campo-valor">' + escaparHtml(fila.servidor) + '</span>'
      + '</div>').join('')
    + '</div>';
}

async function renderizarConflictos() {
  const conflictos = await Db.obtenerTodos('conflictos');
  const seccion = el('seccion-conflictos');
  const contenedor = el('lista-conflictos');
  contenedor.innerHTML = '';

  if (!conflictos.length) {
    seccion.classList.add('oculto');
    return;
  }
  seccion.classList.remove('oculto');

  for (const c of conflictos) {
    const modulo = c.modulo || 'animales';
    const tipo = c.tipo || 'version';
    const tarjeta = document.createElement('article');
    tarjeta.className = 'conflicto';

    if (tipo === 'arete_duplicado') {
      const areteEnConflicto = (c.tuyo && c.tuyo.nro_arete)
        || (c.servidor && c.servidor.nro_arete)
        || '—';
      tarjeta.innerHTML = `
        <p class="conflicto__titulo">Este animal no se pudo subir al servidor porque el número de arete <strong>${escaparHtml(areteEnConflicto)}</strong> ya está registrado en otro animal de este fundo:</p>
        <div class="conflicto__comparacion">
          <div><strong>Tu registro (pendiente)</strong><br>${etiquetaConflicto(modulo, c.tuyo)}</div>
          <div><strong>Animal existente en el servidor</strong><br>${etiquetaConflicto(modulo, c.servidor)}</div>
        </div>
        <div style="margin:10px 0 4px;font-size:12px;color:#5d676d;">
          Corrige el número de arete de tu registro para poder subirlo:
        </div>
        <input
          type="text"
          id="arete-nuevo-${escaparHtml(c.uuid_local)}"
          placeholder="Nuevo número de arete"
          autocomplete="off"
          style="width:100%;padding:8px 10px;border:1px solid #d0d5dd;border-radius:8px;font-size:13px;margin-bottom:8px;"
        >
        <div class="conflicto__acciones">
          <button data-accion="corregir_arete" data-uuid="${c.uuid_local}">Corregir arete y reintentar</button>
          <button data-accion="descartar_mio" data-uuid="${c.uuid_local}">Descartar mi registro</button>
        </div>
      `;
    } else {
      tarjeta.innerHTML = c.eliminado
        ? `
        <p class="conflicto__titulo">Querías eliminar ${escaparHtml(NOMBRE_MODULO[modulo] || modulo)}, pero el registro cambió en el servidor después de tu última sincronización:</p>
        <div class="conflicto__comparacion">
          <div><strong>Tu pedido</strong><br>Eliminar el registro</div>
          <div><strong>Versión del servidor</strong><br>${etiquetaConflicto(modulo, c.servidor)}</div>
        </div>
        <div class="conflicto__acciones">
          <button data-accion="mantener_mio" data-uuid="${c.uuid_local}">Eliminar igual</button>
          <button data-accion="usar_servidor" data-uuid="${c.uuid_local}">Conservar el del servidor</button>
        </div>
      `
        : `
        <p class="conflicto__titulo">Este registro (${escaparHtml(NOMBRE_MODULO[modulo] || modulo)}) cambió en el servidor mientras estabas sin conexión:</p>
        <p class="conflicto__resumen">${etiquetaConflicto(modulo, c.tuyo || c.servidor)}</p>
        ${c.servidor && c.servidor.updated
          ? '<p class="conflicto__nota">Editado en el servidor el ' + escaparHtml(formatearFechaHora(c.servidor.updated)) + '.</p>'
          : ''}
        ${htmlDiferenciasConflicto(c.tuyo, c.servidor)}
        <div class="conflicto__acciones">
          <button data-accion="mantener_mio" data-uuid="${c.uuid_local}">Mantener el mío</button>
          <button data-accion="usar_servidor" data-uuid="${c.uuid_local}">Usar el del servidor</button>
        </div>
      `;
    }
    contenedor.appendChild(tarjeta);
  }

  contenedor.querySelectorAll('button[data-accion]').forEach((boton) => {
    boton.addEventListener('click', () => resolverConflicto(boton.dataset.uuid, boton.dataset.accion));
  });
}

async function resolverConflicto(uuid, accion) {
  const conflicto = await Db.obtener('conflictos', uuid);
  if (!conflicto) return;
  const modulo = conflicto.modulo || 'animales';
  const tipo = conflicto.tipo || 'version';
  const registroLocal = await Db.obtener(modulo, uuid);

  if (tipo === 'arete_duplicado') {
    if (accion === 'descartar_mio') {
      if (registroLocal) await Db.eliminar(modulo, uuid);
      await Db.eliminar('outbox', uuid);
      await Db.eliminar('conflictos', uuid);
    } else if (accion === 'corregir_arete') {
      const input = document.getElementById('arete-nuevo-' + uuid);
      const nuevoArete = input ? input.value.trim() : '';

      if (!nuevoArete) {
        alert('Escribe el nuevo número de arete antes de continuar.');
        return;
      }

      const duplicado = await Animales.existeArete(nuevoArete, uuid);
      if (duplicado) {
        alert(
          'Ese arete también está en uso por '
          + (duplicado.codigo_rgb_id_animal || 'otro animal')
          + (duplicado.nombre ? ' — ' + duplicado.nombre : '')
          + '. Prueba con otro.'
        );
        return;
      }

      if (registroLocal) {
        registroLocal.nro_arete = nuevoArete;
        registroLocal.estado_sync = 'pendiente';
        await Db.guardar(modulo, registroLocal);
      }

      await Db.guardar('outbox', {
        uuid_local: uuid,
        modulo,
        version_base: null,
        datos: { ...(conflicto.tuyo || {}), nro_arete: nuevoArete },
        intentos: 0,
        creado: new Date().toISOString(),
      });

      await Db.eliminar('conflictos', uuid);
    } else {
      return;
    }

    await renderizarConflictos();
    await renderizarLista();
    await renderizarListaControlSanitario();
    await renderizarListaControlLote();
    await renderizarListaProduccionLeche();
    await renderizarListaPesos();
    await renderizarListaServicios();
    await renderizarListaNacimientos();
    await actualizarContadorPendientes();

    if (accion === 'corregir_arete' && navigator.onLine) Sync.sincronizar();
    return;
  }

  // Conflicto de una ANULACIÓN: se quería eliminar el registro y, en el
  // medio, alguien lo cambió desde la web u otro equipo.
  if (conflicto.eliminado) {
    if (accion === 'usar_servidor') {
      await Db.guardar(modulo, {
        ...conflicto.servidor,
        uuid_local: uuid,
        estado_sync: 'sincronizado',
      });
    } else {
      await Db.guardar('outbox', {
        uuid_local: uuid,
        modulo,
        version_base: conflicto.servidor.version,
        datos: {},
        eliminado: true,
        intentos: 0,
        creado: new Date().toISOString(),
      });
      if (registroLocal) {
        registroLocal.status = 0;
        registroLocal.estado_sync = 'pendiente';
        await Db.guardar(modulo, registroLocal);
      }
    }

    await Db.eliminar('conflictos', uuid);
    await renderizarConflictos();
    await renderizarLista();
    await renderizarListaControlSanitario();
    await renderizarListaControlLote();
    await renderizarListaProduccionLeche();
    await renderizarListaPesos();
    await renderizarListaServicios();
    await renderizarListaNacimientos();
    await actualizarContadorPendientes();

    if (accion !== 'usar_servidor' && navigator.onLine) Sync.sincronizar();
    return;
  }

  if (accion === 'usar_servidor') {
    await Db.guardar(modulo, {
      ...conflicto.servidor,
      uuid_local: uuid,
      estado_sync: 'sincronizado',
    });
  } else if (accion === 'mantener_mio') {
    await Db.guardar('outbox', {
      uuid_local: uuid,
      modulo,
      version_base: conflicto.servidor.version,
      datos: conflicto.tuyo,
      intentos: 0,
      creado: new Date().toISOString(),
    });
    if (registroLocal) {
      registroLocal.estado_sync = 'pendiente';
      await Db.guardar(modulo, registroLocal);
    }
  }

  await Db.eliminar('conflictos', uuid);
  await renderizarConflictos();
  await renderizarLista();
  await renderizarListaControlSanitario();
  await renderizarListaControlLote();
  await renderizarListaDesincorporacion();
  await renderizarListaMovimientoRebano();
  await renderizarListaProduccionLeche();
  await renderizarListaPesos();
  await renderizarListaServicios();
  await renderizarListaNacimientos();
  await actualizarContadorPendientes();

  if (accion === 'mantener_mio' && navigator.onLine) Sync.sincronizar();
}

async function actualizarContadorPendientes() {
  const outbox = await Db.obtenerTodos('outbox');
  const conflictos = await Db.obtenerTodos('conflictos');
  const total = outbox.length + conflictos.length;

  await actualizarIndicadorSincronizacion(total);
  await refrescarPendientesParaSalida();

  // Si el aviso de versión nueva está a la vista, se actualiza el texto
  // de pendientes y el rótulo del botón ("Sincronizar y actualizar").
  const aviso = el('aviso-actualizacion');
  if (aviso && !aviso.classList.contains('oculto') && _versionNuevaDisponible) {
    mostrarAvisoActualizacion();
  }
}


let _infoVersionFrontend = null;

// Versión con la que se abrió esta pantalla. Es la referencia para
// saber si el servidor ya tiene una mejora que este equipo todavía no
// está ejecutando.
let _versionAlAbrir = null;
let _hashAlAbrir = null;

// Versión nueva detectada y pendiente de aplicar (null = al día).
let _versionNuevaDisponible = null;
let _omitirAvisoDeVersion = null;
let temporizadorVerificacionVersion = null;

/** Normaliza "v4.32" / "4.32" a [4, 32] para poder comparar. */
function partesDeVersion(valor) {
  return String(valor || '')
    .replace(/^v/i, '')
    .split(/[^0-9]+/)
    .filter((p) => p !== '')
    .map((p) => parseInt(p, 10));
}

/** true si "nueva" es distinta de la actual (mejor o corrección). */
function versionEsDistinta(nueva, actual) {
  if (!nueva || !actual) return false;
  if (String(nueva) === String(actual)) return false;
  const a = partesDeVersion(actual);
  const b = partesDeVersion(nueva);
  if (!a.length || !b.length) return String(nueva) !== String(actual);
  // Distinta en cualquier posición (incluye parches y correcciones).
  return a.join('.') !== b.join('.');
}

async function cargarVersionFrontend() {
  const badge = el('etiqueta-version-frontend');
  if (!badge) return;

  try {
    const resp = await fetch('version.php?_=' + Date.now(), { cache: 'no-store' });
    if (!resp.ok) throw new Error('HTTP ' + resp.status);

    const texto = await resp.text();
    let datos = null;

    try {
      datos = JSON.parse(texto);
    } catch (e) {
      throw new Error('respuesta no es JSON');
    }

    if (!datos || typeof datos !== 'object') {
      throw new Error('respuesta vacía');
    }

    // Nuevo formato: version_app es un número legible (ej. "4.0").
    // Formato viejo: version es un hash (ej. "a1b2c3d4e5f6"). Soportamos
    // ambos para no romper si algún día alguien revierte version.php.
    const versionApp = datos.version_app || datos.version || null;

    if (!versionApp) throw new Error('respuesta sin versión');

    _infoVersionFrontend = {
      version_app: String(versionApp),
      fecha: datos.fecha || null,
      notas: Array.isArray(datos.notas) ? datos.notas : [],
      historial: Array.isArray(datos.historial) ? datos.historial : [],
      hash_tecnico: datos.hash_tecnico || null,
      ultimo_cambio: datos.ultimo_cambio || null,
      cargado_en: new Date().toISOString(),
    };

    // La primera lectura es la que define qué está ejecutando este
    // equipo. Las siguientes (verificación periódica) solo sirven para
    // detectar que el servidor cambió.
    if (_versionAlAbrir === null) {
      _versionAlAbrir = _infoVersionFrontend.version_app;
      _hashAlAbrir = _infoVersionFrontend.hash_tecnico;
    }

    badge.textContent = 'v' + String(versionApp);
    badge.title = 'Ver versión y novedades';
  } catch (e) {
    _infoVersionFrontend = _infoVersionFrontend || {
      version_app: '?',
      error: e && e.message ? e.message : 'error_desconocido',
    };
    console.warn('No se pudo cargar la versión del frontend:', e);
  }
}

/**
 * Pregunta al servidor si hay una versión distinta de la que está
 * corriendo esta pantalla. No recarga nada: solo muestra el aviso para
 * que la persona decida cuándo actualizar.
 */
async function verificarNuevaVersion() {
  if (!navigator.onLine) return;
  if (_versionAlAbrir === null) {
    // Todavía no se sabe qué versión se está ejecutando.
    await cargarVersionFrontend();
    if (_versionAlAbrir === null) return;
  }

  let datos = null;
  try {
    const resp = await fetch('version.php?_=' + Date.now(), { cache: 'no-store' });
    if (!resp.ok) return;
    datos = await resp.json();
  } catch (e) {
    return; // sin red o servidor caído: se reintenta en la próxima
  }

  if (!datos || typeof datos !== 'object') return;

  const versionServidor = String(datos.version_app || datos.version || '');
  if (!versionServidor) return;

  const hayVersionNueva = versionEsDistinta(versionServidor, _versionAlAbrir);
  const hayCorreccionNueva = !!datos.hash_tecnico && !!_hashAlAbrir
    && String(datos.hash_tecnico) !== String(_hashAlAbrir);

  if (!hayVersionNueva && !hayCorreccionNueva) {
    // El servidor volvió a la misma versión que corremos: se retira el aviso.
    if (_versionNuevaDisponible) {
      _versionNuevaDisponible = null;
      ocultarAvisoActualizacion();
    }
    return;
  }

  _versionNuevaDisponible = {
    version_app: versionServidor,
    fecha: datos.fecha || null,
    notas: Array.isArray(datos.notas) ? datos.notas : [],
    hash_tecnico: datos.hash_tecnico || null,
    solo_correccion: !hayVersionNueva,
  };

  mostrarAvisoActualizacion();
}

/** Pinta el aviso con la versión nueva y el estado de pendientes. */
function mostrarAvisoActualizacion() {
  const aviso = el('aviso-actualizacion');
  if (!aviso || !_versionNuevaDisponible) return;

  const nueva = _versionNuevaDisponible;

  // Si la persona ya dijo "Después" para ESTA versión, no se insiste
  // hasta que cambie de versión o reabra la app.
  if (_omitirAvisoDeVersion && _omitirAvisoDeVersion === nueva.version_app) return;

  const titulo = el('aviso-actualizacion-titulo');
  const detalle = el('aviso-actualizacion-detalle');
  const notas = el('aviso-actualizacion-notas');
  const pendientes = el('aviso-actualizacion-pendientes');
  const boton = el('boton-actualizar-app');
  const badge = el('etiqueta-version-frontend');

  if (titulo) {
    titulo.textContent = nueva.solo_correccion
      ? 'Hay una corrección nueva de la app'
      : 'Hay una versión nueva de la app (v' + nueva.version_app + ')';
  }

  if (detalle) {
    detalle.textContent = nueva.solo_correccion
      ? 'Se publicó una corrección para la v' + _versionAlAbrir
        + ' que este equipo todavía no aplicó. Tus datos guardados no se pierden.'
      : 'Usás la v' + _versionAlAbrir + ' y en el servidor está la v'
        + nueva.version_app + '. Tus datos guardados no se pierden.';
  }

  if (notas) {
    const lista = nueva.notas.slice(0, 2);
    notas.innerHTML = lista.map((n) => '<li>' + escaparHtml(n) + '</li>').join('');
    notas.classList.toggle('oculto', !lista.length);
  }

  const porSubir = Number(ultimoConteoPendientes) || 0;
  if (pendientes) {
    pendientes.textContent = porSubir > 0
      ? 'Antes de actualizar se enviarán ' + porSubir
        + (porSubir === 1 ? ' registro que falta subir.' : ' registros que faltan subir.')
      : '';
    pendientes.classList.toggle('oculto', porSubir === 0);
  }

  if (boton) {
    boton.disabled = false;
    boton.textContent = porSubir > 0 ? 'Sincronizar y actualizar' : 'Actualizar ahora';
  }

  if (badge) badge.classList.add('etiqueta-version-frontend--nueva');

  aviso.classList.remove('oculto');
}

function ocultarAvisoActualizacion() {
  const aviso = el('aviso-actualizacion');
  if (aviso) aviso.classList.add('oculto');
  const badge = el('etiqueta-version-frontend');
  if (badge) badge.classList.remove('etiqueta-version-frontend--nueva');
}

/**
 * Espera a que termine una sincronización en curso (o dispara una) para
 * que los registros del equipo suban antes de reemplazar la app.
 */
function sincronizarAntesDeActualizar() {
  return new Promise((resolve) => {
    let terminado = false;
    const finalizar = (resultado) => {
      if (terminado) return;
      terminado = true;
      window.removeEventListener('bovs:sync', alRecibirEvento);
      clearTimeout(tope);
      resolve(resultado);
    };
    const alRecibirEvento = (evento) => {
      const detalle = evento.detail || {};
      if (detalle.en_curso) return;
      finalizar(detalle);
    };
    const tope = setTimeout(() => finalizar(null), 45000);
    window.addEventListener('bovs:sync', alRecibirEvento);
    try {
      Sync.sincronizar();
    } catch (e) {
      finalizar(null);
    }
  });
}

/**
 * Aplica la actualización: pide al service worker la versión nueva y
 * recarga. Es la ÚNICA recarga que provoca la app por este motivo, y
 * siempre nace de un toque de la persona.
 */
async function aplicarActualizacion() {
  const boton = el('boton-actualizar-app');
  const omitir = el('boton-omitir-actualizacion');

  let recargando = false;
  const recargar = () => {
    if (recargando) return;
    recargando = true;
    window.location.reload();
  };

  if (boton) {
    boton.disabled = true;
    boton.textContent = 'Actualizando…';
  }
  if (omitir) omitir.disabled = true;

  if (!('serviceWorker' in navigator)) {
    recargar();
    return;
  }

  navigator.serviceWorker.addEventListener('controllerchange', recargar);

  let registro = null;
  try {
    registro = await navigator.serviceWorker.getRegistration();
  } catch (e) {
    registro = null;
  }

  if (registro) {
    try {
      await registro.update();
    } catch (e) {
      // sin red para el shell: abajo se recarga igual
    }
    if (registro.waiting) {
      registro.waiting.postMessage({ type: 'SKIP_WAITING' });
    }
    // Si el relevo del service worker no llega en unos segundos, se
    // recarga igual: los archivos se revalidan al abrir.
    setTimeout(recargar, 8000);
    return;
  }

  setTimeout(recargar, 300);
}

async function manejarClickActualizar() {
  const porSubir = Number(ultimoConteoPendientes) || 0;

  if (hayTrabajoEnPantalla()) {
    const seguir = window.confirm(
      'Hay un formulario abierto. Si actualizás ahora se cierra y se pierde lo que esté escrito sin guardar.\n\n¿Actualizar igual?'
    );
    if (!seguir) return;
  }

  if (porSubir > 0 && navigator.onLine) {
    const boton = el('boton-actualizar-app');
    if (boton) {
      boton.disabled = true;
      boton.textContent = 'Subiendo datos…';
    }
    await sincronizarAntesDeActualizar();
    await actualizarContadorPendientes();
    if (Number(ultimoConteoPendientes) > 0) {
      const seguir = window.confirm(
        'Quedan ' + ultimoConteoPendientes + ' registro(s) sin subir (revisá los conflictos o los errores). '
        + 'Se guardan en el equipo y se enviarán después de actualizar.\n\n¿Actualizar igual?'
      );
      if (!seguir) {
        mostrarAvisoActualizacion();
        return;
      }
    }
  }

  await aplicarActualizacion();
}

/** Programa la verificación periódica de versión. */
function programarVerificacionDeVersion() {
  if (temporizadorVerificacionVersion) clearInterval(temporizadorVerificacionVersion);

  const cada = Number(CONFIG.INTERVALO_VERSION_MS) || 5 * 60 * 1000;

  // Primera comprobación tardía: no compite con la carga inicial.
  setTimeout(() => verificarNuevaVersion(), 20000);

  temporizadorVerificacionVersion = setInterval(() => verificarNuevaVersion(), cada);

  // Al volver a la app o recuperar señal es cuando de verdad importa.
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) verificarNuevaVersion();
  });
  window.addEventListener('online', () => verificarNuevaVersion());
}


async function mostrarInfoVersion() {
  const badge = el('etiqueta-version-frontend');
  const versionActual = badge ? badge.textContent : 'v?';

  if (!_infoVersionFrontend) {
    await cargarVersionFrontend();
  }

  const info = _infoVersionFrontend || {};
  const bloques = [];

  // Bloque principal: número de versión + fecha de despliegue.
  bloques.push(
    '<div style="background:#f5f5f5; padding:12px 14px; border-radius:8px; margin-bottom:14px;">'
      + '<div style="font-weight:700; color:#1f2937; font-size:18px;">Versión '
      + escaparHtml(String(info.version_app || versionActual).replace(/^v/i, '')) + '</div>'
      + (info.fecha
          ? '<div style="color:#5d676d; font-size:12px; margin-top:4px;">Desplegada el '
            + escaparHtml(formatearFechaHora(info.fecha + 'T00:00:00')) + '</div>'
          : '')
      + (info.cargado_en
          ? '<div style="color:#5d676d; font-size:12px;">Verificada en este equipo: '
            + escaparHtml(formatearFechaHora(info.cargado_en)) + '</div>'
          : '')
    + '</div>'
  );

  // Bloque de novedades de esta versión.
  if (info.error) {
    bloques.push(
      '<div style="background:#fff3cd; border:1px solid #e6d89a; padding:10px 12px; border-radius:8px; font-size:12px; color:#6b5a1e;">'
        + '<strong>No se pudo verificar contra el servidor.</strong><br>'
        + 'Motivo: ' + escaparHtml(info.error) + '.<br>'
        + 'Si estás sin conexión, es normal. La app sigue funcionando con la versión cacheada en este equipo.'
      + '</div>'
    );
  } else if (info.notas && info.notas.length) {
    bloques.push(
      '<div style="margin-bottom:14px;">'
        + '<div style="font-size:11px; font-weight:800; color:#234e35; text-transform:uppercase; letter-spacing:0.06em; margin-bottom:8px;">'
          + 'Novedades en esta versión'
        + '</div>'
        + '<ul style="margin:0; padding-left:18px; font-size:13px; color:#333; line-height:1.6;">'
          + info.notas.map(function (n) {
              return '<li style="margin-bottom:4px;">' + escaparHtml(String(n)) + '</li>';
            }).join('')
        + '</ul>'
      + '</div>'
    );
  } else {
    bloques.push(
      '<div style="background:#e7f0fa; border:1px solid #b9d3ec; padding:10px 12px; border-radius:8px; font-size:12px; color:#1e4976; margin-bottom:14px;">'
        + 'Esta versión no tiene notas registradas. Tu administrador puede agregarlas en el archivo <code>versiones.json</code>.'
      + '</div>'
    );
  }

  // Bloque de historial (últimas versiones anteriores, si las hay).
  if (info.historial && info.historial.length) {
    bloques.push(
      '<div style="margin-bottom:14px;">'
        + '<div style="font-size:11px; font-weight:800; color:#234e35; text-transform:uppercase; letter-spacing:0.06em; margin-bottom:6px;">'
          + 'Versiones anteriores'
        + '</div>'
        + '<div style="font-size:12px; color:#333; line-height:1.8;">'
          + info.historial.map(function (h) {
              const fecha = h.fecha ? formatearFechaHora(h.fecha + 'T00:00:00') : '—';
              return '<div style="display:flex; justify-content:space-between; gap:12px;">'
                + '<span>v' + escaparHtml(String(h.version || '?')) + '</span>'
                + '<span style="color:#6b6455;">' + escaparHtml(fecha) + '</span>'
                + '</div>';
            }).join('')
        + '</div>'
      + '</div>'
    );
  }

  // Bloque de estado técnico — compacto, con hash al final.
  const sw = navigator.serviceWorker && navigator.serviceWorker.controller
    ? 'Activo'
    : navigator.serviceWorker
      ? 'Registrado'
      : 'No soportado';

  bloques.push(
    '<div style="border-top:1px solid #e5e7eb; padding-top:12px; margin-top:4px;">'
      + '<div style="font-size:11px; font-weight:800; color:#234e35; text-transform:uppercase; letter-spacing:0.06em; margin-bottom:6px;">'
        + 'Estado técnico'
      + '</div>'
      + '<div style="font-size:11px; color:#5d676d; line-height:1.7;">'
        + 'Service Worker: <strong>' + escaparHtml(sw) + '</strong><br>'
        + 'Conectividad: <strong>' + (navigator.onLine ? 'En línea' : 'Sin conexión') + '</strong>'
        + (info.hash_tecnico
            ? '<br>Código de compilación: <code style="font-size:11px;">' + escaparHtml(info.hash_tecnico) + '</code>'
            : '')
      + '</div>'
    + '</div>'
  );

  mostrarModalAviso('Versión del frontend', bloques.join(''), { textoBoton: 'Cerrar' });
}

function formatearFechaHora(valor) {
  if (!valor) return '—';
  const d = new Date(valor);
  if (isNaN(d.getTime())) return String(valor);
  const dia = String(d.getDate()).padStart(2, '0');
  const mes = String(d.getMonth() + 1).padStart(2, '0');
  const anio = d.getFullYear();
  const hora = String(d.getHours()).padStart(2, '0');
  const min = String(d.getMinutes()).padStart(2, '0');
  return dia + '-' + mes + '-' + anio + ' ' + hora + ':' + min;
}

// ---------------------------------------------------------------------
// Catálogos
// ---------------------------------------------------------------------

async function poblarSelectsCatalogos() {
  await poblarSelect('campo-raza', await Db.obtenerCatalogo('razas'), 'Selecciona…');
  await poblarSelect('campo-aptitud', await Db.obtenerCatalogo('aptitudes'), 'Selecciona…');
  await poblarSelect('campo-lote', await Db.obtenerCatalogo('lotes'), 'Sin asignar');
  await poblarSelectsCategoriaPorSexo();
  await poblarSelect('servicios-aptitud', await Db.obtenerCatalogo('aptitudes'), 'Selecciona...');
  await poblarSelect('servicios-raza', await Db.obtenerCatalogo('razas'), 'Selecciona...');
  await poblarSelect('servicios-tipo', await Db.obtenerCatalogo('tipos_servicio'), 'Selecciona...');
  await poblarSelect('servicios-estado-fisiologico', await Db.obtenerCatalogo('estados_fisiologicos'), 'Sin determinar');
  await poblarSelect('nacimientos-tipo-servicio', await Db.obtenerCatalogo('tipos_servicio'), 'Selecciona...');
  await poblarSelectAnimalesControlSanitario();
  await poblarSelectAnimalesProduccionLeche();
  await poblarSelectAnimalesPesos();
  await poblarSelectAnimalesServicios();
  await poblarSelectAnimalesNacimientos();
}

async function poblarSelectsCategoriaPorSexo() {
  const sexoSeleccionado = el('campo-sexo') ? el('campo-sexo').value : '';
  const todas = await Db.obtenerCatalogo('categorias');
  const filtradas = sexoSeleccionado
    ? todas.filter((categoria) => String(categoria.sexo) === String(sexoSeleccionado))
    : todas;

  await poblarSelect('campo-categoria-ingreso', filtradas, 'Sin categoria');
  await poblarSelect('campo-categoria-futura', filtradas, 'Sin categoria');
}

async function poblarSelectAnimalesControlSanitario() {
  const animales = await Animales.listar();
  BuscadorAnimal.poblar('cs-animal', animales, 'Selecciona...');
}

async function poblarSelectAnimalesProduccionLeche() {
  // Producción de leche es solo de hembras (sexo 1 en este sistema).
  const animales = (await Animales.listar()).filter((animal) => String(animal.sexo) === '1');
  BuscadorAnimal.poblar('pl-animal', animales, 'Selecciona...');
}

async function poblarSelectAnimalesPesos() {
  const animales = await Animales.listar();
  BuscadorAnimal.poblar('pesos-animal', animales, 'Selecciona...');
}

async function poblarSelectAnimalesServicios() {
  const animales = await Animales.listar();
  const hembras = animales.filter((animal) => String(animal.sexo) === '1' && Number(animal.mestatus_id ?? 1) === 1);
  const tipoServicio = Number(el('servicios-tipo')?.value || 0);
  let machos = animales.filter((animal) => String(animal.sexo) === '2' && Number(animal.mestatus_id ?? 1) === 1);
  if (tipoServicio === 1) machos = machos.filter((animal) => Number(animal.estatus_animal) === 1);
  else if (tipoServicio === 2) machos = machos.filter((animal) => [2, 3].includes(Number(animal.estatus_animal)));
  else if (tipoServicio === 3) machos = [];
  const donadoras = hembras.filter((animal) => [4, 5].includes(Number(animal.estatus_animal)));

  poblarSelectAnimalesDesdeLista('servicios-hembra', hembras, 'Selecciona...');
  poblarSelectAnimalesDesdeLista('servicios-macho', machos, 'Selecciona...');
  poblarSelectAnimalesDesdeLista('servicios-dona', donadoras, 'Selecciona donadora...');
}

function mesesCompletosDesde(valorFecha) {
  const fechaTexto = typeof valorFecha === 'object' && valorFecha && valorFecha.date
    ? valorFecha.date
    : String(valorFecha || '').slice(0, 10);
  const partes = fechaTexto.split('-').map(Number);
  if (partes.length !== 3 || partes.some((parte) => !parte)) return null;

  const hoy = new Date();
  let meses = (hoy.getFullYear() - partes[0]) * 12 + hoy.getMonth() + 1 - partes[1];
  if (hoy.getDate() < partes[2]) meses--;
  return meses;
}

function animalesPorCodigo(animales, codigo) {
  return animales.find((animal) => animal.codigo_rgb_id_animal === codigo) || null;
}

function esAnimalActivo(animal) {
  return !!animal
    && Number(animal.status ?? 1) === 1
    && Number(animal.mestatus_id ?? 1) === 1;
}

async function numeroServicioParaHembra(codigoHembra, mostrarError = true) {
  const mensaje = el('form-servicios-mensaje');
  const limpiarMensaje = () => {
    if (mostrarError) mensaje.textContent = '';
  };
  const fallar = (texto) => {
    if (mostrarError) mensaje.textContent = texto;
    return null;
  };

  if (!codigoHembra) return fallar('Selecciona la hembra del servicio.');

  const animales = await Animales.listar();
  const hembra = animalesPorCodigo(animales, codigoHembra);
  if (!esAnimalActivo(hembra) || Number(hembra.sexo) !== 1) {
    return fallar('La hembra no está activa o no pertenece a los animales disponibles de este fundo. Sincroniza los datos e inténtalo de nuevo.');
  }

  const edadMeses = mesesCompletosDesde(hembra.f_nacimiento);
  if (edadMeses === null || edadMeses < 12) {
    return fallar('La hembra debe tener al menos 12 meses y una fecha de nacimiento registrada.');
  }

  const servicios = await Servicios.listar();
  const activos = servicios
    .filter((servicio) => servicio.codigo_rgb_id_animal_hembra === codigoHembra
      && (Number(servicio.estatus_ciclo) === 1
        || (Number(servicio.version || 0) === 0 && servicio.estado_sync !== 'conflicto')))
    .sort((a, b) => String(b.f_servicio || b.created || '').localeCompare(String(a.f_servicio || a.created || '')));

  limpiarMensaje();
  if (!activos.length) return 1;

  const servicioActivo = activos[0];
  if (![4, 5].includes(Number(servicioActivo.medofisiologicos_id))) {
    return fallar('La hembra ya tiene un servicio activo. Registra el resultado de palpación antes de crear otro.');
  }

  return Number(servicioActivo.nro_servicio || 0) + 1;
}

function fechaProbablePalpacion(fechaServicio, dias) {
  if (!fechaServicio || !Number.isInteger(dias) || dias < 1) return '';
  const [anio, mes, dia] = fechaServicio.split('-').map(Number);
  if (!anio || !mes || !dia) return '';
  const fecha = new Date(anio, mes - 1, dia);
  fecha.setDate(fecha.getDate() + dias);
  return [fecha.getFullYear(), String(fecha.getMonth() + 1).padStart(2, '0'), String(fecha.getDate()).padStart(2, '0')].join('-');
}

async function actualizarDatosDerivadosServicio() {
  const codigoHembra = el('servicios-hembra').value;
  if (!codigoHembra) {
    el('servicios-nro').value = '';
    await calcularFechaProbablePalpacionServicio();
    return;
  }

  const numero = await numeroServicioParaHembra(codigoHembra);
  el('servicios-nro').value = numero || '';

  await calcularFechaProbablePalpacionServicio();
}

async function calcularFechaProbablePalpacionServicio() {
  const periodo = await Db.obtenerCatalogo('parametros_servicios');
  const dias = Number(periodo && periodo.periodo_palpacion_dias);
  if (el('servicios-fecha').value && dias > 0) {
    el('servicios-fpp').value = fechaProbablePalpacion(el('servicios-fecha').value, dias);
  } else {
    el('servicios-fpp').value = '';
  }
}

async function validarNuevoServicioOffline(datosFormulario) {
  const esTransferencia = Number(datosFormulario.mservicios_id) === 3;
  if (!datosFormulario.codigo_rgb_id_animal_hembra || (!esTransferencia && !datosFormulario.codigo_rgb_id_animal_macho)
    || !datosFormulario.f_servicio || !datosFormulario.mservicios_id
    || !datosFormulario.aptitud || !datosFormulario.mrazas_id) {
    return esTransferencia
      ? 'Completa receptora, donadora, fecha, tipo de servicio, aptitud y raza.'
      : 'Completa hembra, macho, fecha, tipo de servicio, aptitud y raza.';
  }
  if (esTransferencia && !datosFormulario.codigo_rgb_id_animal_dona) {
    return 'La transferencia de embriones requiere una donadora.';
  }

  const hoy = new Date();
  const hoyISO = [hoy.getFullYear(), String(hoy.getMonth() + 1).padStart(2, '0'), String(hoy.getDate()).padStart(2, '0')].join('-');
  if (datosFormulario.f_servicio > hoyISO) return 'La fecha del servicio no puede ser futura.';

  const dias = Number((await Db.obtenerCatalogo('parametros_servicios'))?.periodo_palpacion_dias || 0);
  if (!Number.isInteger(dias) || dias < 1) {
    return 'No está descargado el período de palpación de este fundo. Conéctate y sincroniza antes de registrar el servicio.';
  }
  datosFormulario.f_probable_palpacion = fechaProbablePalpacion(datosFormulario.f_servicio, dias);
  if (!datosFormulario.f_probable_palpacion) return 'No se pudo calcular la fecha probable de palpación.';

  const numero = await numeroServicioParaHembra(datosFormulario.codigo_rgb_id_animal_hembra);
  if (!numero) return el('form-servicios-mensaje').textContent || 'La hembra no es elegible para un nuevo servicio.';
  datosFormulario.nro_servicio = numero;
  el('servicios-nro').value = String(numero);
  el('servicios-fpp').value = datosFormulario.f_probable_palpacion;

  const animales = await Animales.listar();
  const tipoServicioValido = (await Db.obtenerCatalogo('tipos_servicio'))
    .some((tipo) => String(tipo.id) === String(datosFormulario.mservicios_id));
  if (!tipoServicioValido) return 'El tipo de servicio ya no está activo en el catálogo descargado.';

  if (!esTransferencia) {
    const macho = animalesPorCodigo(animales, datosFormulario.codigo_rgb_id_animal_macho);
    const estatusMachoValido = Number(datosFormulario.mservicios_id) === 1
      ? Number(macho && macho.estatus_animal) === 1
      : Number(datosFormulario.mservicios_id) === 2
        ? [2, 3].includes(Number(macho && macho.estatus_animal))
        : true;
    if (!esAnimalActivo(macho) || Number(macho.sexo) !== 2 || !estatusMachoValido) {
      return 'El macho no está activo o no cumple el estatus requerido para el tipo de servicio seleccionado.';
    }
  }

  if (esTransferencia) {
    if (datosFormulario.codigo_rgb_id_animal_dona === datosFormulario.codigo_rgb_id_animal_hembra) {
      return 'La donadora y la hembra receptora deben ser animales distintos.';
    }
    const donadora = animalesPorCodigo(animales, datosFormulario.codigo_rgb_id_animal_dona);
    if (!esAnimalActivo(donadora) || Number(donadora.sexo) !== 1 || ![4, 5].includes(Number(donadora.estatus_animal))) {
      return 'La donadora debe ser una hembra activa clasificada como donadora en este fundo.';
    }
  }

  return '';
}

async function poblarSelectAnimalesNacimientos() {
  const animales = await Animales.listar();
  const hembras = animales.filter((animal) => String(animal.sexo) === '1');
  const machos = animales.filter((animal) => String(animal.sexo) === '2');
  /* La donadora es una hembra con estatus 4/5 (catálogo mestatusanimales) */
  const donadoras = hembras.filter((animal) => [4, 5].includes(Number(animal.estatus_animal)));

  poblarSelectAnimalesDesdeLista('nacimientos-hembra', hembras, 'Selecciona...');
  poblarSelectAnimalesDesdeLista('nacimientos-macho', machos, 'Sin especificar');
  poblarSelectAnimalesDesdeLista('nacimientos-dona', donadoras, 'Sin donadora');
}

function poblarSelectAnimalesDesdeLista(idCampo, animales, textoVacio) {
  BuscadorAnimal.poblar(idCampo, animales, textoVacio);
}

// ---------------------------------------------------------------------
// Terneros del parto (nacimientos)
//
// Igual que la web: un parto puede tener una o varias crías, cada una
// con su estado al nacer, sexo, peso, ID/arete y aptitud. En el
// registro local el primer ternero se refleja en los campos planos
// (sexo, peso_nacimiento, estatus_nacido, aptitud, id_ternero) para
// que la ficha del ternero, la lista y la API sigan funcionando igual
// que antes.
// ---------------------------------------------------------------------

const MAX_TERNEROS_PARTO = 6;

async function agregarFilaTernero(datos = {}) {
  const lista = el('nacimientos-terneros');
  if (!lista) return;

  if (lista.querySelectorAll('.ternero-fila').length >= MAX_TERNEROS_PARTO) {
    el('form-nacimientos-mensaje').textContent = 'Un parto no puede tener más de ' + MAX_TERNEROS_PARTO + ' terneros.';
    return;
  }

  const aptitudes = await Db.obtenerCatalogo('aptitudes');
  const opcionesAptitud = aptitudes.map((aptitud) =>
    '<option value="' + escaparHtml(String(aptitud.id)) + '">' + escaparHtml(aptitud.nombre) + '</option>'
  ).join('');

  const fila = document.createElement('div');
  fila.className = 'ternero-fila';
  fila.innerHTML =
    '<div class="ternero-fila__cabecera">'
    + '<span class="ternero-fila__titulo"></span>'
    + '<button type="button" class="ternero-fila__quitar" data-quitar-ternero>Quitar</button>'
    + '</div>'
    + '<div class="ternero-fila__campos">'
    + '<div class="campo"><label>¿Nació?</label><select data-ternero="estatus_nacido">'
    + '<option value="1">Vivo</option><option value="2">Muerto</option></select></div>'
    + '<div class="campo"><label>Sexo</label><select data-ternero="sexo">'
    + '<option value="1">Hembra</option><option value="2">Macho</option></select></div>'
    + '<div class="campo"><label>Peso al nacer (kg)</label>'
    + '<input type="text" inputmode="decimal" data-ternero="peso_nacimiento" placeholder="Ej: 32"></div>'
    + '<div class="campo"><label>ID / Arete</label>'
    + '<input type="text" data-ternero="id_ternero" maxlength="20" placeholder="Opcional"></div>'
    + '<div class="campo ternero-fila__ancho"><label>Aptitud</label><select data-ternero="aptitud">'
    + '<option value="">Selecciona...</option>' + opcionesAptitud + '</select></div>'
    + '</div>';

  const campo = (nombre) => fila.querySelector('[data-ternero="' + nombre + '"]');
  campo('estatus_nacido').value = String(Number(datos.estatus_nacido) === 2 ? 2 : 1);
  campo('sexo').value = String(Number(datos.sexo) === 2 ? 2 : 1);
  campo('peso_nacimiento').value = datos.peso_nacimiento != null ? String(datos.peso_nacimiento) : '';
  campo('id_ternero').value = datos.id_ternero || '';
  campo('aptitud').value = datos.aptitud ? String(datos.aptitud) : '';

  fila.querySelector('[data-quitar-ternero]').addEventListener('click', () => {
    if (lista.querySelectorAll('.ternero-fila').length <= 1) {
      el('form-nacimientos-mensaje').textContent = 'El parto debe tener al menos un ternero.';
      return;
    }
    fila.remove();
    renumerarFilasTerneros();
  });

  lista.appendChild(fila);
  renumerarFilasTerneros();
}

function renumerarFilasTerneros() {
  const lista = el('nacimientos-terneros');
  if (!lista) return;
  lista.querySelectorAll('.ternero-fila').forEach((fila, indice) => {
    const titulo = fila.querySelector('.ternero-fila__titulo');
    if (titulo) titulo.textContent = 'Ternero #' + (indice + 1);
  });
}

async function poblarTernerosFormulario(terneros) {
  const lista = el('nacimientos-terneros');
  if (!lista) return;

  const items = Array.isArray(terneros) && terneros.length ? terneros : [{}];
  lista.innerHTML = '';
  for (const item of items) {
    await agregarFilaTernero(item);
  }
}

function leerTernerosFormulario() {
  const lista = el('nacimientos-terneros');
  if (!lista) return [];

  return Array.from(lista.querySelectorAll('.ternero-fila')).map((fila) => {
    const campo = (nombre) => fila.querySelector('[data-ternero="' + nombre + '"]');
    return {
      estatus_nacido: Number(campo('estatus_nacido').value) || 0,
      sexo: Number(campo('sexo').value) || 0,
      peso_nacimiento: campo('peso_nacimiento').value.trim(),
      id_ternero: campo('id_ternero').value.trim().toUpperCase(),
      aptitud: Number(campo('aptitud').value) || 0,
    };
  });
}

function validarTernerosFormulario(terneros) {
  if (!terneros.length) return 'Agrega al menos un ternero al parto.';

  const idsVistos = new Set();
  for (let indice = 0; indice < terneros.length; indice += 1) {
    const ternero = terneros[indice];
    const nro = indice + 1;

    if (![1, 2].includes(ternero.sexo)) return 'Selecciona el sexo del ternero #' + nro + '.';
    if (![1, 2].includes(ternero.estatus_nacido)) return 'Indica si el ternero #' + nro + ' nació vivo o muerto.';

    if (ternero.peso_nacimiento === '') return 'Registra el peso al nacer del ternero #' + nro + '.';
    const peso = Number(String(ternero.peso_nacimiento).replace(',', '.'));
    if (!Number.isFinite(peso) || peso <= 0 || peso > 200) {
      return 'El peso del ternero #' + nro + ' debe estar entre 1 y 200 kg.';
    }
    ternero.peso_nacimiento = String(peso);

    if (!ternero.aptitud) return 'Selecciona la aptitud del ternero #' + nro + '.';

    if (ternero.id_ternero !== '') {
      if (ternero.id_ternero.length > 20) {
        return 'El ID/arete del ternero #' + nro + ' no puede tener más de 20 caracteres.';
      }
      if (idsVistos.has(ternero.id_ternero)) {
        return 'El ID/arete ' + ternero.id_ternero + ' está repetido entre los terneros de este parto.';
      }
      idsVistos.add(ternero.id_ternero);
    }
  }

  return '';
}

function poblarSelect(idSelect, items, textoVacio) {
  const select = el(idSelect);
  if (!select) return;

  const valorPrevio = select.value;
  select.innerHTML = '';

  const opcionVacia = document.createElement('option');
  opcionVacia.value = '';
  opcionVacia.textContent = textoVacio;
  select.appendChild(opcionVacia);

  for (const item of items) {
    const opcion = document.createElement('option');
    opcion.value = String(item.id);
    opcion.textContent = item.nombre;
    select.appendChild(opcion);
  }

  if (valorPrevio && items.some((i) => String(i.id) === valorPrevio)) {
    select.value = valorPrevio;
  }

  if (!items.length) {
    select.innerHTML = '<option value="">' + (navigator.onLine ? 'Sin datos todavía — sincroniza' : 'Sin conexión: conéctate al menos una vez') + '</option>';
  }
}

// ---------------------------------------------------------------------
// Formulario de registro
// ---------------------------------------------------------------------

let temporizadorValidacionArete = null;
let secuenciaValidacionArete = 0;

function mostrarEstadoValidacionArete(estado, mensaje = '') {
  const campo = el('campo-arete');
  const mensajeEl = el('campo-arete-validacion');
  if (!campo || !mensajeEl) return;

  mensajeEl.className = 'validacion-arete' + (estado ? ' validacion-arete--' + estado : '');
  mensajeEl.textContent = mensaje;
  campo.setAttribute('aria-invalid', estado === 'error' ? 'true' : 'false');
}

function reiniciarValidacionArete() {
  secuenciaValidacionArete++;
  clearTimeout(temporizadorValidacionArete);
  const ayuda = el('campo-arete').disabled
    ? ''
    : 'Se valida en este fundo al escribir y antes de guardar.';
  mostrarEstadoValidacionArete('', ayuda);
}

async function validarAreteIngresado() {
  const campo = el('campo-arete');
  const arete = campo.value.trim();
  const secuencia = ++secuenciaValidacionArete;

  if (!arete) {
    mostrarEstadoValidacionArete('', '');
    return { valido: false, arete };
  }

  mostrarEstadoValidacionArete('comprobando', 'Comprobando arete en este fundo…');

  try {
    const sesion = await Db.obtenerSesion();
    const resultado = await Animales.validarArete(arete, sesion, edicionAnimalUuid || null);
    if (secuencia !== secuenciaValidacionArete || campo.value.trim() !== arete) {
      return { valido: false, obsoleto: true, arete };
    }

    if (resultado.duplicado) {
      const animal = resultado.duplicado;
      const identificacion = [animal.codigo_rgb_id_animal, animal.nombre].filter(Boolean).join(' — ');
      mostrarEstadoValidacionArete(
        'error',
        'Este arete ya está asignado' + (identificacion ? ' a ' + identificacion : ' a otro animal') + ' en este fundo.'
      );
      return { valido: false, arete };
    }

    if (resultado.verificadoServidor) {
      mostrarEstadoValidacionArete('disponible', 'Arete disponible en este fundo.');
    } else if (resultado.sinConexion) {
      mostrarEstadoValidacionArete('aviso', 'Sin conexión con el servidor; se comprobará al sincronizar.');
    } else {
      mostrarEstadoValidacionArete('aviso', 'Sin duplicados en este equipo; se confirmará al sincronizar.');
    }

    return { valido: true, arete, verificadoServidor: resultado.verificadoServidor };
  } catch (error) {
    if (secuencia !== secuenciaValidacionArete || campo.value.trim() !== arete) {
      return { valido: false, obsoleto: true, arete };
    }
    mostrarEstadoValidacionArete('error', 'No se pudo confirmar el arete. Intenta de nuevo antes de guardar.');
    return { valido: false, arete, error };
  }
}

function programarValidacionLocalArete() {
  secuenciaValidacionArete++;
  clearTimeout(temporizadorValidacionArete);
  const arete = el('campo-arete').value.trim();
  if (!arete) {
    mostrarEstadoValidacionArete('', '');
    return;
  }
  mostrarEstadoValidacionArete('comprobando', 'Comprobando arete en este equipo…');
  temporizadorValidacionArete = setTimeout(validarAreteIngresado, 300);
}

async function alternarFormulario(mostrar) {
  el('form-animal-contenedor').classList.toggle('oculto', !mostrar);
  reiniciarValidacionArete();
  if (mostrar) {
    if (!edicionAnimalUuid) {
      restaurarFormularioASuLugar('form-animal-contenedor', 'form-animal-anchor');
      el('form-animal').reset();
      el('form-animal-titulo').textContent = 'Nuevo animal';
      el('form-animal-codigo').classList.add('oculto');
      el('grupo-fecha-nacimiento-animal').classList.toggle('oculto', !nacimientoParaFicharUuid);
      el('campo-arete').disabled = false;
    }
    await poblarSelectsCatalogos();
    snapshotsFormulario['form-animal'] = capturarSnapshotFormulario('form-animal');
  } else {
    edicionAnimalUuid = null;
    el('form-animal-titulo').textContent = 'Nuevo animal';
    el('form-animal-mensaje').textContent = '';
    el('form-animal-codigo').classList.add('oculto');
    el('grupo-fecha-nacimiento-animal').classList.add('oculto');
    el('campo-codigo-ternero').value = '';
    el('campo-arete').disabled = false;
    restaurarFormularioASuLugar('form-animal-contenedor', 'form-animal-anchor');
  }
}

function moverFormularioAntesDe(idFormContenedor, elementoReferencia) {
  const form = el(idFormContenedor);
  if (!form || !elementoReferencia || !elementoReferencia.parentNode) return;
  elementoReferencia.parentNode.insertBefore(form, elementoReferencia);
}

const ANCLAS_FORMULARIOS = {
  'form-animal-contenedor': 'form-animal-anchor',
  'form-cs-contenedor': 'form-cs-anchor',
  'form-pl-contenedor': 'form-pl-anchor',
  'form-pesos-contenedor': 'form-pesos-anchor',
  'form-servicios-contenedor': 'form-servicios-anchor',
  'form-nacimientos-contenedor': 'form-nacimientos-anchor',
};

/* Al editar, el formulario se inserta junto a la tarjeta (dentro de la
   lista). Si la lista se vuelve a dibujar — sincronización, cambio de
   fundo, guardado — ese innerHTML la limpia y el formulario quedaría
   destruido (de ahí los errores de elementos null y el formulario
   "pegado"). Antes de limpiar cualquier lista se devuelven los
   formularios a su ancla original. */
function rescatarFormulariosMovidos(contenedor) {
  if (!contenedor) return;
  Object.keys(ANCLAS_FORMULARIOS).forEach((idForm) => {
    const form = el(idForm);
    if (!form || !contenedor.contains(form)) return; // no está en esta lista
    const ancla = el(ANCLAS_FORMULARIOS[idForm]);
    if (!ancla || !ancla.parentNode) return;
    ancla.parentNode.insertBefore(form, ancla.nextSibling);
  });
}

/* Indica si la lista contiene un formulario visible (se está editando).
   En ese caso no se debe re-dibujar la lista: mover o destruir el
   formulario mientras el calendario nativo está abierto rompe el
   selector de fechas. */
function formularioAbiertoEnLista(contenedor) {
  if (!contenedor) return false;
  return Object.keys(ANCLAS_FORMULARIOS).some((idForm) => {
    const form = el(idForm);
    return !!form && contenedor.contains(form) && !form.classList.contains('oculto');
  });
}

function restaurarFormularioASuLugar(idFormContenedor, idAncla) {
  const form = el(idFormContenedor);
  const ancla = el(idAncla);
  if (!form || !ancla || !ancla.parentNode) return;
  ancla.parentNode.insertBefore(form, ancla.nextSibling);
}

function bloquearCampoAnimal(idCampo, idMostrar, bloquear) {
  BuscadorAnimal.bloquear(idCampo, idMostrar, bloquear);
}

async function alternarFormularioControlSanitario(mostrar) {
  const contenedor = el('form-cs-contenedor');
  if (!contenedor) return;
  contenedor.classList.toggle('oculto', !mostrar);
  if (mostrar) {
    // El control individual y el control por lote no se muestran a la vez.
    const lote = el('form-cs-lote-contenedor');
    if (lote) lote.classList.add('oculto');
    el('cs-animal').disabled = !!edicionControlSanitarioUuid;
    if (!edicionControlSanitarioUuid) {
      restaurarFormularioASuLugar('form-cs-contenedor', 'form-cs-anchor');
      el('form-cs').reset();
      el('form-cs-titulo').textContent = 'Nuevo control sanitario';
      productosControlSanitario = [];
      renderizarProductosControlSanitario();
      bloquearCampoAnimal('cs-animal', 'cs-animal-mostrar', false);
      BuscadorAnimal.seleccionar('cs-animal', '');
      await poblarSelectAnimalesControlSanitario();
    }
    await poblarSelectProductosControlSanitario();
    renderizarProductosControlSanitario();
    snapshotsFormulario['form-cs'] = capturarSnapshotFormulario('form-cs');
  } else {
    edicionControlSanitarioUuid = null;
    el('cs-animal').disabled = false;
    bloquearCampoAnimal('cs-animal', 'cs-animal-mostrar', false);
    BuscadorAnimal.seleccionar('cs-animal', '');
    el('form-cs-titulo').textContent = 'Nuevo control sanitario';
    el('form-cs-mensaje').textContent = '';
    productosControlSanitario = [];
    renderizarProductosControlSanitario();
    el('cs-producto-dosis').value = '';
    el('cs-producto-detalles').value = '';
    restaurarFormularioASuLugar('form-cs-contenedor', 'form-cs-anchor');
  }
}

/**
 * Categorías de productos que tienen al menos un producto en el catálogo
 * offline. Ese catálogo ya viene filtrado por fundo y status desde la API,
 * así que no se ofrecen categorías sin productos aplicables (mismo criterio
 * que la web con get_categoria_con_productos).
 */
async function categoriasProductoConProductos() {
  const [categorias, productos] = await Promise.all([
    Db.obtenerCatalogo('categorias_productos'),
    Db.obtenerCatalogo('productos_control'),
  ]);
  const conProductos = new Set(
    (Array.isArray(productos) ? productos : []).map((producto) => String(producto.categoria_id))
  );
  return (Array.isArray(categorias) ? categorias : [])
    .filter((categoria) => conProductos.has(String(categoria.id)));
}

async function poblarSelectProductosControlSanitario() {
  const select = el('cs-producto-categoria');
  if (!select) return;
  const valorPrevio = select.value;

  /* Solo categorías que tengan al menos un producto en el catálogo
     offline. Ese catálogo ya viene filtrado por fundo y status desde la
     API, así que no se ofrecen categorías sin productos aplicables. */
  const disponibles = await categoriasProductoConProductos();

  select.innerHTML = '<option value="">Selecciona categoría...</option>';
  for (const categoria of disponibles) {
    const opcion = document.createElement('option');
    opcion.value = String(categoria.id);
    opcion.textContent = categoria.nombre;
    select.appendChild(opcion);
  }
  select.disabled = !disponibles.length;
  if (valorPrevio && disponibles.some((categoria) => String(categoria.id) === valorPrevio)) {
    select.value = valorPrevio;
  }

  const mensaje = el('cs-productos-mensaje');
  if (mensaje && !disponibles.length) {
    mensaje.textContent = 'No hay productos cargados para este fundo. Sincroniza los catálogos.';
  }

  await poblarProductosControlSanitario();
}

async function poblarProductosControlSanitario() {
  const select = el('cs-producto');
  if (!select) return;
  const categoriaId = el('cs-producto-categoria').value;
  const productos = await Db.obtenerCatalogo('productos_control');
  const filtrados = categoriaId
    ? productos.filter((producto) => String(producto.categoria_id) === categoriaId)
    : [];
  select.innerHTML = `<option value="">${categoriaId ? 'Selecciona producto...' : 'Selecciona una categoría...'}</option>`;
  for (const producto of filtrados) {
    const opcion = document.createElement('option');
    opcion.value = String(producto.id);
    opcion.textContent = producto.nombre;
    select.appendChild(opcion);
  }
  select.disabled = !filtrados.length;
}

function renderizarProductosControlSanitario() {
  const lista = el('cs-productos-lista');
  if (!lista) return;
  lista.innerHTML = productosControlSanitario.map((producto, indice) => `
    <div class="producto-control-item">
      <div class="producto-control-item__datos">
        <strong>${escaparHtml(producto.producto_nombre)}</strong>
        <span>Dosis: ${escaparHtml(producto.dosis)}</span>
        ${producto.detalles ? `<small>${escaparHtml(producto.detalles)}</small>` : ''}
      </div>
      <button type="button" class="producto-control-item__quitar" data-quitar-producto-control="${indice}">Quitar</button>
    </div>
  `).join('');

  lista.querySelectorAll('[data-quitar-producto-control]').forEach((boton) => {
    boton.addEventListener('click', () => {
      productosControlSanitario.splice(Number(boton.dataset.quitarProductoControl), 1);
      renderizarProductosControlSanitario();
    });
  });
}

async function agregarProductoControlSanitario() {
  const mensaje = el('cs-productos-mensaje');
  const productoId = el('cs-producto').value;
  const dosis = el('cs-producto-dosis').value.trim();
  if (!productoId || !dosis) {
    mensaje.textContent = 'Selecciona un producto e indica la dosis.';
    return;
  }

  const catalogo = await Db.obtenerCatalogo('productos_control');
  const producto = catalogo.find((item) => String(item.id) === productoId);
  if (!producto) {
    mensaje.textContent = 'Ese producto no está disponible en el catálogo offline. Sincroniza los catálogos e intenta de nuevo.';
    return;
  }

  productosControlSanitario.push({
    mproductos_id: Number(producto.id),
    producto_nombre: producto.nombre,
    dosis,
    detalles: el('cs-producto-detalles').value.trim(),
  });
  mensaje.textContent = '';
  el('cs-producto-dosis').value = '';
  el('cs-producto-detalles').value = '';

  /* Limpiar la selección para el siguiente producto: categoría y producto
     vuelven a su estado inicial. */
  el('cs-producto-categoria').value = '';
  await poblarProductosControlSanitario();

  renderizarProductosControlSanitario();
}

async function alternarFormularioProduccionLeche(mostrar) {
  el('form-pl-contenedor').classList.toggle('oculto', !mostrar);
  if (mostrar) {
    el('pl-animal').disabled = !!edicionProduccionLecheUuid;
    if (!edicionProduccionLecheUuid) {
      restaurarFormularioASuLugar('form-pl-contenedor', 'form-pl-anchor');
      el('form-pl').reset();
      el('form-pl-titulo').textContent = 'Nueva produccion de leche';
      bloquearCampoAnimal('pl-animal', 'pl-animal-mostrar', false);
      BuscadorAnimal.seleccionar('pl-animal', '');
      await poblarSelectAnimalesProduccionLeche();
    }
    snapshotsFormulario['form-pl'] = capturarSnapshotFormulario('form-pl');
  } else {
    edicionProduccionLecheUuid = null;
    el('pl-animal').disabled = false;
    bloquearCampoAnimal('pl-animal', 'pl-animal-mostrar', false);
    BuscadorAnimal.seleccionar('pl-animal', '');
    el('form-pl-titulo').textContent = 'Nueva produccion de leche';
    el('form-pl-mensaje').textContent = '';
    restaurarFormularioASuLugar('form-pl-contenedor', 'form-pl-anchor');
  }
}

async function alternarFormularioPesos(mostrar) {
  el('form-pesos-contenedor').classList.toggle('oculto', !mostrar);
  if (mostrar) {
    // El pesaje individual y el pesaje por lote no se muestran a la vez.
    const lote = el('pesaje-lote-contenedor');
    if (lote) lote.classList.add('oculto');
    el('pesos-animal').disabled = !!edicionPesosUuid;
    if (!edicionPesosUuid) {
      restaurarFormularioASuLugar('form-pesos-contenedor', 'form-pesos-anchor');
      el('form-pesos').reset();
      el('form-pesos-titulo').textContent = 'Nuevo pesaje';
      bloquearCampoAnimal('pesos-animal', 'pesos-animal-mostrar', false);
      BuscadorAnimal.seleccionar('pesos-animal', '');
      await poblarSelectAnimalesPesos();
    }
    snapshotsFormulario['form-pesos'] = capturarSnapshotFormulario('form-pesos');
  } else {
    edicionPesosUuid = null;
    el('pesos-animal').disabled = false;
    bloquearCampoAnimal('pesos-animal', 'pesos-animal-mostrar', false);
    BuscadorAnimal.seleccionar('pesos-animal', '');
    el('form-pesos-titulo').textContent = 'Nuevo pesaje';
    el('form-pesos-mensaje').textContent = '';
    restaurarFormularioASuLugar('form-pesos-contenedor', 'form-pesos-anchor');
  }
}

function configurarCamposPorTipoServicio(limpiarIncompatibles = false) {
  const tipoServicio = Number(el('servicios-tipo').value);
  const hayTipo = Number.isInteger(tipoServicio) && tipoServicio > 0;
  const transferencia = tipoServicio === 3;
  const esEdicion = !!edicionServiciosUuid;

  el('servicios-tipo').disabled = esEdicion;
  el('servicios-campos-dependientes').classList.toggle('oculto', !hayTipo);
  el('servicios-macho-grupo').classList.toggle('oculto', transferencia);
  el('servicios-dona-grupo').classList.toggle('oculto', !transferencia);

  for (const idBase of ['servicios-hembra', 'servicios-macho', 'servicios-dona']) {
    const habilitadoPorTipo = hayTipo
      && (idBase === 'servicios-hembra'
        || (idBase === 'servicios-macho' && !transferencia)
        || (idBase === 'servicios-dona' && transferencia));
    const bloqueado = !habilitadoPorTipo || esEdicion;
    const campoOculto = el(idBase);
    const campoBuscar = el(idBase + '-buscar');
    if (campoOculto) campoOculto.disabled = bloqueado;
    if (campoBuscar) campoBuscar.disabled = bloqueado;
  }

  el('servicios-nro').readOnly = !esEdicion;
  if (limpiarIncompatibles && !esEdicion) {
    BuscadorAnimal.seleccionar(transferencia ? 'servicios-macho' : 'servicios-dona', '');
  }
}

async function alternarFormularioServicios(mostrar) {
  el('form-servicios-contenedor').classList.toggle('oculto', !mostrar);
  if (mostrar) {
    el('servicios-nro').readOnly = !edicionServiciosUuid;
    el('servicios-hembra').disabled = !!edicionServiciosUuid;
    el('servicios-macho').disabled = !!edicionServiciosUuid;
    el('servicios-dona').disabled = !!edicionServiciosUuid;
    if (!edicionServiciosUuid) {
      restaurarFormularioASuLugar('form-servicios-contenedor', 'form-servicios-anchor');
      el('form-servicios').reset();
      el('servicios-nro').value = '1';
      el('form-servicios-titulo').textContent = 'Nuevo servicio';
      bloquearCampoAnimal('servicios-hembra', 'servicios-hembra-mostrar', false);
      bloquearCampoAnimal('servicios-macho', 'servicios-macho-mostrar', false);
      bloquearCampoAnimal('servicios-dona', 'servicios-dona-mostrar', false);
      BuscadorAnimal.seleccionar('servicios-hembra', '');
      BuscadorAnimal.seleccionar('servicios-macho', '');
      BuscadorAnimal.seleccionar('servicios-dona', '');
      el('grupo-seguimiento-servicio').classList.add('oculto');
      await poblarSelectAnimalesServicios();
    }
    configurarCamposPorTipoServicio();
    snapshotsFormulario['form-servicios'] = capturarSnapshotFormulario('form-servicios');
  } else {
    edicionServiciosUuid = null;
    el('servicios-tipo').disabled = false;
    el('servicios-hembra').disabled = false;
    el('servicios-macho').disabled = false;
    el('servicios-dona').disabled = false;
    bloquearCampoAnimal('servicios-hembra', 'servicios-hembra-mostrar', false);
    bloquearCampoAnimal('servicios-macho', 'servicios-macho-mostrar', false);
    bloquearCampoAnimal('servicios-dona', 'servicios-dona-mostrar', false);
    BuscadorAnimal.seleccionar('servicios-hembra', '');
    BuscadorAnimal.seleccionar('servicios-macho', '');
    BuscadorAnimal.seleccionar('servicios-dona', '');
    el('form-servicios-titulo').textContent = 'Nuevo servicio';
    el('form-servicios-mensaje').textContent = '';
    restaurarFormularioASuLugar('form-servicios-contenedor', 'form-servicios-anchor');
  }
}

async function alternarFormularioNacimientos(mostrar) {
  el('form-nacimientos-contenedor').classList.toggle('oculto', !mostrar);
  if (mostrar) {
    el('nacimientos-hembra').disabled = !!edicionNacimientosUuid;
    el('nacimientos-macho').disabled = !!edicionNacimientosUuid;
    el('nacimientos-dona').disabled = !!edicionNacimientosUuid;
    if (!edicionNacimientosUuid) {
      focoAnteriorModalNacimiento = document.activeElement;
      el('form-nacimientos').reset();
      el('form-nacimientos-titulo').textContent = 'Nuevo nacimiento';
      el('form-nacimientos-codigo').classList.add('oculto');
      servicioOrigenNacimiento = null;
      el('nacimientos-origen-tipo').value = 'servicio';
      el('nacimientos-servicio-buscar').value = '';
      el('nacimientos-origen-mensaje').textContent = '';
      bloquearCampoAnimal('nacimientos-hembra', 'nacimientos-hembra-mostrar', false);
      bloquearCampoAnimal('nacimientos-macho', 'nacimientos-macho-mostrar', false);
      bloquearCampoAnimal('nacimientos-dona', 'nacimientos-dona-mostrar', false);
      BuscadorAnimal.seleccionar('nacimientos-hembra', '');
      BuscadorAnimal.seleccionar('nacimientos-macho', '');
      BuscadorAnimal.seleccionar('nacimientos-dona', '');
      await poblarSelectAnimalesNacimientos();
      await poblarTernerosFormulario([]);
    }
    configurarBusquedaOrigenNacimiento();
    snapshotsFormulario['form-nacimientos'] = capturarSnapshotFormulario('form-nacimientos');

    const overlay = el('modal-nacimiento-overlay');
    if (edicionNacimientosUuid) {
      overlay.classList.add('oculto');
      document.body.classList.remove('modal-nacimiento-abierto');
    } else {
      el('modal-nacimiento-slot').appendChild(el('form-nacimientos-contenedor'));
      overlay.classList.remove('oculto');
      document.body.classList.add('modal-nacimiento-abierto');
      el('nacimientos-origen-tipo').focus();
    }
  } else {
    edicionNacimientosUuid = null;
    el('nacimientos-hembra').disabled = false;
    el('nacimientos-macho').disabled = false;
    el('nacimientos-dona').disabled = false;
    bloquearCampoAnimal('nacimientos-hembra', 'nacimientos-hembra-mostrar', false);
    bloquearCampoAnimal('nacimientos-macho', 'nacimientos-macho-mostrar', false);
    bloquearCampoAnimal('nacimientos-dona', 'nacimientos-dona-mostrar', false);
    BuscadorAnimal.seleccionar('nacimientos-hembra', '');
    BuscadorAnimal.seleccionar('nacimientos-macho', '');
    BuscadorAnimal.seleccionar('nacimientos-dona', '');
    el('form-nacimientos-titulo').textContent = 'Nuevo nacimiento';
    el('form-nacimientos-codigo').classList.add('oculto');
    el('form-nacimientos-mensaje').textContent = '';
    servicioOrigenNacimiento = null;
    el('nacimientos-servicio-buscar').value = '';
    el('nacimientos-origen-mensaje').textContent = '';
    el('nacimientos-origen-tipo').value = 'servicio';
    configurarBusquedaOrigenNacimiento();
    el('modal-nacimiento-overlay').classList.add('oculto');
    document.body.classList.remove('modal-nacimiento-abierto');
    restaurarFormularioASuLugar('form-nacimientos-contenedor', 'form-nacimientos-anchor');
    if (focoAnteriorModalNacimiento && focoAnteriorModalNacimiento.isConnected) {
      focoAnteriorModalNacimiento.focus();
    }
    focoAnteriorModalNacimiento = null;
  }
}

function manejarEscapeModalNacimiento(evento) {
  if (evento.key !== 'Escape' || el('modal-nacimiento-overlay').classList.contains('oculto')) return;
  pedirConfirmacionYCerrar('form-nacimientos', 'form-nacimientos-contenedor', alternarFormularioNacimientos);
}

function configurarBusquedaOrigenNacimiento() {
  const modoServicio = el('nacimientos-origen-tipo').value === 'servicio';
  const modoEdicion = !!edicionNacimientosUuid;
  const servicioSeleccionado = !!servicioOrigenNacimiento;
  el('nacimientos-origen-tipo').disabled = modoEdicion;
  el('nacimientos-busqueda-servicio-grupo').classList.toggle('oculto', !modoServicio || modoEdicion || servicioSeleccionado);
  el('nacimientos-busqueda-madre-grupo').classList.toggle('oculto', modoServicio || servicioSeleccionado);
  el('nacimientos-madre-servicio-resumen').classList.toggle('oculto', !servicioSeleccionado);
  el('nacimientos-cancelar-origen').classList.toggle('oculto', !servicioSeleccionado || modoEdicion);
  el('nacimientos-datos-contenedor').classList.toggle('oculto', !servicioSeleccionado && !modoEdicion);
}

function limpiarDatosServicioNacimiento(limpiarMadre = true) {
  servicioOrigenNacimiento = null;
  if (limpiarMadre) BuscadorAnimal.seleccionar('nacimientos-hembra', '');
  BuscadorAnimal.seleccionar('nacimientos-macho', '');
  BuscadorAnimal.seleccionar('nacimientos-dona', '');
  el('nacimientos-tipo-servicio').value = '';
  el('nacimientos-madre-servicio-resumen').textContent = '';
  configurarBusquedaOrigenNacimiento();
}

async function mostrarServicioEnNacimiento(servicio) {
  servicioOrigenNacimiento = {
    uuid_local: servicio.uuid_local,
    id: servicio.id || null,
  };
  BuscadorAnimal.seleccionar('nacimientos-hembra', servicio.codigo_rgb_id_animal_hembra || '');
  BuscadorAnimal.seleccionar('nacimientos-macho', servicio.codigo_rgb_id_animal_macho || '');
  BuscadorAnimal.seleccionar('nacimientos-dona', servicio.codigo_rgb_id_animal_dona || '');
  el('nacimientos-tipo-servicio').value = servicio.mservicios_id ? String(servicio.mservicios_id) : '';
  // La aptitud del servicio se propone en el primer ternero (la web
  // también la usa como valor inicial de la cría), pero se puede cambiar
  // por ternero.
  const primeraFila = el('nacimientos-terneros') && el('nacimientos-terneros').querySelector('.ternero-fila');
  const aptitudPrimeraFila = primeraFila && primeraFila.querySelector('[data-ternero="aptitud"]');
  if (aptitudPrimeraFila && !aptitudPrimeraFila.value && servicio.aptitud) {
    aptitudPrimeraFila.value = String(servicio.aptitud);
  }
  const animales = await Animales.listar();
  const madre = animalesPorCodigo(animales, servicio.codigo_rgb_id_animal_hembra);
  const mesesGestacion = mesesCompletosDesde(servicio.f_servicio);
  const descripcionMadre = [
    madre && madre.nombre,
    'Código ' + (servicio.codigo_rgb_id_animal_hembra || '—'),
    'Arete ' + ((madre && madre.nro_arete) || '—'),
    'Gestación ' + mesesGestacion + ' meses',
  ].filter(Boolean).join(' · ');
  el('nacimientos-servicio-buscar').value = descripcionMadre;
  el('nacimientos-madre-servicio-resumen').textContent = descripcionMadre;
  el('nacimientos-origen-mensaje').textContent = 'Se completaron madre, padre, tipo de servicio y aptitud con el servicio seleccionado.';
  bloquearCampoAnimal('nacimientos-hembra', 'nacimientos-hembra-mostrar', true);
  if (servicio.codigo_rgb_id_animal_macho) {
    bloquearCampoAnimal('nacimientos-macho', 'nacimientos-macho-mostrar', true);
  } else {
    bloquearCampoAnimal('nacimientos-macho', 'nacimientos-macho-mostrar', false);
  }
  if (servicio.codigo_rgb_id_animal_dona) {
    bloquearCampoAnimal('nacimientos-dona', 'nacimientos-dona-mostrar', true);
  } else {
    bloquearCampoAnimal('nacimientos-dona', 'nacimientos-dona-mostrar', false);
  }
  configurarBusquedaOrigenNacimiento();
}

function servicioAptoParaNacimiento(servicio, animales) {
  const madre = animalesPorCodigo(animales, servicio.codigo_rgb_id_animal_hembra);
  return esAnimalActivo(madre)
    && Number(madre.sexo) === 1
    && Number(servicio.status ?? 1) === 1
    && Number(servicio.estado_servicio ?? 1) !== 2
    && Number(servicio.estatus_ciclo ?? 1) === 1
    && Number(servicio.medofisiologicos_id) === 1
    && Number(servicio.parto || 0) === 0
    && mesesCompletosDesde(servicio.f_servicio) >= 7
    && mesesCompletosDesde(servicio.f_servicio) <= 10;
}

function servicioActivoMasRecienteNacimiento(servicios, codigoMadre) {
  return servicios
    .filter((servicio) => servicio.codigo_rgb_id_animal_hembra === codigoMadre
      && Number(servicio.status ?? 1) === 1
      && Number(servicio.estatus_ciclo ?? 1) === 1)
    .sort((a, b) => String(b.f_servicio || b.created || '').localeCompare(String(a.f_servicio || a.created || '')))[0] || null;
}

async function vincularServicioActivoDeMadre(codigoMadre) {
  if (!codigoMadre || edicionNacimientosUuid) return;
  const [servicios, animales] = await Promise.all([Servicios.listar(), Animales.listar()]);
  const servicio = servicioActivoMasRecienteNacimiento(servicios, codigoMadre);
  if (servicio && servicioAptoParaNacimiento(servicio, animales)) {
    await mostrarServicioEnNacimiento(servicio);
    return;
  }

  limpiarDatosServicioNacimiento(false);
  el('nacimientos-origen-mensaje').textContent = 'No hay un servicio gestante elegible entre los meses 7 y 10. No se puede registrar el nacimiento sin asociarlo a un servicio.';
}

async function poblarMadresCandidatasNacimiento() {
  const [animales, servicios] = await Promise.all([Animales.listar(), Servicios.listar()]);
  const candidatas = animales.filter((madre) => {
    if (!esAnimalActivo(madre) || Number(madre.sexo) !== 1) return false;
    const servicioActivo = servicioActivoMasRecienteNacimiento(servicios, madre.codigo_rgb_id_animal);
    return !!servicioActivo && servicioAptoParaNacimiento(servicioActivo, animales);
  });
  BuscadorAnimal.poblar('nacimientos-hembra', candidatas, 'Selecciona madre...');
  if (!candidatas.length) {
    el('nacimientos-origen-mensaje').textContent = 'No hay madres con un servicio gestante elegible en este dispositivo. Sincroniza los servicios para actualizar la búsqueda.';
  } else {
    el('nacimientos-origen-mensaje').textContent = 'Selecciona una madre; se cargará su servicio gestante elegible.';
  }
}

async function buscarServiciosParaNacimiento() {
  const input = el('nacimientos-servicio-buscar');
  const lista = el('nacimientos-servicio-opciones');
  const termino = input.value.trim().toLowerCase();
  const servicios = await Servicios.listar();
  const animales = await Animales.listar();
  const indice = construirIndiceAnimales(animales);
  const candidatas = servicios.filter((servicio) =>
    servicioActivoMasRecienteNacimiento(servicios, servicio.codigo_rgb_id_animal_hembra)?.uuid_local === servicio.uuid_local
      && servicioAptoParaNacimiento(servicio, animales)
  );
  const coincidentes = candidatas.filter((servicio) => {
    const hembra = indice[servicio.codigo_rgb_id_animal_hembra];
    const texto = [servicio.codigo_servicio, servicio.codigo_rgb_id_animal_hembra, hembra && hembra.nombre, hembra && hembra.nro_arete]
      .filter(Boolean).join(' ').toLowerCase();
    return !termino || texto.includes(termino);
  }).slice(0, 12);

  lista.innerHTML = '';
  if (!coincidentes.length) {
    const vacio = document.createElement('div');
    vacio.className = 'buscador-animal__vacio';
    vacio.textContent = 'No hay servicios que coincidan en este dispositivo. Puedes cambiar a búsqueda por madre.';
    lista.appendChild(vacio);
    lista.classList.remove('oculto');
    return;
  }

  for (const servicio of coincidentes) {
    const opcion = document.createElement('button');
    opcion.type = 'button';
    opcion.className = 'buscador-animal__item';
    opcion.dataset.servicioUuid = servicio.uuid_local;
    const hembra = indice[servicio.codigo_rgb_id_animal_hembra];
    const mesesGestacion = mesesCompletosDesde(servicio.f_servicio);
    opcion.innerHTML = '<span class="buscador-animal__codigo">'
      + escaparHtml((hembra && hembra.nombre) || 'Hembra sin nombre')
      + '</span><span class="buscador-animal__detalle">Código '
      + escaparHtml(servicio.codigo_rgb_id_animal_hembra || '—')
      + ' · Arete ' + escaparHtml((hembra && hembra.nro_arete) || '—')
      + ' · Gestación ' + escaparHtml(String(mesesGestacion)) + ' meses</span>';
    opcion.addEventListener('click', () => seleccionarServicioParaNacimiento(servicio.uuid_local));
    lista.appendChild(opcion);
  }
  lista.classList.remove('oculto');
}

async function seleccionarServicioParaNacimiento(uuidServicio) {
  const servicio = await Db.obtener('servicios', uuidServicio);
  if (!servicio) return;
  await mostrarServicioEnNacimiento(servicio);
  el('nacimientos-servicio-opciones').classList.add('oculto');
}

async function cambiarModoBusquedaNacimiento() {
  const modo = el('nacimientos-origen-tipo').value;
  el('form-nacimientos').reset();
  servicioOrigenNacimiento = null;
  el('nacimientos-origen-tipo').value = modo;
  el('nacimientos-origen-mensaje').textContent = '';
  el('form-nacimientos-mensaje').textContent = '';
  limpiarDatosServicioNacimiento(true);
  el('nacimientos-servicio-buscar').value = '';
  el('nacimientos-servicio-opciones').innerHTML = '';
  el('nacimientos-servicio-opciones').classList.add('oculto');
  configurarBusquedaOrigenNacimiento();

  if (modo === 'madre') {
    el('nacimientos-busqueda-madre-grupo').classList.remove('oculto');
    await poblarMadresCandidatasNacimiento();
    if (!servicioOrigenNacimiento) {
      el('nacimientos-busqueda-madre-grupo').classList.remove('oculto');
      el('nacimientos-datos-contenedor').classList.add('oculto');
    }
  } else {
    await poblarSelectAnimalesNacimientos();
  }
}

async function cancelarOrigenNacimiento() {
  if (edicionNacimientosUuid) return;
  const modo = el('nacimientos-origen-tipo').value;
  limpiarDatosServicioNacimiento(true);
  el('nacimientos-servicio-buscar').value = '';
  el('nacimientos-servicio-opciones').innerHTML = '';
  el('nacimientos-servicio-opciones').classList.add('oculto');
  el('nacimientos-origen-mensaje').textContent = '';
  configurarBusquedaOrigenNacimiento();

  if (modo === 'madre') {
    await poblarMadresCandidatasNacimiento();
    el('nacimientos-busqueda-madre-grupo').classList.remove('oculto');
    el('nacimientos-hembra-buscar').focus();
  } else {
    el('nacimientos-servicio-buscar').focus();
  }
}

async function manejarCambioOrigenNacimiento(evento) {
  if (!evento.detail || evento.detail.idBase !== 'nacimientos-hembra'
    || edicionNacimientosUuid
    || servicioOrigenNacimiento
    || el('nacimientos-origen-tipo').value !== 'madre') return;
  await vincularServicioActivoDeMadre(evento.detail.codigo);
}

async function abrirEdicionNacimiento(uuidLocal, tarjetaEl) {
  if (!puedeAbrirOtroFormulario('form-nacimientos', 'form-nacimientos-contenedor')) return;
  const registro = await Db.obtener('nacimientos', uuidLocal);
  if (!registro) return;

  const animalesExistentes = await Animales.listar();
  const fichaExistente = animalesExistentes.find((animal) =>
    Number(animal.status ?? 1) === 1
    && ((animal.codigo_ternero && animal.codigo_ternero === registro.codigo_ternero)
      || animal.nacimiento_uuid_local === uuidLocal)
  );
  const yaFichado = esBanderaActiva(registro.fichado) || !!fichaExistente;

  if (yaFichado) {
    let cambio = false;
    if (!esBanderaActiva(registro.fichado)) {
      registro.fichado = true;
      cambio = true;
    }
    if (fichaExistente && registro.ficha_animal_uuid_local !== fichaExistente.uuid_local) {
      registro.ficha_animal_uuid_local = fichaExistente.uuid_local;
      registro.ficha_animal_codigo = fichaExistente.codigo_rgb_id_animal;
      cambio = true;
    }
    if (cambio) await Db.guardar('nacimientos', registro);

    abrirDetalleRegistro('nacimientos', uuidLocal, tarjetaEl);
    return;
  }

  const serviciosLocales = await Db.obtenerTodos('servicios');
  const servicioRelacionado = registro.servicio_uuid_local
    ? serviciosLocales.find((servicio) => servicio.uuid_local === registro.servicio_uuid_local)
    : serviciosLocales.find((servicio) => Number(servicio.id) === Number(registro.mregservicios_id));

  edicionNacimientosUuid = uuidLocal;
  servicioOrigenNacimiento = servicioRelacionado
    ? { uuid_local: servicioRelacionado.uuid_local, id: servicioRelacionado.id || null }
    : (registro.mregservicios_id ? { uuid_local: null, id: registro.mregservicios_id } : null);
  el('nacimientos-origen-tipo').value = servicioOrigenNacimiento ? 'servicio' : 'madre';
  el('nacimientos-servicio-buscar').value = servicioRelacionado
    ? (servicioRelacionado.codigo_servicio || 'Servicio relacionado')
    : (servicioOrigenNacimiento ? 'Servicio ID ' + servicioOrigenNacimiento.id : '');
  if (tarjetaEl) moverFormularioAntesDe('form-nacimientos-contenedor', tarjetaEl);
  await poblarSelectAnimalesNacimientos();

  el('form-nacimientos-titulo').textContent = 'Editar nacimiento';
  el('boton-fichar-nacimiento').classList.remove('oculto');
  // Con varias crías la ficha del animal se arma con la primera (el
  // código de nacimiento es uno solo por parto), así que se avisa en el
  // texto del botón para que no confunda.
  const totalTernerosRegistro = Array.isArray(registro.terneros) ? registro.terneros.length : 1;
  el('boton-fichar-nacimiento').textContent = totalTernerosRegistro > 1
    ? 'Fichar primer ternero'
    : 'Fichar ternero';
  el('nacimiento-fichado-info').classList.add('oculto');
  if (registro.codigo_ternero && registro.codigo_ternero.indexOf('PEND') === -1) {
    el('form-nacimientos-codigo').textContent = registro.codigo_ternero;
    el('form-nacimientos-codigo').classList.remove('oculto');
  } else {
    el('form-nacimientos-codigo').classList.add('oculto');
  }

  asegurarOpcionSeleccionada('nacimientos-hembra', registro.codigo_rgb_id_animal_hembra);
  asegurarOpcionSeleccionada('nacimientos-macho', registro.codigo_rgb_id_animal_macho);
  asegurarOpcionSeleccionada('nacimientos-dona', registro.codigo_rgb_id_animal_dona);
  bloquearCampoAnimal('nacimientos-hembra', 'nacimientos-hembra-mostrar', true);
  if (registro.codigo_rgb_id_animal_macho) {
    bloquearCampoAnimal('nacimientos-macho', 'nacimientos-macho-mostrar', true);
  }
  if (registro.codigo_rgb_id_animal_dona) {
    bloquearCampoAnimal('nacimientos-dona', 'nacimientos-dona-mostrar', true);
  }
  if (servicioRelacionado) {
    el('nacimientos-madre-servicio-resumen').textContent = 'Servicio relacionado: '
      + (servicioRelacionado.codigo_servicio || 'Servicio pendiente');
  } else if (servicioOrigenNacimiento) {
    el('nacimientos-madre-servicio-resumen').textContent = 'Servicio relacionado: ID ' + servicioOrigenNacimiento.id;
  } else {
    el('nacimientos-madre-servicio-resumen').textContent = '';
  }

  el('nacimientos-fecha-parto').value = comoFechaInput(registro.f_parto);
  el('nacimientos-tipo-servicio').value = registro.mtiposervicios_id ? String(registro.mtiposervicios_id) : '';
  el('nacimientos-nro-parto').value = registro.nro_parto ?? '';
  el('nacimientos-proposito').value = String(Number(registro.proposito) || 0);
  el('nacimientos-fecha-secado').value = comoFechaInput(registro.f_secado);
  el('nacimientos-observaciones').value = registro.observaciones || '';

  // Terneros: se usa el detalle del parto si vino del servidor; si el
  // registro es antiguo (una sola cría en campos planos), se arma la
  // fila con esos datos para no perder nada.
  const ternerosPrevios = Array.isArray(registro.terneros) && registro.terneros.length
    ? registro.terneros
    : [{
        sexo: registro.sexo,
        estatus_nacido: registro.estatus_nacido,
        peso_nacimiento: registro.peso_nacimiento,
        id_ternero: registro.id_ternero,
        aptitud: registro.aptitud,
      }];
  await poblarTernerosFormulario(ternerosPrevios);

  configurarBusquedaOrigenNacimiento();

  el('form-nacimientos-mensaje').textContent = '';
  alternarFormularioNacimientos(true);
  el('form-nacimientos-contenedor').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function esBanderaActiva(valor) {
  return valor === true || valor === 1 || valor === '1' || valor === 't' || valor === 'true';
}

async function manejarFicharNacimiento() {
  if (!edicionNacimientosUuid) return;
  if (formularioTieneCambiosSinGuardar('form-nacimientos', 'form-nacimientos-contenedor')) {
    el('form-nacimientos-mensaje').textContent = 'Guarda los cambios del nacimiento antes de fichar el ternero.';
    return;
  }

  const nacimiento = await Db.obtener('nacimientos', edicionNacimientosUuid);
  if (!nacimiento) return;
  if (esBanderaActiva(nacimiento.fichado)) {
    await alternarFormularioNacimientos(false);
    await renderizarListaNacimientos();
    abrirDetalleRegistro('nacimientos', nacimiento.uuid_local);
    return;
  }

  const animales = await Animales.listar();
  const yaExiste = animales.find((animal) =>
    Number(animal.status ?? 1) === 1
    && ((animal.codigo_ternero && animal.codigo_ternero === nacimiento.codigo_ternero)
      || animal.nacimiento_uuid_local === nacimiento.uuid_local)
  );

  if (yaExiste) {
    nacimiento.fichado = true;
    nacimiento.ficha_animal_uuid_local = yaExiste.uuid_local;
    nacimiento.ficha_animal_codigo = yaExiste.codigo_rgb_id_animal;
    await Db.guardar('nacimientos', nacimiento);

    await alternarFormularioNacimientos(false);
    await renderizarListaNacimientos();

    mostrarModalAviso(
      'Este ternero ya tiene ficha',
      '<p style="margin:0 0 10px;">El ternero de este nacimiento ya fue fichado como animal en el fundo:</p>'
      + '<div style="background:#f5f5f5; padding:10px 12px; border-radius:8px; margin-bottom:12px;">'
      +   '<div style="font-weight:700; color:#1f2937;">' + escaparHtml(yaExiste.nombre || '(sin nombre)') + '</div>'
      +   '<div style="color:#5d676d; font-size:12px; margin-top:4px;">'
      +     'Código: ' + escaparHtml(yaExiste.codigo_rgb_id_animal || '—')
      +     ' · Arete: ' + escaparHtml(yaExiste.nro_arete || '—')
      +   '</div>'
      + '</div>'
      + '<div style="background:#e7f0fa; border:1px solid #b9d3ec; padding:10px 12px; border-radius:8px; font-size:12px; color:#1e4976;">'
      +   'El nacimiento queda en modo <strong>solo lectura</strong>. '
      +   'Si nnecesitas modificar los datos del animal, andá al módulo <strong>Animales</strong>.'
      + '</div>'
    );
    return;
  }

  const servicios = await Servicios.listar();
  const servicio = nacimiento.servicio_uuid_local
    ? servicios.find((item) => item.uuid_local === nacimiento.servicio_uuid_local)
    : servicios.find((item) => Number(item.id) === Number(nacimiento.mregservicios_id));
  const madre = animalesPorCodigo(animales, nacimiento.codigo_rgb_id_animal_hembra);
  const padre = animalesPorCodigo(animales, nacimiento.codigo_rgb_id_animal_macho);

  nacimientoParaFicharUuid = nacimiento.uuid_local;
  if (!mostrarTab('animales')) {
    nacimientoParaFicharUuid = null;
    el('form-nacimientos-mensaje').textContent = 'No se pudo abrir Animales porque hay otro formulario con cambios pendientes.';
    return;
  }
  edicionAnimalUuid = null;
  await alternarFormulario(true);

  el('form-animal-titulo').textContent = 'Fichar ternero';
  el('form-animal-codigo').textContent = 'Código de nacimiento: ' + (nacimiento.codigo_ternero || 'Pendiente');
  el('form-animal-codigo').classList.remove('oculto');
  el('grupo-fecha-nacimiento-animal').classList.remove('oculto');
  el('campo-nombre').value = nacimiento.id_ternero || '';
  el('campo-sexo').value = String(nacimiento.sexo || '1');
  el('campo-arete').value = nacimiento.id_ternero || '';
  el('campo-raza').value = String((servicio && servicio.mrazas_id) || (madre && madre.mrazas_id) || '');
  el('campo-aptitud').value = String(nacimiento.aptitud || (servicio && servicio.aptitud) || (madre && madre.aptitud) || '');
  el('campo-lote').value = madre && madre.mlotes_id ? String(madre.mlotes_id) : '';
  el('campo-peso').value = nacimiento.peso_nacimiento || '';
  el('campo-observaciones').value = nacimiento.observaciones || '';
  el('campo-fecha-nacimiento').value = comoFechaInput(nacimiento.f_parto);
  el('campo-codigo-ternero').value = nacimiento.codigo_ternero || '';
  el('form-animal-mensaje').textContent = 'Completa o confirma nombre, arete, raza y aptitud para crear la ficha.';
  snapshotsFormulario['form-animal'] = capturarSnapshotFormulario('form-animal');
  el('campo-nombre').focus();
}

async function manejarCancelarFormularioAnimal() {
  if (!nacimientoParaFicharUuid) {
    pedirConfirmacionYCerrar('form-animal', 'form-animal-contenedor', alternarFormulario);
    return;
  }

  if (formularioTieneCambiosSinGuardar('form-animal', 'form-animal-contenedor')
    && !window.confirm(MENSAJE_CAMBIOS_SIN_GUARDAR)) return;

  const nacimientoUuid = nacimientoParaFicharUuid;
  nacimientoParaFicharUuid = null;
  await alternarFormulario(false);
  mostrarTab('nacimientos');
  await abrirEdicionNacimiento(nacimientoUuid);
}

async function manejarGuardarNacimientos(evento) {
  evento.preventDefault();
  const sesion = await Db.obtenerSesion();
  const terneros = leerTernerosFormulario();
  const primerTernero = terneros[0] || {};

  const datosFormulario = {
    codigo_rgb_id_animal_hembra: el('nacimientos-hembra').value,
    codigo_rgb_id_animal_macho: el('nacimientos-macho').value,
    codigo_rgb_id_animal_dona: el('nacimientos-dona').value,
    f_parto: el('nacimientos-fecha-parto').value,
    // Los campos planos reflejan al primer ternero, igual que la web.
    sexo: primerTernero.sexo,
    id_ternero: primerTernero.id_ternero,
    peso_nacimiento: primerTernero.peso_nacimiento,
    aptitud: primerTernero.aptitud,
    estatus_nacido: primerTernero.estatus_nacido,
    terneros,
    mtiposervicios_id: el('nacimientos-tipo-servicio').value,
    nro_parto: el('nacimientos-nro-parto').value,
    proposito: el('nacimientos-proposito').value,
    f_secado: el('nacimientos-fecha-secado').value,
    observaciones: el('nacimientos-observaciones').value.trim(),
    servicio_uuid_local: servicioOrigenNacimiento ? servicioOrigenNacimiento.uuid_local : null,
    mregservicios_id: servicioOrigenNacimiento ? servicioOrigenNacimiento.id : null,
  };

  if (!datosFormulario.codigo_rgb_id_animal_hembra || !datosFormulario.f_parto) {
    el('form-nacimientos-mensaje').textContent = 'Completa al menos la hembra (madre) y la fecha de parto.';
    return;
  }

  const errorTerneros = validarTernerosFormulario(terneros);
  if (errorTerneros) {
    el('form-nacimientos-mensaje').textContent = errorTerneros;
    return;
  }

  // Con la lista ya validada, los campos planos quedan normalizados.
  datosFormulario.sexo = terneros[0].sexo;
  datosFormulario.id_ternero = terneros[0].id_ternero;
  datosFormulario.peso_nacimiento = terneros[0].peso_nacimiento;
  datosFormulario.aptitud = terneros[0].aptitud;
  datosFormulario.estatus_nacido = terneros[0].estatus_nacido;

  if (Number(datosFormulario.f_secado) && Number(datosFormulario.proposito) === 0) {
    el('form-nacimientos-mensaje').textContent = 'Selecciona el propósito (lactancia o producción de leche) para registrar la fecha de secado.';
    return;
  }

  if (datosFormulario.f_secado && datosFormulario.f_secado < datosFormulario.f_parto) {
    el('form-nacimientos-mensaje').textContent = 'La fecha de secado no puede ser anterior a la fecha de parto.';
    return;
  }

  if (!edicionNacimientosUuid && !servicioOrigenNacimiento) {
    el('form-nacimientos-mensaje').textContent = 'Selecciona un servicio gestante válido para registrar el nacimiento.';
    return;
  }

  if (edicionNacimientosUuid) {
    await Nacimientos.editar(sesion, edicionNacimientosUuid, datosFormulario);
    mostrarToast('Nacimiento actualizado');
  } else {
    await Nacimientos.crear(sesion, datosFormulario);
    mostrarToast('Nacimiento guardado');
  }

  alternarFormularioNacimientos(false);
  await renderizarListaNacimientos();
  await actualizarContadorPendientes();
}


async function abrirEdicionAnimal(uuidLocal, tarjetaEl) {
  if (!puedeAbrirOtroFormulario('form-animal', 'form-animal-contenedor')) return;
  const registro = await Db.obtener('animales', uuidLocal);
  if (!registro) return;

  edicionAnimalUuid = uuidLocal;
  if (tarjetaEl) moverFormularioAntesDe('form-animal-contenedor', tarjetaEl);
  el('form-animal-titulo').textContent = 'Editar animal';
  el('form-animal-codigo').textContent = registro.codigo_rgb_id_animal || '';
  el('form-animal-codigo').classList.remove('oculto');
  el('campo-nombre').value = registro.nombre || '';
  el('campo-sexo').value = String(registro.sexo || '1');
  el('campo-arete').value = registro.nro_arete || '';
  el('campo-arete').disabled = true;
  el('campo-raza').value = String(registro.mrazas_id || '');
  el('campo-aptitud').value = String(registro.aptitud || '');
  el('campo-lote').value = registro.mlotes_id ? String(registro.mlotes_id) : '';
  el('campo-peso').value = registro.peso_nacimiento || '';
  el('campo-observaciones').value = registro.observaciones || '';
  el('form-animal-mensaje').textContent = '';
  await alternarFormulario(true);
  el('campo-categoria-ingreso').value = registro.categoria_ingreso_id ? String(registro.categoria_ingreso_id) : '';
  el('campo-categoria-futura').value = registro.categoria_futura_id ? String(registro.categoria_futura_id) : '';
  el('form-animal-contenedor').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function asegurarOpcionSeleccionada(idCampo, valor) {
  BuscadorAnimal.seleccionar(idCampo, valor);
}

function comoFechaInput(valor) {
  const texto = (valor || '').toString().trim();
  if (!texto) return '';
  // yyyy-mm-dd (o ISO con hora)
  if (/^\d{4}-\d{2}-\d{2}/.test(texto)) return texto.slice(0, 10);
  // dd-mm-yyyy / dd/mm/yyyy: formato que usa la web; el input type=date
  // solo acepta yyyy-mm-dd, de lo contrario el calendario queda inutilizable.
  const partes = texto.match(/^(\d{2})[-/](\d{2})[-/](\d{4})/);
  if (partes) return `${partes[3]}-${partes[2]}-${partes[1]}`;
  return texto.slice(0, 10);
}

function formatearFecha(valor) {
  const soloFecha = comoFechaInput(valor);
  const partes = soloFecha.split('-');
  if (partes.length !== 3 || !partes[0] || !partes[1] || !partes[2]) {
    return valor ? String(valor) : '';
  }
  const [anio, mes, dia] = partes;
  return `${dia}-${mes}-${anio}`;
}

async function abrirEdicionControlSanitario(uuidLocal, tarjetaEl) {
  if (!el('form-cs-contenedor')) return;
  if (!puedeAbrirOtroFormulario('form-cs', 'form-cs-contenedor')) return;
  const registro = await Db.obtener('control_sanitario', uuidLocal);
  if (!registro) return;

  edicionControlSanitarioUuid = uuidLocal;
  productosControlSanitario = Array.isArray(registro.productos_aplicados)
    ? registro.productos_aplicados.map((producto) => ({ ...producto }))
    : [];
  if (tarjetaEl) moverFormularioAntesDe('form-cs-contenedor', tarjetaEl);
  await poblarSelectAnimalesControlSanitario();
  el('form-cs-titulo').textContent = 'Editar control sanitario';
  asegurarOpcionSeleccionada('cs-animal', registro.codigo_rgb_id_animal);
  bloquearCampoAnimal('cs-animal', 'cs-animal-mostrar', true);
  el('cs-fecha').value = comoFechaInput(registro.fecha_control);
  el('cs-fecha-prox').value = comoFechaInput(registro.fecha_prox_control);
  el('cs-evento').value = registro.evento || '';
  el('cs-responsable').value = registro.responsable || '';
  el('cs-observaciones').value = registro.observaciones || '';
  const mensajeFormulario = el('form-cs-mensaje');
  if (mensajeFormulario) mensajeFormulario.textContent = '';
  alternarFormularioControlSanitario(true);
  el('form-cs-contenedor').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

async function abrirEdicionProduccionLeche(uuidLocal, tarjetaEl) {
  if (!puedeAbrirOtroFormulario('form-pl', 'form-pl-contenedor')) return;
  const registro = await Db.obtener('produccion_leche', uuidLocal);
  if (!registro) return;

  edicionProduccionLecheUuid = uuidLocal;
  if (tarjetaEl) moverFormularioAntesDe('form-pl-contenedor', tarjetaEl);
  await poblarSelectAnimalesProduccionLeche();
  el('form-pl-titulo').textContent = 'Editar produccion de leche';
  asegurarOpcionSeleccionada('pl-animal', registro.codigo_rgb_id_animal_hembra);
  bloquearCampoAnimal('pl-animal', 'pl-animal-mostrar', true);
  el('pl-fecha').value = comoFechaInput(registro.fecha_produccion);
  el('pl-litros-manana').value = registro.cantidad_leche_m ?? '';
  el('pl-litros-tarde').value = registro.cantidad_leche_t ?? '';
  el('form-pl-mensaje').textContent = '';
  alternarFormularioProduccionLeche(true);
  el('form-pl-contenedor').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

async function abrirEdicionPesos(uuidLocal, tarjetaEl) {
  if (!puedeAbrirOtroFormulario('form-pesos', 'form-pesos-contenedor')) return;
  const registro = await Db.obtener('pesos', uuidLocal);
  if (!registro) return;

  edicionPesosUuid = uuidLocal;
  if (tarjetaEl) moverFormularioAntesDe('form-pesos-contenedor', tarjetaEl);
  await poblarSelectAnimalesPesos();
  el('form-pesos-titulo').textContent = 'Editar pesaje';
  asegurarOpcionSeleccionada('pesos-animal', registro.codigo_rgb_id_animal);
  bloquearCampoAnimal('pesos-animal', 'pesos-animal-mostrar', true);
  el('pesos-fecha').value = comoFechaInput(registro.fecha_pesaje);
  el('pesos-valor').value = registro.peso ?? '';
  el('form-pesos-mensaje').textContent = '';
  alternarFormularioPesos(true);
  el('form-pesos-contenedor').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

async function abrirEdicionServicios(uuidLocal, tarjetaEl) {
  if (!puedeAbrirOtroFormulario('form-servicios', 'form-servicios-contenedor')) return;
  const registro = await Db.obtener('servicios', uuidLocal);
  if (!registro) return;

  edicionServiciosUuid = uuidLocal;
  if (tarjetaEl) moverFormularioAntesDe('form-servicios-contenedor', tarjetaEl);
  await poblarSelectAnimalesServicios();
  el('form-servicios-titulo').textContent = 'Editar servicio' + (registro.codigo_servicio && registro.codigo_servicio.indexOf('PEND') === -1 ? ' — ' + registro.codigo_servicio : '');
  asegurarOpcionSeleccionada('servicios-hembra', registro.codigo_rgb_id_animal_hembra);
  asegurarOpcionSeleccionada('servicios-macho', registro.codigo_rgb_id_animal_macho);
  asegurarOpcionSeleccionada('servicios-dona', registro.codigo_rgb_id_animal_dona);
  bloquearCampoAnimal('servicios-hembra', 'servicios-hembra-mostrar', true);
  bloquearCampoAnimal('servicios-macho', 'servicios-macho-mostrar', true);
  if (registro.codigo_rgb_id_animal_dona) {
    bloquearCampoAnimal('servicios-dona', 'servicios-dona-mostrar', true);
  }
  el('servicios-fecha').value = comoFechaInput(registro.f_servicio);
  el('servicios-fpp').value = comoFechaInput(registro.f_probable_palpacion);
  el('servicios-tipo').value = String(registro.mservicios_id || '');
  el('servicios-nro').value = String(registro.nro_servicio || 1);
  el('servicios-aptitud').value = String(registro.aptitud || '');
  el('servicios-raza').value = String(registro.mrazas_id || '');
  el('servicios-observaciones').value = registro.observaciones || '';

  el('servicios-fecha-palpacion').value = comoFechaInput(registro.f_palpacion);
  el('servicios-estado-fisiologico').value = registro.medofisiologicos_id ? String(registro.medofisiologicos_id) : '';
  el('servicios-parto-confirmado').checked = Number(registro.parto) === 1;
  el('servicios-fecha-parto').value = comoFechaInput(registro.fecha_parto);
  el('servicios-nro-terneros').value = registro.nro_terneros ?? '';
  el('servicios-dnp').value = registro.dnp ?? '';
  el('servicios-fecha-probable-parto').value = comoFechaInput(registro.f_probable_parto);
  el('servicios-fecha-ultimo-parto').value = comoFechaInput(registro.f_ultimo_parto);
  el('servicios-fecha-secado').value = comoFechaInput(registro.f_secado);
  alternarCamposPartoServicio();
  el('grupo-seguimiento-servicio').classList.remove('oculto');

  el('form-servicios-mensaje').textContent = '';
  alternarFormularioServicios(true);
  el('form-servicios-contenedor').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function alternarCamposPartoServicio() {
  const marcado = el('servicios-parto-confirmado').checked;
  el('grupo-datos-parto-servicio').classList.toggle('oculto', !marcado);
}

async function manejarGuardarAnimal(evento) {
  evento.preventDefault();
  const sesion = await Db.obtenerSesion();

  const datosFormulario = {
    nombre: el('campo-nombre').value.trim(),
    sexo: el('campo-sexo').value,
    nro_arete: el('campo-arete').value.trim(),
    aptitud: el('campo-aptitud').value,
    mrazas_id: el('campo-raza').value,
    mlotes_id: el('campo-lote').value || null,
    categoria_ingreso_id: el('campo-categoria-ingreso').value || null,
    categoria_futura_id: el('campo-categoria-futura').value || null,
    peso_nacimiento: el('campo-peso').value.trim(),
    observaciones: el('campo-observaciones').value.trim(),
  };

  const nacimientoFichaUuid = nacimientoParaFicharUuid;
  if (nacimientoFichaUuid) {
    const nacimiento = await Db.obtener('nacimientos', nacimientoFichaUuid);
    if (!nacimiento || esBanderaActiva(nacimiento.fichado)) {
      el('form-animal-mensaje').textContent = 'Este nacimiento ya fue fichado o no está disponible.';
      return;
    }
    const animales = await Animales.listar();
    const madre = animalesPorCodigo(animales, nacimiento.codigo_rgb_id_animal_hembra);
    const padre = animalesPorCodigo(animales, nacimiento.codigo_rgb_id_animal_macho);
    const servicios = await Servicios.listar();
    const servicio = nacimiento.servicio_uuid_local
      ? servicios.find((item) => item.uuid_local === nacimiento.servicio_uuid_local)
      : servicios.find((item) => Number(item.id) === Number(nacimiento.mregservicios_id));

    datosFormulario.codigo_ternero = nacimiento.codigo_ternero || '';
    datosFormulario.f_nacimiento = nacimiento.f_parto || el('campo-fecha-nacimiento').value || null;
    datosFormulario.nacimiento_uuid_local = nacimiento.uuid_local;
    datosFormulario.rgd_madre = nacimiento.codigo_rgb_id_animal_hembra || '';
    datosFormulario.nombre_madre = madre ? (madre.nombre || '') : '';
    datosFormulario.rgd_padre = nacimiento.codigo_rgb_id_animal_macho || '';
    datosFormulario.nombre_padre = padre ? (padre.nombre || '') : '';
    datosFormulario.procedencia = 'Nacido en este fundo';
    datosFormulario.servicio = nacimiento.mtiposervicios_id || (servicio && servicio.mservicios_id) || null;
  }

  if (!datosFormulario.nombre || !datosFormulario.nro_arete || !datosFormulario.aptitud || !datosFormulario.mrazas_id) {
    el('form-animal-mensaje').textContent = 'Completa al menos nombre, arete, aptitud y raza.';
    return;
  }

  const validacionArete = await validarAreteIngresado();
  if (!validacionArete.valido || validacionArete.arete !== datosFormulario.nro_arete) return;

  let animalCreado = null;
  if (edicionAnimalUuid) {
    await Animales.editar(sesion, edicionAnimalUuid, datosFormulario);
  } else {
    animalCreado = await Animales.crear(sesion, datosFormulario, { sincronizar: !nacimientoFichaUuid });
  }

  if (nacimientoFichaUuid && animalCreado) {
    const nacimiento = await Db.obtener('nacimientos', nacimientoFichaUuid);
    if (nacimiento) {
      nacimiento.fichado = true;
      nacimiento.ficha_animal_uuid_local = animalCreado.uuid_local;
      nacimiento.ficha_animal_codigo = animalCreado.codigo_rgb_id_animal;
      await Db.guardar('nacimientos', nacimiento);
    }
    nacimientoParaFicharUuid = null;
  }

  mostrarToast(
    edicionAnimalUuid ? 'Animal actualizado'
      : nacimientoFichaUuid ? 'Animal fichado desde el nacimiento'
        : 'Animal guardado'
  );

  alternarFormulario(false);
  await renderizarLista();
  await poblarSelectAnimalesControlSanitario();
  await poblarSelectAnimalesProduccionLeche();
  await poblarSelectAnimalesPesos();
  await actualizarContadorPendientes();
  if (nacimientoFichaUuid) {
    await renderizarListaNacimientos();
    mostrarTab('nacimientos');
    await actualizarContadorPendientes();
    Sync.sincronizar();
  }
}

async function manejarGuardarControlSanitario(evento) {
  evento.preventDefault();
  const sesion = await Db.obtenerSesion();

  const datosFormulario = {
    codigo_rgb_id_animal: el('cs-animal').value,
    fecha_control: el('cs-fecha').value,
    fecha_prox_control: el('cs-fecha-prox').value,
    evento: el('cs-evento').value.trim(),
    responsable: el('cs-responsable').value.trim(),
    observaciones: el('cs-observaciones').value.trim(),
    productos_aplicados: productosControlSanitario,
  };

  if (!datosFormulario.codigo_rgb_id_animal || !datosFormulario.fecha_control || !datosFormulario.evento) {
    el('form-cs-mensaje').textContent = 'Completa animal, fecha y evento.';
    return;
  }

  // Misma regla que la web: un solo control sanitario por animal y fecha.
  const duplicado = await registroDuplicadoEnFecha(
    'control_sanitario', 'codigo_rgb_id_animal', 'fecha_control',
    datosFormulario.codigo_rgb_id_animal, datosFormulario.fecha_control,
    edicionControlSanitarioUuid
  );
  if (duplicado) {
    el('form-cs-mensaje').textContent = 'Ese animal ya tiene un control sanitario registrado para el '
      + formatearFecha(datosFormulario.fecha_control) + '.';
    return;
  }

  if (edicionControlSanitarioUuid) {
    await ControlSanitario.editar(sesion, edicionControlSanitarioUuid, datosFormulario);
    mostrarToast('Control sanitario actualizado');
  } else {
    await ControlSanitario.crear(sesion, datosFormulario);
    mostrarToast('Control sanitario guardado');
  }

  alternarFormularioControlSanitario(false);
  await renderizarListaControlSanitario();
  await actualizarContadorPendientes();
}

// ---------------------------------------------------------------------
// Control sanitario por LOTE
//
// Equivalente offline de views/controlsanitariomasivo: un control se
// aplica a TODOS los animales activos del lote, con los productos
// utilizados. El código (RCS-0<fundo>-0<n>) lo asigna el servidor al
// sincronizar.
// ---------------------------------------------------------------------

async function alternarFormularioControlLote(mostrar) {
  const contenedor = el('form-cs-lote-contenedor');
  if (!contenedor) return;

  contenedor.classList.toggle('oculto', !mostrar);

  if (!mostrar) {
    edicionControlLoteUuid = null;
    productosControlLote = [];
    el('form-cs-lote-titulo').textContent = 'Nuevo control por lote';
    el('form-cs-lote-codigo').classList.add('oculto');
    el('form-cs-lote-mensaje').textContent = '';
    el('cs-lote-productos-lista').innerHTML = '';
    el('cs-lote-animales-info').textContent = '';
    el('cs-lote-lote').disabled = false;
    el('cs-lote-fecha').disabled = false;
    restaurarFormularioASuLugar('form-cs-lote-contenedor', 'form-cs-lote-anchor');
    return;
  }

  if (!edicionControlLoteUuid) {
    restaurarFormularioASuLugar('form-cs-lote-contenedor', 'form-cs-lote-anchor');
    el('form-cs-lote').reset();
    el('cs-lote-lote').disabled = false;
    el('cs-lote-fecha').disabled = false;
    el('form-cs-lote-titulo').textContent = 'Nuevo control por lote';
    el('form-cs-lote-codigo').classList.add('oculto');
    el('cs-lote-fecha').value = fechaHoyISO();
    productosControlLote = [];
    await poblarSelectsControlLote();
    renderizarProductosControlLote();
    await actualizarInfoAnimalesLote();
  }

  // El control por lote y el individual no se muestran a la vez.
  const individual = el('form-cs-contenedor');
  if (individual) individual.classList.add('oculto');

  snapshotsFormulario['form-cs-lote'] = capturarSnapshotFormulario('form-cs-lote');
  contenedor.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

async function poblarSelectsControlLote() {
  await poblarSelect('cs-lote-lote', await Db.obtenerCatalogo('lotes'), 'Selecciona un lote...');

  // Solo categorías con productos aplicables (mismo criterio que la web).
  const categorias = await categoriasProductoConProductos();
  await poblarSelect('cs-lote-producto-categoria', categorias, 'Selecciona...');

  const mensajeProductos = el('cs-lote-productos-mensaje');
  if (mensajeProductos && !categorias.length) {
    mensajeProductos.textContent = 'No hay productos cargados para este fundo. Sincroniza los catálogos.';
  }

  await poblarProductosControlLote();
}

async function poblarProductosControlLote() {
  const select = el('cs-lote-producto');
  if (!select) return;

  const categoriaId = el('cs-lote-producto-categoria').value;
  const productos = (await Db.obtenerCatalogo('productos_control'))
    .filter((producto) => !categoriaId || String(producto.categoria_id) === String(categoriaId));

  select.innerHTML = '';
  const opcionVacia = document.createElement('option');
  opcionVacia.value = '';
  opcionVacia.textContent = categoriaId ? 'Selecciona un producto...' : 'Selecciona una categoría...';
  select.appendChild(opcionVacia);

  productos.forEach((producto) => {
    const opcion = document.createElement('option');
    opcion.value = String(producto.id);
    opcion.textContent = producto.nombre;
    select.appendChild(opcion);
  });

  select.disabled = !productos.length;
}

async function agregarProductoControlLote() {
  const productoId = el('cs-lote-producto').value;
  if (!productoId) {
    el('cs-lote-productos-mensaje').textContent = 'Selecciona la categoría y el producto.';
    return;
  }

  const dosis = el('cs-lote-producto-dosis').value.trim();
  if (!dosis) {
    el('cs-lote-productos-mensaje').textContent = 'Indica la dosis aplicada.';
    return;
  }

  const producto = (await Db.obtenerCatalogo('productos_control'))
    .find((item) => String(item.id) === String(productoId));

  productosControlLote.push({
    mproductos_id: Number(productoId),
    producto_nombre: producto ? producto.nombre : '',
    dosis,
    detalles: el('cs-lote-producto-detalles').value.trim(),
  });

  el('cs-lote-producto-dosis').value = '';
  el('cs-lote-producto-detalles').value = '';
  el('cs-lote-producto').value = '';
  el('cs-lote-producto-categoria').value = '';
  el('cs-lote-productos-mensaje').textContent = '';
  await poblarProductosControlLote();
  renderizarProductosControlLote();
}

function renderizarProductosControlLote() {
  const lista = el('cs-lote-productos-lista');
  if (!lista) return;

  if (!productosControlLote.length) {
    lista.innerHTML = '<p class="texto-ayuda">Todavía no agregaste productos.</p>';
    return;
  }

  lista.innerHTML = productosControlLote.map((producto, indice) => `
    <div class="producto-control-item">
      <div class="producto-control-item__datos">
        <strong>${escaparHtml(producto.producto_nombre || 'Producto')}</strong>
        <span>Dosis: ${escaparHtml(producto.dosis || '')}</span>
        ${producto.detalles ? `<small>${escaparHtml(producto.detalles)}</small>` : ''}
      </div>
      <button type="button" class="producto-control-item__quitar" data-quitar-producto-lote="${indice}">Quitar</button>
    </div>
  `).join('');

  lista.querySelectorAll('[data-quitar-producto-lote]').forEach((boton) => {
    boton.addEventListener('click', () => {
      productosControlLote.splice(Number(boton.dataset.quitarProductoLote), 1);
      renderizarProductosControlLote();
    });
  });
}

async function animalesDelLote(loteId) {
  if (!loteId) return [];
  const animales = await Animales.listar();
  // Solo animales ACTIVOS: los que están de baja (mestatus_id != 1) no
  // deben recibir controles sanitarios del lote ni contarse en el aviso.
  return animales.filter((animal) =>
    Number(animal.status ?? 1) === 1
    && Number(animal.mestatus_id ?? 1) === 1
    && String(animal.mlotes_id) === String(loteId));
}

async function actualizarInfoAnimalesLote() {
  const loteId = el('cs-lote-lote').value;
  if (!loteId) {
    el('cs-lote-animales-info').textContent = 'Elegí el lote: el control se aplica a todos sus animales activos.';
    return;
  }
  const animales = await animalesDelLote(loteId);
  el('cs-lote-animales-info').textContent = animales.length
    ? 'Se aplicará a ' + animales.length + ' animal(es) activo(s) del lote.'
    : 'Este lote no tiene animales activos en este dispositivo.';
}

async function abrirEdicionControlLote(uuidLocal, tarjetaEl) {
  const registro = await Db.obtener('control_sanitario_lotes', uuidLocal);
  if (!registro) return;

  edicionControlLoteUuid = uuidLocal;
  productosControlLote = Array.isArray(registro.productos_aplicados)
    ? registro.productos_aplicados.map((producto) => ({ ...producto }))
    : [];

  if (tarjetaEl) moverFormularioAntesDe('form-cs-lote-contenedor', tarjetaEl);
  await poblarSelectsControlLote();

  el('form-cs-lote-titulo').textContent = 'Editar control por lote';
  if (registro.codigo_mcs_lote) {
    el('form-cs-lote-codigo').textContent = registro.codigo_mcs_lote;
    el('form-cs-lote-codigo').classList.remove('oculto');
  } else {
    el('form-cs-lote-codigo').classList.add('oculto');
  }

  el('cs-lote-lote').value = registro.mlotes_id ? String(registro.mlotes_id) : '';
  el('cs-lote-lote').disabled = true;
  el('cs-lote-fecha').value = comoFechaInput(registro.fecha_control);
  el('cs-lote-fecha').disabled = true;
  el('cs-lote-fecha-prox').value = comoFechaInput(registro.fecha_prox_control);
  el('cs-lote-evento').value = registro.evento || '';
  el('cs-lote-responsable').value = registro.responsable || '';
  el('cs-lote-observaciones').value = registro.observaciones || '';

  renderizarProductosControlLote();
  await actualizarInfoAnimalesLote();
  el('form-cs-lote-mensaje').textContent = '';

  await alternarFormularioControlLote(true);
  el('form-cs-lote-contenedor').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

async function manejarGuardarControlLote(evento) {
  evento.preventDefault();
  const sesion = await Db.obtenerSesion();

  const loteId = el('cs-lote-lote').value;
  const animales = await animalesDelLote(loteId);
  const lotes = await Db.obtenerCatalogo('lotes');
  const lote = lotes.find((item) => String(item.id) === String(loteId));

  const datosFormulario = {
    mlotes_id: loteId,
    lote_nombre: lote ? lote.nombre : '',
    fecha_control: el('cs-lote-fecha').value,
    fecha_prox_control: el('cs-lote-fecha-prox').value,
    evento: el('cs-lote-evento').value.trim(),
    responsable: el('cs-lote-responsable').value.trim(),
    observaciones: el('cs-lote-observaciones').value.trim(),
    productos_aplicados: productosControlLote,
    animales: animales.map((animal) => animal.codigo_rgb_id_animal),
  };

  if (!datosFormulario.mlotes_id || !datosFormulario.fecha_control
    || !datosFormulario.evento || !datosFormulario.responsable) {
    el('form-cs-lote-mensaje').textContent = 'Completa lote, fecha, evento y responsable.';
    return;
  }

  if (!datosFormulario.animales.length) {
    el('form-cs-lote-mensaje').textContent = 'El lote no tiene animales activos en este dispositivo. Sincroniza e intenta de nuevo.';
    return;
  }

  if (edicionControlLoteUuid) {
    await ControlSanitarioLotes.editar(sesion, edicionControlLoteUuid, datosFormulario);
    mostrarToast('Control por lote actualizado');
  } else {
    await ControlSanitarioLotes.crear(sesion, datosFormulario);
    mostrarToast('Control por lote guardado · ' + datosFormulario.animales.length + ' animal(es)');
  }

  await alternarFormularioControlLote(false);
  await renderizarListaControlLote();
  await actualizarContadorPendientes();
}

async function renderizarListaControlLote() {
  const registros = await ControlSanitarioLotes.listar();
  const sesion = await Db.obtenerSesion();
  const modoConsulta = sesion && sesion.modo_offline === 'consulta';
  const contenedor = el('lista-control-lote');
  if (!contenedor) return;
  if (formularioAbiertoEnLista(contenedor)) return;
  rescatarFormulariosMovidos(contenedor);
  contenedor.innerHTML = '';

  if (!registros.length) {
    contenedor.innerHTML = '<p class="texto-vacio">Todavia no hay controles por lote registrados en este dispositivo.</p>';
    if (el('buscador-control-lote-contador')) el('buscador-control-lote-contador').classList.add('oculto');
    actualizarContadoresFiltroControl();
    return;
  }

  const estadoTexto = {
    pendiente: 'Pendiente de sincronizar',
    sincronizado: 'Sincronizado',
    conflicto: 'En conflicto',
    error: 'Error al sincronizar',
  };

  for (const registro of registros) {
    const totalAnimales = Number(registro.total_animales)
      || (Array.isArray(registro.animales) ? registro.animales.length : 0);

    const tarjeta = document.createElement('article');
    tarjeta.className = 'chapeta chapeta--' + (registro.estado_sync || 'pendiente');
    tarjeta.dataset.busqueda = [
      registro.codigo_mcs_lote,
      registro.lote_nombre,
      registro.evento,
      registro.responsable,
    ].filter(Boolean).join(' ').toLowerCase();
    tarjeta.innerHTML = `
      <div class="chapeta__codigo">${escaparHtml(registro.codigo_mcs_lote || 'RCS pendiente')}</div>
      <div class="chapeta__nombre">${escaparHtml(registro.evento || '(sin evento)')}</div>
      <div class="chapeta__detalle">${escaparHtml(formatearFecha(registro.fecha_control) || '—')} · ${escaparHtml(registro.lote_nombre || 'Lote')} · ${totalAnimales} animal(es)</div>
      <div class="chapeta__pie">
        <div class="chapeta__estado">${estadoSyncTexto(registro, estadoTexto)}</div>
        ${modoConsulta
          ? `<button class="chapeta__boton-ver" type="button" data-ver-registro="${escaparHtml(registro.uuid_local)}" data-modulo="control_sanitario_lotes" aria-label="Ver detalle del control por lote"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>Ver detalle</button>`
          : `<button class="chapeta__boton-editar" type="button" data-editar-control-lote="${escaparHtml(registro.uuid_local)}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>Editar</button>`}
      </div>
    `;
    contenedor.appendChild(tarjeta);
  }

  contenedor.querySelectorAll('button[data-ver-registro][data-modulo="control_sanitario_lotes"]').forEach((boton) => {
    boton.addEventListener('click', () => abrirDetalleRegistro(boton.dataset.modulo, boton.dataset.verRegistro, boton));
  });

  contenedor.querySelectorAll('button[data-editar-control-lote]').forEach((boton) => {
    boton.addEventListener('click', () => abrirEdicionControlLote(boton.dataset.editarControlLote, boton.closest('.chapeta')));
  });

  filtrarListaPorAnimal('buscador-control-lote', 'lista-control-lote', 'buscador-control-lote-contador');
  actualizarContadoresFiltroControl();
}

/* ============================================================
   BAJAS (desincorporación)
   ============================================================ */

async function alternarFormularioDesincorporacion(mostrar) {
  const contenedor = el('form-desincorporacion-contenedor');
  if (!contenedor) return;

  contenedor.classList.toggle('oculto', !mostrar);

  el('form-desincorporacion').reset();
  el('desinc-animal').value = '';
  el('desinc-animal-buscar').value = '';
  el('desinc-animal-mostrar').classList.add('oculto');
  el('form-desincorporacion-mensaje').textContent = '';
  alternarCamposCausaBaja();

  if (!mostrar) {
    restaurarFormularioASuLugar('form-desincorporacion-contenedor', 'form-desincorporacion-anchor');
    return;
  }

  el('desinc-fecha').value = fechaHoyISO();
  await poblarSelectAnimalesDesincorporacion();
  await poblarCausasBaja();
  restaurarFormularioASuLugar('form-desincorporacion-contenedor', 'form-desincorporacion-anchor');
  snapshotsFormulario['form-desincorporacion'] = capturarSnapshotFormulario('form-desincorporacion');
  contenedor.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

/** Valor centinela para la opción "Otra" de las causas. */
const CAUSA_BAJA_OTRA = '__otra__';

/**
 * Llena las listas de causas con el catálogo descargado del servidor,
 * igual que la web. Antes eran campos de texto libre y quedaban causas
 * escritas a mano que después no coincidían con el catálogo.
 * Siempre queda la opción "Otra" para no bloquear un caso no previsto.
 */
async function poblarCausasBaja() {
  let catalogos = { causas_descarte: [], causas_mortalidad: [] };
  try {
    catalogos = await catalogosCausasBaja();
  } catch (e) {
    catalogos = { causas_descarte: [], causas_mortalidad: [] };
  }

  const llenar = (idSelect, items) => {
    const select = el(idSelect);
    if (!select) return;
    const opciones = ['<option value="">Selecciona...</option>'];
    (items || []).forEach((item) => {
      opciones.push('<option value="' + escaparHtml(String(item.id)) + '">'
        + escaparHtml(item.nombre) + '</option>');
    });
    opciones.push('<option value="' + CAUSA_BAJA_OTRA + '">Otra (escribir)</option>');
    select.innerHTML = opciones.join('');
  };

  llenar('desinc-causa-descarte', catalogos.causas_descarte);
  llenar('desinc-causa-mortalidad', catalogos.causas_mortalidad);
  alternarCausaOtra('desinc-causa-descarte', 'desinc-causa-descarte-otra');
  alternarCausaOtra('desinc-causa-mortalidad', 'desinc-causa-mortalidad-otra');
}

/** Muestra el campo de texto solo cuando se eligió "Otra". */
function alternarCausaOtra(idSelect, idTexto) {
  const select = el(idSelect);
  const texto = el(idTexto);
  if (!select || !texto) return;
  const esOtra = select.value === CAUSA_BAJA_OTRA;
  texto.classList.toggle('oculto', !esOtra);
  if (!esOtra) texto.value = '';
}

/** Causa elegida: el id del catálogo o el texto libre de "Otra". */
function causaBajaSeleccionada(idSelect, idTexto) {
  const select = el(idSelect);
  if (!select) return '';
  if (select.value === CAUSA_BAJA_OTRA) {
    return (el(idTexto)?.value || '').trim();
  }
  return select.value;
}

/** Solo animales activos: un animal ya dado de baja no se puede dar de baja otra vez. */
async function poblarSelectAnimalesDesincorporacion() {
  const animales = (await Animales.listar()).filter((animal) =>
    Number(animal.status ?? 1) === 1 && Number(animal.mestatus_id ?? 1) === 1);
  BuscadorAnimal.poblar('desinc-animal', animales, 'Selecciona...');
}

/** La causa cambia según el tipo: descarte o muerte. */
function alternarCamposCausaBaja() {
  const tipo = Number(el('desinc-tipo').value);
  el('desinc-campo-descarte').classList.toggle('oculto', tipo !== 2);
  el('desinc-campo-mortalidad').classList.toggle('oculto', tipo !== 3);
}

async function manejarGuardarDesincorporacion(evento) {
  evento.preventDefault();

  const mensaje = el('form-desincorporacion-mensaje');
  mensaje.textContent = '';

  const sesion = await Db.obtenerSesion();
  const datosFormulario = {
    codigo_rgb_id_animal: (el('desinc-animal').value || el('desinc-animal-buscar').value || '').trim(),
    tipo_desincorporacion: el('desinc-tipo').value,
    f_desincorporacion: el('desinc-fecha').value,
    causa_descarte: causaBajaSeleccionada('desinc-causa-descarte', 'desinc-causa-descarte-otra'),
    causa_mortalidad: causaBajaSeleccionada('desinc-causa-mortalidad', 'desinc-causa-mortalidad-otra'),
    observaciones: el('desinc-observaciones').value.trim(),
  };

  if (!datosFormulario.codigo_rgb_id_animal) {
    mensaje.textContent = 'Elegí el animal que se da de baja.';
    return;
  }
  if (!datosFormulario.tipo_desincorporacion) {
    mensaje.textContent = 'Elegí el tipo de baja.';
    return;
  }
  if (!datosFormulario.f_desincorporacion) {
    mensaje.textContent = 'Indicá la fecha de la baja.';
    return;
  }

  try {
    await Desincorporacion.crear(sesion, datosFormulario);
  } catch (e) {
    mensaje.textContent = e.message;
    mostrarToast(e.message || 'No se pudo registrar la baja.', 'error');
    return;
  }

  mostrarToast('Baja registrada · ' + (Desincorporacion.nombreTipo(datosFormulario.tipo_desincorporacion) || 'Baja'));

  await alternarFormularioDesincorporacion(false);
  await renderizarListaDesincorporacion();
  await renderizarLista();
  await actualizarContadorPendientes();
}

/**
 * Bajas del rebaño: los animales que salieron del hato (mestatus_id ≠ 1:
 * descarte, muerte, venta, extravío o migración a otro fundo) junto con su
 * registro de baja.
 *
 * Cada animal aparece UNA sola vez: la tarjeta se arma con el animal —así
 * siempre tiene su ficha e historial— y, si el dispositivo tiene el
 * registro de baja, se completa con la fecha, la causa y el botón
 * "Ver baja". Un animal de baja sin registro local (por ejemplo, uno que
 * se dio de baja desde la web y todavía no se descargó) igual aparece; y
 * un registro cuyo animal no esté en el equipo también, para no perder
 * ninguna baja.
 */
async function renderizarListaDesincorporacion() {
  const contenedor = el('lista-desincorporacion');
  if (!contenedor) return;
  if (formularioAbiertoEnLista(contenedor)) return;
  rescatarFormulariosMovidos(contenedor);

  const registros = await Desincorporacion.listar();
  const animales = (await Animales.listar()).filter((a) => Number(a.mestatus_id ?? 1) !== 1);
  const catalogoCausas = await catalogosCausasBaja();
  const clave = (codigo) => String(codigo || '').trim().toUpperCase();

  // Código → baja más reciente, y código → animal. Los dos almacenes se
  // cruzan por código, que es el dato que comparten.
  const bajaPorCodigo = new Map();
  for (const registro of registros) {
    const codigo = clave(registro.codigo_rgb_id_animal);
    const previa = bajaPorCodigo.get(codigo);
    if (!previa || String(registro.f_desincorporacion || '') > String(previa.f_desincorporacion || '')) {
      bajaPorCodigo.set(codigo, registro);
    }
  }

  const animalPorCodigo = new Map();
  animales.forEach((animal) => animalPorCodigo.set(clave(animal.codigo_rgb_id_animal), animal));

  const filas = [];
  new Set([...animalPorCodigo.keys(), ...bajaPorCodigo.keys()]).forEach((codigo) => {
    const animal = animalPorCodigo.get(codigo) || null;
    const registro = bajaPorCodigo.get(codigo) || null;
    const origenEstado = registro || animal || {};
    filas.push({
      codigo,
      animal,
      registro,
      tipo: registro
        ? (Desincorporacion.nombreTipo(registro.tipo_desincorporacion) || 'Baja')
        : (estadoBajaTexto(animal) || 'De baja'),
      causa: registro ? causaBajaTexto(registro, catalogoCausas) : '',
      fecha: registro ? (formatearFecha(registro.f_desincorporacion) || '') : '',
      estado: origenEstado.estado_sync || 'pendiente',
      error: origenEstado.ultimo_error || '',
      orden: String((registro && registro.f_desincorporacion)
        || (animal && (animal.updated || animal.created))
        || ''),
    });
  });

  // Primero lo que necesita atención (conflicto, error, pendiente) y
  // después la baja más reciente.
  const prioridad = { conflicto: 0, error: 1, pendiente: 2, sincronizado: 3 };
  filas.sort((a, b) => {
    const porEstado = (prioridad[a.estado] ?? 9) - (prioridad[b.estado] ?? 9);
    if (porEstado !== 0) return porEstado;
    return b.orden.localeCompare(a.orden);
  });

  contenedor.innerHTML = '';

  if (!filas.length) {
    contenedor.innerHTML = '<p class="texto-vacio">Todavía no hay bajas registradas en este dispositivo.</p>';
    if (el('buscador-desincorporacion-contador')) el('buscador-desincorporacion-contador').classList.add('oculto');
    return;
  }

  const sexoTexto = { 1: 'Hembra', 2: 'Macho' };
  const estadoTexto = {
    pendiente: 'Pendiente de sincronizar',
    sincronizado: 'Sincronizado',
    conflicto: 'En conflicto',
    error: 'Error al sincronizar',
  };

  for (const fila of filas) {
    const animal = fila.animal;
    const registro = fila.registro;

    const detalle = [];
    if (animal) {
      detalle.push(sexoTexto[animal.sexo] || '—');
      detalle.push('Arete ' + (animal.nro_arete || '—'));
    }
    if (fila.fecha) detalle.push('Baja ' + fila.fecha);

    const tarjeta = document.createElement('article');
    tarjeta.className = 'chapeta chapeta--baja chapeta--' + fila.estado;
    tarjeta.dataset.busqueda = [
      fila.codigo,
      animal && animal.nombre,
      animal && animal.nro_arete,
      fila.tipo,
      fila.causa,
      fila.fecha,
      registro && registro.observaciones,
    ].filter(Boolean).join(' ').toLowerCase();

    const botonDetalle = registro
      ? `<button class="chapeta__boton-ver" type="button" data-ver-registro="${escaparHtml(registro.uuid_local)}" data-modulo="desincorporacion" aria-label="Ver el detalle de la baja"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>Ver baja</button>`
      : '';

    const botonFicha = animal
      ? `<button class="chapeta__boton-ficha" type="button" data-ficha-animal="${escaparHtml(animal.uuid_local)}" aria-label="Ver ficha e historial de ${escaparHtml(animal.nombre || fila.codigo || 'este animal')}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="M9 13h6"/><path d="M9 17h6"/></svg>Ficha</button>`
      : '';

    tarjeta.innerHTML = `
      <div class="chapeta__codigo">${escaparHtml(fila.codigo || '—')} <span class="chapeta__baja">Baja · ${escaparHtml(fila.tipo)}</span></div>
      <div class="chapeta__nombre">${escaparHtml((animal && animal.nombre) || '(sin ficha en este equipo)')}</div>
      <div class="chapeta__detalle">${escaparHtml(detalle.join(' · ') || '—')}</div>
      ${fila.causa ? `<div class="chapeta__detalle">Causa: ${escaparHtml(fila.causa)}</div>` : ''}
      <div class="chapeta__pie">
        <div class="chapeta__estado">${estadoSyncTexto({ estado_sync: fila.estado, ultimo_error: fila.error }, estadoTexto)}</div>
        <div class="chapeta__acciones">${botonDetalle}${botonFicha}</div>
      </div>
    `;
    contenedor.appendChild(tarjeta);
  }

  contenedor.querySelectorAll('button[data-ficha-animal]').forEach((boton) => {
    boton.addEventListener('click', () => FichaAnimal.abrir(boton.dataset.fichaAnimal, boton));
  });

  contenedor.querySelectorAll('button[data-ver-registro][data-modulo="desincorporacion"]').forEach((boton) => {
    boton.addEventListener('click', () => abrirDetalleRegistro(boton.dataset.modulo, boton.dataset.verRegistro, boton));
  });

  filtrarListaPorAnimal('buscador-desincorporacion', 'lista-desincorporacion', 'buscador-desincorporacion-contador');
}

/* ============================================================
   MOVIMIENTO DE REBAÑO
   ============================================================ */

async function alternarFormularioMovimientoRebano(mostrar) {
  const contenedor = el('form-movreb-contenedor');
  if (!contenedor) return;

  contenedor.classList.toggle('oculto', !mostrar);

  el('form-movreb').reset();
  el('form-movreb-mensaje').textContent = '';
  el('movreb-animales-info').textContent = '';
  el('movreb-animales-lista').innerHTML = '';

  if (!mostrar) {
    restaurarFormularioASuLugar('form-movreb-contenedor', 'form-movreb-anchor');
    return;
  }

  el('movreb-fecha').value = fechaHoyISO();
  await poblarSelectsMovimientoRebano();
  alternarCamposTipoMovimiento();
  restaurarFormularioASuLugar('form-movreb-contenedor', 'form-movreb-anchor');
  snapshotsFormulario['form-movreb'] = capturarSnapshotFormulario('form-movreb');
  contenedor.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

/** Muestra solo los selectores de origen/destino que usa el tipo elegido. */
function alternarCamposTipoMovimiento() {
  const tipo = String(el('movreb-tipo').value || 'LOTE').toUpperCase();
  const usaLote = tipo === 'LOTE' || tipo === 'AMBOS';
  const usaPotrero = tipo === 'POTRERO' || tipo === 'AMBOS';

  el('movreb-campo-lote-origen').classList.toggle('oculto', !usaLote);
  el('movreb-campo-lote-destino').classList.toggle('oculto', !usaLote);
  el('movreb-campo-potrero-origen').classList.toggle('oculto', !usaPotrero);
  el('movreb-campo-potrero-destino').classList.toggle('oculto', !usaPotrero);

  // Al cambiar el origen hay que volver a pedir la lista de animales.
  el('movreb-animales-lista').innerHTML = '';
  el('movreb-animales-info').textContent = 'Elegí el origen y tocá "Cargar animales del origen".';
}

async function poblarSelectsMovimientoRebano() {
  const lotes = await Db.obtenerCatalogo('lotes');
  const potreros = await Db.obtenerCatalogo('potreros');

  poblarSelect('movreb-lote-origen', lotes, 'Selecciona un lote...');
  poblarSelect('movreb-lote-destino', lotes, 'Selecciona un lote...');
  poblarSelect('movreb-potrero-origen', potreros, 'Selecciona un potrero...');

  const destino = el('movreb-potrero-destino');
  destino.innerHTML = '<option value="">Sin potrero</option>';
  potreros.forEach((potrero) => {
    const opcion = document.createElement('option');
    opcion.value = String(potrero.id);
    opcion.textContent = potrero.nombre;
    destino.appendChild(opcion);
  });

  const aviso = el('movreb-potreros-aviso');
  if (!potreros.length) {
    aviso.textContent = 'No hay potreros cargados en el dispositivo: solo se puede mover entre lotes. Sincronizá para actualizar el catálogo.';
    aviso.classList.remove('oculto');
    if (String(el('movreb-tipo').value).toUpperCase() !== 'LOTE') {
      el('movreb-tipo').value = 'LOTE';
    }
  } else {
    aviso.classList.add('oculto');
  }

  el('movreb-animales-info').textContent = 'Elegí el origen y tocá "Cargar animales del origen".';
}

/** Animales activos que están hoy en el lote/potrero de origen. */
async function cargarAnimalesOrigenMovimiento() {
  const tipo = String(el('movreb-tipo').value || 'LOTE').toUpperCase();
  const usaLote = tipo === 'LOTE' || tipo === 'AMBOS';
  const usaPotrero = tipo === 'POTRERO' || tipo === 'AMBOS';

  const loteId = el('movreb-lote-origen').value;
  const potreroId = el('movreb-potrero-origen').value;

  if (usaLote && !loteId) {
    el('movreb-animales-info').textContent = 'Elegí el lote de origen.';
    return;
  }
  if (usaPotrero && !potreroId) {
    el('movreb-animales-info').textContent = 'Elegí el potrero de origen.';
    return;
  }

  const animales = await Animales.listar();
  const delOrigen = animales.filter((animal) => {
    if (Number(animal.status ?? 1) !== 1) return false;
    if (Number(animal.mestatus_id ?? 1) !== 1) return false;
    if (usaLote && String(animal.mlotes_id ?? '') !== String(loteId)) return false;
    if (usaPotrero && String(animal.mpotreros_id ?? '') !== String(potreroId)) return false;
    return true;
  });

  renderizarAnimalesOrigenMovimiento(delOrigen);

  el('movreb-animales-info').textContent = delOrigen.length
    ? delOrigen.length + ' animal(es) activo(s) en el origen. Marcá los que se mueven.'
    : 'El origen elegido no tiene animales activos en este dispositivo.';
}

function renderizarAnimalesOrigenMovimiento(animales) {
  const contenedor = el('movreb-animales-lista');
  contenedor.innerHTML = '';

  if (!animales.length) return;

  const encabezado = document.createElement('label');
  encabezado.className = 'movreb-seleccion-todos';
  encabezado.innerHTML = '<input type="checkbox" id="movreb-seleccionar-todos"> Seleccionar todos';
  contenedor.appendChild(encabezado);

  animales.forEach((animal) => {
    const fila = document.createElement('label');
    fila.className = 'movreb-animal';
    const codigo = animal.codigo_rgb_id_animal || '';
    fila.innerHTML = `
      <input type="checkbox" class="movreb-animal-check" id="movreb-check-${escaparHtml(codigo)}" value="${escaparHtml(codigo)}">
      <span class="movreb-animal__codigo">${escaparHtml(codigo)}</span>
      <span class="movreb-animal__nombre">${escaparHtml(animal.nombre || '')}</span>
    `;
    contenedor.appendChild(fila);
  });

  const todos = el('movreb-seleccionar-todos');
  if (todos) {
    todos.addEventListener('change', () => {
      contenedor.querySelectorAll('.movreb-animal-check').forEach((check) => {
        check.checked = todos.checked;
      });
    });
  }
}

async function manejarGuardarMovimientoRebano(evento) {
  evento.preventDefault();

  const mensaje = el('form-movreb-mensaje');
  mensaje.textContent = '';

  const sesion = await Db.obtenerSesion();
  const tipo = String(el('movreb-tipo').value || 'LOTE').toUpperCase();
  const usaLote = tipo === 'LOTE' || tipo === 'AMBOS';
  const usaPotrero = tipo === 'POTRERO' || tipo === 'AMBOS';

  const animales = Array.from(document.querySelectorAll('#movreb-animales-lista .movreb-animal-check'))
    .filter((check) => check.checked)
    .map((check) => check.value);

  if (!animales.length) {
    mensaje.textContent = 'Cargá los animales del origen y marcá al menos uno.';
    return;
  }

  const lotes = await Db.obtenerCatalogo('lotes');
  const potreros = await Db.obtenerCatalogo('potreros');
  const nombreDe = (lista, id) => {
    const item = lista.find((elemento) => String(elemento.id) === String(id));
    return item ? item.nombre : '';
  };

  const loteOrigenId = usaLote ? el('movreb-lote-origen').value : '';
  const loteDestinoId = usaLote ? el('movreb-lote-destino').value : '';
  const potreroOrigenId = usaPotrero ? el('movreb-potrero-origen').value : '';
  const potreroDestinoId = usaPotrero ? el('movreb-potrero-destino').value : '';

  if (usaLote) {
    if (!loteOrigenId) { mensaje.textContent = 'Elegí el lote de origen.'; return; }
    if (!loteDestinoId) { mensaje.textContent = 'Elegí el lote de destino.'; return; }
    if (String(loteOrigenId) === String(loteDestinoId)) {
      mensaje.textContent = 'El lote de destino debe ser distinto al de origen.';
      return;
    }
  }
  if (usaPotrero) {
    if (!potreroOrigenId) { mensaje.textContent = 'Elegí el potrero de origen.'; return; }
    if (String(potreroOrigenId) === String(potreroDestinoId)) {
      mensaje.textContent = 'El potrero de destino debe ser distinto al de origen.';
      return;
    }
  }

  const datosFormulario = {
    tipo_movimiento: tipo,
    mlotes_origen_id: loteOrigenId,
    mlotes_destino_id: loteDestinoId,
    mpotreros_origen_id: potreroOrigenId,
    mpotreros_destino_id: potreroDestinoId,
    lote_origen_nombre: nombreDe(lotes, loteOrigenId),
    lote_destino_nombre: nombreDe(lotes, loteDestinoId),
    potrero_origen_nombre: nombreDe(potreros, potreroOrigenId),
    potrero_destino_nombre: nombreDe(potreros, potreroDestinoId),
    fecha_movimiento: el('movreb-fecha').value,
    motivo: el('movreb-motivo').value,
    observaciones: el('movreb-observaciones').value,
    animales: animales,
  };

  try {
    await MovimientoRebano.crear(sesion, datosFormulario);
  } catch (e) {
    mensaje.textContent = e.message;
    mostrarToast(e.message || 'No se pudo registrar el movimiento.', 'error');
    return;
  }

  mostrarToast('Movimiento guardado · ' + animales.length + ' animal(es)');

  await alternarFormularioMovimientoRebano(false);
  await renderizarListaMovimientoRebano();
  await renderizarLista();
  await actualizarContadorPendientes();
}

async function renderizarListaMovimientoRebano() {
  const registros = await MovimientoRebano.listar();
  const contenedor = el('lista-movimiento-rebano');
  if (!contenedor) return;
  if (formularioAbiertoEnLista(contenedor)) return;
  rescatarFormulariosMovidos(contenedor);
  contenedor.innerHTML = '';

  if (!registros.length) {
    contenedor.innerHTML = '<p class="texto-vacio">Todavía no hay movimientos de rebaño registrados en este dispositivo.</p>';
    return;
  }

  const estadoTexto = {
    pendiente: 'Pendiente de sincronizar',
    sincronizado: 'Sincronizado',
    conflicto: 'En conflicto',
    error: 'Error al sincronizar',
  };

  for (const registro of registros) {
    const codigos = (Array.isArray(registro.animales) ? registro.animales : [])
      .map((animal) => (typeof animal === 'string' ? animal : (animal && animal.codigo_rgb_id_animal) || ''))
      .filter(Boolean);
    const total = Number(registro.total_animales) || codigos.length;

    const origen = [
      registro.lote_origen_nombre,
      registro.potrero_origen_nombre,
    ].filter(Boolean).join(' · ') || '—';

    const destino = [
      registro.lote_destino_nombre,
      registro.potrero_destino_nombre,
    ].filter(Boolean).join(' · ') || '—';

    const tarjeta = document.createElement('article');
    tarjeta.className = 'chapeta chapeta--' + (registro.estado_sync || 'pendiente')
      + (registro.revertido === true ? ' chapeta--revertido' : '');
    tarjeta.dataset.busqueda = [
      registro.motivo,
      origen,
      destino,
      codigos.join(' '),
    ].filter(Boolean).join(' ').toLowerCase();
    tarjeta.innerHTML = `
      <div class="chapeta__codigo">${escaparHtml(MovimientoRebano.nombreTipo(registro.tipo_movimiento) || 'Movimiento')}</div>
      <div class="chapeta__nombre">${escaparHtml(registro.motivo || '(sin motivo)')}</div>
      <div class="chapeta__detalle">${escaparHtml(formatearFecha(registro.fecha_movimiento) || '—')} · ${escaparHtml(origen)} → ${escaparHtml(destino)} · ${total} animal(es)</div>
      ${registro.revertido === true
        ? `<div class="chapeta__detalle">Revertido${registro.motivo_reversion ? ': ' + escaparHtml(registro.motivo_reversion) : ''}</div>`
        : ''}
      <div class="chapeta__pie">
        <div class="chapeta__estado">${registro.revertido === true ? 'Revertido' : estadoSyncTexto(registro, estadoTexto)}</div>
        ${MovimientoRebano.puedeRevertir(registro)
          ? `<button class="chapeta__boton-revertir" type="button" data-revertir-movimiento="${escaparHtml(registro.uuid_local)}" aria-label="Revertir el movimiento"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5"/></svg>Revertir</button>`
          : ''}
        <button class="chapeta__boton-ver" type="button" data-ver-registro="${escaparHtml(registro.uuid_local)}" data-modulo="movimiento_rebano" aria-label="Ver detalle del movimiento"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>Ver detalle</button>
      </div>
      <div class="chapeta__reversion oculto" data-reversion-panel>
        <label class="chapeta__reversion-label" for="reversion-motivo-${escaparHtml(registro.uuid_local)}">Motivo de la reversión</label>
        <textarea id="reversion-motivo-${escaparHtml(registro.uuid_local)}" class="chapeta__reversion-texto" rows="2" maxlength="250" placeholder="Mínimo 10 caracteres"></textarea>
        <p class="chapeta__reversion-aviso" aria-live="polite"></p>
        <div class="chapeta__reversion-acciones">
          <button class="chapeta__reversion-confirmar" type="button" data-confirmar-reversion="${escaparHtml(registro.uuid_local)}">Revertir en el sistema</button>
          <button class="chapeta__reversion-cancelar" type="button" data-cancelar-reversion>Cancelar</button>
        </div>
      </div>
    `;
    contenedor.appendChild(tarjeta);
  }

  contenedor.querySelectorAll('button[data-ver-registro][data-modulo="movimiento_rebano"]').forEach((boton) => {
    boton.addEventListener('click', () => abrirDetalleRegistro(boton.dataset.modulo, boton.dataset.verRegistro, boton));
  });

  contenedor.querySelectorAll('button[data-revertir-movimiento]').forEach((boton) => {
    boton.addEventListener('click', () => manejarRevertirMovimiento(boton.dataset.revertirMovimiento, boton));
  });

  contenedor.querySelectorAll('button[data-confirmar-reversion]').forEach((boton) => {
    boton.addEventListener('click', () => confirmarReversion(boton.dataset.confirmarReversion, boton));
  });

  contenedor.querySelectorAll('button[data-cancelar-reversion]').forEach((boton) => {
    boton.addEventListener('click', () => cerrarPanelReversion(boton.closest('.chapeta')));
  });
}

/**
 * Revierte un movimiento de rebaño que todavía NO llegó al servidor.
 * Deja los animales en su lote/potrero de origen, lo saca de la cola de
 * envío y borra el registro local. Si el movimiento ya está
 * sincronizado no se ofrece el botón (se revierte desde la web).
 */
async function manejarRevertirMovimiento(uuidLocal, boton) {
  const registro = await Db.obtener('movimiento_rebano', uuidLocal);
  if (!registro) return;

  const tarjeta = boton ? boton.closest('.chapeta') : null;

  // Ya está en el servidor: la reversión la hace el sistema y necesita
  // motivo. Se pide en un panel dentro de la tarjeta (no con prompt(),
  // que algunos navegadores embebidos bloquean).
  if (MovimientoRebano.estaEnServidor(registro)) {
    abrirPanelReversion(tarjeta);
    return;
  }

  // Todavía no llegó al servidor (pendiente o con el último envío fallido):
  // se deshace en el equipo y listo.
  const total = Number(registro.total_animales)
    || (Array.isArray(registro.animales) ? registro.animales.length : 0);
  const mensaje = '¿Revertir este movimiento?\n\n'
    + 'Todavía no se envió al servidor: se borra de este dispositivo y los '
    + total + ' animal(es) vuelven a su lote/potrero de origen.';

  if (!window.confirm(mensaje)) return;

  const sesion = await Db.obtenerSesion();
  try {
    await MovimientoRebano.revertir(sesion, uuidLocal);
  } catch (e) {
    window.alert(e.message);
    return;
  }

  await renderizarListaMovimientoRebano();
  await renderizarLista();
  await actualizarContadorPendientes();
}

/** Muestra el panel de motivo dentro de la tarjeta del movimiento. */
function abrirPanelReversion(tarjeta) {
  if (!tarjeta) return;
  const panel = tarjeta.querySelector('[data-reversion-panel]');
  if (!panel) return;

  panel.classList.remove('oculto');
  const texto = panel.querySelector('textarea');
  if (texto) {
    texto.value = '';
    texto.focus();
  }
}

function cerrarPanelReversion(tarjeta) {
  if (!tarjeta) return;
  const panel = tarjeta.querySelector('[data-reversion-panel]');
  if (!panel) return;
  panel.classList.add('oculto');
  const aviso = panel.querySelector('.chapeta__reversion-aviso');
  if (aviso) aviso.textContent = '';
}

/** Confirma la reversión de un movimiento ya sincronizado. */
async function confirmarReversion(uuidLocal, boton) {
  const tarjeta = boton ? boton.closest('.chapeta') : null;
  const panel = tarjeta ? tarjeta.querySelector('[data-reversion-panel]') : null;
  const texto = panel ? panel.querySelector('textarea') : null;
  const aviso = panel ? panel.querySelector('.chapeta__reversion-aviso') : null;
  const motivo = texto ? String(texto.value || '').trim() : '';

  if (motivo.length < 10) {
    if (aviso) {
      aviso.textContent = 'El motivo debe tener al menos 10 caracteres.';
    } else {
      window.alert('El motivo de la reversión debe tener al menos 10 caracteres.');
    }
    if (texto) texto.focus();
    return;
  }

  const sesion = await Db.obtenerSesion();
  try {
    await MovimientoRebano.revertirEnServidor(sesion, uuidLocal, motivo);
  } catch (e) {
    if (aviso) {
      aviso.textContent = e.message;
      return;
    }
    window.alert(e.message);
    return;
  }

  await renderizarListaMovimientoRebano();
  await renderizarLista();
  await actualizarContadorPendientes();
}

/**
 * Busca si el animal ya tiene un registro de ese módulo en la misma
 * fecha. Es la regla que aplica la web al guardar (una sola toma de
 * leche, un solo pesaje y un solo control por animal y día) y se
 * comprueba acá, antes de encolar, para que el operario lo corrija en
 * el momento y no cuando falle la sincronización.
 *
 * @param {string} almacen       'produccion_leche' | 'pesos' | 'control_sanitario'
 * @param {string} campoAnimal   campo con el código del animal
 * @param {string} campoFecha    campo con la fecha
 * @param {string} codigo        código del animal del formulario
 * @param {string} fecha         fecha del formulario (YYYY-MM-DD)
 * @param {string|null} uuidExcluir  registro que se está editando
 * @returns {Promise<object|null>}
 */
async function registroDuplicadoEnFecha(almacen, campoAnimal, campoFecha, codigo, fecha, uuidExcluir) {
  const codigoNormal = String(codigo || '').trim().toUpperCase();
  const fechaNormal = String(fecha || '').slice(0, 10);
  if (!codigoNormal || !fechaNormal) return null;

  let registros = [];
  try {
    registros = await Db.obtenerTodos(almacen);
  } catch (e) {
    return null; // sin almacén no se puede comparar: lo valida el servidor
  }

  return registros.find((registro) => {
    if (!registro) return false;
    if (uuidExcluir && registro.uuid_local === uuidExcluir) return false;
    if (Number(registro.status ?? 1) !== 1) return false;
    const mismoAnimal = String(registro[campoAnimal] || '').trim().toUpperCase() === codigoNormal;
    const mismaFecha = String(registro[campoFecha] || '').slice(0, 10) === fechaNormal;
    return mismoAnimal && mismaFecha;
  }) || null;
}

async function manejarGuardarProduccionLeche(evento) {
  evento.preventDefault();
  const sesion = await Db.obtenerSesion();

  const datosFormulario = {
    codigo_rgb_id_animal_hembra: el('pl-animal').value,
    fecha_produccion: el('pl-fecha').value,
    cantidad_leche_m: el('pl-litros-manana').value,
    cantidad_leche_t: el('pl-litros-tarde').value,
  };

  if (!datosFormulario.codigo_rgb_id_animal_hembra || !datosFormulario.fecha_produccion) {
    el('form-pl-mensaje').textContent = 'Completa animal y fecha.';
    return;
  }

  // Misma regla que la web: una sola producción por animal y fecha.
  const duplicado = await registroDuplicadoEnFecha(
    'produccion_leche', 'codigo_rgb_id_animal_hembra', 'fecha_produccion',
    datosFormulario.codigo_rgb_id_animal_hembra, datosFormulario.fecha_produccion,
    edicionProduccionLecheUuid
  );
  if (duplicado) {
    el('form-pl-mensaje').textContent = 'Ese animal ya tiene producción de leche registrada para el '
      + formatearFecha(datosFormulario.fecha_produccion) + '.';
    return;
  }

  if (edicionProduccionLecheUuid) {
    await ProduccionLeche.editar(sesion, edicionProduccionLecheUuid, datosFormulario);
    mostrarToast('Producción de leche actualizada');
  } else {
    await ProduccionLeche.crear(sesion, datosFormulario);
    mostrarToast('Producción de leche guardada');
  }

  alternarFormularioProduccionLeche(false);
  await renderizarListaProduccionLeche();
  await actualizarContadorPendientes();
}

async function manejarGuardarPesos(evento) {
  evento.preventDefault();
  const sesion = await Db.obtenerSesion();

  const datosFormulario = {
    codigo_rgb_id_animal: el('pesos-animal').value,
    fecha_pesaje: el('pesos-fecha').value,
    peso: el('pesos-valor').value,
  };

  if (!datosFormulario.codigo_rgb_id_animal || !datosFormulario.fecha_pesaje || !datosFormulario.peso) {
    el('form-pesos-mensaje').textContent = 'Completa animal, fecha y peso.';
    return;
  }

  // Misma regla que la web: un solo pesaje por animal y fecha.
  const duplicado = await registroDuplicadoEnFecha(
    'pesos', 'codigo_rgb_id_animal', 'fecha_pesaje',
    datosFormulario.codigo_rgb_id_animal, datosFormulario.fecha_pesaje,
    edicionPesosUuid
  );
  if (duplicado) {
    el('form-pesos-mensaje').textContent = 'Ese animal ya tiene un pesaje registrado para el '
      + formatearFecha(datosFormulario.fecha_pesaje) + '.';
    return;
  }

  if (edicionPesosUuid) {
    await Pesos.editar(sesion, edicionPesosUuid, datosFormulario);
    mostrarToast('Pesaje actualizado · ' + datosFormulario.peso + ' kg');
  } else {
    await Pesos.crear(sesion, datosFormulario);
    mostrarToast('Pesaje guardado · ' + datosFormulario.peso + ' kg');
  }

  alternarFormularioPesos(false);
  await renderizarListaPesos();
  await actualizarContadorPendientes();
}

// ---------------------------------------------------------------------
// Pesaje rápido por lote
//
// En el campo se pesa lote por lote: se elige el lote y la fecha, y la
// app va pidiendo el peso de cada animal activo uno detrás de otro
// (Enter guarda y pasa al siguiente). Cada peso se guarda como un
// registro normal del módulo 'pesos', así que la sincronización no
// cambia. Se muestra el último peso conocido y la ganancia diaria.
// ---------------------------------------------------------------------

function fechaHoyISO() {
  const hoy = new Date();
  return [hoy.getFullYear(), String(hoy.getMonth() + 1).padStart(2, '0'), String(hoy.getDate()).padStart(2, '0')].join('-');
}

async function alternarPesajePorLote(mostrar) {
  const contenedor = el('pesaje-lote-contenedor');
  if (!contenedor) return;

  contenedor.classList.toggle('oculto', !mostrar);

  if (!mostrar) {
    if (pesajePorLote && pesajePorLote.registrados.size && !window.confirm('Se registraron ' + pesajePorLote.registrados.size + ' pesajes. ¿Cerrar el pesaje por lote?')) {
      contenedor.classList.remove('oculto');
      return;
    }
    pesajePorLote = null;
    el('pesaje-lote-trabajo').classList.add('oculto');
    el('pesaje-lote-config').classList.remove('oculto');
    return;
  }

  await poblarSelect('pesaje-lote-lote', await Db.obtenerCatalogo('lotes'), 'Selecciona un lote...');
  el('pesaje-lote-fecha').value = fechaHoyISO();
  el('pesaje-lote-config-mensaje').textContent = '';
  el('pesaje-lote-mensaje').textContent = '';
  el('pesaje-lote-config').classList.remove('oculto');
  el('pesaje-lote-trabajo').classList.add('oculto');
  contenedor.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

async function iniciarPesajePorLote() {
  const loteId = el('pesaje-lote-lote').value;
  const fecha = el('pesaje-lote-fecha').value || fechaHoyISO();

  if (!loteId) {
    el('pesaje-lote-config-mensaje').textContent = 'Selecciona el lote que vas a pesar.';
    return;
  }

  const animales = (await Animales.listar())
    .filter((animal) => Number(animal.status ?? 1) === 1 && String(animal.mlotes_id) === String(loteId))
    .sort((a, b) => String(a.codigo_rgb_id_animal || '').localeCompare(String(b.codigo_rgb_id_animal || '')));

  if (!animales.length) {
    el('pesaje-lote-config-mensaje').textContent = 'Ese lote no tiene animales activos en este dispositivo.';
    return;
  }

  pesajePorLote = { loteId, fecha, animales, indice: 0, registrados: new Map() };
  el('pesaje-lote-config-mensaje').textContent = '';
  el('pesaje-lote-config').classList.add('oculto');
  el('pesaje-lote-trabajo').classList.remove('oculto');
  await mostrarAnimalPesajeLote();
}

async function mostrarAnimalPesajeLote() {
  if (!pesajePorLote) return;

  const total = pesajePorLote.animales.length;
  if (pesajePorLote.indice >= total) {
    await finalizarPesajePorLote(true);
    return;
  }
  if (pesajePorLote.indice < 0) pesajePorLote.indice = 0;

  const animal = pesajePorLote.animales[pesajePorLote.indice];
  pesajePorLote.ultimoPeso = await ultimoPesoAnimal(animal.codigo_rgb_id_animal);

  el('pesaje-lote-progreso').textContent = 'Animal ' + (pesajePorLote.indice + 1) + ' de ' + total;
  el('pesaje-lote-codigo').textContent = animal.codigo_rgb_id_animal || 'Sin código';
  el('pesaje-lote-nombre').textContent = [
    animal.nombre,
    animal.nro_arete ? 'Arete ' + animal.nro_arete : '',
    animal.categoria_nombre || '',
  ].filter(Boolean).join(' · ');
  el('pesaje-lote-anterior').textContent = pesajePorLote.ultimoPeso
    ? 'Último peso: ' + pesajePorLote.ultimoPeso.peso + ' kg (' + formatearFecha(pesajePorLote.ultimoPeso.fecha_pesaje) + ')'
    : 'Sin pesajes anteriores de este animal.';

  const registrado = pesajePorLote.registrados.get(animal.codigo_rgb_id_animal);
  el('pesaje-lote-peso').value = registrado != null ? String(registrado) : '';
  el('pesaje-lote-gdp').textContent = '';
  el('pesaje-lote-mensaje').textContent = '';
  el('boton-pesaje-lote-anterior').classList.toggle('oculto', pesajePorLote.indice === 0);
  el('pesaje-lote-peso').focus();
}

async function ultimoPesoAnimal(codigoAnimal) {
  if (!codigoAnimal) return null;
  const pesos = await Db.obtenerTodos('pesos');
  const previos = pesos
    .filter((registro) => registro.codigo_rgb_id_animal === codigoAnimal)
    .sort((a, b) => String(b.fecha_pesaje || '').localeCompare(String(a.fecha_pesaje || '')));
  return previos[0] || null;
}

function actualizarGananciaPesajeLote() {
  const texto = el('pesaje-lote-peso').value.trim();
  const salida = el('pesaje-lote-gdp');
  const ultimo = pesajePorLote && pesajePorLote.ultimoPeso;

  if (!texto || !ultimo || !ultimo.peso) {
    salida.textContent = '';
    return;
  }

  const peso = Number(String(texto).replace(',', '.'));
  const anterior = Number(ultimo.peso);
  if (!Number.isFinite(peso) || !Number.isFinite(anterior)) {
    salida.textContent = '';
    return;
  }

  const dias = diasEntreFechas(ultimo.fecha_pesaje, pesajePorLote.fecha);
  const diferencia = peso - anterior;
  if (!dias || dias <= 0) {
    salida.textContent = 'Diferencia con el último peso: ' + (diferencia >= 0 ? '+' : '') + diferencia.toFixed(1) + ' kg';
    return;
  }

  const gdp = diferencia / dias;
  salida.textContent = 'Diferencia: ' + (diferencia >= 0 ? '+' : '') + diferencia.toFixed(1)
    + ' kg en ' + dias + ' día(s) · Ganancia diaria: ' + gdp.toFixed(3) + ' kg/día';
}

function diasEntreFechas(desde, hasta) {
  const inicio = comoFechaInput(desde);
  const fin = comoFechaInput(hasta);
  if (!inicio || !fin) return 0;
  const [a1, m1, d1] = inicio.split('-').map(Number);
  const [a2, m2, d2] = fin.split('-').map(Number);
  if (!a1 || !a2) return 0;
  const fecha1 = new Date(a1, m1 - 1, d1);
  const fecha2 = new Date(a2, m2 - 1, d2);
  return Math.round((fecha2 - fecha1) / 86400000);
}

async function guardarPesajeLoteActual(avanzar) {
  if (!pesajePorLote || pesajeGuardando) return;

  const animal = pesajePorLote.animales[pesajePorLote.indice];
  if (!animal) return;

  const texto = el('pesaje-lote-peso').value.trim();

  // Sin peso se salta el animal (puede que no se haya podido pesar).
  if (!texto) {
    el('pesaje-lote-mensaje').textContent = 'Sin peso: se omite este animal.';
    if (avanzar) {
      pesajePorLote.indice += 1;
      await mostrarAnimalPesajeLote();
    }
    return;
  }

  const peso = Number(String(texto).replace(',', '.'));
  if (!Number.isFinite(peso) || peso <= 0 || peso > 1500) {
    el('pesaje-lote-mensaje').textContent = 'Escribe un peso válido entre 1 y 1500 kg.';
    return;
  }

  const sesion = await Db.obtenerSesion();
  pesajeGuardando = true;
  try {
    await Pesos.crear(sesion, {
      codigo_rgb_id_animal: animal.codigo_rgb_id_animal,
      fecha_pesaje: pesajePorLote.fecha,
      peso,
    }, { sincronizar: false });
  } finally {
    pesajeGuardando = false;
  }

  pesajePorLote.registrados.set(animal.codigo_rgb_id_animal, peso);
  pesajePorLote.indice += 1;
  await mostrarAnimalPesajeLote();
}

async function retrocederPesajeLote() {
  if (!pesajePorLote || pesajePorLote.indice === 0) return;
  pesajePorLote.indice -= 1;
  await mostrarAnimalPesajeLote();
}

async function finalizarPesajePorLote(automatico) {
  if (!pesajePorLote) return;

  const registrados = pesajePorLote.registrados.size;
  const total = pesajePorLote.animales.length;

  if (!automatico) {
    const mensaje = registrados
      ? '¿Terminar el pesaje? Se registraron ' + registrados + ' de ' + total + ' animales.'
      : 'Todavía no registraste ningún peso. ¿Cerrar el pesaje por lote?';
    if (!window.confirm(mensaje)) return;
  }

  pesajePorLote = null;
  el('pesaje-lote-trabajo').classList.add('oculto');
  el('pesaje-lote-config').classList.remove('oculto');
  el('pesaje-lote-peso').value = '';

  await renderizarListaPesos();
  await actualizarContadorPendientes();

  if (navigator.onLine) Sync.sincronizar();

  mostrarModalAviso(
    'Pesaje por lote terminado',
    '<p style="margin:0;">Se registraron <strong>' + registrados + '</strong> pesajes de ' + total + ' animales del lote.</p>'
  );
}

async function manejarGuardarServicios(evento) {
  evento.preventDefault();
  const sesion = await Db.obtenerSesion();

  const datosFormulario = {
    codigo_rgb_id_animal_hembra: el('servicios-hembra').value,
    codigo_rgb_id_animal_macho: el('servicios-macho').value,
    codigo_rgb_id_animal_dona: el('servicios-dona').value,
    f_servicio: el('servicios-fecha').value,
    f_probable_palpacion: el('servicios-fpp').value,
    mservicios_id: el('servicios-tipo').value,
    nro_servicio: el('servicios-nro').value,
    aptitud: el('servicios-aptitud').value,
    mrazas_id: el('servicios-raza').value,
    observaciones: el('servicios-observaciones').value.trim(),
  };

  const esTransferencia = Number(datosFormulario.mservicios_id) === 3;
  if (edicionServiciosUuid) {
    if (!datosFormulario.codigo_rgb_id_animal_hembra || (!esTransferencia && !datosFormulario.codigo_rgb_id_animal_macho)
      || !datosFormulario.f_servicio || !datosFormulario.mservicios_id || !datosFormulario.aptitud
      || !datosFormulario.mrazas_id || !datosFormulario.nro_servicio) {
      el('form-servicios-mensaje').textContent = esTransferencia
        ? 'Completa receptora, fecha, tipo, nro servicio, aptitud y raza.'
        : 'Completa hembra, macho, fecha, tipo, nro servicio, aptitud y raza.';
      return;
    }
    if (esTransferencia && !datosFormulario.codigo_rgb_id_animal_dona) {
      el('form-servicios-mensaje').textContent = 'La transferencia de embriones requiere una donadora.';
      return;
    }

    const partoConfirmado = el('servicios-parto-confirmado').checked;
    const numeroOVacio = (valor) => (valor === '' ? null : Number(valor));

    datosFormulario.f_palpacion = el('servicios-fecha-palpacion').value || '';
    datosFormulario.medofisiologicos_id = numeroOVacio(el('servicios-estado-fisiologico').value);
    datosFormulario.parto = partoConfirmado ? 1 : 0;
    datosFormulario.fecha_parto = partoConfirmado ? (el('servicios-fecha-parto').value || '') : '';
    datosFormulario.nro_terneros = partoConfirmado ? numeroOVacio(el('servicios-nro-terneros').value) : null;
    datosFormulario.dnp = partoConfirmado ? numeroOVacio(el('servicios-dnp').value) : null;
    datosFormulario.f_probable_parto = el('servicios-fecha-probable-parto').value || '';
    datosFormulario.f_ultimo_parto = el('servicios-fecha-ultimo-parto').value || '';
    datosFormulario.f_secado = el('servicios-fecha-secado').value || '';

    if (partoConfirmado && !datosFormulario.fecha_parto) {
      el('form-servicios-mensaje').textContent = 'Si marcas "Parto confirmado", indica la fecha del parto.';
      return;
    }

    await Servicios.editar(sesion, edicionServiciosUuid, datosFormulario);
    mostrarToast('Servicio actualizado');
  } else {
    const errorValidacion = await validarNuevoServicioOffline(datosFormulario);
    if (errorValidacion) {
      el('form-servicios-mensaje').textContent = errorValidacion;
      return;
    }
    await Servicios.crear(sesion, datosFormulario);
    mostrarToast('Servicio guardado');
  }

  alternarFormularioServicios(false);
  await renderizarListaServicios();
  await actualizarContadorPendientes();
}

function construirIndiceAnimales(animales) {
  const indice = {};
  for (const animal of animales) {
    if (animal.codigo_rgb_id_animal) indice[animal.codigo_rgb_id_animal] = animal;
  }
  return indice;
}

function textoBusquedaAnimalPorCodigo(codigo, indiceAnimales) {
  if (!codigo) return '';
  const animal = indiceAnimales[codigo];
  if (!animal) return codigo.toLowerCase();
  return [animal.codigo_rgb_id_animal, animal.nombre, animal.nro_arete]
    .filter(Boolean).join(' ').toLowerCase();
}

/**
 * Filtro por tipo de registro del módulo Control Sanitario: los controles
 * individuales y los controles por lote se guardan en dos almacenes
 * distintos, así que el filtro decide qué bloque se muestra.
 * Valores: 'todos' | 'individual' | 'lote'.
 */
let filtroTipoControl = 'todos';

/** Aplica el tipo elegido: marca la opción y oculta el bloque que no va. */
function aplicarFiltroTipoControl(valor) {
  filtroTipoControl = ['todos', 'individual', 'lote'].includes(valor) ? valor : 'todos';

  const grupo = el('filtro-tipo-control');
  if (grupo) {
    grupo.querySelectorAll('[data-filtro-control]').forEach((boton) => {
      const activo = boton.dataset.filtroControl === filtroTipoControl;
      boton.classList.toggle('filtro-tipo__opcion--activa', activo);
      boton.setAttribute('aria-pressed', activo ? 'true' : 'false');
    });
  }

  const bloqueIndividual = el('bloque-control-individual');
  const bloqueLote = el('bloque-control-lote');
  if (bloqueIndividual) bloqueIndividual.classList.toggle('oculto', filtroTipoControl === 'lote');
  if (bloqueLote) bloqueLote.classList.toggle('oculto', filtroTipoControl === 'individual');
}

/** Cantidad de registros de cada tipo, para los contadores del filtro. */
function actualizarContadoresFiltroControl() {
  const contar = (idLista) => {
    const lista = el(idLista);
    return lista ? lista.querySelectorAll('.chapeta').length : 0;
  };

  const individual = contar('lista-control-sanitario');
  const lote = contar('lista-control-lote');

  const pintar = (valor, total) => {
    const nodo = document.querySelector('[data-cuenta-control="' + valor + '"]');
    if (nodo) nodo.textContent = String(total);
  };
  pintar('todos', individual + lote);
  pintar('individual', individual);
  pintar('lote', lote);
}

function filtrarListaPorAnimal(idBuscador, idContenedor, idContador) {
  const buscador = el(idBuscador);
  const contenedor = el(idContenedor);
  const contador = el(idContador);
  if (!buscador || !contenedor) return;

  const termino = buscador.value.trim().toLowerCase();
  const tarjetas = contenedor.querySelectorAll('.chapeta');
  let visibles = 0;

  tarjetas.forEach((tarjeta) => {
    const coincide = !termino || (tarjeta.dataset.busqueda || '').includes(termino);
    tarjeta.classList.toggle('oculto', !coincide);
    if (coincide) visibles++;
  });

  if (!contador) return;
  if (termino && tarjetas.length) {
    contador.textContent = visibles + ' de ' + tarjetas.length + ' registros';
    contador.classList.remove('oculto');
  } else {
    contador.classList.add('oculto');
  }
}

/* ---------------------------------------------------------------- */
/* Anular registros ya sincronizados                                 */
/* ---------------------------------------------------------------- */

// Módulos donde el sistema web permite eliminar (borrado lógico con
// status = 0). Bajas, movimientos y nacimientos NO se anulan desde el
// campo: tienen su propio flujo.
const MODULOS_ANULABLES = new Set(['produccion_leche', 'pesos', 'control_sanitario', 'servicios']);

/** Un registro anulado se oculta mientras espera confirmación. */
function visibleEnLista(registro) {
  return !(Number(registro.status) === 0 && registro.estado_sync === 'pendiente');
}

function descripcionParaAnular(modulo, registro) {
  const fecha = formatearFecha(
    modulo === 'pesos' ? registro.fecha_pesaje
      : modulo === 'control_sanitario' ? registro.fecha_control
        : modulo === 'servicios' ? registro.f_servicio
          : registro.fecha_produccion
  );
  const animal = modulo === 'produccion_leche'
    ? registro.codigo_rgb_id_animal_hembra
    : (registro.codigo_rgb_id_animal || registro.codigo_rgb_id_animal_hembra || registro.codigo_servicio);

  const que = {
    produccion_leche: 'esta producción de leche',
    pesos: 'este pesaje',
    control_sanitario: 'este control sanitario',
    servicios: 'este servicio',
  }[modulo] || 'este registro';

  return que
    + (animal ? ' de ' + animal : '')
    + (fecha ? ' del ' + fecha : '');
}

/**
 * Anula un registro.
 *
 * - Si nunca llegó al servidor (sin id) se borra del equipo.
 * - Si ya está sincronizado se encola una anulación ("eliminado") para
 *   que el servidor lo marque con status = 0, igual que la web.
 */
async function anularRegistro(modulo, uuidLocal) {
  const registro = await Db.obtener(modulo, uuidLocal);
  if (!registro) throw new Error('No se encontro ese registro en este dispositivo.');

  if (!(Number(registro.id) > 0)) {
    await Db.eliminar(modulo, uuidLocal);
    await Db.eliminar('outbox', uuidLocal);
    await Db.eliminar('conflictos', uuidLocal);
    return 'borrado';
  }

  const pendiente = await Db.obtener('outbox', uuidLocal);
  const versionBase = pendiente ? pendiente.version_base : (registro.version ?? null);

  registro.status = 0;
  registro.estado_sync = 'pendiente';
  delete registro.ultimo_error;
  await Db.guardar(modulo, registro);

  await Db.guardar('outbox', {
    uuid_local: uuidLocal,
    modulo,
    version_base: versionBase,
    datos: {},
    eliminado: true,
    intentos: 0,
    creado: new Date().toISOString(),
  });

  return 'anulado';
}

async function manejarEliminarRegistro(modulo, uuidLocal) {
  if (!MODULOS_ANULABLES.has(modulo)) return;

  const sesion = await Db.obtenerSesion();
  if (sesion && sesion.modo_offline === 'consulta') {
    alert('Estas en modo consulta: no podes eliminar registros.');
    return;
  }

  const registro = await Db.obtener(modulo, uuidLocal);
  if (!registro) return;

  const descripcion = descripcionParaAnular(modulo, registro);
  const yaSincronizado = Number(registro.id) > 0;
  const pregunta = yaSincronizado
    ? '¿Eliminar ' + descripcion + '?\n\n'
      + 'Se anula tambien en el sistema al sincronizar: deja de aparecer en listados y reportes, '
      + 'pero el registro queda guardado en el servidor.'
    : '¿Descartar ' + descripcion + '?\n\n'
      + 'Todavia no se envio al servidor, asi que se borra directamente de este equipo.';

  if (!confirm(pregunta)) return;

  try {
    await anularRegistro(modulo, uuidLocal);
  } catch (e) {
    alert(e && e.message ? e.message : 'No se pudo eliminar el registro.');
    return;
  }

  await renderizarListaControlSanitario();
  await renderizarListaControlLote();
  await renderizarListaProduccionLeche();
  await renderizarListaPesos();
  await renderizarListaServicios();
  await renderizarListaNacimientos();
  await actualizarContadorPendientes();

  if (navigator.onLine) Sync.sincronizar();
}

async function renderizarListaControlSanitario() {
  const registros = (await ControlSanitario.listar()).filter(visibleEnLista);
  const sesion = await Db.obtenerSesion();
  const modoConsulta = sesion && sesion.modo_offline === 'consulta';
  const contenedor = el('lista-control-sanitario');
  if (!contenedor) return;
  if (formularioAbiertoEnLista(contenedor)) return;
  rescatarFormulariosMovidos(contenedor);
  contenedor.innerHTML = '';

  if (!registros.length) {
    contenedor.innerHTML = '<p class="texto-vacio">Todavia no hay controles sanitarios registrados en este dispositivo.</p>';
    if (el('buscador-control-sanitario-contador')) el('buscador-control-sanitario-contador').classList.add('oculto');
    actualizarContadoresFiltroControl();
    return;
  }

  const animalesLocales = await Animales.listar();
  const indiceAnimales = construirIndiceAnimales(animalesLocales);
  const estadoTexto = {
    pendiente: 'Pendiente de sincronizar',
    sincronizado: 'Sincronizado',
    conflicto: 'En conflicto',
    error: 'Error al sincronizar',
  };

  for (const registro of registros) {
    const tarjeta = document.createElement('article');
    tarjeta.className = 'chapeta chapeta--' + (registro.estado_sync || 'pendiente');
    tarjeta.dataset.busqueda = textoBusquedaAnimalPorCodigo(registro.codigo_rgb_id_animal, indiceAnimales);
    tarjeta.innerHTML = `
      <div class="chapeta__codigo">${escaparHtml(registro.codigo_rgb_id_animal || '')}</div>
      <div class="chapeta__nombre">${escaparHtml(registro.evento || '(sin evento)')}</div>
      <div class="chapeta__detalle">${escaparHtml(formatearFecha(registro.fecha_control) || '—')} · ${escaparHtml(registro.responsable || 'Sin responsable')}</div>
      <div class="chapeta__pie">
        <div class="chapeta__estado">${estadoSyncTexto(registro, estadoTexto)}</div>
        <div class="chapeta__acciones">
        ${modoConsulta
          ? `<button class="chapeta__boton-ver" type="button" data-ver-registro="${escaparHtml(registro.uuid_local)}" data-modulo="control_sanitario" aria-label="Ver detalle del control sanitario de ${escaparHtml(registro.codigo_rgb_id_animal || 'animal')}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>Ver detalle</button>`
          : `<button class="chapeta__boton-editar" type="button" data-editar-cs="${escaparHtml(registro.uuid_local)}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>Editar</button>
          <button class="chapeta__boton-eliminar" type="button" data-eliminar-registro="${escaparHtml(registro.uuid_local)}" data-modulo="control_sanitario" aria-label="Eliminar el control sanitario de ${escaparHtml(registro.codigo_rgb_id_animal || 'animal')}" title="Eliminar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v5"/><path d="M14 11v5"/></svg><span class="chapeta__boton-eliminar-texto">Eliminar</span></button>`}
        </div>
      </div>
    `;
    contenedor.appendChild(tarjeta);
  }

  contenedor.querySelectorAll('button[data-ver-registro][data-modulo="control_sanitario"]').forEach((boton) => {
    boton.addEventListener('click', () => abrirDetalleRegistro(boton.dataset.modulo, boton.dataset.verRegistro, boton));
  });

  contenedor.querySelectorAll('button[data-editar-cs]').forEach((boton) => {
    boton.addEventListener('click', () => abrirEdicionControlSanitario(boton.dataset.editarCs, boton.closest('.chapeta')));
  });

  contenedor.querySelectorAll('button[data-eliminar-registro]').forEach((boton) => {
    boton.addEventListener('click', () => manejarEliminarRegistro(boton.dataset.modulo, boton.dataset.eliminarRegistro));
  });

  filtrarListaPorAnimal('buscador-control-sanitario', 'lista-control-sanitario', 'buscador-control-sanitario-contador');
  actualizarContadoresFiltroControl();
}

async function renderizarListaProduccionLeche() {
  const registros = (await ProduccionLeche.listar()).filter(visibleEnLista);
  const sesion = await Db.obtenerSesion();
  const modoConsulta = sesion && sesion.modo_offline === 'consulta';
  const contenedor = el('lista-produccion-leche');
  if (!contenedor) return;
  if (formularioAbiertoEnLista(contenedor)) return;
  rescatarFormulariosMovidos(contenedor);
  contenedor.innerHTML = '';

  if (!registros.length) {
    contenedor.innerHTML = '<p class="texto-vacio">Todavia no hay registros de produccion en este dispositivo.</p>';
    if (el('buscador-produccion-leche-contador')) el('buscador-produccion-leche-contador').classList.add('oculto');
    return;
  }

  const animalesLocales = await Animales.listar();
  const indiceAnimales = construirIndiceAnimales(animalesLocales);
  const estadoTexto = {
    pendiente: 'Pendiente de sincronizar',
    sincronizado: 'Sincronizado',
    conflicto: 'En conflicto',
    error: 'Error al sincronizar',
  };

  for (const registro of registros) {
    const tarjeta = document.createElement('article');
    tarjeta.className = 'chapeta chapeta--' + (registro.estado_sync || 'pendiente');
    tarjeta.dataset.busqueda = textoBusquedaAnimalPorCodigo(registro.codigo_rgb_id_animal_hembra, indiceAnimales);
    tarjeta.innerHTML = `
      <div class="chapeta__codigo">${escaparHtml(registro.codigo_rgb_id_animal_hembra || '')}</div>
      <div class="chapeta__nombre">${escaparHtml(formatearFecha(registro.fecha_produccion) || 'Sin fecha')}</div>
      <div class="chapeta__detalle">M: ${escaparHtml(String(registro.cantidad_leche_m || 0))} · T: ${escaparHtml(String(registro.cantidad_leche_t || 0))}</div>
      <div class="chapeta__pie">
        <div class="chapeta__estado">${estadoSyncTexto(registro, estadoTexto)}</div>
        <div class="chapeta__acciones">
        ${modoConsulta
          ? `<button class="chapeta__boton-ver" type="button" data-ver-registro="${escaparHtml(registro.uuid_local)}" data-modulo="produccion_leche" aria-label="Ver detalle de producción de leche para ${escaparHtml(registro.codigo_rgb_id_animal_hembra || 'animal')}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>Ver detalle</button>`
          : `<button class="chapeta__boton-editar" type="button" data-editar-pl="${escaparHtml(registro.uuid_local)}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>Editar</button>
          <button class="chapeta__boton-eliminar" type="button" data-eliminar-registro="${escaparHtml(registro.uuid_local)}" data-modulo="produccion_leche" aria-label="Eliminar la producción de leche de ${escaparHtml(registro.codigo_rgb_id_animal_hembra || 'animal')}" title="Eliminar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v5"/><path d="M14 11v5"/></svg><span class="chapeta__boton-eliminar-texto">Eliminar</span></button>`}
        </div>
      </div>
    `;
    contenedor.appendChild(tarjeta);
  }

  contenedor.querySelectorAll('button[data-ver-registro][data-modulo="produccion_leche"]').forEach((boton) => {
    boton.addEventListener('click', () => abrirDetalleRegistro(boton.dataset.modulo, boton.dataset.verRegistro, boton));
  });

  contenedor.querySelectorAll('button[data-editar-pl]').forEach((boton) => {
    boton.addEventListener('click', () => abrirEdicionProduccionLeche(boton.dataset.editarPl, boton.closest('.chapeta')));
  });

  contenedor.querySelectorAll('button[data-eliminar-registro]').forEach((boton) => {
    boton.addEventListener('click', () => manejarEliminarRegistro(boton.dataset.modulo, boton.dataset.eliminarRegistro));
  });

  filtrarListaPorAnimal('buscador-produccion-leche', 'lista-produccion-leche', 'buscador-produccion-leche-contador');
}

async function renderizarListaPesos() {
  const registros = (await Pesos.listar()).filter(visibleEnLista);
  const sesion = await Db.obtenerSesion();
  const modoConsulta = sesion && sesion.modo_offline === 'consulta';
  const contenedor = el('lista-pesos');
  if (!contenedor) return;
  if (formularioAbiertoEnLista(contenedor)) return;
  rescatarFormulariosMovidos(contenedor);
  contenedor.innerHTML = '';

  if (!registros.length) {
    contenedor.innerHTML = '<p class="texto-vacio">Todavia no hay pesajes registrados en este dispositivo.</p>';
    if (el('buscador-pesos-contador')) el('buscador-pesos-contador').classList.add('oculto');
    return;
  }

  const animalesLocales = await Animales.listar();
  const indiceAnimales = construirIndiceAnimales(animalesLocales);
  const estadoTexto = {
    pendiente: 'Pendiente de sincronizar',
    sincronizado: 'Sincronizado',
    conflicto: 'En conflicto',
    error: 'Error al sincronizar',
  };

  for (const registro of registros) {
    const tarjeta = document.createElement('article');
    tarjeta.className = 'chapeta chapeta--' + (registro.estado_sync || 'pendiente');
    tarjeta.dataset.busqueda = textoBusquedaAnimalPorCodigo(registro.codigo_rgb_id_animal, indiceAnimales);
    tarjeta.innerHTML = `
      <div class="chapeta__codigo">${escaparHtml(registro.codigo_rgb_id_animal || '')}</div>
      <div class="chapeta__nombre">${escaparHtml(registro.peso ? String(registro.peso) + ' kg' : 'Sin peso')}</div>
      <div class="chapeta__detalle">${escaparHtml(formatearFecha(registro.fecha_pesaje) || '—')}</div>
      <div class="chapeta__pie">
        <div class="chapeta__estado">${estadoSyncTexto(registro, estadoTexto)}</div>
        <div class="chapeta__acciones">
        ${modoConsulta
          ? `<button class="chapeta__boton-ver" type="button" data-ver-registro="${escaparHtml(registro.uuid_local)}" data-modulo="pesos" aria-label="Ver detalle del pesaje de ${escaparHtml(registro.codigo_rgb_id_animal || 'animal')}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>Ver detalle</button>`
          : `<button class="chapeta__boton-editar" type="button" data-editar-pesos="${escaparHtml(registro.uuid_local)}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>Editar</button>
          <button class="chapeta__boton-eliminar" type="button" data-eliminar-registro="${escaparHtml(registro.uuid_local)}" data-modulo="pesos" aria-label="Eliminar el pesaje de ${escaparHtml(registro.codigo_rgb_id_animal || 'animal')}" title="Eliminar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v5"/><path d="M14 11v5"/></svg><span class="chapeta__boton-eliminar-texto">Eliminar</span></button>`}
        </div>
      </div>
    `;
    contenedor.appendChild(tarjeta);
  }

  contenedor.querySelectorAll('button[data-ver-registro][data-modulo="pesos"]').forEach((boton) => {
    boton.addEventListener('click', () => abrirDetalleRegistro(boton.dataset.modulo, boton.dataset.verRegistro, boton));
  });

  contenedor.querySelectorAll('button[data-editar-pesos]').forEach((boton) => {
    boton.addEventListener('click', () => abrirEdicionPesos(boton.dataset.editarPesos, boton.closest('.chapeta')));
  });

  contenedor.querySelectorAll('button[data-eliminar-registro]').forEach((boton) => {
    boton.addEventListener('click', () => manejarEliminarRegistro(boton.dataset.modulo, boton.dataset.eliminarRegistro));
  });

  filtrarListaPorAnimal('buscador-pesos', 'lista-pesos', 'buscador-pesos-contador');
}

async function renderizarListaServicios() {
  const registros = (await Servicios.listar()).filter(visibleEnLista);
  const sesion = await Db.obtenerSesion();
  const modoConsulta = sesion && sesion.modo_offline === 'consulta';
  const contenedor = el('lista-servicios');
  if (!contenedor) return;
  if (formularioAbiertoEnLista(contenedor)) return;
  rescatarFormulariosMovidos(contenedor);
  contenedor.innerHTML = '';

  if (!registros.length) {
    contenedor.innerHTML = '<p class="texto-vacio">Todavia no hay servicios registrados en este dispositivo.</p>';
    if (el('buscador-servicios-contador')) el('buscador-servicios-contador').classList.add('oculto');
    return;
  }

  const animalesLocales = await Animales.listar();
  const indiceAnimales = construirIndiceAnimales(animalesLocales);
  const estadoTexto = {
    pendiente: 'Pendiente de sincronizar',
    sincronizado: 'Sincronizado',
    conflicto: 'En conflicto',
    error: 'Error al sincronizar',
  };

  const tipoTexto = {
    1: 'Monta',
    2: 'Inseminacion artificial',
    3: 'Transferencia de embriones',
  };

  for (const registro of registros) {
    const tarjeta = document.createElement('article');
    tarjeta.className = 'chapeta chapeta--' + (registro.estado_sync || 'pendiente');
    tarjeta.dataset.busqueda = [
      textoBusquedaAnimalPorCodigo(registro.codigo_rgb_id_animal_hembra, indiceAnimales),
      textoBusquedaAnimalPorCodigo(registro.codigo_rgb_id_animal_macho, indiceAnimales),
      textoBusquedaAnimalPorCodigo(registro.codigo_rgb_id_animal_dona, indiceAnimales),
    ].filter(Boolean).join(' ');
    tarjeta.innerHTML = `
      <div class="chapeta__codigo">${escaparHtml(registro.codigo_servicio || 'RSA-PEND')}</div>
      <div class="chapeta__nombre">${escaparHtml(registro.codigo_rgb_id_animal_hembra || '')} · ${escaparHtml(registro.codigo_rgb_id_animal_macho || '')}</div>
      <div class="chapeta__detalle">${escaparHtml(formatearFecha(registro.f_servicio) || '—')} · ${escaparHtml(tipoTexto[registro.mservicios_id] || 'Sin tipo')}</div>
      <div class="chapeta__pie">
        <div class="chapeta__estado">${estadoSyncTexto(registro, estadoTexto)}</div>
        <div class="chapeta__acciones">
        ${modoConsulta
          ? `<button class="chapeta__boton-ver" type="button" data-ver-registro="${escaparHtml(registro.uuid_local)}" data-modulo="servicios" aria-label="Ver detalle del servicio ${escaparHtml(registro.codigo_servicio || '')}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>Ver detalle</button>`
          : `<button class="chapeta__boton-editar" type="button" data-editar-servicios="${escaparHtml(registro.uuid_local)}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>Editar</button>
          <button class="chapeta__boton-eliminar" type="button" data-eliminar-registro="${escaparHtml(registro.uuid_local)}" data-modulo="servicios" aria-label="Eliminar el servicio ${escaparHtml(registro.codigo_servicio || '')}" title="Eliminar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v5"/><path d="M14 11v5"/></svg><span class="chapeta__boton-eliminar-texto">Eliminar</span></button>`}
        </div>
      </div>
    `;
    contenedor.appendChild(tarjeta);
  }

  contenedor.querySelectorAll('button[data-ver-registro][data-modulo="servicios"]').forEach((boton) => {
    boton.addEventListener('click', () => abrirDetalleRegistro(boton.dataset.modulo, boton.dataset.verRegistro, boton));
  });

  contenedor.querySelectorAll('button[data-editar-servicios]').forEach((boton) => {
    boton.addEventListener('click', () => abrirEdicionServicios(boton.dataset.editarServicios, boton.closest('.chapeta')));
  });

  contenedor.querySelectorAll('button[data-eliminar-registro]').forEach((boton) => {
    boton.addEventListener('click', () => manejarEliminarRegistro(boton.dataset.modulo, boton.dataset.eliminarRegistro));
  });

  filtrarListaPorAnimal('buscador-servicios', 'lista-servicios', 'buscador-servicios-contador');
}

async function renderizarListaNacimientos() {
  const registros = await Nacimientos.listar();
  const sesion = await Db.obtenerSesion();
  const modoConsulta = sesion && sesion.modo_offline === 'consulta';
  const contenedor = el('lista-nacimientos');
  if (!contenedor) return;
  if (formularioAbiertoEnLista(contenedor)) return;
  rescatarFormulariosMovidos(contenedor);
  contenedor.innerHTML = '';

  if (!registros.length) {
    contenedor.innerHTML = '<p class="texto-vacio">Todavia no hay nacimientos registrados en este dispositivo.</p>';
    if (el('buscador-nacimientos-contador')) el('buscador-nacimientos-contador').classList.add('oculto');
    return;
  }

  const animalesLocales = await Animales.listar();
  const indiceAnimales = construirIndiceAnimales(animalesLocales);
  const estadoTexto = {
    pendiente: 'Pendiente de sincronizar',
    sincronizado: 'Sincronizado',
    conflicto: 'En conflicto',
    error: 'Error al sincronizar',
  };
  const sexoTexto = { 1: 'Hembra', 2: 'Macho' };

  for (const registro of registros) {
    const fichaLocal = animalesLocales.find((animal) => Number(animal.status ?? 1) === 1
      && ((animal.codigo_ternero && animal.codigo_ternero === registro.codigo_ternero)
        || animal.nacimiento_uuid_local === registro.uuid_local));
    const fichado = esBanderaActiva(registro.fichado) || !!fichaLocal;
    if (fichaLocal && !esBanderaActiva(registro.fichado)) {
      registro.fichado = true;
      registro.ficha_animal_uuid_local = fichaLocal.uuid_local;
      registro.ficha_animal_codigo = fichaLocal.codigo_rgb_id_animal;
      await Db.guardar('nacimientos', registro);
    }

    const ternerosTarjeta = Array.isArray(registro.terneros) && registro.terneros.length
      ? registro.terneros
      : [{ sexo: registro.sexo, estatus_nacido: registro.estatus_nacido }];
    const resumenTerneros = ternerosTarjeta.length === 1
      ? (sexoTexto[ternerosTarjeta[0].sexo] || '—')
        + (Number(ternerosTarjeta[0].estatus_nacido) === 2 ? ' · muerto' : '')
      : ternerosTarjeta.length + ' terneros';

    const tarjeta = document.createElement('article');
    tarjeta.className = 'chapeta chapeta--' + (registro.estado_sync || 'pendiente');
    tarjeta.dataset.busqueda = [
      textoBusquedaAnimalPorCodigo(registro.codigo_rgb_id_animal_hembra, indiceAnimales),
      textoBusquedaAnimalPorCodigo(registro.codigo_rgb_id_animal_macho, indiceAnimales),
      textoBusquedaAnimalPorCodigo(registro.codigo_rgb_id_animal_dona, indiceAnimales),
    ].filter(Boolean).join(' ');
    tarjeta.innerHTML = `
      <div class="chapeta__codigo">${escaparHtml(registro.codigo_ternero || 'MA-PEND')}${registro.codigo_provisional ? ' <span class="chapeta__provisional">provisional</span>' : ''}</div>
      <div class="chapeta__nombre">Madre: ${escaparHtml(registro.codigo_rgb_id_animal_hembra || '')}${registro.codigo_rgb_id_animal_macho ? ' · Padre: ' + escaparHtml(registro.codigo_rgb_id_animal_macho) : ''}</div>
      <div class="chapeta__detalle">${escaparHtml(formatearFecha(registro.f_parto) || '—')} · ${escaparHtml(resumenTerneros)}</div>
      <div class="chapeta__pie">
        <div class="chapeta__estado">${fichado ? 'Fichado · Solo consulta' : estadoSyncTexto(registro, estadoTexto)}</div>
        ${modoConsulta || fichado
          ? `<button class="chapeta__boton-ver" type="button" data-ver-registro="${escaparHtml(registro.uuid_local)}" data-modulo="nacimientos" aria-label="Ver detalle del nacimiento ${escaparHtml(registro.codigo_ternero || '')}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>Ver detalle</button>`
          : `<button class="chapeta__boton-editar" type="button" data-editar-nacimientos="${escaparHtml(registro.uuid_local)}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>Editar</button>`}
      </div>
    `;
    contenedor.appendChild(tarjeta);
  }

  contenedor.querySelectorAll('button[data-ver-registro][data-modulo="nacimientos"]').forEach((boton) => {
    boton.addEventListener('click', () => abrirDetalleRegistro(boton.dataset.modulo, boton.dataset.verRegistro, boton));
  });

  contenedor.querySelectorAll('button[data-editar-nacimientos]').forEach((boton) => {
    boton.addEventListener('click', () => abrirEdicionNacimiento(boton.dataset.editarNacimientos, boton.closest('.chapeta')));
  });

  filtrarListaPorAnimal('buscador-nacimientos', 'lista-nacimientos', 'buscador-nacimientos-contador');
}

let snapshotsFormulario = {};

function capturarSnapshotFormulario(idForm) {
  const form = el(idForm);
  if (!form) return '';
  const datos = {};
  form.querySelectorAll('input, select, textarea').forEach((campo) => {
    if (!campo.id) return;
    datos[campo.id] = (campo.type === 'checkbox' || campo.type === 'radio') ? campo.checked : campo.value;
  });
  if (idForm === 'form-cs') datos.productos_aplicados = productosControlSanitario;
  if (idForm === 'form-cs-lote') datos.productos_aplicados = productosControlLote;
  if (idForm === 'form-nacimientos') {
    datos.servicioOrigenNacimiento = servicioOrigenNacimiento;
    datos.terneros = leerTernerosFormulario();
  }
  return JSON.stringify(datos);
}

function formularioTieneCambiosSinGuardar(idForm, idContenedor) {
  const contenedor = el(idContenedor);
  if (!contenedor || contenedor.classList.contains('oculto')) return false;
  if (!(idForm in snapshotsFormulario)) return false;
  return capturarSnapshotFormulario(idForm) !== snapshotsFormulario[idForm];
}

const MENSAJE_CAMBIOS_SIN_GUARDAR = 'Tienes cambios sin guardar en este formulario. ¿Deseas salir sin guardarlos?';

const FORMULARIOS_MODULOS = [
  { idForm: 'form-animal', idContenedor: 'form-animal-contenedor', alternar: () => alternarFormulario(false) },
  { idForm: 'form-cs', idContenedor: 'form-cs-contenedor', alternar: () => alternarFormularioControlSanitario(false) },
  { idForm: 'form-cs-lote', idContenedor: 'form-cs-lote-contenedor', alternar: () => alternarFormularioControlLote(false) },
  { idForm: 'form-desincorporacion', idContenedor: 'form-desincorporacion-contenedor', alternar: () => alternarFormularioDesincorporacion(false) },
  { idForm: 'form-movreb', idContenedor: 'form-movreb-contenedor', alternar: () => alternarFormularioMovimientoRebano(false) },
  { idForm: 'form-pl', idContenedor: 'form-pl-contenedor', alternar: () => alternarFormularioProduccionLeche(false) },
  { idForm: 'form-pesos', idContenedor: 'form-pesos-contenedor', alternar: () => alternarFormularioPesos(false) },
  { idForm: 'form-servicios', idContenedor: 'form-servicios-contenedor', alternar: () => alternarFormularioServicios(false) },
  { idForm: 'form-nacimientos', idContenedor: 'form-nacimientos-contenedor', alternar: () => alternarFormularioNacimientos(false) },
];

function confirmarSalirDeFormulariosAbiertos() {
  const hayCambiosSinGuardar = FORMULARIOS_MODULOS.some(
    (f) => formularioTieneCambiosSinGuardar(f.idForm, f.idContenedor)
  );
  if (!hayCambiosSinGuardar) return true;
  return window.confirm(MENSAJE_CAMBIOS_SIN_GUARDAR);
}

function puedeAbrirOtroFormulario(idForm, idContenedor) {
  if (!formularioTieneCambiosSinGuardar(idForm, idContenedor)) return true;
  return window.confirm(MENSAJE_CAMBIOS_SIN_GUARDAR);
}

function pedirConfirmacionYCerrar(idForm, idContenedor, alternarFn) {
  if (formularioTieneCambiosSinGuardar(idForm, idContenedor) && !window.confirm(MENSAJE_CAMBIOS_SIN_GUARDAR)) {
    return;
  }
  alternarFn(false);
}

function mostrarTab(nombreTab) {
  const tabActivaAntes = document.querySelector('.tab--activa');
  const tabAnterior = tabActivaAntes ? tabActivaAntes.dataset.tab : null;

  if (tabAnterior && tabAnterior !== nombreTab) {
    if (!confirmarSalirDeFormulariosAbiertos()) return false;
    FORMULARIOS_MODULOS.forEach((f) => f.alternar());
  }

  document.querySelectorAll('.tab').forEach((tab) => {
    const activa = tab.dataset.tab === nombreTab;
    tab.classList.toggle('tab--activa', activa);
    tab.setAttribute('aria-selected', activa ? 'true' : 'false');
    tab.tabIndex = activa ? 0 : -1;
  });
  document.querySelectorAll('.panel').forEach((panel) => {
    panel.classList.toggle('oculto', panel.id !== 'panel-' + nombreTab);
  });

  // En pantallas angostas la tira de pestañas se desplaza: la activa se
  // centra para que quede a la vista.
  desplazarPestanaActiva();

  // Al cambiar de módulo se vuelve al inicio; antes quedabas a mitad de
  // la lista del módulo nuevo.
  try { window.scrollTo({ top: 0, behavior: 'auto' }); } catch (e) { window.scrollTo(0, 0); }

  return true;
}

/** Centra la pestaña activa dentro de la tira horizontal. */
function desplazarPestanaActiva() {
  const tab = document.querySelector('.tab--activa');
  if (!tab || !tab.parentElement) return;

  const tira = tab.parentElement;
  // Si entran todas (pantalla ancha) no hay nada que desplazar.
  if (tira.scrollWidth <= tira.clientWidth + 4) return;

  const tiraRect = tira.getBoundingClientRect();
  const tabRect = tab.getBoundingClientRect();
  const destino = tira.scrollLeft + (tabRect.left - tiraRect.left)
    - (tira.clientWidth - tabRect.width) / 2;

  const sinAnimacion = window.matchMedia
    && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  try {
    tira.scrollTo({ left: Math.max(0, destino), behavior: sinAnimacion ? 'auto' : 'smooth' });
  } catch (e) {
    tira.scrollLeft = Math.max(0, destino);
  }
}

/**
 * Orden de los módulos en la tira de pestañas.
 *
 * El orden visual y el del teclado (flechas) salen de acá: las pestañas
 * se reubican en el DOM al iniciar, así no hay que tocar el HTML ni
 * duplicar la lista.
 */
const ORDEN_MODULOS = [
  'animales',
  'nacimientos',
  'servicios',
  'control_sanitario',
  'produccion_leche',
  'pesos',
  'movimiento_rebano',
  'desincorporacion',
  'reportes',
];

/** Reubica las pestañas en el orden de ORDEN_MODULOS. */
function ordenarPestanas() {
  const nav = document.querySelector('.tabs');
  if (!nav) return;

  // appendChild mueve el nodo existente: al recorrer la lista en orden,
  // las pestañas quedan al final del nav en ese mismo orden.
  ORDEN_MODULOS.forEach((modulo) => {
    const boton = nav.querySelector('.tab[data-tab="' + modulo + '"]');
    if (boton) nav.appendChild(boton);
  });

  // Si algún día se agrega un módulo que no está en la lista, se respeta
  // igual: queda al final en vez de desaparecer.
  Array.from(nav.querySelectorAll('.tab')).forEach((boton) => {
    if (!ORDEN_MODULOS.includes(boton.dataset.tab)) nav.appendChild(boton);
  });
}

/**
 * Encabezado dentro de cada panel: el nombre del módulo y su icono, para
 * saber dónde se está parado sin volver a la tira de pestañas. Reutiliza
 * el icono de la pestaña, así no hay dos juegos de íconos.
 */
function prepararCabecerasModulos() {
  document.querySelectorAll('.panel[id^="panel-"]').forEach((panel) => {
    if (panel.querySelector('.panel__cabecera')) return;

    const modulo = panel.id.replace('panel-', '');
    const tab = document.querySelector('.tab[data-tab="' + modulo + '"]');
    if (!tab) return;

    const icono = tab.querySelector('.tab__icono');
    const texto = tab.querySelector('.tab__texto');

    const cabecera = document.createElement('header');
    cabecera.className = 'panel__cabecera';

    const cajaIcono = document.createElement('span');
    cajaIcono.className = 'panel__cabecera-icono';
    cajaIcono.setAttribute('aria-hidden', 'true');
    if (icono) cajaIcono.innerHTML = icono.innerHTML;

    const titulo = document.createElement('h2');
    titulo.className = 'panel__cabecera-titulo';
    const nombreModulo = texto ? texto.textContent.trim() : modulo;
    titulo.textContent = nombreModulo;
    // Un nombre largo ("Desincorporaciones") necesita un cuerpo más
    // chico para entrar completo en el ancho del panel.
    if (nombreModulo.length > 14) {
      titulo.classList.add('panel__cabecera-titulo--largo');
    }

    cabecera.appendChild(cajaIcono);
    cabecera.appendChild(titulo);
    panel.insertBefore(cabecera, panel.firstChild);
  });
}

/**
 * Deja la tira de pestañas como un tablist accesible: roles, relación
 * con su panel y navegación con las flechas del teclado físico.
 */
function configurarAccesibilidadPestanas() {
  const nav = document.querySelector('.tabs');
  if (!nav) return;

  nav.setAttribute('role', 'tablist');
  nav.setAttribute('aria-label', 'Módulos');

  const tabs = Array.from(nav.querySelectorAll('.tab')).filter((tab) => tab.dataset.tab);

  tabs.forEach((tab, indice) => {
    const nombre = tab.dataset.tab;
    tab.setAttribute('role', 'tab');
    tab.setAttribute('id', 'tab-' + nombre);
    tab.setAttribute('aria-controls', 'panel-' + nombre);
    tab.setAttribute('aria-selected', tab.classList.contains('tab--activa') ? 'true' : 'false');
    tab.tabIndex = tab.classList.contains('tab--activa') ? 0 : -1;

    tab.addEventListener('keydown', (evento) => {
      let salto = 0;
      if (evento.key === 'ArrowRight') salto = 1;
      else if (evento.key === 'ArrowLeft') salto = -1;
      else if (evento.key === 'Home') {
        evento.preventDefault();
        tabs[0].focus();
        mostrarTab(tabs[0].dataset.tab);
        return;
      } else if (evento.key === 'End') {
        evento.preventDefault();
        tabs[tabs.length - 1].focus();
        mostrarTab(tabs[tabs.length - 1].dataset.tab);
        return;
      }
      if (!salto) return;

      evento.preventDefault();
      const siguiente = tabs[(indice + salto + tabs.length) % tabs.length];
      siguiente.focus();
      mostrarTab(siguiente.dataset.tab);
    });
  });

  document.querySelectorAll('.panel').forEach((panel) => {
    if (!panel.id || panel.id.indexOf('panel-') !== 0) return;
    panel.setAttribute('role', 'tabpanel');
    panel.setAttribute('aria-labelledby', 'tab-' + panel.id.slice('panel-'.length));
    panel.tabIndex = 0;
  });
}

async function mostrarPanelContinuarOffline() {
  // placeholder
}

function actualizarIndicadorConexion() {
  const indicador = el('indicador-conexion');
  if (!indicador) return;
  if (navigator.onLine) {
    indicador.textContent = '● En línea';
    indicador.className = 'indicador indicador--online';
  } else {
    indicador.textContent = '○ Sin conexión';
    indicador.className = 'indicador indicador--offline';
  }
}

async function manejarEventoSync(detalle) {
  const estadoEl = el('estado-sync');
  if (!estadoEl) return;

  // La revisión periódica de fondo (cada 2 min) no debe notarse: si no
  // hay nada por subir se queda callada en vez de escribir "Sincronizando…"
  // cada vez. Cuando el usuario pulsa "Sincronizar" sí se avisa siempre.
  const enSegundoPlano = detalle.automatico === true;
  const hayPendientes = Number(ultimoConteoPendientes) > 0;

  // Escribir solo si el texto cambia: reasignar el mismo contenido en
  // cada pasada rehace el nodo de texto sin que haya nada nuevo.
  const escribirEstado = (texto) => {
    if (estadoEl.textContent !== texto) estadoEl.textContent = texto;
  };

  if (detalle.en_curso) {
    if (enSegundoPlano) {
      // Arranque de una corrida de fondo: si hay algo por subir se anuncia;
      // si no, la línea de estado se queda como estaba (borrarla y
      // reescribirla enseguida era el parpadeo que se veía cada 2 min).
      if (hayPendientes) escribirEstado('Sincronizando…');
      return;
    }
    escribirEstado('Sincronizando…');
    return;
  }

  if (!detalle.ok) {
    escribirEstado(detalle.motivo === 'sin_conexion' ? ''
      : detalle.motivo === 'sesion_local' ? 'Trabajando sin conexión. Inicia sesión arriba para sincronizar.'
      : 'No se pudo sincronizar: ' + detalle.motivo);
  } else {
    const partes = [];
    if (detalle.enviados) partes.push(detalle.enviados + ' enviado(s)');
    if (detalle.traidos) partes.push(detalle.traidos + ' recibido(s)');
    if (detalle.conflictos) partes.push(detalle.conflictos + ' en conflicto');

    const modulosConError = Array.isArray(detalle.modulos_con_error) ? detalle.modulos_con_error : [];
    if (modulosConError.length) {
      const resumenModulos = modulosConError
        .map((m) => m.modulo + ' (' + m.fase + ')')
        .join(', ');
      partes.push('falló: ' + resumenModulos);
    }
    if (!partes.length && detalle.errores) {
      partes.push(detalle.errores + ' con error');
    }

    const novedades = Number(detalle.enviados || 0) + Number(detalle.traidos || 0)
      + Number(detalle.conflictos || 0) + Number(detalle.errores || 0) + modulosConError.length;
    const datosNuevos = Number(detalle.enviados || 0) + Number(detalle.traidos || 0)
      + Number(detalle.conflictos || 0) > 0;

    if (!novedades) {
      // No se envió ni se recibió nada: no hay razón para volver a
      // pintar las listas ni para avisar. Solo se refresca el chip de
      // estado ("Todo sincronizado" / "hace X min"), que es lo único
      // que cambia tras una revisión en segundo plano.
      escribirEstado('');
      await actualizarIndicadorSincronizacion();
      return;
    }

    // Corrida de fondo que solo vuelve a traer los mismos errores: se
    // informa, pero no se repinta todo (sería un parpadeo cada 2 min).
    if (enSegundoPlano && !datosNuevos) {
      escribirEstado('Sincronizado: ' + partes.join(', ') + '.');
      await actualizarIndicadorSincronizacion();
      return;
    }

    // Sí hubo movimiento: se informa y se repintan las listas con los
    // datos nuevos.
    escribirEstado('Sincronizado: ' + partes.join(', ') + '.');

    const sesionActual = await Db.obtenerSesion();
    if (sesionActual && sesionActual.usuario_login) {
      await CredencialLocal.renovarValidacionOnline(sesionActual.usuario_login);
      await actualizarAvisoSesionLocal(sesionActual);
    }

    await renderizarLista();
    await renderizarListaControlSanitario();
    await renderizarListaControlLote();
    await renderizarListaProduccionLeche();
    await renderizarListaPesos();
    await renderizarListaServicios();
    await renderizarListaNacimientos();
    await poblarSelectAnimalesControlSanitario();
    await poblarSelectAnimalesProduccionLeche();
    await poblarSelectAnimalesPesos();
    await poblarSelectAnimalesServicios();
    await poblarSelectAnimalesNacimientos();
    await renderizarListaReportes();
    await renderizarConflictos();
    await actualizarContadorPendientes();
  }
}

/**
 * Corrige los textos que están en MAYÚSCULAS pero con alguna vocal
 * acentuada en minúscula ("FERNANDO PéREZ", "NIñA", "RóSETO"): si no
 * queda ninguna letra minúscula sin tilde, el texto está pensado en
 * mayúsculas, así que se pasa a mayúsculas de verdad (é→É, ñ→Ñ).
 * Es el equivalente en la app offline del arreglo de codificación que
 * se aplicó en el modo web.
 */
function normalizarMayusculas(texto) {
  const valor = String(texto ?? '');
  if (!/[áéíóúüñçÁÉÍÓÚÜÑÇ]/.test(valor)) return valor;
  return /[a-z]/.test(valor) ? valor : valor.toUpperCase();
}

function escaparHtml(texto) {
  const div = document.createElement('div');
  div.textContent = normalizarMayusculas(texto ?? '');
  return div.innerHTML;
}

// ---------------------------------------------------------------------

document.addEventListener('DOMContentLoaded', () => {
  configurarAvisoSalidaConPendientes();
  ordenarPestanas();
  prepararCabecerasModulos();
  configurarAccesibilidadPestanas();
  setInterval(() => { actualizarIndicadorSincronizacion(); }, 60000);

  ['cs-animal', 'pl-animal', 'pesos-animal',
   'servicios-hembra', 'servicios-macho', 'servicios-dona',
   'nacimientos-hembra', 'nacimientos-macho', 'nacimientos-dona', 'desinc-animal']
    .forEach((idCampo) => BuscadorAnimal.crear(idCampo));

  el('form-login').addEventListener('submit', manejarLogin);
  el('boton-logout').addEventListener('click', manejarLogout);
  el('modal-detalle-registro-cerrar').addEventListener('click', cerrarDetalleRegistro);
  el('modal-detalle-registro-overlay').addEventListener('click', (evento) => {
    if (evento.target === evento.currentTarget) cerrarDetalleRegistro();
  });
  el('boton-cerrar-aviso-equipo-ocupado').addEventListener('click', () => el('aviso-equipo-ocupado').classList.add('oculto'));

  // Filtro por tipo de control (todos / individual / por lote).
  const grupoFiltroControl = el('filtro-tipo-control');
  if (grupoFiltroControl) {
    grupoFiltroControl.addEventListener('click', (evento) => {
      const boton = evento.target.closest('[data-filtro-control]');
      if (boton) aplicarFiltroTipoControl(boton.dataset.filtroControl);
    });
  }
  aplicarFiltroTipoControl('todos');

  el('boton-sincronizar').addEventListener('click', () => Sync.sincronizar({ forzarCatalogos: true }));
  const chipSync = el('boton-estado-sync');
  if (chipSync) {
    chipSync.addEventListener('click', () => {
      if (popoverSyncAbierto) {
        cerrarPopoverSync();
        return;
      }
      mostrarDetalleSincronizacion();
    });
  }
  const botonCerrarSync = el('popover-sync-cerrar');
  if (botonCerrarSync) botonCerrarSync.addEventListener('click', cerrarPopoverSync);
  el('etiqueta-version-frontend').addEventListener('click', () => {
    // Si hay una versión nueva esperando, tocar la versión reabre el
    // aviso accionable en vez de la ficha informativa.
    if (_versionNuevaDisponible) {
      _omitirAvisoDeVersion = null;
      mostrarAvisoActualizacion();
      return;
    }
    mostrarInfoVersion();
  });

  // Aviso de versión nueva: actualizar o posponer.
  const botonActualizar = el('boton-actualizar-app');
  if (botonActualizar) botonActualizar.addEventListener('click', manejarClickActualizar);

  const botonOmitir = el('boton-omitir-actualizacion');
  if (botonOmitir) {
    botonOmitir.addEventListener('click', () => {
      _omitirAvisoDeVersion = _versionNuevaDisponible ? _versionNuevaDisponible.version_app : null;
      ocultarAvisoActualizacion();
      const destino = el('estado-sync');
      if (destino) {
        destino.textContent = 'Actualización pospuesta. Para actualizar, tocá la versión en el encabezado o volvé a abrir la app.';
      }
    });
  }

  programarVerificacionDeVersion();
  document.querySelectorAll('.tab').forEach((tab) => {
    tab.addEventListener('click', () => mostrarTab(tab.dataset.tab));
  });
  el('boton-mostrar-form').addEventListener('click', () => {
    if (!puedeAbrirOtroFormulario('form-animal', 'form-animal-contenedor')) return;
    edicionAnimalUuid = null;
    alternarFormulario(true);
  });
  el('campo-sexo').addEventListener('change', () => poblarSelectsCategoriaPorSexo());

  if (el('buscador-animales')) {
    let temporizadorBusqueda = null;
    el('buscador-animales').addEventListener('input', () => {
      clearTimeout(temporizadorBusqueda);
      temporizadorBusqueda = setTimeout(filtrarListaAnimales, 120);
    });
  }
  el('boton-cancelar-form').addEventListener('click', manejarCancelarFormularioAnimal);  el('form-animal').addEventListener('submit', conGuardiaDeEnvio('form-animal', manejarGuardarAnimal));
  el('campo-arete').addEventListener('input', programarValidacionLocalArete);
  el('campo-arete').addEventListener('blur', () => {
    clearTimeout(temporizadorValidacionArete);
    validarAreteIngresado();
  });
  el('campo-sexo').addEventListener('change', poblarSelectsCategoriaPorSexo);

  [
    ['buscador-control-sanitario', 'lista-control-sanitario', 'buscador-control-sanitario-contador'],
    ['buscador-control-lote', 'lista-control-lote', 'buscador-control-lote-contador'],
    ['buscador-produccion-leche', 'lista-produccion-leche', 'buscador-produccion-leche-contador'],
    ['buscador-pesos', 'lista-pesos', 'buscador-pesos-contador'],
    ['buscador-servicios', 'lista-servicios', 'buscador-servicios-contador'],
    ['buscador-nacimientos', 'lista-nacimientos', 'buscador-nacimientos-contador'],
    ['buscador-desincorporacion', 'lista-desincorporacion', 'buscador-desincorporacion-contador'],
    ['buscador-movimiento-rebano', 'lista-movimiento-rebano', 'buscador-movimiento-rebano-contador'],
  ].forEach(([idBuscador, idLista, idContador]) => {
    const campoBuscador = el(idBuscador);
    if (!campoBuscador) return;
    let temporizadorBusquedaLista = null;
    campoBuscador.addEventListener('input', () => {
      clearTimeout(temporizadorBusquedaLista);
      temporizadorBusquedaLista = setTimeout(() => filtrarListaPorAnimal(idBuscador, idLista, idContador), 120);
    });
  });
  el('boton-mostrar-form-cs').addEventListener('click', () => {
    if (!puedeAbrirOtroFormulario('form-cs', 'form-cs-contenedor')) return;
    edicionControlSanitarioUuid = null;
    alternarFormularioControlSanitario(true);
  });
  el('boton-cancelar-form-cs').addEventListener('click', () => pedirConfirmacionYCerrar('form-cs', 'form-cs-contenedor', alternarFormularioControlSanitario));
  el('form-cs').addEventListener('submit', conGuardiaDeEnvio('form-cs', manejarGuardarControlSanitario));
  el('cs-producto-categoria').addEventListener('change', poblarProductosControlSanitario);
  el('cs-producto-agregar').addEventListener('click', agregarProductoControlSanitario);

  // Control sanitario por lote
  el('boton-mostrar-form-cs-lote').addEventListener('click', () => {
    edicionControlLoteUuid = null;
    alternarFormularioControlLote(true);
  });
  el('boton-cancelar-form-cs-lote').addEventListener('click', () => pedirConfirmacionYCerrar('form-cs-lote', 'form-cs-lote-contenedor', alternarFormularioControlLote));
  el('form-cs-lote').addEventListener('submit', conGuardiaDeEnvio('form-cs-lote', manejarGuardarControlLote));
  el('cs-lote-lote').addEventListener('change', actualizarInfoAnimalesLote);
  el('cs-lote-producto-categoria').addEventListener('change', poblarProductosControlLote);
  el('cs-lote-producto-agregar').addEventListener('click', agregarProductoControlLote);

  // Bajas (desincorporación)
  el('boton-mostrar-form-desincorporacion').addEventListener('click', () => {
    if (!puedeAbrirOtroFormulario('form-desincorporacion', 'form-desincorporacion-contenedor')) return;
    alternarFormularioDesincorporacion(true);
  });
  el('boton-cancelar-form-desincorporacion').addEventListener('click', () => pedirConfirmacionYCerrar('form-desincorporacion', 'form-desincorporacion-contenedor', alternarFormularioDesincorporacion));
  el('form-desincorporacion').addEventListener('submit', conGuardiaDeEnvio('form-desincorporacion', manejarGuardarDesincorporacion));
  el('desinc-tipo').addEventListener('change', alternarCamposCausaBaja);
  // "Otra (escribir)" muestra el campo de texto libre de cada causa.
  el('desinc-causa-descarte')?.addEventListener('change', () => alternarCausaOtra('desinc-causa-descarte', 'desinc-causa-descarte-otra'));
  el('desinc-causa-mortalidad')?.addEventListener('change', () => alternarCausaOtra('desinc-causa-mortalidad', 'desinc-causa-mortalidad-otra'));

  // Movimiento de rebaño
  el('boton-mostrar-form-movreb').addEventListener('click', () => {
    if (!puedeAbrirOtroFormulario('form-movreb', 'form-movreb-contenedor')) return;
    alternarFormularioMovimientoRebano(true);
  });
  el('boton-cancelar-form-movreb').addEventListener('click', () => pedirConfirmacionYCerrar('form-movreb', 'form-movreb-contenedor', alternarFormularioMovimientoRebano));
  el('form-movreb').addEventListener('submit', conGuardiaDeEnvio('form-movreb', manejarGuardarMovimientoRebano));
  el('movreb-tipo').addEventListener('change', alternarCamposTipoMovimiento);
  el('movreb-cargar-animales').addEventListener('click', cargarAnimalesOrigenMovimiento);
  el('boton-mostrar-form-pl').addEventListener('click', () => {
    if (!puedeAbrirOtroFormulario('form-pl', 'form-pl-contenedor')) return;
    edicionProduccionLecheUuid = null;
    alternarFormularioProduccionLeche(true);
  });
  el('boton-cancelar-form-pl').addEventListener('click', () => pedirConfirmacionYCerrar('form-pl', 'form-pl-contenedor', alternarFormularioProduccionLeche));
  el('form-pl').addEventListener('submit', conGuardiaDeEnvio('form-pl', manejarGuardarProduccionLeche));
  el('boton-mostrar-form-pesos').addEventListener('click', () => {
    if (!puedeAbrirOtroFormulario('form-pesos', 'form-pesos-contenedor')) return;
    edicionPesosUuid = null;
    alternarFormularioPesos(true);
  });
  el('boton-cancelar-form-pesos').addEventListener('click', () => pedirConfirmacionYCerrar('form-pesos', 'form-pesos-contenedor', alternarFormularioPesos));
  el('form-pesos').addEventListener('submit', conGuardiaDeEnvio('form-pesos', manejarGuardarPesos));

  // Pesaje rápido por lote
  el('boton-mostrar-pesaje-lote').addEventListener('click', () => alternarPesajePorLote(true));
  el('boton-cancelar-pesaje-lote').addEventListener('click', () => alternarPesajePorLote(false));
  el('boton-iniciar-pesaje-lote').addEventListener('click', iniciarPesajePorLote);
  el('boton-pesaje-lote-guardar').addEventListener('click', () => guardarPesajeLoteActual(true));
  el('boton-pesaje-lote-anterior').addEventListener('click', () => retrocederPesajeLote());
  el('boton-pesaje-lote-terminar').addEventListener('click', () => finalizarPesajePorLote(false));
  el('pesaje-lote-peso').addEventListener('input', actualizarGananciaPesajeLote);
  el('pesaje-lote-peso').addEventListener('keydown', (evento) => {
    if (evento.key === 'Enter') {
      evento.preventDefault();
      guardarPesajeLoteActual(true);
    }
  });
  el('boton-mostrar-form-servicios').addEventListener('click', () => {
    if (!puedeAbrirOtroFormulario('form-servicios', 'form-servicios-contenedor')) return;
    edicionServiciosUuid = null;
    alternarFormularioServicios(true);
  });
  el('boton-cancelar-form-servicios').addEventListener('click', () => pedirConfirmacionYCerrar('form-servicios', 'form-servicios-contenedor', alternarFormularioServicios));
  el('form-servicios').addEventListener('submit', conGuardiaDeEnvio('form-servicios', manejarGuardarServicios));
  el('servicios-parto-confirmado').addEventListener('change', alternarCamposPartoServicio);
  el('servicios-tipo').addEventListener('change', async () => {
    configurarCamposPorTipoServicio(true);
    if (!edicionServiciosUuid) {
      await poblarSelectAnimalesServicios();
      await actualizarDatosDerivadosServicio();
    }
  });
  el('servicios-fecha').max = new Date().toISOString().slice(0, 10);
  el('servicios-fecha').addEventListener('change', calcularFechaProbablePalpacionServicio);
  window.addEventListener('bovs:animal_seleccionado', (evento) => {
    if (evento.detail && evento.detail.idBase === 'servicios-hembra'
      && !edicionServiciosUuid
      && !el('form-servicios-contenedor').classList.contains('oculto')) {
      actualizarDatosDerivadosServicio();
    }
  });

  el('boton-mostrar-form-nacimientos').addEventListener('click', () => {
    if (!puedeAbrirOtroFormulario('form-nacimientos', 'form-nacimientos-contenedor')) return;
    edicionNacimientosUuid = null;
    alternarFormularioNacimientos(true);
  });
  el('boton-cancelar-form-nacimientos').addEventListener('click', () => pedirConfirmacionYCerrar('form-nacimientos', 'form-nacimientos-contenedor', alternarFormularioNacimientos));
  el('boton-cancelar-modal-nacimiento').addEventListener('click', () => pedirConfirmacionYCerrar('form-nacimientos', 'form-nacimientos-contenedor', alternarFormularioNacimientos));
  el('boton-fichar-nacimiento').addEventListener('click', manejarFicharNacimiento);
  el('form-nacimientos').addEventListener('submit', conGuardiaDeEnvio('form-nacimientos', manejarGuardarNacimientos));
  el('modal-nacimiento-overlay').addEventListener('click', (evento) => {
    if (evento.target === evento.currentTarget) {
      pedirConfirmacionYCerrar('form-nacimientos', 'form-nacimientos-contenedor', alternarFormularioNacimientos);
    }
  });
  document.addEventListener('keydown', manejarEscapeModalNacimiento);
  el('nacimientos-origen-tipo').addEventListener('change', cambiarModoBusquedaNacimiento);
  el('nacimientos-servicio-buscar').addEventListener('input', buscarServiciosParaNacimiento);
  el('nacimientos-servicio-buscar').addEventListener('focus', buscarServiciosParaNacimiento);
  el('nacimientos-servicio-buscar').addEventListener('keydown', (evento) => {
    if (evento.key === 'Escape') el('nacimientos-servicio-opciones').classList.add('oculto');
    if (evento.key === 'Enter') {
      const primeraOpcion = el('nacimientos-servicio-opciones').querySelector('[data-servicio-uuid]');
      if (primeraOpcion) {
        evento.preventDefault();
        seleccionarServicioParaNacimiento(primeraOpcion.dataset.servicioUuid);
      }
    }
  });
  el('nacimientos-servicio-opciones').addEventListener('mousedown', (evento) => {
    evento.preventDefault();
  });
  el('nacimientos-cancelar-origen').addEventListener('click', cancelarOrigenNacimiento);
  window.addEventListener('bovs:animal_seleccionado', manejarCambioOrigenNacimiento);

  renderizarListaReportes();

  el('form-reconexion-local').addEventListener('submit', manejarReconexionLocal);
  el('boton-usar-otro-usuario').addEventListener('click', manejarUsarOtroUsuario);
  el('boton-olvidar-equipo').addEventListener('click', manejarOlvidarEquipo);

  el('form-confirmar-offline').addEventListener('submit', manejarConfirmarOffline);
  el('boton-omitir-confirmar-offline').addEventListener('click', manejarOmitirConfirmarOffline);

  el('form-renovar-sesion').addEventListener('submit', manejarRenovarSesion);
  el('boton-instalar-app').addEventListener('click', manejarInstalarApp);
  el('boton-cerrar-instalar-app').addEventListener('click', ocultarCajaInstalacion);
  el('boton-cargar-datos-web').addEventListener('click', manejarCargarDatosNavegador);
  ['reporte-fecha-desde', 'reporte-fecha-hasta', 'reporte-sexo', 'reporte-lote', 'reporte-animal']
    .forEach((id) => {
      const elFiltro = el(id);
      if (elFiltro) {
        elFiltro.addEventListener('change', renderizarListaReportes);
        elFiltro.addEventListener('input', renderizarListaReportes);
      }
    });

  iniciar();
});