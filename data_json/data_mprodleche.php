<?php
/* ============================================================
   SOVCA | Producción de Leche — API del listado por animal
   ------------------------------------------------------------
   action=lista   (por defecto)
     ?pagina=1&tamano=25&q=texto
     -> animales paginados por "última producción" DESC, con sus
        totales. No incluye el detalle (se pide al desplegar).

   action=detalle
     ?codigo=SOVCA-059-01&pagina=1&tamano=15
     -> registros paginados del animal (fecha DESC) + totales.
        Cada registro trae "editable" (últimos 30 días).

   La paginación es del lado del servidor: un productor puede tener
   cientos o miles de vacas y/o decenas de miles de ordeños.
   ============================================================ */

/* La sesión se lee ANTES de cargar el modelo: config/conexion.php
   inicia y cierra la sesión al inicializar ActiveRecord. */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$mfundos_id = isset($_SESSION['mfundos_id']) ? intval($_SESSION['mfundos_id']) : 0;
$id_sesion  = isset($_SESSION['idsessionx']) ? $_SESSION['idsessionx'] : '';

require_once '../models/Mprolechera.php';
date_default_timezone_set('America/Caracas');

if (!defined('PL_DIAS_EDICION')) {
    define('PL_DIAS_EDICION', 30);
}

/* ------------------------------------------------------------
   Utilidades
   ------------------------------------------------------------ */

function pl_fecha_dmy($iso)
{
    $iso = trim((string)$iso);
    if ($iso === '') return '';

    $d = DateTime::createFromFormat('Y-m-d', substr($iso, 0, 10));

    return ($d instanceof DateTime) ? $d->format('d-m-Y') : $iso;
}

function pl_sin_acentos($texto)
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
function pl_like($texto)
{
    $texto = pl_sin_acentos($texto);
    $texto = str_replace("'", "''", $texto);
    $texto = str_replace('\\', '\\\\', $texto);
    $texto = str_replace('%', '\\%', $texto);
    $texto = str_replace('_', '\\_', $texto);

    return $texto;
}

function pl_url_resumen($codigo)
{
    $params = 'cod_bovs_inicio=' . $codigo . '&cod_bovs_final=' . $codigo;

    return '../../Reportes/reportes/sovca/rpl.php?%$%=' . rawurlencode(base64_encode($params));
}

