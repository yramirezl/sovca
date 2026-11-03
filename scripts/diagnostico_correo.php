<?php
/**
 * =============================================================================
 * SOVCA · Diagnóstico del correo saliente
 * -----------------------------------------------------------------------------
 * Separa un fallo de CONFIGURACIÓN de un BLOQUEO del proveedor de correo.
 *
 * Uso (desde cualquier carpeta):
 *     php /var/www/html/sovca/scripts/diagnostico_correo.php
 *     php /var/www/html/sovca/scripts/diagnostico_correo.php --to=otro@dominio.com
 *     php /var/www/html/sovca/scripts/diagnostico_correo.php --no-enviar
 *     php /var/www/html/sovca/scripts/diagnostico_correo.php --verbose
 *
 * Por defecto envía UN correo de prueba a la cuenta de respaldo, nunca a los
 * usuarios del sistema.
 *
 * Detecta en particular el caso real del 29/10/2026: el proveedor responde
 * "550 Message rejected" ANTES del saludo (o corta el TLS) porque no acepta
 * la IP del servidor, mientras que desde otra IP el mismo buzón funciona.
 * =============================================================================
 */

require_once __DIR__ . '/../alertas/class.phpmailer.php';
require_once __DIR__ . '/../alertas/class.smtp.php';
require_once __DIR__ . '/../lib/mailer_helper.php';

$opciones = array(
    'to'      => 'backupglobalsovcabovs@gmail.com',
    'enviar'  => true,
    'verbose' => false,
);
foreach (array_slice(isset($argv) ? $argv : array(), 1) as $arg) {
    if (strpos($arg, '--to=') === 0) {
        $opciones['to'] = substr($arg, 5);
    } elseif ($arg === '--no-enviar') {
        $opciones['enviar'] = false;
    } elseif ($arg === '--verbose') {
        $opciones['verbose'] = true;
    }
}

$linea  = str_repeat('=', 70);
$cfg    = bovs_mail_config();
$dominio = (string) $cfg['message_id_domain'];
$fallos = 0;
$avisos = 0;
$hallazgos = array();   // diagnósticos concretos para el veredicto final

echo $linea . PHP_EOL;
echo 'SOVCA · diagnostico de correo · ' . date('Y-m-d H:i:s') . PHP_EOL;
echo $linea . PHP_EOL;

/* ---------------------------------------------------------------- 1. Entorno */
echo PHP_EOL . '[1] Entorno' . PHP_EOL;
echo '  PHP            : ' . PHP_VERSION . ' (' . PHP_SAPI . ')' . PHP_EOL;
echo '  usuario        : ' . (function_exists('posix_getpwuid') && function_exists('posix_geteuid')
    ? posix_getpwuid(posix_geteuid())['name'] : get_current_user()) . PHP_EOL;

$ip_publica = '';
if (function_exists('curl_init')) {
    $ch = curl_init('https://api.ipify.org');
    curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 6));
    $ip_publica = trim((string) curl_exec($ch));
    curl_close($ch);
}
echo '  IP publica     : ' . ($ip_publica !== '' ? $ip_publica : '(no se pudo consultar)') . PHP_EOL;

/* ----------------------------------------------------------- 2. Configuración */
echo PHP_EOL . '[2] Configuracion SMTP efectiva' . PHP_EOL;
foreach (array('host', 'port', 'secure', 'username', 'from_email', 'sender_email', 'hostname', 'message_id_domain') as $k) {
    echo '  ' . str_pad($k, 18) . ': ' . var_export($cfg[$k], true) . PHP_EOL;
}
echo '  ' . str_pad('password', 18) . ': '
    . ($cfg['password'] === '' ? '*** VACIA ***' : '(definida, ' . strlen($cfg['password']) . ' caracteres)') . PHP_EOL;
echo '  ' . str_pad('origen clave', 18) . ': '
    . ((getenv('SOVCA_SMTP_PASS') !== false && getenv('SOVCA_SMTP_PASS') !== '') ? 'variable SOVCA_SMTP_PASS' : 'config/mail.php') . PHP_EOL;
if ($cfg['password'] === '') {
    $fallos++;
    $hallazgos[] = 'La contrasena SMTP esta vacia: revisa config/mail.php o SOVCA_SMTP_PASS.';
}

/* ----------------------------------------------------------------- 3. Red */
echo PHP_EOL . '[3] Resolucion DNS y puertos de ' . $cfg['host'] . PHP_EOL;
$ips = gethostbynamel($cfg['host']);
if (!$ips) {
    echo '  DNS            : FALLO (no resuelve)' . PHP_EOL;
    $fallos++;
    $hallazgos[] = 'No se resuelve ' . $cfg['host'] . ': problema de DNS del servidor.';
} else {
    echo '  DNS            : ' . implode(', ', $ips) . PHP_EOL;
}

