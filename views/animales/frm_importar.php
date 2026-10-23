<?php
include("../../lib/validar_session.php");
ValidaSession("../login");
?>
<?php include("../layouts/constantes.php") ?>
<?php include_once("../layouts/cabecera.php") ?>

<div class="message"></div>

<!-- Encabezado -->
<div class="row" style="margin-bottom: 15px;">
    <div class="col-md-8">
        <h3 style="margin: 5px 0 0 0; font-weight: 600; color: #2C3440;">
            Importar Animales desde Excel
            <small style="color: #6B7480; font-weight: 400; font-size: 14px;">
                — Carga masiva de fichas de animales
            </small>
        </h3>
    </div>
    <div class="col-md-4 text-right">
        <a href="../animales/" class="btn btn-default" style="margin-top: 5px;">
            <i class="fa fa-arrow-left"></i> Volver
        </a>
    </div>
</div>

<!-- Instrucciones paso a paso -->
<div class="row">
    <div class="col-md-12">
        <div class="box box-primary" style="border-top: 3px solid #22B34E;">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-list-ol"></i> Cómo importar tus animales</h3>
            </div>
            <div class="box-body">
                <div class="row" style="text-align: center;">

                    <div class="col-md-3 col-xs-6">
                        <div style="background:#F4F6F8;border-radius:8px;padding:15px;min-height:140px;">
                            <div style="font-size:32px;color:#22B34E;font-weight:700;">1</div>
                            <div style="font-weight:600;color:#2C3440;margin-top:5px;">Descargá la plantilla</div>
                            <button id="btn_descargar" class="btn btn-success btn-sm" style="margin-top:10px;display:block;width:100%;">
                                <i class="fa fa-download"></i> Plantilla de animales
                            </button>
                            <button id="btn_descargar_cat" class="btn btn-default btn-sm" style="margin-top:6px;display:block;width:100%;"
                                title="Listado de razas, categorías, lotes y servicios ya registrados">
                                <i class="fa fa-list"></i> Catálogo de referencia
                            </button>
                        </div>
                    </div>

                    <div class="col-md-3 col-xs-6">
                        <div style="background:#F4F6F8;border-radius:8px;padding:15px;min-height:140px;">
                            <div style="font-size:32px;color:#22B34E;font-weight:700;">2</div>
                            <div style="font-weight:600;color:#2C3440;margin-top:5px;">Llená el Excel</div>
                            <div style="font-size:12px;color:#6B7480;margin-top:8px;">
                                Completá una fila por animal. Seguí los ejemplos.
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3 col-xs-6">
                        <div style="background:#F4F6F8;border-radius:8px;padding:15px;min-height:140px;">
                            <div style="font-size:32px;color:#22B34E;font-weight:700;">3</div>
                            <div style="font-weight:600;color:#2C3440;margin-top:5px;">Subí el archivo</div>
                            <div style="font-size:12px;color:#6B7480;margin-top:8px;">
                                Arrastralo abajo o hacé clic para elegirlo.
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3 col-xs-6">
                        <div style="background:#F4F6F8;border-radius:8px;padding:15px;min-height:140px;">
                            <div style="font-size:32px;color:#22B34E;font-weight:700;">4</div>
                            <div style="font-weight:600;color:#2C3440;margin-top:5px;">Revisá y confirmá</div>
                            <div style="font-size:12px;color:#6B7480;margin-top:8px;">
                                Antes de guardar vas a poder revisar todo.
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- Zona de carga -->
<div class="row" id="zona_carga">
    <div class="col-md-12">
        <div class="box box-success">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-upload"></i> Subir archivo</h3>
            </div>
            <div class="box-body">
                <div id="drop_zone" style="border:3px dashed #22B34E;border-radius:10px;padding:40px;text-align:center;background:#FAFCFA;cursor:pointer;transition:all 0.2s;">
                    <i class="fa fa-file-excel-o" style="font-size:60px;color:#22B34E;"></i>
                    <h4 style="margin-top:15px;color:#2C3440;">
                        Arrastrá tu archivo CSV aquí<br>
                        <small style="color:#6B7480;font-weight:400;">o hacé clic para seleccionarlo</small>
                    </h4>
                    <input type="file" id="archivo_input" accept=".csv" style="display:none;">
                </div>
                <div id="archivo_info" style="display:none;margin-top:15px;padding:12px;background:#E8F5EC;border-radius:6px;border-left:4px solid #22B34E;">
                    <i class="fa fa-file-text-o" style="color:#22B34E;font-size:18px;"></i>
                    <strong id="archivo_nombre" style="margin-left:8px;"></strong>
                    <span id="archivo_tamano" style="color:#6B7480;font-size:12px;margin-left:8px;"></span>
                    <button id="btn_quitar_archivo" class="btn btn-xs btn-danger pull-right">
                        <i class="fa fa-times"></i> Quitar
                    </button>
                </div>
                <div style="text-align:center;margin-top:20px;">
                    <button id="btn_validar" class="btn btn-success btn-lg" disabled>
                        <i class="fa fa-search"></i> Validar archivo
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Zona de resultados (oculta hasta validar) -->
<div id="zona_resultados" style="display:none;">

    <!-- Resumen -->
    <div class="row">
        <div class="col-md-12">
            <div class="box box-info">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-clipboard-check"></i> Resultado de la validación</h3>
                </div>
                <div class="box-body">
                    <div class="row text-center">
                        <div class="col-md-4">
                            <div style="background:#E8F5EC;padding:15px;border-radius:8px;">
                                <div style="font-size:32px;font-weight:700;color:#22B34E;" id="res_ok">0</div>
                                <div style="color:#2C3440;font-weight:600;">Listos para importar</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div style="background:#FFF8E1;padding:15px;border-radius:8px;">
                                <div style="font-size:32px;font-weight:700;color:#F39C12;" id="res_decisiones">0</div>
                                <div style="color:#2C3440;font-weight:600;">Requieren tu decisión</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div style="background:#FDECEA;padding:15px;border-radius:8px;">
                                <div style="font-size:32px;font-weight:700;color:#E74C3C;" id="res_errores">0</div>
                                <div style="color:#2C3440;font-weight:600;">Con errores</div>
                            </div>
                        </div>
                    </div>
                    <div id="aviso_plan" style="display:none;margin-top:15px;padding:12px;background:#FFF3CD;border-left:4px solid #F39C12;border-radius:4px;color:#856404;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filas con decisión pendiente -->
    <div class="row" id="panel_decisiones" style="display:none;">
        <div class="col-md-12">
            <div class="box box-warning">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-exclamation-triangle" style="color:#F39C12;"></i> Revisá estas coincidencias</h3>
                </div>
                <div class="box-body" id="lista_decisiones"></div>
            </div>
        </div>
    </div>

    <!-- Filas OK -->
    <div class="row" id="panel_ok" style="display:none;">
        <div class="col-md-12">
            <div class="box box-success">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-check-circle" style="color:#22B34E;"></i> Filas listas para importar</h3>
                </div>
                <div class="box-body" style="max-height:300px;overflow-y:auto;">
                    <table class="table table-condensed table-hover" style="font-size:12px;">
                        <thead>
                            <tr style="background:#F4F6F8;">
                                <th style="width:60px;">Fila</th>
                                <th>Nombre</th>
                                <th>Nro Arete</th>
                                <th>Sexo</th>
                                <th>Raza</th>
                                <th>Categoría</th>
                                <th>Lote</th>
                            </tr>
                        </thead>
                        <tbody id="lista_ok"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Filas con error -->
    <div class="row" id="panel_errores" style="display:none;">
        <div class="col-md-12">
            <div class="box box-danger">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-times-circle" style="color:#E74C3C;"></i> Filas con errores</h3>
                </div>
                <div class="box-body" style="max-height:300px;overflow-y:auto;">
                    <table class="table table-condensed" style="font-size:12px;">
                        <thead>
                            <tr style="background:#F4F6F8;">
                                <th style="width:60px;">Fila</th>
                                <th>Problema</th>
                            </tr>
                        </thead>
                        <tbody id="lista_errores"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Botones de confirmación -->
    <div class="row">
        <div class="col-md-12 text-center" style="margin-top:20px;margin-bottom:30px;">
            <button id="btn_cancelar_imp" class="btn btn-default btn-lg" style="margin-right:10px;">
                <i class="fa fa-times"></i> Cancelar
            </button>
            <button id="btn_confirmar" class="btn btn-success btn-lg" disabled>
                <i class="fa fa-save"></i> <span id="btn_confirmar_texto">Confirmar importación</span>
            </button>
        </div>
    </div>

