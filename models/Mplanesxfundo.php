<?php
require_once '../config/conexion.php';

class Mplanesxfundo extends ActiveRecord\Model
{
	static $table_name = 'mplanesxfundo';
	static $sequence = 'mplanesxfundo_seq_id_seq';
}
?>