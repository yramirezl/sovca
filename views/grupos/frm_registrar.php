<?php
include("../../lib/validar_session.php");
ValidaSession("../login");
?>
<?php include("../layouts/constantes.php") ?>
<?php include_once("../layouts/cabecera.php") ?>

<link rel="stylesheet" href="../../plugins/confirma/jquery-confirm.min.css" type="text/css" />
<link rel="stylesheet" href="../../css/crud-form.css" type="text/css" />

<div class="crud-form">

    <div class="message"></div>

    <header class="crud-form__header">
        <span class="crud-form__icon"><i class="fa fa-plus-circle"></i></span>
        <div>
            <h3 class="crud-form__title">Registrar Grupo</h3>
            <p class="crud-form__subtitle">Grupo, área, equipo o dependencia</p>
        </div>
        <div class="crud-form__header-actions">
            <button id="exit_top" type="button" class="btn btn-warning">
                <i class="fa fa-reply"></i> Regresar
            </button>
        </div>
    </header>

    <form id="form" role="form" enctype="multipart/form-data">
        <input type="hidden" id="action" value="add">

        <div class="crud-form__grid">

            <div class="crud-form__card crud-form__card--mini">
                <div class="crud-form__card-head">
                    <i class="fa fa-users"></i>
                    <h3>Datos del Grupo</h3>
                </div>
                <div class="crud-form__card-body">

                    <div class="crud-form__field">
                        <label for="nombre">Nombre</label>
                        <input type="text" class="form-control" id="nombre"
                               placeholder="Ingrese el nombre del grupo, área, equipo o dependencia"
                               onkeypress="return esnombre(event);" onblur="alsalir(this.id)">
                        <div id='ms_nombre'><p></p></div>
                    </div>

                </div>
            </div>

        </div>

        <div class="crud-form__actions">
            <button id="exit" type="button" class="btn btn-warning" style="display:none;">
                <i class="fa fa-fw fa-reply"></i> Regresar
            </button>
            <button id="save" type="button" class="btn btn-success">
                <i class="fa fa-fw fa-save"></i> Guardar
            </button>
        </div>

    </form>

</div>

<script language="JavaScript" src="../../plugins/maxLength/maxLength.js"></script>
<script type="text/javascript" src="../../plugins/confirma/jquery-confirm.min.js"></script>

<script type="text/javascript">
$(document).ready(function() {

    $("#save").click(function() {
        $.post("../../controllers/grupos_controller", {
            action: "add",
            nombre: $("#nombre").val()
        }).done(function(data) {
            var parsedJson = $.parseJSON(data);
            $(".message").html(parsedJson.mensaje);
            if (parsedJson.resultado != 'alert') {
                setTimeout(function() {
                    $(location).attr('href', '../grupos/frm_registrar');
                }, 3000);
            }
        }, "json");
    });

    $("#exit").click(function() {
        $(location).attr('href', '../grupos/');
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

function alsalir(e) {
    var caso1 = document.getElementById(e).value;
    if (caso1.length < 3) {
        document.getElementById('ms_' + e).innerHTML = 'Debe escribir al menos 3 caractéres.';
    } else {
        document.getElementById('ms_' + e).innerHTML = '';
    }
}

$(function() {
    $('#nombre').maxLength(100);
});

function no() {
    setTimeout(function() {
        $(location).attr('href', '../grupos/');
    }, 1000);
}

function si() {
    $.post("../../controllers/grupos_controller", {
        action: "activa",
        id: $("#id").val()
    }).done(function(data) {
        var parsedJson = $.parseJSON(data);
        $(".message").html(parsedJson.mensaje);
        if (parsedJson.resultado == 'ok') {
            $("#nombre").val('');
            setTimeout(function() {
                $(location).attr('href', '../grupos/');
            }, 3000);
        }
    }, "json");
}
</script>

<script type="text/javascript">
$(document).on('click', '#exit_top', function() { $('#exit').trigger('click'); });
(function crudUnlockHighlighter() {
    var seen = new WeakMap();
    function check() {
        var fields = document.querySelectorAll('.crud-form .form-control, .crud-form input.form-control');
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