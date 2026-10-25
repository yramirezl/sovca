<?php
require_once '../models/Mpotrero.php';

@session_start();
@$mfundos_id = $_SESSION['mfundos_id'];
$mfundos_id = intval($mfundos_id);

$result = array();

$data = Mpotrero::find_by_sql("
    SELECT
        p.id,
        p.nombre,
        p.descripcion,
        p.hectareas,
        p.aforo,
        p.capacidad_maxima,
        p.tipo_pasto,
        (SELECT COUNT(*) FROM manimales a
         WHERE a.status = 1 AND a.mfundos_id = p.mfundos_id
           AND a.mpotreros_id = p.id) AS cantidad_animales
    FROM mpotreros p
    WHERE p.status = 1 AND p.mfundos_id = {$mfundos_id}
    ORDER BY p.nombre ASC
");

if (is_array($data)) {
    foreach ($data as $rs) {
        array_push($result, array(
            'id'                => $rs->id,
            'nombre'            => $rs->nombre,
            'descripcion'       => $rs->descripcion,
            'hectareas'         => $rs->hectareas,
            'aforo'             => $rs->aforo,
            'forraje_total'     => round(floatval($rs->aforo) * floatval($rs->hectareas), 2),
            'capacidad_maxima'  => $rs->capacidad_maxima,
            'tipo_pasto'        => $rs->tipo_pasto,
            'cantidad_animales' => intval($rs->cantidad_animales)
        ));
    }
}

echo json_encode($result);
?>