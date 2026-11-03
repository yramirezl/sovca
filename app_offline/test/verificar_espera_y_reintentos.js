// PRUEBA DEL MOTOR DE SINCRONIZACIÓN (sin navegador).
//
// Carga config.js y sync.js en un contexto aislado con Db/Api simulados y
// comprueba las reglas que evitan que un registro quede rechazándose para
// siempre:
//
//   1. un registro que depende de un animal creado sin conexión espera a
//      que suba el alta y después se envía con el código definitivo;
//   2. la corrida automática respeta la espera entre reintentos y la
//      sincronización manual la salta;
//   3. tras seis rechazos el registro se bloquea y deja de reintentarse;
//   4. un código provisional sin animal en el equipo se bloquea con un
//      motivo claro en vez de esperar indefinidamente;
//   5. la descarga (pull) conserva `codigo_anterior`, que es lo que
//      permite reconstruir el mapa provisional → definitivo.
//
// Uso (desde la raíz del proyecto):
//   node app_offline/test/verificar_espera_y_reintentos.js
const fs = require('fs');
const vm = require('vm');
const path = require('path');

const RAIZ = require('path').join(__dirname, '..', 'js');

function crearContexto(datosIniciales) {
  const almacenes = JSON.parse(JSON.stringify(datosIniciales));
  const llamadasPush = [];

  const Db = {
    obtenerTodos: async (store) => (almacenes[store] || []).slice(),
    obtener: async (store, id) => (almacenes[store] || []).find((r) => r.uuid_local === id) || null,
    guardar: async (store, fila) => {
      almacenes[store] = almacenes[store] || [];
      const i = almacenes[store].findIndex((r) => r.uuid_local === fila.uuid_local);
      if (i === -1) almacenes[store].push(JSON.parse(JSON.stringify(fila)));
      else almacenes[store][i] = JSON.parse(JSON.stringify(fila));
    },
    guardarVarios: async (store, filas) => {
      for (const f of filas) await Db.guardar(store, f);
    },
    eliminar: async (store, id) => {
      almacenes[store] = (almacenes[store] || []).filter((r) => r.uuid_local !== id);
    },
    fundoActivo: () => 2,
    guardarSyncMeta: async () => {},
    obtenerSyncMeta: async () => null,
    hayPendientesEnAlgunFundo: async () => false,
    listarFundosMeta: async () => [{ id: 2, nombre: 'Fundo prueba' }],
    usarFundo: () => {},
  };

  const Api = {
    push: async (token, modulo, cambios) => {
      llamadasPush.push({ modulo, cambios: JSON.parse(JSON.stringify(cambios)) });
      return {
        resultados: cambios.map((c) => ({ uuid_local: c.uuid_local, resultado: 'creado' })),
      };
    },
    pull: async () => ({ registros: [], hay_mas: false }),
  };

  const contexto = {
    console,
    Db,
    Api,
    Dispositivo: { obtenerId: () => 'd5f0f0e0-0000-4000-8000-000000000001' },
    mensajeError: (e) => (e && e.message ? e.message : String(e)),
    crypto: { randomUUID: () => 'uuid-generado' },
    navigator: { onLine: true },
    CustomEvent: class { constructor(t, o) { this.type = t; this.detail = o && o.detail; } },
    setInterval: () => 0,
    setTimeout: () => 0,
    clearTimeout: () => 0,
    window: { dispatchEvent: () => {}, addEventListener: () => {} },
  };
  contexto.window.CONFIG = undefined;
  contexto.globalThis = contexto;
  vm.createContext(contexto);

  for (const archivo of ['config.js', 'sync.js']) {
    vm.runInContext(fs.readFileSync(path.join(RAIZ, archivo), 'utf8'), contexto, { filename: archivo });
  }

  return { contexto, almacenes, llamadasPush, Sync: vm.runInContext('Sync', contexto) };
}

