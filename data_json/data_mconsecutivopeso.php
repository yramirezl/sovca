<?php
/* ============================================================
   SOVCA | Consecutivo de Pesos — API del listado por animal
   ------------------------------------------------------------
   action=lista   (por defecto)
     ?pagina=1&tamano=25&q=texto
     -> animales paginados por "último pesaje" DESC, con su peso
        actual, ganancia total y ganancia diaria promedio.

   action=detalle
     ?codigo=SOVCA-059-01&pagina=1&tamano=15
     -> pesajes paginados del animal (fecha DESC) + totales. Cada
        fila trae los días desde el pesaje anterior, la ganancia de
        peso (GDP) y la ganancia diaria (GDdP) calculadas con LAG()
        sobre TODA la serie (no solo la página visible).

   La paginación es del lado del servidor: un productor que pesa a
   diario acumula decenas de pesajes por animal y cientos de animales.
   ============================================================ */

/* La sesión se lee ANTES de cargar el modelo: config/conexion.php
   inicia y cierra la sesión al inicializar ActiveRecord. */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$mfundos_id = isset($_SESSION['mfundos_id']) ? intval($_SESSION['mfundos_id']) : 0;
$id_sesion  = isset($_SESSION['idsessionx']) ? $_SESSION['idsessionx'] : '';

require_once '../models/Mconsecutivopeso.php';
date_default_timezone_set('America/Caracas');

/*
 * Días en que un pesaje sigue siendo editable. En pesaje se usa una
 * ventana más corta que en producción de leche: quien pesa a diario
 * solo necesita corregir lo de estos días, y un cambio viejo altera
 * las ganancias (GDP) de todos los pesajes siguientes.
 */
if (!defined('PW_DIAS_EDICION')) {
    define('PW_DIAS_EDICION', 7);
}

/* ------------------------------------------------------------
   Utilidades
   ------------------------------------------------------------ */

function pw_fecha_dmy($iso)
{
    $iso = trim((string)$iso);
    if ($iso === '') return '';

    $d = DateTime::createFromFormat('Y-m-d', substr($iso, 0, 10));

    return ($d instanceof DateTime) ? $d->format('d-m-Y') : $iso;
}

function pw_sin_acentos($texto)
{
    $texto = function_exists('mb_strtolower')
        ? mb_strtolower((string)$texto, 'UTF-8')
        : strtolower((string)$texto);

    return strtr($texto, array(
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
        'ñ' => 'n'
    ));
}

/* Texto seguro para un LIKE (escapa comilla simple, % y _). */
function pw_like($texto)
{
    $texto = pw_sin_acentos($texto);
    $texto = str_replace("'", "''", $texto);
    $texto = str_replace('\\', '\\\\', $texto);
    $texto = str_replace('%', '\\%', $texto);
    $texto = str_replace('_', '\\_', $texto);

    return $texto;
}

/* "Resumen" en PDF: reporte de consecutivo de peso para un animal. */
function pw_url_resumen($codigo)
{
    $params = 'codigo_bovs=' . $codigo;

    return '../../Reportes/reportes/sovca/rcp.php?%$%=' . rawurlencode(base64_encode($params));
}

function pw_fecha_de($valor)
{
    if ($valor instanceof DateTime) {
        return $valor->format('Y-m-d');
    }

    return substr(trim((string)$valor), 0, 10);
}

/* Días entre dos fechas ISO (0 si falta alguna). */
function pw_dias_entre($desde, $hasta)
{
    $desde = substr((string)$desde, 0, 10);
    $hasta = substr((string)$hasta, 0, 10);
    if ($desde === '' || $hasta === '') return 0;

    $a = DateTime::createFromFormat('Y-m-d', $desde);
    $b = DateTime::createFromFormat('Y-m-d', $hasta);
    if (!($a instanceof DateTime) || !($b instanceof DateTime)) return 0;

    return (int)$a->diff($b)->format('%r%a');
}

