<?php
/* ============================================================
   BOVS | Reporte de Control Sanitario (individual + lotes)
   ------------------------------------------------------------
   Compatible con PHP 7.4 y PHP 8.3.

   Modos:
   - Por código BOVS / N° arete : historial del animal (controles
     individuales y recibidos en lote). Si se indica rango de fechas,
     se limita a ese período; sin fechas, muestra el historial completo.
     La columna "Origen" y el lote / código RCS identifican cada control.
   - Por rango de fechas        : reporte amplio agrupado por animal
     (también incorpora los controles en lote).
   - Por rango de lotes o modo=lote : reporte por control de lote,
     con productos, observaciones y animales incluidos.

   Optimización:
   - Una sola consulta de cabeceras (UNION ALL individual + lote).
   - Una sola consulta de productos para todos los controles.
   - Una sola consulta de animales incluidos (solo modo lote).

   Notas técnicas:
   - Constructor dual (__construct + pdfreporte).
   - Polyfills get_magic_quotes_runtime() / set_magic_quotes_runtime().
   - Helper __pdf_txt() para convertir UTF-8 → ISO-8859-1.
   - E_WARNING / E_DEPRECATED / E_NOTICE silenciados.
   ============================================================ */

date_default_timezone_set('America/Caracas');

if (!function_exists('get_magic_quotes_runtime')) {
    function get_magic_quotes_runtime() { return false; }
}
if (!function_exists('set_magic_quotes_runtime')) {
    function set_magic_quotes_runtime($v) { return true; }
}

if (!function_exists('__pdf_txt')) {
    function __pdf_txt($str) {
        if ($str === null || $str === '') return '';
        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($str, 'ISO-8859-1', 'UTF-8');
        }
        return utf8_decode($str);
    }
}

error_reporting(E_ALL & ~E_WARNING & ~E_DEPRECATED & ~E_NOTICE);

require_once('../../lib/general/fpdf/fpdf.php');
require_once('../../lib/bd/basedatosAdoIbase.php');
require_once('../../lib/general/cabecera.php');
require_once('../../lib/general/Herramientas.class.php');
include_once('../../lib/utiles.php');
include_once('../../lib/funciones.php');
require_once('../../lib/animal_codigo_helper.php');

#[\AllowDynamicProperties]
class pdfreporte extends fpdf
{
    var $reg;
    var $p = 0;

    /* Constructor dual: PHP 5.3.3 → 8.3 */
    function __construct()
    {
        $this->pdfreporte();
    }

    function pdfreporte()
    {
        $this->fpdf('p', 'mm', 'legal');
        $this->bd  = new baseClases();
        $this->cab = new Cabecera();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $parametros = base64_decode(getvalue('%$%'));
        $par = $this->parsear_parametros($parametros);

        $this->fecha_desde = $this->normalizar_fecha($par['fecha_inicio']);
        $this->fecha_hasta = $this->normalizar_fecha($par['fecha_final']);
        $this->cod_bovs    = trim($par['cod_bovs']);
        $this->buscar_cod  = intval($par['codigo_erete']);
        $this->lote_inicio = intval($par['lote_inicio']);
        $this->lote_final  = intval($par['lote_final']);
        $this->modo_param  = trim($par['modo']);

        if ($this->buscar_cod !== 1 && $this->buscar_cod !== 2) {
            $this->buscar_cod = (stripos($this->cod_bovs, 'SOVCA-') === 0 || stripos($this->cod_bovs, 'BOVS-') === 0) ? 1 : 2;
        }

        $this->mfundos_id = isset($_SESSION['mfundos_id']) ? intval($_SESSION['mfundos_id']) : 0;

        $this->titulo            = '';
        $this->titulo_reporte    = 'REPORTE DE CONTROL SANITARIO';
        $this->subtitulo_reporte = '';
        $this->resumen_reporte   = '';
        $this->nombre_archivo    = 'Control_Sanitario.pdf';
        $this->controles         = array();
        $this->productos         = array();
        $this->grupos            = array();
        $this->miembros          = array();
        $this->cadena_rango_fecha = '';

        $sql_fundo = 'SELECT nombre, cid_rif FROM mfundos WHERE id=' . intval($this->mfundos_id);
        $this->arrp_fundo    = $this->bd->select($sql_fundo);
        $this->nombre_fundo  = isset($this->arrp_fundo[0]['nombre'])  ? $this->arrp_fundo[0]['nombre']  : 'Sin Fundo';
        $this->cid_rif_fundo = isset($this->arrp_fundo[0]['cid_rif']) ? $this->arrp_fundo[0]['cid_rif'] : '';

        /* Rango de lotes: admite los dos extremos o uno solo. */
        $this->lote_desde = 0;
        $this->lote_hasta = 0;
        $this->tiene_filtro_lote = ($this->lote_inicio > 0 || $this->lote_final > 0);
        if ($this->tiene_filtro_lote) {
            $this->lote_desde = $this->lote_inicio > 0 ? $this->lote_inicio : 0;
            $this->lote_hasta = $this->lote_final > 0 ? $this->lote_final : 0;
            if ($this->lote_desde > 0 && $this->lote_hasta > 0 && $this->lote_desde > $this->lote_hasta) {
                $tmp = $this->lote_desde;
                $this->lote_desde = $this->lote_hasta;
                $this->lote_hasta = $tmp;
            }
        }

        /*
         * Resolución del animal por código BOVS o N° de arete.
         * El reporte por código incluye los controles individuales y los
         * recibidos en lote. Si se indican fechas se respeta ese período;
         * si no, se muestra el historial completo.
         */
        $this->animal         = $this->resolver_animal();
        $this->cod_solicitado = ($this->cod_bovs !== '');

        if ($this->animal !== null) {
            $this->modo_vista = 'animal';
        } elseif ($this->modo_param === 'lote' || $this->tiene_filtro_lote) {
            $this->modo_vista = 'lote';
        } else {
            $this->modo_vista = 'animal';
        }

        $this->preparar_titulos();

        if ($this->mfundos_id <= 0) {
            $this->arrp = array();
            $this->reg  = 0;
            return;
        }

        if ($this->modo_vista === 'lote') {
            $this->cargar_modo_lote();
        } else {
            $this->cargar_modo_animal();
        }

        $this->arrp = $this->controles;
        $this->reg  = 0;
    }

