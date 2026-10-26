<?php

/**
 * POST /api/auth_controller.php
 *
 * Acciones:
 *   - login                     { usuario, password, dispositivo?, dispositivo_id? }
 *   - validar                   (requiere header Authorization)
 *   - logout                    (requiere header Authorization)
 *
 *   - vincular_dispositivo      { dispositivo_id, nombre_dispositivo, modo_offline? }
 *                                modo_offline: 'gestion' (default) | 'consulta'
 *   - desvincular_dispositivo   { dispositivo_id, motivo? }
 *   - estado_dispositivo        { dispositivo_id }
 *   - listar_dispositivos       (requiere header Authorization)
 *   - cambiar_modo_dispositivo  { dispositivo_id, modo_offline }
 *                                Atajo para pasar de consulta a gestión
 *                                sin tener que desvincular/revincular.
 */

require_once __DIR__ . '/config/bootstrap.php';
require_once '../models/Vusuario.php';
require_once '../models/Mfundo.php';
require_once '../models/UsuarioDispositivoOffline.php';

$input = leer_json_input();
$action = $input['action'] ?? null;

switch ($action) {

    case 'login':
        $usuario = trim($input['usuario'] ?? '');
        $password = (string) ($input['password'] ?? '');
        $dispositivo = $input['dispositivo'] ?? null;
        $dispositivoId = $input['dispositivo_id'] ?? null;

        if ($usuario === '' || $password === '') {
            \Api\ApiResponse::error('Debe indicar usuario y contraseña.', 422);
        }

        /* ------------------------------------------------------------
           CAPTCHA
           ------------------------------------------------------------
           El formulario web de instalación offline (views/login.php)
           envía la operación de seguridad resuelta por el usuario.
           La app offline usa este mismo endpoint para su propio login y
           no dispone de captcha, así que solo se exige cuando la
           petición viene de ese formulario.
           ------------------------------------------------------------ */
        if (($input['dispositivo'] ?? null) === 'web-offline-install') {
            require_once __DIR__ . '/../lib/captcha_helper.php';

            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }

            $captchaToken     = (string) ($input['captcha_token'] ?? '');
            $captchaRespuesta = trim((string) ($input['captcha_respuesta'] ?? ''));

            if (!bovs_captcha_validar($captchaToken, $captchaRespuesta, 'captcha_offline')) {
                \Api\ApiResponse::error(
                    'La verificación de seguridad no coincide. Resuelve la operación nueva.',
                    422,
                    array('codigo' => 'captcha', 'captcha' => true)
                );
            }
        }

        $salt = '$DYhG93b0qyJfIxfs2guVoUubWwvniR2G0FgaC9mi$/';
        $passwordHash = md5($salt . $password);

        // Se busca en 'musuarios' (y no en la vista 'vusuarios') a
        // propósito: con multifundo la vista devuelve UNA FILA POR
        // FUNDO del usuario, así que 'find(first)' podía devolver un
        // fundo cualquiera. Los fundos se resuelven aparte, todos, con
        // ApiAuth::fundosDeUsuario().
        $registro = Musuario::find('first', [
            'conditions' => [
                'username = ? and password = ? and status = 1',
                strtoupper($usuario),
                $passwordHash,
            ],
        ]);

        if (!$registro) {
            \Api\ApiResponse::error('Usuario o contraseña incorrectos.', 401);
        }

        $usuarioId = (int) $registro->id;

        // MODO MANTENIMIENTO: solo los perfiles 1 (Administrador) y 4
        // (Super Usuario) pueden autenticarse mientras el sistema está
        // cerrado. La PWA recibe 403 con codigo 'mantenimiento'.
        \Api\ApiAuth::exigirMantenimientoPermitido($usuarioId);

        // ¿El administrador habilitó el modo offline para esta cuenta?
        \Api\ApiAuth::exigirAccesoOffline($usuarioId);

        $fundos = \Api\ApiAuth::fundosDeUsuario($usuarioId);

        if (!$fundos) {
            \Api\ApiResponse::error(
                'Esta cuenta no tiene fundos activos asignados, así que no puede usar la app offline.',
                403,
                ['codigo' => 'sin_fundos']
            );
        }

        $principalId = _fundoPrincipalValido($usuarioId, $fundos);
        $principal = _fundoDeLista($fundos, $principalId);

        // Token DEL USUARIO (multifundo): cada petición indica con qué
        // fundo trabaja y el servidor lo valida. Así un productor con
        // varios fundos no necesita un token por fundo, y el dispositivo
        // puede alternar entre ellos sin volver a iniciar sesión.
        $tokenData = \Api\ApiAuth::crearToken($usuarioId, null, $dispositivo);

        $estadoVinculo = null;
        if ($dispositivoId && _esUuidValido($dispositivoId)) {
            $estadoVinculo = _estadoVinculoMulti(
                $usuarioId,
                array_map(fn($f) => (int) $f['id'], $fundos),
                $dispositivoId
            );
        }

        \Api\ApiResponse::ok([
            'token' => $tokenData['token'],
            'expira' => $tokenData['expira'],
            'usuario' => [
                'id' => $usuarioId,
                'nombre' => $registro->name,
                'username' => $registro->username,
                'rol_id' => (int) $registro->mroles_id,
                'es_multifundo' => (bool) $registro->es_multifundo || count($fundos) > 1,
                'max_fundos' => (int) $registro->max_fundos,
                // Compatibilidad: el "fundo principal" sigue viniendo en
                // las mismas claves de siempre. El tipo de ganadería es
                // del FUNDO (no del usuario).
                'fundo_id' => (int) $principal['id'],
                'fundo_nombre' => $principal['nombre'],
                'tipo_ganaderia_id' => (int) $principal['tipo_ganaderia_id'],
                // Nuevo: todos los fundos con acceso activo.
                'fundos' => $fundos,
            ],
            'estado_vinculo' => $estadoVinculo,
        ]);
        break;

    case 'validar':
        $auth = \Api\ApiAuth::requerirAutenticacion($input);
        $registro = Musuario::find($auth['usuario_id']);
        if (!$registro || (int) $registro->status !== 1) {
            \Api\ApiResponse::error('Usuario no encontrado o inactivo.', 404);
        }

        $usuarioId = (int) $registro->id;

        // Mismo control que en el login: si el administrador le quitó el
        // acceso, la app deja de poder validar la sesión offline.
        \Api\ApiAuth::exigirAccesoOffline($usuarioId);

        $fundos = \Api\ApiAuth::fundosDeUsuario($usuarioId);

        if (!$fundos) {
            \Api\ApiResponse::error(
                'Esta cuenta no tiene fundos activos asignados.',
                403,
                ['codigo' => 'sin_fundos']
            );
        }

        // Con token multifundo (fundo_id = 0) el "fundo actual" es el
        // principal del usuario; el cliente manda el suyo en cada
        // petición de sync.
        $principalId = (int) $auth['fundo_id'] > 0
            ? (int) $auth['fundo_id']
            : _fundoPrincipalValido($usuarioId, $fundos);
        $principal = _fundoDeLista($fundos, $principalId);

        \Api\ApiResponse::ok([
            'usuario' => [
                'id' => $usuarioId,
                'nombre' => $registro->name,
                'username' => $registro->username,
                'rol_id' => (int) $registro->mroles_id,
                'es_multifundo' => (bool) $registro->es_multifundo || count($fundos) > 1,
                'max_fundos' => (int) $registro->max_fundos,
                'fundo_id' => (int) $principal['id'],
                'fundo_nombre' => $principal['nombre'],
                'tipo_ganaderia_id' => (int) $principal['tipo_ganaderia_id'],
                'fundos' => $fundos,
            ],
            'token_id' => $auth['token_id'],
            'fundo_id' => (int) $principal['id'],
            'fundos' => $fundos,
        ]);
        break;

    case 'datos_fundo':
        $auth = \Api\ApiAuth::requerirAutenticacion($input);

        // Antes solo se comprobaba que el fundo EXISTIERA (cualquiera
        // podía leer el nombre de un fundo ajeno). Ahora el fundo
        // pedido se valida contra los fundos con acceso activo del
        // usuario; si no se manda ninguno, se usa el efectivo.
        $fundoId = \Api\ApiAuth::requerirFundo($input, $auth);

        $fundo = Mfundo::find('first', ['conditions' => ['id = ?', $fundoId]]);
        if (!$fundo) {
            \Api\ApiResponse::error('No se encontró el fundo indicado.', 404);
        }

        \Api\ApiResponse::ok([
            'fundo' => [
                'id' => (int) $fundo->id,
                'nombre' => trim((string) $fundo->nombre),
                'tipo_ganaderia_id' => (int) $fundo->mtipoganaderias_id,
            ],
            'fundo_id' => (int) $fundo->id,
        ]);
        break;

    case 'logout':
        $usuario = \Api\ApiAuth::requerirAutenticacion($input);

        $registro = ApiToken::find($usuario['token_id']);
        $registro->status = 0;
        $registro->save();

        \Api\ApiResponse::ok(['mensaje' => 'Sesión cerrada.']);
        break;

    // =================================================================
    // Vínculo offline usuario<->dispositivo
    // =================================================================

    case 'vincular_dispositivo':
        $auth = \Api\ApiAuth::requerirAutenticacion($input);
        $dispositivoId = $input['dispositivo_id'] ?? null;
        $nombreDispositivo = trim((string) ($input['nombre_dispositivo'] ?? ''));
        $modoOffline = trim((string) ($input['modo_offline'] ?? 'gestion'));

        if (!$dispositivoId || !_esUuidValido($dispositivoId)) {
            \Api\ApiResponse::error('Falta dispositivo_id o su formato es inválido (se espera UUID v4).', 422);
        }
        if (!in_array($modoOffline, ['gestion', 'consulta'], true)) {
            \Api\ApiResponse::error('El modo_offline debe ser "gestion" o "consulta".', 422);
        }
        if ($nombreDispositivo === '') {
            $nombreDispositivo = 'Dispositivo sin nombre';
        }
        if (mb_strlen($nombreDispositivo) > 120) {
            $nombreDispositivo = mb_substr($nombreDispositivo, 0, 120);
        }

        $usuarioId = $auth['usuario_id'];

        // Con un token viejo ya emitido, alguien podría intentar activar
        // el modo offline después de que le quitaron el permiso.
        \Api\ApiAuth::exigirAccesoOffline((int) $usuarioId);

        // Qué fundos se activan en ESTE dispositivo. El cliente
        // multifundo manda la lista que eligió la persona; si no manda
        // nada (cliente viejo), se vincula el fundo efectivo de la
        // petición, como se hacía antes.
        $permitidos = \Api\ApiAuth::idsFundosDeUsuario($usuarioId);
        $pedidos = $input['fundos'] ?? null;

        if (is_array($pedidos) && count($pedidos) > 0) {
            $aVincular = [];
            foreach ($pedidos as $fid) {
                $fid = (int) $fid;
                if ($fid <= 0 || in_array($fid, $aVincular, true)) {
                    continue;
                }
                if (!in_array($fid, $permitidos, true)) {
                    \Api\ApiResponse::error(
                        'Uno de los fundos indicados no está entre los que tenés acceso activo.',
                        403,
                        ['codigo' => 'fundo_no_permitido', 'fundo_id' => $fid]
                    );
                }
                $aVincular[] = $fid;
            }
            if (!$aVincular) {
                \Api\ApiResponse::error('La lista de fundos llegó vacía o inválida.', 422);
            }
        } else {
            $aVincular = [\Api\ApiAuth::requerirFundo($input, $auth)];
        }

        // Si es modo 'gestion', validar que no haya OTRO dispositivo de
        // gestión activo. Los de consulta no bloquean. La regla sigue
        // siendo por (usuario, fundo): el mismo teléfono suele ser el de
        // gestión de todos los fundos de la persona.
        if ($modoOffline === 'gestion') {
            foreach ($aVincular as $fid) {
                $otroGestion = UsuarioDispositivoOffline::find('first', [
                    'conditions' => [
                        'musuarios_id = ? and mfundos_id = ? and status = 1 and modo_offline = ? and dispositivo_id != ?',
                        $usuarioId, $fid, 'gestion', $dispositivoId,
                    ],
                ]);

                if ($otroGestion) {
                    \Api\ApiResponse::error(
                        'Este usuario ya tiene un dispositivo con el modo GESTIÓN activo en uno de los fundos elegidos. '
                        . 'Desvinculá ese dispositivo antes de activar gestión en este.',
                        409,
                        [
                            'codigo' => 'dispositivo_gestion_ya_vinculado',
                            'fundo_id' => $fid,
                            'otro_dispositivo' => [
                                'nombre' => $otroGestion->nombre_dispositivo,
                                'modo_offline' => $otroGestion->modo_offline,
                                'activado_en' => $otroGestion->activado_en,
                                'ultima_sincronizacion' => $otroGestion->ultima_sincronizacion,
                            ],
                        ]
                    );
                }
            }
        }

        $resultados = [];

        foreach ($aVincular as $fundoId) {
            // Buscar si ESTE dispositivo ya tiene un registro para ese
            // fundo (activo o histórico).
            $existente = UsuarioDispositivoOffline::find('first', [
                'conditions' => [
                    'musuarios_id = ? and mfundos_id = ? and dispositivo_id = ?',
                    $usuarioId, $fundoId, $dispositivoId,
                ],
                'order' => 'id DESC',
            ]);

            try {
                if ($existente) {
                    // Reactivar/actualizar el registro existente. Esto cubre:
                    //   - Revinculación tras "desvincular".
                    //   - Cambio de modo (consulta <-> gestion).
                    //   - Renovación de metadata.
                    $existente->status = 1;
                    $existente->modo_offline = $modoOffline;
                    $existente->nombre_dispositivo = $nombreDispositivo;
                    $existente->activado_en = date('Y-m-d H:i:s');
                    $existente->desvinculado_en = null;
                    $existente->desvinculado_por = null;
                    $existente->motivo_desvinculacion = null;
                    $existente->save();

                    $resultados[] = [
                        'fundo_id' => $fundoId,
                        'vinculado' => true,
                        'revinculado' => true,
                        'modo_offline' => $modoOffline,
                    ];
                    continue;
                }

                $nuevo = new UsuarioDispositivoOffline();
                $nuevo->musuarios_id = $usuarioId;
                $nuevo->mfundos_id = $fundoId;
                $nuevo->dispositivo_id = $dispositivoId;
                $nuevo->nombre_dispositivo = $nombreDispositivo;
                $nuevo->modo_offline = $modoOffline;
                $nuevo->activado_en = date('Y-m-d H:i:s');
                $nuevo->user_create = $usuarioId;
                $nuevo->status = 1;

                if (!$nuevo->save()) {
                    \Api\ApiResponse::error(
                        'No se pudo registrar el vínculo del dispositivo en el fundo ' . $fundoId . '.',
                        500
                    );
                }

                $resultados[] = [
                    'fundo_id' => $fundoId,
                    'vinculado' => true,
                    'creado' => true,
                    'modo_offline' => $modoOffline,
                ];

            } catch (\Throwable $e) {
                // Race condition: dos requests simultáneos intentan crear
                // un dispositivo de gestión en el mismo fundo. El índice
                // único parcial atrapa al segundo. Devolvemos el mismo
                // error que si lo hubiéramos detectado antes.
                $msg = $e->getMessage();
                if (strpos($msg, 'usuario_dispositivo_offline_unico_activo_gestion_idx') !== false) {
                    \Api\ApiResponse::error(
                        'Este usuario ya tiene un dispositivo con el modo GESTIÓN activo en ese fundo.',
                        409,
                        ['codigo' => 'dispositivo_gestion_ya_vinculado', 'fundo_id' => $fundoId]
                    );
                }
                throw $e;
            }
        }

        \Api\ApiResponse::ok([
            'vinculado' => true,
            'modo_offline' => $modoOffline,
            'dispositivo_id' => $dispositivoId,
            'fundo_id' => $aVincular[0], // compatibilidad con el cliente de un solo fundo
            'fundos' => $resultados,
        ]);
        break;

    case 'cambiar_modo_dispositivo':
        $auth = \Api\ApiAuth::requerirAutenticacion($input);
        $dispositivoId = $input['dispositivo_id'] ?? null;
        $modoNuevo = trim((string) ($input['modo_offline'] ?? ''));

        if (!$dispositivoId || !_esUuidValido($dispositivoId)) {
            \Api\ApiResponse::error('Falta dispositivo_id o su formato es inválido.', 422);
        }
        if (!in_array($modoNuevo, ['gestion', 'consulta'], true)) {
            \Api\ApiResponse::error('El modo_offline debe ser "gestion" o "consulta".', 422);
        }

        $usuarioId = $auth['usuario_id'];

        // El dispositivo puede estar vinculado a VARIOS fundos del mismo
        // usuario (multifundo). El cambio de modo se aplica a todas sus
        // filas activas: si no, quedaría un fundo en gestión y otro en
        // consulta dentro del mismo teléfono.
        $filas = UsuarioDispositivoOffline::find('all', [
            'conditions' => [
                'dispositivo_id = ? and status = 1',
                $dispositivoId,
            ],
            'order' => 'id ASC',
        ]);
        if (!$filas) {
            \Api\ApiResponse::error('No hay un dispositivo activo con ese identificador.', 404);
        }

        $rol = \Api\ApiAuth::rolDeUsuario($usuarioId);
        $propias = [];
        foreach ($filas as $fila) {
            $esPropia = ((int) $fila->musuarios_id === (int) $usuarioId);
            $puedeAdmin = in_array($rol, [1, 4], true)
                || (in_array($rol, [2, 3], true)
                    && in_array((int) $fila->mfundos_id, \Api\ApiAuth::idsFundosDeUsuario($usuarioId), true));

            if ($esPropia || $puedeAdmin) {
                $propias[] = $fila;
            }
        }

        if (!$propias) {
            \Api\ApiResponse::error('No tienes permisos para cambiar el modo de ese dispositivo.', 403);
        }

        // Si pasa a gestión, verificar que no haya otro de gestión en
        // cada (usuario, fundo) afectado.
        if ($modoNuevo === 'gestion') {
            foreach ($propias as $fila) {
                $otroGestion = UsuarioDispositivoOffline::find('first', [
                    'conditions' => [
                        'musuarios_id = ? and mfundos_id = ? and status = 1 and modo_offline = ? and id != ?',
                        (int) $fila->musuarios_id, (int) $fila->mfundos_id, 'gestion', (int) $fila->id,
                    ],
                ]);
                if ($otroGestion) {
                    \Api\ApiResponse::error(
                        'Este usuario ya tiene otro dispositivo con el modo GESTIÓN activo en el fundo ' . (int) $fila->mfundos_id . '.',
                        409,
                        [
                            'codigo' => 'dispositivo_gestion_ya_vinculado',
                            'fundo_id' => (int) $fila->mfundos_id,
                            'otro_dispositivo' => [
                                'nombre' => $otroGestion->nombre_dispositivo,
                                'ultima_sincronizacion' => $otroGestion->ultima_sincronizacion,
                            ],
                        ]
                    );
                }
            }
        }

        $fundosAfectados = [];
        foreach ($propias as $fila) {
            $fila->modo_offline = $modoNuevo;
            $fila->save();
            $fundosAfectados[] = (int) $fila->mfundos_id;
        }

        \Api\ApiResponse::ok([
            'cambiado' => true,
            'modo_offline' => $modoNuevo,
            'dispositivo_id' => $dispositivoId,
            'fundo_id' => $fundosAfectados[0] ?? 0, // compatibilidad
            'fundos' => $fundosAfectados,
        ]);
        break;

    case 'desvincular_dispositivo':
        $auth = \Api\ApiAuth::requerirAutenticacion($input);
        $dispositivoIdObjetivo = $input['dispositivo_id'] ?? null;
        if (!$dispositivoIdObjetivo) {
            $dispositivoIdObjetivo = $input['dispositivo_id_propio'] ?? null;
        }
        if (!$dispositivoIdObjetivo || !_esUuidValido($dispositivoIdObjetivo)) {
            \Api\ApiResponse::error('Falta dispositivo_id o su formato es inválido.', 422);
        }

        $motivo = trim((string) ($input['motivo'] ?? ''));

        // Todas las filas activas de ESE dispositivo (uno por fundo si el
        // usuario es multifundo). Se desvinculan las que el llamador
        // puede administrar: las propias, o las de los fundos a los que
        // tiene acceso si es administrador/productor.
        $filas = UsuarioDispositivoOffline::find('all', [
            'conditions' => [
                'dispositivo_id = ? and status = 1',
                $dispositivoIdObjetivo,
            ],
            'order' => 'id ASC',
        ]);
        if (!$filas) {
            \Api\ApiResponse::error('No hay un dispositivo activo con ese identificador.', 404);
        }

        $rol = \Api\ApiAuth::rolDeUsuario($auth['usuario_id']);
        $fundosPropios = \Api\ApiAuth::idsFundosDeUsuario($auth['usuario_id']);

        $objetivos = [];
        $esPropio = false;
        foreach ($filas as $fila) {
            if ((int) $fila->musuarios_id === (int) $auth['usuario_id']) {
                $objetivos[] = $fila;
                $esPropio = true;
                continue;
            }
            $puedeAdmin = in_array($rol, [1, 4], true)
                || (in_array($rol, [2], true) && in_array((int) $fila->mfundos_id, $fundosPropios, true));
            if ($puedeAdmin) {
                $objetivos[] = $fila;
            }
        }

        if (!$objetivos) {
            \Api\ApiResponse::error('No tienes permisos para desvincular ese dispositivo.', 403);
        }

        $fundosDesvinculados = [];
        foreach ($objetivos as $fila) {
            $fila->status = 0;
            $fila->desvinculado_en = date('Y-m-d H:i:s');
            $fila->desvinculado_por = $auth['usuario_id'];
            $fila->motivo_desvinculacion = $motivo !== '' ? mb_substr($motivo, 0, 200) : null;
            $fila->save();
            $fundosDesvinculados[] = (int) $fila->mfundos_id;
        }

        \Api\ApiResponse::ok([
            'desvinculado' => true,
            'dispositivo_id' => $dispositivoIdObjetivo,
            'fue_propio' => $esPropio,
            'fundo_id' => $fundosDesvinculados[0] ?? 0, // compatibilidad
            'fundos' => $fundosDesvinculados,
        ]);
        break;

    case 'estado_dispositivo':
        $auth = \Api\ApiAuth::requerirAutenticacion($input);
        $dispositivoId = $input['dispositivo_id'] ?? null;

        if (!$dispositivoId || !_esUuidValido($dispositivoId)) {
            \Api\ApiResponse::error('Falta dispositivo_id o su formato es inválido.', 422);
        }

        $estado = _estadoVinculoMulti(
            $auth['usuario_id'],
            \Api\ApiAuth::idsFundosDeUsuario($auth['usuario_id']),
            $dispositivoId
        );

        \Api\ApiResponse::ok($estado);
        break;

    case 'listar_dispositivos':
        $auth = \Api\ApiAuth::requerirAutenticacion($input);

        $usuarioId = (int) $auth['usuario_id'];
        $rol = \Api\ApiAuth::rolDeUsuario($usuarioId);
        $esAdmin = in_array($rol, [1, 2, 4], true);
        $fundosPropios = \Api\ApiAuth::idsFundosDeUsuario($usuarioId);

        // Se listan las filas de todos los fundos del usuario (antes solo
        // las del fundo del token): en multifundo el mismo equipo queda
        // vinculado a varios fundos y conviene verlos juntos.
        $listaFundos = $fundosPropios ? implode(',', array_map('intval', $fundosPropios)) : '0';
        $valores = [$usuarioId]; // AR exige variable (pasa $values por referencia)
        $filas = Musuario::connection()->query(
            "SELECT * FROM usuario_dispositivo_offline
              WHERE musuarios_id = ? OR mfundos_id IN ($listaFundos)
           ORDER BY dispositivo_id ASC, status DESC, mfundos_id ASC",
            $valores
        )->fetchAll();

        // Nombre de los fundos, para no mostrar ids sueltos en la UI.
        $nombresFundos = [];
        foreach (\Api\ApiAuth::fundosDeUsuario($usuarioId) as $f) {
            $nombresFundos[(int) $f['id']] = $f['nombre'];
        }

        $salida = [];       // lista plana (compatible con el cliente anterior)
        $porDispositivo = []; // agrupada por equipo (multifundo)

        foreach ($filas as $d) {
            $usuario = Musuario::find((int) $d['musuarios_id']);
            $fila = [
                'id' => (int) $d['id'],
                'dispositivo_id' => (string) $d['dispositivo_id'],
                'nombre_dispositivo' => $d['nombre_dispositivo'],
                'modo_offline' => $d['modo_offline'] ?: 'gestion',
                'musuarios_id' => (int) $d['musuarios_id'],
                'usuario_nombre' => $usuario ? $usuario->name : '',
                'usuario_username' => $usuario ? $usuario->username : '',
                'activo' => ((int) $d['status'] === 1),
                'activado_en' => $d['activado_en'],
                'ultima_sincronizacion' => $d['ultima_sincronizacion'],
                'desvinculado_en' => $d['desvinculado_en'],
                'motivo_desvinculacion' => $d['motivo_desvinculacion'],
                'fundo_id' => (int) $d['mfundos_id'],
                'fundo_nombre' => $nombresFundos[(int) $d['mfundos_id']] ?? '',
            ];
            $salida[] = $fila;

            $clave = (string) $d['dispositivo_id'];
            if (!isset($porDispositivo[$clave])) {
                $porDispositivo[$clave] = [
                    'dispositivo_id' => $clave,
                    'nombre_dispositivo' => $d['nombre_dispositivo'],
                    'musuarios_id' => (int) $d['musuarios_id'],
                    'usuario_nombre' => $fila['usuario_nombre'],
                    'usuario_username' => $fila['usuario_username'],
                    'activo' => false,
                    'fundos' => [],
                ];
            }
            $porDispositivo[$clave]['activo'] = $porDispositivo[$clave]['activo'] || $fila['activo'];
            $porDispositivo[$clave]['fundos'][] = [
                'fundo_id' => $fila['fundo_id'],
                'fundo_nombre' => $fila['fundo_nombre'],
                'modo_offline' => $fila['modo_offline'],
                'activo' => $fila['activo'],
                'registro_id' => $fila['id'],
                'activado_en' => $fila['activado_en'],
                'ultima_sincronizacion' => $fila['ultima_sincronizacion'],
                'desvinculado_en' => $fila['desvinculado_en'],
                'motivo_desvinculacion' => $fila['motivo_desvinculacion'],
            ];
        }

        \Api\ApiResponse::ok([
            'dispositivos' => $salida,
            'por_dispositivo' => array_values($porDispositivo),
            'es_admin' => $esAdmin,
            'fundo_id' => (int) ($auth['fundo_id'] ?? 0),
        ]);
        break;

    default:
        \Api\ApiResponse::error('Acción no reconocida.', 404);
}

