<?php
include("../../lib/validar_session.php");
ValidaSession("../login");
VerificarAdmin($_SESSION['rolx']);
?>
<?php include("../layouts/constantes.php"); ?>
<?php include_once("../layouts/cabecera.php"); ?>

<?php
$crud = [
    'title'    => 'Sistema de Mensajería Externa',
    'subtitle' => 'Contactos registrados para envíos',
    'icon'     => 'fa-address-book',
    'theme'    => 'info',

    'ajaxUrl'    => '../../data_json/data_mpromocontactos',
    'controller' => '../../controllers/mlotes_controller.php',
    'deleteRecordField' => 'id_lote',

    'routes' => [
        'add'  => 'frm_contact',
        'edit' => 'frm_contact',
        'home' => '../inicio/',
    ],

    'showButtons' => ['add', 'edit', 'delete'],

    'extraButtonsHtml' => '<button id="adj" type="button" class="crud-btn crud-btn--primary"><i class="fa fa-paperclip"></i> <span>Cargar</span></button>',

    'columns' => [
        ['data' => 'name',     'title' => 'Nombres'],
        ['data' => 'correo',   'title' => 'Correo'],
        ['data' => 'telefono', 'title' => 'Teléfono'],
    ],

    'columnDefs' => [['width' => '350px', 'targets' => [0]]],

    'order' => [[0, 'asc']],
    'searchPlaceholder' => 'Buscar contacto…',
    'scrollY' => '240px',

    'rowCallbackJs' => "
        \$('td:eq(0)', nRow).css('font-weight', 'bold');
    ",

    'customScript' => "
        \$('#adj').on('click', function() {
            window.location.href = 'adjuntar';
        });
    ",
];

include("../layouts/_crud_index.php");
?>

<?php include_once("../layouts/pie.php"); ?>