<?php
/**
 * =====================================================================
 * MANIFEST DE LA APLICACIÓN (PWA) — SOVCA
 * ---------------------------------------------------------------------
 * Permite que el navegador ofrezca "Instalar aplicación" / "Añadir a
 * pantalla de inicio" al abrir el login desde un móvil, y que a partir
 * de ahí el usuario abra la app desde un ícono en lugar de una URL.
 *
 * Es DINÁMICO a propósito: calcula su carpeta base desde la URL, así el
 * mismo archivo sirve en /sovca o en un entorno de pruebas sin tocar
 * rutas a mano.
 * =====================================================================
 */

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

/* Carpeta base de la aplicación tal como la ve el navegador:
   '/bovs' cuando el archivo se sirve como /bovs/manifest.php. */
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
if ($base === '.' || $base === '/') {
    $base = '';
}

$appName  = 'SOVCA | Gestión Ovina y Caprina';
$appShort = 'SOVCA';
$appDesc  = 'Gestión de ovinos y caprinos: animales, servicios, nacimientos y reportes.';

$iconos = array(
    array(
        'src'     => $base . '/app_offline/icons/icon-192.png',
        'sizes'   => '192x192',
        'type'    => 'image/png',
        'purpose' => 'any',
    ),
    array(
        'src'     => $base . '/app_offline/icons/icon-512.png',
        'sizes'   => '512x512',
        'type'    => 'image/png',
        'purpose' => 'any',
    ),
    /* Los "maskable" llevan el dibujo más pequeño y el fondo a sangre,
       para que el recorte circular de Android no corte la cabeza. */
    array(
        'src'     => $base . '/app_offline/icons/icon-maskable-192.png',
        'sizes'   => '192x192',
        'type'    => 'image/png',
        'purpose' => 'maskable',
    ),
    array(
        'src'     => $base . '/app_offline/icons/icon-maskable-512.png',
        'sizes'   => '512x512',
        'type'    => 'image/png',
        'purpose' => 'maskable',
    ),
);

$manifest = array(
    'id'               => $base . '/',
    'name'             => $appName,
    'short_name'       => $appShort,
    'description'      => $appDesc,
    'lang'             => 'es',
    'dir'              => 'ltr',
    'start_url'        => $base . '/views/login',
    'scope'            => $base . '/',
    'display'          => 'standalone',
    'display_override' => array('standalone', 'minimal-ui'),
    'orientation'      => 'any',
    'background_color' => '#F1ECDD',
    'theme_color'      => '#006532',
    'categories'       => array('business', 'productivity'),
    'prefer_related_applications' => false,
    'icons'            => $iconos,
);

echo json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
