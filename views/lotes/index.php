<?php
include("../../lib/validar_session.php");
ValidaSession("../login");
?>
<?php include("../layouts/constantes.php"); ?>
<?php include_once("../layouts/cabecera.php"); ?>

<?php
$crud = [
    'title'    => 'Lotes',
    'subtitle' => 'Gestión de lotes de animales',
    'icon'     => 'far fa-bookmark',
    'theme'    => 'success',

    'ajaxUrl'    => '../../data_json/data_mlotes',
    'controller' => '../../controllers/mlotes_controller.php',
    'deleteRecordField' => 'id_lote',

    'routes' => ['add' => 'frm_registrar', 'edit' => 'frm_editar', 'home' => '../inicio/'],

    'showButtons' => ['add', 'edit', 'delete'],

    'extraButtonsHtml' => '<button id="repo" type="button" class="crud-btn crud-btn--info"><i class="fa fa-file-pdf-o"></i> <span>Listar</span></button>',

    'columns' => [
        ['data' => 'nombre',      'title' => 'Nombre'],
        ['data' => 'cantidad',    'title' => 'N° de Animales en el Lote'],
        ['data' => 'descripcion', 'title' => 'Detalles / Descripción'],
        ['data' => 'id',          'title' => 'id'],
    ],

    'columnDefs' => [
        ['width' => '350px', 'targets' => [0]],
        ['width' => '120px', 'targets' => [1]],
        ['width' => '500px', 'targets' => [2]],
    ],

    'order' => [[0, 'asc']],
    'searchPlaceholder' => 'Buscar lote…',
    'scrollY' => '240px',

    'rowCallbackJs' => "
        \$('td:eq(0)', nRow).css('font-weight', 'bold');
        \$('td:eq(1)', nRow).css('font-weight', 'bold').css('text-align', 'center');
    ",

    'customScript' => "
        \$('#repo').on('click', function() {
            var value = crud.getSelectedId();
            if (!value) {
                \$.alert({
                    title: '¡Seleccione el registro a mostrar!',
                    content: false,
                    closeIcon: false,
                    confirmButton: 'Entendido',
                    confirmButtonClass: 'btn-success'
                });
                return;
            }

            \$.post('../../controllers/mlotes_controller.php', {
                action: 'buscar_animales',
                id_lote: value
            }).done(function(data) {
                /* 🔧 FIX: el endpoint devuelve JSON-string. Hay que parsearlo. */
                var parsedJson;
                try {
                    parsedJson = (typeof data === 'string') ? \$.parseJSON(data) : data;
                } catch (e) {
                    parsedJson = { cant_reg: 0 };
                }

                var cant_reg = parseInt(parsedJson.cant_reg) || 0;

                if (cant_reg > 0) {
                    var parametros = window.btoa(value);
                    window.open(
                        '../../Reportes/reportes/sovca/ral.php?%\$%=' + parametros,
                        '',
                        'titlebars=0,toolbar=0,scrollbars=0,location=0,statusbar=0,menubar=0,resizable=0,width=450,height=640,top=150,left=500'
                    );
                } else {
                    \$.alert({
                        title: '¡No hay registros asociados a ese Lote!',
                        content: false,
                        closeIcon: false,
                        confirmButton: 'Entendido',
                        confirmButtonClass: 'btn-success'
                    });
                }
            }).fail(function() {
                \$.alert({
                    title: 'Error de conexión',
                    content: 'No se pudo verificar la disponibilidad de datos. Intente nuevamente.',
                    confirmButton: 'Cerrar',
                    confirmButtonClass: 'btn-danger',
                    closeIcon: true
                });
            });
        });
    ",
];

include("../layouts/_crud_index.php");
?>

<?php include_once("../layouts/pie.php"); ?>