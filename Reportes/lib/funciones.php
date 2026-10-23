<?php

function clave_aleatoria($long){
 
    $salt = 'abchefghknpqrstuvwxyz';    
    $salt .= 'ACDEFHKNPRSTUVWXYZ';  
    $salt .= '@#&+-/(){}.:';
    $salt .= strlen($salt)?'2345679':'0123456789';  
    
    if (strlen($salt) == 0) {       
        return '';  
    }   
    $i = 0; 
    $str = '';  
    srand((double)microtime()*1000000);     
    while ($i < $long) {     
        $num = rand(0, strlen($salt)-1);        
        $str .= substr($salt, $num, 1);     
        $i++;   
    } 
     
    return $str;
}

function getRealIP()
{ 
    if((isset($_SERVER['HTTP_X_FORWARDED_FOR'])) && ($_SERVER['HTTP_X_FORWARDED_FOR'] != '' )) 

   //if( $_SERVER['HTTP_X_FORWARDED_FOR'] != '' )
   {
      if (!empty($_SERVER['REMOTE_ADDR']))
      {
        if (!empty($_ENV['REMOTE_ADDR']))  
        { $client_ip = $_ENV['REMOTE_ADDR'];}
        else
        { $client_ip = "unknown";}
      }

      $entries = preg_split('/[, ]/', $_SERVER['HTTP_X_FORWARDED_FOR']);
      reset($entries);
      while (list(, $entry) = each($entries))
      {
         $entry = trim($entry);
         if ( preg_match("/^([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)/", $entry, $ip_list) )
         {
            // http://www.faqs.org/rfcs/rfc1918.html
            $private_ip = array(
                  '/^0\./',
                  '/^127\.0\.0\.1/',
                  '/^192\.168\..*/',
                  '/^172\.((1[6-9])|(2[0-9])|(3[0-1]))\..*/',
                  '/^10\..*/');
 
            $found_ip = preg_replace($private_ip, $client_ip, $ip_list[1]);
 
            if ($client_ip != $found_ip)
            {
               $client_ip = $found_ip;
               break;
            }
         }
      }
   }
   else
   {
      $client_ip =
         ( !empty($_SERVER['REMOTE_ADDR']) ) ?
            $_SERVER['REMOTE_ADDR']
            :
            ( ( !empty($_ENV['REMOTE_ADDR']) ) ?
               $_ENV['REMOTE_ADDR']
               :
               "unknown" );
   }
 
   return $client_ip;
 
}

function calculaedad( $fecha ) {
        if (strstr($fecha,"/")) {
                $array_fec=  explode('/', $fecha);
                $y=$array_fec[2];
                $m=$array_fec[1];
                $d=$array_fec[0];
                $fecha=$y.'-'.$m.'-'.$d;
            }else {
               $fecha=$fecha; 
            }
            
            
    list($Y,$m,$d) = explode("-",$fecha);
    date_default_timezone_set('America/Caracas');
    return( date("md") < $m.$d ? date("Y")-$Y-1 : date("Y")-$Y );
}

//function calculaedad_beneficiario( $fecha ) {
//    list($d,$m,$Y) = explode("/",$fecha);
//    date_default_timezone_set('America/Caracas');
//    return( date("md") < $m.$d ? date("Y")-$Y-1 : date("Y")-$Y );
//}

function calculaedad_beneficiario($fecha ) {
     if (strstr($fecha,"/")) {
                $array_fec=  explode('/', $fecha);
                $y=$array_fec[2];
                $m=$array_fec[1];
                $d=$array_fec[0];
                $fecha=$y.'-'.$m.'-'.$d;
            }else {
               $fecha=$fecha; 
            }
    
    list($Y,$m,$d) = explode("-",$fecha);
    date_default_timezone_set('America/Caracas');
    return( date("md") < $m.$d ? date("Y")-$Y-1 : date("Y")-$Y );
}



function calculafecha($fecha){
    if (strstr($fecha,"/")) {
                $array_fec=  explode('/', $fecha);
                $y=$array_fec[2];
                $m=$array_fec[1];
                $d=$array_fec[0];
                $fecha=$y.'-'.$m.'-'.$d;
            }else {
               $fecha=$fecha; 
            }
    
    $caduca=0;
    list($ano,$mes,$dia) = explode("-",$fecha);
    date_default_timezone_set('America/Caracas');
   
$timestamp1 = mktime(0,0,0,date("m"),date("d"),date("Y")); 
$timestamp2 = mktime(4,12,0,$mes,$dia,$ano); 

 if($timestamp1>$timestamp2){
     $caduca=1;
 }else{
    $caduca=0;
 }

return $caduca;

} 

function calculafecha_carta_aval($fecha_vencimiento){
    $caduca=false;
    list($dia,$mes,$ano) = explode('/',$fecha_vencimiento);
    date_default_timezone_set('America/Caracas');
   $timestamp1 = mktime(0,0,0,date("m"),date("d"),date("Y")); 
   $timestamp2 = mktime(4,12,0,$mes,$dia,$ano); 

 if($timestamp1>$timestamp2){
     $caduca=true;
 }else{
    $caduca=false;
 }

return $caduca;

} 

function borrar_cache(){
 session_start(); 
session_unset();   
session_destroy();   
unset($_COOKIE['AoIuser']);  
unset($_COOKIE['userPass']);
clearstatcache();  
    
}
function ip_servidor(){
    return $_SERVER['SERVER_ADDR'];
}

function Limpiar_Caracteres($valor){
    $valor=str_replace( 
            
            // 'º', ' º ',
        array ( 
                ' # ',
                '/',
                '\ ' ,

              ),'',$valor);
    return $valor;
}
function noCache() {
header("Expires: Tue, 01 Jul 2016 06:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
}


function dateDiff($start) {
date_default_timezone_set('America/Caracas');
$end = date("Y-m-d"); 
$start_ts = strtotime($start);

$end_ts = strtotime($end);

$diff = $end_ts - $start_ts;

return round($diff / 3600);
}


function dias_transcurridos($fecha_i,$fecha_f)
{
$dias = (strtotime($fecha_i)-strtotime($fecha_f))/86400;
$dias = abs($dias); $dias = floor($dias);
return $dias;
}


?>
