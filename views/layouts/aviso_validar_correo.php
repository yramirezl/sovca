<?php
/* ============================================================
   SOVCA | Aviso de validación de correo electrónico
   ------------------------------------------------------------
   Se muestra a los usuarios que todavía no confirmaron su
   correo (musuarios.act_email = 0).

   Modos:
     - 'modal'  : se abre una sola vez por sesión (lo incluye
                  views/layouts/menu_lateral_izquierdo.php cuando
                  $_SESSION['accion'] está activo).
     - 'banner' : recordatorio permanente en el inicio
                  (lo incluye views/inicio/blank.php).

   Variables de entrada (opcional):
     $bovsAvisoModo = 'modal' | 'banner'   (por defecto 'modal')

   No usa jquery-confirm: es HTML + CSS propios y se mueve a
   <body> para no quedar atrapado por el z-index de #content-wrapper.
   ============================================================ */

if (!isset($bovsAvisoModo)) {
    $bovsAvisoModo = 'modal';
}

$bovsAvisoRol = isset($_SESSION['rolx']) ? (int)$_SESSION['rolx'] : 0;
$bovsAvisoAct = isset($_SESSION['act_email']) ? (int)$_SESSION['act_email'] : 1;
$bovsAvisoAcc = !empty($_SESSION['accion']);

/* Solo el productor (rol 2) y solo mientras el correo no esté validado */
if ($bovsAvisoRol !== 2 || $bovsAvisoAct === 1) {
    return;
}

if ($bovsAvisoModo === 'modal') {

    if (!$bovsAvisoAcc) {
        return;
    }

    /* Un solo aviso por sesión */
    $_SESSION['accion'] = false;

} else {

    /* Si el modal ya se mostró en esta carga, el banner sobra */
    if (!empty($GLOBALS['bovsAvisoEmailModalMostrado'])) {
        return;
    }
}

$bovsAvisoCorreo = isset($_SESSION['emailx']) ? trim((string)$_SESSION['emailx']) : '';
$bovsAvisoNombre = isset($_SESSION['nombresx']) ? trim((string)$_SESSION['nombresx']) : '';
$bovsAvisoCuenta = isset($_SESSION['usuariox']) ? trim((string)$_SESSION['usuariox']) : '';

if ($bovsAvisoNombre === '') {
    $bovsAvisoNombre = $bovsAvisoCuenta;
}

if (function_exists('bovs_nombre_mostrar')) {
    $bovsAvisoNombre = bovs_nombre_mostrar($bovsAvisoNombre);
}

$bovsAvisoCorreoTxt = $bovsAvisoCorreo !== ''
    ? '<strong>' . htmlspecialchars($bovsAvisoCorreo, ENT_QUOTES, 'UTF-8') . '</strong>'
    : ($bovsAvisoCuenta !== ''
        ? 'el correo de tu cuenta <strong>' . htmlspecialchars($bovsAvisoCuenta, ENT_QUOTES, 'UTF-8') . '</strong>'
        : 'tu correo electrónico');

$bovsAvisoUrl = '../inicio/perfil';

if ($bovsAvisoModo === 'modal') {
    $GLOBALS['bovsAvisoEmailModalMostrado'] = true;
}
?>

<style>
/* ------------------ Aviso de validación de correo ------------------ */
.bovs-aviso-email {
    font-family: inherit;
    -webkit-font-smoothing: antialiased;
}

/* -------- Modal -------- */
.bovs-aviso-email--modal {
    position: fixed;
    top: 0;
    right: 0;
    bottom: 0;
    left: 0;
    z-index: 3000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 18px;
    opacity: 0;
    transition: opacity .18s ease;
}
.bovs-aviso-email--modal[hidden] { display: none !important; }
.bovs-aviso-email--modal.is-visible { opacity: 1; }

.bovs-aviso-email__velo {
    position: absolute;
    top: 0;
    right: 0;
    bottom: 0;
    left: 0;
    background: rgba(15, 23, 42, .55);
    backdrop-filter: blur(2px);
}

