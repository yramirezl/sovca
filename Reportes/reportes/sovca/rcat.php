<?php
/* ============================================================
   SOVCA | Reporte de Animales Activos por Categoría
   ------------------------------------------------------------
   Compatible con PHP 7.4 y PHP 8.3.

   Notas técnicas:
   - Constructor dual (__construct + pdfreporte).
   - Polyfills get_magic_quotes_runtime() / set_magic_quotes_runtime().
   - Helper __pdf_txt() para convertir UTF-8 → ISO-8859-1.
   - FPDF legacy: Output($name, $dest) — nombre PRIMERO.
   - $this->titulo = '' para no duplicar el título.
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

require_once("../../lib/general/fpdf/fpdf.php");
require_once("../../lib/bd/basedatosAdoIbase.php");
require_once("../../lib/general/cabecera.php");
require_once("../../lib/general/Herramientas.class.php");
include_once("../../lib/utiles.php");
include_once("../../lib/funciones.php");

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
        $this->fpdf("p", "mm", "legal");
        $this->bd  = new baseClases();
        $this->cab = new Cabecera();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->mfundos_id  = isset($_SESSION['mfundos_id']) ? $_SESSION['mfundos_id'] : 0;
        $this->t_ganaderia = isset($_SESSION['tipo_ganaderia']) ? $_SESSION['tipo_ganaderia'] : 0;

        $parametros = base64_decode(getvalue('%$%'));
        $valores    = explode("=", $parametros);
        $cad0       = $valores[0];

        $this->categoria_id = $cad0;

        // Título vacío: getEncabezado() ya imprime el subtítulo con la categoría.
        $this->titulo = '';

        // Datos del fundo
        $sql_fundo = "SELECT nombre, cid_rif from mfundos where id=" . intval($this->mfundos_id) . ";";
        $this->arrp_fundo    = $this->bd->select($sql_fundo);
        $this->nombre_fundo  = isset($this->arrp_fundo[0]['nombre']) ? $this->arrp_fundo[0]['nombre'] : 'Sin Fundo';
        $this->cid_rif_fundo = isset($this->arrp_fundo[0]['cid_rif']) ? $this->arrp_fundo[0]['cid_rif'] : '';

        // Datos de la categoría
        $sql_cat = "SELECT nombre, sexo, edadminima, edadmaxima from mcategorias where id=" . intval($this->categoria_id) . ";";
        $this->arrp_cat         = $this->bd->select($sql_cat);
        $this->nombre_categoria = isset($this->arrp_cat[0]['nombre'])
            ? (function_exists('mb_strtoupper') ? mb_strtoupper($this->arrp_cat[0]['nombre'], 'UTF-8') : strtoupper($this->arrp_cat[0]['nombre']))
            : 'SIN CATEGORIA';
        $this->sexo_categoria   = isset($this->arrp_cat[0]['sexo']) ? $this->arrp_cat[0]['sexo'] : 0;
        $this->edadminima_cat   = isset($this->arrp_cat[0]['edadminima']) ? $this->arrp_cat[0]['edadminima'] : 0;
        $this->edadmaxima_cat   = isset($this->arrp_cat[0]['edadmaxima']) ? $this->arrp_cat[0]['edadmaxima'] : 999;

        // Filtro por sexo (1=HEMBRA, 2=MACHO, 0=AMBOS)
        $filtro_sexo = '';
        if (intval($this->sexo_categoria) === 1 || intval($this->sexo_categoria) === 2) {
            $filtro_sexo = " AND a.sexo = " . intval($this->sexo_categoria) . " ";
        }

        $sql_principal = "
            SELECT a.codigo_animal, a.id_animal, a.nombre_animal, a.nombre_lote,
                   a.estatus_animal, a.categoria_ingreso, a.categoria_futura,
                   a.aptitud, a.raza, a.sexo, a.sexo_animal,
                   to_char(a.f_nacimiento, 'DD-MM-YYYY') as f_nacimiento,
                   a.peso_nacimiento, a.nro_chapeta, a.anios, a.meses, a.dias
            FROM v_list_animales_x_lotes a
            WHERE a.mfundos_id = " . intval($this->mfundos_id) . "
              AND a.estatus_animal IS NOT NULL
              " . $filtro_sexo . "
              AND (COALESCE(a.anios, 0) * 12 + COALESCE(a.meses, 0)) >= " . intval($this->edadminima_cat) . "
              AND (COALESCE(a.anios, 0) * 12 + COALESCE(a.meses, 0)) <  " . intval($this->edadmaxima_cat) . "
            ORDER BY a.codigo_animal ASC;
        ";

        $this->arrp = $this->bd->select($sql_principal);
        $this->reg  = 0;
    }

    function getEncabezado($x, $y)
    {
        $this->SetFillColor(230, 230, 230);
        $this->SetFont("ARIAL", "B", 10);
        $this->SetXY($x + 0, $y - 20);
        $this->SetWidths(array(190));
        $this->SetAligns(array('C'));
        $this->SetBorder(1);
        $this->SetJump(12);
        $this->RowM(array(''));
        $this->SetXY($x + 60, $y - 15);
        $this->SetWidths(array(160));
        $this->SetAligns(array('C'));

        $fecha = date("d/m/Y");

        $this->SetWidths(array(90, 40));
        $this->SetAligns(array('C', 'R'));

        if ($this->nombre_fundo != $this->cid_rif_fundo) {
            $this->datos_fundo = $this->nombre_fundo . '  ' . $this->cid_rif_fundo;
        } else {
            $this->datos_fundo = $this->nombre_fundo;
        }
        $this->RowM(array(__pdf_txt($this->datos_fundo), 'Fecha: ' . $fecha));

        $this->SetXY($x + 35, $y - 5);
        $this->SetJump(6);
        $this->SetWidths(array(120));
        $this->RowM(array(__pdf_txt('Listado de Animales en la Categoría : ' . $this->nombre_categoria)));
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

    function Footer()
    {
        $this->y2 = 257;
        $this->SetFillColor(230, 230, 230);
        $this->SetFont("ARIAL", "B", "8");
    }

    function RotatedText($x, $y, $txt, $angle)
    {
        $this->Rotate($angle, $x, $y);
        $this->Text($x, $y, $txt);
        $this->Rotate(0);
    }

    function Header()
    {
        $this->cab->poner_cabecera($this, $this->titulo, "p", "", "n");
        $y = $this->GetY();
        $x = $this->GetX();
        $this->getEncabezado($x, $y);
        $this->SetWidths(array(60));
        $this->SetAligns(array('L'));
        $this->SetXY(10, 23);
    }

    function Cuerpo()
    {
        $contador = 0;

        $this->Pie_Pagina();
        $this->SetXY(10, 35);

        foreach ($this->arrp as $data) {

            if ($this->GetY() > 290) {
                $this->AddPage();
                $this->Pie_Pagina();
                $this->SetXY(10, 35);
            }

            $cat_actual = '';
            if (($data['f_nacimiento'] != '') && ($data['sexo'] != '')) {
                $sexo = $data['sexo'];
                $cat_actual = $this->Cat_Actual($sexo, $data['f_nacimiento'], $this->mfundos_id, $this->t_ganaderia);
            }

            $this->SetBorder(0);
            $this->SetFillTable(0);
            $this->SetFont('ARIAL', '', '10');
            $this->SetTextColor(0, 0, 0);

            $this->SetWidths(array(125));
            $this->SetAligns(array('C'));
            $this->SetFillColor(200, 250, 216);
            $this->SetBorder(true);
            $this->SetJump(8);
            $this->RowMFillColor(array(__pdf_txt('DATOS DEL ANIMAL')), true);

            $this->SetWidths(array(60, 30));
            $this->SetAligns(array('L', 'L'));
            $this->SetJump(5);
            $this->SetBorder(1);
            $this->SetBorder(true);

            $this->SetTextColor(0, 0, 0);

            $nro_arete = ($data['nro_chapeta'] != "") ? $data['nro_chapeta'] : 'No Indicado';

            $this->SetWidths(array(30, 25, 70, 25, 40));
            $this->SetAligns(array('C', 'C', 'C', 'C', 'C'));

            $this->SetFont('ARIAL', 'B', '9');
            $this->SetFillColor(100, 200, 116);
            $this->SetBorder(true);
            $this->RowMFillColor(array(__pdf_txt('CÓDIGO'), __pdf_txt('N° ARETE'), 'NOMBRE', 'SEXO', 'ESTATUS'), true);
            $this->SetAligns(array('C', 'C', 'C', 'C'));
            $this->SetTextColor(50, 50, 150);
            $this->SetFillColor(200, 250, 216);
            $this->RowMFillColor(array(
                __pdf_txt($data['codigo_animal']),
                __pdf_txt($nro_arete),
                __pdf_txt($data['nombre_animal']),
                __pdf_txt($data['sexo_animal']),
                __pdf_txt($data['estatus_animal'])
            ), true);

            $this->SetTextColor(0, 0, 0);
            $this->SetFont('ARIAL', 'B', '9');
            $this->SetFillColor(100, 200, 116);
            $this->SetBorder(true);

            $this->SetWidths(array(40, 40, 40, 70));
            $this->SetAligns(array('C', 'C', 'C', 'C'));
            $this->SetJump(5);
            $this->SetBorder(true);

            $this->SetTextColor(0, 0, 0);
            $this->SetFillColor(100, 200, 116);
            $this->RowMFillColor(array(__pdf_txt('F. NACIMIENTO'), __pdf_txt('EDAD'), __pdf_txt('APTITUD'), __pdf_txt('RAZA')), true);

            $this->SetTextColor(0, 0, 150);

            $edad        = "";
            $f_nac       = "";
            $cat_futura  = "";
            $cat_ingreso = "";

            $aptitud     = ($data['aptitud'] != "") ? $data['aptitud'] : 'No Definida';
            $cat_ingreso = ($data['categoria_ingreso'] != "") ? $data['categoria_ingreso'] : 'No Definida';
            $cat_futura  = ($data['categoria_futura'] != "") ? $data['categoria_futura'] : 'No Definida';

            if ($data['f_nacimiento'] != "") {
                $f_nac = $data['f_nacimiento'];
                if ($data['anios'] > 0) {
                    if ($data['meses'] > 0) {
                        $edad = ($data['meses'] > 1)
                            ? $data['anios'] . ' Años y ' . $data['meses'] . ' Meses'
                            : $data['anios'] . ' Años y ' . $data['meses'] . ' Mes';
                    } else {
                        $edad = $data['anios'] . ' Años';
                    }
                } else {
                    $edad = $data['meses'] . ' Meses';
                }
            } else {
                $f_nac = 'No Definida';
                $edad  = 'No Definida';
            }

            $this->SetBorder(true);
            $this->SetJump(5);
            $this->SetAligns(array('C', 'C', 'C', 'C'));
            $this->SetTextColor(50, 50, 150);
            $this->SetFillColor(200, 250, 216);
            $this->RowMFillColor(array(
                __pdf_txt($f_nac),
                __pdf_txt($edad),
                __pdf_txt($aptitud),
                __pdf_txt($data['raza'])
            ), true);

            $this->SetAligns(array('C', 'C', 'C'));
            $this->SetWidths(array(63, 63, 64));
            $this->SetBorder(true);
            $this->SetJump(5);

            $this->SetTextColor(0, 0, 0);
            $this->SetFillColor(100, 200, 116);
            $this->RowMFillColor(array(
                __pdf_txt('CATEGORIA INGRESO'),
                __pdf_txt('CATEGORIA ACTUAL'),
                __pdf_txt('CATEGORIA FUTURA')
            ), true);

            $this->SetTextColor(50, 50, 150);
            $this->SetFillColor(200, 250, 216);
            $this->RowMFillColor(array(
                __pdf_txt($cat_ingreso),
                __pdf_txt($cat_actual),
                __pdf_txt($cat_futura)
            ), true);
            $this->SetTextColor(0, 0, 0);

            $this->SetWidths(array(40, 150));
            $this->SetAligns(array('C', 'L'));
            $this->SetJump(5);

            if ($this->GetY() > 320) {
                $this->AddPage();
                $this->SetXY(10, 35);
            }
            $this->Ln(10);
            $contador++;
        }

        $this->SetWidths(array(160));
        $this->SetAligns(array('L'));
        $this->SetFillColor(200, 250, 216);
        $this->SetFont('ARIAL', 'B', '12');
        $this->SetBorder(true);
        $this->SetJump(8);
        $this->RowMFillColor(array(
            __pdf_txt('Nro Total de Animales en la Categoría ' . $this->nombre_categoria . ': ' . $contador)
        ), true);
    }

    function Cat_Actual($sexo_categoria, $fecha_nac, $mfundos_id, $t_ganaderia)
    {
        $edad = $this->tiempo_t($fecha_nac);

        $sql_data_propia = "select nombre from mcategorias
                            WHERE mtipoganaderias_id = " . intval($t_ganaderia) . "
                              and status = 1
                              and sexo = " . intval($sexo_categoria) . "
                              and mfundos_id = " . intval($mfundos_id) . "
                              and edadminima <= " . intval($edad) . "
                              and edadmaxima > " . intval($edad) . "
                            order by id asc;";

        $this->arrp_data_propia = $this->bd->select($sql_data_propia);

        $sql_data_estandar = "select nombre from mcategorias
                              WHERE mtipoganaderias_id = " . intval($t_ganaderia) . "
                                and status = 1
                                and sexo = " . intval($sexo_categoria) . "
                                and mfundos_id = 0
                                and edadminima <= " . intval($edad) . "
                                and edadmaxima > " . intval($edad) . "
                              order by id asc;";

        $this->arrp_data_estandar = $this->bd->select($sql_data_estandar);

        $cat = '';
        if (!empty($this->arrp_data_propia[0]['nombre'])) {
            $cat = $this->arrp_data_propia[0]['nombre'];
        } elseif (!empty($this->arrp_data_estandar[0]['nombre'])) {
            $cat = $this->arrp_data_estandar[0]['nombre'];
        }
        return $cat;
    }

    function tiempo_t($fecha_nac)
    {
        $fecha_f = date("Y-m-d");
        $fecha_i = $fecha_nac;
        $dias  = (strtotime($fecha_i) - strtotime($fecha_f)) / 86400;
        $dias  = abs($dias);
        $dias  = floor($dias);
        $meses = ($dias / 30);
        $meses = (int)$meses;
        $this->meses = $meses;
        return $this->meses;
    }
}

// ============================================================
// Instancia y salida del PDF
// ============================================================
require_once('../../lib/reporte_contexto_helper.php');

$pdf = new pdfreporte();

/* Nomenclatura de salida: reporte + categoría + fecha de generado */
$fecha_generado = date('d-m-Y');
$pdf->SetTitle(bovs_reporte_titulo(
    'LISTADO DE ANIMALES EN LA CATEGORÍA: ' . $pdf->nombre_categoria,
    array('detalle' => '', 'fecha_generado' => $fecha_generado)
));

$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->Cuerpo();

// IMPORTANTE: FPDF legacy usa Output($name, $dest) — nombre primero.
// Destino 'I' = inline (mostrar en el navegador).
$nombre_archivo = preg_replace('/[^A-Za-z0-9_\-]/', '_', $pdf->nombre_categoria);
$pdf->Output('Categoria_' . $nombre_archivo . '_' . $fecha_generado . '.pdf', 'I');