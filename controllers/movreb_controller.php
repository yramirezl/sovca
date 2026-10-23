<?php
require_once '../models/Mmovimientorebano.php';
require_once '../models/Mmovimientorebanodetalle.php';
require_once '../models/Mlote.php';
require_once '../models/Mpotrero.php';
require_once '../models/Manimale.php';
require_once '../models/Mcategoria.php';

date_default_timezone_set('America/Caracas');

/* Diagnóstico temporal: log completo de errores */
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

@$action              = isset($_POST['action']) ? $_POST['action'] : '';
@$id                  = isset($_POST['id_movimiento']) ? intval($_POST['id_movimiento']) : 0;
@$record              = isset($_POST['record']) ? intval($_POST['record']) : 0;
@$tipo_movimiento     = isset($_POST['tipo_movimiento']) ? $_POST['tipo_movimiento'] : '';
@$lote_origen         = isset($_POST['lote_origen']) ? intval($_POST['lote_origen']) : 0;
@$potrero_origen      = isset($_POST['potrero_origen']) ? intval($_POST['potrero_origen']) : 0;
@$lote_destino        = isset($_POST['lote_destino']) ? intval($_POST['lote_destino']) : 0;
@$potrero_destino     = isset($_POST['potrero_destino']) ? intval($_POST['potrero_destino']) : 0;
@$motivo              = isset($_POST['motivo']) ? $_POST['motivo'] : '';
@$observaciones       = isset($_POST['observaciones']) ? $_POST['observaciones'] : '';
@$animales_ids        = isset($_POST['animales_ids']) ? $_POST['animales_ids'] : '';
@$motivo_reversion    = isset($_POST['motivo_reversion']) ? $_POST['motivo_reversion'] : '';
@$fecha_desde         = isset($_POST['fecha_desde']) ? $_POST['fecha_desde'] : '';
@$fecha_hasta         = isset($_POST['fecha_hasta']) ? $_POST['fecha_hasta'] : '';

/* ============================================================
   HELPERS — declarados ARRIBA para que estén disponibles
   en cualquier case del switch
   ============================================================ */
if (!function_exists('movreb_fecha_texto')) {
    /**
     * Convierte a texto legible un valor de fecha que puede venir como
     * DateTime (php-activerecord castea timestamp/datetime a DateTime),
     * como cadena SQL o como nulo.
     */
    function movreb_fecha_texto($valor, $formato = 'd-m-Y H:i') {
        if ($valor === null || $valor === '') {
            return '';
        }
        if ($valor instanceof DateTime) {
            return $valor->format($formato);
        }
        if (is_object($valor)) {
            if (isset($valor->date)) {
                $ts = strtotime($valor->date);
                return $ts ? date($formato, $ts) : (string) $valor->date;
            }
            if (method_exists($valor, 'format')) {
                return $valor->format($formato);
            }
            return '';
        }
        $ts = strtotime((string) $valor);
        return $ts ? date($formato, $ts) : (string) $valor;
    }
}

