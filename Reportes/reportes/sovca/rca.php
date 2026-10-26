<?php
/* ============================================================
   SOVCA | Wrapper del Reporte de Control Sanitario
   ------------------------------------------------------------
   - Instancia pdfcontrol_animal y lo muestra en el navegador.
   - Valida sesión (mfundos_id) y disponibilidad de datos.
   - Output($nombre, 'I') = inline (mostrar en navegador).
   ============================================================ */

require_once('pdfcontrol_animal.php');
require_once('../../lib/reporte_contexto_helper.php');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$obj        = new pdfreporte();
$mfundos_id = isset($_SESSION['mfundos_id']) ? intval($_SESSION['mfundos_id']) : 0;

/* Nomenclatura de salida: reporte + animal/lote/rango + fecha de generado */
$ctx = bovs_reporte_contexto($mfundos_id);
$obj->contexto_detalle = $ctx['detalle'];
$obj->SetTitle(bovs_reporte_titulo('REPORTE DE CONTROL SANITARIO', $ctx));
$name = bovs_reporte_nombre_archivo('Control_Sanitario', $ctx);

if ($mfundos_id > 0) {

    if ($obj->arrp && count($obj->arrp) > 0) {

        $obj->AliasNbPages();
        $obj->AddPage();
        $obj->Cuerpo();

        // FPDF legacy: Output($name, $dest) — nombre primero, destino después
        $obj->Output($name, 'I');

    } else {
        // Sin datos → aviso consistente y cierre de la ventana emergente
        $sin_datos_texto = 'No existen registros de control sanitario para los filtros seleccionados.';
        include '_sin_datos.php';
    }

} else {
    // Sesión caducada
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Sesión caducada</title>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    </head>
    <body style="font-family: Arial, sans-serif;">
        <script>
            Swal.fire({
                icon: 'warning',
                title: 'Su sesión ha caducado',
                text: 'Por favor, vuelva a iniciar sesión.',
                confirmButtonText: 'Cerrar',
                confirmButtonColor: '#dd4b39'
            }).then(function() {
                window.close();
            });
        </script>
    </body>
    </html>
    <?php
}