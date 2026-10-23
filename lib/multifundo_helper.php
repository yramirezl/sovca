<?php
/**
 * Helper multi-fundo para BOVS.
 * Compatible: PHP 7.4 y 8.3
 *
 * Usa ActiveRecord\ConnectionManager::get_connection()->query()
 * porque PgsqlAdapter NO tiene query_and_fetch_all().
 */

// ============================================================
// WRAPPER: ejecuta SQL y devuelve array asociativo
// ============================================================
if (!function_exists('_mf_q')) {
    function _mf_q($sql) {
        if (!class_exists('ActiveRecord\\ConnectionManager')) return array();
        try {
            $conn = ActiveRecord\ConnectionManager::get_connection();
            if (!$conn) return array();

            $sth = $conn->query($sql);
            if (!$sth) return array();

            // PDOStatement → fetchAll asociativo
            if (method_exists($sth, 'fetchAll')) {
                $rows = $sth->fetchAll(PDO::FETCH_ASSOC);
                return $rows ? $rows : array();
            }

            // Fallback por si devuelve un recurso
            if (is_resource($sth)) {
                $rows = array();
                while ($r = pg_fetch_assoc($sth)) { $rows[] = $r; }
                return $rows;
            }

            return array();
        } catch (Exception $e) {
            return array();
        }
    }
}

if (!function_exists('_mf_q1')) {
    function _mf_q1($sql) {
        $rows = _mf_q($sql);
        return ($rows && isset($rows[0])) ? $rows[0] : null;
    }
}

