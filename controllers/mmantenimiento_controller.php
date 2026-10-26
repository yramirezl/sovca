<?php
/**
 * =====================================================================
 * controllers/mmantenimiento_controller.php — MODO MANTENIMIENTO
 * ---------------------------------------------------------------------
 * Solo accesible para los perfiles 1 (Administrador) y 4 (Super
 * Usuario). Acciones (POST):
 *
 *   · activar    { motivo, duracion_min, mensaje? }
 *   · desactivar { nota? }
 *   · estado     (consulta; devuelve el estado completo)
 *   · bitacora   { limite? }
 *
 * El interruptor que se consulta en el resto del sistema vive en
 * lib/mantenimiento_helper.php (archivo file/mantenimiento/estado.json
 * o la variable de entorno SOVCA_MANTENIMIENTO).
 * =====================================================================
 */

if (!ob_get_level()) {
    ob_start();
}

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED & ~E_NOTICE);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('America/Caracas');

require_once __DIR__ . '/../lib/mantenimiento_helper.php';

header('Content-Type: application/json; charset=utf-8');

$rolSesion  = isset($_SESSION['rolx']) ? (int) $_SESSION['rolx'] : 0;
$usuarioId  = isset($_SESSION['idusuariox']) ? (int) $_SESSION['idusuariox'] : 0;
$usuarioNom = isset($_SESSION['usuariox']) ? (string) $_SESSION['usuariox'] : '';

/* VerificarAdmin(): productores y encargados quedan fuera. */
if ($usuarioId <= 0 || !in_array($rolSesion, array(1, 4), true)) {
    http_response_code(403);
    echo json_encode(array(
        'ok'    => false,
        'error' => 'no_autorizado',
        'msg'   => 'Solo un administrador del sistema puede gestionar el modo mantenimiento.',
    ));
    exit;
}

$accion = isset($_POST['action']) ? (string) $_POST['action'] : '';

switch ($accion) {

    /* ------------------------------------------------------------
       ACTIVAR — cierra el sistema para los perfiles 2 y 3
       ------------------------------------------------------------ */
    case 'activar':
        $motivo     = isset($_POST['motivo']) ? trim((string) $_POST['motivo']) : '';
        $duracion   = isset($_POST['duracion_min']) ? (int) $_POST['duracion_min'] : 120;
        $mensaje    = isset($_POST['mensaje']) ? trim((string) $_POST['mensaje']) : '';

        $resultado = bovs_mantenimiento_activar($motivo, $duracion, $usuarioNom, $usuarioId, array(
            'mensaje' => $mensaje,
            'origen'  => 'panel',
        ));

        if (empty($resultado['ok'])) {
            http_response_code(422);
            echo json_encode(array('ok' => false, 'msg' => $resultado['msg']));
            break;
        }

        $estado = bovs_mantenimiento_estado();

        echo json_encode(array(
            'ok'         => true,
            'msg'        => $resultado['msg'],
            'activo'     => true,
            'motivo'     => $motivo,
            'desde'      => $estado['desde'],
            'hasta'      => $estado['hasta'],
            'minutos'    => (int) $estado['minutos_restantes'],
            'aviso'      => 'Los usuarios productores y ayudantes quedaron bloqueados. Usa Modo soporte desde el listado de fundos para navegar el sistema.',
        ));
        break;

    /* ------------------------------------------------------------
       DESACTIVAR — vuelve a abrir el sistema
       ------------------------------------------------------------ */
    case 'desactivar':
        $nota = isset($_POST['nota']) ? trim((string) $_POST['nota']) : '';

        $resultado = bovs_mantenimiento_desactivar($usuarioNom, $usuarioId, $nota, 'panel');

        if (empty($resultado['ok'])) {
            http_response_code(422);
            echo json_encode(array('ok' => false, 'msg' => $resultado['msg']));
            break;
        }

        echo json_encode(array(
            'ok'     => true,
            'msg'    => $resultado['msg'],
            'activo' => false,
        ));
        break;

    /* ------------------------------------------------------------
       ESTADO — para refrescar la pantalla sin recargar
       ------------------------------------------------------------ */
    case 'estado':
        $estado = bovs_mantenimiento_estado();

        echo json_encode(array(
            'ok'                 => true,
            'activo'             => (bool) $estado['vigente'],
            'motivo'             => $estado['motivo'],
            'mensaje'            => $estado['mensaje'],
            'mensaje_publico'    => $estado['mensaje_publico'],
            'desde'              => $estado['desde'],
            'hasta'              => $estado['hasta'],
            'duracion_min'       => (int) $estado['duracion_min'],
            'minutos_restantes'  => (int) $estado['minutos_restantes'],
            'usuario'            => $estado['usuario'],
            'origen'             => $estado['origen'],
            'roles'              => $estado['roles'],
            'archivo'            => $estado['archivo'],
            'archivo_escribible' => (bool) $estado['archivo_escribible'],
        ), JSON_UNESCAPED_UNICODE);
        break;

    /* ------------------------------------------------------------
       BITÁCORA — quién activó/desactivó y cuándo
       ------------------------------------------------------------ */
    case 'bitacora':
        $limite = isset($_POST['limite']) ? (int) $_POST['limite'] : 40;

        echo json_encode(array(
            'ok'      => true,
            'eventos' => bovs_mantenimiento_bitacora($limite),
        ), JSON_UNESCAPED_UNICODE);
        break;

    default:
        http_response_code(400);
        echo json_encode(array('ok' => false, 'msg' => 'Acción no reconocida.'));
        break;
}
