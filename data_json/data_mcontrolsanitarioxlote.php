<?php
require_once '../models/Vcontrolsanitariosxlote.php';
session_start();
$mfundos_id = $_SESSION['mfundos_id'];

	$result = array();
	$items = 0;
	if (($mfundos_id!='')&&($mfundos_id!=' ')){
	$data = Vcontrolsanitariosxlote::find_by_sql("select distinct(id) as id, codigo_mcs_lote,fecha_control,nombre_lote,
												fecha_prox_control,evento
	       										  from Vcontrolsanitariosxlotes where mfundos_id=".$mfundos_id." 
												   order by codigo_mcs_lote desc;");

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



		array_push($result,array(    "codigo_mcs_lote"=>$rs->codigo_mcs_lote,
									 "evento"=>$rs->evento,										 
									 "fecha_control"=>$fecha_control,
									 "fecha_prox_control"=>$fecha_prox_control,
									 "lote"=>$rs->nombre_lote,
									 "id"=>$rs->id

		                         ));
		$items++;
	}
	}
	print_r(json_encode($result));
