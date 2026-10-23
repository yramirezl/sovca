<?php

function getvalue($variable, $vdefault="", $fdefault=false)
{
    if ($_SERVER['REQUEST_METHOD'] == 'POST')
    {
        if(isset($_POST[$variable]))
        {
            if($fdefault) return !strlen($_POST[$variable]) ? $vdefault : $_POST[$variable];
            return $_POST[$variable];
        }
        else
        {

            return strlen($vdefault) ? $vdefault : false;
        }

    }
    if ($_SERVER['REQUEST_METHOD'] == 'GET'){
        if(isset($_GET[$variable]))
        {
            if($fdefault) return !strlen($_GET[$variable]) ? $vdefault : $_GET[$variable];
            return $_GET[$variable];

        }
        else
        {
            return strlen($vdefault) ? $vdefault : false;
        }
    }
}

function getVarSession($variable)
{
    return isset($_SESSION[$variable]) ? $_SESSION[$variable] : null;
}

function setVarSession($variable, $valor)
{
    $_SESSION[$variable] = $valor;
}


function ControlSession($ruta,$logueado){
   
        if($logueado==0)
        {
            if(!$ruta){
	header("Location: ../timeinfo.html");
            die();
        }else{

            header("Location: ".$ruta);	
            die();	
                    }
        }  
        
        
        
        return true;	 
        
        
        
 }

function ValidaSession($ruta){

session_start();
$session=getVarSession("ID_M_USUARIO");	


if(!$session){
	
	if(!$ruta){
	header("Location: ../timeinfo.html");
	die();
    }else{
		
	header("Location: ".$ruta);	
	die();	
		}
	
	}else if($session==""){
		
			if(!$ruta){
	        // header("Location: ../formularios/cerrar.php");
	         header("Location: ".$ruta);	
             die();
             }
	
		}
	
	
return true;	
}

?>