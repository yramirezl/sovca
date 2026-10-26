<?php
/**
 * VALIDACIÓN DE LA ANULACIÓN (BORRADO LÓGICO) DESDE LA APP OFFLINE — P1.
 *
 * Comprueba que un dispositivo pueda eliminar un registro que YA está
 * sincronizado, con la misma semántica que la web (status = 0):
 *
 *   A) crear un pesaje por la API (queda status = 1)
 *   B) anular con versión desactualizada  -> conflicto (no se toca nada)
 *   C) anular con la versión correcta     -> "eliminado" y status = 0
 *   D) reenviar la misma anulación        -> idempotente ("eliminado")
 *   E) el pull devuelve la fila anulada con status = 0
 *   F) anular algo que nunca llegó al servidor -> "eliminado" sin error
 *   G) un servicio con parto registrado NO se anula
 *   H) un módulo que no admite anulación responde error
 *
 * Uso (desde la raíz del proyecto):
 *   php api/test/validar_anulacion.php
 *
 * Crea un token temporal y borra los registros que llegue a crear.
 */
chdir(dirname(__DIR__));
require_once 'config/bootstrap.php';
require_once 'lib/ApiAuth.php';
require_once '../models/UsuarioDispositivoOffline.php';
require_once '../models/Musuario.php';
require_once '../models/Mconsecutivopeso.php';

const FUNDO = 2;
const USUARIO = 4;
const URL_API = 'http://127.0.0.1/sovca/api/sync_controller.php';

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
        'content' => json_encode($payload), 'timeout' => 25, 'ignore_errors' => true,
    ]]);
    $crudo = @file_get_contents(URL_API, false, $ctx);
    return ['cuerpo' => $crudo === false ? null : json_decode($crudo, true)];
}
function uuid(): string
{
    return sprintf(
        '%04x%04x-%04x-4%03x-%04x-%04x%04x%04x',
        random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff),
        random_int(0, 0x0fff), random_int(0x8000, 0xbfff),
        random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff)
    );
}

$vinculo = \UsuarioDispositivoOffline::find('first', [
    'conditions' => ['musuarios_id = ? and mfundos_id = ? and status = 1', USUARIO, FUNDO],
]);
if (!$vinculo) { echo "Sin dispositivo vinculado.\n"; exit(1); }
$dispositivo = $vinculo->dispositivo_id;

$c = \Musuario::connection();
$q = fn (string $sql, array $p = []) => $c->query($sql, $p)->fetchAll();

$token = null;
$creados = [];

