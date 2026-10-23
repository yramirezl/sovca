<?php
include("../../lib/validar_session.php");
ValidaSession("../login");
?>
<?php include("../layouts/constantes.php") ?>
<?php include_once("../layouts/cabecera.php") ?>

<?php
$id_mov = intval(base64_decode($_GET['%$%']));
?>

<link rel="stylesheet" href="../../plugins/confirma/jquery-confirm.min.css" type="text/css" />
<link rel="stylesheet" href="../../css/crud-form.css" type="text/css" />

<style>
/* ============================================================
   VISTA DE DETALLE — bloque info + tabla
   ============================================================ */
.mov-detalle-info {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 20px;
}
@media (max-width: 768px) {
    .mov-detalle-info { grid-template-columns: 1fr; gap: 10px; }
}
.mov-detalle-info .info-bloque {
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 14px 16px;
}
.mov-detalle-info .info-bloque h4 {
    margin: 0 0 10px 0;
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: #475569;
    border-bottom: 1px dashed #e5e7eb;
    padding-bottom: 6px;
}
.mov-detalle-info .info-bloque p {
    margin: 5px 0;
    font-size: 13.5px;
    color: #334155;
    line-height: 1.5;
}
.mov-detalle-info .info-bloque p strong {
    color: #1f2937;
    font-weight: 700;
}

