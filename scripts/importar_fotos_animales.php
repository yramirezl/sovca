<?php
/**
 * scripts/importar_fotos_animales.php
 * ---------------------------------------------------------------------------
 * Inventario y carga de las FOTOS DE LOS ANIMALES.
 *
 * La web (y la app sin conexión, desde la 4.39) guarda el binario en
 * dist/img/fotos_animales/ y en manimales.imagen SOLO el nombre del
 * archivo. Cuando faltan los archivos —p. ej. al restaurar la base en un
 * servidor nuevo, porque el respaldo trae los datos pero no las fotos—
 * los animales quedan con la referencia puesta y sin imagen.
 *
 * Este script hace dos cosas:
 *
 *   1. INVENTARIO: dice, por fundo, cuántos animales tienen foto
 *      registrada, cuántos archivos están realmente en disco y cuáles
 *      faltan. Puede exportar el listado a CSV para pedirle las fotos al
 *      cliente.
 *
 *   2. IMPORTACIÓN: toma una carpeta donde estén las fotos recibidas y
 *      las deja en dist/img/fotos_animales/ **con el nombre exacto que
 *      espera la base de datos**, así la web y la app las ven sin tocar
 *      ninguna fila. Empareja por:
 *          a) nombre idéntico a manimales.imagen
 *          b) <CODIGO>.<ext>
 *          c) <CODIGO>_<lo que sea>.<ext>
 *      (comparando sin distinguir mayúsculas ni acentos)
 *
 * Uso (desde la raíz del proyecto):
 *   php scripts/importar_fotos_animales.php                        # solo inventario
 *   php scripts/importar_fotos_animales.php --csv=/tmp/faltan.csv  # + listado
 *   php scripts/importar_fotos_animales.php --origen=/ruta/fotos   # simula (dry-run)
 *   php scripts/importar_fotos_animales.php --origen=/ruta/fotos --aplicar
 *
 * Opciones:
 *   --origen=DIR   Carpeta con las fotos recibidas (se lee, no se modifica).
 *   --destino=DIR  Carpeta destino (por defecto dist/img/fotos_animales).
 *   --aplicar      Ejecuta de verdad (sin esto todo es simulación).
 *   --csv=RUTA     Escribe el inventario detallado en CSV (UTF-8, con ;).
 *   --fundo=ID     Limita el trabajo a un fundo.
 *   --webp         Convierte lo importado a WebP con lib/imagen_helper.php
 *                  (800 px, calidad 80) y actualiza manimales.imagen al
 *                  nombre nuevo. Se actualiza la BD, así que se avisa
 *                  cuántas filas cambiarían.
 *   --max=800      Lado mayor en px cuando se usa --webp.
 *   --q=80         Calidad 1-100 cuando se usa --webp.
 *   --host= --port= --db= --user= --pass=   Sobrescriben la conexión.
 *   --help
 *
 * La conexión se lee de config/conexion.php (DSN pgsql://...).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script solo puede ejecutarse por consola (CLI).\n");
}

const DIR_FOTOS_REL = 'dist/img/fotos_animales';
const EXT_VALIDAS = array('jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'jfif');

$ROOT = dirname(__DIR__);
$DIR_FOTOS = $ROOT . '/' . DIR_FOTOS_REL;

// ─────────────────────────────  Argumentos  ─────────────────────────────
$opts = array();
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z0-9\-]+)(?:=(.*))?$/i', $a, $m)) {
        $opts[strtolower($m[1])] = isset($m[2]) ? $m[2] : true;
    }
}

if (isset($opts['help'])) {
    echo "Uso: php scripts/importar_fotos_animales.php [--origen=DIR] [--aplicar]\n";
    echo "                                            [--destino=DIR] [--csv=RUTA] [--fundo=ID] [--webp]\n";
    echo "                                            [--max=800] [--q=80]\n";
    echo "\nSin --aplicar no se escribe nada: solo se informa qué haría.\n";
    exit(0);
}

$APLICAR   = isset($opts['aplicar']);
$USAR_WEBP = isset($opts['webp']);
$MAX_DIM   = isset($opts['max']) && (int) $opts['max'] > 0 ? (int) $opts['max'] : 800;
$CALIDAD   = isset($opts['q']) && (int) $opts['q'] > 0 ? min(100, (int) $opts['q']) : 80;
$FUNDO     = isset($opts['fundo']) && (int) $opts['fundo'] > 0 ? (int) $opts['fundo'] : 0;
$ORIGEN    = isset($opts['origen']) && $opts['origen'] !== true ? rtrim((string) $opts['origen'], '/') : null;
$CSV       = isset($opts['csv']) && $opts['csv'] !== true ? (string) $opts['csv'] : null;
$DESTINO   = isset($opts['destino']) && $opts['destino'] !== true
    ? rtrim((string) $opts['destino'], '/')
    : $DIR_FOTOS;

if ($USAR_WEBP) {
    require $ROOT . '/lib/imagen_helper.php';
}

// ─────────────────────────────  Conexión  ─────────────────────────────

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

// ─────────────────────────────  Utilidades  ─────────────────────────────

/**
 * Clave para comparar nombres: minúsculas, sin acentos, espacios
 * colapsados. Sirve porque los archivos recibidos suelen venir con otro
 * uso de mayúsculas que el registrado en la base.
 */
