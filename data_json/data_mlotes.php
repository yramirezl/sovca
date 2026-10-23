<?php
require_once '../models/Vanimaleslote.php';
session_start();
$mfundos_id = $_SESSION['mfundos_id'];



$result = array();

if (($mfundos_id!='')&&($mfundos_id!=' ')){
@$data = Vanimaleslote::find_by_sql("select id,nombre,descripcion,cantidad_animales from Vanimaleslotes where mfundos_id=" . $mfundos_id . " order by nombre asc;");


$result = array();
foreach ($data as &$rs) {


	array_push($result, array(
		"id" => $rs->id,
		"nombre" => $rs->nombre,
		"descripcion" => $rs->descripcion,
		"cantidad" => $rs->cantidad_animales

	));
}
}

echo json_encode($result);


?>