.mov-detalle-animales {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: 20px;
}
.mov-detalle-animales .head-tabla {
    background: #f8fafc;
    padding: 10px 16px;
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: #475569;
    border-bottom: 1px solid #e5e7eb;
}
.mov-detalle-animales table {
    width: 100%;
    margin: 0;
    font-size: 13px;
    border-collapse: collapse;
}
.mov-detalle-animales thead th {
    background: #f8fafc;
    color: #475569;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .05em;
    padding: 10px 14px;
    border-bottom: 1px solid #e5e7eb;
    text-align: left;
    white-space: nowrap;
}
.mov-detalle-animales tbody td {
    padding: 10px 14px;
    border-bottom: 1px solid #eef1f4;
    color: #334155;
    vertical-align: middle;
}
.mov-detalle-animales tbody tr:hover { background: #f0fdf4; }

.mov-alerta-revertido {
    background: #fef3c7;
    border: 1px solid #fcd34d;
    border-left: 4px solid #d97706;
    border-radius: 8px;
    padding: 12px 16px;
    color: #78350f;
    font-size: 13.5px;
    margin-bottom: 16px;
}
.mov-alerta-revertido strong {
    display: block;
    color: #7c2d12;
    margin-bottom: 4px;
    font-size: 14px;
    letter-spacing: .03em;
}

.mov-alerta-detalle {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-left: 4px solid #2563eb;
    border-radius: 8px;
    padding: 12px 16px;
    color: #1e3a8a;
    font-size: 13.5px;
    margin-bottom: 16px;
}
.mov-alerta-detalle strong {
    display: block;
    color: #1e40af;
    margin-bottom: 4px;
    font-size: 14px;
    letter-spacing: .03em;
}

.mov-detalle-vacio {
    padding: 22px 16px;
    text-align: center;
    color: #64748b;
    font-size: 13px;
}

.mov-cargando {
    text-align: center;
    padding: 60px 20px;
    color: #94a3b8;
    font-size: 14px;
}
.mov-cargando i {
    display: block;
    font-size: 32px;
    margin-bottom: 8px;
    color: #2563eb;
}
</style>

<div class="crud-form">

    <div class="message"></div>

    <header class="crud-form__header">
        <span class="crud-form__icon"><i class="fa fa-file-text-o"></i></span>
        <div>
            <h3 class="crud-form__title">Detalle de Movimiento</h3>
            <p class="crud-form__subtitle">Información completa del movimiento de rebaño</p>
        </div>
        <div class="crud-form__header-actions">
            <button id="exit_top" type="button" class="btn btn-warning">
                <i class="fa fa-reply"></i> Volver
            </button>
        </div>
    </header>

    <div class="crud-form__grid">
        <div class="crud-form__card crud-form__card--full">
            <div class="crud-form__card-body" id="detalle_mov">
                <div class="mov-cargando">
                    <i class="fa fa-spinner fa-spin"></i>
                    Cargando detalle del movimiento...
                </div>
            </div>
        </div>
    </div>

    <div class="crud-form__actions" id="acciones_mov" style="display:none;">
        <button id="exit" type="button" class="btn btn-warning" style="display:none;">
            <i class="fa fa-fw fa-reply"></i> Volver
        </button>
        <button id="btn_ver_pdf" type="button" class="btn btn-primary">
            <i class="fa fa-fw fa-file-pdf-o"></i> Ver PDF
        </button>
        <button id="revertir" type="button" class="btn btn-warning" style="display:none;">
            <i class="fa fa-fw fa-undo"></i> Revertir movimiento
        </button>
    </div>

</div><!-- /.crud-form -->

<script type="text/javascript" src="../../plugins/confirma/jquery-confirm.min.js"></script>

<script type="text/javascript">
/* ═══════════════════════════════════════════════════════════════════════════
   JAVASCRIPT ORIGINAL — SIN CAMBIOS EN LA LÓGICA
   ═══════════════════════════════════════════════════════════════════════════ */
$(document).ready(function() {

    var id_mov = <?php echo $id_mov; ?>;
    var rolx = <?php echo $_SESSION['rolx']; ?>;
    var mov_actual = null;

    /* El backend puede devolver fechas como cadena o como objeto
       (DateTime serializado). Nunca se muestra "[object Object]". */
    function movFecha(valor) {
        if (valor === null || valor === undefined || valor === '') {
            return '-';
        }
        if (typeof valor === 'object') {
            if (valor.date) {
                return String(valor.date).substring(0, 16);
            }
            return '-';
        }
        return String(valor);
    }

    $.post("../../controllers/movreb_controller", {
        action: 'search_movimiento',
        id_movimiento: id_mov
    }, function(data) {

        var r;
        try { r = (typeof data === 'string') ? JSON.parse(data) : data; }
        catch (e) { r = { ok: false, error: 'Respuesta inesperada' }; }

        if (!r.ok) {
            $('#detalle_mov').html('<div class="alert alert-danger" style="margin:0;">' + (r.error || 'Movimiento no encontrado') + '</div>');
            return;
        }

        mov_actual = r.mov;

        var html = '';

        /* Cabecera del movimiento */
        html += '<div class="mov-detalle-info">';

        html += '<div class="info-bloque">';
        html += '<h4><i class="fa fa-info-circle"></i> Datos Generales</h4>';
        html += '<p><strong>Movimiento #' + r.mov.id + '</strong></p>';
        html += '<p><strong>Fecha:</strong> ' + movFecha(r.mov.fecha_movimiento) + '</p>';
        html += '<p><strong>Tipo:</strong> ' + r.mov.tipo_movimiento + '</p>';
        html += '<p><strong>Motivo:</strong> ' + r.mov.motivo + '</p>';
        html += '<p><strong>Observaciones:</strong> ' + (r.mov.observaciones || '-') + '</p>';
        html += '<p><strong>Total animales:</strong> ' + r.mov.total_animales + '</p>';
        html += '</div>';

        html += '<div class="info-bloque">';
        html += '<h4><i class="fa fa-map-marker"></i> Ubicaciones</h4>';
        if (r.mov.tipo_movimiento === 'LOTE' || r.mov.tipo_movimiento === 'AMBOS') {
            html += '<p><strong>Lote Origen:</strong> ' + (r.mov.lote_origen_nombre || '-') + '</p>';
            html += '<p><strong>Lote Destino:</strong> ' + (r.mov.lote_destino_nombre || '-') + '</p>';
        }
        if (r.mov.tipo_movimiento === 'POTRERO' || r.mov.tipo_movimiento === 'AMBOS') {
            html += '<p><strong>Potrero Origen:</strong> ' + (r.mov.potrero_origen_nombre || '-') + '</p>';
            html += '<p><strong>Potrero Destino:</strong> ' + (r.mov.potrero_destino_nombre || '-') + '</p>';
        }
        html += '</div>';

        html += '</div>';

        /* Alerta de revertido */
        if (r.mov.revertido) {
            html += '<div class="mov-alerta-revertido">';
            html += '<strong><i class="fa fa-exclamation-triangle"></i> Movimiento Revertido</strong>';
            html += 'Fecha: ' + movFecha(r.mov.fecha_reversion) + '<br>';
            html += 'Motivo: ' + (r.mov.motivo_reversion || '-');
            html += '</div>';
        }

        var detalle = r.detalle || [];

        /* Aviso cuando el movimiento no tiene animales asociados */
        if (detalle.length === 0) {
            html += '<div class="mov-alerta-detalle">';
            html += '<strong><i class="fa fa-info-circle"></i> Sin detalle de animales</strong>';
            if (r.mov.total_animales > 0) {
                html += 'El movimiento registra ' + r.mov.total_animales + ' animales, pero no se guardó el detalle de cada uno. ' +
                        'Esto ocurre en movimientos creados antes de habilitar el registro del detalle.';
            } else {
                html += 'Este movimiento no tiene animales asociados.';
            }
            html += '</div>';
        }

        /* Tabla de animales */
        html += '<div class="mov-detalle-animales">';
        html += '<div class="head-tabla"><i class="fa fa-cow"></i> Animales Movidos</div>';

        if (detalle.length === 0) {
            html += '<div class="mov-detalle-vacio">No hay animales registrados en el detalle de este movimiento.</div>';
        } else {
            html += '<table>';
            html += '<thead><tr>';
            html += '<th>Código</th><th>Nombre</th><th>Sexo</th>';
            html += '<th>Categoría Antes</th><th>Categoría Ahora</th>';
            html += '</tr></thead><tbody>';

            $.each(detalle, function(i, d) {
                html += '<tr>' +
                    '<td style="font-family:monospace;">' + d.codigo_rgb_id_animal + '</td>' +
                    '<td><strong>' + (d.nombre_animal || '-') + '</strong></td>' +
                    '<td>' + d.sexo + '</td>' +
                    '<td>' + d.categoria_anterior_nombre + '</td>' +
                    '<td>' + d.categoria_nueva_nombre + '</td>' +
                    '</tr>';
            });

            html += '</tbody></table>';
        }

        html += '</div>';

        $('#detalle_mov').html(html);
        $('#acciones_mov').show();

        /* Mostrar botón revertir si aplica */
        if (!r.mov.revertido && (rolx == 1 || rolx == 2 || rolx == 4)) {
            $('#revertir').show();
        }
    });

    /* Revertir */
    $('#revertir').click(function() {

        $.confirm({
            title: '¿Revertir este movimiento?',
            content: '<p>Los ' + (mov_actual ? mov_actual.total_animales : '') + ' animales van a volver a su origen original.</p>' +
                '<p><strong>Motivo de la reversion (obligatorio, min 10 caracteres):</strong></p>' +
                '<textarea id="input_motivo_rev" class="form-control" rows="3"></textarea>',
            confirmButton: 'Revertir',
            cancelButton: 'Cancelar',
            confirmButtonClass: 'btn-warning',
            cancelButtonClass: 'btn-default',
            confirm: function() {

                var motivo = $('#input_motivo_rev').val();

                if (!motivo || motivo.trim().length < 10) {
                    $.alert({
                        title: 'Motivo muy corto',
                        content: 'El motivo debe tener al menos 10 caracteres.',
                        confirmButton: 'OK',
                        confirmButtonClass: 'btn-warning'
                    });
                    return false;
                }

                $.post("../../controllers/movreb_controller", {
                    action: 'revertir_movimiento',
                    id_movimiento: id_mov,
                    motivo_reversion: motivo
                }, function(data) {

                    var r;
                    try { r = (typeof data === 'string') ? JSON.parse(data) : data; }
                    catch (e) { r = { ok: false, error: 'Respuesta inesperada' }; }

                    if (r.ok) {
                        $.alert({
                            title: '¡Movimiento revertido!',
                            content: r.mensaje,
                            confirmButton: 'Aceptar',
                            confirmButtonClass: 'btn-success',
                            onClose: function() {
                                $(location).attr('href', '../movreb/');
                            }
                        });
                    } else {
                        $.alert({
                            title: 'No se pudo revertir',
                            content: r.error || 'Error desconocido.',
                            confirmButton: 'Entendido',
                            confirmButtonClass: 'btn-danger'
                        });
                    }
                });
            }
        });
    });

    /* Ver PDF del movimiento */
    $('#btn_ver_pdf').click(function() {
        var parametros = window.btoa(id_mov);
        window.open(
            "../../Reportes/reportes/sovca/rmov.php?%$%=" + parametros,
            '',
            "titlebars=0, toolbar=0,scrollbars=1,location=0,statusbar=0,menubar=0,resizable=1,width=850,height=700,top=100,left=250"
        );
    });

    $('#exit').click(function() {
        $(location).attr('href', '../movreb/');
    });
});
</script>

<script type="text/javascript">
$(document).on('click', '#exit_top', function() {
    window.location.href = '../movreb/';
});
</script>

<?php include_once("../layouts/pie.php") ?>