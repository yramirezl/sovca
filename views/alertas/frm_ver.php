<?php

include("../../lib/validar_session.php");

ValidaSession("../login");

/* El id llega como parámetro estándar del sistema: %$% = base64 del id.
   Se mantiene ?id_alerta= por compatibilidad con enlaces anteriores. */
$id_alerta = 0;
if (isset($_GET['%$%'])) {
	$parametros = explode('=', base64_decode($_GET['%$%']));
	$id_alerta = (int) $parametros[0];
} elseif (isset($_GET['id_alerta'])) {
	$id_alerta = (int) $_GET['id_alerta'];
}

?>
<?php include("../layouts/constantes.php") ?>
<?php include_once("../layouts/cabecera.php") ?>

<link rel="stylesheet" href="../../css/crud-form.css" type="text/css" />

<div class="crud-form">

	<div class="message"></div>

	<header class="crud-form__header">
		<span class="crud-form__icon"><i class="fa fa-bell"></i></span>
		<div>
			<h3 class="crud-form__title">Detalle de la Notificación</h3>
			<p class="crud-form__subtitle">Consulte el animal y el evento que generó esta alerta</p>
		</div>
		<div class="crud-form__header-actions">
			<button id="exit_top" type="button" class="btn btn-warning">
				<i class="fa fa-reply"></i> Regresar
			</button>
		</div>
	</header>

	<form id="form" role="form" enctype="multipart/form-data">
		<input type="hidden" id="action" value="edit">
		<input type="hidden" id="id_alerta">

		<div class="crud-form__grid">

			<!-- ================= Datos del Animal ================= -->
			<div class="crud-form__card">
				<div class="crud-form__card-head">
					<i class="fa fa-tag"></i>
					<h3>Datos del Animal</h3>
				</div>
				<div class="crud-form__card-body">
					<div class="crud-form__field">
						<label for="cod_animal">Código del Animal</label>
						<input class="form-control" id="cod_animal" type="text" readonly="readonly" placeholder="—">
					</div>
					<div class="crud-form__field">
						<label for="nro_arete">Número de Arete</label>
						<input class="form-control" id="nro_arete" type="text" readonly="readonly" placeholder="—">
					</div>
					<div class="crud-form__field">
						<label for="nombre_animal">Nombre del Animal</label>
						<input class="form-control" id="nombre_animal" type="text" readonly="readonly" placeholder="—">
					</div>
				</div>
			</div>

			<!-- ================= Detalles del Evento ================= -->
			<div class="crud-form__card">
				<div class="crud-form__card-head">
					<i class="fa fa-calendar-check-o"></i>
					<h3>Detalles del Evento</h3>
				</div>
				<div class="crud-form__card-body">
					<div class="crud-form__field">
						<label for="evento">Evento</label>
						<textarea class="form-control" id="evento" rows="3" readonly="readonly" placeholder="—"></textarea>
					</div>
					<div class="crud-form__field">
						<label for="fecha_estimada">Fecha Estimada</label>
						<input class="form-control" id="fecha_estimada" type="text" readonly="readonly" placeholder="Día-Mes-Año">
					</div>
				</div>
			</div>

		</div><!-- /.crud-form__grid -->

		<div class="crud-form__actions">
			<button id="exit" type="button" class="btn btn-warning">
				<i class="fa fa-reply"></i> Regresar
			</button>
		</div>
	</form>

</div><!-- /.crud-form -->

<script type="text/javascript">
	$(document).ready(function () {

		/* ------------------------------------------------------------------
		   Carga los datos de la notificación (el controlador la marca como vista)
		   ------------------------------------------------------------------ */
		$.post("../../controllers/malertas_controller", {

			action: "search_alerta",
			id_alerta: <?php echo (int) $id_alerta; ?>

		}).done(function (data) {

			var parsedJson = null;
			try {
				parsedJson = $.parseJSON(data);
			} catch (e) {
				parsedJson = null;
			}

			if (!parsedJson || !parsedJson.id) {
				$('.message').html(
					'<div class="alert alert-warning" style="margin:0 0 16px;">' +
					'<i class="fa fa-exclamation-triangle"></i> No se encontró la notificación solicitada.' +
					'</div>'
				);
				return;
			}

			$("#id_alerta").val(parsedJson.id);
			$("#evento").val(parsedJson.evento);
			$("#cod_animal").val(parsedJson.cod_animal);
			$("#nro_arete").val(parsedJson.nro_arete);
			$("#nombre_animal").val(parsedJson.nombre_animal);
			$("#fecha_estimada").val(parsedJson.fecha_estimada);

		}, "json");

	});
</script>

<?php include_once("../layouts/pie.php") ?>
