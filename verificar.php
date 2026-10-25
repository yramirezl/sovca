<?php
/**
 * PÁGINA PÚBLICA DE VERIFICACIÓN — No requiere login.
 * Valida token HMAC y muestra certificado de autenticidad.
 *
 * Compatible: PHP 7.4 y 8.3
 */

// ============================================================
// Forzar HTTPS (excepto en localhost/127.0.0.1)
// ============================================================
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
    $es_local = ($host === '127.0.0.1' || strpos($host, 'localhost') !== false);
    if (!$es_local && $host !== '') {
        $url = 'https://' . $host . $_SERVER['REQUEST_URI'];
        header('Location: ' . $url, true, 301);
        exit;
    }
}

// ============================================================
// Headers de seguridad
// ============================================================
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

/* Marca de versión: permite comprobar desde fuera si el servidor tiene
   ESTE archivo (curl -I .../verificar.php | grep X-Verificar-Version). */
header('X-Verificar-Version: 2026-10-25.2');

require_once __DIR__ . '/lib/firma.php';

/* ---------------------------------------------------------------------
   Si algo falla, que NUNCA se devuelva un 500 con el cuerpo vacío: se
   muestra un aviso y el detalle técnico queda en el log.
   --------------------------------------------------------------------- */
set_exception_handler(function ($e) {
    error_log('[verificar] ' . get_class($e) . ': ' . $e->getMessage()
        . ' en ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>Verificación no disponible</title></head>'
       . '<body style="font-family:Arial,Helvetica,sans-serif;background:#f5f7fa;padding:32px">'
       . '<div style="max-width:520px;margin:0 auto;background:#fff;border-top:4px solid #dc3545;border-radius:12px;padding:24px;box-shadow:0 10px 30px rgba(0,0,0,.08)">'
       . '<h3 style="color:#b02a37;margin:0 0 8px;font-size:18px">No se pudo verificar el certificado</h3>'
       . '<p style="color:#555;font-size:14px;line-height:1.5;margin:0">Inténtalo de nuevo en unos minutos. '
       . 'Si el problema continúa, contacta al administrador del sistema.</p>'
       . '</div></body></html>';
    exit;
});

/* ---------------------------------------------------------------------
   Los modelos hacen `require_once '../config/conexion.php'` con ruta
   RELATIVA, que se resuelve contra el DIRECTORIO DE TRABAJO. verificar.php
   vive en la raíz del proyecto, así que '../config' apuntaba a
   /var/www/config (no existe) y el require moría con un fatal → HTTP 500
   sin contenido.
   Se trabaja desde /controllers, el mismo punto de partida que usan los
   controladores, donde todas esas rutas relativas resuelven bien.
   --------------------------------------------------------------------- */
$bovs_cwd_anterior = getcwd();
$bovs_cwd_ok = false;
foreach (array(__DIR__ . '/controllers', __DIR__) as $bovs_cwd_cand) {
    if (is_dir($bovs_cwd_cand) && is_file($bovs_cwd_cand . '/../config/conexion.php')) {
        chdir($bovs_cwd_cand);
        $bovs_cwd_ok = true;
        break;
    }
}
if (!$bovs_cwd_ok) {
    error_log('[verificar] no se encontró un directorio de trabajo válido para los includes relativos');
}

/* ---------------------------------------------------------------------
   Red de seguridad para FATALES, que set_exception_handler NO captura:
   un `require` que no encuentra el archivo, memoria agotada o un error de
   compilación dentro de un include. Sin esto el navegador recibe un 500
   con el cuerpo vacío y no hay forma de saber qué pasó.
   El motivo técnico se registra en el log; se muestra en pantalla solo si
   se pide con ?diag=soporte (útil para diagnosticar en producción).
   --------------------------------------------------------------------- */
register_shutdown_function(function () {
    $error = error_get_last();
    if (!$error) {
        return;
    }
    $fatales = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR);
    if (!in_array($error['type'], $fatales, true)) {
        return;
    }

    error_log('[verificar] FATAL: ' . $error['message'] . ' en ' . $error['file'] . ':' . $error['line']);

    if (ob_get_length() === false || ob_get_length() === 0) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
        }
        $mostrar = (isset($_GET['diag']) && $_GET['diag'] === 'soporte');
        echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">'
           . '<meta name="viewport" content="width=device-width, initial-scale=1">'
           . '<title>Verificación no disponible</title></head>'
           . '<body style="font-family:Arial,Helvetica,sans-serif;background:#f5f7fa;padding:32px">'
           . '<div style="max-width:560px;margin:0 auto;background:#fff;border-top:4px solid #dc3545;border-radius:12px;padding:24px;box-shadow:0 10px 30px rgba(0,0,0,.08)">'
           . '<h3 style="color:#b02a37;margin:0 0 8px;font-size:18px">No se pudo verificar el certificado</h3>'
           . '<p style="color:#555;font-size:14px;line-height:1.5;margin:0">Inténtalo de nuevo en unos minutos. '
           . 'Si el problema continúa, contacta al administrador del sistema.</p>';
        if ($mostrar) {
            echo '<pre style="margin-top:14px;background:#f8f9fa;border:1px solid #e3e7ed;border-radius:8px;padding:10px;font-size:12px;white-space:pre-wrap">'
               . htmlspecialchars($error['message'] . "\n" . $error['file'] . ':' . $error['line'], ENT_QUOTES, 'UTF-8')
               . '</pre>';
        }
        echo '</div></body></html>';
    }
});

