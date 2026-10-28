<?php
/**
 * scripts/sembrar_suscripciones.php
 * ---------------------------------------------------------------------------
 * Deja ACTIVO el control de suscripciones:
 *
 *   · Crea en mplanesxfundo la vigencia de cada productor que no tiene ninguna.
 *   · Deja mfundos.mplanes_id apuntando al plan (es lo que muestra la tarjeta
 *     "Mis Fundos" y de donde sale max_reg → "Animales 85/100").
 *
 * Con --plan=auto (por defecto) elige el plan más pequeño que alcance para la
 * cantidad de animales del fundo (BRONCE 20, PLATA 50, ORO 100, PLATINO 150,
 * DIAMANTE 5000). El administrador puede cambiarlo después desde la app.
 *
 * Uso (desde la raíz del proyecto):
 *   php scripts/sembrar_suscripciones.php                       # simulación
 *   php scripts/sembrar_suscripciones.php --aplicar             # crea las que faltan
 *   php scripts/sembrar_suscripciones.php --actualizar --aplicar
 *   php scripts/sembrar_suscripciones.php --plan=3 --suscripcion=4 --aplicar
 *
 * Opciones:
 *   --plan=auto|ID      Plan a asignar. auto = por capacidad del fundo (defecto).
 *   --suscripcion=ID    msuscripciones.id (1 Mensual … 4 Anual). Defecto 1.
 *   --dias=N            Días de vigencia. 0 = los del tipo de suscripción.
 *   --actualizar        Revisa TODOS los fundos: corrige el plan de la última
 *                       vigencia y de mfundos.mplanes_id (no toca fechas).
 *   --host= --port= --db= --user= --pass=   Sobrescriben la conexión.
 *   --aplicar           Ejecuta de verdad (sin esto es simulación).
 *   --help
 *
 * La conexión se lee de config/conexion.php (DSN pgsql://...).
 * ---------------------------------------------------------------------------
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
    echo "Uso: php scripts/sembrar_suscripciones.php [--plan=auto|ID] [--suscripcion=ID]\n";
    echo "                                            [--dias=N] [--actualizar] [--aplicar]\n";
    exit(0);
}

$APLICAR     = isset($opts['aplicar']);
$ACTUALIZAR  = isset($opts['actualizar']);
$PLAN_ARG    = isset($opts['plan']) && $opts['plan'] !== true ? trim((string) $opts['plan']) : 'auto';
$PLAN_FIJO   = (ctype_digit($PLAN_ARG) && (int) $PLAN_ARG > 0) ? (int) $PLAN_ARG : null;
$SUSCRIP_ID  = isset($opts['suscripcion']) && (int) $opts['suscripcion'] > 0 ? (int) $opts['suscripcion'] : 1;
$DIAS        = isset($opts['dias']) ? max(0, (int) $opts['dias']) : 0;

/** Plan más pequeño que cubra la cantidad de animales (o el mayor). */
function plan_para_animales(array $planes, $animales)
{
    foreach ($planes as $p) {
        if ($animales <= (int) $p['max_reg']) {
            return $p;
        }
    }
    return end($planes);
}

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
        $cfg['user'], $cfg['pass'],
        array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
    );
} catch (PDOException $e) {
    fwrite(STDERR, "ERROR de conexión: " . $e->getMessage() . "\n");
    exit(1);
}

echo str_repeat('─', 78) . "\n";
echo " Control de suscripciones · {$cfg['db']} @ {$cfg['host']}\n";
echo ' Modo: ' . ($APLICAR ? "APLICAR\n" : "SIMULACIÓN (usar --aplicar)\n");
echo ' Plan: ' . ($PLAN_FIJO !== null ? 'fijo #' . $PLAN_FIJO : 'automático por capacidad') . "\n";
echo str_repeat('─', 78) . "\n";

$susc = $pdo->query("SELECT id, descripcion, cantidad_dias FROM msuscripciones WHERE id = " . (int) $SUSCRIP_ID . " AND status = 1")->fetch(PDO::FETCH_ASSOC);
if (!$susc) {
    fwrite(STDERR, "ERROR: la suscripción {$SUSCRIP_ID} no existe o está inactiva.\n");
    exit(1);
}
$dias = $DIAS > 0 ? $DIAS : (int) $susc['cantidad_dias'];
echo " Período: {$susc['descripcion']} · {$dias} días\n\n";

