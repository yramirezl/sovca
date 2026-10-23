<?php
include("../../lib/validar_session.php");
ValidaSession("../login");
VerificarAdmin($_SESSION['rolx']);
?>
<?php include("../layouts/constantes.php"); ?>
<?php include_once("../layouts/cabecera.php"); ?>

<?php
$crud = [
    'title'    => 'Tipo de Usuario',
    'subtitle' => 'Catálogo de tipos de usuario',
    'icon'     => 'fa-user-tag',
    'theme'    => 'info',

    'ajaxUrl'    => '../../data_json/data_mtipousuarios',
    'controller' => '../../controllers/mtipousuarios_controller',

    'routes' => ['add' => 'frm_registrar', 'edit' => 'frm_editar', 'home' => '../inicio/'],

    'showButtons' => ['add', 'edit', 'delete'],

    'columns' => [
        ['data' => 'nombre', 'title' => 'Nombre de Usuario'],
         ['data' => 'status', 'title' => 'Estatus', 'render' => 'status'],
    ],

    'order' => [[0, 'asc']],
    'dblClickEdit' => true,
    'searchPlaceholder' => 'Buscar tipo…',
];

include("../layouts/_crud_index.php");
?>

<?php include_once("../layouts/pie.php"); ?>