.bovs-aviso-email__caja {
    position: relative;
    width: 100%;
    max-width: 460px;
    max-height: calc(100vh - 36px);
    overflow-y: auto;
    background: #ffffff;
    border-radius: 18px;
    padding: 26px 24px 20px;
    box-shadow: 0 26px 60px -22px rgba(15, 23, 42, .65);
    text-align: center;
    transform: translateY(10px) scale(.98);
    transition: transform .2s ease;
}
.bovs-aviso-email--modal.is-visible .bovs-aviso-email__caja {
    transform: translateY(0) scale(1);
}

.bovs-aviso-email__icono {
    width: 62px;
    height: 62px;
    margin: 0 auto 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: linear-gradient(160deg, #fde68a 0%, #f59e0b 100%);
    color: #7c2d12;
    font-size: 26px;
    box-shadow: 0 10px 22px -12px rgba(245, 158, 11, .95);
}

.bovs-aviso-email__eyebrow {
    display: inline-block;
    margin-bottom: 8px;
    padding: 3px 10px;
    border-radius: 999px;
    background: #fff7ed;
    border: 1px solid #fed7aa;
    color: #b45309;
    font-size: 10.5px;
    font-weight: 800;
    letter-spacing: .09em;
    text-transform: uppercase;
}

.bovs-aviso-email__titulo {
    margin: 0 0 8px;
    font-size: 19px;
    font-weight: 800;
    line-height: 1.25;
    color: #0f172a;
}

.bovs-aviso-email__texto {
    margin: 0 0 14px;
    font-size: 13.5px;
    line-height: 1.55;
    color: #475569;
}
.bovs-aviso-email__texto strong { color: #0f172a; }

.bovs-aviso-email__lista {
    margin: 0 0 18px;
    padding: 0;
    list-style: none;
    text-align: left;
    font-size: 13px;
    color: #334155;
}
.bovs-aviso-email__lista li {
    display: flex;
    align-items: flex-start;
    gap: 9px;
    padding: 7px 0;
    border-bottom: 1px dashed #e2e8f0;
    line-height: 1.4;
}
.bovs-aviso-email__lista li:last-child { border-bottom: 0; }
.bovs-aviso-email__lista i {
    flex: 0 0 auto;
    width: 18px;
    height: 18px;
    margin-top: 1px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #dcfce7;
    color: #15803d;
    font-size: 10px;
}

.bovs-aviso-email__acciones {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 4px;
}
.bovs-aviso-email__btn {
    flex: 1 1 150px;
    min-height: 44px;
    padding: 11px 18px;
    border: 1px solid transparent;
    border-radius: 11px;
    font-size: 13.5px;
    font-weight: 700;
    cursor: pointer;
    transition: filter .15s ease, background .15s ease, box-shadow .15s ease;
}
.bovs-aviso-email__btn--primario {
    background: linear-gradient(180deg, #16a34a 0%, #15803d 100%);
    color: #fff;
    box-shadow: 0 10px 20px -12px rgba(21, 128, 61, .95);
}
.bovs-aviso-email__btn--primario:hover { filter: brightness(1.06); }
.bovs-aviso-email__btn--suave {
    background: #f8fafc;
    border-color: #e2e8f0;
    color: #475569;
}
.bovs-aviso-email__btn--suave:hover { background: #f1f5f9; }
.bovs-aviso-email__btn i { margin-right: 7px; }

.bovs-aviso-email__pie {
    margin: 12px 0 0;
    font-size: 11.5px;
    color: #94a3b8;
}

/* -------- Banner del inicio -------- */
.bovs-aviso-email--banner {
    position: relative;
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
    margin: 14px 18px 0;
    padding: 14px 44px 14px 18px;
    background: #ffffff;
    border: 1px solid #fde68a;
    border-left: 5px solid #f59e0b;
    border-radius: 14px;
    box-shadow: 0 12px 26px -22px rgba(15, 23, 42, .55);
}
.bovs-aviso-email--banner[hidden] { display: none !important; }

.bovs-aviso-email--banner .bovs-aviso-email__icono {
    width: 42px;
    height: 42px;
    margin: 0;
    font-size: 18px;
    flex: 0 0 auto;
}

.bovs-aviso-email__banner-texto { flex: 1 1 260px; min-width: 0; }
.bovs-aviso-email__banner-texto strong {
    display: block;
    font-size: 14px;
    font-weight: 800;
    color: #0f172a;
    margin-bottom: 2px;
}
.bovs-aviso-email__banner-texto span {
    font-size: 12.5px;
    line-height: 1.45;
    color: #475569;
}

.bovs-aviso-email--banner .bovs-aviso-email__acciones {
    flex: 0 0 auto;
    margin: 0;
}
.bovs-aviso-email--banner .bovs-aviso-email__btn {
    flex: 0 0 auto;
    min-height: 40px;
    padding: 9px 16px;
    font-size: 13px;
}

.bovs-aviso-email__cerrar {
    position: absolute;
    top: 9px;
    right: 9px;
    width: 30px;
    height: 30px;
    border: 1px solid #e2e8f0;
    border-radius: 50%;
    background: #fff;
    color: #94a3b8;
    font-size: 12px;
    line-height: 1;
    cursor: pointer;
    transition: background .15s ease, color .15s ease;
}
.bovs-aviso-email__cerrar:hover { background: #f1f5f9; color: #475569; }

@media (max-width: 620px) {
    .bovs-aviso-email--banner { margin: 12px 10px 0; padding: 12px 40px 12px 14px; gap: 12px; }
    .bovs-aviso-email__banner-texto { flex: 1 1 150px; }
    .bovs-aviso-email--banner .bovs-aviso-email__acciones { flex: 1 1 auto; }
    .bovs-aviso-email__caja { padding: 22px 18px 18px; }
    .bovs-aviso-email__btn { flex: 1 1 100%; }
}
</style>

<?php if ($bovsAvisoModo === 'modal'): ?>

<div class="bovs-aviso-email bovs-aviso-email--modal" id="bovsAvisoEmail" hidden
     role="dialog" aria-modal="true" aria-labelledby="bovsAvisoEmailTitulo">
    <div class="bovs-aviso-email__velo" data-aviso-email-cerrar></div>

    <div class="bovs-aviso-email__caja">
        <div class="bovs-aviso-email__icono"><i class="fa fa-envelope-o" aria-hidden="true"></i></div>

        <span class="bovs-aviso-email__eyebrow">Acción requerida</span>

        <h4 class="bovs-aviso-email__titulo" id="bovsAvisoEmailTitulo">Valida tu correo electrónico</h4>

        <p class="bovs-aviso-email__texto">
            <?php if ($bovsAvisoNombre !== ''): ?>
                Hola <strong><?php echo htmlspecialchars($bovsAvisoNombre, ENT_QUOTES, 'UTF-8'); ?></strong>,
            <?php endif; ?>
            todavía no has confirmado <?php echo $bovsAvisoCorreoTxt; ?>.
            Es un paso rápido y nos permite mantener tu cuenta segura.
        </p>

        <ul class="bovs-aviso-email__lista">
            <li><i class="fa fa-check" aria-hidden="true"></i> Recuperar tu contraseña si la olvidas</li>
            <li><i class="fa fa-check" aria-hidden="true"></i> Recibir alertas y avisos del sistema</li>
            <li><i class="fa fa-check" aria-hidden="true"></i> Notificaciones de tu fundo</li>
        </ul>

        <div class="bovs-aviso-email__acciones">
            <button type="button" class="bovs-aviso-email__btn bovs-aviso-email__btn--primario"
                    data-aviso-email-ir>
                <i class="fa fa-envelope-open-o" aria-hidden="true"></i>Validar mi correo
            </button>
            <button type="button" class="bovs-aviso-email__btn bovs-aviso-email__btn--suave"
                    data-aviso-email-cerrar>
                Más tarde
            </button>
        </div>

        <p class="bovs-aviso-email__pie">Puedes hacerlo cuando quieras desde tu perfil.</p>

        <span data-aviso-email-url="<?php echo htmlspecialchars($bovsAvisoUrl, ENT_QUOTES, 'UTF-8'); ?>"></span>
    </div>
</div>

<script>
(function () {
    var aviso = document.getElementById('bovsAvisoEmail');
    if (!aviso) return;

    /* A <body>: así ningún contenedor con z-index lo deja detrás */
    if (aviso.parentNode !== document.body) {
        document.body.appendChild(aviso);
    }

    var caja      = aviso.querySelector('.bovs-aviso-email__caja');
    var nodoUrl   = aviso.querySelector('[data-aviso-email-url]');
    var urlPerfil = nodoUrl ? nodoUrl.getAttribute('data-aviso-email-url') : '../inicio/perfil';
    var ultimoFoco = document.activeElement;

    function abrir() {
        aviso.hidden = false;
        void aviso.offsetWidth;
        aviso.classList.add('is-visible');
        var primero = aviso.querySelector('[data-aviso-email-ir]');
        if (primero) primero.focus();
    }

    function cerrar() {
        aviso.classList.remove('is-visible');
        setTimeout(function () { aviso.hidden = true; }, 180);
        document.removeEventListener('keydown', alTeclear);
        if (ultimoFoco && ultimoFoco.focus) ultimoFoco.focus();
    }

    function alTeclear(evento) {
        if (evento.key === 'Escape') cerrar();
    }

    aviso.addEventListener('click', function (evento) {
        if (evento.target.closest('[data-aviso-email-ir]')) {
            window.location.href = urlPerfil;
            return;
        }
        if (evento.target.closest('[data-aviso-email-cerrar]')) {
            cerrar();
        }
    });

    /* Mantiene el foco dentro de la tarjeta mientras está abierta */
    aviso.addEventListener('keydown', function (evento) {
        if (evento.key !== 'Tab') return;

        var focos = aviso.querySelectorAll('button');
        if (!focos.length) return;

        var primero = focos[0];
        var ultimo  = focos[focos.length - 1];

        if (evento.shiftKey && document.activeElement === primero) {
            evento.preventDefault();
            ultimo.focus();
        } else if (!evento.shiftKey && document.activeElement === ultimo) {
            evento.preventDefault();
            primero.focus();
        }
    });

    document.addEventListener('keydown', alTeclear);
    setTimeout(abrir, 220);

    if (caja) caja.setAttribute('tabindex', '-1');
})();
</script>

<?php else: ?>

<div class="bovs-aviso-email bovs-aviso-email--banner" id="bovsAvisoEmailBanner" hidden>
    <div class="bovs-aviso-email__icono"><i class="fa fa-envelope-o" aria-hidden="true"></i></div>

    <div class="bovs-aviso-email__banner-texto">
        <strong>Valida tu correo electrónico</strong>
        <span>
            Confirma <?php echo $bovsAvisoCorreoTxt; ?> para recibir alertas
            y poder recuperar tu contraseña.
        </span>
    </div>

    <div class="bovs-aviso-email__acciones">
        <button type="button" class="bovs-aviso-email__btn bovs-aviso-email__btn--primario"
                data-aviso-email-ir>
            <i class="fa fa-check" aria-hidden="true"></i>Validar correo
        </button>
    </div>

    <button type="button" class="bovs-aviso-email__cerrar" data-aviso-email-banner-cerrar
            title="Ocultar este aviso por ahora" aria-label="Ocultar aviso">
        <i class="fa fa-times" aria-hidden="true"></i>
    </button>

    <span data-aviso-email-url="<?php echo htmlspecialchars($bovsAvisoUrl, ENT_QUOTES, 'UTF-8'); ?>"></span>
</div>

<script>
(function () {
    var banner = document.getElementById('bovsAvisoEmailBanner');
    if (!banner) return;

    var clave = 'bovsAvisoEmailOculto';
    var url   = banner.querySelector('[data-aviso-email-url]').getAttribute('data-aviso-email-url');

    /* Si el usuario lo ocultó en esta sesión del navegador, no insiste */
    try {
        if (sessionStorage.getItem(clave) === '1') return;
    } catch (e) {}

    banner.hidden = false;

    banner.addEventListener('click', function (evento) {
        if (evento.target.closest('[data-aviso-email-ir]')) {
            window.location.href = url;
            return;
        }

        if (evento.target.closest('[data-aviso-email-banner-cerrar]')) {
            banner.hidden = true;
            try { sessionStorage.setItem(clave, '1'); } catch (e) {}
        }
    });
})();
</script>

<?php endif; ?>
