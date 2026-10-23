<div class="modal fade" id="ventana_modal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel1"
    aria-hidden="true" data-backdrop="false">
    <form id="form" role="form" enctype="multipart/form-data">
        <input type="hidden" id="id_mensaje">
        <input type="hidden" id="nro_vista">
        <!-- <div class="modal-dialog modal-lm"> -->
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body">
                    <div class="contenido-modal">
                        <h4 class="modal-title" id="myModalLabel1">Mensaje del Sistema</h4>
                        <div class="message1"></div>
                        <div class="row">
                            <!-- left column -->
                            <div class="col-md-12">
                                <!-- general form elements -->
                                <div class="box box-primary">

                                    <div class="box-header with-border"></div><!-- /.box-header -->
                                    <!-- form start -->
                                    <div class="box-body">
                                   
                                        <div class="form-group-sm">
                                            <label for="titulo_mensaje">Titulo del Mensaje</label>
                                            <input class="form-control bbb" id="titulo_mensaje" tabindex="1"
                                                type="text">

                                        </div>
                                        
                                        <div class="box box-primary">  </div>
                                        <div class="box-body">

                                            <label for="cuerpo_mensaje">Descripción del Mensaje</label>
                                            <textarea class="form-control ccc" id="cuerpo_mensaje" tabindex="8"
                                                rows="3"></textarea>

                                        </div>
                                        <div class="box box-primary">  </div>

                                     

                                    </div><!-- /.box-body -->

                                </div><!-- /.box -->

                            </div>
                        </div>
                        <div class="modal-footer">

                            <button id="cerrar" type="button" class="btn btn-primary  pull-right"><i
                                    class="fa fa-fw fa-times"></i>Cerrar</button>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<li id="desa2" class="dropdown notifications-menu">

    <a href="#" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="false" title="Alertas automáticas">
        <i class="fa fa-life-saver"></i>
        <span id="n_re" class="label label-danger"></span>
    </a>
    <ul class="dropdown-menu bovs-dd">

        <li class="header bovs-dd-header">
            <i class="fa fa-bullhorn" aria-hidden="true"></i>
            <span>Alertas automáticas</span>
        </li>
        <li>
            <ul class="menu bovs-dd-menu">
                <li>
                    <a href="#" id="reg_re_row" class="bovs-dd-item is-empty">
                        <span class="bovs-dd-chip"><i class="fa fa-life-saver" aria-hidden="true"></i></span>
                        <span class="bovs-dd-txt" id="reg_re" name="reg_re">No tienes Notificaciones!</span>
                        <i class="fa fa-angle-right bovs-dd-arrow" aria-hidden="true"></i>
                    </a>
                </li>
            </ul>
        </li>
    </ul>

</li>

<li id="desa3" class="dropdown notifications-menu">

    <a href="#" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="false" title="Mensajes del sistema">
        <i class="fa fa-envelope-o"></i>
        <span id="n_in" class="label label-danger"></span>
    </a>
    <ul class="dropdown-menu bovs-dd">

        <li class="header bovs-dd-header">
            <i class="fa fa-envelope-o" aria-hidden="true"></i>
            <span>Mensajes del sistema</span>
        </li>
        <li>
            <ul class="menu bovs-dd-menu">
                <li>
                    <a href="#" id="reg_in_row" class="bovs-dd-item is-empty">
                        <span class="bovs-dd-chip"><i class="fa fa-envelope-o" aria-hidden="true"></i></span>
                        <span class="bovs-dd-txt" id="reg_in" name="reg_in">No tienes Mensajes!</span>
                        <i class="fa fa-angle-right bovs-dd-arrow" aria-hidden="true"></i>
                    </a>
                </li>
            </ul>
        </li>
    </ul>
</li>

<style>
/* ============================================================
   DESPLEGABLES DE ALERTAS Y MENSAJES (barra superior)
   Tarjeta blanca con punta, cabecera y fila con icono.
   Se usa !important solo donde AdminLTE / el tema ya definen
   las mismas propiedades para el mismo selector.
   ============================================================ */
