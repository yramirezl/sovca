<?php
require_once '../models/Madjuntaexcel.php';

$unid=$_GET["este"];
	$result = array();

	session_start();
	$id_sesion_usuario = $_SESSION['instante'];
	
	if($id_sesion_usuario==null){
		$id_sesion_usuario=1;
	}	
	$data = Madjuntaexcel::find_by_sql("select id,adjunto from madjuntaexcels WHERE mrequerimientos_id = ".$unid." order by id desc;");
	$items = 0;

	foreach ($data as &$rs) {

		
		array_push($result,array(			
											 "id"=>$rs->id,
											 "adjunto"=>$rs->adjunto
		                         ));

		$items++;

	}

//echo($data);exit();
	print_r(json_encode($result));
?>