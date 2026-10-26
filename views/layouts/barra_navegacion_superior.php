<?php
session_start();
require_once __DIR__ . '/../../lib/texto_helper.php';
require_once __DIR__ . '/../../lib/mantenimiento_helper.php';

/* MODO MANTENIMIENTO: los administradores (1/4) siguen dentro del
   sistema; el aviso les recuerda que los usuarios están bloqueados. */
$mttoActivo = bovs_mantenimiento_activo();
$mttoRol    = isset($_SESSION['rolx']) ? (int) $_SESSION['rolx'] : 0;
$mttoAdmin  = in_array($mttoRol, array(1, 4), true);

$cantidad = $_SESSION['maximo_registros'];
$cambiar  = false;
if ($cantidad <= 5000) {
    $cambiar = true;
}

$fundoActivoId     = isset($_SESSION['mfundos_id'])   ? (int)$_SESSION['mfundos_id']   : 0;
$fundoActivoNombre = isset($_SESSION['fundo_nombre']) ? trim((string)$_SESSION['fundo_nombre']) : '';
$fundoActivoNombre = bovs_nombre_mostrar($fundoActivoNombre);

// MODO SOPORTE (solo web): un administrador (rol 1/4) está dentro de un fundo.
$soporteActivo  = !empty($_SESSION['supervisor_mode']);
$soporteExpira  = isset($_SESSION['supervisor_expira']) ? (int)$_SESSION['supervisor_expira'] : 0;
$soporteDesde   = isset($_SESSION['supervisor_inicio']) ? (int)$_SESSION['supervisor_inicio'] : 0;

/* MODO DEMOSTRACIÓN: el usuario demo siempre ve "Comprar suscripción".
   El mensaje de WhatsApp lleva su usuario y fundo para que el
   administrador identifique qué cuenta demo debe importar. */
$demoSesion = !empty($_SESSION['demo']);
$urlCompraDemo = '';
if ($demoSesion) {
    require_once __DIR__ . '/../../lib/demo_helper.php';
    $urlCompraDemo = bovs_demo_whatsapp_url(array(
        'usuario' => isset($_SESSION['usuariox']) ? (string) $_SESSION['usuariox'] : '',
        'fundo'   => $fundoActivoNombre,
    ));
}
?>
<style>
/* ============================================================
   AVISO DE MODO SOPORTE EN LA BARRA SUPERIOR
   ============================================================ */
.navbar-soporte {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-left: 10px;
    padding: 4px 6px 4px 10px;
    border-radius: 9px;
    background: linear-gradient(135deg, #7a3b12, #a8561d);
    box-shadow: 0 3px 10px rgba(122, 59, 18, .28);
    white-space: nowrap;
    /* No se comprime: en pantallas angostas se oculta el card del fundo
       para dejarle espacio (el nombre sigue visible en el menú lateral) */
    flex: 0 0 auto;
    position: relative;
    z-index: 3;
}
.navbar-soporte__chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #fff3e4;
    font-size: 10.5px;
    font-weight: 800;
    letter-spacing: .7px;
    text-transform: uppercase;
}
.navbar-soporte__salir {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 9px;
    border: 1px solid rgba(255, 255, 255, .6);
    border-radius: 6px;
    background: rgba(255, 255, 255, .16);
    color: #ffffff;
    font-size: 10.5px;
    font-weight: 700;
    line-height: 1.2;
    cursor: pointer;
    transition: background .15s ease;
}
.navbar-soporte__salir:hover { background: rgba(255, 255, 255, .3); }
.navbar-soporte__salir:disabled { opacity: .65; cursor: progress; }
/* En pantallas donde la barra no tiene espacio para ambos, el card del
   fundo se oculta mientras dura el modo soporte (el nombre del fundo se
   ve en el bloque "MODO SOPORTE" del menú lateral). */
@media (max-width: 991px) {
    .navbar-active-fundo--soporte { display: none !important; }
}

