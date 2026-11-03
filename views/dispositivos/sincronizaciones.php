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
<link rel="stylesheet" href="../../css/crud-index.css?v=4" type="text/css" />

<style>
    /* ---------------- KPIs ---------------- */
    .sync-kpis {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 10px;
        padding: 14px 24px 0;
    }

    .sync-kpi {
        background: #fff;
        border: 1px solid #e5e8eb;
        border-radius: 12px;
        padding: 12px 16px;
        display: flex;
        flex-direction: column;
        gap: 2px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, .04);
        border-left: 4px solid #2f8f4a;
    }

    .sync-kpi .kpi-num {
        font-size: 22px;
        font-weight: 800;
        color: #1b3a24;
        line-height: 1.1;
    }

    .sync-kpi .kpi-lbl {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .35px;
        text-transform: uppercase;
        color: #7b8794;
    }

    .sync-kpi .kpi-det {
        font-size: 11px;
        color: #97a1ac;
    }

    .sync-kpi.is-info {
        border-left-color: #2b7bbb;
    }

    .sync-kpi.is-info .kpi-num {
        color: #1c4f7c;
    }

    .sync-kpi.is-alerta {
        border-left-color: #e67e22;
    }

    .sync-kpi.is-alerta .kpi-num {
        color: #b45309;
    }

    .sync-kpi.is-error {
        border-left-color: #dc2626;
    }

    .sync-kpi.is-error .kpi-num {
        color: #b91c1c;
    }

    /* ---------------- Filtros ---------------- */
    .sync-filtros {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .sync-select {
        height: 34px;
        border: 1px solid #d8dfe4;
        border-radius: 8px;
        padding: 0 10px;
        font-size: 13px;
        color: #33404d;
        background: #fff;
    }

    .sync-check {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 13px;
        font-weight: 600;
        color: #44515e;
        margin: 0;
        cursor: pointer;
    }

    .sync-check input {
        margin: 0;
        cursor: pointer;
    }

    /* ---------------- Celda "dónde falló" ---------------- */
    .sync-fallo-txt {
        display: block;
        font-size: 11px;
        color: #7c2d12;
        margin-top: 3px;
        max-width: 260px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .sync-dev-id {
        display: block;
        font-size: 10px;
        color: #94a3b8;
        font-family: monospace;
        margin-top: 2px;
    }

    .sync-num {
        font-weight: 800;
        font-size: 14px;
        color: #1e293b;
    }

    .sync-num-sub {
        display: block;
        font-size: 10px;
        color: #94a3b8;
        font-weight: 600;
    }

    .sync-btn-detalle {
        border: 1px solid #cbd5e1;
        background: #fff;
        color: #1c4f7c;
        border-radius: 8px;
        padding: 5px 10px;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
        white-space: nowrap;
    }

    .sync-btn-detalle:hover {
        background: #eff6ff;
        border-color: #93c5fd;
    }

    /* Tabla fija: los anchos en % se respetan y el texto largo (mensajes de
       error) se recorta en lugar de estirar la tabla. */
    #tabla_sync {
        table-layout: fixed;
        width: 100%;
    }

    #tabla_sync th,
    #tabla_sync td {
        overflow: hidden;
        word-wrap: break-word;
    }

    /* El nombre del equipo y del usuario deben poder ocupar dos líneas:
       el CSS general de las tablas CRUD fuerza una sola línea con puntos
       suspensivos, que aquí recortaba "Chrome en Android — fa92". */
    #tabla_sync tbody td {
        white-space: normal;
    }

    /* ---------------- Modal de detalle ---------------- */
    .sync-modal {
        position: fixed;
        top: 0;
        right: 0;
        bottom: 0;
        left: 0;
        z-index: 9999;
        display: none;
    }

    .sync-modal.is-open {
        display: block;
    }

    .sync-modal__backdrop {
        position: absolute;
        top: 0;
        right: 0;
        bottom: 0;
        left: 0;
        background: rgba(15, 23, 42, .55);
    }

    .sync-modal__box {
        position: relative;
        margin: 40px auto;
        max-width: 1000px;
        width: calc(100% - 32px);
        max-height: calc(100vh - 80px);
        background: #fff;
        border-radius: 14px;
        box-shadow: 0 24px 60px rgba(15, 23, 42, .35);
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .sync-modal__head {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 16px 20px;
        border-bottom: 1px solid #e5e8eb;
        background: #f8fafc;
    }

    .sync-modal__head h4 {
        margin: 0;
        font-size: 16px;
        font-weight: 800;
        color: #1e293b;
    }

    .sync-modal__head p {
        margin: 2px 0 0;
        font-size: 12px;
        color: #64748b;
    }

    .sync-modal__cerrar {
        margin-left: auto;
        border: none;
        background: transparent;
        font-size: 22px;
        line-height: 1;
        color: #64748b;
        cursor: pointer;
        padding: 2px 6px;
    }

    .sync-modal__cerrar:hover {
        color: #0f172a;
    }

    .sync-modal__body {
        padding: 14px 20px 20px;
        overflow-y: auto;
        background: #f8fafc;
    }

    .sync-evento {
        background: #fff;
        border: 1px solid #e5e8eb;
        border-left: 4px solid #2f8f4a;
        border-radius: 10px;
        padding: 10px 14px;
        margin-bottom: 8px;
    }

    .sync-evento.is-parcial {
        border-left-color: #e67e22;
    }

    .sync-evento.is-error {
        border-left-color: #dc2626;
    }

    .sync-evento__top {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        font-size: 12px;
        color: #64748b;
    }

    .sync-evento__fecha {
        font-weight: 800;
        color: #1e293b;
        font-size: 12.5px;
    }

    .sync-evento__resumen {
        margin-top: 4px;
        font-size: 12.5px;
        color: #33404d;
    }

    .sync-evento__fallos {
        margin: 8px 0 0;
        padding: 8px 10px;
        list-style: none;
        background: #fff7ed;
        border: 1px solid #fed7aa;
        border-radius: 8px;
    }

    .sync-evento__fallos li {
        font-size: 12px;
        color: #7c2d12;
        padding: 3px 0;
        border-bottom: 1px dashed #fed7aa;
    }

    .sync-evento__fallos li:last-child {
        border-bottom: none;
    }

    .sync-evento__fallos strong {
        color: #431407;
    }

    .sync-vacio {
        text-align: center;
        color: #94a3b8;
        font-size: 13px;
        padding: 26px 10px;
    }

    @media (max-width: 1200px) {
        .sync-kpis {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    @media (max-width: 760px) {
        .sync-kpis {
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            padding: 10px 12px 0;
        }

        .sync-kpi .kpi-num {
            font-size: 18px;
        }
    }
</style>

<div class="crud-page" data-theme="info">

    <div class="message"></div>

    <div class="crud-card">

        <header class="crud-header">
            <span class="crud-header__icon"><i class="fa fa-exchange"></i></span>
            <div class="crud-header__text">
                <h3 class="crud-header__title">Resumen de Sincronización</h3>
                <p class="crud-header__subtitle">
                    <?php if ($es_admin_vista): ?>
                        Actividad del modo offline de todos los fundos: qué enviaron los equipos y dónde falló
                    <?php else: ?>
                        Actividad del modo offline de los equipos de tu fundo
                    <?php endif; ?>
                </p>
            </div>
            <span class="crud-header__badge" id="crud-count-equipos">—</span>
        </header>

        <div class="crud-toolbar">
            <div class="crud-toolbar__actions">
                <div class="sync-filtros">
                    <label for="sync-periodo" style="margin:0;font-size:11px;font-weight:700;letter-spacing:.35px;text-transform:uppercase;color:#7b8794;">Período</label>
                    <select id="sync-periodo" class="sync-select">
                        <option value="7">Últimos 7 días</option>
                        <option value="30" selected>Últimos 30 días</option>
                        <option value="90">Últimos 90 días</option>
                        <option value="0">Todo el histórico</option>
                    </select>
                    <label class="sync-check">
                        <input type="checkbox" id="sync-solo-fallos">
                        Solo equipos con fallos
                    </label>
                </div>
                <button id="recargar" type="button" class="crud-btn crud-btn--success">
                    <i class="fa fa-refresh"></i> <span>Recargar</span>
                </button>
            </div>
            <div class="crud-toolbar__right">
                <a href="index.php" class="crud-btn crud-btn--ghost" style="text-decoration:none;">
                    <i class="fa fa-arrow-left"></i> <span>Volver a dispositivos</span>
                </a>
            </div>
        </div>

        <div class="sync-kpis">
            <div class="sync-kpi is-info">
                <span class="kpi-num" id="kpi-eventos">—</span>
                <span class="kpi-lbl">Sincronizaciones</span>
                <span class="kpi-det" id="kpi-eventos-det">&nbsp;</span>
            </div>
            <div class="sync-kpi">
                <span class="kpi-num" id="kpi-equipos">—</span>
                <span class="kpi-lbl">Equipos con actividad</span>
                <span class="kpi-det" id="kpi-equipos-det">&nbsp;</span>
            </div>
            <div class="sync-kpi is-alerta">
                <span class="kpi-num" id="kpi-fallos">—</span>
                <span class="kpi-lbl">Sincronizaciones con fallos</span>
                <span class="kpi-det" id="kpi-fallos-det">&nbsp;</span>
            </div>
            <div class="sync-kpi is-error">
                <span class="kpi-num" id="kpi-rechazados">—</span>
                <span class="kpi-lbl">Registros rechazados</span>
                <span class="kpi-det" id="kpi-rechazados-det">&nbsp;</span>
            </div>
            <div class="sync-kpi is-alerta">
                <span class="kpi-num" id="kpi-conflictos">—</span>
                <span class="kpi-lbl">Conflictos</span>
                <span class="kpi-det">Registros que chocaron con el servidor</span>
            </div>
        </div>

        <div style="padding: 14px 24px; background: #eff6ff; border-bottom: 1px solid #bfdbfe; color: #1e3a8a; font-size: 13px; line-height: 1.5;">
            <i class="fa fa-info-circle"></i>
            Cada equipo reporta su sincronización al enviar o recibir datos.
            Presioná <strong>Ver detalle</strong> para ver la línea de tiempo y <strong>en qué registro falló</strong>
            cada envío (módulo, código del animal o documento y el motivo que devolvió el servidor).
        </div>

        <div class="crud-table-wrap">
            <table id="tabla_sync" class="table crud-table" width="100%">
                <thead>
                    <tr>
                        <th>Dispositivo</th>
                        <th>Usuario / Fundo</th>
                        <th>Sincronizaciones</th>
                        <th>Fallos</th>
                        <th>Último fallo · dónde falló</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

    </div>
</div>

<!-- Modal de detalle (fuera del .content-wrapper para que el backdrop no lo tape) -->
<div class="sync-modal" id="sync-modal" aria-hidden="true">
    <div class="sync-modal__backdrop" data-cerrar="1"></div>
    <div class="sync-modal__box" role="dialog" aria-modal="true" aria-labelledby="sync-modal-titulo">
        <div class="sync-modal__head">
            <div>
                <h4 id="sync-modal-titulo">Detalle de sincronizaciones</h4>
                <p id="sync-modal-subtitulo">&nbsp;</p>
            </div>
            <button type="button" class="sync-modal__cerrar" data-cerrar="1" aria-label="Cerrar">&times;</button>
        </div>
        <div class="sync-modal__body" id="sync-modal-body">
            <div class="sync-vacio">Cargando…</div>
        </div>
    </div>
</div>

<!-- jQuery explícito (garantiza la cadena de dependencias) -->
<script src="../../plugins/confirma/jquery-confirm.min.js"></script>
<script type="text/javascript">
$(document).ready(function () {

    var PERIODO = 30;
    var SOLO_FALLOS = false;

    var MODO_HTML = {
        gestion: '<span class="crud-badge crud-badge--warning"><i class="fa fa-pencil"></i> Gestión</span>',
        consulta: '<span class="crud-badge crud-badge--info"><i class="fa fa-eye"></i> Consulta</span>',
        mixto: '<span class="crud-badge crud-badge--ghost"><i class="fa fa-random"></i> Mixto</span>'
    };

    function esc(valor) {
        return String(valor === null || valor === undefined ? '' : valor)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function num(valor) {
        var n = Number(valor || 0);
        return n.toLocaleString('es-VE');
    }

    function cortoUuid(uuid) {
        if (!uuid) return '';
        return String(uuid).replace(/-/g, '').slice(0, 8);
    }

    /* ============================================================
       COLUMNAS
       ============================================================ */
    var columnas = [
        {
            "data": "nombre_dispositivo",
            "render": function (data, type, row) {
                if (type !== 'display') return data;
                return '<strong>' + esc(data) + '</strong>'
                    + (row.dispositivo_id
                        ? '<span class="sync-dev-id">' + esc(cortoUuid(row.dispositivo_id)) + '</span>'
                        : '<span class="sync-dev-id">sin identificador</span>')
                    + '<span class="sync-num-sub">Últ. sinc.: ' + esc(row.ultima) + '</span>';
            }
        },
        {
            "data": "usuario_nombre",
            "render": function (data, type, row) {
                if (type !== 'display') return data;
                var html = '<strong>' + esc(data) + '</strong>';

                if (row.fundos_nombres) {
                    html += '<span style="display:block;margin-top:2px;">'
                        + String(row.fundos_nombres).split(', ').map(function (f) {
                            return '<span class="crud-badge crud-badge--info" style="font-size:9px;padding:2px 7px;margin:1px 3px 1px 0;display:inline-block;">'
                                + esc(f) + '</span>';
                        }).join('')
                        + '</span>';
                }

                if (row.modo_offline) {
                    html += '<span style="display:block;margin-top:2px;">'
                        + String(row.modo_offline).split(', ').map(function (m) {
                            return MODO_HTML[m] || ('<span class="crud-badge crud-badge--ghost">' + esc(m) + '</span>');
                        }).join(' ')
                        + '</span>';
                }

                return html;
            }
        },
        {
            "data": "total",
            "className": "text-center",
            "render": function (data, type, row) {
                if (type !== 'display') return Number(data || 0);
                return '<span class="sync-num">' + num(row.total) + '</span>'
                    + '<span class="sync-num-sub">' + num(row.exitos) + ' registro(s)</span>';
            }
        },
        {
            "data": "fallos",
            "className": "text-center",
            "render": function (data, type, row) {
                if (type !== 'display') return Number(data || 0);
                var fallos = Number(row.fallos || 0);
                if (!fallos) {
                    return '<span class="crud-badge crud-badge--active"><i class="fa fa-check"></i> 0</span>';
                }
                var detalle = [];
                if (Number(row.items_error || 0) > 0) detalle.push(num(row.items_error) + ' rechazado(s)');
                if (Number(row.conflictos || 0) > 0) detalle.push(num(row.conflictos) + ' conflicto(s)');
                return '<span class="crud-badge crud-badge--no"><i class="fa fa-exclamation-triangle"></i> ' + num(fallos) + '</span>'
                    + '<span class="sync-num-sub">' + (detalle.length ? detalle.join(' · ') : 'evento con error') + '</span>';
            }
        },
        {
            "data": "fallo_modulo_etiqueta",
            "render": function (data, type, row) {
                if (type === 'sort' || type === 'type') return row.ultimo_fallo_ts || 0;
                if (!row.fallo_modulo_etiqueta) {
                    return '<span style="color:#94a3b8;">Sin fallos</span>';
                }
                var accion = row.fallo_accion ? String(row.fallo_accion).toUpperCase() : '';
                var html = '<span style="font-weight:700;color:#b91c1c;font-size:12.5px;">'
                    + esc(row.ultimo_fallo) + '</span> '
                    + '<span class="crud-badge crud-badge--warning">' + esc(row.fallo_modulo_etiqueta) + '</span>';
                if (accion) {
                    html += ' <span style="font-size:10px;font-weight:700;color:#94a3b8;">' + esc(accion) + '</span>';
                }
                if (row.fallo_mensaje) {
                    var texto = (row.fallo_referencia ? row.fallo_referencia + ' — ' : '') + row.fallo_mensaje;
                    html += '<span class="sync-fallo-txt" title="' + esc(texto) + '">'
                        + esc(texto) + '</span>';
                }
                return html;
            }
        },
        {
            "data": null,
            "orderable": false,
            "className": "text-center",
            "render": function (data, type, row) {
                return '<button type="button" class="sync-btn-detalle" data-dispositivo="'
                    + esc(row.dispositivo_id) + '" data-usuario="' + esc(row.musuarios_id) + '">'
                    + '<i class="fa fa-list-ul"></i> Ver detalle</button>';
            }
        }
    ];

    var idxFallo = 4;

    var tabla = $('#tabla_sync').DataTable({
        "dom": '<"crud-table-top"lf>rt<"crud-table-bottom"ip>',
        "ajax": {
            "url": "../../controllers/mdispositivos_controller.php",
            "type": "POST",
            "data": function (d) {
                d.action = 'resumen_sync';
                d.dias = PERIODO;
                d.solo_fallos = SOLO_FALLOS ? '1' : '0';
            },
            "dataSrc": function (json) {
                pintarKpis(json && json.totales ? json.totales : {});
                if (json && json.resultado === 'error') {
                    mostrarMensaje(json.mensaje || 'No se pudo cargar el resumen.', 'error');
                    return [];
                }
                return (json && json.dispositivos) ? json.dispositivos : [];
            }
        },
        "autoWidth": false,
        "scrollX": false,
        "scrollY": "420px",
        "scrollCollapse": true,
        "order": [[idxFallo, "desc"]],
        "columns": columnas,
        "columnDefs": [
            { "width": "22%", "targets": 0 },
            { "width": "20%", "targets": 1 },
            { "width": "12%", "targets": 2, "className": "text-center" },
            { "width": "11%", "targets": 3, "className": "text-center" },
            { "width": "24%", "targets": 4 },
            { "width": "11%", "targets": 5, "orderable": false }
        ],
        "fnRowCallback": function (nRow, mData) {
            $('td:first', nRow).css('font-weight', 'bold');
            if (Number(mData.fallos || 0) > 0) {
                $(nRow).css('background', '#fffdf7');
            }
            return nRow;
        },
        "language": {
            "search": '',
            "searchPlaceholder": 'Buscar equipo, usuario o fundo…',
            "lengthMenu": '_MENU_ por página',
            "info": 'Mostrando _START_ a _END_ de _TOTAL_',
            "infoEmpty": 'Sin sincronizaciones en el período',
            "infoFiltered": '(filtrado de _MAX_)',
            "zeroRecords": 'No hay equipos que coincidan',
            "emptyTable": 'Todavía no hay sincronizaciones registradas',
            "loadingRecords": 'Cargando…',
            "processing": 'Procesando…',
            "paginate": { "first": '«', "last": '»', "next": '›', "previous": '‹' }
        },
        "drawCallback": function () {
            try {
                var info = this.api().page.info();
                $('#crud-count-equipos').text(
                    info.recordsTotal + ' equipo' + (info.recordsTotal !== 1 ? 's' : '')
                );
            } catch (e) { }
        }
    });

    /* ============================================================
       KPIs
       ============================================================ */
    function pintarKpis(t) {
        var eventos = Number(t.eventos || 0);
        var equipos = Number(t.equipos || 0);
        var fallos = Number(t.fallos || 0);
        var conFallo = Number(t.equipos_con_fallo || 0);
        var rechazados = Number(t.items_error || 0);
        var conflictos = Number(t.conflictos || 0);

        $('#kpi-eventos').text(num(eventos));
        $('#kpi-eventos-det').text(PERIODO > 0 ? 'en los últimos ' + PERIODO + ' días' : 'en todo el histórico');

        $('#kpi-equipos').text(num(equipos));
        $('#kpi-equipos-det').text(equipos ? num(equipos - conFallo) + ' sin fallos' : 'sin actividad');

        $('#kpi-fallos').text(num(fallos));
        $('#kpi-fallos-det').text(fallos ? 'en ' + num(conFallo) + ' equipo(s)' : 'ninguna sincronización falló');

        $('#kpi-rechazados').text(num(rechazados));
        $('#kpi-rechazados-det').text(rechazados ? 'revisá el detalle por equipo' : 'ningún registro rechazado');

        $('#kpi-conflictos').text(num(conflictos));
    }

    /* ============================================================
       MODAL DE DETALLE
       ============================================================ */
    var modal = $('#sync-modal').appendTo('body');
    var ultimoBotonDetalle = null;

    function abrirModal() {
        modal.addClass('is-open').attr('aria-hidden', 'false');
        $('body').css('overflow', 'hidden');
    }

    function cerrarModal() {
        modal.removeClass('is-open').attr('aria-hidden', 'true');
        $('body').css('overflow', '');
        ultimoBotonDetalle = null;
    }

    modal.on('click', '[data-cerrar]', cerrarModal);

    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && modal.hasClass('is-open')) cerrarModal();
    });

    $(document).on('click', '.sync-btn-detalle', function () {
        var boton = $(this);
        ultimoBotonDetalle = boton;
        var dispositivo = boton.attr('data-dispositivo') || '';
        var usuario = boton.attr('data-usuario') || '0';
        var fila = tabla.row(boton.closest('tr')).data() || {};

        $('#sync-modal-titulo').text('Detalle · ' + (fila.nombre_dispositivo || 'Equipo'));
        $('#sync-modal-subtitulo').text(
            (fila.usuario_nombre || '') +
            (fila.fundos_nombres ? ' · ' + fila.fundos_nombres : '') +
            ' · ' + (PERIODO > 0 ? 'últimos ' + PERIODO + ' días' : 'todo el histórico')
        );
        $('#sync-modal-body').html('<div class="sync-vacio">Cargando…</div>');
        abrirModal();

        $.ajax({
            url: '../../controllers/mdispositivos_controller.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'detalle_sync',
                dispositivo_id: dispositivo,
                musuarios_id: usuario,
                dias: PERIODO
            }
        }).done(function (resp) {
            if (!resp || resp.resultado !== 'ok') {
                $('#sync-modal-body').html('<div class="sync-vacio">' + esc((resp && resp.mensaje) || 'No se pudo cargar el detalle.') + '</div>');
                return;
            }
            pintarEventos(resp.eventos || []);
        }).fail(function () {
            $('#sync-modal-body').html('<div class="sync-vacio">No se pudo contactar al servidor.</div>');
        });
    });

    function resumenEvento(ev) {
        var partes = [];
        var accion = String(ev.accion || '').toLowerCase();

        if (accion === 'push') {
            // Se informa lo que realmente quedó guardado en el servidor
            // (exitos), no lo que el equipo intentó enviar: antes un envío
            // de 1 registro rechazado se mostraba como "1 enviado · 1
            // rechazado", que se lee como una contradicción.
            var total = Number(ev.total || 0);
            var exitos = Number(ev.exitos || 0);
            if (total > 0) {
                partes.push(num(exitos) + ' de ' + num(total) + ' guardado(s)');
            } else {
                partes.push(num(exitos) + ' guardado(s)');
            }
            if (Number(ev.conflictos || 0) > 0) partes.push(num(ev.conflictos) + ' en conflicto');
            if (Number(ev.errores || 0) > 0) partes.push(num(ev.errores) + ' rechazado(s)');
        } else if (accion === 'pull') {
            partes.push(num(ev.total) + ' registro(s) descargados');
        } else if (accion === 'subir_foto') {
            partes.push('Foto enviada');
        } else {
            partes.push(num(ev.total) + ' registro(s)');
        }

        if (Number(ev.duracion_ms || 0) > 0) {
            partes.push(num(ev.duracion_ms) + ' ms');
        }
        return partes.join(' · ');
    }

    function pintarEventos(eventos) {
        if (!eventos.length) {
            $('#sync-modal-body').html('<div class="sync-vacio">Este equipo no tiene sincronizaciones en el período seleccionado.</div>');
            return;
        }

        var html = '';
        eventos.forEach(function (ev) {
            var clase = ev.resultado === 'error' ? 'is-error' : (ev.resultado === 'parcial' ? 'is-parcial' : '');
            var badge = ev.resultado === 'ok'
                ? '<span class="crud-badge crud-badge--active"><i class="fa fa-check"></i> OK</span>'
                : (ev.resultado === 'parcial'
                    ? '<span class="crud-badge crud-badge--warning"><i class="fa fa-exclamation-triangle"></i> Parcial</span>'
                    : '<span class="crud-badge crud-badge--no"><i class="fa fa-times"></i> Falló</span>');

            var accion = String(ev.accion || '').toUpperCase();
            var modulo = ev.modulo_etiqueta || 'General';

            html += '<div class="sync-evento ' + clase + '">'
                + '<div class="sync-evento__top">'
                + '<span class="sync-evento__fecha">' + esc(ev.fecha) + '</span>'
                + badge
                + '<span class="crud-badge crud-badge--info">' + esc(accion) + '</span>'
                + '<span class="crud-badge crud-badge--ghost"><i class="fa fa-cube"></i> ' + esc(modulo) + '</span>'
                + (ev.fundo_nombre ? '<span style="font-size:11px;"><i class="fa fa-map-marker"></i> ' + esc(ev.fundo_nombre) + '</span>' : '')
                + '</div>'
                + '<div class="sync-evento__resumen">' + esc(resumenEvento(ev))
                + (ev.mensaje ? ' — ' + esc(ev.mensaje) : '')
                + '</div>';

            if (Array.isArray(ev.detalle) && ev.detalle.length) {
                html += '<ul class="sync-evento__fallos">';
                ev.detalle.forEach(function (fallo) {
                    var etiqueta = fallo.modulo_etiqueta || fallo.modulo || modulo;
                    var ref = fallo.referencia ? '<strong>' + esc(fallo.referencia) + '</strong> · ' : '';
                    var tipo = fallo.tipo === 'conflicto' ? 'Conflicto' : (fallo.tipo === 'peticion' ? 'Petición rechazada' : 'Rechazado');
                    html += '<li><span class="crud-badge ' + (fallo.tipo === 'conflicto' ? 'crud-badge--warning' : 'crud-badge--no') + '" style="font-size:9px;padding:2px 6px;">'
                        + esc(tipo) + '</span> ' + esc(etiqueta) + ': ' + ref + esc(fallo.mensaje || 'Sin detalle')
                        + '</li>';
                });
                html += '</ul>';
            }

            html += '</div>';
        });

        $('#sync-modal-body').html(html);
    }

    /* ============================================================
       FILTROS
       ============================================================ */
    $('#sync-periodo').on('change', function () {
        PERIODO = parseInt($(this).val(), 10) || 0;
        tabla.ajax.reload(null, false);
    });

    $('#sync-solo-fallos').on('change', function () {
        SOLO_FALLOS = $(this).is(':checked');
        tabla.ajax.reload(null, false);
    });

    $('#recargar').on('click', function () {
        tabla.ajax.reload(null, false);
    });

    function mostrarMensaje(texto, tipo) {
        var clase = tipo === 'error' ? 'alert alert-danger' : 'alert alert-info';
        $('.message').html('<div class="' + clase + '" style="margin:10px 24px;">' + esc(texto) + '</div>');
    }

});
</script>

<?php include_once("../layouts/pie.php"); ?>
