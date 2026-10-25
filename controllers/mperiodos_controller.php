<?php
require_once __DIR__ . '/../lib/catalogo_fundo_helper.php';
require_once '../models/Mperiodo.php';
date_default_timezone_set( 'America/Caracas' );

@$action = ( $_POST[ 'action' ] );
@$id = ( $_POST[ 'id_periodo' ] );
@$id_periodo = ( $_POST[ 'id_periodo' ] );
@$record = ( $_POST[ 'record' ] );
@$reg = ( $_POST[ 'reg' ] );

/***********************************/
@$nombre_periodo = ( $_POST[ 'nombre_periodo' ] );
@$dia_gestacion = ( $_POST[ 'dia_gestacion' ] );
@$tipo_ganaderia = ( $_POST[ 'tipo_ganaderia' ] );

/**********************************/

switch ( $action ) {
    case 'add':

    if ( $nombre_periodo == '' ) {

        $respuesta = array( 'resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
            <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
            <h4>
            <i class="icon fa fa-warning"></i>
            Alerta!
            </h4>
            Ingrese el nombre.
            </div>' );
    } elseif ( $dia_gestacion == '' ) {
        $respuesta = array( 'resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
            <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
            <h4>
            <i class="icon fa fa-warning"></i>
            Alerta!
            </h4>
            Ingrese el Dia de Gestaciòn.
            </div>' );
    } else {
        //( A ) si se escribe un nombre se consulta si ya existe...

        $consulta = Mperiodo::find( 'all', array( 'conditions' => array( 'nombre=?', $nombre_periodo ) ) );

        if ( $consulta == null ) {
            //( B ) si no existe lo guarda...

            session_start();
            $usuario_id = $_SESSION[ 'idusuariox' ];
            $hoy = date( 'd-m-Y' );

            $segme = new Mperiodo();
            $segme->nombre = $nombre_periodo;
            $segme->dias_gestacion = $dia_gestacion;
            $segme->mtipoganaderias_id = $tipo_ganaderia;

            $segme->user_create = $usuario_id;
            $segme->created = $hoy;

            if ( $segme->save() ) {
                // da el mensaje de guardado...

                $respuesta = array( 'resultado' => 'ok', 'mensaje' => '<div class="alert alert-success alert-dismissable">
                    <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                    <h4>
                    <i class="icon fa fa-check"></i>
                    Alerta!
                    </h4>
                    Los datos han sido registrados exitosamente !.
                    </div>' );
            } else {

                $respuesta = array( 'resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
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

                $respuesta = array( 'resultado' => 'alert', 'mensaje' => '<input type="hidden" id="id" value="' . $ide . '">
                  <div class="alert alert-danger alert-dismissable">
                  <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                  <h4>
                  <i class="icon fa fa-ban"></i>
                  Alerta!
                  </h4>

                  Registro inactivo . ¿Desea incluir [' . $nombre_periodo . '] ?

                      <div class="modal-footer" >
                        <button type="button" id="no" class="btn btn-outline pull-left" data-dismiss="alert" onClick="no()">No</button>
                        <button type="button" id="silo" class="btn btn-outline pull-left" onClick="si()" >Si</button>
                      </div>
                  </div>' );
            } else {

                $respuesta = array( 'resultado' => 'alert', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
                  <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                  <h4>
                  <i class="icon fa fa-ban"></i>
                  Alerta!
                  </h4>
                  El Tipo [' . $nombre_periodo . '] ya se encuentra registrado.
                  </div>' );
            }
            // echo json_encode( $respuesta );
        }
    }
    echo json_encode( $respuesta );

    break;
    #*******************************************************************************
    case 'edit':

    if ( $nombre_periodo == '' ) {

        $respuesta = array( 'resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
            <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
            <h4>
            <i class="icon fa fa-warning"></i>
            Alerta!
            </h4>
            Ingrese el nombre del Tipo.
            </div>' );
    } elseif ( $dia_gestacion == '' ) {
        $respuesta = array( 'resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
            <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
            <h4>
            <i class="icon fa fa-warning"></i>
            Alerta!
            </h4>
            Ingrese el Dia de Gestaciòn.
            </div>' );
    } else {

        $consulta = Mperiodo::find( 'all', array( 'conditions' => array( 'id=?', $id_periodo ) ) );

        if ( $consulta != null ) {

            session_start();
            $usuario_id = $_SESSION[ 'idusuariox' ];
            $hoy = date( 'd-m-Y' );

            @$rol = $_SESSION[ 'rolx' ];

            if ( ( $rol != 1 ) && ( $rol != 4 ) ) {
                @$sesion_mfundos_id = $_SESSION[ 'mfundos_id' ];
                @$t_ganaderia = $_SESSION[ 'tipo_ganaderia' ];

                $data = Mperiodo::find( 'all', array( 'conditions' => array( 'id=?', $id_periodo ) ) );

                foreach ( $data as $st ) {

                    $fundo = $st->mfundos_id;
                    $cod_per = $st->codper_bov;
                    $tipo_ganaderia = $st->mtipoganaderias_id;

                }

                if ( $fundo > 0 ) {
                    $periodo = Mperiodo::find( $id );
                } else {
                    $periodo = new Mperiodo();
                    $periodo->codper_bov = $cod_per;
                    $periodo->mtipoganaderias_id = $tipo_ganaderia;
                    $periodo->mfundos_id = $sesion_mfundos_id;

                }
            } else {
                $sesion_mfundos_id = 0;
                $periodo = Mperiodo::find( $id );
                $periodo->mfundos_id = $sesion_mfundos_id;
                $periodo->mtipoganaderias_id = $tipo_ganaderia;

            }

            $periodo->nombre = $nombre_periodo;
            $periodo->dias_gestacion = $dia_gestacion;
            $periodo->user_modify = $usuario_id;
            $periodo->updated = $hoy;

            if ( $periodo->save() ) {

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

        if ( $usuario_id != '' && ( $rol == 1 || $rol == 4 ) ) {

            $periodo = Mperiodo::find( $record );
            $periodo->user_modify = $usuario_id;
            $periodo->updated = $hoy;
            $periodo->status = 0;

            if ( $periodo->save() ) {

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

    if ( $record != null ) {

        @$data = Mperiodo::find( 'all', array( 'conditions' => array( 'id=?', $record ) ) );

        if ( $data != null ) {

            foreach ( $data as $rs ) {

                $resp = array(
                    'id' => $rs->id,
                    'nombre' => $rs->nombre,
                    'dias_gestacion' => $rs->dias_gestacion
                );
            }

            echo json_encode( $resp );
        }
    }
    break;

    #******************************************************************************

    case 'get_periodo_palpacion':

    @session_start();
    @$id_sesion = $_SESSION[ 'idsessionx' ];
    @$mfundos_id = $_SESSION[ 'mfundos_id' ];
    @$rol = $_SESSION[ 'rolx' ];
    @$t_ganaderia = $_SESSION[ 'tipo_ganaderia' ];

    $mfundos_id = intval( $mfundos_id );
    if ( !empty( $id_sesion ) && ( $rol == 1 || $rol == 4 ) ) {
        //privilegios del programador y Supervisor

        $data = Mperiodo::find_by_sql( "select id, dias_gestacion as nombre from mperiodos where status>0 and codper_bov='PSP' and  mfundos_id=0 ;" );
    } else if ( !empty( $id_sesion ) && ( ( $rol == 2 ) || ( $rol == 3 ) ) ) {
        $data = Mperiodo::find_by_sql( "	select id,dias_gestacion  as nombre

                            from mperiodos where mtipoganaderias_id=".$t_ganaderia.' and mfundos_id=' . $mfundos_id . " and status>0 and codper_bov='PSP'
                            union
                            select id,dias_gestacion  as nombre
                            
                            from mperiodos where mtipoganaderias_id=".$t_ganaderia." and  mfundos_id=0 and codper_bov='PSP' and status>0 and
                            codper_bov NOT in (select DISTINCT b.codper_bov from mperiodos b 
                                      where b.mtipoganaderias_id=".$t_ganaderia.' and  b.mfundos_id=' . $mfundos_id . ' ) ;' );
    }

    if ( $data != null ) {
        foreach ( $data as $rs ) {
            $resp .= '<option value="' . $rs->nombre . '">' . $rs->nombre . '</option>';
            $resp .= '<hidden>';
        }
    } else {
        $resp = '<option value="">No hay Razas para Agregar</option>';
    }

    echo $resp;
    break;

    #******************************************************************************

    case 'search_periodo':

    if ( $reg != null ) {

        @$data = Mperiodo::find( 'all', array( 'conditions' => array( 'id=?', $reg ) ) );

        if ( $data != null ) {

            foreach ( $data as $rs ) {

                $resp = array(
                    'dias_gestacion' => $rs->dias_gestacion
                );
            }

            echo json_encode( $resp );
        }
    }
    break;

    ////********************************************* */

    case 'get_periodo_gestacion':

    @session_start();
    @$id_sesion = $_SESSION[ 'idsessionx' ];
    @$mfundos_id = $_SESSION[ 'mfundos_id' ];
    @$rol = $_SESSION[ 'rolx' ];
    @$t_ganaderia = $_SESSION[ 'tipo_ganaderia' ];

    $mfundos_id = intval( $mfundos_id );
    $resp = '';
    if ( !empty( $id_sesion ) && ( $rol == 1 || $rol == 4 ) ) {
        $data = Mperiodo::find_by_sql( "select id, dias_gestacion as nombre from mperiodos where status>0 and codper_bov='PGB' and mfundos_id=0 ;" );
    } else if ( !empty( $id_sesion ) && ( ( $rol == 2 ) || ( $rol == 3 ) ) ) {
        $data = Mperiodo::find_by_sql( "select id,dias_gestacion as nombre
             from mperiodos where mtipoganaderias_id=" . $t_ganaderia . ' and mfundos_id=' . $mfundos_id . " and status>0 and codper_bov='PGB'
             union
             select id,dias_gestacion as nombre
             from mperiodos where mtipoganaderias_id=" . $t_ganaderia . " and mfundos_id=0 and codper_bov='PGB' and status>0 and
             codper_bov NOT in (select DISTINCT b.codper_bov from mperiodos b where b.mtipoganaderias_id=" . $t_ganaderia . ' and b.mfundos_id=' . $mfundos_id . ' ) ;' );
    }

    if ( $data != null ) {
        foreach ( $data as $rs ) {
            $resp .= '<option value="' . $rs->nombre . '">' . $rs->nombre . '</option>';
            $resp .= '<hidden>';
        }
    } else {
        $resp = '<option value="">No hay Razas para Agregar</option>';
    }
    echo $resp;
    break;

    if ( $reg != null ) {

        @$data = Mperiodo::find( 'all', array( 'conditions' => array( 'id=?', $reg ) ) );

        if ( $data != null ) {

            foreach ( $data as $rs ) {

                $resp = array(
                    'dias_gestacion' => $rs->dias_gestacion
                );
            }

            echo json_encode( $resp );
        }
    }
    break;

    case 'get_periodo_prodleche':

    @session_start();
    @$id_sesion = $_SESSION[ 'idsessionx' ];
    @$mfundos_id = $_SESSION[ 'mfundos_id' ];
    @$rol = $_SESSION[ 'rolx' ];
    @$t_ganaderia = $_SESSION[ 'tipo_ganaderia' ];

    $mfundos_id = intval( $mfundos_id );
    if ( !empty( $id_sesion ) && ( $rol == 1 || $rol == 4 ) ) {
        //privilegios del programador y Supervisor

        $data = Mperiodo::find_by_sql( "select id, dias_gestacion as nombre from mperiodos where status>0 and codper_bov='PPL' and  mfundos_id=0 ;" );
    } else if ( !empty( $id_sesion ) && ( ( $rol == 2 ) || ( $rol == 3 ) ) ) {
        $data = Mperiodo::find_by_sql( "	select id,dias_gestacion  as nombre
        
                                    from mperiodos where mtipoganaderias_id=".$t_ganaderia.' and mfundos_id=' . $mfundos_id . " and status>0 and codper_bov='PPL'
                                    union
                                    select id,dias_gestacion  as nombre
                                    
                                    from mperiodos where mtipoganaderias_id=".$t_ganaderia." and  mfundos_id=0 and codper_bov='PPL' and status>0 and
                                    codper_bov NOT in (select DISTINCT b.codper_bov from mperiodos b 
                                              where b.mtipoganaderias_id=".$t_ganaderia.' and b.mfundos_id=' . $mfundos_id . ' ) ;' );
    }

    if ( $data != null ) {
        foreach ( $data as $rs ) {
            $resp .= '<option value="' . $rs->nombre . '">' . $rs->nombre . '</option>';
            $resp .= '<hidden>';
        }
    } else {
        $resp = '<option value="">No hay Razas para Agregar</option>';
    }

    echo $resp;
    break;

    case 'get_periodo_prodleche_det':

    @session_start();
    @$id_sesion = $_SESSION[ 'idsessionx' ];
    @$mfundos_id = $_SESSION[ 'mfundos_id' ];
    @$rol = $_SESSION[ 'rolx' ];
    @$t_ganaderia = $_SESSION[ 'tipo_ganaderia' ];

    $mfundos_id = intval( $mfundos_id );
    if ( !empty( $id_sesion ) && ( $rol == 1 || $rol == 4 ) ) {
        //privilegios del programador y Supervisor

        $data = Mperiodo::find_by_sql( "select id, dias_gestacion as nombre from mperiodos where status>0 and codper_bov='PPL' and  mfundos_id=0 ;" );
    } else if ( !empty( $id_sesion ) && ( ( $rol == 2 ) || ( $rol == 3 ) ) ) {
        $data = Mperiodo::find_by_sql( "	select id,dias_gestacion  as nombre
          
                                      from mperiodos where mtipoganaderias_id=".$t_ganaderia.' and mfundos_id=' . $mfundos_id . " and status>0 and codper_bov='PPL'
                                      union
                                      select id,dias_gestacion  as nombre
                                      
                                      from mperiodos where mtipoganaderias_id=".$t_ganaderia." and mfundos_id=0 and codper_bov='PPL' and status>0 and
                                      codper_bov NOT in (select DISTINCT b.codper_bov from mperiodos b 
                                                where b.mtipoganaderias_id=".$t_ganaderia.' and b.mfundos_id=' . $mfundos_id . ' ) ;' );
    }

    if ( $data != null ) {
        foreach ( $data as $rs ) {

            $resp = array(
                'periodo_prodleche' => $rs->nombre.'. Dias'
            );
        }
    }

    echo json_encode( $resp );
    break;

    case 'search_periodo_gestacion_det':

    @session_start();
    @$id_sesion = $_SESSION[ 'idsessionx' ];
    @$mfundos_id = $_SESSION[ 'mfundos_id' ];
    @$rol = $_SESSION[ 'rolx' ];
    @$t_ganaderia = $_SESSION[ 'tipo_ganaderia' ];

    $mfundos_id = intval( $mfundos_id );
    if ( !empty( $id_sesion ) && ( $rol == 1 || $rol == 4 ) ) {
        //privilegios del programador y Supervisor

        $data = Mperiodo::find_by_sql( "select id, dias_gestacion as nombre from mperiodos where status>0 and codper_bov='PGB' and  mfundos_id=0 ;" );
    } else if ( !empty( $id_sesion ) && ( ( $rol == 2 ) || ( $rol == 3 ) ) ) {
        $data = Mperiodo::find_by_sql( "	select id,dias_gestacion  as nombre
              
                                          from mperiodos where mtipoganaderias_id=".$t_ganaderia.' and mfundos_id=' . $mfundos_id . " and status>0 and codper_bov='PGB'
                                          union
                                          select id,dias_gestacion  as nombre
                                          
                                          from mperiodos where mtipoganaderias_id=".$t_ganaderia." and mfundos_id=0 and codper_bov='PGB' and status>0 and
                                          codper_bov NOT in (select DISTINCT b.codper_bov from mperiodos b 
                                                    where b.mtipoganaderias_id=".$t_ganaderia.' and b.mfundos_id=' . $mfundos_id . ' ) ;' );
    }

    if ( $data != null ) {
        foreach ( $data as $rs ) {
            $resp = array(
                'periodo_gestacion' => $rs->nombre
            );
        }
    }

    echo json_encode( $resp );
    break;

    case 'get_periodos_lactancia':
        session_start();
        $mfundos_id = isset($_SESSION['mfundos_id']) ? (int)$_SESSION['mfundos_id'] : 0;
        $tipo_ganaderia = isset($_SESSION['tipo_ganaderia']) ? (int)$_SESSION['tipo_ganaderia'] : 0;
        $id_sesion = isset($_SESSION['idsessionx']) ? $_SESSION['idsessionx'] : null;
        $rol = isset($_SESSION['rolx']) ? (int)$_SESSION['rolx'] : 0;
        $periodos = array('PBL' => 0, 'PPL' => 0);

        if (!empty($id_sesion) && in_array($rol, array(1, 2, 3, 4), true)
            && $mfundos_id >= 0 && $tipo_ganaderia > 0) {
            $data = Mperiodo::find_by_sql(
                "SELECT DISTINCT ON (codper_bov) codper_bov, dias_gestacion
                   FROM Mperiodos
                  WHERE status = 1
                    AND mtipoganaderias_id = ?
                    AND codper_bov IN ('PBL', 'PPL')
                    AND mfundos_id IN (0, ?)
               ORDER BY codper_bov,
                        CASE WHEN mfundos_id = ? THEN 0 ELSE 1 END,
                        id DESC",
                array($tipo_ganaderia, $mfundos_id, $mfundos_id)
            );
            foreach ($data as $periodo) {
                $periodos[$periodo->codper_bov] = max(0, (int)$periodo->dias_gestacion);
            }
        }

        echo json_encode(array(
            'periodo_lactancia_dias' => $periodos['PBL'],
            'periodo_prodleche_dias' => $periodos['PPL'],
            'periodo_lactancia' => $periodos['PBL'] . ' Dias',
            'periodo_prodleche' => $periodos['PPL'] . ' Dias'
        ));
        break;

    case 'get_periodo_palpacion_det':

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
    if ( !empty( $id_sesion ) && ( $rol == 1 || $rol == 4 ) ) {
        //privilegios del programador y Supervisor

        $data = Mperiodo::find_by_sql( "select id, dias_gestacion as nombre from mperiodos where status>0 and codper_bov='PSP' and  mfundos_id=0 ;" );
    } else if ( !empty( $id_sesion ) && ( ( $rol == 2 ) || ( $rol == 3 ) ) ) {
        $data = Mperiodo::find_by_sql( "	select id,dias_gestacion  as nombre

                            from mperiodos where mtipoganaderias_id=".$t_ganaderia.' and mfundos_id=' . $mfundos_id . " and status>0 and codper_bov='PSP'
                            union
                            select id,dias_gestacion  as nombre
                            
                            from mperiodos where mtipoganaderias_id=".$t_ganaderia." and mfundos_id=0 and codper_bov='PSP' and status>0 and
                            codper_bov NOT in (select DISTINCT b.codper_bov from mperiodos b 
                                      where b.mtipoganaderias_id=".$t_ganaderia.' and b.mfundos_id=' . $mfundos_id . ' ) ;' );
    }

    if ( $data != null ) {
        foreach ( $data as $rs ) {
            $resp = array(
                'periodo_palpacion' => $rs->nombre
            );
        }
    }
    echo json_encode( $resp );
    break;
}
//end switch
