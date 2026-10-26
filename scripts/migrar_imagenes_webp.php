<?php
/**
 * scripts/migrar_imagenes_webp.php
 * ---------------------------------------------------------------------------
 * Migra las imágenes YA GUARDADAS (JPG / JPEG / PNG / GIF / BMP / JFIF) al
 * formato WebP —redimensionándolas— y actualiza la referencia en la base de
 * datos. Es idempotente: las que ya son .webp se omiten.
 *
 * Mapeo tabla/columna -> directorio:
 *   manimales.imagen       -> dist/img/fotos_animales/
 *   musuarios.imagen       -> dist/img/fotos_usuarios/
 *   mpromomensajes.imagen  -> dist/img/promo/
 *
 * Uso (desde la raíz del proyecto):
 *   php scripts/migrar_imagenes_webp.php [opciones]
 *
 * Opciones:
 *   --dry-run         Muestra lo que haría SIN tocar archivos ni base de datos.
 *   --force           Vuelve a procesar también las que ya son .webp (recomprime).
 *   --max=800         Lado mayor máximo en px (default 800).
 *   --q=80            Calidad 1-100 (default 80).
 *   --tipo=animales   Procesa solo ese tipo (animales | usuarios | promo). Se puede repetir.
 *   --yes             Ejecuta el modo real sin pedir confirmación.
 *   --keep-old        No borra el archivo original luego de convertirlo.
 *   --huerfanos       Convierte también archivos del directorio que NINGUNA fila
 *                     referencia (imágenes sueltas que quedaron sin usar).
 *   --limit=N         Procesa como máximo N registros por tipo (pruebas).
 *   --host= --port= --db= --user= --pass=   Sobrescriben la conexión.
 *   --help
 *
 * La conexión se lee de config/conexion.php (DSN pgsql://...).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script solo puede ejecutarse por consola (CLI).\n");
}

$ROOT = dirname(__DIR__);
require $ROOT . '/lib/imagen_helper.php';

// ─────────────────────────────  Argumentos  ─────────────────────────────
$opts = array();
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z0-9\-]+)(?:=(.*))?$/i', $a, $m)) {
        $clave = strtolower($m[1]);
        $valor = isset($m[2]) ? $m[2] : true;
        if (isset($opts[$clave])) {
            $opts[$clave] = (array) $opts[$clave];
            $opts[$clave][] = $valor;
        } else {
            $opts[$clave] = $valor;
        }
    }
}

if (isset($opts['help'])) {
    echo "Uso: php scripts/migrar_imagenes_webp.php [--dry-run] [--force] [--max=800] [--q=80]\n";
    echo "                                            [--tipo=animales|usuarios|promo] [--keep-old]\n";
    echo "                                            [--huerfanos] [--limit=N] [--yes]\n";
    echo "                                            [--host= --port= --db= --user= --pass=]\n";
    exit(0);
}

$dryRun  = isset($opts['dry-run']);
$force   = isset($opts['force']);
$keepOld = isset($opts['keep-old']);
$maxDim  = isset($opts['max']) ? (int) $opts['max'] : SOVCA_IMAGEN_MAX_DIM;
$calidad = isset($opts['q'])   ? (int) $opts['q']   : SOVCA_IMAGEN_CALIDAD;
$limit   = isset($opts['limit']) ? (int) $opts['limit'] : 0;

$filtroTipos = array();
if (isset($opts['tipo'])) {
    foreach ((array) $opts['tipo'] as $t) {
        $filtroTipos[strtolower(trim($t))] = true;
    }
}

// ─────────────────────────────  Fuentes  ─────────────────────────────
$fuentes = array(
    'animales' => array(
        'etiqueta' => 'Fotos de animales',
        'tabla'    => 'manimales',
        'columna'  => 'imagen',
        'dir'      => $ROOT . '/dist/img/fotos_animales',
    ),
    'usuarios' => array(
        'etiqueta' => 'Fotos de usuarios',
        'tabla'    => 'musuarios',
        'columna'  => 'imagen',
        'dir'      => $ROOT . '/dist/img/fotos_usuarios',
    ),
    'promo' => array(
        'etiqueta' => 'Imágenes de promociones',
        'tabla'    => 'mpromomensajes',
        'columna'  => 'imagen',
        'dir'      => $ROOT . '/dist/img/promo',
    ),
);

// ───────────────────────  Comprobaciones previas  ───────────────────────
if (!function_exists('imagecreatefromstring') || !function_exists('imagewebp')) {
    fwrite(STDERR, "ERROR: se requiere la extensión GD con soporte WebP.\n");
    fwrite(STDERR, "       Verifica con:  php -r \"var_dump(function_exists('imagewebp'));\"\n");
    exit(1);
}

/**
 * Lee el DSN pgsql://usuario:clave@host:puerto/base desde config/conexion.php.
 */
