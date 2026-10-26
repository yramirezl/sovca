// Envoltorio delgado sobre fetch() para hablar con los 3 endpoints del
// API (auth_controller.php, rangos_controller.php, sync_controller.php).
// No decide NADA de lógica de sincronización — eso vive en sync.js.

class ErrorApi extends Error {
  constructor(mensaje, codigoHttp, cuerpo) {
    super(mensaje);
    this.codigoHttp = codigoHttp;
    this.cuerpo = cuerpo;
  }
}

async function llamarApi(archivo, cuerpo, token) {
  const headers = { 'Content-Type': 'application/json' };

  // El token va DOBLE a propósito: como header (lo correcto según el
  // estándar) y también dentro del cuerpo JSON (respaldo). Algunos
  // hostings compartidos con Apache/mod_php descartan el header
  // "Authorization" antes de que llegue a PHP por una política de
  // seguridad antigua — cuando pasa eso, el servidor igual encuentra
  // el token en el cuerpo y la app sigue funcionando sin depender de
  // tocar la configuración del servidor.
  const cuerpoFinal = token ? { ...cuerpo, token } : cuerpo;
  if (token) headers['Authorization'] = 'Bearer ' + token;

  let respuesta;
  try {
    respuesta = await fetch(CONFIG.API_BASE + archivo, {
      method: 'POST',
      headers,
      body: JSON.stringify(cuerpoFinal),
    });
  } catch (e) {
    // Fetch falla por red caída, no por error del servidor. Esto es
    // justamente lo normal en esta app: quien llama debe interpretarlo
    // como "seguimos trabajando offline", no como un error a mostrar.
    throw new ErrorApi('sin_conexion', 0, null);
  }

  let datos = null;
  try {
    datos = await respuesta.json();
  } catch (e) {
    throw new ErrorApi('El servidor respondió algo inesperado.', respuesta.status, null);
  }

  if (!respuesta.ok || datos.resultado === 'error') {
    // Un 401 con token adjunto significa que el SERVIDOR, estando
    // disponible, rechazó explícitamente esa sesión (expiró, se cerró
    // desde otro lado, etc.) — es distinto de "no hay conexión".
    if (token && respuesta.status === 401) {
      window.dispatchEvent(new CustomEvent('bovs:sesion_invalida', {
        detail: { mensaje: (datos && datos.mensaje) || 'Sesión no válida.' },
      }));
    }

    // 403 con código 'dispositivo_desvinculado': el servidor rechazó
    // esta llamada porque el dispositivo fue desvinculado.
    if (respuesta.status === 403 && datos && datos.codigo === 'dispositivo_desvinculado') {
      window.dispatchEvent(new CustomEvent('bovs:dispositivo_desvinculado', {
        detail: { mensaje: (datos && datos.mensaje) || 'Este dispositivo fue desvinculado.' },
      }));
    }

    // 403 con código 'sin_acceso_offline': el administrador le quitó el
    // permiso de usar la app sin conexión a esta cuenta.
    if (respuesta.status === 403 && datos && datos.codigo === 'sin_acceso_offline') {
      window.dispatchEvent(new CustomEvent('bovs:sin_acceso_offline', {
        detail: { mensaje: (datos && datos.mensaje) || 'Tu cuenta no tiene acceso al modo sin conexión.' },
      }));
    }

    // 403 con código 'mantenimiento': el servidor está cerrado para los
    // productores y ayudantes. No es un fallo de los registros: la app
    // avisa y la sincronización se reintenta cuando termine.
    if (respuesta.status === 403 && datos && datos.codigo === 'mantenimiento') {
      window.dispatchEvent(new CustomEvent('bovs:mantenimiento', {
        detail: { mensaje: (datos && datos.mensaje) || 'El sistema está en mantenimiento.' },
      }));
    }

    throw new ErrorApi(datos.mensaje || 'Error del servidor.', respuesta.status, datos);
  }

  return datos;
}

/**
 * Agrega el fundo con el que se está trabajando al cuerpo de la petición.
 * El servidor lo valida contra los fundos con acceso activo del usuario
 * (multifundo). Si no se indica, el servidor usa el fundo principal, así
 * que las llamadas viejas siguen funcionando igual.
 */
function conFundo(cuerpo, fundoId) {
  const id = parseInt(fundoId, 10) || 0;
  if (id > 0) cuerpo.fundo_id = id;
  return cuerpo;
}

