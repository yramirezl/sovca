<?php
require_once '../models/Manimale.php';

session_start();
$mfundos_id = isset($_SESSION['mfundos_id']) ? intval($_SESSION['mfundos_id']) : 0;

$desde = (!empty($_GET['desde'])) ? $_GET['desde'] : date('Y-m-d', strtotime('-12 months'));
$hasta = (!empty($_GET['hasta'])) ? $_GET['hasta'] : date('Y-m-d');

$meses = array();

/* Servicios por mes */
$servicios = Manimale::find_by_sql("
    SELECT to_char(f_servicio, 'YYYY-MM') AS mes, COUNT(*) AS total
    FROM mregservicios
    WHERE mfundos_id = " . $mfundos_id . "
      AND status = 1
      AND f_servicio BETWEEN CAST('" . $desde . "' AS DATE) AND CAST('" . $hasta . "' AS DATE)
    GROUP BY to_char(f_servicio, 'YYYY-MM')
    ORDER BY mes ASC;
");

/* Nacimientos por mes */
$nacimientos = Manimale::find_by_sql("
    SELECT to_char(f_parto, 'YYYY-MM') AS mes, COUNT(*) AS total
    FROM mregnacimientos
    WHERE mfundos_id = " . $mfundos_id . "
      AND status = 1
      AND f_parto BETWEEN CAST('" . $desde . "' AS DATE) AND CAST('" . $hasta . "' AS DATE)
    GROUP BY to_char(f_parto, 'YYYY-MM')
    ORDER BY mes ASC;
");

/* Controles sanitarios por mes */
$controles = Manimale::find_by_sql("
    SELECT to_char(fecha_control, 'YYYY-MM') AS mes, COUNT(*) AS total
    FROM mcontrolsanitarios
    WHERE mfundos_id = " . $mfundos_id . "
      AND status = 1
      AND fecha_control BETWEEN CAST('" . $desde . "' AS DATE) AND CAST('" . $hasta . "' AS DATE)
    GROUP BY to_char(fecha_control, 'YYYY-MM')
    ORDER BY mes ASC;
");

foreach (array('servicios' => $servicios, 'nacimientos' => $nacimientos, 'controles' => $controles) as $clave => $dataset) {
    if ($dataset == null) continue;
    foreach ($dataset as $row) {
        $m = $row->mes;
        if (!isset($meses[$m])) {
            $meses[$m] = array('mes' => $m, 'servicios' => 0, 'nacimientos' => 0, 'controles' => 0);
        }
        $meses[$m][$clave] = intval($row->total);
    }
}

ksort($meses);
$result = array_values($meses);

if (count($result) === 0) {
    array_push($result, array('mes' => 'Sin datos', 'servicios' => 0, 'nacimientos' => 0, 'controles' => 0));
}

echo json_encode($result);
?>