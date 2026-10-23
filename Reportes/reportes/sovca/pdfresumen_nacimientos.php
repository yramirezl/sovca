<?php
date_default_timezone_set('America/Caracas');

if (!function_exists('get_magic_quotes_runtime')) {
    function get_magic_quotes_runtime()
    {
        return false;
    }
}

require_once('../../lib/general/fpdf/fpdf.php');
include_once('../../lib/utiles.php');
chdir(dirname(__DIR__, 3) . '/config');
require_once('../../../config/conexion.php');
require_once('../../../models/Vmregnacimiento.php');

class pdfreporte extends FPDF
{
    var $arrp = array();
    var $detalle = array();
    var $resumen = array();
    var $madre = null;
    var $mfundos_id = 0;
    var $nombre_fundo = '';
    var $cid_rif_fundo = '';
    var $fecha_desde = '';
    var $fecha_hasta = '';
    var $codigo_madre = '';
    var $modo_madre = false;

    function __construct()
    {
        $this->pdfreporte();
    }

    function pdfreporte()
    {
        $this->FPDF('L', 'mm', 'Legal');

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->mfundos_id = isset($_SESSION['mfundos_id']) ? intval($_SESSION['mfundos_id']) : 0;
        $this->leerFiltros();
        if ($this->mfundos_id <= 0) return;

        $fundo = $this->consultar('SELECT nombre, cid_rif FROM mfundos WHERE id=' . $this->mfundos_id);
        if ($fundo) {
            $this->nombre_fundo = $fundo[0]['nombre'];
            $this->cid_rif_fundo = $fundo[0]['cid_rif'];
        }
        $this->cargarReporte();
    }

    function leerFiltros()
    {
        $parametros = array();
        $codificado = getvalue('%$%');
        if ($codificado) parse_str(base64_decode($codificado), $parametros);

        $this->fecha_desde = $this->fechaValida(isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : (isset($parametros['fecha_inicio']) ? $parametros['fecha_inicio'] : ''));
        $this->fecha_hasta = $this->fechaValida(isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : (isset($parametros['fecha_hasta']) ? $parametros['fecha_hasta'] : ''));
        $this->codigo_madre = trim(isset($_GET['cod_bovs']) ? $_GET['cod_bovs'] : (isset($parametros['cod_bovs']) ? $parametros['cod_bovs'] : ''));
        $buscar_por_arete = isset($_GET['codigo_erete']) ? $_GET['codigo_erete'] : (isset($parametros['codigo_erete']) ? $parametros['codigo_erete'] : '');
        if ($this->codigo_madre !== '' && strval($buscar_por_arete) === '2') $this->codigo_madre = $this->buscarCodigoPorArete($this->codigo_madre);
        $this->modo_madre = $this->codigo_madre !== '';
    }

    function fechaValida($fecha)
    {
        $fecha = trim($fecha);
        foreach (array('Y-m-d', 'd-m-Y') as $formato) {
            $date = DateTime::createFromFormat($formato, $fecha);
            if ($date && $date->format($formato) === $fecha) return $date->format('Y-m-d');
        }
        return '';
    }

    function buscarCodigoPorArete($arete)
    {
        $arete = str_replace("'", "''", $arete);
        $resultado = $this->consultar("SELECT codigo_rgb_id_animal FROM manimales WHERE mfundos_id=" . $this->mfundos_id . " AND nro_arete='" . $arete . "' LIMIT 1");
        return $resultado ? $resultado[0]['codigo_rgb_id_animal'] : '';
    }

    function consultar($sql)
    {
        $registros = Vmregnacimiento::find_by_sql($sql);
        $resultado = array();
        foreach ($registros as $registro) {
            $resultado[] = $registro->attributes();
        }
        return $resultado;
    }

