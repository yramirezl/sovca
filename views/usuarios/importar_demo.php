<?php
/**
 * =====================================================================
 * views/usuarios/importar_demo.php — IMPORTAR CUENTA DEMO
 * ---------------------------------------------------------------------
 * El administrador convierte una cuenta de demostración en cuenta
 * oficial:
 *
 *   PASO 1 · elige la cuenta demo (ve qué procesos cargó),
 *   PASO 2 · crea la cuenta oficial (plan, suscripción, fundo…),
 *   PASO 3 · elige qué procesos importar y los copia,
 *   y la cuenta demo queda cerrada (su correo no sirve para otra demo).
 *
 * Toda la lógica está en lib/demo_import_helper.php y
 * controllers/demo_import_controller.php.
 * =====================================================================
 */

include("../../lib/validar_session.php");
ValidaSession("../login");
VerificarAdmin($_SESSION['rolx']);
?>
<?php include("../layouts/constantes.php") ?>
<?php include_once("../layouts/cabecera.php") ?>

<style>
.imp-page { padding: 4px 2px 24px; }
.imp-head { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 14px; }
.imp-head h2 { font-size: 18px; font-weight: 800; color: #1b3a24; margin: 0; }
.imp-head p { margin: 2px 0 0; font-size: 12px; color: #64748b; }
.imp-head .imp-back { margin-left: auto; }

.imp-card { background: #fff; border: 1px solid #e3e9e5; border-radius: 12px; box-shadow: 0 2px 10px rgba(20, 50, 32, .06); margin-bottom: 16px; overflow: hidden; }
.imp-card__head { padding: 10px 14px; border-bottom: 1px solid #eef2ee; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.imp-card__head h3 { margin: 0; font-size: 14px; font-weight: 800; color: #1b3a24; }
.imp-card__head .imp-count { margin-left: auto; font-size: 11px; font-weight: 700; color: #64748b; }
.imp-card__body { padding: 12px 14px; }

.imp-table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
.imp-table th { text-align: left; font-size: 10.5px; letter-spacing: .6px; text-transform: uppercase; color: #7b8794; font-weight: 800; padding: 6px 8px; border-bottom: 1px solid #eef2ee; white-space: nowrap; }
.imp-table td { padding: 8px; border-bottom: 1px solid #f3f6f4; vertical-align: middle; }
.imp-table tr:hover td { background: #f8fbf9; }
.imp-user { font-weight: 800; color: #1f2d26; }
.imp-sub { display: block; font-size: 11px; color: #8492a0; font-weight: 600; }
.imp-proc { font-size: 11.5px; color: #4b5b52; }

.imp-pill { display: inline-flex; align-items: center; gap: 5px; padding: 2px 8px; border-radius: 999px; font-size: 10.5px; font-weight: 800; text-transform: uppercase; letter-spacing: .4px; }
.imp-pill--activa { background: #e8f5e9; color: #1b5e20; }
.imp-pill--vencida { background: #fdecea; color: #b3261e; }
.imp-pill--migrada { background: #e7f0fd; color: #1e40af; }
.imp-pill--cerrada, .imp-pill--inactiva { background: #f1f3f5; color: #5f6b76; }

.imp-btn { display: inline-flex; align-items: center; gap: 6px; border: none; border-radius: 8px; padding: 6px 12px; font-size: 12px; font-weight: 700; cursor: pointer; transition: filter .2s, transform .15s; }
.imp-btn:hover:not(:disabled) { filter: brightness(1.06); transform: translateY(-1px); }
.imp-btn:disabled { opacity: .55; cursor: not-allowed; }
.imp-btn--primary { background: linear-gradient(135deg, #2c8a56, #1b6e43); color: #fff; }
.imp-btn--ghost { background: #f1f5f2; color: #33413a; }
.imp-btn--danger { background: #fdecea; color: #b3261e; }
.imp-btn--sm { padding: 4px 9px; font-size: 11px; }

.imp-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 10px; }
.imp-field label { display: block; font-size: 10.5px; font-weight: 800; letter-spacing: .6px; text-transform: uppercase; color: #234e35; margin-bottom: 4px; }
.imp-field input, .imp-field select { width: 100%; height: 36px; border: 1px solid #d8e3db; border-radius: 8px; padding: 0 10px; font-size: 12.5px; font-weight: 600; color: #1f2d26; background: #f8fbf9; }
.imp-field input:focus, .imp-field select:focus { outline: none; border-color: #234e35; background: #fff; box-shadow: 0 0 0 3px rgba(35, 78, 53, .12); }
.imp-field--wide { grid-column: 1 / -1; }
.imp-help { font-size: 11px; color: #8492a0; margin-top: 4px; }

.imp-grupos { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 8px; }
.imp-grupo { display: flex; align-items: flex-start; gap: 9px; padding: 9px 11px; border: 1px solid #e3e9e5; border-radius: 9px; background: #fbfdfb; cursor: pointer; }
.imp-grupo:hover { border-color: #b8d8c4; }
.imp-grupo input { margin-top: 2px; }
.imp-grupo__txt { min-width: 0; }
.imp-grupo__txt strong { display: block; font-size: 12.5px; color: #1f2d26; }
.imp-grupo__txt small { display: block; font-size: 11px; color: #8492a0; }
.imp-grupo__n { margin-left: auto; font-size: 11.5px; font-weight: 800; color: #2c6e46; white-space: nowrap; }
.imp-grupo--vacio { opacity: .55; }
.imp-grupo--vacio .imp-grupo__n { color: #a0adb8; }

.imp-msg { display: none; border-radius: 9px; padding: 10px 12px; font-size: 12.5px; font-weight: 600; margin-bottom: 12px; }
.imp-msg.is-visible { display: block; }
.imp-msg--error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
.imp-msg--ok { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
.imp-msg--info { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }

.imp-steps { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 14px; }
.imp-step { display: inline-flex; align-items: center; gap: 6px; padding: 5px 11px; border-radius: 999px; background: #f1f5f2; color: #7b8794; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .4px; }
.imp-step.is-active { background: #234e35; color: #fff; }
.imp-step.is-done { background: #e8f5e9; color: #1b5e20; }
.imp-sep { color: #c3cec7; }

.imp-resumen-linea { font-size: 12px; color: #4b5b52; margin: 0 0 4px; }
.imp-resumen-linea strong { color: #1b3a24; }
.imp-omitidos { margin: 10px 0 0; padding: 10px 12px; background: #fffbeb; border: 1px solid #fde68a; border-radius: 9px; font-size: 11.5px; color: #92400e; max-height: 160px; overflow: auto; }
.imp-vacio { text-align: center; padding: 26px 10px; color: #8492a0; font-size: 12.5px; }
.imp-vacio i { display: block; font-size: 26px; margin-bottom: 8px; color: #b8c7bd; }

@media (max-width: 700px) {
  .imp-table thead { display: none; }
  .imp-table tr { display: block; border-bottom: 1px solid #eef2ee; padding: 6px 0; }
  .imp-table td { display: flex; justify-content: space-between; gap: 10px; border: none; padding: 4px 6px; }
  .imp-table td::before { content: attr(data-label); font-size: 10px; text-transform: uppercase; letter-spacing: .5px; color: #8492a0; font-weight: 800; }
}
</style>

<div class="content-wrapper imp-page">

    <div class="imp-head">
        <div>
            <h2><i class="fa fa-file-import" style="color:#2c6e46;"></i> Importar cuenta demo</h2>
            <p>Convierte una cuenta de demostración en cuenta oficial conservando los datos que cargó el usuario.</p>
        </div>
        <a href="./" class="btn btn-default btn-sm imp-back"><i class="fa fa-arrow-left"></i> Volver al listado</a>
    </div>

    <div class="imp-steps">
        <span class="imp-step is-active" id="step-1"><i class="fa fa-list"></i> 1 · Elegir demo</span>
        <span class="imp-sep">›</span>
        <span class="imp-step" id="step-2"><i class="fa fa-user-plus"></i> 2 · Cuenta oficial</span>
        <span class="imp-sep">›</span>
        <span class="imp-step" id="step-3"><i class="fa fa-database"></i> 3 · Importar datos</span>
    </div>

    <div class="imp-msg" id="imp-msg"></div>

    <!-- ============================================================
         PASO 1 · LISTA DE CUENTAS DEMO
         ============================================================ -->
    <div class="imp-card" id="panel-lista">
        <div class="imp-card__head">
            <h3><i class="fa fa-flask"></i> Cuentas de demostración</h3>
            <span class="imp-count" id="imp-count">Cargando…</span>
        </div>
        <div class="imp-card__body">
            <div id="imp-lista"><div class="imp-vacio"><i class="fa fa-circle-notch fa-spin"></i>Cargando cuentas demo…</div></div>
        </div>
    </div>

    <!-- ============================================================
         PASO 2 · CUENTA OFICIAL
         ============================================================ -->
    <div class="imp-card" id="panel-cuenta" style="display:none;">
        <div class="imp-card__head">
            <h3><i class="fa fa-user-plus"></i> Crear la cuenta oficial</h3>
            <span class="imp-count" id="cuenta-demo-etiqueta"></span>
        </div>
        <div class="imp-card__body">
            <p class="imp-help" style="margin-bottom:12px;">
                Se crea un <strong>usuario productor</strong> con su fundo y la suscripción comprada.
                Después podrás importar los procesos de la cuenta demo.
            </p>

            <div class="imp-grid">
                <div class="imp-field">
                    <label for="cu_usuario">Usuario</label>
                    <input id="cu_usuario" type="text" maxlength="30" autocorrect="off" spellcheck="false">
                </div>
                <div class="imp-field">
                    <label for="cu_nombre">Nombre y apellido</label>
                    <input id="cu_nombre" type="text" maxlength="120">
                </div>
                <div class="imp-field">
                    <label for="cu_email">Correo electrónico</label>
                    <input id="cu_email" type="email" maxlength="150">
                </div>
                <div class="imp-field">
                    <label for="cu_clave">Contraseña</label>
                    <input id="cu_clave" type="password" maxlength="60" autocomplete="new-password">
                </div>
                <div class="imp-field">
                    <label for="cu_clave2">Repetir contraseña</label>
                    <input id="cu_clave2" type="password" maxlength="60" autocomplete="new-password">
                </div>
                <div class="imp-field">
                    <label for="cu_plan">Plan</label>
                    <select id="cu_plan"></select>
                </div>
                <div class="imp-field">
                    <label for="cu_suscripcion">Suscripción</label>
                    <select id="cu_suscripcion"></select>
                </div>
                <div class="imp-field">
                    <label for="cu_fecha">Fecha de activación</label>
                    <input id="cu_fecha" type="date">
                </div>
                <div class="imp-field">
                    <label for="cu_ganaderia">Tipo de ganadería</label>
                    <select id="cu_ganaderia">
                        <option value="1">Ovina</option>
                        <option value="2">Caprina</option>
                    </select>
                </div>
                <div class="imp-field imp-field--wide">
                    <label for="cu_fundo">Nombre del fundo</label>
                    <input id="cu_fundo" type="text" maxlength="120">
                </div>
                <div class="imp-field imp-field--wide">
                    <label for="cu_rif">CID / RIF del fundo</label>
                    <input id="cu_rif" type="text" maxlength="180">
                </div>
            </div>

            <div class="imp-help" style="margin-top:8px;">
                La contraseña debe tener 8+ caracteres, una mayúscula, una minúscula, un número y uno de estos signos: @ # $ %
            </div>

            <div style="display:flex; gap:8px; margin-top:14px; flex-wrap:wrap;">
                <button type="button" class="imp-btn imp-btn--primary" id="btn-crear-cuenta">
                    <i class="fa fa-check"></i> Crear cuenta y continuar
                </button>
                <button type="button" class="imp-btn imp-btn--ghost" id="btn-cancelar-cuenta">
                    <i class="fa fa-times"></i> Cancelar
                </button>
            </div>
        </div>
    </div>

    <!-- ============================================================
         PASO 3 · IMPORTAR PROCESOS
         ============================================================ -->
    <div class="imp-card" id="panel-procesos" style="display:none;">
        <div class="imp-card__head">
            <h3><i class="fa fa-database"></i> Procesos a importar</h3>
            <span class="imp-count" id="procesos-cuenta"></span>
        </div>
        <div class="imp-card__body">
            <p class="imp-help" style="margin-bottom:10px;">
                Marca lo que quieres conservar. El sistema agrega automáticamente los procesos de los que depende cada uno
                (por ejemplo, para los servicios se importan los animales y los catálogos que usan).
            </p>

            <div class="imp-grupos" id="imp-grupos"></div>

            <div style="display:flex; gap:8px; margin-top:14px; flex-wrap:wrap;">
                <button type="button" class="imp-btn imp-btn--primary" id="btn-importar">
                    <i class="fa fa-file-import"></i> Importar seleccionados
                </button>
                <button type="button" class="imp-btn imp-btn--ghost" id="btn-terminar">
                    <i class="fa fa-flag-checkered"></i> Terminar sin importar
                </button>
            </div>

            <div id="imp-resultado" style="margin-top:14px;"></div>
        </div>
    </div>
</div>

<script>
(function ($) {
    'use strict';

    var URL = '../../controllers/demo_import_controller';

    var DEMOS   = [];
    var GRUPOS  = {};
    var PLANES  = [];
    var SUSCRIP = [];
    var ACTUAL  = null;   // demo seleccionada
    var CUENTA  = null;   // cuenta oficial creada

    function escapeHtml(t) {
        return String(t == null ? '' : t)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function mensaje(tipo, html) {
        var $m = $('#imp-msg');
        $m.removeClass('imp-msg--error imp-msg--ok imp-msg--info').addClass('imp-msg--' + tipo + ' is-visible').html(html);
        if (tipo === 'ok' || tipo === 'info') {
            setTimeout(function () { $m.removeClass('is-visible'); }, 6000);
        }
    }

    function limpiarMensaje() { $('#imp-msg').removeClass('is-visible').html(''); }

    function pasos(n) {
        $('#step-1').removeClass('is-active is-done').addClass(n > 1 ? 'is-done' : 'is-active');
        $('#step-2').removeClass('is-active is-done').addClass(n === 2 ? 'is-active' : (n > 2 ? 'is-done' : ''));
        $('#step-3').removeClass('is-active is-done').addClass(n === 3 ? 'is-active' : '');
    }

    function etiquetaEstado(e) {
        var mapa = {
            activa:   'Activa',
            vencida:  'Vencida',
            migrada:  'Migrada',
            cerrada:  'Cerrada',
            inactiva: 'Inactiva'
        };
        return '<span class="imp-pill imp-pill--' + e + '">' + (mapa[e] || e) + '</span>';
    }

    function fecha(t) {
        if (!t) { return '—'; }
        var s = String(t).substr(0, 10).split('-');
        return s.length === 3 ? (s[2] + '-' + s[1] + '-' + s[0]) : t;
    }

    function procesosTexto(resumen) {
        var partes = [];
        $.each(resumen || {}, function (clave, r) {
            if (r.total > 0) {
                var g = GRUPOS[clave] || {};
                partes.push((g.etiqueta || clave) + ' (' + r.total + ')');
            }
        });
        return partes.length ? partes.join(' · ') : 'Sin datos cargados';
    }

    function cargar() {
        $.ajax({ url: URL, type: 'GET', data: { action: 'listar' }, dataType: 'json', cache: false })
            .done(function (d) {
                if (!d || !d.ok) {
                    $('#imp-lista').html('<div class="imp-vacio"><i class="fa fa-triangle-exclamation"></i>' + escapeHtml((d && d.msg) || 'No se pudo cargar la información.') + '</div>');
                    $('#imp-count').text('');
                    return;
                }

                DEMOS   = d.demos || [];
                GRUPOS  = d.grupos || {};
                PLANES  = d.planes || [];
                SUSCRIP = d.suscripciones || [];

                pintarLista(d.hoy);
                pintarSelectores(d.hoy);
            })
            .fail(function () {
                $('#imp-lista').html('<div class="imp-vacio"><i class="fa fa-plug-circle-xmark"></i>No hubo respuesta del servidor.</div>');
            });
    }

    function pintarLista(hoy) {
        if (!DEMOS.length) {
            $('#imp-lista').html('<div class="imp-vacio"><i class="fa fa-inbox"></i>No hay cuentas de demostración registradas.</div>');
            $('#imp-count').text('0 cuentas');
            return;
        }

        var html = '<table class="imp-table"><thead><tr>'
            + '<th>Usuario</th><th>Fundo</th><th>Vence</th><th>Estado</th><th>Procesos cargados</th><th></th>'
            + '</tr></thead><tbody>';

        $.each(DEMOS, function (i, d) {
            var puede = (d.estado === 'activa' || d.estado === 'vencida');

            html += '<tr>'
                + '<td data-label="Usuario"><span class="imp-user">' + escapeHtml(d.username) + '</span>'
                + '<span class="imp-sub">' + escapeHtml(d.name) + ' · ' + escapeHtml(d.email) + '</span></td>'
                + '<td data-label="Fundo">' + escapeHtml(d.fundo_nombre) + '</td>'
                + '<td data-label="Vence">' + fecha(d.caduca) + '</td>'
                + '<td data-label="Estado">' + etiquetaEstado(d.estado) + '</td>'
                + '<td data-label="Procesos" class="imp-proc">' + escapeHtml(procesosTexto(d.resumen)) + '</td>'
                + '<td data-label="Acciones" style="text-align:right; white-space:nowrap;">'
                + (puede
                    ? '<button type="button" class="imp-btn imp-btn--primary imp-btn--sm" data-importar="' + i + '"><i class="fa fa-file-import"></i> Importar</button> '
                    + '<button type="button" class="imp-btn imp-btn--danger imp-btn--sm" data-cerrar="' + i + '" title="Cerrar sin importar datos"><i class="fa fa-ban"></i></button>'
                    : '<span class="imp-sub">' + (d.estado === 'migrada' ? 'Ya importada' : 'Sin acciones') + '</span>')
                + '</td></tr>';
        });

        html += '</tbody></table>';
        $('#imp-lista').html(html);
        $('#imp-count').text(DEMOS.length + ' cuenta(s)');
    }

    function pintarSelectores(hoy) {
        var $p = $('#cu_plan').empty();
        $.each(PLANES, function (i, p) {
            $p.append('<option value="' + p.id + '">' + escapeHtml(p.nombre) + ' (máx. ' + p.max_reg + ')</option>');
        });

        var $s = $('#cu_suscripcion').empty();
        $.each(SUSCRIP, function (i, s) {
            $s.append('<option value="' + s.id + '">' + escapeHtml(s.descripcion) + ' · ' + s.cantidad_dias + ' días</option>');
        });

        $('#cu_fecha').val(hoy || '');
    }

    /* ---------------- Paso 2 ---------------- */
    $(document).on('click', '[data-importar]', function () {
        var i = Number($(this).data('importar'));
        ACTUAL = DEMOS[i];
        CUENTA = null;

        if (!ACTUAL) { return; }

        $('#cu_usuario').val(ACTUAL.username || '');
        $('#cu_nombre').val(ACTUAL.name || '');
        $('#cu_email').val(ACTUAL.email || '');
        $('#cu_clave').val('');
        $('#cu_clave2').val('');
        $('#cu_fundo').val(ACTUAL.fundo_nombre || '');
        $('#cu_rif').val('V-' + String(ACTUAL.username || '').replace(/[^A-Z0-9]/gi, '').substr(0, 12));
        $('#cuenta-demo-etiqueta').text('Cuenta demo: ' + ACTUAL.username + ' · vence ' + fecha(ACTUAL.caduca));

        $('#panel-cuenta').show();
        $('#panel-procesos').hide();
        pasos(2);
        limpiarMensaje();
        $('html, body').animate({ scrollTop: $('#panel-cuenta').offset().top - 90 }, 250);
    });

    $('#btn-cancelar-cuenta').on('click', function () {
        $('#panel-cuenta').hide();
        $('#panel-procesos').hide();
        ACTUAL = null;
        pasos(1);
    });

    $('#btn-crear-cuenta').on('click', function () {
        if (!ACTUAL) { return; }
        limpiarMensaje();

        var $b = $(this).prop('disabled', true);
        $.ajax({
            url: URL,
            type: 'POST',
            dataType: 'json',
            cache: false,
            data: {
                action: 'crear_cuenta',
                nombre: $('#cu_nombre').val(),
                email: $('#cu_email').val(),
                usuario: $('#cu_usuario').val(),
                clave: $('#cu_clave').val(),
                clave2: $('#cu_clave2').val(),
                plan_id: $('#cu_plan').val(),
                suscripcion_id: $('#cu_suscripcion').val(),
                fecha_activa: $('#cu_fecha').val(),
                fundo_nombre: $('#cu_fundo').val(),
                cid_rif: $('#cu_rif').val(),
                tipo_ganaderia: $('#cu_ganaderia').val()
            }
        }).done(function (d) {
            $b.prop('disabled', false);
            if (!d || !d.ok) {
                mensaje('error', escapeHtml((d && d.msg) || 'No se pudo crear la cuenta.'));
                return;
            }

            CUENTA = d;
            mensaje('ok', 'Cuenta oficial <strong>' + escapeHtml($('#cu_usuario').val()) + '</strong> creada. Vigencia hasta ' + fecha(d.caduca) + '.');
            abrirProcesos();
        }).fail(function () {
            $b.prop('disabled', false);
            mensaje('error', 'No hubo respuesta del servidor.');
        });
    });

    /* ---------------- Paso 3 ---------------- */
    function abrirProcesos() {
        var resumen = ACTUAL.resumen || {};
        var html = '';

        $.each(GRUPOS, function (clave, g) {
            var total = (resumen[clave] && resumen[clave].total) ? resumen[clave].total : 0;
            html += '<label class="imp-grupo' + (total ? '' : ' imp-grupo--vacio') + '">'
                + '<input type="checkbox" value="' + clave + '"' + (total ? ' checked' : ' disabled') + '>'
                + '<span class="imp-grupo__txt"><strong><i class="fa ' + (g.icono || 'fa-circle') + '"></i> ' + escapeHtml(g.etiqueta) + '</strong>'
                + '<small>' + escapeHtml(g.descripcion || '') + '</small></span>'
                + '<span class="imp-grupo__n">' + total + '</span>'
                + '</label>';
        });

        $('#imp-grupos').html(html);
        $('#procesos-cuenta').text('Demo: ' + ACTUAL.username + ' → Oficial: ' + $('#cu_usuario').val());
        $('#imp-resultado').empty();
        $('#panel-procesos').show();
        pasos(3);
        $('html, body').animate({ scrollTop: $('#panel-procesos').offset().top - 90 }, 250);
    }

    $('#btn-importar').on('click', function () {
        if (!ACTUAL || !CUENTA) { return; }

        var grupos = [];
        $('#imp-grupos input:checked').each(function () { grupos.push($(this).val()); });

        if (!grupos.length) {
            mensaje('error', 'Marca al menos un proceso para importar.');
            return;
        }

        var $b = $(this).prop('disabled', true).html('<i class="fa fa-circle-notch fa-spin"></i> Importando…');

        $.ajax({
            url: URL,
            type: 'POST',
            dataType: 'json',
            cache: false,
            data: {
                action: 'importar',
                demo_usuario_id: ACTUAL.usuario_id,
                demo_usuario: ACTUAL.username,
                fundo_demo: ACTUAL.fundo_id,
                fundo_destino: CUENTA.fundo_id,
                usuario_destino: CUENTA.usuario_id,
                grupos: grupos
            }
        }).done(function (d) {
            $b.prop('disabled', false).html('<i class="fa fa-file-import"></i> Importar seleccionados');

            if (!d || !d.ok) {
                mensaje('error', escapeHtml((d && d.msg) || 'No se pudo importar.'));
                return;
            }

            var html = '<div class="imp-msg imp-msg--ok is-visible">' + d.msg + '</div>';

            if (d.detalle) {
                html += '<p class="imp-resumen-linea"><strong>Detalle por tabla</strong></p><ul style="font-size:12px; margin:0 0 8px 18px; color:#4b5b52;">';
                $.each(d.detalle, function (tabla, n) {
                    if (n > 0) { html += '<li>' + escapeHtml(tabla) + ': ' + n + '</li>'; }
                });
                html += '</ul>';
            }

            if (d.omitidos && d.omitidos.length) {
                html += '<div class="imp-omitidos"><strong>Se omitieron ' + d.omitidos.length + ' registro(s):</strong><br>'
                    + d.omitidos.slice(0, 20).map(escapeHtml).join('<br>')
                    + (d.omitidos.length > 20 ? '<br>…' : '') + '</div>';
            }

            $('#imp-resultado').html(html);
            $('#imp-grupos').find('input').prop('disabled', true);
            $('#btn-importar').hide();
            $('#btn-terminar').hide();

            /* La cuenta demo quedó cerrada: se recarga la lista. */
            setTimeout(cargar, 1200);
        }).fail(function () {
            $b.prop('disabled', false).html('<i class="fa fa-file-import"></i> Importar seleccionados');
            mensaje('error', 'No hubo respuesta del servidor.');
        });
    });

    $('#btn-terminar').on('click', function () {
        if (!ACTUAL) { return; }

        if (!window.confirm('La cuenta demo ' + ACTUAL.username + ' se cerrará sin importar datos. ¿Continuar?')) {
            return;
        }

        $.ajax({
            url: URL,
            type: 'POST',
            dataType: 'json',
            cache: false,
            data: { action: 'cerrar', demo_usuario_id: ACTUAL.usuario_id }
        }).done(function (d) {
            if (d && d.ok) {
                mensaje('ok', escapeHtml(d.msg));
                cargar();
                $('#panel-procesos').hide();
                $('#panel-cuenta').hide();
                pasos(1);
            } else {
                mensaje('error', escapeHtml((d && d.msg) || 'No se pudo cerrar la cuenta demo.'));
            }
        });
    });

    /* ---------------- Cerrar desde la lista ---------------- */
    $(document).on('click', '[data-cerrar]', function () {
        var i = Number($(this).data('cerrar'));
        var d = DEMOS[i];
        if (!d) { return; }

        if (!window.confirm('¿Cerrar la cuenta demo ' + d.username + ' sin importar datos?\n\nSu correo ya no podrá usarse para otra demostración.')) {
            return;
        }

        $.ajax({
            url: URL,
            type: 'POST',
            dataType: 'json',
            cache: false,
            data: { action: 'cerrar', demo_usuario_id: d.usuario_id }
        }).done(function (r) {
            if (r && r.ok) {
                mensaje('ok', escapeHtml(r.msg));
                cargar();
            } else {
                mensaje('error', escapeHtml((r && r.msg) || 'No se pudo cerrar.'));
            }
        });
    });

    $(function () { cargar(); });

})(jQuery);
</script>

<?php include_once("../layouts/pie.php") ?>
