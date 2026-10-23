<?php
require_once '../models/Mestadofisiologico.php';
date_default_timezone_set('America/Caracas');

@$action = ($_POST["action"]);
@$id = ($_POST["id_estadofisiologico"]);
@$id_estadofisiologico = ($_POST["id_estadofisiologico"]);
@$record = ($_POST["record"]);
/***********************************/
@$nombre_estadofisiologico = strtoupper($_POST["nombre_estadofisiologico"]);
/**********************************/

switch ($action){
  case 'add':

      if($nombre_estadofisiologico ==""){

        $respuesta = array('resultado'=>'error','mensaje'=>'<div class="alert alert-warning alert-dismissable">
            <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
            <h4>
            <i class="icon fa fa-warning"></i>
            Alerta!
            </h4>
            Ingrese el nombre del Tipo.
            </div>');

      }else{  //(A) si se escribe un nombre se consulta si ya existe...

        $consulta = Mestadofisiologico::find('all',array('conditions' => array('nombre=?',$nombre_estadofisiologico)));

          if($consulta==null){  //(B) si no existe lo guarda...

            session_start();
            $usuario_id = $_SESSION['idusuariox'];
            $hoy = date('d-m-Y');

            $segme = new Mestadofisiologico();
            $segme->nombre = $nombre_estadofisiologico;
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
                  <div class="alert alert-danger a lert-dismissable">
                  <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                  <h4>
                  <i class="icon fa fa-ban"></i>
                  Alerta!
                  </h4>

                  Registro inactivo . ¿Desea incluir ['.$nombre_estadofisiologico.'] ?

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
                  El Tipo ['.$nombre_estadofisiologico.'] ya se encuentra registrado.
                  </div>');

           }
                 // echo json_encode($respuesta);
        }


     }
             echo json_encode($respuesta);

  break;
#*******************************************************************************
  case 'edit':

      if($nombre_estadofisiologico ==""){

        $respuesta = array('resultado'=>'error','mensaje'=>'<div class="alert alert-warning alert-dismissable">
            <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
            <h4>
            <i class="icon fa fa-warning"></i>
            Alerta!
            </h4>
            Ingrese el nombre del Tipo.
            </div>');

      }else{

    $consulta = Mestadofisiologico::find('all',array('conditions' => array('id=?',$id_estadofisiologico)));

      if($consulta!=null){

            session_start();
            $usuario_id = $_SESSION['idusuariox'];
            $hoy = date('d-m-Y');



            $estadofisiologico= Mestadofisiologico::find($id);
            $estadofisiologico->nombre = $nombre_estadofisiologico;
            $estadofisiologico->user_modify = $usuario_id;
            $estadofisiologico->updated = $hoy;


            if($estadofisiologico->save()){

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

                  Registro inactivo . ¿Desea incluir ['.$nombre_estadofisiologico.'] ?

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
                  El Tipo Fundo ['.$nombre_estadofisiologico.'] ya se encuentra registrado.
                  </div>');

           }
                 // echo json_encode($respuesta);
        }


     }
             echo json_encode($respuesta);

  break;
#*******************************************************************************

case 'delete':

  if($record !=null){

    session_start();
    $usuario_id = $_SESSION['idusuariox'];
    $rol = $_SESSION['rolx'];
    $hoy = date("d-m-Y");

    if($usuario_id !="" && (($rol ==1)||($rol ==4))){

              $estadofisiologico= Mestadofisiologico::find($record);
              $estadofisiologico->user_modify = $usuario_id;
              $estadofisiologico->updated = $hoy;
              $estadofisiologico->status = 0;

              if($estadofisiologico->save()){

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

    if($record !=null){

      @$data = Mestadofisiologico::find('all',array('conditions' => array('id=?',$record)));

      if($data !=null){

        foreach($data as $rs){

          $resp = array(
                  "id"=>$rs->id,
                  "nombre"=>$rs->nombre
                 );
        }

        echo json_encode($resp);
       }

    }
    break;

#******************************************************************************

case 'get_estado_fisiologico':

  @$data = Mestadofisiologico::find('all',array('conditions' => array('status=?',1),'order' => 'id asc'));

if($data !=null){
         $resp = '<option value="">Seleccione el estado fisiológico de la hembra</option>';
      foreach($data as $rs){
        $resp .= '<option value="'.$rs->id.'">'.$rs->nombre.'</option>';
        $resp .= '<hidden>';
      }

 }else{
         $resp = '<option value="">No hay estado fisiológico para Agregar</option>';
 }

   echo $resp;
break;

}//end switch
?>
