/**
 * Motor del cliente offline ejecutado de verdad en Node.
 *
 * Carga los MISMOS archivos que usa la PWA (db.js, dispositivo.js, api.js,
 * config.js, sync.js) sobre una IndexedDB real (fake-indexeddb) y habla con
 * el API REAL, para comprobar lo que el contrato del servidor no puede ver:
 *
 *   1. `Db.tomarSiguienteCodigo` genera el código provisional;
 *   2. el outbox guarda los datos como los arma cada módulo;
 *   3. `Sync.enviarPendientes` sube los animales, recibe el código definitivo
 *      y REMAPEA la copia local y los pendientes que apuntaban al provisional;
 *   4. tras "recargar la app" (mapa en memoria vacío), el mapa se reconstruye
 *      desde `codigo_anterior` y un pendiente viejo igual se envía bien.
 *
 * Lo lanza api/test/validar_motor_offline.php, que crea el token y limpia.
 * Variables: SOVCA_TOKEN, SOVCA_FUNDO, SOVCA_USUARIO, SOVCA_DISPOSITIVO, SOVCA_API,
 *            SOVCA_NODE_PATH (para resolver fake-indexeddb).
 */

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const DIR_JS = path.join(__dirname, '..', '..', 'app_offline', 'js');
const ENV = process.env;

const ok = [];
const fallos = [];
const creados = { animales: [], servicios: [], pesos: [], control_sanitario: [] };

function comprobar(condicion, descripcion, detalle = '') {
  if (condicion) {
    ok.push(descripcion);
    console.log('  OK    ' + descripcion + (detalle ? '  [' + detalle + ']' : ''));
  } else {
    fallos.push(descripcion);
    console.log('  FALLA ' + descripcion + (detalle ? '  [' + detalle + ']' : ''));
  }
}

// --------------------------------------------------------------------------
// entorno mínimo de navegador
// --------------------------------------------------------------------------
function crearLocalStorage() {
  const datos = new Map();
  return {
    getItem: (k) => (datos.has(k) ? datos.get(k) : null),
    setItem: (k, v) => datos.set(k, String(v)),
    removeItem: (k) => datos.delete(k),
    clear: () => datos.clear(),
  };
}

const { indexedDB, IDBKeyRange } = require('fake-indexeddb');
const { webcrypto } = require('crypto');

const contexto = {
  console,
  setTimeout,
  clearTimeout,
  setInterval: () => 0,
  clearInterval: () => {},
  crypto: webcrypto,
  fetch: globalThis.fetch,
  btoa: (s) => Buffer.from(s, 'binary').toString('base64'),
  atob: (s) => Buffer.from(s, 'base64').toString('binary'),
  TextEncoder,
  TextDecoder,
  navigator: { onLine: true, userAgent: 'node-motor-offline' },
  localStorage: crearLocalStorage(),
  indexedDB,
  IDBKeyRange,
  CustomEvent: class CustomEvent {
    constructor(tipo, opciones) {
      this.type = tipo;
      this.detail = opciones && opciones.detail;
    }
  },
  Event: class Event {
    constructor(tipo) { this.type = tipo; }
  },
};
contexto.window = contexto;
contexto.self = contexto;
contexto.globalThis = contexto;
contexto.addEventListener = () => {};
contexto.removeEventListener = () => {};
contexto.dispatchEvent = () => true;
contexto.document = {
  addEventListener: () => {},
  removeEventListener: () => {},
  querySelector: () => null,
  querySelectorAll: () => [],
  createElement: () => ({ style: {}, setAttribute: () => {}, appendChild: () => {} }),
  body: { appendChild: () => {}, classList: { add: () => {}, remove: () => {} } },
  visibilityState: 'visible',
};
contexto.__ENV = ENV;

// El equipo real ya tiene su UUID de dispositivo en localStorage; sin esto la
// app generaría uno nuevo y el servidor lo rechazaría por no estar vinculado.
if (ENV.SOVCA_DISPOSITIVO) {
  contexto.localStorage.setItem('bovs_dispositivo_id', ENV.SOVCA_DISPOSITIVO);
  contexto.localStorage.setItem('bovs_nombre_dispositivo', 'Equipo de prueba');
}

vm.createContext(contexto);

