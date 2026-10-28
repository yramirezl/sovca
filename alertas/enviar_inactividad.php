<?php

/**
 * Correo de INACTIVIDAD DE UN FUNDO.
 *
 * Se envía al PRODUCTOR (dueño del fundo) cuando ninguno de los usuarios
 * del fundo (productor y ayudantes) se ha conectado en los últimos N días.
 * Expresa preocupación por la inactividad y ofrece ayuda del equipo.
 *
 * Lo usa `controlador_email_inactividad.php` (ver ese archivo para la
 * detección y el seguimiento de envíos).
 *
 * Credenciales SMTP: igual que el resto de los enviadores de `alertas/`,
 * la contraseña se coloca al desplegar (no se guarda en el repositorio).
 */

require_once 'class.phpmailer.php';
require_once 'class.smtp.php';
require_once __DIR__ . '/../lib/mailer_helper.php';
date_default_timezone_set('America/Caracas');

class Enviar_Correo
{
  /**
   * Arma el cuerpo HTML del aviso (separado del envío para poder revisarlo
   * sin mandar correos: `--dry-run --html`).
   *
   * @param array $datos productor + fundos[] (cada fundo: fundo, rif, dias, ultima_conexion, usuarios[]).
   *                     También acepta un único fundo en el nivel raíz (compatibilidad).
   * @return string HTML
   */
  function construir_cuerpo($datos)
  {
    $productor = htmlspecialchars(isset($datos['productor']) ? $datos['productor'] : '', ENT_QUOTES, 'UTF-8');

    /* Un productor multifundo recibe UN SOLO correo con todos sus fundos:
       el cuerpo se arma siempre a partir de una lista de fundos. */
    $fundos = array();
    if (isset($datos['fundos']) && is_array($datos['fundos'])) {
      $fundos = $datos['fundos'];
    } elseif (isset($datos['fundo'])) {
      $fundos[] = $datos;
    }
    $total_fundos = count($fundos);

    /* Un bloque por fundo, con su resumen y sus usuarios */
    $bloques = '';
    $n = 0;
    foreach ($fundos as $f) {
      $n++;
      $fundo   = htmlspecialchars(isset($f['fundo']) ? $f['fundo'] : '', ENT_QUOTES, 'UTF-8');
      $rif     = htmlspecialchars(isset($f['rif']) ? $f['rif'] : '', ENT_QUOTES, 'UTF-8');
      $dias    = (int) (isset($f['dias']) ? $f['dias'] : 7);
      $ultima  = !empty($f['ultima_conexion'])
        ? htmlspecialchars($f['ultima_conexion'], ENT_QUOTES, 'UTF-8')
        : 'Sin registros de conexión';
      $rif_txt = ($rif !== '') ? ' · RIF/Cédula: ' . $rif : '';
      $num_txt = ($total_fundos > 1) ? ($n . '. ') : '';

      $filas = '';
      if (!empty($f['usuarios']) && is_array($f['usuarios'])) {
        foreach ($f['usuarios'] as $u) {
          $rol = ((int) $u['rol'] === 2) ? 'Productor' : 'Ayudante';
          $uc  = !empty($u['ultima_conexion'])
            ? htmlspecialchars($u['ultima_conexion'], ENT_QUOTES, 'UTF-8')
            : 'Sin registros';
          $filas .= '<tr>'
            . '<td style="padding:6px 8px;border-bottom:1px solid #e5e7eb;">' . htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td style="padding:6px 8px;border-bottom:1px solid #e5e7eb;">' . htmlspecialchars($u['nombre'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td style="padding:6px 8px;border-bottom:1px solid #e5e7eb;">' . $rol . '</td>'
            . '<td style="padding:6px 8px;border-bottom:1px solid #e5e7eb;">' . $uc . '</td>'
            . '</tr>';
        }
      }
      if ($filas === '') {
        $filas = '<tr><td colspan="4" style="padding:6px 8px;color:#6b7280;">Sin usuarios activos asociados.</td></tr>';
      }

      $bloques .= '
                        <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="border-collapse:collapse;width:100%;margin-bottom:14px;border:1px solid #e5e7eb;">
                          <tr>
                            <td style="background:#f3f4f6;padding:10px 12px;font-family:sans-serif;font-size:14px;">
                              <strong>' . $num_txt . $fundo . '</strong>' . $rif_txt . '
                            </td>
                          </tr>
                          <tr>
                            <td style="padding:12px;font-family:sans-serif;font-size:13px;">
                              <p style="margin:0 0 10px;"><strong>' . $dias . ' días sin conexiones</strong> · Última conexión registrada: <strong>' . $ultima . '</strong></p>
                              <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="border-collapse:collapse;width:100%;font-family:sans-serif;font-size:13px;">
                                <thead>
                                  <tr style="background:#f9fafb;">
                                    <th style="text-align:left;padding:6px 8px;">Usuario</th>
                                    <th style="text-align:left;padding:6px 8px;">Nombre</th>
                                    <th style="text-align:left;padding:6px 8px;">Rol</th>
                                    <th style="text-align:left;padding:6px 8px;">Última conexión</th>
                                  </tr>
                                </thead>
                                <tbody>' . $filas . '</tbody>
                              </table>
                            </td>
                          </tr>
                        </table>';
    }

    /* Párrafo de entrada: cambia según sea uno o varios fundos */
    if ($total_fundos > 1) {
      $intro = 'Le escribimos del equipo de <strong>SOVCA · Gestión Ovina y Caprina</strong> porque notamos con preocupación
                          que <strong>' . $total_fundos . ' de sus fundos no registran actividad</strong>: ninguno de los usuarios
                          asociados ha ingresado al sistema en los últimos días. El detalle de cada fundo lo encuentra a continuación.';
    } else {
      $f0    = isset($fundos[0]) ? $fundos[0] : array();
      $intro = 'Le escribimos del equipo de <strong>SOVCA · Gestión Ovina y Caprina</strong> porque notamos con preocupación
                          que su fundo <strong>' . htmlspecialchars(isset($f0['fundo']) ? $f0['fundo'] : '', ENT_QUOTES, 'UTF-8') . '</strong>'
        . ((isset($f0['rif']) && $f0['rif'] !== '') ? ' (RIF/Cédula: ' . htmlspecialchars($f0['rif'], ENT_QUOTES, 'UTF-8') . ')' : '')
        . ' lleva <strong>' . (int) (isset($f0['dias']) ? $f0['dias'] : 7) . ' días sin registrar actividad</strong>:
                          ninguno de los usuarios asociados ha ingresado al sistema en ese período.';
    }

    $body = '
              <tr>
        <td style="font-family: sans-serif; font-size: 14px; vertical-align: top;" valign="top">&nbsp;</td>
        <td class="container" style="font-family: sans-serif; font-size: 14px; vertical-align: top; display: block; max-width: 580px; padding: 10px; width: 580px; margin: 0 auto;" width="580" valign="top">
          <div class="content" style="box-sizing: border-box; display: block; margin: 0 auto; max-width: 580px; padding: 10px;">

            <table role="presentation" class="main" style="border-collapse: separate; mso-table-lspace: 0pt; mso-table-rspace: 0pt; background: #ffffff; border-radius: 3px; width: 100%;" width="100%">
              <tr>
                <td class="wrapper" style="font-family: sans-serif; font-size: 14px; vertical-align: top; box-sizing: border-box; padding: 20px;" valign="top">
                  <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="border-collapse: separate; mso-table-lspace: 0pt; mso-table-rspace: 0pt; width: 100%;" width="100%">
                    <tr>
                      <td style="font-family: sans-serif; font-size: 14px; vertical-align: top;" valign="top">

                        <p style="font-family: sans-serif; font-size: 14px; margin: 0 0 15px;">Hola <strong>' . $productor . '</strong>,</p>

                        <p style="font-family: sans-serif; font-size: 14px; margin: 0 0 15px;">
                          ' . $intro . '
                        </p>

                        <p style="font-family: sans-serif; font-size: 14px; margin: 0 0 15px;">
                          Sabemos que el trabajo en el campo no se detiene y que a veces el tiempo no alcanza para
                          cargar la información. Por eso queremos recordarle que <strong>no está solo</strong>:
                          podemos ayudarle a retomar el uso del sistema y aprovechar todo lo que ya tiene cargado.
                        </p>

                        <p style="font-family: sans-serif; font-size: 14px; margin: 0 0 8px;"><strong>En qué podemos ayudarle:</strong></p>
                        <ul style="font-family: sans-serif; font-size: 14px; margin: 0 0 15px; padding-left: 20px;">
                          <li style="margin-bottom: 4px;">Capacitación y acompañamiento a usted y a su equipo (web y app sin conexión).</li>
                          <li style="margin-bottom: 4px;">Carga o migración de los datos que tenga en papel u otra herramienta.</li>
                          <li style="margin-bottom: 4px;">Revisión de sus reportes, alertas y configuración del fundo.</li>
                          <li style="margin-bottom: 4px;">Soporte técnico directo, también para los equipos que trabajan sin señal.</li>
                        </ul>

                        <p style="font-family: sans-serif; font-size: 14px; margin: 0 0 10px;"><strong>Detalle de sus fundos sin actividad:</strong></p>

                        ' . $bloques . '

                        <table role="presentation" border="0" cellpadding="0" cellspacing="0" class="btn btn-primary" style="border-collapse: separate; box-sizing: border-box; width: 100%;" width="100%">
                          <tbody>
                            <tr>
                              <td align="left" style="font-family: sans-serif; font-size: 14px; vertical-align: top; padding-bottom: 15px;" valign="top">
                                <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="border-collapse: separate; width: auto;">
                                  <tbody>
                                    <tr>
                                      <td style="font-family: sans-serif; font-size: 14px; vertical-align: top; border-radius: 5px; text-align: center; background-color: #3498db;" valign="top" align="center" bgcolor="#3498db"> <a href="https://global.ruted.net/sovca/" target="_blank" style="border: solid 1px #3498db; border-radius: 5px; box-sizing: border-box; cursor: pointer; display: inline-block; font-size: 14px; font-weight: bold; margin: 0; padding: 12px 25px; text-decoration: none; text-transform: capitalize; background-color: #3498db; border-color: #3498db; color: #ffffff;">Ingrese Aquí</a> </td>
                                    </tr>
                                  </tbody>
                                </table>
                              </td>
                            </tr>
                          </tbody>
                        </table>

                        <p style="font-family: sans-serif; font-size: 14px; margin: 0 0 15px;">
                          Si prefiere, responda este correo o escríbanos y coordinamos una visita o una sesión remota.
                          Estamos para ayudarle a que sus fundos sigan produciendo con información al día.
                        </p>
                        <p style="font-family: sans-serif; font-size: 14px; margin: 0 0 15px;">Ruted, Tecnología y Desarrollo Rural.</p>
                        <p style="font-family: sans-serif; font-size: 12px; color: #999999; margin: 0;">Este correo va dirigido exclusivamente a nuestros clientes de Ruted.</p>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>
          </div>
        </td>
        <td style="font-family: sans-serif; font-size: 14px; vertical-align: top;" valign="top">&nbsp;</td>
      </tr>
    ';

    return $body;
  }

