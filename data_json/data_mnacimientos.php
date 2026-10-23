<?php
require_once '../models/Vmregnacimiento.php';
session_start();
$mfundos_id = $_SESSION['mfundos_id'];
$result = array();

$items = 0;
if (($mfundos_id != '') && ($mfundos_id != ' ')) {
	$data = Vmregnacimiento::find_by_sql("
		SELECT n.id AS id_animal,
		       n.id_ternero,
		       n.codigo_ternero,
		       n.codigo_rgb_id_animal_hembra,
		       n.codigo_rgb_id_animal_macho,
		       n.fichado,
		       n.nro_arete,
	       COALESCE(crias.cantidad, 1) AS cantidad_terneros,
	       COALESCE(NULLIF(crias.ids_terneros, ''), NULLIF(n.id_ternero, ''), 'Sin ID') AS ids_terneros
		  FROM Vmregnacimientos n
		  LEFT JOIN (
		       SELECT mregnacimientos_id,
		              COUNT(*) AS cantidad,
		              STRING_AGG(NULLIF(id_ternero, ''), ', ' ORDER BY nro_ternero) AS ids_terneros
		         FROM mternerosxnacimientos
		        WHERE status = 1
		     GROUP BY mregnacimientos_id
		  ) crias ON crias.mregnacimientos_id = n.id
		 WHERE n.mfundos_id=" . (int)$mfundos_id . " AND n.mestatus_id=1 AND n.estatus_regnac=1
		 ORDER BY n.codigo_ternero DESC;");

	foreach ($data as &$rs) {




		array_push($result, array(
			"codigo_ternero" => $rs->codigo_ternero,
			"id_ternero" => $rs->id_ternero,
			"cantidad_terneros" => (int)$rs->cantidad_terneros,
			"terneros" => (int)$rs->cantidad_terneros . ((int)$rs->cantidad_terneros === 1 ? " cría: " : " crías: ") . $rs->ids_terneros,
			"fichado" => $rs->fichado,
			"codigo_rgb_id_animal_hembra" => $rs->codigo_rgb_id_animal_hembra,
			"nro_arete" => $rs->nro_arete,
			"codigo_rgb_id_animal_macho" => $rs->codigo_rgb_id_animal_macho,
			"id" => $rs->id_animal

		));
		$items++;
	}
}

print_r(json_encode($result));
