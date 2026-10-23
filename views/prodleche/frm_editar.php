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

/* ============================================================
   GRID ESPECÍFICO: Hembra (6) + Producción (6)
   ============================================================ */
@media (min-width: 993px) {
    #grid_prodleche_edit { grid-template-columns: 6fr 6fr; }
}
</style>

<div class="crud-form">

    <div class="message"></div>

    <header class="crud-form__header">
        <span class="crud-form__icon"><i class="fa fa-pencil"></i></span>
        <div>
            <h3 class="crud-form__title">Editar Producción de Leche</h3>
            <p class="crud-form__subtitle">Modifique los datos de la producción diaria de leche</p>
        </div>
        <div class="crud-form__header-actions">
            <button id="exit_top" type="button" class="btn btn-warning">
                <i class="fa fa-reply"></i> Regresar
            </button>
        </div>
    </header>

    <form id="form" role="form" enctype="multipart/form-data">
        <input type="hidden" id="action" value="edit">
        <input type="hidden" id="id_prodleche">

        <div class="crud-form__grid" id="grid_prodleche_edit">

            <!-- ========== CARD 1: Datos de la hembra ========== -->
            <div class="crud-form__card">
                <div class="crud-form__card-head">
                    <i class="fa fa-venus"></i>
                    <h3>Datos de la Hembra</h3>
                </div>
                <div class="crud-form__card-body">

                    <div class="crud-form__field">
                        <label for="cod_animal_h">ID de la Hembra</label>
                        <input class="form-control bbb" id="cod_animal_h" type="text"
                               placeholder="Código de la Hembra"
                               onpaste="return false" readonly="true">
                        <div id='ms_cod_animal'><p></p></div>
                    </div>

                    <div class="crud-form__field">
                        <label for="nombre_h">Nombre de la Hembra</label>
                        <input class="form-control bbb" id="nombre_h" type="text" tabindex="1"
                               placeholder="Nombre de la HEMBRA"
                               onpaste="return false" readonly="true">
                        <div id='ms_nombre'><p></p></div>
                    </div>

                    <div class="crud-form__field">
                        <label for="fecha_prodleche">Fecha de Ordeño</label>
                        <input class="form-control bbb" id="fecha_prodleche" data-date-format="dd-mm-yyyy"
                               placeholder="dia-mes-año" type="text" onpaste="return false"
                               tabindex="10" readonly="true">
                    </div>

                </div>
            </div>

            <!-- ========== CARD 2: Producción de leche ========== -->
            <div class="crud-form__card">
                <div class="crud-form__card-head">
                    <i class="fa fa-tint"></i>
                    <h3>Producción de Leche</h3>
                </div>
                <div class="crud-form__card-body">

                    <div class="crud-form__field">
                        <label for="kg_leche_m">Mañana (Kg Leche)</label>
                        <input class="form-control bbb" id="kg_leche_m" type="text"
                               placeholder="Kg de Leche"
                               onkeypress="return filterFloat(event,this)"
                               onchange="return SumarKgLeche(value)"
                               onpaste="return false" tabindex="16">
                        <div id='ms_kg_leche_m'><p></p></div>
                    </div>

                    <div class="crud-form__field">
                        <label for="kg_leche_t">Tarde (Kg Leche)</label>
                        <input class="form-control bbb" id="kg_leche_t" type="text"
                               placeholder="Kg de Leche"
                               onkeypress="return filterFloat(event,this)"
                               onchange="return SumarKgLeche(value)"
                               onpaste="return false" tabindex="16">
                        <div id='ms_kg_leche_t'><p></p></div>
                    </div>

                    <div class="crud-form__field">
                        <label for="kg_total_leche">Total (Kg Leche)</label>
                        <input class="form-control bbb" id="kg_total_leche" type="text"
                               placeholder="Kg de Leche totales"
                               onchange="return SumarCostoEvento(value)"
                               onpaste="return false" tabindex="16" readonly="true">
                    </div>

                </div>
            </div>

        </div><!-- /.crud-form__grid -->

        <!-- ========== BOTONES DE ACCIÓN ========== -->
        <div class="crud-form__actions">
           
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
   JAVASCRIPT ORIGINAL DE frm_editar.php — SIN CAMBIOS EN LA LÓGICA
   ═══════════════════════════════════════════════════════════════════════════ */
$(document).ready(function() {
    $.post("../../controllers/mprodleche_controller", {
        action: "search",
        id_prodleche: <?php echo intval($cad0); ?>
    }).done(function(data) {
        var parsedJson = $.parseJSON(data);
        $("#id_prodleche").val(parsedJson.id_prodleche);
        $("#cod_animal_h").val(parsedJson.cod_animal_h);
        $("#nombre_h").val(parsedJson.nombre_animal_h);
        $("#fecha_prodleche").val(parsedJson.fecha_prodleche);
        $("#kg_leche_m").val(parsedJson.kg_leche_m);
        $("#kg_leche_t").val(parsedJson.kg_leche_t);
        $("#kg_total_leche").val(parsedJson.kg_total_leche);
    }, "json");
});

$('#limpiar').click(function() {
    BloquearCampos();
    LimpiarCampos();
    document.getElementById("cod_animal_h").disabled = false;
    document.getElementById("buscar").disabled = false;
    document.getElementById("limpiar").disabled = true;
});

$('#fecha_prodleche').datepicker({
    todayBtn: false,
    todayHighlight: true,
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
                action: "edit",
                cod_animal_h: $("#cod_animal_h").val(),
                id_prodleche: $("#id_prodleche").val(),
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

$(function() {
    $("#cod_animal_h").maxLength(6);
    $("#kg_leche_m").maxLength(5);
    $("#kg_leche_t").maxLength(5);
});

function esnumero(e) {
    k = (document.all) ? e.keyCode : e.which;
    if (k == 8 || k == 0 || k == 13) return true;
    patron = /^[0-9]$/;
    n = String.fromCharCode(k);
    if (patron.test(n) == '') {
        document.getElementById('ms_cod_animal').style.display = 'block';
        document.getElementById("ms_cod_animal").innerHTML = 'Use solo números';
        return patron.test(n);
    } else {
        document.getElementById("ms_cod_animal").innerHTML = '';
        return patron.test(n);
    }
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

<?php include_once("../layouts/pie.php") ?>