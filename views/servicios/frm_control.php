<?php
include("../../lib/validar_session.php");
ValidaSession("../login");

$parametros = base64_decode($_GET["%$%"]);
$valores = explode("=", $parametros);
$cad0 = isset($valores[0]) ? $valores[0] : 0;
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
@media (min-width: 993px) {
    #grid_servicios_control {
        grid-template-columns: 1fr 1fr;
    }
}
</style>

<div class="crud-form">

    <div class="message"></div>

    <header class="crud-form__header">
        <span class="crud-form__icon"><i class="fa fa-medkit"></i></span>
        <div>
            <h3 class="crud-form__title">Control de Servicio</h3>
            <p class="crud-form__subtitle">Registre palpación, gestación, parto y secado</p>
        </div>
        <div class="crud-form__header-actions">
            <button id="exit_top" type="button" class="btn btn-warning">
                <i class="fa fa-reply"></i> Regresar
            </button>
        </div>
    </header>

    <form id="form" role="form" enctype="multipart/form-data">
        <input type="hidden" id="fecha2" value="">
        <input type="hidden" id="action" value="edit_control">
        <input type="hidden" id="id_regservicio">
        <input type="hidden" id="aptitud_h" value=0>

        <div class="crud-form__grid" id="grid_servicios_control">

            <!-- ========== CARD 1: Hembra / Receptora ========== -->
            <div class="crud-form__card">
                <div class="crud-form__card-head">
                    <i class="fa fa-venus"></i>
                    <h3>Hembra / Receptora</h3>
                </div>
                <div class="crud-form__card-body">

                    <div class="crud-form__field">
                        <label>ID SERVICIO
                            <a data-toggle="tooltip" title="Código único y autogenerado para hacer seguimiento a cada servicio.">
                                <img src="../../dist/img/ayuda.jpeg" height="13" width="13">
                            </a>
                        </label>
                        <input type="text" id="codigo_servicio" class="form-control" placeholder="codigo servicio" readonly="true">
                    </div>

                    <div class="crud-form__field">
                        <label for="cod_animal_h">Código de la Hembra</label>
                        <input class="form-control bbb" id="cod_animal_h" type="text" readonly="true">
                        <div id='ms_cod_animal_h'><p></p></div>
                    </div>

                    <div class="crud-form__field">
                        <label for="nombre_h">Nombre de la HEMBRA</label>
                        <input class="form-control bbb" id="nombre_h" type="text" tabindex="1"
                               placeholder="Nombre de la HEMBRA" onpaste="return false" readonly="true">
                        <div id='ms_nombre'><p></p></div>
                    </div>

                    <div class="crud-form__row--3">
                        <div class="crud-form__field">
                            <label>..PG
                                <a data-toggle="tooltip" title="Período de gestación predeterminado o configurado.">
                                    <img src="../../dist/img/ayuda.jpeg" height="13" width="13">
                                </a>
                            </label>
                            <input type="text" id="periodo_gestacion" class="form-control" placeholder="Periodo">
                        </div>
                        <div class="crud-form__field">
                            <label>..PP
                                <a data-toggle="tooltip" title="Período de palpación predeterminado o configurado.">
                                    <img src="../../dist/img/ayuda.jpeg" height="13" width="13">
                                </a>
                            </label>
                            <input type="text" id="periodo_palpacion" class="form-control" placeholder="Periodo">
                        </div>
                        <div class="crud-form__field">
                            <label>..DNP
                                <a data-toggle="tooltip" title="Días no productivos de la hembra.">
                                    <img src="../../dist/img/ayuda.jpeg" height="13" width="13">
                                </a>
                            </label>
                            <input type="text" id="dnp" class="form-control" placeholder="dnp" readonly="true">
                        </div>
                    </div>

                </div>
            </div>

            <!-- ========== CARD 2: Macho ========== -->
            <div class="crud-form__card">
                <div class="crud-form__card-head">
                    <i class="fa fa-mars"></i>
                    <h3>Macho</h3>
                </div>
                <div class="crud-form__card-body">

                    <div class="crud-form__field">
                        <label for="cod_animal_m">Código del MACHO</label>
                        <input class="form-control bbb" id="cod_animal_m" type="text"
                               placeholder="Indique el Código del MACHO (Utilice los ultimos digitos)"
                               onpaste="return false" onkeypress="return esnumero_m(event);">
                        <div id='ms_cod_animal_m'><p></p></div>
                    </div>

                    <div class="crud-form__field">
                        <label for="nombre_m">Nombre del MACHO</label>
                        <input class="form-control bbb" id="nombre_m" type="text" tabindex="1"
                               placeholder="Nombre del MACHO" onpaste="return false" readonly="true">
                        <div id='ms_nnombre_m'><p></p></div>
                    </div>

                </div>
            </div>

            <!-- ========== CARD 3: Datos Palpación ========== -->
            <div class="crud-form__card">
                <div class="crud-form__card-head">
                    <i class="fa fa-stethoscope"></i>
                    <h3>Datos Palpación</h3>
                </div>
                <div class="crud-form__card-body">

                    <div class="crud-form__field">
                        <label for="fecha_servicio">Fecha de Servicio</label>
                        <input class="form-control bbb" id="fecha_servicio" data-date-format="dd-mm-yyyy"
                               placeholder="dia-mes-año" type="text" onpaste="return false"
                               tabindex="10" readonly="true">
                    </div>

                    <div class="crud-form__field">
                        <label for="fecha_fpp">Fecha Prob. de Palpación
                            <a data-toggle="tooltip" title="Indica la fecha probable para realizar la palpación de la hembra.">
                                <img src="../../dist/img/ayuda.jpeg" height="13" width="13">
                            </a>
                        </label>
                        <input class="form-control bbb" id="fecha_fpp" data-date-format="dd-mm-yyyy"
                               placeholder="dia-mes-año" type="text" onpaste="return false"
                               tabindex="10" readonly="true">
                    </div>

                    <div class="crud-form__field">
                        <label for="fecha_fp">Fecha de Palpación
                            <a data-toggle="tooltip" title="Indica la fecha en que se realizó la palpación a la hembra.">
                                <img src="../../dist/img/ayuda.jpeg" height="13" width="13">
                            </a>
                        </label>
                        <input class="form-control bbb" id="fecha_fp" data-date-format="dd-mm-yyyy"
                               placeholder="dia-mes-año" type="text" onpaste="return false"
                               tabindex="10" readonly="true">
                    </div>

                    <div class="crud-form__field">
                        <label for="fecha_fup">Fecha del último Parto
                            <a data-toggle="tooltip" title="Indica la fecha del último parto de la hembra.">
                                <img src="../../dist/img/ayuda.jpeg" height="13" width="13">
                            </a>
                        </label>
                        <input class="form-control bbb" id="fecha_fup" data-date-format="dd-mm-yyyy"
                               placeholder="dia-mes-año" type="text" onpaste="return false"
                               tabindex="10" readonly="true">
                        <div id='ms_fecha_fup'><p></p></div>
                    </div>

                    <div class="crud-form__field">
                        <label for="estado_fisiologico">Estado Fisiológico
                            <a data-toggle="tooltip" title="Indica el estado fisiológico actual de la hembra. Ten en cuenta que, si registras el animal como vacía o aborto, se dará por finalizado el ciclo del servicio.">
                                <img src="../../dist/img/ayuda.jpeg" height="13" width="13">
                            </a>
                        </label>
                        <select name="estado_fisiologico" id="estado_fisiologico" class="form-control bbb">
                            <option></option>
                        </select>
                    </div>

                    <div class="crud-form__field">
                        <label for="fecha_secado">Fecha de Secado</label>
                        <input class="form-control bbb" id="fecha_secado" data-date-format="dd-mm-yyyy"
                               placeholder="dia-mes-año" type="text" onpaste="return false"
                               tabindex="10" readonly="true">
                    </div>

                </div>
            </div>

            <!-- ========== CARD 4: Datos Gestación ========== -->
            <div class="crud-form__card">
                <div class="crud-form__card-head">
                    <i class="fa fa-birthday-cake"></i>
                    <h3>Datos Gestación</h3>
                </div>
                <div class="crud-form__card-body">

                    <div class="crud-form__row">
                        <div class="crud-form__field">
                            <label for="fecha_fppt">Fecha.PP
                                <a data-toggle="tooltip" title="Indica la fecha probable de parto de la hembra.">
                                    <img src="../../dist/img/ayuda.jpeg" height="13" width="13">
                                </a>
                            </label>
                            <input class="form-control bbb" id="fecha_fppt" data-date-format="dd-mm-yyyy"
                                   placeholder="dia-mes-año" type="text" onpaste="return false"
                                   tabindex="10" readonly="true">
                        </div>
                        <div class="crud-form__field">
                            <label for="pario">Parió
                                <a data-toggle="tooltip" title="Con esta acción se cierra el ciclo de servicio y reproducción, y se activa el registro de nacimiento para esta hembra.">
                                    <img src="../../dist/img/ayuda.jpeg" height="13" width="13">
                                </a>
                            </label>
                            <select id="pario" class="btn-sm" tabindex="3">
                                <option value="*">Seleccione</option>
                                <option value="1">SI</option>
                                <option value="2">NO</option>
                            </select>
                        </div>
                    </div>

                    <div class="crud-form__field">
                        <label for="fecha_parto">Fecha del Parto
                            <a data-toggle="tooltip" title="Indica la fecha del parto.">
                                <img src="../../dist/img/ayuda.jpeg" height="13" width="13">
                            </a>
                        </label>
                        <input class="form-control bbb" id="fecha_parto" data-date-format="dd-mm-yyyy"
                               placeholder="dia-mes-año" type="text" onpaste="return false"
                               tabindex="10" readonly="true">
                        <div id='ms_fecha_parto' class="aaa"><p></p></div>
                    </div>

                    <div class="crud-form__field">
                        <label for="nro_terneros">Nro.Terneros
                            <a data-toggle="tooltip" title="Indica la cantidad de terneros que parió la hembra.">
                                <img src="../../dist/img/ayuda.jpeg" height="13" width="13">
                            </a>
                        </label>
                        <select id="nro_terneros" class="btn-sm" tabindex="3">
                            <option value="0">Seleccione</option>
                            <option value="1">1</option>
                            <option value="2">2</option>
                        </select>
                        <div id='ms_nro_terneros' class="aaa"><p></p></div>
                    </div>

                    <div class="crud-form__field">
                        <label for="observaciones">Observaciones</label>
                        <textarea class="form-control ccc" id="observaciones" rows="4" placeholder="Observaciones"
                                  onpaste="return true" tabindex="0" autocomplete="off"></textarea>
                        <div id='ms_observaciones' class="aaa"><p></p></div>
                    </div>

                </div>
            </div>

        </div><!-- /.crud-form__grid -->

        <!-- ========== BOTONES DE ACCIÓN ========== -->
        <div class="crud-form__actions">
            <button id="exit" type="button" class="btn btn-warning" style="display:none;">
                <i class="fa fa-fw fa-reply"></i> Regresar
            </button>
            <button id="modificar" type="button" class="btn btn-primary" tabindex="24">
                <i class="fa fa-fw fa-pencil"></i> EDITAR
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