if (!function_exists('calcularCategoriaActual')) {
    function calcularCategoriaActual($sexo, $fecha_nac, $mfundos_id, $t_ganaderia) {

        $fecha_f = date('Y-m-d');
        $fecha_i = substr((string)$fecha_nac, 0, 10);

        $dias = (strtotime($fecha_i) - strtotime($fecha_f)) / 86400;
        $dias = abs($dias);
        $dias = floor($dias);
        $meses = (int)($dias / 30);

        /* Buscar en categorias propias del fundo */
        $propia = Mcategoria::find_by_sql("
            SELECT id FROM mcategorias
            WHERE mtipoganaderias_id = {$t_ganaderia}
              AND status = 1
              AND sexo = {$sexo}
              AND mfundos_id = {$mfundos_id}
              AND edadminima <= {$meses}
              AND edadmaxima > {$meses}
            ORDER BY id ASC LIMIT 1
        ");
        if (is_array($propia) && count($propia) > 0) {
            return $propia[0]->id;
        }

        /* Si no, en estandar (mfundos_id = 0) */
        $estandar = Mcategoria::find_by_sql("
            SELECT id FROM mcategorias
            WHERE mtipoganaderias_id = {$t_ganaderia}
              AND status = 1
              AND sexo = {$sexo}
              AND mfundos_id = 0
              AND edadminima <= {$meses}
              AND edadmaxima > {$meses}
            ORDER BY id ASC LIMIT 1
        ");
        if (is_array($estandar) && count($estandar) > 0) {
            return $estandar[0]->id;
        }

        return null;
    }
}

/* ============================================================
   SWITCH PRINCIPAL
   ============================================================ */
try {

switch ($action) {

    /* ============================================================
       CREAR MOVIMIENTO
       ============================================================ */
    case 'crear_movimiento':

        session_start();
        $usuario_id  = $_SESSION['idusuariox'];
        $mfundos_id  = $_SESSION['mfundos_id'];
        $t_ganaderia = $_SESSION['tipo_ganaderia'];
        $rol         = $_SESSION['rolx'];
        $hoy         = date('d-m-Y');

        if (!in_array(intval($rol), array(1, 2, 3))) {
            echo json_encode(array('ok' => false, 'error' => 'No tiene permisos para crear movimientos.'));
            break;
        }

        if (!in_array($tipo_movimiento, array('LOTE', 'POTRERO', 'AMBOS'))) {
            echo json_encode(array('ok' => false, 'error' => 'Tipo de movimiento invalido.'));
            break;
        }
        if (trim($motivo) === '') {
            echo json_encode(array('ok' => false, 'error' => 'El motivo es obligatorio.'));
            break;
        }
        if ($animales_ids === '' || $animales_ids === '[]') {
            echo json_encode(array('ok' => false, 'error' => 'Debe seleccionar al menos un animal.'));
            break;
        }

        $ids_array = json_decode($animales_ids, true);
        if (!is_array($ids_array) || count($ids_array) === 0) {
            echo json_encode(array('ok' => false, 'error' => 'La lista de animales esta vacia.'));
            break;
        }

        if ($tipo_movimiento === 'LOTE') {
            if ($lote_origen <= 0 || $lote_destino <= 0) {
                echo json_encode(array('ok' => false, 'error' => 'Debe indicar lote origen y destino.'));
                break;
            }
            if ($lote_origen === $lote_destino) {
                echo json_encode(array('ok' => false, 'error' => 'El lote origen y destino no pueden ser iguales.'));
                break;
            }
        } elseif ($tipo_movimiento === 'POTRERO') {
            if ($potrero_origen <= 0 || $potrero_destino <= 0) {
                echo json_encode(array('ok' => false, 'error' => 'Debe indicar potrero origen y destino.'));
                break;
            }
            if ($potrero_origen === $potrero_destino) {
                echo json_encode(array('ok' => false, 'error' => 'El potrero origen y destino no pueden ser iguales.'));
                break;
            }
        } elseif ($tipo_movimiento === 'AMBOS') {
            if ($lote_origen <= 0 || $lote_destino <= 0 || $potrero_origen <= 0 || $potrero_destino <= 0) {
                echo json_encode(array('ok' => false, 'error' => 'Debe indicar lote y potrero (origen y destino).'));
                break;
            }
            if ($lote_origen === $lote_destino && $potrero_origen === $potrero_destino) {
                echo json_encode(array('ok' => false, 'error' => 'El origen y destino no pueden ser iguales.'));
                break;
            }
        }

        $ids_safe = array();
        foreach ($ids_array as $aid) {
            $aid = intval($aid);
            if ($aid > 0) $ids_safe[] = $aid;
        }
        if (count($ids_safe) === 0) {
            echo json_encode(array('ok' => false, 'error' => 'No hay animales validos en la seleccion.'));
            break;
        }
        $ids_str = implode(',', $ids_safe);

        $animales = Manimale::find_by_sql("
            SELECT id, codigo_rgb_id_animal, nombre, sexo, f_nacimiento,
                   mlotes_id, mpotreros_id, categoria_ingreso_id
            FROM manimales
            WHERE id IN ({$ids_str})
              AND status = 1
              AND mfundos_id = {$mfundos_id}
              AND estatus_animal NOT IN (3, 5)
        ");

        if (!is_array($animales) || count($animales) === 0) {
            echo json_encode(array('ok' => false, 'error' => 'Los animales seleccionados no existen o no estan activos.'));
            break;
        }

        /* Crear movimiento principal y su detalle en una sola transacción:
           si falla cualquier inserción no queda un movimiento sin animales. */
        $conn = ActiveRecord\ConnectionManager::get_connection();
        $mov_id = 0;
        $contador = 0;

        try {
            $conn->transaction();

            $mov = new Mmovimientorebano();
            $mov->mfundos_id           = $mfundos_id;
            $mov->tipo_movimiento      = $tipo_movimiento;
            $mov->mlotes_origen_id     = $lote_origen > 0 ? $lote_origen : null;
            $mov->mlotes_destino_id    = $lote_destino > 0 ? $lote_destino : null;
            $mov->mpotreros_origen_id  = $potrero_origen > 0 ? $potrero_origen : null;
            $mov->mpotreros_destino_id = $potrero_destino > 0 ? $potrero_destino : null;
            $mov->fecha_movimiento     = date('Y-m-d H:i:s');
            $mov->motivo               = $motivo;
            $mov->observaciones        = $observaciones;
            $mov->total_animales       = count($animales);
            $mov->user_create          = $usuario_id;
            $mov->created              = date('Y-m-d H:i:s');

            if (!$mov->save()) {
                throw new Exception('No se pudo crear el movimiento principal.');
            }

            $mov_id = intval($mov->id);
            if ($mov_id <= 0) {
                throw new Exception('No se pudo obtener el identificador del movimiento creado.');
            }

            foreach ($animales as $a) {
                $fecha_nac = $a->f_nacimiento ? (string)$a->f_nacimiento : null;
                $categoria_nueva_id = null;
                if ($fecha_nac) {
                    $categoria_nueva_id = calcularCategoriaActual($a->sexo, $fecha_nac, $mfundos_id, $t_ganaderia);
                }

                $det = new Mmovimientorebanodetalle();
                $det->mmovimientos_rebano_id = $mov_id;
                $det->manimales_id           = $a->id;
                $det->codigo_rgb_id_animal   = $a->codigo_rgb_id_animal;
                $det->lote_anterior_id       = $a->mlotes_id;
                $det->lote_nuevo_id          = $lote_destino > 0 ? $lote_destino : null;
                $det->potrero_anterior_id    = $a->mpotreros_id;
                $det->potrero_nuevo_id       = $potrero_destino > 0 ? $potrero_destino : null;
                $det->categoria_anterior_id  = $a->categoria_ingreso_id;
                $det->categoria_nueva_id     = $categoria_nueva_id;
                $det->created                = date('Y-m-d H:i:s');
                if (!$det->save()) {
                    throw new Exception('No se pudo guardar el detalle del movimiento para el animal ' . $a->codigo_rgb_id_animal . '.');
                }

                $animal_upd = Manimale::find($a->id);
                if (!$animal_upd) {
                    throw new Exception('No se encontro el animal ' . $a->codigo_rgb_id_animal . ' para actualizar su ubicacion.');
                }
                if ($tipo_movimiento === 'LOTE' || $tipo_movimiento === 'AMBOS') {
                    if ($lote_destino > 0) $animal_upd->mlotes_id = $lote_destino;
                }
                if ($tipo_movimiento === 'POTRERO' || $tipo_movimiento === 'AMBOS') {
                    $animal_upd->mpotreros_id = $potrero_destino > 0 ? $potrero_destino : null;
                }
                if ($categoria_nueva_id) {
                    $animal_upd->categoria_ingreso_id = $categoria_nueva_id;
                }
                $animal_upd->user_modify = $usuario_id;
                $animal_upd->updated     = $hoy;
                if (!$animal_upd->save()) {
                    throw new Exception('No se pudo actualizar la ubicacion del animal ' . $a->codigo_rgb_id_animal . '.');
                }

                $contador++;
            }

            $conn->commit();
        } catch (Throwable $e) {
            try { $conn->rollback(); } catch (Throwable $e2) { /* sin transaccion activa */ }
            error_log('movreb_controller crear_movimiento: ' . $e->getMessage());
            echo json_encode(array('ok' => false, 'error' => $e->getMessage()));
            break;
        }

        echo json_encode(array(
            'ok'      => true,
            'mensaje' => 'Movimiento registrado. Se movieron ' . $contador . ' animales.',
            'id'      => $mov_id
        ));
        break;

    /* ============================================================
       REVERTIR MOVIMIENTO
       ============================================================ */
    case 'revertir_movimiento':

        session_start();
        $usuario_id  = $_SESSION['idusuariox'];
        $mfundos_id  = $_SESSION['mfundos_id'];
        $t_ganaderia = $_SESSION['tipo_ganaderia'];
        $rol         = $_SESSION['rolx'];
        $hoy         = date('d-m-Y');

        if (!in_array(intval($rol), array(1, 2, 4))) {
            echo json_encode(array('ok' => false, 'error' => 'Solo propietarios y supervisores pueden revertir.'));
            break;
        }
        if (trim($motivo_reversion) === '' || strlen(trim($motivo_reversion)) < 10) {
            echo json_encode(array('ok' => false, 'error' => 'El motivo de reversion debe tener al menos 10 caracteres.'));
            break;
        }
        if ($id <= 0) {
            echo json_encode(array('ok' => false, 'error' => 'ID de movimiento invalido.'));
            break;
        }

        $mov = Mmovimientorebano::find($id);
        if (!$mov || $mov->mfundos_id != $mfundos_id) {
            echo json_encode(array('ok' => false, 'error' => 'Movimiento no encontrado.'));
            break;
        }
        if ($mov->revertido) {
            echo json_encode(array('ok' => false, 'error' => 'Este movimiento ya fue revertido.'));
            break;
        }

        $detalles = Mmovimientorebanodetalle::find('all', array(
            'conditions' => array('mmovimientos_rebano_id = ?', $id)
        ));

        if (!$detalles || count($detalles) === 0) {
            echo json_encode(array('ok' => false, 'error' => 'El movimiento no tiene detalle.'));
            break;
        }

        $revertidos = 0;
        foreach ($detalles as $d) {
            $animal = Manimale::find($d->manimales_id);
            if (!$animal) continue;

            if ($mov->tipo_movimiento === 'LOTE' || $mov->tipo_movimiento === 'AMBOS') {
                $animal->mlotes_id = $d->lote_anterior_id;
            }
            if ($mov->tipo_movimiento === 'POTRERO' || $mov->tipo_movimiento === 'AMBOS') {
                $animal->mpotreros_id = $d->potrero_anterior_id;
            }

            $fecha_nac = $animal->f_nacimiento ? (string)$animal->f_nacimiento : null;
            if ($fecha_nac) {
                $cat = calcularCategoriaActual($animal->sexo, $fecha_nac, $mfundos_id, $t_ganaderia);
                if ($cat) $animal->categoria_ingreso_id = $cat;
            }

            $animal->user_modify = $usuario_id;
            $animal->updated     = $hoy;
            $animal->save();
            $revertidos++;
        }

        /* Auditoria de reversion — usar SQL directo para evitar el bug de boolean */
        $motivo_esc = str_replace("'", "''", $motivo_reversion);
        Mmovimientorebano::find_by_sql("
            UPDATE mmovimientos_rebano
            SET revertido = TRUE,
                fecha_reversion = NOW(),
                motivo_reversion = '{$motivo_esc}',
                user_reversion = " . intval($usuario_id) . "
            WHERE id = " . intval($id)
        );

        echo json_encode(array(
            'ok'      => true,
            'mensaje' => 'Movimiento revertido. Se devolvieron ' . $revertidos . ' animales a su origen.'
        ));
        break;

    /* ============================================================
       BUSCAR DETALLE
       ============================================================ */
    case 'search_movimiento':

        session_start();
        $mfundos_id = $_SESSION['mfundos_id'];

        if ($id <= 0) {
            echo json_encode(array('ok' => false, 'error' => 'ID invalido.'));
            break;
        }

        $mov = Mmovimientorebano::find($id);
        if (!$mov || $mov->mfundos_id != $mfundos_id) {
            echo json_encode(array('ok' => false, 'error' => 'Movimiento no encontrado.'));
            break;
        }

        $nombre_lote_origen = '';
        if ($mov->mlotes_origen_id) {
            $l = Mlote::find($mov->mlotes_origen_id);
            if ($l) $nombre_lote_origen = $l->nombre;
        }
        $nombre_lote_destino = '';
        if ($mov->mlotes_destino_id) {
            $l = Mlote::find($mov->mlotes_destino_id);
            if ($l) $nombre_lote_destino = $l->nombre;
        }
        $nombre_potrero_origen = '';
        if ($mov->mpotreros_origen_id) {
            $p = Mpotrero::find($mov->mpotreros_origen_id);
            if ($p) $nombre_potrero_origen = $p->nombre;
        }
        $nombre_potrero_destino = '';
        if ($mov->mpotreros_destino_id) {
            $p = Mpotrero::find($mov->mpotreros_destino_id);
            if ($p) $nombre_potrero_destino = $p->nombre;
        }

        $detalles = Mmovimientorebanodetalle::find_by_sql("
            SELECT
                d.id, d.manimales_id, d.codigo_rgb_id_animal,
                d.lote_anterior_id, d.lote_nuevo_id,
                d.potrero_anterior_id, d.potrero_nuevo_id,
                d.categoria_anterior_id, d.categoria_nueva_id,
                a.nombre AS nombre_animal,
                cat_ant.nombre AS categoria_anterior_nombre,
                cat_new.nombre AS categoria_nueva_nombre,
                a.sexo
            FROM mmovimientos_rebano_detalle d
            LEFT JOIN manimales a ON a.id = d.manimales_id
            LEFT JOIN mcategorias cat_ant ON cat_ant.id = d.categoria_anterior_id
            LEFT JOIN mcategorias cat_new ON cat_new.id = d.categoria_nueva_id
            WHERE d.mmovimientos_rebano_id = {$id}
            ORDER BY a.nombre ASC
        ");

        $detalle_out = array();
        if (is_array($detalles)) {
            foreach ($detalles as $d) {
                $detalle_out[] = array(
                    'id'                        => $d->id,
                    'manimales_id'              => $d->manimales_id,
                    'codigo_rgb_id_animal'      => $d->codigo_rgb_id_animal,
                    'nombre_animal'             => $d->nombre_animal,
                    'sexo'                      => intval($d->sexo) === 1 ? 'HEMBRA' : (intval($d->sexo) === 2 ? 'MACHO' : '-'),
                    'categoria_anterior_nombre' => $d->categoria_anterior_nombre ? $d->categoria_anterior_nombre : 'Sin categoria',
                    'categoria_nueva_nombre'    => $d->categoria_nueva_nombre    ? $d->categoria_nueva_nombre    : 'Sin cambio'
                );
            }
        }

        echo json_encode(array(
            'ok'   => true,
            'mov'  => array(
                'id'                => $mov->id,
                'tipo_movimiento'   => $mov->tipo_movimiento,
                'fecha_movimiento'  => movreb_fecha_texto($mov->fecha_movimiento),
                'motivo'            => $mov->motivo,
                'observaciones'     => $mov->observaciones,
                'total_animales'    => intval($mov->total_animales),
                'revertido'         => (bool)$mov->revertido,
                'fecha_reversion'   => movreb_fecha_texto($mov->fecha_reversion),
                'motivo_reversion'  => $mov->motivo_reversion,
                'lote_origen_nombre'     => $nombre_lote_origen,
                'lote_destino_nombre'    => $nombre_lote_destino,
                'potrero_origen_nombre'  => $nombre_potrero_origen,
                'potrero_destino_nombre' => $nombre_potrero_destino
            ),
            'detalle' => $detalle_out
        ));
        break;

    /* ============================================================
       LISTAR ANIMALES POR ORIGEN
       ============================================================ */
    case 'get_animales_por_origen':

        session_start();
        $mfundos_id = $_SESSION['mfundos_id'];

        $where = "WHERE a.status = 1 AND a.mfundos_id = {$mfundos_id} AND a.estatus_animal NOT IN (3, 5)";

        if ($tipo_movimiento === 'LOTE' && $lote_origen > 0) {
            $where .= " AND a.mlotes_id = {$lote_origen}";
        } elseif ($tipo_movimiento === 'POTRERO' && $potrero_origen > 0) {
            $where .= " AND a.mpotreros_id = {$potrero_origen}";
        } elseif ($tipo_movimiento === 'AMBOS') {
            if ($lote_origen > 0) $where .= " AND a.mlotes_id = {$lote_origen}";
            if ($potrero_origen > 0) $where .= " AND a.mpotreros_id = {$potrero_origen}";
        } else {
            echo json_encode(array('ok' => true, 'animales' => array()));
            break;
        }

        $data = Manimale::find_by_sql("
            SELECT a.id, a.codigo_rgb_id_animal, a.nombre, a.sexo, a.nro_arete,
                   a.f_nacimiento, a.categoria_ingreso_id,
                   c.nombre AS categoria_nombre,
                   l.nombre AS lote_nombre
            FROM manimales a
            LEFT JOIN mcategorias c ON c.id = a.categoria_ingreso_id
            LEFT JOIN mlotes l ON l.id = a.mlotes_id
            {$where}
            ORDER BY a.nombre ASC
        ");

        $animales_out = array();
        if (is_array($data)) {
            foreach ($data as $rs) {
                $edad_txt = '';
                if ($rs->f_nacimiento) {
                    $fn = new DateTime((string)$rs->f_nacimiento);
                    $hoy_dt = new DateTime();
                    $diff = $hoy_dt->diff($fn);
                    $edad_txt = $diff->y . 'a ' . $diff->m . 'm';
                }
                $animales_out[] = array(
                    'id'        => $rs->id,
                    'codigo'    => $rs->codigo_rgb_id_animal,
                    'nombre'    => $rs->nombre,
                    'nro_arete' => $rs->nro_arete,
                    'sexo'      => intval($rs->sexo) === 1 ? 'HEMBRA' : (intval($rs->sexo) === 2 ? 'MACHO' : '-'),
                    'categoria' => $rs->categoria_nombre ? $rs->categoria_nombre : 'Sin categoria',
                    'lote'      => $rs->lote_nombre ? $rs->lote_nombre : '-',
                    'edad'      => $edad_txt
                );
            }
        }

        echo json_encode(array('ok' => true, 'animales' => $animales_out));
        break;

    /* ============================================================
       LISTAR MOVIMIENTOS
       ============================================================ */
    case 'listar_movimientos':

        session_start();
        $mfundos_id = $_SESSION['mfundos_id'];

        $where = "WHERE m.mfundos_id = {$mfundos_id}";
        if ($fecha_desde !== '') {
            $fd = str_replace('/', '-', $fecha_desde);
            $fd = date('Y-m-d', strtotime($fd));
            $where .= " AND DATE(m.fecha_movimiento) >= '{$fd}'";
        }
        if ($fecha_hasta !== '') {
            $fh = str_replace('/', '-', $fecha_hasta);
            $fh = date('Y-m-d', strtotime($fh));
            $where .= " AND DATE(m.fecha_movimiento) <= '{$fh}'";
        }

        $data = Mmovimientorebano::find_by_sql("
            SELECT m.id, m.tipo_movimiento, m.fecha_movimiento, m.motivo, m.observaciones,
                   m.total_animales, m.revertido,
                   lo.nombre AS lote_origen, ld.nombre AS lote_destino,
                   po.nombre AS potrero_origen, pd.nombre AS potrero_destino
            FROM mmovimientos_rebano m
            LEFT JOIN mlotes lo ON lo.id = m.mlotes_origen_id
            LEFT JOIN mlotes ld ON ld.id = m.mlotes_destino_id
            LEFT JOIN mpotreros po ON po.id = m.mpotreros_origen_id
            LEFT JOIN mpotreros pd ON pd.id = m.mpotreros_destino_id
            {$where}
            ORDER BY m.fecha_movimiento DESC
            LIMIT 500
        ");

        echo json_encode(is_array($data) ? $data : array());
        break;
}

} catch (Throwable $e) {
    header('Content-Type: application/json');
    echo json_encode(array(
        'ok'    => false,
        'error' => 'Error interno: ' . $e->getMessage() . ' (línea ' . $e->getLine() . ')'
    ));
    exit;
}
?>