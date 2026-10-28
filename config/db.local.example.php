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
   MODO DEMOSTRACIÓN (opcional)
   ---------------------------------------------------------------------
   config/demo.php carga este archivo ANTES de definir sus constantes, así
   que aquí se puede apagar el acceso demo o darle credenciales propias:

       define('SOVCA_DEMO_ACTIVO', false);
       define('SOVCA_DEMO_CLAVE', 'la-clave-de-postgres');

   Así no hay que editar archivos versionados en el servidor.
   --------------------------------------------------------------------- */
