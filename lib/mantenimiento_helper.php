<?php
/**
 * =====================================================================
 * lib/mantenimiento_helper.php — MODO MANTENIMIENTO DEL SISTEMA
 * ---------------------------------------------------------------------
 * Permite dejar el servidor de PRODUCCIÓN en "solo administradores":
 * los perfiles 1 (Administrador) y 4 (Super Usuario) pueden entrar y
 * navegar (con Modo soporte), mientras que los productores (2) y los
 * ayudantes/encargados (3) quedan fuera del sistema web y de la app
 * offline.
 *
 * ¿DÓNDE VIVE EL INTERRUPTOR?
 *   1. Variable de entorno `SOVCA_MANTENIMIENTO` (Apache SetEnv / export).
 *      Tiene prioridad: 1/on/true/si/yes = activo.
 *   2. Archivo `file/mantenimiento/estado.json` (lo escribe el panel
 *      views/mantenimiento o el script scripts/mantenimiento.php).
 *   3. Archivo opcional `config/mantenimiento.php` (despliegue manual:
 *      `<?php return array('activo' => true, 'motivo' => '...');`).
 *
 * Si no hay nada configurado, el modo está APAGADO (falla abierto: es
 * preferible que el sistema funcione a que un archivo faltante deje a
 * todo el mundo sin acceso).
 *
 * Compatible con PHP 7.4 / 8.x. No depende de ActiveRecord ni de la
 * base de datos a propósito: se consulta en login, layouts, endpoints
 * y API.
 * =====================================================================
 */

date_default_timezone_set('America/Caracas');

