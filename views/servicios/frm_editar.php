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
    #grid_servicios_editar {
        grid-template-columns: 1fr 1fr 1.4fr;
    }
}
</style>

<div class="crud-form">

    <div class="message"></div>

    <header class="crud-form__header">
        <span class="crud-form__icon"><i class="fa fa-pencil-square-o"></i></span>
        <div>
            <h3 class="crud-form__title">Editar Servicio</h3>
            <p class="crud-form__subtitle">Modifique los datos del servicio seleccionado</p>
        </div>
        <div class="crud-form__header-actions">
            <button id="exit_top" type="button" class="btn btn-warning">
                <i class="fa fa-reply"></i> Regresar
            </button>
        </div>
    </header>

    <form id="form" role="form" enctype="multipart/form-data">
        <input type="hidden" id="periodo_palpacion" value="">
        <input type="hidden" id="action" value="edit">
        <input type="hidden" id="id_regservicio">
        <input type="hidden" id="activa_edit">

        <div class="crud-form__grid" id="grid_servicios_editar">

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
                        <input class="form-control bbb" id="cod_animal_h" type="text"
                               placeholder="Indique el Código de la Hembra (Utilice los ultimos digitos)"
                               onpaste="return false" onkeypress="return esnumero_h(event);">
                        <div id='ms_cod_animal_h'><p></p></div>
                    </div>

                    <div class="crud-form__field">
                        <label for="nombre_h">Nombre de la HEMBRA</label>
                        <input class="form-control bbb" id="nombre_h" type="text" tabindex="1"
                               placeholder="Nombre de la HEMBRA" onpaste="return false" readonly="true">
                        <div id='ms_nombre'><p></p></div>
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

            <!-- ========== CARD 3: Datos del Servicio ========== -->
            <div class="crud-form__card">
                <div class="crud-form__card-head">
                    <i class="fa fa-calendar"></i>
                    <h3>Datos del Servicio</h3>
                </div>
                <div class="crud-form__card-body">

                    <div class="crud-form__row">
                        <div class="crud-form__field">
                            <label for="tipo_servicio">Tipo de Servicio</label>
                            <select name="tipo_servicio" id="tipo_servicio" class="form-control bbb">
                                <option></option>
                            </select>
                        </div>
                        <div class="crud-form__field">
                            <label for="fecha_servicio">Fecha de Servicio</label>
                            <input class="form-control bbb" id="fecha_servicio" data-date-format="dd-mm-yyyy"
                                   placeholder="dia-mes-año" type="text" onpaste="return false"
                                   tabindex="10" readonly="true">
                            <div id='ms_fecha_servicio'></div>
                        </div>
                    </div>

                    <div class="crud-form__row">
                        <div class="crud-form__field">
                            <label for="fecha_fpp">Fecha Prob. de Palpación
                                <a data-toggle="tooltip" title="Indica la fecha probable para realizar la palpación.">
                                    <img src="../../dist/img/ayuda.jpeg" height="13" width="13">
                                </a>
                            </label>
                            <input class="form-control bbb" id="fecha_fpp" data-date-format="dd-mm-yyyy"
                                   placeholder="dia-mes-año" type="text" onpaste="return false"
                                   tabindex="10" readonly="true">
                        </div>
                        <div class="crud-form__field">
                            <label for="nro_servicio">Nro de Servicio
                                <a data-toggle="tooltip" title="Número de servicio que se está implementando en este período de gestación.">
                                    <img src="../../dist/img/ayuda.jpeg" height="13" width="13">
                                </a>
                            </label>
                            <select id="nro_servicio" class="btn-sm" tabindex="3">
                                <option value="0">Seleccione</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                            </select>
                        </div>
                    </div>

                    <div class="crud-form__row">
                        <div class="crud-form__field">
                            <label for="raza_animal">Raza Esperada</label>
                            <select name="raza_animal" id="raza_animal" class="form-control bbb" tabindex="2">
                                <option></option>
                            </select>
                        </div>
                        <div class="crud-form__field">
                            <label for="aptitud_animal">Aptitud Esperada</label>
                            <select name="aptitud_animal" id="aptitud_animal" class="form-control bbb" tabindex="7">
                                <option></option>
                            </select>
                            <div id='ms_aptitud_animal'></div>
                        </div>
                    </div>

                    <div class="crud-form__field">
                        <label for="observaciones">Observaciones</label>
                        <textarea class="form-control ccc" id="observaciones" rows="2" placeholder="Observaciones"
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
document.getElementById("nro_servicio").disabled = true;

BloquearCampos();

