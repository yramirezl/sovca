<?php
include("../../lib/validar_session.php");
ValidaSession("../login");
?>
<?php include("../layouts/constantes.php"); ?>
<?php include_once("../layouts/cabecera.php"); ?>

<link rel="stylesheet" href="../../css/agrupado-lista.css?v=1">

<?php
$crud = [
    'title'    => 'Control Sanitario',
    'subtitle' => 'Gestión unitaria de controles sanitarios',
    'icon'     => 'fa-medkit',
    'theme'    => 'success',

    'ajaxUrl'    => '../../data_json/data_mcontrolsanitario',
    'controller' => '../../controllers/mcontrolsanitario_controller',

    'routes' => ['add' => 'frm_registrar', 'edit' => 'frm_editar', 'home' => '../inicio/'],

    'showButtons' => ['add', 'edit', 'delete'],
    'buttonLabels' => ['edit' => 'Editar'],

    'extraButtonsHtml' => '<button id="gestion_lote" type="button" class="crud-btn crud-btn--warning"><i class="fa fa-sliders-h"></i> <span>Control en Lote</span></button>',

    'columns' => [
        ['data' => 'nombre_animal',          'title' => 'Nombre del Animal'],
        ['data' => 'codigo_rgb_id_animal',   'title' => 'Código del Animal'],
        ['data' => 'nro_arete',              'title' => 'N° Arete / Microchip'],
        ['data' => 'fecha_control',          'title' => 'Fecha Control'],
        ['data' => 'evento',                 'title' => 'Evento'],
        ['data' => 'fecha_prox_control',     'title' => 'Próximo Control'],
    ],

    'columnDefs' => [
        ['width' => '270px', 'targets' => [0]],
        ['width' => '180px', 'targets' => [1]],
        ['width' => '150px', 'targets' => [2]],
        ['width' => '140px', 'targets' => [3]],
        ['width' => '300px', 'targets' => [4]],
        ['width' => '150px', 'targets' => [5]],
    ],

    'order' => [[0, 'asc']],
    'dblClickEdit' => true,
    'searchPlaceholder' => 'Buscar control…',
    'scrollY' => '240px',

    'rowCallbackJs' => "
        \$('td:eq(0)', nRow).css('font-weight', 'bold');
        \$('td:eq(2)', nRow).css('text-align', 'center');
    ",

    'customScript' => "
        var rolx = " . (int)$_SESSION['rolx'] . ";
        if (rolx == 3) {
            \$('#delete, #edit').hide();
        }

        // La vista agrupada (js/controlsanitario-agrupado.js) necesita el
        // objeto crud para devolver el listado a su estado normal y saber
        // si el rol puede editar.
        window.CS_CRUD = crud;
        window.CS_PUEDE_EDITAR = (rolx != 3);

        \$('#gestion_lote').on('click', function() {
            window.location.href = '../controlsanitariomasivo/index.php';
        });
    ",
];

include("../layouts/_crud_index.php");
?>

<!-- Vista agrupada por animal (mismo patrón que Servicios y Producción de Leche) -->
<script src="../../js/controlsanitario-agrupado.js?v=1"></script>

<?php include_once("../layouts/pie.php"); ?>