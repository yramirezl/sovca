<?php
/* ============================================================
   BOVS | Reporte de Consecutivo de Peso
   ------------------------------------------------------------
   Compatible con PHP 7.4 y PHP 8.3.

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

#[\AllowDynamicProperties]
class pdfreporte extends fpdf
{
    var $reg;
    var $p = 0;
    var $dias = 0;

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
        $filtros    = $this->parsear_parametros($parametros);

        $this->fecha_desde = $this->normalizar_fecha($filtros['fecha_inicio']);
        $this->fecha_hasta = $this->normalizar_fecha($filtros['fecha_final']);

        if ($this->fecha_desde !== '' && $this->fecha_hasta !== ''
            && $this->fecha_desde > $this->fecha_hasta) {
            $tmp               = $this->fecha_desde;
            $this->fecha_desde = $this->fecha_hasta;
            $this->fecha_hasta = $tmp;
        }

        /*
         * Código del animal: el botón "Resumen" del listado de pesajes
         * pide uno solo y SIN fechas, así que el rango es opcional.
         */
        $this->cod_bovs = trim($filtros['codigo_bovs']);
        if ($this->cod_bovs === '') $this->cod_bovs = trim($filtros['cod_bovs']);
        if ($this->cod_bovs === '') $this->cod_bovs = trim($filtros['cod_bovs_inicio']);

        /* Lote: se acepta lote_unico (dashboards viejos) o el rango */
        $this->lote_unico = intval($filtros['lote_unico']);
        $this->lote_desde = intval($filtros['lote_inicio']);
        $this->lote_hasta = intval($filtros['lote_final']);

        $this->mfundos_id = isset($_SESSION['mfundos_id']) ? intval($_SESSION['mfundos_id']) : 0;

        $sql_fundo = "SELECT nombre, cid_rif from mfundos where id=" . intval($this->mfundos_id) . ";";
        $this->arrp_fundo    = $this->bd->select($sql_fundo);
        $this->nombre_fundo  = isset($this->arrp_fundo[0]['nombre'])  ? $this->arrp_fundo[0]['nombre']  : 'Sin Fundo';
        $this->cid_rif_fundo = isset($this->arrp_fundo[0]['cid_rif']) ? $this->arrp_fundo[0]['cid_rif'] : '';

        $cadena_lote = "";
        $this->filtro_lote = false;
        $this->cadena_fecha = "";
        $this->cadena_rango_fecha = "";

        /* Rango de fechas: OPCIONAL (el "Resumen" de un animal pide solo el código) */
        if ($this->fecha_desde !== '' && $this->fecha_hasta !== '') {
            $this->cadena_fecha = " and p.fecha_pesaje BETWEEN CAST('" . $this->fecha_desde . "' AS DATE)"
                                . " AND CAST('" . $this->fecha_hasta . "' AS DATE)";
            $this->cadena_rango_fecha = 'Desde el ' . date('d-m-Y', strtotime($this->fecha_desde))
                                      . ' Hasta el ' . date('d-m-Y', strtotime($this->fecha_hasta));
        }

        /* Filtro por código BOVS o N° de arete (un solo animal) */
        $cadena_codigo = "";
        if ($this->cod_bovs !== '') {
            $criterio  = str_replace("'", "''", str_replace(' ', '', $this->cod_bovs));

            if (stripos($criterio, 'SOVCA') === 0 || stripos($criterio, 'BOVS') === 0) {
                $cadena_codigo = " and p.codigo_rgb_id_animal = TRIM('" . $criterio . "') ";
            } else {
                $cadena_codigo = " and TRIM(a.nro_arete) = TRIM('" . $criterio . "') ";
            }
        }

        /* Lote: lote_unico (dashboards viejos) o rango lote_inicio/lote_final */
        if ($this->lote_unico > 0) {
            $this->lote_desde = $this->lote_unico;
            $this->lote_hasta = $this->lote_unico;
        }

        if ($this->lote_desde > 0 || $this->lote_hasta > 0) {
            if ($this->lote_desde <= 0) $this->lote_desde = $this->lote_hasta;
            if ($this->lote_hasta <= 0) $this->lote_hasta = $this->lote_desde;

            if ($this->lote_desde > $this->lote_hasta) {
                $tmp              = $this->lote_desde;
                $this->lote_desde = $this->lote_hasta;
                $this->lote_hasta = $tmp;
            }

            $cadena_lote = " and a.mlotes_id BETWEEN " . intval($this->lote_desde)
                         . " AND " . intval($this->lote_hasta) . " ";
            $this->filtro_lote = true;
            $this->cadena_rango_lote = "AGRUPADO POR LOTES";
        }

        /*
         * Con un código explícito NO se filtra por estado del animal: el
         * usuario pidió ese animal y su historial debe salir completo
         * (igual que el "Resumen" de producción de leche).
         */
        $animal_unico  = ($cadena_codigo !== '' && !$this->filtro_lote);
        $cadena_estado = $animal_unico
            ? ""
            : " and a.mestatus_id = 1 and COALESCE(a.estatus_animal, 1) NOT IN (3,5) ";
        $tipo_join     = $animal_unico ? "LEFT JOIN" : "INNER JOIN";

        $sql_principal = "
            SELECT p.codigo_rgb_id_animal AS codigo_animal,
                   COALESCE(a.nro_arete, '') AS nro_arete,
                   COALESCE(a.nombre, '')    AS nombre_animal
              FROM mconsecutivopesos p
              " . $tipo_join . " manimales a
                ON a.codigo_rgb_id_animal = p.codigo_rgb_id_animal
               AND a.mfundos_id = p.mfundos_id
             WHERE p.mfundos_id = " . intval($this->mfundos_id) . "
               AND p.status = 1
               " . $cadena_estado . "
               " . $cadena_codigo . "
               " . $this->cadena_fecha . "
               " . $cadena_lote . "
             GROUP BY p.codigo_rgb_id_animal, a.nro_arete, a.nombre
             ORDER BY p.codigo_rgb_id_animal ASC;
        ";

        $this->arrp = $this->bd->select($sql_principal);
        $this->reg  = 0;
    }

    /* ============================================================
       PARSEO DE PARÁMETROS Y FECHAS
       ============================================================ */

    function parsear_parametros($parametros)
    {
        $out = array(
            'codigo_bovs'     => '',
            'cod_bovs'        => '',
            'cod_bovs_inicio' => '',
            'cod_bovs_final'  => '',
            'fecha_inicio'    => '',
            'fecha_final'     => '',
            'lote_unico'      => '',
            'lote_inicio'     => '',
            'lote_final'      => ''
        );

        if (!is_string($parametros) || $parametros === '') {
            return $out;
        }

        $valores = explode('&', $parametros);
        foreach ($valores as $cadena) {
            if ($cadena === '' || strpos($cadena, '=') === false) continue;
            $partes = explode('=', $cadena, 2);
            $clave  = trim($partes[0]);
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
            if ($d instanceof DateTime) {
                $errores = DateTime::getLastErrors();
                if (is_array($errores) && ($errores['warning_count'] > 0 || $errores['error_count'] > 0)) {
                    continue;
                }
                return $d->format('Y-m-d');
            }
        }

        return '';
    }

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

        if ($this->cadena_rango_fecha != '') {
            $this->SetXY($x, $y);
            $this->SetJump(6);
            $this->SetWidths(array(190));
            $this->SetAligns(array('C'));
            $this->RowM(array(__pdf_txt('REPORTE DE CONSECUTIVO DE PESO ' . $this->cadena_rango_fecha)));
        } else {
            $this->SetJump(6);
            $this->SetWidths(array(190));
            $this->SetAligns(array('C'));
            $this->RowM(array(__pdf_txt('INFORME DE CONSECUTIVO DE PESO')));
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
        $puntero  = 0;
        $contador = 0;
        $this->Pie_Pagina();

        $this->SetXY(10, 40);

        foreach ($this->arrp as $data) {

            $cod_animal = $data['codigo_animal'];

            if ($this->GetY() > 250) {
                $this->AddPage();
                $this->Pie_Pagina();
                $this->SetXY(10, 35);
            }

            $this->SetBorder(0);

            $this->SetFont('ARIAL', '', '10');
            $this->SetWidths(array(80));
            $this->SetAligns(array('C'));
            $this->SetFillColor(100, 200, 156);
            $this->SetBorder(true);
            $this->SetJump(8);

            $this->RowMFillColor(array(__pdf_txt('DATOS DEL ANIMAL')), true);

            $this->SetTextColor(0, 0, 0);
            $this->SetFont('ARIAL', 'B', '9');
            $this->SetFillColor(100, 200, 116);
            $this->SetWidths(array(40, 35, 100));
            $this->SetAligns(array('C', 'C', 'C'));
            $this->SetBorder(true);

            $this->RowMFillColor(array(
                __pdf_txt('Código SOVCA'),
                __pdf_txt('N° Arete'),
                __pdf_txt('Nombre')
            ), true);

            $this->SetTextColor(0, 0, 150);
            $this->SetFillColor(200, 250, 216);
            $this->SetJump(6);
            $this->SetAligns(array('C', 'C', 'C', 'C'));

            $this->RowMFillColor(array(
                '   ' . __pdf_txt($data['codigo_animal']),
                '   ' . __pdf_txt($data['nro_arete']),
                __pdf_txt($data['nombre_animal'])
            ), true);

            $this->SetFont('ARIAL', '', '10');
            $this->SetWidths(array(80));
            $this->SetAligns(array('C'));
            $this->SetFillColor(200, 220, 216);
            $this->SetBorder(true);
            $this->SetJump(6);

            $sql_detalle = "
                SELECT p.id AS id_consecutivo_peso, p.peso,
                       to_char(p.fecha_pesaje, 'DD-MM-YYYY') AS fecha_pesaje
                FROM mconsecutivopesos p
                WHERE p.codigo_rgb_id_animal = '" . str_replace("'", "''", $cod_animal) . "'
                  AND p.mfundos_id = " . intval($this->mfundos_id) . "
                  AND p.status = 1
                  " . $this->cadena_fecha . "
                ORDER BY p.fecha_pesaje ASC, p.id ASC;
            ";

            $this->arrp_deta = $this->bd->select($sql_detalle);
            $can_reg_det = count($this->arrp_deta);

            if ($can_reg_det > 0) {
                $this->SetTextColor(0, 0, 0);

                $this->SetFont('ARIAL', 'B', '9');
                $this->SetFillColor(100, 200, 156);
                $this->SetWidths(array(40));
                $this->SetAligns(array('C'));
                $this->SetBorder(true);

                $this->RowMFillColor(array(__pdf_txt('Datos Especificos')), true);
                $this->SetTextColor(0, 0, 0);
                $this->SetFont('ARIAL', 'B', '9');
                $this->SetFillColor(100, 200, 116);
                $this->SetWidths(array(40, 50, 30, 25, 30));
                $this->SetAligns(array('C', 'C', 'C', 'C', 'C'));
                $this->SetBorder(true);

                $this->RowMFillColor(array(
                    __pdf_txt('Fecha Pesaje'),
                    __pdf_txt('Peso (Kgs)'),
                    __pdf_txt('Período'),
                    'GdP',
                    'GDdP'
                ), true);

                $fecha_aux     = "";
                $fecha_pesaje  = "";
                $peso_aux      = "";
                $peso_actual   = "";
                $gdp           = "";
                $gddp          = "";
                $acum          = false;
                $gtp           = 0;
                $dias_t        = 0;
                $peso_inicial  = 0;
                $peso_actual   = 0;
                $min_gddp      = 10000;
                $max_gddp      = 0;
                $conta_det     = 0;
                $media_gddp    = 0;
                $gddp_acum     = 0;
                $gddp_n        = 0;

                foreach ($this->arrp_deta as $data_deta) {

                    if ($this->GetY() > 300) {
                        $this->AddPage();
                        $this->Pie_Pagina();
                        $this->SetXY(10, 35);
                    }

                    $this->SetTextColor(0, 0, 0);
                    $this->SetFont('ARIAL', '', '8');
                    $this->SetFillColor(200, 250, 216);

                    $this->SetWidths(array(40, 50, 30, 25, 30));
                    $this->SetAligns(array('C', 'C', 'C', 'C', 'C'));
                    $this->SetBorder(true);

                    $fecha_pesaje = $data_deta['fecha_pesaje'];
                    $peso_actual  = $data_deta['peso'];

                    if ($fecha_aux != "") {
                        $periodo = $this->tiempo_ies($fecha_aux, $fecha_pesaje);
                    } else {
                        $periodo = 0;
                    }

                    /* Primer pesaje: no hay ganancia que calcular */
                    if ($peso_aux != "") {
                        $gdp_num = $peso_actual - $peso_aux;
                        $gdp     = H::FormatoMonto($gdp_num);
                    } else {
                        $gdp_num = 0;
                        $gdp     = "-";
                    }

                    if ($periodo > 0 && $peso_aux != "") {
                        $gddp_num  = $gdp_num / $periodo;
                        $gddp      = H::FormatoMonto($gddp_num, 3);
                        $acum      = true;
                        if ( $gddp_num < $min_gddp ) $min_gddp = $gddp_num;
                        if ( $gddp_num > $max_gddp ) $max_gddp = $gddp_num;
                        $gddp_acum = $gddp_acum + $gddp_num;
                        $gddp_n    = $gddp_n + 1;
                    } else {
                        $gddp = "-";
                    }

                    $this->RowMFillColor(array(
                        $data_deta['fecha_pesaje'],
                        $data_deta['peso'],
                        $periodo,
                        $gdp,
                        $gddp
                    ), true);

                    $fecha_aux = $data_deta['fecha_pesaje'];
                    $peso_aux  = $data_deta['peso'];

                    $gtp     += $gdp_num;
                    $dias_t  += $periodo;
                    $conta_det++;

                    if ($conta_det == 1) {
                        $peso_inicial = $data_deta['peso'];
                    }
                    if ($conta_det == $can_reg_det) {
                        $peso_actual = $data_deta['peso'];
                    }
                }

                if ($acum) {
                    $media_gddp = ( $gddp_n > 0 ) ? ( $gddp_acum / $gddp_n ) : 0;
                    $media_gddp = H::FormatoMonto($media_gddp, 3);
                    $min_gddp   = ( $min_gddp == 10000 ) ? 0 : $min_gddp;

                    $this->SetTextColor(0, 0, 0);
                    $this->SetFont('ARIAL', 'B', '9');
                    $this->SetFillColor(100, 200, 156);
                    $this->SetWidths(array(40));
                    $this->SetAligns(array('C'));
                    $this->RowMFillColor(array(__pdf_txt('Datos Acumulados')), true);

                    $this->SetFillColor(100, 200, 116);
                    $this->SetWidths(array(20, 30, 25, 25, 25, 25, 25));
                    $this->SetAligns(array('C', 'C', 'C', 'C', 'C', 'C', 'C'));
                    $this->SetBorder(true);
                    $this->RowMFillColor(array(
                        'GTP',
                        __pdf_txt('Días Totales'),
                        __pdf_txt('Peso Inicial'),
                        __pdf_txt('Peso Actual'),
                        __pdf_txt('Media GDdP'),
                        __pdf_txt('Min GDdP'),
                        __pdf_txt('Max GDdP')
                    ), true);

                    $this->SetBorder(true);
                    $this->SetTextColor(0, 0, 0);
                    $this->SetFont('ARIAL', '', '8');
                    $this->SetFillColor(200, 250, 216);

                    $this->RowMFillColor(array(
                        H::FormatoMonto($gtp), $dias_t, $peso_inicial, $peso_actual,
                        $media_gddp, H::FormatoMonto($min_gddp, 3), H::FormatoMonto($max_gddp, 3)
                    ), true);

                    $this->SetBorder(true);
                }
            }

            $this->SetX(140);
            $this->SetWidths(array(60));
            $this->SetBorder(true);
            $this->Ln(5);
            $contador++;
        }
    }

    function tiempo_ies($fecha_pesaje, $fecha_aux)
    {
        $fecha_f = $fecha_aux;
        $fecha_i = $fecha_pesaje;

        $dias = (strtotime($fecha_i) - strtotime($fecha_f)) / 86400;
        $dias = abs($dias);
        $dias = floor($dias);
        $dias = (int)$dias;

        $this->dias = $dias;
        return $this->dias;
    }
}