<?php

require_once 'class.phpmailer.php';
require_once 'class.smtp.php';
require_once __DIR__ . '/../lib/mailer_helper.php';
date_default_timezone_set('America/Caracas');



class Enviar_Correo{

  function enviar($correo,$arreglo){

    $valor=count( $arreglo);
    $k=0;

     
    while ($k < $valor) {
      $mensaje ="";

      $codigo="";
      $nombre="";
      foreach($arreglo as $data) {

             

      $codigo=$data[0];
      $nombre=$data[1];
      $evento=$data[2];
      $f_pautada=$data[3];
      $dias=$data[4];
      if($dias>1){
       $can_dias= ' Faltan : '.$dias.' Dias';
      }else{
        $can_dias= ' Falta : '.$dias.' Dia';

      }


      $k++;
      $mensaje.='Animal : ' .$codigo.' '.$nombre.'<br>'.'Actividad : '.$evento.'<br>'.' Fecha Pautada : '.$f_pautada.'<br>'.$can_dias.'<br> <br>';

      }


    }

    $mail = new PHPMailer();
    // Servidor, remitente y clave salen de config/mail.php
    // (o de la variable de entorno SOVCA_SMTP_PASS). No repetir aquí.
    bovs_mail_configurar_phpmailer($mail);
    if (function_exists('bovs_mail_cabeceras')) {
      bovs_mail_cabeceras($mail, 'notificacion');
    }
    $mail->Subject       = "Notificaciones SOVCA";
    $body                = '             
              
              <tr>
        <td style="font-family: sans-serif; font-size: 14px; vertical-align: top;" valign="top">&nbsp;</td>
        <td class="container" style="font-family: sans-serif; font-size: 14px; vertical-align: top; display: block; max-width: 580px; padding: 10px; width: 580px; margin: 0 auto;" width="580" valign="top">
          <div class="content" style="box-sizing: border-box; display: block; margin: 0 auto; max-width: 580px; padding: 10px;">

            <!-- START CENTERED WHITE CONTAINER -->
            <table role="presentation" class="main" style="border-collapse: separate; mso-table-lspace: 0pt; mso-table-rspace: 0pt; background: #ffffff; border-radius: 3px; width: 100%;" width="100%">

              <!-- START MAIN CONTENT AREA -->
              <tr>
              
              <table role="presentation" class="main" style="border-collapse: separate; mso-table-lspace: 0pt; mso-table-rspace: 0pt; background: #ffffff; border-radius: 3px; width: 100%;" width="100%">

              <!-- START MAIN CONTENT AREA -->
              <tr>
                <td class="wrapper" style="font-family: sans-serif; font-size: 14px; vertical-align: top; box-sizing: border-box; padding: 20px;" valign="top">
                  <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="border-collapse: separate; mso-table-lspace: 0pt; mso-table-rspace: 0pt; width: 100%;" width="100%">
                    <tr>
                      <td style="font-family: sans-serif; font-size: 14px; vertical-align: top;" valign="top">
                        <p style="font-family: sans-serif; font-size: 14px; font-weight: normal; margin: 0; margin-bottom: 15px;">Hola,</p>
                        <p style="font-family: sans-serif; font-size: 14px; font-weight: normal; margin: 0; margin-bottom: 15px;">Usted ha recibido este mensaje como recordatorio de que posee una alerta en el Sistema Ganadero SOVCA Web...</p><br> <br>

                        <p style="font-family: sans-serif; font-size: 14px; font-weight: normal; margin: 0; margin-bottom: 15px;">'.$mensaje.'</p>

                        <table role="presentation" border="0" cellpadding="0" cellspacing="0" class="btn btn-primary" style="border-collapse: separate; mso-table-lspace: 0pt; mso-table-rspace: 0pt; box-sizing: border-box; width: 100%;" width="100%">
                          <tbody>
                            <tr>
                              <td align="left" style="font-family: sans-serif; font-size: 14px; vertical-align: top; padding-bottom: 15px;" valign="top">
                                <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="border-collapse: separate; mso-table-lspace: 0pt; mso-table-rspace: 0pt; width: auto;">
                                  <tbody>
                                    <tr>
                                    <td class="content-block powered-by" style="font-family: sans-serif; vertical-align: top; padding-bottom: 10px; padding-top: 10px; color: #999999; font-size: 12px; text-align: center;" valign="top" align="center">
                                      <td style="font-family: sans-serif; font-size: 14px; vertical-align: top; border-radius: 5px; text-align: center; background-color: #3498db;" valign="top" align="center" bgcolor="#3498db"> <a href="https://global.ruted.net/sovca/" target="_blank" style="border: solid 1px #3498db; border-radius: 5px; box-sizing: border-box; cursor: pointer; display: inline-block; font-size: 14px; font-weight: bold; margin: 0; padding: 12px 25px; text-decoration: none; text-transform: capitalize; background-color: #3498db; border-color: #3498db; color: #ffffff;">Ingrese Aqui</a> </td>
                                    </tr>
                                  </tbody>
                                </table>
                              </td>
                            </tr>
                          </tbody>
                        </table>
                        <p style="font-family: sans-serif; font-size: 14px; font-weight: normal; margin: 0; margin-bottom: 15px;">Este correo va dirigido exclusivamente a nuestros clientes de Ruted.</p>
                        <p style="font-family: sans-serif; font-size: 14px; font-weight: normal; margin: 0; margin-bottom: 15px;">Ruted, Tecnologia y Desarrollo Rural.</p>
                      </td>
                    </tr>
                  </table>

                  </tr>

                  <!-- END MAIN CONTENT AREA -->
                  </table>
                  </div>
                  </td>
                  <td style="font-family: sans-serif; font-size: 14px; vertical-align: top;" valign="top">&nbsp;</td>
                </tr>
              </table>
            ';
    $mail->AltBody    = "Sistema SOVCA: tiene alertas pendientes en su cuenta. Abra el sistema para ver el detalle.";
    $mail->MsgHTML($body);

    $mail->AddAddress($correo);


    $enviado = $mail->Send();
    $mail->ClearAddresses();
    $mail->ClearAttachments();
    return $enviado;
  }
  /* ---------------------------------------------------------------------
     ENVÍO AGRUPADO (productores multifundo)
     Un solo correo por PRODUCTOR con las alertas de TODOS sus fundos.
     Antes se enviaba un correo por fundo (un productor con 5 fundos
     recibía 5 correos). El envío individual `enviar()` se conserva para
     compatibilidad.
     --------------------------------------------------------------------- */

