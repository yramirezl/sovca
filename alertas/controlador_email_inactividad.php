<?php

/**
 * =====================================================================
 * AVISO DE INACTIVIDAD DE FUNDOS  (se ejecuta por cron)
 * =====================================================================
 *
 * Regla:
 *   Si NINGÚN usuario activo del fundo (productor y ayudantes) registró
 *   conexión en los últimos N días (por defecto 7), se le envía un correo
 *   al PRODUCTOR (dueño del fundo) expresando preocupación por la
 *   inactividad del fundo y ofreciéndole ayuda.
 *
 * Agrupación (productores multifundo):
 *   Los fundos inactivos se agrupan POR PRODUCTOR: un productor con varios
 *   fundos recibe UN SOLO correo con una sección por cada fundo (en lugar de
 *   un correo por fundo). El control de repetidos sigue siendo por fundo.
 *
 * Condiciones (las dos se deben cumplir):
 *   - El fundo está activo:  mfundos.status = 1
 *   - El productor dueño está activo:  mfundos.user_propietario apunta a
 *     un usuario con mroles_id = 2 y status = 1, con correo registrado.
 *   - El fundo tiene al menos un usuario activo vinculado
 *     (mfundosxusuarios.status = 1 y musuarios.status = 1).
 *
 * Fuente de actividad:
 *   mrastreosessiones (misma información que el listado
 *   views/conexiones/index.php) tomando la conexión más reciente de
 *   cualquiera de los usuarios activos del fundo.
 *
 * Control de repetidos:
 *   Tabla malertas_inactividad_estado (una fila por fundo). No se vuelve
 *   a escribir al mismo fundo si ya se le avisó dentro de los mismos N
 *   días, salvo que se ejecute con --forzar.
 *
 * Uso:
 *   php controlador_email_inactividad.php                 (envía)
 *   php controlador_email_inactividad.php --dry-run       (solo informa, NO envía)
 *   php controlador_email_inactividad.php --dry-run --html  (muestra el correo a enviar)
 *   php controlador_email_inactividad.php --dias=10       (cambia el umbral)
 *   php controlador_email_inactividad.php --limite=20     (máximo de correos por corrida)
 *   php controlador_email_inactividad.php --forzar        (ignora el control de repetidos)
 *
 * Cron sugerido (todos los días a las 6:00 a.m.):
 *   0 6 * * * cd /var/www/html/sovca/alertas && php controlador_email_inactividad.php >> /var/log/sovca_inactividad.log 2>&1
 *
 * Requiere la tabla malertas_inactividad_estado:
 *   scripts/migracion_alerta_inactividad.sql
 * =====================================================================
 */

require_once 'conexion.php';
require_once 'enviar_inactividad.php';
date_default_timezone_set('America/Caracas');

/* Correo interno del equipo para recibir el resumen de cada corrida.
   Dejar vacío para no enviar resumen (o usar --resumen=correo). */
define('ALERTA_INACTIVIDAD_RESUMEN', '');

/* ---------------------------------------------------------------- */
/* Solo por línea de comandos: si alguien abre la URL, no se ejecuta  */
/* ---------------------------------------------------------------- */
if (PHP_SAPI !== 'cli') {
  header('HTTP/1.1 403 Forbidden', true, 403);
  echo 'Este proceso se ejecuta por cron (línea de comandos).';
  exit;
}

$opciones = getopt('', ['dias::', 'limite::', 'resumen::', 'dry-run', 'html', 'forzar', 'ayuda', 'help']);
$opciones = is_array($opciones) ? $opciones : [];

if (isset($opciones['ayuda']) || isset($opciones['help'])) {
  echo "Uso: php controlador_email_inactividad.php [--dry-run] [--html] [--dias=N] [--limite=N] [--forzar] [--resumen=correo]\n";
  exit(0);
}

$DIAS      = (isset($opciones['dias']) && $opciones['dias'] !== false && $opciones['dias'] !== '')
  ? max(1, (int) $opciones['dias']) : 7;
$LIMITE    = (isset($opciones['limite']) && $opciones['limite'] !== false && $opciones['limite'] !== '')
  ? max(0, (int) $opciones['limite']) : 0; // 0 = sin límite
$DRY_RUN   = isset($opciones['dry-run']);
$VER_HTML  = isset($opciones['html']);
$FORZAR    = isset($opciones['forzar']);
$RESUMEN   = (isset($opciones['resumen']) && is_string($opciones['resumen']) && trim($opciones['resumen']) !== '')
  ? trim($opciones['resumen'])
  : ALERTA_INACTIVIDAD_RESUMEN;