  /**
   * Envía el aviso de inactividad. Devuelve true si el correo salió.
   */
  function enviar_inactividad($correo, $datos)
  {
    $mail = new PHPMailer();
    // Servidor, remitente y clave salen de config/mail.php
    // (o de la variable de entorno SOVCA_SMTP_PASS). No repetir aquí.
    bovs_mail_configurar_phpmailer($mail);
    $mail->CharSet       = 'UTF-8';

    if (function_exists('bovs_mail_cabeceras')) {
      bovs_mail_cabeceras($mail, 'notificacion');
    }

    /* Un productor multifundo recibe un solo correo: el asunto refleja
       si el aviso cubre uno o varios de sus fundos. */
    $fundos_dato = (isset($datos['fundos']) && is_array($datos['fundos'])) ? $datos['fundos'] : array();
    $total_fundos = count($fundos_dato);
    if ($total_fundos === 0 && isset($datos['fundo'])) {
      $total_fundos = 1;
      $fundos_dato = array($datos);
    }

    if ($total_fundos > 1) {
      $mail->Subject = 'Detectamos ' . $total_fundos . ' fundos sin actividad en su cuenta SOVCA';
      $mail->AltBody = "Detectamos " . $total_fundos . " fundos de su cuenta sin registrar actividad en el "
        . "Sistema SOVCA. Escríbanos y con gusto le ayudamos a retomar el uso del sistema.";
    } else {
      $nombre_fundo = isset($fundos_dato[0]['fundo']) ? $fundos_dato[0]['fundo'] : '';
      $dias_fundo   = (int) (isset($fundos_dato[0]['dias']) ? $fundos_dato[0]['dias']
        : (isset($datos['dias']) ? $datos['dias'] : 7));
      $mail->Subject = "¿Cómo podemos ayudarle con el fundo " . $nombre_fundo . "?";
      $mail->AltBody = "Su fundo " . $nombre_fundo . " lleva " . $dias_fundo
        . " días sin registrar actividad en el Sistema SOVCA. Escríbanos y con gusto le ayudamos a retomar el uso del sistema.";
    }

    $mail->MsgHTML($this->construir_cuerpo($datos));

    $mail->AddAddress($correo);

    $enviado = $mail->Send();
    $mail->ClearAddresses();
    $mail->ClearAttachments();

    return $enviado;
  }

