<?php

include("../../lib/validar_session.php");
ValidaSession("../login");
VerificarAdmin($_SESSION['rolx']);

?>
<?php include("../layouts/constantes.php"); ?>
<?php include_once("../layouts/cabecera.php"); ?>

<?php
$crud = [
    'title'    => 'Data por Fundo',
    'subtitle' => 'Resumen consolidado de actividad por cada fundo registrado',
    'icon'     => 'fa-database',
    'theme'    => 'info',

    'ajaxUrl'    => '../../data_json/data_mdataxfundo',
    'controller' => '',

    'routes' => [
        'home' => '../inicio/',
    ],

    /* Reporte de solo lectura: sin botones de acción */
    'showButtons' => [],

    'columns' => [
        ['data' => 'fundo',           'title' => 'Nombre del Fundo'],
        ['data' => 'can_animales',    'title' => '# Animales'],
        ['data' => 'can_servicios',   'title' => '# Servicios'],
        ['data' => 'can_prod_leche',  'title' => '# Prod/Leche'],
        ['data' => 'can_control',     'title' => '# Control-S'],
        ['data' => 'can_consec_peso', 'title' => '# Consec/Peso'],
        ['data' => 'can_desincorp',   'title' => '# Desincorp.'],
        ['data' => 'cid_rif',         'title' => 'RIF'],
        ['data' => 'usuario',         'title' => 'Usuario'],
        ['data' => 'contacto',        'title' => 'Contacto'],
        ['data' => 'conexion',        'title' => 'Última Conexión'],
    ],

    'columnDefs' => [
        ['width' => '240px', 'targets' => [0]],
        ['width' => '90px',  'targets' => [1]],
        ['width' => '90px',  'targets' => [2]],
        ['width' => '100px', 'targets' => [3]],
        ['width' => '100px', 'targets' => [4]],
        ['width' => '110px', 'targets' => [5]],
        ['width' => '110px', 'targets' => [6]],
        ['width' => '170px', 'targets' => [7]],
        ['width' => '200px', 'targets' => [8]],
        ['width' => '300px', 'targets' => [9]],
        ['width' => '130px', 'targets' => [10]],
    ],

    /* Ordenar por nombre de fundo A→Z */
    'order' => [[0, 'asc']],

    'searchPlaceholder' => 'Buscar fundo, usuario o RIF…',
    'scrollY' => 'calc(100vh - 340px)',

    'rowCallbackJs' => "
        \$('td:eq(0)', nRow).css('font-weight', 'bold');
        \$('td:eq(1)', nRow).css('text-align', 'center');
        \$('td:eq(2)', nRow).css('text-align', 'center');
        \$('td:eq(3)', nRow).css('text-align', 'center');
        \$('td:eq(4)', nRow).css('text-align', 'center');
        \$('td:eq(5)', nRow).css('text-align', 'center');
        \$('td:eq(6)', nRow).css('text-align', 'center');
    ",
];

include("../layouts/_crud_index.php");
?>

<?php include_once("../layouts/pie.php"); ?>