<?php
include("../../lib/validar_session.php");
ValidaSession("../login");

$raw = isset($_GET['%$%']) ? $_GET['%$%'] : '';
$parametros = $raw ? base64_decode($raw) : '';
$id_animal = 0;
if ($parametros !== false && $parametros !== '') {
    if (strpos($parametros, '=') !== false) {
        $partes = explode('=', $parametros);
        $id_animal = (int)end($partes);
    } else {
        $id_animal = (int)$parametros;
    }
}
if ($id_animal <= 0) { die('ID de animal inválido'); }
?>
<?php include("../layouts/constantes.php") ?>
<?php include_once("../layouts/cabecera.php") ?>

<style>
.ficha-wrap { max-width: 1080px; margin: 0 auto; background: #fff; padding: 24px; border: 1px solid #e3e7ed; border-radius: 12px; box-shadow: 0 8px 28px rgba(15,23,42,.06); }
.ficha-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid #2f8f4a; padding-bottom: 14px; margin-bottom: 18px; gap: 16px; }
.ficha-header h2 { margin: 0; font-size: 20px; font-weight: 800; color: #234e35; letter-spacing: .08em; }
.ficha-header .fundo { font-size: 13px; color: #6b7480; }
.ficha-header .fundo strong { font-size: 16px; color: #234e35; }
.ficha-main { display: flex; gap: 22px; margin-bottom: 18px; align-items: flex-start; }
.ficha-foto { width: 170px; flex-shrink: 0; text-align: center; }
.ficha-foto img { width: 170px; height: 170px; object-fit: cover; border: 3px solid #dceee1; border-radius: 10px; background: #f4f7f6; }
.ficha-identidad { flex: 1; min-width: 0; }
.ficha-codigo { display: inline-block; font-size: 22px; font-weight: 800; color: #1b5e20; background: #f0fbf3; border: 1px solid #cfe3d6; border-radius: 8px; padding: 6px 12px; margin-bottom: 12px; word-break: break-word; }
.ficha-datos-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px 12px; }
.ficha-datos-grid--identidad { grid-template-columns: repeat(4, minmax(0, 1fr)); }
.ficha-datos-grid--3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.ficha-dato { display: flex; flex-direction: column; gap: 2px; min-width: 0; padding: 8px 10px; background: #f8fafc; border: 1px solid #eef1f4; border-left: 3px solid #dceee1; border-radius: 8px; }
.ficha-dato__label { font-size: 10.5px; font-weight: 700; color: #7b8794; text-transform: uppercase; letter-spacing: .06em; line-height: 1.25; }
.ficha-dato__valor { font-size: 13px; font-weight: 600; color: #233e2d; line-height: 1.35; word-break: break-word; }
.ficha-dato--full { grid-column: 1 / -1; }
.ficha-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-bottom: 18px; align-items: stretch; }
.ficha-panel { display: flex; flex-direction: column; min-width: 0; border: 1px solid #e3e7ed; border-radius: 10px; background: #fff; overflow: hidden; box-shadow: 0 2px 8px rgba(15,23,42,.03); }
.ficha-panel__head { display: flex; align-items: center; gap: 8px; padding: 11px 14px; background: #f0fbf3; border-bottom: 1px solid #dceee1; }
.ficha-panel__head i { color: #2f8f4a; font-size: 14px; }
.ficha-panel__head h4 { margin: 0; font-size: 12px; font-weight: 800; color: #234e35; letter-spacing: .06em; text-transform: uppercase; }
.ficha-panel__body { flex: 1; display: flex; flex-direction: column; gap: 10px; padding: 12px 14px; background: #fff; }
.ficha-panel__body .ficha-datos-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.ficha-obs-wrap { display: flex; flex-direction: column; gap: 4px; flex: 1; min-height: 84px; padding: 10px 12px; background: #f8fafc; border: 1px dashed #cfe3d6; border-radius: 8px; }
.ficha-obs { flex: 1; font-size: 13px; color: #233e2d; line-height: 1.5; white-space: pre-wrap; }
.ficha-tabla-full { width: 100%; font-size: 12px; border-collapse: separate; border-spacing: 0; }
.ficha-tabla-full th { background: #f0fbf3; color: #234e35; font-size: 10.5px; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; padding: 8px 10px; text-align: left; border-bottom: 1px solid #dceee1; }
.ficha-tabla-full td { padding: 8px 10px; border-bottom: 1px solid #eef1f4; color: #233e2d; vertical-align: top; }
.ficha-tabla-full tbody tr:nth-child(even) td { background: #fafbfc; }
.ficha-tabla-full tbody tr:last-child td { border-bottom: none; }
.ficha-footer { display: flex; justify-content: space-between; align-items: flex-end; margin-top: 20px; padding: 16px; border-top: 1px dashed #cfe3d6; background: #f8fafc; border-radius: 10px; gap: 20px; }
.ficha-qr { text-align: center; }
.ficha-qr img { width: 110px; height: 110px; }
.ficha-firma { text-align: center; width: 220px; }
.linea-firma { border-bottom: 1px solid #9aa6b2; height: 40px; margin-bottom: 4px; }
.ficha-botones { text-align: right; margin-top: 20px; }
.ficha-botones .btn { margin-left: 8px; margin-bottom: 6px; }

.ficha-cargando { text-align: center; padding: 80px 20px; color: #888; }
.ficha-cargando .spinner { display: inline-block; width: 40px; height: 40px; border: 4px solid #e0e0e0; border-top-color: #2f8f4a; border-radius: 50%; animation: spin 0.8s linear infinite; margin-bottom: 12px; }
@keyframes spin { to { transform: rotate(360deg); } }

.ficha-no-disponible { max-width: 520px; margin: 40px auto; background: #fff; border-radius: 12px; box-shadow: 0 8px 30px rgba(0,0,0,.08); overflow: hidden; text-align: center; }
.ficha-no-disponible .nd-header { background: linear-gradient(135deg, #dc3545, #c82333); color: #fff; padding: 30px 24px; }
.ficha-no-disponible .nd-icon { font-size: 56px; line-height: 1; margin-bottom: 10px; }
.ficha-no-disponible .nd-header h2 { border: none; color: #fff; font-size: 20px; margin: 0; padding: 0; letter-spacing: 1px; }
.ficha-no-disponible .nd-body { padding: 24px; }
.ficha-no-disponible .nd-msg { color: #666; font-size: 14px; line-height: 1.6; margin-bottom: 20px; }
.ficha-no-disponible .nd-codigo { display: inline-block; background: #f1f3f5; color: #495057; padding: 6px 14px; border-radius: 4px; font-family: monospace; font-size: 13px; margin-bottom: 20px; }

.banner-migracion { background: #f0fbf3; border-left: 4px solid #2f8f4a; border-radius: 6px; padding: 12px 16px; margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; font-size: 13px; }
.banner-migracion .info { flex: 1 1 auto; min-width: 200px; }
.banner-migracion .info strong { color: #234e35; display: block; margin-bottom: 4px; }
.banner-migracion .info small { color: #4a5f70; font-size: 12px; }
.banner-migracion .info code { background: #fff; padding: 1px 6px; border-radius: 3px; color: #1b5e20; font-weight: 600; font-size: 11.5px; }
.banner-migracion .btn { flex-shrink: 0; }

.hist-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(30, 40, 50, 0.35); z-index: 1040; opacity: 0; pointer-events: none; transition: opacity .25s ease; }
.hist-overlay.open { opacity: 1; pointer-events: auto; }
.hist-drawer { position: fixed; top: 0; right: 0; bottom: 0; width: 460px; max-width: 90vw; background: #fff; box-shadow: -4px 0 20px rgba(0,0,0,.15); z-index: 1050; display: flex; flex-direction: column; transform: translateX(100%); transition: transform .3s cubic-bezier(.25,.8,.25,1); }
.hist-drawer.open { transform: translateX(0); }
.hist-drawer-header { background: linear-gradient(135deg, #234e35, #2c6e46); color: #fff; padding: 14px 18px; display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; }
.hist-drawer-header h3 { margin: 0; font-size: 15px; font-weight: 700; letter-spacing: .3px; }
.hist-drawer-header .hist-close { background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.35); border-radius: 4px; color: #fff; font-size: 12px; line-height: 1.4; cursor: pointer; padding: 6px 10px; white-space: nowrap; }
.hist-drawer-header .hist-close:hover { background: rgba(255,255,255,.22); }
.hist-drawer-body { flex: 1 1 auto; overflow-y: auto; padding: 16px 18px; background: #f8fafc; }
.hist-origen-info { background: #fff; border: 1px solid #e5e8eb; border-radius: 8px; padding: 12px 14px; margin-bottom: 14px; font-size: 12.5px; }
.hist-origen-info .row { display: flex; justify-content: space-between; padding: 4px 0; border-bottom: 1px dashed #eef1f4; }
.hist-origen-info .row:last-child { border-bottom: none; }
.hist-origen-info .lbl { color: #8895a4; font-weight: 500; }
.hist-origen-info .val { color: #233e2d; font-weight: 600; text-align: right; }
.hist-seccion { background: #fff; border: 1px solid #e5e8eb; border-radius: 8px; margin-bottom: 10px; overflow: hidden; }
.hist-seccion-head { padding: 10px 14px; background: #fff; cursor: pointer; display: flex; align-items: center; gap: 10px; user-select: none; transition: background .12s; }
.hist-seccion-head:hover { background: #f8fafc; }
.hist-seccion-head .icon { width: 26px; height: 26px; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 12px; flex-shrink: 0; }
.hist-seccion-head .titulo { flex: 1; font-size: 13px; font-weight: 600; color: #233e2d; }
.hist-seccion-head .count { background: #e8f5e9; color: #1b5e20; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 10px; }
.hist-seccion-head .caret { color: #8895a4; font-size: 11px; transition: transform .2s; }
.hist-seccion.open .hist-seccion-head .caret { transform: rotate(180deg); }
.hist-seccion-body { max-height: 0; overflow: hidden; transition: max-height .25s ease; background: #fafbfc; }
.hist-seccion.open .hist-seccion-body { max-height: 2000px; border-top: 1px solid #eef1f4; }
.hist-seccion-body .inner { padding: 8px 14px 12px; }
.hist-evento-acciones { text-align: right; padding: 2px 0 8px !important; border-bottom: 1px solid #eef1f4; }
.hist-evento-reporte-link { display: inline-flex !important; align-items: center; gap: 4px; margin: 3px 0; visibility: visible !important; opacity: 1 !important; }
.hist-reporte-preview-overlay { position: fixed; inset: 0; z-index: 1060; display: none; align-items: center; justify-content: center; padding: 18px; background: rgba(25,35,42,.48); }
.hist-reporte-preview-overlay.open { display: flex; }
.hist-reporte-preview { width: min(960px, 96vw); height: min(820px, 92vh); display: flex; flex-direction: column; overflow: hidden; background: #fff; border-radius: 8px; box-shadow: 0 18px 55px rgba(0,0,0,.28); }
.hist-reporte-preview-header, .hist-reporte-preview-footer { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 12px 16px; background: #fff; flex-shrink: 0; }
.hist-reporte-preview-header { border-bottom: 1px solid #e5e8eb; }
.hist-reporte-preview-header h3 { margin: 0; font-size: 15px; font-weight: 700; color: #233e2d; }
.hist-reporte-preview-footer { justify-content: flex-end; flex-wrap: wrap; border-top: 1px solid #e5e8eb; }
.hist-reporte-preview-opcion { margin: 0 auto 0 0; display: flex; align-items: center; gap: 7px; color: #46545a; font-size: 12px; font-weight: 600; cursor: pointer; }
.hist-reporte-preview-opcion input { width: 15px; height: 15px; margin: 0; accent-color: #27834a; }
.hist-reporte-preview-ayuda { margin: 0 auto 0 0; color: #748087; font-size: 11px; font-weight: 400; }
.hist-reporte-preview-header .hist-reporte-preview-ayuda { display: block; margin: 3px 0 0; }
.hist-reporte-preview-body { min-height: 0; flex: 1; background: #eef1f3; }
.hist-reporte-preview-body iframe { display: block; width: 100%; height: 100%; border: 0; background: #fff; }
@media (max-width: 600px) { .hist-reporte-preview-overlay { padding: 0; } .hist-reporte-preview { width: 100%; height: 100%; max-height: 100%; border-radius: 0; } }
.hist-tabla { width: 100%; font-size: 11.5px; border-collapse: collapse; }
.hist-tabla tr { border-bottom: 1px solid #eef1f4; }
.hist-tabla tr:last-child { border-bottom: none; }
.hist-tabla td { padding: 5px 0; color: #333; vertical-align: top; }
.hist-tabla .fecha { width: 85px; color: #8895a4; font-size: 11px; white-space: nowrap; padding-right: 8px; }
.hist-tabla .destacado { font-weight: 600; color: #233e2d; }
.hist-tabla .extra { color: #666; font-size: 11px; }
.hist-tabla .num { text-align: right; font-weight: 700; color: #2f8f4a; white-space: nowrap; }
.hist-vacio { font-size: 12px; color: #b5bbc8; font-style: italic; text-align: center; padding: 12px 0; }
.bg-icon-leche     { background: linear-gradient(135deg, #3c8dbc, #5dade2); }
.bg-icon-sanidad   { background: linear-gradient(135deg, #27ae60, #52be80); }
.bg-icon-pesos     { background: linear-gradient(135deg, #f39c12, #f7b955); }
.bg-icon-servicios { background: linear-gradient(135deg, #8e44ad, #b07cc6); }
.bg-icon-movs      { background: linear-gradient(135deg, #e74c3c, #ec7063); }

@media (max-width: 900px) {
    .ficha-datos-grid--identidad { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .ficha-datos-grid--3 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 767px) {
    .ficha-wrap { padding: 14px; margin: 0; border-radius: 0; box-shadow: none; }
    .ficha-header { flex-direction: column; align-items: flex-start; }
    .ficha-main { flex-direction: column; align-items: center; }
    .ficha-identidad { width: 100%; }
    .ficha-grid-2 { grid-template-columns: 1fr; }
    .ficha-botones { text-align: center; display: flex; flex-direction: column; gap: 8px; }
    .ficha-footer { flex-direction: column; align-items: center; }
    .hist-drawer { width: 100%; max-width: 100%; }
}
@media (max-width: 560px) {
    .ficha-datos-grid,
    .ficha-datos-grid--identidad,
    .ficha-datos-grid--3,
    .ficha-panel__body .ficha-datos-grid { grid-template-columns: 1fr; }
    .ficha-tabla-full thead { display: none; }
    .ficha-tabla-full, .ficha-tabla-full tbody, .ficha-tabla-full tr, .ficha-tabla-full td { display: block; width: 100%; }
    .ficha-tabla-full tr { border-bottom: 1px solid #eef1f4; padding: 6px 0; }
    .ficha-tabla-full td { border: none; padding: 3px 10px; }
    .ficha-tabla-full td::before { content: attr(data-label) ": "; font-weight: 700; color: #7b8794; }
}
@media print {
    .no-print, .main-header, .main-sidebar, .main-footer, .content-header { display: none !important; }
    .ficha-wrap { border: none; padding: 0; max-width: 100%; box-shadow: none; }
    .ficha-panel, .ficha-dato, .ficha-footer { break-inside: avoid; page-break-inside: avoid; }
    .ficha-botones { display: none !important; }
    .hist-drawer, .hist-overlay { display: none !important; }
}
</style>

<div class="message"></div>

<div id="ficha_loading" class="ficha-cargando">
    <div class="spinner"></div>
    <div>Cargando ficha del animal...</div>
</div>

<div id="ficha_no_disponible" class="ficha-no-disponible" style="display:none;">
    <div class="nd-header">
        <div class="nd-icon">✕</div>
        <h2 id="nd_titulo">Animal no disponible</h2>
    </div>
    <div class="nd-body">
        <p class="nd-msg" id="nd_mensaje">El animal no está disponible.</p>
        <button type="button" class="btn btn-primary" onclick="window.location.href='../animales/';">
            <i class="fa fa-reply"></i> Volver al listado
        </button>
    </div>
</div>

<div class="ficha-wrap" id="ficha_container" style="display:none;">

    <div id="banner_migracion" class="banner-migracion no-print" style="display:none;">
        <div class="info">
            <strong><i class="fa fa-exchange"></i> Animal migrado desde otro fundo</strong>
            <small>
                Código original: <code id="mig_codigo_origen">—</code> ·
                Fundo origen: <strong id="mig_fundo_origen">—</strong> ·
                Fecha: <span id="mig_fecha">—</span>
            </small>
        </div>
        <button type="button" class="btn btn-sm btn-primary" id="btn_ver_historial_origen">
            <i class="fa fa-history"></i> Ver historial de origen
        </button>
    </div>

    <div class="ficha-header">
        <div class="fundo">
            <strong id="fundo_nombre">—</strong><br>
            <small>RIF: <span id="rif_fundo">—</span></small>
        </div>
        <div class="text-right">
            <h2>FICHA DEL ANIMAL</h2>
            <small>Fecha: <?php echo date("d-m-Y"); ?></small>
        </div>
    </div>

    <div class="ficha-main">
        <div class="ficha-foto">
            <img id="foto_animal" src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='160' height='160'%3E%3Crect width='160' height='160' fill='%23f4f4f4'/%3E%3Ctext x='80' y='85' font-family='Arial' font-size='13' fill='%23999' text-anchor='middle'%3ESin foto%3C/text%3E%3C/svg%3E" alt="Foto">
        </div>
        <div class="ficha-identidad">
            <div class="ficha-codigo" id="cod_animal">—</div>
            <div class="ficha-datos-grid ficha-datos-grid--identidad">
                <div class="ficha-dato">
                    <span class="ficha-dato__label">Nombre</span>
                    <span class="ficha-dato__valor" id="nombre_animal">—</span>
                </div>
                <div class="ficha-dato">
                    <span class="ficha-dato__label">Arete</span>
                    <span class="ficha-dato__valor" id="numero_arete">—</span>
                </div>
                <div class="ficha-dato">
                    <span class="ficha-dato__label">Sexo</span>
                    <span class="ficha-dato__valor" id="sexo">—</span>
                </div>
                <div class="ficha-dato">
                    <span class="ficha-dato__label">Raza</span>
                    <span class="ficha-dato__valor" id="raza_animal">—</span>
                </div>
                <div class="ficha-dato">
                    <span class="ficha-dato__label">Edad</span>
                    <span class="ficha-dato__valor" id="edad">—</span>
                </div>
                <div class="ficha-dato">
                    <span class="ficha-dato__label">Estatus</span>
                    <span class="ficha-dato__valor" id="estatus">—</span>
                </div>
                <div class="ficha-dato">
                    <span class="ficha-dato__label">Categoría</span>
                    <span class="ficha-dato__valor" id="categoria_actual">—</span>
                </div>
                <div class="ficha-dato">
                    <span class="ficha-dato__label">Aptitud</span>
                    <span class="ficha-dato__valor" id="aptitud_animal">—</span>
                </div>
            </div>
        </div>
    </div>

    <div class="ficha-grid-2">
        <section class="ficha-panel">
            <div class="ficha-panel__head">
                <i class="fa fa-clipboard"></i>
                <h4>Datos de registro</h4>
            </div>
            <div class="ficha-panel__body">
                <div class="ficha-datos-grid">
                    <div class="ficha-dato">
                        <span class="ficha-dato__label">Fecha de nacimiento</span>
                        <span class="ficha-dato__valor" id="fech_nac">—</span>
                    </div>
                    <div class="ficha-dato">
                        <span class="ficha-dato__label">Fecha de ingreso</span>
                        <span class="ficha-dato__valor" id="fecha_ingreso">—</span>
                    </div>
                    <div class="ficha-dato">
                        <span class="ficha-dato__label">Peso al nacer</span>
                        <span class="ficha-dato__valor" id="peso_nac">—</span>
                    </div>
                    <div class="ficha-dato">
                        <span class="ficha-dato__label">RENA</span>
                        <span class="ficha-dato__valor" id="rena">—</span>
                    </div>
                    <div class="ficha-dato">
                        <span class="ficha-dato__label">Alzada</span>
                        <span class="ficha-dato__valor" id="alza">—</span>
                    </div>
                    <div class="ficha-dato">
                        <span class="ficha-dato__label">Lote</span>
                        <span class="ficha-dato__valor" id="lote">—</span>
                    </div>
                    <div class="ficha-dato">
                        <span class="ficha-dato__label">Procedencia</span>
                        <span class="ficha-dato__valor" id="procedencia">—</span>
                    </div>
                    <div class="ficha-dato">
                        <span class="ficha-dato__label">Servicio</span>
                        <span class="ficha-dato__valor" id="servicio">—</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="ficha-panel">
            <div class="ficha-panel__head">
                <i class="fa fa-sitemap"></i>
                <h4>Genealogía y observaciones</h4>
            </div>
            <div class="ficha-panel__body">
                <div class="ficha-datos-grid">
                    <div class="ficha-dato ficha-dato--full">
                        <span class="ficha-dato__label">Madre</span>
                        <span class="ficha-dato__valor" id="madre">—</span>
                    </div>
                    <div class="ficha-dato ficha-dato--full">
                        <span class="ficha-dato__label">Padre</span>
                        <span class="ficha-dato__valor" id="padre">—</span>
                    </div>
                </div>
                <div class="ficha-obs-wrap">
                    <span class="ficha-dato__label">Observaciones</span>
                    <div class="ficha-obs" id="observaciones">—</div>
                </div>
            </div>
        </section>
    </div>

    <div class="ficha-panel" id="bloque_movimientos" style="display:none; margin-bottom:16px;">
        <div class="ficha-panel__head">
            <i class="fa fa-exchange"></i>
            <h4>Historial de movimientos</h4>
        </div>
        <div class="ficha-panel__body" style="padding:0;">
            <table class="ficha-tabla-full">
                <thead><tr><th style="width:90px;">Fecha</th><th style="width:80px;">Tipo</th><th>Origen</th><th>Destino</th><th>Motivo</th></tr></thead>
                <tbody id="tbody_mov"></tbody>
            </table>
        </div>
    </div>

    <div class="ficha-panel" id="bloque_leche" style="display:none; margin-bottom:16px;">
        <div class="ficha-panel__head">
            <i class="fa fa-tint"></i>
            <h4>Producción de leche</h4>
        </div>
        <div class="ficha-panel__body">
            <div class="ficha-datos-grid ficha-datos-grid--3">
                <div class="ficha-dato">
                    <span class="ficha-dato__label">Promedio diario</span>
                    <span class="ficha-dato__valor"><span id="leche_promedio">—</span> L/día</span>
                </div>
                <div class="ficha-dato">
                    <span class="ficha-dato__label">Último registro</span>
                    <span class="ficha-dato__valor" id="leche_ultimo">—</span>
                </div>
                <div class="ficha-dato">
                    <span class="ficha-dato__label">Registros</span>
                    <span class="ficha-dato__valor" id="leche_total">—</span>
                </div>
            </div>
        </div>
    </div>

    <div class="ficha-panel" id="bloque_sanidad" style="display:none; margin-bottom:16px;">
        <div class="ficha-panel__head">
            <i class="fa fa-stethoscope"></i>
            <h4>Controles sanitarios</h4>
        </div>
        <div class="ficha-panel__body">
            <div class="ficha-datos-grid">
                <div class="ficha-dato">
                    <span class="ficha-dato__label">Último control</span>
                    <span class="ficha-dato__valor" id="san_fecha">—</span>
                </div>
                <div class="ficha-dato">
                    <span class="ficha-dato__label">Evento</span>
                    <span class="ficha-dato__valor" id="san_evento">—</span>
                </div>
                <div class="ficha-dato">
                    <span class="ficha-dato__label">Responsable</span>
                    <span class="ficha-dato__valor" id="san_resp">—</span>
                </div>
                <div class="ficha-dato">
                    <span class="ficha-dato__label">Próximo control</span>
                    <span class="ficha-dato__valor" id="san_prox">—</span>
                </div>
                <div class="ficha-dato ficha-dato--full">
                    <span class="ficha-dato__label">Observaciones</span>
                    <span class="ficha-dato__valor" id="san_obs">—</span>
                </div>
            </div>
        </div>
    </div>

    <div class="ficha-footer">
        <div class="ficha-qr">
            <div id="qr_container"></div>
            <small style="color:#666;">Escanear para verificar autenticidad</small>
        </div>
        <div class="ficha-firma">
            <div class="linea-firma"></div>
            <small>Firma responsable</small>
        </div>
    </div>

    <div class="ficha-botones no-print">
        <button type="button" class="btn btn-warning" id="exit"><i class="fa fa-reply"></i> Regresar</button>
        <a id="ver_publico" class="btn btn-info" href="#" target="_blank" style="display:none;"><i class="fa fa-external-link"></i> Ver página pública</a>
        <button type="button" class="btn btn-success" id="pdf"><i class="fa fa-file-pdf-o"></i> Exportar PDF</button>
        <button type="button" class="btn btn-primary" id="imprimir"><i class="fa fa-print"></i> Imprimir</button>
    </div>
</div>

<div class="hist-overlay" id="hist_overlay"></div>
<div class="hist-drawer" id="hist_drawer">
    <div class="hist-drawer-header">
        <h3><i class="fa fa-history"></i> Historial de Origen</h3>
        <button type="button" class="hist-close" id="hist_close"><i class="fa fa-sign-out"></i> Salir</button>
    </div>
    <div class="hist-drawer-body" id="hist_drawer_body">
        <div style="text-align:center; padding:40px; color:#8895a4;">
            <i class="fa fa-spinner fa-spin fa-2x"></i>
            <p style="margin-top:8px;">Cargando historial...</p>
        </div>
    </div>
</div>

<div class="hist-reporte-preview-overlay" id="hist_reporte_preview_overlay" aria-hidden="true">
    <section class="hist-reporte-preview" role="dialog" aria-modal="true" aria-labelledby="hist_reporte_preview_title">
        <div class="hist-reporte-preview-header">
            <div><h3 id="hist_reporte_preview_title"><i class="fa fa-file-text-o"></i> Vista previa del reporte</h3><small class="hist-reporte-preview-ayuda">El reporte del proceso solicitado siempre se muestra; la procedencia del fundo es opcional.</small></div>
            <button type="button" class="btn btn-default btn-sm" id="hist_reporte_preview_close"><i class="fa fa-times"></i> Cerrar</button>
        </div>
        <div class="hist-reporte-preview-body"><iframe id="hist_reporte_preview_frame" title="Vista previa del reporte"></iframe></div>
        <div class="hist-reporte-preview-footer">
            <label class="hist-reporte-preview-opcion"><input type="checkbox" id="hist_reporte_preview_origen"> Mostrar datos del fundo anterior</label>
            <span class="hist-reporte-preview-ayuda">Opcional; el reporte del evento se incluye siempre.</span>
            <button type="button" class="btn btn-default" id="hist_reporte_preview_cancel">Cerrar</button>
            <button type="button" class="btn btn-primary" id="hist_reporte_preview_print"><i class="fa fa-print"></i> Imprimir / Guardar PDF</button>
        </div>
    </section>
</div>

<form id="form_pdf" method="post" action="../../controllers/rficha.php" target="_blank" style="display:none;">
    <input type="hidden" name="id_animal" id="pdf_id_animal" value="<?php echo (int)$id_animal; ?>">
    <input type="hidden" name="qr"        id="pdf_qr">
</form>

<script src="../../plugins/qrcode/qrcode.min.js"></script>
<script>
var ID_ANIMAL   = <?php echo json_encode((int)$id_animal); ?>;
var QR_DATA_URL = '';
var HISTORIAL_ORIGEN = null;
var DATOS_ANIMAL_REPORTE = null;
var REPORTE_EVENTO_ORIGEN = null;
var REPORTE_EVENTO_SOLICITUD = 0;

function escaparReporte(valor) {
    return $('<div>').text(valor === null || valor === undefined || valor === '' ? '—' : String(valor)).html();
}

function mostrarVistaPreviaReporte(titulo, html) {
    $('#hist_reporte_preview_title').text(titulo || 'Vista previa del reporte');
    document.getElementById('hist_reporte_preview_frame').srcdoc = html;
    $('#hist_reporte_preview_overlay').addClass('open').attr('aria-hidden', 'false');
}

function cerrarVistaPreviaReporte() {
    $('#hist_reporte_preview_overlay').removeClass('open').attr('aria-hidden', 'true');
    document.getElementById('hist_reporte_preview_frame').srcdoc = '';
    $('#hist_reporte_preview_origen').prop('checked', false);
    REPORTE_EVENTO_ORIGEN = null;
    REPORTE_EVENTO_SOLICITUD++;
}

function imprimirVistaPreviaReporte() {
    var frame = document.getElementById('hist_reporte_preview_frame');
    if (frame && frame.contentWindow) {
        frame.contentWindow.focus();
        frame.contentWindow.print();
    }
}

function cargarReporteEventoOrigen(incluirOrigen) {
    if (!REPORTE_EVENTO_ORIGEN || !HISTORIAL_ORIGEN || !DATOS_ANIMAL_REPORTE) return;
    var solicitud = ++REPORTE_EVENTO_SOLICITUD;
    var tipo = REPORTE_EVENTO_ORIGEN.tipo;
    var eventoId = REPORTE_EVENTO_ORIGEN.eventoId;
    var titulos = { leche: 'Producción de leche', sanidad: 'Control sanitario', pesos: 'Pesaje', servicios: 'Servicio', movimientos: 'Movimiento de rebaño' };
    $('#hist_reporte_preview_print').prop('disabled', true);
    $('#hist_reporte_preview_title').html('<i class="fa fa-file-text-o"></i> Reporte de ' + escaparReporte(titulos[tipo]));
    document.getElementById('hist_reporte_preview_frame').srcdoc = '<!doctype html><html lang="es"><body style="font:14px Arial;padding:24px;color:#53635a">Preparando el reporte de ' + escaparReporte(titulos[tipo].toLowerCase()) + '...</body></html>';

    $.post('../../data_json/data_historial_origen.php', {
        action: 'detalle_evento', id_animal: ID_ANIMAL, tipo: tipo,
        evento_id: eventoId, incluir_fundo_origen: incluirOrigen ? 1 : 0
    }, function(resp) {
        if (solicitud !== REPORTE_EVENTO_SOLICITUD) return;
        if (!resp.ok) {
            mostrarVistaPreviaReporte('No se pudo cargar el reporte', '<!doctype html><html lang="es"><body style="font:14px Arial;padding:24px;color:#a33">No se pudo cargar el detalle de este evento.</body></html>');
            return;
        }
        var filas = (resp.campos || []).map(function(item) {
            var etiqueta = item.etiqueta || item.campo.replace(/_/g, ' ').replace(/\b\w/g, function(letra) { return letra.toUpperCase(); });
            return '<tr><th>' + escaparReporte(etiqueta) + '</th><td>' + escaparReporte(item.valor) + '</td></tr>';
        }).join('');
        var animal = DATOS_ANIMAL_REPORTE;
        var datosOrigen = resp.fundo
            ? '<p>Fundo anterior: <strong>' + escaparReporte(resp.fundo.nombre) + '</strong> · RIF: ' + escaparReporte(resp.fundo.rif) + '</p><p>Dirección: ' + escaparReporte(resp.fundo.direccion) + '</p><p>Descripción: ' + escaparReporte(resp.fundo.descripcion) + '</p><p>Código original: ' + escaparReporte(resp.fundo.codigo) + ' · Fecha de migración: ' + escaparReporte(resp.fundo.fecha_migracion) + '</p>'
            : '';
        mostrarVistaPreviaReporte('Reporte de ' + titulos[tipo], '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>' + escaparReporte(titulos[tipo]) + '</title><style>body{font:14px Arial,sans-serif;color:#25332b;margin:32px}h1{font-size:22px;margin:0 0 12px}p{margin:5px 0;color:#53635a}table{width:100%;border-collapse:collapse;margin-top:18px}th,td{border:1px solid #ccd5ce;padding:9px;text-align:left;vertical-align:top}th{background:#e9f1eb;width:32%}@media print{body{margin:12mm}}</style></head><body>' +
            '<h1>' + escaparReporte(titulos[tipo]) + '</h1><p>Animal: <strong>' + escaparReporte(animal.nombre) + '</strong> · Código: ' + escaparReporte(animal.codigo) + ' · Arete: ' + escaparReporte(animal.arete) + ' · Sexo: ' + escaparReporte(animal.sexo) + '</p>' +
            datosOrigen + '<table>' + filas + '</table></body></html>');
        $('#hist_reporte_preview_print').prop('disabled', false);
    }, 'json').fail(function() {
        if (solicitud !== REPORTE_EVENTO_SOLICITUD) return;
        mostrarVistaPreviaReporte('No se pudo cargar el reporte', '<!doctype html><html lang="es"><body style="font:14px Arial;padding:24px;color:#a33">No se pudo consultar el evento. Verifique su conexión y vuelva a intentar.</body></html>');
    });
}

function generarReporteOrigen(tipo, eventoId) {
    if (!HISTORIAL_ORIGEN || !DATOS_ANIMAL_REPORTE) return;
    REPORTE_EVENTO_ORIGEN = { tipo: tipo, eventoId: eventoId };
    $('#hist_reporte_preview_origen').prop('checked', false);
    $('#hist_reporte_preview_print').prop('disabled', true);
    mostrarVistaPreviaReporte('Vista previa del reporte', '<!doctype html><html lang="es"><body style="font:14px Arial;padding:24px;color:#53635a">Preparando el reporte...</body></html>');
    cargarReporteEventoOrigen(false);
}

function mostrarFicha() { $("#ficha_loading").hide(); $("#ficha_container").show(); }
function mostrarNoDisponible(t, m) {
    $("#ficha_loading").hide();
    $("#ficha_container").hide();
    $("#nd_titulo").text(t || 'Animal no disponible');
    $("#nd_mensaje").text(m || 'El animal no está disponible.');
    $("#ficha_no_disponible").show();
}

function abrirDrawer() {
    $("#hist_overlay").addClass('open');
    $("#hist_drawer").addClass('open');
    $('body').css('overflow', 'hidden');
}
function cerrarDrawer() {
    $("#hist_overlay").removeClass('open');
    $("#hist_drawer").removeClass('open');
    $('body').css('overflow', '');
}

$(document).on('click', '#hist_overlay, #hist_close', cerrarDrawer);
$(document).on('keydown', function(e) {
    if (e.key === 'Escape' && $('#hist_reporte_preview_overlay').hasClass('open')) cerrarVistaPreviaReporte();
    else if (e.key === 'Escape' && $("#hist_drawer").hasClass('open')) cerrarDrawer();
});
$(document).on('click', '.hist-seccion-head', function() {
    $(this).closest('.hist-seccion').toggleClass('open');
});
$(document).on('click', '.hist-evento-reporte', function(e) {
    e.preventDefault();
    generarReporteOrigen($(this).data('tipo'), Number($(this).data('evento-id')));
});
$(document).on('click', '#hist_reporte_preview_close, #hist_reporte_preview_cancel', cerrarVistaPreviaReporte);
$(document).on('click', '#hist_reporte_preview_print', imprimirVistaPreviaReporte);
$(document).on('change', '#hist_reporte_preview_origen', function() {
    cargarReporteEventoOrigen(this.checked);
});
$(document).on('click', '#hist_reporte_preview_overlay', function(e) {
    if (e.target === this) cerrarVistaPreviaReporte();
});

$(document).ready(function () {

    $.ajax({
        url: "../../data_json/data_manimales_ficha.php",
        type: "POST",
        dataType: "json",
        data: { id_animal: ID_ANIMAL }
    })
    .done(function (data) {

        if (data && data.error) {
            if (data.error === 'no_encontrado') mostrarNoDisponible(data.titulo, data.mensaje);
            else if (data.error === 'sesion') mostrarNoDisponible('Sesión expirada', 'Debe iniciar sesión nuevamente.');
            else mostrarNoDisponible(data.titulo || 'Error', data.mensaje || '');
            return;
        }

        DATOS_ANIMAL_REPORTE = {
            codigo: data.cod_animal,
            nombre: data.nombre_animal,
            arete: data.nro_arete || 'No registrado',
            sexo: data.sexo
        };

        $("#fundo_nombre").text(data.fundo || '—');
        $("#rif_fundo").text(data.rif_fundo || '—');
        $("#cod_animal").text(data.cod_animal);
        $("#nombre_animal").text(data.nombre_animal);
        $("#numero_arete").text(data.nro_arete || 'No registrado');
        $("#sexo").text(data.sexo);
        $("#raza_animal").text(data.raza || 'No registrado');
        $("#edad").text(data.edad || '—');
        $("#estatus").text(data.estatus);
        $("#categoria_actual").text(data.categoria_actual || '—');
        $("#aptitud_animal").text(data.aptitud || '—');
        $("#fech_nac").text(data.f_nacimiento || '—');
        $("#fecha_ingreso").text(data.f_ingreso || '—');
        $("#peso_nac").text(data.peso_nac || '—');
        $("#rena").text(data.rena || '—');
        $("#alza").text(data.alza || '—');
        $("#lote").text(data.lote || '—');
        $("#procedencia").text(data.procedencia || '—');
        $("#servicio").text(data.servicio || '—');

        var madreTxt = ((data.madre && data.madre.codigo ? data.madre.codigo : '') + ' ' + (data.madre && data.madre.nombre ? data.madre.nombre : '')).trim();
        var padreTxt = ((data.padre && data.padre.codigo ? data.padre.codigo : '') + ' ' + (data.padre && data.padre.nombre ? data.padre.nombre : '')).trim();
        $("#madre").text(madreTxt || 'No registrado');
        $("#padre").text(padreTxt || 'No registrado');
        $("#observaciones").text(data.observaciones || 'Sin observaciones');

        if (data.foto) { $("#foto_animal").attr('src', data.foto); }

        if (data.movimientos && data.movimientos.length) {
            var html = '';
            data.movimientos.forEach(function (m) {
                html += '<tr>' +
                        '<td data-label="Fecha">' + (m.fecha || '') + '</td>' +
                        '<td data-label="Tipo">' + (m.tipo || '') + '</td>' +
                        '<td data-label="Origen">' + (m.origen || '') + '</td>' +
                        '<td data-label="Destino">' + (m.destino || '') + '</td>' +
                        '<td data-label="Motivo">' + ((m.motivo || '') + (m.obs ? ' — ' + m.obs : '')) + '</td>' +
                        '</tr>';
            });
            $("#tbody_mov").html(html);
            $("#bloque_movimientos").show();
        }

        if (data.leche && data.leche.promedio > 0) {
            $("#leche_promedio").text(data.leche.promedio);
            $("#leche_ultimo").text(data.leche.ultimo || '—');
            $("#leche_total").text(data.leche.total);
            $("#bloque_leche").show();
        }

        if (data.sanidad) {
            $("#san_fecha").text(data.sanidad.fecha || '—');
            $("#san_evento").text(data.sanidad.evento || '—');
            $("#san_resp").text(data.sanidad.responsable || '—');
            $("#san_prox").text(data.sanidad.prox || '—');
            $("#san_obs").text(data.sanidad.obs || '—');
            $("#bloque_sanidad").show();
        }

        if (typeof qrcode !== 'function') { mostrarFicha(); return; }
        var contenidoQR = data.qr_url || data.qr_texto || 'Sin datos';
        var qr = qrcode(0, 'M');
        qr.addData(contenidoQR);
        qr.make();
        $("#qr_container").html(qr.createImgTag(4, 0));
        try { QR_DATA_URL = qr.createDataURL(4, 0, 'image/png'); } catch (e) { QR_DATA_URL = qr.createDataURL(4, 0); }
        if (data.qr_url) $("#ver_publico").attr('href', data.qr_url).show();

        if (data.es_migrado && data.animal_origen_id) {
            $("#mig_codigo_origen").text(data.codigo_origen || '—');
            $("#mig_fecha").text(data.fecha_migracion || '—');
            $("#banner_migracion").show();
        }

        mostrarFicha();
    })
    .fail(function (xhr) {
        var resp = null;
        try { resp = JSON.parse(xhr.responseText); } catch (e) {}
        if (xhr.status === 404) mostrarNoDisponible((resp && resp.titulo) || 'Animal no disponible', (resp && resp.mensaje) || 'El animal no existe o fue dado de baja.');
        else if (xhr.status === 401) mostrarNoDisponible('Sesión expirada', 'Debe iniciar sesión nuevamente.');
        else if (xhr.status === 500) mostrarNoDisponible('Error del sistema', 'No se pudieron cargar los datos.');
        else mostrarNoDisponible('Error', 'No se pudieron cargar los datos.');
        console.error('AJAX fail:', xhr.status, xhr.responseText);
    });

    $("#btn_ver_historial_origen").on('click', function() {
        $("#hist_drawer_body").html('<div style="text-align:center; padding:40px; color:#8895a4;"><i class="fa fa-spinner fa-spin fa-2x"></i><p style="margin-top:8px;">Cargando historial...</p></div>');
        abrirDrawer();

        $.post("../../data_json/data_historial_origen.php", { id_animal: ID_ANIMAL }, function(resp) {
            if (!resp.ok || !resp.tiene_origen) {
                $("#hist_drawer_body").html('<div class="alert alert-warning" style="margin:0;">No hay historial disponible.</div>');
                return;
            }

            HISTORIAL_ORIGEN = resp;
            var esHembra = Number(resp.sexo) === 1;

            $("#mig_fundo_origen").text(resp.fundo_origen || '—');

            var html = '';
            html += '<div class="hist-origen-info">';
            html += '  <div class="row"><span class="lbl">Fundo origen:</span><span class="val">' + (resp.fundo_origen || '—') + '</span></div>';
            html += '  <div class="row"><span class="lbl">Código original:</span><span class="val"><code>' + (resp.codigo_origen || '—') + '</code></span></div>';
            html += '  <div class="row"><span class="lbl">Fecha de migración:</span><span class="val">' + (resp.fecha_migracion || '—') + '</span></div>';
            html += '</div>';

            function seccion(id, titulo, iconoClass, count, contenido) {
                var h = '<div class="hist-seccion" data-seccion="' + id + '">';
                h += '  <div class="hist-seccion-head">';
                h += '    <div class="icon ' + iconoClass + '"><i class="fa fa-circle"></i></div>';
                h += '    <span class="titulo">' + titulo + '</span>';
                h += '    <span class="count">' + count + '</span>';
                h += '    <i class="fa fa-chevron-down caret"></i>';
                h += '  </div>';
                h += '  <div class="hist-seccion-body"><div class="inner">' + contenido + '</div></div>';
                h += '</div>';
                return h;
            }

            function botonReporteEvento(tipo, indice) {
                return '<a href="#" role="button" class="btn btn-xs btn-info hist-evento-reporte hist-evento-reporte-link" data-tipo="' + tipo + '" data-evento-id="' + indice + '"><i class="fa fa-file-text-o"></i> Ver reporte</a>';
            }

            if (esHembra) {
            var lecheHtml = '';
            if (resp.leche && resp.leche.length > 0) {
                lecheHtml += '<table class="hist-tabla">';
                resp.leche.forEach(function(l, indice) {
                    lecheHtml += '<tr><td class="fecha">' + l.fecha + '</td><td>M: <span class="destacado">' + l.m + ' L</span> · T: <span class="destacado">' + l.t + ' L</span></td><td class="num">' + l.total + ' L</td></tr>';
                    lecheHtml += '<tr><td colspan="3" class="hist-evento-acciones">' + botonReporteEvento('leche', l.evento_id) + '</td></tr>';
                });
                lecheHtml += '</table>';
            } else { lecheHtml = '<div class="hist-vacio">Sin registros de leche.</div>'; }
            html += seccion('leche', 'Producción de Leche', 'bg-icon-leche', (resp.leche || []).length, lecheHtml);
            }

            var sanHtml = '';
            if (resp.sanidad && resp.sanidad.length > 0) {
                sanHtml += '<table class="hist-tabla">';
                resp.sanidad.forEach(function(s, indice) {
                    sanHtml += '<tr><td class="fecha">' + s.fecha + '</td><td><span class="destacado">' + (s.evento || '') + '</span>';
                    if (s.responsable) sanHtml += '<br><span class="extra">Resp: ' + s.responsable + '</span>';
                    if (s.prox) sanHtml += '<br><span class="extra">Próximo control: ' + s.prox + '</span>';
                    if (s.obs) sanHtml += '<br><span class="extra">' + s.obs + '</span>';
                    sanHtml += '</td></tr>';
                    sanHtml += '<tr><td colspan="2" class="hist-evento-acciones">' + botonReporteEvento('sanidad', s.evento_id) + '</td></tr>';
                });
                sanHtml += '</table>';
            } else { sanHtml = '<div class="hist-vacio">Sin controles sanitarios.</div>'; }
            html += seccion('sanidad', 'Controles Sanitarios', 'bg-icon-sanidad', (resp.sanidad || []).length, sanHtml);

            var pesosHtml = '';
            if (resp.pesos && resp.pesos.length > 0) {
                pesosHtml += '<table class="hist-tabla">';
                resp.pesos.forEach(function(p, indice) {
                    pesosHtml += '<tr><td class="fecha">' + p.fecha + '</td><td><span class="destacado">' + p.peso + ' kg</span></td></tr>';
                    pesosHtml += '<tr><td colspan="2" class="hist-evento-acciones">' + botonReporteEvento('pesos', p.evento_id) + '</td></tr>';
                });
                pesosHtml += '</table>';
            } else { pesosHtml = '<div class="hist-vacio">Sin pesajes registrados.</div>'; }
            html += seccion('pesos', 'Consecutivo de Pesos', 'bg-icon-pesos', (resp.pesos || []).length, pesosHtml);

            if (esHembra) {
                var servHtml = '';
                if (resp.servicios && resp.servicios.length > 0) {
                    servHtml += '<table class="hist-tabla">';
                    resp.servicios.forEach(function(s, indice) {
                        servHtml += '<tr><td class="fecha">' + s.fecha + '</td><td><span class="destacado">' + (s.codigo || '') + '</span> · Servicio #' + s.nro;
                        if (s.obs) servHtml += '<br><span class="extra">' + s.obs + '</span>';
                        servHtml += '</td></tr>';
                        servHtml += '<tr><td colspan="2" class="hist-evento-acciones">' + botonReporteEvento('servicios', s.evento_id) + '</td></tr>';
                    });
                    servHtml += '</table>';
                } else { servHtml = '<div class="hist-vacio">Sin servicios registrados.</div>'; }
                html += seccion('servicios', 'Servicios', 'bg-icon-servicios', (resp.servicios || []).length, servHtml);
            }

            var movHtml = '';
            if (resp.movimientos && resp.movimientos.length > 0) {
                movHtml += '<table class="hist-tabla">';
                resp.movimientos.forEach(function(m, indice) {
                    movHtml += '<tr><td class="fecha">' + m.fecha + '</td><td><span class="destacado">' + (m.tipo || '') + '</span>';
                    if (m.origen || m.destino) movHtml += '<br><span class="extra">' + (m.origen || '—') + ' → ' + (m.destino || '—') + '</span>';
                    if (m.motivo) movHtml += '<br><span class="extra">' + m.motivo + '</span>';
                    if (m.obs) movHtml += '<br><span class="extra">' + m.obs + '</span>';
                    movHtml += '</td></tr>';
                    movHtml += '<tr><td colspan="2" class="hist-evento-acciones">' + botonReporteEvento('movimientos', m.evento_id) + '</td></tr>';
                });
                movHtml += '</table>';
            } else { movHtml = '<div class="hist-vacio">Sin movimientos de rebaño.</div>'; }
            html += seccion('movimientos', 'Movimientos de Rebaño', 'bg-icon-movs', (resp.movimientos || []).length, movHtml);

            $("#hist_drawer_body").html(html);

        }, "json").fail(function(xhr) {
            var msg = 'Error al cargar el historial.';
            try { var r = JSON.parse(xhr.responseText); if (r && r.msg) msg = r.msg; } catch (e) {}
            $("#hist_drawer_body").html('<div class="alert alert-danger" style="margin:0;">' + msg + '</div>');
        });
    });

    $("#imprimir").on('click', function () { window.print(); });
    $("#pdf").on('click', function () {
        $("#pdf_id_animal").val(ID_ANIMAL);
        $("#pdf_qr").val(QR_DATA_URL);
        $("#form_pdf").submit();
    });
    $("#exit").on('click', function () { window.location.href = '../animales/'; });
});
</script>

<?php include_once("../layouts/pie.php") ?>
