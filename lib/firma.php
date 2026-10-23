<?php
/**
 * Sistema de firma digital HMAC-SHA256 para verificación pública.
 * Genera tokens únicos por animal que permiten autenticar la ficha
 * sin necesidad de login.
 */

// ============================================================
// URL PÚBLICA DE PRODUCCIÓN
// ============================================================
if (!defined('BOVS_URL_PUBLICA')) {
    define('BOVS_URL_PUBLICA', 'https://global.ruted.net/bovs');
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