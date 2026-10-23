<?php

require_once '../models/Manimale.php';
require_once '../models/Mcontrolsanitario.php';
require_once '../models/Vcontrolsanitario.php';
require_once '../models/Vcontrolsanitariosxlote.php';
require_once '../models/Manimxlotestemp.php';
require_once '../models/Mcontrolsanitarioxlote.php';
require_once '../models/Mdetacsxlote.php';
require_once '../models/Vanimalesctlsantlote.php';
require_once '../models/Mdetacrlsanitariolote.php';
require_once '../models/Mdetatemp.php';





include '../lib/sanitizar.php';

date_default_timezone_set('America/Caracas');

@$action = ($_POST['action']);
@$id = ($_POST['id_contsanitario']);
@$id_contsanitario = ($_POST['id_contsanitario']);

@$record = ($_POST['record']);

/***********************************/
@$cod_animal = ($_POST['cod_animal']);
@$fecha_control = ($_POST['fecha_control']);
@$fecha_prox_control = ($_POST['fecha_prox_control']);
@$evento = ($_POST['evento']);
@$producto = ($_POST['producto']);
@$dosis = ($_POST['dosis']);
@$responsable = ($_POST['responsable']);
@$observaciones = ($_POST['observaciones']);

/**********************************/

switch ($action) {

    //// **************************

    case 'add':

        if ($cod_animal == '') {

            $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
        <h4>
        <i class="icon fa fa-warning"></i>
        Alerta!
        </h4>
        Ingrese El Código de la Hembra.
        </div>');
        } else if ($fecha_control == '') {

            $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
        <h4>
        <i class="icon fa fa-warning"></i>
        Alerta!
        </h4>
        Indique la Fecha del Control.
        </div>');
        } else {
            //( A ) si se escribe un nombre se consulta si ya existe...

            // $consulta = Mcontrolsanitario::find( 'all', array( 'conditions' => array( 'id=?', $id ) ) );

            session_start();
            $usuario_id = $_SESSION['idusuariox'];
            $mfundos_id = $_SESSION['mfundos_id'];
            $id_sesion_usuario = $_SESSION['instante'];

            $date = new DateTime($fecha_control);
            $f_control = $date->format('Y-m-d');

            @$consulta = Mcontrolsanitario::find_by_sql('select  id 
                     from McontrolsanitarioS where mfundos_id=' . $mfundos_id . " and
                    fecha_control = CAST ('$f_control' AS DATE) and                                                
                    codigo_rgb_id_animal='$cod_animal' and status=1;");


            if ($consulta == null) {
                //( B ) si no existe lo guarda...

                $hoy = date('d-m-Y');

                $reg_control_san = new Mcontrolsanitario();

                $reg_control_san->codigo_rgb_id_animal = $cod_animal;
                $reg_control_san->fecha_control = $fecha_control;
                if ($fecha_prox_control != '') {
                    $reg_control_san->fecha_prox_control = $fecha_prox_control;
                } else {
                    $reg_control_san->fecha_prox_control = $hoy;
                }

                $reg_control_san->evento = $evento;
                $reg_control_san->mfundos_id = $mfundos_id;
                $reg_control_san->responsable = $responsable;
                $reg_control_san->observaciones = $observaciones;
                $reg_control_san->id_sesion_usuario = $id_sesion_usuario;
                $reg_control_san->user_create = $usuario_id;
                $reg_control_san->created = $hoy;

                if ($reg_control_san->save()) {
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

                $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
         <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
         <h4>
         <i class="icon fa fa-warning"></i>
         Alerta!
         </h4>
         El Animal con Código [' . $cod_animal . '] ya se encuentra registrada para la Fecha [' . $fecha_control . '].
         </div>');
            }
        }
        echo json_encode($respuesta);

        break;

    //// **************************

    case 'add_cs_lote':


        $lote_animal = ($_POST['lote_animal']);



        if ($lote_animal == '') {

            $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
        <h4>
        <i class="icon fa fa-warning"></i>
        Alerta!
        </h4>
        Debe Seleccionar un Lote.
        </div>');
        } else if ($fecha_control == '') {

            $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
        <h4>
        <i class="icon fa fa-warning"></i>
        Alerta!
        </h4>
        Indique la Fecha del Control.
        </div>');
        } else {
            //( A ) si se escribe un nombre se consulta si ya existe...


            session_start();
            $usuario_id = $_SESSION['idusuariox'];
            $mfundos_id = $_SESSION['mfundos_id'];
            $id_sesion_usuario = $_SESSION['instante'];

            $date = new DateTime($fecha_control);
            $f_control = $date->format('Y-m-d');

            $hoy = date('d-m-Y');
            $contados = 0;


            $data_genera_codigo = Mcontrolsanitarioxlote::find_by_sql("SELECT count(id) as cant_animal
            FROM Mcontrolsanitarioxlotes
            WHERE status=1 and mfundos_id=" . $mfundos_id . ' ;');

            foreach ($data_genera_codigo as $fund_ani) {
                $cant_animal = $fund_ani->cant_animal;
            }
            if ($cant_animal > 0) {
                $contados = $cant_animal + 1;
            } else {
                $contados = 1;
            }
            $cod_rcs = 'RCS-0' . $mfundos_id . '-0' . $contados;



            $reg_control_san_lote = new Mcontrolsanitarioxlote();

            $reg_control_san_lote->codigo_mcs_lote = $cod_rcs;


            $reg_control_san_lote->fecha_control = $fecha_control;

            $reg_control_san_lote->fecha_prox_control = $fecha_prox_control;
            $reg_control_san_lote->evento = $evento;
            $reg_control_san_lote->mlotes_id = $lote_animal;

            $reg_control_san_lote->responsable = $responsable;
            $reg_control_san_lote->observaciones = $observaciones;
            $reg_control_san_lote->user_create = $usuario_id;
            $reg_control_san_lote->mfundos_id = $mfundos_id;
            $reg_control_san_lote->created = $hoy;

            if ($reg_control_san_lote->save()) {

                ///   Se busca el codigo recien gerado para grabar los detalles de los productos aplicados


                $data_search = Mcontrolsanitarioxlote::find_by_sql("SELECT max(id) as codigo_mcs_lote
                    FROM Mcontrolsanitarioxlotes
                    WHERE status=1 and mfundos_id=" . $mfundos_id . ' ;');

                foreach ($data_search as $reg_cslote) {
                    $codigo_mcs_lote = $reg_cslote->codigo_mcs_lote;
                }


                ///   Se buscan los detalles de los productos aplicados de forma temporal


                //////////////////////////////////////////////////////////////////////////////////////////


                $data_search_detalle = Mdetatemp::find_by_sql("SELECT mproductos_id,dosis,detalles
        FROM Mdetatemps
         WHERE 
          mfundos_id=" . $mfundos_id . "  and
          mcontrolsanitarioxlotes_id_temp=" . $usuario_id . "  and mlote_id=" . $lote_animal . ";");


               

                $borrar_productos_temp = false;

                foreach ($data_search_detalle as $reg_cslote_detalle) {
                     $reg_deta_csl = new Mdetacrlsanitariolote();
                    $reg_deta_csl->mcontrolsanitarioxlotes_id = $codigo_mcs_lote;

                    $mproductos_id = $reg_cslote_detalle->mproductos_id;
                    $dosis = $reg_cslote_detalle->dosis;
                    $detalles = $reg_cslote_detalle->detalles;


                    $reg_deta_csl->mproductos_id = $mproductos_id;
                    $reg_deta_csl->dosis = $dosis;
                    $reg_deta_csl->detalles = $detalles;

                    $reg_deta_csl->mfundos_id = $mfundos_id;
                    $reg_deta_csl->user_create = $usuario_id;


                    $reg_deta_csl->save();
                    $borrar_productos_temp = true;
                }

                if ($borrar_productos_temp) {
                    $borrar_temp = Mdetatemp::find('all', array('conditions' => array('mcontrolsanitarioxlotes_id_temp=?', $usuario_id)));

                    foreach ($borrar_temp as $rs_temp) {


                        $rs_temp->delete();
                    }
                }


                //////////////////////////////////////////////////////////////////////////////////////////


                @$data_lote = Vanimalesctlsantlote::find_by_sql("select id_manimales from Vanimalesctlsantlotes
                                     where mfundos_id=" . $mfundos_id . " 
                                     and id= " . $lote_animal . " 
									 and id_manimales not in (select id_manimal from manimxlotestemps
									 where id_lotes= " . $lote_animal . "  );");

                //////////////////////////////////////////////////////////////////////////////////////////

                


                $borrar_lote = false;

                foreach ($data_lote as $reg_mdetacsxlote) {
                    $reg_deta_lote = new Mdetacsxlote();

                    $reg_deta_lote->mcontrolsanitarioxlotes_id = $codigo_mcs_lote;

                    $reg_deta_lote->codigo_mcs_lote =  $cod_rcs;
                    $reg_deta_lote->id_lotes =  $lote_animal;
                    $reg_deta_lote->mfundos_id = $mfundos_id;
                    $reg_deta_lote->user_create = $usuario_id;
                    $reg_deta_lote->created = $hoy;

                    $reg_deta_lote->manimales_id = $reg_mdetacsxlote->id_manimales;
                    $reg_deta_lote->save();

                    $borrar_lote = true;
                }

                    // $reg_deta_lote->save();

                //////////////////////////////////////////////////////////////////////////////////////////

                if ($borrar_lote) {

                    $borrar_temp_lote = Manimxlotestemp::find('all', array('conditions' => array('id_musuario=?', $usuario_id)));

                    foreach ($borrar_temp_lote as $rsa_temp) {


                        $rsa_temp->delete();
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
                echo json_encode($respuesta);

                break;
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


    case 'edit_cs_lote':
        $id_cs_x_lote = ($_POST['id_cs_x_lote']);



        if ($fecha_control == '') {

            $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
        <h4>
        <i class="icon fa fa-warning"></i>
        Alerta!
        </h4>
        Indique la Fecha del Control.
        </div>');
        } else {
            //( A ) si se escribe un nombre se consulta si ya existe...


            session_start();
            $usuario_id = $_SESSION['idusuariox'];

            $hoy = date('d-m-Y');


            $update_control_x_lote =  Mcontrolsanitarioxlote::find($id_cs_x_lote);



            $update_control_x_lote->fecha_control = $fecha_control;

            if ($fecha_prox_control != '') {
                $update_control_x_lote->fecha_prox_control = $fecha_prox_control;
            } else {
                $update_control_x_lote->fecha_prox_control = $hoy;
            }

            $update_control_x_lote->evento = $evento;
            $update_control_x_lote->responsable = $responsable;
            $update_control_x_lote->observaciones = $observaciones;
            $update_control_x_lote->user_modify = $usuario_id;
            $update_control_x_lote->updated = $hoy;

            if ($update_control_x_lote->save()) {


                $respuesta = array('resultado' => 'ok', 'mensaje' => '<div class="alert alert-success alert-dismissable">
                <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                <h4>
                <i class="icon fa fa-check"></i>
                Alerta!
                </h4>
                Los datos han sido registrados exitosamente !.
                </div>');
                echo json_encode($respuesta);

                break;
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


    case 'edit':

        if ($evento == '') {

            $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
        <h4>
        <i class="icon fa fa-warning"></i>
        Alerta!
        </h4>
        Ingrese El Evento.
        </div>');
        } else if ($fecha_control == '') {

            $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
        <h4>
        <i class="icon fa fa-warning"></i>
        Alerta!
        </h4>
        Indique la Fecha del Control.
        </div>');
        } else {
            //( A ) si se escribe un nombre se consulta si ya existe...

            $id_contsanitario = intval($id_contsanitario);
            session_start();
            $usuario_id = $_SESSION['idusuariox'];
            $mfundos_id = $_SESSION['mfundos_id'];
            $hoy = date('d-m-Y');
            $date = new DateTime($fecha_control);
            $f_control = $date->format('Y-m-d');
            @$consulta = Mcontrolsanitario::find_by_sql("select  codigo_rgb_id_animal from Mcontrolsanitarios 
        where mfundos_id=" . $mfundos_id . " and codigo_rgb_id_animal='$cod_animal'
        and codigo_rgb_id_animal not in (select codigo_rgb_id_animal 
            from Mcontrolsanitarios 
             where codigo_rgb_id_animal='$cod_animal' and
                id=" . $id_contsanitario . ' and mfundos_id=' . $mfundos_id . " and                                                        
                fecha_control = CAST ('$f_control' AS DATE)) and status=1;");

            if ($consulta == null) {

                @$consulta_data = Mcontrolsanitario::find_by_sql("select  id from Mcontrolsanitarios 
                where id=" . $id_contsanitario . '  and mfundos_id=' . $mfundos_id . '  and  status=1;');

                if ($consulta_data != null) {

                    $reg_control_san = Mcontrolsanitario::find($id_contsanitario);
                    if ($fecha_prox_control != '') {
                        $reg_control_san->fecha_prox_control = $fecha_prox_control;
                    } else {
                        $reg_control_san->fecha_prox_control = $hoy;
                    }

                    $reg_control_san->evento = $evento;
                    $reg_control_san->responsable = $responsable;
                    $reg_control_san->observaciones = $observaciones;
                    $reg_control_san->user_modify = $usuario_id;
                    $reg_control_san->updated = $hoy;

                    if ($reg_control_san->save()) {
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

                        $estado_1 = $st->status;
                        $ide = $st->id;
                    }

                    if ($estado_1 != 1) {
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

              Registro inactivo . ¿Desea incluir [' . $cod_animal . '] ?

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
              El Tipo [' . $cod_animal . '] ya se encuentra registrado.
              </div>');
                    }
                    // echo json_encode( $respuesta );
                }
            } else {

                $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
              <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
              <h4>
              <i class="icon fa fa-ban"></i>
              Alerta!
              </h4>
              Fecha de Control Repetida para el Animal  .
              </div>');
            }
        }
        echo json_encode($respuesta);

        break;

    /// LISTADO DE ANIMALES APTOS PARA EL SELECTOR

    case 'listar_candidatos_control_sanitario':
        require_once __DIR__ . '/../lib/animales_aptos_helper.php';
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        $filas = bovs_animales_aptos_listar('controlsanitario');

        echo json_encode(array(
            'resultado' => 'ok',
            'filas'     => $filas,
            'total'     => count($filas)
        ));
        break;

    /// BUSCA LOS DATOS

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

            @$data = Manimale::find_by_sql('select  id, mestatus_id as estatus, nombre as nombre,codigo_rgb_id_animal as cod_animal from Manimales where mfundos_id=' . $mfundos_id . " and mestatus_id=1 $cadena ;");

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
                  El ' . $criterio . ' del Animal NO EXISTE!!.
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
                  No existe un Animal con el ' . $criterio . ' ' . $nro_arete_codigo . ' !!.
                  </div>');
            }
        }
        echo json_encode($resp);
        break;

    case 'delete':

        if ($record != null) {

            session_start();
            $usuario_id = $_SESSION['idusuariox'];
            $rol = $_SESSION['rolx'];
            $hoy = date('d-m-Y');

            if ($usuario_id != '') {

                $reg_control_san = Mcontrolsanitario::find($record);
                $reg_control_san->user_modify = $usuario_id;
                $reg_control_san->updated = $hoy;
                $reg_control_san->status = 0;

                if ($reg_control_san->save()) {

                    echo '<div class="alert alert-success alert-dismissable">
                    <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                    <h4>
                    <i class="fa fa-check"></i>
                    Alerta!
                    </h4>
                    Registro Eliminado exitosamente...
                  </div>';
                } else {
                    echo  '<div class="alert alert-danger alert-dismissable">
                  <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                  <h4>
                  <i class="icon fa fa-ban"></i>
                  Alerta!
                  </h4>
                  Disculpe! Ha ocurrido un error al eliminar el registro..
                  </div>';
                }
            } else {

                echo  '<div class="alert alert-warning alert-dismissable">
                  <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                  <h4>
                  <i class="icon fa fa-warning"></i>
                  Alerta!
                  </h4>
                  Disculpe! Usted no puede realizar esta transacción.
                  </div>';
            }
        }
        break;



    #*******************************************************************************
    case 'delete_X_lote':
        $id_cs_x_lote = ($_POST['record']);

        if ($id_cs_x_lote != null) {

            session_start();
            $usuario_id = $_SESSION['idusuariox'];
            $rol = $_SESSION['rolx'];
            $hoy = date('d-m-Y');

            if ($usuario_id != '') {

                $reg_control_san = Mcontrolsanitarioxlote::find($id_cs_x_lote);
                $reg_control_san->user_modify = $usuario_id;
                $reg_control_san->updated = $hoy;
                $reg_control_san->status = 0;

                if ($reg_control_san->save()) {

                    echo '<div class="alert alert-success alert-dismissable">
                    <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                    <h4>
                    <i class="fa fa-check"></i>
                    Alerta!
                    </h4>
                    Registro Eliminado exitosamente...
                  </div>';
                } else {
                    echo  '<div class="alert alert-danger alert-dismissable">
                  <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                  <h4>
                  <i class="icon fa fa-ban"></i>
                  Alerta!
                  </h4>
                  Disculpe! Ha ocurrido un error al eliminar el registro..
                  </div>';
                }
            } else {

                echo  '<div class="alert alert-warning alert-dismissable">
                  <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                  <h4>
                  <i class="icon fa fa-warning"></i>
                  Alerta!
                  </h4>
                  Disculpe! Usted no puede realizar esta transacción.
                  </div>';
            }
        }
        break;

    #*******************************************************************************


    case 'search':

        if ($id_contsanitario != null) {

            @$data = Vcontrolsanitario::find('all', array('conditions' => array('id=?', $id_contsanitario)));

            if ($data != null) {

                foreach ($data as $rs) {

                        $fecha_control = '';
                        $fecha_prox_control = '';
               
                if ( $rs->fecha_control != '' ) {
                    $fecha_control = ( string )$rs->fecha_control->format( 'd-m-Y' );
                }

                  if ( $rs->fecha_prox_control != '' ) {
                    $fecha_prox_control = ( string )$rs->fecha_prox_control->format( 'd-m-Y' );
                }


                    $resp = array(
                        'id_contsanitario' => $rs->id,
                        'cod_animal' => $rs->codigo_rgb_id_animal,
                        'nombre_animal' => $rs->nombre_animal,
                        'fecha_control' => $fecha_control,
                        'fecha_prox_control' =>$fecha_prox_control,
                        'evento' => $rs->evento,
                        'responsable' => $rs->responsable,
                        'observaciones' => $rs->observaciones

                    );
                }

                echo json_encode($resp);
            }
        }
        break;

    #*******************************************************************************
    case 'search_X_lote':

        @$id_cs_x_lote = ($_POST['id_cs_x_lote']);


        if ($id_cs_x_lote != null) {

            @$data = Vcontrolsanitariosxlote::find('all', array('conditions' => array('id=?', $id_cs_x_lote)));

            if ($data != null) {

                foreach ($data as $rs) {

                                  $fecha_control = '';
                        $fecha_prox_control = '';
               
                if ( $rs->fecha_control != '' ) {
                    $fecha_control = ( string )$rs->fecha_control->format( 'd-m-Y' );
                }

                  if ( $rs->fecha_prox_control != '' ) {
                    $fecha_prox_control = ( string )$rs->fecha_prox_control->format( 'd-m-Y' );
                }


                    $resp = array(
                        'codigo_mcs_lote' => $rs->codigo_mcs_lote,
                        'lote_animal' => $rs->nombre_lote,
                        'id_lote' => $rs->mlotes_id,
                        'fecha_control' => $fecha_control,
                        'fecha_prox_control' => $fecha_prox_control,
                        'evento' => $rs->evento,
                        'responsable' => $rs->responsable,
                        'observaciones' => $rs->observaciones

                    );
                }

                echo json_encode($resp);
            }
        }
        break;

    // Nuevas Busquedas

    case 'buscar_maxid':
        session_start();
        $mfundos_id = $_SESSION['mfundos_id'];
        $id_sesion_usuario = $_SESSION['instante'];

        @$data = Mcontrolsanitario::find_by_sql('SELECT max(id) as id_control_sanitario 
                                                     from Mcontrolsanitarios 
                                                     where id_sesion_usuario=' . $id_sesion_usuario . ' and 
                                                           mfundos_id=' . $mfundos_id . ' and status = 1');
        $idcontrol = 0;
        if ($data != null) {
            foreach ($data as $rs) {
                if ($rs->id_control_sanitario == null) {
                    $idcontrol = 0;
                } else {
                    $idcontrol = $rs->id_control_sanitario;
                }
            }
        }

        echo json_encode($idcontrol);
        break;
    #*******************************************************************************
    #*******************************************************************************

    case 'search_crl_animal':

        $buscar = intval($_POST['buscar_cod']);
        $nro_arete_codigo = ($_POST['rgb_animal']);

        $cadena = ' ';

        session_start();
        $mfundos_id = $_SESSION['mfundos_id'];

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

        @$data = Vcontrolsanitario::find_by_sql('select id as cod_animal from Vcontrolsanitarios where mfundos_id=' . $mfundos_id . "  $cadena;");

        if ($data != null) {

            foreach ($data as $rs) {

                $resp = array(
                    'cod_animal' => $rs->cod_animal

                );
            }
        } else {
            $resp = array(
                'cod_animal' => 0

            );
        }
        echo json_encode($resp);

        break;

    #*******************************************************************************

    #******************************************************************************
    case 'quitar_animal':

        session_start();
        $usuario_id = $_SESSION['idusuariox'];
        $mfundos_id = $_SESSION['mfundos_id'];

        $id_animal_lote = ($_POST['id_animal_lote']);
        $id_lote = ($_POST['id_lote']);


        if ($id_animal_lote != null) {



            $reg_temp = new Manimxlotestemp();

            $reg_temp->id_manimal = $id_animal_lote;
            $reg_temp->id_lotes = $id_lote;
            $reg_temp->mfundos_id = $mfundos_id;
            $reg_temp->id_musuario = $usuario_id;

            if ($reg_temp->save()) {


                $respuesta = array('resultado' => 'ok', 'mensaje' => '<div class="alert alert-success alert-dismissable">
                 <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                 <h4>
                 <i class="icon fa fa-check"></i>
                 Excelente!
                 </h4>
                 El Animal ha sido quitado exitosamente!.
                 </div>');
            } else {

                $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
                     <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                     <h4>
                     <i class="icon fa fa-ban"></i>
                     Alerta!
                     </h4>
                     Error al intentar quitar el Animal.
                     </div>');
            }
        } else {

            $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
                  <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                  <h4>
               <i class="icon fa fa-ban"></i>
               Alerta!
               </h4>
               Error al intentar quitar el Animal.
               </div>');
        }

        echo json_encode($respuesta);

        break;

  case 'contar_anim_lote':

        $id_cs_x_lote = ($_POST['id_cs_x_lote']);

           $data = Mdetacsxlote::find_by_sql("SELECT count(id) as cant_animal
            FROM Mdetacsxlotes
            WHERE  mcontrolsanitarioxlotes_id=" . $id_cs_x_lote . " and status=1 ; ");

                 if ($data != null) {

            foreach ($data as $rs) {

                $resp = array(
                    'cantidad_animales' => $rs->cant_animal

                );
            }
        } else {
            $resp = array(
                'cantidad_animales' => 0

            );
        }
        echo json_encode($resp);
             break;
}

//end switch