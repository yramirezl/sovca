<?php
/**
 * =====================================================================
 * lib/demo_import_helper.php — IMPORTAR UNA CUENTA DEMO A OFICIAL
 * ---------------------------------------------------------------------
 * Permite que el administrador convierta una cuenta de demostración en
 * una cuenta definitiva:
 *
 *   1. lista las cuentas demo (con sus procesos cargados),
 *   2. crea la cuenta oficial (usuario + fundo + plan + suscripción),
 *   3. copia del fundo demo al fundo nuevo los procesos elegidos
 *      (animales, servicios, nacimientos, controles, catálogos…),
 *   4. deja la cuenta demo cerrada y marcada como migrada.
 *
 * Cómo se copian los datos
 * ------------------------
 * Ambas bases (demo y oficial) están en el mismo servidor y tienen la
 * MISMA estructura, así que se copia fila por fila con PDO:
 *
 *   · `id`         → id_demo + offset de la tabla en el destino
 *                    (offset = MAX(id) del destino antes de importar),
 *   · `mfundos_id` → el fundo oficial recién creado,
 *   · claves de catálogos propios del fundo (lote, potrero, raza,
 *     categoría, producto, presentación, período…) → se remapean con el
 *     offset de la tabla referenciada,
 *   · `uuid_local` → se regenera (tiene índice ÚNICO),
 *   · `user_create`/`user_modify` → usuario oficial.
 *
 * Al final se ajustan las secuencias para que la app siga insertando
 * sin chocar con los ids importados.
 *
 * No se copia nada de la cuenta real hacia la demo ni al contrario: la
 * única lectura de la base demo es la de este módulo, ejecutada por un
 * administrador.
 * =====================================================================
 */

require_once __DIR__ . '/password_policy.php';

/* ---------------------------------------------------------------------
   CONEXIÓN A LA BASE OFICIAL
   --------------------------------------------------------------------- */

if (!function_exists('bovs_oficial_pdo')) {
    /**
     * Conexión PDO a la base oficial (destino de la importación).
     *
     * @return PDO
     */
    function bovs_oficial_pdo()
    {
        static $pdo = null;

        if ($pdo instanceof PDO) {
            return $pdo;
        }

        $pdo = new PDO(
            'pgsql:host=' . SOVCA_OFICIAL_HOST
                . ';port=' . SOVCA_OFICIAL_PUERTO
                . ';dbname=' . SOVCA_OFICIAL_DB,
            SOVCA_OFICIAL_USUARIO,
            SOVCA_OFICIAL_CLAVE,
            array(
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            )
        );

        return $pdo;
    }
}

/* ---------------------------------------------------------------------
   DEFINICIÓN DE LOS PROCESOS QUE SE PUEDEN IMPORTAR
   --------------------------------------------------------------------- */

