<?php
/**
 * scripts/unicidad_usuarios.php
 * ---------------------------------------------------------------------------
 * Fortalece la unicidad de `username` y `email` en la tabla `musuarios`.
 *
 *   1. Reporta los duplicados existentes (comparando sin distinguir mayúsculas).
 *   2. Crea índices únicos PARCIALES que solo aplican a usuarios ACTIVOS
 *      (status = 1), que es la misma regla que usa la aplicación y el login
 *      (la vista `vusuarios` filtra status = 1).
 *
 *      uq_musuarios_username_activo  ON musuarios (upper(username))
 *                                    WHERE status = 1
 *      uq_musuarios_email_activo     ON musuarios (lower(email))
 *                                    WHERE status = 1
 *                                      AND coalesce(email, '') <> ''
 *
 * Es idempotente (IF NOT EXISTS). Si existen duplicados que impiden crear un
 * índice, lo informa con el detalle y continúa con el otro.
 *
 * Nada de esto borra ni modifica datos: solo agrega índices.
 *
 * Uso (desde la raíz del proyecto):
 *   php scripts/unicidad_usuarios.php [--dry-run] [--host= --port= --db= --user= --pass=] [--help]
 *
 * La conexión se lee de config/conexion.php (DSN pgsql://...).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script solo puede ejecutarse por consola (CLI).\n");
}

$ROOT = dirname(__DIR__);

// ─────────────────────────────  Argumentos  ─────────────────────────────
$opts = array();
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z0-9\-]+)(?:=(.*))?$/i', $a, $m)) {
        $opts[strtolower($m[1])] = isset($m[2]) ? $m[2] : true;
    }
}

if (isset($opts['help'])) {
    echo "Uso: php scripts/unicidad_usuarios.php [--dry-run]\n";
    echo "                                        [--host= --port= --db= --user= --pass=]\n";
    exit(0);
}

$dryRun = isset($opts['dry-run']);

require_once $ROOT . '/config/db.php';
$cfg = sovca_db_config();
foreach (array('host', 'port', 'db', 'user', 'pass') as $k) {
    if (isset($opts[$k]) && $opts[$k] !== true) {
        $cfg[$k] = (string) $opts[$k];
    }
}

try {
    $pdo = new PDO(
        "pgsql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['db']}",
        $cfg['user'],
        $cfg['pass'],
        array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
    );
} catch (PDOException $e) {
    fwrite(STDERR, "ERROR de conexión (host={$cfg['host']} db={$cfg['db']}): " . $e->getMessage() . "\n");
    exit(1);
}

/** Indica si un índice ya existe. */
function uniq_indice_existe(PDO $pdo, $nombre)
{
    $st = $pdo->prepare("SELECT 1 FROM pg_indexes WHERE schemaname = 'public' AND indexname = ?");
    $st->execute(array($nombre));
    return (bool) $st->fetchColumn();
}

/** Lista grupos duplicados para una expresión, opcionalmente solo activos. */
function uniq_duplicados(PDO $pdo, $expresion, $soloActivos)
{
    $w = $soloActivos ? 'AND status = 1' : '';
    if ($expresion === 'lower(email)') {
        $w .= " AND coalesce(email, '') <> ''";
    }
    $sql = "SELECT {$expresion} AS valor, count(*) AS n,
                   array_agg(id ORDER BY id) AS ids,
                   array_agg(status ORDER BY id) AS estados
              FROM musuarios
             WHERE 1 = 1 {$w}
             GROUP BY 1 HAVING count(*) > 1
             ORDER BY 1";
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

function uniq_imprimir(PDO $pdo, $titulo, $expresion)
{
    $act = uniq_duplicados($pdo, $expresion, true);
    $tod = uniq_duplicados($pdo, $expresion, false);

    printf("   %-34s activos: %d grupo(s)  ·  totales: %d grupo(s)\n", $titulo . ':', count($act), count($tod));
    foreach ($tod as $d) {
        printf("      %-34s ids %-18s estados %s\n", $d['valor'], $d['ids'], $d['estados']);
    }
    return $act;
}

function uniq_crear(PDO $pdo, $nombre, $sql, $dryRun, array $duplicadosActivos)
{
    if (uniq_indice_existe($pdo, $nombre)) {
        echo "   índice {$nombre}: ya existía\n";
        return true;
    }
    if ($dryRun) {
        echo "   índice {$nombre}: [SIMULA] se crearía\n";
        return true;
    }
    try {
        $pdo->exec($sql);
        echo "   índice {$nombre}: CREADO\n";
        return true;
    } catch (PDOException $e) {
        echo "   índice {$nombre}: NO CREADO\n";
        echo "      → " . $e->getMessage() . "\n";
        if (!empty($duplicadosActivos)) {
            echo "      → Resuelve los duplicados entre usuarios ACTIVOS (desactiva una de las cuentas\n";
            echo "        o cámbiale el correo/usuario) y vuelve a ejecutar el script.\n";
        }
        return false;
    }
}

// ─────────────────────────────  Ejecución  ─────────────────────────────
echo "\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo " Unicidad de usuarios (username / email)";
echo $dryRun ? "   [SIMULACIÓN: no se crea nada]\n" : "\n";
echo "═══════════════════════════════════════════════════════════════\n";
printf(" Conexión : %s@%s:%s/%s\n", $cfg['user'], $cfg['host'], $cfg['port'], $cfg['db']);
echo "═══════════════════════════════════════════════════════════════\n\n";

echo "── Duplicados (no distingue mayúsculas) ───────────────────────\n";
$dupUserAct = uniq_imprimir($pdo, 'username', 'upper(username)');
$dupMailAct = uniq_imprimir($pdo, 'email',    'lower(email)');

echo "\n── Índices únicos (solo usuarios activos) ─────────────────────\n";
$okU = uniq_crear(
    $pdo,
    'uq_musuarios_username_activo',
    "CREATE UNIQUE INDEX IF NOT EXISTS uq_musuarios_username_activo
        ON musuarios (upper(username)) WHERE status = 1",
    $dryRun,
    $dupUserAct
);
$okM = uniq_crear(
    $pdo,
    'uq_musuarios_email_activo',
    "CREATE UNIQUE INDEX IF NOT EXISTS uq_musuarios_email_activo
        ON musuarios (lower(email)) WHERE status = 1 AND coalesce(email, '') <> ''",
    $dryRun,
    $dupMailAct
);

echo "\n═══════════════════════════════════════════════════════════════\n";
echo ' username: ' . ($okU ? 'protegido con índice único' : 'PENDIENTE (resolver duplicados)') . "\n";
echo ' email   : ' . ($okM ? 'protegido con índice único' : 'PENDIENTE (resolver duplicados)') . "\n";
echo " Nota: la aplicación ya valida username/email antes de guardar\n";
echo "       (los inactivos no cuentan y no pueden iniciar sesión).\n";
echo $dryRun ? " (SIMULACIÓN — no se cambió nada)\n" : "";
echo "═══════════════════════════════════════════════════════════════\n\n";

exit(($okU && $okM) ? 0 : 2);
