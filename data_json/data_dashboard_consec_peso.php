<?php
require_once '../models/Mconsecutivopeso.php';


session_start();
$mfundos_id = $_SESSION['mfundos_id'];

$tipo=$_GET["tipo"];

$result = array();



  $data = Mconsecutivopeso::find_by_sql("SELECT fecha_pesaje, sum(peso) as total_peso
										        	FROM Mconsecutivopes where mfundos_id=".$mfundos_id." and 							                       
																	status=1 and fecha_pesaje > (CURRENT_DATE - 60)
																	group by fecha_pesaje order by fecha_pesaje asc limit 100;");

	
	
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