    /* ============================================================
       PARSEO / FORMATEO
       ============================================================ */
    function parsear_parametros($parametros)
    {
        $out = array(
            'cod_bovs'     => '',
            'fecha_inicio' => '',
            'fecha_final'  => '',
            'codigo_erete' => '',
            'lote_inicio'  => '',
            'lote_final'   => '',
            'modo'         => ''
        );

        if ($parametros === false || $parametros === null || $parametros === '') {
            return $out;
        }

        $valores = explode('&', $parametros);
        foreach ($valores as $par) {
            if ($par === '' || strpos($par, '=') === false) continue;
            $partes = explode('=', $par, 2);
            $clave  = $partes[0];
            $valor  = isset($partes[1]) ? urldecode($partes[1]) : '';
            if (array_key_exists($clave, $out)) {
                $out[$clave] = $valor;
            }
        }
        return $out;
    }

    function normalizar_fecha($valor)
    {
        $valor = trim((string)$valor);
        if ($valor === '') return '';

        $formatos = array('Y-m-d', 'd-m-Y', 'd/m/Y');
        foreach ($formatos as $formato) {
            $d = DateTime::createFromFormat($formato, $valor);
            if ($d && $d->format($formato) === $valor) {
                return $d->format('Y-m-d');
            }
        }

        $ts = strtotime($valor);
        return $ts ? date('Y-m-d', $ts) : '';
    }

    function sql_texto($valor)
    {
        return str_replace("'", "''", (string)$valor);
    }

    function fmt_fecha($valor)
    {
        if ($valor === null || $valor === '') return '';
        $ts = strtotime((string)$valor);
        return $ts ? date('d-m-Y', $ts) : (string)$valor;
    }

    function nombre_lote_por_id($id)
    {
        $id = intval($id);
        if ($id <= 0) return '';

        $rs = $this->bd->select('SELECT nombre FROM mlotes WHERE id = ' . $id);
        if ($rs && count($rs) > 0) {
            return trim($rs[0]['nombre']);
        }
        return '';
    }

    function resolver_animal()
    {
        if ($this->cod_bovs === '' || $this->mfundos_id <= 0) return null;

        $rs = null;

        if ($this->buscar_cod === 1) {

            /* Código SOVCA: el usuario puede escribirlo con ceros de más o de
               menos (SOVCA-005-199 vs SOVCA-05-0199). Se prueban las variantes
               EN ORDEN —la tal cual se escribió primero— y se corta al primer
               acierto, para no confundir dos animales distintos. */
            $candidatos = bovs_codigos_candidatos($this->cod_bovs, $this->mfundos_id);

            foreach ($candidatos as $c) {
                $sql = "SELECT id, codigo_rgb_id_animal,
                               COALESCE(nro_arete, '') AS nro_arete,
                               COALESCE(nombre, '') AS nombre
                          FROM manimales
                         WHERE mfundos_id = " . intval($this->mfundos_id) . "
                           AND codigo_rgb_id_animal = '" . $this->sql_texto($c) . "'
                         ORDER BY id
                         LIMIT 1";
                $rs = $this->bd->select($sql);
                if ($rs && count($rs) > 0) {
                    break;
                }
            }

            /* Último recurso: comparar el número final del código.
               Cubre cualquier relleno intermedio poco habitual. */
            if (!$rs || count($rs) === 0) {
                $nro = bovs_numero_de_codigo($this->cod_bovs);
                if ($nro > 0) {
                    $sql = "SELECT id, codigo_rgb_id_animal,
                                   COALESCE(nro_arete, '') AS nro_arete,
                                   COALESCE(nombre, '') AS nombre
                              FROM manimales
                             WHERE mfundos_id = " . intval($this->mfundos_id) . "
                               AND COALESCE(substring(codigo_rgb_id_animal from '([0-9]+)$'), '') <> ''
                               AND substring(codigo_rgb_id_animal from '([0-9]+)$')::bigint = " . intval($nro) . "
                             ORDER BY id
                             LIMIT 1";
                    $rs = $this->bd->select($sql);
                }
            }

        } else {

            $valor = $this->sql_texto($this->cod_bovs);
            $sql = "SELECT id, codigo_rgb_id_animal,
                           COALESCE(nro_arete, '') AS nro_arete,
                           COALESCE(nombre, '') AS nombre
                      FROM manimales
                     WHERE mfundos_id = " . intval($this->mfundos_id) . "
                       AND nro_arete = '" . $valor . "'
                     ORDER BY id
                     LIMIT 1";
            $rs = $this->bd->select($sql);
        }

        if ($rs && count($rs) > 0) {
            return array(
                'id'        => intval($rs[0]['id']),
                'codigo'    => $rs[0]['codigo_rgb_id_animal'],
                'nro_arete' => $rs[0]['nro_arete'],
                'nombre'    => $rs[0]['nombre']
            );
        }
        return null;
    }

