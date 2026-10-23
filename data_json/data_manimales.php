<?php
require_once '../models/Vanimale.php';

	session_start();
	$mfundos_id = $_SESSION['mfundos_id'];
	$result = array();

	if (($mfundos_id!='')&&($mfundos_id!=' ')){

	
	$data = Vanimale::find_by_sql("select   id,nombre,
											case when estatus_animal=1 then 'ACTIVO' 
											     when estatus_animal=2 then 'DONADOR ACTIVO' 
												 when estatus_animal=3 then 'DONADOR' 
												 when estatus_animal=4 then 'DONADORA ACTIVO' 
												when estatus_animal=5 then 'DONADORA' 
											     else 'No Definido' END as estatus,
												 case when sexo=1 then 'HEMBRA'
												 when sexo=2 then 'MACHO' 
												 else  'N/D' END as sexo_animal,
											codigo_rgb_id_animal,nro_chapeta,lote
											from vanimales 
											WHERE status=1 and mestatus_id=1 and mfundos_id=" . $mfundos_id . "
											order by id desc;");
											

	$items = 0;
	foreach ($data as &$rs) {

           $lote="No tiene Lote Asignado";
		   if($rs->lote!=""){
 			$lote=$rs->lote;

		   }

		array_push($result,array(   
									"codigo"=>$rs->codigo_rgb_id_animal,
									"nro_arete"=>$rs->nro_chapeta,
		                            "nombre"=>strtoupper($rs->nombre),
									"sexo_animal"=>$rs->sexo_animal,
		                            "estatus_animal"=>$rs->estatus,
									"lote"=>$lote,
									"id"=>$rs->id


		                         ));
		$items++;
	}
}

	print_r(json_encode($result));
?>