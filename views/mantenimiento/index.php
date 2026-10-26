<?php
include("../../lib/validar_session.php");
ValidaSession("../login");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../lib/mantenimiento_helper.php';

$rolSesion = isset($_SESSION['rolx']) ? (int) $_SESSION['rolx'] : 0;
if (!in_array($rolSesion, array(1, 4), true)) {
    header('Location: ../inicio/denegado');
    exit();
}

$estado  = bovs_mantenimiento_estado();
$eventos = bovs_mantenimiento_bitacora(30);
?>
<?php include("../layouts/constantes.php"); ?>
<?php include_once("../layouts/cabecera.php"); ?>

<style>
  .mtto-page {
    max-width: 1080px;
    margin: 0 auto;
    padding: 4px 0 32px;
  }
  .mtto-hero {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    padding: 20px 22px;
    border-radius: 18px;
    background: linear-gradient(135deg, #14532d, #15803d);
    color: #f0fdf4;
    box-shadow: 0 10px 26px rgba(20, 83, 45, .18);
    margin-bottom: 18px;
  }
  .mtto-hero__icono {
    flex: 0 0 auto;
    width: 52px; height: 52px;
    display: grid; place-items: center;
    border-radius: 14px;
    background: rgba(255, 255, 255, .14);
    border: 1px solid rgba(255, 255, 255, .22);
    font-size: 22px;
  }
  .mtto-hero__texto { flex: 1 1 380px; min-width: 0; }
  .mtto-hero__texto h1 {
    margin: 0 0 4px;
    font-size: 20px;
    font-weight: 800;
    letter-spacing: .01em;
  }
  .mtto-hero__texto p {
    margin: 0;
    font-size: 13px;
    line-height: 1.55;
    color: #dcfce7;
  }
  .mtto-estado {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 14px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: .05em;
    text-transform: uppercase;
    white-space: nowrap;
  }
  .mtto-estado--on {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
  }
  .mtto-estado--off {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #86efac;
  }
  .mtto-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.15fr) minmax(0, .85fr);
    gap: 18px;
    align-items: start;
  }
  @media (max-width: 900px) {
    .mtto-grid { grid-template-columns: minmax(0, 1fr); }
  }
  .mtto-col { min-width: 0; }
  .mtto-card {
    background: #fff;
    border: 1px solid #e8e0cd;
    border-radius: 18px;
    box-shadow: 0 6px 18px rgba(31, 41, 55, .06);
    overflow: hidden;
  }
  .mtto-card__head {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 18px;
    border-bottom: 1px solid #f1ead9;
    background: #fbf9f3;
  }
  .mtto-card__head i { color: #15803d; font-size: 16px; }
  .mtto-card__head h2 {
    margin: 0;
    font-size: 12.5px;
    font-weight: 800;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: #4b5a4f;
  }
  .mtto-card__body { padding: 18px; }
  .mtto-datos {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 12px;
    margin: 0 0 14px;
  }
  .mtto-dato {
    padding: 10px 12px;
    border-radius: 12px;
    background: #f8faf9;
    border: 1px solid #eef2ef;
  }
  .mtto-dato small {
    display: block;
    font-size: 9.5px;
    letter-spacing: .09em;
    text-transform: uppercase;
    color: #7b8794;
    font-weight: 800;
    margin-bottom: 3px;
  }
  .mtto-dato strong {
    display: block;
    font-size: 13px;
    color: #1f2937;
    word-break: break-word;
  }
  .mtto-campo { margin-bottom: 14px; }
  .mtto-campo label {
    display: block;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: #4b5a4f;
    margin-bottom: 6px;
  }
  .mtto-campo textarea,
  .mtto-campo input,
  .mtto-campo select {
    width: 100%;
    box-sizing: border-box;
    border: 1px solid #d8dfd9;
    border-radius: 10px;
    padding: 10px 12px;
    font-size: 13px;
    color: #1f2937;
    background: #fff;
  }
  .mtto-campo textarea { min-height: 76px; resize: vertical; }
  .mtto-campo .mtto-ayuda {
    display: block;
    margin-top: 5px;
    font-size: 11.5px;
    color: #7b8794;
  }
  .mtto-acciones { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
  .mtto-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    border-radius: 10px;
    border: 1px solid transparent;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
  }
  .mtto-btn--peligro { background: #b91c1c; border-color: #991b1b; color: #fff; }
  .mtto-btn--ok { background: #15803d; border-color: #166534; color: #fff; }
  .mtto-btn--claro { background: #fff; border-color: #d8dfd9; color: #374151; }
  .mtto-btn:hover { filter: brightness(.96); text-decoration: none; }
  .mtto-btn:disabled { opacity: .6; cursor: progress; }
  .mtto-aviso {
    margin: 0 0 14px;
    padding: 12px 14px;
    border-radius: 12px;
    font-size: 12.5px;
    line-height: 1.5;
  }
  .mtto-aviso--error { background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; }
  .mtto-aviso--ok { background: #dcfce7; border: 1px solid #86efac; color: #166534; }
  .mtto-aviso--info { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }
  .mtto-tabla { width: 100%; border-collapse: collapse; font-size: 12.5px; }
  .mtto-tabla th,
  .mtto-tabla td {
    padding: 9px 10px;
    border-bottom: 1px solid #f1f0ea;
    text-align: left;
    vertical-align: top;
  }
  .mtto-tabla th {
    font-size: 10px;
    letter-spacing: .07em;
    text-transform: uppercase;
    color: #7b8794;
    background: #fbf9f3;
  }
  .mtto-tabla tr:last-child td { border-bottom: 0; }
  .mtto-chip {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .05em;
    text-transform: uppercase;
  }
  .mtto-chip--on { background: #fee2e2; color: #991b1b; }
  .mtto-chip--off { background: #e2e8f0; color: #334155; }
  .mtto-vacio { color: #7b8794; font-size: 12.5px; margin: 0; }
</style>

<div class="mtto-page">

  <section class="mtto-hero">
    <div class="mtto-hero__icono"><i class="fa fa-wrench" aria-hidden="true"></i></div>
    <div class="mtto-hero__texto">
      <h1>Modo mantenimiento del sistema</h1>
      <p>
        Con el mantenimiento activo, solo los administradores del sistema pueden acceder.
        Los productores y ayudantes quedan bloqueados en la web y en la app sin conexión.
        Para navegar el sistema durante la ventana, entra a un fundo con <strong>Modo soporte</strong> desde
        <a href="../fundos/?modo=soporte" style="color:#bbf7d0;text-decoration:underline;">Todos los Fundos</a>.
      </p>
    </div>
    <?php if ($estado['vigente']): ?>
      <span class="mtto-estado mtto-estado--on"><i class="fa fa-lock"></i> Activo</span>
    <?php else: ?>
      <span class="mtto-estado mtto-estado--off"><i class="fa fa-unlock"></i> Inactivo</span>
    <?php endif; ?>
  </section>

  <div id="mtto-aviso"></div>

  <div class="mtto-grid">

    <!-- ================= ESTADO + ACTIVAR ================= -->
    <div>
      <div class="mtto-card" style="margin-bottom:18px;">
        <div class="mtto-card__head">
          <i class="fa fa-info-circle" aria-hidden="true"></i>
          <h2>Estado actual</h2>
        </div>
        <div class="mtto-card__body">
          <div class="mtto-datos">
            <div class="mtto-dato">
              <small>Estado</small>
              <strong><?= $estado['vigente'] ? 'Cerrado (solo administradores)' : 'Abierto para todos' ?></strong>
            </div>
            <div class="mtto-dato">
              <small>Activado por</small>
              <strong><?= htmlspecialchars($estado['usuario'] !== '' ? $estado['usuario'] : '—', ENT_QUOTES, 'UTF-8') ?></strong>
            </div>
            <div class="mtto-dato">
              <small>Desde</small>
              <strong><?= htmlspecialchars($estado['desde'] !== '' ? $estado['desde'] : '—', ENT_QUOTES, 'UTF-8') ?></strong>
            </div>
            <div class="mtto-dato">
              <small>Cierre automático</small>
              <strong>
                <?php if ($estado['hasta'] !== ''): ?>
                  <?= htmlspecialchars($estado['hasta'], ENT_QUOTES, 'UTF-8') ?>
                  <?php if ($estado['minutos_restantes'] > 0): ?>
                    <span style="display:block;font-size:11.5px;color:#b45309;">(<?= (int) $estado['minutos_restantes'] ?> min restantes)</span>
                  <?php endif; ?>
                <?php else: ?>
                  Sin límite
                <?php endif; ?>
              </strong>
            </div>
            <div class="mtto-dato">
              <small>Origen del interruptor</small>
              <strong><?= htmlspecialchars($estado['origen'], ENT_QUOTES, 'UTF-8') ?></strong>
            </div>
            <div class="mtto-dato">
              <small>Roles permitidos</small>
              <strong><?= implode(', ', array_map('intval', $estado['roles'])) ?></strong>
            </div>
          </div>

          <?php if (trim((string) $estado['motivo']) !== ''): ?>
            <div class="mtto-aviso mtto-aviso--info">
              <strong>Motivo:</strong> <?= htmlspecialchars($estado['motivo'], ENT_QUOTES, 'UTF-8') ?>
            </div>
          <?php endif; ?>

          <?php if (!$estado['archivo_escribible']): ?>
            <div class="mtto-aviso mtto-aviso--error">
              <strong>Sin permiso de escritura.</strong>
              El servidor no puede guardar el estado en
              <code><?= htmlspecialchars($estado['archivo'], ENT_QUOTES, 'UTF-8') ?></code>.
              Ejecuta <code>php scripts/mantenimiento.php --instalar</code> con el usuario del
              despliegue o revisa los permisos (carpeta 777 y archivo 666).
            </div>
          <?php endif; ?>

          <?php if ($estado['vigente']): ?>
            <div class="mtto-acciones">
              <button type="button" id="mtto-desactivar" class="mtto-btn mtto-btn--ok">
                <i class="fa fa-unlock" aria-hidden="true"></i> Desactivar y abrir el sistema
              </button>
              <a class="mtto-btn mtto-btn--claro" href="../fundos/?modo=soporte">
                <i class="fa fa-user-secret" aria-hidden="true"></i> Entrar con Modo soporte
              </a>
            </div>
          <?php else: ?>
            <p class="mtto-vacio">
              El sistema está abierto. Usa el formulario de la derecha para cerrarlo
              durante las pruebas de mantenimiento.
            </p>
          <?php endif; ?>
        </div>
      </div>

      <!-- ================= BITÁCORA ================= -->
      <div class="mtto-card">
        <div class="mtto-card__head">
          <i class="fa fa-history" aria-hidden="true"></i>
          <h2>Bitácora reciente</h2>
        </div>
        <div class="mtto-card__body">
          <?php if (!$eventos): ?>
            <p class="mtto-vacio">Todavía no hay activaciones registradas.</p>
          <?php else: ?>
            <div style="overflow-x:auto;">
              <table class="mtto-tabla">
                <thead>
                  <tr>
                    <th>Fecha</th>
                    <th>Acción</th>
                    <th>Usuario</th>
                    <th>Motivo</th>
                    <th>Origen</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($eventos as $ev): ?>
                    <?php $esActivo = isset($ev['accion']) && $ev['accion'] === 'activar'; ?>
                    <tr>
                      <td><?= htmlspecialchars(isset($ev['fecha']) ? $ev['fecha'] : '', ENT_QUOTES, 'UTF-8') ?></td>
                      <td>
                        <span class="mtto-chip <?= $esActivo ? 'mtto-chip--on' : 'mtto-chip--off' ?>">
                          <?= $esActivo ? 'Cerró' : 'Abrió' ?>
                        </span>
                      </td>
                      <td><?= htmlspecialchars(isset($ev['usuario']) ? $ev['usuario'] : '—', ENT_QUOTES, 'UTF-8') ?></td>
                      <td><?= htmlspecialchars(isset($ev['motivo']) && $ev['motivo'] !== '' ? $ev['motivo'] : '—', ENT_QUOTES, 'UTF-8') ?></td>
                      <td><?= htmlspecialchars(isset($ev['origen']) ? $ev['origen'] : 'panel', ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- ================= COLUMNA DERECHA ================= -->
    <div class="mtto-col">

    <!-- ================= ENTRAR A UN FUNDO (MODO SOPORTE) ================= -->
    <div class="mtto-card" style="margin-bottom:18px;">
      <div class="mtto-card__head">
        <i class="fa fa-user-secret" aria-hidden="true"></i>
        <h2>Entrar a un fundo (Modo soporte)</h2>
      </div>
      <div class="mtto-card__body">
        <div class="mtto-campo">
          <label for="mtto-fundo">Fundo</label>
          <select id="mtto-fundo" disabled>
            <option value="">Cargando fundos…</option>
          </select>
          <span class="mtto-ayuda" id="mtto-fundo-ayuda">
            Elige un fundo y entra sin cambiar de cuenta ni de rol.
          </span>
        </div>
        <div class="mtto-acciones">
          <button type="button" id="mtto-entrar-fundo" class="mtto-btn mtto-btn--ok" disabled>
            <i class="fa fa-sign-in" aria-hidden="true"></i> Entrar en Modo soporte
          </button>
          <a class="mtto-btn mtto-btn--claro" href="../fundos/?modo=soporte">
            <i class="fa fa-list" aria-hidden="true"></i> Ver todos los fundos
          </a>
        </div>
      </div>
    </div>

    <!-- ================= FORMULARIO ACTIVAR ================= -->
    <div class="mtto-card">
      <div class="mtto-card__head">
        <i class="fa fa-lock" aria-hidden="true"></i>
        <h2>Cerrar el sistema</h2>
      </div>
      <div class="mtto-card__body">
        <form id="mtto-form" autocomplete="off">
          <div class="mtto-campo">
            <label for="mtto-motivo">Motivo del mantenimiento</label>
            <textarea id="mtto-motivo" name="motivo" maxlength="200"
                      placeholder="Ej.: Pruebas de rendimiento en el servidor productivo"></textarea>
            <span class="mtto-ayuda">Obligatorio (mínimo 5 caracteres). Queda registrado en la bitácora.</span>
          </div>

          <div class="mtto-campo">
            <label for="mtto-duracion">Duración</label>
            <select id="mtto-duracion" name="duracion_min">
              <option value="30">30 minutos</option>
              <option value="60">1 hora</option>
              <option value="120" selected>2 horas</option>
              <option value="240">4 horas</option>
              <option value="480">8 horas</option>
              <option value="0">Sin límite (desactivar a mano)</option>
            </select>
            <span class="mtto-ayuda">
              Al cumplirse el tiempo el sistema se abre solo; así una prueba no deja
              a los usuarios fuera por un olvido.
            </span>
          </div>

          <div class="mtto-campo">
            <label for="mtto-mensaje">Mensaje para los usuarios (opcional)</label>
            <input type="text" id="mtto-mensaje" name="mensaje" maxlength="180"
                   placeholder="Ej.: Volvemos a las 3:00 p. m.">
            <span class="mtto-ayuda">Se muestra en el login y en la app sin conexión. Si se deja vacío se usa un texto estándar.</span>
          </div>

          <div class="mtto-acciones">
            <button type="submit" id="mtto-activar" class="mtto-btn mtto-btn--peligro">
              <i class="fa fa-power-off" aria-hidden="true"></i> Activar mantenimiento
            </button>
          </div>

          <?php if ($estado['origen'] === 'entorno'): ?>
            <div class="mtto-aviso mtto-aviso--info" style="margin-top:14px;">
              El interruptor está forzado por la variable de entorno
              <code>SOVCA_MANTENIMIENTO</code>. Los cambios hechos aquí no tendrán efecto
              hasta que se quite esa variable en Apache.
            </div>
          <?php endif; ?>
        </form>
      </div>
    </div>

    </div><!-- /columna derecha -->

  </div>
</div>

<script>
(function () {
  // La página vive en /views/mantenimiento/, así que el controlador está
  // DOS niveles arriba (/controllers/). Con "../controllers" apuntaba a
  // /views/controllers/ y Apache devolvía 404.
  var URL = '../../controllers/mmantenimiento_controller';
  var aviso = document.getElementById('mtto-aviso');

  function mostrar(tipo, texto) {
    if (!aviso) return;
    aviso.innerHTML = '<div class="mtto-aviso mtto-aviso--' + tipo + '">' + texto + '</div>';
    aviso.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  function escapar(t) {
    return String(t || '').replace(/[&<>"]/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
    });
  }

  /* ----------------------------------------------------------------
     ENTRAR A UN FUNDO EN MODO SOPORTE
     El listado viene del mismo endpoint que usa la página de fundos.
     ---------------------------------------------------------------- */
  var selFundo  = document.getElementById('mtto-fundo');
  var btnFundo  = document.getElementById('mtto-entrar-fundo');
  var ayudaFundo = document.getElementById('mtto-fundo-ayuda');

  function cargarFundos() {
    if (!selFundo) return;

    $.getJSON('../../data_json/data_mfundos', null, function (lista) {
      if (!Array.isArray(lista) || !lista.length) {
        selFundo.innerHTML = '<option value="">Sin fundos disponibles</option>';
        if (ayudaFundo) ayudaFundo.textContent = 'No se encontraron fundos activos. Usa "Ver todos los fundos".';
        return;
      }

      var html = '<option value="">— Selecciona un fundo —</option>';
      lista.forEach(function (f) {
        var prop = (f.propietario || '').trim();
        var avisoProp = (Number(f.propietario_estado) === 1) ? '' : ' (sin propietario activo)';
        var etiqueta = (f.nombre || ('Fundo ' + f.id)) + (prop ? ' — ' + prop : '') + avisoProp;
        html += '<option value="' + escapar(f.id) + '">' + escapar(etiqueta) + '</option>';
      });

      selFundo.innerHTML = html;
      selFundo.disabled = false;
      if (ayudaFundo) {
        ayudaFundo.textContent = lista.length + ' fundo(s) disponibles. Elige uno y entra sin cambiar de cuenta ni de rol.';
      }
    }).fail(function () {
      if (ayudaFundo) ayudaFundo.textContent = 'No se pudo cargar la lista de fundos. Usa "Ver todos los fundos".';
    });
  }

  if (selFundo) {
    selFundo.addEventListener('change', function () {
      if (btnFundo) btnFundo.disabled = !selFundo.value;
    });
    cargarFundos();
  }

  if (btnFundo) {
    btnFundo.addEventListener('click', function () {
      var id = selFundo ? selFundo.value : '';
      if (!id) {
        mostrar('error', 'Selecciona un fundo para entrar en Modo soporte.');
        return;
      }

      btnFundo.disabled = true;
      btnFundo.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Entrando…';

      $.post('../../controllers/mfundos_controller.php', {
        action: 'activar_supervisor',
        fundo_id: id,
        motivo: 'Acceso desde el panel de mantenimiento'
      }, null, 'json').done(function (r) {
        if (r && r.ok) {
          window.location.href = '../inicio/';
          return;
        }
        mostrar('error', escapar((r && r.msg) || 'No se pudo entrar al fundo.'));
      }).fail(function () {
        mostrar('error', 'No se pudo entrar al fundo. Revisa la sesión e inténtalo de nuevo.');
      }).always(function () {
        btnFundo.disabled = false;
        btnFundo.innerHTML = '<i class="fa fa-sign-in" aria-hidden="true"></i> Entrar en Modo soporte';
      });
    });
  }

  function enviar(datos, boton) {
    var textoOriginal = boton ? boton.innerHTML : '';
    if (boton) {
      boton.disabled = true;
      boton.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Procesando…';
    }

    $.post(URL, datos, null, 'json')
      .done(function (r) {
        if (!r || !r.ok) {
          mostrar('error', escapar((r && r.msg) || 'No se pudo completar la operación.'));
          return;
        }
        var extra = r.aviso ? '<br><span style="font-size:12px;">' + escapar(r.aviso) + '</span>' : '';
        mostrar('ok', escapar(r.msg) + extra);
        setTimeout(function () { window.location.reload(); }, 1400);
      })
      .fail(function (xhr) {
        var msg = 'No se pudo completar la operación.';
        try {
          var d = JSON.parse(xhr.responseText);
          if (d && d.msg) msg = d.msg;
        } catch (e) { /* respuesta no JSON */ }
        mostrar('error', escapar(msg));
      })
      .always(function () {
        if (boton) {
          boton.disabled = false;
          boton.innerHTML = textoOriginal;
        }
      });
  }

  var form = document.getElementById('mtto-form');
  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();

      var motivo = (document.getElementById('mtto-motivo') || {}).value || '';
      motivo = motivo.trim();
      if (motivo.length < 5) {
        mostrar('error', 'Indica el motivo del mantenimiento (mínimo 5 caracteres).');
        return;
      }

      var ok = window.confirm(
        'Se cerrará el sistema para productores y ayudantes.\n\n' +
        'Solo los administradores del sistema podrán acceder mientras dure el mantenimiento.\n\n' +
        '¿Activar el modo mantenimiento ahora?'
      );
      if (!ok) return;

      enviar({
        action: 'activar',
        motivo: motivo,
        duracion_min: (document.getElementById('mtto-duracion') || {}).value || '120',
        mensaje: (document.getElementById('mtto-mensaje') || {}).value || ''
      }, document.getElementById('mtto-activar'));
    });
  }

  var btnOff = document.getElementById('mtto-desactivar');
  if (btnOff) {
    btnOff.addEventListener('click', function () {
      var ok = window.confirm('¿Desactivar el mantenimiento y permitir el acceso a todos los usuarios?');
      if (!ok) return;
      enviar({ action: 'desactivar' }, btnOff);
    });
  }
})();
</script>

<?php include_once("../layouts/pie.php"); ?>
