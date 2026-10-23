<?php
include("../../lib/validar_session.php");
ValidaSession("../login");
?>
<?php include("../layouts/constantes.php") ?>
<?php include_once("../layouts/cabecera.php") ?>

<style>
.mig-wrap { max-width: 1100px; margin: 0 auto; }
.mig-steps { display: flex; gap: 10px; margin-bottom: 25px; }
.mig-step { flex: 1; background: #fff; border: 2px solid #e5e8eb; border-radius: 10px; padding: 14px 16px; text-align: center; transition: all .2s; }
.mig-step .num { display: inline-block; width: 30px; height: 30px; line-height: 30px; border-radius: 50%; background: #e5e8eb; color: #8895a4; font-weight: 700; margin-bottom: 6px; }
.mig-step .lbl { font-size: 12px; color: #8895a4; font-weight: 600; }
.mig-step.active { border-color: #2f8f4a; background: #f0fbf3; }
.mig-step.active .num { background: #2f8f4a; color: #fff; }
.mig-step.active .lbl { color: #1b5e20; }
.mig-step.done { border-color: #2f8f4a; }
.mig-step.done .num { background: #2f8f4a; color: #fff; }

.mig-card { background: #fff; border-radius: 10px; border: 1px solid #e5e8eb; padding: 24px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,.04); }
.mig-card h3 { margin-top: 0; color: #233e2d; font-size: 18px; }

.mig-panel { display: none; }
.mig-panel.active { display: block; }

.form-row { display: flex; gap: 16px; margin-bottom: 16px; flex-wrap: wrap; }
.form-col { flex: 1; min-width: 240px; }
.form-col label { font-weight: 600; color: #5a6874; font-size: 12.5px; margin-bottom: 4px; display: block; }

.mig-select, .mig-input, .mig-textarea { width: 100%; padding: 10px 12px; border: 1px solid #d5dbe1; border-radius: 6px; font-size: 13.5px; }
.mig-select:focus, .mig-input:focus, .mig-textarea:focus { border-color: #2f8f4a; outline: none; box-shadow: 0 0 0 3px rgba(47,143,74,.1); }

.tipo-hint { font-size: 11.5px; color: #1b5e20; margin-top: 4px; padding: 4px 8px; background: #f0fbf3; border-radius: 4px; display: none; }
.tipo-hint.show { display: block; }

.animal-list { max-height: 480px; overflow-y: auto; border: 1px solid #e5e8eb; border-radius: 8px; background: #fafbfc; }
.animal-row { display: flex; align-items: center; gap: 12px; padding: 10px 14px; border-bottom: 1px solid #eef1f4; cursor: pointer; transition: background .1s; }
.animal-row:hover { background: #fff; }
.animal-row.selected { background: #e8f5e9; }
.animal-row input[type=checkbox] { width: 18px; height: 18px; cursor: pointer; }
.animal-info { flex: 1; min-width: 0; }
.animal-name { font-weight: 700; color: #233e2d; font-size: 13.5px; }
.animal-meta { font-size: 11.5px; color: #8895a4; margin-top: 2px; }
.animal-arete { background: #f0f4f8; color: #5a6874; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 700; }

.search-bar { display: flex; gap: 8px; margin-bottom: 8px; }
.search-bar input { flex: 1; }
.search-bar button { padding: 10px 18px; }

#filter_count { font-size: 12px; color: #2f8f4a; font-weight: 600; margin-bottom: 10px; padding: 4px 8px; background: #f0fbf3; border-radius: 4px; display: none; }

.mig-toolbar { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; background: #f8fafc; border-radius: 8px; margin-top: 12px; }
.mig-selected-count { font-weight: 700; color: #2f8f4a; }

.mig-btn { padding: 10px 24px; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 13.5px; transition: all .15s; text-decoration: none !important; display: inline-block; line-height: 1.4; }
.mig-btn-primary { background: #2f8f4a; color: #fff !important; }
.mig-btn-primary:hover { background: #237a3c; color: #fff !important; }
.mig-btn-secondary { background: #e5e8eb; color: #5a6874 !important; }
.mig-btn-secondary:hover { background: #d5dbe1; color: #5a6874 !important; }
.mig-btn:disabled { opacity: 0.5; cursor: not-allowed; }

.mig-actions { display: flex; justify-content: space-between; margin-top: 20px; padding-top: 20px; border-top: 1px solid #eef1f4; }

.alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; font-size: 13px; }
.alert-warn { background: #fff8e1; border-left: 4px solid #f39c12; color: #7a5c00; }
.alert-info { background: #e8f5e9 !important; background-color: #e8f5e9 !important; border-left: 4px solid #2f8f4a !important; color: #1b5e20 !important; }
.alert-ok   { background: #e8f5e9; border-left: 4px solid #2f8f4a; color: #1b5e20; }
.alert-err  { background: #fdecea; border-left: 4px solid #e74c3c; color: #922b21; }

.summary-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px dashed #eef1f4; font-size: 13px; }
.summary-row:last-child { border-bottom: none; }
.summary-row .lbl { color: #8895a4; }
.summary-row .val { font-weight: 700; color: #233e2d; }

.resultado-item { padding: 10px 12px; background: #f0fbf3; border-left: 3px solid #2f8f4a; border-radius: 4px; margin-bottom: 8px; font-size: 13px; }
.resultado-item .cod { font-family: monospace; font-weight: 700; color: #2f8f4a; }
.resultado-item .arrow { margin: 0 8px; color: #8895a4; }
.resultado-item .autocreados { display: block; font-size: 11.5px; color: #7a5c00; margin-top: 4px; padding-left: 12px; }
</style>

<div class="mig-wrap">

    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:20px;">
        <div>
            <h2 style="margin:0; color:#233e2d;">Migración de Animales</h2>
            <p style="color:#8895a4; margin:4px 0 0 0; font-size:13px;">
                Migrá animales entre tus fundos conservando el historial en cada uno.
            </p>
        </div>
        <a href="../inicio/" class="mig-btn mig-btn-secondary">
            <i class="fa fa-arrow-left"></i> Volver
        </a>
    </div>

    <div class="mig-steps">
        <div class="mig-step active" data-step="1">
            <div class="num">1</div>
            <div class="lbl">FUNDOS Y MOTIVO</div>
        </div>
        <div class="mig-step" data-step="2">
            <div class="num">2</div>
            <div class="lbl">SELECCIÓN DE ANIMALES</div>
        </div>
        <div class="mig-step" data-step="3">
            <div class="num">3</div>
            <div class="lbl">CONFIRMACIÓN</div>
        </div>
    </div>

    <!-- PASO 1 -->
    <div class="mig-panel active" id="panel-1">
        <div class="mig-card">
            <h3><i class="fa fa-exchange"></i> Configuración de la migración</h3>

            <div class="alert alert-info">
                <i class="fa fa-info-circle"></i>
                <strong>Solo se permite migrar entre fundos del mismo tipo de ganadería.</strong>
                Al seleccionar el fundo origen, el destino se filtrará automáticamente.
            </div>

            <div class="form-row">
                <div class="form-col">
                    <label>Fundo Origen</label>
                    <select id="fundo_origen" class="mig-select">
                        <option value="">— Seleccione —</option>
                    </select>
                </div>
                <div class="form-col">
                    <label>Fundo Destino</label>
                    <select id="fundo_destino" class="mig-select">
                        <option value="">— Seleccione —</option>
                    </select>
                    <div class="tipo-hint" id="tipo_hint"></div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-col">
                    <label>Motivo del traslado *</label>
                    <input type="text" id="motivo" class="mig-input"
                           placeholder="Ej: Reorganización de hatos, venta interna, etc.">
                </div>
            </div>

            <div class="form-row">
                <div class="form-col">
                    <label>Observaciones (opcional)</label>
                    <textarea id="observaciones" class="mig-textarea" rows="3"
                              placeholder="Notas adicionales sobre el traslado"></textarea>
                </div>
            </div>

            <div class="mig-actions">
                <div></div>
                <button type="button" class="mig-btn mig-btn-primary" id="btn_paso1">
                    Siguiente <i class="fa fa-arrow-right"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- PASO 2 -->
    <div class="mig-panel" id="panel-2">
        <div class="mig-card">
            <h3><i class="fa fa-list"></i> Selección de animales</h3>

            <div class="alert alert-info" id="info_resumen"></div>

            <div class="search-bar">
                <input type="text" id="buscar" class="mig-input"
                       placeholder="Buscar por nombre, arete o código... (filtra en vivo)">
                <button type="button" class="mig-btn mig-btn-secondary" id="btn_buscar">
                    <i class="fa fa-search"></i> Buscar
                </button>
                <button type="button" class="mig-btn mig-btn-secondary" id="btn_todos">
                    Seleccionar todos
                </button>
                <button type="button" class="mig-btn mig-btn-secondary" id="btn_ninguno">
                    Quitar todos
                </button>
            </div>

            <div id="filter_count"></div>

            <div class="animal-list" id="animal_list">
                <div style="padding:40px; text-align:center; color:#8895a4;">
                    Cargue la lista de animales...
                </div>
            </div>

            <div class="mig-toolbar">
                <div>
                    <span class="mig-selected-count" id="sel_count">0</span>
                    <span style="color:#8895a4;">de</span>
                    <span id="total_count">0</span>
                    <span style="color:#8895a4;">seleccionados</span>
                </div>
            </div>

            <div class="mig-actions">
                <button type="button" class="mig-btn mig-btn-secondary" id="btn_atras2">
                    <i class="fa fa-arrow-left"></i> Atrás
                </button>
                <button type="button" class="mig-btn mig-btn-primary" id="btn_paso2">
                    Siguiente <i class="fa fa-arrow-right"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- PASO 3 -->
    <div class="mig-panel" id="panel-3">
        <div class="mig-card">
            <h3><i class="fa fa-check-circle"></i> Confirmación</h3>

            <div class="alert alert-warn">
                <strong>⚠️ Esta acción es irreversible desde aquí.</strong><br>
                Los animales seleccionados se <strong>desincorporarán</strong> del fundo origen
                por el motivo "Migración" y se <strong>inscribirán como nuevos</strong> en el
                fundo destino con su historial intacto en ambos lados.
                <br><br>
                <strong>Catálogos:</strong> Si la raza o categoría del animal no existe en el fundo
                destino, se creará automáticamente una copia.
            </div>

            <div id="resumen_final"></div>
            <div id="advertencias_box"></div>

            <div class="mig-actions">
                <button type="button" class="mig-btn mig-btn-secondary" id="btn_atras3">
                    <i class="fa fa-arrow-left"></i> Atrás
                </button>
                <button type="button" class="mig-btn mig-btn-primary" id="btn_ejecutar">
                    <i class="fa fa-check"></i> Ejecutar migración
                </button>
            </div>
        </div>

        <div class="mig-card" id="resultado_box" style="display:none;">
            <h3><i class="fa fa-flag-checkered"></i> Resultado</h3>
            <div id="resultado_contenido"></div>
        </div>
    </div>

</div>

<script>
window.MIG_STATE = {
    PASO: 1,
    FUNDOS: [],
    ANIMALES: [],
    SELECCIONADOS: {},
    EJECUTANDO: false
};

(function() {
    'use strict';

    console.log('=== [MIGRACION] Wizard inicializado ===');

    var S = window.MIG_STATE;

    function irPaso(n) {
        S.PASO = n;
        $('.mig-panel').removeClass('active');
        $('#panel-' + n).addClass('active');

        $('.mig-step').removeClass('active done');
        $('.mig-step').each(function() {
            var sn = parseInt($(this).attr('data-step'), 10);
            if (sn === n) $(this).addClass('active');
            else if (sn < n) $(this).addClass('done');
        });

        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function poblarOrigen() {
        var html = '<option value="">— Seleccione —</option>';
        S.FUNDOS.forEach(function(f) {
            html += '<option value="' + f.id + '">' + f.nombre + ' (' + f.tipo_txt + ')</option>';
        });
        $('#fundo_origen').html(html);

        // Reset destino
        $('#fundo_destino').html('<option value="">— Seleccione —</option>');
        $('#tipo_hint').removeClass('show');
    }

    function cargarFundos() {
        $.post('../../controllers/migracion_controller.php',
            { action: 'listar_fundos' },
            function(d) {
                if (typeof d === 'string') {
                    try { d = JSON.parse(d); } catch (e) {
                        console.error('[MIGRACION] JSON parse error:', e.message);
                        return;
                    }
                }
                if (!d.ok) return;
                S.FUNDOS = d.fundos || [];
                console.log('[MIGRACION] Fundos cargados:', S.FUNDOS);
                poblarOrigen();
            },
            'json'
        ).fail(function(xhr) {
            console.error('[MIGRACION] Fetch error:', xhr.responseText);
        });
    }

    cargarFundos();

    // ─────────────────────────────────────────────────────
    // FILTRADO POR TIPO DE GANADERÍA
    // ─────────────────────────────────────────────────────
    $('#fundo_origen').on('change', function() {
        var origenId = $(this).val();
        var origen = S.FUNDOS.find(function(f) { return String(f.id) === String(origenId); });

        var hint = $('#tipo_hint');

        if (!origen) {
            $('#fundo_destino').html('<option value="">— Seleccione —</option>');
            hint.removeClass('show');
            return;
        }

        var html = '<option value="">— Seleccione —</option>';
        var compatibles = 0;

        S.FUNDOS.forEach(function(f) {
            if (String(f.id) === String(origenId)) return; // excluir origen
            if (f.tipo_ganaderia !== origen.tipo_ganaderia) return; // excluir tipo distinto
            html += '<option value="' + f.id + '">' + f.nombre + '</option>';
            compatibles++;
        });

        $('#fundo_destino').html(html);

        if (compatibles === 0) {
            hint.html('<i class="fa fa-exclamation-triangle"></i> No hay otros fundos de ganadería <strong>' + origen.tipo_txt + '</strong> disponibles para migrar.')
                .addClass('show')
                .css({ 'background': '#fdecea', 'color': '#922b21' });
        } else {
            hint.html('<i class="fa fa-check-circle"></i> Mostrando solo fundos de ganadería <strong>' + origen.tipo_txt + '</strong> (' + compatibles + ' compatibles)')
                .addClass('show')
                .css({ 'background': '#f0fbf3', 'color': '#1b5e20' });
        }
    });

    // ─────────────────────────────────────────────────────
    // PASO 1 → 2
    // ─────────────────────────────────────────────────────
    $('#btn_paso1').on('click', function() {
        var fo = $('#fundo_origen').val();
        var fd = $('#fundo_destino').val();
        var mo = $('#motivo').val().trim();

        if (!fo) { alert('Seleccione el fundo origen'); return; }
        if (!fd) { alert('Seleccione el fundo destino'); return; }
        if (fo === fd) { alert('Los fundos deben ser distintos'); return; }
        if (!mo) { alert('Ingrese el motivo del traslado'); return; }

        var nom_o = S.FUNDOS.find(function(f) { return String(f.id) === String(fo); });
        var nom_d = S.FUNDOS.find(function(f) { return String(f.id) === String(fd); });

        // Validar tipo en cliente también
        if (nom_o && nom_d && nom_o.tipo_ganaderia !== nom_d.tipo_ganaderia) {
            alert('Los fundos deben ser del mismo tipo de ganadería.');
            return;
        }

        $('#info_resumen').html(
            '<i class="fa fa-info-circle"></i> Migrando desde <strong>' +
            (nom_o ? nom_o.nombre : '—') + '</strong> hacia <strong>' +
            (nom_d ? nom_d.nombre : '—') + '</strong> (' + (nom_o ? nom_o.tipo_txt : '') + ')'
        );

        cargarAnimales();
    });

    // ─────────────────────────────────────────────────────
    // CARGA DE ANIMALES
    // ─────────────────────────────────────────────────────
    function cargarAnimales() {
        var fo = $('#fundo_origen').val();

        $('#animal_list').html('<div style="padding:40px; text-align:center; color:#8895a4;"><i class="fa fa-spinner fa-spin"></i> Cargando...</div>');

        $.post('../../controllers/migracion_controller.php',
            { action: 'listar_animales', fundo_id: fo },
            function(d) {
                if (typeof d === 'string') {
                    try { d = JSON.parse(d); } catch (e) {
                        console.error('[MIGRACION] JSON parse error:', e.message);
                        $('#animal_list').html('<div style="padding:20px; color:#e74c3c;">Error parseando respuesta</div>');
                        return;
                    }
                }
                if (!d.ok) {
                    $('#animal_list').html('<div style="padding:20px; color:#e74c3c;">' + (d.msg || 'Error') + '</div>');
                    return;
                }
                S.ANIMALES = d.animales || [];
                renderAnimales();
                $('#total_count').text(S.ANIMALES.length);

                $('#buscar').val('');
                aplicarFiltro();

                if (S.PASO === 1) irPaso(2);
            },
            'json'
        ).fail(function(xhr) {
            console.error('[MIGRACION] Fetch error:', xhr.responseText);
        });
    }

    function renderAnimales() {
        if (S.ANIMALES.length === 0) {
            $('#animal_list').html('<div style="padding:40px; text-align:center; color:#8895a4;">No hay animales elegibles en este fundo.</div>');
            return;
        }

        var html = '';
        S.ANIMALES.forEach(function(a) {
            var checked = S.SELECCIONADOS[a.id] ? 'checked' : '';
            var sel = S.SELECCIONADOS[a.id] ? 'selected' : '';
            html += '<div class="animal-row ' + sel + '" data-id="' + a.id + '">';
            html +=   '<input type="checkbox" ' + checked + '>';
            html +=   '<div class="animal-info">';
            html +=     '<div class="animal-name">' + a.nombre + '</div>';
            html +=     '<div class="animal-meta">' + a.codigo + ' · ' + a.sexo + ' · ' + a.anios + ' años · ' + a.estatus + '</div>';
            html +=   '</div>';
            html +=   '<span class="animal-arete">' + (a.nro_arete || 'S/A') + '</span>';
            html += '</div>';
        });
        $('#animal_list').html(html);

        $('#animal_list .animal-row').off('click').on('click', function(e) {
            if (e.target.tagName === 'INPUT') return;
            var cb = $(this).find('input[type=checkbox]');
            cb.prop('checked', !cb.prop('checked'));
            toggleAnimal($(this), cb.prop('checked'));
        });

        $('#animal_list .animal-row input[type=checkbox]').off('change').on('change', function() {
            var row = $(this).closest('.animal-row');
            toggleAnimal(row, $(this).prop('checked'));
        });

        actualizarContador();
    }

    function toggleAnimal(row, checked) {
        var id = parseInt(row.attr('data-id'), 10);
        if (checked) {
            S.SELECCIONADOS[id] = S.ANIMALES.find(function(a) { return a.id === id; });
            row.addClass('selected');
        } else {
            delete S.SELECCIONADOS[id];
            row.removeClass('selected');
        }
        actualizarContador();
    }

    function actualizarContador() {
        var n = Object.keys(S.SELECCIONADOS).length;
        $('#sel_count').text(n);
    }

    // ─────────────────────────────────────────────────────
    // FILTRO EN VIVO
    // ─────────────────────────────────────────────────────
    function aplicarFiltro() {
        var input = document.getElementById('buscar');
        if (!input) return;

        var q = (input.value || '').trim().toLowerCase();
        var rows = document.querySelectorAll('#animal_list .animal-row');
        var visibles = 0;

        rows.forEach(function(row) {
            var texto = (row.textContent || '').toLowerCase().replace(/\s+/g, ' ');
            var coincide = (q === '' || texto.indexOf(q) !== -1);
            row.style.display = coincide ? '' : 'none';
            if (coincide) visibles++;
        });

        var contador = document.getElementById('filter_count');
        if (contador) {
            if (q === '') {
                contador.style.display = 'none';
            } else {
                contador.textContent = visibles + ' coincidencia' + (visibles === 1 ? '' : 's') + ' para "' + input.value + '"';
                contador.style.display = 'block';
            }
        }
    }

    $('#buscar').on('input keyup change', aplicarFiltro);

    $('#btn_buscar').on('click', function() {
        aplicarFiltro();
        $('#buscar').focus();
    });

    $('#btn_todos').on('click', function() {
        S.ANIMALES.forEach(function(a) { S.SELECCIONADOS[a.id] = a; });
        renderAnimales();
        aplicarFiltro();
    });

    $('#btn_ninguno').on('click', function() {
        S.SELECCIONADOS = {};
        renderAnimales();
        aplicarFiltro();
    });

    $('#btn_atras2').on('click', function() { irPaso(1); });

    // ─────────────────────────────────────────────────────
    // PASO 2 → 3
    // ─────────────────────────────────────────────────────
    $('#btn_paso2').on('click', function() {
        var ids = Object.keys(S.SELECCIONADOS);
        if (ids.length === 0) { alert('Seleccione al menos un animal'); return; }

        $.post('../../controllers/migracion_controller.php',
            {
                action: 'validar',
                fundo_origen_id: $('#fundo_origen').val(),
                fundo_destino_id: $('#fundo_destino').val(),
                animales: ids.join(',')
            },
            function(d) {
                if (typeof d === 'string') {
                    try { d = JSON.parse(d); } catch (e) {
                        alert('Error parseando validación');
                        return;
                    }
                }

                if (!d.ok) {
                    alert(d.msg || 'Error en validación');
                    return;
                }

                var fo = S.FUNDOS.find(function(f) { return String(f.id) === $('#fundo_origen').val(); });
                var fd2 = S.FUNDOS.find(function(f) { return String(f.id) === $('#fundo_destino').val(); });

                var html = '';
                html += '<div class="summary-row"><span class="lbl">Fundo Origen</span><span class="val">' + (fo ? fo.nombre + ' (' + fo.tipo_txt + ')' : '—') + '</span></div>';
                html += '<div class="summary-row"><span class="lbl">Fundo Destino</span><span class="val">' + (fd2 ? fd2.nombre + ' (' + fd2.tipo_txt + ')' : '—') + '</span></div>';
                html += '<div class="summary-row"><span class="lbl">Animales seleccionados</span><span class="val">' + d.total + '</span></div>';
                html += '<div class="summary-row"><span class="lbl">Motivo</span><span class="val">' + $('#motivo').val() + '</span></div>';
                $('#resumen_final').html(html);

                var adv = '';
                if (d.advertencias && d.advertencias.length > 0) {
                    adv += '<div class="alert alert-warn"><strong>⚠️ Advertencias:</strong><ul style="margin:8px 0 0 16px;">';
                    d.advertencias.forEach(function(a) { adv += '<li>' + a + '</li>'; });
                    adv += '</ul></div>';
                }
                $('#advertencias_box').html(adv);

                irPaso(3);
            },
            'json'
        );
    });

    $('#btn_atras3').on('click', function() { irPaso(2); });

    // ─────────────────────────────────────────────────────
    // EJECUTAR
    // ─────────────────────────────────────────────────────
    $('#btn_ejecutar').on('click', function() {
        if (S.EJECUTANDO) {
            alert('La migración ya está en proceso.');
            return;
        }

        var ids = Object.keys(S.SELECCIONADOS);
        if (ids.length === 0) { alert('Seleccione al menos un animal'); return; }

        if (!confirm('¿Confirma la migración de ' + ids.length + ' animal(es)?')) return;

        S.EJECUTANDO = true;
        var $btn = $('#btn_ejecutar');
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Procesando...');

        $.post('../../controllers/migracion_controller.php',
            {
                action: 'ejecutar',
                fundo_origen_id: $('#fundo_origen').val(),
                fundo_destino_id: $('#fundo_destino').val(),
                animales: ids.join(','),
                motivo: $('#motivo').val(),
                observaciones: $('#observaciones').val()
            },
            function(d) {
                S.EJECUTANDO = false;
                console.log('[MIGRACION] Ejecutar response:', d);

                if (typeof d === 'string') {
                    try { d = JSON.parse(d); } catch (e) {
                        alert('Error parseando ejecución: ' + d);
                        $btn.prop('disabled', false).html('<i class="fa fa-check"></i> Reintentar');
                        return;
                    }
                }

                $('#resultado_box').show();

                if (d.ok) {
                    var html = '<div class="alert alert-ok"><strong>✓ ' + d.msg + '</strong></div>';
                    html += '<h4 style="margin-top:20px; color:#233e2d;">Animales migrados:</h4>';
                    d.exitosos.forEach(function(e) {
                        html += '<div class="resultado-item">';
                        html +=   '<strong>' + e.nombre + '</strong> · ';
                        html +=   '<span class="cod">' + e.codigo_orig + '</span>';
                        html +=   '<span class="arrow">→</span>';
                        html +=   '<span class="cod">' + e.codigo_nuevo + '</span>';
                        if (e.auto_creados && e.auto_creados.length > 0) {
                            html += '<span class="autocreados">📦 Catálogos creados: ' + e.auto_creados.join(' · ') + '</span>';
                        }
                        html += '</div>';
                    });

                    if (d.fallidos && d.fallidos.length > 0) {
                        html += '<div class="alert alert-err" style="margin-top:20px;"><strong>Fallidos:</strong><ul style="margin:8px 0 0 16px;">';
                        d.fallidos.forEach(function(f) { html += '<li>' + f + '</li>'; });
                        html += '</ul></div>';
                    }

                    html += '<div style="margin-top:24px; text-align:right;">';
                    html += '<a href="../inicio/" class="mig-btn mig-btn-primary">Ir al inicio</a> ';
                    html += '<a href="./" class="mig-btn mig-btn-secondary">Nueva migración</a>';
                    html += '</div>';

                    $('#resultado_contenido').html(html);
                    $btn.prop('disabled', true).html('<i class="fa fa-check"></i> Completado');
                } else {
                    var html = '<div class="alert alert-err"><strong>✗ ' + (d.msg || 'Error') + '</strong></div>';
                    $('#resultado_contenido').html(html);
                    $btn.prop('disabled', false).html('<i class="fa fa-check"></i> Reintentar');
                }
            },
            'json'
        ).fail(function(xhr) {
            S.EJECUTANDO = false;
            console.error('[MIGRACION] Fetch error:', xhr.responseText);
            $('#resultado_box').show();
            $('#resultado_contenido').html('<div class="alert alert-err">Error de conexión al ejecutar migración</div>');
            $btn.prop('disabled', false).html('<i class="fa fa-check"></i> Reintentar');
        });
    });

    console.log('=== [MIGRACION] Eventos registrados ===');
})();
</script>

<?php include_once("../layouts/pie.php") ?>