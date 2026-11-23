<?php
require_once '../models/Mcategoria.php';
require_once '../lib/catalogo_fundo_helper.php';
require_once '../lib/animales_conteo_helper.php';

@session_start();
@$id_sesion   = $_SESSION['idsessionx'];
@$mfundos_id  = $_SESSION['mfundos_id'];
@$t_ganaderia = $_SESSION['tipo_ganaderia'];
@$rol         = $_SESSION['rolx'];

$mfundos_id = intval($mfundos_id);

/* Categorías del FUNDO ACTIVO + las globales que el fundo no personalizó.
   Antes el administrador veía TODAS las categorías de TODOS los fundos
   (146 filas con nombres repetidos) y el productor solo las suyas: cada uno
   veía una realidad distinta. La regla ahora es la misma para todos y es la
   que usan los demás selectores del sistema (ver lib/catalogo_fundo_helper.php).

   El orden es por sexo y edad mínima para que la lista se lea como la cadena
   real de categorías (0-7, 7-18, 18-24, 24-…). */
$fundo_cat = bovs_catalogo_fundo_id();
$tipo_cat  = bovs_catalogo_tipo_ganaderia();

/* La cantidad por categoría usa el MISMO criterio que el tablero
   (data_json/data_dashboard_categorias.php): animal activo del sistema
   (status 1, mestatus 1, estatus 1/2/4), del tipo de ganadería del fundo
   y con la edad en [edadminima, edadmaxima). Antes salía del listado
   v_list_animales_x_lotes, que incluye donadores sin habilitar (3 y 5),
   así que el listado y el gráfico mostraban números distintos. */
$edad_meses = "(COALESCE(date_part('year', age(now(), date(a.f_nacimiento))), 0) * 12"
    . " + COALESCE(date_part('month', age(now(), date(a.f_nacimiento))), 0))";

$filtro_tipo_animal = ($tipo_cat > 0) ? " AND a.mtipoganaderias_id = " . $tipo_cat : "";

$sql_cat = "SELECT c.id, c.nombre, c.edadminima, c.edadmaxima, c.sexo, c.mfundos_id,
            (SELECT COUNT(*)
             FROM manimales a
             WHERE a.mfundos_id = " . $mfundos_id . "
               AND " . bovs_animal_activo_sql('a') . $filtro_tipo_animal . "
               AND ((c.sexo = 0) OR (a.sexo = c.sexo))
               AND " . $edad_meses . " >= c.edadminima
               AND " . $edad_meses . " <  c.edadmaxima
            ) AS cantidad
        FROM mcategorias c
        WHERE c.status > 0";

if ($tipo_cat > 0) {
    $sql_cat .= " AND c.mtipoganaderias_id = " . $tipo_cat;
}

if ($fundo_cat > 0) {
    $sql_cat .= " AND (c.mfundos_id = " . $fundo_cat . " OR c.mfundos_id = 0)";
    $sql_cat .= " AND c.id NOT IN (SELECT DISTINCT b.id_padre FROM mcategorias b
                                    WHERE b.mfundos_id = " . $fundo_cat . " AND b.id_padre > 0)";
} else {
    $sql_cat .= " AND c.mfundos_id = 0";
}

$sql_cat .= " ORDER BY c.sexo ASC, c.edadminima ASC, c.edadmaxima ASC, c.nombre ASC";

$data = Mcategoria::find_by_sql($sql_cat);

$result = array();

if (!empty($data)) {
    foreach ($data as &$rs) {
        // Mapeo de sexo: 1=HEMBRA, 2=MACHO, 0=''
        $sexo_valor = isset($rs->sexo) ? intval($rs->sexo) : 0;
        switch ($sexo_valor) {
            case 1:  $sexo_texto = 'HEMBRA'; break;
            case 2:  $sexo_texto = 'MACHO';  break;
            default: $sexo_texto = '';  break;
        }

        $cantidad = isset($rs->cantidad) ? intval($rs->cantidad) : 0;

        array_push($result, array(
            "id"         => $rs->id,
            "nombre"     => $rs->nombre,
            "sexo"       => $sexo_texto,
            "edadminima" => $rs->edadminima,
            "edadmaxima" => $rs->edadmaxima,
            "cantidad"   => $cantidad,
        ));
    }
}

echo json_encode($result);
?>