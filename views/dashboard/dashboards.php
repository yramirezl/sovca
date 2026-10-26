<?php

include("../../lib/validar_session.php");

ValidaSession("../login");
//VerificarAdmin($_SESSION['rolx']);

?>
<?php include("../layouts/constantes.php") ?>
<?php include_once("../layouts/cabecera.php") ?>

<div class="message"></div>
<!-- Small boxes (Stat box) -->

<div class="box-footer">
    <button id="reportes" class="btn btn-primary" type="button" data-bs-toggle="tooltip" data-bs-placement="top" title="Una carga de información adecuada garantiza la emisión de reportes confiables."><i class="fa fa-fw fa-graph"></i> Gestor de Reportes

    </button>

</div>

<div class="row">

    <div class="col-md-6">
        <!-- BAR CHART -->


        <div class="box box-success" id='prod_leche_dia'>
            <div class="box-header with-border">
                <h3 class="box-title">GANACIA DE PESO POR DIA </h3>
                <div class="box-tools pull-right">
                    <button class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
                    <button class="btn btn-box-tool" data-widget="remove"><i class="fa fa-times"></i></button>
                </div>
            </div>
            <div class="box-body">
                <div id="chartdivprol" style="width:100%;height:500px;">

                </div>
            </div><!-- /.box-body -->
        </div><!-- /.box -->


    </div>

    <div class="col-md-6">


        <div class="box box-success" id='reg_nac'>
            <div class="box-header with-border">
                <h3 class="box-title">NACIMIENTOS POR MES </h3>
                <div class="box-tools pull-right">
                    <button class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
                    <button class="btn btn-box-tool" data-widget="remove"><i class="fa fa-times"></i></button>
                </div>
            </div>
            <div class="box-body">
                <div id="chartdivnac" style="width:100%;height:500px;">

                </div>
            </div><!-- /.box-body -->
        </div><!-- /.box -->

        </form>

    </div><!-- /.box-body -->
</div><!-- /.box -->
</div>

<div class="modal fade" id="modal3" tabindex="-1" role="dialog" aria-labelledby="myModalLabel1" aria-hidden="true">
    <form id="form" role="form" enctype="multipart/form-data">
        <div class="modal-dialog modal-lm">
            <div class="modal-content">
                <div class="modal-body">
                    <div class="contenido-modal">

                        <h4 class="modal-title" id="myModalLabel1">

                            <p style="color:green;">Gestor de Reportes Parametrizables

                            </p>

                        </h4>
                        <div class="message1"></div>
                        <div class="row">
                            <!-- left column -->
                            <div class="col-md-12">
                                <!-- general form elements -->
                                <div class="box box-primary">

                                    <div class="box-header with-border"></div><!-- /.box-header -->
                                    <!-- form start -->
                                    <div class="box-body">

                                        <div class="row">
                                            <div class="col-sm-12">

                                                <div class="form-group">
                                                    <select class="form-control" id="vista">
                                                        <option value="" selected disabled>Seleccione el Reporte a
                                                            Consultar</option>

                                                        <option value="rep_prod_leche"> Producción de Leche
                                                        </option>
                                                        <option value="rep_ser_animal"> Servicios del Animal
                                                        </option>
                                                        <option value="rep_ctl_animal"> Control Sanitario
                                                        </option>
                                                        <option value="rep_grupo_etario" data-bs-placement="top" title="La fecha de nacimiento y el sexo del animal aseguran una agrupación etaria correcta."> Grupos Etarios
                                                        </option>
                                                        <option value="rep_cons_peso"> Consecutivo de Peso
                                                        </option>
                                                        <option value="rep_nacimientos"> Informe de Nacimientos
                                                        </option>


                                                    </select>
                                                </div>
                                                <label for="parametros">Parámetros:</label>
                                                <label for="sexo_hembra">
                                                    <p style="color:fuchsia;">HEMBRA</p>
                                                </label>
                                                <input type="checkbox" id="sexo_hembra">
                                                <label for="sexo_macho">
                                                    <p style="color:blue;">MACHO</p>
                                                </label>
                                                <input type="checkbox" id="sexo_macho">
                                                <label for="rango_id">
                                                    <p style="color:orange;">CÓDIGO</p>
                                                </label>
                                                <input type="checkbox" id="rango_id">
                                                <label for="rango_fecha">
                                                    <p style="color:teal;">FECHA</p>
                                                </label>
                                                <input type="checkbox" id="rango_fecha">

                                                <label for="rango_lote_unico">
                                                    <p style="color:green;">LOTE</p>
                                                </label>
                                                <input type="checkbox" id="rango_lote_unico">

                                                <label for="cod_bovs">
                                                    <p style="color:red;">CÓDIGO</p>
                                                </label>
                                                <input type="checkbox" id="cod_bovs">


                                            </div>

                                            <p></p>
                                        </div>

                                        <div class="row">

                                            <div class="col-sm-10">
                                                <div class="form-group-sm">
                                                    <label for="lote_unico">Lote</label>
                                                    <select name="lote_unico" id="lote_unico" class="form-control bbb"
                                                        tabindex="2">
                                                        <option></option>
                                                    </select>
                                                </div>


                                            </div>



                                        </div>



                                        <div class="col-sm-5">

                                            <label for="fecha_inicio">Fecha Inicio</label>
                                            <input class="form-control bbb" id="fecha_inicio"
                                                data-date-format="dd-mm-yyyy" placeholder="dia-mes-año" type="text"
                                                onpaste="return false" tabindex="14" readonly="true">
                                            <div style="background-color:#F39C12;color:#fff;text-align:center"
                                                id='ms_fecha_inicio' class="aaa">
                                                <p></p>
                                            </div>

                                        </div>

                                        <div class="col-sm-5">

                                            <label for="fecha_final">Fecha Final</label>
                                            <input class="form-control bbb" id="fecha_final"
                                                data-date-format="dd-mm-yyyy" placeholder="dia-mes-año" type="text"
                                                onpaste="return false" tabindex="15" readonly="true">
                                            <div style="background-color:#F39C12;color:#fff;text-align:center"
                                                id='ms_fecha_final' class="aaa">
                                                <p></p>
                                            </div>

                                        </div>


                                        <div class="col-sm-5">


                                            <div class="form-group-sm">
                                                <label for="ID.SOVCA.Desde">Código SOVCA Desde </label>
                                                <input class="form-control bbb" id="cod_bovs_inicio" type="text"
                                                    placeholder="Desde" onpaste="return false"
                                                    onkeypress="return esnumero_h(event);">
                                                <div style="background-color:#F39C12;color:#fff;text-align:center"
                                                    id='ms_cod_bovs_inicio'>
                                                    <p></p>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-sm-5">


                                            <div class="form-group-sm">
                                                <label for="ID.SOVCA.hasta">Código SOVCA Hasta </label>
                                                <input class="form-control bbb" id="cod_bovs_final" type="text"
                                                    placeholder="Hasta" onpaste="return false"
                                                    onkeypress="return esnumero_h(event);">
                                                <div style="background-color:#F39C12;color:#fff;text-align:center"
                                                    id='ms_cod_bovs_final'>
                                                    <p></p>
                                                </div>
                                            </div>


                                        </div>



                                    </div><!-- /.box-body -->
                                    <div class="row">
                                        <div class="col-sm-8">

                                            <label for="nro_arete">Nro Arete/Micro.Chips</label>
                                            <input type="checkbox" id="nro_arete">
                                            <label></label>
                                            <label for="codigo_bovs">Código SOVCA</label>
                                            <input type="checkbox" id="codigo_bovs">
                                        </div>

                                    </div>
                                    <div class="row">


                                        <div class="col-sm-8">

                                            <div class="form-group-sm">

                                                <input class="form-control bbb" id="id_cod_bovs" type="text"
                                                    placeholder="Micro.Chips/ Nro Arete" onpaste="return true"
                                                    onkeypress="return esnumero_h(event);">

                                                <div style="background-color:#F39C12;color:#fff;text-align:center"
                                                    id='ms_id_cod_bovs'>
                                                    <p></p>
                                                </div>
                                                <div style="background-color:#19ac1b;color:#fff;text-align:center"
                                                    id='ms_id_cod_bovs_acep'>
                                                    <p></p>
                                                </div>

                                            </div>
                                        </div>
                                        <div class="col-sm-1">
                                            <button id="valida_cod" type="button" class="btn btn-primary btn-xs "><i
                                                    class="fa fa-fw fa-check-square-o"></i></button>
                                        </div>


                                    </div>

                                </div><!-- /.box -->

                            </div>
                        </div>
                        <div class="modal-footer">
                            <button id="crear_reporte" type="button" class="btn btn-success"><i
                                    class="fa fa-fw fa-save"></i>Generar</button>

                            <button id="cancelar" type="button" class="btn btn-primary  pull-right"><i
                                    class="fa fa-fw fa-times"></i>Cancelar</button>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script type="text/javascript" src="../../plugins/confirma/jquery-confirm.min.js"></script>
