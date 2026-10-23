<?php
require_once '../models/Valerta.php';
session_start();
$mfundos_id = $_SESSION['mfundos_id'];



$result = array();

 @$data = Valerta::find_by_sql("select id,evento,codigo_animal,nombre_animal,
										case when vista=true then 'VISTA'
										else  'SIN VER' END AS estatus
										from Valertas  where mfundos_id=" . $mfundos_id . "
										 and activa=true ;");


$result = array();
foreach ($data as &$rs) {


	array_push($result, array(
		"id" => $rs->id,
		"descripcion" => $rs->evento,
		"codigo_animal" => $rs->codigo_animal,
		"nombre_animal" => $rs->nombre_animal,
		"estatus" => $rs->estatus




	));
}

echo json_encode($result);
