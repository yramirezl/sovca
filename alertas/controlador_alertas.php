<?php
require_once 'conexion.php';
date_default_timezone_set('America/Caracas');

$num = "";
$con = new conexion();

if (!$con->CONNECT()) {

  $con->DISCONECT();
  unset($con);
  return -1;
} else {


  // BORRAMOS LAS ALERTAS EXIXITENTES Y REINICIAMOS LA SECUENCIA

  $sql_0 = "delete from malertas; ";
  if (!$rs =   $con->SQL_QUERY($sql_0)) {
    $con->ROLLBACK();
  }

  // AGREGANDO ALERTAS PARA PERIODO  SECADO

  $sql_1 = "INSERT INTO malertas (
              mtipoalertas_id, evento, codigo_animal, nro_arete, nombre,
              f_estimada, dias, meses, mfundos_id
            )
            SELECT 1, 'SECADO', secados.codigo_animal, secados.nro_arete,
                   secados.nombre, secados.f_estimada, secados.dias,
                   secados.meses, secados.mfundos_id
              FROM (
                SELECT b.codigo_rgb_id_animal AS codigo_animal,
                       b.nro_arete, b.nombre, s.f_secado::date AS f_estimada,
                       (s.f_secado::date - CURRENT_DATE)::smallint AS dias,
                       FLOOR((s.f_secado::date - CURRENT_DATE) / 30.4375)::smallint AS meses,
                       s.mfundos_id
                  FROM public.mregservicios s
                  JOIN public.manimales b
                    ON b.mfundos_id = s.mfundos_id
                   AND b.codigo_rgb_id_animal = s.codigo_rgb_id_animal_hembra
                 WHERE s.status = 1
                   AND s.f_secado IS NOT NULL
                   AND s.medofisiologicos_id = 1
                   AND b.status = 1
                   AND b.mestatus_id = 1
                   AND b.aptitud IN (2, 3)
                   AND s.f_secado::date BETWEEN CURRENT_DATE AND CURRENT_DATE + 21
                UNION
                SELECT b.codigo_rgb_id_animal AS codigo_animal,
                       b.nro_arete, b.nombre,
                       (n.f_parto::date + periodo.dias_gestacion)::date AS f_estimada,
                       (n.f_parto::date + periodo.dias_gestacion - CURRENT_DATE)::smallint AS dias,
                       FLOOR((n.f_parto::date + periodo.dias_gestacion - CURRENT_DATE) / 30.4375)::smallint AS meses,
                       n.mfundos_id
                  FROM public.mregnacimientos n
                  JOIN public.manimales b
                    ON b.mfundos_id = n.mfundos_id
                   AND b.codigo_rgb_id_animal = n.codigo_rgb_id_animal_hembra
                  JOIN LATERAL (
                       SELECT p.dias_gestacion
                         FROM public.mperiodos p
                        WHERE p.status = 1
                          AND p.mtipoganaderias_id = n.mtipoganaderias_id
                          AND p.codper_bov = 'PPL'
                          AND p.mfundos_id IN (n.mfundos_id, 0)
                     ORDER BY CASE WHEN p.mfundos_id = n.mfundos_id THEN 0 ELSE 1 END, p.id DESC
                        LIMIT 1
                  ) periodo ON periodo.dias_gestacion > 0
                 WHERE n.status = 1
                   AND n.mestatus_id = 1
                   AND n.produccion_leche = 1
                   AND n.proposito = 2
                   AND n.f_parto IS NOT NULL
                   AND b.status = 1
                   AND b.mestatus_id = 1
                   AND (EXISTS (
                         SELECT 1 FROM public.mternerosxnacimientos t
                          WHERE t.mregnacimientos_id = n.id
                            AND t.status = 1 AND t.estatus_nacido = 1
                       ) OR (
                         n.estatus_nacido = 1
                         AND NOT EXISTS (
                           SELECT 1 FROM public.mternerosxnacimientos t
                            WHERE t.mregnacimientos_id = n.id AND t.status = 1
                         )
                       ))
                   AND (n.f_parto::date + periodo.dias_gestacion) BETWEEN CURRENT_DATE AND CURRENT_DATE + 21
              ) AS secados;";
  if (!$rs =   $con->SQL_QUERY($sql_1)) {
    $con->ROLLBACK();
  }

    // AGREGANDO ALERTAS PARA PERIODO  DESTETE

    $sql_1_1 = "INSERT INTO malertas (
                  mtipoalertas_id, evento, codigo_animal, nro_arete, nombre,
                  f_estimada, dias, meses, mfundos_id
                )
                SELECT 1, 'DESTETE', b.codigo_rgb_id_animal, b.nro_arete,
                       b.nombre,
                       (n.f_parto::date + periodo.dias_gestacion)::date,
                       (n.f_parto::date + periodo.dias_gestacion - CURRENT_DATE)::smallint,
                       FLOOR((n.f_parto::date + periodo.dias_gestacion - CURRENT_DATE) / 30.4375)::smallint,
                       n.mfundos_id
                  FROM public.mregnacimientos n
                  JOIN public.manimales b
                    ON b.mfundos_id = n.mfundos_id
                   AND b.codigo_rgb_id_animal = n.codigo_rgb_id_animal_hembra
                  JOIN LATERAL (
                       SELECT p.dias_gestacion
                         FROM public.mperiodos p
                        WHERE p.status = 1
                          AND p.mtipoganaderias_id = n.mtipoganaderias_id
                          AND p.codper_bov = 'PBL'
                          AND p.mfundos_id IN (n.mfundos_id, 0)
                     ORDER BY CASE WHEN p.mfundos_id = n.mfundos_id THEN 0 ELSE 1 END, p.id DESC
                        LIMIT 1
                  ) periodo ON periodo.dias_gestacion > 0
                 WHERE n.status = 1
                   AND n.mestatus_id = 1
                   AND n.produccion_leche = 1
                   AND n.proposito = 1
                   AND n.f_parto IS NOT NULL
                   AND b.status = 1
                   AND b.mestatus_id = 1
                   AND (EXISTS (
                         SELECT 1 FROM public.mternerosxnacimientos t
                          WHERE t.mregnacimientos_id = n.id
                            AND t.status = 1 AND t.estatus_nacido = 1
                       ) OR (
                         n.estatus_nacido = 1
                         AND NOT EXISTS (
                           SELECT 1 FROM public.mternerosxnacimientos t
                            WHERE t.mregnacimientos_id = n.id AND t.status = 1
                         )
                       ))
                   AND (n.f_parto::date + periodo.dias_gestacion) BETWEEN CURRENT_DATE AND CURRENT_DATE + 21;";
