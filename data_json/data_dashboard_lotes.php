<?php
/**
 * Datos del gráfico "Distribución por Lote" (tablero).
 *
 * Antes contaba todo lo que tuviera `status = 1`, incluyendo animales
 * descartados, vendidos, muertos o extraviados (mestatus_id <> 1) y los
 * donadores sin habilitar (estatus_animal 3 y 5). Por eso aparecían más
 * barras —y más animales— que el contador "TOTAL ANIMALES" del tablero.
 *
 * Ahora usa el criterio único de animal activo del sistema
 * (lib/animales_conteo_helper.php):
 *     status = 1 AND mestatus_id = 1 AND estatus_animal IN (1, 2, 4)
 * y el mismo tipo de ganadería del fundo activo que usa ese contador.
 *
 * Respuesta: [{ "nombre": "GENERAL", "total": 24 }, ...]
 */

require_once '../models/Manimale.php';
require_once '../lib/catalogo_fundo_helper.php';
require_once '../lib/animales_conteo_helper.php';

@session_start();

$mfundos_id  = isset($_SESSION['mfundos_id']) ? intval($_SESSION['mfundos_id']) : 0;
$t_ganaderia = bovs_catalogo_tipo_ganaderia();

$vacio = array(array("nombre" => "Sin datos", "total" => 0));

if ($mfundos_id <= 0) {
    echo json_encode($vacio);
    exit;
}

$filtro_tipo = ($t_ganaderia > 0) ? " AND a.mtipoganaderias_id = " . $t_ganaderia : "";

$sql = "SELECT COALESCE(NULLIF(l.nombre, ''), 'Sin lote') AS nombre,
               COUNT(a.id) AS total
          FROM manimales a
          LEFT JOIN mlotes l
                 ON l.id = a.mlotes_id
                AND l.mfundos_id = a.mfundos_id
         WHERE a.mfundos_id = " . $mfundos_id . "
           AND " . bovs_animal_activo_sql('a') . $filtro_tipo . "
         GROUP BY COALESCE(NULLIF(l.nombre, ''), 'Sin lote')
         ORDER BY total DESC
         LIMIT 20";

$result = array();

$data = Manimale::find_by_sql($sql);
if (is_array($data)) {
    foreach ($data as $rs) {
        $total = isset($rs->total) ? intval($rs->total) : 0;
        if ($total <= 0) {
            continue;
        }
        $result[] = array(
            "nombre" => $rs->nombre,
            "total"  => $total
        );
    }
}

if (count($result) === 0) {
    echo json_encode($vacio);
    exit;
}

echo json_encode($result);