function clave_nombre($texto)
{
    $t = (string) $texto;
    if (function_exists('iconv')) {
        $c = @iconv('UTF-8', 'ASCII//TRANSLIT', $t);
        if ($c !== false) {
            $t = $c;
        }
    }
    $t = strtolower($t);
    return preg_replace('/\s+/', ' ', trim($t));
}

/** Nombre base (sin extensión) de un archivo de imagen. */
function base_sin_extension($archivo)
{
    $info = pathinfo($archivo);
    return isset($info['filename']) ? $info['filename'] : $archivo;
}

function extension_de($archivo)
{
    $ext = strtolower((string) pathinfo($archivo, PATHINFO_EXTENSION));
    return in_array($ext, EXT_VALIDAS, true) ? $ext : null;
}

/** Código del animal según las convenciones vistas: <CODIGO>.ext o <CODIGO>_algo.ext */
function codigo_de_nombre($archivo)
{
    $base = base_sin_extension($archivo);
    if (preg_match('/^(SOVCA-[0-9A-Za-z]+-[0-9A-Za-z]+)/', $base, $m)) {
        return strtoupper($m[1]);
    }
    $pos = strpos($base, '_');
    return $pos === false ? strtoupper($base) : strtoupper(substr($base, 0, $pos));
}

function fmt_bytes($bytes)
{
    $u = array('B', 'KB', 'MB', 'GB');
    $i = 0;
    while ($bytes >= 1024 && $i < 3) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 1) . ' ' . $u[$i];
}

// ─────────────────────────────  Inventario  ─────────────────────────────

echo "──────────────────────────────────────────────────────────────\n";
echo " Fotos de animales · {$cfg['db']} @ {$cfg['host']}\n";
echo " Carpeta: {$DESTINO}\n";
echo "──────────────────────────────────────────────────────────────\n";

if (!is_dir($DIR_FOTOS)) {
    echo "AVISO: la carpeta no existe todavía; se creará al importar.\n";
}

$sql = "SELECT mfundos_id,
               codigo_rgb_id_animal AS codigo,
               TRIM(imagen) AS imagen
          FROM manimales
         WHERE TRIM(COALESCE(imagen, '')) <> ''";
$params = array();
if ($FUNDO > 0) {
    $sql .= ' AND mfundos_id = ?';
    $params[] = $FUNDO;
}
$sql .= ' ORDER BY mfundos_id, codigo_rgb_id_animal';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$porFundo = array();
$faltantes = array();
$presentes = 0;

foreach ($filas as $f) {
    $fundo = (int) $f['mfundos_id'];
    if (!isset($porFundo[$fundo])) {
        $porFundo[$fundo] = array('con_foto' => 0, 'presentes' => 0, 'faltan' => 0);
    }
    $porFundo[$fundo]['con_foto']++;
    $ruta = $DESTINO . '/' . $f['imagen'];
    if (is_file($ruta)) {
        $presentes++;
        $porFundo[$fundo]['presentes']++;
    } else {
        $porFundo[$fundo]['faltan']++;
        $faltantes[] = $f;
    }
}

// Archivos en disco que ninguna fila referencia.
$referenciados = array();
foreach ($filas as $f) {
    $referenciados[$f['imagen']] = true;
}
$huerfanos = array();
if (is_dir($DESTINO)) {
    foreach (scandir($DESTINO) as $archivo) {
        if ($archivo === '.' || $archivo === '..' || extension_de($archivo) === null) {
            continue;
        }
        if (!isset($referenciados[$archivo])) {
            $huerfanos[] = $archivo;
        }
    }
}

