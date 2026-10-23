<?php
require_once '../models/Mpaise.php';
require_once '../models/Mestado.php';
require_once '../models/Mmunicipio.php';

date_default_timezone_set('America/Caracas');

@$action = ($_POST["action"]);
@$id = ($_POST["id"]);
@$record = ($_POST["record"]);
/***********************************/
@$nombre = ($_POST["nombre"]);
@$distribuidora = ($_POST["distribuidora"]);
@$departamento = ($_POST["departamento"]);
@$municipio = ($_POST["municipio"]);
@$cpoblado = ($_POST["cpoblado"]);

/**********************************/

switch ($action){

    case 'get_paises':

         $data = Mpaise::find_by_sql("select id,paisnombre as nombres from Mpaises order by paisnombre asc;");

      if($data !=null){
              //  $resp = '<option value="0" disabled selected>--</option>';
               $resp = '<option value="">--</option>';

            foreach($data as $rs){
              $resp .= '<option value="'.$rs->id.'">'.$rs->nombres.'</option>';
            $resp .= '<hidden>';
            }

       }

         echo $resp;
    break;
#******************************************************************************      
case 'get_estados':

  $pais = ($_POST["filtrar_x_pais"]);

  if ($pais!=""){
    $data = Mestado::find_by_sql("select id as id_m_estados,estadonombre as nombres from Mestados WHERE ubicacionpaisid='$pais' order by estadonombre asc;");

  }else{
    $data = Mestado::find_by_sql("select id as id_m_estados,estadonombre as nombres from Mestados order by estadonombre asc;");
  }


if($data !=null){
         $resp = '<option value="0" disabled selected>Seleccione un Estado</option>';
      foreach($data as $rs){
        $resp .= '<option value="'.$rs->id_m_estados.'">'.$rs->nombres.'</option>';
      $resp .= '<hidden>';
      }

 }

   echo $resp;
break;
#******************************************************************************      
case 'get_municipios':

  $estado = ($_POST["filtrar_x_estado"]);


  if ($estado!=""){
    $data = Mmunicipio::find_by_sql("select id,nombres,id_m_municipios from mmunicipios WHERE id_m_estados='$estado' order by nombres asc;");

  }else{
    // $data = Mmunicipio::find('all',array('conditions' => array('id!=?',0),'order' => 'id desc'));
    $data = Mmunicipio::find_by_sql("select id,nombres,id_m_municipios from mmunicipios  order by nombres asc;");

  }

if($data !=null){
         $resp = '<option value="0" disabled selected>Seleccione un Municipio</option>';
      foreach($data as $rs){
        $resp .= '<option value="'.$rs->id.'">'.$rs->nombres.'</option>';
      $resp .= '<hidden>';
      }

 }

   echo $resp;
break;
#******************************************************************************    

case 'get_categoria_ingreso':

  $sexo_categoria = ($_POST["filtrar_x_sexo"]);
  if ($sexo_categoria!=""){
    $data = Mcategoria::find_by_sql("select id,nombre,status from mcategorias WHERE status=1 and sexo=$sexo_categoria order by id desc;");


  }else{
    $data = Mcategoria::find('all',array('conditions' => array('status=?',1),'order' => 'id desc'));

  }


if($data !=null){
         $resp = '<option value="">Indique la Categoria</option>';
      foreach($data as $rs){
        $resp .= '<option value="'.$rs->id.'">'.$rs->nombre.'</option>';
        $resp .= '<hidden>';
      }

 }else{
         $resp = '<option value="">No hay Categoria para Agregar</option>';
 }

   echo $resp;
break;



}//end switch
?>


