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
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 4px;
    }

    .crud-label-row>label {
        margin: 0 !important;
        font-size: inherit;
        text-transform: uppercase;
        font-weight: 700;
        color: #475569;
        letter-spacing: .04em;
        flex: 1 1 auto;
        min-width: 0;
    }

    .crud-label-row .crud-check-group {
        display: inline-flex;
        align-items: center;
        gap: 14px;
        flex: 0 0 auto;
    }

    .crud-label-row .crud-check-group label {
        margin: 0 !important;
        font-size: 13.5px !important;
        font-weight: 600 !important;
        text-transform: none !important;
        letter-spacing: 0 !important;
        color: #334155 !important;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
        white-space: nowrap;
    }

    .crud-label-row .crud-check-group input[type="checkbox"] {
        width: 18px;
        height: 18px;
        margin: 0;
        cursor: pointer;
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

    .crud-chart-vacio {
        text-align: center;
        padding: 60px 20px;
        color: #94a3b8;
        font-style: italic;
        font-size: 13.5px;
    }

    /* ============================================================
   FEEDBACK VISUAL DE DESBLOQUEO
   ============================================================ */
    @keyframes crud-flash-input {
        0% {
            box-shadow: 0 0 0 0 rgba(22, 163, 74, .75);
            background-color: #dcfce7;
            border-color: #16a34a;
        }

        30% {
            box-shadow: 0 0 0 10px rgba(22, 163, 74, 0);
            background-color: #f0fdf4;
            border-color: #16a34a;
        }

        70% {
            box-shadow: 0 0 0 0 rgba(22, 163, 74, .35);
            background-color: #ffffff;
        }

        100% {
            box-shadow: 0 0 0 0 rgba(22, 163, 74, 0);
            background-color: inherit;
        }
    }

    .crud-form .form-control.crud-unlocked,
    .crud-form input.crud-unlocked,
    .crud-form select.crud-unlocked,
    .crud-form textarea.crud-unlocked,
    .crud-form select.btn-sm.crud-unlocked {
        animation: crud-flash-input 4.8s ease !important;
        border-color: #16a34a !important;
        transition: none !important;
    }

    @keyframes crud-flash-btn {
        0% {
            transform: scale(1);
            box-shadow: 0 0 0 0 rgba(22, 163, 74, .65);
        }

        18% {
            transform: scale(1.08);
            box-shadow: 0 0 0 12px rgba(22, 163, 74, 0);
        }

        45% {
            transform: scale(1);
            box-shadow: 0 0 0 0 rgba(22, 163, 74, .45);
        }

        70% {
            transform: scale(1.05);
            box-shadow: 0 0 0 10px rgba(22, 163, 74, 0);
        }

        100% {
            transform: scale(1);
            box-shadow: 0 0 0 0 rgba(22, 163, 74, 0);
        }
    }

    .crud-form button.crud-just-unlocked-btn,
    .crud-form .btn.crud-just-unlocked-btn {
        animation: crud-flash-btn 4.8s ease !important;
        outline: 3px solid rgba(22, 163, 74, .60) !important;
        outline-offset: 3px !important;
        position: relative;
        z-index: 2;
    }

    @keyframes crud-flash-check {
        0% {
            transform: scale(1);
            background-color: rgba(22, 163, 74, .30);
            box-shadow: 0 0 0 0 rgba(22, 163, 74, .55);
        }

        25% {
            transform: scale(1.25);
            background-color: rgba(22, 163, 74, .55);
            box-shadow: 0 0 0 10px rgba(22, 163, 74, 0);
        }

        60% {
            transform: scale(1);
            background-color: rgba(22, 163, 74, .30);
            box-shadow: 0 0 0 0 rgba(22, 163, 74, .30);
        }

        100% {
            transform: scale(1);
            background-color: transparent;
            box-shadow: 0 0 0 0 rgba(22, 163, 74, 0);
        }
    }

    .crud-form input[type="checkbox"].crud-just-unlocked-check {
        animation: crud-flash-check 4.8s ease !important;
        outline: 2px solid #16a34a !important;
        outline-offset: 2px !important;
        border-radius: 3px;
    }

    @keyframes crud-flash-check-label {
        0% {
            background-color: rgba(22, 163, 74, .30);
            color: #15803d;
            transform: translateX(0);
        }

        30% {
            background-color: rgba(22, 163, 74, .18);
            color: #15803d;
            transform: translateX(-3px);
        }

        60% {
            background-color: rgba(22, 163, 74, .08);
            color: #15803d;
            transform: translateX(0);
        }

        100% {
            background-color: transparent;
            color: inherit;
        }
    }

    .crud-form label.crud-just-unlocked-check-label {
        animation: crud-flash-check-label 4.8s ease !important;
        border-radius: 6px !important;
        padding: 3px 8px !important;
        font-weight: 800 !important;
        transition: none !important;
    }

    /* ============================================================
   GRID ESPECÍFICO DE ESTE FORMULARIO
   Formulario (5) + Gráfico (7) en desktop, apilado en móvil
   ============================================================ */
    @media (min-width: 993px) {
        #grid_consecutivos {
            grid-template-columns: 1.2fr 0.8fr;
        }
    }
