<?php
require_once '../models/Vmregnacimiento.php';
session_start();
$mfundos_id = $_SESSION['mfundos_id'];
$result = array();

$items = 0;
if (($mfundos_id != '') && ($mfundos_id != ' ')) {
	$data = Vmregnacimiento::find_by_sql("
		SELECT n.id AS id_animal,
		       n.id_ternero,
		       n.codigo_ternero,
		       n.codigo_rgb_id_animal_hembra,
		       n.codigo_rgb_id_animal_macho,
		       n.fichado,
		       n.nro_arete,
	       COALESCE(crias.cantidad, 1) AS cantidad_terneros,
	       COALESCE(crias.fichadas, 0) AS crias_fichadas,
	       COALESCE(NULLIF(crias.ids_terneros, ''), NULLIF(n.id_ternero, ''), 'Sin ID') AS ids_terneros
		  FROM Vmregnacimientos n
		  LEFT JOIN (
		       SELECT t.mregnacimientos_id,
		              COUNT(*) AS cantidad,
		              COUNT(a.id) AS fichadas,
		              STRING_AGG(NULLIF(t.id_ternero, ''), ', ' ORDER BY t.nro_ternero) AS ids_terneros
		         FROM mternerosxnacimientos t
		    LEFT JOIN manimales a
		           ON a.mfundos_id = t.mfundos_id
		          AND a.status = 1
		          AND UPPER(BTRIM(a.codigo_ternero)) = UPPER(BTRIM(t.id_ternero))
		        WHERE t.status = 1
		     GROUP BY t.mregnacimientos_id
		  ) crias ON crias.mregnacimientos_id = n.id
		 WHERE n.mfundos_id=" . (int)$mfundos_id . " AND n.mestatus_id=1 AND n.estatus_regnac=1
		 ORDER BY n.codigo_ternero DESC;");

	foreach ($data as &$rs) {

		$cantidad_crias = (int)$rs->cantidad_terneros;
		$crias_fichadas = (int)$rs->crias_fichadas;

		/* Con mellizos el estado se cuenta por cría: «1 de 2 fichadas».
		   En partos de una sola cría se mantiene el rótulo original. */
		if ($cantidad_crias > 1) {
			$estado_ficha = $crias_fichadas . ' de ' . $cantidad_crias . ' fichadas';
		} else {
			$estado_ficha = $rs->fichado;
		}

		array_push($result, array(
			"codigo_ternero" => $rs->codigo_ternero,
			"id_ternero" => $rs->id_ternero,
			"cantidad_terneros" => $cantidad_crias,
			"terneros" => $cantidad_crias . ($cantidad_crias === 1 ? " cría: " : " crías: ") . $rs->ids_terneros,
			"fichado" => $estado_ficha,
			"codigo_rgb_id_animal_hembra" => $rs->codigo_rgb_id_animal_hembra,
			"nro_arete" => $rs->nro_arete,
			"codigo_rgb_id_animal_macho" => $rs->codigo_rgb_id_animal_macho,
			"id" => $rs->id_animal

		));
		$items++;
	}
}

print_r(json_encode($result));
