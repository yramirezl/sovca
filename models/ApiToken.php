<?php
require_once '../config/conexion.php';

class ApiToken extends ActiveRecord\Model
{
    static $table_name = 'api_tokens';
}
