<?php
// error_reporting(E_ALL);
// ini_set('display_errors', '1');
require_once 'enviar_promo.php';
require_once 'conexion.php';

date_default_timezone_set('America/Caracas');
$fecha = date('d-m-Y');
$id_mensaje = $_POST['datos'];

$accion = $_POST['action'];
$msj = '';
  // echo 'EL valor '.$accion;exit;
$num = "";
$con = new conexion();
$enviar_correo = new Enviar_Correo();
if ($accion!= '') {
  $enviar_correo->Cancelar();
  $msj = '<div class="success">El envío de Correos fue Cancelado de forma Exitosa </div>';
  $mensaje_errores .= '<ul class="validation">';
  $mensaje_errores .= '<li style="color: #D8000C;">' . $msj . '</li>';
  $mensaje_errores .= '</ul>';

  echo $mensaje_errores;
} else {

  if (!$con->CONNECT()) {

    $con->DISCONECT();
    unset($con);
    return -1;
  } else {

    $sql_mensaje = "SELECT titulo, cuerpo_mensaje, tipo_mensaje, imagen
                FROM public.mpromomensajes
                where enviado=false and id=" . $id_mensaje . " ; ";

    if (!$rs_mensaje =   $con->SQL_QUERY($sql_mensaje)) {
      $con->ROLLBACK();
    } else {

      if (pg_num_rows($rs_mensaje) > 0) {


        while ($fila_mensaje = pg_fetch_assoc($rs_mensaje)) {

          $titulo = $fila_mensaje["titulo"];
          $cuerpo_mensaje = $fila_mensaje["cuerpo_mensaje"];
          $imagen = $fila_mensaje["imagen"];
        }
      }else{

    
          $msj = '<div class="success">El Mensaje No Puede ser Reenviado </div>';
          $mensaje_errores .= '<ul class="validation">';
          $mensaje_errores .= '<li style="color: #D8000C;">' . $msj . '</li>';
          $mensaje_errores .= '</ul>';
          echo $mensaje_errores;
         die();
        
      }

      $sql_contactos = "SELECT id as id_contacto, name, email,telefono
                      FROM public.mcontactos                      
                      where email<> '' and status=1; ";


      if (!$rs_contactos =   $con->SQL_QUERY($sql_contactos)) {
        $con->ROLLBACK();
      } else {

        @$i = 0;

        $enviar = 0;
        $mensaje_errores = "";

        if (pg_num_rows($rs_contactos) > 0) {


          while ($fila_contactos = pg_fetch_assoc($rs_contactos)) {

            $email = $fila_contactos["email"];
            $nombre = $fila_contactos["name"];

            $enviar_correo->enviar($email,$nombre,$titulo,$cuerpo_mensaje,$imagen);
            $enviar = 1;
          }
         
        }
        if ($enviar >= 1 && $accion=='') {


          $sql_update = " UPDATE mpromomensajes
                        SET enviado=true
                        WHERE id=$id_mensaje ; ";


            if (!$rs_fupdate =   $con->SQL_QUERY($sql_update)) {
            $con->ROLLBACK();
            } 

          $msj = '<div class="success">El envío de Correos se realizó correctamente</div>';
        } else {
          $msj = '<div class="success">El envío de Correos No se realizó </div>';
        }
        $mensaje_errores .= '<ul class="validation">';
        $mensaje_errores .= '<li style="color: #4F8A10;">' . $msj . '</li>';
        $mensaje_errores .= '</ul>';

        echo $mensaje_errores;
      }
    }

    $con->DISCONECT();
    unset($con);
  }
}