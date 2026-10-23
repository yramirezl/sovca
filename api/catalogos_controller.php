<?php

/**
 * POST /api/catalogos_controller.php
 *
 * Acciones (requieren header Authorization):
 *
 *   - listar
 *       Devuelve de una vez los catálogos que necesita el formulario
 *       de registro de animales: razas, categorias, aptitudes y lotes.
 *       Son tablas pequeñas, así que se manda el listado completo en
 *       cada llamada (no hay "pull" incremental como en sync — no
 *       hace falta, y así el cliente nunca se queda con datos a medio
 *       actualizar).
 */

require_once __DIR__ . '/config/bootstrap.php';
require_once '../models/Vusuario.php';
require_once '../models/Mfundo.php';
require_once '../models/Maptitude.php';
require_once '../models/Mlote.php';
require_once '../models/Mpotrero.php';
require_once '../models/Mraza.php';
require_once '../models/Mcategoria.php';
require_once '../models/Mtiposervicio.php';
require_once '../models/Mestadofisiologico.php';
require_once '../models/ManimalSync.php';
require_once '../models/Mcategoriaproducto.php';
require_once '../models/Mproducto.php';
require_once '../models/Mperiodo.php';
require_once '../models/Mcausadescarte.php';
require_once '../models/Mcausamortalidade.php';

$input = leer_json_input();
$action = $input['action'] ?? null;
$usuario = \Api\ApiAuth::requerirAutenticacion($input);

// MULTIFUNDO: catálogos, lotes, razas, períodos y validación de aretes
// son del FUNDO con el que se está trabajando, no del "fundo del
// token". Se resuelve y valida una sola vez aquí para que el resto del
// archivo use $usuario['fundo_id'] como siempre.
$usuario['fundo_id'] = \Api\ApiAuth::requerirFundo($input, $usuario);

