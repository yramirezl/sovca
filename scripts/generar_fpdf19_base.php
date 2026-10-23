<?php
/**
 * Regenera Reportes/lib/general/fpdf/generated/fpdf19_base.php
 * a partir de fpdf/fpdf19.php, renombrando la clase a FPDF19Base.
 *
 * Uso:  php scripts/generar_fpdf19_base.php
 *
 * Hay que volver a ejecutarlo cada vez que se actualice fpdf/fpdf19.php
 * desde la distribución oficial de FPDF.
 */

$dirOrigen = __DIR__ . '/../Reportes/lib/general/fpdf';
$origen    = $dirOrigen . '/fpdf19.php';
$destino   = $dirOrigen . '/generated/fpdf19_base.php';

if (!is_file($origen)) {
    fwrite(STDERR, "No se encontró $origen\n");
    exit(1);
}

$codigo = file_get_contents($origen);

// La clase principal de la librería pasa a llamarse FPDF19Base
$codigoNuevo = preg_replace('/^class FPDF\b/m', 'class FPDF19Base', $codigo, 1);

if ($codigoNuevo === null || strpos($codigoNuevo, 'class FPDF19Base') === false) {
    fwrite(STDERR, "No se pudo renombrar la clase FPDF en $origen\n");
    exit(1);
}

$cabecera = "<?php\n"
    . "/**\n"
    . " * ARCHIVO GENERADO — NO EDITAR A MANO.\n"
    . " * Copia de fpdf/fpdf19.php con la clase renombrada a FPDF19Base.\n"
    . " * Se regenera con: php scripts/generar_fpdf19_base.php\n"
    . " */\n";

// Quitar la etiqueta de apertura original del código copiado
$codigoNuevo = preg_replace('/^<\?php\s*/', '', $codigoNuevo, 1);

if (!is_dir(dirname($destino))) {
    mkdir(dirname($destino), 0775, true);
}

file_put_contents($destino, $cabecera . $codigoNuevo);

echo "Generado: $destino (" . filesize($destino) . " bytes)\n";