$rechazo_en_saludo = false;
foreach (array_unique(array(25, 465, 587, (int) $cfg['port'])) as $puerto) {
    $inicio = microtime(true);
    $con = @fsockopen($cfg['host'], $puerto, $errno, $errstr, 6);
    $ms = round((microtime(true) - $inicio) * 1000);
    if (!$con) {
        echo '  puerto ' . str_pad($puerto, 4) . '   : CERRADO/BLOQUEADO (' . $ms . ' ms) ' . $errstr . PHP_EOL;
        $fallos++;
        continue;
    }

    /* En los puertos en claro se puede leer el saludo SMTP: es la prueba
       directa de si el proveedor acepta o rechaza a este servidor. */
    $saludo = '';
    if (in_array($puerto, array(25, 587), true)) {
        stream_set_timeout($con, 4);
        $primera = trim((string) fgets($con, 512));
        $saludo = $primera;
        if (preg_match('/^(5\d\d)\b/', $primera, $m)) {
            $saludo .= '   <-- RECHAZO ' . $m[1] . ' antes del saludo';
            $rechazo_en_saludo = true;
        }
    }
    fclose($con);
    echo '  puerto ' . str_pad($puerto, 4) . '   : ABIERTO (' . $ms . ' ms)'
        . ($saludo !== '' ? ' · ' . $saludo : '') . PHP_EOL;
}
if ($rechazo_en_saludo) {
    $avisos++;
    $hallazgos[] = 'El proveedor de correo rechaza esta IP antes de saludar (5xx en el saludo de los '
        . 'puertos 25/587): es un BLOQUEO POR IP, no un problema de contrasena ni de configuracion.';
}

/* --------------------------------------------------------------- 5. TLS/AUTH */
echo PHP_EOL . '[5] Handshake TLS en el puerto configurado' . PHP_EOL;
$ctx = stream_context_create(array('ssl' => array('verify_peer' => true, 'verify_peer_name' => true)));
$sock = @stream_socket_client('ssl://' . $cfg['host'] . ':' . $cfg['port'], $errno, $errstr, 8, STREAM_CLIENT_CONNECT, $ctx);
if ($sock) {
    $meta = stream_get_meta_data($sock);
    echo '  TLS            : OK' . (isset($meta['crypto']['protocol']) ? ' · ' . $meta['crypto']['protocol'] : '') . PHP_EOL;
    fclose($sock);
} else {
    /* errno 0 con mensaje vacio = el servidor cortó la conexión durante el
       handshake (patrón de bloqueo por IP). Si falla solo con verificación,
       es un problema de certificado. */
    $ctx2 = stream_context_create(array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false)));
    $sock2 = @stream_socket_client('ssl://' . $cfg['host'] . ':' . $cfg['port'], $errno2, $errstr2, 8, STREAM_CLIENT_CONNECT, $ctx2);
    if ($sock2) {
        echo '  TLS            : solo falla la verificacion del certificado · ' . $errstr . PHP_EOL;
        $avisos++;
        $hallazgos[] = 'El certificado de ' . $cfg['host'] . ' no valida desde este servidor (revisa la CA).';
        fclose($sock2);
    } else {
        echo '  TLS            : FALLO · ' . ($errstr !== '' ? $errstr : 'conexion cortada por el servidor')
            . ' (' . $errno . ')' . PHP_EOL;
        $fallos++;
        $hallazgos[] = 'La conexion TLS con ' . $cfg['host'] . ':' . $cfg['port']
            . ' se corta durante el handshake. Junto al rechazo del saludo, apunta a bloqueo por IP.';
    }
}

/* ------------------------------------------------------- 6. PTR y SPF del dominio */
echo PHP_EOL . '[6] Reputacion de la IP y autenticacion del dominio' . PHP_EOL;
if ($ip_publica !== '') {
    $ptr = gethostbyaddr($ip_publica);
    $ptr_ok = ($ptr && $ptr !== $ip_publica && stripos($ptr, $dominio) !== false);
    echo '  PTR de la IP   : ' . ($ptr ?: '(sin PTR)') . ($ptr_ok ? '   [coincide con ' . $dominio . ']' : '   <-- no coincide con ' . $dominio) . PHP_EOL;
    if (!$ptr_ok) {
        $avisos++;
        $hallazgos[] = 'El PTR de ' . $ip_publica . ' no es del dominio ' . $dominio
            . '. Muchos filtros lo exigen: se cambia en el panel del proveedor del servidor (OVH).';
    }

    $txt = array();
    foreach ((array) @dns_get_record($dominio, DNS_TXT) as $r) {
        if (isset($r['txt']) && stripos($r['txt'], 'v=spf1') !== false) {
            $txt[] = $r['txt'];
        }
    }
    if (!$txt) {
        echo '  SPF            : (sin registro SPF en ' . $dominio . ')' . PHP_EOL;
        $avisos++;
        $hallazgos[] = 'El dominio ' . $dominio . ' no publica SPF.';
    } else {
        $spf = $txt[0];
        $autoriza_ip = (stripos($spf, 'ip4:' . $ip_publica) !== false)
            || (stripos($spf, 'ip4:' . $ip_publica . '/') !== false);
        echo '  SPF            : ' . $spf . PHP_EOL;
        echo '  autoriza IP    : ' . ($autoriza_ip ? 'SI' : 'NO   <-- los correos de este servidor fallaran SPF') . PHP_EOL;
        if (!$autoriza_ip) {
            $avisos++;
            $hallazgos[] = 'El SPF de ' . $dominio . ' no autoriza ' . $ip_publica
                . '. Hay que agregar  ip4:' . $ip_publica . '  al registro TXT (panel de DNS de ' . $dominio . ').';
        }
    }
} else {
    echo '  (sin IP publica no se puede revisar PTR ni SPF)' . PHP_EOL;
}

