<?php

include("../../lib/validar_session.php");

ValidaSession("../login");
$parametros = base64_decode($_GET["%$%"]);
$valores = explode("=", $parametros);
$cad0 = $valores[0];
?>

<?php include("../layouts/constantes.php") ?>
<?php include_once("../layouts/cabecera.php") ?>

<div class="message"></div>

<form id="form" role="form" enctype="multipart/form-data">
    <input type="hidden" id="action" value="edit">
    <input type="hidden" id="id_fundo">
    <div class="row">
        <div class="col-md-7">
            <div class="box-header with-border" tabindex="-1">
                <h3 class="box-title">DATOS DEL FUNDO</h3>
            </div><!-- /.box-header -->
            <div class="box box-primary">
                <div class="box-body">

                    <div class="row">

                        <div class="col-xs-5">


                            <div class="form-group-sm">
                                <label for="tipo_ganaderia">Tipo de Ganaderia</label>
                                <div class="box-body">
                                    <div class="form-group-sm">
                                        <select name="tipo_ganaderia" id="tipo_ganaderia" class="form-control bbb">
                                            <option></option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                        </div>
                        <div class="col-xs-4">
                                <label for="tipo_fundo">Tipo Fundo</label>
                                <div class="box-body">
                                    <div class="form-group-sm">
                                        <select name="tipo_fundo" id="tipo_fundo" class="form-control bbb">
                                            <option></option>
                                        </select>
                                        <div style="background-color:#F39C12;color:#fff;text-align:center"
                                            id='ms_tipo_fundo' class="aaa">
                                            <p></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                    </div>
                    <div class="row">

                        <div class="col-xs-4">
                            <div class="form-group-sm">
                                <label for="cid_fundo">CID del Fundo</label>
                                <input class="form-control bbb" id="cid_fundo" type="text"
                                    placeholder="RIF-CID del Fundo" onpaste="return false">
                                <div style="background-color:#F39C12;color:#fff;text-align:center" id='ms_cid_fundo'>
                                    <p></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-xs-8">
                            <div class="form-group-sm">
                                <label for="nombre_fundo">Nombre del Fundo</label>
                                <input class="form-control bbb" id="nombre_fundo" type="text" placeholder="Nombre"
                                    onkeypress="return esnombre(event)"
                                    style="text-transform:uppercase;">
                                <div style="background-color:#F39C12;color:#fff;text-align:center" id='ms_nombre_fundo'>
                                    <p></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group-sm">
                      
                        <label for="descripcion_fundo">Descripción del Fundo</label>
                        <textarea class="form-control ccc" id="descripcion_fundo" rows="3"
                            placeholder="Descripcion del Fundo" onpaste="return true" tabindex="0"
                            onkeypress="return ms_descripcion(event)" onblur="alsalir(this.id);"
                            autocomplete="off"></textarea>
                        <div style="background-color:#F39C12;color:#fff;text-align:center" id='ms_descripcion_fundo'
                            class="aaa">
                            <p></p>
                        </div>
                    </div>
                </div><!-- /.box -->
            </div><!-- FIN box box primary -->
        </div><!-- fin de columna 1 -->
        <!-- inicio de columna 2 -->
        <div class="col-md-5">
            <div class="box-header with-border" tabindex="-1">
                <h3 class="box-title">UBICACION GEOGRAFICA</h3>
            </div><!-- /.box-header -->
            <div class="box box-primary">
                <div class="box-body">
                    <div class="form-group-sm">
                        <label>Pais</label>
                        <select name="pais" id="pais" class="form-control bbb selectpicker"
                                data-live-search="true" data-size="8"
                                title="Seleccione un país" tabindex="4">
                            <option></option>
                        </select>
                        <div style="background-color:#F39C12;color:#fff;text-align:center" id='ms_pais' class="aaa">
                            <p></p>
                        </div>
                    </div>
                    <div class="form-group-sm">
                        <label>Estado</label>
                        <select name="estado" id="estado" class="form-control bbb selectpicker"
                                data-live-search="true" data-size="8"
                                title="Seleccione un estado" tabindex="4">
                            <option></option>
                        </select>
                    </div>
                    <label for="direccion_fundo">Dirección Especifica</label>
                    <textarea class="form-control ccc" id="direccion_fundo" rows="3"
                        placeholder="Dirección Especifica del Fundo" onpaste="return true" tabindex="0"
                        onkeypress="return ms_direccion(event)" onblur="alsalir(this.id);"
                        autocomplete="off"></textarea>
                    <div style="background-color:#F39C12;color:#fff;text-align:center" id='ms_direccion_fundo'
                        class="aaa">
                        <p></p>
                    </div>
                </div><!-- /.box -->
            </div><!-- FIN box box primary -->
        </div><!-- fin de columna 2 -->
    </div><!-- fin de columna row -->

    <div class="box box-primary">
        <div class="box-footer">
            <button id="exit" type="button" class="btn btn-warning pull-left "><i
                    class="fa fa-fw fa-reply"></i>Regresar</button>
            <button id="save" type="button" class="btn btn-success pull-right" tabindex="23"><i
                    class="fa fa-fw fa-save"></i>Guardar</button>
        </div>
    </div>
