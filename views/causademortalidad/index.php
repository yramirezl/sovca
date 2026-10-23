<?php
include("../../lib/validar_session.php");
ValidaSession("../login");
?>
<?php include("../layouts/constantes.php"); ?>
<?php include_once("../layouts/cabecera.php"); ?>

<?php
$crud = [
    'title'    => 'Causas de Mortalidad',
    'subtitle' => 'Catálogo de causas registradas',
    'icon'     => 'fa-heartbeat',
    'theme'    => 'danger',

    'ajaxUrl'    => '../../data_json/data_mcausamortalidad',
    'controller' => '../../controllers/mcausademortalidad_controller.php',

    'routes' => ['add' => 'frm_registrar', 'edit' => 'frm_editar', 'home' => '../inicio/'],

    'showButtons' => ['add', 'edit', 'delete'],

    'columns' => [
        ['data' => 'nombre', 'title' => 'Nombre'],
         ['data' => 'status', 'title' => 'Estatus', 'render' => 'status'],
    ],

    'order' => [[0, 'asc']],
    'dblClickEdit' => true,
    'searchPlaceholder' => 'Buscar causa…',
];

include("../layouts/_crud_index.php");
?>

<?php include_once("../layouts/pie.php"); ?>