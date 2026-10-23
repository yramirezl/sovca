<?php 

include("../../lib/validar_session.php");
ValidaSession("../login");
if(!isset($_SESSION)) 
    { 
        session_start(); 
    } 
///session_start();
$_SESSION['instante']   = time();

//VerificarAdmin($_SESSION['rolx']);

?>


<?php include("../layouts/constantes.php") ?>
<?php include_once("../layouts/cabecera.php") ?>  
<?php include_once("blank.php") ?>
<?php include_once("../layouts/pie.php") ?>  
   