if (!function_exists('bovs_mantenimiento_dir')) {

    /** Roles que SÍ pueden entrar durante el mantenimiento. */
    function bovs_mantenimiento_roles_permitidos()
    {
        $config = bovs_mantenimiento_config();
        if (isset($config['roles']) && is_array($config['roles']) && $config['roles']) {
            $roles = array();
            foreach ($config['roles'] as $rol) {
                $roles[] = (int) $rol;
            }
            return $roles;
        }
        return array(1, 4);
    }

    /** Carpeta donde se guarda el estado (configurable por entorno). */
    function bovs_mantenimiento_dir()
    {
        $dir = getenv('SOVCA_MANTENIMIENTO_DIR');
        if ($dir === false || $dir === '') {
            $dir = dirname(__DIR__) . '/file/mantenimiento';
        }
        return rtrim($dir, '/\\');
    }

    function bovs_mantenimiento_ruta_estado()
    {
        return bovs_mantenimiento_dir() . '/estado.json';
    }

    function bovs_mantenimiento_ruta_bitacora()
    {
        return bovs_mantenimiento_dir() . '/bitacora.log';
    }

    /** Limpia la caché por petición (después de activar o desactivar). */
    function bovs_mantenimiento_cache_reset()
    {
        $GLOBALS['bovs_mantenimiento_cache'] = null;
    }

    /**
     * Estado crudo (sin resolver la caducidad).
     *
     * @return array{activo:bool,motivo:string,mensaje:string,desde:string,hasta:string,
     *               duracion_min:int,usuario:string,usuario_id:int,actualizado:string,
     *               roles:array,origen:string}
     */
    function bovs_mantenimiento_config()
    {
        if (isset($GLOBALS['bovs_mantenimiento_cache']) && is_array($GLOBALS['bovs_mantenimiento_cache'])) {
            return $GLOBALS['bovs_mantenimiento_cache'];
        }

        $config = array(
            'activo'       => false,
            'motivo'       => '',
            'mensaje'      => '',
            'desde'        => '',
            'hasta'        => '',
            'duracion_min' => 0,
            'usuario'      => '',
            'usuario_id'   => 0,
            'actualizado'  => '',
            'roles'        => array(1, 4),
            'origen'       => 'ninguno',
        );

        /* 1) Variable de entorno: manda sobre todo lo demás. */
        $env = getenv('SOVCA_MANTENIMIENTO');
        if ($env !== false && trim((string) $env) !== '') {
            $valor = strtolower(trim((string) $env));
            $activo = in_array($valor, array('1', 'on', 'true', 'si', 'sí', 'yes'), true);
            $config['activo'] = $activo;
            $config['origen'] = 'entorno';
            if ($activo) {
                $config['mensaje'] = 'El sistema está en mantenimiento programado. Solo el equipo administrador puede acceder en este momento.';
            }
            $GLOBALS['bovs_mantenimiento_cache'] = $config;
            return $config;
        }

        /* 2) Archivo de estado (panel de administración / script CLI). */
        $ruta = bovs_mantenimiento_ruta_estado();
        if (is_file($ruta)) {
            $contenido = @file_get_contents($ruta);
            $datos = json_decode((string) $contenido, true);
            if (is_array($datos)) {
                foreach ($datos as $clave => $valor) {
                    if ($clave === 'roles') {
                        $config['roles'] = is_array($valor) ? $valor : $config['roles'];
                    } else {
                        $config[$clave] = $valor;
                    }
                }
                $config['activo'] = !empty($config['activo']);
                $config['origen'] = 'archivo';
            }
        }

        /* 3) Configuración fija por despliegue (opcional, solo si el
              archivo de estado no existe). */
        if ($config['origen'] === 'ninguno') {
            $fijo = dirname(__DIR__) . '/config/mantenimiento.php';
            if (is_file($fijo)) {
                $datos = @include $fijo;
                if (is_array($datos)) {
                    foreach ($datos as $clave => $valor) {
                        $config[$clave] = $valor;
                    }
                    $config['activo'] = !empty($config['activo']);
                    $config['origen'] = 'config';
                }
            }
        }

        $GLOBALS['bovs_mantenimiento_cache'] = $config;
        return $config;
    }

    /**
     * ¿Está activo AHORA? Si el registro tiene una hora de fin y ya pasó,
     * se considera terminado (así una prueba no deja el sistema cerrado
     * para siempre por un olvido).
     */
    function bovs_mantenimiento_activo()
    {
        $config = bovs_mantenimiento_config();
        if (empty($config['activo'])) {
            return false;
        }
        if (!empty($config['hasta'])) {
            $hasta = strtotime((string) $config['hasta']);
            if ($hasta !== false && time() >= $hasta) {
                return false;
            }
        }
        return true;
    }

    /** ¿El rol indicado puede usar el sistema mientras hay mantenimiento? */
    function bovs_mantenimiento_permite_rol($rol)
    {
        $rol = (int) $rol;
        return in_array($rol, bovs_mantenimiento_roles_permitidos(), true);
    }

    /** Texto para mostrar al usuario bloqueado (NO expone el motivo interno). */
    function bovs_mantenimiento_mensaje()
    {
        $config = bovs_mantenimiento_config();
        $mensaje = trim((string) $config['mensaje']);
        if ($mensaje === '') {
            $mensaje = 'El sistema está en mantenimiento programado. Solo el equipo administrador puede acceder en este momento.';
        }
        return strip_tags($mensaje);
    }

    /** Minutos que faltan para el cierre automático (0 = sin límite). */
    function bovs_mantenimiento_minutos_restantes()
    {
        $config = bovs_mantenimiento_config();
        if (empty($config['hasta'])) {
            return 0;
        }
        $hasta = strtotime((string) $config['hasta']);
        if ($hasta === false) {
            return 0;
        }
        $restantes = (int) ceil(($hasta - time()) / 60);
        return $restantes > 0 ? $restantes : 0;
    }

    /**
     * Estado completo para la pantalla de administración.
     *
     * @return array
     */
    function bovs_mantenimiento_estado()
    {
        $config = bovs_mantenimiento_config();
        $ruta = bovs_mantenimiento_ruta_estado();

        $config['vigente']            = bovs_mantenimiento_activo();
        $config['minutos_restantes']  = bovs_mantenimiento_minutos_restantes();
        $config['roles']              = bovs_mantenimiento_roles_permitidos();
        $config['archivo']            = $ruta;
        $config['archivo_existe']     = is_file($ruta);
        $config['archivo_escribible'] = is_file($ruta) ? is_writable($ruta) : is_writable(bovs_mantenimiento_dir());
        $config['mensaje_publico']    = bovs_mantenimiento_mensaje();

        return $config;
    }

    /**
     * Escribe el estado. Devuelve array(ok, msg).
     *
     * @param array $opciones activo, motivo, mensaje, duracion_min,
     *                        usuario, usuario_id
     */
    function bovs_mantenimiento_guardar($opciones)
    {
        $dir = bovs_mantenimiento_dir();
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0777, true) && !is_dir($dir)) {
                return array('ok' => false, 'msg' => 'No existe la carpeta ' . $dir . ' y el servidor no pudo crearla. Créala con permisos de escritura para Apache (o ejecuta scripts/mantenimiento.php --instalar).');
            }
            @chmod($dir, 0777);
        }

        $activo = !empty($opciones['activo']);
        $motivo = isset($opciones['motivo']) ? trim((string) $opciones['motivo']) : '';
        $duracion = isset($opciones['duracion_min']) ? (int) $opciones['duracion_min'] : 0;
        if ($duracion < 0) {
            $duracion = 0;
        }
        if ($duracion > 1440) {
            $duracion = 1440;
        }

        $ahora = date('Y-m-d H:i:s');
        $hasta = ($activo && $duracion > 0) ? date('Y-m-d H:i:s', time() + ($duracion * 60)) : '';

        $datos = array(
            'activo'       => $activo,
            'motivo'       => $motivo,
            'mensaje'      => isset($opciones['mensaje']) ? trim((string) $opciones['mensaje']) : '',
            'desde'        => $activo ? $ahora : '',
            'hasta'        => $hasta,
            'duracion_min' => $activo ? $duracion : 0,
            'usuario'      => isset($opciones['usuario']) ? (string) $opciones['usuario'] : '',
            'usuario_id'   => isset($opciones['usuario_id']) ? (int) $opciones['usuario_id'] : 0,
            'actualizado'  => $ahora,
            // Se conservan los roles permitidos vigentes (por si vienen de
            // config/mantenimiento.php) al reescribir el estado.
            'roles'        => bovs_mantenimiento_roles_permitidos(),
        );

        $json = json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false) {
            return array('ok' => false, 'msg' => 'No se pudo serializar el estado del mantenimiento.');
        }

        $ruta = bovs_mantenimiento_ruta_estado();
        $temporal = $ruta . '.tmp';
        $escrito = @file_put_contents($temporal, $json . PHP_EOL, LOCK_EX);

        if ($escrito === false) {
            return array('ok' => false, 'msg' => 'No se pudo escribir ' . $ruta . '. Revisa los permisos (chmod 666 del archivo y 777 de la carpeta).');
        }

        @chmod($temporal, 0666);
        if (!@rename($temporal, $ruta)) {
            @unlink($temporal);
            return array('ok' => false, 'msg' => 'No se pudo reemplazar ' . $ruta . '. Revisa los permisos de la carpeta.');
        }
        @chmod($ruta, 0666);

        bovs_mantenimiento_cache_reset();

        bovs_mantenimiento_bitacora_registrar(array(
            'accion'     => $activo ? 'activar' : 'desactivar',
            'motivo'     => $motivo,
            'duracion'   => $duracion,
            'hasta'      => $hasta,
            'usuario'    => isset($opciones['usuario']) ? (string) $opciones['usuario'] : '',
            'usuario_id' => isset($opciones['usuario_id']) ? (int) $opciones['usuario_id'] : 0,
            'origen'     => isset($opciones['origen']) ? (string) $opciones['origen'] : 'panel',
            'nota'       => isset($opciones['nota']) ? (string) $opciones['nota'] : '',
        ));

        return array('ok' => true, 'msg' => $activo ? 'Modo mantenimiento activado.' : 'Modo mantenimiento desactivado.');
    }

    /** Activa el modo mantenimiento (motivo obligatorio). */
    function bovs_mantenimiento_activar($motivo, $duracion_min, $usuario, $usuario_id, $opciones = array())
    {
        $motivo = trim((string) $motivo);
        if (function_exists('mb_strlen')) {
            $largo = mb_strlen($motivo, 'UTF-8');
        } else {
            $largo = strlen($motivo);
        }
        if ($largo < 5) {
            return array('ok' => false, 'msg' => 'Indica el motivo del mantenimiento (mínimo 5 caracteres).');
        }

        $datos = array(
            'activo'       => true,
            'motivo'       => $motivo,
            'mensaje'      => isset($opciones['mensaje']) ? $opciones['mensaje'] : '',
            'duracion_min' => (int) $duracion_min,
            'usuario'      => (string) $usuario,
            'usuario_id'   => (int) $usuario_id,
            'origen'       => isset($opciones['origen']) ? $opciones['origen'] : 'panel',
        );

        return bovs_mantenimiento_guardar($datos);
    }

    /** Desactiva el modo mantenimiento. */
    function bovs_mantenimiento_desactivar($usuario, $usuario_id, $nota = '', $origen = 'panel')
    {
        return bovs_mantenimiento_guardar(array(
            'activo'     => false,
            'motivo'     => '',
            'mensaje'    => '',
            'usuario'    => (string) $usuario,
            'usuario_id' => (int) $usuario_id,
            'origen'     => $origen,
            'nota'       => (string) $nota,
        ));
    }

    /** Agrega una línea a la bitácora (JSON por línea). */
    function bovs_mantenimiento_bitacora_registrar($evento)
    {
        $registro = array(
            'fecha'      => date('Y-m-d H:i:s'),
            'accion'     => isset($evento['accion']) ? (string) $evento['accion'] : '',
            'motivo'     => isset($evento['motivo']) ? (string) $evento['motivo'] : '',
            'duracion'   => isset($evento['duracion']) ? (int) $evento['duracion'] : 0,
            'hasta'      => isset($evento['hasta']) ? (string) $evento['hasta'] : '',
            'usuario'    => isset($evento['usuario']) ? (string) $evento['usuario'] : '',
            'usuario_id' => isset($evento['usuario_id']) ? (int) $evento['usuario_id'] : 0,
            'origen'     => isset($evento['origen']) ? (string) $evento['origen'] : 'panel',
            'nota'       => isset($evento['nota']) ? (string) $evento['nota'] : '',
            'ip'         => isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : 'cli',
        );

        $linea = json_encode($registro, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        @file_put_contents(bovs_mantenimiento_ruta_bitacora(), $linea, FILE_APPEND | LOCK_EX);
    }

    /**
     * Últimos eventos de la bitácora (más recientes primero).
     *
     * @return array<int,array>
     */
    function bovs_mantenimiento_bitacora($limite = 30)
    {
        $limite = max(1, (int) $limite);
        $ruta = bovs_mantenimiento_ruta_bitacora();
        if (!is_file($ruta)) {
            return array();
        }

        $lineas = @file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lineas)) {
            return array();
        }

        $salida = array();
        foreach (array_reverse($lineas) as $linea) {
            $datos = json_decode((string) $linea, true);
            if (is_array($datos)) {
                $salida[] = $datos;
                if (count($salida) >= $limite) {
                    break;
                }
            }
        }
        return $salida;
    }

    /**
     * Cierra la sesión de un usuario NO administrador cuando el sistema
     * entra en mantenimiento y lo manda al login con el aviso.
     * Solo para scripts web con sesión escribible (no usar en
     * sanitizar.php, que abre la sesión en modo read_and_close).
     */
    function bovs_mantenimiento_bloquear_sesion_web($destino)
    {
        if (!bovs_mantenimiento_activo()) {
            return false;
        }

        $rol = isset($_SESSION['rolx']) ? (int) $_SESSION['rolx'] : 0;
        $uid = isset($_SESSION['idusuariox']) ? (int) $_SESSION['idusuariox'] : 0;

        if ($uid <= 0 || bovs_mantenimiento_permite_rol($rol)) {
            return false;
        }

        $_SESSION = array();

        if (ini_get('session.use_cookies')) {
            $parametros = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $parametros['path'],
                $parametros['domain'],
                !empty($parametros['secure']),
                !empty($parametros['httponly'])
            );
        }

        @session_destroy();

        $destino = (string) $destino;
        if ($destino === '') {
            $destino = '../login';
        }
        $separador = strpos($destino, '?') === false ? '?' : '&';

        if (!headers_sent()) {
            header('Location: ' . $destino . $separador . 'stat=mantenimiento');
        }
        exit();
    }

    /** Respuesta JSON estándar para endpoints bloqueados. */
    function bovs_mantenimiento_respuesta_json()
    {
        $estado = bovs_mantenimiento_estado();

        if (!headers_sent()) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
        }

        echo json_encode(array(
            'ok'            => false,
            'resultado'     => 'error',
            'error'         => 'mantenimiento',
            'codigo'        => 'mantenimiento',
            'mantenimiento' => true,
            'mensaje'       => bovs_mantenimiento_mensaje(),
            'msg'           => bovs_mantenimiento_mensaje(),
            'hasta'         => isset($estado['hasta']) ? $estado['hasta'] : '',
            'minutos'       => isset($estado['minutos_restantes']) ? (int) $estado['minutos_restantes'] : 0,
        ), JSON_UNESCAPED_UNICODE);

        exit();
    }
}
