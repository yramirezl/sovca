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
			$this->bd = new conexionAdo();

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