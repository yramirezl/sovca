<?php
include("../../lib/validar_session.php");
ValidaSession("../login");
?>
<?php include("../layouts/constantes.php"); ?>
<?php include_once("../layouts/cabecera.php"); ?>

<?php
$crud = [
    'title'    => 'Aptitudes de los Animales',
    'subtitle' => 'Catálogo de aptitudes productivas',
    'icon'     => 'fa-list-ol',
    'theme'    => 'primary',

    'ajaxUrl'    => '../../data_json/data_maptitudes',
    'controller' => '../../controllers/maptitudes_controller.php',

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

    /* El ID está en la primera columna, no la última */
    'idColumnIndex' => 'first',

    /* Doble click sobre la fila abre el formulario de edición */
    'dblClickEdit' => true,

    'searchPlaceholder' => 'Buscar aptitud…',
];

include("../layouts/_crud_index.php");
?>

<?php include_once("../layouts/pie.php"); ?>