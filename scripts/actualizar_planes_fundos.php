<?php
/**
 * ===========================================================================
 *  scripts/actualizar_planes_fundos.php
 * ---------------------------------------------------------------------------
 *  Deja la tabla `mplanes` con la configuración por defecto del negocio:
 *
 *      Plan        Registros   Fundos
 *      BRONCE          20        20
 *      PLATA           50        20
 *      ORO            100        20
 *      PLATINO        150        20
 *      PLATINO +      400         5
 *      DIAMANTE      5000         5
 *
 *  - Añade la columna `max_fundos` si no existe (cupo de fundos por plan).
 *  - Crea los planes que falten (por ejemplo PLATINO +).
 *  - Con --forzar reescribe registros y fundos de TODOS los planes.
 *
 *  IMPORTANTE: estos son solo los valores de partida. El día a día se ajusta
 *  desde el módulo «Planes» (solo super usuario), sin volver a ejecutar esto.
 *  Por eso, sin --forzar, los planes existentes NO se tocan.
 *
 *  Uso (siempre primero en simulación):
 *      cd /var/www/html/sovca
 *      php scripts/actualizar_planes_fundos.php                     (simulación)
 *      php scripts/actualizar_planes_fundos.php --aplicar           (crea lo que falte)
 *      php scripts/actualizar_planes_fundos.php --aplicar --forzar  (impone la tabla)
 *
 *  Es idempotente: se puede correr varias veces sin efectos secundarios.
 * ===========================================================================
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script solo puede ejecutarse por consola (CLI).\n");
}

$aplicar = in_array('--aplicar', $argv, true);
$forzar  = in_array('--forzar', $argv, true);

/* Configuración por defecto: nombre, registros y fundos permitidos. */
$PLANES = array(
    array('nombre' => 'BRONCE',    'max_reg' => 20,   'max_fundos' => 20),
    array('nombre' => 'PLATA',     'max_reg' => 50,   'max_fundos' => 20),
    array('nombre' => 'ORO',       'max_reg' => 100,  'max_fundos' => 20),
    array('nombre' => 'PLATINO',   'max_reg' => 150,  'max_fundos' => 20),
    array('nombre' => 'PLATINO +', 'max_reg' => 400,  'max_fundos' => 5),
    array('nombre' => 'DIAMANTE',  'max_reg' => 5000, 'max_fundos' => 5),
);

// Los require relativos de config/conexion.php se resuelven desde el CWD.
chdir(__DIR__ . '/../controllers');
require_once '../config/conexion.php';

$cx = ActiveRecord\ConnectionManager::get_connection();

function apf_query($cx, $sql)
{
    $st = $cx->connection->query($sql);
    return $st ? $st->fetchAll(PDO::FETCH_ASSOC) : array();
}

echo "\n";
echo "═══════════════════════════════════════════════════════════════════════\n";
echo ' SOVCA · Planes: configuración de registros y fundos' . ($aplicar ? '   [APLICAR]' : '   [SIMULACIÓN]') . "\n";
echo "═══════════════════════════════════════════════════════════════════════\n";

