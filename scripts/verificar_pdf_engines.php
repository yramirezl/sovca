<?php
/**
 * scripts/verificar_pdf_engines.php
 * ---------------------------------------------------------------------------
 * Genera el mismo documento con los dos motores de PDF y los compara.
 *
 *   php scripts/verificar_pdf_engines.php
 *
 * Útil para validar la migración FPDF 1.7 -> FPDF 1.9 sin abrir el navegador:
 * dibuja una tabla (SetWidths/RowM/RowMFillColor), celdas multilínea con
 * acentos, texto rotado y pie de página (AliasNbPages).
 *
 * Compara: que ambos generen PDF, número de páginas y texto extraído.
 */

if (PHP_SAPI !== 'cli') {
    exit("Solo se puede ejecutar por consola.\n");
}

/* Los reportes definen este polyfill para poder usar FPDF 1.7 en PHP 8.
   Se replica aquí para que la comparación sea representativa. */
if (!function_exists('get_magic_quotes_runtime')) {
    function get_magic_quotes_runtime()
    {
        return false;
    }
}
if (!function_exists('set_magic_quotes_runtime')) {
    function set_magic_quotes_runtime($v)
    {
        return true;
    }
}

/* ---------------------------------------------------------------------------
 * Modo interno: dibujar el PDF con un motor y volcarlo a la salida estándar
 * ------------------------------------------------------------------------- */
if (isset($argv[1]) && strpos($argv[1], '--pdf=') === 0) {
    $motor = substr($argv[1], 6);
    if (!in_array($motor, array('17', '19'), true)) {
        fwrite(STDERR, "Motor no válido: $motor\n");
        exit(2);
    }

    define('SOVCA_PDF_ENGINE', $motor);
    require_once __DIR__ . '/../Reportes/lib/general/fpdf/fpdf.php';

    class PDF extends FPDF
    {
        public function __construct()
        {
            $this->FPDF('P', 'mm', 'A4');
        }

        public function Header()
        {
            $this->SetFont('Arial', 'B', 12);
            $this->Cell(0, 8, 'Reporte de prueba SOVCA', 0, 1, 'C');
            $this->Ln(2);
        }

        public function Footer()
        {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 10, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }
    }

    $pdf = new PDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetFont('Arial', '', 9);

    $pdf->SetWidths(array(30, 55, 35, 30));
    $pdf->SetAligns(array('L', 'L', 'C', 'R'));
    $pdf->SetBorder(true);
    $pdf->SetJump(5);
    $pdf->SetFillTable('DF');

    $pdf->SetFillColor(230, 230, 230);
    $pdf->RowMFillColor(array('Código', 'Nombre', 'Categoría', 'Peso'), true);
    $pdf->SetFillColor(255, 255, 255);

    $filas = array(
        array('SOVCA-02-01', 'Oveja ñandú Pérez', 'Cordero', '12,5 kg'),
        array('SOVCA-02-02', 'Cabra María José Núñez', 'Cabrita', '9,8 kg'),
        array('SOVCA-02-03', 'Ejemplar con nombre muy largo para forzar el salto de línea dentro de la celda', 'Ovino adulto', '45,2 kg'),
        array('SOVCA-02-04', 'Acorazado', 'Carnero', '78,0 kg'),
    );
    foreach ($filas as $fila) {
        $pdf->RowM($fila);
    }

    $pdf->Ln(6);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(60, 6, 'Total de animales: 4', 0, 1, 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->MultiCell(120, 5, 'Observación: lote en cuarentena según control sanitario. Revisión del médico veterinario el 23/10/2026.', 1, 'J');

    $pdf->Ln(6);
    $pdf->Rotate(90, 150, 60);
    $pdf->Cell(60, 6, 'Texto rotado', 0, 0, 'C');
    $pdf->Rotate(0);

    $pdf->Output('verificacion.pdf');
    exit(0);
}

/* ---------------------------------------------------------------------------
 * Modo orquestador
 * ------------------------------------------------------------------------- */
$tmp    = rtrim(sys_get_temp_dir(), '/\\');
$salida = array();
$errores = 0;

foreach (array('17', '19') as $motor) {
    $pdfFile = $tmp . '/verificar_fpdf_' . $motor . '.pdf';
    $errFile = $tmp . '/verificar_fpdf_' . $motor . '.err';
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --pdf=' . $motor
        . ' > ' . escapeshellarg($pdfFile) . ' 2> ' . escapeshellarg($errFile);
    exec($cmd, $basura, $codigo);

    $err = is_file($errFile) ? trim(file_get_contents($errFile)) : '';
    $bytes = is_file($pdfFile) ? filesize($pdfFile) : 0;

    $salida[$motor] = array(
        'pdf'   => $pdfFile,
        'err'   => $err,
        'bytes' => $bytes,
        'code'  => $codigo,
    );

    echo "Motor $motor: exit=$codigo  bytes=$bytes";
    if ($err !== '') {
        echo "  (avisos/errores: " . substr(str_replace("\n", ' | ', $err), 0, 200) . ')';
        $errores++;
    }
    echo "\n";
}

/* Comparación por texto (si está pdftotext) */
$txt = array();
foreach (array('17', '19') as $motor) {
    $txt[$motor] = '';
    if ($salida[$motor]['bytes'] > 0 && trim((string) shell_exec('command -v pdftotext')) !== '') {
        $txt[$motor] = (string) shell_exec(
            'pdftotext -layout ' . escapeshellarg($salida[$motor]['pdf']) . ' - 2>/dev/null'
        );
    }
}

if ($txt['17'] !== '' && $txt['19'] !== '') {
    if ($txt['17'] === $txt['19']) {
        echo "\nTexto extraído: IDÉNTICO en ambos motores.\n";
    } else {
        echo "\nTexto extraído: DIFERENTE (revisar).\n";
        file_put_contents($tmp . '/verificar_fpdf_17.txt', $txt['17']);
        file_put_contents($tmp . '/verificar_fpdf_19.txt', $txt['19']);
        echo "  " . $tmp . "/verificar_fpdf_17.txt\n";
        echo "  " . $tmp . "/verificar_fpdf_19.txt\n";
    }
} else {
    echo "\n(pdftotext no disponible: comparación de texto omitida)\n";
}

echo "\nArchivos generados:\n";
foreach ($salida as $motor => $info) {
    echo "  [$motor] " . $info['pdf'] . "\n";
}

if ($errores > 0) {
    echo "\nSe detectaron avisos; no se puede dar la migración por validada.\n";
    exit(1);
}

exit(0);
