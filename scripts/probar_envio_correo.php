<?php
/**
 * =============================================================================
 * SOVCA · Prueba de envío de correo SIN enviar el mensaje
 * -----------------------------------------------------------------------------
 * Comprueba si el servidor donde se ejecuta YA puede enviar correo, sin generar
 * ningún mensaje para los destinatarios.
 *
 * Cómo lo hace: habla SMTP de verdad hasta el paso anterior al contenido.
 *
 *     conexión TLS → EHLO → AUTH → MAIL FROM → RCPT TO → RSET → QUIT
 *                                                      ▲
 *                                          aquí se corta: DATA nunca se envía
 *
 * Si RCPT TO es aceptado, el proveedor ya no está rechazando esta IP: el envío
 * real funcionará. No se entrega ningún correo, ni siquiera a la dirección de
 * control.
 *
 * Uso:
 *     php scripts/probar_envio_correo.php
 *     php scripts/probar_envio_correo.php --to=otro@dominio.com
 *     php scripts/probar_envio_correo.php --json      (salida para monitoreo)
 *
 * Código de salida: 0 = puede enviar · 1 = bloqueado o mal configurado.
 * =============================================================================
 */

require_once __DIR__ . '/../alertas/class.phpmailer.php';
require_once __DIR__ . '/../alertas/class.smtp.php';
require_once __DIR__ . '/../lib/mailer_helper.php';

$opciones = array(
    // Dirección de control: solo se usa en RCPT TO, no recibe nada.
    'to'   => 'backupglobalsovcabovs@gmail.com',
    'json' => false,
);
foreach (array_slice(isset($argv) ? $argv : array(), 1) as $arg) {
    if (strpos($arg, '--to=') === 0) {
        $opciones['to'] = substr($arg, 5);
    } elseif ($arg === '--json') {
        $opciones['json'] = true;
    }
}

$cfg    = bovs_mail_config();
$pasos  = array();      // [ [nombre, ok, detalle], ... ]
$fallo  = '';

$anotar = function ($nombre, $ok, $detalle = '') use (&$pasos) {
    $pasos[] = array('paso' => $nombre, 'ok' => (bool) $ok, 'detalle' => $detalle);
};

/* ------------------------------------------------------------------ 1. Datos */
$host_conexion = ($cfg['secure'] === 'ssl' ? 'ssl://' : '') . $cfg['host'];
$puerto        = (int) $cfg['port'];

if ($cfg['password'] === '') {
    $fallo = 'contrasena SMTP vacia (config/mail.php o SOVCA_SMTP_PASS)';
    $anotar('configuracion', false, $fallo);
}

/* ------------------------------------------------------------- 2. Conversación */
$smtp = null;
if ($fallo === '') {
    $smtp = new SMTP();
    $smtp->do_debug = 0;

    if (!$smtp->Connect($host_conexion, $puerto, 15)) {
        $err = is_array($smtp->error) ? $smtp->error : array();
        $fallo = 'no conecta con ' . $host_conexion . ':' . $puerto
               . (isset($err['errstr']) && $err['errstr'] !== '' ? ' (' . $err['errstr'] . ')' : '');
        $anotar('conexion', false, $fallo);
    } else {
        $anotar('conexion', true, $host_conexion . ':' . $puerto);
    }
}

if ($fallo === '' && !$smtp->Hello($cfg['hostname'])) {
    $fallo = 'el servidor no acepta EHLO ' . $cfg['hostname'] . ' · ' . $smtp->getError();
    $anotar('ehlo', false, $fallo);
} elseif ($fallo === '') {
    $anotar('ehlo', true, $cfg['hostname']);
}

if ($fallo === '' && !$smtp->Authenticate($cfg['username'], $cfg['password'])) {
    $fallo = 'autenticacion rechazada · ' . $smtp->getError();
    $anotar('auth', false, $fallo);
} elseif ($fallo === '') {
    $anotar('auth', true, $cfg['username']);
}

if ($fallo === '' && !$smtp->Mail($cfg['from_email'])) {
    $fallo = 'MAIL FROM rechazado · ' . $smtp->getError();
    $anotar('mail_from', false, $fallo);
} elseif ($fallo === '') {
    $anotar('mail_from', true, $cfg['from_email']);
}

if ($fallo === '' && !$smtp->Recipient($opciones['to'])) {
    $fallo = 'RCPT TO rechazado (aqui es donde aparece el bloqueo por IP) · ' . $smtp->getError();
    $anotar('rcpt_to', false, $fallo);
} elseif ($fallo === '') {
    $anotar('rcpt_to', true, $opciones['to']);
}

/* Cierre limpio. NUNCA se llama a Data(): no sale ningún mensaje. */
if ($smtp !== null && $smtp->Connected()) {
    $smtp->Reset();
    $smtp->Quit();
    $anotar('cierre', true, 'RSET + QUIT sin enviar DATA');
}

$puede_enviar = ($fallo === '');

/* ------------------------------------------------------------------ 3. Salida */
if ($opciones['json']) {
    echo json_encode(array(
        'puede_enviar' => $puede_enviar,
        'fallo'        => $fallo,
        'servidor'     => $cfg['host'] . ':' . $cfg['port'] . '/' . $cfg['secure'],
        'remitente'    => $cfg['from_email'],
        'control'      => $opciones['to'],
        'pasos'        => $pasos,
        'fecha'        => date('c'),
    )), PHP_EOL;
} else {
    $linea = str_repeat('=', 68);
    echo $linea . PHP_EOL;
    echo 'SOVCA · prueba de envio SIN enviar mensaje · ' . date('Y-m-d H:i:s') . PHP_EOL;
    echo 'servidor: ' . $cfg['host'] . ':' . $cfg['port'] . '/' . $cfg['secure']
        . ' · remitente: ' . $cfg['from_email'] . PHP_EOL;
    echo $linea . PHP_EOL;
    foreach ($pasos as $p) {
        printf("  %-12s %-3s %s\n", $p['paso'], ($p['ok'] ? 'OK' : 'NO'), $p['detalle']);
    }
    echo $linea . PHP_EOL;
    if ($puede_enviar) {
        echo 'RESULTADO: el servidor YA puede enviar correo.' . PHP_EOL;
        echo '           (no se ha enviado ningun mensaje)' . PHP_EOL;
    } else {
        echo 'RESULTADO: el servidor NO puede enviar correo.' . PHP_EOL;
        echo '           motivo: ' . $fallo . PHP_EOL;
    }
    echo $linea . PHP_EOL;
}

exit($puede_enviar ? 0 : 1);
