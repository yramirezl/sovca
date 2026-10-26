<?php
/* ============================================================
   SOVCA | Reporte PDF de Movimiento de Rebaño
   ------------------------------------------------------------
   Compatible con PHP 7.4 y PHP 8.3.
   - Constructor dual
   - Polyfills FPDF legacy
   - Helper __pdf_txt() UTF-8 → ISO-8859-1
   - Detección robusta de booleano PostgreSQL ('t'/'f')
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

    /* Constructor dual */
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
        $this->mfundos_id = isset($_SESSION['mfundos_id']) ? intval($_SESSION['mfundos_id']) : 0;

        /* Título vacío: el encabezado propio lo imprime getEncabezado() */
        $this->titulo = '';

        /* Leer ID del movimiento desde el parámetro %$% (base64) */
        $parametros = base64_decode(getvalue('%$%'));
        $this->movimiento_id = intval($parametros);

        if ($this->movimiento_id <= 0) {
            die('ID de movimiento inválido');
        }

        /* Datos del fundo */
        $sql_fundo = "SELECT nombre, cid_rif FROM mfundos WHERE id = " . $this->mfundos_id . ";";
        $this->arrp_fundo    = $this->bd->select($sql_fundo);
        $this->nombre_fundo  = isset($this->arrp_fundo[0]['nombre'])  ? $this->arrp_fundo[0]['nombre']  : 'Sin Fundo';
        $this->cid_rif_fundo = isset($this->arrp_fundo[0]['cid_rif']) ? $this->arrp_fundo[0]['cid_rif'] : '';

        /* Movimiento principal */
        $sql_mov = "
            SELECT
                m.id, m.tipo_movimiento, m.fecha_movimiento, m.motivo, m.observaciones,
                m.total_animales, m.revertido, m.fecha_reversion, m.motivo_reversion,
                m.mlotes_origen_id, m.mlotes_destino_id,
                m.mpotreros_origen_id, m.mpotreros_destino_id,
                lo.nombre AS lote_origen_nombre,
                ld.nombre AS lote_destino_nombre,
                po.nombre AS potrero_origen_nombre,
                pd.nombre AS potrero_destino_nombre
            FROM mmovimientos_rebano m
            LEFT JOIN mlotes lo     ON lo.id = m.mlotes_origen_id
            LEFT JOIN mlotes ld     ON ld.id = m.mlotes_destino_id
            LEFT JOIN mpotreros po  ON po.id = m.mpotreros_origen_id
            LEFT JOIN mpotreros pd  ON pd.id = m.mpotreros_destino_id
            WHERE m.id = " . $this->movimiento_id . "
              AND m.mfundos_id = " . $this->mfundos_id . "
            LIMIT 1
        ";
        $this->arrp_mov = $this->bd->select($sql_mov);

        if (!is_array($this->arrp_mov) || count($this->arrp_mov) === 0) {
            die('Movimiento no encontrado');
        }
        $this->mov = $this->arrp_mov[0];

        /* Detalle de animales movidos */
        $sql_detalle = "
            SELECT
                d.codigo_rgb_id_animal,
                d.categoria_anterior_id,
                d.categoria_nueva_id,
                a.nombre  AS nombre_animal,
                a.nro_arete,
                a.sexo,
                cat_ant.nombre AS categoria_anterior_nombre,
                cat_new.nombre AS categoria_nueva_nombre
            FROM mmovimientos_rebano_detalle d
            LEFT JOIN manimales a         ON a.id = d.manimales_id
            LEFT JOIN mcategorias cat_ant ON cat_ant.id = d.categoria_anterior_id
            LEFT JOIN mcategorias cat_new ON cat_new.id = d.categoria_nueva_id
            WHERE d.mmovimientos_rebano_id = " . $this->movimiento_id . "
            ORDER BY a.nombre ASC
        ";
        $this->arrp_detalle = $this->bd->select($sql_detalle);
        $this->reg = 0;
    }

    function getEncabezado($x, $y)
    {
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

        if ($this->nombre_fundo != $this->cid_rif_fundo) {
            $this->datos_fundo = $this->nombre_fundo . '  ' . $this->cid_rif_fundo;
        } else {
            $this->datos_fundo = $this->nombre_fundo;
        }
        $this->RowM(array(__pdf_txt($this->datos_fundo), 'Fecha: ' . $fecha));

        $this->SetXY($x + 35, $y - 5);
        $this->SetJump(6);
        $this->SetWidths(array(120));
        $this->RowM(array(__pdf_txt('MOVIMIENTO DE REBAÑO #' . $this->mov['id'])));
    }

    function Pie_Pagina()
    {
        $this->SetFillColor(230, 230, 230);
        $this->SetTextColor(0, 0, 100);
        $this->SetFont('ARIAL', 'B', 7);
        $this->SetXY(10, 330);
        $this->SetWidths(array(190));
        $this->SetAligns(array('R'));
        $this->RowM(array(__pdf_txt('Sistema SOVCA (https://global.ruted.net)')));
    }

    function Footer()
    {
        $this->y2 = 257;
        $this->SetFillColor(230, 230, 230);
        $this->SetFont('ARIAL', 'B', '8');
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
        $this->SetXY(10, 35);

        /* ============================================================
           BLOQUE 1: DATOS DEL MOVIMIENTO
           ============================================================ */
        $this->SetFont('ARIAL', 'B', 11);
        $this->SetFillColor(100, 200, 116);
        $this->SetTextColor(255, 255, 255);
        $this->SetBorder(true);
        $this->SetWidths(array(190));
        $this->SetAligns(array('C'));
        $this->SetJump(8);
        $this->RowMFillColor(array(__pdf_txt('DATOS DEL MOVIMIENTO')), true);
        $this->SetTextColor(0, 0, 0);

        /* Fecha + Tipo */
        $this->SetFont('ARIAL', 'B', 9);
        $this->SetFillColor(100, 200, 116);
        $this->SetWidths(array(30, 65, 30, 65));
        $this->SetAligns(array('C', 'L', 'C', 'L'));
        $this->SetJump(6);
        $this->SetBorder(true);

        $fecha_mov = '';
        if (!empty($this->mov['fecha_movimiento'])) {
            $fecha_mov = date('d/m/Y H:i', strtotime($this->mov['fecha_movimiento']));
        }

        $this->RowMFillColor(array(
            __pdf_txt('Fecha:'),
            __pdf_txt($fecha_mov),
            __pdf_txt('Tipo:'),
            __pdf_txt($this->mov['tipo_movimiento'])
        ), true);

        /* Origen / Destino / Motivo / Observaciones */
        $this->SetFillColor(200, 250, 216);
        $this->SetFont('ARIAL', '', 9);
        $this->SetTextColor(0, 0, 150);
        $this->SetBorder(true);
        $this->SetJump(6);

        $origen_txt  = $this->armarTextoOrigenDestino('origen');
        $destino_txt = $this->armarTextoOrigenDestino('destino');

        $this->SetWidths(array(30, 160));
        $this->SetAligns(array('C', 'L'));
        $this->RowMFillColor(array(__pdf_txt('Origen:'),  __pdf_txt($origen_txt)), true);
        $this->RowMFillColor(array(__pdf_txt('Destino:'), __pdf_txt($destino_txt)), true);
        $this->RowMFillColor(array(__pdf_txt('Motivo:'),  __pdf_txt($this->mov['motivo'])), true);

        $obs = !empty($this->mov['observaciones']) ? $this->mov['observaciones'] : '(sin observaciones)';
        $this->RowMFillColor(array(__pdf_txt('Observ.:'), __pdf_txt($obs)), true);

        /* ---- Reversión si aplica (detección robusta de booleano PG) ---- */
        $valor_revertido = isset($this->mov['revertido']) ? $this->mov['revertido'] : false;

        /* PostgreSQL devuelve booleanos como 't' (true) o 'f' (false). */
        $es_revertido = (
            $valor_revertido === true  ||
            $valor_revertido === 't'   ||
            $valor_revertido === 'T'   ||
            $valor_revertido === 'true'||
            $valor_revertido === 'TRUE'||
            $valor_revertido === 1     ||
            $valor_revertido === '1'
        );

        if ($es_revertido) {
            $fecha_rev  = '';
            $motivo_rev = '';

            if (!empty($this->mov['fecha_reversion'])) {
                $fecha_rev = date('d/m/Y H:i', strtotime($this->mov['fecha_reversion']));
            }
            if (!empty($this->mov['motivo_reversion'])) {
                $motivo_rev = $this->mov['motivo_reversion'];
            }

            $detalle_rev = trim($fecha_rev . '  —  ' . $motivo_rev, ' —');

            $this->SetTextColor(180, 0, 0);
            $this->SetFillColor(255, 235, 235);
            $this->RowMFillColor(array(__pdf_txt('REVERTIDO:'), __pdf_txt($detalle_rev)), true);
            $this->SetTextColor(0, 0, 0);
        }

        $this->Ln(6);

        /* ============================================================
           BLOQUE 2: ANIMALES MOVIDOS
           ============================================================ */
        $this->SetFont('ARIAL', 'B', 11);
        $this->SetFillColor(100, 200, 116);
        $this->SetTextColor(255, 255, 255);
        $this->SetBorder(true);
        $this->SetWidths(array(190));
        $this->SetAligns(array('C'));
        $this->SetJump(8);
        $this->RowMFillColor(array(__pdf_txt('ANIMALES MOVIDOS')), true);
        $this->SetTextColor(0, 0, 0);

        /* Cabecera de la tabla */
        $this->SetFont('ARIAL', 'B', 9);
        $this->SetFillColor(100, 200, 116);
        $this->SetWidths(array(30, 55, 25, 40, 40));
        $this->SetAligns(array('C', 'C', 'C', 'C', 'C'));
        $this->SetBorder(true);
        $this->SetJump(7);
        $this->RowMFillColor(array(
            __pdf_txt('CÓDIGO'),
            __pdf_txt('NOMBRE'),
            __pdf_txt('SEXO'),
            __pdf_txt('CAT. ANTES'),
            __pdf_txt('CAT. AHORA')
        ), true);

        /* Filas */
        $this->SetFont('ARIAL', '', 8);
        $this->SetFillColor(200, 250, 216);
        $this->SetTextColor(0, 0, 100);
        $this->SetJump(6);
        $contador = 0;

        if (is_array($this->arrp_detalle) && count($this->arrp_detalle) > 0) {

            foreach ($this->arrp_detalle as $d) {

                /* Salto de página + repetir cabecera */
                if ($this->GetY() > 280) {
                    $this->AddPage();
                    $this->Pie_Pagina();
                    $this->SetXY(10, 35);

                    $this->SetFont('ARIAL', 'B', 9);
                    $this->SetFillColor(100, 200, 116);
                    $this->SetTextColor(255, 255, 255);
                    $this->SetWidths(array(30, 55, 25, 40, 40));
                    $this->SetAligns(array('C', 'C', 'C', 'C', 'C'));
                    $this->SetBorder(true);
                    $this->SetJump(7);
                    $this->RowMFillColor(array(
                        __pdf_txt('CÓDIGO'),
                        __pdf_txt('NOMBRE'),
                        __pdf_txt('SEXO'),
                        __pdf_txt('CAT. ANTES'),
                        __pdf_txt('CAT. AHORA')
                    ), true);

                    $this->SetFont('ARIAL', '', 8);
                    $this->SetFillColor(200, 250, 216);
                    $this->SetTextColor(0, 0, 100);
                    $this->SetJump(6);
                }

                $sexo_txt = 'N/D';
                if (intval($d['sexo']) === 1) $sexo_txt = 'HEMBRA';
                elseif (intval($d['sexo']) === 2) $sexo_txt = 'MACHO';

                $cat_ant = !empty($d['categoria_anterior_nombre']) ? $d['categoria_anterior_nombre'] : 'Sin categoría';
                $cat_new = !empty($d['categoria_nueva_nombre'])    ? $d['categoria_nueva_nombre']    : 'Sin cambio';

                $this->SetWidths(array(30, 55, 25, 40, 40));
                $this->SetAligns(array('C', 'L', 'C', 'C', 'C'));
                $this->SetBorder(true);
                $this->RowMFillColor(array(
                    __pdf_txt($d['codigo_rgb_id_animal']),
                    __pdf_txt($d['nombre_animal']),
                    __pdf_txt($sexo_txt),
                    __pdf_txt($cat_ant),
                    __pdf_txt($cat_new)
                ), true);

                $contador++;
            }

        } else {
            /* Sin detalle: mensaje explicativo */
            $this->SetWidths(array(190));
            $this->SetAligns(array('C'));
            $this->SetBorder(true);
            $this->SetFont('ARIAL', 'I', 9);
            $this->SetTextColor(120, 120, 120);
            $this->RowMFillColor(array(
                __pdf_txt('Este movimiento no tiene animales registrados (creado antes de la correccion del sistema).')
            ), true);
            $this->SetTextColor(0, 0, 0);
        }

        $this->Ln(4);

        /* Total */
        $this->SetFont('ARIAL', 'B', 11);
        $this->SetFillColor(200, 250, 216);
        $this->SetTextColor(0, 0, 100);
        $this->SetWidths(array(190));
        $this->SetAligns(array('C'));
        $this->SetBorder(true);
        $this->SetJump(8);
        $this->RowMFillColor(array(__pdf_txt('Nro Total de Animales Movidos: ' . $contador)), true);
        $this->SetTextColor(0, 0, 0);

        /* ============================================================
           FIRMA DEL RESPONSABLE
           ============================================================ */
        if ($this->GetY() > 250) {
            $this->AddPage();
            $this->Pie_Pagina();
            $this->SetXY(10, 35);
        }

        $this->Ln(30);

        $this->SetFont('ARIAL', '', 9);
        $this->SetWidths(array(70, 50, 70));
        $this->SetAligns(array('C', 'C', 'C'));
        $this->SetBorder(false);
        $this->SetJump(6);
        $this->RowM(array('_________________________', '', ''));
        $this->SetFont('ARIAL', '', 8);
        $this->RowM(array(__pdf_txt('Firma del Responsable'), '', ''));
    }

    /* Arma el texto de origen/destino según tipo de movimiento */
    function armarTextoOrigenDestino($lado)
    {
        $lotes    = ($lado === 'origen') ? $this->mov['lote_origen_nombre']    : $this->mov['lote_destino_nombre'];
        $potreros = ($lado === 'origen') ? $this->mov['potrero_origen_nombre'] : $this->mov['potrero_destino_nombre'];

        $partes = array();
        if (!empty($lotes))    $partes[] = 'Lote: '    . $lotes;
        if (!empty($potreros)) $partes[] = 'Potrero: ' . $potreros;

        return count($partes) > 0 ? implode('   |   ', $partes) : '—';
    }
}