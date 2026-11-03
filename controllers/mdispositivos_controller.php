<?php
require_once '../models/UsuarioDispositivoOffline.php';
require_once '../models/Musuario.php';
require_once '../models/Mfundo.php';
date_default_timezone_set('America/Caracas');

@$action = ($_POST['action']);
@$dispositivo_id = ($_POST['dispositivo_id']);
@$filtro = isset($_POST['filtro']) ? $_POST['filtro'] : 'activos'; // activos | historico

switch ($action) {

    #*******************************************************************************
        case 'listar':
        @session_start();
        $usuario_id = $_SESSION['idusuariox'];
        $mfundos_id = $_SESSION['mfundos_id'];
        $rol = $_SESSION['rolx'];

        if (!$usuario_id) {
            echo json_encode(array(
                'resultado' => 'error',
                'mensaje' => 'Sesión no válida.',
                'dispositivos' => array()
            ));
            die();
        }

        if ($rol == 3) {
            echo json_encode(array(
                'resultado' => 'error',
                'mensaje' => 'No tiene permisos para ver esta información.',
                'dispositivos' => array()
            ));
            die();
        }

        $es_admin = in_array($rol, array(1, 4));
        $status_filtro = ($filtro === 'historico') ? 0 : 1;

        /* -------------------------------------------------------------
         * QUERY SIMPLE — condiciones como STRING directo (sin array de params).
         * Esto evita cualquier problema del parser del ORM.
         * ------------------------------------------------------------- */
        $conditions = 'status = ' . (int) $status_filtro;
        if (!$es_admin) {
            $conditions .= ' AND mfundos_id = ' . (int) $mfundos_id;
        }

        $order = ($status_filtro === 1)
            ? 'updated DESC, id DESC'
            : 'desvinculado_en DESC, id DESC';

        // Debug: cuántas filas crudas devuelve el ORM
        $dispositivos_raw = UsuarioDispositivoOffline::all(array(
            'conditions' => $conditions,
            'order' => $order
        ));

        $cacheUsuarios = array();
        $cacheFundos   = array();

        $filas = array();
        foreach ($dispositivos_raw as $d) {

            $uid = (int) $d->musuarios_id;
            if (!isset($cacheUsuarios[$uid])) {
                try { $cacheUsuarios[$uid] = Musuario::find($uid); }
                catch (\ActiveRecord\RecordNotFound $e) { $cacheUsuarios[$uid] = null; }
            }
            $usuario = $cacheUsuarios[$uid];

            $nombre_fundo = '';
            if ($es_admin && !empty($d->mfundos_id)) {
                $fid = (int) $d->mfundos_id;
                if (!isset($cacheFundos[$fid])) {
                    try {
                        $f = Mfundo::find($fid);
                        $cacheFundos[$fid] = $f ? $f->nombre : '';
                    } catch (\ActiveRecord\RecordNotFound $e) {
                        $cacheFundos[$fid] = '';
                    }
                }
                $nombre_fundo = $cacheFundos[$fid];
            }

            if ($usuario) {
                $usuario_nombre   = $usuario->name ? $usuario->name : $usuario->username;
                $usuario_username = $usuario->username;
                $usuario_status   = (int) $usuario->status;
            } else {
                $usuario_nombre   = '(usuario huérfano)';
                $usuario_username = '';
                $usuario_status   = -1;
            }

            $filas[] = array(
                'id' => (int) $d->id,
                'dispositivo_id' => (string) $d->dispositivo_id,
                'nombre_dispositivo' => $d->nombre_dispositivo ? $d->nombre_dispositivo : '(sin nombre)',
                'musuarios_id' => $uid,
                'usuario_nombre'   => $usuario_nombre,
                'usuario_username' => $usuario_username,
                'usuario_status'   => $usuario_status,
                'fundo_id'     => (int) $d->mfundos_id,
                'fundo_nombre' => $nombre_fundo,
                'modo_offline' => $d->modo_offline ? $d->modo_offline : 'gestion',
                'status' => (int) $d->status,
                'estado_etiqueta' => ((int) $d->status === 1) ? 'Activo' : 'Histórico',
                'activado_en' => $d->activado_en ? date('d-m-Y H:i', strtotime($d->activado_en)) : '—',
                'ultima_sincronizacion' => $d->ultima_sincronizacion ? date('d-m-Y H:i', strtotime($d->ultima_sincronizacion)) : '—',
                'desvinculado_en' => $d->desvinculado_en ? date('d-m-Y H:i', strtotime($d->desvinculado_en)) : '—',
                // Marcas crudas (para agrupar por fecha real, no por texto)
                '_ts_activado' => $d->activado_en ? strtotime($d->activado_en) : 0,
                '_ts_ultima'   => $d->ultima_sincronizacion ? strtotime($d->ultima_sincronizacion) : 0,
            );
        }

        /* -------------------------------------------------------------
         * AGRUPADO POR EQUIPO (multifundo)
         * -------------------------------------------------------------
         * Un mismo teléfono puede estar vinculado a VARIOS fundos del
         * mismo usuario (una fila por fundo en la tabla). Para la
         * pantalla web eso es UN dispositivo con varios fundos, así que
         * se agrupa por (dispositivo_id, usuario) y se listan sus fundos.
         * Se sigue devolviendo la lista plana en 'filas' para el histórico.
         */
        $grupos = array();
        foreach ($filas as $f) {
            $clave = $f['dispositivo_id'] . '|' . $f['musuarios_id'];

            if (!isset($grupos[$clave])) {
                $grupos[$clave] = array(
                    'dispositivo_id' => $f['dispositivo_id'],
                    'nombre_dispositivo' => $f['nombre_dispositivo'],
                    'musuarios_id' => $f['musuarios_id'],
                    'usuario_nombre' => $f['usuario_nombre'],
                    'usuario_username' => $f['usuario_username'],
                    'usuario_status' => $f['usuario_status'],
                    'status' => $f['status'],
                    'estado_etiqueta' => $f['estado_etiqueta'],
                    'fundos' => array(),
                    '_ts_activado' => 0,
                    '_ts_ultima' => 0,
                    '_ts_desvinculado' => 0,
                );
            }

            $grupos[$clave]['fundos'][] = array(
                'registro_id' => $f['id'],
                'fundo_id' => $f['fundo_id'],
                'fundo_nombre' => $f['fundo_nombre'],
                'modo_offline' => $f['modo_offline'],
                'status' => $f['status'],
                'activado_en' => $f['activado_en'],
                'ultima_sincronizacion' => $f['ultima_sincronizacion'],
            );

            $grupos[$clave]['_ts_activado'] = max($grupos[$clave]['_ts_activado'], (int) $f['_ts_activado']);
            $grupos[$clave]['_ts_ultima']   = max($grupos[$clave]['_ts_ultima'], (int) $f['_ts_ultima']);
        }

        $agrupados = array();
        foreach ($grupos as $g) {
            $modos = array_unique(array_map(function ($x) { return $x['modo_offline']; }, $g['fundos']));
            $nombres = array_values(array_filter(array_map(function ($x) { return $x['fundo_nombre']; }, $g['fundos'])));

            $g['n_fundos'] = count($g['fundos']);
            $g['modo_offline'] = (count($modos) === 1) ? $modos[0] : 'mixto';
            // Compatibilidad: la columna "Fundo" de la tabla sigue existiendo.
            $g['fundo_nombre'] = $nombres ? implode(', ', array_unique($nombres)) : '';
            $g['activado_en'] = $g['_ts_activado'] ? date('d-m-Y H:i', $g['_ts_activado']) : '—';
            $g['ultima_sincronizacion'] = $g['_ts_ultima'] ? date('d-m-Y H:i', $g['_ts_ultima']) : '—';
            // Marca numérica para que la tabla pueda ordenar por fecha real
            // (las fechas mostradas son texto 'd-m-Y H:i').
            $g['ultima_sincronizacion_ts'] = (int) $g['_ts_ultima'];
            $g['desvinculado_en'] = '—';

            unset($g['_ts_activado'], $g['_ts_ultima'], $g['_ts_desvinculado']);
            $agrupados[] = $g;
        }

        echo json_encode(array(
            'resultado'    => 'ok',
            'dispositivos' => $agrupados,  // un equipo por fila, con sus fundos
            'filas'        => $filas,      // lista plana (una fila por fundo): histórico
            'es_admin'     => $es_admin,
            'filtro'       => $filtro,
        ));
        break;
    #*******************************************************************************
    case 'desvincular':
        @session_start();
        $usuario_id = $_SESSION['idusuariox'];
        $mfundos_id = $_SESSION['mfundos_id'];
        $rol = $_SESSION['rolx'];

        if (!$usuario_id) {
            echo json_encode(array('resultado' => 'error', 'mensaje' => 'Sesión no válida.'));
            die();
        }

        if ($rol == 3) {
            echo json_encode(array('resultado' => 'error', 'mensaje' => 'No tiene permisos para realizar esta acción.'));
            die();
        }

        if (empty($dispositivo_id)) {
            echo json_encode(array('resultado' => 'error', 'mensaje' => 'Debe indicar el dispositivo a desvincular.'));
            die();
        }

        // MULTIFUNDO: un mismo equipo puede tener una fila por fundo. Se
        // desvinculan TODAS las que el usuario actual puede administrar
        // (las propias, todas si es admin, o las de su fundo si es productor),
        // porque dejar la mitad activas sería un estado inconsistente.
        $filas_dispositivo = UsuarioDispositivoOffline::all(array(
            'conditions' => array('dispositivo_id = ? AND status = 1', $dispositivo_id)
        ));

        if (!$filas_dispositivo) {
            echo json_encode(array('resultado' => 'error', 'mensaje' => 'El dispositivo no existe o ya está desvinculado.'));
            die();
        }

        $esAdminGlobal = in_array($rol, array(1, 4));
        $procesados = 0;
        $fundos_afectados = array();

        foreach ($filas_dispositivo as $dispositivo) {
            $esPropio = ((int) $dispositivo->musuarios_id === (int) $usuario_id);
            $esAdminDelFundo = ($rol == 2) && ((int) $dispositivo->mfundos_id === (int) $mfundos_id);

            if (!$esPropio && !$esAdminGlobal && !$esAdminDelFundo) {
                continue; // sin permiso sobre esa fila
            }

            $dispositivo->status = 0;
            $dispositivo->desvinculado_en = date('Y-m-d H:i:s');
            $dispositivo->desvinculado_por = $usuario_id;
            $dispositivo->motivo_desvinculacion = 'Desvinculado desde la pantalla web';

            if ($dispositivo->save()) {
                $procesados++;
                $fundos_afectados[] = (int) $dispositivo->mfundos_id;
            }
        }

        if ($procesados === 0) {
            echo json_encode(array('resultado' => 'error', 'mensaje' => 'No tiene permisos para desvincular ese dispositivo.'));
            die();
        }

        $mensaje = 'Dispositivo desvinculado exitosamente';
        if (count($fundos_afectados) > 1) {
            $mensaje .= ' en sus ' . count($fundos_afectados) . ' fundos';
        }
        $mensaje .= '. El usuario deberá volver a activar el modo offline en ese equipo.';

        echo json_encode(array(
            'resultado' => 'ok',
            'mensaje' => $mensaje,
            'fundo_ids' => $fundos_afectados
        ));
        break;

    #*******************************************************************************
    case 'resumen_sync':
        /* -------------------------------------------------------------
         * RESUMEN DE SINCRONIZACIONES (modo offline)
         * -------------------------------------------------------------
         * Una fila por EQUIPO (dispositivo + usuario) con cuántas veces
         * sincronizó, cuántas veces falló y dónde quedó el último fallo.
         * Los datos salen de `sync_offline_log`, que escribe la API en
         * cada pull/push (ver api/lib/SyncLogger.php).
         * ------------------------------------------------------------- */
        @session_start();
        $usuario_id = (int) $_SESSION['idusuariox'];
        $mfundos_id = (int) $_SESSION['mfundos_id'];
        $rol = (int) $_SESSION['rolx'];

        if (!$usuario_id) {
            echo json_encode(array('resultado' => 'error', 'mensaje' => 'Sesión no válida.', 'dispositivos' => array()));
            die();
        }

        if ($rol == 3) {
            echo json_encode(array('resultado' => 'error', 'mensaje' => 'No tiene permisos para ver esta información.', 'dispositivos' => array()));
            die();
        }

        $es_admin = in_array($rol, array(1, 4));
        $dias = isset($_POST['dias']) ? (int) $_POST['dias'] : 30;
        $solo_fallos = (isset($_POST['solo_fallos']) && $_POST['solo_fallos'] === '1');

        $condiciones = array();
        $parametros = array();
        if (!$es_admin) {
            $condiciones[] = 'l.mfundos_id = ?';
            $parametros[] = $mfundos_id;
        }
        if ($dias > 0) {
            $condiciones[] = 'l.created >= ?';
            $parametros[] = date('Y-m-d H:i:s', strtotime('-' . $dias . ' days'));
        }
        $where = $condiciones ? ('WHERE ' . implode(' AND ', $condiciones)) : '';

        $sql = "WITH base AS (
                    SELECT l.id, l.dispositivo_id, l.nombre_dispositivo, l.musuarios_id,
                           l.mfundos_id, l.modo_offline, l.accion, l.modulo, l.resultado,
                           l.total, l.exitos, l.conflictos, l.errores, l.mensaje, l.detalle, l.created
                      FROM sync_offline_log l
                      $where
                ),
                agregado AS (
                    SELECT b.dispositivo_id,
                           b.musuarios_id,
                           MAX(b.nombre_dispositivo) AS nombre_dispositivo,
                           COUNT(*)::int AS total,
                           COUNT(*) FILTER (WHERE b.resultado = 'ok')::int AS ok,
                           COUNT(*) FILTER (WHERE b.resultado = 'parcial')::int AS parcial,
                           COUNT(*) FILTER (WHERE b.resultado = 'error')::int AS error,
                           COALESCE(SUM(b.exitos), 0)::int AS exitos,
                           COALESCE(SUM(b.errores), 0)::int AS items_error,
                           COALESCE(SUM(b.conflictos), 0)::int AS conflictos,
                           MAX(b.created) AS ultima,
                           MAX(b.created) FILTER (WHERE b.resultado <> 'ok') AS ultimo_fallo,
                           STRING_AGG(DISTINCT f.nombre, ', ') AS fundos_nombres,
                           STRING_AGG(DISTINCT b.modo_offline, ', ') AS modos
                      FROM base b
                      LEFT JOIN mfundos f ON f.id = b.mfundos_id
                     GROUP BY b.dispositivo_id, b.musuarios_id
                )
                SELECT a.*,
                       u.accion  AS fallo_accion,
                       u.modulo  AS fallo_modulo,
                       u.resultado AS fallo_resultado,
                       u.mensaje AS fallo_mensaje,
                       u.detalle AS fallo_detalle,
                       u.created AS fallo_fecha
                  FROM agregado a
                  LEFT JOIN LATERAL (
                        SELECT b2.accion, b2.modulo, b2.resultado, b2.mensaje, b2.detalle, b2.created
                          FROM base b2
                         WHERE b2.resultado <> 'ok'
                           AND b2.musuarios_id = a.musuarios_id
                           AND b2.dispositivo_id IS NOT DISTINCT FROM a.dispositivo_id
                         ORDER BY b2.created DESC
                         LIMIT 1
                  ) u ON true
                 ORDER BY a.ultimo_fallo DESC NULLS LAST, a.ultima DESC";

        require_once __DIR__ . '/../api/lib/SyncLogger.php';

        $cacheUsuarios = array();
        $filas = array();
        $totales = array('eventos' => 0, 'fallos' => 0, 'items_error' => 0, 'conflictos' => 0, 'equipos' => 0, 'equipos_con_fallo' => 0);

        try {
            $registros = \Musuario::connection()->query($sql, $parametros)->fetchAll();
        } catch (\Throwable $e) {
            echo json_encode(array(
                'resultado' => 'error',
                'mensaje' => 'No se pudo leer el registro de sincronizaciones. Ejecute scripts/migracion_sync_offline_log.sql.',
                'detalle' => $e->getMessage(),
                'dispositivos' => array()
            ));
            die();
        }

        foreach ($registros as $r) {
            $uid = (int) $r['musuarios_id'];
            if (!isset($cacheUsuarios[$uid])) {
                $cacheUsuarios[$uid] = null;
                try {
                    $u = Musuario::find($uid);
                    if ($u) {
                        $cacheUsuarios[$uid] = $u->name ? $u->name : $u->username;
                    }
                } catch (\ActiveRecord\RecordNotFound $e) {
                    $cacheUsuarios[$uid] = null;
                }
            }

            $fallos = (int) $r['parcial'] + (int) $r['error'];

            // "Dónde falló": se prefiere el motivo puntual del primer
            // registro rechazado de esa tanda; si no hay detalle, queda el
            // mensaje resumen del evento.
            $mensajeFallo = $r['fallo_mensaje'] ? $r['fallo_mensaje'] : '';
            $referenciaFallo = '';
            $fallosDetalle = 0;
            if (!empty($r['fallo_detalle'])) {
                $listaFallos = json_decode($r['fallo_detalle'], true);
                if (is_array($listaFallos) && $listaFallos) {
                    $fallosDetalle = count($listaFallos);
                    $primero = $listaFallos[0];
                    if (!empty($primero['referencia'])) {
                        $referenciaFallo = (string) $primero['referencia'];
                    }
                    if (!empty($primero['mensaje'])) {
                        $mensajeFallo = (string) $primero['mensaje'];
                    }
                }
            }
            if ($fallosDetalle > 1) {
                $mensajeFallo .= ' (+' . ($fallosDetalle - 1) . ' más)';
            }

            $totales['equipos']++;
            $totales['eventos'] += (int) $r['total'];
            $totales['fallos'] += $fallos;
            $totales['items_error'] += (int) $r['items_error'];
            $totales['conflictos'] += (int) $r['conflictos'];
            if ($fallos > 0) {
                $totales['equipos_con_fallo']++;
            }

            if ($solo_fallos && $fallos === 0) {
                continue;
            }

            $filas[] = array(
                'dispositivo_id'     => $r['dispositivo_id'] ? (string) $r['dispositivo_id'] : '',
                'nombre_dispositivo' => $r['nombre_dispositivo'] ? $r['nombre_dispositivo'] : '(equipo sin identificar)',
                'musuarios_id'       => $uid,
                'usuario_nombre'     => $cacheUsuarios[$uid] ? $cacheUsuarios[$uid] : '(usuario huérfano)',
                'fundos_nombres'     => $r['fundos_nombres'] ? $r['fundos_nombres'] : '',
                'modo_offline'       => $r['modos'] ? $r['modos'] : '',
                'total'              => (int) $r['total'],
                'ok'                 => (int) $r['ok'],
                'parcial'            => (int) $r['parcial'],
                'error'              => (int) $r['error'],
                'fallos'             => $fallos,
                'exitos'             => (int) $r['exitos'],
                'items_error'        => (int) $r['items_error'],
                'conflictos'         => (int) $r['conflictos'],
                'ultima'             => $r['ultima'] ? date('d-m-Y H:i', strtotime($r['ultima'])) : '—',
                'ultima_ts'          => $r['ultima'] ? strtotime($r['ultima']) : 0,
                'ultimo_fallo'       => $r['ultimo_fallo'] ? date('d-m-Y H:i', strtotime($r['ultimo_fallo'])) : '—',
                'ultimo_fallo_ts'    => $r['ultimo_fallo'] ? strtotime($r['ultimo_fallo']) : 0,
                'fallo_accion'       => $r['fallo_accion'] ? $r['fallo_accion'] : '',
                'fallo_modulo'       => $r['fallo_modulo'] ? $r['fallo_modulo'] : '',
                'fallo_modulo_etiqueta' => $r['fallo_modulo'] ? \Api\SyncLogger::etiquetaModulo($r['fallo_modulo']) : '',
                'fallo_mensaje'      => $mensajeFallo,
                'fallo_mensaje_lote' => $r['fallo_mensaje'] ? $r['fallo_mensaje'] : '',
                'fallo_referencia'   => $referenciaFallo,
                'fallo_fecha'        => $r['fallo_fecha'] ? date('d-m-Y H:i', strtotime($r['fallo_fecha'])) : '',
            );
        }

        echo json_encode(array(
            'resultado'    => 'ok',
            'dispositivos' => $filas,
            'totales'      => $totales,
            'es_admin'     => $es_admin,
            'dias'         => $dias,
        ));
        break;

    #*******************************************************************************
    case 'detalle_sync':
        /* -------------------------------------------------------------
         * DETALLE (línea de tiempo) de las sincronizaciones de UN equipo.
         * Cada evento trae sus contadores y la lista de registros que el
         * servidor rechazó, con módulo y motivo.
         * ------------------------------------------------------------- */
        @session_start();
        $usuario_id = (int) $_SESSION['idusuariox'];
        $mfundos_id = (int) $_SESSION['mfundos_id'];
        $rol = (int) $_SESSION['rolx'];

        if (!$usuario_id) {
            echo json_encode(array('resultado' => 'error', 'mensaje' => 'Sesión no válida.', 'eventos' => array()));
            die();
        }

        if ($rol == 3) {
            echo json_encode(array('resultado' => 'error', 'mensaje' => 'No tiene permisos para ver esta información.', 'eventos' => array()));
            die();
        }

        $es_admin = in_array($rol, array(1, 4));
        $dispositivo_id = isset($_POST['dispositivo_id']) ? trim((string) $_POST['dispositivo_id']) : '';
        $musuario_filtro = isset($_POST['musuarios_id']) ? (int) $_POST['musuarios_id'] : 0;
        $dias = isset($_POST['dias']) ? (int) $_POST['dias'] : 30;

        if ($dispositivo_id !== '' && !preg_match('/^[0-9a-fA-F-]{36}$/', $dispositivo_id)) {
            echo json_encode(array('resultado' => 'error', 'mensaje' => 'Identificador de dispositivo inválido.', 'eventos' => array()));
            die();
        }

        require_once __DIR__ . '/../api/lib/SyncLogger.php';

        $condiciones = array('l.musuarios_id = ?');
        $parametros = array($musuario_filtro);

        if ($dispositivo_id === '') {
            $condiciones[] = 'l.dispositivo_id IS NULL';
        } else {
            $condiciones[] = 'l.dispositivo_id = ?';
            $parametros[] = $dispositivo_id;
        }
        if (!$es_admin) {
            $condiciones[] = 'l.mfundos_id = ?';
            $parametros[] = $mfundos_id;
        }
        if ($dias > 0) {
            $condiciones[] = 'l.created >= ?';
            $parametros[] = date('Y-m-d H:i:s', strtotime('-' . $dias . ' days'));
        }

        $sql = 'SELECT l.accion, l.modulo, l.resultado, l.total, l.exitos, l.conflictos,
                       l.errores, l.duracion_ms, l.mensaje, l.detalle, l.created,
                       l.nombre_dispositivo, f.nombre AS fundo_nombre
                  FROM sync_offline_log l
                  LEFT JOIN mfundos f ON f.id = l.mfundos_id
                 WHERE ' . implode(' AND ', $condiciones) . '
                 ORDER BY l.created DESC
                 LIMIT 300';

        try {
            $registros = \Musuario::connection()->query($sql, $parametros)->fetchAll();
        } catch (\Throwable $e) {
            echo json_encode(array(
                'resultado' => 'error',
                'mensaje' => 'No se pudo leer el detalle de sincronizaciones.',
                'detalle' => $e->getMessage(),
                'eventos' => array()
            ));
            die();
        }

        $eventos = array();
        foreach ($registros as $r) {
            $detalle = array();
            if (!empty($r['detalle'])) {
                $decodificado = json_decode($r['detalle'], true);
                if (is_array($decodificado)) {
                    $detalle = $decodificado;
                }
            }

            foreach ($detalle as $indice => $fallo) {
                if (isset($fallo['modulo']) && $fallo['modulo'] !== '') {
                    $detalle[$indice]['modulo_etiqueta'] = \Api\SyncLogger::etiquetaModulo($fallo['modulo']);
                } else {
                    $detalle[$indice]['modulo_etiqueta'] = '';
                }
            }

            $eventos[] = array(
                'fecha'              => $r['created'] ? date('d-m-Y H:i:s', strtotime($r['created'])) : '—',
                'accion'             => $r['accion'],
                'modulo'             => $r['modulo'],
                'modulo_etiqueta'    => $r['modulo'] ? \Api\SyncLogger::etiquetaModulo($r['modulo']) : 'General',
                'resultado'          => $r['resultado'],
                'total'              => (int) $r['total'],
                'exitos'             => (int) $r['exitos'],
                'conflictos'         => (int) $r['conflictos'],
                'errores'            => (int) $r['errores'],
                'duracion_ms'        => (int) $r['duracion_ms'],
                'mensaje'            => $r['mensaje'],
                'fundo_nombre'       => $r['fundo_nombre'] ? $r['fundo_nombre'] : '',
                'detalle'            => $detalle,
            );
        }

        echo json_encode(array(
            'resultado' => 'ok',
            'eventos'   => $eventos,
            'es_admin'  => $es_admin,
        ));
        break;

    #*******************************************************************************
    default:
        echo json_encode(array('resultado' => 'error', 'mensaje' => 'Acción no reconocida.'));
        break;
}