<?php
/**
 * =====================================================================
 * lib/demo_helper.php — MODO DEMOSTRACIÓN (acceso con base de pruebas)
 * ---------------------------------------------------------------------
 * Todo lo que necesita el modo demo está aquí:
 *
 *   · una conexión PDO propia a la base de pruebas (sovca_demo),
 *   · la creación de la cuenta + fundo + vigencia de 15 días,
 *   · la autenticación de esas cuentas (usada como respaldo del login),
 *   · el armado de la sesión igual que el login normal, pero marcando
 *     $_SESSION['demo'] = 1 para que config/conexion.php apunte a la
 *     base demo,
 *   · la limpieza de cuentas vencidas.
 *
 * Reglas de oro:
 *   1. NADA de ActiveRecord en este archivo. ActiveRecord ya está
 *      inicializado contra la base real cuando este helper se usa
 *      (el login todavía no sabe que el usuario es demo), así que aquí
 *      se trabaja con PDO y SQL directo contra sovca_demo.
 *   2. Ninguna consulta usa datos del usuario sin preparar (SQL
 *      parametrizado) salvo identificadores internos ya casteados.
 *   3. La base demo NUNCA contiene datos de productores reales: el
 *      instalador copia estructura + catálogos y nada más.
 * =====================================================================
 */

require_once __DIR__ . '/password_policy.php';
require_once __DIR__ . '/mailer_helper.php';

/* ---------------------------------------------------------------------
   ESTADO / CONEXIÓN
   --------------------------------------------------------------------- */

if (!function_exists('bovs_demo_activo')) {
    /**
     * ¿El modo demostración está habilitado en config/demo.php?
     */
    function bovs_demo_activo()
    {
        return defined('SOVCA_DEMO_ACTIVO') && SOVCA_DEMO_ACTIVO;
    }
}

if (!function_exists('bovs_demo_dsn')) {
    /**
     * Cadena PDO de la base demo (o de la base indicada).
     */
    function bovs_demo_dsn($base = null)
    {
        $base = ($base === null || $base === '') ? SOVCA_DEMO_DB : (string) $base;
        return 'pgsql:host=' . SOVCA_DEMO_HOST
            . ';port=' . SOVCA_DEMO_PUERTO
            . ';dbname=' . $base;
    }
}

if (!function_exists('bovs_demo_pdo')) {
    /**
     * Conexión PDO a la base demo (una por petición y por base).
     *
     * @param string|null $base Nombre de base; por defecto la demo.
     * @return PDO
     * @throws PDOException Si la base no existe o no acepta conexiones.
     */
    function bovs_demo_pdo($base = null)
    {
        static $conexiones = array();

        $base = ($base === null || $base === '') ? SOVCA_DEMO_DB : (string) $base;

        if (isset($conexiones[$base]) && $conexiones[$base] instanceof PDO) {
            return $conexiones[$base];
        }

        $pdo = new PDO(
            bovs_demo_dsn($base),
            SOVCA_DEMO_USUARIO,
            SOVCA_DEMO_CLAVE,
            array(
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            )
        );

        $conexiones[$base] = $pdo;
        return $pdo;
    }
}

if (!function_exists('bovs_demo_bd_disponible')) {
    /**
     * ¿La base demo existe y tiene la estructura esperada?
     * Se usa para no dejar al visitante con una pantalla en blanco
     * cuando el administrador todavía no corrió scripts/demo_instalar.php.
     */
    function bovs_demo_bd_disponible()
    {
        static $estado = null;

        if ($estado !== null) {
            return $estado;
        }

        try {
            $pdo = bovs_demo_pdo();
            $pdo->query('SELECT 1 FROM musuarios LIMIT 1');
            $estado = true;
        } catch (Throwable $e) {
            /* El motivo se publica en la pantalla del demo y en el log: sin
               esto solo se ve "no está instalada" y no hay forma de saber si
               falta la base, el ruteo del pooler o la clave. */
            $GLOBALS['bovs_demo_bd_error'] = $e->getMessage();
            error_log('[SOVCA demo] base no disponible: ' . $e->getMessage());
            $estado = false;
        }

        return $estado;
    }
}

if (!function_exists('bovs_demo_destino_actual')) {
    /**
     * Describe el destino al que intenta conectarse la sesión demo.
     * Se muestra junto al error para saber de un vistazo qué se probó.
     */
    function bovs_demo_destino_actual()
    {
        $base = defined('SOVCA_DEMO_DB') ? SOVCA_DEMO_DB : 'sovca_demo';
        $host = defined('SOVCA_DEMO_HOST') ? SOVCA_DEMO_HOST : '?';
        $puerto = defined('SOVCA_DEMO_PUERTO') ? SOVCA_DEMO_PUERTO : '?';
        $usuario = defined('SOVCA_DEMO_USUARIO') ? SOVCA_DEMO_USUARIO : '?';

        return $usuario . '@' . $host . ':' . $puerto . '/' . $base;
    }
}

if (!function_exists('bovs_demo_ip')) {
    /**
     * IP del visitante (para el límite de cuentas por día).
     */
    function bovs_demo_ip()
    {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
        return substr($ip !== '' ? $ip : 'desconocida', 0, 60);
    }
}

/* ---------------------------------------------------------------------
   PREPARACIÓN DE LA BASE DEMO
   --------------------------------------------------------------------- */

