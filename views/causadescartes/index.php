<?php
include("../../lib/validar_session.php");
ValidaSession("../login");
?>
<?php include("../layouts/constantes.php"); ?>
<?php include_once("../layouts/cabecera.php"); ?>

<?php
$crud = [
    'title'    => 'Causas de Descartes',
    'subtitle' => 'Catálogo de causas de descarte',
    'icon'     => 'fa-chain-broken',
    'theme'    => 'warning',

    'ajaxUrl'    => '../../data_json/data_mcausadescartes',
    'controller' => '../../controllers/mcausadescartes_controller.php',

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