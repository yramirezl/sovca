// Lógica específica del módulo "Control Sanitario": registro de
// eventos (vacunas, tratamientos) por animal, con edición disponible
// para corregir datos cargados a mano si hace falta.

const ControlSanitario = {
  async listar() {
    const todos = await Db.obtenerTodos('control_sanitario');
    return todos.sort((a, b) => {
      if (a.estado_sync !== b.estado_sync) {
        const prioridad = { conflicto: 0, error: 1, pendiente: 2, sincronizado: 3 };
        return (prioridad[a.estado_sync] ?? 9) - (prioridad[b.estado_sync] ?? 9);
      }
      return (b.fecha_control || '').localeCompare(a.fecha_control || '');
    });
  },

  async crear(sesion, datosFormulario) {
    const uuid = generarUUID();
    const ahora = new Date().toISOString();
    const hoy = ahora.slice(0, 10);

    const registro = {
      uuid_local: uuid,
      id: null,
      codigo_rgb_id_animal: datosFormulario.codigo_rgb_id_animal,
      fecha_control: datosFormulario.fecha_control || hoy,
      evento: datosFormulario.evento,
      responsable: datosFormulario.responsable,
      fecha_prox_control: datosFormulario.fecha_prox_control || null,
      observaciones: datosFormulario.observaciones || '',
      productos_aplicados: copiarProductosAplicados(datosFormulario.productos_aplicados),
      version: 0,
      estado_sync: 'pendiente',
      created: ahora,
      updated: null,
    };

    await Db.guardar('control_sanitario', registro);
    await Db.guardar('outbox', {
      uuid_local: uuid,
      modulo: 'control_sanitario',
      version_base: null, // los eventos siempre son creaciones, nunca ediciones
      datos: datosParaEnviar(registro),
      intentos: 0,
      creado: ahora,
    });

    if (navigator.onLine) Sync.sincronizar();

    return registro;
  },

  async editar(sesion, uuidLocal, datosFormulario) {
    const registroLocal = await Db.obtener('control_sanitario', uuidLocal);
    if (!registroLocal) {
      throw new Error('No se encontro ese control sanitario en este dispositivo.');
    }
    if (registroLocal.estado_sync === 'conflicto') {
      throw new Error('Este control sanitario tiene un conflicto sin resolver.');
    }

    // El animal NO se puede cambiar al editar — es la identidad del
    // registro (a diferencia de la fecha, el evento, etc., que sí son
    // editables). Por eso codigo_rgb_id_animal ni se lee de
    // datosFormulario ni viaja en el payload de la edición.
    registroLocal.fecha_control = datosFormulario.fecha_control || registroLocal.fecha_control;
    registroLocal.fecha_prox_control = datosFormulario.fecha_prox_control || null;
    registroLocal.evento = datosFormulario.evento;
    registroLocal.responsable = datosFormulario.responsable || '';
    registroLocal.observaciones = datosFormulario.observaciones || '';
    registroLocal.productos_aplicados = copiarProductosAplicados(datosFormulario.productos_aplicados);
    registroLocal.estado_sync = 'pendiente';
    await Db.guardar('control_sanitario', registroLocal);

    const pendienteExistente = await Db.obtener('outbox', uuidLocal);
    const versionBase = pendienteExistente
      ? pendienteExistente.version_base
      : (registroLocal.version ?? null);

    await Db.guardar('outbox', {
      uuid_local: uuidLocal,
      modulo: 'control_sanitario',
      version_base: versionBase,
      // Solo los campos que este formulario de edición controla — NO
      // todo el registro (ver comentario arriba sobre el animal).
      datos: {
        fecha_control: registroLocal.fecha_control,
        fecha_prox_control: registroLocal.fecha_prox_control,
        evento: registroLocal.evento,
        responsable: registroLocal.responsable,
        observaciones: registroLocal.observaciones,
        productos_aplicados: registroLocal.productos_aplicados,
      },
      intentos: 0,
      creado: new Date().toISOString(),
    });

    if (navigator.onLine) Sync.sincronizar();

    return registroLocal;
  },
};

function copiarProductosAplicados(productos) {
  if (!Array.isArray(productos)) return [];
  return productos.map((producto) => ({
    mproductos_id: Number(producto.mproductos_id),
    producto_nombre: String(producto.producto_nombre || ''),
    dosis: String(producto.dosis || ''),
    detalles: String(producto.detalles || ''),
  }));
}
