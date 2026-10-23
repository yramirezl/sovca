<?php

// error_reporting(E_ALL);
// ini_set('display_errors', '1');
require_once 'conexion.php';
require_once 'enviar.php';
date_default_timezone_set('America/Caracas');


$num = "";
$con = new conexion();
$enviar_correo = new Enviar_Correo();


if (!$con->CONNECT()) {

  $con->DISCONECT();
  unset($con);
  return -1;
} else {



  $sql_alertas_fundo = "select distinct mfundos_id,email
                        from  v_email_alertas; ";

  
  if (!$rs_fundo =   $con->SQL_QUERY($sql_alertas_fundo)) {
    $con->ROLLBACK();
  } else {

    @$i = 0;


    @$fundo = [];
    @$fundo_resumen = [];

    /* AGRUPACIÓN POR PRODUCTOR (multifundo):
       se acumulan las alertas de todos los fundos por correo de destino y
       se envía UN SOLO mensaje con una sección por fundo.
       Antes: un correo por fundo (un productor con 5 fundos recibía 5). */
    $envios = array();

    /* Modo revisión (solo por línea de comandos): informa sin enviar ni registrar */
    $DRY_RUN  = (PHP_SAPI === 'cli' && isset($argv) && in_array('--dry-run', $argv, true));
    $VER_HTML = (PHP_SAPI === 'cli' && isset($argv) && in_array('--html', $argv, true));



    if (pg_num_rows($rs_fundo) > 0) {


      while ($fila_fundo = pg_fetch_assoc($rs_fundo)) {

        $mfundos_id = $fila_fundo["mfundos_id"];
        $email = $fila_fundo["email"];

        //  array_push($fundo, $email);

        $sql_alertas = "select evento,codigo_animal,nombre_animal,nro_arete,
                   to_char(f_estimada,'DD-MM-YYYY') as f_estimada,
                   f_estimada::date as fecha_estimada,dias
                         from  v_email_alertas where mfundos_id=" . $mfundos_id . "; ";
        if (!$rs_fundo_email =   $con->SQL_QUERY($sql_alertas)) {
          $con->ROLLBACK();
        } else {
          if (pg_num_rows($rs_fundo_email) > 0) {
            $lote = array();
            $estados_lote = array();
            while ($fila_fundo_email = pg_fetch_assoc($rs_fundo_email)) {

              $codigo_animal = $fila_fundo_email["codigo_animal"];
              $nombre_animal = $fila_fundo_email["nombre_animal"];
              $evento = $fila_fundo_email["evento"];
              $f_estimada = $fila_fundo_email["f_estimada"];
              $fecha_estimada = $fila_fundo_email["fecha_estimada"];
              $dias = (int)$fila_fundo_email["dias"];

              if (!$fecha_estimada) {
                continue;
              }

              $tipo_result = pg_query_params($con->obj_conec,
                "SELECT mtipoalertas_id FROM public.malertas
                  WHERE mfundos_id = $1 AND codigo_animal = $2 AND evento = $3
                    AND f_estimada::date = $4::date
                  LIMIT 1",
                array($mfundos_id, $codigo_animal, $evento, $fecha_estimada));
              if (!$tipo_result || pg_num_rows($tipo_result) === 0) {
                continue;
              }
              $tipo_alerta = (int)pg_fetch_result($tipo_result, 0, 'mtipoalertas_id');

              $estado_result = pg_query_params($con->obj_conec,
                "SELECT ultimo_envio, ultima_visualizacion, recordatorio_parto_enviado
                   FROM public.malertas_email_estado
                  WHERE mfundos_id = $1 AND mtipoalertas_id = $2
                    AND codigo_animal = $3 AND evento = $4 AND f_estimada = $5::date",
                array($mfundos_id, $tipo_alerta, $codigo_animal, $evento, $fecha_estimada));
              if (!$estado_result) {
                continue;
              }

              $estado = pg_fetch_assoc($estado_result);
              $sin_leer_desde_ultimo_envio = !$estado || empty($estado['ultimo_envio'])
                || empty($estado['ultima_visualizacion'])
                || strtotime($estado['ultima_visualizacion']) < strtotime($estado['ultimo_envio']);
              $recordatorio_parto_enviado = $estado
                && in_array($estado['recordatorio_parto_enviado'], array('t', 'true', '1'), true);
              $recordatorio_parto_pendiente = $tipo_alerta === 4
                && $dias <= 7
                && !$recordatorio_parto_enviado;

              if (!$sin_leer_desde_ultimo_envio && !$recordatorio_parto_pendiente) {
                continue;
              }

              $lote[] = array($codigo_animal, $nombre_animal, $evento, $f_estimada, $dias);
              $estados_lote[] = array(
                $tipo_alerta,
                $codigo_animal,
                $evento,
                $fecha_estimada,
                $recordatorio_parto_pendiente
              );

            }
            if (count($lote) > 0) {

              /* Nombre del fundo para rotular su sección en el correo agrupado */
              $rs_nombre = pg_query_params($con->obj_conec,
                "SELECT btrim(nombre) AS nombre FROM public.mfundos WHERE id = $1",
                array($mfundos_id));
              $fundo_nombre = ($rs_nombre && pg_num_rows($rs_nombre) > 0)
                ? pg_fetch_result($rs_nombre, 0, 'nombre')
                : ('Fundo ' . $mfundos_id);

              if (!isset($envios[$email])) {
                $envios[$email] = array('fundos' => array(), 'estados' => array());
              }

              $envios[$email]['fundos'][] = array(
                'fundo' => $fundo_nombre,
                'lote'  => $lote
              );

              /* Seguimiento por alerta: se guarda solo si el correo sale */
              foreach ($estados_lote as $estado_lote) {
                $envios[$email]['estados'][] = array(
                  $mfundos_id,          // fundo de la alerta
                  $estado_lote[0],      // mtipoalertas_id
                  $estado_lote[1],      // codigo_animal
                  $estado_lote[2],      // evento
                  $estado_lote[3],      // f_estimada
                  $estado_lote[4]       // recordatorio_parto_pendiente
                );
              }
            }
          }
         
        }

       
      }
      /* ------------------------------------------------------------------
         ENVÍO AGRUPADO: un solo correo por productor con las alertas de
         TODOS sus fundos (multifundo).
         ------------------------------------------------------------------ */
      $grupos_mostrados = 0;
      foreach ($envios as $email => $envio) {

        $n_fundos  = count($envio['fundos']);
        $n_alertas = 0;
        foreach ($envio['fundos'] as $fx) {
          $n_alertas += count($fx['lote']);
        }

        $grupo_correo = array('fundos' => $envio['fundos']);

        /* Modo revisión: informa y NO envía ni registra */
        if ($DRY_RUN) {
          echo 'CORREO   ' . $email . ' → ' . $n_fundos . ' fundo(s), '
            . $n_alertas . ' alerta(s)' . PHP_EOL;
          if ($VER_HTML && $grupos_mostrados === 0) {
            echo str_repeat('=', 78) . PHP_EOL;
            echo 'Correo que se enviaría a ' . $email . ' (' . $n_fundos . ' fundo/s)' . PHP_EOL;
            echo str_repeat('=', 78) . PHP_EOL;
            echo $enviar_correo->construir_cuerpo_agrupado($grupo_correo) . PHP_EOL;
          }
          $grupos_mostrados++;
          continue;
        }

        if (!$enviar_correo->enviar_agrupado($email, $grupo_correo)) {
          error_log('No se pudo enviar el correo de alertas agrupadas a ' . $email);
          continue;
        }

        /* El correo salió: se registra el seguimiento de cada alerta incluida */
        foreach ($envio['estados'] as $estado_lote) {
          $estado_guardado = pg_query_params($con->obj_conec,
            "INSERT INTO public.malertas_email_estado (
                   mfundos_id, mtipoalertas_id, codigo_animal, evento,
                   f_estimada, ultimo_envio, recordatorio_parto_enviado
               ) VALUES ($1, $2, $3, $4, $5::date, NOW(), $6::boolean)
               ON CONFLICT (mfundos_id, mtipoalertas_id, codigo_animal, evento, f_estimada)
               DO UPDATE SET ultimo_envio = EXCLUDED.ultimo_envio,
                             recordatorio_parto_enviado =
                               public.malertas_email_estado.recordatorio_parto_enviado
                               OR EXCLUDED.recordatorio_parto_enviado",
            array(
              $estado_lote[0],
              $estado_lote[1],
              $estado_lote[2],
              $estado_lote[3],
              $estado_lote[4],
              $estado_lote[5] ? 'true' : 'false'
            )
          );
          if (!$estado_guardado) {
            error_log('No se pudo guardar el seguimiento de una alerta enviada: ' . pg_last_error($con->obj_conec));
            continue;
          }
          pg_query_params($con->obj_conec,
            "UPDATE public.malertas SET vista = FALSE
              WHERE mfundos_id = $1 AND mtipoalertas_id = $2
                AND codigo_animal = $3 AND evento = $4 AND f_estimada::date = $5::date",
            array($estado_lote[0], $estado_lote[1], $estado_lote[2], $estado_lote[3], $estado_lote[4])
          );
        }

        $i += $n_alertas;
      }

      if ($DRY_RUN) {
        echo 'Correos que se enviarían: ' . count($envios) . PHP_EOL;
      } else {
        $enviar_correo->enviar_resumen($i);
      }


     
}

    $con->DISCONECT();
    unset($con);
  }
}
