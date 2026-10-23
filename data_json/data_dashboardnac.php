<?php
require_once '../models/Mregnacimiento.php';

session_start();
$mfundos_id = isset($_SESSION['mfundos_id']) ? intval($_SESSION['mfundos_id']) : 0;

$result = array();

$data = Mregnacimiento::find_by_sql("
    SELECT COUNT(*) AS cantidad, sexo
    FROM mregnacimientos
    WHERE mfundos_id = " . $mfundos_id . "
      AND status = 1
    GROUP BY sexo;
");

if (is_array($data) && count($data) > 0) {
    foreach ($data as &$rs) {
        $sexo_valor = intval($rs->sexo);
        $sexo_txt = ($sexo_valor === 1) ? 'HEMBRA' : (($sexo_valor === 2) ? 'MACHO' : 'INDEFINIDO');
        array_push($result, array("cantidad" => intval($rs->cantidad), "sexo" => $sexo_txt));
    }
} else {
    array_push($result, array("cantidad" => 0, "sexo" => "Sin datos"));
}

echo json_encode($result);
?>