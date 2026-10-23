<?php
require_once '../models/Mplane.php';

date_default_timezone_set('America/Caracas');

@$action = ($_POST["action"]);
@$id = ($_POST["id_rol"]);
@$id_rol = ($_POST["id_rol"]);
@$record = ($_POST["record"]);
/***********************************/
@$nombre_rol = strtoupper($_POST["nombre_rol"]);
/**********************************/

switch ($action){
  case 'get_t_plan':
    @$data = Mplane::find('all',array('conditions' => array('status=?',1)));

		$resp  = '';
		if (!empty($data)) {
			$resp = '<option value="*">Seleccione el Tipo de Plan</option>';

			foreach ($data as $rs) {
        $nombre=$rs->nombre;
        $max_reg=$rs->max_reg;
        $detalle=$nombre.'  ( Max Reg. '.$max_reg.' )';

				$resp .= '<option value="' . $rs->id . '">' .  $detalle . '</option>';
			}
			echo $resp;
		}
		break;
    #******************************************************************************


}//end switch
?>
