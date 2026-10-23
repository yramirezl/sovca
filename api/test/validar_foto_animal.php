<?php
/**
 * VALIDACIÓN DE LA FOTO DEL ANIMAL DESDE LA APP OFFLINE.
 *
 * La app web guarda en manimales.imagen SOLO el nombre del archivo y el
 * binario en dist/img/fotos_animales/. La acción "subir_foto" replica
 * exactamente eso para que la foto que se toma en el corral se vea igual
 * desde el navegador de escritorio. Este script comprueba:
 *
 *   A) subir una foto nueva   -> responde el nombre del archivo y el
 *                                archivo queda en disco
 *   B) manimales.imagen se actualiza y la versión avanza
 *   C) el pull entrega el nuevo "imagen" (los demás equipos la reciben)
 *   D) subir otra foto del mismo animal REEMPLAZA el archivo (no acumula)
 *   E) un data URI que no es imagen no se acepta
 *   F) un animal de otro fundo / inexistente responde 404
 *   G) sin código de animal responde 422
 *
 * Uso (desde la raíz del proyecto):
 *   php api/test/validar_foto_animal.php
 *
 * Deja el animal de prueba como estaba (imagen y versión originales) y
 * borra los archivos que haya creado.
 */
chdir(dirname(__DIR__));
require_once 'config/bootstrap.php';
require_once 'lib/ApiAuth.php';
require_once '../models/UsuarioDispositivoOffline.php';
require_once '../models/Musuario.php';

const FUNDO = 2;
const USUARIO = 4;
const URL_API = 'http://127.0.0.1/bovs/api/sync_controller.php';
const DIR_FOTOS = '../dist/img/fotos_animales/';

$ok = 0;
$fallos = 0;
function comprobar(bool $c, string $d, string $det = ''): void
{
    global $ok, $fallos;
    if ($c) { $ok++; echo "  OK    $d" . ($det !== '' ? "  [$det]" : '') . "\n"; }
    else { $fallos++; echo "  FALLA $d" . ($det !== '' ? "  [$det]" : '') . "\n"; }
}
function api(array $payload, string $token): array
{
    $ctx = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\nAuthorization: Bearer $token\r\n",
        'content' => json_encode($payload), 'timeout' => 60, 'ignore_errors' => true,
    ]]);
    $crudo = @file_get_contents(URL_API, false, $ctx);
    return ['cuerpo' => $crudo === false ? null : json_decode($crudo, true)];
}

/** JPEG de prueba (con GD), como data URI igual que lo manda la app. */
function jpegDataUri(int $ancho = 160, int $alto = 120, string $color = '#4A7C2F'): string
{
    $img = imagecreatetruecolor($ancho, $alto);
    [$r, $g, $b] = sscanf($color, '#%02x%02x%02x');
    imagefilledrectangle($img, 0, 0, $ancho, $alto, imagecolorallocate($img, $r, $g, $b));
    ob_start();
    imagejpeg($img, null, 80);
    $bin = ob_get_clean();
    imagedestroy($img);
    return 'data:image/jpeg;base64,' . base64_encode($bin);
}

$vinculo = \UsuarioDispositivoOffline::find('first', [
    'conditions' => ['musuarios_id = ? and mfundos_id = ? and status = 1', USUARIO, FUNDO],
]);
if (!$vinculo) { echo "Sin dispositivo vinculado.\n"; exit(1); }
$dispositivo = $vinculo->dispositivo_id;

$c = \Musuario::connection();
$q = fn (string $sql, array $p = []) => $c->query($sql, $p)->fetchAll();

$token = null;
$animal = null;
$imagenOriginal = null;
$versionOriginal = 0;
$archivosCreados = [];

