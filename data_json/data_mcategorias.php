<?php
require_once '../models/Mcategoria.php';

@session_start();
@$id_sesion   = $_SESSION['idsessionx'];
@$mfundos_id  = $_SESSION['mfundos_id'];
@$t_ganaderia = $_SESSION['tipo_ganaderia'];
@$rol         = $_SESSION['rolx'];

$mfundos_id = intval($mfundos_id);

if (!empty($id_sesion) && (($rol == 1) || ($rol == 4))) {
    // Privilegios del programador y supervisor
    $data = Mcategoria::find_by_sql("
        SELECT c.id, c.nombre, c.edadminima, c.edadmaxima, c.sexo, c.mfundos_id,
            (SELECT COUNT(*)
             FROM v_list_animales_x_lotes a
             WHERE a.mfundos_id = " . $mfundos_id . "
               AND a.estatus_animal IS NOT NULL
               AND ((c.sexo = 0) OR (a.sexo = c.sexo))
               AND (COALESCE(a.anios, 0) * 12 + COALESCE(a.meses, 0)) >= c.edadminima
               AND (COALESCE(a.anios, 0) * 12 + COALESCE(a.meses, 0)) <  c.edadmaxima
            ) AS cantidad
        FROM mcategorias c
        WHERE c.status > 0
        ORDER BY c.nombre ASC;
    ");
} else if (!empty($id_sesion) && ($rol == 2)) {
    $data = Mcategoria::find_by_sql("
        SELECT c.id, c.id_padre, c.nombre, c.edadminima, c.edadmaxima, c.sexo, c.mfundos_id,
            (SELECT COUNT(*)
             FROM v_list_animales_x_lotes a
             WHERE a.mfundos_id = " . $mfundos_id . "
               AND a.estatus_animal IS NOT NULL
               AND ((c.sexo = 0) OR (a.sexo = c.sexo))
               AND (COALESCE(a.anios, 0) * 12 + COALESCE(a.meses, 0)) >= c.edadminima
               AND (COALESCE(a.anios, 0) * 12 + COALESCE(a.meses, 0)) <  c.edadmaxima
            ) AS cantidad
        FROM mcategorias c
        WHERE c.mtipoganaderias_id = " . $t_ganaderia . "
          AND (c.mfundos_id = " . $mfundos_id . " OR c.mfundos_id = 0)
          AND c.id NOT IN (
              SELECT DISTINCT b.id_padre FROM mcategorias b
              WHERE b.mtipoganaderias_id = " . $t_ganaderia . "
                AND b.mfundos_id = " . $mfundos_id . "
          )
          AND c.status > 0
        ORDER BY c.nombre ASC;
    ");
}

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

print_r(json_encode($result));
?>