// Lógica del módulo "Movimiento de rebaño".
//
// Equivalente offline de views/movreb: se elige el lote y/o potrero de
// ORIGEN, se seleccionan los animales que están ahí y se los mueve a un
// lote y/o potrero de DESTINO. El servidor guarda el detalle (lote,
// potrero y categoría antes y después de cada animal) y actualiza al
// animal; acá se hace lo mismo con la copia local para que las listas
// queden al día sin esperar conexión.

const MovimientoRebano = {
  // AMBOS = cambia de lote y de potrero en el mismo movimiento.
  TIPOS: [
    { id: 'LOTE', nombre: 'Cambio de lote' },
    { id: 'POTRERO', nombre: 'Cambio de potrero' },
    { id: 'AMBOS', nombre: 'Lote y potrero' },
  ],

  nombreTipo(tipo) {
    const encontrado = this.TIPOS.find((item) => item.id === String(tipo || '').toUpperCase());
    return encontrado ? encontrado.nombre : '';
  },

  /**
   * Un movimiento se puede deshacer mientras no haya sido revertido:
   * - pendiente o con error de envío  → se borra en el equipo;
   * - ya sincronizado                → se pide la reversión al servidor.
   */
  puedeRevertir(registro) {
    if (!registro) return false;
    if (registro.revertido === true) return false;
    return ['pendiente', 'error', 'sincronizado'].includes(String(registro.estado_sync || 'pendiente'));
  },

  /** ¿Este movimiento ya está confirmado por el servidor? */
  estaEnServidor(registro) {
    if (!registro) return false;
    // Basta con tener id del servidor: aunque el estado local sea
    // "pendiente" (esperando una reversión), el movimiento existe allá y
    // borrarlo acá dejaría el servidor desincronizado.
    return Number(registro.id) > 0 && registro.revertido !== true;
  },

  /**
   * Revierte un movimiento que todavía no se sincronizó: devuelve los
   * animales a su lote/potrero de origen, lo saca de la cola de envío y
   * borra el registro local. No queda rastro en el servidor porque el
   * movimiento nunca llegó.
   */
  async revertir(sesion, uuidLocal) {
    const registro = await Db.obtener('movimiento_rebano', uuidLocal);
    if (!registro) {
      throw new Error('No se encontro ese movimiento en este dispositivo.');
    }
    if (!this.puedeRevertir(registro)) {
      throw new Error('Este movimiento ya fue revertido.');
    }
    if (this.estaEnServidor(registro)) {
      throw new Error('Este movimiento ya esta en el servidor: usa la reversion con motivo.');
    }

    await revertirMovimientoLocal(registro);
    await Db.eliminar('outbox', uuidLocal);
    await Db.eliminar('movimiento_rebano', uuidLocal);

    return registro;
  },

  /**
   * Pide al servidor que revierta un movimiento ya sincronizado. Se
   * encola como un cambio más, así aprovecha los reintentos, el control
   * de versión y la detección de conflictos del resto de la app.
   */
  async revertirEnServidor(sesion, uuidLocal, motivo) {
    const registro = await Db.obtener('movimiento_rebano', uuidLocal);
    if (!registro) {
      throw new Error('No se encontro ese movimiento en este dispositivo.');
    }
    if (registro.revertido === true) {
      throw new Error('Este movimiento ya fue revertido.');
    }
    const texto = String(motivo || '').trim();
    if (texto.length < 10) {
      throw new Error('El motivo de la reversion debe tener al menos 10 caracteres.');
    }

    const pendiente = await Db.obtener('outbox', uuidLocal);
    if (pendiente && pendiente.revertir === true) {
      throw new Error('Ya hay una reversion pendiente de enviar para este movimiento.');
    }

    const versionBase = pendiente ? pendiente.version_base : (registro.version ?? null);

    registro.estado_sync = 'pendiente';
    registro.motivo_reversion = texto;
    delete registro.ultimo_error;
    await Db.guardar('movimiento_rebano', registro);

    await Db.guardar('outbox', {
      uuid_local: uuidLocal,
      modulo: 'movimiento_rebano',
      version_base: versionBase,
      datos: {},
      revertir: true,
      motivo_reversion: texto,
      intentos: 0,
      creado: new Date().toISOString(),
    });

    if (navigator.onLine) Sync.sincronizar();

    return registro;
  },

  async listar() {
    const todos = await Db.obtenerTodos('movimiento_rebano');
    return todos.sort((a, b) => {
      if (a.estado_sync !== b.estado_sync) {
        const prioridad = { conflicto: 0, error: 1, pendiente: 2, sincronizado: 3 };
        return (prioridad[a.estado_sync] ?? 9) - (prioridad[b.estado_sync] ?? 9);
      }
      return String(b.fecha_movimiento || '').localeCompare(String(a.fecha_movimiento || ''));
    });
  },

  async crear(sesion, datosFormulario) {
    const tipo = String(datosFormulario.tipo_movimiento || '').toUpperCase();
    if (!this.TIPOS.some((item) => item.id === tipo)) {
      throw new Error('Elegí el tipo de movimiento.');
    }

    const animales = normalizarAnimalesMovimiento(datosFormulario.animales);
    if (!animales.length) {
      throw new Error('Seleccioná al menos un animal para mover.');
    }

    if (!datosFormulario.motivo || !String(datosFormulario.motivo).trim()) {
      throw new Error('Indicá el motivo del movimiento.');
    }

    const uuid = generarUUID();
    const ahora = new Date().toISOString();

    const registro = {
      uuid_local: uuid,
      id: null,
      tipo_movimiento: tipo,
      mlotes_origen_id: numeroONull(datosFormulario.mlotes_origen_id),
      mlotes_destino_id: numeroONull(datosFormulario.mlotes_destino_id),
      mpotreros_origen_id: numeroONull(datosFormulario.mpotreros_origen_id),
      mpotreros_destino_id: numeroONull(datosFormulario.mpotreros_destino_id),
      lote_origen_nombre: datosFormulario.lote_origen_nombre || '',
      lote_destino_nombre: datosFormulario.lote_destino_nombre || '',
      potrero_origen_nombre: datosFormulario.potrero_origen_nombre || '',
      potrero_destino_nombre: datosFormulario.potrero_destino_nombre || '',
      fecha_movimiento: datosFormulario.fecha_movimiento || ahora,
      motivo: String(datosFormulario.motivo).trim(),
      observaciones: (datosFormulario.observaciones || '').toString().trim(),
      animales: animales,
      total_animales: animales.length,
      version: 0,
      estado_sync: 'pendiente',
      created: ahora,
      updated: null,
    };

    await Db.guardar('movimiento_rebano', registro);
    await Db.guardar('outbox', {
      uuid_local: uuid,
      modulo: 'movimiento_rebano',
      version_base: null,
      datos: datosParaEnviar({
        tipo_movimiento: registro.tipo_movimiento,
        mlotes_origen_id: registro.mlotes_origen_id,
        mlotes_destino_id: registro.mlotes_destino_id,
        mpotreros_origen_id: registro.mpotreros_origen_id,
        mpotreros_destino_id: registro.mpotreros_destino_id,
        fecha_movimiento: registro.fecha_movimiento,
        motivo: registro.motivo,
        observaciones: registro.observaciones,
        animales: registro.animales,
      }),
      intentos: 0,
      creado: ahora,
    });

    await aplicarMovimientoLocal(registro);

    if (navigator.onLine) Sync.sincronizar();

    return registro;
  },
};

