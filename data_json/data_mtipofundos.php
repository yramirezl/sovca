<?php
require_once '../models/Mtipofundo.php';
	$result = array();
	$data = Mtipofundo::find_by_sql("select id,nombre,status from mtipofundos where status>0 order by id desc;");
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