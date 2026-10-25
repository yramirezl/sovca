<?php
require_once __DIR__ . '/../lib/especie_helper.php';

require_once '../models/Manimale.php';
require_once '../models/Mprolechera.php';
require_once '../models/Vprolechera.php';

include '../lib/sanitizar.php';

date_default_timezone_set( 'America/Caracas' );

/*
 * Convierte la fecha recibida del formulario (dd-mm-yyyy del datepicker
 * o YYYY-MM-DD del sistema) a ISO YYYY-MM-DD. NO usar new DateTime()
 * directamente: con 'dd-mm-yyyy' PHP interpreta m-d-Y cuando el día es
 * menor o igual a 12 y guarda el mes equivocado.
 */
function pl_fecha_iso( $valor )
{
    $valor = trim( (string)$valor );
    if ( $valor === '' ) return '';

    foreach ( array( 'Y-m-d', 'd-m-Y' ) as $formato ) {
        $d = DateTime::createFromFormat( $formato, $valor );
        if ( $d instanceof DateTime ) {
            $err = DateTime::getLastErrors();
            if ( is_array( $err ) && ( $err[ 'warning_count' ] > 0 || $err[ 'error_count' ] > 0 ) ) {
                continue;
            }
            return $d->format( 'Y-m-d' );
        }
    }

    return '';
}

/* Días hacia atrás en los que se permite editar o eliminar un ordeño. */
if ( !defined( 'PL_DIAS_EDICION' ) ) {
    define( 'PL_DIAS_EDICION', 30 );
}

@$action = ( $_POST[ 'action' ] );
@$id = ( $_POST[ 'id_prodleche' ] );

@$record = ( $_POST[ 'record' ] );
/***********************************/
@$cod_animal_h = ( $_POST[ 'cod_animal_h' ] );
@$fecha_prodleche = ( $_POST[ 'fecha_prodleche' ] );

@$kg_leche_m = ( $_POST[ 'kg_leche_m' ] );
@$kg_leche_t = ( $_POST[ 'kg_leche_t' ] );

/// Nuevas Variables
@$rgb_animal_h = ( $_POST[ 'rgb_animal_h' ] );
@$id_prodleche = ( $_POST[ 'id_prodleche' ] );

/**********************************/

