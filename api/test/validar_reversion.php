<?php
/**
 * VALIDACIÓN DE LA REVERSIÓN DE MOVIMIENTOS DE REBAÑO — P1.
 *
 * Un movimiento ya sincronizado no se puede "borrar": se revierte en el
 * servidor, que devuelve cada animal a su lote/potrero de origen y deja
 * la auditoría (motivo, fecha, usuario). Esto replica lo que hace la web
 * (`movreb_controller.php`, case `revertir_movimiento`).
 *
 *   A) alta de un movimiento LOTE por la API (los animales cambian)
 *   B) reversión con motivo corto        -> error (no toca nada)
 *   C) reversión con motivo válido       -> "revertido" y animales al origen
 *   D) reenvío de la misma reversión     -> idempotente (no sube versión)
 *   E) reversión con versión vieja       -> conflicto
 *   F) el pull devuelve el movimiento con revertido = true
 *
 * Uso (desde la raíz del proyecto):
 *   php api/test/validar_reversion.php
 *
 * Crea un token temporal, un movimiento de prueba y lo borra al final,
 * dejando los animales en su lote original.
 */
chdir(dirname(__DIR__));
require_once 'config/bootstrap.php';
require_once 'lib/ApiAuth.php';
require_once '../models/UsuarioDispositivoOffline.php';
require_once '../models/Musuario.php';

const FUNDO = 2;
const USUARIO = 4;
const URL_API = 'http://127.0.0.1/sovca/api/sync_controller.php';

