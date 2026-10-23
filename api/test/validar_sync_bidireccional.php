<?php
/**
 * VALIDACIÓN DE LA SINCRONIZACIÓN BIDIRECCIONAL (PWA <-> servidor).
 *
 * Ejercita el API REAL por HTTP: login por token, vínculo de
 * dispositivo, push de altas y ediciones, pull con cursor, conflictos
 * por versión, reenvío idempotente, aislamiento entre fundos y el
 * bloqueo de escritura en modo consulta.
 *
 * Uso (desde la raíz del proyecto):
 *   php api/test/validar_sync_bidireccional.php
 *
 * Variables opcionales por entorno:
 *   BOVS_TEST_FUNDO=2     fundo de prueba (por defecto 2)
 *   BOVS_TEST_USUARIO=4   usuario con acceso a ese fundo
 *   BOVS_TEST_API=http://127.0.0.1/bovs/api/sync_controller.php
 *
 * El script CREA un token y un pesaje de prueba y los BORRA al final
 * (incluso si algo falla). Pensado para sovca_qas, no para producción.
 */

chdir(dirname(__DIR__));
require_once 'config/bootstrap.php';
require_once 'lib/ApiAuth.php';
require_once '../models/UsuarioDispositivoOffline.php';
require_once '../models/Mconsecutivopeso.php';
require_once '../models/Musuario.php';

const FUNDO_PRUEBA = 2;
const USUARIO_PRUEBA = 4;
const FUNDO_SIN_ACCESO = 49;
const URL_API = 'http://127.0.0.1/bovs/api/sync_controller.php';

$ok = 0;
$fallos = 0;

function comprobar(bool $condicion, string $descripcion, string $detalle = ''): void
{
    global $ok, $fallos;
    if ($condicion) {
        $ok++;
        echo '  OK    ' . $descripcion . ($detalle !== '' ? '  [' . $detalle . ']' : '') . "\n";
    } else {
        $fallos++;
        echo '  FALLA ' . $descripcion . ($detalle !== '' ? '  [' . $detalle . ']' : '') . "\n";
    }
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
    foreach (($http_response_header ?? []) as $cabecera) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $cabecera, $m)) {
            $estado = (int) $m[1];
        }
    }

    return [
        'http' => $estado,
        'cuerpo' => $crudo === false ? null : json_decode($crudo, true),
    ];
}

$vinculo = \UsuarioDispositivoOffline::find('first', [
    'conditions' => ['musuarios_id = ? and mfundos_id = ? and status = 1', USUARIO_PRUEBA, FUNDO_PRUEBA],
]);

if (!$vinculo) {
    echo "No hay dispositivo vinculado para el usuario " . USUARIO_PRUEBA
        . " en el fundo " . FUNDO_PRUEBA . ". Vinculá uno desde el sistema web y reintentá.\n";
    exit(1);
}

$dispositivo = $vinculo->dispositivo_id;
$modoOriginal = $vinculo->modo_offline;
$hace = date('Y-m-d H:i:s', time() - 120);
$uuid = sprintf(
    '%04x%04x-%04x-4%03x-%04x-%04x%04x%04x',
    random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff),
    random_int(0, 0x0fff), random_int(0x8000, 0xbfff),
    random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff)
);

$token = null;
$idRegistro = null;
$codigoAnimal = 'BOVS-02-011';