</form>


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

<link rel="stylesheet" href="../../plugins/confirma/jquery-confirm.min.css" type="text/css" />
<link rel="stylesheet" href="../../css/style_format_pass.css" type="text/css" />
<link rel="stylesheet" href="../../plugins/datepicker/datepicker3.css" type="text/css" />
<link rel="stylesheet" href="../../plugins/unslider/unslider.css">
<link rel="stylesheet" href="../../plugins/leaflet/leaflet.css" />

<link rel="stylesheet" href="../../plugins/select3/bootstrap-select.min.css">

<script type="text/javascript">
var RECORD_ID = <?php echo intval($cad0); ?>;
var DATOS_FUNDO = null;

$(document).ready(function() {

    // Inicializar selectpicker apenas carga
    if ($.fn.selectpicker) {
        $('#pais').selectpicker({ liveSearch: true, size: 8 });
        $('#estado').selectpicker({ liveSearch: true, size: 8 });
    }

    // PASO 1: Cargar datos del fundo primero
    $.post("../../controllers/mfundos_controller", {
        action: "search",
        record: RECORD_ID
    }, function(data) {
        try { DATOS_FUNDO = typeof data === 'string' ? $.parseJSON(data) : data; }
        catch(e) { DATOS_FUNDO = null; }
        if (!DATOS_FUNDO) return;

        // PASO 2: Cargar selects en cadena
        cargar_selects_y_rellenar();
    }, "json");

});

function cargar_selects_y_rellenar() {

    // Tipo de ganadería
    $.post("../../controllers/mtipofundos_controller", { action: "get_tipo_ganaderia" }, function(data) {
        $("#tipo_ganaderia").html(data);
        $("#tipo_ganaderia").val(DATOS_FUNDO.tipo_ganaderia).prop('disabled', true);
    });

    // Tipo de fundo
    $.post("../../controllers/mtipofundos_controller", { action: "get_tipo_fundos" }, function(data) {
        $("#tipo_fundo").html(data);
        $("#tipo_fundo").val(DATOS_FUNDO.tipo_fundo);
    });

    // PAÍS → luego ESTADOS → luego rellenar el valor
    $.post("../../controllers/mgeograficas_controller", { action: "get_paises" }, function(data) {
        $("#pais").html(data);

        // Refrescar selectpicker
        if ($.fn.selectpicker) { $('#pais').selectpicker('refresh'); }

        // Asignar país guardado
        $("#pais").val(DATOS_FUNDO.pais);
        if ($.fn.selectpicker) { $('#pais').selectpicker('refresh'); }

        // Cargar estados del país guardado
        $.post("../../controllers/mgeograficas_controller", {
            action: "get_estados",
            filtrar_x_pais: DATOS_FUNDO.pais
        }, function(dataEst) {
            $("#estado").html(dataEst);

            // Refrescar selectpicker
            if ($.fn.selectpicker) { $('#estado').selectpicker('refresh'); }

            // Asignar estado guardado
            $("#estado").val(DATOS_FUNDO.estado);
            if ($.fn.selectpicker) { $('#estado').selectpicker('refresh'); }

            $("#estado").prop('disabled', false);
            $("#direccion_fundo").prop('disabled', false);
        });
    });

    // Rellenar los campos de texto
    $("#id_fundo").val(DATOS_FUNDO.id);
    $("#cid_fundo").val(DATOS_FUNDO.cid_fundo);
    $("#nombre_fundo").val(DATOS_FUNDO.nombre_fundo);
    $("#direccion_fundo").val(DATOS_FUNDO.direccion_fundo);
    $("#descripcion_fundo").val(DATOS_FUNDO.descripcion_fundo);
}

