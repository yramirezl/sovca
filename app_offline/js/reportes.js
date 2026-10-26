const REPORTES_CATALOG = [
  {
    code: 'animales_activos',
    name: 'Animales activos',
    description: 'Listado local de animales con codigo, nombre y arete, y resumen por lote.',
    module: 'animales',
    render: 'animalesActivos',
  },
  {
    code: 'resumen_servicios',
    name: 'Resumen de servicios',
    description: 'Listado local de servicios por hembra y fecha.',
    module: 'servicios',
    render: 'resumenServicios',
  },
  {
    code: 'resumen_produccion_lechera',
    name: 'Produccion de Leche',
    description: 'Agrupacion local de litros por hembra en aptitud leche y fecha.',
    module: 'produccion_leche',
    render: 'resumenProduccionLechera',
  },
  {
    code: 'resumen_control_sanitario',
    name: 'Control sanitario',
    description: 'Resumen por animal (total, ultimo y proximo control) y detalle de cada control, con los dias hasta el proximo.',
    module: 'control_sanitario',
    render: 'controlSanitario',
  },
  {
    code: 'servicios_por_tipo',
    name: 'Servicios por tipo',
    description: 'Agrupacion local de servicios por tipo y fecha.',
    module: 'servicios',
    render: 'serviciosPorTipo',
  },
  {
    code: 'gestantes_activas',
    name: 'Gestantes activas',
    description: 'Hembras en ciclo reproductivo con servicio activo y parto pendiente.',
    module: 'servicios',
    render: 'gestantesActivas',
  },
  {
    code: 'partos_confirmados',
    name: 'Partos confirmados',
    description: 'Servicios cerrados por parto con fecha efectiva.',
    module: 'servicios',
    render: 'partosConfirmados',
  },
  {
    code: 'inventario_por_estado',
    name: 'Inventario por estado animal',
    description: 'Agrupacion local por estado del animal y sexo.',
    module: 'animales',
    render: 'inventarioPorEstado',
  },
  {
    code: 'bajas_desincorporados',
    name: 'Bajas y desincorporados',
    description: 'Animales fuera de produccion con el tipo de baja (descarte, muerte, venta, extravio o migracion), fecha, causa y lote.',
    module: 'animales',
    render: 'bajasDesincorporados',
  },
  {
    code: 'resumen_por_aptitud',
    name: 'Resumen por aptitud',
    description: 'Totales locales por aptitud y sexo en el fondo.',
    module: 'animales',
    render: 'resumenPorAptitud',
  },
  {
    code: 'resumen_nacimientos',
    name: 'Informe de nacimientos',
    description: 'General (todas las madres) o de una hembra especifica si se filtra por su codigo/arete: totales, pesos, IEP y detalle de partos.',
    module: 'nacimientos',
    render: 'resumenNacimientos',
  },
  {
    code: 'consecutivo_peso',
    name: 'Consecutivo de peso',
    description: 'Cada pesaje con el peso anterior del mismo animal, la diferencia y los dias transcurridos.',
    module: 'pesos',
    render: 'consecutivoPeso',
  },
  {
    code: 'movimiento_rebano',
    name: 'Movimiento de rebano',
    description: 'Traslados entre lotes y/o potreros con fecha, motivo, animales y si el movimiento fue revertido.',
    module: 'movimiento_rebano',
    render: 'movimientoRebano',
  },
  {
    code: 'grupos_etarios',
    name: 'Grupos etarios',
    description: 'Animales activos agrupados por edad en meses, con su categoria y sexo.',
    module: 'animales',
    render: 'gruposEtarios',
  },
  {
    code: 'hembras_servidas',
    name: 'Hembras servidas',
    description: 'Servicios por hembra en el rango de fechas, con tipo de servicio, macho y si termino en parto.',
    module: 'servicios',
    render: 'hembrasServidas',
  },
  {
    code: 'productoras_leche',
    name: 'Productoras de leche',
    description: 'Litros por hembra en el rango de fechas: dias con registro, totales y promedio por dia.',
    module: 'produccion_leche',
    render: 'productorasLeche',
  },
  {
    code: 'animales_por_lote',
    name: 'Animales por lote',
    description: 'Listado de animales activos agrupado por lote, con totales de hembras y machos.',
    module: 'animales',
    render: 'animalesPorLote',
  },
];

/**
 * Compara el código/nombre de un animal contra un valor de filtro por
 * IGUALDAD exacta (sin distinguir mayúsculas ni espacios sobrantes) —
 * no por coincidencia parcial. Los filtros de animal/hembra/macho se
 * llenan eligiendo de un buscador que ya deja el código exacto en el
 * campo (ver inicializarBuscadorAnimales en app.js); comparar con
 * .includes() hacía que "SOVCA-02-01" trajera también "SOVCA-02-016" o
 * "SOVCA-02-011" (cualquier código que EMPIECE con el filtro), en vez
 * de traer solo los registros de ese animal exacto.
 */
function coincideAnimalExacto(valorCampo, valorFiltro) {
  return String(valorCampo || '').trim().toLowerCase() === String(valorFiltro || '').trim().toLowerCase();
}

/**
 * Igual que escaparHtml() de app.js: además de escapar el HTML corrige
 * los textos en MAYÚSCULAS con una vocal acentuada en minúscula
 * ("FERNANDO PéREZ", "NIñA"), para que el reporte impreso salga bien.
 * normalizarMayusculas() vive en app.js, que se carga DESPUÉS de este
 * archivo: la comprobación evita un error si se usa antes de tiempo.
 */