<script type="text/javascript">
/* ═══════════════════════════════════════════════════════════════════════════
   JAVASCRIPT ORIGINAL — SIN CAMBIOS EN LA LÓGICA
   ═══════════════════════════════════════════════════════════════════════════ */
BloquearCampos();
var suiche = false;
var fup = "";

var control = false;
var fecha_secado_destete = "";

document.getElementById("save").disabled = true;
document.getElementById("estado_fisiologico").disabled = true;
document.getElementById("periodo_palpacion").disabled = true;
document.getElementById("periodo_gestacion").disabled = true;

$(document).ready(function() {

    $("#estado_fisiologico").val(3);
    $.post("../../controllers/mestadofisiologicos_controller", {
        action: "get_estado_fisiologico"
    }).done(function(data) {
        $("#estado_fisiologico").html(data);
    });

    $.post("../../controllers/mperiodos_controller", {
        action: "get_periodo_palpacion_det"
    }).done(function(data) {
        var parsedJson = $.parseJSON(data);
        $("#periodo_palpacion").html(parsedJson.periodo_palpacion);
        $("#periodo_palpacion").val(parsedJson.periodo_palpacion);
    });

    $.post("../../controllers/mperiodos_controller", {
        action: "search_periodo_gestacion_det"
    }).done(function(data) {
        var parsedJson = $.parseJSON(data);
        $("#periodo_gestacion").html(parsedJson.periodo_gestacion);
        $("#periodo_gestacion").val(parsedJson.periodo_gestacion);
    });

    $.post("../../controllers/mservicios_controller", {
        action: "search_control",
        id_regservicio: <?php echo intval($cad0); ?>
    }).done(function(data) {

        var parsedJson = $.parseJSON(data);
        $("#id_regservicio").val(parsedJson.id_regservicio);
        $("#codigo_servicio").val(parsedJson.codigo_servicio);

        $("#cod_animal_h").val(parsedJson.cod_animal_h);
        $("#nombre_h").val(parsedJson.nombre_animal_h);
        $("#cod_animal_m").val(parsedJson.cod_animal_m);
        $("#nombre_m").val(parsedJson.nombre_animal_m);
        $("#fecha_servicio").val(parsedJson.fecha_servicio);
        $("#aptitud_h").val(parsedJson.aptitud_h);
        $("#fecha_fup").val(parsedJson.fecha_fup);
        fup = parsedJson.fecha_fup;
        $("#fecha_fpp").val(parsedJson.fecha_fpp);
        $("#fecha_fp").val(parsedJson.fecha_fp);
        $("#estado_fisiologico").val(parsedJson.estado_fisiologico);
        $("#fecha_fppt").val(parsedJson.fecha_fppt);
        $("#fecha_secado").val(parsedJson.fecha_destete);
        fecha_secado_destete = parsedJson.fecha_destete;
        $("#pario").val(parsedJson.pario);
        $("#fecha_parto").val(parsedJson.fecha_parto);
        $("#nro_terneros").val(parsedJson.nro_terneros);

        var bloqueado = parsedJson.bloquear;
        var fecha_fup = parsedJson.fecha_fup;

        if (bloqueado == 1) {

            document.getElementById('modificar').style.display = 'none';
            document.getElementById('save').style.display = 'none';
            document.getElementById("exit").disabled = true;

            setTimeout(function() {
                $.confirm({
                    title: 'Registro de Sólo Lectura !',
                    cancelButton: false,
                    confirmButton: true,
                    closeIcon: false,
                    confirmButton: 'cerrar',
                    confirmButtonClass: 'btn-success',
                    content: false
                });
                document.getElementById("exit").disabled = false;
            }, 1500);

        } else {

            if (parsedJson.pario == 1) {
                activar_fecha_parto();
            } else {
                inactivar_fecha_parto();
            }

            if (parsedJson.estado_fisiologico == 1) {
                activar_fecha_secado();
            } else {
                inactivar_fecha_secado();
            }

            // Si hay fecha de secado (guardada o calculada) se deja editable:
            // la regla propone la fecha y el usuario puede ajustarla.
            if (parsedJson.fecha_destete != "") {
                document.getElementById("fecha_secado").disabled = false;
                if (parsedJson.secado_mensaje) {
                    $("#fecha_secado").attr('title', parsedJson.secado_mensaje);
                }
            }

            $("#dnp").val(parsedJson.dnp);
            $("#observaciones").val(parsedJson.observaciones);
            control = parsedJson.control;
        }

        if (fecha_fup != "") {
            activar_fecha_secado();
        }

    }, "json");
});

