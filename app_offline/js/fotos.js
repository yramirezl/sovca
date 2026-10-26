// Fotos de los animales, dentro de la app sin conexión.
//
// El servidor guarda el binario en dist/img/fotos_animales/ y en
// manimales.imagen SOLO el nombre del archivo (varchar(100)). Eso hace
// que el trabajo se divida en tres tareas distintas:
//
//   1. MOSTRAR las fotos que ya existen en el servidor: cualquier animal
//      traído por el pull trae su "imagen" en la fila, así que la foto
//      se puede pedir directamente por URL. Para verla sin señal se
//      guarda una copia del binario en el almacén "fotos".
//   2. COMPRIMIR en el teléfono antes de subir (una foto de cámara pesa
//      3-6 MB; el servidor la deja en 800 px igual, así que no tiene
//      sentido gastar datos subiéndola entera).
//   3. SUBIR con su propia cola ("fotos_pendientes"), aparte del outbox
//      de sincronización: si una foto no sube, el registro del animal
//      no se queda bloqueado esperando, y viceversa.
//
// Nada de esto es obligatorio para registrar un animal: si la foto
// falla, el registro sigue su curso y la foto se reintenta después.

const Fotos = {
  // La app vive en /sovca/app_offline/, la carpeta de fotos en
  // /sovca/dist/img/fotos_animales/ — por eso el "../..".
  RUTA_SERVIDOR: '../dist/img/fotos_animales/',

  MAX_LADO: 1000, // px del lado mayor antes de subir
  CALIDAD: 0.82, // JPEG 0-1
  MAX_BYTES_ORIGINAL: 12 * 1024 * 1024, // tope del archivo elegido
  // Cuántas fotos se intentan bajar por sincronización. Bajar 4.000
  // fotos de golpe no es aceptable ni en wifi: se va completando solo,
  // empezando por los animales que están en el hato activo.
  DESCARGAS_POR_SYNC: 10,

  /* ------------------------------------------------------------------
     Comprimir en el equipo
     ------------------------------------------------------------------ */

  /**
   * Toma un File del input/cámara y devuelve un data URI JPEG ya
   * reducido. Si el navegador no puede decodificarla devuelve null.
   */
  async comprimirArchivo(archivo) {
    if (!archivo) return null;
    if (archivo.size > this.MAX_BYTES_ORIGINAL) {
      throw new Error('La imagen es demasiado grande. Intenta con una foto más liviana.');
    }

    const dataUriOriginal = await this.leerComoDataUri(archivo);
    const imagen = await this.cargarImagen(dataUriOriginal);
    if (!imagen) {
      throw new Error('No se pudo leer esa imagen. Prueba con otra foto.');
    }

    const escala = Math.min(1, this.MAX_LADO / Math.max(imagen.width, imagen.height));
    const ancho = Math.max(1, Math.round(imagen.width * escala));
    const alto = Math.max(1, Math.round(imagen.height * escala));

    const lienzo = document.createElement('canvas');
    lienzo.width = ancho;
    lienzo.height = alto;
    const ctx = lienzo.getContext('2d');
    // Fondo blanco: un PNG con transparencia se vería negro en JPEG.
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, ancho, alto);
    ctx.drawImage(imagen, 0, 0, ancho, alto);

    const salida = lienzo.toDataURL('image/jpeg', this.CALIDAD);
    // Un data URI vacío o gigantesco significa que el lienzo falló.
    if (!salida || salida.length < 100) return null;
    if (salida.length > 8 * 1024 * 1024) {
      throw new Error('La imagen sigue siendo demasiado grande después de comprimirla.');
    }
    return salida;
  },

  leerComoDataUri(archivo) {
    return new Promise((resolve, reject) => {
      const lector = new FileReader();
      lector.onload = () => resolve(String(lector.result || ''));
      lector.onerror = () => reject(new Error('No se pudo leer el archivo de la foto.'));
      lector.readAsDataURL(archivo);
    });
  },

  cargarImagen(dataUri) {
    return new Promise((resolve) => {
      const img = new Image();
      img.onload = () => resolve(img);
      img.onerror = () => resolve(null);
      img.src = dataUri;
    });
  },

  /* ------------------------------------------------------------------
     Cola local de fotos por subir
     ------------------------------------------------------------------ */

  /**
   * Guarda la foto en la cola del equipo. Se indexa por uuid_local del
   * animal (no por código) porque un animal creado sin conexión tiene
   * código PROVISIONAL: se reemplaza al sincronizar, y la foto tiene
   * que seguirlo.
   */
  async guardarPendiente(uuidLocal, dataUri, codigo = null) {
    if (!uuidLocal || !dataUri) return;
    const existente = await Db.obtener('fotos_pendientes', uuidLocal);
    await Db.guardar('fotos_pendientes', {
      uuid_local: uuidLocal,
      data_uri: dataUri,
      codigo: codigo || (existente && existente.codigo) || null,
      creado: (existente && existente.creado) || new Date().toISOString(),
      intentos: existente ? (existente.intentos || 0) : 0,
      ultimo_error: null,
    });
  },

  async contarPendientes() {
    const filas = await Db.obtenerTodos('fotos_pendientes');
    return filas.length;
  },

  async tienePendiente(uuidLocal) {
    return !!(await Db.obtener('fotos_pendientes', uuidLocal));
  },

  /* ------------------------------------------------------------------
     Subir al servidor
     ------------------------------------------------------------------ */

  /**
   * Sube todas las fotos pendientes y devuelve { subidas, fallidas }.
   * - Si no hay señal, no hace nada y las deja para el próximo intento.
   * - Un animal con código provisional se salta hasta que la
   *   sincronización le asigne el definitivo.
   */
  async enviarPendientes(sesion) {
    const resumen = { subidas: 0, fallidas: 0 };
    if (!sesion || !sesion.token || !navigator.onLine) return resumen;
    // Un equipo en modo consulta no puede escribir: sus fotos quedarían
    // rechazadas una y otra vez (403) acumulando intentos inútiles.
    if (sesion.modo_offline === 'consulta') return resumen;

    const pendientes = await Db.obtenerTodos('fotos_pendientes');
    if (!pendientes.length) return resumen;

    const fundoId = sesion.fundo_id;
    const dispositivoId = (typeof Dispositivo !== 'undefined' && Dispositivo.obtenerId)
      ? Dispositivo.obtenerId()
      : null;

    for (const pendiente of pendientes) {
      const animal = await Db.obtener('animales', pendiente.uuid_local);
      if (!animal) {
        // El animal se resolvió como "eliminado" o se borró la copia
        // local: la foto ya no tiene destino posible.
        await Db.eliminar('fotos_pendientes', pendiente.uuid_local);
        continue;
      }
      if (animal.codigo_provisional || !animal.codigo_rgb_id_animal) {
        continue; // todavía no tiene código definitivo en el servidor
      }

      try {
        const respuesta = await Api.subirFoto(
          sesion.token,
          animal.codigo_rgb_id_animal,
          pendiente.data_uri,
          dispositivoId,
          fundoId
        );

        animal.imagen = respuesta.imagen || animal.imagen || null;
        if (respuesta.version !== undefined && respuesta.version !== null) {
          animal.version = respuesta.version;
        }
        if (respuesta.updated) animal.updated = respuesta.updated;
        await Db.guardar('animales', animal);

        // La foto recién subida ya se puede ver sin señal: se guarda el
        // blob local a partir del data URI que acabamos de enviar.
        await this.guardarEnCache(animal.codigo_rgb_id_animal, respuesta.imagen, pendiente.data_uri);
        await Db.eliminar('fotos_pendientes', pendiente.uuid_local);
        resumen.subidas++;
      } catch (error) {
        if (error && error.message === 'sin_conexion') {
          return resumen; // se corta la cola, se reintenta después
        }
        pendiente.intentos = (pendiente.intentos || 0) + 1;
        pendiente.ultimo_error = (error && error.message) || 'Error al subir la foto.';
        await Db.guardar('fotos_pendientes', pendiente);
        resumen.fallidas++;
      }
    }

    return resumen;
  },

  /* ------------------------------------------------------------------
     Copia local para verlas sin conexión
     ------------------------------------------------------------------ */

  /** Guarda en el almacén "fotos" el binario de la imagen indicada. */
  async guardarEnCache(codigo, imagen, dataUri = null) {
    if (!codigo || !imagen) return;
    try {
      let blob = null;
      if (dataUri) {
        blob = await (await fetch(dataUri)).blob();
      } else {
        blob = await this.descargarBlob(imagen);
      }
      if (!blob || !blob.size) return;
      await Db.guardar('fotos', {
        codigo,
        imagen,
        blob,
        sin_archivo: false,
        descargado_en: new Date().toISOString(),
      });
    } catch (e) {
      // Sin señal o archivo ausente: se ve más adelante, no es un error.
    }
  },

  async descargarBlob(imagen) {
    const url = this.rutaDe(imagen);
    if (!url) return null;
    const respuesta = await fetch(url, { cache: 'force-cache' });
    if (!respuesta.ok) return null;
    return respuesta.blob();
  },

  rutaDe(imagen) {
    const nombre = String(imagen || '').trim();
    if (!nombre) return null;
    // Solo el nombre del archivo: nunca una ruta armada por el servidor.
    if (nombre.indexOf('/') !== -1 || nombre.indexOf('\\') !== -1) return null;
    return this.RUTA_SERVIDOR + encodeURIComponent(nombre);
  },

  /**
   * Baja las fotos que faltan en este equipo. Devuelve cuántas bajó.
   * Se prioriza el hato activo (mestatus_id = 1). Un archivo que no
   * existe en el servidor se marca como "sin_archivo" para no volver a
   * intentarlo en cada sincronización: hay miles de filas heredadas de
   * la web que apuntan a imágenes que nunca se copiaron a la carpeta.
   */
  async descargarFaltantes(limite = this.DESCARGAS_POR_SYNC) {
    if (!navigator.onLine) return 0;

    const animales = await Db.obtenerTodos('animales');
    const conFoto = animales.filter((a) => a && a.imagen && Number(a.mestatus_id ?? 1) === 1);
    if (!conFoto.length) return 0;

    const conocidas = new Map((await Db.obtenerTodos('fotos')).map((f) => [f.codigo, f]));
    const faltantes = conFoto.filter((a) => {
      const f = conocidas.get(a.codigo_rgb_id_animal);
      // Ya resuelta para esta misma imagen (blob guardado o archivo ausente).
      if (f && f.imagen === a.imagen) return false;
      return true;
    });

    let bajadas = 0;
    for (const animal of faltantes) {
      if (bajadas >= limite) break;
      const blob = await this.descargarBlob(animal.imagen).catch(() => null);
      if (!blob || !blob.size) {
        await Db.guardar('fotos', {
          codigo: animal.codigo_rgb_id_animal,
          imagen: animal.imagen,
          blob: null,
          sin_archivo: true,
          descargado_en: new Date().toISOString(),
        }).catch(() => {});
        continue;
      }
      await Db.guardar('fotos', {
        codigo: animal.codigo_rgb_id_animal,
        imagen: animal.imagen,
        blob,
        sin_archivo: false,
        descargado_en: new Date().toISOString(),
      });
      bajadas++;
    }
    return bajadas;
  },

  /**
   * URL para mostrar la foto de un animal:
   *   - copia guardada en el equipo  → blob local (funciona sin señal)
   *   - hay señal                    → URL del servidor
   *   - sin señal y sin copia        → null (la vista pone el marcador)
   * Devuelve { url, propia } — "propia" indica que hay que liberar la
   * URL con revocarUrl() cuando la vista se vuelva a pintar.
   */
  async urlDe(animal) {
    if (!animal) return { url: null, propia: false };
    const codigo = animal.codigo_rgb_id_animal;
    if (!codigo) return { url: null, propia: false };

    try {
      const guardada = await Db.obtener('fotos', codigo);
      // Se reutiliza la copia solo si corresponde a la imagen vigente.
      if (guardada && guardada.blob && guardada.imagen === (animal.imagen || guardada.imagen)) {
        return { url: URL.createObjectURL(guardada.blob), propia: true };
      }
      // Ya se comprobó que ese archivo no está en el servidor.
      if (guardada && guardada.sin_archivo && guardada.imagen === animal.imagen) {
        return { url: null, propia: false };
      }
    } catch (e) {
      // sin base abierta: se sigue con la URL del servidor
    }

    const pendiente = await this.tienePendiente(animal.uuid_local).catch(() => false);
    if (pendiente) {
      const fila = await Db.obtener('fotos_pendientes', animal.uuid_local).catch(() => null);
      if (fila && fila.data_uri) return { url: fila.data_uri, propia: false };
    }

    if (navigator.onLine && animal.imagen) {
      return { url: this.rutaDe(animal.imagen), propia: false };
    }
    return { url: null, propia: false };
  },

  revocarUrl(url, propia) {
    if (propia && url) {
      try { URL.revokeObjectURL(url); } catch (e) { /* nada */ }
    }
  },

  // URLs de objeto creadas en el último mapaDeUrls(), para liberarlas
  // antes de pintar la lista otra vez (si no, se acumulan en memoria).
  _urlsDelMapa: [],

  /**
   * Versión en lote de urlDe(): resuelve la foto de MUCHOS animales
   * leyendo el almacén "fotos" una sola vez. La usa la lista del hato,
   * que puede tener miles de tarjetas: llamar a urlDe() por animal
   * abriría la base una vez por cada uno.
   * Devuelve Map<uuid_local, { url, propia }>.
   */
  async mapaDeUrls(animales) {
    this._urlsDelMapa.forEach((url) => this.revocarUrl(url, true));
    this._urlsDelMapa = [];

    const mapa = new Map();
    const lista = Array.isArray(animales) ? animales : [];
    if (!lista.length) return mapa;

    const guardadas = new Map();
    try {
      (await Db.obtenerTodos('fotos')).forEach((f) => guardadas.set(f.codigo, f));
    } catch (e) { /* sin base abierta */ }

    const pendientes = new Map();
    try {
      (await Db.obtenerTodos('fotos_pendientes'))
        .forEach((f) => { if (f.data_uri) pendientes.set(f.uuid_local, f.data_uri); });
    } catch (e) { /* sin base abierta */ }

    const online = navigator.onLine;

    for (const animal of lista) {
      if (!animal || !animal.uuid_local) continue;
      const codigo = animal.codigo_rgb_id_animal;

      const local = codigo ? guardadas.get(codigo) : null;
      if (local && local.blob && local.imagen === (animal.imagen || local.imagen)) {
        const url = URL.createObjectURL(local.blob);
        this._urlsDelMapa.push(url);
        mapa.set(animal.uuid_local, { url, propia: true });
        continue;
      }

      if (pendientes.has(animal.uuid_local)) {
        mapa.set(animal.uuid_local, { url: pendientes.get(animal.uuid_local), propia: false });
        continue;
      }

      // Archivo verificado como ausente en el servidor: no se pide.
      if (local && local.sin_archivo && local.imagen === animal.imagen) continue;

      if (online && animal.imagen) {
        const url = this.rutaDe(animal.imagen);
        if (url) mapa.set(animal.uuid_local, { url, propia: false });
      }
    }

    return mapa;
  },

  /* ------------------------------------------------------------------
     Punto de entrada: lo llama la sincronización
     ------------------------------------------------------------------ */

  /**
   * Sube lo pendiente y baja algunas de las que faltan. Se llama desde
   * Sync pero nunca hace fallar la sincronización: los datos del hato
   * son lo importante, las fotos van aparte.
   */
  async procesar(sesion, permitirSubida = true) {
    const resultado = { subidas: 0, fallidas: 0, bajadas: 0 };
    if (permitirSubida) {
      try {
        const subida = await this.enviarPendientes(sesion);
        resultado.subidas = subida.subidas;
        resultado.fallidas = subida.fallidas;
      } catch (e) {
        console.warn('[Fotos] no se pudieron subir las fotos pendientes:', e);
      }
    }
    try {
      resultado.bajadas = await this.descargarFaltantes();
    } catch (e) {
      console.warn('[Fotos] no se pudieron bajar fotos:', e);
    }
    return resultado;
  },
};

if (typeof window !== 'undefined') {
  window.Fotos = Fotos;
}