    function preparar_titulos()
    {
        $rango = '';
        if ($this->fecha_desde !== '' && $this->fecha_hasta !== '') {
            $rango = 'Desde el ' . $this->fecha_desde . ' Hasta el ' . $this->fecha_hasta;
        }
        $this->cadena_rango_fecha = $rango;

        if ($this->modo_vista === 'lote') {
            $this->titulo_reporte = 'REPORTE DE CONTROL SANITARIO EN LOTES';
            $this->nombre_archivo = 'Control_Sanitario_Lotes.pdf';

            $detalle = $rango;
            if ($this->tiene_filtro_lote) {
                if ($this->lote_desde > 0 && $this->lote_hasta > 0) {
                    $nombre_desde = $this->nombre_lote_por_id($this->lote_desde);
                    $nombre_hasta = $this->nombre_lote_por_id($this->lote_hasta);
                    if ($this->lote_desde == $this->lote_hasta) {
                        $lote_txt = 'Lote: ' . ($nombre_desde !== '' ? $nombre_desde : $this->lote_desde);
                    } else {
                        $lote_txt = 'Lotes: '
                            . ($nombre_desde !== '' ? $nombre_desde : $this->lote_desde)
                            . ' al '
                            . ($nombre_hasta !== '' ? $nombre_hasta : $this->lote_hasta);
                    }
                } elseif ($this->lote_desde > 0) {
                    $nombre_desde = $this->nombre_lote_por_id($this->lote_desde);
                    $lote_txt = 'Lotes desde: ' . ($nombre_desde !== '' ? $nombre_desde : $this->lote_desde);
                } else {
                    $nombre_hasta = $this->nombre_lote_por_id($this->lote_hasta);
                    $lote_txt = 'Lotes hasta: ' . ($nombre_hasta !== '' ? $nombre_hasta : $this->lote_hasta);
                }
                $detalle = ($detalle !== '' ? $detalle . '  |  ' : '') . $lote_txt;
            }
            $this->subtitulo_reporte = ($detalle !== '') ? $detalle : 'Todos los lotes del fundo';
            return;
        }

        if ($this->animal !== null) {
            $this->titulo_reporte = 'HISTORIAL DE CONTROL SANITARIO DEL ANIMAL';
            $this->nombre_archivo = 'Control_Sanitario_Animal.pdf';

            $ident = 'Código: ' . $this->animal['codigo'];
            if (trim($this->animal['nombre']) !== '') {
                $ident .= '  |  Nombre: ' . $this->animal['nombre'];
            }
            if (trim($this->animal['nro_arete']) !== '') {
                $ident .= '  |  N° Arete: ' . $this->animal['nro_arete'];
            }
            if ($rango !== '') {
                $this->subtitulo_reporte = $ident . '  |  ' . $rango . '  |  Individuales y por lote';
            } else {
                $this->subtitulo_reporte = $ident . '  |  Historial completo (individuales y por lote)';
            }
            return;
        }

        if ($this->cod_solicitado && $this->animal === null) {
            $this->titulo_reporte = 'HISTORIAL DE CONTROL SANITARIO DEL ANIMAL';
            $this->nombre_archivo = 'Control_Sanitario_Animal.pdf';
            $this->subtitulo_reporte = 'Código/Arete: ' . $this->cod_bovs . '  |  Sin registros en el fundo';
            return;
        }

        $this->titulo_reporte = 'REPORTE DE CONTROL SANITARIO';
        $this->subtitulo_reporte = ($rango !== '') ? $rango : 'Todos los controles del fundo';
    }

