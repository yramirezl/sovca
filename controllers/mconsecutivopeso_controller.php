<?php

require_once '../models/Manimale.php';
require_once '../models/Mconsecutivopeso.php';
require_once '../models/Vconsecutivopeso.php';

include '../lib/sanitizar.php';

date_default_timezone_set( 'America/Caracas' );

/*
 * Días en que un pesaje sigue siendo editable.
 * En pesaje la ventana es más corta que en producción de leche (30):
 * quien pesa a diario solo necesita corregir los últimos días y un
 * cambio viejo altera las ganancias (GDP) de todos los pesajes siguientes.
 */
if ( !defined( 'CP_DIAS_EDICION' ) ) {
    define( 'CP_DIAS_EDICION', 7 );
}

/*
 * Normaliza la fecha del pesaje a Y-m-d.
 * Acepta Y-m-d, dd-mm-yyyy y dd/mm/yyyy. Sin esto PHP interpreta
 * "07-08-2026" como m-d-Y (mes 07) cuando el día es <= 12.
 */
function cp_fecha_iso( $valor ) {

    $valor = trim( (string)$valor );
    if ( $valor === '' ) return '';

    $formatos = array( 'Y-m-d', 'd-m-Y', 'd/m/Y' );

    foreach ( $formatos as $formato ) {
        $fecha = DateTime::createFromFormat( $formato, $valor );
        if ( $fecha instanceof DateTime ) {
            $errores = DateTime::getLastErrors();
            if ( is_array( $errores ) && ( $errores[ 'warning_count' ] > 0 || $errores[ 'error_count' ] > 0 ) ) {
                continue;
            }
            return $fecha->format( 'Y-m-d' );
        }
    }

    return '';
}

/* Fecha (Y-m-d) que tiene guardada un registro de pesaje. */
function cp_fecha_registro( $registro ) {

    if ( !$registro ) return '';

    if ( $registro->fecha_pesaje instanceof DateTime ) {
        return $registro->fecha_pesaje->format( 'Y-m-d' );
    }

    return substr( (string)$registro->fecha_pesaje, 0, 10 );
}

@$action = ( $_POST[ 'action' ] );
@$id = ( $_POST[ 'id_consecpeso' ] );

/***********************************/
@$cod_animal = ( $_POST[ 'cod_animal' ] );
@$fecha_pesaje = ( $_POST[ 'fecha_pesaje' ] );

@$peso = ( $_POST[ 'peso' ] );

/// Nuevas Variables
@$rgb_animal = ( $_POST[ 'rgb_animal' ] );
@$id_consecpeso = ( $_POST[ 'id_consecpeso' ] );

/**********************************/

