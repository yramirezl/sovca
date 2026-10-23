<?php

include '../lib/sanitizar.php';
include '../lib/imagen_helper.php';

date_default_timezone_set('America/Caracas');

@$action = ($_POST["action"]);


@$sourcePath = $_FILES['file']['tmp_name'];  // Almacenar ruta de origen del archivo en una variable
@$targetPath = "../dist/img/fotos_animales/" . $_FILES['file']['name']; // Ruta de destino donde el archivo se va a almacenar
@$tipo_archivo = $_FILES['file']['type'];



switch ($action) {
  case 'addimagen':

      // Redimensiona y convierte a WebP antes de guardar
      if (bovs_imagen_hay_archivo(isset($_FILES['file']) ? $_FILES['file'] : null)) {
        $err_img = bovs_imagen_error_archivo($_FILES['file']);
        if ($err_img === null) {
          @$nombre_imagen = bovs_imagen_procesar(
            $_FILES['file']['tmp_name'],
            '../dist/img/fotos_animales/',
            bovs_imagen_nombre_base($_FILES['file']['name'])
          );
        }
      }
break;
    
}
     
      