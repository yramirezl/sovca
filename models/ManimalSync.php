<?php
require_once '../config/conexion.php';

/**
 * Modelo EXCLUSIVO para sync_controller.php (crear/editar animales
 * offline). Apunta a 'manimales', que es la tabla real con todos los
 * campos de campos_editables en SyncModulos.php — a diferencia de
 * Manimale.php (que apunta a 'manimalxfundos', una tabla de relación
 * animal×fundo con muchas menos columnas).
 *
 * Se creó como clase separada, en vez de corregir Manimale.php
 * directamente, para no arriesgar romper algo que el resto del
 * sistema (fuera de esta API) pueda estar esperando de ese modelo
 * compartido. Pendiente de confirmar con el equipo si la app web
 * original también inserta una fila en manimalxfundos al registrar un
 * animal — si es así, crear_registro()/actualizar_registro() en
 * sync_controller.php necesitan replicar eso, no solo escribir acá.
 */
class ManimalSync extends ActiveRecord\Model {
    static $table_name = 'manimales';
}
?>
