<?php
include("../../lib/validar_session.php");
ValidaSession("../login");
?>
<?php include("../layouts/constantes.php"); ?>
<?php include_once("../layouts/cabecera.php"); ?>

<?php
$crud = [
    'title'    => 'Control Sanitario',
    'subtitle' => 'Gestión en lote por grupos',
    'icon'     => 'fa-medkit',
    'theme'    => 'success',

    'ajaxUrl'    => '../../data_json/data_mcontrolsanitarioxlote',
    'controller' => '../../controllers/mcontrolsanitario_controller',
    'deleteAction' => 'delete_X_lote',

    'routes' => [
        'add'  => 'frm_reglote',
        'edit' => 'frm_editlote',
        'home' => '../inicio/',
    ],

    'showButtons' => ['add', 'edit', 'delete'],
    'buttonLabels' => ['edit' => 'Editar'],

    'extraButtonsHtml' => '<button id="gestion_unit" type="button" class="crud-btn crud-btn--warning"><i class="fa fa-cow"></i> <span>Control Individual</span></button>',

    'columns' => [
        ['data' => 'codigo_mcs_lote',   'title' => 'Código'],
        ['data' => 'evento',            'title' => 'Evento'],
        ['data' => 'lote',              'title' => 'Lote Referencia'],
        ['data' => 'fecha_control',     'title' => 'Fecha Control'],
        ['data' => 'fecha_prox_control','title' => 'Próximo Control'],
    ],

    'columnDefs' => [
        ['width' => '130px', 'targets' => [0]],
        ['width' => '350px', 'targets' => [1]],
        ['width' => '250px', 'targets' => [2]],
        ['width' => '180px', 'targets' => [3]],
        ['width' => '180px', 'targets' => [4]],
    ],

    'order' => [[0, 'asc']],
    'dblClickEdit' => true,
    'searchPlaceholder' => 'Buscar control en lote…',
    'scrollY' => '300px',
    'autoRedirectMs' => 360000,

    'rowCallbackJs' => "
        \$('td:eq(0)', nRow).css('font-weight', 'bold');
    ",

    'customScript' => "
        var rolx = " . (int)$_SESSION['rolx'] . ";
        if (rolx == 3) {
            \$('#delete, #edit').hide();
        }
        \$('#gestion_unit').on('click', function() {
            window.location.href = '../controlsanitario/index.php';
        });
    ",
];

include("../layouts/_crud_index.php");
?>

<?php include_once("../layouts/pie.php"); ?>