function bovs_leer_conexion($archivo)
{
    $txt = @file_get_contents($archivo);
    if ($txt === false) {
        return null;
    }
    if (preg_match('~pgsql://([^:]+):([^@]*)@([^:/]+)(?::(\d+))?/([A-Za-z0-9_\-]+)~', $txt, $m)) {
        return array(
            'user' => $m[1],
            'pass' => $m[2],
            'host' => $m[3],
            'port' => ($m[4] !== '' ? $m[4] : '5432'),
            'db'   => $m[5],
        );
    }
    return null;
}

$cfg = bovs_leer_conexion($ROOT . '/config/conexion.php');
if ($cfg === null) {
    $cfg = array('user' => 'postgres', 'pass' => 'postgres', 'host' => '127.0.0.1', 'port' => '5432', 'db' => 'sovca_qas');
    fwrite(STDERR, "AVISO: no se pudo leer config/conexion.php; se usan valores por defecto.\n");
}
foreach (array('host', 'port', 'db', 'user', 'pass') as $k) {
    if (isset($opts[$k]) && $opts[$k] !== true) {
        $cfg[$k] = (string) $opts[$k];
    }
}

$pdo = null;
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

/** Devuelve un nombre .webp libre en el directorio (evita pisar otras imágenes). */
function bovs_nombre_libre($dir, $base, array $reservados)
{
    $nombre = $base . '.webp';
    $n = 0;
    while (is_file($dir . '/' . $nombre) || isset($reservados[$nombre])) {
        $n++;
        $nombre = $base . '_' . $n . '.webp';
    }
    return $nombre;
}

// ─────────────────────────────  Encabezado  ─────────────────────────────
echo "\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo " Migración de imágenes a WebP";
echo $dryRun ? "   [SIMULACIÓN: no se modifica nada]\n" : "\n";
echo "═══════════════════════════════════════════════════════════════\n";
printf(" Conexión : %s@%s:%s/%s\n", $cfg['user'], $cfg['host'], $cfg['port'], $cfg['db']);
printf(" Tamaño   : %d px máx   Calidad: %d\n", $maxDim, $calidad);
printf(" Original : %s\n", $keepOld ? 'se conserva' : 'se elimina tras convertir');
echo "═══════════════════════════════════════════════════════════════\n";

// ── Confirmación antes de modificar (salvo --dry-run / --yes) ──
if (!$dryRun && !isset($opts['yes'])) {
    $interactivo = !function_exists('posix_isatty') || posix_isatty(STDIN);
    if (!$interactivo) {
        fwrite(STDERR, "ERROR: entrada no interactiva. Ejecuta con --yes para confirmar el modo real.\n");
        exit(1);
    }
    echo "\n Esta operación convertirá las imágenes a .webp";
    echo $keepOld ? " (conservando los originales)." : " y ELIMINARÁ los originales.";
    echo "\n ¿Deseas continuar? [s/N]: ";
    $resp = trim((string) fgets(STDIN));
    if (strtolower(substr($resp, 0, 1)) !== 's') {
        echo " Cancelado. No se modificó nada.\n";
        exit(0);
    }
    echo "\n";
}

$totales = array('conv' => 0, 'webp' => 0, 'falta' => 0, 'error' => 0, 'huerf' => 0, 'bytes_ini' => 0, 'bytes_fin' => 0);

