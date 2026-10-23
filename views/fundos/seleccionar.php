<?php
require_once '../../lib/validar_session.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['idusuariox'])) {
    header('Location: ../login/');
    exit;
}

require_once '../../lib/multifundo_helper.php';
$uid    = (int)$_SESSION['idusuariox'];
$fundos = mf_listar_fundos_usuario($uid);
$total  = count($fundos);

require_once '../../models/Musuario.php';
$usr_arr = Musuario::find('all', array('conditions' => array('id=?', $uid)));
$max_fundos = ($usr_arr && isset($usr_arr[0])) ? (int)$usr_arr[0]->max_fundos : 1;

// Auto-selección si solo tiene 1
if ($total === 1) {
    if (mf_cargar_fundo_en_sesion($uid, (int)$fundos[0]->mfundos_id)) {
        header('Location: ../inicio/');
        exit;
    }
}

// Sin fundos → a crear
if ($total === 0) {
    header('Location: ../fondos/frm_registrar');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Seleccionar Fundo · BOVS</title>
<link rel="stylesheet" href="../../bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="../../css/fontawesome6/css/all.min.css">
<link rel="stylesheet" href="../../css/fontawesome6/css/v4-shims.min.css">
<style>
    body {
        background: linear-gradient(135deg, #ebf5ee 0%, #e1eee5 100%);
        min-height: 100vh;
        display: flex; align-items: center; justify-content: center;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
        padding: 20px;
    }
    .card-sel {
        background: #fff;
        max-width: 640px; width: 100%;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0,0,0,.08);
        overflow: hidden;
    }
    .card-head {
        background: linear-gradient(135deg, #234e35, #2c6e46);
        color: #fff;
        padding: 28px 24px;
        text-align: center;
    }
    .card-head h1 { font-size: 22px; margin: 0 0 6px; font-weight: 700; }
    .card-head p  { font-size: 13px; opacity: .9; margin: 0; }
    .card-body-sel { padding: 24px; }
    .fundo-row {
        display: flex; align-items: center; gap: 14px;
        border: 2px solid #e0e0e0;
        border-radius: 10px;
        padding: 16px;
        margin-bottom: 12px;
        cursor: pointer;
        transition: all .15s;
        background: #fff;
        text-align: left;
    }
    .fundo-row:hover {
        border-color: #234e35;
        box-shadow: 0 4px 12px rgba(35,78,53,.15);
        transform: translateY(-1px);
    }
    .fundo-avatar {
        width: 52px; height: 52px;
        border-radius: 50%;
        background: linear-gradient(135deg, #234e35, #2c6e46);
        color: #fff;
        display: flex; align-items: center; justify-content: center;
        font-size: 22px; font-weight: 700;
        flex-shrink: 0;
    }
    .fundo-info { flex: 1; min-width: 0; }
    .fundo-nombre { font-size: 16px; font-weight: 600; color: #2c3e50; margin-bottom: 2px; }
    .fundo-meta { font-size: 12px; color: #888; }
    .fundo-meta span { margin-right: 12px; }
    .fundo-arrow { color: #ccc; font-size: 22px; }
    .fundo-row:hover .fundo-arrow { color: #234e35; }
    .card-foot {
        padding: 16px 24px;
        border-top: 1px solid #f0f0f0;
        text-align: center;
        background: #fafbfc;
    }
    .card-foot a {
        color: #234e35; font-size: 13px; font-weight: 600; text-decoration: none;
    }
    .card-foot a:hover { text-decoration: underline; }
    .badge-cuota {
        display: inline-block;
        background: #fff8e1;
        color: #946c00;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 11px;
        margin-top: 8px;
    }
</style>
</head>
<body>

<div class="card-sel">
    <div class="card-head">
        <h1><i class="fa fa-building-o"></i> Seleccione un Fundo</h1>
        <p>Tiene acceso a <?php echo $total; ?> fundo<?php echo $total != 1 ? 's' : ''; ?></p>
    </div>

    <div class="card-body-sel">
        <?php foreach ($fundos as $f): ?>
            <button type="button" class="fundo-row" data-id="<?php echo (int)$f->mfundos_id; ?>">
                <div class="fundo-avatar">
                    <?php echo htmlspecialchars(mb_strtoupper(mb_substr($f->nombre, 0, 1))); ?>
                </div>
                <div class="fundo-info">
                    <div class="fundo-nombre"><?php echo htmlspecialchars($f->nombre); ?></div>
                    <div class="fundo-meta">
                        <span><i class="fa fa-id-card"></i> <?php echo htmlspecialchars($f->cid_rif ? $f->cid_rif : '—'); ?></span>
                        <span><i class="fa fa-cow"></i> <?php echo (int)$f->total_animales; ?> animales</span>
                        <span><i class="fa fa-tasks"></i> Plan: <?php echo htmlspecialchars($f->plan_nombre ? $f->plan_nombre : '—'); ?></span>
                    </div>
                </div>
                <div class="fundo-arrow">›</div>
            </button>
        <?php endforeach; ?>
    </div>

    <div class="card-foot">
        <?php if ($total < $max_fundos): ?>
            <a href="frm_registrar">+ Crear un nuevo fundo</a><br>
            <span class="badge-cuota"><?php echo $total; ?> de <?php echo $max_fundos; ?> fundos usados</span>
        <?php else: ?>
            <span class="badge-cuota">Ha alcanzado el límite de <?php echo $max_fundos; ?> fundos</span>
        <?php endif; ?>
        <br><br>
        <a href="../logout" style="color:#999;">Cerrar sesión</a>
    </div>
</div>

<script>
document.querySelectorAll('.fundo-row').forEach(function(el) {
    el.addEventListener('click', function() {
        var id = this.getAttribute('data-id');
        this.style.opacity = '0.5';
        this.disabled = true;

        var fd = new FormData();
        fd.append('action', 'cambiar_fundo');
        fd.append('fundo_id', id);

        fetch('../../controllers/mfundos_controller.php', {
            method: 'POST',
            body: fd
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.ok) {
                window.location.href = '../inicio/';
            } else {
                alert(data.msg || 'Error al cambiar de fundo');
                el.style.opacity = '1';
                el.disabled = false;
            }
        })
        .catch(function() {
            alert('Error de conexión');
            el.style.opacity = '1';
            el.disabled = false;
        });
    });
});
</script>

</body>
</html>