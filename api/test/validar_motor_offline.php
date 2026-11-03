<?php
/**
 * Lanza la prueba del MOTOR del cliente offline (app_offline/js) en Node.
 *
 * El script de Node carga db.js, dispositivo.js, api.js, config.js y sync.js
 * REALES sobre una IndexedDB en memoria y sincroniza contra el API real.
 * Este wrapper crea el token, prepara el entorno, ejecuta Node y borra todo
 * lo que la prueba creó (incluida la normalización de contadores).
 *
 * Uso:
 *   php api/test/validar_motor_offline.php
 *
 * Requiere Node 18+ y `fake-indexeddb`. Si no está instalado:
 *   mkdir -p /tmp/bovs-node && (cd /tmp/bovs-node && npm i fake-indexeddb)
 * o indicá la ruta con SOVCA_NODE_PATH=/ruta/node_modules
 *
 * Variables opcionales: SOVCA_TEST_FUNDO, SOVCA_TEST_USUARIO, SOVCA_TEST_API.
 */

chdir(dirname(__DIR__));
require_once 'config/bootstrap.php';
require_once 'lib/ApiAuth.php';
require_once '../models/UsuarioDispositivoOffline.php';
require_once '../models/Musuario.php';

$FUNDO = (int) (getenv('SOVCA_TEST_FUNDO') ?: 2);
$USUARIO = (int) (getenv('SOVCA_TEST_USUARIO') ?: 4);
$URL_API = getenv('SOVCA_TEST_API') ?: 'http://127.0.0.1/sovca/api/';
$NODE_PATH = getenv('SOVCA_NODE_PATH') ?: '/tmp/bovs-node/node_modules';

echo "Prueba del motor offline (Node + IndexedDB + API real)\n";
echo 'Fundo ' . $FUNDO . ' · usuario ' . $USUARIO . ' · API ' . $URL_API . "\n";

if (!is_dir($NODE_PATH . '/fake-indexeddb')) {
    echo "\n[aviso] no está fake-indexeddb en {$NODE_PATH}.\n";
    echo "Instalalo (fuera del repo) y volvé a correr:\n";
    echo "  mkdir -p /tmp/bovs-node && (cd /tmp/bovs-node && npm i fake-indexeddb)\n";
    exit(2);
}

$vinculo = \UsuarioDispositivoOffline::find('first', [
    'conditions' => ['musuarios_id = ? and mfundos_id = ? and status = 1', $USUARIO, $FUNDO],
]);
if (!$vinculo) {
    echo "\nNo hay dispositivo vinculado para el usuario {$USUARIO} en el fundo {$FUNDO}.\n";
    exit(1);
}

$conexion = \Musuario::connection();
$token = \Api\ApiAuth::crearToken($USUARIO, null, 'validacion-motor-offline')['token'];

$proceso = proc_open(
    ['node', __DIR__ . '/validar_motor_offline.js'],
    [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
    $tuberias,
    __DIR__,
    [
        'PATH' => getenv('PATH'),
        'HOME' => getenv('HOME'),
        'NODE_PATH' => $NODE_PATH,
        'SOVCA_TOKEN' => $token,
        'SOVCA_FUNDO' => (string) $FUNDO,
        'SOVCA_USUARIO' => (string) $USUARIO,
        'SOVCA_DISPOSITIVO' => $vinculo->dispositivo_id,
        'SOVCA_API' => $URL_API,
    ]
);

if (!is_resource($proceso)) {
    echo "No se pudo ejecutar Node.\n";
    exit(1);
}

$salida = stream_get_contents($tuberias[1]);
$errores = stream_get_contents($tuberias[2]);
fclose($tuberias[1]);
fclose($tuberias[2]);
$codigoSalida = proc_close($proceso);

echo $salida;
if (trim($errores) !== '') {
    echo "\n--- stderr ---\n" . $errores . "\n";
}

// ---------------------------------------------------------------------------
// limpieza: el motor imprime "__UUID__{...}" con lo que creó
// ---------------------------------------------------------------------------
$uuids = [];
if (preg_match('/^__UUID__(.+)$/m', $salida, $m)) {
    $uuids = json_decode(trim($m[1]), true) ?: [];
}

echo "\n=== Limpieza ===\n";

foreach ($uuids['control_sanitario'] ?? [] as $uuid) {
    $fila = $conexion->query('SELECT id FROM mcontrolsanitarios WHERE uuid_local = ? AND mfundos_id = ?', [$uuid, $FUNDO])->fetch();
    if ($fila) {
        $conexion->query('DELETE FROM mdetacrlsanitarios WHERE mcontrolsanitarios_id = ?', [(int) $fila['id']]);
        $conexion->query('DELETE FROM mcontrolsanitarios WHERE id = ?', [(int) $fila['id']]);
    }
}

foreach ($uuids['servicios'] ?? [] as $uuid) {
    $conexion->query('DELETE FROM mregservicios WHERE uuid_local = ? AND mfundos_id = ?', [$uuid, $FUNDO]);
}

foreach ($uuids['pesos'] ?? [] as $uuid) {
    $conexion->query('DELETE FROM mconsecutivopesos WHERE uuid_local = ? AND mfundos_id = ?', [$uuid, $FUNDO]);
}

foreach ($uuids['animales'] ?? [] as $uuid) {
    $fila = $conexion->query('SELECT id FROM manimales WHERE uuid_local = ? AND mfundos_id = ?', [$uuid, $FUNDO])->fetch();
    if ($fila) {
        $conexion->query('DELETE FROM manimalxfundos WHERE manimales_id = ?', [(int) $fila['id']]);
        $conexion->query('DELETE FROM manimales WHERE id = ?', [(int) $fila['id']]);
    }
}

// Normalizar los contadores (nunca por debajo del último código usado).
foreach ([
    ['manimalxfundos_contador', 'manimales', 'codigo_rgb_id_animal', '^SOVCA-[0-9]+-[0-9]+$'],
    ['mnacimientoxfundos_contador', 'mregnacimientos', 'codigo_ternero', '^MA-[0-9]+-[0-9]+$'],
    ['mserviciosxfundos_contador', 'mregservicios', 'codigo_servicio', '^RSA-[0-9]+-[0-9]+$'],
] as $normalizacion) {
    [$tablaCont, $tablaDatos, $campo, $patron] = $normalizacion;
    $conexion->query(
        "UPDATE {$tablaCont} c
            SET ultimo_numero_asignado = m.maximo, updated = NOW()
           FROM (SELECT mfundos_id,
                        MAX(SUBSTRING({$campo} FROM '([0-9]+)$')::int) AS maximo
                   FROM {$tablaDatos}
                  WHERE {$campo} ~ ? GROUP BY mfundos_id) m
          WHERE c.mfundos_id = m.mfundos_id
            AND c.ultimo_numero_asignado < m.maximo",
        [$patron]
    );
}

$conexion->query("DELETE FROM api_tokens WHERE dispositivo = 'validacion-motor-offline'");
echo "  Registros de prueba borrados y contadores normalizados.\n";

exit($codigoSalida);
