<?php
/**
 * =====================================================================
 * SOVCA | Diagnóstico de conexiones a PostgreSQL
 * ---------------------------------------------------------------------
 * Uso (solo línea de comandos):
 *
 *   php scripts/diagnostico_conexiones.php
 *   php scripts/diagnostico_conexiones.php --detalle
 *
 * Muestra:
 *   · max_connections y conexiones actuales por base / usuario / estado
 *   · conexiones "idle in transaction" (las que más daño hacen)
 *   · con --detalle, todas las conexiones con su antigüedad y query
 *
 * No modifica nada y no requiere sesión de la aplicación.
 * =====================================================================
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo 'Este diagnóstico se ejecuta por línea de comandos.';
    exit;
}

require_once __DIR__ . '/../config/db.php';

$detalle = in_array('--detalle', $argv, true);

$host = SOVCA_DB_HOST;
$port = SOVCA_DB_PORT;
$user = SOVCA_DB_USER;
$pass = SOVCA_DB_PASS;
$base = SOVCA_DB_NAME;

/* Se consulta a la base administrativa: pg_stat_activity es global. */
$dsn = 'pgsql:host=' . $host . ';port=' . $port . ';dbname=' . $base;

try {
    $pdo = new PDO($dsn, $user, $pass, array(
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => 5,
    ));
} catch (Throwable $e) {
    fwrite(STDERR, 'No se pudo conectar a PostgreSQL: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

$max = (int) $pdo->query('SHOW max_connections')->fetchColumn();

$filtro = "WHERE backend_type = 'client backend'";

$total = (int) $pdo->query("SELECT count(*) FROM pg_stat_activity $filtro")->fetchColumn();
$idle  = (int) $pdo->query("SELECT count(*) FROM pg_stat_activity $filtro AND state = 'idle'")->fetchColumn();
$act   = (int) $pdo->query("SELECT count(*) FROM pg_stat_activity $filtro AND state = 'active'")->fetchColumn();
$idleTx = (int) $pdo->query("SELECT count(*) FROM pg_stat_activity $filtro AND state LIKE 'idle in transaction%'")->fetchColumn();

echo str_repeat('=', 62) . PHP_EOL;
echo ' SOVCA · Conexiones PostgreSQL' . PHP_EOL;
echo str_repeat('=', 62) . PHP_EOL;
printf(" Servidor            : %s:%s / %s\n", $host, $port, $base);
printf(" max_connections     : %d\n", $max);
printf(" Conexiones actuales : %d  (%.0f%% de %d)\n", $total, $max > 0 ? ($total * 100 / $max) : 0, $max);
printf("   activas           : %d\n", $act);
printf("   idle              : %d\n", $idle);
printf("   idle in transaction: %d\n", $idleTx);
echo PHP_EOL;

$porBase = $pdo->query(
    "SELECT coalesce(datname, '(sin base)') AS base,
            coalesce(usename, '-')           AS usuario,
            coalesce(state, '-')             AS estado,
            count(*)                         AS n
       FROM pg_stat_activity
      $filtro
      GROUP BY 1, 2, 3
      ORDER BY n DESC"
)->fetchAll();

echo " Por base / usuario / estado" . PHP_EOL;
echo str_repeat('-', 62) . PHP_EOL;
printf(" %-24s %-14s %-18s %4s\n", 'BASE', 'USUARIO', 'ESTADO', 'N');
foreach ($porBase as $f) {
    printf(" %-24s %-14s %-18s %4d\n", $f['base'], $f['usuario'], $f['estado'], $f['n']);
}
echo PHP_EOL;

if ($idleTx > 0) {
    $tx = $pdo->query(
        "SELECT pid, datname, usename,
                now() - state_change AS duracion,
                left(regexp_replace(coalesce(query, ''), '\\s+', ' ', 'g'), 90) AS consulta
           FROM pg_stat_activity
          $filtro AND state LIKE 'idle in transaction%'
          ORDER BY state_change ASC
          LIMIT 10"
    )->fetchAll();

    echo " ⚠ Conexiones con transacción abierta sin terminar" . PHP_EOL;
    echo str_repeat('-', 62) . PHP_EOL;
    foreach ($tx as $f) {
        printf(" pid=%s base=%s usuario=%s inactiva=%s\n   %s\n",
            $f['pid'], $f['datname'], $f['usename'], $f['duracion'], $f['consulta']);
    }
    echo PHP_EOL;
}

if ($detalle) {
    $filas = $pdo->query(
        "SELECT pid, datname, usename, state, client_addr,
                now() - backend_start  AS edad,
                now() - state_change   AS inactiva,
                coalesce(application_name, '') AS app,
                left(regexp_replace(coalesce(query, ''), '\\s+', ' ', 'g'), 80) AS consulta
           FROM pg_stat_activity
          $filtro
          ORDER BY backend_start ASC"
    )->fetchAll();

    echo " Detalle de conexiones" . PHP_EOL;
    echo str_repeat('-', 62) . PHP_EOL;
    foreach ($filas as $f) {
        printf(" pid=%s base=%s estado=%s edad=%s inactiva=%s app=%s\n   %s\n",
            $f['pid'], $f['datname'], $f['state'], $f['edad'], $f['inactiva'], $f['app'], $f['consulta']);
    }
    echo PHP_EOL;
}

$pct = $max > 0 ? ($total * 100 / $max) : 0;
echo " Veredicto" . PHP_EOL;
echo str_repeat('-', 62) . PHP_EOL;
if ($idleTx > 0) {
    echo " · Hay transacciones abiertas sin commit/rollback: revísalas primero.\n";
}
if ($pct >= 80) {
    echo " · Uso crítico de conexiones. Considera PgBouncer o subir max_connections.\n";
} elseif ($pct >= 60) {
    echo " · Uso alto. Cada request web consume al menos una conexión.\n";
} else {
    echo " · Nivel saludable en este momento.\n";
}
echo " · Recuerda: sin pooler, el pico ≈ requests concurrentes × conexiones por request.\n";
echo " · Revisa también la cantidad de workers de PHP-FPM.\n";
echo PHP_EOL;
