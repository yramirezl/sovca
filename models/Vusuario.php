<?php
require_once '../config/conexion.php';
class Vusuario extends ActiveRecord\Model{
	// 'vusuarios' es una VISTA (prefijo 'v'), y las vistas en Postgres
	// no tienen clave primaria declarada — sin esto, ActiveRecord no
	// puede armar el WHERE de find($id) (la forma corta) y genera SQL
	// inválido ("un identificador delimitado tiene largo cero").
	// find('all'/'first', ['conditions' => [...]]) con condiciones
	// explícitas (como ya usa musuarios_controller.php) no necesita
	// esto, pero auth_controller.php y catalogos_controller.php sí
	// usan la forma corta find($id), así que hace falta declararla.
	static $primary_key = 'id';




}
?>
