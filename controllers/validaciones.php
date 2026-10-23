<?php
session_start();
$usuario_id = $_SESSION['idusuariox'];

@$action = ($_POST['action']);
@$telefono = ($_POST['telefono']);
@$correo = ($_POST['correo']);
@$nombre = ($_POST['nombre']);

if($usuario_id != ""){

	if($action == "validar1"){ 

		$html = "";


			if (!preg_match('`^[\d]{3}[-]([\d]{3}[-])[\d]{4}$`',$telefono)){
				$html .='	<li id="conf_tel" class="invalid">    Utilice formato telefónico "XXX-XXX-XXXX"</li>';
			}else{
				$html .='	<li id="conf_tel" class="valid">    Correcto</li>';
			}


		echo $html;

	}

	if($action == "validar2"){

		$html = "";


			if (!preg_match('`^[\d]{3}[-]([\d]{3}[-])[\d]{4}$`',$telefono)){
				$html .='	<li id="conf_tel2" class="invalid">    Utilice formato telefónico "XXX-XXX-XXXX"</li>';
			}else{
				$html .='	<li id="conf_tel2" class="valid">    Correcto</li>';
			}


		echo $html;

	}

	if($action == "validar22"){

		$html = "";


			if (!preg_match('`^[\d]{3}[-]([\d]{3}[-])[\d]{4}$`',$telefono)){
				$html .='	<li id="conf_tel3" class="invalid">    Utilice formato telefónico "XXX-XXX-XXXX"</li>';
			}else{
				$html .='	<li id="conf_tel3" class="valid">    Correcto</li>';
			}


		echo $html;

	}

	if($action == "validar3"){

		$html = "";


			if(!preg_match('`^[_a-zA-Z0-9-]+(.[_a-zA-Z0-9-]+)*@([_a-zA-Z0-9-]+.)*[a-zA-Z0-9-]{2,200}.[a-zA-Z]{2,6}$`', $correo)){
		
			//if (!filter_var($correo, FILTER_VALIDATE_EMAIL)){
				$html .='	<li id="conf_cor" class="invalid">    Utilice el formato de correo "XXXX@XXXX.XX"</li>';
			}else{
				$html .='	<li id="conf_cor" class="valid">    Correcto</li>';
			}


		echo $html;

	}

	if($action == "validar4"){

		$html = "";


			//if(!preg_match('`[A-ZÑ ]{2,25})+([,]{1})+([A-ZÑ ]{2,25})+([,]{1})+([A-ZÑ ]{2,25}`', $nombre)){
			if(str_word_count($nombre) < 2 OR str_word_count($nombre) >5 ){
			//if (!filter_var($correo, FILTER_VALIDATE_EMAIL)){
				$html .='	<li id="conf_nom" class="invalid">    Utilice el formato NOMBRE(S) y APELLIDO(S)</li>';
			}else{
				$html .='	<li id="conf_nom" class="valid">    Correcto</li>';
			}


		echo $html;

	}	

	if($action == "validar5"){

		$html = "";


			if(!preg_match('`^[_a-zA-Z0-9-]+(.[_a-zA-Z0-9-]+)*@([_a-zA-Z0-9-]+.)*[a-zA-Z0-9-]{2,200}.[a-zA-Z]{2,6}$`', $correo)){
		
			//if (!filter_var($correo, FILTER_VALIDATE_EMAIL)){
				$html .='	<li id="conf_cor2" class="invalid">    Utilice el formato de correo "XXXX@XXXX.XX"</li>';
			}else{
				$html .='	<li id="conf_cor2" class="valid">    Correcto</li>';
			}


		echo $html;

	} 

	if($action == "validar55"){

		$html = "";


			if(!preg_match('`^[_a-zA-Z0-9-]+(.[_a-zA-Z0-9-]+)*@([_a-zA-Z0-9-]+.)*[a-zA-Z0-9-]{2,200}.[a-zA-Z]{2,6}$`', $correo)){
		
			//if (!filter_var($correo, FILTER_VALIDATE_EMAIL)){
				$html .='	<li id="conf_cor3" class="invalid">    Utilice el formato de correo "XXXX@XXXX.XX"</li>';
			}else{
				$html .='	<li id="conf_cor3" class="valid">    Correcto</li>';
			}


		echo $html;

	} 


}//end if principal
?>