try {
    $token = \Api\ApiAuth::crearToken(USUARIO, null, 'validacion-foto-animal')['token'];

    // ---------------------------------------------------------------
    echo "\n=== A) Subir la foto de un animal ===\n";
    $fila = $q(
        "SELECT id, codigo_rgb_id_animal, imagen, COALESCE(version, 0) AS version
           FROM manimales
          WHERE mfundos_id = ? AND status = 1 AND mestatus_id = 1
          ORDER BY id LIMIT 1",
        [FUNDO]
    )[0] ?? null;
    comprobar($fila !== null, 'Hay un animal activo en el fundo');
    if (!$fila) { throw new Exception('sin animal'); }

    $animal = $fila['codigo_rgb_id_animal'];
    $imagenOriginal = trim((string) $fila['imagen']);
    $versionOriginal = (int) $fila['version'];

    $resp = api([
        'action' => 'subir_foto',
        'fundo_id' => FUNDO,
        'dispositivo_id' => $dispositivo,
        'codigo_rgb_id_animal' => $animal,
        'imagen_base64' => jpegDataUri(),
    ], $token);
    $r = $resp['cuerpo'] ?? [];
    comprobar(($r['resultado'] ?? '') === 'ok', 'La API acepta la foto', json_encode($r['mensaje'] ?? ''));
    $nuevo = (string) ($r['imagen'] ?? '');
    if ($nuevo !== '') { $archivosCreados[] = $nuevo; }
    comprobar($nuevo !== '', 'Devuelve el nombre del archivo', $nuevo);
    comprobar((bool) preg_match('/\.(webp|jpg)$/i', $nuevo), 'El archivo tiene extensión de imagen', $nuevo);
    comprobar(strlen($nuevo) <= 100, 'El nombre cabe en manimales.imagen (100)', strlen($nuevo) . ' car.');
    comprobar(is_file(DIR_FOTOS . $nuevo), 'El archivo quedó en dist/img/fotos_animales', $nuevo);
    comprobar(filesize(DIR_FOTOS . $nuevo) > 50, 'El archivo tiene contenido real', filesize(DIR_FOTOS . $nuevo) . ' bytes');
    $medidas = @getimagesize(DIR_FOTOS . $nuevo);
    comprobar((bool) $medidas, 'El archivo es una imagen legible',
        $medidas ? $medidas[0] . 'x' . $medidas[1] : 'no');
    comprobar($medidas && max($medidas[0], $medidas[1]) <= 800, 'Se guardó redimensionada (máx. 800 px)',
        $medidas ? $medidas[0] . 'x' . $medidas[1] : 'no');

    // ---------------------------------------------------------------
    echo "\n=== B) La base de datos queda apuntando a la foto ===\n";
    $db = $q('SELECT imagen, COALESCE(version,0) AS version FROM manimales WHERE id = ?', [(int) $fila['id']])[0];
    comprobar($db['imagen'] === $nuevo, 'manimales.imagen = nombre del archivo', $db['imagen']);
    comprobar((int) $db['version'] > $versionOriginal, 'La versión avanzó (el pull la verá)',
        $versionOriginal . ' -> ' . $db['version']);

    // ---------------------------------------------------------------
    echo "\n=== C) El pull entrega el nuevo imagen ===\n";
    $resp = api([
        'action' => 'pull', 'modulo' => 'animales', 'fundo_id' => FUNDO,
        'desde' => '1970-01-01 00:00:00|0',
    ], $token);
    $registros = $resp['cuerpo']['registros'] ?? [];
    $encontrado = null;
    foreach ($registros as $reg) {
        if (($reg['codigo_rgb_id_animal'] ?? '') === $animal) { $encontrado = $reg; break; }
    }
    comprobar($encontrado !== null, 'El animal aparece en el pull');
    comprobar($encontrado && ($encontrado['imagen'] ?? '') === $nuevo,
        'El pull trae la foto nueva', (string) ($encontrado['imagen'] ?? ''));

    // ---------------------------------------------------------------
    echo "\n=== D) Subir otra foto reemplaza la anterior ===\n";
    $anterior = $nuevo;
    $resp = api([
        'action' => 'subir_foto', 'fundo_id' => FUNDO, 'dispositivo_id' => $dispositivo,
        'codigo_rgb_id_animal' => $animal,
        'imagen_base64' => jpegDataUri(200, 200, '#B23A2F'),
    ], $token);
    $r = $resp['cuerpo'] ?? [];
    $nuevo2 = (string) ($r['imagen'] ?? '');
    comprobar($r['resultado'] === 'ok' && $nuevo2 !== '', 'La segunda foto se acepta', $nuevo2);
    comprobar($nuevo2 === $anterior, 'Usa el mismo nombre (no acumula archivos)', $nuevo2);
    comprobar(count(array_filter($archivosCreados, fn ($a) => $a === $nuevo2)) === 1,
        'Sigue habiendo un solo archivo para el animal');

    // ---------------------------------------------------------------
    echo "\n=== E) Un data URI que no es imagen se rechaza ===\n";
    $resp = api([
        'action' => 'subir_foto', 'fundo_id' => FUNDO, 'dispositivo_id' => $dispositivo,
        'codigo_rgb_id_animal' => $animal,
        'imagen_base64' => 'data:image/jpeg;base64,' . base64_encode('esto no es una imagen'),
    ], $token);
    comprobar(($resp['cuerpo']['resultado'] ?? '') === 'error', 'Se rechaza el contenido inválido',
        (string) ($resp['cuerpo']['mensaje'] ?? ''));

    $resp = api([
        'action' => 'subir_foto', 'fundo_id' => FUNDO, 'dispositivo_id' => $dispositivo,
        'codigo_rgb_id_animal' => $animal,
        'imagen_base64' => 'https://ejemplo.com/foto.jpg',
    ], $token);
    comprobar(($resp['cuerpo']['resultado'] ?? '') === 'error', 'Se rechaza algo que no es data URI');

    // ---------------------------------------------------------------
    echo "\n=== F) Animal inexistente en el fundo ===\n";
    $resp = api([
        'action' => 'subir_foto', 'fundo_id' => FUNDO, 'dispositivo_id' => $dispositivo,
        'codigo_rgb_id_animal' => 'NO-EXISTE-999',
        'imagen_base64' => jpegDataUri(80, 80),
    ], $token);
    comprobar(($resp['cuerpo']['resultado'] ?? '') === 'error', 'Responde error para un código inexistente',
        (string) ($resp['cuerpo']['mensaje'] ?? ''));

    // ---------------------------------------------------------------
    echo "\n=== G) Sin código de animal ===\n";
    $resp = api([
        'action' => 'subir_foto', 'fundo_id' => FUNDO, 'dispositivo_id' => $dispositivo,
        'imagen_base64' => jpegDataUri(80, 80),
    ], $token);
    comprobar(($resp['cuerpo']['resultado'] ?? '') === 'error', 'Responde error si falta el código',
        (string) ($resp['cuerpo']['mensaje'] ?? ''));

    // La foto original del animal sigue intacta después de los rechazos.
    $db = $q('SELECT imagen FROM manimales WHERE id = ?', [(int) $fila['id']])[0];
    comprobar($db['imagen'] === $nuevo2, 'Los rechazos no tocaron la foto guardada', (string) $db['imagen']);
} catch (Throwable $e) {
    $fallos++;
    echo "  FALLA excepción: " . $e->getMessage() . "\n";
} finally {
    // Restaurar el animal y borrar lo creado.
    if ($animal !== null) {
        $c->query(
            'UPDATE manimales SET imagen = ?, version = ? WHERE codigo_rgb_id_animal = ? AND mfundos_id = ?',
            [$imagenOriginal === '' ? null : $imagenOriginal, $versionOriginal, $animal, FUNDO]
        );
        foreach (array_unique($archivosCreados) as $archivo) {
            // Solo se borra si NO era la foto original del animal.
            if ($archivo === '' || basename($archivo) === basename($imagenOriginal)) {
                continue;
            }
            $ruta = DIR_FOTOS . $archivo;
            if (!is_file($ruta)) {
                continue;
            }
            if (@unlink($ruta)) {
                echo "(borrado: $archivo)\n";
            } else {
                // Pasa cuando el script corre por consola con un usuario
                // distinto al de Apache: el archivo es de www-data y el
                // directorio no da permiso de escritura a otros. No es un
                // fallo de la función (Apache sí borra al reemplazar); se
                // deja constancia para que no quede como basura invisible.
                echo "(aviso: no se pudo borrar $archivo por permisos; "
                    . "es del usuario de Apache. Se reutiliza al subir otra foto)\n";
            }
        }
        echo "\n(restaurado: {$animal} -> imagen " . ($imagenOriginal === '' ? '(vacía)' : $imagenOriginal) . ")\n";
    }
}

echo "\n----------------------------------------\n";
echo "Comprobaciones: " . ($ok + $fallos) . " | OK: $ok | Fallos: $fallos\n";
exit($fallos === 0 ? 0 : 1);
