<?php
/**
 * VALIDACIÓN: el movimiento de rebaño se aplica completo o no se aplica.
 *
 * Antes, si una parte de los animales no era válida (fuera del lote de
 * origen, dado de baja o inexistente), el servidor creaba el movimiento
 * sólo con los válidos: el registro subía "bien" y el operario nunca se
 * enteraba de que el resto del rebaño se había quedado en el potrero de
 * origen.
 *
 * Esta prueba manda un movimiento con 1 animal del origen y 1 fuera del
 * origen y comprueba que:
 *   · el movimiento se rechaza,
 *   · el mensaje avisa que no se movió nada y nombra el animal culpable,
 *   · el animal válido NO se movió,
 *   · no queda ningún movimiento a medias en la base.
 *
 * Uso (desde la raíz del proyecto, con Apache sirviendo /sovca):
 *   php api/test/validar_movimiento_atomico.php
 *
 * ⚠ Usa la base configurada: NO ejecutar contra producción.
 */

chdir(dirname(__DIR__));
require_once 'config/bootstrap.php';
require_once 'lib/ApiAuth.php';
require_once '../models/UsuarioDispositivoOffline.php';
require_once '../models/Musuario.php';

const FUNDO = 2;
const USUARIO = 4;
const LOTE_ORIGEN = 7;
const LOTE_DESTINO = 6;
const URL_API = 'http://127.0.0.1/sovca/api/sync_controller.php';

$ok = 0;
$fallos = 0;

function comprobar(bool $c, string $d, string $det = ''): void
{
    global $ok, $fallos;
    if ($c) { $ok++; echo "  OK    $d" . ($det !== '' ? "  [$det]" : '') . "\n"; }
    else { $fallos++; echo "  FALLA $d" . ($det !== '' ? "  [$det]" : '') . "\n"; }
}

function uuid4(): string
{
    return sprintf(
        '%04x%04x-%04x-4%03x-%04x-%04x%04x%04x',
        random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff),
        random_int(0, 0x0fff), random_int(0x8000, 0xbfff),
        random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff)
    );
}

function llamar_api(array $payload, string $token): array
{
    $contexto = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nAuthorization: Bearer " . $token . "\r\n",
            'content' => json_encode($payload),
            'timeout' => 25,
            'ignore_errors' => true,
        ],
    ]);
    $crudo = @file_get_contents(URL_API, false, $contexto);
    $estado = 0;
    foreach (($http_response_header ?? []) as $c) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $c, $m)) $estado = (int) $m[1];
    }
    return ['http' => $estado, 'cuerpo' => $crudo === false ? null : json_decode($crudo, true)];
}

$vinculo = \UsuarioDispositivoOffline::find('first', [
    'conditions' => ['musuarios_id = ? and mfundos_id = ? and status = 1', USUARIO, FUNDO],
]);
if (!$vinculo) { echo "Sin dispositivo vinculado.\n"; exit(1); }
$dispositivo = $vinculo->dispositivo_id;
$conexion = \Musuario::connection();

$uuidA = uuid4();
$uuidB = uuid4();
$uuidMov = uuid4();

function limpiar(): void
{
    global $conexion, $uuidA, $uuidB, $uuidMov;
    $fila = $conexion->query('SELECT id FROM mmovimientos_rebano WHERE uuid_local = ?', [$uuidMov])->fetch();
    if ($fila) {
        $conexion->query('DELETE FROM mmovimientos_rebano_detalle WHERE mmovimientos_rebano_id = ?', [(int) $fila['id']]);
        $conexion->query('DELETE FROM mmovimientos_rebano WHERE id = ?', [(int) $fila['id']]);
    }
    foreach ([$uuidA, $uuidB] as $uuid) {
        $f = $conexion->query('SELECT id FROM manimales WHERE uuid_local = ?', [$uuid])->fetch();
        if ($f) {
            $conexion->query('DELETE FROM manimalxfundos WHERE manimales_id = ?', [(int) $f['id']]);
            $conexion->query('DELETE FROM manimales WHERE id = ?', [(int) $f['id']]);
        }
    }
}

