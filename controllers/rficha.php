<?php
include '../lib/validar_session.php';
ValidaSession("../login");

require_once '../models/Vanimale.php';
require_once '../models/Manimale.php';
require_once '../lib/fpdf/fpdf.php';

session_start();
if (empty($_SESSION['mfundos_id'])) { die('Sesión no válida'); }

$mfundos_id  = (int)$_SESSION['mfundos_id'];
$t_ganaderia = (int)($_SESSION['tipo_ganaderia'] ?? 0);
$id_animal   = (int)($_POST['id_animal'] ?? 0);
$qr_data_url = $_POST['qr'] ?? '';

if (!$id_animal) { die('ID inválido'); }

// ---------- Helper de fechas robusto ----------
function fx($val, $fmt = 'd-m-Y') {
    if ($val === null || $val === '' || $val === false) return '';
    if ($val instanceof DateTime)  return $val->format($fmt);
    if (is_string($val)) {
        $ts = strtotime($val);
        return $ts ? date($fmt, $ts) : '';
    }
    return '';
}

// ---------- Datos base ----------
$data = Manimale::find('all', [
    'conditions' => ['id=? and mfundos_id=? and status=1', $id_animal, $mfundos_id]
]);
if (!$data) { die('Animal no encontrado'); }
$rs = $data[0];
$codigo  = $rs->codigo_rgb_id_animal;
$cod_esc = addslashes($codigo);

$f_nac_iso = fx($rs->f_nacimiento, 'Y-m-d');
$f_nac     = fx($rs->f_nacimiento, 'd-m-Y');
$f_ing     = fx($rs->f_ingreso,    'd-m-Y');

$edad_txt = '';
if ($f_nac_iso) {
    $d = (new DateTime($f_nac_iso))->diff(new DateTime());
    $p = [];
    if ($d->y) $p[] = $d->y . ' año' . ($d->y != 1 ? 's' : '');
    if ($d->m) $p[] = $d->m . ' mes' . ($d->m != 1 ? 'es' : '');
    $edad_txt = $p ? implode(' ', $p) : $d->d . ' días';
}

$raza_txt = '';
if ($rs->mrazas_id) {
    $q = Vanimale::find_by_sql("SELECT nombre FROM mrazas WHERE id=" . (int)$rs->mrazas_id . " LIMIT 1");
    if ($q) $raza_txt = $q[0]->nombre;
}

$lote_txt = 'No tiene Lote Asignado';
if ($rs->mlotes_id) {
    $q = Vanimale::find_by_sql("SELECT nombre FROM mlotes WHERE id=" . (int)$rs->mlotes_id . " LIMIT 1");
    if ($q) $lote_txt = $q[0]->nombre;
}

$fundo_nombre = '';
$fundo_rif    = '';
$q = Vanimale::find_by_sql("SELECT nombre, cid_rif FROM mfundos WHERE id=" . $mfundos_id . " LIMIT 1");
if ($q) {
    $fundo_nombre = $q[0]->nombre;
    $fundo_rif    = $q[0]->cid_rif;
}

$sexo_txt = ($rs->sexo == 1) ? 'HEMBRA' : (($rs->sexo == 2) ? 'MACHO' : 'N/D');
$est_map  = [1 => 'ACTIVO', 2 => 'DONADOR ACTIVO', 3 => 'DONADOR'];
$est_txt  = isset($est_map[$rs->estatus_animal]) ? $est_map[$rs->estatus_animal] : 'No Definido';
$apt_map  = [1 => 'LECHE', 2 => 'CARNE', 3 => 'DOBLE PROPÓSITO'];
$apt_txt  = isset($apt_map[$rs->aptitud]) ? $apt_map[$rs->aptitud] : '—';

$foto_path = '';
if ($rs->imagen && file_exists('../dist/img/fotos_animales/' . $rs->imagen)) {
    $foto_path = '../dist/img/fotos_animales/' . $rs->imagen;
}

// ---------- Clase PDF ----------
class FichaPDF extends FPDF {
    public $fundo = '';
    public $rif   = '';
    public $fecha = '';

