<?php
/* ============================================================
   SOVCA | Estado de Nacimientos en el período
   ------------------------------------------------------------
   Agrupa los nacimientos por mes y estado:

     - fichados        : ya integrados al rebaño (fichado = true)
                         Independiente del estado actual porque
                         el fichaje ya ocurrió históricamente.
     - sin_fichar      : activos y pendientes de integrar
                         (fichado = false AND mestatus_id = 1)
     - desincorporados : dados de baja sin haber sido fichados
                         (fichado = false AND mestatus_id != 1)

   Mapeo mestatusganados:
     1 = ACTIVO
     2 = DESCARTE
     3 = MUERTO
     4 = VENDIDO
     5 = EXTRAVIADO
   ============================================================ */

require_once '../models/Mregnacimiento.php';

session_start();
$mfundos_id = isset($_SESSION['mfundos_id']) ? intval($_SESSION['mfundos_id']) : 0;

$desde = (!empty($_GET['desde'])) ? $_GET['desde'] : date('Y-01-01');
$hasta = (!empty($_GET['hasta'])) ? $_GET['hasta'] : date('Y-m-d');

$result = array();

$data = Mregnacimiento::find_by_sql("
    SELECT
        to_char(f_parto, 'YYYY-MM') AS mes,
        SUM(CASE WHEN fichado = true
                 THEN 1 ELSE 0 END) AS fichados,
        SUM(CASE WHEN fichado = false
                  AND mestatus_id = 1
                 THEN 1 ELSE 0 END) AS sin_fichar,
        SUM(CASE WHEN fichado = false
                  AND mestatus_id != 1
                 THEN 1 ELSE 0 END) AS desincorporados
    FROM mregnacimientos
    WHERE mfundos_id = " . $mfundos_id . "
      AND f_parto IS NOT NULL
      AND f_parto::date BETWEEN DATE '" . $desde . "' AND DATE '" . $hasta . "'
    GROUP BY to_char(f_parto, 'YYYY-MM')
    ORDER BY mes ASC;
");

if ($data != null && count($data) > 0) {
    foreach ($data as &$rs) {
        array_push($result, array(
            "mes"              => $rs->mes,
            "fichados"         => intval($rs->fichados),
            "sin_fichar"       => intval($rs->sin_fichar),
            "desincorporados"  => intval($rs->desincorporados)
        ));
    }
} else {
    array_push($result, array(
        "mes" => "Sin datos",
        "fichados" => 0,
        "sin_fichar" => 0,
        "desincorporados" => 0
    ));
}

echo json_encode($result);
?>