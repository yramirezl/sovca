<?php
/**
 * scripts/corregir_tipo_servicio_nacimientos.php
 * ---------------------------------------------------------------------------
 * Repara los nacimientos que quedaron sin tipo de servicio válido.
 *
 * Por qué existen
 *   Antes el módulo de servicios permitía guardar sin tipo (`mservicios_id = 0`).
 *   Al registrar el parto el controlador copiaba ese 0 al nacimiento, y como la
 *   vista del listado (`vmregnacimientos`) une con `mtiposervicios`, el parto
 *   NO se veía en pantalla ni se podía unificar/anular. En el espejo de
 *   producción son 3 registros de 975.
 *
 *   Ya está corregido para los nuevos: los servicios exigen tipo y el parto lo
 *   hereda (y si el servicio no lo tenía, lo corrige). Este script es solo para
 *   los registros viejos.
 *
 * Uso (desde la raíz del proyecto)
 *   php scripts/corregir_tipo_servicio_nacimientos.php
 *       Lista los nacimientos con tipo inexistente.
 *
 *   php scripts/corregir_tipo_servicio_nacimientos.php --id=905 --tipo=1 --aplicar
 *       Corrige ese nacimiento (y su servicio gestante si lo tiene y está en 0).
 *
 *   php scripts/corregir_tipo_servicio_nacimientos.php --listar-tipos
 *       Muestra el catálogo mtiposervicios para elegir el id.
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
    echo "Uso: php scripts/corregir_tipo_servicio_nacimientos.php\n";
    echo "     [--id=ID --tipo=ID_O_NOMBRE] [--aplicar] [--listar-tipos]\n";
    echo "\nEjemplos:\n";
    echo "  --id=905 --tipo=2 --aplicar\n";
    echo "  --id=905 --tipo=MONTA --aplicar\n";
    echo "  --id=905 --tipo=INSEMINACION --aplicar\n";
    exit(0);
}

$aplicar  = isset($opts['aplicar']);
$id_nac   = isset($opts['id']) ? (int) $opts['id'] : 0;
$tipo_arg = isset($opts['tipo']) ? trim((string) $opts['tipo']) : '';
$id_tipo  = 0;

chdir($ROOT . '/models');
require_once 'Mregnacimiento.php';
require_once 'Mregservicio.php';
chdir($ROOT);

if (!class_exists('ActiveRecord\\ConnectionManager') || !ActiveRecord\ConnectionManager::get_connection()) {
    fwrite(STDERR, "ERROR: no se pudo conectar a la base de datos (revisar config/conexion.php).\n");
    exit(1);
}

$cx = Mregnacimiento::connection();

/* Catálogo de tipos de servicio: se usa para listar y para aceptar --tipo
   tanto por id (2) como por nombre (INSEMINACION ARTIFICIAL). */
$tipos = $cx->query('SELECT id, nombre FROM mtiposervicios ORDER BY id')->fetchAll();

if (!function_exists('nac_tipo_normalizar')) {
    function nac_tipo_normalizar($texto)
    {
        $t = strtoupper(trim((string) $texto));
        $t = strtr($t, array('Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U','Ñ'=>'N'));
        return preg_replace('/\s+/', ' ', $t);
    }
}

if (!function_exists('nac_tipo_imprimir')) {
    function nac_tipo_imprimir($tipos)
    {
        echo "Tipos de servicio disponibles:\n";
        foreach ($tipos as $t) {
            echo '  ' . (int) $t['id'] . ' · ' . $t['nombre'] . "\n";
        }
        echo "También se acepta el nombre: --tipo=MONTA | --tipo=INSEMINACION | --tipo=TRANSFERENCIA\n";
    }
}

/* Devuelve el id del tipo o 0 si no se reconoce. */
if (!function_exists('nac_tipo_resolver')) {
    function nac_tipo_resolver($tipos, $valor)
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return 0;
        }
        if (ctype_digit($valor)) {
            foreach ($tipos as $t) {
                if ((int) $t['id'] === (int) $valor) {
                    return (int) $t['id'];
                }
            }
            return 0;
        }
        $buscado = nac_tipo_normalizar($valor);
        foreach ($tipos as $t) {
            if (nac_tipo_normalizar($t['nombre']) === $buscado) {
                return (int) $t['id'];
            }
        }
        /* Coincidencia parcial: "inseminacion" -> INSEMINACION ARTIFICIAL.
           Se exigen al menos 3 letras: con una sola, "N" coincidiría con
           MO"N"TA y se aplicaría un tipo que nadie eligió. */
        if (strlen($buscado) < 3) {
            return 0;
        }
        foreach ($tipos as $t) {
            if (strpos(nac_tipo_normalizar($t['nombre']), $buscado) !== false) {
                return (int) $t['id'];
            }
        }
        return 0;
    }
}

