<?php
include("../../lib/validar_session.php");
ValidaSession("../login");
?>
<?php include("../layouts/constantes.php"); ?>
<?php include_once("../layouts/cabecera.php"); ?>

<?php
$crud = [
    'title'    => 'Productos para el Control Animal',
    'subtitle' => 'Catálogo de productos veterinarios',
    'icon'     => 'fa-flask',
    'theme'    => 'warning',

    'ajaxUrl'    => '../../data_json/data_mproductos',
    'controller' => '../../controllers/mproductos_controller.php',

    'deleteRecordField' => 'id_producto',

    'routes' => ['add' => 'frm_registrar', 'edit' => 'frm_editar', 'home' => '../inicio/'],

    'showButtons' => ['add', 'edit', 'delete'],

    'columns' => [
        ['data' => 'nombre_producto',  'title' => 'Nombre del Producto'],
        ['data' => 'nombre_categoria', 'title' => 'Categoría'],
    ],

    'columnDefs' => [['width' => '350px', 'targets' => [0]]],

    'dblClickEdit' => true,
    'searchPlaceholder' => 'Buscar producto…',
];

include("../layouts/_crud_index.php");
?>

<?php include_once("../layouts/pie.php"); ?>