$ok = 0;
$fallos = 0;
function comprobar(bool $c, string $d, string $det = ''): void
{
    global $ok, $fallos;
    if ($c) { $ok++; echo "  OK    $d" . ($det !== '' ? "  [$det]" : "") . "\n"; }
    else { $fallos++; echo "  FALLA $d" . ($det !== '' ? "  [$det]" : "") . "\n"; }
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
$uuidMovimiento = null;

try {
    $token = \Api\ApiAuth::crearToken(USUARIO, null, 'validacion-reversion')['token'];

    // ---------------------------------------------------------------
    echo "\n=== A) Alta de un movimiento LOTE ===\n";
    $lotes = $q(
        "SELECT a.mlotes_id AS lote, COUNT(*) AS total
         FROM manimales a
         JOIN mlotes l ON l.id = a.mlotes_id AND l.mfundos_id = a.mfundos_id AND l.status = 1
         WHERE a.mfundos_id = ? AND a.status = 1 AND a.mestatus_id = 1
         GROUP BY 1 HAVING COUNT(*) >= 2
         ORDER BY 2 DESC LIMIT 2",
        [FUNDO]
    );
    comprobar(count($lotes) >= 2, 'Hay al menos dos lotes con animales', json_encode($lotes));

    $origen = (int) ($lotes[0]['lote'] ?? 0);
    $destino = (int) ($lotes[1]['lote'] ?? 0);
    $codigos = array_column(
        $q(
            "SELECT codigo_rgb_id_animal FROM manimales
             WHERE mfundos_id = ? AND status = 1 AND mestatus_id = 1 AND mlotes_id = ?
             ORDER BY id LIMIT 2",
            [FUNDO, $origen]
        ),
        'codigo_rgb_id_animal'
    );
    comprobar(count($codigos) === 2, 'Hay dos animales en el lote de origen', implode(',', $codigos));

    $uuidMovimiento = uuid();
    $resp = api([
        'action' => 'push', 'modulo' => 'movimiento_rebano', 'fundo_id' => FUNDO, 'dispositivo_id' => $dispositivo,
        'cambios' => [[
            'uuid_local' => $uuidMovimiento,
            'datos' => [
                'tipo_movimiento' => 'LOTE',
                'mlotes_origen_id' => $origen,
                'mlotes_destino_id' => $destino,
                'fecha_movimiento' => date('Y-m-d'),
                'motivo' => 'prueba validacion reversion',
                'animales' => $codigos,
            ],
        ]],
    ], $token);
    $r = $resp['cuerpo']['resultados'][0] ?? [];
    comprobar(($r['resultado'] ?? '') === 'creado', 'El movimiento se crea', json_encode([$r['resultado'] ?? null, $r['mensaje'] ?? '']));
    $versionCreada = (int) ($r['version'] ?? 0);

    $movido = $q(
        "SELECT codigo_rgb_id_animal, mlotes_id FROM manimales WHERE mfundos_id = ? AND codigo_rgb_id_animal = ANY(?)",
        [FUNDO, '{' . implode(',', $codigos) . '}']
    );
    $todosEnDestino = count($movido) === 2;
    foreach ($movido as $fila) {
        if ((int) $fila['mlotes_id'] !== $destino) { $todosEnDestino = false; }
    }
    comprobar($todosEnDestino, 'Los animales quedaron en el lote de destino', json_encode($movido));

    // ---------------------------------------------------------------
    echo "\n=== B) Reversión con motivo corto ===\n";
    $resp = api([
        'action' => 'push', 'modulo' => 'movimiento_rebano', 'fundo_id' => FUNDO, 'dispositivo_id' => $dispositivo,
        'cambios' => [[
            'uuid_local' => $uuidMovimiento,
            'version_base' => $versionCreada,
            'revertir' => true,
            'motivo_reversion' => 'error',
            'datos' => [],
        ]],
    ], $token);
    $r = $resp['cuerpo']['resultados'][0] ?? [];
    comprobar(($r['resultado'] ?? '') === 'error', 'Se rechaza el motivo corto', json_encode($r['resultado'] ?? null));
    comprobar(str_contains((string) ($r['mensaje'] ?? ''), '10 caracteres'), 'El motivo es el motivo del rechazo', (string) ($r['mensaje'] ?? ''));
    $sigue = $q("SELECT revertido FROM mmovimientos_rebano WHERE uuid_local = ?", [$uuidMovimiento])[0] ?? null;
    comprobar($sigue !== null && $sigue['revertido'] === false, 'El movimiento sigue vigente', json_encode($sigue));

    // ---------------------------------------------------------------
    echo "\n=== C) Reversión con motivo válido ===\n";
    $resp = api([
        'action' => 'push', 'modulo' => 'movimiento_rebano', 'fundo_id' => FUNDO, 'dispositivo_id' => $dispositivo,
        'cambios' => [[
            'uuid_local' => $uuidMovimiento,
            'version_base' => $versionCreada,
            'revertir' => true,
            'motivo_reversion' => 'Prueba automatizada de reversion',
            'datos' => [],
        ]],
    ], $token);
    $r = $resp['cuerpo']['resultados'][0] ?? [];
    comprobar(($r['resultado'] ?? '') === 'revertido', 'La reversión se acepta', json_encode($r['resultado'] ?? null));
    comprobar((int) ($r['animales_devueltos'] ?? 0) === 2, 'Informa los animales devueltos', json_encode($r['animales_devueltos'] ?? null));

    $fila = $q(
        "SELECT revertido, motivo_reversion, user_reversion, fecha_reversion, version
         FROM mmovimientos_rebano WHERE uuid_local = ?",
        [$uuidMovimiento]
    )[0] ?? null;
    comprobar($fila !== null && $fila['revertido'] === true, 'Queda marcado como revertido', json_encode($fila));
    comprobar(($fila['motivo_reversion'] ?? '') === 'Prueba automatizada de reversion', 'Se guarda el motivo', (string) ($fila['motivo_reversion'] ?? ''));
    $versionRevertida = (int) ($fila['version'] ?? 0);
    comprobar($versionRevertida > $versionCreada, 'La versión sube', "$versionCreada -> $versionRevertida");

    $devuelto = $q(
        "SELECT codigo_rgb_id_animal, mlotes_id FROM manimales WHERE mfundos_id = ? AND codigo_rgb_id_animal = ANY(?)",
        [FUNDO, '{' . implode(',', $codigos) . '}']
    );
    $todosEnOrigen = count($devuelto) === 2;
    foreach ($devuelto as $filaAnimal) {
        if ((int) $filaAnimal['mlotes_id'] !== $origen) { $todosEnOrigen = false; }
    }
    comprobar($todosEnOrigen, 'Los animales volvieron al lote de origen', json_encode($devuelto));

    // ---------------------------------------------------------------
    echo "\n=== D) Reenvío de la misma reversión ===\n";
    $resp = api([
        'action' => 'push', 'modulo' => 'movimiento_rebano', 'fundo_id' => FUNDO, 'dispositivo_id' => $dispositivo,
        'cambios' => [[
            'uuid_local' => $uuidMovimiento,
            'version_base' => $versionRevertida,
            'revertir' => true,
            'motivo_reversion' => 'Prueba automatizada de reversion',
            'datos' => [],
        ]],
    ], $token);
    $r = $resp['cuerpo']['resultados'][0] ?? [];
    comprobar(($r['resultado'] ?? '') === 'revertido', 'Es idempotente al reintentar', json_encode($r['resultado'] ?? null));
    $fila = $q("SELECT version FROM mmovimientos_rebano WHERE uuid_local = ?", [$uuidMovimiento])[0] ?? null;
    comprobar((int) ($fila['version'] ?? 0) === $versionRevertida, 'No infla la versión', json_encode($fila));

    // ---------------------------------------------------------------
    echo "\n=== E) Reversión con versión vieja ===\n";
    $resp = api([
        'action' => 'push', 'modulo' => 'movimiento_rebano', 'fundo_id' => FUNDO, 'dispositivo_id' => $dispositivo,
        'cambios' => [[
            'uuid_local' => $uuidMovimiento,
            'version_base' => 1,
            'revertir' => true,
            'motivo_reversion' => 'Prueba automatizada de reversion',
            'datos' => [],
        ]],
    ], $token);
    $r = $resp['cuerpo']['resultados'][0] ?? [];
    comprobar(($r['resultado'] ?? '') === 'revertido', 'Ya revertido gana antes que el conflicto de versión', json_encode($r['resultado'] ?? null));

    // ---------------------------------------------------------------
    echo "\n=== F) El pull devuelve la reversión ===\n";
    $encontrado = null;
    $desde = '';
    for ($pagina = 0; $pagina < 40; $pagina++) {
        $resp = api([
            'action' => 'pull', 'modulo' => 'movimiento_rebano', 'fundo_id' => FUNDO,
            'dispositivo_id' => $dispositivo, 'desde' => $desde,
        ], $token);
        foreach ($resp['cuerpo']['registros'] ?? [] as $filaPull) {
            if (($filaPull['uuid_local'] ?? '') === $uuidMovimiento) { $encontrado = $filaPull; break 2; }
        }
        $desde = $resp['cuerpo']['siguiente_desde'] ?? '';
        if (empty($resp['cuerpo']['hay_mas'])) break;
    }
    comprobar($encontrado !== null, 'El movimiento aparece en el pull', $encontrado ? 'encontrado' : 'no encontrado');
    comprobar($encontrado !== null && $encontrado['revertido'] === true, 'Llega con revertido = true');

} catch (\Throwable $e) {
    echo 'EXCEPCIÓN: ' . $e->getMessage() . "\n";
    $fallos++;
} finally {
    echo "\n=== Limpieza ===\n";
    if ($uuidMovimiento) {
        $mov = $q("SELECT id FROM mmovimientos_rebano WHERE uuid_local = ?", [$uuidMovimiento])[0] ?? null;
        if ($mov) {
            $detalles = $q(
                "SELECT manimales_id, lote_anterior_id FROM mmovimientos_rebano_detalle WHERE mmovimientos_rebano_id = ?",
                [(int) $mov['id']]
            );
            foreach ($detalles as $detalle) {
                if ($detalle['lote_anterior_id'] !== null) {
                    $c->query("UPDATE manimales SET mlotes_id = ?, updated = NOW() WHERE id = ?", [
                        (int) $detalle['lote_anterior_id'], (int) $detalle['manimales_id'],
                    ]);
                }
            }
            $c->query("DELETE FROM mmovimientos_rebano_detalle WHERE mmovimientos_rebano_id = ?", [(int) $mov['id']]);
            $c->query("DELETE FROM mmovimientos_rebano WHERE id = ?", [(int) $mov['id']]);
            echo '  Movimiento de prueba borrado (' . count($detalles) . " animales restaurados)\n";
        }
    }
    if ($token) {
        $t = \ApiToken::find('first', ['conditions' => ['token_hash = ?', \Api\ApiAuth::hashToken($token)]]);
        if ($t) { $t->delete(); echo "  Token de prueba borrado\n"; }
    }

    echo "\nRESUMEN: $ok correctas, $fallos fallidas\n";
    exit($fallos === 0 ? 0 : 1);
}