$('#fecha_fp').change(function(event) {
    document.getElementById("estado_fisiologico").disabled = false;
});

$('#pario').change(function(event) {
    var valor = $("#pario").val();

    if (valor == 1) {
        $.confirm({
            title: 'Con esta acción se cerrará el ciclo de Servicio/Reprodcción de la Hembra !',
            cancelButton: false,
            confirmButton: true,
            closeIcon: false,
            confirmButton: 'cerrar',
            confirmButtonClass: 'btn-success',
            content: false
        });

        activar_fecha_parto();
        document.getElementById("nro_terneros").disabled = false;
    } else {
        inactivar_fecha_parto();
        document.getElementById("nro_terneros").disabled = true;
    }
});

function activar_fecha_parto() {
    $('#fecha_parto').show();
    $('label[for="fecha_parto"]').show();
    $('#nro_terneros').show();
    $('label[for="nro_terneros"]').show();
}

function activar_fecha_secado() {
    $('#fecha_secado').show();
    $('label[for="fecha_secado"]').show();
}

function inactivar_fecha_secado() {
    $('#fecha_secado').hide();
    $('label[for="fecha_secado"]').hide();
}

function inactivar_fecha_parto() {
    $('#fecha_parto').hide();
    $('label[for="fecha_parto"]').hide();
}