  /**
   * Arma el cuerpo HTML del correo agrupado (separado del envío para poder
   * revisarlo sin mandar correos).
   *
   * @param array $grupo ['fundos' => [ ['fundo' => 'NOMBRE', 'lote' => [[codigo,nombre,evento,f_pautada,dias], ...]], ... ]]
   * @return string HTML
   */
  function construir_cuerpo_agrupado($grupo)
  {
    $fundos = (isset($grupo['fundos']) && is_array($grupo['fundos'])) ? $grupo['fundos'] : array();

    $secciones = '';
    $n = 0;
    $n_fundos = 0;
    foreach ($fundos as $f) {
      $lote = (isset($f['lote']) && is_array($f['lote'])) ? $f['lote'] : array();
      if (count($lote) === 0) {
        continue;
      }

      $n++;
      $n_fundos++;
      $fundo = htmlspecialchars(isset($f['fundo']) ? $f['fundo'] : '', ENT_QUOTES, 'UTF-8');

      $filas = '';
      foreach ($lote as $data) {
        $codigo    = htmlspecialchars($data[0], ENT_QUOTES, 'UTF-8');
        $animal    = htmlspecialchars($data[1], ENT_QUOTES, 'UTF-8');
        $evento    = htmlspecialchars($data[2], ENT_QUOTES, 'UTF-8');
        $f_pautada = htmlspecialchars($data[3], ENT_QUOTES, 'UTF-8');
        $dias      = (int) $data[4];
        $can_dias  = ($dias > 1) ? ('Faltan : ' . $dias . ' Días') : ('Faltan : ' . $dias . ' Día');

        $filas .= '<tr>'
          . '<td style="padding:6px 8px;border-bottom:1px solid #e5e7eb;">' . $codigo . ' ' . $animal . '</td>'
          . '<td style="padding:6px 8px;border-bottom:1px solid #e5e7eb;">' . $evento . '</td>'
          . '<td style="padding:6px 8px;border-bottom:1px solid #e5e7eb;">' . $f_pautada . '</td>'
          . '<td style="padding:6px 8px;border-bottom:1px solid #e5e7eb;">' . $can_dias . '</td>'
          . '</tr>';
      }

      $num_txt = ($n_fundos > 1 || count($fundos) > 1) ? ($n . '. ') : '';

      $secciones .= '
                        <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="border-collapse:collapse;width:100%;margin-bottom:14px;border:1px solid #e5e7eb;">
                          <tr>
                            <td style="background:#f3f4f6;padding:10px 12px;font-family:sans-serif;font-size:14px;">
                              <strong>' . $num_txt . $fundo . '</strong>
                            </td>
                          </tr>
                          <tr>
                            <td style="padding:12px;">
                              <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="border-collapse:collapse;width:100%;font-family:sans-serif;font-size:13px;">
                                <thead>
                                  <tr style="background:#f9fafb;">
                                    <th style="text-align:left;padding:6px 8px;">Animal</th>
                                    <th style="text-align:left;padding:6px 8px;">Actividad</th>
                                    <th style="text-align:left;padding:6px 8px;">Fecha pautada</th>
                                    <th style="text-align:left;padding:6px 8px;">Plazo</th>
                                  </tr>
                                </thead>
                                <tbody>' . $filas . '</tbody>
                              </table>
                            </td>
                          </tr>
                        </table>';
    }

    if ($n_fundos > 1) {
      $intro = 'Usted ha recibido este mensaje como recordatorio de que posee alertas pendientes en
                        <strong>' . $n_fundos . ' de sus fundos</strong> en el Sistema Ganadero SOVCA Web...';
    } else {
      $intro = 'Usted ha recibido este mensaje como recordatorio de que posee una alerta en el
                        Sistema Ganadero SOVCA Web...';
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
                        <p style="font-family: sans-serif; font-size: 14px; font-weight: normal; margin: 0; margin-bottom: 15px;">Hola,</p>
                        <p style="font-family: sans-serif; font-size: 14px; font-weight: normal; margin: 0; margin-bottom: 15px;">' . $intro . '</p><br>

                        ' . $secciones . '

                        <table role="presentation" border="0" cellpadding="0" cellspacing="0" class="btn btn-primary" style="border-collapse: separate; mso-table-lspace: 0pt; mso-table-rspace: 0pt; box-sizing: border-box; width: 100%;" width="100%">
                          <tbody>
                            <tr>
                              <td align="left" style="font-family: sans-serif; font-size: 14px; vertical-align: top; padding-bottom: 15px;" valign="top">
                                <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="border-collapse: separate; mso-table-lspace: 0pt; mso-table-rspace: 0pt; width: auto;">
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
                        <p style="font-family: sans-serif; font-size: 14px; font-weight: normal; margin: 0; margin-bottom: 15px;">Este correo va dirigido exclusivamente a nuestros clientes de Ruted.</p>
                        <p style="font-family: sans-serif; font-size: 14px; font-weight: normal; margin: 0; margin-bottom: 15px;">Ruted, Tecnologia y Desarrollo Rural.</p>
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
   * Envía en UN SOLO correo las alertas de todos los fundos del productor.
   */
  function enviar_agrupado($correo, $grupo)
  {
    $fundos = (isset($grupo['fundos']) && is_array($grupo['fundos'])) ? $grupo['fundos'] : array();

    $n_fundos = 0;
    $n_alertas = 0;
    foreach ($fundos as $f) {
      $lote = (isset($f['lote']) && is_array($f['lote'])) ? $f['lote'] : array();
      if (count($lote) === 0) {
        continue;
      }
      $n_fundos++;
      $n_alertas += count($lote);
    }

    $mail = new PHPMailer();
    // Servidor, remitente y clave salen de config/mail.php
    // (o de la variable de entorno SOVCA_SMTP_PASS). No repetir aquí.
    bovs_mail_configurar_phpmailer($mail);
    if (function_exists('bovs_mail_cabeceras')) {
      bovs_mail_cabeceras($mail, 'notificacion');
    }
    $mail->CharSet       = 'UTF-8';
    $mail->Subject       = ($n_fundos > 1)
      ? ('Notificaciones SOVCA - alertas en ' . $n_fundos . ' fundos')
      : "Notificaciones SOVCA";

    $mail->AltBody    = "Sistema SOVCA: resumen de alertas enviadas a los fundos de su cuenta.";
    $mail->MsgHTML($this->construir_cuerpo_agrupado($grupo));

    $mail->AddAddress($correo);

    $enviado = $mail->Send();
    $mail->ClearAddresses();
    $mail->ClearAttachments();
    return $enviado;
  }

  function enviar_resumen($i){

  
      $mensaje='Fueron Enviadas  : ' .$i.' Notificaciones'.'<br><br> <br>';

  

    $mail = new PHPMailer();
    // Servidor, remitente y clave salen de config/mail.php
    // (o de la variable de entorno SOVCA_SMTP_PASS). No repetir aquí.
    bovs_mail_configurar_phpmailer($mail);
    if (function_exists('bovs_mail_cabeceras')) {
      bovs_mail_cabeceras($mail, 'notificacion');
    }
    $mail->Subject       = "Resumen de notificaciones SOVCA";
    $body                = '             
              
              <tr>
        <td style="font-family: sans-serif; font-size: 14px; vertical-align: top;" valign="top">&nbsp;</td>
        <td class="container" style="font-family: sans-serif; font-size: 14px; vertical-align: top; display: block; max-width: 580px; padding: 10px; width: 580px; margin: 0 auto;" width="580" valign="top">
          <div class="content" style="box-sizing: border-box; display: block; margin: 0 auto; max-width: 580px; padding: 10px;">

            <!-- START CENTERED WHITE CONTAINER -->
            <table role="presentation" class="main" style="border-collapse: separate; mso-table-lspace: 0pt; mso-table-rspace: 0pt; background: #ffffff; border-radius: 3px; width: 100%;" width="100%">

              <!-- START MAIN CONTENT AREA -->
              <tr>
              
              <table role="presentation" class="main" style="border-collapse: separate; mso-table-lspace: 0pt; mso-table-rspace: 0pt; background: #ffffff; border-radius: 3px; width: 100%;" width="100%">

              <!-- START MAIN CONTENT AREA -->
              <tr>
                <td class="wrapper" style="font-family: sans-serif; font-size: 14px; vertical-align: top; box-sizing: border-box; padding: 20px;" valign="top">
                  <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="border-collapse: separate; mso-table-lspace: 0pt; mso-table-rspace: 0pt; width: 100%;" width="100%">
                    <tr>
                      <td style="font-family: sans-serif; font-size: 14px; vertical-align: top;" valign="top">
                        <p style="font-family: sans-serif; font-size: 14px; font-weight: normal; margin: 0; margin-bottom: 15px;">Hola,</p>
                        <p style="font-family: sans-serif; font-size: 14px; font-weight: normal; margin: 0; margin-bottom: 15px;">Usted ha recibido este mensaje para informarle que han sido generadas y enviadas las alertas en el Sistema Ganadero SOVCA Web...</p><br> <br>

                        <p style="font-family: sans-serif; font-size: 14px; font-weight: normal; margin: 0; margin-bottom: 15px;">'.$mensaje.'</p>

                        <table role="presentation" border="0" cellpadding="0" cellspacing="0" class="btn btn-primary" style="border-collapse: separate; mso-table-lspace: 0pt; mso-table-rspace: 0pt; box-sizing: border-box; width: 100%;" width="100%">
                          <tbody>
                            <tr>
                              <td align="left" style="font-family: sans-serif; font-size: 14px; vertical-align: top; padding-bottom: 15px;" valign="top">
                                <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="border-collapse: separate; mso-table-lspace: 0pt; mso-table-rspace: 0pt; width: auto;">
                                  <tbody>
                                    <tr>
                                    <td class="content-block powered-by" style="font-family: sans-serif; vertical-align: top; padding-bottom: 10px; padding-top: 10px; color: #999999; font-size: 12px; text-align: center;" valign="top" align="center">
                                      <td style="font-family: sans-serif; font-size: 14px; vertical-align: top; border-radius: 5px; text-align: center; background-color: #3498db;" valign="top" align="center" bgcolor="#3498db"> <a href="https://global.ruted.net/sovca/" target="_blank" style="border: solid 1px #3498db; border-radius: 5px; box-sizing: border-box; cursor: pointer; display: inline-block; font-size: 14px; font-weight: bold; margin: 0; padding: 12px 25px; text-decoration: none; text-transform: capitalize; background-color: #3498db; border-color: #3498db; color: #ffffff;">Sistema SOVCA Web</a> </td>
                                    </tr>
                                  </tbody>
                                </table>
                              </td>
                            </tr>
                          </tbody>
                        </table>
                        <p style="font-family: sans-serif; font-size: 14px; font-weight: normal; margin: 0; margin-bottom: 15px;">Este correo va dirigido exclusivamente a los Administradores del Sistema.</p>
                        <p style="font-family: sans-serif; font-size: 14px; font-weight: normal; margin: 0; margin-bottom: 15px;">Ruted, Tecnologia y Desarrollo Rural.</p>
                      </td>
                    </tr>
                  </table>

                  </tr>

                  <!-- END MAIN CONTENT AREA -->
                  </table>
                  </div>
                  </td>
                  <td style="font-family: sans-serif; font-size: 14px; vertical-align: top;" valign="top">&nbsp;</td>
                </tr>
              </table>
            ';
    $mail->AltBody    = "Sistema SOVCA: se generaron y enviaron las alertas de su cuenta. Abra el sistema para ver el detalle.";
    $mail->MsgHTML($body);

    $mail->AddAddress('yonnygr@gmail.com','info@ruted.net');


    $mail->Send();
    $mail->ClearAddresses();
    $mail->ClearAttachments();
  }

  
  }

 