const Api = {
  async login(usuario, password, dispositivo, dispositivoId) {
    const cuerpo = { action: 'login', usuario, password, dispositivo };
    if (dispositivoId) cuerpo.dispositivo_id = dispositivoId;
    return llamarApi('auth_controller.php', cuerpo);
  },

  async validarToken(token) {
    return llamarApi('auth_controller.php', { action: 'validar' }, token);
  },

  async datosFundo(token, fundoId) {
    return llamarApi('auth_controller.php', conFundo({ action: 'datos_fundo' }, fundoId), token);
  },

  async logout(token) {
    return llamarApi('auth_controller.php', { action: 'logout' }, token);
  },

  async reservarRango(token, modulo, cantidad, fundoId) {
    return llamarApi('rangos_controller.php', conFundo({ action: 'reservar', modulo, cantidad }, fundoId), token);
  },

  async pull(token, modulo, desde, dispositivoId, fundoId) {
    const cuerpo = conFundo({ action: 'pull', modulo, desde }, fundoId);
    if (dispositivoId) cuerpo.dispositivo_id = dispositivoId;
    return llamarApi('sync_controller.php', cuerpo, token);
  },

  async push(token, modulo, cambios, dispositivoId, fundoId) {
    const cuerpo = conFundo({ action: 'push', modulo, cambios }, fundoId);
    if (dispositivoId) cuerpo.dispositivo_id = dispositivoId;
    return llamarApi('sync_controller.php', cuerpo, token);
  },

  async catalogos(token, fundoId) {
    return llamarApi('catalogos_controller.php', conFundo({ action: 'listar' }, fundoId), token);
  },

  /**
   * Sube la foto de un animal. No viaja por el outbox de sync porque no
   * es un "cambio de datos" del animal: es un binario aparte que el
   * servidor guarda en disco y referencian con manimales.imagen. Tiene
   * su propia cola local (almacén "fotos_pendientes") y su propio
   * reintento, así una foto que no subió nunca bloquea un registro.
   */
  async subirFoto(token, codigo, imagenBase64, dispositivoId, fundoId) {
    const cuerpo = conFundo({
      action: 'subir_foto',
      codigo_rgb_id_animal: codigo,
      imagen_base64: imagenBase64,
    }, fundoId);
    if (dispositivoId) cuerpo.dispositivo_id = dispositivoId;
    return llamarApi('sync_controller.php', cuerpo, token);
  },

  async validarArete(token, nroArete, uuidLocalExcluir = null, fundoId = null) {
    const cuerpo = conFundo({ action: 'validar_arete', nro_arete: nroArete }, fundoId);
    if (uuidLocalExcluir) cuerpo.uuid_local_excluir = uuidLocalExcluir;
    return llamarApi('catalogos_controller.php', cuerpo, token);
  },

  // ---- Vínculo dispositivo <-> usuario/fundo (modo offline) ---------

  /**
   * Registra este dispositivo como el "dueño" del modo offline para el
   * usuario, en TODOS los fundos indicados (multifundo). Si no se pasa
   * la lista, el servidor vincula el fundo principal.
   *
   * modoOffline: 'gestion' (default) | 'consulta'
   *   - gestion: solo uno activo por usuario Y fundo. Puede pull + push.
   *   - consulta: varios por usuario. Solo pull, no push.
   */
  async vincularDispositivo(token, dispositivoId, nombreDispositivo, modoOffline, fundos) {
    const cuerpo = {
      action: 'vincular_dispositivo',
      dispositivo_id: dispositivoId,
      nombre_dispositivo: nombreDispositivo,
      modo_offline: modoOffline || 'gestion',
    };
    if (Array.isArray(fundos) && fundos.length) cuerpo.fundos = fundos;
    return llamarApi('auth_controller.php', cuerpo, token);
  },

  /**
   * Cambia el modo de un dispositivo existente, sin necesidad de
   * desvincular/revincular. Útil para pasar de consulta a gestión.
   */
  async cambiarModoDispositivo(token, dispositivoId, modoOffline, fundos) {
    const cuerpo = {
      action: 'cambiar_modo_dispositivo',
      dispositivo_id: dispositivoId,
      modo_offline: modoOffline,
    };
    if (Array.isArray(fundos) && fundos.length) cuerpo.fundos = fundos;
    return llamarApi('auth_controller.php', cuerpo, token);
  },

  async desvincularDispositivo(token, dispositivoId, motivo) {
    return llamarApi('auth_controller.php', {
      action: 'desvincular_dispositivo',
      dispositivo_id: dispositivoId,
      motivo: motivo || '',
    }, token);
  },

  async estadoDispositivo(token, dispositivoId) {
    return llamarApi('auth_controller.php', {
      action: 'estado_dispositivo',
      dispositivo_id: dispositivoId,
    }, token);
  },

  async listarDispositivos(token) {
    return llamarApi('auth_controller.php', { action: 'listar_dispositivos' }, token);
  },
};