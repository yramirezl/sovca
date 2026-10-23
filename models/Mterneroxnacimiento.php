<?php
require_once '../config/conexion.php';

class Mterneroxnacimiento extends ActiveRecord\Model
{
    static $table_name = 'mternerosxnacimientos';
    static $sequence = 'mternerosxnacimientos_id_seq';
}