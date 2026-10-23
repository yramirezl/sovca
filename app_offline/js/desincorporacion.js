// Lógica del módulo "Bajas" (desincorporación de animales).
//
// Equivalente offline de views/desincorporacion. Solo se permiten los
// tipos que se deciden en el campo:
//   2 = Descarte · 3 = Muerte · 4 = Venta · 5 = Extravío
// El tipo 7 (MIGRADO A OTRO FUNDO) NO se elige: lo registra solo el
// módulo de migraciones y es informativo.
//
// Igual que la web, al guardar la baja el animal queda con ese estado
// (mestatus_id). El servidor hace lo mismo al sincronizar; acá se
// actualiza la copia local para que el animal deje de aparecer activo
// sin esperar conexión.

// --- Causas de baja ---------------------------------------------------
//
// El formulario WEB guarda en mdesincorporacionanimales.causa_descarte /
// causa_mortalidad el ID del catálogo (mcausadescartes /
// mcausamortalidades), mientras que la app guarda el texto que escribe el
// operario. Para mostrarlas hay que distinguir los dos casos: si el valor
// es numérico se traduce con el catálogo y, si el equipo todavía no lo
// tiene descargado, se omite (mejor nada que un número suelto).

/** Nombre legible de una causa: el texto tal cual o el nombre del catálogo. */
function nombreCausaBaja(valor, items) {
  const texto = String(valor === null || valor === undefined ? '' : valor).trim();
  if (!texto) return '';
  if (!/^\d+$/.test(texto)) return texto;
  const item = (items || []).find((opcion) => String(opcion.id) === texto);
  return item ? item.nombre : '';
}

/** Catálogos de causas (descarte y muerte) descargados en el equipo. */
async function catalogosCausasBaja() {
  const [descarte, mortalidad] = await Promise.all([
    Db.obtenerCatalogo('causas_descarte'),
    Db.obtenerCatalogo('causas_mortalidad'),
  ]);
  return { causas_descarte: descarte || [], causas_mortalidad: mortalidad || [] };
}

/** Causa legible del registro de baja, según el tipo (2 descarte, 3 muerte). */
function causaBajaTexto(registro, catalogos) {
  if (!registro) return '';
  const esMuerte = Number(registro.tipo_desincorporacion) === 3;
  const mapas = catalogos || {};
  const principal = esMuerte ? registro.causa_mortalidad : registro.causa_descarte;
  const alterna = esMuerte ? registro.causa_descarte : registro.causa_mortalidad;
  return nombreCausaBaja(principal, esMuerte ? mapas.causas_mortalidad : mapas.causas_descarte)
    || nombreCausaBaja(alterna, esMuerte ? mapas.causas_descarte : mapas.causas_mortalidad)
    || '';
}

