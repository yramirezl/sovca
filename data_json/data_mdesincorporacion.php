<?php
require_once '../models/Mdesincorporacionanimale.php';
require_once '../models/Vdesincorporacione.php';
	session_start();
	$mfundos_id = $_SESSION['mfundos_id'];
	$result = array();
	if (($mfundos_id!='')&&($mfundos_id!=' ')){
	$data = Vdesincorporacione::find_by_sql("select id_desincorporacion,codigo_rgb_id_animal,nombre_animal,estatus_animal,nro_arete from Vdesincorporaciones where mfundos_id=".$mfundos_id." and  status>0 and estatus_registro >0 order by codigo_rgb_id_animal desc;");
	$items = 0;
	foreach ($data as &$rs) {




		array_push($result,array(   "codigo"=>$rs->codigo_rgb_id_animal,
		                            "nombre"=>$rs->nombre_animal,
									"estatus_animal"=>$rs->estatus_animal,
									"id_desincorporacion"=>$rs->id_desincorporacion,
									"nro_arete"=>$rs->nro_arete



		                         ));
		$items++;
	}
	}
	print_r(json_encode($result));
?>