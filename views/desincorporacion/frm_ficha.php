<?php
include("../../lib/validar_session.php");
ValidaSession("../login");
$parametros = base64_decode($_GET["%$%"]);
$valores = explode("=", $parametros);
$cad0 = $valores[0];
?>
<?php include("../layouts/constantes.php") ?>
<?php include_once("../layouts/cabecera.php") ?>

<link rel="stylesheet" href="../../plugins/confirma/jquery-confirm.min.css" type="text/css" />
<link rel="stylesheet" href="../../css/crud-form.css" type="text/css" />
<link rel="stylesheet" href="../../plugins/datepicker/datepicker3.css" type="text/css" />
<link rel="stylesheet" href="../../plugins/unslider/unslider.css">
<link rel="stylesheet" href="../../plugins/leaflet/leaflet.css" />
<link rel="stylesheet" href="../../plugins/select3/bootstrap-select.min.css">

<style>
/* ============================================================
   FICHA DE DESINCORPORACIÓN — REPORTE COMPACTO EN UNA PÁGINA
   ============================================================ */
.desinc-wrap { max-width: 1080px; margin: 0 auto; }

.desinc-header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
    background: #fff;
    border: 1px solid #e3e7ed;
    border-radius: 10px;
    padding: 12px 16px;
    box-shadow: 0 4px 16px rgba(15, 23, 42, .05);
    margin-bottom: 10px;
}
.desinc-header__title { display: flex; align-items: center; gap: 10px; flex: 1 1 300px; min-width: 0; }
.desinc-header__icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: linear-gradient(135deg, #234e35, #2f8f4a);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    flex-shrink: 0;
}
.desinc-header h2 { margin: 0; font-size: 18px; font-weight: 800; color: #1b3b2d; letter-spacing: .02em; }
.desinc-header p { margin: 2px 0 0; font-size: 11.5px; color: #6b7480; }

.desinc-header__meta { display: grid; grid-template-columns: repeat(3, minmax(80px, 1fr)); gap: 4px 14px; flex: 0 1 auto; }
.desinc-header__meta span { display: block; font-size: 9px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: #8895a4; }
.desinc-header__meta strong { display: block; font-size: 12px; color: #233e2d; margin-top: 1px; word-break: break-word; }
.desinc-header__actions { display: flex; gap: 6px; flex-shrink: 0; }

.desinc-summary {
    display: grid;
    grid-template-columns: 1.1fr 1.5fr 1fr;
    gap: 1px;
    background: #cfe3d6;
    border: 1px solid #cfe3d6;
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 10px;
}
.desinc-summary__item { background: #f0fbf3; padding: 8px 12px; }
.desinc-summary__label { display: block; font-size: 9px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: #5f7f6a; }
.desinc-summary__value { display: block; font-size: 16px; font-weight: 800; color: #1b5e20; margin-top: 2px; word-break: break-word; }

.desinc-card {
    background: #fff;
    border: 1px solid #e3e7ed;
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 10px;
    box-shadow: 0 2px 8px rgba(15, 23, 42, .03);
}
.desinc-card__head { display: flex; align-items: center; gap: 6px; padding: 8px 12px; background: #f0fbf3; border-bottom: 1px solid #dceee1; }
.desinc-card__head i { color: #2f8f4a; }
.desinc-card__head h3 { margin: 0; font-size: 11px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: #234e35; }
.desinc-card__body { padding: 10px 12px; }

.desinc-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 6px 8px; }
.desinc-grid--3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.desinc-field {
    display: flex;
    flex-direction: column;
    gap: 1px;
    min-width: 0;
    padding: 5px 8px;
    background: #f8fafc;
    border: 1px solid #eef1f4;
    border-left: 2px solid #dceee1;
    border-radius: 6px;
}
.desinc-field--full { grid-column: 1 / -1; }
.desinc-label { font-size: 9.5px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: #7b8794; line-height: 1.2; }
.desinc-value { font-size: 12.5px; font-weight: 600; color: #233e2d; line-height: 1.35; word-break: break-word; }
.desinc-sep { color: #b5bbc8; font-weight: 400; }
.desinc-obs { font-size: 12.5px; font-weight: 500; color: #334155; line-height: 1.4; white-space: pre-wrap; min-height: 24px; }

.desinc-card--out .desinc-card__head { background: #fff7ed; border-bottom-color: #fed7aa; }
.desinc-card--out .desinc-card__head i { color: #c2410c; }
.desinc-card--out .desinc-card__head h3 { color: #9a3412; }
.desinc-card--out .desinc-field { border-left-color: #fed7aa; }

.desinc-footer {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    gap: 12px;
    margin-top: 10px;
    padding: 10px 12px;
    background: #f8fafc;
    border: 1px solid #e3e7ed;
    border-radius: 10px;
}
.desinc-footer__brand strong { color: #234e35; }
.desinc-footer__brand small { color: #7b8794; }
.desinc-sign { width: 200px; text-align: center; }
.desinc-sign__line { border-bottom: 1px solid #9aa6b2; height: 26px; margin-bottom: 4px; }
.desinc-sign small { color: #6b7480; }

@media (max-width: 900px) {
    .desinc-header__meta { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .desinc-summary { grid-template-columns: 1fr 1fr; }
    .desinc-summary__item:last-child { grid-column: 1 / -1; }
    .desinc-grid--3 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 640px) {
    .desinc-wrap { padding: 0 4px; }
    .desinc-header { padding: 12px; }
    .desinc-header__meta { grid-template-columns: 1fr; width: 100%; }
    .desinc-header__actions { width: 100%; }
    .desinc-header__actions .btn { flex: 1; }
    .desinc-grid, .desinc-grid--3 { grid-template-columns: 1fr; }
    .desinc-summary { grid-template-columns: 1fr; }
    .desinc-summary__item:last-child { grid-column: auto; }
    .desinc-footer { flex-direction: column; align-items: center; }
}

@media print {
    @page { size: A4 portrait; margin: 6mm; }
    html {
        margin: 0 !important;
        padding: 0 !important;
        height: auto !important;
        overflow: hidden !important;
    }
    /* Altura máxima del área imprimible. Con 265mm el reporte cabe tanto en
       A4 como en Carta y ningún desborde puede generar una segunda hoja. */
    body {
        margin: 0 !important;
        padding: 0 !important;
        height: 265mm !important;
        overflow: hidden !important;
        background: #fff !important;
    }
    .wrapper, .content-wrapper, .content {
        height: auto !important;
        min-height: 0 !important;
        padding: 0 !important;
        margin: 0 !important;
        background: #fff !important;
        overflow: visible !important;
    }
    .main-header, .main-sidebar, .left-side, .main-footer, .pie-footer, .content-header, .no-print,
    .desinc-wrap > .message { display: none !important; }
    form { margin: 0 !important; }
    .desinc-wrap { max-width: 100%; margin: 0; padding: 0; }
    /* Aísla el reporte: ningún otro bloque del layout debe aportar altura
       ni provocar una página adicional al imprimir. */
    body > .wrapper > *:not(.content-wrapper),
    .content-wrapper > *:not(.content),
    .content > *:not(.desinc-wrap) { display: none !important; }
    .desinc-wrap > *:last-child { margin-bottom: 0 !important; }
    .desinc-header, .desinc-summary, .desinc-card, .desinc-footer { box-shadow: none !important; }
    .desinc-header, .desinc-summary, .desinc-card, .desinc-footer, .desinc-field {
        break-inside: avoid;
        page-break-inside: avoid;
    }
    .desinc-header { padding: 8px 10px; margin-bottom: 6px; border-radius: 6px; }
    .desinc-header__icon { width: 28px; height: 28px; font-size: 12px; border-radius: 7px; }
    .desinc-header h2 { font-size: 14px; }
    .desinc-header p { font-size: 9px; }
    .desinc-header__meta {
        grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
    }
    .desinc-header__meta span { font-size: 8px; }
    .desinc-header__meta strong { font-size: 10.5px; }
    .desinc-summary {
        margin-bottom: 6px;
        border-radius: 6px;
        grid-template-columns: 1.1fr 1.5fr 1fr !important;
    }
    .desinc-summary__item { padding: 6px 10px; }
    .desinc-summary__item:last-child { grid-column: auto !important; }
    .desinc-summary__label { font-size: 8px; }
    .desinc-summary__value { font-size: 13px; }
    .desinc-card { margin-bottom: 6px; border-radius: 6px; }
    .desinc-card__head { padding: 6px 10px; }
    .desinc-card__head h3 { font-size: 9px; }
    .desinc-card__body { padding: 6px 10px; }
    .desinc-grid, .desinc-grid--3 { gap: 4px 6px; }
    .desinc-grid--3 { grid-template-columns: repeat(3, minmax(0, 1fr)) !important; }
    .desinc-field { padding: 3px 6px; border-radius: 4px; }
    .desinc-label { font-size: 8px; }
    .desinc-value { font-size: 10.5px; }
    .desinc-obs { font-size: 10.5px; min-height: 18px; }
    .desinc-footer { margin-top: 6px; padding: 6px 10px; border-radius: 6px; }
    .desinc-footer__brand strong { font-size: 10px; }
    .desinc-footer__brand small { font-size: 8px; }
    .desinc-sign { width: 170px; }
    .desinc-sign__line { height: 20px; }
    .desinc-sign small { font-size: 8px; }
    * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>

<div class="desinc-wrap">

    <div class="message"></div>

    <header class="desinc-header">
        <div class="desinc-header__title">
            <span class="desinc-header__icon"><i class="fa fa-id-card-o"></i></span>
            <div>
                <h2>Ficha del Animal Desincorporado</h2>
                <p>Información del animal y del proceso de desincorporación</p>
            </div>
        </div>
        <div class="desinc-header__meta">
            <div>
                <span>Fundo</span>
                <strong id="fundo">—</strong>
            </div>
            <div>
                <span>RIF</span>
                <strong id="rif_fundo">—</strong>
            </div>
            <div>
                <span>Fecha de emisión</span>
                <strong><?php echo date("d-m-Y"); ?></strong>
            </div>
        </div>
        <div class="desinc-header__actions no-print">
            <button id="exit_top" type="button" class="btn btn-warning">
                <i class="fa fa-reply"></i> Regresar
            </button>
            <button id="imprimir" type="button" class="btn btn-success">
                <i class="fa fa-print"></i> Imprimir
            </button>
        </div>
    </header>

    <form id="form" role="form" enctype="multipart/form-data">
        <input type="hidden" id="action" value="edit">
        <input type="hidden" id="id_animal">

        <div class="desinc-summary">
            <div class="desinc-summary__item">
                <span class="desinc-summary__label">Código del animal</span>
                <strong class="desinc-summary__value" id="cod_animal">—</strong>
            </div>
            <div class="desinc-summary__item">
                <span class="desinc-summary__label">Nombre</span>
                <strong class="desinc-summary__value" id="nombre_animal">—</strong>
            </div>
            <div class="desinc-summary__item">
                <span class="desinc-summary__label">Tipo de desincorporación</span>
                <strong class="desinc-summary__value" id="estatus_animal">—</strong>
            </div>
        </div>

        <section class="desinc-card">
            <div class="desinc-card__head">
                <i class="fa fa-cow"></i>
                <h3>Información del animal</h3>
            </div>
            <div class="desinc-card__body">
                <div class="desinc-grid desinc-grid--3">
                    <div class="desinc-field">
                        <span class="desinc-label">Raza</span>
                        <span class="desinc-value" id="raza_animal">—</span>
                    </div>
                    <div class="desinc-field">
                        <span class="desinc-label">Aptitud</span>
                        <span class="desinc-value" id="aptitud_animal">—</span>
                    </div>
                    <div class="desinc-field">
                        <span class="desinc-label">Nro Arete / ID Chip</span>
                        <span class="desinc-value" id="numero_arete">—</span>
                    </div>
                    <div class="desinc-field">
                        <span class="desinc-label">Sexo</span>
                        <span class="desinc-value" id="sexo">—</span>
                    </div>
                    <div class="desinc-field">
                        <span class="desinc-label">Fecha de nacimiento</span>
                        <span class="desinc-value" id="fech_nac">—</span>
                    </div>
                    <div class="desinc-field">
                        <span class="desinc-label">Categoría</span>
                        <span class="desinc-value" id="categoria_actual">—</span>
                    </div>
                    <div class="desinc-field">
                        <span class="desinc-label">Peso / A.N</span>
                        <span class="desinc-value" id="peso_nac">—</span>
                    </div>
                    <div class="desinc-field">
                        <span class="desinc-label">Alzada</span>
                        <span class="desinc-value" id="alza">—</span>
                    </div>
                    <div class="desinc-field">
                        <span class="desinc-label">Fecha de ingreso</span>
                        <span class="desinc-value" id="fecha_ingreso">—</span>
                    </div>
                    <div class="desinc-field">
                        <span class="desinc-label">RENA</span>
                        <span class="desinc-value" id="rena">—</span>
                    </div>
                    <div class="desinc-field">
                        <span class="desinc-label">Lote</span>
                        <span class="desinc-value" id="lote">—</span>
                    </div>
                    <div class="desinc-field">
                        <span class="desinc-label">Procedencia</span>
                        <span class="desinc-value" id="procedencia">—</span>
                    </div>
                    <div class="desinc-field">
                        <span class="desinc-label">Servicio</span>
                        <span class="desinc-value" id="servicio_animal">—</span>
                    </div>
                    <div class="desinc-field">
                        <span class="desinc-label">Madre</span>
                        <span class="desinc-value">
                            <span id="rgd_madre">—</span>
                            <span class="desinc-sep">·</span>
                            <span id="nombre_madre">—</span>
                        </span>
                    </div>
                    <div class="desinc-field">
                        <span class="desinc-label">Padre</span>
                        <span class="desinc-value">
                            <span id="rgd_padre">—</span>
                            <span class="desinc-sep">·</span>
                            <span id="nombre_padre">—</span>
                        </span>
                    </div>
                    <div class="desinc-field desinc-field--full">
                        <span class="desinc-label">Observaciones del animal</span>
                        <div class="desinc-obs" id="observaciones">Sin observaciones</div>
                    </div>
                </div>
            </div>
        </section>

        <section class="desinc-card desinc-card--out">
            <div class="desinc-card__head">
                <i class="fa fa-sign-out"></i>
                <h3>Datos de la desincorporación</h3>
            </div>
            <div class="desinc-card__body desinc-grid">
                <div class="desinc-field">
                    <span class="desinc-label">Fecha de desincorporación</span>
                    <span class="desinc-value" id="fecha_desincorporacion">—</span>
                </div>
                <div class="desinc-field">
                    <span class="desinc-label">Tipo</span>
                    <span class="desinc-value" id="tipo_des_nombre">—</span>
                </div>
                <div class="desinc-field" id="box_causa_descarte">
                    <span class="desinc-label">Causa de descarte</span>
                    <span class="desinc-value" id="causa_descarte">—</span>
                </div>
                <div class="desinc-field" id="box_causa_mortalidad">
                    <span class="desinc-label">Causa de mortalidad</span>
                    <span class="desinc-value" id="causa_mortalidad">—</span>
                </div>
                <div class="desinc-field desinc-field--full">
                    <span class="desinc-label">Observaciones de la desincorporación</span>
                    <div class="desinc-obs" id="obs_desincorporacion">Sin observaciones</div>
                </div>
            </div>
        </section>

        <footer class="desinc-footer">
            <div class="desinc-footer__brand">
                <strong>Sistema SOVCA</strong><br>
                <small>Documento generado el <?php echo date("d-m-Y H:i"); ?></small>
            </div>
            <div class="desinc-sign">
                <div class="desinc-sign__line"></div>
                <small>Firma responsable</small>
            </div>
        </footer>
    </form>

</div><!-- /.desinc-wrap -->

<script language="JavaScript" src="../../plugins/maxLength/maxLength.js"></script>
<script type="text/javascript" src="../../plugins/datepicker/bootstrap-datepicker.js"></script>
<script src="../../plugins/datepicker/locales/bootstrap-datepicker.es.js" charset="UTF-8"></script>
<script src="../../plugins/unslider/unslider-min.js"></script>
<script type="text/javascript" src="../../js/bootstrap-multiselect.js"></script>
<script language="JavaScript" src="../../plugins/mask/jquery.mask.min.js"></script>
<script type="text/javascript" src="../../plugins/confirma/jquery-confirm.min.js"></script>
<script src="../../plugins/balloon/jquery.balloon.min.js"></script>
<script src="../../plugins/leaflet/leaflet.js"></script>
<script src="../../plugins/leaflet/leaflet.label.js"></script>
<script src="../../plugins/select3/bootstrap-select.min.js"></script>
<script src="../../plugins/select3/i18n/defaults-es_ES.min.js"></script>

<script type="text/javascript">
/* ═══════════════════════════════════════════════════════════════════════════
   JAVASCRIPT DE LA FICHA DE DESINCORPORACIÓN — CARGA Y PRESENTACIÓN
   ═══════════════════════════════════════════════════════════════════════════ */
var nombre_real = '';
var fundo = <?php echo $_SESSION['mfundos_id']; ?>;
var buscarx = 0;
var ID_GLOBAL = 0;
var buscarx_m = 0;
var ID_GLOBAL_M = 0;
var nombre_madre = '';
var rgd_madre = '';
var nombre_padre = '';
var rgd_padre = '';

$(document).ready(function() {
    $.post("../../controllers/mdesincorporacion_controller", {
        action: "search_ficha",
        record: <?php echo intval($cad0); ?>
    }).done(function(data) {
        parsedJson = $.parseJSON(data);
        nombre_madre = parsedJson.nombre_madre;
        rgd_madre = parsedJson.rgd_madre;
        nombre_padre = parsedJson.nombre_padre;
        rgd_padre = parsedJson.rgd_padre;

        function valor(v, porDefecto) {
            return (v === null || v === undefined || String(v).trim() === '') ? porDefecto : v;
        }

        $("#id_animal").val(parsedJson.id_animal);
        $("#cod_animal").text(valor(parsedJson.cod_animal, '—'));
        $("#nombre_animal").text(valor(parsedJson.nombre_animal, '—'));
        nombre_real = parsedJson.nombre_animal;
        $("#estatus_animal").text(valor(parsedJson.tipo_des_nombre, '—'));
        $("#lote").text(valor(parsedJson.lote, '—'));
        $("#rena").text(valor(parsedJson.rena, '—'));
        $("#alza").text(valor(parsedJson.alza, '—'));
        $("#raza_animal").text(valor(parsedJson.raza_animal, '—'));
        $("#fech_nac").text(valor(parsedJson.fech_nac, '—'));
        $("#peso_nac").text(valor(parsedJson.peso_nac, '—'));
        $("#fecha_ingreso").text(valor(parsedJson.fecha_ingreso, '—'));
        $("#aptitud_animal").text(valor(parsedJson.aptitud_animal, '—'));
        $("#rgd_madre").text(valor(parsedJson.rgd_madre, '—'));
        $("#nombre_madre").text(valor(parsedJson.nombre_madre, '—'));
        $("#rgd_padre").text(valor(parsedJson.rgd_padre, '—'));
        $("#nombre_padre").text(valor(parsedJson.nombre_padre, '—'));
        $("#procedencia").text(valor(parsedJson.procedencia, '—'));
        $("#servicio_animal").text(valor(parsedJson.servicio_animal, '—'));
        $("#observaciones").text(valor(parsedJson.observaciones, 'Sin observaciones'));
        $("#numero_arete").text(valor(parsedJson.numero_arete, '—'));
        $("#categoria_actual").text(valor(parsedJson.categoria_actual, '—'));
        $("#fundo").text(valor(parsedJson.fundo, '—'));
        $("#rif_fundo").text(valor(parsedJson.rif_fundo, '—'));
        $("#sexo").text(valor(parsedJson.sexo, '—'));
        $("#fecha_desincorporacion").text(valor(parsedJson.fecha_desincorporacion, '—'));
        $("#tipo_des_nombre").text(valor(parsedJson.tipo_des_nombre, '—'));
        $("#causa_descarte").text(valor(parsedJson.causa_descarte, '—'));
        $("#causa_mortalidad").text(valor(parsedJson.causa_mortalidad, '—'));
        $("#obs_desincorporacion").text(valor(parsedJson.obs_desincorporacion, 'Sin observaciones'));

        var tipoDes = parseInt(parsedJson.tipo_desincorporacion, 10) || 0;
        $("#box_causa_descarte").toggle(tipoDes === 2 || (parsedJson.causa_descarte && String(parsedJson.causa_descarte).trim() !== ''));
        $("#box_causa_mortalidad").toggle(tipoDes === 3 || (parsedJson.causa_mortalidad && String(parsedJson.causa_mortalidad).trim() !== ''));
    }, "json");
});

$("#exit_top").click(function() {
    window.location.href = '../desincorporacion/';
});

$("#imprimir").click(function() {
    window.print();
});
</script>

<?php include_once("../layouts/pie.php") ?>