<?php

include("../../lib/validar_session.php");

ValidaSession("../login");
VerificarAdmin($_SESSION['rolx']);
$parametros = base64_decode($_GET["%$%"]);
$valores= explode("=",$parametros);
$cad0=$valores[0]; 
?>

<?php include("../layouts/constantes.php")?>
<?php include_once("../layouts/cabecera.php") ?>

<div class="message"></div>

<form id="form" role="form" enctype="multipart/form-data">
<input type="hidden" id="action" value="edit">
<input type="hidden" id="id_estatusganados">


<div  class="row">

			<div class="col-md-4">
				<div class="box-header with-border" tabindex="-1">
				<h3 class="box-title">Editar Datos</h3>

				</div><!-- /.box-header -->
		  		<div class="box box-primary">
						
				  <div class="box-body">
				<div class="form-group">
				  <label for="nombre_estatuganado">Nombre</label>
				  <input type="text" class="form-control" id="nombre_estatuganado" onkeypress="return esnombre(event);" onblur="alsalir(this.id)">
				  <div style="background-color:#F39C12;color:#fff;text-align:center" id='ms_nombre' ></div>
				</div>
			<div class="box box-primary">
						<div class="box-footer">
					<button id="exit" type="button" class="btn btn-warning pull-left "  ><i class="fa fa-fw fa-reply"></i>Regresar</button>
					<button id="save" type="button" class="btn btn-success pull-right" tabindex="23" ><i class="fa fa-fw fa-save"></i>Guardar</button>					
					<!--<button id="cancelar" type="button" class="btn btn-primary" tabindex="-1"><i class="fa fa-fw fa-times"></i>Cancelar</button>-->
					</div>	
					
					</div>	
						
				</div><!-- /.box -->

			</div>

			
	</div>
</form>


<script language="JavaScript" src="../../plugins/maxLength/maxLength.js"></script>
<script type="text/javascript" src="../../plugins/datepicker/bootstrap-datepicker.js"></script>
<script src="../../plugins/datepicker/locales/bootstrap-datepicker.es.js" charset="UTF-8"></script>
<script src="../../plugins/unslider/unslider-min.js"></script>
<script type="text/javascript" src="../../js/bootstrap-multiselect.js"></script>
<script language="JavaScript" src="../../plugins/mask/jquery.mask.min.js"></script>
<script type="text/javascript" src="../../plugins/confirma/jquery-confirm.min.js"></script>
<script src="../../plugins/balloon/jquery.balloon.min.js"></script>
<script src="../../plugins/leaflet/leaflet.js"></script>
<script src="../../plugins/leaflet/leaflet.label.js"></script>


<script src="../../plugins/select3/bootstrap-select.min.js"></script>
<script src="../../plugins/select3/i18n/defaults-es_ES.min.js"></script>

<link rel="stylesheet" href="../../plugins/confirma/jquery-confirm.min.css" type="text/css"/>
<link rel="stylesheet" href="../../css/style_format_pass.css" type="text/css"/>
<link rel="stylesheet" href="../../plugins/datepicker/datepicker3.css" type="text/css"/>
<link rel="stylesheet" href="../../plugins/unslider/unslider.css">
<link rel="stylesheet" href="../../plugins/leaflet/leaflet.css"/>

<link rel="stylesheet" href="../../plugins/select3/bootstrap-select.min.css">

 <script type="text/javascript">
	$(document).ready(function() {


//cargar datos del formulario ********************************************************************
$.post( "../../controllers/mestatusganados_controller", {

	 action: "search",record:<?php echo intval($cad0); ?>


 }).done(function( data ) {

			var parsedJson = $.parseJSON(data);

		    $("#id_estatusganados").val(parsedJson.id);
			$("#nombre_estatuganado").val(parsedJson.nombre);

},"json");



});

