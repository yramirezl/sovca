<?php 
require_once('../../adodb/adodb.inc.php');

class conexionAdo
{
	var $conn;
	var $dsn;

	/* Constructor dual: PHP 5.3.3 → 8.3 */
	function __construct()
	{
		$this->conexionAdo();
	}

	function conexionAdo()
	{
		$this->conn = "";
	}

	function conectar($driver, $server, $user, $pws, $port, $sid)
	{
		if ($driver == 'mysql') {
			$this->conn = &ADONewConnection($driver);
			$this->conn->PConnect($server, $user, $pws, $sid);
		}
		else if ($driver == 'postgres') {
			$this->dsn = $driver . '://' . $user . ':' . $pws . '@' . $server . ':' . $port . '/' . $sid;
			$this->conn = ADONewConnection($this->dsn);
		}
		else if ($driver == 'oci8') {
			$this->conn = ADONewConnection($driver);
			$this->conn->Connect($server, $user, $pws, $sid);
		}
		else if ($driver == 'firebird') {
			$this->dsn2 = $driver . '://' . $user . ':' . $pws . '@' . $server . '/' . $sid . '?persist&dialect=3';
			$this->conn = ADONewConnection($this->dsn2);
		}
		return $this->conn;
	}

	function cerrarConexion()
	{
		$this->conn->Close();
	}
}
?>