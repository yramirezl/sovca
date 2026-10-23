<?php
/**
 * Envío del CÓDIGO de verificación de correo (alta de usuario).
 *
 * Este archivo conserva el contrato histórico que usa
 * controllers/mavalidacorreo_controller.php:
 *
 *     $correo_sender = new Enviar_Correo();
 *     $correo_sender->enviar($correo, $codigo);   // devuelve true/false
 *
 * La construcción y el envío real del mensaje se delegan en
 * lib/mailer_helper.php para que todos los correos del sistema compartan
 * la misma plantilla, el mismo remitente y las mismas cabeceras
 * (Return-Path, Message-ID, List-Unsubscribe, texto alternativo, ...).
 */

date_default_timezone_set('America/Caracas');

require_once __DIR__ . '/../lib/mailer_helper.php';

if (!class_exists('Enviar_Correo')) {

    class Enviar_Correo
    {
        /**
         * @param string $correo          Destinatario
         * @param string $codigo_generado Código de verificación en claro
         * @param string $nombre          Nombre del usuario (opcional, solo para el saludo)
         * @return bool
         */
        public function enviar($correo, $codigo_generado, $nombre = '')
        {
            $correo = trim((string) $correo);
            $codigo = trim((string) $codigo_generado);

            if ($correo === '' || $codigo === '') {
                return false;
            }

            $cfg     = bovs_mail_config();
            $minutos = !empty($cfg['reset_vigencia_min']) ? (int) $cfg['reset_vigencia_min'] : 15;
            $saludo  = trim((string) $nombre);
            $base    = bovs_url_base();

            $nombreSafe = htmlspecialchars($saludo !== '' ? $saludo : 'usuario', ENT_QUOTES, 'UTF-8');
            $codigoSafe = htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8');

            $enlaceHtml = '';
            if ($base !== '') {
                $enlaceHtml = '<p style="margin:0 0 10px;">Contin&uacute;a en:</p>'
                    . '<p style="font-size:12.5px;margin:0 0 14px;"><a href="'
                    . htmlspecialchars($base, ENT_QUOTES, 'UTF-8')
                    . '" style="color:#2c6e46;">' . htmlspecialchars($base, ENT_QUOTES, 'UTF-8')
                    . '</a> &rarr; <b>Verificaci&oacute;n de correo</b></p>';
            }

            $contenido = '
<h2 style="color:#234e35;font-size:19px;margin:0 0 14px;">Verifica tu correo</h2>
<p style="margin:0 0 12px;">Hola <b>' . $nombreSafe . '</b>,</p>
<p style="margin:0 0 12px;">
  Para activar tu cuenta en <b>SOVCA</b> escribe este c&oacute;digo en la pantalla de verificaci&oacute;n:
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:22px 0;">
  <tr>
    <td align="center" style="background:#f2f9f4;border:2px dashed #2c6e46;border-radius:14px;padding:24px 20px;">
      <div style="font-size:11px;font-weight:800;color:#2c6e46;text-transform:uppercase;letter-spacing:1.5px;margin:0 0 12px;">
        Tu c&oacute;digo de verificaci&oacute;n
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

<p style="margin:0;">Si <b>no creaste</b> esta cuenta, ignora este correo y no se activar&aacute; nada.</p>';

            $texto = "Hola " . ($saludo !== '' ? $saludo : 'usuario') . ",\n\n"
                . "Tu código de verificación de SOVCA es: $codigo\n\n"
                . "Válido por $minutos minutos y un solo uso.\n"
                . "Si no creaste esta cuenta, ignora este correo.\n";

            $res = bovs_mail_enviar(
                $correo,
                'Tu código de verificación SOVCA: ' . $codigo,
                bovs_mail_plantilla('Verifica tu correo', $contenido),
                $texto,
                array('tipo' => 'transaccional')
            );

            return !empty($res['ok']);
        }
    }

}
