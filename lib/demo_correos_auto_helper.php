<?php
/**
 * =====================================================================
 * lib/demo_correos_auto_helper.php — SECUENCIA DE CORREOS DE LA DEMO
 * ---------------------------------------------------------------------
 * Acompaña al visitante durante y después de la demostración:
 *
 *   Día 0   bienvenida (beneficios + cómo empezar)
 *   Día 7   seguimiento: con datos pregunta por la experiencia y empuja
 *           la compra; sin datos ofrece ayuda para cargar registros
 *   Día 14  aviso: la prueba está por vencer (con/sin datos)
 *   Día 22  rescate 1, ya caducada (con/sin datos)
 *   Día 30  rescate 2, último recordatorio (con/sin datos)
 *
 * Reglas:
 *   · solo para cuentas demo NO migradas a la oficial y que no tengan ya
 *     una cuenta oficial con su correo o usuario (si compraron por otra
 *     vía, la secuencia se detiene);
 *   · una etapa se envía una sola vez por cuenta (tabla demo_correos_auto);
 *   · cada etapa tiene una ventana corta de días: si el cron no corre un
 *     día, el correo sale al siguiente, pero una cuenta vieja nunca recibe
 *     los cinco correos de golpe;
 *   · "cargó datos" = tiene animales, servicios, nacimientos, ordeños,
 *     pesos, controles sanitarios, desincorporaciones o movimientos.
 *
 * La ejecuta scripts/demo_correos_auto.php (cron diario) y, para la
 * bienvenida, controllers/demo_controller.php al verificar el correo.
 * =====================================================================
 */

require_once __DIR__ . '/../config/demo.php';
require_once __DIR__ . '/demo_helper.php';
require_once __DIR__ . '/mensajes_demo_helper.php';

/* ---------------------------------------------------------------------
   ESTADO (base demo)
   --------------------------------------------------------------------- */

if (!function_exists('bovs_demo_auto_preparar')) {
    /**
     * Crea la tabla de seguimiento si falta (idempotente).
     *
     * @param PDO|null $pdo
     * @return bool
     */
    function bovs_demo_auto_preparar($pdo = null)
    {
        if ($pdo === null) {
            $pdo = bovs_demo_pdo();
        }

        $sentencias = array(
            "CREATE TABLE IF NOT EXISTS demo_correos_auto (
                id            serial PRIMARY KEY,
                musuarios_id  integer NOT NULL,
                email         varchar(160) NOT NULL,
                etapa         varchar(20) NOT NULL,
                con_datos     boolean DEFAULT false,
                estado        varchar(10) NOT NULL DEFAULT 'pendiente',
                error         varchar(300),
                enviado       timestamp without time zone,
                created       timestamp without time zone DEFAULT now()
            )",
            "CREATE UNIQUE INDEX IF NOT EXISTS idx_demo_correos_auto_unico
                ON demo_correos_auto (musuarios_id, etapa)",
            "CREATE INDEX IF NOT EXISTS idx_demo_correos_auto_email
                ON demo_correos_auto (lower(email))",
        );

        $ok = true;
        foreach ($sentencias as $sql) {
            try {
                $pdo->exec($sql);
            } catch (Throwable $e) {
                $ok = false;
                error_log('[SOVCA demo correos] preparar: ' . $e->getMessage());
            }
        }

        return $ok;
    }
}

if (!function_exists('bovs_demo_auto_etapas')) {
    /**
     * Etapas de la secuencia con su día y la ventana de tolerancia.
     *
     * La tolerancia es corta a propósito: si el cron no corrió ese día, el
     * correo sale al día siguiente; pasada la ventana se considera perdido
     * (no tiene sentido mandar "te queda 1 día" cuando ya venció).
     *
     * @return array
     */
    function bovs_demo_auto_etapas()
    {
        return array(
            'bienvenida' => array('dia' => 0,  'gracia' => 1, 'nombre' => 'Bienvenida'),
            'dia7'       => array('dia' => 7,  'gracia' => 3, 'nombre' => 'Día 7 · seguimiento'),
            'dia14'      => array('dia' => 14, 'gracia' => 1, 'nombre' => 'Día 14 · por vencer'),
            'dia22'      => array('dia' => 22, 'gracia' => 3, 'nombre' => 'Día 22 · rescate'),
            'dia30'      => array('dia' => 30, 'gracia' => 3, 'nombre' => 'Día 30 · último aviso'),
        );
    }
}

