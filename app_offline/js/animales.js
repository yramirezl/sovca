// Lógica específica del módulo "animales": crear, editar y listar.

/** Usado SOLO al crear — es el único momento en que se fija el arete. */
function camposDesdeFormulario(datosFormulario) {
  return {
    nombre: datosFormulario.nombre,
    sexo: Number(datosFormulario.sexo),
    nro_arete: datosFormulario.nro_arete,
    aptitud: Number(datosFormulario.aptitud),
    mrazas_id: Number(datosFormulario.mrazas_id),
    codigo_ternero: datosFormulario.codigo_ternero || '',
    f_nacimiento: datosFormulario.f_nacimiento || null,
    mlotes_id: datosFormulario.mlotes_id ? Number(datosFormulario.mlotes_id) : undefined,
    categoria_ingreso_id: datosFormulario.categoria_ingreso_id ? Number(datosFormulario.categoria_ingreso_id) : undefined,
    categoria_futura_id: datosFormulario.categoria_futura_id ? Number(datosFormulario.categoria_futura_id) : undefined,
      rgd_madre: datosFormulario.rgd_madre || '',
      nombre_madre: datosFormulario.nombre_madre || '',
      rgd_padre: datosFormulario.rgd_padre || '',
      nombre_padre: datosFormulario.nombre_padre || '',
      procedencia: datosFormulario.procedencia || '',
      servicio: datosFormulario.servicio ? Number(datosFormulario.servicio) : undefined,
      peso_nacimiento: datosFormulario.peso_nacimiento || '',
    observaciones: datosFormulario.observaciones || '',
  };
}

/**
 * Usado SOLO al editar — a propósito sin código ni nro_arete: son la
 * identidad física del animal (el arete ya está puesto, el código ya
 * se usó para la chapeta/documentación), igual que el animal mismo no
 * se puede cambiar en Control Sanitario, Pesos, etc. Si hace falta
 * corregir un arete mal cargado, es un caso especial que no debería
 * resolverse editando offline sin más — por ahora queda bloqueado.
 */
function camposEditablesAlEditar(datosFormulario) {
  return {
    nombre: datosFormulario.nombre,
    sexo: Number(datosFormulario.sexo),
    aptitud: Number(datosFormulario.aptitud),
    mrazas_id: Number(datosFormulario.mrazas_id),
    mlotes_id: datosFormulario.mlotes_id ? Number(datosFormulario.mlotes_id) : undefined,
    categoria_ingreso_id: datosFormulario.categoria_ingreso_id ? Number(datosFormulario.categoria_ingreso_id) : undefined,
    categoria_futura_id: datosFormulario.categoria_futura_id ? Number(datosFormulario.categoria_futura_id) : undefined,
    peso_nacimiento: datosFormulario.peso_nacimiento || '',
    observaciones: datosFormulario.observaciones || '',
  };
}