// Cambio de país → recargar estados
$('#pais').on('change', function() {
    var p = $(this).val();
    if (!p) return;
    $.post("../../controllers/mgeograficas_controller", {
        action: "get_estados",
        filtrar_x_pais: p
    }, function(data) {
        $("#estado").html(data);
        if ($.fn.selectpicker) { $('#estado').selectpicker('refresh'); }
        $("#estado").prop('disabled', false);
        $("#direccion_fundo").prop('disabled', false);
    });
});


$("#derecha").click(function() {

    if (tick == 0) {

        var cont_alert = $('.bbb').filter(function() {
            return $(this).val() == "";
        }).size();
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
                document.body.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }, 800);

        }

    } else if (tick == 1) {

        var cont_alert3 = $('.ccc').filter(function() {
            return $(this).val() == "";
        }).size();

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
                document.body.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
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
            document.body.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }, 800);

    } else if (tick == 2) {

        $('.base').unslider('animate:1');
        tick = 1;
        document.getElementById('derecha').disabled = false;
        $("#derecha").css("display", "block");
        setTimeout(function() {
            document.body.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
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

                $(location).attr('href', '../fundos/');

            }, 1000);
        },

        cancel: function() {

        }
    });

});

$('#save').click(function() {
    document.getElementById('save').disabled = true;
    if (ValidarCamposVacios()) {
        Guardar_Fundo();

    } else {
        setTimeout(function() {
            document.getElementById(msj).innerHTML = '';
            document.getElementById('save').disabled = false;
        }, 2000);
    }

});

