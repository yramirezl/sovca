<?php

namespace Api;

/**
 * Catálogo de módulos que soportan sincronización offline. Agregar un
 * módulo nuevo (Fase 4 del diseño) es, en la mayoría de los casos,
 * solo sumar una entrada aquí — sync_controller.php es genérico.
 *
 * "campos_editables" es una lista blanca: el cliente NUNCA puede
 * escribir campos fuera de esta lista (id, mfundos_id, version,
 * uuid_local, status, created, user_create, etc. los controla el
 * servidor). Esto evita que un dispositivo comprometido reasigne un
 * registro a otro fundo o pise el control de versiones.
 */
class SyncModulos
{
    private const CATALOGO = [

        'animales' => [
            'tabla' => 'manimales',
            'modelo' => 'ManimalSync',
            'campos_editables' => [
                'codigo_rgb_id_animal',
                'nombre',
                'sexo',
                'imagen',
                'mestatus_id',
                'nro_arete',
                'categoria_ingreso_id',
                'categoria_futura_id',
                'aptitud',
                'mrazas_id',
                'f_nacimiento',
                'peso_nacimiento',
                'f_ingreso',
                'servicio',
                'rgd_madre',
                'nombre_madre',
                'rgd_padre',
                'nombre_padre',
                'procedencia',
                'observaciones',
                'mlotes_id',
                'rena',
                'alza',
                'estatus_animal',
                'mtipoganaderias_id',
                'codigo_ternero',
                'nac_fundo',
            ],
            // Campos NOT NULL en la base que no tienen valor por
            // defecto: si el cliente no los manda, se completan con
            // '' para no romper el INSERT.
            'campos_texto_obligatorios_bd' => [
                'rgd_madre', 'nombre_madre', 'rgd_padre', 'nombre_padre',
                'procedencia', 'observaciones', 'peso_nacimiento',
            ],
            // Campos que sí debe mandar el cliente porque no tienen
            // ningún valor razonable por defecto.
            'campos_requeridos' => ['nombre', 'aptitud', 'mrazas_id', 'nro_arete'],

            // Campos que ningún formulario pide, pero necesitan un
            // valor de negocio razonable al crear (no simplemente '').
            'valores_por_defecto' => [
                'nac_fundo' => true,
            ],

            // Campos que deben ser ÚNICOS dentro de un fundo — se
            // validan tanto contra animales con status=1 al crear
            // (los dados de baja quedan libres para reutilizar su
            // arete, igual que en la app web) como contra el resto de
            // los animales activos al editar, si el payload incluye
            // el campo. El valor del mapa es el "tipo" de conflicto
            // que el cliente usa para decidir qué UI mostrar (ver
            // renderizarConflictos/resolverConflicto en app.js).
            'campos_unicos' => [
                'nro_arete' => 'arete_duplicado',
            ],

            // Este módulo tiene un código que se genera EN EL
            // DISPOSITIVO cuando no hay conexión (ver
            // Db.tomarSiguienteCodigo en db.js): si no hay rango
            // reservado, arma un código provisional que contiene
            // '-PEND-'. 'solo_si_provisional' => true significa que
            // el servidor SOLO regenera el código cuando detecta ese
            // provisional — si el cliente ya mandó un código
            // definitivo (caso normal, con rango reservado), se
            // respeta tal cual, para no gastar un número del
            // correlativo real dos veces.
            'correlativo' => [
                'campo' => 'codigo_rgb_id_animal',
                'prefijo' => 'SOVCA',
                'funcion_sql' => 'reservar_rango_animal',
                'solo_si_provisional' => true,
            ],

            // La app web original, al crear un animal, además de
            // insertar en 'manimales' crea una fila de relación en
            // 'manimalxfundos' (manimales_id, mfundos_id, user_create
            // — nada más). Otras partes del sistema (conteos de plan,
            // reportes) dependen de que esa fila exista, así que
            // crear_registro() la replica cuando ve esta config.
            'crea_relacion_fundo' => [
            'modelo' => 'Manimalxfundos', // modelo explícito, apunta a 'manimalxfundos'
                'campo_id_relacionado' => 'manimales_id',
            ],
        ],

        'nacimientos' => [
            'tabla' => 'mregnacimientos',
            'modelo' => 'MregnacimientoSync',
            'campos_editables' => [
                'codigo_ternero',
                'id_ternero',
                'codigo_rgb_id_animal_hembra',
                'codigo_rgb_id_animal_macho',
                'codigo_rgb_id_animal_dona',
                'mregservicios_id',
                'mtiposervicios_id',
                'f_parto',
                'peso_nacimiento',
                'estatus_nacido',
                'aptitud',
                'sexo',
                'produccion_leche',
                'proposito',
                'observaciones',
                'f_secado',
                'nro_parto',
            ],
            'campos_texto_obligatorios_bd' => [
                'codigo_rgb_id_animal_dona', 'observaciones',
            ],
            'campos_requeridos' => [
                'codigo_rgb_id_animal_hembra',
                'f_parto',
                'sexo',
            ],
            // f_servicio (fecha de celo) y mtipoganaderias_id no los
            // manda el cliente — los calcula/completa el servidor (ver
            // crear_registro_nacimiento en sync_controller.php).
            'calcula_nacimiento' => true,
            // Un parto puede tener varias crías: el cliente envía la
            // lista "terneros" (además de los campos planos, que
            // reflejan al primer ternero para no romper a los clientes
            // anteriores) y el servidor guarda el detalle en
            // mternerosxnacimientos, igual que la app web.
            'sincroniza_terneros' => true,
            // Mismo patrón que animales: el dispositivo genera un
            // código provisional (MA-0<fundo>-PEND-...) cuando no
            // tiene rango reservado, y el servidor lo resuelve al
            // sincronizar.
            'correlativo' => [
                'campo' => 'codigo_ternero',
                'prefijo' => 'MA',
                'funcion_sql' => 'reservar_rango_ternero',
                'solo_si_provisional' => true,
            ],
            // Mismo patrón que animales: además de 'mregnacimientos',
            // crea la fila de relación en 'mnacimientoxfundos'
            // (mregnacimientos_id, mfundos_id, user_create).
            'crea_relacion_fundo' => [
                'modelo' => 'Mnacimientoxfundo',
                'campo_id_relacionado' => 'mregnacimientos_id',
            ],
        ],

        'control_sanitario' => [
            'tabla' => 'mcontrolsanitarios',
            'modelo' => 'Mcontrolsanitario',
            'sincroniza_productos_aplicados' => true,
            'campos_editables' => [
                'codigo_rgb_id_animal',
                'fecha_control',
                'fecha_prox_control',
                'evento',
                'responsable',
                'observaciones',
            ],
            'campos_texto_obligatorios_bd' => [
                'responsable', 'observaciones',
            ],
            'campos_requeridos' => ['codigo_rgb_id_animal', 'fecha_control', 'evento'],
        ],

        // Control sanitario aplicado a un LOTE completo (equivalente a
        // views/controlsanitariomasivo). El registro principal vive en
        // mcontrolsanitarioxlotes; los productos aplicados en
        // mdetacrlsanitariolotes y los animales incluidos en
        // mdetacsxlotes. El código del control (RCS-0<fundo>-0<n>) lo
        // genera SIEMPRE el servidor, porque la web usa un correlativo
        // por conteo que no se puede reservar desde el dispositivo.
        'control_sanitario_lotes' => [
            'tabla' => 'mcontrolsanitarioxlotes',
            'modelo' => 'Mcontrolsanitarioxlote',
            'campos_editables' => [
                'codigo_mcs_lote',
                'mlotes_id',
                'fecha_control',
                'fecha_prox_control',
                'evento',
                'responsable',
                'observaciones',
            ],
            'campos_texto_obligatorios_bd' => ['observaciones'],
            'campos_requeridos' => ['mlotes_id', 'fecha_control', 'evento', 'responsable'],
            'calcula_control_lote' => true,
        ],

        'produccion_leche' => [
            'tabla' => 'mprolecheras',
            'modelo' => 'Mprolechera',
            'campos_editables' => [
                'codigo_rgb_id_animal_hembra',
                'fecha_produccion',
                'cantidad_leche_m',
                'cantidad_leche_t',
            ],
            'campos_texto_obligatorios_bd' => [],
            'campos_requeridos' => ['codigo_rgb_id_animal_hembra', 'fecha_produccion'],
        ],

        'pesos' => [
            'tabla' => 'mconsecutivopesos',
            'modelo' => 'Mconsecutivopeso',
            'campos_editables' => [
                'codigo_rgb_id_animal',
                'fecha_pesaje',
                'peso',
            ],
            'campos_texto_obligatorios_bd' => [],
            'campos_requeridos' => ['codigo_rgb_id_animal', 'fecha_pesaje', 'peso'],
        ],

        // Desincorporación (bajas): descarte, muerte, venta o extravío.
        // El tipo 7 (MIGRADO A OTRO FUNDO) NO se acepta desde el
        // dispositivo: lo aplica el módulo de migración y es informativo.
        // Al crear, el servidor también cambia manimales.mestatus_id.
        'desincorporacion' => [
            'tabla' => 'mdesincorporacionanimales',
            'modelo' => 'Mdesincorporacionanimale',
            'campos_editables' => [
                'codigo_rgb_id_animal',
                'f_desincorporacion',
                'tipo_desincorporacion',
                'causa_descarte',
                'causa_mortalidad',
                'observaciones',
            ],
            'campos_texto_obligatorios_bd' => ['causa_descarte', 'causa_mortalidad', 'observaciones'],
            'campos_requeridos' => ['codigo_rgb_id_animal', 'f_desincorporacion', 'tipo_desincorporacion'],
            'desincorpora_animal' => true,
            'solo_crear' => true,
        ],

        // Movimiento de rebaño: mueve animales entre lotes y/o potreros.
        // El detalle (categoría antes/después, lote/potrero anterior y
        // nuevo) y la actualización de manimales los hace el servidor.
        'movimiento_rebano' => [
            'tabla' => 'mmovimientos_rebano',
            'modelo' => 'Mmovimientorebano',
            'campos_editables' => [
                'tipo_movimiento',
                'mlotes_origen_id',
                'mlotes_destino_id',
                'mpotreros_origen_id',
                'mpotreros_destino_id',
                'fecha_movimiento',
                'motivo',
                'observaciones',
            ],
            'campos_texto_obligatorios_bd' => ['observaciones'],
            'campos_requeridos' => ['tipo_movimiento', 'fecha_movimiento', 'motivo'],
            'crea_movimiento_rebano' => true,
            'solo_crear' => true,
        ],

        'servicios' => [
            'tabla' => 'mregservicios',
            'modelo' => 'Mregservicio',
            'campos_editables' => [
                'codigo_servicio',
                'codigo_rgb_id_animal_hembra',
                'codigo_rgb_id_animal_macho',
                'codigo_rgb_id_animal_dona',
                'f_servicio',
                'f_probable_palpacion',
                'nro_servicio',
                'mservicios_id',
                'aptitud',
                'mrazas_id',
                'observaciones',
                'f_ultimo_parto',
                'f_secado',
                'f_palpacion',
                'medofisiologicos_id',
                'f_probable_parto',
                'parto',
                'fecha_parto',
                'nro_terneros',
                'dnp',
            ],
            'campos_texto_obligatorios_bd' => [
                'codigo_rgb_id_animal_macho', 'codigo_rgb_id_animal_dona', 'observaciones',
            ],
            'campos_requeridos' => [
                'codigo_rgb_id_animal_hembra',
                'f_servicio',
                'mservicios_id',
                'aptitud',
                'mrazas_id',
            ],
            'usa_ciclo_reproductivo' => true,
            'correlativo' => [
                'campo' => 'codigo_servicio',
                'prefijo' => 'RSA',
                'funcion_sql' => 'reservar_rango_servicio',
                'solo_si_provisional' => false,
            ],
        ],

        // Fase 4: agregar aquí 'control_sanitario', 'produccion_leche',
        // 'nacimientos', 'pesos', etc. siguiendo el mismo patrón.
    ];

    public static function config(string $modulo): ?array
    {
        return self::CATALOGO[$modulo] ?? null;
    }

    public static function filtrarCampos(array $datos, array $permitidos): array
    {
        return array_intersect_key($datos, array_flip($permitidos));
    }
}