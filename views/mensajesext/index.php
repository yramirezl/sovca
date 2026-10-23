<?php
include("../../lib/validar_session.php");
ValidaSession("../login");
VerificarAdmin($_SESSION['rolx']);
?>
<?php include("../layouts/constantes.php"); ?>
<?php include_once("../layouts/cabecera.php"); ?>

<?php
$crud = [
    'title'    => 'Mensajería Externa / Promociones',
    'subtitle' => 'Mensajes promocionales para envío por correo a los contactos',
    'icon'     => 'fa-bullhorn',
    'theme'    => 'info',

    'ajaxUrl'    => '../../data_json/data_mpromomensajes',
    'controller' => '../../controllers/malertas_controller',

    'routes' => [
        'add'  => 'frm_registrar',
        'edit' => 'frm_editar',
        'home' => '../inicio/',
    ],

    'showButtons' => ['add', 'edit', 'delete'],

    'columns' => [
        ['data' => 'titulo_mensaje', 'title' => 'Título del Mensaje'],
        ['data' => 'condicion',      'title' => 'Condición'],
    ],

    'columnDefs' => [
        ['width' => '420px', 'targets' => [0]],
        ['width' => '220px', 'targets' => [1]],
    ],

    'order' => [[0, 'asc']],
    'searchPlaceholder' => 'Buscar promoción…',
    'scrollY' => '240px',

    'rowCallbackJs' => "
        \$('td:eq(0)', nRow).css('font-weight', 'bold');
    ",

    /* Botón extra "E-Mail" (envío de promoción) */
    'extraButtonsHtml' => '<button id="promo" type="button" class="crud-btn crud-btn--primary"><i class="fa fa-envelope-o"></i> <span>E-Mail</span></button>',

    /* Lógica del botón E-Mail: redirige a promo.php con el id en base64 */
    'customScript' => "
        \$('#promo').on('click', function() {
            var id = crud.getSelectedId();
            var rowData = crud.getSelectedRowData();

            if (!id) {
                crud.dialogInfo('¡Seleccione un registro!');
                return;
            }

            var parametros = window.btoa(String(id));
            window.location.href = 'promo?%$%=' + parametros;
        });
    ",
];

include("../layouts/_crud_index.php");
?>

<?php include_once("../layouts/pie.php"); ?>