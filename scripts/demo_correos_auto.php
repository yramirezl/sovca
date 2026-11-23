<?php
/**
 * =====================================================================
 * scripts/demo_correos_auto.php — SECUENCIA DE CORREOS DE LA DEMO
 * ---------------------------------------------------------------------
 * Envía los correos automáticos a quienes crearon una cuenta demo:
 *
 *   Día 0   bienvenida con las bondades del sistema
 *   Día 7   seguimiento (con datos: experiencia y compra;
 *           sin datos: ayuda para empezar)
 *   Día 14  aviso de que queda 1 día de prueba (con/sin datos)
 *   Día 22  rescate después del vencimiento (con/sin datos)
 *   Día 30  último recordatorio (con/sin datos)
 *
 * Lo pensado es ejecutarlo una vez al día desde el cron. Es idempotente:
 * cada etapa se envía una sola vez por cuenta (tabla demo_correos_auto)
 * y quien ya es cliente oficial no recibe nada.
 *
 * USO
 *   php scripts/demo_correos_auto.php
 *   php scripts/demo_correos_auto.php --dry-run            # no envía nada
 *   php scripts/demo_correos_auto.php --limite=20          # tope por corrida
 *   php scripts/demo_correos_auto.php --usuario=LASCOLINAS # solo una cuenta
 *   php scripts/demo_correos_auto.php --etapa=dia7         # solo una etapa
 *
 * CRON (ver scripts/cron/README.md)
 *   0 9 * * * /opt/backups/script/sovca/demo_correos_auto.sh >> /opt/logs/demo_correos.log 2>&1
 *
 * Para pruebas sin SMTP real: SOVCA_MAIL_ARCHIVO=1 (los correos se guardan
 * en file/correo en lugar de enviarse).
 * =====================================================================
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script solo se ejecuta por consola.\n");
}

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED & ~E_NOTICE);
date_default_timezone_set('America/Caracas');

require_once __DIR__ . '/../config/demo.php';
require_once __DIR__ . '/../lib/demo_helper.php';
require_once __DIR__ . '/../lib/mensajes_demo_helper.php';
require_once __DIR__ . '/../lib/demo_correos_auto_helper.php';

$opciones = getopt('', array('dry-run', 'limite::', 'etapa::', 'usuario::', 'verbose', 'ayuda', 'help'));

if (isset($opciones['ayuda']) || isset($opciones['help'])) {
    echo "Secuencia de correos automáticos para usuarios demo\n\n";
    echo "  php scripts/demo_correos_auto.php [--dry-run] [--limite=N]\n";
    echo "                                    [--etapa=bienvenida|dia7|dia14|dia22|dia30]\n";
    echo "                                    [--usuario=USUARIO] [--verbose]\n\n";
    echo "  --dry-run   Muestra qué se enviaría, sin enviar ni registrar nada.\n";
    echo "  --limite=N  Máximo de correos por corrida (por defecto 40).\n";
    echo "  --etapa=X   Procesa solo esa etapa (útil para probar plantillas).\n";
    echo "  --usuario=X Procesa solo esa cuenta demo.\n";
    exit(0);
}

$dryRun  = isset($opciones['dry-run']);
$verbose = isset($opciones['verbose']);
$limite  = isset($opciones['limite']) && $opciones['limite'] !== false ? (int) $opciones['limite'] : 40;
$etapa   = isset($opciones['etapa']) && $opciones['etapa'] !== false ? (string) $opciones['etapa'] : '';
$usuario = isset($opciones['usuario']) && $opciones['usuario'] !== false ? (string) $opciones['usuario'] : '';

if ($etapa !== '' && !isset(bovs_demo_auto_etapas()[$etapa])) {
    fwrite(STDERR, "Etapa desconocida: $etapa\n");
    fwrite(STDERR, "Use: " . implode(', ', array_keys(bovs_demo_auto_etapas())) . "\n");
    exit(2);
}

if ($limite <= 0) {
    $limite = 40;
}

$log = function ($texto) use ($verbose, $dryRun) {
    /* En modo normal solo se muestran los envíos y los errores; con
       --verbose también los omitidos por "ya es cliente" o por fecha. */
    if (!$verbose && !$dryRun && strpos($texto, 'enviado:') === false && strpos($texto, 'ERROR') === false) {
        return;
    }
    echo '[' . date('Y-m-d H:i:s') . '] ' . $texto . "\n";
};

echo '[' . date('Y-m-d H:i:s') . '] inicio secuencia demo'
    . ($dryRun ? ' [dry-run]' : '')
    . ($etapa !== '' ? ' etapa=' . $etapa : '')
    . ($usuario !== '' ? ' usuario=' . $usuario : '')
    . "\n";

$resumen = bovs_demo_auto_procesar(array(
    'dry_run' => $dryRun,
    'limite'  => $limite,
    'etapa'   => $etapa,
    'usuario' => $usuario,
    'verbose' => $verbose,
    'log'     => $log,
));

if (isset($resumen['error'])) {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $resumen['error'] . "\n";
    exit(1);
}

$etapas = array();
foreach ($resumen['por_etapa'] as $clave => $cant) {
    $etapas[] = $clave . '=' . $cant;
}

echo '[' . date('Y-m-d H:i:s') . '] fin secuencia demo: '
    . 'revisados=' . $resumen['revisados']
    . ' enviados=' . $resumen['enviados']
    . ' errores=' . $resumen['errores']
    . ' omitidos=' . $resumen['omitidos']
    . (count($etapas) ? ' (' . implode(', ', $etapas) . ')' : '')
    . "\n";

exit($resumen['errores'] > 0 ? 1 : 0);
