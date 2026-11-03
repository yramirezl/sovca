<?php
/**
 * scripts/normalizar_planes_fundos.php
 * ---------------------------------------------------------------------------
 * Deja el PLAN de una cuenta multifundo igual en TODOS sus fundos.
 *
 * Por qué existe
 *   Al unificar dos fundos, el plan del fundo que llega podía quedarse con el
 *   suyo (más bajo) y el productor veía dos planes distintos en su listado:
 *   "LA FLORESTA" activo en BRONCE y "LA FLORESTA" inactivo en DIAMANTE, con
 *   la cuenta ya en DIAMANTE.
 *
 *   La causa eran dos cosas: las suscripciones (mplanesxfundo) de los usuarios
 *   que quedaron fuera de la unificación seguían ganando la lectura del plan
 *   del fundo, y la propagación del plan solo alcanzaba los fundos ACTIVOS.
 *
 * Qué hace
 *   * Informa, para cada cuenta multifundo, los fundos cuyo plan vigente no
 *     coincide con el plan del usuario, y los fundos con suscripciones de
 *     usuarios que ya no tienen acceso (residuo de una unificación).
 *   * Con --aplicar: pone el plan del usuario en cada fundo de la cuenta
 *     (mfundos.mplanes_id + la suscripción del usuario en mplanesxfundo),
 *     incluidos los fundos inactivos. No cambia el plan del usuario ni borra
 *     filas.
 *
 * Uso (desde la raíz del proyecto)
 *   php scripts/normalizar_planes_fundos.php                  # solo informa
 *   php scripts/normalizar_planes_fundos.php --aplicar        # corrige
 *   php scripts/normalizar_planes_fundos.php --usuario=90     # una sola cuenta
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script solo puede ejecutarse por consola (CLI).\n");
}

$ROOT = dirname(__DIR__);

$opts = array();
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z0-9\-]+)(?:=(.*))?$/i', $a, $m)) {
        $opts[strtolower($m[1])] = isset($m[2]) ? $m[2] : true;
    }
}

if (isset($opts['help'])) {
    echo "Uso: php scripts/normalizar_planes_fundos.php [--aplicar] [--usuario=ID]\n";
    exit(0);
}

$aplicar       = isset($opts['aplicar']);
$solo_usuario  = isset($opts['usuario']) ? (int) $opts['usuario'] : 0;

// Bootstrap de Active Record (igual que el resto de la aplicación)
chdir($ROOT . '/models');
require_once 'Musuario.php';
chdir($ROOT);

require_once $ROOT . '/lib/multifundo_helper.php';

if (!class_exists('ActiveRecord\\ConnectionManager') || !ActiveRecord\ConnectionManager::get_connection()) {
    fwrite(STDERR, "ERROR: no se pudo conectar a la base de datos (revisar config/conexion.php).\n");
    exit(1);
}

echo "=== Planes de las cuentas multifundo ===\n";
echo $aplicar ? "MODO: APLICAR CAMBIOS\n" : "MODO: solo informe (usar --aplicar para corregir)\n";
echo str_repeat('-', 74) . "\n";

$filtro = '';
if ($solo_usuario > 0) {
    $filtro = " AND id = $solo_usuario ";
}

$cuentas = _mf_q("
    SELECT id, username, mplanes_id, max_fundos
      FROM musuarios
     WHERE es_multifundo = TRUE
       AND status = 1
       AND COALESCE(mplanes_id, 0) > 0
       $filtro
  ORDER BY id
");

if (!$cuentas) {
    echo "No hay cuentas multifundo activas.\n";
    exit(0);
}

$total_fundos   = 0;
$desalineados   = 0;
$corregidos     = 0;
$errores        = 0;
$residuos_total = 0;

foreach ($cuentas as $c) {
    $uid  = (int) $c['id'];
    $plan = (int) $c['mplanes_id'];
    $fundos = mf_fundos_de_la_cuenta($uid);

    if (!$fundos) {
        continue;
    }

    $detalle = mf_plan_detalle($plan);
    $linea   = array();

    foreach ($fundos as $f) {
        $fid    = (int) $f['mfundos_id'];
        $actual = mf_plan_vigente_fundo($fid);
        $total_fundos++;

        $estado = ($actual === $plan) ? 'ok' : 'DISTINTO';
        if ($estado === 'DISTINTO') {
            $desalineados++;
        }

        $nombre_actual = '(sin plan)';
        if ($actual > 0) {
            $d_actual = mf_plan_detalle($actual);
            $nombre_actual = $d_actual['nombre'];
        }

        // Suscripciones de usuarios que ya no tienen acceso al fundo
        // (residuo de una unificación: siguen en la tabla y antes ganaban la
        // lectura del plan del fundo).
        $residuos = _mf_q("
            SELECT px.musuarios_id AS usuario, px.mplanes_id
              FROM mplanesxfundo px
              JOIN mfundos f ON f.id = px.mfundos_id
             WHERE px.mfundos_id = $fid
               AND px.msuscripciones_id IS NOT NULL
               AND px.musuarios_id IS DISTINCT FROM f.user_propietario
               AND NOT EXISTS (SELECT 1
                                 FROM mfundosxusuarios x
                                WHERE x.musuarios_id = px.musuarios_id
                                  AND x.mfundos_id = $fid
                                  AND x.status = 1)
        ");
        $residuos_total += count($residuos);

        echo sprintf(
            "  u%-3d %-22s fundo %-4d %-30s plan %-12s %s%s\n",
            $uid,
            substr((string) $c['username'], 0, 22),
            $fid,
            substr((string) $f['nombre'], 0, 30),
            $nombre_actual,
            $estado,
            ((int) $f['status'] === 1 ? '' : '  [fundo inactivo]')
        );

        foreach ($residuos as $r) {
            echo '        residuo: suscripción del usuario ' . (int) $r['usuario']
                . ' (plan ' . (int) $r['mplanes_id'] . ') que ya no tiene acceso' . "\n";
        }

        if ($estado === 'DISTINTO' && $aplicar) {
            if (mf_aplicar_plan_fundo($fid, $uid, $plan)) {
                $corregidos++;
                echo '        → plan actualizado a ' . $detalle['nombre'] . "\n";
            } else {
                $errores++;
                echo "        → ERROR al aplicar el plan\n";
            }
        }

        $linea[] = $estado;
    }
}

echo str_repeat('-', 74) . "\n";
echo 'Cuentas revisadas : ' . count($cuentas) . "\n";
echo 'Fundos revisados  : ' . $total_fundos . "\n";
echo 'Fundos con otro plan : ' . $desalineados . "\n";
echo 'Suscripciones de usuarios sin acceso : ' . $residuos_total . "\n";
if ($aplicar) {
    echo 'Fundos corregidos : ' . $corregidos . "\n";
    if ($errores > 0) {
        echo 'Errores           : ' . $errores . "\n";
    }
} elseif ($desalineados > 0) {
    echo "\nEjecutá con --aplicar para dejar todos los fundos con el plan de la cuenta.\n";
}

exit($errores > 0 ? 1 : 0);
