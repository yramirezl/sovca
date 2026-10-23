<?php
require_once '../models/Mtipoganado.php';
	$result = array();
	$data = Mtipoganado::find_by_sql("select id,nombre,status from mtipoganados where status>0 order by id desc;");
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