<?php
require_once '../models/Mmovimientorebano.php';

@session_start();
@$mfundos_id = $_SESSION['mfundos_id'];
$mfundos_id = intval($mfundos_id);

$result = array();

$data = Mmovimientorebano::find_by_sql("
    SELECT
        m.id,
        m.tipo_movimiento,
        m.fecha_movimiento,
        m.motivo,
        m.observaciones,
        m.total_animales,
        m.revertido,
        m.fecha_reversion,
        lo.nombre AS lote_origen,
        ld.nombre AS lote_destino,
        po.nombre AS potrero_origen,
        pd.nombre AS potrero_destino
    FROM mmovimientos_rebano m
    LEFT JOIN mlotes lo ON lo.id = m.mlotes_origen_id
    LEFT JOIN mlotes ld ON ld.id = m.mlotes_destino_id
    LEFT JOIN mpotreros po ON po.id = m.mpotreros_origen_id
    LEFT JOIN mpotreros pd ON pd.id = m.mpotreros_destino_id
    WHERE m.mfundos_id = {$mfundos_id}
    ORDER BY m.fecha_movimiento DESC
    LIMIT 500
");

if (is_array($data)) {
    foreach ($data as $rs) {
        $fecha = '';
        if ($rs->fecha_movimiento) {
            $fecha = date('d/m/Y H:i', strtotime((string)$rs->fecha_movimiento));
        }

        $origen_txt = '';
        $destino_txt = '';
        if ($rs->tipo_movimiento === 'LOTE') {
            $origen_txt  = $rs->lote_origen;
            $destino_txt = $rs->lote_destino;
        } elseif ($rs->tipo_movimiento === 'POTRERO') {
            $origen_txt  = $rs->potrero_origen;
            $destino_txt = $rs->potrero_destino;
        } else {
            $origen_txt  = trim(($rs->lote_origen ? $rs->lote_origen : '') . ' / ' . ($rs->potrero_origen ? $rs->potrero_origen : ''), ' /');
            $destino_txt = trim(($rs->lote_destino ? $rs->lote_destino : '') . ' / ' . ($rs->potrero_destino ? $rs->potrero_destino : ''), ' /');
        }

        array_push($result, array(
            'id'              => $rs->id,
            'tipo'            => $rs->tipo_movimiento,
            'fecha'           => $fecha,
            'origen'          => $origen_txt ? $origen_txt : '-',
            'destino'         => $destino_txt ? $destino_txt : '-',
            'motivo'          => $rs->motivo,
            'total_animales'  => intval($rs->total_animales),
            'revertido'       => (bool)$rs->revertido
        ));
    }
}

echo json_encode($result);
?>