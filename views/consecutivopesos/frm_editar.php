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
/* ============================================================
   FEEDBACK VISUAL DE DESBLOQUEO
   ============================================================ */
@keyframes crud-flash-input {
    0%   { box-shadow: 0 0 0 0 rgba(22, 163, 74, .75); background-color: #dcfce7; border-color: #16a34a; }
    30%  { box-shadow: 0 0 0 10px rgba(22, 163, 74, 0); background-color: #f0fdf4; border-color: #16a34a; }
    70%  { box-shadow: 0 0 0 0 rgba(22, 163, 74, .35); background-color: #ffffff; }
    100% { box-shadow: 0 0 0 0 rgba(22, 163, 74, 0); background-color: inherit; }
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
    0%   { transform: scale(1); box-shadow: 0 0 0 0 rgba(22, 163, 74, .65); }
    18%  { transform: scale(1.08); box-shadow: 0 0 0 12px rgba(22, 163, 74, 0); }
    45%  { transform: scale(1); box-shadow: 0 0 0 0 rgba(22, 163, 74, .45); }
    70%  { transform: scale(1.05); box-shadow: 0 0 0 10px rgba(22, 163, 74, 0); }
    100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(22, 163, 74, 0); }
}
.crud-form button.crud-just-unlocked-btn,
.crud-form .btn.crud-just-unlocked-btn {
    animation: crud-flash-btn 4.8s ease !important;
    outline: 3px solid rgba(22, 163, 74, .60) !important;
    outline-offset: 3px !important;
    position: relative; z-index: 2;
}
</style>

<div class="crud-form">

    <div class="message"></div>

    <header class="crud-form__header">
        <span class="crud-form__icon"><i class="fa fa-pencil-square-o"></i></span>
        <div>
            <h3 class="crud-form__title">Editar Peso</h3>
            <p class="crud-form__subtitle">Modifique los datos del pesaje seleccionado</p>
        </div>
        <div class="crud-form__header-actions">
            <button id="exit_top" type="button" class="btn btn-warning">
                <i class="fa fa-reply"></i> Regresar
            </button>
        </div>
    </header>

    <form id="form" role="form" enctype="multipart/form-data">
        <input type="hidden" id="id_consecpeso">

        <div class="crud-form__grid">

            <div class="crud-form__card crud-form__card--compact">
                <div class="crud-form__card-head">
                    <i class="fa fa-balance-scale"></i>
                    <h3>Edición de Peso</h3>
                </div>
                <div class="crud-form__card-body">

                    <div class="crud-form__field">
                        <label for="cod_animal">N°. Arete-Chip / Código</label>
                        <input class="form-control bbb" id="cod_animal" type="text"
                               placeholder="Indique el Código o Nro Arete de la Hembra"
                               onpaste="return true"
                               onkeypress="return es_codigo_nro_arete(event);">
                        <div id='ms_cod_animal'><p></p></div>
                    </div>

                    <div class="crud-form__field">
                        <label for="nombre_animal">Nombre del Animal</label>
                        <input class="form-control bbb" id="nombre_animal" type="text" tabindex="1"
                               placeholder="Nombre del ANIMAL" onpaste="return false" readonly="true">
                        <div id='ms_nombre'><p></p></div>
                    </div>

                    <div class="crud-form__field">
                        <label for="fecha_pesaje">Fecha del Pesaje</label>
                        <input class="form-control bbb" id="fecha_pesaje" data-date-format="dd-mm-yyyy"
                               placeholder="dia-mes-año" type="text" onpaste="return false"
                               tabindex="10" readonly="true">
                    </div>

                    <div class="crud-form__field">
                        <label for="peso">Peso (Kg)</label>
                        <input class="form-control bbb" id="peso" type="text" placeholder="Peso del Animal en KG"
                               onkeypress="return filterFloat(event,this)" onpaste="return false" tabindex="16">
                        <div id='ms_peso'><p></p></div>
                    </div>

                </div>
            </div>

        </div>

        <div class="crud-form__actions">
            <button id="exit" type="button" class="btn btn-warning" style="display:none;">
                <i class="fa fa-fw fa-reply"></i> Volver
            </button>
            <button id="modificar" type="button" class="btn btn-primary" tabindex="10">
                <i class="fa fa-fw fa-pencil"></i> Editar
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

$(document).ready(function() {
    $.post("../../controllers/mconsecutivopeso_controller", {
        action: "search",
        id_consecpeso: <?php echo intval($cad0); ?>
    }).done(function(data) {
        var parsedJson = $.parseJSON(data);
        $("#id_consecpeso").val(parsedJson.id_consecpeso);
        $("#cod_animal").val(parsedJson.cod_animal);
        $("#nombre_animal").val(parsedJson.nombre_animal);
        $("#fecha_pesaje").val(parsedJson.fecha_pesaje);
        $("#peso").val(parsedJson.peso);
    }, "json");
});

$('#fecha_pesaje').datepicker({
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
            $.confirm({ title: '¡existen campos vacios !', cancelButton: false, confirmButton: true, closeIcon: false, confirmButton: 'cerrar', confirmButtonClass: 'btn-success', content: false });
        } else {
            $('.base').unslider('animate:1'); tick = 1;
            document.getElementById('izquierda').disabled = false;
            $("#izquierda").css("display", "block");
            setTimeout(function() { document.body.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 800);
        }
    } else if (tick == 1) {
        var cont_alert3 = $('.ccc').filter(function() { return $(this).val() == ""; }).size();
        if (cont_alert3 > 50) {
            $.confirm({ title: '¡existen campos vacios !', cancelButton: false, confirmButton: true, closeIcon: false, confirmButton: 'cerrar', confirmButtonClass: 'btn-success', content: false });
        } else {
            document.getElementById('derecha').disabled = false;
            $("#derecha").css("display", "block");
            setTimeout(function() { document.body.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 800);
        }
    }
});

$("#izquierda").click(function() {
    if (tick == 1) {
        $('.base').unslider('animate:0'); tick = 0;
        document.getElementById('izquierda').disabled = true;
        $("#izquierda").css("display", "none");
        document.getElementById('derecha').disabled = false;
        $("#derecha").css("display", "block");
        setTimeout(function() { document.body.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 800);
    } else if (tick == 2) {
        $('.base').unslider('animate:1'); tick = 1;
        document.getElementById('derecha').disabled = false;
        $("#derecha").css("display", "block");
        setTimeout(function() { document.body.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 800);
    }
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
            setTimeout(function() { $(location).attr('href', '../consecutivopesos/'); }, 1000);
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
            $.post("../../controllers/mconsecutivopeso_controller", {
                action: "edit",
                id_consecpeso: $("#id_consecpeso").val(),
                fecha_pesaje: $("#fecha_pesaje").val(),
                peso: $("#peso").val(),
                cod_animal: $("#cod_animal").val()
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
                    setTimeout(function() { $(location).attr('href', './'); }, 1500);
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

$('#modificar').click(function() {
    DesBloquearCampos();
    document.getElementById("modificar").disabled = true;
    Edit_Reg = true;
});

function BloquearCampos() {
    document.getElementById("nombre_animal").disabled = true;
    document.getElementById("fecha_pesaje").disabled = true;
    document.getElementById("peso").disabled = true;
    document.getElementById('cod_animal').disabled = true;
    document.getElementById('save').disabled = true;
}

function DesBloquearCampos() {
    document.getElementById("fecha_pesaje").disabled = false;
    document.getElementById("peso").disabled = false;
    document.getElementById("save").disabled = false;
}
</script>

<script type="text/javascript">
$(document).on('click', '#exit_top', function() { $('#exit').trigger('click'); });
(function crudUnlockHighlighter() {
    var seen = new WeakMap();
    var DURATION = 4800;
    function highlight(el) {
        if (el.tagName === 'BUTTON') {
            el.classList.add('crud-just-unlocked-btn');
            setTimeout(function () { el.classList.remove('crud-just-unlocked-btn'); }, DURATION); return;
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