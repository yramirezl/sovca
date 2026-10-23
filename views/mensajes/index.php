<?php
include("../../lib/validar_session.php");
ValidaSession("../login");
VerificarAdmin($_SESSION['rolx']);
?>
<?php include("../layouts/constantes.php"); ?>
<?php include_once("../layouts/cabecera.php"); ?>

<?php
$crud = [
    'title'    => 'Sistema de Mensajería',
    'subtitle' => 'Mensajes internos del sistema para los distintos perfiles de usuario',
    'icon'     => 'fa-envelope',
    'theme'    => 'info',

    'ajaxUrl'    => '../../data_json/data_mmensajes',
    'controller' => '../../controllers/malertas_controller',

    'routes' => [
        'add'  => 'frm_registrar',
        'edit' => 'frm_editar',
        'home' => '../inicio/',
    ],

    'showButtons' => ['add', 'edit', 'delete'],

    'columns' => [
        ['data' => 'titulo_mensaje',  'title' => 'Título del Mensaje'],
        ['data' => 'ganaderia',       'title' => 'Ganadería'],
        ['data' => 'estatus_mensaje', 'title' => 'Estatus'],
    ],

    'columnDefs' => [
        ['width' => '420px', 'targets' => [0]],
        ['width' => '220px', 'targets' => [1]],
        ['width' => '150px', 'targets' => [2]],
    ],

    'order' => [[0, 'asc']],
    'searchPlaceholder' => 'Buscar mensaje…',
    'scrollY' => '240px',

    'rowCallbackJs' => "
        \$('td:eq(0)', nRow).css('font-weight', 'bold');
    ",
];

include("../layouts/_crud_index.php");
?>

<?php include_once("../layouts/pie.php"); ?>