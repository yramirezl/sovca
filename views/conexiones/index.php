<?php

include("../../lib/validar_session.php");
ValidaSession("../login");
VerificarAdmin($_SESSION['rolx']);

?>
<?php include("../layouts/constantes.php"); ?>
<?php include_once("../layouts/cabecera.php"); ?>

<style type="text/css">
/* ============================================================
   CONEXIONES · historial y frecuencia de uso
   ============================================================ */
.cnx-wrap { padding: 4px 2px 24px; }

/* ---------- Encabezado ---------- */
.cnx-hero {
    display: flex; align-items: center; gap: 14px; flex-wrap: wrap;
    background: #fff; border: 1px solid #e5e8eb; border-radius: 14px;
    padding: 14px 18px; margin-bottom: 14px;
    box-shadow: 0 1px 3px rgba(0,0,0,.04);
}
.cnx-hero__icon {
    width: 44px; height: 44px; border-radius: 12px; flex: 0 0 44px;
    display: flex; align-items: center; justify-content: center;
    background: #eaf6ee; color: #2f8f4a; font-size: 20px;
}
.cnx-hero__text { flex: 1 1 320px; min-width: 0; }
.cnx-hero__text h1 { margin: 0; font-size: 19px; font-weight: 800; color: #1b3a24; }
.cnx-hero__text p  { margin: 2px 0 0; font-size: 13px; color: #6b7785; }
.cnx-hero__acciones { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.cnx-hero__acciones label {
    margin: 0; font-size: 11px; font-weight: 700; letter-spacing: .35px;
    text-transform: uppercase; color: #7b8794;
}
.cnx-select {
    height: 34px; border: 1px solid #d8dfe4; border-radius: 8px;
    padding: 0 10px; font-size: 13px; color: #33404d; background: #fff;
}
.cnx-btn-ref {
    height: 34px; border: 1px solid #2f8f4a; border-radius: 8px;
    background: #2f8f4a; color: #fff; font-size: 13px; font-weight: 600;
    padding: 0 14px; cursor: pointer;
}
.cnx-btn-ref:hover { background: #26743c; }
.cnx-btn-ref i { margin-right: 5px; }
.cnx-btn-ref[disabled] { opacity: .65; cursor: default; }

/* ---------- KPIs ---------- */
.cnx-kpis {
    display: grid; grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 10px; margin-bottom: 14px;
}
.cnx-kpi {
    background: #fff; border: 1px solid #e5e8eb; border-radius: 12px;
    padding: 12px 16px; display: flex; flex-direction: column; gap: 2px;
    box-shadow: 0 1px 3px rgba(0,0,0,.04);
    border-left: 4px solid #2f8f4a;
}
.cnx-kpi .kpi-num { font-size: 22px; font-weight: 800; color: #1b3a24; line-height: 1.1; }
.cnx-kpi .kpi-lbl { font-size: 11px; font-weight: 700; letter-spacing: .35px; text-transform: uppercase; color: #7b8794; }
.cnx-kpi .kpi-det { font-size: 11px; color: #97a1ac; }
.cnx-kpi.is-info   { border-left-color: #2b7bbb; }
.cnx-kpi.is-info   .kpi-num { color: #1c4f7c; }
.cnx-kpi.is-alerta { border-left-color: #e67e22; }
.cnx-kpi.is-alerta .kpi-num { color: #b45309; }

/* ---------- Pestañas ---------- */
.cnx-modos {
    display: flex; gap: 8px; flex-wrap: wrap; align-items: center;
    margin-bottom: 12px;
}
.cnx-modo {
    border: 1px solid #d8dfe4; background: #fff; color: #44515e;
    border-radius: 10px; padding: 8px 14px; font-size: 13px; font-weight: 600;
    cursor: pointer; display: inline-flex; align-items: center; gap: 7px;
}
.cnx-modo i { color: #93a0ac; }
.cnx-modo.is-on { border-color: #2f8f4a; background: #eaf6ee; color: #1b6b33; }
.cnx-modo.is-on i { color: #2f8f4a; }
.cnx-nota { font-size: 12px; color: #8a95a1; margin-left: auto; }

/* ---------- Panel / tabla ---------- */
.cnx-panel {
    background: #fff; border: 1px solid #e5e8eb; border-radius: 14px;
    padding: 10px 12px 6px; box-shadow: 0 1px 3px rgba(0,0,0,.04);
}
.cnx-panel[hidden] { display: none; }
.cnx-tabla { width: 100% !important; margin: 0; }
.cnx-tabla thead th {
    font-size: 11px; text-transform: uppercase; letter-spacing: .3px;
    color: #7b8794; border-bottom: 1px solid #e5e8eb !important;
    white-space: nowrap;
}
.cnx-tabla tbody td { font-size: 13px; color: #3c4956; vertical-align: middle !important; }

/* Celda de usuario */
.cnx-user { display: flex; flex-direction: column; line-height: 1.25; }
.cnx-user strong { color: #1b3a24; font-weight: 700; }
.cnx-user span   { font-size: 11.5px; color: #8a95a1; }

/* Celda de fecha */
.cnx-fecha { display: flex; flex-direction: column; line-height: 1.25; }
.cnx-fecha strong { font-weight: 700; color: #33404d; }
.cnx-fecha span   { font-size: 11.5px; color: #8a95a1; }

/* Celda de ubicación */
.cnx-ubi { display: flex; flex-direction: column; line-height: 1.25; }
.cnx-ubi span { font-size: 11.5px; color: #8a95a1; font-family: Consolas, Monaco, monospace; }
.cnx-sin-ubi { color: #b6bfc8; font-style: italic; }

.cnx-num { font-weight: 800; color: #1b3a24; }

/* Chips de estado */
.cnx-chip {
    display: inline-block; padding: 2px 9px; border-radius: 999px;
    font-size: 11px; font-weight: 700; letter-spacing: .2px; white-space: nowrap;
}
.cnx-chip--activo    { background: #e6f6ea; color: #1b6b33; }
.cnx-chip--reciente  { background: #e8f1fb; color: #1c4f7c; }
.cnx-chip--ocasional { background: #fdf3e3; color: #a35d09; }
.cnx-chip--inactivo  { background: #fdeaea; color: #a32020; }
.cnx-chip--sin       { background: #eef1f4; color: #7b8794; }

.cnx-origen {
    display: inline-block; padding: 1px 8px; border-radius: 6px;
    font-size: 11px; font-weight: 600; background: #eef1f4; color: #5b6875;
}

/* ---------- Usuario clicable (abre su historial) ---------- */
.cnx-user--link {
    display: flex; flex-direction: column; align-items: flex-start;
    width: 100%; border: 0; background: none; padding: 0;
    text-align: left; cursor: pointer; line-height: 1.25;
    color: inherit; font: inherit;
}
.cnx-user--link strong { display: inline-flex; align-items: center; gap: 5px; }
.cnx-user--link .fa-history { font-size: 10px; color: #b6bfc8; opacity: 0; transition: opacity .15s; }
.cnx-user--link:hover strong { color: #1b6b33; text-decoration: underline; }
.cnx-user--link:hover .fa-history { opacity: 1; }
.cnx-user--link:focus { outline: 2px solid #b9dcc4; outline-offset: 2px; border-radius: 6px; }

/* ---------- Modal: historial del usuario ---------- */
.cnx-modal-sub { margin: 2px 0 0; font-size: 12.5px; color: #8a95a1; }
.cnx-usr-grid {
    display: grid; grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 8px; margin-bottom: 16px;
}
.cnx-usr-dato {
    border: 1px solid #e5e8eb; background: #fafbfc; border-radius: 10px;
    padding: 8px 10px; min-width: 0;
}
.cnx-usr-dato small {
    display: block; font-size: 10px; font-weight: 700; letter-spacing: .3px;
    text-transform: uppercase; color: #7b8794;
}
.cnx-usr-dato small i { margin-right: 4px; color: #9aa5b1; }
.cnx-usr-dato b {
    display: block; font-size: 13.5px; color: #1b3a24; font-weight: 700;
    line-height: 1.3; overflow-wrap: anywhere;
}
.cnx-usr-dato em { display: block; font-size: 11px; font-style: normal; color: #8a95a1; }
.cnx-modal-h5 {
    margin: 0 0 8px; font-size: 13px; font-weight: 800; color: #1b3a24;
    display: flex; align-items: center; gap: 7px; flex-wrap: wrap;
}
.cnx-modal-h5 i { color: #2f8f4a; }
.cnx-modal-h5 span { font-weight: 600; color: #8a95a1; font-size: 12px; }
.cnx-usr-cargando { font-size: 13px; color: #8a95a1; padding: 10px 2px; }

/* ---------- Responsive ---------- */
@media (max-width: 767px) {
    .cnx-usr-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 991px) {
    .cnx-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .cnx-nota { margin-left: 0; width: 100%; }
}
@media (max-width: 560px) {
    .cnx-kpis { grid-template-columns: 1fr 1fr; gap: 8px; }
    .cnx-kpi { padding: 10px 12px; }
    .cnx-kpi .kpi-num { font-size: 18px; }
    .cnx-hero { padding: 12px; }
    .cnx-hero__acciones { width: 100%; }
    .cnx-select, .cnx-btn-ref { flex: 1 1 auto; }
}
/* ---- Cierre remoto de sesión ---- */
.cnx-btn-cerrar {
    display: inline-flex; align-items: center; gap: 5px;
    border: 1px solid #f3c9c5; background: #fdecea; color: #b3261e;
    border-radius: 8px; padding: 4px 9px; font-size: 11.5px; font-weight: 800;
    cursor: pointer; white-space: nowrap; margin-left: 6px;
}
.cnx-btn-cerrar:hover { background: #fbd7d3; color: #8f1d17; }
.cnx-btn-cerrar--cancel {
    border-color: #d9e3dc; background: #eef1f3; color: #3d4b44;
}
.cnx-btn-cerrar--cancel:hover { background: #e2e7ea; color: #233e2d; }
.cnx-cierre-curso {
    display: inline-block; font-size: 11px; color: #b26a00; font-weight: 700;
    margin-left: 6px;
}
</style>

<!-- Diálogos de confirmación (cierre remoto de sesión, avisos de error) -->
<link rel="stylesheet" href="../../plugins/confirma/jquery-confirm.min.css" type="text/css" />
<script type="text/javascript" src="../../plugins/confirma/jquery-confirm.min.js"></script>

<div class="cnx-wrap">

    <!-- ================= Encabezado ================= -->
    <div class="cnx-hero">
        <div class="cnx-hero__icon"><i class="fa fa-plug"></i></div>
        <div class="cnx-hero__text">
            <h1>Conexiones</h1>
            <p>Quién entra al sistema, desde dónde, con qué frecuencia y cuánto tiempo permanece conectado.</p>
        </div>
        <div class="cnx-hero__acciones">
            <label for="cnx-periodo">Periodo</label>
            <select id="cnx-periodo" class="cnx-select">
                <option value="7">Últimos 7 días</option>
                <option value="30" selected>Últimos 30 días</option>
                <option value="90">Últimos 90 días</option>
                <option value="365">Último año</option>
                <option value="0">Todo el historial</option>
            </select>
            <button type="button" id="cnx-refrescar" class="cnx-btn-ref">
                <i class="fa fa-refresh"></i>Actualizar
            </button>
        </div>
    </div>

    <!-- ================= KPIs ================= -->
    <div class="cnx-kpis">
        <div class="cnx-kpi">
            <span class="kpi-num" id="kpi-usuarios">—</span>
            <span class="kpi-lbl">Usuarios con actividad</span>
            <span class="kpi-det" id="kpi-usuarios-det">&nbsp;</span>
        </div>
        <div class="cnx-kpi is-info">
            <span class="kpi-num" id="kpi-conexiones">—</span>
            <span class="kpi-lbl">Conexiones</span>
            <span class="kpi-det" id="kpi-conexiones-det">&nbsp;</span>
        </div>
        <div class="cnx-kpi is-info">
            <span class="kpi-num" id="kpi-promedio">—</span>
            <span class="kpi-lbl">Promedio por usuario</span>
            <span class="kpi-det">Accesos en el periodo</span>
        </div>
        <div class="cnx-kpi is-info">
            <span class="kpi-num" id="kpi-duracion">—</span>
            <span class="kpi-lbl">Duración promedio</span>
            <span class="kpi-det" id="kpi-duracion-det">&nbsp;</span>
        </div>
        <div class="cnx-kpi is-alerta">
            <span class="kpi-num" id="kpi-ultima">—</span>
            <span class="kpi-lbl">Última conexión</span>
            <span class="kpi-det" id="kpi-ultima-det">&nbsp;</span>
        </div>
    </div>

    <!-- ================= Pestañas ================= -->
    <div class="cnx-modos">
        <button type="button" class="cnx-modo is-on" data-modo="resumen">
            <i class="fa fa-users"></i>Resumen por usuario
        </button>
        <button type="button" class="cnx-modo" data-modo="historial">
            <i class="fa fa-history"></i>Historial completo
        </button>
        <span class="cnx-nota">
            <i class="fa fa-info-circle"></i>
            «Frecuencia» es cada cuántos días entra el usuario, medido entre su primer y su último día activo del periodo.
            «Duración» es el tiempo entre el login y la última transacción registrada de esa sesión.
            Haz clic en <strong>un usuario</strong> para ver todo su historial.
        </span>
    </div>

    <!-- ================= Panel: resumen ================= -->
    <div class="cnx-panel" id="cnx-panel-resumen">
        <table id="cnx-tabla-resumen" class="table cnx-tabla">
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Última conexión</th>
                    <th>Ubicación</th>
                    <th>Conexiones</th>
                    <th>Días activos</th>
                    <th>Frecuencia</th>
                    <th>Duración</th>
                    <th>Estado</th>
                    <th>Sesión</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>

    <!-- ================= Panel: historial ================= -->
    <div class="cnx-panel" id="cnx-panel-historial" hidden>
        <table id="cnx-tabla-historial" class="table cnx-tabla">
            <thead>
                <tr>
                    <th>Inicio</th>
                    <th>Usuario</th>
                    <th>Ubicación</th>
                    <th>IP</th>
                    <th>Dispositivo</th>
                    <th>Última transacción</th>
                    <th>Duración</th>
                    <th>Estado</th>
                    <th>Origen</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>

</div>

<!-- ================= Modal: historial completo de un usuario ================= -->
<div class="modal fade" id="cnx-modal-usuario" tabindex="-1" role="dialog" aria-labelledby="cnx-modal-usuario-titulo">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="cnx-modal-usuario-titulo">
                    <i class="fa fa-user-circle-o"></i>
                    <span id="cnx-usr-username">Usuario</span>
                    <span id="cnx-usr-estado"></span>
                </h4>
                <p class="cnx-modal-sub" id="cnx-usr-nombre">&nbsp;</p>
            </div>
            <div class="modal-body">
                <div class="cnx-usr-grid" id="cnx-usr-datos"></div>

                <div class="cnx-modal-h5">
                    <i class="fa fa-history"></i> Historial completo
                    <span id="cnx-usr-resumen">sin filtro de periodo</span>
                </div>
                <div class="cnx-usr-cargando" id="cnx-usr-cargando" hidden>
                    <i class="fa fa-spinner fa-spin"></i> Cargando historial…
                </div>
                <table id="cnx-tabla-usuario" class="table cnx-tabla" style="width:100%">
                    <thead>
                        <tr>
                            <th>Inicio</th>
                            <th>Ubicación</th>
                            <th>IP</th>
                            <th>Dispositivo</th>
                            <th>Última transacción</th>
                            <th>Duración</th>
                            <th>Estado</th>
                            <th>Origen</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
(function () {
    'use strict';

    var URL_RESUMEN   = '../../data_json/data_mconexiones_resumen.php';
    var URL_HISTORIAL = '../../data_json/data_mconexiones.php';

    var dtResumen   = null;
    var dtHistorial = null;
    var dtUsuario   = null;
    var usuarioSeq  = 0;

    /* ---------------- utilidades ---------------- */
    function esc(v) {
        if (v === null || typeof v === 'undefined') { return ''; }
        return String(v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function num(v) {
        var n = parseFloat(v);
        if (isNaN(n)) { return '0'; }
        return n.toLocaleString('es-VE');
    }

    function fechaLegible(iso) {
        if (!iso) { return '—'; }
        var p = String(iso).split(' ');
        if (p.length !== 2) { return iso; }
        var f = p[0].split('-');
        if (f.length !== 3) { return iso; }
        return f[2] + '-' + f[1] + '-' + f[0] + ' ' + p[1];
    }

    function fechaCorta(iso) {
        if (!iso) { return '—'; }
        var p = String(iso).split(' ');
        if (p.length !== 2) { return iso; }
        var f = p[0].split('-');
        if (f.length !== 3) { return iso; }
        return f[2] + '/' + f[1];
    }

    function hace(iso) {
        if (!iso) { return ''; }
        var d = new Date(String(iso).replace(' ', 'T'));
        if (isNaN(d.getTime())) { return ''; }
        var seg = Math.floor((new Date().getTime() - d.getTime()) / 1000);
        if (seg < 0) { seg = 0; }
        if (seg < 60) { return 'hace instantes'; }
        var min = Math.floor(seg / 60);
        if (min < 60) { return 'hace ' + min + ' min'; }
        var h = Math.floor(min / 60);
        if (h < 24) { return 'hace ' + h + ' h'; }
        var dd = Math.floor(h / 24);
        if (dd === 1) { return 'ayer'; }
        if (dd < 30) { return 'hace ' + dd + ' días'; }
        var mm = Math.floor(dd / 30);
        if (mm < 12) { return 'hace ' + mm + ' meses'; }
        return 'hace ' + Math.floor(mm / 12) + ' años';
    }

    function chipEstado(estado) {
        var clase = 'cnx-chip--sin';
        var e = String(estado || '');
        if (e === 'Activo') { clase = 'cnx-chip--activo'; }
        else if (e === 'Reciente') { clase = 'cnx-chip--reciente'; }
        else if (e === 'Ocasional') { clase = 'cnx-chip--ocasional'; }
        else if (e === 'Inactivo') { clase = 'cnx-chip--inactivo'; }
        return '<span class="cnx-chip ' + clase + '">' + esc(e) + '</span>';
    }

    /* Duración en texto: "45 s", "12 min", "1 h 8 min", "2 d 3 h". */
    function duracionTxt(seg) {
        if (seg === null || typeof seg === 'undefined' || seg === '' || isNaN(Number(seg))) {
            return '—';
        }
        var s = Math.max(0, Math.floor(Number(seg)));
        if (s < 60) { return s + ' s'; }
        var m = Math.floor(s / 60);
        if (m < 60) { return m + ' min'; }
        var h = Math.floor(m / 60);
        var r = m % 60;
        if (h < 24) { return h + ' h' + (r ? ' ' + r + ' min' : ''); }
        var d = Math.floor(h / 24);
        h = h % 24;
        return d + ' d' + (h ? ' ' + h + ' h' : '');
    }

    /* Chip del estado de la SESIÓN (no del usuario). */
    function chipSesion(estado) {
        var mapa = {
            activa:    ['cnx-chip--activo', 'En línea'],
            cerrada:   ['cnx-chip--reciente', 'Cerrada'],
            expirada:  ['cnx-chip--ocasional', 'Expirada'],
            sin_datos: ['cnx-chip--sin', 'Sin seguimiento']
        };
        var m = mapa[String(estado || '')] || mapa.sin_datos;
        return '<span class="cnx-chip ' + m[0] + '">' + m[1] + '</span>';
    }

    function idiomaTabla(vacio) {
        return {
            search: '',
            searchPlaceholder: 'Buscar usuario, ciudad o IP…',
            lengthMenu: '_MENU_ por página',
            info: 'Mostrando _START_ a _END_ de _TOTAL_',
            infoEmpty: 'Sin registros',
            infoFiltered: '(filtrado de _MAX_)',
            zeroRecords: vacio,
            paginate: { first: '«', last: '»', next: '›', previous: '‹' }
        };
    }

    /* ---------------- DataTables ---------------- */
    function crearTablaResumen() {
        dtResumen = jQuery('#cnx-tabla-resumen').DataTable({
            data: [],
            order: [[1, 'desc']],
            pageLength: 25,
            scrollX: true,
            autoWidth: false,
            columnDefs: [
                { targets: [0], width: '180px' },
                { targets: [1], width: '150px' },
                { targets: [2], width: '180px' },
                { targets: [3], width: '95px' },
                { targets: [4], width: '95px' },
                { targets: [5], width: '100px' },
                { targets: [6], width: '130px' },
                { targets: [7], width: '105px' },
                { targets: [8], width: '150px' }
            ],
            language: idiomaTabla('Ningún usuario registró accesos en este periodo'),
            columns: [
                {
                    data: 'username',
                    render: function (d, type, row) {
                        if (type !== 'display') { return d; }
                        return '<button type="button" class="cnx-user cnx-user--link"'
                             + ' data-id="' + esc(row.musuarios_id) + '"'
                             + ' title="Ver todo el historial de ' + esc(row.username) + '">'
                             + '<strong>' + esc(row.username) + ' <i class="fa fa-history"></i></strong>'
                             + '<span>' + esc(row.nombre) + '</span></button>';
                    }
                },
                {
                    data: 'ultima',
                    render: function (d, type, row) {
                        if (type !== 'display') { return d || ''; }
                        if (!d) { return '<span class="cnx-sin-ubi">nunca</span>'; }
                        var act = (row.ultima_actividad && row.ultima_actividad !== d)
                            ? '<span title="Última transacción registrada">act. ' + esc(hace(row.ultima_actividad)) + '</span>'
                            : '';
                        return '<div class="cnx-fecha"><strong>' + esc(fechaLegible(d)) + '</strong>'
                             + '<span>' + esc(hace(d)) + '</span>' + act + '</div>';
                    }
                },
                {
                    data: 'ubicacion',
                    render: function (d, type, row) {
                        if (type !== 'display') { return d || ''; }
                        var ubi = row.ubicacion
                            ? esc(row.ubicacion)
                            : '<span class="cnx-sin-ubi">sin ubicación</span>';
                        var ip = row.ultima_ip ? esc(row.ultima_ip) : '';
                        return '<div class="cnx-ubi">' + ubi
                             + (ip ? '<span>' + ip + '</span>' : '') + '</div>';
                    }
                },
                {
                    data: 'total',
                    className: 'text-center',
                    render: function (d, type) {
                        if (type !== 'display') { return d; }
                        return '<span class="cnx-num">' + num(d) + '</span>';
                    }
                },
                {
                    data: 'dias_activos',
                    className: 'text-center',
                    render: function (d, type) {
                        if (type !== 'display') { return d; }
                        return '<span class="cnx-num">' + num(d) + '</span>';
                    }
                },
                {
                    data: 'frecuencia',
                    className: 'text-center',
                    render: function (d, type, row) {
                        if (type !== 'display') {
                            return (d === null || typeof d === 'undefined') ? -1 : d;
                        }
                        if (Number(row.total) === 0) { return '<span class="cnx-sin-ubi">—</span>'; }
                        if (d === null || typeof d === 'undefined') {
                            return '<span class="cnx-sin-ubi">1 solo día</span>';
                        }
                        return '<span class="cnx-num">' + String(d).replace('.', ',') + '</span>'
                             + ' <small style="color:#8a95a1">días</small>';
                    }
                },
                {
                    data: 'duracion_prom',
                    className: 'text-center',
                    render: function (d, type, row) {
                        if (type !== 'display') {
                            return (d === null || typeof d === 'undefined') ? -1 : Number(d);
                        }
                        if (Number(row.total) === 0) { return '<span class="cnx-sin-ubi">—</span>'; }
                        if (d === null || typeof d === 'undefined') {
                            return '<span class="cnx-sin-ubi" title="Sesiones anteriores al seguimiento de duración">sin seguimiento</span>';
                        }
                        var ultima = row.ultima_duracion_txt
                            ? '<span style="font-size:11px;color:#8a95a1">últ. ' + esc(row.ultima_duracion_txt) + '</span>'
                            : '';
                        return '<div class="cnx-fecha"><strong>' + esc(row.duracion_prom_txt || duracionTxt(d)) + '</strong>'
                             + ultima + '</div>';
                    }
                },
                {
                    data: 'estado',
                    render: function (d, type) {
                        if (type !== 'display') { return d; }
                        return chipEstado(d);
                    }
                },
                {
                    /* SESIÓN: si está en línea se puede cerrar de forma remota
                       (con aviso de 60 s al usuario, no de golpe). */
                    data: 'sesion_estado',
                    orderable: false,
                    render: function (d, type, row) {
                        if (type !== 'display') { return d; }
                        var chip = chipSesion(d);
                        if (d !== 'activa' || !row.sesion_historial_id) {
                            return chip;
                        }
                        var id = esc(row.sesion_historial_id);
                        if (row.sesion_cierre_at) {
                            return chip + ' <span class="cnx-cierre-curso" title="El usuario ya está viendo el aviso de cierre">'
                                 + '<i class="fa fa-hourglass-half"></i> cierre en curso</span>'
                                 + ' <button type="button" class="cnx-btn-cerrar cnx-btn-cerrar--cancel" '
                                 + 'data-sesion="' + id + '" title="Cancelar el cierre">Cancelar</button>';
                        }
                        return chip + ' <button type="button" class="cnx-btn-cerrar" data-sesion="' + id + '"'
                             + ' data-usuario="' + esc(row.username) + '" title="Avisar al usuario y cerrar su sesión en 60 s">'
                             + '<i class="fa fa-power-off"></i> Cerrar sesión</button>';
                    }
                }
            ]
        });
    }

    function crearTablaHistorial() {
        dtHistorial = jQuery('#cnx-tabla-historial').DataTable({
            data: [],
            order: [[0, 'desc']],
            pageLength: 50,
            scrollX: true,
            autoWidth: false,
            columnDefs: [
                { targets: [0], width: '160px' },
                { targets: [1], width: '180px' },
                { targets: [2], width: '170px' },
                { targets: [3], width: '125px' },
                { targets: [4], width: '130px' },
                { targets: [5], width: '155px' },
                { targets: [6], width: '95px' },
                { targets: [7], width: '100px' },
                { targets: [8], width: '85px' }
            ],
            language: idiomaTabla('No hay conexiones registradas en este periodo'),
            columns: [
                {
                    data: 'fecha',
                    render: function (d, type) {
                        if (type !== 'display') { return d || ''; }
                        return '<div class="cnx-fecha"><strong>' + esc(fechaLegible(d)) + '</strong>'
                             + '<span>' + esc(hace(d)) + '</span></div>';
                    }
                },
                {
                    data: 'username',
                    render: function (d, type, row) {
                        if (type !== 'display') { return d; }
                        var inactivo = (Number(row.usuario_activo) === 1)
                            ? ''
                            : ' <span class="cnx-origen">inactivo</span>';
                        return '<div class="cnx-user"><strong>' + esc(row.username) + '</strong>'
                             + '<span>' + esc(row.nombre) + inactivo + '</span></div>';
                    }
                },
                {
                    data: 'ubicacion',
                    render: function (d, type, row) {
                        if (type !== 'display') { return d || ''; }
                        if (!row.ubicacion) {
                            return '<span class="cnx-sin-ubi">sin ubicación</span>';
                        }
                        var extra = (row.region && row.region !== row.ciudad)
                            ? ' <span>' + esc(row.region) + '</span>'
                            : '';
                        return '<div class="cnx-ubi">' + esc(row.ubicacion) + extra + '</div>';
                    }
                },
                {
                    data: 'ip',
                    render: function (d, type) {
                        if (type !== 'display') { return d || ''; }
                        return '<span style="font-family:Consolas,Monaco,monospace;font-size:12px">'
                             + esc(d) + '</span>';
                    }
                },
                {
                    data: 'dispositivo',
                    render: function (d, type) {
                        if (type !== 'display') { return d || ''; }
                        return esc(d);
                    }
                },
                {
                    data: 'ultima_actividad',
                    render: function (d, type, row) {
                        if (type !== 'display') { return d || ''; }
                        if (!d) { return '<span class="cnx-sin-ubi">sin seguimiento</span>'; }
                        var ruta = row.ultima_ruta ? ' title="' + esc(row.ultima_ruta) + '"' : '';
                        return '<div class="cnx-fecha"' + ruta + '><strong>' + esc(fechaLegible(d)) + '</strong>'
                             + '<span>' + esc(hace(d)) + '</span></div>';
                    }
                },
                {
                    data: 'duracion_seg',
                    className: 'text-center',
                    render: function (d, type, row) {
                        if (type !== 'display') {
                            return (d === null || typeof d === 'undefined') ? -1 : Number(d);
                        }
                        if (d === null || typeof d === 'undefined') {
                            return '<span class="cnx-sin-ubi">—</span>';
                        }
                        var extra = (row.estado === 'activa')
                            ? '<span style="font-size:11px;color:#1b6b33">en curso</span>'
                            : '';
                        return '<div class="cnx-fecha"><strong>' + esc(row.duracion_txt || duracionTxt(d)) + '</strong>' + extra + '</div>';
                    }
                },
                {
                    data: 'estado',
                    render: function (d, type, row) {
                        if (type !== 'display') { return d || ''; }
                        var chip = chipSesion(d);
                        if (row.cerrada_por) {
                            chip += ' <span style="font-size:10.5px;color:#8a95a1">' + esc(row.cerrada_por) + '</span>';
                        }
                        return chip;
                    }
                },
                {
                    data: 'origen',
                    render: function (d, type) {
                        if (type !== 'display') { return d || ''; }
                        return '<span class="cnx-origen">' + esc(String(d).toUpperCase()) + '</span>';
                    }
                }
            ]
        });
    }

    /* ---------------- Historial de un solo usuario ---------------- */
    function crearTablaUsuario() {
        var idioma = idiomaTabla('Este usuario no tiene accesos registrados');
        idioma.searchPlaceholder = 'Buscar por ciudad, IP o dispositivo…';

        dtUsuario = jQuery('#cnx-tabla-usuario').DataTable({
            data: [],
            order: [[0, 'desc']],
            pageLength: 25,
            scrollX: true,
            autoWidth: false,
            columnDefs: [
                { targets: [0], width: '150px' },
                { targets: [1], width: '200px' },
                { targets: [2], width: '125px' },
                { targets: [3], width: '140px' },
                { targets: [4], width: '150px' },
                { targets: [5], width: '90px' },
                { targets: [6], width: '95px' },
                { targets: [7], width: '80px' }
            ],
            language: idioma,
            columns: [
                {
                    data: 'fecha',
                    render: function (d, type) {
                        if (type !== 'display') { return d || ''; }
                        return '<div class="cnx-fecha"><strong>' + esc(fechaLegible(d)) + '</strong>'
                             + '<span>' + esc(hace(d)) + '</span></div>';
                    }
                },
                {
                    data: 'ubicacion',
                    render: function (d, type, row) {
                        if (type !== 'display') { return d || ''; }
                        if (!row.ubicacion) {
                            return '<span class="cnx-sin-ubi">sin ubicación</span>';
                        }
                        var extra = (row.region && row.region !== row.ciudad)
                            ? ' <span>' + esc(row.region) + '</span>'
                            : '';
                        return '<div class="cnx-ubi">' + esc(row.ubicacion) + extra + '</div>';
                    }
                },
                {
                    data: 'ip',
                    render: function (d, type) {
                        if (type !== 'display') { return d || ''; }
                        return '<span style="font-family:Consolas,Monaco,monospace;font-size:12px">'
                             + esc(d) + '</span>';
                    }
                },
                {
                    data: 'dispositivo',
                    render: function (d, type, row) {
                        if (type !== 'display') { return d || ''; }
                        var titulo = row.user_agent ? ' title="' + esc(row.user_agent) + '"' : '';
                        return '<span' + titulo + '>' + esc(d) + '</span>';
                    }
                },
                {
                    data: 'ultima_actividad',
                    render: function (d, type, row) {
                        if (type !== 'display') { return d || ''; }
                        if (!d) { return '<span class="cnx-sin-ubi">sin seguimiento</span>'; }
                        var ruta = row.ultima_ruta ? ' title="' + esc(row.ultima_ruta) + '"' : '';
                        return '<div class="cnx-fecha"' + ruta + '><strong>' + esc(fechaLegible(d)) + '</strong>'
                             + '<span>' + esc(hace(d)) + '</span></div>';
                    }
                },
                {
                    data: 'duracion_seg',
                    className: 'text-center',
                    render: function (d, type, row) {
                        if (type !== 'display') {
                            return (d === null || typeof d === 'undefined') ? -1 : Number(d);
                        }
                        if (d === null || typeof d === 'undefined') {
                            return '<span class="cnx-sin-ubi">—</span>';
                        }
                        return '<div class="cnx-fecha"><strong>' + esc(row.duracion_txt || duracionTxt(d)) + '</strong></div>';
                    }
                },
                {
                    data: 'estado',
                    render: function (d, type) {
                        if (type !== 'display') { return d || ''; }
                        return chipSesion(d);
                    }
                },
                {
                    data: 'origen',
                    render: function (d, type) {
                        if (type !== 'display') { return d || ''; }
                        return '<span class="cnx-origen">' + esc(String(d).toUpperCase()) + '</span>';
                    }
                }
            ]
        });
    }

    function diasEntreFechas(a, b) {
        var A = new Date(a + 'T00:00:00');
        var B = new Date(b + 'T00:00:00');
        if (isNaN(A.getTime()) || isNaN(B.getTime())) { return null; }
        return Math.round((B.getTime() - A.getTime()) / 86400000);
    }

    function hoyISO() {
        var h = new Date();
        return h.getFullYear() + '-'
             + ('0' + (h.getMonth() + 1)).slice(-2) + '-'
             + ('0' + h.getDate()).slice(-2);
    }

    /* Estadísticas calculadas sobre TODO el historial del usuario,
       no sobre el periodo elegido en la parte superior. */
    function statsHistorial(filas) {
        var dias = {}, ips = {}, ubis = {};
        var nDias = 0, nIps = 0, nUbis = 0;
        var primera = null, ultima = null;
        var durSuma = 0, durN = 0, enLinea = 0, ultimaActividad = null;

        for (var i = 0; i < filas.length; i++) {
            var f = filas[i];
            var fecha = String(f.fecha || '');
            if (fecha.length >= 10) {
                var dia = fecha.substring(0, 10);
                if (!dias[dia]) { dias[dia] = 1; nDias++; }
            }
            if (f.ip && !ips[f.ip]) { ips[f.ip] = 1; nIps++; }
            if (f.ubicacion && !ubis[f.ubicacion]) { ubis[f.ubicacion] = 1; nUbis++; }
            if (fecha) {
                if (!primera || fecha < primera) { primera = fecha; }
                if (!ultima  || fecha > ultima)  { ultima  = fecha; }
            }
            if (f.duracion_seg !== null && typeof f.duracion_seg !== 'undefined' && !isNaN(Number(f.duracion_seg))) {
                durSuma += Number(f.duracion_seg);
                durN++;
            }
            if (f.estado === 'activa') { enLinea++; }
            if (f.ultima_actividad && (!ultimaActividad || f.ultima_actividad > ultimaActividad)) {
                ultimaActividad = f.ultima_actividad;
            }
        }

        var frecuencia = null;
        if (nDias > 1 && primera && ultima) {
            var ventana = diasEntreFechas(primera.substring(0, 10), ultima.substring(0, 10));
            if (ventana !== null && ventana > 0) {
                frecuencia = Math.round((ventana / (nDias - 1)) * 10) / 10;
            }
        }

        var diasSinEntrar = ultima
            ? diasEntreFechas(ultima.substring(0, 10), hoyISO())
            : null;

        var estado = 'Sin actividad';
        if (filas.length > 0) {
            if (diasSinEntrar !== null && diasSinEntrar <= 1) { estado = 'Activo'; }
            else if (diasSinEntrar !== null && diasSinEntrar <= 7) { estado = 'Reciente'; }
            else if (diasSinEntrar !== null && diasSinEntrar <= 30) { estado = 'Ocasional'; }
            else { estado = 'Inactivo'; }
        }

        return {
            total: filas.length,
            diasActivos: nDias,
            ips: nIps,
            ubicaciones: nUbis,
            primera: primera,
            ultima: ultima,
            frecuencia: frecuencia,
            diasSinEntrar: diasSinEntrar,
            estado: estado,
            duracionProm: durN > 0 ? Math.round(durSuma / durN) : null,
            duracionN: durN,
            ultimaActividad: ultimaActividad,
            enLinea: enLinea
        };
    }

    function datoUsr(icono, etiqueta, valor, extra) {
        return '<div class="cnx-usr-dato">'
             + '<small><i class="fa ' + icono + '"></i>' + etiqueta + '</small>'
             + '<b>' + valor + '</b>'
             + (extra ? '<em>' + extra + '</em>' : '')
             + '</div>';
    }

    function pintarDatosUsuario(st) {
        var frecuencia = '—';
        if (st.diasActivos > 1) {
            frecuencia = (st.frecuencia === null)
                ? '—'
                : String(st.frecuencia).replace('.', ',') + ' días';
        } else if (st.total > 0) {
            frecuencia = '1 solo día';
        }

        var html = '';
        html += datoUsr('fa-plug', 'Conexiones totales', num(st.total));
        html += datoUsr('fa-calendar-check-o', 'Días activos', num(st.diasActivos));
        html += datoUsr('fa-repeat', 'Frecuencia', frecuencia);
        html += datoUsr('fa-hourglass-half', 'Días sin entrar',
                        (st.diasSinEntrar === null) ? '—' : num(st.diasSinEntrar));
        html += datoUsr('fa-sign-in', 'Primera conexión',
                        st.primera ? esc(fechaLegible(st.primera)) : '—');
        html += datoUsr('fa-clock-o', 'Última conexión',
                        st.ultima ? esc(fechaLegible(st.ultima)) : '—',
                        st.ultima ? esc(hace(st.ultima)) : '');
        html += datoUsr('fa-hourglass-end', 'Duración promedio',
                        st.duracionProm === null ? '—' : esc(duracionTxt(st.duracionProm)),
                        st.duracionN > 0 ? (num(st.duracionN) + ' sesión(es) medida(s)') : 'sin seguimiento');
        html += datoUsr('fa-bolt', 'Última transacción',
                        st.ultimaActividad ? esc(fechaLegible(st.ultimaActividad)) : '—',
                        st.ultimaActividad ? esc(hace(st.ultimaActividad)) : '');
        html += datoUsr('fa-circle', 'Sesiones en línea', num(st.enLinea));
        html += datoUsr('fa-map-marker', 'Ubicaciones distintas', num(st.ubicaciones));
        html += datoUsr('fa-desktop', 'IPs distintas', num(st.ips));

        jQuery('#cnx-usr-datos').html(html);
    }

    function abrirUsuario(id) {
        var fila = null;
        var filas = dtResumen ? dtResumen.rows().data().toArray() : [];
        for (var i = 0; i < filas.length; i++) {
            if (Number(filas[i].musuarios_id) === Number(id)) {
                fila = filas[i];
                break;
            }
        }

        usuarioSeq++;
        var seq = usuarioSeq;

        jQuery('#cnx-usr-username').text(fila ? fila.username : ('Usuario #' + id));
        jQuery('#cnx-usr-nombre').text((fila && fila.nombre) ? fila.nombre : 'Sin nombre registrado');
        jQuery('#cnx-usr-estado').html('');
        jQuery('#cnx-usr-datos').html('');
        jQuery('#cnx-usr-resumen').text('cargando…');
        jQuery('#cnx-usr-cargando').prop('hidden', false);

        if (dtUsuario) {
            dtUsuario.clear().draw();
        }

        jQuery('#cnx-modal-usuario').modal('show');

        jQuery.ajax({
            url: URL_HISTORIAL,
            data: { usuario: id, dias: 0 },
            dataType: 'json',
            cache: false
        }).done(function (datos) {
            if (seq !== usuarioSeq) { return; }
            if (!jQuery.isArray(datos)) { datos = []; }

            var st = statsHistorial(datos);
            pintarDatosUsuario(st);
            jQuery('#cnx-usr-estado').html(chipEstado(st.estado));
            jQuery('#cnx-usr-resumen').text(
                datos.length > 0
                    ? num(datos.length) + ' accesos · del más reciente al más antiguo'
                    : 'sin accesos registrados'
            );

            if (!dtUsuario) { crearTablaUsuario(); }
            dtUsuario.clear();
            dtUsuario.rows.add(datos);
            dtUsuario.draw();
            dtUsuario.columns.adjust();
        }).fail(function () {
            if (seq !== usuarioSeq) { return; }
            jQuery('#cnx-usr-resumen').text('no se pudo cargar el historial');
        }).always(function () {
            if (seq === usuarioSeq) {
                jQuery('#cnx-usr-cargando').prop('hidden', true);
            }
        });
    }

    /* ---------------- KPIs ---------------- */
    function pintarKpis(resumen, historial) {
        var conActividad = 0;
        var totalConexiones = 0;
        var ultima = null;
        var ultimoUsuario = '';

        for (var i = 0; i < resumen.length; i++) {
            var r = resumen[i];
            if (Number(r.total || 0) > 0) {
                conActividad++;
                totalConexiones += Number(r.total || 0);
            }
            if (r.ultima && (!ultima || r.ultima > ultima)) {
                ultima = r.ultima;
                ultimoUsuario = r.username || '';
            }
        }

        jQuery('#kpi-usuarios').text(num(conActividad));
        jQuery('#kpi-usuarios-det').text('de ' + num(resumen.length) + ' cuentas activas');

        jQuery('#kpi-conexiones').text(num(totalConexiones));
        jQuery('#kpi-conexiones-det').text(num(historial.length) + ' en el historial');

        var promedio = conActividad > 0 ? (totalConexiones / conActividad) : 0;
        jQuery('#kpi-promedio').text(promedio > 0 ? promedio.toFixed(1).replace('.', ',') : '0');

        /* Duración promedio de las sesiones del periodo. */
        var durSuma = 0, durN = 0;
        for (var j = 0; j < historial.length; j++) {
            var seg = historial[j].duracion_seg;
            if (seg !== null && typeof seg !== 'undefined' && !isNaN(Number(seg))) {
                durSuma += Number(seg);
                durN++;
            }
        }
        jQuery('#kpi-duracion').text(durN > 0 ? duracionTxt(Math.round(durSuma / durN)) : '—');
        jQuery('#kpi-duracion-det').text(durN > 0 ? (num(durN) + ' sesiones medidas') : 'sin datos aún');

        if (ultima) {
            jQuery('#kpi-ultima').text(fechaCorta(ultima));
            jQuery('#kpi-ultima-det').text(ultimoUsuario + ' · ' + hace(ultima));
        } else {
            jQuery('#kpi-ultima').text('—');
            jQuery('#kpi-ultima-det').text('Sin conexiones');
        }
    }

    /* ---------------- carga ---------------- */
    var cargando = false;

    function cargar() {
        if (cargando) { return; }
        cargando = true;

        var dias = jQuery('#cnx-periodo').val();
        var qs = '?dias=' + encodeURIComponent(dias);

        jQuery('#cnx-refrescar').prop('disabled', true);

        jQuery.when(
            jQuery.getJSON(URL_RESUMEN + qs),
            jQuery.getJSON(URL_HISTORIAL + qs)
        ).done(function (rResumen, rHistorial) {
            var resumen = rResumen[0] || [];
            var historial = rHistorial[0] || [];

            if (!dtResumen) { crearTablaResumen(); }
            if (!dtHistorial) { crearTablaHistorial(); }

            dtResumen.clear();
            dtResumen.rows.add(resumen);
            dtResumen.draw();

            dtHistorial.clear();
            dtHistorial.rows.add(historial);
            dtHistorial.draw();

            pintarKpis(resumen, historial);
        }).fail(function () {
            if (typeof jQuery.confirm === 'function') {
                jQuery.confirm({
                    title: 'No se pudo cargar',
                    content: 'No se pudieron obtener los datos de conexiones. Intenta de nuevo.',
                    type: 'red',
                    buttons: { ok: 'Entendido' }
                });
            }
        }).always(function () {
            cargando = false;
            jQuery('#cnx-refrescar').prop('disabled', false);
        });
    }

    /* ---------------- eventos ---------------- */
    function aplicarModo(nuevo) {
        jQuery('.cnx-modo').removeClass('is-on');
        jQuery('.cnx-modo[data-modo="' + nuevo + '"]').addClass('is-on');

        jQuery('#cnx-panel-resumen').prop('hidden', nuevo !== 'resumen');
        jQuery('#cnx-panel-historial').prop('hidden', nuevo !== 'historial');

        /* DataTables mide mal las columnas cuando la tabla está oculta. */
        setTimeout(function () {
            if (nuevo === 'resumen' && dtResumen) { dtResumen.columns.adjust(); }
            if (nuevo === 'historial' && dtHistorial) { dtHistorial.columns.adjust(); }
        }, 30);
    }

    jQuery(function () {
        /* El modal se mueve al body: si queda dentro de .content-wrapper
           (z-index propio del tema) el backdrop lo cubre. */
        jQuery('#cnx-modal-usuario').appendTo('body');

        jQuery('.cnx-modo').on('click', function () {
            aplicarModo(jQuery(this).data('modo'));
        });

        /* Clic en el usuario → su historial completo */
        jQuery('#cnx-tabla-resumen tbody').on('click', '.cnx-user--link', function (e) {
            e.preventDefault();
            e.stopPropagation();
            abrirUsuario(jQuery(this).data('id'));
        });

        jQuery('#cnx-modal-usuario').on('shown.bs.modal', function () {
            if (dtUsuario) { dtUsuario.columns.adjust(); }
        });

        jQuery('#cnx-periodo').on('change', function () { cargar(); });
        jQuery('#cnx-refrescar').on('click', function () { cargar(); });

        /* ---------------- CIERRE REMOTO DE SESIÓN ----------------
           No expulsa al usuario de golpe: se le pide el cierre y él recibe
           un aviso con cuenta atrás de 60 s para guardar lo que esté
           haciendo (js/bovs-sesion.js). */
        jQuery('#cnx-tabla-resumen tbody').on('click', '.cnx-btn-cerrar', function (e) {
            e.preventDefault();
            e.stopPropagation();

            var $b       = jQuery(this);
            var id       = $b.attr('data-sesion');
            var esCancel = $b.hasClass('cnx-btn-cerrar--cancel');
            var usr      = $b.attr('data-usuario') || 'el usuario';

            if (!id) { return; }

            jQuery.confirm({
                title: esCancel ? '¿Cancelar el cierre de sesión?' : '¿Cerrar la sesión de ' + usr + '?',
                content: esCancel
                    ? 'La sesión de <b>' + esc(usr) + '</b> seguirá abierta y el aviso desaparecerá.'
                    : 'El usuario recibirá un aviso: <b>«Su sesión ha expirado…»</b> con una cuenta atrás de '
                      + '<b>60 segundos</b> para que guarde lo que esté haciendo. Al terminar, el sistema lo saca.'
                      + '<br><br><small>No se pierde nada de lo que ya haya guardado.</small>',
                confirmButton: esCancel ? 'Sí, cancelar' : 'Sí, cerrar sesión',
                cancelButton: 'Volver',
                confirmButtonClass: esCancel ? 'btn-default' : 'btn-danger',
                cancelButtonClass: 'btn-default',
                confirm: function () {
                    jQuery.post('../../controllers/mconexiones_controller', {
                        action: esCancel ? 'cancelar_cierre' : 'solicitar_cierre',
                        historial_id: id
                    }, function (r) {
                        if (r && r.ok) {
                            cargar();
                        } else {
                            jQuery.confirm({
                                title: 'No se pudo completar',
                                content: esc((r && r.msg) || 'Intenta de nuevo.'),
                                confirmButton: 'Entendido',
                                cancelButton: false
                            });
                        }
                    }, 'json').fail(function () {
                        jQuery.confirm({
                            title: 'Error de conexión',
                            content: 'No se pudo contactar con el servidor.',
                            confirmButton: 'Entendido',
                            cancelButton: false
                        });
                    });
                    return false;
                }
            });
        });

        cargar();
    });

})();
</script>

<?php include_once("../layouts/pie.php"); ?>