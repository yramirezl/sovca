<?php
// =============================================================================
// SOVCA · envía por correo el respaldo diario de la base de datos
// -----------------------------------------------------------------------------
// Uso:
//     php alertas/controlador_email_backup.php
//     php alertas/controlador_email_backup.php --fecha=29-10-2026
//     php alertas/controlador_email_backup.php --to=otro@dominio.com
//     php alertas/controlador_email_backup.php --dry-run
//
// Antes este controlador intentaba enviar SIEMPRE, aunque el archivo no
// existiera: el correo salía como si el respaldo se hubiera generado. Ahora
// valida el archivo y devuelve código de salida distinto de cero, para que el
// cron pueda enterarse.
// =============================================================================
require_once __DIR__ . '/enviar_backup.php';
date_default_timezone_set('America/Caracas');

$opciones = array(
    'fecha'   => date('d-m-Y'),
    // Destinatario del respaldo. Se puede cambiar sin tocar código con
    // SOVCA_BACKUP_EMAIL (entorno de PHP-FPM o del crontab).
    'to'      => (getenv('SOVCA_BACKUP_EMAIL') ?: 'backupglobalsovcabovs@gmail.com'),
    'dry-run' => false,
);

$argumentos = isset($argv) && is_array($argv) ? array_slice($argv, 1) : array();
foreach ($argumentos as $arg) {
    if (strpos($arg, '--fecha=') === 0) {
        $opciones['fecha'] = substr($arg, 8);
    } elseif (strpos($arg, '--to=') === 0) {
        $opciones['to'] = substr($arg, 5);
    } elseif ($arg === '--dry-run') {
        $opciones['dry-run'] = true;
    }
}

$fecha   = $opciones['fecha'];
$Path_db = "/opt/backups/databases/sovca/respaldo_db_sovca_$fecha.tar.gz";
$adjunto = 'db_sovca_' . $fecha;

if (!is_file($Path_db) || filesize($Path_db) === 0) {
    $msg = "No existe el respaldo de la fecha $fecha ($Path_db). " .
           "Ejecute primero scripts/cron/backup.sh.";
    fwrite(STDERR, $msg . PHP_EOL);
    error_log('[SOVCA mail] ' . $msg);
    exit(1);
}

if ($opciones['dry-run']) {
    echo 'se enviaria ' . $Path_db . ' (' . round(filesize($Path_db) / 1048576, 2) .
         ' MB) a ' . $opciones['to'] . PHP_EOL;
    exit(0);
}

$enviar_correo = new Enviar_Correo();
$ok = $enviar_correo->enviar_database($opciones['to'], $Path_db, $adjunto);

echo ($ok ? 'OK' : 'FALLO') . ' · ' . $Path_db . ' -> ' . $opciones['to'] . PHP_EOL;
exit($ok ? 0 : 1);



