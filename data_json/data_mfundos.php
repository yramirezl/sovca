<?php
require_once '../models/Mfundo.php';
session_start();

	$usuario_id = $_SESSION['idusuariox'];
	$mfundos_id = $_SESSION['mfundos_id'];
	$__rol      = (int)(isset($_SESSION['rolx']) ? $_SESSION['rolx'] : 0);
	$__es_admin = in_array($__rol, array(1, 4), true);

	$result = array();

	if ($__es_admin) {
		// ADMINISTRADOR (1 y 4): TODOS los fundos activos con su propietario,
		// estado de esa cuenta, plan contratado y cuántos animales/usuarios tiene.
		// (Los conteos van en subconsultas agrupadas: 1 sola pasada por tabla.)
		$data = Mfundo::find_by_sql("select f.id, f.nombre, f.cid_rif,
			case when f.mtipoganaderias_id=1 then 'OVINA'
				     when f.mtipoganaderias_id=2 then 'CAPRINA'
			     else 'No Definida' end ganaderia,
			coalesce(u.username,'') as propietario,
			coalesce(u.name,'') as propietario_nombre,
			coalesce(u.status,0) as propietario_estado,
			coalesce(p.nombre,'') as plan,
			coalesce(a.animales,0) as animales,
			coalesce(x.usuarios,0) as usuarios
			  from mfundos f
			  left join musuarios u on u.id = f.user_propietario
			  left join mplanes p on p.id = f.mplanes_id
			  left join (select mfundos_id, count(*) as animales
			               from manimales where status = 1 group by mfundos_id) a
			         on a.mfundos_id = f.id
			  left join (select mfundos_id, count(*) as usuarios
			               from mfundosxusuarios where status = 1 group by mfundos_id) x
			         on x.mfundos_id = f.id
			 where f.status>0
			 order by f.nombre asc;");
	} elseif (($mfundos_id!='')&&($mfundos_id!=' ')){
		$data = Mfundo::find_by_sql("select id,nombre,cid_rif,
		case when mtipoganaderias_id=1
				   then 'BOVINA'
			 when mtipoganaderias_id=2
				   then 'BUFALINA'
				   else 'No Definida' end ganaderia 
				     from mfundos where user_propietario=".$usuario_id." and status>0 order by id desc;");
	} else {
		$data = array();
	}

	$items = 0;
	foreach ($data as $rs) {

		array_push($result,array(   "id"=>$rs->id,
		                            "nombre"=>$rs->nombre,
									"cid_rif"=>$rs->cid_rif,
									"ganaderia"=>$rs->ganaderia,
									"propietario"=>isset($rs->propietario) ? $rs->propietario : "",
									"propietario_nombre"=>isset($rs->propietario_nombre) ? $rs->propietario_nombre : "",
									"propietario_estado"=>isset($rs->propietario_estado) ? (int)$rs->propietario_estado : null,
									"plan"=>isset($rs->plan) ? $rs->plan : "",
									"animales"=>isset($rs->animales) ? (int)$rs->animales : 0,
									"usuarios"=>isset($rs->usuarios) ? (int)$rs->usuarios : 0

		                         ));
		$items++;
	}

	print_r(json_encode($result));
?>