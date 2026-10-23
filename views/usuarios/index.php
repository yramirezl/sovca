<?php
include("../../lib/validar_session.php");
ValidaSession("../login");
VerificarAdmin($_SESSION['rolx']);
?>
<?php include("../layouts/constantes.php") ?>
<?php include_once("../layouts/cabecera.php") ?>

<style>
/* ============================================================
   PANEL DE SUSCRIPCIONES
   ============================================================ */
.suscripciones-admin { border-top:3px solid #e89b27; margin-bottom:12px; }
.suscripciones-admin .box-header { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; padding:8px 12px; }
.suscripciones-admin .box-title { font-weight:700; margin:0; font-size:14px; }
.suscripciones-admin .box-body { padding:10px 12px; }
.suscripciones-admin-alerta { margin:0 0 8px; padding:8px 10px; border-left:4px solid #e89b27; background:#fff7e8; color:#694d1d; font-size:12.5px; }
.suscripciones-admin-alerta.vencida { border-left-color:#c74343; background:#fff1f1; color:#803232; }
.suscripcion-alerta-lista { display:grid; gap:6px; }
.suscripcion-alerta-item { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:7px 10px; border:1px solid #e6e9eb; border-left:3px solid #e89b27; background:#fff; font-size:12.5px; }
.suscripcion-alerta-item.vencida { border-left-color:#c74343; background:#fffafa; }
.suscripcion-alerta-item.programada { border-left-color:#3c8dbc; background:#f5fbfe; }
.suscripcion-alerta-info { min-width:0; }
.suscripcion-alerta-info strong { display:block; color:#263b32; font-size:12.5px; }
.suscripcion-alerta-info small { color:#67757c; font-size:11.5px; }
.suscripcion-alerta-item .btn { flex-shrink:0; }
.suscripcion-alerta-acciones { display:flex; align-items:center; gap:6px; flex-shrink:0; }
.suscripciones-pendientes-toggle { display:flex; align-items:center; gap:8px; width:100%; margin:0 0 6px; text-align:left; padding:6px 10px !important; font-size:12.5px; }
.suscripciones-pendientes-toggle .badge { margin-left:auto; }
.suscripciones-pendientes-body { display:none; padding:0 0 4px; }
.suscripciones-pendientes-body.open { display:block; }
@media(max-width:600px) { .suscripcion-alerta-item { align-items:flex-start; flex-direction:column; } }

/* ============================================================
   VISTA COMPACTA — OVERRIDE AGRESIVO DE AdminLTE
   Se usan !important + selectores de alta especificidad
   ============================================================ */

/* ---- Header bloque CRUD ---- */
.crud-index__header,
.crud-form__header {
    padding: 8px 12px !important;
    margin-bottom: 6px !important;
    min-height: auto !important;
}
.crud-index__header h3,
.crud-form__title {
    font-size: 15px !important;
    margin: 0 0 1px !important;
}
.crud-index__header p,
.crud-form__subtitle {
    font-size: 11.5px !important;
    margin: 0 !important;
}

/* ---- Barra de botones (Agregar / Editar / Inactivar / Reactivar) ---- */
.crud-index__actions,
.crud-toolbar,
.crud-form__actions {
    padding: 6px 12px !important;
    margin-bottom: 4px !important;
}
.crud-index__actions .btn,
.crud-toolbar .btn,
.crud-btn,
.crud-form__actions .btn {
    padding: 5px 11px !important;
    font-size: 12px !important;
    line-height: 1.15 !important;
    min-height: 30px !important;
}

/* ---- Controles DataTables (por página / buscar / info / paginación) ---- */
.dataTables_wrapper .dataTables_length,
.dataTables_wrapper .dataTables_filter {
    padding: 4px 0 !important;
    margin: 0 0 4px !important;
    font-size: 12px !important;
}
.dataTables_wrapper .dataTables_length select {
    padding: 2px 6px !important;
    height: 28px !important;
    font-size: 12px !important;
    line-height: 1 !important;
}
/* ---- Input del buscador: dejar hueco para la lupa ---- */
.dataTables_wrapper .dataTables_filter input {
    padding: 3px 8px 3px 30px !important;   /* ⬅️ 30px a la IZQUIERDA para la lupa */
    height: 28px !important;
    font-size: 12px !important;
    line-height: 1 !important;
    background-position: 8px center !important;   /* icono a 8px del borde */
    background-repeat: no-repeat !important;
    background-size: 14px 14px !important;
    box-sizing: border-box !important;
}
.dataTables_wrapper .dataTables_info,
.dataTables_wrapper .dataTables_paginate {
    padding: 4px 0 0 !important;
    margin: 4px 0 0 !important;
    font-size: 11.5px !important;
}
.dataTables_wrapper .dataTables_paginate .paginate_button {
    padding: 2px 7px !important;
    font-size: 11.5px !important;
    min-width: 24px !important;
    line-height: 1.2 !important;
    margin: 0 1px !important;
}

/* ======= TABLA — OVERRIDE DURO ======= */
table.dataTable,
table.dataTable.table,
.box-body table.dataTable {
    margin: 0 !important;
    border-collapse: collapse !important;
    width: 100% !important;
}

/* Header */
table.dataTable thead th,
table.dataTable.table thead th,
.box-body table.dataTable thead th {
    padding: 6px 8px !important;
    font-size: 11px !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    letter-spacing: .3px !important;
    line-height: 1.15 !important;
    white-space: nowrap !important;
    vertical-align: middle !important;
}

/* Body — el fix del alto */
table.dataTable tbody td,
table.dataTable tbody th,
table.dataTable.table tbody td,
table.dataTable.table tbody th,
.box-body table.dataTable tbody td,
.box-body table.dataTable tbody th {
    padding: 4px 8px !important;
    font-size: 12px !important;
    line-height: 1.15 !important;
    vertical-align: middle !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    height: 28px !important;
    max-height: 28px !important;
    box-sizing: border-box !important;
}

/* Anular el padding de AdminLTE en tablas dentro de .box */
.box-body > .dataTables_wrapper > table.dataTable > tbody > tr > td,
.box-body > .dataTables_wrapper > table.dataTable > tbody > tr > th {
    padding: 4px 8px !important;
    font-size: 12px !important;
}

/* Etiquetas de estado más pequeñas */
table.dataTable tbody td .label,
.box-body table.dataTable tbody td .label {
    padding: 2px 8px !important;
    font-size: 10px !important;
    line-height: 1.4 !important;
    border-radius: 10px !important;
    display: inline-block !important;
}

/* Filtro por permiso de modo offline (columna "Modo offline").
   Es un control del propio listado: filtra en memoria, sin tocar la URL. */
.crud-toolbar__actions .crud-filtro-offline {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-left: 6px;
    padding-left: 10px;
    border-left: 1px solid #e5e7eb;
}
.crud-toolbar__actions .crud-filtro-offline__titulo {
    font-size: 12px;
    font-weight: 600;
    color: #374151;
    white-space: nowrap;
}
.crud-toolbar__actions .crud-filtro-offline__titulo i {
    color: #234e35;
    margin-right: 2px;
}
.crud-toolbar__actions .crud-filtro-offline__grupo {
    display: inline-flex;
    align-items: stretch;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    overflow: hidden;
}
.crud-toolbar__actions .crud-filtro-offline__btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    height: 32px;
    padding: 0 10px;
    font-size: 12px;
    font-weight: 600;
    color: #4b5563;
    background: #ffffff;
    border: 0;
    border-right: 1px solid #e5e7eb;
    cursor: pointer;
}
.crud-toolbar__actions .crud-filtro-offline__btn:last-child {
    border-right: 0;
}
.crud-toolbar__actions .crud-filtro-offline__btn:hover {
    background: #f1f5f9;
}
.crud-toolbar__actions .crud-filtro-offline__btn.is-active {
    color: #ffffff;
    background: #234e35;
}
.crud-toolbar__actions .crud-filtro-offline__btn em {
    font-style: normal;
    font-size: 10px;
    font-weight: 700;
    padding: 1px 6px;
    border-radius: 9px;
    color: #374151;
    background: #e5e7eb;
}
.crud-toolbar__actions .crud-filtro-offline__btn.is-active em {
    color: #ffffff;
    background: rgba(255, 255, 255, 0.28);
}

/* Reducir espacio del wrapper */
.crud-index,
.crud-form.crud-index {
    margin-bottom: 8px !important;
}
.box.crud-index-box,
.box-body.crud-index-box-body {
    padding: 8px 10px !important;
    margin-bottom: 8px !important;
}
</style>

<div class="message"></div>

<!-- ============================================================
     PANEL DE VIGENCIAS
     ============================================================ -->
<div class="box suscripciones-admin" id="suscripciones_admin_panel">
    <div class="box-header with-border">
        <h3 class="box-title"><i class="fa fa-clock-o" style="color:#e89b27;"></i> Vigencias de suscripción</h3>
        <span class="label label-warning" id="suscripciones_admin_total">Revisando...</span>
    </div>
    <div class="box-body">
        <div id="suscripciones_admin_alerta" class="suscripciones-admin-alerta" style="display:none;" role="status" aria-live="polite"></div>
        <div id="suscripciones_admin_proximas" class="suscripcion-alerta-lista" aria-live="polite">
            <span class="text-muted">Consultando vigencias...</span>
        </div>
        <hr style="margin:10px 0;">
        <button type="button" id="toggle_pendientes_configurar" class="btn btn-default btn-sm suscripciones-pendientes-toggle" aria-expanded="false" style="display:none;">
            <i class="fa fa-users"></i> Pendientes por configurar <span class="badge" id="suscripciones_pendientes_total">0</span><i class="fa fa-chevron-down" id="suscripciones_pendientes_icono"></i>
        </button>
        <div id="pendientes_por_configurar" class="suscripciones-pendientes-body">
            <div id="suscripciones_admin_lista" class="suscripcion-alerta-lista"></div>
        </div>
    </div>
</div>

<!-- Modal renovación -->
<div class="modal fade" id="modal_renovar_suscripcion" tabindex="-1" role="dialog" aria-labelledby="modal_renovar_suscripcion_titulo">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="modal_renovar_suscripcion_titulo"><i class="fa fa-refresh"></i> Renovar suscripción</h4>
            </div>
            <div class="modal-body">
                <p id="renovar_suscripcion_usuario" class="text-muted"></p>
                <div class="form-group">
                    <label>Suscripción actual</label>
                    <div class="form-control" style="height:auto; min-height:34px; background:#f5f7f8;" id="renovar_suscripcion_actual">—</div>
                    <small class="help-block">Dato informativo; esta suscripción no se modifica.</small>
                </div>
                <div class="form-group">
                    <label for="renovar_suscripcion_tipo">Nueva suscripción para la renovación</label>
                    <select id="renovar_suscripcion_tipo" class="form-control"><option value="">Cargando opciones...</option></select>
                    <small class="help-block">Puedes mantener la actual o seleccionar otra duración.</small>
                </div>
                <div class="checkbox">
                    <label><input type="checkbox" id="renovar_suscripcion_programar"> Activar automáticamente al vencer la suscripción actual</label>
                </div>
                <p id="renovar_suscripcion_fecha" class="help-block" style="margin:6px 0 0;"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning" id="confirmar_renovar_suscripcion"><i class="fa fa-check"></i> Guardar renovación</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     TABLA DE USUARIOS
     ============================================================ -->
<?php
$crud = [
    'title'    => 'Usuarios del Sistema',
    'subtitle' => 'Gestión de cuentas, roles y suscripciones',
    'icon'     => 'fa-users',
    'theme'    => 'info',

    'ajaxUrl'    => '../../data_json/data_musuarios',
    'controller' => '../../controllers/musuarios_controller',

    'routes' => [
        'add'  => 'frm_registrar',
        'edit' => 'frm_editar',
        'home' => '../inicio/',
    ],

    'showButtons' => ['add', 'edit', 'delete'],
    'buttonLabels' => ['delete' => 'Inactivar'],

    'extraButtonsHtml' => '<a href="importar_demo" class="crud-btn crud-btn--success" style="color:#fff !important;"'
        . ' title="Convertir una cuenta de demostración en cuenta oficial conservando sus datos">'
        . '<i class="fa fa-file-import"></i> <span>Importar cuenta demo</span></a>'
        . '<button id="readd" type="button" class="crud-btn crud-btn--warning"><i class="fa fa-refresh"></i> <span>Reactivar</span></button>'
        . '<span class="crud-filtro-offline" role="group" aria-label="Filtrar usuarios por acceso sin conexión">'
        . '<span class="crud-filtro-offline__titulo"><i class="fa fa-cloud"></i> Modo offline</span>'
        . '<span class="crud-filtro-offline__grupo">'
        . '<button type="button" class="crud-filtro-offline__btn is-active" data-offline=""'
        . ' title="Todas las cuentas del listado">Todos <em id="offline-total">0</em></button>'
        . '<button type="button" class="crud-filtro-offline__btn" data-offline="1"'
        . ' title="Cuentas que pueden usar la app sin conexión (incluye a los usuarios que lo heredan del productor)">'
        . 'Con acceso <em id="offline-con">0</em></button>'
        . '<button type="button" class="crud-filtro-offline__btn" data-offline="0"'
        . ' title="Cuentas sin acceso al modo sin conexión">Sin acceso <em id="offline-sin">0</em></button>'
        . '</span>'
        . '</span>',

    'columns' => [
        ['data' => 'username',         'title' => 'Usuario'],
        ['data' => 'name',             'title' => 'Nombre y Apellido'],
        ['data' => 'email',            'title' => 'Correo'],
        ['data' => 'perfil',           'title' => 'Perfil'],
        ['data' => 'status',           'title' => 'Estatus', 'render' => 'status'],
        ['data' => 'fecha_creacion',   'title' => 'Creado'],
        ['data' => 'fecha_activacion', 'title' => 'Fecha Activación'],
        // Última columna a propósito: las posiciones de las demás no cambian.
        ['data' => 'modo_offline',     'title' => 'Modo offline'],
    ],

    'columnDefs' => [
        ['width' => '12%', 'targets' => [0]],
        ['width' => '17%', 'targets' => [1]],
        ['width' => '17%', 'targets' => [2]],
        ['width' => '11%', 'targets' => [3]],
        ['width' => '8%',  'targets' => [4]],
        ['width' => '11%', 'targets' => [5]],
        ['width' => '11%', 'targets' => [6]],
        ['width' => '13%', 'targets' => [7]],
    ],

    /* ACTIVOS (1) ANTES QUE INACTIVOS (0), luego usuario A→Z */
    'order' => [[4, 'desc'], [0, 'asc']],

    'pageLength' => 10,
    'lengthMenu' => [[10, 25, 50, -1], [10, 25, 50, 'Todos']],

    'searchPlaceholder' => 'Buscar usuario, nombre o correo…',

    /*
     * rowCallbackJs: seteamos data-order con el valor CRUDO de status (0/1).
     * DataTables respeta data-order para el ordenamiento ortogonal → ordena
     * por 1 y 0 reales, NO por el texto "Activo"/"Inactivo".
     *
     * La última columna (Modo offline) se pinta aquí como etiqueta: el
     * listado ya trae el texto (Activo / Heredado / Inactivo) y el detalle
     * para el tooltip, para no tocar el JS compartido de las tablas.
     */
    'rowCallbackJs' => "
        var rawStatus = Number(mData.status);
        \$('td:eq(4)', nRow).attr('data-order', rawStatus);

        if (rawStatus === 0) {
            \$(nRow).find('td').css('color', '#dc2626');
            \$(nRow).css('opacity', '0.85');
        } else {
            \$(nRow).find('td').css('color', '');
            \$(nRow).css('opacity', '');
        }
        \$('td:eq(0)', nRow).css('font-weight', 'bold');

        var labelOffline = String(mData.modo_offline || 'Inactivo');
        var claseOffline = (labelOffline === 'Activo')
            ? 'crud-badge--active'
            : ((labelOffline === 'Heredado') ? 'crud-badge--info' : 'crud-badge--inactive');
        var iconoOffline = (labelOffline === 'Activo')
            ? 'fa-check'
            : ((labelOffline === 'Heredado') ? 'fa-sitemap' : 'fa-times');

        \$('td:eq(7)', nRow)
            .attr('data-order', Number(mData.modo_offline_orden) || 0)
            .html('<span class=\"crud-badge ' + claseOffline + '\" title=\"'
                  + String(mData.modo_offline_detalle || '').replace(/\"/g, '&quot;')
                  + '\"><i class=\"fa ' + iconoOffline + '\"></i> ' + labelOffline + '</span>');
    ",

    'beforeEditJs' => "
        // Las cuentas SUPER USUARIO solo las puede editar otro SUPER USUARIO.
        if (rowData && Number(rowData.rol_id) === 4 && " . (int) $_SESSION['rolx'] . " !== 4) {
            \$.alert({
                title: 'Cuenta protegida',
                content: 'Esta cuenta pertenece a un <strong>SUPER USUARIO</strong>. Solo otro SUPER USUARIO puede editarla.',
                confirmButton: 'Entendido'
            });
            return false;
        }
    ",

    'beforeDeleteJs' => "
        // Las cuentas SUPER USUARIO solo las puede inactivar otro SUPER USUARIO.
        if (rowData && Number(rowData.rol_id) === 4 && " . (int) $_SESSION['rolx'] . " !== 4) {
            \$.alert({
                title: 'Cuenta protegida',
                content: 'Esta cuenta pertenece a un <strong>SUPER USUARIO</strong>. Solo otro SUPER USUARIO puede cambiar su estado.',
                confirmButton: 'Entendido'
            });
            return false;
        }
        return window.confirm(
            '¿Desea inactivar al usuario \"' + (rowData ? (rowData.name || rowData.username) : '') + '\"?'
        );
    ",

    'customScript' => "
        /* ------------------------------------------------
           1) Forzar tipo numérico en columna Estatus
              y re-aplicar el orden después de la init
           ------------------------------------------------ */
        setTimeout(function () {
            if (\$.fn.DataTable && \$.fn.DataTable.isDataTable('table.dataTable')) {
                var dt = \$('table.dataTable').DataTable();

                /* Re-aplicar el orden con la columna status como numérica */
                dt.order([[4, 'desc'], [0, 'asc']]).draw(false);
            }
        }, 120);

        /* ------------------------------------------------
           1.b) Filtro por permiso de MODO SIN CONEXIÓN
                (última columna del listado: Activo / Heredado /
                 Inactivo). Es un filtro interno de la tabla: se aplica
                 en memoria y NO se refleja en la URL.
                OJO: si se agregan columnas antes, ajustar el índice.
           ------------------------------------------------ */
        var COLUMNA_OFFLINE = 7;
        var PATRONES_OFFLINE = { '1': '^(Activo|Heredado)\$', '0': '^Inactivo\$' };

        function aplicarFiltroOffline(valor) {
            var patron = PATRONES_OFFLINE[valor] || '';
            // OJO: crud.table es el objeto jQuery de la tabla (API legacy);
            // la API de DataTables se obtiene con .DataTable().
            crud.table.DataTable().column(COLUMNA_OFFLINE).search(patron, true, false).draw();

            \$('.crud-filtro-offline__btn')
                .removeClass('is-active')
                .filter('[data-offline=\"' + valor + '\"]')
                .addClass('is-active');
        }

        \$('.crud-filtro-offline__btn').on('click', function () {
            aplicarFiltroOffline(\$(this).attr('data-offline') || '');
        });

        /* Contadores por estado: se calculan una sola vez, con TODAS las
           filas del listado (sin contar el filtro actual). */
        crud.table.DataTable().on('xhr.dt', function (evento, ajustes, json) {
            var filas = Array.isArray(json) ? json : ((json && json.data) ? json.data : []);
            var con = 0;
            var sin = 0;

            filas.forEach(function (fila) {
                if (String(fila.modo_offline) === 'Inactivo') {
                    sin++;
                } else {
                    con++;
                }
            });

            \$('#offline-total').text(con + sin);
            \$('#offline-con').text(con);
            \$('#offline-sin').text(sin);
        });

        /* ------------------------------------------------
           2) Botón Reactivar
           ------------------------------------------------ */
        \$('#readd').on('click', function() {
            var id = crud.getSelectedId();
            var rowData = crud.getSelectedRowData();

            if (!id) {
                crud.dialogInfo('¡Seleccione el usuario a reactivar!');
                return;
            }

            if (rowData && Number(rowData.status) === 1) {
                crud.dialogInfo('¡El usuario seleccionado no se encuentra INACTIVO!');
                return;
            }

            \$.confirm({
                title: '¿Desea reactivar este usuario?',
                content: 'El usuario podrá volver a iniciar sesión en el sistema.',
                confirmButton: 'Sí, reactivar',
                cancelButton: 'No',
                confirmButtonClass: 'btn-warning',
                cancelButtonClass: 'btn-default',
                confirm: function() {
                    \$.post('../../controllers/musuarios_controller', {
                        action: 'readd',
                        record: id
                    }).done(function(data) {
                        \$('.message').html(data);
                        setTimeout(function() { window.location.reload(); }, 2000);
                    });
                }
            });
        });
    ",
];

include("../layouts/_crud_index.php");
?>

<script src="../../plugins/confirma/jquery-confirm.min.js"></script>
<script type="text/javascript">
$(document).ready(function() {

    function escaparHtml(texto) {
        return $('<div>').text(texto || '').html();
    }

    var usuarioRenovacion = null;

    function actualizarAyudaRenovacion() {
        if (!usuarioRenovacion) return;
        var programar = $('#renovar_suscripcion_programar').is(':checked');
        var diasRestantes = Number(usuarioRenovacion.dias_restantes_suscripcion);
        var fecha = programar ? usuarioRenovacion.fecha_caduca_suscripcion : '<?php echo date('Y-m-d'); ?>';
        var mensaje = programar
            ? 'Se activará automáticamente el ' + fecha + ', al finalizar la vigencia actual.'
            : 'La nueva vigencia comenzará hoy (' + fecha + ').';
        if (!programar && diasRestantes > 0) mensaje += ' Los días restantes no se acumulan.';
        $('#renovar_suscripcion_fecha').text(mensaje);
    }

    function abrirRenovacion(usuario) {
        usuarioRenovacion = usuario;
        var dias = Number(usuario.dias_restantes_suscripcion);
        $('#renovar_suscripcion_usuario').text(usuario.name + ' (' + usuario.username + ') · Vencimiento actual: ' + usuario.fecha_caduca_suscripcion);
        $('#renovar_suscripcion_actual').text(usuario.suscripcion || 'No configurada');
        $('#renovar_suscripcion_programar').prop('checked', dias > 0).prop('disabled', dias <= 0);
        $('#confirmar_renovar_suscripcion').prop('disabled', true);
        $('#renovar_suscripcion_tipo').html('<option value="">Cargando opciones...</option>');
        $.post('../../controllers/musuarios_controller', { action: 'get_suscripciones' }).done(function(options) {
            $('#renovar_suscripcion_tipo').html(options).val(String(usuario.suscripcion_id || ''));
            $('#confirmar_renovar_suscripcion').prop('disabled', !$('#renovar_suscripcion_tipo').val() || $('#renovar_suscripcion_tipo').val() === '*');
            actualizarAyudaRenovacion();
            $('#modal_renovar_suscripcion').appendTo('body').modal('show');
        }).fail(function() {
            $('.message').html('<div class="alert alert-danger">No se pudieron cargar los tipos de suscripción.</div>');
        });
    }

    function cargarSuscripcionesAdmin() {
        $.getJSON('../../data_json/data_musuarios').done(function(usuarios) {
            var porUsuario = new Map();
            (usuarios || []).forEach(function(usuario) {
                if (Number(usuario.rol_id) === 2 && Number(usuario.status) === 1) porUsuario.set(Number(usuario.id), usuario);
            });
            var productores = Array.from(porUsuario.values());
            var pendientes = productores.filter(function(u) { return !u.tiene_suscripcion; });
            var programadas = productores.filter(function(u) { return !!u.renovacion_programada; });
            var alertas = productores.filter(function(u) {
                return u.tiene_suscripcion && !u.renovacion_programada && Number(u.dias_restantes_suscripcion) <= 30;
            }).sort(function(a, b) { return Number(a.dias_restantes_suscripcion) - Number(b.dias_restantes_suscripcion); });
            var vencidas = alertas.filter(function(u) { return Number(u.dias_restantes_suscripcion) <= 0; }).length;
            var proximas = alertas.length - vencidas;

            var totalAtencion = alertas.length + pendientes.length + programadas.length;
            $('#suscripciones_admin_total').text(totalAtencion + (totalAtencion === 1 ? ' cuenta' : ' cuentas'));
            if (alertas.length) {
                var mensaje = [];
                if (vencidas) mensaje.push(vencidas + (vencidas === 1 ? ' suscripción vencida' : ' suscripciones vencidas'));
                if (proximas) mensaje.push(proximas + (proximas === 1 ? ' próxima a vencer' : ' próximas a vencer en 30 días'));
                $('#suscripciones_admin_alerta').text('Atención: ' + mensaje.join(' y ') + '. Revisa estas cuentas para evitar interrupciones.').toggleClass('vencida', vencidas > 0).show();
            } else {
                var mensajeProgramadas = programadas.length
                    ? programadas.length + (programadas.length === 1 ? ' renovación ya está programada.' : ' renovaciones ya están programadas.')
                    : '';
                $('#suscripciones_admin_alerta').text(mensajeProgramadas).toggleClass('vencida', false).toggle(!!mensajeProgramadas);
            }

            var htmlAlertas = '';
            alertas.forEach(function(u) {
                var dias = Number(u.dias_restantes_suscripcion);
                var detalle = dias < 0 ? 'Vencida hace ' + Math.abs(dias) + ' día(s)' : (dias === 0 ? 'Vence hoy' : 'Vence en ' + dias + ' día(s)');
                htmlAlertas += '<div class="suscripcion-alerta-item' + (dias <= 0 ? ' vencida' : '') + '">';
                htmlAlertas += '<div class="suscripcion-alerta-info"><strong>' + escaparHtml(u.name) + ' <small>(' + escaparHtml(u.username) + ')</small></strong>';
                htmlAlertas += '<small>' + escaparHtml(u.suscripcion || 'Suscripción') + ' · ' + detalle + ' · ' + escaparHtml(u.fecha_caduca_suscripcion) + '</small></div>';
                htmlAlertas += '<div class="suscripcion-alerta-acciones"><button type="button" class="btn btn-xs btn-default btn-editar-suscripcion" data-usuario-id="' + Number(u.id) + '"><i class="fa fa-pencil"></i> Editar usuario</button>';
                htmlAlertas += '<button type="button" class="btn btn-xs btn-warning btn-accion-suscripcion" data-usuario-id="' + Number(u.id) + '"><i class="fa fa-refresh"></i> ' + (dias <= 0 ? 'Renovar' : 'Programar renovación') + '</button>';
                htmlAlertas += '</div></div>';
            });
            programadas.forEach(function(u) {
                htmlAlertas += '<div class="suscripcion-alerta-item programada"><div class="suscripcion-alerta-info"><strong>' + escaparHtml(u.name) + ' <small>(' + escaparHtml(u.username) + ')</small></strong>';
                htmlAlertas += '<small>' + escaparHtml(u.suscripcion || 'Suscripción') + ' · Renovación automática para ' + escaparHtml(u.fecha_programada) + ' · Vencimiento: ' + escaparHtml(u.fecha_caduca_suscripcion) + '</small></div>';
                htmlAlertas += '<button type="button" class="btn btn-xs btn-default btn-editar-suscripcion" data-usuario-id="' + Number(u.id) + '"><i class="fa fa-pencil"></i> Editar usuario</button></div>';
            });
            $('#suscripciones_admin_proximas').html(htmlAlertas || '<span class="text-muted">No hay suscripciones próximas a vencer ni renovaciones programadas.</span>');

            $('#suscripciones_pendientes_total').text(pendientes.length);
            $('#toggle_pendientes_configurar').toggle(pendientes.length > 0);
            if (!pendientes.length) {
                $('#pendientes_por_configurar').removeClass('open').hide();
                $('#toggle_pendientes_configurar').attr('aria-expanded', 'false');
                $('#suscripciones_pendientes_icono').removeClass('fa-chevron-up').addClass('fa-chevron-down');
            }
            var htmlPendientes = '';
            pendientes.forEach(function(u) {
                htmlPendientes += '<div class="suscripcion-alerta-item"><div class="suscripcion-alerta-info"><strong>' + escaparHtml(u.name) + ' <small>(' + escaparHtml(u.username) + ')</small></strong><small>Sin suscripción configurada</small></div>';
                htmlPendientes += '<button type="button" class="btn btn-xs btn-default btn-accion-suscripcion" data-accion="configurar" data-usuario-id="' + Number(u.id) + '"><i class="fa fa-cog"></i> Configurar</button></div>';
            });
            $('#suscripciones_admin_lista').html(htmlPendientes);
        }).fail(function() {
            $('#suscripciones_admin_total').text('No disponible');
            $('#suscripciones_admin_lista').html('<span class="text-muted">No se pudieron cargar las vigencias.</span>');
        });
    }

    $('#toggle_pendientes_configurar').on('click', function() {
        var abierto = $('#pendientes_por_configurar').toggleClass('open').hasClass('open');
        $(this).attr('aria-expanded', abierto ? 'true' : 'false');
        $('#suscripciones_pendientes_icono').toggleClass('fa-chevron-down', !abierto).toggleClass('fa-chevron-up', abierto);
    });

    cargarSuscripcionesAdmin();

    $(document).on('click', '.btn-accion-suscripcion', function() {
        var $boton = $(this);
        var accion = $boton.data('accion');
        var usuarioId = Number($boton.data('usuario-id'));
        if (accion === 'configurar') {
            window.location.href = 'frm_editar?%$%=' + window.btoa(String(usuarioId));
            return;
        }
        $.getJSON('../../data_json/data_musuarios').done(function(usuarios) {
            var usuario = (usuarios || []).find(function(item) { return Number(item.id) === usuarioId; });
            if (usuario) abrirRenovacion(usuario);
            else $('.message').html('<div class="alert alert-warning">No se encontró la cuenta seleccionada.</div>');
        }).fail(function() {
            $('.message').html('<div class="alert alert-danger">No se pudo cargar la cuenta para renovar.</div>');
        });
    });

    $('#renovar_suscripcion_programar').on('change', actualizarAyudaRenovacion);
    $('#renovar_suscripcion_tipo').on('change', function() {
        $('#confirmar_renovar_suscripcion').prop('disabled', !this.value || this.value === '*');
    });

    $('#confirmar_renovar_suscripcion').on('click', function() {
        if (!usuarioRenovacion) return;
        var $boton = $(this);
        $boton.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Guardando...');
        $.post('../../controllers/musuarios_controller', {
            action: 'renovar_suscripcion',
            usuario_id: Number(usuarioRenovacion.id),
            msuscripciones_id: Number($('#renovar_suscripcion_tipo').val()),
            programar_renovacion: $('#renovar_suscripcion_programar').is(':checked') ? 1 : 0
        }, null, 'json').done(function(resp) {
            if (resp && resp.ok) {
                $('#modal_renovar_suscripcion').modal('hide');
                $('.message').html('<div class="alert alert-success">' + escaparHtml(resp.msg) + ' Inicio: ' + escaparHtml(resp.fecha_activa) + ' · Vencimiento: ' + escaparHtml(resp.fecha_caduca) + '.</div>');
                cargarSuscripcionesAdmin();
                setTimeout(function() { window.location.reload(); }, 2000);
            } else {
                $('.message').html('<div class="alert alert-warning">' + escaparHtml(resp && resp.msg ? resp.msg : 'No se pudo renovar la suscripción.') + '</div>');
            }
        }).fail(function() {
            $('.message').html('<div class="alert alert-danger">No se pudo guardar la renovación.</div>');
        }).always(function() {
            $boton.prop('disabled', false).html('<i class="fa fa-check"></i> Guardar renovación');
        });
    });

    $(document).on('click', '.btn-editar-suscripcion', function() {
        var usuarioId = Number($(this).data('usuario-id'));
        window.location.href = 'frm_editar?%$%=' + window.btoa(String(usuarioId));
    });

});
</script>

<?php include_once("../layouts/pie.php") ?>