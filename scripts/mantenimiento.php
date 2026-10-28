<?php
/**
 * =====================================================================
 * scripts/mantenimiento.php — MODO MANTENIMIENTO POR CONSOLA
 * ---------------------------------------------------------------------
 * Para activar/desactivar el cierre del sistema de PRODUCCIÓN sin
 * entrar a la web (útil cuando se despliega y todavía no hay sesión).
 *
 *   php scripts/mantenimiento.php --instalar
 *       Crea file/mantenimiento/ con permisos para Apache.
 *
 *   php scripts/mantenimiento.php --estado
 *       Muestra el estado actual.
 *
 *   php scripts/mantenimiento.php --on --motivo="Pruebas de carga" --minutos=120
 *       Cierra el sistema (solo entran los administradores del sistema).
 *
 *   php scripts/mantenimiento.php --off --nota="Terminaron las pruebas"
 *       Vuelve a abrir el sistema.
 *
 * Opciones: --motivo=, --mensaje=, --minutos=N (0 = sin límite),
 *           --nota=, --usuario=, --json
 * =====================================================================
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('ok' => false, 'msg' => 'Este script solo puede ejecutarse por consola.'));
    exit;
}

date_default_timezone_set('America/Caracas');

require_once __DIR__ . '/../lib/mantenimiento_helper.php';

$opciones = getopt('', array(
    'instalar',
    'estado',
    'on',
    'off',
    'motivo:',
    'mensaje:',
    'minutos:',
    'nota:',
    'usuario:',
    'json',
    'help',
));

$salidaJson = isset($opciones['json']);
$usuario    = isset($opciones['usuario']) ? (string) $opciones['usuario'] : 'CLI';

function mtto_cli_imprimir($texto)
{
    echo $texto . PHP_EOL;
}

function mtto_cli_respuesta($datos, $salidaJson)
{
    if ($salidaJson) {
        echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
        return;
    }

    if (!empty($datos['msg'])) {
        mtto_cli_imprimir(($datos['ok'] ? '[OK] ' : '[ERROR] ') . $datos['msg']);
    }

    if (isset($datos['estado']) && is_array($datos['estado'])) {
        $e = $datos['estado'];
        mtto_cli_imprimir('');
        mtto_cli_imprimir('Modo mantenimiento: ' . ((!empty($e['vigente'])) ? 'ACTIVO (solo administradores del sistema)' : 'INACTIVO'));
        mtto_cli_imprimir('  Interruptor guardado en : ' . $e['archivo']);
        mtto_cli_imprimir('  Archivo escribible       : ' . (!empty($e['archivo_escribible']) ? 'sí' : 'NO'));
        mtto_cli_imprimir('  Origen                   : ' . $e['origen']);
        if (!empty($e['desde'])) {
            mtto_cli_imprimir('  Desde                    : ' . $e['desde']);
        }
        if (!empty($e['hasta'])) {
            mtto_cli_imprimir('  Cierre automático        : ' . $e['hasta']);
        }
        if (!empty($e['motivo'])) {
            mtto_cli_imprimir('  Motivo                   : ' . $e['motivo']);
        }
        if (!empty($e['usuario'])) {
            mtto_cli_imprimir('  Activado por             : ' . $e['usuario']);
        }
    }
}

/* ----------------------------------------------------------------
   --instalar : crear carpeta y archivo con permisos de escritura
   ---------------------------------------------------------------- */
if (isset($opciones['instalar'])) {
    $dir = bovs_mantenimiento_dir();
    $ok = true;

    if (!is_dir($dir) && !@mkdir($dir, 0777, true)) {
        $ok = false;
    }
    if ($ok) {
        @chmod($dir, 0777);
    }

    $ruta = bovs_mantenimiento_ruta_estado();
    if ($ok && !is_file($ruta)) {
        $inicial = array(
            'activo'       => false,
            'motivo'       => '',
            'mensaje'      => '',
            'desde'        => '',
            'hasta'        => '',
            'duracion_min' => 0,
            'usuario'      => '',
            'usuario_id'   => 0,
            'actualizado'  => '',
        );
        $ok = @file_put_contents($ruta, json_encode($inicial, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL) !== false;
    }
    if ($ok) {
        @chmod($ruta, 0666);
    }

    $bitacora = bovs_mantenimiento_ruta_bitacora();
    if ($ok && !is_file($bitacora)) {
        $ok = @file_put_contents($bitacora, '') !== false;
    }
    if ($ok) {
        @chmod($bitacora, 0666);
    }

    mtto_cli_respuesta(array(
        'ok'     => $ok,
        'msg'    => $ok
            ? 'Estructura lista en ' . $dir
            : 'No se pudo preparar ' . $dir . '. Revisa los permisos del usuario que ejecuta el script.',
        'estado' => bovs_mantenimiento_estado(),
    ), $salidaJson);
    exit($ok ? 0 : 1);
}

/* ----------------------------------------------------------------
   --on : activar
   ---------------------------------------------------------------- */
if (isset($opciones['on'])) {
    $motivo   = isset($opciones['motivo']) ? (string) $opciones['motivo'] : '';
    $mensaje  = isset($opciones['mensaje']) ? (string) $opciones['mensaje'] : '';
    $minutos  = isset($opciones['minutos']) ? (int) $opciones['minutos'] : 120;

    if (trim($motivo) === '') {
        $motivo = 'Mantenimiento programado por consola (' . $usuario . ')';
    }

    $resultado = bovs_mantenimiento_activar($motivo, $minutos, $usuario, 0, array(
        'mensaje' => $mensaje,
        'origen'  => 'cli',
    ));

    $resultado['estado'] = bovs_mantenimiento_estado();
    mtto_cli_respuesta($resultado, $salidaJson);
    exit(!empty($resultado['ok']) ? 0 : 1);
}

/* ----------------------------------------------------------------
   --off : desactivar
   ---------------------------------------------------------------- */
if (isset($opciones['off'])) {
    $nota = isset($opciones['nota']) ? (string) $opciones['nota'] : '';

    $resultado = bovs_mantenimiento_desactivar($usuario, 0, $nota, 'cli');

    $resultado['estado'] = bovs_mantenimiento_estado();
    mtto_cli_respuesta($resultado, $salidaJson);
    exit(!empty($resultado['ok']) ? 0 : 1);
}

/* ----------------------------------------------------------------
   --estado (o sin opciones)
   ---------------------------------------------------------------- */
$estado = bovs_mantenimiento_estado();

if ($salidaJson) {
    echo json_encode(array('ok' => true, 'estado' => $estado), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
}

mtto_cli_imprimir('SOVCA — modo mantenimiento');
mtto_cli_imprimir('=========================');

$env = getenv('SOVCA_MANTENIMIENTO');
if ($env !== false && trim((string) $env) !== '') {
    mtto_cli_imprimir('⚠ La variable de entorno SOVCA_MANTENIMIENTO=' . trim((string) $env) . ' tiene prioridad sobre el archivo.');
    mtto_cli_imprimir('');
}

mtto_cli_respuesta(array('ok' => true, 'msg' => '', 'estado' => $estado), false);

mtto_cli_imprimir('');
mtto_cli_imprimir('Uso:');
mtto_cli_imprimir('  php scripts/mantenimiento.php --on  --motivo="..." --minutos=120');
mtto_cli_imprimir('  php scripts/mantenimiento.php --off --nota="..."');
mtto_cli_imprimir('  php scripts/mantenimiento.php --estado');
mtto_cli_imprimir('  php scripts/mantenimiento.php --instalar');
