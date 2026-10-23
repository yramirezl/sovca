<?php
include("../../lib/validar_session.php");
ValidaSession("../login");
// VerificarAdmin($_SESSION['rolx']);
?>
<?php include("../layouts/constantes.php"); ?>
<?php include_once("../layouts/cabecera.php"); ?>

<?php
$crud = [
    'title'    => 'Servicios de los Animales',
    'subtitle' => 'Catálogo de tipos de servicio',
    'icon'     => 'fa-truck',
    'theme'    => 'primary',

    'ajaxUrl'    => '../../data_json/data_mtiposervicio',
    'controller' => '../../controllers/mtiposervicios_controller',

    /* Este módulo usa otro nombre de campo al eliminar */
    'deleteRecordField' => 'id_tservicio',

    'routes' => [
        'add'  => 'frm_registrar',
        'edit' => 'frm_editar',
        'home' => '../inicio/',
    ],

    'showButtons' => ['add', 'edit', 'delete'],

    'columns' => [
        ['data' => 'id',     'title' => '#ID'],
        ['data' => 'nombre', 'title' => 'Nombre'],
    ],

    'order' => [[0, 'asc']],

    'idColumnIndex' => 'first',
    'dblClickEdit'  => true,

    'searchPlaceholder' => 'Buscar servicio…',
];

include("../layouts/_crud_index.php");
?>

<?php include_once("../layouts/pie.php"); ?>