<?php
require_once '../models/Mcontacto.php';
session_start();
$mfundos_id = $_SESSION[ 'mfundos_id' ];

$result = array();

@$data = Mcontacto::find_by_sql( "select id,name, email,telefono
									
										from Mcontactos 
										 where  status=1 ;" );

$result = array();
foreach ( $data as &$rs ) {

    array_push( $result, array(
        'id' => $rs->id,
        'name' => $rs->name,
        'correo' => $rs->email,
        'telefono' => $rs->telefono
    ) );
}

echo json_encode( $result );