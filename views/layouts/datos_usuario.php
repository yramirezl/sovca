<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../lib/texto_helper.php';

$usuariox = $_SESSION['usuariox']       ?? 'Usuario';
$nombre   = bovs_nombre_mostrar($_SESSION['nombresx'] ?? $usuariox);
$imagen   = $_SESSION['imagen_perfilx'] ?? '';
$rolId    = (int)($_SESSION['rolx']     ?? 0);

/* Iniciales de fallback */
$iniciales = '?';
$partes = preg_split('/\s+/', trim($nombre));
if (count($partes) >= 2) {
    $iniciales = strtoupper(mb_substr($partes[0], 0, 1) . mb_substr($partes[1], 0, 1));
} elseif (!empty($nombre)) {
    $iniciales = strtoupper(mb_substr($nombre, 0, 2));
}

/* Etiqueta de rol abreviada */
$rolEtiqueta = 'USUARIO';
if ($rolId === 1)     $rolEtiqueta = 'ADMIN';
elseif ($rolId === 2) $rolEtiqueta = 'SUPERVISOR';
elseif ($rolId === 3) $rolEtiqueta = 'COLABORADOR';
elseif ($rolId === 4) $rolEtiqueta = 'PROGRAMADOR';
elseif (in_array($rolId, [5,6,7], true)) $rolEtiqueta = 'EXTERNO';

$rutaFoto  = '../../dist/img/fotos_usuarios/';
$tieneFoto = !empty($imagen) && file_exists(__DIR__ . '/../../dist/img/fotos_usuarios/' . $imagen);
?>
<div class="user-panel user-panel-bovs">

  <div class="pull-left image">
    <?php if ($tieneFoto): ?>
      <img src="<?= $rutaFoto . htmlspecialchars($imagen, ENT_QUOTES, 'UTF-8') ?>"
           class="img-circle user-panel-avatar" alt="Usuario">
    <?php else: ?>
      <span class="img-circle user-panel-initials"><?= htmlspecialchars($iniciales, ENT_QUOTES, 'UTF-8') ?></span>
    <?php endif; ?>
    <span class="user-panel-dot" title="En línea"></span>
  </div>

  <div class="pull-left info user-panel-info">
    <p class="user-panel-name" title="<?= htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') ?>">
      <?= htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') ?>
    </p>
    <span class="user-panel-rol"><?= htmlspecialchars($rolEtiqueta, ENT_QUOTES, 'UTF-8') ?></span>
    <a href="#" class="user-panel-status">
      <i class="fa fa-circle text-success"></i> Activo
    </a>
  </div>
</div>

<style>
/* ============================================================
   USER PANEL — SIDEBAR (BOVS)
   ============================================================ */
.sidebar .user-panel.user-panel-bovs {
    position: relative;
    white-space: nowrap;
    padding: 14px 12px !important;
    margin: 6px 8px 10px !important;
    border-radius: 12px;
    background: linear-gradient(135deg, rgba(35, 78, 53, 0.85) 0%, rgba(27, 58, 36, 0.90) 100%);
    border: 1px solid rgba(37, 211, 102, 0.22);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.06), 0 4px 14px rgba(0, 0, 0, 0.18);
    overflow: hidden;
    transition: background .25s ease, box-shadow .25s ease;
}
.sidebar .user-panel.user-panel-bovs:hover {
    background: linear-gradient(135deg, rgba(47, 107, 69, 0.92) 0%, rgba(27, 58, 36, 0.95) 100%);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08), 0 6px 18px rgba(0, 0, 0, 0.25);
}
.sidebar .user-panel.user-panel-bovs::before {
    content: "";
    position: absolute;
    top: -40px;
    right: -40px;
    width: 110px;
    height: 110px;
    background: radial-gradient(circle, rgba(37, 211, 102, 0.18) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}

/* ---- Avatar ---- */
.sidebar .user-panel.user-panel-bovs .image {
    position: relative;
    width: 48px;
    height: 48px;
    flex-shrink: 0;
}
.sidebar .user-panel.user-panel-bovs .user-panel-avatar,
.sidebar .user-panel.user-panel-bovs .user-panel-initials {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    border: 2px solid rgba(37, 211, 102, 0.85);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.30);
    object-fit: cover;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform .3s cubic-bezier(.34, 1.56, .64, 1);
}
.sidebar .user-panel.user-panel-bovs:hover .user-panel-avatar,
.sidebar .user-panel.user-panel-bovs:hover .user-panel-initials {
    transform: scale(1.06);
}
.sidebar .user-panel.user-panel-bovs .user-panel-initials {
    background: linear-gradient(135deg, #25d366 0%, #1b8f4c 100%);
    color: #ffffff;
    font-size: 16px;
    font-weight: 800;
    letter-spacing: .5px;
    text-transform: uppercase;
}

/* ---- Dot de estado ---- */
.sidebar .user-panel.user-panel-bovs .user-panel-dot {
    position: absolute;
    bottom: 0;
    right: 0;
    width: 14px;
    height: 14px;
    background: #25d366;
    border: 2px solid #1b3a24;
    border-radius: 50%;
    box-shadow: 0 0 0 0 rgba(37, 211, 102, 0.7);
    animation: upStatusPulse 2s infinite;
}
@keyframes upStatusPulse {
    0%   { box-shadow: 0 0 0 0 rgba(37, 211, 102, 0.7); }
    60%  { box-shadow: 0 0 0 8px rgba(37, 211, 102, 0); }
    100% { box-shadow: 0 0 0 0 rgba(37, 211, 102, 0); }
}

/* ---- Info ---- */
.sidebar .user-panel.user-panel-bovs .info.user-panel-info {
    padding-left: 12px !important;
    position: relative;
    z-index: 1;
    min-width: 0;
    max-width: calc(100% - 60px);
}
.sidebar .user-panel.user-panel-bovs .user-panel-name {
    color: #ffffff !important;
    font-size: 13px !important;
    font-weight: 700 !important;
    letter-spacing: .3px;
    margin: 0 0 2px !important;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    line-height: 1.2;
}
.sidebar .user-panel.user-panel-bovs .user-panel-rol {
    display: inline-block;
    padding: 1px 8px;
    background: rgba(37, 211, 102, 0.20);
    color: #7fe2a3;
    font-size: 9px;
    font-weight: 800;
    letter-spacing: .8px;
    text-transform: uppercase;
    border-radius: 8px;
    margin-bottom: 3px;
    border: 1px solid rgba(37, 211, 102, 0.25);
}
.sidebar .user-panel.user-panel-bovs .user-panel-status {
    display: block;
    color: rgba(255, 255, 255, 0.75) !important;
    font-size: 10.5px !important;
    font-weight: 600;
    letter-spacing: .3px;
    text-decoration: none !important;
}
.sidebar .user-panel.user-panel-bovs .user-panel-status i {
    color: #25d366 !important;
    font-size: 7px;
    vertical-align: middle;
    margin-right: 4px;
}
</style>