<?php
require_once '../models/Vcontrolsanitario.php';
session_start();
$mfundos_id = $_SESSION['mfundos_id'];

	$result = array();
	$items = 0;
	if (($mfundos_id!='')&&($mfundos_id!=' ')){
	$data = Vcontrolsanitario::find_by_sql("select id, codigo_rgb_id_animal,fecha_control,nombre_animal,
												fecha_prox_control,evento,nro_arete
	       										  from Vcontrolsanitarios where mfundos_id=".$mfundos_id." 
												   order by codigo_rgb_id_animal desc;");

	foreach ($data as &$rs) {

if ($rs->fecha_control!=Null){
				$fecha_control=$rs->fecha_control->format("d-m-Y");

			}else{
			$fecha_control='No Registrada';
			}
if ($rs->fecha_prox_control!=Null){
				$fecha_prox_control=$rs->fecha_prox_control->format("d-m-Y");

			}else{
			$fecha_prox_control='No Registrada';
			}

		array_push($result,array(    "codigo_rgb_id_animal"=>$rs->codigo_rgb_id_animal,
									 "nro_arete"=>$rs->nro_arete,
 									 "nombre_animal"=>$rs->nombre_animal,
									 "evento"=>$rs->evento,										 
									 "fecha_control"=>$fecha_control,
									 "fecha_prox_control"=>$fecha_prox_control,
									 "nro_arete"=>$rs->nro_arete,
									 "id"=>$rs->id

		                         ));
		$items++;
	}
	}
	print_r(json_encode($result));
