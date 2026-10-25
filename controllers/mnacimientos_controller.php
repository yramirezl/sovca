<?php
require_once __DIR__ . '/../lib/especie_helper.php';
// error_reporting(E_ALL);
// ini_set('display_errors', '1');
require_once '../models/Mregnacimiento.php';
require_once '../models/Vmregnacimiento.php';
require_once '../models/Manimale.php';
require_once '../models/Mperiodo.php';
require_once '../models/Mnacimientoxfundo.php';
require_once '../models/Mdsinficha.php';
require_once '../models/Mterneroxnacimiento.php';
require_once '../models/Mregservicio.php';

include '../lib/sanitizar.php';
require_once '../lib/estatus_animal_helper.php';
require_once '../lib/fecha_secado_helper.php';

date_default_timezone_set('America/Caracas');

@$action = ($_POST["action"]);
@$id = ($_POST["id_reg_nac"]);

@$record = ($_POST["record"]);
/***********************************/
@$cod_animal_h = ($_POST["cod_animal_h"]);
@$cod_animal_m = ($_POST["cod_animal_m"]);

@$aptitud_animal = ($_POST["aptitud_animal"]);

@$fecha_parto = ($_POST["fecha_parto"]);
@$nro_parto = ($_POST["nro_parto"]);
@$nacio = ($_POST["nacio"]);
@$peso_nacer = ($_POST["peso_nacer"]);
@$fecha_secado = ($_POST["fecha_secado"]);
@$observaciones = ($_POST["observaciones"]);
@$sexo = ($_POST["sexo"]);
@$proposito = ($_POST["proposito"]);

// La pregunta "¿Entrará a producción de leche?" ya no existe en el
// formulario: el PROPÓSITO (1 = Lactancia, 2 = Producción de leche) es
// el que define si la cría entra a producción y con qué período se
// calcula la fecha de secado/destete (ver calcularFechaSecadoNacimiento).
$ira_produccion = in_array((int)$proposito, array(1, 2), true) ? 1 : 0;

/// Nuevas Variables
@$rgb_animal_h = ($_POST["rgb_animal_h"]);
@$rgb_animal_m = ($_POST["rgb_animal_m"]);
@$id_reg_nac = ($_POST["id_reg_nac"]);

@$tipo_servicio = intval($_POST["tipo_servicio"]);
@$cod_animal_dona = ($_POST["cod_animal_dona"]);
@$id_ternero = ($_POST["id_ternero"]);
@$mregservicios_id = (int)(isset($_POST["mregservicios_id"]) ? $_POST["mregservicios_id"] : 0);

/**********************************/

function construirGruposUnificacionNacimiento($mfundos_id)
{
  $registros = Mregnacimiento::find_by_sql(
    "SELECT n.id, n.mfundos_id, n.codigo_rgb_id_animal_hembra,
            n.codigo_ternero, n.id_ternero, n.f_parto,
            n.nro_parto, n.mregservicios_id, n.fichado,
            n.estatus_nacido, n.mestatus_id, n.status,
            n.sexo, n.peso_nacimiento, n.aptitud,
            /* created/user_create los usa la unificación al reconstruir el
               detalle de crías de un registro que no lo tenía. */
            n.created, n.user_create,
            COALESCE(crias.cantidad, 0) AS cantidad_terneros,
            COALESCE(crias.ids_terneros, '[]') AS ids_terneros
       FROM public.mregnacimientos n
       LEFT JOIN (
            SELECT mregnacimientos_id,
                   COUNT(*) FILTER (WHERE status = 1) AS cantidad,
                   COALESCE(
                     json_agg(NULLIF(BTRIM(id_ternero), '') ORDER BY nro_ternero)
                       FILTER (WHERE status = 1),
                     '[]'::json
                   )::text AS ids_terneros
              FROM public.mternerosxnacimientos
             GROUP BY mregnacimientos_id
       ) crias ON crias.mregnacimientos_id = n.id
      WHERE n.mfundos_id = ?
        AND n.status = 1
        AND n.mestatus_id = 1
        AND n.f_parto IS NOT NULL
        AND BTRIM(n.codigo_rgb_id_animal_hembra) <> ''
        /* Mismas condiciones que la vista Vmregnacimientos que alimenta el
           listado: si la hembra no está vigente o el tipo de servicio no
           existe, el registro NO se ve en pantalla, así que tampoco debe
           ofrecerse para unificar (caso MA-063-0105: mtiposervicios_id = 0). */
        AND EXISTS (
              SELECT 1 FROM public.manimales h
               WHERE h.codigo_rgb_id_animal = n.codigo_rgb_id_animal_hembra
                 AND h.mfundos_id = n.mfundos_id
                 AND h.mestatus_id = 1
        )
        AND EXISTS (
              SELECT 1 FROM public.mtiposervicios s
               WHERE s.id = n.mtiposervicios_id
        )
      ORDER BY n.codigo_rgb_id_animal_hembra, n.f_parto::date, n.id",
    array((int)$mfundos_id)
  );

  $grupos = array();
  $actual = array();
  $clave_actual = null;
  $fecha_ancla = null;
  $cerrar_grupo = function ($filas) use (&$grupos) {
    if (count($filas) < 2) {
      return;
    }

    $ids = array();
    $fechas = array();
    $codigos_evento = array();
    $servicios = array();
    $estados_ficha = array();
    $numeros_parto = array();
    $ids_crias = array();
    $cantidad_crias = 0;
    $bloqueos = array();
    $avisos = array();

    foreach ($filas as $fila) {
      $ids[] = (int)$fila->id;
      $fecha = $fila->f_parto instanceof DateTimeInterface
        ? $fila->f_parto->format('Y-m-d')
        : substr((string)$fila->f_parto, 0, 10);
      $fechas[] = $fecha;
      $codigos_evento[] = (string)$fila->codigo_ternero;
      /* Solo cuentan los números de parto informados: un registro sin
         número no invalida el grupo. */
      $nro_parto_fila = (int)$fila->nro_parto;
      if ($nro_parto_fila > 0) {
        $numeros_parto[$nro_parto_fila] = true;
      }
      if ($fila->mregservicios_id !== null && $fila->mregservicios_id !== '') {
        $servicios[(string)$fila->mregservicios_id] = true;
      }
      $fichado = in_array($fila->fichado, array(true, 1, '1', 't', 'true'), true);
      $estados_ficha[$fichado ? '1' : '0'] = true;

      $cantidad_crias += max(1, (int)$fila->cantidad_terneros);
      $ids_registro = json_decode((string)$fila->ids_terneros, true);
      if (!is_array($ids_registro) || count($ids_registro) === 0) {
        $ids_registro = array($fila->id_ternero);
      }
      foreach ($ids_registro as $id_cria) {
        $id_cria = strtoupper(trim((string)$id_cria));
        if ($id_cria === '') {
          continue;
        }
        if (isset($ids_crias[$id_cria])) {
          $bloqueos[] = 'Hay IDs de cría repetidos entre los registros.';
        }
        $ids_crias[$id_cria] = true;
      }
    }

    if (count($servicios) > 1) {
      $bloqueos[] = 'Los registros pertenecen a servicios distintos.';
    }
    if (count($estados_ficha) > 1) {
      $bloqueos[] = 'Los registros tienen estados de ficha distintos.';
    }

    /* Números de parto distintos = DOS PARTOS DIFERENTES de la misma hembra,
       no un duplicado. No se ofrecen para unificar (antes solo se avisaba
       "se conservará el menor", lo que borraba un parto real). */
    if (count($numeros_parto) > 1) {
      return;
    }

    $ids = array_values(array_unique($ids));
    sort($ids, SORT_NUMERIC);
    $grupos[] = array(
      'id' => implode('-', $ids),
      'ids' => $ids,
      'madre' => (string)$filas[0]->codigo_rgb_id_animal_hembra,
      'fechas' => array_values(array_unique($fechas)),
      'eventos' => array_values(array_unique($codigos_evento)),
      'cantidad_terneros' => $cantidad_crias,
      'mergeable' => count($bloqueos) === 0,
      'bloqueos' => array_values(array_unique($bloqueos)),
      'avisos' => array_values(array_unique($avisos)),
      'registros' => $filas,
      /* Detalle por registro: lo usa el diálogo para ofrecer "anular este
         duplicado" mostrando la fecha, el código de la cría y si tiene ficha. */
      'registros_detalle' => array_values(array_map(function ($fila) {
        $fecha = $fila->f_parto instanceof DateTimeInterface
          ? $fila->f_parto->format('Y-m-d')
          : substr((string)$fila->f_parto, 0, 10);
        $ids_registro = json_decode((string)$fila->ids_terneros, true);
        if (!is_array($ids_registro) || count($ids_registro) === 0) {
          $ids_registro = array($fila->id_ternero);
        }
        $crias = array();
        foreach ($ids_registro as $id_cria) {
          $id_cria = trim((string)$id_cria);
          if ($id_cria !== '') {
            $crias[] = $id_cria;
          }
        }
        return array(
          'id' => (int)$fila->id,
          'fecha' => $fecha,
          'codigo_ternero' => trim((string)$fila->codigo_ternero),
          'nro_parto' => (int)$fila->nro_parto,
          'fichado' => in_array($fila->fichado, array(true, 1, '1', 't', 'true'), true),
          'cantidad_terneros' => max(1, (int)$fila->cantidad_terneros),
          'crias' => $crias,
          'servicio' => ($fila->mregservicios_id === null || $fila->mregservicios_id === '')
            ? '' : (string)$fila->mregservicios_id
        );
      }, $filas))
    );
  };

  foreach ($registros as $registro) {
    $fecha = $registro->f_parto instanceof DateTimeInterface
      ? $registro->f_parto->format('Y-m-d')
      : substr((string)$registro->f_parto, 0, 10);
    $marca_tiempo = strtotime($fecha);
    $clave = (string)$registro->codigo_rgb_id_animal_hembra . '|' . substr($fecha, 0, 4);

    if ($clave_actual !== $clave || ($fecha_ancla !== null && $marca_tiempo - $fecha_ancla > 86400)) {
      $cerrar_grupo($actual);
      $actual = array();
      $clave_actual = $clave;
      $fecha_ancla = $marca_tiempo;
    }
    $actual[] = $registro;
  }
  $cerrar_grupo($actual);

  return $grupos;
}

function calcularFechaSecadoNacimiento($fecha_parto, $produccion_leche, $proposito, $mfundos_id, $tipo_ganaderia)
{
  if ((int)$produccion_leche !== 1) {
    return array('ok' => true, 'fecha' => null);
  }

  // Regla única compartida con el control de servicios y la sincronización
  // offline (lib/fecha_secado_helper.php): propósito 1 → PBL (destete),
  // propósito 2 → PPL (secado), con prioridad del período del fundo.
  $calculo = bovs_calcular_secado_parto($fecha_parto, $proposito, $mfundos_id, $tipo_ganaderia);
  if (!$calculo['ok']) {
    return array('ok' => false, 'mensaje' => $calculo['mensaje']);
  }

  return array(
    'ok' => true,
    'fecha' => $calculo['fecha'] ? $calculo['fecha']->format('d-m-Y') : null
  );
}

