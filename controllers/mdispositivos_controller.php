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
    default:
        echo json_encode(array('resultado' => 'error', 'mensaje' => 'Acción no reconocida.'));
        break;
}