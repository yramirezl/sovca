// Lógica específica del módulo "Nacimientos". A diferencia de
// Servicios, acá el código del ternero (codigo_ternero) SÍ se genera
// en el dispositivo cuando no hay rango reservado — mismo patrón que
// codigo_rgb_id_animal en animales.js (usa Db.tomarSiguienteCodigo) —
// porque igual que el arete de un animal, es un dato que puede hacer
// falta anotar/mostrar antes de tener señal.
//
// f_servicio (fecha probable de celo) y mtipoganaderias_id NO los
// arma el cliente: los calcula/completa el servidor al crear (ver
// crear_registro_nacimiento en sync_controller.php) y vuelven en
// "campos_resueltos" — sync.js ya los aplica al registro local
// automáticamente, no hace falta nada especial acá.
//
// Un parto puede tener varias crías (igual que la web). El detalle
// viaja en el campo "terneros" y los campos planos del registro
// (sexo, peso_nacimiento, estatus_nacido, aptitud, id_ternero)
// reflejan siempre al primer ternero, que es lo que usa la ficha del
// animal y el servidor para la fila principal de mregnacimientos.

/**
 * "Entrar en lactancia": Sí se guarda como 2 (activa el cálculo de la
 * fecha de secado con el período de lactancia del fundo) y No como 0.
 * El 1 corresponde a registros viejos de "Lactancia" y se sigue
 * aceptando como Sí. Se guarda en la columna produccion_leche (smallint)
 * para no romper los reportes existentes.
 */
function esPropositoProduccion(proposito) {
  return [1, 2].includes(Number(proposito));
}

/**
 * Normaliza el detalle de crías del parto. Si el formulario no trae
 * lista, arma una sola cría con los campos planos (compatibilidad).
 */
function normalizarTernerosNacimiento(datosFormulario) {
  const lista = Array.isArray(datosFormulario.terneros) ? datosFormulario.terneros : [];

  const terneros = lista.map((ternero, indice) => ({
    nro_ternero: indice + 1,
    id_ternero: (ternero.id_ternero || '').toString().trim().toUpperCase(),
    peso_nacimiento: ternero.peso_nacimiento == null ? '' : String(ternero.peso_nacimiento).trim(),
    sexo: Number(ternero.sexo) === 2 ? 2 : 1,
    estatus_nacido: Number(ternero.estatus_nacido) === 2 ? 2 : 1,
    aptitud: Number(ternero.aptitud) || 0,
  }));

  if (terneros.length) return terneros;

  return [{
    nro_ternero: 1,
    id_ternero: (datosFormulario.id_ternero || '').toString().trim().toUpperCase(),
    peso_nacimiento: datosFormulario.peso_nacimiento == null ? '' : String(datosFormulario.peso_nacimiento).trim(),
    sexo: Number(datosFormulario.sexo) === 2 ? 2 : 1,
    estatus_nacido: Number(datosFormulario.estatus_nacido) === 2 ? 2 : 1,
    aptitud: Number(datosFormulario.aptitud) || 0,
  }];
}

/**
 * Marca el servicio gestante como "con parto" en este dispositivo, igual
 * que hace la web al guardar el nacimiento. Así la misma gestación no se
 * vuelve a ofrecer para otro parto mientras no haya conexión. No se toca
 * estado_sync: el cierre definitivo lo confirma el servidor al crear el
 * nacimiento y la siguiente descarga deja todo igual.
 */
async function marcarServicioConParto(nacimiento) {
  if (!nacimiento || !nacimiento.servicio_uuid_local) return;

  const servicio = await Db.obtener('servicios', nacimiento.servicio_uuid_local);
  if (!servicio) return;

  servicio.estatus_ciclo = 2;
  servicio.estado_servicio = 2;
  servicio.parto = 1;
  servicio.fecha_parto = nacimiento.f_parto || servicio.fecha_parto || null;
  servicio.nro_terneros = (nacimiento.terneros || []).length || 1;
  await Db.guardar('servicios', servicio);
}

