<?php

include("../../lib/validar_session.php");
ValidaSession("../login");
VerificarAdmin($_SESSION['rolx']);

?>
<?php include("../layouts/constantes.php") ?>
<?php include_once("../layouts/cabecera.php") ?>

<link rel="stylesheet" href="../../css/crud-form.css" type="text/css" />
<link rel="stylesheet" href="../../css/style_format_pass.css" type="text/css" />
<link rel="stylesheet" href="../../plugins/datepicker/datepicker3.css" type="text/css" />
<link rel="stylesheet" href="../../plugins/confirma/jquery-confirm.min.css" type="text/css" />

<div class="crud-form">

    <div class="message"></div>

    <header class="crud-form__header">
        <span class="crud-form__icon"><i class="fa fa-plus-circle"></i></span>
        <div>
            <h3 class="crud-form__title">Registrar mensaje externo / promoción</h3>
            <p class="crud-form__subtitle">Crea una nueva promoción que se enviará por correo a los contactos.</p>
        </div>
        <div class="crud-form__header-actions">
            <button id="exit_top" type="button" class="btn btn-warning">
                <i class="fa fa-reply"></i> Regresar
            </button>
        </div>
    </header>

    <form id="form" role="form" enctype="multipart/form-data">

        <div class="crud-form__grid">

            <div class="crud-form__card">
                <div class="crud-form__card-head">
                    <i class="fa fa-comment"></i>
                    <h3>Datos del mensaje</h3>
                </div>
                <div class="crud-form__card-body">

                    <div class="crud-form__row">
                        <div class="crud-form__field">
                            <label for="tipo_mensaje">Tipo de Mensaje</label>
                            <select name="tipo_mensaje" id="tipo_mensaje" class="form-control bbb">
                                <option></option>
                            </select>
                        </div>
                        <div class="crud-form__field">
                            <label for="tipo_ganaderia">Tipo de Ganaderia</label>
                            <select name="tipo_ganaderia" id="tipo_ganaderia" class="form-control bbb">
                                <option></option>
                            </select>
                        </div>
                    </div>

                    <div class="crud-form__field">
                        <label for="titulo_mensaje">Titulo del Mensaje</label>
                        <input class="form-control bbb" id="titulo_mensaje" tabindex="1" type="text"
                            placeholder="Titulo del Mensaje" onpaste="return false">
                        <div style="background-color:#F39C12;color:#fff;text-align:center;margin-top:6px;border-radius:6px;" id='ms_titulo_mensaje'>
                            <p></p>
                        </div>
                    </div>

                    <div class="crud-form__field">
                        <label for="cuerpo_mensaje">Descripción del Mensaje</label>
                        <textarea class="form-control ccc" id="cuerpo_mensaje" tabindex="8" rows="6"
                            placeholder="Descripción del Mensaje" onpaste="return true"
                            onkeypress="return ms_direccion(event)" onblur="alsalir(this.id);"
                            autocomplete="off"></textarea>
                        <div style="background-color:#F39C12;color:#fff;text-align:center;margin-top:6px;border-radius:6px;" id='ms_cuerpo_mensaje' class="aaa">
                            <p></p>
                        </div>
                    </div>

                </div>
            </div>

            <div class="crud-form__card">
                <div class="crud-form__card-head">
                    <i class="fa fa-image"></i>
                    <h3>Detalles de la Promoción</h3>
                </div>
                <div class="crud-form__card-body">

                    <div class="crud-form__field">
                        <label for="InputFile">Seleccione la Imagen con la Promoción</label>
                        <div style="text-align:center; margin:8px 0;">
                            <img class="imgusr" alt="" src="#" id="file_url" width="180" height="180"
                                 style="border-radius:8px;border:1px solid #e3efe7;box-shadow:0 2px 8px rgba(35,78,53,.08);object-fit:cover;">
                        </div>
                        <input type="file" id="InputFile" tabindex="8">
                        <small style="color:#888;display:block;margin-top:4px;">Adjuntar imagen no mayor a 1 MB.</small>
                    </div>

                </div>
            </div>

        </div><!-- /.crud-form__grid -->

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
<link rel="stylesheet" href="../../plugins/unslider/unslider.css">
<link rel="stylesheet" href="../../plugins/leaflet/leaflet.css" />
<link rel="stylesheet" href="../../plugins/select3/bootstrap-select.min.css">

<script type="text/javascript">
var msj = "";

$.post("../../controllers/mtipofundos_controller", { action: "get_tipo_ganaderia" })
    .done(function(data) { $("#tipo_ganaderia").html(data); });

$.post("../../controllers/malertas_controller", { action: "get_tipo_mensaje" })
    .done(function(data) { $("#tipo_mensaje").html(data); });

$("#InputFile").change(function() {
    if (validarImagen()) { readURL(this); }
});

function validarImagen() {
    var fileSize = $('#InputFile')[0].files[0].size;
    var siezekiloByte = parseInt(fileSize / 1024);
    if (siezekiloByte > 1024) {
        $('#InputFile').val("");
        $.confirm({ title: 'La imagen es muy grande', content: false, confirmButtonClass: 'none', cancelButtonClass: 'btn-success', cancel: function() {} });
        return false;
    } else { return true; }
}

function readURL(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) { $('#file_url').attr('src', e.target.result); };
        reader.readAsDataURL(input.files[0]);
    }
}

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
        title: '¿Desea Salir?', content: false,
        confirmButton: 'Si', cancelButton: 'No',
        confirmButtonClass: 'btn-success', cancelButtonClass: 'btn-danger',
        confirm: function() { setTimeout(function() { $(location).attr('href', '../mensajesext/'); }, 1000); },
        cancel: function() {}
    });
});

