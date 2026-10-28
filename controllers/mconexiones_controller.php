<?php
/**
 * ============================================================================
 *  controllers/mconexiones_controller.php
 * ----------------------------------------------------------------------------
 *  Control de sesiones desde el módulo «Conexiones».
 *
 *  Acciones:
 *    estado_sesion     El propio usuario pregunta si su sesión tiene un cierre
 *                      remoto pedido (lo usa el aviso con cuenta atrás).
 *    cerrar_ahora      El usuario cierra su sesión en ese momento.
 *    solicitar_cierre  (admin) Pide cerrar una sesión concreta o todas las de
 *                      un usuario. El usuario recibe el aviso de 60 s.
 *    cancelar_cierre   (admin) Deshace el cierre pedido; la sesión continúa.
 *
 *  El cierre NUNCA es drástico: el usuario ve «Su sesión ha expirado…» con la
 *  cuenta atrás y, si no alcanza a reaccionar, validar_session.php lo cierra
 *  al vencer el plazo aunque el navegador esté cerrado.
 * ============================================================================
 */

require_once __DIR__ . '/../lib/conexiones_helper.php';

date_default_timezone_set('America/Caracas');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action = isset($_POST['action']) ? (string) $_POST['action']
        : (isset($_GET['action']) ? (string) $_GET['action'] : '');

$uid = isset($_SESSION['idusuariox']) ? (int) $_SESSION['idusuariox'] : 0;
$rol = isset($_SESSION['rolx']) ? (int) $_SESSION['rolx'] : 0;

if ($uid <= 0) {
    http_response_code(401);
    echo json_encode(array('ok' => false, 'msg' => 'La sesión no está iniciada.'));
    exit;
}

/** Nombre con el que se firma el cierre en el historial. */
function cx_admin_firma($uid)
{
    foreach (array('usuariox', 'username', 'nombre_usuariox') as $clave) {
        if (!empty($_SESSION[$clave])) {
            return substr((string) $_SESSION[$clave], 0, 60);
        }
    }
    return 'admin#' . (int) $uid;
}

function cx_solo_admin($rol)
{
    if (!in_array((int) $rol, array(1, 4), true)) {
        http_response_code(403);
        echo json_encode(array('ok' => false, 'msg' => 'Acción reservada al administrador.'));
        exit;
    }
}

switch ($action) {

    /* ------------------------------------------------------------------
     * El propio usuario: ¿me están cerrando la sesión?
     * ------------------------------------------------------------------ */
    case 'estado_sesion':
        $estado = bovs_cx_cierre_estado_sesion();
        echo json_encode(array(
            'ok'       => true,
            'abierta'  => $estado['abierta'],
            'cierre'   => $estado['cierre'],
            'restante' => $estado['restante'],
            'plazo'    => (int) BOVS_CX_CIERRE_SEG,
        ));
        break;

    /* ------------------------------------------------------------------
     * El propio usuario: cerrar ya (botón «Salir ahora»).
     * ------------------------------------------------------------------ */
    case 'cerrar_ahora':
        bovs_cx_cerrar_sesion('admin');
        session_unset();
        session_destroy();
        echo json_encode(array('ok' => true, 'msg' => 'Sesión cerrada.'));
        break;

    /* ------------------------------------------------------------------
     * Administrador: pedir el cierre (una sesión o todas las de un usuario)
     * ------------------------------------------------------------------ */
    case 'solicitar_cierre':
        cx_solo_admin($rol);

        $historialId = (int) (isset($_POST['historial_id']) ? $_POST['historial_id'] : 0);
        $usuarioId   = (int) (isset($_POST['usuario_id']) ? $_POST['usuario_id'] : 0);
        $firma       = cx_admin_firma($uid);

        if ($historialId > 0) {
            if ($historialId === bovs_cx_sesion_actual()) {
                echo json_encode(array('ok' => false, 'msg' => 'No puede cerrar su propia sesión desde aquí.'));
                break;
            }
            $ok = bovs_cx_cierre_solicitar($historialId, $firma);
        } elseif ($usuarioId > 0) {
            $ok = bovs_cx_cierre_solicitar_usuario($usuarioId, $firma);
        } else {
            echo json_encode(array('ok' => false, 'msg' => 'Indique la sesión o el usuario.'));
            break;
        }

        echo json_encode($ok
            ? array(
                'ok'       => true,
                'msg'      => 'Cierre solicitado. El usuario verá el aviso y su sesión se cerrará en '
                            . (int) BOVS_CX_CIERRE_SEG . ' segundos.',
                'plazo'    => (int) BOVS_CX_CIERRE_SEG,
            )
            : array('ok' => false, 'msg' => 'No se pudo solicitar el cierre.'));
        break;

    /* ------------------------------------------------------------------
     * Administrador: cancelar el cierre pedido
     * ------------------------------------------------------------------ */
    case 'cancelar_cierre':
        cx_solo_admin($rol);

        $historialId = (int) (isset($_POST['historial_id']) ? $_POST['historial_id'] : 0);
        if ($historialId <= 0) {
            echo json_encode(array('ok' => false, 'msg' => 'Indique la sesión.'));
            break;
        }

        echo json_encode(bovs_cx_cierre_cancelar($historialId)
            ? array('ok' => true, 'msg' => 'Cierre cancelado. La sesión continúa abierta.')
            : array('ok' => false, 'msg' => 'No se pudo cancelar el cierre.'));
        break;

    default:
        http_response_code(400);
        echo json_encode(array('ok' => false, 'msg' => 'Acción no reconocida.'));
        break;
}
