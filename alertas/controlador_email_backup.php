<?php
// error_reporting(E_ALL);
// ini_set('display_errors', '1');
require_once 'enviar backup.php';
date_default_timezone_set('America/Caracas');
$fecha = date('d-m-Y');
@$Path_db = "/opt/backups/sistema/sovca/respaldo_db_sovca_$fecha.tar.gz"; // Ruta donde se encuentra ubicado el respaldo

$enviar_correo = new Enviar_Correo();

$correo='cloudsoportesistemas@gmail.com';


if ($Path_db!=''){
  $archivo_db='db_sovca_'.$fecha;
  $enviar_correo->enviar_database($correo,$Path_db,$archivo_db);
}