$planes = $pdo->query("SELECT id, nombre, max_reg FROM mplanes WHERE status = 1 ORDER BY max_reg ASC")->fetchAll(PDO::FETCH_ASSOC);
if (!$planes) {
    fwrite(STDERR, "ERROR: no hay planes activos en mplanes.\n");
    exit(1);
}
if ($PLAN_FIJO !== null && !in_array($PLAN_FIJO, array_map('intval', array_column($planes, 'id')), true)) {
    fwrite(STDERR, "ERROR: el plan {$PLAN_FIJO} no existe o está inactivo.\n");
    exit(1);
}

// Fundos de productores activos, con su plan actual y animales
$sql = "
    SELECT f.id AS fundo_id, f.nombre AS fundo, f.mplanes_id AS plan_actual,
           u.id AS usuario_id, u.username,
           (SELECT count(*) FROM manimales a WHERE a.mfundos_id = f.id AND a.status = 1) AS animales,
           (SELECT p.id FROM mplanesxfundo p
             WHERE p.mfundos_id = f.id AND p.msuscripciones_id IS NOT NULL
             ORDER BY p.id DESC LIMIT 1) AS vigencia_id,
           (SELECT p.mplanes_id FROM mplanesxfundo p
             WHERE p.mfundos_id = f.id AND p.msuscripciones_id IS NOT NULL
             ORDER BY p.id DESC LIMIT 1) AS plan_vigencia
      FROM mfundos f
      JOIN musuarios u ON u.id = f.user_propietario AND u.mroles_id = 2 AND u.status = 1
     WHERE f.status = 1
     ORDER BY f.id";
$fundos = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$crear      = array();   // sin vigencia
$corregir   = array();   // vigencia con plan distinto a mfundos.mplanes_id
$soloFundo  = array();   // tiene vigencia y mfundos.mplanes_id NULL

foreach ($fundos as $f) {
    $plan = $PLAN_FIJO !== null
        ? array_values(array_filter($planes, function ($p) use ($PLAN_FIJO) { return (int) $p['id'] === $PLAN_FIJO; }))[0]
        : plan_para_animales($planes, (int) $f['animales']);
    $f['plan_calculado'] = $plan;

    if (empty($f['vigencia_id'])) {
        $crear[] = $f;
    } elseif ($ACTUALIZAR) {
        if ((int) $f['plan_vigencia'] !== (int) $plan['id'] || (int) $f['plan_actual'] !== (int) $plan['id']) {
            $corregir[] = $f;
        }
    } elseif ((int) $f['plan_actual'] === 0) {
        $soloFundo[] = $f;
    }
}

echo 'Fundos de productores: ' . count($fundos) . "\n";
echo '  · sin vigencia (se crea): ' . count($crear) . "\n";
if ($ACTUALIZAR) {
    echo '  · con plan distinto (se corrige): ' . count($corregir) . "\n";
} else {
    echo '  · con vigencia pero mfundos.mplanes_id vacío: ' . count($soloFundo) . ' (usá --actualizar)' . "\n";
}
echo "\n";

foreach ($crear as $f) {
    printf("  + %-28s fundo %-3s %-34s %3d animales → %s\n",
        $f['username'], $f['fundo_id'], mb_substr($f['fundo'], 0, 34), $f['animales'], $f['plan_calculado']['nombre']);
}
foreach ($corregir as $f) {
    printf("  ~ %-28s fundo %-3s plan %s → %s\n",
        $f['username'], $f['fundo_id'],
        $f['plan_vigencia'] ? '#' . $f['plan_vigencia'] : 'sin plan',
        $f['plan_calculado']['nombre']);
}

