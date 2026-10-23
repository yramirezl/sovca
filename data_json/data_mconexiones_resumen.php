<?php
/**
 * data_mconexiones_resumen.php — RESUMEN por usuario (para medir frecuencia).
 *
 * Parámetros GET:
 *   dias = 7 | 30 | 90 | 365 | 0 (0 = todo el historial)
 *
 * Devuelve una fila por usuario ACTIVO del sistema (rol distinto de SUPER
 * USUARIO). Los usuarios sin accesos en el periodo aparecen con total = 0,
 * así se detectan de un vistazo las cuentas que no están usando el sistema.
 *
 * Campos calculados:
 *   total          conexiones en el periodo
 *   dias_activos   días distintos con al menos un acceso
 *   frecuencia     cada cuántos días entra (promedio entre días activos)
 *   por_dia        promedio de accesos por día natural del periodo
 *   dias_sin_entrar  días transcurridos desde el último acceso
 *   estado         Activo / Reciente / Ocasional / Inactivo / Sin actividad
 */

require_once '../lib/geoip_helper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$__rol = (int)(isset($_SESSION['rolx']) ? $_SESSION['rolx'] : 0);
if (!in_array($__rol, array(1, 4), true)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array());
    exit;
}

$dias = isset($_GET['dias']) ? (int)$_GET['dias'] : 30;
if ($dias < 0) {
    $dias = 0;
}
$periodo = ($dias > 0) ? $dias : 3650;

$sql = "
WITH base AS (
    SELECT h.musuarios_id,
           h.fecha,
           h.ip,
           h.pais,
           h.ciudad,
           (h.fecha AT TIME ZONE 'America/Caracas')::date AS dia
      FROM conexiones_historial h
     WHERE h.resultado = 'ok'
       AND h.fecha >= now() - (? || ' days')::interval
),
agg AS (
    SELECT musuarios_id,
           count(*)                        AS total,
           count(DISTINCT dia)             AS dias_activos,
           count(DISTINCT nullif(ip, ''))  AS ips,
           count(DISTINCT nullif(pais, '')) AS paises,
           min(fecha)                      AS primera,
           max(fecha)                      AS ultima
      FROM base
     GROUP BY musuarios_id
),
ult AS (
    SELECT DISTINCT ON (musuarios_id) musuarios_id, ip, pais, ciudad, fecha
      FROM base
     ORDER BY musuarios_id, fecha DESC
)
SELECT u.id                                AS musuarios_id,
       u.username,
       u.name                              AS nombre,
       u.mroles_id,
       coalesce(a.total, 0)                AS total,
       coalesce(a.dias_activos, 0)         AS dias_activos,
       coalesce(a.ips, 0)                  AS ips,
       coalesce(a.paises, 0)               AS paises,
       to_char(a.primera AT TIME ZONE 'America/Caracas', 'YYYY-MM-DD HH24:MI') AS primera,
       to_char(a.ultima  AT TIME ZONE 'America/Caracas', 'YYYY-MM-DD HH24:MI') AS ultima,
       coalesce(ult.ip, '')                AS ultima_ip,
       coalesce(ult.pais, '')              AS ultimo_pais,
       coalesce(ult.ciudad, '')            AS ultima_ciudad,
       (a.ultima AT TIME ZONE 'America/Caracas')::date AS ultima_dia,
       ((a.ultima AT TIME ZONE 'America/Caracas')::date
        - (a.primera AT TIME ZONE 'America/Caracas')::date) AS dias_ventana
  FROM musuarios u
  LEFT JOIN agg a   ON a.musuarios_id = u.id
  LEFT JOIN ult     ON ult.musuarios_id = u.id
 WHERE u.status = 1
   AND u.mroles_id <> 4
 ORDER BY (a.ultima IS NULL), a.ultima DESC NULLS LAST, u.username
";

$filas = bovs_cx_q($sql, array((string)$periodo));

$hoy = new DateTime('now', new DateTimeZone('America/Caracas'));
$hoy->setTime(0, 0, 0);

$resultado = array();
foreach ($filas as $f) {
    $total   = (int)$f['total'];
    $diasAct = (int)$f['dias_activos'];

    /* Frecuencia: cada cuántos días entra realmente.
       Se mide entre el primer y el último día activo; con un solo día
       activo no hay intervalo que promediar. */
    $frecuencia = null;
    if ($diasAct > 1) {
        $ventana = (int)$f['dias_ventana'];
        if ($ventana > 0) {
            $frecuencia = round($ventana / ($diasAct - 1), 1);
        }
    }

    /* Promedio de accesos por día natural del periodo analizado. */
    $porDia = round($total / max($periodo, 1), 2);

    $diasSinEntrar = null;
    if (!empty($f['ultima_dia'])) {
        $ultima = new DateTime($f['ultima_dia'], new DateTimeZone('America/Caracas'));
        $ultima->setTime(0, 0, 0);
        $diasSinEntrar = (int)$hoy->diff($ultima)->days;
    }

    if ($total === 0) {
        $estado = 'Sin actividad';
    } elseif ($diasSinEntrar !== null && $diasSinEntrar <= 1) {
        $estado = 'Activo';
    } elseif ($diasSinEntrar !== null && $diasSinEntrar <= 7) {
        $estado = 'Reciente';
    } elseif ($diasSinEntrar !== null && $diasSinEntrar <= 30) {
        $estado = 'Ocasional';
    } else {
        $estado = 'Inactivo';
    }

    $resultado[] = array(
        'musuarios_id'    => (int)$f['musuarios_id'],
        'username'        => (string)$f['username'],
        'nombre'          => (string)$f['nombre'],
        'total'           => $total,
        'dias_activos'    => $diasAct,
        'frecuencia'      => $frecuencia,
        'por_dia'         => $porDia,
        'ips'             => (int)$f['ips'],
        'paises'          => (int)$f['paises'],
        'primera'         => $f['primera'],
        'ultima'          => $f['ultima'],
        'dias_sin_entrar' => $diasSinEntrar,
        'ultima_ip'       => $f['ultima_ip'],
        'ultimo_pais'     => $f['ultimo_pais'],
        'ultima_ciudad'   => $f['ultima_ciudad'],
        'ubicacion'       => bovs_geo_etiqueta($f['ultimo_pais'], $f['ultima_ciudad']),
        'estado'          => $estado,
    );
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
