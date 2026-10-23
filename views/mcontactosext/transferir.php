<?php
date_default_timezone_set('America/Caracas');
require_once '../../lib/validar_session.php';

ValidaSession('login.php');
@$username = $_SESSION['usuariox'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
<html>
<head>
    <title>Sistema BOVS</title>
    <link rel="stylesheet" href="../../css/metro-bootstrap.css">
     <link rel="stylesheet" href="../../css/extra.css">
    <script src="../../js/jquery.min.js"></script>
    <script src="../../js/jquery.widget.min.js"></script>
    <script src="../../js/metro.min.js"></script>
</head>
<body class="metro">
	<div id="container">
	<input type="hidden" id="c_data">

		<div id="header">
		</div> 
		<div id="content">
		<div class="grid fluid">	
			<div class="row" style="margin-top: 4px;"><div class="span4 offset8"><? echo "Usuario: ".$username;  ?></div></div>
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
					<form id="frmtransf" name="frmtransf" action="" method="post">
						<fieldset>
						<legend>Transferir data al servidor.</legend>
							</br>
							<div id="msj" style="margin-bottom:5px;"></div>
							<?php 
							if ($gestor = opendir('../../file/')) {
								echo '<div class="listview">';
								while (false !== ($arch = readdir($gestor))) {
								   if ($arch != "." && $arch != "..") {
									   //echo "<li><a href=\"files/".$arch."\" class=\"linkli\">".$arch."</a></li>\n";
									 if($arch !== "index.php"){  
										 //echo $arch;
										echo		'<a class="list" href="#">
														<div class="list-content">
															<img class="icon" src="../../dist/img/excel2013icon.png">
															<div class="data">
															<span class="list-title">Archivo Excel:</span>
															<span class="list-remark">'.$arch.'</span>
															</div>
														</div>
													</a>';
									  }
									   
								   }
								}
								closedir($gestor);
								echo "</div>";
							}
							?>
							<button type="button" id="send" name="send" class="image-button primary">Transferir<img class="bg-cobalt" src="../img/upload-32.png"></button>
							<div>

                            <input type="checkbox" id="data"> Conservar Data                                              
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
	
var datos=0;

	$("#data").click(function() {
    
    if ($('#data').prop('checked') == true) {
		$("#c_data").val(1);
	}else{
		$("#c_data").val(0);

	}
	datos=$("#c_data").val();

	});

jQuery(function($){


	$("#send").click(function() { 
		$('#msj').html('<div><img src="../../dist/img/ajax-loader.gif"/></div>');
		$.post("../../controllers/transferencia_controller",{datos:datos}).done( function(data){if(data != ""){$("#msj" ).html(data);}});
	});
$("#logout" ).click(function(){ window.location = '../mcontactosext/'; });

		
});
</script>
</body>
</html>
