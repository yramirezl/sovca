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
        <span class="crud-form__icon"><i class="fa fa-pencil-square-o"></i></span>
        <div>
            <h3 class="crud-form__title">Editar Raza</h3>
            <p class="crud-form__subtitle">Modifique los datos de la raza seleccionada</p>
        </div>
        <div class="crud-form__header-actions">
            <button id="exit_top" type="button" class="btn btn-warning">
                <i class="fa fa-reply"></i> Regresar
            </button>
        </div>
    </header>

    <form id="form" role="form" enctype="multipart/form-data">
        <input type="hidden" id="action" value="edit">
        <input type="hidden" id="id_raza">

        <div class="crud-form__grid">

            <div class="crud-form__card crud-form__card--mini">
                <div class="crud-form__card-head">
                    <i class="fa fa-tags"></i>
                    <h3>Datos de la Raza</h3>
                </div>
                <div class="crud-form__card-body">

                    <div class="crud-form__field">
                        <label for="pais">País</label>
                        <select name="pais" id="pais" class="form-control bbb" tabindex="5">
                            <option></option>
                        </select>
                    </div>

                    <div class="crud-form__field">
                        <label for="nombre_raza">Nombre</label>
                        <input type="text" class="form-control" id="nombre_raza"
                               onkeypress="return es_nombre_raza(event);" onblur="alsalir(this.id)">
                        <div id='ms_nombre_raza'><p></p></div>
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

</div>

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
$(document).ready(function() {

    $.post("../../controllers/mrazas_controller", {
        action: "search",
        id_raza: <?php echo intval($cad0); ?>
    }).done(function(data) {
        var parsedJson = $.parseJSON(data);
        $("#id_raza").val(parsedJson.id);
        $("#pais").val(parsedJson.pais);
        $("#nombre_raza").val(parsedJson.nombre);
    }, "json");

});

$.post("../../controllers/mgeograficas_controller", {
    action: "get_paises"
}).done(function(data) {
    $("#pais").html(data);
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
    $.confirm({
        title: '¿Desea Salir?',
        content: false,
        confirmButton: 'Si',
        cancelButton: 'No',
        confirmButtonClass: 'btn-success',
        cancelButtonClass: 'btn-danger',
        confirm: function() {
            setTimeout(function() {
                $(location).attr('href', '../razas/');
            }, 1000);
        },
        cancel: function() {}
    });
});

$("#save").click(function() {
    $.post("../../controllers/mrazas_controller", {
        action: $("#action").val(),
        id_raza: $("#id_raza").val(),
        pais: $("#pais").val(),
        nombre_raza: $("#nombre_raza").val()
    }).done(function(data) {
        var parsedJson = $.parseJSON(data);
        $(".message").html(parsedJson.mensaje);
        if (parsedJson.resultado != 'error') {
            $('.base').unslider('animate:0');
            setTimeout(function() {
                $(location).attr('href', '../razas/');
            }, 3000);
        } else {
            setTimeout(function() {
                $(".alert").alert('close');
            }, 1500);
        }
    }, "json");
});

function es_nombre_raza(e) {
    k = (document.all) ? e.keyCode : e.which;
    if (k == 8 || k == 0 || k == 13) return true;
    patron = /^[a-zñA-ZÑ0-9\ \.\/\-]$/;
    n = String.fromCharCode(k);
    if (patron.test(n) == '') {
        document.getElementById('ms_nombre_raza').style.display = 'block';
        document.getElementById("ms_nombre_raza").innerHTML = 'No Utilice caracteres especiales.';
        return patron.test(n);
    } else {
        document.getElementById("ms_nombre_raza").innerHTML = '';
        return patron.test(n);
    }
}
</script>

<script type="text/javascript">
$(document).on('click', '#exit_top', function() { $('#exit').trigger('click'); });
(function crudUnlockHighlighter() {
    var seen = new WeakMap();
    function check() {
        var fields = document.querySelectorAll('.crud-form .form-control, .crud-form select.form-control, .crud-form input.form-control');
        Array.prototype.forEach.call(fields, function (el) {
            var wasDisabled = seen.get(el);
            var isDisabled = el.disabled;
            if (wasDisabled === true && isDisabled === false) {
                el.classList.add('crud-unlocked');
                setTimeout(function () { el.classList.remove('crud-unlocked'); }, 1100);
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