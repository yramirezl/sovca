<?php
include("../../lib/validar_session.php");
ValidaSession("../login");
?>
<?php include("../layouts/constantes.php"); ?>
<?php include_once("../layouts/cabecera.php"); ?>

<?php
$crud = [
    'title'    => 'Registro de Animales',
    'subtitle' => 'Administración del inventario de animales',
    'icon'     => 'fa-tags',
    'theme'    => 'primary',

    'ajaxUrl'    => '../../data_json/data_manimales',
    'controller' => '../../controllers/manimales_controller',

    'routes' => [
        'add'      => 'frm_registrar',
        'edit'     => 'frm_editar',
        'view'     => 'frm_ficha',
        'import'   => 'frm_importar',
        'download' => '../../Reportes/reportes/data/animales.php',
        'home'     => '../inicio/',
    ],

    'showButtons' => ['add', 'edit', 'view', 'import', 'download', 'delete'],

    /* ⚠️ SIN la columna id — ya no se incluye */
    'columns' => [
        ['data' => 'nombre',         'title' => 'Nombre del Animal'],
        ['data' => 'codigo',         'title' => 'Código'],
        ['data' => 'nro_arete',      'title' => 'N° Arete / Microchip'],
        ['data' => 'sexo_animal',    'title' => 'Sexo'],
        ['data' => 'estatus_animal', 'title' => 'Estatus'],
        ['data' => 'lote',           'title' => 'Lote'],
    ],

    'columnDefs' => [
        ['width' => '300px', 'targets' => [0]],
    ],

    'searchPlaceholder' => 'Buscar por nombre, arete o lote…',

    'rowCallbackJs' => "
        if (mData.lote === 'No tiene Lote Asignado') {
            $('td:eq(5)', nRow).css('color', '#dc2626').css('font-weight', '600');
        } else {
            $('td:eq(5)', nRow).css('font-weight', '600');
        }
        $('td:eq(0)', nRow).css('font-weight', '600');
        $('td:eq(2)', nRow).css('text-align', 'center');
    ",

    /* Validación previa a eliminar: bloquea si el animal ya tiene historial */
    'beforeDeleteJs' => "
        var nombre = (rowData && (rowData.codigo || rowData.nombre)) ? (rowData.codigo || rowData.nombre) : '';
        return $.post('../../controllers/manimales_controller.php', { action: 'check_historial', record: id }, null, 'json')
            .then(function (resp) {
                if (resp && resp.ok && resp.tiene) {
                    // Cerrar automáticamente el diálogo de confirmación de eliminación
                    // si estuviera abierto, para no dejar dos modales superpuestos.
                    jQuery('.jconfirm').remove();
                    jQuery('body').removeClass('jconfirm-noscroll');

                    var items = '';
                    (resp.modulos || []).forEach(function (m) { items += '<li>' + m + '</li>'; });
                    var html = '<p>El animal <b>' + nombre + '</b> ya tiene historial en el sistema:</p>' +
                               '<ul>' + items + '</ul>' +
                               '<p>No se puede eliminar. Para dar de baja al animal use el módulo de <b>Desincorporación</b>.</p>';
                    $.confirm({
                        title: 'No se puede eliminar',
                        content: html,
                        confirmButton: 'Ir a Desincorporación',
                        cancelButton: 'Cerrar',
                        confirmButtonClass: 'btn-success',
                        cancelButtonClass: 'btn-default',
                        confirm: function () { window.location.href = '../desincorporacion/'; }
                    });
                    return false;
                }
                return true;
            }, function () {
                $.confirm({
                    title: 'Error de verificación',
                    content: 'No se pudo verificar el historial del animal. Intente nuevamente.',
                    confirmButton: 'Cerrar',
                    cancelButton: false
                });
                return false;
            });
    ",
];

include("../layouts/_crud_index.php");
?>

<?php include_once("../layouts/pie.php"); ?>