@media (max-width: 767px) {
    .navbar-soporte { margin-left: 4px; padding: 3px 4px 3px 7px; gap: 5px; }
    .navbar-soporte__chip { font-size: 9px; letter-spacing: .4px; }
    .navbar-soporte__chip .navbar-soporte__texto { display: none; }
    .navbar-soporte__salir { padding: 4px 7px; font-size: 10px; }
    .navbar-soporte__salir .navbar-soporte__salir-texto { display: none; }
}
/* ============================================================
   NAVBAR SOVCA — Card de fundo ajustado al contenido
   ============================================================ */

.navbar-bovs {
    /* Tamaños de la zona derecha (un solo lugar para ajustarlos).
       El badge del contador queda en ~52% del icono: a un 15-20%
       (≈3-4px) el número no sería legible; ajuste estas dos variables
       si se quiere aún más pequeño. */
    --bovs-icono-nav: 21px;          /* antes 26px (−20%) */
    --bovs-badge-nav: 11px;          /* antes 16px */
    --bovs-badge-font: 7.5px;        /* antes 9.5px */
    --bovs-badge-anillo: rgba(255, 213, 79, 0.45);

    background: linear-gradient(135deg, #0d3d24 0%, #1a5c3a 40%, #23704a 75%, #2c8a56 100%) !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.10) !important;
    box-shadow: 0 2px 14px rgba(0, 0, 0, 0.18) !important;
    min-height: 52px !important;
    height: 52px !important;
    display: flex !important;
    align-items: center !important;
    padding: 0 !important;
    position: relative;
    z-index: 1030;
}
.navbar-bovs::after {
    content: "";
    position: absolute; left: 0; right: 0; bottom: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent 0%, rgba(127,208,122,.45) 50%, transparent 100%);
    pointer-events: none;
}

/* ---- Sidebar toggle ---- */
.navbar-bovs .sidebar-toggle {
    color: #fff !important;
    height: 52px !important;
    padding: 0 16px !important;
    border-right: 1px solid rgba(255, 255, 255, 0.08);
    transition: background .2s ease;
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    float: none !important;
}
.navbar-bovs .sidebar-toggle:hover {
    background: rgba(255, 255, 255, 0.10) !important;
    color: #fff !important;
}
.navbar-bovs .sidebar-toggle::before { font-size: 16px; }

/* ---- Logo mini ---- */
.navbar-bovs .navbar-brand-mini {
    display: inline-flex;
    align-items: center;
    height: 52px;
    padding: 0 14px;
    background: rgba(0, 0, 0, 0.15);
    border-right: 1px solid rgba(255, 255, 255, 0.08);
    flex: 0 0 auto;
    float: none !important;
}
.navbar-bovs .navbar-brand-mini img {
    display: block; height: 32px; width: auto;
    filter: drop-shadow(0 1px 3px rgba(0, 0, 0, 0.35));
}

/* ============================================================
   FUNDO ACTIVO
   El contenedor se ajusta al ANCHO DEL NOMBRE y el nombre se
   ajusta al CONTENEDOR (se recorta con puntos suspensivos).
   ============================================================ */
.navbar-active-fundo {
    /* Es el elemento DINÁMICO de la barra: se ajusta al nombre y al
       espacio libre que deja la zona derecha (que va fija a la derecha) */
    flex: 0 1 auto;
    width: auto;
    min-width: 152px;                /* mínimo legible (nombre muy corto) */
    max-width: 100%;                 /* el espacio disponible lo define la barra */

    height: 42px;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 4px 16px 4px 5px;
    margin: 0 12px;
    background: #ffffff;
    border-radius: 10px;
    box-shadow:
        0 3px 10px rgba(0, 0, 0, 0.18),
        0 1px 0 rgba(255, 255, 255, 0.8) inset;
    overflow: hidden;
    position: relative;
    z-index: 1;
}