switch ($action) {

    case 'validar_arete':
        $nroArete = trim((string) ($input['nro_arete'] ?? ''));
        if ($nroArete === '') {
            \Api\ApiResponse::error('Debe indicar un número de arete.', 422);
        }

        $condicion = 'mfundos_id = ? and status = 1 and UPPER(TRIM(nro_arete)) = ?';
        $valores = [(int) $usuario['fundo_id'], strtoupper($nroArete)];
        $uuidExcluir = trim((string) ($input['uuid_local_excluir'] ?? ''));
        if ($uuidExcluir !== '') {
            if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuidExcluir)) {
                \Api\ApiResponse::error('El animal a excluir no tiene un UUID válido.', 422);
            }
            $condicion .= ' and uuid_local <> ?';
            $valores[] = $uuidExcluir;
        }

        $existente = ManimalSync::find('first', [
            'conditions' => array_merge([$condicion], $valores),
        ]);

        \Api\ApiResponse::ok([
            'duplicado' => (bool) $existente,
            'animal' => $existente ? [
                'uuid_local' => (string) $existente->uuid_local,
                'codigo_rgb_id_animal' => (string) $existente->codigo_rgb_id_animal,
                'nombre' => (string) $existente->nombre,
                'nro_arete' => (string) $existente->nro_arete,
            ] : null,
        ]);
        break;

    case 'listar':
        // El tipo de ganadería (ovina/caprina) es una propiedad del
        // FUNDO, no del usuario — un mismo usuario puede estar
        // asociado a varios fundos (mfundosxusuarios), así que hay
        // que sacarlo del fundo activo de esta sesión
        // ($usuario['fundo_id']), nunca de un campo fijo en el
        // usuario. Antes se leía Vusuario->mtipoganaderias_id, que
        // podía no reflejar el fundo con el que se está trabajando
        // ahora mismo — de ahí que aparecieran razas/categorías que
        // no correspondían a este fundo.
        $fundoActivo = Mfundo::find($usuario['fundo_id']);
        $tipoGanaderia = (int) ($fundoActivo->mtipoganaderias_id ?? 0);

        $aptitudes = Maptitude::find('all', [
            'conditions' => ['status = ?', 1],
            'order' => 'nombre asc',
        ]);

        $lotes = Mlote::find('all', [
            'conditions' => ['mfundos_id = ? and status > 0', $usuario['fundo_id']],
            'order' => 'nombre asc',
        ]);

        // Razas globales (mfundos_id = 0) + razas propias del fundo,
        // filtradas por tipo de ganadería — igual que hoy en la web.
        $razas = Mraza::find('all', [
            'conditions' => [
                'mtipoganaderias_id = ? and (mfundos_id = ? or mfundos_id = 0) and status > 0',
                $tipoGanaderia, $usuario['fundo_id'],
            ],
            'order' => 'nombre asc',
        ]);

        // Categorías (Becerro, Novillo, Vaca, etc. por sexo/edad):
        // mismo criterio exacto que razas — globales (mfundos_id = 0)
        // + propias del fundo, filtradas por tipo de ganadería.
        $categorias = Mcategoria::find('all', [
            'conditions' => [
                'mtipoganaderias_id = ? and (mfundos_id = ? or mfundos_id = 0) and status > 0',
                $tipoGanaderia, $usuario['fundo_id'],
            ],
            'order' => 'nombre asc',
        ]);

        // Tipos de servicio (monta natural, IA, etc.) — catálogo
        // global, igual criterio que aptitudes.
        $tiposServicio = Mtiposervicio::find('all', [
            'conditions' => ['status = ?', 1],
            'order' => 'nombre asc',
        ]);

        // Estados fisiológicos (preñada, vacía, etc.) — necesarios para
        // la pantalla de palpación/parto de Servicios. Mismo criterio
        // de "status = 1" que usa hoy mestadofisiologicos_controller.php.
        $estadosFisiologicos = Mestadofisiologico::find('all', [
            'conditions' => ['status = ?', 1],
            'order' => 'nombre asc',
        ]);

        // Categorías de productos (vacuna, desparasitante, etc.): solo
        // las que tienen al menos un producto activo del fundo o global.
        // Mismo criterio que get_categoria_con_productos en
        // mproductos_controller.php: así el dispositivo nunca muestra
        // categorías vacías en el control sanitario.
        $productosControl = Mproducto::find('all', [
            'conditions' => [
                'status = ? and (mfundos_id = ? or mfundos_id = 0)',
                1, $usuario['fundo_id'],
            ],
            'order' => 'nombre asc',
        ]);

        $idsCategoriasConProductos = array_values(array_unique(array_map(
            fn($producto) => (int) $producto->mcategoriaproductos_id,
            array_filter($productosControl, fn($producto) => (int) $producto->mcategoriaproductos_id > 0)
        )));

        $categoriasProductos = $idsCategoriasConProductos
            ? Mcategoriaproducto::find('all', [
                'conditions' => [
                    'status = ? and (mfundos_id = ? or mfundos_id = 0) and id in (?)',
                    1, $usuario['fundo_id'], $idsCategoriasConProductos,
                ],
                'order' => 'nombre asc',
            ])
            : [];

        // Potreros activos del fundo: los usa el movimiento de rebaño
        // offline para elegir origen y destino.
        $potreros = Mpotrero::find('all', [
            'conditions' => ['mfundos_id = ? and status = ?', $usuario['fundo_id'], 1],
            'order' => 'nombre asc',
        ]);

        $periodoPalpacion = Mperiodo::find('first', [
            'conditions' => [
                'status > ? and codper_bov = ? and mtipoganaderias_id = ? and mfundos_id = ?',
                0, 'PSP', $tipoGanaderia, $usuario['fundo_id'],
            ],
        ]);
        if (!$periodoPalpacion) {
            $periodoPalpacion = Mperiodo::find('first', [
                'conditions' => [
                    'status > ? and codper_bov = ? and mtipoganaderias_id = ? and mfundos_id = 0',
                    0, 'PSP', $tipoGanaderia,
                ],
            ]);
        }

        \Api\ApiResponse::ok([
            'aptitudes' => array_map(fn($a) => ['id' => (int) $a->id, 'nombre' => $a->nombre], $aptitudes),
            'lotes' => array_map(fn($l) => ['id' => (int) $l->id, 'nombre' => $l->nombre], $lotes),
            'potreros' => array_map(fn($p) => ['id' => (int) $p->id, 'nombre' => $p->nombre], $potreros),
            'razas' => array_map(fn($r) => ['id' => (int) $r->id, 'nombre' => $r->nombre], $razas),
            'categorias' => array_map(fn($c) => [
                'id' => (int) $c->id,
                'nombre' => $c->nombre,
                'sexo' => (int) $c->sexo,
                'edadminima' => (int) $c->edadminima,
                'edadmaxima' => (int) $c->edadmaxima,
            ], $categorias),
            'tipos_servicio' => array_map(fn($t) => ['id' => (int) $t->id, 'nombre' => $t->nombre], $tiposServicio),
            'estados_fisiologicos' => array_map(fn($e) => ['id' => (int) $e->id, 'nombre' => $e->nombre], $estadosFisiologicos),
            'categorias_productos' => array_map(fn($c) => [
                'id' => (int) $c->id,
                'nombre' => $c->nombre,
            ], $categoriasProductos),
            'productos_control' => array_map(fn($p) => [
                'id' => (int) $p->id,
                'nombre' => $p->nombre,
                'categoria_id' => (int) $p->mcategoriaproductos_id,
            ], $productosControl),
            // Causas de baja: se envían TODAS (también las inactivas)
            // porque no son opciones de un formulario sino etiquetas que
            // hay que poder traducir en las bajas históricas. Ojo: el
            // formulario web guarda el ID en
            // mdesincorporacionanimales.causa_descarte/causa_mortalidad y
            // la app guarda texto libre, por eso el cliente distingue
            // valor numérico (catálogo) de texto.
            'causas_descarte' => array_map(fn($c) => [
                'id' => (int) $c->id,
                'nombre' => $c->nombre,
            ], Mcausadescarte::find('all', ['order' => 'nombre asc'])),
            'causas_mortalidad' => array_map(fn($c) => [
                'id' => (int) $c->id,
                'nombre' => $c->nombre,
            ], Mcausamortalidade::find('all', ['order' => 'nombre asc'])),
            'periodo_palpacion_dias' => $periodoPalpacion ? (int) $periodoPalpacion->dias_gestacion : null,
            'tipo_ganaderia_id' => $tipoGanaderia,
            'fundo_id' => (int) $usuario['fundo_id'],
            'servidor_ahora' => date('Y-m-d H:i:s'),

            // ---- SOLO DIAGNOSTICO TEMPORAL: borrar este bloque una vez
            // ---- confirmado el problema. No lo consume la app offline.
            'debug_diagnostico' => [
                'fundo_id_usado' => $usuario['fundo_id'],
                'fundo_encontrado' => $fundoActivo !== null,
                'tipo_ganaderia_leido_del_fundo' => $fundoActivo->mtipoganaderias_id ?? null,
                'tipo_ganaderia_usado_en_query' => $tipoGanaderia,
                'razas_crudo' => array_map(fn($r) => [
                    'id' => (int) $r->id,
                    'nombre' => $r->nombre,
                    'mfundos_id' => (int) $r->mfundos_id,
                    'mtipoganaderias_id' => (int) $r->mtipoganaderias_id,
                    'status' => (int) $r->status,
                ], $razas),
            ],
        ]);
        break;

    default:
        \Api\ApiResponse::error('Acción no reconocida.', 404);
}
