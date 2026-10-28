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
    SELECT h.id,
           h.musuarios_id,
           h.fecha,
           h.ip,
           h.pais,
           h.ciudad,
           h.ultima_actividad,
           h.fecha_fin,
           h.cerrada,
           h.cierre_solicitado_at,
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
           max(fecha)                      AS ultima,
           max(coalesce(ultima_actividad, fecha)) AS ultima_actividad,
           count(*) FILTER (WHERE ultima_actividad IS NOT NULL) AS n_dur,
           avg(EXTRACT(EPOCH FROM (COALESCE(fecha_fin, ultima_actividad) - fecha)))
               FILTER (WHERE ultima_actividad IS NOT NULL) AS dur_prom,
           sum(GREATEST(0, EXTRACT(EPOCH FROM (COALESCE(fecha_fin, ultima_actividad) - fecha))))
               FILTER (WHERE ultima_actividad IS NOT NULL) AS dur_total,
           count(*) FILTER (WHERE NOT cerrada
                              AND ultima_actividad >= now() - interval '15 minutes') AS en_linea
      FROM base
     GROUP BY musuarios_id
),
ult AS (
    SELECT DISTINCT ON (musuarios_id) musuarios_id, ip, pais, ciudad, fecha,
           ultima_actividad, fecha_fin
      FROM base
     ORDER BY musuarios_id, fecha DESC
),
/* Sesión realmente ABIERTA: sin cerrar y con latido reciente. Es la que se
   puede cerrar de forma remota desde esta pantalla. */
abierta AS (
    SELECT DISTINCT ON (musuarios_id) musuarios_id, id, ultima_actividad,
           cierre_solicitado_at
      FROM base
     WHERE cerrada = false
       AND ultima_actividad >= now() - interval '15 minutes'
     ORDER BY musuarios_id, ultima_actividad DESC
)
SELECT u.id                                AS musuarios_id,
       u.username,
       u.name                              AS nombre,
       u.mroles_id,
       coalesce(a.total, 0)                AS total,
       coalesce(a.dias_activos, 0)         AS dias_activos,
       coalesce(a.ips, 0)                  AS ips,
       coalesce(a.paises, 0)               AS paises,
       coalesce(a.n_dur, 0)                AS sesiones_medidas,
       coalesce(a.en_linea, 0)             AS en_linea,
       CASE WHEN a.n_dur > 0 THEN round(a.dur_prom)::int END AS duracion_prom,
       CASE WHEN a.n_dur > 0 THEN a.dur_total::bigint END    AS duracion_total,
       to_char(a.ultima_actividad AT TIME ZONE 'America/Caracas', 'YYYY-MM-DD HH24:MI') AS ultima_actividad,
       CASE WHEN ult.ultima_actividad IS NULL THEN NULL
            ELSE GREATEST(0, EXTRACT(EPOCH FROM
                 (COALESCE(ult.fecha_fin, ult.ultima_actividad) - ult.fecha))::int)
       END AS ultima_duracion,
       to_char(a.primera AT TIME ZONE 'America/Caracas', 'YYYY-MM-DD HH24:MI') AS primera,
       to_char(a.ultima  AT TIME ZONE 'America/Caracas', 'YYYY-MM-DD HH24:MI') AS ultima,
       coalesce(ult.ip, '')                AS ultima_ip,
       coalesce(ult.pais, '')              AS ultimo_pais,
       coalesce(ult.ciudad, '')            AS ultima_ciudad,
       ab.id                               AS sesion_historial_id,
       ab.cierre_solicitado_at             AS sesion_cierre_at,
       CASE WHEN ab.id IS NOT NULL THEN 'activa'
            WHEN a.ultima IS NULL    THEN 'sin_datos'
            ELSE 'cerrada' END             AS sesion_estado,
       (a.ultima AT TIME ZONE 'America/Caracas')::date AS ultima_dia,
       ((a.ultima AT TIME ZONE 'America/Caracas')::date
        - (a.primera AT TIME ZONE 'America/Caracas')::date) AS dias_ventana
  FROM musuarios u
  LEFT JOIN agg a   ON a.musuarios_id = u.id
  LEFT JOIN ult     ON ult.musuarios_id = u.id
  LEFT JOIN abierta ab ON ab.musuarios_id = u.id
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
        'duracion_prom'   => ($f['duracion_prom'] === null ? null : (int)$f['duracion_prom']),
        'duracion_prom_txt' => bovs_cx_duracion_texto($f['duracion_prom']),
        'duracion_total'  => ($f['duracion_total'] === null ? null : (int)$f['duracion_total']),
        'duracion_total_txt' => bovs_cx_duracion_texto($f['duracion_total']),
        'sesiones_medidas' => (int)$f['sesiones_medidas'],
        'en_linea'        => (int)$f['en_linea'],
        'ultima_actividad' => (string)$f['ultima_actividad'],
        'ultima_duracion' => ($f['ultima_duracion'] === null ? null : (int)$f['ultima_duracion']),
        'ultima_duracion_txt' => bovs_cx_duracion_texto($f['ultima_duracion']),
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
        'sesion_estado'   => (string)$f['sesion_estado'],
        'sesion_historial_id' => ($f['sesion_historial_id'] === null ? null : (int)$f['sesion_historial_id']),
        'sesion_cierre_at'    => $f['sesion_cierre_at'],
    );
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
