<?php
require_once '../models/Mprolechera.php';
// require_once '../models/Vdesincorporacione.php';
include '../lib/sanitizar.php';


session_start();
$mfundos_id = $_SESSION['mfundos_id'];
$cargar_data = $_GET["cargar_grafica"];

$cod_animal = $_GET["cod_rgb_animal"];

$result = array();
if (($mfundos_id!='')&&($mfundos_id!=' ')){
$rgb_animal = $cod_animal;
$partes = explode("-", $rgb_animal);
$prefijo = $partes[0];
$nro = 0;
if ($prefijo == 'SOVCA') {
	$nro = intval($partes[2]);
} else {
	$nro = intval($rgb_animal);
}

$cod_rgb_animal = 'SOVCA-0' . $mfundos_id . '-0' . $nro;

$cont = 1;

if ($cod_animal != null) {

	$data = Mprolechera::find_by_sql("SELECT fecha_produccion, sum(cantidad_leche_m+cantidad_leche_t) as total_leche
											FROM mprolecheras where mfundos_id=" . $mfundos_id . " and 
											codigo_rgb_id_animal_hembra='$cod_rgb_animal' and
											status=1 and date_part('day', age(date(fecha_produccion)))<= 30
											group by fecha_produccion order by fecha_produccion asc;");



	if ($data != null) {

		foreach ($data as &$rs) {

			array_push($result, array(
				"total_leche" => $rs->total_leche,
				"fecha_produccion" => $rs->fecha_produccion->format("d-m-Y")
			));
			$cont = $cont + 1;
		}
	} else {
		$nom = "";
		$val = 0;

		array_push($result, array(
			"total_leche" => $val,
			"fecha_produccion" => $nom
		));
	}
} else {
	$nom = "";
	$val = 0;

	array_push($result, array(
		"total_leche" => $val,
		"fecha_produccion" => $nom
	));
}
}
echo json_encode($result);
?>