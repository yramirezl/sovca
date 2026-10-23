<?php
require_once '../models/Manimale.php';
require_once '../models/Manimalxfundo.php';
require_once '../models/Mfundo.php';
require_once '../models/MmigracionAnimale.php';
require_once '../models/Mdesincorporacionanimale.php';
require_once '../models/Mraza.php';
require_once '../models/Mcategoria.php';

include '../lib/sanitizar.php';
date_default_timezone_set('America/Caracas');

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['idusuariox'])) {
    http_response_code(401);
    echo json_encode(array('ok' => false, 'msg' => 'Sesión expirada'));
    exit;
}

@$action = isset($_POST['action']) ? $_POST['action'] : '';

$usuario_id = (int)$_SESSION['idusuariox'];
$rolx       = (int)$_SESSION['rolx'];

switch ($action) {

    // ═════════════════════════════════════════════════════════
    // A) LISTAR FUNDOS (con tipo de ganadería)
    // ═════════════════════════════════════════════════════════
    case 'listar_fundos':
        @include_once __DIR__ . '/../lib/multifundo_helper.php';

        if (!function_exists('mf_listar_fundos_usuario')) {
            echo json_encode(array('ok' => false, 'msg' => 'Helper no disponible'));
            break;
        }

        $fundos = mf_listar_fundos_usuario($usuario_id);
        $out = array();

        foreach ($fundos as $f) {
            if (is_array($f)) {
                $fid       = isset($f['mfundos_id'])         ? $f['mfundos_id']         : 0;
                $fname     = isset($f['nombre'])             ? $f['nombre']             : '';
                $frif      = isset($f['cid_rif'])            ? $f['cid_rif']            : '';
                $ftipo_gan = isset($f['mtipoganaderias_id']) ? (int)$f['mtipoganaderias_id'] : 0;
            } else {
                $fid       = isset($f->mfundos_id)         ? $f->mfundos_id         : 0;
                $fname     = isset($f->nombre)             ? $f->nombre             : '';
                $frif      = isset($f->cid_rif)            ? $f->cid_rif            : '';
                $ftipo_gan = isset($f->mtipoganaderias_id) ? (int)$f->mtipoganaderias_id : 0;
            }

            if ((int)$fid > 0) {
                $out[] = array(
                    'id'             => (int)$fid,
                    'nombre'         => $fname,
                    'rif'            => $frif,
                    'tipo_ganaderia' => $ftipo_gan,
                    'tipo_txt'       => ($ftipo_gan === 1) ? 'OVINA' : (($ftipo_gan === 2) ? 'CAPRINA' : 'N/D')
                );
            }
        }

        echo json_encode(array('ok' => true, 'total' => count($out), 'fundos' => $out));
        break;

    // ═════════════════════════════════════════════════════════
    // B) LISTAR ANIMALES (excluye ya migrados)
    // ═════════════════════════════════════════════════════════
    case 'listar_animales':
        $fundo_id = (int)(isset($_POST['fundo_id']) ? $_POST['fundo_id'] : 0);
        if (!$fundo_id) { echo json_encode(array('ok' => false, 'msg' => 'Fundo inválido')); break; }

        @include_once __DIR__ . '/../lib/multifundo_helper.php';

        $acceso = false;
        if (function_exists('mf_listar_fundos_usuario')) {
            $fundos_user = mf_listar_fundos_usuario($usuario_id);
            foreach ($fundos_user as $fu) {
                $fid = is_array($fu)
                    ? (isset($fu['mfundos_id']) ? (int)$fu['mfundos_id'] : 0)
                    : (isset($fu->mfundos_id) ? (int)$fu->mfundos_id : 0);

                if ($fid === $fundo_id) { $acceso = true; break; }
            }
        }

        if (!$acceso && !in_array($rolx, array(1, 4), true)) {
            echo json_encode(array('ok' => false, 'msg' => 'Sin acceso al fundo ' . $fundo_id));
            break;
        }

        $buscar = isset($_POST['buscar']) ? addslashes($_POST['buscar']) : '';
        $filtro = '';
        if ($buscar !== '') {
            $filtro = " AND (m.nombre ILIKE '%$buscar%'
                            OR m.nro_arete ILIKE '%$buscar%'
                            OR m.codigo_rgb_id_animal ILIKE '%$buscar%') ";
        }

        $sql = "SELECT
                    m.id,
                    m.codigo_rgb_id_animal AS codigo,
                    m.nombre,
                    m.nro_arete,
                    m.sexo,
                    CASE WHEN m.sexo = 1 THEN 'HEMBRA' ELSE 'MACHO' END AS sexo_txt,
                    CASE
                        WHEN m.estatus_animal = 1 THEN 'ACTIVO'
                        WHEN m.estatus_animal = 2 THEN 'DONADOR ACTIVO'
                        WHEN m.estatus_animal = 3 THEN 'DONADOR'
                        ELSE 'N/D'
                    END AS estatus_txt,
                    m.f_nacimiento,
                    COALESCE(EXTRACT(YEAR FROM AGE(NOW(), m.f_nacimiento))::int, 0) AS anios,
                    l.nombre AS lote
                FROM manimales m
                LEFT JOIN mlotes l ON l.id = m.mlotes_id
                WHERE m.mfundos_id = $fundo_id
                  AND m.status = 1
                  AND m.mestatus_id = 1
                  AND m.estatus_animal IN (1, 2, 3)
                  AND NOT EXISTS (
                      SELECT 1 FROM mmigraciones_animales mg
                       WHERE mg.manimal_origen_id = m.id
                         AND mg.estado = 'completada'
                         AND mg.revertida = FALSE
                  )
                  $filtro
                ORDER BY m.nombre ASC
                LIMIT 500";

        $data = @Manimale::find_by_sql($sql);
        $out = array();

        if ($data) {
            foreach ($data as $a) {
                $fecha_nac = '';
                if ($a->f_nacimiento instanceof DateTime) {
                    $fecha_nac = $a->f_nacimiento->format('d-m-Y');
                } elseif ($a->f_nacimiento) {
                    $ts = strtotime($a->f_nacimiento);
                    if ($ts) $fecha_nac = date('d-m-Y', $ts);
                }

                $out[] = array(
                    'id'        => (int)$a->id,
                    'codigo'    => $a->codigo,
                    'nombre'    => $a->nombre,
                    'nro_arete' => $a->nro_arete,
                    'sexo'      => $a->sexo_txt,
                    'estatus'   => $a->estatus_txt,
                    'f_nac'     => $fecha_nac,
                    'anios'     => (int)$a->anios,
                    'lote'      => $a->lote ? $a->lote : '—'
                );
            }
        }

        echo json_encode(array('ok' => true, 'total' => count($out), 'animales' => $out));
        break;

    // ═════════════════════════════════════════════════════════
    // C) VALIDAR
    // ═════════════════════════════════════════════════════════
    case 'validar':
        $fundo_origen_id  = (int)(isset($_POST['fundo_origen_id'])  ? $_POST['fundo_origen_id']  : 0);
        $fundo_destino_id = (int)(isset($_POST['fundo_destino_id']) ? $_POST['fundo_destino_id'] : 0);
        $ids_raw          = isset($_POST['animales']) ? $_POST['animales'] : '';

        if (!$fundo_origen_id || !$fundo_destino_id) {
            echo json_encode(array('ok' => false, 'msg' => 'Fundos inválidos')); break;
        }
        if ($fundo_origen_id === $fundo_destino_id) {
            echo json_encode(array('ok' => false, 'msg' => 'Los fundos deben ser distintos')); break;
        }

        // ⭐ VALIDAR TIPO DE GANADERÍA
        $fo_info = Mfundo::find('all', array('conditions' => array('id=?', $fundo_origen_id)));
        $fd_info = Mfundo::find('all', array('conditions' => array('id=?', $fundo_destino_id)));

        if (!$fo_info || !$fd_info) {
            echo json_encode(array('ok' => false, 'msg' => 'Fundos no encontrados')); break;
        }

        $tipo_origen  = (int)$fo_info[0]->mtipoganaderias_id;
        $tipo_destino = (int)$fd_info[0]->mtipoganaderias_id;

        if ($tipo_origen !== $tipo_destino) {
            $nombres_tipo = array(1 => 'OVINA', 2 => 'CAPRINA');
            $t_o = isset($nombres_tipo[$tipo_origen])  ? $nombres_tipo[$tipo_origen]  : 'N/D';
            $t_d = isset($nombres_tipo[$tipo_destino]) ? $nombres_tipo[$tipo_destino] : 'N/D';

            echo json_encode(array(
                'ok'  => false,
                'msg' => "No se puede migrar entre fundos de tipos distintos. " .
                         "Origen: $t_o. Destino: $t_d."
            ));
            break;
        }

        $ids = array();
        foreach (explode(',', $ids_raw) as $i) {
            $i = (int)$i;
            if ($i > 0) $ids[] = $i;
        }
        if (count($ids) === 0) {
            echo json_encode(array('ok' => false, 'msg' => 'Seleccione al menos un animal')); break;
        }

        $ids_sql = implode(',', $ids);

        $q = Manimale::find_by_sql("
            SELECT COUNT(*) AS total FROM manimales
             WHERE id IN ($ids_sql)
               AND mfundos_id = $fundo_origen_id
               AND status = 1
               AND mestatus_id = 1
        ");
        $encontrados = ($q && isset($q[0]->total)) ? (int)$q[0]->total : 0;

        if ($encontrados !== count($ids)) {
            echo json_encode(array(
                'ok'  => false,
                'msg' => 'Algunos animales no pertenecen al fundo origen o no están activos'
            ));
            break;
        }

        // Validar conflictos de NRO_ARETE en destino
        $conflictos_arete = Manimale::find_by_sql("
            SELECT m.nombre, m.nro_arete
              FROM manimales m
             WHERE m.mfundos_id = $fundo_destino_id
               AND m.status = 1
               AND m.mestatus_id = 1
               AND m.nro_arete <> ''
               AND UPPER(TRIM(m.nro_arete)) IN (
                   SELECT UPPER(TRIM(a.nro_arete))
                     FROM manimales a
                    WHERE a.id IN ($ids_sql) AND a.nro_arete <> ''
               )
        ");

        $conflictos = array();
        if ($conflictos_arete) {
            foreach ($conflictos_arete as $c) {
                $conflictos[] = $c->nro_arete;
            }
        }

        if (count($conflictos) > 0) {
            echo json_encode(array(
                'ok'         => false,
                'msg'        => 'Existen conflictos de NRO_ARETE en el fundo destino',
                'conflictos' => $conflictos
            ));
            break;
        }

        $advertencias = array();

        // Ciclos reproductivos abiertos
        $ciclos = Manimale::find_by_sql("
            SELECT DISTINCT m.nombre, m.codigo_rgb_id_animal
              FROM manimales m
             INNER JOIN mregservicios r
                ON r.codigo_rgb_id_animal_hembra = m.codigo_rgb_id_animal
             WHERE m.id IN ($ids_sql)
               AND r.status = 1
               AND r.estatus_ciclo = 1
        ");
        if ($ciclos) {
            foreach ($ciclos as $c) {
                $advertencias[] = "El animal {$c->nombre} ({$c->codigo_rgb_id_animal}) tiene un ciclo reproductivo abierto.";
            }
        }

        // Crías lactantes
        $crias = Manimale::find_by_sql("
            SELECT DISTINCT m.nombre, m.codigo_rgb_id_animal,
                   (SELECT COUNT(*) FROM mregnacimientos r
                     WHERE r.codigo_rgb_id_animal_hembra = m.codigo_rgb_id_animal
                       AND r.status = 1
                       AND r.fichado = FALSE
                       AND r.f_parto > NOW() - INTERVAL '8 months') AS crias_activas
              FROM manimales m
             WHERE m.id IN ($ids_sql)
               AND m.sexo = 1
        ");
        if ($crias) {
            foreach ($crias as $c) {
                if ((int)$c->crias_activas > 0) {
                    $advertencias[] = "El animal {$c->nombre} tiene {$c->crias_activas} cría(s) lactante(s).";
                }
            }
        }

        echo json_encode(array(
            'ok'           => true,
            'total'        => count($ids),
            'advertencias' => $advertencias
        ));
        break;

    // ═════════════════════════════════════════════════════════
    // D) EJECUTAR MIGRACIÓN
    // ═════════════════════════════════════════════════════════
    case 'ejecutar':
        $fundo_origen_id  = (int)(isset($_POST['fundo_origen_id'])  ? $_POST['fundo_origen_id']  : 0);
        $fundo_destino_id = (int)(isset($_POST['fundo_destino_id']) ? $_POST['fundo_destino_id'] : 0);
        $ids_raw          = isset($_POST['animales']) ? $_POST['animales'] : '';
        $motivo           = isset($_POST['motivo']) ? trim($_POST['motivo']) : '';
        $observaciones    = isset($_POST['observaciones']) ? trim($_POST['observaciones']) : '';

        if (!$fundo_origen_id || !$fundo_destino_id || $fundo_origen_id === $fundo_destino_id) {
            echo json_encode(array('ok' => false, 'msg' => 'Parámetros inválidos')); break;
        }

        $ids = array();
        foreach (explode(',', $ids_raw) as $i) {
            $i = (int)$i;
            if ($i > 0) $ids[] = $i;
        }
        if (count($ids) === 0) {
            echo json_encode(array('ok' => false, 'msg' => 'Sin animales')); break;
        }

        if ($motivo === '') {
            echo json_encode(array('ok' => false, 'msg' => 'Indique el motivo del traslado')); break;
        }

        if (!class_exists('ActiveRecord\\ConnectionManager')) {
            echo json_encode(array('ok' => false, 'msg' => 'Sin conexión a la BD')); break;
        }
        $conn = ActiveRecord\ConnectionManager::get_connection();

        $fundo_origen_info  = Mfundo::find('all', array('conditions' => array('id=?', $fundo_origen_id)));
        $fundo_destino_info = Mfundo::find('all', array('conditions' => array('id=?', $fundo_destino_id)));
        if (!$fundo_origen_info || !$fundo_destino_info) {
            echo json_encode(array('ok' => false, 'msg' => 'Fundos no encontrados')); break;
        }

        $nombre_origen  = $fundo_origen_info[0]->nombre;
        $nombre_destino = $fundo_destino_info[0]->nombre;
        $tipo_origen    = (int)$fundo_origen_info[0]->mtipoganaderias_id;
        $tipo_destino   = (int)$fundo_destino_info[0]->mtipoganaderias_id;

        // ⭐ VALIDAR TIPO DE GANADERÍA
        if ($tipo_origen !== $tipo_destino) {
            $nombres_tipo = array(1 => 'OVINA', 2 => 'CAPRINA');
            $t_o = isset($nombres_tipo[$tipo_origen])  ? $nombres_tipo[$tipo_origen]  : 'N/D';
            $t_d = isset($nombres_tipo[$tipo_destino]) ? $nombres_tipo[$tipo_destino] : 'N/D';

            echo json_encode(array(
                'ok'  => false,
                'msg' => "No se puede migrar entre fundos de tipos distintos. " .
                         "Origen: $t_o. Destino: $t_d."
            ));
            break;
        }

        $resultado = array('exitosos' => array(), 'fallidos' => array());
        $hoy_dmy   = date('d-m-Y');
        $hoy_sql   = date('Y-m-d H:i:s');

        try {
            $conn->transaction();

            foreach ($ids as $id_orig) {

                $a = Manimale::find('all', array(
                    'conditions' => array(
                        'id=? AND mfundos_id=? AND status=1 AND mestatus_id=1',
                        $id_orig, $fundo_origen_id
                    )
                ));
                if (!$a || !isset($a[0])) {
                    $resultado['fallidos'][] = "ID $id_orig no encontrado o no activo";
                    continue;
                }
                $animal = $a[0];

                // ═══════════════════════════════════════════════════
                // RESOLVER RAZA Y CATEGORÍAS EN EL FUNDO DESTINO
                // ═══════════════════════════════════════════════════
                $raza_destino_id     = 0;
                $cat_ingreso_destino = 0;
                $cat_futura_destino  = 0;
                $auto_creados        = array();

                // ─── RAZA ──────────────────────────────────────────
                if ((int)$animal->mrazas_id > 0) {
                    $raza_info = Manimale::find_by_sql("
                        SELECT nombre, id_m_paises, mtipoganaderias_id
                          FROM mrazas
                         WHERE id = " . (int)$animal->mrazas_id . "
                         LIMIT 1
                    ");

                    if ($raza_info && isset($raza_info[0])) {
                        $raza_nombre = addslashes($raza_info[0]->nombre);

                        // ¿Existe la raza en destino o globalmente?
                        $raza_existe = Manimale::find_by_sql("
                            SELECT id
                              FROM mrazas
                             WHERE UPPER(nombre) = UPPER('$raza_nombre')
                               AND (mfundos_id = $fundo_destino_id OR mfundos_id = 0)
                               AND status = 1
                             ORDER BY 
                               CASE WHEN mfundos_id = $fundo_destino_id THEN 0 ELSE 1 END
                             LIMIT 1
                        ");

                        if ($raza_existe && isset($raza_existe[0]->id)) {
                            $raza_destino_id = (int)$raza_existe[0]->id;
                        } else {
                            // Crear copia en destino
                            $nueva_raza = new Mraza();
                            $nueva_raza->nombre             = $raza_info[0]->nombre;
                            $nueva_raza->id_m_paises        = $raza_info[0]->id_m_paises;
                            $nueva_raza->mtipoganaderias_id = $tipo_destino;
                            $nueva_raza->mfundos_id         = $fundo_destino_id;
                            $nueva_raza->user_create        = $usuario_id;
                            $nueva_raza->created            = $hoy_dmy;
                            $nueva_raza->status             = 1;

                            if ($nueva_raza->save()) {
                                $raza_destino_id = (int)$nueva_raza->id;
                                $auto_creados[] = "Raza: {$raza_info[0]->nombre}";
                            }
                        }
                    }
                }

                // ─── CATEGORÍA INGRESO ─────────────────────────────
                if ((int)$animal->categoria_ingreso_id > 0) {
                    $res_cat = resolverCategoria(
                        (int)$animal->categoria_ingreso_id,
                        $fundo_destino_id,
                        $usuario_id,
                        $hoy_dmy
                    );
                    $cat_ingreso_destino = $res_cat['id'];
                    if ($res_cat['creada']) {
                        $auto_creados[] = "Categoría ingreso: {$res_cat['nombre']}";
                    }
                }

                // ─── CATEGORÍA FUTURA ──────────────────────────────
                if ((int)$animal->categoria_futura_id > 0) {
                    $res_cat = resolverCategoria(
                        (int)$animal->categoria_futura_id,
                        $fundo_destino_id,
                        $usuario_id,
                        $hoy_dmy
                    );
                    $cat_futura_destino = $res_cat['id'];
                    if ($res_cat['creada']) {
                        $auto_creados[] = "Categoría futura: {$res_cat['nombre']}";
                    }
                }

                // ═══════════════════════════════════════════════════
                // RESERVAR CÓDIGO ATÓMICAMENTE
                // ═══════════════════════════════════════════════════
                $rango = Manimale::find_by_sql("
                    SELECT desde, hasta
                      FROM reservar_rango_animal($fundo_destino_id, 1)
                ");
                if (!$rango || !isset($rango[0]->desde)) {
                    throw new Exception("No se pudo reservar código en fundo $fundo_destino_id");
                }
                $nuevo_nro    = (int)$rango[0]->desde;
                $codigo_nuevo = 'SOVCA-0' . $fundo_destino_id . '-0' . $nuevo_nro;

                // Copiar imagen
                $imagen_nueva = '';
                if (!empty($animal->imagen)) {
                    $src = __DIR__ . '/../dist/img/fotos_animales/' . $animal->imagen;
                    if (file_exists($src)) {
                        $ext = pathinfo($animal->imagen, PATHINFO_EXTENSION);
                        $imagen_nueva = $codigo_nuevo . '.' . $ext;
                        $dst = __DIR__ . '/../dist/img/fotos_animales/' . $imagen_nueva;
                        @copy($src, $dst);
                    }
                }

                // Registrar migración
                $mig = new MmigracionAnimale();
                $mig->manimal_origen_id  = (int)$animal->id;
                $mig->manimal_destino_id = 0;
                $mig->mfundos_origen_id  = $fundo_origen_id;
                $mig->mfundos_destino_id = $fundo_destino_id;
                $mig->codigo_original    = $animal->codigo_rgb_id_animal;
                $mig->codigo_nuevo       = $codigo_nuevo;
                $mig->motivo             = $motivo;
                $mig->observaciones      = $observaciones;
                $mig->usuario_id         = $usuario_id;
                $mig->estado             = 'en_proceso';
                if (!$mig->save()) {
                    throw new Exception("No se pudo crear registro de migración para ID $id_orig");
                }
                $migracion_id = (int)$mig->id;

                // ⭐ Observaciones con nota de autocreación
                $obs_final = (string)$animal->observaciones;
                if (count($auto_creados) > 0) {
                    $obs_final .= "\n[MIGRACIÓN] Catálogos creados automáticamente: " . implode('; ', $auto_creados);
                }

                // Crear animal nuevo
                $nuevo = new Manimale();
                $nuevo->codigo_rgb_id_animal   = $codigo_nuevo;
                $nuevo->nombre                 = $animal->nombre;
                $nuevo->sexo                   = (int)$animal->sexo;
                $nuevo->estatus_animal         = (int)$animal->estatus_animal;
                $nuevo->mestatus_id            = 1;
                $nuevo->nro_arete              = $animal->nro_arete;
                $nuevo->categoria_ingreso_id   = $cat_ingreso_destino > 0 ? $cat_ingreso_destino : (int)$animal->categoria_ingreso_id;
                $nuevo->categoria_futura_id    = $cat_futura_destino > 0  ? $cat_futura_destino  : (int)$animal->categoria_futura_id;
                $nuevo->aptitud                = (int)$animal->aptitud;
                $nuevo->mrazas_id              = $raza_destino_id > 0 ? $raza_destino_id : (int)$animal->mrazas_id;
                $nuevo->f_nacimiento           = $animal->f_nacimiento ? $animal->f_nacimiento->format('Y-m-d') : null;
                $nuevo->peso_nacimiento        = $animal->peso_nacimiento;
                $nuevo->f_ingreso              = $hoy_sql;
                $nuevo->servicio               = (int)$animal->servicio;
                $nuevo->rgd_madre              = $animal->rgd_madre;
                $nuevo->nombre_madre           = $animal->nombre_madre;
                $nuevo->rgd_padre              = $animal->rgd_padre;
                $nuevo->nombre_padre           = $animal->nombre_padre;
                $nuevo->procedencia            = 'MIGRADO DESDE: ' . $nombre_origen . ' (' . $animal->codigo_rgb_id_animal . ')';
                $nuevo->observaciones          = $obs_final;
                $nuevo->mfundos_id             = $fundo_destino_id;
                $nuevo->mlotes_id              = 0;
                $nuevo->rena                   = $animal->rena;
                $nuevo->alza                   = $animal->alza;
                $nuevo->mtipoganaderias_id     = $tipo_destino;
                $nuevo->imagen                 = $imagen_nueva;
                $nuevo->user_create            = $usuario_id;
                $nuevo->created                = $hoy_dmy;
                $nuevo->nac_fundo              = 'f';

                // Vínculo con origen
                $nuevo->animal_origen_id       = (int)$animal->id;
                $nuevo->codigo_origen          = $animal->codigo_rgb_id_animal;
                $nuevo->mfundos_origen_id      = $fundo_origen_id;
                $nuevo->fecha_migracion        = $hoy_sql;
                $nuevo->mmigracion_id          = $migracion_id;

                if (!$nuevo->save()) {
                    throw new Exception("No se pudo crear el animal nuevo para ID $id_orig");
                }
                $nuevo_id = (int)$nuevo->id;

                // Vínculo animal-fundo
                $af = new Manimalxfundo();
                $af->manimales_id  = $nuevo_id;
                $af->mfundos_id    = $fundo_destino_id;
                $af->user_create   = $usuario_id;
                if (!$af->save()) {
                    throw new Exception("No se pudo crear el vínculo animal-fundo para ID $id_orig");
                }

                // Desincorporación en origen
                $des = new Mdesincorporacionanimale();
                $des->codigo_rgb_id_animal    = $animal->codigo_rgb_id_animal;
                $des->tipo_desincorporacion   = 7;
                $des->f_desincorporacion      = $hoy_dmy;
                $des->mfundos_id              = $fundo_origen_id;
                $des->observaciones           = 'Migrado a ' . $nombre_destino . ' (código nuevo: ' . $codigo_nuevo . ')';
                $des->user_create             = $usuario_id;
                $des->created                 = $hoy_dmy;
                if (!$des->save()) {
                    throw new Exception("No se pudo registrar la desincorporación para ID $id_orig");
                }

                // Marcar animal origen como migrado
                $orig = Manimale::find($animal->id);
                $orig->mestatus_id = 7;
                $orig->user_modify = $usuario_id;
                $orig->updated     = $hoy_dmy;
                if (!$orig->save()) {
                    throw new Exception("No se pudo actualizar el animal origen para ID $id_orig");
                }

                // Actualizar la migración con el destino real
                $mig_upd = MmigracionAnimale::find($migracion_id);
                $mig_upd->manimal_destino_id = $nuevo_id;
                $mig_upd->estado             = 'completada';
                $mig_upd->save();

                $resultado['exitosos'][] = array(
                    'id_origen'    => (int)$animal->id,
                    'codigo_orig'  => $animal->codigo_rgb_id_animal,
                    'id_destino'   => $nuevo_id,
                    'codigo_nuevo' => $codigo_nuevo,
                    'nombre'       => $animal->nombre,
                    'auto_creados' => $auto_creados
                );
            }

            $conn->commit();

            echo json_encode(array(
                'ok'       => true,
                'exitosos' => $resultado['exitosos'],
                'fallidos' => $resultado['fallidos'],
                'total'    => count($resultado['exitosos']),
                'msg'      => 'Migración completada: ' . count($resultado['exitosos']) . ' animal(es)'
            ));

        } catch (Exception $e) {
            try { $conn->rollback(); } catch (Exception $e2) {}
            echo json_encode(array(
                'ok'        => false,
                'msg'       => 'Error durante la migración: ' . $e->getMessage(),
                'parciales' => $resultado['exitosos']
            ));
        }
        break;

    // ═════════════════════════════════════════════════════════
    // E) LISTAR MIGRACIONES
    // ═════════════════════════════════════════════════════════
    case 'listar_migraciones':
        $data = MmigracionAnimale::find_by_sql("
            SELECT mg.*,
                   fo.nombre AS fundo_origen,
                   fd.nombre AS fundo_destino,
                   u.name AS usuario
              FROM mmigraciones_animales mg
              LEFT JOIN mfundos fo ON fo.id = mg.mfundos_origen_id
              LEFT JOIN mfundos fd ON fd.id = mg.mfundos_destino_id
              LEFT JOIN musuarios u ON u.id = mg.usuario_id
             WHERE mg.status = 1
             ORDER BY mg.fecha_migracion DESC
             LIMIT 100
        ");

        $out = array();
        if ($data) {
            foreach ($data as $m) {
                $fecha = '';
                if ($m->fecha_migracion instanceof DateTime) {
                    $fecha = $m->fecha_migracion->format('d-m-Y H:i');
                } elseif ($m->fecha_migracion) {
                    $fecha = date('d-m-Y H:i', strtotime($m->fecha_migracion));
                }
                $out[] = array(
                    'id'              => (int)$m->id,
                    'fecha'           => $fecha,
                    'fundo_origen'    => $m->fundo_origen,
                    'fundo_destino'   => $m->fundo_destino,
                    'codigo_original' => $m->codigo_original,
                    'codigo_nuevo'    => $m->codigo_nuevo,
                    'motivo'          => $m->motivo,
                    'usuario'         => $m->usuario,
                    'estado'          => $m->estado,
                    'revertida'       => (bool)$m->revertida
                );
            }
        }

        echo json_encode(array('ok' => true, 'total' => count($out), 'migraciones' => $out));
        break;

    // ═════════════════════════════════════════════════════════
    // F) REVERTIR MIGRACIÓN
    // ═════════════════════════════════════════════════════════
    case 'revertir':
        $migracion_id = (int)(isset($_POST['migracion_id']) ? $_POST['migracion_id'] : 0);
        if (!$migracion_id) { echo json_encode(array('ok' => false, 'msg' => 'ID inválido')); break; }

        $mig = MmigracionAnimale::find('all', array(
            'conditions' => array('id=? AND status=1 AND revertida=FALSE', $migracion_id)
        ));
        if (!$mig || !isset($mig[0])) {
            echo json_encode(array('ok' => false, 'msg' => 'Migración no encontrada o ya revertida'));
            break;
        }
        $M = $mig[0];

        $cod_nuevo = addslashes($M->codigo_nuevo);
        $tiene_datos = false;

        $checks = array(
            "SELECT 1 FROM mprolecheras WHERE codigo_rgb_id_animal_hembra = '$cod_nuevo' AND status = 1 LIMIT 1",
            "SELECT 1 FROM mcontrolsanitarios WHERE codigo_rgb_id_animal = '$cod_nuevo' AND status = 1 LIMIT 1",
            "SELECT 1 FROM mconsecutivopesos WHERE codigo_rgb_id_animal = '$cod_nuevo' AND status = 1 LIMIT 1",
            "SELECT 1 FROM mregservicios WHERE codigo_rgb_id_animal_hembra = '$cod_nuevo' AND status = 1 LIMIT 1",
        );
        foreach ($checks as $sql) {
            $r = Manimale::find_by_sql($sql);
            if ($r && count($r) > 0) { $tiene_datos = true; break; }
        }

        if ($tiene_datos) {
            echo json_encode(array(
                'ok'  => false,
                'msg' => 'No se puede revertir: el animal tiene registros nuevos en el fundo destino.'
            ));
            break;
        }

        try {
            $conn = ActiveRecord\ConnectionManager::get_connection();
            $conn->transaction();

            $orig = Manimale::find($M->manimal_origen_id);
            if ($orig) {
                $orig->mestatus_id = 1;
                $orig->user_modify = $usuario_id;
                $orig->updated     = date('d-m-Y');
                $orig->save();
            }

            $dest = Manimale::find($M->manimal_destino_id);
            if ($dest) {
                $dest->mestatus_id = 7;
                $dest->user_modify = $usuario_id;
                $dest->updated     = date('d-m-Y');
                $dest->save();
            }

            $M->revertida         = true;
            $M->fecha_reversion   = date('Y-m-d H:i:s');
            $M->usuario_reversion = $usuario_id;
            $M->motivo_reversion  = isset($_POST['motivo']) ? trim($_POST['motivo']) : 'Reversión manual';
            $M->estado            = 'revertida';
            $M->save();

            $conn->commit();

            echo json_encode(array('ok' => true, 'msg' => 'Migración revertida exitosamente'));

        } catch (Exception $e) {
            try { $conn->rollback(); } catch (Exception $e2) {}
            echo json_encode(array('ok' => false, 'msg' => 'Error al revertir: ' . $e->getMessage()));
        }
        break;

    default:
        echo json_encode(array('ok' => false, 'msg' => 'Acción no reconocida: ' . $action));
}

/**
 * Resuelve una categoría para el fundo destino.
 * Devuelve array('id' => int, 'creada' => bool, 'nombre' => string)
 * - Si ya existe por nombre en destino → la retorna (creada = false)
 * - Si existe global (mfundos_id = 0) → la usa (creada = false)
 * - Si no existe → crea copia en destino (creada = true)
 * - Si falla → retorna la original
 */
function resolverCategoria($cat_origen_id, $fundo_destino_id, $usuario_id, $hoy) {
    if ((int)$cat_origen_id <= 0) {
        return array('id' => 0, 'creada' => false, 'nombre' => '');
    }

    // Info de la categoría original
    $cat_info = Manimale::find_by_sql("
        SELECT nombre, sexo, edadminima, edadmaxima, id_padre,
               mtipoganaderias_id
          FROM mcategorias
         WHERE id = " . (int)$cat_origen_id . "
         LIMIT 1
    ");

    if (!$cat_info || !isset($cat_info[0])) {
        return array('id' => (int)$cat_origen_id, 'creada' => false, 'nombre' => '');
    }

    $nombre_cat = addslashes($cat_info[0]->nombre);

    // ¿Existe en destino?
    $cat_existe = Manimale::find_by_sql("
        SELECT id
          FROM mcategorias
         WHERE UPPER(nombre) = UPPER('$nombre_cat')
           AND sexo = " . (int)$cat_info[0]->sexo . "
           AND mfundos_id = $fundo_destino_id
           AND status = 1
         LIMIT 1
    ");

    if ($cat_existe && isset($cat_existe[0]->id)) {
        return array('id' => (int)$cat_existe[0]->id, 'creada' => false, 'nombre' => $cat_info[0]->nombre);
    }

    // ¿Existe global?
    $cat_global = Manimale::find_by_sql("
        SELECT id
          FROM mcategorias
         WHERE UPPER(nombre) = UPPER('$nombre_cat')
           AND sexo = " . (int)$cat_info[0]->sexo . "
           AND mfundos_id = 0
           AND status = 1
         LIMIT 1
    ");

    if ($cat_global && isset($cat_global[0]->id)) {
        return array('id' => (int)$cat_global[0]->id, 'creada' => false, 'nombre' => $cat_info[0]->nombre);
    }

    // No existe → crear copia en destino
    $nueva_cat = new Mcategoria();
    $nueva_cat->nombre             = $cat_info[0]->nombre;
    $nueva_cat->sexo               = (int)$cat_info[0]->sexo;
    $nueva_cat->edadminima         = (int)$cat_info[0]->edadminima;
    $nueva_cat->edadmaxima         = (int)$cat_info[0]->edadmaxima;
    $nueva_cat->id_padre           = (int)$cat_info[0]->id_padre;
    $nueva_cat->mtipoganaderias_id = (int)$cat_info[0]->mtipoganaderias_id;
    $nueva_cat->mfundos_id         = $fundo_destino_id;
    $nueva_cat->user_create        = $usuario_id;
    $nueva_cat->created            = $hoy;
    $nueva_cat->status             = 1;

    if ($nueva_cat->save()) {
        return array('id' => (int)$nueva_cat->id, 'creada' => true, 'nombre' => $cat_info[0]->nombre);
    }

    // Fallback
    return array('id' => (int)$cat_origen_id, 'creada' => false, 'nombre' => $cat_info[0]->nombre);
}