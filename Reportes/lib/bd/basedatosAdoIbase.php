<?php	
        @require_once('../../adodb/adodb.inc.php');
        @require_once("conexionAdo.php");
        @require_once("baseClases.class.php");
        @require_once(__DIR__ . '/../../../config/db.php');
  
	class basedatosAdoIbase
	{
		var $conn;
		var $bd;

		/* ------------------------------------------------------------
		   Conexión única compartida por todos los objetos del script.
		   Antes cada instancia (baseClases, Cabecera, contexto) pedía
		   su propia conexión ADOdb. Ahora se reutiliza una sola y se
		   cierra al terminar el request.
		   ------------------------------------------------------------ */
		private static $conn_compartida = null;
		private static $creadas = 0;
		private static $reutilizadas = 0;
		private static $cierre_registrado = false;

		/* Constructor dual: PHP 5.3.3 → 8.3 */
		function __construct()
		{
			$this->basedatosAdoIbase();
		}

		function basedatosAdoIbase()
		{
			/* ============================================================
			   MODO MANTENIMIENTO
			   ------------------------------------------------------------
			   Los reportes usan su PROPIA conexión (ADOdb), así que no
			   pasan por config/conexion.php. El control se hace aquí,
			   antes de conectar, para que un productor o ayudante no
			   pueda generar un PDF durante la ventana de mantenimiento.
			   ============================================================ */
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

			/* Reutilizar la conexión ya abierta en este script/proceso */
			if (self::$conn_compartida) {
				$this->conn = self::$conn_compartida;
				$this->bd   = null;
				self::$reutilizadas++;
				return;
			}

			$this->bd = new conexionAdo();

			/* Credenciales centralizadas: solo sovca / sovca_demo. */
			$cfg = sovca_db_config(SOVCA_DB_NAME);

			$this->conn = $this->bd->conectar(
				"postgres",
				$cfg['host'],
				$cfg['user'],
				$cfg['pass'],
				$cfg['port'],
				$cfg['base']
			);

			self::$conn_compartida = $this->conn;
			self::$creadas++;

			/* Red de seguridad: cierre al terminar el script */
			if (!self::$cierre_registrado) {
				self::$cierre_registrado = true;
				register_shutdown_function(array('basedatosAdoIbase', 'cerrarCompartida'));
			}
		}

		/**
		 * Cierra la conexión compartida del script (idempotente).
		 */
		public static function cerrarCompartida()
		{
			if (self::$conn_compartida) {
				@self::$conn_compartida->Close();
				self::$conn_compartida = null;
			}
		}

		/**
		 * Diagnóstico: conexiones reales abiertas y reutilizaciones.
		 */
		public static function estadisticas()
		{
			return array(
				'creadas'      => self::$creadas,
				'reutilizadas' => self::$reutilizadas,
				'abierta'      => self::$conn_compartida ? 1 : 0,
			);
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

		/* La conexión ahora es COMPARTIDA: no se cierra desde aquí.
		   Cerrarla desde el destructor de baseClases podía dejar sin
		   conexión a Cabecera o al contexto que aún la necesitaran.
		   El cierre real lo hace cerrarCompartida() al final del script. */
		function closed()
		{
		}
	}
?>