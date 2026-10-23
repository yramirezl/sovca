<?php
include '../lib/sanitizar.php';
include '../lib/funciones.php';
include '../lib/imagen_helper.php';
date_default_timezone_set('America/Caracas');
////////////////////////////////////////
require_once '../models/Malerta.php';
require_once '../models/Valerta.php';
require_once '../models/Mtipomensaje.php';
require_once '../models/Mmensaje.php';
require_once '../models/Mdetamensaje.php';
require_once '../models/Mcontacto.php';
require_once '../models/Mpromomensaje.php';



@$action = ($_POST['action']);
@$id = ($_POST['id_alerta']);
@$id_alerta = ($_POST['id_alerta']);
@$record = ($_POST['record']);
/***********************************/
// MENSAJES INTERNOS
@$tipo_mensaje = ($_POST['tipo_mensaje']);
@$titulo_mensaje = ($_POST['titulo_mensaje']);
@$cuerpo_mensaje = ($_POST['cuerpo_mensaje']);
@$tipo_ganaderia = ($_POST['tipo_ganaderia']);
@$fecha_caducidad = ($_POST['fecha_caducidad']);
@$id_mensaje = ($_POST['id_mensaje']);
@$estatus_mensaje = ($_POST['estatus_mensaje']);

/***********************************/
// MENSAJES EXTERNOS


/***********************************/
// CONTACTOS EXTERNOS
@$id_contacto = ($_POST['id_contacto']);
@$nombres = ($_POST['nombres']);
@$email = ($_POST['email']);
@$telefono = ($_POST['telefono']);

/***********************************/

@$sourcePath = $_FILES['file']['tmp_name'];  // Almacenar ruta de origen del archivo en una variable

@$tipo_archivo = $_FILES['file']['type'];
$nombreDelArchivo = ($_FILES['file']['name']);
@$extension = pathinfo($nombreDelArchivo, PATHINFO_EXTENSION);
$valor =  time();
@$imagen = $valor . '.' . $extension;
// @$targetPath = "../dist/img/promo/" . $imagen; // Ruta de destino donde el archivo se va a almacenar

/***********************************/