    function cadena_fecha_sql($campo)
    {
        /*
         * El rango de fechas se respeta SIEMPRE, también en el reporte por
         * código: si el usuario delimita un período, sólo se muestran los
         * controles de ese período. Sin fechas se devuelve el historial completo.
         */
        if ($this->fecha_desde === '' || $this->fecha_hasta === '') return '';

        return " AND " . $campo . "::date BETWEEN DATE '" . $this->fecha_desde . "' AND DATE '" . $this->fecha_hasta . "'";
    }

    function cadena_lote_sql($campo)
    {
        if (!$this->tiene_filtro_lote) return '';

        if ($this->lote_desde > 0 && $this->lote_hasta > 0) {
            return " AND " . $campo . " BETWEEN " . intval($this->lote_desde) . " AND " . intval($this->lote_hasta);
        }
        if ($this->lote_desde > 0) {
            return " AND " . $campo . " >= " . intval($this->lote_desde);
        }
        return " AND " . $campo . " <= " . intval($this->lote_hasta);
    }

    /* ============================================================
       CARGA DE DATOS (una consulta de cabeceras + una de productos)
       ============================================================ */
    function cargar_modo_animal()
    {
        if ($this->cod_solicitado && $this->animal === null) {
            return;
        }

        $mf = intval($this->mfundos_id);
        $filtro_fecha_ind  = $this->cadena_fecha_sql('a.fecha_control');
        $filtro_fecha_lote = $this->cadena_fecha_sql('c.fecha_control');
        $filtro_lote       = $this->cadena_lote_sql('c.mlotes_id');
        $filtro_cod_ind    = '';
        $filtro_cod_lote   = '';
        $filtro_activo_ind  = '';
        $filtro_activo_lote = '';

        if ($this->animal !== null) {
            /* Historial puntual: se incluye aunque el animal ya no esté activo. */
            $filtro_cod_ind  = " AND a.codigo_rgb_id_animal = '" . $this->sql_texto($this->animal['codigo']) . "'";
            $filtro_cod_lote = " AND d.manimales_id = " . intval($this->animal['id']);
        } else {
            /* Reporte amplio: misma semántica del reporte anterior (sólo animales activos). */
            $filtro_activo_ind  = ' AND b.status = 1 AND b.mestatus_id = 1';
            $filtro_activo_lote = ' AND b.status > 0 AND b.mestatus_id = 1';
        }

        $sql = "
            SELECT * FROM (
                SELECT 'IND' AS origen,
                       a.id AS id_control,
                       b.id AS id_animal,
                       b.codigo_rgb_id_animal AS codigo_animal,
                       COALESCE(b.nro_arete, '') AS nro_arete,
                       COALESCE(b.nombre, '') AS nombre_animal,
                       a.fecha_control,
                       a.fecha_prox_control,
                       a.evento,
                       a.responsable,
                       COALESCE(a.observaciones, '') AS observaciones,
                       ''::varchar AS codigo_mcs_lote,
                       ''::varchar AS lote_nombre,
                       0 AS mlotes_id
                  FROM mcontrolsanitarios a
            INNER JOIN manimales b
                    ON b.mfundos_id = a.mfundos_id
                   AND b.codigo_rgb_id_animal = a.codigo_rgb_id_animal
                 WHERE a.mfundos_id = " . $mf . "
                   AND a.status = 1
                   " . $filtro_fecha_ind . "
                   " . $filtro_cod_ind . "
                   " . $filtro_activo_ind . "

                UNION ALL

                SELECT 'LOTE' AS origen,
                       c.id AS id_control,
                       b.id AS id_animal,
                       b.codigo_rgb_id_animal AS codigo_animal,
                       COALESCE(b.nro_arete, '') AS nro_arete,
                       COALESCE(b.nombre, '') AS nombre_animal,
                       c.fecha_control,
                       c.fecha_prox_control,
                       c.evento,
                       c.responsable,
                       COALESCE(c.observaciones, '') AS observaciones,
                       c.codigo_mcs_lote,
                       COALESCE(l.nombre, '') AS lote_nombre,
                       c.mlotes_id
                  FROM mcontrolsanitarioxlotes c
            INNER JOIN mdetacsxlotes d
                    ON d.mcontrolsanitarioxlotes_id = c.id
                   AND d.status = 1
            INNER JOIN manimales b
                    ON b.id = d.manimales_id
                   AND b.mfundos_id = c.mfundos_id
             LEFT JOIN mlotes l
                    ON l.id = c.mlotes_id
                 WHERE c.mfundos_id = " . $mf . "
                   AND c.status = 1
                   " . $filtro_fecha_lote . "
                   " . $filtro_cod_lote . "
                   " . $filtro_lote . "
                   " . $filtro_activo_lote . "
            ) u
            ORDER BY codigo_animal, fecha_control, origen, id_control
        ";

        $rs = $this->bd->select($sql);
        if ($rs) {
            foreach ($rs as $row) {
                $this->controles[] = $row;
            }
        }

        $ids_ind   = array();
        $ids_lote  = array();
        $total_ind = 0;
        $total_lote = 0;

        foreach ($this->controles as $row) {
            $origen     = isset($row['origen']) ? $row['origen'] : 'IND';
            $id_control = intval($row['id_control']);

            if ($origen === 'LOTE') {
                $ids_lote[$id_control] = true;
                $total_lote++;
            } else {
                $ids_ind[$id_control] = true;
                $total_ind++;
            }

            $codigo = $row['codigo_animal'];
            if (!isset($this->grupos[$codigo])) {
                $this->grupos[$codigo] = array(
                    'codigo'    => $codigo,
                    'nro_arete' => $row['nro_arete'],
                    'nombre'    => $row['nombre_animal'],
                    'total'     => 0,
                    'ind'       => 0,
                    'lote'      => 0,
                    'controles' => array()
                );
            }

            $this->grupos[$codigo]['total']++;
            if ($origen === 'LOTE') {
                $this->grupos[$codigo]['lote']++;
            } else {
                $this->grupos[$codigo]['ind']++;
            }
            $this->grupos[$codigo]['controles'][] = $row;
        }

        $this->productos = $this->cargar_productos(array_keys($ids_ind), array_keys($ids_lote));

        $this->resumen_reporte = 'CONTROLES REGISTRADOS: ' . count($this->controles)
            . '   |   INDIVIDUALES: ' . $total_ind
            . '   |   EN LOTE: ' . $total_lote
            . '   |   ANIMALES: ' . count($this->grupos);
    }

