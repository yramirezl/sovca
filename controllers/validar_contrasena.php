<?php
session_start();
$usuario_id = $_SESSION['idusuariox'];

@$action = ($_POST['action']);
@$clave = ($_POST['clave']);

if($usuario_id != ""){

	if($action == "validar"){
		
		$html = "";
		
		
		
							$validos = 0;
							$no_validos = 0;
							$mayusculas = 0;
							$minusculas = 0;
							$especiales = 0;
							$numeros = 0;
							$caracterespermitidos = array("a","b","c","d","e","f","g","h","i","j","k","l","m","n","ñ","o","p","q","r","s","t","u","v","w","x","y","z","A","B","C","D","E","F","G","H","I","J","K","L","M","N","Ñ","O","P","Q","R","S","T","U","V","W","X","Y","Z","1","2","3","4","5","6","7","8","9","0","@","#","$","%");
							$str_mayusculas = array("A","B","C","D","E","F","G","H","I","J","K","L","M","N","Ñ","O","P","Q","R","S","T","U","V","W","X","Y","Z");
							$str_minusculas = array("a","b","c","d","e","f","g","h","i","j","k","l","m","n","ñ","o","p","q","r","s","t","u","v","w","x","y","z");
							$str_numeros = array("1","2","3","4","5","6","7","8","9","0");
							$str_especiales = array("@","#","$","%");
							

							
							for($i=0; $i< strlen($clave); $i++){
								
									if (in_array($clave[$i], $caracterespermitidos,true)){
									
										if(in_array($clave[$i], $str_mayusculas,true)){
										   $mayusculas++; 
										}elseif(in_array($clave[$i], $str_minusculas,true)){
										   $minusculas++; 
										}elseif(in_array($clave[$i], $str_numeros,true)){
										   $numeros++; 
										}elseif(in_array($clave[$i], $str_especiales,true)){
										   $especiales++; 
										}                 
										
										 $validos++; 
									 
									}else{
									 $no_validos++;  
										
										
									}
							}
							
							
							if((strlen($clave))< 8){
								$html .='	<li id="length" class="invalid">Longitud de almenos <strong>8 caracteres</strong></li>';
							}elseif((strlen($clave))>= 8){
								$html .='	<li id="length" class="valid">Longitud de almenos <strong>8 caracteres</strong></li>';
							}
							
							if($mayusculas < 1){
								$html .='	<li id="capital" class="invalid">Al menos <strong>una letra mayúscula</strong></li>';
							}elseif($mayusculas >= 1){
								$html .='	<li id="capital" class="valid">Al menos <strong>una letra mayúscula</strong></li>';
							}
							
							
							if($minusculas < 1){
								$html .='	<li id="letter" class="invalid">Al menos <strong>una letra minúscula</strong></li>';
							}elseif($minusculas >= 1){
								$html .='	<li id="letter" class="valid">Al menos <strong>una letra minúscula</strong></li>';
							}
							
							if($numeros < 1){
								$html .='	<li id="number" class="invalid">Al menos <strong>un número</strong></li>';
							}elseif($numeros >= 1){
								$html .='	<li id="number" class="valid">Al menos <strong>un número</strong></li>';
							}
							
							if($especiales < 1 && $no_validos >= 1){
								$html .='	<li id="especial" class="invalid">Uso de caracteres especiales<strong> @ # $ %</strong></li>';
							}elseif($especiales >= 1 && $no_validos < 1){
								$html .='	<li id="especial" class="valid">Uso de caracteres especiales<strong> @ # $ %</strong></li>';
							}else{
								$html .='	<li id="especial" class="invalid">Uso de caracteres especiales<strong> @ # $ %</strong></li>';
							}
							
		

		echo $html;
		
	}

}//end if principal
?>
