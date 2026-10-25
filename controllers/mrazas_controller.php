<?php
require_once '../models/Mraza.php';
require_once __DIR__ . '/../lib/catalogo_fundo_helper.php';
date_default_timezone_set( 'America/Caracas' );

@$action = ( $_POST[ 'action' ] );
@$id = ( $_POST[ 'id_raza' ] );
@$id_raza = ( $_POST[ 'id_raza' ] );
@$record = ( $_POST[ 'record' ] );
/***********************************/
@$nombre_raza = strtoupper( $_POST[ 'nombre_raza' ] );
@$pais = ( $_POST[ 'pais' ] );

/**********************************/

switch ( $action ) {
    case 'add':
    session_start();
    @$mfundos_id = $_SESSION[ 'mfundos_id' ];
    $nombre_raza = strtoupper( $nombre_raza );

    if ( $nombre_raza == '' ) {

        $respuesta = array( 'resultado'=>'error', 'mensaje'=>'<div class="alert alert-warning alert-dismissable">
            <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
            <h4>
            <i class="icon fa fa-warning"></i>
            Alerta!
            </h4>
            Ingrese el nombre de la Raza.
            </div>' );

    } else
    if ( $pais == '' ) {

        $respuesta = array( 'resultado'=>'error', 'mensaje'=>'<div class="alert alert-warning alert-dismissable">
            <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
            <h4>
            <i class="icon fa fa-warning"></i>
            Alerta!
            </h4>
            Ingrese el Pais al cual pertenece la Raza.
            </div>' );

    } else {
        //( A ) si se escribe un nombre se consulta si ya existe...

        $consulta = Mraza::find( 'all', array( 'conditions' => array( 'nombre=? and mfundos_id=?', $nombre_raza, $mfundos_id ) ) );

        if ( $consulta == null ) {
            //( B ) si no existe lo guarda...

            session_start();
            $usuario_id = $_SESSION[ 'idusuariox' ];
            @$rol = $_SESSION[ 'rolx' ];
            @$mfundos_id_session = $_SESSION[ 'mfundos_id' ];
            @$t_ganaderia = $_SESSION[ 'tipo_ganaderia' ];

            $hoy = date( 'd-m-Y' );

            $raza = new Mraza();

            if ( ( $rol == 1 ) || ( $rol == 4 ) ) {
                $mfundos_id = 0;

            } else {
                $mfundos_id = $mfundos_id_session;

            }
            $raza->mtipoganaderias_id=$t_ganaderia;
            $raza->nombre = $nombre_raza;
            $raza->id_m_paises = $pais;
            $raza->mfundos_id = $mfundos_id;

            $raza->user_create = $usuario_id;
            $raza->created = $hoy;

            if ( $raza->save() ) {
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

                  Registro inactivo . ¿Desea incluir ['.$nombre_raza.'] en el maestro de grupos, áreas, equipos o dependencias?

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
                  La Raza ['.$nombre_raza.'] ya se encuentra registrada.
                  </div>' );

            }
            // echo json_encode( $respuesta );
        }

    }
    echo json_encode( $respuesta );

    break;
    #*******************************************************************************
    case 'edit':
    $nombre_raza = strtoupper( $nombre_raza );

    if ( $nombre_raza == '' ) {

        $respuesta = array( 'resultado'=>'error', 'mensaje'=>'<div class="alert alert-warning alert-dismissable">
            <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
            <h4>
            <i class="icon fa fa-warning"></i>
            Alerta!
            </h4>
            Ingrese el nombre de la Raza.
            </div>' );

    } else
    if ( $pais == '' ) {

        $respuesta = array( 'resultado'=>'error', 'mensaje'=>'<div class="alert alert-warning alert-dismissable">
            <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
            <h4>
            <i class="icon fa fa-warning"></i>
            Alerta!
            </h4>
            Ingrese el Pais al cual pertenece la Raza.
            </div>' );

    } else {

        $consulta = Mraza::find( 'all', array( 'conditions' => array( 'id=?', $id_raza ) ) );

        if ( $consulta != null ) {

            session_start();
            $usuario_id = $_SESSION[ 'idusuariox' ];
            $hoy = date( 'd-m-Y' );

            //// validaciones

            if ( ( $rol == 2 ) || ( $rol == 3 ) ) {
                @$sesion_mfundos_id = $_SESSION[ 'mfundos_id' ];
                @$t_ganaderia = $_SESSION[ 'tipo_ganaderia' ];

                $data = Mraza::find( 'all', array( 'conditions' => array( 'id=?', $id_raza ) ) );

                foreach ( $data as $st ) {

                    $fundo = $st->mfundos_id;
                    $tipo_ganaderia = $st->mtipoganaderias_id;

                }

                if ( $fundo > 0 ) {
                    $raza = Mraza::find( $id_raza );
                    $raza->mtipoganaderias_id = $tipo_ganaderia;


                } else {
                    $raza = new Mraza();
                    $raza->mtipoganaderias_id = $tipo_ganaderia;
                    $raza->mfundos_id = $sesion_mfundos_id;

                }
            } else {
                $sesion_mfundos_id = 0;
                $raza = Mraza::find( $id_raza );
                $raza->mfundos_id = $sesion_mfundos_id;

            }

            //////////////////

            $raza->nombre = $nombre_raza;
            $raza->id_m_paises = $pais;

            $raza->user_modify = $usuario_id;
            $raza->updated = $hoy;

            if ( $raza->save() ) {

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

                  Registro inactivo . ¿Desea incluir ['.$nombre_raza.'] en el maestro de grupos, áreas, equipos o dependencias?

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
                  El grupo, áreas, equipo o dependencia ['.$nombre_raza.'] ya se encuentra registrado.
                  </div>' );

            }
            // echo json_encode( $respuesta );
        }

    }
    echo json_encode( $respuesta );

    break;
    #*******************************************************************************

    case 'delete':

    if ( $record != null ) {

        session_start();
        $usuario_id = $_SESSION[ 'idusuariox' ];
        $rol = $_SESSION[ 'rolx' ];
        $hoy = date( 'd-m-Y' );

        if ( $usuario_id != '' && (( $rol == 1 )||( $rol == 4 ) )) {

            $raza = Mraza::find( $record );
            $raza->user_modify = $usuario_id;
            $raza->updated = $hoy;
            $raza->status = 0;

            if ( $raza->save() ) {

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

            echo	'<div class="alert alert-warning alert-dismissable">
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
        
    @session_start();
    @$id_sesion = $_SESSION[ 'idsessionx' ];
    @$mfundos_id = $_SESSION[ 'mfundos_id' ];
        @$t_ganaderia = $_SESSION[ 'tipo_ganaderia' ];
    if ( $id_raza != null ) {

        @$data = Mraza::find( 'all', array( 'conditions' => array( 'id=? and mfundos_id=? and mtipoganaderias_id=?', $id_raza,$mfundos_id,$t_ganaderia ) ) );

        if ( $data != null ) {

            foreach ( $data as $rs ) {

                $resp = array(
                    'id'=>$rs->id,
                    'pais'=>$rs->id_m_paises,
                    'nombre'=>$rs->nombre
                );
            }

            echo json_encode( $resp );
        }

    }
    break;

    case 'get_razas':

    @session_start();
    @$id_sesion = $_SESSION[ 'idsessionx' ];
    @$mfundos_id = $_SESSION[ 'mfundos_id' ];
    @$rol = $_SESSION[ 'rolx' ];
    @$t_ganaderia = $_SESSION[ 'tipo_ganaderia' ];

    $mfundos_id = intval( $mfundos_id );

    /* Razas del FUNDO ACTIVO (o solo las globales si no hay fundo).
       No se ramifica por rol: con un fundo en la sesión —incluido el MODO
       SOPORTE— se ven las razas de ESE fundo + las globales; si no hay fundo
       activo, solo las globales. Antes el rol 1/4 recibía las razas de TODOS
       los fundos (listas enormes con nombres repetidos). */
    if ( !empty( $id_sesion ) ) {
        $data = Mraza::find_by_sql(
            'select id, UPPER(nombre) as nombre, mfundos_id from mrazas
              where status > 0'
            . bovs_catalogo_sql_tipo() . bovs_catalogo_sql_fundo() .
            ' order by nombre ASC;'
        );
    }

    if ( $data != null ) {
        $resp = '<option value="">Indique la Raza</option>';

        if ( $mfundos_id > 0 ) {
            /* Se agrupan para no confundir las razas PROPIAS del fundo con
               las GLOBALES cuando coinciden en nombre. NO se deduplica por
               nombre: en un mismo fundo hay animales con raza del fundo y
               animales con raza global, y el select tiene que poder mostrar
               el id de cada uno. */
            $propias  = array();
            $globales = array();

            foreach ( $data as $rs ) {
                if ( intval( $rs->mfundos_id ) > 0 ) {
                    $propias[] = $rs;
                } else {
                    $globales[] = $rs;
                }
            }

            if ( count( $propias ) > 0 ) {
                $resp .= '<optgroup label="Razas del fundo">';
                foreach ( $propias as $rs ) {
                    $resp .= '<option value="'.$rs->id.'">'.$rs->nombre.'</option>';
                }
                $resp .= '</optgroup>';
            }

            if ( count( $globales ) > 0 ) {
                $resp .= '<optgroup label="Razas globales">';
                foreach ( $globales as $rs ) {
                    $resp .= '<option value="'.$rs->id.'">'.$rs->nombre.'</option>';
                }
                $resp .= '</optgroup>';
            }

        } else {
            foreach ( $data as $rs ) {
                $resp .= '<option value="'.$rs->id.'">'.$rs->nombre.'</option>';
            }
        }

    } else {
        $resp = '<option value="">No hay Razas para Agregar</option>';
    }

    echo $resp;
    break;

    #******************************************************************************

}
//end switch
?>
