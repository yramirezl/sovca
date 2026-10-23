// Capa de datos local. IndexedDB puro (sin librerías externas) para
// que la app funcione sin conexión desde la primera vez que se abre,
// sin depender de que haya cargado algo desde un CDN.
//
// MULTIFUNDO (v10): un mismo usuario puede manejar varios fundos, así que
// los datos de cada fundo viven en SU PROPIA base de datos
// (bovs_offline_f<id>). El aislamiento es por construcción: listas,
// buscador, validación de aretes, reportes, conflictos y pendientes de
// sincronizar nunca pueden mezclar datos de otro fundo, aunque una
// consulta se olvide de filtrar. Cambiar de fundo = cambiar de base.
//
//   Base GLOBAL (bovs_offline)  -> datos del equipo:
//     sesion             -> una sola fila (clave: "actual") con token,
//                           usuario, fundos conocidos y fundo activo
//     credencial_offline -> credencial derivada (PBKDF2) para volver a
//                           entrar SIN conexión en este equipo
//     fundos_meta        -> qué fundos tiene descargados este equipo
//
//   Base DE CADA FUNDO (bovs_offline_f<id>) -> datos de campo:
//     animales, control_sanitario, produccion_leche, pesos, servicios,
//     nacimientos  -> copia local del fundo (keyPath: 'uuid_local')
//     outbox       -> cambios pendientes de enviar al servidor
//     rangos       -> rango de números reservados por módulo
//     conflictos   -> ediciones que chocaron con el servidor
//     catalogos    -> razas, aptitudes, lotes (para los <select>)
//     reportes_cache, sync_meta (cursores de sincronización)
//
// La credencial a propósito NO se borra al cerrar sesión (ver
// Db.cerrarSesion vs Db.olvidarEquipo): si se borrara ahí, perdería
// sentido — la idea es justo poder reconectarse localmente después de
// que la sesión se cerró por algún motivo.

const DB_NOMBRE = 'bovs_offline';           // base global del equipo
const DB_PREFIJO_FUNDO = 'bovs_offline_f';  // una base por fundo
// v7: reportes offline y caché local. v9: marca de migraciones de sync.
// v10: MULTIFUNDO — una base por fundo + fundos_meta.
const DB_VERSION = 10;
// Versión de la base DE CADA FUNDO. v2: se agregó el almacén
// control_sanitario_lotes (control sanitario aplicado a lotes).
// v3: desincorporacion (bajas) y movimiento_rebano.
// v4: almacenes "fotos" (copia local de la foto de cada animal, para
// verla sin conexión) y "fotos_pendientes" (fotos tomadas en el corral
// que todavía no llegaron al servidor).
const DB_VERSION_FUNDO = 4;

const ALMACENES_DE_FUNDO = [
  'animales', 'control_sanitario', 'control_sanitario_lotes', 'desincorporacion',
  'movimiento_rebano', 'produccion_leche',
  'pesos', 'servicios', 'nacimientos', 'outbox', 'rangos', 'conflictos',
  'catalogos', 'reportes_cache', 'sync_meta',
  'fotos', 'fotos_pendientes',
];

let _dbGlobalPromise = null;
let _dbFundoPromesas = {};
let _fundoActivo = 0;
let _migracionPendiente = null;

/** Nombre de la base de datos de un fundo. */
function nombreDbFundo(fundoId) {
  return DB_PREFIJO_FUNDO + String(parseInt(fundoId, 10) || 0);
}

/** ¿Este almacén guarda datos de campo (por fundo) o del equipo? */
function esAlmacenDeFundo(almacen) {
  return ALMACENES_DE_FUNDO.indexOf(almacen) !== -1;
}

/**
 * Abre la base que corresponde al almacén pedido: la del FUNDO ACTIVO
 * para los datos de campo, o la global para sesión/credencial/fundos.
 * Debe llamarse SIEMPRE con el nombre del almacén.
 */
