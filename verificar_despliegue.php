<?php
/**
 * COMPROBACIÓN TEMPORAL DE DESPLIEGUE  ·  SOVCA
 * ---------------------------------------------------------------------------
 * Uso:    subir este archivo a la raíz del proyecto en el servidor y abrir
 *         https://TU-DOMINIO/sovca/verificar_despliegue.php?k=sovca-verif-2026
 * Reset:  ...?k=sovca-verif-2026&reset=1   (vacía el OPcache de PHP)
 *
 * No imprime credenciales ni datos de negocio: solo dice si cada archivo
 * clave está en disco y si contiene el marcador del código nuevo.
 *
 * BORRAR ESTE ARCHIVO DEL SERVIDOR DESPUÉS DE USARLO.
 */

$TOKEN = 'sovca-verif-2026';

if (!isset($_GET['k']) || !hash_equals($TOKEN, (string) $_GET['k'])) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('403 · token inválido');
}

$reset = isset($_GET['reset']) && $_GET['reset'] === '1';
$resetOk = null;
if ($reset && function_exists('opcache_reset')) {
    $resetOk = @opcache_reset();
}

/**
 * Cada entrada: archivo => [marcador_nuevo, marcador_viejo_que_no_debe_estar]
 */
$controles = array(
    // Gestor de Reportes Parametrizables (buscador interactivo)
    'views/dashboard/dashboard.php'                 => array('sel_rep_cod', 'Un solo código'),
    // Salida de reportes (nomenclatura)
    'Reportes/lib/reporte_contexto_helper.php'      => array('bovs_reporte_contexto', null),
    'Reportes/reportes/sovca/rpl.php'                => array('contexto_detalle', null),
    'Reportes/reportes/sovca/rna.php'                => array('contexto_detalle', null),
    'Reportes/reportes/sovca/pdfprodlechera.php'     => array('contexto_detalle', null),
    'Reportes/reportes/sovca/pdfreg_nacimientos.php' => array('contexto_detalle', null),
    // Nacimientos: Entrar en lactancia / fecha de secado
    'views/nacimientos/frm_registrar.php'           => array('Entrar en lactancia', 'A propósito'),
    'views/nacimientos/frm_editar.php'              => array('Entrar en lactancia', null),
    'views/nacimientos/frm_ficha.php'               => array('FICHA_PENDIENTE', null),
    // Alta desde nacimiento + raza esperada
    'views/animales/frm_registrar.php'              => array('bovsFijarSelect', null),
    // Backend
    'controllers/manimales_controller.php'          => array('listar_animales_reporte', null),
    // Assets del selector y del teclado
    'css/servicio-selector.css'                     => array('.sel-animal', null),
    'js/servicio-selector.js'                       => array('bovsSelectorAnimal', null),
    'js/bovs-teclado.js'                            => array('ms_direccion', null),
    // PWA
    'app_offline/versiones.json'                    => array('4.45', null),
    'app_offline/service-worker.php'                => array('VERSION_CACHE', null),
);

function campo($archivo, $marcador)
{
    if (!is_file($archivo)) {
        return array('ok' => false, 'texto' => 'no existe');
    }
    if ($marcador === null) {
        return array('ok' => true, 'texto' => '—');
    }
    $c = @file_get_contents($archivo);
    if ($c === false) {
        return array('ok' => false, 'texto' => 'ilegible');
    }
    $n = substr_count($c, $marcador);
    return array(
        'ok'    => $n > 0,
        'texto' => $n > 0 ? 'sí (' . $n . ')' : 'NO',
    );
}

$filas = array();
$totalOk = 0;
$totalMal = 0;

foreach ($controles as $ruta => $marcadores) {
    $abs = __DIR__ . '/' . $ruta;
    $existe = is_file($abs);

    $nuevo = campo($abs, $marcadores[0]);
    $viejo = campo($abs, isset($marcadores[1]) ? $marcadores[1] : null);

    $bien = $existe && $nuevo['ok'] && ($viejo['texto'] === '—' || strpos($viejo['texto'], 'NO') === 0);

    if ($bien) {
        $totalOk++;
    } else {
        $totalMal++;
    }

    $filas[] = array(
        'ruta'   => $ruta,
        'existe' => $existe,
        'size'   => $existe ? number_format(filesize($abs) / 1024, 1) . ' KB' : '—',
        'mtime'  => $existe ? date('d-m-Y H:i:s', filemtime($abs)) : '—',
        'nuevo'  => $nuevo['texto'],
        'viejo'  => $viejo['texto'],
        'bien'   => $bien,
    );
}

$opcacheOn = function_exists('opcache_get_status')
    ? (bool) @opcache_get_status(false)
    : false;
