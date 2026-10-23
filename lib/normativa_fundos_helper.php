<?php
/**
 * NORMATIVA DE VÍNCULOS USUARIO ↔ FUNDO
 * ─────────────────────────────────────────────────────────────────────
 *  Modalidad vigente del sistema:
 *
 *   1. Un usuario PRODUCTOR (rol 2) puede tener VARIOS fundos (multifundo).
 *   2. Un FUNDO solo puede tener UN usuario PRODUCTOR.
 *   3. Un fundo puede tener VARIOS usuarios AYUDANTES (rol 3).
 *   4. Un usuario AYUDANTE (rol 3) solo puede pertenecer a UN fundo.
 *
 *  Estas funciones validan los vínculos ANTES de crearlos/activarlos y
 *  auditan los que ya existen. Se usan desde:
 *    - controllers/musuarios_controller.php  (alta de usuario ayudante)
 *    - lib/multifundo_helper.php             (unificación de cuentas/fundos)
 *    - scripts/normalizar_normativa_fundos.php (auditoría y corrección)
 *
 *  Trabaja con la conexión de Active Record, igual que multifundo_helper.
 * ─────────────────────────────────────────────────────────────────────
 */

if (!function_exists('_norm_q')) {
    /** Ejecuta SQL y devuelve filas asociativas ([] si algo falla). */
    function _norm_q($sql)
    {
        if (!class_exists('ActiveRecord\\ConnectionManager')) {
            return array();
        }
        try {
            $conn = ActiveRecord\ConnectionManager::get_connection();
            if (!$conn) {
                return array();
            }
            $sth = $conn->query($sql);
            if (!$sth || !method_exists($sth, 'fetchAll')) {
                return array();
            }
            $rows = $sth->fetchAll(PDO::FETCH_ASSOC);
            return $rows ? $rows : array();
        } catch (Exception $e) {
            return array();
        }
    }
}

if (!function_exists('_norm_q1')) {
    /** Primera fila de una consulta (o null). */
    function _norm_q1($sql)
    {
        $rows = _norm_q($sql);
        return ($rows && isset($rows[0])) ? $rows[0] : null;
    }
}

