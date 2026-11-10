<?php
/**
 * VALIDACIÓN INTEGRAL DEL MODO OFFLINE (cliente ↔ servidor).
 *
 * Reproduce, contra el API REAL, el recorrido completo de un productor que
 * trabaja sin conexión y después sincroniza:
 *
 *   Fase 1  alta offline de 4 animales (2 hembras y 2 machos) con el código
 *           provisional que genera el equipo (SOVCA-0<fundo>-PEND-xxxxxx)
 *   Fase 2  remapeo de códigos (lo que hace sync.js al recibir el definitivo)
 *   Fase 3  servicios (hembra + macho recién creados), edición y conflicto
 *   Fase 4  palpación / gestación (edición del servicio)
 *   Fase 5  pesajes (incluido el duplicado que debe rechazarse)
 *   Fase 6  controles sanitarios individuales con productos aplicados
 *   Fase 7  producción de leche
 *   Fase 8  control sanitario por lote
 *   Fase 9  movimiento de rebaño (lote de origen → destino)
 *   Fase 10 nacimiento con dos crías y cierre del ciclo reproductivo
 *   Fase 11 fichaje individual de las dos crías del nacimiento
 *   Fase 12 desincorporación (venta)
 *   Fase 13 idempotencia (reenviar lo mismo no duplica)
 *   Fase 14 ida y vuelta: pull de los 9 módulos y comparación campo a campo
 *
 * Al final imprime un % de consistencia por módulo y global.
 *
 * Uso (desde la raíz del proyecto, con Apache sirviendo /sovca):
 *   php api/test/validar_offline_completo.php
 *
 * Variables opcionales: SOVCA_TEST_FUNDO, SOVCA_TEST_USUARIO, SOVCA_TEST_API.
 * El script borra TODO lo que crea (incluso si falla a mitad de camino) y
 * restaura los contadores de correlativos del fundo.
 *
 * ⚠ Usa la base configurada: NO ejecutar contra producción.
 */

chdir(dirname(__DIR__));
require_once 'config/bootstrap.php';
require_once 'lib/ApiAuth.php';
require_once '../models/UsuarioDispositivoOffline.php';
require_once '../models/Musuario.php';

$FUNDO = (int) (getenv('SOVCA_TEST_FUNDO') ?: 2);
$USUARIO = (int) (getenv('SOVCA_TEST_USUARIO') ?: 4);
$URL_SYNC = getenv('SOVCA_TEST_API') ?: 'http://127.0.0.1/sovca/api/sync_controller.php';
$URL_CATALOGOS = str_replace('sync_controller.php', 'catalogos_controller.php', $URL_SYNC);

$GLOBALS['ok'] = 0;
$GLOBALS['fallos'] = 0;
$GLOBALS['cmp_ok'] = [];
$GLOBALS['cmp_total'] = [];
$GLOBALS['creados'] = [];   // modulo => [uuid => datos enviados]

// --------------------------------------------------------------------------
// utilidades de salida
// --------------------------------------------------------------------------
function comprobar(bool $condicion, string $descripcion, string $detalle = ''): void
{
    if ($condicion) {
        $GLOBALS['ok']++;
        echo '  OK    ' . $descripcion . ($detalle !== '' ? '  [' . $detalle . ']' : '') . "\n";
    } else {
        $GLOBALS['fallos']++;
        echo '  FALLA ' . $descripcion . ($detalle !== '' ? '  [' . $detalle . ']' : '') . "\n";
    }
}

function titulo(string $texto): void
{
    echo "\n=== " . $texto . " ===\n";
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

function llamar_api(array $payload, string $token, string $url): array
{
    $contexto = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nAuthorization: Bearer " . $token . "\r\n",
            'content' => json_encode($payload),
            'timeout' => 40,
            'ignore_errors' => true,
        ],
    ]);

    $crudo = @file_get_contents($url, false, $contexto);
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

/** push de un módulo con uno o varios cambios. */
function push(string $token, string $modulo, array $cambios, array $base): array
{
    global $URL_SYNC;
    return llamar_api($base + [
        'action' => 'push',
        'modulo' => $modulo,
        'cambios' => array_values($cambios),
    ], $token, $URL_SYNC);
}

function resultado_de(array $respuesta, int $indice = 0): array
{
    return $respuesta['cuerpo']['resultados'][$indice] ?? [];
}

function mensaje_de(array $respuesta, int $indice = 0): string
{
    return (string) (resultado_de($respuesta, $indice)['mensaje'] ?? '');
}

function tipo_de(array $respuesta, int $indice = 0): string
{
    return (string) (resultado_de($respuesta, $indice)['resultado'] ?? 'sin_respuesta');
}

/** pull de un módulo desde una marca. */
function pull(string $token, string $modulo, string $desde, array $base): array
{
    global $URL_SYNC;
    $resp = llamar_api($base + [
        'action' => 'pull',
        'modulo' => $modulo,
        'desde' => $desde,
    ], $token, $URL_SYNC);

    return [
        'registros' => $resp['cuerpo']['registros'] ?? [],
        'http' => $resp['http'],
    ];
}

// --------------------------------------------------------------------------
// comparación de valores (para el % de consistencia)
// --------------------------------------------------------------------------
function normalizar_valor($valor)
{
    if ($valor === null) {
        return '';
    }
    if (is_bool($valor)) {
        return $valor ? '1' : '0';
    }
    if (is_array($valor)) {
        return json_encode($valor, JSON_UNESCAPED_UNICODE);
    }
    if (is_numeric($valor)) {
        $n = (float) $valor;
        if (floor($n) === $n) {
            return (string) (int) $n;
        }
        return (string) round($n, 4);
    }
    $texto = trim((string) $valor);
    // Fechas: '2026-11-01' y '2026-11-01 00:00:00' son el mismo dato.
    if (preg_match('/^\d{4}-\d{2}-\d{2}( 00:00:00)?$/', $texto)) {
        return substr($texto, 0, 10);
    }
    return $texto;
}

function mismo_valor($esperado, $obtenido): bool
{
    return normalizar_valor($esperado) === normalizar_valor($obtenido);
}

/**
 * Compara lo enviado contra lo que devuelve el pull y acumula el porcentaje.
 * Los campos que el servidor recalcula (códigos, fechas derivadas, ciclos)
 * no entran en el %: se comprueban aparte con reglas explícitas.
 */
