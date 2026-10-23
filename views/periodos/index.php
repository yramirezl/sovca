<?php
include("../../lib/validar_session.php");
ValidaSession("../login");
?>
<?php include("../layouts/constantes.php"); ?>
<?php include_once("../layouts/cabecera.php"); ?>

<?php
$crud = [
    'title'    => 'Parametrización de Periodos',
    'subtitle' => 'Periodos de gestación y producción',
    'icon'     => 'fa-calendar',
    'theme'    => 'primary',

    'ajaxUrl'    => '../../data_json/data_mperiodos',
    'controller' => '../../controllers/mperiodos_controller.php',

    'routes' => ['add' => 'frm_registrar', 'edit' => 'frm_editar', 'home' => '../inicio/'],

    'showButtons' => ['add', 'edit', 'delete'],

    'columns' => [
        ['data' => 'nombre',         'title' => 'Nombre'],
        ['data' => 'dias_gestacion', 'title' => 'Cantidad de Días'],
    ],

    'columnDefs' => [['width' => '550px', 'targets' => [0]]],

    'order' => [[0, 'asc']],
    'dblClickEdit' => true,
    'searchPlaceholder' => 'Buscar periodo…',

    /* Roles distintos a 1 y 4 no ven add/delete */
    'customScript' => "
        var rolx = " . (int)$_SESSION['rolx'] . ";
        if (rolx != 1 && rolx != 4) {
            $('#add, #delete').hide();
        }
    ",
];

include("../layouts/_crud_index.php");
?>

<?php include_once("../layouts/pie.php"); ?>