    function cargar_modo_lote()
    {
        if ($this->cod_solicitado && $this->animal === null) {
            return;
        }

        $mf = intval($this->mfundos_id);
        $filtro_fecha  = $this->cadena_fecha_sql('c.fecha_control');
        $filtro_lote   = $this->cadena_lote_sql('c.mlotes_id');
        $filtro_animal = '';

        if ($this->animal !== null) {
            $filtro_animal = " AND EXISTS (
                SELECT 1 FROM mdetacsxlotes dx
                 WHERE dx.mcontrolsanitarioxlotes_id = c.id
                   AND dx.status = 1
                   AND dx.manimales_id = " . intval($this->animal['id']) . "
            )";
        }

        $sql = "
            SELECT c.id AS id_control,
                   c.codigo_mcs_lote,
                   c.fecha_control,
                   c.fecha_prox_control,
                   c.evento,
                   c.responsable,
                   COALESCE(c.observaciones, '') AS observaciones,
                   c.mlotes_id,
                   COALESCE(l.nombre, '') AS lote_nombre,
                   (SELECT COUNT(*)
                      FROM mdetacsxlotes d
                INNER JOIN manimales bx ON bx.id = d.manimales_id
                     WHERE d.mcontrolsanitarioxlotes_id = c.id
                       AND d.status = 1) AS total_animales
              FROM mcontrolsanitarioxlotes c
         LEFT JOIN mlotes l ON l.id = c.mlotes_id
             WHERE c.mfundos_id = " . $mf . "
               AND c.status = 1
               " . $filtro_fecha . "
               " . $filtro_lote . "
               " . $filtro_animal . "
             ORDER BY c.fecha_control, c.codigo_mcs_lote, c.id
        ";

        $rs = $this->bd->select($sql);
        if ($rs) {
            foreach ($rs as $row) {
                $this->controles[] = $row;
            }
        }

        if (count($this->controles) === 0) {
            return;
        }

        $ids_lote       = array();
        $total_animales = 0;
        foreach ($this->controles as $row) {
            $ids_lote[intval($row['id_control'])] = true;
            $total_animales += intval($row['total_animales']);
        }

        $this->productos = $this->cargar_productos(array(), array_keys($ids_lote));
        $this->miembros  = $this->cargar_miembros_lote(array_keys($ids_lote));

