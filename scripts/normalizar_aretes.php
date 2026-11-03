<?php
/**
 * scripts/normalizar_aretes.php
 * ---------------------------------------------------------------------------
 * Deja TODOS los N° de arete en el formato canónico del sistema y resuelve los
 * duplicados que aparezcan al formatearlos.
 *
 * FORMATO (lib/arete_helper.php → bovs_arete_formatear)
 *   - MAYÚSCULAS (Ñ→N, Á→A…)
 *   - cada símbolo o espacio se reemplaza por un guion
 *   - nunca dos guiones seguidos
 *   - sin guion al inicio
 *   - máximo 15 caracteres
 *   Ej.: 'SN 2-1' → 'SN-2-1' · 'NRO. PIERNA: 22/25' → 'NRO-PIERNA-22-2'
 *
 * DUPLICADOS
 *   Se comparan los animales ACTIVOS (status = 1) de cada fundo, porque esa es
 *   la regla que aplica el sistema. El más ANTIGUO se queda con el arete tal
 *   cual y los siguientes agregan un guion final ('322JR' → '322JR-'); si ese
 *   ya existe, '-2', '-3'… siempre respetando el máximo de 15.
 *
 * Es un script de DATOS: por defecto solo informa. Para escribir hay que pasar
 * --aplicar. Cada fila modificada sube `version` (para que los equipos offline
 * reciban el cambio en la próxima sincronización).
 *
 * Uso (desde la raíz del proyecto):
 *   php scripts/normalizar_aretes.php                       # informe (dry-run)
 *   php scripts/normalizar_aretes.php --aplicar             # aplica
 *   php scripts/normalizar_aretes.php --fundo=17            # solo un fundo
 *   php scripts/normalizar_aretes.php --limite=10 --aplicar # prueba corta
 *   php scripts/normalizar_aretes.php --json                # informe JSON
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script solo puede ejecutarse por consola (CLI).\n");
}

$ROOT = dirname(__DIR__);
require_once $ROOT . '/lib/arete_helper.php';

define('ARETE_MAX', 15);

$opts = array();
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z0-9\-]+)(?:=(.*))?$/i', $a, $m)) {
        $opts[strtolower($m[1])] = isset($m[2]) ? $m[2] : true;
    }
}

if (isset($opts['help'])) {
    echo "Uso: php scripts/normalizar_aretes.php [--aplicar] [--fundo=N] [--limite=N] [--json]\n";
    echo "                                        [--host= --port= --db= --user= --pass=]\n";
    exit(0);
}

$aplicar = isset($opts['aplicar']);
$json    = isset($opts['json']);
$fundo   = isset($opts['fundo']) && $opts['fundo'] !== true ? (int) $opts['fundo'] : 0;
$limite  = isset($opts['limite']) && $opts['limite'] !== true ? (int) $opts['limite'] : 0;

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
    fwrite(STDERR, "ERROR de conexión (host={$cfg['host']} db={$cfg['db']}): " . $e->getMessage() . "\n");
    exit(1);
}

/* ─────────────── 1. Leer los aretes ─────────────── */
$sql = "SELECT id, mfundos_id, status, created, nro_arete
          FROM manimales
         WHERE btrim(coalesce(nro_arete, '')) <> ''";
$params = array();
if ($fundo > 0) {
    $sql .= ' AND mfundos_id = ?';
    $params[] = $fundo;
}
$sql .= ' ORDER BY mfundos_id, created NULLS LAST, id';

$st = $pdo->prepare($sql);
$st->execute($params);
$filas = $st->fetchAll(PDO::FETCH_ASSOC);

/* ─────────────── 2. Formatear ─────────────── */
$finales   = array();   // id => valor final
$original  = array();   // id => valor actual
$fundoDe   = array();   // id => fundo
$activoDe  = array();   // id => bool
$creadoDe  = array();   // id => marca para ordenar
$cambios   = 0;
$vacios    = 0;
$truncados = array();
$usados    = array();   // "fundo|arete" => true (activos)
$grupos    = array();   // "fundo|formateado" => [ids activos]

foreach ($filas as $f) {
    $id     = (int) $f['id'];
    $fid    = (int) $f['mfundos_id'];
    $activo = ((int) $f['status'] === 1);
    $nuevo  = bovs_arete_formatear($f['nro_arete'], ARETE_MAX);

    if ($nuevo === '') {
        $vacios++;
        continue;
    }

    $original[$id] = $f['nro_arete'];
    $fundoDe[$id]  = $fid;
    $activoDe[$id] = $activo;
    $creadoDe[$id] = (string) $f['created'];
    $finales[$id]  = $nuevo;

    if ($nuevo !== $f['nro_arete']) {
        $cambios++;
        if (mb_strlen(trim((string) $f['nro_arete']), 'UTF-8') > ARETE_MAX) {
            $truncados[] = array(
                'id'     => $id,
                'fundo'  => $fid,
                'antes'  => $f['nro_arete'],
                'despues' => $nuevo,
            );
        }
    }

    if ($activo) {
        $usados[$fid . '|' . $nuevo] = true;
        $grupos[$fid . '|' . $nuevo][] = $id;
    }
}

/* ─────────────── 3. Resolver duplicados ─────────────── */
$ajustes = array();

