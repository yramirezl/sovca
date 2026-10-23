<?php
include("../../lib/validar_session.php");
ValidaSession("../login");
?>
<?php include("../layouts/constantes.php"); ?>
<?php include_once("../layouts/cabecera.php"); ?>

<?php
$crud = [
    'title'    => 'Potreros',
    'subtitle' => 'Gestión de potreros y capacidad',
    'icon'     => 'fa-leaf',
    'theme'    => 'success',

    'ajaxUrl'    => '../../data_json/data_mpotreros',
    'controller' => '../../controllers/mpotreros_controller',

    'routes' => ['add' => 'frm_registrar', 'edit' => 'frm_editar', 'home' => '../inicio/'],

    'showButtons' => ['add', 'edit', 'delete'],

    'columns' => [
        ['data' => 'nombre',             'title' => 'Nombre'],
        ['data' => 'hectareas',          'title' => 'Hectáreas'],
        ['data' => 'capacidad_maxima',   'title' => 'Capacidad'],
        ['data' => 'tipo_pasto',         'title' => 'Tipo de Pasto'],
        ['data' => 'cantidad_animales',  'title' => 'Animales'],
        ['data' => 'descripcion',        'title' => 'Descripción'],
    ],

    'columnDefs' => [['width' => '250px', 'targets' => [0]]],

    'order' => [[0, 'asc']],
    'searchPlaceholder' => 'Buscar potrero…',

    'rowCallbackJs' => "
        \$('td:eq(0)', nRow).css('font-weight', 'bold');
        \$('td:eq(1)', nRow).css('text-align', 'center');
        \$('td:eq(2)', nRow).css('text-align', 'center');
        \$('td:eq(4)', nRow).css('text-align', 'center').css('font-weight', 'bold');

        var cap  = parseInt(mData.capacidad_maxima)  || 0;
        var cant = parseInt(mData.cantidad_animales) || 0;
        if (cap > 0 && cant > cap) {
            \$('td:eq(4)', nRow).css('color', '#C0392B');
        }
    ",
];

include("../layouts/_crud_index.php");
?>

<?php include_once("../layouts/pie.php"); ?>