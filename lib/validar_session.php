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
	// MODO MANTENIMIENTO: los perfiles de productor (2) y ayudante (3)
	// quedan fuera del sistema; el Administrador (1) y el Super Usuario
	// (4) pueden navegar (y usar el Modo soporte para entrar a los fundos).
	//----------------------------------------------------------------
	require_once __DIR__ . '/mantenimiento_helper.php';
	if (bovs_mantenimiento_activo() && !bovs_mantenimiento_permite_rol($rolActual)) {
		bovs_mantenimiento_bloquear_sesion_web($ruta);
	}

	//----------------------------------------------------------------
	// DURACIÓN DE LA SESIÓN
	// Registra la última actividad del usuario ("última transacción")
	// para poder medir desde el login hasta su última petición.
	//----------------------------------------------------------------
	require_once __DIR__ . '/conexiones_helper.php';
	$rutaLatido = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
	bovs_cx_latido($rutaLatido);

        //----------------------------------------------------------------
        // CIERRE REMOTO DE LA SESIÓN
        // El administrador puede pedir desde «Conexiones» que esta sesión se
        // cierre. El usuario recibe primero un aviso con cuenta atrás (60 s)
        // para que guarde lo que esté haciendo; aquí se cumple el cierre cuando
        // el plazo vence, aunque el navegador esté cerrado o bloqueado.
        //----------------------------------------------------------------
        if (function_exists('bovs_cx_cierre_vencido') && bovs_cx_cierre_vencido()) {
                bovs_cx_cerrar_sesion('admin');
                session_unset();
                session_destroy();
                $separador = strpos($ruta, '?') === false ? '?' : '&';
                header('Location: ' . $ruta . $separador . 'stat=session_closed');
                exit();
        }
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