foreach ($grupos as $clave => $ids) {
    if (count($ids) < 2) {
        continue;
    }

    // El más antiguo conserva el arete; el resto se ajusta.
    usort($ids, function ($a, $b) use ($creadoDe) {
        $ca = $creadoDe[$a];
        $cb = $creadoDe[$b];
        if ($ca === $cb) {
            return $a <=> $b;
        }
        if ($ca === '') return 1;
        if ($cb === '') return -1;
        return strcmp($ca, $cb);
    });

    $base = $finales[$ids[0]];
    $fid  = $fundoDe[$ids[0]];

    for ($i = 1; $i < count($ids); $i++) {
        $id  = $ids[$i];
        $n   = 0;
        $nuevo = $base;

        do {
            $n++;
            $nuevo = bovs_arete_variante($base, $n, ARETE_MAX);
        } while ($nuevo === $base || isset($usados[$fid . '|' . $nuevo]));

        $usados[$fid . '|' . $nuevo] = true;
        $finales[$id] = $nuevo;

        if ($nuevo !== $original[$id]) {
            $cambios++;
        }

        $ajustes[] = array(
            'id'      => $id,
            'fundo'   => $fid,
            'antes'   => $original[$id],
            'despues' => $nuevo,
            'base'    => $base,
        );
    }
}

/* ─────────────── 4. Armar las actualizaciones ─────────────── */
$updates = array();
foreach ($finales as $id => $valor) {
    if (isset($original[$id]) && $valor !== $original[$id]) {
        $updates[] = array('id' => (int) $id, 'antes' => $original[$id], 'despues' => $valor);
    }
}

if ($limite > 0 && count($updates) > $limite) {
    $updates = array_slice($updates, 0, $limite);
}

/* ─────────────── 5. Informe / aplicación ─────────────── */
$resumen = array(
    'modo'             => $aplicar ? 'aplicar' : 'dry-run',
    'base'             => $cfg['db'],
    'fundo'            => $fundo,
    'revisados'        => count($filas),
    'cambian_formato'  => $cambios,
    'quedarian_vacios' => $vacios,
    'duplicados_ajustados' => count($ajustes),
    'actualizaciones'  => count($updates),
    'aplicado'         => 0,
    'truncados'        => $truncados,
    'ajustes'          => $ajustes,
    'detalle'          => $updates,
);

if ($aplicar && count($updates) > 0) {
    /* Hay un índice único parcial en manimales
       (mfundos_id, upper(btrim(nro_arete))) WHERE status = 1 AND mestatus_id = 1:
       al pasar 'sn 2' → 'SN-2' mientras otro animal todavía tiene 'SN-2' el
       UPDATE choca. Por eso se hace en dos fases: primero se apartan las filas
       con un valor temporal único y después se escribe el valor final. Todo en
       la misma transacción: si algo falla, no queda nada a medias. */
    $pdo->beginTransaction();
    try {
        $apartar = $pdo->prepare("UPDATE manimales SET nro_arete = ? WHERE id = ?");
        foreach ($updates as $u) {
            $apartar->execute(array('~' . $u['id'], $u['id']));
        }

        $up = $pdo->prepare(
            "UPDATE manimales
                SET nro_arete = ?,
                    version   = coalesce(version, 0) + 1
              WHERE id = ?"
        );
        foreach ($updates as $u) {
            $up->execute(array($u['despues'], $u['id']));
        }
        $pdo->commit();
        $resumen['aplicado'] = count($updates);
    } catch (Exception $e) {
        $pdo->rollBack();
        fwrite(STDERR, "ERROR: se revirtió todo. " . $e->getMessage() . "\n");
        exit(1);
    }
}

if ($json) {
    echo json_encode($resumen, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
    exit(0);
}

echo "Normalización de N° de arete — " . ($aplicar ? 'APLICANDO' : 'informe (sin escribir)') . "\n";
echo "Base: {$cfg['db']}" . ($fundo > 0 ? " · fundo $fundo" : ' · todos los fundos') . "\n";
echo str_repeat('=', 70) . "\n";
echo "Animales con arete revisados: {$resumen['revisados']}\n";
echo "Cambian de formato:           {$resumen['cambian_formato']}\n";
echo "Duplicados ajustados:         {$resumen['duplicados_ajustados']}\n";
echo "Actualizaciones a escribir:   {$resumen['actualizaciones']}\n";
echo "Quedarían vacíos:             {$resumen['quedarian_vacios']}\n";

if ($truncados) {
    echo "\nRECORTADOS A 15 CARACTERES (revísalos con el productor):\n";
    foreach ($truncados as $t) {
        echo "  id {$t['id']} (fundo {$t['fundo']}): '{$t['antes']}' → '{$t['despues']}'\n";
    }
}

if ($ajustes) {
    echo "\nDUPLICADOS AJUSTADOS:\n";
    foreach ($ajustes as $a) {
        echo "  id {$a['id']} (fundo {$a['fundo']}): '{$a['antes']}' → '{$a['despues']}' (base '{$a['base']}')\n";
    }
}

if ($updates) {
    echo "\nPrimeros cambios:\n";
    foreach (array_slice($updates, 0, 20) as $u) {
        echo "  id {$u['id']}: '{$u['antes']}' → '{$u['despues']}'\n";
    }
    if (count($updates) > 20) {
        echo '  … y ' . (count($updates) - 20) . " más.\n";
    }
}

if ($aplicar && $resumen['aplicado'] > 0) {
    echo "\nAplicado: {$resumen['aplicado']} fila(s). Cada una subió version para que\n";
    echo "los equipos offline reciban el arete nuevo en la próxima sincronización.\n";
} elseif ($aplicar) {
    echo "\nNo hubo nada que cambiar: los aretes ya están en el formato canónico y no\n";
    echo "hay duplicados entre animales activos.\n";
} else {
    echo "\nNada se modificó. Para escribir: php scripts/normalizar_aretes.php --aplicar\n";
}
