<?php
require_once '../models/Mtiposervicio.php';
	$result = array();
	$data = Mtiposervicio::find_by_sql("select id,nombre,status from Mtiposervicios where status>0 order by id desc;");
	$items = 0;
	foreach ($data as &$rs) {




		array_push($result,array(   "id"=>$rs->id,
		                            "nombre"=>$rs->nombre,
									"status"=>$rs->status

		                         ));
		$items++;
	}

	print_r(json_encode($result));
?>