<?php
require_once '../models/Mfundo.php';
require_once '../models/Mfundosxusuario.php';
require_once '../models/Vmusuxfundo.php';
require_once '../models/Musuario.php';

date_default_timezone_set('America/Caracas');

@$action = ($_POST["action"]);
@$id = ($_POST["id_fundo"]);
@$id_fundo = ($_POST["id_fundo"]);
@$record = ($_POST["record"]);
/***********************************/
@$cid_fundo = ($_POST["cid_fundo"]);
@$nombre_fundo = strtoupper($_POST["nombre_fundo"]);
@$tipo_fundo = ($_POST["tipo_fundo"]);
@$pais = ($_POST["pais"]);
@$estado = ($_POST["estado"]);
@$municipio = 0;
@$direccion_fundo = ($_POST["direccion_fundo"]);
@$descripcion_fundo = ($_POST["descripcion_fundo"]);
@$tipo_ganaderia = ($_POST["tipo_ganaderia"]);

if (session_status() === PHP_SESSION_NONE) session_start();

switch ($action) {

    case 'get_active_fundo_header':
        $fundo_activo_id = (int)(isset($_SESSION['mfundos_id']) ? $_SESSION['mfundos_id'] : 0);
        if ($fundo_activo_id <= 0 || empty($_SESSION['idusuariox'])) {
            echo json_encode(array('ok' => false));
            break;
        }

        $fundo_activo = Mfundo::find_by_sql("SELECT f.nombre, tf.nombre AS tipo_fundo
              FROM mfundos f
         LEFT JOIN mtipofundos tf ON tf.id = f.mtipofundos_id
             WHERE f.id = ? AND f.status = 1
             LIMIT 1", array($fundo_activo_id));
        if (!$fundo_activo || !isset($fundo_activo[0])) {
            echo json_encode(array('ok' => false));
            break;
        }

        echo json_encode(array(
            'ok' => true,
            'nombre' => $fundo_activo[0]->nombre,
            'tipo_fundo' => $fundo_activo[0]->tipo_fundo
        ));
        break;

    // =========================================================
    // Listar fundos del usuario
    // =========================================================
    case 'mis_fundos':
        if (empty($_SESSION['idusuariox'])) { echo json_encode(array('error' => 'sesion')); break; }

        @include_once __DIR__ . '/../lib/multifundo_helper.php';

        $uid = (int)$_SESSION['idusuariox'];
        $fundo_activo = (int)(isset($_SESSION['mfundos_id']) ? $_SESSION['mfundos_id'] : 0);

        $usr_arr = Musuario::find('all', array('conditions' => array('id=?', $uid)));
        $max_fundos = 1; $es_multifundo = 0;
        if ($usr_arr && isset($usr_arr[0])) {
            $max_fundos    = (int)$usr_arr[0]->max_fundos;
            $es_multifundo = (int)$usr_arr[0]->es_multifundo;
            if ($max_fundos < 1) $max_fundos = 1;
        }

        $q = @Mfundosxusuario::find_by_sql("
            SELECT
                f.id                  AS mfundos_id,
                f.nombre,
                f.cid_rif,
                f.mtipoganaderias_id,
                f.mplanes_id,
                f.created,
                p.max_reg,
                p.nombre              AS plan_nombre,
                (SELECT COUNT(*) FROM manimales a
                  WHERE a.mfundos_id = f.id AND a.status = 1) AS total_animales,
                (SELECT COUNT(*) FROM mfundosxusuarios fxu2
                  WHERE fxu2.mfundos_id = f.id AND fxu2.status = 1) AS total_usuarios
              FROM mfundosxusuarios fxu
        INNER JOIN mfundos f   ON f.id = fxu.mfundos_id
         LEFT JOIN LATERAL (
                    -- Plan vigente tomado de la suscripción (mplanesxfundo);
                    -- si el fundo no tiene una propia se usa la del
                    -- propietario y, si tampoco, el plan heredado del fundo.
                    SELECT px.mplanes_id
                      FROM mplanesxfundo px
                     WHERE (px.mfundos_id = f.id
                            OR (px.mfundos_id IS NULL AND px.musuarios_id = f.user_propietario))
                       AND px.msuscripciones_id IS NOT NULL
                  ORDER BY px.id DESC
                     LIMIT 1
              ) v ON true
         LEFT JOIN mplanes p   ON p.id = COALESCE(v.mplanes_id, f.mplanes_id)
             WHERE fxu.musuarios_id = $uid
               AND fxu.status = 1
               AND f.status = 1
          ORDER BY f.nombre ASC
        ");

        $out = array();
        if ($q) {
            foreach ($q as $f) {
                $gan = ((int)$f->mtipoganaderias_id === 1) ? 'OVINA'
                     : (((int)$f->mtipoganaderias_id === 2) ? 'CAPRINA' : 'OVINA Y CAPRINA');
                $out[] = array(
                    'id'             => (int)$f->mfundos_id,
                    'nombre'         => $f->nombre,
                    'rif'            => $f->cid_rif,
                    'ganaderia'      => $gan,
                    'plan'           => $f->plan_nombre ? $f->plan_nombre : 'Sin plan',
                    'max_reg'        => (int)$f->max_reg,
                    'total_animales' => (int)$f->total_animales,
                    'total_usuarios' => (int)$f->total_usuarios,
                    'activo'         => ((int)$f->mfundos_id === $fundo_activo)
                );
            }
        }

        $total = count($out);

        echo json_encode(array(
            'es_multifundo' => $es_multifundo,
            'max_fundos'    => $max_fundos,
            'total'         => $total,
            'puede_agregar' => ($total < $max_fundos),
            'restantes'     => max(0, $max_fundos - $total),
            'activo'        => $fundo_activo,
            'fundos'        => $out
        ));
        break;

    // =========================================================
    // Cambiar fundo activo en sesión
    // =========================================================
        case 'cambiar_fundo':
        if (empty($_SESSION['idusuariox'])) {
            http_response_code(401);
            echo json_encode(array('ok' => false, 'msg' => 'Sesión expirada'));
            break;
        }

        @include_once __DIR__ . '/../lib/multifundo_helper.php';

        $uid      = (int)$_SESSION['idusuariox'];
        $fundo_id = (int)(isset($_POST['fundo_id']) ? $_POST['fundo_id'] : 0);

        if (!$fundo_id) { echo json_encode(array('ok' => false, 'msg' => 'Fundo inválido')); break; }

        // El helper blindado actualiza TODAS las variables
        if (function_exists('mf_cargar_fundo_en_sesion') && mf_cargar_fundo_en_sesion($uid, $fundo_id)) {
            echo json_encode(array(
                'ok'            => true,
                'fundo'         => $fundo_id,
                'fundo_nombre'  => $_SESSION['fundo_nombre'],
                'tipo_ganaderia'=> $_SESSION['tipo_ganaderia'],
                'max_reg'       => $_SESSION['maximo_registros']
            ));
        } else {
            http_response_code(403);
            echo json_encode(array('ok' => false, 'msg' => 'No tiene acceso a este fundo'));
        }
        break; 

    // =========================================================
    // MODO SOPORTE — el administrador (rol 1/4) entra a un fundo
    // para revisar y corregir datos. Solo versión web.
    // =========================================================
    case 'activar_supervisor':
        if (empty($_SESSION['idusuariox'])) {
            http_response_code(401);
            echo json_encode(array('ok' => false, 'msg' => 'Sesión expirada'));
            break;
        }

        @include_once __DIR__ . '/../lib/multifundo_helper.php';

        $rol_sesion = isset($_SESSION['rolx']) ? (int)$_SESSION['rolx'] : 0;
        if (!in_array($rol_sesion, array(1, 4), true)) {
            http_response_code(403);
            echo json_encode(array('ok' => false, 'msg' => 'Solo un administrador del sistema puede entrar en modo soporte.'));
            break;
        }

        $fundo_id = (int)(isset($_POST['fundo_id']) ? $_POST['fundo_id'] : 0);
        $motivo   = isset($_POST['motivo']) ? trim((string)$_POST['motivo']) : '';

        if (!function_exists('mf_activar_supervisor') || !mf_activar_supervisor($fundo_id, $motivo)) {
            echo json_encode(array('ok' => false, 'msg' => 'No se pudo activar el modo soporte para ese fundo. Verifique que esté activo.'));
            break;
        }

        echo json_encode(array(
            'ok'             => true,
            'fundo_id'       => $fundo_id,
            'fundo_nombre'   => isset($_SESSION['fundo_nombre']) ? $_SESSION['fundo_nombre'] : '',
            'tipo_ganaderia' => isset($_SESSION['tipo_ganaderia']) ? (int)$_SESSION['tipo_ganaderia'] : 0,
            'expira'         => isset($_SESSION['supervisor_expira']) ? (int)$_SESSION['supervisor_expira'] : 0,
            'msg'            => 'Modo soporte activado. Recuerda salir al terminar la revisión.'
        ));
        break;

    // =========================================================
    // Salir del modo soporte y volver a la sesión de administrador
    // =========================================================
    case 'salir_supervisor':
        if (empty($_SESSION['idusuariox'])) {
            http_response_code(401);
            echo json_encode(array('ok' => false, 'msg' => 'Sesión expirada'));
            break;
        }

        @include_once __DIR__ . '/../lib/multifundo_helper.php';

        if (!function_exists('mf_desactivar_supervisor') || !mf_desactivar_supervisor()) {
            echo json_encode(array('ok' => false, 'msg' => 'No había un modo soporte activo.'));
            break;
        }

        echo json_encode(array(
            'ok'  => true,
            'msg' => 'Saliste del modo soporte.'
        ));
        break;

            // =========================================================
    // DETALLE DE FUNDO — stats para el modal
    // =========================================================
    case 'detalle_fundo':
        if (empty($_SESSION['idusuariox'])) {
            http_response_code(401);
            echo json_encode(array('ok' => false, 'msg' => 'Sesión expirada'));
            break;
        }

        $uid      = (int)$_SESSION['idusuariox'];
        $fundo_id = (int)(isset($_POST['fundo_id']) ? $_POST['fundo_id'] : 0);
        if (!$fundo_id) { echo json_encode(array('ok' => false, 'msg' => 'Fundo inválido')); break; }

        try {
            // Validar acceso del usuario al fundo
            $acceso = Mfundo::find_by_sql("
                SELECT f.id, f.nombre, f.cid_rif, f.mtipoganaderias_id,
                       COALESCE(v.mplanes_id, f.mplanes_id) AS mplanes_id,
                       COALESCE(p.max_reg, 50) AS max_reg,
                       p.nombre AS plan_nombre
                  FROM mfundosxusuarios fxu
            INNER JOIN mfundos f ON f.id = fxu.mfundos_id
             LEFT JOIN LATERAL (
                        SELECT px.mplanes_id
                          FROM mplanesxfundo px
                         WHERE (px.mfundos_id = f.id
                                OR (px.mfundos_id IS NULL AND px.musuarios_id = f.user_propietario))
                           AND px.msuscripciones_id IS NOT NULL
                      ORDER BY px.id DESC
                         LIMIT 1
                  ) v ON true
             LEFT JOIN mplanes p ON p.id = COALESCE(v.mplanes_id, f.mplanes_id)
                 WHERE fxu.musuarios_id = $uid
                   AND fxu.mfundos_id = $fundo_id
                   AND fxu.status = 1
                   AND f.status = 1
                 LIMIT 1
            ");
            if (!$acceso) {
                echo json_encode(array('ok' => false, 'msg' => 'Sin acceso a este fundo'));
                break;
            }
            $f = $acceso[0];

            // Stats generales
            $stats = Mfundo::find_by_sql("
                SELECT 
                    COUNT(*) AS total_animales,
                    SUM(CASE WHEN sexo = 1 THEN 1 ELSE 0 END) AS hembras,
                    SUM(CASE WHEN sexo = 2 THEN 1 ELSE 0 END) AS machos,
                    SUM(CASE WHEN estatus_animal = 2 THEN 1 ELSE 0 END) AS donadores_activos,
                    SUM(CASE WHEN estatus_animal = 4 THEN 1 ELSE 0 END) AS donadoras_activas,
                    SUM(CASE WHEN estatus_animal = 3 THEN 1 ELSE 0 END) AS donadores,
                    SUM(CASE WHEN estatus_animal = 5 THEN 1 ELSE 0 END) AS donadoras
                  FROM manimales
                 WHERE mfundos_id = $fundo_id AND status = 1
            ");
            $s = ($stats && isset($stats[0])) ? $stats[0] : null;

            // Categorías (por categoria_ingreso_id)
            $cats = Mfundo::find_by_sql("
                SELECT COALESCE(c.nombre, 'Sin categoría') AS categoria,
                       COUNT(*) AS cantidad
                  FROM manimales a
             LEFT JOIN mcategorias c ON c.id = a.categoria_ingreso_id
                 WHERE a.mfundos_id = $fundo_id AND a.status = 1
              GROUP BY c.nombre
              ORDER BY cantidad DESC
                 LIMIT 8
            ");

            // Últimos movimientos (si la tabla existe)
            $movs = array();
            try {
                $movs = Mfundo::find_by_sql("
                    SELECT mr.fecha_movimiento, mr.tipo_movimiento, mr.motivo,
                           lo.nombre AS origen, ld.nombre AS destino
                      FROM mmovimientos_rebano mr
                 LEFT JOIN mlotes lo ON lo.id = mr.mlotes_origen_id
                 LEFT JOIN mlotes ld ON ld.id = mr.mlotes_destino_id
                     WHERE mr.mfundos_id = $fundo_id AND mr.status = 1
                  ORDER BY mr.fecha_movimiento DESC
                     LIMIT 3
                ");
            } catch (Exception $e) { $movs = array(); }

            $out_stats = array(
                'total_animales'   => $s ? (int)$s->total_animales : 0,
                'hembras'          => $s ? (int)$s->hembras : 0,
                'machos'           => $s ? (int)$s->machos : 0,
                'donadores_activos'=> $s ? (int)$s->donadores_activos : 0,
                'donadores'        => $s ? (int)$s->donadores : 0,
                'max_reg'          => (int)$f->max_reg
            );

            $out_cats = array();
            if ($cats) {
                foreach ($cats as $c) {
                    $out_cats[] = array(
                        'categoria' => $c->categoria,
                        'cantidad'  => (int)$c->cantidad
                    );
                }
            }

            $out_movs = array();
            if ($movs) {
                foreach ($movs as $m) {
                    $fecha = '';
                    if ($m->fecha_movimiento instanceof DateTime) {
                        $fecha = $m->fecha_movimiento->format('d-m-Y');
                    } elseif ($m->fecha_movimiento) {
                        $fecha = date('d-m-Y', strtotime($m->fecha_movimiento));
                    }
                    $out_movs[] = array(
                        'fecha'  => $fecha,
                        'tipo'   => $m->tipo_movimiento,
                        'motivo' => $m->motivo,
                        'origen' => $m->origen,
                        'destino'=> $m->destino
                    );
                }
            }

            // Ganadería legible
            $gan = ((int)$f->mtipoganaderias_id === 1) ? 'OVINA'
                 : (((int)$f->mtipoganaderias_id === 2) ? 'CAPRINA' : 'OVINA Y CAPRINA');

            echo json_encode(array(
                'ok'     => true,
                'fundo'  => array(
                    'id'         => (int)$f->id,
                    'nombre'     => $f->nombre,
                    'rif'        => $f->cid_rif,
                    'ganaderia'  => $gan,
                    'plan'       => $f->plan_nombre ? $f->plan_nombre : 'Sin plan',
                    'max_reg'    => (int)$f->max_reg
                ),
                'stats'  => $out_stats,
                'categorias' => $out_cats,
                'movimientos' => $out_movs
            ));

        } catch (Exception $e) {
            echo json_encode(array('ok' => false, 'msg' => 'Error al consultar: ' . $e->getMessage()));
        }
        break;
        
        // =========================================================
    // ADD — con validación de duplicado POR USUARIO
    // =========================================================
        case 'add':
        if ($cid_fundo == "") {
            $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
                <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                <h4><i class="icon fa fa-warning"></i> Alerta!</h4>
                Ingrese el CID del fundo a Registrar.
                </div>');
            echo json_encode($respuesta);
            break;
        }

        if (session_status() === PHP_SESSION_NONE) session_start();
        $uid_add = (int)$_SESSION['idusuariox'];
        @include_once __DIR__ . '/../lib/multifundo_helper.php';

        // Validar cuota
        $usr_arr = Musuario::find('all', array('conditions' => array('id=?', $uid_add)));
        $max_f = 1;
        if ($usr_arr && isset($usr_arr[0])) {
            $max_f = (int)$usr_arr[0]->max_fundos;
            if ($max_f < 1) $max_f = 1;
        }
        if (mf_contar_fundos_usuario($uid_add) >= $max_f) {
            echo json_encode(array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
                <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                <h4><i class="icon fa fa-ban"></i> Alerta!</h4>
                Ha alcanzado el máximo de <strong>' . $max_f . '</strong> fundos permitidos.
                </div>'));
            break;
        }

        // Validar nombre / CID duplicado POR USUARIO
        // El nombre solo bloquea si el CID/RIF también coincide: al unificar
        // cuentas es normal juntar dos fincas homónimas (misma persona con
        // ganadería de distinta especie o en otro sitio) y el identificador
        // real es el CID/RIF.
        $nom_esc = addslashes($nombre_fundo);
        $cid_esc = addslashes($cid_fundo);
        $dup_nom = Mfundo::find_by_sql("SELECT id FROM mfundos 
            WHERE nombre='$nom_esc' AND cid_rif='$cid_esc' AND user_propietario=$uid_add AND status=1 LIMIT 1");
        $dup_cid = Mfundo::find_by_sql("SELECT id FROM mfundos 
            WHERE cid_rif='$cid_esc' AND user_propietario=$uid_add AND status=1 LIMIT 1");

        if (!empty($dup_nom)) {
            echo json_encode(array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
                <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                <h4><i class="icon fa fa-ban"></i> Alerta!</h4>
                Ya tiene un fundo con el nombre <strong>[' . $nombre_fundo . ']</strong> y el mismo CID/RIF.
                </div>'));
            break;
        }
        if (!empty($dup_cid)) {
            echo json_encode(array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
                <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                <h4><i class="icon fa fa-ban"></i> Alerta!</h4>
                Ya tiene un fundo con el CID/RIF <strong>[' . $cid_fundo . ']</strong>.
                </div>'));
            break;
        }

        // Crear fundo
        $hoy = date('d-m-Y');
        $reg_fundo = new Mfundo();
        $reg_fundo->cid_rif = $cid_fundo;
        $reg_fundo->nombre = $nombre_fundo;
        $reg_fundo->mtipofundos_id = $tipo_fundo;
        $reg_fundo->mpaises_id = $pais;
        $reg_fundo->mestados_id = $estado;
        $reg_fundo->mmunicipios_id = $municipio;
        $reg_fundo->direccion = $direccion_fundo;
        $reg_fundo->descripcion = $descripcion_fundo;
        $reg_fundo->mtipoganaderias_id = $tipo_ganaderia;
        $reg_fundo->user_propietario = $uid_add;
        $reg_fundo->user_create = $uid_add;
        $reg_fundo->created = $hoy;
        if ($usr_arr && isset($usr_arr[0])) {
            $mp = (int)$usr_arr[0]->mplanes_id;
            if ($mp > 0) $reg_fundo->mplanes_id = $mp;
        }

        if (!$reg_fundo->save()) {
            echo json_encode(array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
                <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                <h4><i class="icon fa fa-ban"></i> Alerta!</h4>
                Error al registrar los datos.
                </div>'));
            break;
        }

        $cod_fundo = (int)$reg_fundo->id;

        // Vincular con usuario
        $vinc = new Mfundosxusuario();
        $vinc->musuarios_id = $uid_add;
        $vinc->mfundos_id = $cod_fundo;
        $vinc->user_create = $uid_add;

        if (!$vinc->save()) {
            echo json_encode(array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
                <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                <h4><i class="icon fa fa-ban"></i> Alerta!</h4>
                Error al vincular el fundo.
                </div>'));
            break;
        }

        // ⭐ CRÍTICO: usar el helper blindado para setear TODAS las variables de sesión
        if (function_exists('mf_cargar_fundo_en_sesion')) {
            mf_cargar_fundo_en_sesion($uid_add, $cod_fundo);
        } else {
            // Fallback: al menos lo básico
            $_SESSION['mfundos_id'] = $cod_fundo;
            $_SESSION['tipo_ganaderia'] = (int)$tipo_ganaderia;
        }

        echo json_encode(array('resultado' => 'ok', 'mensaje' => '<div class="alert alert-success alert-dismissable">
            <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
            <h4><i class="fa fa-check"></i> Alerta!</h4>
            Fundo registrado exitosamente!.
            </div>'));
        break;

    // =========================================================
    // EDIT — con validación de duplicado excluyendo el actual
    // =========================================================
    case 'edit':
        if ($cid_fundo == "") {
            $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
                <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                <h4><i class="icon fa fa-warning"></i> Alerta!</h4>
                Ingrese el Código de Identificación del Fundo.
                </div>');
        } else {
            if (session_status() === PHP_SESSION_NONE) session_start();
            $uid = (int)$_SESSION['idusuariox'];
            $id_fundo_int = (int)$id_fundo;

            // Validar que el fundo pertenece al usuario
            $own = Mfundo::find('all', array('conditions' => array('id=? AND user_propietario=? AND status=1', $id_fundo_int, $uid)));
            if (!$own) {
                $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
                    <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                    <h4><i class="icon fa fa-ban"></i> Alerta!</h4>
                    No tiene permiso para editar este fundo.
                    </div>');
                echo json_encode($respuesta);
                break;
            }

            // Validar nombre duplicado (excluyendo el actual). Solo bloquea
            // junto con el CID/RIF: dos fincas homónimas con distinto RIF son
            // válidas dentro de una misma cuenta unificada.
            $nom_esc = addslashes($nombre_fundo);
            $dup_nom = Mfundo::find_by_sql("SELECT id FROM mfundos 
                WHERE nombre='$nom_esc' 
                  AND cid_rif='" . addslashes($cid_fundo) . "'
                  AND user_propietario=$uid 
                  AND id<>$id_fundo_int
                  AND status=1 LIMIT 1");

            // Validar CID duplicado (excluyendo el actual)
            $cid_esc = addslashes($cid_fundo);
            $dup_cid = Mfundo::find_by_sql("SELECT id FROM mfundos 
                WHERE cid_rif='$cid_esc' 
                  AND user_propietario=$uid 
                  AND id<>$id_fundo_int
                  AND status=1 LIMIT 1");

            if (!empty($dup_nom)) {
                $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
                    <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                    <h4><i class="icon fa fa-ban"></i> Alerta!</h4>
                    Ya tiene otro fundo con el nombre <strong>[' . $nombre_fundo . ']</strong> y el mismo CID/RIF.
                    </div>');
            } elseif (!empty($dup_cid)) {
                $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
                    <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                    <h4><i class="icon fa fa-ban"></i> Alerta!</h4>
                    Ya tiene otro fundo con el CID/RIF <strong>[' . $cid_fundo . ']</strong>.
                    </div>');
            } else {
                $hoy = date('d-m-Y');

                $reg_fundo = Mfundo::find($id_fundo_int);
                $reg_fundo->cid_rif = $cid_fundo;
                $reg_fundo->nombre = $nombre_fundo;
                $reg_fundo->mtipofundos_id = $tipo_fundo;
                $reg_fundo->mpaises_id = $pais;
                $reg_fundo->mestados_id = $estado;
                $reg_fundo->mmunicipios_id = $municipio;
                $reg_fundo->direccion = $direccion_fundo;
                $reg_fundo->descripcion = $descripcion_fundo;
                $reg_fundo->user_modify = $uid;
                $reg_fundo->updated = $hoy;

                if ($reg_fundo->save()) {
                    $respuesta = array('resultado' => 'ok', 'mensaje' => '<div class="alert alert-success alert-dismissable">
                        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                        <h4><i class="fa fa-check"></i> Alerta!</h4>
                        Los datos han sido actualizados exitosamente!.
                        </div>');
                } else {
                    $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
                        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                        <h4><i class="icon fa fa-ban"></i> Alerta!</h4>
                        Error al actualizar los datos.
                        </div>');
                }
            }
        }
        echo json_encode($respuesta);
        break;

    // =========================================================
    // DELETE
    // =========================================================
    case 'delete':
        if ($record != null) {
            $usuario_id = (int)$_SESSION['idusuariox'];
            $rol = (int)$_SESSION['rolx'];
            $hoy = date("d-m-Y");

            if ($usuario_id != "" && (($rol == 1) || ($rol == 4))) {
                $f = Mfundo::find($record);
                $f->user_modify = $usuario_id;
                $f->updated = $hoy;
                $f->status = 0;

                if ($f->save()) {
                    echo '<div class="alert alert-success alert-dismissable">
                        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                        <h4><i class="fa fa-check"></i> Alerta!</h4>
                        Registro Eliminado exitosamente...
                        </div>';
                } else {
                    echo '<div class="alert alert-danger alert-dismissable">
                        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                        <h4><i class="icon fa fa-ban"></i> Alerta!</h4>
                        Error al eliminar el registro.
                        </div>';
                }
            } else {
                echo '<div class="alert alert-warning alert-dismissable">
                    <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                    <h4><i class="icon fa fa-warning"></i> Alerta!</h4>
                    Usted no puede realizar esta transacción.
                    </div>';
            }
        }
        break;

    // =========================================================
    // SEARCH (devuelve datos del fundo)
    // =========================================================
    case 'search':
        if ($record != null) {
            $data = Mfundo::find('all', array('conditions' => array('id=?', $record)));
            if ($data != null) {
                foreach ($data as $rs) {
                    $resp = array(
                        "id"                => $rs->id,
                        "tipo_ganaderia"    => $rs->mtipoganaderias_id,
                        "pais"              => $rs->mpaises_id,
                        "estado"            => $rs->mestados_id,
                        "cid_fundo"         => $rs->cid_rif,
                        "nombre_fundo"      => $rs->nombre,
                        "tipo_fundo"        => $rs->mtipofundos_id,
                        "direccion_fundo"   => $rs->direccion,
                        "descripcion_fundo" => $rs->descripcion
                    );
                }
                echo json_encode($resp);
            }
        }
        break;

    // =========================================================
    // GET FUNDOS (admin)
    // =========================================================
    case 'get_fundos':
        $data = Mfundo::find('all', array('conditions' => array('status=?', 1), 'order' => 'nombre asc'));
        if ($data != null) {
            foreach ($data as $rs) {
                $resp .= '<option value="' . $rs->id . '">' . $rs->nombre . '</option>';
            }
        } else {
            $resp = '<option value="">No hay Fundos para Agregar</option>';
        }
        echo $resp;
        break;

    // =========================================================
    // GET FUNDOS ENCARGADO
    // =========================================================
    case 'get_fundos_encargado':
        $data = Vmusuxfundo::find_by_sql("SELECT id, (cid_rif || '-- '|| nombre) as nombre FROM Vmusuxfundos ;");
        if ($data != null) {
            foreach ($data as $rs) {
                $resp .= '<option value="' . $rs->id . '">' . $rs->nombre . '</option>';
            }
        } else {
            $resp = '<option value="">No hay Fundos para Agregar</option>';
        }
        echo $resp;
        break;

    // =========================================================
    // UNIFICAR FUNDOS EN UN USUARIO  (solo Administrador / Super Usuario)
    // ---------------------------------------------------------
    // Da acceso a un usuario DESTINO sobre varios fundos (dejándolo como
    // multifundo) y permite retirar el acceso a las cuentas sobrantes.
    // NO mueve datos de negocio: los registros siguen colgados de su fundo.
    // =========================================================

    case 'unificar_candidatos':
        unificar_requiere_admin();
        @include_once __DIR__ . '/../lib/multifundo_helper.php';
        $grupos = mf_grupos_usuarios_duplicados();
        // Normalizar los fundos de cada usuario al formato que consume el front
        foreach ($grupos as $gi => $g) {
            foreach ($g['usuarios'] as $ui => $uu) {
                $grupos[$gi]['usuarios'][$ui]['fundos'] = unificar_fundos_json($uu['fundos']);
            }
        }
        echo json_encode(array('ok' => true, 'grupos' => $grupos));
        break;

    case 'unificar_buscar_usuario':
        unificar_requiere_admin();
        @include_once __DIR__ . '/../lib/multifundo_helper.php';
        $out = array();
        foreach (mf_buscar_usuarios(isset($_POST['q']) ? $_POST['q'] : '', 25) as $u) {
            $out[] = array(
                'id'       => (int) $u['id'],
                'username' => $u['username'],
                'name'     => $u['name'],
                'email'    => $u['email'],
                'fundos'   => count(mf_fundos_para_unificar((int) $u['id'])),
            );
        }
        echo json_encode(array('ok' => true, 'usuarios' => $out));
        break;

    case 'unificar_candidatos_fundos':
        // Usuarios involucrados en los fundos marcados: propietarios y quienes
        // tienen acceso activo. Es la lista cerrada para elegir el destino.
        unificar_requiere_admin();
        @include_once __DIR__ . '/../lib/multifundo_helper.php';
        $ids_cand = unificar_parse_ids(isset($_POST['fundos']) ? $_POST['fundos'] : '');
        if (!$ids_cand) {
            echo json_encode(array('ok' => false, 'msg' => 'Debe marcar al menos un fundo en la tabla.'));
            break;
        }
        echo json_encode(array('ok' => true, 'candidatos' => mf_usuarios_involucrados_fundos($ids_cand)));
        break;

    case 'unificar_fundos_usuario':
        unificar_requiere_admin();
        @include_once __DIR__ . '/../lib/multifundo_helper.php';
        $uid = (int) (isset($_POST['usuario_id']) ? $_POST['usuario_id'] : 0);
        $u   = _mf_q1("SELECT id, username, name, email, es_multifundo, max_fundos
                         FROM musuarios WHERE id = $uid LIMIT 1");
        if (!$u) {
            echo json_encode(array('ok' => false, 'msg' => 'Usuario no encontrado.'));
            break;
        }
        echo json_encode(array(
            'ok'      => true,
            'usuario' => array(
                'id'            => (int) $u['id'],
                'username'      => $u['username'],
                'name'          => $u['name'],
                'email'         => $u['email'],
                'es_multifundo' => (int) $u['es_multifundo'],
                'max_fundos'    => (int) $u['max_fundos'],
            ),
            'fundos'  => unificar_fundos_json(mf_fundos_para_unificar($uid)),
        ));
        break;

    case 'unificar_buscar_fundos':
        unificar_requiere_admin();
        @include_once __DIR__ . '/../lib/multifundo_helper.php';
        $safe = addslashes((string) (isset($_POST['q']) ? $_POST['q'] : ''));
        $w    = ($safe === '') ? '' : " AND (f.nombre ILIKE '%$safe%' OR f.cid_rif ILIKE '%$safe%' OR u2.username ILIKE '%$safe%') ";
        $rows = _mf_q("
            SELECT f.id AS mfundos_id, f.nombre, f.cid_rif, f.mtipoganaderias_id,
                   f.user_propietario, u2.username AS propietario_username,
                   COALESCE(v.mplanes_id, f.mplanes_id) AS mplanes_id,
                   p.nombre AS plan_nombre, p.max_reg,
                   (SELECT COUNT(*) FROM manimales a
                     WHERE a.mfundos_id = f.id AND a.status = 1) AS animales
              FROM mfundos f
         LEFT JOIN LATERAL (
                    SELECT px.mplanes_id
                      FROM mplanesxfundo px
                     WHERE px.mfundos_id = f.id
                       AND px.msuscripciones_id IS NOT NULL
                  ORDER BY px.id DESC
                     LIMIT 1
              ) v ON true
         LEFT JOIN mplanes p   ON p.id = COALESCE(v.mplanes_id, f.mplanes_id)
         LEFT JOIN musuarios u2 ON u2.id = f.user_propietario
             WHERE f.status = 1 $w
          ORDER BY f.nombre ASC
             LIMIT 200
        ");
        echo json_encode(array('ok' => true, 'fundos' => unificar_fundos_json($rows)));
        break;

    case 'unificar_preview':
        unificar_requiere_admin();
        @include_once __DIR__ . '/../lib/multifundo_helper.php';
        $prev = unificar_resumen(
            (int) (isset($_POST['destino']) ? $_POST['destino'] : 0),
            unificar_parse_ids(isset($_POST['fundos']) ? $_POST['fundos'] : ''),
            array('desactivar' => !empty($_POST['desactivar']))
        );
        if (isset($prev['error'])) {
            echo json_encode(array('ok' => false, 'msg' => $prev['error']));
            break;
        }
        if (!empty($_POST['solo_involucrados']) && !unificar_destino_involucrado($prev['destino']['id'], $prev['fundo_ids'])) {
            echo json_encode(array('ok' => false, 'msg' => 'El usuario destino debe ser uno de los usuarios involucrados en los fundos marcados.'));
            break;
        }
        unset($prev['fundo_ids']);
        echo json_encode(array('ok' => true) + $prev);
        break;

    case 'unificar_aplicar':
        unificar_requiere_admin();
        @include_once __DIR__ . '/../lib/multifundo_helper.php';
        $resumen = unificar_resumen(
            (int) (isset($_POST['destino']) ? $_POST['destino'] : 0),
            unificar_parse_ids(isset($_POST['fundos']) ? $_POST['fundos'] : ''),
            array('desactivar' => !empty($_POST['desactivar']))
        );
        if (isset($resumen['error'])) {
            echo json_encode(array('ok' => false, 'msg' => $resumen['error']));
            break;
        }
        if (!empty($_POST['solo_involucrados']) && !unificar_destino_involucrado($resumen['destino']['id'], $resumen['fundo_ids'])) {
            echo json_encode(array('ok' => false, 'msg' => 'El usuario destino debe ser uno de los usuarios involucrados en los fundos marcados.'));
            break;
        }
        $res = mf_unificar_fundos(
            (int) $resumen['destino']['id'],
            $resumen['fundo_ids'],
            array_map(function ($o) { return (int) $o['id']; }, $resumen['origenes']),
            array(
                'desactivar_origenes'  => !empty($_POST['desactivar']),
                'transferir_propiedad' => !empty($_POST['propiedad']),
            )
        );
        echo json_encode(array(
            'ok'        => $res['ok'],
            'msg'       => $res['ok'] ? 'Unificación aplicada correctamente.' : implode(' ', $res['errores']),
            'resultado' => $res,
        ));
        break;

}// end switch

/* ============================================================
   Helpers de la acción "unificar fundos"
   ============================================================ */

/** Restringe la acción a Administrador (1) / Super Usuario (4). */
function unificar_requiere_admin() {
    $rol = isset($_SESSION['rolx']) ? (int) $_SESSION['rolx'] : 0;
    if (!in_array($rol, array(1, 4), true)) {
        http_response_code(403);
        echo json_encode(array('ok' => false, 'msg' => 'Acceso restringido al administrador.'));
        exit;
    }
}

/**
 * Verdadero si el usuario destino es uno de los INVOLUCRADOS en los fundos
 * (propietario o con acceso activo). Se usa cuando el front exige elegir
 * únicamente entre los usuarios de la transacción.
 */
function unificar_destino_involucrado($destino, array $fundo_ids) {
    @include_once __DIR__ . '/../lib/multifundo_helper.php';
    foreach (mf_usuarios_involucrados_fundos($fundo_ids) as $c) {
        if ((int) $c['id'] === (int) $destino) { return true; }
    }
    return false;
}

/** Convierte "1,2,3" (o un array) en una lista de ids positivos únicos. */
function unificar_parse_ids($valor) {
    $partes = is_array($valor) ? $valor : explode(',', (string) $valor);
    $tmp = array();
    foreach ($partes as $p) {
        $p = (int) trim($p);
        if ($p > 0) { $tmp[$p] = true; }
    }
    return array_keys($tmp);
}

/** Normaliza el listado de fundos para el front. */
function unificar_fundos_json($rows) {
    $out = array();
    foreach ($rows as $f) {
        $out[] = array(
            'id'          => (int) $f['mfundos_id'],
            'nombre'      => $f['nombre'],
            'rif'         => $f['cid_rif'],
            'ganaderia'   => ((int) $f['mtipoganaderias_id'] === 1) ? 'OVINA'
                           : (((int) $f['mtipoganaderias_id'] === 2) ? 'CAPRINA' : 'OVINA Y CAPRINA'),
            'animales'    => (int) $f['animales'],
            'propietario' => $f['propietario_username'] ? $f['propietario_username'] : '',
            'plan_id'     => isset($f['mplanes_id']) ? (int) $f['mplanes_id'] : 0,
            'plan'        => isset($f['plan_nombre']) ? $f['plan_nombre'] : '',
            'max_reg'     => isset($f['max_reg']) ? (int) $f['max_reg'] : 0,
        );
    }
    return $out;
}

/**
 * Calcula qué cambiaría al unificar: fundos válidos y usuarios origen que
 * perderán el acceso (los que hoy tienen acceso activo a esos fundos).
 *
 * $opciones['desactivar'] refleja la casilla «inhabilitar las cuentas que
 * queden sin fundos». El resumen informa además qué plan quedará vigente y
 * qué cuentas van a quedar inactivas, para que el usuario lo confirme.
 */
function unificar_resumen($destino, array $fundo_ids, array $opciones = array()) {
    $destino    = (int) $destino;
    $desactivar = !empty($opciones['desactivar']);
    $u = _mf_q1("SELECT id, username, name, email, mplanes_id FROM musuarios WHERE id = $destino AND status = 1 LIMIT 1");
    if (!$u) {
        return array('error' => 'El usuario destino no existe o está inactivo.');
    }
    if (!$fundo_ids) {
        return array('error' => 'Debe seleccionar al menos un fundo.');
    }

    $fundos   = array();
    $origenes = array();
    $errores  = array();
    $planes   = array((int) $u['mplanes_id']);

    foreach ($fundo_ids as $fid) {
        $fid = (int) $fid;
        $f = _mf_q1("SELECT id, nombre, mtipoganaderias_id FROM mfundos WHERE id = $fid AND status = 1 LIMIT 1");
        if (!$f) {
            $errores[] = "El fundo $fid no existe o está inactivo.";
            continue;
        }
        $plan_id  = function_exists('mf_plan_vigente_fundo') ? mf_plan_vigente_fundo($fid) : 0;
        $plan     = function_exists('mf_plan_detalle') ? mf_plan_detalle($plan_id) : array('id' => 0, 'nombre' => '', 'max_reg' => 0);
        $planes[] = $plan_id;
        $fundos[] = array(
            'id'        => $fid,
            'nombre'    => $f['nombre'],
            'ganaderia' => ((int) $f['mtipoganaderias_id'] === 1) ? 'OVINA'
                         : (((int) $f['mtipoganaderias_id'] === 2) ? 'CAPRINA' : 'OVINA Y CAPRINA'),
            'plan_id'   => $plan['id'],
            'plan'      => $plan['nombre'],
            'max_reg'   => $plan['max_reg'],
        );
        $usrs = _mf_q("
            SELECT u.id, u.username, u.name
              FROM mfundosxusuarios fxu
        INNER JOIN musuarios u ON u.id = fxu.musuarios_id
             WHERE fxu.mfundos_id = $fid
               AND fxu.status = 1
               AND fxu.musuarios_id <> $destino
          ORDER BY u.username
        ");
        foreach ($usrs as $x) {
            $origenes[(int) $x['id']] = array(
                'id'       => (int) $x['id'],
                'username' => $x['username'],
                'name'     => $x['name'],
            );
        }

        /* El propietario también cuenta como origen aunque ya haya perdido el
           acceso: si el fundo le quedó a nombre suyo, hay que transferirlo y
           la cuenta debe quedar inhabilitada. Sin esto, volver a ejecutar la
           unificación sobre un estado a medias no terminaba de arreglarlo. */
        $prop = _mf_q("
            SELECT u.id, u.username, u.name
              FROM mfundos f
        INNER JOIN musuarios u ON u.id = f.user_propietario
             WHERE f.id = $fid
               AND f.user_propietario IS NOT NULL
               AND f.user_propietario <> $destino
        ");
        foreach ($prop as $x) {
            if (!isset($origenes[(int) $x['id']])) {
                $origenes[(int) $x['id']] = array(
                    'id'       => (int) $x['id'],
                    'username' => $x['username'],
                    'name'     => $x['name'],
                );
            }
        }
    }

    if (!$fundos) {
        return array('error' => 'Ninguno de los fundos seleccionados es válido.');
    }

    /* Plan resultante: el de mayor cobertura entre los fundos de la
       transacción, los que el destino ya tenía y su propia ficha. Unificar
       nunca debe degradar el plan de la cuenta. */
    foreach (mf_fundos_para_unificar($destino) as $ft) {
        $planes[] = function_exists('mf_plan_vigente_fundo') ? mf_plan_vigente_fundo((int) $ft['mfundos_id']) : 0;
    }
    $plan_mayor = function_exists('mf_plan_mayor') ? mf_plan_mayor($planes) : 0;
    $detalle    = function_exists('mf_plan_detalle') ? mf_plan_detalle($plan_mayor) : array('id' => 0, 'nombre' => '', 'max_reg' => 0);

    /* Qué pasa con cada cuenta origen: si le quedan fundos fuera de la
       transacción y si la casilla «desactivar» la inhabilita igualmente. */
    $huerfanos = array();
    foreach ($origenes as $oid => $o) {
        $restantes = array();
        foreach (mf_fundos_para_unificar($oid) as $fx) {
            if (!in_array((int) $fx['mfundos_id'], $fundo_ids, true)) {
                $restantes[] = array('id' => (int) $fx['mfundos_id'], 'nombre' => $fx['nombre']);
            }
        }
        $origenes[$oid]['fundos_restantes'] = count($restantes);
        $origenes[$oid]['quedara_inactivo'] = (count($restantes) === 0 || $desactivar) ? 1 : 0;
        if ($origenes[$oid]['quedara_inactivo'] && count($restantes) > 0) {
            foreach ($restantes as $r) {
                $huerfanos[] = array('usuario' => $o['username'], 'fundo' => $r['id'], 'nombre' => $r['nombre']);
            }
        }
    }

    return array(
        'destino'   => array(
            'id'       => (int) $u['id'],
            'username' => $u['username'],
            'name'     => $u['name'],
            'email'    => $u['email'],
        ),
        'fundos'    => $fundos,
        'origenes'  => array_values($origenes),
        'plan'      => $detalle,
        'huerfanos' => $huerfanos,
        'fundo_ids' => array_map(function ($f) { return (int) $f['id']; }, $fundos),
        'errores'   => $errores,
    );
}
?>