for (const archivo of ['db.js', 'dispositivo.js', 'api.js', 'config.js', 'sync.js']) {
  const codigo = fs.readFileSync(path.join(DIR_JS, archivo), 'utf8');
  vm.runInContext(codigo, contexto, { filename: archivo });
}

// --------------------------------------------------------------------------
// recorrido
// --------------------------------------------------------------------------
const guion = `
(async () => {
  const fundoId = Number(__ENV.SOVCA_FUNDO);
  const token = __ENV.SOVCA_TOKEN;
  const dispositivo = __ENV.SOVCA_DISPOSITIVO;

  CONFIG.API_BASE = __ENV.SOVCA_API;

  const uuid = () => generarUUID();
  const hoy = () => new Date().toISOString().slice(0, 10);
  const haceDias = (d) => {
    const f = new Date();
    f.setDate(f.getDate() - d);
    return f.toISOString().slice(0, 10);
  };

  // --- equipo preparado: sesión + fundo descargado -----------------------
  await Db.guardarSesion({
    token,
    usuario: __ENV.SOVCA_USUARIO,
    usuario_login: 'prueba',
    dispositivo,
    sesion_local: false,
    modo_offline: 'gestion',
    fundo_id: fundoId,
    fundo_activo: fundoId,
    fundos: [{ id: fundoId, nombre: 'FUNDO PRUEBA' }],
  });
  await Db.descargarFundo({ id: fundoId, nombre: 'FUNDO PRUEBA', tipo_ganaderia_id: 1 });
  Db.usarFundo(fundoId);

  const sesion = await Db.obtenerSesion();
  if (!sesion || Number(Db.fundoActivo()) !== fundoId) {
    throw new Error('no se pudo dejar el fundo activo en el equipo');
  }

  // --- 1. dos fichas nuevas (hembra y macho) sin conexión ----------------
  const codHembra = await Db.tomarSiguienteCodigo('animales', fundoId, 'SOVCA');
  const codMacho = await Db.tomarSiguienteCodigo('animales', fundoId, 'SOVCA');

  const uHembra = uuid();
  const uMacho = uuid();

  const hembra = {
    uuid_local: uHembra,
    codigo_rgb_id_animal: codHembra.codigo,
    codigo_provisional: true,
    nombre: 'HEMBRA MOTOR',
    sexo: 1,
    nro_arete: 'M' + Math.floor(1000000 + Math.random() * 8999999),
    aptitud: 1,
    mrazas_id: 4,
    estatus_animal: 1,
    servicio: 0,
    f_nacimiento: haceDias(1000),
    f_ingreso: haceDias(1000),
    nac_fundo: true,
    procedencia: 'Nacido en este fundo',
    observaciones: '',
    estado_sync: 'pendiente',
  };
  const macho = Object.assign({}, hembra, {
    uuid_local: uMacho,
    codigo_rgb_id_animal: codMacho.codigo,
    nombre: 'MACHO MOTOR',
    sexo: 2,
    nro_arete: 'M' + Math.floor(1000000 + Math.random() * 8999999),
  });

  await Db.guardar('animales', hembra);
  await Db.guardar('animales', macho);
  await Db.guardar('outbox', {
    uuid_local: uHembra, modulo: 'animales', version_base: null,
    datos: datosParaEnviar(hembra), intentos: 0, creado: new Date().toISOString(),
  });
  await Db.guardar('outbox', {
    uuid_local: uMacho, modulo: 'animales', version_base: null,
    datos: datosParaEnviar(macho), intentos: 0, creado: new Date().toISOString(),
  });

  // --- 2. servicio, pesaje y control apuntando a los PROVISIONALES -------
  const uServicio = uuid();
  const servicio = {
    uuid_local: uServicio,
    codigo_servicio: 'RSA-PEND-' + Date.now(),
    codigo_rgb_id_animal_hembra: codHembra.codigo,
    codigo_rgb_id_animal_macho: codMacho.codigo,
    codigo_rgb_id_animal_dona: '',
    f_servicio: haceDias(20),
    nro_servicio: 1,
    mservicios_id: 1,
    aptitud: 1,
    mrazas_id: 4,
    observaciones: 'servicio offline',
    medofisiologicos_id: 3,
    parto: 0,
    f_palpacion: null,
    fecha_parto: null,
    nro_terneros: null,
    dnp: null,
    f_probable_parto: null,
    f_ultimo_parto: null,
    f_secado: null,
    estado_sync: 'pendiente',
  };
  await Db.guardar('servicios', servicio);
  await Db.guardar('outbox', {
    uuid_local: uServicio, modulo: 'servicios', version_base: null,
    datos: datosParaEnviar(servicio), intentos: 0, creado: new Date().toISOString(),
  });

  const uPeso = uuid();
  const peso = {
    uuid_local: uPeso,
    codigo_rgb_id_animal: codHembra.codigo,
    fecha_pesaje: hoy(),
    peso: 340,
    estado_sync: 'pendiente',
  };
  await Db.guardar('pesos', peso);
  await Db.guardar('outbox', {
    uuid_local: uPeso, modulo: 'pesos', version_base: null,
    datos: datosParaEnviar(peso), intentos: 0, creado: new Date().toISOString(),
  });

  const uControl = uuid();
  const control = {
    uuid_local: uControl,
    codigo_rgb_id_animal: codHembra.codigo,
    fecha_control: hoy(),
    evento: 'Vacunación',
    responsable: 'Motor offline',
    observaciones: '',
    productos_aplicados: [],
    estado_sync: 'pendiente',
  };
  await Db.guardar('control_sanitario', control);
  await Db.guardar('outbox', {
    uuid_local: uControl, modulo: 'control_sanitario', version_base: null,
    datos: datosParaEnviar(control), intentos: 0, creado: new Date().toISOString(),
  });

  // --- 3. sincronizar (todo en la misma corrida) -------------------------
  const resumen = await Sync.enviarPendientes(sesion, fundoId);

  const animalHembra = await Db.obtener('animales', uHembra);
  const animalMacho = await Db.obtener('animales', uMacho);
  const servicioLocal = await Db.obtener('servicios', uServicio);
  const pesoLocal = await Db.obtener('pesos', uPeso);
  const controlLocal = await Db.obtener('control_sanitario', uControl);
  const outbox = await Db.obtenerTodos('outbox');

  // --- 4. "recargar la app": pendiente viejo con el código provisional ---
  const uPeso2 = uuid();
  const peso2 = {
    uuid_local: uPeso2,
    codigo_rgb_id_animal: codHembra.codigo, // el provisional, como quedó guardado
    fecha_pesaje: haceDias(1),
    peso: 345,
    estado_sync: 'pendiente',
  };
  await Db.guardar('pesos', peso2);
  await Db.guardar('outbox', {
    uuid_local: uPeso2, modulo: 'pesos', version_base: null,
    datos: datosParaEnviar(peso2), intentos: 0, creado: new Date().toISOString(),
  });

  const resumen2 = await Sync.enviarPendientes(sesion, fundoId);
  const peso2Local = await Db.obtener('pesos', uPeso2);
  const outbox2 = await Db.obtenerTodos('outbox');

  // --- 5. lo que quedó en el servidor ------------------------------------
  const pullAnimales = await Api.pull(token, 'animales', '1970-01-01 00:00:00|0', dispositivo, fundoId);
  const pullServicios = await Api.pull(token, 'servicios', '1970-01-01 00:00:00|0', dispositivo, fundoId);
  const pullPesos = await Api.pull(token, 'pesos', '1970-01-01 00:00:00|0', dispositivo, fundoId);
  const pullControl = await Api.pull(token, 'control_sanitario', '1970-01-01 00:00:00|0', dispositivo, fundoId);

  const enServidor = (filas, uuidLocal) =>
    (filas || []).filter((f) => f.uuid_local === uuidLocal).length;

  return {
    resumen,
    resumen2,
    erroresOutbox: outbox.map((o) => o.ultimo_error || null).filter(Boolean),
    erroresOutbox2: outbox2.map((o) => o.ultimo_error || null).filter(Boolean),
    dispositivoUsado: Dispositivo.obtenerId(),
    provisionalHembra: codHembra.codigo,
    provisionalMacho: codMacho.codigo,
    animalHembra,
    animalMacho,
    servicioLocal,
    pesoLocal,
    controlLocal,
    peso2Local,
    outboxRestante: outbox.length,
    outboxRestante2: outbox2.length,
    servidor: {
      hembra: enServidor(pullAnimales.registros, uHembra),
      macho: enServidor(pullAnimales.registros, uMacho),
      servicio: enServidor(pullServicios.registros, uServicio),
      peso: enServidor(pullPesos.registros, uPeso),
      peso2: enServidor(pullPesos.registros, uPeso2),
      control: enServidor(pullControl.registros, uControl),
    },
    uuids: { animales: [uHembra, uMacho], servicios: [uServicio], pesos: [uPeso, uPeso2], control_sanitario: [uControl] },
  };
})()
`;

