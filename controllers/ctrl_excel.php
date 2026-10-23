<?php 
error_reporting(E_ALL);
ini_set('display_errors', '1');
// ini_set('memory_limit', '512M');
// set_time_limit(0);

// require_once '../config/conexion.php';
require_once '../lib/phpexcel/class.generar_excel.php';


//######### incluir los modelos aqui ###################################
require_once '../models/Vw_facturado.php';


//######################################################################


@$columnas = $_POST['check_list'];
@$vista_consultar = $_POST['vista_consultar'];
@$nombre_documento = "";
@$titulo_documento = "";


@$filas_excel = array();
@$columnas_excel = array('nombre','email','twitter');


$n_condiciones = 0;
$str_condiciones = "";






foreach( $_POST as $key => $value ) {
 

	// if (strncmp('filtro_', $key, 7) === 0 && $_POST[$key] != ""){
		
	// 	$n_condiciones++;
		
		
	// 	$str_cmp_1 = str_replace('filtro_','',$key);
		
	// 	$str_cmp_2 = str_replace('_',' ',$str_cmp_1);
		
	// 	$param1 =  explode("#",$str_cmp_2);
		
	// 	$str_cmp_3 = ($param1[0]);
		
	// 	$tipodato = ($param1[1]);
		
		
	// 	if($n_condiciones == 1){
			
			
	// 		 $str_condiciones .= ' where "'.$str_cmp_3.'"';
			 
			 
	// 		 $rango = explode("/",$_POST[$key]);
			 
			  
			 
	// 		 if(count($rango) > 1){
				 
	// 			 if($tipodato == "numeric"){
				 
	// 				$str_condiciones .= " between ". $rango[0]." and ". $rango[1]." ";
				
	// 			 }elseif($tipodato == "date"){
				 
	// 				$str_condiciones .= " between ". $rango[0]." and ". $rango[1]." ";
				 
	// 			 }else{
				 
	// 			    $str_condiciones .= " in ('". $rango[0]."','". $rango[1]."') ";
				    
	// 			  }
				 
	// 		 }else{
				 
	// 			 $str_condiciones .= " ilike '%".$_POST[$key]."%'";
				 
	// 		 }
			 	
			
						
	// 	}
		
	// 	if($n_condiciones > 1){
			
			
			
	// 		$str_condiciones .= ' and "'.$str_cmp_3.'"';
			
	// 		$str_condiciones .= " ilike '%".$_POST[$key]."%'";			
			
			
			
			
	// 	}
		
		
			
	// 		//	 print_r($str_condiciones);
			 
	// 	//	 exit();
		
		
		
	// }
	
	

			
  
}//foreach



if($vista_consultar){
	
	$sql = "";
	$campos = "";
	$nro_campos = 10;//count($columnas);
	

//######### crear la tira SQL #############################################
	
// 	if($nro_campos > 0){
		
// 		for ($i = 0; $i <= $nro_campos-1; $i++) {
			
// 			if($i == 0){
				
// 				$campos .= '"'.$columnas[$i].'"';
				
// 			}elseif($i <= $nro_campos-1){
				
// 				$campos .= ', "'.$columnas[$i].'"';
				
// 			}
			
			
// 			// array_push($columnas_excel,$columnas[$i]);
			
// 		}
		
// 		$sql = "select ".$campos." from ".strtolower($vista_consultar)."s ".$str_condiciones.";";
		
		
			
// //	print_r($sql);
// //	exit();	   	
		
// 	}
	$sql = "select id_sol,nombre,fecha2 from vw_facturados; ";


//############## busqueda en las vistas ####################################
	
	if($vista_consultar == "Vw_facturados"){
		
		
		@$datos = Vw_facturado::find_by_sql($sql);
		
	
		$nombre_documento = "Vw_facturado";
		$titulo_documento = "Vw_facturado";
		
	}
	//  print_r($datos);exit;


	

//####### crear el excel de acuerdo a los datos obtenidos #################


	$n_datos = count($datos);
	$n_columnas_excel = count($columnas_excel);


	for ($i = 0; $i <= ($n_datos - 1); $i++){
		
		
		@$fila = array();
		
		for ($c = 0; $c <= ($n_columnas_excel - 1); $c++){
			
			$col = strtolower($columnas_excel[$c]);


			// if(  gettype($datos[$i]->$col) == 'object' ){// si es typo object es porque es un dato tipo fecha del orm
				
			 	// array_push($fila,$datos[$i]->$col->format('d-m-Y'));
				
			// }else
			// {
								
				 array_push($fila,$datos[$i]->$col);
			// }
			
			
			
			
		}//end for 2
				
			 array_push($filas_excel, $fila);
		
	}//end for 1
	

	
	$excel = new get_excel();
 
    $excel->print_excel($nombre_documento,$titulo_documento,$columnas_excel,$filas_excel);
	

//#########################################################################
	
	
}

?>
