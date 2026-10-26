<?php
include("../../lib/validar_session.php");
ValidaSession("../login");
?>
<?php include("../layouts/constantes.php") ?>
<?php include_once("../layouts/cabecera.php") ?>

<link rel="stylesheet" href="../../plugins/confirma/jquery-confirm.min.css" type="text/css" />
<link rel="stylesheet" href="../../css/crud-form.css" type="text/css" />
<link rel="stylesheet" href="../../css/servicio-selector.css?v=15" type="text/css" />
<link rel="stylesheet" href="../../plugins/datepicker/datepicker3.css" type="text/css" />
<link rel="stylesheet" href="../../plugins/unslider/unslider.css">
<link rel="stylesheet" href="../../plugins/leaflet/leaflet.css" />
<link rel="stylesheet" href="../../plugins/select3/bootstrap-select.min.css">

<style>
/* ============================================================
   LABEL CON CHECKBOXES AL LADO
   ============================================================ */
.crud-label-row {
    display: flex; align-items: center; justify-content: space-between;
    gap: 12px; flex-wrap: wrap; margin-bottom: 4px;
}
.crud-label-row > label {
    margin: 0 !important; font-size: inherit; text-transform: uppercase;
    font-weight: 700; color: #475569; letter-spacing: .04em;
    flex: 1 1 auto; min-width: 0;
}
.crud-label-row .crud-check-group {
    display: inline-flex; align-items: center; gap: 14px; flex: 0 0 auto;
}
.crud-label-row .crud-check-group label {
    margin: 0 !important; font-size: 13.5px !important;
    font-weight: 600 !important; text-transform: none !important;
    letter-spacing: 0 !important; color: #334155 !important;
    display: inline-flex; align-items: center; gap: 6px;
    cursor: pointer; white-space: nowrap;
}
.crud-label-row .crud-check-group input[type="checkbox"] {
    width: 18px; height: 18px; margin: 0; cursor: pointer;
    accent-color: #16a34a;
}

/* ============================================================
   GRÁFICO AMCHARTS — contenedor con altura fija
   ============================================================ */
.crud-chart-wrap {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 14px 16px;
    min-height: 420px;
}
.crud-chart-wrap #chartdiv {
    width: 100%;
    height: 380px;
    display: block;
}

/* ============================================================
   FEEDBACK VISUAL DE DESBLOQUEO
   ============================================================ */
