<?php
/**
 * =====================================================================
 * controllers/demo_controller.php — ACCESO DEMOSTRATIVO
 * ---------------------------------------------------------------------
 * Atiende la pantalla pública views/demo/index.php:
 *
 *   · crear_cuenta     : valida los datos, guarda la solicitud pendiente y
 *                        envía un código de 6 dígitos al correo. NO crea
 *                        todavía usuario ni fundo.
 *   · verificar_codigo : comprueba el código, crea usuario + fundo +
 *                        vigencia de 15 días y deja la sesión demo iniciada.
 *   · reenviar_codigo  : manda un código nuevo (con espera mínima).
 *   · estado           : informa si la base de pruebas está disponible
 *                        (la pantalla lo consulta al cargar).
 *   · salir            : cierra la sesión demo y vuelve al login.
 *
 * La sesión demo se marca con $_SESSION['demo'] = 1; a partir de ahí
 * config/conexion.php resuelve TODAS las consultas contra sovca_demo.
 * =====================================================================
 */

if (!ob_get_level()) {
    ob_start();
}

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED & ~E_NOTICE);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

date_default_timezone_set('America/Caracas');

require_once __DIR__ . '/../config/demo.php';
require_once __DIR__ . '/../lib/demo_helper.php';
require_once __DIR__ . '/../lib/captcha_helper.php';

@$action = isset($_POST['action']) ? (string) $_POST['action'] : (isset($_GET['action']) ? (string) $_GET['action'] : '');

/**
 * Responde siempre JSON y termina.
 */
function demo_responder($datos)
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($datos);
    exit;
}

/* MODO MANTENIMIENTO: mientras el sistema esté cerrado no se crean ni
   se verifican cuentas demo. 'estado' y 'salir' siguen disponibles para
   que la pantalla pueda informar la situación y cerrar la sesión. */
require_once __DIR__ . '/../lib/mantenimiento_helper.php';
if (in_array($action, array('crear_cuenta', 'verificar_codigo', 'reenviar_codigo'), true)
    && bovs_mantenimiento_activo()) {
    demo_responder(array(
        'ok'            => false,
        'mantenimiento' => true,
        'msg'           => bovs_mantenimiento_mensaje(),
    ));
}

