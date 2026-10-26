<?php
/**
 * =====================================================================
 * views/demo/index.php — ACCESO DEMOSTRATIVO DE SOVCA
 * ---------------------------------------------------------------------
 * Pantalla pública (sin sesión) donde un visitante crea su propia
 * cuenta de prueba:
 *
 *   · la cuenta vive en la base de datos sovca_demo (solo catálogos),
 *   · tiene una vigencia de 15 días,
 *   · al crearla entra directo al sistema con esa cuenta.
 *
 * Todo el trabajo de base de datos está en lib/demo_helper.php y
 * controllers/demo_controller.php.
 * =====================================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/demo.php';
require_once __DIR__ . '/../../lib/demo_helper.php';
require_once __DIR__ . '/../../lib/captcha_helper.php';
require_once __DIR__ . '/../../lib/mantenimiento_helper.php';

/* MODO MANTENIMIENTO: la creación de cuentas demo se suspende mientras
   el sistema está cerrado (el controlador también lo rechaza). */
$mttoActivo  = bovs_mantenimiento_activo();
$mttoMensaje = $mttoActivo ? bovs_mantenimiento_mensaje() : '';

$demoActivo  = bovs_demo_activo();
$demoListo   = $demoActivo && !$mttoActivo && bovs_demo_bd_disponible();
$diasDemo    = (int) SOVCA_DEMO_DIAS;

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
  <title>Demostración · SOVCA</title>
  <meta content="width=device-width, initial-scale=1, maximum-scale=5, user-scalable=yes" name="viewport">
  <meta name="robots" content="noindex, nofollow">

  <link href="../../bootstrap/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
  <link rel="stylesheet" href="../../css/fontawesome6/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap" rel="stylesheet">

  <style>
    *,
    *::before,
    *::after { box-sizing: border-box; }

    html, body.demo-page {
      /* Paleta SOVCA: crema + marrón + naranja oscuro (cría ovina y caprina) */
      background-color: #F6F0E7 !important;
      background-image: radial-gradient(circle at 50% 50%, #FDF8F1 0%, #E7DAC8 100%) !important;
      min-height: 100vh;
      margin: 0;
      padding: 0;
      font-family: 'Poppins', 'Source Sans Pro', 'Helvetica Neue', Helvetica, Arial, sans-serif;
      overflow-x: hidden;
      position: relative;
    }

    body.demo-page::before,
    body.demo-page::after {
      content: "";
      position: absolute;
      border-radius: 50%;
      pointer-events: none;
      z-index: 1;
    }

    body.demo-page::before {
      width: 520px; height: 520px;
      top: -180px; right: -160px;
      background: radial-gradient(circle, rgba(91, 58, 30, .10) 0%, rgba(91, 58, 30, 0) 70%);
    }

    body.demo-page::after {
      width: 620px; height: 620px;
      bottom: -260px; left: -220px;
      background: radial-gradient(circle, rgba(194, 65, 12, .10) 0%, rgba(194, 65, 12, 0) 70%);
    }

    .demo-container {
      display: flex;
      align-items: flex-start;
      justify-content: center;
      gap: 26px;
      min-height: 100vh;
      width: 100%;
      padding: 34px 18px 40px;
      position: relative;
      z-index: 3;
      flex-wrap: wrap;
    }

    /* ---------- Columna informativa ---------- */
    .demo-info {
      width: 330px;
      max-width: 100%;
      padding: 26px 24px;
      /* Marrón "maroon" suave: el logo verde de SOVCA resalta sobre él
         mucho mejor que sobre el marrón casi negro anterior. */
      background: linear-gradient(160deg, #a8551d 0%, #bd5219 100%);
      border-radius: 18px;
      color: #FDF6EE;
      box-shadow: 0 18px 44px rgba(90, 58, 38, .26);
    }

    .demo-info__logo {
      display: block;
      margin: 0 auto 16px;
      max-width: 220px;
      width: 100%;
      height: auto;
      /* Placa blanca: aísla el verde del logo del fondo marrón y lo
         hace protagonista. */
      background: #ffffff;
      padding: 10px 18px;
      border-radius: 14px;
      box-shadow: 0 6px 18px rgba(59, 36, 18, .22);
    }

    .demo-info__badge {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      background: rgba(255, 255, 255, .20);
      border: 1px solid rgba(255, 255, 255, .30);
      border-radius: 999px;
      padding: 5px 12px;
      font-size: 10.5px;
      font-weight: 800;
      letter-spacing: .8px;
      text-transform: uppercase;
      margin-bottom: 14px;
    }

    .demo-info h1 {
      font-size: 21px;
      font-weight: 800;
      line-height: 1.25;
      margin: 0 0 10px;
      color: #ffffff;
    }

    .demo-info p {
      font-size: 12.5px;
      line-height: 1.6;
      color: #F6E7D8;
      margin: 0 0 16px;
    }

    .demo-info ul {
      list-style: none;
      margin: 0 0 18px;
      padding: 0;
    }

    .demo-info li {
      display: flex;
      align-items: flex-start;
      gap: 9px;
      font-size: 12px;
      line-height: 1.5;
      color: #FBF1E7;
      margin-bottom: 10px;
    }

    .demo-info li i {
      color: #FFD08A;
      font-size: 12px;
      margin-top: 3px;
      flex-shrink: 0;
    }

    .demo-info__nota {
      font-size: 11px;
      line-height: 1.55;
      color: #EEDCC9;
      border-top: 1px solid rgba(255, 255, 255, .25);
      padding-top: 12px;
      margin: 0;
    }

    .demo-info__volver {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      margin-top: 16px;
      font-size: 12px;
      font-weight: 700;
      color: #ffffff;
      text-decoration: none;
      background: rgba(255, 255, 255, .1);
      border-radius: 9px;
      padding: 8px 14px;
      transition: background .2s;
    }

    .demo-info__volver:hover,
    .demo-info__volver:focus {
      background: rgba(255, 255, 255, .2);
      color: #ffffff;
      text-decoration: none;
    }

    /* ---------- Columna del formulario ---------- */
    .demo-card {
      width: 480px;
      max-width: 100%;
      border: 5px solid transparent;
      border-radius: 20px;
      background:
        linear-gradient(#ffffff, #ffffff) padding-box,
        conic-gradient(from 0deg at 50% 50%,
          #1F5B33 0deg, #8A5A38 45deg, #C2610C 90deg, #8A5A38 135deg,
          #1F5B33 180deg, #8A5A38 225deg, #C2610C 270deg, #8A5A38 315deg, #1F5B33 360deg) border-box;
      box-shadow:
        0 0 0 6px rgba(255, 255, 255, .8),
        0 0 0 8px rgba(31, 91, 51, .12),
        0 24px 60px rgba(59, 36, 18, .16);
      padding: 28px 30px 24px;
      position: relative;
    }

    .demo-card__title {
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 15px;
      font-weight: 800;
      color: #1F5B33;
      letter-spacing: .3px;
      margin: 0 0 4px;
    }

    .demo-card__title i { color: #C2610C; }

    .demo-card__sub {
      font-size: 11.5px;
      color: #64748b;
      font-weight: 600;
      margin: 0 0 18px;
    }

    .form-group { margin-bottom: 13px; }

    .form-group > label {
      display: block;
      font-size: 10.5px;
      font-weight: 800;
      letter-spacing: .7px;
      text-transform: uppercase;
      color: #5B3A1E;
      margin-bottom: 5px;
    }

    .form-group label .opcional {
      font-weight: 600;
      text-transform: none;
      letter-spacing: 0;
      color: #94a3b8;
      font-size: 10px;
    }

    .input-wrap { position: relative; }

    .input-wrap .form-control {
      height: 42px;
      border-radius: 9px;
      border: 1px solid #E2D3C0;
      background: #FCF8F2;
      font-family: inherit;
      font-size: 12.5px;
      font-weight: 600;
      color: #2C1D10;
      box-shadow: none;
      transition: border-color .2s, box-shadow .2s, background .2s;
    }

    .input-wrap .form-control:focus {
      border-color: #8A4B12;
      box-shadow: 0 0 0 3px rgba(138, 75, 18, .16);
      background: #ffffff;
    }

    .input-wrap .form-control::placeholder {
      color: #94a3b8;
      font-weight: 500;
    }

    .input-wrap .form-control.is-invalid {
      border-color: #dc2626;
      box-shadow: 0 0 0 3px rgba(220, 38, 38, .12);
    }

    .input-wrap .input-icon {
      position: absolute;
      right: 13px;
      top: 50%;
      transform: translateY(-50%);
      color: #94a3b8;
      font-size: 13px;
      pointer-events: none;
    }

    .campo-doble {
      display: flex;
      gap: 10px;
    }

    .campo-doble > .form-group { flex: 1; min-width: 0; }

    .ayuda {
      font-size: 10.5px;
      color: #94a3b8;
      font-weight: 600;
      margin-top: 4px;
    }

    /* ---------- Captcha ---------- */
    .captcha-row { display: flex; align-items: stretch; gap: 10px; }

    .captcha-pregunta {
      flex-shrink: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 0 14px;
      background: linear-gradient(135deg, #FBF3E7 0%, #F0E1CB 100%);
      border: 1px solid #DFC8A6;
      border-radius: 9px;
      color: #3B2412;
      font-size: 16px;
      font-weight: 800;
      min-width: 118px;
      height: 42px;
      user-select: none;
    }

    .captcha-pregunta .op { color: #c0392b; font-size: 18px; }

    .captcha-row .input-wrap { flex: 1; }

    .label-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      margin-bottom: 5px;
    }

    .label-row label { margin-bottom: 0; }

    .cap-refresh {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 3px 8px;
      border: none;
      border-radius: 8px;
      background: transparent;
      color: #64748b;
      font-family: inherit;
      font-size: 10px;
      font-weight: 800;
      letter-spacing: .6px;
      line-height: 1;
      text-transform: uppercase;
      cursor: pointer;
      transition: background .2s, color .2s;
    }

    .cap-refresh:hover { background: #EDF3EA; color: #2F6B3F; }
    .cap-refresh.girando i { display: inline-block; animation: girar .6s linear infinite; }
    @keyframes girar { from { transform: rotate(0); } to { transform: rotate(360deg); } }

    /* ---------- Aceptación ---------- */
    .acepto {
      display: flex;
      align-items: flex-start;
      gap: 9px;
      font-size: 11px;
      line-height: 1.5;
      color: #5A4632;
      font-weight: 600;
      margin: 4px 0 16px;
      cursor: pointer;
    }

    .acepto input { margin-top: 2px; flex-shrink: 0; }

    /* ---------- Botón ---------- */
    .btn-demo {
      width: 100%;
      height: 48px;
      border-radius: 10px;
      /* Verde de la marca → marrón cuero */
      background: linear-gradient(135deg, #2F7A46 0%, #8A5A38 100%);
      border: none;
      color: #fff;
      font-family: inherit;
      font-size: 13px;
      font-weight: 800;
      letter-spacing: 1.2px;
      text-transform: uppercase;
      cursor: pointer;
      transition: transform .15s, box-shadow .25s, background .25s;
      box-shadow: 0 6px 18px rgba(47, 107, 63, .32);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
    }

    .btn-demo:hover { transform: translateY(-2px); background: linear-gradient(135deg, #3A8C52 0%, #A4632A 100%); box-shadow: 0 10px 26px rgba(47, 107, 63, .45); }
    .btn-demo:active { transform: translateY(0); }
    .btn-demo:disabled { opacity: .7; cursor: wait; transform: none; }

    /* ---------- Mensajes ---------- */
    .demo-msg {
      display: none;
      align-items: flex-start;
      gap: 9px;
      border-radius: 10px;
      font-size: 12px;
      font-weight: 600;
      padding: 11px 13px;
      margin-bottom: 14px;
      line-height: 1.45;
    }

    .demo-msg.visible { display: flex; }
    .demo-msg--error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
    .demo-msg--ok { background: #FDF2E2; color: #8A4B12; border: 1px solid #E9C89B; }
    .demo-msg--info { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }
    .demo-msg i { margin-top: 2px; }

    .validation-msg {
      display: none;
      background: #fef2f2;
      color: #991b1b;
      border: 1px solid #fecaca;
      font-size: 10.5px;
      font-weight: 700;
      border-radius: 7px;
      margin-top: 5px;
      padding: 6px 9px;
    }

    .validation-msg.visible { display: block; }

    .demo-footer {
      margin-top: 16px;
      padding-top: 13px;
      border-top: 1px solid #E7DBCD;
      text-align: center;
      font-size: 10px;
      font-weight: 600;
      color: #94a3b8;
      letter-spacing: .4px;
    }

    .demo-footer .version { color: #C2610C; font-weight: 800; }

    /* ============================================================
       PASO 2 · CÓDIGO DE VERIFICACIÓN
       ============================================================ */
    .demo-step[hidden] { display: none; }

    .demo-step__icon {
      width: 54px;
      height: 54px;
      margin: -4px auto 14px;
      border-radius: 16px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, #EDF4EC 0%, #D8EAD6 100%);
      border: 1px solid #A9CDA9;
      color: #2F6B3F;
      font-size: 22px;
    }

    .codigo-caja {
      display: flex;
      gap: 8px;
      justify-content: center;
      margin: 6px 0 10px;
    }

    .codigo-digit {
      flex: 1 1 0;
      min-width: 0;
      max-width: 46px;
      height: 54px;
      text-align: center;
      font-family: 'Poppins', sans-serif;
      font-size: 20px;
      font-weight: 800;
      color: #3B2412;
      background: #FCF8F2;
      border: 1px solid #E2D3C0;
      border-radius: 10px;
      box-shadow: none;
      transition: border-color .2s, box-shadow .2s, background .2s;
    }

    .codigo-digit:focus {
      outline: none;
      border-color: #2F6B3F;
      background: #ffffff;
      box-shadow: 0 0 0 3px rgba(47, 107, 63, .16);
    }

    .codigo-digit.lleno {
      border-color: #C2610C;
      background: #FDF6EC;
    }

    .codigo-digit.is-invalid {
      border-color: #dc2626;
      box-shadow: 0 0 0 3px rgba(220, 38, 38, .12);
    }

    .codigo-pie {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      font-size: 11.5px;
      font-weight: 700;
      color: #64748b;
      margin-bottom: 16px;
    }

    .codigo-pie .tiempo {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-variant-numeric: tabular-nums;
    }

    .codigo-pie .tiempo.urgente { color: #b45309; }

    .btn-reenviar {
      border: none;
      background: transparent;
      padding: 4px 8px;
      border-radius: 7px;
      font-family: inherit;
      font-size: 11px;
      font-weight: 800;
      letter-spacing: .4px;
      text-transform: uppercase;
      color: #9A5216;
      cursor: pointer;
      transition: background .2s, color .2s;
    }

    .btn-reenviar:hover:not(:disabled) { background: #F6EEE4; color: #7C3A0F; }
    .btn-reenviar:disabled { color: #b6c2bb; cursor: not-allowed; }

    .demo-link {
      display: block;
      width: 100%;
      margin-top: 12px;
      padding: 6px;
      border: none;
      background: transparent;
      font-family: inherit;
      font-size: 11.5px;
      font-weight: 700;
      color: #64748b;
      cursor: pointer;
      transition: color .2s;
    }

    .demo-link:hover { color: #C2610C; text-decoration: underline; }

    @media (max-width: 480px) {
      .codigo-digit { height: 50px; max-width: 44px; font-size: 18px; }
      .codigo-caja { gap: 6px; }
    }

    @media (max-width: 900px) {
      .demo-container { padding-top: 22px; }
      .demo-info { width: 480px; }
      .demo-card { width: 480px; }
    }

    @media (max-width: 480px) {
      .demo-card { padding: 22px 18px 18px; border-radius: 16px; }
      .demo-info { padding: 20px 18px; }
      .campo-doble { flex-direction: column; gap: 0; }
    }
  </style>
</head>

<body class="demo-page">

  <div class="demo-container">

    <!-- ============================================================
         INFORMACIÓN
         ============================================================ -->
    <aside class="demo-info">
      <img src="../../dist/img/sovca.png" alt="SOVCA" class="demo-info__logo">

      <span class="demo-info__badge">
        <i class="fa fa-flask"></i>
        Entorno de prueba
      </span>

      <h1>Crea tu cuenta y prueba SOVCA</h1>

      <p>
        Un espacio de trabajo real, con la misma estructura del sistema, pero
        separado de la información de los productores.
      </p>

      <ul>
        <li><i class="fa fa-envelope-circle-check"></i> Verificamos tu correo antes de crear la cuenta: cada demo es de una persona real.</li>
        <li><i class="fa fa-database"></i> Base de datos independiente: nada de lo que hagas afecta los datos de producción.</li>
        <li><i class="fa fa-list-check"></i> Catálogos básicos ya cargados: razas, categorías, causas, estatus y geografía.</li>
        <li><i class="fa fa-calendar-day"></i> La cuenta dura <?= $diasDemo ?> días desde que confirmas el código.</li>
        <li><i class="fa fa-user-tie"></i> Entras como productor, con tu propio fundo de prueba e independiente del de los demás.</li>
      </ul>

      <p class="demo-info__nota">
        Puedes cargar información real de tu ganadería: al estar el correo
        verificado, esos datos sirven para evaluar el sistema y no se mezclan
        con los de otros usuarios demo. Puedes pedir una cuenta definitiva
        cuando quieras conservarlos.
      </p>

      <a href="../login" class="demo-info__volver">
        <i class="fa fa-arrow-left"></i>
        Volver al inicio de sesión
      </a>
    </aside>

    <!-- ============================================================
         FORMULARIO
         ============================================================ -->
    <section class="demo-card">

      <h2 class="demo-card__title">
        <i class="fa fa-user-plus"></i>
        Crear cuenta de demostración
      </h2>
      <p class="demo-card__sub">
        Completa tus datos: te enviaremos un código para verificar el correo.
      </p>

      <?php if ($mttoActivo): ?>
        <div class="demo-msg demo-msg--error visible">
          <i class="fa fa-wrench"></i>
          <div>
            <strong>Modo mantenimiento activo.</strong><br>
            <?= htmlspecialchars($mttoMensaje, ENT_QUOTES, 'UTF-8') ?><br>
            La creación de cuentas de demostración está <strong>suspendida</strong>
            hasta que termine el mantenimiento.
          </div>
        </div>
      <?php elseif (!$demoActivo): ?>
        <div class="demo-msg demo-msg--error visible">
          <i class="fa fa-ban"></i>
          <div>El acceso demostrativo está <strong>deshabilitado</strong> en este servidor.</div>
        </div>
      <?php elseif (!$demoListo): ?>
        <div class="demo-msg demo-msg--error visible">
          <i class="fa fa-triangle-exclamation"></i>
          <div>
            La base de datos de pruebas <strong>todavía no está instalada</strong>.<br>
            Un administrador debe ejecutar <code>php scripts/demo_instalar.php</code>.
          </div>
        </div>
      <?php endif; ?>

      <div class="demo-msg" id="demo-msg"></div>

      <form id="demoForm" autocomplete="off" novalidate>
        <input type="hidden" name="action" value="crear_cuenta">
        <input type="hidden" name="captcha_token" value="<?= htmlspecialchars($cap_token, ENT_QUOTES, 'UTF-8') ?>">

        <div class="form-group">
          <label for="nombre">Nombre y apellido</label>
          <div class="input-wrap">
            <input id="nombre" name="nombre" type="text" class="form-control" maxlength="120"
              placeholder="Ej.: Ana Pérez" autocomplete="name">
            <i class="fa fa-user input-icon"></i>
          </div>
          <div class="validation-msg" id="ms_nombre"></div>
        </div>

        <div class="form-group">
          <label for="email">Correo electrónico</label>
          <div class="input-wrap">
            <input id="email" name="email" type="email" class="form-control" maxlength="150"
              placeholder="tucorreo@ejemplo.com" autocomplete="email">
            <i class="fa fa-envelope input-icon"></i>
          </div>
          <div class="validation-msg" id="ms_email"></div>
        </div>

        <div class="campo-doble">
          <div class="form-group">
            <label for="usuario">Usuario</label>
            <div class="input-wrap">
              <input id="usuario" name="usuario" type="text" class="form-control" maxlength="30"
                placeholder="DEMOANA" autocorrect="off" spellcheck="false" autocomplete="username">
              <i class="fa fa-at input-icon"></i>
            </div>
            <div class="validation-msg" id="ms_usuario"></div>
          </div>

          <div class="form-group">
            <label for="fundo">Fundo <span class="opcional">(opcional)</span></label>
            <div class="input-wrap">
              <input id="fundo" name="fundo" type="text" class="form-control" maxlength="120"
                placeholder="Fundo de prueba">
              <i class="fa fa-house input-icon"></i>
            </div>
            <div class="validation-msg" id="ms_fundo"></div>
          </div>
        </div>

        <div class="campo-doble">
          <div class="form-group">
            <label for="clave">Contraseña</label>
            <div class="input-wrap">
              <input id="clave" name="clave" type="password" class="form-control" maxlength="60"
                placeholder="Mínimo 8 caracteres" autocomplete="new-password">
              <i class="fa fa-lock input-icon"></i>
            </div>
            <div class="validation-msg" id="ms_clave"></div>
          </div>

          <div class="form-group">
            <label for="clave2">Repetir contraseña</label>
            <div class="input-wrap">
              <input id="clave2" name="clave2" type="password" class="form-control" maxlength="60"
                placeholder="Repite la contraseña" autocomplete="new-password">
              <i class="fa fa-lock input-icon"></i>
            </div>
            <div class="validation-msg" id="ms_clave2"></div>
          </div>
        </div>

        <div class="ayuda" style="margin:-6px 0 12px;">
          Debe tener 8+ caracteres, una mayúscula, una minúscula, un número y uno de estos signos: @ # $ %
        </div>

        <div class="form-group">
          <div class="label-row">
            <label for="captcha_respuesta">Verificación de seguridad</label>
            <button type="button" id="cap-refresh" class="cap-refresh" title="Generar otra verificación">
              <i class="fa fa-rotate-right" aria-hidden="true"></i>
              <span>Cambiar</span>
            </button>
          </div>
          <div class="captcha-row">
            <div class="captcha-pregunta" id="captcha-caja">
              <span id="cap-a"><?= $cap_a ?></span>
              <span class="op" id="cap-op"><?= $cap_signo ?></span>
              <span id="cap-b"><?= $cap_b ?></span>
              <span style="color:#64748b;font-weight:600;font-size:13px">=</span>
            </div>
            <div class="input-wrap">
              <input id="captcha_respuesta" name="captcha_respuesta" type="text" class="form-control"
                placeholder="Resultado" inputmode="numeric" maxlength="3" autocomplete="off">
            </div>
          </div>
          <div class="validation-msg" id="ms_captcha"></div>
        </div>

        <label class="acepto" for="acepto">
          <input type="checkbox" id="acepto" name="acepto" value="1">
          <span>
            Entiendo que es un entorno de prueba, que la cuenta vence a los
            <?= $diasDemo ?> días y que el correo se verifica antes de crearla.
          </span>
        </label>

        <button type="submit" id="btn-crear" class="btn-demo" <?= (!$demoListo || $mttoActivo) ? 'disabled' : '' ?>>
          <i class="fa fa-paper-plane"></i>
          Continuar · verificar correo
        </button>
      </form>

      <!-- ============================================================
           PASO 2 · CÓDIGO ENVIADO AL CORREO
           La cuenta se crea SOLO cuando el código es correcto, así cada
           demo es de una persona identificable (datos útiles, no basura).
           ============================================================ -->
      <div class="demo-step" id="paso-codigo" hidden>
        <div class="demo-step__icon">
          <i class="fa fa-envelope-circle-check"></i>
        </div>

        <h2 class="demo-card__title" style="justify-content:center;">
          Verifica tu correo
        </h2>
        <p class="demo-card__sub" style="text-align:center;">
          Enviamos un código de 6 dígitos a
          <strong id="cod-email"></strong>.<br>
          La cuenta se crea al confirmarlo.
        </p>

        <div class="demo-msg" id="cod-msg"></div>

        <form id="codigoForm" autocomplete="off" novalidate>
          <div class="codigo-caja" id="codigo-caja">
            <input class="codigo-digit" type="text" inputmode="numeric" maxlength="1" data-digito="1" aria-label="Dígito 1">
            <input class="codigo-digit" type="text" inputmode="numeric" maxlength="1" data-digito="2" aria-label="Dígito 2">
            <input class="codigo-digit" type="text" inputmode="numeric" maxlength="1" data-digito="3" aria-label="Dígito 3">
            <input class="codigo-digit" type="text" inputmode="numeric" maxlength="1" data-digito="4" aria-label="Dígito 4">
            <input class="codigo-digit" type="text" inputmode="numeric" maxlength="1" data-digito="5" aria-label="Dígito 5">
            <input class="codigo-digit" type="text" inputmode="numeric" maxlength="1" data-digito="6" aria-label="Dígito 6">
          </div>

          <div class="codigo-pie">
            <span class="tiempo" id="cod-tiempo">
              <i class="fa fa-clock"></i>
              <span id="cod-timer">15:00</span>
            </span>
            <button type="button" class="btn-reenviar" id="cod-reenviar" disabled>
              Reenviar código
            </button>
          </div>

          <button type="submit" id="btn-verificar" class="btn-demo">
            <i class="fa fa-circle-check"></i>
            Verificar y entrar
          </button>
        </form>

        <button type="button" class="demo-link" id="volver-datos">
          Cambiar mis datos
        </button>
      </div>

      <div class="demo-footer">
        <span class="version">SOVCA</span> · © <?= date('Y') ?> Global-SOVCA · demostración
      </div>
    </section>
  </div>

  <script src="../../plugins/jQuery/jQuery-2.1.4.min.js" type="text/javascript"></script>
  <script>
    (function () {
      'use strict';

      var FORM = document.getElementById('demoForm');
      var MSG = document.getElementById('demo-msg');
      var BTN = document.getElementById('btn-crear');
      var CAP_VIGENCIA = 600; // segundos
      var capGenerado = Date.now();

      function porId(id) { return document.getElementById(id); }

      function mostrarError(id, texto) {
        var el = porId(id);
        if (!el) { return; }
        el.textContent = texto;
        el.classList.add('visible');
        var campo = porId(id.replace('ms_', ''));
        if (campo && campo.classList) { campo.classList.add('is-invalid'); }
      }

      function limpiarError(id) {
        var el = porId(id);
        if (!el) { return; }
        el.textContent = '';
        el.classList.remove('visible');
        var campo = porId(id.replace('ms_', ''));
        if (campo && campo.classList) { campo.classList.remove('is-invalid'); }
      }

      function mensaje(tipo, html) {
        MSG.className = 'demo-msg demo-msg--' + tipo + ' visible';
        MSG.innerHTML = (tipo === 'ok'
          ? '<i class="fa fa-check-circle"></i>'
          : (tipo === 'info' ? '<i class="fa fa-circle-info"></i>' : '<i class="fa fa-ban"></i>')) +
          '<div>' + html + '</div>';
      }

      function limpiarMensaje() {
        MSG.className = 'demo-msg';
        MSG.innerHTML = '';
      }

      /* ---------------- Captcha ---------------- */
      function pintarCaptcha(d) {
        porId('cap-a').textContent = d.a;
        porId('cap-b').textContent = d.b;
        porId('cap-op').textContent = d.signo;
        var tok = FORM.querySelector('input[name="captcha_token"]');
        if (tok) { tok.value = d.token; }
        capGenerado = Date.now();
        var cap = porId('captcha_respuesta');
        if (cap) { cap.value = ''; }
      }

      function refrescarCaptcha() {
        var btn = porId('cap-refresh');
        btn.classList.add('girando');
        $.ajax({
          url: '../../controllers/mcaptcha_controller',
          type: 'POST',
          dataType: 'json',
          cache: false
        }).done(function (d) {
          if (d && d.ok) { pintarCaptcha(d); }
        }).always(function () {
          btn.classList.remove('girando');
        });
      }

      var btnCap = porId('cap-refresh');
      if (btnCap) { btnCap.addEventListener('click', refrescarCaptcha); }

      /* ---------------- Validación en pantalla ---------------- */
      var CAMPOS = ['nombre', 'email', 'usuario', 'clave', 'clave2', 'fundo', 'captcha_respuesta'];

      CAMPOS.forEach(function (id) {
        var el = porId(id);
        if (!el) { return; }
        el.addEventListener('input', function () { limpiarError('ms_' + (id === 'captcha_respuesta' ? 'captcha' : id)); });
      });

      function validar() {
        var ok = true;

        if (porId('nombre').value.trim().length < 3) {
          mostrarError('ms_nombre', 'Escribe tu nombre completo.');
          ok = false;
        }

        var email = porId('email').value.trim();
        if (!/^[^@\s]+@[^@\s]+\.[a-zA-Z]{2,}$/.test(email)) {
          mostrarError('ms_email', 'El correo no parece válido.');
          ok = false;
        }

        var usuario = porId('usuario').value.trim().toUpperCase();
        if (!/^[A-Z0-9._-]{4,30}$/.test(usuario)) {
          mostrarError('ms_usuario', 'Entre 4 y 30 caracteres: letras, números, punto, guion o guion bajo.');
          ok = false;
        }

        var clave = porId('clave').value;
        var faltas = [];
        if (clave.length < 8) { faltas.push('8 caracteres'); }
        if (!/[A-ZÁÉÍÓÚÑ]/.test(clave)) { faltas.push('una mayúscula'); }
        if (!/[a-záéíóúüñ]/.test(clave)) { faltas.push('una minúscula'); }
        if (!/\d/.test(clave)) { faltas.push('un número'); }
        if (!/[@#$%]/.test(clave)) { faltas.push('un signo @ # $ %'); }
        if (faltas.length) {
          mostrarError('ms_clave', 'Falta: ' + faltas.join(', ') + '.');
          ok = false;
        }

        if (porId('clave2').value !== clave) {
          mostrarError('ms_clave2', 'Las contraseñas no coinciden.');
          ok = false;
        }

        if (porId('captcha_respuesta').value.trim() === '') {
          mostrarError('ms_captcha', 'Resuelve la operación.');
          ok = false;
        }

        if (!porId('acepto').checked) {
          mensaje('error', 'Debes aceptar las condiciones del entorno de demostración.');
          ok = false;
        }

        return ok;
      }

      /* ---------------- Envío ---------------- */
      FORM.addEventListener('submit', function (ev) {
        ev.preventDefault();
        limpiarMensaje();

        if (Date.now() - capGenerado > CAP_VIGENCIA * 1000) {
          refrescarCaptcha();
          mensaje('info', 'La verificación caducó. Resuelve la operación nueva.');
          return;
        }

        if (!validar()) { return; }

        BTN.disabled = true;
        var textoOriginal = BTN.innerHTML;
        BTN.innerHTML = '<i class="fa fa-circle-notch fa-spin"></i> Creando cuenta...';

        $.ajax({
          url: '../../controllers/demo_controller',
          type: 'POST',
          data: $(FORM).serialize(),
          dataType: 'json',
          cache: false
        }).done(function (d) {
          if (d && d.ok) {
            /* La cuenta no existe aún: se envió el código al correo. */
            mostrarPasoCodigo(d);
            BTN.disabled = false;
            BTN.innerHTML = textoOriginal;
            return;
          }

          mensaje('error', (d && d.msg) ? d.msg : 'No se pudo crear la cuenta.');
          if (d && d.captcha) { refrescarCaptcha(); }
          BTN.disabled = false;
          BTN.innerHTML = textoOriginal;
        }).fail(function () {
          mensaje('error', 'No hubo respuesta del servidor. Revisa tu conexión e intenta nuevamente.');
          BTN.disabled = false;
          BTN.innerHTML = textoOriginal;
        });
      });

      /* ============================================================
         PASO 2 · CÓDIGO DE VERIFICACIÓN
         ============================================================ */
      var PANEL      = document.getElementById('paso-codigo');
      var COD_MSG    = document.getElementById('cod-msg');
      var BTN_VERIF  = document.getElementById('btn-verificar');
      var BTN_REENV  = document.getElementById('cod-reenviar');
      var DIGITOS    = Array.prototype.slice.call(document.querySelectorAll('.codigo-digit'));

      var USUARIO_PEND  = '';
      var timerCod      = null;
      var reenvioTimer  = null;

      function mensajeCod(tipo, html) {
        COD_MSG.className = 'demo-msg demo-msg--' + tipo + ' visible';
        COD_MSG.innerHTML = (tipo === 'ok'
          ? '<i class="fa fa-check-circle"></i>'
          : (tipo === 'info' ? '<i class="fa fa-circle-info"></i>' : '<i class="fa fa-ban"></i>')) +
          '<div>' + html + '</div>';
      }

      function limpiarMensajeCod() {
        COD_MSG.className = 'demo-msg';
        COD_MSG.innerHTML = '';
      }

      function valorCodigo() {
        return DIGITOS.map(function (i) { return i.value; }).join('');
      }

      function limpiarDigitos() {
        DIGITOS.forEach(function (i) {
          i.value = '';
          i.classList.remove('lleno', 'is-invalid');
        });
      }

      function iniciarTimer(minutos) {
        var total = Math.max(1, parseInt(minutos, 10) || 15) * 60;
        var caja  = document.getElementById('cod-tiempo');
        var texto = document.getElementById('cod-timer');

        if (timerCod) { clearInterval(timerCod); }

        function pintar() {
          var m = Math.floor(total / 60);
          var s = total % 60;
          texto.textContent = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
          caja.classList.toggle('urgente', total <= 60);
          total = total - 1;
          if (total < 0) {
            clearInterval(timerCod);
            timerCod = null;
            texto.textContent = '00:00';
          }
        }

        pintar();
        timerCod = setInterval(pintar, 1000);
      }

      function bloquearReenvio(segundos) {
        var seg = parseInt(segundos, 10);
        if (isNaN(seg) || seg < 1) { seg = 60; }

        if (reenvioTimer) { clearInterval(reenvioTimer); }
        BTN_REENV.disabled = true;

        function tick() {
          if (seg <= 0) {
            clearInterval(reenvioTimer);
            reenvioTimer = null;
            BTN_REENV.disabled = false;
            BTN_REENV.textContent = 'Reenviar código';
            return;
          }
          BTN_REENV.textContent = 'Reenviar en ' + seg + ' s';
          seg = seg - 1;
        }

        tick();
        reenvioTimer = setInterval(tick, 1000);
      }

      function mostrarPasoCodigo(d) {
        USUARIO_PEND = d.usuario || '';
        document.getElementById('cod-email').textContent = d.email_oculto || d.email || '';

        limpiarDigitos();
        limpiarMensajeCod();

        FORM.style.display = 'none';
        PANEL.hidden = false;

        iniciarTimer(d.minutos || 15);
        bloquearReenvio(<?= (int) SOVCA_DEMO_REENVIO_SEG ?>);
        mensajeCod('info', d.msg || 'Revisa tu correo y escribe el código.');

        if (DIGITOS[0]) { DIGITOS[0].focus(); }
      }

      /* --- Comportamiento de las 6 casillas --- */
      DIGITOS.forEach(function (inp, idx) {
        inp.addEventListener('input', function () {
          var soloNum = inp.value.replace(/\D/g, '');
          inp.value = soloNum.slice(-1);
          inp.classList.toggle('lleno', inp.value !== '');
          inp.classList.remove('is-invalid');
          if (inp.value !== '' && idx < DIGITOS.length - 1) {
            DIGITOS[idx + 1].focus();
          }
        });

        inp.addEventListener('keydown', function (ev) {
          if (ev.key === 'Backspace' && inp.value === '' && idx > 0) {
            DIGITOS[idx - 1].focus();
          }
          if (ev.key === 'ArrowLeft' && idx > 0) { DIGITOS[idx - 1].focus(); }
          if (ev.key === 'ArrowRight' && idx < DIGITOS.length - 1) { DIGITOS[idx + 1].focus(); }
        });

        inp.addEventListener('paste', function (ev) {
          var datos = (ev.clipboardData || window.clipboardData);
          var texto = datos ? datos.getData('text') : '';
          var nums = String(texto).replace(/\D/g, '').slice(0, 6);

          if (nums === '') { return; }
          ev.preventDefault();

          for (var i = 0; i < DIGITOS.length; i++) {
            DIGITOS[i].value = nums[i] || '';
            DIGITOS[i].classList.toggle('lleno', !!nums[i]);
            DIGITOS[i].classList.remove('is-invalid');
          }

          var ultimo = Math.min(nums.length, DIGITOS.length) - 1;
          if (ultimo >= 0 && DIGITOS[ultimo]) { DIGITOS[ultimo].focus(); }
        });
      });

      /* --- Verificar el código --- */
      document.getElementById('codigoForm').addEventListener('submit', function (ev) {
        ev.preventDefault();
        limpiarMensajeCod();

        var codigo = valorCodigo();
        if (codigo.length !== 6) {
          mensajeCod('error', 'Escribe los <strong>6 dígitos</strong> del código.');
          return;
        }

        BTN_VERIF.disabled = true;
        var textoOriginal = BTN_VERIF.innerHTML;
        BTN_VERIF.innerHTML = '<i class="fa fa-circle-notch fa-spin"></i> Verificando...';

        $.ajax({
          url: '../../controllers/demo_controller',
          type: 'POST',
          data: { action: 'verificar_codigo', usuario: USUARIO_PEND, codigo: codigo },
          dataType: 'json',
          cache: false
        }).done(function (d) {
          if (d && d.ok) {
            mensajeCod('ok', '<strong>¡Listo!</strong> Tu cuenta <strong>' + d.usuario + '</strong> está activa hasta el ' + d.caduca + '. Entrando al sistema...');
            window.setTimeout(function () {
              window.location.href = d.redirect;
            }, 900);
            return;
          }

          mensajeCod('error', (d && d.msg) ? d.msg : 'No se pudo verificar el código.');

          limpiarDigitos();
          DIGITOS.forEach(function (i) { i.classList.add('is-invalid'); });
          if (DIGITOS[0]) { DIGITOS[0].focus(); }

          if (d && d.expirado) {
            BTN_REENV.disabled = false;
            BTN_REENV.textContent = 'Reenviar código';
            if (reenvioTimer) { clearInterval(reenvioTimer); reenvioTimer = null; }
          }

          BTN_VERIF.disabled = false;
          BTN_VERIF.innerHTML = textoOriginal;
        }).fail(function () {
          mensajeCod('error', 'No hubo respuesta del servidor. Revisa tu conexión e intenta nuevamente.');
          BTN_VERIF.disabled = false;
          BTN_VERIF.innerHTML = textoOriginal;
        });
      });

      /* --- Reenviar el código --- */
      BTN_REENV.addEventListener('click', function () {
        if (BTN_REENV.disabled) { return; }

        BTN_REENV.disabled = true;
        BTN_REENV.textContent = 'Enviando...';

        $.ajax({
          url: '../../controllers/demo_controller',
          type: 'POST',
          data: { action: 'reenviar_codigo', usuario: USUARIO_PEND },
          dataType: 'json',
          cache: false
        }).done(function (d) {
          if (d && d.ok) {
            limpiarDigitos();
            limpiarMensajeCod();
            mensajeCod('ok', d.msg || 'Enviamos un código nuevo.');
            iniciarTimer(d.minutos || 15);
            bloquearReenvio(<?= (int) SOVCA_DEMO_REENVIO_SEG ?>);
            if (DIGITOS[0]) { DIGITOS[0].focus(); }
            return;
          }

          mensajeCod('error', (d && d.msg) ? d.msg : 'No se pudo reenviar el código.');
          bloquearReenvio(d && d.espera ? d.espera : 60);
        }).fail(function () {
          mensajeCod('error', 'No hubo respuesta del servidor. Intenta nuevamente.');
          BTN_REENV.disabled = false;
          BTN_REENV.textContent = 'Reenviar código';
        });
      });

      /* --- Volver a los datos --- */
      document.getElementById('volver-datos').addEventListener('click', function () {
        PANEL.hidden = true;
        FORM.style.display = '';

        if (timerCod) { clearInterval(timerCod); timerCod = null; }
        if (reenvioTimer) { clearInterval(reenvioTimer); reenvioTimer = null; }

        limpiarDigitos();
        limpiarMensajeCod();
        limpiarMensaje();

        BTN.disabled = false;

        /* El captcha del paso 1 ya se consumió: se pide uno nuevo. */
        refrescarCaptcha();
      });
    })();
  </script>
</body>

</html>
