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

<style>
    /* ============================================================
   BOTÓN "VOLVER A ACTIVOS" — naranja suave con texto oscuro
   ============================================================ */
    .crud-btn--back {
        background: linear-gradient(135deg, #fed7aa 0%, #fdba74 100%) !important;
        color: #7c2d12 !important;
        padding: 14px 26px !important;
        font-size: 15.5px !important;
        font-weight: 800 !important;
        letter-spacing: .02em;
        border: 2px solid #f59e0b !important;
        text-shadow: 0 1px 0 rgba(255, 255, 255, .45);
        box-shadow: 0 4px 12px rgba(245, 158, 11, .30),
            0 10px 24px -8px rgba(217, 119, 6, .50) !important;
        animation: crud-pulse-back 2s ease-in-out infinite;
        text-decoration: none;
    }

    .crud-btn--back i {
        font-size: 16px;
        margin-right: 2px;
        color: #7c2d12 !important;
    }

    .crud-btn--back:hover,
    .crud-btn--back:focus {
        background: linear-gradient(135deg, #fdba74 0%, #fb923c 100%) !important;
        color: #431407 !important;
        border-color: #d97706 !important;
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(245, 158, 11, .40),
            0 14px 30px -8px rgba(217, 119, 6, .65) !important;
        animation: none;
    }

    .crud-btn--back:hover i,
    .crud-btn--back:focus i {
        color: #431407 !important;
    }

    @keyframes crud-pulse-back {
        0% {
            box-shadow: 0 4px 12px rgba(245, 158, 11, .30), 0 10px 24px -8px rgba(217, 119, 6, .50), 0 0 0 0 rgba(245, 158, 11, .55);
        }

        70% {
            box-shadow: 0 4px 12px rgba(245, 158, 11, .30), 0 10px 24px -8px rgba(217, 119, 6, .50), 0 0 0 12px rgba(245, 158, 11, 0);
        }

        100% {
            box-shadow: 0 4px 12px rgba(245, 158, 11, .30), 0 10px 24px -8px rgba(217, 119, 6, .50), 0 0 0 0 rgba(245, 158, 11, 0);
        }
    }

    .crud-toolbar--historico {
        justify-content: space-between;
    }

    .crud-toolbar--historico .crud-toolbar__actions {
        flex: 0 1 auto;
    }

    .crud-toolbar--historico .crud-toolbar__right {
        display: flex;
        gap: 10px;
        margin-left: auto;
    }

    /* ============================================================
   FIX: la tabla debe ocupar todo el ancho del card
   ============================================================ */
    .crud-table-wrap {
        padding-right: 12px;
    }

    /* Cuando NO hay scrollX, DataTables no hace split y todo fluye */
    .crud-table-wrap .dataTables_scrollHead,
    .crud-table-wrap .dataTables_scrollBody {
        width: 100% !important;
    }

    .crud-table-wrap .dataTables_scrollHead table,
    .crud-table-wrap .dataTables_scrollBody table {
        width: 100% !important;
        table-layout: fixed !important;
    }

    /* La última columna visible toma el espacio sobrante */
    .crud-table-wrap .dataTables_scrollHead th:last-child,
    .crud-table-wrap .dataTables_scrollBody td:last-child {
        width: auto !important;
    }

    @media (max-width: 640px) {
        .crud-btn--back {
            padding: 11px 16px !important;
            font-size: 13.5px !important;
        }

        .crud-toolbar--historico {
            flex-direction: column;
            align-items: stretch;
        }

        .crud-toolbar--historico .crud-toolbar__right {
            margin-left: 0;
        }

        .crud-toolbar--historico .crud-btn--back {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<div class="crud-page" data-theme="info">

    <div class="message"></div>

    <div class="crud-card">

        <header class="crud-header">
            <span class="crud-header__icon"><i class="fa fa-history"></i></span>
            <div class="crud-header__text">
                <h3 class="crud-header__title">Histórico de Dispositivos</h3>
                <p class="crud-header__subtitle">
                    <?php if ($es_admin_vista): ?>
                        Dispositivos desvinculados de todos los fundos
                    <?php else: ?>
                        Dispositivos desvinculados de tu fundo
                    <?php endif; ?>
                </p>
            </div>
            <span class="crud-header__badge" id="crud-count-dispositivos">—</span>
        </header>

        <div class="crud-toolbar crud-toolbar--historico">
            <div class="crud-toolbar__actions">
                <button id="recargar" type="button" class="crud-btn crud-btn--ghost">
                    <i class="fa fa-refresh"></i> <span>Recargar</span>
                </button>
            </div>
            <div class="crud-toolbar__right">
                <a href="index.php" class="crud-btn crud-btn--back">
                    <i class="fa fa-arrow-left"></i> <span>Volver a activos</span>
                </a>
            </div>
        </div>

        <div
            style="padding: 14px 24px; background: #eff6ff; border-bottom: 1px solid #bfdbfe; color: #1e3a8a; font-size: 13px; line-height: 1.5;">
            <i class="fa fa-info-circle"></i>
            Aquí se listan los dispositivos que <strong>ya fueron desvinculados</strong>.
            Solo lectura — no se puede reactivar un dispositivo desde aquí.
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
                        <th>Desvinculado</th>
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
    $(document).ready(function () {

        var ES_ADMIN = <?php echo $es_admin_vista ? 'true' : 'false'; ?>;

        /* SIN columnas técnicas — todas las columnas son visibles
           y se reparten el 100% del ancho automáticamente */
        var columnas = [
            {
                "data": "usuario_nombre",
                "render": function (data, type, row) {
                    if (type !== 'display') return data;
                    var html = '<strong>' + data + '</strong>';
                    if (row.usuario_status === 0) {
                        html += ' <span class="crud-badge crud-badge--inactive" style="font-size:9px;padding:3px 8px;margin-left:6px;">INACTIVO</span>';
                    }
                    return html;
                }
            },
            { "data": "nombre_dispositivo" }
        ];

        if (ES_ADMIN) {
            columnas.push({
                "data": "fundo_nombre",
                "defaultContent": '<span style="color:#94a3b8;">—</span>'
            });
        }

        columnas.push(
            {
                "data": "modo_offline",
                "render": function (data, type, row) {
                    if (type !== 'display') return data;
                    if (data === 'consulta') {
                        return '<span class="crud-badge crud-badge--info"><i class="fa fa-eye"></i> Consulta</span>';
                    }
                    return '<span class="crud-badge crud-badge--warning"><i class="fa fa-pencil"></i> Gestión</span>';
                }
            },
            { "data": "activado_en" },
            { "data": "ultima_sincronizacion" },
            {
                "data": "desvinculado_en",
                "render": function (data, type, row) {
                    if (type !== 'display') return data;
                    return '<span style="color:#94a3b8; font-weight:600;">' + data + '</span>';
                }
            }
        );

        var tabla = $('#tabla_dispositivos').DataTable({
            "dom": '<"crud-table-top"lf>rt<"crud-table-bottom"ip>',
            "ajax": {
                "url": "../../controllers/mdispositivos_controller.php",
                "type": "POST",
                "data": { action: "listar", filtro: "historico" },
                // El histórico se muestra plano: una fila por (equipo, fundo)
                // para ver exactamente qué fundo se desvinculó y cuándo.
                "dataSrc": "filas"
            },
            "autoWidth": false,     // ← DataTables respeta los anchos declarados
            "scrollX": false,        // ← sin scroll horizontal: la tabla ocupa el 100%
            "scrollY": "420px",
            "scrollCollapse": false,
            "order": [[4, "desc"]],
            "columns": columnas,
            "columnDefs": (ES_ADMIN
                ? [
                    { "width": "15%", "targets": 0 },                            // Usuario
                    { "width": "18%", "targets": 1 },                            // Dispositivo
                    { "width": "16%", "targets": 2 },                            // Fundo
                    { "width": "12%", "targets": 3, "className": "text-center" },// Modo
                    { "width": "13%", "targets": 4 },                            // Activado
                    { "width": "13%", "targets": 5 },                            // Última sinc
                    { "width": "13%", "targets": 6 }                             // Desvinculado
                ]
                : [
                    { "width": "18%", "targets": 0 },                            // Usuario
                    { "width": "22%", "targets": 1 },                            // Dispositivo
                    { "width": "15%", "targets": 2, "className": "text-center" },// Modo
                    { "width": "15%", "targets": 3 },                            // Activado
                    { "width": "15%", "targets": 4 },                            // Última sinc
                    { "width": "15%", "targets": 5 }                             // Desvinculado
                ]
            ),
            "fnRowCallback": function (nRow, mData, iDisplayIndex) {
                $(nRow).css('opacity', '0.75');
                $('td:first', nRow).css('font-weight', 'bold');
                return nRow;
            },
            "language": {
                "search": '',
                "searchPlaceholder": 'Buscar en el histórico…',
                "lengthMenu": '_MENU_ por página',
                "info": 'Mostrando _START_ a _END_ de _TOTAL_',
                "infoEmpty": 'Sin dispositivos en el histórico',
                "infoFiltered": '(filtrado de _MAX_)',
                "zeroRecords": 'No hay registros que coincidan',
                "emptyTable": 'Aún no hay dispositivos desvinculados',
                "loadingRecords": 'Cargando…',
                "processing": 'Procesando…',
                "paginate": { "first": '«', "last": '»', "next": '›', "previous": '‹' }
            },
            "drawCallback": function () {
                try {
                    var info = this.api().page.info();
                    $('#crud-count-dispositivos').text(
                        info.recordsTotal + ' dispositivo' + (info.recordsTotal !== 1 ? 's' : '')
                    );
                } catch (e) { }
            }
        });

        $('#recargar').on('click', function () {
            tabla.ajax.reload(null, false);
        });

    });
</script>

<?php include_once("../layouts/pie.php"); ?>