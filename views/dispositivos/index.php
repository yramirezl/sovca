<?php
include("../../lib/validar_session.php");
ValidaSession("../login");

$rol_actual = (int) $_SESSION['rolx'];
if ($rol_actual == 3) {
    header("Location: ../inicio/denegado");
    die();
}
$es_admin_vista = in_array($rol_actual, [1, 4]);
?>
<?php include("../layouts/constantes.php") ?>
<?php include_once("../layouts/cabecera.php") ?>

<link rel="stylesheet" href="../../plugins/confirma/jquery-confirm.min.css" type="text/css" />
<link rel="stylesheet" href="../../css/crud-index.css?v=3" type="text/css" />

<div class="crud-page" data-theme="danger">

    <div class="message"></div>

    <div class="crud-card">

        <header class="crud-header">
            <span class="crud-header__icon"><i class="fa fa-mobile"></i></span>
            <div class="crud-header__text">
                <h3 class="crud-header__title">Dispositivos Conectados</h3>
                <p class="crud-header__subtitle">
                    <?php if ($es_admin_vista): ?>
                        Todos los dispositivos activos de todos los fundos
                    <?php else: ?>
                        Dispositivos activos vinculados a tu fundo
                    <?php endif; ?>
                </p>
            </div>
            <span class="crud-header__badge" id="crud-count-dispositivos">—</span>
        </header>

        <div class="crud-toolbar">
            <div class="crud-toolbar__actions">
                <button id="desvincular" type="button" class="crud-btn crud-btn--danger">
                    <i class="fa fa-chain-broken"></i> <span>Desvincular seleccionado</span>
                </button>
                <button id="recargar" type="button" class="crud-btn crud-btn--success">
                    <i class="fa fa-refresh"></i> <span>Recargar</span>
                </button>
                <a href="historico.php" class="crud-btn crud-btn--ghost" style="text-decoration:none;">
                    <i class="fa fa-history"></i> <span>Ver histórico</span>
                </a>
            </div>
        </div>

        <div style="padding: 14px 24px; background: #fff7ed; border-bottom: 1px solid #fed7aa; color: #7c2d12; font-size: 13px; line-height: 1.5;">
            <i class="fa fa-info-circle" style="color: #d97706;"></i>
            Cada usuario puede tener el modo offline activo en <strong>un solo dispositivo</strong> por fundo.
            Seleccioná una fila y presioná <em>Desvincular seleccionado</em> —
            <strong>los registros sin sincronizar de ese dispositivo se perderán</strong>.
        </div>

        <div class="crud-table-wrap">
            <table id="tabla_dispositivos" class="table crud-table" width="100%">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Dispositivo</th>
                        <?php if ($es_admin_vista): ?>
                            <th>Fundo</th>
                        <?php endif; ?>
                        <th>Modo</th>
                        <th>Activado</th>
                        <th>Última sinc.</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

    </div>
</div>

