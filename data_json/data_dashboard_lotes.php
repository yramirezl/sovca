<?php
require_once '../models/Manimale.php';

session_start();
$mfundos_id = isset($_SESSION['mfundos_id']) ? intval($_SESSION['mfundos_id']) : 0;

$result = array();

$data = Manimale::find_by_sql("
    SELECT COALESCE(l.nombre, 'Sin lote') AS nombre,
           COUNT(a.id) AS total
    FROM manimales a
    LEFT JOIN mlotes l
        ON l.id = a.mlotes_id
       AND l.mfundos_id = a.mfundos_id
    WHERE a.mfundos_id = " . $mfundos_id . "
      AND a.status = 1
    GROUP BY COALESCE(l.nombre, 'Sin lote')
    ORDER BY total DESC
    LIMIT 20;
");

if (is_array($data) && count($data) > 0) {
    foreach ($data as &$rs) {
        array_push($result, array(
            "nombre" => $rs->nombre,
            "total"  => intval($rs->total)
        ));
    }
} else {
    array_push($result, array("nombre" => "Sin datos", "total" => 0));
}

echo json_encode($result);
?>