if (!function_exists('bovs_demo_auto_dias_desde')) {
    /**
     * Días completos entre una fecha (Y-m-d) y hoy.
     *
     * @param string $desde
     * @param string|null $hasta
     * @return int
     */
    function bovs_demo_auto_dias_desde($desde, $hasta = null)
    {
        $desde = substr(trim((string) $desde), 0, 10);
        if ($desde === '') {
            return -1;
        }

        $hasta = ($hasta === null || $hasta === '') ? date('Y-m-d') : substr((string) $hasta, 0, 10);

        $a = strtotime($desde . ' 12:00:00');
        $b = strtotime($hasta . ' 12:00:00');
        if ($a === false || $b === false) {
            return -1;
        }

        return (int) round(($b - $a) / 86400);
    }
}

if (!function_exists('bovs_demo_auto_etapa_para')) {
    /**
     * Etapa que le toca a una cuenta según los días transcurridos.
     *
     * @param int $dias
     * @return string Clave de la etapa o '' si hoy no toca ninguna.
     */
    function bovs_demo_auto_etapa_para($dias)
    {
        $dias = (int) $dias;
        if ($dias < 0) {
            return '';
        }

        foreach (bovs_demo_auto_etapas() as $clave => $etapa) {
            if ($dias >= $etapa['dia'] && $dias <= ($etapa['dia'] + $etapa['gracia'])) {
                return $clave;
            }
        }

        return '';
    }
}

if (!function_exists('bovs_demo_auto_historial')) {
    /**
     * Estado de la secuencia por cuenta.
     *
     * @param PDO $pdo
     * @return array musuarios_id => array(etapa => fila)
     */
    function bovs_demo_auto_historial(PDO $pdo)
    {
        try {
            $filas = $pdo->query('SELECT musuarios_id, email, etapa, estado, con_datos, enviado, error FROM demo_correos_auto ORDER BY id')->fetchAll();
        } catch (Throwable $e) {
            return array();
        }

        $salida = array();
        foreach ($filas as $f) {
            $salida[(int) $f['musuarios_id']][(string) $f['etapa']] = $f;
        }

        return $salida;
    }
}

