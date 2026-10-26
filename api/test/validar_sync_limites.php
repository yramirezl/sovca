<?php
/**
 * VALIDACIÓN DE LOS CASOS LÍMITE DEL CURSOR DE SINCRONIZACIÓN.
 *
 * Cubre lo que el pull incremental puede perder si las marcas de
 * tiempo no son fiables:
 *
 *   A) Varias filas creadas en el MISMO instante (un push en lote) no
 *      se quedan fuera al continuar desde el cursor.
 *   B) Dos ediciones seguidas de la misma fila: la marca avanza y el
 *      dispositivo recibe el último valor.
 *   C) Ninguna fila queda con "updated" por detrás de "created".
 *
 * Uso:
 *   php api/test/validar_sync_limites.php
 *
 * Requiere la migración scripts/migracion_sync_cursor_updated.sql
 * aplicada. Crea y borra sus propios registros.
 */

chdir(dirname(__DIR__));
require_once 'config/bootstrap.php';
require_once 'lib/ApiAuth.php';
require_once '../models/UsuarioDispositivoOffline.php';
require_once '../models/Mconsecutivopeso.php';
require_once '../models/Musuario.php';

const FUNDO = 2;
const USUARIO = 4;
const URL_API = 'http://127.0.0.1/sovca/api/sync_controller.php';

$ok = 0;
$fallos = 0;

