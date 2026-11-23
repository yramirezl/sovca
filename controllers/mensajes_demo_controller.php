<?php
/**
 * =====================================================================
 * controllers/mensajes_demo_controller.php
 * ---------------------------------------------------------------------
 * Mensajería por correo para quienes crearon una cuenta de demostración.
 *
 * Acciones:
 *   listar       → correos de usuarios demo (vigentes y caducados) + resumen
 *   vista_previa → HTML del correo tal como lo recibirá un destinatario
 *   enviar       → envía el mensaje a un lote de correos y lo registra
 *   historial    → últimos envíos
 *
 * Solo roles 1 (ADMINISTRADOR) y 4 (SUPER USUARIO).
 *
 * El listado NO incluye los correos ya migrados al sistema oficial ni
 * los cerrados por el administrador: esas personas ya no son usuarios
 * demo a los que haya que invitar a comprar.
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
require_once __DIR__ . '/../lib/mensajes_demo_helper.php';

/**
 * Respuesta JSON y fin.
 */
function msjd_responder($datos, $codigoHttp = 200)
{
    if (!headers_sent()) {
        http_response_code($codigoHttp);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('X-Content-Type-Options: nosniff');
    }

    echo json_encode($datos);
    exit;
}

$rol = isset($_SESSION['rolx']) ? (int) $_SESSION['rolx'] : 0;
$uid = isset($_SESSION['idusuariox']) ? (int) $_SESSION['idusuariox'] : 0;

if (!in_array($rol, array(1, 4), true)) {
    msjd_responder(array('ok' => false, 'msg' => 'Solo un administrador puede enviar mensajes a los usuarios demo.'), 403);
}

/* Correos por petición: la pantalla envía en lotes para que una lista
   grande no caiga por tiempo de espera del servidor ni del navegador. */
$LOTE_MAX = 25;

@$action = isset($_POST['action']) ? (string) $_POST['action'] : (isset($_GET['action']) ? (string) $_GET['action'] : '');

