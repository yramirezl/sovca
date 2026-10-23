<?php
if (!isset($_POST)) die ("No Puede Acceder Directamente");

class seguridad{
	
	private $oculta;
	//---------------------------------------------------------------------------------------------------------------------------
	//---------------------------------------------------------------------------------------------------------------------------
	function set_oculta(){
		$this->oculta = $this->generarOculta();
	}
	//---------------------------------------------------------------------------------------------------------------------------
	//---------------------------------------------------------------------------------------------------------------------------
	function get_oculta(){
		return $this->oculta;
	}
	//---------------------------------------------------------------------------------------------------------------------------
	//---------------------------------------------------------------------------------------------------------------------------
	//Para generar la clave a ser guardada en el campo hash
	function generarOculta(){
		srand((double)microtime()*100000);
		$aux  =  rand(0,getrandmax());
		$aux .=  md5(serialize($GLOBALS));
		$aux .=  md5(date("Ymdi"));
		$aux  =  md5($aux);
		return $aux;
	}
	//Para generar la clave a ser guardada en el campo clave
	function generarClave($clave,$hash){	
		$aux  = $hash;
		$aux .= md5($this->validar_texto($clave));
		$aux .= md5($aux);
		$aux = md5($aux);
		return $aux;
	}
	//---------------------------------------------------------------------------------------------------------------------------
	//---------------------------------------------------------------------------------------------------------------------------
	function validar_texto($string){

		return trim($this->filter($this->elimina_slash(trim($this->unhtmlentities($string)))));
	}// fin de la funcion--
	//---------------------------------------------------------------------------------------------------------------------------
	//---------------------------------------------------------------------------------------------------------------------------
	function unhtmlentities ($string)  {

		return $sinsalto = str_replace("[\n|\r|\n\r]",' ', $string);

	}// fin de la funcion
	//---------------------------------------------------------------------------------------------------------------------------
	//---------------------------------------------------------------------------------------------------------------------------
	//---------------------------------------------------------------------------------------------------------------------------
	//---------------------------------------------------------------------------------------------------------------------------
	function filter($cadena) { // quitar valores irregulares y reemplazarlos por nada
		$buscar = array(">", "<", "|", ";", "-","'","&","$");
		$cadena = str_replace($buscar, "", $cadena);
		return $cadena;
	}
	//---------------------------------------------------------------------------------------------------------------------------
	//---------------------------------------------------------------------------------------------------------------------------
	function elimina_slash($valor) {  // limpiador
		if (get_magic_quotes_gpc()) {
			$valor= stripslashes($valor); 
		}
	 	return $valor;
	} 
	//---------------------------------------------------------------------------------------------------------------------------
	//---------------------------------------------------------------------------------------------------------------------------
	function cambiaAcentos($str)
	{
	  $str = htmlentities($str);
	  $str = preg_replace('/&([a-zA-Z])(uml|acute|grave|circ|tilde);/','$1',$str);
	  return html_entity_decode($str);
	}

//---------------------------------------------------------------------------------------------------------------------------
//---------------------------------------------------------------------------------------------------------------------------
function  codigo_seg(){
 
		$salt= 'abcdefghijklmnopqrstuvwxyz';    
		$salt.= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';  
		$salt.= '0123456789';  
		$i = 0; 
		$str = '';  
		srand((double)microtime()*1000000);     
		while ($i < 8) {     
			$num = rand(0, strlen($salt)-1);        
			$str .= substr($salt, $num, 1);     
			$i++;   
		} 
		 
		return $str;
}
//---------------------------------------------------------------------------------------------------------------------------
//---------------------------------------------------------------------------------------------------------------------------
function clave_aleatoria(){
 
		$salt= 'abcdefghijklmnopqrstuvwxyz';    
		$salt.= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';  
		$salt.= '@#%+(){}?!';
		$salt.= '0123456789';  
		$i = 0; 
		$str = '';  
		srand((double)microtime()*1000000);     
		while ($i < 8) {     
			$num = rand(0, strlen($salt)-1);        
			$str .= substr($salt, $num, 1);     
			$i++;   
		} 
		 
		return $str;
}
//---------------------------------------------------------------------------------------------------------------------------
//---------------------------------------------------------------------------------------------------------------------------
/// FUNCION QUE VALIDA QUE LAS CALVES ESTEN FUERTES
	function validar_clave($clave){
	
		   $error_clave = "";
		   
		   if(strlen($clave) < 8){
			  $error_clave = "Al menos 8 caracteres";
			  return $error_clave;
		   }
		   if(strlen($clave) > 16){
			  	$error_clave = "No más de 16 caracteres";
				return $error_clave;
		   }
		   if (!preg_match('`[a-z]`',$clave)){
			  	$error_clave = "Al menos una letra min&uacute;scula";
				return $error_clave;
		   }
		   if (!preg_match('`[A-Z]`',$clave)){
			  	$error_clave = "Al menos una letra may&uacute;scula";
			 	return $error_clave;
		   }
		   if (!preg_match('`[0-9]`',$clave)){
			  	$error_clave = "Al menos un caracter numérico";
			 	return $error_clave;
		   }
		   if (!preg_match('`[@#%+(){}?!]`',$clave)){
			 	$error_clave = "Al menos un caracter especial  @#%+(){}?!   ";
			 	return $error_clave;
		   }
		   
		   return $error_clave;
		   
	}
///---------------------------------------------------------------------------------------------------------------------------------------------------------------------
///---------------------------------------------------------------------------------------------------------------------------------------------------------------------
/// FUNCION QUE RETORNA LA DIRECCION IP REMOTA .
	function getip(){

		   if (getenv("HTTP_CLIENT_IP") && strcasecmp(getenv("HTTP_CLIENT_IP"),"unknown"))
				   $ip = getenv("HTTP_CLIENT_IP");
		  
		   else if (getenv("HTTP_X_FORWARDED_FOR ") && strcasecmp(getenv("HTTP_X_FORWARDED_FOR "), "unknown"))
				   $ip = getenv("HTTP_X_FORWARDED_FOR ");
		
		   else if (getenv("REMOTE_ADDR") && strcasecmp(getenv("REMOTE_ADDR"), "unknown"))
				   $ip = getenv("REMOTE_ADDR");
		
		   else if (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], "unknown"))
				   $ip = $_SERVER['REMOTE_ADDR'];
		  
		   else
				   $ip = "unknown";

		   return($ip);
		}/// FIN FUNCTION ------------------------------------------------
