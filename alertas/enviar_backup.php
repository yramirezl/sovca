<?php

require_once 'class.phpmailer.php';
require_once 'class.smtp.php';
require_once __DIR__ . '/../lib/mailer_helper.php';
date_default_timezone_set('America/Caracas');



class Enviar_Correo{

  function enviar_database($correo,$Path_db,$archivo_db){
   
     $mensaje='Fue Enviado el Respaldo de la Base de Datos de Manera exitosa'.'<br><br> <br>';

    $mail = new PHPMailer();
    $mail->IsSMTP(); // telling the class to use SMTP
    $mail->Host          = "mail.ruted.net";
    $mail->SMTPSecure = 'ssl';
    $mail->SMTPAuth      = true;                  // enable SMTP authentication
    //$mail->SMTPKeepAlive = true;                  // SMTP connection will not close after each email sent           
    $mail->Port          = 465;                    // set the SMTP port for the GMAIL server
    $mail->Username      = 'global@ruted.net'; //$remitente; // SMTP account username
    $mail->Password      = 'Null'; //$clave_email;        // SMTP account password
    $mail->AddAttachment($Path_db,$archivo_db);
    $mail->SetFrom('global@ruted.net', 'SOVCA · Gestión Ovina y Caprina');
    $mail->AddReplyTo('global@ruted.net', 'SOVCA · Soporte');
    if (function_exists('bovs_mail_cabeceras')) {
      bovs_mail_cabeceras($mail, 'notificacion');
    }
    $mail->Subject       = "Respaldo automático SOVCA";
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
                        <p style="font-family: sans-serif; font-size: 14px; font-weight: normal; margin: 0; margin-bottom: 15px;">Usted ha recibido este mensaje para informarle que se llevó a cabo el respaldo automático de la base de datos de SOVCA de manera exitosa...</p><br> <br>

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
                        <p style="font-family: sans-serif; font-size: 14px; font-weight: normal; margin: 0; margin-bottom: 15px;">Este correo va dirigido exclusivamente al Administrador del Servidor.</p>
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
    $body             = preg_replace("~/~", '', $body);
    $mail->AltBody    = "SOVCA: el respaldo automático de la base de datos se realizó correctamente. El archivo va adjunto a este correo.";
    $mail->MsgHTML($body);

    $mail->AddAddress($correo);

    $mail->Send();
    $mail->ClearAddresses();
    $mail->ClearAttachments();
  }

  
  }

 