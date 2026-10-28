<?php

/**
 * Bootstrap común para todos los endpoints del API JSON de SOVCA.
 * Lo incluye cada controller de /api (no se accede directamente).
 *
 * Convención de rutas: este archivo vive en /sovca/api/config/, pero
 * como PHP resuelve los require_once relativos según el directorio del
 * script que originalmente recibió la petición (igual que el resto de
 * la app en /controllers), basta con usar '../config/conexion.php'
 * porque el script de entrada siempre está en /sovca/api/.
 */

date_default_timezone_set('America/Caracas');

// Nunca mostrar errores/warnings de PHP como HTML dentro de una
// respuesta JSON: deben ir al log del servidor, no al cliente.
// Esto se define ANTES de incluir conexion.php porque ese archivo
// dispara un aviso de PHP (índice de sesión no definido) al cargarse.
ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

// CORS: la PWA puede servirse desde un origen distinto al de la API
// (o desde localhost durante el desarrollo). En producción, cambiar
// el '*' por el dominio real donde vive la PWA.
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    // Preflight de CORS: el navegador solo pregunta permisos, no espera cuerpo.
    http_response_code(204);
    exit;
}

/* Marca el contexto ANTES de cargar config/conexion.php: ese archivo no
   inicia sesión PHP cuando corre como API (la PWA se autentica con token). */
if (!defined('SOVCA_API')) {
    define('SOVCA_API', true);
}

require_once '../config/conexion.php';

require_once __DIR__ . '/../lib/ApiResponse.php';
require_once __DIR__ . '/../lib/ApiAuth.php';

set_exception_handler(function (Throwable $e) {
    error_log('[API SOVCA] ' . $e->getMessage());
    \Api\ApiResponse::error('Error interno del servidor.', 500);
});

/**
 * Lee el cuerpo de la petición como JSON (uso normal desde la PWA).
 * Si no viene JSON válido, cae a $_POST para poder probar los
 * endpoints con formularios o Postman sin encabezado
 * "Content-Type: application/json".
 */
function leer_json_input(): array
{
    $crudo = file_get_contents('php://input');
    $datos = json_decode($crudo, true);

    return is_array($datos) ? $datos : $_POST;
}