$(document).ready(function() {
    $('#nro_servicio').hide();
    $('label[for="nro_servicio"]').hide();

    $.post("../../controllers/mtiposervicios_controller", {
        action: "get_servicio_animal"
    }).done(function(data) { $("#tipo_servicio").html(data); });

    $.post("../../controllers/maptitudes_controller", {
        action: "get_aptitud"
    }).done(function(data) { $("#aptitud_animal").html(data); });

    $.post("../../controllers/mrazas_controller", {
        action: "get_razas"
    }).done(function(data) { $("#raza_animal").html(data); });

    $.post("../../controllers/mperiodos_controller", {
        action: "get_periodo_palpacion_det"
    }).done(function(data) {
        var parsedJson = $.parseJSON(data);
        $("#periodo_palpacion").html(parsedJson.periodo_palpacion);
        $("#periodo_palpacion").val(parsedJson.periodo_palpacion);
    });

    $.post("../../controllers/mservicios_controller", {
        action: "search",
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
        $("#fecha_fpp").val(parsedJson.fecha_fpp);
        $("#tipo_servicio").val(parsedJson.tipo_servicio);
        $("#aptitud_animal").val(parsedJson.aptitud_animal);
        $("#nro_servicio").val(parsedJson.nro_servicio);
        $("#raza_animal").val(parsedJson.raza_animal);
        $("#observaciones").val(parsedJson.observaciones);
        $("#activa_edit").val(parsedJson.activa_edit);

        var bloqueado = parsedJson.bloquear;
        var act_edit = parsedJson.activa_edit;

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
            if (act_edit) {
                $('#nro_servicio').show();
                $('label[for="nro_servicio"]').show();
            }
        }
    }, "json");
});

$('#fecha_servicio').datepicker({
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
    var ideco2 = parseInt($("#n_accion").val());

    if (ValidarCamposVacios()) {
        $.confirm({
            title: '¿Está Seguro de Guardar el Registro ?',
            content: false,
            confirmButton: 'Si',
            cancelButton: 'No',
            confirmButtonClass: 'btn-success',
            cancelButtonClass: 'btn-danger',
            confirm: function() {
                $.post("../../controllers/mservicios_controller", {
                    action: "edit",
                    id_regservicio: $("#id_regservicio").val(),
                    fecha_servicio: $("#fecha_servicio").val(),
                    fecha_fpp: $("#fecha_fpp").val(),
                    nro_servicio: $("#nro_servicio").val(),
                    tipo_servicio: $("#tipo_servicio").val(),
                    aptitud_animal: $("#aptitud_animal").val(),
                    raza_animal: $("#raza_animal").val(),
                    observaciones: $("#observaciones").val(),
                    activa_edit: $("#activa_edit").val()
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
                    }
                }, "json");
            },
            cancel: function() {
                document.getElementById('save').disabled = false;
            }
        });
    }
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

function BloquearCampos() {
    document.getElementById("cod_animal_h").disabled = true;
    document.getElementById("cod_animal_m").disabled = true;
    document.getElementById("nombre_m").disabled = true;
    document.getElementById("fecha_servicio").disabled = true;
    document.getElementById("tipo_servicio").disabled = true;
    document.getElementById("aptitud_animal").disabled = true;
    document.getElementById("nro_servicio").disabled = true;
    document.getElementById("raza_animal").disabled = true;
    document.getElementById("save").disabled = true;
    document.getElementById("observaciones").disabled = true;
}

function DesBloquearCampos() {
    document.getElementById("fecha_servicio").disabled = false;
    document.getElementById("tipo_servicio").disabled = false;
    document.getElementById("aptitud_animal").disabled = false;
    document.getElementById("raza_animal").disabled = false;
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

function ValidarCamposVacios() {
    var guardar = false;
    var cod_animal_h = $("#cod_animal_h").val();
    var cod_animal_m = $("#cod_animal_m").val();
    var fecha_servicio = $("#fecha_servicio").val();
    var nro_servicio = $("#nro_servicio").val();
    var tipo_servicio = $("#tipo_servicio").val();
    var aptitud_animal = $("#aptitud_animal").val();
    if (cod_animal_h == "") {
        document.getElementById('ms_cod_animal_h').style.display = 'block';
        document.getElementById("ms_cod_animal_h").innerHTML = 'Este Campo es Obligatorio';
    } else if (cod_animal_m == "") {
        document.getElementById('ms_cod_animal_m').style.display = 'block';
        document.getElementById("ms_cod_animal_m").innerHTML = 'Este Campo es Obligatorio';
    } else if (fecha_servicio == "") {
        document.getElementById('ms_fecha_servicio').style.display = 'block';
        document.getElementById("ms_fecha_servicio").innerHTML = 'Este Campo es Obligatorio';
    } else {
        guardar = true;
    }
    return guardar;
}

$('#modificar').click(function() {
    DesBloquearCampos();
    document.getElementById("save").disabled = false;
    document.getElementById("modificar").disabled = true;

    var editar = $("#activa_edit").val();
    if (editar) {
        document.getElementById("nro_servicio").disabled = false;
    }
});

$('#fecha_servicio').change(function(event) {
    var fecha_pp = 0;
    var fecha = 0;
    var dias = 0;
    if (dias != 0) {
        dias = dias;
    } else {
        dias = parseInt($("#periodo_palpacion").val());
    }
    fecha = $("#fecha_servicio").val();

    var array_fecha = fecha.split('-');
    var new_fecha = array_fecha[2] + '-' + array_fecha[1] + '-' + array_fecha[0];

    var fecha_pp = new Date(new_fecha);
    var mifecha = sumarDias(fecha_pp, dias);
    fecha_pp = formatDate(mifecha);

    $("#fecha_fpp").val(fecha_pp);
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