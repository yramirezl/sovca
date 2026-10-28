<?php

// Credenciales centralizadas (host, puerto, base, usuario y clave).
require_once __DIR__ . '/../config/db.php';

// if (!isset($_POST)) die ("No Puede Acceder Directamente");
//---------------------------------------------------------------------------------------------------------------
//---------------------------------------------------------------------------------------------------------------
class conexion
{
private	$host;
private	$database;
private	$login;
private	$password;
private	$port;
private	$sistema;
var	$obj_conec;

	/* ------------------------------------------------------------
	   Conexión compartida del proceso.
	   pg_connect() ya reutiliza el recurso para la misma cadena de
	   conexión, pero aquí se guarda explícitamente para:
	     · cerrarla UNA sola vez (y no con pg_close() sin recurso),
	     · no dejar sin conexión a otras instancias del mismo script.
	   ------------------------------------------------------------ */
	private static $recurso_compartido = null;
	private static $usos = 0;
	private static $cierre_registrado = false;
	private static $creadas = 0;
	private static $reutilizadas = 0;
	private $conectado = false;

		//---------------------------------------------------------------------------------------------------------------
		//---------------------------------------------------------------------------------------------------------------
		/* Compatibilidad: en PHP 8 el método con el mismo nombre de la clase
		   dejó de ser constructor, por lo que `new conexion()` no inicializaba
		   host/usuario/clave y la conexión fallaba. Se llama explícitamente y
		   se mantiene el método original para entornos antiguos. */
		function __construct(){

			$this->conexion();

		}///FIN DEL CONSTRUCTOR

		//---------------------------------------------------------------------------------------------------------------
		//---------------------------------------------------------------------------------------------------------------
		function conexion(){

			/* Solo hay dos bases de negocio: sovca y sovca_demo. */
			$cfg = sovca_db_config(SOVCA_DB_NAME);

			$this->host     = $cfg['host'];
			$this->database = $cfg['base'];
			$this->login    = $cfg['user'];
			$this->password = $cfg['pass'];
			$this->port     = $cfg['port'];
			$this->sistema  = "SOVCA";

		}///FIN DE LA FUNCTION
		//---------------------------------------------------------------------------------------------------------------
		//---------------------------------------------------------------------------------------------------------------
		function CONNECT(){

			/* Misma instancia ya conectada */
			if ($this->conectado && $this->obj_conec) {
				return $this->obj_conec;
			}

			/* Otra instancia del script ya abrió la conexión: se reutiliza */
			if (self::$recurso_compartido) {
				$this->obj_conec = self::$recurso_compartido;
				$this->conectado = true;
				self::$usos++;
				self::$reutilizadas++;
				return $this->obj_conec;
			}

			$Coneccion = @pg_connect("host=".$this->host." port=".$this->port." dbname=".$this->database." user=".$this->login."  password=".$this->password." ");

			if (!$Coneccion) {
				return false;
			}// fin del if------

			self::$recurso_compartido = $Coneccion;
			self::$creadas++;
			self::$usos = 1;
			$this->obj_conec = $Coneccion;
			$this->conectado = true;

			/* Red de seguridad: si el script termina sin DISCONECT() */
			if (!self::$cierre_registrado) {
				self::$cierre_registrado = true;
				register_shutdown_function(array('conexion', 'CERRAR_COMPARTIDA'));
			}

			return $this->obj_conec;
		}// fin de la funcion------
		//---------------------------------------------------------------------------------------------------------------
		//---------------------------------------------------------------------------------------------------------------
		function SQL_QUERY($sql){
		
			if (!$this->obj_conec) {
				return false;
			}
			$rs=@pg_query($this->obj_conec, $sql);
			return $rs;
		
		}// fin de la funcion------------
		//---------------------------------------------------------------------------------------------------------------
		//---------------------------------------------------------------------------------------------------------------
		function BEGIN_TRANSAC(){
			
			if (!$this->obj_conec) {
				return false;
			}
			$rs=@pg_query($this->obj_conec, "BEGIN");
			return $rs;
		
		}// fin de la funcion------------
		//---------------------------------------------------------------------------------------------------------------
		//---------------------------------------------------------------------------------------------------------------
		function COMMIT(){
			
			if (!$this->obj_conec) {
				return false;
			}
			$rs=@pg_query($this->obj_conec, "COMMIT");
			return $rs;
		
		}// fin de la funcion------------
		//---------------------------------------------------------------------------------------------------------------
		//---------------------------------------------------------------------------------------------------------------
		function ROLLBACK(){
			
			if (!$this->obj_conec) {
				return false;
			}
			$rs=@pg_query($this->obj_conec, "ROLLBACK");
			return $rs;
		
		}// fin de la funcion------------
		//---------------------------------------------------------------------------------------------------------------
		//---------------------------------------------------------------------------------------------------------------
		function DISCONECT() {

			if (!$this->conectado) {
				/* Nunca se conectó (o ya se desconectó): no se cierra nada.
				   Antes se llamaba pg_close() sin recurso y en PHP 8 eso
				   lanza "No PostgreSQL connection opened yet". */
				return;
			}

			$this->conectado = false;
			$this->obj_conec = null;

			if (self::$usos > 0) {
				self::$usos--;
			}

			if (self::$usos === 0) {
				self::CERRAR_COMPARTIDA();
			}
		}// fin de la funcion------------

		/**
		 * Cierra la conexión compartida del script (idempotente).
		 * También se ejecuta al terminar si nadie llamó DISCONECT().
		 */
		public static function CERRAR_COMPARTIDA() {

			if (self::$recurso_compartido) {
				@pg_close(self::$recurso_compartido);
				self::$recurso_compartido = null;
			}
			self::$usos = 0;
		}// fin de la funcion------------

		/**
		 * Diagnóstico: conexiones reales abiertas por el proceso y
		 * cuántas instancias reutilizaron la misma.
		 */
		public static function ESTADISTICAS() {

			return array(
				'creadas'      => self::$creadas,
				'reutilizadas' => self::$reutilizadas,
				'abierta'      => self::$recurso_compartido ? 1 : 0,
			);
		}// fin de la funcion------------

		function __destruct() {

			$this->DISCONECT();
		}// fin de la funcion------------
		 //---------------------------------------------------------------------------------------------------------------
		//---------------------------------------------------------------------------------------------------------------
		function FREE_RESULT($rs){
		
			$rs=@pg_free_result($rs);
			return $rs;
		
		}// fin de la funcion------------
		//---------------------------------------------------------------------------------------------------------------
		//---------------------------------------------------------------------------------------------------------------
		private function  GET_ID_SISTEMA()
		{
			$sql="select id from seguridad.segsistemas where strnombre='".$this->sistema."' ";
			$rs=$this->SQL_QUERY($sql);
			$arr=pg_fetch_array($rs);
			$id=$arr["id"];
			$this->FREE_RESULT($rs);
			return $id;
			
		}
		//---------------------------------------------------------------------------------------------------------------
		//---------------------------------------------------------------------------------------------------------------
		function GET_SISTEMA(){
			
			return $this->GET_ID_SISTEMA();
		}
		//---------------------------------------------------------------------------------------------------------------
		//---------------------------------------------------------------------------------------------------------------

}// fin de la clase-----------------------------------------------------------------------------------------------------
?>
