<?php
include("../../lib/validar_session.php");
session_start();

@$username = $_SESSION['usuariox'];

@$mensaje = $_GET['stat'];
		
		
switch ($mensaje){
	
	case 'passnull':
			$msj ='<div class="alert alert-danger alert-dismissable">
			<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
			<h4>
			<i class="icon fa fa-ban"></i>
			Alerta!
			</h4>Campo nueva contraseña y confirmar contraseña vacía.</div>';
	break;
	
	case 'pass1':
		$msj ='<div class="alert alert-danger alert-dismissable">
			<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
			<h4>
			<i class="icon fa fa-ban"></i>
			Alerta!
			</h4>Campo nueva contraseña vacío.</div>';
		break; 
		    
	case 'pass2':
		$msj ='<div class="alert alert-danger alert-dismissable">
			<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
			<h4>
			<i class="icon fa fa-ban"></i>
			Alerta!
			</h4>Campo confirmar contraseña vacío.</div>';
		break;
		
	case 'me0':
		$msj ='<div class="alert alert-danger alert-dismissable">
			<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
			<h4>
			<i class="icon fa fa-ban"></i>
			Alerta!
			</h4>La nueva clave debe estar compuesta por letras y números o los siguentes caracteres @, #, $, %</div>';
		break;
	case 'me2':
		$msj ='<div class="alert alert-danger alert-dismissable">
			<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
			<h4>
			<i class="icon fa fa-ban"></i>
			Alerta!
			</h4>La nueva contraseña no coincide con la confirmación de la misma.</div>';
		break; 
		
	case 'me3':
		$msj ='<div class="alert alert-danger alert-dismissable">
			<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
			<h4>
			<i class="icon fa fa-ban"></i>
			Alerta!
			</h4>Error al actualizar la contraseña.</div>';
		break; 
	case 'me4':
		$msj ='<div class="alert alert-danger alert-dismissable">
			<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
			<h4>
			<i class="icon fa fa-ban"></i>
			Alerta!
			</h4>La nueva clave debe tener por lo menos 8 caracteres.</div>';
		break; 
	case 'me5':
		$msj ='<div class="alert alert-danger alert-dismissable">
			<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
			<h4>
			<i class="icon fa fa-ban"></i>
			Alerta!
			</h4>La nueva contraseña de contener al menos 1 letra mayúscula.</div>';
		break;     
	case 'me6':
		$msj ='<div class="alert alert-danger alert-dismissable">
			<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
			<h4>
			<i class="icon fa fa-ban"></i>
			Alerta!
			</h4>La nueva contraseña de contener al menos 1 letra minúscula.</div>';
		break;
	case 'me7':
		$msj ='<div class="alert alert-danger alert-dismissable">
			<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
			<h4>
			<i class="icon fa fa-ban"></i>
			Alerta!
			</h4>La nueva contraseña de contener al menos 1 número.</div>';
		break;
	case 'me8':
		$msj ='<div class="alert alert-danger alert-dismissable">
			<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
			<h4>
			<i class="icon fa fa-ban"></i>
			Alerta!
			</h4>La nueva contraseña de contener al menos 1 de los siguientes caracteres especiales @ # $ %</div>';
	break;
}




