<?php
require_once '../models/Mestatusganado.php';
	$result = array();
	$data = Mestatusganado::find_by_sql("select id, nombre, status from mestatusganados where status>0 order by id desc;");
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