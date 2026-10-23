<?php
/**
 * BOVS · Geolocalización de IP y registro del historial de conexiones.
 *
 * Compatible PHP 7.4 y 8.3: sin ??, ?->, match, str_contains ni tipos de unión.
 *
 * Reglas de diseño:
 *  - NUNCA debe romper el login: todo va envuelto en try/catch.
 *  - La consulta externa tiene timeout corto y se cachea por IP, de forma
 *    que solo la primera conexión desde una IP nueva paga la latencia.
 *  - Si no se puede resolver la ubicación, se guarda NULL y se sigue.
 */

if (!defined('BOVS_GEOIP_TIMEOUT')) {
    define('BOVS_GEOIP_TIMEOUT', 2);          // segundos por proveedor
}
if (!defined('BOVS_GEOIP_CACHE_DIAS')) {
    define('BOVS_GEOIP_CACHE_DIAS', 30);      // vigencia de un acierto
}
if (!defined('BOVS_GEOIP_FALLO_HORAS')) {
    define('BOVS_GEOIP_FALLO_HORAS', 24);     // espera antes de reintentar un fallo
}

/* ============================================================
 * Conexión y consultas
 * ============================================================ */

if (!function_exists('bovs_cx_con')) {
    /**
     * Devuelve la conexión de php-activerecord, cargándola si hace falta.
     * config/conexion.php usa rutas relativas: se incluye desde su carpeta.
     */
    function bovs_cx_con()
    {
        if (!class_exists('ActiveRecord\\ConnectionManager')) {
            $cwd = getcwd();
            @chdir(__DIR__ . '/../config');
            @include_once(__DIR__ . '/../config/conexion.php');
            if ($cwd !== false) {
                @chdir($cwd);
            }
        }
        if (!class_exists('ActiveRecord\\ConnectionManager')) {
            return null;
        }
        try {
            return ActiveRecord\ConnectionManager::get_connection();
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('bovs_cx_q')) {
    /**
     * SELECT que devuelve un array de filas asociativas. Nunca lanza.
     */
    function bovs_cx_q($sql, $params = array())
    {
        $conn = bovs_cx_con();
        if (!$conn) {
            return array();
        }
        try {
            $sth = $conn->query($sql, $params);
            if (!$sth || !method_exists($sth, 'fetchAll')) {
                return array();
            }
            $filas = $sth->fetchAll(PDO::FETCH_ASSOC);
            return $filas ? $filas : array();
        } catch (Throwable $e) {
            return array();
        }
    }
}

if (!function_exists('bovs_cx_x')) {
    /**
     * INSERT / UPDATE / DELETE. Devuelve true si no hubo error.
     */
    function bovs_cx_x($sql, $params = array())
    {
        $conn = bovs_cx_con();
        if (!$conn) {
            return false;
        }
        try {
            $conn->query($sql, $params);
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
}

/* ============================================================
 * IP
 * ============================================================ */

if (!function_exists('bovs_ip_privada')) {
    function bovs_ip_privada($ip)
    {
        $ip = trim((string)$ip);
        if ($ip === '' || $ip === 'localhost' || $ip === '::1') {
            return true;
        }
        if (stripos($ip, '::ffff:') === 0) {
            $ip = substr($ip, 7);
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $p = explode('.', $ip);
            $a = (int)$p[0];
            $b = (int)$p[1];
            if ($a === 10 || $a === 127) {
                return true;
            }
            if ($a === 172 && $b >= 16 && $b <= 31) {
                return true;
            }
            if ($a === 192 && $b === 168) {
                return true;
            }
            if ($a === 169 && $b === 254) {
                return true;
            }
            return false;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $l = strtolower($ip);
            if ($l === '::1') {
                return true;
            }
            if (strpos($l, 'fe80') === 0) {
                return true;
            }
            if (strpos($l, 'fc') === 0 || strpos($l, 'fd') === 0) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('bovs_ip_cliente')) {
    /**
     * IP real del visitante. Solo se confía en X-Forwarded-For cuando la
     * conexión directa viene de una red privada (proxy o balanceador propio).
     */
    function bovs_ip_cliente()
    {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? trim($_SERVER['REMOTE_ADDR']) : '';

        if (bovs_ip_privada($ip) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $partes = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $cand = trim($partes[0]);
            if (filter_var($cand, FILTER_VALIDATE_IP)) {
                return $cand;
            }
        }
        return $ip;
    }
}

/* ============================================================
 * Geolocalización
 * ============================================================ */

if (!function_exists('bovs_geo_http_get')) {
    function bovs_geo_http_get($url)
    {
        $timeout = (int)BOVS_GEOIP_TIMEOUT;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'BOVS/1.0');
            $out = curl_exec($ch);
            curl_close($ch);
            return is_string($out) ? $out : null;
        }

        $ctx = stream_context_create(array(
            'http' => array('timeout' => $timeout, 'ignore_errors' => true)
        ));
        $out = @file_get_contents($url, false, $ctx);
        return is_string($out) ? $out : null;
    }
}

if (!function_exists('bovs_geo_consultar_ipapi')) {
    function bovs_geo_consultar_ipapi($ip)
    {
        $url = 'http://ip-api.com/json/' . rawurlencode($ip)
             . '?fields=status,country,city,regionName&lang=es';
        $raw = bovs_geo_http_get($url);
        if ($raw === null) {
            return null;
        }
        $j = json_decode($raw, true);
        if (!is_array($j) || !isset($j['status']) || $j['status'] !== 'success') {
            return null;
        }
        $pais   = isset($j['country'])    ? trim($j['country'])    : '';
        $ciudad = isset($j['city'])       ? trim($j['city'])       : '';
        $region = isset($j['regionName']) ? trim($j['regionName']) : '';
        if ($pais === '') {
            return null;
        }
        return array('pais' => $pais, 'ciudad' => $ciudad, 'region' => $region);
    }
}

if (!function_exists('bovs_geo_consultar_geoplugin')) {
    function bovs_geo_consultar_geoplugin($ip)
    {
        $raw = bovs_geo_http_get('http://www.geoplugin.net/json.gp?ip=' . rawurlencode($ip));
        if ($raw === null) {
            return null;
        }
        $j = json_decode($raw);
        if (!is_object($j) || !isset($j->geoplugin_countryName) || $j->geoplugin_countryName === null) {
            return null;
        }
        $pais   = trim((string)$j->geoplugin_countryName);
        $ciudad = isset($j->geoplugin_city)   ? trim((string)$j->geoplugin_city)   : '';
        $region = isset($j->geoplugin_region) ? trim((string)$j->geoplugin_region) : '';
        if ($pais === '') {
            return null;
        }
        return array('pais' => $pais, 'ciudad' => $ciudad, 'region' => $region);
    }
}

if (!function_exists('bovs_geo_vacio')) {
    function bovs_geo_vacio($fuente)
    {
        return array('pais' => null, 'ciudad' => null, 'region' => null, 'fuente' => $fuente);
    }
}

if (!function_exists('bovs_cx_bool')) {
    /**
     * PDO puede devolver los booleanos de PostgreSQL como true/false o
     * como 't'/'f' según la versión: se normaliza aquí.
     */
    function bovs_cx_bool($valor)
    {
        if ($valor === true) {
            return true;
        }
        if ($valor === false || $valor === null) {
            return false;
        }
        $v = strtolower((string)$valor);
        return ($v === 't' || $v === 'true' || $v === '1' || $v === 'y' || $v === 'yes');
    }
}

if (!function_exists('bovs_geo_cache_leer')) {
    /**
     * Devuelve el resultado cacheado o null si hay que consultar.
     */
    function bovs_geo_cache_leer($ip)
    {
        $filas = bovs_cx_q(
            "SELECT pais, ciudad, region, ok,
                    (now() - actualizado) < (? || ' days')::interval AS vigente,
                    (now() - actualizado) < (? || ' hours')::interval AS reciente
               FROM geoip_cache
              WHERE ip = ?",
            array((string)BOVS_GEOIP_CACHE_DIAS, (string)BOVS_GEOIP_FALLO_HORAS, $ip)
        );
        if (empty($filas)) {
            return null;
        }
        $f = $filas[0];

        if ((int)$f['ok'] === 1 && bovs_cx_bool($f['vigente'])) {
            return array(
                'pais'   => $f['pais'],
                'ciudad' => $f['ciudad'],
                'region' => $f['region'],
                'fuente' => 'cache',
            );
        }
        if ((int)$f['ok'] === 0 && bovs_cx_bool($f['reciente'])) {
            return bovs_geo_vacio('cache-sin-datos');
        }
        return null;
    }
}

if (!function_exists('bovs_geo_cache_guardar')) {
    function bovs_geo_cache_guardar($ip, $pais, $ciudad, $region, $ok)
    {
        bovs_cx_x(
            "INSERT INTO geoip_cache (ip, pais, ciudad, region, ok, actualizado)
             VALUES (?, ?, ?, ?, ?, now())
             ON CONFLICT (ip) DO UPDATE
                SET pais = EXCLUDED.pais,
                    ciudad = EXCLUDED.ciudad,
                    region = EXCLUDED.region,
                    ok = EXCLUDED.ok,
                    actualizado = now()",
            array($ip, $pais, $ciudad, $region, $ok ? 1 : 0)
        );
    }
}

if (!function_exists('bovs_geo_localizar')) {
    /**
     * Resuelve país / ciudad / región de una IP.
     * Devuelve array('pais','ciudad','region','fuente'). Los valores pueden ser null.
     */
    function bovs_geo_localizar($ip)
    {
        $ip = trim((string)$ip);
        if ($ip === '') {
            return bovs_geo_vacio('sin-ip');
        }

        if (bovs_ip_privada($ip)) {
            return array(
                'pais'   => 'Red local',
                'ciudad' => null,
                'region' => null,
                'fuente' => 'local',
            );
        }

        $cache = bovs_geo_cache_leer($ip);
        if ($cache !== null) {
            return $cache;
        }

        $datos = bovs_geo_consultar_ipapi($ip);
        if ($datos === null) {
            $datos = bovs_geo_consultar_geoplugin($ip);
        }

        if ($datos === null) {
            bovs_geo_cache_guardar($ip, null, null, null, false);
            return bovs_geo_vacio('sin-datos');
        }

        bovs_geo_cache_guardar($ip, $datos['pais'], $datos['ciudad'], $datos['region'], true);
        $datos['fuente'] = 'externo';
        return $datos;
    }
}

if (!function_exists('bovs_geo_etiqueta')) {
    /**
     * "País, Ciudad" listo para mostrar (o solo país, o vacío).
     */
    function bovs_geo_etiqueta($pais, $ciudad)
    {
        $pais   = trim((string)$pais);
        $ciudad = trim((string)$ciudad);
        if ($pais === '') {
            return $ciudad;
        }
        if ($ciudad !== '' && strcasecmp($ciudad, $pais) !== 0) {
            return $pais . ', ' . $ciudad;
        }
        return $pais;
    }
}

/* ============================================================
 * Dispositivo (a partir del User-Agent)
 * ============================================================ */

if (!function_exists('bovs_cx_dispositivo')) {
    function bovs_cx_dispositivo($ua)
    {
        $ua = (string)$ua;
        if ($ua === '') {
            return 'Desconocido';
        }

        $so = '';
        if (stripos($ua, 'Android') !== false) {
            $so = 'Android';
        } elseif (stripos($ua, 'iPhone') !== false) {
            $so = 'iPhone';
        } elseif (stripos($ua, 'iPad') !== false) {
            $so = 'iPad';
        } elseif (stripos($ua, 'Windows Phone') !== false) {
            $so = 'Windows Phone';
        } elseif (stripos($ua, 'Windows') !== false) {
            $so = 'Windows';
        } elseif (stripos($ua, 'Mac OS X') !== false || stripos($ua, 'Macintosh') !== false) {
            $so = 'macOS';
        } elseif (stripos($ua, 'Linux') !== false) {
            $so = 'Linux';
        }

        $nav = '';
        if (stripos($ua, 'Edg/') !== false) {
            $nav = 'Edge';
        } elseif (stripos($ua, 'OPR/') !== false || stripos($ua, 'Opera') !== false) {
            $nav = 'Opera';
        } elseif (stripos($ua, 'Chrome') !== false || stripos($ua, 'CriOS') !== false) {
            $nav = 'Chrome';
        } elseif (stripos($ua, 'Firefox') !== false || stripos($ua, 'FxiOS') !== false) {
            $nav = 'Firefox';
        } elseif (stripos($ua, 'Safari') !== false) {
            $nav = 'Safari';
        }

        if ($so !== '' && $nav !== '') {
            return $so . ' · ' . $nav;
        }
        if ($so !== '') {
            return $so;
        }
        if ($nav !== '') {
            return $nav;
        }
        return 'Desconocido';
    }
}

/* ============================================================
 * Registro de conexiones
 * ============================================================ */

if (!function_exists('bovs_cx_actualizar_rastreo')) {
    /**
     * Mantiene el comportamiento legado de `mrastreosessiones`
     * (una fila por usuario) que consumen vmdataconexiones y el
     * controlador de correos de inactividad.
     */
    function bovs_cx_actualizar_rastreo($uid, $ip, $geo)
    {
        $etiqueta = bovs_geo_etiqueta($geo['pais'], $geo['ciudad']);
        if ($etiqueta === '') {
            $etiqueta = null;
        }

        // La columna legada es varchar(15): un IPv6 no cabe.
        $ip_legado = substr($ip, 0, 15);

        $filas = bovs_cx_q(
            'SELECT id FROM mrastreosessiones WHERE id_musuario = ? ORDER BY id ASC LIMIT 1',
            array((int)$uid)
        );

        if (!empty($filas)) {
            bovs_cx_x(
                'UPDATE mrastreosessiones
                    SET logueado = 1, ip = ?, pais = ?, fecha = CURRENT_DATE, hora = LOCALTIME(0), accion = ?
                  WHERE id = ?',
                array($ip_legado, $etiqueta, 'U', (int)$filas[0]['id'])
            );
            return;
        }

        bovs_cx_x(
            'INSERT INTO mrastreosessiones (logueado, id_musuario, ip, pais, fecha, hora, accion)
             VALUES (1, ?, ?, ?, CURRENT_DATE, LOCALTIME(0), ?)',
            array((int)$uid, $ip_legado, $etiqueta, 'I')
        );
    }
}

if (!function_exists('bovs_conexion_registrar')) {
    /**
     * Registra un acceso: inserta la fila del historial y actualiza el
     * rastreo legado. Devuelve true si el historial quedó guardado.
     *
     * @param int    $uid    id del usuario en musuarios
     * @param string $origen web | pwa | api | reset
     */
    function bovs_conexion_registrar($uid, $origen = 'web')
    {
        $uid = (int)$uid;
        if ($uid <= 0) {
            return false;
        }

        $conn = bovs_cx_con();
        if (!$conn) {
            return false;
        }

        $ip = bovs_ip_cliente();
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $ua = substr((string)$ua, 0, 500);

        // Copia del usuario: el historial no debe depender de musuarios.
        $username = '';
        $nombre   = '';
        $filas = bovs_cx_q('SELECT username, name FROM musuarios WHERE id = ? LIMIT 1', array($uid));
        if (!empty($filas)) {
            $username = (string)$filas[0]['username'];
            $nombre   = (string)$filas[0]['name'];
        }

        $geo = bovs_geo_localizar($ip);

        $ok = bovs_cx_x(
            'INSERT INTO conexiones_historial
                (musuarios_id, username, nombre, ip, pais, ciudad, region, user_agent, origen, resultado, fecha)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, now())',
            array(
                $uid,
                substr($username, 0, 60),
                substr($nombre, 0, 120),
                substr($ip, 0, 45),
                $geo['pais'] !== null ? substr($geo['pais'], 0, 100) : null,
                $geo['ciudad'] !== null ? substr($geo['ciudad'], 0, 100) : null,
                $geo['region'] !== null ? substr($geo['region'], 0, 100) : null,
                $ua,
                $origen,
                'ok',
            )
        );

        bovs_cx_actualizar_rastreo($uid, $ip, $geo);

        return $ok;
    }
}
