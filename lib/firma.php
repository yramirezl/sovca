<?php
/**
 * Sistema de firma digital HMAC-SHA256 para verificación pública.
 * Genera tokens únicos por animal que permiten autenticar la ficha
 * sin necesidad de login.
 */

// ============================================================
// URL PÚBLICA — se detecta sola
// ------------------------------------------------------------
// Antes estaba escrita a mano ('https://global.ruted.net/bovs'), lo que
// rompía la verificación en cualquier otra instalación: el QR de SOVCA
// abría la ficha en BOVS (o al revés) y el navegador mostraba "el animal
// ya no está activo en la plataforma".
//
// Ahora se calcula con la carpeta REAL del proyecto:
//   · en web:  esquema + host + carpeta respecto de DOCUMENT_ROOT
//   · en CLI:  https://global.ruted.net/<carpeta del proyecto>
// Si se necesita forzar otra (dominio distinto), definir la constante
// BOVS_URL_PUBLICA ANTES de incluir este archivo.
// ============================================================
if (!defined('BOVS_URL_PUBLICA')) {
    $bovs_raiz_proyecto = dirname(__DIR__);
    $bovs_carpeta       = basename($bovs_raiz_proyecto);
    $bovs_url_publica   = 'https://global.ruted.net/' . $bovs_carpeta;

    if (!empty($_SERVER['HTTP_HOST'])) {
        $bovs_docroot = isset($_SERVER['DOCUMENT_ROOT'])
            ? rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/')
            : '';
        $bovs_raiz_web = rtrim(str_replace('\\', '/', $bovs_raiz_proyecto), '/');

        $bovs_ruta_web = '';
        if ($bovs_docroot !== '' && strpos($bovs_raiz_web, $bovs_docroot) === 0) {
            $bovs_ruta_web = substr($bovs_raiz_web, strlen($bovs_docroot));
        }
        $bovs_ruta_web = '/' . trim($bovs_ruta_web, '/');

        $bovs_esquema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $bovs_url_publica = $bovs_esquema . '://' . $_SERVER['HTTP_HOST'] . $bovs_ruta_web;
    }

    define('BOVS_URL_PUBLICA', rtrim($bovs_url_publica, '/'));
}

// ============================================================
// CLAVE SECRETA HMAC — ¡CAMBIAR EN PRODUCCIÓN!
// Generar con: php -r "echo bin2hex(random_bytes(32));"
// ============================================================
if (!defined('BOVS_FIRMA_SECRET')) {
    // ⚠️ CAMBIAR ESTA CLAVE ANTES DE PRODUCCIÓN
    define('BOVS_FIRMA_SECRET', 'ruted_bovs_2026_firma_segura_a7f3e9c1d4b8e2f5a6c9d0e1f2a3b4c5');
}

/**
 * Genera el token firmado de un animal.
 * Formato: "{id}.{hash_hex_20}"
 * Ejemplo: "3265.a3f9b2c8d1e6f4a2b5c7"
 */
function firma_animal($id_animal) {
    $id   = (int)$id_animal;
    $hash = substr(hash_hmac('sha256', 'animal|' . $id, BOVS_FIRMA_SECRET), 0, 20);
    return $id . '.' . $hash;
}

/**
 * Valida un token y devuelve el id del animal si es correcto, o false.
 */
function firma_verificar($token) {
    if (!is_string($token) || $token === '') return false;
    if (!preg_match('/^(\d+)\.([a-f0-9]{20})$/', $token, $m)) return false;

    $id       = (int)$m[1];
    $hash     = $m[2];
    $esperado = substr(hash_hmac('sha256', 'animal|' . $id, BOVS_FIRMA_SECRET), 0, 20);

    return hash_equals($esperado, $hash) ? $id : false;
}

/**
 * Devuelve la URL pública completa para verificar un animal.
 * Usa la URL de producción configurada arriba.
 */
function firma_url($id_animal) {
    return rtrim(BOVS_URL_PUBLICA, '/') . '/verificar.php?t=' . firma_animal($id_animal);
}