$LOG = function ($mensaje) {
  $linea = date('Y-m-d H:i:s') . ' [inactividad] ' . $mensaje;
  echo $linea . PHP_EOL;
  error_log($linea);
};

/* ------------------------------------------------------------------ */
/* Detección de fundos inactivos                                       */
/* ------------------------------------------------------------------ */
$SQL_CANDIDATOS = "SELECT f.id                                     AS mfundos_id,
                          btrim(f.nombre)                          AS fundo,
                          coalesce(btrim(f.cid_rif), '')           AS rif,
                          p.id                                     AS productor_id,
                          btrim(p.name)                            AS productor,
                          p.username                               AS productor_username,
                          btrim(p.email)                           AS email,
                          ult.ultima_conexion                      AS ultima_conexion,
                          coalesce(ult.usuarios_activos, 0)        AS usuarios_activos,
                          CASE WHEN ult.ultima_conexion IS NULL THEN NULL
                               ELSE (CURRENT_DATE - ult.ultima_conexion::date) END AS dias
                     FROM mfundos f
                     JOIN musuarios p
                       ON p.id = f.user_propietario
                      AND p.mroles_id = 2
                      AND p.status = 1
                     LEFT JOIN LATERAL (
                          SELECT max(r.fecha + r.hora)             AS ultima_conexion,
                                 count(DISTINCT l.musuarios_id)    AS usuarios_activos
                            FROM mfundosxusuarios l
                            JOIN musuarios u
                              ON u.id = l.musuarios_id
                             AND u.status = 1
                            LEFT JOIN mrastreosessiones r
                              ON r.id_musuario = l.musuarios_id
                           WHERE l.mfundos_id = f.id
                             AND l.status = 1
                     ) ult ON TRUE
                    WHERE f.status = 1
                      AND coalesce(btrim(p.email), '') <> ''
                      AND coalesce(ult.usuarios_activos, 0) > 0
                      AND (ult.ultima_conexion IS NULL
                           OR ult.ultima_conexion < (CURRENT_DATE - ($1::int || ' days')::interval))
                    ORDER BY ult.ultima_conexion ASC NULLS FIRST";

/* Usuarios activos del fundo con su última conexión (detalle del correo) */
$SQL_USUARIOS = "SELECT u.username                      AS username,
                        btrim(u.name)                   AS nombre,
                        u.mroles_id                     AS rol,
                        max(r.fecha + r.hora)           AS ultima_conexion
                   FROM mfundosxusuarios l
                   JOIN musuarios u
                     ON u.id = l.musuarios_id
                    AND u.status = 1
                   LEFT JOIN mrastreosessiones r
                     ON r.id_musuario = u.id
                  WHERE l.mfundos_id = $1
                    AND l.status = 1
                  GROUP BY u.id, u.username, u.name, u.mroles_id
                  ORDER BY u.mroles_id, u.username";

/* Control de repetidos */
$SQL_ESTADO = "SELECT ultimo_envio,
                      (ultimo_envio IS NOT NULL
                       AND ultimo_envio > (now() - ($2::int || ' days')::interval)) AS avisado_reciente
                 FROM malertas_inactividad_estado
                WHERE mfundos_id = $1";

$SQL_GUARDAR_ESTADO = "INSERT INTO malertas_inactividad_estado
                             (mfundos_id, ultimo_envio, ultima_conexion, dias_inactividad, correo_enviado, updated)
                       VALUES ($1, now(), $2, $3, $4, now())
                       ON CONFLICT (mfundos_id) DO UPDATE
                             SET ultimo_envio     = EXCLUDED.ultimo_envio,
                                 ultima_conexion  = EXCLUDED.ultima_conexion,
                                 dias_inactividad = EXCLUDED.dias_inactividad,
                                 correo_enviado   = EXCLUDED.correo_enviado,
                                 updated          = now()";

/* ------------------------------------------------------------------ */
/* Ejecución                                                           */
/* ------------------------------------------------------------------ */
$con = new conexion();
$enviar_correo = new Enviar_Correo();

$revisados = 0;
$candidatos = 0;
$correos = 0;
$enviados = 0;
$avisados = 0;
$omitidos = 0;
$errores = 0;
$detalle = [];

if (!$con->CONNECT()) {
  $LOG('ERROR: no se pudo conectar a la base de datos. Se cancela la corrida.');
  exit(1);
}

