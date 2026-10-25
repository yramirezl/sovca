<?php
include("../../lib/validar_session.php");
ValidaSession("../login");
?>
<?php include("../layouts/constantes.php"); ?>
<?php include_once("../layouts/cabecera.php"); ?>

<style>
/* Diálogo de revisión de nacimientos duplicados */
.nac-unificar-lista { text-align: left; }
.nac-grupo { border: 1px solid #E3E7ED; border-radius: 10px; padding: 10px 12px; margin-bottom: 10px; background: #FBFCFD; }
.nac-grupo__cab { display: flex; flex-direction: column; gap: 4px; }
.nac-grupo__resumen { color: #4A5563; font-size: 12.5px; line-height: 1.45; }
.nac-unificar-item { display: flex; align-items: center; gap: 8px; margin: 0; font-weight: 600; color: #1F2937; cursor: pointer; }
.nac-unificar-item input { margin: 0; }
.nac-unificar-bloqueado { color: #92400e; background: #FFFBEB; border-left: 3px solid #F59E0B; padding: 6px 8px; border-radius: 6px; font-size: 12.5px; }
.nac-unificar-aviso { display: block; color: #6B7480; font-size: 11.5px; margin-top: 4px; }
.nac-anular-fila { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-top: 8px; padding-top: 8px; border-top: 1px dashed #E3E7ED; }
.nac-anular-fila label { margin: 0; font-size: 12px; color: #6B7480; font-weight: 500; }
.nac-anular-select { max-width: 100%; min-width: 240px; padding: 5px 8px; border: 1px solid #D8DEE6; border-radius: 6px; font-size: 12.5px; background: #fff; color: #2C3440; }
.nac-anular-select:focus { outline: none; border-color: #22B34E; box-shadow: 0 0 0 2px rgba(34,179,78,.15); }
.nac-anular-aviso { flex: 1 1 100%; color: #146B2E; font-size: 11.5px; }
.nac-anular-aviso:empty { display: none; }
</style>

<?php
$crud = [
    'title'    => 'Nacimientos',
    'subtitle' => 'Registro de nacimientos y crías',
    'icon'     => 'fa fa-cow',
    'theme'    => 'success',

    'ajaxUrl'    => '../../data_json/data_mnacimientos',
    'controller' => '../../controllers/mnacimientos_controller',

    'routes' => [
        'add'  => 'frm_registrar',
        'edit' => 'frm_editar',
        'home' => '../inicio/',
    ],

    'showButtons' => ['add', 'edit', 'delete'],
    'buttonLabels' => ['edit' => 'Editar'],

    'extraButtonsHtml' =>
        '<button id="baja" type="button" class="crud-btn crud-btn--warning"><i class="fa fa-arrow-down"></i> <span>Dar de Baja</span></button>' .
        '<button id="mostrar" type="button" class="crud-btn crud-btn--primary"><i class="fa fa-list"></i> <span>Mostrar Bajas</span></button>' .
        '<button id="unificar_nacimiento" type="button" class="crud-btn crud-btn--warning" style="display:none" enabled><i class="fa fa-clone"></i> <span>Revisar duplicados</span></button>',

    'columns' => [
        ['data' => 'codigo_ternero',                'title' => 'Código del Ternero'],
        ['data' => 'terneros',                      'title' => 'Crías del Parto'],
        ['data' => 'fichado',                       'title' => 'Estado'],
        ['data' => 'codigo_rgb_id_animal_hembra',   'title' => 'Código / HEMBRA'],
        ['data' => 'nro_arete',                     'title' => 'Nro Arete / Microchip HEMBRA'],
        ['data' => 'codigo_rgb_id_animal_macho',    'title' => 'Código / MACHO'],
    ],

    'columnDefs' => [
        ['width' => '150px', 'targets' => [0]],
        ['width' => '120px', 'targets' => [1]],
        ['width' => '130px', 'targets' => [2]],
        ['width' => '180px', 'targets' => [3]],
        ['width' => '250px', 'targets' => [4]],
        ['width' => '180px', 'targets' => [5]],
    ],

    'order' => [[0, 'asc']],
    'searchPlaceholder' => 'Buscar nacimiento…',
    'scrollY' => '350px',

    'rowCallbackJs' => "
        var estado = (mData.fichado || '').trim();
        if (estado === 'Sin Fichar') {
            \$('td:eq(2)', nRow).css('color', '#dc2626').css('font-weight', '600');
        } else {
            \$('td:eq(2)', nRow).css('font-weight', 'bold');
        }
        \$('td:eq(0)', nRow).css('font-weight', 'bold');
        \$('td:eq(1)', nRow).css('text-align', 'center');
        \$('td:eq(4)', nRow).css('text-align', 'center');
    ",

    'customScript' => "
        var rolx = " . (int)$_SESSION['rolx'] . ";
        function escaparTextoUnificacion(valor) {
            return $('<div>').text(valor == null ? '' : String(valor)).html();
        }
        if (rolx == 3) {
            \$('#delete, #edit').hide();
        }
        \$('#baja').on('click', function() {
            var value = crud.getSelectedId();
            if (!value) { crud.dialogInfo('¡Seleccione un registro!'); return; }
            window.location.href = 'frm_exc?%\$%=' + window.btoa(value);
        });
        \$('#mostrar').on('click', function() {
            window.location.href = 'frm_ver';
        });
        if (rolx !== 1 && rolx !== 2) {
            $('#unificar_nacimiento').hide();
        } else {
            $.post('../../controllers/mnacimientos_controller', {
                action: 'analizar_unificacion_nacimientos'
            }, null, 'json').done(function(respuesta) {
                if (respuesta.resultado !== 'ok') return;
                var grupos = respuesta.grupos || [];
                var gruposSeguros = grupos.filter(function(grupo) { return grupo.mergeable; }).length;
                if (gruposSeguros === 0) return;

                var gruposRevision = grupos.length - gruposSeguros;
                var nota = 'Se detectaron ' + gruposSeguros + ' grupo(s) de nacimientos que pueden unificarse.';
                if (gruposRevision > 0) {
                    nota += ' Otros ' + gruposRevision + ' grupo(s) requieren revisión manual.';
                }
                $('.message').empty().append(
                    $('<div>', { 'class': 'alert alert-warning', role: 'alert' }).text(nota)
                );
                $('#unificar_nacimiento').prop('disabled', false).show();
            });
        }
        $('#unificar_nacimiento').on('click', function() {
            var boton = $(this);
            boton.prop('disabled', true);
            $.post('../../controllers/mnacimientos_controller', {
                action: 'analizar_unificacion_nacimientos'
            }, null, 'json').done(function(respuesta) {
                if (respuesta.resultado !== 'ok') {
                    crud.dialogInfo(respuesta.mensaje || 'No se pudo analizar los nacimientos.');
                    return;
                }
                var grupos = respuesta.grupos || [];
                if (grupos.length === 0) {
                    crud.dialogInfo('No se encontraron nacimientos coincidentes para unificar.');
                    return;
                }

                var contenido = '<div class=nac-unificar-lista><p>Revisa cada grupo antes de confirmar. Solo se pueden seleccionar grupos sin conflictos.</p>';
                var gruposSeguros = 0;
                grupos.forEach(function(grupo, indice) {
                    var fechas = grupo.fechas.map(escaparTextoUnificacion).join(' / ');
                    var eventos = grupo.eventos.map(escaparTextoUnificacion).join(', ');
                    var resumen = 'Hembra ' + escaparTextoUnificacion(grupo.madre) + ' · Fechas ' + fechas +
                        ' · Registros ' + eventos + ' · ' + escaparTextoUnificacion(grupo.cantidad_terneros) + ' cría(s)';
                    if (grupo.mergeable) {
                        gruposSeguros++;
                        contenido += '<label class=nac-unificar-item><input class=nac-unificar-seleccion type=checkbox value=' + indice + ' checked> ' + resumen + '</label>';
                    } else {
                        contenido += '<div class=nac-unificar-bloqueado><strong>Requiere revisión:</strong> ' + resumen + '<br>' + grupo.bloqueos.join(' ') + '</div>';
                    }
                    if (grupo.avisos && grupo.avisos.length) {
                        contenido += '<small class=nac-unificar-aviso>' + grupo.avisos.join(' ') + '</small>';
                    }
                });
                contenido += '</div>';
                if (gruposSeguros === 0) {
                    $.alert({ title: 'No hay grupos seguros para unificar', content: contenido, columnClass: 'large' });
                    return;
                }

                $.confirm({
                    title: 'Unificar nacimientos coincidentes',
                    content: contenido,
                    columnClass: 'large',
                    confirmButton: 'Unificar seleccionados',
                    cancelButton: 'Cancelar',
                    confirmButtonClass: 'btn-success',
                    cancelButtonClass: 'btn-default',
                    confirm: function() {
                        var seleccionados = [];
                        this.\$content.find('.nac-unificar-seleccion:checked').each(function() {
                            var grupo = grupos[parseInt(this.value, 10)];
                            if (grupo && grupo.mergeable) seleccionados.push(grupo.ids);
                        });
                        if (seleccionados.length === 0) {
                            crud.dialogInfo('Selecciona al menos un grupo seguro.');
                            return false;
                        }
                        boton.prop('disabled', true);
                        $.post('../../controllers/mnacimientos_controller', {
                            action: 'unificar_nacimientos',
                            grupos: JSON.stringify(seleccionados)
                        }, null, 'json').done(function(resultado) {
                            $('.message').html(resultado.mensaje || '');
                            if (resultado.resultado === 'ok') {
                                setTimeout(function() { window.location.reload(); }, 1800);
                            }
                        }).fail(function() {
                            crud.dialogInfo('No se pudo completar la unificación. No se aplicaron cambios.');
                        }).always(function() {
                            boton.prop('disabled', false);
                        });
                    }
                });
            }).fail(function() {
                crud.dialogInfo('No se pudo consultar los nacimientos coincidentes.');
            }).always(function() {
                boton.prop('disabled', false);
            });
        });
    ",
];

include("../layouts/_crud_index.php");
?>

<?php include_once("../layouts/pie.php"); ?>