@keyframes crud-flash-input {
    0%   { box-shadow: 0 0 0 0 rgba(22,163,74,.75); background-color:#dcfce7; border-color:#16a34a; }
    30%  { box-shadow: 0 0 0 10px rgba(22,163,74,0); background-color:#f0fdf4; border-color:#16a34a; }
    70%  { box-shadow: 0 0 0 0 rgba(22,163,74,.35); background-color:#ffffff; }
    100% { box-shadow: 0 0 0 0 rgba(22,163,74,0); background-color:inherit; }
}
.crud-form .form-control.crud-unlocked,
.crud-form input.crud-unlocked,
.crud-form select.crud-unlocked,
.crud-form textarea.crud-unlocked {
    animation: crud-flash-input 4.8s ease !important;
    border-color: #16a34a !important;
    transition: none !important;
}
@keyframes crud-flash-btn {
    0%   { transform: scale(1);    box-shadow: 0 0 0 0 rgba(22,163,74,.65); }
    18%  { transform: scale(1.08); box-shadow: 0 0 0 12px rgba(22,163,74,0); }
    45%  { transform: scale(1);    box-shadow: 0 0 0 0 rgba(22,163,74,.45); }
    70%  { transform: scale(1.05); box-shadow: 0 0 0 0 rgba(22,163,74,0); }
    100% { transform: scale(1);    box-shadow: 0 0 0 0 rgba(22,163,74,0); }
}
.crud-form button.crud-just-unlocked-btn,
.crud-form .btn.crud-just-unlocked-btn {
    animation: crud-flash-btn 4.8s ease !important;
    outline: 3px solid rgba(22,163,74,.60) !important;
    outline-offset: 3px !important;
    position: relative; z-index: 2;
}
@keyframes crud-flash-check {
    0%   { transform: scale(1);    background-color: rgba(22,163,74,.30); box-shadow: 0 0 0 0 rgba(22,163,74,.55); }
    25%  { transform: scale(1.25); background-color: rgba(22,163,74,.55); box-shadow: 0 0 0 10px rgba(22,163,74,0); }
    60%  { transform: scale(1);    background-color: rgba(22,163,74,.30); box-shadow: 0 0 0 0 rgba(22,163,74,.30); }
    100% { transform: scale(1);    background-color: transparent; box-shadow: 0 0 0 0 rgba(22,163,74,0); }
}
.crud-form input[type="checkbox"].crud-just-unlocked-check {
    animation: crud-flash-check 4.8s ease !important;
    outline: 2px solid #16a34a !important;
    outline-offset: 2px !important;
    border-radius: 3px;
}
@keyframes crud-flash-check-label {
    0%   { background-color: rgba(22,163,74,.30); color: #15803d; transform: translateX(0); }
    30%  { background-color: rgba(22,163,74,.18); color: #15803d; transform: translateX(-3px); }
    60%  { background-color: rgba(22,163,74,.08); color: #15803d; transform: translateX(0); }
    100% { background-color: transparent; color: inherit; }
}
.crud-form label.crud-just-unlocked-check-label {
    animation: crud-flash-check-label 4.8s ease !important;
    border-radius: 6px !important; padding: 3px 8px !important;
    font-weight: 800 !important; transition: none !important;
}

/* ============================================================
   GRID ESPECÍFICO: Formulario (60%) + Gráfico (40%)
   ============================================================ */
@media (min-width: 993px) {
    #grid_prodleche { grid-template-columns: 1.2fr 0.8fr; }
}
</style>

<div class="crud-form">

    <div class="message"></div>

    <header class="crud-form__header">
        <span class="crud-form__icon"><i class="fa fa-tint"></i></span>
        <div>
            <h3 class="crud-form__title">Registrar Producción de Leche</h3>
            <p class="crud-form__subtitle">Registre la producción diaria de leche de la hembra seleccionada</p>
        </div>
        <div class="crud-form__header-actions">
            <button id="exit_top" type="button" class="btn btn-warning">
                <i class="fa fa-reply"></i> Regresar
            </button>
        </div>
    </header>

    <form id="form" role="form" enctype="multipart/form-data">

        <div class="crud-form__grid" id="grid_prodleche">

            <!-- ========== CARD 1: Datos de producción ========== -->
            <div class="crud-form__card">
                <div class="crud-form__card-head">
                    <i class="fa fa-pencil"></i>
                    <h3>Datos de Producción</h3>
                </div>
                <div class="crud-form__card-body">

                    <div class="crud-form__field">
                        <label>Seleccione la Hembra
                            <span class="sel-animal-hint">(sólo hembras con aptitud de leche)</span>
                        </label>
                        <div class="sel-animal" id="sel_animal">
                            <div class="sel-animal__bar">
                                <i class="fa fa-search"></i>
                                <input type="text" class="sel-animal__filtro" data-sel="filtro"
                                       placeholder="Buscar por nombre, código SOVCA o N° de arete…"
                                       autocomplete="off">
                                <button type="button" class="sel-animal__recargar" data-sel="recargar" title="Actualizar el listado">
                                    <i class="fa fa-refresh"></i>
                                </button>
                            </div>
                            <div class="sel-animal__lista" data-sel="lista"></div>
                            <div class="sel-animal__pie">
                                <span data-sel="contador"></span>
                                <span data-sel="resumen"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Búsqueda antigua (arete/código): se conserva para el flujo interno -->
                    <div class="sel-animal-oculto">
                        <div class="crud-label-row">
                            <label for="cod_animal_h">N°. Arete-Chip / Código</label>
                            <div class="crud-check-group">
                                <label><input type="checkbox" id="b_nro_arete"> Nro Arete</label>
                                <label><input type="checkbox" id="b_codigo"> Código</label>
                            </div>
                        </div>
                        <input class="form-control bbb" id="cod_animal_h" type="text"
                               placeholder="Indique el Código o Nro Arete de la Hembra"
                               onpaste="return true"
                               onkeypress="return es_codigo_nro_arete(event);">
                        <div id='ms_cod_animal'><p></p></div>
                        <button id="buscar" type="button" class="btn btn-success btn-xs" tabindex="57">
                            <i class="fa fa-fw fa-search"></i>Buscar
                        </button>
                    </div>

                    <div class="crud-form__field">
                        <div class="crud-label-row">
                            <label for="nombre_h">Hembra seleccionada</label>
                            <button id="limpiar" type="button" class="btn btn-default btn-sm" tabindex="58">
                                <i class="fa fa-fw fa-trash-o"></i> Cambiar
                            </button>
                        </div>
                        <input class="form-control bbb" id="nombre_h" type="text" tabindex="1"
                               placeholder="Seleccione una fila del listado" onpaste="return false" readonly="true">
                        <div id='ms_nombre'><p></p></div>
                    </div>

                    <div class="crud-form__field">
                        <label for="fecha_prodleche">Fecha de Ordeño</label>
                        <input class="form-control bbb" id="fecha_prodleche" data-date-format="dd-mm-yyyy"
                               placeholder="dia-mes-año" type="text" onpaste="return false"
                               tabindex="10" readonly="true">
                    </div>

                    <div class="crud-form__row">
                        <div class="crud-form__field">
                            <label for="kg_leche_m">Mañana (Kg)</label>
                            <input class="form-control bbb" id="kg_leche_m" type="text"
                                   placeholder="Kg de Leche"
                                   onkeypress="return filterFloat(event,this)"
                                   onchange="return SumarKgLeche(value)"
                                   onpaste="return false" tabindex="16">
                            <div id='ms_kg_leche_m'><p></p></div>
                        </div>
                        <div class="crud-form__field">
                            <label for="kg_leche_t">Tarde (Kg)</label>
                            <input class="form-control bbb" id="kg_leche_t" type="text"
                                   placeholder="Kg de Leche"
                                   onkeypress="return filterFloat(event,this)"
                                   onchange="return SumarKgLeche(value)"
                                   onpaste="return false" tabindex="16">
                            <div id='ms_kg_leche_t'><p></p></div>
                        </div>
                    </div>

                    <div class="crud-form__field">
                        <label for="kg_total_leche">Total (Kg)</label>
                        <input class="form-control bbb" id="kg_total_leche" type="text"
                               placeholder="Kg de Leche totales" onpaste="return false"
                               tabindex="16" readonly="true">
                    </div>

                </div>
            </div>

            <!-- ========== CARD 2: Gráfico amCharts ========== -->
            <div class="crud-form__card">
                <div class="crud-form__card-head">
                    <i class="fa fa-line-chart"></i>
                    <h3>Producción de Leche Promedio</h3>
                </div>
                <div class="crud-form__card-body" style="padding: 0;">
                    <div class="crud-chart-wrap">
                        <div id="chartdiv"></div>
                    </div>
                </div>
            </div>

        </div><!-- /.crud-form__grid -->
		        </div><!-- /.crud-form__grid -->

        <!-- ========== CARD HISTÓRICO (mensual) — sólo aparece si hay datos > 6 meses ========== -->
        <div id="card_historico" class="crud-form__card" style="display:none; margin-top: 16px;">
            <div class="crud-form__card-head">
                <i class="fa fa-history"></i>
                <h3>Histórico mensual — Producción acumulada</h3>
            </div>
            <div class="crud-form__card-body" style="padding: 0;">
                <div class="crud-chart-wrap">
                    <div id="chartdiv_historico" style="width:100%;height:400px;display:block;"></div>
                </div>
            </div>
        </div>

        <!-- ========== BOTONES DE ACCIÓN ========== -->
        <div class="crud-form__actions">
            <button id="exit" type="button" class="btn btn-warning" style="display:none;">
                <i class="fa fa-fw fa-reply"></i> Regresar
            </button>
            <button id="save" type="button" class="btn btn-success" tabindex="23">
                <i class="fa fa-fw fa-save"></i> Guardar
            </button>
        </div>

    </form>

</div><!-- /.crud-form -->

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
<script src="../../js/servicio-selector.js?v=16"></script>
<script src="../../js/crud-animal-selector.js?v=15"></script>

<!-- PLUGINS PARA LAS GRÁFICAS -->
<script type="text/javascript" src="../../plugins/amcharts4/core.js"></script>
<script type="text/javascript" src="../../plugins/amcharts4/charts.js"></script>
<script type="text/javascript" src="../../plugins/amcharts4/themes/animated.js"></script>

<script type="text/javascript">
/* ═══════════════════════════════════════════════════════════════════════════
   JAVASCRIPT ORIGINAL — SIN CAMBIOS EN LA LÓGICA
   ═══════════════════════════════════════════════════════════════════════════ */
var ID_GLOBAL = 0;
var buscarx = 0;

CargarGraficas(ID_GLOBAL);
BloquearCampos();
document.getElementById('cod_animal_h').disabled = true;
document.getElementById('buscar').disabled = true;

var fundo = <?php echo $_SESSION['mfundos_id']; ?>;

$("#b_codigo").click(function() {
    document.getElementById("nombre_h").value = '';
    document.getElementById("cod_animal_h").value = '';

    if ($('#b_codigo').prop('checked') == true) {
        var id_fundo = '0' + fundo + '-';
        $("#b_nro_arete").val(0);
        $("#b_codigo").val(1);
        document.getElementById('b_codigo').checked = true;
        document.getElementById('b_nro_arete').checked = false;
        document.getElementById('cod_animal_h').disabled = false;
        document.getElementById("cod_animal_h").value = 'SOVCA-' + id_fundo;
        document.getElementById('buscar').disabled = false;
        buscarx = 1;
    } else {
        $("#b_codigo").val(0);
        $("#b_nro_arete").val(0);
        document.getElementById('b_codigo').checked = false;
        document.getElementById('b_nro_arete').checked = false;
        document.getElementById('cod_animal_h').disabled = true;
        document.getElementById('buscar').disabled = true;
        buscarx = 0;
        ID_GLOBAL = 0;
    }
});

$("#b_nro_arete").click(function() {
    document.getElementById("nombre_h").value = '';
    document.getElementById("cod_animal_h").value = '';

    if ($('#b_nro_arete').prop('checked') == true) {
        $("#b_nro_arete").val(1);
        $("#b_codigo").val(0);
        document.getElementById('b_codigo').checked = false;
        document.getElementById('b_nro_arete').checked = true;
        document.getElementById('cod_animal_h').disabled = false;
        document.getElementById('buscar').disabled = false;
        buscarx = 2;
    } else {
        $("#b_codigo").val(0);
        $("#b_nro_arete").val(0);
        document.getElementById('b_codigo').checked = false;
        document.getElementById('b_nro_arete').checked = false;
        document.getElementById('cod_animal_h').disabled = true;
        document.getElementById('buscar').disabled = true;
        buscarx = 0;
        ID_GLOBAL = 0;
    }
});

$('#limpiar').click(function() {
    BloquearCampos();
    LimpiarCampos();
    document.getElementById("cod_animal_h").disabled = false;
    document.getElementById("buscar").disabled = false;
    document.getElementById("b_nro_arete").disabled = false;
    document.getElementById("b_codigo").disabled = false;
    document.getElementById('b_codigo').checked = false;
    document.getElementById('b_nro_arete').checked = false;
    document.getElementById("limpiar").disabled = true;
});

$('#buscar').click(function() {
    var rgb_animal_h = $("#cod_animal_h").val();
    ID_GLOBAL = rgb_animal_h;
    buscarx = buscarx;
    document.getElementById("nombre_h").value = '';

    $.post("../../controllers/mprodleche_controller", {
        action: "get_buscar_rgb_h",
        rgb_animal_h: rgb_animal_h,
        buscarx: buscarx
    }).done(function(data) {
        var parsedJson = $.parseJSON(data);
        if (parsedJson.resultado == 'error') {
            $(".message").html(parsedJson.mensaje);
            $('.base').unslider('animate:0');
            $('.base').unslider('animate:1');
            setTimeout(function() { $(".alert").alert('close'); }, 3000);
        } else {
            $("#nombre_h").html(data);
            $("#nombre_h").val(data);

            DesBloquearCampos();
            document.getElementById("buscar").disabled = true;
            document.getElementById("cod_animal_h").disabled = true;
            document.getElementById("limpiar").disabled = false;
            document.getElementById("b_nro_arete").disabled = true;
            document.getElementById("b_codigo").disabled = true;
        }

        var valor = $("#nombre_h").val();
        var array = valor.split("*");
        var log = array[0];
        var array2 = log.split('"');
        var log2 = array2[1];

        var log_n = array[1];
        var array_n2 = log_n.split('"');
        var log_n2 = array_n2[0];

        $("#cod_animal_h").html(log2).val(log2);
        $("#nombre_h").html(log_n2).val(log_n2);
        if (log2 != "") {
            ID_GLOBAL = log2;
            CargarGraficas(ID_GLOBAL);
        }
    });
});

$('#fecha_prodleche').datepicker({
    endDate: '+0d',
    todayBtn: false,
    todayHighlight: false,
    autoclose: true,
    language: 'es',
    showOnFocus: true
});

$("#exit").click(function() {
    $.confirm({
        title: '¿Desea Salir?',
        content: false,
        confirmButton: 'Si',
        cancelButton: 'No',
        confirmButtonClass: 'btn-success',
        cancelButtonClass: 'btn-danger',
        confirm: function() {
            setTimeout(function() {
                $(location).attr('href', '../prodleche/');
            }, 1000);
        },
        cancel: function() {}
    });
});

$('#save').click(function() {
    document.getElementById('save').disabled = true;

    $.confirm({
        title: '¿Está Seguro de Guardar el Registro ?',
        content: false,
        confirmButton: 'Si',
        cancelButton: 'No',
        confirmButtonClass: 'btn-success',
        cancelButtonClass: 'btn-danger',
        confirm: function() {
            $.post("../../controllers/mprodleche_controller", {
                action: "add",
                cod_animal_h: $("#cod_animal_h").val(),
                fecha_prodleche: $("#fecha_prodleche").val(),
                kg_leche_m: $("#kg_leche_m").val(),
                kg_leche_t: $("#kg_leche_t").val()
            }).done(function(data) {
                var parsedJson = $.parseJSON(data);
                $(".message").html(parsedJson.mensaje);
                switch (parsedJson.deslizador) {
                    case "1":
                        $('.base').unslider('animate:0'); tick = 0;
                        document.getElementById('izquierda').disabled = true;
                        $("#izquierda").css("display", "none");
                        document.getElementById('derecha').disabled = false;
                        $("#derecha").css("display", "block");
                        break;
                    case "2":
                        $('.base').unslider('animate:1'); tick = 1;
                        document.getElementById('izquierda').disabled = false;
                        $("#izquierda").css("display", "block");
                        document.getElementById('derecha').disabled = true;
                        $("#derecha").css("display", "none");
                        break;
                }
                if (parsedJson.resultado != 'error') {
                    $('.base').unslider('animate:0');
                    setTimeout(function() {
                        $(location).attr('href', './');
                    }, 3000);
                }
            }, "json");
        },
        cancel: function() {
            document.getElementById('save').disabled = false;
        }
    });
});

function esnombre(e) {
    k = (document.all) ? e.keyCode : e.which;
    if (k == 8 || k == 0 || k == 13) return true;
    patron = /[A-Z\,\  ]/;
    n = String.fromCharCode(k);
    if (patron.test(n) == '') {
        document.getElementById('ms_nombre').style.display = 'block';
        document.getElementById("ms_nombre").innerHTML = 'Utilice solo letras mayusculas.';
        return patron.test(n);
    } else {
        document.getElementById("ms_nombre").innerHTML = '';
        return patron.test(n);
    }
}

function filterFloat(evt, input) {
    var key = window.Event ? evt.which : evt.keyCode;
    var chark = String.fromCharCode(key);
    var tempValue = input.value + chark;
    if (key >= 48 && key <= 57) {
        if (filter(tempValue) === false) return false; else return true;
    } else {
        if (key == 8 || key == 13 || key == 0) return true;
        else if (key == 46) { if (filter(tempValue) === false) return false; else return true; }
        else return false;
    }
}

function filter(__val__) {
    var preg = /^([0-9]+\.?[0-9]{0,2})$/;
    if (preg.test(__val__) === true) return true; else return false;
}

function SumarKgLeche(valor) {
    $("#kg_total_leche").val(0);
    var leche_m = $("#kg_leche_m").val();
    var leche_t = $("#kg_leche_t").val();
    if (leche_m != '') { leche_m = parseFloat(leche_m) } else { leche_m = 0 }
    if (leche_t != '') { leche_t = parseFloat(leche_t) } else { leche_t = 0 }
    var total = (leche_m) + (leche_t);
    $("#kg_total_leche").val(total);
}

/* ═══════════════════════════════════════════════════════════════════════════
   GRÁFICA — Línea suave con área rellena + data labels visibles
   Reemplaza el XYChart3D/ColumnSeries3D original.
   ═══════════════════════════════════════════════════════════════════════════ */
/* ═══════════════════════════════════════════════════════════════════════════
   GRÁFICA — Línea limpia con área, marcadores y tooltip enriquecido
   Solo marca el valor máximo con etiqueta; el resto se ve en hover.
   ═══════════════════════════════════════════════════════════════════════════ */
/* ═══════════════════════════════════════════════════════════════════════════
   UTILIDADES FECHA / AGRUPACIÓN
   ═══════════════════════════════════════════════════════════════════════════ */
function parseFechaDDMMYYYY(s) {
    if (!s) return null;
    var m = String(s).match(/^(\d{1,2})-(\d{1,2})-(\d{4})$/);
    if (!m) return null;
    return new Date(parseInt(m[3], 10), parseInt(m[2], 10) - 1, parseInt(m[1], 10));
}

function procesarDatosGrafica(datos, mesesReciente) {
    var hoy = new Date();
    var corte = new Date(hoy.getFullYear(), hoy.getMonth() - mesesReciente + 1, 1);

    var recientes = [];
    var antiguos = [];

    (datos || []).forEach(function(row) {
        var f = parseFechaDDMMYYYY(row.fecha_produccion);
        if (!f) return;
        if (f >= corte) recientes.push(row);
        else antiguos.push(row);
    });

    // Ordenar recientes asc
    recientes.sort(function(a, b) {
        var fa = parseFechaDDMMYYYY(a.fecha_produccion);
        var fb = parseFechaDDMMYYYY(b.fecha_produccion);
        return (fa || 0) - (fb || 0);
    });

    // Agrupar antiguos por mes
    var meses = {};
    antiguos.forEach(function(row) {
        var f = parseFechaDDMMYYYY(row.fecha_produccion);
        if (!f) return;
        var key = f.getFullYear() + '-' + ('0' + (f.getMonth() + 1)).slice(-2);
        if (!meses[key]) meses[key] = { mes: key, total_leche: 0, dias: 0 };
        meses[key].total_leche += parseFloat(row.total_leche) || 0;
        meses[key].dias++;
    });

    var nombres = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
    var historicos = Object.keys(meses).sort().map(function(k) {
        var m = meses[k];
        var partes = k.split('-');
        m.mes_label = nombres[parseInt(partes[1], 10) - 1] + ' ' + partes[0].slice(-2);
        m.total_leche = Math.round(m.total_leche * 100) / 100;
        return m;
    });

    return { recientes: recientes, historicos: historicos };
}

/* ═══════════════════════════════════════════════════════════════════════════
   GRÁFICAS — Diaria (últimos N meses) + Histórica (mensual)
   ═══════════════════════════════════════════════════════════════════════════ */
var _chartDiario = null;
var _chartMensual = null;

function CargarGraficas(ID_GLOBAL) {
    var MESES_RECIENTE = 6; // ← ajusta aquí si quieres 9

    am4core.ready(function() {
        am4core.useTheme(am4themes_animated);

        var cod_rgb_animal = ID_GLOBAL;

        // Destruir charts previos (evita memory leak al recargar)
        if (_chartDiario)  { try { _chartDiario.dispose(); }  catch(e){} _chartDiario = null; }
        if (_chartMensual) { try { _chartMensual.dispose(); } catch(e){} _chartMensual = null; }

        $.post("../../data_json/data_prdlechedashboard?cod_rgb_animal=" + cod_rgb_animal)
            .done(function(data) {
                var datum = [];
                try { datum = $.parseJSON(data); } catch (e) { datum = []; }

                var proc = procesarDatosGrafica(datum, MESES_RECIENTE);

                _chartDiario = crearChartDiario(proc.recientes);

                if (proc.historicos.length > 0) {
                    $('#card_historico').show();
                    setTimeout(function() {
                        _chartMensual = crearChartMensual(proc.historicos);
                    }, 80);
                } else {
                    $('#card_historico').hide();
                }
            })
            .fail(function() {
                _chartDiario = crearChartDiario([]);
                $('#card_historico').hide();
            });
    });
}

/* ─────────────────────────── GRÁFICA DIARIA ─────────────────────────── */
function crearChartDiario(datos) {
    var chart = am4core.create("chartdiv", am4charts.XYChart);
    chart.data = datos;
    chart.padding(20, 20, 10, 10);
    chart.fontSize = 12;

    // Cálculo del máximo (una sola vez)
    var maxValue = -Infinity;
    datos.forEach(function(r) {
        var v = parseFloat(r.total_leche);
        if (!isNaN(v) && v > maxValue) maxValue = v;
    });
    if (!isFinite(maxValue)) maxValue = 0;

    // Eje X
    var cat = chart.xAxes.push(new am4charts.CategoryAxis());
    cat.dataFields.category = "fecha_produccion";
    cat.renderer.minGridDistance = 45;
    cat.renderer.labels.template.rotation = 270;
    cat.renderer.labels.template.horizontalCenter = "right";
    cat.renderer.labels.template.verticalCenter = "middle";
    cat.renderer.labels.template.fontSize = 10;
    cat.renderer.labels.template.fill = am4core.color("#64748b");
    cat.renderer.labels.template.paddingTop = 10;
    cat.renderer.grid.template.location = 0;
    cat.renderer.grid.template.strokeOpacity = 0;
    cat.renderer.line.strokeOpacity = 0.25;
    cat.renderer.line.stroke = am4core.color("#cbd5e1");

    // Eje Y
    var val = chart.yAxes.push(new am4charts.ValueAxis());
    val.title.text = "Kg de Leche (Día)";
    val.title.fontWeight = "600";
    val.title.fontSize = 12;
    val.title.fill = am4core.color("#475569");
    val.renderer.labels.template.fontSize = 11;
    val.renderer.labels.template.fill = am4core.color("#64748b");
    val.renderer.minGridDistance = 35;
    val.renderer.grid.template.strokeOpacity = 0.15;
    val.renderer.grid.template.stroke = am4core.color("#94a3b8");
    val.renderer.line.strokeOpacity = 0.25;
    val.renderer.line.stroke = am4core.color("#cbd5e1");
    val.min = 0;

    // Serie línea suave + área
    var series = chart.series.push(new am4charts.LineSeries());
    series.dataFields.valueY = "total_leche";
    series.dataFields.categoryX = "fecha_produccion";
    series.name = "Producción diaria";
    series.strokeWidth = 2.5;
    series.stroke = am4core.color("#22B34E");
    series.fill = am4core.color("#22B34E");
    series.fillOpacity = 0.15;
    series.tensionX = 0.85;
    series.tensionY = 0.85;
    series.sequencedInterpolation = true;

    var bullet = series.bullets.push(new am4charts.CircleBullet());
    bullet.circle.radius = 3.5;
    bullet.circle.fill = am4core.color("#ffffff");
    bullet.circle.stroke = am4core.color("#22B34E");
    bullet.circle.strokeWidth = 2;
    bullet.setStateOnChildren = true;
    bullet.states.create("hover").properties.scale = 1.8;

    // Label solo en el máximo
    var maxBullet = series.bullets.push(new am4charts.LabelBullet());
    maxBullet.label.text = "{valueY}";
    maxBullet.label.fontSize = 11;
    maxBullet.label.fontWeight = "700";
    maxBullet.label.fill = am4core.color("#ffffff");
    maxBullet.label.background.fill = am4core.color("#16a34a");
    maxBullet.label.background.cornerRadius = 4;
    maxBullet.label.padding(3, 7, 3, 7);
    maxBullet.label.dy = -22;
    maxBullet.label.hideOversized = true;
    maxBullet.label.truncate = false;
    maxBullet.label.adapter.add("visible", function(visible, target) {
        if (!target.dataItem) return false;
        return target.dataItem.values.valueY.value === maxValue;
    });

    // Tooltip
    series.tooltipText = "[bold]{categoryX}[/]\n[font-size:14px]{valueY} Kg[/]";
    series.tooltip.getFillFromObject = false;
    series.tooltip.background.fill = am4core.color("#15803d");
    series.tooltip.background.stroke = am4core.color("#14532d");
    series.tooltip.background.cornerRadius = 8;
    series.tooltip.label.fill = am4core.color("#ffffff");
    series.tooltip.label.fontSize = 12;
    series.tooltip.paddingTop = 8;
    series.tooltip.paddingBottom = 8;
    series.tooltip.paddingLeft = 12;
    series.tooltip.paddingRight = 12;

    // Cursor
    chart.cursor = new am4charts.XYCursor();
    chart.cursor.lineX.strokeOpacity = 0;
    chart.cursor.lineY.stroke = am4core.color("#94a3b8");
    chart.cursor.lineY.strokeOpacity = 0.6;
    chart.cursor.lineY.strokeDasharray = "4,4";
    chart.cursor.behavior = "zoomX";

    // Leyenda
    chart.legend = new am4charts.Legend();
    chart.legend.position = "bottom";
    chart.legend.contentAlign = "center";
    chart.legend.marginTop = 10;
    chart.legend.labels.template.fontSize = 12;
    chart.legend.labels.template.fill = am4core.color("#475569");
    chart.legend.markers.template.width = 14;
    chart.legend.markers.template.height = 14;

    return chart;
}

/* ─────────────────────────── GRÁFICA MENSUAL HISTÓRICA ─────────────────────────── */
/* ─────────────────────────── GRÁFICA MENSUAL HISTÓRICA (línea) ─────────────────────────── */
function crearChartMensual(datos) {
    var chart = am4core.create("chartdiv_historico", am4charts.XYChart);
    chart.data = datos;
    chart.padding(20, 20, 10, 10);
    chart.fontSize = 12;

    // Cálculo del máximo (una sola vez)
    var maxValue = -Infinity;
    datos.forEach(function(r) {
        var v = parseFloat(r.total_leche);
        if (!isNaN(v) && v > maxValue) maxValue = v;
    });
    if (!isFinite(maxValue)) maxValue = 0;

    // Eje X (meses)
    var cat = chart.xAxes.push(new am4charts.CategoryAxis());
    cat.dataFields.category = "mes_label";
    cat.renderer.minGridDistance = 40;
    cat.renderer.labels.template.fontSize = 11;
    cat.renderer.labels.template.fill = am4core.color("#475569");
    cat.renderer.labels.template.paddingTop = 6;
    cat.renderer.grid.template.location = 0;
    cat.renderer.grid.template.strokeOpacity = 0;
    cat.renderer.line.strokeOpacity = 0.25;
    cat.renderer.line.stroke = am4core.color("#cbd5e1");

    // Eje Y
    var val = chart.yAxes.push(new am4charts.ValueAxis());
    val.title.text = "Kg acumulados en el mes";
    val.title.fontWeight = "600";
    val.title.fontSize = 12;
    val.title.fill = am4core.color("#475569");
    val.renderer.labels.template.fontSize = 11;
    val.renderer.labels.template.fill = am4core.color("#64748b");
    val.renderer.minGridDistance = 40;
    val.renderer.grid.template.strokeOpacity = 0.15;
    val.renderer.grid.template.stroke = am4core.color("#94a3b8");
    val.renderer.line.strokeOpacity = 0.25;
    val.renderer.line.stroke = am4core.color("#cbd5e1");
    val.min = 0;

    // Serie línea suave + área (mismo estilo que la diaria)
    var series = chart.series.push(new am4charts.LineSeries());
    series.dataFields.valueY = "total_leche";
    series.dataFields.categoryX = "mes_label";
    series.name = "Total mensual acumulado";
    series.strokeWidth = 3;
    series.stroke = am4core.color("#22B34E");
    series.fill = am4core.color("#22B34E");
    series.fillOpacity = 0.15;
    series.tensionX = 0.85;
    series.tensionY = 0.85;
    series.sequencedInterpolation = true;

    // Marcadores circulares
    var bullet = series.bullets.push(new am4charts.CircleBullet());
    bullet.circle.radius = 5;
    bullet.circle.fill = am4core.color("#ffffff");
    bullet.circle.stroke = am4core.color("#22B34E");
    bullet.circle.strokeWidth = 2.5;
    bullet.setStateOnChildren = true;
    bullet.states.create("hover").properties.scale = 1.6;

    // Data label en TODOS los puntos (pocos meses → no se satura)
    var labelBullet = series.bullets.push(new am4charts.LabelBullet());
    labelBullet.label.text = "{valueY}";
    labelBullet.label.fontSize = 11;
    labelBullet.label.fontWeight = "700";
    labelBullet.label.fill = am4core.color("#15803d");
    labelBullet.label.background.fill = am4core.color("#ffffff");
    labelBullet.label.background.fillOpacity = 0.95;
    labelBullet.label.background.stroke = am4core.color("#bbf7d0");
    labelBullet.label.background.strokeWidth = 1;
    labelBullet.label.background.cornerRadius = 4;
    labelBullet.label.padding(3, 7, 3, 7);
    labelBullet.label.dy = -22;
    labelBullet.label.hideOversized = true;
    labelBullet.label.truncate = false;
    // Si hay muchos meses (>14) ocultar las etiquetas para no saturar
    labelBullet.label.adapter.add("visible", function(visible, target) {
        if (!target.dataItem) return false;
        return datos.length <= 14;
    });

    // Tooltip
    series.tooltipText = "[bold]{categoryX}[/]\n[font-size:14px]{valueY} Kg[/]";
    series.tooltip.getFillFromObject = false;
    series.tooltip.background.fill = am4core.color("#15803d");
    series.tooltip.background.stroke = am4core.color("#14532d");
    series.tooltip.background.cornerRadius = 8;
    series.tooltip.background.pointerLength = 8;
    series.tooltip.label.fill = am4core.color("#ffffff");
    series.tooltip.label.fontSize = 12;
    series.tooltip.paddingTop = 8;
    series.tooltip.paddingBottom = 8;
    series.tooltip.paddingLeft = 12;
    series.tooltip.paddingRight = 12;

    // Cursor
    chart.cursor = new am4charts.XYCursor();
    chart.cursor.lineX.strokeOpacity = 0;
    chart.cursor.lineY.stroke = am4core.color("#94a3b8");
    chart.cursor.lineY.strokeOpacity = 0.6;
    chart.cursor.lineY.strokeDasharray = "4,4";

    // Leyenda
    chart.legend = new am4charts.Legend();
    chart.legend.position = "bottom";
    chart.legend.contentAlign = "center";
    chart.legend.marginTop = 10;
    chart.legend.labels.template.fontSize = 12;
    chart.legend.labels.template.fill = am4core.color("#475569");
    chart.legend.markers.template.width = 14;
    chart.legend.markers.template.height = 14;

    return chart;
}

$(function() {
    $("#cod_animal_h").maxLength(15);
    $("#kg_leche_m").maxLength(5);
    $("#kg_leche_t").maxLength(5);
});

function es_codigo_nro_arete(e) {
    k = (document.all) ? e.keyCode : e.which;
    if (k == 8 || k == 0 || k == 13) return true;
    patron = /[A-Za-z0-9\.\-]/;
    n = String.fromCharCode(k);
    if (patron.test(n) == '') {
        document.getElementById('ms_cod_animal').style.display = 'block';
        document.getElementById("ms_cod_animal").innerHTML = 'Sólo se pemite el - y el .';
        return patron.test(n);
    } else {
        document.getElementById("ms_cod_animal").innerHTML = '';
        return patron.test(n);
    }
}

function LimpiarCampos() {
    ID_GLOBAL = 0;
    document.getElementById("nombre_h").value = '';
    document.getElementById("cod_animal_h").value = '';
    document.getElementById("fecha_prodleche").value = '';
    document.getElementById("kg_leche_m").value = '';
    document.getElementById("kg_leche_t").value = '';
    document.getElementById("kg_total_leche").value = '';
    CargarGraficas(ID_GLOBAL);
}

function BloquearCampos() {
    document.getElementById("limpiar").disabled = true;
    document.getElementById("nombre_h").disabled = true;
    document.getElementById("fecha_prodleche").disabled = true;
    document.getElementById("kg_leche_m").disabled = true;
    document.getElementById("kg_leche_t").disabled = true;
    document.getElementById("kg_total_leche").disabled = true;
}

function DesBloquearCampos() {
    document.getElementById("nombre_h").disabled = false;
    document.getElementById("fecha_prodleche").disabled = false;
    document.getElementById("kg_leche_m").disabled = false;
    document.getElementById("kg_leche_t").disabled = false;
    document.getElementById("kg_total_leche").disabled = false;
}
</script>

<script type="text/javascript">
$(document).on('click', '#exit_top', function() { $('#exit').trigger('click'); });
(function crudUnlockHighlighter() {
    var seen = new WeakMap();
    var DURATION = 4800;
    function findLabelForCheckbox(el) {
        var lbl = el.closest('label'); if (lbl) return lbl;
        var p = el.previousElementSibling;
        while (p) { if (p.tagName === 'LABEL') return p; p = p.previousElementSibling; }
        var n = el.nextElementSibling;
        while (n) { if (n.tagName === 'LABEL') return n; n = n.nextElementSibling; }
        return null;
    }
    function highlight(el) {
        if (el.tagName === 'BUTTON') {
            el.classList.add('crud-just-unlocked-btn');
            setTimeout(function () { el.classList.remove('crud-just-unlocked-btn'); }, DURATION); return;
        }
        if (el.type === 'checkbox') {
            el.classList.add('crud-just-unlocked-check');
            var l = findLabelForCheckbox(el);
            if (l) {
                l.classList.add('crud-just-unlocked-check-label');
                setTimeout(function(){ el.classList.remove('crud-just-unlocked-check'); l.classList.remove('crud-just-unlocked-check-label'); }, DURATION);
            } else { setTimeout(function(){ el.classList.remove('crud-just-unlocked-check'); }, DURATION); }
            return;
        }
        el.classList.add('crud-unlocked');
        setTimeout(function () { el.classList.remove('crud-unlocked'); }, DURATION);
    }
    function check() {
        var fields = document.querySelectorAll('.crud-form input, .crud-form select, .crud-form textarea, .crud-form button');
        Array.prototype.forEach.call(fields, function (el) {
            var was = seen.get(el), now = el.disabled;
            if (was === true && now === false) highlight(el);
            seen.set(el, now);
        });
    }
    document.addEventListener('DOMContentLoaded', function () {
        check();
        var observer = new MutationObserver(function (m) {
            var t = false;
            m.forEach(function (x) { if (x.type === 'attributes' && x.attributeName === 'disabled') t = true; });
            if (t) check();
        });
        observer.observe(document.body, { attributes: true, attributeFilter: ['disabled'], subtree: true });
    });
})();
</script>

<!-- SELECTOR AMIGABLE: listado de hembras aptas para producción de leche -->
<script type="text/javascript">
$(function () {
    bovsCrudAnimalSelector({
        lista:         'sel_animal',
        url:           '../../controllers/mprodleche_controller',
        accion:        'listar_candidatos_prodleche',
        rol:           'hembra',
        campoCodigo:   'cod_animal_h',
        botonBuscar:   'buscar',
        botonCambiar:  'limpiar',
        textoVacio:    'No hay hembras con aptitud de producción de leche en este fundo.',
        textoResumen:  function (ok) { return ok + ' hembras disponibles'; },
        alElegir:      function (fila) { buscarx = 1; }
    });
});
</script>

<?php include_once("../layouts/pie.php") ?>