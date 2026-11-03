<?php

namespace Api;

/**
 * Registro de las sincronizaciones del modo offline.
 *
 * Guarda UNA fila por petición atendida por `api/sync_controller.php`
 * (pull, push o subida de foto) en la tabla `sync_offline_log`, con los
 * contadores de la tanda y el detalle de cada registro rechazado.
 *
 * Diseño:
 *  - Se inicializa al entrar al controlador con `iniciar($input, $usuario)`.
 *  - Cada acción va completando el contexto con `anotar()` y `itemFallido()`.
 *  - La fila se escribe en el SHUTDOWN de PHP, no al final del flujo: todas
 *    las respuestas del API terminan con `ApiResponse::ok()`/`error()`, que
 *    hacen `exit`. Con el shutdown se registra también el 403 de un
 *    dispositivo desvinculado o el 500 de una excepción, sin tocar el flujo
 *    normal de las respuestas.
 *  - NUNCA puede romper la sincronización: todo va en try/catch y cualquier
 *    problema queda solo en el error_log del servidor.
 */
class SyncLogger
{
    /** Contexto de la petición en curso (null = no se inicializó). */
    private static $contexto = null;

    /** Evita escribir dos veces la misma fila. */
    private static $volcado = false;

    /**
     * Etiquetas legibles de los módulos. Se usan en el mensaje resumen y
     * en la pantalla web (controllers/mdispositivos_controller.php).
     */
    private static $etiquetas = array(
        'animales'              => 'Animales',
        'nacimientos'           => 'Nacimientos',
        'servicios'             => 'Servicios',
        'control_sanitario'     => 'Control sanitario',
        'control_sanitario_lotes' => 'Control sanitario por lote',
        'produccion_leche'      => 'Producción de leche',
        'pesos'                 => 'Pesajes',
        'desincorporacion'      => 'Desincorporaciones',
        'movimiento_rebano'     => 'Movimiento de rebaño',
        'fotos'                 => 'Fotos de animales',
    );

    public static function etiquetaModulo($modulo)
    {
        $modulo = (string) $modulo;
        if ($modulo === '') {
            return 'General';
        }
        return isset(self::$etiquetas[$modulo]) ? self::$etiquetas[$modulo] : $modulo;
    }

    /**
     * Abre el registro de la petición. Debe llamarse justo después de
     * autenticar, para que un 401/403 posterior también quede anotado.
     */
    public static function iniciar(array $input, array $usuario = array())
    {
        if (self::$contexto !== null) {
            return;
        }

        $dispositivo = isset($input['dispositivo_id']) ? trim((string) $input['dispositivo_id']) : '';
        if (!self::esUuid($dispositivo)) {
            $dispositivo = '';
        }

        $fundo = 0;
        if (isset($input['fundo_id'])) {
            $fundo = (int) $input['fundo_id'];
        }
        if ($fundo <= 0 && isset($usuario['fundo_id'])) {
            $fundo = (int) $usuario['fundo_id'];
        }

        self::$contexto = array(
            'dispositivo_id'     => $dispositivo !== '' ? $dispositivo : null,
            'nombre_dispositivo' => null,
            'musuarios_id'       => isset($usuario['usuario_id']) ? (int) $usuario['usuario_id'] : null,
            'mfundos_id'         => $fundo > 0 ? $fundo : null,
            'modo_offline'       => null,
            'accion'             => self::texto(isset($input['action']) ? $input['action'] : '', 15),
            'modulo'             => self::texto(isset($input['modulo']) ? $input['modulo'] : '', 40),
            'resultado'          => 'ok',
            'total'              => 0,
            'exitos'             => 0,
            'conflictos'         => 0,
            'errores'            => 0,
            'mensaje'            => '',
            'detalle'            => array(),
            'inicio'             => microtime(true),
        );

        register_shutdown_function(array(__CLASS__, 'volcar'));
    }