// =====================================================================
// Helpers locales de este controlador
// =====================================================================

/**
 * Fundo "principal" del usuario, validado contra la lista de fundos a
 * los que realmente tiene acceso. Si el último fundo guardado ya no
 * sirve (lo perdió, está inactivo), se usa el primero de la lista.
 */
function _fundoPrincipalValido(int $usuarioId, array $fundos): int
{
    $principal = \Api\ApiAuth::fundoPrincipal($usuarioId);

    foreach ($fundos as $f) {
        if ((int) $f['id'] === $principal) {
            return $principal;
        }
    }

    return (int) $fundos[0]['id'];
}

/** Devuelve la fila del fundo indicado dentro de la lista (o el primero). */
function _fundoDeLista(array $fundos, int $fundoId): array
{
    foreach ($fundos as $f) {
        if ((int) $f['id'] === $fundoId) {
            return $f;
        }
    }

    return $fundos[0];
}

/**
 * Arma el estado del vínculo para (usuario, VARIOS fundos, dispositivo).
 * Es la versión multifundo de _estadoVinculo(): el mismo equipo puede
 * estar vinculado a algunos de los fundos del usuario y a otros no.
 *
 * Devuelve:
 *   - vinculado: true solo si ESTE dispositivo está activo en TODOS los
 *     fundos consultados (es lo que el cliente necesita saber para
 *     ofrecer "activar modo offline" de nuevo).
 *   - motivo: 'es_este' | 'otro_gestion' | 'ninguno'
 *   - fundos: [{id, vinculado, modo_offline, ultima_sincronizacion}]
 *   - otro_dispositivo: info si hay otro equipo de gestión activo.
 */