function abrirDb(almacen) {
  if (almacen && esAlmacenDeFundo(almacen)) {
    if (!_fundoActivo) {
      return Promise.reject(new Error('No hay un fundo activo en este dispositivo.'));
    }
    return abrirDbFundo(_fundoActivo);
  }
  return abrirDbGlobal();
}

/** Base global: sesión, credencial y fundos descargados en el equipo. */
function abrirDbGlobal() {
  if (_dbGlobalPromise) return _dbGlobalPromise;

  _dbGlobalPromise = new Promise((resolve, reject) => {
    const req = indexedDB.open(DB_NOMBRE, DB_VERSION);

    req.onupgradeneeded = (evento) => {
      const db = evento.target.result;

      if (!db.objectStoreNames.contains('sesion')) {
        db.createObjectStore('sesion', { keyPath: 'clave' });
      }
      if (!db.objectStoreNames.contains('credencial_offline')) {
        // Una fila por usuario que activó "modo offline" en este
        // equipo: { usuario, salHex, hashHex, usuarioInfo, dispositivo,
        // fundos, fundo_activo, modoOffline, ... }
        db.createObjectStore('credencial_offline', { keyPath: 'usuario' });
      }
      if (!db.objectStoreNames.contains('fundos_meta')) {
        // Fundos descargados en ESTE equipo: { id, nombre, cid_rif,
        // tipo_ganaderia_id, descargado_en, ultima_sync }
        db.createObjectStore('fundos_meta', { keyPath: 'id' });
      }

      // ---- Migración desde la v9 (una sola base para todo) --------
      if (evento.oldVersion > 0 && evento.oldVersion < 10) {
        _prepararMigracionV9(db, evento.target.transaction);
      }
    };

    req.onsuccess = () => {
      const db = req.result;
      // Si venimos de la v9, ahora se vuelca lo leído a la base del
      // fundo que estaba activo (fuera de la transacción de upgrade).
      if (_migracionPendiente) {
        const pendiente = _migracionPendiente;
        _migracionPendiente = null;
        Db._volcarMigracion(pendiente)
          .then(() => resolve(db))
          .catch(() => resolve(db));
        return;
      }
      resolve(db);
    };
    req.onerror = () => reject(req.error);
  });

  return _dbGlobalPromise;
}

/** Base de datos de un fundo (crea los almacenes la primera vez). */
function abrirDbFundo(fundoId) {
  const nombre = nombreDbFundo(fundoId);
  if (_dbFundoPromesas[nombre]) return _dbFundoPromesas[nombre];

  _dbFundoPromesas[nombre] = new Promise((resolve, reject) => {
    const req = indexedDB.open(nombre, DB_VERSION_FUNDO);

    req.onupgradeneeded = (evento) => {
      const db = evento.target.result;

      const conUuid = ['animales', 'control_sanitario', 'control_sanitario_lotes', 'desincorporacion', 'movimiento_rebano', 'produccion_leche', 'pesos', 'servicios', 'nacimientos'];
      conUuid.forEach((almacen) => {
        if (!db.objectStoreNames.contains(almacen)) {
          db.createObjectStore(almacen, { keyPath: 'uuid_local' })
            .createIndex('estado_sync', 'estado_sync', { unique: false });
        }
      });

      if (!db.objectStoreNames.contains('outbox')) {
        db.createObjectStore('outbox', { keyPath: 'uuid_local' });
      }
      if (!db.objectStoreNames.contains('rangos')) {
        db.createObjectStore('rangos', { keyPath: 'modulo' });
      }
      if (!db.objectStoreNames.contains('conflictos')) {
        db.createObjectStore('conflictos', { keyPath: 'uuid_local' });
      }
      if (!db.objectStoreNames.contains('catalogos')) {
        // Una fila por catálogo: { tipo: 'razas', items: [...] }
        db.createObjectStore('catalogos', { keyPath: 'tipo' });
      }
      if (!db.objectStoreNames.contains('reportes_cache')) {
        db.createObjectStore('reportes_cache', { keyPath: 'id' });
      }
      if (!db.objectStoreNames.contains('sync_meta')) {
        db.createObjectStore('sync_meta', { keyPath: 'clave' });
      }
      if (!db.objectStoreNames.contains('fotos')) {
        // Copia local de la foto del animal para verla sin conexión:
        // { codigo, imagen, blob, descargado_en }
        db.createObjectStore('fotos', { keyPath: 'codigo' });
      }
      if (!db.objectStoreNames.contains('fotos_pendientes')) {
        // Foto tomada en el corral que aún no subió al servidor:
        // { uuid_local, data_uri, codigo, creado, intentos, ultimo_error }
        db.createObjectStore('fotos_pendientes', { keyPath: 'uuid_local' });
      }
    };

    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
  });

  return _dbFundoPromesas[nombre];
}

