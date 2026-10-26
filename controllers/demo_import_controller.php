<?php
/**
 * =====================================================================
 * controllers/demo_import_controller.php
 * ---------------------------------------------------------------------
 * Módulo del ADMINISTRADOR para convertir una cuenta de demostración en
 * una cuenta oficial conservando los datos que el usuario cargó.
 *
 * Acciones:
 *   listar        → cuentas demo + planes + suscripciones + procesos
 *   detalle       → resumen de procesos de una cuenta demo
 *   crear_cuenta  → crea usuario + fundo + plan + vigencia en la base real
 *   importar      → copia los procesos elegidos y cierra la cuenta demo
 *   cerrar        → cierra la cuenta demo sin importar datos
 *
 * Solo roles 1 (ADMINISTRADOR) y 4 (SUPER USUARIO).
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
require_once __DIR__ . '/../lib/demo_import_helper.php';

/**
 * Respuesta JSON y fin.
 */
function imp_responder($datos, $codigoHttp = 200)
{
    if (!headers_sent()) {
        http_response_code($codigoHttp);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    }

    echo json_encode($datos);
    exit;
}

$rol = isset($_SESSION['rolx']) ? (int) $_SESSION['rolx'] : 0;
$uid = isset($_SESSION['idusuariox']) ? (int) $_SESSION['idusuariox'] : 0;

if (!in_array($rol, array(1, 4), true)) {
    imp_responder(array('ok' => false, 'msg' => 'Solo un administrador puede importar cuentas demo.'), 403);
}

@$action = isset($_POST['action']) ? (string) $_POST['action'] : (isset($_GET['action']) ? (string) $_GET['action'] : '');

switch ($action) {

    /* =================================================================
       LISTADO
       ================================================================= */
    case 'listar':

        if (!bovs_demo_activo() || !bovs_demo_bd_disponible()) {
            imp_responder(array(
                'ok'  => false,
                'msg' => 'La base de datos de demostración no está disponible en este servidor.'
            ));
        }

        try {
            $demos = bovs_import_listar_demos();

            foreach ($demos as &$d) {
                $resumen = bovs_import_resumen_demo((int) $d['fundo_id']);
                $total   = 0;
                foreach ($resumen as $r) {
                    $total += (int) $r['total'];
                }
                $d['resumen']         = $resumen;
                $d['total_registros'] = $total;
            }
            unset($d);

            $pdo = bovs_oficial_pdo();

            $planes = $pdo->query('SELECT id, nombre, max_reg FROM mplanes WHERE status = 1 ORDER BY id')->fetchAll();
            $suscripciones = $pdo->query('SELECT id, descripcion, cantidad_dias FROM msuscripciones WHERE status = 1 ORDER BY cantidad_dias')->fetchAll();

            imp_responder(array(
                'ok'            => true,
                'demos'         => $demos,
                'planes'        => $planes,
                'suscripciones' => $suscripciones,
                'grupos'        => bovs_import_grupos(),
                'hoy'           => date('Y-m-d'),
            ));
        } catch (Throwable $e) {
            error_log('[SOVCA import demo] listar: ' . $e->getMessage());
            imp_responder(array('ok' => false, 'msg' => 'No se pudo consultar la información: ' . $e->getMessage()));
        }
        break;

    /* =================================================================
       CREAR LA CUENTA OFICIAL
       ================================================================= */
    case 'crear_cuenta':

        $resultado = bovs_import_crear_cuenta(array(
            'nombre'          => isset($_POST['nombre']) ? $_POST['nombre'] : '',
            'email'           => isset($_POST['email']) ? $_POST['email'] : '',
            'usuario'         => isset($_POST['usuario']) ? $_POST['usuario'] : '',
            'telefono'        => isset($_POST['telefono']) ? $_POST['telefono'] : '',
            'clave'           => isset($_POST['clave']) ? $_POST['clave'] : '',
            'clave2'          => isset($_POST['clave2']) ? $_POST['clave2'] : '',
            'plan_id'         => isset($_POST['plan_id']) ? $_POST['plan_id'] : 0,
            'suscripcion_id'  => isset($_POST['suscripcion_id']) ? $_POST['suscripcion_id'] : 0,
            'fecha_activa'    => isset($_POST['fecha_activa']) ? $_POST['fecha_activa'] : '',
            'fundo_nombre'    => isset($_POST['fundo_nombre']) ? $_POST['fundo_nombre'] : '',
            'cid_rif'         => isset($_POST['cid_rif']) ? $_POST['cid_rif'] : '',
            'tipo_ganaderia'  => isset($_POST['tipo_ganaderia']) ? $_POST['tipo_ganaderia'] : 1,
            'admin_id'        => $uid,
        ));

        imp_responder($resultado);
        break;

    /* =================================================================
       IMPORTAR LOS PROCESOS
       ================================================================= */
    case 'importar':

        $demoUsuarioId = isset($_POST['demo_usuario_id']) ? (int) $_POST['demo_usuario_id'] : 0;
        $fundoDemo     = isset($_POST['fundo_demo']) ? (int) $_POST['fundo_demo'] : 0;
        $fundoDestino  = isset($_POST['fundo_destino']) ? (int) $_POST['fundo_destino'] : 0;
        $usuarioDest   = isset($_POST['usuario_destino']) ? (int) $_POST['usuario_destino'] : 0;
        $demoUser      = trim((string) (isset($_POST['demo_usuario']) ? $_POST['demo_usuario'] : ''));

        $grupos = isset($_POST['grupos']) ? (array) $_POST['grupos'] : array();
        $grupos = array_values(array_filter(array_map('strval', $grupos)));

        if ($demoUsuarioId <= 0 || $fundoDemo <= 0 || $fundoDestino <= 0) {
            imp_responder(array('ok' => false, 'msg' => 'Faltan datos para la importación.'));
        }

        $resultado = bovs_importar_procesos(array(
            'fundo_demo'      => $fundoDemo,
            'fundo_destino'   => $fundoDestino,
            'usuario_destino' => $usuarioDest,
            'grupos'          => $grupos,
        ));

        if (!empty($resultado['ok'])) {
            bovs_import_marcar_migrada(
                $demoUsuarioId,
                $usuarioDest,
                $fundoDestino,
                'Importado a la cuenta oficial #' . $usuarioDest . ' por el administrador #' . $uid
            );

            $resultado['msg'] .= ' La cuenta demo <strong>' . htmlspecialchars($demoUser, ENT_QUOTES, 'UTF-8') . '</strong> quedó cerrada.';
        }

        imp_responder($resultado);
        break;

    /* =================================================================
       CERRAR LA CUENTA DEMO SIN IMPORTAR
       ================================================================= */
    case 'cerrar':

        $demoUsuarioId = isset($_POST['demo_usuario_id']) ? (int) $_POST['demo_usuario_id'] : 0;
        $nota          = trim((string) (isset($_POST['nota']) ? $_POST['nota'] : ''));

        if ($demoUsuarioId <= 0) {
            imp_responder(array('ok' => false, 'msg' => 'No se identificó la cuenta demo.'));
        }

        $ok = bovs_import_cerrar_demo(
            $demoUsuarioId,
            $nota !== '' ? $nota : ('Cuenta cerrada por el administrador #' . $uid . ' sin importar datos.')
        );

        imp_responder(array(
            'ok'  => $ok,
            'msg' => $ok
                ? 'La cuenta demo quedó cerrada. Ese correo ya no puede crear otra demostración.'
                : 'No se pudo cerrar la cuenta demo.'
        ));
        break;

    default:
        imp_responder(array('ok' => false, 'msg' => 'Acción no reconocida.'), 400);
}
