<?php
/* ============================================================
   BOVS | Verifica si una categoría tiene animales activos
   ------------------------------------------------------------
   Recibe por POST: id_categoria
   Devuelve JSON:   { "cant_reg": N, "error": "..." }
   Si cant_reg > 0, el reporte rcat.php tiene datos para mostrar.
   ============================================================ */

require_once '../models/Mcategoria.php';

@session_start();
@$id_sesion   = isset($_SESSION['idsessionx'])       ? $_SESSION['idsessionx']       : '';
@$mfundos_id  = isset($_SESSION['mfundos_id'])       ? $_SESSION['mfundos_id']       : 0;
@$t_ganaderia = isset($_SESSION['tipo_ganaderia'])   ? $_SESSION['tipo_ganaderia']   : 0;

$mfundos_id = intval($mfundos_id);

/* --- Leer id_categoria (POST o GET) --- */
$id_categoria = 0;
if (isset($_POST['id_categoria'])) {
    $id_categoria = intval($_POST['id_categoria']);
} elseif (isset($_GET['id_categoria'])) {
    $id_categoria = intval($_GET['id_categoria']);
}

$response = array('cant_reg' => 0, 'error' => '');

if ($id_categoria <= 0) {
    $response['error'] = 'Parámetros inválidos';
    print_r(json_encode($response));
    exit;
}
if (empty($id_sesion)) {
    $response['error'] = 'Sesión no válida';
    print_r(json_encode($response));
    exit;
}

/* --- Datos de la categoría --- */
$cat_data = Mcategoria::find_by_sql(
    "SELECT id, sexo, edadminima, edadmaxima
     FROM mcategorias
     WHERE id = " . $id_categoria . " AND status > 0;"
);

if (empty($cat_data)) {
    $response['error'] = 'Categoría no encontrada';
    print_r(json_encode($response));
    exit;
}

$sexo_cat = intval($cat_data[0]->sexo);
$edad_min = intval($cat_data[0]->edadminima);
$edad_max = intval($cat_data[0]->edadmaxima);

/* --- Filtro por sexo (1=HEMBRA, 2=MACHO, 0=AMBOS) --- */
$filtro_sexo = '';
if ($sexo_cat === 1 || $sexo_cat === 2) {
    $filtro_sexo = " AND a.sexo = " . $sexo_cat . " ";
}

/* --- Contar animales activos que cumplen criterios --- */
$sql = "
    SELECT COUNT(*) AS cant_reg
    FROM v_list_animales_x_lotes a
    WHERE a.mfundos_id = " . $mfundos_id . "
      AND a.estatus_animal IS NOT NULL
      " . $filtro_sexo . "
      AND (COALESCE(a.anios, 0) * 12 + COALESCE(a.meses, 0)) >= " . $edad_min . "
      AND (COALESCE(a.anios, 0) * 12 + COALESCE(a.meses, 0)) <  " . $edad_max . "
";

$resultado = Mcategoria::find_by_sql($sql);

$cant_reg = 0;
if (!empty($resultado) && isset($resultado[0]->cant_reg)) {
    $cant_reg = intval($resultado[0]->cant_reg);
}

$response['cant_reg'] = $cant_reg;

print_r(json_encode($response));
?>