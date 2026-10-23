<?php
include("../../lib/validar_session.php");
ValidaSession("../login");
?>
<?php include("../layouts/constantes.php"); ?>
<?php include_once("../layouts/cabecera.php"); ?>

<?php
$crud = [
    'title'    => 'Categorías de los Animales',
    'subtitle' => 'Catálogo por sexo y rango de edad',
    'icon'     => 'fa-tags',
    'theme'    => 'primary',

    'ajaxUrl'    => '../../data_json/data_mcategorias',
    'controller' => '../../controllers/mcategorias_controller',

    'routes' => [
        'add'  => 'frm_registrar',
        'edit' => 'frm_editar',
        'home' => '../inicio/',
    ],

    'showButtons' => ['add', 'edit', 'delete'],

    'extraButtonsHtml' => '<button id="repo" type="button" class="crud-btn crud-btn--info"><i class="fa fa-file-pdf-o"></i> <span>Listar</span></button>',

    'columns' => [
        ['data' => 'nombre',     'title' => 'Nombre'],
        ['data' => 'sexo',       'title' => 'Sexo'],
        ['data' => 'cantidad',   'title' => 'N° Animales'],
        ['data' => 'edadminima', 'title' => 'Edad Mínima (Meses)'],
        ['data' => 'edadmaxima', 'title' => 'Edad Máxima (Meses)'],
        ['data' => 'id',         'title' => 'id'],
    ],

    'columnDefs' => [
        ['width' => '280px', 'targets' => [0]],
        ['width' => '90px',  'targets' => [1]],
        ['width' => '110px', 'targets' => [2]],
        ['width' => '130px', 'targets' => [3]],
        ['width' => '130px', 'targets' => [4]],
    ],

    'rowCallbackJs' => "
        \$('td:eq(0)', nRow).css('font-weight', 'bold');
        \$('td:eq(1)', nRow).css('text-align', 'center');
        \$('td:eq(2)', nRow).css('text-align', 'center').css('font-weight', 'bold');
    ",

    'searchPlaceholder' => 'Buscar categoría…',

    /* Validación previa: no editar "Estandar" si no es rol 1/4 */
    'beforeEditJs' => "
        var rolx = " . (int)$_SESSION['rolx'] . ";
        var nombre = rowData ? (rowData.nombre || '') : '';
        if ((rolx != 1) && (rolx != 4) && nombre === 'Estandar') {
            crud.dialogInfo('La Categoría Seleccionada No Puede Ser Modificada');
            return false;
        }
        return true;
    ",

    /* Validación previa: no eliminar "Estandar" si no es rol 1/4 */
    'beforeDeleteJs' => "
        var rolx = " . (int)$_SESSION['rolx'] . ";
        var nombre = rowData ? (rowData.nombre || '') : '';
        if ((rolx != 1) && (rolx != 4) && nombre === 'Estandar') {
            crud.dialogInfo('La Categoría Seleccionada No Puede Ser Eliminada');
            return false;
        }
        return true;
    ",

    /* Botón LISTAR + ocultar botones según rol */
    'customScript' => "
        /* Ocultar botones si el rol no es 1 ni 4 */
        var rolx = " . (int)$_SESSION['rolx'] . ";
        if ((rolx != 1) && (rolx != 4)) {
            \$('#add, #edit, #delete').hide();
        }

        /* Botón LISTAR: verifica datos antes de generar PDF */
        \$('#repo').on('click', function () {
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

            \$.post('../../data_json/data_rcat_check', { id_categoria: value })
                .done(function (data) {
                    /* 🔧 FIX: el endpoint devuelve un JSON-string. Hay que parsearlo. */
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
                            '../../Reportes/reportes/sovca/rcat.php?%\$%=' + parametros,
                            '',
                            'titlebars=0,toolbar=0,scrollbars=0,location=0,statusbar=0,menubar=0,resizable=0,width=450,height=640,top=150,left=500'
                        );
                    } else {
                        \$.alert({
                            title: 'Sin datos disponibles',
                            content: 'No hay animales activos que coincidan con los criterios de esta categoría (sexo y rango de edad). Pruebe con otra categoría o registre animales.',
                            confirmButton: 'Entendido',
                            confirmButtonClass: 'btn-success',
                            closeIcon: true,
                            icon: 'fa fa-info-circle',
                            type: 'blue'
                        });
                    }
                })
                .fail(function () {
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