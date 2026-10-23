<?php
class MmigracionAnimale extends ActiveRecord\Model {
    static $table_name = 'mmigraciones_animales';

    static $belongs_to = array(
        array('manimal_origen',  'class_name' => 'Manimale', 'foreign_key' => 'manimal_origen_id'),
        array('manimal_destino', 'class_name' => 'Manimale', 'foreign_key' => 'manimal_destino_id'),
        array('fundo_origen',    'class_name' => 'Mfundo',   'foreign_key' => 'mfundos_origen_id'),
        array('fundo_destino',   'class_name' => 'Mfundo',   'foreign_key' => 'mfundos_destino_id'),
    );
}