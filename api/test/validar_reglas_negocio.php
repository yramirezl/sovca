<?php
/**
 * VALIDACIÓN DE REGLAS DE NEGOCIO EN LA API (P0 de la app offline).
 *
 * Comprueba que la API rechace lo mismo que rechaza la web cuando un
 * dispositivo envía datos desde el modo offline:
 *
 *   A) duplicado de animal + fecha: pesaje
 *   B) duplicado de animal + fecha: producción de leche
 *   C) duplicado de animal + fecha: control sanitario
 *   D) servicio con fecha anterior al último parto de la hembra
 *   E) control positivo: el mismo pesaje en OTRA fecha sí se acepta
 *
 * Uso (desde la raíz del proyecto):
 *   php api/test/validar_reglas_negocio.php
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
const URL_API = 'http://127.0.0.1/bovs/api/sync_controller.php';

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
    $token = \Api\ApiAuth::crearToken(USUARIO, null, 'validacion-p0')['token'];

    // ---------------------------------------------------------------
    echo "\n=== A) Duplicado de PESAJE (animal + fecha) ===\n";
    $peso = $q(
        "SELECT codigo_rgb_id_animal, TO_CHAR(fecha_pesaje, 'YYYY-MM-DD') AS fecha
         FROM mconsecutivopesos WHERE mfundos_id = ? AND status = 1 ORDER BY id DESC LIMIT 1",
        [FUNDO]
    )[0] ?? null;
    comprobar($peso !== null, 'Hay un pesaje de referencia en el fundo', json_encode($peso));

    if ($peso) {
        $resp = api([
            'action' => 'push', 'modulo' => 'pesos', 'fundo_id' => FUNDO, 'dispositivo_id' => $dispositivo,
            'cambios' => [[
                'uuid_local' => uuid(),
                'datos' => [
                    'codigo_rgb_id_animal' => $peso['codigo_rgb_id_animal'],
                    'fecha_pesaje' => $peso['fecha'],
                    'peso' => 333,
                ],
            ]],
        ], $token);
        $r = $resp['cuerpo']['resultados'][0] ?? [];
        comprobar(($r['resultado'] ?? '') === 'error', 'Se rechaza el pesaje duplicado', json_encode($r['resultado'] ?? null));
        comprobar(str_contains((string) ($r['mensaje'] ?? ''), 'ya tiene un pesaje'), 'Con mensaje explicativo', (string) ($r['mensaje'] ?? ''));
    }

    // ---------------------------------------------------------------
    echo "\n=== B) Duplicado de PRODUCCIÓN DE LECHE ===\n";
    $leche = $q(
        "SELECT codigo_rgb_id_animal_hembra AS animal, TO_CHAR(fecha_produccion, 'YYYY-MM-DD') AS fecha
         FROM mprolecheras WHERE mfundos_id = ? AND status = 1 ORDER BY id DESC LIMIT 1",
        [FUNDO]
    )[0] ?? null;
    if ($leche) {
        $resp = api([
            'action' => 'push', 'modulo' => 'produccion_leche', 'fundo_id' => FUNDO, 'dispositivo_id' => $dispositivo,
            'cambios' => [[
                'uuid_local' => uuid(),
                'datos' => [
                    'codigo_rgb_id_animal_hembra' => $leche['animal'],
                    'fecha_produccion' => $leche['fecha'],
                    'cantidad_leche_m' => 5,
                    'cantidad_leche_t' => 5,
                ],
            ]],
        ], $token);
        $r = $resp['cuerpo']['resultados'][0] ?? [];
        comprobar(($r['resultado'] ?? '') === 'error', 'Se rechaza la producción duplicada', json_encode($r['resultado'] ?? null));
    } else {
        echo "  (sin registros de leche para probar)\n";
    }

    // ---------------------------------------------------------------
    echo "\n=== C) Duplicado de CONTROL SANITARIO ===\n";
    $control = $q(
        "SELECT codigo_rgb_id_animal AS animal, TO_CHAR(fecha_control, 'YYYY-MM-DD') AS fecha, evento, responsable
         FROM mcontrolsanitarios WHERE mfundos_id = ? AND status = 1 ORDER BY id DESC LIMIT 1",
        [FUNDO]
    )[0] ?? null;
    if ($control) {
        $resp = api([
            'action' => 'push', 'modulo' => 'control_sanitario', 'fundo_id' => FUNDO, 'dispositivo_id' => $dispositivo,
            'cambios' => [[
                'uuid_local' => uuid(),
                'datos' => [
                    'codigo_rgb_id_animal' => $control['animal'],
                    'fecha_control' => $control['fecha'],
                    'evento' => $control['evento'] ?: 'Control',
                    'responsable' => $control['responsable'] ?: 'Prueba',
                ],
            ]],
        ], $token);
        $r = $resp['cuerpo']['resultados'][0] ?? [];
        comprobar(($r['resultado'] ?? '') === 'error', 'Se rechaza el control duplicado', json_encode($r['resultado'] ?? null));
    } else {
        echo "  (sin controles para probar)\n";
    }

    // ---------------------------------------------------------------
    echo "\n=== D) Servicio anterior al último parto ===\n";
    $madre = $q(
        "SELECT r.codigo_rgb_id_animal_hembra AS animal,
                TO_CHAR(MAX(r.f_parto), 'YYYY-MM-DD') AS ultimo_parto
         FROM mregnacimientos r
         JOIN manimales m ON m.mfundos_id = r.mfundos_id AND m.codigo_rgb_id_animal = r.codigo_rgb_id_animal_hembra
         WHERE r.mfundos_id = ? AND r.status = 1 AND m.status = 1 AND m.mestatus_id = 1 AND m.sexo = 1
           AND m.f_nacimiento IS NOT NULL
         GROUP BY 1 HAVING MAX(r.f_parto) IS NOT NULL
         ORDER BY MAX(r.f_parto) DESC LIMIT 1",
        [FUNDO]
    )[0] ?? null;
    comprobar($madre !== null, 'Hay una hembra con parto registrado', json_encode($madre));

    if ($madre) {
        $macho = $q(
            "SELECT codigo_rgb_id_animal FROM manimales
             WHERE mfundos_id = ? AND status = 1 AND mestatus_id = 1 AND sexo = 2 AND estatus_animal = 1
             ORDER BY id LIMIT 1",
            [FUNDO]
        )[0]['codigo_rgb_id_animal'] ?? null;
        comprobar($macho !== null, 'Hay un macho activo para el servicio', (string) $macho);

        $fechaMala = date('Y-m-d', strtotime($madre['ultimo_parto'] . ' -30 days'));
        $resp = api([
            'action' => 'push', 'modulo' => 'servicios', 'fundo_id' => FUNDO, 'dispositivo_id' => $dispositivo,
            'cambios' => [[
                'uuid_local' => uuid(),
                'datos' => [
                    'codigo_rgb_id_animal_hembra' => $madre['animal'],
                    'mservicios_id' => 1,
                    'codigo_rgb_id_animal_macho' => (string) $macho,
                    'f_servicio' => $fechaMala,
                    'aptitud' => 'Prueba',
                    'mrazas_id' => 1,
                    'observaciones' => 'prueba validacion P0',
                ],
            ]],
        ], $token);
        $r = $resp['cuerpo']['resultados'][0] ?? [];
        $mensaje = (string) ($r['mensaje'] ?? '');
        comprobar(($r['resultado'] ?? '') === 'error', 'Se rechaza el servicio anterior al parto', json_encode($r['resultado'] ?? null));
        comprobar(str_contains($mensaje, 'último parto'), 'El motivo es la fecha frente al parto', $mensaje);
    }

    // ---------------------------------------------------------------
    echo "\n=== E) Control positivo: misma alta en OTRA fecha ===\n";
    if ($peso) {
        $fechaOk = date('Y-m-d', strtotime($peso['fecha'] . ' -1 day'));
        $nuevoUuid = uuid();
        $resp = api([
            'action' => 'push', 'modulo' => 'pesos', 'fundo_id' => FUNDO, 'dispositivo_id' => $dispositivo,
            'cambios' => [[
                'uuid_local' => $nuevoUuid,
                'datos' => [
                    'codigo_rgb_id_animal' => $peso['codigo_rgb_id_animal'],
                    'fecha_pesaje' => $fechaOk,
                    'peso' => 444,
                ],
            ]],
        ], $token);
        $r = $resp['cuerpo']['resultados'][0] ?? [];
        comprobar(($r['resultado'] ?? '') === 'creado', 'Un pesaje en otra fecha se acepta', json_encode($r['resultado'] ?? null));
        if (($r['resultado'] ?? '') === 'creado') {
            $creados[] = $nuevoUuid;
        }
    }

} catch (\Throwable $e) {
    echo 'EXCEPCIÓN: ' . $e->getMessage() . "\n";
    $fallos++;
} finally {
    echo "\n=== Limpieza ===\n";
    foreach ($creados as $u) {
        \Mconsecutivopeso::delete_all(['conditions' => ['uuid_local = ?', $u]]);
    }
    if ($creados) { echo '  Pestajes de prueba borrados: ' . count($creados) . "\n"; }
    if ($token) {
        $t = \ApiToken::find('first', ['conditions' => ['token_hash = ?', \Api\ApiAuth::hashToken($token)]]);
        if ($t) { $t->delete(); echo "  Token de prueba borrado\n"; }
    }
    // Servicios de prueba (por si alguno pasó las validaciones)
    $borrados = $q("DELETE FROM mregservicios WHERE observaciones = 'prueba validacion P0' RETURNING id");
    echo '  Servicios de prueba borrados: ' . count($borrados) . "\n";

    echo "\nRESUMEN: $ok correctas, $fallos fallidas\n";
    exit($fallos === 0 ? 0 : 1);
}
