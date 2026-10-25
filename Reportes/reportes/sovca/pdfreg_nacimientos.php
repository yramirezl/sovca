<?php
/* ============================================================
   BOVS | Reporte de Nacimientos (optimizado)
   ------------------------------------------------------------
   Compatible con PHP 7.4 y PHP 8.3.

   Filtros:
   - fecha_inicio / fecha_final (también acepta fecha_hasta)
   - cod_bovs + codigo_erete (1 = código BOVS, 2 = N° arete)

   Optimización:
   - Una sola consulta con CTE para todos los nacimientos.
   - IEP calculado en SQL con LAG() por madre.
   - Sin consultas N+1 por animal.
   - Totales y promedios calculados en PHP una sola vez.

   Si se indica un animal, el reporte muestra su ficha de nacimientos.
   Si no, muestra el informe general del fundo.
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

    function __construct()
    {
        $this->pdfreporte();
    }

    function pdfreporte()
    {
        $this->fpdf('l', 'mm', 'legal');
        $this->bd  = new baseClases();
        $this->cab = new Cabecera();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $parametros = base64_decode(getvalue('%$%'));
        $par = $this->parsear_parametros($parametros);

        $this->fecha_desde = $this->normalizar_fecha($par['fecha_inicio']);
        $fecha_final = $par['fecha_final'] !== '' ? $par['fecha_final'] : $par['fecha_hasta'];
        $this->fecha_hasta = $this->normalizar_fecha($fecha_final);
        $this->cod_bovs    = trim($par['cod_bovs']);
        $this->buscar_cod  = intval($par['codigo_erete']);

        if ($this->buscar_cod !== 1 && $this->buscar_cod !== 2) {
            $this->buscar_cod = (stripos($this->cod_bovs, 'SOVCA-') === 0 || stripos($this->cod_bovs, 'BOVS-') === 0) ? 1 : 2;
        }

        $this->mfundos_id = isset($_SESSION['mfundos_id']) ? intval($_SESSION['mfundos_id']) : 0;

        $this->titulo             = '';
        $this->titulo_reporte     = 'INFORME DE NACIMIENTOS';
        $this->subtitulo_reporte  = '';
        $this->nombre_archivo     = 'Reporte_de_Nacimientos.pdf';
        $this->nacimientos        = array();
        $this->total_nacimientos  = 0;
        $this->total_hembras      = 0;
        $this->total_machos       = 0;
        $this->pct_vivos          = '-';
        $this->pct_machos         = '-';
        $this->pct_hembras        = '-';
        $this->peso_promedio      = '-';
        $this->peso_max           = '-';
        $this->peso_min           = '-';
        $this->iep_promedio       = '-';

        $sql_fundo = 'SELECT nombre, cid_rif FROM mfundos WHERE id=' . intval($this->mfundos_id);
        $this->arrp_fundo    = $this->bd->select($sql_fundo);
        $this->nombre_fundo  = isset($this->arrp_fundo[0]['nombre'])  ? $this->arrp_fundo[0]['nombre']  : 'Sin Fundo';
        $this->cid_rif_fundo = isset($this->arrp_fundo[0]['cid_rif']) ? $this->arrp_fundo[0]['cid_rif'] : '';

        $this->animal         = $this->resolver_animal();
        $this->cod_solicitado = ($this->cod_bovs !== '');

        $this->preparar_titulos();

        if ($this->mfundos_id <= 0) {
            $this->arrp = array();
            $this->reg = 0;
            return;
        }

        $this->cargar_nacimientos();
        $this->arrp = $this->nacimientos;
        $this->reg = 0;
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
            'fecha_hasta'  => '',
            'codigo_erete' => ''
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

    function resolver_animal()
    {
        if ($this->cod_bovs === '' || $this->mfundos_id <= 0) return null;

        $rs = null;

        if ($this->buscar_cod === 1) {

            /* Código SOVCA: se prueban las variantes con distinto relleno de
               ceros (SOVCA-005-199 vs SOVCA-05-0199) y se corta al primer
               acierto. */
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

            /* Último recurso: comparar el número final del código. */
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

        if ($this->animal !== null) {
            $this->titulo_reporte = 'FICHA DE NACIMIENTOS';
            $this->nombre_archivo = 'Ficha_de_Nacimientos.pdf';

            $ident = 'Código: ' . $this->animal['codigo'];
            if (trim($this->animal['nombre']) !== '') {
                $ident .= '  |  Nombre: ' . $this->animal['nombre'];
            }
            if (trim($this->animal['nro_arete']) !== '') {
                $ident .= '  |  N° Arete: ' . $this->animal['nro_arete'];
            }
            if ($rango !== '') {
                $ident .= '  |  ' . $rango;
            }
            $this->subtitulo_reporte = $ident;
            return;
        }

        if ($this->cod_solicitado && $this->animal === null) {
            $this->titulo_reporte = 'FICHA DE NACIMIENTOS';
            $this->nombre_archivo = 'Ficha_de_Nacimientos.pdf';
            $this->subtitulo_reporte = 'Código/Arete: ' . $this->cod_bovs . '  |  Sin registros en el fundo';
            return;
        }

        $this->titulo_reporte = 'INFORME DE NACIMIENTOS';
        $this->subtitulo_reporte = ($rango !== '') ? $rango : 'Todos los nacimientos del fundo';
    }

    /* ============================================================
       CARGA DE DATOS
       ============================================================ */
    function cargar_nacimientos()
    {
        if ($this->cod_solicitado && $this->animal === null) {
            $this->calcular_resumen();
            return;
        }

        $mf = intval($this->mfundos_id);
        $filtro_fecha = '';
        if ($this->fecha_desde !== '' && $this->fecha_hasta !== '') {
            $filtro_fecha = " AND b.f_parto::date BETWEEN DATE '" . $this->fecha_desde . "' AND DATE '" . $this->fecha_hasta . "'";
        }

        $filtro_codigo = '';
        if ($this->animal !== null) {
            $filtro_codigo = " AND b.codigo_rgb_id_animal_hembra = '" . $this->sql_texto($this->animal['codigo']) . "'";
        }

        $sql = "
            WITH base AS (
                SELECT n.id,
                       n.codigo_rgb_id_animal_hembra,
                       n.codigo_ternero,
                       n.id_ternero,
                       n.f_parto,
                       n.nro_parto,
                       n.estatus_nacido,
                       n.sexo,
                       n.peso_nacimiento,
                       n.aptitud,
                       n.mtiposervicios_id,
                       (n.f_parto::date - (LAG(n.f_parto) OVER (
                            PARTITION BY n.codigo_rgb_id_animal_hembra
                            ORDER BY n.f_parto, n.id
                       ))::date) AS iep
                  FROM mregnacimientos n
                 WHERE n.mfundos_id = " . $mf . "
                   AND n.status = 1
                   AND n.mestatus_id = 1
            )
            SELECT b.id,
                   b.codigo_rgb_id_animal_hembra AS codigo_madre,
                   COALESCE(a.nro_arete, '') AS arete_madre,
                   COALESCE(a.nombre, '') AS nombre_madre,
                   b.f_parto,
                   b.nro_parto,
                   COALESCE(ts.nombre, '') AS tipo_servicio,
                   COALESCE(t.estatus_nacido, b.estatus_nacido) AS estatus_nacido,
                   COALESCE(t.sexo, b.sexo) AS sexo,
                   COALESCE(NULLIF(t.peso_nacimiento, ''), b.peso_nacimiento, '') AS peso,
                   COALESCE(NULLIF(t.id_ternero, ''), NULLIF(b.id_ternero, ''), b.codigo_ternero, '') AS id_cria,
                   b.iep,
                   COALESCE(t.aptitud, b.aptitud) AS aptitud_id,
                   COALESCE(ap.nombre, '') AS aptitud
              FROM base b
        INNER JOIN manimales a
                ON a.codigo_rgb_id_animal = b.codigo_rgb_id_animal_hembra
               AND a.mfundos_id = " . $mf . "
               AND a.mestatus_id = 1
         LEFT JOIN mtiposervicios ts ON ts.id = b.mtiposervicios_id
         LEFT JOIN mternerosxnacimientos t
                ON t.mregnacimientos_id = b.id
               AND t.status = 1
         LEFT JOIN maptitudes ap ON ap.id = COALESCE(t.aptitud, b.aptitud)
             WHERE 1=1
               " . $filtro_fecha . "
               " . $filtro_codigo . "
             ORDER BY b.codigo_rgb_id_animal_hembra, b.f_parto, COALESCE(t.nro_ternero, 0), b.id
        ";

        $rs = $this->bd->select($sql);
        if ($rs) {
            foreach ($rs as $row) {
                $estatus  = intval($row['estatus_nacido']);
                $sexo     = intval($row['sexo']);
                $peso_txt = trim($row['peso']);
                $peso_num = ($peso_txt !== '' && is_numeric($peso_txt)) ? floatval($peso_txt) : null;
                $iep      = ($row['iep'] !== null && $row['iep'] !== '') ? intval($row['iep']) : null;

                $this->nacimientos[] = array(
                    'codigo_madre'  => $row['codigo_madre'],
                    'arete_madre'   => $row['arete_madre'],
                    'nombre_madre'  => $row['nombre_madre'],
                    'fecha'         => $this->fmt_fecha($row['f_parto']),
                    'nro_parto'     => intval($row['nro_parto']),
                    'tipo_servicio' => $row['tipo_servicio'],
                    'nacio'         => ($estatus === 1) ? 'Vivo' : (($estatus === 2) ? 'Muerto' : '-'),
                    'vivo'          => ($estatus === 1),
                    'sexo_valor'    => $sexo,
                    'sexo'          => ($sexo === 1) ? 'Macho' : (($sexo === 2) ? 'Hembra' : '-'),
                    'peso_num'      => $peso_num,
                    'peso'          => ($peso_num !== null) ? number_format($peso_num, 2, '.', '') : '',
                    'id_cria'       => $row['id_cria'],
                    'iep'           => $iep,
                    'aptitud'       => $row['aptitud']
                );
            }
        }

        $this->calcular_resumen();
    }

    function calcular_resumen()
    {
        $total   = count($this->nacimientos);
        $hembras = 0;
        $machos  = 0;
        $vivos   = 0;
        $pesos   = array();
        $ieps    = array();

        foreach ($this->nacimientos as $n) {
            if ($n['sexo_valor'] === 2) $hembras++;
            if ($n['sexo_valor'] === 1) $machos++;
            if ($n['vivo']) $vivos++;
            if ($n['peso_num'] !== null) $pesos[] = $n['peso_num'];
            if ($n['iep'] !== null) $ieps[] = $n['iep'];
        }

        $this->total_nacimientos = $total;
        $this->total_hembras     = $hembras;
        $this->total_machos      = $machos;
        $this->pct_vivos         = ($total > 0) ? number_format(($vivos / $total) * 100, 1, '.', '') . ' %' : '-';
        $this->pct_machos        = ($total > 0) ? number_format(($machos / $total) * 100, 1, '.', '') . ' %' : '-';
        $this->pct_hembras       = ($total > 0) ? number_format(($hembras / $total) * 100, 1, '.', '') . ' %' : '-';
        $this->peso_promedio     = (count($pesos) > 0) ? number_format(array_sum($pesos) / count($pesos), 2, '.', '') : '-';
        $this->peso_max          = (count($pesos) > 0) ? number_format(max($pesos), 2, '.', '') : '-';
        $this->peso_min          = (count($pesos) > 0) ? number_format(min($pesos), 2, '.', '') : '-';
        $this->iep_promedio      = (count($ieps) > 0) ? number_format(array_sum($ieps) / count($ieps), 0, '.', '') : '-';
    }

    /* ============================================================
       ENCABEZADO
       ============================================================ */
    function getEncabezado($x, $y)
    {
        $this->SetBorder(1);
        $this->SetDrawColor(2, 157, 116);
        $this->SetFillColor(230, 230, 230);
        $this->SetFont('ARIAL', 'B', 10);
        $this->SetXY($x + 0, $y - 20);
        $this->SetWidths(array(330));
        $this->SetAligns(array('C'));
        $this->SetBorder(1);
        $this->SetJump(12);
        $this->RowM(array(''));
        $this->SetXY($x + 60, $y - 15);
        $this->SetWidths(array(280));
        $this->SetAligns(array('C'));

        $fecha = date('d/m/Y');
        $this->SetWidths(array(250, 80));
        $this->SetAligns(array('C', 'R'));
        $this->RowM(array(
            __pdf_txt($this->nombre_fundo . '  ' . $this->cid_rif_fundo),
            'Fecha: ' . $fecha
        ));

        $this->SetXY($x + 5, $y);
        $this->SetJump(6);
        $this->SetWidths(array(330));
        $this->SetAligns(array('C'));
        $this->SetFont('ARIAL', 'B', 9);
        $this->RowM(array(__pdf_txt($this->titulo_reporte)));

        if ($this->subtitulo_reporte !== '') {
            $this->SetXY($x + 5, $this->GetY());
            $this->SetJump(4);
            $this->SetWidths(array(330));
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
        $this->SetFont('ARIAL', 'B', 7);
        $this->SetXY(10, 188);
        $this->SetWidths(array(330));
        $this->SetAligns(array('R'));
        $this->RowM(array(__pdf_txt('Sistema SOVCA (https://global.ruted.net)')));
    }

    function Header()
    {
        $this->cab->poner_cabecera($this, $this->titulo, 'p', '', 'n');
        $y = $this->GetY();
        $x = $this->GetX();

        $this->getEncabezado($x, $y);
        $this->SetWidths(array(330));
        $this->SetAligns(array('L'));
        $this->SetXY(10, 23);
    }

    /* ============================================================
       CUERPO
       ============================================================ */
    function Cuerpo()
    {
        $this->Pie_Pagina();
        $this->SetXY(10, 42);

        $this->filaTitulo('RESUMEN DE NACIMIENTOS', 80);

        $this->tablaCabecera(
            array(55, 55, 55, 55, 55, 55),
            array('C', 'C', 'C', 'C', 'C', 'C'),
            array('Total Nacimientos', 'Total Hembras', 'Total Machos', '% Vivos', '% Machos', '% Hembras')
        );
        $this->tablaFila(
            array(55, 55, 55, 55, 55, 55),
            array('C', 'C', 'C', 'C', 'C', 'C'),
            array(
                $this->total_nacimientos,
                $this->total_hembras,
                $this->total_machos,
                $this->pct_vivos,
                $this->pct_machos,
                $this->pct_hembras
            ),
            array(0, 0, 150),
            array(200, 250, 216)
        );

        $this->tablaCabecera(
            array(82, 83, 83, 82),
            array('C', 'C', 'C', 'C'),
            array('Peso Promedio', 'Peso Máximo', 'Peso Mínimo', 'IEP Promedio (días)')
        );
        $this->tablaFila(
            array(82, 83, 83, 82),
            array('C', 'C', 'C', 'C'),
            array(
                $this->peso_promedio,
                $this->peso_max,
                $this->peso_min,
                $this->iep_promedio
            ),
            array(0, 0, 150),
            array(200, 250, 216)
        );

        if ($this->animal !== null) {
            $this->filaTitulo('MADRE', 60);
            $this->tablaCabecera(
                array(110, 100, 120),
                array('C', 'C', 'C'),
                array('Código SOVCA', 'N° Arete', 'Nombre')
            );
            $this->tablaFila(
                array(110, 100, 120),
                array('C', 'C', 'L'),
                array($this->animal['codigo'], $this->animal['nro_arete'], $this->animal['nombre']),
                array(0, 0, 150),
                array(200, 250, 216)
            );
        }

        $this->filaTitulo('DETALLE DE NACIMIENTOS', 90);
        $this->detalleCabecera();

        foreach ($this->nacimientos as $n) {
            if ($this->saltoPagina(170)) {
                $this->detalleCabecera();
            }

            $this->tablaFila(
                array(42, 30, 45, 22, 14, 35, 18, 18, 16, 35, 16, 39),
                array('C', 'C', 'L', 'C', 'C', 'L', 'C', 'C', 'C', 'C', 'C', 'L'),
                array(
                    $n['codigo_madre'],
                    $n['arete_madre'],
                    $n['nombre_madre'],
                    $n['fecha'],
                    $n['nro_parto'],
                    $n['tipo_servicio'],
                    $n['nacio'],
                    $n['sexo'],
                    $n['peso'],
                    $n['id_cria'],
                    ($n['iep'] !== null ? $n['iep'] : ''),
                    $n['aptitud']
                ),
                array(0, 0, 0),
                array(200, 250, 216),
                5
            );
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
        $this->SetFont('ARIAL', '', 7);
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
            array(42, 30, 45, 22, 14, 35, 18, 18, 16, 35, 16, 39),
            array('C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C'),
            array('Código Madre', 'N° Arete', 'Nombre Madre', 'Fecha', 'Parto Nro.', 'Tipo Servicio', 'Nació', 'Sexo', 'Peso', 'ID Cría', 'IEP (días)', 'Aptitud'),
            5
        );
    }
}
