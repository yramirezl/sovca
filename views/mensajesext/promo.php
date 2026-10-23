<?php

include("../../lib/validar_session.php");
ValidaSession("../login");
VerificarAdmin($_SESSION['rolx']);
$parametros = base64_decode($_GET["%$%"]);
$valores = explode("=", $parametros);
$cad0 = $valores[0];

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
        <span class="crud-form__icon"><i class="fa fa-paper-plane"></i></span>
        <div>
            <h3 class="crud-form__title">Enviar Promoción por Correo</h3>
            <p class="crud-form__subtitle">Revisa la promoción y envíala a todos los contactos registrados.</p>
        </div>
        <div class="crud-form__header-actions">
            <button id="exit_top" type="button" class="btn btn-warning">
                <i class="fa fa-reply"></i> Regresar
            </button>
        </div>
    </header>

    <form id="form" role="form" enctype="multipart/form-data">
        <input type="hidden" id="id_mensaje">

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
                        <textarea class="form-control ccc" id="cuerpo_mensaje" tabindex="8" rows="8"
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
                        <label>Promoción</label>
                        <div style="text-align:center; margin:8px 0;">
                            <img class="imgusr" alt="" src="#" id="file_url" width="200" height="200"
                                 style="border-radius:8px;border:1px solid #e3efe7;box-shadow:0 2px 8px rgba(35,78,53,.08);object-fit:cover;">
                        </div>
                    </div>

                    <div class="crud-form__field" style="text-align:center;">
                        <button id="send_promo" class="btn btn-primary" type="button">
                            <i class="fa fa-fw fa-envelope-o"></i> Enviar Correos
                        </button>
                        <button id="cancelar" class="btn btn-danger" type="button">
                            <i class="fa fa-fw fa-times"></i> Cancelar Envío
                        </button>
                    </div>

                    <div id="msj" style="margin-top:10px; text-align:center;"></div>

                </div>
            </div>

        </div><!-- /.crud-form__grid -->

        <div class="crud-form__actions">
            <button id="exit" type="button" class="btn btn-warning" style="display:none;">
                <i class="fa fa-fw fa-reply"></i> Regresar
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
$('#cancelar').hide();
var cancel = false;

document.getElementById('titulo_mensaje').disabled = true;
document.getElementById('cuerpo_mensaje').disabled = true;
document.getElementById('tipo_mensaje').disabled = true;
document.getElementById('tipo_ganaderia').disabled = true;

$(document).ready(function() {
    $('#cancelar').hide();

    $.post("../../controllers/mtipofundos_controller", { action: "get_tipo_ganaderia" })
        .done(function(data) { $("#tipo_ganaderia").html(data); });

    $.post("../../controllers/malertas_controller", { action: "get_tipo_mensaje" })
        .done(function(data) { $("#tipo_mensaje").html(data); });

    $.post("../../controllers/malertas_controller", {
        action: "search_mensaje_ext",
        id_mensaje: <?php echo intval($cad0); ?>
    }).done(function(data) {
        var parsedJson = $.parseJSON(data);
        $("#id_mensaje").val(parsedJson.id_mensaje);
        $("#titulo_mensaje").val(parsedJson.titulo_mensaje);
        $("#cuerpo_mensaje").val(parsedJson.cuerpo_mensaje);
        $("#tipo_mensaje").val(parsedJson.tipo_mensaje);
        $("#tipo_ganaderia").val(parsedJson.tipo_ganaderia);
        $("#estatus_mensaje").val(parsedJson.status);
        $('.imgusr').attr('src', '../../dist/img/promo/' + parsedJson.imagen);
    }, "json");

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
        title: '¿Desea Salir?', content: false,
        confirmButton: 'Si', cancelButton: 'No',
        confirmButtonClass: 'btn-success', cancelButtonClass: 'btn-danger',
        confirm: function() { setTimeout(function() { $(location).attr('href', '../mensajesext/'); }, 1000); },
        cancel: function() {}
    });
});

$("#send_promo").click(function() {
    var datos = $("#id_mensaje").val();

    $.confirm({
        title: '¿Enviar Promo a todos los Contactos?', content: false,
        confirmButton: 'Si', cancelButton: 'No',
        confirmButtonClass: 'btn-success', cancelButtonClass: 'btn-danger',
        confirm: function() {
            $('#cancelar').show();
            $('#send_promo').hide();

            $('#msj').html('<div><img src="../../dist/img/enviando.gif"/>ENVIANDO...</div>');
            $.post("../../alertas/controlador_email_promo", { datos: datos }).done(function(data) {
                if (data != "") {
                    setTimeout(function() {
                        $('#cancelar').hide();
                        $('#send_promo').show();
                        document.getElementById('send_promo').disabled = true;

                        setTimeout(function() {
                            if (cancel == false) { $("#msj").html(data); }
                            setTimeout(function() {
                                document.getElementById('send_promo').disabled = false;
                                $("#msj").hide();
                                window.setTimeout($(location).attr('href', './'), 1000);
                            }, 3000);
                        }, 1000);
                    }, 2000);
                }
            });
        },
        cancel: function() {}
    });
});

$("#cancelar").click(function() {
    var accion = 0;
    cancel = true;
    $.confirm({
        title: '¿Cancelar envío de Correos?', content: false,
        confirmButton: 'Si', cancelButton: 'No',
        confirmButtonClass: 'btn-success', cancelButtonClass: 'btn-danger',
        confirm: function() {
            $('#send_promo').show();
            $('#cancelar').hide();
            document.getElementById('send_promo').disabled = true;

            $('#msj').html('<div><img src="../../dist/img/enviandocorreo.gif"/> CANCELANDO...</div>');
            $.post("../../alertas/controlador_email_promo", { action: accion }).done(function(data) {
                if (data != "") {
                    setTimeout(function() {
                        $("#msj").html(data);
                        document.getElementById('send_promo').disabled = false;
                        $('#send_promo').show();
                        $('#cancelar').hide();
                        window.setTimeout($(location).attr('href', './'), 1000);
                    }, 3000);
                }
            });
        },
        cancel: function() {}
    });
});
</script>

<script type="text/javascript">
$(document).on('click', '#exit_top', function () { $('#exit').trigger('click'); });
</script>

<?php include_once("../layouts/pie.php") ?>