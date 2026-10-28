<?php
/**
 * =====================================================================
 * config/demo.php — MODO DEMOSTRACIÓN DE SOVCA
 * ---------------------------------------------------------------------
 * El modo demostración permite que un visitante se cree su PROPIA cuenta
 * desde la pantalla de acceso, con una vigencia de 15 días, trabajando
 * contra una base de datos APARTE (sovca_demo) que tiene:
 *
 *   · la misma estructura que la base real (tablas, vistas, secuencias),
 *   · solo los CATÁLOGOS básicos cargados,
 *   · ninguna información de los productores reales.
 *
 * Cómo funciona el aislamiento:
 *   cuando la sesión tiene $_SESSION['demo'] = 1, config/conexion.php
 *   cambia la cadena de conexión de php-activerecord a la base demo.
 *   Nada más cambia: los módulos, modelos y reportes siguen igual.
 *
 * Puesta en marcha (una sola vez por servidor):
 *   1) php scripts/demo_instalar.php
 *   2) poner SOVCA_DEMO_ACTIVO en true (aquí abajo)
 *
 * Para apagar la demostración basta con SOVCA_DEMO_ACTIVO = false: el
 * botón desaparece del login y las sesiones demo dejan de resolverse
 * (vuelven a la base real), por eso conviene además cerrar sesión.
 *
 * Este archivo NO contiene la configuración de la base principal:
 * esa vive en config/conexion.php y no se toca.
 * =====================================================================
 */

/* ¿Se muestra el acceso a la demostración? */
if (!defined('SOVCA_DEMO_ACTIVO')) {
    define('SOVCA_DEMO_ACTIVO', true);
}

/* Base de datos de pruebas (misma estructura, solo catálogos). */
if (!defined('SOVCA_DEMO_DB')) {
    define('SOVCA_DEMO_DB', 'sovca_demo');
}
if (!defined('SOVCA_DEMO_HOST')) {
    define('SOVCA_DEMO_HOST', '127.0.0.1');
}
if (!defined('SOVCA_DEMO_PUERTO')) {
    define('SOVCA_DEMO_PUERTO', '5432');
}
if (!defined('SOVCA_DEMO_USUARIO')) {
    define('SOVCA_DEMO_USUARIO', 'postgres');
}
if (!defined('SOVCA_DEMO_CLAVE')) {
    define('SOVCA_DEMO_CLAVE', 'postgres');
}

/* Base "origen" que se clona (solo estructura + catálogos). */
require_once __DIR__ . '/db.php';
if (!defined('SOVCA_DEMO_DB_ORIGEN')) {
    define('SOVCA_DEMO_DB_ORIGEN', SOVCA_DB_NAME);
}

/* ---------------------------------------------------------------------
   BASE OFICIAL (destino de la importación)
   ---------------------------------------------------------------------
   La usa el módulo "Importar cuenta demo" del administrador para crear
   la cuenta definitiva y copiar allí los procesos del usuario demo.
   Debe ser la MISMA base que usa config/conexion.php para la sesión
   normal (config/db.php → SOVCA_DB_NAME).
   --------------------------------------------------------------------- */
if (!defined('SOVCA_OFICIAL_HOST')) {
    define('SOVCA_OFICIAL_HOST', SOVCA_DB_HOST);
}
if (!defined('SOVCA_OFICIAL_PUERTO')) {
    define('SOVCA_OFICIAL_PUERTO', SOVCA_DB_PORT);
}
if (!defined('SOVCA_OFICIAL_DB')) {
    define('SOVCA_OFICIAL_DB', SOVCA_DB_NAME);
}
if (!defined('SOVCA_OFICIAL_USUARIO')) {
    define('SOVCA_OFICIAL_USUARIO', SOVCA_DB_USER);
}
if (!defined('SOVCA_OFICIAL_CLAVE')) {
    define('SOVCA_OFICIAL_CLAVE', SOVCA_DB_PASS);
}

/* ---------------------------------------------------------------------
   COMPRA DE LA SUSCRIPCIÓN (WhatsApp)
   ---------------------------------------------------------------------
   Es el MISMO número que usa el botón "¡Mejora tu Plan Ahora!" de la
   barra superior. El usuario demo siempre ve "Comprar suscripción" y
   el mensaje lleva sus datos para que el administrador identifique la
   cuenta demo que debe importar.
   --------------------------------------------------------------------- */
if (!defined('SOVCA_WHATSAPP_NUMERO')) {
    define('SOVCA_WHATSAPP_NUMERO', '584268205177');
}

/* Vigencia de la cuenta demostrativa, en días. */
if (!defined('SOVCA_DEMO_DIAS')) {
    define('SOVCA_DEMO_DIAS', 15);
}

/* Plan con el que se crea el fundo demo (5 = DIAMANTE, sin límite bajo). */
if (!defined('SOVCA_DEMO_PLAN_ID')) {
    define('SOVCA_DEMO_PLAN_ID', 5);
}

/* Anti-abuso: cuentas demo que puede crear una misma IP en 24 horas.
   0 = sin límite.
   Se deja alto a propósito: varias personas de una misma oficina o
   institución (una sola IP pública) pueden estar probando a la vez. La
   barrera real contra el abuso es la VERIFICACIÓN DEL CORREO. */
if (!defined('SOVCA_DEMO_MAX_POR_IP')) {
    define('SOVCA_DEMO_MAX_POR_IP', 20);
}

/* ---------------------------------------------------------------------
   VERIFICACIÓN DEL CORREO
   ---------------------------------------------------------------------
   La cuenta NO se crea hasta que la persona escribe el código de 6
   dígitos que recibe en su correo. Así las cuentas demo son de alguien
   identificable y los datos que carguen sirven (no son basura anónima).
   --------------------------------------------------------------------- */

/* Minutos de vigencia del código. */
if (!defined('SOVCA_DEMO_VERIF_MINUTOS')) {
    define('SOVCA_DEMO_VERIF_MINUTOS', 15);
}

/* Intentos permitidos antes de anular el código. */
if (!defined('SOVCA_DEMO_VERIF_INTENTOS')) {
    define('SOVCA_DEMO_VERIF_INTENTOS', 5);
}

/* Segundos mínimos entre dos envíos del código (botón Reenviar). */
if (!defined('SOVCA_DEMO_REENVIO_SEG')) {
    define('SOVCA_DEMO_REENVIO_SEG', 60);
}

/* Datos por defecto del fundo demo (se resuelven solos si no existen). */
if (!defined('SOVCA_DEMO_PAIS')) {
    define('SOVCA_DEMO_PAIS', 'Venezuela');
}
if (!defined('SOVCA_DEMO_TIPO_FUNDO_ID')) {
    define('SOVCA_DEMO_TIPO_FUNDO_ID', 1);       // FINCA
}
if (!defined('SOVCA_DEMO_TIPO_GANADERIA_ID')) {
    define('SOVCA_DEMO_TIPO_GANADERIA_ID', 1);   // OVINA
}
if (!defined('SOVCA_DEMO_TIPO_USUARIO_ID')) {
    define('SOVCA_DEMO_TIPO_USUARIO_ID', 0);     // igual que un productor real
}
if (!defined('SOVCA_DEMO_ROL_PRODUCTOR')) {
    define('SOVCA_DEMO_ROL_PRODUCTOR', 2);
}