if (!function_exists('bovs_import_grupos')) {
    /**
     * Grupos que ve el administrador, con las tablas de cada uno y el
     * remapeo de sus claves internas.
     *
     * `depende` = grupos que se incluyen automáticamente (si no, las
     * claves internas quedarían apuntando a filas que no existen).
     *
     * @return array
     */
    function bovs_import_grupos()
    {
        return array(
            'categorias' => array(
                'etiqueta'    => 'Categorías',
                'descripcion' => 'Categorías propias del fundo (las estándar ya existen en la cuenta oficial).',
                'icono'       => 'fa-tags',
                'depende'     => array(),
                'tablas'      => array(
                    array('tabla' => 'mcategorias', 'remaps' => array()),
                ),
            ),
            'razas' => array(
                'etiqueta'    => 'Razas',
                'descripcion' => 'Razas creadas por el usuario en la demostración.',
                'icono'       => 'fa-dna',
                'depende'     => array(),
                'tablas'      => array(
                    array('tabla' => 'mrazas', 'remaps' => array()),
                ),
            ),
            'lotes' => array(
                'etiqueta'    => 'Lotes',
                'descripcion' => 'Lotes del fundo de prueba.',
                'icono'       => 'fa-layer-group',
                'depende'     => array(),
                'tablas'      => array(
                    array('tabla' => 'mlotes', 'remaps' => array()),
                ),
            ),
            'potreros' => array(
                'etiqueta'    => 'Potreros',
                'descripcion' => 'Potreros del fundo de prueba.',
                'icono'       => 'fa-draw-polygon',
                'depende'     => array(),
                'tablas'      => array(
                    array('tabla' => 'mpotreros', 'remaps' => array()),
                ),
            ),
            'productos' => array(
                'etiqueta'    => 'Productos y presentaciones',
                'descripcion' => 'Catálogo de productos de control sanitario.',
                'icono'       => 'fa-flask-vial',
                'depende'     => array(),
                'tablas'      => array(
                    array('tabla' => 'mcategoriaproductos', 'remaps' => array()),
                    array('tabla' => 'mpresentaciones', 'remaps' => array()),
                    array('tabla' => 'mproductos', 'remaps' => array(
                        'mcategoriaproductos_id' => 'mcategoriaproductos',
                        'presetacion_id'         => 'mpresentaciones',
                    )),
                ),
            ),
            'parametros' => array(
                'etiqueta'    => 'Períodos y metas',
                'descripcion' => 'Períodos reproductivos y metas de servicios.',
                'icono'       => 'fa-calendar-days',
                'depende'     => array(),
                'tablas'      => array(
                    array('tabla' => 'mperiodos', 'remaps' => array()),
                    array('tabla' => 'mmetaservicios', 'remaps' => array()),
                ),
            ),
            'animales' => array(
                'etiqueta'    => 'Ficha de animales',
                'descripcion' => 'Todos los animales con sus datos, lote, potrero, raza y categoría.',
                'icono'       => 'fa-cow',
                'depende'     => array('lotes', 'potreros', 'razas', 'categorias'),
                'tablas'      => array(
                    array('tabla' => 'manimales', 'remaps' => array(
                        'mlotes_id'            => 'mlotes',
                        'mpotreros_id'         => 'mpotreros',
                        'mrazas_id'            => 'mrazas',
                        'categoria_ingreso_id' => 'mcategorias',
                        'categoria_futura_id'  => 'mcategorias',
                    )),
                    array('tabla' => 'manimalxfundos', 'remaps' => array('manimales_id' => 'manimales')),
                ),
            ),
            'servicios' => array(
                'etiqueta'    => 'Servicios',
                'descripcion' => 'Servicios (monta, inseminación, transferencia) y sus controles.',
                'icono'       => 'fa-heart-pulse',
                'depende'     => array('animales'),
                'tablas'      => array(
                    array('tabla' => 'mregservicios', 'remaps' => array('mrazas_id' => 'mrazas')),
                ),
            ),
            'nacimientos' => array(
                'etiqueta'    => 'Nacimientos',
                'descripcion' => 'Partos registrados y sus crías.',
                'icono'       => 'fa-baby',
                'depende'     => array('animales', 'servicios'),
                'tablas'      => array(
                    array('tabla' => 'mregnacimientos', 'remaps' => array('mregservicios_id' => 'mregservicios')),
                    array('tabla' => 'mternerosxnacimientos', 'remaps' => array('mregnacimientos_id' => 'mregnacimientos')),
                ),
            ),
            'proleche' => array(
                'etiqueta'    => 'Producción de leche',
                'descripcion' => 'Ordeños registrados.',
                'icono'       => 'fa-glass-water',
                'depende'     => array('animales'),
                'tablas'      => array(
                    array('tabla' => 'mprolecheras', 'remaps' => array()),
                ),
            ),
            'pesos' => array(
                'etiqueta'    => 'Consecutivo de pesos',
                'descripcion' => 'Pesajes registrados.',
                'icono'       => 'fa-weight-scale',
                'depende'     => array('animales'),
                'tablas'      => array(
                    array('tabla' => 'mconsecutivopesos', 'remaps' => array()),
                ),
            ),
            'control' => array(
                'etiqueta'    => 'Control sanitario',
                'descripcion' => 'Controles individuales y sus productos.',
                'icono'       => 'fa-syringe',
                'depende'     => array('animales', 'productos'),
                'tablas'      => array(
                    array('tabla' => 'mcontrolsanitarios', 'remaps' => array()),
                    array('tabla' => 'mdetacrlsanitarios', 'remaps' => array(
                        'mcontrolsanitarios_id' => 'mcontrolsanitarios',
                        'mproductos_id'         => 'mproductos',
                    )),
                ),
            ),
            'control_lotes' => array(
                'etiqueta'    => 'Control sanitario por lote',
                'descripcion' => 'Controles aplicados a lotes completos.',
                'icono'       => 'fa-boxes-stacked',
                'depende'     => array('animales', 'lotes', 'productos'),
                'tablas'      => array(
                    array('tabla' => 'mcontrolsanitarioxlotes', 'remaps' => array('mlotes_id' => 'mlotes')),
                    array('tabla' => 'mdetacrlsanitariolotes', 'remaps' => array(
                        'mcontrolsanitarioxlotes_id' => 'mcontrolsanitarioxlotes',
                        'mproductos_id'              => 'mproductos',
                    )),
                    array('tabla' => 'mdetacsxlotes', 'remaps' => array(
                        'mcontrolsanitarioxlotes_id' => 'mcontrolsanitarioxlotes',
                        'manimales_id'               => 'manimales',
                    )),
                ),
            ),
            'desincorporacion' => array(
                'etiqueta'    => 'Desincorporaciones',
                'descripcion' => 'Bajas y crías sin ficha.',
                'icono'       => 'fa-file-export',
                'depende'     => array('animales'),
                'tablas'      => array(
                    array('tabla' => 'mdesincorporacionanimales', 'remaps' => array()),
                    array('tabla' => 'mdsinfichas', 'remaps' => array()),
                ),
            ),
            'movreb' => array(
                'etiqueta'    => 'Movimiento de rebaño',
                'descripcion' => 'Movimientos entre lotes y potreros.',
                'icono'       => 'fa-shuffle',
                'depende'     => array('animales', 'lotes', 'potreros', 'categorias'),
                'tablas'      => array(
                    array('tabla' => 'mmovimientos_rebano', 'remaps' => array(
                        'mlotes_origen_id'       => 'mlotes',
                        'mlotes_destino_id'      => 'mlotes',
                        'mpotreros_origen_id'    => 'mpotreros',
                        'mpotreros_destino_id'   => 'mpotreros',
                        'movimiento_reversion_id' => 'mmovimientos_rebano',
                    )),
                    array('tabla' => 'mmovimientos_rebano_detalle', 'remaps' => array(
                        'mmovimientos_rebano_id' => 'mmovimientos_rebano',
                        'manimales_id'           => 'manimales',
                        'lote_anterior_id'       => 'mlotes',
                        'lote_nuevo_id'          => 'mlotes',
                        'potrero_anterior_id'    => 'mpotreros',
                        'potrero_nuevo_id'       => 'mpotreros',
                        'categoria_anterior_id'  => 'mcategorias',
                        'categoria_nueva_id'     => 'mcategorias',
                    )),
                ),
            ),
        );
    }
}