$totalAnimales = (int) $pdo->query('SELECT COUNT(*) FROM manimales' . ($FUNDO > 0 ? ' WHERE mfundos_id = ' . $FUNDO : ''))->fetchColumn();

echo "\nAnimales en la base: {$totalAnimales}\n";
echo "Con foto registrada: " . count($filas) . "\n";
echo "  · archivo presente .... {$presentes}\n";
echo "  · archivo faltante .... " . count($faltantes) . "\n";
echo "Archivos en la carpeta sin fila que los use: " . count($huerfanos) . "\n";

if ($porFundo) {
    echo "\nPor fundo:\n";
    printf("  %-8s %10s %10s %10s\n", 'fundo', 'con foto', 'presentes', 'faltan');
    ksort($porFundo);
    foreach ($porFundo as $fundo => $c) {
        printf("  %-8s %10d %10d %10d\n", $fundo, $c['con_foto'], $c['presentes'], $c['faltan']);
    }
}

if ($huerfanos) {
    echo "\nArchivos sueltos (no los usa ninguna fila):\n";
    foreach (array_slice($huerfanos, 0, 10) as $h) {
        echo "  · {$h}\n";
    }
    if (count($huerfanos) > 10) {
        echo '  … y ' . (count($huerfanos) - 10) . " más\n";
    }
}

// ─────────────────────────────  CSV  ─────────────────────────────

if ($CSV !== null) {
    $fh = @fopen($CSV, 'w');
    if ($fh === false) {
        fwrite(STDERR, "No se pudo escribir el CSV en {$CSV}\n");
    } else {
        fprintf($fh, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM: Excel abre bien los acentos
        fputcsv($fh, array('fundo', 'codigo_animal', 'imagen_registrada', 'estado', 'archivo_origen'), ';');
        foreach ($filas as $f) {
            $estado = is_file($DESTINO . '/' . $f['imagen']) ? 'presente' : 'FALTA';
            fputcsv($fh, array($f['mfundos_id'], $f['codigo'], $f['imagen'], $estado, ''), ';');
        }
        foreach ($huerfanos as $h) {
            fputcsv($fh, array('', '', $h, 'huerfano', ''), ';');
        }
        fclose($fh);
        echo "\nCSV escrito: {$CSV}\n";
    }
}

// ─────────────────────────────  Importación  ─────────────────────────────

if ($ORIGEN === null) {
    echo "\n(No se indicó --origen: solo inventario.)\n";
    exit(0);
}
if (!is_dir($ORIGEN)) {
    fwrite(STDERR, "\nERROR: la carpeta de origen no existe: {$ORIGEN}\n");
    exit(1);
}
if (!$faltantes) {
    echo "\nNo falta ninguna foto: no hay nada que importar.\n";
    exit(0);
}

echo "\n──────────────────────────────────────────────────────────────\n";
echo " Importando desde: {$ORIGEN}\n";
echo $APLICAR ? " Modo: APLICAR (se copian los archivos)\n" : " Modo: SIMULACIÓN (usar --aplicar para ejecutar)\n";
echo "──────────────────────────────────────────────────────────────\n";

// Índice de la carpeta de origen (una sola lectura).
$indice = array('exacto' => array(), 'codigo' => array(), 'base' => array());
$iter = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($ORIGEN, FilesystemIterator::SKIP_DOTS)
);
foreach ($iter as $archivo) {
    if (!$archivo->isFile()) {
        continue;
    }
    $nombre = $archivo->getFilename();
    if (extension_de($nombre) === null) {
        continue;
    }
    $ruta = $archivo->getPathname();
    $clave = clave_nombre($nombre);
    $indice['exacto'][$clave] = $ruta;
    $codigo = clave_nombre(codigo_de_nombre($nombre));
    if (!isset($indice['codigo'][$codigo])) {
        $indice['codigo'][$codigo] = $ruta;
    }
    $indice['base'][clave_nombre(base_sin_extension($nombre))] = $ruta;
}
echo 'Archivos candidatos en el origen: ' . count($indice['exacto']) . "\n\n";