/* ------------------------------------------------------------
   Parámetros
   ------------------------------------------------------------ */

$action = isset($_GET['action']) ? strtolower(trim($_GET['action'])) : 'lista';
$pagina = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$tamano = isset($_GET['tamano']) ? intval($_GET['tamano']) : 25;
$q      = isset($_GET['q']) ? trim($_GET['q']) : '';

if ($pagina < 1) $pagina = 1;

if ($tamano < 5)   $tamano = 5;
if ($tamano > 200) $tamano = 200;

$salida = array(
    'ok'             => false,
    'accion'         => $action,
    'pagina'         => $pagina,
    'tamano'         => $tamano,
    'total_paginas'  => 0,
    'total_animales' => 0,
    'total_registros'=> 0,
    'dias_edicion'   => PW_DIAS_EDICION,
    'animales'       => array(),
    'registros'      => array(),
    'mensaje'        => ''
);

if ($mfundos_id <= 0 || $id_sesion === '') {
    $salida['mensaje'] = 'La sesión no tiene un fundo activo.';
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($salida);
    exit;
}

/* ============================================================
   1) LISTA DE ANIMALES (paginada)
   ============================================================ */

if ($action === 'lista') {

    $where = "p.mfundos_id = " . $mfundos_id . " AND p.status = 1";

    if ($q !== '') {
        $q_sql = pw_like($q);

        $where .= " AND (
               translate(lower(p.codigo_rgb_id_animal), 'áéíóúüñ', 'aeiouun') LIKE '%" . $q_sql . "%'
            OR translate(lower(COALESCE(a.nombre, '')), 'áéíóúüñ', 'aeiouun') LIKE '%" . $q_sql . "%'
            OR translate(lower(COALESCE(a.nro_arete, '')), 'áéíóúüñ', 'aeiouun') LIKE '%" . $q_sql . "%'
            OR translate(lower(COALESCE(l.nombre, '')), 'áéíóúüñ', 'aeiouun') LIKE '%" . $q_sql . "%'
        )";
    }

    $from = "FROM mconsecutivopesos p
             LEFT JOIN manimales a
                    ON a.codigo_rgb_id_animal = p.codigo_rgb_id_animal
                   AND a.mfundos_id = p.mfundos_id
             LEFT JOIN mlotes l ON l.id = a.mlotes_id
             WHERE " . $where;

    /* Totales (animales y pesajes) que cumplen el filtro */
    $sql_tot = "SELECT COUNT(*) AS animales,
                       COALESCE(SUM(reg), 0) AS registros
                  FROM (SELECT p.codigo_rgb_id_animal AS codigo,
                               COUNT(*) AS reg
                          " . $from . "
                         GROUP BY p.codigo_rgb_id_animal) x;";

    $tot = Mconsecutivopeso::find_by_sql($sql_tot);

    $total_animales  = (is_array($tot) && isset($tot[0]->animales)) ? intval($tot[0]->animales) : 0;
    $total_registros = (is_array($tot) && isset($tot[0]->registros)) ? intval($tot[0]->registros) : 0;
    $total_paginas   = ($tamano > 0) ? (int)ceil($total_animales / $tamano) : 0;

    if ($total_paginas > 0 && $pagina > $total_paginas) {
        $pagina = $total_paginas;
    }

    $offset = ($pagina - 1) * $tamano;

    $sql = "SELECT p.codigo_rgb_id_animal AS codigo,
                   COALESCE(MAX(a.nombre), '')     AS nombre,
                   COALESCE(MAX(a.nro_arete), '')  AS nro_arete,
                   COALESCE(MAX(l.nombre), '')     AS lote,
                   MAX(p.fecha_pesaje)             AS ultima,
                   MIN(p.fecha_pesaje)             AS primera,
                   COUNT(*)                        AS registros,
                   COUNT(DISTINCT p.fecha_pesaje::date) AS dias,
                   (ARRAY_AGG(p.peso ORDER BY p.fecha_pesaje DESC, p.id DESC))[1] AS peso_actual,
                   (ARRAY_AGG(p.peso ORDER BY p.fecha_pesaje ASC,  p.id ASC ))[1] AS peso_inicial,
                   COALESCE(MIN(p.peso), 0)        AS peso_min,
                   COALESCE(MAX(p.peso), 0)        AS peso_max
              " . $from . "
          GROUP BY p.codigo_rgb_id_animal
          ORDER BY MAX(p.fecha_pesaje) DESC, p.codigo_rgb_id_animal
             LIMIT " . $tamano . " OFFSET " . $offset . ";";

    $data = Mconsecutivopeso::find_by_sql($sql);

    foreach ($data as $rs) {
        $codigo = trim((string)$rs->codigo);
        if ($codigo === '') continue;

        $registros = intval($rs->registros);
        $primera   = pw_fecha_de($rs->primera);
        $ultima    = pw_fecha_de($rs->ultima);
        $p_ini     = floatval($rs->peso_inicial);
        $p_act     = floatval($rs->peso_actual);
        $periodo   = pw_dias_entre($primera, $ultima);
        $ganancia  = $p_act - $p_ini;

        $salida['animales'][] = array(
            'codigo'       => $codigo,
            'nombre'       => trim((string)$rs->nombre),
            'nro_arete'    => trim((string)$rs->nro_arete),
            'lote'         => trim((string)$rs->lote),
            'ultima'       => $ultima,
            'ultima_txt'   => pw_fecha_dmy($ultima),
            'primera'      => $primera,
            'registros'    => $registros,
            'dias'         => intval($rs->dias),
            'periodo'      => $periodo,
            'peso_inicial' => round($p_ini, 2),
            'peso_actual'  => round($p_act, 2),
            'peso_min'     => round(floatval($rs->peso_min), 2),
            'peso_max'     => round(floatval($rs->peso_max), 2),
            'ganancia'     => round($ganancia, 2),
            'gdp_dia'      => $periodo > 0 ? round($ganancia / $periodo, 3) : 0,
            'url_resumen'  => pw_url_resumen($codigo)
        );
    }

    $salida['ok']              = true;
    $salida['pagina']          = $pagina;
    $salida['total_paginas']   = $total_paginas;
    $salida['total_animales']  = $total_animales;
    $salida['total_registros'] = $total_registros;

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($salida, JSON_UNESCAPED_UNICODE);
    exit;
}

