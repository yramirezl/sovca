<?php
date_default_timezone_set('America/Caracas');
if (session_status() === PHP_SESSION_NONE) {
      session_start(array('read_and_close' => true));
}

      $rol_sesion = (int)(isset($_SESSION['rolx']) ? $_SESSION['rolx'] : 0);
      $requiere_verificacion = in_array($rol_sesion, array(2, 3), true) && empty($_SESSION['suscripcion_verificada']);
      $suscripcion_vencida = !empty($_SESSION['fecha_caduca_suscripcion']) && $_SESSION['fecha_caduca_suscripcion'] <= date('Y-m-d');
// Acciones que SIEMPRE deben responder, incluso con la suscripción vencida:
// iniciar sesión y recuperar la contraseña (si no, el usuario no tendría forma
// de volver a entrar).
$acciones_publicas = array('login', 'solicitar_reset', 'aplicar_reset');
$accion_post = isset($_POST['action']) ? (string) $_POST['action'] : '';
if (($requiere_verificacion || $suscripcion_vencida) && !in_array($accion_post, $acciones_publicas, true)) {
      http_response_code(403);
      header('Content-Type: application/json; charset=utf-8');
      $error = $suscripcion_vencida ? 'suscripcion_vencida' : 'suscripcion_revalidar';
      $mensaje = $suscripcion_vencida ? 'La suscripción venció. Solicita al administrador que renueve la cuenta.' : 'Inicia sesión nuevamente para verificar la vigencia de tu cuenta.';
      echo json_encode(array('ok' => false, 'error' => $error, 'msg' => $mensaje));
      exit;
}

function sanitize($input) {

      $textoLimpio = preg_replace('([^A-Za-z0-9])', '', $input);
      return $textoLimpio;

}
/* *******  ejemplo de uso***************
$_GET = sanitize($_GET);
$_POST = sanitize($_POST);
$cadena_final = sanitize($cadena_original);
* 
* */
?>
