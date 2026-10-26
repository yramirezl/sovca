<?php
require_once( 'pdfrlisanimales_activos_x_lotes.php' );
require_once( '../../lib/reporte_contexto_helper.php' );
$obj = new pdfreporte();
$reporte = '';
$mfundos_id = 0;
session_start();
$mfundos_id = $_SESSION[ 'mfundos_id' ];

/* Nomenclatura de salida: reporte + contexto + fecha de generado */
$ctx = bovs_reporte_contexto( $mfundos_id );
$obj->contexto_detalle = $ctx[ 'detalle' ];
$obj->SetTitle( bovs_reporte_titulo( 'REPORTE DE ANIMALES ACTIVOS POR LOTES', $ctx ) );
$name = bovs_reporte_nombre_archivo( 'Animales_Activos_por_Lotes', $ctx );

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