$('#fecha_fup').change(function(event) {
    var continuar = false;

    if (!suiche) {

        var fecha1 = $("#fecha_servicio").val();
        var fecha2 = $("#fecha_fup").val();

        var array_fecha = fecha1.split("-");
        var dia = Number(array_fecha[0]);
        var mes = Number(array_fecha[1]);
        var ano = Number(array_fecha[2]);

        var array_fecha_2 = fecha2.split("-");
        var dia_2 = Number(array_fecha_2[0]);
        var mes_2 = Number(array_fecha_2[1]);
        var ano_2 = Number(array_fecha_2[2]);

        if (ano > ano_2) {
            continuar = true;
        } else if (ano == ano_2) {
            if (mes > mes_2) {
                continuar = true;
            } else if (mes == mes_2) {
                if (dia > dia_2) {
                    continuar = true;
                } else {
                    continuar = false;
                }
            }
        } else {
            continuar = false;
        }

        if (!continuar) {
            document.getElementById("fecha_fup").disabled = true;
            document.getElementById('ms_fecha_fup').style.display = 'block';
            document.getElementById("ms_fecha_fup").innerHTML =
                'Fecha del ültimo Parto No Puede ser Mayor que Fecha de Servicio';
            msj = "ms_fecha_fup";
            setTimeout(function() {
                document.getElementById(msj).innerHTML = '';
                $("#fecha_fup").val(fup);
                document.getElementById("fecha_fup").disabled = false;
                suiche = false;
            }, 2000);

        } else {

            var valor = $("#estado_fisiologico").val();
            if (valor == '1') {

                var aptitud_h = $("#aptitud_h").val();

                if ((aptitud_h == 2) || (aptitud_h == 3)) {
                    activar_fecha_secado();

                    // La fecha de secado depende del último parto: al cambiarlo
                    // se vuelve a aplicar la regla.
                    CalcularFechaSecado();
                } else {
                    inactivar_fecha_secado();
                }
            } else {
                $("#fecha_fppt").val("");
                $("#dnp").val("");
                inactivar_fecha_secado();
            }
        }
        suiche = true;
    }
});

