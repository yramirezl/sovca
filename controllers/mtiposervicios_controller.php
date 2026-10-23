<?php
require_once '../models/Mtiposervicio.php';
date_default_timezone_set('America/Caracas');

@$action = ($_POST["action"]);
@$id = ($_POST["id_tservicio"]);
@$id_tservicio = ($_POST["id_tservicio"]);
@$record = ($_POST["record"]);
/***********************************/
@$nombre_tiposervicio = ($_POST["nombre_tiposervicio"]);
/**********************************/

switch ($action){
  case 'add':

      if($nombre_tiposervicio ==""){

        $respuesta = array('resultado'=>'error','mensaje'=>'<div class="alert alert-warning alert-dismissable">
            <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
            <h4>
            <i class="icon fa fa-warning"></i>
            Alerta!
            </h4>
            Ingrese el nombre del Tipo.
            </div>');

      }else{  //(A) si se escribe un nombre se consulta si ya existe...

        $consulta = Mtiposervicio::find('all',array('conditions' => array('nombre=?',$nombre_tiposervicio)));

          if($consulta==null){  //(B) si no existe lo guarda...

            session_start();
            $usuario_id = $_SESSION['idusuariox'];
            $hoy = date('d-m-Y');

            $segme = new Mtiposervicio();
            $segme->nombre = $nombre_tiposervicio;
            $segme->user_create = $usuario_id;
            $segme->created = $hoy;


            if($segme->save()){ // da el mensaje de guardado...

                $respuesta = array('resultado'=>'ok','mensaje'=>'<div class="alert alert-success alert-dismissable">
                    <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                    <h4>
                    <i class="icon fa fa-check"></i>
                    Alerta!
                    </h4>
                    Los datos han sido registrados exitosamente !.
                    </div>');

            }else{


              $respuesta = array('resultado'=>'error','mensaje'=>'<div class="alert alert-danger alert-dismissable">
         					<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
         					<h4>
         					<i class="icon fa fa-ban"></i>
         					Alerta!
         					</h4>
         					Error al registrar los datos.
         					</div>');
            }

            //echo json_encode($respuesta);

        }else{ //(B) si exite el nombre verifica el status del registro...

            foreach ($consulta as $st) {

                 $estado=$st->status;
                 $ide=$st->id;

              }

            if($estado!=1){ //se activa mensaje modal para llevar el status a "1" ... (o no).

                    //echo 'MODAL PARA ACTIVAR REGISTRO GUARDADO'; exit();

              $respuesta = array('resultado'=>'alert','mensaje'=>'<input type="hidden" id="id" value="'.$ide.'">
                  <div class="alert alert-danger alert-dismissable">
                  <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                  <h4>
                  <i class="icon fa fa-ban"></i>
                  Alerta!
                  </h4>

                  Registro inactivo . ¿Desea incluir ['.$nombre_tiposervicio.'] ?

                      <div class="modal-footer" >
                        <button type="button" id="no" class="btn btn-outline pull-left" data-dismiss="alert" onClick="no()">No</button>
                        <button type="button" id="silo" class="btn btn-outline pull-left" onClick="si()" >Si</button>
                      </div>
                  </div>');

            }else{


              $respuesta = array('resultado'=>'alert','mensaje'=>'<div class="alert alert-danger alert-dismissable">
                  <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                  <h4>
                  <i class="icon fa fa-ban"></i>
                  Alerta!
                  </h4>
                  El Tipo ['.$nombre_tiposervicio.'] ya se encuentra registrado.
                  </div>');

           }
                 // echo json_encode($respuesta);
        }


     }
             echo json_encode($respuesta);

  break;
#*******************************************************************************
  case 'edit':

      if($nombre_tiposervicio ==""){

        $respuesta = array('resultado'=>'error','mensaje'=>'<div class="alert alert-warning alert-dismissable">
            <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
            <h4>
            <i class="icon fa fa-warning"></i>
            Alerta!
            </h4>
            Ingrese el nombre del Tipo.
            </div>');

      }else{

    $consulta = Mtiposervicio::find('all',array('conditions' => array('id=?',$id_tservicio)));

      if($consulta!=null){

            session_start();
            $usuario_id = $_SESSION['idusuariox'];
            $hoy = date('d-m-Y');



            $tiposervicio= Mtiposervicio::find($id);
            $tiposervicio->nombre = $nombre_tiposervicio;
            $tiposervicio->user_modify = $usuario_id;
            $tiposervicio->updated = $hoy;


            if($tiposervicio->save()){

                $respuesta = array('resultado'=>'ok','mensaje'=>'<div class="alert alert-success alert-dismissable">
                    <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                    <h4>
                    <i class="icon fa fa-check"></i>
                    Alerta!
                    </h4>
                    Los datos han sido actualizados exitosamente !.
                    </div>');

            }else{


              $respuesta = array('resultado'=>'error','mensaje'=>'<div class="alert alert-danger alert-dismissable">
                  <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                  <h4>
                  <i class="icon fa fa-ban"></i>
                  Alerta!
                  </h4>
                  Error al actualizar los datos.
                  </div>');


            }

        }else{ //(B) si exite el nombre verifica el status del registro...

            foreach ($consulta as $st) {

                 $estado=$st->status;
                 $ide=$st->id;

              }

            if($estado!=1){ //se activa mensaje modal para llevar el status a "1" ... (o no).

                    //echo 'MODAL PARA ACTIVAR REGISTRO GUARDADO'; exit();

              $respuesta = array('resultado'=>'alert','mensaje'=>'<input type="hidden" id="id" value="'.$ide.'">
                  <div class="alert alert-danger alert-dismissable">
                  <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                  <h4>
                  <i class="icon fa fa-ban"></i>
                  Alerta!
                  </h4>

                  Registro inactivo . ¿Desea incluir ['.$nombre_tiposervicio.'] ?

                      <div class="modal-footer" >
                        <button type="button" id="no" class="btn btn-outline pull-left" data-dismiss="alert" onClick="no()">No</button>
                        <button type="button" id="silo" class="btn btn-outline pull-left" onClick="si()" >Si</button>
                      </div>
                  </div>');

            }else{


              $respuesta = array('resultado'=>'error','mensaje'=>'<div class="alert alert-danger alert-dismissable">
                  <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                  <h4>
                  <i class="icon fa fa-ban"></i>
                  Alerta!
                  </h4>
                  El Tipo Fundo ['.$nombre_tiposervicio.'] ya se encuentra registrado.
                  </div>');

           }
                 // echo json_encode($respuesta);
        }


     }
             echo json_encode($respuesta);

  break;
#*******************************************************************************

case 'delete':

  if($id_tservicio !=null){

    session_start();
    $usuario_id = $_SESSION['idusuariox'];
    $rol = $_SESSION['rolx'];
    $hoy = date("d-m-Y");

    if($usuario_id !="" && (($rol ==1)||($rol ==4))){

              $tiposervicio= Mtiposervicio::find($id_tservicio);
              $tiposervicio->user_modify = $usuario_id;
              $tiposervicio->updated = $hoy;
              $tiposervicio->status = 0;

              if($tiposervicio->save()){

                echo'<div class="alert alert-success alert-dismissable">
                    <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                    <h4>
                    <i class="fa fa-check"></i>
                    Alerta!
                    </h4>
                    Registro Eliminado exitosamente...
                  </div>';
  
            }else{
              echo	'<div class="alert alert-danger alert-dismissable">
                  <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                  <h4>
                  <i class="icon fa fa-ban"></i>
                  Alerta!
                  </h4>
                  Disculpe! Ha ocurrido un error al eliminar el registro..
                  </div>';
  
            }
  
        }else{
  
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

    if($id_tservicio !=null){

      @$data = Mtiposervicio::find('all',array('conditions' => array('id=?',$id_tservicio)));

      if($data !=null){

        foreach($data as $rs){

          $resp = array(
                  "id"=>$rs->id,
                  "nombre_tiposervicio"=>$rs->nombre
                 );
        }

        echo json_encode($resp);
       }

    }
    break;

    case 'get_servicio_animal':

      @$data = Mtiposervicio::find('all',array('conditions' => array('status=?',1),'order' => 'nombre asc'));

    if($data !=null){
             $resp = '<option value="">Indique el Servicio</option>';
          foreach($data as $rs){
            $resp .= '<option value="'.$rs->id.'">'.$rs->nombre.'</option>';
            $resp .= '<hidden>';
          }

     }else{
             $resp = '<option value="">No hay Servicio para Agregar</option>';
     }

       echo $resp;
  break;

#******************************************************************************


}//end switch
?>