<link rel="stylesheet" href="../../plugins/confirma/jquery-confirm.min.css" type="text/css" />

<script type="text/javascript" src="../../plugins/datepicker/bootstrap-datepicker.js"></script>
<script src="../../plugins/datepicker/locales/bootstrap-datepicker.es.js" charset="UTF-8"></script>
<link rel="stylesheet" href="../../plugins/datepicker/datepicker3.css" type="text/css" />


<script type="text/javascript" src="../../plugins/amcharts4/core.js"></script>
<script type="text/javascript" src="../../plugins/amcharts4/charts.js"></script>
<script type="text/javascript" src="../../plugins/amcharts4/themes/animated.js"></script>

<script type="text/javascript">
    /// Constante para dar Formato a los Monstos en Peso Colombianos 
    var fundo = <?php echo $_SESSION['mfundos_id'];  ?>; //verifica el rol del usuario
    var num_animales_activos = 0;
    var num_gestantes_activas = 0;
    var num_servidas_activas = 0;
    var num_productoras_lecha_activas = 0;
    var buscar_cod = 0;
    var tipo_rep = "";
    var cantidad_nacimientos = 0;
    var cantidad_prod_leche = 0;


    document.getElementById('fecha_inicio').disabled = true;
    $("#vista").val('');
    document.getElementById('fecha_final').disabled = true;
    document.getElementById('cod_bovs_inicio').disabled = true;
    document.getElementById('cod_bovs_final').disabled = true;
    document.getElementById('id_cod_bovs').disabled = true;
    document.getElementById('crear_reporte').disabled = true;




    $(document).ready(function() {


        $.post("../../controllers/mreportes_controller", {
            action: "search_datos_animales"
        }).done(function(data) {
            var parsedJson = $.parseJSON(data);

            var animales_activos = parsedJson.total_animales;
            var nro_nacimientos = parsedJson.nro_nacimientos;
            var nro_gestantes = parsedJson.nro_gestantes;
            var nro_prodleche = parsedJson.nro_prodleche;

            var nro_reg_prodcarne = parsedJson.nro_reg_prodcarne;
            var nro_reg_reg_leche = parsedJson.nro_reg_reg_leche;
            var nro_reg_doblep = parsedJson.nro_reg_doblep;
            var nro_servidas = parsedJson.nro_servidas;

            num_animales_activos = animales_activos;
            num_gestantes_activas = nro_gestantes;
            num_servidas_activas = nro_servidas;
            num_productoras_leche_activas = nro_prodleche;

            $("#animales_activos").html(animales_activos + '<sup style="font-size: 20px"></sup>');
            $("#nro_nacimientos").html(nro_nacimientos + '<sup style="font-size: 20px"></sup>');
            $("#total_gestantes").html(nro_gestantes + '<sup style="font-size: 20px"></sup>');
            $("#prod_leche").html(nro_prodleche + '<sup style="font-size: 20px"></sup>');

            $("#prod_carne").html(nro_reg_prodcarne + '<sup style="font-size: 20px"></sup>');
            $("#doble_proposito").html(nro_reg_doblep + '<sup style="font-size: 20px"></sup>');
            $("#prod_leche_reg").html(nro_reg_reg_leche + '<sup style="font-size: 20px"></sup>');
            $("#total_servidas").html(nro_servidas + '<sup style="font-size: 20px"></sup>');

            cantidad_nacimientos = nro_nacimientos;
            cantidad_prod_leche = nro_prodleche;

        }, "json");

    }); // end (document).ready(function()

    // if ((cantidad_nacimientos > 0) || (cantidad_prod_leche > 0)) {


    am4core.ready(function() {


        am4core.useTheme(am4themes_animated);

        // if (cantidad_nacimientos > 0) {

        //////////////////////////////////////////////////////////////

        var chart3 = am4core.create("chartdivnac", am4charts.PieChart3D);
        // Add data
        var datum3 = [];

        $.post("../../data_json/data_dashboardnac.php").done(function(data) {

            var parsedJson3 = $.parseJSON(data);
            datum3 = parsedJson3;
            // datum3.pop();
        });



        setTimeout(function() {

            // chart.legend = new am4charts.Legend();

            chart3.data = datum3;
            // chart.legend.maxHeight = 100;
            //  chart3.legend.scrollable = true;
            chart3.innerRadius = am4core.percent(30);
            var title = chart3.titles.create();
            title.text = "Nacimientos Por Mes (Últimos 3 Meses)";
            title.fontSize = 18;
            title.marginBottom = 100;

            // Add and configure Series
            var pieSeries = chart3.series.push(new am4charts.PieSeries3D());
            pieSeries.dataFields.value = "cantidad";
            pieSeries.dataFields.category = "sexo";
            // pieSeries.dataFields.value = "valor";
            // pieSeries.dataFields.category = "region";


            pieSeries.slices.template.stroke = am4core.color("#fff");
            pieSeries.slices.template.strokeOpacity = 1;
            pieSeries.labels.template.maxWidth = 120;
            pieSeries.labels.template.wrap = true;

            pieSeries.colors.list = [
                am4core.color("#76f218"),
                am4core.color("#f28926"),
                am4core.color("#357918"),
                am4core.color("#f2e826"),
                am4core.color("#2beede"),
                am4core.color("#0a3f6f"),
            ];
            // This creates initial animation
            //  pieSeries.labels.template.maxWidth = 90;
            //  pieSeries.labels.template.wrap = true;<<<s
            pieSeries.labels.template.text =
                "[bold font-size: 10px]{category} -> {value.percent.formatNumber('#.#')}%";
            pieSeries.hiddenState.properties.opacity = 1;
            pieSeries.hiddenState.properties.endAngle = -90;
            pieSeries.hiddenState.properties.startAngle = -90;
            pieSeries.slices.template.tooltipText =
                "{category}: [bold font-size: 12px]{value.value}";

            chart3.hiddenState.properties.radius = am4core.percent(0);
        }, 500);


        var chart = am4core.create("chartdivprol", am4charts.XYChart3D);


        var datum = [];
        $.post("../../data_json/data_dashboard_consec_peso.php").done(function(data) {

            var parsedJson = $.parseJSON(data);
            datum = parsedJson;
            //   datum.pop();
        });

        setTimeout(function() {

            chart.data = datum;
            chart.legend = new am4charts.Legend();
            // Create axes
            let categoryAxis = chart.xAxes.push(new am4charts.CategoryAxis());
            categoryAxis.dataFields.category = "fecha_pesaje";
            categoryAxis.renderer.labels.template.rotation = 270;
            categoryAxis.renderer.labels.template.hideOversized = false;
            categoryAxis.renderer.labels.template.disabled = false;
            categoryAxis.renderer.labels.template.truncate = true;
            categoryAxis.renderer.labels.template.maxWidth = 120;
            categoryAxis.renderer.minGridDistance = 10;
            categoryAxis.renderer.labels.template.fontSize = 10;
            categoryAxis.renderer.labels.template.horizontalCenter = "right";
            categoryAxis.renderer.labels.template.verticalCenter = "middle";
            categoryAxis.tooltip.label.rotation = 270;
            categoryAxis.tooltip.label.horizontalCenter = "right";
            categoryAxis.tooltip.label.verticalCenter = "middle";


            let valueAxis = chart.yAxes.push(new am4charts.ValueAxis());
            valueAxis.title.text = "Kg de Leche (Dia)";
            valueAxis.title.fontWeight = "bold";

            // Create series
            var series = chart.series.push(new am4charts.ColumnSeries3D());
            series.dataFields.valueY = "total_leche";
            series.dataFields.categoryX = "fecha_produccion";
            series.name = "Produccion Diaria de Leche (Últimos 60 dias)";
            series.tooltipText = "{categoryX}: [bold font-size: 12px]{valueY}[/]";
            series.columns.template.fillOpacity = .8;
            var columnTemplate = series.columns.template;
            columnTemplate.strokeWidth = 2;
            columnTemplate.strokeOpacity = 1;
            columnTemplate.stroke = am4core.color("#FFFFFF");

            columnTemplate.adapter.add("fill", function(fill, target) {
                return chart.colors.getIndex(target.dataItem.index);
            })

            columnTemplate.adapter.add("stroke", function(stroke, target) {
                return chart.colors.getIndex(target.dataItem.index);
            })

            chart.cursor = new am4charts.XYCursor();
            chart.cursor.lineX.strokeOpacity = 0;
            chart.cursor.lineY.strokeOpacity = 0;
            chart.hiddenState.properties.radius = am4core.percent(0);

        }, 500);

        // }else{
        //     $('#prod_leche_dia').hide();
        // }


        //#######################################################################################

    }); // end am4core.ready()

    // }else{
    //     $('#prod_leche_dia').hide();
    //     $('#reg_nac').hide();

    // }
    //#######################################################################################
    //#######################################################################################
    //########################## GESTOS DE REPORTES #########################################
    //#######################################################################################
    //#######################################################################################
    //#######################################################################################

    desactivar_todo();
    LimpiarCampos();




    $("#vista").change(function() {
        desactivar_todo();
        tipo_rep = "";


        if ($("#vista").val() == 'rep_prod_leche') {
             $("#lote_unico").val() == '';

            $.post("../../controllers/mlotes_controller", {
                action: "get_lote_prod_leche"
            }).done(function(data) {
                $("#lote_unico").html(data);

            });


            tipo_rep = 'rep_prod_leche';
            activar_filtros_lote();

        } else if ($("#vista").val() == 'rep_ser_animal') {
            tipo_rep = 'rep_ser_animal';
            activar_filtros_servicio();
            activar_filtro_sexo();

        } else if ($("#vista").val() == 'rep_ctl_animal') {
            tipo_rep = 'rep_ctl_animal';

            activar_filtros_ctl_animal();

        } else if ($("#vista").val() == 'rep_grupo_etario') {
            tipo_rep = 'rep_grupo_etario';
            document.getElementById('crear_reporte').disabled = false;

        } else if ($("#vista").val() == 'rep_cons_peso') {

             $("#lote_unico").val() == '';

            $.post("../../controllers/mlotes_controller", {
                action: "get_lote_consecutivo_peso"
            }).done(function(data) {
                $("#lote_unico").html(data);

            });
            tipo_rep = 'rep_cons_peso';
            activar_filtros_lote();

        } else if ($("#vista").val() == 'rep_nacimientos') {
            tipo_rep = 'rep_nacimientos';
            activar_filtros_nacimientos();

        } else if ($("#vista").val() == 'rep_ficha_animal') {
            habilitar_cod();
        } else {
            document.getElementById('fecha_inicio').disabled = true;
            document.getElementById('fecha_final').disabled = true;
            document.getElementById('crear_reporte').disabled = true;
        }


    });


    $("#cancelar").click(function() {

        $('#modal3').modal('toggle');
        LimpiarCampos();
        desactivar_filtros();
        desactivar_label();

    });



    $('#fecha_inicio').datepicker({
        // startDate: '+0d',
        endDate: '+0d',
        todayBtn: false,
        todayHighlight: true,
        autoclose: true,
        language: 'es',
        showOnFocus: true

    });

    $('#fecha_final').datepicker({
        // startDate: '+0d',
        endDate: '+0d',
        todayBtn: false,
        todayHighlight: true,
        autoclose: true,
        language: 'es',
        showOnFocus: true

    });

    //// **********************************************



    //// PARA LA GESTION DE REPORTES


    $("#reportes").click(function() {
        $('#modal3').modal({
            backdrop: 'static',
            keyboard: false
        });


    });


    function LimpiarCampos() {


        document.getElementById("lote_unico").value = '';

        document.getElementById("fecha_inicio").value = '';
        document.getElementById("fecha_final").value = '';
        document.getElementById("cod_bovs_inicio").value = '';
        document.getElementById("cod_bovs_final").value = '';
        document.getElementById("id_cod_bovs").value = '';
        document.getElementById("vista").value = '';
        document.getElementById("nro_arete").value = '';


        document.getElementById('fecha_inicio').disabled = true;
        document.getElementById('fecha_final').disabled = true;
        document.getElementById('lote_unico').disabled = true;

        document.getElementById('cod_bovs_inicio').disabled = true;
        document.getElementById('cod_bovs_final').disabled = true;
        document.getElementById('id_cod_bovs').disabled = true;
        document.getElementById('crear_reporte').disabled = true;

        document.getElementById('cod_bovs').disabled = true;
        document.getElementById('nro_arete').disabled = true;


    }


    ///////////////////////////////////////////////////////


    function LimpiarLote() {

        document.getElementById('lote_unico').disabled = true;

    }

    ///////////////////////////////////////////////////////

    function LimpiarSexo() {

        document.getElementById("sexo_hembra").value = '';
        document.getElementById('sexo_hembra').disabled = true;

    }

    function LimpiarCod() {

        document.getElementById("cod_bovs_inicio").value = '';
        document.getElementById("cod_bovs_final").value = '';
        document.getElementById('cod_bovs_inicio').disabled = true;
        document.getElementById('cod_bovs_final').disabled = true;

    }

    function Limpiar_IdCod() {

        document.getElementById("id_cod_bovs").value = '';
        document.getElementById('id_cod_bovs').disabled = true;

    }

    function LimpiarFecha() {

        document.getElementById("fecha_inicio").value = '';
        document.getElementById("fecha_final").value = '';
        document.getElementById('fecha_inicio').disabled = true;
        document.getElementById('fecha_final').disabled = true;

    }


    $('#crear_reporte').click(function() {

        // filtrado
        var r_cod = $("#rango_id").val();
        var r_cod_unico = $("#cod_bovs").val();


        var r_cod_bovs = $("#cod_bovs").val();
        var codigo_bovs = $("#id_cod_bovs").val();

        var r_fecha = $("#rango_fecha").val();
        var r_lote = $("#rango_lote_unico").val();
        var r_sexo_h = $("#sexo_hembra").val();
        var r_sexo_m = $("#sexo_macho").val();


        var fecha_inicio = $('#fecha_inicio').val();
        var fecha_final = $('#fecha_final').val();
        var cod_bovs_inicio = $('#cod_bovs_inicio').val();
        var cod_bovs_final = $('#cod_bovs_final').val();

        var lote_unico = $('#lote_unico').val();

        var genera = false;
        var tipo = 0;
        var para_fecha = "";
        var para_id = "";
        var para_lote = "";
        var para_sexo = "";
        var para_cod_arete = "";


        var mensaje = 0;


        if ($("#vista").val() == 'rep_prod_leche') {
             mensaje = 0;

            if ((r_fecha == 1) || (r_cod == 1) || (r_lote == 1)) {
                if (r_fecha == 1) {
                    if ((fecha_inicio != "") && (fecha_final != "")) {
                        genera = true;
                        tipo = 1;
                        para_fecha = 'fecha_inicio=' + fecha_inicio + '&fecha_final=' + fecha_final;


                    } else {
                        mensaje = 1;
                    }

                }

                if (r_cod == 1) {
                    if ((cod_bovs_inicio != "") && (cod_bovs_final != "")) {
                        genera = true;
                        tipo = 1;
                        para_id = 'cod_bovs_inicio=' + cod_bovs_inicio + '&cod_bovs_final=' + cod_bovs_final;

                    } else {
                        mensaje = 2;
                    }

                }
                if (r_lote == 1) {
                    if ((lote_unico != "")) {
                        genera = true;
                        tipo = 1;
                        para_id = 'lote_unico=' + lote_unico;

                    } else {
                        mensaje = 3;
                    }

                }
            } else {
                mensaje = 40;

            }
            var parametros = "";
            if (para_fecha != "") {
                parametros = para_fecha;

                if (para_id != "") {
                    parametros = para_fecha + '&' + para_id;

                }
                if (para_lote != "") {
                    parametros = para_fecha + '&' + para_id + '&' + para_lote;

                }

            } else if (para_id != "") {
                parametros = para_id;
                if (para_fecha != "") {
                    parametros = para_fecha + '&' + para_id;
                }
                if (para_lote != "") {
                    parametros = para_fecha + '&' + para_id + '&' + para_lote;

                }

            } else if (para_lote != "") {
                parametros = para_lote;
                if (para_fecha != "") {
                    parametros = para_fecha + '&' + para_lote;
                }
                if (para_id != "") {
                    parametros = para_fecha + '&' + para_id + '&' + para_lote;

                }

            }

        } else if ($("#vista").val() == 'rep_ser_animal') {

            if ((r_fecha == 1) || (r_cod_bovs == 1) || (r_sexo_h == 1) || (r_sexo_m == 1)) {
                if (r_fecha == 1) {
                    if ((fecha_inicio != "") && (fecha_final != "")) {
                        genera = true;
                        tipo = 2;
                        para_fecha = 'fecha_inicio=' + fecha_inicio + '&fecha_final=' + fecha_final;


                    } else {
                        mensaje = 1;
                    }

                }

                if (r_cod_bovs == 1) {
                    if ((codigo_bovs != "")) {
                        genera = true;
                        tipo = 2;
                        para_id = 'cod_bovs=' + codigo_bovs;

                    } else {
                        mensaje = 2;
                    }

                }

                if ((r_sexo_h == 1) || (r_sexo_m == 1)) {
                    if ((r_sexo_h == 1)) {
                        genera = true;
                        tipo = 2;
                        para_sexo = 'filtrarXsexo=' + r_sexo_h;

                    } else if ((r_sexo_m == 1)) {
                        genera = true;
                        tipo = 2;
                        para_sexo = 'filtrarXsexo=' + r_sexo_m;

                    } else {
                        mensaje = 4;
                    }
                    if (buscar_cod > 0) {
                        genera = true;
                        tipo = 2;
                        para_cod_arete = 'codigo_erete=' + buscar_cod;
                    }

                }
            } else {
                mensaje = 40;

            }

            var parametros = "";
            if (para_fecha != "") {
                parametros = para_fecha;

                if (para_id != "") {
                    parametros = para_fecha + '&' + para_id;

                }
                if (para_sexo != "") {
                    parametros = para_fecha + '&' + para_id + '&' + para_sexo;

                }
                if (buscar_cod > 0) {
                    parametros = para_fecha + '&' + para_id + '&' + para_sexo + '&' + para_cod_arete;
                }

            } else if (para_id != "") {
                parametros = para_id;
                if (para_fecha != "") {
                    parametros = para_fecha + '&' + para_id;
                }
                if (para_sexo != "") {
                    parametros = para_fecha + '&' + para_id + '&' + para_sexo;

                    if (buscar_cod > 0) {
                        parametros = para_fecha + '&' + para_id + '&' + para_sexo + '&' + para_cod_arete;
                    }

                }


            }

        } else if ($("#vista").val() == 'rep_ctl_animal') {
            tipo = 3;

            if ((r_fecha == 1) || (r_cod_bovs == 1)) {
                if (r_fecha == 1) {
                    if ((fecha_inicio != "") && (fecha_final != "")) {
                        genera = true;
                        para_fecha = 'fecha_inicio=' + fecha_inicio + '&fecha_final=' + fecha_final;


                    } else {
                        mensaje = 1;
                    }

                }

                if (buscar_cod > 0) {


                    if ((codigo_bovs != "")) {
                        genera = true;
                        para_id = 'cod_bovs=' + codigo_bovs;
                        para_cod_arete = 'codigo_erete=' + buscar_cod;


                    }


                } else {
                    mensaje = 40;

                }

                var parametros = "";
                if (para_fecha != "") {
                    parametros = para_fecha;


                    if ((codigo_bovs != "")) {
                        parametros = para_fecha + '&' + para_id + '&' + para_cod_arete;
                    }

                } else if (para_id != "") {
                    parametros = para_id;
                    if ((codigo_bovs != "")) {
                        parametros = para_id + '&' + para_cod_arete;
                    }



                }
            }

        } else if ($("#vista").val() == 'rep_nacimientos') {
            genera = true;
            tipo = 6;
            var filtros_nacimiento = [];
            var tiene_fecha_nacimiento = (fecha_inicio != "") || (fecha_final != "");

            if (tiene_fecha_nacimiento) {
                if ((fecha_inicio != "") && (fecha_final != "")) {
                    filtros_nacimiento.push('fecha_inicio=' + encodeURIComponent(fecha_inicio));
                    filtros_nacimiento.push('fecha_hasta=' + encodeURIComponent(fecha_final));
                } else {
                    mensaje = 1;
                    genera = false;
                }
            }

            if (r_cod_bovs == 1) {
                if ((codigo_bovs != "") && (buscar_cod > 0)) {
                    filtros_nacimiento.push('cod_bovs=' + encodeURIComponent(codigo_bovs));
                    filtros_nacimiento.push('codigo_erete=' + buscar_cod);
                } else {
                    mensaje = 5;
                    genera = false;
                }
            }

            parametros = filtros_nacimiento.join('&');

        } else if ($("#vista").val() == 'rep_gen_sol') {
            genera = true;
            tipo = 3;
        } else if ($("#vista").val() == 'rep_grupo_etario') {
            genera = true;
            tipo = 4;
        } else if ($("#vista").val() == 'rep_cons_peso') {
                mensaje = 0;
            if ((r_fecha == 1) || (r_cod_unico == 1) || (r_lote == 1)) {
                if (r_fecha == 1) {
                    if ((fecha_inicio != "") && (fecha_final != "")) {
                        genera = true;
                        tipo = 5;
                        para_fecha = 'fecha_inicio=' + fecha_inicio + '&fecha_final=' + fecha_final;


                    } else {
                        mensaje = 1;
                    }

                }

                if (r_cod_unico == 1) {
                    if (codigo_bovs != "") {
                        genera = true;
                        tipo = 5;
                        para_id = 'codigo_bovs=' + codigo_bovs;

                    } else {
                        mensaje = 2;
                    }

                }
                if (r_lote == 1) {
                    if ((lote_unico != "")) {
                        genera = true;
                        tipo = 5;
                        para_id = 'lote_unico=' + lote_unico;

                    } else {
                        mensaje = 3;
                    }

                }
            } else {
                mensaje = 40;

            }
            var parametros = "";
            if (para_fecha != "") {
                parametros = para_fecha;

                if (para_id != "") {
                    parametros = para_fecha + '&' + para_id;

                }
                if (para_lote != "") {
                    parametros = para_fecha + '&' + para_id + '&' + para_lote;

                }

            } else if (para_id != "") {
                parametros = para_id;
                if (para_fecha != "") {
                    parametros = para_fecha + '&' + para_id;
                }
                if (para_lote != "") {
                    parametros = para_fecha + '&' + para_id + '&' + para_lote;

                }

            } else if (para_lote != "") {
                parametros = para_lote;
                if (para_fecha != "") {
                    parametros = para_fecha + '&' + para_lote;
                }
                if (para_id != "") {
                    parametros = para_fecha + '&' + para_id + '&' + para_lote;

                }

            }

        }


        if (mensaje == 1) {
            $.alert({
                title: '!Debe Seleccionar todos los Parámetros!',
                content: false,
                confirmButton: true, // hides the confirm button.
                closeIcon: false,
                confirmButton: 'cerrar',
                confirmButtonClass: 'btn-success'
            });
        } else if (mensaje == 2) {
            $.alert({
                title: '!Debe Seleccionar el Rango de Fechas !',
                content: false,
                confirmButton: true, // hides the confirm button.
                closeIcon: false,
                confirmButton: 'cerrar',
                confirmButtonClass: 'btn-success'
            });
        } else if (mensaje == 3) {
            $.alert({
                title: '!Debe Seleccionar el Lotes !',
                content: false,
                confirmButton: true, // hides the confirm button.
                closeIcon: false,
                confirmButton: 'cerrar',
                confirmButtonClass: 'btn-success'
            });
        } else if (mensaje == 4) {
            $.alert({
                title: '!Debe Seleccionar el Sexo del Animal !',
                content: false,
                confirmButton: true, // hides the confirm button.
                closeIcon: false,
                confirmButton: 'cerrar',
                confirmButtonClass: 'btn-success'
            });
        } else if (mensaje == 5) {
            $.alert({
                title: '!Debe Indicar el Código SOVCA o Nro. de Arete de la Madre!',
                content: false,
                confirmButton: true,
                closeIcon: false,
                confirmButton: 'cerrar',
                confirmButtonClass: 'btn-success'
            });
        } else {

            var parametros_2 = window.btoa(parametros);

            if (genera) {
                if (tipo == 1) {
                    window.open("../../Reportes/reportes/sovca/rpl.php?%$%=" + parametros_2, '',
                        "titlebars=0, toolbar=0,scrollbars=0,location=0,statusbar=0,menubar=0,resizable=0,width=450,height=550,top=150,left=500"
                    );
                } else if (tipo == 2) {
                    window.open("../../Reportes/reportes/sovca/rsa.php?%$%=" + parametros_2, '',
                        "titlebars=0, toolbar=0,scrollbars=0,location=0,statusbar=0,menubar=0,resizable=0,width=450,height=550,top=150,left=500"
                    );
                } else if (tipo == 3) {
                    window.open("../../Reportes/reportes/sovca/rca.php?%$%=" + parametros_2, '',

                        "titlebars=0, toolbar=0,scrollbars=0,location=0,statusbar=0,menubar=0,resizable=0,width=450,height=550,top=150,left=500"
                    );
                } else if (tipo == 4) {
                    window.open("../../Reportes/reportes/sovca/rge.php?", '',
                        "titlebars=0, toolbar=0,scrollbars=0,location=0,statusbar=0,menubar=0,resizable=0,width=450,height=550,top=150,left=500"
                    );
                } else if (tipo == 5) {
                    window.open("../../Reportes/reportes/sovca/rcp.php?%$%=" + parametros_2, '',
                        "titlebars=0, toolbar=0,scrollbars=0,location=0,statusbar=0,menubar=0,resizable=0,width=450,height=550,top=150,left=500"
                    );
                } else if (tipo == 6) {
                    window.open("../../Reportes/reportes/sovca/rrn.php?%$%=" + parametros_2, '',
                        "titlebars=0, toolbar=0,scrollbars=0,location=0,statusbar=0,menubar=0,resizable=0,width=950,height=650,top=100,left=250"
                    );
                }
                LimpiarCampos();
                desactivar_label();
                desactivar_filtros();

                $('#modal3').modal('toggle');
            }



        }


    });




    function desactivar_filtros() {
        $('#rango_fecha').hide();
        $('#rango_id').hide();
        $('#rango_lote_unico').hide();
        $('#cod_bovs').hide();

        $('label[for="rango_fecha"]').hide();
        $('label[for="rango_id"]').hide();
        $('label[for="rango_lote_unico"]').hide();

        $('label[for="cod_bovs"]').hide();




    }

    function desactivar_todo() {
        desactivar_filtros();
        desactivar_label();
        desactivar_filtros();
        $("#rango_fecha").val(0);
        $("#rango_id").val(0);
        $("#rango_lote_unico").val(0);
        $("#sexo_hembra").val(0);
        $("#sexo_macho").val(0);
        $("#cod_bovs").val(0);
        $("#codigo_bovs").val(0);
        $("#nro_arete").val(0);
        document.getElementById('rango_fecha').checked = false;
        document.getElementById('rango_id').checked = false;
        document.getElementById('rango_lote_unico').checked = false;
        document.getElementById('sexo_hembra').checked = false;
        document.getElementById('sexo_macho').checked = false;
        document.getElementById('cod_bovs').checked = false;
        document.getElementById('codigo_bovs').checked = false;
        document.getElementById('nro_arete').checked = false;
        document.getElementById('crear_reporte').disabled = true;

    }

    function activar_filtros_lote() {

        $('#rango_fecha').show();
        $('#rango_lote_unico').show();

        $('#cod_bovs').show();
        $('label[for="cod_bovs"]').show();

        $('label[for="rango_fecha"]').show();
        $('label[for="rango_lote_unico"]').show();
        $('label[for="parametros"]').show();
        document.getElementById('cod_bovs').disabled = false;
        document.getElementById('rango_fecha').disabled = false;
        document.getElementById('lote_unico').disabled = false;


    }

    function activar_filtros_servicio() {


        $('#sexo_hembra').show();

        $('#cod_bovs').show();
        $('label[for="cod_bovs"]').show();
        $('label[for="parametros"]').show();
        $('label[for="sexo_hembra"]').show();

        document.getElementById('sexo_hembra').disabled = false;
        document.getElementById('sexo_macho').disabled = false;
        document.getElementById('cod_bovs').disabled = true;
        document.getElementById('rango_fecha').disabled = true;

    }

    function activar_filtros_ctl_animal() {


        $('#rango_fecha').show();
        $('#cod_bovs').show();

        $('label[for="rango_fecha"]').show();
        $('label[for="cod_bovs"]').show();
        $('label[for="parametros"]').show();

        document.getElementById('cod_bovs').disabled = false;
        document.getElementById('rango_fecha').disabled = false;
        document.getElementById('crear_reporte').disabled = true;


    }

    function activar_filtros_nacimientos() {
        $('#rango_fecha').show();
        $('#cod_bovs').show();
        $('label[for="rango_fecha"]').show();
        $('label[for="cod_bovs"]').show();
        $('label[for="parametros"]').show();

        document.getElementById('rango_fecha').disabled = false;
        document.getElementById('cod_bovs').disabled = false;
        document.getElementById('crear_reporte').disabled = false;
    }

    function activar_filtro_sexo() {

        document.getElementById('sexo_hembra').disabled = false;
        document.getElementById('sexo_macho').disabled = false;
        document.getElementById('id_cod_bovs').disabled = true;
        document.getElementById('rango_fecha').disabled = true;

    }



    function desactivar_label() {
        $('#cod_bovs_inicio').hide();
        $('#cod_bovs_final').hide();

        $('label[for="ID.SOVCA.Desde"]').hide();
        $('label[for="ID.SOVCA.hasta"]').hide();

        $('#fecha_inicio').hide();
        $('#fecha_final').hide();

        $('label[for="fecha_inicio"]').hide();
        $('label[for="fecha_final"]').hide();


        $('#lote_unico').hide();

        $('label[for="lote_unico"]').hide();


        $('#sexo_hembra').hide();
        $('label[for="sexo_hembra"]').hide();

        $('#sexo_macho').hide();
        $('label[for="sexo_macho"]').hide();

        $('#id_cod_bovs').hide();
        $('label[for="id_cod_bovs"]').hide();

        $('#cod_bovs').hide();
        $('label[for="cod_bovs"]').hide();


        $('#nro_arete').hide();
        $('label[for="nro_arete"]').hide();

        $('#codigo_bovs').hide();
        $('label[for="codigo_bovs"]').hide();

        $('#valida_cod').hide();


        $('#parametros').hide();
        $('label[for="parametros"]').hide();


    }

    function activar_fecha() {

        $('#fecha_inicio').show();
        $('#fecha_final').show();

        $('label[for="fecha_inicio"]').show();
        $('label[for="fecha_final"]').show();
    }

    function desactivar_fecha() {

        $('#fecha_inicio').hide();
        $('#fecha_final').hide();

        $('label[for="fecha_inicio"]').hide();
        $('label[for="fecha_final"]').hide();
    }

    /////////////////////////////////////////////////////////

    function activar_lote() {

        $('#lote_unico').show();
        $('label[for="lote_unico"]').show();
    }

    function desactivar_lote() {

        $('#lote_unico').hide();
        $('label[for="lote_unico"]').hide();

    }


    ////////////////////////////////////////////////////////

    function activar_cod() {
        $('#cod_bovs_inicio').show();
        $('#cod_bovs_final').show();

        $('label[for="ID.SOVCA.Desde"]').show();
        $('label[for="ID.SOVCA.hasta"]').show();

    }

    function activar_id_cod() {
        $('#id_cod_bovs').show();
        $('label[for="id_cod_bovs"]').show();

        $('#nro_arete').show();
        $('label[for="nro_arete"]').show();

        $('#codigo_bovs').show();
        $('label[for="codigo_bovs"]').show();

    }

    function desactivar_id_cod() {
        $('#id_cod_bovs').hide();
        $('label[for="id_cod_bovs"]').hide();

        $('#nro_arete').hide();
        $('label[for="nro_arete"]').hide();

        $('#codigo_bovs').hide();
        $('label[for="codigo_bovs"]').hide();

    }

    function desactivar_cod() {
        $('#cod_bovs_inicio').hide();
        $('#cod_bovs_final').hide();

        $('label[for="ID.SOVCA.Desde"]').hide();
        $('label[for="ID.SOVCA.hasta"]').hide();

    }
    $("#cod_bovs").click(function() {

       $("#rango_lote_unico").val(0);

        activar_id_cod();
        Limpiar_IdCod();
        desactivar_lote();
        document.getElementById('rango_lote_unico').checked = false;

        document.getElementById('codigo_bovs').checked = false;
        document.getElementById("id_cod_bovs").value = '';
        document.getElementById('nro_arete').disabled = false;

        document.getElementById('nro_arete').checked = false;
        document.getElementById("nro_arete").value = '';
        $('#valida_cod').hide();


        if ($('#cod_bovs').prop('checked') == true) {
            $("#cod_bovs").val(1);
            if (tipo_rep == 'rep_nacimientos') {
                document.getElementById('crear_reporte').disabled = true;
            }


            /////////////
        } else {
            desactivar_id_cod();
            Limpiar_IdCod();
            $("#cod_bovs").val(0);
            document.getElementById('cod_bovs').checked = false;

        }

    });


    $("#nro_arete").click(function() {

        // habilitar_id_cod();

        document.getElementById("id_cod_bovs").value = '';
        document.getElementById("id_cod_bovs").disabled = false;



        if ($('#nro_arete').prop('checked') == true) {
            $("#nro_arete").val(1);
            $("#codigo_bovs").val(0);
            $('#valida_cod').show();
            document.getElementById('codigo_bovs').checked = false;
            document.getElementById('nro_arete').checked = true;
            buscar_cod = 2;
        } else {
            $("#nro_arete").val(0);
            $("#codigo_bovs").val(0);
            $('#valida_cod').hide();
            document.getElementById("id_cod_bovs").disabled = true;
            buscar_cod = 0;

        }

    });

    $("#codigo_bovs").click(function() {

        document.getElementById("id_cod_bovs").disabled = false;
        document.getElementById("crear_reporte").disabled = true;



        document.getElementById("id_cod_bovs").value = '';


        if ($('#codigo_bovs').prop('checked') == true) {
            habilitar_id_cod();
            $('#valida_cod').show();
            $("#codigo_bovs").val(1);
            $("#nro_arete").val(0);
            document.getElementById('codigo_bovs').checked = true;
            document.getElementById('nro_arete').checked = false;
            buscar_cod = 1;
        } else {
            $("#nro_arete").val(0);
            $("#codigo_bovs").val(0);
            $('#valida_cod').hide();
            document.getElementById("id_cod_bovs").disabled = true;
            buscar_cod = 0;

        }

    });

    $("#id_cod_bovs").on('input', function() {
        if (tipo_rep == 'rep_nacimientos' && $('#cod_bovs').prop('checked')) {
            document.getElementById('crear_reporte').disabled = true;
        }
    });

    ///////////////////////////////////////////////////////

    $("#rango_lote_unico").click(function() {
        activar_lote();
        $("#cod_bovs").val(0);

        if ($('#rango_lote_unico').prop('checked') == true) {
            $("#rango_lote_unico").val(1);
            $("#rango_id").val(0);


            habilitar_lote();
            desactivar_id_cod();
            document.getElementById('cod_bovs').checked = false;
            document.getElementById('rango_id').checked = false;


            /////////////
        } else {
            desactivar_lote();
            LimpiarLote();
            $("#rango_lote_unico").val(0);
            document.getElementById('rango_lote_unico').checked = false;
            if ((document.getElementById('rango_fecha').checked == false) && (document.getElementById('rango_id').checked == false)) {
                document.getElementById('crear_reporte').disabled = true;

            }
        }

    });

    $("#rango_fecha").click(function() {
        activar_fecha();

        if ($('#rango_fecha').prop('checked') == true) {
            $("#rango_fecha").val(1);
            document.getElementById('rango_fecha').checked = true;


            document.getElementById('fecha_inicio').disabled = false;
            document.getElementById('fecha_final').disabled = false;
            document.getElementById('crear_reporte').disabled = false;

        } else {
            desactivar_fecha();
            LimpiarFecha();
            $("#rango_fecha").val(0);
            document.getElementById('rango_fecha').checked = false;
            if ((document.getElementById('rango_id').checked == false) && (document.getElementById('rango_lote_unico')
                    .checked == false)) {
                document.getElementById('crear_reporte').disabled = true;

            }


        }

    });
    $("#rango_id").click(function() {
        activar_cod();
        if ($('#rango_id').prop('checked') == true) {
            $("#rango_id").val(1);
            $("#rango_lote_unico").val(0);

            habilitar_cod();
            document.getElementById('rango_lote_unico').checked = false;
            desactivar_lote_unico();


            /////////////
        } else {
            desactivar_cod();
            LimpiarCod();
            $("#rango_id").val(0);
            document.getElementById('rango_id').checked = false;
            if ((document.getElementById('rango_fecha').checked == false) && (document.getElementById(
                        'rango_lote_unico')
                    .checked == false)) {
                document.getElementById('crear_reporte').disabled = true;

            }
        }

    });






    ///////////////////////////////////////////////////////

    $("#lote_unico").click(function() {
        document.getElementById('crear_reporte').disabled = false;

    });
    ///////////////////////////////////////////////////////

    $("#sexo_hembra").click(function() {

        limpiar_sexo();
        Limpiar_IdCod();
        document.getElementById('sexo_macho').checked = false;


        if ($('#sexo_hembra').prop('checked') == true) {
            $("#sexo_hembra").val(1);
            document.getElementById('sexo_hembra').checked = true;
            document.getElementById('crear_reporte').disabled = false;


            /////////////
        } else {
            activar_filtro_sexo();
            $("#sexo_hembra").val(0);
            document.getElementById('sexo_hembra').checked = false;
            if ((document.getElementById('rango_fecha').checked == false) && (document.getElementById(
                        'id_cod_bovs')
                    .checked == false)) {
                document.getElementById('crear_reporte').disabled = true;

            }
        }

    });
    ////////////////////////////////////////////////////

    $("#sexo_macho").click(function() {
        limpiar_sexo();
        Limpiar_IdCod();
        document.getElementById('sexo_hembra').checked = false;

        if ($('#sexo_macho').prop('checked') == true) {
            $("#sexo_macho").val(1);
            document.getElementById('sexo_macho').checked = true;
            document.getElementById('crear_reporte').disabled = false;

            /////////////
        } else {
            activar_filtro_sexo();
            $("#sexo_macho").val(0);
            document.getElementById('sexo_macho').checked = false;
            if ((document.getElementById('rango_fecha').checked == false) && (document.getElementById(
                        'rango_id')
                    .checked == false)) {
                document.getElementById('crear_reporte').disabled = true;

            }
        }

    });

    ////////////////////////////////////////////////////
    function limpiar_sexo() {
        document.getElementById('rango_fecha').disabled = false;
        document.getElementById('rango_fecha').checked = false;
        document.getElementById('cod_bovs').disabled = false;

        desactivar_fecha();
        LimpiarFecha();
        desactivar_id_cod();
        Limpiar_IdCod();
    }


    function habilitar_cod() {
        var id_fundo = '0' + fundo + '-0';
        activar_cod();

        document.getElementById('cod_bovs_inicio').disabled = false;
        document.getElementById('cod_bovs_final').disabled = false;
        document.getElementById('crear_reporte').disabled = false;
        document.getElementById("cod_bovs_inicio").value = 'SOVCA-' + id_fundo;
        document.getElementById("cod_bovs_final").value = 'SOVCA-' + id_fundo;

    }

    function habilitar_id_cod() {
        var id_fundo = '0' + fundo + '-0';
        activar_id_cod();

        document.getElementById('id_cod_bovs').disabled = false;
        document.getElementById("id_cod_bovs").value = 'SOVCA-' + id_fundo;

    }

    function habilitar_lote() {
        activar_lote();

        document.getElementById('lote_unico').disabled = false;

    }



    ///////////////////////////////////////////////////
    // Nuevas FUnciones 08-03-2023

    $("#valida_cod").click(function() {
        if (tipo_rep == 'rep_ctl_animal') {
            valida_cod_rep_ctl_sanit();
            document.getElementById('crear_reporte').disabled = true;



        } else if (tipo_rep == 'rep_ser_animal') {
            valida_cod_rep_servicio();

        } else if (tipo_rep == 'rep_prod_leche') {
            valida_cod_rep_prod_leche();
        } else if (tipo_rep == 'rep_cons_peso') {
            valida_cod_rep_consc_peso();
        } else if (tipo_rep == 'rep_nacimientos') {
            valida_cod_rep_nacimientos();
        }



    });

    function valida_cod_rep_ctl_sanit() {

        var valor_cod = $("#id_cod_bovs").val();

        if (valor_cod != '') {

            $.post("../../controllers/mcontrolsanitario_controller", {

                action: "search_crl_animal",
                rgb_animal: valor_cod,
                buscar_cod: buscar_cod


            }).done(function(data) {

                var parsedJson = $.parseJSON(data);
                var cod_animal = parsedJson.cod_animal;
                if (cod_animal != 0) {
                    document.getElementById('ms_id_cod_bovs_acep').style.display = 'block';
                    document.getElementById("ms_id_cod_bovs_acep").innerHTML = 'Registro Encontrado';
                    document.getElementById('crear_reporte').disabled = false;


                    setTimeout(function() {

                        document.getElementById("ms_id_cod_bovs_acep").innerHTML = '';

                    }, 2000);

                } else {
                    document.getElementById('ms_id_cod_bovs').style.display = 'block';
                    document.getElementById("ms_id_cod_bovs").innerHTML = 'Registro NO Encontrado';
                    document.getElementById('crear_reporte').disabled = true;


                    setTimeout(function() {

                        document.getElementById("ms_id_cod_bovs").innerHTML = '';

                    }, 2000);

                }

            }, "json");

        } else {

            document.getElementById('ms_id_cod_bovs').style.display = 'block';
            document.getElementById("ms_id_cod_bovs").innerHTML =
                'Campo Vacio';

            setTimeout(function() {

                document.getElementById("ms_id_cod_bovs").innerHTML = '';

            }, 2000);

        }


    }

    function valida_cod_rep_servicio() {

        var valor_cod = $("#id_cod_bovs").val();

        if (valor_cod != '') {

            $.post("../../controllers/mservicios_controller", {

                action: "search_reg_animal_servicio_h",
                rgb_animal: valor_cod,
                buscar_cod: buscar_cod


            }).done(function(data) {

                var parsedJson = $.parseJSON(data);
                var cod_animal = parsedJson.cod_animal;
                if (cod_animal != 0) {
                    document.getElementById('ms_id_cod_bovs_acep').style.display = 'block';
                    document.getElementById("ms_id_cod_bovs_acep").innerHTML =
                        'Registro Encontrado';
                    document.getElementById('crear_reporte').disabled = false;


                    setTimeout(function() {

                        document.getElementById("ms_id_cod_bovs_acep").innerHTML = '';

                    }, 2000);

                } else {
                    document.getElementById('ms_id_cod_bovs').style.display = 'block';
                    document.getElementById("ms_id_cod_bovs").innerHTML =
                        'Registro NO Encontrado';
                    document.getElementById('crear_reporte').disabled = false;


                    setTimeout(function() {

                        document.getElementById("ms_id_cod_bovs").innerHTML = '';

                    }, 2000);

                }

            }, "json");

        } else {

            document.getElementById('ms_id_cod_bovs').style.display = 'block';
            document.getElementById("ms_id_cod_bovs").innerHTML =
                'Campo Vacio';

            setTimeout(function() {

                document.getElementById("ms_id_cod_bovs").innerHTML = '';

            }, 2000);

        }


    }

    function valida_cod_rep_nacimientos() {
        var valor_cod = $("#id_cod_bovs").val();

        document.getElementById('crear_reporte').disabled = true;
        if (valor_cod == '' || buscar_cod < 1) {
            document.getElementById('ms_id_cod_bovs').style.display = 'block';
            document.getElementById('ms_id_cod_bovs').innerHTML = 'Seleccione Código SOVCA o Nro. Arete e ingrese un valor';
            return;
        }

        $.post("../../controllers/mnacimientos_controller", {
            action: 'search_rep_nacimientos_madre',
            rgb_animal: valor_cod,
            buscar_cod: buscar_cod
        }).done(function(data) {
            var parsedJson = $.parseJSON(data);
            if (parsedJson.cod_animal != 0) {
                document.getElementById('ms_id_cod_bovs_acep').style.display = 'block';
                document.getElementById('ms_id_cod_bovs_acep').innerHTML = 'Hembra con nacimientos encontrada';
                document.getElementById('crear_reporte').disabled = false;
                setTimeout(function() { document.getElementById('ms_id_cod_bovs_acep').innerHTML = ''; }, 2000);
            } else {
                document.getElementById('ms_id_cod_bovs').style.display = 'block';
                document.getElementById('ms_id_cod_bovs').innerHTML = 'No existe una hembra con nacimientos para ese valor';
                setTimeout(function() { document.getElementById('ms_id_cod_bovs').innerHTML = ''; }, 2000);
            }
        }).fail(function() {
            document.getElementById('ms_id_cod_bovs').style.display = 'block';
            document.getElementById('ms_id_cod_bovs').innerHTML = 'No se pudo validar el código';
        });
    }

    /////////////////////////////////////////////////////////////////////

    function valida_cod_rep_prod_leche() {

        var valor_cod = $("#id_cod_bovs").val();

        if (valor_cod != '') {

            $.post("../../controllers/mprodleche_controller", {

                action: "search_rep_prod_leche",
                rgb_animal: valor_cod,
                buscar_cod: buscar_cod


            }).done(function(data) {

                var parsedJson = $.parseJSON(data);
                var cod_animal = parsedJson.cod_animal;
                if (cod_animal != 0) {
                    document.getElementById('ms_id_cod_bovs_acep').style.display = 'block';
                    document.getElementById("ms_id_cod_bovs_acep").innerHTML =
                        'Registro Encontrado';
                    document.getElementById('crear_reporte').disabled = false;


                    setTimeout(function() {

                        document.getElementById("ms_id_cod_bovs_acep").innerHTML = '';

                    }, 2000);

                } else {
                    document.getElementById('ms_id_cod_bovs').style.display = 'block';
                    document.getElementById("ms_id_cod_bovs").innerHTML =
                        'Registro NO Encontrado';
                    document.getElementById('crear_reporte').disabled = true;


                    setTimeout(function() {

                        document.getElementById("ms_id_cod_bovs").innerHTML = '';

                    }, 2000);

                }

            }, "json");

        } else {

            document.getElementById('ms_id_cod_bovs').style.display = 'block';
            document.getElementById("ms_id_cod_bovs").innerHTML =
                'Campo Vacio';

            setTimeout(function() {

                document.getElementById("ms_id_cod_bovs").innerHTML = '';

            }, 2000);

        }


    }


    ////////////////////////////////////////////////////////////////////

    function valida_cod_rep_consc_peso() {

        var valor_cod = $("#id_cod_bovs").val();

        if (valor_cod != '') {

            $.post("../../controllers/mconsecutivopeso_controller", {

                action: "search_rep_consc_peso",
                rgb_animal: valor_cod,
                buscar_cod: buscar_cod


            }).done(function(data) {

                var parsedJson = $.parseJSON(data);
                var cod_animal = parsedJson.cod_animal;
                if (cod_animal != 0) {
                    document.getElementById('ms_id_cod_bovs_acep').style.display = 'block';
                    document.getElementById("ms_id_cod_bovs_acep").innerHTML =
                        'Registro Encontrado';
                    document.getElementById('crear_reporte').disabled = false;


                    setTimeout(function() {

                        document.getElementById("ms_id_cod_bovs_acep").innerHTML = '';

                    }, 2000);

                } else {
                    document.getElementById('ms_id_cod_bovs').style.display = 'block';
                    document.getElementById("ms_id_cod_bovs").innerHTML =
                        'Registro NO Encontrado';
                    document.getElementById('crear_reporte').disabled = true;


                    setTimeout(function() {

                        document.getElementById("ms_id_cod_bovs").innerHTML = '';

                    }, 2000);

                }

            }, "json");

        } else {

            document.getElementById('ms_id_cod_bovs').style.display = 'block';
            document.getElementById("ms_id_cod_bovs").innerHTML =
                'Campo Vacio';

            setTimeout(function() {

                document.getElementById("ms_id_cod_bovs").innerHTML = '';

            }, 2000);

        }


    }
    $(document).ready(function() {
        $('#modal3').modal({
            backdrop: 'static',
            keyboard: false
        });
    });
</script>

<?php include_once("../layouts/pie.php") ?>