// Lógica del módulo "Control sanitario por lotes": un control se aplica
// a TODOS los animales de un lote, con los productos utilizados. Es el
// equivalente offline de views/controlsanitariomasivo.
//
// El código del control (RCS-0<fundo>-0<n>) lo asigna el SERVIDOR al
// sincronizar: la web usa un correlativo por conteo que no se puede
// reservar desde el dispositivo, así que acá queda vacío hasta que se
// sube (se muestra "Pendiente").

const ControlSanitarioLotes = {
  async listar() {
    const todos = await Db.obtenerTodos('control_sanitario_lotes');
    return todos.sort((a, b) => {
      if (a.estado_sync !== b.estado_sync) {
        const prioridad = { conflicto: 0, error: 1, pendiente: 2, sincronizado: 3 };
        return (prioridad[a.estado_sync] ?? 9) - (prioridad[b.estado_sync] ?? 9);
      }
      return String(b.fecha_control || '').localeCompare(String(a.fecha_control || ''));
    });
  },

  async crear(sesion, datosFormulario) {
    const uuid = generarUUID();
    const ahora = new Date().toISOString();

    const registro = {
      uuid_local: uuid,
      id: null,
      // Lo completa el servidor (RCS-0<fundo>-0<n>).
      codigo_mcs_lote: '',
      mlotes_id: Number(datosFormulario.mlotes_id),
      lote_nombre: datosFormulario.lote_nombre || '',
      fecha_control: datosFormulario.fecha_control,
      fecha_prox_control: datosFormulario.fecha_prox_control || null,
      evento: datosFormulario.evento || '',
      responsable: datosFormulario.responsable || '',
      observaciones: datosFormulario.observaciones || '',
      productos_aplicados: normalizarProductosLote(datosFormulario.productos_aplicados),
      animales: normalizarAnimalesLote(datosFormulario.animales),
      total_animales: normalizarAnimalesLote(datosFormulario.animales).length,
      version: 0,
      estado_sync: 'pendiente',
      created: ahora,
      updated: null,
    };

    await Db.guardar('control_sanitario_lotes', registro);
    await Db.guardar('outbox', {
      uuid_local: uuid,
      modulo: 'control_sanitario_lotes',
      version_base: null,
      datos: datosParaEnviar(registro),
      intentos: 0,
      creado: ahora,
    });

    if (navigator.onLine) Sync.sincronizar();

    return registro;
  },

  /**
   * Editar un control por lote: el lote y la fecha son la identidad del
   * registro; se pueden ajustar evento, responsable, observaciones,
   * próximo control, productos y animales incluidos.
   */
  async editar(sesion, uuidLocal, datosFormulario) {
    const registroLocal = await Db.obtener('control_sanitario_lotes', uuidLocal);
    if (!registroLocal) {
      throw new Error('No se encontro ese control sanitario en este dispositivo.');
    }
    if (registroLocal.estado_sync === 'conflicto') {
      throw new Error('Este control tiene un conflicto sin resolver.');
    }

    registroLocal.fecha_prox_control = datosFormulario.fecha_prox_control || null;
    registroLocal.evento = datosFormulario.evento || '';
    registroLocal.responsable = datosFormulario.responsable || '';
    registroLocal.observaciones = datosFormulario.observaciones || '';
    registroLocal.productos_aplicados = normalizarProductosLote(datosFormulario.productos_aplicados);
    registroLocal.animales = normalizarAnimalesLote(datosFormulario.animales);
    registroLocal.total_animales = registroLocal.animales.length;
    registroLocal.estado_sync = 'pendiente';
    await Db.guardar('control_sanitario_lotes', registroLocal);

    const pendienteExistente = await Db.obtener('outbox', uuidLocal);
    const versionBase = pendienteExistente
      ? pendienteExistente.version_base
      : (registroLocal.version ?? null);
    const datosAEnviar = pendienteExistente && pendienteExistente.version_base === null
      ? datosParaEnviar(registroLocal)
      : {
          fecha_prox_control: registroLocal.fecha_prox_control,
          evento: registroLocal.evento,
          responsable: registroLocal.responsable,
          observaciones: registroLocal.observaciones,
          productos_aplicados: registroLocal.productos_aplicados,
          animales: registroLocal.animales,
        };

    await Db.guardar('outbox', {
      uuid_local: uuidLocal,
      modulo: 'control_sanitario_lotes',
      version_base: versionBase,
      datos: datosAEnviar,
      intentos: 0,
      creado: new Date().toISOString(),
    });

    if (navigator.onLine) Sync.sincronizar();

    return registroLocal;
  },
};

/** Productos aplicados: siempre como lista de objetos válidos. */
function normalizarProductosLote(productos) {
  if (!Array.isArray(productos)) return [];
  return productos
    .filter((producto) => producto && Number(producto.mproductos_id) > 0)
    .map((producto) => ({
      mproductos_id: Number(producto.mproductos_id),
      producto_nombre: producto.producto_nombre || producto.nombre || '',
      dosis: (producto.dosis || '').toString().trim(),
      detalles: (producto.detalles || '').toString().trim(),
    }));
}

/** Animales del control: lista de códigos únicos. */
function normalizarAnimalesLote(animales) {
  if (!Array.isArray(animales)) return [];
  const vistos = new Set();
  const resultado = [];
  animales.forEach((animal) => {
    const codigo = (typeof animal === 'string' ? animal : (animal && animal.codigo_rgb_id_animal) || '')
      .toString().trim();
    if (!codigo || vistos.has(codigo)) return;
    vistos.add(codigo);
    resultado.push(codigo);
  });
  return resultado;
}