$("#derecha" ).click(function() {

if(tick==0){

	var cont_alert =$('.bbb').filter(function() { return $(this).val() == ""; }).size();
	//var cont_alert = $('.aaa p:contains("") ').size();
//alert(cont_alert);
		document.getElementById('derecha').disabled = true;
		$("#derecha").css("display", "none");
	//if(cont_alert!=0){
		if(cont_alert > 30){	
		//alert('existen campos vacios');

					$.confirm({
						title: '¡existen campos vacios !', // hides the title.
						cancelButton: false, // hides the cancel button.
						confirmButton: true, // hides the confirm button.
						closeIcon: false,
						confirmButton: 'cerrar',
						confirmButtonClass: 'btn-success',
						content: false// hides content block.
					});			

	}else{

		
		$('.base').unslider('animate:1');
		tick=1;
		document.getElementById('izquierda').disabled = false;
		$("#izquierda").css("display", "block");
		

		setTimeout(function() {
			document.body.scrollIntoView({behavior: 'smooth', block: 'start'});
		}, 800);

	}

}else if(tick==1){

	var cont_alert3 =$('.ccc').filter(function() { return $(this).val() == ""; }).size();
	//var cont_alert = $('.aaa p:contains("") ').size();

	//if(cont_alert3!=0){
		if(cont_alert3 > 50){
					$.confirm({
						title: '¡existen campos vacios !', // hides the title.
						cancelButton: false, // hides the cancel button.
						confirmButton: true, // hides the confirm button.
						closeIcon: false,
						confirmButton: 'cerrar',
						confirmButtonClass: 'btn-success',
						content: false// hides content block.
					});	

	}else{

		//$('.base').unslider('animate:2');
		
		//tick=2;
		document.getElementById('derecha').disabled = false;
		$("#derecha").css("display", "block");
		setTimeout(function() {
			document.body.scrollIntoView({behavior: 'smooth', block: 'start'});
		}, 800);			

	}

}

});

		$("#izquierda" ).click(function() {

		if(tick==1){
			
			$('.base').unslider('animate:0');
			tick=0;
			document.getElementById('izquierda').disabled = true;
			$("#izquierda").css("display", "none");
			document.getElementById('derecha').disabled = false;
			$("#derecha").css("display", "block");
			setTimeout(function() {
				document.body.scrollIntoView({behavior: 'smooth', block: 'start'});
			}, 800);

		}else if(tick==2){
			
			$('.base').unslider('animate:1');
			tick=1;
			document.getElementById('derecha').disabled = false;
			$("#derecha").css("display", "block");
			setTimeout(function() {
				document.body.scrollIntoView({behavior: 'smooth', block: 'start'});
			}, 800);

		}

		});
		$("#exit" ).click(function() {

		$.confirm({
			title: '¿Desea Salir?',
			content:false,
			confirmButton: 'Si',
			cancelButton: 'No',
			confirmButtonClass: 'btn-success',
			cancelButtonClass: 'btn-danger',

			confirm: function(){
				
				setTimeout(function(){

						$(location).attr('href','../estatusganados/');

				}, 1000);
			},

			cancel: function(){

			}
		});				

		});


		$("#save" ).click(function() {

				$.post( "../../controllers/mestatusganados_controller", {

					action: $("#action").val(),
					id_estatusganados: $("#id_estatusganados").val(),
					nombre_estatuganado: $("#nombre_estatuganado").val()



				}).done(function( data ) {

					var parsedJson = $.parseJSON(data);
					$(".message").html(parsedJson.mensaje);

					if(parsedJson.resultado != 'alert'){

								setTimeout(function(){

						$(location).attr('href','../estatusganados/');

						}, 3000);

					}

				},"json");

});

			function esnombre(e) {

				k = (document.all) ? e.keyCode : e.which;
				if (k==8 || k==0 || k==13) return true;
				patron = /[A-ZÑa-z\,\ ]/;
				n = String.fromCharCode(k);

							if(patron.test(n)==''){

								document.getElementById('ms_nombre').style.display = 'block';
								document.getElementById("ms_nombre").innerHTML = 'Utilice solo letras .';
									return patron.test(n);

							}else{

								document.getElementById("ms_nombre").innerHTML = '';
								return patron.test(n);

							}

				}
 </script>
 <?php include_once("../layouts/pie.php") ?>