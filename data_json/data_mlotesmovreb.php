<?php
require_once '../models/Vmovimientoxlote.php';
session_start();
$mfundos_id = $_SESSION['mfundos_id'];

$id_lote = $_GET["id_lote"];

$result = array();

if (($id_lote!='')&&($mfundos_id!=' ')){
@$data = Vmovimientoxlote::find_by_sql("select id_manimales,nombre,nro_arete,raza,edad from Vmovimientoxlotes
                                     where mfundos_id=" . $mfundos_id . " 
                                     and id= " . $id_lote . " 
									 and id_manimales not in (select id_manimal from mmovanimxlotestemps
									 where id_lotes= " . $id_lote . "  )
									 order by nombre asc;");


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