$('#estado_fisiologico').change(function(event) {
    var valor = $("#estado_fisiologico").val();

    if (valor == 4 || valor == 5) {
        $.confirm({
            title: 'Los estados fisiológicos "ABORTO Y VACIA", cierran el ciclo de este servicio !',
            cancelButton: false,
            confirmButton: true,
            closeIcon: false,
            confirmButton: 'cerrar',
            confirmButtonClass: 'btn-success',
            content: false
        });
    }

    var dias = 0;
    if (valor == '1') {
        dias = calcularfecha();
        $("#dnp").val(dias);

        var fecha_pp = 0;
        var fecha = $("#fecha_servicio").val();
        var fecha_pp = 0;
        var fecha = 0;
        var dias = 0;
        if (dias != 0) {
            dias = dias;
        } else {
            dias = parseInt($("#periodo_gestacion").val());
        }
        fecha = $("#fecha_servicio").val();

        var array_fecha = fecha.split('-');
        var new_fecha = array_fecha[2] + '-' + array_fecha[1] + '-' + array_fecha[0];

        var fecha_pp = new Date(new_fecha);
        var mifecha = sumarDiasGestacion(fecha_pp, dias);

        fecha_pp = formatDate(mifecha);

        $("#fecha_fppt").val(fecha_pp);
        var aptitud_h = $("#aptitud_h").val();

        if ((aptitud_h == 2) || (aptitud_h == 3)) {
            activar_fecha_secado();

            if (fecha_secado_destete == "") {
                CalcularFechaSecado();
            } else {
                $("#fecha_secado").val(fecha_secado_destete);
            }
        } else {
            inactivar_fecha_secado();
        }

    } else {
        $("#fecha_fppt").val("");
        $("#dnp").val("");
        inactivar_fecha_secado();
    }
    document.getElementById("observaciones").disabled = false;
});

function CalcularFechaSecado() {
    $.post("../../controllers/mservicios_controller", {
        action: "calcula_fecha_secado",
        fecha_servicio: $("#fecha_servicio").val(),
        fecha_parto: $("#fecha_fup").val(),
        aptitud_h: $("#aptitud_h").val()
    }).done(function(data) {
        var parsedJson = $.parseJSON(data);
        var mifecha = parsedJson.mifecha || "";
        fecha_secado_destete = mifecha;
        $("#fecha_secado").val(mifecha);
        // Siempre editable: la regla propone la fecha y el usuario puede ajustarla.
        document.getElementById("fecha_secado").disabled = false;
        if (parsedJson.mensaje) {
            $("#fecha_secado").attr('title', parsedJson.mensaje);
        }
    }, "json");
}

$('#fecha_fup').click(function(event) {
    document.getElementById("ms_fecha_fup").innerHTML = '';
});

function sumarDias(fecha, dias) {
    fecha.setDate(fecha.getDate() + dias);
    return fecha;
}