vm.runInContext(guion, contexto, { filename: 'recorrido.js' })
  .then((r) => {
    console.log('\n=== Motor offline (cliente real) ===');

    comprobar(r.resumen.errores === 0, 'La sincronización no deja registros con error', 'enviados=' + r.resumen.enviados + ' errores=' + r.resumen.errores + ' ' + JSON.stringify(r.resumen.modulos_con_error || []) + ' ' + JSON.stringify(r.erroresOutbox));
    comprobar(r.resumen.enviados === 5, 'Se envían los 5 pendientes (2 animales, servicio, peso, control)', 'enviados=' + r.resumen.enviados);
    comprobar(r.outboxRestante === 0, 'El outbox queda vacío tras sincronizar', 'pendientes=' + r.outboxRestante);

    comprobar(r.animalHembra.codigo_rgb_id_animal !== r.provisionalHembra, 'La hembra recibe el código definitivo en la copia local', r.animalHembra.codigo_rgb_id_animal);
    comprobar(r.animalMacho.codigo_rgb_id_animal !== r.provisionalMacho, 'El macho recibe el código definitivo en la copia local', r.animalMacho.codigo_rgb_id_animal);
    comprobar(r.animalHembra.codigo_anterior === r.provisionalHembra, 'La hembra guarda codigo_anterior (mapa reconstruible)', r.animalHembra.codigo_anterior);
    comprobar(r.animalHembra.estado_sync === 'sincronizado', 'La hembra queda marcada como sincronizada');

    comprobar(r.servicioLocal.codigo_rgb_id_animal_hembra === r.animalHembra.codigo_rgb_id_animal, 'El servicio se remapea al código definitivo de la hembra', r.servicioLocal.codigo_rgb_id_animal_hembra);
    comprobar(r.servicioLocal.codigo_rgb_id_animal_macho === r.animalMacho.codigo_rgb_id_animal, 'El servicio se remapea al código definitivo del macho', r.servicioLocal.codigo_rgb_id_animal_macho);
    comprobar(r.pesoLocal.codigo_rgb_id_animal === r.animalHembra.codigo_rgb_id_animal, 'El pesaje se remapea al código definitivo');
    comprobar(r.controlLocal.codigo_rgb_id_animal === r.animalHembra.codigo_rgb_id_animal, 'El control sanitario se remapea al código definitivo');

    comprobar(r.resumen2.errores === 0, 'Un pendiente viejo (creado antes de la recarga) también sube', 'enviados=' + r.resumen2.enviados + ' errores=' + r.resumen2.errores);
    comprobar(r.outboxRestante2 === 0, 'El outbox vuelve a quedar vacío');
    comprobar(r.peso2Local.codigo_rgb_id_animal === r.animalHembra.codigo_rgb_id_animal, 'El mapa se reconstruye desde codigo_anterior tras recargar', r.peso2Local.codigo_rgb_id_animal);

    comprobar(r.servidor.hembra === 1 && r.servidor.macho === 1, 'Los 2 animales están en el servidor', JSON.stringify(r.servidor));
    comprobar(r.servidor.servicio === 1, 'El servicio está en el servidor');
    comprobar(r.servidor.peso === 1 && r.servidor.peso2 === 1, 'Los 2 pesajes están en el servidor');
    comprobar(r.servidor.control === 1, 'El control sanitario está en el servidor');

    console.log('\n  Comprobaciones: ' + ok.length + ' correctas, ' + fallos.length + ' con falla');
    console.log('__UUID__' + JSON.stringify(r.uuids));
    process.exit(fallos.length === 0 ? 0 : 1);
  })
  .catch((e) => {
    console.error('EXCEPCIÓN: ' + (e && e.stack ? e.stack : e));
    console.log('__UUID__' + JSON.stringify(creados));
    process.exit(1);
  });