    function cargarReporte()
    {
        $where = ' WHERE n.mfundos_id=' . $this->mfundos_id . ' AND n.mestatus_id=1 ';
        if ($this->modo_madre) $where .= " AND n.codigo_rgb_id_animal_hembra='" . str_replace("'", "''", $this->codigo_madre) . "' ";
        $sql = "SELECT n.codigo_rgb_id_animal_hembra, n.id_ternero, n.codigo_ternero, n.f_parto, n.nro_parto,
                       n.estatus_nacido, n.sexo, n.peso_nacimiento, n.aptitud, n.mtiposervicios_id,
                       a.nro_arete, a.nombre AS nombre_madre, COALESCE(ts.nombre, '') AS tipo_servicio
                FROM Vmregnacimientos n
                LEFT JOIN manimales a ON a.codigo_rgb_id_animal=n.codigo_rgb_id_animal_hembra AND a.mfundos_id=n.mfundos_id
                LEFT JOIN mtiposervicios ts ON ts.id=n.mtiposervicios_id " . $where . ' ORDER BY n.codigo_rgb_id_animal_hembra, n.f_parto';
        $historial = $this->consultar($sql);
        $ultima_fecha = array();
        foreach ($historial as $fila) {
            $fila['iep'] = isset($ultima_fecha[$fila['codigo_rgb_id_animal_hembra']]) ? $this->diasEntre($fila['f_parto'], $ultima_fecha[$fila['codigo_rgb_id_animal_hembra']]) : null;
            $ultima_fecha[$fila['codigo_rgb_id_animal_hembra']] = $fila['f_parto'];
            if (($this->fecha_desde && $fila['f_parto'] < $this->fecha_desde) || ($this->fecha_hasta && $fila['f_parto'] > $this->fecha_hasta)) continue;
            $this->detalle[] = $fila;
        }
        $this->arrp = $this->detalle;
        if ($this->modo_madre && $historial) $this->madre = $historial[0];
        $this->calcularResumen();
    }

    function diasEntre($reciente, $anterior)
    {
        return (!$reciente || !$anterior) ? null : floor((strtotime($reciente) - strtotime($anterior)) / 86400);
    }

    function calcularResumen()
    {
        $hembras = 0; $machos = 0; $vivos = 0; $pesos = array(); $ieps = array();
        foreach ($this->detalle as $fila) {
            if (intval($fila['sexo']) === 2) $hembras++;
            if (intval($fila['sexo']) === 1) $machos++;
            if (intval($fila['estatus_nacido']) === 1) $vivos++;
            if ($fila['peso_nacimiento'] !== '' && $fila['peso_nacimiento'] !== null) $pesos[] = floatval($fila['peso_nacimiento']);
            if ($fila['iep'] !== null) $ieps[] = $fila['iep'];
        }
        $this->resumen = array('total' => count($this->detalle), 'hembras' => $hembras, 'machos' => $machos, 'vivos' => $vivos,
            'peso_promedio' => $pesos ? array_sum($pesos) / count($pesos) : null, 'peso_maximo' => $pesos ? max($pesos) : null,
            'peso_minimo' => $pesos ? min($pesos) : null, 'iep_promedio' => $ieps ? round(array_sum($ieps) / count($ieps)) : null);
    }

