<?php
/**
 * =====================================================================
 * SOVCA | Plantilla de configuración LOCAL de PostgreSQL
 * ---------------------------------------------------------------------
 * En el servidor:
 *
 *   cp config/db.local.example.php config/db.local.php
 *   nano config/db.local.php
 *
 * `config/db.local.php` está ignorado por Git:
 *   · no se sube al repositorio,
 *   · un `git pull` NUNCA lo sobrescribe ni genera conflictos.
 *
 * Edita solo los valores que necesites. Los que no definas se toman de
 * las variables de entorno o de los valores por defecto de config/db.php.
 *
 * En producción conviene usar el rol de aplicación (sovca_app), no el
 * superusuario: créalo con `scripts/crear_usuario_sovca_app.sh`.
 *
 * Solo hay dos bases de negocio: sovca y sovca_demo.
 * =====================================================================
 */

if (!defined('SOVCA_DB_HOST')) {
    define('SOVCA_DB_HOST', '127.0.0.1');
}

if (!defined('SOVCA_DB_PORT')) {
    /* Con PgBouncer por delante, en producción este puerto suele ser 6432. */
    define('SOVCA_DB_PORT', '5432');
}

if (!defined('SOVCA_DB_NAME')) {
    define('SOVCA_DB_NAME', 'sovca');
}

if (!defined('SOVCA_DB_DEMO_NAME')) {
    define('SOVCA_DB_DEMO_NAME', 'sovca_demo');
}

if (!defined('SOVCA_DB_USER')) {
    define('SOVCA_DB_USER', 'sovca_app');
}

if (!defined('SOVCA_DB_PASS')) {
    define('SOVCA_DB_PASS', 'CAMBIAR_ESTA_CLAVE');
}

/* ---------------------------------------------------------------------
   MODO DEMOSTRACIÓN
   ---------------------------------------------------------------------
   config/demo.php carga este archivo ANTES de definir sus constantes.

   La SESIÓN demo usa el rol de la aplicación (SOVCA_DB_USER, o sea
   sovca_app): ese rol solo puede leer y escribir, nunca crear bases.

   Crea la base una sola vez como superusuario y déjala a nombre de
   sovca_app; el instalador ya no pide nada más:

       sudo -u postgres createdb -O sovca_app -E UTF8 \
            --lc-collate=es_ES.UTF-8 --lc-ctype=es_ES.UTF-8 \
            -T template0 sovca_demo

   Solo si querés que el instalador cree/borre la base por su cuenta
   (o uses --recrear) hay que darle un rol con CREATEDB. La
   administración va directo a PostgreSQL (5432), nunca por PgBouncer:

       define('SOVCA_DEMO_ADMIN_USUARIO', 'postgres');
       define('SOVCA_DEMO_ADMIN_CLAVE', 'la-clave-de-postgres');

   Para apagar la demostración:

       define('SOVCA_DEMO_ACTIVO', false);
   --------------------------------------------------------------------- */
