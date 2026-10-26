<?php
/**
 * =====================================================================
 * lib/conexiones_helper.php — DURACIÓN DE LAS SESIONES
 * ---------------------------------------------------------------------
 * El historial de conexiones guarda una fila por login. Para saber
 * cuánto tiempo estuvo conectado cada usuario hay que actualizar esa
 * fila mientras navega (latido) y cerrarla al salir.
 *
 *   · bovs_cx_latido()        → marca la última actividad ("última
 *                               transacción") de la sesión actual.
 *   · bovs_cx_cerrar_sesion() → cierra la sesión al hacer logout.
 *   · bovs_cx_resolver_sesion() → encuentra la fila de la sesión actual
 *                               (o reclama la última abierta del usuario
 *                               si el login ocurrió antes de esta versión).
 *
 * Es compatible con PHP 7.4 / 8.x, no lanza excepciones y NUNCA debe
 * romper la petición: cualquier fallo se ignora en silencio.
 *
 * El latido se llama desde lib/validar_session.php (páginas) y desde
 * lib/sanitizar.php (acciones/AJAX). La propia consulta limita las
 * escrituras a una vez cada 20 segundos por sesión.
 * =====================================================================
 */

require_once __DIR__ . '/geoip_helper.php';

if (!function_exists('bovs_cx_sesion_actual')) {
    /** Id de la fila de conexiones_historial de la sesión actual (0 = ninguna). */
    function bovs_cx_sesion_actual()
    {
        return isset($_SESSION['cx_historial_id']) ? (int) $_SESSION['cx_historial_id'] : 0;
    }
}

if (!function_exists('bovs_cx_resolver_sesion')) {
    /**
     * Devuelve el id de la fila del historial que corresponde a la sesión
     * actual. Si no está guardado en la sesión, lo busca por el hash de la
     * sesión y, como último recurso, reclama la última sesión abierta del
     * usuario de las últimas 24 h (caso de usuarios ya logueados cuando se
     * desplegó esta función).
     *
     * @param int $uid Usuario (por defecto, el de la sesión).
     * @return int 0 si no hay fila.
     */
    function bovs_cx_resolver_sesion($uid = 0)
    {
        $id = bovs_cx_sesion_actual();
        if ($id > 0) {
            return $id;
        }

        /* Las sesiones demo trabajan contra otra base: no se mezclan con
           el historial de conexiones de la base real. */
        if (!empty($_SESSION['demo'])) {
            return 0;
        }

        $uid = (int) $uid;
        if ($uid <= 0) {
            $uid = isset($_SESSION['idusuariox']) ? (int) $_SESSION['idusuariox'] : 0;
        }
        if ($uid <= 0) {
            return 0;
        }

        $hash = bovs_cx_hash_sesion();

        if ($hash !== '') {
            $filas = bovs_cx_q(
                'SELECT id
                   FROM conexiones_historial
                  WHERE musuarios_id = ?
                    AND cerrada = false
                    AND session_id_hash = ?
                  ORDER BY id DESC
                  LIMIT 1',
                array($uid, $hash)
            );
            if (!empty($filas[0]['id'])) {
                $id = (int) $filas[0]['id'];
                $_SESSION['cx_historial_id'] = $id;
                return $id;
            }
        }

        $filas = bovs_cx_q(
            'SELECT id
               FROM conexiones_historial
              WHERE musuarios_id = ?
                AND cerrada = false
                AND session_id_hash IS NULL
                AND fecha >= now() - interval \'24 hours\'
              ORDER BY id DESC
              LIMIT 1',
            array($uid)
        );

        if (!empty($filas[0]['id'])) {
            $id = (int) $filas[0]['id'];
            if ($hash !== '') {
                bovs_cx_x(
                    'UPDATE conexiones_historial
                        SET session_id_hash = ?
                      WHERE id = ? AND session_id_hash IS NULL',
                    array($hash, $id)
                );
            }
            $_SESSION['cx_historial_id'] = $id;
            return $id;
        }

        return 0;
    }
}

if (!function_exists('bovs_cx_latido')) {
    /**
     * Registra la actividad de la sesión actual. Devuelve true si la
     * consulta se ejecutó (el UPDATE puede no haber cambiado nada por el
     * límite de 20 s).
     *
     * @param string $ruta Página o acción que se está ejecutando.
     */
    function bovs_cx_latido($ruta = '')
    {
        if (PHP_SAPI === 'cli') {
            return false;
        }
        if (empty($_SESSION['idusuariox']) || !empty($_SESSION['demo'])) {
            return false;
        }

        $id = bovs_cx_resolver_sesion();
        if ($id <= 0) {
            return false;
        }

        $ruta = substr(trim((string) $ruta), 0, 255);

        return bovs_cx_x(
            'UPDATE conexiones_historial
                SET ultima_actividad = now(),
                    ultima_ruta = COALESCE(NULLIF(?, \'\'), ultima_ruta),
                    duracion_seg = GREATEST(0, EXTRACT(EPOCH FROM (now() - fecha))::int)
              WHERE id = ?
                AND cerrada = false
                AND (ultima_actividad IS NULL
                     OR ultima_actividad < now() - interval \'20 seconds\')',
            array($ruta, $id)
        );
    }
}

if (!function_exists('bovs_cx_cerrar_sesion')) {
    /**
     * Cierra la sesión actual (logout). Deja registrada la hora de fin y
     * la duración total.
     *
     * @param string $motivo logout | timeout | admin
     */
    function bovs_cx_cerrar_sesion($motivo = 'logout')
    {
        if (PHP_SAPI === 'cli') {
            return false;
        }

        $id = bovs_cx_resolver_sesion();
        if ($id <= 0) {
            unset($_SESSION['cx_historial_id']);
            return false;
        }
        $ok = bovs_cx_x(
            'UPDATE conexiones_historial
                SET fecha_fin = COALESCE(ultima_actividad, now()),
                    duracion_seg = GREATEST(0, EXTRACT(EPOCH FROM (COALESCE(ultima_actividad, now()) - fecha))::int),
                    cerrada = true,
                    cerrada_por = ?
              WHERE id = ?
                AND cerrada = false',
            array(substr((string) $motivo, 0, 20), $id)
        );

        unset($_SESSION['cx_historial_id']);

        return $ok;
    }
}