</div><!-- /zona_resultados -->

<style>
    #drop_zone:hover,
    #drop_zone.hover {
        background: #E8F5EC;
        border-color: #1A8C3D;
    }

    #drop_zone.hover {
        transform: scale(1.01);
    }

    .decision-card {
        border: 1px solid #E3E7ED;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 15px;
        background: #FFFFFF;
    }

    .decision-card .valor-ingresado {
        background: #F4F6F8;
        padding: 8px 12px;
        border-radius: 4px;
        font-weight: 600;
        color: #2C3440;
        margin-bottom: 10px;
    }

    .decision-card .candidatos {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 10px;
    }

    .decision-card .btn-decision {
        padding: 8px 14px;
        border-radius: 20px;
        border: 2px solid #D8DEE6;
        background: #FFFFFF;
        cursor: pointer;
        transition: all 0.15s;
        font-size: 13px;
    }

    .decision-card .btn-decision:hover {
        border-color: #22B34E;
        background: #F0F9F2;
    }

    .decision-card .btn-decision.selected {
        border-color: #22B34E;
        background: #22B34E;
        color: #FFFFFF;
        font-weight: 600;
    }

    .decision-card .btn-decision.nueva {
        border-color: #F39C12;
        color: #E67E22;
    }

    .decision-card .btn-decision.nueva.selected {
        background: #F39C12;
        color: #FFFFFF;
    }

    .decision-card .btn-decision.ignorar {
        border-color: #E74C3C;
        color: #C0392B;
    }

    .decision-card .btn-decision.ignorar.selected {
        background: #E74C3C;
        color: #FFFFFF;
    }