.navbar-active-fundo-icon {
    display: inline-flex;
    flex: 0 0 32px;
    align-items: center;
    justify-content: center;
    width: 32px; height: 32px;
    color: #fff;
    background: linear-gradient(135deg, #1a5c3a 0%, #2c8a56 100%);
    border-radius: 7px;
    font-size: 14px;
    box-shadow: 0 2px 6px rgba(26, 92, 58, 0.35);
}
.navbar-active-fundo-text {
    display: flex;
    flex-direction: column;
    flex: 1 1 auto;
    min-width: 64px;                 /* nunca desaparece: mínimo legible */
    line-height: 1.1;
    text-align: left;
    overflow: hidden;
}
.navbar-active-fundo-text small {
    overflow: hidden;
    color: #6b7d70;
    font-size: 9.5px;
    font-weight: 700;
    text-overflow: ellipsis;
    text-transform: uppercase;
    letter-spacing: .6px;
    white-space: nowrap;
    flex-shrink: 1;                  /* también se recorta en pantallas angostas */
}

/* ══════════════════════════════════════════════════════════════
   NOMBRE DEL FUNDO — recorte con puntos suspensivos
   ══════════════════════════════════════════════════════════════ */
.navbar-active-fundo-text strong {
    display: block;
    width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: #0d3d24;
    font-size: 16px;
    font-weight: 800;
    line-height: 1.15;
    letter-spacing: .2px;
}
/* El tipo de fundo (si viene) se muestra suavemente antes del nombre */
.navbar-active-fundo-text strong #navbar-active-fundo-type {
    color: #55675c;
    font-weight: 700;
}

/* ============================================================
   ZONA DERECHA
   ============================================================ */
.navbar-custom-menu {
    /* ZONA FIJA A LA DERECHA: mejora tu plan, iconos, notificaciones y
       acceso al perfil siempre arriba a la derecha. El margen automático
       absorbe el espacio sobrante y empuja el grupo al extremo derecho. */
    flex: 0 0 auto;
    display: flex !important;
    align-items: center;
    float: none !important;
    margin-left: auto !important;
    position: relative;
    z-index: 2;
}
.navbar-custom-menu > .nav {
    display: flex !important;
    align-items: center;
    float: none !important;
    margin: 0 !important;
}
.navbar-custom-menu > .nav > li {
    display: inline-flex;
    align-items: center;
    float: none !important;
}

