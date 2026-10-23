<?php
require_once '../models/Mconsecutivopeso.php';

session_start();
$mfundos_id = isset($_SESSION['mfundos_id']) ? intval($_SESSION['mfundos_id']) : 0;

$desde = (!empty($_GET['desde'])) ? $_GET['desde'] : date('Y-m-d', strtotime('-60 days'));
$hasta = (!empty($_GET['hasta'])) ? $_GET['hasta'] : date('Y-m-d');

$result = array();

$data = Mconsecutivopeso::find_by_sql("
    SELECT fecha_pesaje, SUM(peso) AS total_peso
    FROM mconsecutivopesos
    WHERE mfundos_id = " . $mfundos_id . "
      AND status = 1
      AND fecha_pesaje BETWEEN CAST('" . $desde . "' AS DATE) AND CAST('" . $hasta . "' AS DATE)
    GROUP BY fecha_pesaje
    ORDER BY fecha_pesaje ASC
    LIMIT 500;
");

if (is_array($data) && count($data) > 0) {
    foreach ($data as &$rs) {
        array_push($result, array(
            "total_peso"   => floatval($rs->total_peso),
            "fecha_pesaje" => $rs->fecha_pesaje->format("d-m-Y")
        ));
    }
} else {
    array_push($result, array("total_peso" => 0, "fecha_pesaje" => "Sin datos"));
}

echo json_encode($result);
?>