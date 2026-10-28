<?php
/**
 * ===========================================================================
 *  scripts/corregir_fk_servicio_nacimiento.php
 * ---------------------------------------------------------------------------
 *  El alta de un parto SIN servicio gestante fallaba con:
 *
 *      SQLSTATE[23503] ... insert or update on table "mregnacimientos"
 *      violates foreign key constraint "mregnacimientos_mregservicios_id_fkey"
 *
 *  Motivo: `mregnacimientos.mregservicios_id` puede tener DEFAULT 0 en la
 *  base de producción. Cuando el parto no viene de un servicio gestante el
 *  INSERT no incluye la columna, PostgreSQL aplica el 0 y la clave foránea lo
 *  rechaza porque 0 no existe en `mregservicios`. La columna debe quedar NULL.
 *
 *  Este script:
 *    1. Quita el DEFAULT de la columna (la deja nullable, sin valor por
 *       defecto).
 *    2. Pasa a NULL los registros que tengan 0 (datos heredados).
 *    3. Pone en NULL los vínculos que apuntan a servicios inexistentes.
 *    4. Muestra cuántas filas quedaron con vínculo real.
 *
 *  Uso (siempre primero en simulación):
 *      cd /var/www/html/sovca
 *      php scripts/corregir_fk_servicio_nacimiento.php            (simulación)
 *      php scripts/corregir_fk_servicio_nacimiento.php --aplicar
 *
 *  Es seguro ejecutarlo varias veces.
 * ===========================================================================
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script solo puede ejecutarse por consola (CLI).\n");
}

$aplicar = in_array('--aplicar', $argv, true);

// Los require relativos de config/conexion.php se resuelven desde el CWD.
chdir(__DIR__ . '/../controllers');
require_once '../config/conexion.php';

$cx = ActiveRecord\ConnectionManager::get_connection();

function cfn_query($cx, $sql)
{
    $st = $cx->connection->query($sql);
    return $st ? $st->fetchAll(PDO::FETCH_ASSOC) : array();
}

echo "\n";
echo "═══════════════════════════════════════════════════════════════════════\n";
echo ' SOVCA · Nacimientos y el vínculo con el servicio gestante' . ($aplicar ? '   [APLICAR]' : '   [SIMULACIÓN]') . "\n";
echo "═══════════════════════════════════════════════════════════════════════\n";

// ── 1) Estado de la columna ────────────────────────────────────────────
$col = cfn_query($cx, "
    SELECT column_default, is_nullable, data_type
      FROM information_schema.columns
     WHERE table_schema = 'public'
       AND table_name   = 'mregnacimientos'
       AND column_name  = 'mregservicios_id'
");
if (empty($col[0])) {
    fwrite(STDERR, "\nERROR: no existe mregnacimientos.mregservicios_id.\n\n");
    exit(1);
}

$default  = $col[0]['column_default'];
$nullable = $col[0]['is_nullable'];

echo "\n── Columna mregnacimientos.mregservicios_id ───────────────────────────\n";
printf("   tipo        : %s\n", $col[0]['data_type']);
printf("   permite NULL: %s\n", $nullable === 'YES' ? 'sí' : 'NO');
printf("   DEFAULT     : %s\n", ($default === null || $default === '') ? '(ninguno)' : $default);

$tieneDefault = ($default !== null && $default !== '');

// ── 2) Datos actuales ──────────────────────────────────────────────────
$conteo = cfn_query($cx, "
    SELECT count(*)                                         AS total,
           count(*) FILTER (WHERE mregservicios_id IS NULL) AS nulos,
           count(*) FILTER (WHERE mregservicios_id = 0)     AS ceros,
           count(*) FILTER (WHERE mregservicios_id > 0)     AS reales
      FROM mregnacimientos
");
$c = $conteo[0];

echo "\n── Registros de nacimiento ────────────────────────────────────────────\n";
printf("   total               : %s\n", number_format((int) $c['total'], 0, ',', '.'));
printf("   sin servicio (NULL) : %s\n", number_format((int) $c['nulos'], 0, ',', '.'));
printf("   con 0 (inválidos)   : %s\n", number_format((int) $c['ceros'], 0, ',', '.'));
printf("   con servicio real   : %s\n", number_format((int) $c['reales'], 0, ',', '.'));

// ── 3) Filas que apuntan a servicios que ya no existen ─────────────────
$rotos = cfn_query($cx, "
    SELECT count(*) AS n
      FROM mregnacimientos n
     WHERE n.mregservicios_id IS NOT NULL
       AND n.mregservicios_id > 0
       AND NOT EXISTS (SELECT 1 FROM mregservicios s WHERE s.id = n.mregservicios_id)
");
$nRotos = (int) $rotos[0]['n'];
if ($nRotos > 0) {
    printf("\n   AVISO: %s registro(s) apuntan a un servicio inexistente.\n", number_format($nRotos, 0, ',', '.'));
}

$hayQueHacer = $tieneDefault || (int) $c['ceros'] > 0 || $nRotos > 0;

if (!$hayQueHacer) {
    echo "\n ✔ Nada que corregir: la columna no tiene DEFAULT y no hay ceros.\n\n";
    exit(0);
}

if (!$aplicar) {
    echo "\n [SIMULACIÓN] Se haría:\n";
    if ($tieneDefault) {
        echo "   - ALTER COLUMN mregservicios_id DROP DEFAULT\n";
    }
    if ((int) $c['ceros'] > 0) {
        printf("   - UPDATE de %s fila(s) con 0 → NULL\n", number_format((int) $c['ceros'], 0, ',', '.'));
    }
    if ($nRotos > 0) {
        printf("   - UPDATE de %s fila(s) con servicio inexistente → NULL\n", number_format($nRotos, 0, ',', '.'));
    }
    echo "\n Agrega --aplicar para ejecutar.\n\n";
    exit(0);
}

// ── 4) Aplicar ─────────────────────────────────────────────────────────
echo "\n── Aplicando ──────────────────────────────────────────────────────────\n";

if ($tieneDefault) {
    try {
        $cx->connection->exec('ALTER TABLE mregnacimientos ALTER COLUMN mregservicios_id DROP DEFAULT');
        echo "   [OK] DEFAULT eliminado de mregservicios_id.\n";
    } catch (Throwable $e) {
        echo "   [ERROR] No se pudo quitar el DEFAULT: " . $e->getMessage() . "\n";
    }
}

if ((int) $c['ceros'] > 0) {
    try {
        $cx->connection->exec('UPDATE mregnacimientos SET mregservicios_id = NULL WHERE mregservicios_id = 0');
        printf("   [OK] %s fila(s) con 0 pasaron a NULL.\n", number_format((int) $c['ceros'], 0, ',', '.'));
    } catch (Throwable $e) {
        echo "   [ERROR] No se pudieron limpiar los ceros: " . $e->getMessage() . "\n";
    }
}

if ($nRotos > 0) {
    try {
        $cx->connection->exec("
            UPDATE mregnacimientos n
               SET mregservicios_id = NULL
             WHERE n.mregservicios_id IS NOT NULL
               AND n.mregservicios_id > 0
               AND NOT EXISTS (SELECT 1 FROM mregservicios s WHERE s.id = n.mregservicios_id)
        ");
        printf("   [OK] %s fila(s) con servicio inexistente pasaron a NULL.\n", number_format($nRotos, 0, ',', '.'));
    } catch (Throwable $e) {
        echo "   [ERROR] No se pudieron limpiar los vínculos rotos: " . $e->getMessage() . "\n";
    }
}

// ── 5) Verificación ────────────────────────────────────────────────────
echo "\n── Verificación ───────────────────────────────────────────────────────\n";
$final = cfn_query($cx, "
    SELECT column_default, is_nullable
      FROM information_schema.columns
     WHERE table_schema = 'public' AND table_name = 'mregnacimientos'
       AND column_name = 'mregservicios_id'
");
printf(
    "   DEFAULT     : %s\n",
    ($final[0]['column_default'] === null || $final[0]['column_default'] === '')
        ? '(ninguno) ✔' : $final[0]['column_default']
);

$c2 = cfn_query($cx, "
    SELECT count(*) FILTER (WHERE mregservicios_id = 0)     AS ceros,
           count(*) FILTER (WHERE mregservicios_id IS NULL) AS nulos,
           count(*) FILTER (WHERE mregservicios_id > 0)     AS reales
      FROM mregnacimientos
");
printf(
    "   ceros: %d · nulos: %d · con servicio real: %d\n",
    (int) $c2[0]['ceros'],
    (int) $c2[0]['nulos'],
    (int) $c2[0]['reales']
);

echo "\n ✔ Listo. El alta de partos sin servicio gestante ya no choca con la FK.\n\n";
