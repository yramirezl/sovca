<?php

include("../../lib/validar_session.php");
ValidaSession("../login");
VerificarAcceso($_SESSION['acceso_alerta']);


?>
<?php include("../layouts/constantes.php") ?>
<?php include_once("../layouts/cabecera.php") ?>

<style>
/* ============================================================
   DISTINTIVO DE ESTADO DE LA NOTIFICACIÓN
   ============================================================ */
.al-pill {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-width: 96px;
	padding: 5px 12px;
	border-radius: 999px;
	font-size: 12px;
	font-weight: 800;
	letter-spacing: .06em;
	text-transform: uppercase;
	line-height: 1.3;
	white-space: nowrap;
}

.al-pill--new {
	background: #fef3c7;
	color: #b45309;
	box-shadow: inset 0 0 0 1px #fcd34d;
}

.al-pill--seen {
	background: #dcfce7;
	color: #15803d;
	box-shadow: inset 0 0 0 1px #86efac;
}

/* La descripción del evento puede ser larga: se permite el salto de línea */
.crud-page .crud-table tbody td:nth-child(3) {
	white-space: normal;
}

/* En pantallas angostas el listado se compacta para que quepan
   el código, el evento y el estatus */
@media (max-width: 700px) {
	.crud-page .crud-table thead th,
	.crud-page .crud-table tbody td {
		padding: 12px 12px !important;
		font-size: 13.5px !important;
	}

	.crud-page .crud-table tbody td:first-child {
		font-size: 14px !important;
	}

	.al-pill {
		min-width: 82px;
		padding: 4px 8px;
		font-size: 11px;
		letter-spacing: .04em;
	}
}
</style>

<?php
/* ============================================================
   LISTADO DE NOTIFICACIONES ACTIVAS (estándar crud-index)
   ============================================================ */
$crud = [
	'title'    => 'Notificaciones Activas',
	'subtitle' => 'Eventos del rebaño pendientes de revisión: partos, controles sanitarios y pesajes',
	'icon'     => 'fa-bell',
	'theme'    => 'warning',

	'ajaxUrl'    => '../../data_json/data_malertas',
	'controller' => '../../controllers/malertas_controller',

	'routes' => [
		'view' => 'frm_ver',
		'home' => '../inicio/'
	],

	/* El botón "Mostrar" se dibuja aparte para conservar el ícono de ojo */
	'showButtons'      => [],
	'extraButtonsHtml' => '<button id="ver" type="button" class="crud-btn crud-btn--success">'
		. '<i class="fa fa-eye"></i><span>Mostrar</span></button>',

	'columns' => [
		['data' => 'codigo_animal', 'title' => 'Código del Animal'],
		['data' => 'nombre_animal', 'title' => 'Nombre del Animal'],
		['data' => 'descripcion',   'title' => 'Descripción del Evento'],
		['data' => 'estatus',       'title' => 'Estatus'],
	],

	/* Anchos pensados para que código y estatus queden siempre visibles;
	   la descripción del evento se queda con el resto del espacio */
	'columnDefs' => [
		['width' => '190px', 'targets' => [0]],
		['width' => '230px', 'targets' => [1]],
		['width' => '380px', 'targets' => [2]],
		['width' => '140px', 'targets' => [3], 'className' => 'text-center'],
	],

	'searchPlaceholder' => 'Buscar código, animal o evento…',
	/* Sin alto fijo: la tarjeta crece según la cantidad de notificaciones
	   (normalmente son pocas y no conviene dejar un bloque vacío) */
	'scrollY'           => null,
	'idField'           => 'id',

	/* Si el usuario deja la pantalla abierta, regresa al inicio (2 minutos) */
	'autoRedirectMs' => 120000,

	'rowCallbackJs' => "
		\$('td:eq(0)', nRow).css('font-weight', '700');
		var est = (mData && mData.estatus) ? String(mData.estatus) : '';
		var pill = (est === 'SIN VER') ? 'al-pill al-pill--new' : 'al-pill al-pill--seen';
		\$('td:eq(3)', nRow).html('<span class=\"' + pill + '\">' + est + '</span>');
	",

	/* Doble clic sobre una fila abre el detalle de la notificación */
	'customScript' => '
		$("#tabla").on("dblclick", "tbody tr", function () {
			var id = $(this).attr("data-id");
			if (id) {
				window.location.href = "frm_ver?%$%=" + window.btoa(String(id));
			}
		});
	',
];

include("../layouts/_crud_index.php");
?>

<?php include_once("../layouts/pie.php"); ?>
