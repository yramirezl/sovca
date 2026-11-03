<?php
/**
 * data_mconexiones.php — HISTORIAL de conexiones (una fila por acceso).
 *
 * Parámetros GET:
 *   dias    = 7 | 30 | 90 | 365 | 0 (0 = todo el historial)
 *   usuario = id de musuarios (opcional) para ver el historial de una sola
 *             cuenta, que es lo que abre el detalle desde «Resumen por usuario».
 *
 * Devuelve un array de objetos ordenado por fecha DESC. El orden final lo
 * aplica DataTables, pero se entrega ya ordenado por si se consume directo.
 */

require_once '../lib/geoip_helper.php';

$minutosTimeout = defined('SOVCA_CX_TIMEOUT_MIN') ? (int) SOVCA_CX_TIMEOUT_MIN : 15;

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
$usuario = isset($_GET['usuario']) ? (int)$_GET['usuario'] : 0;
$limite = 3000;   // techo de seguridad para no enviar payloads enormes

$sql = "SELECT h.id,
               to_char(h.fecha AT TIME ZONE 'America/Caracas', 'YYYY-MM-DD HH24:MI') AS fecha,
               coalesce(nullif(h.username, ''), u.username, '—') AS username,
               coalesce(nullif(h.nombre, ''), u.name, '—')     AS nombre,
               coalesce(h.ip, '')        AS ip,
               coalesce(h.pais, '')      AS pais,
               coalesce(h.ciudad, '')    AS ciudad,
               coalesce(h.region, '')    AS region,
               coalesce(h.user_agent, '') AS user_agent,
               coalesce(h.origen, 'web') AS origen,
               coalesce(h.cerrada_por, '') AS cerrada_por,
               coalesce(h.ultima_ruta, '') AS ultima_ruta,
               coalesce(to_char(h.fecha AT TIME ZONE 'America/Caracas', 'YYYY-MM-DD HH24:MI'), '') AS inicio,
               coalesce(to_char(h.ultima_actividad AT TIME ZONE 'America/Caracas', 'YYYY-MM-DD HH24:MI'), '') AS ultima_actividad,
               coalesce(to_char(COALESCE(h.fecha_fin, CASE WHEN NOT h.cerrada THEN h.ultima_actividad END)
                                AT TIME ZONE 'America/Caracas', 'YYYY-MM-DD HH24:MI'), '') AS fin,
               /* En curso: la duración se cuenta hasta AHORA y se acumula
                  con cada latido (no se congela en el último pulso). */
               CASE WHEN h.ultima_actividad IS NULL THEN NULL
                    ELSE GREATEST(0, EXTRACT(EPOCH FROM (
                         COALESCE(h.fecha_fin,
                                  CASE WHEN h.cerrada THEN h.ultima_actividad ELSE now() END)
                         - h.fecha))::int)
               END AS duracion_seg,
               /* Un solo estado con significado claro:
                    activa   = conectado ahora
                    cerrada  = cerró sesión (logout)
                    caducada = quedó sin actividad (navegador cerrado, sesión vencida) */
               CASE WHEN h.ultima_actividad IS NULL THEN 'sin_datos'
                    WHEN h.cerrada AND h.cerrada_por = 'timeout' THEN 'caducada'
                    WHEN h.cerrada THEN 'cerrada'
                    WHEN h.ultima_actividad >= now() - interval '{$minutosTimeout} minutes' THEN 'activa'
                    ELSE 'caducada'
               END AS estado,
               /* Momento que corresponde al estado, para mostrarlo junto al chip. */
               CASE WHEN h.ultima_actividad IS NULL THEN ''
                    WHEN NOT h.cerrada THEN
                         to_char(h.fecha AT TIME ZONE 'America/Caracas', 'YYYY-MM-DD HH24:MI')
                    ELSE
                         to_char(COALESCE(h.fecha_fin, h.ultima_actividad)
                                 AT TIME ZONE 'America/Caracas', 'YYYY-MM-DD HH24:MI')
               END AS estado_en,
               coalesce(u.status, 0)     AS usuario_activo
          FROM conexiones_historial h
          LEFT JOIN musuarios u ON u.id = h.musuarios_id
         WHERE h.resultado = 'ok'";

$params = array();
if ($usuario > 0) {
    $sql .= " AND h.musuarios_id = ?";
    $params[] = $usuario;
}
if ($dias > 0) {
    $sql .= " AND h.fecha >= now() - (? || ' days')::interval";
    $params[] = (string)$dias;
}

$sql .= " ORDER BY h.fecha DESC, h.id DESC LIMIT " . (int)$limite;

$filas = bovs_cx_q($sql, $params);

$resultado = array();
foreach ($filas as $f) {
    $resultado[] = array(
        'id'             => (int)$f['id'],
        'fecha'          => $f['fecha'],
        'username'       => $f['username'],
        'nombre'         => $f['nombre'],
        'ip'             => $f['ip'],
        'pais'           => $f['pais'],
        'ciudad'         => $f['ciudad'],
        'region'         => $f['region'],
        'user_agent'     => $f['user_agent'],
        'ubicacion'      => bovs_geo_etiqueta($f['pais'], $f['ciudad']),
        'dispositivo'    => bovs_cx_dispositivo($f['user_agent']),
        'origen'         => $f['origen'],
        'usuario_activo' => (int)$f['usuario_activo'],
        'inicio'         => $f['inicio'],
        'ultima_actividad' => $f['ultima_actividad'],
        'fin'            => $f['fin'],
        'duracion_seg'   => ($f['duracion_seg'] === null ? null : (int)$f['duracion_seg']),
        'duracion_txt'   => bovs_cx_duracion_texto($f['duracion_seg']),
        'estado'         => $f['estado'],
        'estado_en'      => $f['estado_en'],
        'cerrada_por'    => $f['cerrada_por'],
        'ultima_ruta'    => $f['ultima_ruta'],
    );
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($resultado, JSON_UNESCAPED_UNICODE);