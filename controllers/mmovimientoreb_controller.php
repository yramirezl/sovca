<?php
require_once __DIR__ . '/../lib/catalogo_fundo_helper.php';
require_once '../models/Manimale.php';
require_once '../models/Mmovanimxlotestemp.php';
require_once '../models/Mdetamovanimxlote.php';
require_once '../models/Vmovimientoxlote.php';
require_once '../models/Mmovanimxlote.php';




date_default_timezone_set('America/Caracas');
@$action = ($_POST['action']);
@$id = ($_POST['id_lote']);
@$id_lote = ($_POST['id_lote']);
/***********************************/

@$lote_animal = ($_POST['lote_animal']);
@$lote_destino = ($_POST['lote_destino']);
@$fecha_mov = ($_POST['fecha_mov']);
@$motivo = ($_POST['motivo']);
@$responsable = ($_POST['responsable']);
@$observaciones = ($_POST['observaciones']);

@$lote_origen = ($_POST['lote_origen']);


/**********************************/

switch ($action) {


    #******************************************************************************

    case 'mover_animales_lote':


        $lote_origen = ($_POST['lote_origen']);
        $lote_destino = ($_POST['lote_destino']);

        $total_lote_origen = ($_POST['total_lote_origen']);
        $total_animales_exc = ($_POST['total_animales_exc']);



        if ($lote_origen == '') {

            $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
        <h4>
        <i class="icon fa fa-warning"></i>
        Alerta!
        </h4>
        Debe Seleccionar el Lote de Origen.
        </div>');
        } else  if ($lote_destino == '') {

            $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
        <h4>
        <i class="icon fa fa-warning"></i>
        Alerta!
        </h4>
        Debe Seleccionar el Lote de Destino.
        </div>');
        } else if ($fecha_mov == '') {

            $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
        <h4>
        <i class="icon fa fa-warning"></i>
        Alerta!
        </h4>
        Indique la Fecha del Movimiento.
        </div>');
        } else {
            //( A ) si se escribe un nombre se consulta si ya existe...


            session_start();
            $usuario_id = $_SESSION['idusuariox'];
            $mfundos_id = $_SESSION['mfundos_id'];
            $id_sesion_usuario = $_SESSION['instante'];

            $date = new DateTime($fecha_mov);
            $f_mov = $date->format('Y-m-d');

            $hoy = date('d-m-Y');
            $contados = 0;
            $continuar = false;


            $reg_mov_lote = new Mdetamovanimxlote();

            $reg_mov_lote->fecha_mov = $f_mov;
            $reg_mov_lote->motivo = $motivo;
            $reg_mov_lote->id_lote_origen = $lote_origen;
            $reg_mov_lote->id_lote_destino = $lote_destino;
            $reg_mov_lote->cantidad_lote_origen = $total_lote_origen;
            $reg_mov_lote->cantidad_exc_lote_origen = $total_animales_exc;
            $reg_mov_lote->responsable = $responsable;
            $reg_mov_lote->observaciones = $observaciones;
            $reg_mov_lote->id_musuario = $usuario_id;
            $reg_mov_lote->mfundos_id = $mfundos_id;
            $reg_mov_lote->created = $hoy;

            if ($reg_mov_lote->save()) {


                ///  Se buscan los animales que estabn en el lote anterior para luego pasarlos al lote destino



                $data_mov = Vmovimientoxlote::find_by_sql("select id_manimales from Vmovimientoxlotes
                                     where mfundos_id=" . $mfundos_id . " 
                                     and id= " . $lote_origen . " 
									 and id_manimales not in (select id_manimal from mmovanimxlotestemps
									 where id_lotes= " . $lote_origen . "  )
									 order by id_manimales asc;");



                $borrar_lote = false;
                foreach ($data_mov as $reg_movlote) {

                    $id_animal = $reg_movlote->id_manimales;

                    $animales = Manimale::find($id_animal);
                    $animales->mlotes_id = $lote_destino;
                    $animales->save();
                    $borrar_lote = true;
                    $continuar = true;
                }

                ///////////////////////////////////////////////////////////////////////////////////////////
                $codigo_mov_lote = 0;

                $data_search = Mdetamovanimxlote::find_by_sql("SELECT max(id) as cod_mov_lote
                    FROM Mdetamovanimxlotes
                    WHERE status=1 and mfundos_id=" . $mfundos_id . ' ;');

                foreach ($data_search as $reg_mov_lote) {
                    $codigo_mov_lote = $reg_mov_lote->cod_mov_lote;
                }
                ///////////////////////////////////////////////////////////////////////////////////////////
                $id_animal = 0;
                foreach ($data_mov as $reg_movlote) {

                    $reg_deta_mov_lote = new Mmovanimxlote();
                    $id_animal = $reg_movlote->id_manimales;

                    $reg_deta_mov_lote->id_mdetamovanimxlote = $codigo_mov_lote;

                    $reg_deta_mov_lote->id_manimal =  $id_animal;
                    $reg_deta_mov_lote->mfundos_id = $mfundos_id;
                    $reg_deta_mov_lote->id_musuario = $usuario_id;
                    $reg_deta_mov_lote->save();

                    $borrar_lote = true;
                }

                ////////////////////////////////////////////////////////////////////////////////////////

                if ($borrar_lote) {

                    $borrar_temp = Mmovanimxlotestemp::find('all', array('conditions' => array('id_musuario=?', $usuario_id)));

                    if ($borrar_temp != null) {
                        foreach ($borrar_temp as $rs_temp) {


                            $rs_temp->delete();
                            $continuar = true;
                        }
                    }
                }



                if ($continuar) {

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


    #******************************************************************************
    case 'contador':

        $id_lote_origen = ($_POST['lote_origen']);
        session_start();
        $usuario_id = $_SESSION['idusuariox'];
        $mfundos_id = $_SESSION['mfundos_id'];

        $hoy = date("d-m-Y");

        $data = Vanimaleslote::find_by_sql("select cantidad_animales from Vanimaleslotes where mfundos_id=" . $mfundos_id . " and id = " . $id_lote_origen . " ;");
        $total_animales_mov = 0;
        $total_animales = 0;
        $total_animales_exc = 0;


        if ($data != null) {
            foreach ($data as $rs) {

                $total_animales = $rs->cantidad_animales;
            }
            @$data_temp = Mmovanimxlotestemp::find_by_sql("select count(id) as cantidad from Mmovanimxlotestemps where mfundos_id=" . $mfundos_id . " and id_lotes = " . $id_lote_origen . "  ;");


            if ($data_temp != null) {
                foreach ($data_temp as $rs_t) {

                    $total_animales_exc = $rs_t->cantidad;
                }
            }


            $total_animales_mov = $total_animales - $total_animales_exc;

            @$resp = array(
                "total_animales" => $total_animales,
                "total_animales_exc" => $total_animales_exc,
                "total_animales_mov" => $total_animales_mov

            );

            echo json_encode($resp);
        }
        break;

    #******************************************************************************


    case 'quitar_animal':

        session_start();
        $usuario_id = $_SESSION['idusuariox'];
        $mfundos_id = $_SESSION['mfundos_id'];

        $id_animal_lote = ($_POST['id_animal_lote']);
        $id_lote = ($_POST['id_lote']);


        if ($id_animal_lote != null) {



            $reg_temp = new Mmovanimxlotestemp();

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

    case 'get_lote_origen':

        @session_start();
        @$id_sesion = $_SESSION['idsessionx'];
        $usuario_id = $_SESSION['idusuariox'];

        @$mfundos_id = $_SESSION['mfundos_id'];
        @$rol = $_SESSION['rolx'];

        $mfundos_id = intval($mfundos_id);
    /* Catálogos del FUNDO ACTIVO (MODO SOPORTE incluido): si hay un fundo
       en la sesión se usa la misma rama que el productor de ese fundo, en
       vez de devolver los registros de TODOS los fundos (rol 1/4). */
    if ( bovs_catalogo_fundo_id() > 0 && ( $rol == 1 || $rol == 4 ) ) {
        $rol = 2;
    }
        $borrar_productos_temp = true;
        if (!empty($id_sesion) && ($rol == 1)) {
            //privilegios del programador y Supervisor

            $data = Mlote::find_by_sql('select id,nombre from mlotes where status>0 order by id desc;');
        } else if (!empty($id_sesion) && (($rol == 2) || ($rol == 3))) {

            if ($borrar_productos_temp) {
                $borrar_temp = Mmovanimxlotestemp::find('all', array('conditions' => array('id_musuario=?', $usuario_id)));

                if ($borrar_temp != null) {
                    foreach ($borrar_temp as $rs_temp) {


                        $rs_temp->delete();
                    }
                }
            }

            $data = Mlote::find_by_sql('select id,nombre from mlotes where (mfundos_id=' . $mfundos_id . ' ) and status>0 order by id desc;');
        }

        if ($data != null) {
            $resp = '<option value="">--</option>';
            foreach ($data as $rs) {
                $resp .= '<option value="' . $rs->id . '">' . $rs->nombre . '</option>';
                $resp .= '<hidden>';
            }
        } else {
            $resp = '<option value="">No hay LOTE para Agregar</option>';
        }

        echo $resp;
        break;

    case 'get_lote_destino':

        @session_start();
        @$id_sesion = $_SESSION['idsessionx'];
        @$mfundos_id = $_SESSION['mfundos_id'];
        @$rol = $_SESSION['rolx'];
        @$lote_origen = $_POST['lote_origen'];
        @$lote_destino = $_POST['lote_destino'];



        $mfundos_id = intval($mfundos_id);
    /* Catálogos del FUNDO ACTIVO (MODO SOPORTE incluido): si hay un fundo
       en la sesión se usa la misma rama que el productor de ese fundo, en
       vez de devolver los registros de TODOS los fundos (rol 1/4). */
    if ( bovs_catalogo_fundo_id() > 0 && ( $rol == 1 || $rol == 4 ) ) {
        $rol = 2;
    }

        if (!empty($id_sesion) && ($rol == 1)) {
            //privilegios del programador y Supervisor

            $data = Mlote::find_by_sql('select id,nombre from mlotes where status>0 order by id desc;');
        } else if (!empty($id_sesion) && (($rol == 2) || ($rol == 3))) {
            $data = Mlote::find_by_sql('select id,nombre from mlotes where (mfundos_id=' . $mfundos_id . '  and id != ' . $lote_origen . ' ) and status>0 order by id desc;');
        }

        if ($data != null) {
            $resp = '<option value="">--</option>';
            foreach ($data as $rs) {
                $resp .= '<option value="' . $rs->id . '">' . $rs->nombre . '</option>';
                $resp .= '<hidden>';
            }
        } else {
            $resp = '<option value="">No hay LOTE para Agregar</option>';
        }

        echo $resp;
        break;


    #*******************************************************************************

    case 'search_deta':
        session_start();
        $mfundos_id = $_SESSION['mfundos_id'];
        $id_reg = $_POST['id_reg'];

        if ($id_reg != null) {

            $data = Mdetamovanimxlote::find_by_sql("select
											a.id as id_registro,
											a.cantidad_lote_origen,
											a.cantidad_exc_lote_origen,
											a.fecha_mov,
											a.motivo,
											a.responsable,
                                            a.observaciones,
											(select b.nombre from mlotes b where b.id =a.id_lote_origen ) as nombre_lote_origen,
											(select c.nombre from mlotes c where c.id =a.id_lote_destino ) as nombre_lote_destino

											from mdetamovanimxlotes a 
                                             where   
                                                  a.id=" . $id_reg . "   and                                       
                                                  a.mfundos_id=" . $mfundos_id . "  order by a.fecha_mov desc;");


            if ($data != null) {

                $cantidad_mov_lote = 0;

                foreach ($data as $rs) {


                    if ($rs->fecha_mov != "") {
                        $fecha_mov = (string)$rs->fecha_mov->format("d-m-Y");
                    }
                    $cantidad_mov_lote = $rs->cantidad_lote_origen - $rs->cantidad_exc_lote_origen;

                    $resp = array(
                        "id_registro" => $rs->id_registro,
                        "fecha_mov" => $fecha_mov,
                        "lote_origen" => $rs->nombre_lote_origen,
                        "lote_destino" => $rs->nombre_lote_destino,
                        "motivo" => $rs->motivo,
                        "responsable" => $rs->responsable,
                        "cantidad_lote_origen" => $rs->cantidad_lote_origen,
                        "cantidad_exc_lote_origen" => $rs->cantidad_exc_lote_origen,
                        "observaciones" => $rs->observaciones,
                        "cantidad_mov_lote_destino" => $cantidad_mov_lote
                    );
                }

                echo json_encode($resp);
            }
        }
        break;

    case 'quitar_reg':
        $id_reg = $_POST['id_reg'];


        if ($id_reg != null) {

            session_start();
            $usuario_id = $_SESSION['idusuariox'];
            $rol = $_SESSION['rolx'];
            $hoy = date('d-m-Y');

            if ($usuario_id != '') {

                $mov_lote = Mdetamovanimxlote::find($id_reg);
                $mov_lote->user_modify = $usuario_id;
                // $mov_lote->updated = $hoy;
                $mov_lote->status = 0;

                if ($mov_lote->save()) {

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
}
//end switch
