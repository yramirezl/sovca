<?php
require_once __DIR__ . '/../lib/catalogo_fundo_helper.php';
require_once '../models/Manimale.php';
require_once '../models/Mfundo.php';
require_once '../models/Vlotesprolechera.php';
require_once '../models/Vloteconsecutivopeso.php';



date_default_timezone_set( 'America/Caracas' );
@$action = ( $_POST[ 'action' ] );
@$id = ( $_POST[ 'id_lote' ] );
@$id_lote = ( $_POST[ 'id_lote' ] );
@$record = ( $_POST[ 'record' ] );
/***********************************/
@$nombre_lote = strtoupper( $_POST[ 'nombre_lote' ] );
@$detalles_lote = ( $_POST[ 'detalles_lote' ] );
/**********************************/

switch ( $action ) {
    case 'add':

    if ( $nombre_lote == '' ) {

        $respuesta = array( 'resultado'=>'error', 'mensaje'=>'<div class="alert alert-warning alert-dismissable">
            <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
            <h4>
            <i class="icon fa fa-warning"></i>
            Alerta!
            </h4>
            Ingrese el nombre del Lote
            </div>' );

    } else      if ( $detalles_lote == '' ) {

        $respuesta = array( 'resultado'=>'error', 'mensaje'=>'<div class="alert alert-warning alert-dismissable">
            <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
            <h4>
            <i class="icon fa fa-warning"></i>
            Alerta!
            </h4>
            Ingrese los Detalle o Descripción del LOTE
            </div>' );

    } else {
        //( A ) si se escribe un nombre se consulta si ya existe...

        session_start();
        $mfundos_id = $_SESSION[ 'mfundos_id' ];
        $consulta = Mlote::find( 'all', array( 'conditions' => array( 'nombre=? AND mfundos_id=?', $nombre_lote, $mfundos_id  ) ) );

        if ( $consulta == null ) {
            //( B ) si no existe lo guarda...

            session_start();
            $usuario_id = $_SESSION[ 'idusuariox' ];
            @$rol = $_SESSION[ 'rolx' ];

            if ( ( $rol != 1 ) && ( $rol != 4 ) ) {
                @$mfundos_id = $_SESSION[ 'mfundos_id' ];

            } else {
                $mfundos_id = 0;
            }
            $hoy = date( 'd-m-Y' );

            $lote = new Mlote();
            $lote->nombre = $nombre_lote;

            $lote->descripcion = $detalles_lote;

            $lote->mfundos_id = $mfundos_id;

            $lote->user_create = $usuario_id;
            $lote->created = $hoy;

            if ( $lote->save() ) {
                // da el mensaje de guardado...

                $respuesta = array( 'resultado'=>'ok', 'mensaje'=>'<div class="alert alert-success alert-dismissable">
                    <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                    <h4>
                    <i class="icon fa fa-check"></i>
                    Alerta!
                    </h4>
                    Los datos han sido registrados exitosamente !.
                    </div>' );

            } else {

                $respuesta = array( 'resultado'=>'error', 'mensaje'=>'<div class="alert alert-danger alert-dismissable">
         					<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
         					<h4>
         					<i class="icon fa fa-ban"></i>
         					Alerta!
         					</h4>
         					Error al registrar los datos.
         					</div>' );
            }

            //echo json_encode( $respuesta );

        } else {
            //( B ) si exite el nombre verifica el status del registro...

            foreach ( $consulta as $st ) {

                $estado = $st->status;
                $ide = $st->id;

            }

            if ( $estado != 1 ) {
                //se activa mensaje modal para llevar el status a '1' ... ( o no ).

                //echo 'MODAL PARA ACTIVAR REGISTRO GUARDADO';
                exit();

                $respuesta = array( 'resultado'=>'alert', 'mensaje'=>'<input type="hidden" id="id" value="'.$ide.'">
                  <div class="alert alert-danger alert-dismissable">
                  <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                  <h4>
                  <i class="icon fa fa-ban"></i>
                  Alerta!
                  </h4>

                  Registro inactivo . ¿Desea incluir ['.$nombre_lote.'] en el maestro de grupos, áreas, equipos o dependencias?

                      <div class="modal-footer" >
                        <button type="button" id="no" class="btn btn-outline pull-left" data-dismiss="alert" onClick="no()">No</button>
                        <button type="button" id="silo" class="btn btn-outline pull-left" onClick="si()" >Si</button>
                      </div>
                  </div>' );

            } else {

                $respuesta = array( 'resultado'=>'alert', 'mensaje'=>'<div class="alert alert-danger alert-dismissable">
                  <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                  <h4>
                  <i class="icon fa fa-ban"></i>
                  Alerta!
                  </h4>
                  La Raza ['.$nombre_lote.'] ya se encuentra registrada.
                  </div>' );

            }
            // echo json_encode( $respuesta );
        }

    }
    echo json_encode( $respuesta );

    break;
    #*******************************************************************************
    case 'edit':

    if ( $nombre_lote == '' ) {

        $respuesta = array( 'resultado'=>'error', 'mensaje'=>'<div class="alert alert-warning alert-dismissable">
            <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
            <h4>
            <i class="icon fa fa-warning"></i>
            Alerta!
            </h4>
            Ingrese el nombre de la Rdel LOTE.
            </div>' );

    } else      if ( $detalles_lote == '' ) {

        $respuesta = array( 'resultado'=>'error', 'mensaje'=>'<div class="alert alert-warning alert-dismissable">
            <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
            <h4>
            <i class="icon fa fa-warning"></i>
            Alerta!
            </h4>
            Ingrese los Detalle o Descripción del LOTE
            </div>' );

    } else {


        session_start();
        $mfundos_id = $_SESSION[ 'mfundos_id' ];
        $consulta = Mlote::find( 'all', array( 'conditions' => array( 'nombre=? AND mfundos_id=? and id!=?', $nombre_lote, $mfundos_id,$id_lote  ) ) );

        if ( $consulta == null ) {

            session_start();
            $usuario_id = $_SESSION[ 'idusuariox' ];
            $hoy = date( 'd-m-Y' );

            $lote = Mlote::find( $id );
            $lote->nombre = $nombre_lote;
            $lote->descripcion = $detalles_lote;

            $lote->user_modify = $usuario_id;
            $lote->updated = $hoy;

            if ( $lote->save() ) {

                $respuesta = array( 'resultado'=>'ok', 'mensaje'=>'<div class="alert alert-success alert-dismissable">
                    <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                    <h4>
                    <i class="icon fa fa-check"></i>
                    Alerta!
                    </h4>
                    Los datos han sido actualizados exitosamente !.
                    </div>' );

            } else {

                $respuesta = array( 'resultado'=>'error', 'mensaje'=>'<div class="alert alert-danger alert-dismissable">
                  <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                  <h4>
                  <i class="icon fa fa-ban"></i>
                  Alerta!
                  </h4>
                  Error al actualizar los datos.
                  </div>' );

            }

        } else {
            //( B ) si exite el nombre verifica el status del registro...

            foreach ( $consulta as $st ) {

                $estado = $st->status;
                $ide = $st->id;

            }

            if ( $estado != 1 ) {
                //se activa mensaje modal para llevar el status a '1' ... ( o no ).

                //echo 'MODAL PARA ACTIVAR REGISTRO GUARDADO';
                exit();

                $respuesta = array( 'resultado'=>'alert', 'mensaje'=>'<input type="hidden" id="id" value="'.$ide.'">
                  <div class="alert alert-danger alert-dismissable">
                  <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                  <h4>
                  <i class="icon fa fa-ban"></i>
                  Alerta!
                  </h4>

                  Registro inactivo . ¿Desea incluir ['.$nombre_lote.'] en el maestro de grupos, áreas, equipos o dependencias?

                      <div class="modal-footer" >
                        <button type="button" id="no" class="btn btn-outline pull-left" data-dismiss="alert" onClick="no()">No</button>
                        <button type="button" id="silo" class="btn btn-outline pull-left" onClick="si()" >Si</button>
                      </div>
                  </div>' );

            } else {

                $respuesta = array( 'resultado'=>'error', 'mensaje'=>'<div class="alert alert-danger alert-dismissable">
                  <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                  <h4>
                  <i class="icon fa fa-ban"></i>
                  Alerta!
                  </h4>
                  El Lote ['.$nombre_lote.'] ya se encuentra registrado.
                  </div>' );

            }
            // echo json_encode( $respuesta );
        }

    }
    echo json_encode( $respuesta );

    break;
    #*******************************************************************************

    case 'delete':

    if ( $id_lote != null ) {

        session_start();
        $usuario_id = $_SESSION[ 'idusuariox' ];
        @$sesion_mfundos_id = $_SESSION[ 'mfundos_id' ];

        $rol = $_SESSION[ 'rolx' ];
        $hoy = date( 'd-m-Y' );

        if ( ($usuario_id != '') && ( ( $rol != 3 )  ) ) {

            $valida_data = Manimale::find_by_sql( 'select count(id) as cantidad  
                                                from manimales 
                                                where mfundos_id='.$sesion_mfundos_id.' and
                                                      mlotes_id='.$id_lote.' and 
                                                      status>0;' );

            foreach ( $valida_data as $reg_ani ) {
                $cant_animal = $reg_ani->cantidad;
            }
            if ( $cant_animal == 0 ) {

                $lote = Mlote::find( $id_lote );
                $lote->user_modify = $usuario_id;
                $lote->updated = $hoy;
                $lote->status = 0;

                if ( $lote->save() ) {

                    echo'<div class="alert alert-success alert-dismissable">
                    <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                    <h4>
                    <i class="fa fa-check"></i>
                    Alerta!
                    </h4>
                    Registro Eliminado exitosamente...
                  </div>';

                } else {
                    echo	'<div class="alert alert-danger alert-dismissable">
                  <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                  <h4>
                  <i class="icon fa fa-ban"></i>
                  Alerta!
                  </h4>
                  Disculpe! Ha ocurrido un error al eliminar el registro..
                  </div>';

                }

            } else {

                echo	'<div class="alert alert-danger alert-dismissable">
                  <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                  <h4>
                  <i class="icon fa fa-danger"></i>
                  Alerta!
                  </h4>
                  Disculpe! Usted no puede realizar esta transacción. Existen Animales asociados a este LOTE
                  </div>';
            }

        } else {

            echo	'<div class="alert alert-warning alert-dismissable">
                  <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                  <h4>
                  <i class="icon fa fa-warning"></i>
                  Alerta!
                  </h4>
                  Disculpe! Usted no puede realizar esta transacción.
                  </div>';

        }
    } else {
        echo	'<div class="alert alert-danger alert-dismissable">
                <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                <h4>
                <i class="icon fa fa-ban"></i>
                Alerta!
                </h4>
                Disculpe! Ha ocurrido un error al eliminar el registro..
                </div>';

    }
    break;

    #*******************************************************************************
    case 'search':
    session_start();
    $mfundos_id = $_SESSION[ 'mfundos_id' ];
    if ( $id_lote != null ) {

        @$data = Mlote::find( 'all', array( 'conditions' => array( 'id=? and mfundos_id=?', $id_lote, $mfundos_id ) ) );

        if ( $data != null ) {

            foreach ( $data as $rs ) {

                $resp = array(
                    'id'=>$rs->id,
                    'nombre'=>$rs->nombre,
                    'descripcion'=>$rs->descripcion
                );
            }

            echo json_encode( $resp );
        }

    }
    break;

        #*******************************************************************************
        case 'buscar_animales':
            session_start();
            $mfundos_id = $_SESSION[ 'mfundos_id' ];
            if ( $id_lote != null ) {
        
                @$data = Manimale::find_by_sql("SELECT count(id) as cantidad  FROM Manimales  WHERE  status=1 and mlotes_id=".$id_lote." and  mfundos_id=" . $mfundos_id . " ;");

                if ($data != null) {

                    foreach ($data as $rs) {
          
                      $resp = array(
                        "cant_reg" => $rs->cantidad
          
                      );
                    }
                  } else {
                    $resp = array(
                      "cant_reg" => 0
          
                    );
                  }
               
          
                echo json_encode($resp);
        
            }
            break;

    case 'get_lote':

    @session_start();
    @$id_sesion = $_SESSION[ 'idsessionx' ];
    @$mfundos_id = $_SESSION[ 'mfundos_id' ];
    @$rol = $_SESSION[ 'rolx' ];

    $mfundos_id = intval( $mfundos_id );
    /* Catálogos del FUNDO ACTIVO (MODO SOPORTE incluido): si hay un fundo
       en la sesión se usa la misma rama que el productor de ese fundo, en
       vez de devolver los registros de TODOS los fundos (rol 1/4). */
    if ( bovs_catalogo_fundo_id() > 0 && ( $rol == 1 || $rol == 4 ) ) {
        $rol = 2;
    }

    if ( !empty( $id_sesion ) && ( $rol == 1 ) ) {
        //privilegios del programador y Supervisor

        $data = Mlote::find_by_sql( 'select id,nombre from mlotes where status>0 order by id desc;' );

    } else if ( !empty( $id_sesion ) && ( ( $rol == 2 ) || ( $rol == 3 ) ) ) {
        $data = Mlote::find_by_sql( 'select id,nombre from mlotes where (mfundos_id='.$mfundos_id.' ) and status>0 order by id desc;' );

    }

    if ( $data != null ) {
        $resp = '<option value="">--</option>';
        foreach ( $data as $rs ) {
            $resp .= '<option value="'.$rs->id.'">'.$rs->nombre.'</option>';
            $resp .= '<hidden>';
        }

    } else {
        $resp = '<option value="">No hay LOTE para Agregar</option>';
    }

    echo $resp;
    break;

    #******************************************************************************
    case 'get_lote_control_sanitario':

    @session_start();
    @$id_sesion = $_SESSION[ 'idsessionx' ];
    @$mfundos_id = $_SESSION[ 'mfundos_id' ];

    $mfundos_id = intval( $mfundos_id );

    $data = null;
    if ( !empty( $id_sesion ) && ( $mfundos_id > 0 ) ) {
        // El reporte siempre trabaja sobre el fundo activo.
        $data = Mlote::find_by_sql( 'select id,nombre from mlotes where mfundos_id='.$mfundos_id.' and status>0 order by nombre;' );
    }

    if ( $data != null ) {
        $resp = '<option value="">* Seleccione un lote</option>';

        foreach ( $data as $rs ) {
            $resp .= '<option value="'.$rs->id.'">'.$rs->nombre.'</option>';
        }

    } else {
        $resp = '<option value="">No hay LOTES registrados</option>';
    }

    echo $resp;
    break;

    #******************************************************************************
    case 'get_lote_consecutivo_peso':

    @session_start();
    @$id_sesion = $_SESSION[ 'idsessionx' ];
    @$mfundos_id = $_SESSION[ 'mfundos_id' ];
    @$rol = $_SESSION[ 'rolx' ];

    $mfundos_id = intval( $mfundos_id );
    /* Catálogos del FUNDO ACTIVO (MODO SOPORTE incluido): si hay un fundo
       en la sesión se usa la misma rama que el productor de ese fundo, en
       vez de devolver los registros de TODOS los fundos (rol 1/4). */
    if ( bovs_catalogo_fundo_id() > 0 && ( $rol == 1 || $rol == 4 ) ) {
        $rol = 2;
    }

    if ( !empty( $id_sesion ) && ( $rol == 1 ) ) {
        //privilegios del programador y Supervisor

        $data = Vloteconsecutivopeso::find_by_sql( 'select id,nombre from Vloteconsecutivopesos order by id desc;' );

    } else if ( !empty( $id_sesion ) && ( ( $rol == 2 ) || ( $rol == 3 ) ) ) {
        $data = Vloteconsecutivopeso::find_by_sql( 'select id,nombre from Vloteconsecutivopesos where (mfundos_id='.$mfundos_id.' );' );

    }

    if ( $data != null ) {
                $resp = '<option value="">* Seleccione un lote</option>';

        foreach ( $data as $rs ) {
            $resp .= '<option value="'.$rs->id.'">'.$rs->nombre.'</option>';
            $resp .= '<hidden>';
        }

    } else {
        $resp = '<option value="">No hay LOTE para Agregar</option>';
    }

    echo $resp;
    break;

    #******************************************************************************
    case 'get_lote_prod_leche':

    @session_start();
    @$id_sesion = $_SESSION[ 'idsessionx' ];
    @$mfundos_id = $_SESSION[ 'mfundos_id' ];
    @$rol = $_SESSION[ 'rolx' ];

    $mfundos_id = intval( $mfundos_id );
    /* Catálogos del FUNDO ACTIVO (MODO SOPORTE incluido): si hay un fundo
       en la sesión se usa la misma rama que el productor de ese fundo, en
       vez de devolver los registros de TODOS los fundos (rol 1/4). */
    if ( bovs_catalogo_fundo_id() > 0 && ( $rol == 1 || $rol == 4 ) ) {
        $rol = 2;
    }

    if ( !empty( $id_sesion ) && ( $rol == 1 ) ) {
        //privilegios del programador y Supervisor

        $data = Vlotesprolechera::find_by_sql( 'select id,nombre from Vlotesprolecheras order by id desc;' );

    } else if ( !empty( $id_sesion ) && ( ( $rol == 2 ) || ( $rol == 3 ) ) ) {
        $data = Vlotesprolechera::find_by_sql( 'select id,nombre from Vlotesprolecheras where (mfundos_id='.$mfundos_id.' );' );

    }

    if ( $data != null ) {
                $resp = '<option value="">* Seleccione un lote</option>';

        foreach ( $data as $rs ) {
            $resp .= '<option value="'.$rs->id.'">'.$rs->nombre.'</option>';
            $resp .= '<hidden>';
        }

    } else {
        $resp = '<option value="">No hay LOTE para Agregar</option>';
    }

    echo $resp;
    break;

}
//end switch
?>