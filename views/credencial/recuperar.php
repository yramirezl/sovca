<?php
/**
 * views/credencial/recuperar.php
 * ---------------------------------------------------------------------------
 * "¿Olvidaste tu contraseña?" — Paso 1
 *
 * El usuario escribe el correo de su cuenta y el sistema le envía un
 * CÓDIGO de 6 dígitos (vida útil 15 minutos) que luego escribe junto con
 * su contraseña nueva en views/credencial/reset_password.php (paso 2).
 *
 * · La solicitud la procesa controllers/musuarios_controller.php
 *   (action=solicitar_reset), que exige el captcha y responde siempre lo
 *   mismo exista o no la cuenta.
 * · El captcha es el mismo del inicio de sesión (lib/captcha_helper.php) y
 *   se puede cambiar sin recargar la página.
 */
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

include(__DIR__ . '/../layouts/constantes.php');
require_once __DIR__ . '/../../lib/captcha_helper.php';

$cap       = bovs_captcha_generar();
$cap_a     = $cap['a'];
$cap_b     = $cap['b'];
$cap_signo = $cap['signo'];
$cap_token = $cap['token'];
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <title>Recuperar contraseña — <?php echo $constante["titulo_pag"] ?? 'SOVCA'; ?></title>
  <meta content="width=device-width, initial-scale=1, maximum-scale=5, user-scalable=yes" name="viewport">

  <link href="../../css/fontawesome6/css/all.min.css" rel="stylesheet">
  <link href="../../css/fontawesome6/css/v4-shims.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap" rel="stylesheet">

  <style>
    *, *::before, *::after { box-sizing: border-box; }

    html, body {
      background-color: #ebf5ee;
      background-image: radial-gradient(circle at 50% 50%, #f4fbf6 0%, #dbe9df 100%);
      min-height: 100vh;
      margin: 0;
      font-family: 'Poppins', 'Source Sans Pro', 'Helvetica Neue', Helvetica, Arial, sans-serif;
    }

    body::before, body::after {
      content: ""; position: absolute; border-radius: 50%; pointer-events: none;
    }
    body::before {
      width: 520px; height: 520px; top: -180px; right: -160px;
      background: radial-gradient(circle, rgba(35,78,53,.08) 0%, rgba(35,78,53,0) 70%);
    }
    body::after {
      width: 620px; height: 620px; bottom: -260px; left: -220px;
      background: radial-gradient(circle, rgba(40,167,69,.08) 0%, rgba(40,167,69,0) 70%);
    }

    .cred-wrap {
      display: flex; align-items: center; justify-content: center;
      min-height: 100vh; padding: 30px 15px; position: relative; z-index: 3;
    }

    .cred-card {
      width: 440px; max-width: 100%;
      background: #ffffff; border-radius: 20px;
      padding: 38px 34px 30px;
      box-shadow: 0 24px 60px rgba(0,0,0,.18), 0 8px 20px rgba(0,0,0,.10);
      position: relative; overflow: hidden;
      animation: cardEnter .6s cubic-bezier(.22,1,.36,1);
    }
    @keyframes cardEnter {
      from { opacity: 0; transform: translateY(20px) scale(.97); }
      to   { opacity: 1; transform: translateY(0) scale(1); }
    }
    .cred-card::before {
      content: ""; position: absolute; top: -60px; right: -50px;
      width: 160px; height: 160px; border-radius: 50%;
      background: rgba(35,78,53,.05); pointer-events: none;
    }

    .cred-logo { text-align: center; margin-bottom: 10px; position: relative; z-index: 2; }
    .cred-logo img { max-width: 240px; width: 100%; height: auto; display: block; margin: 0 auto; }

    .cred-badge {
      display: block; width: fit-content; margin: 0 auto 18px;
      padding: 5px 16px; background: #e8f5e9; color: #1b5e20;
      font-size: 11px; font-weight: 800; border-radius: 20px;
      letter-spacing: .8px; text-transform: uppercase;
    }

    .cred-title {
      margin: 0 0 8px; text-align: center;
      color: #1b3a24; font-size: 20px; font-weight: 800;
      letter-spacing: 1.2px; text-transform: uppercase;
    }
    .cred-sub {
      margin: 0 0 22px; text-align: center;
      color: #5c6b62; font-size: 12.8px; line-height: 1.55;
    }

    .cred-alert {
      display: none; align-items: flex-start; gap: 10px;
      padding: 12px 14px; border-radius: 10px; margin-bottom: 16px;
      font-size: 12.8px; font-weight: 600; line-height: 1.45;
    }
    .cred-alert.visible { display: flex; }
    .cred-alert i { font-size: 15px; margin-top: 1px; flex-shrink: 0; }
    .cred-alert.ok    { background: #ecfdf3; color: #14532d; border: 1px solid #a7e8c1; }
    .cred-alert.error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

    .form-group { margin-bottom: 15px; }
    .form-group label {
      display: block; font-size: 10.5px; font-weight: 800;
      color: #6b7d70; text-transform: uppercase;
      letter-spacing: .8px; margin-bottom: 6px;
    }

    .input-wrap { position: relative; }
    .input-wrap input {
      width: 100%; height: 46px; border-radius: 10px;
      border: 1px solid #d7e4da; background: #f8fbf9;
      font-family: inherit; font-size: 14px; color: #16281d;
      padding: 0 44px 0 14px; outline: none;
      transition: border-color .2s, box-shadow .2s, background .2s;
    }
    .input-wrap input:focus {
      border-color: #2c6e46; background: #fff;
      box-shadow: 0 0 0 3px rgba(44,110,70,.14);
    }
    .input-wrap input.is-invalid { border-color: #dc2626; background: #fff7f7; }
    .input-wrap .input-icon {
      position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
      color: #9aab9f; font-size: 14px; pointer-events: none;
    }
    .input-wrap .btn-eye {
      position: absolute; right: 4px; top: 50%; transform: translateY(-50%);
      background: transparent; border: none; padding: 8px 12px;
      color: #6b7d70; font-size: 14px; cursor: pointer; border-radius: 8px;
    }
    .input-wrap .btn-eye:hover { background: #eef6f0; color: #2c6e46; }

    .label-row {
      display: flex; align-items: center; justify-content: space-between;
      gap: 8px; margin-bottom: 6px;
    }
    .label-row label { margin-bottom: 0 !important; }
    .cap-refresh {
      display: inline-flex; align-items: center; gap: 5px;
      background: transparent; border: none; cursor: pointer;
      color: #2c6e46; font-family: inherit;
      font-size: 10.5px; font-weight: 800; text-transform: uppercase;
      letter-spacing: .6px; padding: 2px 4px; border-radius: 6px;
    }
    .cap-refresh:hover { background: #eef6f0; }
    .cap-refresh.girando i { animation: girar .8s linear infinite; }
    @keyframes girar { to { transform: rotate(360deg); } }

    .captcha-row { display: flex; gap: 10px; align-items: stretch; }
    .captcha-pregunta {
      flex: 0 0 auto; display: inline-flex; align-items: center; gap: 7px;
      padding: 0 14px; min-height: 46px; border-radius: 10px;
      background: linear-gradient(135deg, #f3f9f4, #e6f2e9);
      border: 1px solid #cfe3d5; color: #1b3a24;
      font-size: 17px; font-weight: 800; letter-spacing: 1px;
    }
    .captcha-pregunta .op { color: #2c6e46; }
    .captcha-pregunta.cargando { opacity: .55; }
    .captcha-row .input-wrap { flex: 1 1 auto; }

    .btn-cred {
      width: 100%; height: 50px; margin-top: 8px;
      border: none; border-radius: 10px; cursor: pointer;
      background: linear-gradient(135deg, #234e35 0%, #2c6e46 55%, #3a8a58 100%);
      color: #fff; font-family: inherit;
      font-size: 13px; font-weight: 800; letter-spacing: 1px;
      text-transform: uppercase;
      display: inline-flex; align-items: center; justify-content: center; gap: 9px;
      box-shadow: 0 6px 18px rgba(35,78,53,.32);
      transition: transform .15s, box-shadow .25s;
    }
    .btn-cred:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 10px 26px rgba(35,78,53,.42); }
    .btn-cred:disabled { opacity: .65; cursor: wait; transform: none; }

    .cred-foot {
      margin-top: 20px; padding-top: 16px;
      border-top: 1px solid #eef4ef; text-align: center;
    }
    .cred-foot a {
      color: #2c6e46; font-size: 12.5px; font-weight: 700;
      text-decoration: none; display: inline-flex; align-items: center; gap: 6px;
    }
    .cred-foot a:hover { text-decoration: underline; }

    .cred-version {
      margin-top: 18px; text-align: center;
      font-size: 10.5px; color: #9aab9f; letter-spacing: .4px;
    }

    @media (max-width: 480px) {
      .cred-card { padding: 30px 22px 24px; border-radius: 16px; }
      .cred-logo img { max-width: 200px; }
      .cred-title { font-size: 17px; }
    }
  </style>
</head>

<body>
  <div class="cred-wrap">
    <div class="cred-card">

      <div class="cred-logo">
        <a href="../login" style="text-decoration:none;">
          <img src="../../dist/img/sovca.png" alt="SOVCA">
        </a>
      </div>
      <span class="cred-badge">Gestión Ovina y Caprina</span>

      <h1 class="cred-title">Recuperar contraseña</h1>
      <p class="cred-sub">
        Escribe el correo electrónico asociado a tu cuenta.<br>
        Te enviaremos un <b>código de 6 dígitos</b> para crear una contraseña nueva.
      </p>

      <div class="cred-alert" id="cred-alerta" role="alert" aria-live="polite">
        <i class="fa fa-info-circle"></i>
        <span id="cred-alerta-texto"></span>
      </div>

      <form id="formRecuperar" autocomplete="off" novalidate>
        <input type="hidden" name="captcha_token" value="<?= htmlspecialchars($cap_token, ENT_QUOTES, 'UTF-8') ?>" />

        <div class="form-group">
          <label for="email">Correo electrónico</label>
          <div class="input-wrap">
            <input type="email" id="email" name="email" placeholder="tucorreo@ejemplo.com"
              autocomplete="email" autocorrect="off" spellcheck="false" maxlength="100">
            <i class="fa fa-envelope input-icon"></i>
          </div>
        </div>

        <div class="form-group">
          <div class="label-row">
            <label for="captcha_respuesta">Verificación de seguridad</label>
            <button type="button" id="cap-refresh" class="cap-refresh"
              title="Generar otra verificación" aria-label="Generar otra verificación">
              <i class="fa fa-rotate-right"></i>
              <span>Cambiar</span>
            </button>
          </div>
          <div class="captcha-row">
            <div class="captcha-pregunta" id="captcha-caja">
              <span id="cap-a"><?= $cap_a ?></span>
              <span class="op" id="cap-op"><?= $cap_signo ?></span>
              <span id="cap-b"><?= $cap_b ?></span>
              <span style="color:#8a998f;font-weight:600;font-size:14px;">=</span>
            </div>
            <div class="input-wrap">
              <input id="captcha_respuesta" name="captcha_respuesta" type="text" placeholder="Resultado"
                inputmode="numeric" pattern="[0-9\-]*" maxlength="3" autocomplete="off">
            </div>
          </div>
        </div>

        <button type="submit" class="btn-cred" id="btnEnviar">
          <i class="fa fa-paper-plane"></i>
          Enviar código
        </button>
      </form>

      <div class="cred-foot">
        <a href="../login">
          <i class="fa fa-arrow-left"></i>
          Volver al inicio de sesión
        </a>
      </div>

      <div class="cred-version">SOVCA · © <?= date('Y') ?> Global-SOVCA</div>
    </div>
  </div>

  <script>
    (function () {
      'use strict';

      var form    = document.getElementById('formRecuperar');
      var btn     = document.getElementById('btnEnviar');
      var caja    = document.getElementById('captcha-caja');
      var btnCap  = document.getElementById('cap-refresh');
      var alerta  = document.getElementById('cred-alerta');
      var alertaT = document.getElementById('cred-alerta-texto');
      var pidiendo = false;

      function mostrarAlerta(tipo, msg) {
        alerta.classList.remove('ok', 'error');
        alerta.classList.add(tipo, 'visible');
        alerta.querySelector('i').className = (tipo === 'ok')
          ? 'fa fa-circle-check'
          : 'fa fa-circle-exclamation';
        alertaT.textContent = msg;
      }

      function limpiarAlerta() {
        alerta.classList.remove('visible', 'ok', 'error');
        alertaT.textContent = '';
      }

      /* ---------- Captcha: pedir otro sin recargar la página ---------- */
      function refrescarCaptcha(silencioso) {
        if (pidiendo) return;
        pidiendo = true;
        if (caja) caja.classList.add('cargando');
        if (btnCap) btnCap.classList.add('girando');

        var xhr = new XMLHttpRequest();
        xhr.open('POST', '../../controllers/mcaptcha_controller', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onreadystatechange = function () {
          if (xhr.readyState !== 4) return;

          pidiendo = false;
          if (caja) caja.classList.remove('cargando');
          if (btnCap) btnCap.classList.remove('girando');

          var d = null;
          try { d = JSON.parse(xhr.responseText); } catch (e) { d = null; }
          if (xhr.status !== 200 || !d || !d.ok) {
            if (!silencioso) mostrarAlerta('error', 'No se pudo actualizar la verificación. Intenta de nuevo.');
            return;
          }

          document.getElementById('cap-a').textContent = d.a;
          document.getElementById('cap-b').textContent = d.b;
          document.getElementById('cap-op').textContent = d.signo;
          form.querySelector('input[name="captcha_token"]').value = d.token;

          var cap = document.getElementById('captcha_respuesta');
          if (cap) { cap.value = ''; if (!silencioso) cap.focus(); }
        };
        xhr.send('action=nuevo&_=' + Date.now());
      }

      /* ---------- Envío ---------- */
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        limpiarAlerta();

        var emailEl = document.getElementById('email');
        var capEl   = document.getElementById('captcha_respuesta');
        var email   = emailEl.value.trim();
        var captcha = capEl.value.trim();

        emailEl.classList.remove('is-invalid');
        capEl.classList.remove('is-invalid');

        if (!email) {
          emailEl.classList.add('is-invalid');
          mostrarAlerta('error', 'Escribe el correo electrónico de tu cuenta.');
          emailEl.focus();
          return;
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) {
          emailEl.classList.add('is-invalid');
          mostrarAlerta('error', 'El correo electrónico no parece válido.');
          emailEl.focus();
          return;
        }
        if (!captcha) {
          capEl.classList.add('is-invalid');
          mostrarAlerta('error', 'Resuelve la verificación de seguridad.');
          capEl.focus();
          return;
        }

        var htmlOriginal = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Enviando...';

        var xhr = new XMLHttpRequest();
        xhr.open('POST', '../../controllers/musuarios_controller', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        xhr.onreadystatechange = function () {
          if (xhr.readyState !== 4) return;

          btn.disabled = false;
          btn.innerHTML = htmlOriginal;

          var r = null;
          try { r = JSON.parse(xhr.responseText); } catch (e) { r = null; }

          if (r && r.ok) {
            mostrarAlerta('ok', r.msg + ' Redirigiendo para escribir el código...');
            form.reset();
            refrescarCaptcha(true);
            // Paso 2: pantalla del código (el correo queda en la sesión)
            setTimeout(function () {
              window.location.href = 'reset_password';
            }, 1600);
            return;
          }

          mostrarAlerta('error', (r && r.msg) ? r.msg : 'No se pudo procesar la solicitud. Intenta nuevamente.');
          if (r && r.captcha_error) refrescarCaptcha(true);
        };

        xhr.onerror = function () {
          btn.disabled = false;
          btn.innerHTML = htmlOriginal;
          mostrarAlerta('error', 'Error de conexión. Revisa tu internet e intenta de nuevo.');
        };

        var body = 'action=solicitar_reset'
          + '&email=' + encodeURIComponent(email)
          + '&captcha_token=' + encodeURIComponent(form.querySelector('input[name="captcha_token"]').value)
          + '&captcha_respuesta=' + encodeURIComponent(captcha);

        xhr.send(body);
      });

      if (btnCap) {
        btnCap.addEventListener('click', function (e) {
          e.preventDefault();
          limpiarAlerta();
          refrescarCaptcha(false);
        });
      }

      var capInput = document.getElementById('captcha_respuesta');
      if (capInput) {
        capInput.addEventListener('input', function () {
          this.value = this.value.replace(/[^\d\-]/g, '');
        });
      }

      ['email', 'captcha_respuesta'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('focus', function () { this.classList.remove('is-invalid'); });
      });

      setTimeout(function () {
        var el = document.getElementById('email');
        if (el) el.focus();
      }, 350);
    })();
  </script>
</body>

</html>
