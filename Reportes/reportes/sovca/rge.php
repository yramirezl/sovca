<?php
require_once( 'pdfgruposetarios.php' );
require_once( '../../lib/reporte_contexto_helper.php' );
$obj = new pdfreporte();
$reporte = '';
$mfundos_id = 0;
session_start();
$mfundos_id = $_SESSION[ 'mfundos_id' ];

/* Nomenclatura de salida: reporte + contexto + fecha de generado */
$ctx = bovs_reporte_contexto( $mfundos_id );
$obj->contexto_detalle = $ctx[ 'detalle' ];
$obj->SetTitle( bovs_reporte_titulo( 'REPORTE GENERAL POR GRUPOS ETARIOS', $ctx ) );
$name = bovs_reporte_nombre_archivo( 'Grupos_Etarios', $ctx );

if ( $mfundos_id>0 ) {

    if ( $obj->arrp ) {
        $obj->AliasNbPages();
        $obj->AddPage();
        $obj->Cuerpo();
        $obj->Output($name);

    } else {
        $sin_datos_texto = 'No existen grupos etarios para mostrar con los filtros actuales.';
        include '_sin_datos.php';
    }

} else {
    include '_sesion_caducada.php';
}
?>
