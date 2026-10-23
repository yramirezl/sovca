<?php
include("../../lib/validar_session.php");
ValidaSession("../login");

if (session_status() === PHP_SESSION_NONE) session_start();
$__rol = isset($_SESSION['rolx']) ? (int)$_SESSION['rolx'] : 0;

if ($__rol !== 1 && $__rol !== 4) {
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
   UNIFICAR FUNDOS — WIZARD
   ============================================================ */
.uf-wrap { max-width: 980px; margin: 0 auto; padding-bottom: 40px; }

.uf-hero {
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
.uf-hero::before {
    content: "";
    position: absolute;
    top: -60px; right: -60px;
    width: 220px; height: 220px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(255,255,255,.14) 0%, transparent 70%);
    pointer-events: none;
}
.uf-hero::after {
    content: "";
    position: absolute;
    bottom: -80px; left: -40px;
    width: 180px; height: 180px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(127,208,122,.20) 0%, transparent 70%);
    pointer-events: none;
}
.uf-hero-icon {
    flex-shrink: 0;
    width: 58px; height: 58px;
    border-radius: 14px;
    background: rgba(255,255,255,.18);
    border: 1px solid rgba(255,255,255,.28);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 26px;
    backdrop-filter: blur(4px);
    box-shadow: 0 4px 12px rgba(0,0,0,.15);
}
.uf-hero-info { flex: 1; min-width: 0; position: relative; z-index: 1; }
.uf-hero-info h2 { margin: 0 0 4px; font-size: 22px; font-weight: 800; color: #fff; }
.uf-hero-info p { margin: 0; font-size: 13px; line-height: 1.5; color: rgba(255,255,255,.85); max-width: 620px; }
.uf-hero-action { flex-shrink: 0; position: relative; z-index: 1; }

/* ---- Steps ---- */
.uf-steps {
    display: flex; align-items: stretch;
    background: #fff; border: 1px solid #e5e8eb;
    border-radius: 12px; padding: 16px 12px;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,.04);
    overflow: hidden;
}
.uf-step-item {
    flex: 1;
    display: flex; align-items: center; gap: 12px;
    padding: 8px 12px;
    border-radius: 8px;
    position: relative;
    transition: background .25s ease;
    min-width: 0;
}
.uf-step-item + .uf-step-item::before {
    content: "";
    position: absolute; left: -8px; top: 50%;
    width: 16px; height: 2px;
    background: #e0e6ec;
    transform: translateY(-50%);
}
.uf-step-item.done + .uf-step-item::before,
.uf-step-item.active + .uf-step-item::before { background: #2f8f4a; }

.uf-step-num {
    flex-shrink: 0;
    width: 36px; height: 36px;
    border-radius: 50%;
    background: #f1f4f7;
    color: #8895a4;
    display: inline-flex; align-items: center; justify-content: center;
    font-weight: 800; font-size: 14px;
    border: 2px solid transparent;
    transition: all .25s ease;
}
.uf-step-info { min-width: 0; line-height: 1.2; }
.uf-step-info strong {
    display: block; font-size: 13px;
    color: #b0bbc7; font-weight: 700;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.uf-step-info small {
    display: block; font-size: 11px;
    color: #b0bbc7;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.uf-step-item.active { background: #f4fbf6; }
.uf-step-item.active .uf-step-num {
    background: #fff; color: #2f8f4a; border-color: #2f8f4a;
    box-shadow: 0 0 0 4px rgba(47,143,74,.12);
    animation: ufPulse 2s ease-in-out infinite;
}
@keyframes ufPulse {
    0%, 100% { box-shadow: 0 0 0 4px rgba(47,143,74,.12); }
    50%      { box-shadow: 0 0 0 8px rgba(47,143,74,.04); }
}
.uf-step-item.active .uf-step-info strong { color: #233e2d; }
.uf-step-item.active .uf-step-info small  { color: #5b6874; }
.uf-step-item.done .uf-step-num { background: #2f8f4a; color: #fff; border-color: #2f8f4a; }
.uf-step-item.done .uf-step-num::before {
    content: "\f00c";
    font-family: "FontAwesome";
    font-size: 15px;
}
.uf-step-item.done .uf-step-num span { display: none; }
.uf-step-item.done .uf-step-info strong { color: #2f8f4a; }
.uf-step-item.done .uf-step-info small  { color: #5b6874; }
.uf-step-item.locked { opacity: .55; }

/* ---- Panels ---- */
.uf-panel {
    background: #fff;
    border: 1px solid #e5e8eb;
    border-radius: 12px;
    padding: 22px 24px;
    margin-bottom: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,.04);
    transition: opacity .3s ease, box-shadow .3s ease;
}
.uf-panel.locked {
    opacity: .55;
    pointer-events: none;
    background: #f8fafc;
}
.uf-panel.unlocking {
    animation: ufPanelUnlock .8s ease;
}
@keyframes ufPanelUnlock {
    0%   { box-shadow: 0 0 0 0 rgba(47,143,74,.35); }
    50%  { box-shadow: 0 0 0 8px rgba(47,143,74,.08); }
    100% { box-shadow: 0 1px 3px rgba(0,0,0,.04); }
}
.uf-panel-head { display: flex; align-items: center; gap: 10px; margin-bottom: 6px; }
.uf-panel-head h3 { margin: 0; font-size: 15px; font-weight: 800; color: #233e2d; }
.uf-panel-head .badge-step {
    display: inline-flex; align-items: center; justify-content: center;
    width: 24px; height: 24px;
    border-radius: 50%;
    background: #e8f5e9; color: #2f8f4a;
    font-size: 12px; font-weight: 800;
    flex-shrink: 0;
}
.uf-panel-hint { color: #8895a4; font-size: 12.5px; margin: 0 0 16px 34px; line-height: 1.5; }
.uf-panel-body { padding-left: 34px; }

/* ---- Search ---- */
.uf-search { display: flex; gap: 8px; max-width: 520px; margin-bottom: 10px; }
.uf-search input.form-control {
    height: 42px; border-radius: 8px;
    border: 1px solid #d7e2ea; background: #f8fbf9;
    font-size: 13px; padding: 0 14px;
}
.uf-search input.form-control:focus {
    border-color: #234e35;
    box-shadow: 0 0 0 3px rgba(35,78,53,.14);
    background: #fff;
}
.uf-search .btn { height: 42px; padding: 0 18px; font-weight: 700; border-radius: 8px; }

/* ---- Results ---- */
.uf-results {
    max-height: 260px; overflow-y: auto;
    border: 1px solid #e5e8eb; border-radius: 10px;
    background: #fff; margin-top: 4px;
}
.uf-results::-webkit-scrollbar { width: 8px; }
.uf-results::-webkit-scrollbar-thumb { background: #cdd7df; border-radius: 4px; }

.uf-result {
    display: flex; align-items: center; gap: 12px;
    padding: 12px 14px;
    border-bottom: 1px solid #f1f3f5;
    cursor: pointer;
    transition: background .15s ease;
}
.uf-result:last-child { border-bottom: 0; }
.uf-result:hover { background: #f4fbf6; }
.uf-result.selected { background: #e8f5e9; }
.uf-result.selected::after {
    content: "\f00c";
    font-family: "FontAwesome";
    color: #2f8f4a;
    font-size: 18px;
    margin-left: auto;
}
.uf-result-avatar {
    flex-shrink: 0;
    width: 40px; height: 40px;
    border-radius: 10px;
    background: linear-gradient(135deg, #234e35, #2c6e46);
    color: #fff;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 15px; font-weight: 800;
}
.uf-result-avatar.fundo { background: linear-gradient(135deg, #2f8f4a, #4caf50); }
.uf-result-body { flex: 1; min-width: 0; line-height: 1.35; }
.uf-result-body strong {
    display: block; font-size: 13.5px;
    color: #233e2d; font-weight: 700;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.uf-result-body small {
    display: block; font-size: 11.5px; color: #8895a4;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.uf-result-meta { flex-shrink: 0; display: flex; flex-direction: column; align-items: flex-end; gap: 4px; }
.uf-result-tag {
    padding: 2px 10px; border-radius: 12px;
    font-size: 10.5px; font-weight: 800;
    letter-spacing: .4px; text-transform: uppercase;
    background: #eef4f8; color: #4a5b6d;
}
.uf-result-tag.ok    { background: #e8f5e9; color: #1b5e20; }
.uf-result-tag.warn  { background: #fff3e0; color: #e65100; }
.uf-result-tag.info  { background: #e3f2fd; color: #0d47a1; }

/* ---- Selected destination ---- */
.uf-selected-box {
    background: linear-gradient(135deg, #f4fbf6 0%, #e8f5e9 100%);
    border: 1px solid #c8e6c9;
    border-left: 4px solid #2f8f4a;
    border-radius: 10px;
    padding: 14px 18px;
    display: flex; align-items: center; gap: 14px;
}
.uf-selected-avatar {
    flex-shrink: 0;
    width: 48px; height: 48px;
    border-radius: 12px;
    background: linear-gradient(135deg, #2f8f4a, #4caf50);
    color: #fff;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 18px; font-weight: 800;
    box-shadow: 0 4px 12px rgba(47,143,74,.28);
}
.uf-selected-info { flex: 1; min-width: 0; }
.uf-selected-info strong { display: block; font-size: 15px; color: #233e2d; font-weight: 800; }
.uf-selected-info small { display: block; font-size: 12px; color: #5b6874; margin-top: 2px; }
.uf-selected-actions { flex-shrink: 0; }

/* ---- Chips ---- */
.uf-chips { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 6px; }
.uf-chip {
    display: inline-flex; align-items: center; gap: 8px;
    background: #fff;
    border: 1px solid #d7e2ea;
    border-radius: 10px;
    padding: 6px 10px 6px 6px;
    font-size: 12.5px; color: #233e2d;
    transition: box-shadow .2s ease, transform .15s ease;
}
.uf-chip:hover { box-shadow: 0 4px 10px rgba(0,0,0,.06); transform: translateY(-1px); }
.uf-chip-avatar {
    flex-shrink: 0;
    width: 26px; height: 26px;
    border-radius: 6px;
    background: linear-gradient(135deg, #2f8f4a, #4caf50);
    color: #fff;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 11px; font-weight: 800;
}
.uf-chip-body { line-height: 1.2; }
.uf-chip-body strong { display: block; font-weight: 700; color: #233e2d; font-size: 12.5px; }
.uf-chip-body small { display: block; color: #8895a4; font-size: 10.5px; }
.uf-chip .uf-chip-x {
    margin-left: 4px;
    color: #c62828; font-weight: 800;
    cursor: pointer;
    padding: 2px 6px; border-radius: 4px;
    text-decoration: none !important;
    transition: background .15s ease;
    font-size: 14px; line-height: 1;
}
.uf-chip .uf-chip-x:hover { background: #ffebee; color: #b71c1c; }

.uf-empty-inline {
    display: inline-flex; align-items: center; gap: 6px;
    color: #b0bbc7; font-size: 12.5px; font-style: italic;
    padding: 8px 12px;
    background: #f8fafc;
    border-radius: 8px;
    border: 1px dashed #d7e2ea;
}

/* ---- Sub labels ---- */
.uf-sub-label {
    display: flex; align-items: center; gap: 8px;
    font-size: 11px; font-weight: 800;
    letter-spacing: .8px; text-transform: uppercase;
    color: #8895a4;
    margin: 18px 0 8px;
}
.uf-sub-label:first-child { margin-top: 4px; }
.uf-sub-label i { color: #2f8f4a; font-size: 12px; }
.uf-sub-label .count-pill {
    padding: 1px 8px;
    background: #e8f5e9; color: #1b5e20;
    border-radius: 10px;
    font-size: 10.5px; font-weight: 800;
}

/* ---- Alerts ---- */
.uf-alert {
    display: flex; align-items: flex-start; gap: 12px;
    padding: 12px 16px;
    border-radius: 10px;
    font-size: 13px; line-height: 1.5;
    border: 1px solid transparent;
    margin: 10px 0;
}
.uf-alert i { flex-shrink: 0; font-size: 16px; margin-top: 1px; }
.uf-alert-info { background: #eff6ff; border-color: #bfdbfe; color: #1e40af; }
.uf-alert-info i { color: #2563eb; }
.uf-alert-warn { background: #fffbeb; border-color: #fde68a; color: #78350f; }
.uf-alert-warn i { color: #d97706; }
.uf-alert-ok { background: #ecfdf5; border-color: #6ee7b7; color: #065f46; }
.uf-alert-ok i { color: #10b981; }

/* ---- Options ---- */
.uf-options { display: flex; flex-direction: column; gap: 10px; margin: 8px 0 16px; }
.uf-option {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 12px 14px;
    border: 1px solid #e5e8eb;
    border-radius: 10px;
    cursor: pointer;
    transition: all .2s ease;
    background: #fff;
}
.uf-option:hover { border-color: #b8d8c4; background: #f8fbf9; }
.uf-option.checked {
    border-color: #2f8f4a;
    background: #f4fbf6;
    box-shadow: 0 0 0 3px rgba(47,143,74,.10);
}
.uf-option input[type="checkbox"] {
    margin-top: 3px;
    width: 18px; height: 18px;
    accent-color: #2f8f4a;
    cursor: pointer;
    flex-shrink: 0;
}
.uf-option-body { flex: 1; line-height: 1.4; }
.uf-option-body strong { display: block; font-size: 13px; font-weight: 700; color: #233e2d; margin-bottom: 2px; }
.uf-option-body small { display: block; font-size: 11.5px; color: #8895a4; }

/* ---- Summary ---- */
.uf-summary {
    background: #f8fafc;
    border: 1px solid #e5e8eb;
    border-radius: 12px;
    padding: 16px 18px;
    margin: 8px 0 18px;
}
.uf-summary-row {
    display: flex; align-items: flex-start; gap: 12px;
    padding: 10px 0;
    border-bottom: 1px dashed #eef1f4;
    font-size: 13px;
}
.uf-summary-row:last-child { border-bottom: 0; padding-bottom: 0; }
.uf-summary-row:first-child { padding-top: 0; }
.uf-summary-icon {
    flex-shrink: 0;
    width: 30px; height: 30px;
    border-radius: 8px;
    background: #e8f5e9; color: #2f8f4a;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 13px;
}
.uf-summary-body { flex: 1; min-width: 0; line-height: 1.5; }
.uf-summary-body strong { color: #233e2d; font-weight: 700; }
.uf-summary-body .uf-summary-val { display: block; color: #233e2d; font-weight: 700; margin-bottom: 2px; }
.uf-summary-body .uf-summary-desc { display: block; color: #8895a4; font-size: 12px; }
.uf-summary-body .uf-warn { color: #c62828; font-weight: 600; }

/* ---- Final actions ---- */
.uf-final-actions {
    display: flex; justify-content: flex-end; gap: 10px;
    margin-top: 20px; padding-top: 16px;
    border-top: 1px solid #eef1f4;
}
.uf-btn-primary {
    background: linear-gradient(135deg, #2c5a3f 0%, #1b3a24 100%) !important;
    color: #fff !important;
    border: none !important;
    border-radius: 10px !important;
    padding: 12px 24px !important;
    font-size: 14px !important;
    font-weight: 800 !important;
    letter-spacing: .6px !important;
    box-shadow: 0 6px 18px rgba(35,78,53,.30) !important;
    transition: transform .15s, box-shadow .25s !important;
    display: inline-flex; align-items: center; gap: 8px;
}
.uf-btn-primary:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 10px 26px rgba(35,78,53,.40) !important;
    color: #fff !important;
}
.uf-btn-primary:disabled { opacity: .5; cursor: not-allowed; box-shadow: none !important; }
.uf-btn-secondary {
    background: #fff !important;
    color: #5b6874 !important;
    border: 1px solid #d7e2ea !important;
    border-radius: 10px !important;
    padding: 12px 20px !important;
    font-size: 13px !important;
    font-weight: 700 !important;
    transition: all .15s !important;
    text-decoration: none !important;
    display: inline-flex; align-items: center; gap: 6px;
}
.uf-btn-secondary:hover {
    border-color: #2f8f4a !important;
    color: #2f8f4a !important;
    background: #f4fbf6 !important;
}

.uf-loading {
    display: flex; align-items: center; justify-content: center;
    gap: 10px; padding: 30px 20px;
    color: #8895a4; font-size: 13px;
}
.uf-loading i { font-size: 20px; color: #2f8f4a; }

@media (max-width: 768px) {
    .uf-hero { flex-direction: column; align-items: flex-start; gap: 14px; padding: 18px 20px; }
    .uf-hero-action { width: 100%; }
    .uf-hero-action .btn { width: 100%; }
    .uf-steps { flex-direction: column; gap: 6px; }
    .uf-step-item + .uf-step-item::before { display: none; }
    .uf-panel { padding: 18px 16px; }
    .uf-panel-body { padding-left: 0; }
    .uf-panel-hint { margin-left: 0; }
    .uf-search { flex-direction: column; }
    .uf-search .btn { width: 100%; }
    .uf-selected-box { flex-direction: column; text-align: center; }
    .uf-final-actions { flex-direction: column-reverse; }
    .uf-final-actions .btn, .uf-final-actions a { width: 100%; justify-content: center; }
}
</style>

<div class="uf-wrap">

    <div class="message"></div>

    <div class="uf-hero">
        <span class="uf-hero-icon"><i class="fa fa-object-group"></i></span>
        <div class="uf-hero-info">
            <h2>Unificar Fundos</h2>
            <p>Consolida varios fundos bajo un único usuario multifundo. No se mueve ningún dato — los animales y registros siguen perteneciendo a su fundo original; solo cambia <b>quién tiene acceso</b>.</p>
        </div>
        <div class="uf-hero-action">
            <a href="index.php" class="btn btn-warning"><i class="fa fa-reply"></i> Regresar</a>
        </div>
    </div>

    <div class="uf-steps" id="uf-steps">
        <div class="uf-step-item active" data-step="1">
            <span class="uf-step-num"><span>1</span></span>
            <div class="uf-step-info">
                <strong>Usuario destino</strong>
                <small>Quién conservará la cuenta</small>
            </div>
        </div>
        <div class="uf-step-item locked" data-step="2">
            <span class="uf-step-num"><span>2</span></span>
            <div class="uf-step-info">
                <strong>Fundos a unificar</strong>
                <small>Selecciona los fundos</small>
            </div>
        </div>
        <div class="uf-step-item locked" data-step="3">
            <span class="uf-step-num"><span>3</span></span>
            <div class="uf-step-info">
                <strong>Confirmar</strong>
                <small>Revisa y aplica</small>
            </div>
        </div>
    </div>

    <!-- PASO 1 -->
    <div class="uf-panel" data-panel="1">
        <div class="uf-panel-head">
            <span class="badge-step">1</span>
            <h3>Usuario que conserva la cuenta</h3>
        </div>
        <p class="uf-panel-hint">Busca y selecciona el usuario que quedará como cuenta multifundo. Los fundos que elijas en el siguiente paso pasarán a su control.</p>
        <div class="uf-panel-body">

            <div id="uf-preseleccion-aviso" style="display:none;"></div>

            <div id="uf-destino-buscar">
                <div class="uf-search">
                    <input type="text" class="form-control" id="uf-q-usuario"
                           placeholder="Buscar por usuario, nombre o correo…" autocomplete="off">
                    <button class="btn btn-success" type="button" id="uf-btn-usuario">
                        <i class="fa fa-search"></i> Buscar
                    </button>
                </div>
                <div class="uf-results" id="uf-lista-usuarios" style="display:none;"></div>
            </div>

            <div id="uf-destino-box" style="display:none;">
                <div class="uf-selected-box">
                    <span class="uf-selected-avatar" id="uf-destino-avatar">?</span>
                    <div class="uf-selected-info">
                        <strong id="uf-destino-nombre"></strong>
                        <small id="uf-destino-mail"></small>
                    </div>
                    <div class="uf-selected-actions">
                        <button type="button" class="btn btn-default btn-sm" id="uf-destino-cambiar">
                            <i class="fa fa-refresh"></i> Cambiar
                        </button>
                    </div>
                </div>

                <div class="uf-sub-label" style="margin-top:18px;">
                    <i class="fa fa-building-o"></i>
                    Fundos que ya administra
                    <span class="count-pill" id="uf-destino-count">0</span>
                </div>
                <div class="uf-chips" id="uf-destino-fundos"></div>

                <div id="uf-destino-aviso"></div>

                <!-- Botón manual para avanzar por si el usuario lo necesita -->
                <div class="uf-final-actions" id="uf-paso1-avance" style="display:none;">
                    <button type="button" class="uf-btn-primary" id="uf-ir-paso2">
                        Continuar al siguiente paso <i class="fa fa-arrow-right"></i>
                    </button>
                </div>
            </div>

        </div>
    </div>

    <!-- PASO 2 -->
    <div class="uf-panel locked" data-panel="2">
        <div class="uf-panel-head">
            <span class="badge-step">2</span>
            <h3>Fundos que se asignarán al usuario</h3>
        </div>
        <p class="uf-panel-hint">Selecciona los fundos que pasarán al usuario destino. Puedes usar las sugerencias automáticas o buscar manualmente por nombre, RIF o propietario.</p>
        <div class="uf-panel-body">

            <div id="uf-sugerencias"></div>

            <div class="uf-search">
                <input type="text" class="form-control" id="uf-q-fundo"
                       placeholder="Buscar fundo por nombre, RIF o usuario…" autocomplete="off">
                <button class="btn btn-success" type="button" id="uf-btn-fundo">
                    <i class="fa fa-search"></i> Buscar
                </button>
            </div>
            <div class="uf-results" id="uf-lista-fundos" style="display:none;"></div>

            <div class="uf-sub-label" style="margin-top:18px;">
                <i class="fa fa-check-circle"></i>
                Fundos seleccionados
                <span class="count-pill" id="uf-seleccion-count">0</span>
            </div>
            <div id="uf-seleccion">
                <span class="uf-empty-inline">
                    <i class="fa fa-info-circle"></i> Aún no has seleccionado fundos
                </span>
            </div>

            <!-- Botón manual para avanzar -->
            <div class="uf-final-actions" id="uf-paso2-avance" style="display:none;">
                <button type="button" class="uf-btn-primary" id="uf-ir-paso3">
                    Continuar al paso final <i class="fa fa-arrow-right"></i>
                </button>
            </div>

        </div>
    </div>

    <!-- PASO 3 -->
    <div class="uf-panel locked" data-panel="3">
        <div class="uf-panel-head">
            <span class="badge-step">3</span>
            <h3>Opciones y confirmación</h3>
        </div>
        <p class="uf-panel-hint">Configura las opciones finales y revisa el resumen antes de aplicar los cambios.</p>
        <div class="uf-panel-body">

            <div class="uf-options">
                <label class="uf-option checked" id="uf-opt-desactivar-lbl">
                    <input type="checkbox" id="uf-desactivar" checked>
                    <div class="uf-option-body">
                        <strong>Desactivar cuentas origen sin fundos</strong>
                        <small>Las cuentas que queden sin ningún fundo tras la unificación serán desactivadas automáticamente.</small>
                    </div>
                </label>
                <label class="uf-option checked" id="uf-opt-propiedad-lbl">
                    <input type="checkbox" id="uf-propiedad" checked>
                    <div class="uf-option-body">
                        <strong>Dejar al destino como propietario de los fundos</strong>
                        <small>El usuario destino figurará como propietario legal de los fundos unificados.</small>
                    </div>
                </label>
            </div>

            <div class="uf-sub-label">
                <i class="fa fa-list-alt"></i>
                Resumen de la operación
            </div>
            <div class="uf-summary" id="uf-resumen">
                <div class="uf-loading">
                    <i class="fa fa-info-circle"></i>
                    Selecciona fundos en el paso anterior para ver el resumen.
                </div>
            </div>

            <div class="uf-final-actions">
                <a href="index.php" class="uf-btn-secondary">
                    <i class="fa fa-reply"></i> Cancelar
                </a>
                <button type="button" class="uf-btn-primary" id="uf-aplicar" disabled>
                    <i class="fa fa-object-group"></i> Unificar fundos
                </button>
            </div>

        </div>
    </div>

</div>

<script type="text/javascript">
jQuery(function ($) {
    "use strict";

    var CTRL = "../../controllers/mfundos_controller";
    var destino = null;
    var seleccion = {};
    var enDestino = {};
    var PRESELECCION_IDS = [];

    /* ============================================================
       HELPERS
       ============================================================ */
    function esc(s) {
        return $('<div>').text(s === null || s === undefined ? '' : s).html();
    }
    function inicial(str) {
        return (str || '?').trim().charAt(0).toUpperCase();
    }
    function log() {
        if (window.console && console.log) {
            console.log.apply(console, ['[UF]'].concat(Array.prototype.slice.call(arguments)));
        }
    }

    /* ============================================================
       WIZARD — CONTROL DE PASOS
       ============================================================ */
    function irAlPaso(n) {
        log('irAlPaso →', n);

        // 1) Steps superiores
        $('#uf-steps .uf-step-item').each(function () {
            var $s = $(this);
            var s = parseInt($s.attr('data-step'), 10);
            $s.removeClass('active done locked');
            if (s < n)        $s.addClass('done');
            else if (s === n) $s.addClass('active');
            else              $s.addClass('locked');
        });

        // 2) Paneles
        var $nuevoPanel = null;
        $('.uf-panel').each(function () {
            var $p = $(this);
            var p = parseInt($p.attr('data-panel'), 10);
            if (p > n) {
                $p.addClass('locked');
            } else {
                var estabaBloqueado = $p.hasClass('locked');
                $p.removeClass('locked');
                if (estabaBloqueado && p === n) $nuevoPanel = $p;
            }
        });

        // 3) Animación + scroll
        if ($nuevoPanel && $nuevoPanel.length) {
            $nuevoPanel.addClass('unlocking');
            setTimeout(function () { $nuevoPanel.removeClass('unlocking'); }, 900);
            var top = $nuevoPanel.offset().top - 80;
            $('html, body').animate({ scrollTop: top }, 400);
            log('Panel', n, 'desbloqueado — scroll a', top);
        }
    }

    /* ============================================================
       LECTURA DE QUERY STRING
       ============================================================ */
    (function leerPreseleccion() {
        try {
            var params = new URLSearchParams(window.location.search);
            var p = params.get('preseleccion');
            if (!p) return;
            PRESELECCION_IDS = p.split(',')
                .map(function (x) { return parseInt(x, 10); })
                .filter(function (x) { return !isNaN(x) && x > 0; });
            log('Preselección detectada:', PRESELECCION_IDS);
        } catch (e) {
            log('Error leyendo preselección:', e.message);
        }
    })();

    /* ============================================================
       ⭐ FUNCIÓN GLOBAL DE SELECCIÓN DE USUARIO
       Expuesta en window para poder ser llamada desde onclick inline
       como fallback por si la delegación falla.
       ============================================================ */
    window.ufSeleccionarUsuario = function (el) {
        try {
            log('>>> ufSeleccionarUsuario llamado');

            var $el = $(el);
            if (!$el.length) {
                log('ERROR: elemento no encontrado');
                return false;
            }

            var id = $el.attr('data-id');
            if (!id) {
                log('ERROR: data-id vacío');
                return false;
            }

            destino = {
                id:       id,
                username: $el.attr('data-username') || '',
                name:     $el.attr('data-name') || '',
                email:    $el.attr('data-email') || ''
            };
            enDestino = {};
            log('✓ Destino seleccionado:', destino);

            // UI: marcar como seleccionado
            $('#uf-lista-usuarios .uf-result').removeClass('selected');
            $el.addClass('selected');

            // UI: mostrar ficha y ocultar lista
            setTimeout(function () {
                $('#uf-lista-usuarios').hide();
                $('#uf-destino-buscar').hide();
                $('#uf-destino-box').show();
                $('#uf-destino-avatar').text(inicial(destino.name || destino.username));
                $('#uf-destino-nombre').text(destino.username + ' — ' + destino.name);
                $('#uf-destino-mail').text(destino.email);

                // *** DESBLOQUEAR PASO 2 DIRECTAMENTE ***
                $('.uf-panel[data-panel="2"]').removeClass('locked');
                $('.uf-panel[data-panel="3"]').addClass('locked');

                // *** MARCAR STEP 1 COMO DONE Y STEP 2 COMO ACTIVE ***
                $('#uf-steps .uf-step-item[data-step="1"]').removeClass('active').addClass('done');
                $('#uf-steps .uf-step-item[data-step="2"]').removeClass('locked').addClass('active');
                $('#uf-steps .uf-step-item[data-step="3"]').addClass('locked').removeClass('active');

                // Scroll al paso 2
                var top = $('.uf-panel[data-panel="2"]').offset().top - 80;
                $('html, body').animate({ scrollTop: top }, 400);

                // Cargar datos del destino
                cargarFundosDestino();
                cargarSugerencias();
                pintarSeleccion();

                log('✓ Paso 2 desbloqueado y visible');
            }, 100);

            return false;
        } catch (err) {
            log('EXCEPCIÓN en ufSeleccionarUsuario:', err.message);
            return false;
        }
    };

    /* ============================================================
       CAPA 1: Delegación en el contenedor padre directo
       ============================================================ */
    $('#uf-lista-usuarios').on('click', '.uf-result', function (e) {
        log('CLICK [capa 1 — contenedor]');
        e.preventDefault();
        e.stopPropagation();
        return window.ufSeleccionarUsuario(this);
    });

    /* ============================================================
       CAPA 2: Delegación en document (backup)
       ============================================================ */
    $(document).on('click', '#uf-lista-usuarios .uf-result', function (e) {
        // Si la capa 1 ya procesó el click, evitar re-ejecución
        if (e.isDefaultPrevented()) { log('CLICK [capa 2] ignorado (capa 1 ya procesó)'); return; }
        log('CLICK [capa 2 — document]');
        e.preventDefault();
        return window.ufSeleccionarUsuario(this);
    });

    /* ============================================================
       PASO 1: BUSCAR USUARIO DESTINO
       ============================================================ */
    function buscarUsuario() {
        log('Buscando usuario:', $('#uf-q-usuario').val());

        $('#uf-lista-usuarios')
            .html('<div class="uf-loading"><i class="fa fa-spinner fa-spin"></i> Buscando…</div>')
            .show();

        $.post(CTRL, { action: 'unificar_buscar_usuario', q: $('#uf-q-usuario').val() }, function (r) {
            log('unificar_buscar_usuario →', r);
            if (!r || !r.ok) {
                $('#uf-lista-usuarios').html('<div class="uf-loading"><i class="fa fa-exclamation-triangle" style="color:#c62828"></i> No se pudo consultar.</div>');
                return;
            }
            var h = '';
            if (!r.usuarios || !r.usuarios.length) {
                h = '<div class="uf-loading"><i class="fa fa-search" style="color:#b0bbc7"></i> Sin resultados para esta búsqueda.</div>';
            } else {
                $.each(r.usuarios, function (i, u) {
                    // ⭐ onclick inline como CAPA 3 (fallback definitivo)
                    h += '<div class="uf-result" '
                       +   'data-id="' + u.id + '" '
                       +   'data-username="' + esc(u.username) + '" '
                       +   'data-name="' + esc(u.name) + '" '
                       +   'data-email="' + esc(u.email) + '" '
                       +   'onclick="return window.ufSeleccionarUsuario(this);">'
                       +   '<span class="uf-result-avatar">' + esc(inicial(u.name || u.username)) + '</span>'
                       +   '<div class="uf-result-body">'
                       +     '<strong>' + esc(u.username) + '</strong>'
                       +     '<small>' + esc(u.name) + ' · ' + esc(u.email) + '</small>'
                       +   '</div>'
                       +   '<div class="uf-result-meta">'
                       +     '<span class="uf-result-tag info">' + u.fundos + ' fundo' + (u.fundos !== 1 ? 's' : '') + '</span>'
                       +   '</div>'
                       + '</div>';
                });
            }
            $('#uf-lista-usuarios').html(h).show();
        }, 'json').fail(function (xhr) {
            log('Error AJAX unificar_buscar_usuario', xhr.status);
            $('#uf-lista-usuarios').html('<div class="uf-loading"><i class="fa fa-exclamation-triangle" style="color:#c62828"></i> Error de conexión.</div>');
        });
    }

    $('#uf-btn-usuario').on('click', function (e) {
        e.preventDefault();
        buscarUsuario();
    });
    $('#uf-q-usuario').on('keypress', function (e) {
        if (e.which === 13) { e.preventDefault(); buscarUsuario(); }
    });

    /* ============================================================
       CAMBIAR DESTINO
       ============================================================ */
    $('#uf-destino-cambiar').on('click', function (e) {
        e.preventDefault();
        destino = null;
        seleccion = {};
        enDestino = {};
        $('#uf-destino-box').hide();
        $('#uf-destino-buscar').show();
        $('#uf-lista-usuarios').hide();
        $('#uf-sugerencias').empty();
        $('#uf-destino-aviso').empty();
        $('#uf-destino-fundos').empty();
        $('#uf-seleccion').html('<span class="uf-empty-inline"><i class="fa fa-info-circle"></i> Aún no has seleccionado fundos</span>');
        $('#uf-seleccion-count').text(0);
        $('#uf-q-usuario').val('').focus();
        irAlPaso(1);
    });

    /* Botón manual paso 1 → 2 */
    $('#uf-ir-paso2').on('click', function (e) {
        e.preventDefault();
        if (!destino) return;
        irAlPaso(2);
    });

    /* Botón manual paso 2 → 3 */
    $('#uf-ir-paso3').on('click', function (e) {
        e.preventDefault();
        if (!destino) return;
        var n = Object.keys(seleccion).length;
        if (!n) {
            $.confirm({ title: 'Selecciona al menos un fundo', content: false, confirmButton: 'cerrar', confirmButtonClass: 'btn-success', cancelButton: false, closeIcon: false });
            return;
        }
        irAlPaso(3);
        refrescarResumen();
        cargarInvolucrados(Object.keys(seleccion).join(','));
    });

    /* ============================================================
       FUNDOS DEL DESTINO
       ============================================================ */
    function cargarFundosDestino() {
        if (!destino) return;
        $.post(CTRL, { action: 'unificar_fundos_usuario', usuario_id: destino.id }, function (r) {
            if (!r || !r.ok) return;
            enDestino = {};
            var h = '';
            $.each(r.fundos || [], function (i, f) {
                enDestino[f.id] = true;
                h += '<span class="uf-chip">'
                   +   '<span class="uf-chip-avatar">' + esc(inicial(f.nombre)) + '</span>'
                   +   '<span class="uf-chip-body">'
                   +     '<strong>' + esc(f.nombre) + '</strong>'
                   +     '<small>' + esc(f.ganaderia) + ' · ' + f.animales + ' anim.</small>'
                   +   '</span>'
                   + '</span>';
            });
            if (!h) h = '<span class="uf-empty-inline"><i class="fa fa-info-circle"></i> Todavía no tiene fundos</span>';
            $('#uf-destino-fundos').html(h);
            $('#uf-destino-count').text((r.fundos || []).length);
        }, 'json');
    }

    /* ============================================================
       SUGERENCIAS POR CORREO
       ============================================================ */
    function cargarSugerencias() {
        if (!destino) return;
        $.post(CTRL, { action: 'unificar_candidatos' }, function (r) {
            if (!r || !r.ok) return;
            var grupo = null;
            $.each(r.grupos || [], function (i, g) {
                if (destino.email && g.email === destino.email.toLowerCase()) grupo = g;
            });
            if (!grupo) { $('#uf-sugerencias').empty(); return; }

            var otros = [], fundos = 0;
            $.each(grupo.usuarios, function (i, u) {
                if (String(u.id) === String(destino.id)) return;
                otros.push(u);
                $.each(u.fundos, function (j, f) { fundos++; });
            });
            if (!otros.length) { $('#uf-sugerencias').empty(); return; }

            var names = $.map(otros, function (u) { return esc(u.username); }).join(', ');
            $('#uf-sugerencias').html(
                '<div class="uf-alert uf-alert-info">'
              +   '<i class="fa fa-lightbulb-o"></i>'
              +   '<div>'
              +     '<b>Se detectaron ' + otros.length + ' cuenta(s) con el mismo correo:</b> ' + names + '.'
              +     ' <button type="button" class="btn btn-xs btn-success" id="uf-cargar-grupo" style="margin-left:6px;">'
              +       '<i class="fa fa-plus"></i> Cargar sus ' + fundos + ' fundo(s)'
              +     '</button>'
              +   '</div>'
              + '</div>'
            );
        }, 'json');
    }

    $(document).on('click', '#uf-cargar-grupo', function (e) {
        e.preventDefault();
        if (!destino) return;
        $.post(CTRL, { action: 'unificar_candidatos' }, function (r) {
            if (!r || !r.ok) return;
            $.each(r.grupos || [], function (i, g) {
                if (g.email !== destino.email.toLowerCase()) return;
                $.each(g.usuarios, function (k, u) {
                    if (String(u.id) === String(destino.id)) return;
                    $.each(u.fundos, function (j, f) { agregar(f); });
                });
            });
            pintarSeleccion();
            if ($('#uf-lista-fundos').is(':visible')) buscarFundos();
        }, 'json');
    });

    /* ============================================================
       BUSCAR FUNDOS
       ============================================================ */
    function buscarFundos() {
        $('#uf-lista-fundos')
            .html('<div class="uf-loading"><i class="fa fa-spinner fa-spin"></i> Buscando…</div>')
            .show();

        $.post(CTRL, { action: 'unificar_buscar_fundos', q: $('#uf-q-fundo').val() }, function (r) {
            if (!r || !r.ok) {
                $('#uf-lista-fundos').html('<div class="uf-loading"><i class="fa fa-exclamation-triangle" style="color:#c62828"></i> No se pudo consultar.</div>');
                return;
            }
            var h = '';
            if (!r.fundos || !r.fundos.length) {
                h = '<div class="uf-loading"><i class="fa fa-search" style="color:#b0bbc7"></i> Sin resultados.</div>';
            } else {
                $.each(r.fundos, function (i, f) {
                    var yaEsta = enDestino[f.id];
                    var estaSel = !!seleccion[f.id];
                    h += '<div class="uf-result' + (estaSel ? ' selected' : '') + '" '
                       +   'data-id="' + f.id + '" '
                       +   'data-nombre="' + esc(f.nombre) + '" '
                       +   'data-gan="' + esc(f.ganaderia) + '" '
                       +   'data-anim="' + f.animales + '" '
                       +   'data-prop="' + esc(f.propietario) + '" '
                       +   'onclick="return window.ufToggleFundo(this);">'
                       +   '<span class="uf-result-avatar fundo">' + esc(inicial(f.nombre)) + '</span>'
                       +   '<div class="uf-result-body">'
                       +     '<strong>' + esc(f.nombre) + '</strong>'
                       +     '<small>' + esc(f.rif || 'Sin RIF') + ' · ' + esc(f.ganaderia) + ' · ' + f.animales + ' anim.'
                       +       (f.propietario ? ' · propietario: ' + esc(f.propietario) : '') + '</small>'
                       +   '</div>'
                       +   '<div class="uf-result-meta">'
                       +     (yaEsta
                                ? '<span class="uf-result-tag ok">Ya lo tiene</span>'
                                : (estaSel
                                    ? '<span class="uf-result-tag info">Seleccionado</span>'
                                    : '<span class="uf-result-tag">Disponible</span>'))
                       +   '</div>'
                       + '</div>';
                });
            }
            $('#uf-lista-fundos').html(h).show();
        }, 'json');
    }

    $('#uf-btn-fundo').on('click', function (e) { e.preventDefault(); buscarFundos(); });
    $('#uf-q-fundo').on('keypress', function (e) {
        if (e.which === 13) { e.preventDefault(); buscarFundos(); }
    });

    /* ============================================================
       ⭐ FUNCIÓN GLOBAL PARA TOGGLE DE FUNDO
       ============================================================ */
    window.ufToggleFundo = function (el) {
        try {
            var $el = $(el);
            var id = $el.attr('data-id');
            if (!id) return false;

            if (seleccion[id]) {
                delete seleccion[id];
            } else {
                if (enDestino[id]) return false;
                agregar({
                    id:          id,
                    nombre:      $el.attr('data-nombre'),
                    ganaderia:   $el.attr('data-gan'),
                    animales:    $el.attr('data-anim'),
                    propietario: $el.attr('data-prop')
                });
            }
            pintarSeleccion();

            $el.toggleClass('selected', !!seleccion[id]);
            var $tag = $el.find('.uf-result-tag');
            if (seleccion[id]) $tag.text('Seleccionado').removeClass('ok').addClass('info');
            else               $tag.text('Disponible').removeClass('info').addClass('');

            return false;
        } catch (err) {
            log('EXCEPCIÓN en ufToggleFundo:', err.message);
            return false;
        }
    };

    // Delegación backup para fundos
    $('#uf-lista-fundos').on('click', '.uf-result', function (e) {
        if (e.isDefaultPrevented()) return;
        e.preventDefault();
        return window.ufToggleFundo(this);
    });
    $(document).on('click', '#uf-lista-fundos .uf-result', function (e) {
        if (e.isDefaultPrevented()) return;
        e.preventDefault();
        return window.ufToggleFundo(this);
    });

    function agregar(f) {
        if (enDestino[f.id]) return;
        seleccion[f.id] = f;
    }

    /* ============================================================
       PINTAR SELECCIÓN
       ============================================================ */
     /* ============================================================
       PINTAR SELECCIÓN — SIN auto-avance
       El usuario decide cuándo continuar al paso 3
       ============================================================ */
    function pintarSeleccion() {
        var ids = Object.keys(seleccion);
        $('#uf-seleccion-count').text(ids.length);

        if (!ids.length) {
            $('#uf-seleccion').html('<span class="uf-empty-inline"><i class="fa fa-info-circle"></i> Aún no has seleccionado fundos</span>');
            $('#uf-aplicar').prop('disabled', true);
            $('#uf-resumen').html('<div class="uf-loading"><i class="fa fa-info-circle"></i> Selecciona fundos para ver el resumen.</div>');
            $('#uf-destino-aviso').empty();
            $('#uf-paso2-avance').hide();
            if (destino) irAlPaso(2);
            return;
        }

        // Pintar chips
        var h = '';
        $.each(ids, function (i, id) {
            var f = seleccion[id];
            h += '<span class="uf-chip">'
               +   '<span class="uf-chip-avatar">' + esc(inicial(f.nombre)) + '</span>'
               +   '<span class="uf-chip-body">'
               +     '<strong>' + esc(f.nombre) + '</strong>'
               +     '<small>' + esc(f.ganaderia) + ' · ' + esc(f.animales) + ' anim.</small>'
               +   '</span>'
               +   '<a href="#" class="uf-chip-x" data-quitar="' + esc(id) + '" title="Quitar">&times;</a>'
               + '</span>';
        });
        $('#uf-seleccion').html(h);

        // Si hay destino → mostrar botón para avanzar (NO auto-avanzar)
        if (destino) {
            $('#uf-paso2-avance').show();
            $('#uf-paso1-avance').hide();
            // Mantener el paso 2 activo, pero habilitar el botón manual
            if ($('#uf-steps .uf-step-item[data-step="2"]').hasClass('active')) {
                // Ya estamos en paso 2: bien
            } else if ($('#uf-steps .uf-step-item[data-step="3"]').hasClass('active')) {
                // Ya estamos en paso 3: no hacer nada
            } else {
                irAlPaso(2);
            }
        } else {
            $('#uf-paso2-avance').hide();
            $('#uf-aplicar').prop('disabled', true);
            if (PRESELECCION_IDS.length) $('#uf-preseleccion-aviso').show();
        }
    }

    /* ============================================================
       INVOLUCRADOS
       ============================================================ */
    function cargarInvolucrados(ids) {
        if (!ids || !destino) { $('#uf-destino-aviso').empty(); return; }
        $.post(CTRL, { action: 'unificar_candidatos_fundos', fundos: ids }, function (r) {
            if (!r || !r.ok || !destino) { $('#uf-destino-aviso').empty(); return; }
            var c = r.candidatos || [];
            var esInvolucrado = false;
            $.each(c, function (i, u) {
                if (String(u.id) === String(destino.id)) esInvolucrado = true;
            });

            var h = '';
            if (!esInvolucrado && c.length) {
                h += '<div class="uf-alert uf-alert-warn">'
                   +   '<i class="fa fa-exclamation-triangle"></i>'
                   +   '<div><b>El destino <u>' + esc(destino.username) + '</u> no figura entre los usuarios involucrados</b> '
                   +   'en los fundos seleccionados. Se recomienda elegir uno de ellos para evitar accesos huérfanos.</div>'
                   + '</div>';
            }
            if (c.length) {
                h += '<div class="uf-sub-label" style="margin-top:14px;">'
                   +   '<i class="fa fa-users"></i> Usuarios involucrados en los fundos'
                   + '</div>';
                h += '<div style="display:flex; flex-wrap:wrap; gap:6px;">';
                $.each(c, function (i, u) {
                    if (String(u.id) === String(destino.id)) {
                        h += '<span class="uf-chip" style="border-color:#2f8f4a;background:#f4fbf6;">'
                           +   '<span class="uf-chip-avatar"><i class="fa fa-check"></i></span>'
                           +   '<span class="uf-chip-body">'
                           +     '<strong>' + esc(u.username) + ' (destino)</strong>'
                           +     '<small>' + u.fundos_total + ' fundo(s) · ' + esc(u.rol) + '</small>'
                           +   '</span>'
                           + '</span>';
                    } else {
                        h += '<button type="button" class="btn btn-default btn-xs" data-usar="' + u.id + '" '
                           +   'data-username="' + esc(u.username) + '" data-name="' + esc(u.name) + '" '
                           +   'data-email="' + esc(u.email) + '" style="margin-bottom:6px;">'
                           +   '<i class="fa fa-user"></i> ' + esc(u.username)
                           +   ' <small style="color:#8895a4;">(' + u.fundos_total + ' fundo(s) · ' + esc(u.rol) + ')</small>'
                           +   ' <span style="color:#2f8f4a;font-size:10px;">usar como destino</span>'
                           + '</button>';
                    }
                });
                h += '</div>';
            }
            $('#uf-destino-aviso').html(h);
        }, 'json');
    }

    $(document).on('click', '#uf-destino-aviso [data-usar]', function (e) {
        e.preventDefault();
        var $b = $(this);
        destino = {
            id:       $b.attr('data-usar'),
            username: $b.attr('data-username'),
            name:     $b.attr('data-name'),
            email:    $b.attr('data-email')
        };
        $('#uf-destino-avatar').text(inicial(destino.name || destino.username));
        $('#uf-destino-nombre').text(destino.username + ' — ' + destino.name);
        $('#uf-destino-mail').text(destino.email);
        enDestino = {};
        cargarFundosDestino();
        pintarSeleccion();
    });

    $(document).on('click', '#uf-seleccion [data-quitar]', function (e) {
        e.preventDefault();
        delete seleccion[$(this).attr('data-quitar')];
        pintarSeleccion();
        if ($('#uf-lista-fundos').is(':visible')) buscarFundos();
    });

    /* ============================================================
       RESUMEN
       ============================================================ */
    function refrescarResumen() {
        var ids = Object.keys(seleccion).join(',');
        if (!ids) {
            $('#uf-resumen').html('<div class="uf-loading"><i class="fa fa-info-circle"></i> Sin fundos seleccionados.</div>');
            return;
        }
        $.post(CTRL, { action: 'unificar_preview', destino: destino.id, fundos: ids }, function (r) {
            if (!r || !r.ok) {
                $('#uf-resumen').html('<div class="uf-alert uf-alert-warn"><i class="fa fa-exclamation-triangle"></i>' + esc((r && r.msg) || 'Error.') + '</div>');
                return;
            }
            var h = '';

            h += '<div class="uf-summary-row">'
               +   '<span class="uf-summary-icon"><i class="fa fa-user-circle"></i></span>'
               +   '<div class="uf-summary-body">'
               +     '<span class="uf-summary-val">' + esc(r.destino.username) + ' — ' + esc(r.destino.name) + '</span>'
               +     '<span class="uf-summary-desc">Recibirá ' + r.fundos.length + ' fundo(s)</span>'
               +   '</div>'
               + '</div>';

            h += '<div class="uf-summary-row">'
               +   '<span class="uf-summary-icon"><i class="fa fa-building-o"></i></span>'
               +   '<div class="uf-summary-body">'
               +     '<span class="uf-summary-val">Fundos a unificar (' + r.fundos.length + ')</span>'
               +     '<span class="uf-summary-desc">'
               +       $.map(r.fundos, function (f) { return esc(f.nombre) + ' <small>(' + esc(f.ganaderia) + ')</small>'; }).join(' · ')
               +     '</span>'
               +   '</div>'
               + '</div>';

            if (r.origenes.length) {
                h += '<div class="uf-summary-row">'
                   +   '<span class="uf-summary-icon" style="background:#fff3e0;color:#e65100;"><i class="fa fa-users"></i></span>'
                   +   '<div class="uf-summary-body">'
                   +     '<span class="uf-summary-val">Perderán el acceso (' + r.origenes.length + ')</span>'
                   +     '<span class="uf-summary-desc">' + $.map(r.origenes, function (o) { return esc(o.username); }).join(', ') + '</span>'
                   +   '</div>'
                   + '</div>';
            } else {
                h += '<div class="uf-summary-row">'
                   +   '<span class="uf-summary-icon" style="background:#e3f2fd;color:#0d47a1;"><i class="fa fa-info-circle"></i></span>'
                   +   '<div class="uf-summary-body"><span class="uf-summary-desc">Ninguna otra cuenta tiene acceso a esos fundos.</span></div>'
                   + '</div>';
            }

            if (r.errores && r.errores.length) {
                h += '<div class="uf-summary-row">'
                   +   '<span class="uf-summary-icon" style="background:#fdecea;color:#c62828;"><i class="fa fa-exclamation-triangle"></i></span>'
                   +   '<div class="uf-summary-body">'
                   +     '<span class="uf-summary-val uf-warn">Advertencias</span>'
                   +     '<span class="uf-summary-desc uf-warn">' + $.map(r.errores, esc).join('<br>') + '</span>'
                   +   '</div>'
                   + '</div>';
            }

            $('#uf-resumen').html(h);
            $('#uf-aplicar').prop('disabled', false);
        }, 'json');
    }

    /* ============================================================
       TOGGLE OPCIONES
       ============================================================ */
    $('#uf-desactivar, #uf-propiedad').on('change', function () {
        $(this).closest('.uf-option').toggleClass('checked', $(this).is(':checked'));
    });

    /* ============================================================
       APLICAR
       ============================================================ */
    $('#uf-aplicar').on('click', function (e) {
        e.preventDefault();
        var ids = Object.keys(seleccion).join(',');
        if (!destino) {
            $.confirm({ title: 'Seleccione el usuario destino', content: false, confirmButton: 'cerrar', confirmButtonClass: 'btn-success', cancelButton: false, closeIcon: false });
            return;
        }
        if (!ids) {
            $.confirm({ title: 'Seleccione al menos un fundo', content: false, confirmButton: 'cerrar', confirmButtonClass: 'btn-success', cancelButton: false, closeIcon: false });
            return;
        }
        $.confirm({
            title: '¿Unificar los fundos seleccionados?',
            content: 'Los fundos pasarán al usuario <b>' + esc(destino.username) + '</b>. '
                   + 'No se borra ningún dato: solo cambia el acceso.<br><br>'
                   + '<small>Las cuentas origen que queden sin fundos se inhabilitan y pierden el acceso a esos fundos; '
                   + 'los usuarios ayudantes de cada fundo pasan a depender del nuevo productor (heredan su situación).</small>',
            confirmButton: 'Sí, unificar', cancelButton: 'Cancelar',
            confirmButtonClass: 'btn-success', cancelButtonClass: 'btn-default',
            confirm: function () {
                $('#uf-aplicar').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Unificando…');
                $.post(CTRL, {
                    action: 'unificar_aplicar',
                    destino: destino.id,
                    fundos: ids,
                    desactivar: $('#uf-desactivar').is(':checked') ? 1 : 0,
                    propiedad: $('#uf-propiedad').is(':checked') ? 1 : 0
                }, function (r) {
                    if (r && r.ok) {
                        var res = r.resultado || {};
                        var nDes = (res.desactivados || []).length;
                        var nHer = (res.heredados || []).length;
                        var detalle = '';

                        if (nDes || nHer) {
                            detalle = '<br><small>';
                            if (nDes) {
                                detalle += 'Cuentas origen inhabilitadas: <b>' + nDes + '</b>. ';
                            }
                            if (nHer) {
                                detalle += 'Usuarios ayudantes que heredaron la inhabilitación: <b>'
                                         + $.map(res.heredados, function (h) { return esc(h.username); }).join(', ')
                                         + '</b>.';
                            }
                            detalle += '</small>';
                        }

                        $('.message').html(
                            '<div class="alert alert-success alert-dismissable">'
                          +   '<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>'
                          +   '<i class="fa fa-check-circle"></i> ' + esc(r.msg) + detalle
                          + '</div>'
                        );
                        $('html, body').animate({ scrollTop: 0 }, 300);
                        seleccion = {};
                        pintarSeleccion();
                        cargarFundosDestino();
                        setTimeout(function () { location.reload(); }, 2500);
                    } else {
                        $('.message').html(
                            '<div class="alert alert-danger alert-dismissable">'
                          +   '<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>'
                          +   '<i class="fa fa-ban"></i> ' + esc((r && r.msg) || 'No se pudo unificar.')
                          + '</div>'
                        );
                        $('html, body').animate({ scrollTop: 0 }, 300);
                        $('#uf-aplicar').prop('disabled', false).html('<i class="fa fa-object-group"></i> Unificar fundos');
                    }
                }, 'json').fail(function () {
                    $('.message').html('<div class="alert alert-danger"><i class="fa fa-ban"></i> Error de conexión.</div>');
                    $('#uf-aplicar').prop('disabled', false).html('<i class="fa fa-object-group"></i> Unificar fundos');
                });
            }
        });
    });

    /* ============================================================
       INIT
       ============================================================ */
    $('#uf-q-usuario').focus();

    // Cargar preselección si viene desde index.php con ?preseleccion=1,2,3
    if (PRESELECCION_IDS.length) {
        (function cargarPreseleccion() {
            log('Cargando preselección:', PRESELECCION_IDS);
            $.post(CTRL, { action: 'unificar_buscar_fundos', q: '' }, function (r) {
                if (!r || !r.ok) return;
                var encontrados = [];
                $.each(r.fundos || [], function (i, f) {
                    if (PRESELECCION_IDS.indexOf(parseInt(f.id, 10)) !== -1) {
                        seleccion[f.id] = f;
                        encontrados.push(f);
                    }
                });
                if (encontrados.length) {
                    var nombres = $.map(encontrados, function (f) { return esc(f.nombre); }).join(', ');
                    $('#uf-preseleccion-aviso').html(
                        '<div class="uf-alert uf-alert-info">'
                      +   '<i class="fa fa-info-circle"></i>'
                      +   '<div><b>' + encontrados.length + ' fundo(s) preseleccionado(s):</b> ' + nombres + '. '
                      +   'Elige el usuario destino para continuar.</div>'
                      + '</div>'
                    ).show();
                    pintarSeleccion();
                }
            }, 'json');
        })();
    }

    log('Wizard listo. Preselección:', PRESELECCION_IDS);
    log('window.ufSeleccionarUsuario disponible:', typeof window.ufSeleccionarUsuario);

});
</script>

<?php include_once("../layouts/pie.php") ?>