?>
<!DOCTYPE html>
<html>
  <head>
    <meta charset="UTF-8">
    <title>:: RUTED| Gestión Ovina y Caprina ::</title>
    <!-- Tell the browser to be responsive to screen width -->
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
    <!-- Bootstrap 3.3.4 -->
    <link href="../../bootstrap/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <!-- Theme style -->
    <link href="../../dist/css/AdminLTE.min.css" rel="stylesheet" type="text/css" />
    <!-- iCheck -->
    <link href="../../plugins/iCheck/square/blue.css" rel="stylesheet" type="text/css" />

		<style>
			/* Reuse login card styles */
			.login-box { max-width:460px; margin:28px auto; }
			.login-logo img{ max-width:100%; height:auto; display:block; margin:0 auto 8px; }
			.login-input{ background:#fff9d6; border:1px solid #e6d89a; border-radius:8px; padding:10px; }
			.btn-login{ background:#234e35; color:#fff; border:none; border-radius:8px; padding:10px 12px; }
			.login-desc{ color:#6b6b6b; }
			#pswd_info{ margin-top:12px; }
		</style>

    <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
        <script src="../../js/html5shiv.min.js"></script>
        <script src="../../js/respond.min.js"></script>
    <![endif]-->
  </head>
  <body class="login-page">
    <div class="login-box">
		
		<?php echo @$msj;?>
		

      <div class="login-box-body">
	  <div class="login-logo">
       <!-- <a href="#"><b>Admin</b>LTE</a>-->
	   <a href="https://global.ruted.net/"><img src="../../dist/img/logo_sirelog.png" alt="Gestión Ovina y Caprina" height="175" width="320"></a>
      </div><!-- /.login-logo -->
	        <p class="login-box-msg">Actualizar clave</p>
       <form action="../../controllers/musuarios_controller" method="post">
				<input id="action" name="action" type="hidden" value="act_clave" /> 
				
				<div class="form-group">
				  <label for="usuario">Usuario</label>
				  <input type="text" class="form-control" id="usuario" value="<?php echo @$username;?>" readonly="true">
				</div>				
				
			  <div class="form-group has-feedback">
				  <input id="password1" name="password1" type="password" class="form-control login-input" placeholder="Nueva contraseña" autocomplete="new-text" />
				<span class="glyphicon glyphicon-lock form-control-feedback"></span>
			  </div>
			  <div class="form-group has-feedback">
				<input id="password2" name="password2" type="password" class="form-control login-input" placeholder="Confirmar nueva contraseña" autocomplete="new-password"/>
				<span class="glyphicon glyphicon-lock form-control-feedback"></span>
			  </div>          
          <div class="row">
            <div class="col-xs-8">
            </div><!-- /.col -->
            <div class="col-xs-4">
			  <button type="submit" class="btn btn-login btn-block btn-flat">Entrar</button>
            </div><!-- /.col -->
          </div>
        </form>

		<div id="pswd_info">
		<h4>La contraseña debe cumplir los siguientes requisitos:</h4>
		<ul id="indicaciones">
			<li id="letter" class="invalid">Al menos <strong>una letra</strong></li>
			<li id="capital" class="invalid">Al menos <strong>una letra mayúscula</strong></li>
			<li id="number" class="invalid">Al menos <strong>un número</strong></li>
			<li id="length" class="invalid">Longitud de almenos <strong>8 caracteres</strong></li>
			<li id="especial" class="invalid">Uso de caracteres especiales<strong> @ # $ %</strong></li>
		</ul>
		</div>
		<div id="pswd_confirm_info">
			<ul><li id="compare" class="invalid">La nueva clave y la clave de confirmación deben ser iguales</strong></li></ul>    
		</div>          
        

      </div><!-- /.login-box-body -->
    </div><!-- /.login-box -->
    
   

    <!-- jQuery 2.1.4 -->
    <script src="../../plugins/jQuery/jQuery-2.1.4.min.js" type="text/javascript"></script>
    <!-- Bootstrap 3.3.2 JS -->
    <script src="../../bootstrap/js/bootstrap.min.js" type="text/javascript"></script>
    <!-- iCheck -->
    <script src="../../plugins/iCheck/icheck.min.js" type="text/javascript"></script>
    <link rel="stylesheet" href="../../css/style_format_pass.css" type="text/css"/>
<script>
$(document).ready(function() {
	
/******************************************************************************/
		$('#password1').keyup(function() {
			// set password variable
			var pswd = $(this).val();
			
			$.post( "../../controllers/validar_contrasena", { action: "validar",clave:pswd}).done(function( data ) {
			 $("#indicaciones").html( data );
			
			});
			
		}).focus(function() {
			$('#pswd_info').show();
		}).blur(function() {
			$('#pswd_info').hide();
		});
		
		
		$('#password2').keyup(function() {
			var pw3 = $(this).val();
			var pswd = $("#password2").val();
			
			if(pswd != ""){
				
				if(pswd === pw3){
				   
					 $('#compare').removeClass('invalid').addClass('valid'); 
				}else{
				   $('#compare').removeClass('valid').addClass('invalid'); 
				}
			}
		}).focus(function() {
			$('#pswd_confirm_info').show();
		}).blur(function() {
			$('#pswd_confirm_info').hide();
		});

/********************************************************************/

});
</script>
  </body>
</html>