.navbar-bovs .navbar-custom-menu .notifications-menu > .dropdown-menu.bovs-dd {
    width: 330px !important;
    max-width: calc(100vw - 24px);
    left: auto !important;
    right: 0 !important;
    top: 100% !important;
    margin: 8px 0 0 0 !important;
    padding: 0 !important;
    border: 1px solid #e2e8e3 !important;
    border-radius: 12px !important;
    box-shadow: 0 16px 38px rgba(13, 61, 36, .22) !important;
    overflow: visible !important;
    z-index: 1200 !important;
}
/* Punta que apunta al icono del menú */
.navbar-bovs .navbar-custom-menu .notifications-menu > .dropdown-menu.bovs-dd::before {
    content: "";
    position: absolute;
    top: -6px; right: 24px;
    width: 11px; height: 11px;
    background: #f7faf8;
    border-left: 1px solid #e2e8e3;
    border-top: 1px solid #e2e8e3;
    transform: rotate(45deg);
    z-index: 2;
}
/* Cabecera */
.navbar-bovs .navbar-custom-menu .notifications-menu > .dropdown-menu.bovs-dd > li.header.bovs-dd-header {
    display: flex !important;
    align-items: center !important;
    gap: 8px;
    padding: 11px 14px !important;
    background: #f7faf8 !important;
    border-bottom: 1px solid #e9eee9 !important;
    border-radius: 11px 11px 0 0 !important;
    color: #0d3d24 !important;
    font-size: 11px !important;
    font-weight: 800 !important;
    letter-spacing: .8px;
    text-transform: uppercase;
}
.navbar-bovs .navbar-custom-menu .notifications-menu > .dropdown-menu.bovs-dd > li.header.bovs-dd-header > i {
    color: #2c8a56 !important;
    font-size: 12px !important;
}
/* Lista de items */
.navbar-bovs .navbar-custom-menu .notifications-menu > .dropdown-menu.bovs-dd > li > ul.menu.bovs-dd-menu {
    list-style: none !important;
    margin: 0 !important;
    padding: 0 !important;
    max-height: 280px !important;
    overflow-x: hidden !important;
    overflow-y: auto !important;
}
/* Fila (item) */
.navbar-bovs .navbar-custom-menu .notifications-menu > .dropdown-menu.bovs-dd > li > ul.menu.bovs-dd-menu > li > a.bovs-dd-item {
    display: flex !important;
    align-items: center !important;
    gap: 10px;
    padding: 12px 14px !important;
    margin: 0 !important;
    border-bottom: 1px solid #f2f5f2 !important;
    border-radius: 0 !important;
    color: #24402f !important;
    background: #ffffff !important;
    white-space: normal !important;
    text-decoration: none !important;
    transition: background .18s ease;
}
.navbar-bovs .navbar-custom-menu .notifications-menu > .dropdown-menu.bovs-dd > li > ul.menu.bovs-dd-menu > li > a.bovs-dd-item:hover {
    background: #f2f8f4 !important;
}
.navbar-bovs .navbar-custom-menu .notifications-menu .bovs-dd-chip {
    flex: 0 0 34px;
    width: 34px; height: 34px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 9px;
    background: linear-gradient(135deg, #1a5c3a 0%, #2c8a56 100%);
    color: #ffffff;
    font-size: 14px;
    box-shadow: 0 2px 6px rgba(26, 92, 58, .28);
    transition: background .18s ease, color .18s ease;
}
.navbar-bovs .navbar-custom-menu .notifications-menu .bovs-dd-txt {
    flex: 1 1 auto;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    font-size: 13px;
    font-weight: 600;
    line-height: 1.3;
}
.navbar-bovs .navbar-custom-menu .notifications-menu .bovs-dd-arrow {
    color: #a7bcae !important;
    font-size: 15px;
    flex: 0 0 auto;
}
/* Estado sin novedades: discreto y sin flecha */
.navbar-bovs .navbar-custom-menu .notifications-menu .bovs-dd-item.is-empty .bovs-dd-chip {
    background: #eef3f0;
    color: #7d9187;
    box-shadow: none;
}
.navbar-bovs .navbar-custom-menu .notifications-menu .bovs-dd-item.is-empty .bovs-dd-txt {
    color: #7d9187 !important;
    font-weight: 600;
}
.navbar-bovs .navbar-custom-menu .notifications-menu .bovs-dd-item.is-empty .bovs-dd-arrow {
    display: none;
}
/* Con novedades: el texto toma el color del estado del badge */
.navbar-bovs .navbar-custom-menu .notifications-menu .bovs-dd-item:not(.is-empty) .bovs-dd-txt {
    color: #12492c;
}
/* El badge del icono no se muestra si no hay número */
.navbar-custom-menu .navbar-nav > li > a > .label:empty {
    display: none !important;
}
/* En pantallas angostas que el desplegable no se salga de la ventana */
@media (max-width: 480px) {
    .navbar-bovs .navbar-custom-menu .notifications-menu > .dropdown-menu.bovs-dd {
        width: calc(100vw - 20px) !important;
        right: -8px !important;
    }
    .navbar-bovs .navbar-custom-menu .notifications-menu > .dropdown-menu.bovs-dd::before { right: 30px; }
}
</style>

<script type="text/javascript" src="../../plugins/confirma/jquery-confirm.min.js"></script>
<link rel="stylesheet" href="../../plugins/confirma/jquery-confirm.min.css" type="text/css" />
<script type="text/javascript">
var can_msj = 0;
var can_notif = 0;
$("#id_mensaje").val(0);
$(document).ready(function() {

    CargarMensajes();

    var desho = <?php echo $_SESSION['rolx'];  ?>;

    if (desho == 3) {

        $("#desa3 *").prop('disabled', true);
        $("#desa2").css('display', 'none');

    } else if (desho == 1) {

        $("#desa2").css('display', 'none');
        $("#desa3").css('display', 'none');
    }

    $.post("../../controllers/malertas_controller", {
        action: "get_notificaciones"
    }).done(function(data) {

        $("#n_re").html(data);
        var div = document.getElementById("reg_re");
        var fila_notif = document.getElementById("reg_re_row");
        var dato_1 = $.parseJSON(data);

        var dato1 = JSON.stringify(data);

        can_notif = dato_1;
        if (dato1 != '"0"') {
            if (dato_1 > 1) {
                div.textContent = ' Tienes ' + dato_1 + ' Notificaciones !';
            } else {
                div.textContent = ' Tienes ' + dato_1 + ' Notificación !';

            }
            if ($("#n_re").hasClass('label-success')) {
                $("#n_re").removeClass('label-success');
                $("#n_re").addClass('label-danger');
            }

            if ($("#reg_re").hasClass('text-green')) {
                $("#reg_re").removeClass('text-green');
                $("#reg_re").addClass('text-red');
            }

            if (fila_notif) { fila_notif.classList.remove('is-empty'); }

        } else {

            div.textContent = ' No tienes Notificaciones!';
            if (fila_notif) { fila_notif.classList.add('is-empty'); }
            $("#n_re").html('');   // sin novedades: no mostrar el 0

            if ($("#n_re").hasClass('label-danger')) {
                $("#n_re").removeClass('label-danger');
                $("#n_re").addClass('label-success');
            }

            if ($("#reg_re").hasClass('text-red')) {
                $("#reg_re").removeClass('text-red');
                $("#reg_re").addClass('text-green');
            }

        }

    });
});

function CargarMensajes() {


    $.post("../../controllers/malertas_controller", {
        action: "get_mensajes",
        id_msj: $("#id_mensaje").val()
    }).done(function(data) {

        var parsedJson = $.parseJSON(data);
        // alert(parsedJson);

        $("#nro_vista").val(parsedJson.nro_vista);
        $("#n_in").html(parsedJson.cant_id);

        var div2 = document.getElementById("reg_in");
        var fila_msj = document.getElementById("reg_in_row");
        var dato_2 = parsedJson.cant_id;
        var n_vista = parsedJson.nro_vista;
        can_msj = dato_2;
        if ((dato_2 > 0) && (n_vista < 2)) {


            if (dato_2 > 1) {
                div2.textContent = ' Tienes ' + dato_2 + ' Mensajes !';
            } else {
                div2.textContent = ' Tienes ' + dato_2 + ' Mensaje !';

            }

            if ($("#n_in").hasClass('label-success')) {
                $("#n_in").removeClass('label-success');
                $("#n_in").addClass('label-warning');
            }

            if ($("#reg_in").hasClass('text-green')) {
                $("#reg_in").removeClass('text-green');
                $("#reg_in").addClass('text-orange');
            }

            if (fila_msj) { fila_msj.classList.remove('is-empty'); }

        } else {

            div2.textContent = ' No tienes Mensajes!';
            if (fila_msj) { fila_msj.classList.add('is-empty'); }
            $("#n_in").html('');   // sin novedades: no mostrar el 0


            $("#n_in").removeClass('label-warning');
            $("#reg_in").removeClass('text-orange');
            $("#reg_in").addClass('text-green');
            $("#n_in").addClass('label-success');

        }

        var nro_vista = $("#nro_vista").val();

        if (nro_vista == 1) {

            if ($("#n_in").hasClass('label-danger')) {
                $("#n_in").removeClass('label-danger');
                $("#n_in").addClass('label-warning');
            }

            if ($("#reg_in").hasClass('text-red')) {
                $("#reg_in").removeClass('text-red');
                $("#reg_in").addClass('text-orange');
            }

        } else if (nro_vista == 2) {

            if ($("#n_in").hasClass('label-warning')) {
                $("#n_in").removeClass('label-warning');
                $("#n_in").addClass('label-success');
            }

            if ($("#reg_in").hasClass('text-orange')) {
                $("#reg_in").removeClass('text-orange');
                $("#reg_in").addClass('text-green');
            }
        }

    });

}


$("#reg_in_row").click(function(e) {

    e.preventDefault();

    if (can_msj > 0) {
        $('#ventana_modal').scrollTop(0);
        $('#ventana_modal').modal();
        document.getElementById('cerrar').disabled = true;
        MostrarMensaje();
    } else {

        $.alert({
            title: '! No existen Mensajes en su Buzón !',
            content: false,
            confirmButton: true, // hides the confirm button.
            closeIcon: false,
            confirmButton: 'ok',
            confirmButtonClass: 'btn-success'
        });
    }
});

$("#reg_re_row").click(function(e) {

    e.preventDefault();

    if (can_notif > 0) {
        $(location).attr('href', '../alertas/index');
    } else {
        $.alert({
            title: '! No tiene Notificaciones  !',
            content: false,
            confirmButton: true, // hides the confirm button.
            closeIcon: false,
            confirmButton: 'ok',
            confirmButtonClass: 'btn-success'
        });

    }
});
$("#cerrar").click(function() {
    $('#ventana_modal').modal('toggle');
    setTimeout(function() {
        CargarMensajes();
    }, 1500);


});

function MostrarMensaje() {
    $.post("../../controllers/malertas_controller", {
        action: "search_contenido_mensaje"
    }).done(function(data) {
        var parsedJson = $.parseJSON(data);
        if (!parsedJson || parsedJson.resultado !== 'ok' || !parsedJson.id_mensaje) {
            $('#ventana_modal').modal('hide');
            document.getElementById('cerrar').disabled = false;
            CargarMensajes();
            return;
        }
        $("#id_mensaje").val(parsedJson.id_mensaje);
        $("#titulo_mensaje").val(parsedJson.titulo_mensaje);
        $("#cuerpo_mensaje").val(parsedJson.cuerpo_mensaje);
        document.getElementById('titulo_mensaje').disabled = true;
        document.getElementById('cuerpo_mensaje').disabled = true;
        document.getElementById('cerrar').disabled = false;


    }).fail(function() {
        $('#ventana_modal').modal('hide');
        document.getElementById('cerrar').disabled = false;
        $.alert({
            title: 'No se pudo cargar el mensaje',
            content: 'Inténtalo de nuevo en unos momentos.',
            confirmButton: 'Aceptar',
            confirmButtonClass: 'btn-success'
        });
    });
}
</script>