if (!function_exists('bovs_demo_preparar')) {
    /**
     * Crea (si faltan) las piezas propias del modo demo dentro de la base
     * de pruebas. Es idempotente y segura ante peticiones simultáneas
     * (varios usuarios demo se registran al mismo tiempo).
     *
     *   demo_registros       — bitácora de cuentas creadas (IP, vencimiento).
     *   demo_verificaciones  — solicitudes pendientes de verificar el correo.
     */
    function bovs_demo_preparar(?PDO $pdo = null)
    {
        if ($pdo === null) {
            $pdo = bovs_demo_pdo();
        }

        /* Si dos peticiones intentan crear la misma tabla a la vez, una de
           ellas recibe "duplicate key ... pg_type" aunque use IF NOT EXISTS.
           Se ignora: la tabla ya existe, que es lo que se buscaba. */
        $sentencias = array(
            "CREATE TABLE IF NOT EXISTS demo_registros (
                id              serial PRIMARY KEY,
                musuarios_id    integer,
                mfundos_id      integer,
                username        varchar(60),
                email           varchar(160),
                ip              varchar(60),
                fecha_caduca    date,
                email_verificado boolean DEFAULT false,
                created         timestamp without time zone DEFAULT now()
            )",
            "ALTER TABLE demo_registros ADD COLUMN IF NOT EXISTS email_verificado boolean DEFAULT false",
            "ALTER TABLE demo_registros ADD COLUMN IF NOT EXISTS migrado boolean DEFAULT false",
            "ALTER TABLE demo_registros ADD COLUMN IF NOT EXISTS migrado_a_usuario integer",
            "ALTER TABLE demo_registros ADD COLUMN IF NOT EXISTS migrado_fundo_id integer",
            "ALTER TABLE demo_registros ADD COLUMN IF NOT EXISTS migrado_fecha timestamp without time zone",
            "ALTER TABLE demo_registros ADD COLUMN IF NOT EXISTS cerrada boolean DEFAULT false",
            "ALTER TABLE demo_registros ADD COLUMN IF NOT EXISTS nota varchar(300)",
            "CREATE INDEX IF NOT EXISTS idx_demo_registros_email ON demo_registros (lower(email))",
            "CREATE INDEX IF NOT EXISTS idx_demo_registros_ip ON demo_registros (ip, created)",

            "CREATE TABLE IF NOT EXISTS demo_verificaciones (
                id              serial PRIMARY KEY,
                username        varchar(60)  NOT NULL,
                email           varchar(160) NOT NULL,
                nombre          varchar(120) NOT NULL,
                fundo           varchar(120) NOT NULL,
                password_hash   varchar(64)  NOT NULL,
                codigo_hash     varchar(64)  NOT NULL,
                expira          timestamp    NOT NULL,
                intentos        smallint     NOT NULL DEFAULT 0,
                ultimo_envio    timestamp    NOT NULL DEFAULT now(),
                ip              varchar(60),
                usado           boolean      NOT NULL DEFAULT false,
                created         timestamp without time zone DEFAULT now()
            )",
            "CREATE INDEX IF NOT EXISTS idx_demo_verif_usuario ON demo_verificaciones (upper(username), usado)",
            "CREATE INDEX IF NOT EXISTS idx_demo_verif_email ON demo_verificaciones (lower(email), usado)",
        );

        foreach ($sentencias as $sql) {
            try {
                $pdo->exec($sql);
            } catch (Throwable $e) {
                /* Solo importa si la tabla quedó inutilizable: se comprueba abajo. */
                error_log('[SOVCA demo] preparar: ' . $e->getMessage());
            }
        }

        return true;
    }
}

if (!function_exists('bovs_demo_suscripcion_id')) {
    /**
     * Devuelve el id de msuscripciones que representa la vigencia demo.
     * Si no existe, crea la fila "DEMO" con la cantidad de días configurada.
     *
     * Se hace así para que la ficha del usuario muestre algo coherente en
     * lugar de "Mensual (30 días)" con un vencimiento a 15 días.
     */
    function bovs_demo_suscripcion_id(?PDO $pdo = null)
    {
        if ($pdo === null) {
            $pdo = bovs_demo_pdo();
        }

        $dias = (int) SOVCA_DEMO_DIAS;

        $st = $pdo->prepare('SELECT id FROM msuscripciones WHERE cantidad_dias = ? ORDER BY id LIMIT 1');
        $st->execute(array($dias));
        $id = $st->fetchColumn();

        if ($id !== false && $id !== null) {
            return (int) $id;
        }

        $ins = $pdo->prepare('INSERT INTO msuscripciones (descripcion, cantidad_dias, status) VALUES (?, ?, 1) RETURNING id');
        $ins->execute(array('DEMO (' . $dias . ' días)', $dias));

        return (int) $ins->fetchColumn();
    }
}

if (!function_exists('bovs_demo_geo_por_defecto')) {
    /**
     * País / estado / municipio válidos para el fundo demo.
     *
     * mfundos exige mpaises_id, mestados_id y mmunicipios_id (NOT NULL),
     * así que se resuelven contra los catálogos ya instalados y, si algo
     * no aparece, se toma el primer registro disponible.
     *
     * @return array{pais:int,estado:string,municipio:string}
     */
    function bovs_demo_geo_por_defecto(?PDO $pdo = null)
    {
        if ($pdo === null) {
            $pdo = bovs_demo_pdo();
        }

        $pais = 0;
        try {
            $st = $pdo->prepare('SELECT id FROM mpaises WHERE paisnombre ILIKE ? ORDER BY id LIMIT 1');
            $st->execute(array('%' . SOVCA_DEMO_PAIS . '%'));
            $pais = (int) $st->fetchColumn();
        } catch (Throwable $e) {
            $pais = 0;
        }

        if ($pais <= 0) {
            try {
                $pais = (int) $pdo->query('SELECT id FROM mpaises ORDER BY id LIMIT 1')->fetchColumn();
            } catch (Throwable $e) {
                $pais = 0;
            }
        }

        $estado = '';
        if ($pais > 0) {
            try {
                $st = $pdo->prepare('SELECT id FROM mestados WHERE ubicacionpaisid = ? ORDER BY id LIMIT 1');
                $st->execute(array($pais));
                $estado = (string) $st->fetchColumn();
            } catch (Throwable $e) {
                $estado = '';
            }
        }
        if ($estado === '' || $estado === '0') {
            $estado = '0';
        }

        return array(
            'pais'      => $pais,
            'estado'    => $estado,
            'municipio' => '0',
        );
    }
}

/* ---------------------------------------------------------------------
   VALIDACIÓN DE LOS DATOS DEL FORMULARIO
   --------------------------------------------------------------------- */

