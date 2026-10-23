<?php
require_once '../models/Vlistarusuario.php';
require_once '../models/Mplanesxfundo.php';
require_once '../models/Msuscripcion.php';
require_once '../lib/acceso_offline_helper.php';
date_default_timezone_set('America/Caracas');

session_start();
$mtipousuarios_id = $_SESSION['mtipo_user_id'];

/*
 * Permiso de acceso al modo sin conexión (offline) de TODOS los usuarios,
 * en una sola consulta. Se resuelve con la misma regla que usa el API:
 * el permiso es del PRODUCTOR dueño del fundo y el rol ENCARGADO lo
 * hereda (ver lib/acceso_offline_helper.php).
 */
$mapa_offline = bovs_acceso_offline_mapa(Vlistarusuario::connection());


$result = array();

if ($mtipousuarios_id == 1) {
	@$data = Vlistarusuario::find_by_sql("SELECT DISTINCT
												id, username, name, email, descripcion AS perfil,
												mroles_id, fecha_activacion, estatus, status, creado
											FROM Vlistarusuarios
											WHERE status IN (0, 1)
											ORDER BY estatus DESC, username ASC;");
} else {
	@$data = Vlistarusuario::find_by_sql("SELECT DISTINCT
												id, username, name, email, descripcion AS perfil,
												mroles_id, fecha_activacion, estatus, status, creado
											FROM Vlistarusuarios
											WHERE mtipousuarios_id <> 1 AND status IN (0, 1)
											ORDER BY estatus DESC, username ASC;");
}

/*
 * ────────────────────────────────────────────────────────────────
 *  RED DE SEGURIDAD #1: Deduplicar por id en PHP.
 *  Aunque la vista devuelva la misma fila N veces (por JOINs internos),
 *  aquí colapsamos a una sola fila por usuario.
 * ────────────────────────────────────────────────────────────────
 */
$dedup = array();
$ids_vistos = array();
if (!empty($data)) {
	foreach ($data as $rs) {
		$uid = (int)$rs->id;
		if (!isset($ids_vistos[$uid])) {
			$ids_vistos[$uid] = true;
			$dedup[] = $rs;
		}
	}
}
$data = $dedup;

/*
 * ────────────────────────────────────────────────────────────────
 *  RED DE SEGURIDAD #2: Ordenar en PHP.
 *  Activos (status=1) primero, luego inactivos (status=0).
 *  Dentro de cada grupo, username A→Z.
 * ────────────────────────────────────────────────────────────────
 */
usort($data, function ($a, $b) {
	$sa = (int)$a->status;
	$sb = (int)$b->status;
	if ($sa !== $sb) {
		return $sb - $sa;   // 1 antes que 0
	}
	return strcasecmp((string)$a->username, (string)$b->username);
});

foreach ($data as $rs) {

	$correo = $rs->email;

	if ($correo == '') {
		$correo = 'SIN CORREO';
	}

	if ($rs->status != 0) {
		$f_activacion = $rs->fecha_activacion->format("d-m-Y");
	} else {
		$f_activacion = 'No Aplica';
	}

	$fecha_caduca = '';
	$fecha_activa = '';
	$fecha_programada = '';
	$dias_restantes = null;
	$suscripcion_descripcion = '';
	$suscripcion_id = null;
	$tiene_suscripcion = false;
	$renovacion_programada = false;

	if ((int)$rs->mroles_id === 2) {
		$vigencia = Mplanesxfundo::find('first', array(
			'conditions' => array('musuarios_id=? AND msuscripciones_id IS NOT NULL', (int)$rs->id),
			'order' => 'id DESC'
		));
		if ($vigencia) {
			$suscripcion_id = (int)$vigencia->msuscripciones_id;
			if ($vigencia->fecha_activa_suscripcion instanceof DateTime) {
				$fecha_activa = $vigencia->fecha_activa_suscripcion->format('Y-m-d');
			} elseif (!empty($vigencia->fecha_activa_suscripcion)) {
				$fecha_activa = date('Y-m-d', strtotime($vigencia->fecha_activa_suscripcion));
			}
			if ($vigencia->fecha_caduca_suscripcion instanceof DateTime) {
				$fecha_caduca = $vigencia->fecha_caduca_suscripcion->format('Y-m-d');
			} elseif (!empty($vigencia->fecha_caduca_suscripcion)) {
				$fecha_caduca = date('Y-m-d', strtotime($vigencia->fecha_caduca_suscripcion));
			}
			$suscripcion = Msuscripcion::find((int)$vigencia->msuscripciones_id);
			if ($suscripcion) $suscripcion_descripcion = $suscripcion->descripcion;
			if ($fecha_caduca !== '') {
				$tiene_suscripcion = true;
				$hoy = new DateTime('today');
				if ($fecha_activa !== '' && $fecha_activa > $hoy->format('Y-m-d')) {
					$renovacion_programada = true;
					$fecha_programada = $fecha_activa;
				}
				$caduca = new DateTime($fecha_caduca);
				$dias_restantes = (int)$hoy->diff($caduca)->format('%r%a');
			}
		}
	}

	$estado_offline = isset($mapa_offline[(int)$rs->id])
		? $mapa_offline[(int)$rs->id]
		: array('efectivo' => false, 'heredado' => false, 'propio' => false, 'productores' => '');

	$texto_offline = bovs_acceso_offline_texto($estado_offline);

	array_push($result, array(
		"id" => (int)$rs->id,
		"username" => $rs->username,
		"name" => strtoupper($rs->name),
		"email" => strtoupper($correo),
		"perfil" => $rs->perfil,
		"rol_id" => (int)$rs->mroles_id,
		"estatus" => $rs->estatus,
		"fecha_creacion" => $rs->creado->format("d-m-Y"),
		"status" => intval($rs->status),
		"fecha_activacion" => $f_activacion,
		"acceso_offline" => intval($estado_offline['propio']),
		"modo_offline" => $texto_offline['etiqueta'],
		"modo_offline_orden" => $texto_offline['orden'],
		"modo_offline_detalle" => $texto_offline['detalle'],
		"fecha_caduca_suscripcion" => $fecha_caduca,
		"fecha_activa_suscripcion" => $fecha_activa,
		"renovacion_programada" => $renovacion_programada,
		"fecha_programada" => $fecha_programada,
		"dias_restantes_suscripcion" => $dias_restantes,
		"suscripcion" => $suscripcion_descripcion,
		"suscripcion_id" => $suscripcion_id,
		"tiene_suscripcion" => $tiene_suscripcion
	));
}

print_r(json_encode($result));