<?php
/**
 * =====================================================================
 * lib/mensajes_demo_helper.php — CORREOS INFORMATIVOS A USUARIOS DEMO
 * ---------------------------------------------------------------------
 * Reúne los correos de quienes crearon una cuenta de demostración y
 * permite enviarles un mensaje (invitación a comprar la cuenta oficial).
 *
 * Reglas del listado:
 *   · una cuenta demo vigente y una caducada cuentan igual: el correo se
 *     conserva aunque la cuenta haya vencido, porque el objetivo es
 *     recuperar al visitante;
 *   · NO se muestran las cuentas ya migradas al sistema oficial (ya son
 *     clientes) ni las cerradas por el administrador sin comprar;
 *   · un correo aparece una sola vez (la demostración es una por correo).
 *
 * La información vive en la base demo (sovca_demo) y la bitácora de
 * envíos se guarda en la tabla demo_mensajes de esa misma base: la base
 * oficial no se toca.
 *
 * Lo usan views/mensajesdemo/index.php y controllers/mensajes_demo_controller.php.
 * =====================================================================
 */

require_once __DIR__ . '/../config/demo.php';
require_once __DIR__ . '/demo_helper.php';
require_once __DIR__ . '/mailer_helper.php';

if (!function_exists('bovs_msj_demo_recortar')) {
    /**
     * Recorta sin partir un carácter (no todos los servidores tienen
     * mbstring; el proyecto lo usa siempre con respaldo).
     *
     * @param string $texto
     * @param int    $largo
     * @return string
     */
    function bovs_msj_demo_recortar($texto, $largo)
    {
        $texto = (string) $texto;
        return function_exists('mb_substr')
            ? mb_substr($texto, 0, (int) $largo)
            : substr($texto, 0, (int) $largo);
    }
}

if (!function_exists('bovs_msj_demo_preparar')) {
    /**
     * Crea la bitácora de envíos si falta. Idempotente y silenciosa: si
     * el usuario de la base no puede crear tablas, el módulo sigue
     * enviando correos, solo que sin historial.
     *
     * @param PDO|null $pdo
     * @return bool
     */
    function bovs_msj_demo_preparar($pdo = null)
    {
        if ($pdo === null) {
            $pdo = bovs_demo_pdo();
        }

        $sentencias = array(
            "CREATE TABLE IF NOT EXISTS demo_mensajes (
                id          serial PRIMARY KEY,
                email       varchar(160) NOT NULL,
                asunto      varchar(180) NOT NULL,
                estado      varchar(10)  NOT NULL DEFAULT 'ok',
                error       varchar(300),
                admin_id    integer,
                created     timestamp without time zone DEFAULT now()
            )",
            "CREATE INDEX IF NOT EXISTS idx_demo_mensajes_email
                ON demo_mensajes (lower(email), created DESC)",
        );

        $ok = true;
        foreach ($sentencias as $sql) {
            try {
                $pdo->exec($sql);
            } catch (Throwable $e) {
                $ok = false;
                error_log('[SOVCA mensajes demo] preparar: ' . $e->getMessage());
            }
        }

        return $ok;
    }
}