// ── 1) ¿Existe la columna max_fundos? ───────────────────────────────────
$col = apf_query($cx, "
    SELECT column_name
      FROM information_schema.columns
     WHERE table_schema = 'public'
       AND table_name   = 'mplanes'
       AND column_name  = 'max_fundos'
");
$existe = !empty($col);

echo "\n── Estado actual ──────────────────────────────────────────────────────\n";
echo '   Columna mplanes.max_fundos: ' . ($existe ? "sí existe\n" : "NO existe\n");

$actuales = apf_query(
    $cx,
    'SELECT id, nombre, max_reg, status'
        . ($existe ? ', max_fundos' : ', 1 AS max_fundos')
        . ' FROM mplanes ORDER BY id ASC'
);

$porNombre = array();
foreach ($actuales as $a) {
    $porNombre[strtoupper(trim((string) $a['nombre']))] = $a;
}

echo "\n── Planes ─────────────────────────────────────────────────────────────\n";
printf("   %-12s %-22s %-22s\n", 'PLAN', 'ACTUAL (reg/fundos)', 'POR DEFECTO (reg/fundos)');
foreach ($PLANES as $p) {
    $clave = strtoupper(trim($p['nombre']));
    $reg   = isset($porNombre[$clave])
        ? $porNombre[$clave]['max_reg'] . ' / ' . $porNombre[$clave]['max_fundos']
        : '— (no existe)';
    printf("   %-12s %-22s %-22s\n", $p['nombre'], $reg, $p['max_reg'] . ' / ' . $p['max_fundos']);
}

/* Planes que están en la base y no en la lista: se avisan, no se tocan. */
$sobrantes = array();
foreach ($actuales as $a) {
    $clave   = strtoupper(trim((string) $a['nombre']));
    $enLista = false;
    foreach ($PLANES as $p) {
        if (strtoupper(trim($p['nombre'])) === $clave) {
            $enLista = true;
            break;
        }
    }
    if (!$enLista) {
        $sobrantes[] = $a['nombre'];
    }
}
if ($sobrantes) {
    echo "\n   AVISO: hay planes fuera de esta lista (no se tocan): " . implode(', ', $sobrantes) . "\n";
}

if (!$aplicar) {
    echo "\n [SIMULACIÓN] No se cambió nada. Agrega --aplicar para ejecutar.\n";
    echo " Use --forzar si además quiere imponer los valores de la tabla.\n\n";
    exit(0);
}

// ── 2) Crear la columna si falta ────────────────────────────────────────
if (!$existe) {
    $cx->connection->exec('ALTER TABLE mplanes ADD COLUMN max_fundos integer NOT NULL DEFAULT 1');
    echo "\n   [OK] Columna mplanes.max_fundos creada.\n";
}

// ── 3) Crear los planes que falten y, con --forzar, fijar los valores ──
echo "\n── Aplicando configuración ────────────────────────────────────────────\n";
foreach ($PLANES as $p) {
    $clave = strtoupper(trim($p['nombre']));

    if (!isset($porNombre[$clave])) {
        $st = $cx->connection->prepare(
            'INSERT INTO mplanes (nombre, max_reg, max_fundos, status) VALUES (?, ?, ?, 1)'
        );
        $st->execute(array($p['nombre'], (int) $p['max_reg'], (int) $p['max_fundos']));
        printf("   [CREADO]  %-12s %d registros · %d fundos\n", $p['nombre'], $p['max_reg'], $p['max_fundos']);
        continue;
    }

    if ($forzar) {
        $st = $cx->connection->prepare('UPDATE mplanes SET max_reg = ?, max_fundos = ? WHERE id = ?');
        $st->execute(array((int) $p['max_reg'], (int) $p['max_fundos'], (int) $porNombre[$clave]['id']));
        printf("   [AJUSTADO] %-12s %d registros · %d fundos\n", $p['nombre'], $p['max_reg'], $p['max_fundos']);
    } else {
        // Sin --forzar solo se corrige el cupo si la columna acaba de nacer.
        if (!$existe) {
            $st = $cx->connection->prepare('UPDATE mplanes SET max_fundos = ? WHERE id = ?');
            $st->execute(array((int) $p['max_fundos'], (int) $porNombre[$clave]['id']));
            printf("   [CUPO]    %-12s %d fundos\n", $p['nombre'], $p['max_fundos']);
        } else {
            printf("   [--]      %-12s sin cambios\n", $p['nombre']);
        }
    }
}

// ── 4) Verificación ─────────────────────────────────────────────────────
echo "\n── Verificación ───────────────────────────────────────────────────────\n";
foreach (apf_query($cx, 'SELECT id, nombre, max_reg, max_fundos, status FROM mplanes ORDER BY max_reg ASC, id ASC') as $p) {
    printf(
        "   id %-3d %-12s  registros %-6d  fundos %-4d  %s\n",
        $p['id'],
        $p['nombre'],
        $p['max_reg'],
        $p['max_fundos'],
        ((int) $p['status'] === 1 ? 'activo' : 'inactivo')
    );
}

// ── 5) Cuentas que hoy exceden el cupo de su plan ───────────────────────
echo "\n── Cuentas con más fundos que los que permite su plan ─────────────────\n";
$exceden = apf_query($cx, "
    SELECT u.id, u.username, p.nombre AS plan, p.max_fundos,
           COUNT(DISTINCT fxu.mfundos_id) AS fundos
      FROM musuarios u
      JOIN mplanes p ON p.id = u.mplanes_id
      LEFT JOIN mfundosxusuarios fxu
             ON fxu.musuarios_id = u.id AND fxu.status = 1
     WHERE u.status = 1
     GROUP BY u.id, u.username, p.nombre, p.max_fundos
    HAVING COUNT(DISTINCT fxu.mfundos_id) > p.max_fundos
     ORDER BY fundos DESC, u.username
");
if (!$exceden) {
    echo "   (ninguna)\n";
} else {
    foreach ($exceden as $e) {
        printf(
            "   id %-6d %-24s plan %-10s %d fundo(s) de %d permitidos\n",
            $e['id'],
            $e['username'],
            $e['plan'],
            $e['fundos'],
            $e['max_fundos']
        );
    }
    echo "\n   NOTA: no se modifica ninguna cuenta. El sistema bloqueará futuras\n";
    echo "   altas hasta que la cuenta quede dentro del cupo de su plan.\n";
}

echo "\n ✔ Listo.\n\n";
