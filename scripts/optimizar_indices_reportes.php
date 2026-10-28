<?php
/**
 * ============================================================================
 *  scripts/optimizar_indices_reportes.php
 * ----------------------------------------------------------------------------
 *  Índices que necesitan los REPORTES de SOVCA y que hoy faltan.
 *
 *  Por qué: los reportes filtran siempre por dos cosas:
 *
 *        WHERE mfundos_id = X AND <fecha> BETWEEN ... AND ...
 *
 *  Las tablas de SOVCA solo tienen índice por la clave primaria y por
 *  uuid_local, así que PostgreSQL lee TODAS las filas del fundo y descarta en
 *  memoria las que no caen en el rango. En una tabla de producción eso
 *  significa leer decenas de miles de filas para quedarse con unas cien.
 *
 *  Medición local en BOVS (200.000 filas, un año de un fundo):
 *
 *        sin índice   19,2 ms   ·   con índice   6,9 ms
 *        rango corto  11,5 ms   ·   con índice   1,4 ms   (8x)
 *
 *  Los índices se crean con CONCURRENTLY: no bloquean la tabla mientras se
 *  construyen, así que se pueden crear con el sistema en uso.
 *
 *  Uso (siempre primero en simulación):
 *      cd /var/www/html/sovca
 *      php scripts/optimizar_indices_reportes.php               (simulación)
 *      php scripts/optimizar_indices_reportes.php --aplicar     (crea)
 *      php scripts/optimizar_indices_reportes.php --aplicar --analizar
 *
 *  Es idempotente: si el índice ya existe, lo informa y sigue.
 * ============================================================================
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script solo puede ejecutarse por consola (CLI).\n");
}

$aplicar  = in_array('--aplicar', $argv, true);
$analizar = in_array('--analizar', $argv, true);

/* nombre del índice => [tabla, definición] */
$INDICES = array(
    'mprolecheras_fundo_fecha_prod_idx' => array(
        'tabla'   => 'mprolecheras',
        'def'     => 'ON mprolecheras (mfundos_id, fecha_produccion) WHERE status = 1',
        'para'    => 'Reporte de Producción de Leche, resumen por animal y filtros por rango de fechas',
    ),
    'mregservicios_fundo_fecha_idx' => array(
        'tabla'   => 'mregservicios',
        'def'     => 'ON mregservicios (mfundos_id, f_servicio)',
        'para'    => 'Reporte de Servicios del Animal, gestantes, servidas y ciclos',
    ),
    'mconsecutivopesos_fundo_fecha_idx' => array(
        'tabla'   => 'mconsecutivopesos',
        'def'     => 'ON mconsecutivopesos (mfundos_id, fecha_pesaje)',
        'para'    => 'Reporte de Consecutivo de Pesos',
    ),
    /* Los reportes "por lote" (animales activos x lote) filtran
       mfundos_id + mlotes_id sobre los animales vigentes. */
    'manimales_fundo_lote_activos_idx' => array(
        'tabla'   => 'manimales',
        'def'     => 'ON manimales (mfundos_id, mlotes_id) WHERE status = 1 AND mestatus_id = 1',
        'para'    => 'Reporte de Animales Activos por Lote y conteos del hato',
    ),
);

// Los require relativos de config/conexion.php se resuelven desde el CWD.
chdir(__DIR__ . '/../controllers');
require_once '../config/conexion.php';

$cx = ActiveRecord\ConnectionManager::get_connection();

function oir_query($cx, $sql)
{
    $st = $cx->connection->query($sql);
    return $st ? $st->fetchAll(PDO::FETCH_ASSOC) : array();
}

function oir_existe($cx, $nombre)
{
    $r = oir_query($cx, "SELECT 1 AS x FROM pg_indexes WHERE schemaname = 'public' AND indexname = '" . addslashes($nombre) . "'");
    return !empty($r);
}

echo "\n";
echo "═══════════════════════════════════════════════════════════════════════\n";
echo ' SOVCA · Índices para reportes' . ($aplicar ? '   [APLICAR]' : '   [SIMULACIÓN]') . "\n";
echo "═══════════════════════════════════════════════════════════════════════\n";

echo "\n── Estado de las tablas ───────────────────────────────────────────────\n";
foreach (array_unique(array_map(function ($i) { return $i['tabla']; }, $INDICES)) as $tabla) {
    $r = oir_query($cx, "
        SELECT pg_size_pretty(pg_total_relation_size('$tabla')) AS tam,
               (SELECT count(*) FROM $tabla) AS filas
    ");
    $f = !empty($r[0]) ? $r[0] : array('tam' => '?', 'filas' => '?');
    printf("   %-22s %8s  ·  %s filas\n", $tabla, $f['tam'], number_format((int) $f['filas'], 0, ',', '.'));
}

echo "\n── Índices propuestos ─────────────────────────────────────────────────\n";
$pendientes = array();
foreach ($INDICES as $nombre => $info) {
    $existe = oir_existe($cx, $nombre);
    printf("   %s %-36s (%s)\n", $existe ? '[OK]  ' : '[FALTA]', $nombre, $info['tabla']);
    printf("          para: %s\n", $info['para']);
    if (!$existe) {
        $pendientes[$nombre] = $info;
    }
}

if (!$pendientes) {
    echo "\n ✔ No falta ningún índice.\n\n";
    exit(0);
}

if (!$aplicar) {
    echo "\n [SIMULACIÓN] Se crearían " . count($pendientes) . " índice(s).\n";
    echo " Agrega --aplicar para ejecutarlos (CONCURRENTLY, sin bloquear).\n\n";
    exit(0);
}

echo "\n── Creando índices ────────────────────────────────────────────────────\n";
foreach ($pendientes as $nombre => $info) {
    $t0  = microtime(true);
    $sql = "CREATE INDEX CONCURRENTLY IF NOT EXISTS $nombre " . $info['def'];
    $ok  = true;
    try {
        $cx->connection->exec($sql);
    } catch (Throwable $e) {
        $ok = false;
        echo "   [ERROR] $nombre → " . $e->getMessage() . "\n";
    }
    if ($ok) {
        printf("   [OK] %-36s %.2f s\n", $nombre, microtime(true) - $t0);
    }
}

if ($analizar) {
    echo "\n── Actualizando estadísticas (ANALYZE) ────────────────────────────────\n";
    foreach (array_unique(array_map(function ($i) { return $i['tabla']; }, $INDICES)) as $tabla) {
        try {
            $cx->connection->exec("ANALYZE $tabla");
            printf("   [OK] ANALYZE %s\n", $tabla);
        } catch (Throwable $e) {
            printf("   [--] ANALYZE %s: %s\n", $tabla, $e->getMessage());
        }
    }
}

echo "\n── Verificación ───────────────────────────────────────────────────────\n";
foreach (array_keys($INDICES) as $nombre) {
    printf("   %-36s %s\n", $nombre, oir_existe($cx, $nombre) ? 'existe' : 'NO existe');
}

echo "\n ✔ Listo. Comparable el rendimiento de los reportes antes/después.\n\n";
