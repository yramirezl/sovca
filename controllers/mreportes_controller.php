<?php
date_default_timezone_set('America/Caracas');

require_once '../models/Manimale.php';
require_once '../models/Mregnacimiento.php';
require_once '../models/Mregservicio.php';
require_once '../models/Mprolechera.php';
require_once '../models/Vanimalesactivo.php';


@$action = ($_POST["action"]);
// @$id = ($_POST["id_raza"]);

switch ($action){

  case 'search_datos_animales':
    session_start();
    $usuario_id = $_SESSION['idusuariox'];
    $mfundos_id = $_SESSION['mfundos_id'];
    $t_ganaderia = $_SESSION[ 'tipo_ganaderia' ];

    $hoy = date("d-m-Y");

    @$data = Manimale::find_by_sql(" SELECT  max(id) as id_animales from Manimales WHERE  status=1 AND mfundos_id=".$mfundos_id." and mtipoganaderias_id=".$t_ganaderia." limit 1;");  

    if($data !=null){
      foreach($data as $rs){
          @$total_animales = Vanimalesactivo::find_by_sql("SELECT sum(total) as cantidad from Vanimalesactivos WHERE mfundos_id=".$mfundos_id." AND mtipoganaderias_id=".$t_ganaderia." ");  
          @$total_nacimientos = Mregnacimiento::find_by_sql("SELECT count(codigo_ternero) as cantidad from Mregnacimientos WHERE  mfundos_id=".$mfundos_id." AND  status=1  and fichado = false AND  estatus_nacido = 1 AND mestatus_id = 1 AND f_parto IS NOT NULL;");  
          @$total_gestantes = Mregservicio::find_by_sql("SELECT count(id) as cantidad from Mregservicios WHERE  medofisiologicos_id=1  AND mfundos_id=".$mfundos_id." and parto=0 and status=1;");  
          @$total_prodleche = Mprolechera::find_by_sql("SELECT count(distinct codigo_rgb_id_animal_hembra) as cantidad  from mprolecheras WHERE  mfundos_id=".$mfundos_id." and status=1 ;");  
          @$total_reg_prodcarne = Manimale::find_by_sql("SELECT count(codigo_rgb_id_animal) as cantidad from Manimales WHERE mfundos_id=".$mfundos_id." AND status=1 and aptitud=1  and estatus_animal NOT IN (3, 5) AND mestatus_id = 1;");  
          @$total_reg_leche = Manimale::find_by_sql("SELECT count(codigo_rgb_id_animal) as cantidad from Manimales WHERE mfundos_id=".$mfundos_id." AND status=1 and aptitud=2  and estatus_animal NOT IN (3, 5) AND mestatus_id = 1;");  
          @$total_reg_doblep = Manimale::find_by_sql("SELECT count(codigo_rgb_id_animal) as cantidad from Manimales WHERE mfundos_id=".$mfundos_id." AND status=1 and aptitud=3 and estatus_animal NOT IN (3, 5) AND mestatus_id = 1;");  
          @$total_servidas = Mregservicio::find_by_sql("SELECT count(id) as cantidad from Mregservicios WHERE  mfundos_id=".$mfundos_id." and medofisiologicos_id in (2,3) and parto=0 AND status=1 and f_palpacion IS NULL ;"); // and date(f_probable_palpacion) > date(now())  
          
    
          $nro_nacimientos=0;
          $animales_activos=0;
          $nro_gestantes=0;
          $nro_prodleche=0;
          $nro_reg_prodcarne=0;
          $nro_reg_reg_leche=0;
          $nro_reg_doblep=0;
          $nro_servidas=0;

          if($total_servidas !=null){
            foreach($total_servidas as $servidas){
              if($servidas->cantidad!=null){
                $nro_servidas = $servidas->cantidad;      
              }
            }      
            }

          if($total_reg_doblep !=null){
            foreach($total_reg_doblep as $proddoblep){
              if($proddoblep->cantidad!=null){
                $nro_reg_doblep = $proddoblep->cantidad;      
              }
            }      
            }

          if($total_reg_leche !=null){
            foreach($total_reg_leche as $prodlechera){
              if($prodlechera->cantidad!=null){
                $nro_reg_reg_leche = $prodlechera->cantidad;      
              }
            }      
            }


          if($total_reg_prodcarne !=null){
            foreach($total_reg_prodcarne as $prodcarne){
              if($prodcarne->cantidad!=null){
                $nro_reg_prodcarne = $prodcarne->cantidad;      
              }
            }      
            }


          if($total_prodleche !=null){
            foreach($total_prodleche as $prodlecle){
              if($prodlecle->cantidad!=null){
                $nro_prodleche = $prodlecle->cantidad;      
              }
            }      
            }


          if($total_animales !=null){
            foreach($total_animales as $anim){
              if($anim->cantidad!=null){
                $animales_activos = $anim->cantidad;      
              }
            }      
            }
            if($total_nacimientos !=null){
              foreach($total_nacimientos as $naci){
                if($naci->cantidad!=null){
                  $nro_nacimientos = $naci->cantidad;      
                }
              }      
              }

              if($total_gestantes !=null){
                foreach($total_gestantes as $gest){
                  if($gest->cantidad!=null){
                    $nro_gestantes = $gest->cantidad;      
                  }
                }      
                }

               


          
  @$resp = array(
                    "total_animales"=>$animales_activos,
                    "nro_nacimientos"=>$nro_nacimientos,
                    "nro_gestantes"=>$nro_gestantes,
                    "nro_prodleche"=>$nro_prodleche,
                    "nro_reg_prodcarne"=>$nro_reg_prodcarne,
                    "nro_reg_reg_leche"=>$nro_reg_reg_leche,
                    "nro_reg_doblep"=>$nro_reg_doblep,
                    "nro_servidas"=>$nro_servidas


          );
        
          echo json_encode($resp);
        } 
    
    }else{
          @$resp = array(
            "total_animales"=>100
            // ,
            // "cos_contrato"=>0,
            // "estado"=>"no", 
            // "sub_part_cos"=>floatval(0),
            // "dir_ges_cos"=>floatval(0),
            // "dir_cord_cos"=>floatval(0),
            // "restan"=>floatval(0),
            // "dir_cord_nac_cos"=>floatval(0),
            // "grup_proy"=>floatval(0),
            // "vict_ext_cos"=>floatval(0),
            // "restan"=>floatval(0)
          );
          echo json_encode($resp);
    }   
     

  break;

  case 'search_nacimientos':
    $result = array();
    @$nacimientos = Mregnacimiento::find_by_sql("SELECT count(id) as cantidad,
    case  when sexo=1  then 'HEMBRA' 
          when sexo=2  then 'MACHO' end
    from Mregnacimientos WHERE  mfundos_id=8 AND mestatus_id = 1 and status=1 and sexo in (1,2) GROUP BY sexo;");  

    if($nacimientos !=null){

    foreach($nacimientos as $nac){
      
      $cantidad=$nac->cantidad;
      $sexo=$nac->sexo;

  
  
  
      } 

      // foreach($nacimientos_m as $nac_m){
      
      //   $nac_macho=$nac_m->cantidad;
    
    
    
      //   } 
  
     
      array_push($result,array(   
        "cantidad"=>$cantidad,
        "sexo"=>$sexo		
      ));	
  }else{
    array_push($result,array(   
      "cantidad"=>0,
      "sexo"=>0
    ));	
  }
  
  
    
    echo json_encode($result);

    break;


  

}//end switch
?>
