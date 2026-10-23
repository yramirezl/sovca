<?php
require_once '../models/Vdatafundo.php';
	$result = array();
	$data = Vdatafundo::find_by_sql("select 
										cid_rif,fundo,(telefono ||'/ '||email) as contacto,can_animales,can_servicios,
										can_prod_leche,can_control,can_consec_peso,can_desincorp,usuario,fecha
										from Vdatafundos order by fundo desc;");
	$items = 0;
	foreach ($data as &$rs) {

			if ($rs->fecha!=''){
				$fecha=$rs->fecha->format("d-m-Y");

			}else{
			$fecha='No Detectada';
			}


		array_push($result,array(   "cid_rif"=>$rs->cid_rif,
		                            "fundo"=>$rs->fundo,
									"contacto"=>$rs->contacto,
									"can_animales"=>$rs->can_animales,
									"can_servicios"=>$rs->can_servicios,
									"can_prod_leche"=>$rs->can_prod_leche,
									"can_control"=>$rs->can_control,
									"can_consec_peso"=>$rs->can_consec_peso,
									"can_desincorp"=>$rs->can_desincorp,
									"usuario"=>$rs->usuario,
									"conexion"=>$fecha

		                         ));
		$items++;
	}

	print_r(json_encode($result));
?>