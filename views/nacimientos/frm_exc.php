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

<div class="crud-form">

    <div class="message"></div>

    <header class="crud-form__header">
        <span class="crud-form__icon"><i class="fa fa-archive"></i></span>
        <div>
            <h3 class="crud-form__title">Desincorporación del Animal</h3>
            <p class="crud-form__subtitle">Registre la baja del ternero del sistema</p>
        </div>
        <div class="crud-form__header-actions">
            <button id="exit_top" type="button" class="btn btn-warning">
                <i class="fa fa-reply"></i> Regresar
            </button>
        </div>
    </header>

    <form id="form" role="form" enctype="multipart/form-data">
        <input type="hidden" id="action" value="edit">
        <input type="hidden" id="id_desincorporacion">

        <div class="crud-form__grid">

            <div class="crud-form__card crud-form__card--compact">
                <div class="crud-form__card-head">
                    <i class="fa fa-info-circle"></i>
                    <h3>Datos de la Desincorporación</h3>
                </div>
                <div class="crud-form__card-body">

                    <div class="crud-form__field">
                        <label for="codigo_ternero">Código del Ternero</label>
                        <input class="form-control bbb" id="codigo_ternero" type="text" tabindex="1"
                               placeholder="Codigo" onpaste="return false" readonly="true">
                        <div id='ms_nombre'><p></p></div>
                    </div>

                    <div class="crud-form__field">
                        <label for="tipo_des">Tipo de Desincorporación</label>
                        <select name="tipo_des" id="tipo_des" class="form-control bbb" tabindex="2">
                            <option></option>
                        </select>
                    </div>

                    <div class="crud-form__row">
                        <div class="crud-form__field">
                            <label for="fecha_desincorporacion">Fecha de Desincorporación</label>
                            <input class="form-control bbb" id="fecha_desincorporacion" data-date-format="dd-mm-yyyy"
                                   placeholder="dia-mes-año" type="text" onpaste="return false"
                                   tabindex="10" readonly="true">
                        </div>
                        <div class="crud-form__field">
                            <label for="causa_descarte_animal">Causa de Descarte</label>
                            <select name="causa_descarte_animal" id="causa_descarte_animal" class="form-control bbb">
                                <option></option>
                            </select>
                        </div>
                    </div>

                    <div class="crud-form__field">
                        <label for="causa_mortalidad_animal">Causa de Mortalidad</label>
                        <select name="causa_mortalidad_animal" id="causa_mortalidad_animal" class="form-control bbb">
                            <option></option>
                        </select>
                    </div>

                    <div class="crud-form__field">
                        <label for="observaciones">Observaciones</label>
                        <textarea class="form-control ccc" id="observaciones" rows="4" placeholder="Observaciones"
                                  onpaste="return true" tabindex="0" autocomplete="off"></textarea>
                        <div id='ms_observaciones' class="aaa"><p></p></div>
                    </div>

                </div>
            </div>

        </div>

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

<script type="text/javascript">
$.post("../../controllers/mcausadescartes_controller", {
    action: "get_causa_descarte_animal"
}).done(function(data) { $("#causa_descarte_animal").html(data); });

$.post("../../controllers/mcausademortalidad_controller", {
    action: "get_causa_mortalidad_animal"
}).done(function(data) { $("#causa_mortalidad_animal").html(data); });

$.post("../../controllers/mestatusganados_controller", {
    action: "get_buscar_estatus"
}).done(function(data) { $("#tipo_des").html(data); });

document.getElementById('causa_descarte_animal').disabled = true;
document.getElementById('causa_mortalidad_animal').disabled = true;
document.getElementById('fecha_desincorporacion').disabled = true;
document.getElementById("observaciones").disabled = true;
document.getElementById("save").disabled = true;

var id_desincorporacion = <?php echo intval($cad0); ?>;
$("#id_desincorporacion").val(id_desincorporacion);

$(document).ready(function() {
    $.post("../../controllers/mnacimientos_controller", {
        action: "search_desinc_nac",
        id_reg_nac: <?php echo intval($cad0); ?>
    }).done(function(data) {
        var parsedJson = $.parseJSON(data);
        $("#id_reg_nac").val(parsedJson.id);
        $("#fichado").val(parsedJson.fichado);
        $("#codigo_ternero").val(parsedJson.codigo_ternero);
        if (parsedJson.fichado == 'FICHADO') {
            $.confirm({
                title: 'Animal FICHADO, proceda por el MODULO de DESINCORPORACION',
                cancelButton: false,
                confirmButton: false,
                closeIcon: false,
                confirmButton: 'cerrar',
                confirmButtonClass: 'btn-success',
                content: false
            });
            setTimeout(function() {
                $(location).attr('href', '../nacimientos/');
            }, 3000);
        }
    }, "json");
});

