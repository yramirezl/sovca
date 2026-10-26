<?php

namespace Api;

require_once __DIR__ . '/../../models/ApiToken.php';
require_once __DIR__ . '/../../models/Musuario.php';
require_once __DIR__ . '/../../lib/mantenimiento_helper.php';

/**
 * Autenticación por token para la API (en vez de sesión PHP, que no
 * sirve para un cliente que puede pasar días sin conexión).
 *
 * El token en claro se entrega UNA sola vez al hacer login; en la base
 * de datos solo se guarda su hash SHA-256, igual que una contraseña.
 *
 * MULTIFUNDO: un usuario puede tener acceso a varios fundos
 * (mfundosxusuarios). Para no obligarlo a tener un token por fundo, el
 * token puede ser "del usuario" (api_tokens.mfundos_id NULL) y cada
 * petición indica el fundo con el que trabaja; el servidor lo valida
 * contra sus fundos con acceso activo (ver requerirFundo).
 * Los tokens viejos, atados a un fundo, siguen funcionando igual.
 */
class ApiAuth
{
    const DIAS_EXPIRACION = 30;
    const MAX_LONGITUD_DISPOSITIVO = 100;

    const ROL_ADMINISTRADOR = 1;
    const ROL_PRODUCTOR = 2;
    const ROL_ENCARGADO = 3;
    const ROL_SUPER_USUARIO = 4;

    /** Caché por petición: evita repetir el mismo SELECT en un mismo push. */
    private static array $cacheRol = [];
    private static array $cacheFundos = [];
    private static array $cacheAccesoOffline = [];

