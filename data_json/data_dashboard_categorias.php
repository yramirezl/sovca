<?php
/**
 * Datos del gráfico "Distribución por Categoría" (tablero).
 *
 * La categoría de un animal NO es la de ingreso: cambia con la edad y el
 * sexo, y es la misma regla que usa el módulo Categorías
 * (data_json/data_mcategorias.php). Antes este endpoint agrupaba por
 * `categoria_ingreso_id`, así que los animales adultos seguían contando
 * como TERNERA/TERNERO y VACA no aparecía nunca.
 *
 * Se cuentan solo los animales que el sistema considera ACTIVOS
 * (lib/animales_conteo_helper.php):
 *     status = 1 AND mestatus_id = 1 AND estatus_animal IN (1, 2, 4)
 * — activo, donador activo y donadora activa —, que es el mismo criterio
 * del contador "TOTAL ANIMALES" del tablero.
 *
 * Respuesta: [{ "nombre": "VACA", "total": 32 }, ...]
 */

require_once '../models/Manimale.php';
require_once '../lib/catalogo_fundo_helper.php';
require_once '../lib/animales_conteo_helper.php';

@session_start();

$mfundos_id = isset($_SESSION['mfundos_id']) ? intval($_SESSION['mfundos_id']) : 0;
$fundo_cat  = bovs_catalogo_fundo_id();
$tipo_cat   = bovs_catalogo_tipo_ganaderia();

$vacio = array(array("nombre" => "Sin datos", "total" => 0));

if ($mfundos_id <= 0) {
    echo json_encode($vacio);
    exit;
}

/* Edad en meses, con el mismo cálculo que el listado de Categorías
   (un animal sin fecha de nacimiento cuenta como 0 meses). */
$edad_meses = "(COALESCE(date_part('year', age(now(), date(a.f_nacimiento))), 0) * 12"
    . " + COALESCE(date_part('month', age(now(), date(a.f_nacimiento))), 0))";

$filtro_tipo_animal = ($tipo_cat > 0) ? " AND a.mtipoganaderias_id = " . $tipo_cat : "";

$sql = "SELECT c.nombre,
               c.sexo,
               (SELECT COUNT(*)
                  FROM manimales a
                 WHERE a.mfundos_id = " . $mfundos_id . "
                   AND " . bovs_animal_activo_sql('a') . $filtro_tipo_animal . "
                   AND (c.sexo = 0 OR a.sexo = c.sexo)
                   AND " . $edad_meses . " >= c.edadminima
                   AND " . $edad_meses . " <  c.edadmaxima
               ) AS total
          FROM mcategorias c
         WHERE c.status > 0";

if ($tipo_cat > 0) {
    $sql .= " AND c.mtipoganaderias_id = " . $tipo_cat;
}

if ($fundo_cat > 0) {
    $sql .= " AND (c.mfundos_id = " . $fundo_cat . " OR c.mfundos_id = 0)";
    $sql .= " AND c.id NOT IN (SELECT DISTINCT b.id_padre FROM mcategorias b
                                WHERE b.mfundos_id = " . $fundo_cat . " AND b.id_padre > 0)";
} else {
    $sql .= " AND c.mfundos_id = 0";
}

$sql .= " ORDER BY total DESC, c.nombre ASC";

$result = array();
$suma   = 0;

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
        $suma += $total;
    }
}

/* Los animales activos que no caen en ningún rango de edad se muestran
   aparte: así la suma del gráfico coincide con el contador del tablero. */
$activos = bovs_contar_animales_activos($mfundos_id, $tipo_cat > 0 ? $tipo_cat : null);
$resto   = $activos - $suma;
if ($resto > 0) {
    $result[] = array("nombre" => "Sin categoría", "total" => $resto);
}

if (count($result) === 0) {
    echo json_encode($vacio);
    exit;
}

echo json_encode($result);