    function Header() {
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 6, utf8_decode($this->fundo), 0, 1);
        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 4, 'RIF: ' . $this->rif, 0, 1);
        $this->SetFont('Arial', 'B', 15);
        $this->SetTextColor(60, 141, 188);
        $this->Cell(0, 9, 'FICHA DEL ANIMAL', 0, 1, 'C');
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Arial', '', 8);
        $this->Cell(0, 4, 'Fecha: ' . $this->fecha, 0, 1, 'R');
        $this->Ln(2);
        $this->SetDrawColor(60, 141, 188);
        $this->SetLineWidth(0.6);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(4);
    }

    function Footer() {
        $this->SetY(-12);
        $this->SetFont('Arial', 'I', 7);
        $this->SetTextColor(120, 120, 120);
        $this->Cell(0, 8, utf8_decode('Documento generado el ' . date('d-m-Y H:i')), 0, 0, 'C');
    }

    function SeccionTitulo($txt) {
        $this->SetFillColor(60, 141, 188);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(0, 6, '  ' . utf8_decode($txt), 0, 1, 'L', true);
        $this->SetTextColor(0, 0, 0);
        $this->Ln(1);
    }

    function Fila($etq, $val) {
        $this->SetFont('Arial', 'B', 8);
        $this->Cell(40, 5, utf8_decode($etq), 0, 0);
        $this->SetFont('Arial', '', 8);
        $this->Cell(0, 5, utf8_decode($val), 0, 1);
    }
}

// ---------- Construir PDF ----------
$pdf = new FichaPDF('P', 'mm', 'A4');
$pdf->fundo = $fundo_nombre;
$pdf->rif   = $fundo_rif;
$pdf->fecha = date('d-m-Y');
$pdf->SetMargins(10, 10, 10);
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 15);

// Foto + datos
$y0 = $pdf->GetY();
if ($foto_path) { $pdf->Image($foto_path, 12, $y0, 40, 40); }

$pdf->SetXY(56, $y0);
$pdf->SetFont('Arial', 'B', 13);
$pdf->SetTextColor(60, 141, 188);
$pdf->Cell(0, 7, utf8_decode($codigo), 0, 1);
$pdf->SetTextColor(0, 0, 0);

$pdf->SetX(56);
$pdf->SetFont('Arial', 'B', 8); $pdf->Cell(22, 5, 'Nombre:', 0, 0);
$pdf->SetFont('Arial', '', 8);  $pdf->Cell(55, 5, utf8_decode($rs->nombre), 0, 0);
$pdf->SetFont('Arial', 'B', 8); $pdf->Cell(20, 5, 'Arete:', 0, 0);
$pdf->SetFont('Arial', '', 8);  $pdf->Cell(0,  5, utf8_decode($rs->nro_arete ? $rs->nro_arete : 'NO Registrado'), 0, 1);

$pdf->SetX(56);
$pdf->SetFont('Arial', 'B', 8); $pdf->Cell(22, 5, 'Sexo:', 0, 0);
$pdf->SetFont('Arial', '', 8);  $pdf->Cell(55, 5, utf8_decode($sexo_txt), 0, 0);
$pdf->SetFont('Arial', 'B', 8); $pdf->Cell(20, 5, 'Raza:', 0, 0);
$pdf->SetFont('Arial', '', 8);  $pdf->Cell(0,  5, utf8_decode($raza_txt ? $raza_txt : '—'), 0, 1);

$pdf->SetX(56);
$pdf->SetFont('Arial', 'B', 8); $pdf->Cell(22, 5, 'Edad:', 0, 0);
$pdf->SetFont('Arial', '', 8);  $pdf->Cell(55, 5, utf8_decode($edad_txt ? $edad_txt : '—'), 0, 0);
$pdf->SetFont('Arial', 'B', 8); $pdf->Cell(20, 5, 'Estatus:', 0, 0);
$pdf->SetFont('Arial', '', 8);  $pdf->Cell(0,  5, utf8_decode($est_txt), 0, 1);

$pdf->SetX(56);
$pdf->SetFont('Arial', 'B', 8); $pdf->Cell(22, 5, 'Aptitud:', 0, 0);
$pdf->SetFont('Arial', '', 8);  $pdf->Cell(0,  5, utf8_decode($apt_txt), 0, 1);

$pdf->SetY(max($pdf->GetY(), $y0 + 42) + 3);

