<?php
/**
 * Endpoint que devuelve el historial del animal en su fundo de origen.
 * Se usa desde el botón "Ver historial de origen" en la ficha.
 *
 * Compatible: PHP 7.4 y 8.3
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../models/Vanimale.php';
require_once '../models/Manimale.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (empty($_SESSION['idusuariox'])) {
    http_response_code(401);
    echo json_encode(array('ok' => false, 'error' => 'sesion'));
    exit;
}

$mfundos_id = (int)(isset($_SESSION['mfundos_id']) ? $_SESSION['mfundos_id'] : 0);
$id_animal  = (int)(isset($_POST['id_animal']) ? $_POST['id_animal'] : 0);

if ($id_animal <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'id_invalido'));
    exit;
}

if (!function_exists('fx_dh')) {
    function fx_dh($v) {
        if (!$v) return '';
        if ($v instanceof DateTime) return $v->format('d-m-Y');
        $ts = strtotime($v);
        return $ts ? date('d-m-Y', $ts) : '';
    }
}

try {
    // 1) Cargar el animal
    $data = Manimale::find('all', array(
        'conditions' => array('id=? AND mfundos_id=? AND status=1', $id_animal, $mfundos_id)
    ));

    if (!$data || !isset($data[0])) {
        echo json_encode(array('ok' => false, 'error' => 'no_encontrado'));
        exit;
    }

    $animal = $data[0];

    // 2) ¿Tiene origen migratorio?
    if (empty($animal->animal_origen_id)) {
        echo json_encode(array('ok' => true, 'tiene_origen' => false));
        exit;
    }

    // 3) Info del fundo origen
    $nombre_fundo_origen = '—';
    if (!empty($animal->mfundos_origen_id)) {
        $fo = Manimale::find_by_sql("
            SELECT nombre, cid_rif, direccion, descripcion FROM mfundos
             WHERE id = " . (int)$animal->mfundos_origen_id . "
             LIMIT 1
        ");
        if ($fo && isset($fo[0]->nombre)) {
            $nombre_fundo_origen = $fo[0]->nombre;
        }
    }

    $codigo_origen = addslashes((string)$animal->codigo_origen);
    $id_origen     = (int)$animal->animal_origen_id;
    $fundo_origen_id = (int)$animal->mfundos_origen_id;

    if (isset($_POST['action']) && $_POST['action'] === 'detalle_evento') {
        $tipo = isset($_POST['tipo']) ? (string)$_POST['tipo'] : '';
        $evento_id = isset($_POST['evento_id']) ? (int)$_POST['evento_id'] : 0;
        $consultas = array(
            'leche' => array('mprolecheras', 'codigo_rgb_id_animal_hembra'),
            'sanidad' => array('mcontrolsanitarios', 'codigo_rgb_id_animal'),
            'pesos' => array('mconsecutivopesos', 'codigo_rgb_id_animal'),
            'servicios' => array('mregservicios', 'codigo_rgb_id_animal_hembra'),
            'movimientos' => array('', '')
        );

        if ($evento_id <= 0 || !isset($consultas[$tipo]) || $fundo_origen_id <= 0) {
            echo json_encode(array('ok' => false, 'error' => 'evento_invalido'));
            exit;
        }

        if ($tipo === 'movimientos') {
            $detalle = Manimale::find_by_sql("
                SELECT mr.*, lo.nombre AS lote_origen, ld.nombre AS lote_destino,
                       po.nombre AS potrero_origen, pd.nombre AS potrero_destino
                  FROM mmovimientos_rebano mr
             LEFT JOIN mlotes lo ON lo.id = mr.mlotes_origen_id
             LEFT JOIN mlotes ld ON ld.id = mr.mlotes_destino_id
             LEFT JOIN mpotreros po ON po.id = mr.mpotreros_origen_id
             LEFT JOIN mpotreros pd ON pd.id = mr.mpotreros_destino_id
                 WHERE mr.id = $evento_id
                   AND mr.mfundos_id = $fundo_origen_id
                   AND mr.status = 1
                   AND EXISTS (
                       SELECT 1 FROM mmovimientos_rebano_detalle mrd
                        WHERE mrd.mmovimientos_rebano_id = mr.id
                          AND mrd.manimales_id = $id_origen
                   )
                 LIMIT 1
            ");
        } elseif ($tipo === 'servicios') {
            $detalle = Manimale::find_by_sql("
                SELECT s.*,
                       h.nombre AS nombre_hembra, h.nro_arete AS arete_hembra,
                       m.nombre AS nombre_macho, m.nro_arete AS arete_macho,
                       r.nombre AS nombre_raza,
                       ef.nombre AS estado_fisiologico,
                       ts.nombre AS nombre_tipo_servicio
                  FROM mregservicios s
             LEFT JOIN manimales h
                    ON h.codigo_rgb_id_animal = s.codigo_rgb_id_animal_hembra
                   AND h.mfundos_id = s.mfundos_id
             LEFT JOIN manimales m
                    ON m.codigo_rgb_id_animal = s.codigo_rgb_id_animal_macho
                   AND m.mfundos_id = s.mfundos_id
             LEFT JOIN mrazas r ON r.id = s.mrazas_id
             LEFT JOIN mestadofisiologicos ef ON ef.id = s.medofisiologicos_id
             LEFT JOIN mtiposervicios ts ON ts.id = s.mservicios_id
                 WHERE s.id = $evento_id
                   AND s.mfundos_id = $fundo_origen_id
                   AND s.codigo_rgb_id_animal_hembra = '$codigo_origen'
                   AND s.status = 1
                 LIMIT 1
            ");
        } else {
            $tabla = $consultas[$tipo][0];
            $columna_animal = $consultas[$tipo][1];
            $detalle = Manimale::find_by_sql("
                SELECT * FROM $tabla
                 WHERE id = $evento_id
                   AND mfundos_id = $fundo_origen_id
                   AND $columna_animal = '$codigo_origen'
                   AND status = 1
                 LIMIT 1
            ");

        }

        if (!$detalle || !isset($detalle[0])) {
            http_response_code(404);
            echo json_encode(array('ok' => false, 'error' => 'evento_no_encontrado'));
            exit;
        }

        $campos = array();
        $ocultar = array('id', 'status', 'mfundos_id', 'user_create', 'user_modify', 'created', 'updated', 'id_sesion_usuario', 'uuid_local', 'version', 'origen_dispositivo');
        $etiquetas = array(
            'codigo_servicio' => 'Código del servicio',
            'codigo_rgb_id_animal_hembra' => 'Código de la hembra',
            'codigo_rgb_id_animal_macho' => 'Código del macho',
            'nombre_hembra' => 'Nombre de la hembra',
            'arete_hembra' => 'Arete de la hembra',
            'nombre_macho' => 'Nombre del macho',
            'arete_macho' => 'Arete del macho',
            'nombre_raza' => 'Raza',
            'estado_fisiologico' => 'Estado fisiológico',
            'nombre_tipo_servicio' => 'Tipo de servicio',
            'mservicios_id' => 'Código del tipo de servicio',
            'mrazas_id' => 'Código de raza',
            'medofisiologicos_id' => 'Código de estado fisiológico',
            'f_servicio' => 'Fecha del servicio',
            'f_probable_palpacion' => 'Fecha probable de palpación',
            'f_palpacion' => 'Fecha de palpación',
            'f_ultimo_parto' => 'Fecha del último parto',
            'f_probable_parto' => 'Fecha probable de parto',
            'fecha_parto' => 'Fecha de parto',
            'f_secado' => 'Fecha de secado',
            'nro_servicio' => 'Número de servicio',
            'nro_terneros' => 'Número de terneros',
            'fecha_produccion' => 'Fecha de producción',
            'cantidad_leche_m' => 'Leche de la mañana',
            'cantidad_leche_t' => 'Leche de la tarde',
            'fecha_control' => 'Fecha del control',
            'fecha_prox_control' => 'Fecha del próximo control',
            'fecha_pesaje' => 'Fecha del pesaje',
            'tipo_movimiento' => 'Tipo de movimiento',
            'lote_origen' => 'Lote de origen',
            'potrero_origen' => 'Potrero de origen',
            'lote_destino' => 'Lote de destino',
            'potrero_destino' => 'Potrero de destino',
            'mlotes_origen_id' => 'Código del lote de origen',
            'mpotreros_origen_id' => 'Código del potrero de origen',
            'mlotes_destino_id' => 'Código del lote de destino',
            'mpotreros_destino_id' => 'Código del potrero de destino'
        );
        $atributos = method_exists($detalle[0], 'attributes') ? $detalle[0]->attributes() : (array)$detalle[0];
        foreach ($atributos as $campo => $valor) {
            if (in_array($campo, $ocultar, true) || strpos($campo, 'id_sesion') === 0) continue;
            if ($valor instanceof DateTime) $valor = $valor->format('d-m-Y H:i');
            elseif (is_object($valor) && method_exists($valor, 'format')) $valor = $valor->format('d-m-Y H:i');
            elseif (is_null($valor) || $valor === '') continue;
            $etiqueta = isset($etiquetas[$campo]) ? $etiquetas[$campo] : ucfirst(str_replace('_', ' ', (string)$campo));
            $campos[] = array('campo' => (string)$campo, 'etiqueta' => $etiqueta, 'valor' => $valor);
        }

        $incluir_fundo = !empty($_POST['incluir_fundo_origen']);
        echo json_encode(array(
            'ok' => true,
            'tipo' => $tipo,
            'campos' => $campos,
            'fundo' => $incluir_fundo ? array(
                'id' => $fundo_origen_id,
                'nombre' => $nombre_fundo_origen,
                'rif' => isset($fo[0]->cid_rif) ? $fo[0]->cid_rif : '',
                'direccion' => isset($fo[0]->direccion) ? $fo[0]->direccion : '',
                'descripcion' => isset($fo[0]->descripcion) ? $fo[0]->descripcion : '',
                'codigo' => $animal->codigo_origen,
                'fecha_migracion' => fx_dh($animal->fecha_migracion)
            ) : null
        ));
        exit;
    }

    // ═══════════════════════════════════════════════════════════
    // 4) Historial del animal original
    // ═══════════════════════════════════════════════════════════

    // Producción de leche y servicios solo aplican a hembras.
    $hist_leche = array();
    $hist_servicios = array();
    if ((int)$animal->sexo === 1) {
        $q = @Manimale::find_by_sql("
            SELECT id, fecha_produccion AS fecha,
                   cantidad_leche_m, cantidad_leche_t,
                   (cantidad_leche_m + cantidad_leche_t) AS total
              FROM mprolecheras
             WHERE codigo_rgb_id_animal_hembra = '$codigo_origen'
               AND mfundos_id = $fundo_origen_id
               AND status = 1
          ORDER BY fecha_produccion DESC
             LIMIT 100
        ");
        if ($q) {
            foreach ($q as $l) {
                $hist_leche[] = array(
                    'evento_id' => (int)$l->id,
                    'fecha' => fx_dh($l->fecha),
                    'm'     => (float)$l->cantidad_leche_m,
                    't'     => (float)$l->cantidad_leche_t,
                    'total' => (float)$l->total
                );
            }
        }

        $q = @Manimale::find_by_sql("
            SELECT id, f_servicio AS fecha, codigo_servicio, nro_servicio, observaciones
              FROM mregservicios
             WHERE codigo_rgb_id_animal_hembra = '$codigo_origen'
               AND mfundos_id = $fundo_origen_id
               AND status = 1
          ORDER BY f_servicio DESC
             LIMIT 100
        ");
        if ($q) {
            foreach ($q as $s) {
                $hist_servicios[] = array(
                    'evento_id' => (int)$s->id,
                    'fecha'  => fx_dh($s->fecha),
                    'codigo' => $s->codigo_servicio,
                    'nro'    => (int)$s->nro_servicio,
                    'obs'    => $s->observaciones
                );
            }
        }
    }

    // SANIDAD
    $hist_sanidad = array();
    $q = @Manimale::find_by_sql("
         SELECT id, fecha_control AS fecha, evento, responsable, observaciones,
             fecha_prox_control
          FROM mcontrolsanitarios
         WHERE codigo_rgb_id_animal = '$codigo_origen'
           AND mfundos_id = $fundo_origen_id
           AND status = 1
      ORDER BY fecha_control DESC
         LIMIT 100
    ");
    if ($q) {
        foreach ($q as $s) {
            $hist_sanidad[] = array(
                'evento_id'   => (int)$s->id,
                'fecha'       => fx_dh($s->fecha),
                'evento'      => $s->evento,
                'responsable' => $s->responsable,
                'prox'        => fx_dh($s->fecha_prox_control),
                'obs'         => $s->observaciones
            );
        }
    }

    // PESOS
    $hist_pesos = array();
    $q = @Manimale::find_by_sql("
        SELECT id, fecha_pesaje AS fecha, peso
          FROM mconsecutivopesos
         WHERE codigo_rgb_id_animal = '$codigo_origen'
           AND mfundos_id = $fundo_origen_id
           AND status = 1
      ORDER BY fecha_pesaje DESC
         LIMIT 100
    ");
    if ($q) {
        foreach ($q as $p) {
            $hist_pesos[] = array(
                'evento_id' => (int)$p->id,
                'fecha' => fx_dh($p->fecha),
                'peso'  => (float)$p->peso
            );
        }
    }

    // MOVIMIENTOS — ⭐ CORREGIDO: mmovimientos_rebano_id (doble "m")
    $hist_movimientos = array();
    $q = @Manimale::find_by_sql("
         SELECT mr.id AS evento_id, mr.fecha_movimiento AS fecha,
             mr.tipo_movimiento, mr.motivo, mr.observaciones,
             lo.nombre AS lote_origen, ld.nombre AS lote_destino,
             po.nombre AS potrero_origen, pd.nombre AS potrero_destino
          FROM mmovimientos_rebano_detalle mrd
    INNER JOIN mmovimientos_rebano mr ON mr.id = mrd.mmovimientos_rebano_id
         LEFT JOIN mlotes lo ON lo.id = mr.mlotes_origen_id
         LEFT JOIN mlotes ld ON ld.id = mr.mlotes_destino_id
         LEFT JOIN mpotreros po ON po.id = mr.mpotreros_origen_id
         LEFT JOIN mpotreros pd ON pd.id = mr.mpotreros_destino_id
         WHERE mrd.manimales_id = $id_origen
           AND mr.mfundos_id = $fundo_origen_id
           AND mr.status = 1
      ORDER BY mr.fecha_movimiento DESC
         LIMIT 50
    ");
    if ($q) {
        foreach ($q as $m) {
            $hist_movimientos[] = array(
                'evento_id' => (int)$m->evento_id,
                'fecha'  => fx_dh($m->fecha),
                'tipo'   => $m->tipo_movimiento,
                'motivo' => $m->motivo,
                'obs'    => $m->observaciones,
                'origen' => trim(($m->lote_origen ? $m->lote_origen : '') .
                                 ($m->potrero_origen ? ' / ' . $m->potrero_origen : '')),
                'destino' => trim(($m->lote_destino ? $m->lote_destino : '') .
                                  ($m->potrero_destino ? ' / ' . $m->potrero_destino : ''))
            );
        }
    }

    // 5) Respuesta
    echo json_encode(array(
        'ok'              => true,
        'tiene_origen'    => true,
        'fundo_origen'    => $nombre_fundo_origen,
        'codigo_origen'   => $animal->codigo_origen,
        'sexo'            => (int)$animal->sexo,
        'fecha_migracion' => fx_dh($animal->fecha_migracion),
        'leche'           => $hist_leche,
        'sanidad'         => $hist_sanidad,
        'pesos'           => $hist_pesos,
        'servicios'       => $hist_servicios,
        'movimientos'     => $hist_movimientos
    ));

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array(
        'ok'    => false,
        'error' => 'excepcion',
        'msg'   => $e->getMessage()
    ));
}