        $this->resumen_reporte = 'CONTROLES POR LOTE: ' . count($this->controles)
            . '   |   ANIMALES INCLUIDOS: ' . $total_animales;
    }

    function cargar_productos($ids_ind, $ids_lote)
    {
        $partes = array();

        if (count($ids_ind) > 0) {
            $partes[] = "
                SELECT 'IND' AS origen,
                       d.mcontrolsanitarios_id AS id_control,
                       COALESCE(cat.nombre, '') AS nombre_categoria,
                       COALESCE(p.nombre, '') AS nombre_producto,
                       COALESCE(d.dosis, '') AS dosis,
                       COALESCE(d.detalles, '') AS detalles
                  FROM mdetacrlsanitarios d
             LEFT JOIN mproductos p ON p.id = d.mproductos_id
             LEFT JOIN mcategoriaproductos cat ON cat.id = p.mcategoriaproductos_id
                 WHERE d.status = 1
                   AND d.mcontrolsanitarios_id IN (" . implode(',', $ids_ind) . ")
            ";
        }

        if (count($ids_lote) > 0) {
            $partes[] = "
                SELECT 'LOTE' AS origen,
                       d.mcontrolsanitarioxlotes_id AS id_control,
                       COALESCE(cat.nombre, '') AS nombre_categoria,
                       COALESCE(p.nombre, '') AS nombre_producto,
                       COALESCE(d.dosis, '') AS dosis,
                       COALESCE(d.detalles, '') AS detalles
                  FROM mdetacrlsanitariolotes d
             LEFT JOIN mproductos p ON p.id = d.mproductos_id
             LEFT JOIN mcategoriaproductos cat ON cat.id = p.mcategoriaproductos_id
                 WHERE d.status > 0
                   AND d.mcontrolsanitarioxlotes_id IN (" . implode(',', $ids_lote) . ")
            ";
        }

        if (count($partes) === 0) return array();

        $sql = implode(" UNION ALL ", $partes) . " ORDER BY nombre_categoria, nombre_producto";
        $rs = $this->bd->select($sql);

        $out = array();
        if ($rs) {
            foreach ($rs as $row) {
                $key = $row['origen'] . '|' . intval($row['id_control']);
                if (!isset($out[$key])) $out[$key] = array();
                $out[$key][] = $row;
            }
        }
        return $out;
    }

    function cargar_miembros_lote($ids_lote)
    {
        if (count($ids_lote) === 0) return array();

        $sql = "
            SELECT d.mcontrolsanitarioxlotes_id AS id_control,
                   b.codigo_rgb_id_animal AS codigo_animal,
                   COALESCE(b.nro_arete, '') AS nro_arete,
                   COALESCE(b.nombre, '') AS nombre_animal
              FROM mdetacsxlotes d
        INNER JOIN manimales b ON b.id = d.manimales_id
             WHERE d.status = 1
               AND d.mcontrolsanitarioxlotes_id IN (" . implode(',', $ids_lote) . ")
             ORDER BY b.codigo_rgb_id_animal
        ";
        $rs = $this->bd->select($sql);

        $out = array();
        if ($rs) {
            foreach ($rs as $row) {
                $id = intval($row['id_control']);
                if (!isset($out[$id])) $out[$id] = array();
                $out[$id][] = $row;
            }
        }
        return $out;
    }

    /* ============================================================
       ENCABEZADO DEL REPORTE
       ============================================================ */
    function getEncabezado($x, $y)
    {
        $this->SetBorder(1);
        $this->SetDrawColor(2, 157, 116);
        $this->SetFillColor(230, 230, 230);
        $this->SetFont('ARIAL', 'B', 10);
        $this->SetXY($x + 0, $y - 20);
        $this->SetWidths(array(190));
        $this->SetAligns(array('C'));
        $this->SetBorder(1);
        $this->SetJump(12);
        $this->RowM(array(''));
        $this->SetXY($x + 60, $y - 15);
        $this->SetWidths(array(160));
        $this->SetAligns(array('C'));

        $fecha = date('d/m/Y');

        $this->SetWidths(array(90, 40));
        $this->SetAligns(array('C', 'R'));

        $this->RowM(array(
            __pdf_txt($this->nombre_fundo . '  ' . $this->cid_rif_fundo),
            'Fecha: ' . $fecha
        ));

        $this->SetXY($x + 5, $y);
        $this->SetJump(6);
        $this->SetWidths(array(190));
        $this->SetAligns(array('C'));
        $this->SetFont('ARIAL', 'B', 9);
        $this->RowM(array(__pdf_txt($this->titulo_reporte)));

        if ($this->subtitulo_reporte !== '') {
            $this->SetXY($x + 5, $this->GetY());
            $this->SetJump(4);
            $this->SetWidths(array(190));
            $this->SetAligns(array('C'));
            $this->SetFont('ARIAL', '', 7.5);
            $this->RowM(array(__pdf_txt($this->subtitulo_reporte)));
        }
    }

    function Footer()
    {
        $this->y2 = 257;
        $this->SetFillColor(230, 230, 230);
        $this->SetFont('ARIAL', 'B', '8');
    }

    function Pie_Pagina()
    {
        $this->SetFillColor(230, 230, 230);
        $this->SetTextColor(0, 0, 100);
        $this->SetFont("ARIAL", "B", 7);
        $this->SetXY(10, 330);
        $this->SetWidths(array(190));
        $this->SetAligns(array('R'));
        $this->RowM(array(__pdf_txt('Sistema SOVCA (https://global.ruted.net)')));
    }

    function Header()
    {
        $this->cab->poner_cabecera($this, $this->titulo, 'p', '', 'n');
        $y = $this->GetY();
        $x = $this->GetX();

        $this->getEncabezado($x, $y);
        $this->SetWidths(array(60));
        $this->SetAligns(array('L'));
        $this->SetXY(10, 23);
    }

    function Cuerpo()
    {
        $this->Pie_Pagina();
        $this->SetXY(10, 42);

        $this->SetTextColor(0, 0, 0);
        $this->SetFont('ARIAL', 'B', 8);
        $this->SetFillColor(230, 238, 235);
        $this->SetWidths(array(190));
        $this->SetAligns(array('L'));
        $this->SetBorder(true);
        $this->SetJump(5);
        $this->RowMFillColor(array(__pdf_txt($this->resumen_reporte)), true);
        $this->Ln(4);

        if ($this->modo_vista === 'lote') {
            $this->cuerpoLotes();
        } else {
            $this->cuerpoAnimales();
        }
    }

    /* ============================================================
       HELPERS DE RENDER
       ============================================================ */
    function saltoPagina($limite)
    {
        if ($this->GetY() > $limite) {
            $this->AddPage();
            $this->Pie_Pagina();
            $this->SetXY(10, 42);
            return true;
        }
        return false;
    }

    function filaTitulo($texto, $ancho)
    {
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('ARIAL', '', 9);
        $this->SetFillColor(200, 250, 216);
        $this->SetWidths(array($ancho));
        $this->SetAligns(array('C'));
        $this->SetBorder(true);
        $this->SetJump(7);
        $this->RowMFillColor(array(__pdf_txt($texto)), true);
    }

    function tablaCabecera($widths, $aligns, $labels, $jump = 6)
    {
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('ARIAL', 'B', 8);
        $this->SetFillColor(100, 200, 116);
        $this->SetWidths($widths);
        $this->SetAligns($aligns);
        $this->SetBorder(true);
        $this->SetJump($jump);
        $this->RowMFillColor(array_map('__pdf_txt', $labels), true);
    }

    function tablaFila($widths, $aligns, $values, $texto, $fondo, $jump = 6)
    {
        $this->SetTextColor($texto[0], $texto[1], $texto[2]);
        $this->SetFont('ARIAL', '', 8);
        $this->SetFillColor($fondo[0], $fondo[1], $fondo[2]);
        $this->SetWidths($widths);
        $this->SetAligns($aligns);
        $this->SetBorder(true);
        $this->SetJump($jump);
        $this->RowMFillColor(array_map('__pdf_txt', $values), true);
    }

    function detalleCabecera()
    {
        $this->tablaCabecera(
            array(23, 47, 22, 46, 27, 25),
            array('C', 'L', 'C', 'L', 'L', 'C'),
            array('Fecha Control', 'Evento', 'Origen', 'Lote / Código RCS', 'Responsable', 'Próximo Control')
        );
    }

    function productosCabecera()
    {
        $this->tablaCabecera(
            array(42, 55, 20, 73),
            array('C', 'C', 'C', 'L'),
            array('Categoría', 'Producto', 'Dosis', 'Indicaciones')
        );
    }

    function filaControl($c)
    {
        $origen = (isset($c['origen']) && $c['origen'] === 'LOTE') ? 'Lote' : 'Individual';

        $lote = '-';
        if ($origen === 'Lote') {
            $lote   = trim($c['lote_nombre']);
            $codigo = trim($c['codigo_mcs_lote']);
            if ($lote === '') {
                $lote = $codigo;
            } elseif ($codigo !== '') {
                $lote .= ' (' . $codigo . ')';
            }
            if ($lote === '') $lote = '-';
        }

        $this->tablaFila(
            array(23, 47, 22, 46, 27, 25),
            array('C', 'L', 'C', 'L', 'L', 'C'),
            array(
                $this->fmt_fecha($c['fecha_control']),
                $c['evento'],
                $origen,
                $lote,
                $c['responsable'],
                $this->fmt_fecha($c['fecha_prox_control'])
            ),
            array(0, 0, 0),
            array(200, 250, 216)
        );

        $key = (isset($c['origen']) ? $c['origen'] : 'IND') . '|' . intval($c['id_control']);
        $productos = isset($this->productos[$key]) ? $this->productos[$key] : array();

        if (count($productos) > 0) {
            $this->saltoPagina(285);
            $this->productosCabecera();

            foreach ($productos as $p) {
                if ($this->saltoPagina(285)) {
                    $this->productosCabecera();
                }
                $this->tablaFila(
                    array(42, 55, 20, 73),
                    array('L', 'L', 'C', 'L'),
                    array($p['nombre_categoria'], $p['nombre_producto'], $p['dosis'], $p['detalles']),
                    array(0, 0, 150),
                    array(200, 250, 216)
                );
            }
        }

        $this->saltoPagina(290);

        $obs = trim($c['observaciones']);
        if ($obs === '') $obs = 'Sin observaciones.';

        $this->tablaCabecera(array(190), array('L'), array('OBSERVACIONES'), 5);
        $this->tablaFila(
            array(190),
            array('L'),
            array($obs),
            array(0, 0, 150),
            array(200, 250, 216),
            5
        );

        $this->Ln(4);
    }

    function cuerpoAnimales()
    {
        $widths = array(34, 26, 62, 20, 22, 26);
        $aligns = array('C', 'C', 'C', 'C', 'C', 'C');

        foreach ($this->grupos as $g) {
            $this->saltoPagina(235);

            $this->filaTitulo('DATOS DEL ANIMAL', 80);

            $this->tablaCabecera(
                $widths,
                $aligns,
                array('Código SOVCA', 'N° Arete', 'Nombre', 'Total', 'Individuales', 'En Lote')
            );
            $this->tablaFila(
                $widths,
                $aligns,
                array(
                    $g['codigo'],
                    $g['nro_arete'],
                    $g['nombre'],
                    $g['total'],
                    $g['ind'],
                    $g['lote']
                ),
                array(0, 0, 150),
                array(200, 250, 216)
            );

            $this->filaTitulo('DETALLES', 80);
            $this->detalleCabecera();

            foreach ($g['controles'] as $c) {
                if ($this->saltoPagina(250)) {
                    $this->detalleCabecera();
                }
                $this->filaControl($c);
            }

            $this->Ln(3);
        }
    }

    function cuerpoLotes()
    {
        foreach ($this->controles as $c) {
            $this->saltoPagina(215);

            $this->filaTitulo('CONTROL SANITARIO EN LOTE', 90);

            $this->tablaCabecera(
                array(38, 32, 90, 30),
                array('C', 'C', 'C', 'C'),
                array('Código RCS', 'Fecha Control', 'Lote', 'N° Animales')
            );
            $this->tablaFila(
                array(38, 32, 90, 30),
                array('C', 'C', 'L', 'C'),
                array(
                    $c['codigo_mcs_lote'],
                    $this->fmt_fecha($c['fecha_control']),
                    $c['lote_nombre'],
                    intval($c['total_animales'])
                ),
                array(0, 0, 150),
                array(200, 250, 216)
            );

            $this->tablaCabecera(
                array(70, 60, 60),
                array('C', 'C', 'C'),
                array('Evento', 'Responsable', 'Próximo Control')
            );
            $this->tablaFila(
                array(70, 60, 60),
                array('L', 'L', 'C'),
                array(
                    $c['evento'],
                    $c['responsable'],
                    $this->fmt_fecha($c['fecha_prox_control'])
                ),
                array(0, 0, 0),
                array(200, 250, 216)
            );

            $key = 'LOTE|' . intval($c['id_control']);
            $productos = isset($this->productos[$key]) ? $this->productos[$key] : array();

            if (count($productos) > 0) {
                $this->saltoPagina(285);
                $this->productosCabecera();

                foreach ($productos as $p) {
                    if ($this->saltoPagina(285)) {
                        $this->productosCabecera();
                    }
                    $this->tablaFila(
                        array(42, 55, 20, 73),
                        array('L', 'L', 'C', 'L'),
                        array($p['nombre_categoria'], $p['nombre_producto'], $p['dosis'], $p['detalles']),
                        array(0, 0, 150),
                        array(200, 250, 216)
                    );
                }
            } else {
                $this->tablaFila(
                    array(190),
                    array('L'),
                    array('Sin productos registrados.'),
                    array(0, 0, 150),
                    array(200, 250, 216)
                );
            }

            $this->saltoPagina(290);

            $obs = trim($c['observaciones']);
            if ($obs === '') $obs = 'Sin observaciones.';

            $this->tablaCabecera(array(190), array('L'), array('OBSERVACIONES'), 5);
            $this->tablaFila(
                array(190),
                array('L'),
                array($obs),
                array(0, 0, 150),
                array(200, 250, 216),
                5
            );

            $this->filaTitulo('ANIMALES INCLUIDOS', 80);
            $this->tablaCabecera(
                array(62, 48, 80),
                array('C', 'C', 'C'),
                array('Código SOVCA', 'N° Arete', 'Nombre')
            );

            $id_control = intval($c['id_control']);
            $miembros   = isset($this->miembros[$id_control]) ? $this->miembros[$id_control] : array();

            if (count($miembros) === 0) {
                $this->tablaFila(
                    array(190),
                    array('L'),
                    array('Sin animales registrados en este control.'),
                    array(0, 0, 150),
                    array(200, 250, 216)
                );
            } else {
                foreach ($miembros as $m) {
                    if ($this->saltoPagina(290)) {
                        $this->tablaCabecera(
                            array(62, 48, 80),
                            array('C', 'C', 'C'),
                            array('Código SOVCA', 'N° Arete', 'Nombre')
                        );
                    }
                    $this->tablaFila(
                        array(62, 48, 80),
                        array('C', 'C', 'L'),
                        array($m['codigo_animal'], $m['nro_arete'], $m['nombre_animal']),
                        array(0, 0, 0),
                        array(200, 250, 216)
                    );
                }
            }

            $this->Ln(5);
        }
    }
}