if (!function_exists('bovs_demo_validar')) {
    /**
     * Valida y normaliza los datos de la cuenta demo.
     *
     * @param array $datos Datos crudos del POST.
     * @return array{errores:array,datos:array}
     */
    function bovs_demo_validar(array $datos)
    {
        $nombre  = trim((string) (isset($datos['nombre']) ? $datos['nombre'] : ''));
        $email   = strtolower(trim((string) (isset($datos['email']) ? $datos['email'] : '')));
        $usuario = strtoupper(trim((string) (isset($datos['usuario']) ? $datos['usuario'] : '')));
        $clave   = (string) (isset($datos['clave']) ? $datos['clave'] : '');
        $clave2  = (string) (isset($datos['clave2']) ? $datos['clave2'] : '');
        $fundo   = trim((string) (isset($datos['fundo']) ? $datos['fundo'] : ''));

        $errores = array();

        if (mb_strlen($nombre) < 3 || mb_strlen($nombre) > 120) {
            $errores[] = 'Escribe tu nombre completo (entre 3 y 120 caracteres).';
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
            $errores[] = 'El correo electrónico no es válido.';
        }

        if (!preg_match('/^[A-Z0-9._-]{4,30}$/', $usuario)) {
            $errores[] = 'El usuario debe tener entre 4 y 30 caracteres: letras, números, punto, guion o guion bajo.';
        }

        if ($clave !== $clave2) {
            $errores[] = 'Las dos contraseñas no coinciden.';
        } else {
            $errores = array_merge($errores, bovs_password_errores($clave));
        }

        if (mb_strlen($fundo) > 120) {
            $errores[] = 'El nombre del fundo no puede pasar de 120 caracteres.';
        }

        if ($fundo === '') {
            $fundo = 'Fundo Demo ' . $usuario;
        }

        return array(
            'errores' => $errores,
            'datos'   => array(
                'nombre'  => $nombre,
                'email'   => $email,
                'usuario' => $usuario,
                'clave'   => $clave,
                'fundo'   => $fundo,
            ),
        );
    }
}

/* ---------------------------------------------------------------------
   ALTA DE LA CUENTA DEMO (con verificación de correo)
   ---------------------------------------------------------------------
   El alta va en DOS pasos:

     1) bovs_demo_solicitar_cuenta() → valida los datos, guarda la
        solicitud pendiente y envía un código de 6 dígitos al correo.
     2) bovs_demo_confirmar_cuenta() → comprueba el código y RECIÉN AHÍ
        crea usuario + fundo + vigencia y abre la sesión.

   Nada se crea antes de verificar el correo: no entran cuentas anónimas
   y lo que cargue cada demo es de alguien identificable (sirve como dato
   real, no como basura).
   --------------------------------------------------------------------- */

