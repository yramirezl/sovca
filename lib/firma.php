<?php
/**
 * Sistema de firma digital HMAC-SHA256 para verificación pública.
 * Genera tokens únicos por animal que permiten autenticar la ficha
 * sin necesidad de login.
 */

// ============================================================
// URL PÚBLICA — se detecta sola
// ------------------------------------------------------------
// Antes estaba escrita a mano, lo que rompía la verificación en cualquier
// otra instalación: el QR abría la ficha del sistema equivocado y el
// navegador mostraba "el animal ya no está activo en la plataforma".
//
// Ahora se calcula con la carpeta REAL del proyecto:
//   · en web:  esquema + host + carpeta respecto de DOCUMENT_ROOT
//   · en CLI:  https://global.ruted.net/<carpeta del proyecto>
// Si se necesita forzar otra (dominio distinto), definir la constante
// SOVCA_URL_PUBLICA ANTES de incluir este archivo.
// ============================================================
// ============================================================
// CONFIGURACIÓN LOCAL DEL SERVIDOR (no versionada)
// ------------------------------------------------------------
// Si existe lib/firma.local.php se carga PRIMERO: ahí el servidor define
// SOVCA_FIRMA_SECRET y/o SOVCA_URL_PUBLICA. Hay una plantilla en
// lib/firma.local.example.php. Así la clave no vive en un archivo que git
// reescribe en cada despliegue (ni depende del pool de PHP-FPM).
// ============================================================
$sovca_firma_local = __DIR__ . '/firma.local.php';
if (is_file($sovca_firma_local)) {
    require_once $sovca_firma_local;
}

if (!defined('SOVCA_URL_PUBLICA')) {
    /* La variable de entorno manda: permite fijar el dominio de un servidor
       concreto sin editar este archivo (que está versionado y se reescribe en
       cada despliegue). */
    $sovca_url_env = getenv('SOVCA_URL_PUBLICA');
    if (($sovca_url_env === false || $sovca_url_env === '') && isset($_SERVER['SOVCA_URL_PUBLICA'])) {
        $sovca_url_env = $_SERVER['SOVCA_URL_PUBLICA'];
    }

    if ($sovca_url_env !== false && $sovca_url_env !== '') {
        $sovca_url_publica = $sovca_url_env;
    } else {
        $sovca_raiz_proyecto = dirname(__DIR__);
        $sovca_carpeta       = basename($sovca_raiz_proyecto);
        $sovca_url_publica   = 'https://global.ruted.net/' . $sovca_carpeta;

        if (!empty($_SERVER['HTTP_HOST'])) {
            $sovca_docroot = isset($_SERVER['DOCUMENT_ROOT'])
                ? rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/')
                : '';
            $sovca_raiz_web = rtrim(str_replace('\\', '/', $sovca_raiz_proyecto), '/');

            $sovca_ruta_web = '';
            if ($sovca_docroot !== '' && strpos($sovca_raiz_web, $sovca_docroot) === 0) {
                $sovca_ruta_web = substr($sovca_raiz_web, strlen($sovca_docroot));
            }
            $sovca_ruta_web = '/' . trim($sovca_ruta_web, '/');

            $sovca_esquema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $sovca_url_publica = $sovca_esquema . '://' . $_SERVER['HTTP_HOST'] . $sovca_ruta_web;
        }
    }

    define('SOVCA_URL_PUBLICA', rtrim($sovca_url_publica, '/'));
}

// ============================================================
// CLAVE SECRETA HMAC
// ------------------------------------------------------------
// En cada servidor va en la variable de entorno SOVCA_FIRMA_SECRET
// (env[SOVCA_FIRMA_SECRET] en el pool de PHP-FPM). Así el archivo queda
// idéntico al del repositorio y un despliegue no puede cambiar la clave,
// que invalidaría todos los QR ya impresos.
// Generar otra con: php -r "echo bin2hex(random_bytes(32));"
// ============================================================
if (!defined('SOVCA_FIRMA_SECRET')) {
    $sovca_firma_env = getenv('SOVCA_FIRMA_SECRET');
    if (($sovca_firma_env === false || $sovca_firma_env === '') && isset($_SERVER['SOVCA_FIRMA_SECRET'])) {
        $sovca_firma_env = $_SERVER['SOVCA_FIRMA_SECRET'];
    }

    define(
        'SOVCA_FIRMA_SECRET',
        ($sovca_firma_env !== false && $sovca_firma_env !== '')
            ? $sovca_firma_env
            : 'ruted_sovca_2026_firma_segura_85fd75c0d0446401d0566b59ad55f19aaea06a794b1adb18f53243d17ba46e55'
    );
}

/**
 * Genera el token firmado de un animal.
 * Formato: "{id}.{hash_hex_20}"
 * Ejemplo: "3265.a3f9b2c8d1e6f4a2b5c7"
 */
function firma_animal($id_animal) {
    $id   = (int)$id_animal;
    $hash = substr(hash_hmac('sha256', 'animal|' . $id, SOVCA_FIRMA_SECRET), 0, 20);
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
    $esperado = substr(hash_hmac('sha256', 'animal|' . $id, SOVCA_FIRMA_SECRET), 0, 20);

    if (hash_equals($esperado, $hash)) {
        return $id;
    }

    /* Compatibilidad temporal con QR ya impresos: solo se activa si el
       despliegue define SOVCA_FIRMA_SECRET_ANTERIOR en el entorno. */
    $anterior = getenv('SOVCA_FIRMA_SECRET_ANTERIOR');
    if ($anterior !== false && $anterior !== '') {
        $esperado_anterior = substr(hash_hmac('sha256', 'animal|' . $id, $anterior), 0, 20);
        if (hash_equals($esperado_anterior, $hash)) {
            return $id;
        }
    }

    return false;
}

/**
 * Devuelve la URL pública completa para verificar un animal.
 * Usa la URL de producción configurada arriba.
 */
function firma_url($id_animal) {
    return rtrim(SOVCA_URL_PUBLICA, '/') . '/verificar.php?t=' . firma_animal($id_animal);
}