// Config central de la app offline. Si mueves esta carpeta a otro
// lugar dentro del proyecto, este es el único archivo que hace falta
// tocar para que vuelva a encontrar el API.
const CONFIG = {
  // Respaldo de versión visible. Normalmente se calcula automático
  // desde version.php en cada despliegue.
  FRONTEND_VERSION: 'auto',

  // Ruta relativa desde /app_offline/ hasta /api/. Ajustar si cambia
  // dónde vive esta carpeta respecto a /sovca/api/.
  API_BASE: '../api/',

  MODULO_ANIMALES: 'animales',
  MODULO_NACIMIENTOS: 'nacimientos',

  // Módulos soportados hoy por el backend de sincronización.
  MODULOS: [
    'animales', 'control_sanitario', 'control_sanitario_lotes', 'desincorporacion',
    'movimiento_rebano', 'produccion_leche', 'pesos', 'servicios', 'nacimientos',
  ],

  CAMPOS_REFERENCIA_ANIMAL: {
    control_sanitario: ['codigo_rgb_id_animal'],
    // 'control_sanitario_lotes' guarda la lista de animales en el array
    // "animales" (códigos), que sync.js remapea aparte: por eso la lista
    // de campos va vacía pero el módulo debe estar declarado.
    control_sanitario_lotes: [],
    // Bajas: la baja apunta a un animal por código.
    desincorporacion: ['codigo_rgb_id_animal'],
    // 'movimiento_rebano' guarda los animales movidos en el array
    // "animales" (códigos) y en el detalle: sync.js remapea ambos.
    movimiento_rebano: [],
    produccion_leche: ['codigo_rgb_id_animal_hembra'],
    pesos: ['codigo_rgb_id_animal'],
    // 'servicios' referencia un animal en TRES campos distintos —
    // si alguno se registró offline con código provisional (hembra,
    // macho o receptora/doña en transferencia de embriones), los tres
    // deben corregirse cuando ese animal reciba su código definitivo.
    servicios: ['codigo_rgb_id_animal_hembra', 'codigo_rgb_id_animal_macho', 'codigo_rgb_id_animal_dona'],
    nacimientos: ['codigo_rgb_id_animal_hembra', 'codigo_rgb_id_animal_macho', 'codigo_rgb_id_animal_dona'],
  },

  // Módulos cuyo código se genera EN EL DISPOSITIVO cuando no hay
  // conexión (necesitan reservar un rango de números — ver
  // Db.tomarSiguienteCodigo / Sync.asegurarRango). A diferencia de
  // 'servicios', cuyo código siempre lo asigna el servidor.
  MODULOS_CON_RANGO_LOCAL: {
    animales: { prefijoProvisional: 'SOVCA' },
    nacimientos: { prefijoProvisional: 'MA' },
  },

  // Cuando quedan menos de este número de códigos libres en el rango
  // reservado, la app intenta pedir un rango nuevo en cuanto detecta
  // conexión (para no quedarse sin códigos definitivos en el campo).
  UMBRAL_RESERVA_BAJA: 5,
  CANTIDAD_A_RESERVAR: 20,

  // Cada cuánto se intenta sincronizar solo, en milisegundos, mientras
  // hay conexión (además de sincronizar al tocar "Sincronizar" o al
  // recuperar señal).
  INTERVALO_AUTO_SYNC_MS: 2 * 60 * 1000,

  // Cada cuánto se le pregunta al servidor si hay una versión nueva de
  // la app (mejoras desplegadas). Solo se muestra un aviso: la recarga
  // la decide la persona que usa el equipo.
  INTERVALO_VERSION_MS: 5 * 60 * 1000,

  // Los catálogos (aptitudes, razas, categorías, productos, períodos)
  // cambian poco: se refrescan como máximo cada 12 horas por fundo en
  // las sincronizaciones automáticas. La sincronización manual (botón
  // "Sincronizar") siempre los refresca.
  HORAS_REFRESCO_CATALOGOS: 12,

  // Cuántas descargas de módulos se lanzan en paralelo por fundo. Cada
  // módulo guarda su propio cursor de sincronización, así que se pueden
  // pedir a la vez sin desordenar nada; el límite evita saturar al
  // servidor con muchas peticiones simultáneas.
  DESCARGAS_EN_PARALELO: 3,

  // Solo para pruebas locales sin HTTPS: permite derivar credencial
  // offline sin WebCrypto.subtle. En producción debe ir en false.
  PERMITIR_CRYPTO_OFFLINE_INSEGURO: true,
};