try {
    $token = \Api\ApiAuth::crearToken(USUARIO, null, 'prueba-atomicidad')['token'];
    $base = ['fundo_id' => FUNDO, 'dispositivo_id' => $dispositivo];

    $alta = function (string $uuid, int $lote, string $nombre) use ($base, $token) {
        $r = llamar_api($base + [
            'action' => 'push',
            'modulo' => 'animales',
            'cambios' => [[
                'uuid_local' => $uuid,
                'version_base' => null,
                'datos' => [
                    'codigo_rgb_id_animal' => 'SOVCA-0' . FUNDO . '-PEND-' . random_int(100000, 999999),
                    'nombre' => $nombre,
                    'sexo' => 2,
                    'nro_arete' => 'A' . random_int(100000, 999999),
                    'aptitud' => 0,
                    'mrazas_id' => 0,
                    'estatus_animal' => 1,
                    'servicio' => 0,
                    'nac_fundo' => true,
                    'mlotes_id' => $lote,
                ],
            ]],
        ], $token);
        return $r['cuerpo']['resultados'][0]['campos_resueltos']['codigo_rgb_id_animal'] ?? null;
    };

    echo "=== Preparación ===\n";
    $codigoA = $alta($uuidA, LOTE_ORIGEN, 'ATOMICO ORIGEN');
    $codigoB = $alta($uuidB, LOTE_DESTINO, 'ATOMICO DESTINO');
    comprobar(is_string($codigoA) && is_string($codigoB), 'Se crearon los dos animales de prueba', "$codigoA / $codigoB");

    echo "\n=== Movimiento con 1 animal del origen y 1 fuera del origen ===\n";
    $mov = llamar_api($base + [
        'action' => 'push',
        'modulo' => 'movimiento_rebano',
        'cambios' => [[
            'uuid_local' => $uuidMov,
            'version_base' => null,
            'datos' => [
                'tipo_movimiento' => 'LOTE',
                'mlotes_origen_id' => LOTE_ORIGEN,
                'mlotes_destino_id' => LOTE_DESTINO,
                'fecha_movimiento' => date('Y-m-d H:i:s'),
                'motivo' => 'Prueba de atomicidad',
                'observaciones' => '',
                'animales' => [$codigoA, $codigoB],
            ],
        ]],
    ], $token);

    $res = $mov['cuerpo']['resultados'][0] ?? [];
    $mensaje = (string) ($res['mensaje'] ?? '');
    comprobar(($res['resultado'] ?? '') !== 'creado', 'El movimiento se rechaza', (string) ($res['resultado'] ?? 'sin respuesta'));
    comprobar(strpos($mensaje, 'rechazó completo') !== false, 'El mensaje avisa que no se movió nada', $mensaje);
    comprobar(strpos($mensaje, $codigoB) !== false, 'El mensaje nombra el animal fuera de origen', $codigoB);

    $loteA = $conexion->query(
        'SELECT mlotes_id FROM manimales WHERE codigo_rgb_id_animal = ?', [$codigoA]
    )->fetch();
    comprobar(
        $loteA && (int) $loteA['mlotes_id'] === LOTE_ORIGEN,
        'El animal válido NO se movió (antes se movía solo)',
        'lote=' . ($loteA['mlotes_id'] ?? '—')
    );

    $movGuardado = $conexion->query('SELECT id FROM mmovimientos_rebano WHERE uuid_local = ?', [$uuidMov])->fetch();
    comprobar(!$movGuardado, 'No quedó un movimiento a medias guardado');
} finally {
    limpiar();
    echo "\nLimpieza realizada.\n";
}

echo "\nResultado: $ok correctas, $fallos con falla.\n";
exit($fallos === 0 ? 0 : 1);
