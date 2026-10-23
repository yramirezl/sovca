<?php
/**
 * data_dashboard_bundle.php
 * Endpoint unificado del tablero.
 *
 * Recibe:
 *   ?desde=YYYY-MM-DD
 *   ?hasta=YYYY-MM-DD
 *
 * Devuelve UN JSON con las 5 series que dependen del rango:
 * {
 *   "prod_leche":  [ { total_leche, fecha_produccion }, ... ],
 *   "nacimientos": [ { mes, fichados, sin_fichar, desincorporados }, ... ],
 *   "top_vacas":   [ { nombre, arete, codigo, total }, ... ],
 *   "peso":        [ { total_peso, fecha_pesaje }, ... ],
 *   "actividad":   [ { mes, servicios, nacimientos, controles }, ... ]
 * }
 *
 * Reduce de 5 requests AJAX a 1. No cachea en servidor: el cache
 * vive en el cliente (JS) por URL exacta.
 */

require_once '../models/Manimale.php';
require_once '../models/Mregnacimiento.php';
require_once '../models/Mconsecutivopeso.php';
require_once '../models/Mprolechera.php';

session_start();
$mfundos_id = isset($_SESSION['mfundos_id']) ? intval($_SESSION['mfundos_id']) : 0;

$desde = (!empty($_GET['desde'])) ? $_GET['desde'] : date('Y-m-01');
$hasta = (!empty($_GET['hasta'])) ? $_GET['hasta'] : date('Y-m-d');

$out = array(
    'prod_leche'  => array(),
    'nacimientos' => array(),
    'top_vacas'   => array(),
    'peso'        => array(),
    'actividad'   => array()
);

/* ============================================================
   1. PRODUCCIÓN DE LECHE (diaria)
   ============================================================ */
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
    foreach ($data as $rs) {
        $out['prod_leche'][] = array(
            "total_leche"      => floatval($rs->total_leche),
            "fecha_produccion" => $rs->fecha_produccion->format("d-m-Y")
        );
    }
} else {
    $out['prod_leche'][] = array("total_leche" => 0, "fecha_produccion" => "Sin datos");
}

/* ============================================================
   2. ESTADO DE NACIMIENTOS (mensual)
   ============================================================ */
$data = Mregnacimiento::find_by_sql("
    SELECT
        to_char(f_parto, 'YYYY-MM') AS mes,
        SUM(CASE WHEN fichado = true THEN 1 ELSE 0 END)                          AS fichados,
        SUM(CASE WHEN fichado = false AND mestatus_id = 1  THEN 1 ELSE 0 END)    AS sin_fichar,
        SUM(CASE WHEN fichado = false AND mestatus_id != 1 THEN 1 ELSE 0 END)    AS desincorporados
    FROM mregnacimientos
    WHERE mfundos_id = " . $mfundos_id . "
      AND f_parto IS NOT NULL
      AND f_parto::date BETWEEN DATE '" . $desde . "' AND DATE '" . $hasta . "'
    GROUP BY to_char(f_parto, 'YYYY-MM')
    ORDER BY mes ASC;
");
if ($data != null && count($data) > 0) {
    foreach ($data as $rs) {
        $out['nacimientos'][] = array(
            "mes"             => $rs->mes,
            "fichados"        => intval($rs->fichados),
            "sin_fichar"      => intval($rs->sin_fichar),
            "desincorporados" => intval($rs->desincorporados)
        );
    }
} else {
    $out['nacimientos'][] = array(
        "mes" => "Sin datos", "fichados" => 0, "sin_fichar" => 0, "desincorporados" => 0
    );
}

/* ============================================================
   3. TOP 10 VACAS PRODUCTORAS
   ============================================================ */
$data = Mprolechera::find_by_sql("
    SELECT
        NULLIF(TRIM(m.nombre), '')    AS nombre,
        NULLIF(TRIM(m.nro_arete), '') AS arete,
        m.codigo_rgb_id_animal        AS codigo,
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
    foreach ($data as $rs) {
        $nombre_final = trim((string)$rs->nombre);
        $arete = trim((string)$rs->arete);
        if ($nombre_final === '') {
            $identificador = $arete !== '' ? 'arete ' . $arete : $rs->codigo;
            $nombre_final = 'Sin nombre (' . $identificador . ')';
        }
        $out['top_vacas'][] = array(
            "nombre" => $nombre_final,
            "arete"  => $arete,
            "codigo" => $rs->codigo,
            "total"  => floatval($rs->total)
        );
    }
} else {
    $out['top_vacas'][] = array("nombre" => "Sin datos", "arete" => "", "codigo" => "", "total" => 0);
}

/* ============================================================
   4. CONSECUTIVO DE PESO (suma diaria)
   ============================================================ */
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
    foreach ($data as $rs) {
        $out['peso'][] = array(
            "total_peso"   => floatval($rs->total_peso),
            "fecha_pesaje" => $rs->fecha_pesaje->format("d-m-Y")
        );
    }
} else {
    $out['peso'][] = array("total_peso" => 0, "fecha_pesaje" => "Sin datos");
}

/* ============================================================
   5. ACTIVIDAD DEL FUNDO (servicios + nacimientos + controles)
   ============================================================ */
$meses_act = array();

$servicios = Manimale::find_by_sql("
    SELECT to_char(f_servicio, 'YYYY-MM') AS mes, COUNT(*) AS total
    FROM mregservicios
    WHERE mfundos_id = " . $mfundos_id . "
      AND status = 1
      AND f_servicio BETWEEN CAST('" . $desde . "' AS DATE) AND CAST('" . $hasta . "' AS DATE)
    GROUP BY to_char(f_servicio, 'YYYY-MM')
    ORDER BY mes ASC;
");

$nacimientos_act = Manimale::find_by_sql("
    SELECT to_char(f_parto, 'YYYY-MM') AS mes, COUNT(*) AS total
    FROM mregnacimientos
    WHERE mfundos_id = " . $mfundos_id . "
      AND status = 1
      AND f_parto BETWEEN CAST('" . $desde . "' AS DATE) AND CAST('" . $hasta . "' AS DATE)
    GROUP BY to_char(f_parto, 'YYYY-MM')
    ORDER BY mes ASC;
");

$controles = Manimale::find_by_sql("
    SELECT to_char(fecha_control, 'YYYY-MM') AS mes, COUNT(*) AS total
    FROM mcontrolsanitarios
    WHERE mfundos_id = " . $mfundos_id . "
      AND status = 1
      AND fecha_control BETWEEN CAST('" . $desde . "' AS DATE) AND CAST('" . $hasta . "' AS DATE)
    GROUP BY to_char(fecha_control, 'YYYY-MM')
    ORDER BY mes ASC;
");

foreach (array(
    'servicios'   => $servicios,
    'nacimientos' => $nacimientos_act,
    'controles'   => $controles
) as $clave => $ds) {
    if ($ds == null) continue;
    foreach ($ds as $row) {
        $m = $row->mes;
        if (!isset($meses_act[$m])) {
            $meses_act[$m] = array('mes' => $m, 'servicios' => 0, 'nacimientos' => 0, 'controles' => 0);
        }
        $meses_act[$m][$clave] = intval($row->total);
    }
}

ksort($meses_act);
if (count($meses_act) > 0) {
    $out['actividad'] = array_values($meses_act);
} else {
    $out['actividad'][] = array('mes' => 'Sin datos', 'servicios' => 0, 'nacimientos' => 0, 'controles' => 0);
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($out);
?>