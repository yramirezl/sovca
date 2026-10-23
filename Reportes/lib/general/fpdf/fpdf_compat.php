<?php
/**
 * lib/general/fpdf/fpdf_compat.php
 * ---------------------------------------------------------------------------
 * Motor PDF moderno: FPDF 1.9 + la capa de compatibilidad que necesitan
 * los reportes del sistema.
 *
 * Por qué existe este archivo:
 *   · `fpdf/fpdf.php` es FPDF 1.7 **más** una capa de tablas propia
 *     (SetWidths, SetAligns, SetBorder, SetJump, SetFillTable, RowM,
 *     RowMFillColor, NbLines, MultiCellBlt, CheckPageBreak, Rotate) y una
 *     normalización de acentos (`_pdfNormalizeText`).
 *   · FPDF 1.9 no trae nada de eso, cambia la firma de `Output()`, usa
 *     `__construct` en lugar del método `FPDF()` y no convierte el texto
 *     UTF-8 a ISO-8859-1.
 *
 * Este archivo carga la 1.9 (clase renombrada a FPDF19Base) y define
 * `class FPDF extends FPDF19Base` con todo lo anterior, de modo que
 * `class PDF extends FPDF` de los reportes siga funcionando sin cambios. *
 * NO se carga por sí solo: lo incluye `lib/general/fpdf/fpdf.php` cuando
 * `lib/general/pdf_engine.php` devuelve '19' (o con ?pdf_engine=19 en la URL,
 * útil para comparar reporte por reporte durante la migración).
 *
 * Si se actualiza `fpdf/fpdf19.php`, regenerar la base con:
 *     php scripts/generar_fpdf19_base.php
 */

if (class_exists('FPDF', false)) {
    // Ya hay una clase FPDF cargada (motor 1.7 o este mismo archivo).
    return;
}

$__base = __DIR__ . '/generated/fpdf19_base.php';

if (!is_file($__base)) {
    trigger_error(
        'FPDF 1.9: falta ' . $__base . '. Ejecute "php scripts/generar_fpdf19_base.php".',
        E_USER_ERROR
    );
    return;
}

require_once $__base;

if (!class_exists('FPDF19Base', false)) {
    trigger_error(
        'FPDF 1.9: generated/fpdf19_base.php no define la clase FPDF19Base.',
        E_USER_ERROR
    );
    return;
}

class FPDF extends FPDF19Base
{
    /* ---------------------------------------------------------------------
     * Estado de la capa de tablas.
     * En la 1.7 eran propiedades dinámicas (`var $widths` no existía); aquí
     * se declaran para no generar avisos de deprecación en PHP 8.2+.
     * ------------------------------------------------------------------- */
    protected $widths = array();
    protected $aligns = array();
    protected $border = false;
    protected $jump   = 4;
    protected $mcfill = 'D';      // relleno del MultiCell de las tablas
    protected $angle  = 0;        // estado de rotación

    /* ---------------------------------------------------------------------
     * Inicialización.
     * Los reportes hacen `$this->FPDF('L','mm','Legal')` desde su
     * `__construct` (estilo PHP 4). En 1.9 el constructor es `__construct`,
     * así que se conserva el método `FPDF()` por compatibilidad.
     * ------------------------------------------------------------------- */
    public function __construct($orientation = 'P', $unit = 'mm', $size = 'A4')
    {
        parent::__construct($orientation, $unit, $size);
        // La base generada vive en fpdf19/generated/, pero las fuentes están
        // en fpdf19/font/: 1.9 las busca junto a su propio archivo.
        $this->fontpath = __DIR__ . '/font/';
    }

    public function FPDF($orientation = 'P', $unit = 'mm', $size = 'A4')
    {
        parent::__construct($orientation, $unit, $size);
        $this->fontpath = __DIR__ . '/font/';
    }

    /** Compatibilidad: en 1.7 `Open()` era un no-op heredado. */
    public function Open()
    {
        // Sin efecto: 1.9 abre el documento en el constructor.
    }