/* Sin la tabla de control se podrían repetir los avisos a diario: no se sigue. */
$rsTabla = pg_query($con->obj_conec, "SELECT to_regclass('public.malertas_inactividad_estado') IS NOT NULL AS existe");
$tabla_existe = $rsTabla ? (bool) pg_fetch_result($rsTabla, 0, 'existe') : false;
if (!$tabla_existe) {
  $LOG('ERROR: falta la tabla malertas_inactividad_estado. Aplique scripts/migracion_alerta_inactividad.sql y repita la corrida.');
  $con->DISCONECT();
  exit(1);
}

$LOG('Inicio de la corrida. Días de inactividad: ' . $DIAS
  . ($DRY_RUN ? ' (modo revisión: NO se envían correos)' : '')
  . ($FORZAR ? ' (forzado: ignora el control de repetidos)' : ''));

$rs = pg_query_params($con->obj_conec, $SQL_CANDIDATOS, [$DIAS]);

if (!$rs) {
  $LOG('ERROR: no se pudo consultar los fundos inactivos: ' . pg_last_error($con->obj_conec));
  $con->DISCONECT();
  exit(1);
}

/* ------------------------------------------------------------------ */
/* 1) Detección: los fundos inactivos se AGRUPAN POR PRODUCTOR, de modo  */
/*    que un productor multifundo reciba UN SOLO correo con todos sus    */
/*    fundos en lugar de un correo por fundo.                            */
/* ------------------------------------------------------------------ */
$grupos = array(); // email del productor => ['productor'=>.., 'fundos'=>[], 'pendientes'=>[]]

while ($c = pg_fetch_assoc($rs)) {
  $revisados++;
  $mfundos_id = (int) $c['mfundos_id'];
  $email      = strtolower(trim($c['email']));
  $diasCalc   = is_null($c['dias']) ? $DIAS : (int) $c['dias'];

  /* ¿Ya se le avisó a este fundo hace poco? (el control es por fundo) */
  if (!$FORZAR) {
    $rsEstado = pg_query_params($con->obj_conec, $SQL_ESTADO, [$mfundos_id, $DIAS]);
    if ($rsEstado && pg_num_rows($rsEstado) > 0) {
      $estado = pg_fetch_assoc($rsEstado);
      if (!empty($estado['avisado_reciente'])) {
        $omitidos++;
        $detalle[] = 'OMITIDO  fundo ' . $mfundos_id . ' (' . $c['fundo'] . '): ya avisado el ' . $estado['ultimo_envio'];
        continue;
      }
    }
  }

  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errores++;
    $detalle[] = 'ERROR    fundo ' . $mfundos_id . ' (' . $c['fundo'] . '): correo inválido (' . $email . ')';
    continue;
  }

  /* Detalle de usuarios del fundo */
  $usuarios = [];
  $rsUsu = pg_query_params($con->obj_conec, $SQL_USUARIOS, [$mfundos_id]);
  if ($rsUsu) {
    while ($u = pg_fetch_assoc($rsUsu)) {
      $usuarios[] = [
        'username'        => $u['username'],
        'nombre'          => $u['nombre'],
        'rol'             => (int) $u['rol'],
        'ultima_conexion' => $u['ultima_conexion']
          ? date('d-m-Y H:i', strtotime($u['ultima_conexion'])) : null,
      ];
    }
  }

  if (!isset($grupos[$email])) {
    $grupos[$email] = array(
      'productor'  => $c['productor'],
      'fundos'     => array(),
      'pendientes' => array(),
    );
  }

  $grupos[$email]['fundos'][] = array(
    'mfundos_id'      => $mfundos_id,
    'fundo'           => $c['fundo'],
    'rif'             => $c['rif'],
    'dias'            => $diasCalc,
    'ultima_conexion' => $c['ultima_conexion']
      ? date('d-m-Y H:i', strtotime($c['ultima_conexion'])) : null,
    'usuarios'        => $usuarios,
  );

  $grupos[$email]['pendientes'][] = array(
    'mfundos_id'      => $mfundos_id,
    'fundo'           => $c['fundo'],
    'ultima_conexion' => $c['ultima_conexion'],
    'dias'            => $diasCalc,
  );

  $candidatos++;
}

$LOG('Fundos con inactividad detectados: ' . $candidatos
  . ' | productores a notificar: ' . count($grupos) . ' (un correo por productor)');

