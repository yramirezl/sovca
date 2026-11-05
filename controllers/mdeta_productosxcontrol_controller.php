<?php
require_once '../models/Mdetacrlsanitario.php';
require_once '../models/Mdetacrlsanitariolote.php';
require_once '../models/Mdetatemp.php';
require_once '../models/Vmproductosxlote.php';
require_once '../models/Mcontrolsanitario.php';
require_once '../lib/transaccion_helper.php';

date_default_timezone_set('America/Caracas');

@$action = ($_POST['action']);

@$id_control_sanit = ($_POST['id_control_sanit']);
@$producto = ($_POST['producto']);
@$cantidad = 0;
@$dosis = ($_POST['dosis']);
@$detalles_indicaciones = ($_POST['detalles_indicaciones']);

function marcar_control_sanitario_actualizado($idControl, $idFundo, $usuarioId)
{
    $control = Mcontrolsanitario::find('first', [
        'conditions' => ['id = ? and mfundos_id = ? and status = 1', $idControl, $idFundo],
    ]);
    if (!$control) return false;

    $control->version = (int) ($control->version ?? 0) + 1;
    $control->updated = date('Y-m-d H:i:s');
    $control->user_modify = $usuarioId;
    return $control->save();
}

/**********************************/

switch ($action) {

    #******************************************************************************
    case 'add_productos':
        if ($producto == '') {

            $respuesta = array('deslizador' => '1', 'resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
           <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
           <h4>
           <i class="icon fa fa-warning"></i>
           Alerta!
           </h4>
           Debe ingresar el Producto.
           </div>');
        } else if ($dosis == '') {

            $respuesta = array('deslizador' => '1', 'resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
           <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
           <h4>
           <i class="icon fa fa-warning"></i>
           Alerta!
           </h4>
           Debe ingresar la Dosis.
           </div>');
        } else {
            //( A )

            if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
            $usuario_id = isset($_SESSION['idusuariox']) ? (int) $_SESSION['idusuariox'] : 0;
            $id_sesion_usuario = isset($_SESSION['instante']) ? $_SESSION['instante'] : null;
            $mfundos_id = isset($_SESSION['mfundos_id']) ? (int) $_SESSION['mfundos_id'] : 0;
            $id_control_sanit = (int) $id_control_sanit;

            $hoy = date('d-m-Y');
            $respuesta = null;

            /* Igual que en la unificación de nacimientos: si PostgreSQL deja
               la transacción abortada (25P02), se revierte y se reintenta una
               vez. La pasada anterior no deja cambios. */
            for ($intento = 1; $intento <= 2; $intento++) {
                $conexion = null;
                $token_transaccion = null;

                try {
                    $control = Mcontrolsanitario::find('first', array(
                        'conditions' => array(
                            'id = ? and mfundos_id = ? and status = 1',
                            $id_control_sanit,
                            $mfundos_id
                        )
                    ));
                    if (!$control) {
                        throw new RuntimeException('El control sanitario no existe o no pertenece al fundo de la sesión.');
                    }

                    $reg_prod_control_sanitario = new Mdetacrlsanitario();

                    $reg_prod_control_sanitario->mcontrolsanitarios_id = $id_control_sanit;

                    $reg_prod_control_sanitario->mproductos_id = $producto;
                    $reg_prod_control_sanitario->dosis = $dosis;
                    $reg_prod_control_sanitario->detalles = $detalles_indicaciones;

                    $reg_prod_control_sanitario->mfundos_id = $mfundos_id;

                    $reg_prod_control_sanitario->user_create = $usuario_id;
                    $reg_prod_control_sanitario->created = $hoy;

                    $conexion = Mcontrolsanitario::connection();
                    $token_transaccion = bovs_tx_iniciar($conexion);

                    if (!$reg_prod_control_sanitario->save()) {
                        throw new RuntimeException('La base de datos rechazó el detalle del producto.');
                    }

                    if (!marcar_control_sanitario_actualizado($id_control_sanit, $mfundos_id, $usuario_id)) {
                        throw new RuntimeException('No se pudo actualizar el control sanitario.');
                    }

                    bovs_tx_confirmar($conexion, $token_transaccion);

                    $respuesta = array('resultado' => 'ok', 'mensaje' => '<div class="alert alert-success alert-dismissable">
                 <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                 <h4>
                 <i class="icon fa fa-check"></i>
                 Excelente!
                 </h4>
                 Los datos han sido registrados exitosamente!.
                 </div>');
                    break;
                } catch (Throwable $e) {
                    if ($conexion && $token_transaccion !== null) {
                        try {
                            bovs_tx_revertir($conexion, $token_transaccion);
                        } catch (Throwable $e_revertir) {
                            error_log('[control sanitario] no se pudo revertir: ' . $e_revertir->getMessage());
                        }
                    }

                    if ($intento === 1 && bovs_tx_error_abortado($e)) {
                        continue;
                    }

                    error_log('[control sanitario] add_productos: ' . $e->getMessage());

                    if ($e instanceof RuntimeException) {
                        $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger">No se pudo guardar el producto. '
                            . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</div>');
                    } else {
                        $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger">No se pudo guardar el producto. Detalle: '
                            . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</div>');
                    }
                    break;
                }
            }
        }
        echo json_encode($respuesta);

        break;
    #******************************************************************************
    case 'add_productos_x_lote':


        $id_producto = ($_POST['producto']);
        $id_cs_x_lote = ($_POST['id_cs_x_lote']);

        if ($producto == '') {

            $respuesta = array('deslizador' => '1', 'resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
           <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
           <h4>
           <i class="icon fa fa-warning"></i>
           Alerta!
           </h4>
           Debe ingresar el Producto.
           </div>');
        } else if ($dosis == '') {

            $respuesta = array('deslizador' => '1', 'resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
           <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
           <h4>
           <i class="icon fa fa-warning"></i>
           Alerta!
           </h4>
           Debe ingresar la Dosis.
           </div>');
        } else {
            //( A )

            if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
            @$usuario_id = $_SESSION['idusuariox'];
            $id_sesion_usuario = $_SESSION['instante'];
            $mfundos_id = $_SESSION['mfundos_id'];

            $hoy = date('d-m-Y');


            $datos = Vmproductosxlote::find_by_sql("SELECT  mproductos_id as id_control 
                        from Vmproductosxlotes 
                        where mcontrolsanitarioxlotes_id=" . $id_cs_x_lote . " and 
                            mproductos_id=" . $id_producto . " ;");

            $count = count($datos);

            if ($count > 0) {


                $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
                 <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                 <h4>
                 <i class="icon fa fa-ban"></i>
                 Alerta!
                 </h4>
                 El Producto ya Fue Cargado para este Control.
                 </div>');
                echo json_encode($respuesta);

                break;
            } else {

                $reg_prod_control_sanitario_lote = new Mdetacrlsanitariolote();

                $reg_prod_control_sanitario_lote->mcontrolsanitarioxlotes_id = $id_cs_x_lote;


                $reg_prod_control_sanitario_lote->mproductos_id = $producto;
                $reg_prod_control_sanitario_lote->dosis = $dosis;
                $reg_prod_control_sanitario_lote->detalles = $detalles_indicaciones;

                $reg_prod_control_sanitario_lote->mfundos_id = $mfundos_id;

                $reg_prod_control_sanitario_lote->user_create = $usuario_id;
                $reg_prod_control_sanitario_lote->created = $hoy;

                if ($reg_prod_control_sanitario_lote->save()) {

                    $respuesta = array('resultado' => 'ok', 'mensaje' => '<div class="alert alert-success alert-dismissable">
             <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
             <h4>
             <i class="icon fa fa-check"></i>
             Excelente!
             </h4>
             Los datos han sido registrados exitosamente!.
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
            }
        }
        echo json_encode($respuesta);

        break;

    #******************************************************************************
    case 'add_productos_temp':
        if ($producto == '') {

            $respuesta = array('deslizador' => '1', 'resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
           <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
           <h4>
           <i class="icon fa fa-warning"></i>
           Alerta!
           </h4>
           Debe ingresar el Producto.
           </div>');
        } else if ($dosis == '') {

            $respuesta = array('deslizador' => '1', 'resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
           <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
           <h4>
           <i class="icon fa fa-warning"></i>
           Alerta!
           </h4>
           Debe ingresar la Dosis.
           </div>');
        } else {


            if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
            @$usuario_id = $_SESSION['idusuariox'];
            $id_sesion_usuario = $_SESSION['instante'];
            $mfundos_id = $_SESSION['mfundos_id'];
            $lote = ($_POST['lote']);


            $hoy = date('d-m-Y');

            $reg_mdetatemps = new Mdetatemp();

            $reg_mdetatemps->mcontrolsanitarioxlotes_id_temp = $usuario_id;

            $reg_mdetatemps->mproductos_id = $producto;
            $reg_mdetatemps->dosis = $dosis;
            $reg_mdetatemps->detalles = $detalles_indicaciones;
            $reg_mdetatemps->mlote_id = $lote;
            $reg_mdetatemps->mfundos_id = $mfundos_id;

            if ($reg_mdetatemps->save()) {

                $respuesta = array('resultado' => 'ok', 'mensaje' => '<div class="alert alert-success alert-dismissable">
             <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
             <h4>
             <i class="icon fa fa-check"></i>
             Excelente!
             </h4>
             Los datos han sido registrados exitosamente!.
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
        }
        echo json_encode($respuesta);

        break;

    #******************************************************************************
    case 'delete_producto':

        @$id_det = ($_POST['id_det']);

        if ($id_det != null) {


            $reg_prod_control_sanitario = Mdetacrlsanitario::find($id_det);
            if (!$reg_prod_control_sanitario) {
                $respuesta = array('resultado' => 'error', 'mensaje' => 'No se encontró el detalle de producto.');
                echo json_encode($respuesta);
                break;
            }

            if (session_status() !== PHP_SESSION_ACTIVE) session_start();
            $conexion = Mcontrolsanitario::connection();
            $conexion->transaction();

            if ($reg_prod_control_sanitario->delete()) {
                $controlActualizado = marcar_control_sanitario_actualizado(
                    (int) $reg_prod_control_sanitario->mcontrolsanitarios_id,
                    (int) $reg_prod_control_sanitario->mfundos_id,
                    (int) ($_SESSION['idusuariox'] ?? 0)
                );

                if ($controlActualizado) {
                    $conexion->commit();

                    $respuesta = array('resultado' => 'ok', 'mensaje' => '<div class="alert alert-success alert-dismissable">
                 <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                 <h4>
                 <i class="icon fa fa-check"></i>
                 Excelente!
                 </h4>
                 El producto ha sido quitado exitosamente!.
                 </div>');
                } else {
                    $conexion->rollBack();
                    $respuesta = array('resultado' => 'error', 'mensaje' => 'No se pudo actualizar el control. No se quitó el producto.');
                }
            } else {
                $conexion->rollBack();

                $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
                     <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                     <h4>
                     <i class="icon fa fa-ban"></i>
                     Alerta!
                     </h4>
                     Error al intentar quitar el Peoducto.
                     </div>');
            }
        } else {

            $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
                  <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                  <h4>
               <i class="icon fa fa-ban"></i>
               Alerta!
               </h4>
               Error al intentar quitar el Peoducto.
               </div>');
        }

        echo json_encode($respuesta);

        break;
    #*******************************************************************************
    #******************************************************************************
    case 'delete_producto_temp':

        @$id_det = ($_POST['id_det']);

        if ($id_det != null) {


            $delete_Mdetatemp = Mdetatemp::find($id_det);

            if ($delete_Mdetatemp->delete()) {

                $respuesta = array('resultado' => 'ok', 'mensaje' => '<div class="alert alert-success alert-dismissable">
                 <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                 <h4>
                 <i class="icon fa fa-check"></i>
                 Excelente!
                 </h4>
                 El producto ha sido quitado exitosamente!.
                 </div>');
            } else {

                $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
                     <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                     <h4>
                     <i class="icon fa fa-ban"></i>
                     Alerta!
                     </h4>
                     Error al intentar quitar el Peoducto.
                     </div>');
            }
        } else {

            $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
                  <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                  <h4>
               <i class="icon fa fa-ban"></i>
               Alerta!
               </h4>
               Error al intentar quitar el Peoducto.
               </div>');
        }

        echo json_encode($respuesta);

        break;

    #******************************************************************************
    case 'delete_producto_lote':

        $producto = ($_POST['id_det']);
        $id_cs_x_lote = ($_POST['id_cs_x_lote']);


        if ($producto != null) {



            @$borrar_det = Mdetacrlsanitariolote::find_by_sql('SELECT id as id_control 
		 from Mdetacrlsanitariolotes 
		 where mcontrolsanitarioxlotes_id=' . $id_cs_x_lote . ' and 
			   mproductos_id=' . $producto . ' ;');


            foreach ($borrar_det as $rs_temp) {

                $id_det = $rs_temp->id_control;
            }

            $delete_Mdetatemp = Mdetacrlsanitariolote::find($id_det);



            if ($delete_Mdetatemp->delete()) {

                $respuesta = array('resultado' => 'ok', 'mensaje' => '<div class="alert alert-success alert-dismissable">
                 <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                 <h4>
                 <i class="icon fa fa-check"></i>
                 Excelente!
                 </h4>
                 El producto ha sido quitado exitosamente!.
                 </div>');
            } else {

                $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
                     <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                     <h4>
                     <i class="icon fa fa-ban"></i>
                     Alerta!
                     </h4>
                     Error al intentar quitar el Peoducto.
                     </div>');
            }
        } else {

            $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
                  <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                  <h4>
               <i class="icon fa fa-ban"></i>
               Alerta!
               </h4>
               Error al intentar quitar el Peoducto.
               </div>');
        }

        echo json_encode($respuesta);

        break;
}
//end switch
