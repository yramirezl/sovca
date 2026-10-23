<?php
require_once '../models/Mcausadescarte.php';
	$result = array();
	$data = Mcausadescarte::find_by_sql("select id, nombre, status from mcausadescartes where status>0 order by id desc;");
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