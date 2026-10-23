<?php
require_once '../models/Mdetamovanimxlote.php';
session_start();
$mfundos_id = $_SESSION['mfundos_id'];

$result = array();

if ($mfundos_id!=' '){
@$data = Mdetamovanimxlote::find_by_sql("	select
											a.id as id_registro,
											a.cantidad_lote_origen,
											a.cantidad_exc_lote_origen,
											a.fecha_mov,
											a.motivo,
											a.responsable,
											(select b.nombre from mlotes b where b.id =a.id_lote_origen ) as nombre_lote_origen,
											(select c.nombre from mlotes c where c.id =a.id_lote_destino ) as nombre_lote_destino

											from mdetamovanimxlotes a  where a.mfundos_id=" . $mfundos_id . " and a.status<>0 order by a.fecha_mov desc;");


$result = array();
foreach ($data as &$rs) {
	
if ($rs->fecha_mov!=Null){
				$fecha_mov=$rs->fecha_mov->format("d-m-Y");

			}else{
			$fecha_mov='No Registrada';
			}

	array_push($result, array(
		"id_registro" => $rs->id_registro,
		"nombre_lote_origen" => $rs->nombre_lote_origen,
		"nombre_lote_destino" => $rs->nombre_lote_destino,
		"fecha_mov" => $fecha_mov,
		"motivo" => $rs->motivo,
		"responsable" => $rs->responsable



	));
}
}

echo json_encode($result);


?>