/**
 * Migración v9 -> v10: lee TODO lo que había en la base única y lo deja
 * en memoria para volcarlo después a la base del fundo que estaba
 * activo. Se hace así (leer en el upgrade, escribir después) porque no
 * se puede escribir en otra base dentro de la transacción de upgrade.
 */
function _prepararMigracionV9(db, tx) {
  const aMigrar = ALMACENES_DE_FUNDO.filter((a) => db.objectStoreNames.contains(a));
  const buffer = {};
  let pendientes = 0;

  const leerStore = (almacen) => {
    pendientes++;
    const req = tx.objectStore(almacen).getAll();
    req.onsuccess = () => {
      buffer[almacen] = req.result || [];
      if (--pendientes === 0) {
        finalizar();
      }
    };
    req.onerror = () => {
      buffer[almacen] = [];
      if (--pendientes === 0) {
        finalizar();
      }
    };
  };

  const finalizar = () => {
    // Se guardan los datos leídos y se quitan los almacenes de campo de
    // la base global (a partir de v10 viven en la base de cada fundo).
    let total = 0;
    Object.keys(buffer).forEach((a) => { total += buffer[a].length; });

    const reqSesion = db.objectStoreNames.contains('sesion')
      ? tx.objectStore('sesion').get('actual')
      : null;

    const terminar = (sesion) => {
      const fundoId = (sesion && (sesion.fundo_activo || sesion.fundo_id)) || 0;
      if (total > 0 && fundoId) {
        _migracionPendiente = { fundoId, buffer, sesion };
      }
      aMigrar.forEach((a) => db.deleteObjectStore(a));
    };

    if (reqSesion) {
      reqSesion.onsuccess = () => terminar(reqSesion.result || null);
      reqSesion.onerror = () => terminar(null);
    } else {
      terminar(null);
    }
  };

  if (!aMigrar.length) return;
  aMigrar.forEach(leerStore);
}

function promesaDesdeRequest(req) {
  return new Promise((resolve, reject) => {
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
  });
}

