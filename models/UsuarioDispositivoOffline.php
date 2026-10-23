<?php
require_once '../config/conexion.php';

/**
 * Registro de dispositivos que activaron el modo offline para un
 * usuario/fundo. Un usuario solo puede tener UN dispositivo activo
 * (status=1) por fundo a la vez — ver el índice único parcial
 * usuario_dispositivo_offline_unico_activo_idx en la migración.
 */
class UsuarioDispositivoOffline extends ActiveRecord\Model {
    static $table_name = 'usuario_dispositivo_offline';
}
?>