let ok = 0;
let fallos = 0;
function comprobar(cond, desc, det) {
  if (cond) { ok++; console.log('  OK    ' + desc + (det ? '  [' + det + ']' : '')); }
  else { fallos++; console.log('  FALLA ' + desc + (det ? '  [' + det + ']' : '')); }
}

async function caso1() {
  console.log('\n=== 1. Movimiento sobre un animal creado sin conexión ===');
  const { contexto, almacenes, llamadasPush, Sync } = crearContexto({
    animales: [{ uuid_local: 'a1', codigo_rgb_id_animal: 'SOVCA-02-PEND-1247B0', estado_sync: 'pendiente' }],
    outbox: [{
      uuid_local: 'm1', modulo: 'movimiento_rebano', intentos: 0, estado_sync: 'pendiente',
      datos: { tipo_movimiento: 'LOTE', animales: ['SOVCA-02-PEND-1247B0'] },
    }],
    movimiento_rebano: [{ uuid_local: 'm1', animales: ['SOVCA-02-PEND-1247B0'], estado_sync: 'error' }],
  });

  const r = await Sync.enviarPendientes({ token: 'tk', modo_offline: 'gestion' }, 2, { automatico: true });

  const fila = almacenes.outbox.find((x) => x.uuid_local === 'm1');
  comprobar(llamadasPush.filter((p) => p.modulo === 'movimiento_rebano').length === 0,
    'No se envía el movimiento (no hay rechazo del servidor)');
  comprobar(fila.espera_animal === 'SOVCA-02-PEND-1247B0', 'Queda marcado esperando al animal', fila.espera_animal);
  comprobar(/Esperando que el animal/.test(fila.ultimo_error || ''), 'El motivo es claro para el usuario', fila.ultimo_error);
  comprobar(!fila.bloqueado, 'No se bloquea: se resolverá solo');
  comprobar(r.errores === 0, 'No se cuenta como error', 'errores=' + r.errores);
  const reg = almacenes.movimiento_rebano.find((x) => x.uuid_local === 'm1');
  comprobar(reg.estado_sync === 'pendiente', 'El registro deja de estar en rojo', reg.estado_sync);

  // El animal sube: el movimiento debe salir en la siguiente pasada.
  almacenes.animales[0].estado_sync = 'sincronizado';
  almacenes.animales[0].codigo_rgb_id_animal = 'SOVCA-02-0117';
  almacenes.animales[0].codigo_anterior = 'SOVCA-02-PEND-1247B0';
  await Sync.enviarPendientes({ token: 'tk', modo_offline: 'gestion' }, 2, { automatico: true });
  const pushMov = llamadasPush.filter((p) => p.modulo === 'movimiento_rebano');
  comprobar(pushMov.length === 1, 'Cuando el animal sube, el movimiento se envía solo');
  comprobar(JSON.stringify(pushMov[0].cambios[0].datos.animales) === '["SOVCA-02-0117"]',
    'Viaja con el código definitivo, no con el provisional',
    JSON.stringify(pushMov[0].cambios[0].datos.animales));
}

async function caso2() {
  console.log('\n=== 2. Espera progresiva entre reintentos ===');
  const futuro = new Date(Date.now() + 5 * 60000).toISOString();
  const { contexto, llamadasPush, Sync } = crearContexto({
    animales: [],
    outbox: [{
      uuid_local: 'p1', modulo: 'pesos', datos: { codigo_rgb_id_animal: 'SOVCA-02-0001' },
      intentos: 3, proximo_intento: futuro,
    }],
  });

  await Sync.enviarPendientes({ token: 'tk', modo_offline: 'gestion' }, 2, { automatico: true });
  comprobar(llamadasPush.length === 0, 'La corrida de fondo respeta la espera');

  await Sync.enviarPendientes({ token: 'tk', modo_offline: 'gestion' }, 2, { automatico: false });
  comprobar(llamadasPush.length === 1, 'La sincronización manual reintenta ya');
}