$("#tipo_des").click(function() {
    document.getElementById('observaciones').disabled = false;
    document.getElementById('save').disabled = true;

    if ($("#tipo_des").val() == '2') {
        document.getElementById('causa_descarte_animal').disabled = false;
        document.getElementById('causa_mortalidad_animal').disabled = true;
        document.getElementById('fecha_desincorporacion').disabled = false;
        document.getElementById('save').disabled = false;
    } else if ($("#tipo_des").val() == '3') {
        document.getElementById('causa_descarte_animal').disabled = true;
        document.getElementById('causa_mortalidad_animal').disabled = false;
        document.getElementById('fecha_desincorporacion').disabled = false;
        document.getElementById('save').disabled = false;
    } else if (($("#tipo_des").val() == '4') || ($("#tipo_des").val() == '5')) {
        document.getElementById('causa_descarte_animal').disabled = true;
        document.getElementById('causa_mortalidad_animal').disabled = true;
        document.getElementById('fecha_desincorporacion').disabled = false;
        document.getElementById('save').disabled = false;
    } else {
        document.getElementById('causa_descarte_animal').disabled = true;
        document.getElementById('causa_mortalidad_animal').disabled = true;
        document.getElementById('fecha_desincorporacion').disabled = true;
    }
});

$('#limpiar').click(function() {
    BloquearCampos();
    LimpiarCampos();
    document.getElementById("cod_animal").disabled = false;
    document.getElementById("buscar").disabled = false;
    document.getElementById("limpiar").disabled = true;
});

$('#modificar').click(function() {
    DesBloquearCampos();
    document.getElementById("modificar").disabled = true;
    document.getElementById("save").disabled = false;
});

$('#fecha_desincorporacion').datepicker({
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
    setTimeout(function() {
        $(location).attr('href', '../nacimientos/');
    }, 1000);
});

$('#save').click(function() {
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
            $.post("../../controllers/mdesincorporacion_controller", {
                action: "add_nac",
                codigo_ternero: $("#codigo_ternero").val(),
                tipo_des: $("#tipo_des").val(),
                fecha_desincorporacion: $("#fecha_desincorporacion").val(),
                causa_descarte_animal: $("#causa_descarte_animal").val(),
                causa_mortalidad_animal: $("#causa_mortalidad_animal").val(),
                observaciones: $("#observaciones").val()
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
                    }, 1500);
                } else {
                    setTimeout(function() {
                        $(".alert").alert('close');
                        document.getElementById('save').disabled = false;
                    }, 1500);
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

function BloquearCampos() {
    document.getElementById("cod_animal").disabled = true;
    document.getElementById("nombre").disabled = true;
    document.getElementById("fecha_desincorporacion").disabled = true;
    document.getElementById("causa_descarte_animal").disabled = true;
    document.getElementById("causa_mortalidad_animal").disabled = true;
    document.getElementById("observaciones").disabled = true;
}

function DesBloquearCampos() {
    document.getElementById("cod_animal").disabled = false;
    document.getElementById("nombre").disabled = true;
    document.getElementById("tipo_des").readonly = false;
    document.getElementById("tipo_des").disabled = false;
    document.getElementById("fecha_desincorporacion").disabled = true;
    document.getElementById("causa_descarte_animal").disabled = true;
    document.getElementById("causa_mortalidad_animal").disabled = true;
    document.getElementById("observaciones").disabled = false;
}

function LimpiarCampos() {
    document.getElementById("cod_animal").value = '';
    document.getElementById("nombre").value = '';
    document.getElementById("tipo_des").value = 0;
    document.getElementById("fecha_desincorporacion").value = '';
    document.getElementById("causa_descarte_animal").value = '';
    document.getElementById("causa_mortalidad_animal").value = '';
    document.getElementById("observaciones").value = '';
}
</script>

<script type="text/javascript">
$(document).on('click', '#exit_top', function() {
    setTimeout(function() { $(location).attr('href', '../nacimientos/'); }, 200);
});
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
            if (l) { l.classList.add('crud-just-unlocked-check-label');
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

<?php include_once("../layouts/pie.php") ?>