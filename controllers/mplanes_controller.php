<?php
require_once '../models/Mplane.php';

date_default_timezone_set('America/Caracas');

@$action = ($_POST["action"]);
@$id = ($_POST["id_rol"]);
@$id_rol = ($_POST["id_rol"]);
@$record = ($_POST["record"]);
/***********************************/
@$nombre_rol = strtoupper($_POST["nombre_rol"]);
/**********************************/

switch ($action){
  case 'get_t_plan':
    @$data = Mplane::find('all',array('conditions' => array('status=?',1)));

		$resp  = '';
		if (!empty($data)) {
			$resp = '<option value="*">Seleccione el Tipo de Plan</option>';

			foreach ($data as $rs) {
        $nombre=$rs->nombre;
        $max_reg=$rs->max_reg;
        // El cupo de fundos lo define el plan (mplanes.max_fundos). Se envía
        // en el atributo data- para que el formulario de usuario lo muestre
        // sin necesidad de otra petición.
        $max_fundos = (int) $rs->max_fundos;
        if ($max_fundos < 1) {
            $max_fundos = 1;
        }
        $detalle=$nombre.'  ( Max Reg. '.$max_reg.' )';

				$resp .= '<option value="' . $rs->id . '" data-max-fundos="' . $max_fundos . '">' .  $detalle . '</option>';
			}
			echo $resp;
		}
		break;
    #******************************************************************************


    #******************************************************************************

    // =========================================================
    // MÓDULO DE PLANES  (solo Administrador / Super Usuario)
    // ---------------------------------------------------------
    // El plan define los registros y los fundos que admite una cuenta.
    // Se puede ajustar bajo demanda: subir el cupo de fundos de un plan
    // permite dar más fincas sin obligar a cambiar de plan al productor.
    // =========================================================

    case 'planes_listar':
        planes_requiere_admin();
        $rows = Mplane::find_by_sql("
            SELECT p.id, p.nombre, p.max_reg, p.max_fundos, p.status,
                   (SELECT COUNT(*) FROM musuarios u
                     WHERE u.mplanes_id = p.id AND u.status = 1) AS cuentas,
                   (SELECT COUNT(*) FROM musuarios u
                     WHERE u.mplanes_id = p.id AND u.status = 1 AND u.es_multifundo) AS multifundo
              FROM mplanes p
          ORDER BY p.max_reg ASC, p.id ASC
        ");
        $out = array();
        foreach ($rows as $r) {
            $out[] = array(
                'id'         => (int) $r->id,
                'nombre'     => $r->nombre,
                'max_reg'    => (int) $r->max_reg,
                'max_fundos' => (int) $r->max_fundos,
                'status'     => (int) $r->status,
                'cuentas'    => (int) $r->cuentas,
                'multifundo' => (int) $r->multifundo,
            );
        }
        echo json_encode(array('ok' => true, 'planes' => $out));
        break;

    case 'planes_guardar':
        planes_requiere_admin();
        $pid        = (int) (isset($_POST['plan_id']) ? $_POST['plan_id'] : 0);
        $nombre     = trim((string) (isset($_POST['nombre']) ? $_POST['nombre'] : ''));
        $max_reg    = (int) (isset($_POST['max_reg']) ? $_POST['max_reg'] : 0);
        $max_fundos = (int) (isset($_POST['max_fundos']) ? $_POST['max_fundos'] : 0);
        $status     = (int) (isset($_POST['status']) ? $_POST['status'] : 1);

        if ($nombre === '') {
            echo json_encode(array('ok' => false, 'msg' => 'El nombre del plan es obligatorio.'));
            break;
        }
        if ($max_reg < 1 || $max_reg > 1000000) {
            echo json_encode(array('ok' => false, 'msg' => 'Los registros deben estar entre 1 y 1.000.000.'));
            break;
        }
        if ($max_fundos < 1 || $max_fundos > 100) {
            echo json_encode(array('ok' => false, 'msg' => 'Los fundos deben estar entre 1 y 100.'));
            break;
        }

        $safe = addslashes($nombre);
        $dup  = Mplane::find_by_sql("SELECT id FROM mplanes
                                      WHERE upper(nombre) = upper('$safe') AND id <> $pid
                                      LIMIT 1");
        if (!empty($dup)) {
            echo json_encode(array('ok' => false, 'msg' => 'Ya existe un plan con el nombre "' . $nombre . '".'));
            break;
        }

        $plan = $pid > 0 ? Mplane::find($pid) : new Mplane();
        if ($pid > 0 && !$plan) {
            echo json_encode(array('ok' => false, 'msg' => 'El plan no existe.'));
            break;
        }
        $plan->nombre     = $nombre;
        $plan->max_reg    = $max_reg;
        $plan->max_fundos = $max_fundos;
        $plan->status     = ($status === 0) ? 0 : 1;

        if (!$plan->save()) {
            echo json_encode(array('ok' => false, 'msg' => 'No se pudo guardar el plan.'));
            break;
        }

        $pid = (int) $plan->id;

        /* El cupo de la ficha se recalcula con el nuevo valor del plan. Se usa
           GREATEST para no dejar fuera de cupo a una cuenta que ya tiene más
           fundos de los que el plan pasa a permitir. */
        Mplane::connection()->query("
            UPDATE musuarios u
               SET max_fundos = GREATEST($max_fundos,
                       (SELECT COUNT(*) FROM mfundosxusuarios fx
                         WHERE fx.musuarios_id = u.id AND fx.status = 1))
             WHERE u.mplanes_id = $pid AND u.es_multifundo = TRUE
        ");

        echo json_encode(array(
            'ok'  => true,
            'msg' => 'Plan guardado.',
            'id'  => $pid,
        ));
        break;

    case 'planes_estado':
        planes_requiere_admin();
        $pid    = (int) (isset($_POST['plan_id']) ? $_POST['plan_id'] : 0);
        $status = ((int) (isset($_POST['status']) ? $_POST['status'] : 1)) === 0 ? 0 : 1;
        $plan   = $pid > 0 ? Mplane::find($pid) : null;
        if (!$plan) {
            echo json_encode(array('ok' => false, 'msg' => 'El plan no existe.'));
            break;
        }
        if ($status === 0) {
            $enUso = Mplane::find_by_sql("SELECT COUNT(*) AS n FROM musuarios
                                           WHERE mplanes_id = $pid AND status = 1");
            $n = $enUso ? (int) $enUso[0]->n : 0;
            if ($n > 0) {
                echo json_encode(array(
                    'ok'  => false,
                    'msg' => 'No se puede desactivar: ' . $n . ' cuenta(s) activa(s) usan este plan. '
                           . 'Cámbieles el plan primero.',
                ));
                break;
            }
        }
        $plan->status = $status;
        echo json_encode($plan->save()
            ? array('ok' => true, 'msg' => $status ? 'Plan activado.' : 'Plan desactivado.')
            : array('ok' => false, 'msg' => 'No se pudo cambiar el estado.'));
        break;

}//end switch

/* ============================================================
   Helpers del módulo de planes
   ============================================================ */

/** Restringe el módulo de planes al Super Usuario (rol 4). */
function planes_requiere_admin()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $rol = isset($_SESSION['rolx']) ? (int) $_SESSION['rolx'] : 0;
    if ($rol !== 4) {
        http_response_code(403);
        echo json_encode(array('ok' => false, 'msg' => 'Acceso restringido al Super Usuario.'));
        exit;
    }
}
?>
