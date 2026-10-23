// Credencial local para poder reconectarse SIN CONEXIÓN si la sesión
// (el token) se cierra en este equipo. No reemplaza el login normal.
//
// El hash se deriva con PBKDF2 + sal aleatoria propia de este
// dispositivo. Además del hash local, se registra en el servidor que
// ESTE dispositivo es el activo para este usuario/fundo, con un modo:
//   - 'gestion': puede pull + push. Solo uno por usuario.
//   - 'consulta': solo pull. Varios por usuario.

const CredencialLocal = {
  ITERACIONES: 150000,

  LIMITE_DIAS_SIN_VALIDAR: 30,
  DIAS_AVISO_PREVIO: 5,

  disponible() {
    return this._soportaWebCryptoSeguro() || this._permiteFallbackInseguro();
  },

  _soportaWebCryptoSeguro() {
    return !!(window.crypto && window.crypto.subtle && typeof window.TextEncoder !== 'undefined');
  },

  _permiteFallbackInseguro() {
    return !!(CONFIG && CONFIG.PERMITIR_CRYPTO_OFFLINE_INSEGURO && typeof window.TextEncoder !== 'undefined');
  },

  _bytesAHex(bytes) {
    return Array.from(new Uint8Array(bytes)).map((b) => b.toString(16).padStart(2, '0')).join('');
  },

  _hexABytes(hex) {
    const bytes = new Uint8Array(hex.length / 2);
    for (let i = 0; i < bytes.length; i++) bytes[i] = parseInt(hex.substr(i * 2, 2), 16);
    return bytes;
  },

  generarSalHex() {
    if (!this.disponible()) {
      const err = new Error('Este navegador no soporta WebCrypto para reconexión offline.');
      err.code = 'crypto_no_disponible';
      throw err;
    }
    const bytes = new Uint8Array(16);
    if (window.crypto && typeof window.crypto.getRandomValues === 'function') {
      window.crypto.getRandomValues(bytes);
    } else {
      for (let i = 0; i < bytes.length; i++) bytes[i] = Math.floor(Math.random() * 256);
    }
    return this._bytesAHex(bytes);
  },

  _fnv1a32(texto, semilla) {
    let h = semilla >>> 0;
    for (let i = 0; i < texto.length; i++) {
      h ^= texto.charCodeAt(i);
      h = Math.imul(h, 16777619);
    }
    return h >>> 0;
  },

  _fallbackHashHex(password, salHex) {
    let bloque = password + '|' + salHex + '|BOVS_OFFLINE_FALLBACK';
    let a = 0x811c9dc5;
    let b = 0x9e3779b9;
    let c = 0x85ebca6b;
    let d = 0xc2b2ae35;

    for (let i = 0; i < 4096; i++) {
      a = this._fnv1a32(bloque, a);
      b = this._fnv1a32(bloque + String(a), b);
      c = this._fnv1a32(String(b) + bloque, c);
      d = this._fnv1a32(String(c) + String(a), d);
      bloque = a.toString(16) + b.toString(16) + c.toString(16) + d.toString(16);
    }

    return [a, b, c, d].map((n) => n.toString(16).padStart(8, '0')).join('');
  },

  async derivarHashHex(password, salHex) {
    if (!this.disponible()) {
      const err = new Error('Este navegador no soporta WebCrypto para reconexión offline.');
      err.code = 'crypto_no_disponible';
      throw err;
    }

    if (!this._soportaWebCryptoSeguro()) {
      return this._fallbackHashHex(password, salHex);
    }

    const enc = new TextEncoder();
    const claveMaterial = await crypto.subtle.importKey('raw', enc.encode(password), 'PBKDF2', false, ['deriveBits']);
    const bits = await crypto.subtle.deriveBits(
      { name: 'PBKDF2', salt: this._hexABytes(salHex), iterations: this.ITERACIONES, hash: 'SHA-256' },
      claveMaterial,
      256,
    );
    return this._bytesAHex(bits);
  },

  /**
   * Activa (o renueva) la credencial local para este usuario en este
   * equipo. Se llama justo después de un login exitoso EN LÍNEA.
   *
   * Antes de guardar el hash local, se registra el vínculo en el
   * servidor (Api.vincularDispositivo). Si el server rechaza porque
   * el usuario ya tiene otro dispositivo en modo GESTIÓN, se propaga
   * ese error con código 'dispositivo_gestion_ya_vinculado'.
   *
   * modoOffline: 'gestion' (default) | 'consulta'.
   */
  async activar(usuario, password, usuarioInfo, dispositivo, token = null, dispositivoId = null, modoOffline = 'gestion', fundos = null) {
    const existente = await Db.algunaCredencialOffline();

    // MULTIFUNDO: el equipo queda a nombre de UN usuario, pero ese mismo
    // usuario puede tener varios fundos. Antes se exigía además que el
    // fundo coincidiera, lo que impedía de plano activar el segundo
    // fundo de la misma persona en el mismo teléfono.
    const esOtroUsuario = existente && existente.usuario !== usuario;

    if (esOtroUsuario) {
      const err = new Error(
        'Este equipo ya tiene el modo sin conexión activado para "' + existente.usuario + '". '
        + 'Para activarlo con otro usuario hay que "Olvidar este equipo" primero.'
      );
      err.code = 'equipo_ocupado';
      err.usuarioExistente = existente.usuario;
      throw err;
    }

    if (!['gestion', 'consulta'].includes(modoOffline)) {
      modoOffline = 'gestion';
    }

    const fundosActivos = Array.isArray(fundos) && fundos.length
      ? fundos.map((f) => parseInt(f, 10)).filter((f) => f > 0)
      : null;

    if (token && dispositivoId) {
      try {
        await Api.vincularDispositivo(token, dispositivoId, dispositivo, modoOffline, fundosActivos);
      } catch (e) {
        const cuerpo = e && e.cuerpo ? e.cuerpo : null;

        if (cuerpo && cuerpo.codigo === 'dispositivo_gestion_ya_vinculado') {
          const err = new Error(
            'Este usuario ya tiene otro dispositivo en modo GESTIÓN.'
          );
          err.code = 'dispositivo_gestion_ya_vinculado';
          err.otroDispositivo = cuerpo.otro_dispositivo || null;
          throw err;
        }
        // Compatibilidad con la versión vieja del backend.
        if (cuerpo && cuerpo.codigo === 'dispositivo_ya_vinculado') {
          const err = new Error(
            'Este usuario ya tiene el modo offline activo en otro dispositivo.'
          );
          err.code = 'dispositivo_gestion_ya_vinculado';
          err.otroDispositivo = cuerpo.otro_dispositivo || null;
          throw err;
        }
        throw e;
      }
    }

    const salHex = this.generarSalHex();
    const hashHex = await this.derivarHashHex(password, salHex);

    await Db.guardarCredencialOffline({
      usuario,
      salHex,
      hashHex,
      usuarioInfo,
      dispositivo,
      dispositivoId,
      modoOffline,
      // Multifundo: se recuerda con qué fundos quedó habilitado este
      // equipo, para poder reconectar sin conexión y para reconstruir la
      // sesión (que ya no es "un usuario y un fundo").
      fundos: fundosActivos || (usuarioInfo && usuarioInfo.fundos) || [],
      fundo_activo: (usuarioInfo && usuarioInfo.fundo_id) || (fundosActivos ? fundosActivos[0] : 0),
      activado: new Date().toISOString(),
      ultimaValidacionOnline: new Date().toISOString(),
    });
  },

  async hay(usuario) {
    return !!(await Db.obtenerCredencialOffline(usuario));
  },

  vigencia(fila) {
    const base = fila && fila.ultimaValidacionOnline ? new Date(fila.ultimaValidacionOnline) : null;
    if (!base || isNaN(base.getTime())) {
      return { vencida: true, avisar: false, fechaLimite: null, diasRestantes: null };
    }

    const fechaLimite = new Date(base.getTime());
    fechaLimite.setDate(fechaLimite.getDate() + this.LIMITE_DIAS_SIN_VALIDAR);

    const msRestantes = fechaLimite.getTime() - Date.now();
    const diasRestantes = Math.ceil(msRestantes / (24 * 60 * 60 * 1000));
    const vencida = msRestantes <= 0;
    const avisar = !vencida && diasRestantes <= this.DIAS_AVISO_PREVIO;

    return { vencida, avisar, fechaLimite, diasRestantes };
  },

  async vigenciaDe(usuario) {
    const fila = await Db.obtenerCredencialOffline(usuario);
    return this.vigencia(fila);
  },

  async renovarValidacionOnline(usuario) {
    const fila = await Db.obtenerCredencialOffline(usuario);
    if (!fila) return;
    fila.ultimaValidacionOnline = new Date().toISOString();
    await Db.guardarCredencialOffline(fila);
  },

  async verificar(usuario, password) {
    const fila = await Db.obtenerCredencialOffline(usuario);
    if (!fila) return null;

    const { vencida, fechaLimite } = this.vigencia(fila);
    if (vencida) {
      const err = new Error('Tu acceso sin conexión venció. Conéctate a internet e inicia sesión para seguir usando la app.');
      err.code = 'credencial_vencida';
      err.fechaLimite = fechaLimite;
      throw err;
    }

    const hashIntentado = await this.derivarHashHex(password, fila.salHex);
    return hashIntentado === fila.hashHex ? fila : null;
  },

  async olvidarEquipo(usuario) {
    await Db.olvidarEquipoOffline(usuario);
  },
};