<?php

/**
 * =====================================================================
 * CAPTCHA NUEVO SIN RECARGAR EL FORMULARIO (AJAX)
 * ---------------------------------------------------------------------
 * Lo llama views/login.php cuando el usuario pulsa el botón de recargar
 * o cuando el captcha de la página lleva demasiado tiempo generado.
 *
 * Reemplaza el captcha de la sesión y devuelve la nueva operación:
 *   {"ok":1,"a":7,"b":3,"signo":"+","token":"…"}
 *
 * No requiere sesión iniciada (es parte del formulario de acceso) y no
 * expone nada más que la operación que debe resolver el usuario.
 * =====================================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../lib/captcha_helper.php';

/* El modal de instalación offline pide su propio captcha para no
   invalidar el del formulario de acceso. Solo se aceptan espacios
   conocidos. */
$slot = isset($_GET['slot']) ? (string) $_GET['slot'] : 'captcha';
if (!in_array($slot, array('captcha', 'captcha_offline'), true)) {
    $slot = 'captcha';
}

$cap = bovs_captcha_generar($slot);

echo json_encode(array(
    'ok'    => 1,
    'slot'  => $slot,
    'a'     => $cap['a'],
    'b'     => $cap['b'],
    'signo' => $cap['signo'],
    'token' => $cap['token'],
));
