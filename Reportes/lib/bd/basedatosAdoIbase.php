<?php	
        @require_once('../../adodb/adodb.inc.php');
        @require_once("conexionAdo.php");
        @require_once("baseClases.class.php");
  
	class basedatosAdoIbase
	{
		var $conn;
		var $bd;		

		/* Constructor dual: PHP 5.3.3 → 8.3 */
		function __construct()
		{
			$this->basedatosAdoIbase();
		}

		function basedatosAdoIbase()
		{
                        /* =====================================================
                           MODO MANTENIMIENTO
                           -----------------------------------------------------
                           Los reportes usan su PROPIA conexión (ADOdb), así que no
                           pasan por config/conexion.php. El control se hace aquí,
                           antes de conectar, para que un productor o ayudante no
                           pueda generar un PDF durante la ventana de mantenimiento.
                           ======================================================== */
                        @require_once(__DIR__ . '/../../../lib/mantenimiento_helper.php');
                        if (function_exists('bovs_mantenimiento_activo')
                                && bovs_mantenimiento_activo()
                                && !empty($_SESSION['idusuariox'])
                                && !bovs_mantenimiento_permite_rol(isset($_SESSION['rolx']) ? (int) $_SESSION['rolx'] : 0)) {
                                if (!headers_sent()) {
                                        http_response_code(403);
                                        header('Content-Type: text/html; charset=UTF-8');
                                }
                                echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Sistema en mantenimiento</title></head>'
                                        . '<body style="font-family:Arial,Helvetica,sans-serif;background:#fff7ed;color:#7f1d1d;padding:40px;text-align:center;">'
                                        . '<h2 style="margin:0 0 10px;">Sistema en mantenimiento</h2>'
                                        . '<p style="margin:0;">' . htmlspecialchars(bovs_mantenimiento_mensaje(), ENT_QUOTES, 'UTF-8') . '</p>'
                                        . '<script>setTimeout(function(){ window.close(); }, 8000);</script>'
                                        . '</body></html>';
                                exit;
                        }

			/* -----------------------------------------------------------------
			   BASE DE DATOS DE LOS REPORTES
			   -----------------------------------------------------------------
			   Los reportes usan ADOdb y no pasan por config/conexion.php,
			   así que la base se define aquí. Debe ser la misma que usa la
			   aplicación (sovca_qas); si se cambia en un despliegue, hay que
			   cambiar también este valor.
			   ----------------------------------------------------------------- */
			$host     = '127.0.0.1';
			$port     = '5432';
			$dbname   = 'sovca_qas';
			$user     = 'postgres';
			$password = 'postgres';

			$this->conn = $this->bd->conectar("postgres", $host, $user, $password, $port, $dbname);
		}

		function select($sql)
		{
			$this->conn->Execute('SET search_path TO "public"');
			$rs = $this->conn->Execute($sql);
			return $rs;
		}
		
		function selectp($sql)
		{
			$this->conn->Execute('SET search_path TO "public"');
			$rs = $this->conn->Execute($sql);
			return $rs;
		}
		
		function actualizar($sql)
		{
			$this->conn->Execute('SET search_path TO "public"');
			$this->conn->Execute($sql);
		}

		/* Defensivo: no falla si la conexión es null */
		function closed()
		{
			if ($this->conn) {
				$this->conn->Close();
			}
		}
	}
?>