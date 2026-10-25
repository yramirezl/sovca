<?php
include("../../lib/validar_session.php");
ValidaSession("../login");
?>
<?php include("../layouts/constantes.php") ?>
<?php include_once("../layouts/cabecera.php") ?>

<?php
$parametros = base64_decode($_GET['%$%']);
$id_potrero = intval($parametros);
?>

<link rel="stylesheet" href="../../plugins/confirma/jquery-confirm.min.css" type="text/css" />
<link rel="stylesheet" href="../../css/crud-form.css" type="text/css" />

<div class="crud-form">

    <div class="message"></div>

    <header class="crud-form__header">
        <span class="crud-form__icon"><i class="fa fa-pencil-square-o"></i></span>
        <div>
            <h3 class="crud-form__title">Editar Potrero</h3>
            <p class="crud-form__subtitle">Modifique los datos del potrero seleccionado</p>
        </div>
        <div class="crud-form__header-actions">
            <button id="exit_top" type="button" class="btn btn-warning">
                <i class="fa fa-reply"></i> Regresar
            </button>
        </div>
    </header>

    <form id="form" role="form" enctype="multipart/form-data">
        <input type="hidden" id="id_potrero" value="<?php echo $id_potrero; ?>">

        <div class="crud-form__grid">

            <div class="crud-form__card crud-form__card--compact">
                <div class="crud-form__card-head">
                    <i class="fa fa-leaf"></i>
                    <h3>Datos del Potrero</h3>
                </div>
                <div class="crud-form__card-body">

                    <div class="crud-form__row">
                        <div class="crud-form__field">
                            <label for="nombre_potrero">Nombre *</label>
                            <input class="form-control" id="nombre_potrero" type="text" maxlength="100" tabindex="1">
                        </div>
                        <div class="crud-form__field">
                            <label for="tipo_pasto">Tipo de Pasto</label>
                            <input class="form-control" id="tipo_pasto" type="text" maxlength="100" tabindex="2">
                        </div>
                    </div>

                    <div class="crud-form__row">
                        <div class="crud-form__field">
                            <label for="hectareas">Hectáreas</label>
                            <input class="form-control" id="hectareas" type="number" step="0.01" min="0" tabindex="3">
                        </div>
                        <div class="crud-form__field">
                            <label for="capacidad_maxima">Capacidad Máxima (animales)</label>
                            <input class="form-control" id="capacidad_maxima" type="number" min="0" tabindex="4">
                        </div>
                    </div>

                    <div class="crud-form__row">
                        <div class="crud-form__field">
                            <label for="aforo">Aforo (kg de forraje verde por hectárea)</label>
                            <input class="form-control" id="aforo" type="number" step="0.01" min="0" tabindex="5">
                        </div>
                        <div class="crud-form__field">
                            <label>Forraje total estimado</label>
                            <input class="form-control" id="forraje_total" type="text" readonly
                                   placeholder="kg disponibles">
                        </div>
                    </div>

                    <div class="crud-form__field">
                        <label for="descripcion">Descripción / Observaciones</label>
                        <textarea class="form-control" id="descripcion" rows="3" maxlength="500" tabindex="5"></textarea>
                    </div>

                </div>
            </div>

        </div>

        <div class="crud-form__actions">
            <button id="exit" type="button" class="btn btn-warning" style="display:none;">
                <i class="fa fa-fw fa-reply"></i> Regresar
            </button>
            <button id="save" type="button" class="btn btn-success" tabindex="20">
                <i class="fa fa-fw fa-save"></i> Guardar cambios
            </button>
        </div>

    </form>

</div><!-- /.crud-form -->

<script type="text/javascript" src="../../plugins/confirma/jquery-confirm.min.js"></script>

<script type="text/javascript">
$(document).ready(function() {

    var id_potrero = $('#id_potrero').val();

    /* Cargar datos actuales */
    $.post("../../controllers/mpotreros_controller", {
        action: 'search',
        id_potrero: id_potrero
    }, function(data) {
        var r = (typeof data === 'string') ? JSON.parse(data) : data;
        $('#nombre_potrero').val(r.nombre);
        $('#tipo_pasto').val(r.tipo_pasto);
        $('#hectareas').val(r.hectareas);
        $('#aforo').val(r.aforo);
        $('#capacidad_maxima').val(r.capacidad_maxima);
        $('#descripcion').val(r.descripcion);

        /* Forraje total estimado = aforo (kg/ha) × hectáreas */
        var ha = parseFloat(r.hectareas) || 0;
        var af = parseFloat(r.aforo) || 0;
        $('#forraje_total').val((ha > 0 && af > 0) ? Math.round(ha * af) + ' kg' : '');
    });

    $('#exit').click(function() {
        $.confirm({
            title: '¿Desea salir sin guardar?',
            content: false,
            confirmButton: 'Si',
            cancelButton: 'No',
            confirmButtonClass: 'btn-primary',
            cancelButtonClass: 'btn-success',
            confirm: function() {
                $(location).attr('href', '../potreros/');
            }
        });
    });

    $('#save').click(function() {

        var nombre = $.trim($('#nombre_potrero').val());
        if (nombre == '') {
            $.alert({
                title: 'Campo obligatorio',
                content: 'Ingrese el nombre del potrero.',
                confirmButton: 'Entendido',
                confirmButtonClass: 'btn-warning'
            });
            return;
        }

        $('#save').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Guardando...');

        $.post("../../controllers/mpotreros_controller", {
            action:             'edit',
            id_potrero:         id_potrero,
            nombre_potrero:     nombre,
            descripcion:        $('#descripcion').val(),
            hectareas:          $('#hectareas').val(),
            aforo:              $('#aforo').val(),
            capacidad_maxima:   $('#capacidad_maxima').val(),
            tipo_pasto:         $('#tipo_pasto').val()
        }, function(data) {

            $('#save').prop('disabled', false).html('<i class="fa fa-fw fa-save"></i> Guardar cambios');

            var r;
            try { r = (typeof data === 'string') ? JSON.parse(data) : data; }
            catch (e) { r = { resultado: 'error', mensaje: 'Respuesta inesperada del servidor.' }; }

            if (r.resultado === 'ok') {
                $.alert({
                    title: '¡Listo!',
                    content: r.mensaje,
                    confirmButton: 'Aceptar',
                    confirmButtonClass: 'btn-success',
                    onClose: function() {
                        $(location).attr('href', '../potreros/');
                    }
                });
            } else {
                $.alert({
                    title: 'No se pudo guardar',
                    content: r.mensaje || 'Error desconocido.',
                    confirmButton: 'Entendido',
                    confirmButtonClass: 'btn-danger'
                });
            }
        });

    });

});
</script>

<script type="text/javascript">
$(document).on('click', '#exit_top', function() { $('#exit').trigger('click'); });
</script>

<?php include_once("../layouts/pie.php") ?>