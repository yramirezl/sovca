<?php
/* ============================================================
   BOVS | Verificación previa de reportes
   ------------------------------------------------------------
   Devuelve la cantidad de filas que tendría un reporte, SIN
   generar el PDF. El gestor de reportes lo usa para avisar al
   usuario cuando los filtros no arrojan registros, evitando
   abrir una ventana vacía.

   Respuesta:
     { "ok": true, "reporte": "rca", "total": 8, "mensaje": "..." }
   ============================================================ */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

$mfundos_id = isset($_SESSION['mfundos_id']) ? intval($_SESSION['mfundos_id']) : 0;
if ($mfundos_id <= 0) {
    echo json_encode(array(
        'ok'      => false,
        'error'   => 'sesion',
        'mensaje' => 'La sesión no tiene un fundo activo.'
    ));
    exit;
}

$reportes = array(
    'rpl' => 'pdfprodlechera.php',
    'rsa' => 'pdfservicio_animal.php',
    'rca' => 'pdfcontrol_animal.php',
    'rge' => 'pdfgruposetarios.php',
    'rcp' => 'pdfconsecutivo_peso.php',
    'rna' => 'pdfreg_nacimientos.php'
);

$rep = isset($_GET['rep']) ? strtolower(trim($_GET['rep'])) : '';
if (!isset($reportes[$rep])) {
    echo json_encode(array(
        'ok'      => false,
        'error'   => 'reporte',
        'mensaje' => 'Reporte no reconocido.'
    ));
    exit;
}

/*
 * Los constructores de los reportes pueden emitir warnings o texto.
 * Se captura y descarta para que la respuesta JSON quede limpia.
 */
ob_start();
require_once($reportes[$rep]);
$obj = new pdfreporte();
$arrp = isset($obj->arrp) ? $obj->arrp : array();
ob_end_clean();

$total = is_array($arrp) ? count($arrp) : 0;

echo json_encode(array(
    'ok'      => true,
    'reporte' => $rep,
    'total'   => $total,
    'mensaje' => $total > 0
        ? 'El reporte tiene registros para mostrar.'
        : 'No existen registros para los filtros seleccionados.'
));
