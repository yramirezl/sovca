<?php
require_once '../models/Mpromomensaje.php';
session_start();
$mfundos_id = $_SESSION[ 'mfundos_id' ];

$result = array();

@$data = Mpromomensaje::find_by_sql( "select id,titulo,
                                          case when enviado=true  
										        then 'ENVIADO'
												
												else 'SIN ENVIAR' end condicion
									
										from Mpromomensajes 
										 where  status=1 ;" );

$result = array();
foreach ( $data as &$rs ) {

    array_push( $result, array(
        'id' => $rs->id,
        'titulo_mensaje' => $rs->titulo,
        'condicion' => $rs->condicion
    ) );
}

echo json_encode( $result );