function formatDate(mifecha) {
    var d = mifecha,
        dformat = [padLeft(d.getDate()),
            padLeft(d.getMonth() + 1),
            d.getFullYear()
        ].join('-');
    return dformat;
}

function sumarDiasGestacion(fecha, dias) {
    fecha.setDate(fecha.getDate() + dias);
    return fecha;
}

function padLeft(n) {
    return ("00" + n).slice(-2);
}

$('#limpiar_m').click(function() {
    document.getElementById("cod_animal_m").disabled = false;
    document.getElementById("buscar_m").disabled = false;
    document.getElementById("limpiar_m").disabled = true;
});

$('#modificar').click(function() {
    DesBloquearCampos();
    document.getElementById("save").disabled = false;
    document.getElementById("modificar").disabled = true;
    document.getElementById("estado_fisiologico").disabled = false;

    if (control) {
        document.getElementById("pario").disabled = false;
        var nro = $("#pario").val();
        if (nro == 1) {
            document.getElementById("nro_terneros").disabled = false;
            document.getElementById("fecha_parto").disabled = false;
            activar_fecha_parto();
        }
    }
});

$('#fecha_servicio').datepicker({
    endDate: '+0d',
    todayBtn: false,
    todayHighlight: true,
    autoclose: true,
    language: 'es',
    showOnFocus: true
});
$('#fecha_fup').datepicker({
    endDate: '+0d',
    todayBtn: false,
    todayHighlight: true,
    autoclose: true,
    language: 'es',
    showOnFocus: true
});

$('#fecha_parto').datepicker({
    endDate: '+0d',
    todayBtn: false,
    todayHighlight: true,
    autoclose: true,
    language: 'es',
    showOnFocus: true
});

$('#fecha_fpp').datepicker({
    endDate: '+0d',
    todayBtn: false,
    todayHighlight: true,
    autoclose: true,
    language: 'es',
    showOnFocus: true
});
$('#fecha_secado').datepicker({
    endDate: '+0d',
    todayBtn: false,
    todayHighlight: true,
    autoclose: true,
    language: 'es',
    showOnFocus: true
});
$('#fecha_fp').datepicker({
    endDate: '+0d',
    todayBtn: false,
    todayHighlight: true,
    autoclose: true,
    language: 'es',
    showOnFocus: true
});
$('#fecha_fppt').datepicker({
    endDate: '+0d',
    todayBtn: false,
    todayHighlight: true,
    autoclose: true,
    language: 'es',
    showOnFocus: true
});

