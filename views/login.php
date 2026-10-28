<?php
/* ✅ Sesión activa antes que nada */
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

include("./layouts/constantes.php");
require_once __DIR__ . '/../lib/captcha_helper.php';
require_once __DIR__ . '/../config/demo.php';
require_once __DIR__ . '/../lib/mantenimiento_helper.php';

/* MODO MANTENIMIENTO: solo los administradores del sistema pueden entrar. */
$mttoActivo  = bovs_mantenimiento_activo();
$mttoMensaje = $mttoActivo ? bovs_mantenimiento_mensaje() : '';

/* ============================================================
   CAPTCHA — suma/resta con token anti-manipulación
   (el generador vive en lib/captcha_helper.php para poder pedir
    uno nuevo por AJAX sin recargar el formulario)
   ============================================================ */
$cap       = bovs_captcha_generar();
$cap_a     = $cap['a'];
$cap_b     = $cap['b'];
$cap_signo = $cap['signo'];
$cap_token = $cap['token'];

/* Captcha independiente para el modal de instalación offline: así no
   invalida el del formulario de acceso (cada uno usa su propio slot). */
$capOff = bovs_captcha_generar('captcha_offline');

$loginUsuarioPrevio = '';
if (isset($_SESSION['login_usuario_temp'])) {
  $loginUsuarioPrevio = (string) $_SESSION['login_usuario_temp'];
  unset($_SESSION['login_usuario_temp']);
}
$autofocusPassword = !empty($loginUsuarioPrevio);
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <title><?php echo $constante["titulo_pag"] ?? 'SOVCA'; ?></title>
  <meta content="width=device-width, initial-scale=1, maximum-scale=5, user-scalable=yes" name="viewport">

  <!-- ============================================================
       INSTALACIÓN COMO APLICACIÓN (PWA)
       Permite que en el móvil el navegador ofrezca "Instalar app"
       y que, desde ese momento, se abra desde un ícono y no desde
       la URL. El manifest se genera en ../manifest.php
       ============================================================ -->
  <link rel="manifest" href="../manifest.php">
  <link rel="icon" type="image/png" sizes="192x192" href="../app_offline/icons/icon-192.png">
  <meta name="theme-color" content="#006532">
  <meta name="application-name" content="SOVCA">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="SOVCA">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <link rel="apple-touch-icon" href="../app_offline/icons/apple-touch-icon.png">

  <link href="../bootstrap/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
  <link href="../dist/css/AdminLTE.min.css" rel="stylesheet" type="text/css" />
  <link href="../plugins/iCheck/square/blue.css" rel="stylesheet" type="text/css" />
  <link rel="stylesheet" href="../css/fontawesome6/css/all.min.css">
  <link rel="stylesheet" href="../css/fontawesome6/css/v4-shims.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap" rel="stylesheet">

  <style>
    /* ============================================================
       RESET / BASE
       ============================================================ */
    *,
    *::before,
    *::after {
      box-sizing: border-box;
    }

    html,
    body.login-page {
      /* Paleta SOVCA: crema + marrón + naranja oscuro (cría ovina y caprina) */
      background-color: #F6F0E7 !important;
      background-image: radial-gradient(circle at 50% 50%, #FDF8F1 0%, #E7DAC8 100%) !important;
      min-height: 100vh !important;
      margin: 0 !important;
      padding: 0 !important;
      font-family: 'Poppins', 'Source Sans Pro', 'Helvetica Neue', Helvetica, Arial, sans-serif !important;
      overflow-x: hidden !important;
      position: relative !important;
    }

    /* Decoración sutil de fondo (círculos).
       position:fixed para que no alargue el área desplazable de la página. */
    body.login-page::before,
    body.login-page::after {
      content: "";
      position: fixed;
      border-radius: 50%;
      pointer-events: none;
      z-index: 1;
    }

    body.login-page::before {
      width: 520px;
      height: 520px;
      top: -180px;
      right: -160px;
      background: radial-gradient(circle, rgba(139, 90, 56, 0.12) 0%, rgba(139, 90, 56, 0) 70%);
    }

    body.login-page::after {
      width: 620px;
      height: 620px;
      bottom: -260px;
      left: -220px;
      background: radial-gradient(circle, rgba(31, 91, 51, 0.10) 0%, rgba(31, 91, 51, 0) 70%);
    }

    /* ============================================================
       LAYOUT: SOLO CARD CENTRAL
       ============================================================ */
    .login-main-container {
      display: flex !important;
      min-height: 100vh !important;
      min-height: 100dvh !important;
      width: 100% !important;
      padding: 24px 16px !important;
      padding-bottom: calc(24px + env(safe-area-inset-bottom)) !important;
      position: relative !important;
      z-index: 3 !important;
    }

    /* margin:auto centra la tarjeta en ambos ejes y, si no cabe,
       colapsa a 0 en lugar de recortar la parte superior (align-items:center
       sí la recorta cuando el contenido es más alto que la ventana). */
    .sector-center {
      width: 440px !important;
      max-width: 100% !important;
      flex-shrink: 0 !important;
      margin: auto !important;
      z-index: 5 !important;
    }

    /* ============================================================
       CARD DE LOGIN
       ============================================================ */
    .login-card {
      /* Marco degradado en los 4 lados: alterna el verde de la marca
         con el marrón y el naranja oscuro de la cría ovina y caprina.
         Se logra con un borde transparente + doble fondo (blanco en
         padding-box y el degradado cónico en border-box), así el marco
         respeta el border-radius sin cortes en las esquinas. */
      border: 4px solid transparent !important;
      border-radius: 20px !important;
      background:
        linear-gradient(#ffffff, #ffffff) padding-box,
        conic-gradient(from 0deg at 50% 50%,
          #1F5B33 0deg,
          #8A5A38 22.5deg,
          #C2610C 45deg,
          #8A5A38 67.5deg,
          #1F5B33 90deg,
          #8A5A38 112.5deg,
          #C2610C 135deg,
          #8A5A38 157.5deg,
          #1F5B33 180deg,
          #8A5A38 202.5deg,
          #C2610C 225deg,
          #8A5A38 247.5deg,
          #1F5B33 270deg,
          #8A5A38 292.5deg,
          #C2610C 315deg,
          #8A5A38 337.5deg,
          #1F5B33 360deg) border-box !important;
      /* Halo suave exterior + sombra de elevación */
      box-shadow:
        0 0 0 5px rgba(255, 255, 255, 0.85),
        0 0 0 7px rgba(31, 91, 51, 0.12),
        0 18px 44px rgba(59, 36, 18, 0.16),
        0 6px 16px rgba(59, 36, 18, 0.08) !important;
      padding: 30px 32px 22px !important;
      position: relative !important;
      overflow: hidden !important;
      animation: cardEnter .6s cubic-bezier(.22, 1, .36, 1);
    }

    @keyframes cardEnter {
      0% {
        opacity: 0;
        transform: translateY(20px) scale(.97);
      }

      100% {
        opacity: 1;
        transform: translateY(0) scale(1);
      }
    }

    .login-card::before {
      content: "";
      position: absolute;
      top: -60px;
      right: -50px;
      width: 160px;
      height: 160px;
      border-radius: 50%;
      background: rgba(91, 58, 30, 0.06);
      pointer-events: none;
    }

    .login-card::after {
      content: "";
      position: absolute;
      bottom: -70px;
      left: -60px;
      width: 180px;
      height: 180px;
      border-radius: 50%;
      background: rgba(91, 58, 30, 0.06);
      pointer-events: none;
    }

    /* ---- Logo principal ---- */
    .login-card-logo {
      text-align: center;
      margin-bottom: 6px;
      position: relative;
      z-index: 2;
    }

    .login-card-logo img {
      max-width: 220px;
      width: 100%;
      height: auto;
      display: block;
      margin: 0 auto 4px;
      user-select: none;
      -webkit-user-drag: none;
    }

    .login-title {
      margin: 0 !important;
      font-weight: 800 !important;
      /* Verde de la marca: enlaza con el logo y con el marco */
      color: #1F5B33 !important;
      font-size: 26px !important;
      letter-spacing: 3px !important;
      text-align: center !important;
      position: relative;
      z-index: 2;
    }

    .sub-title-badge {
      display: inline-block !important;
      margin-top: 4px !important;
      padding: 5px 16px !important;
      background: #EAF2E9 !important;
      color: #2F6B3F !important;
      font-size: 11px !important;
      font-weight: 800 !important;
      border-radius: 20px !important;
      letter-spacing: 1px !important;
      text-transform: uppercase !important;
      border: 1px solid #C3D9C6 !important;
      box-shadow: 0 2px 8px rgba(31, 91, 51, 0.10) !important;
    }

    .login-title-wrap {
      text-align: center;
      margin-bottom: 16px;
      position: relative;
      z-index: 2;
    }

    /* ---- Chip de seguridad ---- */
    .login-chip {
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 16px;
      width: fit-content;
      gap: 7px;
      padding: 4px 12px 4px 10px;
      background: linear-gradient(135deg, #EDF4EC 0%, #D8EAD6 100%);
      border: 1px solid #A9CDA9;
      border-radius: 20px;
      font-size: 10.5px;
      font-weight: 800;
      color: #1F5B33;
      letter-spacing: .6px;
      text-transform: uppercase;
      position: relative;
      z-index: 2;
    }

    .login-chip .live-dot {
      width: 8px;
      height: 8px;
      background: #3E8E52;
      border-radius: 50%;
      animation: livePulse 1.6s infinite;
    }

    @keyframes livePulse {
      0% {
        box-shadow: 0 0 0 0 rgba(62, 142, 82, 0.75);
      }

      70% {
        box-shadow: 0 0 0 10px rgba(62, 142, 82, 0);
      }

      100% {
        box-shadow: 0 0 0 0 rgba(62, 142, 82, 0);
      }
    }

    /* ============================================================
       FORMULARIO
       ============================================================ */
    .form-group {
      margin-bottom: 13px;
      position: relative;
      z-index: 2;
    }

    .form-group--submit {
      margin-top: 18px;
      margin-bottom: 0;
    }

    .form-group label,
    .label-row label {
      display: block;
      font-size: 10.5px;
      font-weight: 800;
      color: #64748b;
      text-transform: uppercase;
      letter-spacing: .8px;
      margin-bottom: 6px;
    }

    /* ============================================================
       ETIQUETAS DESTACADAS (USUARIO / CONTRASEÑA)
       ============================================================ */
    .form-group>label {
      display: inline-flex !important;
      align-items: center;
      gap: 7px;
      font-size: 11.5px !important;
      font-weight: 800 !important;
      color: #1F5B33 !important;
      letter-spacing: 1.15px !important;
      margin-bottom: 8px !important;
      padding-bottom: 5px;
      position: relative;
    }

    .form-group>label::before {
      content: "";
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: linear-gradient(135deg, #C2610C 0%, #E7A23C 100%);
      box-shadow: 0 0 0 3px rgba(194, 97, 12, 0.16);
      flex-shrink: 0;
    }

    .form-group>label::after {
      content: "";
      position: absolute;
      left: 0;
      bottom: 0;
      width: 100%;
      height: 2px;
      border-radius: 2px;
      background: linear-gradient(90deg, rgba(31, 91, 51, 0.55) 0%, rgba(31, 91, 51, 0) 100%);
    }

    .input-wrap {
      position: relative;
    }

    .input-wrap .form-control {
      width: 100%;
      height: 44px;
      border-radius: 10px;
      border: 1px solid #E2D3C0;
      background: #FCF8F2;
      box-shadow: inset 0 1px 2px rgba(59, 36, 18, 0.05);
      font-family: inherit;
      font-size: 14px;
      color: #0f172a;
      padding: 0 44px 0 14px;
      transition: border-color .2s, box-shadow .2s, background .2s;
      outline: none;
      box-shadow: none;
    }

    .input-wrap .form-control:focus {
      border-color: #2F6B3F;
      box-shadow: 0 0 0 3px rgba(47, 107, 63, 0.16);
      background: #ffffff;
    }

    .input-wrap .form-control::placeholder {
      color: #94a3b8;
      font-weight: 500;
    }

    .input-wrap .form-control.is-invalid {
      border-color: #dc2626;
      box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.12);
    }

    .input-wrap .input-icon {
      position: absolute;
      right: 14px;
      top: 50%;
      transform: translateY(-50%);
      color: #94a3b8;
      font-size: 15px;
      pointer-events: none;
      transition: color .2s;
    }

    .input-wrap .form-control:focus~.input-icon {
      color: #2F6B3F;
    }

    .btn-eye {
      position: absolute;
      right: 4px;
      top: 50%;
      transform: translateY(-50%);
      background: transparent;
      border: none;
      padding: 8px 12px;
      color: #64748b;
      font-size: 15px;
      cursor: pointer;
      border-radius: 8px;
      transition: background .2s, color .2s;
      font-family: inherit;
    }

    .btn-eye:hover,
    .btn-eye:focus {
      background: #EDF3EA;
      color: #2F6B3F;
      outline: none;
    }

    .validation-msg {
      display: none;
      background: #fef2f2;
      color: #991b1b;
      border: 1px solid #fecaca;
      font-size: 11.5px;
      font-weight: 700;
      border-radius: 8px;
      margin-top: 6px;
      padding: 7px 10px;
      align-items: center;
      gap: 6px;
    }

    .validation-msg.visible {
      display: flex;
    }

    .validation-msg::before {
      content: "\f06a";
      font-family: "FontAwesome";
      font-size: 12px;
    }

    /* ============================================================
       CAPTCHA
       ============================================================ */
    .captcha-row {
      display: flex;
      align-items: stretch;
      gap: 10px;
    }

    .captcha-pregunta {
      flex-shrink: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 0 16px;
      background: linear-gradient(135deg, #FBF3E7 0%, #F0E1CB 100%);
      border: 1px solid #DFC8A6;
      border-radius: 10px;
      color: #3B2412;
      font-size: 17px;
      font-weight: 800;
      font-variant-numeric: tabular-nums;
      letter-spacing: 1px;
      min-width: 130px;
      height: 44px;
      user-select: none;
      position: relative;
      overflow: hidden;
      transition: opacity .2s;
    }

    .captcha-pregunta::before {
      content: "";
      position: absolute;
      inset: 0;
      background-image:
        radial-gradient(circle at 20% 30%, rgba(91, 58, 30, 0.10) 0%, transparent 40%),
        radial-gradient(circle at 80% 70%, rgba(217, 63, 63, 0.06) 0%, transparent 40%);
      pointer-events: none;
    }

    .captcha-pregunta>* {
      position: relative;
      z-index: 1;
    }

    .captcha-pregunta .op {
      color: #c0392b;
      font-size: 19px;
    }

    /* Botón para pedir otro captcha (no recarga el formulario).
       Va arriba a la DERECHA, junto a la etiqueta: es una acción del
       bloque y no se mezcla con la operación matemática. */
    .label-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      margin-bottom: 6px;
    }

    .label-row label {
      margin-bottom: 0;
    }

    /* ============================================================
       RECUPERACIÓN DE CONTRASEÑA — enlace bajo el campo de clave
       ============================================================ */
    .olvide-wrap {
      display: flex;
      justify-content: flex-end;
      margin: -6px 0 10px;
    }

    .olvide-link {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      /* Acento naranja: el enlace secundario del formulario */
      color: #C2610C;
      font-size: 11.5px;
      font-weight: 700;
      letter-spacing: .2px;
      text-decoration: none;
      padding: 3px 6px;
      border-radius: 6px;
      transition: background .15s ease, color .15s ease;
    }

    .olvide-link i {
      font-size: 11px;
    }

    .olvide-link:hover,
    .olvide-link:focus {
      background: #FDF1E1;
      color: #8A4B12;
      text-decoration: none;
    }

    .cap-refresh {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      margin: 0;
      padding: 3px 8px;
      border: none;
      border-radius: 8px;
      background: transparent;
      color: #64748b;
      font-family: inherit;
      font-size: 10.5px;
      font-weight: 800;
      letter-spacing: .6px;
      line-height: 1;
      text-transform: uppercase;
      cursor: pointer;
      transition: background .2s, color .2s, box-shadow .2s;
    }

    .cap-refresh i {
      font-size: 12px;
    }

    .cap-refresh:hover {
      background: #EDF3EA;
      color: #2F6B3F;
    }

    .cap-refresh:focus-visible {
      background: #EDF3EA;
      color: #2F6B3F;
      outline: none;
      box-shadow: 0 0 0 2px rgba(47, 107, 63, .35);
    }

    .cap-refresh.girando {
      color: #2F6B3F;
    }

    .cap-refresh.girando i {
      display: inline-block;
      animation: cap-giro .6s linear infinite;
    }

    @keyframes cap-giro {
      from { transform: rotate(0deg); }
      to   { transform: rotate(360deg); }
    }

    .captcha-pregunta.cargando {
      opacity: .6;
    }

    .captcha-row .input-wrap {
      flex: 1;
    }

    /* ============================================================
       BOTÓN LOGIN
       ============================================================ */
    .btn-login {
      width: 100%;
      height: 48px;
      border-radius: 10px;
      /* Verde de la marca → marrón cuero: ovino y caprino en un solo gesto */
      background: linear-gradient(135deg, #2F7A46 0%, #8A5A38 100%);
      border: none;
      color: #fff;
      font-family: inherit;
      font-size: 14px;
      font-weight: 800;
      letter-spacing: 1.5px;
      text-transform: uppercase;
      cursor: pointer;
      transition: transform .15s, box-shadow .25s, background .25s;
      box-shadow: 0 6px 18px rgba(47, 107, 63, 0.32);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      position: relative;
      overflow: hidden;
      z-index: 2;
    }

    .btn-login::after {
      content: "";
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.18), transparent);
      transition: left .55s ease;
    }

    .btn-login:hover::after {
      left: 100%;
    }

    .btn-login:hover {
      transform: translateY(-2px);
      background: linear-gradient(135deg, #3A8C52 0%, #A4632A 100%);
      box-shadow: 0 10px 26px rgba(47, 107, 63, 0.45);
      color: #fff;
    }

    .btn-login:active {
      transform: translateY(0);
    }

    .btn-login:disabled {
      opacity: 0.7;
      cursor: wait;
      transform: none;
    }

    .btn-login i {
      font-size: 15px;
    }

    /* ============================================================
       ACCESO DEMOSTRATIVO (base de pruebas, 15 días)
       ------------------------------------------------------------
       Mismo formato de texto que "Modo offline", pero en la paleta
       de ganadería: candado dorado + texto marrón cuero, más grande
       y en negrita para que resalte sin romper la armonía del login.
       ============================================================ */
    .demo-cta {
      margin-top: 12px;
      text-align: center;
      position: relative;
      z-index: 2;
    }

    .btn-demo-login {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 4px 8px;
      border: none;
      border-radius: 8px;
      background: transparent;
      color: #B45309;
      font-family: inherit;
      font-size: 12px;
      font-weight: 800;
      letter-spacing: .7px;
      text-transform: uppercase;
      text-decoration: none;
      transition: color .2s, background .2s;
    }

    .btn-demo-login i {
      font-size: 15px;
      color: #c9971f;
      text-shadow: 0 1px 0 rgba(255, 255, 255, .85);
      transition: color .2s, transform .2s;
    }

    .btn-demo-login:hover,
    .btn-demo-login:focus {
      color: #8A4B12;
      background: rgba(201, 151, 31, .12);
      text-decoration: none;
    }

    .btn-demo-login:hover i,
    .btn-demo-login:focus i {
      color: #e0b53c;
      transform: scale(1.08);
    }

    .demo-cta__hint {
      display: block;
      text-align: center;
      font-size: 10px;
      font-weight: 600;
      color: #C2610C;
      letter-spacing: .3px;
      margin-top: 5px;
    }

    /* ============================================================
       ALERTAS
       ============================================================ */
    .alert-custom {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      border-radius: 10px;
      font-size: 13px;
      font-weight: 600;
      padding: 12px 14px;
      margin-bottom: 16px;
      border: none;
      position: relative;
      z-index: 2;
      line-height: 1.4;
    }

    .alert-custom i {
      font-size: 15px;
      margin-top: 2px;
      flex-shrink: 0;
    }

    /* ============================================================
       BANNER MODO MANTENIMIENTO
       Se muestra siempre que el interruptor está activo (no solo
       después de un intento de acceso), para que el visitante sepa
       de inmediato que la demo y el modo offline están suspendidos.
       ============================================================ */
    .mtto-banner {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      background: #fdf2e9;
      color: #8a3d0a;
      border: 1px solid #f3c99a;
      border-left: 4px solid #b45309;
      border-radius: 10px;
      padding: 12px 14px;
      margin-bottom: 16px;
      font-size: 13px;
      line-height: 1.45;
      position: relative;
      z-index: 2;
    }

    .mtto-banner > i {
      font-size: 16px;
      color: #b45309;
      margin-top: 2px;
      flex-shrink: 0;
    }

    .mtto-banner strong {
      display: block;
      text-transform: uppercase;
      letter-spacing: .4px;
      font-size: 12px;
      margin-bottom: 3px;
    }

    .mtto-banner__hint {
      display: block;
      margin-top: 6px;
      font-size: 11.5px;
      font-weight: 600;
      color: #9a5a22;
    }

    .offline-status--mtto {
      font-size: 11.5px;
      color: #9a5a22;
      font-weight: 600;
    }

    .alert-danger {
      background: #fef2f2;
      color: #991b1b;
      border: 1px solid #fecaca;
    }

    .alert-danger i {
      color: #dc2626;
    }

    .alert-warning {
      background: #fffbeb;
      color: #92400e;
      border: 1px solid #fde68a;
    }

    .alert-warning i {
      color: #d97706;
    }

    .alert-info {
      background: #eff6ff;
      color: #1e40af;
      border: 1px solid #bfdbfe;
    }

    .alert-info i {
      color: #2563eb;
    }

    /* ============================================================
       OFFLINE STATUS
       ============================================================ */
    .offline-status {
      font-size: 11px;
      color: #B45309;
      margin-top: 12px;
      text-align: center;
      position: relative;
      z-index: 2;
    }

    .offline-trigger {
      text-align: center;
      margin-top: 6px;
      position: relative;
      z-index: 2;
    }

    .offline-trigger button {
      color: #C2610C;
      font-size: 13px;
      background: none;
      border: none;
      padding: 0;
      cursor: pointer;
      font-family: inherit;
      font-weight: 600;
    }

    .offline-trigger button:hover {
      text-decoration: underline;
    }

    .offline-trigger button i {
      margin-right: 6px;
    }

    /* ============================================================
       FOOTER + LOGO ESQUINA INFERIOR DERECHA
       ============================================================ */
    .login-footer {
      margin-top: 16px;
      padding-top: 12px;
      border-top: 1px solid #E7DBCD;
      text-align: center;
      font-size: 10.5px;
      font-weight: 600;
      color: #94a3b8;
      letter-spacing: .5px;
      position: relative;
      z-index: 2;
      min-height: 34px;
    }

    .login-footer .version {
      /* Naranja oscuro: remate de la tarjeta */
      color: #C2610C;
      font-weight: 800;
    }

    .footer-corner-logo {
      position: absolute;
      right: 0;
      bottom: 4px;
      height: 30px;
      width: auto;
      object-fit: contain;
      opacity: 0.85;
      transition: opacity .25s ease, transform .25s ease;
      user-select: none;
      -webkit-user-drag: none;
      pointer-events: none;
    }

    /* ============================================================
       RESPONSIVE
       ============================================================ */

    /* Portátiles con pantalla baja (1366x768, 1280x720, etc.):
       el formulario completo cabe sin scroll vertical. */
    @media (max-height: 820px) and (min-width: 481px) {
      .login-main-container {
        padding: 16px 16px !important;
      }

      .login-card {
        padding: 20px 30px 16px !important;
      }

      .login-card-logo img {
        max-width: 158px;
      }

      .login-card-logo {
        margin-bottom: 2px;
      }

      .login-title-wrap {
        margin-bottom: 10px;
      }

      .form-group {
        margin-bottom: 10px;
      }

      .form-group--submit {
        margin-top: 14px;
      }

      .input-wrap .form-control,
      .captcha-pregunta {
        height: 42px;
      }

      .btn-login {
        height: 44px;
      }

      .olvide-wrap {
        margin: -2px 0 8px;
      }

      .demo-cta {
        margin-top: 8px;
      }

      .offline-status {
        margin-top: 8px;
      }

      .login-footer {
        margin-top: 12px;
        padding-top: 10px;
        min-height: 30px;
      }
    }

    /* Portátiles muy bajos: un poco más de compactación */
    @media (max-height: 700px) and (min-width: 481px) {
      .login-card {
        padding: 16px 28px 14px !important;
      }

      .login-card-logo img {
        max-width: 130px;
      }

      .login-footer {
        margin-top: 10px;
        padding-top: 8px;
      }

      .login-chip,
      .demo-cta__hint,
      .offline-status {
        display: none;
      }
    }

    /* Teléfonos: tarjeta a lo ancho, campos más altos y sin zoom
       automático de iOS (fuente de 16px en los inputs). */
    @media (max-width: 480px) {
      .login-main-container {
        padding: 12px 12px !important;
        padding-bottom: calc(12px + env(safe-area-inset-bottom)) !important;
      }

      .login-card {
        padding: 20px 18px 16px !important;
        border-radius: 16px !important;
        border-width: 4px !important;
      }

      .login-card-logo img {
        max-width: 140px !important;
      }

      .login-title-wrap {
        margin-bottom: 12px;
      }

      .sub-title-badge {
        font-size: 10px !important;
        padding: 4px 13px !important;
      }

      .login-chip {
        display: none;
      }

      .form-group {
        margin-bottom: 11px;
      }

      .form-group--submit {
        margin-top: 14px;
      }

      .login-title {
        font-size: 22px !important;
        letter-spacing: 2px !important;
      }

      .input-wrap .form-control {
        font-size: 16px;
        height: 46px;
      }

      .captcha-row {
        gap: 8px;
      }

      .captcha-pregunta {
        min-width: 96px;
        height: 46px;
        font-size: 15px;
        padding: 0 10px;
        gap: 5px;
      }

      .captcha-pregunta .op {
        font-size: 17px;
      }

      .olvide-link {
        font-size: 11px;
      }

      .btn-login {
        height: 48px;
        font-size: 13px;
      }

      .btn-demo-login {
        font-size: 11.5px;
      }

      .demo-cta__hint {
        font-size: 9.5px;
      }

      /* ---- Footer responsivo: logo debajo del texto, centrado ---- */
      .login-footer {
        margin-top: 14px;
        padding-bottom: 38px;
        min-height: auto;
      }

      .footer-corner-logo {
        position: static;
        display: block;
        margin: 10px auto 0;
        height: 26px;
        right: auto;
        bottom: auto;
      }
    }

    /* Teléfonos pequeños (alto ≤ 720px): se recorta lo decorativo */
    @media (max-width: 480px) and (max-height: 720px) {
      .login-card {
        padding: 16px 16px 14px !important;
      }

      .login-card-logo img {
        max-width: 110px !important;
      }

      .login-card-logo,
      .sub-title-badge {
        margin-bottom: 0;
      }

      .login-title-wrap {
        margin-bottom: 10px;
      }

      .form-group {
        margin-bottom: 9px;
      }

      .form-group--submit {
        margin-top: 10px;
      }

      .input-wrap .form-control,
      .captcha-pregunta {
        height: 44px;
      }

      /* En pantallas estrechas, la etiqueta del captcha y "Cambiar"
         deben caber en la misma línea. */
      .label-row label {
        font-size: 9.5px !important;
        letter-spacing: .5px !important;
      }

      .login-footer {
        padding-bottom: 34px;
      }

      .offline-status {
        display: none;
      }

      .demo-cta__hint {
        display: none;
      }
    }

    /* Teléfonos muy pequeños (alto ≤ 600px): solo el formulario */
    @media (max-width: 480px) and (max-height: 600px) {
      .sub-title-badge,
      .login-footer {
        display: none;
      }

      .login-title-wrap {
        margin-bottom: 8px;
      }
    }

    /* Cualquier dispositivo táctil: evita el zoom al enfocar un campo */
    @media (hover: none) and (pointer: coarse) {
      .input-wrap .form-control {
        font-size: 16px;
      }
    }

    /* Móvil en horizontal (alto muy reducido): se oculta lo accesorio
       para que el formulario quede usable sin apenas scroll. */
    @media (max-height: 520px) and (orientation: landscape) {
      .login-main-container {
        padding: 8px 12px !important;
      }

      .login-card {
        padding: 10px 24px 8px !important;
      }

      .login-card-logo img {
        max-width: 100px !important;
      }

      .login-title-wrap,
      .demo-cta {
        display: none;
      }

      .form-group {
        margin-bottom: 7px;
      }

      .form-group--submit {
        margin-top: 8px;
      }

      .input-wrap .form-control,
      .captcha-pregunta {
        height: 40px;
      }

      .login-chip,
      .demo-cta__hint,
      .offline-status,
      .offline-trigger,
      .login-footer {
        display: none;
      }

      .btn-login {
        height: 42px;
      }
    }

    @media (prefers-reduced-motion: reduce) {
      .login-card {
        animation: none;
      }

      .btn-login::after {
        display: none;
      }
    }
  </style>
</head>

<body class="login-page">
  <div class="login-main-container">

    <!-- ============================================================
         CARD CENTRAL (ÚNICO)
         ============================================================ -->
    <div class="sector-center">
      <div class="login-card">

        <!-- Logo principal -->
        <div class="login-card-logo">
          <a href="">
            <img src="../dist/img/sovca.png" alt="SOVCA">
          </a>
        </div>

        <div class="login-title-wrap">
          <span class="sub-title-badge">Gestión Ovina y Caprina</span>
        </div>

        <div class="login-chip">
          <span class="live-dot"></span>
          Acceso Seguro
        </div>

        <?php if ($mttoActivo): ?>
          <div class="mtto-banner" role="status">
            <i class="fa fa-wrench"></i>
            <div>
              <strong>Modo mantenimiento activo</strong>
              <?= htmlspecialchars($mttoMensaje, ENT_QUOTES, 'UTF-8') ?>
              <span class="mtto-banner__hint">
                Solo los administradores pueden iniciar sesión. El acceso demostrativo
                y el modo offline están deshabilitados hasta que termine el mantenimiento.
              </span>
            </div>
          </div>
        <?php endif; ?>

        <?php if (@$_GET['stat'] == 'failed'): ?>
          <div class="alert alert-danger alert-custom">
            <i class="fa fa-ban"></i>
            <div>
              <strong>Usuario o Contraseña incorrecta.</strong><br>
              Verifica tus credenciales e intenta nuevamente.
            </div>
          </div>
        <?php elseif (@$_GET['stat'] == 'null'): ?>
          <div class="alert alert-danger alert-custom">
            <i class="fa fa-ban"></i>
            <div>
              <strong>Campos vacíos.</strong><br>
              Introduce tu usuario, contraseña y resuelve el captcha.
            </div>
          </div>
        <?php elseif (@$_GET['stat'] == 'captcha'): ?>
          <div class="alert alert-danger alert-custom">
            <i class="fa fa-ban"></i>
            <div>
              <strong>Captcha inválido.</strong><br>
              El resultado del captcha no coincide. Intenta nuevamente.
            </div>
          </div>
        <?php elseif (@$_GET['stat'] == 'subscription_expired'): ?>
          <div class="alert alert-warning alert-custom">
            <i class="fa fa-warning"></i>
            <div>
              <strong>Suscripción vencida.</strong><br>
              Contacta al administrador para renovarla.
            </div>
          </div>
        <?php elseif (@$_GET['stat'] == 'session_closed'): ?>
          <div class="alert alert-warning alert-custom">
            <i class="fa fa-clock-o"></i>
            <div>
              <strong>Su sesión ha expirado.</strong><br>
              El administrador cerró la sesión de forma remota. Si tenía un cambio
              importante, vuelva a entrar e introdúzcalo de nuevo.
            </div>
          </div>
        <?php elseif (@$_GET['stat'] == 'subscription_refresh'): ?>
          <div class="alert alert-info alert-custom">
            <i class="fa fa-info-circle"></i>
            <div>
              <strong>Verificación de suscripción.</strong><br>
              Inicia sesión nuevamente para actualizar la vigencia.
            </div>
          </div>
        <?php elseif (@$_GET['stat'] == 'sin_productor'): ?>
          <div class="alert alert-warning alert-custom">
            <i class="fa fa-warning"></i>
            <div>
              <strong>Acceso suspendido.</strong><br>
              El productor dueño del fundo donde trabajas está inactivo, y tu cuenta
              hereda su situación. Contacta al administrador del sistema.
            </div>
          </div>
        <?php elseif (@$_GET['stat'] == 'reset_ok'): ?>
          <div class="alert alert-success alert-custom">
            <i class="fa fa-check-circle"></i>
            <div>
              <strong>Contraseña actualizada.</strong><br>
              Ya puedes iniciar sesión con tu nueva contraseña.
            </div>
          </div>
        <?php elseif (@$_GET['stat'] == 'mantenimiento' && !$mttoActivo): ?>
          <div class="alert alert-warning alert-custom">
            <i class="fa fa-wrench"></i>
            <div>
              <strong>El acceso estuvo restringido por mantenimiento.</strong><br>
              La ventana de mantenimiento ya terminó. Intenta iniciar sesión nuevamente.
            </div>
          </div>
        <?php elseif (@$_GET['stat'] == 'demo_vencida' && !$mttoActivo): ?>
          <div class="alert alert-warning alert-custom">
            <i class="fa fa-hourglass-end"></i>
            <div>
              <strong>Tu cuenta de demostración venció.</strong><br>
              Crea una nueva desde <a href="demo/">Acceso demostrativo</a> o escribe al
              administrador para una cuenta definitiva.
            </div>
          </div>
        <?php endif; ?>

        <form action="../controllers/musuarios_controller" method="post" id="loginForm" autocomplete="off">
          <input id="action" name="action" type="hidden" value="login" />
          <input type="hidden" name="captcha_token" value="<?= htmlspecialchars($cap_token, ENT_QUOTES, 'UTF-8') ?>" />

          <div class="form-group">
            <label for="usuario">Usuario</label>
            <div class="input-wrap">
              <input id="usuario" name="usuario" type="text" class="form-control" placeholder="Ingresa tu usuario"
                autocorrect="off" spellcheck="false" autocomplete="username"
                value="<?= htmlspecialchars($loginUsuarioPrevio, ENT_QUOTES, 'UTF-8') ?>">
              <i class="fa fa-user input-icon"></i>
            </div>
            <div class="validation-msg" id="ms_usuario"></div>
          </div>

          <div class="form-group">
            <label for="password1">Contraseña</label>
            <div class="input-wrap">
              <input id="password1" name="password1" type="password" class="form-control"
                placeholder="Ingresa tu contraseña" autocorrect="off" spellcheck="false"
                autocomplete="current-password">
              <button type="button" class="btn-eye" onclick="togglePassword(this)" tabindex="-1">
                <i class="fa fa-eye-slash"></i>
              </button>
            </div>
            <div class="validation-msg" id="ms_password1"></div>
          </div>

          <!-- Recuperación de contraseña por correo -->
          <div class="olvide-wrap">
            <a href="credencial/recuperar" class="olvide-link">
              <i class="fa fa-key"></i>
              ¿Olvidaste tu contraseña?
            </a>
          </div>

          <div class="form-group">
            <div class="label-row">
              <label for="captcha_respuesta">Verificación de seguridad</label>
              <button type="button" id="cap-refresh" class="cap-refresh"
                title="Generar otra verificación" aria-label="Generar otra verificación">
                <i class="fa fa-rotate-right" aria-hidden="true"></i>
                <span>Cambiar</span>
              </button>
            </div>
            <div class="captcha-row">
              <div class="captcha-pregunta" id="captcha-caja">
                <span id="cap-a"><?= $cap_a ?></span>
                <span class="op" id="cap-op"><?= $cap_signo ?></span>
                <span id="cap-b"><?= $cap_b ?></span>
                <span style="color:#64748b;font-weight:600;font-size:14px">=</span>
              </div>
              <div class="input-wrap">
                <input id="captcha_respuesta" name="captcha_respuesta" type="text" class="form-control"
                  placeholder="Resultado" inputmode="numeric" pattern="[0-9\-]*" maxlength="3" autocomplete="off"
                  style="padding-right:14px;">
              </div>
            </div>
            <div class="validation-msg" id="ms_captcha"></div>
          </div>

          <div class="form-group form-group--submit">
            <button type="submit" id="submit" name="submit" class="btn-login">
              <i class="fa fa-sign-in"></i>
              Iniciar Sesión
            </button>
          </div>

          <?php if (!$mttoActivo && defined('SOVCA_DEMO_ACTIVO') && SOVCA_DEMO_ACTIVO): ?>
            <!-- ============================================================
                 ACCESO DEMOSTRATIVO
                 Abre la pantalla donde el visitante crea su propia cuenta
                 sobre la base de pruebas (vigencia de 15 días).
                 No se ofrece durante el mantenimiento: el servidor rechaza
                 la creación de cuentas demo mientras el sistema está cerrado.
                 ============================================================ -->
            <div class="demo-cta">
              <a href="demo/" class="btn-demo-login">
                <i class="fa fa-unlock"></i>
                Acceso demostrativo
              </a>
              <span class="demo-cta__hint">
                Cuenta de prueba de <?= (int) SOVCA_DEMO_DIAS ?> días · datos de ejemplo
              </span>
            </div>
          <?php endif; ?>

          <?php if (!$mttoActivo): ?>
            <div class="offline-status" id="offline-mode-status">Comprobando estado de conexión...</div>
            <div class="offline-trigger" id="offline-mode-discrete">
              <button type="button" id="offline-discrete-button" title="Activar este dispositivo para uso offline">
                <i class="fa fa-cloud-download"></i>Modo offline
              </button>
            </div>
          <?php else: ?>
            <div class="offline-status offline-status--mtto" id="offline-mode-status">
              <i class="fa fa-cloud"></i>
              Modo offline no disponible durante el mantenimiento.
            </div>
          <?php endif; ?>
        </form>

        <!-- Footer con logo secundario -->
        <div class="login-footer">
          <span class="version">SOVCA</span> · © <?= date('Y') ?> Global-SOVCA
          <img src="../dist/img/logo_sirelog.png" alt="SIREL" class="footer-corner-logo">
        </div>

      </div>
    </div>
  </div>

  <?php if (!$mttoActivo): ?>
  <!-- Modal Offline -->
  <div id="offline-modal"
    style="display:none; position:fixed; left:0; right:0; top:0; bottom:0; background:rgba(0,0,0,0.4); align-items:center; justify-content:center; z-index:1000; padding:16px; overflow-y:auto;">
    <div
      style="background:#fff; width:100%; max-width:420px; margin:auto; padding:18px; border-radius:8px; box-shadow:0 8px 30px rgba(0,0,0,0.2); max-height:calc(100vh - 32px); overflow-y:auto;">
      <h4 style="margin-top:0; color:#5B3A1E;">USAR ESTE DISPOSITIVO PARA MANEJAR EN MODO OFFLINE</h4>
      <p style="color:#5a5a5a;">Se instalará la versión offline y se descargarán los datos asociados a tu usuario. Debes
        ingresar tus credenciales para autorizar la precarga.</p>
      <form id="offline-install-form">
        <div class="form-group">
          <input id="offline-usuario" name="offline-usuario" type="text" class="form-control" placeholder="Usuario"
            required>
        </div>
        <div class="form-group">
          <input id="offline-password" name="offline-password" type="password" class="form-control"
            placeholder="Contraseña" required>
        </div>
        <div class="form-group">
          <div class="label-row">
            <label for="offline-captcha-respuesta">Verificación de seguridad</label>
            <button type="button" id="offline-cap-refresh" class="cap-refresh"
              title="Generar otra verificación" aria-label="Generar otra verificación">
              <i class="fa fa-rotate-right" aria-hidden="true"></i>
              <span>Cambiar</span>
            </button>
          </div>
          <div class="captcha-row">
            <div class="captcha-pregunta" id="offline-captcha-caja">
              <span id="offline-cap-a"><?= $capOff['a'] ?></span>
              <span class="op" id="offline-cap-op"><?= $capOff['signo'] ?></span>
              <span id="offline-cap-b"><?= $capOff['b'] ?></span>
              <span style="color:#64748b;font-weight:600;font-size:14px">=</span>
            </div>
            <div class="input-wrap">
              <input id="offline-captcha-respuesta" name="offline-captcha-respuesta" type="text" class="form-control"
                placeholder="Resultado" inputmode="numeric" pattern="[0-9\-]*" maxlength="3" autocomplete="off"
                required style="padding-right:14px;">
            </div>
          </div>
          <input type="hidden" id="offline-captcha-token"
            value="<?= htmlspecialchars($capOff['token'], ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div id="offline-progress" style="display:none; margin-top:8px;">
          <div style="background:#e9ecef;border-radius:6px;overflow:hidden;height:12px;">
            <div id="offline-progress-bar"
              style="width:0%; height:12px; background:linear-gradient(90deg,#B45309,#E7A23C);"></div>
          </div>
          <p id="offline-progress-text" style="font-size:13px;color:#5B3A1E;margin-top:6px;">Preparando...</p>
        </div>
        <div style="text-align:right; margin-top:8px;">
          <button type="button" id="offline-cancel" class="btn btn-default">Cancelar</button>
          <button type="submit" id="offline-confirm" class="btn btn-primary">Instalar y precargar</button>
        </div>
        <p id="offline-install-msg" style="color:#d63301; margin-top:8px; min-height:18px;"></p>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <script src="../plugins/jQuery/jQuery-2.1.4.min.js" type="text/javascript"></script>
  <script src="../bootstrap/js/bootstrap.min.js" type="text/javascript"></script>

  <script>
    (function () {
      'use strict';

      /* Mantenimiento: el servidor ya bloquea demo, offline y API, y aquí
         se evita además registrar el service worker o mostrar los avisos
         de conexión de la PWA. */
      var MTTO_ACTIVO = <?= $mttoActivo ? 'true' : 'false' ?>;

      function $(id) { return document.getElementById(id); }
      var __toastTimer = {};

      function showError(id, msg) {
        var el = $(id);
        if (!el) return;
        el.textContent = msg;
        el.classList.add('visible');
        clearTimeout(__toastTimer[id]);
        __toastTimer[id] = setTimeout(function () { el.classList.remove('visible'); }, 4000);
      }
      function hideError(id) {
        var el = $(id);
        if (el) el.classList.remove('visible');
      }
      function marcarInvalido(el) {
        if (!el) return;
        el.classList.add('is-invalid');
        setTimeout(function () { el.classList.remove('is-invalid'); }, 2500);
      }

      /* ------------------------------------------------------------
         CAPTCHA: pedir uno nuevo sin recargar el formulario
         ------------------------------------------------------------ */
      var CAP_VIGENCIA = 600; // segundos (el captcha del servidor caduca a los 15 min)
      var capGeneradoEn = Math.floor(Date.now() / 1000);
      var capPidiendo = false;

      function pintarCaptcha(d) {
        var ea = document.getElementById('cap-a');
        var eb = document.getElementById('cap-b');
        var eo = document.getElementById('cap-op');
        var tok = document.querySelector('input[name="captcha_token"]');
        if (ea) ea.textContent = d.a;
        if (eb) eb.textContent = d.b;
        if (eo) eo.textContent = d.signo;
        if (tok) tok.value = d.token;
        capGeneradoEn = Math.floor(Date.now() / 1000);
      }

      function refrescarCaptcha(silencioso) {
        if (capPidiendo) return;
        capPidiendo = true;

        var caja = document.getElementById('captcha-caja');
        var btn = document.getElementById('cap-refresh');
        if (caja) caja.classList.add('cargando');
        if (btn) btn.classList.add('girando');

        function terminar() {
          capPidiendo = false;
          if (caja) caja.classList.remove('cargando');
          if (btn) btn.classList.remove('girando');
        }

        var xhr = new XMLHttpRequest();
        xhr.open('POST', '../controllers/mcaptcha_controller', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onreadystatechange = function () {
          if (xhr.readyState !== 4) return;
          terminar();

          var d = null;
          try { d = JSON.parse(xhr.responseText); } catch (err) { d = null; }

          if (xhr.status !== 200 || !d || !d.ok) {
            if (!silencioso) showError('ms_captcha', 'No se pudo actualizar la verificación. Intenta de nuevo.');
            return;
          }

          pintarCaptcha(d);
          hideError('ms_captcha');

          var cap = document.getElementById('captcha_respuesta');
          if (cap) {
            cap.value = '';
            if (!silencioso) cap.focus();
          }
        };
        xhr.send('action=nuevo&_=' + Date.now());
      }

      function captchaVencido() {
        return (Math.floor(Date.now() / 1000) - capGeneradoEn) > CAP_VIGENCIA;
      }

      window.togglePassword = function (btn) {
        var input = $('password1');
        var icon = btn.querySelector('i');
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

      function validarLogin(e) {
        var ok = true;
        var usu = $('usuario');
        var psw = $('password1');
        var cap = $('captcha_respuesta');

        if (!usu.value.trim()) {
          showError('ms_usuario', 'Debe escribir el usuario');
          marcarInvalido(usu);
          ok = false;
        }
        if (!psw.value.trim()) {
          showError('ms_password1', 'Debe escribir la contraseña');
          marcarInvalido(psw);
          ok = false;
        }
        var capVal = cap.value.trim();
        if (!capVal) {
          showError('ms_captcha', 'Debe resolver el captcha');
          marcarInvalido(cap);
          ok = false;
        } else if (!/^-?\d+$/.test(capVal)) {
          showError('ms_captcha', 'Solo números permitidos');
          marcarInvalido(cap);
          ok = false;
        }

        if (!ok) { e.preventDefault(); return false; }

        var btn = $('submit');
        if (btn) {
          btn.disabled = true;
          btn.innerHTML = '<i class="fa fa-refresh fa-spin"></i> Verificando...';
        }
        return true;
      }

      document.addEventListener('DOMContentLoaded', function () {
        var form = $('loginForm');
        if (form) form.addEventListener('submit', validarLogin);

        ['usuario', 'password1', 'captcha_respuesta'].forEach(function (id) {
          var el = $(id);
          var msgId = 'ms_' + (id === 'captcha_respuesta' ? 'captcha' : id);
          if (!el) return;
          el.addEventListener('focus', function () { hideError(msgId); });
          el.addEventListener('input', function () { hideError(msgId); });
        });

        var cap = $('captcha_respuesta');
        if (cap) {
          cap.addEventListener('input', function () {
            this.value = this.value.replace(/[^\d\-]/g, '');
          });
        }

        /* Botón: generar otra verificación sin recargar el formulario */
        var btnCap = $('cap-refresh');
        if (btnCap) {
          btnCap.addEventListener('click', function (e) {
            e.preventDefault();
            refrescarCaptcha(false);
          });
        }

        /* Refresco automático: si la página lleva mucho tiempo abierta el
           captcha del servidor caduca, así que se pide otro al volver a la
           pestaña (sin interrumpir lo que el usuario está escribiendo). */
        document.addEventListener('visibilitychange', function () {
          if (!document.hidden && captchaVencido()) refrescarCaptcha(true);
        });
        window.addEventListener('focus', function () {
          if (captchaVencido()) refrescarCaptcha(true);
        });

        var autofocusTarget = <?= $autofocusPassword ? "'password1'" : "'usuario'" ?>;
        var elAutofocus = document.getElementById(autofocusTarget.replace(/'/g, ''));
        if (elAutofocus) setTimeout(function () { elAutofocus.focus(); }, 400);

        // Auto-redirect cada 12 horas
        setTimeout(function () {
          window.location.href = 'https://global.ruted.net/';
        }, 43200000);

        // ---------- OFFLINE ----------
        if (!MTTO_ACTIVO) {
          actualizarModoOffline();
          precargarOfflineShell();
        }

        var offlineDiscrete = $('offline-discrete-button');
        var offlineModal = $('offline-modal');
        var offlineCancel = $('offline-cancel');
        var offlineForm = $('offline-install-form');
        var offlineMsg = $('offline-install-msg');

        /* Captcha propio de este modal (slot 'captcha_offline').
           Es de un solo uso: se renueva al abrir y después de cada
           intento fallido, sin tocar el captcha del formulario. */
        function pintarCaptchaOffline(d) {
          var ea = $('offline-cap-a');
          var eb = $('offline-cap-b');
          var eo = $('offline-cap-op');
          var tok = $('offline-captcha-token');
          var resp = $('offline-captcha-respuesta');
          if (ea) ea.textContent = d.a;
          if (eb) eb.textContent = d.b;
          if (eo) eo.textContent = d.signo;
          if (tok) tok.value = d.token;
          if (resp) resp.value = '';
        }

        var offlineCapPidiendo = false;
        async function refrescarCaptchaOffline() {
          if (offlineCapPidiendo) return;
          offlineCapPidiendo = true;

          var caja = $('offline-captcha-caja');
          var btn = $('offline-cap-refresh');
          if (caja) caja.classList.add('cargando');
          if (btn) btn.classList.add('girando');

          try {
            var r = await fetch('../controllers/mcaptcha_controller.php?slot=captcha_offline&_=' + Date.now(), { cache: 'no-store' });
            var d = await r.json();
            if (d && d.ok) pintarCaptchaOffline(d);
          } catch (e) {
            console.warn('No se pudo renovar la verificación del modo offline:', e);
          } finally {
            offlineCapPidiendo = false;
            if (caja) caja.classList.remove('cargando');
            if (btn) btn.classList.remove('girando');
          }
        }

        var offlineCapRefresh = $('offline-cap-refresh');
        if (offlineCapRefresh) {
          offlineCapRefresh.addEventListener('click', refrescarCaptchaOffline);
        }

        if (offlineDiscrete) {
          offlineDiscrete.addEventListener('click', function (e) {
            e.preventDefault();
            var u = $('usuario'), p = $('password1');
            var mu = $('offline-usuario'), mp = $('offline-password');
            if (mu && u) mu.value = u.value || '';
            if (mp && p) mp.value = p.value || '';
            if (offlineModal) offlineModal.style.display = 'flex';
            /* Token vigente aunque la página lleve abierta un rato */
            refrescarCaptchaOffline();
          });
        }

        if (offlineCancel && offlineModal) {
          offlineCancel.addEventListener('click', function () {
            offlineModal.style.display = 'none';
            offlineMsg.textContent = '';
          });
        }

        if (offlineForm) {
          offlineForm.addEventListener('submit', async function (ev) {
            ev.preventDefault();
            offlineMsg.textContent = '';
            var progress = $('offline-progress');
            var progressBar = $('offline-progress-bar');
            var progressText = $('offline-progress-text');
            var confirmBtn = $('offline-confirm');
            var cancelBtn = $('offline-cancel');

            if (!navigator.onLine) { offlineMsg.textContent = 'Necesitas conexión para habilitar el modo offline.'; return; }

            var usuario = $('offline-usuario').value.trim();
            var password = $('offline-password').value;
            if (!usuario || !password) { offlineMsg.textContent = 'Completa usuario y contraseña.'; return; }

            var captchaToken = $('offline-captcha-token').value;
            var captchaRespuesta = $('offline-captcha-respuesta').value.trim();
            if (!captchaRespuesta) {
              offlineMsg.textContent = 'Resuelve la verificación de seguridad.';
              return;
            }

            confirmBtn.disabled = true; cancelBtn.disabled = true;
            progress.style.display = 'block';
            progressBar.style.width = '5%';
            progressText.textContent = 'Autenticando...';

            try {
              var resp = await fetch('../api/auth_controller.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                  action: 'login',
                  usuario: usuario,
                  password: password,
                  dispositivo: 'web-offline-install',
                  captcha_token: captchaToken,
                  captcha_respuesta: captchaRespuesta
                })
              });
              var datos = await resp.json();
              if (!resp.ok || datos.resultado === 'error') {
                offlineMsg.textContent = datos.mensaje || 'Error al autenticar.';
                confirmBtn.disabled = false; cancelBtn.disabled = false;
                progress.style.display = 'none';
                /* El captcha se consumió: renovarlo para el siguiente intento */
                refrescarCaptchaOffline();
                return;
              }

              var token = datos.token;
              progressBar.style.width = '35%';
              progressText.textContent = 'Registrando el instalador offline...';

              if ('serviceWorker' in navigator) {
                await navigator.serviceWorker.register('../app_offline/service-worker.js', { scope: '../app_offline/' });
                progressBar.style.width = '55%';
                progressText.textContent = 'Descargando la app (cacheando shell)...';
                await fetch('../app_offline/index.html').catch(function () { });
                progressBar.style.width = '75%';
                progressText.textContent = 'Iniciando precarga de datos...';
              }

              var preloadHash = '';
              try {
                var encoder = new TextEncoder();
                var data = encoder.encode(password + 'SOVCA_OFFLINE_SALT');
                var digest = await crypto.subtle.digest('SHA-256', data);
                preloadHash = Array.from(new Uint8Array(digest))
                  .map(function (b) { return b.toString(16).padStart(2, '0'); })
                  .join('');
              } catch (herr) { console.warn('No se pudo calcular el hash offline:', herr); }

              await new Promise(function (r) { setTimeout(r, 350); });
              progressBar.style.width = '90%';
              progressText.textContent = 'Redirigiendo para completar la precarga...';

              var qs = '?preload_token=' + encodeURIComponent(token) +
                '&preload_user=' + encodeURIComponent(usuario) +
                '&preload_hash=' + encodeURIComponent(preloadHash);
              window.location.href = '../app_offline/index.html' + qs;
            } catch (err) {
              console.warn(err);
              offlineMsg.textContent = 'Error al comunicarse con el servidor.';
              confirmBtn.disabled = false; cancelBtn.disabled = false;
              progress.style.display = 'none';
              refrescarCaptchaOffline();
            }
          });
        }

        window.addEventListener('online', function () {
          if (MTTO_ACTIVO) return;
          actualizarModoOffline();
          precargarOfflineShell();
        });
        window.addEventListener('offline', function () {
          if (MTTO_ACTIVO) return;
          actualizarModoOffline();
        });
      });

      function actualizarModoOffline() {
        var offlineStatus = document.getElementById('offline-mode-status');
        if (!offlineStatus) return;
        offlineStatus.textContent = navigator.onLine
          ? 'Conexión disponible. El modo online está activo.'
          : 'Sin conexión: usa el portal offline para continuar.';
      }

      async function precargarOfflineShell() {
        if (!navigator.onLine || !('serviceWorker' in navigator)) return;
        try {
          var reg = await navigator.serviceWorker.register(
            '../app_offline/service-worker.js',
            { scope: '../app_offline/' }
          );
          if (reg.active || reg.waiting || reg.installing) {
            fetch('../app_offline/index.html').catch(function () { });
          }
        } catch (e) {
          console.warn('No se pudo registrar el service worker offline:', e);
        }
      }
    })();
  </script>
  <!-- ============================================================
       AVISO DE INSTALACIÓN (PWA)
       Se muestra solo en móvil, solo si la app no está instalada y
       solo si el usuario no lo ha descartado hace poco.
       · Android/Chrome/Edge: usa el aviso nativo del navegador.
       · iPhone/iPad: no existe aviso nativo → instrucciones.
       ============================================================ -->
  <style>
    .pwa-instalar {
      position: fixed;
      left: 0; right: 0; bottom: 0;
      z-index: 99999;
      display: none;
      padding: 12px 14px;
      background: #ffffff;
      border-top: 1px solid #d9e4dc;
      box-shadow: 0 -6px 20px rgba(0, 0, 0, .14);
      font-family: 'Poppins', Arial, sans-serif;
    }
    .pwa-instalar.is-visible { display: block; }
    .pwa-instalar__caja {
      display: flex; align-items: center; gap: 12px;
      max-width: 620px; margin: 0 auto;
    }
    .pwa-instalar__icono { width: 44px; height: 44px; border-radius: 10px; flex: 0 0 auto; }
    .pwa-instalar__texto { flex: 1 1 auto; min-width: 0; }
    .pwa-instalar__titulo { font-size: 14px; font-weight: 600; color: #0b4a2b; line-height: 1.25; }
    .pwa-instalar__sub { font-size: 12px; color: #6b7280; line-height: 1.3; margin-top: 2px; }
    .pwa-instalar__acciones { display: flex; gap: 8px; flex: 0 0 auto; }
    .pwa-instalar__btn {
      border: 0; border-radius: 8px; padding: 9px 14px;
      font-size: 13px; font-weight: 600; cursor: pointer; white-space: nowrap;
      font-family: inherit;
    }
    .pwa-instalar__btn--ok { background: #006532; color: #fff; }
    .pwa-instalar__btn--ok:disabled { opacity: .6; }
    .pwa-instalar__btn--no { background: #eef2ef; color: #4b5563; }
    @media (max-width: 430px) {
      .pwa-instalar__sub { display: none; }
      .pwa-instalar__btn { padding: 9px 11px; font-size: 12px; }
    }
  </style>

  <div id="pwa-instalar" class="pwa-instalar" role="dialog" aria-live="polite" aria-label="Instalar aplicación">
    <div class="pwa-instalar__caja">
      <img class="pwa-instalar__icono" src="../app_offline/icons/icon-192.png" alt="">
      <div class="pwa-instalar__texto">
        <div class="pwa-instalar__titulo" id="pwa-instalar-titulo">Instala SOVCA en tu dispositivo</div>
        <div class="pwa-instalar__sub" id="pwa-instalar-sub">Ábrela desde el ícono, como cualquier otra aplicación.</div>
      </div>
      <div class="pwa-instalar__acciones">
        <button type="button" class="pwa-instalar__btn pwa-instalar__btn--ok" id="pwa-instalar-ok">Instalar</button>
        <button type="button" class="pwa-instalar__btn pwa-instalar__btn--no" id="pwa-instalar-no">Ahora no</button>
      </div>
    </div>
  </div>

  <script>
  (function () {
    'use strict';

    var NOMBRE_APP   = 'SOVCA';
    var CLAVE_OMITIR = 'sovca_pwa_omitir';
    var DIAS_OMITIR  = 7;

    var banner    = document.getElementById('pwa-instalar');
    var btnOk     = document.getElementById('pwa-instalar-ok');
    var btnNo     = document.getElementById('pwa-instalar-no');
    var titulo    = document.getElementById('pwa-instalar-titulo');
    var subtitulo = document.getElementById('pwa-instalar-sub');
    if (!banner) { return; }

    var promptDiferido = null;

    /* ¿Ya se está usando como aplicación instalada? */
    function abiertaComoApp() {
      try {
        return window.matchMedia('(display-mode: standalone)').matches ||
               window.matchMedia('(display-mode: minimal-ui)').matches ||
               window.navigator.standalone === true;
      } catch (e) { return false; }
    }

    /* Solo en móviles: en escritorio no tiene sentido el aviso. */
    function esMovil() {
      var ua = navigator.userAgent || '';
      if (/Android|iPhone|iPad|iPod|Mobile|Windows Phone/i.test(ua)) { return true; }
      try {
        return window.matchMedia('(pointer: coarse)').matches && window.innerWidth <= 900;
      } catch (e) { return false; }
    }

    function esIOS() {
      var ua = navigator.userAgent || '';
      return /iPad|iPhone|iPod/.test(ua) ||
             (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    }

    function omitidoReciente() {
      try {
        var t = parseInt(localStorage.getItem(CLAVE_OMITIR), 10);
        if (!t) { return false; }
        return (Date.now() - t) < (DIAS_OMITIR * 24 * 60 * 60 * 1000);
      } catch (e) { return false; }
    }

    function mostrar(conInstrucciones) {
      if (abiertaComoApp() || !esMovil() || omitidoReciente()) { return; }
      if (conInstrucciones) {
        titulo.textContent    = 'Instala ' + NOMBRE_APP + ' en tu iPhone/iPad';
        subtitulo.textContent = 'Toca Compartir y elige "Añadir a pantalla de inicio".';
        btnOk.textContent     = 'Ver cómo';
      }
      banner.classList.add('is-visible');
    }

    function ocultar() { banner.classList.remove('is-visible'); }

    /* Android / Chrome / Edge / Samsung Internet */
    window.addEventListener('beforeinstallprompt', function (e) {
      e.preventDefault();
      promptDiferido = e;
      mostrar(false);
    });

    window.addEventListener('appinstalled', function () {
      promptDiferido = null;
      try { localStorage.removeItem(CLAVE_OMITIR); } catch (e) {}
      ocultar();
    });

    btnNo.addEventListener('click', function () {
      try { localStorage.setItem(CLAVE_OMITIR, String(Date.now())); } catch (e) {}
      ocultar();
    });

    btnOk.addEventListener('click', function () {
      if (promptDiferido) {
        btnOk.disabled = true;
        var p = promptDiferido;
        promptDiferido = null;
        try {
          p.prompt();
          if (p.userChoice && p.userChoice.then) {
            p.userChoice.then(function () { btnOk.disabled = false; ocultar(); })
                          .catch(function () { btnOk.disabled = false; ocultar(); });
          } else {
            btnOk.disabled = false; ocultar();
          }
        } catch (e) {
          btnOk.disabled = false; ocultar();
        }
        return;
      }

      alert('Para instalar ' + NOMBRE_APP + ':\n\n' +
            '1. Toca el botón Compartir (cuadrado con flecha) en el navegador.\n' +
            '2. Elige "Añadir a pantalla de inicio".\n' +
            '3. Confirma con "Añadir".\n\n' +
            'Listo: ' + NOMBRE_APP + ' quedará como una aplicación más.');
    });

    /* iPhone/iPad no dispara beforeinstallprompt */
    if (esIOS() && !abiertaComoApp() && !omitidoReciente()) {
      mostrar(true);
    }

    /* Service worker del portal: es lo que hace la web instalable.
       El scope queda en la carpeta de la app (/sovca/). */
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', function () {
        navigator.serviceWorker.register('../sw.js').catch(function (e) {
          console.warn('Service worker del portal no registrado:', e);
        });
      });
    }
  })();
  </script>
</body>

</html>
