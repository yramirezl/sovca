<?php
require_once '../models/Mdesincorporacionanimale.php';
require_once '../models/Vdesincorporacione.php';
require_once '../models/Manimale.php';
require_once '../models/MmigracionAnimale.php';
require_once '../models/Vmfichaxanimale.php';
require_once '../models/Mregnacimiento.php';
require_once '../models/Mdsinficha.php';
require_once '../models/Mestatusganado.php';


include '../lib/sanitizar.php';

date_default_timezone_set('America/Caracas');

@$action = ($_POST['action']);
@$id = ($_POST['id_causadescarte']);

@$record = ($_POST['record']);
/***********************************/
@$causa_mortalidad_animal = ($_POST['causa_mortalidad_animal']);
@$causa_descarte_animal = ($_POST['causa_descarte_animal']);
@$fecha_desincorporacion = ($_POST['fecha_desincorporacion']);
@$causa_descarte_animal = ($_POST['causa_descarte_animal']);
@$cod_animal = ($_POST['cod_animal']);
@$observaciones = ($_POST['observaciones']);
@$tipo_des = ($_POST['tipo_des']);
@$rgb_animal = ($_POST['rgb_animal']);
@$id_desincorporacion = ($_POST['id_desincorporacion']);

/**********************************/

switch ($action) {

    //// **************************


    case 'add':

        if ($tipo_des == '') {

            $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
        <h4>
        <i class="icon fa fa-warning"></i>
        Alerta!
        </h4>
        Ingrese La Causa de la Desincorporación del Animal.
        </div>');
        } else  if ($fecha_desincorporacion == '') {

            $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
        <h4>
        <i class="icon fa fa-warning"></i>
        Alerta!
        </h4>
        Debe Ingresar la Fecha de la Desincorporación del Animal.
        </div>');
        } else {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }

            if (!desincorporacion_permite('registrar')) {
                echo json_encode(respuesta_alerta(desincorporacion_sin_permiso('registrar')));
                break;
            }

            $usuario_id = (int)$_SESSION['idusuariox'];
            $mfundos_id = (int)$_SESSION['mfundos_id'];
            $t_ganaderia = (int)$_SESSION['tipo_ganaderia'];

            $cod_animal = trim((string)$cod_animal);

            // (A) Validaciones previas. Se busca SOLO la baja activa
            // (status = 1): los registros históricos anulados no deben
            // impedir volver a desincorporar al mismo animal.
            $error_validacion = '';
            $reg_animal = null;

            $animales = Manimale::find('all', array(
                'conditions' => array('mfundos_id=? AND codigo_rgb_id_animal=? AND status=1', $mfundos_id, $cod_animal),
                'order' => 'id DESC',
                'limit' => 1
            ));
            if ($animales && isset($animales[0])) {
                $reg_animal = $animales[0];
            }

            $bajas_activas = Mdesincorporacionanimale::find('all', array(
                'conditions' => array('mfundos_id=? AND codigo_rgb_id_animal=? AND status=1', $mfundos_id, $cod_animal),
                'order' => 'id DESC',
                'limit' => 1
            ));
            $baja_activa = ($bajas_activas && isset($bajas_activas[0])) ? $bajas_activas[0] : null;

            if ($cod_animal === '') {
                $error_validacion = 'Debe indicar el animal a desincorporar.';
            } elseif (!$reg_animal) {
                $error_validacion = 'El animal <strong>' . htmlspecialchars($cod_animal, ENT_QUOTES, 'UTF-8') . '</strong> no existe o está inactivo en este fundo.';
            } elseif ($baja_activa) {
                $fecha_baja = '';
                if ($baja_activa->f_desincorporacion != null) {
                    $fecha_baja = $baja_activa->f_desincorporacion->format('d-m-Y');
                }
                $error_validacion = 'El animal <strong>' . htmlspecialchars($cod_animal, ENT_QUOTES, 'UTF-8') . '</strong> ya tiene una desincorporación activa'
                    . ($fecha_baja !== '' ? ' del ' . $fecha_baja : '')
                    . '. Para volver a desincorporarlo, primero anule ese registro en el listado.';
            } elseif ((int)$reg_animal->mestatus_id === 7) {
                $error_validacion = 'El animal <strong>' . htmlspecialchars($cod_animal, ENT_QUOTES, 'UTF-8') . '</strong> fue migrado a otro fundo y no se puede desincorporar aquí.';
            } elseif ((int)$reg_animal->mestatus_id !== 1) {
                $error_validacion = 'El animal <strong>' . htmlspecialchars($cod_animal, ENT_QUOTES, 'UTF-8') . '</strong> está en estado <strong>'
                    . htmlspecialchars(estatus_animal_nombre($reg_animal->mestatus_id), ENT_QUOTES, 'UTF-8')
                    . '</strong>. Solo se pueden desincorporar animales ACTIVOS.';
            }

            if ($error_validacion !== '') {
                $respuesta = respuesta_alerta($error_validacion);
            } else {
                // (B) Registrar la baja y cambiar el estado del animal.
                $crias_pendientes = Mregnacimiento::find_by_sql(
                    "SELECT id_ternero, codigo_ternero
                       FROM Mregnacimientos
                      WHERE mfundos_id = ?
                        AND codigo_rgb_id_animal_hembra = ?
                        AND status = 1
                        AND f_parto IS NOT NULL
                        AND estatus_nacido = 1
                        AND mestatus_id = 1
                        AND fichado = FALSE
                      ORDER BY f_parto, id",
                    array((int)$mfundos_id, (string)$cod_animal)
                );

                if ($crias_pendientes) {
                    $codigos_crias = array();
                    foreach ($crias_pendientes as $cria) {
                        $codigo_cria = trim((string)$cria->id_ternero);
                        if ($codigo_cria === '') {
                            $codigo_cria = trim((string)$cria->codigo_ternero);
                        }
                        $codigos_crias[] = htmlspecialchars($codigo_cria, ENT_QUOTES, 'UTF-8');
                    }
                    $respuesta = array(
                        'resultado' => 'error',
                        'mensaje' => '<div class="alert alert-warning"><strong>No se puede desincorporar esta hembra.</strong> Tiene crías registradas como vivas y activas que aún no tienen ficha: ' . implode(', ', $codigos_crias) . '. Si siguen vivas, fiche cada cría; si murieron o ya no deben estar activas, actualice su estado en <a href="../nacimientos/">Nacimientos</a> y vuelva a intentar.</div>'
                    );
                    echo json_encode($respuesta);
                    break;
                }

                $hoy = date('d-m-Y');
                $conexion = Mdesincorporacionanimale::connection();
                $conexion->transaction();

                try {
                    if ($baja_previa) {
                        /* Se reutiliza el registro anulado: no se tocan
                           created ni user_create (histórico original). */
                        $reg_desincorporacion = Mdesincorporacionanimale::find($baja_previa->id);
                        $reg_desincorporacion->status = 1;
                        $reg_desincorporacion->user_modify = $usuario_id;
                        $reg_desincorporacion->updated = $hoy;
                    } else {
                        $reg_desincorporacion = new Mdesincorporacionanimale();
                        $reg_desincorporacion->user_create = $usuario_id;
                        $reg_desincorporacion->created = $hoy;
                    }

                    $reg_desincorporacion->codigo_rgb_id_animal = $cod_animal;
                    $reg_desincorporacion->tipo_desincorporacion = $tipo_des;
                    $reg_desincorporacion->f_desincorporacion = $fecha_desincorporacion;

                    $reg_desincorporacion->mfundos_id = $mfundos_id;
                    $reg_desincorporacion->causa_descarte = $causa_descarte_animal;
                    $reg_desincorporacion->causa_mortalidad = $causa_mortalidad_animal;
                    $reg_desincorporacion->observaciones = $observaciones;

                    // Estado que tenía el animal antes de la baja: se usa para
                    // devolverlo exactamente a ese estado cuando se anule.
                    if (array_key_exists('mestatus_anterior', $reg_desincorporacion->attributes())) {
                        $reg_desincorporacion->mestatus_anterior = (int)$reg_animal->mestatus_id;
                    }

                    if (!$reg_desincorporacion->save()) {
                        throw new Exception('No se pudo guardar la desincorporación.');
                    }

                    $reg_animal->mestatus_id = (int)$tipo_des;
                    $reg_animal->user_modify = $usuario_id;
                    $reg_animal->updated = $hoy;

                    if (!$reg_animal->save()) {
                        throw new Exception('No se pudo actualizar el estado del animal.');
                    }

                    $conexion->commit();
                    $respuesta = respuesta_alerta('Los datos han sido registrados exitosamente.', 'success');
                } catch (Throwable $e) {
                    $conexion->rollback();

                    /* El detalle técnico va al log; al usuario se le muestra
                       un mensaje entendible (antes se volcaba el SQL completo). */
                    error_log('[desincorporacion] add ' . $cod_animal . ': ' . $e->getMessage());

                    $detalle = $e->getMessage();
                    if (stripos($detalle, 'duplicate key') !== false || strpos($detalle, '23505') !== false) {
                        $texto_error = 'El animal <strong>' . htmlspecialchars($cod_animal, ENT_QUOTES, 'UTF-8') . '</strong> ya tiene un registro de desincorporación en el sistema.'
                            . ' Anule ese registro en el listado y vuelva a intentarlo.';
                    } else {
                        $texto_error = 'No se pudo registrar la desincorporación. Inténtelo de nuevo; si el problema continúa, contacte al administrador.';
                    }

                    $respuesta = respuesta_alerta($texto_error, 'danger');
                }

            }
        }
        echo json_encode($respuesta);

        break;

    //// **************************

    case 'edit':

        if ($tipo_des == '') {

            $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
            <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
            <h4>
            <i class="icon fa fa-warning"></i>
            Alerta!
            </h4>
            Ingrese La Causa de la Desincorporación del Animal.
            </div>');
        } else  if ($fecha_desincorporacion == '') {

            $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
        <h4>
        <i class="icon fa fa-warning"></i>
        Alerta!
        </h4>
        Debe Ingresar la Fecha de la Desincorporación del Animal.
        </div>');
        } else {
            //( A ) si se escribe un nombre se consulta si ya existe...
            $id_desincorporacion = intval($id_desincorporacion);
            $consulta = Mdesincorporacionanimale::find('all', array('conditions' => array('id=?', $id_desincorporacion)));

            if ($consulta != null) {
                //( B ) si no existe lo guarda...

                session_start();
                if (!desincorporacion_permite('editar')) {
                    echo json_encode(respuesta_alerta(desincorporacion_sin_permiso('editar')));
                    break;
                }
                $usuario_id = $_SESSION['idusuariox'];
                $mfundos_id = $_SESSION['mfundos_id'];

                $hoy = date('d-m-Y');

                $reg_desincorporacion = Mdesincorporacionanimale::find($id_desincorporacion);
                $reg_desincorporacion->tipo_desincorporacion = $tipo_des;
                $reg_desincorporacion->f_desincorporacion = $fecha_desincorporacion;
                $reg_desincorporacion->causa_descarte = $causa_descarte_animal;
                $reg_desincorporacion->causa_mortalidad = $causa_mortalidad_animal;
                $reg_desincorporacion->observaciones = $observaciones;
                $reg_desincorporacion->user_modify = $usuario_id;
                $reg_desincorporacion->updated = $hoy;


                if ($reg_desincorporacion->save()) {
                    // Si la baja sigue activa, el estado del animal debe seguir
                    // al tipo de desincorporación corregido.
                    if ((int)$reg_desincorporacion->status === 1) {
                        $animal_edit = Manimale::find('all', array(
                            'conditions' => array('codigo_rgb_id_animal=? AND mfundos_id=? AND status=1', $reg_desincorporacion->codigo_rgb_id_animal, $mfundos_id),
                            'order' => 'id DESC',
                            'limit' => 1
                        ));
                        if ($animal_edit && isset($animal_edit[0])) {
                            $animal_edit[0]->mestatus_id = (int)$tipo_des;
                            $animal_edit[0]->user_modify = $usuario_id;
                            $animal_edit[0]->updated = $hoy;
                            $animal_edit[0]->save();
                        }
                    }

                    // da el mensaje de guardado...

                    $respuesta = array('resultado' => 'ok', 'mensaje' => '<div class="alert alert-success alert-dismissable">
                    <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                    <h4>
                    <i class="icon fa fa-check"></i>
                    Alerta!
                    </h4>
                    Los datos han sido registrados exitosamente !.
                    </div>');
                } else {
                    $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
                  <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                  <h4>
                  <i class="icon fa fa-ban"></i>
                  Alerta!
                  </h4>
                  Error al registrar los datos.
                  </div>');
                }
            } else {
                $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
              <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
              <h4>
              <i class="icon fa fa-ban"></i>
              Alerta!
              </h4>
              Error al registrar los datos.
              </div>');
            }
        }
        echo json_encode($respuesta);

        break;

    //// **************************

    case 'listar_candidatos_desincorporacion':
        require_once __DIR__ . '/../lib/animales_aptos_helper.php';
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        $filas = bovs_animales_aptos_listar('desincorporacion');

        echo json_encode(array(
            'resultado' => 'ok',
            'filas'     => $filas,
            'total'     => count($filas)
        ));
        break;

    case 'get_buscar_rgb':
        session_start();
        $mfundos_id = $_SESSION['mfundos_id'];
        $buscar = intval($_POST['buscarx']);
        $nro_arete_codigo = ($_POST['rgb_animal_h']);

        $cadena = ' ';
        $cadena_2 = '';

        if ($nro_arete_codigo == '') {

            $resp = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
          <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
          <h4>
          <i class="icon fa fa-warning"></i>
          Alerta!
          </h4>
          Debe Ingresar el Código o Numero de Arete del Animal.
          </div>');
        } else {

            session_start();
            $mfundos_id = $_SESSION['mfundos_id'];
            $t_ganaderia = $_SESSION['tipo_ganaderia'];


            if ($buscar == 1) {

                $rgb_animal = $nro_arete_codigo;
                $partes = explode('-', $rgb_animal);
                $prefijo = $partes[0];
                $nro = 0;
                if ($prefijo == 'SOVCA' || $prefijo == 'BOVS') {
                    $nro = intval($partes[2]);
                } else {
                    $nro = intval($rgb_animal);
                }

                $cod_rgb_animal = 'SOVCA-0' . $mfundos_id . '-0' . $nro;

                $cadena = "and codigo_rgb_id_animal='$cod_rgb_animal' ";

                $criterio = 'Código';
            } else   if ($buscar == 2) {

                $nro_arete = $nro_arete_codigo;

                $cadena = "and nro_arete='$nro_arete' ";
                $criterio = 'Nro de Arete';
            }

            @$data = Manimale::find_by_sql('select  id, mestatus_id as estatus, nombre,codigo_rgb_id_animal as cod_animal from Manimales where status=1 and mtipoganaderias_id=' . $t_ganaderia . ' AND mfundos_id=' . $mfundos_id . "  $cadena ;");

            // La baja se comprueba por el CÓDIGO del animal y solo si sigue
            // activa (status = 1). Un registro anulado no debe volver a
            // bloquear la desincorporación.
            $data_descarte = null;
            if ($data != null && isset($data[0])) {
                $codigo_encontrado = addslashes((string)$data[0]->cod_animal);
                $data_descarte = Mdesincorporacionanimale::find_by_sql(
                    'select id from Mdesincorporacionanimales where mfundos_id=' . (int)$mfundos_id
                    . " and status=1 and codigo_rgb_id_animal='" . $codigo_encontrado . "' limit 1;"
                );
            }

            if ($data_descarte != null) {

                // foreach ( $data_descarte as $rs_descarte ) {

                $resp = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
                  <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                  <h4>
                  <i class="icon fa fa-ban"></i>
                  Alerta!
                  </h4>
                  El Código del Animal Ya se encuentra DESINCORPORADO. Debe Dirigirse a la Opción Editar.
                  </div>');
                // }

            } else

        if ($data != null) {

                foreach ($data as $rs) {

                    $estatus = $rs->estatus;
                    if ($estatus == 1) {
                        $nombre = $rs->cod_animal . '*' . $rs->nombre;
                        $resp = $nombre;
                    } else {
                        $resp = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
                  <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                  <h4>
                  <i class="icon fa fa-ban"></i>
                  Alerta!
                  </h4>
                  El Código del Animal NO EXISTE!!.
                  </div>');
                    }
                }
            } else {
                $resp = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
                  <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                  <h4>
                  <i class="icon fa fa-ban"></i>
                  Alerta!
                  </h4>
                  El Código del Animal NO EXISTE!!.
                  </div>');
            }
        }
        echo json_encode($resp);
        break;

    #*******************************************************************************
    case 'search':
        session_start();
        $mfundos_id = $_SESSION['mfundos_id'];
        if ($id_desincorporacion != null) {

            @$data = Vdesincorporacione::find('all', array('conditions' => array('id_desincorporacion=? and mfundos_id=?', $id_desincorporacion, $mfundos_id)));

            if ($data != null) {

                foreach ($data as $rs) {

                    if ($rs->f_desincorporacion != null) {

                        $fecha_desincorporacion = (string)$rs->f_desincorporacion->format('d-m-Y');
                    } else {
                        $fecha_desincorporacion = '';
                    }

                    $resp = array(
                        'cod_animal' => $rs->codigo_rgb_id_animal,
                        'nombre_animal' => $rs->nombre_animal,
                        'tipo_des' => $rs->tipo_desincorporacion,
                        'fecha_desincorporacion' => $fecha_desincorporacion,
                        'causa_descarte_animal' => $rs->causa_descarte,
                        'causa_mortalidad_animal' => $rs->causa_mortalidad,
                        'observaciones' => $rs->observaciones

                    );
                }

                echo json_encode($resp);
            }
        }
        break;

    #*******************************************************************************

    case 'delete':

        if ($record != null) {

            session_start();
            $usuario_id = $_SESSION['idusuariox'];
            $rol = $_SESSION['rolx'];
            $hoy = date('d-m-Y');
            $mfundos_id = $_SESSION['mfundos_id'];

            // Motivo de la anulación excepcional (obligatorio en modo soporte).
            $motivo_anulacion = isset($_POST['motivo_anulacion']) ? trim((string)$_POST['motivo_anulacion']) : '';
            if (mb_strlen($motivo_anulacion) > 150) {
                $motivo_anulacion = mb_substr($motivo_anulacion, 0, 150);
            }

            if ($usuario_id != '' && desincorporacion_permite('eliminar')) {

                if (desincorporacion_exige_motivo_anulacion() && $motivo_anulacion === '') {
                    echo alerta_desincorporacion('Para anular en <strong>modo soporte</strong> debes registrar el <strong>motivo</strong> de la anulación.', 'warning');
                    break;
                }

                $record_id = (int)$record;
                $registros_des = Mdesincorporacionanimale::find('all', array(
                    'conditions' => array('id=? AND mfundos_id=? AND status=1', $record_id, $mfundos_id),
                    'limit' => 1
                ));

                if (!$registros_des || !isset($registros_des[0])) {
                    echo alerta_desincorporacion('No se encontró un registro <strong>activo</strong> de desincorporación en el fundo actual. Puede que ya haya sido anulado.', 'warning');
                    break;
                }

                $baja = $registros_des[0];
                $cod_animal = (string)$baja->codigo_rgb_id_animal;

                // No debe quedar otra baja activa del mismo animal.
                $otras_bajas = Mdesincorporacionanimale::find('all', array(
                    'conditions' => array('id<>? AND mfundos_id=? AND codigo_rgb_id_animal=? AND status=1', $record_id, $mfundos_id, $cod_animal)
                ));
                if ($otras_bajas && isset($otras_bajas[0])) {
                    echo alerta_desincorporacion('El animal <strong>' . htmlspecialchars($cod_animal, ENT_QUOTES, 'UTF-8') . '</strong> tiene otra desincorporación activa. Anule primero esa otra baja.', 'warning');
                    break;
                }

                $animales_origen = Manimale::find('all', array(
                    'conditions' => array('codigo_rgb_id_animal=? AND mfundos_id=? AND status=1', $cod_animal, $mfundos_id),
                    'order' => 'id DESC',
                    'limit' => 1
                ));

                if (!$animales_origen || !isset($animales_origen[0])) {
                    echo alerta_desincorporacion('No se encontró el animal activo asociado a este registro.', 'warning');
                    break;
                }

                $animal = $animales_origen[0];

                $migraciones_activas = MmigracionAnimale::find('all', array(
                    'conditions' => array(
                        'manimal_origen_id=? AND mfundos_origen_id=? AND status=1 AND revertida=FALSE AND estado=?',
                        (int)$animal->id,
                        $mfundos_id,
                        'completada'
                    )
                ));

                if ($migraciones_activas && isset($migraciones_activas[0])) {
                    echo alerta_desincorporacion('<strong>No se puede eliminar este registro.</strong> El animal fue migrado a otro fundo y se conserva para que allí puedan consultar su historial. Para anular la migración, usa la opción de revertir migración.', 'warning');
                    break;
                }

                // Estado al que debe volver el animal. Los registros anteriores
                // a la columna mestatus_anterior solo podían desincorporarse
                // desde ACTIVO, así que ese es el valor por defecto.
                $estatus_anterior = 1;
                $atributos_baja = $baja->attributes();
                if (array_key_exists('mestatus_anterior', $atributos_baja) && (int)$baja->mestatus_anterior > 0) {
                    $estatus_anterior = (int)$baja->mestatus_anterior;
                }

                $conexion = Mdesincorporacionanimale::connection();
                $conexion->transaction();

                try {
                    if (desincorporacion_exige_motivo_anulacion()) {
                        // Deja rastro visible en el propio registro (máx. 300).
                        $marca = '[ANULADA ' . date('d-m-Y H:i') . ' · soporte] ' . $motivo_anulacion . ' · ';
                        $observacion_original = trim((string)$baja->observaciones);
                        $disponible = 300 - mb_strlen($marca);
                        $baja->observaciones = mb_substr(
                            $marca . mb_substr($observacion_original, 0, max(0, $disponible)),
                            0,
                            300
                        );
                    }

                    $baja->user_modify = $usuario_id;
                    $baja->updated = $hoy;
                    $baja->status = 0;
                    if (!$baja->save()) {
                        throw new Exception('No se pudo anular la desincorporación.');
                    }

                    $animal->mestatus_id = $estatus_anterior;
                    $animal->user_modify = $usuario_id;
                    $animal->updated = $hoy;
                    if (!$animal->save()) {
                        throw new Exception('No se pudo restaurar el estado del animal.');
                    }

                    $conexion->commit();

                    $mensaje_exito = 'Registro Eliminado exitosamente. El animal <strong>' . htmlspecialchars($cod_animal, ENT_QUOTES, 'UTF-8') . '</strong> volvió a su estado anterior ('
                        . htmlspecialchars(estatus_animal_nombre($estatus_anterior), ENT_QUOTES, 'UTF-8') . ') y ya puede desincorporarse de nuevo.';

                    if (desincorporacion_exige_motivo_anulacion()) {
                        $mensaje_exito .= ' Motivo registrado: <em>' . htmlspecialchars($motivo_anulacion, ENT_QUOTES, 'UTF-8') . '</em>.';
                    }

                    echo alerta_desincorporacion($mensaje_exito, 'success');
                } catch (Throwable $e) {
                    $conexion->rollback();
                    echo alerta_desincorporacion('Disculpe! Ha ocurrido un error al anular el registro: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'), 'danger');
                }
            } else {

                echo alerta_desincorporacion(desincorporacion_sin_permiso('eliminar'), 'warning');
            }
        }
        break;


    #*******************************************************************************
    case 'search_ficha':
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $mfundos_id = $_SESSION['mfundos_id'];
        @$t_ganaderia = $_SESSION['tipo_ganaderia'];
        $resp = array();

        if ($record != null) {
            $record_id = (int)$record;

            $searh_cod_animal = Mdesincorporacionanimale::find_by_sql("
                select d.codigo_rgb_id_animal as cod_animal,
                       to_char(d.f_desincorporacion, 'DD-MM-YYYY') as f_desincorporacion,
                       d.tipo_desincorporacion,
                       coalesce(tg.nombre, '') as tipo_des_nombre,
                       coalesce(d.causa_descarte, '') as causa_descarte,
                       coalesce(d.causa_mortalidad, '') as causa_mortalidad,
                       coalesce(d.observaciones, '') as obs_desincorporacion
                from mdesincorporacionanimales d
                left join mestatusganados tg on tg.id = d.tipo_desincorporacion
                where d.id=" . $record_id . " and d.mfundos_id=" . (int)$mfundos_id . ";
            ");

            if ($searh_cod_animal != null) {
                $cod_animal = '';
                $fecha_desincorporacion = '';
                $tipo_desincorporacion = 0;
                $tipo_des_nombre = '';
                $causa_descarte = '';
                $causa_mortalidad = '';
                $obs_desincorporacion = '';

                foreach ($searh_cod_animal as $rs_cod) {
                    $cod_animal = $rs_cod->cod_animal;
                    $fecha_des = $rs_cod->f_desincorporacion;
                    if ($fecha_des instanceof DateTime) {
                        $fecha_desincorporacion = $fecha_des->format('d-m-Y');
                    } else {
                        $fecha_des_str = trim((string)$fecha_des);
                        if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $fecha_des_str)) {
                            $fecha_desincorporacion = $fecha_des_str;
                        } elseif ($fecha_des_str !== '') {
                            $ts = strtotime($fecha_des_str);
                            $fecha_desincorporacion = $ts ? date('d-m-Y', $ts) : '';
                        } else {
                            $fecha_desincorporacion = '';
                        }
                    }
                    $tipo_desincorporacion = (int)$rs_cod->tipo_desincorporacion;
                    $tipo_des_nombre = $rs_cod->tipo_des_nombre;
                    $causa_descarte = $rs_cod->causa_descarte;
                    $causa_mortalidad = $rs_cod->causa_mortalidad;
                    $obs_desincorporacion = $rs_cod->obs_desincorporacion;
                }

                $searh_id_animal = Manimale::find_by_sql("select id as id_animal from Manimales
                    where codigo_rgb_id_animal='" . $cod_animal . "' and mfundos_id=" . (int)$mfundos_id . ";");

                if ($searh_id_animal != null) {
                    $id_animal = 0;
                    foreach ($searh_id_animal as $rs_id) {
                        $id_animal = $rs_id->id_animal;
                    }

                    @$data_fundo = Mfundo::find('all', array('conditions' => array('id=?', $mfundos_id)));
                    $nombre_fundo = '';
                    $rif_fundo = '';
                    foreach ($data_fundo as $rs_fundo) {
                        $nombre_fundo = $rs_fundo->nombre;
                        $rif_fundo = $rs_fundo->cid_rif;
                    }

                    @$data = Vmfichaxanimale::find('all', array('conditions' => array('id_animal=? and mfundos_id=?', $id_animal, $mfundos_id)));

                    if ($data != null) {
                        foreach ($data as $rs) {
                            $f_nacimiento = '';
                            $f_ingreso = '';

                            if ($rs->f_nacimiento != '') {
                                $f_nacimiento = (string)$rs->f_nacimiento->format('d-m-Y');
                            }
                            if ($rs->f_ingreso != '') {
                                $f_ingreso = (string)$rs->f_ingreso->format('d-m-Y');
                            }
                            $cat_actual = Cat_Actual($rs->id_sexo, $f_nacimiento, $mfundos_id, $t_ganaderia);

                            $resp = array(
                                'cod_animal' => $rs->codigo_rgb_id_animal,
                                'nombre_animal' => $rs->nombre,
                                'estatus_animal' => $rs->estatus_animal,
                                'sexo' => $rs->sexo,
                                'lote' => $rs->lote,
                                'rena' => $rs->rena,
                                'raza_animal' => $rs->raza,
                                'fech_nac' => $f_nacimiento,
                                'peso_nac' => $rs->peso_nacimiento,
                                'fecha_ingreso' => $f_ingreso,
                                'aptitud_animal' => $rs->aptitud,
                                'numero_arete' => $rs->nro_arete,
                                'rgd_madre' => $rs->rgd_madre,
                                'nombre_madre' => $rs->nombre_madre,
                                'rgd_padre' => $rs->rgd_padre,
                                'nombre_padre' => $rs->nombre_padre,
                                'servicio_animal' => $rs->servicio,
                                'procedencia' => $rs->procedencia,
                                'observaciones' => $rs->observaciones,
                                'categoria_actual' => $cat_actual,
                                'fundo' => $nombre_fundo,
                                'rif_fundo' => $rif_fundo,
                                'fecha_desincorporacion' => $fecha_desincorporacion,
                                'tipo_desincorporacion' => $tipo_desincorporacion,
                                'tipo_des_nombre' => $tipo_des_nombre,
                                'causa_descarte' => $causa_descarte,
                                'causa_mortalidad' => $causa_mortalidad,
                                'obs_desincorporacion' => $obs_desincorporacion
                            );
                        }
                    }
                }
            }
        }
        echo json_encode($resp);
        break;


      case 'add_nac':    
        
        $codigo_ternero = ($_POST['codigo_ternero']);

        if ($tipo_des == '') {

            $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
        <h4>
        <i class="icon fa fa-warning"></i>
        Alerta!
        </h4>
        Ingrese La Causa de la Desincorporación del Animal.
        </div>');
        } else  if ($fecha_desincorporacion == '') {

            $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
        <h4>
        <i class="icon fa fa-warning"></i>
        Alerta!
        </h4>
        Debe Ingresar la Fecha de la Desincorporación del Animal.
        </div>');
        } else {
            //( A ) si se escribe un nombre se consulta si ya existe...

            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            $codigo_ternero = trim((string)$codigo_ternero);

            if (!desincorporacion_permite('registrar')) {
                echo json_encode(respuesta_alerta(desincorporacion_sin_permiso('registrar')));
                break;
            }

            // Solo las bajas ACTIVAS bloquean: las anuladas no impiden volver
            // a registrar la desincorporación de la misma cría.
            $consulta = Mdsinficha::find('all', array(
                'conditions' => array('codigo_ternero=? AND mfundos_id=? AND status=1', $codigo_ternero, (int)$_SESSION['mfundos_id'])
            ));

            if ($consulta == null) {
                //( B ) si no existe lo guarda...

                if (session_status() !== PHP_SESSION_ACTIVE) {
                    session_start();
                }
                $usuario_id = $_SESSION['idusuariox'];
                $mfundos_id = $_SESSION['mfundos_id'];
                $t_ganaderia = $_SESSION['tipo_ganaderia'];

                $hoy = date('d-m-Y');

                $reg_desincorporacion = new Mdsinficha();

                $reg_desincorporacion->codigo_ternero = $codigo_ternero;
                $reg_desincorporacion->tipo_desincorporacion = $tipo_des;
                $reg_desincorporacion->f_desincorporacion = $fecha_desincorporacion;
                $reg_desincorporacion->causa_descarte = $causa_descarte_animal;
                $reg_desincorporacion->causa_mortalidad = $causa_mortalidad_animal;
                $reg_desincorporacion->observaciones = $observaciones;
                $reg_desincorporacion->user_create = $usuario_id;
                $reg_desincorporacion->mfundos_id = $mfundos_id;
                $reg_desincorporacion->created = $hoy;

                if ($reg_desincorporacion->save()) {
                    // da el mensaje de guardado...

                    $searh_animal = Mregnacimiento::find_by_sql('select  id from Mregnacimientos where mfundos_id=' . $mfundos_id . " AND mtipoganaderias_id=" . $t_ganaderia . " and codigo_ternero='$codigo_ternero';");

                    foreach ($searh_animal as $st) {

                        $ide = $st->id;
                    }
                    $reg_animal = Mregnacimiento::find($ide);

                    $reg_animal->mestatus_id = $tipo_des;
                    $reg_animal->user_modify = $usuario_id;

                    if ($reg_animal->save()) {

                        $respuesta = array('resultado' => 'ok', 'mensaje' => '<div class="alert alert-success alert-dismissable">
                <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                <h4>
                <i class="icon fa fa-check"></i>
                Alerta!
                </h4>
                Los datos han sido registrados exitosamente !.
                </div>');
                    } else {
                        $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
              <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
              <h4>
              <i class="icon fa fa-ban"></i>
              Alerta!
              </h4>
              Error al registrar los datos.
              </div>');
                    }
                } else {

                    $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
               <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
               <h4>
               <i class="icon fa fa-ban"></i>
               Alerta!
               </h4>
               Error al registrar los datos.
               </div>');
                }

                //echo json_encode( $respuesta );

            }
        }
        echo json_encode($respuesta);

        break;

    //// **************************

      case 'edit_nac':    
        
        $cod_reg = ($_POST['cod_reg']);

        if ($tipo_des == '') {

            $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
        <h4>
        <i class="icon fa fa-warning"></i>
        Alerta!
        </h4>
        Ingrese La Causa de la Desincorporación del Animal.
        </div>');
        } else  if ($fecha_desincorporacion == '') {

            $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
        <h4>
        <i class="icon fa fa-warning"></i>
        Alerta!
        </h4>
        Debe Ingresar la Fecha de la Desincorporación del Animal.
        </div>');
        } else {
            //( A ) si se escribe un nombre se consulta si ya existe...

            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            if (!desincorporacion_permite('editar')) {
                echo json_encode(respuesta_alerta(desincorporacion_sin_permiso('editar')));
                break;
            }

                $hoy = date('d-m-Y');

                $reg_desincorporacion = Mdsinficha::find($cod_reg);
                $reg_desincorporacion->tipo_desincorporacion = $tipo_des;
                $reg_desincorporacion->f_desincorporacion = $fecha_desincorporacion;
                $reg_desincorporacion->causa_descarte = $causa_descarte_animal;
                $reg_desincorporacion->causa_mortalidad = $causa_mortalidad_animal;
                $reg_desincorporacion->observaciones = $observaciones;
                $reg_desincorporacion->updated = $hoy;

                if ($reg_desincorporacion->save()) {
                
                 $respuesta = array('resultado' => 'ok', 'mensaje' => '<div class="alert alert-success alert-dismissable">
                <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                <h4>
                <i class="icon fa fa-check"></i>
                Alerta!
                </h4>
                Los datos han sido registrados exitosamente !.
                </div>');
                    
                    }

              else {

                    $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
               <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
               <h4>
               <i class="icon fa fa-ban"></i>
               Alerta!
               </h4>
               Error al registrar los datos.
               </div>');
                }

        }
        echo json_encode($respuesta);

        break;

    //// **************************





}
//end switch

/**
 * Matriz de permisos del módulo de desincorporación.
 *
 * Es un módulo OPERATIVO DEL FUNDO, no de administración del sistema:
 *   - PRODUCTOR (2)     : registrar, editar y eliminar.
 *   - ENCARGADO (3)     : registrar y editar. NUNCA elimina.
 *   - ADMINISTRADOR (1) : en MODO SOPORTE solo EDITAR. La anulación es
 *                         excepcional y exige motivo (desincorporacion_exige_motivo_anulacion).
 *   - SUPER USUARIO (4) : igual que el administrador.
 *
 * En modo soporte la sesión conserva rolx = 1/4 (ver lib/multifundo_helper.php),
 * por eso se comprueba también $_SESSION['supervisor_mode'].
 */
function desincorporacion_permite($accion)
{
    $rol = isset($_SESSION['rolx']) ? (int)$_SESSION['rolx'] : 0;
    $en_soporte = !empty($_SESSION['supervisor_mode']);

    $es_productor = ($rol === 2);
    $es_encargado = ($rol === 3);
    $es_admin_en_soporte = in_array($rol, array(1, 4), true) && $en_soporte;

    switch ($accion) {
        case 'registrar':
            // El alta corresponde al fundo; el admin en soporte no registra.
            return $es_productor || $es_encargado;

        case 'editar':
            return $es_productor || $es_encargado || $es_admin_en_soporte;

        case 'eliminar':
            // Productor siempre; admin en soporte solo con motivo (se valida en delete).
            return $es_productor || $es_admin_en_soporte;

        default:
            return false;
    }
}

/**
 * ¿La anulación actual es la excepción del modo soporte?
 * En ese caso el motivo es obligatorio y se guarda en la observación.
 */
function desincorporacion_exige_motivo_anulacion()
{
    $rol = isset($_SESSION['rolx']) ? (int)$_SESSION['rolx'] : 0;
    return in_array($rol, array(1, 4), true) && !empty($_SESSION['supervisor_mode']);
}

/**
 * Mensaje de rechazo según el rol y la acción.
 */
function desincorporacion_sin_permiso($accion)
{
    $rol = isset($_SESSION['rolx']) ? (int)$_SESSION['rolx'] : 0;
    $verbo = ($accion === 'eliminar') ? 'anular desincorporaciones' : 'modificar desincorporaciones';

    if (in_array($rol, array(1, 4), true)) {
        if (empty($_SESSION['supervisor_mode'])) {
            return 'El módulo de Desincorporación se opera desde el fundo. Entra en <strong>modo soporte</strong> sobre el fundo para poder ' . $verbo . '.';
        }

        if ($accion === 'registrar') {
            return 'En <strong>modo soporte</strong> solo puedes editar registros existentes. El registro de nuevas desincorporaciones corresponde al productor o al encargado.';
        }
    }

    if ($rol === 3 && $accion === 'eliminar') {
        return 'El rol <strong>ENCARGADO</strong> no puede anular desincorporaciones. Solo el productor (o un administrador en modo soporte, con motivo) puede hacerlo.';
    }

    return 'Usted no puede realizar esta transacción.';
}

/**
 * Mensaje HTML estándar de los casos de desincorporación.
 */
function alerta_desincorporacion($texto, $tipo = 'warning')
{
    $iconos = array('success' => 'fa-check', 'danger' => 'fa-ban', 'warning' => 'fa-warning');
    $icono = isset($iconos[$tipo]) ? $iconos[$tipo] : 'fa-warning';

    return '<div class="alert alert-' . $tipo . ' alert-dismissable">'
        . '<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>'
        . '<h4><i class="icon ' . $icono . '"></i> Alerta!</h4>'
        . $texto
        . '</div>';
}

/**
 * Respuesta JSON (resultado + mensaje) para los formularios que usan $.post.
 */
function respuesta_alerta($texto, $tipo = 'warning')
{
    return array(
        'resultado' => ($tipo === 'success') ? 'ok' : 'error',
        'mensaje'   => alerta_desincorporacion($texto, $tipo)
    );
}

/**
 * Nombre del estado del animal (mestatusganados) con caché por petición.
 */
function estatus_animal_nombre($estatus_id)
{
    $estatus_id = (int)$estatus_id;
    if ($estatus_id <= 0) {
        return '';
    }

    static $cache = array();
    if (!array_key_exists($estatus_id, $cache)) {
        $filas = Mestatusganado::find_by_sql('SELECT nombre FROM Mestatusganados WHERE id = ?', array($estatus_id));
        $cache[$estatus_id] = ($filas && isset($filas[0]->nombre))
            ? trim((string)$filas[0]->nombre)
            : ('ESTADO ' . $estatus_id);
    }

    return $cache[$estatus_id];
}

function Cat_Actual( $sexo_categoria, $fecha_nac, $mfundos_id, $t_ganaderia ) {

    $edad = tiempo_t( $fecha_nac );

    $data_propia = Mcategoria::find_by_sql( "select nombre from mcategorias 
                                          WHERE 
                                          mtipoganaderias_id=".$t_ganaderia." and 
                                          status=1 and 
                                          sexo=".$sexo_categoria." and 
                                          mfundos_id=".$mfundos_id." and
                                          edadminima<=".$edad." and
                                          edadmaxima> ".$edad.'  order by id asc;' );

    $data_estandar = Mcategoria::find_by_sql( "select nombre from mcategorias 
                                          WHERE 
                                          mtipoganaderias_id=".$t_ganaderia." and 
                                          status=1 and 
                                          sexo=".$sexo_categoria." and 
                                          mfundos_id=0 and
                                          edadminima<=".$edad." and
                                          edadmaxima> ".$edad.'  order by id asc;' );

    if ( $data_propia != null ) {

        foreach ( $data_propia as $rs ) {

            $cat_actual = $rs->nombre;

        }

    } else if ( $data_estandar != null ) {

        foreach ( $data_estandar as $rs ) {

            $cat_actual = $rs->nombre;

        }

    }
    return  $cat_actual;

}

function tiempo_t( $fecha_nac ) {
    $fecha_f = date( 'Y-m-d' );
    $fecha_i = $fecha_nac;
    $dias = ( strtotime( $fecha_i ) - strtotime( $fecha_f ) ) / 86400;
    $dias = abs( $dias );
    $dias = floor( $dias );
    $meses = ( $dias / 30 );
    $meses = ( int )$meses;

    return $meses;
}