function _estadoVinculoMulti(int $usuarioId, array $fundoIds, string $dispositivoId): array
{
    $fundoIds = array_values(array_unique(array_filter(array_map('intval', $fundoIds))));
    if (!$fundoIds) {
        return ['vinculado' => false, 'motivo' => 'ninguno', 'fundos' => []];
    }

    $filas = UsuarioDispositivoOffline::find('all', [
        'conditions' => [
            'musuarios_id = ? and dispositivo_id = ? and status = 1',
            $usuarioId, $dispositivoId,
        ],
    ]);

    $vinculados = [];
    foreach ($filas as $f) {
        $vinculados[(int) $f->mfundos_id] = $f;
    }

    $fundos = [];
    $todos = true;
    foreach ($fundoIds as $fid) {
        $esEste = isset($vinculados[$fid]);
        if (!$esEste) {
            $todos = false;
        }
        $fundos[] = [
            'id' => $fid,
            'vinculado' => $esEste,
            'modo_offline' => $esEste ? ($vinculados[$fid]->modo_offline ?: 'gestion') : null,
            'ultima_sincronizacion' => $esEste ? $vinculados[$fid]->ultima_sincronizacion : null,
        ];
    }

    if ($todos) {
        return [
            'vinculado' => true,
            'motivo' => 'es_este',
            'modo_offline' => $fundos[0]['modo_offline'],
            'ultima_sincronizacion' => $fundos[0]['ultima_sincronizacion'],
            'fundos' => $fundos,
        ];
    }

    // ¿Hay un equipo de gestión activo en alguno de esos fundos?
    $gestion = UsuarioDispositivoOffline::find('first', [
        'conditions' => [
            'musuarios_id = ? and mfundos_id = ? and status = 1 and modo_offline = ?',
            $usuarioId, $fundoIds[0], 'gestion',
        ],
    ]);

    $salida = [
        'vinculado' => false,
        'motivo' => $gestion ? 'otro_gestion' : 'ninguno',
        'fundos' => $fundos,
    ];

    if ($gestion) {
        $salida['otro_dispositivo'] = [
            'nombre' => $gestion->nombre_dispositivo,
            'modo_offline' => $gestion->modo_offline,
            'activado_en' => $gestion->activado_en,
            'ultima_sincronizacion' => $gestion->ultima_sincronizacion,
            'fundo_id' => (int) $gestion->mfundos_id,
        ];
    }

    return $salida;
}

/**
 * Compatibilidad: estado del vínculo para UN fundo (la usa el login
 * cuando el cliente manda un solo fundo).
 */
function _estadoVinculo(int $usuarioId, int $fundoId, string $dispositivoId): array
{
    return _estadoVinculoMulti($usuarioId, [$fundoId], $dispositivoId);
}

/** UUID v4: 8-4-4-4-12 hex, con variante y versión correctas. */
function _esUuidValido(string $uuid): bool
{
    return (bool) preg_match(
        '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
        $uuid
    );
}