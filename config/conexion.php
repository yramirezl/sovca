<?php
require_once '../php-activerecord/ActiveRecord.php';
date_default_timezone_set('America/Caracas');

// Configuración única de PostgreSQL (credenciales y nombres de base).
require_once __DIR__ . '/db.php';

// Configuración del modo demostración (base de pruebas aparte).
require_once __DIR__ . '/demo.php';

// initialize ActiveRecord
ActiveRecord\Config::initialize(function($cfg)
{
    $cfg->set_model_directory('../models/');

    /* La sesión solo se abre en el flujo web.
       · /api define SOVCA_API: la PWA usa token, no sesión. Abrir sesión
         ahí crea un archivo por request y serializa las llamadas
         concurrentes del mismo cliente por el bloqueo del archivo.
       · En CLI (cron, migraciones, diagnósticos) la sesión no aporta nada. */
    if (!defined('SOVCA_API') && PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $session_demo = isset($_SESSION['demo']) ? $_SESSION['demo'] : 0;  

    $accion_solicitada = isset($_POST['action']) ? (string)$_POST['action'] : '';
    $ruta_actual = isset($_SERVER['SCRIPT_NAME']) ? basename(parse_url($_SERVER['SCRIPT_NAME'], PHP_URL_PATH)) : '';
    // Rutas/acciones que deben responder siempre (aunque la suscripción esté
    // vencida): entrar al sistema y recuperar la contraseña por correo.
    $acciones_publicas = array('login', 'solicitar_reset', 'aplicar_reset');
    $es_ruta_publica = strpos($ruta_actual, 'login') === 0
        || strpos($ruta_actual, 'logout') === 0
        || in_array($accion_solicitada, $acciones_publicas, true);

    /* ================================================================
       MODO MANTENIMIENTO (red de seguridad global)
       ----------------------------------------------------------------
       Se evalúa aquí porque TODOS los modelos incluyen este archivo:
       así también quedan cubiertos los endpoints de data_json, los
       reportes y cualquier script que no pase por los controladores.
       Solo afecta a sesiones web de productores (2) y ayudantes (3);
       el API con token no guarda rol en $_SESSION y aplica su propio
       control en ApiAuth.
       ================================================================ */
    require_once __DIR__ . '/../lib/mantenimiento_helper.php';
    $rol_mantenimiento = (int)(isset($_SESSION['rolx']) ? $_SESSION['rolx'] : 0);
    if (!$es_ruta_publica
        && !empty($_SESSION['idusuariox'])
        && bovs_mantenimiento_activo()
        && !bovs_mantenimiento_permite_rol($rol_mantenimiento)) {
        bovs_mantenimiento_respuesta_json();
    }

    $fecha_caduca = isset($_SESSION['fecha_caduca_suscripcion']) ? $_SESSION['fecha_caduca_suscripcion'] : '';
    $rol_sesion = (int)(isset($_SESSION['rolx']) ? $_SESSION['rolx'] : 0);
    $requiere_verificacion = in_array($rol_sesion, array(2, 3), true) && empty($_SESSION['suscripcion_verificada']);
    $suscripcion_vencida = $fecha_caduca !== '' && $fecha_caduca <= date('Y-m-d');
    if (($requiere_verificacion || $suscripcion_vencida) && !$es_ruta_publica) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        $error = $suscripcion_vencida ? 'suscripcion_vencida' : 'suscripcion_revalidar';
        $mensaje = $suscripcion_vencida ? 'La suscripción venció. Solicita al administrador que renueve la cuenta.' : 'Inicia sesión nuevamente para verificar la vigencia de tu cuenta.';
        echo json_encode(array('ok' => false, 'error' => $error, 'msg' => $mensaje));
        exit;
    }

    /* ================================================================
       BASE DE DATOS SEGÚN EL TIPO DE SESIÓN
       ----------------------------------------------------------------
       · Sesión normal  → base real (config/db.php → SOVCA_DB_NAME).
       · Sesión demo    → base de pruebas (sovca_demo), que tiene la
                          misma estructura y solo los catálogos.
       El cambio se decide SOLO por $_SESSION['demo'], que únicamente
       escriben el acceso demostrativo y el respaldo de login demo.
       Si el modo demo está apagado en config/demo.php, una sesión que
       hubiera quedado marcada vuelve a la base real.
       ================================================================ */
    $cadena_conexion = sovca_db_dsn_ar(SOVCA_DB_NAME);

    $es_sesion_demo = ((int)$session_demo === 1) && defined('SOVCA_DEMO_ACTIVO') && SOVCA_DEMO_ACTIVO;
    if ($es_sesion_demo) {
        $cadena_conexion = sovca_db_dsn_ar(defined('SOVCA_DB_DEMO_NAME') ? SOVCA_DB_DEMO_NAME : SOVCA_DEMO_DB);
    }

    $cfg->set_connections(array('development' => $cadena_conexion)); 

  
   
  
});

// ActiveRecord\Config::Config::instance();
?>