const Desincorporacion = {
  TIPOS: [
    { id: 2, nombre: 'Descarte' },
    { id: 3, nombre: 'Muerte' },
    { id: 4, nombre: 'Venta' },
    { id: 5, nombre: 'Extravío' },
  ],

  nombreTipo(tipo) {
    const encontrado = this.TIPOS.find((item) => Number(item.id) === Number(tipo));
    return encontrado ? encontrado.nombre : '';
  },

  esTipoPermitido(tipo) {
    return this.TIPOS.some((item) => Number(item.id) === Number(tipo));
  },

  async listar() {
    const todos = await Db.obtenerTodos('desincorporacion');
    return todos.sort((a, b) => {
      if (a.estado_sync !== b.estado_sync) {
        const prioridad = { conflicto: 0, error: 1, pendiente: 2, sincronizado: 3 };
        return (prioridad[a.estado_sync] ?? 9) - (prioridad[b.estado_sync] ?? 9);
      }
      return String(b.f_desincorporacion || '').localeCompare(String(a.f_desincorporacion || ''));
    });
  },

  async crear(sesion, datosFormulario) {
    const codigo = (datosFormulario.codigo_rgb_id_animal || '').toString().trim().toUpperCase();
    if (!codigo) {
      throw new Error('Elegí el animal que se da de baja.');
    }

    const tipo = Number(datosFormulario.tipo_desincorporacion);
    if (!this.esTipoPermitido(tipo)) {
      throw new Error('Solo se puede dar de baja por descarte, muerte, venta o extravío.');
    }

    if (!datosFormulario.f_desincorporacion) {
      throw new Error('Indicá la fecha de la baja.');
    }

    const bajas = await Db.obtenerTodos('desincorporacion');
    const yaRegistrada = bajas.find((baja) =>
      String(baja.codigo_rgb_id_animal || '').toUpperCase() === codigo);
    if (yaRegistrada) {
      throw new Error('Ese animal ya tiene una baja registrada en este dispositivo.');
    }

    // Mismo control que la web: una hembra con crías vivas y activas sin
    // ficha no se puede dar de baja. Se valida ACÁ (con los nacimientos
    // que ya están en el dispositivo) para no guardar una baja que el
    // servidor va a rechazar y dejar el animal marcado de baja sin serlo.
    const criasPendientes = await criasPendientesDeFicha(codigo);
    if (criasPendientes.length) {
      throw new Error('No se puede dar de baja esta hembra: tiene crías vivas que aún no tienen ficha ('
        + criasPendientes.join(', ') + '). Fichá las crías en Nacimientos, o actualizá su estado si murieron.');
    }

    const uuid = generarUUID();
    const ahora = new Date().toISOString();

    const registro = {
      uuid_local: uuid,
      id: null,
      codigo_rgb_id_animal: codigo,
      tipo_desincorporacion: tipo,
      f_desincorporacion: datosFormulario.f_desincorporacion,
      causa_descarte: tipo === 2 ? (datosFormulario.causa_descarte || '').toString().trim() : '',
      causa_mortalidad: tipo === 3 ? (datosFormulario.causa_mortalidad || '').toString().trim() : '',
      observaciones: (datosFormulario.observaciones || '').toString().trim(),
      version: 0,
      estado_sync: 'pendiente',
      created: ahora,
      updated: null,
    };

    await Db.guardar('desincorporacion', registro);
    await Db.guardar('outbox', {
      uuid_local: uuid,
      modulo: 'desincorporacion',
      version_base: null,
      datos: datosParaEnviar({
        codigo_rgb_id_animal: registro.codigo_rgb_id_animal,
        tipo_desincorporacion: registro.tipo_desincorporacion,
        f_desincorporacion: registro.f_desincorporacion,
        causa_descarte: registro.causa_descarte,
        causa_mortalidad: registro.causa_mortalidad,
        observaciones: registro.observaciones,
      }),
      intentos: 0,
      creado: ahora,
    });

    await aplicarBajaLocal(codigo, tipo);

    if (navigator.onLine) Sync.sincronizar();

    return registro;
  },
};

/**
 * Crías vivas y activas que esa hembra tiene en el dispositivo y que
 * todavía no fueron fichadas (mismo criterio que la web:
 * f_parto no nulo, estatus_nacido = 1, sin ficha). Devuelve los códigos
 * o identificadores para poder nombrarlas en el mensaje.
 */
async function criasPendientesDeFicha(codigo) {
  const codigoBuscado = String(codigo || '').toUpperCase();
  const nacimientos = await Db.obtenerTodos('nacimientos');
  const pendientes = [];

  for (const nacimiento of nacimientos) {
    const madre = String(nacimiento.codigo_rgb_id_animal_hembra || '').toUpperCase();
    if (madre !== codigoBuscado) continue;
    if (nacimiento.fichado) continue;
    if (!nacimiento.f_parto) continue;

    const terneros = Array.isArray(nacimiento.terneros) && nacimiento.terneros.length
      ? nacimiento.terneros
      : [{ id_ternero: nacimiento.id_ternero, estatus_nacido: nacimiento.estatus_nacido }];

    for (const ternero of terneros) {
      if (Number(ternero.estatus_nacido) !== 1) continue;
      const identificador = String(ternero.id_ternero || '').trim()
        || String(nacimiento.codigo_ternero || '').trim();
      pendientes.push(identificador || 'sin identificar');
    }
  }

  return pendientes;
}

/**
 * Marca el animal como dado de baja en la copia local (sin pasar por la
 * cola de envío: el servidor aplica el mismo cambio al recibir la baja).
 */
async function aplicarBajaLocal(codigo, tipo) {
  const animales = await Db.obtenerTodos('animales');
  const animal = animales.find((item) =>
    String(item.codigo_rgb_id_animal || '').toUpperCase() === String(codigo).toUpperCase());

  if (!animal) return;

  animal.mestatus_id = Number(tipo);
  animal.estado_baja = Number(tipo);
  animal.baja_fecha = new Date().toISOString().slice(0, 10);
  await Db.guardar('animales', animal);
}