/** Busca en el índice la foto que corresponde a una fila. */
function buscar_origen(array $indice, array $fila)
{
    $imagen = $fila['imagen'];
    $claveExacta = clave_nombre($imagen);
    if (isset($indice['exacto'][$claveExacta])) {
        return array($indice['exacto'][$claveExacta], 'nombre exacto');
    }
    // Mismo nombre con otra extensión (SOVCA-02-060.jpeg en la base y
    // SOVCA-02-060.jpg en la carpeta).
    $base = clave_nombre(base_sin_extension($imagen));
    if (isset($indice['base'][$base])) {
        return array($indice['base'][$base], 'mismo nombre, otra extensión');
    }
    $codigo = clave_nombre($fila['codigo']);
    if (isset($indice['codigo'][$codigo])) {
        return array($indice['codigo'][$codigo], 'por código');
    }
    return array(null, null);
}

$importadas = 0;
$noEncontradas = 0;
$omitidas = 0;
$errores = 0;
$actualizaciones = array();

if ($APLICAR && !is_dir($DESTINO)) {
    @mkdir($DESTINO, 0775, true);
}

foreach ($faltantes as $fila) {
    list($rutaOrigen, $criterio) = buscar_origen($indice, $fila);
    $rutaDestino = $DESTINO . '/' . $fila['imagen'];

    if ($rutaOrigen === null) {
        $noEncontradas++;
        continue;
    }
    if (is_file($rutaDestino)) {
        $omitidas++;
        continue;
    }

    $info = @getimagesize($rutaOrigen);
    if (!$info) {
        echo "  [ERROR] no es una imagen válida: {$rutaOrigen}\n";
        $errores++;
        continue;
    }

    echo "  {$fila['codigo']}  <-  " . basename($rutaOrigen) . "  ({$criterio})\n";

    if (!$APLICAR) {
        $importadas++;
        continue;
    }

    if ($USAR_WEBP) {
        // Convertir: el nombre final cambia a .webp, así que hay que
        // actualizar la referencia en la base de datos.
        $base = base_sin_extension($fila['imagen']);
        $nuevo = bovs_imagen_procesar($rutaOrigen, $DESTINO, $base, $MAX_DIM, $CALIDAD);
        if ($nuevo === null) {
            echo "    [ERROR] no se pudo convertir a WebP\n";
            $errores++;
            continue;
        }
        if ($nuevo !== $fila['imagen']) {
            $actualizaciones[] = array(
                'fundo' => (int) $fila['mfundos_id'],
                'codigo' => $fila['codigo'],
                'viejo' => $fila['imagen'],
                'nuevo' => $nuevo,
            );
        }
        $importadas++;
        continue;
    }

    if (!@copy($rutaOrigen, $rutaDestino)) {
        echo "    [ERROR] no se pudo copiar (¿permisos de {$DESTINO}?)\n";
        $errores++;
        continue;
    }
    @chmod($rutaDestino, 0664);
    $importadas++;
}

if ($APLICAR && $actualizaciones) {
    $up = $pdo->prepare(
        'UPDATE manimales SET imagen = ?, version = COALESCE(version, 0) + 1, updated = NOW()
          WHERE mfundos_id = ? AND codigo_rgb_id_animal = ?'
    );
    $okUpd = 0;
    foreach ($actualizaciones as $u) {
        try {
            $up->execute(array($u['nuevo'], $u['fundo'], $u['codigo']));
            $okUpd += $up->rowCount();
        } catch (PDOException $e) {
            echo "  [ERROR] no se pudo actualizar {$u['codigo']}: " . $e->getMessage() . "\n";
            $errores++;
        }
    }
    echo "\nReferencias actualizadas en la base (--webp): {$okUpd}\n";
} elseif ($actualizaciones) {
    echo "\nCon --webp cambiarían " . count($actualizaciones) . " referencias en manimales.imagen.\n";
}

// ─────────────────────────────  Resumen  ─────────────────────────────

echo "\n──────────────────────────────────────────────────────────────\n";
echo ' ' . ($APLICAR ? 'Importadas' : 'Se importarían') . ": {$importadas}\n";
echo " Sin archivo en el origen: {$noEncontradas}\n";
echo " Omitidas (ya existían):   {$omitidas}\n";
echo " Errores:                  {$errores}\n";
echo "──────────────────────────────────────────────────────────────\n";
if (!$APLICAR) {
    echo " Esto fue una SIMULACIÓN. Repetí el comando con --aplicar.\n";
} else {
    echo " Listo. Si el servidor web corre con otro usuario, revisá los permisos\n";
    echo " de {$DESTINO} (debe poder leerlo www-data).\n";
}
exit($errores > 0 ? 1 : 0);