if (!function_exists('bovs_import_orden')) {
    /**
     * Orden de inserción: primero los catálogos y los animales, después
     * los procesos (los hijos siempre van después de sus padres).
     *
     * @return array Lista de tablas en orden.
     */
    function bovs_import_orden()
    {
        return array(
            'mcategoriaproductos',
            'mpresentaciones',
            'mrazas',
            'mcategorias',
            'mlotes',
            'mpotreros',
            'mperiodos',
            'mmetaservicios',
            'mproductos',
            'manimales',
            'manimalxfundos',
            'mregservicios',
            'mregnacimientos',
            'mternerosxnacimientos',
            'mprolecheras',
            'mconsecutivopesos',
            'mcontrolsanitarios',
            'mdetacrlsanitarios',
            'mcontrolsanitarioxlotes',
            'mdetacrlsanitariolotes',
            'mdetacsxlotes',
            'mdesincorporacionanimales',
            'mdsinfichas',
            'mmovimientos_rebano',
            'mmovimientos_rebano_detalle',
        );
    }
}

if (!function_exists('bovs_import_mapa_tablas')) {
    /**
     * Indexa las tablas de todos los grupos por nombre:
     *   tabla => array('grupo' => ..., 'remaps' => ...)
     *
     * @return array
     */
    function bovs_import_mapa_tablas()
    {
        static $mapa = null;

        if ($mapa !== null) {
            return $mapa;
        }

        $mapa = array();
        foreach (bovs_import_grupos() as $clave => $grupo) {
            foreach ($grupo['tablas'] as $t) {
                $mapa[$t['tabla']] = array(
                    'grupo'  => $clave,
                    'remaps' => isset($t['remaps']) ? $t['remaps'] : array(),
                );
            }
        }

        return $mapa;
    }
}

if (!function_exists('bovs_import_expandir')) {
    /**
     * Agrega los grupos de los que depende la selección (hasta quedar
     * estable). Sin esto, una clave interna podría apuntar a una fila
     * que no se importó.
     *
     * @param array $seleccion Claves de grupos.
     * @return array
     */
    function bovs_import_expandir(array $seleccion)
    {
        $grupos  = bovs_import_grupos();
        $elegidos = array();

        foreach ($seleccion as $clave) {
            $clave = (string) $clave;
            if (isset($grupos[$clave])) {
                $elegidos[$clave] = true;
            }
        }

        $cambio = true;
        while ($cambio) {
            $cambio = false;
            foreach (array_keys($elegidos) as $clave) {
                foreach ($grupos[$clave]['depende'] as $dep) {
                    if (!isset($elegidos[$dep]) && isset($grupos[$dep])) {
                        $elegidos[$dep] = true;
                        $cambio = true;
                    }
                }
            }
        }

        /* Se devuelven en el orden de definición para que la pantalla sea estable. */
        $salida = array();
        foreach (array_keys($grupos) as $clave) {
            if (isset($elegidos[$clave])) {
                $salida[] = $clave;
            }
        }

        return $salida;
    }
}

/* ---------------------------------------------------------------------
   CONSULTAS SOBRE LA BASE DEMO
   --------------------------------------------------------------------- */

