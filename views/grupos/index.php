<?php
include("../../lib/validar_session.php");
ValidaSession("../login");
VerificarAdmin($_SESSION['rolx']);
?>
<?php include("../layouts/constantes.php"); ?>
<?php include_once("../layouts/cabecera.php"); ?>

<?php
$crud = [
    'title'    => 'Grupos y Dependencias',
    'subtitle' => 'Grupos, áreas, equipos o dependencias',
    'icon'     => 'fa-users',
    'theme'    => 'primary',

    'ajaxUrl'    => '../../data_json/data_grupos',
    'controller' => '../../controllers/grupos_controller',

    'routes' => ['add' => 'frm_registrar', 'edit' => 'frm_editar', 'home' => '../inicio/'],

    'showButtons' => ['add', 'edit', 'delete'],

    /* El JSON usa 'cid' como campo del id */
    'idField' => 'cid',

    'columns' => [
        ['data' => 'nombre', 'title' => 'Nombre'],
    ],

    'order' => [[0, 'asc']],
    'dblClickEdit' => true,
    'searchPlaceholder' => 'Buscar grupo…',
];

include("../layouts/_crud_index.php");
?>

<?php include_once("../layouts/pie.php"); ?>