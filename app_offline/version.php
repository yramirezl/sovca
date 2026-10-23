<?php
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

/**
 * Devuelve la versión del frontend. La fuente de verdad es
 * versiones.json (editado por el programador al desplegar). Este
 * PHP solo lo lee y agrega un hash técnico calculado a partir del
 * mtime de los archivos, útil para depuración.
 *
 * Si versiones.json no existe o está roto, cae a un modo degradado
 * que solo devuelve el hash técnico — la app igual funciona, pero
 * el badge muestra "?" en lugar de la versión.
 */

$rutaVersiones = __DIR__ . '/versiones.json';

$datosJson = null;
if (is_file($rutaVersiones)) {
    $crudo = @file_get_contents($rutaVersiones);
    if ($crudo !== false) {
        $datosJson = json_decode($crudo, true);
    }
}

// Hash técnico, siempre disponible: cambio en el mtime de cualquier
// archivo del shell. Sirve para diagnóstico interno, no para mostrar
// al usuario.
$archivos = [
    __DIR__ . '/index.html',
    __DIR__ . '/manifest.json',
    __DIR__ . '/css/estilos.css',
    __DIR__ . '/css/modal.css',
    __DIR__ . '/js/config.js',
    __DIR__ . '/js/dispositivo.js',
    __DIR__ . '/js/credenciales.js',
    __DIR__ . '/js/db.js',
    __DIR__ . '/js/api.js',
    __DIR__ . '/js/sync.js',
    __DIR__ . '/js/animales.js',
    __DIR__ . '/js/controlsanitario.js',
    __DIR__ . '/js/produccionleche.js',
    __DIR__ . '/js/pesos.js',
    __DIR__ . '/js/servicios.js',
    __DIR__ . '/js/nacimientos.js',
    __DIR__ . '/js/reportes.js',
    __DIR__ . '/js/buscador-animal.js',
    __DIR__ . '/js/app.js',
    __DIR__ . '/icons/icon-192.png',
    __DIR__ . '/icons/icon-512.png',
];

$partes = [];
$ultimoCambio = 0;
foreach ($archivos as $archivo) {
    if (!is_file($archivo)) continue;
    $mtime = filemtime($archivo);
    $ultimoCambio = max($ultimoCambio, (int) $mtime);
    $partes[] = basename($archivo) . ':' . $mtime;
}
$hashTecnico = $partes ? substr(sha1(implode('|', $partes)), 0, 12) : 'sin-archivos';

// Modo normal: versiones.json válido y con "actual" definido.
if (is_array($datosJson) && !empty($datosJson['actual'])) {
    $actual = (string) $datosJson['actual'];
    $lista = is_array($datosJson['versiones'] ?? null) ? $datosJson['versiones'] : [];

    // Buscar la entrada de la versión actual para tener su fecha y notas.
    $datosActual = null;
    foreach ($lista as $entrada) {
        if (is_array($entrada) && ($entrada['version'] ?? null) === $actual) {
            $datosActual = $entrada;
            break;
        }
    }

    // Excluir la versión actual del historial que se muestra (no tiene
    // sentido listarla dos veces). Dejar hasta 5 anteriores.
    $historial = [];
    foreach ($lista as $entrada) {
        if (!is_array($entrada)) continue;
        if (($entrada['version'] ?? null) === $actual) continue;
        $historial[] = [
            'version' => (string) ($entrada['version'] ?? ''),
            'fecha' => (string) ($entrada['fecha'] ?? ''),
        ];
        if (count($historial) >= 5) break;
    }

    echo json_encode([
        'version_app' => $actual,
        'fecha' => (string) ($datosActual['fecha'] ?? ''),
        'notas' => array_values((array) ($datosActual['notas'] ?? [])),
        'historial' => $historial,
        'hash_tecnico' => $hashTecnico,
        'ultimo_cambio' => $ultimoCambio,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Modo degradado: versiones.json ausente o inválido.
echo json_encode([
    'version_app' => null,
    'fecha' => '',
    'notas' => [],
    'historial' => [],
    'hash_tecnico' => $hashTecnico,
    'ultimo_cambio' => $ultimoCambio,
], JSON_UNESCAPED_UNICODE);