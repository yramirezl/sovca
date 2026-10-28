<?php
/**
 * ACTUALIZA LAS VISTAS QUE USAN manimales.estatus_animal
 * ─────────────────────────────────────────────────────────────────────
 *  El catálogo de estatus del animal ahora es:
 *     1 ACTIVO (hembras y machos) · 2 DONADOR ACTIVO (macho) · 3 DONADOR (macho)
 *     4 DONADORA ACTIVO (hembra)  · 5 DONADORA (hembra)
 *
 *  Este script actualiza (idempotente) las vistas que traducen ese valor a
 *  texto o que lo filtran:
 *
 *   * v_list_animales_x_lotes    → CASE que traducía solo 1/2/3 (las donadoras
 *                                  salían NULL) → agrega 4 y 5.
 *   * v_list_animales_activos    → mismo CASE (1/2) y excluía el estatus 3;
 *                                  ahora excluye 3 y 5 (donador/donadora sin
 *                                  estado "activo") y traduce 4/5.
 *   * v_consecutivo_peso         → excluía el estatus 3 → excluye 3 y 5.
 *   * v_grupos_etarios           → idem.
 *
 *  Uso:
 *      php scripts/actualizar_vistas_estatus_animal.php [--dry-run]
 *      php scripts/actualizar_vistas_estatus_animal.php --host= --port= --db= --user= --pass=
 * ─────────────────────────────────────────────────────────────────────
 */

$ROOT = dirname(__DIR__);
$opts = array();

foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--([A-Za-z-]+)(?:=(.*))?$/', $arg, $m)) {
        $opts[strtolower($m[1])] = isset($m[2]) ? $m[2] : true;
    }
}

if (isset($opts['help'])) {
    echo "Uso: php scripts/actualizar_vistas_estatus_animal.php [--dry-run]\n";
    exit(0);
}

$dryRun = isset($opts['dry-run']);

require_once $ROOT . '/config/db.php';
$cfg = sovca_db_config();
foreach (array('host', 'port', 'db', 'user', 'pass') as $k) {
    if (isset($opts[$k]) && $opts[$k] !== true) {
        $cfg[$k] = (string) $opts[$k];
    }
}

try {
    $pdo = new PDO(
        "pgsql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['db']}",
        $cfg['user'],
        $cfg['pass'],
        array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
    );
} catch (PDOException $e) {
    fwrite(STDERR, "ERROR de conexión: " . $e->getMessage() . "\n");
    exit(1);
}

$vistas = array('v_list_animales_x_lotes', 'v_list_animales_activos', 'v_consecutivo_peso', 'v_grupos_etarios');

/**
 * Agrega los estatus de DONADORA (4 y 5) al CASE que traduce estatus_animal.
 * Devuelve la definición modificada (o la misma si no aplica).
 */
function ea_extender_case($definicion)
{
    if (stripos($definicion, 'DONADORA') !== false) {
        return $definicion;   /* ya actualizada */
    }

    if (!preg_match('~ELSE NULL::text\s*END AS estatus_animal~i', $definicion)) {
        return $definicion;
    }

    $alias = 'a';
    if (preg_match('~WHEN \(([A-Za-z0-9_]+)\.estatus_animal = 1\)~i', $definicion, $m)) {
        $alias = $m[1];
    }

    $nuevos = "WHEN ({$alias}.estatus_animal = 4) THEN 'DONADORA ACTIVO'::text\n"
            . "             WHEN ({$alias}.estatus_animal = 5) THEN 'DONADORA'::text\n"
            . "             ";

    return preg_replace('~ELSE NULL::text\s*END AS estatus_animal~i', $nuevos . 'ELSE NULL::text END AS estatus_animal', $definicion, 1);
}

/** Cambia el filtro "estatus_animal <> 3" por "estatus_animal NOT IN (3, 5)". */
function ea_extender_filtro($definicion)
{
    if (stripos($definicion, 'NOT IN (3, 5)') !== false) {
        return $definicion;
    }

    return preg_replace('~(([A-Za-z0-9_]+)\.)?estatus_animal\s*<>\s*3~i', '${1}estatus_animal NOT IN (3, 5)', $definicion);
}

echo "Base: {$cfg['db']} @ {$cfg['host']}" . ($dryRun ? "   (DRY RUN)" : '') . "\n";

$cambios = 0;

foreach ($vistas as $vista) {
    $existe = $pdo->query("SELECT 1 FROM pg_views WHERE schemaname='public' AND viewname='" . $vista . "'")->fetchColumn();

    if (!$existe) {
        echo "  - {$vista}: no existe (se omite)\n";
        continue;
    }

    $definicion = $pdo->query("SELECT pg_get_viewdef('" . $vista . "'::regclass, true)")->fetchColumn();

    $nueva = ea_extender_case($definicion);
    $nueva = ea_extender_filtro($nueva);

    if (trim($nueva) === trim($definicion)) {
        echo "  - {$vista}: ya actualizada\n";
        continue;
    }

    if ($dryRun) {
        echo "  - {$vista}: SE ACTUALIZARÍA\n";
        $cambios++;
        continue;
    }

    try {
        $pdo->exec('CREATE OR REPLACE VIEW ' . $vista . ' AS ' . $nueva);
        echo "  - {$vista}: actualizada\n";
        $cambios++;
    } catch (PDOException $e) {
        echo "  - {$vista}: ERROR " . $e->getMessage() . "\n";
    }
}

echo "Vistas modificadas: {$cambios}\n";
