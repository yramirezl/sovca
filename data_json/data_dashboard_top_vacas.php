<?php
/* Anti-cache: fuerza al navegador a pedir datos frescos siempre */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once '../models/Mprolechera.php';

session_start();
$mfundos_id = isset($_SESSION['mfundos_id']) ? intval($_SESSION['mfundos_id']) : 0;

$desde = (!empty($_GET['desde'])) ? $_GET['desde'] : date('Y-m-01');
$hasta = (!empty($_GET['hasta'])) ? $_GET['hasta'] : date('Y-m-d');

$result = array();

$data = Mprolechera::find_by_sql("
    SELECT
        NULLIF(TRIM(m.nombre), '') AS nombre,
        NULLIF(TRIM(m.nro_arete), '') AS arete,
        m.codigo_rgb_id_animal AS codigo,
        SUM(p.cantidad_leche_m + p.cantidad_leche_t) AS total
    FROM mprolecheras p
    INNER JOIN manimales m
        ON m.codigo_rgb_id_animal = p.codigo_rgb_id_animal_hembra
       AND m.mfundos_id = p.mfundos_id
       AND m.status = 1
    WHERE p.mfundos_id = " . $mfundos_id . "
      AND p.status = 1
      AND p.fecha_produccion::date BETWEEN DATE '" . $desde . "' AND DATE '" . $hasta . "'
    GROUP BY m.nombre, m.nro_arete, m.codigo_rgb_id_animal
    ORDER BY total DESC
    LIMIT 10;
");

if ($data != null && count($data) > 0) {
    foreach ($data as &$rs) {
        $nombre_final = trim((string)$rs->nombre);
        $arete = trim((string)$rs->arete);
        if ($nombre_final === '') {
            $identificador = $arete !== '' ? 'arete ' . $arete : $rs->codigo;
            $nombre_final = 'Sin nombre (' . $identificador . ')';
        }
        array_push($result, array(
            "nombre" => $nombre_final,
            "arete"  => $arete,
            "codigo" => $rs->codigo,
            "total"  => floatval($rs->total)
        ));
    }
} else {
    array_push($result, array("nombre" => "Sin datos", "arete" => "", "codigo" => "", "total" => 0));
}

echo json_encode($result);
?>