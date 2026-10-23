<?php
/**
 * lib/general/fpdf/fpdf.php
 * ---------------------------------------------------------------------------
 * Punto de entrada que usan los 35 reportes
 * (`require_once('../../lib/general/fpdf/fpdf.php')`).
 *
 * Este archivo ya NO contiene la clase: solo elige qué motor cargar, para
 * poder convivir con las dos versiones durante la migración.
 *
 *   · Motor '17' (por defecto) → fpdf/fpdf17.php
 *       FPDF 1.7 + capa de tablas del proyecto. Es el motor histórico.
 *
 *   · Motor '19' → fpdf/fpdf_compat.php
 *       FPDF 1.9 + la misma capa de tablas (clase FPDF equivalente).
 *
 * El motor se decide así (en este orden):
 *   1. Constante SOVCA_PDF_ENGINE definida por el script que incluye.
 *   2. Parámetro de URL `?pdf_engine=19` (solo para comparar reportes).
 *   3. Valor de lib/general/pdf_engine.php ('17' por defecto).
 *
 * IMPORTANTE: la decisión se toma antes de compilar ninguna clase. Por eso la
 * 1.7 vive en su propio archivo (fpdf17.php): si estuviera aquí, PHP la
 * registraría al compilar este archivo y no se podría cargar la 1.9.
 */

if (!defined('SOVCA_PDF_ENGINE')) {
    $__sovca_motor = '17';

    $__sovca_cfg = __DIR__ . '/../pdf_engine.php';
    if (is_file($__sovca_cfg)) {
        $__sovca_val = include $__sovca_cfg;
        if (is_string($__sovca_val) && trim($__sovca_val) === '19') {
            $__sovca_motor = '19';
        }
    }

    if (isset($_GET['pdf_engine'])
        && in_array((string) $_GET['pdf_engine'], array('17', '19'), true)
    ) {
        $__sovca_motor = (string) $_GET['pdf_engine'];
    }

    define('SOVCA_PDF_ENGINE', $__sovca_motor);
    unset($__sovca_motor, $__sovca_cfg, $__sovca_val);
}

if (SOVCA_PDF_ENGINE === '19') {
    require_once __DIR__ . '/fpdf_compat.php';
} else {
    // Motor histórico: sus fuentes están en font17/ (formato .php).
    if (!defined('FPDF_FONTPATH')) {
        define('FPDF_FONTPATH', __DIR__ . '/font17/');
    }
    require_once __DIR__ . '/fpdf17.php';
}
