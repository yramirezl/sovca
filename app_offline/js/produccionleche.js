// Lógica específica del módulo "Producción de Leche": registro de
// litros ordeñados por animal y fecha, con edición disponible para
// corregir datos cargados a mano si hace falta.

const ProduccionLeche = {
  async listar() {
    const todos = await Db.obtenerTodos('produccion_leche');
    return todos.sort((a, b) => {
      if (a.estado_sync !== b.estado_sync) {
        const prioridad = { conflicto: 0, error: 1, pendiente: 2, sincronizado: 3 };
        return (prioridad[a.estado_sync] ?? 9) - (prioridad[b.estado_sync] ?? 9);
      }
      return (b.fecha_produccion || '').localeCompare(a.fecha_produccion || '');
    });
  },

  async crear(sesion, datosFormulario) {
    const uuid = generarUUID();
    const ahora = new Date().toISOString();
    const hoy = ahora.slice(0, 10);

    const registro = {
      uuid_local: uuid,
      id: null,
      codigo_rgb_id_animal_hembra: datosFormulario.codigo_rgb_id_animal_hembra,
      fecha_produccion: datosFormulario.fecha_produccion || hoy,
      cantidad_leche_m: Number(datosFormulario.cantidad_leche_m || 0),
      cantidad_leche_t: Number(datosFormulario.cantidad_leche_t || 0),
      version: 0,
      estado_sync: 'pendiente',
      created: ahora,
      updated: null,
    };

    await Db.guardar('produccion_leche', registro);
    await Db.guardar('outbox', {
      uuid_local: uuid,
      modulo: 'produccion_leche',
      version_base: null, // siempre creación, nunca edición
      datos: datosParaEnviar(registro),
      intentos: 0,
      creado: ahora,
    });

    if (navigator.onLine) Sync.sincronizar();

    return registro;
  },

  async editar(sesion, uuidLocal, datosFormulario) {
    const registroLocal = await Db.obtener('produccion_leche', uuidLocal);
    if (!registroLocal) {
      throw new Error('No se encontro ese registro de produccion en este dispositivo.');
    }
    if (registroLocal.estado_sync === 'conflicto') {
      throw new Error('Este registro de produccion tiene un conflicto sin resolver.');
    }

    // El animal (hembra) NO se puede cambiar al editar — es la
    // identidad del registro.
    registroLocal.fecha_produccion = datosFormulario.fecha_produccion || registroLocal.fecha_produccion;
    registroLocal.cantidad_leche_m = Number(datosFormulario.cantidad_leche_m || 0);
    registroLocal.cantidad_leche_t = Number(datosFormulario.cantidad_leche_t || 0);
    registroLocal.estado_sync = 'pendiente';
    await Db.guardar('produccion_leche', registroLocal);

    const pendienteExistente = await Db.obtener('outbox', uuidLocal);
    const versionBase = pendienteExistente
      ? pendienteExistente.version_base
      : (registroLocal.version ?? null);

    await Db.guardar('outbox', {
      uuid_local: uuidLocal,
      modulo: 'produccion_leche',
      version_base: versionBase,
      datos: {
        fecha_produccion: registroLocal.fecha_produccion,
        cantidad_leche_m: registroLocal.cantidad_leche_m,
        cantidad_leche_t: registroLocal.cantidad_leche_t,
      },
      intentos: 0,
      creado: new Date().toISOString(),
    });

    if (navigator.onLine) Sync.sincronizar();

    return registroLocal;
  },
};
