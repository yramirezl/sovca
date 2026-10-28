<?php
include("../../lib/validar_session.php");
ValidaSession("../login");

if (session_status() === PHP_SESSION_NONE) session_start();
$__rol = isset($_SESSION['rolx']) ? (int)$_SESSION['rolx'] : 0;

/* El maestro de planes es exclusivo del Super Usuario (4). */
if ($__rol !== 4) {
    header("Location: ../inicio/");
    exit();
}
?>
<?php include("../layouts/constantes.php") ?>
<?php include_once("../layouts/cabecera.php") ?>

<link rel="stylesheet" href="../../plugins/confirma/jquery-confirm.min.css" type="text/css" />
<script type="text/javascript" src="../../plugins/confirma/jquery-confirm.min.js"></script>

<style>
/* ============================================================
   MÓDULO DE PLANES
   ============================================================ */
.pl-wrap { max-width: 1040px; margin: 0 auto; padding-bottom: 40px; }

.pl-hero {
    background: linear-gradient(135deg, #234e35 0%, #2c6e46 55%, #2f8f4a 100%);
    border-radius: 14px;
    padding: 22px 26px;
    color: #fff;
    margin-bottom: 20px;
    box-shadow: 0 8px 24px rgba(35, 78, 53, .22);
    display: flex;
    align-items: center;
    gap: 18px;
    position: relative;
    overflow: hidden;
}
.pl-hero::before {
    content: "";
    position: absolute;
    top: -60px; right: -60px;
    width: 220px; height: 220px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(255,255,255,.14) 0%, transparent 70%);
    pointer-events: none;
}
.pl-hero-icon {
    flex-shrink: 0;
    width: 58px; height: 58px;
    border-radius: 14px;
    background: rgba(255,255,255,.18);
    border: 1px solid rgba(255,255,255,.28);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 26px;
}
.pl-hero-info { flex: 1; min-width: 0; position: relative; z-index: 1; }
.pl-hero-info h2 { margin: 0 0 4px; font-size: 22px; font-weight: 800; color: #fff; }
.pl-hero-info p { margin: 0; font-size: 13px; line-height: 1.5; color: rgba(255,255,255,.85); max-width: 640px; }

.pl-card {
    background: #fff;
    border: 1px solid #e3ebe5;
    border-radius: 14px;
    box-shadow: 0 2px 10px rgba(35, 78, 53, .06);
    overflow: hidden;
}
.pl-card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 16px 20px;
    border-bottom: 1px solid #eef3ef;
    background: #f8fbf9;
}
.pl-card-head h3 { margin: 0; font-size: 15px; font-weight: 800; color: #234e35; }
.pl-card-head small { display: block; color: #8895a4; font-weight: 400; font-size: 12px; margin-top: 2px; }

.pl-btn {
    display: inline-flex; align-items: center; gap: 6px;
    border: 0; border-radius: 8px; padding: 9px 16px;
    font-size: 13px; font-weight: 700; cursor: pointer;
    background: #2f8f4a; color: #fff; transition: .15s;
}
.pl-btn:hover { background: #267a3e; color: #fff; }
.pl-btn--ghost { background: #eef3ef; color: #234e35; }
.pl-btn--ghost:hover { background: #e1ebe4; color: #234e35; }
.pl-btn--sm { padding: 5px 10px; font-size: 12px; border-radius: 7px; }
.pl-btn--danger { background: #fdecea; color: #c62828; }
.pl-btn--danger:hover { background: #fbd7d3; color: #b71c1c; }
.pl-btn:disabled { opacity: .55; cursor: not-allowed; }

table.pl-tabla { width: 100%; border-collapse: collapse; }
table.pl-tabla thead th {
    background: #f3f8f4;
    color: #5a6b60;
    font-size: 11.5px;
    text-transform: uppercase;
    letter-spacing: .4px;
    font-weight: 800;
    padding: 11px 14px;
    text-align: left;
    border-bottom: 1px solid #e6efe8;
    white-space: nowrap;
}
table.pl-tabla tbody td {
    padding: 12px 14px;
    border-bottom: 1px solid #f0f4f1;
    font-size: 13px;
    color: #33463a;
    vertical-align: middle;
}
table.pl-tabla tbody tr:hover { background: #fafdfb; }
table.pl-tabla tbody tr:last-child td { border-bottom: 0; }
.pl-num { font-variant-numeric: tabular-nums; font-weight: 700; color: #234e35; }
.pl-plan-nombre { font-weight: 800; color: #234e35; }

.pl-pill {
    display: inline-block; padding: 3px 10px; border-radius: 999px;
    font-size: 11px; font-weight: 800; letter-spacing: .3px;
}
.pl-pill--on  { background: #e8f5e9; color: #2f8f4a; }
.pl-pill--off { background: #eef1f3; color: #7b8a94; }

.pl-alerta {
    border-radius: 10px; padding: 12px 16px; font-size: 13px;
    display: flex; gap: 10px; align-items: flex-start; margin-bottom: 16px;
}
.pl-alerta--info { background: #eef6ff; color: #1a4f8a; border: 1px solid #d3e6fb; }
.pl-alerta--ok   { background: #eaf7ee; color: #24713c; border: 1px solid #cfe9d8; }
.pl-alerta--warn { background: #fff8e6; color: #8a6100; border: 1px solid #f6e3b4; }
.pl-alerta--err  { background: #fdecea; color: #a12622; border: 1px solid #f7cdc9; }
.pl-alerta i { margin-top: 2px; }

.pl-cargando { padding: 34px; text-align: center; color: #8895a4; font-size: 13px; }

/* Formulario dentro del modal */
.pl-form label { display: block; font-size: 12px; font-weight: 800; color: #5a6b60; margin: 14px 0 5px; text-transform: uppercase; letter-spacing: .3px; }
.pl-form input[type="text"], .pl-form input[type="number"], .pl-form select {
    width: 100%; padding: 9px 12px; border: 1px solid #d9e3dc; border-radius: 8px;
    font-size: 14px; color: #33463a; background: #fff;
}
.pl-form input:focus, .pl-form select:focus { outline: none; border-color: #2f8f4a; box-shadow: 0 0 0 3px rgba(47,143,74,.12); }
.pl-form small { display: block; color: #8895a4; font-size: 11.5px; margin-top: 4px; line-height: 1.4; }
.pl-form-fila { display: flex; gap: 14px; }
.pl-form-fila > div { flex: 1; }
</style>

<div class="content-wrapper">
  <section class="content">
    <div class="pl-wrap">

      <div class="pl-hero">
        <div class="pl-hero-icon"><i class="fa fa-tags"></i></div>
        <div class="pl-hero-info">
          <h2>Planes</h2>
          <p>
            El plan define cuántos registros de animales y cuántos fundos admite una cuenta.
            Estos son los valores por defecto; puede subir el cupo cuando haga falta sin
            obligar al productor a cambiar de plan.
          </p>
        </div>
      </div>

      <div id="pl-mensaje"></div>

      <div class="pl-alerta pl-alerta--info">
        <i class="fa fa-info-circle"></i>
        <div>
          Al cambiar los fundos de un plan, las cuentas multifundo de ese plan se ajustan solas.
          Si una cuenta ya tiene más fundos de los que el plan pasa a permitir, conserva su
          cupo actual y el sistema solo bloqueará altas nuevas hasta que se regularice.
        </div>
      </div>

      <div class="pl-card">
        <div class="pl-card-head">
          <div>
            <h3>Configuración de planes</h3>
            <small>Los cambios se aplican de inmediato a las cuentas nuevas y al próximo inicio de sesión de las existentes.</small>
          </div>
          <button type="button" class="pl-btn" id="pl-nuevo">
            <i class="fa fa-plus"></i> Nuevo plan
          </button>
        </div>

        <div id="pl-tabla-cont">
          <div class="pl-cargando"><i class="fa fa-spinner fa-spin"></i> Cargando planes…</div>
        </div>
      </div>

    </div>
  </section>
</div>

<script type="text/javascript">
jQuery(function ($) {
    "use strict";

    var CTRL = "../../controllers/mplanes_controller";
    var planes = [];

    function esc(s) {
        return $('<div>').text(s === null || s === undefined ? '' : s).html();
    }

    function mensaje(tipo, html) {
        var icono = tipo === 'ok' ? 'fa-check-circle'
                  : tipo === 'err' ? 'fa-ban'
                  : tipo === 'warn' ? 'fa-exclamation-triangle'
                  : 'fa-info-circle';
        $('#pl-mensaje').html(
            '<div class="pl-alerta pl-alerta--' + tipo + '">'
          +   '<i class="fa ' + icono + '"></i><div>' + html + '</div>'
          + '</div>'
        );
    }

    function cargar() {
        $.post(CTRL, { action: 'planes_listar' }, function (r) {
            if (!r || !r.ok) {
                $('#pl-tabla-cont').html('<div class="pl-cargando">No se pudieron cargar los planes.</div>');
                return;
            }
            planes = r.planes || [];
            pintar();
        }, 'json').fail(function () {
            $('#pl-tabla-cont').html('<div class="pl-cargando">Error de conexión.</div>');
        });
    }

    function pintar() {
        if (!planes.length) {
            $('#pl-tabla-cont').html('<div class="pl-cargando">No hay planes registrados.</div>');
            return;
        }

        var h = '<table class="pl-tabla"><thead><tr>'
              +   '<th>Plan</th>'
              +   '<th>Registros</th>'
              +   '<th>Fundos</th>'
              +   '<th>Cuentas activas</th>'
              +   '<th>Estado</th>'
              +   '<th style="text-align:right;">Acciones</th>'
              + '</tr></thead><tbody>';

        $.each(planes, function (i, p) {
            h += '<tr>'
               +   '<td class="pl-plan-nombre">' + esc(p.nombre) + '</td>'
               +   '<td class="pl-num">' + p.max_reg.toLocaleString('es') + '</td>'
               +   '<td class="pl-num">' + p.max_fundos + '</td>'
               +   '<td>' + p.cuentas + (p.multifundo ? ' <small style="color:#8895a4;">(' + p.multifundo + ' multifundo)</small>' : '') + '</td>'
               +   '<td><span class="pl-pill ' + (p.status === 1 ? 'pl-pill--on' : 'pl-pill--off') + '">'
               +     (p.status === 1 ? 'ACTIVO' : 'INACTIVO') + '</span></td>'
               +   '<td style="text-align:right; white-space:nowrap;">'
               +     '<button type="button" class="pl-btn pl-btn--ghost pl-btn--sm" data-editar="' + p.id + '">'
               +       '<i class="fa fa-pencil"></i> Editar'
               +     '</button> '
               +     '<button type="button" class="pl-btn ' + (p.status === 1 ? 'pl-btn--danger' : 'pl-btn--ghost') + ' pl-btn--sm" data-estado="' + p.id + '" data-nuevo="' + (p.status === 1 ? 0 : 1) + '">'
               +       '<i class="fa ' + (p.status === 1 ? 'fa-ban' : 'fa-check') + '"></i> '
               +       (p.status === 1 ? 'Desactivar' : 'Activar')
               +     '</button>'
               +   '</td>'
               + '</tr>';
        });

        h += '</tbody></table>';
        $('#pl-tabla-cont').html(h);
    }

    function buscar(id) {
        for (var i = 0; i < planes.length; i++) {
            if (String(planes[i].id) === String(id)) { return planes[i]; }
        }
        return null;
    }

    /* ---------------- Modal de alta / edición ---------------- */
    function abrirFormulario(plan) {
        var esNuevo = !plan;
        var p = plan || { nombre: '', max_reg: 20, max_fundos: 1, status: 1 };

        var html = ''
          + '<div class="pl-form">'
          +   '<label>Nombre del plan</label>'
          +   '<input type="text" id="plf-nombre" maxlength="40" value="' + esc(p.nombre) + '" placeholder="Ej.: DIAMANTE">'
          +   '<div class="pl-form-fila">'
          +     '<div>'
          +       '<label>Registros de animales</label>'
          +       '<input type="number" id="plf-reg" min="1" max="1000000" value="' + p.max_reg + '">'
          +       '<small>Cupo de animales activos por fundo.</small>'
          +     '</div>'
          +     '<div>'
          +       '<label>Fundos por cuenta</label>'
          +       '<input type="number" id="plf-fundos" min="1" max="100" value="' + p.max_fundos + '">'
          +       '<small>Cuántas fincas puede administrar una cuenta multifundo.</small>'
          +     '</div>'
          +   '</div>'
          +   '<label>Estado</label>'
          +   '<select id="plf-status">'
          +     '<option value="1"' + (p.status === 1 ? ' selected' : '') + '>Activo</option>'
          +     '<option value="0"' + (p.status === 0 ? ' selected' : '') + '>Inactivo</option>'
          +   '</select>'
          + '</div>';

        $.confirm({
            title: (esNuevo ? 'Nuevo plan' : 'Editar plan: ' + esc(p.nombre)),
            content: html,
            columnClass: 'medium',
            confirmButton: 'Guardar',
            cancelButton: 'Cancelar',
            confirmButtonClass: 'btn-success',
            cancelButtonClass: 'btn-default',
            confirm: function () {
                var $this = this;
                var datos = {
                    action: 'planes_guardar',
                    plan_id: esNuevo ? 0 : p.id,
                    nombre: $('#plf-nombre').val(),
                    max_reg: $('#plf-reg').val(),
                    max_fundos: $('#plf-fundos').val(),
                    status: $('#plf-status').val()
                };

                if (!String(datos.nombre || '').trim()) {
                    mensaje('err', 'El nombre del plan es obligatorio.');
                    return false;
                }

                $.post(CTRL, datos, function (r) {
                    if (r && r.ok) {
                        mensaje('ok', 'Plan guardado correctamente.');
                        cargar();
                    } else {
                        mensaje('err', (r && r.msg) || 'No se pudo guardar el plan.');
                    }
                }, 'json').fail(function () {
                    mensaje('err', 'Error de conexión.');
                });

                return false; // cierra el modal sin esperar
            }
        });
    }

    $('#pl-nuevo').on('click', function (e) {
        e.preventDefault();
        abrirFormulario(null);
    });

    $(document).on('click', '#pl-tabla-cont [data-editar]', function (e) {
        e.preventDefault();
        var p = buscar($(this).attr('data-editar'));
        if (p) { abrirFormulario(p); }
    });

    $(document).on('click', '#pl-tabla-cont [data-estado]', function (e) {
        e.preventDefault();
        var id    = $(this).attr('data-estado');
        var nuevo = parseInt($(this).attr('data-nuevo'), 10) === 1 ? 1 : 0;
        var p     = buscar(id);
        if (!p) { return; }

        $.confirm({
            title: (nuevo ? '¿Activar el plan?' : '¿Desactivar el plan?'),
            content: nuevo
                ? 'El plan <b>' + esc(p.nombre) + '</b> volverá a estar disponible al registrar cuentas.'
                : 'El plan <b>' + esc(p.nombre) + '</b> dejará de ofrecerse. Las cuentas que ya lo tienen no se modifican.',
            confirmButton: nuevo ? 'Sí, activar' : 'Sí, desactivar',
            cancelButton: 'Cancelar',
            confirmButtonClass: nuevo ? 'btn-success' : 'btn-danger',
            cancelButtonClass: 'btn-default',
            confirm: function () {
                $.post(CTRL, { action: 'planes_estado', plan_id: id, status: nuevo }, function (r) {
                    if (r && r.ok) {
                        mensaje('ok', r.msg || 'Listo.');
                        cargar();
                    } else {
                        mensaje('err', (r && r.msg) || 'No se pudo cambiar el estado.');
                    }
                }, 'json').fail(function () {
                    mensaje('err', 'Error de conexión.');
                });
                return false;
            }
        });
    });

    cargar();
});
</script>

<?php include("../layouts/pie.php") ?>