if (!$rs =   $con->SQL_QUERY($sql_1_1)) {
$con->ROLLBACK();
}


  // AGREGANDO ALERTAS PARA PERIODO DE CELO

  $sql_2 = "insert into malertas (mtipoalertas_id,evento, codigo_animal, nro_arete, nombre, f_estimada, dias, meses, mfundos_id)
                             select 3,motivo,cod_animal,nro_arete,nombre,f_servicio,dias,meses,mfundos_id FROM v_pericelo_alertas;";
  if (!$rs =   $con->SQL_QUERY($sql_2)) {
    $con->ROLLBACK();
  }

  // AGREGANDO ALERTAS PARA PERIODO DE PALPACION

  $sql_3 = "insert into malertas (mtipoalertas_id,evento, codigo_animal, nro_arete, nombre, f_estimada, dias, meses, mfundos_id)
                                select 2,motivo,cod_animal,nro_arete,nombre,f_probable_palpacion,dias,meses,mfundos_id FROM v_palpacion_alertas;";
  if (!$rs =   $con->SQL_QUERY($sql_3)) {
    $con->ROLLBACK();
  }
  // AGREGANDO ALERTAS PARA PERIODO DE GESTACION

  $sql_4 = "insert into malertas (mtipoalertas_id,evento, codigo_animal, nro_arete, nombre, f_estimada, dias, meses, mfundos_id)
                  select 4,motivo,cod_animal,nro_arete,nombre,f_probable_parto,dias,meses,mfundos_id 
                FROM v_parir_alertas";
  if (!$rs =   $con->SQL_QUERY($sql_4)) {
    $con->ROLLBACK();
  }
  // AGREGANDO ALERTAS PARA CONTROL SANITARIO

  $sql_5 = "insert into malertas (mtipoalertas_id,evento, codigo_animal, nro_arete, nombre, f_estimada, dias, meses, mfundos_id)
 select 5,motivo,cod_animal,nro_arete,nombre,fecha_prox_control,dias,meses,mfundos_id 
FROM v_controlsanitario_alertas";
  if (!$rs =   $con->SQL_QUERY($sql_5)) {
    $con->ROLLBACK();
  }

  // Restauramos la lectura segun el ultimo correo enviado para cada alerta.
  $sql_6 = "UPDATE public.malertas AS alerta
            SET vista = estado.ultima_visualizacion IS NOT NULL
                        AND (estado.ultimo_envio IS NULL
                             OR estado.ultima_visualizacion >= estado.ultimo_envio)
            FROM public.malertas_email_estado AS estado
            WHERE alerta.mfundos_id = estado.mfundos_id
              AND alerta.mtipoalertas_id = estado.mtipoalertas_id
              AND alerta.codigo_animal = estado.codigo_animal
              AND alerta.evento = estado.evento
              AND alerta.f_estimada::date = estado.f_estimada;";
  if (!$rs = $con->SQL_QUERY($sql_6)) {
    $con->ROLLBACK();
  }

  $con->DISCONECT();
  unset($con);
}
