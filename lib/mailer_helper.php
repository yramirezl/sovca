<?php
/**
 * lib/mailer_helper.php
 * ---------------------------------------------------------------------------
 * Envío de correos de SOVCA con el PHPMailer que ya trae el proyecto
 * (alertas/class.phpmailer.php · SMTP mail.ruted.net) y una plantilla HTML
 * con la identidad visual del sistema (verde ganadero).
 *
 * Funciones:
 *   bovs_mail_config()          Configuración (config/mail.php + variables de entorno)
 *   bovs_url_base()             Dirección pública del sistema (para armar enlaces)
 *   bovs_mail_codigo()          Código aleatorio de 6 dígitos
 *   bovs_mail_codigo_hash()     Hash SHA-256 del código (lo que se guarda en la BD)
 *   bovs_mail_plantilla()       Envoltura HTML del correo
 *   bovs_mail_enviar()          Envía (o guarda en archivo en modo de prueba)
 *   bovs_mail_enviar_codigo()   Correo con el código de recuperación de contraseña
 *
 * Nunca lanza excepciones: siempre devuelve array('ok' => bool, 'error' => string).
 */

if (!function_exists('bovs_mail_config')) {
    /**
     * @return array
     */
    function bovs_mail_config()
    {
        static $cfg = null;
        if ($cfg !== null) {
            return $cfg;
        }

        $base = array(
            'host'               => 'mail.ruted.net',
            'port'               => 465,
            'secure'             => 'ssl',
            'username'           => 'global@ruted.net',
            'password'           => '',
            'from_email'         => 'global@ruted.net',
            'from_name'          => 'SOVCA · Gestión Ovina y Caprina',
            'sender_email'       => 'global@ruted.net',
            'reply_to'           => 'global@ruted.net',
            'reply_to_name'      => 'SOVCA · Soporte',
            'hostname'           => 'mail.ruted.net',
            'message_id_domain'  => 'ruted.net',
            'list_unsubscribe'   => 'mailto:global@ruted.net?subject=Baja%20SOVCA',
            'public_url'         => 'https://global.ruted.net/sovca',
            'dkim_domain'        => '',
            'dkim_selector'      => 'default',
            'dkim_private'       => '',
            'base_url'           => '',
            'debug'              => false,
            'guardar_en_archivo' => false,
            'archivo_dir'        => '',
            'reset_vigencia_min' => 15,
            'reset_max_intentos' => 5,
        );

        $archivo = __DIR__ . '/../config/mail.php';
        if (is_file($archivo)) {
            $propia = include $archivo;
            if (is_array($propia)) {
                $base = array_merge($base, $propia);
            }
        }

        // Variables de entorno (útiles en el servidor y en pruebas)
        $pass = getenv('SOVCA_SMTP_PASS');
        if ($pass !== false && $pass !== '') {
            $base['password'] = $pass;
        }
        if (getenv('SOVCA_MAIL_ARCHIVO') === '1') {
            $base['guardar_en_archivo'] = true;
        }

        $cfg = $base;
        return $cfg;
    }
}

