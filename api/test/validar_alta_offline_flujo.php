<?php
/**
 * VALIDACIÓN DEL FLUJO "ALTA OFFLINE → CONTROL SANITARIO → MOVIMIENTO".
 *
 * Reproduce, contra el API REAL, la secuencia que reportó el productor:
 *
 *   1. se crea un animal sin conexión (código PROVISIONAL SOVCA-0x-PEND-…)
 *   2. se registra un control sanitario de ese animal
 *   3. se lo mueve de lote
 *
 * y comprueba dos cosas:
 *
 *   A) El servidor SÍ asigna el código definitivo y devuelve
 *      `codigo_anterior` + `campos_resueltos`, que es lo que usa la app
 *      para "renombrar" la copia local (sync.js).
 *   B) Los dos mensajes de error que veía el productor se reproducen
 *      cuando el dispositivo manda el código provisional o pierde el
 *      campo (payload parcial al editar un registro que aún no subió):
 *        · "Falta el campo requerido 'codigo_rgb_id_animal'."
 *        · "Ninguno de los animales seleccionados está activo en este fundo."
 *      Y desaparecen cuando el payload llega con el código definitivo.
 *
 * Uso (desde la raíz del proyecto, con Apache sirviendo /sovca):
 *   php api/test/validar_alta_offline_flujo.php
 *
 * Variables opcionales: SOVCA_TEST_FUNDO, SOVCA_TEST_USUARIO, SOVCA_TEST_API.
 * El script borra TODO lo que crea (incluso si falla a mitad de camino).
 * ⚠ Usa la base configurada: NO ejecutar contra producción.
 */

chdir(dirname(__DIR__));
require_once 'config/bootstrap.php';
require_once 'lib/ApiAuth.php';
require_once '../models/UsuarioDispositivoOffline.php';
require_once '../models/Musuario.php';

const FUNDO_PRUEBA = 2;
const USUARIO_PRUEBA = 4;
const URL_API = 'http://127.0.0.1/sovca/api/sync_controller.php';

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
    foreach (($http_response_header ?? []) as $cabecera) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $cabecera, $m)) {
            $estado = (int) $m[1];
        }
    }

    return ['http' => $estado, 'cuerpo' => $crudo === false ? null : json_decode($crudo, true)];
}

$vinculo = \UsuarioDispositivoOffline::find('first', [
    'conditions' => ['musuarios_id = ? and mfundos_id = ? and status = 1', USUARIO_PRUEBA, FUNDO_PRUEBA],
]);
if (!$vinculo) {
    echo "No hay dispositivo vinculado para el usuario " . USUARIO_PRUEBA
        . " en el fundo " . FUNDO_PRUEBA . ".\n";
    exit(1);
}
$dispositivo = $vinculo->dispositivo_id;

$token = null;
$uuidAnimal = uuid4();
$uuidControl = uuid4();
$uuidControlSinCodigo = uuid4();
$uuidControlProvisional = uuid4();
$uuidMovProvisional = uuid4();
$uuidMovBueno = uuid4();
$codigoProvisional = 'SOVCA-0' . FUNDO_PRUEBA . '-PEND-' . random_int(100000, 999999);
$codigoDefinitivo = null;
$arete = 'T' . random_int(100000, 999999);
$conexion = \Musuario::connection();

