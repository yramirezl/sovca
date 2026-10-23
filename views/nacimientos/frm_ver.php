<?php
include("../../lib/validar_session.php");
ValidaSession("../login");

$rol_actual = (int) $_SESSION['rolx'];
?>
<?php include("../layouts/constantes.php") ?>
<?php include_once("../layouts/cabecera.php") ?>

<?php
$crud = [
    'title' => 'Terneros Desincorporados',
    'subtitle' => 'Registro de bajas de terneros',
    'icon' => 'fa-archive',
    'theme' => 'warning',

    'ajaxUrl' => '../../data_json/data_mnacimientos_exc',
    'controller' => null,

    'routes' => [
        'edit' => 'frm_edt_exc',
        'home' => '../nacimientos/',
    ],

    'showButtons' => ['edit'],
    'buttonLabels' => ['edit' => 'Mostrar'],

    'extraButtonsHtml' => '<a href="../nacimientos/" class="crud-btn crud-btn--warning" style="text-decoration:none; color:#ffffff !important;"><i class="fa fa-reply" style="color:#ffffff !important;"></i> <span style="color:#ffffff !important;">Regresar</span></a>',
    'columns' => [
        ['data' => 'codigo_ternero', 'title' => 'Código del Ternero'],
        ['data' => 'codigo_rgb_id_animal_hembra', 'title' => 'Código / HEMBRA'],
        ['data' => 'nro_arete', 'title' => 'Nro Arete / Microchip HEMBRA'],
        ['data' => 'codigo_rgb_id_animal_macho', 'title' => 'Código / MACHO'],
    ],

    'columnDefs' => [
        ['width' => '300px', 'targets' => [0]],
        ['width' => '180px', 'targets' => [1]],
        ['width' => '250px', 'targets' => [2]],
        ['width' => '180px', 'targets' => [3]],
    ],

    'order' => [[0, 'asc']],
    'searchPlaceholder' => 'Buscar ternero…',
    'scrollY' => '350px',

    'rowCallbackJs' => "
        \$('td:eq(0)', nRow).css('font-weight', 'bold');
        \$('td:eq(1)', nRow).css('text-align', 'center');
        \$('td:eq(3)', nRow).css('text-align', 'center');
    ",

    'customScript' => "
        var rolx = " . $rol_actual . ";
        if (rolx == 3) {
            \$('#edit').hide();
        }
    ",
];

include("../layouts/_crud_index.php");
?>

<?php include_once("../layouts/pie.php") ?>