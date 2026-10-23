<?php
require_once '../models/Vregservicio.php';
session_start();
$mfundos_id = $_SESSION['mfundos_id'];
$result = array();
$items = 0;
if (($mfundos_id != '') && ($mfundos_id != ' ')) {
	$data = Vregservicio::find_by_sql("select v.id, v.codigo_rgb_id_animal_hembra, v.codigo_servicio, v.f_servicio, v.fecha_parto,
											v.nombre_h, v.nombre_m,
											v.estado_fisiologico,
											case when (v.medofisiologicos_id=1 and v.parto=0)
													then 'Gestación Activa'
												when (v.medofisiologicos_id=1 and v.parto=1 and 
													date_part('month', age(now(),date(v.fecha_parto))) < 1.5 )
													then 'Periodo de Descanso'
												when (v.medofisiologicos_id=1 and v.parto=1 and 
													date_part('month', age(now(),date(v.fecha_parto))) >= 1.5 )
													then 'Disponible para Servico'	  
												when (v.medofisiologicos_id in (2,3) and v.parto=0)
													then 'Servicio Activo'	
												when (v.medofisiologicos_id in (4,5) and v.parto=0)
													then 'Disponible para Servico' end  condicion,
												case when v.estado_servicio=1 then 'ABIERTO'
													when v.estado_servicio=2 then 'CERRADO' end estadoservicio,
													v.estado_servicio, v.medofisiologicos_id, v.parto, v.nro_arete, v.estatus_ciclo, v.nro_servicio,
													v.estatus_animal,
													coalesce(sg.nombre, '') as estatus_animal_nombre
												from Vregservicios v
												left join mestatusganados sg on sg.id = v.estatus_animal
												where  v.mfundos_id=" . $mfundos_id . " and v.estatus_animal>0
												order by v.f_servicio desc nulls last, v.id desc");

	/*
	 * Máximo id por hembra en UNA sola consulta.
	 *
	 * Antes se lanzaba una consulta por cada fila (N+1): con 88 servicios
	 * eran 88 viajes extra a la base. El resultado es el mismo, pero ahora
	 * se resuelve con una única consulta agregada.
	 */
	$maximo_por_hembra = array();
	$data_control = Vregservicio::find_by_sql("select codigo_rgb_id_animal_hembra as codigo, max(id) as maximo
											   from Vregservicios											  where mfundos_id=" . (int)$mfundos_id . "											  group by codigo_rgb_id_animal_hembra;");
	if ($data_control) {
		foreach ($data_control as $rs_control) {
			$maximo_por_hembra[(string)$rs_control->codigo] = (int)$rs_control->maximo;
		}
	}

	foreach ($data as $rs) {

		$f_servicio ='';
		$f_servicio_orden = '';
		if ($rs->f_servicio != Null) {
               $f_servicio = (string)$rs->f_servicio->format('d-m-Y');
               // Fecha en formato ISO: permite ordenar por fecha real
               // (la versión d-m-Y se ordena como texto y da un orden falso).
               $f_servicio_orden = (string)$rs->f_servicio->format('Y-m-d');

		}else{
            $f_servicio ='No Registrada';

		}


		$codigo_hembra = (string)$rs->codigo_rgb_id_animal_hembra;
		$maximo = isset($maximo_por_hembra[$codigo_hembra]) ? $maximo_por_hembra[$codigo_hembra] : 0;

		$condicion = $rs->condicion;
		if ($rs->estado_servicio == 2) {

			if ($rs->estatus_ciclo == 2) {

				if ($rs->id == $maximo) {

					if ($fecha_parto != Null) {
						$fecha_parto = $rs->fecha_parto;
						$f_parto = (string)$fecha_parto->format('Y-m-d');
						$tiempo = tiempo_t($f_parto);

						if ($tiempo <= 1) {
							$condicion = "[* " . $rs->condicion . " *]";
						}
						if ($tiempo <= 3) {
							$condicion = "[* Disponible Para Servicio *]";
						} else {
							$condicion = "[* No Aplica *]";
						}
					}
				} else {
					$condicion = "[* No Aplica *]";
				}
			} else {
				if ($rs->id == $maximo) {

					$condicion = $condicion;

				} else {
					$condicion = "[* No Aplica *]";
				}
			}
		}

		$estado_fisiologico = $rs->estado_fisiologico;

		if ($rs->estado_servicio == 2 && $rs->parto == 1) {
			if ($rs->medofisiologicos_id == 1) {

				$estado_fisiologico = $rs->estado_fisiologico . " [ Pario ]";
			}
		}

		/*
		 * Estatus reproductivo VIGENTE.
		 *
		 * Una hembra tiene un único estatus reproductivo actual: el de su último
		 * registro (max id). Los servicios anteriores pertenecen a ciclos ya
		 * cerrados y no deben mostrar estados "vivos" como "Disponible para
		 * Servicio", porque eso hace creer que la hembra sigue disponible cuando
		 * en realidad volvió a recibir servicio, parió o se le cerró el ciclo.
		 */
		$es_vigente = ((int)$rs->id === (int)$maximo) ? 1 : 0;

		if (!$es_vigente) {
			$condicion = 'Ciclo cerrado';
		}

		/*
		 * Misma regla de edición que aplica el formulario
		 * (controllers/mservicios_controller.php, case 'search' → $bloquear).
		 *
		 * Un servicio solo se puede editar si sigue ABIERTO, su ciclo no está
		 * cerrado y el estado fisiológico es "Servicio Activo" (3). Si cambia
		 * en el controlador, hay que cambiar también aquí.
		 */
		$editable = (
			(int)$rs->estado_servicio !== 2
			&& (int)$rs->estatus_ciclo !== 2
			&& (int)$rs->medofisiologicos_id === 3
		) ? 1 : 0;


		array_push($result, array(
			"codigo_servicio" => $rs->codigo_servicio,
			"codigo_rgb_id_animal_hembra" => $rs->codigo_rgb_id_animal_hembra,
			"nombre_h" => $rs->nombre_h,
			"f_servicio" => $f_servicio,
			"f_servicio_orden" => $f_servicio_orden,
			"estado_fisiologico" => $estado_fisiologico,
			"condicion" => $condicion,
			"nombre_m" => $rs->nombre_m,
			"estatus_servicio" => $rs->estadoservicio,
			"nro_arete" => $rs->nro_arete,
		    "nro_servicio" => $rs->nro_servicio,
			"estatus_animal" => (int)$rs->estatus_animal,
			"estatus_animal_nombre" => (string)$rs->estatus_animal_nombre,
			"vigente" => $es_vigente,
			"editable" => $editable,
			"id" => $rs->id

		));
		$items++;
	}
}

print_r(json_encode($result));

function tiempo_t($fecha_nac)
{
	$fecha_f = date('Y-m-d');
	$fecha_i = $fecha_nac;
	$dias = (strtotime($fecha_i) - strtotime($fecha_f)) / 86400;
	$dias = abs($dias);
	$dias = floor($dias);
	$meses = ($dias / 30);
	$meses = (int)$meses;

	return $meses;
}
