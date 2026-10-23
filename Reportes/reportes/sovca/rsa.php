<?php
require_once( 'pdfservicio_animal.php' );
$obj = new pdfreporte();
$reporte = '';
$mfundos_id = 0;
session_start();
$mfundos_id = $_SESSION[ 'mfundos_id' ];
$name='Servicio de Animale.pdf';

if ( $mfundos_id>0 ) {

    if ( $obj->arrp ) {
        $obj->AliasNbPages();
        $obj->AddPage();
        $obj->Cuerpo();
        $obj->Output($name);

    } else {
        $sin_datos_texto = 'No existen registros de servicios para los filtros seleccionados.';
        include '_sin_datos.php';
    }

} else {
    include '_sesion_caducada.php';
}
?>
