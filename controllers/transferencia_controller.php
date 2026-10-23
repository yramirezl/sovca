<?php
require_once '../models/Mcontacto.php';

include '../lib/sanitizar.php';

require_once '../lib/PHPExcel/Classes/PHPExcel/IOFactory.php';
date_default_timezone_set('America/Caracas');
$datos = ($_POST["datos"]);
$errores = array();
$mensaje_errores ="";

$arry_dir = array();

if ($gestor = opendir('../file/')) {
	while (false !== ($arch = readdir($gestor))) {
		if ($arch != "." && $arch != "..") {
		   $arry_dir[].= $arch;
		}
	}
}

$contar_arch = count($arry_dir);


if($contar_arch == 2){

	if (($arry_dir[0]=='MCONTACTOS.xlsx')||(($arry_dir[0]=='MCONTACTOS.xls'))){
		$archivo="../file/".($arry_dir[0]);
	}else{
		$archivo="../file/".($arry_dir[1]);
	}
       

	$data = PHPExcel_IOFactory::load($archivo);
	$hoja = $data->getActiveSheet()->toArray(null,true,true,true);

	
	$encabezado = $hoja[1];

	$cant_colum = count($encabezado);



	$encab_col_1 = ($hoja[1]['A']);//nombres
	$encab_col_2 = ($hoja[1]['B']);//correo
	$encab_col_3 = ($hoja[1]['C']);//telefono
	$encab_col_4 = ($hoja[1]['D']);// ganaderia
	

	//print_r($hoja[1]);
	//exit();
	
	if($cant_colum == 4){
		
		if($encab_col_1 != 'NOMBRE'){
			$errores[] .='El nombre encabezado de la columna 1 debe ser igual a "NOMBRE".';
		}elseif($encab_col_2 != 'CORREO'){
			$errores[] .='El nombre encabezado de la columna 2 debe ser igual a "CORREO".';
		}elseif($encab_col_3 != 'TLF'){
			$errores[] .='El nombre encabezado de la columna 3 debe ser igual a "TLF".';
		}elseif($encab_col_4 != 'PREF'){
			$errores[] .='El nombre encabezado de la columna 4 debe ser igual a "PREF".';
		}else{//si los encabezados son correctos
			
			$cant_rec = count($hoja);
			$hoy = date("d-m-Y"); 
			
			session_start();
			$usuario_id = $_SESSION['idusuariox'];

			
			$rec = 0;
			$rs = Mcontacto::find('all');
			//  print_r($rs);
			if(!empty($rs)){
				if($datos!=1){
					Mcontacto::delete_all(array('conditions' => array('id <> ?', 0)));
				}
			}

			for ($i = 2; $i <= $cant_rec; $i++) {
	 
								
					$nombre = trim($hoja[$i]['A']);	
					$correo = trim($hoja[$i]['B']);									
					$telefono = trim($hoja[$i]['C']);
					if( ($correo!='') && ($nombre!='') ){
					 $contacto = new Mcontacto();
					 $contacto->name = $nombre;
					 $contacto->email = $correo;
					 $contacto->telefono = $telefono;
					 $contacto->user_create = $usuario_id; //el usuario que esta en sesion
					 $contacto->created = $hoy;
					 $contacto->save();

					}
					 
					 
					 $rec++;
					 
			

			}//end for

			if(empty($errores)){
				if($rec >= 1 ){
					echo '<div class="success">La transferencia de datos se realizo correctamente.  '.$rec.' items registrados</div>';
				}
			}

			
		}
		
	}else{
		$errores[] .= 'El documento debe poseer 4 columnas y con un emcabezado como el siguiente: NOMBRE, CORREO, TLF, PREF';
	}


}else{

	$errores[] .= 'No existe archivo tipo Excel para transferir la información.';

}

/***********************************************************************/

if(!empty($errores)){
	$mensaje_errores .='<ul class="validation">';
	foreach ($errores as $msj){
       $mensaje_errores .='<li style="color: #D8000C;">'.$msj.'</li>';
    }
    $mensaje_errores .='</ul>';
    
    echo $mensaje_errores;
}

?>