// Fundos activos sin plan, aunque su propietario ya no esté activo: la tarjeta
// "Mis Fundos" los muestra a los usuarios vinculados, así que también necesitan
// plan (solo se rellena mfundos.mplanes_id, sin crear vigencia).
$sinPlan = $pdo->query("
    SELECT f.id AS fundo_id, f.nombre AS fundo,
           (SELECT count(*) FROM manimales a WHERE a.mfundos_id = f.id AND a.status = 1) AS animales
      FROM mfundos f
     WHERE f.status = 1 AND f.mplanes_id IS NULL
     ORDER BY f.id")->fetchAll(PDO::FETCH_ASSOC);

foreach ($sinPlan as &$f) {
    $f['plan_calculado'] = $PLAN_FIJO !== null
        ? array_values(array_filter($planes, function ($p) use ($PLAN_FIJO) { return (int) $p['id'] === $PLAN_FIJO; }))[0]
        : plan_para_animales($planes, (int) $f['animales']);
}
unset($f);

if ($sinPlan) {
    echo "\nFundos activos sin plan (se les asigna por capacidad):\n";
    foreach ($sinPlan as $f) {
        printf("    fundo %-3s %-40s %4d animales → %s\n",
            $f['fundo_id'], mb_substr($f['fundo'], 0, 40), $f['animales'], $f['plan_calculado']['nombre']);
    }
}

$hechos = array('vigencia' => 0, 'plan_fundo' => 0);

if ($APLICAR) {
    $insVig = $pdo->prepare(
        "INSERT INTO mplanesxfundo
            (musuarios_id, msuscripciones_id, mfundos_id, mplanes_id,
             fecha_activa_suscripcion, fecha_caduca_suscripcion)
         VALUES (?, ?, ?, ?, CURRENT_DATE, CURRENT_DATE + (? || ' days')::interval)"
    );
    $updVig = $pdo->prepare("UPDATE mplanesxfundo SET mplanes_id = ? WHERE id = ?");
    $updFun = $pdo->prepare("UPDATE mfundos SET mplanes_id = ? WHERE id = ?");

    $pdo->beginTransaction();
    try {
        foreach ($crear as $f) {
            $insVig->execute(array((int) $f['usuario_id'], (int) $susc['id'], (int) $f['fundo_id'], (int) $f['plan_calculado']['id'], $dias));
            $hechos['vigencia'] += $insVig->rowCount();
            $updFun->execute(array((int) $f['plan_calculado']['id'], (int) $f['fundo_id']));
            $hechos['plan_fundo'] += $updFun->rowCount();
        }
        foreach ($soloFundo as $f) {
            $updFun->execute(array((int) $f['plan_calculado']['id'], (int) $f['fundo_id']));
            $hechos['plan_fundo'] += $updFun->rowCount();
        }
        foreach ($corregir as $f) {
            $updVig->execute(array((int) $f['plan_calculado']['id'], (int) $f['vigencia_id']));
            $updFun->execute(array((int) $f['plan_calculado']['id'], (int) $f['fundo_id']));
            $hechos['plan_fundo'] += $updFun->rowCount();
        }
        foreach ($sinPlan as $f) {
            $updFun->execute(array((int) $f['plan_calculado']['id'], (int) $f['fundo_id']));
            $hechos['plan_fundo'] += $updFun->rowCount();
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
        exit(1);
    }

    echo "\nVigencias creadas: {$hechos['vigencia']}\n";
    echo "Fundos con plan actualizado: {$hechos['plan_fundo']}\n";
    echo "El administrador puede ajustar plan, período o fechas desde la app.\n";
} else {
    echo "\n(Simulación: no se escribió nada. Añadí --aplicar para ejecutar.)\n";
}

// Diagnóstico final (se ejecuta siempre, sin escribir)
$sinPlan = $pdo->query("SELECT count(*) FROM mfundos WHERE status = 1 AND mplanes_id IS NULL")->fetchColumn();
$sinVig  = $pdo->query("
    SELECT count(*) FROM musuarios u
     WHERE u.mroles_id = 2 AND u.status = 1
       AND NOT EXISTS (SELECT 1 FROM mplanesxfundo p WHERE p.musuarios_id = u.id)")->fetchColumn();
echo "\nEstado: fundos sin plan = {$sinPlan} · productores sin vigencia = {$sinVig}\n";