// Registro
$pdf->SeccionTitulo('DATOS DE REGISTRO');
$pdf->Fila('Fecha nacimiento:', $f_nac ? $f_nac : '—');
$pdf->Fila('Fecha ingreso:',    $f_ing ? $f_ing : '—');
$pdf->Fila('Peso nacimiento:',  $rs->peso_nacimiento ? $rs->peso_nacimiento : '—');
$pdf->Fila('RENA:',             $rs->rena ? $rs->rena : '—');
$pdf->Fila('Alzada:',           $rs->alza ? $rs->alza : '—');
$pdf->Fila('Lote:',             $lote_txt);
$pdf->Fila('Procedencia:',      $rs->procedencia ? $rs->procedencia : '—');
$pdf->Fila('Servicio:',         $rs->servicio ? $rs->servicio : '—');

// Genealogía
$pdf->Ln(2);
$pdf->SeccionTitulo('GENEALOGÍA');
$pdf->Fila('Madre:', trim(($rs->rgd_madre ? $rs->rgd_madre : '—') . '  ' . ($rs->nombre_madre ? $rs->nombre_madre : '')));
$pdf->Fila('Padre:', trim(($rs->rgd_padre ? $rs->rgd_padre : '—') . '  ' . ($rs->nombre_padre ? $rs->nombre_padre : '')));

if ($rs->observaciones) {
    $pdf->Ln(2);
    $pdf->SeccionTitulo('OBSERVACIONES');
    $pdf->SetFont('Arial', '', 8);
    $pdf->MultiCell(0, 4, utf8_decode($rs->observaciones));
}

