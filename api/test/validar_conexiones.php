<?php
/**
 * VALIDACIÓN DEL HISTORIAL DE CONEXIONES.
 *
 * Comprueba que una misma sesión/equipo NO genere una fila nueva cada vez
 * que llega un pulso, y que la duración se acumule en la misma fila:
 *
 *   1. dos registros seguidos de la misma sesión → una sola fila;
 *   2. la duración se acumula (no queda en 0 s);
 *   3. una conexión sin actividad caduca y se cierra con cerrada_por=timeout
 *      y la hora REAL de caducidad (la última actividad);
 *   4. al volver a entrar después de caducar, sí se crea una fila nueva.
 *
 * Uso (desde la raíz del proyecto):
 *   php api/test/validar_conexiones.php
 *
 * Variables opcionales: SOVCA_TEST_USUARIO (por defecto 1).
 * Borra todo lo que crea.
 */

chdir(dirname(__DIR__) . '/..');
require_once 'lib/geoip_helper.php';
require_once 'lib/conexiones_helper.php';

$USUARIO = (int) (getenv('SOVCA_TEST_USUARIO') ?: 1);
$IP = '203.0.113.77';          // rango reservado para documentación (RFC 5737)
$UA = 'PruebaConexiones/1.0';
$SID = 'pruebaconexiones' . bin2hex(random_bytes(6));

$ok = 0;
$fallos = 0;

function comprobar(bool $condicion, string $descripcion, string $detalle = ''): void
{
    global $ok, $fallos;
    if ($condicion) {
        $ok++;
        echo '  OK    ' . $descripcion . ($detalle !== '' ? '  [' . $detalle . ']' : '') . "\n";
    } else {
        $fallos++;
        echo '  FALLA ' . $descripcion . ($detalle !== '' ? '  [' . $detalle . ']' : '') . "\n";
    }
}

function filas_prueba(string $ip, string $ua): array
{
    return bovs_cx_q(
        'SELECT id, fecha, ultima_actividad, fecha_fin, duracion_seg, cerrada, cerrada_por, session_id_hash
           FROM conexiones_historial
          WHERE ip = ? AND user_agent = ?
          ORDER BY id',
        array($ip, $ua)
    );
}

function limpiar(string $ip, string $ua): void
{
    bovs_cx_x('DELETE FROM conexiones_historial WHERE ip = ? AND user_agent = ?', array($ip, $ua));
}

echo "Validación del historial de conexiones\n";
echo "Usuario {$USUARIO} · IP {$IP}\n";

limpiar($IP, $UA);

// Simular la sesión del navegador (hash estable entre peticiones).
$_SERVER['REMOTE_ADDR'] = $IP;
$_SERVER['HTTP_USER_AGENT'] = $UA;
$_COOKIE[session_name()] = $SID;
$_SESSION = array('idusuariox' => $USUARIO);
unset($_SESSION['cx_historial_id']);

try {
    echo "\n=== 1. Dos pulsos de la misma sesión ===\n";
    comprobar(bovs_conexion_registrar($USUARIO, 'web'), 'El primer pulso registra la conexión');
    $primera = filas_prueba($IP, $UA);
    comprobar(count($primera) === 1, 'Se crea una fila', 'filas=' . count($primera));
    $idPrimera = (int) ($primera[0]['id'] ?? 0);

    comprobar(bovs_conexion_registrar($USUARIO, 'web'), 'El segundo pulso (misma sesión) se registra');
    $despues = filas_prueba($IP, $UA);
    comprobar(count($despues) === 1, 'NO se crea una fila nueva', 'filas=' . count($despues));
    comprobar((int) ($despues[0]['id'] ?? 0) === $idPrimera, 'Se reutiliza la misma conexión', 'id=' . (int) ($despues[0]['id'] ?? 0));

    echo "\n=== 2. La duración se acumula ===\n";
    // Simular que la conexión empezó hace 7 minutos y que hay actividad.
    bovs_cx_x(
        "UPDATE conexiones_historial
            SET fecha = now() - interval '7 minutes',
                ultima_actividad = now() - interval '6 minutes',
                duracion_seg = NULL
          WHERE id = ?",
        array($idPrimera)
    );
    bovs_cx_actualizar_actividad($idPrimera, '/sovca/views/inicio/', true);
    $conLatido = filas_prueba($IP, $UA)[0] ?? array();
    $dur = (int) ($conLatido['duracion_seg'] ?? -1);
    comprobar($dur >= 380 && $dur <= 460, 'El latido acumula la duración desde el inicio', 'duracion_seg=' . $dur);

    $hash = (string) ($conLatido['session_id_hash'] ?? '');
    comprobar($hash !== '' && $hash === hash('sha256', $SID), 'La fila guarda el hash de la sesión (nunca el id en claro)', substr($hash, 0, 12) . '…');

    echo "\n=== 3. Conexión caducada ===\n";
    bovs_cx_x(
        "UPDATE conexiones_historial
            SET ultima_actividad = now() - interval '40 minutes'
          WHERE id = ?",
        array($idPrimera)
    );
    $cerradas = bovs_cx_cerrar_caducadas($USUARIO);
    $caducada = filas_prueba($IP, $UA)[0] ?? array();
    comprobar($cerradas >= 1, 'El barrido cierra las conexiones sin actividad', 'cerradas=' . $cerradas);
    comprobar(in_array($caducada['cerrada'], array(true, 't', '1', 1), true), 'La fila queda marcada como cerrada');
    comprobar((string) ($caducada['cerrada_por'] ?? '') === 'timeout', 'El motivo es "timeout" (no logout)', (string) ($caducada['cerrada_por'] ?? '—'));

    $fin = (string) ($caducada['fecha_fin'] ?? '');
    $ult = (string) ($caducada['ultima_actividad'] ?? '');
    comprobar($fin !== '' && $fin === $ult, 'La hora de fin es la última actividad (cuándo caducó de verdad)', $fin);

    echo "\n=== 4. Volver a entrar después de caducar ===\n";
    unset($_SESSION['cx_historial_id']);
    comprobar(bovs_conexion_registrar($USUARIO, 'web'), 'El nuevo acceso se registra');
    $final = filas_prueba($IP, $UA);
    comprobar(count($final) === 2, 'Se crea una conexión nueva (la anterior ya estaba cerrada)', 'filas=' . count($final));
    $nueva = end($final);
    comprobar(in_array($nueva['cerrada'], array(false, 'f', '0', 0), true), 'La conexión nueva está abierta');
} catch (Throwable $e) {
    echo "\nEXCEPCIÓN: " . $e->getMessage() . "\n";
    $fallos++;
} finally {
    echo "\n=== Limpieza ===\n";
    limpiar($IP, $UA);
    echo "  Filas de prueba borradas.\n";
}

echo "\nResultado: {$ok} correctas, {$fallos} con falla\n";
exit($fallos === 0 ? 0 : 1);
