<?php
/**
 * =====================================================================
 * views/mensajesdemo/index.php — MENSAJES PARA USUARIOS DEMO
 * ---------------------------------------------------------------------
 * Lista los correos de quienes crearon una cuenta de demostración
 * (vigentes y caducadas) y permite enviarles un mensaje informativo que
 * los invita a comprar la cuenta oficial.
 *
 *   · los correos migrados al sistema oficial no aparecen,
 *   · cada correo indica si su cuenta demo está VIGENTE o CADUCADA,
 *   · el envío se hace por lotes para no agotar el tiempo del servidor.
 *
 * La lógica está en lib/mensajes_demo_helper.php y
 * controllers/mensajes_demo_controller.php.
 * =====================================================================
 */

include("../../lib/validar_session.php");
ValidaSession("../login");
VerificarAdmin($_SESSION['rolx']);
?>
<?php include("../layouts/constantes.php") ?>
<?php include_once("../layouts/cabecera.php") ?>

<link rel="stylesheet" href="../../plugins/confirma/jquery-confirm.min.css" type="text/css" />

<style>
.msjd-page { padding: 4px 2px 26px; }

.msjd-head { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 14px; }
.msjd-head h2 { font-size: 18px; font-weight: 800; color: #1b3a24; margin: 0; }
.msjd-head p { margin: 2px 0 0; font-size: 12px; color: #64748b; max-width: 720px; }
.msjd-head .msjd-back { margin-left: auto; }

.msjd-stats { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 14px; }
.msjd-stat { background: #fff; border: 1px solid #e3e9e5; border-radius: 12px; padding: 10px 16px; min-width: 150px; box-shadow: 0 2px 10px rgba(20,50,32,.05); }
.msjd-stat span { display: block; font-size: 10.5px; letter-spacing: .6px; text-transform: uppercase; color: #7b8794; font-weight: 800; }
.msjd-stat strong { display: block; font-size: 22px; font-weight: 800; color: #1b3a24; line-height: 1.2; }
.msjd-stat--ok strong { color: #1b6e43; }
.msjd-stat--no strong { color: #b3261e; }

.msjd-card { background: #fff; border: 1px solid #e3e9e5; border-radius: 12px; box-shadow: 0 2px 10px rgba(20,50,32,.06); margin-bottom: 16px; overflow: hidden; }
.msjd-card__head { padding: 11px 14px; border-bottom: 1px solid #eef2ee; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.msjd-card__head h3 { margin: 0; font-size: 14px; font-weight: 800; color: #1b3a24; }
.msjd-card__body { padding: 12px 14px; }

.msjd-filtro { margin-left: auto; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.msjd-chip { display: inline-flex; align-items: center; gap: 5px; border: 1px solid #dbe6df; background: #f6faf7;
  color: #46604f; border-radius: 999px; padding: 5px 11px; font-size: 10.5px; font-weight: 800;
  letter-spacing: .4px; text-transform: uppercase; cursor: pointer; transition: background .18s, color .18s, border-color .18s; }
.msjd-chip:hover { border-color: #bcd8c6; color: #234e35; }
.msjd-chip.is-active { background: #e6f4ea; border-color: #96c9a8; color: #1b5e20; }
.msjd-chip i { font-size: 10px; }

.msjd-buscar { display: flex; gap: 8px; margin-bottom: 10px; flex-wrap: wrap; }
.msjd-buscar input { flex: 1; min-width: 200px; height: 36px; border: 1px solid #d8e3db; border-radius: 8px; padding: 0 12px; font-size: 13px; color: #1f2d26; background: #f8fbf9; }
.msjd-buscar input:focus { outline: none; border-color: #234e35; background: #fff; box-shadow: 0 0 0 3px rgba(35,78,53,.12); }

.msjd-tabla-wrap { overflow-x: auto; border: 1px solid #eef2ee; border-radius: 10px; }
.msjd-tabla { width: 100%; border-collapse: collapse; font-size: 12.5px; min-width: 760px; }
.msjd-tabla th { text-align: left; font-size: 10.5px; letter-spacing: .6px; text-transform: uppercase; color: #7b8794; font-weight: 800; padding: 8px; border-bottom: 1px solid #eef2ee; background: #fbfdfb; white-space: nowrap; }
.msjd-tabla td { padding: 9px 8px; border-bottom: 1px solid #f3f6f4; vertical-align: middle; }
.msjd-tabla tr:hover td { background: #f8fbf9; }
.msjd-tabla input[type=checkbox] { width: 16px; height: 16px; cursor: pointer; }
.msjd-mail { font-weight: 700; color: #1f2d26; white-space: nowrap; }
.msjd-sub { display: block; font-size: 11px; color: #8492a0; font-weight: 600; }
.msjd-vacio { text-align: center; padding: 28px 12px; color: #8492a0; font-size: 12.5px; }
.msjd-vacio i { display: block; font-size: 26px; margin-bottom: 8px; color: #b8c7bd; }

.msjd-pill { display: inline-flex; align-items: center; gap: 5px; padding: 2px 9px; border-radius: 999px; font-size: 10.5px; font-weight: 800; text-transform: uppercase; letter-spacing: .4px; white-space: nowrap; }
.msjd-pill--vigente { background: #e8f5e9; color: #1b5e20; }
.msjd-pill--caducada { background: #fdecea; color: #b3261e; }

.msjd-barra { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; padding: 11px 14px; border-top: 1px solid #eef2ee; background: #fbfdfb; }
.msjd-barra .msjd-sel { font-weight: 800; color: #2c6e46; font-size: 12.5px; }

.msjd-btn { display: inline-flex; align-items: center; gap: 6px; border: none; border-radius: 9px; padding: 9px 16px; font-size: 12.5px; font-weight: 800; cursor: pointer; transition: filter .18s, transform .15s; text-decoration: none !important; }
.msjd-btn:hover:not(:disabled) { filter: brightness(1.06); transform: translateY(-1px); }
.msjd-btn:disabled { opacity: .55; cursor: not-allowed; }
.msjd-btn--primary { background: linear-gradient(135deg, #2c8a56, #1b6e43); color: #fff !important; }
.msjd-btn--ghost { background: #f1f5f2; color: #33413a !important; }
.msjd-btn--soft { background: #e6f4ea; color: #1b5e20 !important; }
.msjd-btn--sm { padding: 6px 11px; font-size: 11.5px; }
.msjd-btn--bar { margin-left: auto; }

.msjd-field { margin-bottom: 12px; }
.msjd-field label { display: block; font-size: 10.5px; font-weight: 800; letter-spacing: .6px; text-transform: uppercase; color: #234e35; margin-bottom: 4px; }
.msjd-field input, .msjd-field textarea { width: 100%; border: 1px solid #d8e3db; border-radius: 8px; padding: 9px 11px; font-size: 13px; font-weight: 600; color: #1f2d26; background: #f8fbf9; }
.msjd-field textarea { min-height: 210px; resize: vertical; font-weight: 500; line-height: 1.55; }
.msjd-field input:focus, .msjd-field textarea:focus { outline: none; border-color: #234e35; background: #fff; box-shadow: 0 0 0 3px rgba(35,78,53,.12); }
.msjd-help { font-size: 11.5px; color: #8492a0; margin-top: 4px; }
.msjd-vars { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 6px; }
.msjd-var { border: 1px dashed #bcd8c6; background: #f4faf6; color: #24603c; border-radius: 6px; padding: 3px 8px; font-size: 11px; font-weight: 700; cursor: pointer; }
.msjd-var:hover { background: #e6f4ea; }

.msjd-destinos { background: #f4faf6; border: 1px solid #d8ecdf; border-radius: 10px; padding: 10px 12px; font-size: 12.5px; color: #2c4a37; margin-bottom: 14px; }
.msjd-destinos b { color: #1b5e20; }
.msjd-destinos .msjd-lista-mails { display: block; font-size: 11.5px; color: #64748b; margin-top: 4px; word-break: break-all; }

.msjd-progreso { display: none; margin-top: 12px; }
.msjd-progreso.is-visible { display: block; }
.msjd-barra-fondo { height: 8px; background: #eef2ee; border-radius: 999px; overflow: hidden; }
.msjd-barra-fondo span { display: block; height: 100%; width: 0; background: linear-gradient(90deg, #2c8a56, #1b6e43); transition: width .25s; }
.msjd-progreso small { display: block; font-size: 11.5px; color: #64748b; margin-top: 5px; font-weight: 700; }

.msjd-msg { display: none; border-radius: 9px; padding: 10px 12px; font-size: 12.5px; font-weight: 600; margin-bottom: 12px; }
.msjd-msg.is-visible { display: block; }
.msjd-msg--error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
.msjd-msg--ok { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
.msjd-msg--info { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }

.msjd-secuencia { display: block; font-size: 11px; color: #1b5e20; font-weight: 700; line-height: 1.5; }

.msjd-hist { width: 100%; border-collapse: collapse; font-size: 12px; }
.msjd-hist th { text-align: left; font-size: 10px; letter-spacing: .6px; text-transform: uppercase; color: #7b8794; font-weight: 800; padding: 6px 8px; border-bottom: 1px solid #eef2ee; white-space: nowrap; }
.msjd-hist td { padding: 7px 8px; border-bottom: 1px solid #f3f6f4; vertical-align: top; }
.msjd-hist .msjd-mail { font-weight: 600; }

.msjd-preview-frame { width: 100%; height: 560px; border: 1px solid #e3e9e5; border-radius: 10px; background: #fff; }

@media (max-width: 700px) {
  .msjd-head .msjd-back { margin-left: 0; }
  .msjd-btn--bar { margin-left: 0; }

  /* En móvil la tabla se lee como fichas: se evita el desplazamiento
     horizontal para que el correo y el estado siempre se vean. */
  .msjd-tabla { min-width: 0; }
  .msjd-tabla thead { display: none; }
  .msjd-tabla tr { display: block; border-bottom: 1px solid #eef2ee; padding: 8px 0; }
  .msjd-tabla td { display: flex; justify-content: space-between; gap: 10px; border: none; padding: 4px 6px; }
  .msjd-tabla td::before { content: attr(data-label); font-size: 10px; text-transform: uppercase; letter-spacing: .5px; color: #8492a0; font-weight: 800; }
  .msjd-tabla td:first-child { justify-content: flex-start; }
  .msjd-tabla td:first-child::before { content: ''; }
  .msjd-tabla .msjd-mail { white-space: normal; overflow-wrap: anywhere; }
}
</style>

<div class="content-wrapper msjd-page">

    <div class="msjd-head">
        <div>
            <h2><i class="fa fa-envelope-open-text" style="color:#2c6e46;"></i> Mensajes para usuarios demo</h2>
            <p>Correos de quienes crearon una cuenta de demostración. Se muestran las cuentas vigentes y las caducadas
               para invitarlos a comprar la cuenta oficial. Los correos ya migrados al sistema no aparecen.</p>
        </div>
        <a href="../inicio/" class="btn btn-default btn-sm msjd-back"><i class="fa fa-arrow-left"></i> Volver</a>
    </div>

    <div class="msjd-stats">
        <div class="msjd-stat">
            <span>Correos disponibles</span>
            <strong id="st-total">—</strong>
        </div>
        <div class="msjd-stat msjd-stat--ok">
            <span>Cuenta demo vigente</span>
            <strong id="st-vigentes">—</strong>
        </div>
        <div class="msjd-stat msjd-stat--no">
            <span>Cuenta demo caducada</span>
            <strong id="st-caducadas">—</strong>
        </div>
    </div>

    <div class="msjd-msg" id="msjd-msg"></div>

    <!-- ============================================================
         DESTINATARIOS
         ============================================================ -->
    <div class="msjd-card">
        <div class="msjd-card__head">
            <h3><i class="fa fa-address-book"></i> Destinatarios</h3>
            <div class="msjd-filtro">
                <button type="button" class="msjd-chip is-active" data-filtro="todas"><i class="fa fa-list"></i> Todas</button>
                <button type="button" class="msjd-chip" data-filtro="vigente"><i class="fa fa-circle-check"></i> Vigentes</button>
                <button type="button" class="msjd-chip" data-filtro="caducada"><i class="fa fa-clock-rotate-left"></i> Caducadas</button>
            </div>
        </div>
        <div class="msjd-card__body">
            <div class="msjd-buscar">
                <input type="text" id="msjd-buscar" placeholder="Buscar por correo, usuario o fundo…" autocomplete="off">
            </div>
            <div id="msjd-lista">
                <div class="msjd-vacio"><i class="fa fa-circle-notch fa-spin"></i> Cargando usuarios demo…</div>
            </div>
        </div>
        <div class="msjd-barra">
            <span class="msjd-sel" id="msjd-sel-count">0 seleccionados</span>
            <button type="button" class="msjd-btn msjd-btn--ghost msjd-btn--sm" id="btn-limpiar"><i class="fa fa-eraser"></i> Limpiar</button>
            <button type="button" class="msjd-btn msjd-btn--primary msjd-btn--bar" id="btn-redactar">
                <i class="fa fa-pen-to-square"></i> Redactar mensaje
            </button>
        </div>
    </div>

    <!-- ============================================================
         MENSAJE (se muestra al pulsar "Redactar mensaje")
         ============================================================ -->
    <div class="msjd-card" id="panel-mensaje" style="display:none;">
        <div class="msjd-card__head">
            <h3><i class="fa fa-paper-plane"></i> Mensaje para los usuarios demo</h3>
            <button type="button" class="msjd-btn msjd-btn--ghost msjd-btn--sm msjd-btn--bar" id="btn-cerrar-mensaje">
                <i class="fa fa-xmark"></i> Cerrar
            </button>
        </div>
        <div class="msjd-card__body">

            <div class="msjd-destinos" id="cmp-destinos">—</div>

            <div class="msjd-field">
                <label for="cmp-asunto">Asunto</label>
                <input type="text" id="cmp-asunto" maxlength="180" autocomplete="off">
                <div class="msjd-help">Puedes usar las variables de abajo también en el asunto.</div>
            </div>

            <div class="msjd-field">
                <label for="cmp-cuerpo">Mensaje</label>
                <textarea id="cmp-cuerpo" maxlength="6000"></textarea>
                <div class="msjd-vars">
                    <span class="msjd-var" data-var="{nombre}">{nombre}</span>
                    <span class="msjd-var" data-var="{usuario}">{usuario}</span>
                    <span class="msjd-var" data-var="{fundo}">{fundo}</span>
                    <span class="msjd-var" data-var="{vence}">{vence}</span>
                    <span class="msjd-var" data-var="{dias}">{dias}</span>
                    <span class="msjd-var" data-var="{estado}">{estado}</span>
                </div>
                <div class="msjd-help">
                    El correo se arma con la identidad de SOVCA: saludo personal, el estado de su cuenta demo
                    (vigente o caducada) y el botón <b>Quiero mi cuenta oficial</b> que abre WhatsApp con sus datos.
                </div>
            </div>

            <div class="msjd-progreso" id="cmp-progreso">
                <div class="msjd-barra-fondo"><span id="cmp-barra"></span></div>
                <small id="cmp-progreso-texto">Enviando…</small>
            </div>

            <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:14px;">
                <button type="button" class="msjd-btn msjd-btn--primary" id="btn-enviar">
                    <i class="fa fa-envelope"></i> <span id="btn-enviar-texto">Enviar mensaje</span>
                </button>
                <button type="button" class="msjd-btn msjd-btn--soft" id="btn-preview">
                    <i class="fa fa-eye"></i> Vista previa
                </button>
            </div>
        </div>
    </div>

    <!-- ============================================================
         HISTORIAL
         ============================================================ -->
    <div class="msjd-card">
        <div class="msjd-card__head">
            <h3><i class="fa fa-clock-rotate-left"></i> Últimos envíos</h3>
        </div>
        <div class="msjd-card__body" id="msjd-historial">
            <div class="msjd-vacio"><i class="fa fa-circle-notch fa-spin"></i> Cargando historial…</div>
        </div>
    </div>
</div>

<!-- Vista previa del correo -->
<div class="modal fade" id="msjd-modal-preview" tabindex="-1" role="dialog" aria-labelledby="msjd-modal-preview-titulo">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="msjd-modal-preview-titulo"><i class="fa fa-eye"></i> Vista previa del correo</h4>
            </div>
            <div class="modal-body">
                <iframe id="msjd-preview-frame" class="msjd-preview-frame" title="Vista previa del correo"></iframe>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script src="../../plugins/confirma/jquery-confirm.min.js"></script>
<script>
(function ($) {
    'use strict';

    var URL = '../../controllers/mensajes_demo_controller';
    var LOTE = 10;               // correos por petición

    var LEADS = [];              // lista completa del servidor
    var SELECCION = {};          // email -> true
    var FILTRO = 'todas';
    var BUSCAR = '';
    var SUGERIDO = { asunto: '', cuerpo: '' };
    var ENVIANDO = false;

    function esc(t) {
        return String(t == null ? '' : t)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function fecha(t) {
        if (!t) { return '—'; }
        var s = String(t).substr(0, 10).split('-');
        return s.length === 3 ? (s[2] + '/' + s[1] + '/' + s[0]) : t;
    }

    function fechaHora(t) {
        if (!t) { return '—'; }
        var p = String(t).replace('T', ' ').substr(0, 16).split(' ');
        var d = (p[0] || '').split('-');
        var h = (p[1] || '').substr(0, 5);
        return d.length === 3 ? (d[2] + '/' + d[1] + '/' + d[0] + ' ' + h) : t;
    }

    function mensaje(tipo, html) {
        var $m = $('#msjd-msg');
        $m.removeClass('msjd-msg--error msjd-msg--ok msjd-msg--info')
          .addClass('msjd-msg--' + tipo + ' is-visible').html(html);
        if (tipo !== 'error') {
            setTimeout(function () { $m.removeClass('is-visible'); }, 7000);
        }
    }

    function visibles() {
        return LEADS.filter(function (l) {
            if (FILTRO !== 'todas' && l.estado !== FILTRO) { return false; }
            if (BUSCAR === '') { return true; }
            var heno = (l.email + ' ' + l.nombre + ' ' + l.usuario + ' ' + l.fundo).toLowerCase();
            return heno.indexOf(BUSCAR) !== -1;
        });
    }

    function seleccionados() {
        return Object.keys(SELECCION).filter(function (e) { return SELECCION[e]; });
    }

    function detalleEstado(l) {
        if (l.estado === 'vigente') {
            if (l.dias === null || l.dias === undefined) { return 'Vigente'; }
            return l.dias === 0 ? 'Vence hoy' : ('Quedan ' + l.dias + ' día' + (l.dias === 1 ? '' : 's'));
        }
        if (l.dias === null || l.dias === undefined) { return 'Caducada'; }
        return l.dias === 0 ? 'Venció hoy' : ('Venció hace ' + Math.abs(l.dias) + ' día' + (Math.abs(l.dias) === 1 ? '' : 's'));
    }

    /* ---------------- Carga ---------------- */
    function cargar() {
        $.ajax({ url: URL, type: 'GET', data: { action: 'listar' }, dataType: 'json', cache: false })
            .done(function (d) {
                if (!d || !d.ok) {
                    $('#msjd-lista').html('<div class="msjd-vacio"><i class="fa fa-triangle-exclamation"></i>'
                        + esc((d && d.msg) || 'No se pudo cargar la información.') + '</div>');
                    return;
                }

                LEADS = d.leads || [];
                SUGERIDO = d.sugerido || { asunto: '', cuerpo: '' };

                $('#st-total').text(d.resumen.total);
                $('#st-vigentes').text(d.resumen.vigentes);
                $('#st-caducadas').text(d.resumen.caducadas);

                pintarLista();
                pintarHistorial(d.historial || []);

                if ($('#cmp-asunto').val() === '') { $('#cmp-asunto').val(SUGERIDO.asunto); }
                if ($('#cmp-cuerpo').val() === '') { $('#cmp-cuerpo').val(SUGERIDO.cuerpo); }
            })
            .fail(function () {
                $('#msjd-lista').html('<div class="msjd-vacio"><i class="fa fa-plug-circle-xmark"></i>No hubo respuesta del servidor.</div>');
            });
    }

    /* Etapas de la secuencia automática (lib/demo_correos_auto_helper.php) */
    var ETAPAS_AUTO = {
        bienvenida: 'Bienvenida',
        dia7: 'Día 7',
        dia14: 'Día 14',
        dia22: 'Día 22',
        dia30: 'Día 30'
    };

    function secuenciaTexto(etapas) {
        if (!etapas || !etapas.length) {
            return '<span class="msjd-sub">Sin iniciar</span>';
        }

        var partes = [];
        $.each(etapas, function (i, e) {
            if (e.estado !== 'ok') { return; }
            partes.push((ETAPAS_AUTO[e.etapa] || e.etapa)
                + (e.enviado ? ' · ' + fecha(e.enviado) : ''));
        });

        var conError = etapas.filter(function (e) { return e.estado === 'error'; }).length;

        if (!partes.length) {
            return '<span class="msjd-sub">' + (conError ? 'Con error de envío' : 'Pendiente') + '</span>';
        }

        return '<span class="msjd-secuencia">' + esc(partes.join(' · ')) + '</span>'
            + (conError ? '<span class="msjd-sub">1 con error</span>' : '');
    }

    /* ---------------- Lista ---------------- */
    function pintarLista() {
        var filas = visibles();

        if (!LEADS.length) {
            $('#msjd-lista').html('<div class="msjd-vacio"><i class="fa fa-inbox"></i>'
                + 'Todavía no hay usuarios demo registrados, o sus correos ya fueron migrados al sistema oficial.</div>');
            actualizarContador();
            return;
        }

        if (!filas.length) {
            $('#msjd-lista').html('<div class="msjd-vacio"><i class="fa fa-magnifying-glass"></i>'
                + 'Ningún correo coincide con el filtro o la búsqueda.</div>');
            actualizarContador();
            return;
        }

        var todosMarcados = true;
        $.each(filas, function (i, l) { if (!SELECCION[l.email]) { todosMarcados = false; } });

        var html = '<div class="msjd-tabla-wrap"><table class="msjd-tabla"><thead><tr>'
            + '<th style="width:34px;"><input type="checkbox" id="msjd-check-todos"' + (todosMarcados ? ' checked' : '') + '></th>'
            + '<th>Correo</th><th>Usuario / Fundo</th><th>Cuenta demo</th><th>Secuencia automática</th><th>Último envío manual</th>'
            + '</tr></thead><tbody>';

        $.each(filas, function (i, l) {
            html += '<tr>'
                + '<td data-label=""><input type="checkbox" class="msjd-check" data-email="' + esc(l.email) + '"'
                + (SELECCION[l.email] ? ' checked' : '') + '></td>'
                + '<td data-label="Correo"><span class="msjd-mail">' + esc(l.email) + '</span>'
                + '<span class="msjd-sub">Registrado el ' + fecha(l.registrado) + '</span></td>'
                + '<td data-label="Usuario / Fundo">' + esc(l.usuario || '—') + '<span class="msjd-sub">' + esc(l.nombre) + '</span>'
                + '<span class="msjd-sub">' + esc(l.fundo || 'Sin fundo') + '</span></td>'
                + '<td data-label="Cuenta demo"><span class="msjd-pill msjd-pill--' + l.estado + '">'
                + (l.estado === 'vigente' ? 'Vigente' : 'Caducada') + '</span>'
                + '<span class="msjd-sub">' + esc(detalleEstado(l))
                + (l.vence ? ' · ' + fecha(l.vence) : '') + '</span></td>'
                + '<td data-label="Secuencia">' + secuenciaTexto(l.secuencia) + '</td>'
                + '<td data-label="Último envío manual">' + (l.ultimo_envio
                    ? '<span class="msjd-sub">' + fechaHora(l.ultimo_envio) + '</span>'
                    : '<span class="msjd-sub">Sin enviar</span>') + '</td>'
                + '</tr>';
        });

        html += '</tbody></table></div>';
        $('#msjd-lista').html(html);
        actualizarContador();
    }

    function actualizarContador() {
        var n = seleccionados().length;
        $('#msjd-sel-count').text(n + ' seleccionado' + (n === 1 ? '' : 's'));
        $('#btn-limpiar').prop('disabled', n === 0);
    }

    /* ---------------- Selección ---------------- */
    $(document).on('change', '#msjd-check-todos', function () {
        var marcar = $(this).is(':checked');
        $.each(visibles(), function (i, l) {
            if (marcar) { SELECCION[l.email] = true; } else { delete SELECCION[l.email]; }
        });
        pintarLista();
    });

    $(document).on('change', '.msjd-check', function () {
        var email = $(this).data('email');
        if ($(this).is(':checked')) { SELECCION[email] = true; } else { delete SELECCION[email]; }
        actualizarContador();

        /* La casilla del encabezado refleja si quedó todo el filtro marcado */
        var filas = visibles();
        var todos = filas.length > 0;
        $.each(filas, function (i, l) { if (!SELECCION[l.email]) { todos = false; } });
        $('#msjd-check-todos').prop('checked', todos);
    });

    $('#btn-limpiar').on('click', function () {
        SELECCION = {};
        pintarLista();
    });

    $('.msjd-chip[data-filtro]').on('click', function () {
        $('.msjd-chip[data-filtro]').removeClass('is-active');
        $(this).addClass('is-active');
        FILTRO = $(this).data('filtro');
        pintarLista();
    });

    $('#msjd-buscar').on('input', function () {
        BUSCAR = $.trim($(this).val()).toLowerCase();
        pintarLista();
    });

    /* ---------------- Redacción ---------------- */
    function abrirRedaccion() {
        var lista = seleccionados();
        if (!lista.length) {
            mensaje('info', 'Marca al menos un correo de la lista para preparar el mensaje.');
            return;
        }

        var muestra = lista.slice(0, 6).join(', ') + (lista.length > 6 ? ' …' : '');
        $('#cmp-destinos').html('<b>' + lista.length + ' correo' + (lista.length === 1 ? '' : 's')
            + ' seleccionado' + (lista.length === 1 ? '' : 's') + '.</b>'
            + '<span class="msjd-lista-mails">' + esc(muestra) + '</span>');

        $('#panel-mensaje').show();
        document.getElementById('panel-mensaje').scrollIntoView({ behavior: 'smooth', block: 'start' });
        document.getElementById('cmp-asunto').focus();
    }

    $('#btn-redactar').on('click', abrirRedaccion);

    $('#btn-cerrar-mensaje').on('click', function () {
        if (ENVIANDO) {
            mensaje('info', 'El envío está en curso; espera a que termine.');
            return;
        }
        $('#panel-mensaje').hide();
    });

    /* Insertar variables en el cursor */
    $('.msjd-var').on('click', function () {
        var texto = $(this).data('var');
        var campo = document.getElementById('cmp-cuerpo');
        var ini = campo.selectionStart || 0;
        var fin = campo.selectionEnd || 0;
        campo.value = campo.value.substring(0, ini) + texto + campo.value.substring(fin);
        campo.selectionStart = campo.selectionEnd = ini + texto.length;
        campo.focus();
    });

    /* ---------------- Vista previa ---------------- */
    $('#btn-preview').on('click', function () {
        var asunto = $('#cmp-asunto').val();
        var cuerpo = $('#cmp-cuerpo').val();
        var lista = seleccionados();

        if ($.trim(cuerpo) === '') {
            mensaje('info', 'Escribe el mensaje antes de verlo.');
            return;
        }

        $.ajax({
            url: URL, type: 'POST', dataType: 'json', cache: false,
            data: { action: 'vista_previa', asunto: asunto, cuerpo: cuerpo, destino: lista.length ? lista[0] : '' }
        }).done(function (d) {
            if (!d || !d.ok) {
                mensaje('error', esc((d && d.msg) || 'No se pudo generar la vista previa.'));
                return;
            }
            var frame = document.getElementById('msjd-preview-frame');
            frame.srcdoc = d.html;
            $('#msjd-modal-preview').modal('show');
        }).fail(function () {
            mensaje('error', 'No hubo respuesta del servidor al generar la vista previa.');
        });
    });

    /* ---------------- Envío por lotes ---------------- */
    function progreso(pct, texto) {
        $('#cmp-progreso').addClass('is-visible');
        $('#cmp-barra').css('width', Math.max(0, Math.min(100, pct)) + '%');
        $('#cmp-progreso-texto').text(texto);
    }

    $('#btn-enviar').on('click', function () {
        if (ENVIANDO) { return; }

        var lista = seleccionados();
        var asunto = $.trim($('#cmp-asunto').val());
        var cuerpo = $('#cmp-cuerpo').val();

        if (!lista.length) {
            mensaje('info', 'Marca al menos un correo de la lista.');
            return;
        }
        if (asunto === '') {
            mensaje('error', 'Escribe el asunto del mensaje.');
            return;
        }
        if ($.trim(cuerpo) === '') {
            mensaje('error', 'Escribe el mensaje que se enviará.');
            return;
        }

        $.confirm({
            title: '¿Enviar el mensaje?',
            content: 'Se enviará a <b>' + lista.length + '</b> correo' + (lista.length === 1 ? '' : 's')
                + ' de usuarios demo. Esta acción no se puede deshacer.',
            confirmButton: 'Sí, enviar',
            cancelButton: 'Cancelar',
            confirmButtonClass: 'btn-success',
            cancelButtonClass: 'btn-danger',
            confirm: function () { enviarLotes(lista, asunto, cuerpo); },
            cancel: function () { }
        });
    });

    function enviarLotes(lista, asunto, cuerpo) {
        ENVIANDO = true;
        $('#btn-enviar').prop('disabled', true);
        $('#btn-preview').prop('disabled', true);
        $('#btn-enviar-texto').text('Enviando…');

        var total = lista.length;
        var procesados = 0;
        var enviados = 0;
        var fallidos = 0;
        var errores = [];

        progreso(0, 'Preparando el envío de ' + total + ' correo' + (total === 1 ? '' : 's') + '…');

        function terminar() {
            ENVIANDO = false;
            $('#btn-enviar').prop('disabled', false);
            $('#btn-preview').prop('disabled', false);
            $('#btn-enviar-texto').text('Enviar mensaje');
            progreso(100, 'Envío terminado: ' + enviados + ' enviado' + (enviados === 1 ? '' : 's')
                + (fallidos ? ' · ' + fallidos + ' con error' : ''));

            if (enviados > 0) {
                mensaje('ok', '<b>Mensaje enviado.</b> ' + enviados + ' correo' + (enviados === 1 ? '' : 's')
                    + ' procesado' + (enviados === 1 ? '' : 's') + ' correctamente'
                    + (fallidos ? '. ' + fallidos + ' no se pudo enviar: ' + esc(errores.slice(0, 3).join('; ')) : '.'));
            } else {
                mensaje('error', 'No se pudo enviar ningún correo. ' + esc(errores.slice(0, 3).join('; ')));
            }

            cargar();
        }

        function siguiente() {
            if (procesados >= total) { terminar(); return; }

            var parte = lista.slice(procesados, procesados + LOTE);
            progreso((procesados / total) * 100, 'Enviando ' + (procesados + 1) + '–'
                + (procesados + parte.length) + ' de ' + total + '…');

            $.ajax({
                url: URL, type: 'POST', dataType: 'json', cache: false, timeout: 120000,
                data: { action: 'enviar', asunto: asunto, cuerpo: cuerpo, destinos: JSON.stringify(parte) }
            }).done(function (d) {
                if (d && d.ok) {
                    enviados += d.enviados || 0;
                    fallidos += d.fallidos || 0;
                    $.each(d.detalle || [], function (i, r) {
                        if (!r.ok) { errores.push(r.email + ': ' + r.msg); }
                    });
                } else {
                    fallidos += parte.length;
                    errores.push((d && d.msg) ? d.msg : 'Error del servidor');
                }
            }).fail(function () {
                fallidos += parte.length;
                errores.push('No hubo respuesta del servidor');
            }).always(function () {
                procesados += parte.length;
                progreso((procesados / total) * 100, 'Enviados ' + procesados + ' de ' + total + '…');
                setTimeout(siguiente, 200);
            });
        }

        siguiente();
    }

    /* ---------------- Historial ---------------- */
    function pintarHistorial(filas) {
        if (!filas.length) {
            $('#msjd-historial').html('<div class="msjd-vacio"><i class="fa fa-inbox"></i>'
                + 'Todavía no se ha enviado ningún mensaje a los usuarios demo.</div>');
            return;
        }

        var html = '<div style="overflow-x:auto;"><table class="msjd-hist"><thead><tr>'
            + '<th>Fecha</th><th>Correo</th><th>Asunto</th><th>Estado</th>'
            + '</tr></thead><tbody>';

        $.each(filas, function (i, f) {
            html += '<tr>'
                + '<td>' + fechaHora(f.created) + '</td>'
                + '<td class="msjd-mail">' + esc(f.email) + '</td>'
                + '<td>' + esc(f.asunto) + '</td>'
                + '<td>' + (f.estado === 'ok'
                    ? '<span class="msjd-pill msjd-pill--vigente">Enviado</span>'
                    : '<span class="msjd-pill msjd-pill--caducada">Error</span>'
                        + (f.error ? '<span class="msjd-sub">' + esc(f.error) + '</span>' : ''))
                + '</td></tr>';
        });

        html += '</tbody></table></div>';
        $('#msjd-historial').html(html);
    }

    $(function () {
        cargar();
        actualizarContador();
    });
})(jQuery);
</script>

<?php include_once("../layouts/pie.php"); ?>