function limpiar(): void
{
    global $conexion, $uuidAnimal, $uuidControl, $uuidControlSinCodigo,
        $uuidControlProvisional, $uuidMovProvisional, $uuidMovBueno;

    $ids = [];
    foreach ([$uuidMovProvisional, $uuidMovBueno] as $uuid) {
        $fila = $conexion->query(
            'SELECT id FROM mmovimientos_rebano WHERE uuid_local = ?',
            [$uuid]
        )->fetch();
        if ($fila) $ids[] = (int) $fila['id'];
    }
    foreach ($ids as $id) {
        $conexion->query('DELETE FROM mmovimientos_rebano_detalle WHERE mmovimientos_rebano_id = ?', [$id]);
        $conexion->query('DELETE FROM mmovimientos_rebano WHERE id = ?', [$id]);
    }

    foreach ([$uuidControl, $uuidControlSinCodigo, $uuidControlProvisional] as $uuid) {
        $conexion->query('DELETE FROM mcontrolsanitarios WHERE uuid_local = ?', [$uuid]);
    }

    $fila = $conexion->query('SELECT id FROM manimales WHERE uuid_local = ?', [$uuidAnimal])->fetch();
    if ($fila) {
        $conexion->query('DELETE FROM manimalxfundos WHERE manimales_id = ?', [(int) $fila['id']]);
        $conexion->query('DELETE FROM manimales WHERE id = ?', [(int) $fila['id']]);
    }
}