function medir_consistencia(string $modulo, array $enviados, array $filas, array $ignorar = []): void
{
    $porUuid = [];
    foreach ($filas as $fila) {
        if (!empty($fila['uuid_local'])) {
            $porUuid[$fila['uuid_local']] = $fila;
        }
    }

    $ok = 0;
    $total = 0;
    $detalleFallos = [];

    foreach ($enviados as $uuid => $datos) {
        if (!isset($porUuid[$uuid])) {
            continue;
        }
        $fila = $porUuid[$uuid];
        foreach ($datos as $campo => $valor) {
            if (in_array($campo, $ignorar, true)) {
                continue;
            }
            if (!array_key_exists($campo, $fila)) {
                continue;
            }
            $total++;
            if (mismo_valor($valor, $fila[$campo])) {
                $ok++;
            } elseif (count($detalleFallos) < 6) {
                $detalleFallos[] = $campo . ' (enviado ' . json_encode($valor)
                    . ' ≠ servidor ' . json_encode($fila[$campo]) . ')';
            }
        }
    }

    $GLOBALS['cmp_ok'][$modulo] = ($GLOBALS['cmp_ok'][$modulo] ?? 0) + $ok;
    $GLOBALS['cmp_total'][$modulo] = ($GLOBALS['cmp_total'][$modulo] ?? 0) + $total;

    $pct = $total > 0 ? round($ok * 100 / $total, 1) : 100.0;
    comprobar($total === 0 || $ok === $total, "Ida y vuelta de '$modulo' coincide con lo enviado", $ok . '/' . $total . ' campos = ' . $pct . '%');
    foreach ($detalleFallos as $fallo) {
        echo '        · ' . $fallo . "\n";
    }
}

function guardar_enviado(string $modulo, string $uuid, array $datos): void
{
    $GLOBALS['creados'][$modulo][$uuid] = $datos;
}

// --------------------------------------------------------------------------
// preparación: catálogos reales del fundo
// --------------------------------------------------------------------------
$conexion = \Musuario::connection();
$token = null;
$base = ['fundo_id' => $FUNDO];

$uuids = [
    'animal_h1' => uuid4(), 'animal_m1' => uuid4(),
    'animal_h2' => uuid4(), 'animal_m2' => uuid4(),
    'ternero'   => uuid4(), 'ternero_2' => uuid4(),
    'servicio_1' => uuid4(), 'servicio_2' => uuid4(),
    'servicio_provisional' => uuid4(),
    'peso_1' => uuid4(), 'peso_2' => uuid4(), 'peso_dup' => uuid4(),
    'control_1' => uuid4(), 'control_dup' => uuid4(),
    'leche_1' => uuid4(), 'leche_2' => uuid4(), 'leche_dup' => uuid4(),
    'control_lote' => uuid4(),
    'movimiento' => uuid4(),
    'nacimiento' => uuid4(),
    'baja' => uuid4(),
];

$contadoresPrevios = [];
$tablasContador = ['manimalxfundos_contador', 'mnacimientoxfundos_contador', 'mserviciosxfundos_contador'];

/** Borra todo lo que creó la prueba y restaura los contadores. */
function limpiar(): void
{
    global $conexion, $uuids, $contadoresPrevios, $tablasContador, $FUNDO;

    // Movimientos (cabecera + detalle)
    foreach ([$uuids['movimiento']] as $uuid) {
        $fila = $conexion->query('SELECT id FROM mmovimientos_rebano WHERE uuid_local = ? AND mfundos_id = ?', [$uuid, $FUNDO])->fetch();
        if ($fila) {
            $conexion->query('DELETE FROM mmovimientos_rebano_detalle WHERE mmovimientos_rebano_id = ?', [(int) $fila['id']]);
            $conexion->query('DELETE FROM mmovimientos_rebano WHERE id = ?', [(int) $fila['id']]);
        }
    }

    // Control sanitario por lote (+ detalle)
    $fila = $conexion->query('SELECT id, codigo_mcs_lote FROM mcontrolsanitarioxlotes WHERE uuid_local = ? AND mfundos_id = ?', [$uuids['control_lote'], $FUNDO])->fetch();
    if ($fila) {
        $conexion->query('DELETE FROM mdetacrlsanitariolotes WHERE mcontrolsanitarioxlotes_id = ?', [(int) $fila['id']]);
        $conexion->query('DELETE FROM mdetacsxlotes WHERE codigo_mcs_lote = ? AND mfundos_id = ?', [$fila['codigo_mcs_lote'], $FUNDO]);
        $conexion->query('DELETE FROM mcontrolsanitarioxlotes WHERE id = ?', [(int) $fila['id']]);
    }

    // Controles individuales (+ productos)
    foreach ([$uuids['control_1'], $uuids['control_dup']] as $uuid) {
        $fila = $conexion->query('SELECT id FROM mcontrolsanitarios WHERE uuid_local = ? AND mfundos_id = ?', [$uuid, $FUNDO])->fetch();
        if ($fila) {
            $conexion->query('DELETE FROM mdetacrlsanitarios WHERE mcontrolsanitarios_id = ?', [(int) $fila['id']]);
            $conexion->query('DELETE FROM mcontrolsanitarios WHERE id = ?', [(int) $fila['id']]);
        }
    }

    // Nacimientos (+ crías + relación fundo)
    $fila = $conexion->query('SELECT id FROM mregnacimientos WHERE uuid_local = ? AND mfundos_id = ?', [$uuids['nacimiento'], $FUNDO])->fetch();
    if ($fila) {
        $conexion->query('DELETE FROM mternerosxnacimientos WHERE mregnacimientos_id = ?', [(int) $fila['id']]);
        $conexion->query('DELETE FROM mnacimientoxfundos WHERE mregnacimientos_id = ?', [(int) $fila['id']]);
        $conexion->query('DELETE FROM mregnacimientos WHERE id = ?', [(int) $fila['id']]);
    }

    // Servicios
    foreach ([$uuids['servicio_1'], $uuids['servicio_2'], $uuids['servicio_provisional']] as $uuid) {
        $conexion->query('DELETE FROM mregservicios WHERE uuid_local = ? AND mfundos_id = ?', [$uuid, $FUNDO]);
    }

    // Pesajes, leche, bajas
    foreach ([$uuids['peso_1'], $uuids['peso_2'], $uuids['peso_dup']] as $uuid) {
        $conexion->query('DELETE FROM mconsecutivopesos WHERE uuid_local = ? AND mfundos_id = ?', [$uuid, $FUNDO]);
    }
    foreach ([$uuids['leche_1'], $uuids['leche_2'], $uuids['leche_dup']] as $uuid) {
        $conexion->query('DELETE FROM mprolecheras WHERE uuid_local = ? AND mfundos_id = ?', [$uuid, $FUNDO]);
    }
    $conexion->query('DELETE FROM mdesincorporacionanimales WHERE uuid_local = ? AND mfundos_id = ?', [$uuids['baja'], $FUNDO]);

    // Animales (+ relación fundo)
    foreach (['animal_h1', 'animal_m1', 'animal_h2', 'animal_m2', 'ternero', 'ternero_2'] as $clave) {
        $fila = $conexion->query('SELECT id FROM manimales WHERE uuid_local = ? AND mfundos_id = ?', [$uuids[$clave], $FUNDO])->fetch();
        if ($fila) {
            $conexion->query('DELETE FROM manimalxfundos WHERE manimales_id = ?', [(int) $fila['id']]);
            $conexion->query('DELETE FROM manimales WHERE id = ?', [(int) $fila['id']]);
        }
    }

    // Contadores de correlativos del fundo
    foreach ($tablasContador as $tabla) {
        if (array_key_exists($tabla, $contadoresPrevios) && $contadoresPrevios[$tabla] !== null) {
            $conexion->query(
                "UPDATE {$tabla} SET ultimo_numero_asignado = ? WHERE mfundos_id = ?",
                [$contadoresPrevios[$tabla], $FUNDO]
            );
        }
    }

    // Y se dejan nunca por debajo del último código realmente usado: es la
    // misma normalización que scripts/migracion_correlativos_contador.sql, y
    // evita reintroducir el desfase que rompía los nacimientos.
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

    $conexion->query("DELETE FROM api_tokens WHERE dispositivo = 'validacion-offline-completo'");
}

