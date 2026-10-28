<?php
require_once '../config/conexion.php';
class Mplane extends ActiveRecord\Model{

    /**
     * La tabla mplanes no sigue la convención de nombres de php-activerecord:
     * su clave usa la secuencia `mplanes_seq_id_seq` (no `mplanes_id_seq`).
     * Sin esta línea el ORM falla al insertar con
     * «relation "mplanes_id_seq" does not exist», que es lo que pasaba al
     * crear un plan desde el módulo Planes.
     */
    static $sequence = 'mplanes_seq_id_seq';

}
?>