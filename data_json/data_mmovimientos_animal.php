<?php
/* ============================================================
   BOVS | Movimientos de un animal específico
   ------------------------------------------------------------
   Recibe por GET: id_animal
   Devuelve JSON con el historial completo de movimientos.
   ============================================================ */

require_once '../models/Mmovimientorebano.php';
require_once '../models/Mmovimientorebanodetalle.php';

@session_start();
@$mfundos_id = $_SESSION['mfundos_id'];
$mfundos_id = intval($mfundos_id);

$id_animal = isset($_GET['id_animal']) ? intval($_GET['id_animal']) : 0;

$result = array();

if ($id_animal <= 0 || $mfundos_id <= 0) {
    echo json_encode(array());
    exit;
}

/* Traer todos los movimientos donde aparece este animal */
$data = Mmovimientorebano::find_by_sql("
    SELECT
        d.id                              AS detalle_id,
        d.mmovimientos_rebano_id          AS movimiento_id,
        m.tipo_movimiento,
        m.fecha_movimiento,
        m.motivo,
        m.observaciones,
        m.revertido,
        m.fecha_reversion,
        m.motivo_reversion,
        lo.nombre AS lote_origen,
        ld.nombre AS lote_destino,
        po.nombre AS potrero_origen,
        pd.nombre AS potrero_destino,
        cat_ant.nombre AS categoria_anterior,
        cat_new.nombre AS categoria_nueva
    FROM mmovimientos_rebano_detalle d
    INNER JOIN mmovimientos_rebano m ON m.id = d.mmovimientos_rebano_id
    LEFT JOIN mlotes lo     ON lo.id = m.mlotes_origen_id
    LEFT JOIN mlotes ld     ON ld.id = m.mlotes_destino_id
    LEFT JOIN mpotreros po  ON po.id = m.mpotreros_origen_id
    LEFT JOIN mpotreros pd  ON pd.id = m.mpotreros_destino_id
    LEFT JOIN mcategorias cat_ant ON cat_ant.id = d.categoria_anterior_id
    LEFT JOIN mcategorias cat_new ON cat_new.id = d.categoria_nueva_id
    WHERE d.manimales_id = {$id_animal}
      AND m.mfundos_id = {$mfundos_id}
    ORDER BY m.fecha_movimiento DESC
");

if (is_array($data)) {
    foreach ($data as $rs) {

        /* Armar texto origen/destino según tipo */
        $origen_txt  = '-';
        $destino_txt = '-';

        if ($rs->tipo_movimiento === 'LOTE') {
            $origen_txt  = $rs->lote_origen  ? $rs->lote_origen  : '-';
            $destino_txt = $rs->lote_destino ? $rs->lote_destino : '-';
        } elseif ($rs->tipo_movimiento === 'POTRERO') {
            $origen_txt  = $rs->potrero_origen  ? $rs->potrero_origen  : '-';
            $destino_txt = $rs->potrero_destino ? $rs->potrero_destino : '-';
        } else {
            $partes_o = array();
            if ($rs->lote_origen)    $partes_o[] = $rs->lote_origen;
            if ($rs->potrero_origen) $partes_o[] = $rs->potrero_origen;
            $origen_txt = count($partes_o) > 0 ? implode(' / ', $partes_o) : '-';

            $partes_d = array();
            if ($rs->lote_destino)    $partes_d[] = $rs->lote_destino;
            if ($rs->potrero_destino) $partes_d[] = $rs->potrero_destino;
            $destino_txt = count($partes_d) > 0 ? implode(' / ', $partes_d) : '-';
        }

        /* Formatear fecha */
        $fecha = '';
        if (!empty($rs->fecha_movimiento)) {
            $fecha = date('d/m/Y H:i', strtotime((string)$rs->fecha_movimiento));
        }

        /* Detección robusta del booleano PostgreSQL */
        $valor_rev = $rs->revertido;
        $es_revertido = (
            $valor_rev === true || $valor_rev === 't' || $valor_rev === 'T' ||
            $valor_rev === 'true' || $valor_rev === 'TRUE' ||
            $valor_rev === 1 || $valor_rev === '1'
        );

        /* Categoría antes/después */
        $cat_ant = $rs->categoria_anterior ? $rs->categoria_anterior : 'Sin categoría';
        $cat_new = $rs->categoria_nueva    ? $rs->categoria_nueva    : 'Sin cambio';

        array_push($result, array(
            'movimiento_id'      => $rs->movimiento_id,
            'tipo'               => $rs->tipo_movimiento,
            'fecha'              => $fecha,
            'origen'             => $origen_txt,
            'destino'            => $destino_txt,
            'motivo'             => $rs->motivo,
            'observaciones'      => $rs->observaciones,
            'categoria_anterior' => $cat_ant,
            'categoria_nueva'    => $cat_new,
            'revertido'          => $es_revertido,
            'fecha_reversion'    => $rs->fecha_reversion,
            'motivo_reversion'   => $rs->motivo_reversion
        ));
    }
}

echo json_encode($result);
?>