const Nacimientos = {
  async listar() {
    const todos = await Db.obtenerTodos('nacimientos');
    return todos.sort((a, b) => {
      if (a.estado_sync !== b.estado_sync) {
        const prioridad = { conflicto: 0, error: 1, pendiente: 2, sincronizado: 3 };
        return (prioridad[a.estado_sync] ?? 9) - (prioridad[b.estado_sync] ?? 9);
      }
      return (b.f_parto || '').localeCompare(a.f_parto || '');
    });
  },

  async crear(sesion, datosFormulario) {
    const { codigo, provisional } = await Db.tomarSiguienteCodigo('nacimientos', sesion.fundo_id, 'MA');
    const uuid = generarUUID();
    const ahora = new Date().toISOString();
    const terneros = normalizarTernerosNacimiento(datosFormulario);
    const primerTernero = terneros[0];

    const registro = {
      uuid_local: uuid,
      id: null,
      codigo_ternero: codigo,
      codigo_provisional: provisional,
      id_ternero: primerTernero.id_ternero,
      codigo_rgb_id_animal_hembra: datosFormulario.codigo_rgb_id_animal_hembra,
      codigo_rgb_id_animal_macho: datosFormulario.codigo_rgb_id_animal_macho || '',
      codigo_rgb_id_animal_dona: datosFormulario.codigo_rgb_id_animal_dona || '',
      mregservicios_id: datosFormulario.mregservicios_id || null,
      servicio_uuid_local: datosFormulario.servicio_uuid_local || null,
      fichado: false,
      ficha_animal_uuid_local: null,
      ficha_animal_codigo: null,
      mtiposervicios_id: datosFormulario.mtiposervicios_id ? Number(datosFormulario.mtiposervicios_id) : null,
      f_parto: datosFormulario.f_parto,
      peso_nacimiento: primerTernero.peso_nacimiento,
      estatus_nacido: primerTernero.estatus_nacido,
      aptitud: primerTernero.aptitud || null,
      sexo: primerTernero.sexo,
      produccion_leche: esPropositoProduccion(datosFormulario.proposito) ? 1 : 0,
      proposito: Number(datosFormulario.proposito) || 0,
      observaciones: datosFormulario.observaciones || '',
      f_secado: datosFormulario.f_secado || null,
      nro_parto: datosFormulario.nro_parto ? Number(datosFormulario.nro_parto) : null,
      // Detalle de las crías del parto (una o varias), igual que la web.
      terneros,
      // Se completan al sincronizar (ver comentario arriba) — quedan
      // en null hasta entonces, no se muestran como si ya existieran.
      f_servicio: null,
      mtipoganaderias_id: null,
      version: 0,
      estado_sync: 'pendiente',
      created: ahora,
      updated: null,
    };

    await Db.guardar('nacimientos', registro);
    await Db.guardar('outbox', {
      uuid_local: uuid,
      modulo: 'nacimientos',
      version_base: null,
      datos: datosParaEnviar(registro),
      intentos: 0,
      creado: ahora,
    });

    await marcarServicioConParto(registro);

    if (navigator.onLine) Sync.sincronizar();

    return registro;
  },

  /**
   * Editar un nacimiento: igual que en los demás módulos, la hembra,
   * el macho y la doña son la identidad del registro y no se pueden
   * cambiar acá — solo los demás datos (peso, estatus, propósito,
   * observaciones, etc.).
   */
  async editar(sesion, uuidLocal, datosFormulario) {
    const registroLocal = await Db.obtener('nacimientos', uuidLocal);
    if (!registroLocal) {
      throw new Error('No se encontró ese nacimiento en este dispositivo.');
    }
    if (registroLocal.fichado) {
      throw new Error('Este nacimiento ya fue fichado y solo se puede consultar.');
    }
    if (registroLocal.estado_sync === 'conflicto') {
      throw new Error('Este nacimiento tiene un conflicto sin resolver.');
    }

    registroLocal.mtiposervicios_id = datosFormulario.mtiposervicios_id ? Number(datosFormulario.mtiposervicios_id) : null;
    registroLocal.f_parto = datosFormulario.f_parto || registroLocal.f_parto;
    registroLocal.terneros = normalizarTernerosNacimiento(datosFormulario);
    registroLocal.peso_nacimiento = registroLocal.terneros[0].peso_nacimiento;
    registroLocal.estatus_nacido = registroLocal.terneros[0].estatus_nacido;
    registroLocal.aptitud = registroLocal.terneros[0].aptitud || null;
    registroLocal.sexo = registroLocal.terneros[0].sexo;
    registroLocal.id_ternero = registroLocal.terneros[0].id_ternero;
    registroLocal.produccion_leche = esPropositoProduccion(datosFormulario.proposito) ? 1 : 0;
    registroLocal.proposito = Number(datosFormulario.proposito) || 0;
    registroLocal.observaciones = datosFormulario.observaciones || '';
    registroLocal.f_secado = datosFormulario.f_secado || null;
    registroLocal.nro_parto = datosFormulario.nro_parto ? Number(datosFormulario.nro_parto) : null;
    registroLocal.estado_sync = 'pendiente';
    await Db.guardar('nacimientos', registroLocal);

    const datosActualizacion = {
      id_ternero: registroLocal.id_ternero,
      mtiposervicios_id: registroLocal.mtiposervicios_id,
      f_parto: registroLocal.f_parto,
      peso_nacimiento: registroLocal.peso_nacimiento,
      estatus_nacido: registroLocal.estatus_nacido,
      aptitud: registroLocal.aptitud,
      sexo: registroLocal.sexo,
      produccion_leche: registroLocal.produccion_leche,
      proposito: registroLocal.proposito,
      observaciones: registroLocal.observaciones,
      f_secado: registroLocal.f_secado,
      nro_parto: registroLocal.nro_parto,
      terneros: registroLocal.terneros,
    };

    const pendienteExistente = await Db.obtener('outbox', uuidLocal);
    const versionBase = pendienteExistente
      ? pendienteExistente.version_base
      : (registroLocal.version ?? null);
    const datosAEnviar = pendienteExistente && pendienteExistente.version_base === null
      ? datosParaEnviar(registroLocal)
      : datosActualizacion;

    await Db.guardar('outbox', {
      uuid_local: uuidLocal,
      modulo: 'nacimientos',
      version_base: versionBase,
      datos: datosAEnviar,
      intentos: 0,
      creado: new Date().toISOString(),
    });

    if (navigator.onLine) Sync.sincronizar();

    return registroLocal;
  },
};