if (!function_exists('bovs_import_listar_demos')) {
    /**
     * Cuentas demo con su fundo, vigencia y estado de migración.
     *
     * @return array
     */
    function bovs_import_listar_demos()
    {
        if (!function_exists('bovs_demo_pdo')) {
            require_once __DIR__ . '/demo_helper.php';
        }

        $pdo = bovs_demo_pdo();
        bovs_demo_preparar($pdo);

        $sql = "
            SELECT u.id            AS usuario_id,
                   u.username,
                   u.name,
                   u.email,
                   u.status,
                   u.created,
                   f.id            AS fundo_id,
                   f.nombre        AS fundo_nombre,
                   v.fecha_caduca_suscripcion::date AS caduca,
                   COALESCE(d.migrado, false)       AS migrado,
                   d.migrado_a_usuario,
                   d.migrado_fecha,
                   d.cerrada,
                   d.ip
              FROM musuarios u
              JOIN mfundosxusuarios fxu ON fxu.musuarios_id = u.id AND fxu.status = 1
              JOIN mfundos f            ON f.id = fxu.mfundos_id
              LEFT JOIN LATERAL (
                    SELECT p.fecha_caduca_suscripcion
                      FROM mplanesxfundo p
                     WHERE p.musuarios_id = u.id AND p.msuscripciones_id IS NOT NULL
                     ORDER BY p.id DESC LIMIT 1
              ) v ON true
              LEFT JOIN LATERAL (
                    SELECT r.migrado, r.migrado_a_usuario, r.migrado_fecha, r.cerrada, r.ip
                      FROM demo_registros r
                     WHERE r.musuarios_id = u.id
                     ORDER BY r.id DESC LIMIT 1
              ) d ON true
             ORDER BY u.created DESC, u.id DESC
        ";

        $filas = $pdo->query($sql)->fetchAll();
        $hoy   = date('Y-m-d');

        foreach ($filas as &$f) {
            $caduca = $f['caduca'] !== null ? substr((string) $f['caduca'], 0, 10) : '';

            if (!empty($f['migrado'])) {
                $f['estado'] = 'migrada';
            } elseif (!empty($f['cerrada'])) {
                $f['estado'] = 'cerrada';
            } elseif ((int) $f['status'] !== 1) {
                $f['estado'] = 'inactiva';
            } elseif ($caduca === '' || $caduca < $hoy) {
                $f['estado'] = 'vencida';
            } else {
                $f['estado'] = 'activa';
            }
        }
        unset($f);

        return $filas;
    }
}

if (!function_exists('bovs_import_resumen_demo')) {
    /**
     * Filas que hay en cada grupo para un fundo demo.
     *
     * @param int $fundoDemo
     * @return array grupo => array('total'=>int, 'tablas'=>array(tabla=>int))
     */
    function bovs_import_resumen_demo($fundoDemo)
    {
        $fundoDemo = (int) $fundoDemo;
        $resumen   = array();

        if ($fundoDemo <= 0) {
            return $resumen;
        }

        $pdo = bovs_demo_pdo();

        foreach (bovs_import_grupos() as $clave => $grupo) {
            $total  = 0;
            $tablas = array();

            foreach ($grupo['tablas'] as $t) {
                $cant = bovs_import_contar_tabla($pdo, $t['tabla'], $fundoDemo);
                $tablas[$t['tabla']] = $cant;
                $total += $cant;
            }

            $resumen[$clave] = array('total' => $total, 'tablas' => $tablas);
        }

        return $resumen;
    }
}