<!-- jQuery explícito (garantiza la cadena de dependencias) -->
<script src="../../plugins/confirma/jquery-confirm.min.js"></script>
<script type="text/javascript">
$(document).ready(function() {

    var ES_ADMIN = <?php echo $es_admin_vista ? 'true' : 'false'; ?>;

    var columnas = [
        {
            "data": "usuario_nombre",
            "render": function(data, type, row) {
                if (type !== 'display') return data;
                var html = '<strong>' + data + '</strong>';
                if (row.usuario_status === 0) {
                    html += ' <span class="crud-badge crud-badge--inactive" style="font-size:9px;padding:3px 8px;margin-left:6px;">INACTIVO</span>';
                } else if (row.usuario_status === -1) {
                    html += ' <span class="crud-badge crud-badge--warning" style="font-size:9px;padding:3px 8px;margin-left:6px;">HUÉRFANO</span>';
                }
                return html;
            }
        },
        { "data": "nombre_dispositivo" }
    ];

    if (ES_ADMIN) {
        columnas.push({
            "data": "fundo_nombre",
            // MULTIFUNDO: un equipo puede estar vinculado a varios fundos,
            // así que en vez de un nombre se listan todos como etiquetas.
            "render": function(data, type, row) {
                if (type !== 'display') return data;

                var fundos = Array.isArray(row.fundos) ? row.fundos : [];
                if (!fundos.length) {
                    return data ? data : '<span style="color:#94a3b8;">—</span>';
                }

                var html = fundos.map(function(f) {
                    var clase = (f.modo_offline === 'consulta') ? 'crud-badge--info' : 'crud-badge--warning';
                    return '<span class="crud-badge ' + clase + '" style="font-size:9px;padding:3px 8px;margin:1px 3px 1px 0;display:inline-block;">'
                        + (f.fundo_nombre || ('Fundo ' + f.fundo_id))
                        + '</span>';
                }).join('');

                if (fundos.length > 1) {
                    html += '<div style="font-size:10px;color:#64748b;margin-top:2px;">'
                        + fundos.length + ' fundos en este equipo</div>';
                }

                return html;
            },
            "defaultContent": '<span style="color:#94a3b8;">—</span>'
        });
    }

    columnas.push(
        {
            "data": "modo_offline",
            "render": function(data, type, row) {
                if (type !== 'display') return data;
                if (data === 'consulta') {
                    return '<span class="crud-badge crud-badge--info"><i class="fa fa-eye"></i> Consulta</span>';
                }
                if (data === 'mixto') {
                    return '<span class="crud-badge crud-badge--ghost"><i class="fa fa-random"></i> Mixto</span>';
                }
                return '<span class="crud-badge crud-badge--warning"><i class="fa fa-pencil"></i> Gestión</span>';
            }
        },
        { "data": "activado_en" },
        {
            "data": "ultima_sincronizacion",
            "render": function(data, type, row) {
                // Ordena por la marca real, muestra el texto formateado.
                if (type === 'sort' || type === 'type') return row.ultima_sincronizacion_ts || 0;
                return data;
            }
        }
    );

    var tabla = $('#tabla_dispositivos').DataTable({
        "dom": '<"crud-table-top"lf>rt<"crud-table-bottom"ip>',
        "ajax": {
            "url": "../../controllers/mdispositivos_controller.php",
            "type": "POST",
            "data": { action: "listar", filtro: "activos" },
            "dataSrc": "dispositivos"
        },
        "autoWidth": false,
        /* scrollX activo: los anchos en % + bordes de celda pueden exceder el
           contenedor por unos pocos píxeles. Con scrollX DataTables mantiene la
           cabecera sincronizada con el cuerpo; sin él, el cuerpo se desplaza y
           los títulos quedan corridos respecto a sus datos. */
        "scrollX": true,
        "scrollY": "420px",
        "scrollCollapse": false,
        "order": [[ES_ADMIN ? 5 : 4, "desc"]],
        "columns": columnas,
        "columnDefs": [
            { "width": "24%", "targets": 0 },
            { "width": "26%", "targets": 1 },
            { "width": "16%", "targets": ES_ADMIN ? 3 : 2, "className": "text-center" },
            { "width": "17%", "targets": ES_ADMIN ? 4 : 3 },
            { "width": "17%", "targets": ES_ADMIN ? 5 : 4 }
        ],
        "fnRowCallback": function(nRow, mData, iDisplayIndex) {
            $(nRow).attr('data-dispositivo-id', mData.dispositivo_id);
            $('td:first', nRow).css('font-weight', 'bold');
            return nRow;
        },
        "language": {
            "search": '',
            "searchPlaceholder": 'Buscar dispositivo, usuario o fundo…',
            "lengthMenu": '_MENU_ por página',
            "info": 'Mostrando _START_ a _END_ de _TOTAL_',
            "infoEmpty": 'Sin dispositivos activos',
            "infoFiltered": '(filtrado de _MAX_)',
            "zeroRecords": 'No hay dispositivos activos que coincidan',
            "emptyTable": 'No hay dispositivos activos en este momento',
            "loadingRecords": 'Cargando…',
            "processing": 'Procesando…',
            "paginate": { "first": '«', "last": '»', "next": '›', "previous": '‹' }
        },
        "drawCallback": function() {
            try {
                var info = this.api().page.info();
                $('#crud-count-dispositivos').text(
                    info.recordsTotal + ' dispositivo' + (info.recordsTotal !== 1 ? 's' : '') + ' activo' + (info.recordsTotal !== 1 ? 's' : '')
                );
            } catch (e) {}
        }
    });

    // Selección de fila
    $('#tabla_dispositivos tbody').on('click', 'tr', function() {
        if ($(this).hasClass('selected')) {
            $(this).removeClass('selected');
        } else {
            tabla.$('tr.selected').removeClass('selected');
            $(this).addClass('selected');
        }
    });

    // Botón recargar
    $('#recargar').on('click', function() {
        tabla.ajax.reload(null, false);
    });

    // Botón desvincular (usa fila seleccionada)
    $('#desvincular').on('click', function() {

        var $row = tabla.$('tr.selected');
        if (!$row.length) {
            $.confirm({
                title: '¡Seleccione un dispositivo!',
                content: 'Hacé clic en la fila del dispositivo que querés desvincular.',
                confirmButton: 'Entendido',
                cancelButton: false,
                closeIcon: false,
                confirmButtonClass: 'btn-primary'
            });
            return;
        }

        var dispositivoId = $row.attr('data-dispositivo-id');
        var rowData = tabla.row($row).data();
        var nombre  = rowData.nombre_dispositivo;
        var usuario = rowData.usuario_nombre;

        // MULTIFUNDO: el equipo puede estar vinculado a varios fundos.
        var fundos = Array.isArray(rowData.fundos) ? rowData.fundos : [];
        var nombresFundos = fundos.map(function(f) {
            return f.fundo_nombre || ('Fundo ' + f.fundo_id);
        });
        if (!nombresFundos.length && rowData.fundo_nombre) {
            nombresFundos = [rowData.fundo_nombre];
        }

        var detalle = 'Vas a desvincular el dispositivo <strong>"' + nombre + '"</strong> '
            + 'del usuario <strong>' + usuario + '</strong>';
        if (nombresFundos.length === 1) {
            detalle += ' del fundo <strong>' + nombresFundos[0] + '</strong>';
        } else if (nombresFundos.length > 1) {
            detalle += ' de <strong>' + nombresFundos.length + ' fundos</strong>: '
                + nombresFundos.join(', ');
        }
        detalle += '.<br><br>'
            + '<div style="background:#fff3cd; border:1px solid #e6d89a; padding:10px; border-radius:6px; font-size:12px;">'
            + '<i class="fa fa-exclamation-triangle" style="color:#8a5a00;"></i> '
            + '<strong>Advertencia:</strong> si este dispositivo tiene registros sin sincronizar, '
            + 'se perderán para siempre. El usuario dejará de poder sincronizarse desde ese equipo '
            + 'hasta que vuelva a activar el modo offline en otro dispositivo.'
            + '</div>';

        $.confirm({
            title: '¿Desvincular este dispositivo?',
            content: detalle,
            confirmButton: 'Sí, desvincular',
            cancelButton: 'Cancelar',
            confirmButtonClass: 'btn-danger',
            cancelButtonClass: 'btn-default',
            confirm: function() {
                $.post("../../controllers/mdispositivos_controller.php", {
                    action: "desvincular",
                    dispositivo_id: dispositivoId
                }).done(function(data) {
                    var resp = data;
                    if (typeof data === 'string') {
                        try { resp = JSON.parse(data); } catch (e) { resp = { resultado: 'error', mensaje: 'Respuesta inválida.' }; }
                    }
                    if (resp.resultado === 'ok') {
                        $(".message").html(
                            '<div class="alert alert-success alert-dismissable">'
                            + '<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>'
                            + '<h4><i class="fa fa-check"></i> Alerta!</h4>'
                            + resp.mensaje
                            + '</div>'
                        );
                        tabla.ajax.reload(null, false);
                        window.setTimeout(function() { $(".alert").alert('close'); }, 4000);
                    } else {
                        $(".message").html(
                            '<div class="alert alert-danger alert-dismissable">'
                            + '<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>'
                            + '<h4><i class="icon fa fa-ban"></i> Alerta!</h4>'
                            + (resp.mensaje || 'No se pudo desvincular el dispositivo.')
                            + '</div>'
                        );
                    }
                }).fail(function() {
                    $(".message").html(
                        '<div class="alert alert-danger alert-dismissable">'
                        + '<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>'
                        + '<h4><i class="icon fa fa-ban"></i> Alerta!</h4>'
                        + 'Error de comunicación con el servidor.'
                        + '</div>'
                    );
                });
            }
        });
    });

});
</script>

<?php include_once("../layouts/pie.php"); ?>