switch ( $action ) {

    //// **************************

    case 'add':

    if ( $cod_animal == '' ) {

        $respuesta = array( 'resultado'=>'error', 'mensaje'=>'<div class="alert alert-warning alert-dismissable">
        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
        <h4>
        <i class="icon fa fa-warning"></i>
        Alerta!
        </h4>
        Ingrese El Código de la Hembra.
        </div>' );

    } else if ( $fecha_pesaje == '' ) {

        $respuesta = array( 'resultado'=>'error', 'mensaje'=>'<div class="alert alert-warning alert-dismissable">
        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
        <h4>
        <i class="icon fa fa-warning"></i>
        Alerta!
        </h4>
        Indique la Fecha del Pesaje.
        </div>' );

    } else {
        //( A ) si se escribe un nombre se consulta si ya existe...

        session_start();
        $usuario_id = $_SESSION[ 'idusuariox' ];
        $mfundos_id = $_SESSION[ 'mfundos_id' ];
        $hoy = date( 'd-m-Y' );

        $fecha_prod =  substr( $fecha_pesaje, 0, 10 );

        //////////////********************//////////////////////
        /////////////CAMBIANDO FORMATO DE FECHA ////////////
        $f_pesaje = cp_fecha_iso( $fecha_pesaje );
        //////////////********************//////////////////////

        if ( $f_pesaje === '' ) {
            echo json_encode( array( 'resultado'=>'error', 'mensaje'=>'<div class="alert alert-warning alert-dismissable">
            <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
            <h4>
            <i class="icon fa fa-warning"></i>
            Alerta!
            </h4>
            La fecha del pesaje no es válida.
            </div>' ) );
            break;
        }

        $partes = explode( '-', $fecha_prod );
        $aa = $partes[ 0 ];
        $mes = $partes[ 1 ];
        $dd = $partes[ 2 ];
        $fecha_prod = $dd.'-'.$mes.'-'.$aa;

        @$consulta = Mconsecutivopeso::find_by_sql( 'select  id from MconsecutivopesoS where mfundos_id='.$mfundos_id." and
                                                    fecha_pesaje = CAST ('$f_pesaje' AS DATE) and                                                
                                                    codigo_rgb_id_animal='$cod_animal' and status=1;" );

        if ( $consulta == null ) {
            //( B ) si no existe lo guarda...

            $reg_consecpeso = new Mconsecutivopeso();

            $reg_consecpeso->codigo_rgb_id_animal = $cod_animal;
            $reg_consecpeso->fecha_pesaje = $f_pesaje;
            $reg_consecpeso->peso = $peso;
            $reg_consecpeso->mfundos_id = $mfundos_id;

            $reg_consecpeso->user_create = $usuario_id;
            $reg_consecpeso->created = $hoy;

            if ( $reg_consecpeso->save() ) {
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
            El ANIMAL con Codigo ['.$cod_animal.'] ya se encuentra registrada para la Fecha ['.$fecha_pesaje.'].
            </div>' );

            // echo json_encode( $respuesta );
        }
    }
    echo json_encode( $respuesta );

    break;

    //// **************************

    case 'edit':

    if ( $peso == '' ) {

        $respuesta = array( 'resultado'=>'error', 'mensaje'=>'<div class="alert alert-warning alert-dismissable">
        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
        <h4>
        <i class="icon fa fa-warning"></i>
        Alerta!
        </h4>
        Ingrese El Peso del Animal.
        </div>' );

    } else if ( $fecha_pesaje == '' ) {

        $respuesta = array( 'resultado'=>'error', 'mensaje'=>'<div class="alert alert-warning alert-dismissable">
        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
        <h4>
        <i class="icon fa fa-warning"></i>
        Alerta!
        </h4>
        Indique la Fecha del Pesaje.
        </div>' );

    } else {
        //( A ) si se escribe un nombre se consulta si ya existe...
        session_start();
        $usuario_id = $_SESSION[ 'idusuariox' ];
        $mfundos_id = $_SESSION[ 'mfundos_id' ];
        $id_consecpeso = intval( $_POST[ 'id_consecpeso' ] );
        //////////////********************//////////////////////
        /////////////CAMBIANDO FORMATO DE FECHA ////////////
        $f_pesaje = cp_fecha_iso( $fecha_pesaje );
        //////////////********************//////////////////////

        if ( $f_pesaje === '' ) {

            $respuesta = array( 'resultado'=>'error', 'mensaje'=>'<div class="alert alert-warning alert-dismissable">
            <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
            <h4>
            <i class="icon fa fa-warning"></i>
            Alerta!
            </h4>
            La fecha del pesaje no es válida.
            </div>' );
            echo json_encode( $respuesta );
            break;
        }

        /* Regla de la ventana editable: ni el registro original ni la
           fecha destino pueden estar fuera de los últimos N días. */
        $fecha_limite = date( 'Y-m-d', strtotime( '-' . CP_DIAS_EDICION . ' days' ) );

        @$reg_actual = Mconsecutivopeso::find_by_sql( 'select id, fecha_pesaje from mconsecutivopesos
                                                       where id=' . $id_consecpeso . '
                                                         and mfundos_id=' . $mfundos_id . '
                                                         and status=1;' );

        if ( !is_array( $reg_actual ) || count( $reg_actual ) == 0 ) {

            $respuesta = array( 'resultado'=>'error', 'mensaje'=>'<div class="alert alert-danger alert-dismissable">
            <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
            <h4>
            <i class="icon fa fa-ban"></i>
            Alerta!
            </h4>
            El registro de pesaje ya no existe.
            </div>' );
            echo json_encode( $respuesta );
            break;
        }

        $fecha_reg = cp_fecha_registro( $reg_actual[ 0 ] );

        if ( ( $fecha_reg !== '' && $fecha_reg < $fecha_limite ) || $f_pesaje < $fecha_limite ) {

            $respuesta = array( 'resultado'=>'error', 'mensaje'=>'<div class="alert alert-warning alert-dismissable">
            <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
            <h4>
            <i class="icon fa fa-lock"></i>
            Alerta!
            </h4>
            Solo se pueden modificar los pesajes de los últimos ' . CP_DIAS_EDICION . ' días.
            </div>' );
            echo json_encode( $respuesta );
            break;
        }
        @$consulta = Mconsecutivopeso::find_by_sql( "select  codigo_rgb_id_animal from Mconsecutivopesos 
                                                        where mfundos_id=".$mfundos_id." and codigo_rgb_id_animal='$cod_animal'
                                                        and codigo_rgb_id_animal in (select codigo_rgb_id_animal 
                                                            from Mconsecutivopesos 
                                                             where codigo_rgb_id_animal='$cod_animal' and
                                                                id!=".$id_consecpeso.' and mfundos_id='.$mfundos_id." and                                                        
                                                                fecha_pesaje = CAST ('$f_pesaje' AS DATE)) and status=1;" );

        if ( $consulta == null ) {

            @$consulta_data = Mconsecutivopeso::find_by_sql( "select  id from Mconsecutivopesos 
                                                                where id=".$id_consecpeso.'  and mfundos_id='.$mfundos_id.'  and  status=1;' );

            if ( $consulta_data != null ) {

                $hoy = date( 'Y-m-d H:i:s' );

                $reg_consecpeso = Mconsecutivopeso::find( $id_consecpeso );
                $reg_consecpeso->fecha_pesaje = $f_pesaje;
                $reg_consecpeso->peso = $peso;
                $reg_consecpeso->user_modify = $usuario_id;
                $reg_consecpeso->updated = $hoy;
                if ( $reg_consecpeso->save() ) {
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
              Error al  registrar los datos.
              </div>' );
                }
            } else {
                $respuesta = array( 'resultado'=>'error', 'mensaje'=>'<div class="alert alert-danger alert-dismissable">
          <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
          <h4>
          <i class="icon fa fa-ban"></i>
          Alerta!
          </h4>
          Error  al registrar los datos.
          </div>' );
            }

            //echo json_encode( $respuesta );

        } else {

            $respuesta = array( 'resultado'=>'error', 'mensaje'=>'<div class="alert alert-danger alert-dismissable">
              <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
              <h4>
              <i class="icon fa fa-ban"></i>
              Alerta!
              </h4>
              Fecha de Pesaje Repetida para el Animal  .
              </div>' );
        }

    }
    echo json_encode( $respuesta );

    break;

    /// LISTADO DE ANIMALES APTOS PARA EL SELECTOR

    case 'listar_candidatos_consecutivo_peso':
        require_once __DIR__ . '/../lib/animales_aptos_helper.php';
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

        $filas = bovs_animales_aptos_listar('consecutivo_peso');

        echo json_encode(array(
            'resultado' => 'ok',
            'filas'     => $filas,
            'total'     => count($filas)
        ));
        break;

    /// BUSCA LOS DATOS DE LA HEMBRA PARA SER COLOCADA EN SERVICIO

    case 'get_buscar_rgb':

    session_start();
    $mfundos_id = $_SESSION[ 'mfundos_id' ];
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
          Debe Ingresar el Código o Numero de Arete del Animal.
          </div>' );

    } else {

        session_start();
        $mfundos_id = $_SESSION[ 'mfundos_id' ];

        if ( $buscar == 1 ) {

            $rgb_animal = $nro_arete_codigo;
            $partes = explode( '-', $rgb_animal );
            $prefijo = $partes[ 0 ];
            $nro = 0;
            if ($prefijo == 'SOVCA') {
                $nro = intval( $partes[ 2 ] );
            } else {
                $nro = intval( $rgb_animal );
            }

            $cod_rgb_animal = 'SOVCA-0' . $mfundos_id . '-0' . $nro;

            $cadena = "and codigo_rgb_id_animal='$cod_rgb_animal' ";

            $criterio = 'Código';

        } else   if ( $buscar == 2 ) {

            $nro_arete = $nro_arete_codigo;

            $cadena = "and nro_arete='$nro_arete' ";
            $criterio = 'Nro de Arete';

        }

        @$data = Manimale::find_by_sql( 'select  id, mestatus_id as estatus, nombre as nombre,codigo_rgb_id_animal as cod_animal from Manimales where mfundos_id='.$mfundos_id." and mestatus_id=1 $cadena;" );

        if ( $data != null ) {

            foreach ( $data as $rs ) {

                $estatus = $rs->estatus;
                if ( $estatus == 1 ) {
                    $nombre = $rs->cod_animal.'*'.$rs->nombre;
                    $resp = $nombre;

                } else {
                    $resp = array( 'resultado'=>'error', 'mensaje'=>'<div class="alert alert-danger alert-dismissable">
                  <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                  <h4>
                  <i class="icon fa fa-ban"></i>
                  Alerta!
                  </h4>
                  El Código del Animal NO EXISTE!!.
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
                  No existe Animal con el  ['.$criterio.'] ['.$cod_rgb_animal.'] !!.
                  </div>' );

        }
    }
    echo json_encode( $resp );
    break;

    #*******************************************************************************
    case 'search':

    if ( $id_consecpeso != null ) {

        @$data = Vconsecutivopeso::find( 'all', array( 'conditions' => array( 'id=?', $id_consecpeso ) ) );

           

        if ( $data != null ) {

            foreach ( $data as $rs ) {

                 $fecha_pesaje = '';

                if ( $rs->fecha_pesaje != '' ) {
                    $fecha_pesaje = ( string )$rs->fecha_pesaje->format( 'd-m-Y' );
                }

                $resp = array(
                    'id_consecpeso'=>$rs->id,
                    'cod_animal'=>$rs->codigo_rgb_id_animal,
                    'nombre_animal'=>$rs->nombre_animal,
                    'fecha_pesaje'=>$fecha_pesaje,
                    'peso'=>$rs->peso

                );
            }

            echo json_encode( $resp );
        }

    }
    break;
    /////////////////////////////////////////////////////////


     case 'search_rep_consc_peso':

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
        if ($prefijo == 'SOVCA') {
            $nro = intval( $partes[ 2 ] );
        } else {
            $nro = intval( $rgb_animal );
        }

        $cod_rgb_animal = 'SOVCA-0' . $mfundos_id . '-0' . $nro;

        $cadena = "and codigo_rgb_id_animal='$cod_rgb_animal' ";

        $criterio = 'Código';
    } else   if ( $buscar == 2 ) {

        $nro_arete = $nro_arete_codigo;

        $cadena = "and nro_arete='$nro_arete' ";
        $criterio = 'Nro de Arete';
    }

    @$data = Vconsecutivopeso::find_by_sql( 'select id as cod_animal from Vconsecutivopesos where mfundos_id=' . $mfundos_id . "  $cadena;" );

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
      $id_cp = ( $_POST[ 'record' ] );

        if ( $id_cp != null ) {
    
            session_start();
            $usuario_id = $_SESSION[ 'idusuariox' ];
            $rol = $_SESSION[ 'rolx' ];
            $hoy = date( 'Y-m-d H:i:s' );
    
            if ( $id_cp != '' && ( $rol !=3  )) {
    
                $delete_cp = Mconsecutivopeso::find( $id_cp );

                /* Solo se pueden eliminar los pesajes de los últimos N días */
                $fecha_limite = date( 'Y-m-d', strtotime( '-' . CP_DIAS_EDICION . ' days' ) );
                $fecha_reg    = cp_fecha_registro( $delete_cp );

                if ( $fecha_reg !== '' && $fecha_reg < $fecha_limite ) {

                    echo '<div class="alert alert-warning alert-dismissable">
                        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                        <h4>
                        <i class="icon fa fa-lock"></i>
                        Alerta!
                        </h4>
                        Solo se pueden eliminar los pesajes de los últimos ' . CP_DIAS_EDICION . ' días.
                      </div>';
                    break;
                }

                $delete_cp->user_modify = $usuario_id;
                $delete_cp->updated = $hoy;
                $delete_cp->status = 0;
    
                if ( $delete_cp->save() ) {
    
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