function Guardar_Fundo() {
    document.getElementById('save').disabled = true;


    $.confirm({

        title: '¿Está Seguro de Guardar el Registro ?',
        content: false,
        confirmButton: 'Si',
        cancelButton: 'No',
        confirmButtonClass: 'btn-success',
        cancelButtonClass: 'btn-danger',

        confirm: function() {

            $.post("../../controllers/mfundos_controller", {
                action: "edit",
                cid_fundo: $("#cid_fundo").val(),
                id_fundo: $("#id_fundo").val(),
                nombre_fundo: $("#nombre_fundo").val(),
                tipo_fundo: $("#tipo_fundo").val(),
                pais: $("#pais").val(),
                estado: $("#estado").val(),
                direccion_fundo: $("#direccion_fundo").val(),
                descripcion_fundo: $("#descripcion_fundo").val()
            }).done(function(data) {
                var parsedJson = $.parseJSON(data);
                $(".message").html(parsedJson.mensaje);

                if (parsedJson.resultado != 'error') {
                    $('.base').unslider('animate:0');
                    setTimeout(function() {
                        $(location).attr('href', './');
                    }, 1500);
                } else {
                    document.getElementById('save').disabled = false;
                    setTimeout(function() { $(".alert").alert('close'); }, 4000);
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

function ms_cid_fundo(e) {
    k = (document.all) ? e.keyCode : e.which;
    if (k == 8 || k == 0 || k == 13) return true;
    patron = /[A-Z\,\  ]/;
    n = String.fromCharCode(k);

    if (patron.test(n) == '') {

        document.getElementById('ms_cid_fundo').style.display = 'block';
        document.getElementById("ms_cid_fundo").innerHTML = 'Utilice solo letras mayusculas.';
        return patron.test(n);

    } else {

        document.getElementById("ms_cid_fundo").innerHTML = '';
        return patron.test(n);

    }

}

function ms_descripcion(e) {
    k = (document.all) ? e.keyCode : e.which;
    if (k == 8 || k == 0 || k == 13) return true;
    patron = /[A-Z\,\  ]/;
    n = String.fromCharCode(k);

    if (patron.test(n) == '') {

        document.getElementById('ms_descripcion').style.display = 'block';
        document.getElementById("ms_descripcion").innerHTML = 'Utilice solo letras mayusculas.';
        return patron.test(n);

    } else {

        document.getElementById("ms_descripcion").innerHTML = '';
        return patron.test(n);

    }

}


function esnumero_sol(e) {

    k = (document.all) ? e.keyCode : e.which;
    if (k == 8 || k == 0 || k == 13) return true;
    patron = /[0-9]/;
    n = String.fromCharCode(k);

    if (patron.test(n) == '') {

        document.getElementById('ms_num_sol').style.display = 'block';
        document.getElementById("ms_num_sol").innerHTML = 'Use solo números';
        return patron.test(n);

    } else {

        document.getElementById("ms_num_sol").innerHTML = '';
        return patron.test(n);

    }
}

function ValidarCamposVacios() {
    var guardar = false;
    var pais = $("#pais").val();
    var cid_fundo = $("#cid_fundo").val();
    var nombre_fundo = $("#nombre_fundo").val();
    var tipo_fundo = $("#tipo_fundo").val();
    var descripcion_fundo = $("#descripcion_fundo").val();
    var direccion_fundo = $("#direccion_fundo").val();


    if (cid_fundo == "") {
        document.getElementById('ms_cid_fundo').style.display = 'block';
        document.getElementById("ms_cid_fundo").innerHTML = 'Este Campo es Obligatorio';
        msj = "ms_cid_fundo";
    } else if (nombre_fundo == "") {
        document.getElementById('ms_nombre_fundo').style.display = 'block';
        document.getElementById("ms_nombre_fundo").innerHTML = 'Este Campo es Obligatorio';
        msj = "ms_nombre_fundo";

    } else if ((tipo_fundo == "") || (tipo_fundo == 'null')) {
        document.getElementById('ms_tipo_fundo').style.display = 'block';
        document.getElementById("ms_tipo_fundo").innerHTML = 'Este Campo es Obligatorio';
        msj = "ms_tipo_fundo";

    } else if (descripcion_fundo == "") {
        document.getElementById('ms_descripcion_fundo').style.display = 'block';
        document.getElementById("ms_descripcion_fundo").innerHTML = 'Este Campo es Obligatorio';
        msj = "ms_descripcion_fundo";

    } else if ((pais == "") || (pais == 'null')) {

        document.getElementById('ms_pais').style.display = 'block';
        document.getElementById("ms_pais").innerHTML = 'Este Campo es Obligatorio';
        msj = "ms_pais";

    } else if (direccion_fundo == "") {
        document.getElementById('ms_direccion_fundo').style.display = 'block';
        document.getElementById("ms_direccion_fundo").innerHTML = 'Este Campo es Obligatorio';
        msj = "ms_direccion_fundo";

    } else {
        guardar = true;
    }


    return guardar;
}
//************************************************************************/

function alsalir(e) {

    var caso1 = document.getElementById(e).value;

    if (caso1.length < 5) {

        document.getElementById('ms_' + e).innerHTML = 'Debe escribir al menos 5 caractéres.';

    } else {

        document.getElementById('ms_' + e).innerHTML = '';

    }

}
</script>
<?php include_once("../layouts/pie.php") ?>