if (!function_exists('bovs_import_contar_tabla')) {
    /**
     * Cuenta las filas de una tabla que pertenecen al fundo demo.
     */
    function bovs_import_contar_tabla(PDO $pdo, $tabla, $fundoDemo)
    {
        $fundoDemo = (int) $fundoDemo;
        $tabla     = (string) $tabla;

        if ($tabla === 'mmovimientos_rebano_detalle') {
            $sql = 'SELECT count(*) FROM mmovimientos_rebano_detalle d
                     WHERE d.mmovimientos_rebano_id IN (SELECT id FROM mmovimientos_rebano WHERE mfundos_id = ?)';
        } else {
            $sql = 'SELECT count(*) FROM "' . $tabla . '" WHERE mfundos_id = ?';
        }

        try {
            $st = $pdo->prepare($sql);
            $st->execute(array($fundoDemo));
            return (int) $st->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }
}

/* ---------------------------------------------------------------------
   CUENTA OFICIAL
   --------------------------------------------------------------------- */

if (!function_exists('bovs_import_crear_cuenta')) {
    /**
     * Crea la cuenta definitiva (usuario + fundo + vínculo + vigencia).
     *
     * @param array $d Datos del formulario.
     * @return array{ok:bool,msg:string,usuario_id?:int,fundo_id?:int,caduca?:string}
     */
    function bovs_import_crear_cuenta(array $d)
    {
        $nombre    = trim((string) (isset($d['nombre']) ? $d['nombre'] : ''));
        $email     = strtolower(trim((string) (isset($d['email']) ? $d['email'] : '')));
        $usuario   = strtoupper(trim((string) (isset($d['usuario']) ? $d['usuario'] : '')));
        $telefono  = trim((string) (isset($d['telefono']) ? $d['telefono'] : ''));
        $clave     = (string) (isset($d['clave']) ? $d['clave'] : '');
        $clave2    = (string) (isset($d['clave2']) ? $d['clave2'] : '');
        $plan      = (int) (isset($d['plan_id']) ? $d['plan_id'] : 0);
        $suscrip   = (int) (isset($d['suscripcion_id']) ? $d['suscripcion_id'] : 0);
        $activa    = trim((string) (isset($d['fecha_activa']) ? $d['fecha_activa'] : ''));
        $fundoNom  = trim((string) (isset($d['fundo_nombre']) ? $d['fundo_nombre'] : ''));
        $rif       = trim((string) (isset($d['cid_rif']) ? $d['cid_rif'] : ''));
        $ganaderia = (int) (isset($d['tipo_ganaderia']) ? $d['tipo_ganaderia'] : 1);
        $adminId   = (int) (isset($d['admin_id']) ? $d['admin_id'] : 0);

        $errores = array();

        if (mb_strlen($nombre) < 3) {
            $errores[] = 'Escribe el nombre completo.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'El correo electrónico no es válido.';
        }
        if (!preg_match('/^[A-Z0-9._-]{4,30}$/', $usuario)) {
            $errores[] = 'El usuario debe tener entre 4 y 30 caracteres (letras, números, punto, guion o guion bajo).';
        }
        if ($clave !== $clave2) {
            $errores[] = 'Las dos contraseñas no coinciden.';
        } else {
            $errores = array_merge($errores, bovs_password_errores($clave));
        }
        if ($fundoNom === '') {
            $errores[] = 'Indica el nombre del fundo.';
        }
        if ($rif === '') {
            $errores[] = 'Indica el CID/RIF del fundo.';
        }
        if ($plan <= 0 || $suscrip <= 0) {
            $errores[] = 'Selecciona el plan y el tipo de suscripción.';
        }

        $fecha = DateTime::createFromFormat('Y-m-d', $activa);
        if (!$fecha || $fecha->format('Y-m-d') !== $activa) {
            $errores[] = 'La fecha de activación no es válida.';
        }

        if (!empty($errores)) {
            return array('ok' => false, 'msg' => implode(' ', $errores));
        }

        try {
            $pdo = bovs_oficial_pdo();

            /* --- Duplicados entre cuentas activas -------------------- */
            $st = $pdo->prepare('SELECT id FROM musuarios WHERE status = 1 AND upper(username) = ? LIMIT 1');
            $st->execute(array($usuario));
            if ($st->fetchColumn()) {
                return array('ok' => false, 'msg' => 'El usuario <strong>' . htmlspecialchars($usuario, ENT_QUOTES, 'UTF-8') . '</strong> ya existe en el sistema.');
            }

            $st = $pdo->prepare("SELECT id FROM musuarios WHERE status = 1 AND lower(email) = ? AND coalesce(email,'') <> '' LIMIT 1");
            $st->execute(array($email));
            if ($st->fetchColumn()) {
                return array('ok' => false, 'msg' => 'Ese correo ya está registrado en otra cuenta.');
            }

            /* --- Vigencia según el tipo de suscripción ---------------- */
            $st = $pdo->prepare('SELECT cantidad_dias FROM msuscripciones WHERE id = ? AND status = 1');
            $st->execute(array($suscrip));
            $dias = (int) $st->fetchColumn();
            if ($dias <= 0) {
                return array('ok' => false, 'msg' => 'El tipo de suscripción elegido no es válido.');
            }

            $caduca = clone $fecha;
            $caduca->modify('+' . $dias . ' days');
            $fechaCaduca = $caduca->format('Y-m-d');

            /* --- Datos geográficos por defecto ------------------------ */
            $pais = (int) $pdo->query("SELECT id FROM mpaises WHERE paisnombre ILIKE '%venezuela%' ORDER BY id LIMIT 1")->fetchColumn();
            if ($pais <= 0) {
                $pais = (int) $pdo->query('SELECT id FROM mpaises ORDER BY id LIMIT 1')->fetchColumn();
            }
            $estado = '';
            if ($pais > 0) {
                $st = $pdo->prepare('SELECT id FROM mestados WHERE ubicacionpaisid = ? ORDER BY id LIMIT 1');
                $st->execute(array($pais));
                $estado = (string) $st->fetchColumn();
            }

            $pdo->beginTransaction();

            $st = $pdo->prepare("
                INSERT INTO musuarios
                    (mroles_id, username, password, name, email, imagen, updated, created,
                     status, user_create, user_modify, cid, act_clave, act_email, clave_email,
                     telefono, mtipousuarios_id, mplanes_id, fecha_activacion,
                     es_multifundo, max_fundos, ultimo_fundo_id, acceso_offline,
                     password_reset_intentos)
                VALUES
                    (2, ?, ?, ?, ?, NULL, now(), now(),
                     1, ?, ?, NULL, 1, 1, NULL,
                     ?, 0, ?, now(),
                     false, 1, NULL, false,
                     0)
                RETURNING id
            ");
            $st->execute(array(
                $usuario,
                bovs_password_hash($clave),
                $nombre,
                $email,
                $adminId > 0 ? $adminId : null,
                $adminId > 0 ? $adminId : null,
                substr($telefono, 0, 1),
                $plan,
            ));
            $usuarioId = (int) $st->fetchColumn();

            $st = $pdo->prepare("
                INSERT INTO mfundos
                    (cid_rif, nombre, mtipofundos_id, mpaises_id, mestados_id, mmunicipios_id,
                     direccion, descripcion, updated, created, status,
                     user_create, user_propietario, user_modify,
                     mtipoganaderias_id, mplanes_id)
                VALUES
                    (?, ?, 1, ?, ?, '0',
                     NULL, ?, NULL, now(), 1,
                     ?, ?, NULL,
                     ?, ?)
                RETURNING id
            ");
            $st->execute(array(
                substr($rif, 0, 180),
                $fundoNom,
                $pais,
                $estado !== '' ? $estado : '0',
                'Cuenta importada desde la demostración.',
                $adminId > 0 ? $adminId : null,
                $usuarioId,
                $ganaderia,
                $plan,
            ));
            $fundoId = (int) $st->fetchColumn();

            $st = $pdo->prepare('INSERT INTO mfundosxusuarios (musuarios_id, mfundos_id, created, status, user_create) VALUES (?, ?, now(), 1, ?)');
            $st->execute(array($usuarioId, $fundoId, $adminId > 0 ? $adminId : null));

            $st = $pdo->prepare('
                INSERT INTO mplanesxfundo
                    (musuarios_id, msuscripciones_id, mfundos_id, mplanes_id,
                     fecha_activa_suscripcion, fecha_caduca_suscripcion)
                VALUES (?, ?, ?, ?, ?, ?)
            ');
            $st->execute(array($usuarioId, $suscrip, $fundoId, $plan, $fecha->format('Y-m-d'), $fechaCaduca));

            $st = $pdo->prepare('UPDATE musuarios SET ultimo_fundo_id = ? WHERE id = ?');
            $st->execute(array($fundoId, $usuarioId));

            $pdo->commit();
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[SOVCA import demo] Error al crear cuenta: ' . $e->getMessage());

            if ($e instanceof PDOException && (string) $e->getCode() === '23505') {
                return array('ok' => false, 'msg' => 'Ese usuario o correo ya existe. Elige otros datos.');
            }

            return array('ok' => false, 'msg' => 'No se pudo crear la cuenta oficial. Intenta nuevamente.');
        }

        return array(
            'ok'         => true,
            'msg'        => 'Cuenta oficial creada.',
            'usuario_id' => $usuarioId,
            'fundo_id'   => $fundoId,
            'caduca'     => $fechaCaduca,
        );
    }
}

/* ---------------------------------------------------------------------
   MOTOR DE IMPORTACIÓN
   --------------------------------------------------------------------- */

if (!function_exists('bovs_import_uuid')) {
    /** UUID v4 para regenerar uuid_local (tiene índice único en la base). */
    function bovs_import_uuid()
    {
        $d = random_bytes(16);
        $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
        $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
    }
}

if (!function_exists('bovs_import_columnas_info')) {
    /**
     * Columnas de una tabla del destino con su tipo de dato.
     *
     * @return array nombre => data_type
     */
    function bovs_import_columnas_info(PDO $pdo, $tabla)
    {
        static $cache = array();

        if (isset($cache[$tabla])) {
            return $cache[$tabla];
        }

        $st = $pdo->prepare("
            SELECT column_name, data_type
              FROM information_schema.columns
             WHERE table_schema = 'public' AND table_name = ?
             ORDER BY ordinal_position
        ");
        $st->execute(array($tabla));

        $mapa = array();
        foreach ($st->fetchAll() as $c) {
            $mapa[$c['column_name']] = $c['data_type'];
        }

        $cache[$tabla] = $mapa;
        return $mapa;
    }
}

if (!function_exists('bovs_import_columnas')) {
    /**
     * Columnas de una tabla en el destino (orden real de la tabla).
     *
     * @return array Lista de nombres de columna.
     */
    function bovs_import_columnas(PDO $pdo, $tabla)
    {
        return array_keys(bovs_import_columnas_info($pdo, $tabla));
    }
}

if (!function_exists('bovs_import_filas_demo')) {
    /**
     * Filas del fundo demo para una tabla, en orden de id.
     *
     * @return array
     */
    function bovs_import_filas_demo(PDO $pdoDemo, $tabla, $fundoDemo)
    {
        $fundoDemo = (int) $fundoDemo;

        if ($tabla === 'mmovimientos_rebano_detalle') {
            $sql = 'SELECT d.* FROM mmovimientos_rebano_detalle d
                     WHERE d.mmovimientos_rebano_id IN (SELECT id FROM mmovimientos_rebano WHERE mfundos_id = ?)
                     ORDER BY d.id';
        } else {
            $sql = 'SELECT t.* FROM "' . str_replace('"', '""', $tabla) . '" t
                     WHERE t.mfundos_id = ? ORDER BY t.id';
        }

        $st = $pdoDemo->prepare($sql);
        $st->execute(array($fundoDemo));
        return $st->fetchAll();
    }
}

if (!function_exists('bovs_importar_procesos')) {
    /**
     * Copia los grupos elegidos del fundo demo al fundo oficial.
     *
     * @param array $opciones
     *   fundo_demo (int), fundo_destino (int), usuario_destino (int),
     *   grupos (array de claves)
     * @return array{ok:bool,msg:string,detalle?:array,omitidos?:array}
     */
    function bovs_importar_procesos(array $opciones)
    {
        $fundoDemo      = (int) (isset($opciones['fundo_demo']) ? $opciones['fundo_demo'] : 0);
        $fundoDestino   = (int) (isset($opciones['fundo_destino']) ? $opciones['fundo_destino'] : 0);
        $usuarioDestino = (int) (isset($opciones['usuario_destino']) ? $opciones['usuario_destino'] : 0);
        $grupos         = isset($opciones['grupos']) && is_array($opciones['grupos']) ? $opciones['grupos'] : array();

        if ($fundoDemo <= 0 || $fundoDestino <= 0) {
            return array('ok' => false, 'msg' => 'Faltan datos de la cuenta demo o del fundo destino.');
        }

        $grupos = bovs_import_expandir($grupos);
        if (empty($grupos)) {
            return array('ok' => false, 'msg' => 'Selecciona al menos un proceso para importar.');
        }

        $mapaTablas = bovs_import_mapa_tablas();
        $orden      = bovs_import_orden();

        try {
            $pdoDemo = bovs_demo_pdo();
            $pdoOfi  = bovs_oficial_pdo();
        } catch (Throwable $e) {
            return array('ok' => false, 'msg' => 'No se pudo conectar a las bases de datos.');
        }

        $offsets   = array();
        $detalle   = array();
        $omitidos  = array();
        $omitidosNac = array();

        /* Códigos de ternero que ya existen en la cuenta oficial:
           mregnacimientos.codigo_ternero es ÚNICO en toda la base. */
        $ternerosExistentes = array();
        $st = $pdoOfi->query("SELECT upper(btrim(codigo_ternero)) FROM mregnacimientos WHERE coalesce(btrim(codigo_ternero),'') <> ''");
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $cod) {
            $ternerosExistentes[$cod] = true;
        }

        try {
            $pdoOfi->beginTransaction();

            foreach ($orden as $tabla) {
                if (!isset($mapaTablas[$tabla])) {
                    continue;
                }

                $info = $mapaTablas[$tabla];
                if (!in_array($info['grupo'], $grupos, true)) {
                    continue;
                }

                /* --- Offset de esta tabla en el destino ---------------- */
                $offset = (int) $pdoOfi->query('SELECT COALESCE(MAX(id), 0) FROM "' . $tabla . '"')->fetchColumn();
                $offsets[$tabla] = $offset;

                $columnasDestino = bovs_import_columnas($pdoOfi, $tabla);
                $tiposDestino    = bovs_import_columnas_info($pdoOfi, $tabla);
                if (empty($columnasDestino)) {
                    continue;
                }

                $filas = bovs_import_filas_demo($pdoDemo, $tabla, $fundoDemo);
                if (empty($filas)) {
                    $detalle[$tabla] = 0;
                    continue;
                }

                $remaps = $info['remaps'];
                $columnasInsert = array_values(array_intersect($columnasDestino, array_keys($filas[0])));
                $lista = implode(', ', array_map(function ($c) { return '"' . $c . '"'; }, $columnasInsert));
                $marcas = implode(', ', array_fill(0, count($columnasInsert), '?'));
                $sqlInsert = 'INSERT INTO "' . $tabla . '" (' . $lista . ') VALUES (' . $marcas . ')';
                $stInsert = $pdoOfi->prepare($sqlInsert);

                $insertadas = 0;

                foreach ($filas as $fila) {
                    /* Nacimiento con código de ternero ya usado: se omite
                       el parto y también sus crías. */
                    if ($tabla === 'mregnacimientos') {
                        $cod = strtoupper(trim((string) $fila['codigo_ternero']));
                        if ($cod !== '' && isset($ternerosExistentes[$cod])) {
                            $omitidosNac[(int) $fila['id']] = true;
                            $omitidos[] = 'Nacimiento con código de ternero ' . $cod . ' (ya existe en la cuenta oficial)';
                            continue;
                        }
                    }

                    if ($tabla === 'mternerosxnacimientos' && isset($omitidosNac[(int) $fila['mregnacimientos_id']])) {
                        continue;
                    }

                    $valores = array();

                    foreach ($columnasInsert as $col) {
                        $valor = isset($fila[$col]) ? $fila[$col] : null;

                        if ($col === 'id') {
                            $valor = (int) $valor + $offset;
                        } elseif ($col === 'mfundos_id') {
                            $valor = $fundoDestino;
                        } elseif (isset($remaps[$col])) {
                            $ref = $remaps[$col];
                            if ($valor !== null && (int) $valor > 0) {
                                $valor = (int) $valor + (isset($offsets[$ref]) ? $offsets[$ref] : 0);
                            }
                        } elseif ($col === 'uuid_local' && $valor !== null) {
                            $valor = bovs_import_uuid();
                        } elseif (($col === 'user_create' || $col === 'user_modify') && $valor !== null) {
                            $valor = $usuarioDestino > 0 ? $usuarioDestino : $valor;
                        }

                        /* PDO no acepta objetos DateTime: se pasan como texto. */
                        if ($valor instanceof DateTimeInterface) {
                            $valor = $valor->format('Y-m-d H:i:s');
                        }

                        /* Normalización de tipos: pdo_pgsql puede devolver
                           cadenas vacías para booleanos y numéricos, y al
                           enviar `false` manda "" (PostgreSQL lo rechaza).
                           Por eso los booleanos viajan como 't' / 'f'. */
                        $tipo = isset($tiposDestino[$col]) ? (string) $tiposDestino[$col] : '';

                        if ($tipo === 'boolean') {
                            if ($valor === null || $valor === '') {
                                $valor = 'f';
                            } else {
                                $valor = in_array(strtolower((string) $valor), array('t', 'true', '1', 'si', 'sí'), true) ? 't' : 'f';
                            }
                        } elseif ($valor === '' && in_array($tipo, array(
                            'smallint', 'integer', 'bigint', 'numeric', 'real', 'double precision',
                            'date', 'timestamp without time zone', 'timestamp with time zone', 'uuid'
                        ), true)) {
                            $valor = null;
                        }

                        $valores[] = $valor;
                    }

                    try {
                        $stInsert->execute($valores);
                        $insertadas++;
                    } catch (PDOException $e) {
                        /* Un duplicado no debe tumbar toda la importación:
                           se aparta la fila y se informa. */
                        if ((string) $e->getCode() === '23505') {
                            $omitidos[] = $tabla . ': ' . $e->getMessage();
                            continue;
                        }
                        throw $e;
                    }
                }

                $detalle[$tabla] = $insertadas;
            }

            /* --- Secuencias al día ------------------------------------ */
            $pdoOfi->exec(<<<'SQL'
DO $$
DECLARE
    r record;
    seq text;
BEGIN
    FOR r IN
        SELECT c.table_name, c.column_name, c.column_default
          FROM information_schema.columns c
         WHERE c.table_schema = 'public'
           AND c.column_default LIKE 'nextval(%'
    LOOP
        seq := regexp_replace(r.column_default, '^nextval\(''([^'']+)''.*$', '\1');
        IF seq <> '' AND to_regclass(seq) IS NOT NULL THEN
            EXECUTE format(
                'SELECT setval(%L, GREATEST(COALESCE((SELECT MAX(%I) FROM %I), 0), 1), COALESCE((SELECT MAX(%I) FROM %I), 0) > 0)',
                seq, r.column_name, r.table_name, r.column_name, r.table_name
            );
        END IF;
    END LOOP;
END $$;
SQL
            );

            $pdoOfi->commit();
        } catch (Throwable $e) {
            if ($pdoOfi->inTransaction()) {
                $pdoOfi->rollBack();
            }
            error_log('[SOVCA import demo] Error al importar: ' . $e->getMessage());
            return array('ok' => false, 'msg' => 'Error al importar los datos: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
        }

        $total = 0;
        foreach ($detalle as $n) {
            $total += (int) $n;
        }

        return array(
            'ok'       => true,
            'msg'      => 'Se importaron ' . $total . ' registro(s) desde la cuenta demo.',
            'total'    => $total,
            'detalle'  => $detalle,
            'omitidos' => $omitidos
        );
    }
}

/* ---------------------------------------------------------------------
   CIERRE / MARCA DE LA CUENTA DEMO
   --------------------------------------------------------------------- */

if (!function_exists('bovs_import_marcar_migrada')) {
    /**
     * Marca la cuenta demo como migrada y la deja cerrada: ya no puede
     * volver a usarse y el mismo correo no puede pedir otra demo.
     *
     * @param int    $demoUsuarioId
     * @param int    $destinoUsuarioId
     * @param int    $destinoFundoId
     * @param string $nota
     * @return bool
     */
    function bovs_import_marcar_migrada($demoUsuarioId, $destinoUsuarioId, $destinoFundoId, $nota = '')
    {
        $demoUsuarioId = (int) $demoUsuarioId;

        if ($demoUsuarioId <= 0) {
            return false;
        }

        try {
            $pdo = bovs_demo_pdo();
            bovs_demo_preparar($pdo);

            $st = $pdo->prepare('
                UPDATE demo_registros
                   SET migrado = true,
                       migrado_a_usuario = ?,
                       migrado_fundo_id = ?,
                       migrado_fecha = now(),
                       cerrada = true,
                       nota = ?
                 WHERE musuarios_id = ?
            ');
            $st->execute(array(
                (int) $destinoUsuarioId,
                (int) $destinoFundoId,
                mb_substr((string) $nota, 0, 300),
                $demoUsuarioId
            ));

            $pdo->prepare('UPDATE musuarios SET status = 0, updated = now() WHERE id = ?')->execute(array($demoUsuarioId));

            return true;
        } catch (Throwable $e) {
            error_log('[SOVCA import demo] No se pudo marcar la demo como migrada: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('bovs_import_cerrar_demo')) {
    /**
     * Cierra una cuenta demo SIN importar datos (el visitante decidió no
     * continuar). El correo queda bloqueado para nuevas demos.
     *
     * @return bool
     */
    function bovs_import_cerrar_demo($demoUsuarioId, $nota = '')
    {
        return bovs_import_marcar_migrada($demoUsuarioId, 0, 0, $nota);
    }
}