foreach ($fuentes as $tipo => $f) {
    if (!empty($filtroTipos) && !isset($filtroTipos[$tipo])) {
        continue;
    }

    echo "\n── {$f['etiqueta']}  ({$f['tabla']}.{$f['columna']})  ───────────────\n";

    if (!is_dir($f['dir'])) {
        echo "   [AVISO] el directorio no existe: {$f['dir']}\n";
        continue;
    }
    if (!$dryRun && !is_writable($f['dir'])) {
        echo "   [OMITIDO] el directorio no tiene permiso de escritura: {$f['dir']}\n";
        echo "             Ejecuta el script como el usuario del servidor web, p. ej.:\n";
        echo "             sudo -u www-data php {$argv[0]}\n";
        continue;
    }

    // Índice case-insensitive del directorio (para tolerar .JPG vs .jpg)
    $indice = array();
    foreach (scandir($f['dir']) as $archivo) {
        if ($archivo === '.' || $archivo === '..') {
            continue;
        }
        $indice[strtolower($archivo)] = $archivo;
    }

    $sql = "SELECT id, {$f['columna']} AS img FROM {$f['tabla']}
            WHERE {$f['columna']} IS NOT NULL AND {$f['columna']} <> ''";
    if ($limit > 0) {
        $sql .= " LIMIT {$limit}";
    }

    try {
        $filas = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo "   [ERROR] consulta: " . $e->getMessage() . "\n";
        continue;
    }

    $c = array('conv' => 0, 'webp' => 0, 'falta' => 0, 'error' => 0, 'huerf' => 0);
    $cache = array();      // 'dir|nombre' => nuevo nombre
    $reservados = array(); // nombres .webp creados en esta corrida

    foreach ($filas as $fila) {
        $id  = (int) $fila['id'];
        $old = (string) $fila['img'];
        $ext = strtolower(pathinfo($old, PATHINFO_EXTENSION));

        // Ya está en WebP
        if ($ext === 'webp' && !$force) {
            $c['webp']++;
            $totales['webp']++;
            continue;
        }

        $clave = $f['dir'] . '|' . $old;
        if (array_key_exists($clave, $cache)) {
            $nuevo = $cache[$clave];
            if ($nuevo !== null && $nuevo !== $old && !$dryRun) {
                actualizar_referencia($pdo, $f, $old, $nuevo);
            }
            continue;
        }

        // Localizar el archivo real en disco
        $nombreReal = basename($old);
        if (!is_file($f['dir'] . '/' . $nombreReal)) {
            $bajo = strtolower($nombreReal);
            $nombreReal = isset($indice[$bajo]) ? $indice[$bajo] : null;
        }
        if ($nombreReal === null) {
            echo "   [FALTA] id={$id}  {$old}\n";
            $c['falta']++;
            $totales['falta']++;
            $cache[$clave] = null;
            continue;
        }

        $ruta   = $f['dir'] . '/' . $nombreReal;
        $base   = pathinfo($nombreReal, PATHINFO_FILENAME);
        $pesoIni = (int) @filesize($ruta);

        if ($base === '') {
            echo "   [ERROR] id={$id}  nombre inválido: {$old}\n";
            $c['error']++;
            $totales['error']++;
            $cache[$clave] = null;
            continue;
        }

        // Nombre destino (si ya es .webp y --force, se recomprime en el mismo lugar)
        if ($ext === 'webp') {
            $destino = $base . '.webp';
        } else {
            $destino = bovs_nombre_libre($f['dir'], $base, $reservados);
        }

        if ($dryRun) {
            echo "   [SIMULA] id={$id}  {$nombreReal} -> {$destino}" .
                 ($pesoIni ? '  (' . round($pesoIni / 1024) . ' KB)' : '') . "\n";
            $cache[$clave] = $destino;
            $c['conv']++;
            $totales['conv']++;
            continue;
        }

        $nuevo = bovs_imagen_procesar($ruta, $f['dir'], pathinfo($destino, PATHINFO_FILENAME), $maxDim, $calidad);
        if (!$nuevo) {
            echo "   [ERROR] id={$id}  no se pudo convertir: {$nombreReal}\n";
            $c['error']++;
            $totales['error']++;
            $cache[$clave] = null;
            continue;
        }

        $pesoFin = (int) @filesize($f['dir'] . '/' . $nuevo);
        $reservados[$nuevo] = true;

        // Actualizar la BD (todas las filas que apuntaban al nombre anterior)
        if ($nuevo !== $old) {
            actualizar_referencia($pdo, $f, $old, $nuevo);
        }

        // Borrar el original (solo si cambió de nombre y no es --keep-old)
        if (!$keepOld && $nuevo !== $nombreReal) {
            @unlink($ruta);
        }

        $cache[$clave] = $nuevo;
        $c['conv']++;
        $totales['conv']++;
        $totales['bytes_ini'] += $pesoIni;
        $totales['bytes_fin'] += $pesoFin;

        printf(
            "   [OK] id=%d  %s -> %s  (%d KB -> %d KB)\n",
            $id,
            $nombreReal,
            $nuevo,
            round($pesoIni / 1024),
            round($pesoFin / 1024)
        );
    }

    // ── Archivos huérfanos: están en disco pero ninguna fila los referencia ──
    if (isset($opts['huerfanos'])) {
        $referenciados = array();
        foreach ($filas as $fila) {
            $referenciados[strtolower(basename((string) $fila['img']))] = true;
        }
        foreach ($indice as $bajo => $archivo) {
            if ($archivo === 'index.php' || isset($referenciados[$bajo])) {
                continue;
            }
            if (strtolower(pathinfo($archivo, PATHINFO_EXTENSION)) === 'webp') {
                continue;
            }
            $base = pathinfo($archivo, PATHINFO_FILENAME);
            if ($base === '') {
                continue;
            }
            $destino = bovs_nombre_libre($f['dir'], $base, $reservados);
            if ($dryRun) {
                echo "   [SIMULA·HUÉRFANO] {$archivo} -> {$destino}\n";
                $c['huerf']++;
                $totales['huerf']++;
                continue;
            }
            $ruta  = $f['dir'] . '/' . $archivo;
            $nuevo = bovs_imagen_procesar($ruta, $f['dir'], pathinfo($destino, PATHINFO_FILENAME), $maxDim, $calidad);
            if (!$nuevo) {
                echo "   [ERROR·HUÉRFANO] no se pudo convertir: {$archivo}\n";
                $c['error']++;
                $totales['error']++;
                continue;
            }
            $reservados[$nuevo] = true;
            if (!$keepOld && $nuevo !== $archivo) {
                @unlink($ruta);
            }
            printf("   [OK·HUÉRFANO] %s -> %s\n", $archivo, $nuevo);
            $c['huerf']++;
            $totales['huerf']++;
        }
    }

    printf(
        "   → convertidas: %d · ya webp: %d · faltantes: %d · errores: %d%s\n",
        $c['conv'],
        $c['webp'],
        $c['falta'],
        $c['error'],
        isset($opts['huerfanos']) ? ' · huérfanos: ' . $c['huerf'] : ''
    );
}

