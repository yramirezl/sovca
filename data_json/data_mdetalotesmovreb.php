<?php
require_once '../models/Vdetamovimientoxlote.php';
session_start();
$mfundos_id = $_SESSION['mfundos_id'];

$id_reg = $_GET["id_registro"];

$result = array();

if (($id_reg!='')&&($mfundos_id!=' ')){
@$data = Vdetamovimientoxlote::find_by_sql("select id_manimales,nombre,nro_arete,raza,edad from Vdetamovimientoxlotes
                                     where mfundos_id=" . $mfundos_id . " 
                                     and id_mdetamovanimxlote= " . $id_reg . " order by nombre desc;");


$result = array();
foreach ($data as &$rs) {

if ($rs->raza!=''){
				$raza=$rs->raza;

			}else{
			$raza='No Definida';
			}

	array_push($result, array(
		"id_manimales" => $rs->id_manimales,
		"nombre" => $rs->nombre,
		"nro_arete" => $rs->nro_arete,
		"raza" => $raza,
		"edad" => $rs->edad


	));
}
}

echo json_encode($result);


?>