/* Botón "Mejora tu Plan" */
.btn-upgrade-plan-item {
    display: inline-flex;
    align-items: center;
    height: 52px;
    padding: 0 8px;
}
.btn-upgrade-plan {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 7px 16px;
    font-size: 12.5px;
    font-weight: 800;
    letter-spacing: .3px;
    color: #0d3d24 !important;
    background: linear-gradient(135deg, #ffd54f 0%, #ffb300 100%);
    border-radius: 20px;
    border: none;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.22),
                0 0 0 1px rgba(255, 255, 255, 0.35) inset;
    text-decoration: none !important;
    transition: all 0.25s cubic-bezier(0.25, 0.8, 0.25, 1);
    position: relative;
    overflow: hidden;
    white-space: nowrap;
}
.btn-upgrade-plan::before {
    content: '';
    position: absolute;
    top: -50%; left: -50%;
    width: 200%; height: 200%;
    background: linear-gradient(60deg, transparent 30%, rgba(255, 255, 255, 0.55) 50%, transparent 70%);
    transform: rotate(30deg);
    animation: shine-sweep 3.5s infinite;
}
.btn-upgrade-plan:hover {
    transform: translateY(-2px) scale(1.03);
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.28),
                0 0 0 1px rgba(255, 255, 255, 0.45) inset;
    color: #0d3d24 !important;
}
.btn-upgrade-plan .badge-star {
    color: #b71c1c;
    font-size: 14px;
    text-shadow: 0 1px 2px rgba(255, 255, 255, 0.5);
    animation: pulse-star 1.8s infinite alternate;
}
.btn-upgrade-plan .icon-wa { font-size: 16px; color: #0d5c2a; }
@keyframes shine-sweep { 0% { left: -100%; } 20% { left: 100%; } 100% { left: 100%; } }
@keyframes pulse-star { 0% { transform: scale(1); } 100% { transform: scale(1.28); } }

/* Botón "Comprar suscripción" del usuario DEMO */
.btn-demo-comprar .btn-upgrade-plan {
    color: #ffffff !important;
    background: linear-gradient(135deg, #2c8a56 0%, #1b6e43 55%, #14532d 100%);
    box-shadow: 0 3px 10px rgba(0, 0, 0, .25),
                0 0 0 1px rgba(255, 255, 255, .28) inset;
}
.btn-demo-comprar .btn-upgrade-plan:hover {
    color: #ffffff !important;
}
.btn-demo-comprar .btn-upgrade-plan .badge-star {
    color: #ffe082;
    text-shadow: 0 1px 2px rgba(0, 0, 0, .35);
}
.btn-demo-comprar .btn-upgrade-plan .icon-wa {
    color: #d9ffe4;
}

/* Iconos derechos */
.navbar-custom-menu > .nav > li > a {
    color: #ffffff !important;
    height: 52px !important;
    padding: 10px 16px !important;
    line-height: 1 !important;
    font-size: 15px;
    transition: background .2s ease;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    position: relative;
    background: transparent !important;
}
.navbar-custom-menu > .nav > li > a:hover,
.navbar-custom-menu > .nav > li.open > a {
    background: rgba(255, 255, 255, 0.10) !important;
    color: #ffffff !important;
}

/* ⭐ Iconos de mensajes y notificaciones */
.navbar-custom-menu .notifications-menu > a,
.navbar-custom-menu .messages-menu > a {
    background: transparent !important;
    padding: 8px 20px !important;
}
.navbar-custom-menu .notifications-menu > a > i,
.navbar-custom-menu .messages-menu > a > i,
.navbar-custom-menu .notifications-menu > a > .fa,
.navbar-custom-menu .messages-menu > a > .fa {
    font-size: var(--bovs-icono-nav, 21px) !important;
    color: #FFD54F !important;
    background: transparent !important;
    border: none !important;
    width: auto !important;
    height: auto !important;
    padding: 0 !important;
    margin: 0 !important;
    box-shadow: none !important;
    line-height: 1 !important;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.45),
                 0 0 8px rgba(255, 213, 79, 0.35);
    transition: color .2s ease, text-shadow .2s ease, transform .2s ease;
}
.navbar-custom-menu .notifications-menu > a:hover > i,
.navbar-custom-menu .messages-menu > a:hover > i,
.navbar-custom-menu .notifications-menu.open > a > i,
.navbar-custom-menu .messages-menu.open > a > i {
    color: #FFEB3B !important;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.55),
                 0 0 14px rgba(255, 235, 59, 0.75);
    transform: scale(1.10);
}
.navbar-custom-menu .notifications-menu > a:hover,
.navbar-custom-menu .messages-menu > a:hover {
    background: rgba(255, 255, 255, 0.08) !important;
}

/* Badges numéricos */
.navbar-custom-menu .navbar-nav > li > a > .label {
    font-size: var(--bovs-badge-font, 7.5px) !important;
    font-weight: 800 !important;
    padding: 0 2px !important;
    min-width: var(--bovs-badge-nav, 11px) !important;
    height: var(--bovs-badge-nav, 11px) !important;
    line-height: var(--bovs-badge-nav, 11px) !important;
    top: 4px !important;
    right: 8px !important;
    border-radius: 10px !important;
    background: #d63031 !important;
    color: #fff !important;
    box-shadow: 0 0 0 1px #0d3d24,
                0 0 0 2px var(--bovs-badge-anillo, rgba(255, 213, 79, 0.45));
    text-shadow: none;
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
}
.navbar-custom-menu .navbar-nav > li > a > .label.label-success,
.navbar-custom-menu .navbar-nav > li > a > .label.label-warning {
    background: #d63031 !important;
}

/* Separador vertical */
.navbar-custom-menu .navbar-nav > li.nav-sep {
    width: 1px;
    height: 28px;
    background: rgba(255, 255, 255, 0.15);
    margin: 12px 6px;
    padding: 0;
    pointer-events: none;
}

/* Anular AdminLTE */
.navbar-bovs > * { float: none !important; }
.navbar-bovs .navbar-custom-menu { float: none !important; }
.navbar-bovs .navbar-custom-menu > ul { float: none !important; }
.navbar-bovs .navbar-custom-menu > ul > li { float: none !important; }

/* ============================================================
   RESPONSIVE
   ============================================================ */
@media (max-width: 1199px) {
    .navbar-custom-menu .btn-upgrade-plan span { font-size: 11.5px; }
    .navbar-custom-menu .btn-upgrade-plan { padding: 6px 12px; }
}

@media (max-width: 991px) {
    .btn-upgrade-plan-item { display: none !important; }
    /* El botón de compra del usuario demo SÍ se mantiene visible */
    .btn-demo-comprar.btn-upgrade-plan-item { display: inline-flex !important; height: 46px; padding: 0 4px; }
    .btn-demo-comprar .btn-upgrade-plan { padding: 6px 11px; }
    .btn-demo-comprar .btn-upgrade-plan span { font-size: 11px; }
    .navbar-custom-menu .nav-sep { display: none !important; }
    .navbar-active-fundo { margin: 0 8px; min-width: 140px; }
}

@media (max-width: 767px) {
    /* En móvil se reduce todo proporcionalmente */
    .navbar-bovs {
        --bovs-icono-nav: 18px;
        --bovs-badge-nav: 10px;
        --bovs-badge-font: 7px;
    }
    .navbar-bovs .navbar-brand-mini { display: none !important; }
    .navbar-active-fundo {
        margin: 0 6px;
        padding: 3px 10px 3px 4px;
        height: 38px;
        gap: 8px;
        min-width: 116px;
    }
    .navbar-active-fundo-icon {
        flex-basis: 26px;
        width: 26px; height: 26px;
        font-size: 12px;
        border-radius: 6px;
    }
    .navbar-active-fundo-text strong { font-size: 13px; }
    .navbar-active-fundo-text small { font-size: 8px; letter-spacing: .4px; }
    .navbar-custom-menu > .nav > li > a {
        padding: 10px 12px !important;
    }
    .navbar-custom-menu .notifications-menu > a > i,
    .navbar-custom-menu .messages-menu > a > i {
        font-size: var(--bovs-icono-nav, 18px) !important;
    }
    .navbar-custom-menu .navbar-nav > li > a > .label {
        top: 3px !important;
        right: 7px !important;
    }
}

@media (max-width: 480px) {
    .navbar-active-fundo-text strong { font-size: 12px; }
    .navbar-active-fundo-text small { display: none; }
    .navbar-active-fundo { min-width: 104px; }
}
</style>

<nav class="navbar navbar-static-top navbar-bovs" role="navigation">

    <a href="#" class="sidebar-toggle" data-toggle="offcanvas" role="button">
        <span class="sr-only">Toggle navigation</span>
    </a>

    <div class="pull-left navbar-brand-mini">
        <img src="../../dist/img/sovca.png" alt="SOVCA">
    </div>

    <?php if ($fundoActivoId > 0) { ?>
        <div class="navbar-active-fundo<?php echo $soporteActivo ? ' navbar-active-fundo--soporte' : ''; ?>" id="navbar-active-fundo" aria-label="Fundo activo"
             title="<?php echo htmlspecialchars($fundoActivoNombre !== '' ? $fundoActivoNombre : 'Fundo activo', ENT_QUOTES, 'UTF-8'); ?>">
            <span class="navbar-active-fundo-icon"><i class="fa fa-building-o" aria-hidden="true"></i></span>
            <span class="navbar-active-fundo-text">
                <small>Fundo activo</small>
                <strong id="navbar-active-fundo-linea"><span id="navbar-active-fundo-type"></span><span id="navbar-active-fundo-name"><?php echo htmlspecialchars($fundoActivoNombre, ENT_QUOTES, 'UTF-8'); ?></span></strong>
            </span>
        </div>
    <?php } ?>

    <?php if ($mttoActivo && $mttoAdmin) { ?>
        <div class="navbar-soporte navbar-mtto"
             title="Modo mantenimiento activo: solo los administradores del sistema pueden acceder. Los productores y ayudantes están bloqueados. Abre la gestión para desactivarlo.">
            <span class="navbar-soporte__chip">
                <i class="fa fa-wrench" aria-hidden="true"></i>
                <span class="navbar-soporte__texto">Mantenimiento</span>
            </span>
            <a class="navbar-soporte__salir" href="../mantenimiento/">
                <i class="fa fa-cog" aria-hidden="true"></i>
                <span class="navbar-soporte__salir-texto">Gestionar</span>
            </a>
        </div>
    <?php } ?>

    <?php if ($soporteActivo) { ?>
        <div class="navbar-soporte"
             title="Modo soporte: estás dentro de este fundo para revisar y corregir sus datos. Solo versión web. El acceso queda registrado.">
            <span class="navbar-soporte__chip">
                <i class="fa fa-user-secret" aria-hidden="true"></i>
                <span class="navbar-soporte__texto">Modo soporte</span>
            </span>
            <button type="button" class="navbar-soporte__salir js-salir-soporte">
                <i class="fa fa-sign-out" aria-hidden="true"></i>
                <span class="navbar-soporte__salir-texto">Salir</span>
            </button>
        </div>
    <?php } ?>

    <div class="navbar-custom-menu">
        <ul class="nav navbar-nav">

            <?php
            if ($demoSesion) {
            ?>
                <li class="btn-upgrade-plan-item btn-demo-comprar oculto-impresion">
                    <a href="<?= htmlspecialchars($urlCompraDemo, ENT_QUOTES, 'UTF-8') ?>"
                       class="btn-upgrade-plan"
                       target="_blank"
                       rel="noopener"
                       title="Compra tu suscripción y te migramos los datos de tu cuenta de demostración">
                        <i class="fa fa-cart-shopping badge-star"></i>
                        <span>Comprar suscripción</span>
                        <i class="fa fa-whatsapp icon-wa"></i>
                    </a>
                </li>
                <li class="nav-sep oculto-impresion"></li>
            <?php
            } elseif (( $_SESSION['tipo_ganaderia'] == 1 ) && ($cambiar)) {
            ?>
                <li class="btn-upgrade-plan-item hidden-xs oculto-impresion">
                    <a href="https://wa.me/584268205177?text=Hola,%20deseo%20obtener%20informaci%C3%B3n%20para%20mejorar%20mi%20Plan%20en%20Sistema%20de%20Gesti%C3%B3n%20OVINA%20Y%20CAPRINA"
                       class="btn-upgrade-plan"
                       target="_blank"
                       title="Mejora tu plan para ampliar el límite de registros y acceder a más beneficios">
                        <i class="fa fa-star badge-star"></i>
                        <span>¡Mejora tu Plan Ahora!</span>
                        <i class="fa fa-whatsapp icon-wa"></i>
                    </a>
                </li>
                <li class="nav-sep hidden-xs"></li>
            <?php
            }

            @session_start();
            @$id_sesion = $_SESSION['idsessionx'];
            @$rol = $_SESSION['rolx'];

            if (!empty($id_sesion) && ($rol == 2)) {
                include_once("menu_notificaciones.php");
            }

            include_once("menu_perfil_usuario.php");
            ?>
        </ul>
    </div>
</nav>

<?php if ($fundoActivoId > 0) { ?>
    <script type="text/javascript">
        (function($) {
            /* ══════════════════════════════════════════════════
               AJUSTE DEL CARD
               El ancho del contenedor y el recorte del nombre los
               resuelve el CSS (flex + text-overflow: ellipsis), así
               que no se necesita medir ni animar nada desde JS.
               ══════════════════════════════════════════════════ */

            /* ══════════════════════════════════════════════════
               REFRESCO REMOTO DEL HEADER
               ══════════════════════════════════════════════════ */
            /* Mismo criterio que bovs_nombre_mostrar() en
               lib/texto_helper.php: si el nombre viene en mayúsculas
               pero con acentos en minúscula ("PéREZ"), se normaliza
               completo. Si ya tiene capitalización normal, no se toca. */
            function bovsNormalizarNombre(txt) {
                txt = String(txt || '').replace(/\s+/g, ' ').trim();
                if (txt && !/[a-z]/.test(txt)) {
                    return txt.toLocaleUpperCase('es');
                }
                return txt;
            }

            function bovsRefrescarFundoHeader() {
                return $.post('../../controllers/mfundos_controller.php',
                    { action: 'get_active_fundo_header' }, null, 'json')
                    .done(function(respuesta) {
                        if (!respuesta || !respuesta.ok || !respuesta.nombre) return;

                        // Colapsa espacios y corrige acentos en mayúsculas
                        var nombre = bovsNormalizarNombre(respuesta.nombre);
                        var tipo   = String(respuesta.tipo_fundo || '').trim();

                        $('#navbar-active-fundo-type').text(tipo ? tipo + ' ' : '');
                        $('#navbar-active-fundo-name').text(nombre);

                        // El nombre visible puede recortarse: el texto completo
                        // queda en la etiqueta accesible y en el tooltip
                        var etiqueta = (tipo || 'Fundo') + ' ' + nombre;
                        $('#navbar-active-fundo').attr('aria-label', etiqueta).attr('title', etiqueta);
                    });
            }
            window.bovsRefrescarFundoHeader = bovsRefrescarFundoHeader;

            /* ══════════════════════════════════════════════════
               INIT
               ══════════════════════════════════════════════════ */
            $(function() {
                // El nombre se refresca desde el servidor; el CSS ajusta el
                // tamaño del contenedor y recorta el texto si no cabe
                bovsRefrescarFundoHeader();
            });

            $(document).on('bovs:fundo-cambiado', function() { bovsRefrescarFundoHeader(); });
        })(jQuery);
    </script>
<?php } ?>

<?php if ($soporteActivo) { ?>
    <script type="text/javascript">
        (function($) {
            /* ══════════════════════════════════════════════════
               SALIR DEL MODO SOPORTE
               Delegado para cubrir el botón de la barra superior y el
               del menú lateral. Al salir se restaura el contexto
               anterior (el administrador vuelve a su propio contexto).
               ══════════════════════════════════════════════════ */
            function bovsAvisoSoporte(msg) {
                if ($.alert) {
                    $.alert({ title: 'Modo soporte', content: msg });
                } else {
                    alert(msg);
                }
            }

            $(document).on('click', '.js-salir-soporte', function(e) {
                e.preventDefault();

                var $botones = $('.js-salir-soporte');
                if ($botones.data('bovsBusy')) return;
                $botones.data('bovsBusy', true).prop('disabled', true);

                function restaurar() {
                    $botones.data('bovsBusy', false).prop('disabled', false);
                }

                $.post('../../controllers/mfundos_controller.php',
                    { action: 'salir_supervisor' }, null, 'json')
                    .done(function(respuesta) {
                        if (respuesta && respuesta.ok) {
                            window.location.href = '../inicio/';
                            return;
                        }
                        restaurar();
                        bovsAvisoSoporte((respuesta && respuesta.msg)
                            ? respuesta.msg
                            : 'No se pudo salir del modo soporte.');
                    })
                    .fail(function() {
                        restaurar();
                        bovsAvisoSoporte('No se pudo salir del modo soporte. Verifique la conexión.');
                    });
            });

            /* ══════════════════════════════════════════════════
               CIERRE AUTOMÁTICO (tiempo límite del modo soporte)
               Se cierra en el servidor y se vuelve al inicio.
               ══════════════════════════════════════════════════ */
            var expira = <?php echo (int)$soporteExpira; ?>;
            if (expira > 0) {
                var restante = (expira * 1000) - Date.now();
                if (restante < 1000) restante = 1000;
                // setTimeout no admite más de ~24.8 días; el límite real es 2 h
                if (restante > 2147483000) restante = 2147483000;

                setTimeout(function() {
                    $.post('../../controllers/mfundos_controller.php',
                        { action: 'salir_supervisor' }, null, 'json')
                        .always(function() { window.location.href = '../inicio/'; });
                }, restante);
            }
        })(jQuery);
    </script>
<?php } ?>