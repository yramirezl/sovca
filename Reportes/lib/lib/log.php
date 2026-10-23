<?php
      
      function log_query($sql,$log_usuario){
		               
                $dir = is_dir('../../log_app/log_saslla2017/fecha/');                
                if($dir){
                date_default_timezone_set('America/Caracas');
                $dia=date("d-m-Y");
                $archivo = fopen('../../log_app/log_saslla2017/fecha/'.$dia.'.log','a');
		fwrite($archivo,"[".date("d/m/Y,  g:i a")."][$log_usuario][$sql]\n");
	        fclose($archivo);
	        log_query_usuario($sql,$log_usuario);
                }
	}
  
  function log_query_usuario($sql,$log_usuario){
		               
                $dir = is_dir('../../log_app/log_saslla2017/usuarios/');                
                if($dir){
                date_default_timezone_set('America/Caracas');
                $arr_usuario=  explode('-', $log_usuario);
                $usuario=$arr_usuario[0];    
                $archivo = fopen('../../log_app/log_saslla2017/usuarios/ID-'.$usuario.'.log','a');                
                fwrite($archivo,"[".date("d/m/Y, g:i a")."][$sql]\n");
                fclose($archivo);
                }
	} 
        
 
 function log_error_login_fallidos($log){
		$dir = is_dir('../../log_app/log_errores_login_saslla/');                
                if($dir){
                date_default_timezone_set('America/Caracas');
                $dia=date("d-m-Y");
                $archivo = fopen('../../log_app/log_errores_login_saslla/'.$dia.'.log','a');
		fwrite($archivo,"[".date("d/m/Y,  g:i a")."][$log]\n");
	        fclose($archivo);
                }
	     return true;   
	} 
  function log_error_claves_fallidos($log){
                $dir = is_dir('../../log_app/log_errores_clave_saslla/');                
                if($dir){
		date_default_timezone_set('America/Caracas');
                $dia=date("d-m-Y");
                $archivo = fopen('../../log_app/log_errores_clave_saslla/'.$dia.'.log','a');
		fwrite($archivo,"[".date("d/m/Y,  g:i a")."][$log]\n");
	        fclose($archivo);
                }
	     return true;   
	}       
          

?>