// --------------------------------------------------------------------------
// arranque
// --------------------------------------------------------------------------
foreach ($tablasContador as $tabla) {
    $fila = $conexion->query("SELECT ultimo_numero_asignado FROM {$tabla} WHERE mfundos_id = ?", [$FUNDO])->fetch();
    $contadoresPrevios[$tabla] = $fila ? (int) $fila['ultimo_numero_asignado'] : null;
}

$vinculo = \UsuarioDispositivoOffline::find('first', [
    'conditions' => ['musuarios_id = ? and mfundos_id = ? and status = 1', $USUARIO, $FUNDO],
]);

echo "Validación integral del modo offline\n";
echo 'Fundo ' . $FUNDO . ' · usuario ' . $USUARIO . ' · API ' . $URL_SYNC . "\n";

if (!$vinculo) {
    echo "\nNo hay dispositivo vinculado para el usuario {$USUARIO} en el fundo {$FUNDO}.\n";
    exit(1);
}
$base['dispositivo_id'] = $vinculo->dispositivo_id;

$marcaInicio = date('Y-m-d H:i:s', time() - 60);

try {
    $token = \Api\ApiAuth::crearToken($USUARIO, null, 'validacion-offline-completo')['token'];

    // ---------------------------------------------------------------- 0
    titulo('0. Catálogos que el equipo descarga antes de salir al campo');
    $cat = llamar_api(['action' => 'listar'] + $base, $token, $URL_CATALOGOS);
    $catalogos = $cat['cuerpo'] ?? [];
    comprobar($cat['http'] === 200 && !empty($catalogos['aptitudes']), 'Se descargan las aptitudes', 'http=' . $cat['http']);
    comprobar(!empty($catalogos['razas']), 'Se descargan las razas');
    comprobar(!empty($catalogos['lotes']), 'Se descargan los lotes');
    comprobar(!empty($catalogos['potreros']), 'Se descargan los potreros');
    comprobar(!empty($catalogos['tipos_servicio']), 'Se descargan los tipos de servicio');
    comprobar(!empty($catalogos['estados_fisiologicos']), 'Se descargan los estados fisiológicos');
    comprobar(!empty($catalogos['productos_control']), 'Se descargan los productos sanitarios');

    $aptitud = (int) ($catalogos['aptitudes'][0]['id'] ?? 1);
    $raza = (int) ($catalogos['razas'][0]['id'] ?? 1);
    $loteA = (int) ($catalogos['lotes'][0]['id'] ?? 6);
    $loteB = (int) ($catalogos['lotes'][1]['id'] ?? 7);
    $potreroA = (int) ($catalogos['potreros'][0]['id'] ?? 1);
    $potreroB = (int) ($catalogos['potreros'][1]['id'] ?? 2);
    $tipoMonta = 1;
    $tipoIA = 2;
    $producto = (int) ($catalogos['productos_control'][0]['id'] ?? 1);
    $productoNombre = (string) ($catalogos['productos_control'][0]['nombre'] ?? 'Producto');
    $gestante = 1;
    $sinPalpar = 3;

    echo '  catálogos: aptitud=' . $aptitud . ' raza=' . $raza . ' loteA=' . $loteA
        . ' loteB=' . $loteB . ' potreroA=' . $potreroA . ' producto=' . $producto . "\n";

    // ---------------------------------------------------------------- 1
    titulo('1. Alta offline de 4 animales (códigos provisionales del equipo)');
    $provisional = function (string $prefijo) use ($FUNDO): string {
        return $prefijo . '-0' . $FUNDO . '-PEND-' . bin2hex(random_bytes(3));
    };
    $arete = function (): string {
        return 'T' . random_int(1000000, 9999999);
    };

    $codH1 = $provisional('SOVCA');
    $codM1 = $provisional('SOVCA');
    $codH2 = $provisional('SOVCA');
    $codM2 = $provisional('SOVCA');

    $animal = function (string $codigo, string $nombre, int $sexo, string $arete, string $nacimiento, int $lote) use ($aptitud, $raza): array {
        return [
            'codigo_rgb_id_animal' => $codigo,
            'nombre' => $nombre,
            'sexo' => $sexo,
            'nro_arete' => $arete,
            'aptitud' => $aptitud,
            'mrazas_id' => $raza,
            'estatus_animal' => 1,
            'servicio' => 0,
            'f_nacimiento' => $nacimiento,
            'f_ingreso' => $nacimiento,
            'nac_fundo' => true,
            'mlotes_id' => $lote,
            'procedencia' => 'Nacido en este fundo',
            'observaciones' => 'Alta offline de prueba',
        ];
    };

    $h1 = $animal($codH1, 'HEMBRA OFFLINE UNO', 1, $arete(), date('Y-m-d', strtotime('-3 years')), $loteA);
    $m1 = $animal($codM1, 'MACHO OFFLINE UNO', 2, $arete(), date('Y-m-d', strtotime('-3 years')), $loteA);
    $h2 = $animal($codH2, 'HEMBRA OFFLINE DOS', 1, $arete(), date('Y-m-d', strtotime('-4 years')), $loteA);
    $m2 = $animal($codM2, 'MACHO OFFLINE DOS', 2, $arete(), date('Y-m-d', strtotime('-3 years')), $loteA);

    $cambiosAnimales = [];
    foreach ([
        'animal_h1' => $h1, 'animal_m1' => $m1, 'animal_h2' => $h2, 'animal_m2' => $m2,
    ] as $clave => $datos) {
        $cambiosAnimales[] = [
            'uuid_local' => $uuids[$clave],
            'version_base' => null,
            'datos' => $datos,
        ];
        guardar_enviado('animales', $uuids[$clave], $datos);
    }

    $respAnimales = push($token, 'animales', $cambiosAnimales, $base);
    $resAnimales = $respAnimales['cuerpo']['resultados'] ?? [];
    comprobar($respAnimales['http'] === 200, 'El lote de 4 animales se envía en una sola petición', 'http=' . $respAnimales['http']);
    comprobar(count($resAnimales) === 4, 'El servidor devuelve 4 resultados', count($resAnimales) . ' resultados');

    $definitivo = [];
    foreach ($resAnimales as $r) {
        $definitivo[$r['uuid_local']] = $r['campos_resueltos']['codigo_rgb_id_animal'] ?? null;
        comprobar(($r['resultado'] ?? '') === 'creado', 'Animal creado con código definitivo', (string) ($r['campos_resueltos']['codigo_rgb_id_animal'] ?? $r['mensaje'] ?? ''));
        comprobar(strpos((string) ($r['codigo_anterior'] ?? ''), '-PEND-') !== false, 'Devuelve codigo_anterior para remapear la copia local', (string) ($r['codigo_anterior'] ?? '—'));
    }
    $codH1 = $definitivo[$uuids['animal_h1']] ?? null;
    $codM1 = $definitivo[$uuids['animal_m1']] ?? null;
    $codH2 = $definitivo[$uuids['animal_h2']] ?? null;
    $codM2 = $definitivo[$uuids['animal_m2']] ?? null;
    comprobar(count(array_unique(array_filter($definitivo))) === 4, 'Los 4 códigos definitivos son distintos');
    comprobar((bool) preg_match('/^SOVCA-0' . $FUNDO . '-0\d{2,}$/', (string) $codH1), 'El formato del código es el de la web', (string) $codH1);

    // ---------------------------------------------------------------- 2
    titulo('2. Remapeo: los registros que apuntan al código provisional se corrigen');
    $respProv = push($token, 'control_sanitario', [[
        'uuid_local' => uuid4(),
        'version_base' => null,
        'datos' => [
            'codigo_rgb_id_animal' => (string) ($resAnimales[0]['codigo_anterior'] ?? ''),
            'fecha_control' => date('Y-m-d'),
            'evento' => 'Vacunación',
            'responsable' => 'Prueba',
            'observaciones' => '',
            'productos_aplicados' => [],
        ],
    ]], $base);
    comprobar(strpos(mensaje_de($respProv), 'todavía no tiene código definitivo') !== false, 'Sin remapear, el servidor rechaza el código provisional (no guarda huérfanos)', mensaje_de($respProv));

    // ---------------------------------------------------------------- 3
    titulo('3. Servicios con hembra y macho recién creados');

    // 3a: servicio que apunta al código provisional → rechazo claro
    $respServProv = push($token, 'servicios', [[
        'uuid_local' => $uuids['servicio_provisional'],
        'version_base' => null,
        'datos' => [
            'codigo_servicio' => 'RSA-PEND-' . time(),
            'codigo_rgb_id_animal_hembra' => (string) ($resAnimales[0]['codigo_anterior'] ?? ''),
            'codigo_rgb_id_animal_macho' => $codM1,
            'codigo_rgb_id_animal_dona' => '',
            'f_servicio' => date('Y-m-d'),
            'nro_servicio' => 1,
            'mservicios_id' => $tipoMonta,
            'aptitud' => $aptitud,
            'mrazas_id' => $raza,
            'observaciones' => '',
            'medofisiologicos_id' => $sinPalpar,
            'parto' => 0,
            'f_palpacion' => null,
            'fecha_parto' => null,
            'nro_terneros' => null,
            'dnp' => null,
            'f_probable_parto' => null,
            'f_ultimo_parto' => null,
            'f_secado' => null,
        ],
    ]], $base);
    comprobar(strpos(mensaje_de($respServProv), 'todavía no tiene código definitivo') !== false, 'Servicio con código provisional: rechazo explícito', mensaje_de($respServProv));

    // 3b: servicio real (hembra H1 + macho M1 recién creados)
    $servicio1 = [
        'codigo_servicio' => 'RSA-PEND-' . (time() + 1),
        'codigo_rgb_id_animal_hembra' => $codH1,
        'codigo_rgb_id_animal_macho' => $codM1,
        'codigo_rgb_id_animal_dona' => '',
        'f_servicio' => date('Y-m-d', strtotime('-30 days')),
        'nro_servicio' => 1,
        'mservicios_id' => $tipoMonta,
        'aptitud' => $aptitud,
        'mrazas_id' => $raza,
        'observaciones' => 'Servicio registrado sin conexión',
        'medofisiologicos_id' => $sinPalpar,
        'parto' => 0,
        'f_palpacion' => null,
        'fecha_parto' => null,
        'nro_terneros' => null,
        'dnp' => null,
        'f_probable_parto' => null,
        'f_ultimo_parto' => null,
        'f_secado' => null,
    ];
    $respS1 = push($token, 'servicios', [[
        'uuid_local' => $uuids['servicio_1'],
        'version_base' => null,
        'datos' => $servicio1,
    ]], $base);
    $rS1 = resultado_de($respS1);
    comprobar(tipo_de($respS1) === 'creado', 'Servicio de H1 + M1 (ambos creados offline) se registra', tipo_de($respS1) . ' ' . mensaje_de($respS1));
    $codServicio1 = (string) ($rS1['campos_resueltos']['codigo_servicio'] ?? '');
    comprobar((bool) preg_match('/^RSA-0' . $FUNDO . '-0\d{2,}$/', $codServicio1), 'El servidor asigna el código de servicio (RSA-…)', $codServicio1);
    comprobar(!empty($rS1['campos_resueltos']['f_probable_palpacion']), 'El servidor calcula la fecha probable de palpación', (string) ($rS1['campos_resueltos']['f_probable_palpacion'] ?? '—'));
    guardar_enviado('servicios', $uuids['servicio_1'], $servicio1);
    $versionS1 = (int) ($rS1['version'] ?? 1);

    // 3c: servicio gestante (H2 + M2), luego se palpa
    $servicio2 = $servicio1;
    $servicio2['codigo_servicio'] = 'RSA-PEND-' . (time() + 2);
    $servicio2['codigo_rgb_id_animal_hembra'] = $codH2;
    $servicio2['codigo_rgb_id_animal_macho'] = $codM2;
    $servicio2['f_servicio'] = date('Y-m-d', strtotime('-275 days'));
    $servicio2['observaciones'] = 'Servicio gestante de prueba';

    $respS2 = push($token, 'servicios', [[
        'uuid_local' => $uuids['servicio_2'],
        'version_base' => null,
        'datos' => $servicio2,
    ]], $base);
    comprobar(tipo_de($respS2) === 'creado', 'Servicio gestante de H2 + M2 se registra', tipo_de($respS2) . ' ' . mensaje_de($respS2));
    guardar_enviado('servicios', $uuids['servicio_2'], $servicio2);
    $versionS2 = (int) (resultado_de($respS2)['version'] ?? 1);

    // ---------------------------------------------------------------- 4
    titulo('4. Palpación: edición del servicio (control de versión)');
    $edicion = [
        'medofisiologicos_id' => $gestante,
        'f_palpacion' => date('Y-m-d', strtotime('-180 days')),
        'observaciones' => 'Palpación positiva',
    ];
    $respEdit = push($token, 'servicios', [[
        'uuid_local' => $uuids['servicio_2'],
        'version_base' => $versionS2,
        'datos' => $edicion,
    ]], $base);
    comprobar(tipo_de($respEdit) === 'actualizado', 'La palpación se guarda como edición', tipo_de($respEdit) . ' ' . mensaje_de($respEdit));
    $versionS2 = (int) (resultado_de($respEdit)['version'] ?? $versionS2 + 1);
    // El servidor ya tiene la observación nueva: es lo que verá el pull.
    $GLOBALS['creados']['servicios'][$uuids['servicio_2']]['observaciones'] = $edicion['observaciones'];

    // Conflicto real: editar primero (sube la versión) y después enviar un
    // cambio construido sobre la versión anterior.
    $respEdit1 = push($token, 'servicios', [[
        'uuid_local' => $uuids['servicio_1'],
        'version_base' => $versionS1,
        'datos' => ['observaciones' => 'Primera edición del equipo'],
    ]], $base);
    comprobar(tipo_de($respEdit1) === 'actualizado', 'La edición del servicio sube la versión', tipo_de($respEdit1) . ' ' . mensaje_de($respEdit1));
    $GLOBALS['creados']['servicios'][$uuids['servicio_1']]['observaciones'] = 'Primera edición del equipo';

    $respViejo = push($token, 'servicios', [[
        'uuid_local' => $uuids['servicio_1'],
        'version_base' => $versionS1,
        'datos' => ['observaciones' => 'Cambio hecho en otro equipo'],
    ]], $base);
    $resViejo = resultado_de($respViejo);
    comprobar(
        tipo_de($respViejo) === 'conflicto',
        'Una edición con versión anterior se marca como conflicto (no pisa datos)',
        tipo_de($respViejo) . ' ' . mensaje_de($respViejo)
    );
    comprobar(($resViejo['servidor']['version'] ?? 0) > $versionS1, 'El conflicto trae la versión del servidor para resolverlo', 'version=' . ($resViejo['servidor']['version'] ?? '—'));

    // ---------------------------------------------------------------- 5
    titulo('5. Pesajes (incluye el duplicado del mismo día)');
    $peso1 = ['codigo_rgb_id_animal' => $codH1, 'fecha_pesaje' => date('Y-m-d', strtotime('-40 days')), 'peso' => 320];
    $peso2 = ['codigo_rgb_id_animal' => $codH1, 'fecha_pesaje' => date('Y-m-d'), 'peso' => 335];
    $pesoDup = ['codigo_rgb_id_animal' => $codH1, 'fecha_pesaje' => date('Y-m-d'), 'peso' => 340];

    $respPesos = push($token, 'pesos', [
        ['uuid_local' => $uuids['peso_1'], 'version_base' => null, 'datos' => $peso1],
        ['uuid_local' => $uuids['peso_2'], 'version_base' => null, 'datos' => $peso2],
        ['uuid_local' => $uuids['peso_dup'], 'version_base' => null, 'datos' => $pesoDup],
    ], $base);
    comprobar(tipo_de($respPesos, 0) === 'creado', 'Pesaje 1 creado', tipo_de($respPesos, 0) . ' ' . mensaje_de($respPesos, 0));
    comprobar(tipo_de($respPesos, 1) === 'creado', 'Pesaje 2 creado', tipo_de($respPesos, 1) . ' ' . mensaje_de($respPesos, 1));
    comprobar(strpos(mensaje_de($respPesos, 2), 'ya tiene un pesaje registrado') !== false, 'Segundo pesaje del mismo día se rechaza con mensaje claro', mensaje_de($respPesos, 2));
    guardar_enviado('pesos', $uuids['peso_1'], $peso1);
    guardar_enviado('pesos', $uuids['peso_2'], $peso2);

    // ---------------------------------------------------------------- 6
    titulo('6. Control sanitario individual con productos aplicados');
    $control1 = [
        'codigo_rgb_id_animal' => $codH1,
        'fecha_control' => date('Y-m-d', strtotime('-20 days')),
        'fecha_prox_control' => date('Y-m-d', strtotime('+100 days')),
        'evento' => 'Desparasitación',
        'responsable' => 'Prueba offline',
        'observaciones' => 'Aplicado en el corral',
        'productos_aplicados' => [
            ['mproductos_id' => $producto, 'producto_nombre' => $productoNombre, 'dosis' => '10 ml', 'detalles' => 'Vía intramuscular'],
        ],
    ];
    $controlDup = $control1;
    $controlDup['evento'] = 'Duplicado mismo día';

    $respControl = push($token, 'control_sanitario', [
        ['uuid_local' => $uuids['control_1'], 'version_base' => null, 'datos' => $control1],
        ['uuid_local' => $uuids['control_dup'], 'version_base' => null, 'datos' => $controlDup],
    ], $base);
    comprobar(tipo_de($respControl, 0) === 'creado', 'Control sanitario creado con productos', tipo_de($respControl, 0) . ' ' . mensaje_de($respControl, 0));
    comprobar(strpos(mensaje_de($respControl, 1), 'ya tiene un control sanitario registrado') !== false, 'Control duplicado del mismo día se rechaza', mensaje_de($respControl, 1));
    guardar_enviado('control_sanitario', $uuids['control_1'], $control1);

    $productosBd = $conexion->query(
        'SELECT COUNT(*) AS n FROM mdetacrlsanitarios d
           JOIN mcontrolsanitarios c ON c.id = d.mcontrolsanitarios_id
          WHERE c.uuid_local = ? AND c.mfundos_id = ?',
        [$uuids['control_1'], $FUNDO]
    )->fetch();
    comprobar((int) ($productosBd['n'] ?? 0) === 1, 'El producto aplicado queda en el detalle del servidor', 'filas=' . (int) ($productosBd['n'] ?? 0));

    // ---------------------------------------------------------------- 7
    titulo('7. Producción de leche');
    $leche1 = ['codigo_rgb_id_animal_hembra' => $codH1, 'fecha_produccion' => date('Y-m-d', strtotime('-1 day')), 'cantidad_leche_m' => 12.5, 'cantidad_leche_t' => 6.25];
    $leche2 = ['codigo_rgb_id_animal_hembra' => $codH1, 'fecha_produccion' => date('Y-m-d'), 'cantidad_leche_m' => 13, 'cantidad_leche_t' => 6.5];
    $lecheDup = $leche2;

    $respLeche = push($token, 'produccion_leche', [
        ['uuid_local' => $uuids['leche_1'], 'version_base' => null, 'datos' => $leche1],
        ['uuid_local' => $uuids['leche_2'], 'version_base' => null, 'datos' => $leche2],
        ['uuid_local' => $uuids['leche_dup'], 'version_base' => null, 'datos' => $lecheDup],
    ], $base);
    comprobar(tipo_de($respLeche, 0) === 'creado', 'Producción de leche 1 creada', tipo_de($respLeche, 0) . ' ' . mensaje_de($respLeche, 0));
    comprobar(tipo_de($respLeche, 1) === 'creado', 'Producción de leche 2 creada', tipo_de($respLeche, 1) . ' ' . mensaje_de($respLeche, 1));
    comprobar(strpos(mensaje_de($respLeche, 2), 'ya tiene un') !== false && strpos(mensaje_de($respLeche, 2), 'producción de leche') !== false, 'Producción duplicada del mismo día se rechaza', mensaje_de($respLeche, 2));
    guardar_enviado('produccion_leche', $uuids['leche_1'], $leche1);
    guardar_enviado('produccion_leche', $uuids['leche_2'], $leche2);

    // ---------------------------------------------------------------- 8
    titulo('8. Control sanitario por lote');
    $controlLote = [
        'codigo_mcs_lote' => '',
        'mlotes_id' => $loteA,
        'lote_nombre' => 'Lote de prueba',
        'fecha_control' => date('Y-m-d', strtotime('-10 days')),
        'fecha_prox_control' => date('Y-m-d', strtotime('+80 days')),
        'evento' => 'Vacunación de lote',
        'responsable' => 'Prueba offline',
        'observaciones' => 'Todos los animales del lote',
        'productos_aplicados' => [
            ['mproductos_id' => $producto, 'producto_nombre' => $productoNombre, 'dosis' => '5 ml', 'detalles' => ''],
        ],
        'animales' => [$codH1, $codM1],
        'total_animales' => 2,
    ];
    $respLote = push($token, 'control_sanitario_lotes', [[
        'uuid_local' => $uuids['control_lote'],
        'version_base' => null,
        'datos' => $controlLote,
    ]], $base);
    $rLote = resultado_de($respLote);
    comprobar(tipo_de($respLote) === 'creado', 'Control por lote creado', tipo_de($respLote) . ' ' . mensaje_de($respLote));
    $codLote = (string) ($rLote['campos_resueltos']['codigo_mcs_lote'] ?? '');
    comprobar((bool) preg_match('/^RCS-0' . $FUNDO . '-0\d+$/', $codLote), 'El servidor genera el código del control de lote', $codLote);
    guardar_enviado('control_sanitario_lotes', $uuids['control_lote'], $controlLote);

    $detalleLote = $conexion->query(
        'SELECT COUNT(*) AS n FROM mdetacsxlotes WHERE codigo_mcs_lote = ? AND mfundos_id = ?',
        [$codLote, $FUNDO]
    )->fetch();
    comprobar((int) ($detalleLote['n'] ?? 0) === 2, 'El detalle guarda los 2 animales del lote', 'filas=' . (int) ($detalleLote['n'] ?? 0));

    // ---------------------------------------------------------------- 9
    titulo('9. Movimiento de rebaño (lote A → lote B)');
    $movimiento = [
        'tipo_movimiento' => 'LOTE',
        'mlotes_origen_id' => $loteA,
        'mlotes_destino_id' => $loteB,
        'mpotreros_origen_id' => null,
        'mpotreros_destino_id' => null,
        'fecha_movimiento' => date('Y-m-d'),
        'motivo' => 'Rotación de potreros',
        'observaciones' => 'Movimiento registrado sin conexión',
        'animales' => [$codH1, $codM1],
    ];
    $respMov = push($token, 'movimiento_rebano', [[
        'uuid_local' => $uuids['movimiento'],
        'version_base' => null,
        'datos' => $movimiento,
    ]], $base);
    comprobar(tipo_de($respMov) === 'creado', 'Movimiento de rebaño creado', tipo_de($respMov) . ' ' . mensaje_de($respMov));
    guardar_enviado('movimiento_rebano', $uuids['movimiento'], $movimiento);

    $enDestino = $conexion->query(
        'SELECT COUNT(*) AS n FROM manimales WHERE mfundos_id = ? AND codigo_rgb_id_animal IN (?, ?) AND mlotes_id = ?',
        [$FUNDO, $codH1, $codM1, $loteB]
    )->fetch();
    comprobar((int) ($enDestino['n'] ?? 0) === 2, 'Los 2 animales quedan en el lote de destino', 'filas=' . (int) ($enDestino['n'] ?? 0));

    // ---------------------------------------------------------------- 10
    titulo('10. Nacimiento con dos crías y cierre del ciclo');
    $codTernero = 'MA-0' . $FUNDO . '-PEND-' . bin2hex(random_bytes(3));
    $idTernero1 = 'CR' . random_int(100000, 999999);
    $idTernero2 = 'CR' . random_int(100000, 999999);
    $nacimiento = [
        'codigo_ternero' => $codTernero,
        'id_ternero' => $idTernero1,
        'codigo_rgb_id_animal_hembra' => $codH2,
        'codigo_rgb_id_animal_macho' => $codM2,
        'codigo_rgb_id_animal_dona' => '',
        'mregservicios_id' => null,
        'servicio_uuid_local' => $uuids['servicio_2'],
        'mtiposervicios_id' => $tipoMonta,
        'f_parto' => date('Y-m-d'),
        'peso_nacimiento' => '35',
        'estatus_nacido' => 1,
        'aptitud' => $aptitud,
        'sexo' => 1,
        'produccion_leche' => 1,
        'proposito' => 1,
        'observaciones' => 'Parto de prueba offline',
        'f_secado' => null,
        'nro_parto' => 1,
        'f_servicio' => null,
        'mtipoganaderias_id' => null,
        'terneros' => [
            ['nro_ternero' => 1, 'id_ternero' => $idTernero1, 'peso_nacimiento' => '35', 'sexo' => 1, 'estatus_nacido' => 1, 'aptitud' => $aptitud],
            ['nro_ternero' => 2, 'id_ternero' => $idTernero2, 'peso_nacimiento' => '32', 'sexo' => 2, 'estatus_nacido' => 1, 'aptitud' => $aptitud],
        ],
    ];
    $respNac = push($token, 'nacimientos', [[
        'uuid_local' => $uuids['nacimiento'],
        'version_base' => null,
        'datos' => $nacimiento,
    ]], $base);
    $rNac = resultado_de($respNac);
    comprobar(tipo_de($respNac) === 'creado', 'Nacimiento registrado', tipo_de($respNac) . ' ' . mensaje_de($respNac));
    $codTerneroFinal = (string) ($rNac['campos_resueltos']['codigo_ternero'] ?? '');
    comprobar((bool) preg_match('/^MA-0' . $FUNDO . '-0\d{2,}$/', $codTerneroFinal), 'El servidor asigna el código del ternero', $codTerneroFinal);
    comprobar(!empty($rNac['campos_resueltos']['f_servicio']), 'El servidor calcula la fecha de servicio del parto', json_encode($rNac['campos_resueltos']['f_servicio'] ?? null));
    comprobar(count($rNac['terneros'] ?? []) === 2, 'El parto guarda las 2 crías (mellizos)', count($rNac['terneros'] ?? []) . ' crías');
    comprobar(empty($rNac['campos_resueltos']['fichado']), 'El nacimiento queda pendiente de ficha', $rNac['campos_resueltos']['fichado'] ?? 'null');
    guardar_enviado('nacimientos', $uuids['nacimiento'], $nacimiento);

    $ciclo = $conexion->query(
        'SELECT estatus_ciclo, parto, nro_terneros FROM mregservicios WHERE uuid_local = ? AND mfundos_id = ?',
        [$uuids['servicio_2'], $FUNDO]
    )->fetch();
    comprobar((int) ($ciclo['estatus_ciclo'] ?? 0) === 2, 'El ciclo del servicio gestante se cierra', 'estatus_ciclo=' . (int) ($ciclo['estatus_ciclo'] ?? -1));
    comprobar((int) ($ciclo['parto'] ?? 0) === 1, 'El servicio queda marcado como parido');
    comprobar((int) ($ciclo['nro_terneros'] ?? 0) === 2, 'El servicio registra 2 terneros', 'nro_terneros=' . (int) ($ciclo['nro_terneros'] ?? -1));

    // ---------------------------------------------------------------- 11
    titulo('11. Fichaje individual de las crías (nacimiento → fichas de animal)');
    $fichaTernero = [
        'codigo_rgb_id_animal' => $provisional('SOVCA'),
        'nombre' => 'CRIA DE H2',
        'sexo' => 1,
        'nro_arete' => $arete(),
        'aptitud' => $aptitud,
        'mrazas_id' => $raza,
        'estatus_animal' => 1,
        'servicio' => 0,
        'f_nacimiento' => date('Y-m-d'),
        'f_ingreso' => date('Y-m-d'),
        'nac_fundo' => true,
        'mlotes_id' => $loteB,
        'procedencia' => 'Nacido en este fundo',
        'observaciones' => 'Ficha de la cría',
        'codigo_ternero' => $idTernero1,
    ];
    $respFicha = push($token, 'animales', [[
        'uuid_local' => $uuids['ternero'],
        'version_base' => null,
        'datos' => $fichaTernero,
        'nacimiento_uuid_local' => $uuids['nacimiento'],
        'nacimiento_nro_cria' => 1,
    ]], $base);
    comprobar(tipo_de($respFicha) === 'creado', 'Se crea la ficha de la primera cría', tipo_de($respFicha) . ' ' . mensaje_de($respFicha));
    comprobar((int) (resultado_de($respFicha)['nacimiento_cria_fichada']['nro_ternero'] ?? 0) === 1, 'El servidor vincula la primera ficha con la cría #1');
    guardar_enviado('animales', $uuids['ternero'], $fichaTernero);

    $nacFichado = $conexion->query(
        'SELECT fichado FROM mregnacimientos WHERE uuid_local = ? AND mfundos_id = ?',
        [$uuids['nacimiento'], $FUNDO]
    )->fetch();
    $fichasParciales = $conexion->query(
        'SELECT COUNT(*) AS n FROM manimales WHERE mfundos_id = ? AND codigo_ternero IN (?, ?) AND status = 1',
        [$FUNDO, $idTernero1, $idTernero2]
    )->fetch();
    comprobar(!empty($nacFichado['fichado']), 'El nacimiento registra la ficha parcial');
    comprobar((int) ($fichasParciales['n'] ?? 0) === 1, 'Solo la primera cría está fichada antes del segundo registro');
    $fichaTernero2 = array_merge($fichaTernero, [
        'codigo_rgb_id_animal' => $provisional('SOVCA'),
        'nombre' => 'CRIA DE H2 2',
        'sexo' => 2,
        'nro_arete' => $arete(),
        'codigo_ternero' => $idTernero2,
    ]);
    $respFicha2 = push($token, 'animales', [[
        'uuid_local' => $uuids['ternero_2'],
        'version_base' => null,
        'datos' => $fichaTernero2,
        'nacimiento_uuid_local' => $uuids['nacimiento'],
        'nacimiento_nro_cria' => 2,
    ]], $base);
    comprobar(tipo_de($respFicha2) === 'creado', 'Se crea la ficha de la segunda cría tras la primera', tipo_de($respFicha2) . ' ' . mensaje_de($respFicha2));
    comprobar((int) (resultado_de($respFicha2)['nacimiento_cria_fichada']['nro_ternero'] ?? 0) === 2, 'El servidor vincula la segunda ficha con la cría #2');
    guardar_enviado('animales', $uuids['ternero_2'], $fichaTernero2);

    // ---------------------------------------------------------------- 12
    titulo('12. Desincorporación (venta)');
    $baja = [
        'codigo_rgb_id_animal' => $codM2,
        'f_desincorporacion' => date('Y-m-d'),
        'tipo_desincorporacion' => 4,
        'causa_descarte' => '',
        'causa_mortalidad' => '',
        'observaciones' => 'Venta registrada sin conexión',
    ];
    $respBaja = push($token, 'desincorporacion', [[
        'uuid_local' => $uuids['baja'],
        'version_base' => null,
        'datos' => $baja,
    ]], $base);
    comprobar(tipo_de($respBaja) === 'creado', 'Desincorporación registrada', tipo_de($respBaja) . ' ' . mensaje_de($respBaja));
    guardar_enviado('desincorporacion', $uuids['baja'], $baja);

    $estatusBaja = $conexion->query(
        'SELECT mestatus_id FROM manimales WHERE mfundos_id = ? AND codigo_rgb_id_animal = ?',
        [$FUNDO, $codM2]
    )->fetch();
    comprobar((int) ($estatusBaja['mestatus_id'] ?? 0) === 4, 'El animal queda con estatus de baja (4 = venta)', 'mestatus_id=' . (int) ($estatusBaja['mestatus_id'] ?? -1));

    $bajaReintento = push($token, 'desincorporacion', [[
        'uuid_local' => $uuids['baja'],
        'version_base' => null,
        'datos' => $baja,
    ]], $base);
    comprobar(
        tipo_de($bajaReintento) === 'error'
            && strpos(mensaje_de($bajaReintento), 'no se puede modificar') !== false,
        'Reenviar la baja no la duplica (el módulo es de solo creación)',
        tipo_de($bajaReintento) . ' ' . mensaje_de($bajaReintento)
    );
    $bajasBd = $conexion->query(
        'SELECT COUNT(*) AS n FROM mdesincorporacionanimales WHERE uuid_local = ? AND mfundos_id = ?',
        [$uuids['baja'], $FUNDO]
    )->fetch();
    comprobar((int) ($bajasBd['n'] ?? 0) === 1, 'Solo hay una baja guardada', 'filas=' . (int) ($bajasBd['n'] ?? 0));

    // ---------------------------------------------------------------- 13
    titulo('13. Idempotencia: reenviar el mismo alta no duplica');
    $respRepo = push($token, 'animales', [[
        'uuid_local' => $uuids['animal_h1'],
        'version_base' => null,
        'datos' => $h1,
    ]], $base);
    comprobar(tipo_de($respRepo) === 'sin_cambios', 'Reenviar el alta devuelve "sin_cambios"', tipo_de($respRepo) . ' ' . mensaje_de($respRepo));

    $cantidad = $conexion->query(
        'SELECT COUNT(*) AS n FROM manimales WHERE mfundos_id = ? AND uuid_local = ?',
        [$FUNDO, $uuids['animal_h1']]
    )->fetch();
    comprobar((int) ($cantidad['n'] ?? 0) === 1, 'No hay animales duplicados', 'filas=' . (int) ($cantidad['n'] ?? 0));

    // ---------------------------------------------------------------- 14
    titulo('14. Ida y vuelta: pull de los 9 módulos y comparación campo a campo');
    $ignorarPorModulo = [
        // mlotes_id lo cambia el movimiento de rebaño (se verifica aparte).
        'animales' => ['codigo_rgb_id_animal', 'codigo_ternero', 'mlotes_id'],
        // El servidor regenera el código, calcula la palpación y cierra el
        // ciclo cuando hay parto; todo eso se comprueba por separado.
        'servicios' => ['codigo_servicio', 'f_probable_palpacion', 'nro_servicio', 'f_palpacion', 'medofisiologicos_id', 'nro_terneros', 'dnp', 'parto', 'fecha_parto'],
        'nacimientos' => ['codigo_ternero', 'f_servicio', 'mtipoganaderias_id', 'fichado', 'f_secado', 'terneros', 'servicio_uuid_local', 'mregservicios_id'],
        'control_sanitario' => ['productos_aplicados'],
        'control_sanitario_lotes' => ['codigo_mcs_lote', 'total_animales', 'productos_aplicados', 'lote_nombre'],
        'movimiento_rebano' => ['animales'],
        'desincorporacion' => [],
        'pesos' => [],
        'produccion_leche' => [],
    ];

    foreach (array_keys($GLOBALS['creados']) as $modulo) {
        $filas = pull($token, $modulo, $marcaInicio, $base)['registros'];
        medir_consistencia($modulo, $GLOBALS['creados'][$modulo], $filas, $ignorarPorModulo[$modulo] ?? []);
    }

    // El ternero fichado se envió por 'animales': su código también se resolvió.
    $fichaRemota = $conexion->query(
        'SELECT codigo_ternero, nombre FROM manimales WHERE mfundos_id = ? AND uuid_local = ?',
        [$FUNDO, $uuids['ternero']]
    )->fetch();
    comprobar(($fichaRemota['codigo_ternero'] ?? '') === $idTernero1, 'La ficha de la primera cría conserva su ID individual', (string) ($fichaRemota['codigo_ternero'] ?? '—'));

    // ---------------------------------------------------------------- resumen
    $okCampos = array_sum($GLOBALS['cmp_ok']);
    $totalCampos = array_sum($GLOBALS['cmp_total']);
    $pctGlobal = $totalCampos > 0 ? round($okCampos * 100 / $totalCampos, 1) : 100.0;

    titulo('RESUMEN');
    foreach ($GLOBALS['cmp_total'] as $modulo => $total) {
        $okMod = $GLOBALS['cmp_ok'][$modulo] ?? 0;
        $pctMod = $total > 0 ? round($okMod * 100 / $total, 1) : 100.0;
        printf("  %-26s %3d/%-3d campos  %5.1f%%\n", $modulo, $okMod, $total, $pctMod);
    }
    echo "\n";
    printf("  Comprobaciones funcionales: %d correctas, %d con falla\n", $GLOBALS['ok'], $GLOBALS['fallos']);
    printf("  Consistencia offline ↔ web: %d/%d campos = %.1f%%\n", $okCampos, $totalCampos, $pctGlobal);
} catch (\Throwable $e) {
    echo "\nEXCEPCIÓN: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    $GLOBALS['fallos']++;
} finally {
    titulo('Limpieza');
    limpiar();
    echo "  Se borraron los registros de prueba y se restauraron los contadores.\n";
}

$GLOBALS['fallos'] === 0 ? exit(0) : exit(1);