</style>

<div class="crud-form">

    <div class="message"></div>

    <header class="crud-form__header">
        <span class="crud-form__icon"><i class="fa fa-balance-scale"></i></span>
        <div>
            <h3 class="crud-form__title">Registrar Peso</h3>
            <p class="crud-form__subtitle">Registre un nuevo pesaje del animal seleccionado</p>
        </div>
        <div class="crud-form__header-actions">
            <button id="exit_top" type="button" class="btn btn-warning">
                <i class="fa fa-reply"></i> Regresar
            </button>
        </div>
    </header>

    <form id="form" role="form" enctype="multipart/form-data">

        <div class="crud-form__grid" id="grid_consecutivos">

            <!-- ========== CARD 1: Formulario de pesaje ========== -->
            <div class="crud-form__card">
                <div class="crud-form__card-head">
                    <i class="fa fa-pencil"></i>
                    <h3>Datos del Pesaje</h3>
                </div>
                <div class="crud-form__card-body">

                    <div class="crud-form__field">
                        <label>Seleccione el Animal
                            <span class="sel-animal-hint">(sólo animales activos del fundo)</span>
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
                            <label for="cod_animal">N°. Arete-Chip / Código</label>
                            <div class="crud-check-group">
                                <label><input type="checkbox" id="b_nro_arete"> Nro Arete</label>
                                <label><input type="checkbox" id="b_codigo"> Código</label>
                            </div>
                        </div>
                        <input class="form-control bbb" id="cod_animal" type="text"
                            placeholder="Indique el Código o Nro Arete de la Hembra" onpaste="return true"
                            onkeypress="return es_codigo_nro_arete(event);">
                        <div id='ms_cod_animal'>
                            <p></p>
                        </div>
                        <button id="buscar" type="button" class="btn btn-success btn-xs" tabindex="57">
                            <i class="fa fa-fw fa-search"></i>Buscar
                        </button>
                    </div>

                    <div class="crud-form__field">
                        <div class="crud-label-row">
                            <label for="nombre_animal">Animal seleccionado</label>
                            <button id="limpiar" type="button" class="btn btn-default btn-sm" tabindex="58">
                                <i class="fa fa-fw fa-trash-o"></i> Cambiar
                            </button>
                        </div>
                        <input class="form-control bbb" id="nombre_animal" type="text" tabindex="1"
                            placeholder="Seleccione una fila del listado" onpaste="return false" readonly="true">
                        <div id='ms_nombre'>
                            <p></p>
                        </div>
                    </div>

                    <div class="crud-form__field">
                        <label for="fecha_pesaje">Fecha del Pesaje</label>
                        <input class="form-control bbb" id="fecha_pesaje" data-date-format="dd-mm-yyyy"
                            placeholder="dia-mes-año" type="text" onpaste="return false" tabindex="10" readonly="true">
                    </div>

                    <div class="crud-form__field">
                        <label for="peso">Peso (Kg)</label>
                        <input class="form-control bbb" id="peso" type="text" placeholder="Peso del Animal en KG"
                            onkeypress="return filterFloat(event,this)" onpaste="return false" tabindex="16">
                        <div id='ms_peso'>
                            <p></p>
                        </div>
                    </div>

                </div>
            </div>

            <!-- ========== CARD 2: Gráfico amCharts ========== -->
            <div class="crud-form__card">
                <div class="crud-form__card-head">
                    <i class="fa fa-line-chart"></i>
                    <h3>Consecutivo de Peso</h3>
                </div>
                <div class="crud-form__card-body" style="padding: 0;">
                    <div class="crud-chart-wrap">
                        <div id="chartdiv"></div>
                    </div>
                </div>
            </div>

        </div><!-- /.crud-form__grid -->

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
    var num_reg = 0;
    var fundo = <?php echo $_SESSION['mfundos_id']; ?>;

    var msj = "";
    var Save_Reg = false;

    BloquearCampos();
    CargarGraficas();

    $('#limpiar').click(function () {
        BloquearCampos();
        document.getElementById("cod_animal").disabled = false;
        document.getElementById("buscar").disabled = false;
        document.getElementById("limpiar").disabled = true;
        document.getElementById("fecha_pesaje").value = "";
        document.getElementById("peso").value = "";
        document.getElementById("nombre_animal").value = "";
        document.getElementById("b_nro_arete").disabled = false;
        document.getElementById("b_codigo").disabled = false;
        document.getElementById('b_codigo').checked = false;
        document.getElementById('b_nro_arete').checked = false;
    });

    $('#buscar').click(function () {
        var rgb_animal_h = $("#cod_animal").val();
        ID_GLOBAL = rgb_animal_h;
        buscarx = buscarx;
        document.getElementById("nombre_animal").value = '';

        $.post("../../controllers/mconsecutivopeso_controller", {
            action: "get_buscar_rgb",
            rgb_animal_h: rgb_animal_h,
            buscarx: buscarx
        }).done(function (data) {
            var parsedJson = $.parseJSON(data);
            if (parsedJson.resultado == 'error') {
                $(".message").html(parsedJson.mensaje);
                $('.base').unslider('animate:0');
                $('.base').unslider('animate:1');
            } else {
                $("#nombre_animal").html(data).val(data);
                DesBloquearCampos();
                document.getElementById("buscar").disabled = true;
                document.getElementById("cod_animal").disabled = true;
                document.getElementById("limpiar").disabled = false;
                document.getElementById('save').disabled = false;
            }

            var valor = $("#nombre_animal").val();
            var array = valor.split("*");
            var log = array[0];
            var array2 = log.split('"');
            var log2 = array2[1];

            var log_n = array[1];
            var array_n2 = log_n.split('"');
            var log_n2 = array_n2[0];

            $("#cod_animal").html(log2).val(log2);
            $("#nombre_animal").html(log_n2).val(log_n2);
            if (log2 != "") {
                CargarGraficas();
            }
        });
    });

    $("#b_codigo").click(function () {
        document.getElementById("nombre_animal").value = '';
        document.getElementById("cod_animal").value = '';

        if ($('#b_codigo').prop('checked') == true) {
            var id_fundo = '0' + fundo + '-';
            $("#b_nro_arete").val(0);
            $("#b_codigo").val(1);
            document.getElementById('b_codigo').checked = true;
            document.getElementById('b_nro_arete').checked = false;
            document.getElementById('cod_animal').disabled = false;
            document.getElementById("cod_animal").value = 'SOVCA-' + id_fundo;
            document.getElementById('buscar').disabled = false;
            buscarx = 1;
        } else {
            $("#b_codigo").val(0);
            $("#b_nro_arete").val(0);
            document.getElementById('b_codigo').checked = false;
            document.getElementById('b_nro_arete').checked = false;
            document.getElementById('cod_animal').disabled = true;
            document.getElementById('buscar').disabled = true;
            buscarx = 0;
            ID_GLOBAL = 0;
        }
    });

    $("#b_nro_arete").click(function () {
        document.getElementById("nombre_animal").value = '';
        document.getElementById("cod_animal").value = '';

        if ($('#b_nro_arete').prop('checked') == true) {
            $("#b_nro_arete").val(1);
            $("#b_codigo").val(0);
            document.getElementById('b_codigo').checked = false;
            document.getElementById('b_nro_arete').checked = true;
            document.getElementById('cod_animal').disabled = false;
            document.getElementById('buscar').disabled = false;
            buscarx = 2;
        } else {
            $("#b_codigo").val(0);
            $("#b_nro_arete").val(0);
            document.getElementById('b_codigo').checked = false;
            document.getElementById('b_nro_arete').checked = false;
            document.getElementById('cod_animal').disabled = true;
            document.getElementById('buscar').disabled = true;
            buscarx = 0;
            ID_GLOBAL = 0;
        }
    });

    $('#fecha_pesaje').datepicker({
        endDate: '+0d',
        todayBtn: false,
        todayHighlight: true,
        autoclose: true,
        language: 'es',
        showOnFocus: true
    });

    $("#derecha").click(function () {
        if (tick == 0) {
            var cont_alert = $('.bbb').filter(function () { return $(this).val() == ""; }).size();
            document.getElementById('derecha').disabled = true;
            $("#derecha").css("display", "none");
            if (cont_alert > 30) {
                $.confirm({ title: '¡existen campos vacios !', cancelButton: false, confirmButton: true, closeIcon: false, confirmButton: 'cerrar', confirmButtonClass: 'btn-success', content: false });
            } else {
                $('.base').unslider('animate:1'); tick = 1;
                document.getElementById('izquierda').disabled = false;
                $("#izquierda").css("display", "block");
                setTimeout(function () { document.body.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 800);
            }
        } else if (tick == 1) {
            var cont_alert3 = $('.ccc').filter(function () { return $(this).val() == ""; }).size();
            if (cont_alert3 > 50) {
                $.confirm({ title: '¡existen campos vacios !', cancelButton: false, confirmButton: true, closeIcon: false, confirmButton: 'cerrar', confirmButtonClass: 'btn-success', content: false });
            } else {
                document.getElementById('derecha').disabled = false;
                $("#derecha").css("display", "block");
                setTimeout(function () { document.body.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 800);
            }
        }
    });

    $("#izquierda").click(function () {
        if (tick == 1) {
            $('.base').unslider('animate:0'); tick = 0;
            document.getElementById('izquierda').disabled = true;
            $("#izquierda").css("display", "none");
            document.getElementById('derecha').disabled = false;
            $("#derecha").css("display", "block");
            setTimeout(function () { document.body.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 800);
        } else if (tick == 2) {
            $('.base').unslider('animate:1'); tick = 1;
            document.getElementById('derecha').disabled = false;
            $("#derecha").css("display", "block");
            setTimeout(function () { document.body.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 800);
        }
    });

    $("#exit").click(function () {
        $.confirm({
            title: '¿Desea Salir?',
            content: false,
            confirmButton: 'Si',
            cancelButton: 'No',
            confirmButtonClass: 'btn-success',
            cancelButtonClass: 'btn-danger',
            confirm: function () {
                setTimeout(function () { $(location).attr('href', '../consecutivopesos/'); }, 1000);
            },
            cancel: function () { }
        });
    });

    $('#save').click(function () {
        document.getElementById('save').disabled = true;

        $.confirm({
            title: '¿Está Seguro de Guardar el Registro ?',
            content: false,
            confirmButton: 'Si',
            cancelButton: 'No',
            confirmButtonClass: 'btn-success',
            cancelButtonClass: 'btn-danger',
            confirm: function () {
                $.post("../../controllers/mconsecutivopeso_controller", {
                    action: "add",
                    cod_animal: $("#cod_animal").val(),
                    fecha_pesaje: $("#fecha_pesaje").val(),
                    peso: $("#peso").val()
                }).done(function (data) {
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
                        window.setTimeout('location.reload()', 3000);
                    } else {
                        setTimeout(function () {
                            $(".alert").alert('close');
                            document.getElementById('save').disabled = false;
                        }, 1500);
                    }
                }, "json");
            },
            cancel: function () {
                document.getElementById('save').disabled = false;
            }
        });
    });

    function esnumero_peso(e) {
        k = (document.all) ? e.keyCode : e.which;
        if (k == 8 || k == 0 || k == 13) return true;
        patron = "^[0-9]+(.[0-9]+)?$";
        n = String.fromCharCode(k);
        if (patron.test(n) == '') {
            document.getElementById('ms_peso').style.display = 'block';
            document.getElementById("ms_peso").innerHTML = 'Use solo números';
            return patron.test(n);
        } else {
            document.getElementById("ms_peso").innerHTML = '';
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

    function CargarGraficas() {
        am4core.ready(function () {
            am4core.useTheme(am4themes_animated);
            var chart = am4core.create("chartdiv", am4charts.XYChart3D);
            var datum = [];
            var cod_rgb_animal = ID_GLOBAL;

            $.post("../../data_json/data_pesopromdashboard?cod_rgb_animal=" + cod_rgb_animal).done(function (data) {
                var parsedJson = $.parseJSON(data);
                datum = parsedJson;
            });

            setTimeout(function () {
                chart.data = datum;
                chart.legend = new am4charts.Legend();
                let categoryAxis = chart.xAxes.push(new am4charts.CategoryAxis());
                categoryAxis.dataFields.category = "fecha_pesaje_alza";
                categoryAxis.renderer.labels.template.rotation = 270;
                categoryAxis.renderer.labels.template.hideOversized = false;
                categoryAxis.renderer.labels.template.disabled = false;
                categoryAxis.renderer.labels.template.truncate = true;
                categoryAxis.renderer.labels.template.maxWidth = 80;
                categoryAxis.renderer.minGridDistance = 10;
                categoryAxis.renderer.labels.template.fontSize = 10;
                categoryAxis.renderer.labels.template.horizontalCenter = "right";
                categoryAxis.renderer.labels.template.verticalCenter = "middle";
                categoryAxis.tooltip.label.rotation = 270;
                categoryAxis.tooltip.label.horizontalCenter = "right";
                categoryAxis.tooltip.label.verticalCenter = "middle";

                let valueAxis = chart.yAxes.push(new am4charts.ValueAxis());
                valueAxis.title.text = "Peso (Dia)";
                valueAxis.title.fontWeight = "bold";

                var series = chart.series.push(new am4charts.ColumnSeries3D());
                series.dataFields.valueY = "peso_alza";
                series.dataFields.categoryX = "fecha_pesaje_alza";
                series.name = "Consecutivo de Peso";
                series.tooltipText = "{categoryX}: [bold font-size: 12px]{valueY}[/]";
                series.columns.template.fillOpacity = .8;
                var columnTemplate = series.columns.template;
                columnTemplate.strokeWidth = 2;
                columnTemplate.strokeOpacity = 1;
                columnTemplate.stroke = am4core.color("#FFFFFF");
                columnTemplate.adapter.add("fill", function (fill, target) { return chart.colors.getIndex(target.dataItem.index); });
                columnTemplate.adapter.add("stroke", function (stroke, target) { return chart.colors.getIndex(target.dataItem.index); });

                chart.cursor = new am4charts.XYCursor();
                chart.cursor.lineX.strokeOpacity = 0;
                chart.cursor.lineY.strokeOpacity = 1;
                chart.hiddenState.properties.radius = am4core.percent(0);
            }, 500);
        });
    }

    function BloquearCampos() {
        document.getElementById("limpiar").disabled = true;
        document.getElementById("nombre_animal").disabled = true;
        document.getElementById("fecha_pesaje").disabled = true;
        document.getElementById("peso").disabled = true;
        document.getElementById('cod_animal').disabled = true;
        document.getElementById('buscar').disabled = true;
        document.getElementById('save').disabled = true;
    }

    function DesBloquearCampos() {
        document.getElementById("nombre_animal").disabled = false;
        document.getElementById("fecha_pesaje").disabled = false;
        document.getElementById("peso").disabled = false;
    }
</script>

<script type="text/javascript">
    $(document).on('click', '#exit_top', function () { $('#exit').trigger('click'); });
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
                    setTimeout(function () { el.classList.remove('crud-just-unlocked-check'); l.classList.remove('crud-just-unlocked-check-label'); }, DURATION);
                } else { setTimeout(function () { el.classList.remove('crud-just-unlocked-check'); }, DURATION); }
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

<!-- SELECTOR AMIGABLE: listado de animales aptos para el pesaje -->
<script type="text/javascript">
$(function () {
    bovsCrudAnimalSelector({
        lista:         'sel_animal',
        url:           '../../controllers/mconsecutivopeso_controller',
        accion:        'listar_candidatos_consecutivo_peso',
        rol:           'animal',
        campoCodigo:   'cod_animal',
        botonBuscar:   'buscar',
        botonCambiar:  'limpiar',
        textoVacio:    'No hay animales activos registrados en este fundo.',
        textoResumen:  function (ok) { return ok + ' animales disponibles'; },
        alElegir:      function (fila) { buscarx = 1; }
    });
});
</script>

<?php include_once("../layouts/pie.php") ?>