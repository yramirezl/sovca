<?php
require_once '../config/conexion.php';
class Manimale extends ActiveRecord\Model{
	// static $table_name = 'manimalxfundos';

public function getCatalogoPublico($tipo) {
    switch ($tipo) {
        case 'razas':      return $this->catalogo_razas;
        case 'aptitudes':  return $this->catalogo_aptitudes;
        case 'categorias': return $this->catalogo_categorias;
        case 'lotes':      return $this->catalogo_lotes;
        case 'servicios':  return $this->catalogo_servicios;
    }
    return array();
}

}

?>