$('#fecha_caducidad').datepicker({
    todayBtn: false, todayHighlight: true, autoclose: true,
    language: 'es', showOnFocus: true, startDate: '+0d'
});

$("#save").click(function() {
    var formData = new FormData();
    formData.append('file', $('input[type=file]')[0].files[0]);
    formData.append('action', 'add_msj_ext');
    formData.append('tipo_ganaderia', $("#tipo_ganaderia").val());
    formData.append('tipo_mensaje', $("#tipo_mensaje").val());
    formData.append('titulo_mensaje', $("#titulo_mensaje").val());
    formData.append('cuerpo_mensaje', $("#cuerpo_mensaje").val());

    $.ajax({
        url: "../../controllers/malertas_controller",
        type: "POST", data: formData,
        contentType: false, cache: false, processData: false,
        success: function(data) {
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
            }
        }
    });
});

function esnombre(e) {
    k = (document.all) ? e.keyCode : e.which;
    if (k == 8 || k == 0 || k == 13) return true;
    patron = /[A-Z\,\  ]/; n = String.fromCharCode(k);
    if (patron.test(n) == '') { document.getElementById('ms_nombre').style.display = 'block'; document.getElementById("ms_nombre").innerHTML = 'Utilice solo letras mayusculas.'; return patron.test(n); }
    else { document.getElementById("ms_nombre").innerHTML = ''; return patron.test(n); }
}
function ms_cid_fundo(e) {
    k = (document.all) ? e.keyCode : e.which;
    if (k == 8 || k == 0 || k == 13) return true;
    patron = /[A-Z\,\  ]/; n = String.fromCharCode(k);
    if (patron.test(n) == '') { document.getElementById('ms_cid_fundo').style.display = 'block'; document.getElementById("ms_cid_fundo").innerHTML = 'Utilice solo letras mayusculas.'; return patron.test(n); }
    else { document.getElementById("ms_cid_fundo").innerHTML = ''; return patron.test(n); }
}
function ms_descripcion(e) {
    k = (document.all) ? e.keyCode : e.which;
    if (k == 8 || k == 0 || k == 13) return true;
    patron = /[A-Z\,\  ]/; n = String.fromCharCode(k);
    if (patron.test(n) == '') { document.getElementById('ms_descripcion').style.display = 'block'; document.getElementById("ms_descripcion").innerHTML = 'Utilice solo letras mayusculas.'; return patron.test(n); }
    else { document.getElementById("ms_descripcion").innerHTML = ''; return patron.test(n); }
}
function esnumero_sol(e) {
    k = (document.all) ? e.keyCode : e.which;
    if (k == 8 || k == 0 || k == 13) return true;
    patron = /[0-9]/; n = String.fromCharCode(k);
    if (patron.test(n) == '') { document.getElementById('ms_num_sol').style.display = 'block'; document.getElementById("ms_num_sol").innerHTML = 'Use solo números'; return patron.test(n); }
    else { document.getElementById("ms_num_sol").innerHTML = ''; return patron.test(n); }
}
function ValidarCamposVacios() {
    var guardar = false;
    var pais = $("#pais").val(); var cid_fundo = $("#cid_fundo").val();
    var nombre_fundo = $("#nombre_fundo").val(); var tipo_fundo = $("#tipo_fundo").val();
    var descripcion_fundo = $("#descripcion_fundo").val(); var direccion_fundo = $("#direccion_fundo").val();
    if (cid_fundo == "") { document.getElementById('ms_cid_fundo').style.display = 'block'; document.getElementById("ms_cid_fundo").innerHTML = 'Este Campo es Obligatorio'; msj = "ms_cid_fundo"; }
    else if (nombre_fundo == "") { document.getElementById('ms_nombre_fundo').style.display = 'block'; document.getElementById("ms_nombre_fundo").innerHTML = 'Este Campo es Obligatorio'; msj = "ms_nombre_fundo"; }
    else if ((tipo_fundo == "") || (tipo_fundo == 'null')) { document.getElementById('ms_tipo_fundo').style.display = 'block'; document.getElementById("ms_tipo_fundo").innerHTML = 'Este Campo es Obligatorio'; msj = "ms_tipo_fundo"; }
    else if (descripcion_fundo == "") { document.getElementById('ms_descripcion_fundo').style.display = 'block'; document.getElementById("ms_descripcion_fundo").innerHTML = 'Este Campo es Obligatorio'; msj = "ms_descripcion_fundo"; }
    else if ((pais == "") || (pais == 'null')) { document.getElementById('ms_pais').style.display = 'block'; document.getElementById("ms_pais").innerHTML = 'Este Campo es Obligatorio'; msj = "ms_pais"; }
    else if (direccion_fundo == "") { document.getElementById('ms_direccion_fundo').style.display = 'block'; document.getElementById("ms_direccion_fundo").innerHTML = 'Este Campo es Obligatorio'; msj = "ms_direccion_fundo"; }
    else { guardar = true; }
    return guardar;
}
function alsalir(e) {
    var caso1 = document.getElementById(e).value;
    if (caso1.length < 5) { document.getElementById('ms_' + e).innerHTML = 'Debe escribir al menos 5 caractéres.'; }
    else { document.getElementById('ms_' + e).innerHTML = ''; }
}
</script>

<script type="text/javascript">
$(document).on('click', '#exit_top', function () { $('#exit').trigger('click'); });
</script>

<?php include_once("../layouts/pie.php") ?>