function comprobar(bool $c, string $d, string $det = ''): void
{
    global $ok, $fallos;
    if ($c) {
        $ok++;
        echo "  OK    $d" . ($det !== '' ? "  [$det]" : '') . "\n";
    } else {
        $fallos++;
        echo "  FALLA $d" . ($det !== '' ? "  [$det]" : '') . "\n";
    }
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

$vinculo = \UsuarioDispositivoOffline::find('first', [
    'conditions' => ['musuarios_id = ? and mfundos_id = ? and status = 1', USUARIO, FUNDO],
]);
if (!$vinculo) {
    echo "No hay dispositivo vinculado para el usuario " . USUARIO . " en el fundo " . FUNDO . ".\n";
    exit(1);
}
$dispositivo = $vinculo->dispositivo_id;

$conexion = \Musuario::connection();
$consulta = fn (string $sql, array $params = []) => $conexion->query($sql, $params)->fetchAll();

$uuids = [];
for ($i = 0; $i < 3; $i++) {
    $uuids[] = sprintf('cccccccc-dddd-4eee-8fff-%012d', random_int(0, 999999999999));
}
$token = null;

try {
    $token = \Api\ApiAuth::crearToken(USUARIO, null, 'validacion-limites')['token'];

    echo "\n=== A) Tres altas en un mismo lote (misma marca) ===\n";
    // Un lote real pesa VARIOS animales distintos. Se usan tres animales
    // diferentes para no chocar con la regla de negocio que rechaza dos
    // pesajes del mismo animal en la misma fecha (validar_reglas_negocio).
    $animales = $consulta(
        "SELECT codigo_rgb_id_animal FROM manimales
         WHERE mfundos_id = ? AND status = 1 AND mestatus_id = 1
         ORDER BY id LIMIT 3",
        [FUNDO]
    );
    $codigos = array_column($animales, 'codigo_rgb_id_animal');
    comprobar(count($codigos) === 3, 'Hay tres animales activos en el fundo', implode(',', $codigos));
    // Fecha fija y alejada de hoy: el pesaje del día puede existir ya.
    $fecha = date('Y-m-d', strtotime('-777 days'));

    $antes = $consulta(
        "SELECT COALESCE(MAX(COALESCE(updated, created)), '1970-01-01') AS m, COALESCE(MAX(id), 0) AS i
         FROM mconsecutivopesos WHERE mfundos_id = ?",
        [FUNDO]
    )[0];
    $cursor = $antes['m'] . '|' . $antes['i'];

    $push = api([
        'action' => 'push', 'modulo' => 'pesos', 'fundo_id' => FUNDO, 'dispositivo_id' => $dispositivo,
        'cambios' => array_map(fn ($u, $n) => [
            'uuid_local' => $u,
            'datos' => ['codigo_rgb_id_animal' => $codigos[$n], 'fecha_pesaje' => $fecha, 'peso' => 400 + $n],
        ], $uuids, array_keys($uuids)),
    ], $token);
    $resultados = array_column($push['cuerpo']['resultados'] ?? [], 'resultado');
    comprobar(count(array_filter($resultados, fn ($r) => $r === 'creado')) === 3, 'Las 3 altas se crean', implode(',', $resultados));

    $marcas = $consulta(
        "SELECT uuid_local, COALESCE(updated, created) AS marca FROM mconsecutivopesos WHERE uuid_local IN (?,?,?)",
        $uuids
    );
    comprobar(count(array_unique(array_column($marcas, 'marca'))) === 1, 'Comparten la misma marca', (string) count($marcas));

    $pull = api([
        'action' => 'pull', 'modulo' => 'pesos', 'desde' => $cursor,
        'fundo_id' => FUNDO, 'dispositivo_id' => $dispositivo,
    ], $token);
    $recibidas = array_filter(
        array_column($pull['cuerpo']['registros'] ?? [], 'uuid_local'),
        fn ($u) => in_array($u, $uuids, true)
    );
    comprobar(count($recibidas) === 3, 'El pull entrega las 3 (ninguna se pierde por el empate)', 'recibidas=' . count($recibidas));
    $cursorA = $pull['cuerpo']['siguiente_desde'] ?? '';

    $pull2 = api([
        'action' => 'pull', 'modulo' => 'pesos', 'desde' => $cursorA,
        'fundo_id' => FUNDO, 'dispositivo_id' => $dispositivo,
    ], $token);
    $repetidas = array_intersect(array_column($pull2['cuerpo']['registros'] ?? [], 'uuid_local'), $uuids);
    comprobar(count($repetidas) === 0, 'Al continuar desde el cursor no se repiten', 'repetidas=' . count($repetidas));

    echo "\n=== B) Dos ediciones seguidas de la misma fila ===\n";
    $uuid1 = $uuids[0];
    $fila = $consulta("SELECT id, version, COALESCE(updated, created) AS marca FROM mconsecutivopesos WHERE uuid_local = ?", [$uuid1])[0];
    $marcaAntes = $fila['marca'];

    foreach ([411, 412] as $peso) {
        $edicion = api([
            'action' => 'push', 'modulo' => 'pesos', 'fundo_id' => FUNDO, 'dispositivo_id' => $dispositivo,
            'cambios' => [[
                'uuid_local' => $uuid1,
                'version_base' => (int) $fila['version'],
                'datos' => ['codigo_rgb_id_animal' => $codigos[0], 'fecha_pesaje' => $fecha, 'peso' => $peso],
            ]],
        ], $token);
        $res = $edicion['cuerpo']['resultados'][0] ?? [];
        comprobar(($res['resultado'] ?? '') === 'actualizado', "Edición a $peso aceptada", json_encode($res['resultado'] ?? null));
        $fila = $consulta("SELECT id, version, COALESCE(updated, created) AS marca FROM mconsecutivopesos WHERE uuid_local = ?", [$uuid1])[0];
    }
    comprobar($fila['marca'] > $marcaAntes, 'La marca avanza en cada edición', $marcaAntes . ' -> ' . $fila['marca']);

    $pull3 = api([
        'action' => 'pull', 'modulo' => 'pesos', 'desde' => $cursorA,
        'fundo_id' => FUNDO, 'dispositivo_id' => $dispositivo,
    ], $token);
    $editada = null;
    foreach (($pull3['cuerpo']['registros'] ?? []) as $f) {
        if (($f['uuid_local'] ?? '') === $uuid1) { $editada = $f; }
    }
    comprobar($editada !== null, 'El pull trae la fila reeditada');
    comprobar((float) ($editada['peso'] ?? 0) === 412.0, 'Con el último valor (412)', 'peso=' . json_encode($editada['peso'] ?? null));

    echo "\n=== C) Coherencia marca/creación en todas las tablas sincronizables ===\n";
    $tablas = [
        'manimales', 'mregnacimientos', 'mcontrolsanitarios', 'mcontrolsanitarioxlotes',
        'mprolecheras', 'mconsecutivopesos', 'mdesincorporacionanimales',
        'mmovimientos_rebano', 'mregservicios',
    ];
    $malas = 0;
    foreach ($tablas as $tabla) {
        $n = $consulta("SELECT count(*) AS n FROM $tabla WHERE updated IS NULL OR updated < created")[0]['n'];
        if ((int) $n > 0) {
            echo "    $tabla: $n fila(s) desfasadas\n";
        }
        $malas += (int) $n;
    }
    comprobar($malas === 0, 'Ninguna fila queda con la marca por detrás de su creación', 'filas=' . $malas);

} catch (\Throwable $e) {
    echo 'EXCEPCIÓN: ' . $e->getMessage() . "\n";
    $fallos++;
} finally {
    echo "\n=== Limpieza ===\n";
    foreach ($uuids as $u) {
        \Mconsecutivopeso::delete_all(['conditions' => ['uuid_local = ?', $u]]);
    }
    echo '  Registros de prueba borrados: ' . count($uuids) . "\n";
    if ($token) {
        $t = \ApiToken::find('first', ['conditions' => ['token_hash = ?', \Api\ApiAuth::hashToken($token)]]);
        if ($t) { $t->delete(); echo "  Token de prueba borrado\n"; }
    }
    echo '  Restantes: ' . $consulta("SELECT count(*) n FROM mconsecutivopesos WHERE uuid_local IN (?,?,?)", $uuids)[0]['n'] . "\n";
    echo "\nRESUMEN: $ok correctas, $fallos fallidas\n";
    exit($fallos === 0 ? 0 : 1);
}