function pl_fecha_de($valor)
{
    if ($valor instanceof DateTime) {
        return $valor->format('Y-m-d');
    }

    return substr(trim((string)$valor), 0, 10);
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
    'dias_edicion'   => PL_DIAS_EDICION,
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
        $q_sql = pl_like($q);

        $where .= " AND (
               translate(lower(p.codigo_rgb_id_animal_hembra), 'áéíóúüñ', 'aeiouun') LIKE '%" . $q_sql . "%'
            OR translate(lower(COALESCE(a.nombre, '')), 'áéíóúüñ', 'aeiouun') LIKE '%" . $q_sql . "%'
            OR translate(lower(COALESCE(a.nro_arete, '')), 'áéíóúüñ', 'aeiouun') LIKE '%" . $q_sql . "%'
            OR translate(lower(COALESCE(l.nombre, '')), 'áéíóúüñ', 'aeiouun') LIKE '%" . $q_sql . "%'
        )";
    }

    $from = "FROM mprolecheras p
             LEFT JOIN manimales a
                    ON a.codigo_rgb_id_animal = p.codigo_rgb_id_animal_hembra
                   AND a.mfundos_id = p.mfundos_id
             LEFT JOIN mlotes l ON l.id = a.mlotes_id
             WHERE " . $where;

    /* Totales (animales y ordeños) que cumplen el filtro */
    $sql_tot = "SELECT COUNT(*) AS animales,
                       COALESCE(SUM(reg), 0) AS registros
                  FROM (SELECT p.codigo_rgb_id_animal_hembra AS codigo,
                               COUNT(*) AS reg
                          " . $from . "
                         GROUP BY p.codigo_rgb_id_animal_hembra) x;";

    $tot = Mprolechera::find_by_sql($sql_tot);

    $total_animales  = (is_array($tot) && isset($tot[0]->animales)) ? intval($tot[0]->animales) : 0;
    $total_registros = (is_array($tot) && isset($tot[0]->registros)) ? intval($tot[0]->registros) : 0;
    $total_paginas   = ($tamano > 0) ? (int)ceil($total_animales / $tamano) : 0;

    if ($total_paginas > 0 && $pagina > $total_paginas) {
        $pagina = $total_paginas;
    }

    $offset = ($pagina - 1) * $tamano;

    $sql = "SELECT p.codigo_rgb_id_animal_hembra AS codigo,
                   COALESCE(MAX(a.nombre), '')    AS nombre,
                   COALESCE(MAX(a.nro_arete), '') AS nro_arete,
                   COALESCE(MAX(l.nombre), '')    AS lote,
                   MAX(p.fecha_produccion)        AS ultima,
                   COUNT(DISTINCT p.fecha_produccion::date) AS dias,
                   COUNT(*)                       AS registros,
                   COALESCE(SUM(p.cantidad_leche_m), 0) AS suma_m,
                   COALESCE(SUM(p.cantidad_leche_t), 0) AS suma_t,
                   COALESCE(SUM(p.cantidad_leche_m + p.cantidad_leche_t), 0) AS total
              " . $from . "
          GROUP BY p.codigo_rgb_id_animal_hembra
          ORDER BY MAX(p.fecha_produccion) DESC, p.codigo_rgb_id_animal_hembra
             LIMIT " . $tamano . " OFFSET " . $offset . ";";

    $data = Mprolechera::find_by_sql($sql);

    foreach ($data as $rs) {
        $codigo = trim((string)$rs->codigo);
        if ($codigo === '') continue;

        $dias      = intval($rs->dias);
        $registros = intval($rs->registros);
        $total     = floatval($rs->total);
        $ultima    = pl_fecha_de($rs->ultima);

        $salida['animales'][] = array(
            'codigo'       => $codigo,
            'nombre'       => trim((string)$rs->nombre),
            'nro_arete'    => trim((string)$rs->nro_arete),
            'lote'         => trim((string)$rs->lote),
            'ultima'       => $ultima,
            'ultima_txt'   => pl_fecha_dmy($ultima),
            'dias'         => $dias,
            'registros'    => $registros,
            'suma_m'       => round(floatval($rs->suma_m), 2),
            'suma_t'       => round(floatval($rs->suma_t), 2),
            'total'        => round($total, 2),
            'promedio_dia' => $dias > 0 ? round($total / $dias, 2) : 0,
            'url_resumen'  => pl_url_resumen($codigo)
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
              AND codigo_rgb_id_animal_hembra = '" . $codigo . "'";

    $sql_tot = "SELECT COUNT(*) AS registros,
                       COUNT(DISTINCT fecha_produccion::date) AS dias,
                       COALESCE(SUM(cantidad_leche_m), 0) AS suma_m,
                       COALESCE(SUM(cantidad_leche_t), 0) AS suma_t,
                       COALESCE(SUM(cantidad_leche_m + cantidad_leche_t), 0) AS total
                  FROM mprolecheras
                 WHERE " . $where . ";";

    $tot = Mprolechera::find_by_sql($sql_tot);

    $registros_tot = (is_array($tot) && isset($tot[0]->registros)) ? intval($tot[0]->registros) : 0;
    $dias          = (is_array($tot) && isset($tot[0]->dias)) ? intval($tot[0]->dias) : 0;
    $total_kg      = (is_array($tot) && isset($tot[0]->total)) ? floatval($tot[0]->total) : 0;
    $total_paginas = ($tamano > 0) ? (int)ceil($registros_tot / $tamano) : 0;

    if ($total_paginas > 0 && $pagina > $total_paginas) {
        $pagina = $total_paginas;
    }

    $offset = ($pagina - 1) * $tamano;
    $limite = date('Y-m-d', strtotime('-' . PL_DIAS_EDICION . ' days'));

    $sql = "SELECT id, fecha_produccion, cantidad_leche_m, cantidad_leche_t
              FROM mprolecheras
             WHERE " . $where . "
          ORDER BY fecha_produccion DESC, id DESC
             LIMIT " . $tamano . " OFFSET " . $offset . ";";

    $data = Mprolechera::find_by_sql($sql);

    foreach ($data as $rs) {
        $fecha = pl_fecha_de($rs->fecha_produccion);
        $m     = floatval($rs->cantidad_leche_m);
        $t     = floatval($rs->cantidad_leche_t);

        $salida['registros'][] = array(
            'id'        => intval($rs->id),
            'fecha'     => $fecha,
            'fecha_txt' => pl_fecha_dmy($fecha),
            'm'         => $m,
            't'         => $t,
            'total'     => $m + $t,
            'editable'  => ($fecha !== '' && $fecha >= $limite)
        );
    }

    $salida['ok']              = true;
    $salida['codigo']          = str_replace("''", "'", $codigo);
    $salida['pagina']          = $pagina;
    $salida['total_paginas']   = $total_paginas;
    $salida['total_registros'] = $registros_tot;
    $salida['dias']            = $dias;
    $salida['total_kg']        = round($total_kg, 2);
    $salida['promedio_dia']    = $dias > 0 ? round($total_kg / $dias, 2) : 0;

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($salida, JSON_UNESCAPED_UNICODE);
    exit;
}

$salida['mensaje'] = 'Acción no reconocida.';
header('Content-Type: application/json; charset=utf-8');
echo json_encode($salida, JSON_UNESCAPED_UNICODE);