    function Header()
    {
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 6, utf8_decode($this->nombre_fundo . ($this->cid_rif_fundo ? '  ' . $this->cid_rif_fundo : '')), 0, 1, 'C');
        $titulo = $this->modo_madre ? 'FICHA DE NACIMIENTOS' : 'INFORME DE NACIMIENTOS';
        if ($this->fecha_desde || $this->fecha_hasta) $titulo .= ' - ' . ($this->fecha_desde ? 'Desde ' . $this->fecha_desde : '') . ($this->fecha_hasta ? ' Hasta ' . $this->fecha_hasta : '');
        $this->Cell(0, 6, utf8_decode($titulo), 0, 1, 'C');
        $this->SetFont('Arial', '', 8);
        $this->Cell(0, 5, 'Fecha: ' . date('d/m/Y'), 0, 1, 'R');
        $this->Ln(2);
    }

    function Footer()
    {
        $this->SetY(-12);
        $this->SetFont('Arial', '', 7);
        $this->Cell(0, 5, utf8_decode('Sistema SOVCA'), 0, 0, 'L');
        $this->Cell(0, 5, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'R');
    }

    function encabezadoTabla($titulos, $anchos)
    {
        $this->SetFont('Arial', 'B', 7); $this->SetFillColor(100, 200, 116);
        foreach ($titulos as $indice => $titulo) $this->Cell($anchos[$indice], 6, utf8_decode($titulo), 1, 0, 'C', true);
        $this->Ln(); $this->SetFont('Arial', '', 7);
    }

    function filaTabla($valores, $anchos)
    {
        foreach ($valores as $indice => $valor) $this->Cell($anchos[$indice], 5, utf8_decode(strval($valor)), 1, 0, 'C');
        $this->Ln();
    }

    function porcentaje($cantidad)
    {
        return $this->resumen['total'] ? number_format(($cantidad / $this->resumen['total']) * 100, 1) . '%' : '-';
    }

    function valorNumero($valor, $decimales = 1)
    {
        return $valor === null ? '-' : number_format($valor, $decimales);
    }

    function Cuerpo()
    {
        if ($this->modo_madre) $this->dibujarFichaMadre(); else $this->dibujarResumenGeneral();
        $this->dibujarDetalle();
    }

    function dibujarFichaMadre()
    {
        $madre = $this->madre ? $this->madre : array('codigo_rgb_id_animal_hembra' => $this->codigo_madre, 'nro_arete' => '-', 'nombre_madre' => '-');
        $anchos = array(55, 30, 55, 35, 30, 30, 30);
        $this->encabezadoTabla(array('Codigo SOVCA', 'Nro. arete', 'Nombre', 'Total nacimientos', '% vivos', '% hembras', '% machos'), $anchos);
        $this->filaTabla(array($madre['codigo_rgb_id_animal_hembra'], $madre['nro_arete'], $madre['nombre_madre'], $this->resumen['total'], $this->porcentaje($this->resumen['vivos']), $this->porcentaje($this->resumen['hembras']), $this->porcentaje($this->resumen['machos'])), $anchos);
        $this->Ln(3); $anchos = array(55, 55, 55, 55);
        $this->encabezadoTabla(array('Peso promedio al nacer', 'Peso maximo al nacer', 'Peso minimo al nacer', 'IEP promedio (dias)'), $anchos);
        $this->filaTabla(array($this->valorNumero($this->resumen['peso_promedio']), $this->valorNumero($this->resumen['peso_maximo']), $this->valorNumero($this->resumen['peso_minimo']), $this->valorNumero($this->resumen['iep_promedio'], 0)), $anchos);
        $this->Ln(3);
    }

    function dibujarResumenGeneral()
    {
        $anchos = array(27, 25, 25, 20, 20, 20, 27, 27, 27, 30);
        $this->encabezadoTabla(array('Total nacimientos', 'Total hembras', 'Total machos', '% vivos', '% machos', '% hembras', 'Peso promedio', 'P. max al nacer', 'P. min al nacer', 'IEP promedio (dias)'), $anchos);
        $this->filaTabla(array($this->resumen['total'], $this->resumen['hembras'], $this->resumen['machos'], $this->porcentaje($this->resumen['vivos']), $this->porcentaje($this->resumen['machos']), $this->porcentaje($this->resumen['hembras']), $this->valorNumero($this->resumen['peso_promedio']), $this->valorNumero($this->resumen['peso_maximo']), $this->valorNumero($this->resumen['peso_minimo']), $this->valorNumero($this->resumen['iep_promedio'], 0)), $anchos);
        $this->Ln(3);
    }

    function dibujarDetalle()
    {
        $titulos = $this->modo_madre ? array('Fecha', 'Parto Nro.', 'Tipo de servicio', 'Nacio', 'Sexo', 'Peso al nacer', 'ID', 'Aptitud', 'IEP (dias)') : array('Codigo SOVCA', 'Nro. arete madre', 'Fecha', 'Parto Nro.', 'Tipo de servicio', 'Nacio', 'Sexo', 'Peso al nacer', 'ID', 'IEP (dias)');
        $anchos = $this->modo_madre ? array(25, 20, 42, 20, 20, 28, 28, 30, 22) : array(38, 28, 24, 20, 35, 18, 18, 25, 28, 20);
        $this->encabezadoTabla($titulos, $anchos);
        foreach ($this->detalle as $fila) {
            if ($this->GetY() > 190) { $this->AddPage(); $this->encabezadoTabla($titulos, $anchos); }
            $nacio = intval($fila['estatus_nacido']) === 1 ? 'Vivo' : (intval($fila['estatus_nacido']) === 2 ? 'Muerto' : '-');
            $sexo = intval($fila['sexo']) === 1 ? 'Macho' : (intval($fila['sexo']) === 2 ? 'Hembra' : '-');
            $valores = array(date('d/m/Y', strtotime($fila['f_parto'])), $fila['nro_parto'], $fila['tipo_servicio'] ?: '-', $nacio, $sexo, $fila['peso_nacimiento'] !== '' ? $fila['peso_nacimiento'] : '-', $fila['id_ternero'] ?: $fila['codigo_ternero'], $fila['aptitud'] ?: '-', $fila['iep'] === null ? '-' : $fila['iep']);
            if (!$this->modo_madre) {
                array_unshift($valores, $fila['codigo_rgb_id_animal_hembra'], $fila['nro_arete']);
                array_splice($valores, 9, 1);
            }
            $this->filaTabla($valores, $anchos);
        }
        if (!$this->detalle) $this->Cell(array_sum($anchos), 6, utf8_decode('Sin registros para los filtros seleccionados.'), 1, 1, 'C');
    }
}