try {
    $token = \Api\ApiAuth::crearToken(USUARIO_PRUEBA, null, 'validacion-alta-offline')['token'];
    $base = ['fundo_id' => FUNDO_PRUEBA, 'dispositivo_id' => $dispositivo];

    echo "\n=== 1. Alta del animal con código PROVISIONAL (como el dispositivo) ===\n";
    $animal = llamar_api($base + [
        'action' => 'push',
        'modulo' => 'animales',
        'cambios' => [[
            'uuid_local' => $uuidAnimal,
            'version_base' => null,
            'datos' => [
                'codigo_rgb_id_animal' => $codigoProvisional,
                'nombre' => 'PRUEBA FLUJO OFFLINE',
                'sexo' => 2,
                'nro_arete' => $arete,
                'aptitud' => 0,
                'mrazas_id' => 0,
                'estatus_animal' => 1,
                'servicio' => 0,
                'nac_fundo' => true,
                'mlotes_id' => 7,
            ],
        ]],
    ], $token);

    $resultado = $animal['cuerpo']['resultados'][0] ?? [];
    comprobar(($resultado['resultado'] ?? '') === 'creado', 'El animal se crea en el servidor', (string) ($resultado['resultado'] ?? 'sin respuesta'));
    $codigoDefinitivo = $resultado['campos_resueltos']['codigo_rgb_id_animal'] ?? null;
    comprobar(is_string($codigoDefinitivo) && $codigoDefinitivo !== '', 'El servidor asigna el código definitivo', (string) $codigoDefinitivo);
    comprobar(($resultado['codigo_anterior'] ?? null) === $codigoProvisional, 'Devuelve codigo_anterior para renombrar la copia local', (string) ($resultado['codigo_anterior'] ?? '—'));

    echo "\n=== 2. Control sanitario SIN el campo del animal (payload parcial) ===\n";
    $controlSinCodigo = llamar_api($base + [
        'action' => 'push',
        'modulo' => 'control_sanitario',
        'cambios' => [[
            'uuid_local' => $uuidControlSinCodigo,
            'version_base' => null,
            'datos' => [
                'fecha_control' => date('Y-m-d'),
                'evento' => 'Vacunación',
                'responsable' => 'Prueba',
                'observaciones' => '',
                'productos_aplicados' => [],
            ],
        ]],
    ], $token);
    $mensajeParcial = $controlSinCodigo['cuerpo']['resultados'][0]['mensaje'] ?? '';
    comprobar(
        strpos($mensajeParcial, "Falta el campo requerido 'codigo_rgb_id_animal'") !== false,
        'Se reproduce el error del productor con el payload parcial',
        $mensajeParcial
    );

    echo "\n=== 2b. Control sanitario con el código PROVISIONAL del animal ===\n";
    $controlProvisional = llamar_api($base + [
        'action' => 'push',
        'modulo' => 'control_sanitario',
        'cambios' => [[
            'uuid_local' => $uuidControlProvisional,
            'version_base' => null,
            'datos' => [
                'codigo_rgb_id_animal' => $codigoProvisional,
                'fecha_control' => date('Y-m-d'),
                'evento' => 'Vacunación',
                'responsable' => 'Prueba',
                'observaciones' => '',
                'productos_aplicados' => [],
            ],
        ]],
    ], $token);
    $mensajeProvisional = $controlProvisional['cuerpo']['resultados'][0]['mensaje'] ?? '';
    comprobar(
        strpos($mensajeProvisional, 'todavía no tiene código definitivo') !== false,
        'Se rechaza el código provisional en vez de guardar un registro huérfano',
        $mensajeProvisional
    );

    echo "\n=== 3. Control sanitario con el código DEFINITIVO ===\n";    $control = llamar_api($base + [
        'action' => 'push',
        'modulo' => 'control_sanitario',
        'cambios' => [[
            'uuid_local' => $uuidControl,
            'version_base' => null,
            'datos' => [
                'codigo_rgb_id_animal' => $codigoDefinitivo,
                'fecha_control' => date('Y-m-d'),
                'evento' => 'Vacunación',
                'responsable' => 'Prueba',
                'observaciones' => '',
                'productos_aplicados' => [],
            ],
        ]],
    ], $token);
    comprobar(
        ($control['cuerpo']['resultados'][0]['resultado'] ?? '') === 'creado',
        'El control sanitario sube con el código definitivo',
        (string) ($control['cuerpo']['resultados'][0]['mensaje'] ?? '')
    );

    echo "\n=== 4. Movimiento con el código PROVISIONAL (sin remapear) ===\n";
    $movProvisional = llamar_api($base + [
        'action' => 'push',
        'modulo' => 'movimiento_rebano',
        'cambios' => [[
            'uuid_local' => $uuidMovProvisional,
            'version_base' => null,
            'datos' => [
                'tipo_movimiento' => 'LOTE',
                'mlotes_origen_id' => 7,
                'mlotes_destino_id' => 6,
                'fecha_movimiento' => date('Y-m-d H:i:s'),
                'motivo' => 'Prueba de flujo offline',
                'observaciones' => '',
                'animales' => [$codigoProvisional],
            ],
        ]],
    ], $token);
    $mensajeMov = $movProvisional['cuerpo']['resultados'][0]['mensaje'] ?? '';
    comprobar(
        strpos($mensajeMov, 'Ninguno de los animales seleccionados está activo en este fundo') === 0,
        'Se reproduce el error del producto al usar el código provisional',
        $mensajeMov
    );

    echo "\n=== 5. Movimiento con el código DEFINITIVO (lo que hace la app corregida) ===\n";
    $movBueno = llamar_api($base + [
        'action' => 'push',
        'modulo' => 'movimiento_rebano',
        'cambios' => [[
            'uuid_local' => $uuidMovBueno,
            'version_base' => null,
            'datos' => [
                'tipo_movimiento' => 'LOTE',
                'mlotes_origen_id' => 7,
                'mlotes_destino_id' => 6,
                'fecha_movimiento' => date('Y-m-d H:i:s'),
                'motivo' => 'Prueba de flujo offline',
                'observaciones' => '',
                'animales' => [$codigoDefinitivo],
            ],
        ]],
    ], $token);
    comprobar(
        ($movBueno['cuerpo']['resultados'][0]['resultado'] ?? '') === 'creado',
        'El movimiento sube con el código definitivo',
        (string) ($movBueno['cuerpo']['resultados'][0]['mensaje'] ?? '')
    );

    $lote = $conexion->query(
        'SELECT mlotes_id FROM manimales WHERE uuid_local = ?',
        [$uuidAnimal]
    )->fetch();
    comprobar((int) ($lote['mlotes_id'] ?? 0) === 6, 'El animal quedó en el lote de destino', 'lote=' . ($lote['mlotes_id'] ?? '—'));

} catch (\Throwable $e) {
    $fallos++;
    echo '  FALLA excepción: ' . $e->getMessage() . "\n";
} finally {
    limpiar();
    echo "\nLimpieza: se borraron los registros de prueba.\n";
}

echo "\nResultado: $ok correctas, $fallos con falla.\n";
exit($fallos === 0 ? 0 : 1);
