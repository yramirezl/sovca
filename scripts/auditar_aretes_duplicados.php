<?php
/**
 * scripts/auditar_aretes_duplicados.php
 * ---------------------------------------------------------------------------
 * Informe (SOLO LECTURA) de N° de arete repetidos entre animales activos.
 *
 * Los duplicados los corrige el PRODUCTOR (inactivando uno de los dos
 * registros o corrigiendo el arete); este script solo los lista para que se
 * sepa cuáles son. No modifica nada.
 *
 * Revisa dos cosas:
 *
 *   1. DUPLICADOS EXACTOS — dos animales activos con el mismo arete, con la
 *      misma normalización que usa el sistema (lib/arete_helper.php):
 *      mayúsculas y espacios colapsados ("322  jr" == "322 JR").
 *      Esto es lo que el alta/edición ya bloquea.
 *
 *   2. MISMA BASE NUMÉRICA — aretes distintos que empiezan con el mismo
 *      número ("332" y "332 JR"). No es un duplicado técnico, pero en
 *      pantalla y en las búsquedas se confunden.
 *
 * Uso (desde la raíz del proyecto):
 *   php scripts/auditar_aretes_duplicados.php [--fundo=63] [--json]
 *                                             [--solo=exactos|base|todo]
 *                                             [--host= --port= --db= --user= --pass=]
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
    echo "Uso: php scripts/auditar_aretes_duplicados.php [--fundo=N] [--json]\n";
    echo "                                                 [--solo=exactos|base|todo]\n";
    echo "                                                 [--host= --port= --db= --user= --pass=]\n";
    exit(0);
}

$fundo   = isset($opts['fundo']) && $opts['fundo'] !== true ? (int) $opts['fundo'] : 0;
$json    = isset($opts['json']);
$solo    = isset($opts['solo']) && $opts['solo'] !== true ? strtolower((string) $opts['solo']) : 'todo';
$verExac = in_array($solo, array('todo', 'exactos'), true);
$verBase = in_array($solo, array('todo', 'base'), true);

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

/** Expresión SQL con la MISMA normalización de lib/arete_helper.php. */
$NORMALIZAR = "regexp_replace(upper(btrim(coalesce(nro_arete, ''))), '\\s+', ' ', 'g')";

/* ─────────────── 1. Duplicados exactos ─────────────── */
$exactos = array();
if ($verExac) {
    $sql = "SELECT mfundos_id AS fundo,
                   {$NORMALIZAR} AS arete,
                   count(*) AS total,
                   string_agg(id || ':' || codigo_rgb_id_animal || ' ' || coalesce(nombre, ''), ' | ' ORDER BY id) AS animales
              FROM manimales
             WHERE status = 1
               AND btrim(coalesce(nro_arete, '')) <> ''";
    $params = array();
    if ($fundo > 0) {
        $sql .= ' AND mfundos_id = ?';
        $params[] = $fundo;
    }
    $sql .= ' GROUP BY 1, 2 HAVING count(*) > 1 ORDER BY 1, 2';

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $exactos = $st->fetchAll(PDO::FETCH_ASSOC);
}

/* ─────────────── 2. Misma base numérica ─────────────── */
$base = array();
if ($verBase) {
    $sql = "SELECT mfundos_id AS fundo, id, codigo_rgb_id_animal, nombre, nro_arete
              FROM manimales
             WHERE status = 1
               AND btrim(coalesce(nro_arete, '')) <> ''";
    $params = array();
    if ($fundo > 0) {
        $sql .= ' AND mfundos_id = ?';
        $params[] = $fundo;
    }
    $sql .= ' ORDER BY mfundos_id, id';

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $filas = $st->fetchAll(PDO::FETCH_ASSOC);

    $grupos = array();
    foreach ($filas as $f) {
        $normalizado = strtoupper(preg_replace('/\s+/u', ' ', trim((string) $f['nro_arete'])));
        if ($normalizado === '' || !preg_match('/^0*([0-9]+)/u', $normalizado, $m)) {
            continue;
        }
        $numero = ltrim($m[1], '0');
        if ($numero === '') {
            continue;
        }
        $clave = $f['fundo'] . '|' . $numero;
        $grupos[$clave][] = array(
            'arete'  => trim((string) $f['nro_arete']),
            'codigo' => (string) $f['codigo_rgb_id_animal'],
            'nombre' => (string) $f['nombre'],
        );
    }

    foreach ($grupos as $clave => $animales) {
        if (count($animales) < 2) {
            continue;
        }
        $aretes = array();
        foreach ($animales as $a) {
            $aretes[] = strtoupper(preg_replace('/\s+/u', ' ', $a['arete']));
        }
        if (count(array_unique($aretes)) < 2) {
            continue; // duplicado exacto: ya se informa arriba
        }
        list($f, $numero) = explode('|', $clave);
        $base[] = array(
            'fundo'    => (int) $f,
            'numero'   => $numero,
            'animales' => $animales,
        );
    }
}

/* ─────────────── Salida ─────────────── */
if ($json) {
    echo json_encode(array(
        'fundo'      => $fundo,
        'exactos'    => $exactos,
        'base'       => $base,
        'generado'   => date('Y-m-d H:i:s'),
    ), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
    exit(0);
}

$alcance = $fundo > 0 ? "fundo $fundo" : 'todos los fundos';
echo "Auditoría de N° de arete — $alcance\n";
echo str_repeat('=', 70), "\n";

if ($verExac) {
    echo "\n[1] ARETES DUPLICADOS EXACTOS (mismo fundo, animales activos): " . count($exactos) . "\n";
    if (!$exactos) {
        echo "    Ninguno.\n";
    }
    foreach ($exactos as $d) {
        echo "    Fundo {$d['fundo']} · arete {$d['arete']} × {$d['total']}\n";
        foreach (explode(' | ', $d['animales']) as $a) {
            echo "        - $a\n";
        }
    }
}

if ($verBase) {
    echo "\n[2] ARETES QUE COMPARTEN NÚMERO INICIAL (no son duplicados exactos): " . count($base) . "\n";
    if (!$base) {
        echo "    Ninguno.\n";
    }
    foreach ($base as $g) {
        echo "    Fundo {$g['fundo']} · número {$g['numero']}\n";
        foreach ($g['animales'] as $a) {
            echo "        - {$a['arete']} → {$a['codigo']} {$a['nombre']}\n";
        }
    }
}

echo "\nNada se modificó: los duplicados los corrige el productor (inactivar un\n";
echo "registro o corregir el arete desde la ficha del animal).\n";