switch ( $action ) {

    //// **************************

    case 'add':

    if ( $cod_animal_h == '' ) {

        $respuesta = array( 'resultado'=>'error', 'mensaje'=>'<div class="alert alert-warning alert-dismissable">
        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
        <h4>
        <i class="icon fa fa-warning"></i>
        Alerta!
        </h4>
        Ingrese El Código de la Hembra.
        </div>' );

    } else if ( $fecha_prodleche == '' ) {

        $respuesta = array( 'resultado'=>'error', 'mensaje'=>'<div class="alert alert-warning alert-dismissable">
        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
        <h4>
        <i class="icon fa fa-warning"></i>
        Alerta!
        </h4>
        Indique la Fecha del Ordeño.
        </div>' );

    } else {
        //( A ) si se escribe un nombre se consulta si ya existe...

        session_start();
        $usuario_id = $_SESSION[ 'idusuariox' ];
        $mfundos_id = $_SESSION[ 'mfundos_id' ];
        $hoy = date( 'd-m-Y' );

        $fecha_prod =  substr( $fecha_prodleche, 0, 10 );
        //////////////********************//////////////////////
        /////////////CAMBIANDO FORMATO DE FECHA ////////////
        $f_prodleche = pl_fecha_iso( $fecha_prodleche );
        //////////////********************//////////////////////

        $partes = explode( '-', $fecha_prod );
        $aa = $partes[ 0 ];
        $mes = $partes[ 1 ];
        $dd = $partes[ 2 ];
        $fecha_prod = $dd.'-'.$mes.'-'.$aa;

        @$consulta = Mprolechera::find_by_sql( 'select  id from Mprolecheras where mfundos_id='.$mfundos_id." and
                                                fecha_produccion = CAST ('$f_prodleche' AS DATE) and                                                
                                                codigo_rgb_id_animal_hembra='$cod_animal_h' and status=1;" );

        if ( $consulta == null ) {
            //( B ) si no existe lo guarda...

            $reg_servicio = new Mprolechera();

            $reg_servicio->codigo_rgb_id_animal_hembra = $cod_animal_h;
            $reg_servicio->fecha_produccion = $f_prodleche;
            $reg_servicio->cantidad_leche_m = $kg_leche_m;
            $reg_servicio->cantidad_leche_t = $kg_leche_t;
            $reg_servicio->mfundos_id = $mfundos_id;

            $reg_servicio->user_create = $usuario_id;
            $reg_servicio->created = $hoy;

            if ( $reg_servicio->save() ) {
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

            $respuesta = array( 'resultado'=>'error', 'mensaje'=>'<div class="alert alert-warning alert-dismissable">
              <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
              <h4>
              <i class="icon fa fa-warning"></i>
              Alerta!
              </h4>
              La ' . bovs_especie_hembra(false, 'title') . ' con Codigo ['.$cod_animal_h.'] ya se encuentra registrada para la Fecha ['.$fecha_prodleche.'].
              </div>' );

            // echo json_encode( $respuesta );
        }

    }
    echo json_encode( $respuesta );

    break;

    //// **************************

    case 'edit':

    if ( $fecha_prodleche == '' ) {

        $respuesta = array( 'resultado'=>'error', 'mensaje'=>'<div class="alert alert-warning alert-dismissable">
        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
        <h4>
        <i class="icon fa fa-warning"></i>
        Alerta!
        </h4>
        Ingrese La fecha de Servicio.
        </div>' );

    } else if ( $kg_leche_m == '' ) {

        $respuesta = array( 'resultado'=>'error', 'mensaje'=>'<div class="alert alert-warning alert-dismissable">
        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
        <h4>
        <i class="icon fa fa-warning"></i>
        Alerta!
        </h4>
        Ingrese los Kg de Leche.
        </div>' );

    } else {
        //( A ) si se escribe un nombre se consulta si ya existe...

        $id_prodleche = intval( $id_prodleche );
        $consulta = Mprolechera::find( 'all', array( 'conditions' => array( 'id=?', $id_prodleche ) ) );

        if ( $consulta != null ) {

            session_start();
            $usuario_id = $_SESSION[ 'idusuariox' ];
            $mfundos_id = isset( $_SESSION[ 'mfundos_id' ] ) ? intval( $_SESSION[ 'mfundos_id' ] ) : 0;
            $hoy = date( 'd-m-Y' );

            $reg_servicio = Mprolechera::find( $id_prodleche );
            $f_prodleche  = pl_fecha_iso( $fecha_prodleche );

            /* Fecha que tiene actualmente el registro (regla de los 30 días) */
            $fecha_reg = '';
            if ( $reg_servicio->fecha_produccion instanceof DateTime ) {
                $fecha_reg = $reg_servicio->fecha_produccion->format( 'Y-m-d' );
            } else {
                $fecha_reg = substr( (string)$reg_servicio->fecha_produccion, 0, 10 );
            }

            $fecha_limite = date( 'Y-m-d', strtotime( '-' . PL_DIAS_EDICION . ' days' ) );

            /* ¿Ya existe OTRO registro del mismo animal en esa fecha? */
            $dup = null;
            if ( $f_prodleche !== '' && $mfundos_id > 0 ) {

                $cod_sql = str_replace( "'", "''", $cod_animal_h );

                $dup = Mprolechera::find_by_sql( "select id from mprolecheras
                                                   where mfundos_id = " . $mfundos_id . "
                                                     and status = 1
                                                     and codigo_rgb_id_animal_hembra = '" . $cod_sql . "'
                                                     and fecha_produccion = CAST('" . $f_prodleche . "' AS DATE)
                                                     and id <> " . intval( $id_prodleche ) . "
                                                   limit 1;" );
            }

            if ( $fecha_reg !== '' && $fecha_reg < $fecha_limite ) {

                $respuesta = array( 'resultado'=>'error', 'mensaje'=>'<div class="alert alert-warning alert-dismissable">
                <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                <h4>
                <i class="icon fa fa-lock"></i>
                Alerta!
                </h4>
                Solo se pueden modificar los ordeños de los últimos ' . PL_DIAS_EDICION . ' días.
                </div>' );

            } else if ( $f_prodleche === '' || $f_prodleche < $fecha_limite ) {

                $respuesta = array( 'resultado'=>'error', 'mensaje'=>'<div class="alert alert-warning alert-dismissable">
                <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                <h4>
                <i class="icon fa fa-calendar-times-o"></i>
                Alerta!
                </h4>
                La fecha indicada está fuera del período editable (últimos ' . PL_DIAS_EDICION . ' días).
                </div>' );

            } else if ( $dup != null && count( $dup ) > 0 ) {

                $respuesta = array( 'resultado'=>'error', 'mensaje'=>'<div class="alert alert-warning alert-dismissable">
                <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                <h4>
                <i class="icon fa fa-warning"></i>
                Alerta!
                </h4>
                Ya existe un registro para el animal [' . $cod_animal_h . '] en la fecha [' . $fecha_prodleche . '].
                </div>' );

            } else {

                $reg_servicio->codigo_rgb_id_animal_hembra = $cod_animal_h;
                $reg_servicio->fecha_produccion = $f_prodleche;
                $reg_servicio->cantidad_leche_m = $kg_leche_m;
                $reg_servicio->cantidad_leche_t = $kg_leche_t;
                $reg_servicio->user_modify = $usuario_id;
                $reg_servicio->updated    = date( 'Y-m-d H:i:s' );

                if ( $reg_servicio->save() ) {
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
            }

            //echo json_encode( $respuesta );

        } else {
            //( B ) si exite el nombre verifica el status del registro...

            foreach ( $consulta as $st ) {

                $estado_1 = $st->status;
                $ide = $st->id;

            }

            if ( $estado_1 != 1 ) {
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

              Registro inactivo . ¿Desea incluir ['.$cod_animal_h.'] ?

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
              El Animal ['.$cod_animal_h.'] ya se encuentra registrado.
              </div>' );

            }
            // echo json_encode( $respuesta );
        }

    }
    echo json_encode( $respuesta );

    break;

    /// LISTADO DE ANIMALES APTOS PARA EL SELECTOR

    case 'listar_candidatos_prodleche':
        require_once __DIR__ . '/../lib/animales_aptos_helper.php';
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        $filas = bovs_animales_aptos_listar('prodleche');

        echo json_encode(array(
            'resultado' => 'ok',
            'filas'     => $filas,
            'total'     => count($filas)
        ));
        break;

    /// BUSCA LOS DATOS DE LA HEMBRA PARA SER COLOCADA EN SERVICIO

    case 'get_buscar_rgb_h':

    $buscar = intval( $_POST[ 'buscarx' ] );
    $nro_arete_codigo = ( $_POST[ 'rgb_animal_h' ] );

    $cadena = ' ';
    $cadena_2 = '';

    if ( $nro_arete_codigo == '' ) {

        $resp = array( 'resultado'=>'error', 'mensaje'=>'<div class="alert alert-warning alert-dismissable">
          <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
          <h4>
          <i class="icon fa fa-warning"></i>
          Alerta!
          </h4>
          Debe Ingresar el Código o Numero de Arete de la HEMBRA.
          </div>' );

    } else {

        session_start();
        $mfundos_id = $_SESSION[ 'mfundos_id' ];

        if ( $buscar == 1 ) {

            $rgb_animal = $nro_arete_codigo;
            $partes = explode( '-', $rgb_animal );
            $prefijo = $partes[ 0 ];
            $nro = 0;
            if ( $prefijo == 'SOVCA' || $prefijo == 'BOVS' ) {
                $nro = intval( $partes[ 2 ] );
            } else {
                $nro = intval( $rgb_animal );
            }

            $cod_rgb_animal = 'SOVCA-0' . $mfundos_id . '-0' . $nro;

            $cadena = "and codigo_rgb_id_animal='$cod_rgb_animal' ";
            $cadena_2 = "and codigo_rgb_id_animal_hembra='$cod_rgb_animal' ";

            $criterio = 'Código';

        } else   if ( $buscar == 2 ) {

            $nro_arete = $nro_arete_codigo;

            $cadena = "and nro_arete='$nro_arete' ";
            $cadena_2 = "and nro_arete='$nro_arete' ";
            $criterio = 'Nro de Arete';

        }

        @$data = Manimale::find_by_sql( 'select  id, mestatus_id as estatus, nombre as nombre_h,codigo_rgb_id_animal as cod_animal_h from Manimales where mfundos_id='.$mfundos_id." and mestatus_id=1 and sexo=1 and aptitud in (2,3) $cadena ;" );
        @$data_servicio = Vprolechera::find_by_sql( 'select  id from Vprolecheras where mfundos_id='.$mfundos_id." $cadena_2 ;" );

        if ( $data != null ) {

            foreach ( $data as $rs ) {

                $estatus = $rs->estatus;
                if ( $estatus == 1 ) {
                    $nombre = $rs->cod_animal_h.'*'.$rs->nombre_h;
                    $resp = $nombre;

                } else {
                    $resp = array( 'resultado'=>'error', 'mensaje'=>'<div class="alert alert-danger alert-dismissable">
                  <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                  <h4>
                  <i class="icon fa fa-ban"></i>
                  Alerta!
                  </h4>
                  El '.$criterio.' del Animal NO EXISTE, O no está egistrada con APTITUD de Producción de Leche!!.
                  </div>' );

                }

            }

        } else {
            $resp = array( 'resultado'=>'error', 'mensaje'=>'<div class="alert alert-danger alert-dismissable">
                  <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                  <h4>
                  <i class="icon fa fa-ban"></i>
                  Alerta!
                  </h4>
                  No existe una HEMBRA con el '.$criterio.' ['.$nro_arete_codigo.'] Con APTITUD de Producción de Leche!! !!.
                  </div>' );

        }

    }
    echo json_encode( $resp );
    break;

    #*******************************************************************************
    case 'search':
    session_start();
    $mfundos_id = $_SESSION[ 'mfundos_id' ];

    if ( $id_prodleche != null ) {

        @$data = Vprolechera::find( 'all', array( 'conditions' => array( 'id=? and mfundos_id=?', $id_prodleche, $mfundos_id ) ) );

        if ( $data != null ) {

            foreach ( $data as $rs ) {

                           $fecha_produccion = '';
               
                if ( $rs->fecha_produccion != '' ) {
                    $fecha_produccion = ( string )$rs->fecha_produccion->format( 'd-m-Y' );
                }

                $resp = array(
                    'id_prodleche'=>$rs->id,
                    'cod_animal_h'=>$rs->codigo_rgb_id_animal_hembra,
                    'nombre_animal_h'=>$rs->nombre_animal,
                    'fecha_prodleche'=>$fecha_produccion,
                    'kg_leche_m'=>$rs->cantidad_leche_m,
                    'kg_leche_t'=>$rs->cantidad_leche_t,
                    'kg_total_leche'=>$rs->cantidad_leche_m+$rs->cantidad_leche_t

                );
            }

            echo json_encode( $resp );
        }

    }
    break;

    /////////////////////////////////////////////////////////


     case 'search_rep_prod_leche':

    $buscar = intval( $_POST[ 'buscar_cod' ] );
    $nro_arete_codigo = ( $_POST[ 'rgb_animal' ] );

    $cadena = ' ';

    session_start();
    $mfundos_id = $_SESSION[ 'mfundos_id' ];

    if ( $buscar == 1 ) {

        $rgb_animal = $nro_arete_codigo;
        $partes = explode( '-', $rgb_animal );
        $prefijo = $partes[ 0 ];
        $nro = 0;
        if ( $prefijo == 'SOVCA' || $prefijo == 'BOVS' ) {
            $nro = intval( $partes[ 2 ] );
        } else {
            $nro = intval( $rgb_animal );
        }

        $cod_rgb_animal = 'SOVCA-0' . $mfundos_id . '-0' . $nro;

        $cadena = "and codigo_rgb_id_animal_hembra='$cod_rgb_animal' ";

        $criterio = 'Código';
    } else   if ( $buscar == 2 ) {

        $nro_arete = $nro_arete_codigo;

        $cadena = "and nro_arete='$nro_arete' ";
        $criterio = 'Nro de Arete';
    }

    @$data = Vprolechera::find_by_sql( 'select id as cod_animal from Vprolecheras where mfundos_id=' . $mfundos_id . "  $cadena;" );

    if ( $data != null ) {

        foreach ( $data as $rs ) {

            $resp = array(
                'cod_animal' => $rs->cod_animal

            );
        }
    } else {
        $resp = array(
            'cod_animal' => 0

        );
    }
    echo json_encode( $resp );

    break;
     #*******************************************************************************

      case 'delete':
      $id_pl = ( $_POST[ 'record' ] );

        if ( $id_pl != null ) {
    
            session_start();
            $usuario_id = $_SESSION[ 'idusuariox' ];
            $rol = $_SESSION[ 'rolx' ];
            $hoy = date( 'd-m-Y' );
    
            if ( $id_pl != '' && ( $rol !=3  )) {
    
                $delete_pl = Mprolechera::find( $id_pl );

                /* Solo se pueden eliminar los ordeños de los últimos 30 días */
                $fecha_reg = '';
                if ( $delete_pl->fecha_produccion instanceof DateTime ) {
                    $fecha_reg = $delete_pl->fecha_produccion->format( 'Y-m-d' );
                } else {
                    $fecha_reg = substr( (string)$delete_pl->fecha_produccion, 0, 10 );
                }

                $fecha_limite = date( 'Y-m-d', strtotime( '-' . PL_DIAS_EDICION . ' days' ) );

                if ( $fecha_reg !== '' && $fecha_reg < $fecha_limite ) {

                    echo '<div class="alert alert-warning alert-dismissable">
                        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                        <h4>
                        <i class="icon fa fa-lock"></i>
                        Alerta!
                        </h4>
                        Solo se pueden eliminar los ordeños de los últimos ' . PL_DIAS_EDICION . ' días.
                      </div>';
                    break;
                }

                $delete_pl->user_modify = $usuario_id;
                $delete_pl->updated = $hoy;
                $delete_pl->status = 0;
    
                if ( $delete_pl->save() ) {
    
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
?>
