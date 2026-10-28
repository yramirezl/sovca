<?php
/**
 * =====================================================================
 * SOVCA | Configuración única de PostgreSQL
 * ---------------------------------------------------------------------
 * Todas las capas del sistema (aplicación, reportes, alertas, demo y
 * scripts de mantenimiento) deben leer de aquí sus credenciales.
 *
 * Solo hay DOS bases de datos de negocio:
 *   · SOVCA_DB_NAME       → base real            (por defecto `sovca`)
 *   · SOVCA_DB_DEMO_NAME  → base de demostración (por defecto `sovca_demo`)
 *
 * Orden de resolución de cada valor:
 *   1. constante ya definida por el entorno,
 *   2. variable de entorno (recomendado en producción),
 *   3. valor por defecto de desarrollo.
 *
 * Ejemplos de variables de entorno:
 *   SOVCA_DB_HOST=127.0.0.1
 *   SOVCA_DB_PORT=5432
 *   SOVCA_DB_NAME=sovca
 *   SOVCA_DB_DEMO_NAME=sovca_demo
 *   SOVCA_DB_USER=sovca_app
 *   SOVCA_DB_PASS=…
 *
 * También existe una configuración LOCAL por servidor:
 *   config/db.local.php  (ignorado por Git, no se sube)
 * Si ese archivo existe, sus constantes tienen prioridad. Es la forma
 * recomendada de editar credenciales en producción sin que un `git pull`
 * provoque conflictos. Hay una plantilla en `config/db.local.example.php`.
 * =====================================================================
 */

/* Configuración local del servidor. Se carga ANTES de definir valores para
   que pueda sobrescribirlos con constantes. Este archivo NO se versiona.

   Para una ejecución puntual con OTRO usuario (por ejemplo, los scripts que
   hacen DDL y necesitan ser dueños de las tablas), se puede ignorar este
   archivo con la variable de entorno SOVCA_DB_IGNORAR_LOCAL=1:

       SOVCA_DB_IGNORAR_LOCAL=1 SOVCA_DB_USER=postgres SOVCA_DB_PASS=… \
           php scripts/optimizar_indices_reportes.php --aplicar
*/
if (getenv('SOVCA_DB_IGNORAR_LOCAL') !== '1' && is_file(__DIR__ . '/db.local.php')) {
    require __DIR__ . '/db.local.php';
}

if (!function_exists('sovca_db_env')) {
    /**
     * Lee una variable de entorno con respaldo en $_SERVER y un valor fijo.
     */
    function sovca_db_env($nombre, $defecto)
    {
        $valor = getenv($nombre);
        if ($valor === false || $valor === '') {
            $valor = isset($_SERVER[$nombre]) ? $_SERVER[$nombre] : '';
        }
        return ($valor === '' || $valor === null) ? $defecto : $valor;
    }
}

if (!defined('SOVCA_DB_HOST')) {
    define('SOVCA_DB_HOST', sovca_db_env('SOVCA_DB_HOST', '127.0.0.1'));
}
if (!defined('SOVCA_DB_PORT')) {
    define('SOVCA_DB_PORT', sovca_db_env('SOVCA_DB_PORT', '5432'));
}
if (!defined('SOVCA_DB_NAME')) {
    define('SOVCA_DB_NAME', sovca_db_env('SOVCA_DB_NAME', 'sovca'));
}
if (!defined('SOVCA_DB_DEMO_NAME')) {
    define('SOVCA_DB_DEMO_NAME', sovca_db_env('SOVCA_DB_DEMO_NAME', 'sovca_demo'));
}
if (!defined('SOVCA_DB_USER')) {
    define('SOVCA_DB_USER', sovca_db_env('SOVCA_DB_USER', 'postgres'));
}
if (!defined('SOVCA_DB_PASS')) {
    define('SOVCA_DB_PASS', sovca_db_env('SOVCA_DB_PASS', 'postgres'));
}

if (!function_exists('sovca_db_nombre')) {
    /**
     * Normaliza el nombre de la base.
     *
     * `sovca_qas` era la base de QA y ya no existe como base de negocio:
     * se resuelve a la base real para que ningún módulo (alertas,
     * reportes, scripts) quede apuntando a una base inexistente.
     *
     * @param string|null $base
     * @return string
     */
    function sovca_db_nombre($base = null)
    {
        $base = trim((string) $base);

        if ($base === '') {
            return SOVCA_DB_NAME;
        }
        if (strcasecmp($base, 'sovca_qas') === 0) {
            return SOVCA_DB_NAME;
        }

        return $base;
    }
}

if (!function_exists('sovca_db_config')) {
    /**
     * Datos de conexión. Devuelve también la clave `db` (alias de `base`)
     * para los scripts que ya trabajan con ese nombre.
     *
     * @param string|null $base
     * @return array{host:string,port:string,base:string,db:string,user:string,pass:string}
     */
    function sovca_db_config($base = null)
    {
        $nombre = sovca_db_nombre($base);

        return array(
            'host' => SOVCA_DB_HOST,
            'port' => SOVCA_DB_PORT,
            'base' => $nombre,
            'db'   => $nombre,
            'user' => SOVCA_DB_USER,
            'pass' => SOVCA_DB_PASS,
        );
    }
}

if (!function_exists('sovca_db_dsn_pdo')) {
    /**
     * DSN para `new PDO(...)`.
     */
    function sovca_db_dsn_pdo($base = null)
    {
        $cfg = sovca_db_config($base);

        return 'pgsql:host=' . $cfg['host'] . ';port=' . $cfg['port'] . ';dbname=' . $cfg['base'];
    }
}

if (!function_exists('sovca_db_dsn_ar')) {
    /**
     * DSN para php-activerecord (`pgsql://usuario:clave@host:puerto/base`).
     */
    function sovca_db_dsn_ar($base = null)
    {
        $cfg = sovca_db_config($base);

        return 'pgsql://' . rawurlencode($cfg['user']) . ':' . rawurlencode($cfg['pass'])
            . '@' . $cfg['host'] . ':' . $cfg['port'] . '/' . $cfg['base'];
    }
}

if (!function_exists('sovca_db_pg_conninfo')) {
    /**
     * Cadena de conexión para la extensión pgsql (`pg_connect`).
     * Escapa los valores para que espacios o comillas no rompan la cadena.
     */
    function sovca_db_pg_conninfo($base = null)
    {
        $cfg = sovca_db_config($base);

        $escapar = function ($valor) {
            return "'" . str_replace(array('\\', "'"), array('\\\\', "\\'"), (string) $valor) . "'";
        };

        return 'host=' . $escapar($cfg['host'])
            . ' port=' . $escapar($cfg['port'])
            . ' dbname=' . $escapar($cfg['base'])
            . ' user=' . $escapar($cfg['user'])
            . ' password=' . $escapar($cfg['pass']);
    }
}