/* HELO: el nombre que el servidor declara al saludar debe resolver a su propia
   IP. Si no, muchos filtros rechazan la conexión (fue la causa del 550 de
   mail.ruted.net el 29/10/2026: PTR vps-...ovh.net genérico). */
$helo = (string) $cfg['hostname'];
$helo_ips = @gethostbynamel($helo);
echo PHP_EOL . '[6b] Nombre del saludo (HELO)' . PHP_EOL;
echo '  HELO           : ' . $helo . PHP_EOL;
if (!$helo_ips) {
    echo '  resuelve a     : NADA   <-- el HELO no tiene registro A' . PHP_EOL;
    $avisos++;
    $hallazgos[] = 'SOVCA_SMTP_HELO (' . $helo . ') no resuelve. Crea el registro A '
        . $helo . ' → ' . ($ip_publica !== '' ? $ip_publica : 'la IP del servidor') . ' en el DNS de ' . $dominio . '.';
} else {
    $coincide = ($ip_publica !== '' && in_array($ip_publica, $helo_ips, true));
    echo '  resuelve a     : ' . implode(', ', $helo_ips)
        . ($coincide ? '   [coincide con la IP del servidor]' : '   <-- no coincide con ' . ($ip_publica ?: 'la IP del servidor')) . PHP_EOL;
    if (!$coincide) {
        $avisos++;
        $hallazgos[] = 'El registro A de ' . $helo . ' no apunta a la IP del servidor: '
            . 'el HELO no coincidirá con su PTR y los filtros pueden rechazarlo.';
    }
}

/* ------------------------------------------------------------------ 7. Envio */
if ($opciones['enviar']) {
    echo PHP_EOL . '[7] Envio de prueba a ' . $opciones['to'] . PHP_EOL;
    $mail = new PHPMailer();
    bovs_mail_configurar_phpmailer($mail);
    $mail->Timeout = 20;
    if ($opciones['verbose']) {
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = 'echo';
    }
    $mail->Subject = 'Diagnostico de correo SOVCA ' . date('Y-m-d H:i');
    $mail->MsgHTML('<p>Correo de diagnostico enviado desde ' . htmlspecialchars($ip_publica !== '' ? $ip_publica : 'el servidor') . '.</p>');
    $mail->AltBody = 'Correo de diagnostico SOVCA.';

    $mail->AddAddress($opciones['to']);
    $ok = $mail->Send();
    if ($ok) {
        echo '  resultado      : ENVIADO' . PHP_EOL;
    } else {
        echo '  resultado      : FALLO' . PHP_EOL;
        echo '  detalle        : ' . $mail->ErrorInfo . PHP_EOL;
        $fallos++;
    }
} else {
    echo PHP_EOL . '[7] Envio de prueba: omitido (--no-enviar)' . PHP_EOL;
}

/* ---------------------------------------------------------------- 8. Veredicto */
echo PHP_EOL . $linea . PHP_EOL;
if ($fallos === 0 && $avisos === 0) {
    echo 'VEREDICTO: configuracion y entrega correctas.' . PHP_EOL;
} elseif ($fallos === 0) {
    echo 'VEREDICTO: el correo sale, pero hay ' . $avisos . ' punto(s) a corregir para no caer en spam.' . PHP_EOL;
} else {
    echo 'VEREDICTO: ' . $fallos . ' fallo(s), ' . $avisos . ' aviso(s).' . PHP_EOL;
}

if ($hallazgos) {
    echo PHP_EOL . 'Hallazgos:' . PHP_EOL;
    foreach ($hallazgos as $i => $h) {
        echo '  ' . ($i + 1) . '. ' . $h . PHP_EOL;
    }
}

if ($fallos > 0) {
    echo PHP_EOL . 'Si el envio falla con 5xx antes del saludo, el proveedor esta' . PHP_EOL;
    echo 'rechazando la IP del servidor: revisa el PTR y el SPF de la seccion [6].' . PHP_EOL;
}
echo $linea . PHP_EOL;

exit(($fallos === 0 && $avisos === 0) ? 0 : 1);