function numeroONull(valor) {
  const numero = Number(valor);
  return Number.isFinite(numero) && numero > 0 ? numero : null;
}

/** Códigos únicos y en mayúsculas. */
function normalizarAnimalesMovimiento(animales) {
  if (!Array.isArray(animales)) return [];
  const vistos = new Set();
  const resultado = [];
  animales.forEach((animal) => {
    const codigo = (typeof animal === 'string'
      ? animal
      : (animal && animal.codigo_rgb_id_animal) || '').toString().trim().toUpperCase();
    if (!codigo || vistos.has(codigo)) return;
    vistos.add(codigo);
    resultado.push(codigo);
  });
  return resultado;
}

/**
 * Refleja el movimiento en la copia local del animal (lote y potrero).
 * El servidor hace exactamente lo mismo al recibir el movimiento.
 */
async function aplicarMovimientoLocal(registro) {
  const usaLote = ['LOTE', 'AMBOS'].includes(registro.tipo_movimiento);
  const usaPotrero = ['POTRERO', 'AMBOS'].includes(registro.tipo_movimiento);
  const animales = await Db.obtenerTodos('animales');
  const codigos = new Set((registro.animales || []).map((codigo) => String(codigo).toUpperCase()));

  for (const animal of animales) {
    if (!codigos.has(String(animal.codigo_rgb_id_animal || '').toUpperCase())) continue;

    if (usaLote && registro.mlotes_destino_id) {
      animal.mlotes_id = Number(registro.mlotes_destino_id);
    }
    if (usaPotrero) {
      animal.mpotreros_id = registro.mpotreros_destino_id
        ? Number(registro.mpotreros_destino_id)
        : null;
    }
    await Db.guardar('animales', animal);
  }
}

/**
 * Deshace el movimiento en la copia local: los animales movidos vuelven
 * al lote y/o potrero de ORIGEN que quedó guardado en el movimiento.
 */
async function revertirMovimientoLocal(registro) {
  const usaLote = ['LOTE', 'AMBOS'].includes(registro.tipo_movimiento);
  const usaPotrero = ['POTRERO', 'AMBOS'].includes(registro.tipo_movimiento);

  const loteOrigen = registro.mlotes_origen_id ? Number(registro.mlotes_origen_id) : null;
  const potreroOrigen = registro.mpotreros_origen_id ? Number(registro.mpotreros_origen_id) : null;

  const animales = await Db.obtenerTodos('animales');
  const codigos = new Set((registro.animales || []).map((codigo) => String(codigo).toUpperCase()));

  for (const animal of animales) {
    if (!codigos.has(String(animal.codigo_rgb_id_animal || '').toUpperCase())) continue;

    if (usaLote) animal.mlotes_id = loteOrigen;
    if (usaPotrero) animal.mpotreros_id = potreroOrigen;

    await Db.guardar('animales', animal);
  }

  return codigos.size;
}
