<?php
require_once '../config/conexion.php';

/**
 * Modelo explícito para la tabla 'manimalxfundos' (relación N:M entre
 * animales y fundos). Se crea como clase nueva en vez de corregir
 * Manimale.php porque ese archivo podría estar en uso en otros lados
 * del sistema web (con la línea de table_name comentada, por algún
 * motivo histórico). Este modelo es seguro de usar solo en
 * sync_controller.php para replicar la fila de relación que la web
 * original crea al registrar un animal.
 */
class Manimalxfundos extends ActiveRecord\Model {
    static $table_name = 'manimalxfundos';
}