if (!function_exists('bovs_demo_auto_reservar')) {
    /**
     * Aparta la etapa para que no la envíe otro proceso a la vez.
     *
     * @param PDO    $pdo
     * @param int    $usuarioId
     * @param string $etapa
     * @param string $email
     * @param bool   $conDatos
     * @return bool true si este proceso debe enviar el correo.
     */
    function bovs_demo_auto_reservar(PDO $pdo, $usuarioId, $etapa, $email, $conDatos)
    {
        $usuarioId = (int) $usuarioId;
        $etapa     = (string) $etapa;

        try {
            $st = $pdo->prepare("
                INSERT INTO demo_correos_auto (musuarios_id, email, etapa, con_datos, estado)
                VALUES (?, ?, ?, ?, 'pendiente')
                ON CONFLICT (musuarios_id, etapa) DO NOTHING
            ");
            $st->execute(array($usuarioId, bovs_msj_demo_recortar($email, 160), $etapa, $conDatos ? 't' : 'f'));

            if ($st->rowCount() > 0) {
                return true;
            }

            /* Ya existe: solo se reintenta si el intento anterior falló. */
            $st = $pdo->prepare("
                UPDATE demo_correos_auto
                   SET estado = 'pendiente', error = NULL, con_datos = ?
                 WHERE musuarios_id = ? AND etapa = ? AND estado = 'error'
            ");
            $st->execute(array($conDatos ? 't' : 'f', $usuarioId, $etapa));

            return $st->rowCount() > 0;
        } catch (Throwable $e) {
            error_log('[SOVCA demo correos] reservar: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('bovs_demo_auto_marcar')) {
    /**
     * Cierra la etapa como enviada o con error.
     *
     * @param PDO    $pdo
     * @param int    $usuarioId
     * @param string $etapa
     * @param string $estado 'ok' | 'error'
     * @param string $error
     * @return void
     */
    function bovs_demo_auto_marcar(PDO $pdo, $usuarioId, $etapa, $estado, $error = '')
    {
        try {
            $st = $pdo->prepare("
                UPDATE demo_correos_auto
                   SET estado = ?, error = ?, enviado = now()
                 WHERE musuarios_id = ? AND etapa = ?
            ");
            $st->execute(array(
                ($estado === 'ok') ? 'ok' : 'error',
                ($error === '') ? null : bovs_msj_demo_recortar($error, 300),
                (int) $usuarioId,
                (string) $etapa,
            ));
        } catch (Throwable $e) {
            error_log('[SOVCA demo correos] marcar: ' . $e->getMessage());
        }
    }
}

/* ---------------------------------------------------------------------
   ¿EL USUARIO CARGÓ DATOS?
   --------------------------------------------------------------------- */

if (!function_exists('bovs_demo_auto_tiene_datos')) {
    /**
     * ¿El fundo demo tiene registros propios?
     *
     * Se consultan las tablas operativas principales (no los catálogos):
     * un lote o una raza no significan que la persona haya trabajado.
     *
     * @param PDO $pdo
     * @param int $fundoId
     * @return array{tiene:bool,total:int,animales:int}
     */
    function bovs_demo_auto_tiene_datos(PDO $pdo, $fundoId)
    {
        $fundoId = (int) $fundoId;
        if ($fundoId <= 0) {
            return array('tiene' => false, 'total' => 0, 'animales' => 0);
        }

        $tablas = array(
            'manimales',
            'mregservicios',
            'mregnacimientos',
            'mprolecheras',
            'mconsecutivopesos',
            'mcontrolsanitarios',
            'mdesincorporacionanimales',
            'mmovimientos_rebano',
        );

        $total    = 0;
        $animales = 0;

        foreach ($tablas as $tabla) {
            try {
                $st = $pdo->prepare('SELECT count(*) FROM "' . $tabla . '" WHERE mfundos_id = ?');
                $st->execute(array($fundoId));
                $cant = (int) $st->fetchColumn();
                $total += $cant;
                if ($tabla === 'manimales') {
                    $animales = $cant;
                }
            } catch (Throwable $e) {
                /* La tabla puede no existir en una base demo antigua. */
            }
        }

        return array('tiene' => $total > 0, 'total' => $total, 'animales' => $animales);
    }
}

/* ---------------------------------------------------------------------
   ¿YA ES CLIENTE OFICIAL?
   --------------------------------------------------------------------- */

if (!function_exists('bovs_demo_auto_clientes_oficiales')) {
    /**
     * Correos y usuarios que ya tienen cuenta en la base oficial.
     *
     * Se consulta una sola vez por corrida y sirve para detener la
     * secuencia de quien compró sin pasar por "Importar cuenta demo".
     *
     * @param array $emails   Correos (en minúsculas)
     * @param array $usuarios Usuarios demo
     * @return array ['emails' => array, 'usuarios' => array]
     */
    function bovs_demo_auto_clientes_oficiales(array $emails, array $usuarios)
    {
        $vacio = array('emails' => array(), 'usuarios' => array());

        $emails   = array_values(array_unique(array_filter(array_map('strtolower', $emails))));
        $usuarios = array_values(array_unique(array_filter(array_map('strtoupper', $usuarios))));

        if (count($emails) === 0 && count($usuarios) === 0) {
            return $vacio;
        }

        try {
            $pdo = new PDO(
                'pgsql:host=' . SOVCA_OFICIAL_HOST . ';port=' . SOVCA_OFICIAL_PUERTO . ';dbname=' . SOVCA_OFICIAL_DB,
                SOVCA_OFICIAL_USUARIO,
                SOVCA_OFICIAL_CLAVE,
                array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC)
            );
        } catch (Throwable $e) {
            error_log('[SOVCA demo correos] oficial: ' . $e->getMessage());
            return $vacio;
        }

        $encontrados = $vacio;

        /* Se consulta por partes para no armar una lista enorme de parámetros. */
        foreach (array_chunk($emails, 200) as $parte) {
            $marcas = implode(',', array_fill(0, count($parte), '?'));
            try {
                $st = $pdo->prepare("SELECT lower(email) AS email FROM musuarios WHERE lower(email) IN ($marcas)");
                $st->execute($parte);
                foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $e) {
                    $encontrados['emails'][(string) $e] = true;
                }
            } catch (Throwable $e) {
                error_log('[SOVCA demo correos] oficial (correos): ' . $e->getMessage());
            }
        }

        foreach (array_chunk($usuarios, 200) as $parte) {
            $marcas = implode(',', array_fill(0, count($parte), '?'));
            try {
                $st = $pdo->prepare("SELECT upper(username) AS username FROM musuarios WHERE upper(username) IN ($marcas)");
                $st->execute($parte);
                foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $u) {
                    $encontrados['usuarios'][(string) $u] = true;
                }
            } catch (Throwable $e) {
                error_log('[SOVCA demo correos] oficial (usuarios): ' . $e->getMessage());
            }
        }

        return $encontrados;
    }
}

/* ---------------------------------------------------------------------
   MENSAJES DE CADA ETAPA
   --------------------------------------------------------------------- */

if (!function_exists('bovs_demo_auto_beneficios')) {
    /**
     * Lista de bondades del sistema (la misma en todas las etapas).
     *
     * @return array
     */
    function bovs_demo_auto_beneficios()
    {
        return array(
            'Ficha completa de cada animal: raza, categoría, lote y potrero.',
            'Servicios, nacimientos y control de ciclos reproductivos.',
            'Producción de leche por animal, por lote y por período.',
            'Pesajes y ganancia de peso para ver cuáles rinden mejor.',
            'Control sanitario y desincorporaciones con historial.',
            'Reportes de cuánto produces y qué produces, con máximos y mínimos por animal.',
            'Modo offline: sigue trabajando sin señal y sincroniza después.',
        );
    }
}

if (!function_exists('bovs_demo_auto_pasos_inicio')) {
    /**
     * Primeros pasos para quien todavía no cargó nada.
     *
     * @return array
     */
    function bovs_demo_auto_pasos_inicio()
    {
        return array(
            'Crea tus lotes y potreros (o usa los que ya vienen cargados).',
            'Registra tus primeros animales con su código, raza y categoría.',
            'Anota un servicio, un ordeño o un peso para ver los reportes en acción.',
            'Si te trabas, escríbenos por WhatsApp y lo hacemos contigo paso a paso.',
        );
    }
}

if (!function_exists('bovs_demo_auto_whatsapp')) {
    /**
     * Enlace de WhatsApp con los datos del usuario demo.
     *
     * @param array $lead
     * @return string
     */
    function bovs_demo_auto_whatsapp(array $lead)
    {
        return bovs_demo_whatsapp_url(array(
            'usuario' => isset($lead['usuario']) ? $lead['usuario'] : '',
            'fundo'   => isset($lead['fundo']) ? $lead['fundo'] : '',
            'email'   => isset($lead['email']) ? $lead['email'] : '',
        ));
    }
}

if (!function_exists('bovs_demo_auto_mensaje')) {
    /**
     * Asunto, cuerpo y opciones del correo para una etapa.
     *
     * @param string $etapa    bienvenida | dia7 | dia14 | dia22 | dia30
     * @param bool   $conDatos
     * @param array  $lead
     * @return array asunto, cuerpo, opciones
     */
    function bovs_demo_auto_mensaje($etapa, $conDatos, array $lead)
    {
        $conDatos = (bool) $conDatos;
        $wa       = bovs_demo_auto_whatsapp($lead);

        $opciones = array(
            'cta_texto'   => 'Quiero mi cuenta oficial',
            'cta_url'     => $wa,
            'lista_titulo' => '',
            'lista'       => array(),
        );

        switch ((string) $etapa) {

            /* ---------------------------------------------------------
               DÍA 0 · BIENVENIDA
               --------------------------------------------------------- */
            case 'bienvenida':
                $asunto = '¡Bienvenido a SOVCA, {nombre}! Tu cuenta demo ya está activa';
                $cuerpo = "Tu cuenta de demostración quedó lista y desde hoy puedes probar SOVCA con tus propios datos "
                    . "durante 15 días, sin costo y sin compromiso.\n\n"
                    . "Esto es lo que puedes hacer desde el primer momento:";
                $opciones['lista_titulo'] = 'Todo lo que tienes disponible';
                $opciones['lista']        = bovs_demo_auto_beneficios();
                $opciones['cta_texto']    = 'Entrar a mi cuenta demo';
                $opciones['cta_url']      = bovs_demo_auto_url_demo();
                $opciones['cta_nota']     = 'Tu usuario demo es ' . (isset($lead['usuario']) && $lead['usuario'] !== '' ? $lead['usuario'] : 'el que registraste') . '.';
                $opciones['enlace_texto'] = 'Conocer los planes';
                $opciones['pie_nota']     = 'Recibes este mensaje porque creaste una cuenta de demostración en SOVCA.';
                break;

            /* ---------------------------------------------------------
               DÍA 7 · SEGUIMIENTO
               --------------------------------------------------------- */
            case 'dia7':
                if ($conDatos) {
                    $asunto = '{nombre}, ¿cómo va tu experiencia con SOVCA?';
                    $cuerpo = "Ya pasó una semana desde que empezaste y vemos que cargaste información en tu fundo: "
                        . "eso es justo lo que buscábamos.\n\n"
                        . "Nos gustaría saber cómo te has sentido usando el sistema: ¿te resultó fácil? "
                        . "¿hay algo que no encontraste o que te gustaría que hiciera distinto? "
                        . "Responde a este correo y lo revisamos con calma.\n\n"
                        . "Aún te quedan {dias} días de prueba para seguir sacándole provecho. "
                        . "Y si decides pasar a tu cuenta oficial, conservamos todo lo que ya registraste.";
                    $opciones['lista_titulo'] = 'Lo que puedes exprimir en estos días';
                } else {
                    $asunto = '{nombre}, te ayudamos a cargar tus primeros animales';
                    $cuerpo = "Han pasado unos días desde que creaste tu cuenta demo y queremos asegurarnos de que no te quedes "
                        . "con las ganas de probarla. Empezar es más fácil de lo que parece y podemos acompañarte.\n\n"
                        . "Te quedan {dias} días de prueba. Con estos pasos ya podrás ver SOVCA funcionando con tus datos:";
                    $opciones['lista_titulo'] = 'Cómo empezar (te toma pocos minutos)';
                    $opciones['lista']        = bovs_demo_auto_pasos_inicio();
                    $opciones['cta_texto']    = 'Pedir ayuda por WhatsApp';
                }
                break;

            /* ---------------------------------------------------------
               DÍA 14 · POR VENCER
               --------------------------------------------------------- */
            case 'dia14':
                if ($conDatos) {
                    $asunto = '{nombre}, a tu cuenta demo le queda 1 día (y tus datos están listos)';
                    $cuerpo = "Tu cuenta de demostración vence el {vence}. Lo bueno es que los datos que cargaste "
                        . "quedaron completos y no tendrías que volver a escribirlos: al pasar a tu cuenta oficial "
                        . "los migramos nosotros.\n\n"
                        . "Si el sistema te sirvió, este es el mejor momento para dar el paso:";
                    $opciones['lista_titulo'] = 'Lo que te llevas a la cuenta oficial';
                    $opciones['lista']        = array(
                        'Todos tus animales, servicios, nacimientos y ordeños ya cargados.',
                        'Historial y reportes que puedas comparar mes a mes.',
                        'Soporte para dejar todo funcionando en tu fundo.',
                        'Acceso desde el celular y trabajo sin internet con el modo offline.',
                    );
                } else {
                    $asunto = '{nombre}, a tu cuenta demo le queda 1 día de prueba';
                    $cuerpo = "Tu cuenta de demostración vence el {vence}. Nos gustaría que no te quedes con las ganas "
                        . "de probarlo: si quieres, te acompañamos para cargar tus primeros animales y ver los reportes "
                        . "funcionando.\n\n"
                        . "Unos minutos bastan para darte cuenta si SOVCA te sirve:";
                    $opciones['lista_titulo'] = 'Esto es lo que verás apenas cargues datos';
                    $opciones['lista']        = array(
                        'Cuánto produce cada animal y cuáles rinden mejor.',
                        'La evolución de tu rebaño por lote y por período.',
                        'Los reportes de producción sin hacer cuentas a mano.',
                        'Cómo se trabaja sin internet y se sincroniza después.',
                    );
                    $opciones['cta_texto'] = 'Pedir ayuda por WhatsApp';
                }
                break;

            /* ---------------------------------------------------------
               DÍA 22 · RESCATE 1
               --------------------------------------------------------- */
            case 'dia22':
                if ($conDatos) {
                    $asunto = '{nombre}, los datos de tu demo siguen guardados';
                    $cuerpo = "Tu cuenta de demostración venció el {vence}, pero la información que cargaste "
                        . "no se perdió: sigue guardada y podemos pasarla a tu cuenta oficial tal como está.\n\n"
                        . "Si dejaste la prueba pendiente por falta de tiempo, te ayudamos a retomarla donde quedaste.";
                    $opciones['lista_titulo'] = 'Recuerda lo que tienes en SOVCA';
                } else {
                    $asunto = '{nombre}, ¿te quedó alguna duda de SOVCA?';
                    $cuerpo = "Tu cuenta de demostración venció el {vence}. A veces no es falta de interés, "
                        . "sino de tiempo o de una mano para empezar.\n\n"
                        . "Si quieres otra oportunidad, te acompañamos con una demostración guiada "
                        . "y cargamos juntos tus primeros datos.";
                    $opciones['lista_titulo'] = 'Lo que podemos hacer contigo';
                    $opciones['lista']        = array(
                        'Hacer una demostración guiada por WhatsApp, con tus propios animales.',
                        'Ayudarte a cargar los primeros registros sin complicaciones.',
                        'Mostrarte los reportes con datos reales de tu fundo.',
                        'Explicarte los planes sin compromiso.',
                    );
                    $opciones['cta_texto'] = 'Agendar una demostración guiada';
                }
                break;

            /* ---------------------------------------------------------
               DÍA 30 · ÚLTIMO AVISO
               --------------------------------------------------------- */
            case 'dia30':
                if ($conDatos) {
                    $asunto = 'Última oportunidad para conservar los datos de tu demo';
                    $cuerpo = "Este es el último recordatorio sobre tu cuenta de demostración, que venció el {vence}. "
                        . "Los datos que cargaste siguen guardados, pero no queremos que los pierdas por no decidir.\n\n"
                        . "Si quieres continuar, escríbenos y en poco tiempo tendrás tu cuenta oficial funcionando, "
                        . "con toda tu información dentro.";
                } else {
                    $asunto = 'Última oportunidad para probar SOVCA con acompañamiento';
                    $cuerpo = "Este es el último recordatorio sobre tu cuenta demo, que venció el {vence}. "
                        . "Quizá lo que faltó fue acompañamiento para dar los primeros pasos.\n\n"
                        . "Si en algún momento quieres ver cómo funciona con tus propios animales, "
                        . "escríbenos y lo hacemos juntos, sin compromiso.";
                }
                $opciones['lista_titulo'] = 'En resumen, con SOVCA puedes';
                $opciones['lista']        = array_slice(bovs_demo_auto_beneficios(), 0, 4);
                break;

            default:
                return array();
        }

        if ($opciones['lista_titulo'] !== '' && count($opciones['lista']) === 0) {
            $opciones['lista'] = bovs_demo_auto_beneficios();
        }

        return array(
            'asunto'   => $asunto,
            'cuerpo'   => $cuerpo,
            'opciones' => $opciones,
        );
    }
}

if (!function_exists('bovs_demo_auto_url_demo')) {
    /**
     * Enlace público a la pantalla de acceso demo (vacío si no hay URL).
     *
     * @return string
     */
    function bovs_demo_auto_url_demo()
    {
        $base = function_exists('bovs_url_base') ? bovs_url_base() : '';
        if ($base === '') {
            return '';
        }

        return $base . '/views/demo/?ingresar=1';
    }
}

/* ---------------------------------------------------------------------
   ENVÍO
   --------------------------------------------------------------------- */

if (!function_exists('bovs_demo_auto_enviar')) {
    /**
     * Envía la etapa a un usuario demo y actualiza el seguimiento.
     *
     * @param PDO    $pdo
     * @param array  $lead
     * @param string $etapa
     * @param bool   $conDatos
     * @return array ok, error, asunto
     */
    function bovs_demo_auto_enviar(PDO $pdo, array $lead, $etapa, $conDatos)
    {
        $mensaje = bovs_demo_auto_mensaje($etapa, $conDatos, $lead);
        if (empty($mensaje)) {
            return array('ok' => false, 'error' => 'Etapa desconocida', 'asunto' => '');
        }

        $usuarioId = isset($lead['usuario_id']) ? (int) $lead['usuario_id'] : 0;
        if ($usuarioId <= 0) {
            return array('ok' => false, 'error' => 'Cuenta demo sin usuario', 'asunto' => '');
        }

        if (!bovs_demo_auto_reservar($pdo, $usuarioId, $etapa, $lead['email'], $conDatos)) {
            return array('ok' => false, 'error' => 'La etapa ya estaba registrada para esta cuenta', 'asunto' => '', 'omitido' => true);
        }

        /* El correo debe saber si hay registros: el recuadro de estado y el
           texto dicen cosas distintas para quien no cargó nada. */
        $lead['con_datos'] = (bool) $conDatos;

        $asunto = trim(bovs_msj_demo_personalizar($mensaje['asunto'], $lead));
        $html   = bovs_msj_demo_html($lead, $mensaje['asunto'], $mensaje['cuerpo'], $mensaje['opciones']);
        $texto  = bovs_msj_demo_texto($lead, $mensaje['asunto'], $mensaje['cuerpo'], $mensaje['opciones']);

        $resultado = bovs_mail_enviar($lead['email'], $asunto, $html, $texto, array('tipo' => 'notificacion'));

        if (!empty($resultado['ok'])) {
            bovs_demo_auto_marcar($pdo, $usuarioId, $etapa, 'ok');
        } else {
            bovs_demo_auto_marcar(
                $pdo,
                $usuarioId,
                $etapa,
                'error',
                isset($resultado['error']) ? (string) $resultado['error'] : 'Error desconocido'
            );
        }

        return array(
            'ok'     => !empty($resultado['ok']),
            'error'  => isset($resultado['error']) ? (string) $resultado['error'] : '',
            'asunto' => $asunto,
        );
    }
}

if (!function_exists('bovs_demo_auto_procesar')) {
    /**
     * Recorre las cuentas demo y envía las etapas que tocan hoy.
     *
     * @param array $opciones dry_run, limite, etapa, usuario, verbose, log
     * @return array resumen
     */
    function bovs_demo_auto_procesar(array $opciones = array())
    {
        $dryRun  = !empty($opciones['dry_run']);
        $limite  = isset($opciones['limite']) ? max(1, (int) $opciones['limite']) : 40;
        $soloEtapa   = isset($opciones['etapa']) ? (string) $opciones['etapa'] : '';
        $soloUsuario = isset($opciones['usuario']) ? strtoupper(trim((string) $opciones['usuario'])) : '';
        $verbose = !empty($opciones['verbose']);
        $log     = isset($opciones['log']) && is_callable($opciones['log']) ? $opciones['log'] : null;

        $resumen = array(
            'revisados' => 0,
            'enviados'  => 0,
            'omitidos'  => 0,
            'errores'   => 0,
            'detalle'   => array(),
            'por_etapa' => array(),
        );

        $avisar = function ($texto) use ($log) {
            if ($log !== null) {
                call_user_func($log, $texto);
            }
        };

        if (!bovs_demo_activo() || !bovs_demo_bd_disponible()) {
            $resumen['error'] = 'La base de datos de demostración no está disponible.';
            $avisar($resumen['error']);
            return $resumen;
        }

        $pdo = bovs_demo_pdo();
        bovs_demo_preparar($pdo);
        bovs_demo_auto_preparar($pdo);

        $leads = bovs_msj_demo_listar();
        if (count($leads) === 0) {
            $avisar('No hay usuarios demo a los que escribir.');
            return $resumen;
        }

        /* Quien ya tiene cuenta oficial (aunque no se haya importado por el
           módulo) no debe recibir la secuencia. */
        $emails   = array();
        $usuarios = array();
        foreach ($leads as $l) {
            $emails[]   = $l['email'];
            $usuarios[] = $l['usuario'];
        }
        $clientes = bovs_demo_auto_clientes_oficiales($emails, $usuarios);

        $historial = bovs_demo_auto_historial($pdo);
        $hoy       = date('Y-m-d');

        foreach ($leads as $lead) {
            if ($resumen['enviados'] + $resumen['errores'] >= $limite) {
                $avisar('Se alcanzó el límite de la corrida.');
                break;
            }

            if ($soloUsuario !== '' && strtoupper($lead['usuario']) !== $soloUsuario) {
                continue;
            }

            $resumen['revisados']++;

            $dias = isset($lead['dias_desde'])
                ? (int) $lead['dias_desde']
                : bovs_demo_auto_dias_desde($lead['registrado'], $hoy);
            if ($dias < 0) {
                $resumen['omitidos']++;
                continue;
            }

            $etapa = bovs_demo_auto_etapa_para($dias);
            if ($etapa === '') {
                $resumen['omitidos']++;
                continue;
            }
            if ($soloEtapa !== '' && $etapa !== $soloEtapa) {
                $resumen['omitidos']++;
                continue;
            }

            if (isset($clientes['emails'][strtolower($lead['email'])])
                || ($lead['usuario'] !== '' && isset($clientes['usuarios'][strtoupper($lead['usuario'])]))) {
                $avisar('  omitido (ya es cliente oficial): ' . $lead['email']);
                $resumen['omitidos']++;
                continue;
            }

            $estadoPrevio = isset($historial[$lead['usuario_id']][$etapa]) ? $historial[$lead['usuario_id']][$etapa] : null;
            if ($estadoPrevio !== null && $estadoPrevio['estado'] === 'ok') {
                $resumen['omitidos']++;
                continue;
            }

            $datos    = bovs_demo_auto_tiene_datos($pdo, isset($lead['fundo_id']) ? $lead['fundo_id'] : 0);
            $conDatos = !empty($datos['tiene']);

            $nombreEtapa = bovs_demo_auto_etapas()[$etapa]['nombre'];

            if ($dryRun) {
                $avisar(sprintf(
                    '  [dry-run] %s · %s · día %d · %s · %d registros',
                    $lead['email'],
                    $nombreEtapa,
                    $dias,
                    $conDatos ? 'con datos' : 'sin datos',
                    $datos['total']
                ));
                $resumen['detalle'][] = array(
                    'email'   => $lead['email'],
                    'etapa'   => $etapa,
                    'dias'    => $dias,
                    'datos'   => $conDatos,
                    'estado'  => 'dry-run',
                );
                $resumen['por_etapa'][$etapa] = (isset($resumen['por_etapa'][$etapa]) ? $resumen['por_etapa'][$etapa] : 0) + 1;
                continue;
            }

            $r = bovs_demo_auto_enviar($pdo, $lead, $etapa, $conDatos);

            if (!empty($r['ok'])) {
                $resumen['enviados']++;
                $resumen['por_etapa'][$etapa] = (isset($resumen['por_etapa'][$etapa]) ? $resumen['por_etapa'][$etapa] : 0) + 1;
                $avisar(sprintf(
                    '  enviado: %s · %s · %s',
                    $lead['email'],
                    $nombreEtapa,
                    $conDatos ? 'con datos' : 'sin datos'
                ));
            } else {
                $resumen['errores']++;
                $avisar(sprintf('  ERROR: %s · %s: %s', $lead['email'], $nombreEtapa, $r['error']));
            }

            $resumen['detalle'][] = array(
                'email'  => $lead['email'],
                'etapa'  => $etapa,
                'dias'   => $dias,
                'datos'  => $conDatos,
                'estado' => !empty($r['ok']) ? 'ok' : 'error',
            );
        }

        return $resumen;
    }
}

/* ---------------------------------------------------------------------
   BIENVENIDA AL CREAR LA CUENTA
   --------------------------------------------------------------------- */

if (!function_exists('bovs_demo_auto_bienvenida')) {
    /**
     * Envía la bienvenida a una cuenta recién creada.
     *
     * No lanza nada: si falla, el cron la reintenta en la ventana del
     * día 0-1. Se llama desde demo_controller después de responder.
     *
     * @param int $usuarioId
     * @return bool
     */
    function bovs_demo_auto_bienvenida($usuarioId)
    {
        $usuarioId = (int) $usuarioId;
        if ($usuarioId <= 0 || !bovs_demo_bd_disponible()) {
            return false;
        }

        try {
            $pdo  = bovs_demo_pdo();
            $lead = null;

            foreach (bovs_msj_demo_listar() as $l) {
                if ((int) $l['usuario_id'] === $usuarioId) {
                    $lead = $l;
                    break;
                }
            }

            if ($lead === null) {
                return false;
            }

            $r = bovs_demo_auto_enviar($pdo, $lead, 'bienvenida', false);
            return !empty($r['ok']);
        } catch (Throwable $e) {
            error_log('[SOVCA demo correos] bienvenida: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('bovs_demo_auto_bienvenida_diferida')) {
    /**
     * Programa la bienvenida para cuando la petición ya respondió.
     *
     * El visitante acaba de verificar su correo: que no espere al SMTP.
     * En PHP-FPM la respuesta se cierra con fastcgi_finish_request();
     * en otros servidores el cierre ocurre al terminar el script.
     *
     * @param int $usuarioId
     * @return void
     */
    function bovs_demo_auto_bienvenida_diferida($usuarioId)
    {
        $usuarioId = (int) $usuarioId;
        if ($usuarioId <= 0) {
            return;
        }

        register_shutdown_function(function () use ($usuarioId) {
            if (function_exists('fastcgi_finish_request')) {
                @fastcgi_finish_request();
            }
            bovs_demo_auto_bienvenida($usuarioId);
        });
    }
}
