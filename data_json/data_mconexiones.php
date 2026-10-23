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
    );
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($resultado, JSON_UNESCAPED_UNICODE);