    /**
     * Completa/actualiza el contexto. Solo se aceptan las claves conocidas
     * para que una acción no pueda escribir columnas arbitrarias.
     */
    public static function anotar(array $datos)
    {
        if (self::$contexto === null) {
            return;
        }

        foreach ($datos as $clave => $valor) {
            switch ($clave) {
                case 'dispositivo_id':
                    $valor = trim((string) $valor);
                    self::$contexto[$clave] = self::esUuid($valor) ? $valor : null;
                    break;
                case 'nombre_dispositivo':
                    self::$contexto[$clave] = self::texto($valor, 120);
                    break;
                case 'modo_offline':
                    self::$contexto[$clave] = self::texto($valor, 20);
                    break;
                case 'accion':
                    self::$contexto[$clave] = self::texto($valor, 15);
                    break;
                case 'modulo':
                    self::$contexto[$clave] = self::texto($valor, 40);
                    break;
                case 'musuarios_id':
                case 'mfundos_id':
                case 'total':
                case 'exitos':
                case 'conflictos':
                case 'errores':
                    self::$contexto[$clave] = (int) $valor;
                    break;
                case 'mensaje':
                    self::$contexto[$clave] = self::texto($valor, 500);
                    break;
            }
        }

        if (self::$contexto['musuarios_id'] === 0) {
            self::$contexto['musuarios_id'] = null;
        }
        if (self::$contexto['mfundos_id'] === 0) {
            self::$contexto['mfundos_id'] = null;
        }
    }

    /**
     * Deja constancia de un registro rechazado o en conflicto.
     *
     * @param array $fallo { modulo, referencia, uuid_local, tipo, mensaje, codigo }
     */
    public static function itemFallido(array $fallo)
    {
        if (self::$contexto === null) {
            return;
        }

        if (count(self::$contexto['detalle']) >= 100) {
            return; // tope de seguridad: no inflar el jsonb
        }

        $tipo = isset($fallo['tipo']) ? (string) $fallo['tipo'] : 'error';

        self::$contexto['detalle'][] = array(
            'tipo'       => self::texto($tipo, 20),
            'modulo'     => self::texto(isset($fallo['modulo']) ? $fallo['modulo'] : self::$contexto['modulo'], 40),
            'referencia' => self::texto(isset($fallo['referencia']) ? $fallo['referencia'] : '', 120),
            'uuid_local' => self::texto(isset($fallo['uuid_local']) ? $fallo['uuid_local'] : '', 64),
            'mensaje'    => self::texto(isset($fallo['mensaje']) ? $fallo['mensaje'] : '', 500),
            'codigo'     => self::texto(isset($fallo['codigo']) ? $fallo['codigo'] : '', 60),
        );
    }

    /**
     * Registra que la PETICIÓN completa falló (403 dispositivo
     * desvinculado, 422 módulo inválido, 500 excepción…). Lo llama
     * ApiResponse::error() antes de cortar la ejecución.
     */
    public static function falloPeticion($mensaje, $codigoHttp = 0, array $extra = array())
    {
        if (self::$contexto === null) {
            return;
        }

        self::$contexto['resultado'] = 'error';
        if (self::$contexto['mensaje'] === '') {
            self::$contexto['mensaje'] = self::texto($mensaje, 500);
        }

        if (count(self::$contexto['detalle']) < 100) {
            self::$contexto['detalle'][] = array(
                'tipo'       => 'peticion',
                'modulo'     => self::texto(self::$contexto['modulo'], 40),
                'referencia' => '',
                'uuid_local' => '',
                'mensaje'    => self::texto($mensaje, 500),
                'codigo'     => self::texto(isset($extra['codigo']) ? $extra['codigo'] : 'http_' . (int) $codigoHttp, 60),
            );
        }
    }

    /**
     * La petición terminó bien: se deduce el resultado a partir de los
     * contadores. Lo llama ApiResponse::ok() antes del exit.
     */
    public static function cerrarPeticion()
    {
        if (self::$contexto === null || self::$contexto['resultado'] === 'error') {
            return;
        }

        if (self::$contexto['errores'] > 0 || self::$contexto['conflictos'] > 0) {
            self::$contexto['resultado'] = 'parcial';
        } else {
            self::$contexto['resultado'] = 'ok';
        }
    }