$("#derecha").click(function() {
    if (tick == 0) {
        var cont_alert = $('.bbb').filter(function() { return $(this).val() == ""; }).size();
        document.getElementById('derecha').disabled = true;
        $("#derecha").css("display", "none");
        if (cont_alert > 30) {
            $.confirm({
                title: '¡existen campos vacios !',
                cancelButton: false,
                confirmButton: true,
                closeIcon: false,
                confirmButton: 'cerrar',
                confirmButtonClass: 'btn-success',
                content: false
            });
        } else {
            $('.base').unslider('animate:1');
            tick = 1;
            document.getElementById('izquierda').disabled = false;
            $("#izquierda").css("display", "block");
            setTimeout(function() {
                document.body.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 800);
        }
    } else if (tick == 1) {
        var cont_alert3 = $('.ccc').filter(function() { return $(this).val() == ""; }).size();
        if (cont_alert3 > 50) {
            $.confirm({
                title: '¡existen campos vacios !',
                cancelButton: false,
                confirmButton: true,
                closeIcon: false,
                confirmButton: 'cerrar',
                confirmButtonClass: 'btn-success',
                content: false
            });
        } else {
            document.getElementById('derecha').disabled = false;
            $("#derecha").css("display", "block");
            setTimeout(function() {
                document.body.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 800);
        }
    }
});

$("#izquierda").click(function() {
    if (tick == 1) {
        $('.base').unslider('animate:0');
        tick = 0;
        document.getElementById('izquierda').disabled = true;
        $("#izquierda").css("display", "none");
        document.getElementById('derecha').disabled = false;
        $("#derecha").css("display", "block");
        setTimeout(function() {
            document.body.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 800);
    } else if (tick == 2) {
        $('.base').unslider('animate:1');
        tick = 1;
        document.getElementById('derecha').disabled = false;
        $("#derecha").css("display", "block");
        setTimeout(function() {
            document.body.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 800);
    }
});

$("#exit").click(function() {
    $(location).attr('href', '../servicios/');
});
$(document).on('click', '#exit_top', function() {
    window.location.href = '../servicios/';
});
$('#save').click(function() {
    document.getElementById('save').disabled = true;

    if (ValidarCamposVacios()) {
        Guardar_Control();
    }
});

function ValidarCamposVacios() {
    var guardar = false;
    var pario = $("#pario").val();
    var nro_terneros = $("#nro_terneros").val();
    var fecha_parto = $("#fecha_parto").val();

    if (pario == 1) {
        if (nro_terneros == 0) {
            document.getElementById('ms_nro_terneros').style.display = 'block';
            document.getElementById("ms_nro_terneros").innerHTML = 'Indique Nro de Terneros';
            msj = "ms_nro_terneros";
            setTimeout(function() {
                document.getElementById(msj).innerHTML = '';
                document.getElementById('save').disabled = false;
                document.getElementById('nro_terneros').disabled = false;
            }, 2000);
        } else if (fecha_parto == "") {
            document.getElementById('ms_fecha_parto').style.display = 'block';
            document.getElementById("ms_fecha_parto").innerHTML = 'Indique la Fecha de Parto';
            msj = "ms_fecha_parto";
            setTimeout(function() {
                document.getElementById(msj).innerHTML = '';
                document.getElementById('save').disabled = false;
                document.getElementById('fecha_parto').disabled = false;
            }, 2000);
        } else {
            guardar = true;
        }
    } else {
        guardar = true;
    }
    return guardar;
}

function Guardar_Control() {
    document.getElementById('save').disabled = true;
    var ideco2 = parseInt($("#n_accion").val());

    $.confirm({
        title: '¿Está Seguro de Guardar el Registro ?',
        content: false,
        confirmButton: 'Si',
        cancelButton: 'No',
        confirmButtonClass: 'btn-success',
        cancelButtonClass: 'btn-danger',
        confirm: function() {
            $.post("../../controllers/mservicios_controller", {
                action: "edit_control",
                id_regservicio: $("#id_regservicio").val(),
                fecha_fup: $("#fecha_fup").val(),
                fecha_servicio: $("#fecha_servicio").val(),
                fecha_fp: $("#fecha_fp").val(),
                estado_fisiologico: $("#estado_fisiologico").val(),
                fecha_fppt: $("#fecha_fppt").val(),
                dnp: $("#dnp").val(),
                nro_terneros: $("#nro_terneros").val(),
                observaciones: $("#observaciones").val(),
                pario: $("#pario").val(),
                fecha_parto: $("#fecha_parto").val(),
                fecha_secado: $("#fecha_secado").val()
            }).done(function(data) {
                var parsedJson = $.parseJSON(data);
                $(".message").html(parsedJson.mensaje);
                switch (parsedJson.deslizador) {
                    case "1":
                        $('.base').unslider('animate:0');
                        tick = 0;
                        document.getElementById('izquierda').disabled = true;
                        $("#izquierda").css("display", "none");
                        document.getElementById('derecha').disabled = false;
                        $("#derecha").css("display", "block");
                        break;
                    case "2":
                        $('.base').unslider('animate:1');
                        tick = 1;
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
                } else {
                    setTimeout(function() {
                        $(".alert").alert('close');
                        document.getElementById('save').disabled = false;
                    }, 3000);
                }
            }, "json");
        },
        cancel: function() {
            document.getElementById('save').disabled = false;
        }
    });
}

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

function LimpiarCampos() {
    document.getElementById("nombre_h").value = '';
    document.getElementById("cod_animal_h").value = '';
    document.getElementById("cod_animal_m").value = '';
    document.getElementById("nombre_m").value = '';
    document.getElementById("fecha_servicio").value = '';
    document.getElementById("fecha_fup").value = '';
    document.getElementById("tipo_servicio").value = '';
    document.getElementById("fecha_fpp").value = '';
    document.getElementById("fecha_fp").value = '';
    document.getElementById("estado_fisiologico").value = '';
    document.getElementById("fecha_fppt").value = '';
    document.getElementById("pario").value = '';
    document.getElementById("dnp").value = '';
}

function BloquearCampos() {
    document.getElementById("cod_animal_h").disabled = true;
    document.getElementById("cod_animal_m").disabled = true;
    document.getElementById("fecha_servicio").disabled = true;
    document.getElementById("fecha_fup").disabled = true;
    document.getElementById("fecha_fpp").disabled = true;
    document.getElementById("fecha_fp").disabled = true;
    document.getElementById("estado_fisiologico").disabled = true;
    document.getElementById("fecha_fppt").disabled = true;
    document.getElementById("fecha_parto").disabled = true;
    document.getElementById("pario").disabled = true;
    document.getElementById("dnp").disabled = true;
    document.getElementById("nro_terneros").disabled = true;
    document.getElementById("observaciones").disabled = true;
    document.getElementById("fecha_secado").disabled = true;
}

function DesBloquearCampos() {
    document.getElementById("fecha_fup").disabled = false;
    document.getElementById("fecha_fpp").disabled = true;
    document.getElementById("fecha_fp").disabled = false;
    document.getElementById("fecha_fppt").disabled = true;
    document.getElementById("observaciones").disabled = false;
}

function esnumero_h(e) {
    k = (document.all) ? e.keyCode : e.which;
    if (k == 8 || k == 0 || k == 13) return true;
    patron = /^[0-9]$/;
    n = String.fromCharCode(k);
    if (patron.test(n) == '') {
        document.getElementById('ms_cod_animal_h').style.display = 'block';
        document.getElementById("ms_cod_animal_h").innerHTML = 'Use solo números';
        return patron.test(n);
    } else {
        document.getElementById("ms_cod_animal_h").innerHTML = '';
        return patron.test(n);
    }
}

function esnumer_m(e) {
    k = (document.all) ? e.keyCode : e.which;
    if (k == 8 || k == 0 || k == 13) return true;
    patron = /^[0-9]$/;
    n = String.fromCharCode(k);
    if (patron.test(n) == '') {
        document.getElementById('ms_cod_animal_m').style.display = 'block';
        document.getElementById("ms_cod_animal_m").innerHTML = 'Use solo números';
        return patron.test(n);
    } else {
        document.getElementById("ms_cod_animal_m").innerHTML = '';
        return patron.test(n);
    }
}

function calcularfecha() {
    var fecha1 = $("#fecha_fup").val();
    var fecha2 = $("#fecha_servicio").val();
    dias = 0;
    if (fecha1 != "") {
        var aFecha1 = fecha1.split('-');
        var aFecha2 = fecha2.split('-');
        var fFecha1 = Date.UTC(aFecha1[2], aFecha1[1] - 1, aFecha1[0]);
        var fFecha2 = Date.UTC(aFecha2[2], aFecha2[1] - 1, aFecha2[0]);
        var dif = fFecha2 - fFecha1;
        dias = Math.floor(dif / (1000 * 60 * 60 * 24));
    }
    return dias;
}
</script>

<script type="text/javascript">
(function crudUnlockHighlighter() {
    var seen = new WeakMap();
    
    function highlight(el) {
        // Aplica la clase según el tipo de elemento
        if (el.tagName === 'BUTTON') {
            el.classList.add('crud-just-unlocked-btn');
            setTimeout(function() { el.classList.remove('crud-just-unlocked-btn'); }, 2200);
        } else if (el.type === 'checkbox') {
            // Para checkboxes, marcar también el label asociado si existe
            el.classList.add('crud-just-unlocked-check');
            var label = el.closest('label');
            if (label) {
                label.classList.add('crud-just-unlocked-check-label');
                setTimeout(function() { 
                    el.classList.remove('crud-just-unlocked-check');
                    if (label) label.classList.remove('crud-just-unlocked-check-label');
                }, 2200);
            } else {
                setTimeout(function() { el.classList.remove('crud-just-unlocked-check'); }, 2200);
            }
        } else {
            el.classList.add('crud-unlocked');
            setTimeout(function() { el.classList.remove('crud-unlocked'); }, 2200);
        }
    }
    
    function check() {
        var fields = document.querySelectorAll('.crud-form input, .crud-form select, .crud-form textarea, .crud-form button');
        Array.prototype.forEach.call(fields, function (el) {
            var wasDisabled = seen.get(el);
            var isDisabled = el.disabled;
            if (wasDisabled === true && isDisabled === false) {
                highlight(el);
            }
            seen.set(el, isDisabled);
        });
    }
    
    document.addEventListener('DOMContentLoaded', function () {
        check();
        var observer = new MutationObserver(function (mutations) {
            var touched = false;
            mutations.forEach(function (m) {
                if (m.type === 'attributes' && m.attributeName === 'disabled') touched = true;
            });
            if (touched) check();
        });
        observer.observe(document.body, { attributes: true, attributeFilter: ['disabled'], subtree: true });
    });
})();
</script>

<?php include_once("../layouts/pie.php") ?>