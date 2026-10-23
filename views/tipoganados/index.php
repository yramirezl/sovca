<?php
include("../../lib/validar_session.php");
ValidaSession("../login");
VerificarAdmin($_SESSION['rolx']);
?>
<?php include("../layouts/constantes.php"); ?>
<?php include_once("../layouts/cabecera.php"); ?>

<?php
$crud = [
    'title'    => 'Tipos de Ganado',
    'subtitle' => 'Catálogo de tipos de ganado',
    'icon'     => 'fa-cubes',
    'theme'    => 'primary',

    'ajaxUrl'    => '../../data_json/data_mtipoganados',
    'controller' => '../../controllers/mtipoganados_controller',

    'routes' => [
        'add'  => 'frm_registrar',
        'edit' => 'frm_editar',
        'home' => '../inicio/',
    ],

    'showButtons' => ['add', 'edit', 'delete'],

    'columns' => [
        ['data' => 'id',     'title' => '#ID'],
        ['data' => 'nombre', 'title' => 'Nombre del Ganado'],
         ['data' => 'status', 'title' => 'Estatus', 'render' => 'status'],
    ],

    'order' => [[0, 'asc']],

    'idColumnIndex' => 'first',
    'dblClickEdit'  => true,

    'searchPlaceholder' => 'Buscar tipo de ganado…',
];

include("../layouts/_crud_index.php");
?>

<?php include_once("../layouts/pie.php"); ?>