<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../lib/texto_helper.php';

$imagen      = $_SESSION['imagen_perfilx'] ?? '';
$nombre      = bovs_nombre_mostrar($_SESSION['nombresx'] ?? 'Usuario');
$nombre_rol  = $_SESSION['nombre_rolx']    ?? 'Sin rol';
$creado      = $_SESSION['creadox']        ?? '—';
$usuario     = $_SESSION['usuariox']       ?? '';
$rolId       = (int)($_SESSION['rolx']     ?? 0);

/* Iniciales del nombre */
$iniciales = '?';
$partes = preg_split('/\s+/', trim($nombre));
if (count($partes) >= 2) {
    $iniciales = strtoupper(mb_substr($partes[0], 0, 1) . mb_substr($partes[1], 0, 1));
} elseif (!empty($nombre)) {
    $iniciales = strtoupper(mb_substr($nombre, 0, 2));
}

/* Clasificación del rol BOVS */
$rolClase = 'rol-generico';
$rolEtiqueta = 'USUARIO';
if ($rolId === 1)      { $rolClase = 'rol-admin';   $rolEtiqueta = 'ADMIN'; }
elseif ($rolId === 2)  { $rolClase = 'rol-superv';  $rolEtiqueta = 'SUPERVISOR'; }
elseif ($rolId === 3)  { $rolClase = 'rol-colab';   $rolEtiqueta = 'COLABORADOR'; }
elseif ($rolId === 4)  { $rolClase = 'rol-program'; $rolEtiqueta = 'PROGRAMADOR'; }
elseif (in_array($rolId, [5, 6, 7], true)) { $rolClase = 'rol-externo'; $rolEtiqueta = 'EXTERNO'; }

$rutaFoto = '../../dist/img/fotos_usuarios/';
$tieneFoto = !empty($imagen) && file_exists(__DIR__ . '/../../dist/img/fotos_usuarios/' . $imagen);
?>

