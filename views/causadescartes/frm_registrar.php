<?php

include("../../lib/validar_session.php");

ValidaSession("../login");
//VerificarAdmin($_SESSION['rolx']);

?>

<?php include("../layouts/constantes.php")?>
<?php include_once("../layouts/cabecera.php") ?>

<div class="message"></div>

<form id="form" role="form" enctype="multipart/form-data">

<div  class="row">

			<div class="col-md-4">
				<div class="box-header with-border" tabindex="-1">
				<h3 class="box-title">Datos de la Causa Descarte</h3>

				</div><!-- /.box-header -->
		  		<div class="box box-primary">
						

						<div class="box-body">

						   <div class="form-group-sm">
							  <label for="nombre_causadescarte">Nombre </label> 	   
					          <input class="form-control bbb" id="nombre_causadescarte"  type="text"  placeholder="Nombre" onpaste="return false"  onkeypress="return esnumero_sol(event)">
							  <div style="background-color:#F39C12;color:#fff;text-align:center" id='ms_nombre'><p></p></div>

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

						$(location).attr('href','../causadescartes/');

				}, 1000);
			},

			cancel: function(){

			}
		});				

		});

			$('#save').click( function () {


			var ideco2=parseInt($("#n_accion").val());


			$.confirm({

			title: '¿Está Seguro de Guardar el Registro ?',
			content:false,
			confirmButton: 'Si',
			cancelButton: 'No',
			confirmButtonClass: 'btn-success',
			cancelButtonClass: 'btn-danger',
			
			confirm: function(){

				$.post( "../../controllers/mcausadescartes_controller.php", {
					action: "add",
					nombre_causadescarte:$("#nombre_causadescarte").val()
				}).done(function(data){
					var parsedJson = $.parseJSON(data);
					$(".message").html(parsedJson.mensaje);
					switch(parsedJson.deslizador){
						case "1":
							$('.base').unslider('animate:0');
							tick=0;
							document.getElementById('izquierda').disabled = true;
							$("#izquierda").css("display", "none");
							document.getElementById('derecha').disabled = false;
							$("#derecha").css("display", "block");
						break;
						case "2":
							$('.base').unslider('animate:1');
							tick=1;
							document.getElementById('izquierda').disabled = false;
							$("#izquierda").css("display", "block");
							document.getElementById('derecha').disabled = true;
							$("#derecha").css("display", "none");
						break;

					}
					if(parsedJson.resultado != 'error'){
						$('.base').unslider('animate:0');
							setTimeout(function(){
								$(location).attr('href','./');								

								}, 1500);

					}

				},"json");

									
			},

			cancel: function(){

				}
				});


			});	


 </script>
 <?php include_once("../layouts/pie.php") ?>