<?php
require_once '../models/Mraza.php';

@session_start();
@$id_sesion = $_SESSION['idsessionx'];
@$mfundos_id = $_SESSION['mfundos_id'];
@$rol = $_SESSION['rolx'];
@$t_ganaderia = $_SESSION['tipo_ganaderia'];


$mfundos_id=intval($mfundos_id);

if (($mfundos_id!='')&&($mfundos_id!=' ')){
if(!empty($id_sesion) && (($rol==1)||($rol==4))){//privilegios del programador y Supervisor

		$data = Mraza::find_by_sql("select id,nombre,status,mfundos_id  from mrazas where mfundos_id=0 and status>0 order by mfundos_id,nombre desc;");


	}else if(!empty($id_sesion)&& ($rol==2) ||($rol==3)){
		$data = Mraza::find_by_sql("select id,nombre,status,mfundos_id from mrazas where mtipoganaderias_id=".$t_ganaderia." and (mfundos_id=".$mfundos_id." or mfundos_id=0) and status>0 order by mfundos_id,nombre desc;");

	}


	$result = array();
	$items = 0;
	foreach ($data as &$rs) {
if ($rs->mfundos_id==0){
	$valor='PURA';
}else{
	$valor='Cruce';
}


		array_push($result,array(   "id"=>$rs->id,
		                            "nombre"=>$rs->nombre,
									"condicion"=>$valor									

		                         ));
		$items++;
	}
}

	print_r(json_encode($result));
