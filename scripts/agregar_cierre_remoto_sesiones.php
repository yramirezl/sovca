<?php
/**
 * ============================================================================
 *  scripts/agregar_cierre_remoto_sesiones.php
 * ----------------------------------------------------------------------------
 *  Agrega a `conexiones_historial` las columnas que necesita el cierre de
 *  sesión remoto desde el módulo Conexiones:
 *
 *      cierre_solicitado_at   cuándo el administrador pidió el cierre
 *      cierre_solicitado_por  quién lo pidió (username del administrador)
 *
 *  Con eso el usuario ve un aviso con cuenta atrás de 60 segundos antes de
 *  que el sistema lo saque, para que pueda guardar lo que esté haciendo.
 *
 *  Uso:
 *      php scripts/agregar_cierre_remoto_sesiones.php            (simulación)
 *      php scripts/agregar_cierre_remoto_sesiones.php --aplicar  (ejecuta)
 *
 *  Es idempotente.
 * ============================================================================
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script solo puede ejecutarse por consola (CLI).\n");
}

$ROOT    = dirname(__DIR__);
$aplicar = in_array('--aplicar', $argv, true);

chdir($ROOT . '/controllers');
require_once $ROOT . '/config/conexion.php';

$cx = ActiveRecord\ConnectionManager::get_connection();

function crs_query($cx, $sql)
{
    $st = $cx->connection->query($sql);
    return $st ? $st->fetchAll(PDO::FETCH_ASSOC) : array();
}

echo "\n";
echo "═══════════════════════════════════════════════════════════════════════\n";
echo ' Cierre remoto de sesiones' . ($aplicar ? '   [APLICAR]' : '   [SIMULACIÓN]') . "\n";
echo "═══════════════════════════════════════════════════════════════════════\n";

$columnas = array(
    'cierre_solicitado_at'  => 'timestamp with time zone',
    'cierre_solicitado_por' => 'character varying(60)',
);

echo "\n── Columnas en conexiones_historial ───────────────────────────────────\n";
$faltan = array();
foreach ($columnas as $nombre => $tipo) {
    $existe = crs_query($cx, "
        SELECT column_name
          FROM information_schema.columns
         WHERE table_schema = 'public'
           AND table_name   = 'conexiones_historial'
           AND column_name  = '$nombre'
    ");
    $hay = !empty($existe);
    if (!$hay) { $faltan[$nombre] = $tipo; }
    printf("   %-24s %s\n", $nombre, $hay ? 'ya existe' : 'FALTA');
}

if (!$faltan) {
    echo "\n ✔ Nada que hacer: la tabla ya tiene todas las columnas.\n\n";
    exit(0);
}

if (!$aplicar) {
    echo "\n [SIMULACIÓN] Se agregarían: " . implode(', ', array_keys($faltan)) . "\n";
    echo " Agrega --aplicar para ejecutar.\n\n";
    exit(0);
}

foreach ($faltan as $nombre => $tipo) {
    $cx->connection->exec("ALTER TABLE conexiones_historial ADD COLUMN IF NOT EXISTS $nombre $tipo");
    echo "\n   [OK] Columna $nombre agregada.\n";
}

echo "\n── Verificación ───────────────────────────────────────────────────────\n";
foreach (crs_query($cx, "
    SELECT column_name, data_type
      FROM information_schema.columns
     WHERE table_schema = 'public'
       AND table_name   = 'conexiones_historial'
       AND column_name IN ('cierre_solicitado_at', 'cierre_solicitado_por')
  ORDER BY column_name
") as $c) {
    printf("   %-24s %s\n", $c['column_name'], $c['data_type']);
}

echo "\n ✔ Listo.\n\n";
