<?php
include("../../lib/validar_session.php");
ValidaSession("../login");
VerificarAdmin($_SESSION['rolx']);
?>
<?php include("../layouts/constantes.php"); ?>
<?php include_once("../layouts/cabecera.php"); ?>

<?php
$crud = [
    'title'    => 'Tipos de Rol',
    'subtitle' => 'Catálogo de roles del sistema',
    'icon'     => 'fa-user-circle',
    'theme'    => 'info',

    'ajaxUrl'    => '../../data_json/data_mroles',
    'controller' => '../../controllers/mroles_controller.php',

    'routes' => ['add' => 'frm_registrar', 'edit' => 'frm_editar', 'home' => '../inicio/'],

    'showButtons' => ['add', 'edit', 'delete'],

    'columns' => [
        ['data' => 'nombre', 'title' => 'Nombre'],
         ['data' => 'status', 'title' => 'Estatus', 'render' => 'status'],
    ],

    'order' => [[0, 'asc']],
    'dblClickEdit' => true,
    'searchPlaceholder' => 'Buscar rol…',
];

include("../layouts/_crud_index.php");
?>

<?php include_once("../layouts/pie.php"); ?>