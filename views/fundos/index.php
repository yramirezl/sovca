<?php
include("../../lib/validar_session.php");
ValidaSession("../login");

if (session_status() === PHP_SESSION_NONE) session_start();
$__rol = isset($_SESSION['rolx']) ? (int)$_SESSION['rolx'] : 0;
?>
<?php include("../layouts/constantes.php") ?>
<?php include_once("../layouts/cabecera.php") ?>

<?php if ($__rol == 1 || $__rol == 4): ?>
<!-- ══════════════════════════════════════════════════════════════ -->
<!-- VISTA ADMIN — CONSULTA · MODO SOPORTE · ASISTENTE DE FUSIÓN    -->
<!-- Un solo listado, tres modos claros. Sin editar ni eliminar:    -->
<!-- la edición es del propietario y la baja es inactivar su cuenta. -->
<!-- ══════════════════════════════════════════════════════════════ -->

<style>
/* ---------- Estructura ---------- */
.fnd-wrap { max-width: 1240px; margin: 0 auto; padding-bottom: 34px; }

/* ---------- Encabezado ---------- */
.fnd-hero {
    background: linear-gradient(135deg, #234e35 0%, #2c6e46 55%, #2f8f4a 100%);
    border-radius: 14px;
    padding: 22px 26px;
    color: #fff;
    margin-bottom: 16px;
    box-shadow: 0 8px 24px rgba(35, 78, 53, .22);
    display: flex;
    align-items: center;
    gap: 18px;
    position: relative;
    overflow: hidden;
}
.fnd-hero::before {
    content: ""; position: absolute; top: -60px; right: -60px;
    width: 220px; height: 220px; border-radius: 50%;
    background: radial-gradient(circle, rgba(255,255,255,.14) 0%, transparent 70%);
    pointer-events: none;
}
.fnd-hero-icon {
    flex-shrink: 0; width: 58px; height: 58px; border-radius: 14px;
    background: rgba(255,255,255,.18); border: 1px solid rgba(255,255,255,.28);
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 26px; box-shadow: 0 4px 12px rgba(0,0,0,.15);
}
.fnd-hero-info { flex: 1; min-width: 0; position: relative; z-index: 1; }
.fnd-hero-info h2 { margin: 0 0 4px; font-size: 22px; font-weight: 800; color: #fff; }
.fnd-hero-info p { margin: 0; font-size: 13px; line-height: 1.5; color: rgba(255,255,255,.88); max-width: 760px; }
.fnd-hero-action { flex-shrink: 0; position: relative; z-index: 1; }

/* ---------- Botones ---------- */
.fnd-btn {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 9px 16px; border: none; border-radius: 8px;
    font-size: 13px; font-weight: 700; letter-spacing: .3px;
    cursor: pointer; transition: all .15s ease;
    text-decoration: none !important; white-space: nowrap; line-height: 1.2;
}
.fnd-btn i { font-size: 13px; }
.fnd-btn:disabled { opacity: .45; cursor: not-allowed; transform: none !important; }
.fnd-btn-primary { background: linear-gradient(135deg, #2f8f4a, #1b5e20); color: #fff !important; box-shadow: 0 3px 8px rgba(47,143,74,.28); }
.fnd-btn-primary:hover:not(:disabled) { box-shadow: 0 5px 14px rgba(47,143,74,.42); color: #fff !important; }
.fnd-btn-secondary { background: #fff; color: #5b6874 !important; border: 1px solid #d7e2ea; }
.fnd-btn-secondary:hover:not(:disabled) { border-color: #2f8f4a; color: #2f8f4a !important; background: #f4fbf6; }
.fnd-btn-ghost { background: transparent; color: #4b5a52 !important; border: 1px solid transparent; padding: 8px 12px; }
.fnd-btn-ghost:hover:not(:disabled) { background: rgba(27,94,32,.08); color: #1b5e20 !important; }

/* ---------- Indicadores ---------- */
.fnd-kpis {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 16px;
}
.fnd-kpi {
    background: #fff; border: 1px solid #e5e8eb; border-radius: 12px;
    padding: 12px 16px; display: flex; flex-direction: column; gap: 2px;
    box-shadow: 0 1px 3px rgba(0,0,0,.04);
    border-left: 4px solid #2f8f4a;
}
.fnd-kpi .kpi-num { font-size: 22px; font-weight: 800; color: #1b3a24; line-height: 1.1; }
.fnd-kpi .kpi-lbl { font-size: 11px; font-weight: 700; letter-spacing: .35px; text-transform: uppercase; color: #7b8794; }
.fnd-kpi.is-alerta { border-left-color: #e67e22; }
.fnd-kpi.is-alerta .kpi-num { color: #b45309; }

/* ---------- Selector de modo ---------- */
.fnd-modos {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
    margin-bottom: 14px;
}
.fnd-modo {
    display: flex; align-items: flex-start; gap: 12px; text-align: left;
    background: #fff; border: 1.5px solid #e2e8ee; border-radius: 12px;
    padding: 13px 15px; cursor: pointer;
    transition: border-color .15s ease, box-shadow .15s ease, background .15s ease, transform .15s ease;
    font-family: inherit;
}
.fnd-modo:hover { border-color: #b9d9c3; background: #fafefb; transform: translateY(-1px); }
.fnd-modo.is-active {
    border-color: #2f8f4a;
    background: linear-gradient(180deg, #f2fbf5 0%, #ffffff 70%);
    box-shadow: 0 0 0 3px rgba(47,143,74,.12), 0 4px 14px rgba(47,143,74,.12);
}
.fnd-modo .fm-ic {
    flex-shrink: 0; width: 36px; height: 36px; border-radius: 10px;
    display: inline-flex; align-items: center; justify-content: center;
    background: #eef4f0; color: #4a6b56; font-size: 15px;
    transition: background .15s ease, color .15s ease;
}
.fnd-modo.is-active .fm-ic { background: #2f8f4a; color: #fff; }
.fnd-modo .fm-txt { min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.fnd-modo strong { font-size: 13.5px; font-weight: 800; color: #233e2d; letter-spacing: .1px; }
.fnd-modo small { font-size: 11.5px; line-height: 1.4; color: #7b8794; }
.fnd-modo.is-active strong { color: #1b5e20; }

/* ---------- Panel ---------- */
.fnd-panel {
    background: #fff; border: 1px solid #e5e8eb; border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0,0,0,.04);
    overflow: hidden;
}
.fnd-hint {
    display: flex; align-items: flex-start; gap: 10px; flex-wrap: wrap;
    padding: 11px 18px;
    background: #f4f8f5; border-bottom: 1px solid #e3ede6;
    font-size: 12.5px; line-height: 1.55; color: #3f5348;
}
.fnd-hint > i { color: #2f8f4a; font-size: 15px; margin-top: 1px; flex-shrink: 0; }
.fnd-hint > #fnd-hint-texto { flex: 1 1 480px; min-width: 0; }
.fnd-hint b { color: #1b3a24; }
.fnd-hint .fnd-hint-cuenta { font-weight: 800; color: #1b5e20; white-space: nowrap; }
.fnd-hint .fnd-hint-link {
    flex-shrink: 0;
    font-size: 12px; font-weight: 700; color: #2f8f4a;
    background: #fff; border: 1px solid #cfe3d6; border-radius: 7px;
    padding: 5px 11px; cursor: pointer; text-decoration: none !important;
    display: none; align-items: center; gap: 6px;
}
.fnd-hint .fnd-hint-link:hover { background: #f2fbf5; border-color: #2f8f4a; }
.fnd-modo-fusion .fnd-hint .fnd-hint-link { display: inline-flex; }

/* Casilla para incluir los fundos sin propietario activo (solo modo soporte) */
.fnd-hint .fnd-hint-check {
    display: none; align-items: center; gap: 7px; flex-shrink: 0;
    cursor: pointer; user-select: none;
    font-size: 11.5px; font-weight: 700; color: #43604e;
    background: #fff; border: 1px solid #cfe3d6; border-radius: 7px;
    padding: 5px 11px; white-space: nowrap;
}
.fnd-hint .fnd-hint-check:hover { background: #f2fbf5; border-color: #2f8f4a; }
.fnd-hint .fnd-hint-check input {
    width: 14px; height: 14px; margin: 0; cursor: pointer; accent-color: #2f8f4a;
}
.fnd-modo-soporte .fnd-hint .fnd-hint-check { display: inline-flex; }
.fnd-modo-soporte .fnd-hint .fnd-hint-check + .fnd-hint-link { margin-left: 8px; }

/* ---------- Tabla ---------- */
.fnd-table-wrap { padding: 6px 16px 14px; }
.fnd-table-wrap table.dataTable { margin: 0 !important; border-collapse: collapse !important; width: 100% !important; }
.fnd-table-wrap table.dataTable thead th {
    padding: 8px 10px !important; font-size: 10.5px !important; font-weight: 800 !important;
    text-transform: uppercase !important; letter-spacing: .45px !important;
    line-height: 1.15 !important; white-space: nowrap !important; vertical-align: middle !important;
    color: #5b6b60 !important; background: #f7faf8 !important; border-bottom: 2px solid #e3ede6 !important;
}
.fnd-table-wrap table.dataTable tbody td {
    padding: 7px 10px !important; font-size: 12.5px !important; line-height: 1.25 !important;
    vertical-align: middle !important; white-space: nowrap !important;
    overflow: hidden !important; text-overflow: ellipsis !important;
}
.fnd-table-wrap table.dataTable tbody tr:hover { background: #f6fbf7 !important; }
.fnd-table-wrap table.dataTable tbody tr.is-foco { background: #e8f5e9 !important; }

.fnd-nombre {
    font-weight: 700; color: #233e2d;
    display: inline-block; max-width: 210px; vertical-align: bottom;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.fnd-vacio { color: #b0bcc4; }
.fnd-tag {
    display: inline-block; padding: 2px 8px; border-radius: 20px;
    background: #eef4f0; color: #43604e; font-size: 10.5px; font-weight: 800; letter-spacing: .3px;
}
.fnd-prop { display: inline-flex; align-items: center; gap: 6px; min-width: 0; max-width: 100%; }
.fnd-prop .p-user {
    font-weight: 600; color: #33463b;
    max-width: 112px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.fnd-prop .p-user.is-vacio { color: #94a3b8; font-weight: 600; }
.fnd-prop .p-estado {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 10px; font-weight: 800; letter-spacing: .3px; text-transform: uppercase;
    padding: 2px 7px; border-radius: 20px;
}
.fnd-prop .p-estado.ok { background: #e8f5e9; color: #1b5e20; }
.fnd-prop .p-estado.off { background: #fff3e0; color: #b45309; }
.fnd-prop .p-estado.none { background: #f1f5f9; color: #64748b; }
.fnd-prop .p-estado i { font-size: 9px; }
.fnd-num { font-variant-numeric: tabular-nums; font-weight: 700; color: #33463b; }
.fnd-num.is-cero { color: #b0bcc4; font-weight: 600; }

.fnd-row-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 5px 12px; border-radius: 7px; border: 1px solid #cfe3d6;
    background: #fff; color: #1b5e20 !important; font-size: 11.5px; font-weight: 800;
    cursor: pointer; transition: all .15s ease; text-decoration: none !important;
}
.fnd-row-btn:hover { background: #2f8f4a; border-color: #2f8f4a; color: #fff !important; box-shadow: 0 3px 9px rgba(47,143,74,.3); }
.fnd-row-btn i { font-size: 11px; }

.fnd-chk { width: 17px; height: 17px; margin: 0; cursor: pointer; accent-color: #2f8f4a; vertical-align: middle; }

.fnd-table-wrap .dataTables_length,
.fnd-table-wrap .dataTables_filter { padding: 6px 0 !important; margin: 0 0 8px !important; font-size: 12.5px !important; }
.fnd-table-wrap .dataTables_length select { padding: 3px 8px !important; height: 30px !important; font-size: 12.5px !important; }
.fnd-table-wrap .dataTables_filter input {
    padding: 4px 10px 4px 32px !important; height: 30px !important; font-size: 12.5px !important;
    border-radius: 6px; border: 1px solid #d7e2ea; background-color: #f8fbf9;
    background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%23999' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><circle cx='11' cy='11' r='8'/><line x1='21' y1='21' x2='16.65' y2='16.65'/></svg>");
    background-repeat: no-repeat; background-position: 10px center; background-size: 14px 14px;
    box-sizing: border-box;
}
.fnd-table-wrap .dataTables_filter input:focus {
    outline: none; border-color: #2f8f4a; background-color: #fff;
    box-shadow: 0 0 0 3px rgba(47,143,74,.12);
}
.fnd-table-wrap .dataTables_info,
.fnd-table-wrap .dataTables_paginate { padding: 8px 0 0 !important; margin: 8px 0 0 !important; font-size: 12px !important; }
.fnd-table-wrap .dataTables_paginate .paginate_button {
    padding: 3px 9px !important; font-size: 12px !important; min-width: 28px !important;
    line-height: 1.2 !important; margin: 0 2px !important; border-radius: 6px !important;
}

/* ---------- Barra de selección (fusión) ---------- */
.fnd-barra {
    display: none; align-items: center; gap: 12px; flex-wrap: wrap;
    padding: 11px 18px;
    background: linear-gradient(135deg, #f4fbf6 0%, #e8f5e9 100%);
    border-top: 1px solid #c8e6c9;
    font-size: 12.5px; color: #1b5e20; font-weight: 600;
}
.fnd-barra.is-visible { display: flex; }
.fnd-barra .fb-icon {
    width: 27px; height: 27px; border-radius: 50%; background: #2f8f4a; color: #fff;
    display: inline-flex; align-items: center; justify-content: center; font-size: 12px; flex-shrink: 0;
}
.fnd-barra .fb-count { font-weight: 800; font-size: 14px; }
.fnd-barra .fb-acciones { margin-left: auto; display: flex; align-items: center; gap: 4px; }
.fnd-barra .fb-limpiar {
    background: none; border: none; color: #1b5e20; font-size: 11.5px; font-weight: 700;
    cursor: pointer; padding: 6px 10px; border-radius: 6px;
}
.fnd-barra .fb-limpiar:hover { background: rgba(27,94,32,.10); }
.fnd-barra .fb-sep { width: 1px; height: 20px; background: #c8e6c9; margin: 0 6px; }

/* ---------- Nota de administración ---------- */
.fnd-nota {
    display: flex; gap: 11px; align-items: flex-start;
    margin-top: 14px; padding: 13px 17px;
    background: #fbfcfb; border: 1px dashed #d9e5dc; border-radius: 11px;
    font-size: 12px; line-height: 1.6; color: #5b6b60;
}
.fnd-nota > i { color: #7ba789; font-size: 14px; margin-top: 2px; flex-shrink: 0; }
.fnd-nota p { margin: 0; }
.fnd-nota b { color: #33463b; }

/* ---------- Responsive ---------- */
@media (max-width: 991px) {
    .fnd-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .fnd-modos { grid-template-columns: 1fr; }
}
@media (max-width: 767px) {
    .fnd-hero { flex-direction: column; align-items: flex-start; gap: 14px; padding: 18px 20px; }
    .fnd-hero-action, .fnd-hero-action .fnd-btn { width: 100%; justify-content: center; }
    .fnd-table-wrap { padding: 4px 10px 12px; }
    .fnd-hint { padding: 10px 12px; font-size: 12px; }
    .fnd-barra { padding: 10px 12px; }
    .fnd-barra .fb-acciones { width: 100%; margin-left: 0; justify-content: space-between; }
}
</style>

<div class="fnd-wrap">

    <div class="message"></div>

    <div class="fnd-hero">
        <span class="fnd-hero-icon"><i class="fa fa-building-o"></i></span>
        <div class="fnd-hero-info">
            <h2>Fundos del sistema</h2>
            <p>Supervisa los fundos registrados, entra a uno en <b>modo soporte</b> para corregir sus datos o <b>fusiona</b> varios fundos de un mismo productor en una sola cuenta multifundo.</p>
        </div>
        <div class="fnd-hero-action">
            <a href="../inicio/" class="fnd-btn fnd-btn-secondary">
                <i class="fa fa-reply"></i> Regresar
            </a>
        </div>
    </div>

    <div class="fnd-kpis">
        <div class="fnd-kpi">
            <span class="kpi-num" id="kpi-fundos">—</span>
            <span class="kpi-lbl">Fundos activos</span>
        </div>
        <div class="fnd-kpi">
            <span class="kpi-num" id="kpi-con-prop">—</span>
            <span class="kpi-lbl">Con propietario activo</span>
        </div>
        <div class="fnd-kpi" id="kpi-sin-wrap">
            <span class="kpi-num" id="kpi-sin-prop">—</span>
            <span class="kpi-lbl">Sin propietario activo</span>
        </div>
        <div class="fnd-kpi">
            <span class="kpi-num" id="kpi-animales">—</span>
            <span class="kpi-lbl">Animales registrados</span>
        </div>
    </div>

    <div class="fnd-modos" role="tablist" aria-label="Acciones sobre los fundos">
        <button type="button" class="fnd-modo is-active" data-modo="consulta" role="tab" aria-selected="true">
            <span class="fm-ic"><i class="fa fa-list-ul"></i></span>
            <span class="fm-txt">
                <strong>Consultar fundos</strong>
                <small>Propietario, plan, animales y usuarios de cada fundo.</small>
            </span>
        </button>
        <button type="button" class="fnd-modo" data-modo="soporte" role="tab" aria-selected="false">
            <span class="fm-ic"><i class="fa fa-user-secret"></i></span>
            <span class="fm-txt">
                <strong>Modo soporte</strong>
                <small>Entra a un fundo para revisar y corregir sus datos.</small>
            </span>
        </button>
        <button type="button" class="fnd-modo" data-modo="fusion" role="tab" aria-selected="false">
            <span class="fm-ic"><i class="fa fa-object-group"></i></span>
            <span class="fm-txt">
                <strong>Asistente de fusión</strong>
                <small>Une varios fundos de un productor en una cuenta.</small>
            </span>
        </button>
    </div>

    <div class="fnd-panel" id="fnd-panel">

        <div class="fnd-hint" id="fnd-hint">
            <i class="fa fa-circle-info"></i>
            <span id="fnd-hint-texto"></span>
            <label class="fnd-hint-check" id="fnd-hint-check"
                   title="Los fundos sin propietario activo no tienen licencia vigente, así que casi nunca necesitan soporte.">
                <input type="checkbox" id="chk-inactivos">
                Ver también los inactivos
            </label>
            <a href="unificar" class="fnd-hint-link" id="fnd-hint-link">
                <i class="fa fa-wand-magic-sparkles"></i> Abrir el asistente sin selección
            </a>
        </div>

        <div class="fnd-table-wrap dataTables_wrapper form-inline dt-bootstrap">
            <table id="tabla" class="table table-bordered table-hover">
                <thead>
                    <tr>
                        <th class="text-center" style="width:36px;"></th>
                        <th style="width:228px;">FUNDO</th>
                        <th style="width:122px;">#CID-RIF</th>
                        <th style="width:96px;">GANADERÍA</th>
                        <th style="width:198px;">PROPIETARIO</th>
                        <th class="text-right" style="width:78px;">ANIMALES</th>
                        <th class="text-right" style="width:78px;">USUARIOS</th>
                        <th style="width:96px;">PLAN</th>
                        <th style="width:96px;"></th>
                        <th style="display:none;">id</th>
                        <th style="display:none;">activo</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>

        <div class="fnd-barra" id="fnd-barra" aria-live="polite">
            <span class="fb-icon"><i class="fa fa-object-group"></i></span>
            <span><span class="fb-count" id="fb-count">0</span> fundo(s) seleccionado(s)</span>
            <div class="fb-acciones">
                <button type="button" class="fb-limpiar" id="fb-todos">
                    <i class="fa fa-check-square-o"></i> Marcar los visibles
                </button>
                <span class="fb-sep"></span>
                <button type="button" class="fb-limpiar" id="fb-limpiar">
                    <i class="fa fa-times"></i> Limpiar
                </button>
                <button type="button" class="fnd-btn fnd-btn-primary" id="fb-continuar" disabled>
                    Continuar al asistente <i class="fa fa-arrow-right"></i>
                </button>
            </div>
        </div>

    </div>

    <div class="fnd-nota">
        <i class="fa fa-shield"></i>
        <p><b>Cómo se administra un fundo.</b> La <b>edición</b> de sus datos la hace el propietario desde su propia cuenta, por eso aquí no hay botones de editar ni eliminar. Para <b>retirar</b> un fundo se inactiva la cuenta de su propietario: el fundo deja de estar disponible y los datos históricos se conservan; si la cuenta se reactiva, todo vuelve a estar accesible.</p>
    </div>

</div>

<link rel="stylesheet" href="../../plugins/confirma/jquery-confirm.min.css" type="text/css" />
<script type="text/javascript" src="../../plugins/confirma/jquery-confirm.min.js"></script>
<script type="text/javascript">
$(document).ready(function () {

    /* ============================================================
       DATOS Y ESTADO
       ============================================================ */
    var DATOS = [];
    var MODO  = 'consulta';
    /* Modo soporte: por defecto solo los fundos con propietario ACTIVO.
       Un fundo sin propietario activo no tiene licencia vigente, así que casi
       nunca necesita asistencia; la casilla del aviso permite verlos igual. */
    var soloActivos = true;

    function esc(t) {
        return String(t === null || t === undefined ? '' : t)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
    function num(n) {
        n = Number(n) || 0;
        return n.toLocaleString('es-VE');
    }
    function recorta(t, max) {
        t = String(t === null || t === undefined ? '' : t).trim();
        return (t.length > max) ? (t.substring(0, max - 1) + '…') : t;
    }
    function nomFila(row) {
        return row && row.nombre ? String(row.nombre).trim() : 'Fundo';
    }

    /* ============================================================
       TABLA
       ============================================================ */
    var dt = $('#tabla').DataTable({
        "ajax": {
            "url": "../../data_json/data_mfundos",
            "dataSrc": function (json) { DATOS = json || []; return DATOS; }
        },
        "columns": [
            { "data": null, "orderable": false, "searchable": false, "className": "text-center",
              "render": function (d, type, row) {
                  if (type !== 'display') return '';
                  return '<input type="checkbox" class="fnd-chk" value="' + esc(row.id) + '" aria-label="Seleccionar ' + esc(nomFila(row)) + '">';
              } },
            { "data": "nombre",
              "render": function (d, type) {
                  if (type !== 'display') return d || '';
                  return '<span class="fnd-nombre" title="' + esc(d) + '">' + esc(recorta(d, 42)) + '</span>';
              } },
            { "data": "cid_rif",
              "render": function (d, type) {
                  if (type !== 'display') return d || '';
                  if (!d) return '<span class="fnd-vacio">—</span>';
                  return '<span title="' + esc(d) + '">' + esc(recorta(d, 20)) + '</span>';
              } },
            { "data": "ganaderia",
              "render": function (d, type) {
                  if (type !== 'display') return d || '';
                  return '<span class="fnd-tag">' + esc(d || '—') + '</span>';
              } },
            { "data": "propietario",
              "render": function (d, type, row) {
                  if (type !== 'display') return d || '';
                  var est = row.propietario_estado;
                  var chip, user;
                  if (!d) {
                      user = '<span class="p-user is-vacio">Sin propietario</span>';
                      chip = '<span class="p-estado none"><i class="fa fa-minus"></i> Ninguno</span>';
                  } else {
                      user = '<span class="p-user" title="' + esc(d) + '">' + esc(recorta(d, 18)) + '</span>';
                      chip = (Number(est) === 1)
                           ? '<span class="p-estado ok"><i class="fa fa-check"></i> Activo</span>'
                           : '<span class="p-estado off"><i class="fa fa-pause"></i> Inactivo</span>';
                  }
                  return '<span class="fnd-prop">' + user + chip + '</span>';
              } },
            { "data": "animales", "className": "text-right",
              "render": function (d, type) {
                  if (type !== 'display') return Number(d) || 0;
                  var n = Number(d) || 0;
                  return '<span class="fnd-num' + (n === 0 ? ' is-cero' : '') + '">' + num(n) + '</span>';
              } },
            { "data": "usuarios", "className": "text-right",
              "render": function (d, type) {
                  if (type !== 'display') return Number(d) || 0;
                  var n = Number(d) || 0;
                  return '<span class="fnd-num' + (n === 0 ? ' is-cero' : '') + '">' + num(n) + '</span>';
              } },
            { "data": "plan",
              "render": function (d, type) {
                  if (type !== 'display') return d || '';
                  return d ? esc(d) : '<span class="fnd-vacio">—</span>';
              } },
            { "data": null, "orderable": false, "searchable": false, "className": "text-right",
              "render": function (d, type, row) {
                  if (type !== 'display') return '';
                  return '<button type="button" class="fnd-row-btn js-entrar" data-id="' + esc(row.id) + '" '
                       + 'data-nombre="' + esc(nomFila(row)) + '"><i class="fa fa-user-secret"></i> Entrar</button>';
              } },
            { "data": "id", "visible": false },
            /* Columna oculta SOLO para ordenar: 0 = propietario activo, 1 = sin propietario activo.
               Así, en modo soporte, los fundos que sí pueden necesitar asistencia van primero. */
            { "data": null, "orderable": true, "searchable": false, "visible": false,
              "render": function (d, type, row) {
                  return Number(row.propietario_estado) === 1 ? 0 : 1;
              } }
        ],
        "order": [[1, "asc"]],
        "paging": true,
        "pageLength": 25,
        "lengthMenu": [[25, 50, 100, -1], [25, 50, 100, "Todos"]],
        "scrollX": true,
        "autoWidth": false,
        "language": {
            "search": "Buscar fundo, propietario o RIF:",
            "zeroRecords": "No hay fundos que coincidan con la búsqueda",
            "info": "Mostrando _START_ a _END_ de _TOTAL_ fundos",
            "infoEmpty": "Sin fundos",
            "infoFiltered": "(filtrado de _MAX_)",
            "lengthMenu": "_MENU_ por página",
            "paginate": { "first": "«", "last": "»", "next": "›", "previous": "‹" }
        },
        "initComplete": function () {
            actualizarKpis();
            // setTimeout 0: garantiza que la variable `dt` ya esté asignada.
            setTimeout(function () { aplicarModo(MODO); }, 0);
        }
    });

    /* ============================================================
       FILTRO DEL MODO SOPORTE
       Solo se da soporte a los fundos que tienen propietario activo:
       si la cuenta está inactiva es porque no tiene licencia vigente.
       La casilla «Ver también los inactivos» desactiva el filtro.
       ============================================================ */
    $.fn.dataTable.ext.search.push(function (settings, searchData, dataIndex) {
        if (!settings.nTable || settings.nTable.id !== 'tabla') return true;
        if (MODO !== 'soporte' || !soloActivos) return true;
        var fila = settings.aoData[dataIndex] ? settings.aoData[dataIndex]._aData : null;
        return fila ? (Number(fila.propietario_estado) === 1) : true;
    });

    /* ============================================================
       INDICADORES
       ============================================================ */
    function actualizarKpis() {
        var total = DATOS.length, conProp = 0, animales = 0;
        for (var i = 0; i < DATOS.length; i++) {
            var r = DATOS[i];
            if (Number(r.propietario_estado) === 1) conProp++;
            animales += Number(r.animales) || 0;
        }
        $('#kpi-fundos').text(num(total));
        $('#kpi-con-prop').text(num(conProp));
        $('#kpi-sin-prop').text(num(total - conProp));
        $('#kpi-animales').text(num(animales));
        $('#kpi-sin-wrap').toggleClass('is-alerta', (total - conProp) > 0);
    }

    /* ============================================================
       MODOS: consulta · soporte · fusión
       Cada modo decide QUÉ muestra el mismo listado, así nunca hay
       botones sueltos ni acciones "a ciegas" sobre una fila.
       ============================================================ */
    var HINT = {
        consulta: '<b>Vista de consulta.</b> Revisa el estado de cada fundo: propietario y su estado, plan contratado, animales registrados y usuarios con acceso. La edición la hace el propietario desde su cuenta.',
        soporte:  '<b>Modo soporte.</b> Solo fundos con <b>propietario activo</b> (sin licencia vigente no suele hacer falta soporte), ordenados por propietario y nombre. Pulsa <b>Entrar</b> o haz clic sobre la fila para trabajar dentro del fundo sin cambiar de cuenta ni de rol.',
        fusion:   '<b>Asistente de fusión.</b> Marca los fundos que pertenecen al mismo productor y pulsa <b>Continuar al asistente</b>: allí eliges la cuenta destino y ves el resumen antes de aplicar nada.'
    };

    /* Texto para la tabla vacía (cambia según el filtro del modo soporte). */
    function textoVacio() {
        if (MODO === 'soporte' && soloActivos) {
            return 'Ningún fundo tiene propietario activo. Marca «Ver también los inactivos» para ver el resto.';
        }
        return 'No hay fundos que coincidan con la búsqueda';
    }

    /* En modo soporte el aviso indica cuántos fundos se están listando. */
    function actualizarCuentaSoporte() {
        if (MODO !== 'soporte') return;
        var visibles = dt.rows({ search: 'applied' }).data().toArray().length;
        $('#fnd-hint-texto').append(
            ' <span class="fnd-hint-cuenta">' + visibles + ' de ' + DATOS.length + ' fundos</span>'
        );
    }

    function aplicarModo(modo) {
        MODO = modo;
        $('.fnd-modo').each(function () {
            var activo = $(this).data('modo') === modo;
            $(this).toggleClass('is-active', activo).attr('aria-selected', activo ? 'true' : 'false');
        });

        $('#fnd-panel').removeClass('fnd-modo-consulta fnd-modo-soporte fnd-modo-fusion').addClass('fnd-modo-' + modo);
        $('#fnd-hint-texto').html(HINT[modo] || '');

        // Columna 0 = selección (solo fusión) · Columna 8 = acción Entrar (solo soporte)
        dt.column(0).visible(modo === 'fusion', false);
        dt.column(8).visible(modo === 'soporte', false);

        dt.settings()[0].oLanguage.sZeroRecords = textoVacio();

        // El estado del filtro se toma de la casilla (el navegador puede restaurarla).
        soloActivos = !$('#chk-inactivos').prop('checked');

        // Orden: en soporte, primero los fundos con propietario ACTIVO (columna oculta 10)
        // y después por nombre; en los demás modos, solo por nombre.
        dt.order(modo === 'soporte' ? [[10, 'asc'], [1, 'asc']] : [[1, 'asc']]);
        // draw explícito: `order()` no vuelve a pasar el filtro personalizado.
        dt.draw(false);

        $('#fnd-barra').toggleClass('is-visible', modo === 'fusion');
        $('#tabla tbody tr').removeClass('is-foco');
        limpiarSeleccion();
        dt.columns.adjust();
        actualizarCuentaSoporte();
    }

    $('.fnd-modo').on('click', function () { aplicarModo($(this).data('modo')); });

    $('#chk-inactivos').on('change', function () {
        soloActivos = !this.checked;
        dt.settings()[0].oLanguage.sZeroRecords = textoVacio();
        dt.draw(false);
        $('#fnd-hint-texto').html(HINT[MODO] || '');
        actualizarCuentaSoporte();
    });

    /* ============================================================
       SELECCIÓN (modo fusión)
       ============================================================ */
    function idsSeleccionados() {
        var ids = [];
        $('#tabla tbody .fnd-chk:checked').each(function () { ids.push($(this).val()); });
        return ids;
    }
    function actualizarBarra() {
        var n = idsSeleccionados().length;
        $('#fb-count').text(n);
        $('#fb-continuar').prop('disabled', n === 0);
    }
    function limpiarSeleccion() {
        $('#tabla tbody .fnd-chk').prop('checked', false);
        actualizarBarra();
    }

    $('#tabla tbody').on('change', '.fnd-chk', actualizarBarra);
    $('#tabla tbody').on('click', '.fnd-chk', function (e) { e.stopPropagation(); });

    $('#fb-limpiar').on('click', limpiarSeleccion);

    $('#fb-todos').on('click', function () {
        var nodos = dt.rows({ search: 'applied' }).nodes();
        var marcar = $(nodos).find('.fnd-chk:not(:checked)').length > 0;
        $(nodos).find('.fnd-chk').prop('checked', marcar);
        actualizarBarra();
    });

    $('#fb-continuar').on('click', function () {
        var ids = idsSeleccionados();
        if (!ids.length) { return; }
        if (ids.length === 1) {
            $.confirm({
                title: '<i class="fa fa-info-circle" style="color:#e67e22"></i> Selecciona al menos dos fundos',
                content: '<div style="text-align:left;font-size:13px;">La fusión une <b>varios fundos</b> en una sola cuenta. Marca dos o más fundos del mismo productor y vuelve a intentarlo.</div>',
                confirmButton: 'Entendido', cancelButton: false, closeIcon: false
            });
            return;
        }
        window.location.href = 'unificar?preseleccion=' + encodeURIComponent(ids.join(','));
    });

    /* ============================================================
       MODO SOPORTE — entrar al fundo
       ============================================================ */
    $('#tabla tbody').on('click', '.js-entrar', function (e) {
        e.stopPropagation();
        confirmarSoporte($(this).data('id'), $(this).data('nombre'));
    });

    $('#tabla tbody').on('click', 'tr', function (e) {
        if (MODO !== 'soporte') return;
        if ($(e.target).closest('.js-entrar').length) return;
        var $btn = $(this).find('.js-entrar');
        if (!$btn.length) return;
        confirmarSoporte($btn.data('id'), $btn.data('nombre'));
    });

    function confirmarSoporte(id, nombre) {
        if (!id) return;
        $.confirm({
            title: '<i class="fa fa-user-secret" style="color:#2f8f4a"></i> Modo soporte',
            content:
                '<div style="text-align:left;font-size:13px;line-height:1.6;">'
              + '<p>Vas a entrar al fundo <b>' + esc(nombre) + '</b> en <b>modo soporte</b>.</p>'
              + '<p>Podrás consultar y corregir sus datos desde la web, <b>sin cambiar de cuenta ni de rol</b>: '
              + 'seguirás viendo el menú de gestión del fundo y también podrás volver a las pantallas de administración.</p>'
              + '<p style="color:#7b8794;">Aparecerá un aviso en la barra superior con el botón <b>Salir</b> y el acceso quedará registrado (quién, cuándo y por cuánto tiempo).</p>'
              + '<label for="soporte-motivo" style="display:block;margin-top:10px;font-weight:600;">Motivo (opcional)</label>'
              + '<input type="text" id="soporte-motivo" maxlength="200" class="form-control" '
              + 'placeholder="Ej.: revisar el control sanitario del lote 12">'
              + '</div>',
            confirmButton: 'Entrar al fundo',
            cancelButton: 'Cancelar',
            confirmButtonClass: 'btn-success',
            cancelButtonClass: 'btn-default',
            confirm: function () {
                var campo  = document.getElementById('soporte-motivo');
                var motivo = campo ? campo.value : '';

                $.post('../../controllers/mfundos_controller.php', {
                    action: 'activar_supervisor',
                    fundo_id: id,
                    motivo: motivo
                }, null, 'json').done(function (resp) {
                    if (resp && resp.ok) {
                        window.location.href = '../inicio/';
                    } else {
                        $.alert({
                            title: 'No se pudo entrar al fundo',
                            content: (resp && resp.msg) ? resp.msg : 'Intente de nuevo.',
                            confirmButton: 'Entendido'
                        });
                    }
                }).fail(function () {
                    $.alert({
                        title: 'No se pudo entrar al fundo',
                        content: 'Error de comunicación con el servidor.',
                        confirmButton: 'Entendido'
                    });
                });
            }
        });
    }

    /* Atajo: foco visual en la fila que se está mirando */
    $('#tabla tbody').on('mouseenter', 'tr', function () {
        if (MODO !== 'soporte') return;
        $('#tabla tbody tr').removeClass('is-foco');
        $(this).addClass('is-foco');
    });

});
</script>

<?php elseif ($__rol == 2): ?>
<!-- ══════════════════════════════════════════════════════════════ -->
<!-- VISTA PRODUCTOR — CARDS "MIS FUNDOS" (DISEÑO ORIGINAL)         -->
<!-- ══════════════════════════════════════════════════════════════ -->

<style>
.mf-header {
    display: flex; align-items: center; justify-content: space-between;
    background: #fff; border-radius: 8px; padding: 16px 20px; margin-bottom: 16px;
    border: 1px solid #e5e8eb; box-shadow: 0 1px 3px rgba(0,0,0,.04);
}
.mf-header h2 { margin: 0; font-size: 20px; font-weight: 700; color: #233e2d; }
.mf-header .mf-cuota {
    display: inline-flex; align-items: center; gap: 8px;
    background: #e8f5e9; color: #1b5e20; padding: 6px 14px; border-radius: 20px;
    font-size: 13px; font-weight: 700;
}
.mf-header .mf-cuota.lleno { background: #fff3e0; color: #e65100; }
.mf-header .mf-cuota .num { font-size: 15px; }
.mf-progress { width: 100%; height: 6px; background: #e5e8eb; border-radius: 3px; margin-top: 10px; overflow: hidden; }
.mf-progress .bar { height: 100%; background: linear-gradient(90deg, #2f8f4a, #7fd07a); transition: width .4s ease; }
.mf-progress.lleno .bar { background: linear-gradient(90deg, #e65100, #ff9800); }

.mf-cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 16px;
}
.mf-card {
    background: #fff; border-radius: 10px; overflow: hidden;
    border: 1px solid #e5e8eb; transition: all .2s ease;
    display: flex; flex-direction: column;
    position: relative;
}
.mf-card:hover { box-shadow: 0 6px 18px rgba(0,0,0,.08); transform: translateY(-2px); }
.mf-card.activo {
    border: 2px solid #2f8f4a;
    box-shadow: 0 0 0 4px rgba(47,143,74,.12), 0 6px 20px rgba(47,143,74,.18);
    background: linear-gradient(180deg, #f4fbf6 0%, #ffffff 60%);
}
.mf-card.activo::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: linear-gradient(90deg, #2f8f4a, #7fd07a, #2f8f4a);
    background-size: 200% 100%;
    animation: mf-shine 3s linear infinite;
}
@keyframes mf-shine {
    0%   { background-position: 0% 50%; }
    100% { background-position: 200% 50%; }
}
.mf-card .mfc-head {
    display: flex; align-items: center; gap: 12px;
    padding: 14px 16px; background: #f8fafc;
    border-bottom: 1px solid #e5e8eb;
}
.mf-card.activo .mfc-head { background: #e8f5e9; }
.mf-card .mfc-avatar {
    width: 44px; height: 44px; border-radius: 10px;
    background: linear-gradient(135deg, #234e35, #2c6e46); color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px; font-weight: 700; flex-shrink: 0;
}
.mf-card.activo .mfc-avatar {
    background: linear-gradient(135deg, #2f8f4a, #4caf50);
    box-shadow: 0 0 0 3px rgba(47,143,74,.2);
}
.mf-card .mfc-title { flex: 1; min-width: 0; }
.mf-card .mfc-title h4 {
    margin: 0 0 2px 0; font-size: 15px; font-weight: 700; color: #233e2d;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.mf-card .mfc-title small {
    color: #8895a4; font-size: 11px;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: block;
}
.mf-card .mfc-badge {
    padding: 4px 12px; border-radius: 12px; font-size: 10px;
    font-weight: 800; letter-spacing: .5px; text-transform: uppercase;
    flex-shrink: 0;
}
.mf-card .mfc-badge.activo {
    background: linear-gradient(135deg, #2f8f4a, #4caf50);
    color: #fff;
    box-shadow: 0 2px 6px rgba(47,143,74,.35);
}
.mf-card .mfc-badge.activo::before { content: '✓ '; margin-right: 2px; }
.mf-card .mfc-badge.inactivo { background: #f5f5f5; color: #999; }
.mf-card .mfc-body { padding: 14px 16px; flex: 1; }
.mf-card .mfc-stat {
    display: flex; justify-content: space-between; align-items: center;
    font-size: 12.5px; padding: 6px 0; border-bottom: 1px dashed #eef1f4;
}
.mf-card .mfc-stat:last-child { border-bottom: none; }
.mf-card .mfc-stat .lbl { color: #8895a4; }
.mf-card .mfc-stat .val { color: #233e2d; font-weight: 600; }
.mf-card .mfc-bar { height: 4px; background: #eef1f4; border-radius: 2px; margin-top: 4px; overflow: hidden; }
.mf-card .mfc-bar .fill { height: 100%; background: linear-gradient(90deg, #3c8dbc, #5dade2); }
.mf-card .mfc-bar.warn .fill { background: linear-gradient(90deg, #f39c12, #f7b955); }
.mf-card .mfc-bar.full .fill { background: linear-gradient(90deg, #e74c3c, #ec7063); }
.mf-card .mfc-foot {
    padding: 10px 16px; background: #fafbfc;
    border-top: 1px solid #eef1f4;
    display: flex; gap: 6px; justify-content: flex-end;
}
.mf-card.activo .mfc-foot { background: #f0faf3; }
.mf-card .mfc-foot .btn { font-size: 11.5px; padding: 5px 12px; }

.mf-empty {
    text-align: center; padding: 60px 20px;
    background: #fff; border-radius: 10px; border: 1px dashed #d5dbe1;
}
.mf-empty i { font-size: 56px; color: #cfd6dd; }
.mf-empty h3 { color: #5a6874; margin: 16px 0 8px; font-size: 18px; }
.mf-empty p { color: #8895a4; margin-bottom: 20px; }
.mf-limit-banner {
    background: #fff8e1; border-left: 4px solid #f39c12;
    padding: 12px 16px; border-radius: 6px; margin-bottom: 16px;
    color: #7a5c00; font-size: 13px;
}
.mf-limit-banner strong { color: #5c4500; }

.mf-detail-overlay {
    position: fixed; inset: 0; z-index: 1040;
    background: rgba(30, 40, 50, .22);
    opacity: 0; pointer-events: none;
    transition: opacity .2s ease;
}
.mf-detail-overlay.open { opacity: 1; pointer-events: auto; }
.mf-detail-drawer {
    position: fixed; top: 0; right: 0; bottom: 0; z-index: 1050;
    display: flex; flex-direction: column;
    width: 500px; max-width: 92vw;
    background: #fff;
    box-shadow: -4px 0 20px rgba(0,0,0,.16);
    transform: translateX(100%);
    transition: transform .28s cubic-bezier(.25,.8,.25,1);
}
.mf-detail-drawer.open { transform: translateX(0); }
.mf-detail-header {
    background: linear-gradient(135deg, #234e35, #2c6e46);
    color: #fff; border-radius: 5px 5px 0 0;
    padding: 14px 18px;
    display: flex; align-items: center; justify-content: space-between;
    gap: 12px; flex-shrink: 0;
}
.mf-detail-header .modal-title { margin: 0; font-weight: 700; font-size: 16px; }
.mf-detail-close {
    background: rgba(255,255,255,.12);
    border: 1px solid rgba(255,255,255,.35);
    border-radius: 4px;
    color: #fff;
    padding: 6px 10px;
    font-size: 12px;
    white-space: nowrap;
}
.mf-detail-close:hover { background: rgba(255,255,255,.22); }
.mf-detail-body { flex: 1 1 auto; min-height: 0; overflow-y: auto; padding: 18px; background: #f8fafc; }
.mf-detail-footer { display: flex; justify-content: flex-end; gap: 8px; padding: 12px 18px; border-top: 1px solid #e5e8eb; background: #fff; flex-shrink: 0; }
@media (max-width: 767px) {
    .mf-detail-drawer { width: 100%; max-width: 100%; }
    .mf-detail-body { padding: 14px; }
}
.det-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
    gap: 10px; margin-bottom: 20px;
}
.det-stat {
    background: #f8fafc; border: 1px solid #e5e8eb;
    border-radius: 8px; padding: 12px; text-align: center;
}
.det-stat .det-num { font-size: 24px; font-weight: 700; color: #233e2d; line-height: 1; }
.det-stat .det-lbl { font-size: 10.5px; color: #8895a4; text-transform: uppercase; letter-spacing: .5px; margin-top: 6px; }
.det-stat.primary .det-num { color: #2f8f4a; }
.det-stat.hembra .det-num { color: #d81b60; }
.det-stat.macho .det-num { color: #1976d2; }
.det-section-title {
    font-size: 12px; font-weight: 700; color: #5a6874;
    text-transform: uppercase; letter-spacing: .5px;
    margin: 20px 0 10px; padding-bottom: 6px;
    border-bottom: 2px solid #eef1f4;
}
.det-section-title:first-child { margin-top: 0; }
.det-cat-list { list-style: none; padding: 0; margin: 0; }
.det-cat-list li {
    display: flex; align-items: center; justify-content: space-between;
    padding: 8px 0; border-bottom: 1px dashed #eef1f4; font-size: 13px;
}
.det-cat-list li:last-child { border-bottom: none; }
.det-cat-list .cat-nombre { color: #233e2d; font-weight: 500; }
.det-cat-list .cat-cant {
    background: #e8f5e9; color: #1b5e20;
    padding: 2px 10px; border-radius: 12px; font-size: 11px; font-weight: 700;
}
.det-mov-list { list-style: none; padding: 0; margin: 0; }
.det-mov-list li {
    padding: 8px 10px; border-left: 3px solid #3c8dbc;
    background: #f8fafc; margin-bottom: 6px;
    border-radius: 4px; font-size: 12px;
}
.det-mov-list .mov-fecha { font-weight: 700; color: #233e2d; }
.det-mov-list .mov-tipo { color: #3c8dbc; font-weight: 600; }
.det-mov-list .mov-motivo { color: #666; }
.det-empty { color: #aaa; font-style: italic; font-size: 12px; padding: 8px 0; }
#modal_detalle_body .loading { text-align: center; padding: 40px 0; color: #888; }
#modal_detalle_body .loading i { font-size: 32px; margin-bottom: 8px; display: block; }

.mf-toast {
    position: fixed;
    bottom: 30px;
    right: 30px;
    background: #fff;
    border-left: 4px solid #2f8f4a;
    border-radius: 8px;
    box-shadow: 0 8px 30px rgba(0,0,0,.18);
    padding: 14px 18px;
    display: none;
    align-items: center;
    gap: 12px;
    z-index: 9999;
    max-width: 440px;
    animation: slideInUp .3s ease;
}
.mf-toast.show { display: flex; }
@keyframes slideInUp {
    from { transform: translateY(20px); opacity: 0; }
    to   { transform: translateY(0);    opacity: 1; }
}
.mf-toast-icon {
    width: 32px; height: 32px; border-radius: 50%;
    background: #e8f5e9; color: #2f8f4a;
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; font-weight: 700; flex-shrink: 0;
}
.mf-toast-text {
    flex: 1; font-size: 13px; color: #233e2d;
    line-height: 1.4;
}
.mf-toast-text strong { color: #2f8f4a; }
.mf-toast-link {
    color: #2f8f4a; font-weight: 700; font-size: 13px; text-decoration: none;
    white-space: nowrap;
}
.mf-toast-link:hover { text-decoration: underline; color: #1b5e20; }
.mf-toast-close {
    background: none; border: none; color: #bbb;
    font-size: 20px; line-height: 1; cursor: pointer;
    padding: 0 4px; flex-shrink: 0;
}
.mf-toast-close:hover { color: #666; }
</style>

<div class="row">
    <div class="col-md-12">

        <div class="mf-header">
            <div style="flex:1; min-width:0;">
                <h2>Mis Fundos</h2>
                <small style="color:#8895a4; display:block; margin-top:2px;">
                    Administrá todos tus fundos desde un solo lugar
                </small>
                <div class="mf-progress" id="mf_progress">
                    <div class="bar" id="mf_bar" style="width:0%"></div>
                </div>
            </div>
            <div style="text-align:right; margin-left:16px;">
                <div class="mf-cuota" id="mf_cuota">
                    <span class="num" id="cuota_actual">–</span>
                    <span style="opacity:.6;">/</span>
                    <span id="cuota_max">–</span>
                    <span style="font-weight:500; margin-left:4px;">fundos</span>
                </div>
            </div>
        </div>

        <div id="mf_limite_banner" class="mf-limit-banner" style="display:none;">
            <strong>⚠️ Alcanzaste el máximo de fundos permitidos.</strong>
            Contacta al administrador para ampliar tu plan si nnecesitas crear más.
        </div>

        <div id="mf_container" class="mf-cards"></div>

        <div id="mf_empty" class="mf-empty" style="display:none;">
            <i class="fa fa-building-o"></i>
            <h3>Aún no tenés fundos registrados</h3>
            <p>Creá tu primer fundo para comenzar a gestionar tus animales.</p>
            <button type="button" class="btn btn-success btn-lg" onclick="location.href='frm_registrar'">
                <i class="fa fa-plus"></i> Crear mi primer fundo
            </button>
        </div>

        <div id="mf_footer" style="text-align:center; margin-top:20px; display:none;">
            <button type="button" class="btn btn-success btn-lg" id="btn_add_fundo" onclick="location.href='frm_registrar'">
                <i class="fa fa-plus"></i> Agregar nuevo fundo
            </button>
            <div id="mf_restantes" style="margin-top:8px; color:#8895a4; font-size:12px;"></div>
        </div>

    </div>
</div>

<!-- MODAL: DETALLE DEL FUNDO -->
<div class="mf-detail-overlay" id="fundo_detail_overlay"></div>
<aside class="mf-detail-drawer" id="modalDetalleFundo" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="det_modal_title">
    <div class="mf-detail-header">
        <h3 class="modal-title" id="det_modal_title"><i class="fa fa-building-o"></i> Detalles del Fundo</h3>
        <button type="button" class="mf-detail-close" id="btn_salir_detalle_fundo"><i class="fa fa-sign-out"></i> Salir</button>
    </div>
    <div class="mf-detail-body" id="modal_detalle_body">
        <div class="loading"><i class="fa fa-spinner fa-spin"></i>Cargando detalles...</div>
    </div>
    <div class="mf-detail-footer">
        <button type="button" class="btn btn-success" id="btn_activar_desde_modal" style="display:none;">
            <i class="fa fa-check-circle"></i> Activar este fundo
        </button>
        <button type="button" class="btn btn-default" id="btn_cerrar_detalle_fundo"><i class="fa fa-times"></i> Cerrar</button>
    </div>
</aside>

<!-- Toast de cambio de fundo -->
<div class="mf-toast" id="mf_toast">
    <div class="mf-toast-icon">✓</div>
    <div class="mf-toast-text" id="mf_toast_text">
        Fundo cambiado correctamente
    </div>
    <a href="../inicio/" class="mf-toast-link" id="mf_toast_link">
        Ir al dashboard →
    </a>
    <button type="button" class="mf-toast-close" onclick="$('#mf_toast').removeClass('show')">×</button>
</div>

<script type="text/javascript" src="../../plugins/confirma/jquery-confirm.min.js"></script>
<link rel="stylesheet" href="../../plugins/confirma/jquery-confirm.min.css" type="text/css" />
<script>
var MF_FUNDO_ACTIVO = 0;
var MF_FUNDO_DETALLE = 0;

function abrirDetalleFundo() {
    $('#fundo_detail_overlay').addClass('open');
    $('#modalDetalleFundo').addClass('open').attr('aria-hidden', 'false');
    $('body').css('overflow', 'hidden');
}

function cerrarDetalleFundo() {
    $('#fundo_detail_overlay').removeClass('open');
    $('#modalDetalleFundo').removeClass('open').attr('aria-hidden', 'true');
    $('body').css('overflow', '');
}

$(document).ready(function() {

    $('#fundo_detail_overlay, #btn_salir_detalle_fundo, #btn_cerrar_detalle_fundo').on('click', cerrarDetalleFundo);
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#modalDetalleFundo').hasClass('open')) cerrarDetalleFundo();
    });

    function cargar_fundos(silent) {
        $.post("../../controllers/mfundos_controller", { action: "mis_fundos" }, function(data) {
            if (data.error) {
                if (!silent) $(".message").html('<div class="alert alert-danger">Error: ' + data.error + '</div>');
                return;
            }

            MF_FUNDO_ACTIVO = data.activo || 0;

            $("#cuota_actual").text(data.total);
            $("#cuota_max").text(data.max_fundos);
            $("#mf_cuota").removeClass("lleno");
            $("#mf_progress").removeClass("lleno");

            var pct = data.max_fundos > 0 ? Math.min(100, Math.round((data.total / data.max_fundos) * 100)) : 0;
            $("#mf_bar").css("width", pct + "%");

            if (data.total >= data.max_fundos) {
                $("#mf_cuota").addClass("lleno");
                $("#mf_progress").addClass("lleno");
                $("#mf_limite_banner").show();
                $("#btn_add_fundo").prop("disabled", true).addClass("disabled")
                    .html('<i class="fa fa-lock"></i> Límite alcanzado');
                $("#mf_restantes").text("No podés agregar más fundos hasta ampliar tu plan.");
            } else {
                $("#mf_limite_banner").hide();
                $("#btn_add_fundo").prop("disabled", false).removeClass("disabled")
                    .html('<i class="fa fa-plus"></i> Agregar nuevo fundo');
                var rest = data.restantes;
                $("#mf_restantes").text("Podés agregar " + rest + " fundo" + (rest !== 1 ? "s" : "") + " más");
            }

            if (data.total === 0) {
                $("#mf_empty").show();
                $("#mf_container").hide();
                $("#mf_footer").hide();
                return;
            }

            $("#mf_empty").hide();
            $("#mf_footer").show();

            var lista = data.fundos.slice().sort(function(a, b) {
                if (a.activo && !b.activo) return -1;
                if (!a.activo && b.activo) return 1;
                return 0;
            });

            var html = "";
            lista.forEach(function(f) {
                var inicial = (f.nombre || "?").charAt(0).toUpperCase();
                var pct_animales = f.max_reg > 0 ? Math.round((f.total_animales / f.max_reg) * 100) : 0;
                var bar_class = "";
                if (pct_animales >= 100)      bar_class = "full";
                else if (pct_animales >= 80)  bar_class = "warn";

                html += '<div class="mf-card ' + (f.activo ? 'activo' : '') + '" data-id="' + f.id + '">';
                html +=   '<div class="mfc-head">';
                html +=     '<div class="mfc-avatar">' + inicial + '</div>';
                html +=     '<div class="mfc-title">';
                html +=       '<h4>' + f.nombre + '</h4>';
                html +=       '<small>' + (f.rif || 'Sin RIF') + '</small>';
                html +=     '</div>';
                html +=     '<span class="mfc-badge ' + (f.activo ? 'activo' : 'inactivo') + '">' +
                            (f.activo ? 'ACTIVO' : 'INACTIVO') + '</span>';
                html +=   '</div>';
                html +=   '<div class="mfc-body">';
                html +=     '<div class="mfc-stat"><span class="lbl">Ganadería</span><span class="val">' + f.ganaderia + '</span></div>';
                html +=     '<div class="mfc-stat">';
                html +=       '<span class="lbl">Animales</span>';
                html +=       '<span class="val">' + f.total_animales + ' / ' + f.max_reg + '</span>';
                html +=     '</div>';
                html +=     '<div class="mfc-bar ' + bar_class + '"><div class="fill" style="width:' + Math.min(100, pct_animales) + '%"></div></div>';
                html +=     '<div class="mfc-stat" style="margin-top:10px;"><span class="lbl">Plan</span><span class="val">' + f.plan + '</span></div>';
                html +=     '<div class="mfc-stat"><span class="lbl">Usuarios</span><span class="val">' + f.total_usuarios + '</span></div>';
                html +=   '</div>';
                html +=   '<div class="mfc-foot">';
                html +=     '<button type="button" class="btn btn-default btn-detalle" data-id="' + f.id + '"><i class="fa fa-list-alt"></i> Ver detalles</button>';
                if (!f.activo) {
                    html += '<button type="button" class="btn btn-primary btn-activar" data-id="' + f.id + '"><i class="fa fa-play"></i> Activar</button>';
                } else {
                    html += '<button type="button" class="btn btn-default btn-editar" data-id="' + f.id + '"><i class="fa fa-pencil"></i> Editar</button>';
                }
                html +=   '</div>';
                html += '</div>';
            });

            $("#mf_container").html(html).show();

        }, "json");
    }

    /* VER DETALLES */
    $(document).on('click', '.btn-detalle', function() {
        var id = $(this).data('id');
        MF_FUNDO_DETALLE = id;

        $("#det_modal_title").html('<i class="fa fa-building-o"></i> Detalles del Fundo');
        $("#modal_detalle_body").html('<div class="loading"><i class="fa fa-spinner fa-spin"></i>Cargando detalles...</div>');
        $("#btn_activar_desde_modal").hide();
        abrirDetalleFundo();

        $.post("../../controllers/mfundos_controller", {
            action: "detalle_fundo",
            fundo_id: id
        }, function(resp) {
            if (!resp.ok) {
                $("#modal_detalle_body").html('<div class="alert alert-danger">' + (resp.msg || 'Error al cargar') + '</div>');
                return;
            }

            var f = resp.fundo;
            var s = resp.stats;

            $("#det_modal_title").html('<i class="fa fa-building-o"></i> ' + f.nombre);

            var html = '';

            html += '<div style="display:flex; align-items:center; gap:12px; margin-bottom:16px; padding-bottom:12px; border-bottom:1px solid #eef1f4;">';
            html += '  <div style="width:48px; height:48px; border-radius:10px; background:linear-gradient(135deg,#234e35,#2c6e46); color:#fff; display:flex; align-items:center; justify-content:center; font-size:22px; font-weight:700;">' + (f.nombre || '?').charAt(0).toUpperCase() + '</div>';
            html += '  <div style="flex:1;">';
            html += '    <div style="font-size:16px; font-weight:700; color:#233e2d;">' + f.nombre + '</div>';
            html += '    <div style="font-size:12px; color:#8895a4;">' + (f.rif || 'Sin RIF') + ' · ' + f.ganaderia + ' · Plan ' + f.plan + '</div>';
            html += '  </div>';
            html += '</div>';

            html += '<div class="det-stats-grid">';
            html += '  <div class="det-stat primary"><div class="det-num">' + s.total_animales + '</div><div class="det-lbl">Total Animales</div></div>';
            html += '  <div class="det-stat hembra"><div class="det-num">' + s.hembras + '</div><div class="det-lbl">Hembras</div></div>';
            html += '  <div class="det-stat macho"><div class="det-num">' + s.machos + '</div><div class="det-lbl">Machos</div></div>';
            if (s.donadores_activos > 0 || s.donadores > 0 || s.donadoras_activas > 0 || s.donadoras > 0) {
                html += '  <div class="det-stat"><div class="det-num">' + (s.donadores_activos + s.donadores + s.donadoras_activas + s.donadoras) + '</div><div class="det-lbl">Donadores / Donadoras</div></div>';
            }
            html += '</div>';

            var pct_cuota = s.max_reg > 0 ? Math.round((s.total_animales / s.max_reg) * 100) : 0;
            html += '<div style="margin-bottom:16px;">';
            html += '  <div style="display:flex; justify-content:space-between; font-size:12px; color:#8895a4; margin-bottom:4px;">';
            html += '    <span>Uso de cuota</span>';
            html += '    <span><strong style="color:#233e2d;">' + s.total_animales + '</strong> de ' + s.max_reg + ' (' + pct_cuota + '%)</span>';
            html += '  </div>';
            html += '  <div style="height:6px; background:#eef1f4; border-radius:3px; overflow:hidden;">';
            html += '    <div style="height:100%; width:' + Math.min(100, pct_cuota) + '%; background:linear-gradient(90deg,#2f8f4a,#7fd07a);"></div>';
            html += '  </div>';
            html += '</div>';

            html += '<div class="det-section-title">Animales por categoría</div>';
            if (resp.categorias && resp.categorias.length) {
                html += '<ul class="det-cat-list">';
                resp.categorias.forEach(function(c) {
                    html += '<li><span class="cat-nombre">' + c.categoria + '</span><span class="cat-cant">' + c.cantidad + '</span></li>';
                });
                html += '</ul>';
            } else {
                html += '<div class="det-empty">Sin categorías registradas.</div>';
            }

            if (resp.movimientos && resp.movimientos.length) {
                html += '<div class="det-section-title">Últimos movimientos</div>';
                html += '<ul class="det-mov-list">';
                resp.movimientos.forEach(function(m) {
                    html += '<li>';
                    html += '  <span class="mov-fecha">' + m.fecha + '</span> · ';
                    html += '  <span class="mov-tipo">' + m.tipo + '</span>';
                    if (m.motivo) html += '<br><span class="mov-motivo">' + m.motivo + '</span>';
                    html += '</li>';
                });
                html += '</ul>';
            }

            $("#modal_detalle_body").html(html);

            if (id !== MF_FUNDO_ACTIVO) {
                $("#btn_activar_desde_modal").show();
            } else {
                $("#btn_activar_desde_modal").hide();
            }

        }, "json").fail(function() {
            $("#modal_detalle_body").html('<div class="alert alert-danger">Error de conexión</div>');
        });
    });

    /* ACTIVAR FUNDO */
    function activar_fundo(id, callback) {
        return $.post("../../controllers/mfundos_controller.php", {
            action: "cambiar_fundo",
            fundo_id: id
        }, function(resp) {
            if (resp && resp.ok) {
                MF_FUNDO_ACTIVO = id;
                $(document).trigger('bovs:fundo-cambiado', [id, resp]);
                if (callback) callback(true, resp);
            } else {
                if (callback) callback(false, resp);
            }
        }, "json").fail(function() {
            if (callback) callback(false, {msg: 'Error de conexión'});
        });
    }

    $(document).on('click', '.btn-activar', function() {
        var $btn = $(this);
        var id = $btn.data('id');
        var $card = $btn.closest('.mf-card');
        var nombre = $card.find('.mfc-title h4').text();

        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Activando...');

        activar_fundo(id, function(ok, resp) {
            if (ok) {
                $("#mf_toast_text").html('Fundo <strong>' + nombre + '</strong> activado');
                $("#mf_toast").addClass("show");
                setTimeout(function() { $("#mf_toast").removeClass("show"); }, 8000);
                cargar_fundos(true);
                cerrarDetalleFundo();
            } else {
                alert((resp && resp.msg) ? resp.msg : 'Error al cambiar de fundo');
                $btn.prop('disabled', false).html('<i class="fa fa-play"></i> Activar');
            }
        });
    });

    $('#btn_activar_desde_modal').on('click', function() {
        var $btn = $(this);
        var id = MF_FUNDO_DETALLE;
        var nombre = $('#det_modal_title').text().replace(' Detalles del Fundo', '').trim();

        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Activando...');

        activar_fundo(id, function(ok, resp) {
            if (ok) {
                $("#mf_toast_text").html('Fundo <strong>' + nombre + '</strong> activado');
                $("#mf_toast").addClass("show");
                setTimeout(function() { $("#mf_toast").removeClass("show"); }, 8000);
                cerrarDetalleFundo();
                cargar_fundos(true);
            } else {
                alert((resp && resp.msg) ? resp.msg : 'Error al cambiar de fundo');
                $btn.prop('disabled', false).html('<i class="fa fa-check-circle"></i> Activar este fundo');
            }
        });
    });

    /* EDITAR FUNDO */
    $(document).on('click', '.btn-editar', function() {
        var id = $(this).data('id');
        window.location.href = "frm_editar?%$%=" + window.btoa(id);
    });

    /* INIT */
    cargar_fundos(false);

});
</script>

<?php else: ?>
<!-- ══════════════════════════════════════════════════════════════ -->
<!-- VISTA ENCARGADO                                                -->
<!-- ══════════════════════════════════════════════════════════════ -->
<div class="row">
    <div class="col-md-8 col-md-offset-2">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-building-o"></i> Mi Fundo Asignado</h3>
            </div>
            <div class="box-body" style="text-align:center; padding:30px;">
                <p style="color:#666;">
                    Como encargado, tu fundo está pre-asignado por el productor.<br>
                    Si nnecesitas cambiar de fundo, Contacta al administrador.
                </p>
                <a href="../inicio/" class="btn btn-primary" style="margin-top:12px;">
                    <i class="fa fa-arrow-left"></i> Volver al Inicio
                </a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include_once("../layouts/pie.php") ?>