$opStats = $opcacheOn ? @opcache_get_status(false) : array();
$validaTs = ini_get('opcache.validate_timestamps');
$revalidate = ini_get('opcache.revalidate_freq');

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Verificación de despliegue · SOVCA</title>
<style>
    body { font: 14px/1.45 -apple-system, Segoe UI, Roboto, Arial, sans-serif; margin: 24px; color: #1f2d3d; background: #f5f7fa; }
    h1 { font-size: 20px; margin: 0 0 4px; }
    p.sub { margin: 0 0 18px; color: #5b6b7c; }
    table { border-collapse: collapse; width: 100%; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
    th, td { border-bottom: 1px solid #e6ebf1; padding: 7px 10px; text-align: left; vertical-align: top; }
    th { background: #eef3f8; font-size: 12px; text-transform: uppercase; letter-spacing: .03em; color: #46586b; }
    tr.mal td { background: #fff4f4; }
    .ok { color: #1c7c46; font-weight: 600; }
    .no { color: #b3261e; font-weight: 600; }
    .res { margin: 16px 0; padding: 12px 14px; border-radius: 6px; font-weight: 600; }
    .res.b { background: #e6f7ec; color: #1c7c46; }
    .res.m { background: #fdeceb; color: #b3261e; }
    .box { margin-top: 22px; background: #fff; padding: 14px 16px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
    code { background: #eef3f8; padding: 1px 5px; border-radius: 3px; }
    .aviso { margin-top: 22px; padding: 12px 14px; background: #fff8e1; border-left: 4px solid #f0b429; }
</style>
</head>
<body>

<h1>Verificación de despliegue · SOVCA</h1>
<p class="sub">Comprueba si los archivos del despliegue están realmente en este servidor.</p>

<div class="box">
    <strong>Ruta real del proyecto:</strong> <code><?= htmlspecialchars(__DIR__) ?></code><br>
    <strong>PHP:</strong> <?= htmlspecialchars(PHP_VERSION) ?> (<?= htmlspecialchars(php_sapi_name()) ?>)<br>
    <strong>OPcache:</strong>
    <?= $opcacheOn ? 'activo' : 'inactivo' ?>
    · <code>validate_timestamps=<?= htmlspecialchars((string) $validaTs) ?></code>
    · <code>revalidate_freq=<?= htmlspecialchars((string) $revalidate) ?></code>
    <?php if ($opcacheOn && isset($opStats['opcache_statistics'])): ?>
        · archivos en caché: <?= (int) $opStats['opcache_statistics']['num_cached_scripts'] ?>
    <?php endif; ?>
    <?php if ($reset): ?>
        <br><strong>Reset de OPcache solicitado:</strong>
        <?= $resetOk ? '<span class="ok">hecho</span>' : '<span class="no">no disponible / falló</span>' ?>
    <?php endif; ?>
</div>

<div class="res <?= $totalMal === 0 ? 'b' : 'm' ?>">
    <?= (int) $totalOk ?> archivo(s) con el código nuevo ·
    <?= (int) $totalMal ?> archivo(s) desactualizado(s) o ausente(s)
</div>

<table>
    <thead>
        <tr>
            <th>Archivo</th>
            <th>Existe</th>
            <th>Tamaño</th>
            <th>Modificado (mtime)</th>
            <th>Marcador nuevo</th>
            <th>Marcador viejo</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($filas as $f): ?>
        <tr class="<?= $f['bien'] ? '' : 'mal' ?>">
            <td><code><?= htmlspecialchars($f['ruta']) ?></code></td>
            <td class="<?= $f['existe'] ? 'ok' : 'no' ?>"><?= $f['existe'] ? 'sí' : 'NO' ?></td>
            <td><?= htmlspecialchars($f['size']) ?></td>
            <td><?= htmlspecialchars($f['mtime']) ?></td>
            <td class="<?= strpos($f['nuevo'], 'NO') === 0 ? 'no' : 'ok' ?>"><?= htmlspecialchars($f['nuevo']) ?></td>
            <td><?= htmlspecialchars($f['viejo']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<div class="aviso">
    Las filas en rojo son las que hay que volver a subir.
    Después de subirlas, recarga esta página; si siguen rojas con la fecha de
    modificación correcta, pulsa
    <a href="?k=<?= htmlspecialchars($TOKEN) ?>&amp;reset=1">vaciar OPcache</a>
    y vuelve a comprobar.
</div>

<div class="box">
    <strong>Recordatorio:</strong> borra <code>verificar_despliegue.php</code> del servidor cuando termines.
</div>

</body>
</html>
