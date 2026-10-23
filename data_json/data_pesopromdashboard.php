<?php
require_once '../models/Mconsecutivopeso.php';
include '../lib/sanitizar.php';


session_start();
$mfundos_id = $_SESSION['mfundos_id'];
$cargar_data=$_GET["cargar_grafica"];

$cod_animal=$_GET["cod_rgb_animal"];

$result = array();
if (($mfundos_id!='')&&($mfundos_id!=' ')){
$partes = explode("-", $cod_animal);
$prefijo = $partes[0];
$nro = 0;
if ($prefijo == 'SOVCA' || $prefijo == 'BOVS') {
  $nro = intval($partes[2]);
} else {
  $nro = intval($cod_animal);
}

$cod_rgb_animal = 'SOVCA-0' . $mfundos_id . '-0' . $nro;

	$cont = 1;
	
	if($cod_animal!=null){
	
	$data =Mconsecutivopeso::find_by_sql("SELECT fecha_pesaje, peso 
	                                           FROM Mconsecutivopesos
											     where  mfundos_id=".$mfundos_id." and 
														codigo_rgb_id_animal='$cod_rgb_animal' and
														status=1
														group by fecha_pesaje, peso order by fecha_pesaje asc limit 10;");


	
	if($data !=null){

	foreach ($data as &$rs) {

		if ($rs->fecha_pesaje!=Null){
				$fecha_pesaje=$rs->fecha_pesaje->format("d-m-Y");

			}else{
			$fecha_pesaje='No Registrada';
			}

		array_push($result,array(   
			"peso_alza"=>$rs->peso,
			"fecha_pesaje_alza"=>$fecha_pesaje
		));	
		$cont = $cont + 1;		

	}
}else{
	$nom="";		
	$val=0;
		
			array_push($result,array(   
				"peso_alza"=>$val,
				"fecha_pesaje_alza"=>$nom		
			));	
}
	
}else{
	$nom="";		
	$val=0;	
		
			array_push($result,array(   
				"peso_alza"=>$val,
				"fecha_pesaje_alza"=>$nom		
			));	
}
}
	echo json_encode($result);
?>