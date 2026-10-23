<?php
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

/* ------------------------------------------------------------------
   Antes de destruir la sesión, persistimos el último fundo activo
   del usuario para restaurarlo automáticamente en el próximo login.
   ------------------------------------------------------------------ */
$__uid = isset($_SESSION['idusuariox']) ? (int) $_SESSION['idusuariox'] : 0;
$__fid = isset($_SESSION['mfundos_id']) ? (int) $_SESSION['mfundos_id'] : 0;

if ($__uid > 0 && $__fid > 0) {
	// El CWD debe ser el directorio de este script (views/) porque
	// config/conexion.php usa rutas relativas internas ('../models/', etc).
	@chdir(__DIR__);
	@include_once __DIR__ . '/../config/conexion.php';
	@include_once __DIR__ . '/../lib/multifundo_helper.php';
	if (function_exists('mf_guardar_ultimo_fundo')) {
		@mf_guardar_ultimo_fundo($__uid, $__fid);
	}
}

$_SESSION['idusuario'] = null;
$_SESSION = array();
@session_unset();

/* Redirección relativa a este script: funciona en cualquier subcarpeta
   (/sovca, /bovs o dominio raíz) sin hardcodear la ruta del sistema. */
header('Location: login');
exit;
?>