/* ============================================================
   2) DETALLE DE UN ANIMAL (paginado)
   ============================================================ */

if ($action === 'detalle') {

    $codigo = isset($_GET['codigo']) ? trim($_GET['codigo']) : '';
    $codigo = str_replace("'", "''", $codigo);

    if ($codigo === '') {
        $salida['mensaje'] = 'Indique el código del animal.';
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($salida, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $where = "mfundos_id = " . $mfundos_id . "
              AND status = 1
              AND codigo_rgb_id_animal = '" . $codigo . "'";

    $sql_tot = "SELECT COUNT(*) AS registros,
                       COUNT(DISTINCT fecha_pesaje::date) AS dias,
                       MIN(fecha_pesaje) AS primera,
                       MAX(fecha_pesaje) AS ultima,
                       COALESCE(MIN(peso), 0) AS peso_min,
                       COALESCE(MAX(peso), 0) AS peso_max,
                       (ARRAY_AGG(peso ORDER BY fecha_pesaje ASC,  id ASC ))[1] AS peso_inicial,
                       (ARRAY_AGG(peso ORDER BY fecha_pesaje DESC, id DESC))[1] AS peso_actual
                  FROM mconsecutivopesos
                 WHERE " . $where . ";";

    $tot = Mconsecutivopeso::find_by_sql($sql_tot);

    $registros_tot = (is_array($tot) && isset($tot[0]->registros)) ? intval($tot[0]->registros) : 0;
    $dias          = (is_array($tot) && isset($tot[0]->dias)) ? intval($tot[0]->dias) : 0;
    $primera       = (is_array($tot) && isset($tot[0]->primera)) ? pw_fecha_de($tot[0]->primera) : '';
    $ultima        = (is_array($tot) && isset($tot[0]->ultima)) ? pw_fecha_de($tot[0]->ultima) : '';
    $p_ini         = (is_array($tot) && isset($tot[0]->peso_inicial)) ? floatval($tot[0]->peso_inicial) : 0;
    $p_act         = (is_array($tot) && isset($tot[0]->peso_actual)) ? floatval($tot[0]->peso_actual) : 0;
    $periodo       = pw_dias_entre($primera, $ultima);
    $ganancia      = $p_act - $p_ini;

    $total_paginas = ($tamano > 0) ? (int)ceil($registros_tot / $tamano) : 0;

    if ($total_paginas > 0 && $pagina > $total_paginas) {
        $pagina = $total_paginas;
    }

    $offset = ($pagina - 1) * $tamano;
    $limite = date('Y-m-d', strtotime('-' . PW_DIAS_EDICION . ' days'));

    /* LAG() sobre toda la serie -> GDP y días reales, incluso al paginar */
    $sql = "WITH serie AS (
                SELECT id, fecha_pesaje, peso,
                       LAG(peso)         OVER (ORDER BY fecha_pesaje, id) AS peso_ant,
                       LAG(fecha_pesaje) OVER (ORDER BY fecha_pesaje, id) AS fecha_ant
                  FROM mconsecutivopesos
                 WHERE " . $where . "
            )
            SELECT id,
                   fecha_pesaje,
                   peso,
                   peso_ant,
                   fecha_ant,
                   (peso - peso_ant) AS gdp,
                   (fecha_pesaje::date - fecha_ant::date) AS dias
              FROM serie
          ORDER BY fecha_pesaje DESC, id DESC
             LIMIT " . $tamano . " OFFSET " . $offset . ";";

    $data = Mconsecutivopeso::find_by_sql($sql);

    foreach ($data as $rs) {
        $fecha    = pw_fecha_de($rs->fecha_pesaje);
        $peso     = floatval($rs->peso);
        $gdp      = ($rs->gdp === null) ? null : floatval($rs->gdp);
        $dias_reg = ($rs->dias === null) ? null : intval($rs->dias);

        $salida['registros'][] = array(
            'id'        => intval($rs->id),
            'fecha'     => $fecha,
            'fecha_txt' => pw_fecha_dmy($fecha),
            'peso'      => round($peso, 2),
            'peso_ant'  => ($rs->peso_ant === null) ? null : round(floatval($rs->peso_ant), 2),
            'dias'      => $dias_reg,
            'gdp'       => ($gdp === null) ? null : round($gdp, 2),
            'gddp'      => ($gdp === null || $dias_reg === null || $dias_reg <= 0) ? null : round($gdp / $dias_reg, 3),
            'editable'  => ($fecha !== '' && $fecha >= $limite)
        );
    }

    $salida['ok']              = true;
    $salida['codigo']          = str_replace("''", "'", $codigo);
    $salida['pagina']          = $pagina;
    $salida['total_paginas']   = $total_paginas;
    $salida['total_registros'] = $registros_tot;
    $salida['dias']            = $dias;
    $salida['primera']         = $primera;
    $salida['ultima']          = $ultima;
    $salida['peso_inicial']    = round($p_ini, 2);
    $salida['peso_actual']     = round($p_act, 2);
    $salida['ganancia']        = round($ganancia, 2);
    $salida['periodo']         = $periodo;
    $salida['gdp_dia']         = $periodo > 0 ? round($ganancia / $periodo, 3) : 0;

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($salida, JSON_UNESCAPED_UNICODE);
    exit;
}

$salida['mensaje'] = 'Acción no reconocida.';
header('Content-Type: application/json; charset=utf-8');
echo json_encode($salida, JSON_UNESCAPED_UNICODE);