try {
    $token = \Api\ApiAuth::crearToken(USUARIO, null, 'validacion-anulacion')['token'];

    // ---------------------------------------------------------------
    echo "\n=== A) Crear un pesaje de prueba ===\n";
    $animal = $q(
        "SELECT codigo_rgb_id_animal FROM manimales
         WHERE mfundos_id = ? AND status = 1 AND mestatus_id = 1 ORDER BY id LIMIT 1",
        [FUNDO]
    )[0]['codigo_rgb_id_animal'] ?? null;
    comprobar($animal !== null, 'Hay un animal activo en el fundo', (string) $animal);

    $uuidPeso = uuid();
    $fecha = date('Y-m-d', strtotime('-913 days'));
    $resp = api([
        'action' => 'push', 'modulo' => 'pesos', 'fundo_id' => FUNDO, 'dispositivo_id' => $dispositivo,
        'cambios' => [[
            'uuid_local' => $uuidPeso,
            'datos' => ['codigo_rgb_id_animal' => $animal, 'fecha_pesaje' => $fecha, 'peso' => 321],
        ]],
    ], $token);
    $r = $resp['cuerpo']['resultados'][0] ?? [];
    comprobar(($r['resultado'] ?? '') === 'creado', 'El pesaje se crea', json_encode($r['resultado'] ?? null));
    $creados[] = $uuidPeso;
    $versionCreada = (int) ($r['version'] ?? 0);

    // ---------------------------------------------------------------
    echo "\n=== B) Anular con versión desactualizada ===\n";
    $resp = api([
        'action' => 'push', 'modulo' => 'pesos', 'fundo_id' => FUNDO, 'dispositivo_id' => $dispositivo,
        'cambios' => [[
            'uuid_local' => $uuidPeso,
            'version_base' => 999,
            'eliminado' => true,
            'datos' => [],
        ]],
    ], $token);
    $r = $resp['cuerpo']['resultados'][0] ?? [];
    comprobar(($r['resultado'] ?? '') === 'conflicto', 'Devuelve conflicto en vez de borrar', json_encode($r['resultado'] ?? null));
    $fila = $q("SELECT status FROM mconsecutivopesos WHERE uuid_local = ?", [$uuidPeso])[0] ?? null;
    comprobar((int) ($fila['status'] ?? -1) === 1, 'El registro sigue activo', json_encode($fila));

    // ---------------------------------------------------------------
    echo "\n=== C) Anular con la versión correcta ===\n";
    $resp = api([
        'action' => 'push', 'modulo' => 'pesos', 'fundo_id' => FUNDO, 'dispositivo_id' => $dispositivo,
        'cambios' => [[
            'uuid_local' => $uuidPeso,
            'version_base' => $versionCreada,
            'eliminado' => true,
            'datos' => [],
        ]],
    ], $token);
    $r = $resp['cuerpo']['resultados'][0] ?? [];
    comprobar(($r['resultado'] ?? '') === 'eliminado', 'La anulación se acepta', json_encode($r['resultado'] ?? null));
    $fila = $q("SELECT status, version FROM mconsecutivopesos WHERE uuid_local = ?", [$uuidPeso])[0] ?? null;
    comprobar((int) ($fila['status'] ?? -1) === 0, 'Queda con status = 0 (borrado lógico)', json_encode($fila));
    $versionAnulada = (int) ($fila['version'] ?? 0);
    comprobar($versionAnulada > $versionCreada, 'La versión sube para que el cambio viaje a otros equipos', "$versionCreada -> $versionAnulada");

    // ---------------------------------------------------------------
    echo "\n=== D) Reenviar la misma anulación ===\n";
    $resp = api([
        'action' => 'push', 'modulo' => 'pesos', 'fundo_id' => FUNDO, 'dispositivo_id' => $dispositivo,
        'cambios' => [[
            'uuid_local' => $uuidPeso,
            'version_base' => $versionAnulada,
            'eliminado' => true,
            'datos' => [],
        ]],
    ], $token);
    $r = $resp['cuerpo']['resultados'][0] ?? [];
    comprobar(($r['resultado'] ?? '') === 'eliminado', 'Es idempotente al reintentar', json_encode($r['resultado'] ?? null));
    $fila = $q("SELECT version FROM mconsecutivopesos WHERE uuid_local = ?", [$uuidPeso])[0] ?? null;
    comprobar((int) ($fila['version'] ?? 0) === $versionAnulada, 'No infla la versión', json_encode($fila));

    // ---------------------------------------------------------------
    echo "\n=== E) El pull informa la anulación ===\n";
    $encontrado = null;
    $desde = '';
    for ($pagina = 0; $pagina < 40; $pagina++) {
        $resp = api([
            'action' => 'pull', 'modulo' => 'pesos', 'fundo_id' => FUNDO,
            'dispositivo_id' => $dispositivo, 'desde' => $desde,
        ], $token);
        foreach ($resp['cuerpo']['registros'] ?? [] as $filaPull) {
            if (($filaPull['uuid_local'] ?? '') === $uuidPeso) { $encontrado = $filaPull; break 2; }
        }
        $desde = $resp['cuerpo']['siguiente_desde'] ?? '';
        if (empty($resp['cuerpo']['hay_mas'])) break;
    }
    comprobar($encontrado !== null, 'El registro anulado aparece en el pull', $encontrado ? 'encontrado' : 'no encontrado');
    comprobar($encontrado !== null && (int) $encontrado['status'] === 0, 'Llega con status = 0 para que el equipo lo borre');

    // ---------------------------------------------------------------
    echo "\n=== F) Anular algo que nunca llegó al servidor ===\n";
    $resp = api([
        'action' => 'push', 'modulo' => 'pesos', 'fundo_id' => FUNDO, 'dispositivo_id' => $dispositivo,
        'cambios' => [[
            'uuid_local' => uuid(),
            'version_base' => null,
            'eliminado' => true,
            'datos' => [],
        ]],
    ], $token);
    $r = $resp['cuerpo']['resultados'][0] ?? [];
    comprobar(($r['resultado'] ?? '') === 'eliminado', 'Responde eliminado sin fallar', json_encode($r['resultado'] ?? null));

    // ---------------------------------------------------------------
    echo "\n=== G) Servicio con parto registrado ===\n";
    $servicio = $q(
        "SELECT uuid_local, version FROM mregservicios
         WHERE mfundos_id = ? AND status = 1 AND parto = 1
         ORDER BY id DESC LIMIT 1",
        [FUNDO]
    )[0] ?? null;
    comprobar($servicio !== null, 'Hay un servicio con parto en el fundo', json_encode($servicio));
    if ($servicio) {
        $resp = api([
            'action' => 'push', 'modulo' => 'servicios', 'fundo_id' => FUNDO, 'dispositivo_id' => $dispositivo,
            'cambios' => [[
                'uuid_local' => $servicio['uuid_local'],
                'version_base' => (int) $servicio['version'],
                'eliminado' => true,
                'datos' => [],
            ]],
        ], $token);
        $r = $resp['cuerpo']['resultados'][0] ?? [];
        comprobar(($r['resultado'] ?? '') === 'error', 'Se rechaza la anulación', json_encode($r['resultado'] ?? null));
        comprobar(str_contains((string) ($r['mensaje'] ?? ''), 'parto'), 'El motivo menciona el parto', (string) ($r['mensaje'] ?? ''));
        $sigue = $q("SELECT status FROM mregservicios WHERE uuid_local = ?", [$servicio['uuid_local']])[0] ?? null;
        comprobar((int) ($sigue['status'] ?? -1) === 1, 'El servicio sigue activo', json_encode($sigue));
    }

    // ---------------------------------------------------------------
    echo "\n=== H) Módulo que no admite anulación ===\n";
    $baja = $q(
        "SELECT uuid_local FROM mdesincorporacionanimales WHERE mfundos_id = ? AND status = 1 ORDER BY id DESC LIMIT 1",
        [FUNDO]
    )[0] ?? null;
    comprobar($baja !== null, 'Hay una baja registrada en el fundo', json_encode($baja));
    if ($baja) {
        $resp = api([
            'action' => 'push', 'modulo' => 'desincorporacion', 'fundo_id' => FUNDO, 'dispositivo_id' => $dispositivo,
            'cambios' => [[
                'uuid_local' => $baja['uuid_local'],
                'version_base' => null,
                'eliminado' => true,
                'datos' => [],
            ]],
        ], $token);
        $r = $resp['cuerpo']['resultados'][0] ?? [];
        comprobar(($r['resultado'] ?? '') === 'error', 'Se rechaza borrar una baja', json_encode($r['resultado'] ?? null));
        $sigue = $q("SELECT status FROM mdesincorporacionanimales WHERE uuid_local = ?", [$baja['uuid_local']])[0] ?? null;
        comprobar((int) ($sigue['status'] ?? -1) === 1, 'La baja sigue intacta', json_encode($sigue));
    }

} catch (\Throwable $e) {
    echo 'EXCEPCIÓN: ' . $e->getMessage() . "\n";
    $fallos++;
} finally {
    echo "\n=== Limpieza ===\n";
    foreach ($creados as $u) {
        \Mconsecutivopeso::delete_all(['conditions' => ['uuid_local = ?', $u]]);
    }
    if ($creados) { echo '  Pesajes de prueba borrados: ' . count($creados) . "\n"; }
    if ($token) {
        $t = \ApiToken::find('first', ['conditions' => ['token_hash = ?', \Api\ApiAuth::hashToken($token)]]);
        if ($t) { $t->delete(); echo "  Token de prueba borrado\n"; }
    }

    echo "\nRESUMEN: $ok correctas, $fallos fallidas\n";
    exit($fallos === 0 ? 0 : 1);
}