if (!function_exists('bovs_url_base')) {
    /**
     * Dirección pública del sistema SIN barra final.
     * Usa config/mail.php['base_url'] y, si está vacía, la deduce del navegador.
     *
     * @return string
     */
    function bovs_url_base()
    {
        $cfg = bovs_mail_config();

        // public_url manda: es la dirección que se escribe dentro del correo.
        if (!empty($cfg['public_url'])) {
            return rtrim($cfg['public_url'], '/');
        }
        if (!empty($cfg['base_url'])) {
            return rtrim($cfg['base_url'], '/');
        }

        $host  = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
        $proto = 'http';
        if ((!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
        ) {
            $proto = 'https';
        }

        $dir = '';
        if (!empty($_SERVER['SCRIPT_NAME'])) {
            $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
            // Quitar cualquier subcarpeta interna desde la que se ejecute el
            // script (controllers/, views/<modulo>/, api/, alertas/, ...)
            $dir = preg_replace('#/(controllers|views|api|alertas|data_json|scripts|lib)(/.*)?$#', '', $dir);
            if ($dir === '.' || $dir === '/') {
                $dir = '';
            }
        }

        // Un enlace a localhost/127.0.0.1 dentro de un correo dispara los
        // filtros de spam. Si estamos en local y no hay public_url, no se
        // inventa una URL local: se devuelve la ruta vacía (el correo se
        // envía igual, sin enlace roto).
        $hostSinPuerto = strtolower(preg_replace('/:\d+$/', '', $host));
        if (in_array($hostSinPuerto, array('localhost', '127.0.0.1', '::1'), true)) {
            return '';
        }

        return $proto . '://' . $host . $dir;
    }
}

if (!function_exists('bovs_mail_codigo')) {
    /**
     * Código de recuperación de 6 dígitos (100000 - 999999).
     * @return string
     */
    function bovs_mail_codigo()
    {
        return (string) random_int(100000, 999999);
    }
}

if (!function_exists('bovs_mail_codigo_hash')) {
    /**
     * En la base de datos se guarda el HASH del código, nunca el código.
     * @param string $codigo
     * @return string 64 caracteres hex
     */
    function bovs_mail_codigo_hash($codigo)
    {
        return hash('sha256', (string) $codigo);
    }
}

if (!function_exists('bovs_mail_plantilla')) {
    /**
     * Envoltura HTML común de los correos (identidad SOVCA).
     *
     * @param string $titulo
     * @param string $contenidoHtml
     * @return string
     */
    function bovs_mail_plantilla($titulo, $contenidoHtml)
    {
        $titulo = htmlspecialchars((string) $titulo, ENT_QUOTES, 'UTF-8');
        $base   = htmlspecialchars(bovs_url_base(), ENT_QUOTES, 'UTF-8');
        $anio   = date('Y');

        // Sin URL pública no se escribe enlace (un enlace vacío o a localhost
        // es una seña típica de correo no deseado).
        $pieEnlace = '';
        if ($base !== '') {
            $pieEnlace = '<br><a href="' . $base . '" style="color:#6b7d70;text-decoration:none;">' . $base . '</a>';
        }

        return '<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>' . $titulo . '</title>
</head>
<body style="margin:0;padding:0;background:#eef3ec;font-family:\'Segoe UI\',Roboto,Arial,sans-serif;color:#1f2d24;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef3ec;padding:26px 12px;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 8px 24px rgba(35,78,53,0.12);">
          <tr>
            <td style="background:#234e35;padding:24px 30px;text-align:center;color:#ffffff;">
              <div style="font-size:19px;font-weight:800;letter-spacing:2px;text-transform:uppercase;">SOVCA</div>
              <div style="font-size:11.5px;opacity:.9;margin-top:5px;letter-spacing:1px;text-transform:uppercase;">Gestión Ovina y Caprina</div>
            </td>
          </tr>
          <tr>
            <td style="padding:30px 32px 22px;font-size:14.5px;line-height:1.6;color:#33413a;">
              ' . $contenidoHtml . '
              <hr style="border:none;border-top:1px solid #e6ece7;margin:22px 0;">
              <p style="font-size:12px;color:#8a998f;margin:0;">
                Si no reconoces esta acción, puedes ignorar este correo.
              </p>
            </td>
          </tr>
          <tr>
            <td style="background:#f4f8f4;padding:16px 30px;text-align:center;font-size:11.5px;color:#6b7d70;border-top:1px solid #e6ece7;">
              <span style="color:#2c6e46;font-weight:800;">Sistema SOVCA</span> · &copy; ' . $anio . ' Global-SOVCA' . $pieEnlace . '
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>';
    }
}

if (!function_exists('bovs_mail_cabeceras')) {
    /**
     * Cabeceras que ayudan a la entregabilidad y evitan que el correo se
     * trate como sospechoso:
     *   · Sender / Return-Path alineado con el dominio del From (SPF)
     *   · Hostname del dominio remitente (HELO y Message-ID)
     *   · Message-ID propio con dominio real
     *   · X-Mailer neutro (no la versión vieja de PHPMailer)
     *   · Auto-Submitted / X-Auto-Response-Suppress (correo automático)
     *   · List-Unsubscribe para que exista una vía de baja
     *
     * @param PHPMailer $mail
     * @param string    $tipo 'transaccional' | 'notificacion' | 'masivo'
     * @return PHPMailer
     */
    function bovs_mail_cabeceras($mail, $tipo = 'transaccional')
    {
        $cfg = bovs_mail_config();

        $dominio = !empty($cfg['message_id_domain'])
            ? (string) $cfg['message_id_domain']
            : preg_replace('/^.*@/', '', (string) $cfg['from_email']);

        if (!empty($cfg['sender_email'])) {
            $mail->Sender = (string) $cfg['sender_email'];
        }
        if (!empty($cfg['hostname'])) {
            $mail->Hostname = (string) $cfg['hostname'];
        }
        if (empty($mail->MessageID)) {
            $mail->MessageID = sprintf('<%s@%s>', bin2hex(random_bytes(16)), $dominio);
        }

        $mail->Priority = 3;
        $mail->CharSet  = 'UTF-8';

        // PHPMailer 5.1 recibe cada cabecera extra como una sola cadena
        // "Nombre: valor".
        $mail->AddCustomHeader('Auto-Submitted: auto-generated');
        $mail->AddCustomHeader('X-Auto-Response-Suppress: All');

        if ($tipo !== 'masivo' && !empty($cfg['list_unsubscribe'])) {
            $mail->AddCustomHeader('List-Unsubscribe: <' . $cfg['list_unsubscribe'] . '>');
            $mail->AddCustomHeader('List-Unsubscribe-Post: List-Unsubscribe=One-Click');
        }

        return $mail;
    }
}

if (!function_exists('bovs_mail_enviar')) {
    /**
     * Envía un correo HTML.
     *
     * @param string $para    Destinatario
     * @param string $asunto
     * @param string $html    Cuerpo HTML
     * @param string $texto   Cuerpo alternativo en texto plano (opcional)
     * @param array  $opciones tipo, reply_to, reply_to_name
     * @return array ('ok' => bool, 'error' => string)
     */
    function bovs_mail_enviar($para, $asunto, $html, $texto = '', $opciones = array())
    {
        $para  = trim((string) $para);
        $cfg   = bovs_mail_config();
        $tipo  = isset($opciones['tipo']) ? (string) $opciones['tipo'] : 'transaccional';
        $reply = !empty($opciones['reply_to']) ? (string) $opciones['reply_to'] : (string) $cfg['reply_to'];
        $replyNombre = !empty($opciones['reply_to_name'])
            ? (string) $opciones['reply_to_name']
            : (string) $cfg['reply_to_name'];

        if ($para === '' || !filter_var($para, FILTER_VALIDATE_EMAIL)) {
            return array('ok' => false, 'error' => 'Destinatario inválido');
        }

        /* ---------- Modo de prueba: guardar el correo en disco ---------- */
        if (!empty($cfg['guardar_en_archivo'])) {
            $nombre = date('Ymd_His') . '_' . preg_replace('/[^a-z0-9]+/i', '_', $para) . '.html';

            // Primero la carpeta configurada (config/mail.php → archivo_dir),
            // luego file/correo (junto al proyecto) y, si el usuario de Apache
            // no puede escribir en ninguna, la carpeta temporal del sistema.
            // Este modo es solo de pruebas: lo importante es poder leer el correo.
            $candidatas = array();
            if (!empty($cfg['archivo_dir'])) {
                $candidatas[] = rtrim((string) $cfg['archivo_dir'], '/\\');
            }
            $candidatas[] = __DIR__ . '/../file/correo';
            $candidatas[] = rtrim(sys_get_temp_dir(), '/\\') . '/bovs_correo';

            $guardado = false;
            $ruta     = '';

            foreach ($candidatas as $dir) {
                if (!is_dir($dir)) {
                    @mkdir($dir, 0775, true);
                }
                if (!is_dir($dir) || !is_writable($dir)) {
                    continue;
                }
                $ruta = $dir . '/' . $nombre;
                if (@file_put_contents($ruta, "<!-- Para: $para -->\n<!-- Asunto: $asunto -->\n" . $html) !== false) {
                    $guardado = true;
                    break;
                }
            }

            if (!$guardado) {
                error_log('[SOVCA mail] Modo archivo: no se pudo escribir el correo de ' . $para);
                return array('ok' => false, 'error' => 'No se pudo escribir el correo de prueba');
            }

            return array('ok' => true, 'error' => '', 'archivo' => $ruta);
        }

        /* ---------- Envío real por SMTP ---------- */
        if (trim((string) $cfg['password']) === '') {
            error_log('[SOVCA mail] SMTP sin contraseña configurada (config/mail.php o SOVCA_SMTP_PASS)');
            return array('ok' => false, 'error' => 'SMTP sin contraseña configurada');
        }

        require_once __DIR__ . '/../alertas/class.phpmailer.php';
        require_once __DIR__ . '/../alertas/class.smtp.php';

        $mail = new PHPMailer();
        try {
            $mail->IsSMTP();
            $mail->Host       = $cfg['host'];
            $mail->Port       = (int) $cfg['port'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $cfg['username'];
            $mail->Password   = $cfg['password'];
            $mail->SMTPSecure = ($cfg['secure'] === 'ssl') ? 'ssl' : 'tls';
            $mail->CharSet    = 'UTF-8';

            if (!empty($cfg['debug'])) {
                $mail->SMTPDebug = 2;
            }

            // $auto = 0: sin esto PHPMailer registra un Reply-To automático con
            // el nombre del remitente y luego AddReplyTo no lo reemplaza
            // (el array está indexado por dirección).
            $mail->SetFrom($cfg['from_email'], $cfg['from_name'], 0);
            if (!empty($reply)) {
                $mail->AddReplyTo($reply, $replyNombre);
            }
            $mail->AddAddress($para);

            // SPF, Message-ID, HELO, X-Mailer y vía de baja
            bovs_mail_cabeceras($mail, $tipo);

            // Firma DKIM si se dispone de la clave privada del dominio.
            // Sin DKIM y sin DMARC, Gmail y Outlook tienden a mandar a spam
            // los correos con código de verificación.
            if (!empty($cfg['dkim_domain']) && !empty($cfg['dkim_private']) && is_file($cfg['dkim_private'])) {
                $mail->DKIM_domain   = (string) $cfg['dkim_domain'];
                $mail->DKIM_selector = !empty($cfg['dkim_selector']) ? (string) $cfg['dkim_selector'] : 'default';
                $mail->DKIM_private  = (string) $cfg['dkim_private'];
                $mail->DKIM_identity = $mail->From;
            }

            $mail->IsHTML(true);
            $mail->Subject = $asunto;
            $mail->Body    = $html;
            $mail->AltBody = ($texto !== '') ? $texto : trim(strip_tags($html));

            if (!$mail->Send()) {
                error_log('[SOVCA mail] Error SMTP: ' . $mail->ErrorInfo);
                return array('ok' => false, 'error' => (string) $mail->ErrorInfo);
            }

            return array('ok' => true, 'error' => '');
        } catch (\Throwable $e) {
            error_log('[SOVCA mail] Excepción: ' . $e->getMessage());
            return array('ok' => false, 'error' => $e->getMessage());
        }
    }
}

if (!function_exists('bovs_mail_enviar_codigo')) {
    /**
     * Correo con el CÓDIGO de 6 dígitos para restablecer la contraseña.
     *
     * @param string $para
     * @param string $nombre
     * @param string $codigo   Código de 6 dígitos (en claro, solo va en el correo)
     * @param int    $minutos  Vigencia del código
     * @return array ('ok' => bool, 'error' => string)
     */
    function bovs_mail_enviar_codigo($para, $nombre, $codigo, $minutos)
    {
        $nombreSafe = htmlspecialchars((string) $nombre, ENT_QUOTES, 'UTF-8');
        $codigoSafe = htmlspecialchars((string) $codigo, ENT_QUOTES, 'UTF-8');
        $minutos    = (int) $minutos;
        $base       = htmlspecialchars(bovs_url_base(), ENT_QUOTES, 'UTF-8');

        $enlaceHtml = '';
        if ($base !== '') {
            $enlaceHtml = '<p style="margin:0 0 10px;">Ingr&eacute;salo en:</p>'
                . '<p style="font-size:12.5px;margin:0 0 14px;"><a href="' . $base . '" style="color:#2c6e46;">' . $base . '</a> &rarr; <b>&iquest;Olvidaste tu contrase&ntilde;a?</b></p>';
        }

        $contenido = '
<h2 style="color:#234e35;font-size:19px;margin:0 0 14px;">Recuperar contraseña</h2>
<p style="margin:0 0 12px;">Hola <b>' . $nombreSafe . '</b>,</p>
<p style="margin:0 0 12px;">
  Recibimos una solicitud para restablecer la contraseña de tu cuenta en <b>SOVCA</b>.
  Escribe este código en la pantalla de recuperación:
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:22px 0;">
  <tr>
    <td align="center" style="background:#f2f9f4;border:2px dashed #2c6e46;border-radius:14px;padding:24px 20px;">
      <div style="font-size:11px;font-weight:800;color:#2c6e46;text-transform:uppercase;letter-spacing:1.5px;margin:0 0 12px;">
        Tu código de verificaci&oacute;n
      </div>
      <div style="font-family:\'Courier New\',Consolas,monospace;font-size:38px;font-weight:800;color:#1b5e20;letter-spacing:14px;line-height:1;padding-left:14px;">
        ' . $codigoSafe . '
      </div>
      <div style="font-size:12px;color:#5c6b62;margin:14px 0 0;">
        V&aacute;lido por <b style="color:#b45309;">' . $minutos . ' minutos</b> &middot; un solo uso
      </div>
    </td>
  </tr>
</table>

<div style="background:#f4f8f4;border-left:4px solid #2c6e46;padding:12px 16px;border-radius:0 8px 8px 0;font-size:13px;color:#4b5a51;margin:16px 0;">
  <b>Importante:</b> el sistema nunca te pedir&aacute; este c&oacute;digo por tel&eacute;fono ni por WhatsApp.
  No lo compartas con nadie.
</div>

' . $enlaceHtml . '

<p style="margin:0;">Si <b>no solicitaste</b> este cambio, ignora este correo: tu contrase&ntilde;a actual seguir&aacute; funcionando.</p>';

        $texto = "Hola $nombre,\n\n"
            . "Recibimos una solicitud para restablecer tu contraseña en SOVCA.\n"
            . "Tu código de verificación es: $codigo\n\n"
            . "Válido por $minutos minutos y un solo uso.\n"
            . "Si no solicitaste el cambio, ignora este correo.\n";

        return bovs_mail_enviar(
            $para,
            'Tu código SOVCA: ' . $codigo,
            bovs_mail_plantilla('Recuperar contraseña', $contenido),
            $texto,
            array('tipo' => 'transaccional')
        );
    }
}
