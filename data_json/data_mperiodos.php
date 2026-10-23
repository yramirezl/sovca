<?php
require_once '../models/Mperiodo.php';
$result = array();
$items = 0;


@session_start();
@$id_sesion = $_SESSION['idsessionx'];
@$mfundos_id = $_SESSION['mfundos_id'];
@$rol = $_SESSION['rolx'];
@$t_ganaderia = $_SESSION['tipo_ganaderia'];

$mfundos_id = intval($mfundos_id);

if (($mfundos_id!='')&&($mfundos_id!=' ')){


				if (!empty($id_sesion) && ($rol == 1 || $rol == 4)) { //privilegios del programador y Supervisor

					$data = Mperiodo::find_by_sql("select id, nombre, dias_gestacion, status from mperiodos where status>0 and  mfundos_id=0 order by nombre desc;");
				} else if (!empty($id_sesion) && ($rol == 2)) {
					$data = Mperiodo::find_by_sql("	select id,dias_gestacion, status,nombre
										from mperiodos where  mtipoganaderias_id=".$t_ganaderia." and  mfundos_id=" . $mfundos_id . " and status>0 
										union
										select id,dias_gestacion, status,nombre										
										from mperiodos where  mtipoganaderias_id=".$t_ganaderia." and  mfundos_id=0 and status>0 and
										codper_bov NOT in (select DISTINCT b.codper_bov from mperiodos b 
															where  mtipoganaderias_id=".$t_ganaderia." and  b.mfundos_id=" . $mfundos_id . " ) ");
				}




foreach ($data as &$rs) {




	array_push($result, array(
		"id" => $rs->id,
		"nombre" => $rs->nombre,
		"dias_gestacion" => $rs->dias_gestacion,
		"status" => $rs->status

	));
	$items++;
}
}
print_r(json_encode($result));