switch ($action) {

    case 'add_msj_ext': // MENSAJES EXTERNOS

        if ($tipo_mensaje == '') {

            $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
            <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
            <h4>
            <i class="icon fa fa-ban"></i>
            Alerta!
            </h4>
            Indique el Tipo de Mensaje.
            </div>');
            echo json_encode($respuesta);
            die();
        } else  if ($titulo_mensaje == '') {

            $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
            <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
            <h4>
            <i class="icon fa fa-ban"></i>
            Alerta!
            </h4>
          Indique el Titulo de Mensaje.
          </div>');
          echo json_encode($respuesta);
          die();
        } else if ($cuerpo_mensaje == '') {

            $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
            <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
            <h4>
            <i class="icon fa fa-warning"></i>
            Alerta!
            </h4>
            Indique el Cuerpo de Mensaje.
            </div>');
            echo json_encode($respuesta);
            die();
        } else {
            //( A ) si se escribe un nombre se consulta si ya existe...
            $procesar = false;
            session_start();
            $usuario_id = $_SESSION['idusuariox'];
            $hoy = date('d-m-Y');

            // IMAGEN — redimensiona y convierte a WebP
            @$nombre_imagen = "";
            // Imagen actual del mensaje (para conservarla si no se adjunta una nueva)
            $imagen_anterior = "";
            if ($id_mensaje != '') {
                $reg_img = Mpromomensaje::find_by_sql("SELECT imagen FROM Mpromomensajes WHERE id=" . (int) $id_mensaje . " ;");
                if ($reg_img) {
                    $imagen_anterior = (string) $reg_img[0]->imagen;
                }
            }
            if (bovs_imagen_hay_archivo(isset($_FILES['file']) ? $_FILES['file'] : null)) {
                $err_img = bovs_imagen_error_archivo($_FILES['file']);
                if ($err_img === null) {
                    $img_proc = bovs_imagen_procesar($_FILES['file']['tmp_name'], '../dist/img/promo/', time());
                    if ($img_proc) {
                        // Eliminar la imagen anterior (si existe y es distinta a la nueva)
                        if ($imagen_anterior !== "" && $imagen_anterior !== $img_proc) {
                            bovs_imagen_eliminar('../dist/img/promo/', $imagen_anterior);
                        }
                        @$nombre_imagen = $img_proc;
                    }
                }
            }



            if ($id_mensaje != '') {
                $consulta = Mpromomensaje::find('all', array('conditions' => array('id=? ', $id_mensaje)));
                if ($consulta != null) {
                    $reg_mensaje = Mpromomensaje::find($id_mensaje);
                    $reg_mensaje->user_modify = $usuario_id;
                    $reg_mensaje->updated = $hoy;
                    $procesar = true;
                    $mensaje = 1;
                }
            } else {
                $consulta = Mpromomensaje::find('all', array('conditions' => array('titulo=? and mtipoganaderias_id=?', $titulo_mensaje, $tipo_ganaderia)));
                if ($consulta == null) {
                    $reg_mensaje = new Mpromomensaje();
                    $reg_mensaje->user_create = $usuario_id;
                    $reg_mensaje->created = $hoy;
                    $procesar = true;
                    $mensaje = 2;
                }
            }
            if ($procesar) {
                //( B ) si no existe lo guarda...

                $reg_mensaje->tipo_mensaje = $tipo_mensaje;
                $reg_mensaje->titulo = $titulo_mensaje;
                $reg_mensaje->cuerpo_mensaje = $cuerpo_mensaje;
                $reg_mensaje->mtipoganaderias_id = $tipo_ganaderia;
                // Solo se actualiza la imagen si se adjuntó una nueva (no borrar la existente)
                if ($nombre_imagen !== "") {
                    $reg_mensaje->imagen = $nombre_imagen;
                }


                if ($reg_mensaje->save()) {
                    // da el mensaje de guardado...
                    if ($mensaje == 1) {
                        $respuesta = array('resultado' => 'ok', 'mensaje' => '<div class="alert alert-success alert-dismissable">
                            <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                            <h4>
                            <i class="icon fa fa-check"></i>
                            Alerta!
                            </h4>
                            Los datos han sido Actualizados correctamente !.
                            </div>');
                    } else {
                        $respuesta = array('resultado' => 'ok', 'mensaje' => '<div class="alert alert-success alert-dismissable">
                            <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                            <h4>
                            <i class="icon fa fa-check"></i>
                            Alerta!
                            </h4>
                            Los datos han sido registrados exitosamente !.
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
        #*******************************************************************************


    case 'edit_msj': // MENSAJES INTERNOS

        if ($tipo_mensaje == '') {

            $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
          <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
          <h4>
          <i class="icon fa fa-warning"></i>
          Alerta!
          </h4>
          Indique el Tipo de Mensaje.
          </div>');
        } else   if ($titulo_mensaje == '') {

            $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
          <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
          <h4>
          <i class="icon fa fa-warning"></i>
          Alerta!
          </h4>
          Indique el Titulo de Mensaje.
          </div>');
        } else   if ($cuerpo_mensaje == '') {

            $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
          <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
          <h4>
          <i class="icon fa fa-warning"></i>
          Alerta!
          </h4>
          Indique el Cuerpo de Mensaje.
          </div>');
        } else {
            //( A ) si se escribe un nombre se consulta si ya existe...

            $consulta = Mmensaje::find('all', array('conditions' => array('id=? ', $id_mensaje)));

            if ($consulta != null) {
                //( B ) si no existe lo guarda...

                session_start();
                $usuario_id = $_SESSION['idusuariox'];
                $hoy = date('d-m-Y');

                $reg_mensaje = Mmensaje::find($id_mensaje);

                $reg_mensaje->tipo_mensaje = $tipo_mensaje;
                $reg_mensaje->titulo = $titulo_mensaje;
                $reg_mensaje->cuerpo_mensaje = $cuerpo_mensaje;
                $reg_mensaje->mtipoganaderias_id = $tipo_ganaderia;
                $reg_mensaje->fecha_caducidad = $fecha_caducidad;
                $reg_mensaje->activo = $estatus_mensaje;

                if ($reg_mensaje->save()) {
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

                //echo json_encode( $respuesta );

            } else {
                //( B ) si exite el nombre verifica el status del registro...

                foreach ($consulta as $st) {

                    $estado = $st->status;
                    $ide = $st->id;
                }

                if ($estado != 1) {
                    //se activa mensaje modal para llevar el status a '1' ... ( o no ).

                    //echo 'MODAL PARA ACTIVAR REGISTRO GUARDADO';
                    exit();

                    $respuesta = array('resultado' => 'alert', 'mensaje' => '<input type="hidden" id="id" value="' . $ide . '">
                <div class="alert alert-danger alert-dismissable">
                <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                <h4>
                <i class="icon fa fa-ban"></i>
                Alerta!
                </h4>

                Registro inactivo . ¿Desea incluir [' . $titulo_mensaje . '] ?

                    <div class="modal-footer" >
                      <button type="button" id="no" class="btn btn-outline pull-left" data-dismiss="alert" onClick="no()">No</button>
                      <button type="button" id="silo" class="btn btn-outline pull-left" onClick="si()" >Si</button>
                    </div>
                </div>');
                } else {

                    $respuesta = array('resultado' => 'alert', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
                <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                <h4>
                <i class="icon fa fa-ban"></i>
                Alerta!
                </h4>
                El  [' . $titulo_mensaje . '] ya se encuentra registrado.
                </div>');
                }
                // echo json_encode( $respuesta );
            }
        }
        echo json_encode($respuesta);

        break;
        #*******************************************************************************
    case 'add_msj': // MENSAJES INTERNOS

        if ($tipo_mensaje == '') {

            $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
          <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
          <h4>
          <i class="icon fa fa-warning"></i>
          Alerta!
          </h4>
          Indique el Tipo de Mensaje.
          </div>');
        } else   if ($titulo_mensaje == '') {

            $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
          <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
          <h4>
          <i class="icon fa fa-warning"></i>
          Alerta!
          </h4>
          Indique el Titulo de Mensaje.
          </div>');
        } else   if ($cuerpo_mensaje == '') {

            $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
          <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
          <h4>
          <i class="icon fa fa-warning"></i>
          Alerta!
          </h4>
          Indique el Cuerpo de Mensaje.
          </div>');
        } else {
            //( A ) si se escribe un nombre se consulta si ya existe...

            $consulta = Mmensaje::find('all', array('conditions' => array('titulo=? and mtipoganaderias_id=?', $titulo_mensaje, $tipo_ganaderia)));

            if ($consulta == null) {
                //( B ) si no existe lo guarda...

                session_start();
                $usuario_id = $_SESSION['idusuariox'];
                $hoy = date('d-m-Y');

                $reg_mensaje = new Mmensaje();

                $reg_mensaje->tipo_mensaje = $tipo_mensaje;
                $reg_mensaje->titulo = $titulo_mensaje;
                $reg_mensaje->cuerpo_mensaje = $cuerpo_mensaje;
                $reg_mensaje->mtipoganaderias_id = $tipo_ganaderia;
                $reg_mensaje->fecha_caducidad = $fecha_caducidad;

                $reg_mensaje->user_create = $usuario_id;
                $reg_mensaje->created = $hoy;

                if ($reg_mensaje->save()) {
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

                //echo json_encode( $respuesta );

            } else {
                //( B ) si exite el nombre verifica el status del registro...

                foreach ($consulta as $st) {

                    $estado = $st->status;
                    $ide = $st->id;
                }

                if ($estado != 1) {
                    //se activa mensaje modal para llevar el status a '1' ... ( o no ).

                    //echo 'MODAL PARA ACTIVAR REGISTRO GUARDADO';
                    exit();

                    $respuesta = array('resultado' => 'alert', 'mensaje' => '<input type="hidden" id="id" value="' . $ide . '">
                <div class="alert alert-danger alert-dismissable">
                <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                <h4>
                <i class="icon fa fa-ban"></i>
                Alerta!
                </h4>

                Registro inactivo . ¿Desea incluir [' . $titulo_mensaje . '] ?

                    <div class="modal-footer" >
                      <button type="button" id="no" class="btn btn-outline pull-left" data-dismiss="alert" onClick="no()">No</button>
                      <button type="button" id="silo" class="btn btn-outline pull-left" onClick="si()" >Si</button>
                    </div>
                </div>');
                } else {

                    $respuesta = array('resultado' => 'alert', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
                <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                <h4>
                <i class="icon fa fa-ban"></i>
                Alerta!
                </h4>
                El  [' . $titulo_mensaje . '] ya se encuentra registrado.
                </div>');
                }
                // echo json_encode( $respuesta );
            }
        }
        echo json_encode($respuesta);

        break;
        #*******************************************************************************
  

    case 'search_alerta':
        session_start();
        $mfundos_id = $_SESSION['mfundos_id'];
        if ($id_alerta != null) {

            @$data = Valerta::find('all', array('conditions' => array('id=? and mfundos_id=?', $id_alerta, $mfundos_id)));

            if ($data != null) {

                foreach ($data as $rs) {

                    $resp = array(
                        'id' => $rs->id,
                        'evento' => $rs->evento,
                        'cod_animal' => $rs->codigo_animal,
                        'nro_arete' => $rs->nro_arete,
                        'nombre_animal' => $rs->nombre_animal,
                        'fecha_estimada' => (string)$rs->f_estimada->format('d-m-Y')

                    );
                }
                $reg_alerta = Malerta::find($id_alerta);
                $reg_alerta->vista = true;
                if ($reg_alerta->save()) {
                    $db = ActiveRecord\ConnectionManager::get_connection();
                    $alerta_id = (int)$id_alerta;
                    $db->query("INSERT INTO public.malertas_email_estado (
                            mfundos_id, mtipoalertas_id, codigo_animal, evento,
                            f_estimada, ultima_visualizacion
                        )
                        SELECT mfundos_id, mtipoalertas_id, codigo_animal, evento,
                               f_estimada::date, NOW()
                          FROM public.malertas
                         WHERE id = " . $alerta_id . " AND f_estimada IS NOT NULL
                        ON CONFLICT (mfundos_id, mtipoalertas_id, codigo_animal, evento, f_estimada)
                        DO UPDATE SET ultima_visualizacion = EXCLUDED.ultima_visualizacion");
                }

                echo json_encode($resp);
            }
        }
        break;

        #*******************************************************************************

    case 'get_notificaciones':

        session_start();
        @$mfundos_id = $_SESSION['mfundos_id'];
        $rolex = $_SESSION['rolx'];
        $resp = 0;
        if ($rolex == 2) {
            $data = Malerta::find_by_sql(
                'SELECT COUNT(*) AS cantidad
                   FROM Malertas
                  WHERE activa = TRUE AND vista = FALSE AND mfundos_id = ?',
                array((int)$mfundos_id)
            );
            $resp = ($data && isset($data[0])) ? (int)$data[0]->cantidad : 0;
            $_SESSION['acceso_alerta'] = $resp > 0;
        } else if ($rolex == 4) {
            $data = Malerta::find_by_sql(
                'SELECT COUNT(*) AS cantidad FROM Malertas WHERE activa = TRUE'
            );
            $resp = ($data && isset($data[0])) ? (int)$data[0]->cantidad : 0;
        }
        echo $resp;

        break;

        #*******************************************************************************
    case 'search_rec_msj':

        session_start();
        $usuario_id = $_SESSION['idusuariox'];
        $rolex = $_SESSION['rolx'];
        @$mfundos_id = $_SESSION['mfundos_id'];
        @$mtipoganaderias_id = $_SESSION['tipo_ganaderia'];

        $hoy = date('d-m-Y');

        @$data = Mmensaje::find('all', array('conditions' => array('activo=? AND mtipoganaderias_id=? OR mtipoganaderias_id=? ', 1, $mtipoganaderias_id, 0)));

        if ($data != null) {

            foreach ($data as $rs) {
                //echo( $rs->id );
                exit();
                $resp = $rs->id;
            }
        }

        echo $resp;

        break;

        #******************************************************************************
        #*******************************************************************************
    case 'search_rec':

        session_start();
        $usuario_id = $_SESSION['idusuariox'];
        $rolex = $_SESSION['rolx'];
        @$mfundos_id = $_SESSION['mfundos_id'];

        $hoy = date('d-m-Y');

        @$data = Malerta::find('all', array('conditions' => array('activa=? AND mfundos_id=?', 'true', $mfundos_id)));

        if ($data != null) {

            foreach ($data as $rs) {

                exit();
                $resp = $rs->id;
            }
        }

        echo $resp;

        break;

        #******************************************************************************

    case 'get_tipo_mensaje':

        @$data = Mtipomensaje::find('all', array('conditions' => array('status=?', 1), 'order' => 'id desc'));

        if ($data != null) {
            $resp = '<option value="">--</option>';
            foreach ($data as $rs) {
                $resp .= '<option value="' . $rs->id . '">' . $rs->nombre . '</option>';
                $resp .= '<hidden>';
            }
        } else {
            $resp = '<option value="">No hay Tipo de Mensajes para Agregar</option>';
        }

        echo $resp;
        break;
        #******************************************************************************

    case 'get_mensajes':
                    session_start();
                    $mfundos_id = (int)$_SESSION['mfundos_id'];
                    $rol = (int)$_SESSION['rolx'];
                    $tipo_ganaderia = (int)$_SESSION['tipo_ganaderia'];
                    $respuesta = array('cant_id' => 0, 'nro_vista' => 0);

                    if ($rol === 2 || $rol === 3) {
                        $data = Mmensaje::find_by_sql(
                            "SELECT COUNT(*) FILTER (WHERE COALESCE(lectura.nro_vista, 0) < 2) AS cant_id,
                                            CASE
                                                WHEN COUNT(*) = 0 THEN 0
                                                WHEN COUNT(*) FILTER (WHERE COALESCE(lectura.nro_vista, 0) < 2) = 0 THEN 2
                                                WHEN COUNT(*) FILTER (WHERE COALESCE(lectura.nro_vista, 0) = 0) > 0 THEN 0
                                                ELSE 1
                                            END AS nro_vista
                                 FROM Mmensajes AS mensaje
                        LEFT JOIN LATERAL (
                                            SELECT detalle.nro_vista
                                                FROM Mdetamensajes AS detalle
                                             WHERE detalle.mmensajes_id = mensaje.id
                                                 AND detalle.mfundos_id = ?
                                        ORDER BY detalle.fecha_visto DESC NULLS LAST, detalle.id DESC
                                             LIMIT 1
                                 ) AS lectura ON TRUE
                                WHERE mensaje.fecha_caducidad::date > CURRENT_DATE
                                    AND mensaje.mtipoganaderias_id IN (?, 0)
                                    AND mensaje.activo = 1
                                    AND mensaje.status = 1",
                            array($mfundos_id, $tipo_ganaderia)
                        );
                        if ($data && isset($data[0])) {
                            $respuesta = array(
                                'cant_id' => (int)$data[0]->cant_id,
                                'nro_vista' => (int)$data[0]->nro_vista
                            );
                        }
                    }
                    echo json_encode($respuesta);
                    break;

                case 'search_mensaje':
                    if ($id_mensaje != null) {
                        $data = Mmensaje::find('all', array('conditions' => array('id=?', $id_mensaje)));
                        if ($data != null) {
                            foreach ($data as $rs) {
                                $resp = array(
                                    'id_mensaje' => $rs->id,
                                    'activo' => $rs->activo,
                                    'tipo_mensaje' => $rs->tipo_mensaje,
                                    'titulo_mensaje' => $rs->titulo,
                                    'cuerpo_mensaje' => $rs->cuerpo_mensaje,
                                    'fecha_caducidad' => (string)$rs->fecha_caducidad->format('d-m-Y'),
                                    'tipo_ganaderia' => $rs->mtipoganaderias_id
                                );
                            }
                            echo json_encode($resp);
                        }
                    }
                    break;
                    //////////////////////////////////////////////////////////////////////

                case 'search_mensaje_ext':
                    if ($id_mensaje != null) {
                        $data = Mpromomensaje::find('all', array('conditions' => array('id=?', $id_mensaje)));
                        if ($data != null) {
                            foreach ($data as $rs) {
                                $resp = array(
                                    'id_mensaje' => $rs->id,
                                    'tipo_mensaje' => $rs->tipo_mensaje,
                                    'titulo_mensaje' => $rs->titulo,
                                    'cuerpo_mensaje' => $rs->cuerpo_mensaje,
                                    'tipo_ganaderia' => $rs->mtipoganaderias_id,
                                    'imagen' => $rs->imagen
                                );
                            }
                            echo json_encode($resp);
                        }
                    }
                    break;

                    //////////////////////////////////////////////////////////////////

    case 'search_contacto_ext':

        if ($id_contacto != null) {

            @$data = Mcontacto::find('all', array('conditions' => array('id=?', $id_contacto)));

            if ($data != null) {

                foreach ($data as $rs) {

                    $resp = array(
                        'id_contacto' => $rs->id,
                        'nombres' => $rs->name,
                        'email' => $rs->email,
                        'telefono' => $rs->telefono
                    );
                }

                echo json_encode($resp);
            }
        }
        break;

        //////////////////////////////////////////////////////////////////
        //////////////////////////////////////////////////////////////////

    case 'search_contenido_mensaje':

        session_start();
        $mfundos_id = (int)$_SESSION['mfundos_id'];
        $tipo_ganaderia = (int)$_SESSION['tipo_ganaderia'];
        $data = Mmensaje::find_by_sql(
            "SELECT mensaje.id AS id_mensaje,
                    mensaje.titulo AS titulo_mensaje,
                    mensaje.cuerpo_mensaje,
                    COALESCE(lectura.nro_vista, 0) AS nro_vista,
                    lectura.id AS id_detalle
               FROM Mmensajes AS mensaje
          LEFT JOIN LATERAL (
                    SELECT detalle.id, detalle.nro_vista
                      FROM Mdetamensajes AS detalle
                     WHERE detalle.mmensajes_id = mensaje.id
                       AND detalle.mfundos_id = ?
                  ORDER BY detalle.fecha_visto DESC NULLS LAST, detalle.id DESC
                     LIMIT 1
               ) AS lectura ON TRUE
              WHERE mensaje.mtipoganaderias_id IN (?, 0)
                AND mensaje.fecha_caducidad::date > CURRENT_DATE
                AND mensaje.activo = 1
                AND mensaje.status = 1
                AND COALESCE(lectura.nro_vista, 0) < 2
           ORDER BY mensaje.fecha_caducidad, mensaje.id
              LIMIT 1",
            array($mfundos_id, $tipo_ganaderia)
        );

        if (!$data || !isset($data[0])) {
            echo json_encode(array('resultado' => 'sin_mensajes', 'id_mensaje' => 0));
            break;
        }

        $mensaje = $data[0];
        if (!empty($mensaje->id_detalle)) {
            $registro_vista = Mdetamensaje::find((int)$mensaje->id_detalle);
        } else {
            $registro_vista = new Mdetamensaje();
        }
        $registro_vista->mfundos_id = $mfundos_id;
        $registro_vista->mmensajes_id = (int)$mensaje->id_mensaje;
        $registro_vista->fecha_visto = date('Y-m-d H:i:s');
        $registro_vista->nro_vista = min(2, (int)$mensaje->nro_vista + 1);

        if (!$registro_vista->save()) {
            echo json_encode(array('resultado' => 'error', 'id_mensaje' => 0));
            break;
        }

        echo json_encode(array(
            'resultado' => 'ok',
            'id_mensaje' => (int)$mensaje->id_mensaje,
            'titulo_mensaje' => $mensaje->titulo_mensaje,
            'cuerpo_mensaje' => $mensaje->cuerpo_mensaje
        ));

        break;

        #*******************************************************************************
    case 'save_contacto_ext':

        if ($nombres == '') {

            $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
          <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
          <h4>
          <i class="icon fa fa-warning"></i>
          Alerta!
          </h4>
          Indique el Nombre de Contacto.
          </div>');
        } else   if ($email == '') {

            $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
          <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
          <h4>
          <i class="icon fa fa-warning"></i>
          Alerta!
          </h4>
          Indique el Correo de Contacto.
          </div>');
        } else {
            //( A ) si se escribe un nombre se consulta si ya existe...
            $procesar = 0;
            session_start();
            $usuario_id = $_SESSION['idusuariox'];
            $hoy = date('d-m-Y');

            if ($id_contacto != '') {
                $consulta = Mcontacto::find('all', array('conditions' => array('id=? ', $id_contacto)));
                if ($consulta != null) {
                    $reg_contacto = Mcontacto::find($id_contacto);
                    $reg_contacto->user_modify = $usuario_id;
                    $reg_contacto->updated = $hoy;
                    $procesar = 1;
                }
            } else {
                $consulta = Mcontacto::find('all', array('conditions' => array('email=? ', $email)));
                if ($consulta == null) {
                    $reg_contacto = new Mcontacto();
                    $reg_contacto->user_create = $usuario_id;
                    $reg_contacto->created = $hoy;
                    $procesar = 1;
                }
            }
            if ($procesar != 0) {
                //( B ) si no existe lo guarda...

                $reg_contacto->name = $nombres;
                $reg_contacto->email = $email;
                $reg_contacto->telefono = $telefono;




                if ($reg_contacto->save()) {
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

                //echo json_encode( $respuesta );

            } else {
                //( B ) si exite el nombre verifica el status del registro...

                foreach ($consulta as $st) {

                    $estado = $st->status;
                    $ide = $st->id;
                }

                if ($estado != 1) {
                    //se activa mensaje modal para llevar el status a '1' ... ( o no ).

                    //echo 'MODAL PARA ACTIVAR REGISTRO GUARDADO';
                    exit();

                    $respuesta = array('resultado' => 'alert', 'mensaje' => '<input type="hidden" id="id" value="' . $ide . '">
                <div class="alert alert-danger alert-dismissable">
                <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                <h4>
                <i class="icon fa fa-ban"></i>
                Alerta!
                </h4>

                Registro inactivo . ¿Desea incluir el correo [' . $email . '] ?

                    <div class="modal-footer" >
                      <button type="button" id="no" class="btn btn-outline pull-left" data-dismiss="alert" onClick="no()">No</button>
                      <button type="button" id="silo" class="btn btn-outline pull-left" onClick="si()" >Si</button>
                    </div>
                </div>');
                } else {

                    $respuesta = array('resultado' => 'alert', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
                <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                <h4>
                <i class="icon fa fa-ban"></i>
                Alerta!
                </h4>
                El correo [' . $email . '] ya se encuentra registrado.
                </div>');
                }
                // echo json_encode( $respuesta );
            }
        }
        echo json_encode($respuesta);

        break;
}
//end switch