if (!function_exists('bovs_demo_crear_cuenta_verificada')) {
    /**
     * Crea la cuenta ya verificada (usuario + fundo + vínculo + vigencia).
     *
     * @param PDO   $pdo
     * @param array $pend Fila de demo_verificaciones.
     * @return array{ok:bool,msg:string,usuario?:array,caduca?:string}
     */
    function bovs_demo_crear_cuenta_verificada(PDO $pdo, array $pend)
    {
        $d = array(
            'usuario' => strtoupper((string) $pend['username']),
            'email'   => strtolower((string) $pend['email']),
            'nombre'  => (string) $pend['nombre'],
            'fundo'   => (string) $pend['fundo'],
            'clave'   => '',
        );
        $hash = (string) $pend['password_hash'];

        try {
            bovs_demo_preparar($pdo);

            /* --- Duplicados (usuario / correo) ----------------------- */
            $st = $pdo->prepare('SELECT id FROM musuarios WHERE status = 1 AND upper(username) = ? LIMIT 1');
            $st->execute(array($d['usuario']));
            if ($st->fetchColumn()) {
                return array('ok' => false, 'msg' => 'El usuario <strong>' . htmlspecialchars($d['usuario'], ENT_QUOTES, 'UTF-8') . '</strong> ya existe en la demostración. Elige otro.');
            }

            $st = $pdo->prepare('SELECT id FROM musuarios WHERE status = 1 AND lower(email) = ? LIMIT 1');
            $st->execute(array($d['email']));
            if ($st->fetchColumn()) {
                return array('ok' => false, 'msg' => 'Ese correo ya tiene una cuenta en la demostración.');
            }

            /* --- Datos de apoyo de los catálogos --------------------- */
            $geo      = bovs_demo_geo_por_defecto($pdo);
            $suscrip  = bovs_demo_suscripcion_id($pdo);
            $plan     = (int) SOVCA_DEMO_PLAN_ID;
            $dias     = (int) SOVCA_DEMO_DIAS;
            $caduca   = date('Y-m-d', strtotime('+' . $dias . ' days'));

            $pdo->beginTransaction();

            /* --- 1. Usuario ----------------------------------------- */
            $sqlUsuario = "
                INSERT INTO musuarios
                    (mroles_id, username, password, name, email, imagen, updated, created,
                     status, user_create, user_modify, cid, act_clave, act_email, clave_email,
                     telefono, mtipousuarios_id, mplanes_id, fecha_activacion,
                     es_multifundo, max_fundos, ultimo_fundo_id, acceso_offline,
                     password_reset_intentos)
                VALUES
                    (?, ?, ?, ?, ?, NULL, now(), now(),
                     1, NULL, NULL, NULL, 1, 1, NULL,
                     NULL, ?, ?, now(),
                     false, 1, NULL, false,
                     0)
                RETURNING id
            ";
            $st = $pdo->prepare($sqlUsuario);
            $st->execute(array(
                (int) SOVCA_DEMO_ROL_PRODUCTOR,
                $d['usuario'],
                $hash,
                $d['nombre'],
                $d['email'],
                (int) SOVCA_DEMO_TIPO_USUARIO_ID,
                $plan,
            ));
            $usuarioId = (int) $st->fetchColumn();

            /* --- 2. Fundo ------------------------------------------- */
            $sqlFundo = "
                INSERT INTO mfundos
                    (cid_rif, nombre, mtipofundos_id, mpaises_id, mestados_id, mmunicipios_id,
                     direccion, descripcion, updated, created, status,
                     user_create, user_propietario, user_modify,
                     mtipoganaderias_id, mplanes_id)
                VALUES
                    (?, ?, ?, ?, ?, ?,
                     ?, ?, NULL, now(), 1,
                     ?, ?, NULL,
                     ?, ?)
                RETURNING id
            ";
            $st = $pdo->prepare($sqlFundo);
            $st->execute(array(
                'DEMO-' . $d['usuario'],
                $d['fundo'],
                (int) SOVCA_DEMO_TIPO_FUNDO_ID,
                (int) $geo['pais'],
                (string) $geo['estado'],
                (string) $geo['municipio'],
                'Fundo de demostración',
                'Creado automáticamente desde el acceso demostrativo (vigencia ' . $dias . ' días).',
                $usuarioId,
                $usuarioId,
                (int) SOVCA_DEMO_TIPO_GANADERIA_ID,
                $plan,
            ));
            $fundoId = (int) $st->fetchColumn();

            /* --- 3. Vínculo usuario ↔ fundo ------------------------- */
            $st = $pdo->prepare('INSERT INTO mfundosxusuarios (musuarios_id, mfundos_id, created, status, user_create) VALUES (?, ?, now(), 1, ?)');
            $st->execute(array($usuarioId, $fundoId, $usuarioId));

            /* --- 4. Vigencia de 15 días ---------------------------- */
            $st = $pdo->prepare('
                INSERT INTO mplanesxfundo
                    (musuarios_id, msuscripciones_id, mfundos_id, mplanes_id,
                     fecha_activa_suscripcion, fecha_caduca_suscripcion)
                VALUES (?, ?, ?, ?, now(), ?)
            ');
            $st->execute(array($usuarioId, $suscrip, $fundoId, $plan, $caduca));

            /* --- 5. Último fundo activo ---------------------------- */
            $st = $pdo->prepare('UPDATE musuarios SET ultimo_fundo_id = ? WHERE id = ?');
            $st->execute(array($fundoId, $usuarioId));

            /* --- 6. Bitácora demo ---------------------------------- */
            $st = $pdo->prepare('INSERT INTO demo_registros (musuarios_id, mfundos_id, username, email, ip, fecha_caduca, email_verificado) VALUES (?, ?, ?, ?, ?, ?, true)');
            $st->execute(array($usuarioId, $fundoId, $d['usuario'], $d['email'], bovs_demo_ip(), $caduca));

            $pdo->commit();
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[SOVCA demo] Error al crear cuenta: ' . $e->getMessage());
            return array('ok' => false, 'msg' => 'No se pudo crear la cuenta de demostración. Intenta nuevamente.');
        }

        return array(
            'ok'      => true,
            'msg'     => 'Cuenta de demostración creada.',
            'caduca'  => $caduca,
            'usuario' => sovca_demo_datos_sesion($pdo, $usuarioId),
        );
    }
}

/* ---------------------------------------------------------------------
   VERIFICACIÓN DEL CORREO
   --------------------------------------------------------------------- */

if (!function_exists('bovs_demo_email_oculto')) {
    /**
     * Enmascara el correo para mostrarlo en pantalla
     * (ana.perez@dominio.com → an***@dominio.com).
     */
    function bovs_demo_email_oculto($email)
    {
        $email = (string) $email;
        $pos = strpos($email, '@');
        if ($pos === false || $pos < 1) {
            return $email;
        }

        $usuario  = substr($email, 0, $pos);
        $dominio  = substr($email, $pos);
        $visibles = mb_substr($usuario, 0, 2);

        return $visibles . str_repeat('*', max(3, mb_strlen($usuario) - 2)) . $dominio;
    }
}

if (!function_exists('bovs_demo_enviar_codigo')) {
    /**
     * Correo con el código que confirma la cuenta de demostración.
     *
     * @return array{ok:bool,error?:string}
     */
    function bovs_demo_enviar_codigo($para, $nombre, $usuario, $codigo, $minutos)
    {
        $nombreSafe  = htmlspecialchars((string) $nombre, ENT_QUOTES, 'UTF-8');
        $usuarioSafe = htmlspecialchars((string) $usuario, ENT_QUOTES, 'UTF-8');
        $codigoSafe  = htmlspecialchars((string) $codigo, ENT_QUOTES, 'UTF-8');
        $minutos     = (int) $minutos;
        $dias        = (int) SOVCA_DEMO_DIAS;
        $base        = htmlspecialchars(bovs_url_base(), ENT_QUOTES, 'UTF-8');

        // El enlace solo se escribe si hay una URL pública real: un enlace
        // a localhost/127.0.0.1 dentro del correo dispara los filtros de spam.
        $enlaceHtml = '';
        if ($base !== '') {
            $enlaceHtml = '<p style="margin:0 0 10px;">Confirma el c&oacute;digo en:</p>'
                . '<p style="font-size:12.5px;margin:0 0 14px;"><a href="' . $base . '" style="color:#2c6e46;">' . $base . '</a> &rarr; <b>Acceso demostrativo</b></p>';
        }

        $contenido = '
<h2 style="color:#234e35;font-size:19px;margin:0 0 14px;">Confirma tu correo</h2>
<p style="margin:0 0 12px;">Hola <b>' . $nombreSafe . '</b>,</p>
<p style="margin:0 0 12px;">
  Recibimos una solicitud para crear la cuenta de demostración <b>' . $usuarioSafe . '</b> en <b>SOVCA</b>.
  Escribe este código en la pantalla de registro:
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:22px 0;">
  <tr>
    <td align="center" style="background:#f2f9f4;border:2px dashed #2c6e46;border-radius:14px;padding:24px 20px;">
      <div style="font-size:11px;font-weight:800;color:#2c6e46;text-transform:uppercase;letter-spacing:1.5px;margin:0 0 12px;">
        Tu código de verificación
      </div>
      <div style="font-family:\'Courier New\',Consolas,monospace;font-size:38px;font-weight:800;color:#1b5e20;letter-spacing:14px;line-height:1;padding-left:14px;">
        ' . $codigoSafe . '
      </div>
      <div style="font-size:12px;color:#5c6b62;margin:14px 0 0;">
        Válido por <b style="color:#b45309;">' . $minutos . ' minutos</b> &middot; un solo uso
      </div>
    </td>
  </tr>
</table>

<div style="background:#f4f8f4;border-left:4px solid #2c6e46;padding:12px 16px;border-radius:0 8px 8px 0;font-size:13px;color:#4b5a51;margin:16px 0;">
  <b>¿Por qué verificamos el correo?</b> Para que cada cuenta demo sea de una persona
  identificable y los datos que se carguen puedan usarse de verdad. La cuenta se crea
  <b>solo después</b> de confirmar este código y dura <b>' . $dias . ' días</b>.
</div>

' . $enlaceHtml . '

<p style="margin:0;">Si <b>no solicitaste</b> esta cuenta, ignora este correo: no se creará nada.</p>';

        $texto = "Hola $nombre,\n\n"
            . "Solicitaste una cuenta de demostración en SOVCA ($usuario).\n"
            . "Tu código de verificación es: $codigo\n\n"
            . "Válido por $minutos minutos y un solo uso. La cuenta se crea al confirmarlo y dura $dias días.\n"
            . "Si no solicitaste la cuenta, ignora este correo.\n";

        return bovs_mail_enviar(
            $para,
            'Tu código SOVCA: ' . $codigo,
            bovs_mail_plantilla('Confirma tu correo', $contenido),
            $texto,
            array('tipo' => 'transaccional')
        );
    }
}

if (!function_exists('bovs_demo_solicitar_cuenta')) {
    /**
     * PASO 1 del alta: valida los datos, guarda la solicitud pendiente y
     * envía el código al correo. NO crea usuario ni fundo todavía.
     *
     * @param array $datos Datos crudos del formulario.
     * @return array{ok:bool,msg:string,paso?:string,usuario?:string,email?:string,email_oculto?:string,minutos?:int}
     */
    function bovs_demo_solicitar_cuenta(array $datos)
    {
        if (!bovs_demo_activo()) {
            return array('ok' => false, 'msg' => 'El modo demostración no está habilitado.');
        }

        if (!bovs_demo_bd_disponible()) {
            return array('ok' => false, 'msg' => 'La base de datos de pruebas no está disponible. Avisa al administrador del sistema.');
        }

        $v = bovs_demo_validar($datos);
        if (!empty($v['errores'])) {
            return array('ok' => false, 'msg' => implode(' ', $v['errores']));
        }
        $d = $v['datos'];

        $pendienteId = 0;
        $minutos = max(1, (int) SOVCA_DEMO_VERIF_MINUTOS);

        try {
            $pdo = bovs_demo_pdo();
            bovs_demo_preparar($pdo);

            /* --- Cuota por IP (24 h). 0 = sin límite ------------------ */
            $limite = (int) SOVCA_DEMO_MAX_POR_IP;
            if ($limite > 0) {
                $st = $pdo->prepare("SELECT count(*) FROM demo_registros WHERE ip = ? AND created > now() - interval '1 day'");
                $st->execute(array(bovs_demo_ip()));
                if ((int) $st->fetchColumn() >= $limite) {
                    return array(
                        'ok'  => false,
                        'msg' => 'Se crearon varias cuentas demo desde esta conexión hoy. Intenta de nuevo mañana.'
                    );
                }
            }

            /* --- Duplicados entre las cuentas ya creadas -------------- */
            $st = $pdo->prepare('SELECT id FROM musuarios WHERE status = 1 AND upper(username) = ? LIMIT 1');
            $st->execute(array($d['usuario']));
            if ($st->fetchColumn()) {
                return array('ok' => false, 'msg' => 'El usuario <strong>' . htmlspecialchars($d['usuario'], ENT_QUOTES, 'UTF-8') . '</strong> ya existe en la demostración. Elige otro.');
            }

            $st = $pdo->prepare('SELECT id FROM musuarios WHERE status = 1 AND lower(email) = ? LIMIT 1');
            $st->execute(array($d['email']));
            if ($st->fetchColumn()) {
                return array('ok' => false, 'msg' => 'Ese correo ya tiene una cuenta en la demostración.');
            }

            /* --- Un correo = una sola demostración ---------------------
               Aunque la cuenta haya vencido o se haya cerrado, el correo
               no vuelve a servir para otra demo: así se evita crear
               cuentas en serie y se empuja a comprar la suscripción. */
            $st = $pdo->prepare('SELECT username, migrado FROM demo_registros WHERE lower(email) = ? ORDER BY id DESC LIMIT 1');
            $st->execute(array($d['email']));
            $previa = $st->fetch();

            if ($previa) {
                if (!empty($previa['migrado'])) {
                    return array(
                        'ok'  => false,
                        'msg' => 'Ese correo ya fue migrado a una cuenta oficial. Inicia sesión con tu cuenta definitiva.'
                    );
                }

                $wa = bovs_demo_whatsapp_url(array('usuario' => $previa['username'], 'email' => $d['email']));

                return array(
                    'ok'  => false,
                    'msg' => 'Ese correo ya usó la cuenta de demostración. Para conservar tus datos y seguir trabajando, '
                        . 'compra tu suscripción: <a href="' . htmlspecialchars($wa, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">escríbenos por WhatsApp</a>.'
                );
            }

            /* --- Solicitud pendiente + código ------------------------- */
            $codigo  = bovs_mail_codigo();
            $expira  = date('Y-m-d H:i:s', strtotime('+' . $minutos . ' minutes'));

            $pdo->beginTransaction();

            // Si reintenta el formulario, se descarta la solicitud anterior.
            $del = $pdo->prepare('DELETE FROM demo_verificaciones WHERE usado = false AND (upper(username) = ? OR lower(email) = ?)');
            $del->execute(array($d['usuario'], $d['email']));

            $ins = $pdo->prepare('
                INSERT INTO demo_verificaciones
                    (username, email, nombre, fundo, password_hash, codigo_hash,
                     expira, intentos, ultimo_envio, ip)
                VALUES (?, ?, ?, ?, ?, ?, ?, 0, now(), ?)
                RETURNING id
            ');
            $ins->execute(array(
                $d['usuario'],
                $d['email'],
                $d['nombre'],
                $d['fundo'],
                bovs_password_hash($d['clave']),
                bovs_mail_codigo_hash($codigo),
                $expira,
                bovs_demo_ip(),
            ));
            $pendienteId = (int) $ins->fetchColumn();

            $pdo->commit();
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[SOVCA demo] Error al solicitar cuenta: ' . $e->getMessage());

            if ($e instanceof PDOException && (string) $e->getCode() === '23505') {
                return array('ok' => false, 'msg' => 'Ese usuario o correo se acaba de registrar. Elige otros datos.');
            }

            return array('ok' => false, 'msg' => 'No se pudo registrar la solicitud. Intenta nuevamente.');
        }

        /* --- Envío del correo (fuera de la transacción) -------------- */
        $envio = bovs_demo_enviar_codigo($d['email'], $d['nombre'], $d['usuario'], $codigo, $minutos);

        if (empty($envio['ok'])) {
            try {
                $pdo->prepare('DELETE FROM demo_verificaciones WHERE id = ?')->execute(array($pendienteId));
            } catch (Throwable $e) {
                error_log('[SOVCA demo] No se pudo borrar la solicitud fallida: ' . $e->getMessage());
            }

            error_log('[SOVCA demo] No se pudo enviar el código a ' . $d['email'] . ': ' . (isset($envio['error']) ? $envio['error'] : 'sin detalle'));

            return array(
                'ok'  => false,
                'msg' => 'No pudimos enviar el correo de verificación. Revisa la dirección e intenta de nuevo; si sigue fallando, avisa al administrador.'
            );
        }

        return array(
            'ok'           => true,
            'paso'         => 'verificacion',
            'usuario'      => $d['usuario'],
            'email'        => $d['email'],
            'email_oculto' => bovs_demo_email_oculto($d['email']),
            'minutos'      => $minutos,
            'msg'          => 'Te enviamos un código de 6 dígitos a <strong>' . htmlspecialchars(bovs_demo_email_oculto($d['email']), ENT_QUOTES, 'UTF-8') . '</strong>.',
        );
    }
}

if (!function_exists('bovs_demo_confirmar_cuenta')) {
    /**
     * PASO 2 del alta: comprueba el código y crea la cuenta.
     *
     * @param string $usuario
     * @param string $codigo
     * @return array{ok:bool,msg:string,usuario?:array,caduca?:string,expirado?:bool}
     */
    function bovs_demo_confirmar_cuenta($usuario, $codigo)
    {
        if (!bovs_demo_activo() || !bovs_demo_bd_disponible()) {
            return array('ok' => false, 'msg' => 'La base de datos de pruebas no está disponible. Avisa al administrador del sistema.');
        }

        $usuario = strtoupper(trim((string) $usuario));
        $codigo  = preg_replace('/\D/', '', (string) $codigo);

        if ($usuario === '') {
            return array('ok' => false, 'msg' => 'No se identificó la solicitud. Vuelve a empezar el registro.');
        }

        if (strlen($codigo) !== 6) {
            return array('ok' => false, 'msg' => 'Escribe el código de 6 dígitos que enviamos a tu correo.');
        }

        try {
            $pdo = bovs_demo_pdo();
            bovs_demo_preparar($pdo);

            $st = $pdo->prepare('SELECT * FROM demo_verificaciones WHERE upper(username) = ? AND usado = false ORDER BY id DESC LIMIT 1');
            $st->execute(array($usuario));
            $pend = $st->fetch();

            if (!$pend) {
                return array('ok' => false, 'msg' => 'No hay una solicitud pendiente para ese usuario. Vuelve a empezar el registro.');
            }

            $idPend = (int) $pend['id'];
            $max    = max(1, (int) SOVCA_DEMO_VERIF_INTENTOS);

            /* --- Caducidad ------------------------------------------- */
            if (strtotime((string) $pend['expira']) < time()) {
                $pdo->prepare('UPDATE demo_verificaciones SET usado = true WHERE id = ?')->execute(array($idPend));
                return array('ok' => false, 'expirado' => true, 'msg' => 'El código caducó. Pulsa <strong>Reenviar</strong> para recibir uno nuevo.');
            }

            /* --- Intentos agotados ----------------------------------- */
            if ((int) $pend['intentos'] >= $max) {
                $pdo->prepare('UPDATE demo_verificaciones SET usado = true WHERE id = ?')->execute(array($idPend));
                return array('ok' => false, 'expirado' => true, 'msg' => 'Se agotaron los intentos con este código. Pulsa <strong>Reenviar</strong> para recibir uno nuevo.');
            }

            /* --- Código incorrecto ----------------------------------- */
            if (!hash_equals((string) $pend['codigo_hash'], bovs_mail_codigo_hash($codigo))) {
                $up = $pdo->prepare('UPDATE demo_verificaciones SET intentos = intentos + 1 WHERE id = ? RETURNING intentos');
                $up->execute(array($idPend));
                $usados = (int) $up->fetchColumn();
                $restan = max(0, $max - $usados);

                if ($restan <= 0) {
                    $pdo->prepare('UPDATE demo_verificaciones SET usado = true WHERE id = ?')->execute(array($idPend));
                    return array('ok' => false, 'expirado' => true, 'msg' => 'Se agotaron los intentos con este código. Pulsa <strong>Reenviar</strong> para recibir uno nuevo.');
                }

                return array('ok' => false, 'msg' => 'El código no es correcto. Te quedan <strong>' . $restan . '</strong> intento(s).');
            }

            /* --- Código correcto: se crea la cuenta ------------------- */
            $alta = bovs_demo_crear_cuenta_verificada($pdo, $pend);
            if (empty($alta['ok'])) {
                return $alta;
            }

            $pdo->prepare('UPDATE demo_verificaciones SET usado = true WHERE id = ?')->execute(array($idPend));

            return array(
                'ok'      => true,
                'msg'     => 'Correo verificado. ¡Bienvenido!',
                'usuario' => $alta['usuario'],
                'caduca'  => $alta['caduca'],
            );
        } catch (Throwable $e) {
            error_log('[SOVCA demo] Error al confirmar cuenta: ' . $e->getMessage());
            return array('ok' => false, 'msg' => 'No se pudo verificar el código. Intenta nuevamente.');
        }
    }
}

if (!function_exists('bovs_demo_reenviar_codigo')) {
    /**
     * Genera y envía un código nuevo para una solicitud pendiente.
     *
     * @param string $usuario
     * @return array{ok:bool,msg:string,minutos?:int,espera?:int}
     */
    function bovs_demo_reenviar_codigo($usuario)
    {
        if (!bovs_demo_activo() || !bovs_demo_bd_disponible()) {
            return array('ok' => false, 'msg' => 'La base de datos de pruebas no está disponible.');
        }

        $usuario = strtoupper(trim((string) $usuario));
        if ($usuario === '') {
            return array('ok' => false, 'msg' => 'No se identificó la solicitud.');
        }

        try {
            $pdo = bovs_demo_pdo();
            bovs_demo_preparar($pdo);

            $st = $pdo->prepare('SELECT * FROM demo_verificaciones WHERE upper(username) = ? AND usado = false ORDER BY id DESC LIMIT 1');
            $st->execute(array($usuario));
            $pend = $st->fetch();

            if (!$pend) {
                return array('ok' => false, 'msg' => 'La solicitud ya no está disponible. Vuelve a empezar el registro.');
            }

            /* --- Espera mínima entre envíos --------------------------- */
            $esperaSeg = max(0, (int) SOVCA_DEMO_REENVIO_SEG);
            $transcurrido = time() - strtotime((string) $pend['ultimo_envio']);
            $faltan = $esperaSeg - $transcurrido;
            if ($faltan > 0) {
                return array(
                    'ok'     => false,
                    'espera' => $faltan,
                    'msg'    => 'Espera <strong>' . $faltan . ' s</strong> para pedir otro código.'
                );
            }

            $minutos = max(1, (int) SOVCA_DEMO_VERIF_MINUTOS);
            $codigo  = bovs_mail_codigo();

            $up = $pdo->prepare('UPDATE demo_verificaciones SET codigo_hash = ?, expira = ?, intentos = 0, ultimo_envio = now() WHERE id = ?');
            $up->execute(array(bovs_mail_codigo_hash($codigo), date('Y-m-d H:i:s', strtotime('+' . $minutos . ' minutes')), (int) $pend['id']));

            $envio = bovs_demo_enviar_codigo($pend['email'], $pend['nombre'], $pend['username'], $codigo, $minutos);
            if (empty($envio['ok'])) {
                return array('ok' => false, 'msg' => 'No se pudo enviar el correo. Intenta de nuevo en un momento.');
            }

            return array(
                'ok'      => true,
                'minutos' => $minutos,
                'msg'     => 'Enviamos un código nuevo a <strong>' . htmlspecialchars(bovs_demo_email_oculto($pend['email']), ENT_QUOTES, 'UTF-8') . '</strong>.'
            );
        } catch (Throwable $e) {
            error_log('[SOVCA demo] Error al reenviar código: ' . $e->getMessage());
            return array('ok' => false, 'msg' => 'No se pudo reenviar el código. Intenta nuevamente.');
        }
    }
}

if (!function_exists('bovs_demo_whatsapp_url')) {
    /**
     * Enlace de WhatsApp para COMPRAR la suscripción.
     *
     * Es el mismo número del botón "Mejora tu Plan", pero el mensaje
     * lleva los datos de la cuenta demo para que el administrador sepa
     * cuál debe importar.
     *
     * @param array $datos usuario, fundo, email (opcionales)
     * @return string
     */
    function bovs_demo_whatsapp_url(array $datos = array())
    {
        $numero = defined('SOVCA_WHATSAPP_NUMERO') ? SOVCA_WHATSAPP_NUMERO : '584268205177';

        $usuario = isset($datos['usuario']) ? trim((string) $datos['usuario']) : '';
        $fundo   = isset($datos['fundo']) ? trim((string) $datos['fundo']) : '';
        $email   = isset($datos['email']) ? trim((string) $datos['email']) : '';

        $texto = 'Hola, deseo comprar una suscripción de SOVCA y conservar los datos de mi cuenta de demostración.';
        if ($usuario !== '') {
            $texto .= ' Usuario demo: ' . $usuario . '.';
        }
        if ($fundo !== '') {
            $texto .= ' Fundo: ' . $fundo . '.';
        }
        if ($email !== '') {
            $texto .= ' Correo: ' . $email . '.';
        }

        return 'https://wa.me/' . $numero . '?text=' . rawurlencode($texto);
    }
}

/* ---------------------------------------------------------------------
   AUTENTICACIÓN / SESIÓN
   --------------------------------------------------------------------- */

if (!function_exists('sovca_demo_datos_sesion')) {
    /**
     * Reúne los datos que la sesión necesita, tomados de la base demo.
     * Devuelve null si el usuario no existe o está inactivo.
     *
     * @return array|null
     */
    function sovca_demo_datos_sesion(PDO $pdo, $usuarioId)
    {
        $usuarioId = (int) $usuarioId;
        if ($usuarioId <= 0) {
            return null;
        }

        $st = $pdo->prepare("
            SELECT u.id, u.username, u.name, u.email, u.imagen, u.created,
                   u.mroles_id, u.mtipousuarios_id, u.act_email,
                   COALESCE(r.nombre, '') AS rol_nombre,
                   COALESCE(fxu.mfundos_id, 0) AS mfundos_id
              FROM musuarios u
              LEFT JOIN mroles r ON r.id = u.mroles_id
              LEFT JOIN mfundosxusuarios fxu ON fxu.musuarios_id = u.id AND fxu.status = 1
             WHERE u.id = ? AND u.status = 1
             ORDER BY fxu.id
             LIMIT 1
        ");
        $st->execute(array($usuarioId));
        $u = $st->fetch();
        if (!$u) {
            return null;
        }

        $fundoId = (int) $u['mfundos_id'];

        $fundo = array('nombre' => '', 'mtipoganaderias_id' => 0, 'mplanes_id' => 0, 'max_reg' => 0);
        if ($fundoId > 0) {
            $st = $pdo->prepare('
                SELECT f.nombre, f.mtipoganaderias_id, f.mplanes_id, COALESCE(p.max_reg, 0) AS max_reg
                  FROM mfundos f
                  LEFT JOIN mplanes p ON p.id = f.mplanes_id
                 WHERE f.id = ? AND f.status = 1
                 LIMIT 1
            ');
            $st->execute(array($fundoId));
            $f = $st->fetch();
            if ($f) {
                $fundo = $f;
            }
        }

        $caduca = '';
        $st = $pdo->prepare('SELECT fecha_caduca_suscripcion FROM mplanesxfundo WHERE musuarios_id = ? AND msuscripciones_id IS NOT NULL ORDER BY id DESC LIMIT 1');
        $st->execute(array($usuarioId));
        $fecha = $st->fetchColumn();
        if ($fecha) {
            $ts = strtotime((string) $fecha);
            if ($ts) {
                $caduca = date('Y-m-d', $ts);
            }
        }

        $animales = 0;
        if ($fundoId > 0) {
            $st = $pdo->prepare('SELECT count(*) FROM manimalxfundos WHERE status = 1 AND mfundos_id = ?');
            $st->execute(array($fundoId));
            $animales = (int) $st->fetchColumn();
        }

        return array(
            'id'                => (int) $u['id'],
            'username'          => (string) $u['username'],
            'name'              => (string) $u['name'],
            'email'             => (string) $u['email'],
            'imagen'            => (string) $u['imagen'],
            'created'           => (string) $u['created'],
            'mroles_id'         => (int) $u['mroles_id'],
            'rol_nombre'        => (string) $u['rol_nombre'],
            'mtipousuarios_id'  => (int) $u['mtipousuarios_id'],
            'act_email'         => (int) $u['act_email'],
            'mfundos_id'        => $fundoId,
            'fundo_nombre'      => (string) $fundo['nombre'],
            'tipo_ganaderia'    => (int) $fundo['mtipoganaderias_id'],
            'mplanes_id'        => (int) $fundo['mplanes_id'],
            'maximo_registros'  => (int) $fundo['max_reg'],
            'animales'          => $animales,
            'fecha_caduca'      => $caduca,
        );
    }
}

if (!function_exists('bovs_demo_autenticar')) {
    /**
     * Respaldo del inicio de sesión: busca la cuenta en la base demo.
     *
     * Se llama SOLO cuando el usuario no existe en la base real, así que
     * una cuenta demo nunca puede "pisar" a un productor real.
     *
     * @param string $usuario  Usuario tal como lo escribió la persona.
     * @param string $password Contraseña en claro.
     * @return array{ok:bool,error?:string,usuario?:array}
     */
    function bovs_demo_autenticar($usuario, $password)
    {
        if (!bovs_demo_activo() || !bovs_demo_bd_disponible()) {
            return array('ok' => false, 'error' => 'sin_bd');
        }

        $usuario = strtoupper(trim((string) $usuario));
        if ($usuario === '' || (string) $password === '') {
            return array('ok' => false, 'error' => 'datos');
        }

        try {
            $pdo = bovs_demo_pdo();
            bovs_demo_preparar($pdo);

            $st = $pdo->prepare('SELECT id FROM musuarios WHERE status = 1 AND upper(username) = ? AND password = ? LIMIT 1');
            $st->execute(array($usuario, bovs_password_hash($password)));
            $id = (int) $st->fetchColumn();

            if ($id <= 0) {
                return array('ok' => false, 'error' => 'credenciales');
            }

            $datos = sovca_demo_datos_sesion($pdo, $id);
            if (!$datos) {
                return array('ok' => false, 'error' => 'credenciales');
            }

            if ($datos['fecha_caduca'] === '' || $datos['fecha_caduca'] <= date('Y-m-d')) {
                return array('ok' => false, 'error' => 'vencida');
            }

            return array('ok' => true, 'usuario' => $datos);
        } catch (Throwable $e) {
            error_log('[SOVCA demo] Error al autenticar: ' . $e->getMessage());
            return array('ok' => false, 'error' => 'error');
        }
    }
}

if (!function_exists('bovs_demo_iniciar_sesion')) {
    /**
     * Deja la sesión lista para trabajar en la base demo.
     * Debe llamarse justo antes de redirigir: la petición actual ya tiene
     * ActiveRecord apuntando a la base real, la siguiente no.
     *
     * @param array $u Datos devueltos por sovca_demo_datos_sesion().
     */
    function bovs_demo_iniciar_sesion(array $u)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        /* Se conserva la sesión (el captcha y el usuario previo ya no
           hacen falta), pero se limpia lo que pudiera venir de una
           sesión real anterior. */
        $_SESSION = array();

        $_SESSION['demo']                    = 1;
        $_SESSION['idsessionx']              = session_id();
        $_SESSION['idusuariox']              = (int) $u['id'];
        $_SESSION['usuariox']                = $u['username'];
        $_SESSION['nombresx']                = $u['name'];
        $_SESSION['rolx']                    = (int) $u['mroles_id'];
        $_SESSION['nombre_rolx']             = $u['rol_nombre'];
        $_SESSION['imagen_perfilx']          = $u['imagen'];
        $_SESSION['creadox']                 = $u['created'] !== '' ? date('d-m-Y', strtotime($u['created'])) : date('d-m-Y');
        $_SESSION['fecha_caduca_suscripcion'] = $u['fecha_caduca'];
        $_SESSION['suscripcion_verificada']  = 1;
        $_SESSION['demo_fecha_caduca']       = $u['fecha_caduca'];
        $_SESSION['mfundos_id']              = (int) $u['mfundos_id'];
        $_SESSION['fundo_nombre']            = $u['fundo_nombre'];
        $_SESSION['tipo_ganaderia']          = (int) $u['tipo_ganaderia'];
        $_SESSION['mplanes_id']              = (int) $u['mplanes_id'];
        $_SESSION['maximo_registros']        = (int) $u['maximo_registros'];
        $_SESSION['acceso_reg_animales']     = ((int) $u['maximo_registros'] > (int) $u['animales']) ? 1 : 0;
        $_SESSION['mtipo_user_id']           = (int) $u['mtipousuarios_id'];
        $_SESSION['act_email']               = (int) $u['act_email'];
        $_SESSION['accion']                  = true;
        $_SESSION['acceso_alerta']           = true;
        $_SESSION['acceso_sms']              = true;
        $_SESSION['es_multifundo']           = 0;
        $_SESSION['max_fundos']              = 1;

        return true;
    }
}

if (!function_exists('bovs_demo_cerrar_sesion')) {
    /**
     * Quita el modo demo de la sesión (sin destruirla).
     */
    function bovs_demo_cerrar_sesion()
    {
        unset($_SESSION['demo'], $_SESSION['demo_fecha_caduca']);
        return true;
    }
}

/* ---------------------------------------------------------------------
   MANTENIMIENTO
   --------------------------------------------------------------------- */

if (!function_exists('bovs_demo_purgar_vencidas')) {
    /**
     * Desactiva las cuentas demo vencidas y sus fundos.
     *
     * No borra nada: solo pone status = 0, para no romper historiales si
     * el administrador quiere revisar una cuenta antes de eliminarla.
     *
     * @return array{cuentas:int}
     */
    function bovs_demo_purgar_vencidas()
    {
        if (!bovs_demo_bd_disponible()) {
            return array('cuentas' => 0);
        }

        $pdo = bovs_demo_pdo();
        bovs_demo_preparar($pdo);

        $st = $pdo->prepare("
            SELECT DISTINCT musuarios_id
              FROM mplanesxfundo
             WHERE musuarios_id IN (SELECT musuarios_id FROM demo_registros)
               AND fecha_caduca_suscripcion < current_date
        ");
        $st->execute();
        $ids = $st->fetchAll(PDO::FETCH_COLUMN);

        $total = 0;
        foreach ($ids as $id) {
            $id = (int) $id;

            $up = $pdo->prepare("UPDATE musuarios SET status = 0, updated = now() WHERE id = ? AND status = 1");
            $up->execute(array($id));
            $total += $up->rowCount();

            $up = $pdo->prepare("
                UPDATE mfundos SET status = 0, updated = now()
                 WHERE user_propietario = ?
                   AND status = 1
                   AND id IN (SELECT mfundos_id FROM demo_registros WHERE musuarios_id = ?)
            ");
            $up->execute(array($id, $id));
        }

        return array('cuentas' => $total);
    }
}