if (isset($opts['listar-tipos'])) {
    echo "Tipos de servicio disponibles:\n";
    foreach ($tipos as $r) {
        echo '  ' . (int) $r['id'] . ' · ' . $r['nombre'] . "\n";
    }
    exit(0);
}

echo "=== Nacimientos con tipo de servicio inexistente ===\n";
echo $aplicar ? "MODO: APLICAR CAMBIOS\n" : "MODO: solo informe\n";
echo str_repeat('-', 74) . "\n";

$sqlMalos = "
    SELECT n.id, n.mfundos_id, n.codigo_rgb_id_animal_hembra, n.f_parto::date AS fecha,
           COALESCE(n.mtiposervicios_id, 0) AS tipo, n.mregservicios_id, n.status
      FROM mregnacimientos n
     WHERE n.status = 1
       AND NOT EXISTS (SELECT 1 FROM mtiposervicios s WHERE s.id = n.mtiposervicios_id)
  ORDER BY n.mfundos_id, n.id
";
$malos = $cx->query($sqlMalos)->fetchAll();

if (!$malos) {
    echo "Ninguno: todos los nacimientos activos tienen un tipo de servicio válido ✔\n";
    exit(0);
}

foreach ($malos as $m) {
    echo '  id ' . (int) $m['id'] . ' · fundo ' . (int) $m['mfundos_id'] . ' · '
        . $m['codigo_rgb_id_animal_hembra'] . ' · ' . $m['fecha']
        . ' · tipo ' . (int) $m['tipo']
        . ' · servicio ' . ((int) $m['mregservicios_id'] > 0 ? (int) $m['mregservicios_id'] : '(sin servicio)')
        . "\n";
}

echo str_repeat('-', 74) . "\n";
echo 'Total: ' . count($malos) . "\n";

if (!$aplicar) {
    echo "\nPara corregir uno de ellos:\n";
    $ejemplo = isset($malos[0]) ? (int) $malos[0]['id'] : 0;
    echo "  php scripts/corregir_tipo_servicio_nacimientos.php --id=" . $ejemplo . " --tipo=2 --aplicar\n";
    echo "  (--tipo acepta el id o el nombre: MONTA, INSEMINACION, TRANSFERENCIA)\n";
    exit(0);
}

if ($id_nac <= 0) {
    fwrite(STDERR, "ERROR: falta --id, el nacimiento que se quiere corregir.\n");
    nac_tipo_imprimir($tipos);
    exit(1);
}

if ($tipo_arg === '') {
    fwrite(STDERR, "ERROR: falta --tipo.\n");
    nac_tipo_imprimir($tipos);
    exit(1);
}

$id_tipo = nac_tipo_resolver($tipos, $tipo_arg);
if ($id_tipo <= 0) {
    fwrite(STDERR, "ERROR: «" . $tipo_arg . "» no es un tipo de servicio válido.\n");
    nac_tipo_imprimir($tipos);
    exit(1);
}

$tipo_valido = $cx->query('SELECT nombre FROM mtiposervicios WHERE id = ?', array($id_tipo))->fetch();
if (!$tipo_valido) {
    fwrite(STDERR, "ERROR: el tipo $id_tipo no existe en mtiposervicios.\n");
    exit(1);
}

$fila = null;
foreach ($malos as $m) {
    if ((int) $m['id'] === $id_nac) {
        $fila = $m;
        break;
    }
}
if (!$fila) {
    fwrite(STDERR, "ERROR: el nacimiento $id_nac no está en la lista de corregibles.\n");
    exit(1);
}

try {
    $cx->transaction();

    $cx->query(
        'UPDATE mregnacimientos
            SET mtiposervicios_id = ?, updated = NOW()
          WHERE id = ?',
        array($id_tipo, $id_nac)
    );

    $servicio = (int) $fila['mregservicios_id'];
    if ($servicio > 0) {
        $cx->query(
            'UPDATE mregservicios
                SET mservicios_id = ?, updated = NOW()
              WHERE id = ? AND COALESCE(mservicios_id, 0) = 0',
            array($id_tipo, $servicio)
        );
        echo "Servicio $servicio: tipo actualizado a $id_tipo (si estaba en 0).\n";
    }

    $cx->commit();
    echo "Nacimiento $id_nac: tipo " . $id_tipo . ' (' . $tipo_valido['nombre'] . ") aplicado.\n";
    echo "Ya debería aparecer en el listado y poder unificarse/anularse.\n";
} catch (Throwable $e) {
    try { $cx->rollback(); } catch (Throwable $e2) { /* nada */ }
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
    exit(1);
}
