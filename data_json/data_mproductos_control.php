<?php
require_once '../models/Vmdetacrlsanitario.php';
session_start();
$mfundos_id = $_SESSION[ 'mfundos_id' ];

$result = array();
$id_control = $_GET[ 'id_control' ];
if (($mfundos_id!='')&&($mfundos_id!=' ')){
if ( $id_control>0 ) {
    $data = Vmdetacrlsanitario::find_by_sql( "SELECT id_deta_control,nombre_producto,dosis
                                              FROM Vmdetacrlsanitarios 
										      WHERE id_m_control=" . $id_control . ' and mfundos_id=' . $mfundos_id . "
										           order by id_deta_control desc ; " );

    foreach ( $data as &$rs ) {

        array_push( $result, array(

            'id' => $rs->id_deta_control,
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