switch ($action) {

    /* =================================================================
       CREAR LA CUENTA DEMO
       ================================================================= */
    case 'crear_cuenta':
        if (!bovs_demo_activo()) {
            demo_responder(array(
                'ok'  => false,
                'msg' => 'El acceso demostrativo está deshabilitado en este momento.'
            ));
        }

        /* --- Captcha (mismo esquema que el login) ------------------- */
        $token     = isset($_POST['captcha_token']) ? (string) $_POST['captcha_token'] : '';
        $respuesta = isset($_POST['captcha_respuesta']) ? trim((string) $_POST['captcha_respuesta']) : '';

        if (!bovs_captcha_validar($token, $respuesta)) {
            demo_responder(array(
                'ok'      => false,
                'msg'     => 'La verificación de seguridad no coincide. Intenta con la operación nueva.',
                'captcha' => true
            ));
        }

        /* --- Aceptación de las condiciones ------------------------- */
        if (empty($_POST['acepto'])) {
            demo_responder(array(
                'ok'  => false,
                'msg' => 'Debes aceptar las condiciones del entorno de demostración.'
            ));
        }

        $resultado = bovs_demo_solicitar_cuenta(array(
            'nombre'  => isset($_POST['nombre']) ? $_POST['nombre'] : '',
            'email'   => isset($_POST['email']) ? $_POST['email'] : '',
            'usuario' => isset($_POST['usuario']) ? $_POST['usuario'] : '',
            'clave'   => isset($_POST['clave']) ? $_POST['clave'] : '',
            'clave2'  => isset($_POST['clave2']) ? $_POST['clave2'] : '',
            'fundo'   => isset($_POST['fundo']) ? $_POST['fundo'] : '',
        ));

        if (empty($resultado['ok'])) {
            demo_responder(array(
                'ok'  => false,
                'msg' => isset($resultado['msg']) ? $resultado['msg'] : 'No se pudo registrar la solicitud.'
            ));
        }

        /* La cuenta NO existe todavía: solo se guardó la solicitud y se
           envió el código. Se pasa a la pantalla de verificación. */
        demo_responder(array(
            'ok'           => true,
            'paso'         => 'verificacion',
            'usuario'      => $resultado['usuario'],
            'email'        => $resultado['email'],
            'email_oculto' => $resultado['email_oculto'],
            'minutos'      => $resultado['minutos'],
            'msg'          => isset($resultado['msg']) ? $resultado['msg'] : 'Revisa tu correo.',
        ));
        break;

    /* =================================================================
       VERIFICAR EL CÓDIGO Y CREAR LA CUENTA
       ================================================================= */
    case 'verificar_codigo':

        if (!bovs_demo_activo()) {
            demo_responder(array('ok' => false, 'msg' => 'El acceso demostrativo está deshabilitado.'));
        }

        $resultado = bovs_demo_confirmar_cuenta(
            isset($_POST['usuario']) ? $_POST['usuario'] : '',
            isset($_POST['codigo']) ? $_POST['codigo'] : ''
        );

        if (empty($resultado['ok']) || empty($resultado['usuario'])) {
            demo_responder(array(
                'ok'       => false,
                'expirado' => !empty($resultado['expirado']),
                'msg'      => isset($resultado['msg']) ? $resultado['msg'] : 'No se pudo verificar el código.'
            ));
        }

        /* Recién aquí existe la cuenta. La petición actual ya tiene
           ActiveRecord contra la base real; la sesión queda marcada y la
           REDIRECCIÓN ya trabaja contra la base demo. */
        bovs_demo_iniciar_sesion($resultado['usuario']);

        demo_responder(array(
            'ok'       => true,
            'msg'      => 'Correo verificado. ¡Bienvenido!',
            'usuario'  => $resultado['usuario']['username'],
            'caduca'   => $resultado['usuario']['fecha_caduca'],
            'redirect' => '../../views/inicio/'
        ));
        break;

    /* =================================================================
       REENVIAR EL CÓDIGO
       ================================================================= */
    case 'reenviar_codigo':

        if (!bovs_demo_activo()) {
            demo_responder(array('ok' => false, 'msg' => 'El acceso demostrativo está deshabilitado.'));
        }

        $resultado = bovs_demo_reenviar_codigo(isset($_POST['usuario']) ? $_POST['usuario'] : '');

        demo_responder(array(
            'ok'      => !empty($resultado['ok']),
            'msg'     => isset($resultado['msg']) ? $resultado['msg'] : 'No se pudo reenviar el código.',
            'espera'  => isset($resultado['espera']) ? (int) $resultado['espera'] : 0,
            'minutos' => isset($resultado['minutos']) ? (int) $resultado['minutos'] : 0
        ));
        break;

    /* =================================================================
       ESTADO DE LA BASE DE PRUEBAS
       ================================================================= */
    case 'estado':

        $disponible = bovs_demo_activo() && bovs_demo_bd_disponible();

        demo_responder(array(
            'ok'         => $disponible,
            'activo'     => bovs_demo_activo(),
            'disponible' => $disponible,
            'dias'       => (int) SOVCA_DEMO_DIAS,
            'msg'        => $disponible
                ? 'Entorno de demostración disponible.'
                : 'El entorno de demostración no está disponible. Avisa al administrador del sistema.'
        ));
        break;

    /* =================================================================
       SALIR DE LA DEMO
       ================================================================= */
    case 'salir':

        bovs_demo_cerrar_sesion();
        $_SESSION = array();
        @session_unset();

        header('Location: ../../views/login');
        exit;

    default:
        demo_responder(array('ok' => false, 'msg' => 'Acción no reconocida.'));
}