async function caso3() {
  console.log('\n=== 3. Bloqueo tras seis rechazos ===');
  const { contexto, almacenes, Sync } = crearContexto({
    animales: [],
    outbox: [{ uuid_local: 'x1', modulo: 'animales', intentos: 0, datos: {} }],
    animales_store: [],
  });
  const esperas = [];
  for (let i = 1; i <= 6; i++) {
    const bloqueado = await Sync._registrarFallo('x1', 'El servidor rechazó este registro.');
    const fila = almacenes.outbox.find((x) => x.uuid_local === 'x1');
    esperas.push(fila.proximo_intento ? 1 : 0);
    if (i === 6) {
      comprobar(bloqueado === true, 'Al sexto intento se bloquea');
      comprobar(fila.bloqueado === true, 'Queda marcado para revisión');
      comprobar(fila.intentos === 6, 'Se guardan los intentos', fila.intentos);
    }
  }
  comprobar(esperas.every((v) => v === 1), 'Cada rechazo programa una nueva espera');

  // Un registro bloqueado no se reenvía en las corridas automáticas.
  const { contexto: c2, llamadasPush, Sync: Sync2 } = crearContexto({
    animales: [],
    outbox: [{ uuid_local: 'x2', modulo: 'pesos', bloqueado: true, intentos: 6, datos: { codigo_rgb_id_animal: 'SOVCA-02-0001' } }],
  });
  await Sync2.enviarPendientes({ token: 'tk', modo_offline: 'gestion' }, 2, { automatico: true });
  comprobar(llamadasPush.length === 0, 'Los bloqueados no se reintentan solos');
}

async function caso4() {
  console.log('\n=== 4. Código provisional cuyo alta ya no existe ===');
  const { contexto, almacenes, Sync } = crearContexto({
    animales: [],
    outbox: [{
      uuid_local: 'm2', modulo: 'movimiento_rebano', intentos: 0,
      datos: { tipo_movimiento: 'LOTE', animales: ['SOVCA-02-PEND-999999'] },
    }],
    movimiento_rebano: [{ uuid_local: 'm2', estado_sync: 'pendiente' }],
  });
  await Sync.enviarPendientes({ token: 'tk', modo_offline: 'gestion' }, 2, { automatico: true });
  const fila = almacenes.outbox.find((x) => x.uuid_local === 'm2');
  comprobar(fila.bloqueado === true, 'Se bloquea en vez de esperar para siempre');
  comprobar(/ya no está en este equipo/.test(fila.ultimo_error || ''), 'El motivo lo explica', fila.ultimo_error);
}

async function caso5() {
  console.log('\n=== 5. La descarga conserva el código anterior del animal ===');
  const { almacenes, Sync } = crearContexto({
    animales: [{
      uuid_local: 'a1',
      codigo_rgb_id_animal: 'SOVCA-02-0117',
      codigo_anterior: 'SOVCA-02-PEND-1247B0',
      codigo_provisional: 'SOVCA-02-PEND-1247B0',
      estado_sync: 'sincronizado',
    }],
    outbox: [],
  });

  const Sync2 = Sync;
  Sync2.traerCambios = Sync.traerCambios;
  await Sync2.traerCambios({ token: 'tk' }, 'animales', 2);

  const fila = almacenes.animales.find((x) => x.uuid_local === 'a1');
  comprobar(fila && fila.estado_sync === 'sincronizado', 'La fila se reemplaza con la del servidor', fila && fila.estado_sync);
  comprobar(fila && fila.codigo_anterior === 'SOVCA-02-PEND-1247B0',
    'Se conserva codigo_anterior (mapa de renombres)', fila && fila.codigo_anterior);

  const mapa = await Sync2.mapaCodigosRenombrados();
  comprobar(mapa.get('SOVCA-02-PEND-1247B0') === 'SOVCA-02-0117',
    'El mapa provisional→definitivo se reconstruye tras la descarga');
}

(async () => {
  await caso1();
  await caso2();
  await caso3();
  await caso4();
  await caso5();
  console.log('\nResultado: ' + ok + ' correctas, ' + fallos + ' con falla.');
  process.exit(fallos === 0 ? 0 : 1);
})();