    /* ---------------------------------------------------------------------
     * Normalización de acentos (UTF-8 -> ISO-8859-1).
     * La 1.7 lo hacía dentro de Cell/MultiCell/Write/Text; la 1.9 no, así
     * que se replica aquí para que los textos se vean igual que hoy.
     * La función es idempotente: si el texto ya está en ISO-8859-1 no lo toca.
     * ------------------------------------------------------------------- */
    protected function _pdfNormalizeText($txt)
    {
        if (!is_string($txt) || $txt === '') {
            return $txt;
        }

        $isUtf8 = function_exists('mb_check_encoding')
            ? mb_check_encoding($txt, 'UTF-8')
            : (preg_match('//u', $txt) === 1);
        if (!$isUtf8) {
            return $txt;
        }
        if (!preg_match('/[\x80-\xFF]/', $txt)) {
            return $txt;
        }
        if (function_exists('iconv')) {
            $conv = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $txt);
            if ($conv !== false) {
                return $conv;
            }
        }
        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($txt, 'ISO-8859-1', 'UTF-8');
        }
        return $txt;
    }

    public function Cell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = '')
    {
        return parent::Cell($w, $h, $this->_pdfNormalizeText($txt), $border, $ln, $align, $fill, $link);
    }

    public function MultiCell($w, $h, $txt, $border = 0, $align = 'J', $fill = false)
    {
        return parent::MultiCell($w, $h, $this->_pdfNormalizeText($txt), $border, $align, $fill);
    }

    public function Write($h, $txt, $link = '')
    {
        return parent::Write($h, $this->_pdfNormalizeText($txt), $link);
    }

    public function Text($x, $y, $txt)
    {
        return parent::Text($x, $y, $this->_pdfNormalizeText($txt));
    }

    /* ---------------------------------------------------------------------
     * Salida del documento.
     * La 1.7 de este proyecto usa `Output($nombre)` y siempre descarga
     * (dest 'D'). La 1.9 espera `Output($dest, $nombre, $isUTF8)`; se
     * mantiene la firma histórica para no tocar los 35 reportes.
     * ------------------------------------------------------------------- */
    public function Output($name = '', $dest = '', $isUTF8 = false)
    {
        if ($dest === '') {
            $dest = 'D';
        }
        if ($name === '') {
            $name = 'Documento.pdf';
        }
        return parent::Output(strtoupper($dest), $name, $isUTF8);
    }

    /* =====================================================================
     * CAPA DE TABLAS (idéntica a la de fpdf/fpdf.php 1.7)
     * =================================================================== */

    public function SetWidths($w)
    {
        // Anchos de columna de la tabla
        $this->widths = $w;
        $this->border = false;
        $this->jump   = 4;
        $this->mcfill = 'D';
    }

    public function SetAligns($a = 'L')
    {
        // Alineaciones de las columnas
        if (!is_array($a)) {
            $this->aligns = array();
            $this->aligns = array_pad($this->aligns, count($this->widths), $a);
        } else {
            $this->aligns = $a;
        }
    }

    public function SetBorder($valor = false)
    {
        $this->border = $valor;
    }

    public function SetJump($valor = 4)
    {
        $this->jump = $valor;
    }

    public function SetFillTable($valor = 'D')
    {
        if ($valor == 1) {
            $this->mcfill = 'DF';
        } elseif ($valor == 'D' || $valor == 0) {
            $this->mcfill = 'D';
        } elseif ($valor == 'F') {
            $this->mcfill = 'F';
        } elseif ($valor == 'DF') {
            $this->mcfill = 'DF';
        } elseif ($valor == 'FD') {
            $this->mcfill = 'DF';
        }
    }

    public function RowM($data)
    {
        // Altura de la fila
        for ($i = 0; $i < count($data); $i++) {
            $data[$i] = $this->_pdfNormalizeText($data[$i]);
        }
        $nb = 0;
        for ($i = 0; $i < count($data); $i++) {
            $nb = max($nb, $this->NbLines($this->widths[$i], $data[$i]));
        }
        $h = $this->jump * $nb;

        // Salto de página si no cabe
        $this->CheckPageBreak($h);

        // Celdas de la fila
        for ($i = 0; $i < count($data); $i++) {
            $w = $this->widths[$i];
            $a = isset($this->aligns[$i]) ? $this->aligns[$i] : 'L';
            $x = $this->GetX();
            $y = $this->GetY();
            if ($this->border) {
                $this->Rect($x, $y, $w, $h, $this->mcfill);
            }
            $this->MultiCell($w, $this->jump, $data[$i], 0, $a);
            $this->SetXY($x + $w, $y);
        }

        $this->Ln($h);
    }

    public function RowMFillColor($data, $fill)
    {
        for ($i = 0; $i < count($data); $i++) {
            $data[$i] = $this->_pdfNormalizeText($data[$i]);
        }
        $nb = 0;
        for ($i = 0; $i < count($data); $i++) {
            $nb = max($nb, $this->NbLines($this->widths[$i], $data[$i]));
        }
        $h = $this->jump * $nb;

        $this->CheckPageBreak($h);

        for ($i = 0; $i < count($data); $i++) {
            $w = $this->widths[$i];
            $a = isset($this->aligns[$i]) ? $this->aligns[$i] : 'L';
            $x = $this->GetX();
            $y = $this->GetY();
            if ($this->border) {
                $this->Rect($x, $y, $w, $h, $this->mcfill);
            }
            $this->MultiCell($w, $this->jump, $data[$i], 0, $a, $fill);
            $this->SetXY($x + $w, $y);
        }

        $this->Ln($h);
    }

    public function NbLines($w, $txt)
    {
        // Número de líneas que ocupará un MultiCell de ancho $w
        $txt = $this->_pdfNormalizeText($txt);
        $cw  = $this->CurrentFont['cw'];

        if ($w == 0) {
            $w = $this->w - $this->rMargin - $this->x;
        }
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;

        $s  = str_replace("\r", '', $txt);
        $nb = strlen($s);
        if ($nb > 0 && $s[$nb - 1] == "\n") {
            $nb--;
        }

        $sep = -1;
        $i   = 0;
        $j   = 0;
        $l   = 0;
        $nl  = 1;

        while ($i < $nb) {
            $c = $s[$i];
            if ($c == "\n") {
                $i++;
                $sep = -1;
                $j   = $i;
                $l   = 0;
                $nl++;
                continue;
            }
            if ($c == ' ') {
                $sep = $i;
            }
            $l += isset($cw[$c]) ? $cw[$c] : 0;
            if ($l > $wmax) {
                if ($sep == -1) {
                    if ($i == $j) {
                        $i++;
                    }
                } else {
                    $i = $sep + 1;
                }
                $sep = -1;
                $j   = $i;
                $l   = 0;
                $nl++;
            } else {
                $i++;
            }
        }

        return $nl;
    }

    public function MultiCellBlt($w, $h, $blt, $txt, $border = 0, $align = 'J', $fill = false)
    {
        // MultiCell con viñeta
        $blt_width = $this->GetStringWidth($blt) + $this->cMargin * 2;
        $bak_x = $this->x;

        $this->Cell($blt_width, $h, $blt, 0, '', $fill);
        $this->MultiCell($w - $blt_width, $h, $txt, $border, $align, $fill);

        $this->x = $bak_x;
    }

    public function CheckPageBreak($h)
    {
        if ($this->GetY() + $h > $this->PageBreakTrigger) {
            $this->AddPage($this->CurOrientation);
        }
    }

    public function Rotate($angle, $x = -1, $y = -1)
    {
        if ($x == -1) {
            $x = $this->x;
        }
        if ($y == -1) {
            $y = $this->y;
        }
        if ($this->angle != 0) {
            $this->_out('Q');
        }
        $this->angle = $angle;
        if ($angle != 0) {
            $angle *= M_PI / 180;
            $c  = cos($angle);
            $s  = sin($angle);
            $cx = $x * $this->k;
            $cy = ($this->h - $y) * $this->k;
            $this->_out(sprintf(
                'q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm',
                $c,
                $s,
                -$s,
                $c,
                $cx,
                $cy,
                -$cx,
                -$cy
            ));
        }
    }
}