    /**
     * Escribe la fila. Se ejecuta en el shutdown de PHP.
     */
    public static function volcar()
    {
        if (self::$volcado || self::$contexto === null) {
            return;
        }
        self::$volcado = true;

        $c = self::$contexto;

        // Los pull vacíos son el caso normal de una revisión periódica:
        // no aportan al resumen y llenarían la tabla de ruido.
        if ($c['accion'] === 'pull' && $c['resultado'] === 'ok' && (int) $c['total'] === 0) {
            return;
        }

        try {
            if ($c['nombre_dispositivo'] === null && $c['dispositivo_id'] !== null) {
                $c = self::completarDatosDispositivo($c);
            }

            $duracion = (int) round((microtime(true) - $c['inicio']) * 1000);
            if ($duracion < 0) {
                $duracion = 0;
            }
            if ($duracion > 600000) {
                $duracion = 600000;
            }

            $detalle = null;
            if (!empty($c['detalle'])) {
                $detalle = json_encode($c['detalle'], JSON_UNESCAPED_UNICODE);
                if ($detalle === false) {
                    $detalle = null;
                }
            }

            $conexion = \Musuario::connection();
            $conexion->query(
                'INSERT INTO sync_offline_log
                    (dispositivo_id, nombre_dispositivo, musuarios_id, mfundos_id, modo_offline,
                     accion, modulo, resultado, total, exitos, conflictos, errores,
                     duracion_ms, ip, mensaje, detalle, created)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?::jsonb, ?)',
                array(
                    $c['dispositivo_id'],
                    $c['nombre_dispositivo'],
                    $c['musuarios_id'],
                    $c['mfundos_id'],
                    $c['modo_offline'],
                    $c['accion'] !== '' ? $c['accion'] : 'sync',
                    $c['modulo'] !== '' ? $c['modulo'] : null,
                    $c['resultado'],
                    (int) $c['total'],
                    (int) $c['exitos'],
                    (int) $c['conflictos'],
                    (int) $c['errores'],
                    $duracion,
                    self::ipCliente(),
                    $c['mensaje'] !== '' ? $c['mensaje'] : null,
                    $detalle,
                    date('Y-m-d H:i:s'),
                )
            );
        } catch (\Throwable $e) {
            // Nunca romper la respuesta del API por un fallo del registro.
            error_log('[SYNC LOG] ' . $e->getMessage());
        }
    }

    /**
     * Completa nombre y modo del equipo desde el vínculo offline. Sirve
     * para las peticiones que fallan ANTES de validar el vínculo (o para
     * clientes que no lo mandan), así el renglón igual dice qué equipo fue.
     */
    private static function completarDatosDispositivo(array $c)
    {
        try {
            $conexion = \Musuario::connection();
            $parametros = array($c['dispositivo_id']);
            $sql = 'SELECT nombre_dispositivo, modo_offline, musuarios_id, mfundos_id
                      FROM usuario_dispositivo_offline
                     WHERE dispositivo_id = ?';
            if ($c['mfundos_id'] !== null) {
                $sql .= ' AND mfundos_id = ?';
                $parametros[] = $c['mfundos_id'];
            }
            $sql .= ' ORDER BY status DESC, id DESC LIMIT 1';

            $fila = $conexion->query($sql, $parametros)->fetch();
            if ($fila) {
                if ($c['nombre_dispositivo'] === null && !empty($fila['nombre_dispositivo'])) {
                    $c['nombre_dispositivo'] = self::texto($fila['nombre_dispositivo'], 120);
                }
                if ($c['modo_offline'] === null && !empty($fila['modo_offline'])) {
                    $c['modo_offline'] = self::texto($fila['modo_offline'], 20);
                }
                if ($c['musuarios_id'] === null && !empty($fila['musuarios_id'])) {
                    $c['musuarios_id'] = (int) $fila['musuarios_id'];
                }
                if ($c['mfundos_id'] === null && !empty($fila['mfundos_id'])) {
                    $c['mfundos_id'] = (int) $fila['mfundos_id'];
                }
            }
        } catch (\Throwable $e) {
            error_log('[SYNC LOG] ' . $e->getMessage());
        }

        return $c;
    }

    /** ¿Es un UUID con formato válido? (la columna es de tipo uuid) */
    private static function esUuid($valor)
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            (string) $valor
        );
    }

    /** Texto recortado a la longitud de la columna (nunca null). */
    private static function texto($valor, $largo)
    {
        $valor = is_scalar($valor) ? (string) $valor : '';
        $valor = trim(preg_replace('/\s+/u', ' ', $valor));
        if (function_exists('mb_substr')) {
            return mb_substr($valor, 0, $largo, 'UTF-8');
        }
        return substr($valor, 0, $largo);
    }

    /** IP real del cliente, sin consultar servicios externos. */
    private static function ipCliente()
    {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? trim((string) $_SERVER['REMOTE_ADDR']) : '';
        if ($ip !== '' && !filter_var($ip, FILTER_VALIDATE_IP)) {
            $ip = '';
        }
        return $ip !== '' ? substr($ip, 0, 45) : null;
    }
}
