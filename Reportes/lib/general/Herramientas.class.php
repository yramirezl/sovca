<?php
require_once("../../lib/bd/basedatosAdoIbase.php");
require_once("ClaseMontos.class.php");
//@include_once("../../controladores/control_sesiones.php");



class Herramientas {

  function Herramientas() {
  }

  
  public static function GetPost($variable)
  { 
    if ($_POST[$variable]!=""){
      return trim($_POST[$variable]);
    }
    elseif($_GET[$variable]!="")
    {
      return trim($_GET[$variable]);
    }else return "";
  }



  public static function PrintR($obj)
  {
    print '<pre>';
    print_r($obj);
    print '</pre>';
  }
 public function FormatoNum($varmonto,$format='VE')
  {
  	if($format=='VE')
  	{
  		$auxvar=str_replace(".","",$varmonto);
		$auxvar=str_replace(",",".",$auxvar);
  	}else
  	{
		$auxvar=str_replace(",","",$varmonto);
		$auxvar=str_replace(".",",",$auxvar);
  	}

	return $auxvar;
  }




	public static function instr($palabra,$busqueda,$comienzo,$concurrencia)
	{
		
		$tamano=strlen($palabra);
		//echo "   ,   ";
		$cont=0;
		$cont_conc=0;

		for ($i=$comienzo;$i<=$tamano;$i++){
			$cont=$cont+1;
			if ($palabra[$i]==$busqueda):
				$cont_conc=$cont_conc+1;

				if ($cont_conc==$concurrencia):
					$i=$tamano;
				endif;
			endif;
		}
			if ($concurrencia > $cont_conc ):
				 $cont=0;
			else:
				 $cont;
			endif;

		return $instr=$cont;
	}

	 public static function isFloat($value)
  {
    $expresionfloat =  "/^(\d{1,3}\,)(\d{3,3}\,){1,10}(\.\d+)$/";
    $expresionfloat_1 =  "/^(\d{1,10})(\.\d+)$/";
    $expresionfloat_2 =  "/^(\d{1,3}\,){1,10}(\d{3,3})(\.\d+)$/";
    $expresionfloat_3 =  "/^(\d{1,20})$/";
    $expresionfloat_4 =  "/^(\d{1,20})(\.\d+)$/";

    if(preg_match($expresionfloat,$value) || preg_match($expresionfloat_1,$value) || preg_match($expresionfloat_2,$value) || preg_match($expresionfloat_3,$value) || preg_match($expresionfloat_4,$value) ) return true;
    else return false;
  }

  public static function isFloatVE($value)
  {
    $expresionfloatVE =  "/^(\d{1,3}\.)(\d{3,3}\.){1,10}(\,\d+)$/";
    $expresionfloatVE_1 =  "/^(\d{1,10})(\.\d+)$/";
    $expresionfloatVE_2 =  "/^(\d{1,3}\.){1,10}(\d{3,3})(\,\d+)$/";
    $expresionfloatVE_3 =  "/^(\d{1,20})$/";
    $expresionfloatVE_4 =  "/^(\d{1,20})(\,\d+)$/";

    if(preg_match($expresionfloatVE,$value) || preg_match($expresionfloatVE_1,$value) || preg_match($expresionfloatVE_2,$value) || preg_match($expresionfloatVE_3,$value) || preg_match($expresionfloatVE_4,$value) ) return true;
    else return false;

  }

  public static function FloatVEtoFloat($value){
    try{
      $sinpuntos = str_replace('.','',$value);
      $valor_en_float = (float)str_replace(',','.',$sinpuntos);
      if(is_nan($valor_en_float))
          return 0.00;
      else return $valor_en_float;
    }catch(Exception $e){return 0.00;}
  }

  public static function toFloat($value)
  {
    $valorfloat = 0.0;
    if ( ($value==" ") || ($value=="") || ($value=="NaN"))
    {
      $valorfloat=0.00;
    }else{
      if(Herramientas::isFloat($value) || is_float($value)){
        $value = str_replace(',','',$value);
        $valorfloat = (float)$value;
      }else{

        if(Herramientas::isFloatVE($value))
          $valorfloat = Herramientas::FloatVEtoFloat($value);
        else
          $valorfloat = 0.00;
      }
    }
    return round($valorfloat,2);
  }

  public static function FormatoMonto($value,$dec='2')
  {

  		$for='VE';
	  	if ($value==' ')
	  		$value=0;
	  	if ($for=='VE')
	  		$valor = number_format($value,$dec,',','.');
	  	elseif ($for=='IN')
	  	   	$valor = number_format($value,$dec,'.',',');
	  	else
	  	    $valor = number_format($value,0);

  	return $valor;
  }

  public static function ObtenerMesenLetras($mes)
  {
  			if($mes=='01')  return $mes='Enero';
			if($mes=='02')  return $mes='Febrero';
			if($mes=='03')  return $mes='Marzo';
			if($mes=='04')  return $mes='Abril';
			if($mes=='05')  return $mes='Mayo';
			if($mes=='06')	return $mes='Junio';
			if($mes=='07')  return $mes='Julio';
			if($mes=='08')	return $mes='Agosto';
			if($mes=='09')  return $mes='Septiembre';
			if($mes=='10')	return $mes='Octubre';
			if($mes=='11')  return $mes='Noviembre';
			if($mes=='12')  return $mes='Diciembre';
  }
	public static function QuitaAcento($cadena)
	{
		$sinacento = str_replace(array('á','é','í','ó','ú','Á','É','Í','Ó','Ú'),array('a','e','i','o','u','A','E','I','O','U'),$cadena);
		return $sinacento;
	}

	//quita los acentos y las eñes de una cadena
	public static function QuitaAcentoyEnie($cadena)
	{
		$sinacento = str_replace(array('á','é','í','ó','ú','Á','É','Í','Ó','Ú','Ñ','ñ'),array('a','e','i','o','u','A','E','I','O','U','N','n'),$cadena);
		return $sinacento;
	}

}




class H extends Herramientas
{

}


?>
