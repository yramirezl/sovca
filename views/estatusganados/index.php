<?php
include("../../lib/validar_session.php");
ValidaSession("../login");
VerificarAdmin($_SESSION['rolx']);
?>
<?php include("../layouts/constantes.php"); ?>
<?php include_once("../layouts/cabecera.php"); ?>

<?php
$crud = [
    'title'    => 'Estatus Ganados',
    'subtitle' => 'Catálogo de estatus del ganado',
    'icon'     => 'fa-check-circle',
    'theme'    => 'primary',

    'ajaxUrl'    => '../../data_json/data_mestatusganados',
    'controller' => '../../controllers/mestatusganados_controller',

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

    'searchPlaceholder' => 'Buscar estatus…',
];

include("../layouts/_crud_index.php");
?>

<?php include_once("../layouts/pie.php"); ?>