const Animales = {
  async listar() {
    const todos = await Db.obtenerTodos('animales');
    // Más recientes primero; los pendientes de sincronizar y en
    // conflicto suben arriba porque son los que la persona necesita
    // revisar primero.
    return todos.sort((a, b) => {
      if (a.estado_sync !== b.estado_sync) {
        const prioridad = { conflicto: 0, error: 1, pendiente: 2, sincronizado: 3 };
        return (prioridad[a.estado_sync] ?? 9) - (prioridad[b.estado_sync] ?? 9);
      }
      return (b.created || '').localeCompare(a.created || '');
    });
  },

  /**
   * ¿Ya hay un animal ACTIVO con este número de arete en el fundo de
   * esta sesión? Se usa desde el formulario (validación al guardar) y
   * desde la tarjeta de conflicto de tipo 'arete_duplicado' (para
   * verificar el arete nuevo antes de re-encolar el push).
   *
   * Criterios, cada uno con su razón:
   *  - Mayúsculas y trim: el sistema web original normaliza con
   *    strtoupper() al guardar, así que "abc-123" y "ABC-123" son el
   *    mismo arete.
   *  - Solo contra animales con status===1 (o undefined, que cuenta
   *    como 1): la app web permite reutilizar el arete de un animal
   *    dado de baja (status=0), así que acá replicamos ese criterio.
   *  - Excluir el propio uuid al editar: si no, el animal se
   *    encontraría a sí mismo como duplicado.
   *  - Todo local contra IndexedDB — no consulta al servidor. El
   *    servidor hace la misma validación al recibir el push (ver
   *    validar_campos_unicos en sync_controller.php) por si dos
   *    dispositivos offline registraron el mismo arete sin haberse
   *    visto entre sí.
   */
  async existeArete(nroArete, uuidExcluir = null) {
    if (!nroArete) return null;
    const objetivo = String(nroArete).trim().toUpperCase();
    if (!objetivo) return null;

    const todos = await Db.obtenerTodos('animales');
    const choque = todos.find((animal) => {
      if (uuidExcluir && animal.uuid_local === uuidExcluir) return false;
      if (Number(animal.status ?? 1) !== 1) return false;
      return String(animal.nro_arete || '').trim().toUpperCase() === objetivo;
    });
    return choque || null;
  },

  async validarArete(nroArete, sesion, uuidExcluir = null) {
    const local = await this.existeArete(nroArete, uuidExcluir);
    if (local) return { duplicado: local, verificadoServidor: false };

    if (!navigator.onLine || !sesion || !sesion.token) {
      return { duplicado: null, verificadoServidor: false };
    }

    try {
      const respuesta = await Api.validarArete(sesion.token, nroArete, uuidExcluir);
      return {
        duplicado: respuesta.animal || null,
        verificadoServidor: true,
      };
    } catch (error) {
      if (error && error.message === 'sin_conexion') {
        return { duplicado: null, verificadoServidor: false, sinConexion: true };
      }
      throw error;
    }
  },

  /**
   * Crea un animal nuevo enteramente local: le asigna código (definitivo
   * si hay rango reservado, provisional si no) y lo agrega al outbox
   * para que se suba en la próxima sincronización.
   */
  async crear(sesion, datosFormulario, opciones = {}) {
    const { codigo, provisional } = await Db.tomarSiguienteCodigo('animales', sesion.fundo_id, 'SOVCA');
    const uuid = generarUUID();
    const ahora = new Date().toISOString();

    const animal = {
      uuid_local: uuid,
        nacimiento_uuid_local: datosFormulario.nacimiento_uuid_local || null,
      id: null,
      codigo_rgb_id_animal: codigo,
      codigo_provisional: provisional,
      ...camposDesdeFormulario(datosFormulario),
      version: 0,
      estado_sync: 'pendiente',
      created: ahora,
      updated: null,
    };

    await Db.guardar('animales', animal);
    await Db.guardar('outbox', {
      uuid_local: uuid,
        nacimiento_uuid_local: animal.nacimiento_uuid_local,
      modulo: CONFIG.MODULO_ANIMALES,
      version_base: null, // null = es una creación, no una edición
      datos: datosParaEnviar(animal),
      intentos: 0,
      creado: ahora,
    });

    // Si hay señal, intenta sincronizar de una vez; si no, el outbox
    // se queda esperando tranquilo hasta que vuelva la conexión.
    if (navigator.onLine && opciones.sincronizar !== false) Sync.sincronizar();

    return animal;
  },

  /**
   * Edita un animal que ya existe localmente (haya sido creado offline
   * o traído del servidor con "pull"). No sobreescribe nada en el
   * servidor directamente: encola el cambio y lo aplica el próximo
   * push, con el mismo control de versión que usa el resto del motor
   * de sincronización.
   */
  async editar(sesion, uuidLocal, datosFormulario) {
    const animalLocal = await Db.obtener('animales', uuidLocal);
    if (!animalLocal) {
      throw new Error('No se encontró ese animal en este dispositivo.');
    }
    if (animalLocal.estado_sync === 'conflicto') {
      throw new Error('Este animal tiene un conflicto sin resolver. Resuélvelo antes de editarlo de nuevo.');
    }

    Object.assign(animalLocal, camposEditablesAlEditar(datosFormulario));
    animalLocal.estado_sync = 'pendiente';
    await Db.guardar('animales', animalLocal);

    // Si ya había un cambio pendiente para este mismo animal (por
    // ejemplo, todavía no se sincronizó su creación), se reemplaza
    // conservando su version_base original — así el servidor sigue
    // sabiendo si es una creación (null) o una edición sobre una
    // versión concreta.
    const pendienteExistente = await Db.obtener('outbox', uuidLocal);
    const versionBase = pendienteExistente
      ? pendienteExistente.version_base
      : (animalLocal.version ?? null);

    await Db.guardar('outbox', {
      uuid_local: uuidLocal,
      modulo: CONFIG.MODULO_ANIMALES,
      version_base: versionBase,
      // A propósito NO se manda datosParaEnviar(animalLocal) (todo el
      // registro) ni camposDesdeFormulario (esa incluye nro_arete):
      // al editar solo viajan los campos que este formulario
      // realmente controla — código y arete quedan bloqueados (ver
      // camposEditablesAlEditar).
      datos: camposEditablesAlEditar(datosFormulario),
      intentos: 0,
      creado: new Date().toISOString(),
    });

    if (navigator.onLine) Sync.sincronizar();

    return animalLocal;
  },
};