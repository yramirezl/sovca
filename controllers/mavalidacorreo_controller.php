<?php
include '../lib/sanitizar.php';
include '../lib/funciones.php';
date_default_timezone_set('America/Caracas');
////////////////////////////////////////
require_once '../models/Mvalidacorreo.php';

require_once '../alertas/enviar_codigo.php';


@$action = ($_POST['action']);
@$id = ($_POST['id_alerta']);
@$correo = ($_POST['correo']);
@$reenviar = ($_POST['cod_valida']);


$enviar_correo = new Enviar_Correo();


/***********************************/

switch ($action) {

    case 'genera_codigo':
        

        if ($correo != null) {

            session_start();
            $usuario_id = $_SESSION['idusuariox'];

            if ($reenviar==1) {

                $data_buscar = Mvalidacorreo::find_by_sql("select codigo_validacion as codigo from Mvalidacorreos
                                                where  musuarios_id=" . $usuario_id . " and estatus_codigo=true;");



                if ($data_buscar != Null) {
                    foreach ($data_buscar as $rs_temp) {

                        $codigo =  $rs_temp->codigo;
                    }
                }


                if ($enviar_correo->enviar($correo, $codigo)) {
                // if (!$reenviar) {


                    $resp = array(
                        'enviado' => true

                    );
                } else {
                    $resp = array(
                        'enviado' => false

                    );
                }

               
            }else{
                
            $codigo_generado = random_int(100000, 999999);
            $usr_vc = new Mvalidacorreo();
            $usr_vc->codigo_validacion = $codigo_generado;
            $usr_vc->musuarios_id = $usuario_id;
            $usr_vc->email = $correo;


            if ($usr_vc->save()) {

               if ($enviar_correo->enviar($correo, $codigo_generado)) {


                    $resp = array(
                        'enviado' => true

                    );
                }

                $resp = array(
                    'enviado' => true

                );
            } else {
                $resp = array(
                    'enviado' => false

                );
            }
                
            }



          
        } else {
            $resp = array(
                'enviado' => false

            );
        }

        echo json_encode($resp);

        break;


    #*******************************************************************************

    case 'search_correo':

        $correo = intval($_POST['correo']);


        session_start();
        $usuario_id = $_SESSION['idusuariox'];


        @$data = Musuario::find_by_sql("select email as  correo_usu from Musuarios where email='$correo' and id!=" . $usuario_id . " ;");

        if ($data != null) {

            foreach ($data as $rs) {

                $resp = array(
                    'correo_usu' => $rs->correo_usu

                );
            }
        } else {
            $resp = array(
                'correo_usu' => 0

            );
        }
        echo json_encode($resp);

        break;

    #*******************************************************************************


    case 'validar_codigo':

        $codigo = $_POST['codigo'];
        $email = $_POST['correo'];

        session_start();
        $usuario_id = $_SESSION['idusuariox'];
        $hoy = date("d-m-Y");



        @$data = Mvalidacorreo::find_by_sql("select codigo_validacion as codigo from Mvalidacorreos
                                                where codigo_validacion=" . $codigo . "  and musuarios_id=" . $usuario_id . " and estatus_codigo=true ;");

        if ($data != null) {

            $usr = Musuario::find($usuario_id);
            $usr->email = strtolower($email);
            $usr->updated = $hoy;
            $usr->act_email = 1;
            if ($usr->save()) {
                
                $_SESSION['accion'] = false;


                $resp = array(

                    'validado' => true

                );
            } else {

                $resp = array(
                    'validado' => false

                );
            }
        } else {

            $resp = array(
                'validado' => false

            );
        }
        echo json_encode($resp);

        break;

    #*******************************************************************************


    case 'inactivar_codigo':



        session_start();
        $usuario_id = $_SESSION['idusuariox'];

        $borrar_cod_usu = Mvalidacorreo::find('all', array('conditions' => array('musuarios_id=?', $usuario_id)));

        $accion = false;

        if ($borrar_cod_usu != Null) {
            foreach ($borrar_cod_usu as $rs_temp) {

                $rs_temp->delete();
                $accion = true;
            }
        }



        if ($accion()) {


            $resp = array(
                'anulado' => true

            );
        } else {
            $resp = array(
                'anulado' => false

            );
        }

        echo json_encode($resp);

        break;


        #**


}
//end switch
