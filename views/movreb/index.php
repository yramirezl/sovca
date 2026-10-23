<?php
include("../../lib/validar_session.php");
ValidaSession("../login");
?>
<?php include("../layouts/constantes.php"); ?>
<?php include_once("../layouts/cabecera.php"); ?>

<?php
$crud = [
    'title'    => 'Movimientos de Rebaño',
    'subtitle' => 'Historial de movimientos entre fundos',
    'icon'     => 'fa-exchange',
    'theme'    => 'warning',

    'ajaxUrl'    => '../../data_json/data_mmovimientos',
    'controller' => null,

    'routes' => [
        'add'  => 'frm_registrar',
        'view' => 'frm_ficha',
        'home' => '../inicio/',
    ],

    'showButtons' => ['add', 'view'],
    'buttonLabels' => ['view' => 'Ver detalle'],

    'columns' => [
        ['data' => 'fecha',          'title' => 'Fecha'],
        ['data' => 'tipo',           'title' => 'Tipo'],
        ['data' => 'origen',         'title' => 'Origen'],
        ['data' => 'destino',        'title' => 'Destino'],
        ['data' => 'motivo',         'title' => 'Motivo'],
        ['data' => 'total_animales', 'title' => 'Animales'],
        ['data' => 'revertido',      'title' => 'Estado', 'render' => 'bool'],
    ],

    'order' => [[0, 'desc']],
    'searchPlaceholder' => 'Buscar movimiento…',
    'scrollY' => '300px',
    'autoRedirectMs' => 180000,

    'rowCallbackJs' => "
        \$('td:eq(0)', nRow).css('font-weight', 'bold');
        \$('td:eq(5)', nRow).css('text-align', 'center').css('font-weight', 'bold');
    ",
];

include("../layouts/_crud_index.php");
?>

<?php include_once("../layouts/pie.php"); ?>