if (!function_exists('bovs_msj_demo_secuencia')) {
    /**
     * Etapas de la secuencia automática ya registradas por cuenta.
     *
     * La escribe lib/demo_correos_auto_helper.php (cron y bienvenida). Si
     * la tabla no existe todavía se devuelve vacío: el listado manual no
     * depende de la automatización.
     *
     * @param PDO|null $pdo
     * @return array usuario_id => array de ['etapa','estado','enviado']
     */
    function bovs_msj_demo_secuencia($pdo = null)
    {
        if ($pdo === null) {
            if (!bovs_demo_bd_disponible()) {
                return array();
            }
            $pdo = bovs_demo_pdo();
        }

        try {
            $filas = $pdo->query('
                SELECT musuarios_id, etapa, estado, enviado
                  FROM demo_correos_auto
                 ORDER BY id
            ')->fetchAll();
        } catch (Throwable $e) {
            return array();
        }

        $salida = array();
        foreach ($filas as $f) {
            $salida[(int) $f['musuarios_id']][] = array(
                'etapa'   => (string) $f['etapa'],
                'estado'  => (string) $f['estado'],
                'enviado' => substr((string) $f['enviado'], 0, 10),
            );
        }

        return $salida;
    }
}

if (!function_exists('bovs_msj_demo_listar')) {
    /**
     * Correos a los que se les puede escribir.
     *
     * Se parte de demo_registros (la bitácora que se escribe al crear la
     * cuenta demo) y no de musuarios: así el correo se conserva aunque la
     * cuenta se haya desactivado o purgado al vencer.
     *
     * @return array Lista de leads (uno por correo).
     */
    function bovs_msj_demo_listar()
    {
        if (!bovs_demo_bd_disponible()) {
            return array();
        }

        $pdo = bovs_demo_pdo();
        bovs_demo_preparar($pdo);
        bovs_msj_demo_preparar($pdo);

        $sql = "
            SELECT r.id                                AS registro_id,
                   r.musuarios_id,
                   r.mfundos_id,
                   (current_date - r.created::date)    AS dias_desde,
                   upper(COALESCE(r.username, u.username, '')) AS username,
                   COALESCE(NULLIF(u.email, ''), NULLIF(r.email, ''), '') AS email,
                   COALESCE(NULLIF(u.name, ''), r.username, '') AS nombre,
                   COALESCE(f.nombre, '')              AS fundo_nombre,
                   COALESCE(r.fecha_caduca::text, v.caduca::text, '') AS caduca,
                   r.created                           AS registrado,
                   COALESCE(r.migrado, false)          AS migrado,
                   COALESCE(r.migrado_a_usuario, 0)    AS migrado_a_usuario,
                   COALESCE(r.cerrada, false)          AS cerrada,
                   COALESCE(u.status, 0)               AS usuario_status
              FROM demo_registros r
              LEFT JOIN musuarios u ON u.id = r.musuarios_id
              LEFT JOIN mfundos f   ON f.id = r.mfundos_id
              LEFT JOIN LATERAL (
                    SELECT p.fecha_caduca_suscripcion::date AS caduca
                      FROM mplanesxfundo p
                     WHERE p.musuarios_id = r.musuarios_id
                       AND p.msuscripciones_id IS NOT NULL
                     ORDER BY p.id DESC LIMIT 1
              ) v ON true
             ORDER BY r.id DESC
        ";

        try {
            $filas = $pdo->query($sql)->fetchAll();
        } catch (Throwable $e) {
            error_log('[SOVCA mensajes demo] listar: ' . $e->getMessage());
            return array();
        }

        $hoy   = date('Y-m-d');
        $leads = array();

        foreach ($filas as $f) {
            /* Migrada al sistema oficial (o cerrada por el administrador):
               ya no es un usuario demo al que haya que convencer. */
            if (!empty($f['migrado'])) {
                continue;
            }

            $email = strtolower(trim((string) $f['email']));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $caduca = substr(trim((string) $f['caduca']), 0, 10);
            $dias   = null;
            if ($caduca !== '') {
                $dias = (int) round((strtotime($caduca . ' 12:00:00') - strtotime($hoy . ' 12:00:00')) / 86400);
            }

            $vigente = ($caduca !== '' && $caduca >= $hoy && (int) $f['usuario_status'] === 1);

            $lead = array(
                'email'      => $email,
                'nombre'     => trim((string) $f['nombre']),
                'usuario'    => trim((string) $f['username']),
                'fundo'      => trim((string) $f['fundo_nombre']),
                'vence'      => $caduca,
                'dias'       => $dias,
                'estado'     => $vigente ? 'vigente' : 'caducada',
                'registrado' => substr((string) $f['registrado'], 0, 10),
                'usuario_id'  => (int) $f['musuarios_id'],
                'fundo_id'    => (int) $f['mfundos_id'],
                /* Días completos desde el alta, calculados en la base: así no
                   dependen de la zona horaria del proceso que consulta. */
                'dias_desde'  => (int) $f['dias_desde'],
            );

            /* Un correo = una demostración, pero por datos heredados puede
               haber varias filas: gana la más reciente y, si alguna sigue
               vigente, el correo se muestra como vigente. */
            if (isset($leads[$email])) {
                if ($vigente) {
                    $leads[$email]['estado'] = 'vigente';
                    $leads[$email]['vence']  = $caduca;
                    $leads[$email]['dias']   = $dias;
                }
                continue;
            }

            $leads[$email] = $lead;
        }

        /* Último envío de cada correo (si la bitácora existe). */
        $historial = bovs_msj_demo_ultimos_envios($pdo);
        $secuencia = bovs_msj_demo_secuencia($pdo);

        foreach ($leads as $email => &$l) {
            $l['ultimo_envio']  = isset($historial[$email]) ? $historial[$email]['created'] : '';
            $l['ultimo_estado'] = isset($historial[$email]) ? $historial[$email]['estado'] : '';
            $l['secuencia']     = isset($secuencia[$l['usuario_id']]) ? $secuencia[$l['usuario_id']] : array();
        }
        unset($l);

        usort($leads, function ($a, $b) {
            return strcmp((string) $b['registrado'], (string) $a['registrado']);
        });

        return array_values($leads);
    }
}

if (!function_exists('bovs_msj_demo_ultimos_envios')) {
    /**
     * Último envío por correo.
     *
     * @param PDO|null $pdo
     * @return array email => ['created' => 'Y-m-d H:i', 'estado' => 'ok'|'error']
     */
    function bovs_msj_demo_ultimos_envios($pdo = null)
    {
        if ($pdo === null) {
            if (!bovs_demo_bd_disponible()) {
                return array();
            }
            $pdo = bovs_demo_pdo();
        }

        try {
            $filas = $pdo->query("
                SELECT DISTINCT ON (lower(email))
                       lower(email) AS email,
                       estado,
                       created
                  FROM demo_mensajes
                 ORDER BY lower(email), created DESC, id DESC
            ")->fetchAll();
        } catch (Throwable $e) {
            return array();
        }

        $salida = array();
        foreach ($filas as $f) {
            $salida[(string) $f['email']] = array(
                'created' => substr((string) $f['created'], 0, 16),
                'estado'  => (string) $f['estado'],
            );
        }

        return $salida;
    }
}

if (!function_exists('bovs_msj_demo_historial')) {
    /**
     * Últimos envíos (para la bitácora de la pantalla).
     *
     * @param int $limite
     * @return array
     */
    function bovs_msj_demo_historial($limite = 40)
    {
        if (!bovs_demo_bd_disponible()) {
            return array();
        }

        $limite = max(1, min(200, (int) $limite));

        try {
            $pdo = bovs_demo_pdo();
            bovs_msj_demo_preparar($pdo);

            return $pdo->query("
                SELECT id, email, asunto, estado, COALESCE(error, '') AS error, created
                  FROM demo_mensajes
                 ORDER BY id DESC
                 LIMIT " . $limite . "
            ")->fetchAll();
        } catch (Throwable $e) {
            return array();
        }
    }
}

if (!function_exists('bovs_msj_demo_registrar')) {
    /**
     * Anota un envío en la bitácora. Nunca interrumpe el envío.
     *
     * @param string $email
     * @param string $asunto
     * @param string $estado 'ok' | 'error'
     * @param string $error
     * @param int    $adminId
     * @return void
     */
    function bovs_msj_demo_registrar($email, $asunto, $estado, $error = '', $adminId = 0)
    {
        try {
            $pdo = bovs_demo_pdo();
            bovs_msj_demo_preparar($pdo);

            $st = $pdo->prepare('
                INSERT INTO demo_mensajes (email, asunto, estado, error, admin_id)
                VALUES (?, ?, ?, ?, ?)
            ');
            $st->execute(array(
                bovs_msj_demo_recortar($email, 160),
                bovs_msj_demo_recortar($asunto, 180),
                ($estado === 'ok') ? 'ok' : 'error',
                ($error === '') ? null : bovs_msj_demo_recortar($error, 300),
                ((int) $adminId) > 0 ? (int) $adminId : null,
            ));
        } catch (Throwable $e) {
            error_log('[SOVCA mensajes demo] bitácora: ' . $e->getMessage());
        }
    }
}

if (!function_exists('bovs_msj_demo_asunto_sugerido')) {
    /**
     * Asunto con el que arranca el formulario (editable por el administrador).
     *
     * @return string
     */
    function bovs_msj_demo_asunto_sugerido()
    {
        return 'Tu cuenta demo de SOVCA está lista para dar el siguiente paso';
    }
}

if (!function_exists('bovs_msj_demo_cuerpo_sugerido')) {
    /**
     * Texto base del mensaje persuasivo (editable). Los {campos} se
     * reemplazan por los datos de cada usuario al enviar.
     *
     * @return string
     */
    function bovs_msj_demo_cuerpo_sugerido()
    {
        return "Hola {nombre}:\n\n"
            . "Durante tu demostración viste lo fácil que es llevar el control de tu fundo. "
            . "Ahora imagina tenerlo todo en tu cuenta oficial, sin fecha de vencimiento y con soporte permanente.\n\n"
            . "Con tu cuenta oficial SOVCA puedes:\n"
            . "• Medir cuánto produces y qué produces, animal por animal.\n"
            . "• Generar reportes de producción de leche por lotes y períodos.\n"
            . "• Identificar cuáles animales tienen mejor rendimiento y cuáles requieren atención.\n"
            . "• Trabajar sin internet con el modo offline y sincronizar cuando vuelvas a conectarte.\n"
            . "• Conservar los datos que ya cargaste en la demostración: nosotros los migramos.\n\n"
            . "Cuéntanos qué necesitas y te ayudamos a dejar tu sistema listo en pocos minutos.";
    }
}

if (!function_exists('bovs_msj_demo_variables')) {
    /**
     * Variables disponibles en el asunto y en el cuerpo.
     *
     * @param array $lead
     * @return array
     */
    function bovs_msj_demo_variables(array $lead)
    {
        $vence = isset($lead['vence']) ? (string) $lead['vence'] : '';
        $dias  = isset($lead['dias']) && $lead['dias'] !== null ? (int) $lead['dias'] : null;

        return array(
            '{nombre}'  => isset($lead['nombre']) && $lead['nombre'] !== '' ? $lead['nombre'] : 'productor',
            '{usuario}' => isset($lead['usuario']) ? (string) $lead['usuario'] : '',
            '{fundo}'   => isset($lead['fundo']) && $lead['fundo'] !== '' ? $lead['fundo'] : 'tu fundo',
            '{correo}'  => isset($lead['email']) ? (string) $lead['email'] : '',
            '{vence}'   => $vence !== '' ? date('d/m/Y', strtotime($vence . ' 12:00:00')) : '',
            '{dias}'    => $dias === null ? '' : (string) abs($dias),
            '{estado}'  => (isset($lead['estado']) && $lead['estado'] === 'vigente') ? 'vigente' : 'caducada',
        );
    }
}

if (!function_exists('bovs_msj_demo_personalizar')) {
    /**
     * Reemplaza las {variables} por los datos del destinatario.
     *
     * @param string $texto
     * @param array  $lead
     * @return string
     */
    function bovs_msj_demo_personalizar($texto, array $lead)
    {
        return strtr((string) $texto, bovs_msj_demo_variables($lead));
    }
}

if (!function_exists('bovs_msj_demo_linea_estado')) {
    /**
     * Frase con el estado de la demo, distinta para vigente y caducada.
     *
     * @param array $lead
     * @return string Texto ya escapado para HTML
     */
    function bovs_msj_demo_linea_estado(array $lead)
    {
        $fundo = htmlspecialchars(isset($lead['fundo']) && $lead['fundo'] !== '' ? $lead['fundo'] : 'tu fundo', ENT_QUOTES, 'UTF-8');
        $vence = isset($lead['vence']) ? (string) $lead['vence'] : '';
        $fecha = $vence !== '' ? date('d/m/Y', strtotime($vence . ' 12:00:00')) : '';
        $dias  = isset($lead['dias']) && $lead['dias'] !== null ? (int) $lead['dias'] : null;

        /* La secuencia automática marca si la persona cargó registros: el
           texto no puede decir "los datos que cargaste" a quien no cargó
           nada. Sin el dato se asume que sí (como antes). */
        $sinDatos = array_key_exists('con_datos', $lead) && !$lead['con_datos'];

        if (isset($lead['estado']) && $lead['estado'] === 'vigente') {
            $txt = 'Tu cuenta demo de <b>' . $fundo . '</b> sigue activa';
            if ($fecha !== '') {
                $txt .= ' hasta el <b>' . $fecha . '</b>';
            }
            if ($dias !== null) {
                if ($dias === 0) {
                    $txt .= ' (vence hoy)';
                } elseif ($dias === 1) {
                    $txt .= ' (queda <b>1 día</b>)';
                } else {
                    $txt .= ' (quedan <b>' . $dias . ' días</b>)';
                }
            }
            $txt .= $sinDatos ? '. Todavía no tiene registros, así que estás a tiempo de probarla.' : '.';
            return $txt;
        }

        $txt = 'Tu cuenta demo de <b>' . $fundo . '</b> ya venció';
        if ($fecha !== '') {
            $txt .= ' (el ' . $fecha . ')';
        }
        $txt .= $sinDatos
            ? '. No alcanzaste a cargar registros, pero podemos empezar de nuevo cuando quieras.'
            : '. Los datos que cargaste siguen guardados y los pasamos a tu cuenta oficial cuando la actives.';

        return $txt;
    }
}

if (!function_exists('bovs_msj_demo_html')) {
    /**
     * Correo HTML: identidad SOVCA + saludo personal, estado de la demo,
     * el mensaje, una lista opcional de beneficios/pasos y el botón final.
     *
     * @param array  $lead
     * @param string $asunto
     * @param string $cuerpo
     * @param array  $opciones lista_titulo, lista, cta_texto, cta_url,
     *                         cta_nota, enlace_texto, enlace_url, pie_nota
     * @return string
     */
    function bovs_msj_demo_html(array $lead, $asunto, $cuerpo, array $opciones = array())
    {
        $nombre = htmlspecialchars(isset($lead['nombre']) && $lead['nombre'] !== '' ? $lead['nombre'] : 'productor', ENT_QUOTES, 'UTF-8');
        $asunto = bovs_msj_demo_personalizar((string) $asunto, $lead);
        $cuerpo = bovs_msj_demo_personalizar((string) $cuerpo, $lead);
        $cuerpo = nl2br(htmlspecialchars($cuerpo, ENT_QUOTES, 'UTF-8'));

        $lineaEstado = bovs_msj_demo_linea_estado($lead);

        $wa = bovs_demo_whatsapp_url(array(
            'usuario' => isset($lead['usuario']) ? $lead['usuario'] : '',
            'fundo'   => isset($lead['fundo']) ? $lead['fundo'] : '',
            'email'   => isset($lead['email']) ? $lead['email'] : '',
        ));

        $numero = defined('SOVCA_WHATSAPP_NUMERO') ? (string) SOVCA_WHATSAPP_NUMERO : '';
        $numeroVisible = '';
        if ($numero !== '') {
            $numeroVisible = '+' . substr($numero, 0, 2) . ' ' . substr($numero, 2, 3) . '-' . substr($numero, 5);
        }

        /* --- Opciones: permiten adaptar el correo a cada etapa de la
           secuencia (bienvenida, día 7, día 14…) sin duplicar la plantilla. --- */
        $ctaTexto  = isset($opciones['cta_texto']) && $opciones['cta_texto'] !== '' ? (string) $opciones['cta_texto'] : 'Quiero mi cuenta oficial';
        $ctaUrl    = array_key_exists('cta_url', $opciones) ? (string) $opciones['cta_url'] : $wa;
        $ctaNota   = array_key_exists('cta_nota', $opciones) ? (string) $opciones['cta_nota'] : '';
        $enlaceTxt = isset($opciones['enlace_texto']) ? (string) $opciones['enlace_texto'] : 'Ver planes y precios';
        $enlaceUrl = isset($opciones['enlace_url']) && $opciones['enlace_url'] !== ''
            ? (string) $opciones['enlace_url']
            : 'https://global.ruted.net/sovca/';
        $pieNota   = isset($opciones['pie_nota']) && $opciones['pie_nota'] !== ''
            ? (string) $opciones['pie_nota']
            : 'Recibes este mensaje porque creaste una cuenta de demostración en SOVCA.';

        $listaTitulo = isset($opciones['lista_titulo']) ? trim((string) $opciones['lista_titulo']) : '';
        $lista       = (isset($opciones['lista']) && is_array($opciones['lista'])) ? $opciones['lista'] : array();

        $bloqueLista = '';
        if ($listaTitulo !== '' || count($lista) > 0) {
            $items = '';
            foreach ($lista as $item) {
                $items .= '<tr>'
                    . '<td style="padding:4px 10px 4px 0;font-size:13.5px;line-height:1.5;color:#2c4a37;vertical-align:top;width:18px;">&#10003;</td>'
                    . '<td style="padding:4px 0;font-size:13.5px;line-height:1.5;color:#2c4a37;">'
                    . htmlspecialchars((string) $item, ENT_QUOTES, 'UTF-8') . '</td>'
                    . '</tr>';
            }
            $bloqueLista = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" '
                . 'style="background:#f7fbf8;border:1px solid #dceddf;border-radius:10px;margin:16px 0 6px;">'
                . '<tr><td style="padding:14px 16px;">'
                . ($listaTitulo !== ''
                    ? '<p style="margin:0 0 8px;font-size:13px;font-weight:800;letter-spacing:.3px;text-transform:uppercase;color:#1b5e3a;">'
                        . htmlspecialchars($listaTitulo, ENT_QUOTES, 'UTF-8') . '</p>'
                    : '')
                . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $items . '</table>'
                . '</td></tr></table>';
        }

        $boton = '';
        if ($ctaUrl !== '') {
            $boton = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:24px 0 6px;">
                <tr>
                  <td align="center">
                    <a href="' . htmlspecialchars($ctaUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener"
                       style="display:inline-block;background:#2f8f4a;color:#ffffff;text-decoration:none;font-weight:800;font-size:14.5px;padding:14px 30px;border-radius:10px;letter-spacing:.3px;">
                      ' . htmlspecialchars($ctaTexto, ENT_QUOTES, 'UTF-8') . '
                    </a>
                  </td>
                </tr>
              </table>';
        }

        if ($ctaNota === '') {
            $ctaNota = 'o escríbenos por WhatsApp'
                . ($numeroVisible !== '' ? ': <b style="color:#2c6e46;">' . htmlspecialchars($numeroVisible, ENT_QUOTES, 'UTF-8') . '</b>' : '');
        } else {
            $ctaNota = htmlspecialchars($ctaNota, ENT_QUOTES, 'UTF-8');
        }

        $base = function_exists('bovs_url_base') ? bovs_url_base() : '';
        $logo = '';
        if ($base !== '') {
            $logo = '<img src="' . htmlspecialchars($base, ENT_QUOTES, 'UTF-8') . '/dist/img/sovca.png"'
                . ' alt="SOVCA" width="120" style="display:block;margin:0 auto 8px;border:0;max-width:120px;height:auto;">';
        }

        $anio = date('Y');

        return '<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>' . htmlspecialchars((string) $asunto, ENT_QUOTES, 'UTF-8') . '</title>
</head>
<body style="margin:0;padding:0;background:#eef3ec;font-family:\'Segoe UI\',Roboto,Arial,sans-serif;color:#1f2d24;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef3ec;padding:26px 12px;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 8px 24px rgba(35,78,53,0.14);">

          <tr>
            <td style="background:linear-gradient(135deg,#1b5e3a,#2c8a56);padding:26px 30px;text-align:center;color:#ffffff;">
              ' . $logo . '
              <div style="font-size:20px;font-weight:800;letter-spacing:2px;text-transform:uppercase;">SOVCA</div>
              <div style="font-size:11.5px;opacity:.92;margin-top:5px;letter-spacing:1px;text-transform:uppercase;">Gestión Ovina y Caprina · Ruted</div>
            </td>
          </tr>

          <tr>
            <td style="padding:28px 32px 8px;font-size:15.5px;line-height:1.6;color:#33413a;">
              <p style="margin:0 0 14px;font-size:17px;font-weight:700;color:#1b3a24;">Hola, ' . $nombre . '</p>
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f2f8f3;border-left:4px solid #2f8f4a;border-radius:8px;margin:0 0 18px;">
                <tr>
                  <td style="padding:12px 14px;font-size:13px;line-height:1.55;color:#2c4a37;">' . $lineaEstado . '</td>
                </tr>
              </table>
              <div style="font-size:14.5px;line-height:1.65;">' . $cuerpo . '</div>
              ' . $bloqueLista . '
              ' . $boton . '
              <p style="margin:0 0 6px;text-align:center;font-size:12px;color:#7b8794;">' . $ctaNota . '</p>
              <p style="margin:0 0 20px;text-align:center;font-size:12px;">
                <a href="' . htmlspecialchars($enlaceUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener" style="color:#2c6e46;text-decoration:underline;">' . htmlspecialchars($enlaceTxt, ENT_QUOTES, 'UTF-8') . '</a>
              </p>
            </td>
          </tr>

          <tr>
            <td style="background:#f4f8f4;padding:16px 30px;text-align:center;font-size:11.5px;color:#6b7d70;border-top:1px solid #e6ece7;line-height:1.6;">
              <span style="color:#2c6e46;font-weight:800;">Sistema SOVCA</span> · &copy; ' . $anio . ' Global-SOVCA<br>
              ' . htmlspecialchars($pieNota, ENT_QUOTES, 'UTF-8') . '
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>';
    }
}

if (!function_exists('bovs_msj_demo_texto')) {
    /**
     * Versión en texto plano del mismo correo (para el AltBody).
     *
     * @param array  $lead
     * @param string $asunto
     * @param string $cuerpo
     * @param array  $opciones Mismas opciones que bovs_msj_demo_html()
     * @return string
     */
    function bovs_msj_demo_texto(array $lead, $asunto, $cuerpo, array $opciones = array())
    {
        $asunto = bovs_msj_demo_personalizar((string) $asunto, $lead);
        $cuerpo = bovs_msj_demo_personalizar((string) $cuerpo, $lead);
        $linea  = strip_tags(bovs_msj_demo_linea_estado($lead));
        $wa     = bovs_demo_whatsapp_url(array(
            'usuario' => isset($lead['usuario']) ? $lead['usuario'] : '',
            'fundo'   => isset($lead['fundo']) ? $lead['fundo'] : '',
            'email'   => isset($lead['email']) ? $lead['email'] : '',
        ));

        $lista = (isset($opciones['lista']) && is_array($opciones['lista'])) ? $opciones['lista'] : array();
        $bloqueLista = '';
        if (count($lista) > 0) {
            $titulo = isset($opciones['lista_titulo']) ? trim((string) $opciones['lista_titulo']) : '';
            $bloqueLista = ($titulo !== '' ? "\n" . $titulo . ":\n" : '');
            foreach ($lista as $item) {
                $bloqueLista .= '- ' . bovs_msj_demo_personalizar((string) $item, $lead) . "\n";
            }
        }

        $ctaTexto = isset($opciones['cta_texto']) && $opciones['cta_texto'] !== ''
            ? (string) $opciones['cta_texto']
            : 'Quiero mi cuenta oficial';
        $ctaUrl = array_key_exists('cta_url', $opciones) ? (string) $opciones['cta_url'] : $wa;

        $pieNota = isset($opciones['pie_nota']) && $opciones['pie_nota'] !== ''
            ? (string) $opciones['pie_nota']
            : 'Recibes este mensaje porque creaste una cuenta de demostración en SOVCA.';

        $enlace = $ctaUrl !== '' ? "\n" . $ctaTexto . ': ' . $ctaUrl . "\n" : '';

        return trim((string) $asunto) . "\n\n" . $linea . "\n\n" . $cuerpo . "\n"
            . $bloqueLista . $enlace . "\n"
            . "Sistema SOVCA · Ruted, Tecnología y Desarrollo Rural.\n"
            . $pieNota;
    }
}


if (!function_exists('bovs_msj_demo_enviar')) {
    /**
     * Envía el mensaje a un destinatario y lo anota en la bitácora.
     *
     * @param array  $lead
     * @param string $asunto
     * @param string $cuerpo
     * @param int    $adminId
     * @return array ('ok' => bool, 'error' => string)
     */
    function bovs_msj_demo_enviar(array $lead, $asunto, $cuerpo, $adminId = 0)
    {
        $asuntoFinal = trim(bovs_msj_demo_personalizar((string) $asunto, $lead));
        if ($asuntoFinal === '') {
            $asuntoFinal = bovs_msj_demo_asunto_sugerido();
        }

        $html  = bovs_msj_demo_html($lead, $asuntoFinal, $cuerpo);
        $texto = bovs_msj_demo_texto($lead, $asuntoFinal, $cuerpo);

        $resultado = bovs_mail_enviar(
            $lead['email'],
            $asuntoFinal,
            $html,
            $texto,
            array('tipo' => 'notificacion')
        );

        bovs_msj_demo_registrar(
            $lead['email'],
            $asuntoFinal,
            !empty($resultado['ok']) ? 'ok' : 'error',
            !empty($resultado['ok']) ? '' : (isset($resultado['error']) ? $resultado['error'] : ''),
            $adminId
        );

        return $resultado;
    }
}
