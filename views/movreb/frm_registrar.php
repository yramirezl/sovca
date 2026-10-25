<?php
include("../../lib/validar_session.php");
ValidaSession("../login");
?>
<?php include("../layouts/constantes.php") ?>
<?php include_once("../layouts/cabecera.php") ?>

<link rel="stylesheet" href="../../plugins/confirma/jquery-confirm.min.css" type="text/css" />
<link rel="stylesheet" href="../../css/crud-form.css" type="text/css" />

<style>
/* ============================================================
   WIZARD DE MOVIMIENTO — pasos numerados
   ============================================================ */
.crud-step .crud-form__card-head {
    background: linear-gradient(180deg, #eff6ff 0%, #dbeafe 100%);
    border-bottom: 1px solid #bfdbfe;
}
.crud-step .crud-form__card-head h3 {
    color: #1e40af;
    font-size: 1rem;
    letter-spacing: .02em;
    text-transform: none;
}
.crud-step .crud-form__card-head .crud-step-num {
    width: 28px; height: 28px;
    border-radius: 50%;
    background: #2563eb; color: #fff;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 800; flex-shrink: 0;
}

.crud-resumen {
    background: linear-gradient(180deg, #ecfdf5 0%, #d1fae5 100%);
    border: 1px solid #6ee7b7;
    border-left: 4px solid #16a34a;
}
.crud-resumen .crud-form__card-head {
    background: transparent;
    border-bottom: 1px dashed #6ee7b7;
}
.crud-resumen .crud-form__card-head h3 {
    color: #14532d;
    font-size: 1rem;
    letter-spacing: .02em;
    text-transform: none;
}
.crud-resumen .crud-form__card-body > div {
    padding: 3px 0;
    font-size: 13.5px;
    color: #1f2937;
}
.crud-resumen .crud-form__card-body > div strong {
    color: #14532d;
    font-weight: 700;
}

.crud-radios {
    display: flex; flex-wrap: wrap; gap: 20px;
    padding: 4px 0;
}
.crud-radios label {
    display: inline-flex; align-items: center; gap: 8px;
    font-size: 14px; font-weight: 600; color: #334155;
    cursor: pointer; margin: 0;
    padding: 8px 14px;
    border: 1.5px solid #e5e7eb;
    border-radius: 10px;
    background: #fff;
    transition: all .18s ease;
}
.crud-radios label:hover { border-color: #16a34a; background: #f0fdf4; }
.crud-radios input[type="radio"] {
    width: 18px; height: 18px;
    accent-color: #16a34a;
    cursor: pointer; margin: 0;
}
.crud-radios input[type="radio"]:checked + span { color: #15803d; }

.crud-lista-animales {
    max-height: 320px; overflow-y: auto;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    background: #fff;
    padding: 10px;
}
.crud-lista-animales .crud-vacio-lista {
    color: #94a3b8; font-style: italic; text-align: center;
    padding: 30px 20px; font-size: 13px;
}
.crud-lista-animales table {
    width: 100%; margin: 0; font-size: 13px;
    border-collapse: collapse;
}
.crud-lista-animales thead th {
    background: #f8fafc; color: #475569;
    font-size: 11px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .05em;
    padding: 8px 10px; border-bottom: 1px solid #e5e7eb;
    text-align: left; white-space: nowrap;
}
.crud-lista-animales tbody td {
    padding: 9px 10px;
    border-bottom: 1px solid #eef1f4;
    color: #334155;
    vertical-align: middle;
}
.crud-lista-animales tbody tr:hover { background: #f0fdf4; }
.crud-lista-animales tbody tr.selected { background: #dcfce7; }
.crud-lista-animales input[type="checkbox"] {
    width: 18px; height: 18px; accent-color: #16a34a;
    cursor: pointer; margin: 0;
}

/* ---- Panel de aforo / días de ocupación ---- */
.crud-aforo {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(165px, 1fr));
    gap: 10px;
}
.crud-aforo__item {
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 10px 12px;
}
.crud-aforo__item span {
    display: block;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: #64748b;
    font-weight: 700;
}
.crud-aforo__item strong {
    display: block;
    font-size: 16px;
    color: #0f172a;
    margin-top: 3px;
}
.crud-aforo__dias {
    grid-column: 1 / -1;
    background: linear-gradient(180deg, #ecfdf5 0%, #d1fae5 100%);
    border-color: #6ee7b7;
}
.crud-aforo__dias strong {
    font-size: 22px;
    color: #15803d;
}
.crud-aforo__nota {
    grid-column: 1 / -1;
    font-size: 12.5px;
    color: #92400e;
    background: #fffbeb;
    border: 1px solid #fcd34d;
    border-radius: 8px;
    padding: 8px 10px;
}
.crud-sin-peso { color: #b45309; font-size: 12px; }
</style>

<div class="crud-form">

    <div class="message"></div>

    <header class="crud-form__header">
        <span class="crud-form__icon"><i class="fa fa-exchange"></i></span>
        <div>
            <h3 class="crud-form__title">Nuevo Movimiento de Rebaño</h3>
            <p class="crud-form__subtitle">Registre el movimiento de animales entre lotes y potreros</p>
        </div>
        <div class="crud-form__header-actions">
            <button id="exit_top" type="button" class="btn btn-warning">
                <i class="fa fa-reply"></i> Regresar
            </button>
        </div>
    </header>

    <div class="crud-form__grid">

        <!-- ===================== PASO 1 ===================== -->
        <div class="crud-form__card crud-form__card--full crud-step">
            <div class="crud-form__card-head">
                <span class="crud-step-num">1</span>
                <h3>¿Qué querés cambiar?</h3>
            </div>
            <div class="crud-form__card-body">
                <div class="crud-radios">
                    <label>
                        <input type="radio" name="tipo_mov" value="LOTE" checked>
                        <span>Solo el Lote</span>
                    </label>
                    <label>
                        <input type="radio" name="tipo_mov" value="POTRERO">
                        <span>Solo el Potrero</span>
                    </label>
                    <label>
                        <input type="radio" name="tipo_mov" value="AMBOS">
                        <span>Ambos</span>
                    </label>
                </div>
            </div>
        </div>

        <!-- ===================== PASO 2 ===================== -->
        <div class="crud-form__card crud-form__card--full crud-step">
            <div class="crud-form__card-head">
                <span class="crud-step-num">2</span>
                <h3>¿De dónde salen?</h3>
            </div>
            <div class="crud-form__card-body">
                <div class="crud-form__row">
                    <div class="crud-form__field" id="bloque_lote_origen">
                        <label>Lote Origen *</label>
                        <select id="lote_origen" class="form-control bbb">
                            <option value="">--</option>
                        </select>
                    </div>
                    <div class="crud-form__field" id="bloque_potrero_origen" style="display:none;">
                        <label>Potrero Origen *</label>
                        <select id="potrero_origen" class="form-control bbb">
                            <option value="">--</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===================== PASO 3 ===================== -->
        <div class="crud-form__card crud-form__card--full crud-step">
            <div class="crud-form__card-head">
                <span class="crud-step-num">3</span>
                <h3>¿A dónde van?</h3>
            </div>
            <div class="crud-form__card-body">
                <div class="crud-form__row">
                    <div class="crud-form__field" id="bloque_lote_destino">
                        <label>Lote Destino *</label>
                        <select id="lote_destino" class="form-control bbb">
                            <option value="">--</option>
                        </select>
                    </div>
                    <div class="crud-form__field" id="bloque_potrero_destino" style="display:none;">
                        <label>Potrero Destino *</label>
                        <select id="potrero_destino" class="form-control bbb">
                            <option value="">--</option>
                        </select>
                    </div>
                </div>

                <div class="crud-form__row">
                    <div class="crud-form__field">
                        <label>Motivo *</label>
                        <select id="motivo" class="form-control bbb">
                            <option value="">-- Seleccione --</option>
                            <option value="Crecimiento">Crecimiento</option>
                            <option value="Rotacion de potrero">Rotación de potrero</option>
                            <option value="Sanidad / Cuarentena">Sanidad / Cuarentena</option>
                            <option value="Venta">Venta</option>
                            <option value="Destete">Destete</option>
                            <option value="Produccion">Producción</option>
                            <option value="Secado">Secado</option>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>
                    <div class="crud-form__field">
                        <label>Observaciones</label>
                        <input type="text" id="observaciones" class="form-control" maxlength="500">
                    </div>
                </div>
            </div>
        </div>

        <!-- ===================== DISPONIBILIDAD DE FORRAJE ===================== -->
        <div class="crud-form__card crud-form__card--full crud-step" id="card_aforo" style="display:none;">
            <div class="crud-form__card-head">
                <span class="crud-step-num"><i class="fa fa-leaf"></i></span>
                <h3>Disponibilidad de forraje y días de ocupación</h3>
            </div>
            <div class="crud-form__card-body">
                <div id="aforo_body">
                    <div style="color:#6b7280; font-style:italic; font-size:13px;">
                        Seleccione el potrero destino para ver su aforo.
                    </div>
                </div>
            </div>
        </div>

        <!-- ===================== PASO 4 ===================== -->
        <div class="crud-form__card crud-form__card--full crud-step">
            <div class="crud-form__card-head">
                <span class="crud-step-num">4</span>
                <h3>¿Cuáles animales?</h3>
            </div>
            <div class="crud-form__card-body">

                <div style="display:flex; flex-wrap:wrap; align-items:center; gap:16px; margin-bottom:10px;">
                    <button id="btn_cargar_animales" class="btn btn-info btn-sm" type="button">
                        <i class="fa fa-refresh"></i> Cargar animales del origen
                    </button>
                    <label style="display:inline-flex; align-items:center; gap:8px; margin:0; font-size:13px; font-weight:600; color:#334155; cursor:pointer;">
                        <input type="checkbox" id="check_todos" disabled
                               style="width:18px; height:18px; margin:0; accent-color:#16a34a; cursor:pointer;">
                        Marcar todos
                    </label>
                    <span id="contador_sel" style="color:#6b7280; font-size:13px;"></span>
                </div>

                <div id="lista_animales" class="crud-lista-animales">
                    <div class="crud-vacio-lista">
                        Hacé clic en "Cargar animales del origen" para ver la lista.
                    </div>
                </div>

            </div>
        </div>

        <!-- ===================== RESUMEN ===================== -->
        <div class="crud-form__card crud-form__card--full crud-resumen">
            <div class="crud-form__card-head">
                <i class="fa fa-check-circle" style="color:#16a34a;"></i>
                <h3>Resumen del Movimiento</h3>
            </div>
            <div class="crud-form__card-body" id="resumen_body">
                <div style="color:#6b7280; font-style:italic; font-size:13px;">
                    Complete los pasos anteriores.
                </div>
            </div>
        </div>

    </div><!-- /.crud-form__grid -->

    <div class="crud-form__actions">
        <button id="exit" type="button" class="btn btn-warning" style="display:none;">
            <i class="fa fa-fw fa-reply"></i> Regresar
        </button>
        <button id="save" type="button" class="btn btn-success" style="min-width:200px;">
            <i class="fa fa-fw fa-check"></i> Confirmar movimiento
        </button>
    </div>

</div><!-- /.crud-form -->

<script type="text/javascript" src="../../plugins/confirma/jquery-confirm.min.js"></script>

<script type="text/javascript">
/* ═══════════════════════════════════════════════════════════════════════════
   JAVASCRIPT ORIGINAL — SIN CAMBIOS EN LA LÓGICA
   ═══════════════════════════════════════════════════════════════════════════ */
$(document).ready(function() {

    var lotes_data = [];
    var potreros_data = [];

    $.post("../../controllers/mlotes_controller", { action: "get_lote" }, function(html) {
        var $tmp = $('<select>' + html + '</select>');
        $tmp.find('option').each(function() {
            var v = $(this).val();
            var t = $(this).text();
            if (v !== '') lotes_data.push({ id: v, nombre: t });
        });
        refrescarLotes();
    });

    $.post("../../controllers/mpotreros_controller", { action: "get_potreros" }, function(html) {
        var $tmp = $('<select>' + html + '</select>');
        $tmp.find('option').each(function() {
            var v = $(this).val();
            var t = $(this).text();
            if (v !== '') potreros_data.push({ id: v, nombre: t });
        });
        refrescarPotreros();
    });

    function refrescarLotes() {
        var sel_origen  = $('#lote_origen').val();
        var sel_destino = $('#lote_destino').val();

        var opt_origen = '<option value="">--</option>';
        $.each(lotes_data, function(i, l) {
            if (String(l.id) === String(sel_destino)) return;
            opt_origen += '<option value="' + l.id + '"' + (String(l.id) === String(sel_origen) ? ' selected' : '') + '>' + l.nombre + '</option>';
        });
        $('#lote_origen').html(opt_origen);

        var opt_destino = '<option value="">--</option>';
        $.each(lotes_data, function(i, l) {
            if (String(l.id) === String(sel_origen)) return;
            opt_destino += '<option value="' + l.id + '"' + (String(l.id) === String(sel_destino) ? ' selected' : '') + '>' + l.nombre + '</option>';
        });
        $('#lote_destino').html(opt_destino);
    }

    function refrescarPotreros() {
        var sel_origen  = $('#potrero_origen').val();
        var sel_destino = $('#potrero_destino').val();

        var opt_origen = '<option value="">--</option>';
        $.each(potreros_data, function(i, p) {
            if (String(p.id) === String(sel_destino)) return;
            opt_origen += '<option value="' + p.id + '"' + (String(p.id) === String(sel_origen) ? ' selected' : '') + '>' + p.nombre + '</option>';
        });
        $('#potrero_origen').html(opt_origen);

        var opt_destino = '<option value="">--</option>';
        $.each(potreros_data, function(i, p) {
            if (String(p.id) === String(sel_origen)) return;
            opt_destino += '<option value="' + p.id + '"' + (String(p.id) === String(sel_destino) ? ' selected' : '') + '>' + p.nombre + '</option>';
        });
        $('#potrero_destino').html(opt_destino);
    }

    function aplicarTipo() {
        var tipo = $('input[name=tipo_mov]:checked').val();
        if (tipo === 'LOTE') {
            $('#bloque_lote_origen').show();
            $('#bloque_lote_destino').show();
            $('#bloque_potrero_origen').hide();
            $('#bloque_potrero_destino').hide();
        } else if (tipo === 'POTRERO') {
            $('#bloque_lote_origen').hide();
            $('#bloque_lote_destino').hide();
            $('#bloque_potrero_origen').show();
            $('#bloque_potrero_destino').show();
        } else {
            $('#bloque_lote_origen').show();
            $('#bloque_lote_destino').show();
            $('#bloque_potrero_origen').show();
            $('#bloque_potrero_destino').show();
        }

        $('#lista_animales').html('<div class="crud-vacio-lista">Hacé clic en "Cargar animales del origen" para ver la lista.</div>');
        $('#check_todos').prop('checked', false).prop('disabled', true);
        $('#contador_sel').text('');
        actualizarResumen();
    }

    $('input[name=tipo_mov]').change(aplicarTipo);
    aplicarTipo();

    $('#lote_origen').change(function() {
        refrescarLotes();
        actualizarResumen();
        limpiarAnimales();
    });

    /* ============================================================
       AFORO DEL POTRERO Y DÍAS DE OCUPACIÓN
       - forraje disponible = aforo (kg/ha) × hectáreas
       - consumo diario     = peso total del lote × 10 %
       - días de ocupación  = forraje disponible / consumo diario
       ============================================================ */
    var potreros_aforo = {};        // id -> {nombre, hectareas, aforo, forraje_total, animales}
    var PESO_DEFECTO   = 400;       // kg cuando el animal no tiene pesaje registrado

    function cargarAforoPotreros() {
        $.post("../../controllers/mpotreros_controller", { action: "get_potreros_aforo" }, function(data) {
            var r;
            try { r = (typeof data === 'string') ? JSON.parse(data) : data; }
            catch (e) { r = null; }
            if (!r || !r.ok) return;

            $.each(r.potreros || [], function(i, p) {
                potreros_aforo[String(p.id)] = p;
            });
            renderAforo();
        });
    }

    function numero(v, dec) {
        var n = parseFloat(v);
        if (isNaN(n)) n = 0;
        return n.toLocaleString('es-VE', {
            minimumFractionDigits: dec || 0,
            maximumFractionDigits: dec || 0
        });
    }

    function datoAforo(etiqueta, valor) {
        return '<div class="crud-aforo__item"><span>' + etiqueta + '</span><strong>' + valor + '</strong></div>';
    }

    function renderAforo() {

        var tipo = $('input[name=tipo_mov]:checked').val();

        /* El aforo solo aplica cuando hay potrero destino */
        if (tipo === 'LOTE') {
            $('#card_aforo').hide();
            return;
        }
        $('#card_aforo').show();

        var $box = $('#aforo_body');
        var pid  = $('#potrero_destino').val();
        var p    = pid ? potreros_aforo[String(pid)] : null;

        if (!p) {
            $box.html('<div style="color:#6b7280; font-style:italic; font-size:13px;">Seleccione el potrero destino para ver su aforo.</div>');
            return;
        }

        var ha      = parseFloat(p.hectareas) || 0;
        var aforo   = parseFloat(p.aforo) || 0;
        var forraje = ha * aforo;

        var n = 0, pesoTotal = 0, sinPeso = 0;
        $('.chk_animal:checked').each(function() {
            n++;
            var w = parseFloat($(this).attr('data-peso'));
            if (isNaN(w) || w <= 0) { w = PESO_DEFECTO; sinPeso++; }
            pesoTotal += w;
        });

        var consumo = pesoTotal * 0.10;
        var dias    = (consumo > 0 && forraje > 0) ? Math.floor(forraje / consumo) : 0;

        var html = '<div class="crud-aforo">';
        html += datoAforo('Potrero destino', p.nombre || '-');
        html += datoAforo('Superficie', numero(ha, 2) + ' ha');
        html += datoAforo('Aforo', numero(aforo, 0) + ' kg/ha');
        html += datoAforo('Forraje disponible', numero(forraje, 0) + ' kg');
        html += datoAforo('Animales ya en el potrero', numero(p.animales, 0));
        html += datoAforo('Animales seleccionados', numero(n, 0));
        html += datoAforo('Peso total del lote', numero(pesoTotal, 0) + ' kg');
        html += datoAforo('Consumo diario (10 %)', numero(consumo, 0) + ' kg/día');

        if (n > 0 && forraje > 0) {
            html += '<div class="crud-aforo__item crud-aforo__dias"><span>Días de ocupación estimados</span><strong>'
                 + numero(dias, 0) + ' día(s)</strong></div>';
        }

        if (aforo <= 0) {
            html += '<div class="crud-aforo__nota"><strong>Este potrero no tiene aforo registrado.</strong> '
                 + 'Puede agregarlo en <em>Potreros → Editar → Aforo (kg de forraje verde por hectárea)</em> '
                 + 'para conocer cuántos días puede pastorear el lote.</div>';
        } else if (forraje <= 0) {
            html += '<div class="crud-aforo__nota"><strong>Faltan las hectáreas del potrero</strong> '
                 + 'para calcular el forraje total disponible.</div>';
        }

        if (sinPeso > 0) {
            html += '<div class="crud-aforo__nota">' + sinPeso + ' animal(es) sin pesaje registrado: '
                 + 'se estimaron ' + PESO_DEFECTO + ' kg por animal.</div>';
        }

        html += '</div>';
        $box.html(html);
    }

    cargarAforoPotreros();

    $('#lote_destino').change(function() {
        refrescarLotes();
        actualizarResumen();
    });

    $('#potrero_origen').change(function() {
        refrescarPotreros();
        actualizarResumen();
        limpiarAnimales();
    });

    $('#potrero_destino').change(function() {
        refrescarPotreros();
        actualizarResumen();
        renderAforo();
    });

    $('#motivo').change(actualizarResumen);

    function limpiarAnimales() {
        $('#lista_animales').html('<div class="crud-vacio-lista">Hacé clic en "Cargar animales del origen" para ver la lista.</div>');
        $('#check_todos').prop('checked', false).prop('disabled', true);
        $('#contador_sel').text('');
    }

    $('#btn_cargar_animales').click(function() {
        var tipo = $('input[name=tipo_mov]:checked').val();
        var lo = $('#lote_origen').val();
        var po = $('#potrero_origen').val();
        /* En "Solo el Potrero" el lote destino es el que entrará al potrero:
           si se eligió, se listan los animales de ese lote. */
        var loteFiltro = (tipo === 'POTRERO') ? ($('#lote_destino').val() || 0) : 0;

        if ((tipo === 'LOTE' || tipo === 'AMBOS') && !lo) {
            $.alert({ title: 'Falta lote origen', content: 'Seleccione un lote de origen.', confirmButton: 'OK', confirmButtonClass: 'btn-warning' });
            return;
        }
        if ((tipo === 'POTRERO' || tipo === 'AMBOS') && !po && !loteFiltro) {
            $.alert({
                title: 'Falta el origen',
                content: 'Seleccione el potrero de origen o el lote que entra al potrero destino.',
                confirmButton: 'OK',
                confirmButtonClass: 'btn-warning'
            });
            return;
        }

        $.post("../../controllers/movreb_controller", {
            action:          'get_animales_por_origen',
            tipo_movimiento: tipo,
            lote_origen:     lo,
            potrero_origen:  po,
            lote_filtro:     loteFiltro
        }, function(data) {

            var r;
            try { r = (typeof data === 'string') ? JSON.parse(data) : data; }
            catch (e) { r = { ok: false, animales: [] }; }

            if (!r.ok || r.animales.length === 0) {
                $('#lista_animales').html('<div class="crud-vacio-lista" style="color:#dc2626; font-style:normal;"><strong>No hay animales activos en ese origen.</strong></div>');
                $('#check_todos').prop('disabled', true).prop('checked', false);
                $('#contador_sel').text('');
                actualizarResumen();
                return;
            }

            var html = '<table>'
                     + '<thead><tr>'
                     + '<th style="width:36px;"></th>'
                     + '<th>Código</th><th>Nombre</th><th>Arete</th>'
                     + '<th>Sexo</th><th>Categoría</th><th>Edad</th>'
                     + '</tr></thead><tbody>';

            $.each(r.animales, function(i, a) {
                html += '<tr>'
                     + '<td><input type="checkbox" class="chk_animal" value="' + a.id + '" data-peso="' + (a.peso || '') + '"></td>'
                     + '<td style="font-family:monospace;">' + a.codigo + '</td>'
                     + '<td><strong>' + a.nombre + '</strong></td>'
                     + '<td>' + a.nro_arete + '</td>'
                     + '<td>' + a.sexo + '</td>'
                     + '<td>' + a.categoria + '</td>'
                     + '<td>' + a.edad + '</td>'
                     + '</tr>';
            });
            html += '</tbody></table>';

            $('#lista_animales').html(html);
            $('#check_todos').prop('disabled', false);
            actualizarContador();
            actualizarResumen();
        });
    });

    $('#check_todos').change(function() {
        $('.chk_animal').prop('checked', $(this).prop('checked'));
        actualizarContador();
        actualizarResumen();
        renderAforo();
    });

    $(document).on('change', '.chk_animal', function() {
        actualizarContador();
        actualizarResumen();
        renderAforo();
    });

    function actualizarContador() {
        var total = $('.chk_animal').length;
        var sel = $('.chk_animal:checked').length;
        if (total > 0) {
            $('#contador_sel').html('<strong style="color:#16a34a;">' + sel + '</strong> de ' + total + ' seleccionados');
        } else {
            $('#contador_sel').text('');
        }
    }

    function actualizarResumen() {
        var tipo = $('input[name=tipo_mov]:checked').val();
        var lo_txt = $('#lote_origen option:selected').text();
        var ld_txt = $('#lote_destino option:selected').text();
        var po_txt = $('#potrero_origen option:selected').text();
        var pd_txt = $('#potrero_destino option:selected').text();
        var motivo = $('#motivo').val();
        var sel = $('.chk_animal:checked').length;

        var html = '';
        html += '<div><strong>Tipo:</strong> ' + (tipo || '-') + '</div>';
        if (tipo === 'LOTE' || tipo === 'AMBOS') {
            html += '<div><strong>Lote origen:</strong> ' + (lo_txt || '-') + '</div>';
            html += '<div><strong>Lote destino:</strong> ' + (ld_txt || '-') + '</div>';
        }
        if (tipo === 'POTRERO' || tipo === 'AMBOS') {
            html += '<div><strong>Potrero origen:</strong> ' + (po_txt || '-') + '</div>';
            html += '<div><strong>Potrero destino:</strong> ' + (pd_txt || '-') + '</div>';
        }
        html += '<div><strong>Motivo:</strong> ' + (motivo || '-') + '</div>';
        html += '<div><strong>Animales seleccionados:</strong> ' + sel + '</div>';

        $('#resumen_body').html(html);
    }

    $('#save').click(function() {
        var tipo = $('input[name=tipo_mov]:checked').val();
        var lo = $('#lote_origen').val() || 0;
        var ld = $('#lote_destino').val() || 0;
        var po = $('#potrero_origen').val() || 0;
        var pd = $('#potrero_destino').val() || 0;
        var motivo = $('#motivo').val();
        var obs = $('#observaciones').val();

        var ids = [];
        $('.chk_animal:checked').each(function() { ids.push($(this).val()); });

        if (!motivo) {
            $.alert({ title: 'Falta el motivo', content: 'Seleccione un motivo.', confirmButton: 'OK', confirmButtonClass: 'btn-warning' });
            return;
        }
        if (ids.length === 0) {
            $.alert({ title: 'Sin animales', content: 'Seleccione al menos un animal.', confirmButton: 'OK', confirmButtonClass: 'btn-warning' });
            return;
        }

        $('#save').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Procesando...');

        $.ajax({
            url: "../../controllers/movreb_controller",
            type: "POST",
            dataType: "text",
            data: {
                action:          'crear_movimiento',
                tipo_movimiento: tipo,
                lote_origen:     lo,
                lote_destino:    ld,
                potrero_origen:  po,
                potrero_destino: pd,
                motivo:          motivo,
                observaciones:   obs,
                animales_ids:    JSON.stringify(ids)
            }
        })
        .done(function(data) {
            $('#save').prop('disabled', false).html('<i class="fa fa-fw fa-check"></i> Confirmar movimiento');

            var r;
            try { r = (typeof data === 'string') ? JSON.parse(data) : data; }
            catch (e) {
                console.error('Respuesta cruda del server:', data);
                $.alert({
                    title: 'Respuesta inesperada',
                    content: 'El servidor devolvió algo que no es JSON. Abrí la consola (F12) para ver el detalle.',
                    confirmButton: 'OK',
                    confirmButtonClass: 'btn-danger'
                });
                return;
            }

            if (r.ok) {
                $.alert({
                    title: '¡Listo!',
                    content: r.mensaje,
                    confirmButton: 'Ver historial',
                    confirmButtonClass: 'btn-success',
                    onClose: function() {
                        $(location).attr('href', '../movreb/');
                    }
                });
            } else {
                $.alert({
                    title: 'No se pudo mover',
                    content: r.error || 'Error desconocido.',
                    confirmButton: 'Entendido',
                    confirmButtonClass: 'btn-danger'
                });
            }
        })
        .fail(function(xhr) {
            $('#save').prop('disabled', false).html('<i class="fa fa-fw fa-check"></i> Confirmar movimiento');
            console.error('HTTP ' + xhr.status, xhr.responseText);
            $.alert({
                title: 'Error de conexión',
                content: 'HTTP ' + xhr.status + '. Ver consola (F12).',
                confirmButton: 'OK',
                confirmButtonClass: 'btn-danger'
            });
        });
    });

    $('#exit').click(function() {
        $.confirm({
            title: '¿Desea salir sin guardar?',
            content: false,
            confirmButton: 'Si',
            cancelButton: 'No',
            confirmButtonClass: 'btn-primary',
            cancelButtonClass: 'btn-success',
            confirm: function() { $(location).attr('href', '../movreb/'); }
        });
    });
});
</script>

<script type="text/javascript">
$(document).on('click', '#exit_top', function() { $('#exit').trigger('click'); });
</script>

<?php include_once("../layouts/pie.php") ?>