switch ($action) {

  case 'analizar_unificacion_nacimientos':
    session_start();
    $mfundos_id = (int)$_SESSION['mfundos_id'];
    $rol = (int)$_SESSION['rolx'];
    if (!in_array($rol, array(1, 2), true)) {
      echo json_encode(array('resultado' => 'error', 'mensaje' => 'No tienes permiso para unificar nacimientos.'));
      break;
    }

    $grupos = construirGruposUnificacionNacimiento($mfundos_id);
    $respuesta_grupos = array();
    foreach ($grupos as $grupo) {
      $respuesta_grupos[] = array(
        'id' => $grupo['id'],
        'ids' => $grupo['ids'],
        'madre' => $grupo['madre'],
        'fechas' => $grupo['fechas'],
        'eventos' => $grupo['eventos'],
        'cantidad_terneros' => $grupo['cantidad_terneros'],
        'mergeable' => $grupo['mergeable'],
        'bloqueos' => $grupo['bloqueos'],
        'avisos' => $grupo['avisos'],
        'registros' => isset($grupo['registros_detalle']) ? $grupo['registros_detalle'] : array()
      );
    }
    echo json_encode(array('resultado' => 'ok', 'grupos' => $respuesta_grupos));
    break;

  case 'unificar_nacimientos':
    session_start();
    $mfundos_id = (int)$_SESSION['mfundos_id'];
    $usuario_id = (int)$_SESSION['idusuariox'];
    $rol = (int)$_SESSION['rolx'];
    $grupos_solicitados = json_decode((string)(isset($_POST['grupos']) ? $_POST['grupos'] : ''), true);

    if (!in_array($rol, array(1, 2), true)) {
      echo json_encode(array('resultado' => 'error', 'mensaje' => 'No tienes permiso para unificar nacimientos.'));
      break;
    }
    if (!is_array($grupos_solicitados) || count($grupos_solicitados) === 0) {
      echo json_encode(array('resultado' => 'error', 'mensaje' => 'Selecciona al menos un grupo para unificar.'));
      break;
    }

    $ids_solicitados = array();
    $ids_seleccionados = array();
    foreach ($grupos_solicitados as $grupo_ids) {
      if (!is_array($grupo_ids)) {
        echo json_encode(array('resultado' => 'error', 'mensaje' => 'La selección de nacimientos no es válida.'));
        break 2;
      }
      $grupo_ids = array_values(array_unique(array_map('intval', $grupo_ids)));
      sort($grupo_ids, SORT_NUMERIC);
      if (count($grupo_ids) < 2 || in_array(0, $grupo_ids, true)) {
        echo json_encode(array('resultado' => 'error', 'mensaje' => 'Cada grupo debe contener al menos dos nacimientos válidos.'));
        break 2;
      }
      foreach ($grupo_ids as $id_seleccionado) {
        if (isset($ids_seleccionados[$id_seleccionado])) {
          echo json_encode(array('resultado' => 'error', 'mensaje' => 'Un nacimiento no puede pertenecer a dos grupos seleccionados.'));
          break 3;
        }
        $ids_seleccionados[$id_seleccionado] = true;
      }
      $ids_solicitados[] = $grupo_ids;
    }

    $conexion = Mregnacimiento::connection();
    $conexion->transaction();
    try {
      $unificados = 0;
      foreach ($ids_solicitados as $ids) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $valores_bloqueo = array_merge(array($mfundos_id), $ids);
        $conexion->query(
          "SELECT id FROM public.mregnacimientos
            WHERE mfundos_id = ? AND id IN ($placeholders) AND status = 1
            ORDER BY id FOR UPDATE",
          $valores_bloqueo
        );
        $conexion->query(
          "SELECT id FROM public.mternerosxnacimientos
            WHERE mregnacimientos_id IN ($placeholders) AND status = 1
            ORDER BY id FOR UPDATE",
          $ids
        );

        $clave_solicitada = implode('-', $ids);
        $grupo_actual = null;
        foreach (construirGruposUnificacionNacimiento($mfundos_id) as $candidato) {
          if ($candidato['id'] === $clave_solicitada) {
            $grupo_actual = $candidato;
            break;
          }
        }
        if (!$grupo_actual || !$grupo_actual['mergeable']) {
          throw new RuntimeException('El grupo cambió o requiere revisión manual. Vuelve a analizar los nacimientos.');
        }

        $registros = $grupo_actual['registros'];
        $principal = $registros[0];
        $id_principal = (int)$principal->id;
        $codigos_duplicados = array();
        foreach ($registros as $registro) {
          if ((int)$registro->id !== $id_principal) {
            $codigo = trim((string)$registro->codigo_ternero);
            if ($codigo !== '' && $codigo !== (string)$principal->codigo_ternero) {
              $codigos_duplicados[$codigo] = true;
            }
          }
        }

        $detalles = Mterneroxnacimiento::find_by_sql(
          "SELECT * FROM public.mternerosxnacimientos
            WHERE status = 1 AND mregnacimientos_id IN ($placeholders)
            ORDER BY mregnacimientos_id, nro_ternero, id",
          $ids
        );
        $eventos_con_detalle = array();
        foreach ($detalles as $detalle) {
          $eventos_con_detalle[(int)$detalle->mregnacimientos_id] = true;
        }
        foreach ($registros as $registro) {
          $id_evento = (int)$registro->id;
          if (isset($eventos_con_detalle[$id_evento])) {
            continue;
          }
          $detalle = new Mterneroxnacimiento();
          $detalle->mregnacimientos_id = $id_evento;
          $detalle->mfundos_id = $mfundos_id;
          $detalle->nro_ternero = 1;
          $detalle->id_ternero = $registro->id_ternero;
          $detalle->peso_nacimiento = $registro->peso_nacimiento;
          $detalle->sexo = $registro->sexo;
          $detalle->estatus_nacido = $registro->estatus_nacido;
          $detalle->aptitud = $registro->aptitud;
          $detalle->created = $registro->created ? $registro->created : date('Y-m-d H:i:s');
          $detalle->status = 1;
          $detalle->user_create = $registro->user_create;
          if (!$detalle->save()) {
            throw new RuntimeException('No se pudo preservar una cría del nacimiento duplicado.');
          }
        }

        $conexion->query(
          "WITH orden AS (
               SELECT id, ROW_NUMBER() OVER (ORDER BY mregnacimientos_id, nro_ternero, id) AS posicion
                 FROM public.mternerosxnacimientos
                WHERE status = 1 AND mregnacimientos_id IN ($placeholders)
           )
           UPDATE public.mternerosxnacimientos AS detalle
              SET nro_ternero = -orden.posicion::smallint
             FROM orden
            WHERE detalle.id = orden.id",
          $ids
        );
        $valores_mover_detalles = array_merge(array($id_principal), $ids);
        $conexion->query(
          "UPDATE public.mternerosxnacimientos
              SET mregnacimientos_id = ?
            WHERE status = 1 AND mregnacimientos_id IN ($placeholders)",
          $valores_mover_detalles
        );
        $detalles_consolidados = Mterneroxnacimiento::find_by_sql(
          "SELECT * FROM public.mternerosxnacimientos
            WHERE status = 1 AND mregnacimientos_id = ?
            ORDER BY ABS(nro_ternero), id",
          array($id_principal)
        );
        foreach ($detalles_consolidados as $indice => $detalle) {
          /* ⚠️ find_by_sql() devuelve modelos en SOLO LECTURA (AR le pasa
             readonly=true), así que $detalle->save() lanzaría
             ReadOnlyException y rompería toda la unificación. */
          $conexion->query(
            "UPDATE public.mternerosxnacimientos SET nro_ternero = ? WHERE id = ?",
            array($indice + 1, (int)$detalle->id)
          );
        }

        $principal_actual = Mregnacimiento::find($id_principal);
        if (!$principal_actual) {
          throw new RuntimeException('No se encontró el nacimiento que debía conservarse.');
        }
        if (count($detalles_consolidados) > 0) {
          $primera_cria = $detalles_consolidados[0];
          $principal_actual->id_ternero = $primera_cria->id_ternero;
          $principal_actual->peso_nacimiento = $primera_cria->peso_nacimiento;
          $principal_actual->sexo = $primera_cria->sexo;
          $principal_actual->estatus_nacido = $primera_cria->estatus_nacido;
          $principal_actual->aptitud = $primera_cria->aptitud;
        }
        $principal_actual->f_parto = $grupo_actual['fechas'][0];
        /* Solo números válidos: un registro sin número de parto no debe
           arrastrar el valor a 0. */
        $numeros_parto = array_values(array_filter(
          array_map(function ($registro) { return (int)$registro->nro_parto; }, $registros),
          function ($n) { return $n > 0; }
        ));
        if (count($numeros_parto) > 0) {
          $principal_actual->nro_parto = min($numeros_parto);
        }
        foreach ($registros as $registro) {
          if ($registro->mregservicios_id !== null && $registro->mregservicios_id !== '') {
            $principal_actual->mregservicios_id = $registro->mregservicios_id;
            break;
          }
        }
        $principal_actual->updated = date('Y-m-d H:i:s');
        $principal_actual->user_modify = $usuario_id;
        if (!$principal_actual->save()) {
          throw new RuntimeException('No se pudo actualizar el nacimiento consolidado.');
        }

        foreach (array_keys($codigos_duplicados) as $codigo_duplicado) {
          $animales = Manimale::find('all', array('conditions' => array(
            'mfundos_id=? AND codigo_ternero=?', $mfundos_id, $codigo_duplicado
          )));
          foreach ($animales as $animal) {
            $animal->codigo_ternero = $principal_actual->codigo_ternero;
            $animal->user_modify = $usuario_id;
            $animal->updated = date('Y-m-d H:i:s');
            if (!$animal->save()) {
              throw new RuntimeException('No se pudo actualizar el vínculo de una ficha animal.');
            }
          }
          $bajas_crias = Mdsinficha::find('all', array('conditions' => array(
            'mfundos_id=? AND codigo_ternero=?', $mfundos_id, $codigo_duplicado
          )));
          foreach ($bajas_crias as $baja_cria) {
            $baja_cria->codigo_ternero = $principal_actual->codigo_ternero;
            $baja_cria->user_modify = $usuario_id;
            $baja_cria->updated = date('Y-m-d H:i:s');
            if (!$baja_cria->save()) {
              throw new RuntimeException('No se pudo actualizar el vínculo de una baja de cría.');
            }
          }
        }

        $placeholders_duplicados = implode(',', array_fill(0, count($ids) - 1, '?'));
        $ids_duplicados = array_values(array_filter($ids, function ($id) use ($id_principal) {
          return (int)$id !== $id_principal;
        }));
        $valores_relaciones = array_merge(array($mfundos_id), $ids_duplicados);
        $relaciones_duplicadas = Mnacimientoxfundo::find('all', array('conditions' => array(
          'mfundos_id=? AND mregnacimientos_id IN (' . $placeholders_duplicados . ') AND status=1',
          $valores_relaciones[0],
          ...array_slice($valores_relaciones, 1)
        )));
        $relacion_principal = Mnacimientoxfundo::find('first', array('conditions' => array(
          'mfundos_id=? AND mregnacimientos_id=? AND status=1', $mfundos_id, $id_principal
        )));
        if (!$relacion_principal && count($relaciones_duplicadas) > 0) {
          $relacion_principal = array_shift($relaciones_duplicadas);
          $relacion_principal->mregnacimientos_id = $id_principal;
          $relacion_principal->user_modify = $usuario_id;
          $relacion_principal->updated = date('Y-m-d H:i:s');
          if (!$relacion_principal->save()) {
            throw new RuntimeException('No se pudo conservar el vínculo del nacimiento con el fundo.');
          }
        } elseif (!$relacion_principal) {
          $relacion_principal = new Mnacimientoxfundo();
          $relacion_principal->mregnacimientos_id = $id_principal;
          $relacion_principal->mfundos_id = $mfundos_id;
          $relacion_principal->user_create = $usuario_id;
          $relacion_principal->created = date('Y-m-d H:i:s');
          if (!$relacion_principal->save()) {
            throw new RuntimeException('No se pudo crear el vínculo del nacimiento con el fundo.');
          }
        }
        foreach ($relaciones_duplicadas as $relacion) {
          $relacion->status = 0;
          $relacion->user_modify = $usuario_id;
          $relacion->updated = date('Y-m-d H:i:s');
          if (!$relacion->save()) {
            throw new RuntimeException('No se pudo archivar un vínculo duplicado del fundo.');
          }
        }

        foreach ($ids_duplicados as $id_duplicado) {
          $registro_duplicado = Mregnacimiento::find((int)$id_duplicado);
          if (!$registro_duplicado) {
            throw new RuntimeException('No se encontró uno de los registros duplicados.');
          }
          $nota = ' Registro unificado en nacimiento ' . $id_principal . '.';
          $registro_duplicado->observaciones = trim((string)$registro_duplicado->observaciones . $nota);
          $registro_duplicado->status = 0;
          $registro_duplicado->updated = date('Y-m-d H:i:s');
          $registro_duplicado->user_modify = $usuario_id;
          if (!$registro_duplicado->save()) {
            throw new RuntimeException('No se pudo archivar un registro duplicado.');
          }
        }
        $unificados++;
      }

      $conexion->commit();
      echo json_encode(array(
        'resultado' => 'ok',
        'unificados' => $unificados,
        'mensaje' => '<div class="alert alert-success">Se unificaron ' . $unificados . ' grupo(s). Se conservaron las crías y sus fichas asociadas.</div>'
      ));
    } catch (Throwable $e) {
      $conexion->rollback();
      error_log('Error al unificar nacimientos: ' . $e->getMessage());
      echo json_encode(array(
        'resultado' => 'error',
        'mensaje' => '<div class="alert alert-danger">No se completó la unificación; todos los cambios fueron revertidos. Vuelve a analizar los grupos e inténtalo de nuevo.</div>'
      ));
    }
    break;

  case 'anular_nacimientos_duplicados':
    session_start();
    $mfundos_id = (int)$_SESSION['mfundos_id'];
    $usuario_id = (int)$_SESSION['idusuariox'];
    $rol = (int)$_SESSION['rolx'];
    $solicitadas = json_decode((string)(isset($_POST['anulaciones']) ? $_POST['anulaciones'] : ''), true);

    if (!in_array($rol, array(1, 2), true)) {
      echo json_encode(array('resultado' => 'error', 'mensaje' => 'No tienes permiso para anular nacimientos duplicados.'));
      break;
    }
    if (!is_array($solicitadas) || count($solicitadas) === 0) {
      echo json_encode(array('resultado' => 'error', 'mensaje' => 'No se recibió ningún registro para anular.'));
      break;
    }

    $pares = array();
    $ids_usados = array();
    foreach ($solicitadas as $item) {
      if (!is_array($item)) {
        echo json_encode(array('resultado' => 'error', 'mensaje' => 'La selección de nacimientos no es válida.'));
        break 2;
      }
      $id_anular    = isset($item['id_anular']) ? (int)$item['id_anular'] : 0;
      $id_conservar = isset($item['id_conservar']) ? (int)$item['id_conservar'] : 0;
      $motivo       = trim((string)(isset($item['motivo']) ? $item['motivo'] : ''));
      if (function_exists('mb_substr')) {
        $motivo = mb_substr($motivo, 0, 200, 'UTF-8');
      } else {
        $motivo = substr($motivo, 0, 200);
      }

      if ($id_anular <= 0 || $id_conservar <= 0 || $id_anular === $id_conservar) {
        echo json_encode(array('resultado' => 'error', 'mensaje' => 'Cada anulación debe indicar dos nacimientos distintos.'));
        break 2;
      }
      if (isset($ids_usados[$id_anular]) || isset($ids_usados[$id_conservar])) {
        echo json_encode(array('resultado' => 'error', 'mensaje' => 'Un nacimiento no puede aparecer en dos anulaciones a la vez.'));
        break 2;
      }
      $ids_usados[$id_anular] = true;
      $ids_usados[$id_conservar] = true;
      $pares[] = array('anular' => $id_anular, 'conservar' => $id_conservar, 'motivo' => $motivo);
    }

    $conexion = Mregnacimiento::connection();
    $conexion->transaction();
    try {
      /* Solo se anulan registros que el análisis sigue considerando parte del
         MISMO grupo (misma madre y parto dentro de las 24 h). */
      $grupo_de = array();
      foreach (construirGruposUnificacionNacimiento($mfundos_id) as $grupo) {
        foreach ($grupo['ids'] as $id_grupo) {
          $grupo_de[(int)$id_grupo] = (string)$grupo['id'];
        }
      }
      foreach ($pares as $par) {
        if (!isset($grupo_de[$par['anular']]) || !isset($grupo_de[$par['conservar']])
            || $grupo_de[$par['anular']] !== $grupo_de[$par['conservar']]) {
          throw new RuntimeException('Los nacimientos seleccionados ya no coinciden entre sí. Vuelve a analizarlos.');
        }
      }

      $anulados = 0;
      foreach ($pares as $par) {
        $id_anular    = (int)$par['anular'];
        $id_conservar = (int)$par['conservar'];
        $motivo       = (string)$par['motivo'];

        $bloqueados = $conexion->query(
          "SELECT id FROM public.mregnacimientos
            WHERE mfundos_id = ? AND id IN (?, ?) AND status = 1
            ORDER BY id FOR UPDATE",
          array($mfundos_id, $id_anular, $id_conservar)
        )->fetchAll();
        if (count($bloqueados) !== 2) {
          throw new RuntimeException('Uno de los nacimientos ya no está activo.');
        }

        $reg_anular    = Mregnacimiento::find($id_anular);
        $reg_conservar = Mregnacimiento::find($id_conservar);
        if (!$reg_anular || !$reg_conservar) {
          throw new RuntimeException('No se encontró uno de los nacimientos seleccionados.');
        }
        if (trim((string)$reg_anular->codigo_rgb_id_animal_hembra) !== trim((string)$reg_conservar->codigo_rgb_id_animal_hembra)) {
          throw new RuntimeException('Los nacimientos pertenecen a madres distintas; no se pueden anular como duplicados.');
        }

        /* Servicios: si cada registro apunta a un servicio diferente no hay
           forma de saber cuál parto es el bueno → se pide unificar. */
        $serv_anular    = trim((string)$reg_anular->mregservicios_id);
        $serv_conservar = trim((string)$reg_conservar->mregservicios_id);
        if ($serv_anular !== '' && $serv_conservar !== '' && $serv_anular !== $serv_conservar) {
          throw new RuntimeException('Los dos registros apuntan a servicios distintos. Usa "Unificar" en lugar de "Anular".');
        }

        /* Crías: el registro que se anula no puede aportar crías que el que se
           conserva no tenga (si no, se perdería información real). */
        $crias_anular = Mterneroxnacimiento::find_by_sql(
          "SELECT * FROM public.mternerosxnacimientos
            WHERE mregnacimientos_id = ? AND status = 1
            ORDER BY nro_ternero, id",
          array($id_anular)
        );
        $crias_conservar = Mterneroxnacimiento::find_by_sql(
          "SELECT * FROM public.mternerosxnacimientos
            WHERE mregnacimientos_id = ? AND status = 1
            ORDER BY nro_ternero, id",
          array($id_conservar)
        );
        $ids_conservar = array();
        foreach ($crias_conservar as $cria) {
          $codigo_cria = strtoupper(trim((string)$cria->id_ternero));
          if ($codigo_cria !== '') {
            $ids_conservar[$codigo_cria] = true;
          }
        }
        /* Si el registro no tiene detalle, la cría vive en el campo plano
           (mismo criterio que el análisis de grupos). */
        $cria_plana_conservar = strtoupper(trim((string)$reg_conservar->id_ternero));
        if ($cria_plana_conservar !== '') {
          $ids_conservar[$cria_plana_conservar] = true;
        }

        $crias_sobrantes = array();
        foreach ($crias_anular as $cria) {
          $codigo_cria = strtoupper(trim((string)$cria->id_ternero));
          if ($codigo_cria !== '' && !isset($ids_conservar[$codigo_cria])) {
            $crias_sobrantes[$codigo_cria] = true;
          }
        }
        $cria_plana_anular = strtoupper(trim((string)$reg_anular->id_ternero));
        if ($cria_plana_anular !== '' && !isset($ids_conservar[$cria_plana_anular])) {
          $crias_sobrantes[$cria_plana_anular] = true;
        }

        /* Códigos de cría REALES (no vacíos) que aporta el registro anulado. */
        $codigos_anular = array();
        foreach ($crias_anular as $cria) {
          $codigo_cria = strtoupper(trim((string)$cria->id_ternero));
          if ($codigo_cria !== '') {
            $codigos_anular[$codigo_cria] = true;
          }
        }
        if ($cria_plana_anular !== '') {
          $codigos_anular[$cria_plana_anular] = true;
        }

        /* Excepción: si el registro que queda NO tiene ninguna cría
           identificada, la del anulado se copia (no se pierde), siempre que
           sea una sola. */
        if (count($ids_conservar) === 0 && count($codigos_anular) <= 1) {
          $crias_sobrantes = array();
        }

        if (count($crias_sobrantes) > 0) {
          throw new RuntimeException(
            'El registro a anular tiene crías que no están en el que se conserva ('
            . implode(', ', array_keys($crias_sobrantes))
            . '). Usa "Unificar" en lugar de "Anular".'
          );
        }

        /* Se completa el registro que queda con lo que el anulado aporte y
           falte (código de cría, peso, sexo, aptitud, nro de parto, servicio,
           ficha y secado). Así anular no pierde ningún dato útil. */
        $codigo_anular    = trim((string)$reg_anular->codigo_ternero);
        $codigo_conservar = trim((string)$reg_conservar->codigo_ternero);
        if ($codigo_conservar === '' && $codigo_anular !== '') {
          $reg_conservar->codigo_ternero = $codigo_anular;
          $codigo_conservar = $codigo_anular;
        }
        if (trim((string)$reg_conservar->id_ternero) === '' && trim((string)$reg_anular->id_ternero) !== '') {
          $reg_conservar->id_ternero = $reg_anular->id_ternero;
        }
        if ((float)$reg_conservar->peso_nacimiento <= 0 && (float)$reg_anular->peso_nacimiento > 0) {
          $reg_conservar->peso_nacimiento = $reg_anular->peso_nacimiento;
        }
        if ((int)$reg_conservar->sexo <= 0 && (int)$reg_anular->sexo > 0) {
          $reg_conservar->sexo = $reg_anular->sexo;
        }
        if ((int)$reg_conservar->estatus_nacido <= 0 && (int)$reg_anular->estatus_nacido > 0) {
          $reg_conservar->estatus_nacido = $reg_anular->estatus_nacido;
        }
        if ((int)$reg_conservar->aptitud <= 0 && (int)$reg_anular->aptitud > 0) {
          $reg_conservar->aptitud = $reg_anular->aptitud;
        }
        if ((int)$reg_conservar->nro_parto <= 0 && (int)$reg_anular->nro_parto > 0) {
          $reg_conservar->nro_parto = $reg_anular->nro_parto;
        }
        if ($serv_conservar === '' && $serv_anular !== '') {
          $reg_conservar->mregservicios_id = $reg_anular->mregservicios_id;
        }
        if (empty($reg_conservar->f_secado) && !empty($reg_anular->f_secado)) {
          $reg_conservar->f_secado = $reg_anular->f_secado;
        }
        /* Si el anulado tenía la ficha hecha, el parto que queda debe quedar
           marcado como fichado (la cría existe en el hato). */
        if (in_array($reg_anular->fichado, array(true, 1, '1', 't', 'true'), true)) {
          $reg_conservar->fichado = 1;
        }
        $reg_conservar->updated = date('Y-m-d H:i:s');
        $reg_conservar->user_modify = $usuario_id;
        if (!$reg_conservar->save()) {
          throw new RuntimeException('No se pudo actualizar el nacimiento que se conserva.');
        }

        /* Si el registro que queda no tenía el código de la cría en su
           detalle, se lo pasamos para que "Crías del Parto" lo muestre. */
        if (count($ids_conservar) === 0 && count($codigos_anular) === 1) {
          $codigos_lista = array_keys($codigos_anular);
          $conexion->query(
            "UPDATE public.mternerosxnacimientos
                SET id_ternero = ?, user_modify = ?, updated = NOW()
              WHERE mregnacimientos_id = ? AND status = 1
                AND BTRIM(COALESCE(id_ternero, '')) = ''",
            array($codigos_lista[0], $usuario_id, $id_conservar)
          );
        }

        /* Fichas del animal y bajas de cría que apuntaban al código del
           registro anulado pasan al código que se conserva. */
        if ($codigo_anular !== '' && $codigo_conservar !== '' && $codigo_anular !== $codigo_conservar) {
          $conexion->query(
            "UPDATE public.manimales SET codigo_ternero = ?, user_modify = ?, updated = NOW()
              WHERE mfundos_id = ? AND codigo_ternero = ?",
            array($codigo_conservar, $usuario_id, $mfundos_id, $codigo_anular)
          );
          $conexion->query(
            "UPDATE public.mdsinfichas SET codigo_ternero = ?, user_modify = ?, updated = NOW()
              WHERE mfundos_id = ? AND codigo_ternero = ?",
            array($codigo_conservar, $usuario_id, $mfundos_id, $codigo_anular)
          );
        }

        /* Baja lógica de las crías del registro anulado y del propio registro. */
        $conexion->query(
          "UPDATE public.mternerosxnacimientos
              SET status = 0, user_modify = ?, updated = NOW()
            WHERE mregnacimientos_id = ? AND status = 1",
          array($usuario_id, $id_anular)
        );
        $conexion->query(
          "UPDATE public.mnacimientoxfundos
              SET status = 0, user_modify = ?, updated = NOW()
            WHERE mfundos_id = ? AND mregnacimientos_id = ? AND status = 1",
          array($usuario_id, $mfundos_id, $id_anular)
        );

        $nota = ' Registro duplicado anulado; se conserva el nacimiento ' . $id_conservar . '.';
        if ($motivo !== '') {
          $nota .= ' Motivo: ' . $motivo . '.';
        }
        $reg_anular->observaciones = trim((string)$reg_anular->observaciones . $nota);
        $reg_anular->status = 0;
        $reg_anular->updated = date('Y-m-d H:i:s');
        $reg_anular->user_modify = $usuario_id;
        if (!$reg_anular->save()) {
          throw new RuntimeException('No se pudo anular el registro duplicado.');
        }

        $anulados++;
      }

      $conexion->commit();
      echo json_encode(array(
        'resultado' => 'ok',
        'anulados' => $anulados,
        'mensaje' => '<div class="alert alert-success">Se anularon ' . $anulados
          . ' nacimiento(s) duplicado(s). Las crías, fichas y vínculos se conservaron en el registro que queda.</div>'
      ));
    } catch (Throwable $e) {
      $conexion->rollback();
      error_log('Error al anular nacimientos duplicados: ' . $e->getMessage());
      /* Solo las validaciones propias (RuntimeException) son aptas para
         mostrarse; un fallo técnico se informa en genérico. */
      $detalle = ($e instanceof RuntimeException)
        ? htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
        : 'Ocurrió un error inesperado al anular el registro.';
      echo json_encode(array(
        'resultado' => 'error',
        'mensaje' => '<div class="alert alert-danger">' . $detalle . ' No se aplicó ningún cambio.</div>'
      ));
    }
    break;
  case 'buscar_servicios_gestantes':
    session_start();
    $mfundos_id = (int)$_SESSION['mfundos_id'];
    $termino = trim((string)(isset($_POST['termino']) ? $_POST['termino'] : ''));
    $condicionBusqueda = '';
    $valores = array($mfundos_id);
    if ($termino !== '') {
      $condicionBusqueda = " AND (s.codigo_servicio ILIKE ?
          OR s.codigo_rgb_id_animal_hembra ILIKE ?
          OR s.nombre_h ILIKE ?
          OR COALESCE(s.nro_arete, '') ILIKE ?)";
      $like = '%' . $termino . '%';
      $valores = array_merge($valores, array($like, $like, $like, $like));
    }

    $sql = "SELECT s.id, s.codigo_servicio, s.codigo_rgb_id_animal_hembra,
                   s.codigo_rgb_id_animal_macho, s.codigo_rgb_id_animal_dona,
                   s.nombre_h, s.nombre_m, s.nro_arete, s.f_servicio,
                   s.mservicios_id, s.aptitud,
                   (SELECT a.nombre
                      FROM Manimales a
                     WHERE a.mfundos_id = s.mfundos_id
                       AND a.codigo_rgb_id_animal = s.codigo_rgb_id_animal_dona
                     LIMIT 1) AS nombre_dona,
                   COALESCE((SELECT MAX(n.nro_parto) + 1
                               FROM mregnacimientos n
                              WHERE n.mfundos_id = s.mfundos_id
                                AND n.codigo_rgb_id_animal_hembra = s.codigo_rgb_id_animal_hembra
                                AND n.status = 1), 1) AS nro_parto,
                   (EXTRACT(YEAR FROM age(CURRENT_DATE, s.f_servicio::date)) * 12
                    + EXTRACT(MONTH FROM age(CURRENT_DATE, s.f_servicio::date))) AS meses_gestacion
              FROM Vregservicios s
             WHERE s.mfundos_id = ?
               AND s.estatus_servicio = 1
               AND s.estado_servicio <> 2
               AND s.estatus_ciclo = 1
               AND s.medofisiologicos_id = 1
               AND COALESCE(s.parto, 0) = 0
               AND s.f_servicio::date <= CURRENT_DATE
               AND (EXTRACT(YEAR FROM age(CURRENT_DATE, s.f_servicio::date)) * 12
                    + EXTRACT(MONTH FROM age(CURRENT_DATE, s.f_servicio::date))) BETWEEN 7 AND 10" .
               $condicionBusqueda . "
          ORDER BY s.f_servicio DESC, s.id DESC
             LIMIT 25";
    $registros = Mregnacimiento::find_by_sql($sql, $valores);
    $servicios = array();
    if ($registros) {
      foreach ($registros as $servicio) {
        $fecha = $servicio->f_servicio instanceof DateTimeInterface
          ? $servicio->f_servicio->format('Y-m-d')
          : substr((string)$servicio->f_servicio, 0, 10);
        $servicios[] = array(
          'id' => (int)$servicio->id,
          'codigo_servicio' => $servicio->codigo_servicio,
          'codigo_rgb_id_animal_hembra' => $servicio->codigo_rgb_id_animal_hembra,
          'codigo_rgb_id_animal_macho' => $servicio->codigo_rgb_id_animal_macho,
          'codigo_rgb_id_animal_dona' => $servicio->codigo_rgb_id_animal_dona,
          'nombre_hembra' => $servicio->nombre_h,
          'nombre_macho' => $servicio->nombre_m,
          'nombre_donadora' => $servicio->nombre_dona,
          'arete_hembra' => $servicio->nro_arete,
          'fecha_servicio' => $fecha,
          'nro_parto' => (int)$servicio->nro_parto,
          'tipo_servicio' => (int)$servicio->mservicios_id,
          'aptitud' => (int)$servicio->aptitud,
          'meses_gestacion' => (int)$servicio->meses_gestacion
        );
      }
    }
    echo json_encode(array('ok' => true, 'servicios' => $servicios));
    break;

  case 'search_rep_nacimientos_madre':
    session_start();
    $mfundos_id = intval($_SESSION['mfundos_id']);
    $buscar = intval($_POST['buscar_cod']);
    $valor = trim($_POST['rgb_animal']);
    $respuesta = array('cod_animal' => 0);

    if ($mfundos_id > 0 && $valor != '' && ($buscar === 1 || $buscar === 2)) {
      $valor = str_replace("'", "''", $valor);
      $campo = $buscar === 1 ? 'codigo_rgb_id_animal_hembra' : 'nro_arete';
      $registros = Vmregnacimiento::find_by_sql("SELECT codigo_rgb_id_animal_hembra
        FROM Vmregnacimientos
        WHERE mfundos_id=" . $mfundos_id . " AND mestatus_id=1
          AND " . $campo . "='" . $valor . "' LIMIT 1");

      if ($registros != null) {
        $respuesta = array('cod_animal' => $registros[0]->codigo_rgb_id_animal_hembra);
      }
    }

    echo json_encode($respuesta);
    break;

    /// **************************

    /// LISTADO PARA EL BUSCADOR AMIGABLE DE NACIMIENTOS
    /// rol = madre | donadora | padre. Devuelve TODOS los animales que cumplen la
    /// condición del rol y marca `disponible` con las MISMAS reglas que usan
    /// get_buscar_rgb_h / get_buscar_rgb_h_dona / get_buscar_rgb_m, para que el
    /// listado y la validación al elegir una fila nunca se contradigan.

  case 'listar_candidatos_nacimiento':

    session_start();
    $mfundos_id = intval($_SESSION['mfundos_id']);
    $rol        = isset($_POST['rol'])    ? $_POST['rol']     : '';
    $tipo_s     = isset($_POST['tipo_s']) ? intval($_POST['tipo_s']) : 0;

    $filas = array();

    $edad_sql = "(extract(year from age(current_date, a.f_nacimiento)) * 12
                  + extract(month from age(current_date, a.f_nacimiento)))";

    if ($rol == 'padre' || $rol == 'macho' || $rol == 'toro') {

      /* ---------- PADRE / MACHO ---------- */
      $datos = Manimale::find_by_sql("select a.codigo_rgb_id_animal          as codigo,
                                             coalesce(a.nro_arete, '')      as nro_arete,
                                             coalesce(a.nombre, '')         as nombre,
                                             coalesce(a.estatus_animal, 1)  as estatus_animal,
                                             " . $edad_sql . "::int         as edad_meses
                                        from manimales a
                                       where a.mfundos_id = " . $mfundos_id . "
                                         and a.mestatus_id = 1
                                         and a.sexo = 2
                                         and " . bovs_estatus_animal_sql(2, 'a') . "
                                       order by a.nombre, a.codigo_rgb_id_animal;");

      foreach ($datos as $rs) {

        $est = intval($rs->estatus_animal);

        /* MONTA (1) => toro ACTIVO · INSEMINACIÓN (2) y TRANSFERENCIA (3) => donador */
        if ($tipo_s == 2 || $tipo_s == 3) {
          $disponible = (($est == 2) || ($est == 3)) ? 1 : 0;
          $condicion  = ($disponible == 1) ? 'Donador' : 'Toro de monta';
        } else {
          $disponible = ($est == 1) ? 1 : 0;
          $condicion  = ($disponible == 1) ? 'Toro de monta' : 'Donador';
        }

        $filas[] = array(
          'codigo'       => $rs->codigo,
          'nro_arete'    => ($rs->nro_arete === null) ? '' : $rs->nro_arete,
          'nombre'       => ($rs->nombre === null) ? '' : $rs->nombre,
          'edad_meses'   => intval($rs->edad_meses),
          'condicion'    => $condicion,
          'disponible'   => $disponible,
          'nro_servicio' => 0
        );
      }

    } else {

      /* ---------- MADRE / DONADORA (hembras) ---------- */
      $es_donadora = ($rol == 'donadora');

      $cad_estatus = $es_donadora
          ? 'and ' . bovs_estatus_animal_donante_sql(1, 'a')
          : 'and ' . bovs_estatus_animal_sql(1, 'a');

      $sql = "select a.codigo_rgb_id_animal                       as codigo,
                     coalesce(a.nro_arete, '')                   as nro_arete,
                     coalesce(a.nombre, '')                      as nombre,
                     " . $edad_sql . "::int                      as edad_meses,
                     coalesce(n.nro_parto, 0)                    as nro_parto,
                     case when n.f_parto is null then 300
                          else (current_date - n.f_parto::date) end as dias
                from manimales a
                left join (select codigo_rgb_id_animal_hembra as hembra,
                                  mfundos_id,
                                  max(f_parto)   as f_parto,
                                  max(nro_parto) as nro_parto
                             from mregnacimientos
                            where status = 1
                            group by 1, 2) n
                       on n.hembra = a.codigo_rgb_id_animal
                      and n.mfundos_id = a.mfundos_id
               where a.mfundos_id = " . $mfundos_id . "
                 and a.mestatus_id = 1
                 and a.sexo = 1
                 and a.f_nacimiento is not null
                 " . $cad_estatus . "
               order by a.nombre, a.codigo_rgb_id_animal;";

      foreach (Manimale::find_by_sql($sql) as $rs) {

        $edad = intval($rs->edad_meses);
        $dias = intval($rs->dias);

        if ($es_donadora) {

          /* La donadora es una hembra donante: su estado reproductivo no la inhabilita */
          $disponible = 1;
          $condicion  = 'Donadora';

        } elseif ($edad < 24) {

          $disponible = 0;
          $condicion  = 'Menor de 24 meses';

        } elseif ($dias <= 290) {

          $disponible = 0;
          $condicion  = 'Gestación reciente (' . $dias . ' días)';

        } else {

          $disponible = 1;
          $condicion  = 'Madre';
        }

        $filas[] = array(
          'codigo'       => $rs->codigo,
          'nro_arete'    => ($rs->nro_arete === null) ? '' : $rs->nro_arete,
          'nombre'       => ($rs->nombre === null) ? '' : $rs->nombre,
          'edad_meses'   => $edad,
          'condicion'    => $condicion,
          'disponible'   => $disponible,
          'nro_servicio' => 0
        );
      }
    }

    echo json_encode(array(
      'resultado' => 'ok',
      'rol'       => $rol,
      'tipo_s'    => $tipo_s,
      'filas'     => $filas,
      'total'     => count($filas)
    ));

    break;

    /// BUSCA LOS DATOS DE LA HEMBRA PARA REGISTRAR QUE DIO A LUZ

  case 'get_buscar_rgb_h':

    $buscar = intval($_POST["buscarx"]);
    $nro_arete_codigo = ($_POST["rgb_animal_h"]);


    $cadena = " ";
    $cadena_2 = "";


    if ($nro_arete_codigo == "") {

      $resp = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
          <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
          <h4>
          <i class="icon fa fa-warning"></i>
          Alerta!
          </h4>
          Debe Ingresar el Código o Numero de Arete de la HEMBRA.
          </div>');
    } else {

      session_start();
      $mfundos_id = $_SESSION['mfundos_id'];

      if ($buscar == 1) {


        $rgb_animal = $nro_arete_codigo;
        $partes = explode("-", $rgb_animal);
        $prefijo = $partes[0];
        $nro = 0;
        if ($prefijo == 'SOVCA' || $prefijo == 'BOVS') {
          $nro = intval($partes[2]);
        } else {
          $nro = intval($rgb_animal);
        }

        $cod_rgb_animal = 'SOVCA-0' . $mfundos_id . '-0' . $nro;

        $cadena = "and codigo_rgb_id_animal='$cod_rgb_animal' ";
        $cadena_2 = "and codigo_rgb_id_animal_hembra='$cod_rgb_animal' ";

        $criterio = "Código";
      } else   if ($buscar == 2) {


        $nro_arete = $nro_arete_codigo;

        $cadena = "and nro_arete='$nro_arete' ";
        $cadena_2 = "and nro_arete='$nro_arete' ";
        $criterio = "Nro de Arete";
      }

      @$data = Manimale::find_by_sql("select  id, mestatus_id as estatus, nombre as nombre_h,codigo_rgb_id_animal as cod_animal_h,f_nacimiento from Manimales where mfundos_id=" . $mfundos_id . " and mestatus_id=1 and sexo=1 and " . bovs_estatus_animal_sql(1) . " $cadena;");

      if ($data != null) {
        @$data_nacimiento = VMregnacimiento::find_by_sql("select  id from VMregnacimientos where mfundos_id=" . $mfundos_id . " $cadena_2 ;");

        @$data_nro_parto = VMregnacimiento::find_by_sql("select  max(nro_parto) as nro_parto from VMregnacimientos where mfundos_id=" . $mfundos_id . " $cadena_2 ;");
        @$data_fecha_parto = VMregnacimiento::find_by_sql("select f_parto from VMregnacimientos where mfundos_id=" . $mfundos_id . " $cadena_2;");

        $nro_parto = 0;
        $dias = 0;
        if ($data_nro_parto != null) {

          foreach ($data_nro_parto as $rs_parto) {
            $nro_parto = $rs_parto->nro_parto;
          }
        }
        $activa = false;
        if ($data_fecha_parto != null) {

          foreach ($data_fecha_parto as $rs_fparto) {
            $f_parto = $rs_fparto->f_parto;
            $activa = true;
          }
        }
        if ($activa) {
          $hoy = date('d-m-Y');
          $f_parto = (string)$f_parto->format("d-m-Y");
          $fecha1 = new DateTime($f_parto);
          $fecha2 = new DateTime($hoy);
          $diff = $fecha1->diff($fecha2);
          $dias = $diff->days;
        } else {
          $dias = 300;
        }
        $nro_parto = $nro_parto + 1;

        if ($dias <= 290) {


          $resp = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
                  <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                  <h4>
                  <i class="icon fa fa-ban"></i>
                  Alerta!
                  </h4>
                  La ' . bovs_especie_hembra(false, 'title') . ' No Cuenta con el Periodo de Gestación Recomendado. Consulte al ADMINISTRADOR DEL SISTEMA.
                  </div>');
        } else 
    if ($data != null) {

          foreach ($data as $rs) {

            $fecha_nac = $rs->f_nacimiento;
            @$fecha_nac = (string)$fecha_nac->format("Y-m-d");
            $edad = tiempo_t($fecha_nac);




            if ($edad < 24) {
              $resp = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
                  <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                  <h4>
                  <i class="icon fa fa-ban"></i>
                  Alerta!
                  </h4>
                 La Edad de la ' . bovs_especie_hembra(false, 'upper') . ' no puede ser Menor de 24 Meses.. EDAD ACTUAL  [ ' . $edad . ' ] meses !!.
                  </div>');
            } else {


              $estatus = $rs->estatus;
              if ($estatus == 1) {
                $nombre = $rs->cod_animal_h . '*' . $rs->nombre_h . '*' .  $nro_parto;
                $resp = $nombre;
              } else {
                $resp = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
                  <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                  <h4>
                  <i class="icon fa fa-ban"></i>
                  Alerta!
                  </h4>
                  El ' . $criterio . ' del Animal NO EXISTE!!.
                  </div>');
              }
            }
          }
        } else {
          $resp = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
                  <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                  <h4>
                  <i class="icon fa fa-ban"></i>
                  Alerta!
                  </h4>
                  No existe una HEMBRA con el ' . $criterio . ' [' . $cod_rgb_animal . '] !!.
                  </div>');
        }
      } else {
        $resp = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
        <h4>
        <i class="icon fa fa-ban"></i>
        Alerta!
        </h4>
        El ' . $criterio . ' del Animal NO EXISTE!!.
        </div>');
      }
    }
    echo json_encode($resp);
    break;

    //// **************************
  case 'get_buscar_rgb_h_dona':

    $buscar = intval($_POST["buscarx_dona"]);
    $nro_arete_codigo = ($_POST["rgb_animal_h_dona"]);


    $cadena = " ";
    $cadena_2 = "";


    if ($nro_arete_codigo == "") {

      $resp = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
            <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
            <h4>
            <i class="icon fa fa-warning"></i>
            Alerta!
            </h4>
            Debe Ingresar el Código o Número de Arete de la HEMBRA.
            </div>');
    } else {

      session_start();
      $mfundos_id = $_SESSION['mfundos_id'];

      if ($buscar == 1) {


        $rgb_animal = $nro_arete_codigo;
        $partes = explode("-", $rgb_animal);
        $prefijo = $partes[0];
        $nro = 0;
        if ($prefijo == 'SOVCA' || $prefijo == 'BOVS') {
          $nro = intval($partes[2]);
        } else {
          $nro = intval($rgb_animal);
        }

        $cod_rgb_animal = 'SOVCA-0' . $mfundos_id . '-0' . $nro;

        $cadena = "and codigo_rgb_id_animal='$cod_rgb_animal' ";
        $cadena_2 = "and codigo_rgb_id_animal_hembra='$cod_rgb_animal' ";

        $criterio = "Código";
      } else   if ($buscar == 2) {

        $nro_arete = $nro_arete_codigo;

        $cadena = "and nro_arete='$nro_arete' ";
        $cadena_2 = "and nro_arete='$nro_arete' ";
        $criterio = "Nro de Arete";
      }

      /* La DONADORA es una hembra donante (4 DONADORA ACTIVO / 5 DONADORA) */
      @$data = Manimale::find_by_sql("select  id, mestatus_id as estatus, nombre as nombre_h,codigo_rgb_id_animal as cod_animal_h,f_nacimiento from Manimales where mfundos_id=" . $mfundos_id . " and mestatus_id=1 and sexo=1 and " . bovs_estatus_animal_donante_sql(1) . " $cadena;");

      if ($data != null) {
        @$data_nacimiento = VMregnacimiento::find_by_sql("select  id from VMregnacimientos where mfundos_id=" . $mfundos_id . " $cadena_2 ;");

        if ($data != null) {

          foreach ($data as $rs) {


            $estatus = $rs->estatus;
            if ($estatus == 1) {
              $nombre = $rs->cod_animal_h . '*' . $rs->nombre_h;
              $resp = $nombre;
            } else {
              $resp = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
                    <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                    <h4>
                    <i class="icon fa fa-ban"></i>
                    Alerta!
                    </h4>
                    El ' . $criterio . ' del Animal NO EXISTE!!.
                    </div>');
            }
          }
        } else {
          $resp = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
                    <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                    <h4>
                    <i class="icon fa fa-ban"></i>
                    Alerta!
                    </h4>
                    No existe una HEMBRA con el ' . $criterio . ' [' . $cod_rgb_animal . '] !!.
                    </div>');
        }
      } else {
        $resp = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
          <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
          <h4>
          <i class="icon fa fa-ban"></i>
          Alerta!
          </h4>
          El ' . $criterio . ' del Animal NO EXISTE!!.
          </div>');
      }
    }
    echo json_encode($resp);
    break;

    //// **************************
  case 'add':

    $terneros = null;
    if (isset($_POST['terneros'])) {
      $terneros = json_decode((string)$_POST['terneros'], true);
      if (!is_array($terneros) || json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning">No se pudieron leer los datos de los terneros.</div>'));
        break;
      }
    }
    if (!is_array($terneros) || count($terneros) === 0) {
      $terneros = array(array(
        'id_ternero' => $id_ternero,
        'sexo' => $sexo,
        'peso_nacimiento' => $peso_nacer,
        'estatus_nacido' => $nacio,
        'aptitud' => $aptitud_animal
      ));
    }

    $ids_terneros = array();
    foreach ($terneros as $indice => &$ternero) {
      if (!is_array($ternero)) {
        echo json_encode(array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning">Cada cría debe tener sus datos completos.</div>'));
        break 2;
      }
      $ternero = array(
        'id_ternero' => strtoupper(trim((string)(isset($ternero['id_ternero']) ? $ternero['id_ternero'] : ''))),
        'sexo' => (int)(isset($ternero['sexo']) ? $ternero['sexo'] : 0),
        'peso_nacimiento' => trim((string)(isset($ternero['peso_nacimiento']) ? $ternero['peso_nacimiento'] : '')),
        'estatus_nacido' => (int)(isset($ternero['estatus_nacido']) ? $ternero['estatus_nacido'] : 0),
        'aptitud' => (int)(isset($ternero['aptitud']) ? $ternero['aptitud'] : 0)
      );
      if (!in_array($ternero['sexo'], array(1, 2), true) || !in_array($ternero['estatus_nacido'], array(1, 2), true)
        || $ternero['peso_nacimiento'] === '' || $ternero['aptitud'] <= 0) {
        echo json_encode(array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning">Completa sexo, estado, peso y aptitud de cada ternero/a.</div>'));
        break 2;
      }
      if ($ternero['id_ternero'] !== '') {
        $id_normalizado = strtoupper($ternero['id_ternero']);
        if (!preg_match('/^[A-Z0-9.\-]+$/', $id_normalizado)) {
          echo json_encode(array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning">El ID/arete solo puede contener letras, números, guion y punto.</div>'));
          break 2;
        }
        if (isset($ids_terneros[$id_normalizado])) {
          echo json_encode(array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning">No repitas el mismo ID/arete en dos crías del parto.</div>'));
          break 2;
        }
        $ids_terneros[$id_normalizado] = true;
      }
    }
    unset($ternero);

    $primer_ternero = $terneros[0];
    $id_ternero = $primer_ternero['id_ternero'];
    $sexo = $primer_ternero['sexo'];
    $peso_nacer = $primer_ternero['peso_nacimiento'];
    $nacio = $primer_ternero['estatus_nacido'];
    $aptitud_animal = $primer_ternero['aptitud'];

    if (($tipo_servicio ==3) &&  ($cod_animal_dona == "" )) {

        $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
        <h4>
        <i class="icon fa fa-warning"></i>
        Alerta!
        </h4>
        Ingrese El Código de la Hembra Donadora.
        </div>');
     
    } else
    
    if ($cod_animal_h == "") {

      $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
        <h4>
        <i class="icon fa fa-warning"></i>
        Alerta!
        </h4>
        Ingrese El Código de la Hembra.
        </div>');
    } else if ($cod_animal_m == "") {

      $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
        <h4>
        <i class="icon fa fa-warning"></i>
        Alerta!
        </h4>
        Ingrese El Código del Macho.
        </div>');
    } else if ($sexo == "") {

      $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
        <h4>
        <i class="icon fa fa-warning"></i>
        Alerta!
        </h4>
        Ingrese El Sexo del Animal.
        </div>');
    } else if ($peso_nacer == "") {

      $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
        <h4>
        <i class="icon fa fa-warning"></i>
        Alerta!
        </h4>
        Ingrese El Peso del Animal.
        </div>');
    } else {  //(A) si se escribe un nombre se consulta si ya existe...

      $consulta = Mregnacimiento::find('all', array('conditions' => array('id=?', $id_reg_nac)));

      if ($consulta == null) {  //(B) si no existe lo guarda...

        session_start();
        $usuario_id = $_SESSION['idusuariox'];
        $mfundos_id = $_SESSION['mfundos_id'];
        @$t_ganaderia = $_SESSION[ 'tipo_ganaderia' ];

        $calculo_periodo = calcularFechaSecadoNacimiento(
          $fecha_parto,
          $ira_produccion,
          $proposito,
          $mfundos_id,
          $t_ganaderia
        );
        if (!$calculo_periodo['ok']) {
          echo json_encode(array(
            'resultado' => 'error',
            'mensaje' => '<div class="alert alert-warning">' . htmlspecialchars($calculo_periodo['mensaje'], ENT_QUOTES, 'UTF-8') . '</div>'
          ));
          break;
        }
        $fecha_secado = $calculo_periodo['fecha'];

        foreach ($ids_terneros as $id_normalizado => $ignorado) {
          $id_existente = Mterneroxnacimiento::find('first', array(
            'conditions' => array('mfundos_id=? AND UPPER(id_ternero)=? AND status=1', $mfundos_id, $id_normalizado)
          ));
          if ($id_existente) {
            echo json_encode(array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning">El ID/arete ' . htmlspecialchars($id_normalizado, ENT_QUOTES, 'UTF-8') . ' ya está asociado a otro nacimiento en este fundo.</div>'));
            break 2;
          }
        }

        $hoy = date('d-m-Y');
        $sexo_a = $sexo;
        if ($sexo_a != "") {
          $sexo_a = intval($sexo);
        } else {
          $sexo_a = 0;
        }

        /// GENERAMOS EL CODIGO PARA EL ANIMAL
        //***************** */
        $data_genera_codigo = Mnacimientoxfundo::find_by_sql("SELECT count(id) as cant_animal
            FROM mnacimientoxfundos
            WHERE status=1 and mfundos_id=" . $mfundos_id . " ;");

        foreach ($data_genera_codigo as $fund_ani) {
          $cant_animal = $fund_ani->cant_animal;
        }
        if ($cant_animal > 0) {
          $contados = $cant_animal + 1;
        } else {
          $contados = 1;
        }
        $cod_rgb_animal = 'MA-0' . $mfundos_id . '-0' . $contados;
        //***************** */
   /// CALCULANDO EL PERIODO DE CELO
        //***************** */
        $data_celo = Mperiodo::find_by_sql(" select dias_gestacion as dias from mperiodos where mfundos_id=" . $mfundos_id . " and codper_bov='PPS';");
        
        foreach ($data_celo as $d_celo) {
          $dias = $d_celo->dias;
        }
        if (($dias > 0)&&($dias !="")) {
          $nro_dias = $dias;
        } else {
          $nro_dias = 60;
        }
        @$f_parto = $fecha_parto;
        @$nro_dias=intval($nro_dias);

        $fecha_celo=sumar_dias_fecha($f_parto,$nro_dias);

        //***************** */


        $reg_nac = new Mregnacimiento();

        $reg_nac->codigo_ternero = $cod_rgb_animal;
        $reg_nac->mtiposervicios_id = $tipo_servicio;

        if ($tipo_servicio !=1){
          $reg_nac->codigo_rgb_id_animal_dona = $cod_animal_dona;
        }
        $reg_nac->id_ternero = $id_ternero;
        $reg_nac->codigo_rgb_id_animal_hembra = $cod_animal_h;
        $reg_nac->codigo_rgb_id_animal_macho = $cod_animal_m;
        $reg_nac->f_parto = $fecha_parto;    
        $reg_nac->f_servicio = $fecha_celo;
        $reg_nac->peso_nacimiento = $peso_nacer;
        $reg_nac->estatus_nacido = $nacio;
        $reg_nac->mfundos_id = $mfundos_id;
        $reg_nac->aptitud = $aptitud_animal;
        $reg_nac->sexo = intval($sexo_a);
        $reg_nac->produccion_leche = $ira_produccion;
        $reg_nac->proposito = $proposito;
        $reg_nac->observaciones = $observaciones;
        $reg_nac->f_secado = $fecha_secado;
        $reg_nac->nro_parto = $nro_parto;
        $reg_nac->mtipoganaderias_id = $t_ganaderia;
        $reg_nac->user_create = $usuario_id;
        $reg_nac->created = $hoy;


        $conexion = Mregnacimiento::connection();
        $conexion->transaction();
        $servicio_gestante = null;
        if ($mregservicios_id > 0) {
          Mregservicio::find_by_sql('SELECT id FROM mregservicios WHERE id=? FOR UPDATE', array($mregservicios_id));
          $mesesGestacionSql = "(EXTRACT(YEAR FROM age(CURRENT_DATE, s.f_servicio::date)) * 12 + EXTRACT(MONTH FROM age(CURRENT_DATE, s.f_servicio::date)))";
          $serviciosValidos = Mregnacimiento::find_by_sql("SELECT s.id, s.mfundos_id, s.codigo_servicio,
                    s.codigo_rgb_id_animal_hembra, s.codigo_rgb_id_animal_macho, s.codigo_rgb_id_animal_dona,
                    s.mservicios_id, s.f_servicio
                FROM Vregservicios s
               WHERE s.id=? AND s.mfundos_id=?
                 AND s.estatus_servicio=1
                 AND COALESCE(s.estado_servicio, 1)<>2
                 AND s.estatus_ciclo=1
                 AND s.medofisiologicos_id=1
                 AND COALESCE(s.parto, 0)=0
                 AND s.f_servicio::date<=CURRENT_DATE
                 AND $mesesGestacionSql BETWEEN 7 AND 10
               LIMIT 1", array($mregservicios_id, $mfundos_id));
          if (!$serviciosValidos || count($serviciosValidos) !== 1) {
            $conexion->rollback();
            echo json_encode(array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning">El servicio ya no está disponible o no cumple las condiciones de gestación de 7 a 10 meses.</div>'));
            break;
          }
          $servicio_gestante = $serviciosValidos[0];
          $cod_animal_h = $servicio_gestante->codigo_rgb_id_animal_hembra;
          $cod_animal_m = $servicio_gestante->codigo_rgb_id_animal_macho;
          $cod_animal_dona = $servicio_gestante->codigo_rgb_id_animal_dona;
          $tipo_servicio = (int)$servicio_gestante->mservicios_id;
          $reg_nac->mregservicios_id = (int)$servicio_gestante->id;
          $reg_nac->mtiposervicios_id = $tipo_servicio;
          $reg_nac->codigo_rgb_id_animal_hembra = $cod_animal_h;
          $reg_nac->codigo_rgb_id_animal_macho = $cod_animal_m;
          if ($tipo_servicio != 1) $reg_nac->codigo_rgb_id_animal_dona = $cod_animal_dona;
        }
        if ($reg_nac->save()) {
          $detalle_guardado = true;
          foreach ($terneros as $indice => $ternero) {
            $reg_ternero = new Mterneroxnacimiento();
            $reg_ternero->mregnacimientos_id = (int)$reg_nac->id;
            $reg_ternero->mfundos_id = (int)$mfundos_id;
            $reg_ternero->nro_ternero = (int)$indice + 1;
            $reg_ternero->id_ternero = $ternero['id_ternero'];
            $reg_ternero->peso_nacimiento = $ternero['peso_nacimiento'];
            $reg_ternero->sexo = (int)$ternero['sexo'];
            $reg_ternero->estatus_nacido = (int)$ternero['estatus_nacido'];
            $reg_ternero->aptitud = (int)$ternero['aptitud'];
            $reg_ternero->created = date('Y-m-d H:i:s');
            $reg_ternero->status = 1;
            $reg_ternero->user_create = $usuario_id;
            if (!$reg_ternero->save()) {
              $detalle_guardado = false;
              break;
            }
          }

          if (!$detalle_guardado) {
            $conexion->rollback();
            $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger">No se pudieron guardar los datos de todos los terneros. No se registró el parto.</div>');
          } else {
            $reg_nac_fundo = new Mnacimientoxfundo();
            $reg_nac_fundo->mregnacimientos_id = (int)$reg_nac->id;
            $reg_nac_fundo->mfundos_id = $mfundos_id;
            $reg_nac_fundo->user_create = $usuario_id;

            if ($reg_nac_fundo->save()) {
              $servicio_actualizado = true;
              if ($servicio_gestante) {
                $serviciosCiclo = Mregservicio::find('all', array('conditions' => array(
                  'mfundos_id=? AND codigo_rgb_id_animal_hembra=? AND estatus_ciclo=1',
                  $mfundos_id,
                  $cod_animal_h
                )));
                $servicio_encontrado = false;
                foreach ($serviciosCiclo as $servicioCiclo) {
                  $servicioCiclo->estatus_ciclo = 2;
                  $servicioCiclo->estado_servicio = 2;
                  $servicioCiclo->user_modify = $usuario_id;
                  $servicioCiclo->updated = $hoy;
                  if ((int)$servicioCiclo->id === (int)$servicio_gestante->id) {
                    $servicioCiclo->parto = 1;
                    $servicioCiclo->fecha_parto = $fecha_parto;
                    $servicioCiclo->nro_terneros = count($terneros);
                    $servicio_encontrado = true;
                  }
                  if (!$servicioCiclo->save()) {
                    $servicio_actualizado = false;
                    break;
                  }
                }
                if (!$servicio_encontrado) $servicio_actualizado = false;
              }
              if ($servicio_actualizado) {
                $conexion->commit();
                $respuesta = array('resultado' => 'ok', 'mensaje' => '<div class="alert alert-success alert-dismissable">
                <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                <h4><i class="icon fa fa-check"></i> Alerta!</h4>
                Parto registrado con ' . count($terneros) . ' ternero(s).
                </div>');
              } else {
                $conexion->rollback();
                $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger">No se pudo cerrar el servicio gestante. No se registró el parto.</div>');
              }
            } else {
              $conexion->rollback();
              $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger">No se pudo asociar el parto al fundo. No se guardaron los cambios.</div>');
            }
          }
        } else {
          $conexion->rollback();
          $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
          <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
          <h4>
          <i class="icon fa fa-ban"></i>
          Alerta!
          </h4>
          Error al registrar los datos.
          </div>');
        }
        // echo json_encode($respuesta);
      }
    }
    echo json_encode($respuesta);

    break;

    //// **************************

  case 'edit':

    if (($tipo_servicio ==3) &&  ($cod_animal_dona == "" )) {

      $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
      <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
      <h4>
      <i class="icon fa fa-warning"></i>
      Alerta!
      </h4>
      Ingrese El Código de la Hembra Donadora.
      </div>');
   
  } else

    if ($cod_animal_h == "") {

      $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
        <h4>
        <i class="icon fa fa-warning"></i>
        Alerta!
        </h4>
        Ingrese El Código de la Hembra.
        </div>');
    } else if ($cod_animal_m == "") {

      $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
        <h4>
        <i class="icon fa fa-warning"></i>
        Alerta!
        </h4>
        Ingrese El Código del Macho.
        </div>');
    } else if ($sexo == "") {

      $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
        <h4>
        <i class="icon fa fa-warning"></i>
        Alerta!
        </h4>
        Ingrese El Sexo del Animal.
        </div>');
    } else if ($peso_nacer == "") {

      $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
        <h4>
        <i class="icon fa fa-warning"></i>
        Alerta!
        </h4>
        Ingrese El Peso del Animal.
        </div>');
    } else {
      //(A) si se escribe un nombre se consulta si ya existe...

      $id_reg_nac = intval($id_reg_nac);
      $consulta = Mregnacimiento::find('all', array('conditions' => array('id=?', $id_reg_nac)));

      if ($consulta != null) {

        session_start();
        $usuario_id = $_SESSION['idusuariox'];
        $mfundos_id = $_SESSION['mfundos_id'];
        $t_ganaderia = $_SESSION['tipo_ganaderia'];

        $calculo_periodo = calcularFechaSecadoNacimiento(
          $fecha_parto,
          $ira_produccion,
          $proposito,
          $mfundos_id,
          $t_ganaderia
        );
        if (!$calculo_periodo['ok']) {
          echo json_encode(array(
            'resultado' => 'error',
            'mensaje' => '<div class="alert alert-warning">' . htmlspecialchars($calculo_periodo['mensaje'], ENT_QUOTES, 'UTF-8') . '</div>'
          ));
          break;
        }
        $fecha_secado = $calculo_periodo['fecha'];

        $hoy = date('d-m-Y');


         /// CALCULANDO EL PERIODO DE CELO
        //***************** */
        $data_celo = Mperiodo::find_by_sql(" select dias_gestacion as dias from mperiodos where mfundos_id=" . $mfundos_id . " and codper_bov='PPS';");
        
        foreach ($data_celo as $d_celo) {
          $dias = $d_celo->dias;
        }
        if (($dias > 0)&&($dias !="")) {
          $nro_dias = $dias;
        } else {
          $nro_dias = 60;
        }
       
         @$f_parto = $fecha_parto;
         @$nro_dias=intval($nro_dias);

         $fecha_celo=sumar_dias_fecha($f_parto,$nro_dias);
        //***************** */




        $reg_nac = Mregnacimiento::find($id_reg_nac);

        $reg_nac->mtiposervicios_id = $tipo_servicio;

        if ($tipo_servicio !=1){
          $reg_nac->codigo_rgb_id_animal_dona = $cod_animal_dona;
        }
        $reg_nac->id_ternero = $id_ternero;

        $reg_nac->codigo_rgb_id_animal_macho = $cod_animal_m;
        $reg_nac->f_parto = $fecha_parto;
        $reg_nac->f_servicio = $fecha_celo;
        $reg_nac->peso_nacimiento = $peso_nacer;
        $reg_nac->estatus_nacido = $nacio;
        $reg_nac->aptitud = $aptitud_animal;
        $reg_nac->sexo = $sexo;
        $reg_nac->produccion_leche = $ira_produccion;
        $reg_nac->proposito = $proposito;
        $reg_nac->observaciones = $observaciones;
        $reg_nac->f_secado = $fecha_secado;
        $reg_nac->nro_parto = $nro_parto;
        $reg_nac->user_modify = $usuario_id;
        $reg_nac->updated = $hoy;


        if ($reg_nac->save()) { // da el mensaje de guardado...


          $respuesta = array('resultado' => 'ok', 'mensaje' => '<div class="alert alert-success alert-dismissable">
                <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                <h4>
                <i class="icon fa fa-check"></i>
                Alerta!
                </h4>
                Los datos han sido ACTUALIZADOS exitosamente !.
                </div>');
        } else {
          $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
              <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
              <h4>
              <i class="icon fa fa-ban"></i>
              Alerta!
              </h4>
              Error al registrar los datos.
              </div>');
        }


        //echo json_encode($respuesta);

      } else {
        $respuesta = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning">No se encontró el registro de nacimiento que intentas actualizar.</div>');
      }
    }
    
    echo json_encode($respuesta);

    break;

    /// BUSCA LOS DATOS DEL MACHO PARA SER COLOCADA EN SERVICIO

  case 'get_buscar_rgb_m':


    //// hacer las adecuaciones 

    $buscar = intval($_POST["buscarx_m"]);
    $nro_arete_codigo = ($_POST["rgb_animal_m"]);
    $tipo_s = ($_POST["tipo_s"]);



    $cadena = " ";


    if ($nro_arete_codigo == "") {

      $resp = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-warning alert-dismissable">
          <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
          <h4>
          <i class="icon fa fa-warning"></i>
          Alerta!
          </h4>
          Debe Ingresar el Código o Numero de Arete del MACHO.
          </div>');
    } else {

      session_start();
      $mfundos_id = $_SESSION['mfundos_id'];

      if ($buscar == 1) {


        $rgb_animal = $nro_arete_codigo;
        $partes = explode("-", $rgb_animal);
        $prefijo = $partes[0];
        $nro = 0;
        if ($prefijo == 'SOVCA' || $prefijo == 'BOVS') {
          $nro = intval($partes[2]);
        } else {
          $nro = intval($rgb_animal);
        }

        $cod_rgb_animal = 'SOVCA-0' . $mfundos_id . '-0' . $nro;

        $cadena = "and codigo_rgb_id_animal='$cod_rgb_animal' ";

        $criterio = "Código";
      } else   if ($buscar == 2) {


        $nro_arete = $nro_arete_codigo;

        $cadena = "and nro_arete='$nro_arete' ";
        $criterio = "Nro de Arete";
      }
      $cad_estatus="";
      if($tipo_s==2){
        $cad_estatus="and " . bovs_estatus_animal_donante_sql(2);
        $nombre_servicio="INSEMINACION ARTIFICIAL";


      }else  if($tipo_s==1){
        $cad_estatus="and estatus_animal=1";
        $nombre_servicio="MONTA";

      } else  if($tipo_s==3){
        $cad_estatus="and " . bovs_estatus_animal_donante_sql(2);
         $nombre_servicio="TRANSFERENCIA DE EMBRIONES";
      }

      @$data = Manimale::find_by_sql("select  id, mestatus_id as estatus, nombre as nombre_m,codigo_rgb_id_animal as cod_animal_m from Manimales where mfundos_id=" . $mfundos_id . " and mestatus_id=1 and sexo=2 $cadena $cad_estatus;");


      if ($data != null) {

        foreach ($data as $rs) {

          $estatus = $rs->estatus;
          if ($estatus == 1) {
            $nombre = $rs->cod_animal_m . '*' . $rs->nombre_m;
            $resp = $nombre;
          } else {
            $resp = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
                <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                <h4>
                <i class="icon fa fa-ban"></i>
                Alerta!
                </h4>
                El ' . $criterio . ' del Animal NO EXISTE!!.
                </div>');
          }
        }
      } else {
        $resp = array('resultado' => 'error', 'mensaje' => '<div class="alert alert-danger alert-dismissable">
                <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                <h4>
                <i class="icon fa fa-ban"></i>
                Alerta!
                </h4>
                No existe el MACHO con el ' . $criterio . ' [' . $cod_rgb_animal . '] para el tipo de Servicio [' . $nombre_servicio . ']!!.
                </div>');
      }
    } //Pendiente
    echo json_encode($resp);
    break;



    #*******************************************************************************

  case 'delete':

    if ($record != null) {

      session_start();
      $usuario_id = $_SESSION['idusuariox'];
      $rol = $_SESSION['rolx'];
      $hoy = date("d-m-Y");

      if ($usuario_id != "" && (( $rol == 1 )||( $rol == 4 ) )) {

        $regserv = Mregnacimiento::find($record);
        $regserv->user_modify = $usuario_id;
        $regserv->updated = $hoy;
        $regserv->status = 0;

        if ($regserv->save()) {

          echo '<div class="alert alert-success alert-dismissable">
                        <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                        <h4>
                        <i class="fa fa-check"></i>
                        Alerta!
                        </h4>
                        Registro Eliminado exitosamente...
                      </div>';
        } else {
          echo  '<div class="alert alert-danger alert-dismissable">
                      <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                      <h4>
                      <i class="icon fa fa-ban"></i>
                      Alerta!
                      </h4>
                      Disculpe! Ha ocurrido un error al eliminar el registro..
                      </div>';
        }
      } else {

        echo  '<div class="alert alert-warning alert-dismissable">
                      <button class="close" aria-hidden="true" data-dismiss="alert" type="button">×</button>
                      <h4>
                      <i class="icon fa fa-warning"></i>
                      Alerta!
                      </h4>
                      Disculpe! Usted no puede realizar esta transacción.
                      </div>';
      }
    }
    break;

    #*******************************************************************************
  case 'search':
    session_start();
    $mfundos_id = $_SESSION['mfundos_id'];

    if ($id_reg_nac != null) {

      @$data = Vmregnacimiento::find('all', array('conditions' => array('id=? and mfundos_id=?', $id_reg_nac,$mfundos_id)));

      if ($data != null) {

        foreach ($data as $rs) {
            // validar fechas
            $f_parto="";
            $f_secado="";

          if ($rs->f_parto!=""){
            $f_parto=(string)$rs->f_parto->format("d-m-Y");
          }
          if ($rs->f_secado!=""){
            $f_secado=(string)$rs->f_secado->format("d-m-Y");
          }
          


          $resp = array(
            "id" => $rs->id,
            "fichado" => $rs->fichado,
            "codigo_ternero" => $rs->codigo_ternero,
            "cod_animal_h" => $rs->codigo_rgb_id_animal_hembra,
            "nombre_animal_h" => $rs->nombre_h,
            "cod_animal_m" => $rs->codigo_rgb_id_animal_macho,
            "nombre_animal_m" => $rs->nombre_m,
            "fecha_parto" => $f_parto,
            "peso_nacer" => $rs->peso_nacimiento,
            "sexo" => $rs->sexo,
            "aptitud_animal" => $rs->aptitud,
            "nacio" => $rs->estatus_nacido,
            "nro_parto" => $rs->nro_parto,
            "fecha_secado" => $f_secado,
            "observaciones" => $rs->observaciones,
            "ira_produccion" => $rs->produccion_leche,
            "id_ternero" => $rs->id_ternero,
            "cod_animal_dona" => $rs->codigo_rgb_id_animal_dona,
            "tipo_servicio" => $rs->mtiposervicios_id,
            "proposito" => $rs->proposito
          );
        }

        echo json_encode($resp);
      }
    }
    break;

    ////////// Buscar el ID asignado al ternero

    case 'search_ternero':
      session_start();
      $mfundos_id = $_SESSION['mfundos_id'];
  
      if ($id_reg_nac != null) {
  
        @$data = Vmregnacimiento::find('all', array('conditions' => array('id=? and mfundos_id=?', $id_reg_nac,$mfundos_id)));
  
        if ($data != null) {
  
          foreach ($data as $rs) {
              // validar fechas
              $f_secado="";
  
            if ($rs->f_parto!=""){
              $f_parto=(string)$rs->f_parto->format("d-m-Y");
            }
           
              $resp = array(
              "id" => $rs->id,
              "cod_animal_h" => $rs->codigo_rgb_id_animal_hembra,
              "nombre_animal_h" => $rs->nombre_h,
              "cod_animal_m" => $rs->codigo_rgb_id_animal_macho,
              "nombre_animal_m" => $rs->nombre_m,
              "fecha_parto" => $f_parto,
              "peso_nacer" => $rs->peso_nacimiento,
              "sexo" => $rs->sexo,
              "aptitud_animal" => $rs->aptitud,
              "observaciones" => $rs->observaciones,
              "codigo_ternero" => $rs->codigo_ternero,
              "id_ternero" => $rs->id_ternero,
              "tipo_servicio" => $rs->mtiposervicios_id
            );
          }
              @$mregservicios_id = (int)(isset($_POST["mregservicios_id"]) ? $_POST["mregservicios_id"] : 0);
  
          echo json_encode($resp);
        }
      }
      break;
  
      ////////// Buscar el ID asignado al ternero
  
    
  
  
    case 'search_id_ternero':
    session_start();

    $id_ternero = ($_POST["id_ternero"]); // Código que le asigna el productor
    $codigo_ternero = ($_POST["codigo_ternero"]); // Código que genera el sistema de forma automática



    $usuario_id = $_SESSION['idusuariox'];
    $mfundos_id = $_SESSION['mfundos_id']; // Cargar esta variable en el starsession inicial


    if ($id_ternero != null) {

      if ($codigo_ternero != null) {
        @$data = Mregnacimiento::find_by_sql("SELECT id_ternero,codigo_ternero
        FROM Mregnacimientos
        WHERE  id_ternero='$id_ternero' 
         and	id_ternero NOT  in (select a.id_ternero FROM Mregnacimientos a
          WHERE a.id_ternero='$id_ternero' and a.codigo_ternero='$codigo_ternero'
           and  a.status=1 and a.mfundos_id=" . $mfundos_id . ") and  status=1 and mfundos_id=" . $mfundos_id . ";");


        if ($data != null) {

          foreach ($data as $rs) {

            $resp = array(
              "id_ternero" => $rs->id_ternero,
              "codigo_ternero" => $rs->codigo_ternero

            );
          }
        } else {
          $resp = array(
            "id_ternero" => 0,
            "codigo_ternero" => 0

          );
        }
      } else {
        $data = Mterneroxnacimiento::find('first', array(
          'conditions' => array('mfundos_id=? AND UPPER(id_ternero)=? AND status=1', (int)$mfundos_id, strtoupper(trim($id_ternero)))
        ));
        $resp = $data
          ? array('id_ternero' => $data->id_ternero, 'codigo_ternero' => 1)
          : array('id_ternero' => 0, 'codigo_ternero' => 0);
      }



      echo json_encode($resp);
    }
    break;

    #*******************************************************************************
  case 'search_desinc_nac':
    session_start();
    $mfundos_id = $_SESSION['mfundos_id'];

    if ($id_reg_nac != null) {

      @$data = Vmregnacimiento::find('all', array('conditions' => array('id=? and mfundos_id=?', $id_reg_nac,$mfundos_id)));

      if ($data != null) {

          
foreach ($data as $rs) {

          $resp = array(
            "id" => $rs->id,
            "fichado" => $rs->fichado,
            "codigo_ternero" => $rs->codigo_ternero


          );
        }
           }

        echo json_encode($resp);
      }
    
    break;

        #*******************************************************************************


  case 'search_nac_exc':

  $cod_ternero = ($_POST["cod_ternero"]); // Código que le asigna el productor



    if ($cod_ternero != null) {

      @$data = Mdsinficha::find('all', array('conditions' => array('id=?', $cod_ternero)));

      if ($data != null) {

          
foreach ($data as $rs) {

       $f_desincorporacion="";

     if ($rs->f_desincorporacion != '') {
                        $f_desincorporacion = (string)$rs->f_desincorporacion->format('d-m-Y');
                    }
          $resp = array(
             "id" => $rs->id,
             "codigo_ternero" => $rs->codigo_ternero,
             "fecha_desincorporacion" => $f_desincorporacion,
             "causa_mortalidad_animal" => $rs->causa_mortalidad,
             "tipo_des" => $rs->tipo_desincorporacion,
             "causa_descarte" => $rs->causa_descarte,
              "observaciones" => $rs->observaciones
             

          );
      
           }

        echo json_encode($resp);
      }
        }
    
    break;
} //end switch


function tiempo_t($fecha_nac)
{
  $fecha_f = date("Y-m-d");
  $fecha_i = $fecha_nac;
  $dias = (strtotime($fecha_i) - strtotime($fecha_f)) / 86400;
  $dias = abs($dias);
  $dias = floor($dias);
  $meses = ($dias / 30);
  $meses = (int)$meses;

  return $meses;
}

function sumar_dias_fecha($f_parto,$nro_dias)
{

$date_future = strtotime('+ 60 day', strtotime($f_parto));
// $date_future = strtotime('+ '.$nro_dias.' day', strtotime($f_parto));

$date_future = date('d-m-Y', $date_future);
return $date_future;
}