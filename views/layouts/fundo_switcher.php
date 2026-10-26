<?php
/**
 * Helper multi-fundo para SOVCA.
 *
 * IMPORTANTE: Este helper NO carga modelos por sí solo.
 * Los modelos del proyecto usan rutas relativas ('../config/conexion.php')
 * que fallan si el CWD no es models/.
 *
 * En su lugar, el helper asume que el contexto que lo llama ya cargó los
 * modelos necesarios (Mfundosxusuario, Mfundo, Manimalxfundo, Musuario) y
 * usa class_exists() para verificar antes de usarlos.
 *
 * Si un modelo no está cargado, las funciones devuelven valores seguros
 * (array vacío, 0, false) en vez de tirar fatal error.
 *
 * Compatible: PHP 7.4 y 8.3
 */


// ============================================================
// HELPERS INTERNOS
// ============================================================

if (!function_exists('_mf_clase_ok')) {
    function _mf_clase_ok($nombre) {
        return class_exists($nombre, false) || class_exists($nombre);
    }
}


// ============================================================
// FUNCIONES PRINCIPALES
// ============================================================

if (!function_exists('mf_listar_fundos_usuario')) {
    /**
     * Devuelve todos los fundos activos vinculados a un usuario.
     */
    function mf_listar_fundos_usuario($usuario_id) {
        if (!_mf_clase_ok('Mfundosxusuario')) return array();

        $usuario_id = (int)$usuario_id;
        $q = @Mfundosxusuario::find_by_sql("
            SELECT
                f.id                AS mfundos_id,
                f.nombre,
                f.cid_rif,
                f.mtipoganaderias_id,
                f.mplanes_id,
                p.max_reg,
                p.nombre            AS plan_nombre,
                (SELECT COUNT(*) FROM manimales a 
                  WHERE a.mfundos_id = f.id AND a.status = 1) AS total_animales
              FROM mfundosxusuarios fxu
        INNER JOIN mfundos f    ON f.id = fxu.mfundos_id
         LEFT JOIN mplanes p    ON p.id = f.mplanes_id
             WHERE fxu.musuarios_id = $usuario_id
               AND fxu.status = 1
               AND f.status = 1
          ORDER BY f.nombre ASC
        ");
        return $q ? $q : array();
    }
}

if (!function_exists('mf_contar_fundos_usuario')) {
    function mf_contar_fundos_usuario($usuario_id) {
        if (!_mf_clase_ok('Mfundosxusuario')) return 0;

        $usuario_id = (int)$usuario_id;
        $q = @Mfundosxusuario::find_by_sql("
            SELECT COUNT(*) AS total
              FROM mfundosxusuarios fxu
        INNER JOIN mfundos f ON f.id = fxu.mfundos_id
             WHERE fxu.musuarios_id = $usuario_id
               AND fxu.status = 1
               AND f.status = 1
        ");
        return ($q && isset($q[0]->total)) ? (int)$q[0]->total : 0;
    }
}

if (!function_exists('mf_puede_crear_fundo')) {
    function mf_puede_crear_fundo($usuario_id) {
        if (!_mf_clase_ok('Musuario')) return false;

        $usuario_id = (int)$usuario_id;
        $usr = @Musuario::find('all', array('conditions' => array('id=?', $usuario_id)));
        if (!$usr || !isset($usr[0])) return false;

        $max    = (int)$usr[0]->max_fundos;
        $actual = mf_contar_fundos_usuario($usuario_id);

        return ($actual < $max);
    }
}

if (!function_exists('mf_cargar_fundo_en_sesion')) {
    /**
     * Carga en $_SESSION los datos del fundo activo.
     */
    function mf_cargar_fundo_en_sesion($usuario_id, $fundo_id) {
        if (!_mf_clase_ok('Mfundosxusuario')) return false;

        $usuario_id = (int)$usuario_id;
        $fundo_id   = (int)$fundo_id;

        $q = @Mfundosxusuario::find_by_sql("
            SELECT f.id, f.nombre, f.mtipoganaderias_id, f.mplanes_id, p.max_reg
              FROM mfundosxusuarios fxu
        INNER JOIN mfundos f ON f.id = fxu.mfundos_id
         LEFT JOIN mplanes p ON p.id = f.mplanes_id
             WHERE fxu.musuarios_id = $usuario_id
               AND fxu.mfundos_id  = $fundo_id
               AND fxu.status      = 1
               AND f.status        = 1
             LIMIT 1
        ");

        if (!$q || !isset($q[0])) return false;

        $f = $q[0];

        $_SESSION['mfundos_id']       = (int)$f->id;
        $_SESSION['fundo_nombre']     = $f->nombre;
        $_SESSION['tipo_ganaderia']   = (int)$f->mtipoganaderias_id;
        $_SESSION['mplanes_id']       = (int)$f->mplanes_id;
        $_SESSION['maximo_registros'] = (int)$f->max_reg;

        // Recalcular acceso a registro de animales
        if (_mf_clase_ok('Manimalxfundo')) {
            $cnt = @Manimalxfundo::find_by_sql("
                SELECT COUNT(id) AS total
                  FROM manimalxfundos
                 WHERE status = 1 AND mfundos_id = " . (int)$f->id
            );
            $usados = ($cnt && isset($cnt[0]->total)) ? (int)$cnt[0]->total : 0;
            $_SESSION['acceso_reg_animales'] = ($_SESSION['maximo_registros'] > $usados) ? 1 : 0;
        } else {
            $_SESSION['acceso_reg_animales'] = 1;
        }

        // Persistir como último fundo activo del usuario (para restaurarlo al reingresar)
        if (_mf_clase_ok('Musuario')) {
            $usr = @Musuario::find('first', array('conditions' => array('id=?', $usuario_id)));
            if ($usr) {
                $usr->ultimo_fundo_id = (int)$f->id;
                @$usr->save();
            }
        }

        return true;
    }
}


// ============================================================
// MODO SUPERVISOR
// ============================================================

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

if (!function_exists('mf_activar_supervisor')) {
    function mf_activar_supervisor($fundo_id) {
        if (!mf_es_admin()) return false;
        if (!_mf_clase_ok('Mfundo')) return false;

        $fundo_id = (int)$fundo_id;
        if ($fundo_id <= 0) return false;

        $f = @Mfundo::find('all', array('conditions' => array('id=? AND status=?', $fundo_id, 1)));
        if (!$f || !isset($f[0])) return false;

        if (!isset($_SESSION['supervisor_fundo_original'])) {
            $_SESSION['supervisor_fundo_original'] = isset($_SESSION['mfundos_id'])
                ? (int)$_SESSION['mfundos_id'] : 0;
        }

        $_SESSION['supervisor_mode']  = true;
        $_SESSION['supervisor_fundo'] = $fundo_id;

        $_SESSION['mfundos_id']       = (int)$f[0]->id;
        $_SESSION['fundo_nombre']     = $f[0]->nombre;
        $_SESSION['tipo_ganaderia']   = (int)$f[0]->mtipoganaderias_id;

        if (!empty($f[0]->mplanes_id) && _mf_clase_ok('Mfundo')) {
            $q = @Mfundo::find_by_sql("SELECT max_reg FROM mplanes WHERE id=" . (int)$f[0]->mplanes_id);
            if ($q && isset($q[0])) {
                $_SESSION['maximo_registros'] = (int)$q[0]->max_reg;
            }
        }

        return true;
    }
}

if (!function_exists('mf_desactivar_supervisor')) {
    function mf_desactivar_supervisor() {
        if (!mf_modo_supervisor_activo()) return false;

        $original = isset($_SESSION['supervisor_fundo_original'])
            ? (int)$_SESSION['supervisor_fundo_original'] : 0;

        unset($_SESSION['supervisor_mode']);
        unset($_SESSION['supervisor_fundo']);
        unset($_SESSION['supervisor_fundo_original']);

        if ($original > 0) {
            $_SESSION['mfundos_id'] = $original;
        }

        return true;
    }
}

if (!function_exists('mf_listar_todos_los_fundos')) {
    function mf_listar_todos_los_fundos($filtro_nombre = '') {
        if (!mf_es_admin()) return array();
        if (!_mf_clase_ok('Mfundo')) return array();

        $filtro = '';
        if ($filtro_nombre !== '') {
            $safe = addslashes($filtro_nombre);
            $filtro = " AND (f.nombre ILIKE '%$safe%' OR f.cid_rif ILIKE '%$safe%') ";
        }

        $q = @Mfundo::find_by_sql("
            SELECT
                f.id                AS mfundos_id,
                f.nombre,
                f.cid_rif,
                f.mtipoganaderias_id,
                f.mplanes_id,
                f.created,
                p.max_reg,
                p.nombre            AS plan_nombre,
                u.name              AS propietario_nombre,
                u.username          AS propietario_username,
                u.email             AS propietario_email,
                (SELECT COUNT(*) FROM manimales a
                  WHERE a.mfundos_id = f.id AND a.status = 1) AS total_animales,
                (SELECT COUNT(*) FROM mfundosxusuarios fxu
                  WHERE fxu.mfundos_id = f.id AND fxu.status = 1) AS total_usuarios
              FROM mfundos f
         LEFT JOIN mplanes p    ON p.id = f.mplanes_id
         LEFT JOIN musuarios u  ON u.id = f.user_propietario
             WHERE f.status = 1
             $filtro
          ORDER BY f.nombre ASC
        ");
        return $q ? $q : array();
    }
}