if (!function_exists('_mf_exec')) {
    /**
     * Ejecuta una sentencia SQL de escritura (UPDATE/INSERT/DELETE).
     * Devuelve true si la consulta se ejecutó sin errores.
     */
    function _mf_exec($sql) {
        if (!class_exists('ActiveRecord\\ConnectionManager')) return false;
        try {
            $conn = ActiveRecord\ConnectionManager::get_connection();
            if (!$conn) return false;
            $conn->query($sql);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}


// ============================================================
// FUNCIONES PRINCIPALES
// ============================================================

if (!function_exists('mf_listar_fundos_usuario')) {
    function mf_listar_fundos_usuario($usuario_id) {
        $uid = (int)$usuario_id;
        return _mf_q("
            SELECT f.id AS mfundos_id, f.nombre, f.cid_rif,
                   f.mtipoganaderias_id, f.mplanes_id,
                   COALESCE(p.max_reg, 50) AS max_reg,
                   p.nombre AS plan_nombre,
                   (SELECT COUNT(*) FROM manimales a
                     WHERE a.mfundos_id = f.id AND a.status = 1) AS total_animales
              FROM mfundosxusuarios fxu
        INNER JOIN mfundos f ON f.id = fxu.mfundos_id
         LEFT JOIN mplanes p ON p.id = f.mplanes_id
             WHERE fxu.musuarios_id = $uid
               AND fxu.status = 1
               AND f.status = 1
          ORDER BY f.nombre ASC
        ");
    }
}

if (!function_exists('mf_contar_fundos_usuario')) {
    function mf_contar_fundos_usuario($usuario_id) {
        $uid = (int)$usuario_id;
        $r = _mf_q1("
            SELECT COUNT(*) AS total
              FROM mfundosxusuarios fxu
        INNER JOIN mfundos f ON f.id = fxu.mfundos_id
             WHERE fxu.musuarios_id = $uid
               AND fxu.status = 1
               AND f.status = 1
        ");
        return ($r && isset($r['total'])) ? (int)$r['total'] : 0;
    }
}

if (!function_exists('mf_puede_crear_fundo')) {
    function mf_puede_crear_fundo($usuario_id) {
        $uid = (int)$usuario_id;
        $r = _mf_q1("SELECT max_fundos FROM musuarios WHERE id = $uid LIMIT 1");
        $max = ($r && isset($r['max_fundos'])) ? (int)$r['max_fundos'] : 1;
        if ($max < 1) $max = 1;
        return (mf_contar_fundos_usuario($uid) < $max);
    }
}

if (!function_exists('mf_guardar_ultimo_fundo')) {
    /**
     * Persiste en musuarios.ultimo_fundo_id el fundo activo del usuario.
     * Solo guarda si el usuario tiene acceso vigente a ese fundo.
     */
    function mf_guardar_ultimo_fundo($usuario_id, $fundo_id) {
        $uid = (int)$usuario_id;
        $fid = (int)$fundo_id;
        if ($uid <= 0 || $fid <= 0) return false;

        $acceso = _mf_q1("
            SELECT 1 AS ok
              FROM mfundosxusuarios
             WHERE musuarios_id = $uid
               AND mfundos_id   = $fid
               AND status = 1
             LIMIT 1
        ");
        if (!$acceso) return false;

        return _mf_exec("UPDATE musuarios SET ultimo_fundo_id = $fid WHERE id = $uid");
    }
}

if (!function_exists('mf_es_usuario_multifundo')) {
    /**
     * Indica si el usuario está marcado como multifundo.
     * Lee directo de musuarios (la vista vusuarios no expone la columna).
     */
    function mf_es_usuario_multifundo($usuario_id) {
        $uid = (int)$usuario_id;
        if ($uid <= 0) return false;

        $r = _mf_q1("SELECT es_multifundo FROM musuarios WHERE id = $uid LIMIT 1");
        if (!$r || !isset($r['es_multifundo'])) return false;

        // Postgres/PDO puede devolver boolean como 't'/'f', '1'/'0', true/false
        $val = strtolower(trim((string)$r['es_multifundo']));
        return in_array($val, array('1', 't', 'true', 'y', 'yes'), true);
    }
}

if (!function_exists('mf_ultimo_fundo')) {
    /**
     * Devuelve el último fundo activo guardado del usuario.
     * Retorna 0 si no existe o si ya no tiene acceso vigente.
     */
    function mf_ultimo_fundo($usuario_id) {
        $uid = (int)$usuario_id;
        if ($uid <= 0) return 0;

        $row = _mf_q1("
            SELECT u.ultimo_fundo_id
              FROM musuarios u
        INNER JOIN mfundosxusuarios fxu
                ON fxu.musuarios_id = u.id
               AND fxu.mfundos_id   = u.ultimo_fundo_id
               AND fxu.status = 1
        INNER JOIN mfundos f
                ON f.id = u.ultimo_fundo_id
               AND f.status = 1
             WHERE u.id = $uid
               AND u.ultimo_fundo_id IS NOT NULL
             LIMIT 1
        ");

        return ($row && isset($row['ultimo_fundo_id']) && $row['ultimo_fundo_id'] !== null)
            ? (int)$row['ultimo_fundo_id'] : 0;
    }
}

if (!function_exists('mf_cargar_fundo_en_sesion')) {
    /**
     * Recalcula TODAS las variables de sesión dependientes del fundo.
     */
    function mf_cargar_fundo_en_sesion($usuario_id, $fundo_id) {
        $uid = (int)$usuario_id;
        $fid = (int)$fundo_id;
        if ($uid <= 0 || $fid <= 0) return false;

        $f = _mf_q1("
            SELECT f.id, f.nombre, f.mtipoganaderias_id, f.mplanes_id,
                   COALESCE(p.max_reg, 50) AS max_reg,
                   p.nombre AS plan_nombre
              FROM mfundosxusuarios fxu
        INNER JOIN mfundos f ON f.id = fxu.mfundos_id
         LEFT JOIN mplanes p ON p.id = f.mplanes_id
             WHERE fxu.musuarios_id = $uid
               AND fxu.mfundos_id  = $fid
               AND fxu.status = 1
               AND f.status = 1
             LIMIT 1
        ");

        if (!$f) return false;

        // Actualizar TODAS las variables de sesión del fundo
        $_SESSION['mfundos_id']       = (int)$f['id'];
        $_SESSION['fundo_nombre']     = $f['nombre'];
        $_SESSION['tipo_ganaderia']   = (int)$f['mtipoganaderias_id'];
        $_SESSION['mplanes_id']       = (int)$f['mplanes_id'];
        $_SESSION['maximo_registros'] = (int)$f['max_reg'];

        // Recalcular acceso a registro de animales
        $c = _mf_q1("
            SELECT COUNT(id) AS total
              FROM manimalxfundos
             WHERE status = 1 AND mfundos_id = $fid
        ");
        $usados = ($c && isset($c['total'])) ? (int)$c['total'] : 0;
        $_SESSION['acceso_reg_animales'] = ($_SESSION['maximo_registros'] > $usados) ? 1 : 0;

        // Persistir como último fundo activo del usuario, para restaurarlo
        // automáticamente cuando vuelva a iniciar sesión.
        mf_guardar_ultimo_fundo($uid, $fid);

        return true;
    }
}


// ============================================================
// MODO SOPORTE (solo versión web)
// ------------------------------------------------------------
// Un administrador (rol 1 ADMINISTRADOR o 4 SUPER USUARIO) puede
// "entrar" temporalmente a un fundo para revisar y corregir datos,
// sin cambiar de cuenta ni de rol:
//
//  · la sesión CONSERVA el rol 1/4 (sigue teniendo acceso a las
//    pantallas de administración mientras está dentro del fundo);
//  · se cargan las variables del fundo (mfundos_id, nombre, tipo de
//    ganadería, plan, límite de registros y acceso a registrar
//    animales) para que los módulos de gestión trabajen sobre él;
//  · el menú lateral muestra el menú operativo del productor;
//  · se cierra a mano ("Salir del modo soporte") o al vencer
//    MF_SOPORTE_DURACION_SEG (2 horas);
//  · cada acceso y su duración quedan en la tabla soporte_fundos
//    (si la tabla no existe, la bitácora falla en silencio y el
//    modo soporte sigue funcionando).
//
// La app offline NO usa este modo: el API exige rol/productor con
// acceso_offline y los roles 1 y 4 quedan fuera.
// ============================================================

if (!defined('MF_SOPORTE_DURACION_SEG')) {
    define('MF_SOPORTE_DURACION_SEG', 7200); // 2 horas
}

if (!function_exists('mf_es_admin')) {
    function mf_es_admin() {
        $rol = isset($_SESSION['rolx']) ? (int)$_SESSION['rolx'] : 0;
        return in_array($rol, array(1, 4), true);
    }
}

if (!function_exists('mf_modo_supervisor_activo')) {
    function mf_modo_supervisor_activo() {
        return !empty($_SESSION['supervisor_mode']);
    }
}

/**
 * Datos del modo soporte activo (para pintar el aviso y la bitácora).
 * Devuelve null cuando no está activo.
 */
if (!function_exists('mf_soporte_estado')) {
    function mf_soporte_estado() {
        if (!mf_modo_supervisor_activo()) return null;

        return array(
            'fundo_id' => isset($_SESSION['supervisor_fundo']) ? (int)$_SESSION['supervisor_fundo'] : 0,
            'fundo'    => isset($_SESSION['fundo_nombre']) ? (string)$_SESSION['fundo_nombre'] : '',
            'inicio'   => isset($_SESSION['supervisor_inicio']) ? (int)$_SESSION['supervisor_inicio'] : 0,
            'expira'   => isset($_SESSION['supervisor_expira']) ? (int)$_SESSION['supervisor_expira'] : 0,
            'registro' => isset($_SESSION['supervisor_registro']) ? (int)$_SESSION['supervisor_registro'] : 0,
            'motivo'   => isset($_SESSION['supervisor_motivo']) ? (string)$_SESSION['supervisor_motivo'] : ''
        );
    }
}

/**
 * ¿Venció el tiempo máximo de la sesión de soporte?
 */
if (!function_exists('mf_soporte_expirado')) {
    function mf_soporte_expirado() {
        $estado = mf_soporte_estado();
        if (!$estado) return false;
        if ($estado['expira'] > 0 && time() > $estado['expira']) return true;
        return false;
    }
}

/**
 * Activa el modo soporte sobre un fundo.
 * Si ya había un modo soporte activo, se cierra primero (conservando
 * el fundo ORIGINAL de la sesión, no el del soporte anterior).
 */
if (!function_exists('mf_activar_supervisor')) {
    function mf_activar_supervisor($fundo_id, $motivo = '') {
        if (!mf_es_admin()) return false;

        $fid = (int)$fundo_id;
        if ($fid <= 0) return false;

        if (mf_modo_supervisor_activo()) {
            mf_desactivar_supervisor('cambio de fundo');
        }

        $f = _mf_q1("
            SELECT f.id, f.nombre, f.mtipoganaderias_id, f.mplanes_id,
                   COALESCE(p.max_reg, 50) AS max_reg
              FROM mfundos f
         LEFT JOIN mplanes p ON p.id = f.mplanes_id
             WHERE f.id = $fid AND f.status = 1
             LIMIT 1
        ");
        if (!$f) return false;

        if (!isset($_SESSION['supervisor_fundo_original'])) {
            $_SESSION['supervisor_fundo_original'] = isset($_SESSION['mfundos_id'])
                ? (int)$_SESSION['mfundos_id'] : 0;
        }

        // Datos de control del modo soporte
        $_SESSION['supervisor_mode']   = true;
        $_SESSION['supervisor_fundo']  = $fid;
        $_SESSION['supervisor_inicio'] = time();
        $_SESSION['supervisor_expira'] = time() + MF_SOPORTE_DURACION_SEG;
        $_SESSION['supervisor_motivo'] = mb_substr(trim((string)$motivo), 0, 200);

        // Contexto del fundo (lo mismo que carga mf_cargar_fundo_en_sesion)
        $_SESSION['mfundos_id']       = (int)$f['id'];
        $_SESSION['fundo_nombre']     = trim((string)$f['nombre']);
        $_SESSION['tipo_ganaderia']   = (int)$f['mtipoganaderias_id'];
        $_SESSION['mplanes_id']       = (int)$f['mplanes_id'];
        $_SESSION['maximo_registros'] = (int)$f['max_reg'];

        $c = _mf_q1("
            SELECT COUNT(id) AS total
              FROM manimalxfundos
             WHERE status = 1 AND mfundos_id = $fid
        ");
        $usados = ($c && isset($c['total'])) ? (int)$c['total'] : 0;
        $_SESSION['acceso_reg_animales'] = ($_SESSION['maximo_registros'] > $usados) ? 1 : 0;

        // Bitácora (si la tabla no existe, _mf_q/_mf_exec fallan en silencio)
        $uid = isset($_SESSION['idusuariox']) ? (int)$_SESSION['idusuariox'] : 0;
        $ip  = isset($_SERVER['REMOTE_ADDR']) ? substr((string)$_SERVER['REMOTE_ADDR'], 0, 45) : '';
        $ua  = isset($_SERVER['HTTP_USER_AGENT']) ? substr((string)$_SERVER['HTTP_USER_AGENT'], 0, 255) : '';
        $mot = addslashes($_SESSION['supervisor_motivo']);
        $ips = addslashes($ip);
        $uas = addslashes($ua);

        $registro = 0;
        if ($uid > 0) {
            _mf_exec("
                INSERT INTO soporte_fundos (musuarios_id, mfundos_id, motivo, ip, user_agent)
                VALUES ($uid, $fid, '$mot', '$ips', '$uas')
            ");
            $r = _mf_q1("
                SELECT id FROM soporte_fundos
                 WHERE musuarios_id = $uid AND mfundos_id = $fid
              ORDER BY id DESC LIMIT 1
            ");
            $registro = ($r && isset($r['id'])) ? (int)$r['id'] : 0;
        }
        $_SESSION['supervisor_registro'] = $registro;

        return true;
    }
}

/**
 * Cierra el modo soporte y devuelve la sesión al estado anterior.
 * Si el administrador no tenía fundo (lo normal), se limpian las
 * variables del fundo para que no quede "pegado" al fundo visitado.
 */
if (!function_exists('mf_desactivar_supervisor')) {
    function mf_desactivar_supervisor($motivo = '') {
        if (!mf_modo_supervisor_activo()) return false;

        $estado   = mf_soporte_estado();
        $original = isset($_SESSION['supervisor_fundo_original'])
            ? (int)$_SESSION['supervisor_fundo_original'] : 0;

        // Cerrar el registro abierto de la bitácora
        if ($estado && !empty($estado['registro'])) {
            $reg = (int)$estado['registro'];
            _mf_exec("
                UPDATE soporte_fundos
                   SET fecha_fin    = now(),
                       duracion_seg = GREATEST(0, EXTRACT(EPOCH FROM (now() - fecha_inicio))::int),
                       status       = 0
                 WHERE id = $reg
            ");
        }

        unset($_SESSION['supervisor_mode']);
        unset($_SESSION['supervisor_fundo']);
        unset($_SESSION['supervisor_inicio']);
        unset($_SESSION['supervisor_expira']);
        unset($_SESSION['supervisor_motivo']);
        unset($_SESSION['supervisor_registro']);
        unset($_SESSION['supervisor_fundo_original']);

        if ($original > 0 && isset($_SESSION['idusuariox']) && function_exists('mf_cargar_fundo_en_sesion')) {
            if (mf_cargar_fundo_en_sesion((int)$_SESSION['idusuariox'], $original)) {
                return true;
            }
        }

        // Sin fundo previo: dejar la sesión como la de un administrador
        $_SESSION['mfundos_id']          = 0;
        $_SESSION['fundo_nombre']        = '';
        $_SESSION['tipo_ganaderia']      = 0;
        $_SESSION['mplanes_id']          = 0;
        $_SESSION['maximo_registros']    = 0;
        $_SESSION['acceso_reg_animales'] = 0;

        return true;
    }
}

if (!function_exists('mf_listar_todos_los_fundos')) {
    function mf_listar_todos_los_fundos($filtro = '') {
        if (!mf_es_admin()) return array();

        $f_where = '';
        if ($filtro !== '') {
            $safe = addslashes($filtro);
            $f_where = " AND (f.nombre ILIKE '%$safe%' OR f.cid_rif ILIKE '%$safe%') ";
        }

        return _mf_q("
            SELECT f.id AS mfundos_id, f.nombre, f.cid_rif,
                   f.mtipoganaderias_id, f.mplanes_id, f.created,
                   COALESCE(p.max_reg, 50) AS max_reg,
                   p.nombre AS plan_nombre,
                   u.name AS propietario_nombre,
                   u.username AS propietario_username,
                   u.email AS propietario_email,
                   (SELECT COUNT(*) FROM manimales a
                     WHERE a.mfundos_id = f.id AND a.status = 1) AS total_animales,
                   (SELECT COUNT(*) FROM mfundosxusuarios fxu
                     WHERE fxu.mfundos_id = f.id AND fxu.status = 1) AS total_usuarios
              FROM mfundos f
         LEFT JOIN mplanes p   ON p.id = f.mplanes_id
         LEFT JOIN musuarios u ON u.id = f.user_propietario
             WHERE f.status = 1 $f_where
          ORDER BY f.nombre ASC
        ");
    }
}


// ============================================================
// UNIFICACIÓN DE USUARIOS Y FUNDOS  (roles 1 y 4)
// ============================================================
//
// Situación que resuelve: una misma persona que necesitaba administrar varios
// fundos tuvo que crear UN USUARIO POR FUNDO (mismo email, distinto username).
// La unificación asigna TODOS esos fundos a un único usuario (multifundo) y
// permite desactivar las cuentas sobrantes.
//
// IMPORTANTE: NO se mueve ningún dato de negocio. Los animales, controles,
// producciones, etc. siguen perteneciendo a su fundo (manimales.mfundos_id);
// lo único que cambia es QUIÉN tiene acceso al fundo (mfundosxusuarios) y,
// opcionalmente, quién figura como propietario (mfundos.user_propietario).

if (!function_exists('mf_fundos_para_unificar')) {
    /** Fundos activos a los que el usuario tiene acceso (para unificar). */
    function mf_fundos_para_unificar($usuario_id) {
        $uid = (int) $usuario_id;
        return _mf_q("
            SELECT f.id AS mfundos_id, f.nombre, f.cid_rif, f.mtipoganaderias_id,
                   f.user_propietario,
                   u2.username AS propietario_username,
                   (SELECT COUNT(*) FROM manimales a
                     WHERE a.mfundos_id = f.id AND a.status = 1) AS animales
              FROM mfundosxusuarios fxu
        INNER JOIN mfundos f ON f.id = fxu.mfundos_id AND f.status = 1
         LEFT JOIN musuarios u2 ON u2.id = f.user_propietario
             WHERE fxu.musuarios_id = $uid
               AND fxu.status = 1
          ORDER BY f.nombre ASC
        ");
    }
}

if (!function_exists('mf_buscar_usuarios')) {
    /** Busca usuarios ACTIVOS por username / nombre / email (para el selector). */
    function mf_buscar_usuarios($q, $limit = 20) {
        $lim  = max(1, min(50, (int) $limit));
        $safe = addslashes((string) $q);
        $w    = ($safe === '')
            ? ''
            : " AND (u.username ILIKE '%$safe%' OR u.name ILIKE '%$safe%' OR u.email ILIKE '%$safe%') ";
        return _mf_q("
            SELECT u.id, u.username, u.name, u.email, u.mroles_id, u.es_multifundo, u.max_fundos
              FROM musuarios u
             WHERE u.status = 1 $w
          ORDER BY u.username ASC
             LIMIT $lim
        ");
    }
}

if (!function_exists('mf_grupos_usuarios_duplicados')) {
    /**
     * Detecta los grupos de cuentas ACTIVAS que comparten el mismo email
     * (candidatos naturales a unificar).
     *
     * @return array  [ ['email' => ..., 'usuarios' => [ [id, username, name, rol, fundos=>[...] ], ... ] ], ... ]
     */
    function mf_grupos_usuarios_duplicados() {
        $rows = _mf_q("
            SELECT u.id, u.username, u.name, u.email, u.mroles_id, u.es_multifundo, u.max_fundos
              FROM musuarios u
             WHERE u.status = 1
               AND coalesce(u.email, '') <> ''
               AND lower(u.email) IN (
                     SELECT lower(email)
                       FROM musuarios
                      WHERE status = 1 AND coalesce(email, '') <> ''
                      GROUP BY lower(email)
                     HAVING count(*) > 1
                   )
          ORDER BY lower(u.email), u.id
        ");
        if (!$rows) {
            return array();
        }

        $grupos = array();
        foreach ($rows as $r) {
            $email = strtolower((string) $r['email']);
            if (!isset($grupos[$email])) {
                $grupos[$email] = array('email' => $email, 'usuarios' => array());
            }
            $grupos[$email]['usuarios'][] = array(
                'id'            => (int) $r['id'],
                'username'      => $r['username'],
                'name'          => $r['name'],
                'rol'           => (int) $r['mroles_id'],
                'es_multifundo' => $r['es_multifundo'],
                'max_fundos'    => (int) $r['max_fundos'],
                'fundos'        => mf_fundos_para_unificar((int) $r['id']),
            );
        }
        return array_values($grupos);
    }
}

if (!function_exists('mf_unificar_fundos')) {
    /**
     * Asigna a un usuario destino un conjunto de fundos y lo marca como multifundo.
     *
     * @param int   $destino_id Usuario que conservará todos los fundos (debe estar activo).
     * @param array $fundo_ids  Fundos a asignar al destino.
     * @param array $origenes   Usuarios que pierden acceso a esos fundos.
     * @param array $opciones   ['desactivar_origenes' => bool, 'transferir_propiedad' => bool]
     * @return array            Reporte: ok, errores, fundos, es_multifundo, max_fundos, desactivados, con_fundos
     */
    function mf_unificar_fundos($destino_id, array $fundo_ids, array $origenes = array(), array $opciones = array()) {
        $destino    = (int) $destino_id;
        $desactivar = !empty($opciones['desactivar_origenes']);
        $propiedad  = !empty($opciones['transferir_propiedad']);

        $res = array(
            'ok'            => false,
            'destino'       => $destino,
            'fundos'        => array(),
            'errores'       => array(),
            'es_multifundo' => 0,
            'max_fundos'    => 0,
            'desactivados'  => array(),
            'heredados'     => array(),
            'con_fundos'    => array(),
        );

        $u = _mf_q1("SELECT id, username FROM musuarios WHERE id = $destino AND status = 1 LIMIT 1");
        if (!$u) {
            $res['errores'][] = 'El usuario destino no existe o está inactivo.';
            return $res;
        }

        $tmp = array();
        foreach ($fundo_ids as $f) {
            $f = (int) $f;
            if ($f > 0) {
                $tmp[$f] = true;
            }
        }
        $fundos = array_keys($tmp);
        if (!$fundos) {
            $res['errores'][] = 'No se seleccionaron fundos.';
            return $res;
        }

        // ---- 1) Dar acceso del destino a cada fundo ----
        foreach ($fundos as $fid) {
            $f = _mf_q1("SELECT id, nombre, user_propietario FROM mfundos WHERE id = $fid AND status = 1 LIMIT 1");
            if (!$f) {
                $res['errores'][] = "El fundo $fid no existe o está inactivo.";
                continue;
            }

            // NORMATIVA: un fundo solo puede tener un productor y un ayudante
            // solo puede pertenecer a un fundo. Los usuarios que van a perder
            // el acceso en esta misma operación (los orígenes) se excluyen.
            @include_once __DIR__ . '/normativa_fundos_helper.php';
            if (function_exists('norm_validar_vinculo')) {
                $errores_norm = norm_validar_vinculo($destino, $fid, $origenes);
                if (!empty($errores_norm)) {
                    $res['errores'][] = implode(' ', $errores_norm);
                    continue;
                }
            }

            $existe = _mf_q1("SELECT id, status FROM mfundosxusuarios WHERE musuarios_id = $destino AND mfundos_id = $fid LIMIT 1");
            if ($existe) {
                $ok = ((int) $existe['status'] === 1)
                    ? true
                    : _mf_exec("UPDATE mfundosxusuarios SET status = 1 WHERE id = " . (int) $existe['id']);
            } else {
                $ok = _mf_exec("INSERT INTO mfundosxusuarios (musuarios_id, mfundos_id, status, user_create, created)
                                VALUES ($destino, $fid, 1, $destino, NOW())");
            }
            if (!$ok) {
                $res['errores'][] = "No se pudo asignar el fundo '{$f['nombre']}' ($fid) al usuario destino.";
                continue;
            }

            // ---- 2) Transferir la propiedad (opcional) ----
            if ($propiedad && (int) $f['user_propietario'] !== $destino) {
                if (!_mf_exec("UPDATE mfundos SET user_propietario = $destino WHERE id = $fid")) {
                    $res['errores'][] = "No se pudo transferir la propiedad del fundo '{$f['nombre']}'.";
                }
            }

            $res['fundos'][] = array('id' => $fid, 'nombre' => $f['nombre']);
        }

        if (!$res['fundos']) {
            return $res;
        }

        // ---- 3) El destino pasa a ser multifundo ----
        $total = mf_contar_fundos_usuario($destino);
        if ($total > 100) {
            $total = 100;
        }
        if (!_mf_exec("UPDATE musuarios
                          SET es_multifundo = TRUE,
                              max_fundos = GREATEST(COALESCE(max_fundos, 1), $total)
                        WHERE id = $destino")) {
            $res['errores'][] = 'No se pudo marcar al usuario destino como multifundo.';
        }
        $u2 = _mf_q1("SELECT es_multifundo, max_fundos FROM musuarios WHERE id = $destino LIMIT 1");
        if ($u2) {
            $res['es_multifundo'] = 1;
            $res['max_fundos']    = (int) $u2['max_fundos'];
        }

        // ---- 4) Los orígenes pierden acceso a los fundos movidos ----
        foreach ($origenes as $ori) {
            $ori = (int) $ori;
            if ($ori <= 0 || $ori === $destino) {
                continue;
            }
            foreach ($res['fundos'] as $fd) {
                _mf_exec("UPDATE mfundosxusuarios SET status = 0
                           WHERE musuarios_id = $ori AND mfundos_id = " . (int) $fd['id']);
            }
            $restantes = mf_contar_fundos_usuario($ori);
            if ($desactivar && $restantes === 0) {
                // Se guarda la "firma" (quién la hizo y cuándo) para que, si algún
                // día se reactiva la cuenta, puedan volver sus usuarios ayudantes
                // (ver lib/herencia_productor_helper.php).
                $actor = isset($_SESSION['idusuariox']) ? (int) $_SESSION['idusuariox'] : 0;

                if (_mf_exec("UPDATE musuarios
                                 SET status = 0,
                                     user_modify = $actor,
                                     updated = NOW()
                               WHERE id = $ori")) {
                    $res['desactivados'][] = $ori;

                    // HERENCIA: los ayudantes de los fundos que le queden sin
                    // productor activo heredan la inhabilitación de su productor.
                    @include_once __DIR__ . '/herencia_productor_helper.php';
                    $cx = class_exists('ActiveRecord\\ConnectionManager')
                        ? ActiveRecord\ConnectionManager::get_connection()
                        : null;
                    if ($cx && function_exists('bovs_prod_inhabilitar_ayudantes')) {
                        $heredados = bovs_prod_inhabilitar_ayudantes($cx, $ori, $actor, date('d-m-Y'));
                        foreach ($heredados as $h) {
                            $res['heredados'][] = $h;
                        }
                    }
                } else {
                    $res['errores'][] = "No se pudo desactivar el usuario $ori.";
                }
            } elseif ($restantes > 0) {
                $res['con_fundos'][] = array('id' => $ori, 'fundos' => $restantes);
            }
        }

        $res['ok'] = (count($res['errores']) === 0);
        return $res;
    }
}

if (!function_exists('mf_usuarios_involucrados_fundos')) {
    /**
     * Usuarios "involucrados" en un conjunto de fundos: quienes hoy tienen
     * acceso activo (mfundosxusuarios.status = 1) y quienes figuran como
     * propietario (mfundos.user_propietario).
     *
     * Es la lista CERRADA de candidatos a conservar la cuenta al unificar:
     * no tiene sentido elegir como destino a alguien que no participa de la
     * transacción.
     *
     * @param array $fundo_ids Fundos marcados por el administrador.
     * @return array [{id, username, name, email, rol, es_multifundo, max_fundos,
     *                 fundos_total, fundos_propios, es_propietario, sugerido}]
     */
    function mf_usuarios_involucrados_fundos(array $fundo_ids) {
        $ids = array();
        foreach ($fundo_ids as $f) {
            $f = (int) $f;
            if ($f > 0) { $ids[$f] = true; }
        }
        $ids = array_keys($ids);
        if (!$ids) { return array(); }

        // Los ids ya vienen saneados (enteros > 0), por eso se interpolan.
        $lista = implode(',', $ids);
        $rows  = _mf_q("
            SELECT u.id, u.username, u.name, u.email, u.es_multifundo, u.max_fundos,
                   r.nombre AS rol,
                   COUNT(DISTINCT rel.mfundos_id) AS fundos_total,
                   COUNT(DISTINCT CASE WHEN f.user_propietario = u.id THEN rel.mfundos_id END) AS fundos_propios
              FROM (
                    SELECT fxu.musuarios_id AS uid, fxu.mfundos_id AS mfundos_id
                      FROM mfundosxusuarios fxu
                     WHERE fxu.status = 1 AND fxu.mfundos_id IN ($lista)
                     UNION
                    SELECT f0.user_propietario AS uid, f0.id AS mfundos_id
                      FROM mfundos f0
                     WHERE f0.status = 1 AND f0.id IN ($lista) AND f0.user_propietario IS NOT NULL
                   ) rel
              JOIN musuarios u ON u.id = rel.uid AND u.status = 1
         LEFT JOIN mroles r    ON r.id = u.mroles_id
         LEFT JOIN mfundos f   ON f.id = rel.mfundos_id
          GROUP BY u.id, u.username, u.name, u.email, u.es_multifundo, u.max_fundos, r.nombre
          ORDER BY fundos_propios DESC, fundos_total DESC, u.username ASC
        ");

        $out = array();
        foreach ($rows as $r) {
            $out[] = array(
                'id'             => (int) $r['id'],
                'username'       => $r['username'],
                'name'           => $r['name'],
                'email'          => $r['email'],
                'rol'            => $r['rol'] ? $r['rol'] : '',
                'es_multifundo'  => (int) $r['es_multifundo'],
                'max_fundos'     => (int) $r['max_fundos'],
                'fundos_total'   => (int) $r['fundos_total'],
                'fundos_propios' => (int) $r['fundos_propios'],
                'es_propietario' => ((int) $r['fundos_propios'] > 0) ? 1 : 0,
                'sugerido'       => 0,
            );
        }

        // El primer candidato (propietario de más fundos / con más acceso)
        // se marca como el sugerido para conservar la cuenta.
        if ($out) { $out[0]['sugerido'] = 1; }
        return $out;
    }
}