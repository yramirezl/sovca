// Lógica específica del módulo "Pesos": registro de pesajes por
// animal y fecha, con edición disponible para corregir datos.

const Pesos = {
  async listar() {
    const todos = await Db.obtenerTodos('pesos');
    return todos.sort((a, b) => {
      if (a.estado_sync !== b.estado_sync) {
        const prioridad = { conflicto: 0, error: 1, pendiente: 2, sincronizado: 3 };
        return (prioridad[a.estado_sync] ?? 9) - (prioridad[b.estado_sync] ?? 9);
      }
      return (b.fecha_pesaje || '').localeCompare(a.fecha_pesaje || '');
    });
  },

  async crear(sesion, datosFormulario, opciones = {}) {
    const uuid = generarUUID();
    const ahora = new Date().toISOString();
    const hoy = ahora.slice(0, 10);

    const registro = {
      uuid_local: uuid,
      id: null,
      codigo_rgb_id_animal: datosFormulario.codigo_rgb_id_animal,
      fecha_pesaje: datosFormulario.fecha_pesaje || hoy,
      peso: Number(datosFormulario.peso || 0),
      version: 0,
      estado_sync: 'pendiente',
      created: ahora,
      updated: null,
    };

    await Db.guardar('pesos', registro);
    await Db.guardar('outbox', {
      uuid_local: uuid,
      modulo: 'pesos',
      version_base: null, // siempre creación, nunca edición
      datos: datosParaEnviar(registro),
      intentos: 0,
      creado: ahora,
    });

    // En el pesaje por lote se registran muchos animales seguidos: ahí
    // se difiere la sincronización hasta terminar (opciones.sincronizar).
    if (opciones.sincronizar !== false && navigator.onLine) Sync.sincronizar();

    return registro;
  },

  async editar(sesion, uuidLocal, datosFormulario) {
    const registroLocal = await Db.obtener('pesos', uuidLocal);
    if (!registroLocal) {
      throw new Error('No se encontro ese pesaje en este dispositivo.');
    }
    if (registroLocal.estado_sync === 'conflicto') {
      throw new Error('Este pesaje tiene un conflicto sin resolver.');
    }

    // El animal NO se puede cambiar al editar — es la identidad del
    // registro.
    registroLocal.fecha_pesaje = datosFormulario.fecha_pesaje || registroLocal.fecha_pesaje;
    registroLocal.peso = Number(datosFormulario.peso || 0);
    registroLocal.estado_sync = 'pendiente';
    await Db.guardar('pesos', registroLocal);

    const pendienteExistente = await Db.obtener('outbox', uuidLocal);
    const versionBase = pendienteExistente
      ? pendienteExistente.version_base
      : (registroLocal.version ?? null);

    await Db.guardar('outbox', {
      uuid_local: uuidLocal,
      modulo: 'pesos',
      version_base: versionBase,
      datos: {
        fecha_pesaje: registroLocal.fecha_pesaje,
        peso: registroLocal.peso,
      },
      intentos: 0,
      creado: new Date().toISOString(),
    });

    if (navigator.onLine) Sync.sincronizar();

    return registroLocal;
  },
};