const Db = {
  async obtener(almacen, clave) {
    // Sin fundo activo (por ejemplo antes de iniciar sesión) las
    // lecturas devuelven vacío: la interfaz se pinta igual y no rompe.
    if (esAlmacenDeFundo(almacen) && !_fundoActivo) return undefined;
    const db = await abrirDb(almacen);
    const tx = db.transaction(almacen, 'readonly');
    return promesaDesdeRequest(tx.objectStore(almacen).get(clave));
  },

  async obtenerTodos(almacen) {
    if (esAlmacenDeFundo(almacen) && !_fundoActivo) return [];
    const db = await abrirDb(almacen);
    const tx = db.transaction(almacen, 'readonly');
    return promesaDesdeRequest(tx.objectStore(almacen).getAll());
  },

  async obtenerTodosPorIndice(almacen, indice, valor) {
    if (esAlmacenDeFundo(almacen) && !_fundoActivo) return [];
    const db = await abrirDb(almacen);
    const tx = db.transaction(almacen, 'readonly');
    return promesaDesdeRequest(tx.objectStore(almacen).index(indice).getAll(valor));
  },

  async guardar(almacen, objeto) {
    const db = await abrirDb(almacen);
    const tx = db.transaction(almacen, 'readwrite');
    await promesaDesdeRequest(tx.objectStore(almacen).put(objeto));
  },

  async obtenerSyncMeta(clave) {
    const fila = await this.obtener('sync_meta', clave);
    return fila ? fila.valor : null;
  },

  async guardarSyncMeta(clave, valor) {
    await this.guardar('sync_meta', { clave, valor });
  },

  async guardarVarios(almacen, objetos) {
    if (!objetos.length) return;
    const db = await abrirDb(almacen);
    const tx = db.transaction(almacen, 'readwrite');
    const store = tx.objectStore(almacen);
    for (const obj of objetos) store.put(obj);
    await new Promise((resolve, reject) => {
      tx.oncomplete = resolve;
      tx.onerror = () => reject(tx.error);
    });
  },

  async eliminar(almacen, clave) {
    const db = await abrirDb(almacen);
    const tx = db.transaction(almacen, 'readwrite');
    await promesaDesdeRequest(tx.objectStore(almacen).delete(clave));
  },

  async vaciar(almacen) {
    const db = await abrirDb(almacen);
    const tx = db.transaction(almacen, 'readwrite');
    await promesaDesdeRequest(tx.objectStore(almacen).clear());
  },

  // ---- Ayudas específicas de sesión --------------------------------

  async obtenerSesion() {
    return (await this.obtener('sesion', 'actual')) || null;
  },

  async guardarSesion(datos) {
    await this.guardar('sesion', { clave: 'actual', ...datos });
  },

  // ---- Fundos descargados en este dispositivo (multifundo) ----------

  /** Fundo activo: el que se está viendo/registrando ahora mismo. */
  fundoActivo() {
    return _fundoActivo;
  },

  /**
   * Fija el fundo activo. A partir de aquí TODA lectura y escritura de
   * datos de campo (animales, pesos, servicios, reportes, pendientes,
   * rangos, catálogos…) corresponde a ese fundo, porque cada fundo tiene
   * su propia base de datos.
   */
  usarFundo(fundoId) {
    _fundoActivo = parseInt(fundoId, 10) || 0;
    return _fundoActivo;
  },

  /** Fundos descargados en este equipo, ordenados por nombre (selector). */
  async listarFundosMeta() {
    const filas = await this.obtenerTodos('fundos_meta');
    return filas.sort((a, b) => String(a.nombre).localeCompare(String(b.nombre)));
  },

  /**
   * Deja un fundo listo para trabajar sin conexión: crea su base de
   * datos y lo anota en fundos_meta.
   */
  async descargarFundo(fundo) {
    const id = parseInt(fundo.id, 10) || 0;
    if (!id) return null;

    await abrirDbFundo(id); // crea la base y sus almacenes la primera vez

    const fila = {
      id,
      nombre: fundo.nombre || ('Fundo ' + id),
      cid_rif: fundo.cid_rif || '',
      tipo_ganaderia_id: parseInt(fundo.tipo_ganaderia_id, 10) || 0,
      descargado_en: fundo.descargado_en || new Date().toISOString(),
      ultima_sync: fundo.ultima_sync || null,
    };

    await this.guardar('fundos_meta', fila);
    return fila;
  },

  /**
   * Quita un fundo de este equipo: borra SU base de datos completa
   * (animales, reportes, pendientes…). Es la única forma de liberar
   * espacio de un fundo que ya no se usa aquí.
   */
  async borrarFundo(fundoId) {
    const id = parseInt(fundoId, 10) || 0;
    if (!id) return;

    const nombre = nombreDbFundo(id);
    await this.eliminar('fundos_meta', id);

    const db = await abrirDbFundo(id).catch(() => null);
    if (db) {
      try { db.close(); } catch (e) { /* ya cerrada */ }
    }
    delete _dbFundoPromesas[nombre];

    await new Promise((resolve) => {
      const req = indexedDB.deleteDatabase(nombre);
      req.onsuccess = req.onerror = req.onblocked = () => resolve();
    });
  },

  /**
   * Fundos que tienen cambios sin subir al servidor. Necesario antes de
   * cerrar sesión o quitar un fundo del equipo: cerrar sesión nunca
   * borra datos, pero "olvidar el equipo" o "quitar fundo" SÍ.
   */
  async fundosConPendientes() {
    const fundos = await this.listarFundosMeta();
    const activoPrevio = _fundoActivo;
    const resultado = [];

    for (const fundo of fundos) {
      this.usarFundo(fundo.id);
      const pendientes = await this.obtenerTodos('outbox').catch(() => []);
      if (pendientes.length) {
        resultado.push({ fundo, pendientes: pendientes.length });
      }
    }

    this.usarFundo(activoPrevio);
    return resultado;
  },

  /**
   * Vuelca a la base del fundo lo que se leyó de la base única de la v9
   * (migración multifundo). A cada fila se le marca su fundo: los
   * registros creados sin conexión no lo traían.
   */
  async _volcarMigracion({ fundoId, buffer, sesion }) {
    await this.descargarFundo({
      id: fundoId,
      nombre: (sesion && (sesion.fundo_nombre || (sesion.usuario && sesion.usuario.fundo_nombre)))
        || ('Fundo ' + fundoId),
      cid_rif: '',
      tipo_ganaderia_id: (sesion && sesion.usuario && sesion.usuario.tipo_ganaderia_id) || 0,
    });

    const activoPrevio = _fundoActivo;
    this.usarFundo(fundoId);

    for (const almacen of Object.keys(buffer)) {
      const filas = buffer[almacen] || [];
      if (!filas.length) continue;
      const conFundo = filas.map((fila) => ({
        ...fila,
        mfundos_id: fila.mfundos_id || fundoId,
      }));
      await this.guardarVarios(almacen, conFundo);
    }

    this.usarFundo(activoPrevio);
  },

  async cerrarSesion() {
    // OJO: a propósito NO borra ningún dato local — ni el caché
    // (animales, control_sanitario, etc.) ni, sobre todo, 'outbox'
    // (cambios pendientes de sincronizar). Cerrar sesión —incluso de
    // forma automática, cuando el servidor rechaza el token o cuando
    // venció el plazo de reconexión local (ver app.js)— nunca debe ser
    // la forma en que alguien pierde trabajo de campo sin sincronizar.
    // Tampoco toca 'credencial_offline': si el equipo tiene modo
    // offline activado, la persona debe poder reconectarse localmente
    // después sin tener que "activarlo" de nuevo, viendo exactamente
    // los mismos datos que dejó.
    //
    // El único lugar donde tiene sentido borrar todo de verdad es
    // "Olvidar este equipo" (ver Db.olvidarEquipoYDatos) — esa es la
    // acción explícita para cuando el dispositivo deja de ser de
    // confianza para este usuario/fundo.
    await this.eliminar('sesion', 'actual');
  },

  /** true si queda algo grabado localmente que todavía no llegó al servidor. */
  async hayPendientesSinSincronizar() {
    const conPendientes = await this.fundosConPendientes();
    return conPendientes.length > 0;
  },

  /**
   * Igual que hayPendientesSinSincronizar(), pero SIN mover el fundo
   * activo: se puede llamar en cualquier momento (por ejemplo al cerrar
   * la app) sin riesgo de que una escritura en curso termine en la base
   * de otro fundo.
   */
  async hayPendientesEnAlgunFundo() {
    const fundos = await this.listarFundosMeta().catch(() => []);
    for (const fundo of fundos) {
      try {
        const db = await abrirDbFundo(fundo.id);
        const tx = db.transaction(['outbox', 'conflictos'], 'readonly');
        const pendientes = await promesaDesdeRequest(tx.objectStore('outbox').count());
        if (pendientes > 0) return true;
        const conflictos = await promesaDesdeRequest(tx.objectStore('conflictos').count());
        if (conflictos > 0) return true;
      } catch (e) {
        // Si un fundo no se puede leer, se ignora: no conviene bloquear
        // la salida por un problema de lectura.
      }
    }
    return false;
  },

  /**
   * "Olvidar este equipo" de verdad: borra la credencial local Y todo
   * el caché de datos, incluido lo pendiente de sincronizar. A
   * diferencia de cerrarSesion(), esto SÍ es destructivo — solo debe
   * llamarse tras una confirmación explícita de la persona, avisando
   * primero si hay cambios sin sincronizar (ver manejarOlvidarEquipo
   * en app.js, que ya hace esa comprobación).
   */
  async olvidarEquipoYDatos(usuario) {
    await this.olvidarEquipoOffline(usuario);

    // Multifundo: cada fundo tiene su propia base de datos; "olvidar el
    // equipo" las borra TODAS (incluidos los cambios sin sincronizar).
    const fundos = await this.listarFundosMeta().catch(() => []);
    for (const fundo of fundos) {
      await this.borrarFundo(fundo.id).catch(() => {});
    }

    this.usarFundo(0);
    await this.eliminar('sesion', 'actual');
    await this.vaciar('fundos_meta').catch(() => {});
  },

  // ---- Catálogos (razas, aptitudes, lotes) ---------------------------

  async obtenerCatalogo(tipo) {
    const fila = await this.obtener('catalogos', tipo);
    return fila ? fila.items : [];
  },

  // ---- Credencial local (reconexión sin conexión) --------------------

  async guardarCredencialOffline(fila) {
    await this.guardar('credencial_offline', fila);
  },

  async obtenerCredencialOffline(usuario) {
    return (await this.obtener('credencial_offline', usuario)) || null;
  },

  /**
   * "Olvidar este equipo": a diferencia de cerrarSesion(), esto SÍ
   * borra la credencial local. Es la acción a usar cuando el equipo
   * deja de ser de confianza (se presta, se vende, se pierde) o
   * cuando la persona ya no quiere que este dispositivo pueda
   * reconectarse sin conexión.
   */
  async olvidarEquipoOffline(usuario) {
    await this.eliminar('credencial_offline', usuario);
  },

  /**
   * Cualquier credencial offline activada en este equipo (para decidir
   * qué pantalla mostrar al abrir la app sin sesión activa, sin tener
   * que adivinar de antemano qué usuario fue).
   */
  async algunaCredencialOffline() {
    const todas = await this.obtenerTodos('credencial_offline');
    return todas[0] || null;
  },

  // ---- Rango de códigos reservados ----------------------------------
  // Reserva "atómica" a nivel de este dispositivo: usa una única
  // transacción de lectura+escritura, así que si la interfaz llegara a
  // disparar dos registros casi al mismo tiempo (dos pestañas, doble
  // tap) igual nunca entrega el mismo número dos veces.

  async tomarSiguienteCodigo(modulo, prefijoFundoId, prefijoProvisional) {
    const db = await abrirDb('rangos');
    const tx = db.transaction('rangos', 'readwrite');
    const store = tx.objectStore('rangos');
    const rango = await promesaDesdeRequest(store.get(modulo));

    if (!rango || rango.actual > rango.hasta) {
      // No hay rango reservado (o se agotó): código provisional.
      // Se resuelve a número definitivo en la próxima sincronización.
      const provisional = prefijoProvisional + '-0' + prefijoFundoId + '-PEND-' + Math.random().toString(16).slice(2, 8);
      return { codigo: provisional, provisional: true };
    }

    const numero = rango.actual;
    rango.actual = numero + 1;
    store.put(rango);

    await new Promise((resolve, reject) => {
      tx.oncomplete = resolve;
      tx.onerror = () => reject(tx.error);
    });

    const codigo = rango.prefijo_codigo + String(numero).padStart(2, '0');
    return { codigo, provisional: false, quedanEnRango: rango.hasta - rango.actual + 1 };
  },
};