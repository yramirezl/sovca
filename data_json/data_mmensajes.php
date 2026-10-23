<?php
require_once '../models/Mmensaje.php';
session_start();
$mfundos_id = $_SESSION[ 'mfundos_id' ];

$result = array();

@$data = Mmensaje::find_by_sql( "select id,titulo,
                                          case when mtipoganaderias_id=1  
										        then 'OVINA'
												when mtipoganaderias_id=2  
										        then 'CAPRINA'
												else 'Todas' end ganaderia,
										   case when activo=1
										         then 'Activo'
												 else 'Caducado' end estatus_mensaje
									
										from Mmensajes 
										 where  status=1 ;" );

$result = array();
foreach ( $data as &$rs ) {

    array_push( $result, array(
        'id' => $rs->id,
        'titulo_mensaje' => $rs->titulo,
        'ganaderia' => $rs->ganaderia,
        'estatus_mensaje' => $rs->estatus_mensaje
    ) );
}

echo json_encode( $result );