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
        ['data' => 'nombre',         'title' => 'Nombre del Animal',     'busqueda' => 'palabra'],
        ['data' => 'codigo',         'title' => 'Código',                'busqueda' => 'codigo'],
        ['data' => 'nro_arete',      'title' => 'N° Arete / Microchip',  'busqueda' => 'palabra'],
        ['data' => 'sexo_animal',    'title' => 'Sexo',                  'busqueda' => 'palabra'],
        ['data' => 'estatus_animal', 'title' => 'Estatus',               'busqueda' => 'palabra'],
        ['data' => 'lote',           'title' => 'Lote',                  'busqueda' => 'palabra'],
    ],

    'columnDefs' => [
        ['width' => '300px', 'targets' => [0]],
    ],

    /* El buscador trabaja por campos: un número ya no se compara como trozo
       dentro del CÓDIGO (antes "322" traía al animal con código SOVCA-063-0322
       y al 2322). Ver js/crud-index.js → busquedaPorCampos. */
    'busquedaPorCampos' => true,
    'searchPlaceholder' => 'Buscar por nombre, arete, lote o estatus…',

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

<!-- La ficha se consulta en un panel lateral, no navegando a otra página:
     los formularios quedan para registrar y editar. -->
<link rel="stylesheet" href="../../css/bovs-drawer.css" type="text/css" />
<script src="../../js/bovs-drawer.js"></script>
<script>
$(document).ready(function () {
    /* La ficha se consulta en un panel ancho, sin paginar ni navegar: se abre
       sobre la lista, se lee completa y se cierra. Para ver otro animal se
       selecciona su fila y se vuelve a pulsar "Ver ficha". */
    $('#ver').off('click').on('click', function () {
        var $seleccionada = $('table.dataTable tbody tr.selected');

        if (!$seleccionada.length) {
            $.alert({
                title: 'Seleccione un animal',
                content: 'Haga clic sobre una fila del listado y vuelva a pulsar <b>Ver ficha</b>.'
            });
            return;
        }

        var id = $seleccionada.attr('data-id');
        if (!id) {
            $.alert({ title: 'Sin datos', content: 'No se pudo identificar el animal de la fila.' });
            return;
        }

        BovsDrawer.open({
            title: 'Ficha del animal',
            ficha: true,
            limpio: true,
            url: 'frm_ficha?' + encodeURIComponent('%$%') + '=' + btoa(String(id)) + '&embed=1',
            onLoad: function ($body) {
                /* Los tooltips de Bootstrap se inicializan en cabecera.php solo
                   al cargar la página; el contenido de la ficha llega después
                   por AJAX, así que hay que activarlos aquí. */
                $body.find('[data-toggle="tooltip"]').tooltip({
                    trigger: 'hover focus',
                    container: 'body',
                    placement: 'top',
                    html: false,
                    delay: { show: 120, hide: 80 }
                });
            }
        });
    });
});
</script>

<?php include_once("../layouts/pie.php"); ?>