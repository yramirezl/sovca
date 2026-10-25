// Lógica específica del módulo "Servicios" (reproducción).
// Permite crear y editar registros localmente; los cambios se encolan
// en outbox para sincronizar cuando haya conexión.

const Servicios = {
  async listar() {
    const todos = await Db.obtenerTodos('servicios');
    return todos.sort((a, b) => {
      if (a.estado_sync !== b.estado_sync) {
        const prioridad = { conflicto: 0, error: 1, pendiente: 2, sincronizado: 3 };
        return (prioridad[a.estado_sync] ?? 9) - (prioridad[b.estado_sync] ?? 9);
      }
      return (b.f_servicio || '').localeCompare(a.f_servicio || '');
    });
  },

  async crear(sesion, datosFormulario) {
    const uuid = generarUUID();
    const ahora = new Date().toISOString();
    const hoy = ahora.slice(0, 10);

    const registro = {
      uuid_local: uuid,
      id: null,
      codigo_servicio: 'RSA-PEND-' + Date.now(),
      codigo_rgb_id_animal_hembra: datosFormulario.codigo_rgb_id_animal_hembra,
      codigo_rgb_id_animal_macho: datosFormulario.codigo_rgb_id_animal_macho,
      codigo_rgb_id_animal_dona: datosFormulario.codigo_rgb_id_animal_dona || '',
      f_servicio: datosFormulario.f_servicio || hoy,
      f_probable_palpacion: datosFormulario.f_probable_palpacion || null,
      nro_servicio: Number(datosFormulario.nro_servicio || 1),
      mservicios_id: Number(datosFormulario.mservicios_id),
      aptitud: Number(datosFormulario.aptitud) || 0,
      mrazas_id: Number(datosFormulario.mrazas_id) || 0,
      observaciones: datosFormulario.observaciones || '',
      medofisiologicos_id: Number(datosFormulario.medofisiologicos_id || 3),
      parto: Number(datosFormulario.parto || 0),
      // Seguimiento (palpación/parto): no aplica todavía a un servicio
      // recién creado — se completa más adelante editando (ver
      // Servicios.editar y el grupo "seguimiento" del formulario).
      f_palpacion: null,
      fecha_parto: null,
      nro_terneros: null,
      dnp: null,
      f_probable_parto: null,
      f_ultimo_parto: null,
      f_secado: null,
      version: 0,
      estado_sync: 'pendiente',
      created: ahora,
      updated: null,
    };

    await Db.guardar('servicios', registro);
    await Db.guardar('outbox', {
      uuid_local: uuid,
      modulo: 'servicios',
      version_base: null,
      datos: datosParaEnviar(registro),
      intentos: 0,
      creado: ahora,
    });

    if (navigator.onLine) Sync.sincronizar();

    return registro;
  },

  async editar(sesion, uuidLocal, datosFormulario) {
    const registroLocal = await Db.obtener('servicios', uuidLocal);
    if (!registroLocal) {
      throw new Error('No se encontro ese servicio en este dispositivo.');
    }
    if (registroLocal.estado_sync === 'conflicto') {
      throw new Error('Este servicio tiene un conflicto sin resolver.');
    }

    // Los animales (hembra/macho/doña) NO se pueden cambiar al editar
    // — son la identidad del registro, igual que en los otros
    // módulos. Solo se tocan los demás datos del servicio y el
    // seguimiento (palpación/parto).
    registroLocal.f_servicio = datosFormulario.f_servicio || registroLocal.f_servicio;
    registroLocal.f_probable_palpacion = datosFormulario.f_probable_palpacion || null;
    registroLocal.nro_servicio = Number(datosFormulario.nro_servicio || registroLocal.nro_servicio || 1);
    registroLocal.mservicios_id = Number(datosFormulario.mservicios_id || registroLocal.mservicios_id);
    registroLocal.aptitud = Number(datosFormulario.aptitud || registroLocal.aptitud) || 0;
    registroLocal.mrazas_id = Number(datosFormulario.mrazas_id || registroLocal.mrazas_id) || 0;
    registroLocal.observaciones = datosFormulario.observaciones || '';

    const datosAEnviar = {
      f_servicio: registroLocal.f_servicio,
      f_probable_palpacion: registroLocal.f_probable_palpacion,
      nro_servicio: registroLocal.nro_servicio,
      mservicios_id: registroLocal.mservicios_id,
      aptitud: registroLocal.aptitud,
      mrazas_id: registroLocal.mrazas_id,
      observaciones: registroLocal.observaciones,
    };

    // Seguimiento (palpación/parto) — ver manejarGuardarServicios en
    // app.js, solo se mandan estos campos al editar un servicio ya
    // existente. Si vienen en datosFormulario (aunque sea null/''), se
    // respetan tal cual: null/'' es una limpieza deliberada del campo,
    // no "no cambiar nada".
    for (const campo of ['f_palpacion', 'medofisiologicos_id', 'parto', 'fecha_parto', 'nro_terneros', 'dnp', 'f_probable_parto', 'f_ultimo_parto', 'f_secado']) {
      if (campo in datosFormulario) {
        const valor = campo === 'parto' ? Number(datosFormulario[campo]) : datosFormulario[campo];
        registroLocal[campo] = valor;
        datosAEnviar[campo] = valor;
      }
    }

    registroLocal.estado_sync = 'pendiente';
    await Db.guardar('servicios', registroLocal);

    const pendienteExistente = await Db.obtener('outbox', uuidLocal);
    const versionBase = pendienteExistente
      ? pendienteExistente.version_base
      : (registroLocal.version ?? null);

    await Db.guardar('outbox', {
      uuid_local: uuidLocal,
      modulo: 'servicios',
      version_base: versionBase,
      datos: datosAEnviar,
      intentos: 0,
      creado: new Date().toISOString(),
    });

    if (navigator.onLine) Sync.sincronizar();

    return registroLocal;
  },
};
