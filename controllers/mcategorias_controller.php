<?php
require_once __DIR__ . '/../lib/catalogo_fundo_helper.php';
require_once '../models/Mcategoria.php';
date_default_timezone_set( 'America/Caracas' );

@$action = ( $_POST[ 'action' ] );
@$id = ( $_POST[ 'id_categoria' ] );
@$id_categoria = ( $_POST[ 'id_categoria' ] );
@$record = ( $_POST[ 'record' ] );
/***********************************/
@$nombre_categoria =strtoupper( $_POST[ 'nombre_categoria' ] );
@$edadminima = ( $_POST[ 'edadminima' ] );
@$edadmaxima = ( $_POST[ 'edadmaxima' ] );
@$sexo = ( $_POST[ 'sexo' ] );

/**********************************/

switch ( $action ) {

    #*******************************************************************************
    case 'edit_cat':

    if ( $nombre_categoria == '' ) {

        $respuesta = array( 'resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
                <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                <h4>
                <i class="icon fa fa-warning"></i>
                Alerta!
                </h4>
                Ingrese el nombre del Tipo.
                </div>' );
    } elseif ( $edadminima == '' ) {
        $respuesta = array( 'resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
                <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                <h4>
                <i class="icon fa fa-warning"></i>
                Alerta!
                </h4>
                Ingrese el Dia de Gestaciòn.
                </div>' );
    } elseif ( $edadmaxima == '' ) {
        $respuesta = array( 'resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
                  <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                  <h4>
                  <i class="icon fa fa-warning"></i>
                  Alerta!
                  </h4>
                  Ingrese el Dia de Gestaciòn.
                  </div>' );
    } else {

        $consulta = Mcategoria::find( 'all', array( 'conditions' => array( 'id=?', $id_categoria ) ) );

        if ( $consulta != null ) {

            session_start();
            $usuario_id = $_SESSION[ 'idusuariox' ];
            $hoy = date( 'd-m-Y' );

            @$rol = $_SESSION[ 'rolx' ];

            if ( ( $rol == 2 ) || ( $rol == 3 ) ) {
                @$sesion_mfundos_id = $_SESSION[ 'mfundos_id' ];
                @$t_ganaderia = $_SESSION[ 'tipo_ganaderia' ];

                $data = Mcategoria::find( 'all', array( 'conditions' => array( 'id=?', $id_categoria ) ) );

                foreach ( $data as $st ) {

                    $fundo = $st->mfundos_id;
                    $id_padre = $st->id;
                    $tipo_ganaderia = $st->mtipoganaderias_id;

                }

                if ( $fundo > 0 ) {
                    $reg_categoria = Mcategoria::find( $id_categoria );

                } else {
                    $reg_categoria = new Mcategoria();
                    $reg_categoria->id_padre = $id_padre;
                    $reg_categoria->mtipoganaderias_id = $tipo_ganaderia;
                    $reg_categoria->sexo = $st->sexo;
                    $reg_categoria->mfundos_id = $sesion_mfundos_id;

                }
            } else {
                $sesion_mfundos_id = 0;
                $reg_categoria = Mcategoria::find( $id_categoria );
                $reg_categoria->mfundos_id = $sesion_mfundos_id;

            }

            $reg_categoria->nombre = $nombre_categoria;
            $reg_categoria->edadminima = $edadminima;
            $reg_categoria->edadmaxima = $edadmaxima;
            $reg_categoria->user_modify = $usuario_id;
            $reg_categoria->updated = $hoy;

            if ( $reg_categoria->save() ) {

                $respuesta = array( 'resultado' => 'ok', 'mensaje' => '<div class="alert alert-success alert-dismissable">
                        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                        <h4>
                        <i class="icon fa fa-check"></i>
                        Alerta!
                        </h4>
                        Los datos han sido actualizados exitosamente !.
                        </div>' );
            } else {

                $respuesta = array( 'resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
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

                $respuesta = array( 'resultado' => 'alert', 'mensaje' => '<input type="hidden" id="id" value="' . $ide . '">
                      <div class="alert alert-danger alert-dismissable">
                      <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                      <h4>
                      <i class="icon fa fa-ban"></i>
                      Alerta!
                      </h4>
    
                      Registro inactivo . ¿Desea incluir [' . $nombre_categoria . '] ?
    
                          <div class="modal-footer" >
                            <button type="button" id="no" class="btn btn-outline pull-left" data-dismiss="alert" onClick="no()">No</button>
                            <button type="button" id="silo" class="btn btn-outline pull-left" onClick="si()" >Si</button>
                          </div>
                      </div>' );
            } else {

                $respuesta = array( 'resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
                      <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                      <h4>
                      <i class="icon fa fa-ban"></i>
                      Alerta!
                      </h4>
                      El Tipo Fundo [' . $nombre_categoria . '] ya se encuentra registrado.
                      </div>' );
            }
            // echo json_encode( $respuesta );
        }
    }
    echo json_encode( $respuesta );

    break;
    #*******************************************************************************
    //////////////////////////////

    case 'add':
    session_start();
    $usuario_id = $_SESSION[ 'idusuariox' ];
    $mfundos_id_session = $_SESSION[ 'mfundos_id' ];

    $rol = $_SESSION[ 'rolx' ];
    $nombre_categoria = strtoupper( $nombre_categoria );

    if ( $nombre_categoria == '' ) {

        $respuesta = array( 'resultado'=>'error', 'mensaje'=>'<div class="alert alert-warning alert-dismissable">
            <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
            <h4>
            <i class="icon fa fa-warning"></i>
            Alerta!
            </h4>
            Ingrese el nombre.
            </div>' );
    } elseif ( $edadminima == '' ) {
        $respuesta = array( 'resultado'=>'error', 'mensaje'=>'<div class="alert alert-warning alert-dismissable">
                <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                <h4>
                <i class="icon fa fa-warning"></i>
                Alerta!
                </h4>
                Ingrese Edad Minima.
                </div>' );
    } elseif ( $edadmaxima == '' ) {
        $respuesta = array( 'resultado'=>'error', 'mensaje'=>'<div class="alert alert-warning alert-dismissable">
                    <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                    <h4>
                    <i class="icon fa fa-warning"></i>
                    Alerta!
                    </h4>
                    Ingrese Edad Maxima.
                    </div>' );

    } elseif ( $edadmaxima <= $edadminima ) {
        $respuesta = array( 'resultado'=>'error', 'mensaje'=>'<div class="alert alert-warning alert-dismissable">
                <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                <h4>
                <i class="icon fa fa-warning"></i>
                Alerta!
                </h4>
                Corregir Parametros de Edad -> Mínima / Máxima.
                </div>' );

    } else {
        //( A ) si se escribe un nombre se consulta si ya existe...

        $consulta = Mcategoria::find( 'all', array( 'conditions' => array( 'nombre=? and mfundos_id=?', $nombre_categoria, $mfundos_id_session ) ) );

        if ( $consulta == null ) {

            if ( ( $rol == 1 ) || ( $rol == 4 ) ) {
                $mfundos_id = 0;

            } else {
                $mfundos_id = $mfundos_id_session;
            }

            $hoy = date( 'd-m-Y' );

            $reg_categoria = new Mcategoria();
            $reg_categoria->nombre = $nombre_categoria;
            $reg_categoria->sexo = $sexo;

            $reg_categoria->edadminima = $edadminima;
            $reg_categoria->edadmaxima = $edadmaxima;
            $reg_categoria->mfundos_id = $mfundos_id;

            $reg_categoria->user_create = $usuario_id;
            $reg_categoria->created = $hoy;

            if ( $reg_categoria->save() ) {
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

                  Registro inactivo . ¿Desea incluir ['.$nombre_categoria.'] ?

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
                  El Tipo ['.$nombre_categoria.'] ya se encuentra registrado.
                  </div>' );

            }
            // echo json_encode( $respuesta );
        }

    }
    echo json_encode( $respuesta );

    break;
    #*******************************************************************************
    case 'edit':
    $nombre_categoria = strtoupper( $nombre_categoria );

    if ( $nombre_categoria == '' ) {

        $respuesta = array( 'resultado'=>'error', 'mensaje'=>'<div class="alert alert-warning alert-dismissable">
            <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
            <h4>
            <i class="icon fa fa-warning"></i>
            Alerta!
            </h4>
            Ingrese el nombre de la Categoria.
            </div>' );

    } elseif ( $edadminima == '' ) {
        $respuesta = array( 'resultado'=>'error', 'mensaje'=>'<div class="alert alert-warning alert-dismissable">
              <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
              <h4>
              <i class="icon fa fa-warning"></i>
              Alerta!
              </h4>
              Ingrese Edad Minima.
              </div>' );
    } elseif ( $edadmaxima == '' ) {
        $respuesta = array( 'resultado'=>'error', 'mensaje'=>'<div class="alert alert-warning alert-dismissable">
                  <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                  <h4>
                  <i class="icon fa fa-warning"></i>
                  Alerta!
                  </h4>
                  Ingrese Edad Maxima.
                  </div>' );

    } else {

        $consulta = Mcategoria::find( 'all', array( 'conditions' => array( 'id=?', $id_categoria ) ) );

        if ( $consulta != null ) {

            session_start();
            $usuario_id = $_SESSION[ 'idusuariox' ];
            $hoy = date( 'd-m-Y' );

            $categoria = Mcategoria::find( $id_categoria );
            $categoria->nombre = $nombre_categoria;
            $categoria->sexo = $sexo;

            $categoria->edadminima = $edadminima;
            $categoria->edadmaxima = $edadmaxima;
            $categoria->user_modify = $usuario_id;
            $categoria->updated = $hoy;

            if ( $categoria->save() ) {

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

                  Registro inactivo . ¿Desea incluir ['.$nombre_categoria.'] ?

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
                  El Tipo Fundo ['.$nombre_categoria.'] ya se encuentra registrado.
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

        if ( $usuario_id != '' && ( $rol == 1 ) ) {

            $categoria = Mcategoria::find( $record );
            $categoria->user_modify = $usuario_id;
            $categoria->updated = $hoy;
            $categoria->status = 0;

            if ( $categoria->save() ) {

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

    if ( $record != null ) {

        @$data = Mcategoria::find( 'all', array( 'conditions' => array( 'id=?', $record ) ) );

        if ( $data != null ) {

            foreach ( $data as $rs ) {

                $resp = array(
                    'id'=>$rs->id,
                    'nombre'=>$rs->nombre,
                    'sexo'=>$rs->sexo,
                    'edadminima'=>$rs->edadminima,
                    'edadmaxima'=>$rs->edadmaxima
                );
            }

            echo json_encode( $resp );
        }

    }
    break;
    ////*********************************    */

    #*******************************************************************************
    case 'search_categoria_actual':
    @session_start();
    @$mfundos_id = $_SESSION[ 'mfundos_id' ];
    $sexo_categoria = intval( $_POST[ 'sexo' ] );
    $fecha_nac = ( $_POST[ 'fecha_nac' ] );
    $edad = intval( tiempo_t( $fecha_nac ) );
    @$t_ganaderia = intval( $_SESSION[ 'tipo_ganaderia' ] );

    /* Respuesta por defecto: si ninguna categoría cubre esa edad, el campo queda
       vacío (antes no se devolvía nada y el JSON rompía la vista). */
    $resp = array( 'cat_actual'=>'', 'id_categoria'=>0 );

    /* Entre las categorías cuyo rango contiene la edad se elige la MÁS ESPECÍFICA:
       mayor edad mínima y, a igualdad, el rango más corto. Antes se quedaba con la
       ÚLTIMA por id, así que una categoría "comodín" (edad mínima 0 y máxima muy
       alta; p. ej. VACAS 0-5000) se imponía incluso en una cría recién nacida y la
       Categoría Actual salía VACA. */
    $orden_categoria = ' order by edadminima desc, edadmaxima asc, id asc limit 1;';

    $data_propia = Mcategoria::find_by_sql( "select id,nombre from mcategorias 
                                            WHERE 
                                            
                                            mtipoganaderias_id=".$t_ganaderia." and 
                                            status=1 and 
                                            sexo=".$sexo_categoria." and 
                                            mfundos_id=".$mfundos_id." and
                                            edadminima<=".$edad." and
                                            edadmaxima> ".$edad . $orden_categoria );

    $data_estandar = Mcategoria::find_by_sql( "select id,nombre from mcategorias 
                                            WHERE 
                                            mtipoganaderias_id=".$t_ganaderia." and 
                                            status=1 and 
                                            sexo=".$sexo_categoria." and 
                                            mfundos_id=0 and
                                            edadminima<=".$edad." and
                                            edadmaxima> ".$edad . $orden_categoria );

    if ( ( $data_propia != null ) && ( count( $data_propia ) > 0 ) ) {

        $resp = array(
            'cat_actual'=>$data_propia[0]->nombre,
            'id_categoria'=>$data_propia[0]->id
        );

    } else if ( ( $data_estandar != null ) && ( count( $data_estandar ) > 0 ) ) {

        $resp = array(
            'cat_actual'=>$data_estandar[0]->nombre,
            'id_categoria'=>$data_estandar[0]->id
        );

    }
    echo json_encode( $resp );

    break;

    case 'get_categoria_ingreso':

    @session_start();
    @$id_sesion = $_SESSION[ 'idsessionx' ];
    @$mfundos_id = $_SESSION[ 'mfundos_id' ];
    @$rol = $_SESSION[ 'rolx' ];
    @$t_ganaderia = $_SESSION[ 'tipo_ganaderia' ];

    $mfundos_id = intval( $mfundos_id );
    /* Catálogos del FUNDO ACTIVO (MODO SOPORTE incluido): si hay un fundo
       en la sesión se usa la misma rama que el productor de ese fundo, en
       vez de devolver los registros de TODOS los fundos (rol 1/4). */
    if ( bovs_catalogo_fundo_id() > 0 && ( $rol == 1 || $rol == 4 ) ) {
        $rol = 2;
    }

    $sexo_categoria = ( $_POST[ 'filtrar_x_sexo' ] );
    if ( $sexo_categoria != '' ) {

        if ( !empty( $id_sesion ) && ( ( $rol == 1 ) || ( $rol == 4 ) ) ) {
            //privilegios del programador y Supervisor

            $data = Mcategoria::find_by_sql( 'select id,nombre,status from mcategorias WHERE status=1 and sexo='.$sexo_categoria.' order by id asc;' );

        } else if ( !empty( $id_sesion ) && ( ( $rol == 2 ) || ( $rol == 3 ) ) ) {

                                        $data = Mcategoria::find_by_sql( "select id,nombre,status from mcategorias
                                        WHERE  mtipoganaderias_id=".$t_ganaderia." and
                                        id  not in ( select id_padre from mcategorias WHERE mfundos_id in (".$mfundos_id.")) and
                                        (mfundos_id in (0,".$mfundos_id.") ) and 
                                        status=1 and sexo=".$sexo_categoria." order by edadminima asc; " );

        }

    } else {

        if ( !empty( $id_sesion ) && ( ( $rol == 1 ) || ( $rol == 4 ) ) ) {
            //privilegios del programador y Supervisor

            $data = Mcategoria::find( 'all', array( 'conditions' => array( 'status=?', 1 ), 'order' => 'id desc' ) );

        } else if ( !empty( $id_sesion ) && ( ( $rol == 2 ) || ( $rol == 3 ) ) ) {
            $data = Mcategoria::find_by_sql( "select id,nombre from mcategorias
                                                    WHERE  mtipoganaderias_id=".$t_ganaderia." and
                                                    id  not in ( select id_padre from mcategorias WHERE mfundos_id in (".$mfundos_id.")) and
                                                    (mfundos_id in (0,".$mfundos_id.") ) and 
                                                    status=1  order by edadminima asc;" );



        }

    }

    if ( $data != null ) {
        $resp = '<option value="">--</option>';
        foreach ( $data as $rs ) {
            $resp .= '<option value="'.$rs->id.'">'.$rs->nombre.'</option>';
            $resp .= '<hidden>';
        }

    } else {
        $resp = '<option value="">No hay Categoria para Agregar</option>';
    }

    echo $resp;
    break;
    #******************************************************************************
    case 'get_categoria_futura':
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

    $sexo_categoria = ( $_POST[ 'filtrar_x_sexo' ] );
    $categoria_ingreso = ( $_POST[ 'categoria_ingreso' ] );


    $categoria_ingreso = intval( $categoria_ingreso );
    @$t_ganaderia = $_SESSION[ 'tipo_ganaderia' ];

    if ( $sexo_categoria != '' ) {

        if ( !empty( $id_sesion ) && ( ( $rol == 1 ) || ( $rol == 4 ) ) ) {
            //privilegios del programador y Supervisor

                            $data = Mcategoria::find_by_sql( "select id,nombre,status,edadminima from mcategorias 
                    WHERE status=1 and sexo=".$sexo_categoria." and
                    edadminima > (select a.edadminima from mcategorias a 
                        WHERE a.id=" . $categoria_ingreso . ' and a.status=1 and a.sexo='.$sexo_categoria." )
                        order by id asc;" );

                        } else if ( !empty( $id_sesion ) && ( ( $rol == 2 ) || ( $rol == 3 ) ) ) {

                            $data = Mcategoria::find_by_sql( "select id,nombre,status,edadminima from mcategorias 
                            WHERE mtipoganaderias_id=".$t_ganaderia." and (mfundos_id in (".$mfundos_id.",0)) and status=1 
                            and sexo=" .$sexo_categoria. "  and
                            id  not in ( select x.id_padre from mcategorias x WHERE x.mfundos_id in (".$mfundos_id.")) and
                            edadminima >= (select a.edadminima from mcategorias a 
                                WHERE mtipoganaderias_id=".$t_ganaderia." and (a.mfundos_id in (".$mfundos_id.",0)) and a.id=" . $categoria_ingreso . " and a.status=1 and a.sexo=".$sexo_categoria. " )
                                order by edadminima asc;" );

        }

    } else {

        if ( !empty( $id_sesion ) && ( ( $rol == 1 ) || ( $rol == 4 ) ) ) {
            //privilegios del programador y Supervisor

            $data = Mcategoria::find( 'all', array( 'conditions' => array( 'status=?', 1 ), 'order' => 'id desc' ) );

        } else if ( !empty( $id_sesion ) && ( ( $rol == 2 ) || ( $rol == 3 ) ) ) {

            $data = Mcategoria::find_by_sql( "select id,nombre from mcategorias 
                                WHERE mtipoganaderias_id=".$t_ganaderia." and (mfundos_id=".$mfundos_id." or mfundos_id=0) and status=1 
                                    order by id asc;" );

        }

    }

    /* La CATEGORÍA DE INGRESO ya NO se ofrece como categoría futura: una vez
       elegida, no tiene sentido volver a seleccionarla. Se quita sólo si queda
       alguna otra opción, porque la categoría futura es obligatoria y el
       formulario no puede quedar sin opciones (p. ej. cuando la categoría de
       ingreso es la última del animal). */
    if ( ( $categoria_ingreso > 0 ) && ( $data != null ) && ( count( $data ) > 1 ) ) {

        $filtradas = array();

        foreach ( $data as $rs_cat ) {

            if ( intval( $rs_cat->id ) !== $categoria_ingreso ) {
                $filtradas[] = $rs_cat;
            }
        }

        if ( count( $filtradas ) > 0 ) {
            $data = $filtradas;
        }
    }

    if ( $data != null ) {
        $resp = '<option value="">--</option>';
        foreach ( $data as $rs ) {
            $resp .= '<option value="'.$rs->id.'">'.$rs->nombre.'</option>';
            $resp .= '<hidden>';
        }

    } else {
        $resp = '<option value="">No hay Categoria para Agregar</option>';
    }

    echo $resp;
    break;


    ////////////////////// AGREGADO EL 07-07-2023

    case 'get_categoria_ingreso_filtrado':
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
    
        $sexo_categoria = ( $_POST[ 'filtrar_x_sexo' ] );
        $categoria_ingreso = ( $_POST[ 'filtrar_cat_actual' ] );
    
    
        $categoria_ingreso = intval( $categoria_ingreso );
        @$t_ganaderia = $_SESSION[ 'tipo_ganaderia' ];
    
        if ( $sexo_categoria != '' ) {
    
            if ( !empty( $id_sesion ) && ( ( $rol == 1 ) || ( $rol == 4 ) ) ) {
                //privilegios del programador y Supervisor
    
                                $data = Mcategoria::find_by_sql( "select id,nombre,status,edadminima from mcategorias 
                        WHERE status=1 and sexo=".$sexo_categoria." and
                        edadminima > (select a.edadminima from mcategorias a 
                            WHERE a.id=" . $categoria_ingreso . ' and a.status=1 and a.sexo='.$sexo_categoria." )
                            order by id asc;" );
    
                            } else if ( !empty( $id_sesion ) && ( ( $rol == 2 ) || ( $rol == 3 ) ) ) {
    
                                $data = Mcategoria::find_by_sql( "select id,nombre,status,edadminima from mcategorias 
                        WHERE mtipoganaderias_id=".$t_ganaderia." and (mfundos_id=".$mfundos_id." or mfundos_id=0) and status=1 and sexo=" .$sexo_categoria. " and
                        edadminima >= (select a.edadminima from mcategorias a 
                            WHERE mtipoganaderias_id=".$t_ganaderia." and (a.mfundos_id=".$mfundos_id." or a.mfundos_id=0) and a.id=" . $categoria_ingreso . " and a.status=1 and a.sexo=".$sexo_categoria. " )
                            order by id asc;" );
    
            }
    
        } else {
    
            if ( !empty( $id_sesion ) && ( ( $rol == 1 ) || ( $rol == 4 ) ) ) {
                //privilegios del programador y Supervisor
    
                $data = Mcategoria::find( 'all', array( 'conditions' => array( 'status=?', 1 ), 'order' => 'id desc' ) );
    
            } else if ( !empty( $id_sesion ) && ( ( $rol == 2 ) || ( $rol == 3 ) ) ) {
    
                $data = Mcategoria::find_by_sql( "select id,nombre from mcategorias 
                                    WHERE mtipoganaderias_id=".$t_ganaderia." and (mfundos_id=".$mfundos_id." or mfundos_id=0) and status=1 
                                        order by id asc;" );
    
            }
    
        }
    
        if ( $data != null ) {
            $resp = '<option value="">--</option>';
            foreach ( $data as $rs ) {
                $resp .= '<option value="'.$rs->id.'">'.$rs->nombre.'</option>';
                $resp .= '<hidden>';
            }
    
        } else {
            $resp = '<option value="">No hay Categoria para Agregar</option>';
        }
    
        echo $resp;
        break;
    

}
//end switch

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

?>