<?php
include("../../lib/validar_session.php");
ValidaSession("../login");
VerificarAdmin($_SESSION['rolx']);
?>
<?php include("../layouts/constantes.php"); ?>
<?php include_once("../layouts/cabecera.php"); ?>

<?php
$crud = [
    'title'    => 'Estados Fisiológicos',
    'subtitle' => 'Catálogo de estados fisiológicos del ganado',
    'icon'     => 'fa-heartbeat',
    'theme'    => 'primary',

    'ajaxUrl'    => '../../data_json/data_mestadosfisiologicos',
    'controller' => '../../controllers/mestadosfisiologicos_controller.php',

    'routes' => [
        'add'  => 'frm_registrar',
        'edit' => 'frm_editar',
        'home' => '../inicio/',
    ],

    'showButtons' => ['add', 'edit', 'delete'],

    'columns' => [
        ['data' => 'id',     'title' => '#ID'],
        ['data' => 'nombre', 'title' => 'Nombre'],
        ['data' => 'status', 'title' => 'Estatus', 'render' => 'status'],
    ],

    'order' => [[0, 'asc']],

    'idColumnIndex' => 'first',
    'dblClickEdit'  => true,

    'searchPlaceholder' => 'Buscar estado…',
];

include("../layouts/_crud_index.php");
?>

<?php include_once("../layouts/pie.php"); ?>