<?php
	require_once("pdfprodlechera.php");
	require_once('../../lib/reporte_contexto_helper.php');
	$obj= new pdfreporte();
	$reporte='';
    $mfundos_id=0;
    session_start();
    $mfundos_id = $_SESSION['mfundos_id'];

    /* Nomenclatura de salida: reporte + animal/lote/rango + fecha de generado */
    $ctx = bovs_reporte_contexto($mfundos_id);
    $obj->contexto_detalle = $ctx['detalle'];
    $obj->SetTitle(bovs_reporte_titulo('REPORTE DE PRODUCCIÓN DE LECHE', $ctx));
    $name = bovs_reporte_nombre_archivo('Produccion_de_Leche', $ctx);

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