function htmlEscapar(valor) {
  const crudo = String(valor ?? '');
  const txt = typeof normalizarMayusculas === 'function'
    ? normalizarMayusculas(crudo)
    : crudo;
  return txt
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function obtenerNombreFundo(sesion) {
  const valor = (
    sesion?.usuario?.fundo_nombre ||
    sesion?.usuario?.fundo ||
    sesion?.fundo_nombre ||
    sesion?.fundo ||
    ''
  );
  return String(valor ?? '').trim() || '—';
}

function formatearFechaDDMMYYYY(fecha = new Date()) {
  const valor = new Date(fecha);
  if (Number.isNaN(valor.getTime())) return '—';
  const dia = String(valor.getDate()).padStart(2, '0');
  const mes = String(valor.getMonth() + 1).padStart(2, '0');
  const anio = valor.getFullYear();
  return `${dia}-${mes}-${anio}`;
}

function formatearFechaReporte(valor) {
  if (valor === null || valor === undefined || valor === '') return '—';
  const texto = String(valor).trim();
  if (!texto || texto === '—') return '—';
  if (/^\d{2}-\d{2}-\d{4}$/.test(texto)) return texto;

  // Los primeros 10 caracteres son "YYYY-MM-DD" venga la fecha como
  // solo-fecha ("2022-08-05"), en ISO con T ("2022-08-05T00:00:00") o
  // como datetime de servidor con espacio ("2022-08-05 00:00:00" —
  // este último es el que antes se colaba sin formatear: al no tener
  // "T", se le pegaba "T00:00:00" al final y quedaba una cadena
  // invalida tipo "2022-08-05 00:00:00T00:00:00", que Date() no podía
  // parsear y se devolvia tal cual, cruda, en el reporte).
  const soloFecha = texto.slice(0, 10);
  const fecha = new Date(`${soloFecha}T00:00:00`);
  if (Number.isNaN(fecha.getTime())) return texto;

  const dia = String(fecha.getDate()).padStart(2, '0');
  const mes = String(fecha.getMonth() + 1).padStart(2, '0');
  const anio = fecha.getFullYear();
  return `${dia}-${mes}-${anio}`;
}

const Reportes = {
  catalogo: REPORTES_CATALOG,

  async listar() {
    const sesion = await Db.obtenerSesion();
    return this.catalogo.map((reporte) => ({
      ...reporte,
      disponible: !!sesion,
    }));
  },

  normalizarFecha(valor) {
    if (!valor) return '';
    const fecha = new Date(valor + 'T00:00:00');
    if (Number.isNaN(fecha.getTime())) return '';
    return fecha.toISOString().slice(0, 10);
  },

  aplicarRangoFecha(datos, campo, desde, hasta) {
    const desdeNorm = this.normalizarFecha(desde);
    const hastaNorm = this.normalizarFecha(hasta);
    if (!desdeNorm && !hastaNorm) return datos;

    return datos.filter((fila) => {
      const valor = String(fila[campo] || '');
      if (!valor) return false;
      if (desdeNorm && valor < desdeNorm) return false;
      if (hastaNorm && valor > hastaNorm) return false;
      return true;
    });
  },

  async obtenerAnimalesActivosPorCodigo() {
    const animales = await Db.obtenerTodos('animales');
    return new Set(
      animales
        .filter((animal) => Number(animal.status ?? 1) > 0)
        .filter((animal) => Number(animal.mestatus_id ?? 1) === 1)
        .map((animal) => String(animal.codigo_rgb_id_animal || '').trim())
        .filter(Boolean)
    );
  },

  /**
   * Códigos de animales que SÍ están sincronizados localmente pero
   * están inactivos (de baja) — para excluirlos de reportes de
   * servicios/producción/gestación. A propósito NO es "todo lo que no
   * está en este set se excluye" (eso es obtenerAnimalesActivosPorCodigo,
   * para reportes que listan animales directamente) — acá es al revés:
   * si el animal ni siquiera bajó del servidor todavía, no hay forma de
   * saber que está de baja, así que su servicio/producción/gestación
   * real se sigue mostrando igual. Excluir de más dejaba reportes
   * vacíos con datos que sí existían (ver conversación: catálogo de
   * "Gestantes activas"/"Producción de Leche" mostraba códigos que
   * el reporte generado no encontraba).
   */
  async obtenerCodigosInactivosConocidos() {
    const animales = await Db.obtenerTodos('animales');
    return new Set(
      animales
        .filter((animal) => !(Number(animal.status ?? 1) > 0 && Number(animal.mestatus_id ?? 1) === 1))
        .map((animal) => String(animal.codigo_rgb_id_animal || '').trim())
        .filter(Boolean)
    );
  },

  async obtenerHembrasValidas() {
    return await this.obtenerAnimalesActivosPorCodigo();
  },

  async obtenerHembrasLecheValidas() {
    return await this.obtenerAnimalesActivosPorCodigo();
  },

  async obtenerNombreFundoActual(sesion) {
    if (!sesion) return '—';

    const nombreActual = (
      sesion?.usuario?.fundo_nombre ||
      sesion?.usuario?.fundo ||
      sesion?.fundo_nombre ||
      sesion?.fundo ||
      ''
    );

    if (String(nombreActual || '').trim()) {
      return String(nombreActual).trim();
    }

    if (!sesion.fundo_id || !navigator.onLine || !sesion.token) {
      return '—';
    }

    try {
      const resp = await Api.datosFundo(sesion.token, sesion.fundo_id);
      const nombreServidor = resp?.fundo?.nombre || '';
      if (nombreServidor) {
        sesion.fundo_nombre = nombreServidor;
        if (sesion.usuario) sesion.usuario.fundo_nombre = nombreServidor;
        await Db.guardarSesion(sesion);
      }
      return nombreServidor || '—';
    } catch (error) {
      console.warn('No se pudo obtener el nombre del fundo para el reporte:', error);
      return '—';
    }
  },

  async prepararReporte(code, filtros = {}) {
    const codigo = String(code || '');
    const sesion = await Db.obtenerSesion();
    if (!sesion) {
      throw new Error('Debes iniciar sesion antes de generar reportes offline.');
    }

    sesion.fundo_nombre = await this.obtenerNombreFundoActual(sesion);

    let datos = [];
    let titulo = 'Reporte';

    switch (codigo) {
      case 'animales_activos': {
        datos = await this.obtenerAnimalesActivos(filtros);
        titulo = 'Animales activos';
        break;
      }
      case 'resumen_servicios': {
        datos = await this.obtenerResumenServicios(filtros);
        titulo = 'Resumen de servicios';
        break;
      }
      case 'resumen_produccion_lechera': {
        datos = await this.obtenerResumenProduccionLechera(filtros);
        titulo = 'Resumen de produccion lechera';
        break;
      }
      case 'resumen_control_sanitario': {
        datos = await this.obtenerControlSanitario(filtros);
        titulo = 'Control sanitario';
        break;
      }
      case 'produccion_acumulada': {
        datos = await this.obtenerProduccionAcumulada(filtros);
        titulo = 'Produccion de Leche';
        break;
      }
      case 'servicios_por_tipo': {
        datos = await this.obtenerServiciosPorTipo(filtros);
        titulo = 'Servicios por tipo';
        break;
      }
      case 'gestantes_activas': {
        datos = await this.obtenerGestantesActivas(filtros);
        titulo = 'Gestantes activas';
        break;
      }
      case 'partos_confirmados': {
        datos = await this.obtenerPartosConfirmados(filtros);
        titulo = 'Partos confirmados';
        break;
      }
      case 'inventario_por_estado': {
        datos = await this.obtenerInventarioPorEstado(filtros);
        titulo = 'Inventario por estado animal';
        break;
      }
      case 'bajas_desincorporados': {
        datos = await this.obtenerBajasDesincorporados(filtros);
        titulo = 'Bajas y desincorporados';
        break;
      }
      case 'resumen_por_aptitud': {
        datos = await this.obtenerResumenPorAptitud(filtros);
        titulo = 'Resumen por aptitud';
        break;
      }
      case 'resumen_nacimientos': {
        datos = await this.obtenerResumenNacimientos(filtros);
        titulo = filtros.madre_con_partos ? 'Nacimientos por madre' : 'Informe de nacimientos';
        break;
      }
      case 'consecutivo_peso': {
        datos = await this.obtenerConsecutivoPeso(filtros);
        titulo = 'Consecutivo de peso';
        break;
      }
      case 'movimiento_rebano': {
        datos = await this.obtenerMovimientoRebano(filtros);
        titulo = 'Movimiento de rebano';
        break;
      }
      case 'grupos_etarios': {
        datos = await this.obtenerGruposEtarios(filtros);
        titulo = 'Grupos etarios';
        break;
      }
      case 'hembras_servidas': {
        datos = await this.obtenerHembrasServidas(filtros);
        titulo = 'Hembras servidas';
        break;
      }
      case 'productoras_leche': {
        datos = await this.obtenerProductorasLeche(filtros);
        titulo = 'Productoras de leche';
        break;
      }
      case 'animales_por_lote': {
        datos = await this.obtenerAnimalesPorLote(filtros);
        titulo = 'Animales por lote';
        break;
      }
      default:
        throw new Error('Reporte no soportado en esta version offline.');
    }

    const html = this.renderizarHtml(codigo, datos, titulo, sesion);
    return { codigo, titulo, datos, html, sesion };
  },

  /**
   * Abre una ventana con el HTML del reporte usando una URL real
   * (Blob), en vez de document.write() sobre una página en blanco.
   *
   * Por qué Blob: una ventana abierta con window.open('', ...) +
   * document.write() no tiene una URL propia que recargar — al
   * presionar "recargar" en esa ventana, el navegador terminaba
   * renavegando y volvía a cargar toda la SPA (el index.html), dejando
   * dos instancias de la app corriendo a la vez. Con una URL de Blob,
   * la ventana sí tiene un documento real y autocontenido: recargarla
   * vuelve a mostrar el mismo reporte (inofensivo), nunca la app
   * completa.
   *
   * Por qué UNA sola ventana reutilizada: acumular una ventana por
   * cada reporte abierto es fácil de perder de vista (sobre todo en
   * celular, donde cada una suele abrir como pestaña nueva), consume
   * memoria en equipos de gama media, y puede confundir sobre cuál
   * ventana tiene el dato más reciente. Si ya hay una vista de reporte
   * abierta y la persona no la cerró, se reutiliza (se renaveja a la
   * nueva URL) en vez de abrir otra.
   *
   * Al reutilizar, primero se pinta una pantalla breve de transición
   * ("Cambiando de reporte…") antes de navegar al nuevo — sin eso, la
   * ventana queda en blanco un instante mientras carga, y podría
   * parecer que algo se rompió.
   */
  _ventanaReporteActual: null,
  _blobUrlReporteActual: null,

  _htmlTransicionReporte(tituloNuevo) {
    return `<!doctype html>
      <html lang="es">
      <head>
        <meta charset="utf-8">
        <title>Cambiando de reporte…</title>
        <style>
          html, body { height: 100%; margin: 0; }
          body {
            display: flex; align-items: center; justify-content: center;
            background: #F1ECDD; font-family: Arial, sans-serif; color: #201D18;
          }
          .caja { text-align: center; padding: 24px; }
          .spinner {
            width: 34px; height: 34px; margin: 0 auto 16px;
            border: 4px solid #E4DCC6; border-top-color: #2F5233; border-radius: 50%;
            animation: girar 0.8s linear infinite;
          }
          @keyframes girar { to { transform: rotate(360deg); } }
          h2 { margin: 0 0 4px; font-size: 16px; color: #234e35; }
          p { margin: 0; font-size: 13px; color: #6b6455; }
        </style>
      </head>
      <body>
        <div class="caja">
          <div class="spinner"></div>
          <h2>Cambiando de reporte…</h2>
          <p>Preparando: ${htmlEscapar(tituloNuevo || 'reporte')}</p>
        </div>
      </body>
      </html>`;
  },

  abrirVentanaConHtml(html, titulo) {
    const ancho = 1200;
    const alto = 900;
    const anchoDisponible = window.screen?.availWidth || window.innerWidth || ancho;
    const altoDisponible = window.screen?.availHeight || window.innerHeight || alto;
    const izquierda = Math.max(0, Math.round((anchoDisponible - ancho) / 2));
    const arriba = Math.max(0, Math.round((altoDisponible - alto) / 2));

    const blob = new Blob([html], { type: 'text/html' });
    const url = URL.createObjectURL(blob);
    const blobUrlAnterior = this._blobUrlReporteActual;

    const asentarNuevaUrl = () => {
      this._blobUrlReporteActual = url;
      if (blobUrlAnterior) {
        // Respiro para que la navegación termine de asentarse antes
        // de invalidar la URL anterior.
        setTimeout(() => URL.revokeObjectURL(blobUrlAnterior), 3000);
      }
    };

    if (this._ventanaReporteActual && !this._ventanaReporteActual.closed) {
      const ventana = this._ventanaReporteActual;
      try { ventana.focus(); } catch (error) { /* no es critico */ }

      try {
        ventana.document.open();
        ventana.document.write(this._htmlTransicionReporte(titulo));
        ventana.document.close();
      } catch (error) {
        // Si por lo que sea no se puede pintar la transición, no pasa
        // nada — se navega igual abajo, solo se pierde el efecto.
      }

      // Se devuelve una promesa que resuelve DESPUÉS de la navegación
      // real al reporte nuevo — importante para generar(), que dispara
      // la impresión automática justo después: si no se esperara esto,
      // podría llegar a imprimir la pantalla de transición en vez del
      // reporte real.
      return new Promise((resolve) => {
        setTimeout(() => {
          ventana.location.href = url;
          asentarNuevaUrl();
          try { ventana.focus(); } catch (error) { /* no es critico */ }
          resolve(ventana);
        }, 250);
      });
    }

    this._ventanaReporteActual = window.open(url, '_blank', `width=${ancho},height=${alto},left=${izquierda},top=${arriba}`);
    if (!this._ventanaReporteActual) {
      URL.revokeObjectURL(url);
      throw new Error('El navegador bloqueo la ventana del reporte. Permite ventanas emergentes e intentalo de nuevo.');
    }
    try { this._ventanaReporteActual.focus(); } catch (error) { /* no es critico */ }
    asentarNuevaUrl();
    return Promise.resolve(this._ventanaReporteActual);
  },

  async abrirVistaPrevia(code, filtros = {}) {
    const clave = `vista-previa:${String(code || '')}`;
    if (window.__bovsReporteVistaPreviaEnCurso && window.__bovsReporteVistaPreviaEnCurso === clave) {
      return { codigo: String(code || ''), titulo: 'Vista previa en curso', filas: 0, html: '' };
    }
    window.__bovsReporteVistaPreviaEnCurso = clave;

    try {
      const { titulo, datos, html, sesion } = await this.prepararReporte(code, filtros);
      await this.abrirVentanaConHtml(html, titulo);

      await this.guardarCache({
        id: `${sesion.usuario_login || 'usuario'}:${code}:${Date.now()}`,
        usuario: sesion.usuario_login,
        fundo_id: sesion.fundo_id,
        codigo_reporte: code,
        titulo,
        datos,
        generado_en: new Date().toISOString(),
      });

      return { codigo, titulo, filas: datos.length, html };
    } finally {
      window.__bovsReporteVistaPreviaEnCurso = null;
    }
  },

  async generar(code, filtros = {}) {
    const { titulo, datos, html, sesion } = await this.prepararReporte(code, filtros);
    const ventana = await this.abrirVentanaConHtml(html, titulo);

    setTimeout(() => {
      try {
        ventana.print();
      } catch (error) {
        console.warn('No se pudo disparar la impresion automatica del PDF:', error);
      }
    }, 250);

    await this.guardarCache({
      id: `${sesion.usuario_login || 'usuario'}:${code}:${Date.now()}`,
      usuario: sesion.usuario_login,
      fundo_id: sesion.fundo_id,
      codigo_reporte: code,
      titulo,
      datos,
      generado_en: new Date().toISOString(),
    });

    return { codigo, titulo, filas: datos.length, html };
  },

  /**
   * Un solo reporte de Animales activos: devuelve el LISTADO (un
   * animal por fila) — el resumen por lote (antes un reporte aparte,
   * "Animales activos por lote") se calcula a partir de este mismo
   * listado dentro de renderizarHtml, igual que ya se hace con
   * Nacimientos y Control Sanitario.
   */
  async obtenerAnimalesActivos(filtros = {}) {
    const animales = await Db.obtenerTodos('animales');
    const lotes = await Db.obtenerCatalogo('lotes');
    const mapaLotes = new Map((lotes || []).map((lote) => [String(lote.id), lote.nombre || `Lote ${lote.id}`]));

    let resultado = animales.map((animal) => ({
      codigo: animal.codigo_rgb_id_animal || '—',
      nombre: animal.nombre || 'Sin nombre',
      arete: animal.nro_arete || '—',
      // OJO: en este sistema sexo 1 = HEMBRA y 2 = MACHO (igual que el
      // formulario del animal y la API). Antes esta línea los tenía
      // invertidos y el reporte mostraba las vacas como machos.
      sexo: this.etiquetaSexo(animal.sexo),
      estado: animal.estado_sync || 'sincronizado',
      sexoValor: Number(animal.sexo),
      loteId: animal.mlotes_id || null,
      lote: animal.mlotes_id
        ? (mapaLotes.get(String(animal.mlotes_id)) || `Lote ${animal.mlotes_id}`)
        : 'Sin lote',
    }));

    if (filtros.sexo) {
      resultado = resultado.filter((fila) => String(fila.sexoValor) === String(filtros.sexo));
    }
    if (filtros.lote_id) {
      resultado = resultado.filter((fila) => String(fila.loteId) === String(filtros.lote_id));
    }

    return resultado.sort((a, b) => a.codigo.localeCompare(b.codigo));
  },

  /**
   * Un solo reporte con dos modos, según si viene filtros.madre_con_partos:
   *
   * - Sin código de madre: reporte GENERAL (página 2 del PDF de
   *   referencia) — listado de partos de todas las hembras, filtrable
   *   por rango de fechas.
   * - Con código de madre: FICHA de esa hembra en particular (página 1
   *   del PDF) — sus totales/porcentajes/IEP promedio y el detalle de
   *   cada uno de sus partos. El código se elige con el buscador que
   *   ya solo lista hembras con al menos un parto registrado (ver
   *   inicializarBuscadorAnimales en app.js).
   */
  async obtenerResumenNacimientos(filtros = {}) {
    const codigoMadreFiltro = String(filtros.madre_con_partos || '').trim();

    const nacimientos = await Db.obtenerTodos('nacimientos');
    const animales = await Db.obtenerTodos('animales');
    const mapaAnimales = new Map(
      animales.map((animal) => [String(animal.codigo_rgb_id_animal || '').trim(), animal])
    );
    const tiposServicio = await Db.obtenerCatalogo('tipos_servicio');
    const mapaTiposServicio = new Map((tiposServicio || []).map((tipo) => [String(tipo.id), String(tipo.nombre || 'Sin tipo')]));

    const activos = nacimientos.filter((nacimiento) => Number(nacimiento.status ?? 1) === 1);

    // --- Modo "una sola madre" (ficha, página 1) ---
    if (codigoMadreFiltro) {
      const partosDeEstaMadre = activos
        .filter((nacimiento) => String(nacimiento.codigo_rgb_id_animal_hembra || '').trim() === codigoMadreFiltro)
        .sort((a, b) => String(a.f_parto || '').localeCompare(String(b.f_parto || '')));

      let filasMadre = partosDeEstaMadre.map((nacimiento, indice) => {
        const sexoValor = Number(nacimiento.sexo);
        const pesoValor = nacimiento.peso_nacimiento !== '' && nacimiento.peso_nacimiento != null
          ? Number(nacimiento.peso_nacimiento)
          : null;
        // Con la lista ya reducida a esta única madre y ordenada por
        // fecha, el IEP es contra la fila anterior de este mismo
        // arreglo (su historial completo, sin importar el filtro de
        // fechas — ver nota de IEP más abajo).
        const iep = indice > 0
          ? this.diasEntreFechas(nacimiento.f_parto, partosDeEstaMadre[indice - 1].f_parto)
          : null;

        return {
          fecha: nacimiento.f_parto || '—',
          nro_parto: nacimiento.nro_parto ?? '—',
          tipo_servicio: mapaTiposServicio.get(String(nacimiento.mtiposervicios_id)) || nacimiento.mtiposervicios_id || '—',
          nacio: Number(nacimiento.estatus_nacido) === 1 ? 'Vivo' : Number(nacimiento.estatus_nacido) === 2 ? 'Muerto' : '—',
          vivo: Number(nacimiento.estatus_nacido) === 1,
          sexoValor,
          sexo: sexoValor === 1 ? 'Macho' : sexoValor === 2 ? 'Hembra' : '—',
          peso: pesoValor !== null && !Number.isNaN(pesoValor) ? pesoValor : null,
          id_cria: nacimiento.id_ternero || nacimiento.codigo_ternero || '—',
          aptitud: nacimiento.aptitud || '—',
          iep,
        };
      });

      // El rango de fechas (si se puso) filtra cuáles partos se
      // MUESTRAN, pero el IEP de arriba ya se calculó contra el
      // historial completo de la madre — no se recalcula aquí.
      filasMadre = this.aplicarRangoFecha(filasMadre, 'fecha', filtros.fecha_desde, filtros.fecha_hasta);

      const madre = mapaAnimales.get(codigoMadreFiltro) || null;
      // La ficha viaja colgada del array (no como una fila más) para
      // que renderizarHtml arme el encabezado sin volver a consultar
      // Db — sigue disponible aunque la madre no tenga partos en el
      // rango elegido, gracias a que renderizarHtml ya no descarta
      // propiedades extra de arrays vacíos.
      filasMadre.ficha = {
        codigo: codigoMadreFiltro,
        arete: madre ? (madre.nro_arete || '—') : '—',
        nombre: madre ? (madre.nombre || '—') : '—',
        seleccionada: true,
      };
      filasMadre.modo = 'madre';
      return filasMadre;
    }

    // --- Modo general (listado de todas las madres, página 2) ---

    // IEP (Intervalo Entre Partos): días entre este parto y el parto
    // ANTERIOR de la MISMA madre. Se calcula sobre el historial
    // completo de cada madre (todos sus partos, no solo los que
    // queden dentro del filtro de fechas actual) — el intervalo es
    // una propiedad real del animal, no debería cambiar según qué
    // recorte de fechas se esté mirando en este momento. El primer
    // parto registrado de cada madre no tiene parto anterior, así que
    // queda sin IEP (columna vacía).
    const partosPorMadre = new Map();
    for (const nacimiento of activos) {
      const codigoMadre = String(nacimiento.codigo_rgb_id_animal_hembra || '').trim();
      if (!codigoMadre || !nacimiento.f_parto) continue;
      if (!partosPorMadre.has(codigoMadre)) partosPorMadre.set(codigoMadre, []);
      partosPorMadre.get(codigoMadre).push(nacimiento);
    }
    const mapaIep = new Map(); // uuid_local -> dias transcurridos desde el parto anterior de esa madre
    for (const partosDeMadre of partosPorMadre.values()) {
      partosDeMadre.sort((a, b) => String(a.f_parto).localeCompare(String(b.f_parto)));
      for (let i = 1; i < partosDeMadre.length; i++) {
        const dias = this.diasEntreFechas(partosDeMadre[i].f_parto, partosDeMadre[i - 1].f_parto);
        if (dias !== null) mapaIep.set(partosDeMadre[i].uuid_local, dias);
      }
    }

    let resultado = activos.map((nacimiento) => {
      const codigoMadre = String(nacimiento.codigo_rgb_id_animal_hembra || '').trim();
      const madre = mapaAnimales.get(codigoMadre);
      const sexoValor = Number(nacimiento.sexo);
      const pesoValor = nacimiento.peso_nacimiento !== '' && nacimiento.peso_nacimiento != null
        ? Number(nacimiento.peso_nacimiento)
        : null;

      return {
        codigo_madre: codigoMadre || '—',
        arete_madre: madre ? (madre.nro_arete || '—') : '—',
        fecha: nacimiento.f_parto || '—',
        nro_parto: nacimiento.nro_parto ?? '—',
        tipo_servicio: mapaTiposServicio.get(String(nacimiento.mtiposervicios_id)) || nacimiento.mtiposervicios_id || '—',
        nacio: Number(nacimiento.estatus_nacido) === 1 ? 'Vivo' : Number(nacimiento.estatus_nacido) === 2 ? 'Muerto' : '—',
        vivo: Number(nacimiento.estatus_nacido) === 1,
        sexoValor,
        sexo: sexoValor === 1 ? 'Macho' : sexoValor === 2 ? 'Hembra' : '—',
        peso: pesoValor !== null && !Number.isNaN(pesoValor) ? pesoValor : null,
        // "ID" del reporte: el código que la persona le asigna a mano
        // al ternero (id_ternero); si todavía no le puso uno, se
        // muestra el código que ya generó el sistema (codigo_ternero).
        id_cria: nacimiento.id_ternero || nacimiento.codigo_ternero || '—',
        iep: mapaIep.has(nacimiento.uuid_local) ? mapaIep.get(nacimiento.uuid_local) : null,
      };
    });

    resultado = this.aplicarRangoFecha(resultado, 'fecha', filtros.fecha_desde, filtros.fecha_hasta);
    resultado.sort((a, b) => String(a.fecha).localeCompare(String(b.fecha)));
    resultado.modo = 'general';
    return resultado;
  },

  diasEntreFechas(fechaReciente, fechaAnterior) {
    const a = new Date(String(fechaReciente).slice(0, 10) + 'T00:00:00');
    const b = new Date(String(fechaAnterior).slice(0, 10) + 'T00:00:00');
    if (Number.isNaN(a.getTime()) || Number.isNaN(b.getTime())) return null;
    return Math.round((a.getTime() - b.getTime()) / (24 * 60 * 60 * 1000));
  },

  async obtenerResumenServicios(filtros = {}) {
    const servicios = await Db.obtenerTodos('servicios');
    const codigosInactivos = await this.obtenerCodigosInactivosConocidos();
    const tiposServicio = await Db.obtenerCatalogo('tipos_servicio');
    const mapaTiposServicio = new Map((tiposServicio || []).map((tipo) => [String(tipo.id), String(tipo.nombre || 'Sin tipo')]));

    let resultado = servicios
      .filter((servicio) => Number(servicio.status ?? 1) === 1)
      .filter((servicio) => {
        const codigo = String(servicio.codigo_rgb_id_animal_hembra || '').trim();
        return codigo && !codigosInactivos.has(codigo);
      })
      .map((servicio) => ({
        hembra: servicio.codigo_rgb_id_animal_hembra || '—',
        macho: servicio.codigo_rgb_id_animal_macho || '—',
        fecha: servicio.f_servicio || '—',
        tipo: mapaTiposServicio.get(String(servicio.mservicios_id)) || servicio.mservicios_id || '—',
        observaciones: servicio.observaciones || '—',
      }));

    resultado = this.aplicarRangoFecha(resultado, 'fecha', filtros.fecha_desde, filtros.fecha_hasta);
    if (filtros.hembra) {
      resultado = resultado.filter((fila) => coincideAnimalExacto(fila.hembra, filtros.hembra));
    }
    if (filtros.macho) {
      resultado = resultado.filter((fila) => coincideAnimalExacto(fila.macho, filtros.macho));
    }
    if (filtros.tipo_servicio) {
      resultado = resultado.filter((fila) => String(fila.tipo) === String(filtros.tipo_servicio));
    }

    return resultado.sort((a, b) => (a.fecha || '').localeCompare(b.fecha || ''));
  },

  async obtenerResumenProduccionLechera(filtros = {}) {
    const registros = await Db.obtenerTodos('produccion_leche');
    const codigosInactivos = await this.obtenerCodigosInactivosConocidos();

    let resultado = registros
      .filter((registro) => Number(registro.status ?? 1) === 1)
      .filter((registro) => {
        const codigo = String(registro.codigo_rgb_id_animal_hembra || '').trim();
        return codigo && !codigosInactivos.has(codigo);
      })
      .map((registro) => ({
        animal: registro.codigo_rgb_id_animal_hembra || 'Sin animal',
        fecha: registro.fecha_produccion || '—',
        manana: Number(registro.cantidad_leche_m || 0),
        tarde: Number(registro.cantidad_leche_t || 0),
        total: Number(registro.cantidad_leche_m || 0) + Number(registro.cantidad_leche_t || 0),
      }));

    resultado = this.aplicarRangoFecha(resultado, 'fecha', filtros.fecha_desde, filtros.fecha_hasta);
    if (filtros.hembra) {
      resultado = resultado.filter((fila) => coincideAnimalExacto(fila.animal, filtros.hembra));
    }

    return resultado.sort((a, b) => b.total - a.total);
  },

  /**
   * Un solo reporte de Control Sanitario: devuelve el DETALLE (un
   * registro por control, con los días hasta el próximo) — el resumen
   * por animal (antes un reporte aparte, "Resumen de control
   * sanitario") se calcula a partir de este mismo detalle dentro de
   * renderizarHtml, igual que ya se hace con Nacimientos. Antes eran
   * dos reportes que mostraban lo mismo mirado de dos formas — y de
   * paso el resumen tenía un bug: "último control" tomaba la fecha de
   * la ÚLTIMA FILA RECORRIDA (según el orden en que IndexedDB los
   * devuelve, no necesariamente cronológico), no la fecha realmente
   * más reciente. Al calcularlo ahora ordenando primero, queda bien.
   */
  async obtenerControlSanitario(filtros = {}) {
    const registros = await Db.obtenerTodos('control_sanitario');
    let resultado = registros
      .filter((registro) => Number(registro.status ?? 1) === 1)
      .map((registro) => ({
        animal: registro.codigo_rgb_id_animal || 'Sin animal',
        evento: registro.evento || '—',
        fecha_control: registro.fecha_control || '—',
        proximo: registro.fecha_prox_control || '—',
        rango_dias: null,
      }));

    resultado = this.aplicarRangoFecha(resultado, 'fecha_control', filtros.fecha_desde, filtros.fecha_hasta);
    if (filtros.animal) {
      resultado = resultado.filter((fila) => coincideAnimalExacto(fila.animal, filtros.animal));
    }

    resultado = resultado.map((fila) => {
      if (fila.proximo && fila.fecha_control && fila.proximo !== '—' && fila.fecha_control !== '—') {
        const inicio = new Date(fila.fecha_control + 'T00:00:00');
        const fin = new Date(fila.proximo + 'T00:00:00');
        const diff = Math.round((fin - inicio) / (1000 * 60 * 60 * 24));
        fila.rango_dias = diff > 0 ? diff : 0;
      }
      return fila;
    });

    return resultado.sort((a, b) => String(a.fecha_control || '').localeCompare(String(b.fecha_control || '')));
  },


  async obtenerResumenLotes(filtros = {}) {
    const animales = await Db.obtenerTodos('animales');
    let resultado = animales.filter((animal) => {
      if (filtros.sexo && String(animal.sexo) !== String(filtros.sexo)) return false;
      if (filtros.lote_id && String(animal.mlotes_id || '') !== String(filtros.lote_id)) return false;
      return true;
    });

    const mapa = new Map();
    for (const animal of resultado) {
      const loteKey = animal.mlotes_id ? `Lote ${animal.mlotes_id}` : 'Sin lote';
      if (!mapa.has(loteKey)) {
        mapa.set(loteKey, {
          lote: loteKey,
          total: 0,
          machos: 0,
          hembras: 0,
          activos: 0,
        });
      }
      const fila = mapa.get(loteKey);
      fila.total += 1;
      if (Number(animal.sexo) === 1) fila.machos += 1;
      if (Number(animal.sexo) === 2) fila.hembras += 1;
      fila.activos += 1;
    }

    return [...mapa.values()].sort((a, b) => b.total - a.total);
  },

  async obtenerProduccionAcumulada(filtros = {}) {
    const registros = await Db.obtenerTodos('produccion_leche');
    const codigosInactivos = await this.obtenerCodigosInactivosConocidos();

    let resultado = registros
      .filter((registro) => Number(registro.status ?? 1) === 1)
      .filter((registro) => {
        const codigo = String(registro.codigo_rgb_id_animal_hembra || '').trim();
        return codigo && !codigosInactivos.has(codigo);
      })
      .map((registro) => ({
        animal: registro.codigo_rgb_id_animal_hembra || 'Sin animal',
        fecha: registro.fecha_produccion || '—',
        manana: Number(registro.cantidad_leche_m || 0),
        tarde: Number(registro.cantidad_leche_t || 0),
        total: Number(registro.cantidad_leche_m || 0) + Number(registro.cantidad_leche_t || 0),
      }));

    resultado = this.aplicarRangoFecha(resultado, 'fecha', filtros.fecha_desde, filtros.fecha_hasta);
    if (filtros.hembra) {
      resultado = resultado.filter((fila) => coincideAnimalExacto(fila.animal, filtros.hembra));
    }

    const mapa = new Map();
    for (const fila of resultado) {
      const key = String(fila.animal);
      if (!mapa.has(key)) {
        mapa.set(key, {
          animal: fila.animal,
          fecha_inicio: fila.fecha,
          total: 0,
          manana: 0,
          tarde: 0,
          registros: 0,
        });
      }
      const actual = mapa.get(key);
      actual.total += fila.total;
      actual.manana += fila.manana;
      actual.tarde += fila.tarde;
      actual.registros += 1;
      if (fila.fecha && (!actual.fecha_inicio || fila.fecha < actual.fecha_inicio)) {
        actual.fecha_inicio = fila.fecha;
      }
    }

    return [...mapa.values()].sort((a, b) => b.total - a.total);
  },

  async obtenerServiciosPorTipo(filtros = {}) {
    const servicios = await Db.obtenerTodos('servicios');
    const tiposServicio = await Db.obtenerCatalogo('tipos_servicio');
    const mapaTiposServicio = new Map((tiposServicio || []).map((tipo) => [String(tipo.id), String(tipo.nombre || 'Sin tipo')]));

    let resultado = servicios.map((servicio) => ({
      tipo: mapaTiposServicio.get(String(servicio.mservicios_id)) || servicio.mservicios_id || '—',
      fecha: servicio.f_servicio || '—',
      hembra: servicio.codigo_rgb_id_animal_hembra || '—',
      macho: servicio.codigo_rgb_id_animal_macho || '—',
      cantidad: 1,
    }));

    resultado = this.aplicarRangoFecha(resultado, 'fecha', filtros.fecha_desde, filtros.fecha_hasta);
    if (filtros.hembra) {
      resultado = resultado.filter((fila) => coincideAnimalExacto(fila.hembra, filtros.hembra));
    }
    if (filtros.macho) {
      resultado = resultado.filter((fila) => coincideAnimalExacto(fila.macho, filtros.macho));
    }
    if (filtros.tipo_servicio) {
      resultado = resultado.filter((fila) => String(fila.tipo) === String(filtros.tipo_servicio));
    }

    const mapa = new Map();
    for (const fila of resultado) {
      const key = String(fila.tipo);
      if (!mapa.has(key)) {
        mapa.set(key, {
          tipo: fila.tipo,
          total: 0,
          fecha_ultimo: fila.fecha,
        });
      }
      const actual = mapa.get(key);
      actual.total += fila.cantidad;
      if (fila.fecha && (!actual.fecha_ultimo || fila.fecha > actual.fecha_ultimo)) {
        actual.fecha_ultimo = fila.fecha;
      }
    }

    return [...mapa.values()].sort((a, b) => b.total - a.total);
  },

  async obtenerGestantesActivas(filtros = {}) {
    const servicios = await Db.obtenerTodos('servicios');
    const codigosInactivos = await this.obtenerCodigosInactivosConocidos();
    let resultado = servicios
      .filter((servicio) => Number(servicio.status ?? 1) === 1)
      .filter((servicio) => {
        const codigo = String(servicio.codigo_rgb_id_animal_hembra || '').trim();
        return codigo && !codigosInactivos.has(codigo) && Number(servicio.medofisiologicos_id) === 1 && Number(servicio.parto || 0) === 0;
      })
      .map((servicio) => ({
        hembra: servicio.codigo_rgb_id_animal_hembra || '—',
        servicio: servicio.codigo_servicio || '—',
        fecha_servicio: servicio.f_servicio || '—',
        fecha_probable_parto: servicio.f_probable_parto || '—',
        ultimo_parto: servicio.f_ultimo_parto || '—',
        estado_fisiologico: servicio.medofisiologicos_id || '—',
      }));

    resultado = this.aplicarRangoFecha(resultado, 'fecha_servicio', filtros.fecha_desde, filtros.fecha_hasta);
    if (filtros.hembra) {
      resultado = resultado.filter((fila) => coincideAnimalExacto(fila.hembra, filtros.hembra));
    }

    return resultado.sort((a, b) => (a.fecha_servicio || '').localeCompare(b.fecha_servicio || ''));
  },

  async obtenerPartosConfirmados(filtros = {}) {
    const servicios = await Db.obtenerTodos('servicios');
    const codigosInactivos = await this.obtenerCodigosInactivosConocidos();
    let resultado = servicios
      .filter((servicio) => Number(servicio.status ?? 1) === 1)
      .filter((servicio) => {
        const codigo = String(servicio.codigo_rgb_id_animal_hembra || '').trim();
        return codigo && !codigosInactivos.has(codigo) && Number(servicio.medofisiologicos_id) === 1 && Number(servicio.parto || 0) === 1;
      })
      .map((servicio) => ({
        hembra: servicio.codigo_rgb_id_animal_hembra || '—',
        servicio: servicio.codigo_servicio || '—',
        fecha_parto: servicio.fecha_parto || '—',
        fecha_servicio: servicio.f_servicio || '—',
        nro_terneros: servicio.nro_terneros || 0,
        dnp: servicio.dnp || 0,
      }));

    resultado = this.aplicarRangoFecha(resultado, 'fecha_parto', filtros.fecha_desde, filtros.fecha_hasta);
    if (filtros.hembra) {
      resultado = resultado.filter((fila) => coincideAnimalExacto(fila.hembra, filtros.hembra));
    }

    return resultado.sort((a, b) => (a.fecha_parto || '').localeCompare(b.fecha_parto || ''));
  },

  async obtenerInventarioPorEstado(filtros = {}) {
    const animales = await Db.obtenerTodos('animales');
    let resultado = animales.filter((animal) => {
      if (filtros.sexo && String(animal.sexo) !== String(filtros.sexo)) return false;
      if (filtros.estado_animal && String(animal.estatus_animal || '') !== String(filtros.estado_animal)) return false;
      return true;
    }).map((animal) => ({
      estado: animal.estatus_animal ? this.etiquetaEstadoAnimal(Number(animal.estatus_animal)) : 'Sin estado',
      sexo: this.etiquetaSexo(animal.sexo),
      total: 1,
    }));

    const mapa = new Map();
    for (const fila of resultado) {
      const key = `${fila.estado}|${fila.sexo}`;
      if (!mapa.has(key)) {
        mapa.set(key, { estado: fila.estado, sexo: fila.sexo, total: 0 });
      }
      mapa.get(key).total += fila.total;
    }

    return [...mapa.values()].sort((a, b) => a.estado.localeCompare(b.estado) || a.sexo.localeCompare(b.sexo));
  },

  etiquetaEstadoAnimal(valor) {
    const mapa = {
      1: 'Activo',
      2: 'Donador Activo',
      3: 'Donador',
      4: 'Donadora Activo',
      5: 'Donadora',
    };
    return mapa[Number(valor)] || `Estado ${valor || 'sin dato'}`;
  },

  /**
   * En este sistema (igual que el formulario del animal y el API)
   * sexo 1 = HEMBRA y sexo 2 = MACHO. Varios reportes lo tenían al
   * revés y mostraban las vacas como machos.
   */
  etiquetaSexo(valor) {
    const numero = Number(valor);
    if (numero === 1) return 'Hembra';
    if (numero === 2) return 'Macho';
    return '—';
  },

  /**
   * Tipos de baja reales (ids de mestatusganados). El 7 lo aplica el
   * módulo de migración: no se elige en campo, pero sí se reporta.
   */
  TIPOS_BAJA: {
    2: 'Descarte',
    3: 'Muerte',
    4: 'Venta',
    5: 'Extravío',
    7: 'Migrado a otro fundo',
  },

  etiquetaTipoBaja(valor) {
    return this.TIPOS_BAJA[Number(valor)] || `Baja ${valor || 'sin dato'}`;
  },

  etiquetaAptitud(valor) {
    const mapa = {
      1: 'Carne',
      2: 'Leche',
      3: 'Doble proposito',
      4: 'Reproductor',
    };
    return mapa[Number(valor)] || `Aptitud ${valor || 'sin dato'}`;
  },

  /**
   * Bajas y desincorporados.
   *
   * ANTES este reporte "adivinaba" la baja con `estatus_animal` (4/5) y
   * una expresión regular sobre las observaciones. En este sistema
   * estatus_animal 4/5 son DONADORA ACTIVO / DONADORA — no bajas — así
   * que el reporte mezclaba donadoras con animales muertos y no traía
   * el motivo real. La baja vive en `mestatus_id` (2 descarte, 3 muerte,
   * 4 venta, 5 extravío, 7 migrado) y su detalle en el almacén
   * `desincorporacion` (tipo, fecha, causa y observaciones).
   */
  async obtenerBajasDesincorporados(filtros = {}) {
    const animales = await Db.obtenerTodos('animales');
    const bajas = await Db.obtenerTodos('desincorporacion');
    const lotes = await Db.obtenerCatalogo('lotes');
    const catalogoCausas = await catalogosCausasBaja();

    // Una baja por código: si hubiera más de una, vale la más reciente.
    const bajaPorCodigo = new Map();
    for (const baja of bajas) {
      const codigo = String(baja.codigo_rgb_id_animal || '').trim().toUpperCase();
      if (!codigo) continue;
      const fecha = String(baja.f_desincorporacion || baja.created || '');
      const previa = bajaPorCodigo.get(codigo);
      if (!previa || fecha > String(previa.f_desincorporacion || previa.created || '')) {
        bajaPorCodigo.set(codigo, baja);
      }
    }

    const nombreLote = (id) => {
      const lote = lotes.find((item) => String(item.id) === String(id));
      if (lote) return lote.nombre;
      return id ? `Lote ${id}` : 'Sin lote';
    };

    let resultado = animales
      .filter((animal) => {
        const tipo = Number(animal.mestatus_id ?? 1);
        if (!this.TIPOS_BAJA[tipo]) return false;
        if (filtros.sexo && String(animal.sexo) !== String(filtros.sexo)) return false;
        if (filtros.lote_id && String(animal.mlotes_id || '') !== String(filtros.lote_id)) return false;
        if (filtros.estado_animal && String(tipo) !== String(filtros.estado_animal)) return false;
        return true;
      })
      .map((animal) => {
        const codigo = String(animal.codigo_rgb_id_animal || '').trim();
        const baja = bajaPorCodigo.get(codigo.toUpperCase());
        const tipo = Number(animal.mestatus_id);
        const causa = baja ? causaBajaTexto(baja, catalogoCausas) : '';

        return {
          codigo: codigo || '—',
          nombre: animal.nombre || 'Sin nombre',
          sexo: this.etiquetaSexo(animal.sexo),
          tipo: this.etiquetaTipoBaja(tipo),
          tipoValor: tipo,
          fecha: (baja && baja.f_desincorporacion) || '',
          causa: causa || '—',
          lote: nombreLote(animal.mlotes_id),
          // Solo hay registro de la baja cuando la hizo la app o cuando
          // la sincronización ya la trajo. Si el animal llegó al
          // dispositivo ya dado de baja desde la web, solo se conoce su
          // estado (sin fecha ni causa).
          conRegistro: Boolean(baja),
          observaciones: (baja && baja.observaciones) || animal.observaciones || '—',
        };
      });

    if (filtros.animal) {
      resultado = resultado.filter((fila) => coincideAnimalExacto(fila.codigo, filtros.animal)
        || coincideAnimalExacto(fila.nombre, filtros.animal));
    }

    return resultado.sort((a, b) => a.tipo.localeCompare(b.tipo) || a.codigo.localeCompare(b.codigo));
  },

  async obtenerResumenPorAptitud(filtros = {}) {
    const animales = await Db.obtenerTodos('animales');
    let resultado = animales.filter((animal) => {
      if (filtros.sexo && String(animal.sexo) !== String(filtros.sexo)) return false;
      if (filtros.aptitud && String(animal.aptitud || '') !== String(filtros.aptitud)) return false;
      return true;
    }).map((animal) => ({
      aptitud: this.etiquetaAptitud(animal.aptitud),
      sexo: this.etiquetaSexo(animal.sexo),
      total: 1,
    }));

    const mapa = new Map();
    for (const fila of resultado) {
      const key = `${fila.aptitud}|${fila.sexo}`;
      if (!mapa.has(key)) {
        mapa.set(key, { aptitud: fila.aptitud, sexo: fila.sexo, total: 0 });
      }
      mapa.get(key).total += fila.total;
    }

    return [...mapa.values()].sort((a, b) => a.aptitud.localeCompare(b.aptitud) || a.sexo.localeCompare(b.sexo));
  },

  /**
   * CONSECUTIVO DE PESO (equivalente offline de `rcp`/`pdfconsecutivo_peso`).
   *
   * Cada pesaje de cada animal con el peso ANTERIOR del mismo animal,
   * la diferencia y los días transcurridos (y la ganancia media diaria).
   * El "anterior" se toma del historial COMPLETO del animal —igual que
   * el IEP de nacimientos— y después el rango de fechas solo decide
   * qué filas se muestran.
   */
  async obtenerConsecutivoPeso(filtros = {}) {
    const pesos = (await Db.obtenerTodos('pesos'))
      .filter((registro) => Number(registro.status ?? 1) === 1)
      .map((registro) => ({
        animal: String(registro.codigo_rgb_id_animal || '').trim(),
        fecha: String(registro.fecha_pesaje || '').slice(0, 10),
        peso: registro.peso === '' || registro.peso == null ? null : Number(registro.peso),
      }))
      .filter((fila) => fila.animal && fila.fecha && fila.peso !== null && !Number.isNaN(fila.peso));

    const porAnimal = new Map();
    for (const fila of pesos) {
      if (!porAnimal.has(fila.animal)) porAnimal.set(fila.animal, []);
      porAnimal.get(fila.animal).push(fila);
    }

    const filas = [];
    for (const [animal, lista] of porAnimal) {
      lista.sort((a, b) => a.fecha.localeCompare(b.fecha));
      let pesoAnterior = null;
      let fechaAnterior = null;

      for (const fila of lista) {
        const dias = fechaAnterior ? this.diasEntreFechas(fila.fecha, fechaAnterior) : null;
        const diferencia = pesoAnterior !== null
          ? Math.round((fila.peso - pesoAnterior) * 100) / 100
          : null;
        filas.push({
          animal,
          fecha: fila.fecha,
          peso: fila.peso,
          anterior: pesoAnterior,
          diferencia,
          dias,
          gmd: diferencia !== null && dias ? Math.round((diferencia / dias) * 1000) / 1000 : null,
        });
        pesoAnterior = fila.peso;
        fechaAnterior = fila.fecha;
      }
    }

    let resultado = this.aplicarRangoFecha(filas, 'fecha', filtros.fecha_desde, filtros.fecha_hasta);
    if (filtros.animal) {
      resultado = resultado.filter((fila) => coincideAnimalExacto(fila.animal, filtros.animal));
    }

    return resultado.sort((a, b) => a.animal.localeCompare(b.animal) || a.fecha.localeCompare(b.fecha));
  },

  /** MOVIMIENTO DE REBAÑO: traslados con origen, destino y reversión. */
  async obtenerMovimientoRebano(filtros = {}) {
    const movimientos = await Db.obtenerTodos('movimiento_rebano');
    const lotes = await Db.obtenerCatalogo('lotes');
    const mapaLotes = new Map((lotes || []).map((lote) => [String(lote.id), lote.nombre || `Lote ${lote.id}`]));
    const tipos = typeof MovimientoRebano !== 'undefined' ? MovimientoRebano : null;

    const filas = movimientos.map((mov) => {
      const animales = Array.isArray(mov.animales) ? mov.animales : [];
      const loteOrigen = mov.lote_origen_nombre
        || (mov.mlotes_origen_id ? mapaLotes.get(String(mov.mlotes_origen_id)) : '');
      const loteDestino = mov.lote_destino_nombre
        || (mov.mlotes_destino_id ? mapaLotes.get(String(mov.mlotes_destino_id)) : '');

      return {
        fecha: String(mov.fecha_movimiento || '').slice(0, 10),
        tipo: tipos ? (tipos.nombreTipo(mov.tipo_movimiento) || mov.tipo_movimiento || '—') : (mov.tipo_movimiento || '—'),
        origen: [loteOrigen, mov.potrero_origen_nombre].filter(Boolean).join(' · ') || '—',
        destino: [loteDestino, mov.potrero_destino_nombre].filter(Boolean).join(' · ') || '—',
        total: Number(mov.total_animales) || animales.length,
        codigos: animales.join(' '),
        motivo: mov.motivo || '—',
        revertidoValor: mov.revertido === true,
        revertido: mov.revertido === true
          ? ('Si' + (mov.motivo_reversion ? ' — ' + mov.motivo_reversion : ''))
          : 'No',
        loteOrigenId: mov.mlotes_origen_id || null,
        loteDestinoId: mov.mlotes_destino_id || null,
      };
    });

    let resultado = this.aplicarRangoFecha(filas, 'fecha', filtros.fecha_desde, filtros.fecha_hasta);
    if (filtros.lote_id) {
      resultado = resultado.filter((fila) => String(fila.loteOrigenId) === String(filtros.lote_id)
        || String(fila.loteDestinoId) === String(filtros.lote_id));
    }

    return resultado.sort((a, b) => b.fecha.localeCompare(a.fecha));
  },

  /** GRUPOS ETARIOS: animales activos por edad en meses. */
  async obtenerGruposEtarios(filtros = {}) {
    const animales = await Db.obtenerTodos('animales');
    const categorias = await Db.obtenerCatalogo('categorias');
    const mapaCategorias = new Map((categorias || []).map((cat) => [String(cat.id), cat]));

    let filas = animales
      .filter((animal) => Number(animal.status ?? 1) === 1 && Number(animal.mestatus_id ?? 1) === 1)
      .map((animal) => {
        const meses = this.mesesCumplidos(animal.f_nacimiento);
        const categoria = animal.categoria_ingreso_id
          ? mapaCategorias.get(String(animal.categoria_ingreso_id))
          : null;

        return {
          codigo: animal.codigo_rgb_id_animal || '—',
          nombre: animal.nombre || '—',
          arete: animal.nro_arete || '—',
          sexo: this.etiquetaSexo(animal.sexo),
          sexoValor: Number(animal.sexo),
          nacimiento: String(animal.f_nacimiento || '').slice(0, 10),
          meses,
          grupo: this.grupoEtario(meses),
          categoria: categoria
            ? categoria.nombre
            : (animal.categoria_ingreso_id ? `Categoria ${animal.categoria_ingreso_id}` : '—'),
        };
      });

    if (filtros.sexo) {
      filas = filas.filter((fila) => String(fila.sexoValor) === String(filtros.sexo));
    }

    return filas.sort((a, b) => a.grupo.localeCompare(b.grupo) || a.codigo.localeCompare(b.codigo));
  },

  /** Meses cumplidos desde una fecha de nacimiento (null si no hay fecha). */
  mesesCumplidos(fechaNacimiento) {
    if (!fechaNacimiento) return null;
    const nacimiento = new Date(String(fechaNacimiento).slice(0, 10) + 'T00:00:00');
    if (Number.isNaN(nacimiento.getTime())) return null;

    const hoy = new Date();
    let meses = (hoy.getFullYear() - nacimiento.getFullYear()) * 12 + (hoy.getMonth() - nacimiento.getMonth());
    if (hoy.getDate() < nacimiento.getDate()) meses -= 1;
    return meses < 0 ? 0 : meses;
  },

  /** Tramo de edad (en meses) al que pertenece un animal. */
  grupoEtario(meses) {
    if (meses === null || meses === undefined) return 'Sin fecha de nacimiento';
    if (meses <= 3) return '0 a 3 meses';
    if (meses <= 8) return '4 a 8 meses';
    if (meses <= 12) return '9 a 12 meses';
    if (meses <= 24) return '13 a 24 meses';
    if (meses <= 36) return '25 a 36 meses';
    if (meses <= 48) return '37 a 48 meses';
    if (meses <= 72) return '49 a 72 meses';
    return 'Mas de 72 meses';
  },

  /** HEMBRAS SERVIDAS: servicios del rango con tipo, macho y parto. */
  async obtenerHembrasServidas(filtros = {}) {
    const servicios = await Db.obtenerTodos('servicios');
    const tipos = await Db.obtenerCatalogo('tipos_servicio');
    const mapaTipos = new Map((tipos || []).map((tipo) => [String(tipo.id), tipo.nombre || `Tipo ${tipo.id}`]));
    const hoy = new Date().toISOString().slice(0, 10);

    const filas = servicios
      .filter((servicio) => Number(servicio.status ?? 1) === 1)
      .map((servicio) => {
        const fecha = String(servicio.f_servicio || '').slice(0, 10);
        return {
          hembra: String(servicio.codigo_rgb_id_animal_hembra || '').trim() || '—',
          fecha,
          tipo: mapaTipos.get(String(servicio.mservicios_id))
            || (servicio.mservicios_id ? `Tipo ${servicio.mservicios_id}` : '—'),
          tipoValor: servicio.mservicios_id ? String(servicio.mservicios_id) : '',
          macho: servicio.codigo_rgb_id_animal_macho
            || (servicio.codigo_rgb_id_animal_dona ? `Donadora ${servicio.codigo_rgb_id_animal_dona}` : '—'),
          nro: servicio.nro_servicio ?? '—',
          parto: Number(servicio.parto) === 1 ? 'Si' : 'No',
          partoValor: Number(servicio.parto) === 1,
          dias: fecha ? this.diasEntreFechas(hoy, fecha) : null,
          observaciones: servicio.observaciones || '',
        };
      });

    let resultado = this.aplicarRangoFecha(filas, 'fecha', filtros.fecha_desde, filtros.fecha_hasta);
    if (filtros.hembra) {
      resultado = resultado.filter((fila) => coincideAnimalExacto(fila.hembra, filtros.hembra));
    }
    if (filtros.tipo_servicio) {
      resultado = resultado.filter((fila) => String(fila.tipoValor) === String(filtros.tipo_servicio));
    }

    return resultado.sort((a, b) => b.fecha.localeCompare(a.fecha) || a.hembra.localeCompare(b.hembra));
  },

  /** PRODUCTORAS DE LECHE: litros por hembra en el rango de fechas. */
  async obtenerProductorasLeche(filtros = {}) {
    let registros = (await Db.obtenerTodos('produccion_leche'))
      .filter((registro) => Number(registro.status ?? 1) === 1)
      .map((registro) => ({
        animal: String(registro.codigo_rgb_id_animal_hembra || '').trim() || '—',
        fecha: String(registro.fecha_produccion || '').slice(0, 10),
        manana: Number(registro.cantidad_leche_m || 0),
        tarde: Number(registro.cantidad_leche_t || 0),
      }));

    registros = this.aplicarRangoFecha(registros, 'fecha', filtros.fecha_desde, filtros.fecha_hasta);
    if (filtros.hembra) {
      registros = registros.filter((fila) => coincideAnimalExacto(fila.animal, filtros.hembra));
    }

    const porAnimal = new Map();
    for (const fila of registros) {
      if (!porAnimal.has(fila.animal)) {
        porAnimal.set(fila.animal, {
          animal: fila.animal,
          dias: new Set(),
          manana: 0,
          tarde: 0,
          primera: fila.fecha,
          ultima: fila.fecha,
        });
      }
      const acumulado = porAnimal.get(fila.animal);
      if (fila.fecha) acumulado.dias.add(fila.fecha);
      acumulado.manana += fila.manana;
      acumulado.tarde += fila.tarde;
      if (fila.fecha && fila.fecha < acumulado.primera) acumulado.primera = fila.fecha;
      if (fila.fecha && fila.fecha > acumulado.ultima) acumulado.ultima = fila.fecha;
    }

    const redondear = (valor) => Math.round(valor * 100) / 100;

    return [...porAnimal.values()]
      .map((acumulado) => {
        const total = acumulado.manana + acumulado.tarde;
        const dias = acumulado.dias.size;
        return {
          animal: acumulado.animal,
          dias,
          manana: redondear(acumulado.manana),
          tarde: redondear(acumulado.tarde),
          total: redondear(total),
          promedio: dias ? redondear(total / dias) : 0,
          primera: acumulado.primera,
          ultima: acumulado.ultima,
        };
      })
      .sort((a, b) => b.total - a.total);
  },

  /** ANIMALES POR LOTE: listado de activos con su lote. */
  async obtenerAnimalesPorLote(filtros = {}) {
    const animales = await Db.obtenerTodos('animales');
    const lotes = await Db.obtenerCatalogo('lotes');
    const mapaLotes = new Map((lotes || []).map((lote) => [String(lote.id), lote.nombre || `Lote ${lote.id}`]));

    let filas = animales
      .filter((animal) => Number(animal.status ?? 1) === 1 && Number(animal.mestatus_id ?? 1) === 1)
      .map((animal) => ({
        loteId: animal.mlotes_id || null,
        lote: animal.mlotes_id
          ? (mapaLotes.get(String(animal.mlotes_id)) || `Lote ${animal.mlotes_id}`)
          : 'Sin lote',
        codigo: animal.codigo_rgb_id_animal || '—',
        nombre: animal.nombre || '—',
        arete: animal.nro_arete || '—',
        sexo: this.etiquetaSexo(animal.sexo),
        sexoValor: Number(animal.sexo),
        estatus: animal.estatus_animal ? this.etiquetaEstadoAnimal(animal.estatus_animal) : '—',
      }));

    if (filtros.sexo) {
      filas = filas.filter((fila) => String(fila.sexoValor) === String(filtros.sexo));
    }
    if (filtros.lote_id) {
      filas = filas.filter((fila) => String(fila.loteId) === String(filtros.lote_id));
    }

    return filas.sort((a, b) => a.lote.localeCompare(b.lote) || a.codigo.localeCompare(b.codigo));
  },

  async guardarCache(fila) {
    try {
      await Db.guardar('reportes_cache', fila);
    } catch (error) {
      console.warn('No se pudo guardar el cache del reporte offline:', error);
    }
  },

  async listarCache() {
    try {
      return await Db.obtenerTodos('reportes_cache');
    } catch (error) {
      console.warn('No se pudo leer el cache de reportes:', error);
      return [];
    }
  },

  /* ---------------------------------------------------------------- */
  /* PDF real en el equipo (sin diálogo de impresión)                  */
  /* ---------------------------------------------------------------- */

  // Claves internas que no se muestran como columna en el PDF.
  CAMPOS_NO_PDF: new Set([
    'sexoValor', 'loteId', 'vivo', 'partoValor', 'revertidoValor',
    'tipoValor', 'categoriaSexo', 'codigos', 'animalOrden', 'ficha', 'modo',
  ]),

  ETIQUETAS_PDF: {
    codigo: 'Codigo',
    codigo_madre: 'Codigo madre',
    arete: 'Arete',
    arete_madre: 'Arete madre',
    nombre: 'Nombre',
    animal: 'Animal',
    hembra: 'Hembra',
    macho: 'Macho',
    fecha: 'Fecha',
    nacimiento: 'Nacimiento',
    meses: 'Meses',
    grupo: 'Grupo etario',
    categoria: 'Categoria',
    estatus: 'Estatus',
    estado: 'Estado',
    lote: 'Lote',
    sexo: 'Sexo',
    peso: 'Peso',
    anterior: 'Peso anterior',
    diferencia: 'Diferencia',
    dias: 'Dias',
    gmd: 'Ganancia/dia',
    tipo: 'Tipo',
    origen: 'Origen',
    destino: 'Destino',
    total: 'Total',
    motivo: 'Motivo',
    revertido: 'Revertido',
    manana: 'Manana',
    tarde: 'Tarde',
    promedio: 'Promedio',
    primera: 'Primer registro',
    ultima: 'Ultimo registro',
    nro: 'Nro.',
    parto: 'Parto',
    observaciones: 'Observaciones',
  },

  /**
   * Convierte las filas del reporte en columnas + filas de texto para el
   * PDF. Las columnas salen de las claves de las filas (no hace falta
   * una definición por reporte).
   */
  tablaPdf(filas) {
    const claves = [];
    for (const fila of filas.slice(0, 25)) {
      if (!fila || typeof fila !== 'object') continue;
      for (const clave of Object.keys(fila)) {
        if (this.CAMPOS_NO_PDF.has(clave)) continue;
        if (!claves.includes(clave)) claves.push(clave);
      }
    }

    const etiqueta = (clave) => this.ETIQUETAS_PDF[clave]
      || clave.replace(/_/g, ' ').replace(/^./, (letra) => letra.toUpperCase());

    const valor = (crudo) => {
      if (crudo === null || crudo === undefined || crudo === '') return '—';
      if (typeof crudo === 'boolean') return crudo ? 'Si' : 'No';
      if (Array.isArray(crudo)) return String(crudo.length) + ' elem.';
      if (typeof crudo === 'object') return '—';
      const texto = String(crudo);
      if (/^\d{4}-\d{2}-\d{2}/.test(texto)) return formatearFechaReporte(texto);
      return texto;
    };

    const columnas = claves.map((clave) => {
      const titulo = etiqueta(clave);
      const numerica = ['peso', 'anterior', 'diferencia', 'dias', 'gmd', 'meses', 'total', 'manana', 'tarde', 'promedio', 'nro']
        .includes(clave);
      return {
        clave,
        titulo,
        ancho: numerica ? 1.2 : (titulo.length > 14 ? 3 : 2),
        alinear: numerica ? 'right' : 'left',
      };
    });

    const filasPdf = filas.map((fila) => {
      const salida = {};
      for (const columna of columnas) {
        salida[columna.clave] = valor(fila ? fila[columna.clave] : '');
      }
      return salida;
    });

    return { columnas, filas: filasPdf };
  },

  /**
   * Genera y descarga el PDF del reporte EN EL EQUIPO: no abre el
   * diálogo de impresión ni necesita conexión.
   */
  async descargarPdf(code, filtros = {}) {
    const { blob, nombre, titulo, filas } = await this.armarPdf(code, filtros);
    PdfSimple.descargar(blob, nombre);
    return { codigo: code, titulo, filas, bytes: blob.size, accion: 'descargado' };
  },

  /**
   * Genera el PDF y abre el menú de compartir del teléfono (WhatsApp,
   * correo, Drive…). Si el equipo no puede compartir archivos —o si el
   * envío falla— cae en la descarga de siempre, así el operario nunca
   * se queda sin el reporte.
   */
  async compartirPdf(code, filtros = {}) {
    const { blob, nombre, titulo, filas } = await this.armarPdf(code, filtros);
    const resultado = await PdfSimple.compartir(blob, nombre, titulo);

    if (resultado === 'compartido') {
      return { codigo: code, titulo, filas, bytes: blob.size, accion: 'compartido' };
    }
    if (resultado === 'cancelado') {
      return { codigo: code, titulo, filas, bytes: blob.size, accion: 'cancelado' };
    }

    // no_soportado / error: se entrega igual, descargado.
    PdfSimple.descargar(blob, nombre);
    return {
      codigo: code,
      titulo,
      filas,
      bytes: blob.size,
      accion: 'descargado',
      motivo: resultado === 'no_soportado' ? 'sin_soporte' : 'fallo_al_compartir',
    };
  },

  /**
   * Arma el PDF del reporte y guarda una copia en el caché local.
   * Lo usan descargarPdf() y compartirPdf(): así no hay dos versiones
   * del mismo armado que se puedan desincronizar.
   */
  async armarPdf(code, filtros = {}) {
    if (typeof PdfSimple === 'undefined') {
      throw new Error('El generador de PDF no esta disponible en este equipo.');
    }

    const { titulo, datos, sesion } = await this.prepararReporte(code, filtros);
    const filas = Array.isArray(datos) ? datos : [];
    if (!filas.length) {
      throw new Error('Este reporte no tiene datos para exportar.');
    }

    const tabla = this.tablaPdf(filas);
    const meta = [
      { etiqueta: 'Usuario', valor: sesion.usuario_login || '—' },
      { etiqueta: 'Nombre del fundo', valor: obtenerNombreFundo(sesion) },
      { etiqueta: 'Generado', valor: formatearFechaDDMMYYYY(new Date()) },
      { etiqueta: 'Registros', valor: String(tabla.filas.length) },
    ];

    const orientacion = tabla.columnas.length > 6 ? 'horizontal' : 'vertical';
    const blob = PdfSimple.generar({
      titulo,
      meta,
      columnas: tabla.columnas,
      filas: tabla.filas,
      orientacion,
      pie: 'SOVCA Offline - generado en el equipo',
    });

    await this.guardarCache({
      id: `${sesion.usuario_login || 'usuario'}:${code}:${Date.now()}`,
      usuario: sesion.usuario_login,
      fundo_id: sesion.fundo_id,
      codigo_reporte: code,
      titulo,
      datos: filas,
      generado_en: new Date().toISOString(),
    });

    return { blob, nombre: PdfSimple.nombreArchivo(titulo), titulo, filas: tabla.filas.length };
  },

  renderizarHtml(code, datos, titulo, sesion) {
    // Antes era `datos.length ? datos : []`, que con un array vacío
    // creaba uno nuevo y perdía cualquier propiedad extra colgada de
    // él (como la "ficha" de la madre en el reporte por madre) — justo
    // cuando más hace falta esa info (sin nacimientos que mostrar,
    // pero igual hay que mostrar de quién se trata).
    const filas = Array.isArray(datos) ? datos : [];

    const contenidoBase = {
      animalesActivos: () => {
        // Resumen por lote, calculado a partir del mismo listado de
        // abajo — así nunca puede desalinearse de lo que la persona
        // realmente ve en la tabla (mismo patrón que Nacimientos y
        // Control Sanitario).
        const mapaLotes = new Map();
        for (const fila of filas) {
          const key = fila.loteId ?? 'sin_lote';
          if (!mapaLotes.has(key)) {
            mapaLotes.set(key, { lote: fila.lote, total: 0, machos: 0, hembras: 0 });
          }
          const actual = mapaLotes.get(key);
          actual.total += 1;
          if (fila.sexoValor === 1) actual.hembras += 1;
          if (fila.sexoValor === 2) actual.machos += 1;
        }
        const resumenLotes = [...mapaLotes.values()].sort((a, b) => b.total - a.total);

        return `
          <table>
            <thead>
              <tr>
                <th>Lote</th>
                <th>Total</th>
                <th>Machos</th>
                <th>Hembras</th>
              </tr>
            </thead>
            <tbody>
              ${resumenLotes.map((fila) => `
                <tr>
                  <td>${htmlEscapar(fila.lote)}</td>
                  <td>${htmlEscapar(fila.total)}</td>
                  <td>${htmlEscapar(fila.machos)}</td>
                  <td>${htmlEscapar(fila.hembras)}</td>
                </tr>
              `).join('') || '<tr><td colspan="4">Sin registros</td></tr>'}
            </tbody>
          </table>
          <table>
            <thead>
              <tr>
                <th>Codigo</th>
                <th>Nombre</th>
                <th>Arete</th>
                <th>Sexo</th>
                <th>Lote</th>
                <th>Estado</th>
              </tr>
            </thead>
            <tbody>
              ${filas.map((fila) => `
                <tr>
                  <td>${htmlEscapar(fila.codigo)}</td>
                  <td>${htmlEscapar(fila.nombre)}</td>
                  <td>${htmlEscapar(fila.arete)}</td>
                  <td>${htmlEscapar(fila.sexo)}</td>
                  <td>${htmlEscapar(fila.lote)}</td>
                  <td>${htmlEscapar(fila.estado)}</td>
                </tr>
              `).join('') || '<tr><td colspan="6">Sin registros</td></tr>'}
            </tbody>
          </table>
        `;
      },
      resumenServicios: () => `
        <table>
          <thead>
            <tr>
              <th>Hembra</th>
              <th>Macho</th>
              <th>Fecha</th>
              <th>Tipo</th>
              <th>Observaciones</th>
            </tr>
          </thead>
          <tbody>
            ${filas.map((fila) => `
              <tr>
                <td>${htmlEscapar(fila.hembra)}</td>
                <td>${htmlEscapar(fila.macho)}</td>
                <td>${htmlEscapar(formatearFechaReporte(fila.fecha))}</td>
                <td>${htmlEscapar(fila.tipo)}</td>
                <td>${htmlEscapar(fila.observaciones)}</td>
              </tr>
            `).join('') || '<tr><td colspan="5">Sin registros</td></tr>'}
          </tbody>
        </table>
      `,
      resumenProduccionLechera: () => `
        <table>
          <thead>
            <tr>
              <th>Animal</th>
              <th>Fecha</th>
              <th>Manana</th>
              <th>Tarde</th>
              <th>Total</th>
            </tr>
          </thead>
          <tbody>
            ${filas.map((fila) => `
              <tr>
                <td>${htmlEscapar(fila.animal)}</td>
                <td>${htmlEscapar(formatearFechaReporte(fila.fecha))}</td>
                <td>${htmlEscapar(fila.manana)}</td>
                <td>${htmlEscapar(fila.tarde)}</td>
                <td>${htmlEscapar(fila.total)}</td>
              </tr>
            `).join('') || '<tr><td colspan="5">Sin registros</td></tr>'}
          </tbody>
        </table>
      `,
      controlSanitario: () => {
        // El resumen por animal se calcula acá, a partir del mismo
        // detalle que se muestra abajo — así nunca puede desalinearse
        // de lo que la persona realmente ve en la tabla.
        const mapaPorAnimal = new Map();
        for (const fila of filas) {
          const key = fila.animal;
          if (!mapaPorAnimal.has(key)) {
            mapaPorAnimal.set(key, { animal: key, total: 0, ultimo_control: null, proximo: null });
          }
          const actual = mapaPorAnimal.get(key);
          actual.total += 1;
          // "Ultimo control" = el de fecha mas reciente para ESE
          // animal (comparando fechas como texto ISO, que ordenan
          // igual que cronologicamente) — no el ultimo visto al
          // recorrer la lista.
          if (fila.fecha_control && fila.fecha_control !== '—'
            && (!actual.ultimo_control || fila.fecha_control > actual.ultimo_control)) {
            actual.ultimo_control = fila.fecha_control;
            actual.proximo = fila.proximo;
          }
        }
        const resumenFilas = [...mapaPorAnimal.values()].sort((a, b) => b.total - a.total);

        return `
          <table>
            <thead>
              <tr>
                <th>Animal</th>
                <th>Total controles</th>
                <th>Ultimo control</th>
                <th>Proximo control</th>
              </tr>
            </thead>
            <tbody>
              ${resumenFilas.map((fila) => `
                <tr>
                  <td>${htmlEscapar(fila.animal)}</td>
                  <td>${htmlEscapar(fila.total)}</td>
                  <td>${htmlEscapar(formatearFechaReporte(fila.ultimo_control))}</td>
                  <td>${htmlEscapar(formatearFechaReporte(fila.proximo))}</td>
                </tr>
              `).join('') || '<tr><td colspan="4">Sin registros</td></tr>'}
            </tbody>
          </table>
          <table>
            <thead>
              <tr>
                <th>Animal</th>
                <th>Fecha control</th>
                <th>Proximo control</th>
                <th>Dias hasta el proximo</th>
              </tr>
            </thead>
            <tbody>
              ${filas.map((fila) => `
                <tr>
                  <td>${htmlEscapar(fila.animal)}</td>
                  <td>${htmlEscapar(formatearFechaReporte(fila.fecha_control))}</td>
                  <td>${htmlEscapar(formatearFechaReporte(fila.proximo))}</td>
                  <td>${htmlEscapar(fila.rango_dias !== null && fila.rango_dias !== undefined ? fila.rango_dias : '—')}</td>
                </tr>
              `).join('') || '<tr><td colspan="4">Sin registros</td></tr>'}
            </tbody>
          </table>
        `;
      },
      resumenLotes: () => `
        <table>
          <thead>
            <tr>
              <th>Lote</th>
              <th>Total</th>
              <th>Machos</th>
              <th>Hembras</th>
            </tr>
          </thead>
          <tbody>
            ${filas.map((fila) => `
              <tr>
                <td>${htmlEscapar(fila.lote)}</td>
                <td>${htmlEscapar(fila.total)}</td>
                <td>${htmlEscapar(fila.machos)}</td>
                <td>${htmlEscapar(fila.hembras)}</td>
              </tr>
            `).join('') || '<tr><td colspan="4">Sin registros</td></tr>'}
          </tbody>
        </table>
      `,
      produccionAcumulada: () => `
        <table>
          <thead>
            <tr>
              <th>Animal</th>
              <th>Inicio</th>
              <th>Manana</th>
              <th>Tarde</th>
              <th>Total</th>
            </tr>
          </thead>
          <tbody>
            ${filas.map((fila) => `
              <tr>
                <td>${htmlEscapar(fila.animal)}</td>
                <td>${htmlEscapar(formatearFechaReporte(fila.fecha_inicio))}</td>
                <td>${htmlEscapar(fila.manana)}</td>
                <td>${htmlEscapar(fila.tarde)}</td>
                <td>${htmlEscapar(fila.total)}</td>
              </tr>
            `).join('') || '<tr><td colspan="5">Sin registros</td></tr>'}
          </tbody>
        </table>
      `,
      serviciosPorTipo: () => `
        <table>
          <thead>
            <tr>
              <th>Tipo</th>
              <th>Total</th>
              <th>Ultima fecha</th>
            </tr>
          </thead>
          <tbody>
            ${filas.map((fila) => `
              <tr>
                <td>${htmlEscapar(fila.tipo)}</td>
                <td>${htmlEscapar(fila.total)}</td>
                <td>${htmlEscapar(formatearFechaReporte(fila.fecha_ultimo))}</td>
              </tr>
            `).join('') || '<tr><td colspan="3">Sin registros</td></tr>'}
          </tbody>
        </table>
      `,
      gestantesActivas: () => `
        <table>
          <thead>
            <tr>
              <th>Hembra</th>
              <th>Servicio</th>
              <th>F. servicio</th>
              <th>F. probable parto</th>
            </tr>
          </thead>
          <tbody>
            ${filas.map((fila) => `
              <tr>
                <td>${htmlEscapar(fila.hembra)}</td>
                <td>${htmlEscapar(fila.servicio)}</td>
                <td>${htmlEscapar(formatearFechaReporte(fila.fecha_servicio))}</td>
                <td>${htmlEscapar(formatearFechaReporte(fila.fecha_probable_parto))}</td>
              </tr>
            `).join('') || '<tr><td colspan="4">Sin registros</td></tr>'}
          </tbody>
        </table>
      `,
      partosConfirmados: () => `
        <table>
          <thead>
            <tr>
              <th>Hembra</th>
              <th>Servicio</th>
              <th>F. parto</th>
              <th>N° terneros</th>
            </tr>
          </thead>
          <tbody>
            ${filas.map((fila) => `
              <tr>
                <td>${htmlEscapar(fila.hembra)}</td>
                <td>${htmlEscapar(fila.servicio)}</td>
                <td>${htmlEscapar(formatearFechaReporte(fila.fecha_parto))}</td>
                <td>${htmlEscapar(fila.nro_terneros)}</td>
              </tr>
            `).join('') || '<tr><td colspan="4">Sin registros</td></tr>'}
          </tbody>
        </table>
      `,
      inventarioPorEstado: () => `
        <table>
          <thead>
            <tr>
              <th>Estado</th>
              <th>Sexo</th>
              <th>Total</th>
            </tr>
          </thead>
          <tbody>
            ${filas.map((fila) => `
              <tr>
                <td>${htmlEscapar(fila.estado)}</td>
                <td>${htmlEscapar(fila.sexo)}</td>
                <td>${htmlEscapar(fila.total)}</td>
              </tr>
            `).join('') || '<tr><td colspan="3">Sin registros</td></tr>'}
          </tbody>
        </table>
      `,
      bajasDesincorporados: () => {
        // Resumen por tipo, calculado del MISMO listado que se muestra
        // abajo (no puede desalinearse).
        const resumenTipos = new Map();
        for (const fila of filas) {
          resumenTipos.set(fila.tipo, (resumenTipos.get(fila.tipo) || 0) + 1);
        }
        const sinRegistro = filas.filter((fila) => !fila.conRegistro).length;

        return `
          <table>
            <thead>
              <tr>
                <th>Tipo de baja</th>
                <th>Animales</th>
              </tr>
            </thead>
            <tbody>
              ${[...resumenTipos.entries()].map(([tipo, total]) => `
                <tr>
                  <td>${htmlEscapar(tipo)}</td>
                  <td>${htmlEscapar(total)}</td>
                </tr>
              `).join('') || '<tr><td colspan="2">Sin registros</td></tr>'}
            </tbody>
          </table>
          <table>
            <thead>
              <tr>
                <th>Codigo</th>
                <th>Nombre</th>
                <th>Sexo</th>
                <th>Tipo de baja</th>
                <th>Fecha</th>
                <th>Causa</th>
                <th>Lote</th>
                <th>Observaciones</th>
              </tr>
            </thead>
            <tbody>
              ${filas.map((fila) => `
                <tr>
                  <td>${htmlEscapar(fila.codigo)}</td>
                  <td>${htmlEscapar(fila.nombre)}</td>
                  <td>${htmlEscapar(fila.sexo)}</td>
                  <td>${htmlEscapar(fila.tipo)}</td>
                  <td>${htmlEscapar(fila.fecha ? formatearFechaReporte(fila.fecha) : '—')}</td>
                  <td>${htmlEscapar(fila.causa)}</td>
                  <td>${htmlEscapar(fila.lote)}</td>
                  <td>${htmlEscapar(fila.observaciones)}</td>
                </tr>
              `).join('') || '<tr><td colspan="8">Sin registros</td></tr>'}
            </tbody>
          </table>
          ${sinRegistro ? `<p style="font-size:11px; color:#5d676d; margin-top:10px;">`
            + `${htmlEscapar(sinRegistro)} animal(es) figuran dados de baja pero este dispositivo no tiene el registro de la baja `
            + '(fecha y causa): se sincronizaron ya con ese estado desde la versión web.'
            + '</p>' : ''}
        `;
      },
      resumenPorAptitud: () => `
        <table>
          <thead>
            <tr>
              <th>Aptitud</th>
              <th>Sexo</th>
              <th>Total</th>
            </tr>
          </thead>
          <tbody>
            ${filas.map((fila) => `
              <tr>
                <td>${htmlEscapar(fila.aptitud)}</td>
                <td>${htmlEscapar(fila.sexo)}</td>
                <td>${htmlEscapar(fila.total)}</td>
              </tr>
            `).join('') || '<tr><td colspan="3">Sin registros</td></tr>'}
          </tbody>
        </table>
      `,
      resumenNacimientos: () => {
        // Reutilizables en los dos modos.
        const total = filas.length;
        const totalHembras = filas.filter((f) => f.sexoValor === 2).length;
        const totalMachos = filas.filter((f) => f.sexoValor === 1).length;
        const totalVivos = filas.filter((f) => f.vivo).length;
        const pesos = filas.map((f) => f.peso).filter((p) => p !== null && p !== undefined && !Number.isNaN(p));
        const pesoProm = pesos.length ? pesos.reduce((a, b) => a + b, 0) / pesos.length : null;
        const pesoMax = pesos.length ? Math.max(...pesos) : null;
        const pesoMin = pesos.length ? Math.min(...pesos) : null;
        const porcentaje = (n) => (total ? `${(Math.round((n / total) * 1000) / 10)}%` : '—');
        // IEP Promedio: solo con los valores presentes (distinto de
        // vacío) en la columna IEP, tal como indica la fórmula.
        const iepValidos = filas.map((f) => f.iep).filter((v) => v !== null && v !== undefined && !Number.isNaN(v));
        const iepProm = iepValidos.length ? Math.round(iepValidos.reduce((a, b) => a + b, 0) / iepValidos.length) : null;

        // --- Modo "una sola madre": ficha + detalle de sus partos ---
        if (filas.modo === 'madre') {
          const ficha = filas.ficha || { codigo: '—', arete: '—', nombre: '—', seleccionada: false };
          const filaVaciaMensaje = !ficha.seleccionada
            ? 'Selecciona una hembra en el filtro "Codigo / Arete de la madre" para ver sus estadisticas.'
            : 'Esta hembra no tiene nacimientos registrados en el rango elegido.';

          return `
            <table>
              <thead>
                <tr>
                  <th>Codigo SOVCA</th>
                  <th>Nro. arete</th>
                  <th>Nombre</th>
                  <th>Total nacimientos</th>
                  <th>% vivos</th>
                  <th>% hembras</th>
                  <th>% machos</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>${htmlEscapar(ficha.codigo)}</td>
                  <td>${htmlEscapar(ficha.arete)}</td>
                  <td>${htmlEscapar(ficha.nombre)}</td>
                  <td>${total}</td>
                  <td>${porcentaje(totalVivos)}</td>
                  <td>${porcentaje(totalHembras)}</td>
                  <td>${porcentaje(totalMachos)}</td>
                </tr>
              </tbody>
            </table>
            <table>
              <thead>
                <tr>
                  <th>Peso promedio al nacer</th>
                  <th>Peso maximo al nacer</th>
                  <th>Peso minimo al nacer</th>
                  <th>IEP promedio (dias)</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>${pesoProm !== null ? pesoProm.toFixed(1) : '—'}</td>
                  <td>${pesoMax !== null ? pesoMax : '—'}</td>
                  <td>${pesoMin !== null ? pesoMin : '—'}</td>
                  <td>${iepProm !== null ? iepProm : '—'}</td>
                </tr>
              </tbody>
            </table>
            <table>
              <thead>
                <tr>
                  <th>Fecha</th>
                  <th>Parto Nro.</th>
                  <th>Tipo de servicio</th>
                  <th>Nacio</th>
                  <th>Sexo</th>
                  <th>Peso al nacer</th>
                  <th>ID</th>
                  <th>Aptitud del animal</th>
                  <th>IEP (dias)</th>
                </tr>
              </thead>
              <tbody>
                ${filas.map((fila) => `
                  <tr>
                    <td>${htmlEscapar(formatearFechaReporte(fila.fecha))}</td>
                    <td>${htmlEscapar(fila.nro_parto)}</td>
                    <td>${htmlEscapar(fila.tipo_servicio)}</td>
                    <td>${htmlEscapar(fila.nacio)}</td>
                    <td>${htmlEscapar(fila.sexo)}</td>
                    <td>${htmlEscapar(fila.peso !== null ? fila.peso : '—')}</td>
                    <td>${htmlEscapar(fila.id_cria)}</td>
                    <td>${htmlEscapar(fila.aptitud)}</td>
                    <td>${htmlEscapar(fila.iep !== null && fila.iep !== undefined ? fila.iep : '—')}</td>
                  </tr>
                `).join('') || `<tr><td colspan="9">${htmlEscapar(filaVaciaMensaje)}</td></tr>`}
              </tbody>
            </table>
          `;
        }

        // --- Modo general: listado de todas las madres ---
        return `
          <table>
            <thead>
              <tr>
                <th>Total nacimientos</th>
                <th>Total hembras</th>
                <th>Total machos</th>
                <th>% vivos</th>
                <th>% machos</th>
                <th>% hembras</th>
                <th>Peso promedio</th>
                <th>P. max al nacer</th>
                <th>P. min al nacer</th>
                <th>IEP promedio (dias)</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>${total}</td>
                <td>${totalHembras}</td>
                <td>${totalMachos}</td>
                <td>${porcentaje(totalVivos)}</td>
                <td>${porcentaje(totalMachos)}</td>
                <td>${porcentaje(totalHembras)}</td>
                <td>${pesoProm !== null ? pesoProm.toFixed(1) : '—'}</td>
                <td>${pesoMax !== null ? pesoMax : '—'}</td>
                <td>${pesoMin !== null ? pesoMin : '—'}</td>
                <td>${iepProm !== null ? iepProm : '—'}</td>
              </tr>
            </tbody>
          </table>
          <table>
            <thead>
              <tr>
                <th>Codigo SOVCA</th>
                <th>Nro. arete madre</th>
                <th>Fecha</th>
                <th>Parto Nro.</th>
                <th>Tipo de servicio</th>
                <th>Nacio</th>
                <th>Sexo</th>
                <th>Peso al nacer</th>
                <th>ID</th>
                <th>IEP (dias)</th>
              </tr>
            </thead>
            <tbody>
              ${filas.map((fila) => `
                <tr>
                  <td>${htmlEscapar(fila.codigo_madre)}</td>
                  <td>${htmlEscapar(fila.arete_madre)}</td>
                  <td>${htmlEscapar(formatearFechaReporte(fila.fecha))}</td>
                  <td>${htmlEscapar(fila.nro_parto)}</td>
                  <td>${htmlEscapar(fila.tipo_servicio)}</td>
                  <td>${htmlEscapar(fila.nacio)}</td>
                  <td>${htmlEscapar(fila.sexo)}</td>
                  <td>${htmlEscapar(fila.peso !== null ? fila.peso : '—')}</td>
                  <td>${htmlEscapar(fila.id_cria)}</td>
                  <td>${htmlEscapar(fila.iep !== null && fila.iep !== undefined ? fila.iep : '—')}</td>
                </tr>
              `).join('') || '<tr><td colspan="10">Sin registros</td></tr>'}
            </tbody>
          </table>
        `;
      },
      consecutivoPeso: () => {
        const animales = new Set(filas.map((fila) => fila.animal)).size;
        const conDiferencia = filas.filter((fila) => fila.diferencia !== null);

        return `
          <table>
            <thead>
              <tr>
                <th>Pesajes</th>
                <th>Animales</th>
                <th>Con diferencia calculada</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>${filas.length}</td>
                <td>${animales}</td>
                <td>${conDiferencia.length}</td>
              </tr>
            </tbody>
          </table>
          <table>
            <thead>
              <tr>
                <th>Animal</th>
                <th>Fecha</th>
                <th>Peso (kg)</th>
                <th>Peso anterior</th>
                <th>Diferencia</th>
                <th>Dias</th>
                <th>Ganancia media diaria</th>
              </tr>
            </thead>
            <tbody>
              ${filas.map((fila) => `
                <tr>
                  <td>${htmlEscapar(fila.animal)}</td>
                  <td>${htmlEscapar(formatearFechaReporte(fila.fecha))}</td>
                  <td>${htmlEscapar(fila.peso)}</td>
                  <td>${htmlEscapar(fila.anterior !== null ? fila.anterior : '—')}</td>
                  <td>${htmlEscapar(fila.diferencia !== null ? fila.diferencia : '—')}</td>
                  <td>${htmlEscapar(fila.dias !== null && fila.dias !== undefined ? fila.dias : '—')}</td>
                  <td>${htmlEscapar(fila.gmd !== null && fila.gmd !== undefined ? fila.gmd : '—')}</td>
                </tr>
              `).join('') || '<tr><td colspan="7">Sin registros</td></tr>'}
            </tbody>
          </table>
        `;
      },
      movimientoRebano: () => {
        const revertidos = filas.filter((fila) => fila.revertidoValor).length;

        return `
          <table>
            <thead>
              <tr>
                <th>Movimientos</th>
                <th>Vigentes</th>
                <th>Revertidos</th>
                <th>Animales movidos</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>${filas.length}</td>
                <td>${filas.length - revertidos}</td>
                <td>${revertidos}</td>
                <td>${filas.reduce((total, fila) => total + (Number(fila.total) || 0), 0)}</td>
              </tr>
            </tbody>
          </table>
          <table>
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Tipo</th>
                <th>Origen</th>
                <th>Destino</th>
                <th>Animales</th>
                <th>Motivo</th>
                <th>Revertido</th>
              </tr>
            </thead>
            <tbody>
              ${filas.map((fila) => `
                <tr>
                  <td>${htmlEscapar(formatearFechaReporte(fila.fecha))}</td>
                  <td>${htmlEscapar(fila.tipo)}</td>
                  <td>${htmlEscapar(fila.origen)}</td>
                  <td>${htmlEscapar(fila.destino)}</td>
                  <td>${htmlEscapar(fila.total)}</td>
                  <td>${htmlEscapar(fila.motivo)}</td>
                  <td>${htmlEscapar(fila.revertido)}</td>
                </tr>
              `).join('') || '<tr><td colspan="7">Sin registros</td></tr>'}
            </tbody>
          </table>
        `;
      },
      gruposEtarios: () => {
        const resumen = new Map();
        for (const fila of filas) {
          if (!resumen.has(fila.grupo)) resumen.set(fila.grupo, { grupo: fila.grupo, hembras: 0, machos: 0, total: 0 });
          const actual = resumen.get(fila.grupo);
          actual.total += 1;
          if (fila.sexoValor === 1) actual.hembras += 1;
          if (fila.sexoValor === 2) actual.machos += 1;
        }

        return `
          <table>
            <thead>
              <tr>
                <th>Grupo etario</th>
                <th>Hembras</th>
                <th>Machos</th>
                <th>Total</th>
              </tr>
            </thead>
            <tbody>
              ${[...resumen.values()].map((fila) => `
                <tr>
                  <td>${htmlEscapar(fila.grupo)}</td>
                  <td>${fila.hembras}</td>
                  <td>${fila.machos}</td>
                  <td>${fila.total}</td>
                </tr>
              `).join('') || '<tr><td colspan="4">Sin registros</td></tr>'}
            </tbody>
          </table>
          <table>
            <thead>
              <tr>
                <th>Codigo</th>
                <th>Nombre</th>
                <th>Arete</th>
                <th>Sexo</th>
                <th>Nacimiento</th>
                <th>Edad (meses)</th>
                <th>Grupo</th>
                <th>Categoria</th>
              </tr>
            </thead>
            <tbody>
              ${filas.map((fila) => `
                <tr>
                  <td>${htmlEscapar(fila.codigo)}</td>
                  <td>${htmlEscapar(fila.nombre)}</td>
                  <td>${htmlEscapar(fila.arete)}</td>
                  <td>${htmlEscapar(fila.sexo)}</td>
                  <td>${htmlEscapar(formatearFechaReporte(fila.nacimiento))}</td>
                  <td>${htmlEscapar(fila.meses !== null ? fila.meses : '—')}</td>
                  <td>${htmlEscapar(fila.grupo)}</td>
                  <td>${htmlEscapar(fila.categoria)}</td>
                </tr>
              `).join('') || '<tr><td colspan="8">Sin registros</td></tr>'}
            </tbody>
          </table>
        `;
      },
      hembrasServidas: () => {
        const hembras = new Set(filas.map((fila) => fila.hembra)).size;
        const conParto = filas.filter((fila) => fila.partoValor).length;

        return `
          <table>
            <thead>
              <tr>
                <th>Servicios</th>
                <th>Hembras distintas</th>
                <th>Terminaron en parto</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>${filas.length}</td>
                <td>${hembras}</td>
                <td>${conParto}</td>
              </tr>
            </tbody>
          </table>
          <table>
            <thead>
              <tr>
                <th>Hembra</th>
                <th>Fecha del servicio</th>
                <th>Tipo</th>
                <th>Macho / donadora</th>
                <th>Nro. servicio</th>
                <th>Dias transcurridos</th>
                <th>Parto</th>
              </tr>
            </thead>
            <tbody>
              ${filas.map((fila) => `
                <tr>
                  <td>${htmlEscapar(fila.hembra)}</td>
                  <td>${htmlEscapar(formatearFechaReporte(fila.fecha))}</td>
                  <td>${htmlEscapar(fila.tipo)}</td>
                  <td>${htmlEscapar(fila.macho)}</td>
                  <td>${htmlEscapar(fila.nro)}</td>
                  <td>${htmlEscapar(fila.dias !== null && fila.dias !== undefined ? fila.dias : '—')}</td>
                  <td>${htmlEscapar(fila.parto)}</td>
                </tr>
              `).join('') || '<tr><td colspan="7">Sin registros</td></tr>'}
            </tbody>
          </table>
        `;
      },
      productorasLeche: () => {
        const totales = filas.reduce((acumulado, fila) => ({
          manana: acumulado.manana + fila.manana,
          tarde: acumulado.tarde + fila.tarde,
          total: acumulado.total + fila.total,
        }), { manana: 0, tarde: 0, total: 0 });
        const redondear = (valor) => Math.round(valor * 100) / 100;

        return `
          <table>
            <thead>
              <tr>
                <th>Hembras</th>
                <th>Total manana</th>
                <th>Total tarde</th>
                <th>Total litros</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>${filas.length}</td>
                <td>${redondear(totales.manana)}</td>
                <td>${redondear(totales.tarde)}</td>
                <td>${redondear(totales.total)}</td>
              </tr>
            </tbody>
          </table>
          <table>
            <thead>
              <tr>
                <th>Hembra</th>
                <th>Dias con registro</th>
                <th>Manana</th>
                <th>Tarde</th>
                <th>Total</th>
                <th>Promedio por dia</th>
                <th>Primer registro</th>
                <th>Ultimo registro</th>
              </tr>
            </thead>
            <tbody>
              ${filas.map((fila) => `
                <tr>
                  <td>${htmlEscapar(fila.animal)}</td>
                  <td>${htmlEscapar(fila.dias)}</td>
                  <td>${htmlEscapar(fila.manana)}</td>
                  <td>${htmlEscapar(fila.tarde)}</td>
                  <td>${htmlEscapar(fila.total)}</td>
                  <td>${htmlEscapar(fila.promedio)}</td>
                  <td>${htmlEscapar(formatearFechaReporte(fila.primera))}</td>
                  <td>${htmlEscapar(formatearFechaReporte(fila.ultima))}</td>
                </tr>
              `).join('') || '<tr><td colspan="8">Sin registros</td></tr>'}
            </tbody>
          </table>
        `;
      },
      animalesPorLote: () => {
        const resumen = new Map();
        for (const fila of filas) {
          if (!resumen.has(fila.lote)) resumen.set(fila.lote, { lote: fila.lote, hembras: 0, machos: 0, total: 0 });
          const actual = resumen.get(fila.lote);
          actual.total += 1;
          if (fila.sexoValor === 1) actual.hembras += 1;
          if (fila.sexoValor === 2) actual.machos += 1;
        }

        return `
          <table>
            <thead>
              <tr>
                <th>Lote</th>
                <th>Hembras</th>
                <th>Machos</th>
                <th>Total</th>
              </tr>
            </thead>
            <tbody>
              ${[...resumen.values()].map((fila) => `
                <tr>
                  <td>${htmlEscapar(fila.lote)}</td>
                  <td>${fila.hembras}</td>
                  <td>${fila.machos}</td>
                  <td>${fila.total}</td>
                </tr>
              `).join('') || '<tr><td colspan="4">Sin registros</td></tr>'}
            </tbody>
          </table>
          <table>
            <thead>
              <tr>
                <th>Lote</th>
                <th>Codigo</th>
                <th>Nombre</th>
                <th>Arete</th>
                <th>Sexo</th>
                <th>Estatus</th>
              </tr>
            </thead>
            <tbody>
              ${filas.map((fila) => `
                <tr>
                  <td>${htmlEscapar(fila.lote)}</td>
                  <td>${htmlEscapar(fila.codigo)}</td>
                  <td>${htmlEscapar(fila.nombre)}</td>
                  <td>${htmlEscapar(fila.arete)}</td>
                  <td>${htmlEscapar(fila.sexo)}</td>
                  <td>${htmlEscapar(fila.estatus)}</td>
                </tr>
              `).join('') || '<tr><td colspan="6">Sin registros</td></tr>'}
            </tbody>
          </table>
        `;
      },
    };

    const contenido = {
      ...contenidoBase,
      animales_activos: contenidoBase.animalesActivos,
      resumen_servicios: contenidoBase.resumenServicios,
      resumen_produccion_lechera: contenidoBase.resumenProduccionLechera,
      resumen_control_sanitario: contenidoBase.controlSanitario,
      produccion_acumulada: contenidoBase.produccionAcumulada,
      servicios_por_tipo: contenidoBase.serviciosPorTipo,
      gestantes_activas: contenidoBase.gestantesActivas,
      partos_confirmados: contenidoBase.partosConfirmados,
      inventario_por_estado: contenidoBase.inventarioPorEstado,
      bajas_desincorporados: contenidoBase.bajasDesincorporados,
      resumen_por_aptitud: contenidoBase.resumenPorAptitud,
      resumen_nacimientos: contenidoBase.resumenNacimientos,
      consecutivo_peso: contenidoBase.consecutivoPeso,
      movimiento_rebano: contenidoBase.movimientoRebano,
      grupos_etarios: contenidoBase.gruposEtarios,
      hembras_servidas: contenidoBase.hembrasServidas,
      productoras_leche: contenidoBase.productorasLeche,
      animales_por_lote: contenidoBase.animalesPorLote,
    };

    const cuerpo = contenido[code] ? contenido[code]() : '<p>Reporte sin contenido.</p>';

    const nombreFundo = obtenerNombreFundo(sesion);

    return `<!doctype html>
      <html lang="es">
      <head>
        <meta charset="utf-8">
        <title>${htmlEscapar(titulo)}</title>
        <style>
          @page { size: A4 portrait; margin: 12mm; }
          body {
            font-family: Arial, sans-serif;
            margin: 24px;
            color: #1d2a2c;
            background: #ffffff;
          }
          h1 {
            font-size: 24px;
            margin-bottom: 4px;
            color: #234e35;
          }
          .meta {
            color: #5d676d;
            margin-bottom: 20px;
            font-size: 12px;
            line-height: 1.5;
          }
          table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
            page-break-inside: avoid;
          }
          th, td {
            border: 1px solid #d4d7d9;
            padding: 8px;
            text-align: left;
            font-size: 12px;
            vertical-align: top;
          }
          th {
            background: #eef3f5;
            color: #1b3b2d;
          }
          .footer {
            margin-top: 16px;
            font-size: 11px;
            color: #5d676d;
            border-top: 1px solid #dfe5e7;
            padding-top: 10px;
          }
          @media print {
            body { margin: 0; }
            button, .no-print { display: none !important; }
          }
        </style>
      </head>
      <body>
        <div class="no-print" style="display:flex; gap:10px; margin-bottom:20px;">
          <button onclick="window.print()" style="padding:10px 20px; background:#2F5233; color:#fff; border:none; border-radius:8px; font-weight:700; font-size:13px; cursor:pointer;">Imprimir</button>
          <button onclick="window.close()" style="padding:10px 20px; background:#E4DCC6; color:#201D18; border:none; border-radius:8px; font-weight:700; font-size:13px; cursor:pointer;">Cerrar</button>
        </div>
        <h1>${htmlEscapar(titulo)}</h1>
        <div class="meta">
          <div>Usuario: ${htmlEscapar(sesion.usuario_login || '—')}</div>
          <div>Nombre del fundo: ${htmlEscapar(nombreFundo)}</div>
          <div>Generado: ${htmlEscapar(formatearFechaDDMMYYYY(new Date()))}</div>
        </div>
        ${cuerpo}
        <div class="footer">SOVCA Offline · Generado localmente</div>
      </body>
      </html>`;
  },
};

window.Reportes = Reportes;