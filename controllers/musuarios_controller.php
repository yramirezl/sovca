<?php
/* ============================================================
   BLINDAJE: buffer de salida + supresión de deprecations
   Compatible con PHP 7.4 y 8.3
   ------------------------------------------------------------
   - ob_start() evita que warnings/notices rompan los header()
   - error_reporting filtra solo deprecations y notices, los
	 errores reales (E_ERROR, E_WARNING, E_PARSE) siguen activos
   - session_start() SIN read_and_close para poder escribir
	 $_SESSION (captcha, login_usuario_temp)
   ============================================================ */
if (!ob_get_level()) {
	ob_start();
}

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED & ~E_NOTICE);

if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

date_default_timezone_set('America/Caracas');
include '../lib/sanitizar.php';
include '../lib/imagen_helper.php';
require_once '../models/Mrole.php';
require_once '../models/Mtipousuario.php';
require_once '../models/Mfundosxusuario.php';
require_once '../models/Musuario.php';
require_once '../models/Vusuario.php';
require_once '../models/Mrastreosessione.php';
require_once '../models/Manimalxfundo.php';
require_once '../models/Mplanesxfundo.php';
require_once '../models/Msuscripcion.php';
require_once '../lib/herencia_productor_helper.php';
require_once '../lib/normativa_fundos_helper.php';
require_once '../lib/geoip_helper.php';

/*
 * La IP y la ubicación ya NO se resuelven aquí: se calculan una sola vez en
 * el bloque de login con bovs_conexion_registrar() (lib/geoip_helper.php).
 * Resolverlas al inicio del controlador obligaba a geolocalizar en TODAS las
 * acciones, incluso en las que no registran accesos.
 */

@$action = ($_POST["action"]);
@$id = ($_POST["id"]);
@$record = sanitize($_POST["record"]);
function usuariosMayusculas($valor)
{
	$valor = (string) $valor;
	return function_exists('mb_strtoupper') ? mb_strtoupper($valor, 'UTF-8') : strtoupper($valor);
}

/* ============================================================
   PERMISOS SOBRE LAS CUENTAS
   ------------------------------------------------------------
   El SUPER USUARIO (rol 4) es el perfil de mayor jerarquía:
     · solo otro SUPER USUARIO puede consultarlo, editarlo,
       inactivarlo o reactivarlo;
     · solo un SUPER USUARIO puede asignar ese perfil o mover una
       cuenta al tipo ROOT (mtipousuarios_id = 1).
   Las cuentas ROOT (tipo 1) se protegen igual, aunque su rol no
   sea 4, porque son las que administran el sistema.
   ============================================================ */
function usuarioSesionRol()
{
	return isset($_SESSION['rolx']) ? (int) $_SESSION['rolx'] : 0;
}

function usuarioSesionEsSuperUsuario()
{
	return usuarioSesionRol() === 4;
}

/**
 * Devuelve la cuenta (modelo) o null. Se usa find('first', …) a
 * propósito: find($id) lanza excepción cuando el id no existe.
 */
function usuarioBuscarCuenta($id)
{
	$id = (int) $id;
	if ($id <= 0) {
		return null;
	}
	return Musuario::find('first', array('conditions' => array('id=?', $id)));
}

/**
 * ¿La cuenta es SUPER USUARIO (rol 4) o del tipo ROOT?
 */
function cuentaEsSuperUsuario($id)
{
	$cuenta = usuarioBuscarCuenta($id);
	if (!$cuenta) {
		return false;
	}
	return ((int) $cuenta->mroles_id === 4) || ((int) $cuenta->mtipousuarios_id === 1);
}

/**
 * ¿La sesión actual puede ver/editar/cambiar el estado de esa cuenta?
 * Las cuentas protegidas solo las gestiona otro SUPER USUARIO.
 */
function puedeGestionarCuenta($id)
{
	if (!cuentaEsSuperUsuario($id)) {
		return true;
	}
	return usuarioSesionEsSuperUsuario();
}

function avisoCuentaProtegida()
{
	return '<div class="alert alert-danger alert-dismissable">
				<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
				<input type="hidden" id="tocon" value="0">
				<h4>
				<i class="icon fa fa-lock"></i>
				Acceso restringido
				</h4>
				Esta cuenta pertenece a un <strong>SUPER USUARIO</strong>. Solo otro SUPER USUARIO puede consultarla, editarla o cambiar su estado.
				</div>';
}

function avisoPerfilRestringido()
{
	return '<div class="alert alert-danger alert-dismissable">
				<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
				<input type="hidden" id="tocon" value="0">
				<h4>
				<i class="icon fa fa-lock"></i>
				Acceso restringido
				</h4>
				Solo un usuario con perfil <strong>SUPER USUARIO</strong> puede asignar ese perfil o el tipo de usuario ROOT.
				</div>';
}

/**
 * Si la cuenta editada es la de la propia sesión, la cabecera del sistema
 * debe mostrar los datos nuevos sin tener que volver a iniciar sesión.
 */
function actualizarSesionSiEsPropia($cuenta, $usuarioSesionId)
{
	if (!$cuenta || (int) $cuenta->id !== (int) $usuarioSesionId) {
		return;
	}
	$_SESSION['usuariox'] = $cuenta->username;
	$_SESSION['nombresx'] = $cuenta->name;
	$_SESSION['rolx'] = (int) $cuenta->mroles_id;
	$_SESSION['imagen_perfilx'] = $cuenta->imagen;
}

/**
 * Tipo de usuario a guardar en la edición. Sin valor se conserva el
 * actual; y solo un SUPER USUARIO puede mover una cuenta a ROOT (1).
 */
function tipoUsuarioParaGuardar($tipoNuevo, $tipoActual)
{
	$tipoNuevo = (int) $tipoNuevo;
	$tipoActual = (int) $tipoActual;

	if ($tipoNuevo <= 0) {
		return $tipoActual;
	}
	if ($tipoNuevo === 1 && !usuarioSesionEsSuperUsuario()) {
		return $tipoActual;
	}
	return $tipoNuevo;
}

@$nombre = usuariosMayusculas($_POST["nombre"]);
@$email = strtoupper($_POST["email"]);
@$telefono = ($_POST["telefono"]);

@$usuario = usuariosMayusculas($_POST["usuario"]);
@$rol = ($_POST["rol"]);
@$password1 = ($_POST["password1"]);
@$password2 = ($_POST["password2"]);
@$sourcePath = $_FILES['file']['tmp_name'];  // Almacenar ruta de origen del archivo en una variable
@$targetPath = "../dist/img/fotos_usuarios/" . $_FILES['file']['name']; // Ruta de destino donde el archivo se va a almacenar
@$tipo_archivo = $_FILES['file']['type'];

@$m_tuser_id = ($_POST["t_user"]);
@$m_t_plan_id = ($_POST["t_plan"]);
@$msuscripcion_id = ($_POST["t_suscripcion"]);
@$fecha_activa_suscripcion = ($_POST["fecha_activa_suscripcion"]);

@$fundo = ($_POST["fundo"]);

// --------- MULTI-FUNDO ---------
@$es_multifundo = (int) (isset($_POST["es_multifundo"]) ? $_POST["es_multifundo"] : 0);
@$max_fundos = (int) (isset($_POST["max_fundos"]) ? $_POST["max_fundos"] : 1);
if ($max_fundos < 1)
	$max_fundos = 1;
if ($max_fundos > 100)
	$max_fundos = 100;

// --------- ACCESO AL MODO OFFLINE ---------
// El permiso es del PRODUCTOR (rol 2), dueño del fundo: los usuarios que
// trabajan en su fundo (rol 3) lo heredan y los roles 1/4 no usan la app
// sin conexión. Por eso acá solo se toma el valor del formulario cuando el
// rol guardado es 2; para cualquier otro rol queda APAGADO.
// Ojo: si el formulario no manda el campo (versión vieja en caché) también
// queda apagado — el acceso hay que asignarlo explícitamente.
@$acceso_offline = isset($_POST["acceso_offline"]) ? (int) $_POST["acceso_offline"] : 0;
$acceso_offline = (intval($rol) === 2 && $acceso_offline) ? 1 : 0;

function datosSuscripcionProductor($planId, $suscripcionId, $fechaActiva)
{
	if (intval($planId) <= 0 || intval($suscripcionId) <= 0 || !$fechaActiva) {
		return array('error' => 'Debe indicar el plan, tipo y fecha de activacion de la suscripcion.');
	}

	$fecha = DateTime::createFromFormat('Y-m-d', $fechaActiva);
	if (!$fecha || $fecha->format('Y-m-d') !== $fechaActiva) {
		return array('error' => 'La fecha de activacion de la suscripcion no es valida.');
	}

	$suscripcion = Msuscripcion::find('first', array('conditions' => array('id=? AND status=?', intval($suscripcionId), 1)));
	if (!$suscripcion || intval($suscripcion->cantidad_dias) <= 0) {
		return array('error' => 'El tipo de suscripcion seleccionado no es valido.');
	}

	$fechaCaduca = clone $fecha;
	$fechaCaduca->modify('+' . intval($suscripcion->cantidad_dias) . ' days');
	return array('fecha_activa' => $fecha->format('Y-m-d'), 'fecha_caduca' => $fechaCaduca->format('Y-m-d'));
}

function guardarSuscripcionProductor($usuarioId, $planId, $suscripcionId, $fechaActiva)
{
	$datos = datosSuscripcionProductor($planId, $suscripcionId, $fechaActiva);
	if (isset($datos['error'])) {
		return $datos['error'];
	}

	$registro = Mplanesxfundo::find('first', array('conditions' => array('musuarios_id=?', intval($usuarioId)), 'order' => 'id DESC'));
	if (!$registro) {
		$registro = new Mplanesxfundo();
		$registro->musuarios_id = intval($usuarioId);
	}

	$registro->mplanes_id = intval($planId);
	$registro->msuscripciones_id = intval($suscripcionId);
	$registro->fecha_activa_suscripcion = $datos['fecha_activa'];
	$registro->fecha_caduca_suscripcion = $datos['fecha_caduca'];

	return $registro->save() ? '' : 'No se pudo guardar la suscripcion del productor.';
}

function obtenerVigenciaAplicable($usuario)
{
	$rolUsuario = (int) $usuario->mroles_id;
	$idSuscripcion = (int) $usuario->id;
	if ($rolUsuario === 3 && !empty($usuario->mfundos_id)) {
		$idFundo = (int) $usuario->mfundos_id;
		$propietario = Musuario::find_by_sql("SELECT user_propietario FROM mfundos WHERE id = $idFundo AND status = 1 LIMIT 1");
		if (!$propietario || !isset($propietario[0]->user_propietario))
			return null;
		$idSuscripcion = (int) $propietario[0]->user_propietario;
		$cuentaProductor = Musuario::find('first', array(
			'conditions' => array('id=? AND mroles_id=? AND status=1', $idSuscripcion, 2)
		));
		if (!$cuentaProductor)
			return null;
	} elseif ($rolUsuario !== 2) {
		return null;
	}

	return Mplanesxfundo::find('first', array(
		'conditions' => array('musuarios_id=? AND msuscripciones_id IS NOT NULL', $idSuscripcion),
		'order' => 'id DESC'
	));
}


