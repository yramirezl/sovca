<?php
/**
 * views/credencial/reset_password.php
 * ---------------------------------------------------------------------------
 * "¿Olvidaste tu contraseña?" — Paso 2
 *
 * El usuario escribe el CÓDIGO de 6 dígitos que recibió por correo y su
 * contraseña nueva (dos veces, con la lista de requisitos en vivo).
 *
 * · El correo llega desde la sesión ($_SESSION['reset_email'], puesto por
 *   action=solicitar_reset). Si la sesión se perdió, el campo se puede
 *   escribir a mano: el servidor valida correo + código juntos.
 * · El código lo valida controllers/musuarios_controller.php
 *   (action=aplicar_reset): caducidad de 15 min, 5 intentos, un solo uso y
 *   las mismas reglas de contraseña que "Cambiar contraseña"
 *   (lib/password_policy.php).
 * · En la base de datos solo existe el HASH del código.
 */
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

include(__DIR__ . '/../layouts/constantes.php');

$resetEmail = isset($_SESSION['reset_email']) ? trim((string) $_SESSION['reset_email']) : '';
$resetExpira = isset($_SESSION['reset_expira']) ? (int) $_SESSION['reset_expira'] : 0;
$segundosRestantes = $resetExpira > 0 ? max(0, $resetExpira - time()) : 0;
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <title>Nueva contraseña — <?php echo $constante["titulo_pag"] ?? 'SOVCA'; ?></title>
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
      width: 470px; max-width: 100%;
      background: #ffffff; border-radius: 20px;
      padding: 34px 32px 28px;
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

    .cred-logo { text-align: center; margin-bottom: 8px; position: relative; z-index: 2; }
    .cred-logo img { max-width: 220px; width: 100%; height: auto; display: block; margin: 0 auto; }

    .cred-badge {
      display: block; width: fit-content; margin: 0 auto 14px;
      padding: 5px 16px; background: #e8f5e9; color: #1b5e20;
      font-size: 11px; font-weight: 800; border-radius: 20px;
      letter-spacing: .8px; text-transform: uppercase;
    }

    .cred-title {
      margin: 0 0 6px; text-align: center;
      color: #1b3a24; font-size: 19px; font-weight: 800;
      letter-spacing: 1px; text-transform: uppercase;
    }
    .cred-sub {
      margin: 0 0 18px; text-align: center;
      color: #5c6b62; font-size: 12.5px; line-height: 1.55;
    }
    .cred-sub b { color: #234e35; }

    .cred-alert {
      display: none; align-items: flex-start; gap: 10px;
      padding: 11px 13px; border-radius: 10px; margin-bottom: 14px;
      font-size: 12.5px; font-weight: 600; line-height: 1.45;
    }
    .cred-alert.visible { display: flex; }
    .cred-alert i { font-size: 15px; margin-top: 1px; flex-shrink: 0; }
    .cred-alert.ok    { background: #ecfdf3; color: #14532d; border: 1px solid #a7e8c1; }
    .cred-alert.error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

    .form-group { margin-bottom: 14px; }
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
      padding: 0 46px 0 14px; outline: none;
      transition: border-color .2s, box-shadow .2s, background .2s;
    }
    .input-wrap input:focus {
      border-color: #2c6e46; background: #fff;
      box-shadow: 0 0 0 3px rgba(44,110,70,.14);
    }
    .input-wrap input.is-invalid { border-color: #dc2626; background: #fff7f7; }
    .input-wrap input[readonly] { background: #f1f6f2; color: #4b5a51; }
    .input-wrap .btn-eye {
      position: absolute; right: 4px; top: 50%; transform: translateY(-50%);
      background: transparent; border: none; padding: 8px 12px;
      color: #6b7d70; font-size: 14px; cursor: pointer; border-radius: 8px;
    }
    .input-wrap .btn-eye:hover { background: #eef6f0; color: #2c6e46; }

    /* ---------- Casillas del código ---------- */
    .codigo-row {
      display: flex; gap: 7px; justify-content: center; margin: 2px 0 8px;
    }
    .codigo-digit {
      flex: 1 1 0; min-width: 0; max-width: 46px;
      height: 54px;
      text-align: center; font-family: 'Courier New', Consolas, monospace;
      font-size: 22px; font-weight: 800; color: #1b3a24;
      border: 2px solid #d7e4da; border-radius: 10px;
      background: #f8fbf9; outline: none;
      transition: border-color .15s, box-shadow .15s, background .15s;
      -moz-appearance: textfield;
    }
    .codigo-digit::-webkit-outer-spin-button,
    .codigo-digit::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    .codigo-digit:focus {
      border-color: #2c6e46; background: #fff;
      box-shadow: 0 0 0 3px rgba(44,110,69,.15);
    }
    .codigo-digit.lleno { border-color: #7fc29b; background: #f2fbf5; }
    .codigo-digit.error { border-color: #dc2626; background: #fff7f7; }

    .codigo-info {
      display: flex; align-items: center; justify-content: space-between;
      gap: 8px; margin-bottom: 14px;
    }
    .codigo-timer {
      display: inline-flex; align-items: center; gap: 6px;
      font-size: 11.5px; font-weight: 700; color: #6b7d70;
    }
    .codigo-timer.vencido { color: #b91c1c; }
    .codigo-timer i { font-size: 11px; }
    .btn-reenviar {
      display: inline-flex; align-items: center; gap: 5px;
      background: #fff; border: 1px solid #cfe0d5; border-radius: 7px;
      padding: 5px 10px; cursor: pointer;
      color: #2c6e46; font-family: inherit;
      font-size: 10.5px; font-weight: 800; text-transform: uppercase;
      letter-spacing: .5px;
    }
    .btn-reenviar:hover:not(:disabled) { background: #eef6f0; }
    .btn-reenviar:disabled { opacity: .6; cursor: not-allowed; }

    .match-msg {
      display: none; align-items: center; gap: 8px;
      padding: 8px 11px; border-radius: 9px; margin-bottom: 11px;
      font-size: 11.8px; font-weight: 700;
    }
    .match-msg.visible { display: flex; }
    .match-msg.ok    { background: #ecfdf3; border: 1px solid #a7e8c1; color: #14532d; }
    .match-msg.error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }

    .checklist {
      background: linear-gradient(135deg, #f7fbf8 0%, #eef6f0 100%);
      border: 1px solid #d7e7db; border-radius: 11px;
      padding: 12px 14px; margin: 2px 0 14px;
    }
    .checklist h4 {
      margin: 0 0 8px; font-size: 10.5px; font-weight: 800;
      color: #1b5e20; text-transform: uppercase; letter-spacing: .8px;
    }
    .checklist ul { list-style: none; margin: 0; padding: 0; display: grid; gap: 5px; }
    .checklist li {
      display: flex; align-items: center; gap: 8px;
      font-size: 11.5px; color: #6b7d70; font-weight: 500;
      transition: color .2s;
    }
    .checklist li::before {
      content: "\f00d"; font-family: "Font Awesome 6 Free"; font-weight: 900;
      display: inline-flex; align-items: center; justify-content: center;
      width: 16px; height: 16px; border-radius: 50%; font-size: 8px;
      background: #fee2e2; color: #dc2626; flex-shrink: 0;
      transition: background .2s, color .2s;
    }
    .checklist li.valid { color: #14532d; font-weight: 700; }
    .checklist li.valid::before { content: "\f00c"; background: #d1fae5; color: #059669; }

    .btn-cred {
      width: 100%; height: 50px; margin-top: 2px;
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
    .btn-cred:disabled { opacity: .6; cursor: not-allowed; transform: none; }

    .cred-foot {
      margin-top: 18px; padding-top: 14px;
      border-top: 1px solid #eef4ef; text-align: center;
    }
    .cred-foot a {
      color: #2c6e46; font-size: 12.5px; font-weight: 700;
      text-decoration: none; display: inline-flex; align-items: center; gap: 6px;
    }
    .cred-foot a:hover { text-decoration: underline; }

    .cred-version {
      margin-top: 16px; text-align: center;
      font-size: 10.5px; color: #9aab9f; letter-spacing: .4px;
    }

    @media (max-width: 480px) {
      .cred-card { padding: 28px 20px 22px; border-radius: 16px; }
      .cred-logo img { max-width: 180px; }
      .cred-title { font-size: 16.5px; }
      .codigo-row { gap: 5px; }
      .codigo-digit { max-width: 40px; height: 48px; font-size: 19px; }
    }

    @media (max-width: 340px) {
      .codigo-row { gap: 4px; }
      .codigo-digit { height: 44px; font-size: 17px; border-radius: 8px; }
    }
  </style>
</head>

<body>
  <div class="cred-wrap">
    <div class="cred-card">

      <div class="cred-logo">
        <a href="../login" style="text-decoration:none;">
          <img src="../../dist/img/logo_sirelog_bovs.png" alt="BOVS">
        </a>
      </div>
      <span class="cred-badge">Gestión Ganadera</span>

      <h1 class="cred-title">Nueva contraseña</h1>
      <p class="cred-sub">
        Escribe el <b>código de 6 dígitos</b> que enviamos a tu correo<br>
        y luego tu contraseña nueva.
      </p>

      <div class="cred-alert" id="cred-alerta" role="alert" aria-live="polite">
        <i class="fa fa-info-circle"></i>
        <span id="cred-alerta-texto"></span>
      </div>

      <form id="formReset" autocomplete="off" novalidate>

        <div class="form-group">
          <label for="email">Correo electrónico</label>
          <div class="input-wrap">
            <input type="email" id="email" name="email" placeholder="tucorreo@ejemplo.com"
              autocomplete="email" autocorrect="off" spellcheck="false" maxlength="100"
              value="<?= htmlspecialchars($resetEmail, ENT_QUOTES, 'UTF-8') ?>"
              <?= $resetEmail !== '' ? 'readonly' : '' ?>>
            <i class="fa fa-envelope input-icon" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);color:#9aab9f;font-size:14px;pointer-events:none;"></i>
          </div>
        </div>

        <div class="form-group">
          <label>Código de verificación</label>
          <div class="codigo-row" id="codigoRow">
            <input type="text" class="codigo-digit" maxlength="1" inputmode="numeric" pattern="[0-9]"
              autocomplete="one-time-code" aria-label="Dígito 1">
            <input type="text" class="codigo-digit" maxlength="1" inputmode="numeric" pattern="[0-9]"
              autocomplete="off" aria-label="Dígito 2">
            <input type="text" class="codigo-digit" maxlength="1" inputmode="numeric" pattern="[0-9]"
              autocomplete="off" aria-label="Dígito 3">
            <input type="text" class="codigo-digit" maxlength="1" inputmode="numeric" pattern="[0-9]"
              autocomplete="off" aria-label="Dígito 4">
            <input type="text" class="codigo-digit" maxlength="1" inputmode="numeric" pattern="[0-9]"
              autocomplete="off" aria-label="Dígito 5">
            <input type="text" class="codigo-digit" maxlength="1" inputmode="numeric" pattern="[0-9]"
              autocomplete="off" aria-label="Dígito 6">
          </div>
          <div class="codigo-info">
            <span class="codigo-timer" id="codigoTimer">
              <i class="fa fa-clock"></i>
              <span>El código caduca en <b id="codigoTimerTexto">15:00</b></span>
            </span>
            <button type="button" class="btn-reenviar" id="btnReenviar">
              <i class="fa fa-rotate-right"></i>
              Reenviar
            </button>
          </div>
        </div>

        <div class="form-group">
          <label for="password1">Nueva contraseña</label>
          <div class="input-wrap">
            <input type="password" id="password1" name="password1"
              placeholder="Escribe la nueva contraseña" autocomplete="new-password">
            <button type="button" class="btn-eye" onclick="togglePass('password1', this)" tabindex="-1"
              aria-label="Mostrar u ocultar la contraseña">
              <i class="fa fa-eye-slash"></i>
            </button>
          </div>
        </div>

        <div class="form-group">
          <label for="password2">Confirmar contraseña</label>
          <div class="input-wrap">
            <input type="password" id="password2" name="password2"
              placeholder="Repite la nueva contraseña" autocomplete="new-password">
            <button type="button" class="btn-eye" onclick="togglePass('password2', this)" tabindex="-1"
              aria-label="Mostrar u ocultar la contraseña">
              <i class="fa fa-eye-slash"></i>
            </button>
          </div>
        </div>

        <div class="match-msg" id="matchMsg">
          <i class="fa fa-circle-check"></i>
          <span id="matchText">Las contraseñas coinciden</span>
        </div>

        <div class="checklist">
          <h4><i class="fa fa-shield-halved"></i> Requisitos de seguridad</h4>
          <ul>
            <li id="req-length">Al menos <strong>&nbsp;8 caracteres</strong></li>
            <li id="req-capital">Al menos <strong>&nbsp;una letra mayúscula</strong></li>
            <li id="req-lower">Al menos <strong>&nbsp;una letra minúscula</strong></li>
            <li id="req-number">Al menos <strong>&nbsp;un número</strong></li>
            <li id="req-special">Al menos <strong>&nbsp;uno de estos: @ # $ %</strong></li>
            <li id="req-allowed">Solo letras, números y <strong>&nbsp;@ # $ %</strong></li>
          </ul>
        </div>

        <button type="submit" class="btn-cred" id="btnGuardar" disabled>
          <i class="fa fa-floppy-disk"></i>
          Guardar nueva contraseña
        </button>
      </form>

      <div class="cred-foot">
        <a href="recuperar">
          <i class="fa fa-arrow-left"></i>
          Usar otro correo / pedir un código nuevo
        </a>
      </div>

      <div class="cred-version">BOVS · © <?= date('Y') ?> Global-BOVS</div>
    </div>
  </div>

  <script>
    (function () {
      'use strict';

      var form    = document.getElementById('formReset');
      var btn     = document.getElementById('btnGuardar');
      var btnReen = document.getElementById('btnReenviar');
      var emailEl = document.getElementById('email');
      var alerta  = document.getElementById('cred-alerta');
      var alertaT = document.getElementById('cred-alerta-texto');
      var match   = document.getElementById('matchMsg');
      var matchT  = document.getElementById('matchText');
      var p1      = document.getElementById('password1');
      var p2      = document.getElementById('password2');
      var digits  = Array.prototype.slice.call(document.querySelectorAll('.codigo-digit'));
      var timerEl = document.getElementById('codigoTimer');
      var timerTx = document.getElementById('codigoTimerTexto');
      var valid1  = false;
      var valid2  = false;
      var enviando = false;

      /* ---------- Mensajes ---------- */
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

      window.togglePass = function (id, boton) {
        var input = document.getElementById(id);
        var icon  = boton.querySelector('i');
        if (!input || !icon) return;
        if (input.type === 'password') {
          input.type = 'text';
          icon.classList.remove('fa-eye-slash');
          icon.classList.add('fa-eye');
        } else {
          input.type = 'password';
          icon.classList.remove('fa-eye');
          icon.classList.add('fa-eye-slash');
        }
      };

      /* ---------- Código: 6 casillas ---------- */
      function codigoActual() {
        return digits.map(function (d) { return d.value; }).join('');
      }

      function marcarCodigo(clase) {
        digits.forEach(function (d) {
          d.classList.remove('error');
          if (clase === 'error') d.classList.add('error');
        });
      }

      function refrescarCasillas() {
        digits.forEach(function (d) {
          if (d.value) d.classList.add('lleno');
          else d.classList.remove('lleno');
        });
        actualizarBoton();
      }

      digits.forEach(function (d, i) {
        d.addEventListener('input', function () {
          var v = this.value.replace(/[^0-9]/g, '');
          this.value = v.slice(-1);
          marcarCodigo();
          if (this.value && i < digits.length - 1) digits[i + 1].focus();
          refrescarCasillas();
        });

        d.addEventListener('keydown', function (e) {
          if (e.key === 'Backspace' && !this.value && i > 0) {
            digits[i - 1].focus();
            digits[i - 1].value = '';
            refrescarCasillas();
            e.preventDefault();
          }
          if (e.key === 'ArrowLeft' && i > 0) { digits[i - 1].focus(); e.preventDefault(); }
          if (e.key === 'ArrowRight' && i < digits.length - 1) { digits[i + 1].focus(); e.preventDefault(); }
        });

        d.addEventListener('paste', function (e) {
          var texto = (e.clipboardData || window.clipboardData).getData('text') || '';
          var nums = texto.replace(/[^0-9]/g, '');
          if (!nums) return;
          e.preventDefault();
          nums.split('').slice(0, digits.length).forEach(function (n, k) {
            digits[k].value = n;
          });
          var ultimo = Math.min(nums.length, digits.length) - 1;
          if (ultimo >= 0) digits[ultimo].focus();
          marcarCodigo();
          refrescarCasillas();
        });

        d.addEventListener('focus', function () { this.select(); });
      });

      /* ---------- Contador del código ---------- */
      var restante = <?= (int) $segundosRestantes ?>;

      function pintarReloj() {
        if (restante <= 0) {
          timerEl.classList.add('vencido');
          timerEl.querySelector('span').innerHTML = 'El código caducó: pide uno nuevo';
          return;
        }
        var m = Math.floor(restante / 60);
        var s = restante % 60;
        timerTx.textContent = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
      }

      if (restante > 0) {
        pintarReloj();
        setInterval(function () {
          restante--;
          pintarReloj();
        }, 1000);
      }

      /* ---------- Contraseña ---------- */
      function validarPass1() {
        var v = p1.value;
        var checks = {
          'req-length':  v.length >= 8,
          'req-capital': /[A-ZÁÉÍÓÚÜÑ]/.test(v),
          'req-lower':   /[a-záéíóúüñ]/.test(v),
          'req-number':  /\d/.test(v),
          'req-special': /[@#$%]/.test(v),
          'req-allowed': v.length > 0 && /^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ0-9@#$%]+$/.test(v)
        };

        var todas = true;
        for (var id in checks) {
          var el = document.getElementById(id);
          if (checks[id]) {
            el.classList.add('valid');
          } else {
            el.classList.remove('valid');
            todas = false;
          }
        }
        valid1 = todas;
        p1.classList.remove('is-invalid');
        validarPass2();
      }

      function validarPass2() {
        if (p2.value === '') {
          match.classList.remove('visible', 'ok', 'error');
          valid2 = false;
          actualizarBoton();
          return;
        }
        if (p1.value === p2.value) {
          match.classList.add('visible', 'ok');
          match.classList.remove('error');
          match.querySelector('i').className = 'fa fa-circle-check';
          matchT.textContent = 'Las contraseñas coinciden';
          valid2 = true;
        } else {
          match.classList.add('visible', 'error');
          match.classList.remove('ok');
          match.querySelector('i').className = 'fa fa-circle-exclamation';
          matchT.textContent = 'Las contraseñas no coinciden';
          valid2 = false;
        }
        actualizarBoton();
      }

      function emailValido() {
        return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(emailEl.value.trim());
      }

      function actualizarBoton() {
        btn.disabled = enviando || !(valid1 && valid2 && codigoActual().length === 6 && emailValido());
      }

      p1.addEventListener('input', validarPass1);
      p2.addEventListener('input', validarPass2);
      emailEl.addEventListener('input', function () {
        this.classList.remove('is-invalid');
        actualizarBoton();
      });

      /* ---------- Reenviar código ---------- */
      if (btnReen) {
        btnReen.addEventListener('click', function () {
          var email = emailEl.value.trim();
          if (!email) {
            emailEl.classList.add('is-invalid');
            mostrarAlerta('error', 'Escribe el correo de tu cuenta.');
            emailEl.focus();
            return;
          }

          btnReen.disabled = true;
          var htmlOrig = btnReen.innerHTML;
          btnReen.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Enviando...';

          var xhr = new XMLHttpRequest();
          xhr.open('POST', '../../controllers/musuarios_controller', true);
          xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
          xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
          xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) return;
            btnReen.disabled = false;
            btnReen.innerHTML = htmlOrig;

            var r = null;
            try { r = JSON.parse(xhr.responseText); } catch (e) { r = null; }

            if (r && r.ok) {
              mostrarAlerta('ok', r.msg);
              // Reiniciar las casillas para escribir el código nuevo
              digits.forEach(function (d) { d.value = ''; d.classList.remove('lleno', 'error'); });
              digits[0].focus();
              refrescarCasillas();
            } else {
              mostrarAlerta('error', (r && r.msg) ? r.msg : 'No se pudo reenviar el código.');
            }
          };
          xhr.onerror = function () {
            btnReen.disabled = false;
            btnReen.innerHTML = htmlOrig;
            mostrarAlerta('error', 'Error de conexión. Intenta de nuevo.');
          };
          xhr.send('action=reenviar_codigo&email=' + encodeURIComponent(email));
        });
      }

      /* ---------- Enviar ---------- */
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (btn.disabled || enviando) return;

        limpiarAlerta();
        var codigo = codigoActual();

        if (codigo.length !== 6) {
          marcarCodigo('error');
          mostrarAlerta('error', 'Escribe los 6 dígitos del código.');
          return;
        }

        enviando = true;
        btn.disabled = true;
        var htmlOriginal = btn.innerHTML;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Guardando...';

        var xhr = new XMLHttpRequest();
        xhr.open('POST', '../../controllers/musuarios_controller', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        xhr.onreadystatechange = function () {
          if (xhr.readyState !== 4) return;

          enviando = false;
          btn.innerHTML = htmlOriginal;

          var r = null;
          try { r = JSON.parse(xhr.responseText); } catch (err) { r = null; }

          if (r && r.ok) {
            mostrarAlerta('ok', r.msg + ' Redirigiendo al inicio de sesión...');
            form.reset();
            digits.forEach(function (d) { d.value = ''; d.classList.remove('lleno', 'error'); });
            match.classList.remove('visible', 'ok', 'error');
            ['req-length', 'req-capital', 'req-lower', 'req-number', 'req-special', 'req-allowed'].forEach(function (id) {
              var el = document.getElementById(id);
              if (el) el.classList.remove('valid');
            });
            btn.disabled = true;
            setTimeout(function () {
              window.location.href = '../login?stat=reset_ok';
            }, 2200);
            return;
          }

          marcarCodigo('error');
          mostrarAlerta('error', (r && r.msg) ? r.msg : 'No se pudo guardar la contraseña. Intenta nuevamente.');
          digits[0].focus();
          actualizarBoton();
        };

        xhr.onerror = function () {
          enviando = false;
          btn.innerHTML = htmlOriginal;
          mostrarAlerta('error', 'Error de conexión. Revisa tu internet e intenta de nuevo.');
          actualizarBoton();
        };

        xhr.send(
          'action=aplicar_reset'
          + '&email=' + encodeURIComponent(emailEl.value.trim())
          + '&codigo=' + encodeURIComponent(codigo)
          + '&password1=' + encodeURIComponent(p1.value)
          + '&password2=' + encodeURIComponent(p2.value)
        );
      });

      /* ---------- Arranque ---------- */
      refrescarCasillas();
      setTimeout(function () {
        if (emailEl.value.trim() === '') emailEl.focus();
        else digits[0].focus();
      }, 350);
    })();
  </script>
</body>

</html>
