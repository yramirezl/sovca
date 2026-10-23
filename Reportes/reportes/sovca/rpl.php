<?php
	require_once("pdfprodlechera.php");
	$obj= new pdfreporte();
	$reporte='';
    $mfundos_id=0;
    session_start();
    $mfundos_id = $_SESSION['mfundos_id'];
    $name='Produccion de Leche.pdf';

    if($mfundos_id>0){

        if ($obj->arrp){
            $obj->AliasNbPages();
            $obj->AddPage();
            $obj->Cuerpo();
            $obj->Output($name);

	}else{
            $sin_datos_texto = 'No existen registros de producción de leche para los filtros seleccionados.';
            include '_sin_datos.php';
	}   
        
}else {
    include '_sesion_caducada.php';
}
?>
