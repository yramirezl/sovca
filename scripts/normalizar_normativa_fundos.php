<?php
/**
 * scripts/normalizar_normativa_fundos.php
 * ---------------------------------------------------------------------------
 * Revisa y (opcionalmente) corrige los vínculos usuario ↔ fundo para que
 * cumplan la normativa vigente:
 *
 *   1. Un PRODUCTOR (rol 2) puede tener varios fundos (multifundo).
 *   2. Un FUNDO solo puede tener UN productor.
 *   3. Un fundo puede tener VARIOS ayudantes (rol 3).
 *   4. Un AYUDANTE (rol 3) solo puede pertenecer a UN fundo.
 *
 * Qué hace al corregir (--aplicar):
 *   * Fundo con varios productores → se queda con el `user_propietario` del
 *     fundo (o, si no está entre los vinculados, con el primer productor
 *     ACTIVO; si no hay activos, con el primero por id) y se DESACTIVAN
 *     (status = 0) los enlaces de los demás. No se borra ninguna fila.
 *   * Ayudante con varios fundos → conserva el de menor id y se desactivan
 *     los demás enlaces.
 *
 * No toca animales, pesos ni ningún dato de producción: solo el enlace
 * (mfundosxusuarios), que es lo que decide quién ve qué fundo.
 *
 * Uso (desde la raíz del proyecto):
 *   php scripts/normalizar_normativa_fundos.php            # solo informa
 *   php scripts/normalizar_normativa_fundos.php --aplicar  # corrige
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script solo puede ejecutarse por consola (CLI).\n");
}

$ROOT = dirname(__DIR__);

$opts = array();
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z0-9\-]+)$/i', $a, $m)) {
        $opts[strtolower($m[1])] = true;
    }
}

if (isset($opts['help'])) {
    echo "Uso: php scripts/normalizar_normativa_fundos.php [--aplicar]\n";
    exit(0);
}

$aplicar = isset($opts['aplicar']);

// Bootstrap de Active Record (igual que el resto de la aplicación)
chdir($ROOT . '/models');
require_once 'Musuario.php';
chdir($ROOT);

require_once $ROOT . '/lib/normativa_fundos_helper.php';

if (!class_exists('ActiveRecord\\ConnectionManager') || !ActiveRecord\ConnectionManager::get_connection()) {
    fwrite(STDERR, "ERROR: no se pudo conectar a la base de datos (revisar config/conexion.php).\n");
    exit(1);
}

echo "=== Normativa de vínculos usuario ↔ fundo ===\n";
echo $aplicar ? "MODO: APLICAR CAMBIOS\n" : "MODO: solo informe (usar --aplicar para corregir)\n";
echo str_repeat('-', 74) . "\n";

$auditoria = norm_auditar();
$multiProductor = $auditoria['fundos_multi_productor'];
$multiFundo = $auditoria['ayudantes_multi_fundo'];

$corregidosFundos = 0;
$corregidosAyudantes = 0;

// ───────────────────────── Fundos con varios productores ─────────────────────────
if (empty($multiProductor)) {
    echo "1) Fundos con más de un productor: ninguno ✔\n";
} else {
    echo "1) Fundos con más de un productor: " . count($multiProductor) . "\n";
    foreach ($multiProductor as $f) {
        $conservar = norm_productor_a_conservar($f['fundo']);
        $u = $conservar > 0 ? norm_usuario($conservar) : null;
        echo "   - Fundo {$f['fundo']} \"{$f['nombre']}\" ({$f['productores']} productores, {$f['activos']} activos): {$f['usuarios']}\n";
        echo "     se conserva: " . ($u ? $u['username'] . ' (id ' . $conservar . ')' : '(no se pudo determinar)') . "\n";

        if ($conservar <= 0) {
            continue;
        }

        $productores = norm_fundo_productores($f['fundo'], false);
        foreach ($productores as $p) {
            if ((int) $p['id'] === $conservar) {
                continue;
            }
            echo "     desactivar enlace de {$p['username']} (id {$p['id']})\n";
            if ($aplicar) {
                $ok = _norm_exec("UPDATE mfundosxusuarios
                                     SET status = 0, updated = NOW()
                                   WHERE mfundos_id = " . (int) $f['fundo'] . "
                                     AND musuarios_id = " . (int) $p['id'] . "
                                     AND status = 1");
                if ($ok) {
                    $corregidosFundos++;
                } else {
                    fwrite(STDERR, "     ERROR al desactivar el enlace de {$p['username']} en el fundo {$f['fundo']}\n");
                }
            }
        }
    }
}

// ───────────────────────── Ayudantes en varios fundos ─────────────────────────
if (empty($multiFundo)) {
    echo "2) Ayudantes en más de un fundo: ninguno ✔\n";
} else {
    echo "2) Ayudantes en más de un fundo: " . count($multiFundo) . "\n";
    foreach ($multiFundo as $a) {
        echo "   - {$a['username']} (id {$a['usuario']}, status {$a['status']}): {$a['nombres']}\n";

        $fundos = norm_usuario_fundos($a['usuario'], true);
        $conservarFundo = 0;
        foreach ($fundos as $fu) {
            if ($conservarFundo === 0) {
                $conservarFundo = (int) $fu['id'];
                echo "     se conserva el fundo {$conservarFundo} \"" . trim((string) $fu['nombre']) . "\"\n";
                continue;
            }
            echo "     desactivar enlace con el fundo " . (int) $fu['id'] . " \"" . trim((string) $fu['nombre']) . "\"\n";
            if ($aplicar) {
                $ok = _norm_exec("UPDATE mfundosxusuarios
                                     SET status = 0, updated = NOW()
                                   WHERE musuarios_id = " . (int) $a['usuario'] . "
                                     AND mfundos_id = " . (int) $fu['id'] . "
                                     AND status = 1");
                if ($ok) {
                    $corregidosAyudantes++;
                } else {
                    fwrite(STDERR, "     ERROR al desactivar el enlace con el fundo " . (int) $fu['id'] . "\n");
                }
            }
        }
    }
}

echo str_repeat('-', 74) . "\n";

if ($aplicar) {
    echo "Enlaces desactivados: {$corregidosFundos} (fundos con varios productores), {$corregidosAyudantes} (ayudantes en varios fundos)\n";

    $auditoria2 = norm_auditar();
    $pendientes = count($auditoria2['fundos_multi_productor']) + count($auditoria2['ayudantes_multi_fundo']);
    echo $pendientes === 0
        ? "Verificación posterior: la base cumple la normativa ✔\n"
        : "AVISO: quedan {$pendientes} casos por revisar.\n";
} else {
    echo "Nada modificado. Ejecute de nuevo con --aplicar para corregirlo.\n";
}

exit(0);
