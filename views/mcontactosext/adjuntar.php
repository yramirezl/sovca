<?php
require_once '../../lib/validar_session.php';
date_default_timezone_set('America/Caracas');
ValidaSession('login.php');
@$username = $_SESSION['usuariox'];

@$tamano = $_FILES["archivo"]['size'];
@$tipo = $_FILES["archivo"]['type'];
@$archivo = $_FILES["archivo"]['name'];
@$estatus = "";
if(@$_POST["__input_file_wrapper__"] == "upload"){
	
	if ($archivo != "") {
			// guardamos el archivo a la carpeta files
			$arraystr = explode('.',$archivo);
			$archivo = "";
			
			$archivo = "".$arraystr[0];
			$extension = "".$arraystr[1];

			if($archivo == "CONTACTOS" || $archivo =="contactos"){
				
				$destino =  "../../file/".strtoupper($archivo).".".$extension;
				
				if (copy($_FILES['archivo']['tmp_name'],$destino)) {
					$status = '<div class="success"><b>El archivo '.$archivo.' ha sido cargado en el servidor.</b></div>';
				} else {
					$status = '<div class="error">Error al subir archivo</div>';
				}

			}else{ 
			
				$status = '<div class="warning">El nombre del archivo no es igual al requerido. Ver Nota.</div>';
				//$status = $archivo;
			
		   }
			
	}else{
		$status = '<div class="error">Error al subir archivo</div>';
	}
	
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <html>

    <head>
        <title>Sistema BOVS-Web</title>
        <link rel="stylesheet" href="../../css/metro-bootstrap.css">
        <link rel="stylesheet" href="../../css/extra.css">
        <script src="../../js/jquery.min.js"></script>
        <script src="../../js/jquery.widget.min.js"></script>
        <script src="../../js/metro.min.js"></script>

        <script type="text/javascript" src="../../plugins/confirma/jquery-confirm.min.js"></script>
        <link rel="stylesheet" href="../../plugins/confirma/jquery-confirm.min.css" type="text/css" />
        <link rel="stylesheet" href="../../plugins/select3/bootstrap-select.min.css">

    </head>

<body class="metro">
    <div id="container">
        <div id="header">
        </div>
        <div id="content">
            <div class="grid fluid">
                <div class="row" style="margin-top: 4px;">
                    <div class="span4 offset8">
                        <? echo "Usuario: ".$username;  ?>
                    </div>
                </div>
                <div class="row" style="margin-top: 4px;">
                    <div class="span4">
                        <ul class="dropdown-menu open keep-open" style="position: relative; width: 200px; z-index: 1">
                            <li class="menu-title">Archivo</li>
                            <li>
                                <a href="adjuntar.php">Adjuntar archivo</a>
                            </li>
                            <li>
                                <a href="transferir.php">Transferir datos</a>
                            </li>

                            <li class="divider"></li>
                            <li class="">
                                <a id="logout" href="#">Regresar</a>
                            </li>
                        </ul>
                    </div>
                    <div class="span8">
                        <form id="frmupload" name="frmupload" action="adjuntar.php" method="post"
                            enctype="multipart/form-data">
                            <fieldset>
                                <legend>Adjuntar archivo xsl - xslx</legend>
                                </br>
                                <?php if(@$status !=""){echo $status;}?>
                                <div class="input-control file" data-role="input-control">
                                    <input id="archivo" name="archivo" type="file" style="z-index: 0;" tabindex="-1">
                                    <input id="__input_file_wrapper__" name="__input_file_wrapper__" type="text"
                                        style="z-index: 1; cursor: default;" readonly="" value="upload">
                                    <button class="btn btn-file" type="button"></button>
                                </div>
                                <div class="notice marker-on-top" style="margin-top: 8px;">
                                    <div class="fg-white">Nota: El archivo debe ser renombrado con el nombre de
                                        CONTACTOS antes de ser adjuntado.</div>
                                </div>
                                <input type="submit" class="image-button primary" style="margin-top:10px;"
                                    value="Adjuntar">
                                <div>

								<input type="button" id="ver" class="image-button success" style="margin-top:10px;"
                                    value="Obtener Formato">
                                <div>
                                    
                                    
                                </div>

                            </fieldset>
                        </form>
                    </div>
                </div>
            </div><!-- end gridfluid -->
        </div><!-- end content -->
        <div id="footer">
        </div>
    </div>
    <script type="text/javascript">
    jQuery(function($) {
        $("#logout").click(function() {
            // Regresar directo al menú anterior (sin confirmación)
            window.location = '../mcontactosext/';
        });
    });

    $("#ver").click(function() {

        if (confirm('¿Desea Descargar este Formato?')) {
            window.open("../../dist/file/formato/CONTACTOS.xlsx", '',
                "titlebars=0, toolbar=0,scrollbars=0,location=0,statusbar=0,menubar=0,resizable=0,width=320,height=240,top=250,left=500"
            );
        }



    });
    </script>
</body>

</html>