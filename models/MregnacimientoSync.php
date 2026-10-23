<?php
require_once '../config/conexion.php';

/**
 * Modelo EXCLUSIVO para sync_controller.php (crear/editar nacimientos
 * offline). Apunta a 'mregnacimientos', la tabla real con todos los
 * campos de campos_editables en SyncModulos.php — igual criterio que
 * ManimalSync.php: se creó separado del modelo compartido
 * (Mregnacimiento.php) para no arriesgar romper algo que el resto del
 * sistema (fuera de esta API) pueda estar esperando de él.
 */
class MregnacimientoSync extends ActiveRecord\Model {
    static $table_name = 'mregnacimientos';
}
?>
