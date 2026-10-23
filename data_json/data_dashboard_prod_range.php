<?php
require_once '../models/Mprolechera.php';

session_start();
$mfundos_id = isset($_SESSION['mfundos_id']) ? intval($_SESSION['mfundos_id']) : 0;

$desde = (!empty($_GET['desde'])) ? $_GET['desde'] : date('Y-m-01');
$hasta = (!empty($_GET['hasta'])) ? $_GET['hasta'] : date('Y-m-d');

$result = array();

$data = Mprolechera::find_by_sql("
    SELECT fecha_produccion, SUM(cantidad_leche_m + cantidad_leche_t) AS total_leche
    FROM mprolecheras
    WHERE mfundos_id = " . $mfundos_id . "
      AND status = 1
      AND fecha_produccion BETWEEN CAST('" . $desde . "' AS DATE) AND CAST('" . $hasta . "' AS DATE)
    GROUP BY fecha_produccion
    ORDER BY fecha_produccion ASC
    LIMIT 500;
");

if (is_array($data) && count($data) > 0) {
    foreach ($data as &$rs) {
        array_push($result, array(
            "total_leche"      => floatval($rs->total_leche),
            "fecha_produccion" => $rs->fecha_produccion->format("d-m-Y")
        ));
    }
} else {
    array_push($result, array("total_leche" => 0, "fecha_produccion" => "Sin datos"));
}

echo json_encode($result);
?>