///---------------------------------------------------------------------------------------------------------------------------------------------------------------------
///---------------------------------------------------------------------------------------------------------------------------------------------------------------------
///---------------------------------------------------------------------------------------------------------------------------------------------------------------------
///---------------------------------------------------------------------------------------------------------------------------------------------------------------------
/// FUNCION QUE REGISTRA EN EL AUDITOR
	function insert_auditor(&$con,$tabla,$modulo,$operacion,$sql,$usuario){

		
		 $ip=$this->getip();
		 $pc=gethostbyaddr($_SERVER['REMOTE_ADDR']);
		 
		 $sql=str_replace("'","''",$sql);
		 
		 
	$sql="INSERT INTO seguridad.segauditor(
						idsistema, dtmfecha, strusuario, strippc, strnompc, strtabla, lngregistro, strmodulo, stroperacion, lngtransaccion, lngorden, strerror, lngafectada, lngnumrows, lngduracionms, strsql)
						VALUES ((select id from seguridad.segsistemas where strnombre='ReciboPago'), 
											now(), 
											'".$usuario."', 
											'".$ip."', 
											'".$pc."', 
											'".$tabla."', 
											0, 
											'".$modulo."', 
											'".$operacion."', 
											0,
											1, 
											'', 
											1, 
											1, 
											1, 
											'".$sql."');";
											
					$con -> SQL_QUERY($sql);			
		 
	}/// FIN FUNCTION ------------------------------------------------
///---------------------------------------------------------------------------------------------------------------------------------------------------------------------
///---------------------------------------------------------------------------------------------------------------------------------------------------------------------
	
}/// FIN DE LA CLASE -------------------------------
?>