switch ($action) {

    /* =================================================================
       LISTADO
       ================================================================= */
    case 'listar':

        if (!bovs_demo_activo() || !bovs_demo_bd_disponible()) {
            msjd_responder(array(
                'ok'  => false,
                'msg' => 'La base de datos de demostración no está disponible en este servidor.'
            ));
        }

        try {
            $leads = bovs_msj_demo_listar();

            $vigentes  = 0;
            $caducadas = 0;
            foreach ($leads as $l) {
                if ($l['estado'] === 'vigente') {
                    $vigentes++;
                } else {
                    $caducadas++;
                }
            }

            msjd_responder(array(
                'ok'      => true,
                'leads'   => $leads,
                'resumen' => array(
                    'total'     => count($leads),
                    'vigentes'  => $vigentes,
                    'caducadas' => $caducadas,
                ),
                'historial' => bovs_msj_demo_historial(25),
                'sugerido'  => array(
                    'asunto' => bovs_msj_demo_asunto_sugerido(),
                    'cuerpo' => bovs_msj_demo_cuerpo_sugerido(),
                ),
                'hoy' => date('Y-m-d'),
            ));
        } catch (Throwable $e) {
            error_log('[SOVCA mensajes demo] listar: ' . $e->getMessage());
            msjd_responder(array('ok' => false, 'msg' => 'No se pudo consultar la información de los usuarios demo.'));
        }
        break;

    /* =================================================================
       VISTA PREVIA
       ================================================================= */
    case 'vista_previa':

        $asunto  = isset($_POST['asunto']) ? trim((string) $_POST['asunto']) : '';
        $cuerpo  = isset($_POST['cuerpo']) ? (string) $_POST['cuerpo'] : '';
        $destino = isset($_POST['destino']) ? strtolower(trim((string) $_POST['destino'])) : '';

        if (trim($cuerpo) === '') {
            msjd_responder(array('ok' => false, 'msg' => 'Escribe el mensaje antes de verlo.'));
        }

        /* La vista previa usa un destinatario real del listado cuando se
           indica (así se ve el nombre y la fecha correctos) o uno de
           muestra si todavía no hay correos seleccionados. */
        $lead = null;
        foreach (bovs_msj_demo_listar() as $l) {
            if ($destino !== '' && $l['email'] === $destino) {
                $lead = $l;
                break;
            }
        }

        if ($lead === null) {
            $lead = array(
                'email'   => $destino !== '' ? $destino : 'productor@ejemplo.com',
                'nombre'  => 'Productor de ejemplo',
                'usuario' => 'EJEMPLO',
                'fundo'   => 'Fundo El Ejemplo',
                'vence'   => date('Y-m-d', strtotime('-5 days')),
                'dias'    => -5,
                'estado'  => 'caducada',
            );
        }

        $html   = bovs_msj_demo_html($lead, $asunto, $cuerpo);
        $texto  = bovs_msj_demo_texto($lead, $asunto, $cuerpo);

        msjd_responder(array('ok' => true, 'html' => $html, 'texto' => $texto));
        break;

    /* =================================================================
       ENVÍO
       ================================================================= */
    case 'enviar':

        $asunto = isset($_POST['asunto']) ? trim((string) $_POST['asunto']) : '';
        $cuerpo = isset($_POST['cuerpo']) ? (string) $_POST['cuerpo'] : '';

        if ($asunto === '') {
            msjd_responder(array('ok' => false, 'msg' => 'Escribe el asunto del mensaje.'));
        }
        $largo = function ($texto) {
            return function_exists('mb_strlen') ? mb_strlen($texto, 'UTF-8') : strlen($texto);
        };

        if ($largo($asunto) > 180) {
            msjd_responder(array('ok' => false, 'msg' => 'El asunto no puede pasar de 180 caracteres.'));
        }
        if (trim($cuerpo) === '') {
            msjd_responder(array('ok' => false, 'msg' => 'Escribe el mensaje que se enviará.'));
        }
        if ($largo($cuerpo) > 6000) {
            msjd_responder(array('ok' => false, 'msg' => 'El mensaje es demasiado largo (máximo 6000 caracteres).'));
        }

        /* Lista blanca: solo se puede escribir a correos que estén en el
           listado de usuarios demo (se calcula aquí, no se confía en el
           navegador). */
        $permitidos = array();
        foreach (bovs_msj_demo_listar() as $l) {
            $permitidos[$l['email']] = $l;
        }

        $destinos = array();
        $crudo = isset($_POST['destinos']) ? (string) $_POST['destinos'] : '';
        if ($crudo !== '') {
            $lista = json_decode($crudo, true);
            if (!is_array($lista)) {
                msjd_responder(array('ok' => false, 'msg' => 'La lista de destinatarios no es válida.'));
            }
            foreach ($lista as $email) {
                $email = strtolower(trim((string) $email));
                if ($email !== '' && isset($permitidos[$email])) {
                    $destinos[$email] = $permitidos[$email];
                }
            }
        }

        if (count($destinos) === 0) {
            msjd_responder(array('ok' => false, 'msg' => 'No hay destinatarios válidos. Actualiza la lista e intenta de nuevo.'));
        }
        if (count($destinos) > $LOTE_MAX) {
            msjd_responder(array('ok' => false, 'msg' => 'El lote máximo es de ' . $LOTE_MAX . ' correos por envío.'));
        }

        @set_time_limit(120);

        $enviados = 0;
        $fallidos = 0;
        $detalle  = array();

        foreach ($destinos as $email => $lead) {
            $r = bovs_msj_demo_enviar($lead, $asunto, $cuerpo, $uid);

            if (!empty($r['ok'])) {
                $enviados++;
                $detalle[] = array('email' => $email, 'ok' => true, 'msg' => 'Enviado');
            } else {
                $fallidos++;
                $detalle[] = array(
                    'email' => $email,
                    'ok'    => false,
                    'msg'   => isset($r['error']) ? (string) $r['error'] : 'No se pudo enviar',
                );
            }

            /* Pequeña pausa entre correos: el servidor SMTP no tolera
               ráfagas y así también se evita que el proveedor marque el
               envío como masivo. */
            usleep(120000);
        }

        msjd_responder(array(
            'ok'       => true,
            'enviados' => $enviados,
            'fallidos' => $fallidos,
            'detalle'  => $detalle,
            'pendientes' => max(0, count($destinos) - $enviados - $fallidos),
        ));
        break;

    /* =================================================================
       HISTORIAL
       ================================================================= */
    case 'historial':

        msjd_responder(array('ok' => true, 'historial' => bovs_msj_demo_historial(40)));
        break;

    default:
        msjd_responder(array('ok' => false, 'msg' => 'Acción no reconocida.'), 400);
}
