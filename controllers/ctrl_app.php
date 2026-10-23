<?php 
// require_once '../config/conexion.php';


//######### incluir los modelos aqui ###################################
require_once '../models/Vw_facturado.php';
date_default_timezone_set('America/Bogota');


//######################################################################

@$action = $_POST['action'];
@$vista = $_POST['vista'];

if($action == "obtener_interfaz"){


	switch ($vista) {
		
		case 'Vw_facturados':
		
			@$columns =  array('nombre','email','twitter');;//Vw_facturado::table()->columns;
			
			$n_filtros =  array('id','id','id');//Vw_facturado::first()->nro_filtros;
			
			generar_checkboxs_inputs($columns,$vista,$n_filtros);
				
			break;
			
		
			
	}//end switch
	

}//end if



function generar_checkboxs_inputs($columns,$vista,$n_filtros){
	
	@$result = array();
	@$options_tag = "";
	@$inputs_tag = "";
	

	$indice = 0;
	
	foreach ($columns as $column) {
		
		
		if($column->name != 'nro_filtros'){
		
		   $options_tag .= "<div class='checkbox'><label><input type='checkbox' name='check_list[]' value='{$column->name}' checked>{$column->name}</label></div>";
		
		}
		
		if($indice <= $n_filtros){
			
			$inputs_tag .= '<div class="form-group">';
			
			if ($column->raw_type == 'numeric' || $column->raw_type == 'date'){
				
				$inputs_tag .= '<label>'.$column->name.' <span class="glyphicon glyphicon-resize-horizontal" aria-hidden="true"></span></label>';
			
			}else{
				
				$inputs_tag .= '<label>'.$column->name.'</label>';
				
			}
			$inputs_tag .= '<input class="form-control" type="text" name="filtro_'.$column->name.'#'.$column->raw_type.'" id="filtro_'.$column->name.'" >';
			$inputs_tag .= '</div>';
			
		}
		
		$indice++;
	}

	$inputs_tag .= '<input type="hidden" name="vista_consultar" id="vista_consultar" value="'.$vista.'">';
				
	array_push($result,array("columnas"=>$options_tag,"filtros"=>$inputs_tag));

	echo json_encode($result);	
	
	
}

?>
