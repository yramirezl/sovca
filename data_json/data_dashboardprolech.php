<?php
require_once '../models/Mprolechera.php';


session_start();
$mfundos_id = $_SESSION['mfundos_id'];

$tipo=$_GET["tipo"];

$result = array();



  $data = Mprolechera::find_by_sql("SELECT fecha_produccion, sum(cantidad_leche_m+cantidad_leche_t) as total_leche
										        	FROM mprolecheras where mfundos_id=".$mfundos_id." and 							                       
																	status=1 and fecha_produccion > (CURRENT_DATE - 60)
																	group by fecha_produccion order by fecha_produccion asc limit 100;");

	
	
      if($data !=null){

      foreach ($data as &$rs) {

        array_push($result,array(   
          "total_leche"=>$rs->total_leche,
          "fecha_produccion"=>$rs->fecha_produccion->format("d-m-Y")
        ));	

      }
    }else{
      $nom="";		
      $val=0;
        
          array_push($result,array(   
            "total_leche"=>$val,
            "fecha_produccion"=>$nom		
          ));	
    }


echo json_encode($result);

?>