// ─────────────────────────────  Resumen  ─────────────────────────────
echo "\n═══════════════════════════════════════════════════════════════\n";
printf(
    " Convertidas: %d · Ya webp: %d · Faltantes: %d · Errores: %d%s\n",
    $totales['conv'],
    $totales['webp'],
    $totales['falta'],
    $totales['error'],
    isset($opts['huerfanos']) ? ' · Huérfanos: ' . $totales['huerf'] : ''
);
if ($totales['bytes_ini'] > 0) {
    printf(
        " Peso: %s -> %s  (ahorro %s%%)\n",
        bovs_formato_bytes($totales['bytes_ini']),
        bovs_formato_bytes($totales['bytes_fin']),
        round((1 - $totales['bytes_fin'] / $totales['bytes_ini']) * 100)
    );
}
echo $dryRun ? " (SIMULACIÓN — no se cambió nada)\n" : "";
echo "═══════════════════════════════════════════════════════════════\n\n";

/** Actualiza la referencia (columna) de todas las filas que apuntan al nombre viejo. */
function actualizar_referencia(PDO $pdo, array $f, $old, $nuevo)
{
    try {
        $upd = $pdo->prepare("UPDATE {$f['tabla']} SET {$f['columna']} = ? WHERE {$f['columna']} = ?");
        $upd->execute(array($nuevo, $old));
    } catch (PDOException $e) {
        fwrite(STDERR, "   [ERROR BD] {$f['tabla']}: " . $e->getMessage() . "\n");
    }
}

/** Formatea bytes a KB/MB. */
function bovs_formato_bytes($bytes)
{
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 1) . ' MB';
    }
    return round($bytes / 1024, 1) . ' KB';
}
