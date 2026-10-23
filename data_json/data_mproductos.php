<?php
require_once '../models/Vmproductocontrole.php';
session_start();
$mfundos_id = $_SESSION['mfundos_id'];



$result = array();
if (($mfundos_id!='')&&($mfundos_id!=' ')){

@$data = Vmproductocontrole::find_by_sql("select id_producto,nombre_producto,	
									nombre_categoria 
                                    from Vmproductocontroles
									 where mfundos_id=" . $mfundos_id . "  
									 order by nombre_producto asc;");


foreach ($data as &$rs) {


	array_push($result, array(
		"id" => $rs->id_producto,
		"nombre_producto" => $rs->nombre_producto,
		"nombre_categoria" => $rs->nombre_categoria

	));
}
}

echo json_encode($result);


?>