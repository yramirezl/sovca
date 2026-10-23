<?php
require_once '../models/Vmdetacsxlote.php';
session_start();
$mfundos_id = $_SESSION['mfundos_id'];

$id_cs_x_lote = $_GET["id_cs_x_lote"];

$result = array();

if (($id_cs_x_lote != '') && ($mfundos_id != ' ')) {
	@$data = Vmdetacsxlote::find_by_sql("select mcontrolsanitarioxlotes_id as id_reg,nombre,nro_arete, raza,edad 
													from Vmdetacsxlotes
													where mfundos_id=" . $mfundos_id . " 
													and mcontrolsanitarioxlotes_id= " . $id_cs_x_lote . " 									
													order by nombre asc;");


	$result = array();
	foreach ($data as &$rs) {

		if ($rs->raza != '') {
			$raza = $rs->raza;
		} else {
			$raza = 'No Definida';
		}

		array_push($result, array(
			"id_reg" => $rs->id_reg,
			"nombre" => $rs->nombre,
			"nro_arete" => $rs->nro_arete,
			"raza" => $rs->raza,
			"edad" => $rs->edad

		));
	}
}

echo json_encode($result);