// Movimientos
$mov_q = @Vanimale::find_by_sql("
    SELECT mr.fecha_movimiento, mr.tipo_movimiento, mr.motivo, mr.observaciones,
           lo.nombre AS lote_origen, ld.nombre AS lote_destino,
           po.nombre AS potrero_origen, pd.nombre AS potrero_destino
      FROM mmovimientos_rebano_detalle mrd
INNER JOIN mmovimientos_rebano mr ON mr.id = mrd.mmovimientos_rebano_id
 LEFT JOIN mlotes     lo ON lo.id = mr.mlotes_origen_id
 LEFT JOIN mlotes     ld ON ld.id = mr.mlotes_destino_id
 LEFT JOIN mpotreros  po ON po.id = mr.mpotreros_origen_id
 LEFT JOIN mpotreros  pd ON pd.id = mr.mpotreros_destino_id
     WHERE mrd.manimales_id = $id_animal AND mr.status = 1
  ORDER BY mr.fecha_movimiento DESC LIMIT 10");

if ($mov_q) {
    $pdf->Ln(3);
    $pdf->SeccionTitulo('HISTORIAL DE MOVIMIENTOS');
    $pdf->SetFont('Arial', 'B', 7.5);
    $pdf->SetFillColor(230, 240, 250);
    $pdf->Cell(22, 5, 'Fecha',    1, 0, 'C', true);
    $pdf->Cell(22, 5, 'Tipo',     1, 0, 'C', true);
    $pdf->Cell(45, 5, 'Origen',   1, 0, 'C', true);
    $pdf->Cell(45, 5, 'Destino',  1, 0, 'C', true);
    $pdf->Cell(0,  5, 'Motivo/Obs.', 1, 1, 'C', true);
    $pdf->SetFont('Arial', '', 7);
    foreach ($mov_q as $m) {
        $orig = trim(($m->lote_origen ? $m->lote_origen : '') .
                     ($m->potrero_origen ? ' / ' . $m->potrero_origen : ''));
        $dest = trim(($m->lote_destino ? $m->lote_destino : '') .
                     ($m->potrero_destino ? ' / ' . $m->potrero_destino : ''));
        $mot  = trim(($m->motivo ? $m->motivo : '') .
                     ($m->observaciones ? ' - ' . $m->observaciones : ''));
        $pdf->Cell(22, 5, fx($m->fecha_movimiento, 'd-m-Y'), 1, 0);
        $pdf->Cell(22, 5, utf8_decode($m->tipo_movimiento), 1, 0);
        $pdf->Cell(45, 5, utf8_decode($orig), 1, 0);
        $pdf->Cell(45, 5, utf8_decode($dest), 1, 0);
        $pdf->Cell(0,  5, utf8_decode($mot), 1, 1);
    }
}

// Leche
$lec_q = @Vanimale::find_by_sql("
    SELECT ROUND(AVG(cantidad_leche_m + cantidad_leche_t), 2) AS promedio,
           MAX(fecha_produccion) AS ultimo,
           COUNT(*)              AS total
      FROM mprolecheras
     WHERE codigo_rgb_id_animal_hembra = '$cod_esc'
       AND mfundos_id = $mfundos_id
       AND status = 1");
if ($lec_q && (int)$lec_q[0]->total > 0) {
    $pdf->Ln(3);
    $pdf->SeccionTitulo('PRODUCCIÓN DE LECHE');
    $pdf->SetFont('Arial', '', 8);
    $pdf->Cell(0, 5, utf8_decode(
        'Promedio: ' . $lec_q[0]->promedio . ' L/día   ·   Último: ' .
        fx($lec_q[0]->ultimo, 'd-m-Y') .
        '   ·   Registros: ' . $lec_q[0]->total), 0, 1);
}

// Sanidad
$san_q = @Vanimale::find_by_sql("
    SELECT fecha_control, evento, responsable, observaciones, fecha_prox_control
      FROM mcontrolsanitarios
     WHERE codigo_rgb_id_animal = '$cod_esc'
       AND mfundos_id = $mfundos_id
       AND status = 1
  ORDER BY fecha_control DESC LIMIT 1");
if ($san_q) {
    $pdf->Ln(3);
    $pdf->SeccionTitulo('CONTROLES SANITARIOS');
    $pdf->Fila('Último control:', fx($san_q[0]->fecha_control, 'd-m-Y'));
    $pdf->Fila('Evento:',         $san_q[0]->evento ? $san_q[0]->evento : '—');
    $pdf->Fila('Responsable:',    $san_q[0]->responsable ? $san_q[0]->responsable : '—');
    $pdf->Fila('Próximo:',        fx($san_q[0]->fecha_prox_control, 'd-m-Y'));
    $pdf->Fila('Observaciones:',  $san_q[0]->observaciones ? $san_q[0]->observaciones : '—');
}

// ---------- QR + Firma ----------
$pdf->Ln(8);
$y = $pdf->GetY();

if ($qr_data_url) {
    $partes = explode(',', $qr_data_url);
    if (count($partes) === 2) {
        $bin = base64_decode($partes[1]);
        $tmp_png = sys_get_temp_dir() . '/qr_' . $id_animal . '_' . time() . '.png';

        // Detectar firma binaria real
        $es_png = (substr($bin, 0, 8) === "\x89PNG\r\n\x1a\n");
        $es_gif = (substr($bin, 0, 3) === 'GIF');

        if ($es_png) {
            // Ya es PNG, guardar directo
            file_put_contents($tmp_png, $bin);
            $pdf->Image($tmp_png, 12, $y, 32, 32);
            @unlink($tmp_png);
        } elseif ($es_gif && function_exists('imagecreatefromstring')) {
            // Convertir GIF → PNG con GD
            $img = @imagecreatefromstring($bin);
            if ($img !== false) {
                // Fondo blanco (GIF trae transparencia que rompe el PDF)
                $w = imagesx($img);
                $h = imagesy($img);
                $bg = imagecreatetruecolor($w, $h);
                $white = imagecolorallocate($bg, 255, 255, 255);
                imagefill($bg, 0, 0, $white);
                imagecopy($bg, $img, 0, 0, 0, 0, $w, $h);

                imagepng($bg, $tmp_png);
                imagedestroy($img);
                imagedestroy($bg);

                $pdf->Image($tmp_png, 12, $y, 32, 32);
                @unlink($tmp_png);
            }
        }
    }
}
$pdf->SetXY(12, $y + 33);
$pdf->SetFont('Arial', 'I', 7);
$pdf->SetTextColor(120, 120, 120);
$pdf->Cell(32, 4, 'Datos offline en QR', 0, 0, 'C');
$pdf->SetTextColor(0, 0, 0);

// Firma
$pdf->SetXY(140, $y + 30);
$pdf->Cell(55, 0, '', 'T');
$pdf->SetXY(140, $y + 31);
$pdf->SetFont('Arial', '', 8);
$pdf->Cell(55, 4, 'Firma del responsable', 0, 0, 'C');

$pdf->Output('I', 'Ficha_' . $codigo . '.pdf');