$token     = isset($_GET['t']) ? $_GET['t'] : '';
$id_animal = firma_verificar($token);
$valido    = ($id_animal !== false);
$animal    = null;
$error_msg = '';
$fecha_verif = date('d-m-Y H:i');

if ($valido) {
    require_once __DIR__ . '/models/Vanimale.php';
    require_once __DIR__ . '/models/Manimale.php';

    try {
        $data = Manimale::find('all', [
            'conditions' => ['id=? and status=1', $id_animal]
        ]);

        if ($data) {
            $rs = $data[0];

            // Raza
            $raza_txt = '';
            if ($rs->mrazas_id) {
                $q = Vanimale::find_by_sql("SELECT nombre FROM mrazas WHERE id=" . (int)$rs->mrazas_id . " LIMIT 1");
                if ($q) $raza_txt = $q[0]->nombre;
            }

            // Fundo
            $fundo_txt = '';
            if ($rs->mfundos_id) {
                $q = Vanimale::find_by_sql("SELECT nombre FROM mfundos WHERE id=" . (int)$rs->mfundos_id . " LIMIT 1");
                if ($q) $fundo_txt = $q[0]->nombre;
            }

            // Fecha nacimiento (robusto)
            $f_nac_iso = '';
            if ($rs->f_nacimiento) {
                if ($rs->f_nacimiento instanceof DateTime) {
                    $f_nac_iso = $rs->f_nacimiento->format('Y-m-d');
                } else {
                    $ts = strtotime($rs->f_nacimiento);
                    if ($ts) $f_nac_iso = date('Y-m-d', $ts);
                }
            }

            // Edad legible
            $edad_txt = '';
            if ($f_nac_iso) {
                $d = (new DateTime($f_nac_iso))->diff(new DateTime());
                $p = array();
                if ($d->y) $p[] = $d->y . ' año' . ($d->y != 1 ? 's' : '');
                if ($d->m) $p[] = $d->m . ' mes' . ($d->m != 1 ? 'es' : '');
                $edad_txt = $p ? implode(' ', $p) : $d->d . ' días';
            }

            // Foto
            $foto_url = '';
            if ($rs->imagen && file_exists(__DIR__ . '/dist/img/fotos_animales/' . $rs->imagen)) {
                $foto_url = 'dist/img/fotos_animales/' . $rs->imagen;
            }

            $sexo_txt = ($rs->sexo == 1) ? 'HEMBRA' : (($rs->sexo == 2) ? 'MACHO' : 'N/D');

            $animal = array(
                'codigo' => $rs->codigo_rgb_id_animal,
                'nombre' => $rs->nombre,
                'sexo'   => $sexo_txt,
                'raza'   => $raza_txt,
                'edad'   => $edad_txt,
                'f_nac'  => $f_nac_iso ? date('d-m-Y', strtotime($f_nac_iso)) : '',
                'fundo'  => $fundo_txt,
                'foto'   => $foto_url
            );
        } else {
            $valido    = false;
            $error_msg = 'El animal ya no está activo en la plataforma.';
        }
    } catch (Throwable $e) {
        /* Error de consulta (tabla/columna ausente, etc.): se informa igual. */
        error_log('[verificar] consulta: ' . $e->getMessage());
        $valido    = false;
        $error_msg = 'Error al consultar los datos. Inténtalo de nuevo en unos minutos.';
    }
} else {
    $error_msg = 'El código de verificación no es válido o fue alterado.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $valido ? 'Ficha verificada' : 'Verificación fallida'; ?> · SOVCA</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
        background: linear-gradient(135deg, #f5f7fa 0%, #e8eef5 100%);
        min-height: 100vh;
        display: flex; align-items: center; justify-content: center;
        padding: 20px;
    }
    .card {
        background: #fff;
        max-width: 520px; width: 100%;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0,0,0,.08);
        overflow: hidden;
    }
    .card-header {
        padding: 28px 24px;
        text-align: center;
        color: #fff;
    }
    .card-header.ok  { background: linear-gradient(135deg, #28a745, #20c997); }
    .card-header.err { background: linear-gradient(135deg, #dc3545, #c82333); }
    .card-header .icon { font-size: 52px; line-height: 1; margin-bottom: 10px; }
    .card-header h1 { font-size: 20px; font-weight: 700; letter-spacing: .5px; }
    .card-header p  { font-size: 13px; opacity: .9; margin-top: 4px; }
    .card-body { padding: 24px; }
    .animal-main {
        display: flex; gap: 16px; margin-bottom: 20px; align-items: flex-start;
    }
    .animal-foto {
        width: 100px; height: 100px; border-radius: 8px; object-fit: cover;
        border: 2px solid #3c8dbc; background: #f0f0f0; flex-shrink: 0;
    }
    .animal-foto-placeholder {
        display: flex; align-items: center; justify-content: center;
        color: #bbb; font-size: 11px;
    }
    .animal-info { flex: 1; min-width: 0; }
    .animal-codigo {
        font-size: 18px; font-weight: 700; color: #3c8dbc;
        margin-bottom: 4px; word-break: break-all;
    }
    .animal-nombre {
        font-size: 15px; font-weight: 600; color: #333; margin-bottom: 8px;
    }
    .tabla { width: 100%; font-size: 12px; border-collapse: collapse; }
    .tabla th {
        text-align: left; color: #888; font-weight: 600;
        padding: 3px 0; width: 42%; vertical-align: top;
    }
    .tabla td { padding: 3px 0; color: #333; }
    .sello {
        background: #f0f9ff; border-left: 4px solid #3c8dbc;
        padding: 14px 16px; border-radius: 4px;
        font-size: 12px; color: #555; margin-top: 16px;
    }
    .sello strong { color: #3c8dbc; }
    .error-msg {
        text-align: center; color: #666; font-size: 14px; padding: 20px 0;
    }
    .footer {
        text-align: center; font-size: 11px; color: #aaa;
        padding: 16px; border-top: 1px solid #f0f0f0;
    }
    @media (max-width: 480px) {
        .animal-main { flex-direction: column; align-items: center; text-align: center; }
        .animal-info { width: 100%; }
        .animal-codigo { font-size: 16px; }
        .tabla th { width: 45%; font-size: 11px; }
        .tabla td { font-size: 11.5px; }
    }
</style>
</head>
<body>

<div class="card">

    <?php if ($valido && $animal): ?>

        <div class="card-header ok">
            <div class="icon">✓</div>
            <h1>CERTIFICADO DE AUTENTICIDAD</h1>
            <p>Ficha verificada por la plataforma SOVCA</p>
        </div>

        <div class="card-body">

            <div class="animal-main">
                <?php if ($animal['foto']): ?>
                    <img class="animal-foto" src="<?php echo htmlspecialchars($animal['foto']); ?>" alt="Foto">
                <?php else: ?>
                    <div class="animal-foto animal-foto-placeholder">Sin foto</div>
                <?php endif; ?>

                <div class="animal-info">
                    <div class="animal-codigo"><?php echo htmlspecialchars($animal['codigo']); ?></div>
                    <div class="animal-nombre"><?php echo htmlspecialchars($animal['nombre']); ?></div>
                </div>
            </div>

            <table class="tabla">
                <tr><th>Sexo:</th>         <td><?php echo htmlspecialchars($animal['sexo']); ?></td></tr>
                <tr><th>Raza:</th>         <td><?php echo htmlspecialchars($animal['raza'] ? $animal['raza'] : '—'); ?></td></tr>
                <tr><th>Fecha nac.:</th>   <td><?php echo htmlspecialchars($animal['f_nac'] ? $animal['f_nac'] : '—'); ?></td></tr>
                <tr><th>Edad:</th>         <td><?php echo htmlspecialchars($animal['edad'] ? $animal['edad'] : '—'); ?></td></tr>
                <tr><th>Fundo origen:</th> <td><?php echo htmlspecialchars($animal['fundo'] ? $animal['fundo'] : '—'); ?></td></tr>
            </table>

            <div class="sello">
                <strong>✓ Autenticidad verificada</strong><br>
                Esta ficha fue emitida por la plataforma SOVCA y su integridad
                está garantizada mediante firma digital HMAC-SHA256.<br>
                <small>Verificado el <?php echo $fecha_verif; ?></small>
            </div>

        </div>

    <?php else: ?>

        <div class="card-header err">
            <div class="icon">✕</div>
            <h1>NO VERIFICADA</h1>
            <p>No se pudo validar la autenticidad</p>
        </div>

        <div class="card-body">
            <p class="error-msg">
                <?php echo htmlspecialchars($error_msg ? $error_msg : 'Código de verificación inválido.'); ?>
            </p>
        </div>

    <?php endif; ?>

    <div class="footer">
        © <?php echo date('Y'); ?> Ruted · Sistema de Gestión Ovina y Caprina SOVCA
    </div>

</div>

</body>
</html>