    public static function generarToken(): string
    {
        return bin2hex(random_bytes(32)); // 64 caracteres en hexadecimal
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Crea y persiste un token nuevo.
     *
     * @param int|null $fundoId Fundo al que se ata el token, o null
     *   para un token DEL USUARIO (multifundo): en ese caso cada
     *   petición indica el fundo y el servidor lo valida contra sus
     *   accesos activos.
     * @return array{token: string, expira: string}
     */
    public static function crearToken(int $usuarioId, ?int $fundoId, ?string $dispositivo): array
    {
        $token = self::generarToken();
        $expira = date('Y-m-d H:i:s', strtotime('+' . self::DIAS_EXPIRACION . ' days'));

        $registro = new \ApiToken();
        $registro->musuarios_id = $usuarioId;
        $registro->mfundos_id = ($fundoId && $fundoId > 0) ? $fundoId : null;
        $registro->token_hash = self::hashToken($token);
        $registro->dispositivo = $dispositivo
            ? substr($dispositivo, 0, self::MAX_LONGITUD_DISPOSITIVO)
            : 'desconocido';
        $registro->created = date('Y-m-d H:i:s');
        $registro->expira = $expira;
        $registro->status = 1;
        $registro->save();

        return ['token' => $token, 'expira' => $expira];
    }

    /**
     * Lee el token, ya sea del header "Authorization: Bearer <token>"
     * o del campo "token" del cuerpo JSON (respaldo). Muchos hosting
     * compartidos con Apache/mod_php descartan el header Authorization
     * antes de que llegue a PHP por una política de seguridad antigua
     * — cuando pasa eso, el campo del cuerpo permite que la API siga
     * funcionando igual sin depender de tocar la configuración del
     * servidor. Lo valida contra api_tokens y devuelve los datos del
     * usuario autenticado. Devuelve null si no hay token, es
     * inválido, o expiró.
     *
     * @param array $input El cuerpo ya decodificado de la petición
     *   (de leer_json_input()), para poder leer "token" de ahí si el
     *   header no llegó.
     * @return array{usuario_id:int, fundo_id:int, dispositivo:?string, token_id:int}|null
     */
    public static function usuarioAutenticado(array $input = []): ?array
    {
        $token = self::leerTokenDeHeader() ?: ($input['token'] ?? null);
        if (!$token) {
            return null;
        }

        $hash = self::hashToken($token);
        $ahora = date('Y-m-d H:i:s');

        $registro = \ApiToken::find('first', [
            'conditions' => [
                'token_hash = ? and status = 1 and expira > ?',
                $hash,
                $ahora,
            ],
        ]);

        if (!$registro) {
            return null;
        }

        return [
            'usuario_id' => (int) $registro->musuarios_id,
            'fundo_id' => (int) $registro->mfundos_id,
            'dispositivo' => $registro->dispositivo,
            'token_id' => (int) $registro->id,
        ];
    }

    /**
     * Igual que usuarioAutenticado(), pero corta la ejecución con un
     * 401 si no hay sesión válida. Úsalo al inicio de cada acción que
     * requiera login, pasándole el $input de leer_json_input().
     *
     * @return array{usuario_id:int, fundo_id:int, dispositivo:?string, token_id:int}
     */
    public static function requerirAutenticacion(array $input = []): array
    {
        $usuario = self::usuarioAutenticado($input);

        if (!$usuario) {
            ApiResponse::error('Token inválido, expirado o ausente. Vuelve a iniciar sesión.', 401);
        }

        // MODO MANTENIMIENTO: la sincronización de la PWA se detiene si el
        // sistema está cerrado. Los administradores (1/4) sí siguen operando.
        self::exigirMantenimientoPermitido((int) $usuario['usuario_id']);

        return $usuario;
    }

    /**
     * Corta la petición con 403 si el sistema está en mantenimiento y la
     * cuenta no pertenece a los perfiles permitidos (1 Administrador,
     * 4 Super Usuario). Responde con el código 'mantenimiento' para que
     * la PWA pueda distinguirlo de un error real de los registros.
     */
    public static function exigirMantenimientoPermitido(int $usuarioId): void
    {
        if (!\bovs_mantenimiento_activo()) {
            return;
        }

        if (!\bovs_mantenimiento_permite_rol(self::rolDeUsuario($usuarioId))) {
            ApiResponse::error(
                \bovs_mantenimiento_mensaje() . ' La sincronización se reanudará cuando termine.',
                403,
                array(
                    'codigo'        => 'mantenimiento',
                    'mantenimiento' => true,
                )
            );
        }
    }

    // =================================================================
    // MULTIFUNDO
    // =================================================================

    /** Rol del usuario (1 Administrador, 2 Productor, 3 Encargado, 4 Super Usuario). */
    public static function rolDeUsuario(int $usuarioId): int
    {
        if (!isset(self::$cacheRol[$usuarioId])) {
            // OJO: ActiveRecord\Connection::query() recibe $values por
            // REFERENCIA — por eso se pasa una variable y nunca un
            // literal, o PHP lanza "could not be passed by reference".
            $valores = [$usuarioId];
            $fila = \Musuario::connection()
                ->query('SELECT mroles_id, ultimo_fundo_id FROM musuarios WHERE id = ? LIMIT 1', $valores)
                ->fetch();
            self::$cacheRol[$usuarioId] = $fila ? (int) $fila['mroles_id'] : 0;
            self::$cacheRol['ultimo:' . $usuarioId] = $fila ? (int) ($fila['ultimo_fundo_id'] ?? 0) : 0;
        }

        return self::$cacheRol[$usuarioId];
    }

    /** Último fundo con el que el usuario trabajó (se comparte con la app web). */
    public static function fundoPrincipal(int $usuarioId): int
    {
        self::rolDeUsuario($usuarioId); // llena la caché
        return (int) (self::$cacheRol['ultimo:' . $usuarioId] ?? 0);
    }

    /**
     * Fundos a los que el usuario tiene acceso ACTIVO, con su nombre y
     * tipo de ganadería.
     *
     * Roles 1 (Administrador) y 4 (Super Usuario) ven todos los fundos
     * activos, igual que la app web; el resto, solo los que tiene en
     * mfundosxusuarios con status = 1.
     *
     * @return array<int, array{id:int, nombre:string, cid_rif:string, tipo_ganaderia_id:int}>
     */
    public static function fundosDeUsuario(int $usuarioId): array
    {
        if (isset(self::$cacheFundos[$usuarioId])) {
            return self::$cacheFundos[$usuarioId];
        }

        $rol = self::rolDeUsuario($usuarioId);

        if (in_array($rol, [1, 4], true)) {
            $sql = "SELECT f.id, f.nombre, f.cid_rif, f.mtipoganaderias_id AS tipo_ganaderia_id
                      FROM mfundos f
                     WHERE f.status > 0
                  ORDER BY f.nombre ASC";
            $valores = [];
        } else {
            $sql = "SELECT f.id, f.nombre, f.cid_rif, f.mtipoganaderias_id AS tipo_ganaderia_id
                      FROM mfundosxusuarios fxu
                INNER JOIN mfundos f ON f.id = fxu.mfundos_id AND f.status > 0
                     WHERE fxu.musuarios_id = ? AND fxu.status = 1
                  ORDER BY f.nombre ASC";
            $valores = [$usuarioId];
        }

        $filas = \Musuario::connection()->query($sql, $valores)->fetchAll();

        $salida = [];
        foreach ($filas as $f) {
            $salida[] = [
                'id' => (int) $f['id'],
                'nombre' => trim((string) $f['nombre']),
                'cid_rif' => $f['cid_rif'] !== null ? trim((string) $f['cid_rif']) : '',
                'tipo_ganaderia_id' => (int) $f['tipo_ganaderia_id'],
            ];
        }

        self::$cacheFundos[$usuarioId] = $salida;
        return $salida;
    }

    /** Ids de los fundos con acceso activo (para validar rápido). */
    public static function idsFundosDeUsuario(int $usuarioId): array
    {
        $ids = [];
        foreach (self::fundosDeUsuario($usuarioId) as $f) {
            $ids[] = $f['id'];
        }
        return $ids;
    }

    /**
     * Resuelve el fundo EFECTIVO de una petición.
     *
     * Orden de decisión:
     *   1. Token atado a un fundo (clientes viejos / un solo fundo):
     *      ese fundo, como siempre.
     *   2. La petición manda "fundo_id": se valida contra los fundos
     *      con acceso activo del usuario; si no le pertenece, 403.
     *   3. La petición no manda fundo: se usa el fundo principal del
     *      usuario (ultimo_fundo_id) y, si no sirve, el primero de sus
     *      fundos. Así un cliente que todavía no manda fundo_id sigue
     *      funcionando.
     *
     * @throws nunca: corta con ApiResponse::error() (403) si no hay
     *   fundo válido.
     */
    public static function requerirFundo(array $input, array $auth): int
    {
        $usuarioId = (int) $auth['usuario_id'];
        $fundoToken = (int) ($auth['fundo_id'] ?? 0);

        if ($fundoToken > 0) {
            return $fundoToken;
        }

        $permitidos = self::idsFundosDeUsuario($usuarioId);
        $pedido = (int) ($input['fundo_id'] ?? 0);

        if ($pedido > 0) {
            if (!in_array($pedido, $permitidos, true)) {
                ApiResponse::error(
                    'Ese fundo no está entre los que tenés acceso activo.',
                    403,
                    ['codigo' => 'fundo_no_permitido']
                );
            }
            return $pedido;
        }

        $principal = self::fundoPrincipal($usuarioId);
        if ($principal > 0 && in_array($principal, $permitidos, true)) {
            return $principal;
        }
        if ($permitidos) {
            return (int) $permitidos[0];
        }

        ApiResponse::error(
            'Esta cuenta no tiene fundos activos asignados.',
            403,
            ['codigo' => 'sin_fundos']
        );
    }

    /**
     * Igual que requerirFundo(), pero devuelve 0 en vez de cortar la
     * petición. Sirve para acciones que pueden funcionar sin fundo
     * (por ejemplo, listar el estado del dispositivo).
     */
    public static function fundoOpcional(array $input, array $auth): int
    {
        try {
            return self::requerirFundo($input, $auth);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    // =================================================================
    // Permiso de uso del MODO OFFLINE (lo decide el administrador)
    // =================================================================

    /**
     * Exige que la cuenta tenga habilitado el uso del modo sin conexión.
     *
     * El permiso es del PRODUCTOR (rol 2), dueño del fundo: él lo activa
     * o desactiva desde el formulario de usuarios (solo el plan DIAMANTE
     * lo trae activo). Los usuarios que trabajan en su fundo (rol 3,
     * ENCARGADO) lo HEREDAN; los roles 1 (Administrador) y 4 (Super
     * Usuario) no usan la app sin conexión, solo administran, así que
     * quedan sujetos a su propio permiso (apagado por defecto).
     *
     * Si no tiene acceso, corta la petición con 403 y el código
     * 'sin_acceso_offline' para que la app muestre el aviso correcto
     * ("no tiene acceso al modo offline, contacte al administrador") en
     * lugar de un error genérico.
     *
     * Si la columna todavía no existe (migración pendiente) NO se bloquea
     * a nadie: es preferible dejar entrar a los equipos de campo que
     * dejar a todo el mundo sin poder sincronizar por un olvido de BD.
     */
    public static function exigirAccesoOffline(int $usuarioId): void
    {
        if (self::tieneAccesoOffline($usuarioId) === false) {
            ApiResponse::error(
                'Tu cuenta no tiene acceso al modo sin conexión. Contacta al administrador del sistema.',
                403,
                ['codigo' => 'sin_acceso_offline']
            );
        }
    }

    /**
     * ¿La cuenta puede usar la app sin conexión?
     *
     *   * PRODUCTOR (rol 2): su propio permiso (musuarios.acceso_offline).
     *   * ENCARGADO (rol 3): hereda del productor dueño del fundo. Si
     *     trabaja en varios fundos, se exige que TODOS sus productores
     *     tengan el permiso activo (si uno no lo tiene, ese fundo no
     *     debería quedar disponible sin conexión en el equipo).
     *   * ADMINISTRADOR / SUPER USUARIO: su propio permiso, que nace
     *     apagado (no necesitan la app sin conexión).
     *
     * @return bool|null null cuando la columna no existe todavía: en ese
     *   caso no se bloquea a nadie (despliegue a medias).
     */
    public static function tieneAccesoOffline(int $usuarioId): ?bool
    {
        if (!array_key_exists($usuarioId, self::$cacheAccesoOffline)) {
            $valores = [$usuarioId]; // AR exige variable (pasa $values por referencia)
            $fila = \Musuario::connection()
                ->query('SELECT acceso_offline FROM musuarios WHERE id = ? LIMIT 1', $valores)
                ->fetch();

            $propio = ($fila && array_key_exists('acceso_offline', $fila))
                ? self::aBooleano($fila['acceso_offline'])
                : null;

            if ($propio === null) {
                // Columna ausente: despliegue incompleto, no se bloquea.
                self::$cacheAccesoOffline[$usuarioId] = null;
            } elseif (self::rolDeUsuario($usuarioId) === self::ROL_ENCARGADO) {
                self::$cacheAccesoOffline[$usuarioId] = self::productoresDelUsuarioPermitenOffline($usuarioId);
            } else {
                self::$cacheAccesoOffline[$usuarioId] = $propio;
            }
        }

        return self::$cacheAccesoOffline[$usuarioId];
    }

    /**
     * Herencia del rol 3: el encargado tiene acceso sin conexión solo si
     * TODOS los productores dueños de sus fundos activos lo tienen
     * habilitado (y se encontró al menos uno). Sin productor conocido no
     * se habilita: el permiso es del dueño del fundo, no de un ayudante.
     */
    private static function productoresDelUsuarioPermitenOffline(int $usuarioId): bool
    {
        $valores = [$usuarioId];
        $filas = \Musuario::connection()->query(
            "SELECT DISTINCT p.id AS productor_id, p.acceso_offline
               FROM mfundosxusuarios mio
               JOIN mfundosxusuarios dueno
                    ON dueno.mfundos_id = mio.mfundos_id
                   AND dueno.status = 1
                   AND dueno.musuarios_id <> mio.musuarios_id
               JOIN musuarios p
                    ON p.id = dueno.musuarios_id
                   AND p.mroles_id = 2
                   AND p.status = 1
              WHERE mio.musuarios_id = ?
                AND mio.status = 1",
            $valores
        )->fetchAll();

        if (empty($filas)) {
            return false;
        }

        foreach ($filas as $fila) {
            if (!self::aBooleano($fila['acceso_offline'])) {
                return false;
            }
        }

        return true;
    }

    /** Interpreta un booleano tal como lo devuelve PostgreSQL ('t'/'f', 'true'/1/0). */
    private static function aBooleano($valor): bool
    {
        $v = strtolower(trim((string) $valor));
        return in_array($v, ['1', 't', 'true', 'y', 'yes', 'si', 'sí'], true);
    }

    private static function leerTokenDeHeader(): ?string
    {
        // Orden de variantes según cómo cada configuración de
        // servidor (Apache/mod_php, Apache+FastCGI, Nginx+FPM, detrás
        // de un proxy o de reglas de RewriteRule) termina exponiendo
        // el header a PHP.
        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? null;

        if (!$header && function_exists('getallheaders')) {
            $headers = getallheaders();
            $header = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        }

        if (!$header || stripos($header, 'Bearer ') !== 0) {
            return null;
        }

        return trim(substr($header, 7));
    }
}