  /**
   * Resumen interno de la corrida (para el equipo de soporte).
   */
  function enviar_resumen($correo, $datos)
  {
    $mensaje = 'Fundos candidatos: ' . (int) (isset($datos['revisados']) ? $datos['revisados'] : 0)
      . '<br>Correos enviados (agrupados por productor): ' . (int) (isset($datos['enviados']) ? $datos['enviados'] : 0)
      . '<br>Fundos avisados: ' . (int) (isset($datos['avisados']) ? $datos['avisados'] : 0)
      . '<br>Fundos omitidos (ya avisados antes): ' . (int) (isset($datos['omitidos']) ? $datos['omitidos'] : 0)
      . '<br>Errores: ' . (int) (isset($datos['errores']) ? $datos['errores'] : 0)
      . '<br><br>Días de inactividad configurados: ' . (int) $datos['dias'];

    if (!empty($datos['detalle'])) {
      $mensaje .= '<br><br>' . $datos['detalle'];
    }

    $mail = new PHPMailer();
    // Servidor, remitente y clave salen de config/mail.php
    // (o de la variable de entorno SOVCA_SMTP_PASS). No repetir aquí.
    bovs_mail_configurar_phpmailer($mail);
    $mail->CharSet       = 'UTF-8';
    if (function_exists('bovs_mail_cabeceras')) {
      bovs_mail_cabeceras($mail, 'notificacion');
    }
    $mail->Subject       = "Notificaciones SOVCA - Inactividad de fundos";
    $mail->AltBody       = strip_tags(str_replace('<br>', "\n", $mensaje));
    $mail->MsgHTML('<div style="font-family:sans-serif;font-size:14px;">'
      . '<p>Resumen del aviso por inactividad de fundos:</p>'
      . '<p>' . $mensaje . '</p>'
      . '<p>Ruted, Tecnología y Desarrollo Rural.</p></div>');
    $mail->AddAddress($correo);

    $enviado = $mail->Send();
    $mail->ClearAddresses();
    $mail->ClearAttachments();

    return $enviado;
  }
}
