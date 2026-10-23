<?php
require_once( 'pdfrlisanimales_servidas_act.php' );
$obj = new pdfreporte();
$reporte = '';
$mfundos_id = 0;
session_start();
$mfundos_id = $_SESSION[ 'mfundos_id' ];
$name='Hembras Servidas.pdf';

if ( $mfundos_id>0 ) {

    if ( $obj->arrp ) {
        $obj->AliasNbPages();
        $obj->AddPage();
        $obj->Cuerpo();
        $obj->Output($name);

    } else {
        ?><script language = 'JavaScript' type = 'text/javascript'>
        alert( 'No Existen Datos Para este Reporte' );
        window.close();
        </script><?php
    }

} else {
    include '_sesion_caducada.php';
}
?>