</style>

<script type="text/javascript">
    /* ============================================================
   IMPORTADOR DE ANIMALES - FRONTEND
   ============================================================ */

    var archivo_actual = null;
    var resultado_val = null; /* respuesta cruda del endpoint validar */
    var decisiones_usuario = {}; /* {fila_campo: {tipo, id, nombre}} */

    /* ============================================================
       PASO 1 - DESCARGAR PLANTILLA
       ============================================================ */
    $("#btn_descargar").click(function() {
        window.location.href = "../../controllers/mimportador_controller.php?action=descargar_plantilla";
    });

    $("#btn_descargar_cat").click(function() {
        window.location.href = "../../controllers/mimportador_controller.php?action=descargar_catalogo";
    }); 

    /* ============================================================
       PASO 2 - ZONA DE CARGA
       ============================================================ */
    var drop = document.getElementById('drop_zone');
    var input = document.getElementById('archivo_input');

    $('#drop_zone').click(function() {
        input.click();
    });

    ['dragenter', 'dragover'].forEach(function(evt) {
        drop.addEventListener(evt, function(e) {
            e.preventDefault();
            e.stopPropagation();
            drop.classList.add('hover');
        }, false);
    });
    ['dragleave', 'drop'].forEach(function(evt) {
        drop.addEventListener(evt, function(e) {
            e.preventDefault();
            e.stopPropagation();
            drop.classList.remove('hover');
        }, false);
    });
    drop.addEventListener('drop', function(e) {
        var files = e.dataTransfer.files;
        if (files.length > 0) procesarArchivo(files[0]);
    });

    input.addEventListener('change', function() {
        if (this.files.length > 0) procesarArchivo(this.files[0]);
    });

    function procesarArchivo(file) {
        if (!/\.csv$/i.test(file.name)) {
            $.alert({
                title: 'Archivo no válido',
                content: 'El archivo debe ser formato CSV. Guardá tu Excel como "CSV (delimitado por comas)".',
                confirmButton: 'Entendido',
                confirmButtonClass: 'btn-warning',
                type: 'orange',
                icon: 'fa fa-exclamation-triangle'
            });
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            $.alert({
                title: 'Archivo muy grande',
                content: 'El archivo no debe superar los 5 MB.',
                confirmButton: 'Entendido',
                confirmButtonClass: 'btn-warning'
            });
            return;
        }

        archivo_actual = file;
        $('#archivo_nombre').text(file.name);
        var kb = Math.round(file.size / 1024);
        $('#archivo_tamano').text('(' + kb + ' KB)');
        $('#archivo_info').show();
        $('#zona_carga .box-body').find('#drop_zone').hide();
        $('#btn_validar').prop('disabled', false);
    }

    $('#btn_quitar_archivo').click(function(e) {
        e.stopPropagation();
        archivo_actual = null;
        $('#archivo_input').val('');
        $('#archivo_info').hide();
        $('#drop_zone').show();
        $('#btn_validar').prop('disabled', true);
        $('#zona_resultados').hide();
    });

    /* ============================================================
       PASO 3 - VALIDAR
       ============================================================ */
    $('#btn_validar').click(function() {
        if (!archivo_actual) return;

        var btn = $(this);
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Validando...');

        var fd = new FormData();
        fd.append('action', 'validar');
        fd.append('archivo', archivo_actual);

        $.ajax({
                url: '../../controllers/mimportador_controller.php',
                type: 'POST',
                data: fd,
                contentType: false,
                processData: false,
                dataType: 'text'
            })
            .done(function(resp) {
                btn.prop('disabled', false).html('<i class="fa fa-search"></i> Validar archivo');

                var datos;
                try {
                    datos = JSON.parse(resp);
                } catch (e) {
                    console.error('JSON inválido:', resp.substring(0, 500));
                    $.alert({
                        title: 'Error al leer respuesta',
                        content: 'El servidor devolvió un formato inesperado. Revisá la consola (F12).',
                        confirmButton: 'OK',
                        confirmButtonClass: 'btn-danger'
                    });
                    return;
                }

                if (!datos.ok) {
                    $.alert({
                        title: 'No se pudo validar',
                        content: datos.error || 'Error desconocido',
                        confirmButton: 'OK',
                        confirmButtonClass: 'btn-danger'
                    });
                    return;
                }

                resultado_val = datos;
                renderizarResultado(datos);
            })
            .fail(function(xhr) {
                btn.prop('disabled', false).html('<i class="fa fa-search"></i> Validar archivo');
                console.error('HTTP ' + xhr.status, xhr.responseText);
                $.alert({
                    title: 'Error de conexión',
                    content: 'HTTP ' + xhr.status + '. Revisá la consola (F12).',
                    confirmButton: 'OK',
                    confirmButtonClass: 'btn-danger'
                });
            });
    });

    /* ============================================================
       RENDERIZAR RESULTADO
       ============================================================ */
    function renderizarResultado(datos) {
        decisiones_usuario = {};
        $('#zona_resultados').show();
        $('#zona_carga').hide();

        /* Resumen */
        $('#res_ok').text(datos.resumen.ok);
        $('#res_decisiones').text(datos.resumen.decisiones);
        $('#res_errores').text(datos.resumen.errores);

        /* Aviso de plan */
        if (datos.aviso_plan) {
            $('#aviso_plan').html('<i class="fa fa-info-circle"></i> ' + datos.aviso_plan).show();
        } else {
            $('#aviso_plan').hide();
        }

        /* Decisiones pendientes */
        var decisiones = [];
        $.each(datos.filas, function(i, fila) {
            if (fila.decisiones && fila.decisiones.length > 0) {
                $.each(fila.decisiones, function(j, dec) {
                    decisiones.push({
                        _fila: fila._fila,
                        campo: dec.campo,
                        valor_ingresado: dec.valor_ingresado,
                        candidatos: dec.candidatos,
                        datos: fila.datos
                    });
                });
            }
        });

        if (decisiones.length > 0) {
            renderizarDecisiones(decisiones);
            $('#panel_decisiones').show();
        } else {
            $('#panel_decisiones').hide();
        }

        /* Filas OK */
        var ok = [];
        $.each(datos.filas, function(i, fila) {
            if ((!fila.errores || fila.errores.length === 0) && (!fila.decisiones || fila.decisiones.length === 0)) {
                ok.push(fila);
            }
        });

        if (ok.length > 0) {
            var html = '';
            $.each(ok, function(i, fila) {
                var d = fila.datos || {};
                html += '<tr>' +
                    '<td>' + fila._fila + '</td>' +
                    '<td>' + escapar(d.nombre || '') + '</td>' +
                    '<td>' + escapar(d.nro_arete || '') + '</td>' +
                    '<td>' + (d.sexo == 1 ? 'HEMBRA' : (d.sexo == 2 ? 'MACHO' : '')) + '</td>' +
                    '<td>' + nombreDeCatalogo(d.mrazas_id, 'razas') + '</td>' +
                    '<td>' + nombreDeCatalogo(d.categoria_ingreso_id, 'categorias') + '</td>' +
                    '<td>' + nombreDeCatalogo(d.mlotes_id, 'lotes') + '</td>' +
                    '</tr>';
            });
            $('#lista_ok').html(html);
            $('#panel_ok').show();
        } else {
            $('#panel_ok').hide();
        }

        /* Filas con error */
        var errores = [];
        $.each(datos.filas, function(i, fila) {
            if (fila.errores && fila.errores.length > 0) {
                errores.push(fila);
            }
        });

        if (errores.length > 0) {
            var html2 = '';
            $.each(errores, function(i, fila) {
                html2 += '<tr>' +
                    '<td><strong>' + fila._fila + '</strong></td>' +
                    '<td style="color:#C0392B;">' + escapar(fila.errores.join(' | ')) + '</td>' +
                    '</tr>';
            });
            $('#lista_errores').html(html2);
            $('#panel_errores').show();
        } else {
            $('#panel_errores').hide();
        }

        actualizarBotonConfirmar();
        $('html, body').animate({
            scrollTop: $('#zona_resultados').offset().top - 20
        }, 400);
    }

    /* ============================================================
       DECISIONES SOBRE NOMBRES SIMILARES
       ============================================================ */
    function renderizarDecisiones(decisiones) {
        var html = '';
        $.each(decisiones, function(i, dec) {
            var key = dec._fila + '_' + dec.campo;

            html += '<div class="decision-card">';
            html += '<div style="font-weight:600;color:#2C3440;margin-bottom:8px;">';
            html += '<i class="fa fa-flag" style="color:#F39C12;"></i> ';
            html += 'Fila <strong>' + dec._fila + '</strong> — Campo: <strong>' + dec.campo + '</strong>';
            html += '</div>';
            html += '<div class="valor-ingresado">Escribiste: "' + escapar(dec.valor_ingresado) + '"</div>';

            if (dec.sin_match) {
                /* No hay candidatos similares */
                html += '<div style="background:#FFF8E1;padding:8px 12px;border-radius:4px;color:#856404;font-size:13px;margin-bottom:10px;">';
                html += '<i class="fa fa-info-circle"></i> No existe ese valor en el sistema. ¿Qué querés hacer?';
                html += '</div>';
                html += '<div class="candidatos" data-key="' + key + '">';
                html += '<button type="button" class="btn-decision nueva" ' +
                    'data-tipo="nueva" data-nombre="' + escaparAttr(dec.valor_ingresado) + '">' +
                    '➕ Crear "' + escapar(dec.valor_ingresado) + '" como nuevo' +
                    '</button>';
                html += '<button type="button" class="btn-decision ignorar" data-tipo="ignorar">' +
                    '✘ Ignorar esta fila' +
                    '</button>';
                html += '</div>';
            } else {
                /* Hay candidatos similares */
                html += '<div style="font-size:13px;color:#6B7480;margin-bottom:5px;">¿A qué corresponde?</div>';
                html += '<div class="candidatos" data-key="' + key + '">';

                $.each(dec.candidatos, function(j, cand) {
                    html += '<button type="button" class="btn-decision" ' +
                        'data-tipo="existente" data-id="' + cand.id + '" data-nombre="' + escaparAttr(cand.nombre) + '">' +
                        '✅ Es: ' + escapar(cand.nombre) + ' (' + cand.similitud + '%)' +
                        '</button>';
                });

                html += '<button type="button" class="btn-decision nueva" ' +
                    'data-tipo="nueva" data-nombre="' + escaparAttr(dec.valor_ingresado) + '">' +
                    '➕ Crear "' + escapar(dec.valor_ingresado) + '" como nuevo' +
                    '</button>';

                html += '<button type="button" class="btn-decision ignorar" data-tipo="ignorar">' +
                    '✘ Ignorar esta fila' +
                    '</button>';

                html += '</div>';
            }

            html += '</div>';
        });

        $('#lista_decisiones').html(html);

        /* Handler de click */
        $('#lista_decisiones').off('click', '.btn-decision').on('click', '.btn-decision', function() {
            var $btn = $(this);
            var $grupo = $btn.closest('.candidatos');
            var key = $grupo.data('key');

            $grupo.find('.btn-decision').removeClass('selected');
            $btn.addClass('selected');

            decisiones_usuario[key] = {
                tipo: $btn.data('tipo'),
                id: $btn.data('id') || null,
                nombre: $btn.data('nombre') || null
            };

            actualizarBotonConfirmar();
        });
    }

    /* ============================================================
       HABILITAR / DESHABILITAR BOTÓN CONFIRMAR
       ============================================================ */
    function actualizarBotonConfirmar() {
        if (!resultado_val) return;

        /* Contar cuántas decisiones totales hay y cuántas resueltas */
        var total_dec = 0;
        $.each(resultado_val.filas, function(i, fila) {
            if (fila.decisiones) total_dec += fila.decisiones.length;
        });
        var resueltas = Object.keys(decisiones_usuario).length;

        var filas_ok = resultado_val.resumen.ok;
        var filas_dec = resultado_val.resumen.decisiones;

        /* Casos */
        var hay_algo_para_importar = (filas_ok + filas_dec) > 0;
        var decisiones_completas = (total_dec === 0) || (resueltas === total_dec);

        if (hay_algo_para_importar && decisiones_completas) {
            var total_est = filas_ok;
            $.each(resultado_val.filas, function(i, fila) {
                if (fila.decisiones && fila.decisiones.length > 0) {
                    var todas_existentes_o_nuevas = true;
                    $.each(fila.decisiones, function(j, dec) {
                        var key = fila._fila + '_' + dec.campo;
                        var sel = decisiones_usuario[key];
                        if (!sel || sel.tipo === 'ignorar') todas_existentes_o_nuevas = false;
                    });
                    if (todas_existentes_o_nuevas) total_est++;
                }
            });

            $('#btn_confirmar').prop('disabled', false);
            $('#btn_confirmar_texto').text('Confirmar importación (' + total_est + ' animales)');
        } else {
            $('#btn_confirmar').prop('disabled', true);
            if (!decisiones_completas) {
                $('#btn_confirmar_texto').text('Resolvé las ' + (total_dec - resueltas) + ' coincidencias pendientes');
            } else {
                $('#btn_confirmar_texto').text('Confirmar importación');
            }
        }
    }

    /* ============================================================
       PASO 4 - CONFIRMAR
       ============================================================ */
    $('#btn_confirmar').click(function() {
        /* Armar payload con las filas listas */
        var filas_payload = [];

        $.each(resultado_val.filas, function(i, fila) {
            /* Filas con error → nunca */
            if (fila.errores && fila.errores.length > 0) return;

            /* Filas sin decisiones → listas tal cual */
            if (!fila.decisiones || fila.decisiones.length === 0) {
                filas_payload.push({
                    listo_para_importar: true,
                    datos: fila.datos
                });
                return;
            }

            /* Filas con decisiones → aplicar lo que eligió el usuario */
            var datos = $.extend({}, fila.datos);
            var todo_ok = true;

            $.each(fila.decisiones, function(j, dec) {
                var key = fila._fila + '_' + dec.campo;
                var sel = decisiones_usuario[key];
                if (!sel) {
                    todo_ok = false;
                    return false;
                }

                if (sel.tipo === 'ignorar') {
                    todo_ok = false;
                    return false;
                }

                if (sel.tipo === 'existente') {
                    /* Mapear campo → variable destino */
                    if (dec.campo === 'raza') datos.mrazas_id = sel.id;
                    else if (dec.campo === 'aptitud') datos.aptitud = sel.id;
                    else if (dec.campo === 'categoria_ingreso') datos.categoria_ingreso_id = sel.id;
                    else if (dec.campo === 'categoria_futura') datos.categoria_futura_id = sel.id;
                    else if (dec.campo === 'lote') datos.mlotes_id = sel.id;
                    else if (dec.campo === 'servicio') datos.servicio = sel.id;
                } else if (sel.tipo === 'nueva') {
                    if (dec.campo === 'raza') datos.nueva_raza = sel.nombre;
                    else if (dec.campo === 'aptitud') datos.nueva_aptitud = sel.nombre;
                    else if (dec.campo === 'categoria_ingreso') datos.nueva_categoria_ingreso = sel.nombre;
                    else if (dec.campo === 'categoria_futura') datos.nueva_categoria_futura = sel.nombre;
                    else if (dec.campo === 'lote') datos.nuevo_lote = sel.nombre;
                    else if (dec.campo === 'servicio') datos.nuevo_servicio = sel.nombre;
                    else {
                        todo_ok = false;
                        return false;
                    }
                }
            });

            if (todo_ok) {
                filas_payload.push({
                    listo_para_importar: true,
                    datos: datos
                });
            }
        });

        if (filas_payload.length === 0) {
            $.alert({
                title: 'Nada para importar',
                content: 'No hay filas listas para importar.',
                confirmButton: 'OK',
                confirmButtonClass: 'btn-warning'
            });
            return;
        }

        $.confirm({
            title: '¿Confirmar importación?',
            content: 'Se van a importar <strong>' + filas_payload.length + '</strong> animales al sistema.<br><br>Esta acción no se puede deshacer.',
            confirmButton: 'Sí, importar',
            cancelButton: 'Cancelar',
            confirmButtonClass: 'btn-success',
            cancelButtonClass: 'btn-default',
            confirm: function() {
                ejecutarImportacion(filas_payload);
            }
        });
    });

    function ejecutarImportacion(filas_payload) {
        var btn = $('#btn_confirmar');
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Importando...');

        $.post('../../controllers/mimportador_controller.php', {
                action: 'confirmar',
                filas: JSON.stringify(filas_payload)
            },
            function(resp) {
                var datos;
                try {
                    datos = JSON.parse(resp);
                } catch (e) {
                    console.error('JSON inválido:', resp.substring(0, 500));
                    btn.prop('disabled', false).html('<i class="fa fa-save"></i> Confirmar importación');
                    $.alert({
                        title: 'Error al leer respuesta',
                        content: 'Revisá la consola (F12).',
                        confirmButton: 'OK',
                        confirmButtonClass: 'btn-danger'
                    });
                    return;
                }

                if (datos.ok && datos.resultado) {
                    var r = datos.resultado;
                    var msg = '<div style="text-align:left;">' +
                        '<p><strong>✅ Insertados:</strong> ' + r.insertados + '</p>';
                    if (r.errores && r.errores.length > 0) {
                        msg += '<p><strong>⚠️ No insertados:</strong> ' + r.errores.length + '</p>';
                        msg += '<ul style="max-height:150px;overflow-y:auto;font-size:12px;">';
                        $.each(r.errores, function(i, e) {
                            msg += '<li>' + escapar(e) + '</li>';
                        });
                        msg += '</ul>';
                    }
                    msg += '</div>';

                    $.alert({
                        title: '¡Importación finalizada!',
                        content: msg,
                        confirmButton: 'Ver animales',
                        confirmButtonClass: 'btn-success',
                        onClose: function() {
                            window.location.href = '../animales/';
                        }
                    });
                } else {
                    btn.prop('disabled', false).html('<i class="fa fa-save"></i> Confirmar importación');
                    $.alert({
                        title: 'Error al importar',
                        content: (datos.error || 'Error desconocido'),
                        confirmButton: 'OK',
                        confirmButtonClass: 'btn-danger'
                    });
                }
            }, 'text'
        ).fail(function(xhr) {
            btn.prop('disabled', false).html('<i class="fa fa-save"></i> Confirmar importación');
            console.error('HTTP ' + xhr.status, xhr.responseText);
            $.alert({
                title: 'Error de conexión',
                content: 'HTTP ' + xhr.status,
                confirmButton: 'OK',
                confirmButtonClass: 'btn-danger'
            });
        });
    }

    /* ============================================================
       CANCELAR
       ============================================================ */
    $('#btn_cancelar_imp').click(function() {
        $.confirm({
            title: '¿Descartar la importación?',
            content: 'Se va a limpiar todo y volvés al inicio.',
            confirmButton: 'Sí, descartar',
            cancelButton: 'No',
            confirmButtonClass: 'btn-warning',
            cancelButtonClass: 'btn-default',
            confirm: function() {
                window.location.reload();
            }
        });
    });

    /* ============================================================
       HELPERS
       ============================================================ */
    function escapar(s) {
        if (s === null || s === undefined) return '';
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function escaparAttr(s) {
        return escapar(s).replace(/'/g, '&#39;');
    }

    function nombreDeCatalogo(id, catalogo) {
        if (!resultado_val || !resultado_val.catalogos[catalogo]) return '';
        var items = resultado_val.catalogos[catalogo];
        for (var i = 0; i < items.length; i++) {
            if (items[i].id == id) return escapar(items[i].nombre);
        }
        return '—';
    }
</script>

<?php include_once("../layouts/pie.php") ?>