switch ($action) {

	case 'get_roles_tipo':

		if (session_status() === PHP_SESSION_NONE) {
			session_start();
		}

		// Los roles del tipo elegido. El tipo ROOT (1) y el perfil
		// SUPER USUARIO (4) quedan fuera para cualquier sesión que no
		// sea SUPER USUARIO (antes bastaba con enviar el tipo 1 por POST
		// para que apareciera el perfil de mayor jerarquía).
		$tipo_id = (int) $m_tuser_id;
		$resp = '';
		if ($tipo_id > 0) {
			$sql = "SELECT id, nombre FROM Mroles WHERE status=1 AND m_tuser_id = " . $tipo_id;
			if (!usuarioSesionEsSuperUsuario()) {
				$sql .= " AND m_tuser_id <> 1 AND id <> 4";
			}
			$sql .= " ORDER BY nombre desc";

			$data = Mrole::find_by_sql($sql);

			if (!empty($data)) {
				$resp = '<option value="*">Indique el Rol</option>';

				foreach ($data as $rs) {
					$resp .= '<option value="' . $rs->id . '">' . $rs->nombre . '</option>';
				}
			}
		}
		echo $resp;
		break;

	case 'get_roles':
		// El perfil SUPER USUARIO solo se ofrece a una sesión de SUPER USUARIO.
		$sql_roles = "SELECT id, nombre FROM Mroles WHERE status=1 AND id > 1";
		if (!usuarioSesionEsSuperUsuario()) {
			$sql_roles .= " AND id <> 4";
		}
		$sql_roles .= " ORDER BY id desc";
		$data = Mrole::find_by_sql($sql_roles);

		// OJO: esta acción no puede "caer" en get_roles_id (antes no tenía
		// break y terminaba consultando con un rol_id vacío, lo que dejaba
		// el selector de Rol vacío en el formulario de edición). Por eso
		// arma y devuelve su propia respuesta aquí.
		$resp = '';
		if (!empty($data)) {
			foreach ($data as $rs) {
				$resp .= '<option value="' . $rs->id . '">' . $rs->nombre . '</option>';
			}
			echo $resp;
		}
		break;

	case 'get_roles_id';
		$rol_id = ($_POST["rol_id"]);

		$data = Mrole::find_by_sql("SELECT id, nombre
												FROM Mroles 
										    WHERE status=1 and id =" . $rol_id . " order by id desc;");



		$resp = '';
		if (!empty($data)) {
			foreach ($data as $rs) {
				$resp .= '<option value="' . $rs->id . '">' . $rs->nombre . '</option>';
			}
			echo $resp;
		}
		break;

	case 'get_t_user_id':

		// Antes $resp no se inicializaba: cuando el tipo del usuario no
		// existía (p. ej. 0) la salida quedaba vacía y el selector
		// "Tipo de Usuario" aparecía en blanco al editar.
		$user_id = (int) (isset($_POST["t_user_id"]) ? $_POST["t_user_id"] : 0);
		$resp = '';

		if ($user_id > 0) {
			$data = Mtipousuario::find_by_sql("SELECT id, nombre
											FROM Mtipousuarios 
										    WHERE status=1 and id =" . $user_id . " order by id desc;");

			if (!empty($data)) {

				foreach ($data as $rs) {
					$resp .= '<option value="' . $rs->id . '">' . $rs->nombre . '</option>';
				}
			}
		}
		echo $resp;
		break;

	case 'get_t_user':

		// El tipo ROOT (1) es el de las cuentas de soporte: antes se
		// excluía para TODOS, así que al editar un SUPER USUARIO (tipo
		// ROOT) el selector "Tipo de Usuario" salía vacío y el dato no se
		// podía ver ni corregir. Se incluye solo para un SUPER USUARIO.
		$sql_tipos = "SELECT id, nombre FROM Mtipousuarios WHERE status=1";
		if (!usuarioSesionEsSuperUsuario()) {
			$sql_tipos .= " AND id > 1";
		}
		$sql_tipos .= " ORDER BY id desc";
		$data = Mtipousuario::find_by_sql($sql_tipos);




		$resp = '';
		if (!empty($data)) {
			$resp = '<option value="*">Seleccione el Tipo de Usuario</option>';

			foreach ($data as $rs) {
				$resp .= '<option value="' . $rs->id . '">' . $rs->nombre . '</option>';
			}
			echo $resp;
		}
		break;

	case 'get_suscripciones':
		$data = Msuscripcion::find_by_sql("SELECT id, descripcion, cantidad_dias
			FROM msuscripciones
			WHERE status = 1
			ORDER BY cantidad_dias ASC");
		$resp = '<option value="*">Seleccione el Tipo de Suscripcion</option>';
		foreach ($data as $rs) {
			$resp .= '<option value="' . $rs->id . '">' . $rs->descripcion . ' (' . $rs->cantidad_dias . ' dias)</option>';
		}
		echo $resp;
		break;

	case 'renovar_suscripcion':
		header('Content-Type: application/json; charset=utf-8');
		$usuario_id = (int) (isset($_POST['usuario_id']) ? $_POST['usuario_id'] : 0);
		$suscripcion_nueva_id = (int) (isset($_POST['msuscripciones_id']) ? $_POST['msuscripciones_id'] : 0);
		$programar_renovacion = !empty($_POST['programar_renovacion']);
		$rol_admin = (int) (isset($_SESSION['rolx']) ? $_SESSION['rolx'] : 0);
		if (!in_array($rol_admin, array(1, 4), true)) {
			echo json_encode(array('ok' => false, 'msg' => 'No tienes permiso para renovar suscripciones.'));
			break;
		}

		$cuenta = Musuario::find('first', array(
			'conditions' => array('id=? AND mroles_id=? AND status=1', $usuario_id, 2)
		));
		$vigencia_actual = Mplanesxfundo::find('first', array(
			'conditions' => array('musuarios_id=? AND msuscripciones_id IS NOT NULL', $usuario_id),
			'order' => 'id DESC'
		));
		if (!$cuenta || !$vigencia_actual || !$vigencia_actual->fecha_caduca_suscripcion) {
			echo json_encode(array('ok' => false, 'msg' => 'No se encontró una suscripción vigente para renovar.'));
			break;
		}
		if ($suscripcion_nueva_id <= 0)
			$suscripcion_nueva_id = (int) $vigencia_actual->msuscripciones_id;
		$suscripcion_nueva = Msuscripcion::find('first', array(
			'conditions' => array('id=? AND status=?', $suscripcion_nueva_id, 1)
		));
		if (!$suscripcion_nueva) {
			echo json_encode(array('ok' => false, 'msg' => 'Selecciona un tipo de suscripción activo.'));
			break;
		}

		$fechaCaducaActual = $vigencia_actual->fecha_caduca_suscripcion instanceof DateTime
			? clone $vigencia_actual->fecha_caduca_suscripcion
			: new DateTime((string) $vigencia_actual->fecha_caduca_suscripcion);
		$hoy = new DateTime('today');
		$diasRestantes = (int) $hoy->diff($fechaCaducaActual)->format('%r%a');
		if ($diasRestantes > 30) {
			echo json_encode(array('ok' => false, 'msg' => 'Puedes programar la renovación durante los 30 días previos al vencimiento.'));
			break;
		}
		$programar_renovacion = $programar_renovacion && $diasRestantes > 0;
		$fechaInicioRenovacion = $programar_renovacion
			? $fechaCaducaActual->format('Y-m-d')
			: $hoy->format('Y-m-d');

		$datosRenovacion = datosSuscripcionProductor(
			$vigencia_actual->mplanes_id,
			$suscripcion_nueva_id,
			$fechaInicioRenovacion
		);
		if (isset($datosRenovacion['error'])) {
			echo json_encode(array('ok' => false, 'msg' => $datosRenovacion['error']));
			break;
		}

		$nuevaVigencia = new Mplanesxfundo();
		$nuevaVigencia->musuarios_id = $usuario_id;
		$nuevaVigencia->mfundos_id = (int) $vigencia_actual->mfundos_id;
		$nuevaVigencia->mplanes_id = (int) $vigencia_actual->mplanes_id;
		$nuevaVigencia->msuscripciones_id = $suscripcion_nueva_id;
		$nuevaVigencia->fecha_activa_suscripcion = $datosRenovacion['fecha_activa'];
		$nuevaVigencia->fecha_caduca_suscripcion = $datosRenovacion['fecha_caduca'];
		if (!$nuevaVigencia->save()) {
			echo json_encode(array('ok' => false, 'msg' => 'No se pudo guardar la renovación.'));
			break;
		}

		echo json_encode(array(
			'ok' => true,
			'msg' => $programar_renovacion ? 'Renovación programada correctamente.' : 'Suscripción renovada correctamente.',
			'fecha_activa' => $datosRenovacion['fecha_activa'],
			'fecha_caduca' => $datosRenovacion['fecha_caduca'],
			'programada' => $programar_renovacion
		));
		break;


	case 'search':

		// OJO: el formulario de edición también se abre para usuarios
		// INACTIVOS (status = 0), así que aquí NO se filtra por status.
		// Cuando la consulta pedía "status=1", al editar una cuenta inactiva
		// no devolvía nada y el formulario quedaba completamente en blanco.
		// El listado es el que decide qué cuentas se pueden ver.
		$record = (int) $record;

		// El formulario de edición de una cuenta SUPER USUARIO (o ROOT)
		// queda reservado a otro SUPER USUARIO.
		if (!empty($record) && !puedeGestionarCuenta($record)) {
			echo json_encode(array(
				"id" => 0,
				"error" => "Esta cuenta pertenece a un SUPER USUARIO. Solo otro SUPER USUARIO puede consultarla o editarla."
			));
			break;
		}

		if (!empty($record)) {

			// Se devuelven también los NOMBRES del rol y del tipo de usuario:
			// si la cuenta tiene un valor fuera del catálogo (p. ej. tipo 0)
			// el formulario puede mostrar el dato en vez de un select vacío.
			$data = Musuario::find_by_sql("SELECT u.id, u.mroles_id, u.mplanes_id, u.username, u.email, u.name,
												  u.telefono, u.imagen, u.act_email, u.mtipousuarios_id,
												  u.es_multifundo, u.max_fundos, u.acceso_offline, u.status,
												  (SELECT r.nombre FROM mroles r WHERE r.id = u.mroles_id) AS rol_nombre,
												  (SELECT t.nombre FROM mtipousuarios t WHERE t.id = u.mtipousuarios_id) AS tipo_nombre
											 FROM Musuarios u
											WHERE u.id = " . $record . " ;");

			$resp = '';
			if (!empty($data)) {

				// TODOS los fundos activos de la cuenta. Antes se leía la
				// lista sin ORDER BY y se quedaba con el ÚLTIMO: para una
				// cuenta multifundo el "fundo" que llegaba al formulario era
				// distinto en cada apertura. Ahora manda el primero (por id)
				// y se envía la lista completa.
				$fundo = 0;
				$fundos = array();
				$consulta_fundo = Mfundosxusuario::find_by_sql("SELECT mfundos_id as fundo
                                    FROM Mfundosxusuarios
                                WHERE status=1 and musuarios_id =" . $record . "
                                ORDER BY mfundos_id ASC ;");

				foreach ($consulta_fundo as $rs_fundo) {
					$fundos[] = (int) $rs_fundo->fundo;
				}
				if (!empty($fundos)) {
					$fundo = $fundos[0];
				}

				foreach ($data as $rs) {
					$suscripcion = Mplanesxfundo::find('first', array(
						'conditions' => array('musuarios_id=? AND msuscripciones_id IS NOT NULL', $record),
						'order' => 'id DESC'
					));

					$fecha_activa = '';
					if ($suscripcion && !empty($suscripcion->fecha_activa_suscripcion)) {
						$fecha_activa = ($suscripcion->fecha_activa_suscripcion instanceof DateTime)
							? $suscripcion->fecha_activa_suscripcion->format('Y-m-d')
							: date('Y-m-d', strtotime((string) $suscripcion->fecha_activa_suscripcion));
					}

					$resp = array(
						"id" => (int) $rs->id,
						"rol" => (int) $rs->mroles_id,
						"rol_nombre" => (string) $rs->rol_nombre,
						"tipo_user" => (int) $rs->mtipousuarios_id,
						"tipo_user_nombre" => (string) $rs->tipo_nombre,
						"t_plan" => (int) $rs->mplanes_id,
						"usuario" => $rs->username,
						"nombre" => $rs->name,
						"email" => $rs->email,
						// La columna es char(n): se recorta el relleno.
						"telefono" => trim((string) $rs->telefono),
						"imagen" => $rs->imagen,
						"act_email" => (int) $rs->act_email,
						"fundo" => (int) $fundo,
						"fundos" => $fundos,
						"t_suscripcion" => $suscripcion ? (int) $suscripcion->msuscripciones_id : 0,
						"fecha_activa_suscripcion" => $fecha_activa,
						"es_multifundo" => (int) $rs->es_multifundo,
						"max_fundos" => (int) $rs->max_fundos,
						"acceso_offline" => (int) $rs->acceso_offline,
						"status" => (int) $rs->status
					);
				}
				echo json_encode($resp);
			} else {

				// Cuenta inexistente (id inválido o enlace viejo): se avisa al
				// formulario en vez de responder vacío, que dejaba la pantalla
				// en blanco sin explicación.
				echo json_encode(array(
					"id" => 0,
					"error" => "No se encontró la cuenta solicitada. Es posible que el enlace esté vencido."
				));
			}
		}

		break;

	case 'add':
		$rol = (int) $rol;

		// Solo un SUPER USUARIO puede crear cuentas con su mismo perfil o
		// con el tipo ROOT.
		if (($rol === 4 || (int) $m_tuser_id === 1) && !usuarioSesionEsSuperUsuario()) {
			echo avisoPerfilRestringido();
			break;
		}

		if ($rol !== 2) {
			$es_multifundo = 0;
			$max_fundos = 1;
		} else {
			$es_multifundo = $es_multifundo ? 1 : 0;
			if (!$es_multifundo)
				$max_fundos = 1;
		}
		if ($rol !== 3)
			$fundo = 0;

		// Email único entre usuarios ACTIVOS (comparación sin distinguir mayúsculas)
		@$consulta3 = Musuario::find('all', array('conditions' => array('lower(email)=lower(?) AND status=?', $email, 1)));

		if ($nombre == "") {

			echo '<div class="alert alert-danger alert-dismissable">
					<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
					<input type="hidden" id="tocon" value="0">
					<h4>
					<i class="icon fa fa-ban"></i>
					Alerta!
					</h4>
					Campo nombre y apellido vacío..
					</div>';
		} elseif (count($consulta3) != 0) {

			echo '<div class="alert alert-danger alert-dismissable">
					<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
					<input type="hidden" id="tocon" value="0">
					<h4>
					<i class="icon fa fa-ban"></i>
					Alerta!
					</h4>
					El correo [' . strtolower($email) . '] ya está asociado a otro usuario.
					</div>';
		} elseif ($usuario == "") {

			echo '<div class="alert alert-danger alert-dismissable">
					<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
					<input type="hidden" id="tocon" value="0">
					<h4>
					<i class="icon fa fa-ban"></i>
					Alerta!
					</h4>
					Campo usuario vacío.
					</div>';
		} elseif ($password1 == " ") {

			echo '<div class="alert alert-danger alert-dismissable">
					<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
					<input type="hidden" id="tocon" value="0">
					<h4>
					<i class="icon fa fa-ban"></i>
					Alerta!
					</h4>
					Campo contraseña vacío.
					</div>';
		} elseif ($password2 == " ") {

			echo '<div class="alert alert-danger alert-dismissable">
					<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
					<input type="hidden" id="tocon" value="0">
					<h4>
					<i class="icon fa fa-ban"></i>
					Alerta!
					</h4>
					Campo confirmar contraseña vacío.
					</div>';
		} elseif ($password1 != $password2) {

			echo '<div class="alert alert-danger alert-dismissable">
					<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
					<input type="hidden" id="tocon" value="0">
					<h4>
					<i class="icon fa fa-ban"></i>
					Alerta!
					</h4>
					La nueva contraseña y su confirmación son distintas.
					</div>';
		} elseif ($password1 == $password2) {

			// Username único entre usuarios ACTIVOS (comparación sin distinguir mayúsculas)
			$consulta = Musuario::find('all', array('conditions' => array('upper(username)=upper(?) AND status=?', $usuario, 1)));

			if (count($consulta) == 0) {
				if (($rol == 1) || ($rol == 4)) {

					//$distribuidora=1;

					$str_mayusculas = array("A", "B", "C", "D", "E", "F", "G", "H", "I", "J", "K", "L", "M", "N", "Ñ", "O", "P", "Q", "R", "S", "T", "U", "V", "W", "X", "Y", "Z");
					$str_minusculas = array("a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k", "l", "m", "n", "ñ", "o", "p", "q", "r", "s", "t", "u", "v", "w", "x", "y", "z");
					$str_numeros = array("1", "2", "3", "4", "5", "6", "7", "8", "9", "0");
					$str_especiales = array("@", "#", "$", "%");


					$str_contraseña_aleatoria = "";
					$p1 = array_rand($str_mayusculas, 1);
					$p2 = array_rand($str_minusculas, 3);
					$p3 = array_rand($str_numeros, 3);
					$p4 = array_rand($str_especiales, 1);


					$str_contraseña_aleatoria .= $str_mayusculas[$p1];
					foreach ($p2 as $value2) {
						$str_contraseña_aleatoria .= $str_minusculas[$value2];
					}
					foreach ($p3 as $value3) {
						$str_contraseña_aleatoria .= $str_numeros[$value3];
					}
					$str_contraseña_aleatoria .= $str_especiales[$p4];


					// El SUPER USUARIO puede definir la clave que quiera. Para
					// el resto de las sesiones estas cuentas nacen con la clave
					// fija de soporte.
					if (usuarioSesionEsSuperUsuario() && trim((string) $password1) !== '' && $password1 === $password2) {
						$password = $password1;
					} else {
						$password = 'RUTED123456#'; //setear fijamente la contraseña
					}

					@$salt = '$DYhG93b0qyJfIxfs2guVoUubWwvniR2G0FgaC9mi$/';

					@$contraseña = md5($salt . $password);

					//print_r('crear contraseña aleatoria y enviar por correo');
					//exit();


				} elseif ($rol == 3 || $rol == 2 || $rol == 5 || $rol == 6 || $rol == 7) {



					$validos = 0;
					$no_validos = 0;
					$mayusculas = 0;
					$minusculas = 0;
					$especiales = 0;
					$numeros = 0;
					$caracterespermitidos = array("a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k", "l", "m", "n", "ñ", "o", "p", "q", "r", "s", "t", "u", "v", "w", "x", "y", "z", "A", "B", "C", "D", "E", "F", "G", "H", "I", "J", "K", "L", "M", "N", "Ñ", "O", "P", "Q", "R", "S", "T", "U", "V", "W", "X", "Y", "Z", "1", "2", "3", "4", "5", "6", "7", "8", "9", "0", "@", "#", "$", "%");
					$str_mayusculas = array("A", "B", "C", "D", "E", "F", "G", "H", "I", "J", "K", "L", "M", "N", "Ñ", "O", "P", "Q", "R", "S", "T", "U", "V", "W", "X", "Y", "Z");
					$str_minusculas = array("a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k", "l", "m", "n", "ñ", "o", "p", "q", "r", "s", "t", "u", "v", "w", "x", "y", "z");
					$str_numeros = array("1", "2", "3", "4", "5", "6", "7", "8", "9", "0");
					$str_especiales = array("@", "#", "$", "%");

					for ($i = 0; $i < strlen($password2); $i++) {

						if (in_array($password2[$i], $caracterespermitidos, true)) {

							if (in_array($password2[$i], $str_mayusculas, true)) {
								$mayusculas++;
							} elseif (in_array($password2[$i], $str_minusculas, true)) {
								$minusculas++;
							} elseif (in_array($password2[$i], $str_numeros, true)) {
								$numeros++;
							} elseif (in_array($password2[$i], $str_especiales, true)) {
								$especiales++;
							}

							$validos++;
						} else {
							$no_validos++;
						}
					}


					if ((strlen($password2)) < 8) {

						echo '<div class="alert alert-danger alert-dismissable">
									<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
									<input type="hidden" id="tocon" value="0">
									<h4>
									<i class="icon fa fa-ban"></i>
									Alerta!
									</h4>La nueva clave debe tener por lo menos 8 caracteres.</div>';
						die();
					} elseif ($mayusculas < 1) {

						echo '<div class="alert alert-danger alert-dismissable">
								<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
								<input type="hidden" id="tocon" value="0">
								<h4>
								<i class="icon fa fa-ban"></i>
								Alerta!
								</h4>La nueva contraseña de contener al menos 1 letra mayúscula.</div>';
						die();
					} elseif ($minusculas < 1) {

						echo '<div class="alert alert-danger alert-dismissable">
										<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
										<input type="hidden" id="tocon" value="0">
										<h4>
										<i class="icon fa fa-ban"></i>
										Alerta!
										</h4>La nueva contraseña de contener al menos 1 letra minúscula.</div>';

						die();
					} elseif ($numeros < 1) {

						echo '<div class="alert alert-danger alert-dismissable">
										<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
										<input type="hidden" id="tocon" value="0">
										<h4>
										<i class="icon fa fa-ban"></i>
										Alerta!
										</h4>La nueva contraseña de contener al menos 1 número.</div>';

						die();
					} elseif ($especiales < 1) {

						echo '<div class="alert alert-danger alert-dismissable">
										<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
										<input type="hidden" id="tocon" value="0">
										<h4>
										<i class="icon fa fa-ban"></i>
										Alerta!
										</h4>La nueva contraseña de contener al menos 1 de los siguientes caracteres especiales @ # $ %</div>';

						die();
					} elseif ($no_validos >= 1) {

						echo '<div class="alert alert-danger alert-dismissable">
										<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
										<input type="hidden" id="tocon" value="0">
										<h4>
										<i class="icon fa fa-ban"></i>
										Alerta!
										</h4>La nueva clave debe estar compuesta por letras y números o los siguentes caracteres @, #, $, %</div>';

						die();
					}

					@$password = $password1;
					@$salt = '$DYhG93b0qyJfIxfs2guVoUubWwvniR2G0FgaC9mi$/';

					@$contraseña = md5($salt . $password);

					//exit();
				}

				session_start();
				$usuario_id = $_SESSION['idusuariox'];

				$hoy = date("d-m-Y");

				$m_t_plan = 0;

				if ($m_tuser_id == 3) {


					if ($m_t_plan_id != "") {

						$m_t_plan_id = intval($m_t_plan_id);
						$m_t_plan = $m_t_plan_id;
					} else {

						if ($rol == 3) {

							$data_plan = Vusuario::find_by_sql("SELECT mplanes_id as plan
							FROM Vusuarios 
					 WHERE  mfundos_id=" . $fundo . " ;");

							foreach ($data_plan as $d_plan) {
								$m_t_plan = $d_plan->plan;
							}
						} else if ($rol == 2) {
							echo '<div class="alert alert-danger alert-dismissable">
										<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
										<input type="hidden" id="tocon" value="0">
										<h4>
										<i class="icon fa fa-ban"></i>
										Alerta!
										</h4>Debe Indicar el Plan</div>';

							die();
						}
					}
				}
				$m_t_plan = intval($m_t_plan);
				if ($rol == 2) {
					$m_t_plan = intval($m_t_plan_id);
					$datosSuscripcion = datosSuscripcionProductor($m_t_plan, $msuscripcion_id, $fecha_activa_suscripcion);
					if (isset($datosSuscripcion['error'])) {
						echo '<div class="alert alert-danger alert-dismissable"><input type="hidden" id="tocon" value="0">' . $datosSuscripcion['error'] . '</div>';
						die();
					}
				}



				$usr = new Musuario();
				$usr->name = $nombre;
				$usr->email = strtolower($email);
				$usr->username = strtoupper($usuario);
				$usr->password = $contraseña;
				$usr->user_create = $usuario_id;
				$usr->telefono = $telefono;
				$usr->created = $hoy;
				$usr->mroles_id = $rol;
				$usr->mtipousuarios_id = $m_tuser_id;

				// --------- MULTI-FUNDO ---------
				$usr->es_multifundo = $es_multifundo;
				$usr->max_fundos = $max_fundos;
				// -------------------------------

				// --------- ACCESO AL MODO OFFLINE ---------
				// Se guarda 0/1 (no true/false): el driver de PostgreSQL de
				// Active Record serializa `false` como '' y la columna
				// booleana lo rechaza. El valor ya viene filtrado por rol
				// (solo el productor puede tenerlo activo).
				$usr->acceso_offline = $acceso_offline;
				// ----------------------------------------

				if ($m_t_plan != 0) {
					$usr->mplanes_id = $m_t_plan;
				}

				if ($usr->save()) {
					// Imagen de perfil: se procesa una vez generado el id del usuario
					if (bovs_imagen_hay_archivo(isset($_FILES['file']) ? $_FILES['file'] : null)) {
						$err_img = bovs_imagen_error_archivo($_FILES['file']);
						if ($err_img === null) {
							$img_proc = bovs_imagen_procesar(
								$_FILES['file']['tmp_name'],
								'../dist/img/fotos_usuarios/',
								'usr_' . $usr->id
							);
							if ($img_proc) {
								$usr->imagen = $img_proc;
								$usr->save();
							}
						}
					}

					if ($rol == 2) {
						$errorSuscripcion = guardarSuscripcionProductor($usr->id, $m_t_plan, $msuscripcion_id, $fecha_activa_suscripcion);
						if ($errorSuscripcion !== '') {
							echo '<div class="alert alert-danger alert-dismissable"><input type="hidden" id="tocon" value="0">' . $errorSuscripcion . '</div>';
							die();
						}
					}
					$m_fundo = 0;
					if ($rol == 3 && $m_tuser_id == 3 && $fundo > 0) {
						$m_fundo = $fundo;

						// NORMATIVA: un ayudante solo puede pertenecer a un fundo.
						// Se usa el id del usuario recién creado (y no un "max(id)"
						// por fecha de creación, que podía apuntar a otra cuenta).
						$errores_norm = norm_validar_vinculo($usr->id, $m_fundo);
						if (!empty($errores_norm)) {
							echo '<div class="alert alert-warning alert-dismissable">
										<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
										<input type="hidden" id="tocon" value="0">
										<h4>
										<i class="icon fa fa-warning"></i>
										Alerta!
										</h4>'
										. htmlspecialchars(implode(' ', $errores_norm))
									. '</div>';
							die();
						}

						$cod_usu = (int) $usr->id;

						$reg_fundo_user = new Mfundosxusuario();
						$reg_fundo_user->musuarios_id = $cod_usu;
						$reg_fundo_user->mfundos_id = $m_fundo;
						$reg_fundo_user->user_create = $usuario_id;


						////////////////
						if ($reg_fundo_user->save()) {
							echo '<div class="alert alert-success alert-dismissable">
									<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
									<input type="hidden" id="tocon" value="1">
									<h4>
									<i class="fa fa-check"></i>
									Alerta!
									</h4>
									Usuario [' . $usuario . '] registrado exitosamente...
								</div>';
						} else {
							echo '<div class="alert alert-success alert-dismissable">
										<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
										<input type="hidden" id="tocon" value="1">
										<h4>
										<i class="fa fa-check"></i>
										Alerta!
										</h4>
										Usuario [' . $usuario . '] No pudo ser REGISTRADO...
									</div>';
						}
						break;
					}




					/////////////////////


					echo '<div class="alert alert-success alert-dismissable">
							<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
							<input type="hidden" id="tocon" value="1">
							<h4>
							<i class="fa fa-check"></i>
							Alerta!
							</h4>
							Usuario [' . $usuario . '] registrado exitosamente...
						</div>';
				}
			} else {

				echo '<div class="alert alert-danger alert-dismissable">
						<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
						<input type="hidden" id="tocon" value="0">
						<h4>
						<i class="icon fa fa-ban"></i>
						Alerta!
						</h4>
						El usuario [' . $usuario . '] ya se encuentra registrado.
					</div>';
			}
		}


		break;

	case 'edit':
		$id = (int) $id;
		$rol = (int) $rol;

		// PROTECCIÓN: la cuenta de un SUPER USUARIO (o del tipo ROOT)
		// solo la puede modificar otro SUPER USUARIO, aunque el POST
		// llegue desde fuera del listado.
		if (!puedeGestionarCuenta($id)) {
			echo avisoCuentaProtegida();
			break;
		}

		// Nadie que no sea SUPER USUARIO puede asignar ese perfil.
		if ($rol === 4 && !usuarioSesionEsSuperUsuario()) {
			echo avisoPerfilRestringido();
			break;
		}

		if ($rol !== 2) {
			$es_multifundo = 0;
			$max_fundos = 1;
		} else {
			$es_multifundo = $es_multifundo ? 1 : 0;
			if (!$es_multifundo)
				$max_fundos = 1;
		}
		if ($rol !== 3)
			$fundo = 0;

		if ($rol == 2) {
			$m_t_plan_id = intval($m_t_plan_id);
			$datosSuscripcion = datosSuscripcionProductor($m_t_plan_id, $msuscripcion_id, $fecha_activa_suscripcion);
			if (isset($datosSuscripcion['error'])) {
				echo '<div class="alert alert-danger alert-dismissable"><input type="hidden" id="tocon" value="0">' . $datosSuscripcion['error'] . '</div>';
				break;
			}
		}

		// Unicidad de username y email entre usuarios ACTIVOS (sin distinguir mayúsculas)
		$consulta  = Musuario::find('all', array('conditions' => array('upper(username)=upper(?) AND id<>? AND status=?', $usuario, $id, 1)));
		$consulta2 = Musuario::find('all', array('conditions' => array('lower(email)=lower(?) AND id<>? AND status=?', $email, $id, 1)));

		if (count($consulta) != 0) {

			echo '<div class="alert alert-danger alert-dismissable">
					<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
					<input type="hidden" id="tocon" value="0">
					<h4>
					<i class="icon fa fa-ban"></i>
					Alerta!
					</h4>
					El usuario [' . $usuario . '] ya se encuentra registrado.
					</div>';
		} elseif (count($consulta2) != 0) {

			echo '<div class="alert alert-danger alert-dismissable">
					<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
					<input type="hidden" id="tocon" value="0">
					<h4>
					<i class="icon fa fa-ban"></i>
					Alerta!
					</h4>
					El correo [' . strtolower($email) . '] ya está asociado a otro usuario.
					</div>';
		} elseif ($nombre == "") {

			echo '<div class="alert alert-danger alert-dismissable">
					<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
					<input type="hidden" id="tocon" value="0">
					<h4>
					<i class="icon fa fa-ban"></i>
					Alerta!
					</h4>
					Campo nombre y apellido vacío..
					</div>';
		} elseif ($usuario == "") {

			echo '<div class="alert alert-danger alert-dismissable">
					<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
					<input type="hidden" id="tocon" value="0">
					<h4>
					<i class="icon fa fa-ban"></i>
					Alerta!
					</h4>
					Campo usuario vacío.
					</div>';
		} elseif ($password1 != "" && $password2 == "") {

			echo '<div class="alert alert-warning alert-dismissable">
					<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
					<input type="hidden" id="tocon" value="0">
					<h4>
					<i class="icon fa fa-warning"></i>
					Alerta!
					</h4>
					Debe confirmar la contraseña.
					</div>';
		} elseif ($password1 != "" && $password2 != "") {


			$validos = 0;
			$no_validos = 0;
			$mayusculas = 0;
			$minusculas = 0;
			$especiales = 0;
			$numeros = 0;
			$caracterespermitidos = array("a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k", "l", "m", "n", "ñ", "o", "p", "q", "r", "s", "t", "u", "v", "w", "x", "y", "z", "A", "B", "C", "D", "E", "F", "G", "H", "I", "J", "K", "L", "M", "N", "Ñ", "O", "P", "Q", "R", "S", "T", "U", "V", "W", "X", "Y", "Z", "1", "2", "3", "4", "5", "6", "7", "8", "9", "0", "@", "#", "$", "%");
			$str_mayusculas = array("A", "B", "C", "D", "E", "F", "G", "H", "I", "J", "K", "L", "M", "N", "Ñ", "O", "P", "Q", "R", "S", "T", "U", "V", "W", "X", "Y", "Z");
			$str_minusculas = array("a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k", "l", "m", "n", "ñ", "o", "p", "q", "r", "s", "t", "u", "v", "w", "x", "y", "z");
			$str_numeros = array("1", "2", "3", "4", "5", "6", "7", "8", "9", "0");
			$str_especiales = array("@", "#", "$", "%");

			for ($i = 0; $i < strlen($password2); $i++) {

				if (in_array($password2[$i], $caracterespermitidos, true)) {

					if (in_array($password2[$i], $str_mayusculas, true)) {
						$mayusculas++;
					} elseif (in_array($password2[$i], $str_minusculas, true)) {
						$minusculas++;
					} elseif (in_array($password2[$i], $str_numeros, true)) {
						$numeros++;
					} elseif (in_array($password2[$i], $str_especiales, true)) {
						$especiales++;
					}

					$validos++;
				} else {
					$no_validos++;
				}
			}


			if ((strlen($password2)) < 8) {

				echo '<div class="alert alert-danger alert-dismissable">
						<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
						<input type="hidden" id="tocon" value="0">
						<h4>
						<i class="icon fa fa-ban"></i>
						Alerta!
						</h4>La nueva clave debe tener por lo menos 8 caracteres.</div>';
				die();
			} elseif ($mayusculas < 1) {

				echo '<div class="alert alert-danger alert-dismissable">
					<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
					<input type="hidden" id="tocon" value="0">
					<h4>
					<i class="icon fa fa-ban"></i>
					Alerta!
					</h4>La nueva contraseña de contener al menos 1 letra mayúscula.</div>';
				die();
			} elseif ($minusculas < 1) {

				echo '<div class="alert alert-danger alert-dismissable">
							<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
							<input type="hidden" id="tocon" value="0">
							<h4>
							<i class="icon fa fa-ban"></i>
							Alerta!
							</h4>La nueva contraseña de contener al menos 1 letra minúscula.</div>';

				die();
			} elseif ($numeros < 1) {

				echo '<div class="alert alert-danger alert-dismissable">
							<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
							<input type="hidden" id="tocon" value="0">
							<h4>
							<i class="icon fa fa-ban"></i>
							Alerta!
							</h4>La nueva contraseña de contener al menos 1 número.</div>';

				die();
			} elseif ($especiales < 1) {

				echo '<div class="alert alert-danger alert-dismissable">
							<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
							<input type="hidden" id="tocon" value="0">
							<h4>
							<i class="icon fa fa-ban"></i>
							Alerta!
							</h4>La nueva contraseña de contener al menos 1 de los siguientes caracteres especiales @ # $ %</div>';

				die();
			} elseif ($no_validos >= 1) {

				echo '<div class="alert alert-danger alert-dismissable">
							<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
							<input type="hidden" id="tocon" value="0">
							<h4>
							<i class="icon fa fa-ban"></i>
							Alerta!
							</h4>La nueva clave debe estar compuesta por letras y números o los siguentes caracteres @, #, $, %</div>';

				die();
			}

			//actualiza con nueva contraseña
			@$password = $password1;
			@$salt = '$DYhG93b0qyJfIxfs2guVoUubWwvniR2G0FgaC9mi$/';
			@$contraseña = md5($salt . $password);
			$hoy = date("d-m-Y");

			// IMAGEN — redimensiona y convierte a WebP
			@$nombre_imagen = "";
			// IMAGEN — redimensiona y convierte a WebP; nombre = usr_<id>
			@$nombre_imagen = "";
			if ($id > 0 && bovs_imagen_hay_archivo(isset($_FILES['file']) ? $_FILES['file'] : null)) {
				$err_img = bovs_imagen_error_archivo($_FILES['file']);
				if ($err_img === null) {
					$img_proc = bovs_imagen_procesar(
						$_FILES['file']['tmp_name'],
						'../dist/img/fotos_usuarios/',
						'usr_' . $id
					);
					if ($img_proc) {
						@$nombre_imagen = $img_proc;
					}
				}
			}
			$plan_x = -1;
			if ($rol == 3) {


				$consulta_fundo = Vusuario::find_by_sql("SELECT mfundos_id as fundo, 

												case when mplanes_id  is null
												then 0
												else mplanes_id end plan
												FROM Vusuarios 
										WHERE  id=" . $id . " ;");

				foreach ($consulta_fundo as $d_fundo) {
					$fundo = $d_fundo->fundo;
					$plan_x = $d_fundo->plan;
				}
			}



			session_start();
			$usuario_id = $_SESSION['idusuariox'];

			$usr = Musuario::find($id);
			$usr->name = $nombre;
			$usr->email = strtolower($email);
			$usr->username = strtoupper($usuario);
			$usr->password = $contraseña;
			$usr->telefono = $telefono;
			$usr->mroles_id = $rol;

			// TIPO DE USUARIO: el formulario lo mostraba pero la edición no
			// lo guardaba, así que el cambio se perdía en silencio.
			$usr->mtipousuarios_id = tipoUsuarioParaGuardar($m_tuser_id, $usr->mtipousuarios_id);

			// --------- MULTI-FUNDO ---------
			$usr->es_multifundo = $es_multifundo;
			$usr->max_fundos = $max_fundos;
			// -------------------------------

			// --------- ACCESO AL MODO OFFLINE ---------
			// 0/1 (ver nota en la acción 'add'). Ya viene filtrado por rol:
			// solo el productor (rol 2) puede tenerlo activo.
			$usr->acceso_offline = $acceso_offline;
			// ----------------------------------------

			// Solo cuando el encargado no tiene plan propio. Se exige que el fundo
			// sea válido ($fundo > 0): para una cuenta INACTIVA la vista Vusuarios
			// (WHERE status = 1) no lo devuelve, y la consulta quedaba en
			// "mfundos_id= and ...", lo que reventaba el guardado con error 500.
			if (($plan_x == 0) && ($rol == 3) && ($fundo > 0)) {

				$data_plan = Vusuario::find_by_sql("SELECT mplanes_id as plan
							FROM Vusuarios 
					 WHERE mfundos_id=" . $fundo . " and mplanes_id is not Null;");

				foreach ($data_plan as $d_plan) {

					$usr->mplanes_id = $d_plan->plan;
				}
			} else if ($rol == 2) {
				$usr->mplanes_id = $m_t_plan_id;
			}

			if (!empty($nombre_imagen)) {
				$usr->imagen = $nombre_imagen;
			}
			$usr->user_modify = $usuario_id;
			$usr->updated = $hoy;
			$usr->mroles_id = $rol;

			if ($usr->save()) {
				actualizarSesionSiEsPropia($usr, $usuario_id);

				if ($rol == 2) {
					guardarSuscripcionProductor($usr->id, $m_t_plan_id, $msuscripcion_id, $fecha_activa_suscripcion);
				}

				echo '<div class="alert alert-success alert-dismissable">
							<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
							<input type="hidden" id="tocon" value="1">
							<h4>
							<i class="fa fa-check"></i>
							Alerta!
							</h4>
							Usuario [' . $usuario . '] actualizado exitosamente...
						</div>';
			}
		} elseif ($password1 == "" && $password2 == "") {
			//actualiza todos los campos menos la contraseña
			$hoy = date("d-m-Y");

			// IMAGEN — redimensiona y convierte a WebP
			@$nombre_imagen = "";
			// IMAGEN — redimensiona y convierte a WebP; nombre = usr_<id>
			@$nombre_imagen = "";
			if ($id > 0 && bovs_imagen_hay_archivo(isset($_FILES['file']) ? $_FILES['file'] : null)) {
				$err_img = bovs_imagen_error_archivo($_FILES['file']);
				if ($err_img === null) {
					$img_proc = bovs_imagen_procesar(
						$_FILES['file']['tmp_name'],
						'../dist/img/fotos_usuarios/',
						'usr_' . $id
					);
					if ($img_proc) {
						@$nombre_imagen = $img_proc;
					}
				}
			}

			session_start();
			$usuario_id = $_SESSION['idusuariox'];

			// 0 = "sin plan propio" (se hereda del productor del fundo). Se
			// inicializa para no depender de la vista Vusuarios, que excluye a
			// los usuarios inactivos (status = 0).
			$plan_x = 0;

			if ($rol == 3) {


				$consulta_fundo = Vusuario::find_by_sql("SELECT mfundos_id as fundo, 

												case when mplanes_id  is null
												then 0
												else mplanes_id end plan
												FROM Vusuarios 
										WHERE  id=" . $id . " ;");

				foreach ($consulta_fundo as $d_fundo) {
					$fundo = $d_fundo->fundo;
					$plan_x = $d_fundo->plan;
				}
			}



			$usr = Musuario::find($id);

			$usr->name = $nombre;
			$usr->email = strtolower($email);
			$usr->username = strtoupper($usuario);
			$usr->telefono = $telefono;
			$usr->mroles_id = $rol;

			// TIPO DE USUARIO: el formulario lo mostraba pero la edición no
			// lo guardaba, así que el cambio se perdía en silencio.
			$usr->mtipousuarios_id = tipoUsuarioParaGuardar($m_tuser_id, $usr->mtipousuarios_id);

			// OJO: antes esta rama (editar sin cambiar la clave) sobrescribía
			// created y user_create con la fecha y el usuario que editaba, y
			// se perdía el dato original de creación. Esos campos solo se
			// escriben en el alta.

			// --------- MULTI-FUNDO ---------
			$usr->es_multifundo = $es_multifundo;
			$usr->max_fundos = $max_fundos;
			// -------------------------------

			// --------- ACCESO AL MODO OFFLINE ---------
			// 0/1 (ver nota en la acción 'add'). Ya viene filtrado por rol:
			// solo el productor (rol 2) puede tenerlo activo.
			$usr->acceso_offline = $acceso_offline;
			// ----------------------------------------

			// Solo cuando el encargado no tiene plan propio. Se exige que el fundo
			// sea válido ($fundo > 0): para una cuenta INACTIVA la vista Vusuarios
			// (WHERE status = 1) no lo devuelve, y la consulta quedaba en
			// "mfundos_id= and ...", lo que reventaba el guardado con error 500.
			if (($plan_x == 0) && ($rol == 3) && ($fundo > 0)) {

				$data_plan = Vusuario::find_by_sql("SELECT mplanes_id as plan
							FROM Vusuarios 
							 WHERE mfundos_id=" . $fundo . " and mplanes_id is not Null;");

				foreach ($data_plan as $d_plan) {

					$usr->mplanes_id = $d_plan->plan;
				}
			} else if ($rol == 2) {
				$usr->mplanes_id = $m_t_plan_id;
			}


			if (!empty($nombre_imagen)) {
				$usr->imagen = $nombre_imagen;
			}
			$usr->user_modify = $usuario_id;
			$usr->updated = $hoy;

			if ($usr->save()) {
				actualizarSesionSiEsPropia($usr, $usuario_id);

				if ($rol == 2) {
					guardarSuscripcionProductor($usr->id, $m_t_plan_id, $msuscripcion_id, $fecha_activa_suscripcion);
				}

				echo '<div class="alert alert-success alert-dismissable">
							<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
							<input type="hidden" id="tocon" value="1">
							<h4>
							<i class="fa fa-check"></i>
							Alerta!
							</h4>
							Usuario [' . $usuario . '] actualizado exitosamente...
						</div>';
			}
		}
		break;



	case 'add_img':
		$hoy = date("d-m-Y");
		session_start();
		$usuario_id = $_SESSION['idusuariox'];

		// IMAGEN — redimensiona y convierte a WebP; nombre = usr_<id>
		@$nombre_imagen = "";
		if ($usuario_id > 0 && bovs_imagen_hay_archivo(isset($_FILES['file']) ? $_FILES['file'] : null)) {
			$err_img = bovs_imagen_error_archivo($_FILES['file']);
			if ($err_img === null) {
				$img_proc = bovs_imagen_procesar(
					$_FILES['file']['tmp_name'],
					'../dist/img/fotos_usuarios/',
					'usr_' . $usuario_id
				);
				if ($img_proc) {
					@$nombre_imagen = $img_proc;
				}
			}
		}
		// Solo actualizar la foto de sesión si realmente se guardó una imagen
		if (!empty($nombre_imagen)) {
			$_SESSION['imagen_perfilx'] = $nombre_imagen;
		}

		$usr = Musuario::find($usuario_id);
		if (!empty($nombre_imagen)) {
			$usr->imagen = $nombre_imagen;
		}

		if ($usr->save()) {


			echo '<div class="alert alert-success alert-dismissable">
					<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
					<input type="hidden" id="tocon" value="1">
					<h4>
					<i class="fa fa-check"></i>
					Alerta!
					</h4>
					La imagen de Perfil fue actualizada exitosamente...
				</div>';
		}



		break;


	/* ============================================================
	   RECUPERACIÓN DE CONTRASEÑA — SOLICITUD DEL CÓDIGO
	   ------------------------------------------------------------
	   El usuario escribe el correo de su cuenta y recibe un CÓDIGO
	   de 6 dígitos (vigencia 15 minutos) que luego escribe junto con
	   su contraseña nueva.

	   Reglas:
	     · exige captcha (el mismo del inicio de sesión) para que el
	       formulario no sirva para enviar correos masivos;
	     · la respuesta es SIEMPRE la misma, exista o no la cuenta,
	       para no revelar qué correos están registrados;
	     · un envío cada 3 minutos por cuenta;
	     · en la base de datos se guarda el HASH del código, no el código;
	     · el código admite 5 intentos fallidos (después se anula).

	   Lo consume views/credencial/recuperar.php
	   ============================================================ */
	case 'solicitar_reset':

		header('Content-Type: application/json; charset=utf-8');
		header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
		header('X-Content-Type-Options: nosniff');

		require_once '../lib/captcha_helper.php';
		require_once '../lib/mailer_helper.php';

		// Respuesta neutra (idéntica para cualquier correo)
		$respuestaNeutra = array(
			'ok'   => true,
			'paso' => 2,
			'msg'  => 'Si el correo está registrado, te enviamos un código de 6 dígitos para restablecer tu contraseña. Revisa también la carpeta de spam.'
		);

		// 1) Captcha
		$capToken = isset($_POST['captcha_token']) ? (string) $_POST['captcha_token'] : '';
		$capResp  = isset($_POST['captcha_respuesta']) ? (string) $_POST['captcha_respuesta'] : '';

		if (!bovs_captcha_validar($capToken, $capResp)) {
			echo json_encode(array(
				'ok'            => false,
				'captcha_error' => true,
				'msg'           => 'La verificación de seguridad no coincide. Intenta nuevamente.'
			));
			exit;
		}

		// 2) Correo
		$email_reset = strtolower(trim(isset($_POST['email']) ? (string) $_POST['email'] : ''));

		if ($email_reset === '') {
			echo json_encode(array('ok' => false, 'msg' => 'Ingresa tu correo electrónico.'));
			exit;
		}

		if (!filter_var($email_reset, FILTER_VALIDATE_EMAIL)) {
			echo json_encode(array('ok' => false, 'msg' => 'El correo electrónico no es válido.'));
			exit;
		}

		// 3) Cuenta activa con ese correo (si no existe → respuesta neutra)
		$cuenta_reset = Musuario::find('first', array(
			'conditions' => array('LOWER(email) = ? AND status = 1', $email_reset)
		));

		if (!$cuenta_reset) {
			// La pantalla del código necesita el correo aunque la cuenta no exista
			$_SESSION['reset_email'] = $email_reset;
			$_SESSION['reset_expira'] = time() + 900;
			echo json_encode($respuestaNeutra);
			exit;
		}

		// 4) Límite de envío: 3 minutos por cuenta (respuesta neutra)
		$ultima_solicitud = $cuenta_reset->password_reset_solicitado;
		if (!empty($ultima_solicitud)) {
			$segundos = time() - strtotime((string) $ultima_solicitud);
			if ($segundos >= 0 && $segundos < 180) {
				$_SESSION['reset_email'] = $email_reset;
				echo json_encode($respuestaNeutra);
				exit;
			}
		}

		// 5) Código de 6 dígitos
		$cfg_mail = bovs_mail_config();
		$vigencia = (int) $cfg_mail['reset_vigencia_min'];
		if ($vigencia <= 0) {
			$vigencia = 15;
		}

		$codigo_reset = bovs_mail_codigo();

		$usr = Musuario::find((int) $cuenta_reset->id);
		$usr->password_reset_codigo     = bovs_mail_codigo_hash($codigo_reset);
		$usr->password_reset_expires    = date('Y-m-d H:i:s', time() + ($vigencia * 60));
		$usr->password_reset_solicitado = date('Y-m-d H:i:s');
		$usr->password_reset_intentos   = 0;
		$usr->updated                   = date('Y-m-d H:i:s');

		if (!$usr->save()) {
			error_log('[SOVCA reset] No se pudo guardar el código del usuario ' . (int) $cuenta_reset->id);
			echo json_encode(array(
				'ok'  => false,
				'msg' => 'No se pudo procesar la solicitud. Intenta nuevamente.'
			));
			exit;
		}

		// El correo se recuerda en la sesión para la pantalla del código
		$_SESSION['reset_email']  = $email_reset;
		$_SESSION['reset_expira'] = time() + ($vigencia * 60);

		// 6) Envío del correo
		$envio = bovs_mail_enviar_codigo(
			$email_reset,
			(string) $cuenta_reset->name,
			$codigo_reset,
			$vigencia
		);

		if (!empty($envio['ok'])) {
			echo json_encode($respuestaNeutra);
			exit;
		}

		// Si el correo no salió se anula el código, para no dejar uno válido
		// que el usuario nunca recibió.
		error_log('[SOVCA reset] Falló el envío a ' . $email_reset . ': ' . (isset($envio['error']) ? $envio['error'] : 'sin detalle'));

		$usr->password_reset_codigo   = null;
		$usr->password_reset_expires  = null;
		$usr->password_reset_intentos = 0;
		$usr->save();

		echo json_encode(array(
			'ok'  => false,
			'msg' => 'No se pudo enviar el correo. Intenta más tarde o contacta al administrador del sistema.'
		));
		exit;

	/* ============================================================
	   RECUPERACIÓN DE CONTRASEÑA — REENVIAR UN CÓDIGO NUEVO
	   ------------------------------------------------------------
	   Lo usa el botón "Reenviar" de la pantalla del código.
	   Respeta el mismo límite de 3 minutos y responde igual siempre.
	   ============================================================ */
	case 'reenviar_codigo':

		header('Content-Type: application/json; charset=utf-8');
		header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
		header('X-Content-Type-Options: nosniff');

		require_once '../lib/mailer_helper.php';

		$respuestaNeutra = array(
			'ok'  => true,
			'msg' => 'Si el correo está registrado, te enviamos un código nuevo. Revisa también la carpeta de spam.'
		);

		$email_reset = strtolower(trim(isset($_POST['email']) ? (string) $_POST['email'] : ''));

		if ($email_reset === '' && !empty($_SESSION['reset_email'])) {
			$email_reset = strtolower(trim((string) $_SESSION['reset_email']));
		}

		if ($email_reset === '' || !filter_var($email_reset, FILTER_VALIDATE_EMAIL)) {
			echo json_encode(array('ok' => false, 'msg' => 'Escribe el correo de tu cuenta.'));
			exit;
		}

		$cuenta_reset = Musuario::find('first', array(
			'conditions' => array('LOWER(email) = ? AND status = 1', $email_reset)
		));

		if (!$cuenta_reset) {
			echo json_encode($respuestaNeutra);
			exit;
		}

		// Límite de 3 minutos
		$ultima_solicitud = $cuenta_reset->password_reset_solicitado;
		if (!empty($ultima_solicitud)) {
			$segundos = time() - strtotime((string) $ultima_solicitud);
			if ($segundos >= 0 && $segundos < 180) {
				echo json_encode($respuestaNeutra);
				exit;
			}
		}

		$cfg_mail = bovs_mail_config();
		$vigencia = (int) $cfg_mail['reset_vigencia_min'];
		if ($vigencia <= 0) {
			$vigencia = 15;
		}

		$codigo_reset = bovs_mail_codigo();

		$usr = Musuario::find((int) $cuenta_reset->id);
		$usr->password_reset_codigo     = bovs_mail_codigo_hash($codigo_reset);
		$usr->password_reset_expires    = date('Y-m-d H:i:s', time() + ($vigencia * 60));
		$usr->password_reset_solicitado = date('Y-m-d H:i:s');
		$usr->password_reset_intentos   = 0;
		$usr->updated                   = date('Y-m-d H:i:s');

		if (!$usr->save()) {
			echo json_encode($respuestaNeutra);
			exit;
		}

		$_SESSION['reset_expira'] = time() + ($vigencia * 60);

		$envio = bovs_mail_enviar_codigo(
			$email_reset,
			(string) $cuenta_reset->name,
			$codigo_reset,
			$vigencia
		);

		if (empty($envio['ok'])) {
			error_log('[SOVCA reset] Falló el reenvío a ' . $email_reset . ': ' . (isset($envio['error']) ? $envio['error'] : 'sin detalle'));
			$usr->password_reset_codigo   = null;
			$usr->password_reset_expires  = null;
			$usr->save();
		}

		echo json_encode($respuestaNeutra);
		exit;

	/* ============================================================
	   RECUPERACIÓN DE CONTRASEÑA — APLICAR LA NUEVA
	   ------------------------------------------------------------
	   Valida el CÓDIGO de 6 dígitos (con su vigencia y sus intentos)
	   y las reglas de contraseña (lib/password_policy.php), y guarda
	   la nueva clave con la misma firma del inicio de sesión.

	   Lo consume views/credencial/reset_password.php
	   ============================================================ */
	case 'aplicar_reset':

		header('Content-Type: application/json; charset=utf-8');
		header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
		header('X-Content-Type-Options: nosniff');

		require_once '../lib/password_policy.php';
		require_once '../lib/mailer_helper.php';

		$cfg_mail    = bovs_mail_config();
		$max_intentos = (int) $cfg_mail['reset_max_intentos'];
		if ($max_intentos <= 0) {
			$max_intentos = 5;
		}

		$email_reset = strtolower(trim(isset($_POST['email']) ? (string) $_POST['email'] : ''));
		if ($email_reset === '' && !empty($_SESSION['reset_email'])) {
			$email_reset = strtolower(trim((string) $_SESSION['reset_email']));
		}

		// Se aceptan espacios o guiones al escribir el código
		$codigo_reset = preg_replace('/[^0-9]/', '', isset($_POST['codigo']) ? (string) $_POST['codigo'] : '');

		$pass1_reset = isset($_POST['password1']) ? (string) $_POST['password1'] : '';
		$pass2_reset = isset($_POST['password2']) ? (string) $_POST['password2'] : '';

		if ($email_reset === '' || !filter_var($email_reset, FILTER_VALIDATE_EMAIL)) {
			echo json_encode(array('ok' => false, 'msg' => 'Escribe el correo de tu cuenta.'));
			exit;
		}

		if (strlen($codigo_reset) !== 6) {
			echo json_encode(array('ok' => false, 'msg' => 'El código debe tener los 6 dígitos que enviamos al correo.'));
			exit;
		}

		if ($pass1_reset === '' || $pass2_reset === '') {
			echo json_encode(array('ok' => false, 'msg' => 'Debes completar ambas contraseñas.'));
			exit;
		}

		if ($pass1_reset !== $pass2_reset) {
			echo json_encode(array('ok' => false, 'msg' => 'Las contraseñas no coinciden.'));
			exit;
		}

		$errores_reset = bovs_password_errores($pass1_reset);
		if (!empty($errores_reset)) {
			echo json_encode(array('ok' => false, 'msg' => implode(' ', $errores_reset)));
			exit;
		}

		$cuenta_reset = Musuario::find('first', array(
			'conditions' => array('LOWER(email) = ? AND status = 1', $email_reset)
		));

		// Sin código pendiente (cuenta inexistente o sin solicitud) → mensaje genérico
		if (!$cuenta_reset || empty($cuenta_reset->password_reset_codigo)) {
			echo json_encode(array(
				'ok'  => false,
				'msg' => 'El código no es válido o ya caducó. Solicita uno nuevo desde "¿Olvidaste tu contraseña?".'
			));
			exit;
		}

		$cuenta_id = (int) $cuenta_reset->id;

		// Intentos agotados: se anula el código
		if ((int) $cuenta_reset->password_reset_intentos >= $max_intentos) {
			$bloqueada = Musuario::find($cuenta_id);
			$bloqueada->password_reset_codigo   = null;
			$bloqueada->password_reset_expires  = null;
			$bloqueada->password_reset_intentos = 0;
			$bloqueada->save();

			echo json_encode(array(
				'ok'  => false,
				'msg' => 'Se agotaron los intentos con ese código. Solicita uno nuevo desde "¿Olvidaste tu contraseña?".'
			));
			exit;
		}

		// Caducidad
		$expira_reset = strtotime((string) $cuenta_reset->password_reset_expires);
		if (!$expira_reset || $expira_reset < time()) {
			$vencido = Musuario::find($cuenta_id);
			$vencido->password_reset_codigo     = null;
			$vencido->password_reset_expires    = null;
			$vencido->password_reset_intentos   = 0;
			$vencido->password_reset_solicitado = null;
			$vencido->save();

			echo json_encode(array(
				'ok'  => false,
				'msg' => 'El código caducó. Solicita uno nuevo desde "¿Olvidaste tu contraseña?".'
			));
			exit;
		}

		// Código incorrecto → se cuenta el intento
		if (!hash_equals((string) $cuenta_reset->password_reset_codigo, bovs_mail_codigo_hash($codigo_reset))) {
			$intentos = (int) $cuenta_reset->password_reset_intentos + 1;
			$fallida  = Musuario::find($cuenta_id);
			$fallida->password_reset_intentos = $intentos;

			if ($intentos >= $max_intentos) {
				$fallida->password_reset_codigo  = null;
				$fallida->password_reset_expires = null;
			}
			$fallida->save();

			$quedan = $max_intentos - $intentos;
			echo json_encode(array(
				'ok'           => false,
				'intentos'     => $intentos,
				'intentos_max' => $max_intentos,
				'msg'          => ($quedan > 0)
					? 'El código no es correcto. Te quedan ' . $quedan . ' intento(s).'
					: 'El código no es correcto y se agotaron los intentos. Solicita uno nuevo.'
			));
			exit;
		}

		// Código correcto → se guarda la contraseña nueva
		$usr = Musuario::find($cuenta_id);
		$usr->password                  = bovs_password_hash($pass1_reset);
		$usr->password_reset_codigo     = null;
		$usr->password_reset_expires    = null;
		$usr->password_reset_solicitado = null;
		$usr->password_reset_intentos   = 0;
		// La clave la eligió el usuario: no se le vuelve a pedir cambiarla
		$usr->act_clave   = 1;
		$usr->user_modify = $cuenta_id;
		$usr->updated     = date('Y-m-d H:i:s');

		if ($usr->save()) {
			// La sesión del código ya no hace falta
			unset($_SESSION['reset_email']);
			unset($_SESSION['reset_expira']);

			echo json_encode(array(
				'ok'  => true,
				'msg' => 'Contraseña actualizada correctamente. Ya puedes iniciar sesión.'
			));
		} else {
			error_log('[SOVCA reset] No se pudo actualizar la contraseña del usuario ' . $cuenta_id);
			echo json_encode(array(
				'ok'  => false,
				'msg' => 'No se pudo guardar la nueva contraseña. Intenta nuevamente.'
			));
		}
		exit;

	case 'login':

		/* Cualquier inicio de sesión "normal" arranca fuera del modo
		   demostración: la base real es la de por defecto. Solo el
		   respaldo de demo (más abajo) vuelve a marcar la sesión. */
		unset($_SESSION['demo'], $_SESSION['demo_fecha_caduca']);

		/* ============================================================
		   VALIDACIÓN DE CAPTCHA (token anti-manipulación)
		   ------------------------------------------------------------
		   - Valida token, expiración (15 min) y resultado numérico
		   - Usa hash_equals para evitar timing attacks
		   - Captcha de un solo uso
		   ============================================================ */
		$captchaToken = isset($_POST['captcha_token']) ? (string) $_POST['captcha_token'] : '';
		$captchaRespuesta = isset($_POST['captcha_respuesta']) ? trim((string) $_POST['captcha_respuesta']) : '';
		$captchaSession = isset($_SESSION['captcha']) ? $_SESSION['captcha'] : null;

		$captchaInvalido = (
			!is_array($captchaSession) ||
			!isset($captchaSession['token'], $captchaSession['resultado'], $captchaSession['ts']) ||
			!hash_equals((string) $captchaSession['token'], $captchaToken) ||
			(time() - (int) $captchaSession['ts']) > 900
		);

		if (!$captchaInvalido) {
			if (
				!preg_match('/^-?\d+$/', $captchaRespuesta) ||
				(int) $captchaRespuesta !== (int) $captchaSession['resultado']
			) {
				$captchaInvalido = true;
			}
		}

		// Invalidar captcha (un solo uso)
		unset($_SESSION['captcha']);

		if ($captchaInvalido) {
			if ($usuario !== '') {
				$_SESSION['login_usuario_temp'] = $usuario;
			}
			header("Location: ../views/login?stat=captcha");
			exit();
		}
		/* ============================================================
		   FIN VALIDACIÓN CAPTCHA — continúa el flujo normal
		   ============================================================ */

		if ($usuario != "" && $password1 != "") {


			@$password = $password1;
			@$salt = '$DYhG93b0qyJfIxfs2guVoUubWwvniR2G0FgaC9mi$/';
			@$contraseña = md5($salt . $password);
			@$consulta = Vusuario::find('all', array('conditions' => array('username=? and password =?', strtoupper($usuario), $contraseña)));



			if ($consulta != null) {



				foreach ($consulta as $rs) {

					/* ============================================================
					   MODO MANTENIMIENTO
					   ------------------------------------------------------------
					   Solo pueden iniciar sesión los perfiles 1 (Administrador)
					   y 4 (Super Usuario). Los productores (2) y ayudantes (3)
					   quedan fuera mientras el sistema esté en mantenimiento.
					   ============================================================ */
					require_once '../lib/mantenimiento_helper.php';
					if (bovs_mantenimiento_activo() && !bovs_mantenimiento_permite_rol((int) $rs->mroles_id)) {
						if ($usuario !== '') {
							$_SESSION['login_usuario_temp'] = $usuario;
						}
						header("Location: ../views/login?stat=mantenimiento");
						exit();
					}

					// Un AYUDANTE (rol 3) hereda la situación del productor dueño de
					// su fundo: si no queda ningún productor activo en sus fundos,
					// no puede entrar (aunque su propia cuenta siga activa).
					if ((int) $rs->mroles_id === 3
						&& bovs_prod_usuario_sin_productor_activo(Vusuario::connection(), (int) $rs->id)) {
						if ($usuario !== '') {
							$_SESSION['login_usuario_temp'] = $usuario;
						}
						header("Location: ../views/login?stat=sin_productor");
						exit();
					}

					$fechaCaducaSesion = '';
					$vigencia = obtenerVigenciaAplicable($rs);
					if ($vigencia && $vigencia->fecha_caduca_suscripcion) {
						$fechaCaducaActual = $vigencia->fecha_caduca_suscripcion->format('Y-m-d');
						if ($fechaCaducaActual <= date('Y-m-d')) {
							header("Location: ../views/login?stat=subscription_expired");
							exit();
						}
						if ($fechaCaducaSesion === '' || $fechaCaducaActual < $fechaCaducaSesion) {
							$fechaCaducaSesion = $fechaCaducaActual;
						}
					}

					session_start();
					$xsession_id = strtoupper(session_id());
					$xusuario_id = $rs->id;
					$_SESSION['idsessionx'] = session_id();
					$_SESSION['idusuariox'] = $xusuario_id;
					$_SESSION['usuariox'] = $rs->username;
					$_SESSION['nombresx'] = $rs->name;
					$_SESSION['rolx'] = $rs->mroles_id;
					$_SESSION['fecha_caduca_suscripcion'] = $fechaCaducaSesion;
					$_SESSION['suscripcion_verificada'] = 1;
					$_SESSION['imagen_perfilx'] = $rs->imagen;
					$_SESSION['nombre_rolx'] = $rs->descripcion;
					$_SESSION['creadox'] = ($rs->created->format('d-m-Y'));
					$_SESSION['mfundos_id'] = $rs->mfundos_id;
					$_SESSION['tipo_ganaderia'] = $rs->mtipoganaderias_id;
					$_SESSION['mtipo_user_id'] = $rs->mtipousuarios_id;
					$_SESSION['acceso_alerta'] = true;
					$_SESSION['acceso_sms'] = true;
					$_SESSION['maximo_registros'] = $rs->maximo_registros;
					$_SESSION['es_multifundo'] = isset($rs->es_multifundo) ? (int) $rs->es_multifundo : 0;
					$_SESSION['max_fundos'] = isset($rs->max_fundos) ? (int) $rs->max_fundos : 1;

					if ((($rs->mroles_id == 2)) || ($rs->mroles_id == 3) && ($rs->mfundos_id != '')) {

						if ($rs->mfundos_id != "") {

							$cadena = "and mfundos_id=" . $rs->mfundos_id . " ";
							$_SESSION['act_email'] = $rs->act_email;
							$_SESSION['accion'] = true;
						} else {
							$cadena = "";
						}
						$max_animal = Manimalxfundo::find_by_sql("SELECT count(id) as cant_animal
							FROM Manimalxfundos
							WHERE status=1  $cadena ;");

						foreach ($max_animal as $fund_ani) {
							$cant_animal = $fund_ani->cant_animal;
						}

						if ($rs->maximo_registros >= $cant_animal) {

							$_SESSION['acceso_reg_animales'] = 1;
						} else {
							$_SESSION['acceso_reg_animales'] = 0;
						}
					}

					if ($rs->act_clave == 0) {

						header("Location: ../views/act_pass/");
						exit();
					}

					// MULTI-FUNDO: la resolución del fundo activo se realiza
					// después de este bucle (ver bloque posterior al foreach).
				}

				// =========================================================
				// MULTI-FUNDO: resolver el fundo activo al iniciar sesión
				// =========================================================
				require_once '../lib/multifundo_helper.php';
				$uid = (int) $_SESSION['idusuariox'];
				$es_multi = mf_es_usuario_multifundo($uid) ? 1 : 0;
				$_SESSION['es_multifundo'] = $es_multi;

				if ($es_multi === 1) {
					$total_fundos = mf_contar_fundos_usuario($uid);

					if ($total_fundos === 1) {
						// Auto-seleccionar el único fundo disponible
						$f = mf_listar_fundos_usuario($uid);
						if (isset($f[0])) {
							mf_cargar_fundo_en_sesion($uid, (int) $f[0]->mfundos_id);
						}
					} elseif ($total_fundos > 1) {
						// Restaurar el último fundo activo (guardado al cambiarlo
						// o al cerrar la sesión). Si sigue vigente, se entra
						// directamente; si no, se muestra el selector.
						$ultimo_fundo = mf_ultimo_fundo($uid);

						if ($ultimo_fundo > 0 && mf_cargar_fundo_en_sesion($uid, $ultimo_fundo)) {
							// Fundo restaurado: continúa hacia el dashboard
						} else {
							// Sin fundo previo válido → forzar selector
							$_SESSION['mfundos_id'] = 0;
							$_SESSION['tipo_ganaderia'] = 0;
							$_SESSION['maximo_registros'] = 0;
							$_SESSION['acceso_reg_animales'] = 0;
							$_SESSION['fundo_nombre'] = '';
							$_SESSION['mplanes_id'] = 0;

							// Registrar el acceso en el historial de conexiones
							// (inserta una fila nueva y actualiza el rastreo legado)
							bovs_conexion_registrar($uid, 'web');

							header("Location: ../views/fondos/seleccionar");
							exit();
						}
					} else {
						// Sin fundos → a crear el primero
						header("Location: ../views/fondos/frm_registrar");
						exit();
					}
				}
				// =========================================================

				//*****************************************************************************


				session_start();
				$usuario_id = $_SESSION['idusuariox'];

				// Registrar el acceso en el historial de conexiones
				// (una fila por inicio de sesión, con país y ciudad)
				bovs_conexion_registrar($usuario_id, 'web');





				if (($_SESSION['rolx'] == 1) || ($_SESSION['rolx'] == 4)) { //ingresa como administrador o programador

					header("Location: ../views/inicio/");
				} elseif ($_SESSION['rolx'] == 2) { //entra como supervisor

					header("Location: ../views/inicio/");
				} elseif ($_SESSION['rolx'] == 3) { //entra como colaborador

					header("Location: ../views/inicio");
				}

				//******************************************************************************

			} else {
				/* MODO MANTENIMIENTO: las cuentas demo son de productores
				   (rol 2), así que tampoco pueden entrar mientras el sistema
				   esté cerrado. */
				require_once '../lib/mantenimiento_helper.php';
				if (bovs_mantenimiento_activo()) {
					if ($usuario !== '') {
						$_SESSION['login_usuario_temp'] = $usuario;
					}
					header("Location: ../views/login?stat=mantenimiento");
					exit();
				}

				/* ============================================================
				   RESPALDO: CUENTAS DE DEMOSTRACIÓN
				   ------------------------------------------------------------
				   La cuenta no existe en la base real. Antes de rechazar el
				   acceso se prueba en la base de pruebas (sovca_demo): las
				   cuentas creadas desde "Acceso demostrativo" viven allí y
				   no en la base de producción.

				   Si entra, la sesión queda con $_SESSION['demo'] = 1 y
				   config/conexion.php resuelve todo contra sovca_demo.
				   ============================================================ */
				require_once '../lib/demo_helper.php';

				$demo = bovs_demo_autenticar($usuario, $password1);

				if (!empty($demo['ok']) && !empty($demo['usuario'])) {
					bovs_demo_iniciar_sesion($demo['usuario']);
					header("Location: ../views/inicio/");
					exit();
				}

				unset($_SESSION['demo'], $_SESSION['demo_fecha_caduca']);

				if (isset($demo['error']) && $demo['error'] === 'vencida') {
					header("Location: ../views/login?stat=demo_vencida");
					exit();
				}

				if ($usuario !== '') {
					$_SESSION['login_usuario_temp'] = $usuario;
				}
				header("Location: ../views/login?stat=failed");
			}
		} else {

			if ($usuario !== '') {
				$_SESSION['login_usuario_temp'] = $usuario;
			}
			header("Location: ../views/login?stat=null");
		}
		break;

	case 'readd':

		$valor = $_POST["record"];

		if (!empty($valor)) {

			// Las cuentas SUPER USUARIO / ROOT solo las puede reactivar
			// otro SUPER USUARIO.
			if (!puedeGestionarCuenta($valor)) {
				echo avisoCuentaProtegida();
				break;
			}

			session_start();
			$usuario_id = $_SESSION['idusuariox'];
			$rol = $_SESSION['rolx'];
			$hoy = date("d-m-Y");

			if ($usuario_id != "") {

				$usr = Musuario::find($valor);

				// Firma de la inhabilitación anterior: los ayudantes que se
				// inhabilitaron JUNTO con este productor comparten su mismo
				// user_modify y su misma fecha, así que se pueden reconocer para
				// reactivarlos sin revivir cuentas inhabilitadas por otros motivos.
				$rol_obj = $usr ? (int) $usr->mroles_id : 0;
				$firma_mod = $usr ? (int) $usr->user_modify : 0;
				$firma_fecha = null;
				if ($usr && $usr->updated instanceof DateTime) {
					$firma_fecha = $usr->updated->format('Y-m-d');
				} elseif ($usr && !empty($usr->updated)) {
					$firma_fecha = date('Y-m-d', strtotime($usr->updated));
				}

				$usr->user_modify = $usuario_id;
				$usr->updated = $hoy;
				$usr->fecha_activacion = $hoy;
				$usr->status = 1;

				$aviso_heredados = '';

				if ($usr->save()) {

					// HERENCIA: al reactivar al productor vuelven sus ayudantes
					// (los que se inhabilitaron con él).
					$heredados = array();
					if ($rol_obj == 2) {
						$heredados = bovs_prod_reactivar_ayudantes(Musuario::connection(), $valor, $usuario_id, $hoy, $firma_mod, $firma_fecha);
					}

					if (!empty($heredados)) {
						$nombres_heredados = array();
						foreach ($heredados as $h) {
							$nombres_heredados[] = $h['username'];
						}
						$aviso_heredados = '<p style="margin:8px 0 0;">Se reactivaron también sus usuarios del fundo: <strong>'
							. htmlspecialchars(implode(', ', $nombres_heredados)) . '</strong>.</p>';
					}

					echo '<div class="alert alert-success alert-dismissable">
									<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
									<h4>
									<i class="fa fa-check"></i>
									Alerta!
									</h4>
									Usuario Activado exitosamente...' . $aviso_heredados . '
								</div>';
				} else {
					echo '<div class="alert alert-danger alert-dismissable">
								<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
								<h4>
								<i class="icon fa fa-ban"></i>
								Alerta!
								</h4>
								Disculpe! Ha ocurrido un error al intentar Activar el registro..
								</div>';
				}
			} else {

				echo '<div class="alert alert-warning alert-dismissable">
								<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
								<h4>
								<i class="icon fa fa-warning"></i>
								Alerta!
								</h4>
								Disculpe! Usted no puede realizar esta transacción.
								</div>';
			}
		} else {

			echo '<div class="alert alert-warning alert-dismissable">
								<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
								<h4>
								<i class="icon fa fa-warning"></i>
								Alerta!
								</h4>
								Error al intentar Activar el registro..
								</div>';
		}

		break;



	case 'delete':
		$valor = $_POST["record"];

		if (!empty($valor)) {

			// Las cuentas SUPER USUARIO / ROOT solo las puede inactivar
			// otro SUPER USUARIO.
			if (!puedeGestionarCuenta($valor)) {
				echo avisoCuentaProtegida();
				break;
			}

			session_start();
			$usuario_id = $_SESSION['idusuariox'];
			$rol = $_SESSION['rolx'];
			$hoy = date("d-m-Y");




			// Perfil del usuario a inhabilitar, leído de la TABLA BASE: la vista
			// vusuarios trae "WHERE status = 1" y no sirve ni para cuentas ya
			// inactivas ni para conocer TODOS los fundos del usuario.
			$objetivo = Musuario::find($valor);
			$rol_u = $objetivo ? (int) $objetivo->mroles_id : 0;

			$grabar = false;
			if ($objetivo) {

				$objetivo->user_modify = $usuario_id;
				$objetivo->updated = $hoy;
				$objetivo->status = 0;
				$grabar = (bool) $objetivo->save();
			}

			// --------- HERENCIA DEL PRODUCTOR → AYUDANTES (rol 3) ---------
			// Si se inhabilita un PRODUCTOR, los usuarios que trabajan en sus
			// fundos heredan la inhabilitación. Antes esto se hacía con la vista
			// vusuarios: inactivaba a TODOS los usuarios del fundo (incluidos
			// otros productores del mismo fundo) y solo del último fundo leído,
			// de modo que un productor con varios fundos dejaba ayudantes con
			// acceso. Ahora se recorren todos sus fundos y solo sus ayudantes,
			// respetando los fundos que siguen con dueño activo.
			$heredados = array();
			if ($grabar && $rol_u == 2) {
				$heredados = bovs_prod_inhabilitar_ayudantes(Musuario::connection(), $valor, $usuario_id, $hoy);
			}
			// -------------------------------------------------------------

			$aviso_heredados = '';
			if (!empty($heredados)) {
				$nombres_heredados = array();
				foreach ($heredados as $h) {
					$nombres_heredados[] = $h['username'];
				}
				$aviso_heredados = '<p style="margin:8px 0 0;">Por depender de este productor se inhabilitaron también: <strong>'
					. htmlspecialchars(implode(', ', $nombres_heredados)) . '</strong>.</p>';
			}

			if ($grabar) {

				echo '<div class="alert alert-success alert-dismissable">
									<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
									<h4>
									<i class="fa fa-check"></i>
									Alerta!
									</h4>
									Usuario eliminado exitosamente...' . $aviso_heredados . '
								</div>';
			} else {
				echo '<div class="alert alert-danger alert-dismissable">
								<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
								<h4>
								<i class="icon fa fa-ban"></i>
								Alerta!
								</h4>
								Disculpe! Ha ocurrido un error al eliminar el registro..
								</div>';
			}
		} else {

			echo '<div class="alert alert-warning alert-dismissable">
								<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
								<h4>
								<i class="icon fa fa-warning"></i>
								Alerta!
								</h4>
								Disculpe! Usted no puede realizar esta transacción.
								</div>';
		}


		break;


	case 'changepassword':

		if ($password1 == "") {

			echo '<div class="alert alert-warning alert-dismissable">
					<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
					<h4>
					<i class="icon fa fa-warning"></i>
					Alerta!
					</h4>
					Ingrese la nueva contraseña.
					</div>';
		} elseif ($password2 == "") {
			echo '<div class="alert alert-warning alert-dismissable">
					<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
					<h4>
					<i class="icon fa fa-warning"></i>
					Alerta!
					</h4>
					Debe confirmar la contraseña.
					</div>';
		} else {
			session_start();
			$usuario_id = $_SESSION['idusuariox'];

			if ($usuario_id) {

				$validos = 0;
				$no_validos = 0;
				$mayusculas = 0;
				$minusculas = 0;
				$especiales = 0;
				$numeros = 0;
				$caracterespermitidos = array("a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k", "l", "m", "n", "ñ", "o", "p", "q", "r", "s", "t", "u", "v", "w", "x", "y", "z", "A", "B", "C", "D", "E", "F", "G", "H", "I", "J", "K", "L", "M", "N", "Ñ", "O", "P", "Q", "R", "S", "T", "U", "V", "W", "X", "Y", "Z", "1", "2", "3", "4", "5", "6", "7", "8", "9", "0", "@", "#", "$", "%");
				$str_mayusculas = array("A", "B", "C", "D", "E", "F", "G", "H", "I", "J", "K", "L", "M", "N", "Ñ", "O", "P", "Q", "R", "S", "T", "U", "V", "W", "X", "Y", "Z");
				$str_minusculas = array("a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k", "l", "m", "n", "ñ", "o", "p", "q", "r", "s", "t", "u", "v", "w", "x", "y", "z");
				$str_numeros = array("1", "2", "3", "4", "5", "6", "7", "8", "9", "0");
				$str_especiales = array("@", "#", "$", "%");

				for ($i = 0; $i < strlen($password2); $i++) {

					if (in_array($password2[$i], $caracterespermitidos, true)) {

						if (in_array($password2[$i], $str_mayusculas, true)) {
							$mayusculas++;
						} elseif (in_array($password2[$i], $str_minusculas, true)) {
							$minusculas++;
						} elseif (in_array($password2[$i], $str_numeros, true)) {
							$numeros++;
						} elseif (in_array($password2[$i], $str_especiales, true)) {
							$especiales++;
						}

						$validos++;
					} else {
						$no_validos++;
					}
				}


				if ((strlen($password2)) < 8) {

					echo '<div class="alert alert-danger alert-dismissable">
						<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
						<h4>
						<i class="icon fa fa-ban"></i>
						Alerta!
						</h4>La nueva clave debe tener por lo menos 8 caracteres.</div>';
					die();
				} elseif ($mayusculas < 1) {

					echo '<div class="alert alert-danger alert-dismissable">
					<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
					<h4>
					<i class="icon fa fa-ban"></i>
					Alerta!
					</h4>La nueva contraseña de contener al menos 1 letra mayúscula.</div>';
					die();
				} elseif ($minusculas < 1) {

					echo '<div class="alert alert-danger alert-dismissable">
							<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
							<h4>
							<i class="icon fa fa-ban"></i>
							Alerta!
							</h4>La nueva contraseña de contener al menos 1 letra minúscula.</div>';

					die();
				} elseif ($numeros < 1) {

					echo '<div class="alert alert-danger alert-dismissable">
							<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
							<h4>
							<i class="icon fa fa-ban"></i>
							Alerta!
							</h4>La nueva contraseña de contener al menos 1 número.</div>';

					die();
				} elseif ($especiales < 1) {

					echo '<div class="alert alert-danger alert-dismissable">
							<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
							<h4>
							<i class="icon fa fa-ban"></i>
							Alerta!
							</h4>La nueva contraseña de contener al menos 1 de los siguientes caracteres especiales @ # $ %</div>';

					die();
				} elseif ($no_validos >= 1) {

					echo '<div class="alert alert-danger alert-dismissable">
							<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
							<h4>
							<i class="icon fa fa-ban"></i>
							Alerta!
							</h4>La nueva clave debe estar compuesta por letras y números o los siguentes caracteres @, #, $, %</div>';

					die();
				}



				@$password = $password1;
				@$salt = '$DYhG93b0qyJfIxfs2guVoUubWwvniR2G0FgaC9mi$/';
				@$contraseña = md5($salt . $password);

				$hoy = date("d-m-Y");

				$usr = Musuario::find($usuario_id);
				$usr->password = $contraseña;
				$usr->user_modify = $usuario_id;
				$usr->updated = $hoy;
				if ($usr->save()) {
					echo '<div class="alert alert-success alert-dismissable">
									<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
									<h4>
									<i class="fa fa-check"></i>
									Alerta!
									</h4>
									La contraseña ha sido cambiada exitosamente.
								</div>';
				}
			} else {
				echo '<div class="alert alert-warning alert-dismissable">
								<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
								<h4>
								<i class="icon fa fa-warning"></i>
								Alerta!
								</h4>
								Error al actualizar la contraseña.
								</div>';
			}
		}
		break;


	case 'find_perfil':

		if (session_status() === PHP_SESSION_NONE) {
			session_start();
		}
		$usuario_id = (int) (isset($_SESSION['idusuariox']) ? $_SESSION['idusuariox'] : 0);

		$resp = array(
			"id" => $usuario_id,
			"nombrerol" => '',
			"usuario" => '',
			"nombre" => '',
			"email" => '',
			"telefono" => '',
			"imagen" => '',
			"rol" => 0,
			"tipo_user" => 0,
			// El formulario "Mi perfil" habilita los campos solo para el
			// SUPER USUARIO: el resto de los perfiles lo consulta.
			"editable" => usuarioSesionEsSuperUsuario()
		);

		// Se lee de la tabla base para devolver también el teléfono (la
		// vista vusuarios excluye a las cuentas inactivas).
		$cuenta_perfil = usuarioBuscarCuenta($usuario_id);
		if ($cuenta_perfil) {
			$rol_perfil = Mrole::find('first', array('conditions' => array('id=?', (int) $cuenta_perfil->mroles_id)));
			$resp['nombrerol'] = $rol_perfil ? $rol_perfil->nombre : '';
			$resp['usuario'] = $cuenta_perfil->username;
			$resp['nombre'] = $cuenta_perfil->name;
			$resp['email'] = $cuenta_perfil->email;
			$resp['telefono'] = trim((string) $cuenta_perfil->telefono);
			$resp['imagen'] = $cuenta_perfil->imagen;
			$resp['rol'] = (int) $cuenta_perfil->mroles_id;
			$resp['tipo_user'] = (int) $cuenta_perfil->mtipousuarios_id;
		}

		echo json_encode($resp);

		break;

	case 'act_perfil':

		// Actualización de los datos propios desde "Mi perfil".
		// Reservado al SUPER USUARIO (el resto de los perfiles usa la
		// pantalla de solo lectura y el cambio de contraseña).
		if (session_status() === PHP_SESSION_NONE) {
			session_start();
		}
		$usuario_id = (int) (isset($_SESSION['idusuariox']) ? $_SESSION['idusuariox'] : 0);

		if (!usuarioSesionEsSuperUsuario() || $usuario_id <= 0) {
			echo avisoCuentaProtegida();
			break;
		}

		$nombre_perfil = usuariosMayusculas(isset($_POST['nombre']) ? trim($_POST['nombre']) : '');
		$usuario_perfil = usuariosMayusculas(isset($_POST['usuario']) ? trim($_POST['usuario']) : '');
		$email_perfil = strtolower(trim(isset($_POST['email']) ? $_POST['email'] : ''));
		$telefono_perfil = trim(isset($_POST['telefono']) ? $_POST['telefono'] : '');

		if ($usuario_perfil === '' || $nombre_perfil === '' || $email_perfil === '') {
			echo '<div class="alert alert-warning alert-dismissable">
					<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
					<input type="hidden" id="tocon" value="0">
					<h4><i class="icon fa fa-warning"></i> Alerta!</h4>
					Complete el usuario, el nombre y el correo.
				</div>';
			break;
		}

		if (!filter_var($email_perfil, FILTER_VALIDATE_EMAIL)) {
			echo '<div class="alert alert-warning alert-dismissable">
					<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
					<input type="hidden" id="tocon" value="0">
					<h4><i class="icon fa fa-warning"></i> Alerta!</h4>
					El correo indicado no es válido.
				</div>';
			break;
		}

		// Unicidad entre cuentas ACTIVAS, igual que en el alta y la edición.
		$dup_usuario = Musuario::find('all', array('conditions' => array('upper(username)=upper(?) AND id<>? AND status=?', $usuario_perfil, $usuario_id, 1)));
		$dup_email = Musuario::find('all', array('conditions' => array('lower(email)=lower(?) AND id<>? AND status=?', $email_perfil, $usuario_id, 1)));

		if (count($dup_usuario) != 0) {
			echo '<div class="alert alert-danger alert-dismissable">
					<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
					<input type="hidden" id="tocon" value="0">
					<h4><i class="icon fa fa-ban"></i> Alerta!</h4>
					El usuario [' . $usuario_perfil . '] ya se encuentra registrado.
				</div>';
			break;
		}

		if (count($dup_email) != 0) {
			echo '<div class="alert alert-danger alert-dismissable">
					<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
					<input type="hidden" id="tocon" value="0">
					<h4><i class="icon fa fa-ban"></i> Alerta!</h4>
					El correo [' . $email_perfil . '] ya está asociado a otro usuario.
				</div>';
			break;
		}

		$cuenta_perfil = usuarioBuscarCuenta($usuario_id);
		if (!$cuenta_perfil) {
			echo '<div class="alert alert-danger alert-dismissable">
					<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
					<input type="hidden" id="tocon" value="0">
					<h4><i class="icon fa fa-ban"></i> Alerta!</h4>
					No se encontró la cuenta de la sesión.
				</div>';
			break;
		}

		$cuenta_perfil->name = $nombre_perfil;
		$cuenta_perfil->username = strtoupper($usuario_perfil);
		$cuenta_perfil->email = $email_perfil;
		$cuenta_perfil->telefono = $telefono_perfil;
		$cuenta_perfil->user_modify = $usuario_id;
		$cuenta_perfil->updated = date("d-m-Y");

		if ($cuenta_perfil->save()) {
			// La cabecera del sistema lee estos valores de la sesión.
			$_SESSION['usuariox'] = $cuenta_perfil->username;
			$_SESSION['nombresx'] = $cuenta_perfil->name;

			echo '<div class="alert alert-success alert-dismissable">
					<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
					<input type="hidden" id="tocon" value="1">
					<h4><i class="fa fa-check"></i> Alerta!</h4>
					Datos del perfil actualizados exitosamente...
				</div>';
		} else {
			echo '<div class="alert alert-danger alert-dismissable">
					<button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
					<input type="hidden" id="tocon" value="0">
					<h4><i class="icon fa fa-ban"></i> Alerta!</h4>
					No se pudieron guardar los datos del perfil.
				</div>';
		}

		break;

	case 'act_clave':

		if (empty($password1)) {
			header("Location: ../views/act_pass/?stat=pass1");
			die();
		} elseif (empty($password2)) {
			header("Location: ../views/act_pass/?stat=pass2");
			die();
		} elseif ($password1 == " " && $password2 == " ") {
			header("Location: ../views/act_pass/?stat=passnull");
			die();
		} else {

			$validos = 0;
			$no_validos = 0;
			$mayusculas = 0;
			$minusculas = 0;
			$especiales = 0;
			$numeros = 0;
			$caracterespermitidos = array("a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k", "l", "m", "n", "ñ", "o", "p", "q", "r", "s", "t", "u", "v", "w", "x", "y", "z", "A", "B", "C", "D", "E", "F", "G", "H", "I", "J", "K", "L", "M", "N", "Ñ", "O", "P", "Q", "R", "S", "T", "U", "V", "W", "X", "Y", "Z", "1", "2", "3", "4", "5", "6", "7", "8", "9", "0", "@", "#", "$", "%");
			$str_mayusculas = array("A", "B", "C", "D", "E", "F", "G", "H", "I", "J", "K", "L", "M", "N", "Ñ", "O", "P", "Q", "R", "S", "T", "U", "V", "W", "X", "Y", "Z");
			$str_minusculas = array("a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k", "l", "m", "n", "ñ", "o", "p", "q", "r", "s", "t", "u", "v", "w", "x", "y", "z");
			$str_numeros = array("1", "2", "3", "4", "5", "6", "7", "8", "9", "0");
			$str_especiales = array("@", "#", "$", "%");

			for ($i = 0; $i < strlen($password2); $i++) {

				if (in_array($password2[$i], $caracterespermitidos, true)) {

					if (in_array($password2[$i], $str_mayusculas, true)) {
						$mayusculas++;
					} elseif (in_array($password2[$i], $str_minusculas, true)) {
						$minusculas++;
					} elseif (in_array($password2[$i], $str_numeros, true)) {
						$numeros++;
					} elseif (in_array($password2[$i], $str_especiales, true)) {
						$especiales++;
					}

					$validos++;
				} else {
					$no_validos++;
				}
			}


			if ((strlen($password2)) < 8) {
				header("Location: ../views/act_pass/?stat=me4");
				die();
			} elseif ($mayusculas < 1) {
				header("Location: ../views/act_pass/?stat==me5");
				die();
			} elseif ($minusculas < 1) {
				header("Location: ../views/act_pass/?stat=me6");
				die();
			} elseif ($numeros < 1) {
				header("Location: ../views/act_pass/?stat=me7");
				die();
			} elseif ($especiales < 1) {
				header("Location: ../views/act_pass/?stat=me8");
				die();
			} elseif ($no_validos >= 1) {
				header("Location: ../views/act_pass/?stat=me0");
				die();
			}


			@$salt = '$DYhG93b0qyJfIxfs2guVoUubWwvniR2G0FgaC9mi$/';
			@$contraseña1 = md5($salt . $password1);
			@$contraseña2 = md5($salt . $password2);

			$valor = strcmp($contraseña1, $contraseña2);

			if ($valor != 0) {
				header("Location: ../views/act_pass/?stat=me2");
				die();
			} else {


				$hoy = date("d-m-Y");
				session_start();
				$usuario_id = $_SESSION['idusuariox'];

				$usr = Musuario::find($usuario_id);
				$usr->password = $contraseña2;
				$usr->user_modify = $usuario_id;
				$usr->updated = $hoy;
				$usr->act_clave = 1;
				// $usr->act_email = 1;

				if ($usr->save()) {

					if ($_SESSION['rolx'] == 1 || $_SESSION['rolx'] == 4) { //ingresa como soporte usuario o programador

						header("Location: ../views/inicio/");
					} elseif ($_SESSION['rolx'] == 2) { //entra como administrador

						header("Location: ../views/inicio/");
					} elseif ($_SESSION['rolx'] == 3) { //entra como usuario

						header("Location: ../views/inicio");
					}
				} else {

					header("Location: ../views/act_pass/?stat=me3");
					die();
				}
			}
		} //end else
		break;
} //end switch
function getLocationInfoByIp($ip_addr)
{
	// Compatibilidad: ahora delega en el helper (con caché y timeout).
	$geo = bovs_geo_localizar($ip_addr);
	$txt = bovs_geo_etiqueta($geo['pais'], $geo['ciudad']);
	return $txt !== '' ? $txt : null;
}