if (!function_exists('_norm_exec')) {
    /** Ejecuta una escritura. Devuelve true si no hubo error. */
    function _norm_exec($sql)
    {
        if (!class_exists('ActiveRecord\\ConnectionManager')) {
            return false;
        }
        try {
            $conn = ActiveRecord\ConnectionManager::get_connection();
            if (!$conn) {
                return false;
            }
            $conn->query($sql);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('norm_usuario')) {
    /** Datos básicos del usuario (id, username, mroles_id, status). */
    function norm_usuario($usuarioId)
    {
        return _norm_q1("SELECT id, username, mroles_id, status
                           FROM musuarios
                          WHERE id = " . (int) $usuarioId . " LIMIT 1");
    }
}

if (!function_exists('norm_fundo')) {
    /** Datos básicos del fundo (id, nombre, user_propietario, status). */
    function norm_fundo($fundoId)
    {
        return _norm_q1("SELECT id, nombre, user_propietario, status
                           FROM mfundos
                          WHERE id = " . (int) $fundoId . " LIMIT 1");
    }
}

if (!function_exists('norm_fundo_productores')) {
    /**
     * Productores (rol 2) vinculados a un fundo.
     *
     * @param int  $fundoId
     * @param bool $soloActivos
     * @param int  $exceptoUsuarioId  Productor que se ignora (el que ya está).
     * @param array $exceptoUsuarios  Otros usuarios que se ignoran (p. ej. los
     *        orígenes de una unificación, que están por perder el acceso).
     * @return array<int, array{id:int, username:string, status:int}>
     */
    function norm_fundo_productores($fundoId, $soloActivos = true, $exceptoUsuarioId = 0, array $exceptoUsuarios = array())
    {
        $sql = "SELECT u.id, u.username, u.status
                  FROM mfundosxusuarios l
                  JOIN musuarios u ON u.id = l.musuarios_id
                 WHERE l.mfundos_id = " . (int) $fundoId . "
                   AND l.status = 1
                   AND u.mroles_id = 2";

        if ((int) $exceptoUsuarioId > 0) {
            $sql .= " AND u.id <> " . (int) $exceptoUsuarioId;
        }

        $excluir = array();
        foreach ($exceptoUsuarios as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $excluir[$id] = true;
            }
        }
        if ($excluir) {
            $sql .= " AND u.id NOT IN (" . implode(',', array_keys($excluir)) . ")";
        }

        if ($soloActivos) {
            $sql .= " AND u.status = 1";
        }

        return _norm_q($sql . " ORDER BY u.id");
    }
}

if (!function_exists('norm_usuario_fundos')) {
    /**
     * Fundos con enlace ACTIVO del usuario.
     *
     * @param int   $usuarioId
     * @param bool  $soloActivos
     * @param int   $exceptoFundoId  Fundo que se ignora (el que se está asignando).
     * @return array<int, array{id:int, nombre:string}>
     */
    function norm_usuario_fundos($usuarioId, $soloActivos = true, $exceptoFundoId = 0)
    {
        $sql = "SELECT f.id, f.nombre
                  FROM mfundosxusuarios l
                  JOIN mfundos f ON f.id = l.mfundos_id
                 WHERE l.musuarios_id = " . (int) $usuarioId . "
                   AND l.status = 1";

        if ((int) $exceptoFundoId > 0) {
            $sql .= " AND f.id <> " . (int) $exceptoFundoId;
        }
        if ($soloActivos) {
            $sql .= " AND f.status = 1";
        }

        return _norm_q($sql . " ORDER BY f.id");
    }
}

if (!function_exists('norm_validar_vinculo')) {
    /**
     * Valida si se puede vincular (o activar) un usuario en un fundo.
     *
     * @param int   $usuarioId
     * @param int   $fundoId
     * @param array $usuariosQuePierdenAcceso Usuarios que van a quedar fuera
     *        del fundo en la misma operación (p. ej. los orígenes de una
     *        unificación): se excluyen de la validación de "un solo productor".
     * @return array<int, string> Mensajes de error (vacío = cumple la normativa).
     */
    function norm_validar_vinculo($usuarioId, $fundoId, array $usuariosQuePierdenAcceso = array())
    {
        $errores = array();

        $usuario = norm_usuario($usuarioId);
        if (!$usuario) {
            return array('El usuario indicado no existe.');
        }

        $fundo = norm_fundo($fundoId);
        if (!$fundo) {
            return array('El fundo indicado no existe.');
        }

        $rol = (int) $usuario['mroles_id'];

        if ($rol === 2) {
            // Regla 2: un fundo solo puede tener un productor. Se cuentan
            // TAMBIÉN los productores inactivos (su enlace sigue existiendo):
            // para cederle el fundo a otro productor hay que incluir al
            // anterior entre los usuarios que pierden el acceso.
            $otros = norm_fundo_productores($fundoId, false, $usuarioId, $usuariosQuePierdenAcceso);
            if (!empty($otros)) {
                $nombres = array();
                foreach ($otros as $o) {
                    $nombres[] = $o['username'] . ((int) $o['status'] === 0 ? ' (inactivo)' : '');
                }
                $errores[] = 'El fundo "' . trim((string) $fundo['nombre']) . '" ya pertenece al productor '
                    . implode(', ', $nombres) . '. Un fundo solo puede tener un usuario productor.';
            }
        } elseif ($rol === 3) {
            // Regla 4: un ayudante solo puede pertenecer a un fundo.
            $fundos = norm_usuario_fundos($usuarioId, true, $fundoId);
            if (!empty($fundos)) {
                $nombres = array();
                foreach ($fundos as $f) {
                    $nombres[] = trim((string) $f['nombre']);
                }
                $errores[] = 'El usuario ' . $usuario['username'] . ' ya pertenece al fundo "'
                    . implode(', ', $nombres) . '". Un usuario ayudante solo puede estar en un fundo.';
            }
        }

        return $errores;
    }
}

if (!function_exists('norm_auditar')) {
    /**
     * Revisa TODA la base y devuelve las violaciones de la normativa.
     *
     * @return array{
     *   fundos_multi_productor: array<int, array{fundo:int, nombre:string, productores:array}>,
     *   ayudantes_multi_fundo:  array<int, array{usuario:int, username:string, fundos:array}>
     * }
     */
    function norm_auditar()
    {
        // Fundos con más de un productor activo (o sin productor activo).
        $filas = _norm_q(
            "SELECT l.mfundos_id AS fundo, f.nombre,
                    count(*) AS productores,
                    count(*) FILTER (WHERE u.status = 1) AS activos,
                    string_agg(u.username, ', ' ORDER BY u.id) AS usuarios
               FROM mfundosxusuarios l
               JOIN musuarios u ON u.id = l.musuarios_id
               JOIN mfundos f ON f.id = l.mfundos_id
              WHERE l.status = 1
                AND f.status = 1
                AND u.mroles_id = 2
           GROUP BY l.mfundos_id, f.nombre
             HAVING count(*) > 1
           ORDER BY l.mfundos_id"
        );

        $multi_productor = array();
        foreach ($filas as $f) {
            $multi_productor[] = array(
                'fundo' => (int) $f['fundo'],
                'nombre' => trim((string) $f['nombre']),
                'productores' => (int) $f['productores'],
                'activos' => (int) $f['activos'],
                'usuarios' => (string) $f['usuarios'],
            );
        }

        // Ayudantes con más de un fundo activo.
        $filas = _norm_q(
            "SELECT l.musuarios_id AS usuario, u.username, u.status,
                    count(*) AS fundos,
                    string_agg(f.nombre, ', ' ORDER BY l.mfundos_id) AS nombres
               FROM mfundosxusuarios l
               JOIN musuarios u ON u.id = l.musuarios_id
               JOIN mfundos f ON f.id = l.mfundos_id
              WHERE l.status = 1
                AND u.mroles_id = 3
           GROUP BY l.musuarios_id, u.username, u.status
             HAVING count(*) > 1
           ORDER BY l.musuarios_id"
        );

        $multi_fundo = array();
        foreach ($filas as $f) {
            $multi_fundo[] = array(
                'usuario' => (int) $f['usuario'],
                'username' => trim((string) $f['username']),
                'status' => (int) $f['status'],
                'fundos' => (int) $f['fundos'],
                'nombres' => (string) $f['nombres'],
            );
        }

        return array(
            'fundos_multi_productor' => $multi_productor,
            'ayudantes_multi_fundo'  => $multi_fundo,
        );
    }
}

if (!function_exists('norm_productor_a_conservar')) {
    /**
     * Decide qué productor se queda con un fundo cuando hay varios
     * (para la corrección de datos):
     *   1. El `user_propietario` del fundo, si está entre los vinculados.
     *   2. Si no, el primer productor ACTIVO.
     *   3. Si no, el primero por id.
     * Devuelve el id del usuario que conserva el fundo, o 0.
     */
    function norm_productor_a_conservar($fundoId)
    {
        $fundo = norm_fundo($fundoId);
        $productores = norm_fundo_productores($fundoId, false);

        if (empty($productores)) {
            return 0;
        }

        if ($fundo && (int) $fundo['user_propietario'] > 0) {
            foreach ($productores as $p) {
                if ((int) $p['id'] === (int) $fundo['user_propietario']) {
                    return (int) $p['id'];
                }
            }
        }

        foreach ($productores as $p) {
            if ((int) $p['status'] === 1) {
                return (int) $p['id'];
            }
        }

        return (int) $productores[0]['id'];
    }
}