/* ------------------------------------------------------------------ */
/* 2) Envío: un correo por productor, con una sección por cada fundo.  */
/* ------------------------------------------------------------------ */
foreach ($grupos as $email => $grupo) {
  $n_fundos = count($grupo['fundos']);
  $ids      = array();
  foreach ($grupo['pendientes'] as $p) {
    $ids[] = $p['mfundos_id'];
  }
  $ids_txt = implode(', ', $ids);

  $datos = array(
    'productor' => $grupo['productor'],
    'fundos'    => $grupo['fundos'],
  );

  /* Modo revisión: solo informar */
  if ($DRY_RUN) {
    $detalle[] = 'CANDIDATO ' . $email . ' (' . $grupo['productor'] . ') → ' . $n_fundos
      . ' fundo(s) en UN correo [' . $ids_txt . ']';

    if ($VER_HTML && $correos === 0) {
      echo str_repeat('=', 78) . PHP_EOL;
      echo 'Correo que se enviaría a ' . $email . ' (' . $n_fundos . ' fundo/s): ' . $ids_txt . PHP_EOL;
      echo str_repeat('=', 78) . PHP_EOL;
      echo $enviar_correo->construir_cuerpo($datos) . PHP_EOL;
    }

    $correos++;
    if ($LIMITE > 0 && $correos >= $LIMITE) {
      $detalle[] = 'INFO     se alcanzó el límite de ' . $LIMITE . ' correo(s) por corrida.';
      break;
    }
    continue;
  }

  /* Envío real */
  if ($enviar_correo->enviar_inactividad($email, $datos)) {
    $enviados++;

    /* El aviso se registra fundo por fundo (control de repetidos por fundo) */
    foreach ($grupo['pendientes'] as $p) {
      $rsGuarda = pg_query_params($con->obj_conec, $SQL_GUARDAR_ESTADO, array(
        $p['mfundos_id'],
        $p['ultima_conexion'],
        $p['dias'],
        $email,
      ));

      if (!$rsGuarda) {
        $errores++;
        $detalle[] = 'ERROR    fundo ' . $p['mfundos_id'] . ': no se pudo registrar el envío: ' . pg_last_error($con->obj_conec);
      } else {
        $avisados++;
      }
    }

    $detalle[] = 'ENVIADO  ' . $email . ' (' . $grupo['productor'] . ') → ' . $n_fundos
      . ' fundo(s) en un solo correo [' . $ids_txt . ']';
  } else {
    $errores++;
    $detalle[] = 'ERROR    ' . $email . ' (' . $grupo['productor'] . ') → ' . $n_fundos
      . ' fundo(s): no se pudo enviar el correo (' . $enviar_correo->ErrorInfo . ')';
  }

  $correos++;
  if ($LIMITE > 0 && $correos >= $LIMITE) {
    $detalle[] = 'INFO     se alcanzó el límite de ' . $LIMITE . ' correo(s) por corrida.';
    break;
  }
}

if (!$DRY_RUN && $RESUMEN !== '' && ($enviados > 0 || $errores > 0 || $candidatos > 0)) {
  $resumen_ok = $enviar_correo->enviar_resumen($RESUMEN, [
    'revisados' => $candidatos,
    'enviados'  => $enviados,
    'avisados'  => $avisados,
    'omitidos'  => $omitidos,
    'errores'   => $errores,
    'dias'      => $DIAS,
    'detalle'   => implode('<br>', array_map('htmlspecialchars', $detalle)),
  ]);
  $LOG('Resumen interno ' . ($resumen_ok ? 'enviado' : 'NO enviado') . ' a ' . $RESUMEN);
}

if ($DRY_RUN) {
  $LOG('Fundos inactivos detectados: ' . $candidatos
    . ' | correos a enviar (agrupados por productor): ' . $correos
    . ' | fundos omitidos por aviso reciente: ' . $omitidos
    . ' | errores: ' . $errores);
} else {
  $LOG('Fin de la corrida. Fundos candidatos: ' . $candidatos
    . ' | correos enviados: ' . $enviados
    . ' | fundos avisados: ' . $avisados
    . ' | fundos omitidos: ' . $omitidos
    . ' | errores: ' . $errores);
}

if (!empty($detalle)) {
  echo str_repeat('-', 78) . PHP_EOL;
  foreach ($detalle as $linea) {
    echo $linea . PHP_EOL;
  }
  echo str_repeat('-', 78) . PHP_EOL;
}

pg_free_result($rs);
$con->DISCONECT();
unset($con);
exit(0);
