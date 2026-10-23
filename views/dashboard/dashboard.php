<?php
include("../../lib/validar_session.php");
ValidaSession("../login");
$mostrar_resumen_suscripciones = in_array((int)$_SESSION['rolx'], array(1, 4), true);
?>
<?php include("../layouts/constantes.php") ?>
<?php include_once("../layouts/cabecera.php") ?>

<div class="message"></div>

<!-- Encabezado -->
<div class="row" style="margin-bottom: 10px;">
    <div class="col-md-8">
        <h3 style="margin: 5px 0 0 0; font-weight: 600; color: #2C3440;">
            Tablero de Control
            <small style="color: #6B7480; font-weight: 400; font-size: 14px;">— Resumen general del fundo</small>
        </h3>
    </div>
    <div class="col-md-4 text-right">
        <button id="btn_gestor_reportes" type="button" class="btn btn-primary" style="margin-top: 5px;">
            <i class="fa fa-fw fa-file-text-o"></i> Gestor de Reportes
        </button>
    </div>
</div>

<?php if ($mostrar_resumen_suscripciones): ?>
<style>
.suscripcion-dashboard { display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; margin:0 0 16px; padding:12px 16px; border:1px solid #ead8b9; border-left:4px solid #e89b27; background:#fffdf8; }
.suscripcion-dashboard-titulo { color:#493c25; font-size:13px; font-weight:700; }
.suscripcion-dashboard-metricas { display:flex; gap:18px; flex-wrap:wrap; }
.suscripcion-dashboard-metrica { color:#647178; font-size:12px; }
.suscripcion-dashboard-metrica strong { color:#263b32; font-size:16px; margin-right:4px; }
@media(max-width:767px) { .suscripcion-dashboard { align-items:flex-start; flex-direction:column; } }
</style>
<div class="suscripcion-dashboard" id="suscripcion_dashboard_admin" aria-live="polite">
    <div><div class="suscripcion-dashboard-titulo"><i class="fa fa-clock-o"></i> Vigencias de suscripción</div><small id="suscripcion_dashboard_estado" class="text-muted">Consultando cuentas...</small></div>
    <div class="suscripcion-dashboard-metricas">
        <span class="suscripcion-dashboard-metrica"><strong id="suscripcion_dashboard_vencidas">—</strong>vencidas</span>
        <span class="suscripcion-dashboard-metrica"><strong id="suscripcion_dashboard_proximas">—</strong>vencen en 30 días</span>
        <span class="suscripcion-dashboard-metrica"><strong id="suscripcion_dashboard_sin_vigencia">—</strong>sin vigencia</span>
    </div>
    <a href="../usuarios/" class="btn btn-warning btn-sm"><i class="fa fa-users"></i> Revisar usuarios</a>
</div>
<script>
$(function() {
    $.getJSON('../../data_json/data_musuarios').done(function(usuarios) {
        var productores = new Map();
        (usuarios || []).forEach(function(usuario) {
            if (Number(usuario.rol_id) === 2 && Number(usuario.status) === 1) productores.set(Number(usuario.id), usuario);
        });
        var vencidas = 0;
        var proximas = 0;
        var sinVigencia = 0;
        productores.forEach(function(usuario) {
            if (!usuario.tiene_suscripcion) sinVigencia++;
            else if (Number(usuario.dias_restantes_suscripcion) < 0) vencidas++;
            else if (Number(usuario.dias_restantes_suscripcion) <= 30) proximas++;
        });
        $('#suscripcion_dashboard_vencidas').text(vencidas);
        $('#suscripcion_dashboard_proximas').text(proximas);
        $('#suscripcion_dashboard_sin_vigencia').text(sinVigencia);
        $('#suscripcion_dashboard_estado').text('Productores activos: ' + productores.size);
    }).fail(function() {
        $('#suscripcion_dashboard_estado').text('No se pudieron consultar las vigencias.');
    });
});
</script>
<?php endif; ?>

<!-- KPIs -->
<div class="row" id="kpi_row">
    <div class="col-md-1 hidden-sm hidden-xs"></div>
    <div class="col-md-2 col-xs-6">
        <div class="small-box bg-aqua kpi-card" data-target="animales_act" style="cursor: pointer;">
            <div class="inner">
                <h4 style="font-size: 26px; font-weight: 700;" id="animales_activos">—</h4>
                <p style="font-size: 12px;">TOTAL ANIMALES</p>
            </div>
            <div class="icon"><i class="ion-pie-graph"></i></div>
            <a href="#" id="animales_act" class="small-box-footer" style="cursor: pointer;">Ver activos <i class="fa fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <div class="col-md-2 col-xs-6">
        <div class="small-box bg-fuchsia kpi-card" data-target="animales_gestantes_act" style="cursor: pointer;">
            <div class="inner">
                <h4 style="font-size: 26px; font-weight: 700;" id="total_gestantes">—</h4>
                <p style="font-size: 12px;">GESTANTES</p>
            </div>
            <div class="icon"><i class="ion-pie-graph"></i></div>
            <a href="#" id="animales_gestantes_act" class="small-box-footer" style="cursor: pointer;">Ver activas <i class="fa fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <div class="col-md-2 col-xs-6">
        <div class="small-box bg-orange kpi-card" data-target="animales_servidas_act" style="cursor: pointer;">
            <div class="inner">
                <h4 style="font-size: 26px; font-weight: 700;" id="total_servidas">—</h4>
                <p style="font-size: 12px;">SERVIDAS</p>
            </div>
            <div class="icon"><i class="ion-pie-graph"></i></div>
            <a href="#" id="animales_servidas_act" class="small-box-footer" style="cursor: pointer;">Ver activas <i class="fa fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <div class="col-md-2 col-xs-6">
        <div class="small-box bg-purple kpi-card" data-target="animales_prodleche_act" style="cursor: pointer;">
            <div class="inner">
                <h4 style="font-size: 26px; font-weight: 700;" id="prod_leche">—</h4>
                <p style="font-size: 12px;">APTITUD LECHE</p>
            </div>
            <div class="icon"><i class="ion-pie-graph"></i></div>
            <a href="#" id="animales_prodleche_act" class="small-box-footer" style="cursor: pointer;">Ver activas <i class="fa fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <div class="col-md-2 col-xs-6">
        <div class="small-box bg-green" style="cursor: default;">
            <div class="inner">
                <h4 style="font-size: 26px; font-weight: 700;" id="prod_carne">—</h4>
                <p style="font-size: 12px;">APTITUD CARNE</p>
            </div>
            <div class="icon"><i class="ion-pie-graph"></i></div>
            <a href="#" class="small-box-footer" style="cursor: default;">Registrados <i class="fa fa-check"></i></a>
        </div>
    </div>
    <div class="col-md-1 hidden-sm hidden-xs"></div>
</div>

<!-- BLOQUE 1 — ESTADO ACTUAL -->
<div class="row" id="section_estado_header">
    <div class="col-md-12" style="margin-bottom: 5px;">
        <h4 style="margin: 5px 0 0 0; color: #6B7480; font-weight: 500; font-size: 13px; letter-spacing: 1px;">
            ESTADO ACTUAL DEL HATO
        </h4>
        <hr style="margin: 8px 0 15px 0; border-color: #E3E7ED;">
    </div>
</div>

<div class="row" id="section_estado_body">
    <div class="col-md-6" id="box_lotes">
        <div class="box box-info">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-map-marker" style="color:#00BCD4;"></i> Distribución por Lote</h3>
                <div class="box-tools pull-right"><button class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button></div>
            </div>
            <div class="box-body">
                <div style="position:relative;">
                    <div id="chartdivlotes" style="width:100%;height:420px;"></div>
                    <div id="chartdivlotes_msg" class="chart-empty" style="display:none;position:absolute;top:0;left:0;right:0;bottom:0;">Sin datos</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6" id="box_categorias">
        <div class="box box-info">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-pie-chart" style="color:#00BCD4;"></i> Distribución por Categoría</h3>
                <div class="box-tools pull-right"><button class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button></div>
            </div>
            <div class="box-body">
                <div style="position:relative;">
                    <div id="chartdivcat" style="width:100%;height:520px;"></div>
                    <div id="chartdivcat_msg" class="chart-empty" style="display:none;position:absolute;top:0;left:0;right:0;bottom:0;">Sin datos</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- BLOQUE 2 — EVOLUCIÓN -->
<div class="row" id="section_evolucion_header">
    <div class="col-md-12" style="margin-bottom: 5px; margin-top: 10px;">
        <h4 style="margin: 5px 0 0 0; color: #6B7480; font-weight: 500; font-size: 13px; letter-spacing: 1px;">
            EVOLUCIÓN EN EL TIEMPO
        </h4>
        <hr style="margin: 8px 0 15px 0; border-color: #E3E7ED;">
    </div>
</div>

<div class="row" id="section_evolucion_filtro">
    <div class="col-md-12">
        <div class="box" style="border-top: 3px solid #22B34E; border-radius: 6px;">
            <div class="box-body" style="padding: 14px 18px;">
                <div class="row">
                    <div class="col-md-1 text-center" style="border-right: 1px solid #E3E7ED; padding-top: 6px;">
                        <i class="fa fa-filter" style="font-size: 22px; color: #22B34E;"></i>
                        <div style="font-size: 10px; color: #6B7480; font-weight: 600; margin-top: 4px;">FILTRO</div>
                    </div>
                    <div class="col-md-6" style="padding-left: 18px;">
                        <div id="quick_ranges" role="group">
                            <button type="button" class="btn quick-range" data-range="hoy">Hoy</button>
                            <button type="button" class="btn quick-range" data-range="ayer">Ayer</button>
                            <button type="button" class="btn quick-range" data-range="semana">Esta semana</button>
                            <button type="button" class="btn quick-range" data-range="mes">Este mes</button>
                            <button type="button" class="btn quick-range" data-range="mes_pasado">Mes pasado</button>
                            <button type="button" class="btn quick-range" data-range="anio">Este año</button>
                            <button type="button" class="btn quick-range" data-range="personalizado">Personalizado</button>
                        </div>
                        <div style="font-size: 12px; color: #6B7480; margin-top: 6px; font-style: italic;">
                            Período actual: <strong id="range_label_txt">este mes</strong>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div id="custom_range" style="display:none;">
                            <div class="input-group input-group-sm">
                                <span class="input-group-addon" style="background:#22B34E;color:#fff;border-color:#1A8C3D;">Desde</span>
                                <input type="text" class="form-control" id="filtro_desde" readonly="true" placeholder="año-mes-día">
                                <span class="input-group-addon" style="background:#22B34E;color:#fff;border-color:#1A8C3D;">Hasta</span>
                                <input type="text" class="form-control" id="filtro_hasta" readonly="true" placeholder="año-mes-día">
                                <span class="input-group-btn">
                                    <button class="btn btn-success btn-sm" id="aplicar_rango" type="button">
                                        <i class="fa fa-search"></i> Aplicar
                                    </button>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12" id="box_leche">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Producción de Leche <small class="text-muted" id="lbl_rango_prod"></small></h3>
                <div class="box-tools pull-right"><button class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button></div>
            </div>
            <div class="box-body">
                <div style="position:relative;">
                    <div id="chartdivprol" style="width:100%;height:320px;"></div>
                    <div id="chartdivprol_msg" class="chart-empty" style="display:none;position:absolute;top:0;left:0;right:0;bottom:0;">Sin datos para el período seleccionado</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12" id="box_nacimientos">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Estado de Nacimientos <small class="text-muted" id="lbl_rango_nac"></small></h3>
                <div class="box-tools pull-right"><button class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button></div>
            </div>
            <div class="box-body">
                <div style="position:relative;">
                    <div id="chartdivnac" style="width:100%;height:360px;"></div>
                    <div id="chartdivnac_msg" class="chart-empty" style="display:none;position:absolute;top:0;left:0;right:0;bottom:0;">Sin datos para el período seleccionado</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6" id="box_top_vacas">
        <div class="box box-success">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-trophy" style="color:#FFD24A;"></i> Top 10 Vacas Más Productoras</h3>
                <div class="box-tools pull-right"><button class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button></div>
            </div>
            <div class="box-body">
                <div style="position:relative;">
                    <div id="chartdivtop" style="width:100%;height:400px;"></div>
                    <div id="chartdivtop_msg" class="chart-empty" style="display:none;position:absolute;top:0;left:0;right:0;bottom:0;">Sin datos para el período seleccionado</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6" id="box_peso">
        <div class="box box-success">
            <div class="box-header with-border">
                <h3 class="box-title">Consecutivo de Peso — Suma diaria del hato</h3>
                <div class="box-tools pull-right"><button class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button></div>
            </div>
            <div class="box-body">
                <div style="position:relative;">
                    <div id="chartdivpes" style="width:100%;height:400px;"></div>
                    <div id="chartdivpes_msg" class="chart-empty" style="display:none;position:absolute;top:0;left:0;right:0;bottom:0;">Sin datos para el período seleccionado</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12" id="box_actividad">
        <div class="box box-warning">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-line-chart" style="color:#F39C12;"></i> Actividad del Fundo</h3>
                <div class="box-tools pull-right"><button class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button></div>
            </div>
            <div class="box-body">
                <div style="position:relative;">
                    <div id="chartdivact" style="width:100%;height:350px;"></div>
                    <div id="chartdivact_msg" class="chart-empty" style="display:none;position:absolute;top:0;left:0;right:0;bottom:0;">Sin datos para el período seleccionado</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: GESTOR DE REPORTES -->
<div class="modal" id="modal3" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">

            <div class="modal-header" style="background:linear-gradient(135deg,#2563eb 0%,#1d4ed8 100%); color:#fff; border:none; padding:18px 22px;">
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"
                        style="color:#fff; opacity:.9; text-shadow:none;">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" style="font-weight:800; letter-spacing:.04em; text-transform:uppercase; color:#fff;">
                    <i class="fa fa-file-text-o"></i> Gestor de Reportes Parametrizables
                </h4>
            </div>

            <div class="modal-body" style="padding:22px 24px;">
                <div class="message1"></div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group" style="margin-bottom:14px;">
                            <label for="vista" style="font-weight:700; color:#334155; text-transform:uppercase; letter-spacing:.04em; font-size:.85rem;">
                                Reporte a consultar
                            </label>
                            <select class="form-control" id="vista">
                                <option value="" selected disabled>Seleccione el reporte</option>
                                <option value="rep_prod_leche">Producción de Leche</option>
                                <option value="rep_ser_animal">Servicios del Animal</option>
                                <option value="rep_ctl_animal">Control Sanitario</option>
                                <option value="rep_ctl_lote">Control Sanitario en Lotes</option>
                                <option value="rep_nacimientos">Nacimientos</option>
                                <option value="rep_grupo_etario">Grupos Etarios</option>
                                <option value="rep_cons_peso">Consecutivo de Peso</option>
                            </select>
                        </div>

                        <div id="params_container" style="display:none; margin-top: 8px;">
                            <label style="font-weight:700; color:#334155;">Parámetros:</label><br>
                            <label data-param="sexo_hembra" style="color:fuchsia; display:none; margin-right:12px;"><input type="checkbox" id="sexo_hembra"> HEMBRA</label>
                            <label data-param="sexo_macho" style="color:blue; display:none; margin-right:12px;"><input type="checkbox" id="sexo_macho"> MACHO</label>
                            <label data-param="rango_id" style="color:orange; display:none; margin-right:12px;"><input type="checkbox" id="rango_id"> CÓDIGO (rango)</label>
                            <label data-param="rango_fecha" style="color:teal; display:none; margin-right:12px;"><input type="checkbox" id="rango_fecha"> FECHA (rango)</label>
                            <label data-param="rango_lote" style="color:green; display:none; margin-right:12px;"><input type="checkbox" id="rango_lote"> LOTES (rango)</label>
                            <label data-param="cod_bovs" style="color:red; display:none; margin-right:12px;"><input type="checkbox" id="cod_bovs"> CÓDIGO BOVS único</label>
                        </div>

                        <div id="params_empty" style="display:none; color:#6B7480; font-style:italic; margin-top:8px;">
                            Este reporte no requiere parámetros. Presione "Generar" para continuar.
                        </div>

                        <div id="params_prompt" style="color:#6B7480; font-style:italic; margin-top:8px;">
                            Seleccione un reporte para ver los parámetros disponibles.
                        </div>

                        <hr>

                        <div class="row" style="display:none;" id="row_fechas">
                            <div class="col-sm-6">
                                <label>Fecha Inicio</label>
                                <input class="form-control" id="fecha_inicio" type="text" readonly="true" placeholder="año-mes-día">
                            </div>
                            <div class="col-sm-6">
                                <label>Fecha Final</label>
                                <input class="form-control" id="fecha_final" type="text" readonly="true" placeholder="año-mes-día">
                            </div>
                        </div>

                        <div class="row" style="display:none;" id="row_cod_rango">
                            <div class="col-sm-6">
                                <label>Código BOVS Desde</label>
                                <input class="form-control" id="cod_bovs_inicio" type="text">
                            </div>
                            <div class="col-sm-6">
                                <label>Código BOVS Hasta</label>
                                <input class="form-control" id="cod_bovs_final" type="text">
                            </div>
                        </div>

                        <div class="row" style="display:none;" id="row_lotes">
                            <div class="col-sm-6">
                                <label>Desde el Lote</label>
                                <select id="lote_inicio" class="form-control"><option value=""></option></select>
                            </div>
                            <div class="col-sm-6">
                                <label>Hasta el Lote</label>
                                <select id="lote_final" class="form-control"><option value=""></option></select>
                            </div>
                        </div>

                        <div class="row" style="display:none; margin-top: 10px;" id="row_cod_unico">
                            <div class="col-sm-8">
                                <label>
                                    <input type="checkbox" id="nro_arete"> Nro Arete
                                    &nbsp;&nbsp;
                                    <input type="checkbox" id="codigo_bovs"> Código BOVS
                                </label>
                                <input class="form-control" id="id_cod_bovs" type="text" placeholder="Código / Nro Arete">
                                <div id="ms_id_cod_bovs" style="background:#F39C12; color:#fff; text-align:center; margin-top:4px; border-radius:4px;"></div>
                                <div id="ms_id_cod_bovs_acep" style="background:#19ac1b; color:#fff; text-align:center; margin-top:4px; border-radius:4px;"></div>
                            </div>
                            <div class="col-sm-2" style="padding-top:22px;">
                                <button id="valida_cod" type="button" class="btn btn-primary btn-sm">
                                    <i class="fa fa-check-square-o"></i> Validar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer" style="border-top:1px solid #e5e7eb; padding:16px 22px; background:#f8fafc;">
                <button id="cancelar" type="button" class="btn btn-default pull-left" data-dismiss="modal"
                        style="min-width:130px; min-height:44px; border-radius:8px;">
                    <i class="fa fa-fw fa-times"></i> Cancelar
                </button>
                <button id="crear_reporte" type="button" class="btn btn-success" disabled
                        style="min-width:150px; min-height:44px; border-radius:8px; font-weight:700;">
                    <i class="fa fa-fw fa-save"></i> Generar
                </button>
            </div>

        </div>
    </div>
</div>

<!-- Scripts -->
<script type="text/javascript" src="../../plugins/confirma/jquery-confirm.min.js"></script>
<link rel="stylesheet" href="../../plugins/confirma/jquery-confirm.min.css" type="text/css" />
<script type="text/javascript" src="../../plugins/datepicker/bootstrap-datepicker.js"></script>
<script src="../../plugins/datepicker/locales/bootstrap-datepicker.es.js" charset="UTF-8"></script>
<link rel="stylesheet" href="../../plugins/datepicker/datepicker3.css" type="text/css" />
<script type="text/javascript" src="../../plugins/amcharts4/core.js"></script>
<script type="text/javascript" src="../../plugins/amcharts4/charts.js"></script>
<script type="text/javascript" src="../../plugins/amcharts4/themes/animated.js"></script>

<style>
    #quick_ranges .quick-range {
        background: #F4F6F8;
        border: 1px solid #D8DEE6;
        color: #2C3440;
        font-weight: 500;
        padding: 6px 14px;
        border-radius: 20px;
        margin-right: 5px;
        margin-bottom: 5px;
        transition: all 0.15s ease;
    }

    #quick_ranges .quick-range:hover {
        background: #E8F5EC;
        border-color: #22B34E;
        color: #146B2E;
        transform: translateY(-1px);
    }

    #quick_ranges .quick-range.active {
        background: linear-gradient(135deg, #22B34E 0%, #1A8C3D 100%);
        color: #FFFFFF;
        border-color: #1A8C3D;
        font-weight: 600;
        box-shadow: 0 2px 6px rgba(34, 179, 78, 0.35);
    }

    .kpi-card:hover {
        transform: translateY(-2px);
        transition: transform 0.15s ease;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .chart-empty {
        display: flex;
        align-items: center;
        justify-content: center;
        color: #6B7480;
        font-style: italic;
        font-size: 14px;
        background: #FAFBFC;
        border: 1px dashed #E3E7ED;
        border-radius: 4px;
    }

    .datepicker { z-index: 9999 !important; }
    .datepicker-dropdown { z-index: 9999 !important; }

    #crear_reporte:disabled { opacity: 0.5; cursor: not-allowed; }
    #params_container label { cursor: pointer; margin-right: 8px; }
</style>

<script type="text/javascript">
    /* ============================================================
   VARIABLES GLOBALES
   ============================================================ */
    var num_animales_activos = 0;
    var num_gestantes_activas = 0;
    var num_servidas_activas = 0;
    var num_productoras_leche_activas = 0;
    var buscar_cod = 0;
    var tipo_rep = "";
    var codigo_validado = false;
    var verificando_reporte = false;

    /* Prefijo BOVS del fundo activo (usado para filtrar por código BOVS) */
    var BOVS_PREFIJO = (function() {
        var f = <?php echo (int)$_SESSION['mfundos_id']; ?>;
        var s = String(f);
        while (s.length < 3) { s = '0' + s; }
        return 'SOVCA-' + s + '-';
    })();

    var rango_actual = (function() {
        var hoy = new Date();
        var primero = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
        return {
            desde: isoDate(primero),
            hasta: isoDate(hoy),
            etiqueta: 'este mes'
        };
    })();

    var chartLeche, chartPeso, chartNac, chartTop, chartLotes, chartCat, chartAct;

    var REPORTE_CONFIG = {
        'rep_prod_leche':   ['rango_fecha', 'rango_id', 'rango_lote'],
        'rep_ser_animal':   ['sexo_hembra', 'sexo_macho', 'cod_bovs'],
        'rep_ctl_animal':   ['rango_fecha', 'rango_lote', 'cod_bovs'],
        'rep_ctl_lote':     ['rango_fecha', 'rango_lote'],
        'rep_nacimientos':  ['rango_fecha', 'cod_bovs'],
        'rep_grupo_etario': [],
        'rep_cons_peso':    ['rango_fecha', 'rango_lote']
    };

    var PARAM_A_FILA = {
        'rango_fecha': 'row_fechas',
        'rango_id':    'row_cod_rango',
        'rango_lote':  'row_lotes',
        'cod_bovs':    'row_cod_unico'
    };

    /* ============================================================
       HELPERS
       ============================================================ */
    function isoDate(d) {
        var dd = ('0' + d.getDate()).slice(-2);
        var mm = ('0' + (d.getMonth() + 1)).slice(-2);
        return d.getFullYear() + '-' + mm + '-' + dd;
    }

    function parseFechaAISO(valor) {
        if (!valor) return '';
        valor = valor.trim();
        if (/^\d{4}-\d{2}-\d{2}$/.test(valor)) return valor;
        var m1 = valor.match(/^(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})$/);
        if (m1) return m1[3] + '-' + ('0' + m1[2]).slice(-2) + '-' + ('0' + m1[1]).slice(-2);
        var m2 = valor.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
        if (m2) return m2[3] + '-' + ('0' + m2[1]).slice(-2) + '-' + ('0' + m2[2]).slice(-2);
        return valor;
    }

    function estaVacio(datos) {
        if (!datos || datos.length === 0) return true;
        if (datos.length === 1) {
            var d = datos[0];
            if (d.nombre === 'Sin datos' && (d.total === 0 || d.total === '0')) return true;
            if (d.sexo === 'Sin datos') return true;
            if (d.fecha_produccion === 'Sin datos') return true;
            if (d.fecha_pesaje === 'Sin datos') return true;
            if (d.mes === 'Sin datos') return true;
        }
        return false;
    }

    function mostrarVacio(chartId, mensaje) {
        var el = document.getElementById(chartId);
        var msg = document.getElementById(chartId + '_msg');
        if (el) el.style.display = 'none';
        if (msg) { msg.textContent = mensaje; msg.style.display = 'flex'; }
    }

    function ocultarVacio(chartId) {
        var el = document.getElementById(chartId);
        var msg = document.getElementById(chartId + '_msg');
        if (el) el.style.display = '';
        if (msg) msg.style.display = 'none';
    }

    function debounce(fn, wait) {
        var t;
        return function() {
            var ctx = this, args = arguments;
            clearTimeout(t);
            t = setTimeout(function() { fn.apply(ctx, args); }, wait);
        };
    }

    function calcularRango(tipo) {
        var hoy = new Date(), desde, hasta;
        switch (tipo) {
            case 'hoy':
                desde = hasta = isoDate(hoy); break;
            case 'ayer':
                var a = new Date(hoy); a.setDate(a.getDate() - 1);
                desde = hasta = isoDate(a); break;
            case 'semana':
                var lunes = new Date(hoy), dia = hoy.getDay();
                lunes.setDate(hoy.getDate() - (dia === 0 ? 6 : dia - 1));
                desde = isoDate(lunes); hasta = isoDate(hoy); break;
            case 'mes':
                desde = isoDate(new Date(hoy.getFullYear(), hoy.getMonth(), 1));
                hasta = isoDate(hoy); break;
            case 'mes_pasado':
                desde = isoDate(new Date(hoy.getFullYear(), hoy.getMonth() - 1, 1));
                hasta = isoDate(new Date(hoy.getFullYear(), hoy.getMonth(), 0)); break;
            case 'anio':
                desde = isoDate(new Date(hoy.getFullYear(), 0, 1));
                hasta = isoDate(hoy); break;
            default:
                desde = hasta = isoDate(hoy);
        }
        return { desde: desde, hasta: hasta };
    }

    /* ============================================================
       MOTOR DE CARGA DE GRÁFICAS
       Bundle + Cache + Abort + dataType JSON
       ============================================================ */
    var _graficasCache      = {};   // { url: datos } para estáticas
    var _graficasPendientes = {};   // { chartId: jqXHR }
    var _graficasListas     = {};   // { chartId: true }
    var _charts             = {};   // { chartId: chart }
    var _bundleXHR          = null; // XHR activo del bundle

    var _ENDPOINTS_ESTATICAS = {
        'chartdivlotes': '../../data_json/data_dashboard_lotes.php',
        'chartdivcat':   '../../data_json/data_dashboard_categorias.php'
    };

    var _URL_BUNDLE = '../../data_json/data_dashboard_bundle.php';

    function registrarChart(chartId, chart) {
        _charts[chartId] = chart;
    }

    function _aplicarDatos(chartId, datos) {
        var chart = _charts[chartId];
        if (!chart) return;
        if (estaVacio(datos)) {
            mostrarVacio(chartId, 'Sin datos para el periodo seleccionado');
            return;
        }
        ocultarVacio(chartId);
        chart.data = [];
        if (typeof chart.invalidateData === 'function') chart.invalidateData();
        chart.data = datos;
    }

    function _ejecutarCargaEstatica(chartId, url) {
        if (_graficasCache[url]) {
            _aplicarDatos(chartId, _graficasCache[url]);
            _graficasListas[chartId] = true;
            return;
        }

        if (_graficasPendientes[chartId]) {
            try { _graficasPendientes[chartId].abort(); } catch (e) {}
        }

        var xhr = $.ajax({
            url: url,
            type: 'GET',
            dataType: 'json',
            cache: true
        })
        .done(function(datos) {
            delete _graficasPendientes[chartId];
            _graficasCache[url] = datos;
            _aplicarDatos(chartId, datos);
            _graficasListas[chartId] = true;
        })
        .fail(function(x, status) {
            delete _graficasPendientes[chartId];
            if (status === 'abort') return;
            mostrarVacio(chartId, 'Error al cargar (' + x.status + ')');
        });

        _graficasPendientes[chartId] = xhr;
    }

    function _cargarEstatica(chartId) {
        var url = _ENDPOINTS_ESTATICAS[chartId];
        if (!url) return;
        if (_graficasListas[chartId]) return;

        // Si el módulo padre está oculto, no pedir
        var boxPadre = $('#' + chartId).closest('[id^="box_"]');
        if (boxPadre.length && boxPadre.is(':hidden')) return;

        _ejecutarCargaEstatica(chartId, url);
    }

    function _aplicarBundle(bundle) {
        var mapa = {
            'chartdivprol': 'prod_leche',
            'chartdivnac':  'nacimientos',
            'chartdivtop':  'top_vacas',
            'chartdivpes':  'peso',
            'chartdivact':  'actividad'
        };
        Object.keys(mapa).forEach(function(chartId) {
            var key = mapa[chartId];
            var boxPadre = $('#' + chartId).closest('[id^="box_"]');
            if (boxPadre.length && boxPadre.is(':hidden')) return;
            if (bundle && bundle[key]) {
                _aplicarDatos(chartId, bundle[key]);
                _graficasListas[chartId] = true;
            }
        });
    }

    function _cargarBundle(desde, hasta) {
        var url = _URL_BUNDLE + '?desde=' + encodeURIComponent(desde) + '&hasta=' + encodeURIComponent(hasta);

        // Cache hit
        if (_graficasCache[url]) {
            _aplicarBundle(_graficasCache[url]);
            return;
        }

        // Abortar bundle anterior si sigue en vuelo
        if (_bundleXHR) { try { _bundleXHR.abort(); } catch (e) {} }

        _bundleXHR = $.ajax({
            url: url,
            type: 'GET',
            dataType: 'json',
            cache: true
        })
        .done(function(bundle) {
            _bundleXHR = null;
            _graficasCache[url] = bundle;
            _aplicarBundle(bundle);
        })
        .fail(function(x, status) {
            _bundleXHR = null;
            if (status === 'abort') return;
            console.error('[Bundle] Error HTTP ' + x.status);
            ['chartdivprol', 'chartdivnac', 'chartdivtop', 'chartdivpes', 'chartdivact'].forEach(function(id) {
                mostrarVacio(id, 'Error al cargar (' + x.status + ')');
            });
        });
    }

    function invalidarCacheConRango() {
        // Solo borrar las entradas del bundle; las estáticas no dependen del rango
        Object.keys(_graficasCache).forEach(function(k) {
            if (k.indexOf(_URL_BUNDLE) === 0) delete _graficasCache[k];
        });
        ['chartdivprol', 'chartdivnac', 'chartdivtop', 'chartdivpes', 'chartdivact'].forEach(function(id) {
            delete _graficasListas[id];
        });
    }

    function recargarGraficas() {
        // 1. Estáticas (solo la primera vez cada una)
        _cargarEstatica('chartdivlotes');
        _cargarEstatica('chartdivcat');

        // 2. Bundle con rango (1 solo request)
        _cargarBundle(rango_actual.desde, rango_actual.hasta);
    }

    /* ============================================================
       CONFIGURACION DEL MODAL DE REPORTES
       ============================================================ */
    function aplicarConfiguracionReporte(tipo) {
        var activos = REPORTE_CONFIG[tipo] || [];
        var todos = ['sexo_hembra', 'sexo_macho', 'rango_id', 'rango_fecha', 'rango_lote', 'cod_bovs'];

        if (!tipo) {
            $('#params_container').hide();
            $('#params_empty').hide();
            $('#params_prompt').show();
            Object.values(PARAM_A_FILA).forEach(function(rowId) { $('#' + rowId).hide(); });
            return;
        }

        $('#params_prompt').hide();

        if (activos.length === 0) {
            $('#params_container').hide();
            $('#params_empty').show();
            Object.values(PARAM_A_FILA).forEach(function(rowId) { $('#' + rowId).hide(); });
            return;
        }

        $('#params_container').show();
        $('#params_empty').hide();

        todos.forEach(function(id) {
            var $lbl = $('label[data-param="' + id + '"]');
            if (activos.indexOf(id) !== -1) $lbl.show();
            else { $lbl.hide(); $('#' + id).prop('checked', false).val(0); }
        });

        Object.values(PARAM_A_FILA).forEach(function(rowId) { $('#' + rowId).hide(); });
    }

    function cargarLotes(tipo) {
        var action = '';
        if (tipo === 'rep_prod_leche') action = 'get_lote_prod_leche';
        else if (tipo === 'rep_cons_peso') action = 'get_lote_consecutivo_peso';
        else if (tipo === 'rep_ctl_animal' || tipo === 'rep_ctl_lote') action = 'get_lote_control_sanitario';
        else {
            $("#lote_inicio, #lote_final").html('<option value=""></option>');
            return;
        }

        $.post("../../controllers/mlotes_controller", { action: action })
            .done(function(data) {
                $("#lote_inicio").html(data);
                $("#lote_final").html(data);
            });
    }

    function validarBotonGenerar() {
        var puede = false;
        if (verificando_reporte) { $("#crear_reporte").prop('disabled', true); return; }
        if (!tipo_rep) { $("#crear_reporte").prop('disabled', true); return; }

        var tiene_fecha_completa       = $("#rango_fecha").prop('checked') && $("#fecha_inicio").val() !== '' && $("#fecha_final").val() !== '';
        var tiene_rango_cod_completo   = $("#rango_id").prop('checked') && $("#cod_bovs_inicio").val() !== '' && $("#cod_bovs_final").val() !== '';
        var tiene_rango_lote_completo  = $("#rango_lote").prop('checked') && $("#lote_inicio").val() !== '' && $("#lote_final").val() !== '';
        var tiene_sexo                 = $("#sexo_hembra").prop('checked') || $("#sexo_macho").prop('checked');
        var tiene_codigo_validado      = $("#cod_bovs").prop('checked') && codigo_validado === true && $("#id_cod_bovs").val() !== '';

        switch (tipo_rep) {
            case 'rep_grupo_etario':   puede = true; break;
            case 'rep_ctl_animal':     puede = tiene_fecha_completa || tiene_rango_lote_completo || tiene_codigo_validado; break;
            case 'rep_ctl_lote':       puede = tiene_fecha_completa || tiene_rango_lote_completo; break;
            case 'rep_nacimientos':    puede = tiene_fecha_completa || tiene_codigo_validado; break;
            case 'rep_ser_animal':     puede = tiene_sexo || tiene_codigo_validado; break;
            case 'rep_prod_leche':     puede = tiene_fecha_completa || tiene_rango_cod_completo || tiene_rango_lote_completo; break;
            case 'rep_cons_peso':      puede = tiene_fecha_completa || tiene_rango_lote_completo; break;
            default:                   puede = false;
        }
        $("#crear_reporte").prop('disabled', !puede);
    }

    function resetGestorReportes() {
        $('#vista').val('').trigger('change');
        $("#rango_fecha, #rango_id, #rango_lote, #cod_bovs, #sexo_hembra, #sexo_macho, #nro_arete, #codigo_bovs")
            .prop('checked', false).val(0);
        $("#fecha_inicio, #fecha_final, #cod_bovs_inicio, #cod_bovs_final, #id_cod_bovs").val('');
        $("#lote_inicio, #lote_final").html('<option value=""></option>').val('');
        $("#ms_id_cod_bovs").text('').hide();
        $("#ms_id_cod_bovs_acep").text('').hide();
        tipo_rep = '';
        buscar_cod = 0;
        codigo_validado = false;
        $('#params_container').hide();
        $('#params_empty').hide();
        $('#params_prompt').show();
        Object.values(PARAM_A_FILA).forEach(function(rowId) { $('#' + rowId).hide(); });
        $("#crear_reporte").prop('disabled', true);
    }

    /* ============================================================
       INIT
       ============================================================ */
    $(document).ready(function() {
        $.post("../../controllers/mreportes_controller", { action: "search_datos_animales" })
            .done(function(data) {
                var p = $.parseJSON(data);
                num_animales_activos = parseInt(p.total_animales) || 0;
                num_gestantes_activas = parseInt(p.nro_gestantes) || 0;
                num_servidas_activas = parseInt(p.nro_servidas) || 0;
                num_productoras_leche_activas = parseInt(p.nro_prodleche) || 0;
                $("#animales_activos").text(num_animales_activos);
                $("#total_gestantes").text(num_gestantes_activas);
                $("#total_servidas").text(num_servidas_activas);
                $("#prod_leche").text(num_productoras_leche_activas);
                $("#prod_carne").text(parseInt(p.nro_reg_prodcarne) || 0);
            }, "json");

        $.get("../../data_json/data_dashboard_modulos.php", function(data) {
            var m;
            try { m = JSON.parse(data); } catch (e) { m = {}; }
            aplicarVisibilidadModulos(m);
        }).fail(function() {
            console.warn('No se pudo cargar data_dashboard_modulos.php');
        });

        resetGestorReportes();
    });

    function aplicarVisibilidadModulos(m) {
        if (!m.lotes) $('#box_lotes').hide();
        if (!m.categorias) $('#box_categorias').hide();
        if (!m.lotes && !m.categorias) {
            $('#section_estado_header').hide();
            $('#section_estado_body').hide();
        }
        if (!m.leche) {
            $('#box_leche').hide();
            $('#box_top_vacas').hide();
        }
        if (!m.peso) $('#box_peso').hide();
        if (!m.nacimientos) $('#box_nacimientos').hide();
        if (!m.servicios && !m.nacimientos && !m.controles) $('#box_actividad').hide();

        if (!m.leche && !m.peso && !m.nacimientos && !m.servicios && !m.controles) {
            $('#section_evolucion_header').hide();
            $('#section_evolucion_filtro').hide();
        }
    }

    /* ============================================================
       FILTRO DE GRAFICAS
       ============================================================ */
    var _recargarGraficasDebounced = debounce(recargarGraficas, 350);

    $(document).on('click', '.quick-range', function() {
        var tipo = $(this).data('range');
        if (tipo === 'personalizado') {
            $("#custom_range").show();
            $("#quick_ranges .quick-range").removeClass('active');
            $(this).addClass('active');
            return;
        }
        $("#custom_range").hide();
        $("#quick_ranges .quick-range").removeClass('active');
        $(this).addClass('active');
        aplicarRango(tipo);
    });

    function aplicarRango(tipo) {
        var r = calcularRango(tipo);
        rango_actual.desde = r.desde;
        rango_actual.hasta = r.hasta;
        var etiquetas = {
            'hoy': 'hoy', 'ayer': 'ayer', 'semana': 'esta semana',
            'mes': 'este mes', 'mes_pasado': 'mes pasado', 'anio': 'este año'
        };
        rango_actual.etiqueta = etiquetas[tipo] || (r.desde + ' a ' + r.hasta);
        $("#range_label_txt").text(rango_actual.etiqueta);
        $("#lbl_rango_prod").text('- ' + rango_actual.etiqueta);
        $("#lbl_rango_nac").text('- ' + rango_actual.etiqueta);
        invalidarCacheConRango();
        _recargarGraficasDebounced();
    }

    $('#filtro_desde, #filtro_hasta').datepicker({
        format: 'yyyy-mm-dd',
        endDate: '+0d',
        todayHighlight: true,
        autoclose: true,
        language: 'es',
        container: 'body'
    });

    $("#aplicar_rango").click(function() {
        var d = parseFechaAISO($("#filtro_desde").val());
        var h = parseFechaAISO($("#filtro_hasta").val());
        if (!d || !h) {
            $.alert({
                title: 'Debe completar ambas fechas',
                confirmButton: 'OK',
                confirmButtonClass: 'btn-warning'
            });
            return;
        }
        rango_actual.desde = d;
        rango_actual.hasta = h;
        rango_actual.etiqueta = d + ' a ' + h;
        $("#range_label_txt").text(rango_actual.etiqueta);
        $("#lbl_rango_prod").text('- ' + rango_actual.etiqueta);
        $("#lbl_rango_nac").text('- ' + rango_actual.etiqueta);
        invalidarCacheConRango();
        _recargarGraficasDebounced();
    });

    /* ============================================================
       INICIALIZACION DE GRAFICAS
       ============================================================ */
    am4core.ready(function() {
        am4core.useTheme(am4themes_animated);

        /* ---- LECHE ---- */
        chartLeche = am4core.create("chartdivprol", am4charts.XYChart);
        var catL = chartLeche.xAxes.push(new am4charts.CategoryAxis());
        catL.dataFields.category = "fecha_produccion";
        catL.renderer.minGridDistance = 20;
        catL.renderer.labels.template.fontSize = 10;

        var valL = chartLeche.yAxes.push(new am4charts.ValueAxis());
        valL.title.text = "Litros / dia";

        var serL = chartLeche.series.push(new am4charts.LineSeries());
        serL.dataFields.valueY = "total_leche";
        serL.dataFields.categoryX = "fecha_produccion";
        serL.strokeWidth = 2;
        serL.stroke = am4core.color("#22B34E");
        serL.fill = am4core.color("#22B34E");
        serL.fillOpacity = 0.15;
        serL.adapter.add("tooltipText", function(text, target) {
            if (!target.dataItem || !target.dataItem.dataContext) return text;
            var d = target.dataItem.dataContext;
            return d.fecha_produccion + ': ' + d.total_leche + ' L';
        });
        var bL = serL.bullets.push(new am4charts.CircleBullet());
        bL.circle.radius = 3;
        bL.circle.fill = am4core.color("#22B34E");
        chartLeche.cursor = new am4charts.XYCursor();

        /* ---- ESTADO DE NACIMIENTOS ---- */
        chartNac = am4core.create("chartdivnac", am4charts.XYChart);
        chartNac.legend = new am4charts.Legend();
        chartNac.legend.position = "bottom";
        chartNac.legend.labels.template.fontSize = 11;

        var catNac = chartNac.xAxes.push(new am4charts.CategoryAxis());
        catNac.dataFields.category = "mes";
        catNac.renderer.minGridDistance = 30;
        catNac.renderer.labels.template.fontSize = 10;

        var valNac = chartNac.yAxes.push(new am4charts.ValueAxis());
        valNac.title.text = "Nacimientos";
        valNac.stacked = true;
        valNac.renderer.minGridDistance = 20;

        var serFichados = chartNac.series.push(new am4charts.ColumnSeries());
        serFichados.dataFields.valueY = "fichados";
        serFichados.dataFields.categoryX = "mes";
        serFichados.name = "Fichados";
        serFichados.stacked = true;
        serFichados.columns.template.fill = am4core.color("#22B34E");
        serFichados.columns.template.stroke = am4core.color("#1A8C3D");
        serFichados.columns.template.strokeWidth = 1;
        serFichados.adapter.add("tooltipText", function(text, target) {
            if (!target.dataItem || !target.dataItem.dataContext) return text;
            var d = target.dataItem.dataContext;
            return d.mes + ' — Fichados: ' + d.fichados;
        });

        var serSinFichar = chartNac.series.push(new am4charts.ColumnSeries());
        serSinFichar.dataFields.valueY = "sin_fichar";
        serSinFichar.dataFields.categoryX = "mes";
        serSinFichar.name = "Sin fichar";
        serSinFichar.stacked = true;
        serSinFichar.columns.template.fill = am4core.color("#F39C12");
        serSinFichar.columns.template.stroke = am4core.color("#C87F0A");
        serSinFichar.columns.template.strokeWidth = 1;
        serSinFichar.adapter.add("tooltipText", function(text, target) {
            if (!target.dataItem || !target.dataItem.dataContext) return text;
            var d = target.dataItem.dataContext;
            return d.mes + ' — Sin fichar: ' + d.sin_fichar;
        });

        var serDesinc = chartNac.series.push(new am4charts.ColumnSeries());
        serDesinc.dataFields.valueY = "desincorporados";
        serDesinc.dataFields.categoryX = "mes";
        serDesinc.name = "Desincorporados";
        serDesinc.stacked = true;
        serDesinc.columns.template.fill = am4core.color("#E91E63");
        serDesinc.columns.template.stroke = am4core.color("#AD1457");
        serDesinc.columns.template.strokeWidth = 1;
        serDesinc.adapter.add("tooltipText", function(text, target) {
            if (!target.dataItem || !target.dataItem.dataContext) return text;
            var d = target.dataItem.dataContext;
            return d.mes + ' — Desincorporados: ' + d.desincorporados;
        });

        chartNac.cursor = new am4charts.XYCursor();

        /* ---- TOP VACAS ---- */
        chartTop = am4core.create("chartdivtop", am4charts.XYChart);
        chartTop.padding(0, 0, 0, 0);
        chartTop.tooltip.paddingTop = 8;
        chartTop.tooltip.paddingBottom = 8;
        chartTop.tooltip.paddingLeft = 12;
        chartTop.tooltip.paddingRight = 12;

        var catT = chartTop.yAxes.push(new am4charts.CategoryAxis());
        catT.dataFields.category = "nombre";
        catT.renderer.inversed = true;
        catT.renderer.labels.template.fontSize = 10;
        catT.renderer.grid.template.location = 0;
        catT.renderer.labels.template.truncate = true;
        catT.renderer.labels.template.maxWidth = 120;
        catT.renderer.labels.template.wrap = false;

        var valT = chartTop.xAxes.push(new am4charts.ValueAxis());
        valT.title.text = "Litros (rango)";

        var serT = chartTop.series.push(new am4charts.ColumnSeries());
        serT.dataFields.valueX = "total";
        serT.dataFields.categoryY = "nombre";
        serT.tooltipText = "";
        serT.columns.template.fill = am4core.color("#FFD24A");
        serT.columns.template.stroke = am4core.color("#F39C12");
        serT.columns.template.strokeWidth = 1;
        serT.columns.template.tooltipText = "{nombre}: {total} L";

        serT.adapter.add("tooltipText", function(text, target) {
            if (!target.dataItem || !target.dataItem.dataContext) return "";
            var d = target.dataItem.dataContext;
            var nombre = (d.nombre && d.nombre !== '') ? d.nombre : 'Sin nombre';
            var valor = (d.total !== undefined && d.total !== null) ? d.total : 0;
            var arete = d.arete ? ' · Arete: ' + d.arete : '';
            return nombre + arete + ' · ' + valor + ' L';
        });

        /* ---- PESO ---- */
        chartPeso = am4core.create("chartdivpes", am4charts.XYChart);
        var catP = chartPeso.xAxes.push(new am4charts.CategoryAxis());
        catP.dataFields.category = "fecha_pesaje";
        catP.renderer.minGridDistance = 20;
        catP.renderer.labels.template.fontSize = 10;

        var valP = chartPeso.yAxes.push(new am4charts.ValueAxis());
        valP.title.text = "Kg (suma diaria)";

        var serP = chartPeso.series.push(new am4charts.ColumnSeries());
        serP.dataFields.valueY = "total_peso";
        serP.dataFields.categoryX = "fecha_pesaje";
        serP.columns.template.fill = am4core.color("#4A7C59");
        serP.columns.template.stroke = am4core.color("#22B34E");
        serP.adapter.add("tooltipText", function(text, target) {
            if (!target.dataItem || !target.dataItem.dataContext) return text;
            var d = target.dataItem.dataContext;
            return d.fecha_pesaje + ': ' + d.total_peso + ' Kg';
        });
        chartPeso.cursor = new am4charts.XYCursor();

        /* ---- ACTIVIDAD ---- */
        chartAct = am4core.create("chartdivact", am4charts.XYChart);
        var catA = chartAct.xAxes.push(new am4charts.CategoryAxis());
        catA.dataFields.category = "mes";
        catA.renderer.minGridDistance = 30;
        catA.renderer.labels.template.fontSize = 10;

        var valA = chartAct.yAxes.push(new am4charts.ValueAxis());
        valA.title.text = "Cantidad";

        var sServ = chartAct.series.push(new am4charts.ColumnSeries());
        sServ.dataFields.valueY = "servicios";
        sServ.dataFields.categoryX = "mes";
        sServ.name = "Servicios";
        sServ.columns.template.fill = am4core.color("#2196F3");

        var sNac = chartAct.series.push(new am4charts.ColumnSeries());
        sNac.dataFields.valueY = "nacimientos";
        sNac.dataFields.categoryX = "mes";
        sNac.name = "Nacimientos";
        sNac.columns.template.fill = am4core.color("#22B34E");

        var sCtl = chartAct.series.push(new am4charts.ColumnSeries());
        sCtl.dataFields.valueY = "controles";
        sCtl.dataFields.categoryX = "mes";
        sCtl.name = "Controles sanitarios";
        sCtl.columns.template.fill = am4core.color("#F39C12");

        chartAct.legend = new am4charts.Legend();
        chartAct.cursor = new am4charts.XYCursor();

        /* ---- LOTES ---- */
        chartLotes = am4core.create("chartdivlotes", am4charts.XYChart);
        var catLo = chartLotes.xAxes.push(new am4charts.CategoryAxis());
        catLo.dataFields.category = "nombre";
        catLo.renderer.labels.template.fontSize = 10;
        catLo.renderer.labels.template.rotation = 270;
        catLo.renderer.labels.template.horizontalCenter = "right";
        catLo.renderer.labels.template.verticalCenter = "middle";
        catLo.renderer.minGridDistance = 20;

        var valLo = chartLotes.yAxes.push(new am4charts.ValueAxis());
        valLo.title.text = "Animales";

        var serLo = chartLotes.series.push(new am4charts.ColumnSeries());
        serLo.dataFields.valueY = "total";
        serLo.dataFields.categoryX = "nombre";
        serLo.columns.template.fill = am4core.color("#22B34E");
        serLo.columns.template.stroke = am4core.color("#1A8C3D");
        serLo.adapter.add("tooltipText", function(text, target) {
            if (!target.dataItem || !target.dataItem.dataContext) return text;
            var d = target.dataItem.dataContext;
            return d.nombre + ': ' + d.total + ' animales';
        });
        chartLotes.cursor = new am4charts.XYCursor();

        /* ---- CATEGORIAS ---- */
        chartCat = am4core.create("chartdivcat", am4charts.PieChart);
        chartCat.innerRadius = am4core.percent(45);

        var pieC = chartCat.series.push(new am4charts.PieSeries());
        pieC.dataFields.value = "total";
        pieC.dataFields.category = "nombre";
        pieC.labels.template.disabled = true;
        pieC.ticks.template.disabled = true;
        pieC.adapter.add("tooltipText", function(text, target) {
            if (!target.dataItem || !target.dataItem.dataContext) return text;
            var d = target.dataItem.dataContext;
            return d.nombre + ': ' + d.total + ' animales';
        });
        pieC.colors.list = [
            am4core.color("#22B34E"), am4core.color("#4A7C59"), am4core.color("#FFD24A"),
            am4core.color("#F39C12"), am4core.color("#E91E63"), am4core.color("#9C27B0"),
            am4core.color("#2196F3"), am4core.color("#00BCD4"), am4core.color("#8BC34A"),
            am4core.color("#795548"), am4core.color("#607D8B"), am4core.color("#CDDC39")
        ];

        chartCat.legend = new am4charts.Legend();
        chartCat.legend.position = "bottom";
        chartCat.legend.labels.template.fontSize = 10;
        chartCat.legend.valueLabels.template.fontSize = 10;
        chartCat.legend.itemContainers.template.paddingTop = 2;
        chartCat.legend.itemContainers.template.paddingBottom = 2;
        chartCat.legend.itemContainers.template.maxWidth = 180;
        chartCat.legend.contentAlign = "center";

        /* ── Registro central para el motor de carga ── */
        registrarChart('chartdivlotes', chartLotes);
        registrarChart('chartdivcat',   chartCat);
        registrarChart('chartdivprol',  chartLeche);
        registrarChart('chartdivnac',   chartNac);
        registrarChart('chartdivtop',   chartTop);
        registrarChart('chartdivpes',   chartPeso);
        registrarChart('chartdivact',   chartAct);

        recargarGraficas();
    });

    /* ============================================================
       KPIs → REPORTES RAPIDOS
       ============================================================ */
    $(document).on('click', '.kpi-card', function(e) {
        if ($(e.target).is('a')) return;
        var target = $(this).data('target');
        if (target) $("#" + target).trigger('click');
    });

    function abrirReporte(titulo, url, cantidad) {
        if (cantidad < 1) {
            $.alert({
                title: 'Sin datos disponibles',
                content: 'No existen registros para generar este reporte.',
                confirmButton: 'Entendido',
                confirmButtonClass: 'btn-success',
                closeIcon: true,
                icon: 'fa fa-info-circle',
                type: 'blue'
            });
            return;
        }
        $.confirm({
            title: 'Generar ' + titulo + '?',
            content: false,
            confirmButton: 'Si',
            cancelButton: 'No',
            confirmButtonClass: 'btn-success',
            cancelButtonClass: 'btn-default',
            confirm: function() {
                window.open(url, '',
                    "titlebars=0, toolbar=0,scrollbars=0,location=0,statusbar=0,menubar=0,resizable=0,width=450,height=550,top=150,left=500");
            }
        });
    }

    $("#animales_act").click(function(e) {
        e.preventDefault(); e.stopPropagation();
        abrirReporte('Reporte de Animales Activos', "../../Reportes/reportes/sovca/rla.php?", num_animales_activos);
    });
    $("#animales_gestantes_act").click(function(e) {
        e.preventDefault(); e.stopPropagation();
        abrirReporte('Reporte de Hembras Gestantes', "../../Reportes/reportes/sovca/rlga.php?", num_gestantes_activas);
    });
    $("#animales_servidas_act").click(function(e) {
        e.preventDefault(); e.stopPropagation();
        abrirReporte('Reporte de Hembras Servidas', "../../Reportes/reportes/sovca/rlsa.php?", num_servidas_activas);
    });
    $("#animales_prodleche_act").click(function(e) {
        e.preventDefault(); e.stopPropagation();
        abrirReporte('Reporte de Productoras de Leche', "../../Reportes/reportes/sovca/rlpa.php?", num_productoras_leche_activas);
    });

    /* ============================================================
       MODAL DE REPORTES
       ============================================================ */
    $("#btn_gestor_reportes").click(function() {
        resetGestorReportes();
        $('#modal3').appendTo('body');
        $('#modal3').modal({ backdrop: true, keyboard: true, show: true });
    });

    $("#cancelar").click(function() {
        resetGestorReportes();
        $('#modal3').modal('hide');
    });

    $("#vista").change(function() {
        tipo_rep = $("#vista").val();
        codigo_validado = false;

        $("#rango_fecha, #rango_id, #rango_lote, #cod_bovs, #sexo_hembra, #sexo_macho, #nro_arete, #codigo_bovs")
            .prop('checked', false).val(0);
        $("#fecha_inicio, #fecha_final, #cod_bovs_inicio, #cod_bovs_final, #id_cod_bovs").val('');
        $("#lote_inicio, #lote_final").val('');
        $("#ms_id_cod_bovs").text('').hide();
        $("#ms_id_cod_bovs_acep").text('').hide();

        aplicarConfiguracionReporte(tipo_rep);
        cargarLotes(tipo_rep);
        validarBotonGenerar();
    });

    $("#rango_fecha").click(function() {
        if ($(this).prop('checked')) $('#row_fechas').show();
        else { $('#row_fechas').hide(); $("#fecha_inicio, #fecha_final").val(''); }
        validarBotonGenerar();
    });

    $("#rango_id").click(function() {
        if ($(this).prop('checked')) {
            $('#row_cod_rango').show();
            $("#cod_bovs_inicio").val(BOVS_PREFIJO);
            $("#cod_bovs_final").val(BOVS_PREFIJO);
            setTimeout(function() {
                var $inp = $("#cod_bovs_inicio");
                $inp.focus();
                if ($inp[0] && $inp[0].setSelectionRange) {
                    var len = $inp.val().length;
                    $inp[0].setSelectionRange(len, len);
                }
            }, 50);
        } else {
            $('#row_cod_rango').hide();
            $("#cod_bovs_inicio, #cod_bovs_final").val('');
        }
        validarBotonGenerar();
    });

    $("#rango_lote").click(function() {
        if ($(this).prop('checked')) $('#row_lotes').show();
        else { $('#row_lotes').hide(); $("#lote_inicio, #lote_final").val(''); }
        validarBotonGenerar();
    });

    $("#cod_bovs").click(function() {
        if ($(this).prop('checked')) {
            $('#row_cod_unico').show();
        } else {
            $('#row_cod_unico').hide();
            $("#id_cod_bovs").val('').trigger('input');
            codigo_validado = false;
            $("#nro_arete, #codigo_bovs").prop('checked', false);
            buscar_cod = 0;
        }
        validarBotonGenerar();
    });

    $("#nro_arete").click(function() {
        if ($(this).prop('checked')) {
            $("#codigo_bovs").prop('checked', false);
            buscar_cod = 2;
            $("#id_cod_bovs").val('').trigger('input');
            $("#id_cod_bovs").attr('placeholder', 'Nro Arete');
        } else {
            buscar_cod = 0;
            codigo_validado = false;
            $("#id_cod_bovs").val('').trigger('input');
        }
        validarBotonGenerar();
    });

    $("#codigo_bovs").click(function() {
        if ($(this).prop('checked')) {
            $("#nro_arete").prop('checked', false);
            buscar_cod = 1;
            $("#id_cod_bovs").val(BOVS_PREFIJO).trigger('input');
            $("#id_cod_bovs").attr('placeholder', 'Código BOVS');
            setTimeout(function() {
                var $inp = $("#id_cod_bovs");
                $inp.focus();
                if ($inp[0] && $inp[0].setSelectionRange) {
                    var len = $inp.val().length;
                    $inp[0].setSelectionRange(len, len);
                }
            }, 50);
        } else {
            buscar_cod = 0;
            codigo_validado = false;
            $("#id_cod_bovs").val('').trigger('input');
        }
        validarBotonGenerar();
    });

    $("#sexo_hembra").click(function() {
        if ($(this).prop('checked')) $("#sexo_macho").prop('checked', false);
        validarBotonGenerar();
    });
    $("#sexo_macho").click(function() {
        if ($(this).prop('checked')) $("#sexo_hembra").prop('checked', false);
        validarBotonGenerar();
    });

    $("#cod_bovs_inicio, #cod_bovs_final").on('input', validarBotonGenerar);
    $("#lote_inicio, #lote_final").on('change', validarBotonGenerar);

    // Autocompletar "Hasta" si solo se escribió el "Desde"
    $("#cod_bovs_inicio").on('blur', function() {
        var desde = $(this).val().trim();
        var hasta = $("#cod_bovs_final").val().trim();
        if (desde && desde !== BOVS_PREFIJO && hasta === BOVS_PREFIJO) {
            $("#cod_bovs_final").val(desde).trigger('input');
        }
    });

    $("#id_cod_bovs").on('input', function() {
        codigo_validado = false;
        validarBotonGenerar();
    });

    $('#fecha_inicio, #fecha_final').datepicker({
        format: 'yyyy-mm-dd',
        endDate: '+0d',
        todayHighlight: true,
        autoclose: true,
        language: 'es',
        container: 'body'
    }).on('changeDate', function() {
        validarBotonGenerar();
    });

    $("#crear_reporte").click(function() {
        if (verificando_reporte) return;

        var parametros = "";
        var fecha_inicio = parseFechaAISO($("#fecha_inicio").val()),
            fecha_final  = parseFechaAISO($("#fecha_final").val());
        var cod_bovs_inicio = $("#cod_bovs_inicio").val(),
            cod_bovs_final  = $("#cod_bovs_final").val();
        var lote_inicio = $("#lote_inicio").val(),
            lote_final  = $("#lote_final").val();
        var codigo_bovs = $("#id_cod_bovs").val();
        var tipo = 0, genera = false, mensaje = 0;

        switch (tipo_rep) {
            case 'rep_prod_leche':
                if ($("#rango_fecha").prop('checked')) {
                    if (fecha_inicio && fecha_final) {
                        parametros += (parametros ? '&' : '') + 'fecha_inicio=' + fecha_inicio + '&fecha_final=' + fecha_final;
                        genera = true; tipo = 1;
                    } else mensaje = 1;
                }
                if ($("#rango_id").prop('checked')) {
                    if (cod_bovs_inicio && cod_bovs_final) {
                        parametros += (parametros ? '&' : '') + 'cod_bovs_inicio=' + cod_bovs_inicio + '&cod_bovs_final=' + cod_bovs_final;
                        genera = true; tipo = 1;
                    } else mensaje = 2;
                }
                if ($("#rango_lote").prop('checked')) {
                    if (lote_inicio && lote_final) {
                        parametros += (parametros ? '&' : '') + 'lote_inicio=' + lote_inicio + '&lote_final=' + lote_final;
                        genera = true; tipo = 1;
                    } else mensaje = 3;
                }
                break;
            case 'rep_ser_animal':
                if ($("#sexo_hembra").prop('checked')) {
                    parametros += (parametros ? '&' : '') + 'filtrarXsexo=1';
                    genera = true; tipo = 2;
                } else if ($("#sexo_macho").prop('checked')) {
                    parametros += (parametros ? '&' : '') + 'filtrarXsexo=2';
                    genera = true; tipo = 2;
                }
                if (buscar_cod > 0 && codigo_bovs && codigo_validado) {
                    parametros += (parametros ? '&' : '') + 'cod_bovs=' + codigo_bovs + '&codigo_erete=' + buscar_cod;
                    genera = true; tipo = 2;
                }
                if (!genera) mensaje = 4;
                break;
            case 'rep_ctl_animal':
                if ($("#rango_fecha").prop('checked') && fecha_inicio && fecha_final) {
                    parametros += (parametros ? '&' : '') + 'fecha_inicio=' + fecha_inicio + '&fecha_final=' + fecha_final;
                    genera = true; tipo = 3;
                }
                if ($("#rango_lote").prop('checked') && lote_inicio && lote_final) {
                    parametros += (parametros ? '&' : '') + 'lote_inicio=' + lote_inicio + '&lote_final=' + lote_final;
                    genera = true; tipo = 3;
                }
                if (buscar_cod > 0 && codigo_bovs && codigo_validado) {
                    parametros += (parametros ? '&' : '') + 'cod_bovs=' + codigo_bovs + '&codigo_erete=' + buscar_cod;
                    genera = true; tipo = 3;
                }
                if (!genera) mensaje = 1;
                break;
            case 'rep_ctl_lote':
                if ($("#rango_fecha").prop('checked') && fecha_inicio && fecha_final) {
                    parametros += (parametros ? '&' : '') + 'fecha_inicio=' + fecha_inicio + '&fecha_final=' + fecha_final;
                    genera = true; tipo = 3;
                }
                if ($("#rango_lote").prop('checked') && lote_inicio && lote_final) {
                    parametros += (parametros ? '&' : '') + 'lote_inicio=' + lote_inicio + '&lote_final=' + lote_final;
                    genera = true; tipo = 3;
                }
                parametros += (parametros ? '&' : '') + 'modo=lote';
                if (!genera) mensaje = 1;
                break;
            case 'rep_nacimientos':
                if ($("#rango_fecha").prop('checked') && fecha_inicio && fecha_final) {
                    parametros += (parametros ? '&' : '') + 'fecha_inicio=' + fecha_inicio + '&fecha_final=' + fecha_final;
                    genera = true; tipo = 6;
                }
                if (buscar_cod > 0 && codigo_bovs && codigo_validado) {
                    parametros += (parametros ? '&' : '') + 'cod_bovs=' + codigo_bovs + '&codigo_erete=' + buscar_cod;
                    genera = true; tipo = 6;
                }
                if (!genera) mensaje = 1;
                break;
            case 'rep_grupo_etario':
                genera = true; tipo = 4; break;
            case 'rep_cons_peso':
                if ($("#rango_fecha").prop('checked')) {
                    if (fecha_inicio && fecha_final) {
                        parametros += (parametros ? '&' : '') + 'fecha_inicio=' + fecha_inicio + '&fecha_final=' + fecha_final;
                        genera = true; tipo = 5;
                    } else mensaje = 1;
                }
                if ($("#rango_lote").prop('checked')) {
                    if (lote_inicio && lote_final) {
                        parametros += (parametros ? '&' : '') + 'lote_inicio=' + lote_inicio + '&lote_final=' + lote_final;
                        genera = true; tipo = 5;
                    } else mensaje = 3;
                }
                break;
            default: mensaje = 40;
        }

        if (mensaje > 0) {
            var msg = {
                1: 'Debe seleccionar todos los parametros',
                2: 'Debe completar el rango de fechas',
                3: 'Debe completar el rango de lotes',
                4: 'Debe seleccionar sexo o codigo',
                40: 'Debe seleccionar al menos un filtro'
            };
            $.alert({
                title: 'Parametros incompletos',
                content: msg[mensaje] || 'Verifique',
                confirmButton: 'Entendido',
                confirmButtonClass: 'btn-warning',
                closeIcon: true,
                icon: 'fa fa-exclamation-triangle',
                type: 'orange'
            });
            return;
        }
        if (!genera) return;

        var parametros_2 = parametros ? window.btoa(parametros) : "";
        var urls = {
            1: "../../Reportes/reportes/sovca/rpl.php?%$%=" + parametros_2,
            2: "../../Reportes/reportes/sovca/rsa.php?%$%=" + parametros_2,
            3: "../../Reportes/reportes/sovca/rca.php?%$%=" + parametros_2,
            4: "../../Reportes/reportes/sovca/rge.php?",
            5: "../../Reportes/reportes/sovca/rcp.php?%$%=" + parametros_2,
            6: "../../Reportes/reportes/sovca/rna.php?%$%=" + parametros_2
        };
        var codigos_reporte = { 1: 'rpl', 2: 'rsa', 3: 'rca', 4: 'rge', 5: 'rcp', 6: 'rna' };
        var reporte_codigo = codigos_reporte[tipo] || '';
        var $btn_generar = $("#crear_reporte");
        var texto_btn = $btn_generar.html();

        /*
         * Verificación previa: si los filtros no arrojan registros, se avisa
         * en el propio gestor de reportes y NO se abre una ventana vacía.
         */
        verificando_reporte = true;
        $btn_generar.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Verificando...');

        $.ajax({
                url: '../../Reportes/reportes/sovca/reporte_check.php',
                type: 'GET',
                dataType: 'json',
                cache: false,
                data: {
                    rep: reporte_codigo,
                    '%$%': parametros_2
                }
            })
            .done(function(resp) {
                verificando_reporte = false;
                $btn_generar.prop('disabled', false).html(texto_btn);

                if (!resp || resp.ok !== true) {
                    $.alert({
                        title: 'No se pudo verificar',
                        content: 'No fue posible comprobar si el reporte tiene datos. Intente nuevamente.',
                        confirmButton: 'Entendido',
                        confirmButtonClass: 'btn-warning',
                        closeIcon: true,
                        icon: 'fa fa-exclamation-triangle',
                        type: 'orange'
                    });
                    return;
                }

                if (parseInt(resp.total, 10) <= 0) {
                    $.alert({
                        title: 'Sin datos para mostrar',
                        content: 'No existen registros para los filtros seleccionados. Ajuste los filtros e intente nuevamente.',
                        confirmButton: 'Entendido',
                        confirmButtonClass: 'btn-warning',
                        closeIcon: true,
                        icon: 'fa fa-info-circle',
                        type: 'orange'
                    });
                    return;
                }

                window.open(urls[tipo], '', "titlebars=0, toolbar=0,scrollbars=0,location=0,statusbar=0,menubar=0,resizable=0,width=450,height=550,top=150,left=500");
                resetGestorReportes();
                $("#modal3").modal('hide');
            })
            .fail(function() {
                verificando_reporte = false;
                $btn_generar.prop('disabled', false).html(texto_btn);
                $.alert({
                    title: 'No se pudo verificar',
                    content: 'No fue posible comprobar si el reporte tiene datos. Intente nuevamente.',
                    confirmButton: 'Entendido',
                    confirmButtonClass: 'btn-warning',
                    closeIcon: true,
                    icon: 'fa fa-exclamation-triangle',
                    type: 'orange'
                });
            });
    });

    $("#valida_cod").click(function() {
        var valor = $("#id_cod_bovs").val();
        if (!valor) {
            $("#ms_id_cod_bovs").text('Campo vacio').show();
            setTimeout(function() { $("#ms_id_cod_bovs").text('').hide(); }, 2500);
            codigo_validado = false;
            validarBotonGenerar();
            return;
        }
        var url = "";
        if (tipo_rep === 'rep_ctl_animal')      url = "../../controllers/mcontrolsanitario_controller";
        else if (tipo_rep === 'rep_ser_animal')  url = "../../controllers/mservicios_controller";
        else if (tipo_rep === 'rep_prod_leche')  url = "../../controllers/mprodleche_controller";
        else if (tipo_rep === 'rep_cons_peso')   url = "../../controllers/mconsecutivopeso_controller";
        else if (tipo_rep === 'rep_nacimientos') url = "../../controllers/mnacimientos_controller";
        else return;

        var accion_validacion = (tipo_rep === 'rep_nacimientos')
            ? 'search_rep_nacimientos_madre'
            : 'search_crl_animal';

        $.post(url, {
                action: accion_validacion,
                rgb_animal: valor,
                buscar_cod: buscar_cod
            })
            .done(function(data) {
                var p = $.parseJSON(data);
                if (p.cod_animal != 0) {
                    $("#ms_id_cod_bovs_acep").text('Registro encontrado').show();
                    setTimeout(function() { $("#ms_id_cod_bovs_acep").text('').hide(); }, 2500);
                    codigo_validado = true;
                } else {
                    $("#ms_id_cod_bovs").text('Registro no encontrado').show();
                    setTimeout(function() { $("#ms_id_cod_bovs").text('').hide(); }, 2500);
                    codigo_validado = false;
                }
                validarBotonGenerar();
            }).fail(function() {
                $("#ms_id_cod_bovs").text('Error al validar').show();
                codigo_validado = false;
                validarBotonGenerar();
            });
    });
</script>

<?php include_once("../layouts/pie.php") ?>