<li class="dropdown user user-menu user-menu-bovs">

    <!-- ============ TRIGGER ============ -->
    <a href="#" class="dropdown-toggle user-trigger" data-toggle="dropdown" aria-expanded="false">

        <?php if ($tieneFoto): ?>
            <img src="<?= $rutaFoto . htmlspecialchars($imagen, ENT_QUOTES, 'UTF-8') ?>"
                 class="user-image" alt="Perfil">
        <?php else: ?>
            <span class="user-image user-image-initials"><?= htmlspecialchars($iniciales, ENT_QUOTES, 'UTF-8') ?></span>
        <?php endif; ?>

        <span class="hidden-xs user-name"><?= htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') ?></span>

        <i class="fa fa-angle-down user-caret hidden-xs"></i>
    </a>

    <!-- ============ DROPDOWN ============ -->
    <ul class="dropdown-menu user-dropdown">

        <!-- Header -->
        <li class="user-header user-header-bovs">

            <span class="user-header-deco user-header-deco-1"></span>
            <span class="user-header-deco user-header-deco-2"></span>

            <div class="user-avatar-wrap">
                <?php if ($tieneFoto): ?>
                    <img src="<?= $rutaFoto . htmlspecialchars($imagen, ENT_QUOTES, 'UTF-8') ?>"
                         class="img-circle user-avatar-img" alt="Perfil">
                <?php else: ?>
                    <span class="img-circle user-avatar-initials"><?= htmlspecialchars($iniciales, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
                <span class="user-status-dot" title="En línea"></span>
            </div>

            <div class="user-header-info">
                <p class="user-header-nombre"><?= htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') ?></p>

                <?php if (!empty($usuario)): ?>
                    <p class="user-header-username">@<?= htmlspecialchars(bovs_texto_mayusculas($usuario), ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <span class="user-rol-chip <?= $rolClase ?>">
                    <i class="fa fa-shield"></i>
                    <?= htmlspecialchars($rolEtiqueta, ENT_QUOTES, 'UTF-8') ?>
                </span>

                <p class="user-header-rol"><?= htmlspecialchars($nombre_rol, ENT_QUOTES, 'UTF-8') ?></p>

                <p class="user-header-desde">
                    <i class="fa fa-calendar-check-o"></i>
                    Activo desde el <?= htmlspecialchars($creado, ENT_QUOTES, 'UTF-8') ?>
                </p>
            </div>
        </li>

        <!-- Footer -->
        <li class="user-footer user-footer-bovs">
            <a href="../usuarios/perfil" class="user-btn user-btn-perfil">
                <i class="fa fa-user-circle"></i>
                <span>Mi Perfil</span>
            </a>
            <a href="../logout" class="user-btn user-btn-salir">
                <i class="fa fa-sign-out"></i>
                <span>Salir</span>
            </a>
        </li>
    </ul>
</li>

<style>
/* ============================================================
   TRIGGER
   ============================================================ */
.navbar-custom-menu .user-menu-bovs > .user-trigger {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 16px !important;
    color: #fff !important;
    height: 50px;
    line-height: 1;
    transition: background .2s ease;
    text-decoration: none;
    position: relative;
}
.navbar-custom-menu .user-menu-bovs > .user-trigger:hover,
.navbar-custom-menu .user-menu-bovs.open > .user-trigger {
    background: rgba(255, 255, 255, 0.10);
}

.navbar-custom-menu .user-menu-bovs .user-image {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid rgba(37, 211, 102, 0.85);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.25);
    flex-shrink: 0;
    transition: transform .25s cubic-bezier(.34, 1.56, .64, 1);
}
.navbar-custom-menu .user-menu-bovs > .user-trigger:hover .user-image {
    transform: scale(1.06);
}

.navbar-custom-menu .user-menu-bovs .user-image-initials {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #25d366 0%, #1b8f4c 100%);
    color: #ffffff;
    font-size: 13px;
    font-weight: 800;
    letter-spacing: .5px;
    text-transform: uppercase;
}

.navbar-custom-menu .user-menu-bovs .user-name {
    font-size: 13px;
    font-weight: 700;
    color: #fff;
    letter-spacing: .3px;
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.navbar-custom-menu .user-menu-bovs .user-caret {
    font-size: 12px;
    color: rgba(255, 255, 255, 0.75);
    margin-left: -4px;
    transition: transform .25s ease;
}
.navbar-custom-menu .user-menu-bovs.open .user-caret {
    transform: rotate(180deg);
}

/* ============================================================
   DROPDOWN
   ============================================================ */
.navbar-custom-menu .user-menu-bovs .user-dropdown {
    min-width: 320px;
    padding: 0;
    border-radius: 14px;
    border: none;
    box-shadow:
        0 20px 45px rgba(15, 23, 42, 0.22),
        0 6px 15px rgba(35, 78, 53, 0.18);
    overflow: visible;
    margin-top: 8px;
    animation: userDropIn .28s cubic-bezier(.34, 1.56, .64, 1);
}
@keyframes userDropIn {
    0%   { opacity: 0; transform: translateY(-8px) scale(.97); }
    100% { opacity: 1; transform: translateY(0) scale(1); }
}

.navbar-custom-menu .user-menu-bovs .user-dropdown::before {
    content: "";
    position: absolute;
    top: -8px;
    right: 30px;
    width: 16px;
    height: 16px;
    background: #234e35;
    transform: rotate(45deg);
    border-radius: 3px;
}

/* ============================================================
   HEADER
   ============================================================ */
.navbar-custom-menu .user-menu-bovs .user-header-bovs {
    position: relative;
    padding: 22px 20px 18px;
    background: linear-gradient(135deg, #1b3a24 0%, #234e35 55%, #2f6b45 100%);
    color: #fff;
    overflow: hidden;
    text-align: center;
    height: auto !important;
    min-height: 0 !important;
    display: block !important;
    border-radius: 14px 14px 0 0;
}

.user-header-deco {
    position: absolute;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.06);
    pointer-events: none;
}
.user-header-deco-1 {
    width: 140px;
    height: 140px;
    top: -60px;
    right: -40px;
}
.user-header-deco-2 {
    width: 100px;
    height: 100px;
    bottom: -50px;
    left: -30px;
    background: rgba(37, 211, 102, 0.12);
}

.user-avatar-wrap {
    position: relative;
    width: 82px;
    height: 82px;
    margin: 0 auto 12px;
    display: inline-block;
}
.user-avatar-img,
.user-avatar-initials {
    width: 82px;
    height: 82px;
    border-radius: 50%;
    border: 3px solid #fff;
    box-shadow:
        0 8px 22px rgba(0, 0, 0, 0.30),
        0 0 0 1px rgba(255, 255, 255, 0.15);
    object-fit: cover;
    display: flex;
    align-items: center;
    justify-content: center;
}
.user-avatar-initials {
    background: linear-gradient(135deg, #25d366 0%, #1b8f4c 100%);
    color: #ffffff;
    font-size: 28px;
    font-weight: 800;
    letter-spacing: 1px;
    text-transform: uppercase;
}

.user-status-dot {
    position: absolute;
    bottom: 3px;
    right: 3px;
    width: 16px;
    height: 16px;
    background: #25d366;
    border: 3px solid #fff;
    border-radius: 50%;
    box-shadow: 0 0 0 0 rgba(37, 211, 102, 0.7);
    animation: userStatusPulse 2s infinite;
}
@keyframes userStatusPulse {
    0%   { box-shadow: 0 0 0 0 rgba(37, 211, 102, 0.7); }
    60%  { box-shadow: 0 0 0 10px rgba(37, 211, 102, 0); }
    100% { box-shadow: 0 0 0 0 rgba(37, 211, 102, 0); }
}

.user-header-info { position: relative; z-index: 1; }

.user-header-nombre {
    margin: 0 0 2px !important;
    font-size: 15px !important;
    font-weight: 800 !important;
    color: #fff !important;
    letter-spacing: .3px;
    line-height: 1.25 !important;
    white-space: normal !important;
    padding: 0 !important;
    text-align: center !important;
    word-break: break-word;
}

.user-header-username {
    margin: 0 0 10px !important;
    font-size: 11.5px !important;
    font-weight: 600 !important;
    color: rgba(255, 255, 255, 0.75) !important;
    font-family: 'Consolas', 'Monaco', monospace;
    letter-spacing: .5px;
    text-align: center !important;
}

.user-rol-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 9.5px;
    font-weight: 800;
    letter-spacing: 1px;
    text-transform: uppercase;
    margin-bottom: 6px;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
}
.user-rol-chip i { font-size: 9px; }

.user-rol-chip.rol-admin   { background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%); color: #ffffff; }
.user-rol-chip.rol-superv  { background: linear-gradient(135deg, #60a5fa 0%, #3b82f6 100%); color: #ffffff; }
.user-rol-chip.rol-colab   { background: linear-gradient(135deg, #a78bfa 0%, #8b5cf6 100%); color: #ffffff; }
.user-rol-chip.rol-program { background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); color: #78350f; }
.user-rol-chip.rol-externo { background: rgba(255, 255, 255, 0.20); color: #ffffff; border: 1px solid rgba(255, 255, 255, 0.30); }
.user-rol-chip.rol-generico{ background: rgba(255, 255, 255, 0.15); color: #ffffff; }

.user-header-rol {
    margin: 0 0 8px !important;
    font-size: 12px !important;
    font-weight: 700 !important;
    color: #7fe2a3 !important;
    letter-spacing: .4px;
    text-align: center !important;
    text-transform: uppercase;
}

.user-header-desde {
    margin: 6px 0 0 !important;
    font-size: 10.5px !important;
    color: rgba(255, 255, 255, 0.75) !important;
    font-weight: 600;
    letter-spacing: .3px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    background: rgba(255, 255, 255, 0.10);
    border-radius: 12px;
}
.user-header-desde i { font-size: 10px; }

/* ============================================================
   FOOTER
   ============================================================ */
.navbar-custom-menu .user-menu-bovs .user-footer-bovs {
    padding: 12px 14px 14px !important;
    background: #f6faf7 !important;
    display: flex !important;
    flex-direction: row !important;
    gap: 8px !important;
    border-top: 1px solid #e3efe7 !important;
    border-radius: 0 0 14px 14px !important;
    height: auto !important;
    min-height: 0 !important;
    visibility: visible !important;
    opacity: 1 !important;
    overflow: visible !important;
}

.navbar-custom-menu .user-menu-bovs .user-footer-bovs .user-btn,
.navbar-custom-menu .user-menu-bovs .user-footer-bovs a.user-btn,
.user-menu-bovs .user-footer-bovs .user-btn {
    flex: 1 1 0 !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 7px !important;
    padding: 10px 12px !important;
    border-radius: 9px !important;
    font-size: 12px !important;
    font-weight: 800 !important;
    letter-spacing: .4px !important;
    text-decoration: none !important;
    text-transform: uppercase !important;
    border: none !important;
    cursor: pointer !important;
    min-width: 0 !important;
    min-height: 40px !important;
    line-height: 1 !important;
    white-space: nowrap !important;
    visibility: visible !important;
    opacity: 1 !important;
    box-sizing: border-box !important;
}
.navbar-custom-menu .user-menu-bovs .user-footer-bovs .user-btn i {
    font-size: 13px !important;
    display: inline-block !important;
    visibility: visible !important;
}
.navbar-custom-menu .user-menu-bovs .user-footer-bovs .user-btn span {
    display: inline-block !important;
    visibility: visible !important;
}

/* Botón PERFIL — verde sólido */
.navbar-custom-menu .user-menu-bovs .user-footer-bovs .user-btn.user-btn-perfil,
.user-menu-bovs .user-footer-bovs .user-btn-perfil {
    background: linear-gradient(135deg, #2f6b45 0%, #1b3a24 100%) !important;
    background-color: #234e35 !important;
    color: #ffffff !important;
    box-shadow: 0 3px 10px rgba(35, 78, 53, 0.30) !important;
}
.navbar-custom-menu .user-menu-bovs .user-footer-bovs .user-btn.user-btn-perfil:hover,
.user-menu-bovs .user-footer-bovs .user-btn-perfil:hover {
    box-shadow: 0 5px 14px rgba(35, 78, 53, 0.45) !important;
    color: #ffffff !important;
    transform: translateY(-1px);
}

/* Botón SALIR — rojo suave */
.navbar-custom-menu .user-menu-bovs .user-footer-bovs .user-btn.user-btn-salir,
.user-menu-bovs .user-footer-bovs .user-btn-salir {
    background: #fef2f2 !important;
    background-color: #fef2f2 !important;
    color: #991b1b !important;
    border: 1px solid #fecaca !important;
}
.navbar-custom-menu .user-menu-bovs .user-footer-bovs .user-btn.user-btn-salir:hover,
.user-menu-bovs .user-footer-bovs .user-btn-salir:hover {
    background: #fee2e2 !important;
    border-color: #fca5a5 !important;
    color: #7f1d1d !important;
    transform: translateY(-1px);
}

/* Anular AdminLTE */
.navbar-custom-menu .user-menu-bovs .user-header,
.navbar-custom-menu .user-menu-bovs .user-footer {
    padding: 0 !important;
    min-height: 0 !important;
    height: auto !important;
}

/* ============================================================
   RESPONSIVE
   ============================================================ */
@media (max-width: 767px) {
    .navbar-custom-menu .user-menu-bovs .user-dropdown {
        min-width: 280px;
        right: 10px;
        left: auto;
    }
    .navbar-custom-menu .user-menu-bovs .user-dropdown::before { right: 24px; }
    .navbar-custom-menu .user-menu-bovs .user-header-bovs { padding: 18px 16px 16px; }
    .user-avatar-wrap,
    .user-avatar-img,
    .user-avatar-initials { width: 68px; height: 68px; }
    .user-avatar-initials { font-size: 22px; }
    .user-header-nombre { font-size: 14px !important; }
    .user-header-username { font-size: 10.5px !important; }
    .user-header-rol { font-size: 11px !important; }
    .user-header-desde { font-size: 10px !important; }
    .user-footer-bovs { padding: 10px 12px 12px !important; }
    .navbar-custom-menu .user-menu-bovs .user-footer-bovs .user-btn {
        font-size: 11px !important;
        padding: 9px 10px !important;
        min-height: 38px !important;
    }
    .navbar-custom-menu .user-menu-bovs .user-footer-bovs .user-btn i {
        font-size: 12px !important;
        margin-right: 2px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .user-status-dot { animation: none; }
    .user-dropdown { animation: none; }
    .user-image { transition: none; }
}
</style>