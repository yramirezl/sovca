<?php
require_once __DIR__ . '/../lib/catalogo_fundo_helper.php';
require_once '../models/Mcategoria.php';
require_once __DIR__ . '/../lib/categoria_rangos_helper.php';
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
/* 1 = el usuario ya vio las advertencias de rango y decidió continuar. */
@$forzar = (int) ( isset( $_POST[ 'forzar' ] ) ? $_POST[ 'forzar' ] : 0 );

/**********************************/

switch ( $action ) {

    #*******************************************************************************
    /* VALIDACIÓN EN VIVO DE LA CADENA DE EDADES
       ---------------------------------------------------------------------
       El formulario la llama mientras el usuario escribe y pinta las
       categorías del mismo sexo con sus rangos. Devuelve además los errores
       (bloquean) y los avisos (no bloquean, pero se muestran). */
    case 'validar_rangos':
    @session_start();

    $fundo_val = bovs_catalogo_fundo_id();
    $sexo_val  = (int) ( isset( $_POST[ 'sexo' ] ) ? $_POST[ 'sexo' ] : 0 );
    $min_val   = (int) ( isset( $_POST[ 'edadminima' ] ) ? $_POST[ 'edadminima' ] : 0 );
    $max_val   = (int) ( isset( $_POST[ 'edadmaxima' ] ) ? $_POST[ 'edadmaxima' ] : 0 );
    $id_val    = (int) ( isset( $_POST[ 'id_categoria' ] ) ? $_POST[ 'id_categoria' ] : 0 );

    /* El administrador sin fundo activo trabaja sobre el catálogo global. */
    if ( $fundo_val <= 0 ) {
        $fundo_val = 0;
    }

    /* Si el nombre coincide con una categoría general, el alta la guarda como
       personalización: aquí se excluye para no avisar de un cruce con la
       categoría que se va a reemplazar. */
    $padre_val = bovs_categoria_padre_global(
        $fundo_val,
        $nombre_categoria,
        $sexo_val,
        bovs_catalogo_tipo_ganaderia()
    );

    if ( $id_val <= 0 && $padre_val > 0 ) {
        $id_val = $padre_val;
    }

    $analisis = bovs_analizar_rango_categoria(
        $fundo_val,
        bovs_catalogo_tipo_ganaderia(),
        $sexo_val,
        $min_val,
        $max_val,
        $id_val,
        $nombre_categoria
    );

    echo json_encode( array(
        'resultado'      => 'ok',
        'errores'        => $analisis[ 'errores' ],
        'avisos'         => $analisis[ 'avisos' ],
        'sugerencia_min' => $analisis[ 'sugerencia_min' ],
        'cadena'         => bovs_categoria_cadena_json( $analisis[ 'cadena' ], $id_val ),
    ) );
    break;

    #*******************************************************************************
    case 'edit_cat':

    /* =====================================================================
       EDITAR / PERSONALIZAR UNA CATEGORÍA
       ---------------------------------------------------------------------
       · Productor (rol 2/3): si la categoría es GLOBAL se crea una copia en
         su fundo (`id_padre` = la global) para no cambiar el catálogo de
         todos los fundos. Si ya es suya, se edita directamente.
       · Administrador (rol 1/4): edita la categoría dentro de SU ámbito. Antes
         se forzaba `mfundos_id = 0`, así que al editar una categoría de un
         fundo se la movía al catálogo global sin querer.
       · En los dos casos se valida la cadena de edades antes de guardar
         (ver lib/categoria_rangos_helper.php).
       ===================================================================== */
    session_start();

    $usuario_id       = $_SESSION[ 'idusuariox' ];
    $rol              = (int) $_SESSION[ 'rolx' ];
    $fundo_sesion     = (int) $_SESSION[ 'mfundos_id' ];
    $tipo_ganaderia   = (int) ( isset( $_SESSION[ 'tipo_ganaderia' ] ) ? $_SESSION[ 'tipo_ganaderia' ] : 0 );
    $ahora            = date( 'Y-m-d H:i:s' );
    $nombre_categoria = strtoupper( trim( (string) $nombre_categoria ) );

    if ( $nombre_categoria === '' ) {

        $respuesta = array( 'resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
                <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                <h4>
                <i class="icon fa fa-warning"></i>
                Alerta!
                </h4>
                Ingrese el nombre de la categoría.
                </div>' );

    } elseif ( (int) $id_categoria <= 0 ) {

        $respuesta = array( 'resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
                <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                <h4>
                <i class="icon fa fa-warning"></i>
                Alerta!
                </h4>
                Seleccione la categoría que desea modificar.
                </div>' );

    } elseif ( trim( (string) $edadminima ) === '' || trim( (string) $edadmaxima ) === '' ) {

        $respuesta = array( 'resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
                <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                <h4>
                <i class="icon fa fa-warning"></i>
                Alerta!
                </h4>
                Ingrese la edad mínima y la edad máxima (en meses).
                </div>' );

    } else {

        $categoria_actual = Mcategoria::find( (int) $id_categoria );

        if ( !$categoria_actual ) {

            $respuesta = array( 'resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
                    <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                    <h4>
                    <i class="icon fa fa-ban"></i>
                    Alerta!
                    </h4>
                    La categoría ya no existe. Actualice el listado.
                    </div>' );

        } else {

            $fundo_actual = (int) $categoria_actual->mfundos_id;

            /* Ámbito donde quedará el registro y permiso del usuario. */
            $permiso_ok = true;
            if ( $rol === 2 || $rol === 3 ) {
                if ( $fundo_actual > 0 && $fundo_actual !== $fundo_sesion ) {
                    $permiso_ok = false;
                }
                $fundo_destino = ( $fundo_actual > 0 ) ? $fundo_actual : $fundo_sesion;
            } else {
                $fundo_destino = $fundo_actual;
            }

            if ( !$permiso_ok ) {

                $respuesta = array( 'resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
                        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                        <h4>
                        <i class="icon fa fa-ban"></i>
                        Alerta!
                        </h4>
                        La categoría pertenece a otro fundo y no puede modificarla.
                        </div>' );

            } else {

                $analisis = bovs_analizar_rango_categoria(
                    $fundo_destino,
                    $tipo_ganaderia,
                    (int) $sexo,
                    (int) $edadminima,
                    (int) $edadmaxima,
                    (int) $id_categoria,
                    $nombre_categoria
                );

                /* Solo se pregunta si el usuario CAMBIÓ las edades: renombrar
                   una categoría que ya venía cruzada no debe obligar a
                   confirmar nada. */
                $cambio_rango = ( (int) $categoria_actual->edadminima !== (int) $edadminima
                               || (int) $categoria_actual->edadmaxima !== (int) $edadmaxima );

                if ( count( $analisis[ 'errores' ] ) > 0 ) {

                    $respuesta = array(
                        'resultado' => 'error',
                        'mensaje'   => bovs_mensaje_errores_categoria( $analisis[ 'errores' ] )
                    );

                } elseif ( count( $analisis[ 'avisos' ] ) > 0 && $forzar !== 1 && $cambio_rango ) {

                    /* El rango se cruza con otro: el formulario pregunta antes
                       de guardar y reenvía con forzar = 1. */
                    $respuesta = bovs_respuesta_advertencia_categoria( $analisis[ 'avisos' ] );

                } else {

                    /* Si el destino es el fundo y la categoría era global, se
                       guarda una copia: el catálogo global no se toca. */
                    $es_personalizacion = ( $fundo_destino !== $fundo_actual );

                    if ( $es_personalizacion ) {
                        $reg_categoria = new Mcategoria();
                        $reg_categoria->id_padre           = (int) $categoria_actual->id;
                        $reg_categoria->mfundos_id         = $fundo_destino;
                        $reg_categoria->mtipoganaderias_id = (int) $categoria_actual->mtipoganaderias_id;
                        $reg_categoria->sexo               = (int) $sexo;
                        $reg_categoria->status             = 1;
                        $reg_categoria->created            = $ahora;
                        $reg_categoria->user_create        = $usuario_id;
                    } else {
                        $reg_categoria = $categoria_actual;
                        $reg_categoria->sexo        = (int) $sexo;
                        $reg_categoria->user_modify = $usuario_id;
                        $reg_categoria->updated     = $ahora;
                    }

                    $reg_categoria->nombre     = $nombre_categoria;
                    $reg_categoria->edadminima = (int) $edadminima;
                    $reg_categoria->edadmaxima = (int) $edadmaxima;

                    if ( $reg_categoria->save() ) {

                        $respuesta = array(
                            'resultado' => 'ok',                            'avisos'    => $analisis[ 'avisos' ],                            'mensaje'   => '<div class="alert alert-success alert-dismissable">
                                <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                                <h4>
                                <i class="icon fa fa-check"></i>
                                Alerta!
                                </h4>
                                Los datos han sido actualizados exitosamente !.'
                                . ( $es_personalizacion
                                    ? '<br><small>Se creó una versión de esta categoría para su fundo; el catálogo general no cambia.</small>'
                                    : '' )
                                . '</div>' . bovs_mensaje_avisos_categoria( $analisis[ 'avisos' ] )
                        );

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
                }
            }
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

    /* Ámbito donde quedará la categoría y validación de la cadena de edades.
       El administrador crea catálogo global; el productor, el de su fundo. */
    $mfundos_id_add  = ( ( $rol == 1 ) || ( $rol == 4 ) ) ? 0 : (int) $mfundos_id_session;
    $tipo_ganaderia_add = (int) ( isset( $_SESSION[ 'tipo_ganaderia' ] ) ? $_SESSION[ 'tipo_ganaderia' ] : 0 );

    /* POR AHORA solo el administrador (rol 1/4) crea categorías nuevas; el
       productor las EDITA para adaptarlas a su fundo (case 'edit_cat' guarda
       una copia propia cuando la categoría es general). La creación por fundo
       queda implementada abajo: para habilitarla, basta con quitar este
       bloqueo y volver a mostrar el botón «Agregar» en el listado. */
    $sin_permiso_add = ( ( $rol != 1 ) && ( $rol != 4 ) );
    $sin_fundo_add   = ( ( $rol != 1 ) && ( $rol != 4 ) && $mfundos_id_add <= 0 );

    /* Si ya existe una categoría GENERAL con el mismo nombre (y sexo), la nueva
       se guarda como personalización (`id_padre`) y la general deja de aplicarle
       al fundo. Así el productor ajusta la que ya usa en vez de duplicarla. */
    $padre_global_add = bovs_categoria_padre_global(
        $mfundos_id_add,
        $nombre_categoria,
        $sexo,
        $tipo_ganaderia_add
    );

    $analisis_add    = bovs_analizar_rango_categoria(
        $mfundos_id_add,
        $tipo_ganaderia_add,
        $sexo,
        $edadminima,
        $edadmaxima,
        $padre_global_add,
        $nombre_categoria
    );
    $error_rangos_add = bovs_mensaje_errores_categoria( $analisis_add[ 'errores' ] );

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

    } elseif ( $sin_permiso_add ) {
        $respuesta = array( 'resultado'=>'error', 'mensaje'=>'<div class="alert alert-warning alert-dismissable">
                <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                <h4>
                <i class="icon fa fa-warning"></i>
                Alerta!
                </h4>
                Por ahora solo el administrador crea categorías nuevas.
                <br>Puede <b>editar</b> las existentes para adaptarlas a su fundo
                (por ejemplo, con el nombre que usa en su zona).
                </div>' );
    } elseif ( $sin_fundo_add ) {
        $respuesta = array( 'resultado'=>'error', 'mensaje'=>'<div class="alert alert-warning alert-dismissable">
                <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                <h4>
                <i class="icon fa fa-warning"></i>
                Alerta!
                </h4>
                Su usuario no tiene un fundo activo: no puede crear categorías.
                </div>' );
    } elseif ( $error_rangos_add !== '' ) {
        /* Rango duplicado o sexo sin indicar: no se guarda. */
        $respuesta = array( 'resultado'=>'error', 'mensaje'=>$error_rangos_add );

    } elseif ( count( $analisis_add[ 'avisos' ] ) > 0 && $forzar !== 1 ) {
        /* El rango se cruza con otro: se pregunta antes de guardar. */
        $respuesta = bovs_respuesta_advertencia_categoria( $analisis_add[ 'avisos' ] );

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

            /* Si el nombre coincide con una categoría general, queda como
               personalización del fundo (la general deja de aplicarle). */
            $reg_categoria->id_padre = $padre_global_add;

            /* Antes quedaba en el DEFAULT 1 (bovino): una categoría creada desde
               un fundo de búfalos se guardaba como bovina y no aparecía. */
            $reg_categoria->mtipoganaderias_id = ( (int) $tipo_ganaderia_add > 0 ) ? (int) $tipo_ganaderia_add : 1;

            $reg_categoria->edadminima = $edadminima;
            $reg_categoria->edadmaxima = $edadmaxima;
            $reg_categoria->mfundos_id = $mfundos_id;

            $reg_categoria->user_create = $usuario_id;
            $reg_categoria->created = $hoy;

            if ( $reg_categoria->save() ) {
                // da el mensaje de guardado...

                $respuesta = array( 'resultado'=>'ok', 'avisos'=>$analisis_add[ 'avisos' ], 'mensaje'=>'<div class="alert alert-success alert-dismissable">
                    <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                    <h4>
                    <i class="icon fa fa-check"></i>
                    Alerta!
                    </h4>
                    Los datos han sido registrados exitosamente !.
                    </div>' . bovs_mensaje_avisos_categoria( $analisis_add[ 'avisos' ] ) );

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
       alta; p. ej. OVEJAS 0-5000) se imponía incluso en una cría recién nacida y la
       Categoría Actual salía OVEJA. */
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
    @$mfundos_id = $_SESSION[ 'mfundos_id' ];

    /* Categorías del fundo activo (ver nota en get_categoria_ingreso). */
    if ( bovs_catalogo_fundo_id() > 0 ) {
        $mfundos_id = bovs_catalogo_fundo_id();
    }

    $mfundos_id = intval( $mfundos_id );
    $categoria_ingreso = intval( isset( $_POST[ 'categoria_ingreso' ] ) ? $_POST[ 'categoria_ingreso' ] : 0 );

    $resp = '<option value="">--</option>';

    /* Sin categoría de ingreso no hay futuro que calcular: la cadena se arma a
       partir de ella. */
    if ( $categoria_ingreso > 0 ) {

        $fundo_cat = $mfundos_id > 0 ? $mfundos_id : 0;

        /* REGLA: la categoría futura empieza donde TERMINA la de ingreso.
           Se exige mismo sexo y misma ganadería (no se ofrece una categoría
           CAPRINA en un rebaño ovino), del fundo activo o globales, distinta
           de la propia y con edadminima >= la edadmaxima de la ingreso.

           Antes se comparaba edadminima contra edadminima, así que:
             · BORREGA (18-24) ofrecía una categoría CAPRINA (24-180) en
               rebaños ovinos
             · OVEJA (24-180) se ofrecía a sí misma como futura
             · nunca se miraba la edad máxima de la categoría actual */
        $data = Mcategoria::find_by_sql( "
            SELECT c.id, c.nombre
              FROM mcategorias c
              JOIN mcategorias i ON i.id = " . $categoria_ingreso . "
             WHERE c.status = 1
               AND i.status = 1
               AND c.sexo = i.sexo
               AND c.mtipoganaderias_id = i.mtipoganaderias_id
               AND c.mfundos_id IN (0, " . $fundo_cat . ")
               AND c.id <> i.id
               AND c.edadminima >= COALESCE(NULLIF(i.edadmaxima, 0), i.edadminima + 1)
               AND c.id NOT IN (SELECT x.id_padre FROM mcategorias x WHERE x.mfundos_id = " . $fundo_cat . ")
             ORDER BY c.edadminima ASC, c.nombre ASC;" );

        if ( $data != null && count( $data ) > 0 ) {
            foreach ( $data as $rs ) {
                $resp .= '<option value="' . $rs->id . '">' . $rs->nombre . '</option>';
            }
        } else {
            /* La categoría elegida es la última de la cadena (p. ej. OVEJA):
               no hay a qué pasar, y eso es correcto. */
            $resp .= '<option value="">Es la última categoría del animal</option>';
        }
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