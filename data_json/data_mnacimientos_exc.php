<?php
require_once '../models/Vmregnacimiento.php';
session_start();
$mfundos_id = $_SESSION['mfundos_id'];
$result = array();

$items = 0;
if (($mfundos_id != '') && ($mfundos_id != ' ')) {
	$data = Vmregnacimiento::find_by_sql(" select 		
											b.id as id_reg,
											a.codigo_ternero,
											a.codigo_rgb_id_animal_hembra,
											a.codigo_rgb_id_animal_macho,nro_arete
											from Vmregnacimientos a, mdsinfichas b
											where a.codigo_ternero=b.codigo_ternero
											  
											and a.mfundos_id=" . $mfundos_id . " and a.mestatus_id!=1 order by a.codigo_ternero desc;");

	foreach ($data as &$rs) {




		array_push($result, array(
		     "id_reg" => $rs->id_reg,
			"codigo_ternero" => $rs->codigo_ternero,
			"codigo_rgb_id_animal_hembra" => $rs->codigo_rgb_id_animal_hembra,
			"nro_arete" => $rs->nro_arete,
			"codigo_rgb_id_animal_macho" => $rs->codigo_rgb_id_animal_macho

		));
		$items++;
	}
}

print_r(json_encode($result));
