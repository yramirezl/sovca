<?php
require_once '../models/Vmproductosxlote.php';
session_start();
$mfundos_id = $_SESSION[ 'mfundos_id' ];

$result = array();
$id_cs_x_lote = $_GET[ 'id_cs_x_lote' ];
if (($mfundos_id!='')&&($mfundos_id!=' ')){
if ( $id_cs_x_lote>0 ) {
    $data = Vmproductosxlote::find_by_sql( "SELECT mproductos_id,nombre_producto,dosis
                                              FROM Vmproductosxlotes 
										      WHERE mcontrolsanitarioxlotes_id=" . $id_cs_x_lote . " and mfundos_id=" . $mfundos_id . "
										           order by mcontrolsanitarioxlotes_id desc ; " );

    foreach ( $data as &$rs ) {

        array_push( $result, array(

            'id' => $rs->mproductos_id,
            'nombre_producto' => $rs->nombre_producto,
            'dosis' => $rs->dosis

        ) );

    }

} else {
    array_push( $result, array(

        'id' => 0,
        'nombre_producto' => '',
        'dosis' =>'',
        'detalles' =>''
    ) );

}
}

print_r( json_encode( $result ) );