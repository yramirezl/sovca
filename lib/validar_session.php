<?php
function ValidaSession($ruta){
	session_start();
	date_default_timezone_set('America/Caracas');
	$session = isset($_SESSION['idusuariox']) ? $_SESSION['idusuariox'] : null;

	if (!$session) {
		header('Location: ' . $ruta);
		exit();
	}

	$rolActual = (int)(isset($_SESSION['rolx']) ? $_SESSION['rolx'] : 0);
	$fechaCaduca = isset($_SESSION['fecha_caduca_suscripcion']) ? $_SESSION['fecha_caduca_suscripcion'] : '';
	if ($fechaCaduca !== '' && $fechaCaduca <= date('Y-m-d')) {
		session_unset();
		session_destroy();
		$separador = strpos($ruta, '?') === false ? '?' : '&';
		header('Location: ' . $ruta . $separador . 'stat=subscription_expired');
		exit();
	}
	if (in_array($rolActual, array(2, 3), true) && empty($_SESSION['suscripcion_verificada'])) {
		session_unset();
		session_destroy();
		$separador = strpos($ruta, '?') === false ? '?' : '&';
		header('Location: ' . $ruta . $separador . 'stat=subscription_refresh');
		exit();
	}

	//----------------------------------------------------------------
	// MODO SOPORTE: cierre automático al cumplirse el tiempo límite.
	// Un administrador (rol 1/4) que entró temporalmente a un fundo
	// debe salir solo; aquí se garantiza aunque el navegador haya
	// estado cerrado y el temporizador del cliente no se ejecute.
	//----------------------------------------------------------------
	if (!empty($_SESSION['supervisor_mode'])) {
		$expiraSoporte = (int)(isset($_SESSION['supervisor_expira']) ? $_SESSION['supervisor_expira'] : 0);

		if ($expiraSoporte > 0 && time() >= $expiraSoporte) {
			// El cierre escribe en la bitácora (soporte_fundos): se necesita
			// ActiveRecord disponible antes de llamar al helper. config/conexion.php
			// usa rutas relativas, así que se incluye desde su propio directorio.
			if (!class_exists('ActiveRecord\ConnectionManager')) {
				$cwdSoporte = getcwd();
				@chdir(__DIR__ . '/../config');
				@include_once(__DIR__ . '/../config/conexion.php');
				@chdir($cwdSoporte);
			}
			@include_once(__DIR__ . '/multifundo_helper.php');

			if (function_exists('mf_desactivar_supervisor')) {
				mf_desactivar_supervisor('expirado');
			} else {
				// Respaldo sin helper: limpiar el contexto de soporte
				foreach (array('supervisor_mode', 'supervisor_fundo', 'supervisor_inicio',
							   'supervisor_expira', 'supervisor_motivo', 'supervisor_registro',
							   'supervisor_fundo_original') as $keySoporte) {
					unset($_SESSION[$keySoporte]);
				}
				$_SESSION['mfundos_id']         = 0;
				$_SESSION['fundo_nombre']       = '';
				$_SESSION['tipo_ganaderia']     = 0;
				$_SESSION['mplanes_id']         = 0;
				$_SESSION['maximo_registros']   = 0;
				$_SESSION['acceso_reg_animales'] = 0;
			}

			$_SESSION['soporte_aviso'] = 'expirado';

			$dirBase = dirname($ruta);
			if ($dirBase === '.' || $dirBase === '/' || $dirBase === '\\') {
				$dirBase = '';
			} else {
				$dirBase .= '/';
			}

			header('Location: ' . $dirBase . 'inicio/?stat=soporte_finalizado');
			exit();
		}
	}

	return true;
}


function VerificarAdmin($rol){//
	if(($rol==2)||($rol==3)){
		header("Location:../inicio/denegado");
		die();
	}
}

function VerificarAdminNavegador($rol){
	if(($rol==2)||($rol==3)){
		header("Location:../views/inicio/denegado");
		die();
	}
}



function VerificarAdminDigitalizacion($rol){
	if(($rol==2)||($rol==3)){
		header("Location:../views/inicio/denegado");
		die();
	}
}

///// Funciones agregadas el 20-02-2023
function VerificarAcceso($acceso){
	if(($acceso!=true)){
		header("Location:../inicio/denegado");
		die();
	}
}

?>