try {
    echo "\n=== 0. Preparación ===\n";
    $token = \Api\ApiAuth::crearToken(USUARIO_PRUEBA, null, 'validacion-bidireccional')['token'];
    comprobar(strlen($token) === 64, 'Se emitió un token de acceso');
    comprobar($modoOriginal === 'gestion', 'El dispositivo está en modo gestión', (string) $modoOriginal);

    echo "\n=== 1. PULL (nube -> dispositivo) ===\n";
    $pull = llamar_api([
        'action' => 'pull', 'modulo' => 'pesos', 'desde' => $hace,
        'fundo_id' => FUNDO_PRUEBA, 'dispositivo_id' => $dispositivo,
    ], $token);
    comprobar($pull['http'] === 200, 'Responde HTTP 200', 'http=' . $pull['http']);
    comprobar(isset($pull['cuerpo']['registros']) && is_array($pull['cuerpo']['registros']), 'Trae el arreglo de registros');
    $marca = $pull['cuerpo']['siguiente_desde'] ?? $hace;
    comprobar(strpos($marca, '|') !== false, 'El cursor es compuesto (marca|id)', $marca);

    echo "\n=== 2. PUSH de un registro nuevo ===\n";
    $push = llamar_api([
        'action' => 'push', 'modulo' => 'pesos', 'fundo_id' => FUNDO_PRUEBA,
        'dispositivo_id' => $dispositivo,
        'cambios' => [[
            'uuid_local' => $uuid,
            'datos' => ['codigo_rgb_id_animal' => $codigoAnimal, 'fecha_pesaje' => date('Y-m-d'), 'peso' => 321],
        ]],
    ], $token);
    $r1 = $push['cuerpo']['resultados'][0] ?? [];
    comprobar(($r1['resultado'] ?? '') === 'creado', 'El servidor confirma "creado"', json_encode($r1['resultado'] ?? null));
    comprobar(($r1['version'] ?? null) === 1, 'La versión inicial es 1');
    $idRegistro = $r1['id'] ?? null;
    $version = (int) ($r1['version'] ?? 0);

    echo "\n=== 3. Ida y vuelta: el pull devuelve lo que se subió ===\n";
    $pull2 = llamar_api([
        'action' => 'pull', 'modulo' => 'pesos', 'desde' => $marca,
        'fundo_id' => FUNDO_PRUEBA, 'dispositivo_id' => $dispositivo,
    ], $token);
    $encontrado = null;
    foreach (($pull2['cuerpo']['registros'] ?? []) as $fila) {
        if (($fila['uuid_local'] ?? '') === $uuid) { $encontrado = $fila; }
    }
    comprobar($encontrado !== null, 'El registro vuelve al dispositivo');
    comprobar((float) ($encontrado['peso'] ?? 0) === 321.0, 'El dato coincide', 'peso=' . json_encode($encontrado['peso'] ?? null));
    $marca2 = $pull2['cuerpo']['siguiente_desde'] ?? $marca;

    echo "\n=== 4. Edición con la versión correcta ===\n";
    $ed = llamar_api([
        'action' => 'push', 'modulo' => 'pesos', 'fundo_id' => FUNDO_PRUEBA,
        'dispositivo_id' => $dispositivo,
        'cambios' => [[
            'uuid_local' => $uuid,
            'version_base' => $version,
            'datos' => ['codigo_rgb_id_animal' => $codigoAnimal, 'fecha_pesaje' => date('Y-m-d'), 'peso' => 322],
        ]],
    ], $token);
    $r2 = $ed['cuerpo']['resultados'][0] ?? [];
    comprobar(($r2['resultado'] ?? '') === 'actualizado', 'Se aplica la edición', json_encode($r2['resultado'] ?? null));
    comprobar((int) ($r2['version'] ?? 0) === $version + 1, 'La versión sube');

    echo "\n=== 5. Conflicto por versión vieja ===\n";
    $conf = llamar_api([
        'action' => 'push', 'modulo' => 'pesos', 'fundo_id' => FUNDO_PRUEBA,
        'dispositivo_id' => $dispositivo,
        'cambios' => [[
            'uuid_local' => $uuid, 'version_base' => 1,
            'datos' => ['codigo_rgb_id_animal' => $codigoAnimal, 'fecha_pesaje' => date('Y-m-d'), 'peso' => 999],
        ]],
    ], $token);
    $r3 = $conf['cuerpo']['resultados'][0] ?? [];
    comprobar(($r3['resultado'] ?? '') === 'conflicto', 'Devuelve "conflicto"', json_encode($r3['resultado'] ?? null));
    comprobar(!empty($r3['servidor']) && !empty($r3['tuyo']), 'Entrega las dos versiones para resolver');
    $enBd = \Mconsecutivopeso::find('first', ['conditions' => ['uuid_local = ?', $uuid]]);
    comprobar($enBd && (float) $enBd->peso === 322.0, 'El dato del servidor no se pisó');

    echo "\n=== 6. Reenvío idempotente (sin version_base) ===\n";
    $idem = llamar_api([
        'action' => 'push', 'modulo' => 'pesos', 'fundo_id' => FUNDO_PRUEBA,
        'dispositivo_id' => $dispositivo,
        'cambios' => [[
            'uuid_local' => $uuid,
            'datos' => ['codigo_rgb_id_animal' => $codigoAnimal, 'fecha_pesaje' => date('Y-m-d'), 'peso' => 500],
        ]],
    ], $token);
    $r4 = $idem['cuerpo']['resultados'][0] ?? [];
    comprobar(($r4['resultado'] ?? '') === 'sin_cambios', 'Devuelve "sin_cambios"', json_encode($r4['resultado'] ?? null));

    echo "\n=== 7. La edición baja por el cursor ===\n";
    $pull3 = llamar_api([
        'action' => 'pull', 'modulo' => 'pesos', 'desde' => $marca2,
        'fundo_id' => FUNDO_PRUEBA, 'dispositivo_id' => $dispositivo,
    ], $token);
    $editado = null;
    foreach (($pull3['cuerpo']['registros'] ?? []) as $fila) {
        if (($fila['uuid_local'] ?? '') === $uuid) { $editado = $fila; }
    }
    comprobar($editado !== null, 'El pull entrega la fila reeditada');
    comprobar((float) ($editado['peso'] ?? 0) === 322.0, 'Con el último valor', 'peso=' . json_encode($editado['peso'] ?? null));

    echo "\n=== 8. Aislamiento entre fundos ===\n";
    $ajeno = llamar_api([
        'action' => 'pull', 'modulo' => 'pesos', 'desde' => '',
        'fundo_id' => FUNDO_SIN_ACCESO, 'dispositivo_id' => $dispositivo,
    ], $token);
    comprobar($ajeno['http'] >= 400, 'Un fundo sin acceso se rechaza', 'http=' . $ajeno['http']);

    echo "\n=== 9. Modo consulta no escribe ===\n";
    $vinculo->modo_offline = 'consulta';
    $vinculo->save();
    $pushConsulta = llamar_api([
        'action' => 'push', 'modulo' => 'pesos', 'fundo_id' => FUNDO_PRUEBA,
        'dispositivo_id' => $dispositivo,
        'cambios' => [[
            'uuid_local' => sprintf('11111111-2222-4333-8444-%012d', random_int(0, 999999999999)),
            'datos' => ['codigo_rgb_id_animal' => $codigoAnimal, 'fecha_pesaje' => date('Y-m-d'), 'peso' => 123],
        ]],
    ], $token);
    comprobar($pushConsulta['http'] === 403, 'El push se rechaza con 403', 'http=' . $pushConsulta['http']);
    $codigo = $pushConsulta['cuerpo']['codigo'] ?? ($pushConsulta['cuerpo']['error']['codigo'] ?? null);
    comprobar($codigo === 'modo_consulta_no_escribe', 'Con un código que la app sabe explicar', json_encode($codigo));

    $pullConsulta = llamar_api([
        'action' => 'pull', 'modulo' => 'pesos', 'desde' => $marca2,
        'fundo_id' => FUNDO_PRUEBA, 'dispositivo_id' => $dispositivo,
    ], $token);
    comprobar($pullConsulta['http'] === 200, 'El pull sí se permite en consulta', 'http=' . $pullConsulta['http']);

} catch (\Throwable $e) {
    echo 'EXCEPCIÓN: ' . $e->getMessage() . "\n";
    $fallos++;
} finally {
    echo "\n=== Limpieza ===\n";
    if ($vinculo && $modoOriginal !== null) {
        $vinculo->modo_offline = $modoOriginal;
        $vinculo->save();
        echo '  Vínculo restaurado a modo ' . $modoOriginal . "\n";
    }
    if ($idRegistro) {
        $borrados = \Mconsecutivopeso::delete_all(['conditions' => ['uuid_local = ?', $uuid]]);
        echo '  Registro de prueba borrado (' . $borrados . ")\n";
    }
    if ($token) {
        $t = \ApiToken::find('first', ['conditions' => ['token_hash = ?', \Api\ApiAuth::hashToken($token)]]);
        if ($t) { $t->delete(); echo "  Token de prueba borrado\n"; }
    }
    echo '  Registros de prueba restantes: '
        . \Mconsecutivopeso::count(['conditions' => ['uuid_local = ?', $uuid]]) . "\n";

    echo "\nRESUMEN: $ok correctas, $fallos fallidas\n";
    exit($fallos === 0 ? 0 : 1);
}
