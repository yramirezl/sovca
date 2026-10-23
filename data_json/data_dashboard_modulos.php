<?php
/* ============================================================
   BOVS | Devuelve qué módulos tienen datos en el fundo actual
   ------------------------------------------------------------
   Se usa para ocultar dinámicamente gráficas sin datos.
   ============================================================ */

require_once '../models/Manimale.php';

session_start();
$mfundos_id = isset($_SESSION['mfundos_id']) ? intval($_SESSION['mfundos_id']) : 0;

$result = array(
    'leche'       => false,
    'peso'        => false,
    'nacimientos' => false,
    'servicios'   => false,
    'controles'   => false,
    'lotes'       => false,
    'categorias'  => false
);

if ($mfundos_id > 0) {
    $queries = array(
        'leche'       => "SELECT COUNT(*) AS c FROM mprolecheras       WHERE mfundos_id = " . $mfundos_id . " AND status = 1",
        'peso'        => "SELECT COUNT(*) AS c FROM mconsecutivopesos  WHERE mfundos_id = " . $mfundos_id . " AND status = 1",
        'nacimientos' => "SELECT COUNT(*) AS c FROM mregnacimientos    WHERE mfundos_id = " . $mfundos_id . " AND status = 1",
        'servicios'   => "SELECT COUNT(*) AS c FROM mregservicios      WHERE mfundos_id = " . $mfundos_id . " AND status = 1",
        'controles'   => "SELECT COUNT(*) AS c FROM mcontrolsanitarios WHERE mfundos_id = " . $mfundos_id . " AND status = 1",
        'lotes'       => "SELECT COUNT(*) AS c FROM mlotes             WHERE mfundos_id = " . $mfundos_id . " AND status = 1",
        'categorias'  => "SELECT COUNT(*) AS c FROM manimales          WHERE mfundos_id = " . $mfundos_id . " AND status = 1"
    );

    foreach ($queries as $key => $sql) {
        $data = Manimale::find_by_sql($sql);
        if ($data && count($data) > 0) {
            $result[$key] = intval($data[0]->c) > 0;
        }
    }
}

echo json_encode($result);
?>