<?php
require_once __DIR__ . '/../lib/catalogo_fundo_helper.php';
require_once '../models/Mproducto.php';
require_once '../models/Mfundo.php';
require_once '../models/Mcategoriaproducto.php';
require_once '../models/Mpresentacione.php';


date_default_timezone_set('America/Caracas');

@$action = ($_POST["action"]);
@$id_producto = ( $_POST[ 'id_producto' ] );
@$categoria = ($_POST["categoria"]);
@$nombre_producto = ($_POST["nombre_producto"]);
@$presentacion = ($_POST["presentacion"]);

//**********************************/

switch ($action){


    case 'edit':

        if ( $nombre_producto == '' ) {
    
            $respuesta = array( 'resultado'=>'error', 'mensaje'=>'<div class="alert alert-warning alert-dismissable">
                    <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                    <h4>
                    <i class="icon fa fa-warning"></i>
                    Alerta!
                    </h4>
                    Ingrese el nombre del Producto
                    </div>' );
    
        } else  if ( $categoria == '' ) {
    
            $respuesta = array( 'resultado'=>'error', 'mensaje'=>'<div class="alert alert-warning alert-dismissable">
                    <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                    <h4>
                    <i class="icon fa fa-warning"></i>
                    Alerta!
                    </h4>
                    Ingrese la Categoria de Producto
                    </div>' );
    
        } else  if ( $presentacion == '' ) {
    
            $respuesta = array( 'resultado'=>'error', 'mensaje'=>'<div class="alert alert-warning alert-dismissable">
                    <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                    <h4>
                    <i class="icon fa fa-warning"></i>
                    Alerta!
                    </h4>
                    Ingrese la Presentacion de Producto
                    </div>' );
    
        } else {
            //( A ) si se escribe un nombre se consulta si ya existe...
    
            session_start();
            $usuario_id = $_SESSION[ 'idusuariox' ];
            @$rol = $_SESSION[ 'rolx' ];
            @$sesion_mfundos_id = $_SESSION[ 'mfundos_id' ];

            $consulta = Mproducto::find( 'all', array( 'conditions' => array( 'id=?', $id_producto ) ) );

            if ( $consulta != null ) {
    
        
                //( B ) si no existe lo guarda...
    
                if ( ( $rol != 1 ) && ( $rol != 4 ) ) {
                    @$mfundos_id = $sesion_mfundos_id;
    
                } else {
                    $mfundos_id = 0;
                }
                $hoy = date( 'd-m-Y' );
    
                $reg_productos = Mproducto::find($id_producto);
                $reg_productos->nombre = $nombre_producto;    
                $reg_productos->mcategoriaproductos_id = $categoria;
                $reg_productos->presetacion_id = $presentacion; 
                $reg_productos->user_modify = $usuario_id;
                $reg_productos->updated = $hoy;
    
                if ( $reg_productos->save() ) {
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
        
                          Registro inactivo . ¿Desea incluir ['.$nombre_producto.'] ?
        
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
                          EL Produco ['.$nombre_producto.'] ya se encuentra registrada.
                          </div>' );
    
                }
                // echo json_encode( $respuesta );
            }
    
        }
        echo json_encode( $respuesta );
    
        break;
        #*******************************************************************************

    case 'add':

        if ( $nombre_producto == '' ) {
    
            $respuesta = array( 'resultado'=>'error', 'mensaje'=>'<div class="alert alert-warning alert-dismissable">
                <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                <h4>
                <i class="icon fa fa-warning"></i>
                Alerta!
                </h4>
                Ingrese el nombre del Producto
                </div>' );
    
        } else  if ( $categoria == '' ) {
    
            $respuesta = array( 'resultado'=>'error', 'mensaje'=>'<div class="alert alert-warning alert-dismissable">
                <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                <h4>
                <i class="icon fa fa-warning"></i>
                Alerta!
                </h4>
                Ingrese la Categoria de Producto
                </div>' );
    
        }else  if ( $presentacion == '' ) {
    
            $respuesta = array( 'resultado'=>'error', 'mensaje'=>'<div class="alert alert-warning alert-dismissable">
                <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                <h4>
                <i class="icon fa fa-warning"></i>
                Alerta!
                </h4>
                Ingrese la Presentacion de Producto
                </div>' );
    
        } else {
            //( A ) si se escribe un nombre se consulta si ya existe...
               
            session_start();
            $usuario_id = $_SESSION[ 'idusuariox' ];
            @$rol = $_SESSION[ 'rolx' ];
            @$sesion_mfundos_id = $_SESSION[ 'mfundos_id' ];

            $consulta = Mproducto::find( 'all', array( 'conditions' => array( 'nombre=? and mfundos_id=?', $nombre_producto,$sesion_mfundos_id ) ) );
    
            if ( $consulta == null ) {
                //( B ) si no existe lo guarda...
 
    
                if ( ( $rol != 1 ) && ( $rol != 4 ) ) {
                    @$mfundos_id = $sesion_mfundos_id;
    
                } else {
                    $mfundos_id = 0;
                }
                $hoy = date( 'd-m-Y' );
    
                $reg_productos = new Mproducto();
                $reg_productos->nombre = $nombre_producto;    
                $reg_productos->mcategoriaproductos_id = $categoria;
                $reg_productos->presetacion_id = $presentacion;               
                $reg_productos->mfundos_id = $mfundos_id;    
                $reg_productos->user_create = $usuario_id;
                $reg_productos->created = $hoy;
    
                if ( $reg_productos->save() ) {
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
    
                      Registro inactivo . ¿Desea incluir ['.$nombre_producto.'] ?
    
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
                      EL Produco ['.$nombre_producto.'] ya se encuentra registrada.
                      </div>' );
    
                }
                // echo json_encode( $respuesta );
            }
    
        }
        echo json_encode( $respuesta );
    
        break;
        #*******************************************************************************


case 'get_categoria':

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

      if ( !empty( $id_sesion ) && ( ( $rol == 1 ) || ( $rol == 4 ) ) ) {
          //privilegios del programador y Supervisor

          $data = Mcategoriaproducto::find( 'all', array( 'conditions' => array( 'status=?', 1 ), 'order' => 'nombre  asc ' ) );

      } else if ( !empty( $id_sesion ) && ( ( $rol == 2 ) || ( $rol == 3 ) ) ) {

          $data = Mcategoriaproducto::find( 'all', array( 'conditions' => array( 'status=1 and (mfundos_id=? or mfundos_id=0)', $mfundos_id ), 'order' => 'nombre  asc  ' ) );

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

  ///////////#######################################################################

  /* Categorías que realmente tienen productos disponibles para el fundo
     activo. Se usa en los formularios de Control Sanitario (individual y
     por lotes) para no mostrar categorías vacías. El mantenedor de
     productos sigue usando `get_categoria` para poder crear el primero. */
  case 'get_categoria_con_productos':

    @session_start();
    @$id_sesion = $_SESSION[ 'idsessionx' ];
    @$mfundos_id = intval( isset( $_SESSION[ 'mfundos_id' ] ) ? $_SESSION[ 'mfundos_id' ] : 0 );

    if ( empty( $id_sesion ) ) {
        echo '<option value="">--</option>';
        break;
    }

    $filtro_fundo = ( $mfundos_id > 0 )
        ? " AND ( p.mfundos_id = {$mfundos_id} OR p.mfundos_id = 0 ) "
        : '';

    $data = Mcategoriaproducto::find_by_sql("
        SELECT c.id, c.nombre
          FROM mcategoriaproductos c
          INNER JOIN mproductos p ON p.mcategoriaproductos_id = c.id
         WHERE c.status = 1
           AND p.status = 1
           {$filtro_fundo}
         GROUP BY c.id, c.nombre
         ORDER BY c.nombre ASC
    ");

    if ( is_array( $data ) && count( $data ) > 0 ) {
        $resp = '<option value="">--</option>';
        foreach ( $data as $rs ) {
            $resp .= '<option value="' . $rs->id . '">' . $rs->nombre . '</option>';
        }
    } else {
        $resp = '<option value="">No hay Categor&iacute;a con productos para este fundo</option>';
    }

    echo $resp;
    break;

  ///////////#######################################################################

  case 'get_presentacion':

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
  
        if ( !empty( $id_sesion ) && ( ( $rol == 1 ) || ( $rol == 4 ) ) ) {
            //privilegios del programador y Supervisor
  
            $data = Mpresentacione::find( 'all', array( 'conditions' => array( 'status=?', 1 ), 'order' => 'nombre  asc ' ) );
  
        } else if ( !empty( $id_sesion ) && ( ( $rol == 2 ) || ( $rol == 3 ) ) ) {
  
            $data = Mpresentacione::find( 'all', array( 'conditions' => array( 'status=1 and (mfundos_id=? or mfundos_id=0)', $mfundos_id ), 'order' => 'nombre  asc ' ) );
  
        }
  
    
  
    if ( $data != null ) {
        $resp = '<option value="">--</option>';
        foreach ( $data as $rs ) {
            $resp .= '<option value="'.$rs->id.'">'.$rs->nombre.'</option>';
            $resp .= '<hidden>';
        }
  
    } else {
        $resp = '<option value="">No hay Presentacion para Agregar</option>';
    }
  
    echo $resp;
    break;
  
  
  ///////////#######################################################################


  case 'get_productos':
    @session_start();
    @$id_sesion = $_SESSION[ 'idsessionx' ];
    @$mfundos_id = intval( isset( $_SESSION[ 'mfundos_id' ] ) ? $_SESSION[ 'mfundos_id' ] : 0 );
    @$rol = $_SESSION[ 'rolx' ];

    $categoria = intval( $categoria );

    /* Solo productos activos y disponibles para el fundo activo
       (propios + globales). Antes se devolvían productos de cualquier
       fundo, incluso inactivos. */
    $filtro_fundo = ( $mfundos_id > 0 )
        ? " AND ( mfundos_id = {$mfundos_id} OR mfundos_id = 0 ) "
        : '';
    $filtro_categoria = ( $categoria > 0 )
        ? " AND mcategoriaproductos_id = {$categoria} "
        : '';

    @$data = Mproducto::find_by_sql("select id, nombre
                       from mproductos
                      where status = 1
                      {$filtro_categoria}
                      {$filtro_fundo}
                      order by nombre asc;");
       
    
    if($data !=null){
         $resp = '<option value="">Seleccione el Producto</option>';
             foreach($data as $rs){
               /* Las p&aacute;ginas son UTF-8 y la BD tambi&eacute;n: se env&iacute;a
                  el nombre tal cual para no romper acentos. */
               $resp .= '<option value="'.$rs->id.'">'.$rs->nombre.'</option>';
               $resp .= '<hidden>';
             }
       
         }else{
         $resp = '<option value="">..</option>';
         }
       
           echo $resp;
      
   break;

     #*******************************************************************************
     case 'search':
        session_start();
        $mfundos_id = $_SESSION[ 'mfundos_id' ];
        if ( $id_producto != null ) {
    
            @$data = Mproducto::find( 'all', array( 'conditions' => array( 'id=? and mfundos_id=?', $id_producto, $mfundos_id ) ) );
    
            if ( $data != null ) {
    
                foreach ( $data as $rs ) {
    
                    $resp = array(
                        'id'=>$rs->id,
                        'nombre'=>$rs->nombre,
                        'categoria'=>$rs->mcategoriaproductos_id,
                        'presentacion'=>$rs->presetacion_id
                    );
                }
    
                echo json_encode( $resp );
            }
    
        }
        break;
    
      #*******************************************************************************

      case 'delete':

        if ( $id_producto != null ) {
    
            session_start();
            $usuario_id = $_SESSION[ 'idusuariox' ];
            $rol = $_SESSION[ 'rolx' ];
            $hoy = date( 'd-m-Y' );
    
            if ( $usuario_id != '' && ( $rol !=3  )) {
    
                $reg_productos = Mproducto::find( $id_producto );
                $reg_productos->user_modify = $usuario_id;
                $reg_productos->updated = $hoy;
                $reg_productos->status = 0;
    
                if ( $reg_productos->save() ) {
    
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


}//end switch
?>
