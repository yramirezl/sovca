<?php
require_once '../models/Vmdetacrlstemp.php';
session_start();
$mfundos_id = $_SESSION[ 'mfundos_id' ];
$usuario_id = $_SESSION[ 'idusuariox' ];
$id_lote = $_GET[ 'id_lote' ];


$result = array();

    $data = Vmdetacrlstemp::find_by_sql( "SELECT id_control,nombre_producto,dosis
                                              FROM vmdetacrlstemps 
										      WHERE
                                               mcontrolsanitarioxlotes_id_temp=" . $usuario_id . " and mfundos_id=" . $mfundos_id . "
                                                and mlote_id=" . $id_lote . "                        
										           order by mcontrolsanitarioxlotes_id_temp desc ; " );

    foreach ( $data as &$rs ) {

        array_push( $result, array(

            'id' => $rs->id_control,
            'nombre_producto' => $rs->nombre_producto,
            'dosis' => $rs->dosis

        ) );

    }


print_r( json_encode( $result ) );